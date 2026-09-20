<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\Visit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FR81, 08.5. One method per dashboard widget, in the order 08.5 lists them.
 * Every figure comes from a paid order (`paid_at`), never a placed one — an
 * unpaid order is not revenue (BR25). Dates use the app timezone
 * (Australia/Melbourne), so "today" is the venue's day, not UTC's.
 */
class ReportService
{
    /** Widget 14's threshold: "remaining < 2× buffer" (08.5). */
    private const LOW_STOCK_BUFFER_MULTIPLE = 2;

    /** Widget 12: a cash payment waiting longer than this needs someone (08.5). */
    private const CASH_WAITING_MINUTES = 10;

    public function __construct(
        private readonly StockService $stock,
        private readonly ReservationService $reservations,
    ) {
    }

    /**
     * All 15 widgets in one call, so the controller stays a single line.
     *
     * @return array<string, mixed>
     */
    public function dashboard(int $trendDays = 7): array
    {
        return [
            'sales_today' => $this->salesToday(),
            'orders_today' => $this->ordersToday(),
            'average_order_value' => $this->averageOrderValue(),
            'cash_vs_stripe' => $this->cashVsStripe(),
            'covers_booked_today' => $this->coversBookedToday(),
            'pending_reservation_requests' => $this->pendingReservationRequests(),
            'open_refund_requests' => $this->openRefundRequests(),
            'average_rating' => $this->averageRating(),
            'sales_by_hour' => $this->salesByHourToday(),
            'sales_trend' => $this->salesTrend($trendDays),
            'orders_by_status' => $this->ordersByStatusToday(),
            'needs_attention' => $this->needsAttention(),
            'top_items_today' => $this->topItemsToday(),
            'low_stock' => $this->lowStock(),
            'latest_feedback' => $this->latestFeedback(),
        ];
    }

    /**
     * Widget 1. Gross takings today against the same weekday last week —
     * a Saturday only means anything next to another Saturday.
     *
     * @return array{amount:float, previous:float, change_pct:?float}
     */
    public function salesToday(): array
    {
        $today = $this->grossBetween(today(), today()->endOfDay());
        $lastWeek = $this->grossBetween(today()->subWeek(), today()->subWeek()->endOfDay());

        return [
            'amount' => $today,
            'previous' => $lastWeek,
            'change_pct' => $lastWeek > 0 ? round((($today - $lastWeek) / $lastWeek) * 100, 1) : null,
        ];
    }

    /** Widget 2. Paid orders only — a pending_payment order is not a sale. */
    public function ordersToday(): int
    {
        return $this->paidToday()->count();
    }

    /** Widget 3. */
    public function averageOrderValue(): float
    {
        $orders = $this->ordersToday();

        return $orders === 0 ? 0.0 : round($this->salesToday()['amount'] / $orders, 2);
    }

    /**
     * Widget 4. Share of today's paid amount by method, from the payment
     * rows rather than the orders, because cash rounding and staff
     * adjustments (BR22, BR23) live there.
     *
     * @return array{cash:float, stripe:float, cash_pct:?float}
     */
    public function cashVsStripe(): array
    {
        $byMethod = Payment::where('status', PaymentAttemptStatus::Succeeded)
            ->whereBetween('paid_at', [today(), today()->endOfDay()])
            ->groupBy('method')
            ->select('method', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'method');

        $cash = (float) ($byMethod[PaymentMethod::Cash->value] ?? 0);
        $stripe = (float) ($byMethod[PaymentMethod::Stripe->value] ?? 0);
        $total = $cash + $stripe;

        return [
            'cash' => round($cash, 2),
            'stripe' => round($stripe, 2),
            'cash_pct' => $total > 0 ? round(($cash / $total) * 100, 1) : null,
        ];
    }

