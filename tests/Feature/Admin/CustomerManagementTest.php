<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Services\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private Staff $admin;

    private Staff $waitstaff;

    private SlotCapacity $slot;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Australia/Melbourne'));

        $adminRole = Role::firstOrCreate(['role_name' => 'admin'], [
            'description' => 'Admin',
            'landing_screen' => 'admin.dashboard',
        ]);
        $waitstaffRole = Role::firstOrCreate(['role_name' => 'waitstaff'], [
            'description' => 'Waitstaff',
            'landing_screen' => 'staff.floor.index',
        ]);

        $this->admin = Staff::factory()->create(['role_id' => $adminRole->role_id]);
        $this->waitstaff = Staff::factory()->create(['role_id' => $waitstaffRole->role_id]);

        $this->slot = SlotCapacity::factory()->create([
            'slot_time' => '18:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_view_customer_list(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Bruce Wayne',
            'email' => 'bruce@wayne.com',
            'phone' => '0411223344',
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('admin.customers.index'));

        $response->assertOk()
            ->assertSee('Bruce Wayne')
            ->assertSee('bruce@wayne.com')
            ->assertSee('0411223344');
    }

    public function test_admin_can_search_customers(): void
    {
        Customer::factory()->create([
            'full_name' => 'Clark Kent',
            'email' => 'clark@dailyplanet.com',
        ]);

        Customer::factory()->create([
            'full_name' => 'Diana Prince',
            'email' => 'diana@themyscira.gov',
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('admin.customers.index', ['search' => 'Clark']));

        $response->assertOk()
            ->assertSee('Clark Kent')
            ->assertDontSee('Diana Prince');
    }

    public function test_admin_can_view_customer_detail_and_trust_profile(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Barry Allen',
            'email' => 'barry@centralcity.com',
        ]);

        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-20',
            'status' => ReservationStatus::Completed,
            'party_size' => 2,
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('admin.customers.show', $customer));

        $response->assertOk()
            ->assertSee('Barry Allen')
            ->assertSee('barry@centralcity.com')
            ->assertSee('Trust profile');
    }

    public function test_customer_detail_shows_order_history(): void
    {
        $customer = Customer::factory()->create();

        $order = Order::factory()->paid()->create([
            'customer_id' => $customer->customer_id,
            'order_number' => 'ORD-TEST-9001',
        ]);

        $otherOrder = Order::factory()->create(['order_number' => 'ORD-OTHER-9002']);

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('admin.customers.show', $customer));

        $response->assertOk()
            ->assertSee('Order history')
            ->assertSee('ORD-TEST-9001')
            ->assertSee('data-testid="admin-customer-order-row-'.$order->order_id.'"', false)
            ->assertDontSee('ORD-OTHER-9002');
    }

    public function test_customer_detail_reports_no_orders_when_there_are_none(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('No orders on record for this customer.');
    }

    public function test_admin_can_clear_no_show_with_required_reason(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Peter Parker',
            'email' => 'peter@bugle.com',
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-20',
            'booking_time' => '18:00',
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subDays(2),
            'no_show_by_staff_id' => $this->waitstaff->staff_id,
        ]);

        $trustService = app(TrustService::class);
        $this->assertSame(TrustService::BADGE_FLAGGED, $trustService->badge($customer));

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('admin.customers.no-shows.clear', [
                'customer' => $customer,
                'reservation' => $reservation,
            ]), [
                'reason' => 'Customer called to apologize, stuck in subway blackout',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'no-show-cleared');

        $reservation->refresh();
        $this->assertNotNull($reservation->no_show_cleared_at);
        $this->assertSame($this->admin->staff_id, $reservation->no_show_cleared_by_staff_id);
        $this->assertSame('Customer called to apologize, stuck in subway blackout', $reservation->no_show_clear_reason);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'no_show_cleared',
            'entity_name' => 'reservation',
            'entity_id' => $reservation->reservation_id,
        ]);

        $this->assertSame(TrustService::BADGE_NEW, $trustService->badge($customer));
    }

    public function test_clear_no_show_requires_reason(): void
    {
        $customer = Customer::factory()->create();

        $reservation = Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('admin.customers.no-shows.clear', [
                'customer' => $customer,
                'reservation' => $reservation,
            ]), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors('reason');

        $reservation->refresh();
        $this->assertNull($reservation->no_show_cleared_at);
    }

    public function test_cannot_clear_already_cleared_no_show(): void
    {
        $customer = Customer::factory()->create();

        $reservation = Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subDays(1),
            'no_show_cleared_at' => now()->subHours(5),
            'no_show_cleared_by_staff_id' => $this->admin->staff_id,
            'no_show_clear_reason' => 'Already cleared earlier',
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('admin.customers.no-shows.clear', [
                'customer' => $customer,
                'reservation' => $reservation,
            ]), [
                'reason' => 'Attempting second clear',
            ]);

        $response->assertSessionHasErrors('reservation');
    }

    public function test_waitstaff_cannot_access_customer_list_or_clear_no_show(): void
    {
        $customer = Customer::factory()->create();
        $reservation = Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subDays(1),
        ]);

        $indexRes = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('admin.customers.index'));

        $indexRes->assertForbidden();

        $clearRes = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('admin.customers.no-shows.clear', [
                'customer' => $customer,
                'reservation' => $reservation,
            ]), [
                'reason' => 'Waitstaff trying to clear',
            ]);

        $clearRes->assertForbidden();
    }
}
