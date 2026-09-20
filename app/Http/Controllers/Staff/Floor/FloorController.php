<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * FR16, FR71, FR73, S22, UC16. Read-only: FR17/FR18 (seat/clear) is T091,
 * FR42 (take order) is T092, FR60 (mark served) is T093 — all this same
 * namespace.
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
        return view('staff.floor.index', [
            'tables' => $this->tables(),
            'readyToServe' => $this->readyToServe(),
            'cashWaiting' => $this->cashWaiting(),
            'qrOrderingPaused' => $this->qrOrderingPaused(),
        ]);
    }

    /** NFR09: what floor.js re-fetches on every broadcast and on reconnect. */
    public function state(): JsonResponse
    {
        return response()->json([
            'tables' => $this->tables()->map(fn (RestaurantTable $table) => [
                'table_id' => $table->table_id,
                'status' => $table->status->value,
                'active_order_count' => $table->active_order_count,
                'next_reservation' => $this->nextReservationPayload($table),
            ])->values(),
            'ready_to_serve' => $this->readyToServe()->map(fn (Order $order) => [
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'table_number' => $order->restaurantTable?->table_number,
                'ready_at' => $order->ready_at?->toIso8601String(),
                'destinations' => $order->items->pluck('destination')->unique()->map(fn ($d) => $d->value)->values(),
            ])->values(),
            'cash_waiting' => $this->cashWaiting()->map(fn (Payment $payment) => [
                'payment_id' => $payment->payment_id,
                'order_number' => $payment->order->order_number,
                'table_number' => $payment->order->restaurantTable?->table_number,
                'amount' => (float) $payment->amount,
                'requested_at' => $payment->created_at?->toIso8601String(),
            ])->values(),
            'qr_ordering_paused' => $this->qrOrderingPaused(),
        ]);
    }

    /** BR04: "next assigned reservation" = an open visit that already has one. */
    private function tables(): Collection
    {
        return RestaurantTable::query()
            ->where('is_active', true)
            ->withCount(['orders as active_order_count' => fn ($query) => $query->whereIn('status', self::ACTIVE_ORDER_STATUSES)])
            ->with(['visits' => fn ($query) => $query
                ->whereNull('closed_at')
                ->whereNotNull('reservation_id')
                ->with('reservation.slot')
                ->oldest('created_at')])
            ->orderBy('table_number')
            ->get();
    }

    private function nextReservationPayload(RestaurantTable $table): ?array
    {
        $reservation = $table->visits->first()?->reservation;

        if ($reservation === null) {
            return null;
        }

        return [
            'reservation_id' => $reservation->reservation_id,
            'booking_date' => $reservation->booking_date->toDateString(),
            'slot_time' => $reservation->slot->slot_time,
            'party_size' => $reservation->party_size,
        ];
    }

    /** FR60/UC21's own list: orders whose derived status (BR28) is ready. */
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

    /** FR48/FR49's queue: cash requested, not yet collected. */
    private function cashWaiting(): Collection
    {
        return Payment::where('method', PaymentMethod::Cash)
            ->where('status', PaymentAttemptStatus::Pending)
            ->with('order.restaurantTable')
            ->orderBy('created_at')
            ->get();
    }

    /** BR58. */
    private function qrOrderingPaused(): bool
    {
        return Setting::find('qr_ordering_enabled')?->setting_value === '0';
    }
}
