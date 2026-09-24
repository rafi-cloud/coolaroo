<?php

namespace App\Models;

use Database\Factories\AllergenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    /**
     * @use HasFactory<AllergenFactory>
     */
    use HasFactory;

    protected $table = 'allergen';

    protected $primaryKey = 'allergen_id';

    public $timestamps = false;

    protected $guarded = ['allergen_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_allergen', 'allergen_id', 'item_id', 'allergen_id', 'item_id')
            ->using(MenuItemAllergen::class);
    }

    public function isInUse(): bool
    {
        return $this->menuItems()->exists();
    }
}
