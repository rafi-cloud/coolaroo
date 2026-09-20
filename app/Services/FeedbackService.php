<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * FR76: Customer feedback submit (BR43).
 * FR77: View and reply to feedback (BR44).
 * FR78: Hide abusive feedback with mandatory reason (BR44).
 * FR79: Feature review on public site (BR45, UC34).
 */
class FeedbackService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * BR43: one feedback per served, paid QR order. Ownership, served status and
     * staff-taken exclusion are FeedbackPolicy::create()'s job, checked before
     * this runs; "not already submitted" is checked here instead, since it needs
     * a query the policy deliberately avoids.
     */
    public function submit(Order $order, Customer $customer, array $data): Feedback
    {
        if ($order->feedback()->exists()) {
            throw ValidationException::withMessages([
                'feedback' => 'Feedback has already been submitted for this order.',
            ]);
        }

        return Feedback::create([
            'order_id' => $order->order_id,
            'customer_id' => $customer->customer_id,
            'food_rating' => $data['food_rating'],
            'service_rating' => $data['service_rating'],
            'comment' => $data['comment'] ?? null,
        ]);
    }

    /**
     * FR77: Paginated feedback list for admin moderation.
     *
     * @param  array{status?: string, rating?: int|string, search?: string}  $filters
     */
    public function listForAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = Feedback::with(['customer', 'order', 'repliedBy'])
            ->latest('submitted_at');

        $status = $filters['status'] ?? 'all';
        if ($status === 'visible') {
            $query->where('is_hidden', false);
        } elseif ($status === 'hidden') {
            $query->where('is_hidden', true);
        } elseif ($status === 'featured') {
            $query->where('is_featured', true);
        } elseif ($status === 'replied') {
            $query->whereNotNull('admin_reply');
        } elseif ($status === 'unreplied') {
            $query->whereNull('admin_reply');
        }

        if (! empty($filters['rating'])) {
            $rating = (int) $filters['rating'];
            $query->where(function ($q) use ($rating) {
                $q->where('food_rating', $rating)
                    ->orWhere('service_rating', $rating);
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhere('order_id', $search)
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Summary counters for moderation overview.
     *
     * @return array{total: int, visible: int, hidden: int, featured: int, unreplied: int}
     */
    public function counts(): array
    {
        return [
            'total' => Feedback::count(),
            'visible' => Feedback::where('is_hidden', false)->count(),
            'hidden' => Feedback::where('is_hidden', true)->count(),
            'featured' => Feedback::where('is_featured', true)->count(),
            'unreplied' => Feedback::whereNull('admin_reply')->count(),
        ];
    }

    /**
     * FR77: Reply to feedback.
     * Admin cannot edit customer rating or comment (BR44).
     */
    public function reply(Feedback $feedback, Staff $admin, string $reply): Feedback
    {
        $reply = trim($reply);

        $feedback->update([
            'admin_reply' => $reply,
            'replied_by_staff_id' => $admin->staff_id,
            'replied_at' => now(),
        ]);

        $this->auditLogger->log($admin, 'feedback_reply', $feedback);

        return $feedback;
    }

    /**
     * FR78, BR44: Hide abusive feedback with mandatory reason.
     * Hidden feedback is excluded from public averages and cannot be featured (BR45, UC34).
     */
    public function hide(Feedback $feedback, Staff $admin, string $reason): Feedback
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when hiding customer feedback.',
            ]);
        }

        $feedback->update([
            'is_hidden' => true,
            'hidden_reason' => $reason,
            'is_featured' => false,
        ]);

        $this->auditLogger->log($admin, 'feedback_hide', $feedback, $reason);

        return $feedback;
    }

    /**
     * FR78: Unhide feedback.
     */
    public function unhide(Feedback $feedback, Staff $admin): Feedback
    {
        $feedback->update([
            'is_hidden' => false,
            'hidden_reason' => null,
        ]);

        $this->auditLogger->log($admin, 'feedback_unhide', $feedback);

        return $feedback;
    }

    /**
     * FR79, BR45: Feature review on public site.
     * Hidden feedback cannot be featured (UC34).
     */
    public function feature(Feedback $feedback, Staff $admin): Feedback
    {
        if ($feedback->is_hidden) {
            throw ValidationException::withMessages([
                'featured' => 'Hidden feedback cannot be featured.',
            ]);
        }

        $feedback->update([
            'is_featured' => true,
        ]);

        $this->auditLogger->log($admin, 'feedback_feature', $feedback);

        return $feedback;
    }

    /**
     * FR79: Unfeature review.
     */
    public function unfeature(Feedback $feedback, Staff $admin): Feedback
    {
        $feedback->update([
            'is_featured' => false,
        ]);

        $this->auditLogger->log($admin, 'feedback_unfeature', $feedback);

        return $feedback;
    }

    /**
     * Toggle featured state.
     */
    public function toggleFeatured(Feedback $feedback, Staff $admin): Feedback
    {
        if ($feedback->is_featured) {
            return $this->unfeature($feedback, $admin);
        }

        return $this->feature($feedback, $admin);
    }
}
