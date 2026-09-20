<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Support\Facades\Broadcast;

/**
 * NFR06, 07.8. Every channel here is private. The 'guards' option is what
 * makes this work at all: channel callbacks resolve the user from the default
 * guard (customer), so staff-facing channels must name 'staff' explicitly.
 *
 * 'menu' (07.8) is deliberately absent — it is a public channel and needs no
 * authorisation callback.
 */

/** 07.8: the owning customer, or any staff member. */
Broadcast::channel('order.{orderId}', function (Customer|Staff $user, int $orderId) {
    if ($user instanceof Staff) {
        return true;
    }

    return Order::where('order_id', $orderId)
        ->where('customer_id', $user->customer_id)
        ->exists();
}, ['guards' => ['customer', 'staff']]);

/** S30: Kitchen and Bar see their own station; Admin sees both. */
Broadcast::channel('station.{destination}', function (Staff $staff, string $destination) {
    if (! in_array($destination, ['kitchen', 'bar'], true)) {
        return false;
    }

    return in_array($staff->role->role_name, [$destination, 'admin'], true);
}, ['guards' => ['staff']]);

/** S22: the floor view is Waitstaff and Admin. */
Broadcast::channel('floor', function (Staff $staff) {
    return in_array($staff->role->role_name, ['waitstaff', 'admin'], true);
}, ['guards' => ['staff']]);

/** S32-S34: admin dashboard, orders and refund queue. */
Broadcast::channel('admin', function (Staff $staff) {
    return $staff->role->role_name === 'admin';
}, ['guards' => ['staff']]);
