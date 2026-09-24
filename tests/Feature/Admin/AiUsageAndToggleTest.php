<?php

namespace Tests\Feature\Admin;

use App\Events\SettingSwitched;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AiUsageAndToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::firstOrCreate(
            ['role_name' => 'admin'],
            ['display_name' => 'Administrator', 'landing_screen' => '/admin', 'is_active' => true]
        );

        return Staff::factory()->create([
            'role_id' => $role->role_id,
            'is_active' => true,
        ]);
    }

    private function waitstaff(): Staff
    {
        $role = Role::firstOrCreate(
            ['role_name' => 'waitstaff'],
            ['display_name' => 'Waitstaff', 'landing_screen' => '/staff/floor', 'is_active' => true]
        );

        return Staff::factory()->create([
            'role_id' => $role->role_id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get(route('admin.reports.show', ['type' => 'ai']))
            ->assertRedirect(route('staff.login'));

        $this->patch(route('admin.reports.ai.toggle'))
            ->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_cannot_access_ai_report_or_toggle(): void
    {
        $waitstaff = $this->waitstaff();

        $this->actingAs($waitstaff, 'staff')
            ->get(route('admin.reports.show', ['type' => 'ai']))
            ->assertForbidden();

        $this->actingAs($waitstaff, 'staff')
            ->patch(route('admin.reports.ai.toggle'))
            ->assertForbidden();
    }

    public function test_admin_can_view_ai_usage_report_fr45(): void
    {
        $admin = $this->admin();
        $customer = Customer::factory()->create([
            'full_name' => 'Gordon Ramsay',
        ]);

        AuditLog::create([
            'customer_id' => $customer->customer_id,
            'action_type' => 'ai_request',
            'entity_name' => 'ai_request',
            'details' => [
                'feature' => 'chat',
                'tokens_in' => 120,
                'tokens_out' => 45,
                'status' => 'success',
            ],
            'ip_address' => '127.0.0.1',
            'logged_at' => now()->subHours(2),
        ]);

        AuditLog::create([
            'customer_id' => null,
            'action_type' => 'ai_request',
            'entity_name' => 'ai_request',
            'details' => [
                'feature' => 'meal_builder',
                'tokens_in' => 250,
                'tokens_out' => 85,
                'status' => 'busy',
            ],
            'ip_address' => '192.168.1.50',
            'logged_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'ai']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-ai"', false)
            ->assertSee('data-testid="admin-ai-switch-card"', false)
            ->assertSee('data-testid="admin-report-ai-features"', false)
            ->assertSee('data-testid="admin-report-ai-requests"', false)
            ->assertSee('Gordon Ramsay')
            ->assertSee('500 total tokens billed')
            ->assertSee('50%');
    }

    public function test_admin_can_toggle_ai_on_and_off_fr98(): void
    {
        Event::fake([SettingSwitched::class]);

        $admin = $this->admin();
        Setting::create([
            'setting_key' => 'ai_enabled',
            'setting_value' => '1',
            'value_type' => 'bool',
        ]);

        $settingService = app(SettingService::class);
        $settingService->clearCache();
        $this->assertTrue($settingService->getBool('ai_enabled'));

        $response = $this->actingAs($admin, 'staff')
            ->patch(route('admin.reports.ai.toggle'));

        $response->assertRedirect();
        $this->assertFalse($settingService->getBool('ai_enabled'));

        Event::assertDispatched(SettingSwitched::class, function (SettingSwitched $e) {
            return $e->key === 'ai_enabled' && $e->value === '0';
        });

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $admin->staff_id,
            'action_type' => 'setting_update',
            'entity_name' => 'setting',
        ]);

        $this->actingAs($admin, 'staff')
            ->patch(route('admin.reports.ai.toggle'))
            ->assertRedirect();

        $this->assertTrue($settingService->getBool('ai_enabled'));

        Event::assertDispatched(SettingSwitched::class, function (SettingSwitched $e) {
            return $e->key === 'ai_enabled' && $e->value === '1';
        });
    }

    public function test_admin_can_export_ai_usage_as_csv_and_pdf_fr88(): void
    {
        $admin = $this->admin();

        AuditLog::create([
            'customer_id' => null,
            'action_type' => 'ai_request',
            'entity_name' => 'ai_request',
            'details' => [
                'feature' => 'chat',
                'tokens_in' => 80,
                'tokens_out' => 30,
                'status' => 'success',
            ],
            'ip_address' => '10.0.0.1',
            'logged_at' => now(),
        ]);

        $csvResponse = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.export', ['type' => 'ai', 'format' => 'csv']))
            ->assertOk();

        $this->assertEquals('text/csv; charset=UTF-8', $csvResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('AI Usage Report', $csvResponse->getContent());
        $this->assertStringContainsString('Total AI Requests', $csvResponse->getContent());

        $pdfResponse = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.export', ['type' => 'ai', 'format' => 'pdf']))
            ->assertOk();

        $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }
}
