<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServeOrderTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    private function readyOrder(): Order
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
        $line->forceFill(['status' => 'ready'])->save();
        $order->forceFill(['status' => OrderStatus::Ready])->save();

        return $order->fresh();
    }

    public function test_waitstaff_mark_an_order_served(): void
    {
        $order = $this->readyOrder();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.serve', [$order, 'kitchen']))
            ->assertRedirect();

        $this->assertSame('served', $order->fresh()->status->value);
    }

    public function test_kitchen_staff_cannot_mark_served(): void
    {
        $order = $this->readyOrder();

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.orders.serve', [$order, 'kitchen']))
            ->assertForbidden();
    }
}
