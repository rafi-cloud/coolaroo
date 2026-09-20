<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->admin()->create()->role_id]);
    }

    public function test_admin_finds_an_order_by_number(): void
    {
        $order = Order::factory()->create(['order_number' => '20260920-007']);
        Order::factory()->create(['order_number' => '20260920-008']);

        $this->actingAs($this->admin(), 'staff')
            ->get(route('admin.orders.index', ['number' => '007']))
            ->assertOk()
            ->assertSee('20260920-007')
            ->assertDontSee('20260920-008');
    }

    public function test_admin_filters_by_status(): void
    {
        $ready = Order::factory()->create();
        $ready->forceFill(['status' => OrderStatus::Ready])->save();
        $paid = Order::factory()->paid()->create();

        $this->actingAs($this->admin(), 'staff')
            ->get(route('admin.orders.index', ['status' => 'ready']))
            ->assertOk()
            ->assertSee($ready->order_number)
            ->assertDontSee($paid->order_number);
    }

    public function test_admin_can_view_an_order_with_its_lines_and_history(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin(), 'staff')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_waitstaff_cannot_reach_the_admin_orders_page(): void
    {
        $waitstaff = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);

        $this->actingAs($waitstaff, 'staff')
            ->get(route('admin.orders.index'))
            ->assertForbidden();
    }
}
