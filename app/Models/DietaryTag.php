<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DietaryTag extends Model
{
    /** @use HasFactory<\Database\Factories\DietaryTagFactory> */
    use HasFactory;

    protected $table = 'dietary_tag';

    protected $primaryKey = 'dietary_tag_id';

    public $timestamps = false;

    protected $guarded = ['dietary_tag_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_dietary_tag', 'dietary_tag_id', 'item_id', 'dietary_tag_id', 'item_id')
            ->using(MenuItemDietaryTag::class);
    }

    public function isInUse(): bool
    {
        return $this->menuItems()->exists();
    }
}
