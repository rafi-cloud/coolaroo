<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Events\OrderLinesUpdated;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StationActionsTest extends TestCase
{
    use RefreshDatabase;

    private function kitchenOrder(string $lineStatus = 'pending'): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 10]);
        $order = Order::factory()->paid()->create();

        $line = $order->items()->create([
            'line_no' => 1,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => $item->item_name,
            'size_name' => 'Regular',
            'destination' => Destination::Kitchen,
            'quantity' => 1,
            'original_unit_price' => 10,
            'unit_price' => 10,
            'line_total' => 10,
        ]);
        $line->forceFill(['status' => $lineStatus])->save();

        return $order->fresh();
    }

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_kitchen_staff_start_an_order_and_the_station_is_told(): void
    {
        Event::fake([OrderLinesUpdated::class]);
        $order = $this->kitchenOrder();

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.kds.start', [$order, 'kitchen']))
            ->assertRedirect();

        $this->assertSame('preparing', $order->fresh()->status->value);
        Event::assertDispatched(OrderLinesUpdated::class);
    }

    /** FR58: a station may only move its own lines — role:kitchen,bar alone does not enforce that. */
    public function test_bar_staff_cannot_start_kitchen_lines(): void
    {
        $order = $this->kitchenOrder();

        $this->actingAs($this->staffWithRole('bar'), 'staff')
            ->post(route('staff.kds.start', [$order, 'kitchen']))
            ->assertForbidden();

        $this->assertSame('paid', $order->fresh()->status->value);
    }

    public function test_waitstaff_cannot_use_the_station_actions(): void
    {
        $order = $this->kitchenOrder();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.kds.start', [$order, 'kitchen']))
            ->assertForbidden();
    }
}
