<?php

namespace Tests\Feature\Public;

use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_accessible_skip_link_and_header_attributes(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-testid="site-skip-link"', false);
        $response->assertSee('Skip to main content');
        $response->assertSee('aria-label="Toggle navigation menu"', false);
        $response->assertSee('lang="en-AU"', false);
    }

    public function test_customer_order_bar_and_cart_inputs_have_accessible_labels(): void
    {
        $table = RestaurantTable::create([
            'table_number' => 12,
            'seat_capacity' => 4,
            'is_active' => true,
            'qr_token' => Str::random(64),
        ]);

        $response = $this->withSession(['table_id' => $table->table_id])
            ->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('data-testid="order-bar-cart"', false);
        $response->assertSee('aria-label="View shopping cart"', false);
        $response->assertSee('id="menu-search-input"', false);
        $response->assertSee('for="menu-search-input"', false);
    }

    public function test_admin_and_staff_layouts_include_accessible_toggle_and_logo_alt(): void
    {
        $adminRole = Role::create(['role_name' => 'admin', 'landing_screen' => 'admin.dashboard']);
        $admin = Staff::create([
            'role_id' => $adminRole->role_id,
            'full_name' => 'Accessibility Admin',
            'email' => 'admin@coolaroo.local',
            'password_hash' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'staff')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-nav-toggle"', false);
        $response->assertSee('aria-label="Toggle navigation menu"', false);
        $response->assertSee('alt="Coolaroo Restaurant Logo"', false);
    }

    public function test_auth_forms_include_descriptive_logo_alt_and_labels(): void
    {
        $loginResponse = $this->get(route('customer.login'));
        $loginResponse->assertOk();
        $loginResponse->assertSee('alt="Coolaroo Restaurant Logo"', false);
        $loginResponse->assertSee('for="remember"', false);

        $registerResponse = $this->get(route('register'));
        $registerResponse->assertOk();
        $registerResponse->assertSee('alt="Coolaroo Restaurant Logo"', false);
        $registerResponse->assertSee('for="full_name"', false);
        $registerResponse->assertSee('for="email"', false);
        $registerResponse->assertSee('for="password"', false);

        $staffLoginResponse = $this->get(route('staff.login'));
        $staffLoginResponse->assertOk();
        $staffLoginResponse->assertSee('alt="Coolaroo Restaurant Logo"', false);
        $staffLoginResponse->assertSee('for="email"', false);
        $staffLoginResponse->assertSee('for="password"', false);
    }

    public function test_stylesheets_define_focus_contrast_and_accessibility_rules(): void
    {
        $styleCss = file_get_contents(public_path('css/style.css'));
        $dashboardCss = file_get_contents(public_path('css/dashboard.css'));

        $this->assertStringContainsString(':focus-visible{outline:2.5px solid var(--orange-dark)', $styleCss);
        $this->assertStringContainsString(':focus-visible{outline:2.5px solid var(--orange-dark)', $dashboardCss);

        $this->assertStringContainsString('.skip-link', $styleCss);
        $this->assertStringContainsString('.skip-link:focus', $styleCss);

        $this->assertStringContainsString('min-height:44px', $styleCss);
        $this->assertStringContainsString('min-height:44px', $dashboardCss);

        $this->assertStringContainsString('prefers-reduced-motion:reduce', $styleCss);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $dashboardCss);
    }
}
