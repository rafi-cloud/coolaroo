<?php

namespace Tests\Feature\Services;

use App\Enums\Destination;
use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use App\Services\KitchenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KitchenServiceTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithLines(array $lines): Order
    {
        $order = Order::factory()->paid()->create();

        foreach ($lines as $index => [$destination, $status]) {
            $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => $destination]);
            $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 10]);

            $line = $order->items()->create([
                'line_no' => $index + 1,
                'item_id' => $item->item_id,
                'size_id' => $size->size_id,
                'item_name' => $item->item_name,
                'size_name' => 'Regular',
                'destination' => $destination,
                'quantity' => 1,
                'original_unit_price' => 10,
                'unit_price' => 10,
                'line_total' => 10,
            ]);

            $line->forceFill(['status' => $status])->save();
        }

        return $order->fresh();
    }

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    /** BR28: any active line preparing → order preparing. */
    public function test_start_moves_only_this_stations_lines_and_derives_preparing(): void
    {
        $order = $this->orderWithLines([
            [Destination::Kitchen, 'pending'],
            [Destination::Bar, 'pending'],
        ]);

        app(KitchenService::class)->start($order, Destination::Kitchen, $this->staffWithRole('kitchen'));

        $order->refresh();
        $this->assertSame(OrderStatus::Preparing, $order->status);
        $this->assertSame('preparing', $order->items->firstWhere('destination', Destination::Kitchen)->status->value);
        $this->assertSame('pending', $order->items->firstWhere('destination', Destination::Bar)->status->value);
        $this->assertNotNull($order->started_at);
    }

    /** BR28: ready only when ALL active lines are ready or served. */
    public function test_the_order_is_ready_only_once_every_station_is_ready(): void
    {
        $order = $this->orderWithLines([
            [Destination::Kitchen, 'preparing'],
            [Destination::Bar, 'preparing'],
        ]);
        $kitchen = app(KitchenService::class);

        $kitchen->ready($order, Destination::Kitchen, $this->staffWithRole('kitchen'));
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        $kitchen->ready($order, Destination::Bar, $this->staffWithRole('bar'));
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);
    }

    /** BR28's "active": a refund-cancelled line must not hold the order back. */
    public function test_cancelled_lines_are_ignored_when_deriving(): void
    {
        $order = $this->orderWithLines([
            [Destination::Kitchen, 'preparing'],
            [Destination::Bar, 'cancelled'],
        ]);

        app(KitchenService::class)->ready($order, Destination::Kitchen, $this->staffWithRole('kitchen'));

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
    }

    /** 5.1 is walked one legal step at a time, so the history stays truthful. */
    public function test_the_status_ladder_is_climbed_one_legal_step_at_a_time(): void
    {
        $order = $this->orderWithLines([[Destination::Kitchen, 'preparing']]);

        app(KitchenService::class)->ready($order, Destination::Kitchen, $this->staffWithRole('kitchen'));

        $this->assertSame(
            ['preparing', 'ready'],
            $order->fresh()->statusHistory->pluck('status')->all(),
        );
    }

    /** FR60, BR28: the order climbs to served once every station's ready lines are served. */
    public function test_serving_the_last_stations_lines_completes_the_order(): void
    {
        $order = $this->orderWithLines([[Destination::Kitchen, 'ready']]);

        app(KitchenService::class)->serve($order, Destination::Kitchen, $this->staffWithRole('waitstaff'));

        $order->refresh();
        $this->assertSame(OrderStatus::Served, $order->status);
        $this->assertSame('served', $order->items->first()->status->value);
        $this->assertNotNull($order->served_at);
    }

    /** A two-station order isn't served until both are delivered. */
    public function test_serving_one_station_does_not_complete_a_two_station_order(): void
    {
        $order = $this->orderWithLines([
            [Destination::Kitchen, 'ready'],
            [Destination::Bar, 'ready'],
        ]);

        app(KitchenService::class)->serve($order, Destination::Kitchen, $this->staffWithRole('waitstaff'));

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
    }

    public function test_starting_a_station_with_no_pending_lines_is_rejected(): void
    {
        $order = $this->orderWithLines([[Destination::Kitchen, 'ready']]);

        $this->expectException(ValidationException::class);

        app(KitchenService::class)->start($order, Destination::Kitchen, $this->staffWithRole('kitchen'));
    }
}
