<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\SlotCapacity;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $upcoming = Reservation::where('customer_id', $customer->customer_id)
            ->whereIn('status', [ReservationStatus::Requested, ReservationStatus::Confirmed])
            ->with(['slot', 'visits.restaurantTable'])
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->get();

        $past = Reservation::where('customer_id', $customer->customer_id)
            ->whereNotIn('status', [ReservationStatus::Requested, ReservationStatus::Confirmed])
            ->with(['slot', 'visits.restaurantTable'])
            ->orderByDesc('booking_date')
            ->orderByDesc('booking_time')
            ->paginate(10, ['*'], 'past_page');

        $activeSlots = SlotCapacity::where('is_active', true)
            ->orderBy('slot_time')
            ->get();

        return view('customer.reservations', [
            'upcoming' => $upcoming,
            'past' => $past,
            'activeSlots' => $activeSlots,
        ]);
    }

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
            ->with('message', "Your reservation request {$reservation->reference_code} for {$reservation->party_size} guests on {$reservation->booking_date->format('d/m/Y')} at ".substr($reservation->booking_time, 0, 5).' has been received.');
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        $validated = $request->validate([
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'slot_id' => ['nullable', 'integer', 'exists:slot_capacity,slot_id'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'special_requests' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = $request->user('customer');
        $updated = $this->reservationService->update($reservation, $customer, $validated);

        $statusMsg = "Reservation {$updated->reference_code} has been updated.";
        if ($updated->status === ReservationStatus::Requested) {
            $statusMsg .= ' Changes to date, time or party size require staff review.';
        }

        return redirect()->route('reservations.index')
            ->with('status', 'reservation-updated')
            ->with('message', $statusMsg);
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        $customer = $request->user('customer');
        $cancelled = $this->reservationService->cancelByCustomer($reservation, $customer);

        $msg = "Reservation {$cancelled->reference_code} has been cancelled.";
        if ($cancelled->is_late_cancellation) {
            $msg .= ' (Note: Cancellations within 2 hours of booking time are recorded as late cancellations).';
        }

        return redirect()->route('reservations.index')
            ->with('status', 'reservation-cancelled')
            ->with('message', $msg);
    }
}
