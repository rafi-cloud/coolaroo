<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Order>
 *
 * status and payment_status are $guarded on the model (they belong to the
 * transition maps), so the states below set them through forceFill after
 * creation rather than as attributes.
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_id' => RestaurantTable::factory(),
            'order_number' => strtoupper(Str::random(10)),
            'idempotency_key' => (string) Str::uuid(),
            'total_amount' => 22,
            'gst_amount' => 2,
            'placed_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->afterCreating(fn ($order) => $order->forceFill([
            'status' => OrderStatus::Paid,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ])->save());
    }
}
