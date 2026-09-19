<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Validation\ValidationException;

/**
 * FR26, FR27.
 */
class MenuItemSizeService
{
    public function create(MenuItem $item, array $data): MenuItemSize
    {
        return $item->sizes()->create($data);
    }

    public function update(MenuItemSize $size, array $data): MenuItemSize
    {
        $size->update($data);

        return $size;
    }

    public function delete(MenuItemSize $size): void
    {
        if ($size->menuItem->sizes()->count() <= 1) {
            throw ValidationException::withMessages([
                'size' => 'Cannot delete the only remaining size for this item.',
            ]);
        }

        $size->delete();
    }
}
