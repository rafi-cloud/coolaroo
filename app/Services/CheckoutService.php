<?php

namespace App\Services;

use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes the plain array from CartService::rawLines(), not the cart itself — checkout doesn't need to know a cart is
 * session-backed. $staffActor is null for the QR customer path and
 * switches the staff-order differences: exact stock check (not the 5x QR
 * buffer), taken_by_staff_id recorded, event_source 'waitstaff'.
 */
class CheckoutService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int, array{item_id:int,size_id:int,quantity:int,special_request:?string,add_on_option_ids:int[]}>  $cartLines
     * @return array{order: Order, removed: string[]}
     */
    public function checkout(int $tableId, ?int $customerId, array $cartLines, string $idempotencyKey, ?Staff $staffActor = null): array
    {
        $existing = Order::where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return ['order' => $existing, 'removed' => []];
        }

        if (empty($cartLines)) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        [$validLines, $removed] = $this->revalidate($cartLines);

        if (empty($validLines)) {
            throw ValidationException::withMessages(['cart' => 'Every item in your cart is no longer available.']);
        }

        $this->checkStock($validLines, exact: $staffActor !== null);

        $order = DB::transaction(fn () => $this->createOrder($tableId, $customerId, $idempotencyKey, $validLines, $staffActor));

        return ['order' => $order, 'removed' => $removed];
    }

    /**
     * revalidate availability, options and current prices.
     *
     * @return array{0: array<int, array>, 1: string[]}
     */
    private function revalidate(array $cartLines): array
    {
        $valid = [];
        $removed = [];

        foreach ($cartLines as $line) {
            $item = MenuItem::find($line['item_id']);
            $size = $item !== null ? MenuItemSize::find($line['size_id']) : null;

            if ($item === null || ! $item->is_active || ! $item->is_available || $size === null || $size->item_id !== $item->item_id || ! $size->is_active) {
                $removed[] = $item?->item_name ?? 'An item';

                continue;
            }

            $options = AddOnOption::whereIn('option_id', $line['add_on_option_ids'])
                ->where('is_active', true)
                ->where('is_available', true)
                ->with('group')
                ->get();

            if ($options->count() !== count($line['add_on_option_ids'])) {
                $removed[] = $item->item_name;

                continue;
            }

            $valid[] = [
                'item' => $item,
                'size' => $size,
                'options' => $options,
                'quantity' => $line['quantity'],
                'special_request' => $line['special_request'],
            ];
        }

        return [$valid, $removed];
    }

    /**
     * Stock check, buffered for the QR path or exact for staff orders,
     * aggregated per item_id across every valid line that ordered it.
     */
    private function checkStock(array $validLines, bool $exact): void
    {
        $quantityByItem = [];
        $itemsById = [];

        foreach ($validLines as $line) {
            $itemId = $line['item']->item_id;
            $quantityByItem[$itemId] = ($quantityByItem[$itemId] ?? 0) + $line['quantity'];
            $itemsById[$itemId] = $line['item'];
        }

        foreach ($quantityByItem as $itemId => $quantity) {
            $item = $itemsById[$itemId];

            $hasStock = $exact ? $this->stock->hasExactStock($item, $quantity) : $this->stock->hasQrStock($item, $quantity);

            if (! $hasStock) {
                $max = $exact ? $this->stock->remaining($item) : $this->stock->maxOrderableQuantity($item);

                throw ValidationException::withMessages(['cart' => "You can order up to {$max} of \"{$item->item_name}\" right now."]);
            }
        }
    }

    private function createOrder(int $tableId, ?int $customerId, string $idempotencyKey, array $validLines, ?Staff $staffActor = null): Order
    {
        $rows = [];
        $total = 0.0;
        $lineNo = 1;

        foreach ($validLines as $line) {
            $unitPrice = $this->currentPrice($line['size']);
            $optionsTotal = (float) $line['options']->sum(fn ($o) => (float) $o->price_delta);
            $lineTotal = round(($unitPrice + $optionsTotal) * $line['quantity'], 2);
            $total += $lineTotal;

            $rows[] = [
                'line_no' => $lineNo++,
                'item_id' => $line['item']->item_id,
                'size_id' => $line['size']->size_id,
                'item_name' => $line['item']->item_name,
                'size_name' => $line['size']->size_name,
                'destination' => $line['item']->destination,
                'quantity' => $line['quantity'],
                'original_unit_price' => $line['size']->price,
                'unit_price' => $unitPrice,
                'selected_options' => $line['options']->map(fn ($o) => [
                    'group' => $o->group->group_name,
                    'option_id' => $o->option_id,
                    'name' => $o->option_name,
                    'price' => (float) $o->price_delta,
                ])->values()->all(),
                'special_request' => $line['special_request'],
                'line_total' => $lineTotal,
            ];
        }

        $total = round($total, 2);

        $order = Order::create([
            'table_id' => $tableId,
            'customer_id' => $customerId,
            'taken_by_staff_id' => $staffActor?->staff_id,
            'order_number' => $this->nextOrderNumber(),
            'idempotency_key' => $idempotencyKey,
            'total_amount' => $total,
            'gst_amount' => round($total / 11, 2),
        ]);

        foreach ($rows as $row) {
            OrderItem::create($row + ['order_id' => $order->order_id]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->order_id,
            'status_seq' => 1,
            'status' => 'pending_payment',
            'occurred_at' => now(),
            'event_source' => $staffActor !== null ? 'waitstaff' : 'customer',
        ]);

        return $order->refresh();
    }

    private function currentPrice(MenuItemSize $size): float
    {
        return app(SpecialsService::class)->isSaleActive($size)
            ? (float) $size->sale_price
            : (float) $size->price;
    }

    private function nextOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $count = Order::whereDate('placed_at', now()->toDateString())->lockForUpdate()->count();

        return sprintf('%s-%03d', $date, $count + 1);
    }
}
