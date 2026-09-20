<?php

namespace Tests\Feature\Public;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TableScanTest extends TestCase
{
    use RefreshDatabase;

    private function scanUrl(RestaurantTable $table, ?string $token = null): string
    {
        return URL::signedRoute('table.scan', [
            'table' => $table->table_id,
            'token' => $token ?? $table->qr_token,
        ]);
    }

    private function reservedTableFor(Customer $holder, string $bookingTime = '18:00'): array
    {
        $table = RestaurantTable::factory()->create();
        $table->forceFill(['status' => TableStatus::Reserved])->save();

        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $holder->customer_id,
            'booking_date' => now()->toDateString(),
            'booking_time' => $bookingTime,
        ]);

        $visit = $table->visits()->create(['reservation_id' => $reservation->reservation_id]);

        return [$table, $reservation, $visit];
    }

    public function test_a_logged_out_scan_lands_on_the_qr_login_page(): void
    {
        $table = RestaurantTable::factory()->create();

        $this->get($this->scanUrl($table))
            ->assertRedirect(route('customer.login'));

        $this->assertSame($table->table_id, session('qr.table_id'));

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('table '.$table->table_number, false);
    }

    public function test_a_token_that_no_longer_matches_the_table_is_rejected(): void
    {
        $table = RestaurantTable::factory()->create();
        $url = $this->scanUrl($table);

        $table->update(['qr_token' => str_repeat('z', 64)]);

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get($url)
            ->assertForbidden();
    }

    public function test_an_inactive_table_shows_the_unavailable_page(): void
    {
        $table = RestaurantTable::factory()->create(['is_active' => false]);

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get($this->scanUrl($table))
            ->assertOk()
            ->assertSee("This table isn't available", false);
    }

    public function test_an_available_table_binds_context_and_opens_ordering(): void
    {
        $table = RestaurantTable::factory()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get($this->scanUrl($table))
            ->assertRedirect(route('cart.index'));

        $this->assertSame($table->table_id, session('table_id'));
    }

    public function test_a_non_holder_sees_the_reserved_notice_with_a_masked_name(): void
    {
        $holder = Customer::factory()->create(['full_name' => 'Jane Doherty']);
        [$table] = $this->reservedTableFor($holder);

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get($this->scanUrl($table))
            ->assertOk()
            ->assertSee('Reserved for Jane D')
            ->assertDontSee('Doherty');

        $this->assertNull(session('table_id'));
    }

    public function test_the_holder_scanning_inside_the_window_is_seated(): void
    {
        $holder = Customer::factory()->create();
        [$table, $reservation, $visit] = $this->reservedTableFor($holder, now()->addMinutes(5)->format('H:i'));

        $this->actingAs($holder, 'customer')
            ->get($this->scanUrl($table))
            ->assertRedirect(route('cart.index'));

        $this->assertSame(ReservationStatus::Seated, $reservation->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
        $this->assertNotNull($visit->fresh()->opened_at);
        $this->assertNull($visit->fresh()->opened_by_staff_id);
    }

    public function test_scanning_another_table_asks_before_clearing_the_cart(): void
    {
        $first = RestaurantTable::factory()->create();
        $second = RestaurantTable::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->get($this->scanUrl($first));

        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory()]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);
        app(CartService::class)->add($item->item_id, $size->size_id, [], 1, null);

        $this->actingAs($customer, 'customer')
            ->get($this->scanUrl($second))
            ->assertOk()
            ->assertSee('Start a new order?');

        $this->actingAs($customer, 'customer')
            ->post(route('table.scan.switch', $second))
            ->assertRedirect(route('cart.index'));

        $this->assertSame($second->table_id, session('table_id'));
        $this->assertSame(0, app(CartService::class)->count());
    }
}
