<?php

namespace App\Services;

use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR35, FR36, BR21, BR57. Cart lines live in the session, not the database —
 * nothing about an unpaid cart is worth persisting past checkout.
 */
class CartService
{
    public function add(int $itemId, int $sizeId, array $addOnOptionIds, int $quantity, ?string $specialRequest): string
    {
        $lineId = (string) Str::uuid();
        $cart = $this->rawCart();
        $cart['table_id'] ??= session('table_id');
        $cart['lines'][$lineId] = [
            'item_id' => $itemId,
            'size_id' => $sizeId,
            'add_on_option_ids' => array_values($addOnOptionIds),
            'quantity' => $quantity,
            'special_request' => $specialRequest,
        ];
        $this->saveCart($cart);

        return $lineId;
    }

    public function updateLine(string $lineId, int $quantity, ?string $specialRequest): void
    {
        $cart = $this->rawCart();

        if (! isset($cart['lines'][$lineId])) {
            throw ValidationException::withMessages(['line' => 'That cart line no longer exists.']);
        }

        $cart['lines'][$lineId]['quantity'] = $quantity;
        $cart['lines'][$lineId]['special_request'] = $specialRequest;
        $this->saveCart($cart);
    }

    public function removeLine(string $lineId): void
    {
        $cart = $this->rawCart();
        unset($cart['lines'][$lineId]);
        $this->saveCart($cart);
    }

    public function clear(): void
    {
        session()->forget('cart');
    }

    /** @return array<int, array{item_id:int,size_id:int,quantity:int,special_request:?string,add_on_option_ids:int[]}> */
    public function rawLines(): array
    {
        return array_values($this->rawCart()['lines'] ?? []);
    }

    /**
     * @return array<int, array{line_id: string, item: MenuItem, size: MenuItemSize, options: Collection, quantity: int, special_request: ?string, unit_price: float, line_total: float}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->rawCart()['lines'] ?? [] as $lineId => $line) {
            $item = MenuItem::find($line['item_id']);
            $size = MenuItemSize::find($line['size_id']);

            if ($item === null || $size === null) {
                continue;
            }

            $options = AddOnOption::whereIn('option_id', $line['add_on_option_ids'])->get();
            $unitPrice = $this->currentPrice($size) + (float) $options->sum(fn ($o) => (float) $o->price_delta);

            $lines[] = [
                'line_id' => $lineId,
                'item' => $item,
                'size' => $size,
                'options' => $options,
                'quantity' => $line['quantity'],
                'special_request' => $line['special_request'],
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $line['quantity'], 2),
            ];
        }

        return $lines;
    }

    public function total(): float
    {
        return round(array_sum(array_column($this->lines(), 'line_total')), 2);
    }

    public function count(): int
    {
        return (int) array_sum(array_column($this->rawCart()['lines'] ?? [], 'quantity'));
    }

    public function boundTableId(): ?int
    {
        return $this->rawCart()['table_id'] ?? null;
    }

    private function currentPrice(MenuItemSize $size): float
    {
        return app(SpecialsService::class)->isSaleActive($size)
            ? (float) $size->sale_price
            : (float) $size->price;
    }

    private function rawCart(): array
    {
        return session('cart', ['table_id' => null, 'lines' => []]);
    }

    private function saveCart(array $cart): void
    {
        session(['cart' => $cart]);
    }
}
