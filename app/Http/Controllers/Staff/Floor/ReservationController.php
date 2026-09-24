<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePhoneBookingRequest;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Services\ReservationService;
use App\Services\TrustService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Staff Floor Reservations Board & Review.
 */
class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $reservationService,
        private TrustService $trustService,
    ) {}

    public function index(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $statusFilter = $request->input('status');

        $query = Reservation::query()
            ->whereDate('booking_date', $date)
            ->with([
                'customer',
                'slot',
                'visits.restaurantTable',
                'reviewedBy',
                'createdBy',
            ])
            ->orderBy('booking_time')
            ->orderBy('created_at');

        if ($statusFilter && in_array($statusFilter, array_column(ReservationStatus::cases(), 'value'), true)) {
            $query->where('status', $statusFilter);
        }

        $reservations = $query->get();

        // Calculate counts for tab filters
        $allDayReservations = Reservation::whereDate('booking_date', $date)->get();
        $counts = [
            'all' => $allDayReservations->count(),
            'requested' => $allDayReservations->where('status', ReservationStatus::Requested)->count(),
            'confirmed' => $allDayReservations->where('status', ReservationStatus::Confirmed)->count(),
            'seated' => $allDayReservations->where('status', ReservationStatus::Seated)->count(),
            'completed' => $allDayReservations->where('status', ReservationStatus::Completed)->count(),
            'cancelled' => $allDayReservations->whereIn('status', [
                ReservationStatus::Cancelled,
                ReservationStatus::Declined,
                ReservationStatus::Expired,
                ReservationStatus::NoShow,
            ])->count(),
        ];

        $isToday = Carbon::parse($date)->isToday();

        // Process each reservation with trust badges and unassigned T-30 alerts
        $reservations->each(function (Reservation $reservation) use ($isToday) {
            $reservation->trust_badge = $this->trustService->badge($reservation->customer);
            $reservation->trust_profile = $this->trustService->profile($reservation->customer);

            // Active (unclosed) assigned tables
            $activeVisits = $reservation->visits->whereNull('closed_at');
            $reservation->assigned_tables = $activeVisits->map(fn ($v) => $v->restaurantTable)->filter();
            $reservation->is_unassigned = $activeVisits->isEmpty();

            // unassigned bookings inside T-30 highlighted
            $bookedAt = $this->reservationService->bookedAt($reservation);
            $insideT30 = now()->betweenIncluded(
                $bookedAt->copy()->subMinutes(30),
                $bookedAt->copy()->addMinutes(15)
            );

            $reservation->is_unassigned_inside_t30 = $isToday
                && $reservation->is_unassigned
                && $insideT30
                && in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::Requested], true);

            // grace elapsed check
            $graceMinutes = (int) (Setting::find('reservation_grace_minutes')?->setting_value ?? 15);
            $graceCutoff = $bookedAt->copy()->addMinutes($graceMinutes);
            $reservation->is_grace_elapsed = $reservation->status === ReservationStatus::Confirmed && now()->gte($graceCutoff);
            $reservation->can_seat = $reservation->status === ReservationStatus::Confirmed && $reservation->assigned_tables->isNotEmpty();
        });

        $activeSlots = SlotCapacity::where('is_active', true)->orderBy('slot_time')->get();
        $recentCustomers = Customer::where('is_active', true)->orderBy('full_name')->take(50)->get();

        return view('staff.reservations.index', [
            'reservations' => $reservations,
            'date' => $date,
            'statusFilter' => $statusFilter,
            'counts' => $counts,
            'isToday' => $isToday,
            'activeSlots' => $activeSlots,
            'recentCustomers' => $recentCustomers,
        ]);
    }

    public function store(StorePhoneBookingRequest $request): RedirectResponse
    {
        $staff = $request->user('staff');

        $reservation = $this->reservationService->createPhoneBooking(
            $staff,
            $request->validated()
        );

        return redirect()->route('staff.reservations.index', ['date' => $reservation->booking_date->toDateString()])
            ->with('status', 'reservation-created')
            ->with('message', "Phone booking {$reservation->reference_code} created and confirmed for {$reservation->party_size} guests.");
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        $staff = $request->user('staff');

        $this->reservationService->approve($reservation, $staff);

        return back()->with('status', 'reservation-approved')
            ->with('message', "Reservation {$reservation->reference_code} approved successfully.");
    }

    public function decline(Request $request, Reservation $reservation): RedirectResponse
    {
        $request->validate([
            'decline_reason' => 'nullable|string|max:255',
        ]);

        $staff = $request->user('staff');

        $this->reservationService->decline(
            $reservation,
            $staff,
            $request->input('decline_reason')
        );

        return back()->with('status', 'reservation-declined')
            ->with('message', "Reservation {$reservation->reference_code} declined.");
    }

    public function seat(Request $request, Reservation $reservation): RedirectResponse
    {
        $staff = $request->user('staff');

        $this->reservationService->seatReservation($reservation, $staff);

        return back()->with('status', 'reservation-seated')
            ->with('message', "Reservation {$reservation->reference_code} seated successfully.");
    }

    public function noShow(Request $request, Reservation $reservation): RedirectResponse
    {
        $staff = $request->user('staff');

        $this->reservationService->markNoShow($reservation, $staff);

        return back()->with('status', 'reservation-no-show')
            ->with('message', "Reservation {$reservation->reference_code} marked as no-show.");
    }
}
