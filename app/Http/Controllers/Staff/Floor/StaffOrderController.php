<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffOrderRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Services\CashPaymentService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\StripeService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

/**
 * FR42's Stripe-QR half (T073), FR47's staff Check-payment button, and
 * FR42's table/cart/checkout half (T092, index()/store()).
 */
class StaffOrderController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private PaymentService $payments,
        private CheckoutService $checkout,
        private CashPaymentService $cashPayments,
    ) {}

    /** FR42, UC19. One route, two states — 07.6 lists no separate "order created" URI. */
    public function index(RestaurantTable $table): View
    {
        Gate::authorize('take', Order::class);

        $pendingOrder = $table->orders()->where('status', OrderStatus::PendingPayment)->latest('placed_at')->first();

        if ($pendingOrder !== null) {
            return view('staff.floor.order-payment', [
                'table' => $table,
                'order' => $pendingOrder,
                'amountDue' => $this->cashPayments->amountDue($pendingOrder, 0),
                'roundingAmount' => $this->cashPayments->roundingAmount($pendingOrder),
            ]);
        }

        return view('staff.floor.order-builder', [
            'table' => $table,
            'items' => $this->orderableItems(),
        ]);
    }

    /** BR53: exact stock check, taken_by_staff_id, no customer account. */
    public function store(StaffOrderRequest $request, RestaurantTable $table): RedirectResponse
    {
        Gate::authorize('take', Order::class);

        $cartLines = collect($request->validated('lines'))->map(function (array $line) {
            [$itemId, $sizeId] = array_map('intval', explode(':', $line['item_size']));

            return [
                'item_id' => $itemId,
                'size_id' => $sizeId,
                'quantity' => (int) $line['quantity'],
                'special_request' => $line['special_request'] ?: null,
                'add_on_option_ids' => [],
            ];
        })->all();

        $sessionKey = "staff_checkout_idempotency_key_{$table->table_id}";
        $idempotencyKey = $request->session()->get($sessionKey) ?? (string) Str::uuid();
        $request->session()->put($sessionKey, $idempotencyKey);

        try {
            $this->checkout->checkout($table->table_id, null, $cartLines, $idempotencyKey, $request->user('staff'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $request->session()->forget($sessionKey);

        return redirect()->route('staff.tables.order', $table)->with('status', 'order-created');
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

        $qrSvg = (new Builder(writer: new SvgWriter))
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

    /** FR42: active, available items with at least one active size. */
    private function orderableItems(): Collection
    {
        return MenuItem::where('is_active', true)
            ->where('is_available', true)
            ->with(['sizes' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('item_name')
            ->get()
            ->filter(fn (MenuItem $item) => $item->sizes->isNotEmpty())
            ->values();
    }
}
