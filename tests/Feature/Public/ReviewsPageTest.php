<?php

namespace Tests\Feature\Public;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    private function review(string $name, string $comment, bool $hidden = false): Feedback
    {
        $customer = Customer::factory()->create(['full_name' => $name]);
        $order = Order::factory()->create(['customer_id' => $customer->customer_id]);

        return Feedback::create([
            'order_id' => $order->order_id,
            'customer_id' => $customer->customer_id,
            'food_rating' => 5,
            'service_rating' => 4,
            'comment' => $comment,
            'is_featured' => false,
            'is_hidden' => $hidden,
            'submitted_at' => now(),
        ]);
    }

    public function test_a_visitor_can_read_every_published_review(): void
    {
        $this->review('Sarah Kelly', 'Barramundi was perfect.');
        $this->review('Tom Nguyen', 'Great service on a busy night.');

        $this->get(route('reviews.index'))
            ->assertOk()
            ->assertSee('Barramundi was perfect.')
            ->assertSee('Great service on a busy night.')
            ->assertSee('Sarah K.')
            ->assertSee('Tom N.');
    }

    public function test_hidden_feedback_never_reaches_the_public_page(): void
    {
        $this->review('Sarah Kelly', 'Barramundi was perfect.');
        $hidden = $this->review('Rude Person', 'An abusive comment that was moderated.', hidden: true);

        $this->get(route('reviews.index'))
            ->assertOk()
            ->assertSee('Barramundi was perfect.')
            ->assertDontSee('An abusive comment that was moderated.')
            ->assertDontSee('data-testid="review-'.$hidden->order_id.'"', false);
    }

    public function test_the_page_shows_a_reviewer_as_first_name_and_last_initial_only(): void
    {
        $this->review('Sarah Kelly', 'Lovely evening.');

        $this->get(route('reviews.index'))
            ->assertOk()
            ->assertSee('Sarah K.')
            ->assertDontSee('Sarah Kelly');
    }

    public function test_the_homepage_links_visitors_through_to_the_full_list(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-testid="home-reviews-view-all"', false)
            ->assertSee(route('reviews.index'), false);
    }

    public function test_the_page_stands_up_with_no_reviews_yet(): void
    {
        $this->get(route('reviews.index'))
            ->assertOk()
            ->assertSee('data-testid="reviews-empty"', false);
    }
}
