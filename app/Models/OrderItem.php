<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $table = 'order_item';

    protected $primaryKey = 'order_item_id';

    public $timestamps = false;

    protected $guarded = ['order_item_id', 'status'];

    protected function casts(): array
    {
        return [
            'original_unit_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'selected_options' => 'array',
            'line_total' => 'decimal:2',
            'prepared_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(MenuItemSize::class, 'size_id', 'size_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_item_id', 'order_item_id');
    }
}
