<?php

namespace Tests\Feature\Admin;

use App\Events\SettingSwitched;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    private Staff $admin;

    private Staff $waitstaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, SettingSeeder::class]);

        $adminRole = Role::where('role_name', 'admin')->first();
        $waitstaffRole = Role::where('role_name', 'waitstaff')->first();

        $this->admin = Staff::factory()->create([
            'role_id' => $adminRole->role_id,
            'full_name' => 'Admin Settings Manager',
            'is_active' => true,
        ]);

        $this->waitstaff = Staff::factory()->create([
            'role_id' => $waitstaffRole->role_id,
            'full_name' => 'Waitstaff Member',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $response = $this->get(route('admin.settings.index'));

        $response->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_cannot_access_settings(): void
    {
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->get(route('admin.settings.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Venue Settings');
        $response->assertSee('Coolaroo Restaurant & Bistro');
        $response->assertSee('QR Ordering');
        $response->assertSee('Online Reservations');
        $response->assertSee('AI Assistant');
        $response->assertSee('data-testid="admin-settings-page"', false);
    }

    public function test_admin_can_update_venue_settings_and_it_is_audited(): void
    {
        $payload = [
            'venue_name' => 'Coolaroo Sports Bar & Grill',
            'venue_address' => '789 Pascoe Vale Road, Coolaroo VIC 3048',
            'venue_phone' => '(03) 9302 9999',
            'venue_email' => 'contact@coolaroogrill.com.au',
            'social_facebook' => 'https://facebook.com/coolaroogrill',
            'social_instagram' => 'https://instagram.com/coolaroogrill',
            'social_x' => '',
            'social_tiktok' => '',
            'social_whatsapp' => '',
            'opening_time' => '10:30',
            'closing_time' => '23:30',
            'closed_weekdays' => [1, 2], // Mon & Tue closed
            'reservation_max_days_ahead' => 90,
            'reservation_min_lead_hours' => 3,
            'reservation_max_party_online' => 12,
            'reservation_duration_1_2' => 75,
            'reservation_duration_3_6' => 105,
            'reservation_duration_7_plus' => 135,
            'reservation_request_expiry_minutes' => 45,
            'reservation_reminder_hours' => 48,
            'late_cancellation_hours' => 3,
            'holder_unlock_before_minutes' => 20,
            'reservation_grace_minutes' => 20,
            'reserved_switch_before_minutes' => 40,
            'unassigned_admin_alert_minutes' => 20,
            'qr_stock_buffer_multiplier' => 6,
            'table_idle_autoclear_minutes' => 50,
            'avg_ticket_minutes_kitchen' => 10,
            'avg_ticket_minutes_bar' => 4,
            'call_waiter_cooldown_seconds' => 90,
            'staff_session_timeout_minutes' => 45,
            'regular_badge_visits' => 4,
            'no_show_expiry_months' => 6,
            'public_rating_min_count' => 15,
            'qr_ordering_enabled' => '1',
            'reservations_online_enabled' => '1',
            'ai_enabled' => '1',
        ];

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.settings.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Settings saved successfully.');

        // Verify database updates
        $this->assertDatabaseHas('setting', [
            'setting_key' => 'venue_name',
            'setting_value' => 'Coolaroo Sports Bar & Grill',
            'updated_by_staff_id' => $this->admin->staff_id,
        ]);

        $this->assertDatabaseHas('setting', [
            'setting_key' => 'closed_weekdays',
            'setting_value' => '1,2',
        ]);

        $this->assertDatabaseHas('setting', [
            'setting_key' => 'reservation_max_days_ahead',
            'setting_value' => '90',
        ]);

        // Audit log verified
        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'setting_update',
            'entity_name' => 'setting',
            'entity_id' => 'venue_name',
        ]);

        // Cache was invalidated
        /** @var SettingService $service */
        $service = app(SettingService::class);
        $this->assertEquals('Coolaroo Sports Bar & Grill', $service->get('venue_name'));
        $this->assertEquals(90, $service->getInt('reservation_max_days_ahead'));
    }

    public function test_admin_can_toggle_qr_ordering_switch_and_it_dispatches_event(): void
    {
        Event::fake([SettingSwitched::class]);

        $this->assertEquals('1', Setting::where('setting_key', 'qr_ordering_enabled')->value('setting_value'));

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.settings.toggle', 'qr_ordering_enabled'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'QR Ordering is now paused.');

        $this->assertEquals('0', Setting::where('setting_key', 'qr_ordering_enabled')->value('setting_value'));

        Event::assertDispatched(SettingSwitched::class, function (SettingSwitched $e) {
            return $e->key === 'qr_ordering_enabled' && $e->value === '0';
        });

        // Audit log verified
        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'setting_update',
            'entity_name' => 'setting',
            'entity_id' => 'qr_ordering_enabled',
        ]);
    }

    public function test_admin_can_toggle_online_reservations_switch_and_it_dispatches_event(): void
    {
        Event::fake([SettingSwitched::class]);

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.settings.toggle', 'reservations_online_enabled'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Online Reservations is now paused.');

        $this->assertEquals('0', Setting::where('setting_key', 'reservations_online_enabled')->value('setting_value'));

        Event::assertDispatched(SettingSwitched::class, function (SettingSwitched $e) {
            return $e->key === 'reservations_online_enabled' && $e->value === '0';
        });
    }

    public function test_admin_can_toggle_ai_assistant_switch_and_it_dispatches_event(): void
    {
        Event::fake([SettingSwitched::class]);

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.settings.toggle', 'ai_enabled'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'AI Assistant is now paused.');

        $this->assertEquals('0', Setting::where('setting_key', 'ai_enabled')->value('setting_value'));

        Event::assertDispatched(SettingSwitched::class, function (SettingSwitched $e) {
            return $e->key === 'ai_enabled' && $e->value === '0';
        });
    }

    public function test_validation_rejects_invalid_inputs(): void
    {
        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.settings.update'), [
                'venue_name' => '',
                'venue_email' => 'not-an-email',
                'opening_time' => '99:99',
                'reservation_max_days_ahead' => 0,
            ]);

        $response->assertSessionHasErrors([
            'venue_name',
            'venue_email',
            'opening_time',
            'reservation_max_days_ahead',
        ]);
    }
}
