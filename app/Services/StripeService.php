<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund as RefundModel;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\Refund as StripeRefund;
use Stripe\StripeClient;

/**
 * Test mode, no webhooks — every "is this paid"
 * question this app ever asks goes through retrieveSession().
 */
class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    /** amount fixed here. 30-minute expiry (07.9, Stripe's own minimum). */
    public function createCheckoutSession(Order $order, Payment $payment): Session
    {
        $base = request()->hasSession() ? request()->getSchemeAndHttpHost() : '';
        $successUrl = ($base ? $base.route('payment.success', [], false) : route('payment.success')).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = ($base ? $base.route('payment.cancelled', [], false) : route('payment.cancelled')).'?session_id={CHECKOUT_SESSION_ID}';

        return $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'aud',
                    'product_data' => ['name' => 'Coolaroo order #'.$order->order_number],
                    'unit_amount' => (int) round((float) $payment->amount * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'order_id' => (string) $order->order_id,
                'payment_id' => (string) $payment->payment_id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
    }

    /** the only thing this app accepts as proof of payment. */
    public function retrieveSession(string $sessionId): Session
    {
        return $this->client->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Stripe refunds attach to the PaymentIntent, not the Checkout
     * Session, so the intent id is resolved from the session and cached in
     * payment.provider_payment_id (06.4.21) on first use.
     */
    public function createRefund(RefundModel $refund): StripeRefund
    {
        $payment = $refund->payment;

        if ($payment->provider_payment_id === null) {
            $session = $this->retrieveSession($payment->stripe_session_id);
            $payment->update(['provider_payment_id' => $session->payment_intent]);
            $payment->refresh();
        }

        return $this->client->refunds->create([
            'payment_intent' => $payment->provider_payment_id,
            'amount' => (int) round((float) $refund->amount * 100),
            'metadata' => [
                'order_id' => (string) $refund->order_id,
                'refund_id' => (string) $refund->refund_id,
            ],
        ]);
    }

    public function retrieveRefund(string $refundId): StripeRefund
    {
        return $this->client->refunds->retrieve($refundId);
    }

    /** local half of order cancel and the daily cleanup job. */
    public function expireSession(string $sessionId): void
    {
        try {
            $this->client->checkout->sessions->expire($sessionId);
        } catch (InvalidRequestException) {
            // Already expired or already completed on Stripe's side.
        }
    }
}
