<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    private function authorise(string $channel): TestResponse
    {
        return $this->post('/broadcasting/auth', [
            'channel_name' => 'private-'.$channel,
            'socket_id' => '1234.5678',
        ]);
    }

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    private function orderFor(?Customer $customer): Order
    {
        return Order::factory()->create([
            'table_id' => RestaurantTable::factory(),
            'customer_id' => $customer?->customer_id,
        ]);
    }

    public function test_a_customer_may_listen_to_their_own_order_only(): void
    {
        $customer = Customer::factory()->create();
        $own = $this->orderFor($customer);
        $other = $this->orderFor(Customer::factory()->create());

        $this->actingAs($customer, 'customer')->authorise("order.{$own->order_id}")->assertOk();
        $this->actingAs($customer, 'customer')->authorise("order.{$other->order_id}")->assertForbidden();
    }

    public function test_staff_may_listen_to_any_order(): void
    {
        $order = $this->orderFor(Customer::factory()->create());

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->authorise("order.{$order->order_id}")
            ->assertOk();
    }

    public function test_a_station_channel_is_limited_to_its_own_role_and_admin(): void
    {
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')->authorise('station.kitchen')->assertOk();
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')->authorise('station.bar')->assertForbidden();
        $this->actingAs($this->staffWithRole('admin'), 'staff')->authorise('station.bar')->assertOk();
    }

    public function test_floor_and_admin_channels_follow_their_screen_actors(): void
    {
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')->authorise('floor')->assertOk();
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')->authorise('floor')->assertForbidden();
        $this->actingAs($this->staffWithRole('admin'), 'staff')->authorise('admin')->assertOk();
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')->authorise('admin')->assertForbidden();
    }

    public function test_a_guest_cannot_authorise_any_private_channel(): void
    {
        $this->authorise('floor')->assertForbidden();
    }
}
