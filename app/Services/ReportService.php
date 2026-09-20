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
}

