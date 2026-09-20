<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Screen S40: Admin Feedback Moderation.
 * FR77: View and reply to feedback (BR44).
 * FR78: Hide abusive feedback with mandatory reason (BR44).
 * FR79: Feature/unfeature review on public site (BR45, UC34).
 */
class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    /**
     * FR77, S40, UC34: Display paginated feedback list with filters and status breakdown.
     */
    public function index(Request $request): View
    {
        $filters = [
            'status' => (string) $request->input('status', 'all'),
            'rating' => $request->input('rating'),
            'search' => trim((string) $request->input('search', '')),
        ];

        $feedback = $this->feedbackService->listForAdmin($filters);
        $counts = $this->feedbackService->counts();

        return view('admin.feedback.index', [
            'feedbackList' => $feedback,
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    /**
     * FR77, BR44: Reply to a customer review. Admin cannot edit the review itself.
     */
    public function reply(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        /** @var \App\Models\Staff $admin */
        $admin = $request->user('staff');

        $this->feedbackService->reply($feedback, $admin, $validated['reply']);

        return back()->with('status', 'Reply posted successfully.');
    }

    /**
     * FR78, BR44: Hide abusive review with mandatory justification reason.
     */
    public function hide(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        /** @var \App\Models\Staff $admin */
        $admin = $request->user('staff');

        $this->feedbackService->hide($feedback, $admin, $validated['reason']);

        return back()->with('status', 'Review hidden from public site.');
    }

    /**
     * FR78: Restore hidden review to public visibility.
     */
    public function unhide(Request $request, Feedback $feedback): RedirectResponse
    {
        /** @var \App\Models\Staff $admin */
        $admin = $request->user('staff');

        $this->feedbackService->unhide($feedback, $admin);

        return back()->with('status', 'Review restored to public visibility.');
    }

    /**
     * FR79, BR45, UC34: Toggle featured testimonial status.
     * Hidden reviews cannot be featured.
     */
    public function toggleFeatured(Request $request, Feedback $feedback): RedirectResponse
    {
        /** @var \App\Models\Staff $admin */
        $admin = $request->user('staff');

        try {
            $updated = $this->feedbackService->toggleFeatured($feedback, $admin);
            $msg = $updated->is_featured ? 'Review featured on public site.' : 'Review unfeatured.';

            return back()->with('status', $msg);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }
}
