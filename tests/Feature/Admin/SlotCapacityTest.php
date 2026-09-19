<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_a_time_slot(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->post('/admin/slots', [
            'slot_time' => '18:00',
            'max_covers' => 40,
        ]);

        $response->assertRedirect('/admin/slots');
        $this->assertDatabaseHas('slot_capacity', ['slot_time' => '18:00', 'max_covers' => 40]);
    }

    public function test_slot_times_must_be_unique(): void
    {
        $admin = $this->admin();
        SlotCapacity::factory()->create(['slot_time' => '18:00']);

        $response = $this->actingAs($admin, 'staff')->post('/admin/slots', [
            'slot_time' => '18:00',
            'max_covers' => 40,
        ]);

        $response->assertSessionHasErrors('slot_time');
    }

    public function test_a_non_admin_cannot_reach_the_slot_list(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($waiter, 'staff')->get('/admin/slots')->assertForbidden();
    }

    public function test_a_slot_with_reservations_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $slot = SlotCapacity::factory()->create();
        Reservation::create([
            'slot_id' => $slot->slot_id,
            'reference_code' => 'ABC123',
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => $slot->slot_time,
            'party_size' => 2,
        ]);

        $response = $this->actingAs($admin, 'staff')->delete("/admin/slots/{$slot->slot_id}");

        $response->assertSessionHasErrors('slot');
        $this->assertDatabaseHas('slot_capacity', ['slot_id' => $slot->slot_id]);
    }

    public function test_an_unused_slot_can_be_deleted(): void
    {
        $admin = $this->admin();
        $slot = SlotCapacity::factory()->create();

        $this->actingAs($admin, 'staff')->delete("/admin/slots/{$slot->slot_id}");

        $this->assertDatabaseMissing('slot_capacity', ['slot_id' => $slot->slot_id]);
    }
}
