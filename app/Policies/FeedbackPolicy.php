<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Staff;

class FeedbackPolicy
{
    public function moderate(Staff $staff): bool
    {
        return false;
    }

    public function create(Customer $customer, Order $order): bool
    {
        return $order->customer_id !== null
            && $order->customer_id === $customer->customer_id
            && $order->status === OrderStatus::Served
            && $order->taken_by_staff_id === null;
    }
}
