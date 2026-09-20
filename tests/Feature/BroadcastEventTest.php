<?php

namespace Tests\Feature;

use App\Enums\Destination;
use App\Events\MenuAvailabilityChanged;
use App\Events\OrderLinesUpdated;
use App\Events\ReservationAlert;
use App\Events\SettingSwitched;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastEventTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function channelNames(object $event): array
    {
        return array_map(fn ($channel) => (string) $channel, $event->broadcastOn());
    }

    /** 07.8 lists eleven events. */
    public function test_all_eleven_events_from_the_design_exist(): void
    {
        foreach ([
            'OrderPaid', 'OrderStatusChanged', 'OrderLinesUpdated', 'StockConflictDetected',
            'CashPaymentRequested', 'WaiterCalled', 'TableStatusChanged', 'ReservationAlert',
            'RefundRequested', 'MenuAvailabilityChanged', 'SettingSwitched',
        ] as $event) {
            $this->assertTrue(class_exists("App\\Events\\{$event}"), "{$event} is missing");
        }
    }

    public function test_order_lines_updated_reaches_the_station_and_the_order(): void
    {
        $order = Order::factory()->paid()->create();
        $event = new OrderLinesUpdated($order, Destination::Kitchen);

        $this->assertSame(
            ['private-station.kitchen', "private-order.{$order->order_id}"],
            $this->channelNames($event),
        );
        $this->assertSame('kitchen', $event->broadcastWith()['destination']);
    }

    public function test_reservation_alert_carries_its_kind_to_floor_and_admin(): void
    {
        $event = new ReservationAlert(Reservation::factory()->create(), ReservationAlert::PLACE_SIGN);

        $this->assertSame(['private-floor', 'private-admin'], $this->channelNames($event));
        $this->assertSame('place_sign', $event->broadcastWith()['kind']);
    }

    /** T110: 'menu' has no authorisation callback, so it must stay public. */
    public function test_the_menu_channel_events_are_public(): void
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory()]);

        $availability = new MenuAvailabilityChanged($item);
        $this->assertInstanceOf(Channel::class, $availability->broadcastOn()[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $availability->broadcastOn()[0]);
        $this->assertSame(['menu'], $this->channelNames($availability));
        $this->assertSame('menu_item', $availability->broadcastWith()['entity']);

        $switched = new SettingSwitched('qr_ordering_enabled', '0');
        $this->assertSame(['menu', 'private-floor'], $this->channelNames($switched));
    }
}
