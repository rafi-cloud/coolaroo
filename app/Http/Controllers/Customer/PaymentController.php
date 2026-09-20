<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Events\CashPaymentRequested;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

/**
 * FR46, FR47, FR48, BR24, BR25. show()/stripe()/return()/check() are
 * T070's "pay by card" half; cash() is T072's "request cash" half — the
 * actual "record cash payment" (FR49) is Staff\Floor\CashPaymentController.
 */
class PaymentController extends Controller
{
    public function __construct(private StripeService $stripe, private PaymentService $payments)
    {
    }

    public function show(Order $order): View
    {
        Gate::authorize('pay', $order);

        return view('customer.pay', ['order' => $order]);
    }

    public function stripe(Order $order): RedirectResponse
    {
        Gate::authorize('pay', $order);

        $payment = Payment::create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Stripe,
            'amount' => $order->total_amount,
        ]);

        try {
            $session = $this->stripe->createCheckoutSession($order, $payment);
        } catch (ApiErrorException $e) {
            Log::channel('integrations')->error('Stripe session creation failed', [
                'order_id' => $order->order_id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Card payment is temporarily unavailable — please try again or ask a staff member.');
        }

        $payment->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    /** FR48: order stays pending_payment; the floor's cash-waiting list is told (07.8). */
    public function cash(Order $order): RedirectResponse
    {
        Gate::authorize('pay', $order);

        Payment::create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Cash,
            'amount' => $order->total_amount,
        ]);

        event(new CashPaymentRequested($order));

        return redirect()->route('orders.show', $order)->with('status', 'cash-requested');
    }

    public function return(Request $request): RedirectResponse
    {
        $payment = Payment::where('stripe_session_id', $request->query('session_id'))->firstOrFail();
        $order = $payment->order;

        Gate::authorize('view', $order);

        return $this->verify($payment, $order);
    }

    public function check(Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);

        $payment = $order->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->latest('payment_id')
            ->first();

        if ($payment === null || $payment->stripe_session_id === null) {
            return back()->with('error', 'No pending card payment to check.');
        }

        return $this->verify($payment, $order);
    }

    /** BR25: delegates to PaymentService::verifyStripePayment() (T073) — the one shared implementation. */
    private function verify(Payment $payment, Order $order): RedirectResponse
    {
        try {
            $paid = $this->payments->verifyStripePayment($payment, $order->customer);
        } catch (ApiErrorException $e) {
            Log::channel('integrations')->error('Stripe session retrieval failed', [
                'payment_id' => $payment->payment_id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('orders.show', $order)
                ->with('error', 'Could not check payment status just now — try again shortly.');
        }

        if ($paid) {
            return redirect()->route('orders.show', $order)->with('status', 'order-paid');
        }

        return redirect()->route('orders.show', $order)
            ->with('error', 'Payment not completed yet — you can try again or use Check payment status.');
    }
}
