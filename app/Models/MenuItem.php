<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    /** @use HasFactory<\Database\Factories\MenuItemFactory> */
    use HasFactory;

    protected $table = 'menu_item';

    protected $primaryKey = 'item_id';

    protected $guarded = ['item_id'];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'calories_kcal' => 'decimal:2',
            'protein_g' => 'decimal:2',
            'carbohydrates_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id', 'category_id');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(MenuItemSize::class, 'item_id', 'item_id');
    }

    public function addOnGroups(): HasMany
    {
        return $this->hasMany(AddOnGroup::class, 'item_id', 'item_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'item_id', 'item_id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'menu_item_allergen', 'item_id', 'allergen_id', 'item_id', 'allergen_id')
            ->using(MenuItemAllergen::class);
    }

    public function dietaryTags(): BelongsToMany
    {
        return $this->belongsToMany(DietaryTag::class, 'menu_item_dietary_tag', 'item_id', 'dietary_tag_id', 'item_id', 'dietary_tag_id')
            ->using(MenuItemDietaryTag::class);
    }
}
