<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantTableFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'restaurant_table';

    protected $primaryKey = 'table_id';

    protected $guarded = ['table_id', 'status'];

    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'table_id', 'table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id', 'table_id');
    }
}
