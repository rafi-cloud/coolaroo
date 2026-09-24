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

    public function test_a_visitor_can_register_and_is_sent_a_verification_email_without_being_logged_in(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'full_name' => 'Jamie Nguyen',
            'email' => 'jamie@example.test',
            'phone' => '0412345678',
            'password' => 'Hello@123',
            'password_confirmation' => 'Hello@123',
        ]);

        $customer = Customer::where('email', 'jamie@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('verification.email', 'jamie@example.test');
        $this->assertGuest('customer');
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
            'password' => 'Hello@123',
            'password_confirmation' => 'Hello@123',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_registration_rejects_a_password_without_mixed_case_a_number_and_a_symbol(): void
    {
        $response = $this->from('/register')->post('/register', [
            'full_name' => 'Jamie Nguyen',
            'email' => 'jamie@example.test',
            'phone' => '0412345678',
            'password' => 'passwordpassword',
            'password_confirmation' => 'passwordpassword',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('customer', ['email' => 'jamie@example.test']);
    }

    public function test_a_logged_in_customer_cannot_reach_the_registration_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/register')
            ->assertRedirect('/');
    }
}
