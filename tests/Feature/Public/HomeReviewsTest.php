<?php

namespace Tests\Feature\Public;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    private function createOrderForCustomer(Customer $customer): Order
    {
        return Order::factory()->create(['customer_id' => $customer->customer_id]);
    }

    public function test_featured_reviews_render_with_author_name_and_date(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Sarah Kelly']);
        $order = $this->createOrderForCustomer($customer);

        Feedback::create([
            'order_id' => $order->order_id,
            'customer_id' => $customer->customer_id,
            'food_rating' => 5,
            'service_rating' => 5,
            'comment' => 'The Margherita is the best we have had outside Naples.',
            'is_featured' => true,
            'is_hidden' => false,
            'submitted_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="home-reviews-section"', false);
        $response->assertSee('Sarah K.');
        $response->assertSee('The Margherita is the best we have had outside Naples.');
        $response->assertSee('Rated 5 out of 5');
    }

    public function test_rating_summary_card_hidden_when_review_count_below_threshold(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'John Smith']);

        for ($i = 0; $i < 3; $i++) {
            $order = $this->createOrderForCustomer($customer);
            Feedback::create([
                'order_id' => $order->order_id,
                'customer_id' => $customer->customer_id,
                'food_rating' => 4,
                'service_rating' => 4,
                'comment' => "Review {$i}",
                'is_featured' => false,
                'is_hidden' => false,
                'submitted_at' => now(),
            ]);
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-testid="home-rating-card"', false);
    }

    public function test_rating_summary_card_renders_when_review_count_meets_threshold(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Diner Group']);

        for ($i = 0; $i < 10; $i++) {
            $order = $this->createOrderForCustomer($customer);
            Feedback::create([
                'order_id' => $order->order_id,
                'customer_id' => $customer->customer_id,
                'food_rating' => 5,
                'service_rating' => 4,
                'comment' => "Review {$i}",
                'is_featured' => false,
                'is_hidden' => false,
                'submitted_at' => now(),
            ]);
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="home-rating-card"', false);
        $response->assertSee('4.5');
        $response->assertSee('Based on 10 reviews from diners');
    }

    public function test_hidden_reviews_are_excluded_from_averages_and_featured_list(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Hidden User']);
        $order = $this->createOrderForCustomer($customer);

        Feedback::create([
            'order_id' => $order->order_id,
            'customer_id' => $customer->customer_id,
            'food_rating' => 1,
            'service_rating' => 1,
            'comment' => 'Secret offensive text that should never show',
            'is_featured' => true,
            'is_hidden' => true,
            'hidden_reason' => 'Policy violation',
            'submitted_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Secret offensive text that should never show');
    }
}
