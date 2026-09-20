<?php

namespace App\Models;

use App\Enums\VisitCloseReason;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'visit';

    protected $primaryKey = 'visit_id';

    protected $guarded = ['visit_id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'close_reason' => VisitCloseReason::class,
        ];
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id', 'table_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'opened_by_staff_id', 'staff_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'closed_by_staff_id', 'staff_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'visit_id', 'visit_id');
    }
}
