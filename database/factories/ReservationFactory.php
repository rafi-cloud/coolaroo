<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\SlotCapacity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'slot_id' => SlotCapacity::factory(),
            'reference_code' => 'CR-'.Str::upper(Str::random(6)),
            'booking_date' => now()->toDateString(),
            'booking_time' => '18:00',
            'party_size' => 2,
        ];
    }

    public function confirmed(): static
    {
        return $this->afterCreating(
            fn (Reservation $reservation) => $reservation->forceFill(['status' => 'confirmed'])->save()
        );
    }
}
