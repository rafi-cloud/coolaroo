<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MenuItemAllergen extends Pivot
{
    /** @use HasFactory<\Database\Factories\MenuItemAllergenFactory> */
    use HasFactory;

    protected $table = 'menu_item_allergen';

    public $timestamps = false;

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class, 'allergen_id', 'allergen_id');
    }
}
