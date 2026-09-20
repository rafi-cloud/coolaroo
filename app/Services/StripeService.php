<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * FR46, FR47, BR24-BR26. Test mode, no webhooks — every "is this paid"
 * question this app ever asks goes through retrieveSession() (BR25).
 * Refunds (BR27) are a T075 extension of this class, not built here.
 */
class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    /** BR24: amount fixed here. 30-minute expiry (07.9, Stripe's own minimum). */
    public function createCheckoutSession(Order $order, Payment $payment): Session
    {
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
            'success_url' => route('payment.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payment.cancelled').'?session_id={CHECKOUT_SESSION_ID}',
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
    }

    /** BR25: the only thing this app accepts as proof of payment. */
    public function retrieveSession(string $sessionId): Session
    {
        return $this->client->checkout->sessions->retrieve($sessionId);
    }

    /** BR26: local half of order cancel (T063) and the daily cleanup job (T160). */
    public function expireSession(string $sessionId): void
    {
        try {
            $this->client->checkout->sessions->expire($sessionId);
        } catch (InvalidRequestException) {
            // Already expired or already completed on Stripe's side.
        }
    }
}
