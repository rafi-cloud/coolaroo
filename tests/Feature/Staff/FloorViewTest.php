<?php

namespace Tests\Feature\Staff;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorViewTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_the_floor_page_lists_ready_orders_and_cash_waiting(): void
    {
        $ready = Order::factory()->create();
        $ready->forceFill(['status' => OrderStatus::Ready])->save();

        $cashOrder = Order::factory()->paid()->create();
        Payment::factory()->create(['order_id' => $cashOrder->order_id, 'method' => PaymentMethod::Cash]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee($ready->order_number)
            ->assertSee($cashOrder->order_number);
    }

    public function test_the_state_endpoint_reports_the_paused_setting(): void
    {
        Setting::create(['setting_key' => 'qr_ordering_enabled', 'value_type' => 'bool', 'setting_value' => '0']);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->getJson(route('staff.floor.state'))
            ->assertOk()
            ->assertJsonPath('qr_ordering_paused', true);
    }

    /** N+1 guard for FR16's own "active orders" count. */
    public function test_a_tables_active_order_count_only_counts_active_statuses(): void
    {
        $table = RestaurantTable::factory()->create();

        $paidOrder = Order::factory()->paid()->create(['table_id' => $table->table_id]);
        $servedOrder = Order::factory()->create(['table_id' => $table->table_id]);
        $servedOrder->forceFill(['status' => OrderStatus::Served])->save();

        $response = $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->getJson(route('staff.floor.state'))
            ->assertOk();

        $tablePayload = collect($response->json('tables'))->firstWhere('table_id', $table->table_id);
        $this->assertSame(1, $tablePayload['active_order_count']);
    }

    public function test_kitchen_staff_cannot_reach_the_floor_view(): void
    {
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertForbidden();
    }
}
