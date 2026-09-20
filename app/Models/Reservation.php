<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $table = 'reservation';

    protected $primaryKey = 'reservation_id';

    protected $guarded = ['reservation_id', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'booking_date' => 'date',
            'is_late_cancellation' => 'boolean',
            'reviewed_at' => 'datetime',
            'seated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
            'no_show_cleared_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(SlotCapacity::class, 'slot_id', 'slot_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id', 'staff_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reviewed_by_staff_id', 'staff_id');
    }

    public function noShowBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'no_show_by_staff_id', 'staff_id');
    }

    public function noShowClearedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'no_show_cleared_by_staff_id', 'staff_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'reservation_id', 'reservation_id');
    }
}
