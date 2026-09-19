<?php

namespace Tests\Unit\Policies;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use App\Policies\OrderPolicy;
use PHPUnit\Framework\TestCase;

class OrderPolicyTest extends TestCase
{
    private function staff(string $roleName): Staff
    {
        $staff = new Staff();
        $staff->setRelation('role', (new Role())->forceFill(['role_name' => $roleName]));

        return $staff;
    }

    public function test_only_waitstaff_can_take_an_order(): void
    {
        $policy = new OrderPolicy();

        $this->assertTrue($policy->take($this->staff('waitstaff')));
        $this->assertFalse($policy->take($this->staff('kitchen')));
    }

    public function test_kitchen_and_bar_can_request_a_refund_but_not_issue_one(): void
    {
        $policy = new OrderPolicy();

        foreach (['waitstaff', 'kitchen', 'bar'] as $role) {
            $this->assertTrue($policy->requestRefund($this->staff($role)), $role);
            $this->assertFalse($policy->issueRefund($this->staff($role)), $role);
        }
    }

    public function test_customer_can_cancel_their_own_order_only(): void
    {
        $policy = new OrderPolicy();
        $customer = (new Customer())->forceFill(['customer_id' => 7]);
        $ownOrder = (new Order())->forceFill(['order_id' => 1, 'customer_id' => 7]);
        $othersOrder = (new Order())->forceFill(['order_id' => 2, 'customer_id' => 9]);
        $guestOrder = (new Order())->forceFill(['order_id' => 3, 'customer_id' => null]);

        $this->assertTrue($policy->cancel($customer, $ownOrder));
        $this->assertFalse($policy->cancel($customer, $othersOrder));
        $this->assertFalse($policy->cancel($customer, $guestOrder));
    }

    public function test_waitstaff_can_cancel_any_order_kitchen_cannot(): void
    {
        $policy = new OrderPolicy();
        $order = (new Order())->forceFill(['order_id' => 1, 'customer_id' => null]);

        $this->assertTrue($policy->cancel($this->staff('waitstaff'), $order));
        $this->assertFalse($policy->cancel($this->staff('kitchen'), $order));
    }
}
