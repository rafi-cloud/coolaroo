<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Read-only search and detail. */
class OrderController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(Request $request): View
    {
        $filters = Validator::make($request->query(), [
            'number' => ['nullable', 'string', 'max:64'],
            'date' => ['nullable', 'date'],
            'table_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ])->valid();

        $orders = Order::query()
            ->with('restaurantTable')
            ->when(filled($filters['number'] ?? null), fn ($query) => $query->where('order_number', 'like', '%'.$filters['number'].'%'))
            ->when(filled($filters['date'] ?? null), fn ($query) => $query->whereDate('placed_at', $filters['date']))
            ->when(filled($filters['table_id'] ?? null), fn ($query) => $query->where('table_id', $filters['table_id']))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('placed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'tables' => RestaurantTable::orderBy('table_number')->get(),
            'statuses' => OrderStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'items',
            'payments',
            'refunds.requestedBy',
            'refunds.requestedByCustomer',
            'refunds.processedBy',
            'statusHistory',
            'restaurantTable',
            'customer',
            'takenBy',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'canRequestRefund' => $this->refunds->canBeRefundRequested($order),
        ]);
    }
}
