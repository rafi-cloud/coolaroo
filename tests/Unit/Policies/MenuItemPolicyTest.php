<?php

namespace Tests\Unit\Policies;

use App\Enums\Destination;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use App\Policies\MenuItemPolicy;
use PHPUnit\Framework\TestCase;

class MenuItemPolicyTest extends TestCase
{
    private function staff(string $roleName): Staff
    {
        $staff = new Staff();
        $staff->setRelation('role', (new Role())->forceFill(['role_name' => $roleName]));

        return $staff;
    }

    public function test_only_kitchen_can_toggle_a_kitchen_item(): void
    {
        $policy = new MenuItemPolicy();
        $item = (new MenuItem())->forceFill(['destination' => Destination::Kitchen]);

        $this->assertTrue($policy->toggleAvailability($this->staff('kitchen'), $item));
        $this->assertFalse($policy->toggleAvailability($this->staff('bar'), $item));
        $this->assertFalse($policy->toggleAvailability($this->staff('waitstaff'), $item));
    }

    public function test_only_bar_can_toggle_a_bar_item(): void
    {
        $policy = new MenuItemPolicy();
        $item = (new MenuItem())->forceFill(['destination' => Destination::Bar]);

        $this->assertTrue($policy->toggleAvailability($this->staff('bar'), $item));
        $this->assertFalse($policy->toggleAvailability($this->staff('kitchen'), $item));
    }

    public function test_manage_is_admin_only(): void
    {
        $this->assertFalse((new MenuItemPolicy())->manage($this->staff('waitstaff')));
    }
}
