<?php

namespace Tests\Unit\Policies;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use App\Policies\FeedbackPolicy;
use PHPUnit\Framework\TestCase;

class FeedbackPolicyTest extends TestCase
{
    public function test_moderate_is_admin_only(): void
    {
        $waitstaff = new Staff();
        $waitstaff->setRelation('role', (new Role())->forceFill(['role_name' => 'waitstaff']));

        $this->assertFalse((new FeedbackPolicy())->moderate($waitstaff));
    }

    public function test_customer_can_only_submit_feedback_on_their_own_order(): void
    {
        $policy = new FeedbackPolicy();
        $customer = (new Customer())->forceFill(['customer_id' => 3]);
        $ownOrder = (new Order())->forceFill(['order_id' => 1, 'customer_id' => 3]);
        $othersOrder = (new Order())->forceFill(['order_id' => 2, 'customer_id' => 4]);
        $guestOrder = (new Order())->forceFill(['order_id' => 3, 'customer_id' => null]);

        $this->assertTrue($policy->create($customer, $ownOrder));
        $this->assertFalse($policy->create($customer, $othersOrder));
        $this->assertFalse($policy->create($customer, $guestOrder));
    }
}
