<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_customer_sees_the_verification_notice(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get('/email/verify')
            ->assertOk();
    }

    public function test_an_already_verified_customer_is_redirected_away_from_the_notice(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get('/email/verify')
            ->assertRedirect('/');
    }

    public function test_a_valid_signed_link_verifies_the_email(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $customer->getKey(),
            'hash' => sha1($customer->email),
        ]);

        $response = $this->actingAs($customer, 'customer')->get($url);

        $response->assertRedirect('/');
        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_a_tampered_link_is_rejected(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $customer->getKey(),
            'hash' => sha1('someone-else@example.test'),
        ]);

        $this->actingAs($customer, 'customer')->get($url)->assertForbidden();

        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_an_unverified_customer_can_request_a_new_link(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->post('/email/verification-notification')
            ->assertRedirect();

        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_a_signed_link_verifies_and_signs_in_a_guest(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $customer->getKey(),
            'hash' => sha1($customer->email),
        ]);

        $response = $this->get($url);

        $response->assertRedirect('/');
        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_an_unverified_account_cannot_log_in(): void
    {
        $customer = Customer::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'password_hash' => 'password123',
        ]);

        $this->from(route('customer.login'))
            ->post('/login', ['email' => 'unverified@example.test', 'password' => 'password123'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification.email', 'unverified@example.test');

        $this->assertGuest('customer');
    }

    public function test_a_guest_can_request_a_new_link_by_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create(['email' => 'guest@example.test']);

        $this->from(route('customer.login'))
            ->post('/email/verification-notification', ['email' => 'guest@example.test'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_a_guest_with_no_pending_address_is_sent_to_login(): void
    {
        $this->get('/email/verify')->assertRedirect(route('customer.login'));
    }

    public function test_a_guest_resend_for_an_unknown_address_looks_the_same(): void
    {
        Notification::fake();

        $this->from(route('customer.login'))
            ->post('/email/verification-notification', ['email' => 'nobody@example.test'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertNothingSent();
    }
}
