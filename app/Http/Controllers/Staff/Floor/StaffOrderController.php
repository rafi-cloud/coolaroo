<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\StripeService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

/**
 * FR42's Stripe-QR half (T073) + FR47's staff Check-payment button —
 * T092 adds the table/cart/checkout half to this same class.
 */
class StaffOrderController extends Controller
{
    public function __construct(private StripeService $stripe, private PaymentService $payments)
    {
    }

    public function stripeQr(Order $order): View|RedirectResponse
    {
        Gate::authorize('take', Order::class);

        $payment = $this->pendingStripePayment($order);

        if ($payment === null) {
            $payment = Payment::create([
                'order_id' => $order->order_id,
                'method' => PaymentMethod::Stripe,
                'amount' => $order->total_amount,
            ]);
        }

        try {
            $session = $payment->stripe_session_id === null
                ? $this->stripe->createCheckoutSession($order, $payment)
                : $this->stripe->retrieveSession($payment->stripe_session_id);
        } catch (ApiErrorException $e) {
            Log::channel('integrations')->error('Stripe session creation/retrieval failed', [
                'order_id' => $order->order_id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Card payment is temporarily unavailable — please try again or take cash.');
        }

        if ($payment->stripe_session_id === null) {
            $payment->update(['stripe_session_id' => $session->id]);
        }

        $qrSvg = (new Builder(writer: new SvgWriter()))
            ->build(data: $session->url, size: 300, margin: 10)
            ->getString();

        return view('staff.floor.stripe-qr', ['order' => $order, 'qrSvg' => $qrSvg]);
    }

    public function check(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('take', Order::class);

        $payment = $this->pendingStripePayment($order);

        if ($payment === null || $payment->stripe_session_id === null) {
            return back()->with('error', 'No pending card payment to check.');
        }

        try {
            $paid = $this->payments->verifyStripePayment($payment, $request->user('staff'));
        } catch (ApiErrorException $e) {
            Log::channel('integrations')->error('Stripe session retrieval failed', [
                'payment_id' => $payment->payment_id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not check payment status just now — try again shortly.');
        }

        return $paid
            ? back()->with('status', 'order-paid')
            : back()->with('error', 'Payment not completed yet.');
    }

    private function pendingStripePayment(Order $order): ?Payment
    {
        return $order->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->latest('payment_id')
            ->first();
    }
}
