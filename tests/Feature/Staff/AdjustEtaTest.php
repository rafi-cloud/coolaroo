<?php

namespace Tests\Feature\Staff;

use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustEtaTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_kitchen_staff_adjust_the_kitchen_eta(): void
    {
        $order = Order::factory()->paid()->create(['kitchen_eta_at' => now()->addMinutes(10)]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->patch(route('staff.kds.eta', [$order, 'kitchen']), ['minutes' => 5])
            ->assertRedirect();

        $this->assertNotNull($order->fresh()->kitchen_eta_at);
    }

    public function test_bar_staff_cannot_adjust_the_kitchen_eta(): void
    {
        $order = Order::factory()->paid()->create(['kitchen_eta_at' => now()->addMinutes(10)]);

        $this->actingAs($this->staffWithRole('bar'), 'staff')
            ->patch(route('staff.kds.eta', [$order, 'kitchen']), ['minutes' => 5])
            ->assertForbidden();
    }

    public function test_zero_minutes_fails_validation(): void
    {
        $order = Order::factory()->paid()->create(['kitchen_eta_at' => now()->addMinutes(10)]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->patch(route('staff.kds.eta', [$order, 'kitchen']), ['minutes' => 0])
            ->assertSessionHasErrors('minutes');
    }
}
