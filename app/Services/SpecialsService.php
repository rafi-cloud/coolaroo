<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Support\Collection;

/**
 * BR59. Nothing calls this yet — the public menu (FR32) and homepage offer
 * block are both later tasks; this is the decision logic they'll use.
 */
class SpecialsService
{
    public function isSaleActive(MenuItemSize $size): bool
    {
        if ($size->sale_price === null || ! $size->is_active) {
            return false;
        }

        $now = now();

        if ($size->sale_starts_at !== null && $now->lt($size->sale_starts_at)) {
            return false;
        }

        if ($size->sale_ends_at !== null && $now->gt($size->sale_ends_at)) {
            return false;
        }

        return true;
    }

    /** @return Collection<int, MenuItem> */
    public function itemsOnSpecial(): Collection
    {
        return MenuItem::where('is_active', true)
            ->whereHas('sizes', function ($query) {
                $query->whereNotNull('sale_price')
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()));
            })
            ->get();
    }

    /** @return array{size: MenuItemSize, discount_percent: float, ends_at: ?\Illuminate\Support\Carbon}|null */
    public function topSpecial(): ?array
    {
        $best = null;
        $bestDiscount = 0.0;

        foreach (MenuItemSize::whereNotNull('sale_price')->where('is_active', true)->get() as $size) {
            if (! $this->isSaleActive($size)) {
                continue;
            }

            $discount = ((float) $size->price - (float) $size->sale_price) / (float) $size->price;

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = $size;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'size' => $best->loadMissing('menuItem'),
            'discount_percent' => round($bestDiscount * 100),
            'ends_at' => $best->sale_ends_at,
        ];
    }
}
