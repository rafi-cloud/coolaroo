<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RecordCashPaymentRequest;
use App\Models\Order;
use App\Services\CashPaymentService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * FR49, BR22, BR23, BR54. UC20 step 5's "Confirm" action — everything
 * before it (open the item, see the amount, enter received) is
 * <x-floor.cash-modal>'s job once T090/T091 give it a page to live on.
 */
class CashPaymentController extends Controller
{
    public function __construct(private CashPaymentService $cash, private PaymentService $payments)
    {
    }

    public function store(RecordCashPaymentRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('recordPayment', Order::class);

        $payment = $order->payments()
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentAttemptStatus::Pending)
            ->latest('payment_id')
            ->first();

        abort_if($payment === null, 404, 'No pending cash payment for this order.');

        $adjustmentAmount = (float) ($request->validated('adjustment_amount') ?? 0);
        $amountDue = $this->cash->amountDue($order, $adjustmentAmount);
        $amountReceived = (float) $request->validated('amount_received');

        if ($amountReceived < $amountDue) {
            throw ValidationException::withMessages([
                'amount_received' => 'The amount received is less than the amount due.',
            ]);
        }

        $payment->update([
            'recorded_by_staff_id' => $request->user('staff')->staff_id,
            'amount' => $amountDue,
            'amount_received' => $amountReceived,
            'change_given' => $this->cash->changeGiven($amountDue, $amountReceived),
            'rounding_amount' => $this->cash->roundingAmount($order),
            'adjustment_amount' => $adjustmentAmount,
            'adjustment_category' => $request->validated('adjustment_category'),
            'adjustment_note' => $request->validated('adjustment_note'),
        ]);

        $this->payments->markPaid($payment, $request->user('staff'));

        return redirect()->to(
            Route::has('staff.floor.index') ? route('staff.floor.index') : route('staff.profile.edit')
        )->with('status', 'cash-recorded');
    }
}
