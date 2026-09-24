<?php

namespace App\Policies;

use App\Enums\Destination;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Staff;

/**
 * 3.3: order rows. Admin passes every method via Gate::before.
 */
class OrderPolicy
{
    public function take(Staff $staff): bool
    {
        return $staff->role->role_name === 'waitstaff';
    }

    public function recordPayment(Staff $staff): bool
    {
        return $staff->role->role_name === 'waitstaff';
    }

    public function markServed(Staff $staff): bool
    {
        return $staff->role->role_name === 'waitstaff';
    }

    public function requestRefund(Staff $staff): bool
    {
        return in_array($staff->role->role_name, ['waitstaff', 'kitchen', 'bar'], true);
    }

    /**
     * BR27: a customer may raise a request against their own paid order. The
     * 24-hour window and what is left to refund are RefundService's call —
     * column checks only here, as with FeedbackPolicy::create().
     */
    public function requestRefundAsCustomer(Customer $customer, Order $order): bool
    {
        return $order->customer_id !== null
            && $order->customer_id === $customer->customer_id
            && $order->payment_status === PaymentStatus::Paid;
    }

    /** a station may only move its own lines. Admin passes via Gate::before. */
    public function updateStation(Staff $staff, Destination $destination): bool
    {
        return $staff->role->role_name === $destination->value;
    }

    public function resolveStockConflict(Staff $staff): bool
    {
        return in_array($staff->role->role_name, ['kitchen', 'bar'], true);
    }

    public function issueRefund(Staff $staff): bool
    {
        return false;
    }

    public function cancel(Staff|Customer $user, Order $order): bool
    {
        if ($user instanceof Staff) {
            return $user->role->role_name === 'waitstaff';
        }

        return $order->customer_id !== null
            && $order->customer_id === $user->customer_id
            && $order->status === OrderStatus::PendingPayment;
    }

    public function view(Customer $customer, Order $order): bool
    {
        return $order->customer_id !== null && $order->customer_id === $customer->customer_id;
    }

    public function pay(Customer $customer, Order $order): bool
    {
        return $order->customer_id !== null
            && $order->customer_id === $customer->customer_id
            && $order->status === OrderStatus::PendingPayment;
    }
}
