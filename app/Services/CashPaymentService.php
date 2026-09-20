<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;

/**
 * FR49, BR22, BR23. Cash amount math and the pre-confirm stock warning —
 * no controller/view owns this class alone; PaymentService still does the
 * actual paying (stock deduction, visit, table, broadcast).
 */
class CashPaymentService
{
    public function __construct(private StockService $stock) {}

    /** BR22: nearest 5 cents. */
    public function roundedTotal(Order $order): float
    {
        return round((float) $order->total_amount * 20) / 20;
    }

    public function roundingAmount(Order $order): float
    {
        return round($this->roundedTotal($order) - (float) $order->total_amount, 2);
    }

    /** BR23: adjustment_amount is always a discount off the rounded total. */
    public function amountDue(Order $order, float $adjustmentAmount): float
    {
        return round($this->roundedTotal($order) - $adjustmentAmount, 2);
    }

    public function changeGiven(float $amountDue, float $amountReceived): float
    {
        return round($amountReceived - $amountDue, 2);
    }

    /** FR49: "warns if exact stock check would fail" — read-only, never blocks. */
    public function wouldConflictStock(Order $order): bool
    {
        $quantityByItem = [];

        foreach ($order->items as $line) {
            $quantityByItem[$line->item_id] = ($quantityByItem[$line->item_id] ?? 0) + $line->quantity;
        }

        $menuItems = MenuItem::whereIn('item_id', array_keys($quantityByItem))->get()->keyBy('item_id');

        foreach ($quantityByItem as $itemId => $quantity) {
            if (! $this->stock->hasExactStock($menuItems[$itemId], $quantity)) {
                return true;
            }
        }

        return false;
    }
}
