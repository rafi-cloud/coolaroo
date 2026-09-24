<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Staff;

class ReservationPolicy
{
    public function manage(Staff $staff): bool
    {
        return $staff->role->role_name === 'waitstaff';
    }

    public function clearNoShow(Staff $staff): bool
    {
        return false;
    }

    public function create(Customer $customer): bool
    {
        return true;
    }

    public function view(Customer $customer, Reservation $reservation): bool
    {
        return $this->owns($customer, $reservation);
    }

    public function update(Customer $customer, Reservation $reservation): bool
    {
        return $this->owns($customer, $reservation);
    }

    public function cancel(Customer $customer, Reservation $reservation): bool
    {
        return $this->owns($customer, $reservation);
    }

    private function owns(Customer $customer, Reservation $reservation): bool
    {
        return $reservation->customer_id === $customer->customer_id;
    }
}
