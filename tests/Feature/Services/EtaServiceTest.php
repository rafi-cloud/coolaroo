<?php

namespace Tests\Feature\Services;

use App\Enums\Destination;
use App\Events\OrderLinesUpdated;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use App\Services\EtaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_adjust_moves_the_stations_own_column_and_broadcasts(): void
    {
        Event::fake([OrderLinesUpdated::class]);
        $order = Order::factory()->paid()->create(['kitchen_eta_at' => now()->addMinutes(10)]);
        $before = $order->kitchen_eta_at;

        app(EtaService::class)->adjust($order, Destination::Kitchen, 5, $this->staffWithRole('kitchen'));

        $this->assertTrue($order->fresh()->kitchen_eta_at->equalTo($before->copy()->addMinutes(5)));
        Event::assertDispatched(OrderLinesUpdated::class);
    }

    public function test_a_negative_delta_moves_the_eta_earlier(): void
    {
        $order = Order::factory()->paid()->create(['bar_eta_at' => now()->addMinutes(10)]);
        $before = $order->bar_eta_at;

        app(EtaService::class)->adjust($order, Destination::Bar, -5, $this->staffWithRole('bar'));

        $this->assertTrue($order->fresh()->bar_eta_at->equalTo($before->copy()->subMinutes(5)));
    }

    public function test_adjusting_a_station_with_no_eta_is_rejected(): void
    {
        $order = Order::factory()->paid()->create(['kitchen_eta_at' => null]);

        $this->expectException(ValidationException::class);

        app(EtaService::class)->adjust($order, Destination::Kitchen, 5, $this->staffWithRole('kitchen'));
    }

    /**
     * @param  'pending_payment'|'paid'|'served'|'cancelled'  $status
     */
    private function kitchenOrder(string $status, string $lineStatus = 'pending'): Order
    {
        $item = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory(),
            'destination' => Destination::Kitchen,
            'prep_minutes' => 10,
        ]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        $order = Order::factory()->create();
        $order->forceFill(['status' => $status])->save();

        $order->items()->create([
            'line_no' => 1,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => $item->item_name,
            'size_name' => $size->size_name,
            'destination' => Destination::Kitchen,
            'quantity' => 1,
            'original_unit_price' => 12,
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        $order->items()->update(['status' => $lineStatus]);

        return $order->fresh();
    }

    public function test_only_orders_still_in_the_station_queue_count_as_ahead(): void
    {
        $this->kitchenOrder('cancelled');
        $this->kitchenOrder('served');
        $this->kitchenOrder('paid');

        $subject = $this->kitchenOrder('paid');
        $items = $subject->items()->with('menuItem')->get();

        $eta = app(EtaService::class)->estimate($subject, $items, Destination::Kitchen);

        $this->assertSame(18, (int) round(now()->diffInMinutes($eta)));
    }

    public function test_estimate_returns_null_when_the_station_has_no_lines(): void
    {
        $order = Order::factory()->paid()->create();

        $result = app(EtaService::class)->estimate($order, collect(), Destination::Kitchen);

        $this->assertNull($result);
    }
}
