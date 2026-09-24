<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('order.{orderId}', function (Customer|Staff $user, int $orderId) {
    if ($user instanceof Staff) {
        return true;
    }

    return Order::where('order_id', $orderId)
        ->where('customer_id', $user->customer_id)
        ->exists();
}, ['guards' => ['customer', 'staff']]);

Broadcast::channel('station.{destination}', function (Staff $staff, string $destination) {
    if (! in_array($destination, ['kitchen', 'bar'], true)) {
        return false;
    }

    return in_array($staff->role->role_name, [$destination, 'admin'], true);
}, ['guards' => ['staff']]);

Broadcast::channel('floor', function (Staff $staff) {
    return in_array($staff->role->role_name, ['waitstaff', 'admin'], true);
}, ['guards' => ['staff']]);

Broadcast::channel('admin', function (Staff $staff) {
    return $staff->role->role_name === 'admin';
}, ['guards' => ['staff']]);
