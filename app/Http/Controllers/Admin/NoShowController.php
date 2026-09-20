<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NoShowController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {
    }

    /**
     * FR10, UC38, S38: Admin clears a customer's no-show flag with required reason.
     */
    public function clear(Request $request, Customer $customer, Reservation $reservation): RedirectResponse
    {
        if ($reservation->customer_id !== $customer->customer_id) {
            throw ValidationException::withMessages([
                'reservation' => 'Reservation does not belong to this customer.',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $admin = $request->user('staff');

        $this->reservationService->clearNoShow(
            $reservation,
            $admin,
            $validated['reason']
        );

        return back()
            ->with('status', 'no-show-cleared')
            ->with('message', "No-show flag cleared for booking {$reservation->reference_code}.");
    }
}
