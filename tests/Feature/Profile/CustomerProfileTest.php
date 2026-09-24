<?php

namespace Tests\Feature\Profile;

use App\Models\Customer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_update_their_name_and_phone(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Old Name']);

        $response = $this->actingAs($customer, 'customer')->patch('/profile', [
            'full_name' => 'New Name',
            'email' => $customer->email,
            'phone' => '0498765432',
        ]);

        $response->assertRedirect();
        $this->assertSame('New Name', $customer->fresh()->full_name);
        $this->assertSame('0498765432', $customer->fresh()->phone);
    }

    public function test_changing_email_resets_verification_and_sends_a_new_link(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->patch('/profile', [
            'full_name' => $customer->full_name,
            'email' => 'new-address@example.test',
            'phone' => $customer->phone,
        ]);

        $fresh = $customer->fresh();
        $this->assertSame('new-address@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        Notification::assertSentTo($fresh, VerifyEmail::class);
    }

    public function test_keeping_the_same_email_does_not_reset_verification(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->patch('/profile', [
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ]);

        $this->assertNotNull($customer->fresh()->email_verified_at);
        Notification::assertNotSentTo($customer, VerifyEmail::class);
    }

    public function test_changing_the_password_requires_the_current_password(): void
    {
        $customer = Customer::factory()->create(['password_hash' => 'old-password']);

        $response = $this->from('/profile')->actingAs($customer, 'customer')->patch('/profile', [
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'current_password' => 'wrong-password',
            'password' => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors('current_password');
    }

    public function test_a_correct_current_password_allows_a_password_change(): void
    {
        $customer = Customer::factory()->create(['password_hash' => 'old-password']);

        $this->actingAs($customer, 'customer')->patch('/profile', [
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'current_password' => 'old-password',
            'password' => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ]);

        $this->assertTrue(Hash::check('NewPass@123', $customer->fresh()->password_hash));
    }

    public function test_a_guest_cannot_reach_the_profile_page(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }
}
