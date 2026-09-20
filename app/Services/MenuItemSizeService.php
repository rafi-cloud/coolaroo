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
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(MenuItem $item, array $data): MenuItemSize
    {
        $size = $item->sizes()->create($data);

        $this->auditLogger->log(null, 'menu_item_size_create', $size);

        return $size;
    }

    public function update(MenuItemSize $size, array $data): MenuItemSize
    {
        $size->update($data);

        $this->auditLogger->log(null, 'menu_item_size_update', $size);

        return $size;
    }

    public function delete(MenuItemSize $size): void
    {
        if ($size->menuItem->sizes()->count() <= 1) {
            throw ValidationException::withMessages([
                'size' => 'Cannot delete the only remaining size for this item.',
            ]);
        }

        $this->auditLogger->log(null, 'menu_item_size_delete', $size);

        $size->delete();
    }
}
