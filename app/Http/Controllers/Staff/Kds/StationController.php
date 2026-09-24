<?php

namespace App\Http\Controllers\Staff\Kds;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AdjustEtaRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\EtaService;
use App\Services\KitchenService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The station queue and its filters, Start/Ready, and ETA adjustment.
 * The order status behind Start/Ready is derived by KitchenService,
 * never set here.
 */
class StationController extends Controller
{
    public function __construct(
        private KitchenService $kitchen,
        private EtaService $eta,
    ) {}

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
        $orders = $this->queue($request, $destination);

        return view('staff.kds.index', [
            'destination' => $destination,
            'orders' => $orders,
            'signature' => $this->signature($orders, $destination),
            'status' => $request->query('status'),
            'window' => $request->query('window'),
            'availabilityItems' => $this->availabilityItems($destination),
        ]);
    }

    /** what kds.js re-fetches on every broadcast and on reconnect. */
    public function state(Request $request, Destination $destination): JsonResponse
    {
        $orders = $this->queue($request, $destination);

        return response()->json([
            'destination' => $destination->value,
            'count' => $orders->count(),
            'signature' => $this->signature($orders, $destination),
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

    /** No Form Request: the order and the station both come from the URL. */
    public function start(Request $request, Order $order, Destination $destination): RedirectResponse
    {
        Gate::authorize('updateStation', [Order::class, $destination]);

        $this->kitchen->start($order, $destination, $request->user('staff'));

        return back()->with('status', 'lines-started');
    }

    /**
     * Mark every still-preparing line at this station ready in one press, for
     * a ticket that plates together. Lines already ticked are left alone.
     */
    public function ready(Request $request, Order $order, Destination $destination): RedirectResponse
    {
        Gate::authorize('updateStation', [Order::class, $destination]);

        $this->kitchen->ready($order, $destination, $request->user('staff'));

        return back()->with('status', 'lines-ready');
    }

    /**
     * Tick one prepared line. The station is taken from the line, so a bar
     * cook cannot tick a kitchen line by posting its id.
     */
    public function readyLine(Request $request, OrderItem $line): RedirectResponse
    {
        Gate::authorize('updateStation', [Order::class, $line->destination]);

        $this->kitchen->markLineReady($line, $request->user('staff'));

        return back()->with('status', 'line-ready');
    }

    /** Same station-scoped ability as start()/ready(). */
    public function adjustEta(AdjustEtaRequest $request, Order $order, Destination $destination): RedirectResponse
    {
        Gate::authorize('updateStation', [Order::class, $destination]);

        $this->eta->adjust($order, $destination, (int) $request->validated('minutes'), $request->user('staff'));

        return back()->with('status', 'eta-adjusted');
    }

    /** this station's active items with their add-on options, for the drawer. */
    private function availabilityItems(Destination $destination): Collection
    {
        return MenuItem::where('destination', $destination)
            ->where('is_active', true)
            ->with('addOnGroups.options')
            ->orderBy('item_name')
            ->get();
    }

    /** oldest first, this station's active lines only. */
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

    /**
     * One string for "is the board showing something different now". The page
     * carries the value it was rendered with, the poll and every station
     * broadcast compare against it, and a difference re-renders the page —
     * cheaper than keeping a second copy of the ticket markup in JavaScript.
     */
    private function signature(Collection $orders, Destination $destination): string
    {
        return md5($orders->map(fn (Order $order) => implode(':', [
            $order->order_id,
            $order->status->value,
            $order->has_stock_conflict ? '1' : '0',
            $this->etaFor($order, $destination)?->toIso8601String() ?? '-',
            $order->items->map(fn (OrderItem $line) => $line->order_item_id.'='.$line->status->value)->implode(','),
        ]))->implode('|'));
    }

    /** the status filter applies to the line, not the order. */
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
