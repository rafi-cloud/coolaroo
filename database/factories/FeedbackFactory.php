<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->served()->create(['customer_id' => $customer->customer_id]);

        return [
            'order_id' => $order->order_id,
            'customer_id' => $customer->customer_id,
            'food_rating' => $this->faker->numberBetween(1, 5),
            'service_rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentence(),
        ];
    }
}
