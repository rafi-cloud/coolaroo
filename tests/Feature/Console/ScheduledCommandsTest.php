<?php

namespace Tests\Feature\Console;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationAlert;
use App\Mail\ReservationReminderMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Services\StripeService;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;
use Tests\TestCase;

/**
 * T160, UC38, 07.10. One test per scheduled command, plus the two rules most
 * likely to break: BR26's "verify before you cancel" and BR39's once-only
 * suggestion.
 */
class ScheduledCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_all_eight_scheduled_commands_are_registered(): void
    {
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->implode(' ');

        foreach ([
            'stock:reset-daily',
            'payments:reconcile',
            'orders:cleanup-unpaid',
            'tables:auto-clear',
            'reservations:switch-reserved',
            'reservations:expire-requests',
            'reservations:send-reminders',
            'reservations:suggest-no-shows',
        ] as $command) {
            $this->assertStringContainsString($command, $scheduled);
        }
    }

    public function test_stock_reset_daily_zeroes_the_sold_counters(): void
    {
        $item = MenuItem::factory()->create(['sold_today' => 17]);

        $this->artisan('stock:reset-daily')->assertSuccessful();

        $this->assertSame(0, $item->fresh()->sold_today);
    }

    public function test_payments_reconcile_marks_an_order_paid_when_stripe_confirms(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::create([
            'order_id' => $order->order_id,
            'method' => 'stripe',
            'amount' => 22,
            'stripe_session_id' => 'cs_test_reconcile',
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')
                ->with('cs_test_reconcile')
                ->once()
                ->andReturn(Session::constructFrom(['id' => 'cs_test_reconcile', 'payment_status' => 'paid', 'status' => 'complete']));
        });

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_payments_reconcile_closes_an_attempt_stripe_has_expired(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::create([
            'order_id' => $order->order_id,
            'method' => 'stripe',
            'amount' => 22,
            'stripe_session_id' => 'cs_test_stale',
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')
                ->andReturn(Session::constructFrom(['id' => 'cs_test_stale', 'payment_status' => 'unpaid', 'status' => 'expired']));
        });

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentAttemptStatus::Expired, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_cleanup_unpaid_cancels_an_order_stripe_still_reports_unpaid(): void
    {
        $order = Order::factory()->create();
        Payment::create([
            'order_id' => $order->order_id,
            'method' => 'stripe',
            'amount' => 22,
            'stripe_session_id' => 'cs_test_close',
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('expireSession')->with('cs_test_close')->once();
            $mock->shouldReceive('retrieveSession')
                ->andReturn(Session::constructFrom(['id' => 'cs_test_close', 'payment_status' => 'unpaid', 'status' => 'expired']));
        });

        $this->artisan('orders:cleanup-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame('system', OrderStatusHistory::where('order_id', $order->order_id)
            ->where('status', 'cancelled')->value('event_source'));
    }

    public function test_cleanup_unpaid_marks_a_late_payment_paid_instead_of_cancelling_it(): void
    {
        $order = Order::factory()->create();
        Payment::create([
            'order_id' => $order->order_id,
            'method' => 'stripe',
            'amount' => 22,
            'stripe_session_id' => 'cs_test_late',
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('expireSession')->once();
            $mock->shouldReceive('retrieveSession')
                ->andReturn(Session::constructFrom(['id' => 'cs_test_late', 'payment_status' => 'paid', 'status' => 'complete']));
        });

        $this->artisan('orders:cleanup-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_tables_auto_clear_returns_an_idle_occupied_table_to_available(): void
    {
        $table = RestaurantTable::factory()->create();
        $table->forceFill(['status' => TableStatus::Occupied])->save();

        Order::factory()->served()->create(['table_id' => $table->table_id])
            ->forceFill(['paid_at' => now()->subHours(3)])->save();

        $this->artisan('tables:auto-clear')->assertSuccessful();

        $this->assertSame(TableStatus::Available, $table->fresh()->status);
    }

    public function test_switch_reserved_moves_an_assigned_table_to_reserved_with_a_place_sign_alert(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 18:00:00'));
        Event::fake();

        $table = RestaurantTable::factory()->create();
        $reservation = Reservation::factory()->confirmed()->create([
            'booking_date' => '2026-09-21',
            'booking_time' => '18:20',
        ]);

        Visit::create([
            'table_id' => $table->table_id,
            'reservation_id' => $reservation->reservation_id,
            'guest_count' => 2,
            'opened_at' => null,
        ]);

        $this->artisan('reservations:switch-reserved')->assertSuccessful();

        $this->assertSame(TableStatus::Reserved, $table->fresh()->status);
        Event::assertDispatched(ReservationAlert::class,
            fn (ReservationAlert $event) => $event->kind === ReservationAlert::PLACE_SIGN);
    }

    public function test_expire_requests_expires_a_request_still_unreviewed_inside_the_expiry_window(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 18:00:00'));
        Mail::fake();

        $reservation = Reservation::factory()->create([
            'booking_date' => '2026-09-21',
            'booking_time' => '18:30',
        ]);

        $this->artisan('reservations:expire-requests')->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
    }

    public function test_send_reminders_queues_one_reminder_per_booking(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 20:00:00'));
        Mail::fake();

        $reservation = Reservation::factory()->confirmed()->create([
            'booking_date' => '2026-09-22',
            'booking_time' => '18:00',
        ]);

        $this->artisan('reservations:send-reminders')->assertSuccessful();
        $this->artisan('reservations:send-reminders')->assertSuccessful();

        Mail::assertQueued(ReservationReminderMail::class, 1);
        $this->assertNotNull($reservation->fresh()->reminder_sent_at);
    }

    public function test_suggest_no_shows_alerts_once_and_leaves_the_status_to_staff(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 18:30:00'));
        Event::fake();

        $reservation = Reservation::factory()->confirmed()->create([
            'booking_date' => '2026-09-21',
            'booking_time' => '18:00',
        ]);

        $this->artisan('reservations:suggest-no-shows')->assertSuccessful();
        $this->artisan('reservations:suggest-no-shows')->assertSuccessful();

        Event::assertDispatchedTimes(ReservationAlert::class, 1);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
    }
}
