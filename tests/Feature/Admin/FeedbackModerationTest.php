<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackModerationTest extends TestCase
{
    use RefreshDatabase;

    private Staff $admin;

    private Staff $waitstaff;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, SettingSeeder::class]);

        $adminRole = Role::where('role_name', 'admin')->first();
        $waitstaffRole = Role::where('role_name', 'waitstaff')->first();

        $this->admin = Staff::factory()->create([
            'role_id' => $adminRole->role_id,
            'full_name' => 'Admin User',
            'is_active' => true,
        ]);

        $this->waitstaff = Staff::factory()->create([
            'role_id' => $waitstaffRole->role_id,
            'full_name' => 'Wait Staff',
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'full_name' => 'Alice Diner',
            'email' => 'alice@example.com',
        ]);
    }

    private function createFeedback(array $attributes = []): Feedback
    {
        $order = Order::factory()->served()->create([
            'customer_id' => $this->customer->customer_id,
        ]);

        return Feedback::create(array_merge([
            'order_id' => $order->order_id,
            'customer_id' => $this->customer->customer_id,
            'food_rating' => 5,
            'service_rating' => 4,
            'comment' => 'Delicious pizza and fast service!',
            'is_hidden' => false,
            'is_featured' => false,
            'submitted_at' => now(),
        ], $attributes));
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $response = $this->get(route('admin.feedback.index'));

        $response->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_is_forbidden_from_feedback_moderation(): void
    {
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->get(route('admin.feedback.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_feedback_moderation_page(): void
    {
        $fb = $this->createFeedback();

        $response = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.feedback.index'));

        $response->assertOk();
        $response->assertSee('Customer Feedback');
        $response->assertSee('Alice Diner');
        $response->assertSee('Delicious pizza and fast service!');
        $response->assertSee("Order #{$fb->order_id}");
        $response->assertSee('data-testid="admin-feedback-page"', false);
    }

    public function test_admin_can_filter_feedback_by_status(): void
    {
        $visible = $this->createFeedback(['comment' => 'Visible review']);
        $hidden = $this->createFeedback([
            'comment' => 'Offensive comment',
            'is_hidden' => true,
            'hidden_reason' => 'Offensive terms',
        ]);
        $featured = $this->createFeedback([
            'comment' => 'Award winning dinner',
            'is_featured' => true,
        ]);

        $resHidden = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.feedback.index', ['status' => 'hidden']));

        $resHidden->assertOk();
        $resHidden->assertSee('Offensive comment');
        $resHidden->assertDontSee('Visible review');

        $resFeatured = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.feedback.index', ['status' => 'featured']));

        $resFeatured->assertOk();
        $resFeatured->assertSee('Award winning dinner');
        $resFeatured->assertDontSee('Offensive comment');
    }

    public function test_admin_can_search_feedback(): void
    {
        $this->createFeedback(['comment' => 'Exceptional Barramundi']);
        $this->createFeedback(['comment' => 'Regular burger']);

        $res = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.feedback.index', ['search' => 'Barramundi']));

        $res->assertOk();
        $res->assertSee('Exceptional Barramundi');
        $res->assertDontSee('Regular burger');
    }

    public function test_admin_can_reply_to_feedback_and_it_is_audited(): void
    {
        $fb = $this->createFeedback();

        $response = $this->actingAs($this->admin, 'staff')
            ->post(route('admin.feedback.reply', $fb), [
                'reply' => 'Thank you for your lovely feedback Alice! We look forward to seeing you again.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Reply posted successfully.');

        $fb->refresh();
        $this->assertEquals('Thank you for your lovely feedback Alice! We look forward to seeing you again.', $fb->admin_reply);
        $this->assertEquals($this->admin->staff_id, $fb->replied_by_staff_id);
        $this->assertNotNull($fb->replied_at);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'feedback_reply',
            'entity_name' => 'feedback',
            'entity_id' => $fb->order_id,
        ]);
    }

    public function test_reply_requires_content(): void
    {
        $fb = $this->createFeedback();

        $response = $this->actingAs($this->admin, 'staff')
            ->post(route('admin.feedback.reply', $fb), [
                'reply' => '',
            ]);

        $response->assertSessionHasErrors('reply');
    }

    public function test_admin_can_hide_feedback_with_mandatory_reason_and_it_is_audited(): void
    {
        $fb = $this->createFeedback();

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.hide', $fb), [
                'reason' => 'Inappropriate profanity',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Review hidden from public site.');

        $fb->refresh();
        $this->assertTrue($fb->is_hidden);
        $this->assertEquals('Inappropriate profanity', $fb->hidden_reason);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'feedback_hide',
            'entity_name' => 'feedback',
            'entity_id' => $fb->order_id,
        ]);
    }

    public function test_hiding_feedback_requires_a_reason_per_br44(): void
    {
        $fb = $this->createFeedback();

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.hide', $fb), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors('reason');

        $fb->refresh();
        $this->assertFalse($fb->is_hidden);
    }

    public function test_admin_can_unhide_feedback_and_it_is_audited(): void
    {
        $fb = $this->createFeedback([
            'is_hidden' => true,
            'hidden_reason' => 'Disputed comment',
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.unhide', $fb));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Review restored to public visibility.');

        $fb->refresh();
        $this->assertFalse($fb->is_hidden);
        $this->assertNull($fb->hidden_reason);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'feedback_unhide',
            'entity_name' => 'feedback',
            'entity_id' => $fb->order_id,
        ]);
    }

    public function test_admin_can_feature_and_unfeature_review_and_it_is_audited(): void
    {
        $fb = $this->createFeedback(['is_featured' => false]);

        $resFeature = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.feature', $fb));

        $resFeature->assertRedirect();
        $resFeature->assertSessionHas('status', 'Review featured on public site.');

        $fb->refresh();
        $this->assertTrue($fb->is_featured);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'feedback_feature',
            'entity_name' => 'feedback',
            'entity_id' => $fb->order_id,
        ]);

        $resUnfeature = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.feature', $fb));

        $resUnfeature->assertRedirect();
        $resUnfeature->assertSessionHas('status', 'Review unfeatured.');

        $fb->refresh();
        $this->assertFalse($fb->is_featured);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'feedback_unfeature',
            'entity_name' => 'feedback',
            'entity_id' => $fb->order_id,
        ]);
    }

    public function test_hidden_review_cannot_be_featured_per_br45_and_uc34(): void
    {
        $fb = $this->createFeedback([
            'is_hidden' => true,
            'hidden_reason' => 'Spam link',
            'is_featured' => false,
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.feature', $fb));

        $response->assertSessionHasErrors('featured');

        $fb->refresh();
        $this->assertFalse($fb->is_featured);
    }

    public function test_hiding_a_featured_review_automatically_unfeatures_it_per_br45(): void
    {
        $fb = $this->createFeedback(['is_featured' => true]);

        $this->assertTrue($fb->is_featured);

        $response = $this->actingAs($this->admin, 'staff')
            ->patch(route('admin.feedback.hide', $fb), [
                'reason' => 'Reported content',
            ]);

        $response->assertRedirect();

        $fb->refresh();
        $this->assertTrue($fb->is_hidden);
        $this->assertFalse($fb->is_featured);
    }
}
