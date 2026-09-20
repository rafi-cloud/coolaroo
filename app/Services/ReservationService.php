<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\Visit;
use Illuminate\Support\Carbon;

/**
 * BR02, BR03, BR04. Minimal on purpose — 07.4 gives this service the whole
 * reservation lifecycle, but only UC07's holder-scan seating is due now.
 * T101/T104/T105 extend this class rather than replace it.
 */
class ReservationService
{
    public function __construct(private TableStatusService $tableStatus)
    {
    }

    /** 06.4.16: the visit row is the only link between a table and a reservation. */
    public function assignedReservation(RestaurantTable $table): ?Reservation
    {
        return $table->visits()
            ->whereNull('closed_at')
            ->whereNotNull('reservation_id')
            ->latest('visit_id')
            ->first()?->reservation;
    }

    /** BR02, BR03: the holder, inside [booking - unlock, booking + grace]. */
    public function isHolderWithinWindow(Reservation $reservation, ?Customer $customer): bool
    {
        if ($customer === null || $reservation->customer_id !== $customer->customer_id) {
            return false;
        }

        if ($reservation->status !== ReservationStatus::Confirmed) {
            return false;
        }

        $bookedAt = $this->bookedAt($reservation);
        $before = (int) (Setting::find('holder_unlock_before_minutes')?->setting_value ?? 15);
        $grace = (int) (Setting::find('reservation_grace_minutes')?->setting_value ?? 15);

        return now()->betweenIncluded(
            $bookedAt->copy()->subMinutes($before),
            $bookedAt->copy()->addMinutes($grace),
        );
    }

    /** BR04: reuse the assigned-but-unopened visit; open it and occupy the table. */
    public function seatOnHolderScan(RestaurantTable $table, Reservation $reservation): Visit
    {
        $visit = $table->visits()
            ->whereNull('closed_at')
            ->where('reservation_id', $reservation->reservation_id)
            ->latest('visit_id')
            ->first();

        if ($visit === null) {
            $visit = $table->visits()->create([
                'reservation_id' => $reservation->reservation_id,
                'guest_count' => $reservation->party_size,
                'opened_at' => now(),
            ]);
        } elseif ($visit->opened_at === null) {
            $visit->update(['opened_at' => now()]);
        }

        $reservation->status->ensureCanTransitionTo(ReservationStatus::Seated);
        $reservation->forceFill([
            'status' => ReservationStatus::Seated,
            'seated_at' => now(),
        ])->save();

        if ($table->status !== TableStatus::Occupied) {
            $this->tableStatus->transition($table, TableStatus::Occupied);
        }

        return $visit;
    }

    /** BR41: 'Jane D' — never the full surname. */
    public function holderDisplayName(Reservation $reservation): string
    {
        $name = trim($reservation->customer?->full_name ?? $reservation->guest_name ?? '');

        if ($name === '') {
            return 'a guest';
        }

        $parts = preg_split('/\s+/', $name);
        $first = array_shift($parts);

        return $parts === [] ? $first : $first.' '.strtoupper(substr((string) end($parts), 0, 1));
    }

    private function bookedAt(Reservation $reservation): Carbon
    {
        return Carbon::parse(
            $reservation->booking_date->format('Y-m-d').' '.$reservation->booking_time
        );
    }
}