    /** Widget 5. Confirmed and seated party sizes — a request is not a cover yet. */
    public function coversBookedToday(): int
    {
        return (int) Reservation::whereDate('booking_date', today())
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Seated])
            ->sum('party_size');
    }

    /** Widget 6. Links to S27. */
    public function pendingReservationRequests(): int
    {
        return Reservation::where('status', ReservationStatus::Requested)->count();
    }

    /** Widget 7. Open = not yet resolved either way, so requested and processing both count. */
    public function openRefundRequests(): int
    {
        return Refund::whereIn('status', [RefundStatus::Requested, RefundStatus::Processing])->count();
    }

    /**
     * Widget 8. Hidden feedback is left out: it is hidden because it was
     * abusive or spam (FR78), and counting it would distort the venue's own
     * quality signal. FR86's report carries the hidden count separately.
     *
     * @return array{food:?float, service:?float, count:int}
     */
    public function averageRating(int $days = 30): array
    {
        $window = Feedback::where('is_hidden', false)
            ->where('submitted_at', '>=', now()->subDays($days));

        $averages = (clone $window)
            ->select(
                DB::raw('AVG(food_rating) as food'),
                DB::raw('AVG(service_rating) as service'),
            )
            ->first();

        return [
            'food' => $averages?->food !== null ? round((float) $averages->food, 1) : null,
            'service' => $averages?->service !== null ? round((float) $averages->service, 1) : null,
            'count' => $window->count(),
        ];
    }

    /**
     * Widget 9. Every hour of today, zero-filled, so the bar chart has no
     * gaps where nothing sold. Grouped in PHP rather than with `HOUR()`:
     * that function does not exist on SQLite, which the test suite runs on,
     * and a day of orders is a small enough set to group in memory.
     *
     * @return array<int, array{hour:int, amount:float}>
     */
    public function salesByHourToday(): array
    {
        $byHour = $this->paidToday()
            ->get(['paid_at', 'total_amount'])
            ->groupBy(fn (Order $order) => (int) $order->paid_at->format('G'))
            ->map(fn (Collection $orders) => $orders->sum('total_amount'));

        return collect(range(0, 23))
            ->map(fn (int $hour) => [
                'hour' => $hour,
                'amount' => round((float) ($byHour[$hour] ?? 0), 2),
            ])
            ->all();
    }

    /**
     * Widget 10. Zero-filled again — a closed day is a real zero, not a
     * missing point the chart should interpolate over. Grouped in PHP for
     * the same portability reason as widget 9 (`DATE()` vs SQLite).
     *
     * @return array<int, array{date:string, amount:float}>
     */
    public function salesTrend(int $days = 7): array
    {
        $from = today()->subDays($days - 1);

        $byDate = Order::whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, today()->endOfDay()])
            ->get(['paid_at', 'total_amount'])
            ->groupBy(fn (Order $order) => $order->paid_at->toDateString())
            ->map(fn (Collection $orders) => $orders->sum('total_amount'));

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($from, $byDate) {
                $date = $from->copy()->addDays($offset)->toDateString();

                return ['date' => $date, 'amount' => round((float) ($byDate[$date] ?? 0), 2)];
            })
            ->all();
    }

    /**
     * Widget 11. Today's orders by status, every status present so the bars
     * keep their order and colour between refreshes.
     *
     * @return array<string, int>
     */
    public function ordersByStatusToday(): array
    {
        $counts = Order::whereBetween('placed_at', [today(), today()->endOfDay()])
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }

    /**
     * Widget 12. The five signals 08.5 names, each as a count plus the rows
     * behind it. Definitions are borrowed from the screens that already own
     * them so the dashboard and the floor never disagree: cash waiting is
     * `FloorController`'s pending cash payment, T−30 is
     * `Staff\Floor\ReservationController`'s window.
     *
     * @return array<string, mixed>
     */
    public function needsAttention(): array
    {
        $stockConflicts = Order::where('has_stock_conflict', true)
            ->with('restaurantTable')
            ->orderBy('paid_at')
            ->get();

        $refundRequests = Refund::whereIn('status', [RefundStatus::Requested, RefundStatus::Processing])
            ->with('order')
            ->orderBy('requested_at')
            ->get();

        $cashWaiting = Payment::where('method', PaymentMethod::Cash)
            ->where('status', PaymentAttemptStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(self::CASH_WAITING_MINUTES))
            ->with('order.restaurantTable')
            ->orderBy('created_at')
            ->get();

        $lateLines = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])
            ->where(fn ($query) => $query
                ->where('kitchen_eta_at', '<', now())
                ->orWhere('bar_eta_at', '<', now()))
            ->whereHas('items', fn ($query) => $query->whereIn('status', [
                OrderItemStatus::Pending,
                OrderItemStatus::Preparing,
            ]))
            ->with('restaurantTable')
            ->orderBy('placed_at')
            ->get();

        $unassignedBookings = $this->unassignedBookingsInsideT30();

        return [
            'stock_conflicts' => $stockConflicts,
            'refund_requests' => $refundRequests,
            'cash_waiting' => $cashWaiting,
            'unassigned_bookings' => $unassignedBookings,
            'late_lines' => $lateLines,
            'total' => $stockConflicts->count()
                + $refundRequests->count()
                + $cashWaiting->count()
                + $unassignedBookings->count()
                + $lateLines->count(),
        ];
    }

    /**
     * Widget 13. Quantity and revenue from the line snapshots (BR15), not
     * from today's menu prices — a sale price that ended at noon must not
     * repost the morning's takings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topItemsToday(int $limit = 5): array
    {
        return OrderItem::whereHas('order', fn ($query) => $query
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [today(), today()->endOfDay()]))
            ->groupBy('item_id', 'item_name')
            ->select(
                'item_id',
                'item_name',
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(line_total) as revenue'),
            )
            ->orderByDesc(DB::raw('SUM(quantity)'))
            ->limit($limit)
            ->get()
            ->map(fn (OrderItem $row) => [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Widget 14. Two different problems in one list: switched off by hand
     * (FR29), or close enough to the daily limit that QR checkout is about
     * to start refusing it (BR09's buffer, doubled per 08.5).
     *
     * @return array<int, array<string, mixed>>
     */
    public function lowStock(): array
    {
        return MenuItem::where('is_active', true)
            ->where(fn ($query) => $query
                ->where('is_available', false)
                ->orWhereNotNull('daily_limit'))
            ->orderBy('item_name')
            ->get()
            ->map(fn (MenuItem $item) => [
                'item' => $item,
                'remaining' => $item->daily_limit === null ? null : $this->stock->remaining($item),
                'is_available' => (bool) $item->is_available,
            ])
            ->filter(fn (array $row) => ! $row['is_available'] || $this->isLowStock($row['remaining']))
            ->values()
            ->all();
    }

    /** Widget 15. */
    public function latestFeedback(int $limit = 5): Collection
    {
        return Feedback::with(['customer', 'order'])
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Order> */
    private function paidToday()
    {
        return Order::whereNotNull('paid_at')
            ->whereBetween('paid_at', [today(), today()->endOfDay()]);
    }

    private function grossBetween(Carbon $from, Carbon $to): float
    {
        return round((float) Order::whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('total_amount'), 2);
    }

    private function isLowStock(?int $remaining): bool
    {
        if ($remaining === null) {
            return false;
        }

        return $remaining < self::LOW_STOCK_BUFFER_MULTIPLE * $this->stock->bufferMultiplier();
    }

    /**
     * FR68's window, as `Staff\Floor\ReservationController` defines it: a
     * booking with no open visit, between 30 minutes before and 15 after.
     *
     * @return Collection<int, Reservation>
     */
    private function unassignedBookingsInsideT30(): Collection
    {
        return Reservation::whereDate('booking_date', today())
            ->whereIn('status', [ReservationStatus::Requested, ReservationStatus::Confirmed])
            ->with(['customer', 'visits'])
            ->get()
            ->filter(function (Reservation $reservation) {
                $bookedAt = $this->reservations->bookedAt($reservation);

                return $reservation->visits->whereNull('closed_at')->isEmpty()
                    && now()->betweenIncluded($bookedAt->copy()->subMinutes(30), $bookedAt->copy()->addMinutes(15));
            })
            ->values();
    }

    /**
     * FR82. Sales Report: Gross, GST, sale discounts, cash adjustments, refunds, net, method split, order source, cash by staff.
     *
     * @return array<string, mixed>
     */
    public function salesReport(Carbon $from, Carbon $to): array
    {
        $payments = Payment::where('status', PaymentAttemptStatus::Succeeded)
            ->whereBetween('paid_at', [$from, $to])
            ->with(['order.items', 'recordedBy.role'])
            ->get();

        $gross = round((float) $payments->sum('amount'), 2);
        $gst = round($gross / 11, 2);

        $paidOrders = Order::whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->with(['items', 'customer', 'takenBy'])
            ->get();

        $saleDiscounts = round((float) $paidOrders->flatMap->items->sum(function (OrderItem $item) {
            return max(0, ((float) $item->original_unit_price - (float) $item->unit_price) * $item->quantity);
        }), 2);

        $adjustments = round((float) $payments->where('method', PaymentMethod::Cash)->sum('adjustment_amount'), 2);

        $refunds = round((float) Refund::where('status', RefundStatus::Completed)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('completed_at', [$from, $to])
                    ->orWhere(fn ($q2) => $q2->whereNull('completed_at')->whereBetween('requested_at', [$from, $to]));
            })
            ->sum('amount'), 2);

        $net = round($gross - $refunds, 2);

        // Payment method split
        $cashPayments = $payments->where('method', PaymentMethod::Cash);
        $stripePayments = $payments->where('method', PaymentMethod::Stripe);
        $cashTotal = round((float) $cashPayments->sum('amount'), 2);
        $stripeTotal = round((float) $stripePayments->sum('amount'), 2);

        // Order sources (QR / online vs waitstaff taken)
        $qrOrders = $paidOrders->whereNull('taken_by_staff_id');
        $staffOrders = $paidOrders->whereNotNull('taken_by_staff_id');
        $qrTotal = round((float) $qrOrders->sum('total_amount'), 2);
        $staffTotal = round((float) $staffOrders->sum('total_amount'), 2);

        // Cash collected by staff
        $cashByStaff = $cashPayments->groupBy('recorded_by_staff_id')
            ->map(function (Collection $group, $staffId) {
                $staff = $group->first()?->recordedBy;

                return [
                    'staff_id' => $staffId,
                    'staff_name' => $staff?->name ?? ($staffId ? 'Staff #'.$staffId : 'System / Unassigned'),
                    'role' => $staff?->role?->role_name ?? 'staff',
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        // Daily breakdown
        $daily = $payments->groupBy(fn (Payment $p) => $p->paid_at->toDateString())
            ->map(function (Collection $group, string $date) {
                $dayGross = round((float) $group->sum('amount'), 2);

                return [
                    'date' => $date,
                    'gross' => $dayGross,
                    'gst' => round($dayGross / 11, 2),
                    'count' => $group->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        return [
            'gross_sales' => $gross,
            'gst_amount' => $gst,
            'sale_discounts' => $saleDiscounts,
            'cash_adjustments' => $adjustments,
            'refunds' => $refunds,
            'net_sales' => $net,
            'total_orders' => $paidOrders->count(),
            'method_split' => [
                'cash_amount' => $cashTotal,
                'cash_count' => $cashPayments->count(),
                'cash_pct' => $gross > 0 ? round(($cashTotal / $gross) * 100, 1) : 0,
                'stripe_amount' => $stripeTotal,
                'stripe_count' => $stripePayments->count(),
                'stripe_pct' => $gross > 0 ? round(($stripeTotal / $gross) * 100, 1) : 0,
            ],
            'source_split' => [
                'qr_amount' => $qrTotal,
                'qr_count' => $qrOrders->count(),
                'qr_pct' => $gross > 0 ? round(($qrTotal / $gross) * 100, 1) : 0,
                'staff_amount' => $staffTotal,
                'staff_count' => $staffOrders->count(),
                'staff_pct' => $gross > 0 ? round(($staffTotal / $gross) * 100, 1) : 0,
            ],
            'cash_by_staff' => $cashByStaff,
            'daily' => $daily,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * FR83. Item and Category Report: top/bottom sellers, category sales, sold-out occurrences.
     *
     * @return array<string, mixed>
     */
    public function itemsReport(Carbon $from, Carbon $to): array
    {
        $paidOrderItems = OrderItem::whereHas('order', fn ($q) => $q
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to]))
            ->with('menuItem.category')
            ->get();

        $byItem = $paidOrderItems->groupBy('item_id')
            ->map(function (Collection $lines) {
                $first = $lines->first();
                $qty = (int) $lines->sum('quantity');
                $revenue = round((float) $lines->sum('line_total'), 2);

                return [
                    'item_id' => $first->item_id,
                    'item_name' => $first->item_name,
                    'category_name' => $first->menuItem?->category?->category_name ?? 'Uncategorised',
                    'quantity' => $qty,
                    'revenue' => $revenue,
                ];
            });

        $topSellers = $byItem->sortByDesc('quantity')->take(10)->values();

        // Bottom sellers among active items
        $activeItems = MenuItem::where('is_active', true)->with('category')->get();
        $bottomSellers = $activeItems->map(function (MenuItem $item) use ($byItem) {
            $sold = $byItem->get($item->item_id);

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'category_name' => $item->category?->category_name ?? 'Uncategorised',
                'quantity' => $sold['quantity'] ?? 0,
                'revenue' => $sold['revenue'] ?? 0.0,
            ];
        })
            ->sortBy('quantity')
            ->take(10)
            ->values();

        // Category breakdown
        $categorySales = $paidOrderItems->groupBy(fn (OrderItem $i) => $i->menuItem?->category?->category_name ?? 'Uncategorised')
            ->map(function (Collection $lines, string $catName) {
                return [
                    'category_name' => $catName,
                    'quantity' => (int) $lines->sum('quantity'),
                    'revenue' => round((float) $lines->sum('line_total'), 2),
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        // Sold-out occurrences
        $soldOutItems = MenuItem::where('is_active', true)
            ->where('is_available', false)
            ->with('category')
            ->get();

        $toggleCount = AuditLog::whereIn('action_type', [
            'menu_item_availability_toggled',
            'add_on_option_availability_toggled',
        ])
            ->whereBetween('logged_at', [$from, $to])
            ->count();

        return [
            'top_sellers' => $topSellers,
            'bottom_sellers' => $bottomSellers,
            'category_sales' => $categorySales,
            'sold_out_items' => $soldOutItems,
            'toggle_count' => $toggleCount,
            'total_items_sold' => (int) $paidOrderItems->sum('quantity'),
            'total_revenue' => round((float) $paidOrderItems->sum('line_total'), 2),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * FR84. Operations Report: peak hours, station prep times, ETA accuracy, table turnover.
     *
     * @return array<string, mixed>
     */
    public function operationsReport(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->with('items')
            ->get();

        // Peak hours (0 to 23)
        $byHour = $orders->groupBy(fn (Order $o) => (int) $o->placed_at->format('G'));
        $peakHours = collect(range(0, 23))->map(function (int $h) use ($byHour) {
            $group = $byHour->get($h, collect());

            return [
                'hour' => $h,
                'orders_count' => $group->count(),
                'gross_sales' => round((float) $group->sum('total_amount'), 2),
            ];
        })->all();

        // Average prep time per station
        $preparedLines = OrderItem::whereHas('order', fn ($q) => $q
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to]))
            ->whereNotNull('prepared_at')
            ->with('order')
            ->get();

        $kitchenLines = $preparedLines->where('destination', 'kitchen');
        $barLines = $preparedLines->where('destination', 'bar');

        $kitchenAvgPrep = $kitchenLines->isNotEmpty()
            ? round((float) $kitchenLines->avg(fn (OrderItem $i) => $i->prepared_at->diffInMinutes($i->order->paid_at)), 1)
            : null;

        $barAvgPrep = $barLines->isNotEmpty()
            ? round((float) $barLines->avg(fn (OrderItem $i) => $i->prepared_at->diffInMinutes($i->order->paid_at)), 1)
            : null;

        // ETA Accuracy
        $kitchenEtaOrders = $orders->filter(fn (Order $o) => $o->kitchen_eta_at !== null && $o->ready_at !== null);
        $kitchenOnTime = $kitchenEtaOrders->isNotEmpty()
            ? round(($kitchenEtaOrders->filter(fn (Order $o) => $o->ready_at <= $o->kitchen_eta_at)->count() / $kitchenEtaOrders->count()) * 100, 1)
            : null;

        $barEtaOrders = $orders->filter(fn (Order $o) => $o->bar_eta_at !== null && $o->ready_at !== null);
        $barOnTime = $barEtaOrders->isNotEmpty()
            ? round(($barEtaOrders->filter(fn (Order $o) => $o->ready_at <= $o->bar_eta_at)->count() / $barEtaOrders->count()) * 100, 1)
            : null;

        // Table turnover (completed visits)
        $visits = Visit::whereBetween('created_at', [$from, $to])
            ->whereNotNull('opened_at')
            ->whereNotNull('closed_at')
            ->get();

        $avgTurnover = $visits->isNotEmpty()
            ? round((float) $visits->avg(fn (Visit $v) => $v->closed_at->diffInMinutes($v->opened_at)), 1)
            : null;

        $visitsByCovers = [
            '1_2' => $visits->filter(fn (Visit $v) => ($v->guest_count ?? 2) <= 2),
            '3_6' => $visits->filter(fn (Visit $v) => ($v->guest_count ?? 2) >= 3 && ($v->guest_count ?? 2) <= 6),
            '7_plus' => $visits->filter(fn (Visit $v) => ($v->guest_count ?? 2) >= 7),
        ];

        return [
            'peak_hours' => $peakHours,
            'kitchen_avg_prep' => $kitchenAvgPrep,
            'bar_avg_prep' => $barAvgPrep,
            'kitchen_on_time_pct' => $kitchenOnTime,
            'bar_on_time_pct' => $barOnTime,
            'avg_turnover_minutes' => $avgTurnover,
            'completed_visits_count' => $visits->count(),
            'turnover_by_size' => [
                '1_2' => $visitsByCovers['1_2']->isNotEmpty() ? round((float) $visitsByCovers['1_2']->avg(fn (Visit $v) => $v->closed_at->diffInMinutes($v->opened_at)), 1) : null,
                '3_6' => $visitsByCovers['3_6']->isNotEmpty() ? round((float) $visitsByCovers['3_6']->avg(fn (Visit $v) => $v->closed_at->diffInMinutes($v->opened_at)), 1) : null,
                '7_plus' => $visitsByCovers['7_plus']->isNotEmpty() ? round((float) $visitsByCovers['7_plus']->avg(fn (Visit $v) => $v->closed_at->diffInMinutes($v->opened_at)), 1) : null,
            ],
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * FR85. Reservation Report: bookings, covers, approval/decline rate, no-show rate, late cancellations, walk-in visits.
     *
     * @return array<string, mixed>
     */
    public function reservationsReport(Carbon $from, Carbon $to): array
    {
        $reservations = Reservation::whereDate('booking_date', '>=', $from->toDateString())
            ->whereDate('booking_date', '<=', $to->toDateString())
            ->get();

        $totalBookings = $reservations->count();
        $totalCovers = (int) $reservations->whereIn('status', [
            ReservationStatus::Confirmed,
            ReservationStatus::Seated,
            ReservationStatus::Completed,
        ])->sum('party_size');

        $byStatus = collect(ReservationStatus::cases())->mapWithKeys(fn (ReservationStatus $s) => [
            $s->value => $reservations->where('status', $s)->count(),
        ]);

        $reviewed = $reservations->whereIn('status', [
            ReservationStatus::Confirmed,
            ReservationStatus::Seated,
            ReservationStatus::Completed,
            ReservationStatus::Declined,
            ReservationStatus::NoShow,
        ])->count();

        $approved = $reservations->whereIn('status', [
            ReservationStatus::Confirmed,
            ReservationStatus::Seated,
            ReservationStatus::Completed,
            ReservationStatus::NoShow,
        ])->count();

        $approvalRate = $reviewed > 0 ? round(($approved / $reviewed) * 100, 1) : null;

        $finished = $reservations->whereIn('status', [
            ReservationStatus::Seated,
            ReservationStatus::Completed,
            ReservationStatus::NoShow,
        ])->count();

        $noShows = $reservations->where('status', ReservationStatus::NoShow)->count();
        $noShowRate = $finished > 0 ? round(($noShows / $finished) * 100, 1) : null;

        $lateCancellations = $reservations->filter(function (Reservation $r) {
            return $r->status === ReservationStatus::Cancelled && $r->is_late_cancellation;
        })->count();

        $walkIns = Visit::whereBetween('created_at', [$from, $to])
            ->whereNull('reservation_id')
            ->count();

        return [
            'total_bookings' => $totalBookings,
            'total_covers' => $totalCovers,
            'status_counts' => $byStatus->all(),
            'approval_rate' => $approvalRate,
            'no_show_rate' => $noShowRate,
            'no_show_count' => $noShows,
            'late_cancellations' => $lateCancellations,
            'walk_in_visits' => $walkIns,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * FR86. Feedback Report: averages, trends, distribution, hidden count.
     *
     * @return array<string, mixed>
     */
    public function feedbackReport(Carbon $from, Carbon $to): array
    {
        $allFeedback = Feedback::whereBetween('submitted_at', [$from, $to])
            ->with(['order', 'customer'])
            ->get();

        $validFeedback = $allFeedback->where('is_hidden', false);
        $hiddenFeedback = $allFeedback->where('is_hidden', true);

        $foodAvg = $validFeedback->isNotEmpty() ? round((float) $validFeedback->avg('food_rating'), 1) : null;
        $serviceAvg = $validFeedback->isNotEmpty() ? round((float) $validFeedback->avg('service_rating'), 1) : null;
        $overallAvg = ($foodAvg !== null && $serviceAvg !== null) ? round(($foodAvg + $serviceAvg) / 2, 1) : null;

        $totalValid = $validFeedback->count();
        $foodDist = collect(range(1, 5))->mapWithKeys(function (int $star) use ($validFeedback, $totalValid) {
            $count = $validFeedback->where('food_rating', $star)->count();

            return [$star => [
                'count' => $count,
                'pct' => $totalValid > 0 ? round(($count / $totalValid) * 100, 1) : 0,
            ]];
        })->all();

        $serviceDist = collect(range(1, 5))->mapWithKeys(function (int $star) use ($validFeedback, $totalValid) {
            $count = $validFeedback->where('service_rating', $star)->count();

            return [$star => [
                'count' => $count,
                'pct' => $totalValid > 0 ? round(($count / $totalValid) * 100, 1) : 0,
            ]];
        })->all();

        return [
            'total_reviews' => $totalValid,
            'food_avg' => $foodAvg,
            'service_avg' => $serviceAvg,
            'overall_avg' => $overallAvg,
            'food_distribution' => $foodDist,
            'service_distribution' => $serviceDist,
            'hidden_count' => $hiddenFeedback->count(),
            'hidden_items' => $hiddenFeedback->values(),
            'recent_reviews' => $validFeedback->sortByDesc('submitted_at')->take(10)->values(),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * FR87. Staff Activity Report: cash payments, adjustments, refund requests, toggles, overrides per staff.
     *
     * @return array<string, mixed>
     */
    public function staffActivityReport(Carbon $from, Carbon $to): array
    {
        $staffMembers = Staff::with('role')->get();

        $cashPayments = Payment::where('method', PaymentMethod::Cash)
            ->where('status', PaymentAttemptStatus::Succeeded)
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        $refundRequests = Refund::whereBetween('requested_at', [$from, $to])->get();

        $logs = AuditLog::whereBetween('logged_at', [$from, $to])->get();

        $activity = $staffMembers->map(function (Staff $staff) use ($cashPayments, $refundRequests, $logs) {
            $staffCash = $cashPayments->where('recorded_by_staff_id', $staff->staff_id);
            $staffRefunds = $refundRequests->where('requested_by_staff_id', $staff->staff_id);
            $staffLogs = $logs->where('staff_id', $staff->staff_id);

            $toggles = $staffLogs->whereIn('action_type', [
                'menu_item_availability_toggled',
                'add_on_option_availability_toggled',
            ])->count();

            $overrides = $staffLogs->where('action_type', 'table_override')->count();

            return [
                'staff' => $staff,
                'cash_count' => $staffCash->count(),
                'cash_total' => round((float) $staffCash->sum('amount'), 2),
                'adjustments_count' => $staffCash->whereNotNull('adjustment_amount')->filter(fn ($p) => (float) $p->adjustment_amount != 0.0)->count(),
                'adjustments_total' => round((float) $staffCash->sum('adjustment_amount'), 2),
                'refund_requests_count' => $staffRefunds->count(),
                'toggles_count' => $toggles,
                'overrides_count' => $overrides,
                'total_actions' => $staffCash->count() + $staffRefunds->count() + $toggles + $overrides,
            ];
        })
            ->sortByDesc('total_actions')
            ->values();

        return [
            'staff_activity' => $activity,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * FR88. Export report as formatted CSV string.
     */
    public function exportCsv(string $type, Carbon $from, Carbon $to): string
    {
        $handle = fopen('php://temp', 'r+');

        $data = match ($type) {
            'sales' => $this->salesReport($from, $to),
            'items' => $this->itemsReport($from, $to),
            'operations' => $this->operationsReport($from, $to),
            'reservations' => $this->reservationsReport($from, $to),
            'feedback' => $this->feedbackReport($from, $to),
            'staff' => $this->staffActivityReport($from, $to),
            'ai' => $this->aiReport($from, $to),
            default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };

        $typeNames = [
            'sales' => 'Sales Report',
            'items' => 'Item & Category Report',
            'operations' => 'Operations Report',
            'reservations' => 'Reservation Report',
            'feedback' => 'Feedback Report',
            'staff' => 'Staff Activity Report',
            'ai' => 'AI Usage Report',
        ];

        fputcsv($handle, ['Report Type', $typeNames[$type] ?? ucfirst($type).' Report']);
        fputcsv($handle, ['Date Range', $from->format('Y-m-d').' to '.$to->format('Y-m-d')]);
        fputcsv($handle, ['Generated At', now()->toDateTimeString()]);
        fputcsv($handle, []);

        match ($type) {
            'sales' => $this->writeSalesCsv($handle, $data),
            'items' => $this->writeItemsCsv($handle, $data),
            'operations' => $this->writeOperationsCsv($handle, $data),
            'reservations' => $this->writeReservationsCsv($handle, $data),
            'feedback' => $this->writeFeedbackCsv($handle, $data),
            'staff' => $this->writeStaffCsv($handle, $data),
            'ai' => $this->writeAiCsv($handle, $data),
        };

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    /**
     * FR88. Export report as PDF binary string.
     */
    public function exportPdf(string $type, Carbon $from, Carbon $to): string
    {
        $data = match ($type) {
            'sales' => $this->salesReport($from, $to),
            'items' => $this->itemsReport($from, $to),
            'operations' => $this->operationsReport($from, $to),
            'reservations' => $this->reservationsReport($from, $to),
            'feedback' => $this->feedbackReport($from, $to),
            'staff' => $this->staffActivityReport($from, $to),
            'ai' => $this->aiReport($from, $to),
            default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };

        $typeNames = [
            'sales' => 'Sales Report',
            'items' => 'Item & Category Report',
            'operations' => 'Operations Report',
            'reservations' => 'Reservation Report',
            'feedback' => 'Feedback Report',
            'staff' => 'Staff Activity Report',
            'ai' => 'AI Usage Report',
        ];

        $venue = app(SettingService::class)->venue();

        return Pdf::loadView('pdf.report', [
            'type' => $type,
            'typeName' => $typeNames[$type] ?? ucfirst($type).' Report',
            'data' => $data,
            'from' => $from,
            'to' => $to,
            'venue' => $venue,
            'generatedAt' => now(),
        ])->output();
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeSalesCsv($handle, array $data): void
    {
        fputcsv($handle, ['Metric', 'Value (AUD)']);
        fputcsv($handle, ['Gross Sales', sprintf('$%.2f', $data['gross_sales'])]);
        fputcsv($handle, ['Net Takings', sprintf('$%.2f', $data['net_sales'])]);
        fputcsv($handle, ['GST Liability (1/11th)', sprintf('$%.2f', $data['gst_amount'])]);
        fputcsv($handle, ['Total Paid Orders', (string) $data['total_orders']]);
        fputcsv($handle, ['Cash Takings', sprintf('$%.2f', $data['method_split']['cash_amount'])]);
        fputcsv($handle, ['Stripe Takings', sprintf('$%.2f', $data['method_split']['stripe_amount'])]);
        fputcsv($handle, ['Total Refunds', sprintf('$%.2f', $data['refunds'])]);
        fputcsv($handle, ['Discounts & Adjustments', sprintf('$%.2f', $data['sale_discounts'] + $data['cash_adjustments'])]);
        fputcsv($handle, []);

        fputcsv($handle, ['Daily Sales Breakdown']);
        fputcsv($handle, ['Date', 'Orders', 'Gross Sales', 'GST Liability']);
        foreach ($data['daily'] as $day) {
            fputcsv($handle, [
                $day['date'],
                $day['count'],
                sprintf('$%.2f', $day['gross']),
                sprintf('$%.2f', $day['gst']),
            ]);
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeItemsCsv($handle, array $data): void
    {
        fputcsv($handle, ['Top Selling Items']);
        fputcsv($handle, ['Rank', 'Item Name', 'Category', 'Quantity Sold', 'Gross Revenue']);
        foreach ($data['top_sellers'] as $idx => $item) {
            fputcsv($handle, [
                $idx + 1,
                $item['item_name'],
                $item['category_name'],
                $item['quantity'],
                sprintf('$%.2f', $item['revenue']),
            ]);
        }
        fputcsv($handle, []);

        fputcsv($handle, ['Category Breakdown']);
        fputcsv($handle, ['Category Name', 'Items Sold', 'Gross Revenue']);
        foreach ($data['category_sales'] as $cat) {
            fputcsv($handle, [
                $cat['category_name'],
                $cat['quantity'],
                sprintf('$%.2f', $cat['revenue']),
            ]);
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeOperationsCsv($handle, array $data): void
    {
        fputcsv($handle, ['Metric', 'Value']);
        fputcsv($handle, ['Kitchen On-Time Rate', $data['kitchen_on_time_pct'] !== null ? $data['kitchen_on_time_pct'].'%' : '—']);
        fputcsv($handle, ['Bar On-Time Rate', $data['bar_on_time_pct'] !== null ? $data['bar_on_time_pct'].'%' : '—']);
        fputcsv($handle, ['Completed Dining Visits', (string) $data['completed_visits_count']]);
        fputcsv($handle, ['Average Turnover (All Visits)', $data['avg_turnover_minutes'] !== null ? $data['avg_turnover_minutes'].' min' : '—']);
        fputcsv($handle, ['Average Turnover (1–2 covers)', $data['turnover_by_size']['1_2'] !== null ? $data['turnover_by_size']['1_2'].' min' : '—']);
        fputcsv($handle, ['Average Turnover (3–6 covers)', $data['turnover_by_size']['3_6'] !== null ? $data['turnover_by_size']['3_6'].' min' : '—']);
        fputcsv($handle, ['Average Turnover (7+ covers)', $data['turnover_by_size']['7_plus'] !== null ? $data['turnover_by_size']['7_plus'].' min' : '—']);
        fputcsv($handle, []);

        fputcsv($handle, ['Orders by Hour (Peak Times)']);
        fputcsv($handle, ['Hour', 'Orders Placed', 'Gross Sales']);
        foreach ($data['peak_hours'] as $hourData) {
            fputcsv($handle, [
                sprintf('%02d:00 – %02d:59', $hourData['hour'], $hourData['hour']),
                $hourData['orders_count'],
                sprintf('$%.2f', $hourData['gross_sales']),
            ]);
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeReservationsCsv($handle, array $data): void
    {
        fputcsv($handle, ['Metric', 'Value']);
        fputcsv($handle, ['Total Bookings', (string) $data['total_bookings']]);
        fputcsv($handle, ['Total Covers', (string) $data['total_covers']]);
        fputcsv($handle, ['Approval Rate', $data['approval_rate'] !== null ? $data['approval_rate'].'%' : '—']);
        fputcsv($handle, ['No-Show Rate', $data['no_show_rate'] !== null ? $data['no_show_rate'].'%' : '0%']);
        fputcsv($handle, ['No-Show Count', (string) $data['no_show_count']]);
        fputcsv($handle, ['Late Cancellations (< 2h)', (string) $data['late_cancellations']]);
        fputcsv($handle, ['Walk-in Visits', (string) $data['walk_in_visits']]);
        fputcsv($handle, []);

        fputcsv($handle, ['Booking Status Distribution']);
        fputcsv($handle, ['Status', 'Count']);
        foreach ($data['status_counts'] as $status => $count) {
            fputcsv($handle, [ucfirst($status), $count]);
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeFeedbackCsv($handle, array $data): void
    {
        fputcsv($handle, ['Metric', 'Value']);
        fputcsv($handle, ['Overall Average Rating', $data['overall_avg'] !== null ? $data['overall_avg'].' / 5' : '—']);
        fputcsv($handle, ['Food Rating Average', $data['food_avg'] !== null ? $data['food_avg'].' / 5' : '—']);
        fputcsv($handle, ['Service Rating Average', $data['service_avg'] !== null ? $data['service_avg'].' / 5' : '—']);
        fputcsv($handle, ['Public Reviews Count', (string) $data['total_reviews']]);
        fputcsv($handle, ['Hidden Reviews Count', (string) $data['hidden_count']]);
        fputcsv($handle, []);

        fputcsv($handle, ['Recent Feedback Reviews']);
        fputcsv($handle, ['Date', 'Customer', 'Food Rating', 'Service Rating', 'Comment']);
        foreach ($data['recent_reviews'] as $rev) {
            $customerName = $rev->customer ? $rev->customer->first_name.' '.substr($rev->customer->last_name, 0, 1).'.' : 'Guest Diner';
            fputcsv($handle, [
                $rev->submitted_at?->format('Y-m-d'),
                $customerName,
                $rev->food_rating,
                $rev->service_rating,
                $rev->comment ?? '',
            ]);
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeStaffCsv($handle, array $data): void
    {
        fputcsv($handle, ['Staff Member', 'Role', 'Cash Count', 'Cash Total', 'Adjustments Count', 'Adjustments Total', 'Refund Requests', 'Menu Toggles', 'Overrides', 'Total Actions']);
        foreach ($data['staff_activity'] as $row) {
            fputcsv($handle, [
                $row['staff']->name,
                ucfirst($row['staff']->role?->role_name ?? 'staff'),
                $row['cash_count'],
                sprintf('$%.2f', $row['cash_total']),
                $row['adjustments_count'],
                sprintf('$%.2f', $row['adjustments_total']),
                $row['refund_requests_count'],
                $row['toggles_count'],
                $row['overrides_count'],
                $row['total_actions'],
            ]);
        }
    }

    /**
     * FR45. AI Usage Report: requests, tokens, and success/busy rate from audit_log ai_request entries.
     *
     * @return array<string, mixed>
     */
    public function aiReport(Carbon $from, Carbon $to): array
    {
        $logs = AuditLog::where('action_type', 'ai_request')
            ->whereBetween('logged_at', [$from, $to])
            ->with('customer')
            ->orderByDesc('logged_at')
            ->get();

        $totalRequests = $logs->count();
        $totalTokensIn = (int) $logs->sum(fn (AuditLog $l) => (int) ($l->details['tokens_in'] ?? 0));
        $totalTokensOut = (int) $logs->sum(fn (AuditLog $l) => (int) ($l->details['tokens_out'] ?? 0));
        $totalTokens = $totalTokensIn + $totalTokensOut;

        $avgTokens = $totalRequests > 0 ? (int) round($totalTokens / $totalRequests) : 0;

        $busyCount = $logs->filter(fn (AuditLog $l) => ($l->details['status'] ?? 'success') === 'busy')->count();
        $successCount = $totalRequests - $busyCount;
        $successRate = $totalRequests > 0 ? round(($successCount / $totalRequests) * 100, 1) : 100.0;

        // By feature (chat vs meal_builder)
        $chatLogs = $logs->filter(fn (AuditLog $l) => ($l->details['feature'] ?? 'chat') === 'chat');
        $mealLogs = $logs->filter(fn (AuditLog $l) => ($l->details['feature'] ?? '') === 'meal_builder');

        $byFeature = [
            'chat' => [
                'requests' => $chatLogs->count(),
                'tokens_in' => (int) $chatLogs->sum(fn (AuditLog $l) => (int) ($l->details['tokens_in'] ?? 0)),
                'tokens_out' => (int) $chatLogs->sum(fn (AuditLog $l) => (int) ($l->details['tokens_out'] ?? 0)),
                'total_tokens' => (int) $chatLogs->sum(fn (AuditLog $l) => (int) (($l->details['tokens_in'] ?? 0) + ($l->details['tokens_out'] ?? 0))),
            ],
            'meal_builder' => [
                'requests' => $mealLogs->count(),
                'tokens_in' => (int) $mealLogs->sum(fn (AuditLog $l) => (int) ($l->details['tokens_in'] ?? 0)),
                'tokens_out' => (int) $mealLogs->sum(fn (AuditLog $l) => (int) ($l->details['tokens_out'] ?? 0)),
                'total_tokens' => (int) $mealLogs->sum(fn (AuditLog $l) => (int) (($l->details['tokens_in'] ?? 0) + ($l->details['tokens_out'] ?? 0))),
            ],
        ];

        return [
            'total_requests' => $totalRequests,
            'total_tokens_in' => $totalTokensIn,
            'total_tokens_out' => $totalTokensOut,
            'total_tokens' => $totalTokens,
            'avg_tokens_per_request' => $avgTokens,
            'success_rate' => $successRate,
            'success_count' => $successCount,
            'busy_count' => $busyCount,
            'by_feature' => $byFeature,
            'recent_requests' => $logs->take(50)->values(),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $data
     */
    private function writeAiCsv($handle, array $data): void
    {
        fputcsv($handle, ['Metric', 'Value']);
        fputcsv($handle, ['Total AI Requests', (string) $data['total_requests']]);
        fputcsv($handle, ['Total Tokens Used', (string) $data['total_tokens']]);
        fputcsv($handle, ['Prompt Tokens (In)', (string) $data['total_tokens_in']]);
        fputcsv($handle, ['Completion Tokens (Out)', (string) $data['total_tokens_out']]);
        fputcsv($handle, ['Average Tokens / Request', (string) $data['avg_tokens_per_request']]);
        fputcsv($handle, ['Success Rate', $data['success_rate'].'%']);
        fputcsv($handle, ['Busy / Throttled Rate', ($data['total_requests'] > 0 ? round(($data['busy_count'] / $data['total_requests']) * 100, 1) : 0).'%']);
        fputcsv($handle, []);

        fputcsv($handle, ['Feature Breakdown']);
        fputcsv($handle, ['Feature', 'Requests', 'Tokens In', 'Tokens Out', 'Total Tokens']);
        fputcsv($handle, [
            'Chat Assistant',
            $data['by_feature']['chat']['requests'],
            $data['by_feature']['chat']['tokens_in'],
            $data['by_feature']['chat']['tokens_out'],
            $data['by_feature']['chat']['total_tokens'],
        ]);
        fputcsv($handle, [
            'Meal Builder',
            $data['by_feature']['meal_builder']['requests'],
            $data['by_feature']['meal_builder']['tokens_in'],
            $data['by_feature']['meal_builder']['tokens_out'],
            $data['by_feature']['meal_builder']['total_tokens'],
        ]);
        fputcsv($handle, []);

        fputcsv($handle, ['Recent AI Requests']);
        fputcsv($handle, ['Date & Time', 'Feature', 'Customer / Actor', 'Tokens In', 'Tokens Out', 'IP Address']);
        foreach ($data['recent_requests'] as $req) {
            $customerName = $req->customer ? $req->customer->first_name.' '.$req->customer->last_name : 'Guest Visitor';
            fputcsv($handle, [
                $req->logged_at?->format('Y-m-d H:i:s'),
                ucwords(str_replace('_', ' ', (string) ($req->details['feature'] ?? 'chat'))),
                $customerName,
                $req->details['tokens_in'] ?? 0,
                $req->details['tokens_out'] ?? 0,
                $req->ip_address ?? '—',
            ]);
        }
    }
}

