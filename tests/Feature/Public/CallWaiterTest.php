<?php

namespace Tests\Feature\Public;

use App\Events\WaiterCalled;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallWaiterTest extends TestCase
{
    use RefreshDatabase;

    private function setting(int $seconds): void
    {
        Setting::create([
            'setting_key' => 'call_waiter_cooldown_seconds',
            'value_type' => 'int',
            'setting_value' => (string) $seconds,
        ]);
    }

    public function test_a_visitor_at_the_table_calls_a_waiter(): void
    {
        Event::fake([WaiterCalled::class]);
        $this->setting(120);
        $table = RestaurantTable::factory()->create();

        $this->withSession(['qr.table_id' => $table->table_id])
            ->post(route('table.call-waiter', $table))
            ->assertRedirect()
            ->assertSessionHas('status', 'waiter-called');

        Event::assertDispatched(WaiterCalled::class);
    }

    public function test_a_second_call_within_the_cooldown_is_refused_politely(): void
    {
        Event::fake([WaiterCalled::class]);
        $this->setting(120);
        $table = RestaurantTable::factory()->create();

        $this->withSession(['qr.table_id' => $table->table_id])->post(route('table.call-waiter', $table));

        $this->withSession(['qr.table_id' => $table->table_id])
            ->post(route('table.call-waiter', $table))
            ->assertSessionHas('status', 'waiter-cooldown');

        Event::assertDispatchedTimes(WaiterCalled::class, 1);
    }

    public function test_another_table_is_not_blocked_by_the_first_ones_cooldown(): void
    {
        $this->setting(120);
        $first = RestaurantTable::factory()->create();
        $second = RestaurantTable::factory()->create();

        $this->withSession(['qr.table_id' => $first->table_id])->post(route('table.call-waiter', $first));

        $this->withSession(['qr.table_id' => $second->table_id])
            ->post(route('table.call-waiter', $second))
            ->assertSessionHas('status', 'waiter-called');
    }

    public function test_an_inactive_table_shows_the_unavailable_page(): void
    {
        $table = RestaurantTable::factory()->create(['is_active' => false]);

        $this->withSession(['qr.table_id' => $table->table_id])
            ->post(route('table.call-waiter', $table))
            ->assertOk()
            ->assertViewIs('public.table-unavailable');
    }

    public function test_someone_who_never_scanned_the_table_cannot_call_its_waiter(): void
    {
        $table = RestaurantTable::factory()->create();

        $this->post(route('table.call-waiter', $table))->assertForbidden();
    }
}
