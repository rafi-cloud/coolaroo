<?php

namespace Tests\Feature\Http;

use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('throttle:login')->post('/__test/login', fn () => 'ok');
        Route::middleware('throttle:checkout')->post('/__test/checkout', fn () => 'ok');
    }

    public function test_login_allows_five_attempts_then_blocks_the_sixth(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/__test/login', ['email' => 'a@b.test'])->assertOk();
        }

        $this->postJson('/__test/login', ['email' => 'a@b.test'])->assertStatus(429);
    }

    public function test_login_throttle_is_keyed_per_email_not_shared_globally(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/__test/login', ['email' => 'a@b.test'])->assertOk();
        }

        $this->postJson('/__test/login', ['email' => 'a@b.test'])->assertStatus(429);
        $this->postJson('/__test/login', ['email' => 'different@b.test'])->assertOk();
    }

    public function test_checkout_allows_ten_attempts_then_blocks_the_eleventh(): void
    {
        $customer = (new Customer)->forceFill(['customer_id' => 1]);
        $this->actingAs($customer, 'customer');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/__test/checkout')->assertOk();
        }

        $this->postJson('/__test/checkout')->assertStatus(429);
    }

    public function test_checkout_throttle_is_keyed_per_customer_not_shared_globally(): void
    {
        $customerOne = (new Customer)->forceFill(['customer_id' => 1]);
        $this->actingAs($customerOne, 'customer');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/__test/checkout')->assertOk();
        }

        $this->postJson('/__test/checkout')->assertStatus(429);

        $customerTwo = (new Customer)->forceFill(['customer_id' => 2]);
        $this->actingAs($customerTwo, 'customer');

        $this->postJson('/__test/checkout')->assertOk();
    }
}
