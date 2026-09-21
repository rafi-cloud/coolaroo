<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddCartLineRequest;
use App\Http\Requests\Customer\UpdateCartLineRequest;
use App\Models\RestaurantTable;
use App\Services\CartService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(): View
    {
        $tableId = session('table_id');
        $table = $tableId ? RestaurantTable::find($tableId) : null;

        return view('customer.cart', [
            'lines' => $this->cart->lines(),
            'total' => $this->cart->total(),
            'table' => $table,
            'tableLabel' => $table ? 'Table '.$table->table_number : null,
            'qrOrderingEnabled' => app(SettingService::class)->getBool('qr_ordering_enabled', true),
        ]);
    }

    public function store(AddCartLineRequest $request): RedirectResponse
    {
        $this->cart->add(
            $request->validated('item_id'),
            $request->validated('size_id'),
            $request->input('add_on_option_ids', []),
            $request->validated('quantity'),
            $request->validated('special_request'),
        );

        return redirect()->route('cart.index')->with('status', 'item-added');
    }

    public function update(UpdateCartLineRequest $request, string $line): RedirectResponse
    {
        $this->cart->updateLine($line, $request->validated('quantity'), $request->validated('special_request'));

        return redirect()->route('cart.index')->with('status', 'cart-updated');
    }

    public function destroy(string $line): RedirectResponse
    {
        $this->cart->removeLine($line);

        return redirect()->route('cart.index')->with('status', 'line-removed');
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return redirect()->route('cart.index')->with('status', 'cart-cleared');
    }
}
