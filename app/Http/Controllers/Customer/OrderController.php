<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * FR38, FR41, UC11, UC12, BR30. show()/state() render and poll the
 * timeline (T062); cancel() is BR29's customer cancel (T063).
 */
class OrderController extends Controller
{
    private const TIMELINE_STEPS = ['paid', 'preparing', 'ready', 'served'];

    public function __construct(private OrderService $orders)
    {
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load('items');

        return view('customer.order', [
            'order' => $order,
            'timeline' => $this->timeline($order),
            'kitchenEta' => $this->etaRange($order->kitchen_eta_at),
            'barEta' => $this->etaRange($order->bar_eta_at),
        ]);
    }

    public function state(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json([
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $this->orders->cancelUnpaid($order, $request->user('customer'));

        return redirect()->route('orders.show', $order)->with('status', 'order-cancelled');
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

    /** BR30: "shown as a 5-minute range." */
    private function etaRange(?Carbon $eta): ?array
    {
        if ($eta === null) {
            return null;
        }

        return ['from' => $eta, 'to' => $eta->copy()->addMinutes(5)];
    }
}
