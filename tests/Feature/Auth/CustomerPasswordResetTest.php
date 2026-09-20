<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_reset_link_always_shows_the_same_generic_message(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();
        $message = 'If that email matches an account, a reset link is on its way.';

        $this->post('/forgot-password', ['email' => $customer->email])
            ->assertSessionHas('status', $message);

        $this->post('/forgot-password', ['email' => 'nobody@example.test'])
            ->assertSessionHas('status', $message);

        Notification::assertSentTo($customer, ResetPassword::class);
    }

    public function test_a_valid_token_resets_the_password_and_the_customer_can_log_in_with_it(): void
    {
        $customer = Customer::factory()->create(['password_hash' => 'old-password']);
        $token = Password::createToken($customer);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect('/login');

        $this->post('/login', [
            'email' => $customer->email,
            'password' => 'new-password123',
        ]);

        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->from('/reset-password/bad-token')->post('/reset-password', [
            'token' => 'bad-token',
            'email' => $customer->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
