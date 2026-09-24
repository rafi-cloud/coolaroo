<?php

namespace App\Http\Controllers\Public;

use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\CartService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * The 'signed' middleware
 * proves we issued the URL; the qr_token comparison below is what makes
 * the "regenerating invalidates the old QR" actually true.
 */
class TableScanController extends Controller
{
    public function __construct(
        private CartService $cart,
        private ReservationService $reservations,
    ) {}

    public function show(Request $request, RestaurantTable $table, string $token): View|RedirectResponse
    {
        abort_unless(hash_equals($table->qr_token, $token), 403);

        $request->session()->put('qr.table_id', $table->table_id);

        if (! $table->is_active) {
            return view('public.table-unavailable', ['table' => $table]);
        }

        $customer = $request->user('customer');

        if ($customer === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('customer.login');
        }

        $reservation = $this->reservations->assignedReservation($table);

        // BR03/FR69: the holder's own scan seats them. This is deliberately not
        // gated on the table already reading Reserved — that only happens once
        // the T-30 sweep has run, and a holder arriving before it would
        // otherwise walk in as an anonymous diner and leave their booking
        // Confirmed with the floor none the wiser.
        if ($reservation !== null && $this->reservations->isHolderWithinWindow($reservation, $customer)) {
            $this->reservations->seatOnHolderScan($table, $reservation);
        } elseif ($table->status === TableStatus::Reserved) {
            return view('public.table-reserved', [
                'table' => $table,
                'holderName' => $reservation === null
                    ? 'a booking'
                    : $this->reservations->holderDisplayName($reservation),
            ]);
        }

        $boundTableId = $this->cart->boundTableId();

        if ($boundTableId !== null && $boundTableId !== $table->table_id && $this->cart->count() > 0) {
            $request->session()->put('qr.pending_table_id', $table->table_id);

            return view('public.table-switch', [
                'table' => $table,
                'boundTable' => RestaurantTable::find($boundTableId),
                'cartCount' => $this->cart->count(),
            ]);
        }

        return $this->enterOrdering($request, $table);
    }

    /** the cart belongs to one table; switching clears it. */
    public function switchTable(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless(
            $request->session()->get('qr.pending_table_id') === $table->table_id,
            403
        );

        $this->cart->clear();

        return $this->enterOrdering($request, $table);
    }

    private function enterOrdering(Request $request, RestaurantTable $table): RedirectResponse
    {
        $request->session()->put('table_id', $table->table_id);
        $request->session()->forget(['qr.table_id', 'qr.pending_table_id']);

        return redirect()->to(
            Route::has('menu.index') ? route('menu.index') : route('cart.index')
        );
    }
}
