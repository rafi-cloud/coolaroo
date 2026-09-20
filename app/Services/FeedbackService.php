<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * BR43: one feedback per served, paid QR order. Ownership, served status and
 * staff-taken exclusion are FeedbackPolicy::create()'s job, checked before
 * this runs; "not already submitted" is checked here instead, since it needs
 * a query the policy deliberately avoids (every other Policy in this project
 * is a pure, DB-free unit test).
 */
class FeedbackService
{
    public function submit(Order $order, Customer $customer, array $data): Feedback
    {
        if ($order->feedback()->exists()) {
            throw ValidationException::withMessages([
                'feedback' => 'Feedback has already been submitted for this order.',
            ]);
        }

        return Feedback::create([
            'order_id' => $order->order_id,
            'customer_id' => $customer->customer_id,
            'food_rating' => $data['food_rating'],
            'service_rating' => $data['service_rating'],
            'comment' => $data['comment'] ?? null,
        ]);
    }
}
