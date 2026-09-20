<?php

namespace App\Http\Controllers\Staff\Kds;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KitchenService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * FR56, FR57 (queue and filters, T080) and FR58 (Start/Ready, T081).
 * S30, UC28. The order status behind the two actions is derived by
 * KitchenService, never set here (BR28).
 */
class StationController extends Controller
{
    public function __construct(private KitchenService $kitchen)
    {
    }

    private const ACTIVE_ORDER_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Preparing,
        OrderStatus::Ready,
    ];

    private const ACTIVE_LINE_STATUSES = [
        OrderItemStatus::Pending,
        OrderItemStatus::Preparing,
        OrderItemStatus::Ready,
    ];

    public function index(Request $request, Destination $destination): View
    {
        return view('staff.kds.index', [
            'destination' => $destination,
            'orders' => $this->queue($request, $destination),
            'status' => $request->query('status'),
            'window' => $request->query('window'),
        ]);
    }

    /** NFR09: what kds.js re-fetches on every broadcast and on reconnect. */
    public function state(Request $request, Destination $destination): JsonResponse
    {
        $orders = $this->queue($request, $destination);

        return response()->json([
            'destination' => $destination->value,
            'count' => $orders->count(),
            'orders' => $orders->map(fn (Order $order) => [
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'table' => $order->restaurantTable?->table_number,
                'status' => $order->status->value,
                'has_stock_conflict' => $order->has_stock_conflict,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'eta_at' => $this->etaFor($order, $destination)?->toIso8601String(),
                'lines' => $order->items->map(fn ($line) => [
                    'order_item_id' => $line->order_item_id,
                    'item_name' => $line->item_name,
                    'size_name' => $line->size_name,
                    'quantity' => $line->quantity,
                    'status' => $line->status->value,
                    'special_request' => $line->special_request,
                    'selected_options' => $line->selected_options,
                ])->values(),
            ])->values(),
        ]);
    }

    /** FR58. No Form Request: the order and the station both come from the URL. */
    public function start(Request $request, Order $order, Destination $destination): RedirectResponse
    {
        Gate::authorize('updateStation', [Order::class, $destination]);

        $this->kitchen->start($order, $destination, $request->user('staff'));

        return back()->with('status', 'lines-started');
    }

    /** FR58. */
    public function ready(Request $request, Order $order, Destination $destination): RedirectResponse
    {
        Gate::authorize('updateStation', [Order::class, $destination]);

        $this->kitchen->ready($order, $destination, $request->user('staff'));

        return back()->with('status', 'lines-ready');
    }

    /** FR56: oldest first, this station's active lines only. */
    private function queue(Request $request, Destination $destination): Collection
    {
        $lineStatuses = $this->requestedLineStatuses($request);

        return Order::query()
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
            ->when($this->windowMinutes($request), fn ($query, int $minutes) => $query->where('paid_at', '>=', now()->subMinutes($minutes)))
            ->whereHas('items', fn ($query) => $query
                ->where('destination', $destination)
                ->whereIn('status', $lineStatuses))
            ->with([
                'restaurantTable',
                'items' => fn ($query) => $query
                    ->where('destination', $destination)
                    ->whereIn('status', $lineStatuses),
            ])
            ->orderBy('paid_at')
            ->get();
    }

    /** FR57: the status filter applies to the line, not the order. */
    private function requestedLineStatuses(Request $request): array
    {
        $requested = OrderItemStatus::tryFrom((string) $request->query('status'));

        if ($requested === null || ! in_array($requested, self::ACTIVE_LINE_STATUSES, true)) {
            return self::ACTIVE_LINE_STATUSES;
        }

        return [$requested];
    }

    private function windowMinutes(Request $request): ?int
    {
        return in_array($request->query('window'), ['30', '60'], true)
            ? (int) $request->query('window')
            : null;
    }

    private function etaFor(Order $order, Destination $destination): ?Carbon
    {
        return $destination === Destination::Kitchen ? $order->kitchen_eta_at : $order->bar_eta_at;
    }
}
