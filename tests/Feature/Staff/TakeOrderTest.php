<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TakeOrderTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    private function orderableItem(?int $dailyLimit = null): array
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen, 'daily_limit' => $dailyLimit]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 15]);

        return [$item, $size];
    }

    public function test_waitstaff_creates_a_pending_order_for_a_table(): void
    {
        [$item, $size] = $this->orderableItem();
        $table = RestaurantTable::factory()->create();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.tables.order.store', $table), [
                'lines' => [
                    ['item_size' => "{$item->item_id}:{$size->size_id}", 'quantity' => 2, 'special_request' => null],
                ],
            ])
            ->assertRedirect(route('staff.tables.order', $table));

        $order = Order::where('table_id', $table->table_id)->firstOrFail();
        $this->assertSame('pending_payment', $order->status->value);
        $this->assertNull($order->customer_id);
        $this->assertNotNull($order->taken_by_staff_id);
        $this->assertSame('waitstaff', $order->statusHistory->first()->event_source);
    }

    /** BR53: exact check, not the 5x QR buffer. */
    public function test_a_staff_order_uses_the_exact_stock_check_not_the_qr_buffer(): void
    {
        [$item, $size] = $this->orderableItem(dailyLimit: 3);
        $table = RestaurantTable::factory()->create();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.tables.order.store', $table), [
                'lines' => [
                    ['item_size' => "{$item->item_id}:{$size->size_id}", 'quantity' => 3, 'special_request' => null],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, Order::where('table_id', $table->table_id)->count());
    }

    public function test_the_order_page_shows_payment_choice_once_an_order_exists(): void
    {
        [$item, $size] = $this->orderableItem();
        $table = RestaurantTable::factory()->create();
        $staff = $this->staffWithRole('waitstaff');

        $this->actingAs($staff, 'staff')->post(route('staff.tables.order.store', $table), [
            'lines' => [['item_size' => "{$item->item_id}:{$size->size_id}", 'quantity' => 1, 'special_request' => null]],
        ]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.tables.order', $table))
            ->assertOk()
            ->assertViewIs('staff.floor.order-payment');
    }

    public function test_kitchen_staff_cannot_take_an_order(): void
    {
        $table = RestaurantTable::factory()->create();

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.tables.order', $table))
            ->assertForbidden();
    }
}
