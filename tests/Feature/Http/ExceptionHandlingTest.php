<?php

namespace Tests\Feature\Http;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/login', fn () => 'customer login page')->name('customer.login');
        Route::get('/staff/login', fn () => 'staff login page')->name('staff.login');

        Route::middleware('auth:customer')->get('/__test/customer-only', fn () => 'ok');
        Route::middleware('auth:staff')->get('/staff/__test/staff-only', fn () => 'ok');
        Route::middleware('auth:staff')->get('/admin/__test/admin-only', fn () => 'ok');

        Route::get('/__test/invalid-transition', function () {
            throw new InvalidTransitionException(OrderStatus::Served, OrderStatus::Preparing);
        });

        Route::post('/__test/validate', function (Request $request) {
            $request->validate(['name' => 'required']);

            return 'ok';
        });

        Route::get('/__test/forbidden', function () {
            throw new AuthorizationException('nope');
        });

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_guest_hitting_a_customer_route_redirects_to_customer_login(): void
    {
        $this->get('/__test/customer-only')->assertRedirect('/login');
    }

    public function test_guest_hitting_a_staff_route_redirects_to_staff_login(): void
    {
        $this->get('/staff/__test/staff-only')->assertRedirect('/staff/login');
    }

    public function test_guest_hitting_an_admin_route_redirects_to_staff_login(): void
    {
        $this->get('/admin/__test/admin-only')->assertRedirect('/staff/login');
    }

    public function test_invalid_transition_redirects_back_with_a_flash_message_for_html(): void
    {
        $this->from('/previous-page')
            ->get('/__test/invalid-transition')
            ->assertRedirect('/previous-page')
            ->assertSessionHas('error', 'Cannot change OrderStatus from served to preparing.');
    }

    public function test_invalid_transition_returns_409_json_for_json_requests(): void
    {
        $this->getJson('/__test/invalid-transition')
            ->assertStatus(409)
            ->assertJson(['message' => 'Cannot change OrderStatus from served to preparing.']);
    }

    public function test_validation_failure_returns_422(): void
    {
        $this->postJson('/__test/validate', [])->assertStatus(422);
    }

    public function test_authorization_failure_returns_403(): void
    {
        $this->get('/__test/forbidden')->assertForbidden();
    }
}
