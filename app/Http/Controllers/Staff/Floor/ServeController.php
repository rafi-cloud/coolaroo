<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\Destination;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KitchenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServeController extends Controller
{
    public function __construct(private KitchenService $kitchen) {}

    public function store(Request $request, Order $order, Destination $destination): RedirectResponse
    {
        Gate::authorize('markServed', Order::class);

        $this->kitchen->serve($order, $destination, $request->user('staff'));

        return back()->with('status', 'order-served');
    }
}
