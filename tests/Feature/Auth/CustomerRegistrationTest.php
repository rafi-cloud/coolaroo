<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_is_logged_in_and_sent_a_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'full_name' => 'Jamie Nguyen',
            'email' => 'jamie@example.test',
            'phone' => '0412345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $customer = Customer::where('email', 'jamie@example.test')->firstOrFail();

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertNull($customer->email_verified_at);
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_registration_fails_when_the_email_is_already_taken(): void
    {
        Customer::factory()->create(['email' => 'taken@example.test']);

        $response = $this->from('/register')->post('/register', [
            'full_name' => 'Jamie Nguyen',
            'email' => 'taken@example.test',
            'phone' => '0412345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_a_logged_in_customer_cannot_reach_the_registration_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/register')
            ->assertRedirect('/');
    }
}
