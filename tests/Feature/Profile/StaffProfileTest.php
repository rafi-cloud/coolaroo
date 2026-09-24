<?php

namespace Tests\Feature\Profile;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_staff_member_can_update_their_name_and_phone(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Old Name']);

        $response = $this->actingAs($staff, 'staff')->patch('/staff/profile', [
            'full_name' => 'New Name',
            'phone' => '0498765432',
        ]);

        $response->assertRedirect();
        $this->assertSame('New Name', $staff->fresh()->full_name);
    }

    public function test_a_staff_member_cannot_change_their_own_role(): void
    {
        $staff = Staff::factory()->create();
        $originalRoleId = $staff->role_id;

        $this->actingAs($staff, 'staff')->patch('/staff/profile', [
            'full_name' => $staff->full_name,
            'phone' => $staff->phone,
            'role_id' => $originalRoleId + 1,
        ]);

        $this->assertSame($originalRoleId, $staff->fresh()->role_id);
    }

    public function test_changing_the_password_requires_the_current_password(): void
    {
        $staff = Staff::factory()->create(['password_hash' => 'old-password']);

        $response = $this->from('/staff/profile')->actingAs($staff, 'staff')->patch('/staff/profile', [
            'full_name' => $staff->full_name,
            'phone' => $staff->phone,
            'current_password' => 'wrong-password',
            'password' => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ]);

        $response->assertRedirect('/staff/profile');
        $response->assertSessionHasErrors('current_password');
    }

    public function test_a_guest_cannot_reach_the_staff_profile_page(): void
    {
        $this->get('/staff/profile')->assertRedirect('/staff/login');
    }
}
