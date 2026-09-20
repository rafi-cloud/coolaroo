<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveRefundRequest;
use App\Http\Requests\Admin\RejectRefundRequest;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FR52, UC33, S34. Admin only — the whole route group is behind role:admin.
 */
class RefundController extends Controller
{
    public function __construct(private RefundService $refunds)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $refunds = Refund::query()
            ->with(['order', 'orderItem', 'payment', 'requestedBy', 'processedBy'])
            ->when(
                RefundStatus::tryFrom((string) $status),
                fn ($query, RefundStatus $only) => $query->where('status', $only),
            )
            ->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'failed' THEN 1 WHEN 'processing' THEN 2 ELSE 3 END")
            ->orderByDesc('requested_at')
            ->get();

        return view('admin.refunds.index', [
            'refunds' => $refunds,
            'status' => $status,
            'statuses' => RefundStatus::cases(),
        ]);
    }

    public function approve(ApproveRefundRequest $request, Refund $refund): RedirectResponse
    {
        $this->refunds->approve(
            $refund,
            $request->user('staff'),
            RefundMethod::from($request->validated('method')),
            (bool) $request->validated('return_to_stock'),
            $request->validated('manual_reference'),
        );

        return back()->with('status', 'refund-approved');
    }

    public function reject(RejectRefundRequest $request, Refund $refund): RedirectResponse
    {
        $this->refunds->reject($refund, $request->user('staff'), $request->validated('rejection_reason'));

        return back()->with('status', 'refund-rejected');
    }

    public function complete(Request $request, Refund $refund): RedirectResponse
    {
        $this->refunds->checkProcessing($refund, $request->user('staff'));

        return back()->with('status', 'refund-checked');
    }

    public function retry(Request $request, Refund $refund): RedirectResponse
    {
        $this->refunds->retry($refund, $request->user('staff'));

        return back()->with('status', 'refund-retried');
    }
}
