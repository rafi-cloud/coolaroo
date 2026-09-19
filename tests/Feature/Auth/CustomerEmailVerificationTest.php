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
}
