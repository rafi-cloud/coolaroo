<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

class MenuItemService
{
    private const MAX_FEATURED = 12;

    public function __construct(private AuditLogger $auditLogger) {}

    public function create(array $data, array $allergenIds, array $dietaryTagIds): MenuItem
    {
        $item = MenuItem::create($data);
        $item->allergens()->sync($allergenIds);
        $item->dietaryTags()->sync($dietaryTagIds);

        $this->auditLogger->log(null, 'menu_item_create', $item);

        return $item;
    }

    public function update(MenuItem $item, array $data, array $allergenIds, array $dietaryTagIds): MenuItem
    {
        $item->update($data);
        $item->allergens()->sync($allergenIds);
        $item->dietaryTags()->sync($dietaryTagIds);

        $this->auditLogger->log(null, 'menu_item_update', $item);

        return $item;
    }

    public function archive(MenuItem $item, Staff $actor, ?string $reason = null): void
    {
        $item->update(['is_active' => false]);

        $this->auditLogger->snapshot($actor, 'menu_item_archive', $item, $reason);
    }

    public function unarchive(MenuItem $item, Staff $actor): void
    {
        $item->update(['is_active' => true]);

        $this->auditLogger->log($actor, 'menu_item_unarchive', $item);
    }

    public function setFeatured(MenuItem $item, bool $featured): void
    {
        if ($featured && ! $item->is_featured) {
            $count = MenuItem::where('is_featured', true)->where('is_active', true)->count();

            if ($count >= self::MAX_FEATURED) {
                throw ValidationException::withMessages([
                    'featured' => 'At most 12 items can be featured at once.',
                ]);
            }
        }

        $item->update(['is_featured' => $featured]);
    }
}
