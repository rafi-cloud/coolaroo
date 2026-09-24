<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'restaurant_table';

    protected $primaryKey = 'table_id';

    protected $guarded = ['table_id', 'status'];

    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'status_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'table_id', 'table_id');
    }

    /**
     * FR65: bookings holding this table — an open visit row with opened_at
     * still NULL is what an assignment is, and seatReservation() is what opens
     * it.
     *
     * @return Collection<int, Reservation>
     */
    public function heldReservations(): Collection
    {
        return $this->visits
            ->filter(fn (Visit $visit) => $visit->opened_at === null
                && $visit->closed_at === null
                && $visit->reservation !== null)
            ->map(fn (Visit $visit) => $visit->reservation)
            ->unique('reservation_id')
            ->sortBy(fn (Reservation $reservation) => $reservation->slot?->slot_time)
            ->values();
    }

    /**
     * FR69: of those, the ones a waiter can seat right now — confirmed and for
     * today. Same "confirmed and assigned" condition S27 seats on.
     *
     * @return Collection<int, Reservation>
     */
    public function seatableReservations(): Collection
    {
        return $this->heldReservations()
            ->filter(fn (Reservation $reservation) => $reservation->status === ReservationStatus::Confirmed
                && $reservation->booking_date->isToday())
            ->values();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id', 'table_id');
    }
}
