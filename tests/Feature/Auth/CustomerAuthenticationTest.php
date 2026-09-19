<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_log_in_with_the_right_credentials(): void
    {
        $customer = Customer::factory()->create(['password_hash' => 'password123']);

        $response = $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    public function test_login_fails_with_the_wrong_password(): void
    {
        $customer = Customer::factory()->create(['password_hash' => 'password123']);

        $response = $this->from('/login')->post('/login', [
            'email' => $customer->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_a_logged_in_customer_can_log_out(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest('customer');
    }

    public function test_a_guest_cannot_log_out(): void
    {
        $this->post('/logout')->assertRedirect('/login');
    }
}
