<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Staff;

/**
 * 3.3: feedback rows. Admin passes every method via Gate::before.
 */
class FeedbackPolicy
{
    public function moderate(Staff $staff): bool
    {
        return false;
    }

    public function create(Customer $customer, Order $order): bool
    {
        return $order->customer_id !== null && $order->customer_id === $customer->customer_id;
    }
}
