<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Order;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** FR76, UC15. */
class FeedbackController extends Controller
{
    public function __construct(private FeedbackService $feedback)
    {
    }

    public function store(StoreFeedbackRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('create', [Feedback::class, $order]);

        $this->feedback->submit($order, $request->user('customer'), $request->validated());

        return redirect()->route('orders.show', $order)->with('status', 'feedback-submitted');
    }
}
