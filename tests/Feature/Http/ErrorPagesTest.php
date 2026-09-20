<?php

namespace Tests\Feature\Http;

use App\Models\Customer;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T211: Error pages: 403, 404, 419, 500 and paused states (NFR11).
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_error_page_renders_custom_view_with_menu_and_home_actions(): void
    {
        $response = $this->get('/non-existent-page-url-xyz');

        $response->assertNotFound()
            ->assertSee('Page Not Found (404)')
            ->assertSee('data-testid="error-404-home"', false)
            ->assertSee('data-testid="error-404-menu"', false)
            ->assertSee('data-testid="error-404-reserve"', false);
    }

    public function test_403_error_page_renders_custom_view(): void
    {
        // Visiting customer profile as an unauthenticated visitor aborts or redirects
        // Let's hit a route that directly aborts with 403 or trigger a 403 via tampered QR scan
        $table = RestaurantTable::factory()->create();
        $tamperedUrl = route('table.scan', ['table' => $table->table_id, 'token' => 'invalid-token-signature']) . '&signature=bad';

        $response = $this->get($tamperedUrl);

        $response->assertForbidden()
            ->assertSee('Access Forbidden (403)')
            ->assertSee('data-testid="error-403-home"', false)
            ->assertSee('data-testid="error-403-menu"', false);
    }

    public function test_419_error_view_renders_session_expired_guidance(): void
    {
        $rendered = view('errors.419')->render();

        $this->assertStringContainsString('Session Expired (419)', $rendered);
        $this->assertStringContainsString('data-testid="error-419-refresh"', $rendered);
        $this->assertStringContainsString('data-testid="error-419-home"', $rendered);
        $this->assertStringContainsString('data-testid="error-419-login"', $rendered);
    }

    public function test_500_error_view_renders_server_error_guidance(): void
    {
        $rendered = view('errors.500')->render();

        $this->assertStringContainsString('Something Went Wrong (500)', $rendered);
        $this->assertStringContainsString('data-testid="error-500-home"', $rendered);
        $this->assertStringContainsString('data-testid="error-500-menu"', $rendered);
    }

    public function test_503_error_view_renders_maintenance_guidance(): void
    {
        $rendered = view('errors.503')->render();

        $this->assertStringContainsString('Temporarily Unavailable (503)', $rendered);
        $this->assertStringContainsString('data-testid="error-503-home"', $rendered);
    }

    public function test_paused_qr_ordering_state_in_cart(): void
    {
        Setting::updateOrCreate(
            ['setting_key' => 'qr_ordering_enabled'],
            ['setting_value' => '0', 'value_type' => 'bool']
        );

        $table = RestaurantTable::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->withSession(['table_id' => $table->table_id])
            ->get(route('cart.index'));

        // BR58 & EnsureQrOrderingEnabled: blocks customer cart / checkout when paused
        $response->assertRedirect()
            ->assertSessionHas('error', 'Online ordering is paused right now — please order with a staff member.');

        // Test rendering cart view directly with qrOrderingEnabled = false
        $rendered = view('customer.cart', [
            'lines' => collect(),
            'total' => 0.0,
            'qrOrderingEnabled' => false,
        ])->render();

        $this->assertStringContainsString('data-testid="paused-state-notice"', $rendered);
        $this->assertStringContainsString('Online Ordering Paused', $rendered);
        $this->assertStringContainsString('Please speak with our waitstaff to order', $rendered);
    }

    public function test_reusable_paused_notice_component_renders_reservations_phone(): void
    {
        Setting::updateOrCreate(
            ['setting_key' => 'venue_phone'],
            ['setting_value' => '03 9300 1234', 'value_type' => 'string']
        );

        $rendered = (string) $this->blade('<x-site.paused-notice type="reservations" />');

        $this->assertStringContainsString('Online Bookings Paused', $rendered);
        $this->assertStringContainsString('03 9300 1234', $rendered);
        $this->assertStringContainsString('tel:0393001234', $rendered);
        $this->assertStringContainsString('data-testid="paused-state-notice"', $rendered);
    }
}
