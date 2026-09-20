<?php

namespace Tests\Feature\Mail;

use App\Mail\ReservationCancelledMail;
use App\Mail\ReservationConfirmedMail;
use App\Mail\ReservationDeclinedMail;
use App\Mail\ReservationExpiredMail;
use App\Mail\ReservationReceivedMail;
use App\Mail\ReservationReminderMail;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Services\ReservationService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationEmailsTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $service;

    private Customer $customer;

    private Staff $staff;

    private SlotCapacity $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);

        // Fixed test time: Tuesday 22 Sep 2026 12:00:00 (Tuesday is an open day)
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Australia/Melbourne'));

        $this->service = app(ReservationService::class);

        $this->slot = SlotCapacity::factory()->create([
            'slot_time' => '18:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'full_name' => 'John Wick',
            'email' => 'john.wick@example.com',
            'phone' => '0412345678',
            'email_verified_at' => now(),
        ]);

        $waitstaffRole = Role::firstOrCreate(['role_name' => 'waitstaff'], [
            'description' => 'Waitstaff',
            'landing_screen' => 'staff.floor.index',
        ]);

        $this->staff = Staff::factory()->create([
            'role_id' => $waitstaffRole->role_id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reservation_received_mail_renders_content_and_queues_on_request(): void
    {
        Mail::fake();

        $reservation = $this->service->request($this->customer, [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 4,
            'special_requests' => 'Window booth please',
        ]);

        Mail::assertQueued(ReservationReceivedMail::class, function (ReservationReceivedMail $mail) use ($reservation) {
            $this->assertSame($reservation->reservation_id, $mail->reservation->reservation_id);
            $this->assertStringContainsString($reservation->reference_code, $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString($reservation->reference_code, $html);
            $this->assertStringContainsString('Window booth please', $html);
            $this->assertStringContainsString('awaiting staff review', $html);
            $this->assertStringContainsString('Coolaroo', $html);

            return $mail->hasTo('john.wick@example.com');
        });
    }

    public function test_reservation_confirmed_mail_renders_content_and_queues_on_approve(): void
    {
        Mail::fake();

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $this->service->approve($reservation, $this->staff);

        Mail::assertQueued(ReservationConfirmedMail::class, function (ReservationConfirmedMail $mail) use ($reservation) {
            $this->assertStringContainsString('Reservation Confirmed!', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString($reservation->reference_code, $html);
            $this->assertStringContainsString('15-Minute Grace Period', $html);
            $this->assertStringContainsString('Your reservation is confirmed!', $html);

            return $mail->hasTo('john.wick@example.com');
        });
    }

    public function test_reservation_declined_mail_renders_content_and_reason_and_queues_on_decline(): void
    {
        Mail::fake();

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 8,
        ]);

        $this->service->decline($reservation, $this->staff, 'Fully booked for large parties');

        Mail::assertQueued(ReservationDeclinedMail::class, function (ReservationDeclinedMail $mail) use ($reservation) {
            $this->assertStringContainsString('Reservation Request Update', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString($reservation->reference_code, $html);
            $this->assertStringContainsString('Fully booked for large parties', $html);

            return $mail->hasTo('john.wick@example.com');
        });
    }

    public function test_reservation_expired_mail_renders_content_and_queues_on_expire(): void
    {
        Mail::fake();

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '12:00',
            'party_size' => 2,
        ]);

        $this->service->expire($reservation);

        Mail::assertQueued(ReservationExpiredMail::class, function (ReservationExpiredMail $mail) use ($reservation) {
            $this->assertStringContainsString('Reservation Request Expired', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString($reservation->reference_code, $html);
            $this->assertStringContainsString('expired because the requested booking time has arrived', $html);

            return $mail->hasTo('john.wick@example.com');
        });
    }

    public function test_reservation_cancelled_mail_renders_content_and_queues_on_cancel(): void
    {
        Mail::fake();

        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $this->service->cancelByCustomer($reservation, $this->customer);

        Mail::assertQueued(ReservationCancelledMail::class, function (ReservationCancelledMail $mail) use ($reservation) {
            $this->assertStringContainsString('Reservation Cancelled', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString($reservation->reference_code, $html);

            return $mail->hasTo('john.wick@example.com');
        });
    }

    public function test_reservation_reminder_mail_renders_content_and_sets_timestamp(): void
    {
        Mail::fake();

        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-23',
            'booking_time' => '18:00',
            'party_size' => 4,
            'reminder_sent_at' => null,
        ]);

        $sent = $this->service->sendReminder($reservation);
        $this->assertTrue($sent);

        $reservation->refresh();
        $this->assertNotNull($reservation->reminder_sent_at);

        Mail::assertQueued(ReservationReminderMail::class, function (ReservationReminderMail $mail) use ($reservation) {
            $this->assertStringContainsString('Upcoming Reservation Reminder', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString($reservation->reference_code, $html);
            $this->assertStringContainsString('Reminder: Your table reservation is coming up!', $html);

            return $mail->hasTo('john.wick@example.com');
        });

        // Calling a second time should not duplicate
        $second = $this->service->sendReminder($reservation);
        $this->assertFalse($second);
    }
}
