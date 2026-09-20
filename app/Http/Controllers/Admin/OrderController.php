<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** FR102, UC33, S33. Read-only search and detail. */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with('restaurantTable')
            ->when($request->filled('number'), fn ($query) => $query->where('order_number', 'like', '%'.$request->query('number').'%'))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('placed_at', $request->query('date')))
            ->when($request->filled('table_id'), fn ($query) => $query->where('table_id', $request->query('table_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('placed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'tables' => RestaurantTable::orderBy('table_number')->get(),
            'statuses' => OrderStatus::cases(),
            'filters' => $request->only(['number', 'date', 'table_id', 'status']),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'items',
            'payments',
            'refunds.requestedBy',
            'refunds.processedBy',
            'statusHistory',
            'restaurantTable',
            'customer',
            'takenBy',
        ]);

        return view('admin.orders.show', ['order' => $order]);
    }
}
