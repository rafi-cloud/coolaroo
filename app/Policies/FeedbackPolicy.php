<?php

namespace App\Policies;

use App\Enums\OrderStatus;
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

    /**
     * BR43, BR53: served, paid QR order, not staff-taken. Column checks only
     * (no relation query) — every other Policy test in this project is a
     * pure unit test against in-memory objects; "not already submitted" is a
     * stateful check left to FeedbackService::submit(), same as
     * OrderService::cancelUnpaid() re-validates status at write time even
     * after OrderPolicy::cancel() already authorized the request.
     */
    public function create(Customer $customer, Order $order): bool
    {
        return $order->customer_id !== null
            && $order->customer_id === $customer->customer_id
            && $order->status === OrderStatus::Served
            && $order->taken_by_staff_id === null;
    }
}
