<?php

namespace App\Models;

use Database\Factories\MenuItemDietaryTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MenuItemDietaryTag extends Pivot
{
    /**
     * @use HasFactory<MenuItemDietaryTagFactory>
     */
    use HasFactory;

    protected $table = 'menu_item_dietary_tag';

    public $timestamps = false;

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function dietaryTag(): BelongsTo
    {
        return $this->belongsTo(DietaryTag::class, 'dietary_tag_id', 'dietary_tag_id');
    }
}
