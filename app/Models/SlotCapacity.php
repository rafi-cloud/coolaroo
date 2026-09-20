<?php

namespace App\Models;

use Database\Factories\SlotCapacityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotCapacity extends Model
{
    /** @use HasFactory<SlotCapacityFactory> */
    use HasFactory;

    protected $table = 'slot_capacity';

    protected $primaryKey = 'slot_id';

    public $timestamps = false;

    protected $guarded = ['slot_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'slot_id', 'slot_id');
    }

    public function isInUse(): bool
    {
        return $this->reservations()->exists();
    }
}
