<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
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
}
