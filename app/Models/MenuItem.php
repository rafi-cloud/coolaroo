<?php

namespace App\Models;

use App\Enums\Destination;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    /**
     * @use HasFactory<MenuItemFactory>
     */
    use HasFactory;

    protected $table = 'menu_item';

    protected $primaryKey = 'item_id';

    protected $guarded = ['item_id'];

    protected function casts(): array
    {
        return [
            'destination' => Destination::class,
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

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return 'storage/'.$this->image_path;
        }

        $name = strtolower($this->item_name ?? '');

        if (str_contains($name, 'pizza') || str_contains($name, 'margherita')) {
            return 'images/dish-margherita.jpg';
        }
        if (str_contains($name, 'burger') || str_contains($name, 'cheeseburger')) {
            return 'images/dish-burger.jpg';
        }
        if (str_contains($name, 'ribeye') || str_contains($name, 'steak') || str_contains($name, 'parma') || str_contains($name, 'parmigiana')) {
            return 'images/dish-porterhouse.jpg';
        }
        if (str_contains($name, 'barramundi') || str_contains($name, 'fish')) {
            return 'images/dish-fish.jpg';
        }
        if (str_contains($name, 'calamari') || str_contains($name, 'prawn') || str_contains($name, 'seafood')) {
            return 'images/dish-prawns.jpg';
        }
        if (str_contains($name, 'risotto') || str_contains($name, 'pasta') || str_contains($name, 'rigatoni')) {
            return 'images/dish-rigatoni.jpg';
        }
        if (str_contains($name, 'ribs') || str_contains($name, 'taco')) {
            return 'images/dish-tacos.jpg';
        }
        if (str_contains($name, 'pudding') || str_contains($name, 'dessert') || str_contains($name, 'donut') || str_contains($name, 'cupcake') || str_contains($name, 'gelato')) {
            return 'images/dish-donuts.jpg';
        }
        if (str_contains($name, 'beer') || str_contains($name, 'wine') || str_contains($name, 'shiraz') || str_contains($name, 'draught') || str_contains($name, 'drink') || str_contains($name, 'coffee') || str_contains($name, 'espresso')) {
            return 'images/dish-espresso.jpg';
        }
        if (str_contains($name, 'salad') || str_contains($name, 'pull-apart') || str_contains($name, 'garlic') || str_contains($name, 'bread') || str_contains($name, 'bowl')) {
            return 'images/dish-garden-bowl.jpg';
        }

        return 'images/dish-burger.jpg';
    }
}
