<?php

namespace Tests\Feature\Middleware;

use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureStaffSessionIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth:staff', 'staff.session'])->get('/__test/staff-area', fn () => 'ok');
    }

    public function test_a_fresh_session_is_allowed_through_and_records_activity(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff, 'staff')
            ->get('/__test/staff-area')
            ->assertOk();

        $this->assertNotNull(session('staff_last_activity'));
    }

    public function test_a_session_within_the_timeout_stays_logged_in(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff, 'staff');
        $this->withSession(['staff_last_activity' => now()->subMinutes(10)])
            ->get('/__test/staff-area')
            ->assertOk();

        $this->assertAuthenticatedAs($staff, 'staff');
    }

    public function test_a_session_past_the_timeout_is_logged_out(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff, 'staff');
        $response = $this->withSession(['staff_last_activity' => now()->subMinutes(31)])
            ->get('/__test/staff-area');

        $response->assertRedirect('/staff/login');
        $this->assertGuest('staff');
    }

    public function test_the_timeout_length_comes_from_the_settings_table(): void
    {
        Setting::updateOrCreate(
            ['setting_key' => 'staff_session_timeout_minutes'],
            ['setting_value' => '5', 'value_type' => 'int']
        );

        $staff = Staff::factory()->create();

        $this->actingAs($staff, 'staff');
        $response = $this->withSession(['staff_last_activity' => now()->subMinutes(6)])
            ->get('/__test/staff-area');

        $response->assertRedirect('/staff/login');
        $this->assertGuest('staff');
    }
}
