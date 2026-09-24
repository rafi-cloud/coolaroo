<?php

namespace Tests\Feature\Public;

use App\Models\Customer;
use App\Models\Setting;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_homepage_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('public.home');
    }

    public function test_homepage_renders_header_with_navigation_and_hamburger_toggle(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="site-home"', false);
        $response->assertSee('data-testid="site-nav-toggle"', false);
        $response->assertSee('data-testid="site-nav-home"', false);
        $response->assertSee('data-testid="site-nav-menu"', false);
        $response->assertSee('data-testid="site-nav-reviews"', false);
        $response->assertSee('data-testid="site-nav-about"', false);
        $response->assertSee('data-testid="site-nav-sign-in"', false);
        $response->assertSee('data-testid="site-book-table"', false);

        $response->assertDontSee('data-testid="site-search"', false);
    }

    public function test_header_shows_account_link_for_authenticated_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="site-nav-account"', false);
        $response->assertDontSee('data-testid="site-nav-sign-in"', false);
    }

    public function test_homepage_renders_hero_action_tiles_and_about_section(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="home-hero"', false);
        $response->assertSee('TASTE SOMETHING NEW');
        $response->assertSee('data-testid="home-hero-menu"', false);

        $response->assertSee('data-testid="home-tiles"', false);
        $response->assertSee('data-testid="home-tile-menu"', false);
        $response->assertSee('data-testid="home-tile-table"', false);
        $response->assertSee('data-testid="home-tile-meal-builder"', false);

        $response->assertSee('data-testid="home-about"', false);
        $response->assertSee('Some words about us');
    }

    public function test_footer_renders_dynamic_venue_settings(): void
    {
        Setting::where('setting_key', 'venue_name')->update(['setting_value' => 'Coolaroo Test Venue']);
        Setting::where('setting_key', 'venue_phone')->update(['setting_value' => '(03) 9999 8888']);
        Setting::where('setting_key', 'venue_email')->update(['setting_value' => 'test@coolaroo.local']);

        app(SettingService::class)->clearCache();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Coolaroo Test Venue');
        $response->assertSee('(03) 9999 8888');
        $response->assertSee('test@coolaroo.local');
        $response->assertSee('data-testid="site-footer-hours"', false);
        $response->assertSee('data-testid="site-footer-subscribe-email"', false);
        $response->assertSee('data-testid="site-social-facebook"', false);
        $response->assertSee('data-testid="site-social-instagram"', false);
    }
}
