<?php

namespace Tests\Unit\Policies;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Staff;
use App\Policies\ReservationPolicy;
use PHPUnit\Framework\TestCase;

class ReservationPolicyTest extends TestCase
{
    private function staff(string $roleName): Staff
    {
        $staff = new Staff();
        $staff->setRelation('role', (new Role())->forceFill(['role_name' => $roleName]));

        return $staff;
    }

    public function test_only_waitstaff_can_manage_reservations(): void
    {
        $policy = new ReservationPolicy();

        $this->assertTrue($policy->manage($this->staff('waitstaff')));
        $this->assertFalse($policy->manage($this->staff('kitchen')));
        $this->assertFalse($policy->manage($this->staff('bar')));
    }

    public function test_clear_no_show_is_admin_only(): void
    {
        $this->assertFalse((new ReservationPolicy())->clearNoShow($this->staff('waitstaff')));
    }

    public function test_any_customer_can_request_a_reservation(): void
    {
        $customer = (new Customer())->forceFill(['customer_id' => 1]);

        $this->assertTrue((new ReservationPolicy())->create($customer));
    }

    public function test_customer_can_only_touch_their_own_reservation(): void
    {
        $policy = new ReservationPolicy();
        $customer = (new Customer())->forceFill(['customer_id' => 5]);
        $own = (new Reservation())->forceFill(['reservation_id' => 1, 'customer_id' => 5]);
        $others = (new Reservation())->forceFill(['reservation_id' => 2, 'customer_id' => 6]);

        $this->assertTrue($policy->view($customer, $own));
        $this->assertTrue($policy->update($customer, $own));
        $this->assertTrue($policy->cancel($customer, $own));
        $this->assertFalse($policy->view($customer, $others));
        $this->assertFalse($policy->update($customer, $others));
        $this->assertFalse($policy->cancel($customer, $others));
    }
}
