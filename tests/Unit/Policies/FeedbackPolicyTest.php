<?php

namespace Tests\Unit\Policies;

use App\Enums\OrderStatus;
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
        $waitstaff = new Staff;
        $waitstaff->setRelation('role', (new Role)->forceFill(['role_name' => 'waitstaff']));

        $this->assertFalse((new FeedbackPolicy)->moderate($waitstaff));
    }

    public function test_customer_can_only_submit_feedback_on_their_own_served_qr_order(): void
    {
        $policy = new FeedbackPolicy;
        $customer = (new Customer)->forceFill(['customer_id' => 3]);
        $ownOrder = (new Order)->forceFill(['order_id' => 1, 'customer_id' => 3, 'status' => OrderStatus::Served]);
        $othersOrder = (new Order)->forceFill(['order_id' => 2, 'customer_id' => 4, 'status' => OrderStatus::Served]);
        $guestOrder = (new Order)->forceFill(['order_id' => 3, 'customer_id' => null, 'status' => OrderStatus::Served]);
        $notServedOrder = (new Order)->forceFill(['order_id' => 4, 'customer_id' => 3, 'status' => OrderStatus::Paid]);
        $staffTakenOrder = (new Order)->forceFill(['order_id' => 5, 'customer_id' => 3, 'status' => OrderStatus::Served, 'taken_by_staff_id' => 9]);

        $this->assertTrue($policy->create($customer, $ownOrder));
        $this->assertFalse($policy->create($customer, $othersOrder));
        $this->assertFalse($policy->create($customer, $guestOrder));
        $this->assertFalse($policy->create($customer, $notServedOrder), 'BR43: only served orders are eligible');
        $this->assertFalse($policy->create($customer, $staffTakenOrder), 'BR53: staff-taken orders are not eligible for feedback');
    }
}
