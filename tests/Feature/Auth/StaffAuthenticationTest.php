<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaffAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_is_redirected_to_their_role_landing_screen_when_it_exists(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id, 'password_hash' => 'password123']);

        $response = $this->post('/staff/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/staff/floor');
        $this->assertAuthenticatedAs($staff->fresh(), 'staff');
        $this->assertNotNull($staff->fresh()->last_login_at);
    }

    public function test_every_seeded_role_lands_on_a_registered_parameterless_route(): void
    {
        $this->seed(RoleSeeder::class);

        foreach (Role::all() as $role) {
            $this->assertTrue(
                Route::has($role->landing_screen),
                "Role [{$role->role_name}] has landing_screen [{$role->landing_screen}], which is not a registered route name."
            );

            $this->assertNotEmpty(route($role->landing_screen));
        }
    }

    public function test_a_signed_in_staff_member_revisiting_the_login_page_is_returned_to_their_own_screen(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($staff, 'staff')
            ->get('/staff/login')
            ->assertRedirect(route('staff.floor.index'));
    }

    public function test_login_falls_back_to_the_homepage_when_the_landing_screen_route_does_not_exist_yet(): void
    {
        $role = Role::factory()->create(['landing_screen' => 'nonexistent.route']);
        $staff = Staff::factory()->create(['role_id' => $role->role_id, 'password_hash' => 'password123']);

        $response = $this->post('/staff/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
    }

    public function test_admin_is_redirected_to_admin_dashboard(): void
    {
        $role = Role::factory()->admin()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id, 'password_hash' => 'password123']);

        $response = $this->post('/staff/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin');
    }

    public function test_login_fails_with_the_wrong_password(): void
    {
        $staff = Staff::factory()->create(['password_hash' => 'password123']);

        $response = $this->from('/staff/login')->post('/staff/login', [
            'email' => $staff->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/staff/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('staff');
    }

    public function test_a_deactivated_staff_member_cannot_log_in(): void
    {
        $staff = Staff::factory()->create(['password_hash' => 'password123', 'is_active' => false]);

        $response = $this->from('/staff/login')->post('/staff/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('staff');
    }

    public function test_a_logged_in_staff_member_can_log_out(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff, 'staff')->post('/staff/logout');

        $response->assertRedirect('/staff/login');
        $this->assertGuest('staff');
    }

    public function test_a_guest_cannot_log_out(): void
    {
        $this->post('/staff/logout')->assertRedirect('/staff/login');
    }
}
