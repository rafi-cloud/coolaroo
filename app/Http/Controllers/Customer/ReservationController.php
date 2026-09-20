<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {
    }

    /**
     * FR62, UC13: Submit online reservation request.
     */
    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'slot_id' => ['required', 'integer', 'exists:slot_capacity,slot_id'],
            'party_size' => ['required', 'integer', 'min:1', 'max:10'],
            'special_requests' => ['nullable', 'string', 'max:500'],
        ]);

        $reservation = $this->reservationService->request($customer, $validated);

        return redirect(route('home').'#reserve')
            ->with('status', 'reservation-requested')
            ->with('reservation_code', $reservation->reference_code)
            ->with('message', "Your reservation request {$reservation->reference_code} for {$reservation->party_size} guests on {$reservation->booking_date->format('d/m/Y')} at ".substr($reservation->booking_time, 0, 5)." has been received.");
    }
}
