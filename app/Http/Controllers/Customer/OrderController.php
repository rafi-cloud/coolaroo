<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * FR38, UC11, BR30. show() renders the timeline; state() is the JSON
 * endpoint the page polls to stay live (NFR09) until T110-T112 wire a
 * real broadcast channel for order.{id}.
 */
class OrderController extends Controller
{
    private const TIMELINE_STEPS = ['paid', 'preparing', 'ready', 'served'];

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
