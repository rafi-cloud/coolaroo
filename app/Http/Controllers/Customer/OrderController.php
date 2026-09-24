<?php

namespace App\Http\Controllers\Customer;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Support\AustralianDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * show()/state() render and poll the
 * timeline; cancel is the customer cancel.
 */
class OrderController extends Controller
{
    private const TIMELINE_STEPS = ['paid', 'preparing', 'ready', 'served'];

    public function __construct(
        private OrderService $orders,
        private RefundService $refunds,
    ) {}

    /**
     * View customer order history.
     */
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $orders = Order::where('customer_id', $customer->customer_id)
            ->with(['restaurantTable', 'items.refunds', 'payments', 'refunds'])
            ->orderByDesc('placed_at')
            ->paginate(10);

        return view('customer.orders', [
            'orders' => $orders,
            'refundable' => $orders->mapWithKeys(fn (Order $order) => [
                $order->order_id => $this->refundableLines($order)->isNotEmpty(),
            ])->all(),
        ]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['restaurantTable', 'items.menuItem', 'items.refunds', 'feedback', 'payments']);

        return view('customer.order', [
            'order' => $order,
            'refundableLines' => $this->refundableLines($order),
            'table' => $order->restaurantTable,
            'tableLabel' => $order->restaurantTable ? 'Table '.$order->restaurantTable->table_number : null,
            'timeline' => $this->timeline($order),
            'kitchenEta' => $this->stationEta($order, Destination::Kitchen),
            'barEta' => $this->stationEta($order, Destination::Bar),
        ]);
    }

    public function state(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->loadMissing('items');

        return response()->json([
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'kitchen_eta' => $this->formatEtaRange($this->stationEta($order, Destination::Kitchen)),
            'bar_eta' => $this->formatEtaRange($this->stationEta($order, Destination::Bar)),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $this->orders->cancelUnpaid($order, $request->user('customer'));

        return redirect()->route('orders.show', $order)->with('status', 'order-cancelled');
    }

    /**
     * FR51: the lines a customer can still ask for money back on. Empty means
     * the order is outside the 24-hour window, unpaid, or fully claimed
     * already — RefundService owns all three rules.
     *
     * @return Collection<int, array{item:OrderItem, remaining:int}>
     */
    public function refundableLines(Order $order): Collection
    {
        if (! $this->refunds->canBeRefundRequested($order)) {
            return collect();
        }

        return $order->items
            ->map(fn (OrderItem $item) => ['item' => $item, 'remaining' => $this->refunds->remainingQuantity($item)])
            ->filter(fn (array $line) => $line['remaining'] > 0)
            ->values();
    }

    /** @return array<int, array{step:string, done:bool, current:bool, at:?Carbon}> */
    private function timeline(Order $order): array
    {
        $currentIndex = array_search($order->status->value, self::TIMELINE_STEPS, true);

        $reachedAt = [
            'paid' => $order->paid_at,
            'preparing' => $order->started_at,
            'ready' => $order->ready_at,
            'served' => $order->served_at,
        ];

        $steps = [];

        foreach (self::TIMELINE_STEPS as $index => $step) {
            $steps[] = [
                'step' => $step,
                'done' => $currentIndex !== false && $index <= $currentIndex,
                'current' => $index === $currentIndex,
                'at' => $reachedAt[$step],
            ];
        }

        return $steps;
    }

    /**
     * FR59's ETA answers "when will this station be done", so it is dropped
     * once that station has nothing pending or preparing left — the same
     * queue filter EtaService::estimate() uses. The column itself stays on the
     * order: ReportService reads it against ready_at for ETA accuracy.
     */
    private function stationEta(Order $order, Destination $destination): ?array
    {
        $eta = $destination === Destination::Kitchen ? $order->kitchen_eta_at : $order->bar_eta_at;

        $stationWorking = $order->items->contains(
            fn (OrderItem $item) => $item->destination === $destination
                && in_array($item->status, [OrderItemStatus::Pending, OrderItemStatus::Preparing], true)
        );

        return $stationWorking ? $this->etaRange($eta) : null;
    }

    /** "shown as a 5-minute range." */
    private function etaRange(?Carbon $eta): ?array
    {
        if ($eta === null) {
            return null;
        }

        return ['from' => $eta, 'to' => $eta->copy()->addMinutes(5)];
    }

    /** "customer ETA updates live" needs formatted strings on state(), not just show(). */
    private function formatEtaRange(?array $range): ?array
    {
        if ($range === null) {
            return null;
        }

        return ['from' => AustralianDate::time($range['from']), 'to' => AustralianDate::time($range['to'])];
    }
}
