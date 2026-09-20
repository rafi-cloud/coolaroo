<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create(['email_verified_at' => now()]);
    }

    public function test_customer_can_submit_feedback_for_a_served_qr_order(): void
    {
        $order = Order::factory()->served()->create(['customer_id' => $this->customer->customer_id]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertSee('data-testid="feedback-form"', false);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('orders.feedback.store', $order), [
                'food_rating' => 5,
                'service_rating' => 4,
                'comment' => 'Great meal.',
            ]);

        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseHas('feedback', [
            'order_id' => $order->order_id,
            'customer_id' => $this->customer->customer_id,
            'food_rating' => 5,
            'service_rating' => 4,
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertSee('data-testid="feedback-thanks"', false)
            ->assertDontSee('data-testid="feedback-form"', false);
    }

    public function test_ratings_must_be_between_one_and_five(): void
    {
        $order = Order::factory()->served()->create(['customer_id' => $this->customer->customer_id]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('orders.feedback.store', $order), [
                'food_rating' => 6,
                'service_rating' => 4,
            ]);

        $response->assertSessionHasErrors('food_rating');
        $this->assertDatabaseMissing('feedback', ['order_id' => $order->order_id]);
    }

    public function test_customer_cannot_submit_feedback_for_someone_elses_order(): void
    {
        $otherCustomer = Customer::factory()->create();
        $order = Order::factory()->served()->create(['customer_id' => $otherCustomer->customer_id]);

        $this->actingAs($this->customer, 'customer')
            ->post(route('orders.feedback.store', $order), [
                'food_rating' => 5,
                'service_rating' => 5,
            ])
            ->assertForbidden();
    }

    /** BR53: staff-taken orders are not eligible for feedback. */
    public function test_staff_taken_orders_are_not_eligible_for_feedback(): void
    {
        $order = Order::factory()->served()->create([
            'customer_id' => $this->customer->customer_id,
            'taken_by_staff_id' => Staff::factory(),
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertDontSee('data-testid="feedback-form"', false);

        $this->actingAs($this->customer, 'customer')
            ->post(route('orders.feedback.store', $order), [
                'food_rating' => 5,
                'service_rating' => 5,
            ])
            ->assertForbidden();
    }

    /** BR43: one feedback per order. */
    public function test_feedback_cannot_be_submitted_twice(): void
    {
        $order = Order::factory()->served()->create(['customer_id' => $this->customer->customer_id]);

        Feedback::create([
            'order_id' => $order->order_id,
            'customer_id' => $this->customer->customer_id,
            'food_rating' => 4,
            'service_rating' => 4,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('orders.feedback.store', $order), [
                'food_rating' => 3,
                'service_rating' => 3,
            ]);

        $response->assertSessionHasErrors('feedback');
        $this->assertDatabaseCount('feedback', 1);
    }
}
