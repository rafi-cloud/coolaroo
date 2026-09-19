<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'method' => PaymentMethod::Stripe,
            'amount' => 10,
        ];
    }
}
