<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkout, private CartService $cart) {}

    public function store(Request $request): RedirectResponse
    {
        $key = $request->session()->get('checkout_idempotency_key') ?? (string) Str::uuid();
        $request->session()->put('checkout_idempotency_key', $key);

        try {
            $result = $this->checkout->checkout(
                $request->session()->get('table_id'),
                $request->user('customer')?->customer_id,
                $this->cart->rawLines(),
                $key,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $request->session()->forget('checkout_idempotency_key');
        $this->cart->clear();

        return redirect()->route('orders.pay.show', $result['order'])
            ->with('removed_items', $result['removed']);
    }
}
