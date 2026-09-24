<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Read-only. Seat/clear, take order and mark served are all separate
 * controllers in this same namespace.
 */
class FloorController extends Controller
{
    private const ACTIVE_ORDER_STATUSES = [
        OrderStatus::PendingPayment,
        OrderStatus::Paid,
        OrderStatus::Preparing,
        OrderStatus::Ready,
    ];

    public function index(): View
    {
        $readyToServe = $this->readyToServe();
        $cashWaiting = $this->cashWaiting();

        return view('staff.floor.index', [
            'tables' => $this->tables(),
            'readyToServe' => $readyToServe,
            'cashWaiting' => $cashWaiting,
            'attention' => $this->attention($readyToServe, $cashWaiting),
            'qrOrderingPaused' => $this->qrOrderingPaused(),
            'assignableReservations' => $this->assignableReservations(),
        ]);
    }

    /**
     * FR65 moved onto S23: today's bookings that can still be given a table.
     * Loaded once for the whole grid rather than per drawer.
     *
     * @return Collection<int, Reservation>
     */
    private function assignableReservations(): Collection
    {
        return Reservation::query()
            ->whereDate('booking_date', now()->toDateString())
            ->whereIn('status', [ReservationStatus::Requested, ReservationStatus::Confirmed])
            ->with(['slot', 'visits'])
            ->orderBy('booking_time')
            ->get();
    }

    /**
     * What needs a waiter right now, keyed by table so the grid can colour
     * the table itself and the alert list can name it. Derived from the same
     * two queries the lists use, so it survives a page load rather than
     * depending on a broadcast arriving — WaiterCalled and ReservationAlert
     * are still pushed live on top of this, and are not stored anywhere.
     *
     * @return array<int, array{kind: string, label: string, order_number: string}>
     */
    private function attention(Collection $readyToServe, Collection $cashWaiting): array
    {
        $attention = [];

        foreach ($readyToServe as $order) {
            if ($order->table_id === null) {
                continue;
            }

            $attention[$order->table_id] = [
                'kind' => 'ready',
                'label' => 'Ready to serve',
                'order_number' => $order->order_number,
            ];
        }

        // cash outranks food: the diner is waiting to leave, not to eat
        foreach ($cashWaiting as $payment) {
            if ($payment->order->table_id === null) {
                continue;
            }

            $attention[$payment->order->table_id] = [
                'kind' => 'cash',
                'label' => 'Cash requested',
                'order_number' => $payment->order->order_number,
            ];
        }

        return $attention;
    }

    /** what floor.js re-fetches on every broadcast and on reconnect. */
    public function state(): JsonResponse
    {
        $readyToServe = $this->readyToServe();
        $cashWaiting = $this->cashWaiting();
        $attention = $this->attention($readyToServe, $cashWaiting);

        return response()->json([
            'tables' => $this->tables()->map(fn (RestaurantTable $table) => [
                'table_id' => $table->table_id,
                'table_number' => $table->table_number,
                'status' => $table->status->value,
                'active_order_count' => $table->active_order_count,
                'next_reservation' => $this->nextReservationPayload($table),
                'attention' => $attention[$table->table_id]['kind'] ?? null,
                'attention_label' => $attention[$table->table_id]['label'] ?? null,
            ])->values(),
            'attention' => collect($attention)->map(fn (array $row, int $tableId) => $row + ['table_id' => $tableId])->values(),
            'ready_to_serve' => $readyToServe->map(fn (Order $order) => [
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'table_number' => $order->restaurantTable?->table_number,
                'ready_at' => $order->ready_at?->toIso8601String(),
                'destinations' => $order->items->pluck('destination')->unique()->map(fn ($d) => $d->value)->values(),
            ])->values(),
            'cash_waiting' => $cashWaiting->map(fn (Payment $payment) => [
                'payment_id' => $payment->payment_id,
                'order_number' => $payment->order->order_number,
                'table_number' => $payment->order->restaurantTable?->table_number,
                // the floor needs somewhere to send the waiter, not just a row to read
                'table_id' => $payment->order->table_id,
                'settle_url' => $payment->order->table_id
                    ? route('staff.tables.order', $payment->order->table_id)
                    : null,
                'amount' => (float) $payment->amount,
                'requested_at' => $payment->created_at?->toIso8601String(),
            ])->values(),
            'qr_ordering_paused' => $this->qrOrderingPaused(),
        ]);
    }

    /** "next assigned reservation" = an open visit that already has one. */
    private function tables(): Collection
    {
        return RestaurantTable::query()
            ->where('is_active', true)
            ->withCount(['orders as active_order_count' => fn ($query) => $query->whereIn('status', self::ACTIVE_ORDER_STATUSES)])
            ->with(['visits' => fn ($query) => $query
                ->whereNull('closed_at')
                ->whereNotNull('reservation_id')
                ->with(['reservation.slot', 'reservation.visits'])
                ->oldest('created_at')])
            ->orderBy('table_number')
            ->get();
    }

    private function nextReservationPayload(RestaurantTable $table): ?array
    {
        $visit = $table->visits->first();
        $reservation = $visit?->reservation;

        if ($reservation === null) {
            return null;
        }

        return [
            'reservation_id' => $reservation->reservation_id,
            'reference_code' => $reservation->reference_code,
            'booking_date' => $reservation->booking_date->toDateString(),
            'slot_time' => $reservation->slot->slot_time,
            'party_size' => $reservation->party_size,
            'seated' => $visit->opened_at !== null,
        ];
    }

    /** the own list: orders whose derived status is ready. */
    private function readyToServe(): Collection
    {
        return Order::where('status', OrderStatus::Ready)
            ->with([
                'restaurantTable',
                'items' => fn ($query) => $query->where('status', OrderItemStatus::Ready),
            ])
            ->orderBy('ready_at')
            ->get();
    }

    /** the queue: cash requested, not yet collected. */
    private function cashWaiting(): Collection
    {
        return Payment::where('method', PaymentMethod::Cash)
            ->where('status', PaymentAttemptStatus::Pending)
            // a cancelled or already-paid order can never be settled, so its
            // stale request must not sit here forever offering a dead action
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::PendingPayment))
            ->with('order.restaurantTable')
            ->orderBy('created_at')
            ->get();
    }

    private function qrOrderingPaused(): bool
    {
        return Setting::find('qr_ordering_enabled')?->setting_value === '0';
    }
}
