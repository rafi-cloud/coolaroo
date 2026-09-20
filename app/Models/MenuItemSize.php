<?php

namespace App\Models;

use Database\Factories\MenuItemSizeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemSize extends Model
{
    /** @use HasFactory<MenuItemSizeFactory> */
    use HasFactory;

    protected $table = 'menu_item_size';

    protected $primaryKey = 'size_id';

    public $timestamps = false;

    protected $guarded = ['size_id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'size_id', 'size_id');
    }
}
