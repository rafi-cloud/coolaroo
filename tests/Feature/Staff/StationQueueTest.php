<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationQueueTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    private function orderWithLine(Destination $destination, string $lineStatus = 'pending', array $orderAttributes = []): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => $destination]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        $order = Order::factory()->paid()->create($orderAttributes);

        $line = $order->items()->create([
            'line_no' => 1,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => $item->item_name,
            'size_name' => 'Regular',
            'destination' => $destination,
            'quantity' => 2,
            'original_unit_price' => 12,
            'unit_price' => 12,
            'line_total' => 24,
        ]);

        $line->forceFill(['status' => $lineStatus])->save();

        return $order->fresh();
    }

    public function test_the_queue_shows_this_stations_orders_oldest_first(): void
    {
        $older = $this->orderWithLine(Destination::Kitchen, orderAttributes: ['paid_at' => now()->subMinutes(20)]);
        $newer = $this->orderWithLine(Destination::Kitchen, orderAttributes: ['paid_at' => now()->subMinutes(2)]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.kds.index', 'kitchen'))
            ->assertOk()
            ->assertSeeInOrder([$older->order_number, $newer->order_number]);
    }

    public function test_the_eta_steppers_are_offered_while_there_is_still_cooking_to_do(): void
    {
        $order = $this->orderWithLine(Destination::Kitchen, 'preparing', [
            'kitchen_eta_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.kds.index', 'kitchen'))
            ->assertOk()
            ->assertSee('data-testid="kds-eta-plus-'.$order->order_id.'"', false)
            ->assertSee('data-testid="kds-eta-minus-'.$order->order_id.'"', false);
    }

    public function test_the_eta_steppers_disappear_once_the_station_is_ready(): void
    {
        $order = $this->orderWithLine(Destination::Kitchen, 'ready', [
            'kitchen_eta_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.kds.index', 'kitchen'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertDontSee('data-testid="kds-eta-plus-'.$order->order_id.'"', false)
            ->assertDontSee('data-testid="kds-eta-minus-'.$order->order_id.'"', false);
    }

    public function test_another_stations_order_is_not_shown(): void
    {
        $bar = $this->orderWithLine(Destination::Bar);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.kds.index', 'kitchen'))
            ->assertOk()
            ->assertDontSee($bar->order_number);
    }

    public function test_the_status_filter_narrows_the_queue(): void
    {
        $pending = $this->orderWithLine(Destination::Kitchen, 'pending');
        $preparing = $this->orderWithLine(Destination::Kitchen, 'preparing');

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.kds.index', ['destination' => 'kitchen', 'status' => 'preparing']))
            ->assertOk()
            ->assertSee($preparing->order_number)
            ->assertDontSee($pending->order_number);
    }

    public function test_the_state_endpoint_returns_the_same_queue_as_json(): void
    {
        $order = $this->orderWithLine(Destination::Kitchen);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->getJson(route('staff.kds.state', 'kitchen'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('orders.0.order_number', $order->order_number)
            ->assertJsonPath('orders.0.lines.0.quantity', 2);
    }

    public function test_the_state_signature_changes_when_a_line_moves_on(): void
    {
        $order = $this->orderWithLine(Destination::Kitchen);
        $cook = $this->staffWithRole('kitchen');

        $before = $this->actingAs($cook, 'staff')
            ->getJson(route('staff.kds.state', 'kitchen'))
            ->json('signature');

        $this->actingAs($cook, 'staff')
            ->get(route('staff.kds.index', 'kitchen'))
            ->assertOk()
            ->assertSee('data-signature="'.$before.'"', false);

        $order->items()->update(['status' => 'preparing']);

        $after = $this->actingAs($cook, 'staff')
            ->getJson(route('staff.kds.state', 'kitchen'))
            ->json('signature');

        $this->assertNotSame($before, $after);
    }

    public function test_waitstaff_cannot_open_a_station_display(): void
    {
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.kds.index', 'kitchen'))
            ->assertForbidden();
    }
}
