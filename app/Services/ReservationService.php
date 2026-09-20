<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR62, FR63, FR66, FR67, BR31-BR38, BR41, BR42, BR58, BR64.
 * Core reservation service for requesting, approving, declining, cancelling,
 * and updating reservations with concurrency locks and state machine enforcement.
 */
class ReservationService
{
    public function __construct(
        private TableStatusService $tableStatus,
        private AvailabilityService $availability,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * FR62, BR31, BR35, BR42, BR58: Customer submits a reservation request.
     * Status starts at requested; slot capacity is checked; unique reference code generated.
     */
    public function request(Customer $customer, array $data): Reservation
    {
        if (! $this->availability->isOnlineReservationsEnabled()) {
            throw ValidationException::withMessages([
                'booking' => 'Online reservations are currently paused. Please call the venue directly.',
            ]);
        }

        if (! $customer->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Your email address must be verified before making a reservation.',
            ]);
        }

        if (empty(trim((string) $customer->phone))) {
            throw ValidationException::withMessages([
                'phone' => 'A mobile phone number is required on your profile to make a reservation.',
            ]);
        }

        $partySize = (int) ($data['party_size'] ?? 0);
        if ($partySize < 1) {
            throw ValidationException::withMessages([
                'party_size' => 'Party size must be at least 1 guest.',
            ]);
        }

        if ($partySize > $this->availability->getMaxPartyOnline()) {
            throw ValidationException::withMessages([
                'party_size' => 'Online bookings are limited to '.$this->availability->getMaxPartyOnline().' guests. Please call for larger groups.',
            ]);
        }

        if (empty($data['booking_date'])) {
            throw ValidationException::withMessages([
                'booking_date' => 'A booking date is required.',
            ]);
        }

        $bookingDate = $data['booking_date'] instanceof Carbon
            ? $data['booking_date']->copy()->startOfDay()
            : Carbon::parse($data['booking_date'])->startOfDay();

        if ($bookingDate->isBefore(today())) {
            throw ValidationException::withMessages([
                'booking_date' => 'Reservations cannot be made for past dates.',
            ]);
        }

        if ($this->availability->isClosedWeekday($bookingDate)) {
            throw ValidationException::withMessages([
                'booking_date' => 'The venue is closed on this day.',
            ]);
        }

        if ($bookingDate->isAfter(today()->addDays($this->availability->getMaxDaysAhead()))) {
            throw ValidationException::withMessages([
                'booking_date' => 'Reservations can only be made up to '.$this->availability->getMaxDaysAhead().' days in advance.',
            ]);
        }

        $slot = null;
        if (! empty($data['slot_id'])) {
            $slot = SlotCapacity::find($data['slot_id']);
        } elseif (! empty($data['booking_time'])) {
            $timeStr = substr($data['booking_time'], 0, 5);
            $slot = SlotCapacity::where('slot_time', $timeStr)
                ->orWhere('slot_time', $timeStr.':00')
                ->first();
        }

        if (! $slot || ! $slot->is_active) {
            throw ValidationException::withMessages([
                'slot' => 'The selected time slot is invalid or inactive.',
            ]);
        }

        $bookedAt = Carbon::parse($bookingDate->format('Y-m-d').' '.$slot->slot_time);
        if ($bookedAt->isBefore(now()->addHours($this->availability->getMinLeadHours()))) {
            throw ValidationException::withMessages([
                'booking_time' => 'Reservations require at least '.$this->availability->getMinLeadHours().' hours lead time.',
            ]);
        }

        if (isset($data['special_requests']) && strlen($data['special_requests']) > 500) {
            throw ValidationException::withMessages([
                'special_requests' => 'Special requests cannot exceed 500 characters.',
            ]);
        }

        return DB::transaction(function () use ($customer, $slot, $bookingDate, $partySize, $data) {
            SlotCapacity::whereKey($slot->slot_id)->lockForUpdate()->first();

            if (! $this->availability->hasSlotCapacity($slot, $bookingDate, $partySize)) {
                throw ValidationException::withMessages([
                    'slot' => 'No availability for the selected time slot and party size.',
                ]);
            }

            $referenceCode = $this->generateReferenceCode();

            $reservation = new Reservation();
            $reservation->forceFill([
                'customer_id' => $customer->customer_id,
                'slot_id' => $slot->slot_id,
                'reference_code' => $referenceCode,
                'booking_date' => $bookingDate->toDateString(),
                'booking_time' => $slot->slot_time,
                'party_size' => $partySize,
                'special_requests' => $data['special_requests'] ?? null,
                'status' => ReservationStatus::Requested,
            ]);
            $reservation->save();

            $this->auditLogger->log($customer, 'reservation_requested', $reservation);

            return $reservation;
        });
    }

    /**
     * FR63, BR31, BR33: Staff approves a reservation request.
     * Checks slot capacity, transitions requested -> confirmed, records reviewer and timestamp.
     */
    public function approve(Reservation $reservation, Staff $staff): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Confirmed);

            $slot = SlotCapacity::whereKey($locked->slot_id)->lockForUpdate()->firstOrFail();

            // Check if current booked covers for this slot exceed capacity
            $currentBooked = $this->availability->getBookedCovers($slot->slot_id, $locked->booking_date);
            if ($currentBooked > $slot->max_covers) {
                throw ValidationException::withMessages([
                    'capacity' => 'Approving this reservation would exceed slot capacity.',
                ]);
            }

            $locked->forceFill([
                'status' => ReservationStatus::Confirmed,
                'reviewed_by_staff_id' => $staff->staff_id,
                'reviewed_at' => now(),
            ])->save();

            $this->auditLogger->log($staff, 'reservation_approved', $locked);

            return $locked;
        });
    }

    /**
     * FR63: Staff declines a reservation request with optional reason.
     * Transitions requested -> declined, unlinks any tables, records reviewer.
     */
    public function decline(Reservation $reservation, Staff $staff, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff, $reason) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Declined);

            $locked->forceFill([
                'status' => ReservationStatus::Declined,
                'decline_reason' => $reason,
                'reviewed_by_staff_id' => $staff->staff_id,
                'reviewed_at' => now(),
            ])->save();

            $this->unlinkTables($locked, VisitCloseReason::Cancelled);

            $this->auditLogger->log($staff, 'reservation_declined', $locked, $reason);

            return $locked;
        });
    }

    /**
     * FR67, BR38, BR64: Customer cancels their reservation.
     * Flags late cancellation if within 2 hours of booking time; unlinks tables.
     */
    public function cancelByCustomer(Reservation $reservation, Customer $customer): Reservation
    {
        if ($reservation->customer_id !== $customer->customer_id) {
            throw ValidationException::withMessages([
                'reservation' => 'You do not own this reservation.',
            ]);
        }

        return DB::transaction(function () use ($reservation, $customer) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Cancelled);

            $isLate = $this->isLateCancellation($locked);

            $locked->forceFill([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => 'customer',
                'is_late_cancellation' => $isLate,
            ])->save();

            $this->unlinkTables($locked, VisitCloseReason::Cancelled);

            $this->auditLogger->log($customer, 'reservation_cancelled', $locked, $isLate ? 'Late cancellation' : null);

            return $locked;
        });
    }

    /**
     * FR67, BR38, BR64: Staff cancels a reservation.
     * Records late cancellation if within 2 hours; unlinks tables.
     */
    public function cancelByStaff(Reservation $reservation, Staff $staff, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff, $reason) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Cancelled);

            $isLate = $this->isLateCancellation($locked);

            $locked->forceFill([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => 'staff',
                'is_late_cancellation' => $isLate,
                'decline_reason' => $reason ?? $locked->decline_reason,
            ])->save();

            $this->unlinkTables($locked, VisitCloseReason::Cancelled);

            $this->auditLogger->log($staff, 'reservation_cancelled', $locked, $reason);

            return $locked;
        });
    }

    /**
     * FR66, BR36, BR38, BR64: Customer or Staff updates a reservation.
     * For customer: changes to date, time or party return booking to requested and unlink tables,
     * and are locked within 2 hours of booking. Notes stay editable anytime.
     * For staff: can always edit date/time/party/notes.
     */
    public function update(Reservation $reservation, Staff|Customer $actor, array $data): Reservation
    {
        if ($actor instanceof Customer) {
            if ($reservation->customer_id !== $actor->customer_id) {
                throw ValidationException::withMessages([
                    'reservation' => 'You do not own this reservation.',
                ]);
            }

            if (! in_array($reservation->status, [ReservationStatus::Requested, ReservationStatus::Confirmed], true)) {
                throw ValidationException::withMessages([
                    'reservation' => 'Only requested or confirmed reservations can be modified.',
                ]);
            }
        }

        $dateChanged = isset($data['booking_date'])
            && Carbon::parse($data['booking_date'])->toDateString() !== $reservation->booking_date->toDateString();

        $partyChanged = isset($data['party_size'])
            && (int) $data['party_size'] !== $reservation->party_size;

        $timeChanged = false;
        if (isset($data['slot_id']) && (int) $data['slot_id'] !== $reservation->slot_id) {
            $timeChanged = true;
        } elseif (isset($data['booking_time']) && substr($data['booking_time'], 0, 5) !== substr($reservation->booking_time, 0, 5)) {
            $timeChanged = true;
        }

        $coreChanged = $dateChanged || $timeChanged || $partyChanged;

        if ($actor instanceof Customer && $coreChanged) {
            if ($this->isLockedForCustomer($reservation)) {
                throw ValidationException::withMessages([
                    'booking' => 'Reservation date, time and party size cannot be changed within 2 hours of the booking.',
                ]);
            }
        }

        $newDate = $dateChanged
            ? Carbon::parse($data['booking_date'])->startOfDay()
            : $reservation->booking_date->copy()->startOfDay();

        $newPartySize = $partyChanged
            ? (int) $data['party_size']
            : $reservation->party_size;

        if ($coreChanged) {
            if ($newPartySize < 1) {
                throw ValidationException::withMessages([
                    'party_size' => 'Party size must be at least 1 guest.',
                ]);
            }

            if ($actor instanceof Customer && $newPartySize > $this->availability->getMaxPartyOnline()) {
                throw ValidationException::withMessages([
                    'party_size' => 'Online bookings are limited to '.$this->availability->getMaxPartyOnline().' guests.',
                ]);
            }

            if ($this->availability->isClosedWeekday($newDate)) {
                throw ValidationException::withMessages([
                    'booking_date' => 'The venue is closed on this day.',
                ]);
            }

            if ($actor instanceof Customer && $newDate->isAfter(today()->addDays($this->availability->getMaxDaysAhead()))) {
                throw ValidationException::withMessages([
                    'booking_date' => 'Reservations can only be made up to '.$this->availability->getMaxDaysAhead().' days in advance.',
                ]);
            }

            if ($newDate->isBefore(today())) {
                throw ValidationException::withMessages([
                    'booking_date' => 'Reservations cannot be made in the past.',
                ]);
            }
        }

        // Slot resolution
        $newSlot = $reservation->slot;
        if (! empty($data['slot_id'])) {
            $newSlot = SlotCapacity::find($data['slot_id']);
        } elseif (! empty($data['booking_time'])) {
            $timeStr = substr($data['booking_time'], 0, 5);
            $newSlot = SlotCapacity::where('slot_time', $timeStr)
                ->orWhere('slot_time', $timeStr.':00')
                ->first();
        }

        if (! $newSlot || ! $newSlot->is_active) {
            throw ValidationException::withMessages([
                'slot' => 'The selected time slot is invalid or inactive.',
            ]);
        }

        if ($coreChanged && $actor instanceof Customer) {
            $newBookedAt = Carbon::parse($newDate->format('Y-m-d').' '.$newSlot->slot_time);
            if ($newBookedAt->isBefore(now()->addHours($this->availability->getMinLeadHours()))) {
                throw ValidationException::withMessages([
                    'booking_time' => 'Reservations require at least '.$this->availability->getMinLeadHours().' hours lead time.',
                ]);
            }
        }

        if (isset($data['special_requests']) && strlen($data['special_requests']) > 500) {
            throw ValidationException::withMessages([
                'special_requests' => 'Special requests cannot exceed 500 characters.',
            ]);
        }

        return DB::transaction(function () use (
            $reservation,
            $actor,
            $coreChanged,
            $newDate,
            $newSlot,
            $newPartySize,
            $data
        ) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            if ($coreChanged) {
                SlotCapacity::whereKey($newSlot->slot_id)->lockForUpdate()->first();

                // If staying in the same slot on the same date:
                if ($locked->slot_id === $newSlot->slot_id && $locked->booking_date->toDateString() === $newDate->toDateString()) {
                    $delta = $newPartySize - $locked->party_size;
                    if ($delta > 0 && ! $this->availability->hasSlotCapacity($newSlot, $newDate, $delta)) {
                        throw ValidationException::withMessages([
                            'slot' => 'No capacity for the additional guests in this time slot.',
                        ]);
                    }
                } else {
                    // Moving to different slot or date: check full party size on new slot
                    if (! $this->availability->hasSlotCapacity($newSlot, $newDate, $newPartySize)) {
                        throw ValidationException::withMessages([
                            'slot' => 'No availability for the selected time slot and party size.',
                        ]);
                    }
                }

                // If customer made core change to confirmed booking, return to requested (BR36)
                if ($actor instanceof Customer && $locked->status === ReservationStatus::Confirmed) {
                    $locked->status->ensureCanTransitionTo(ReservationStatus::Requested);
                    $locked->forceFill(['status' => ReservationStatus::Requested]);
                }

                // Unlink tables on core change (BR36, BR64)
                $this->unlinkTables($locked, VisitCloseReason::Unassigned);

                $locked->booking_date = $newDate->toDateString();
                $locked->booking_time = $newSlot->slot_time;
                $locked->slot_id = $newSlot->slot_id;
                $locked->party_size = $newPartySize;
            }

            if (array_key_exists('special_requests', $data)) {
                $locked->special_requests = $data['special_requests'];
            }

            $locked->save();

            $this->auditLogger->log($actor, 'reservation_updated', $locked);

            return $locked;
        });
    }

    /**
     * BR37, 5.8: Expire an unreviewed reservation request.
     */
    public function expire(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Expired);

            $locked->forceFill([
                'status' => ReservationStatus::Expired,
            ])->save();

            $this->unlinkTables($locked, VisitCloseReason::Cancelled);

            $this->auditLogger->log(null, 'reservation_expired', $locked);

            return $locked;
        });
    }

    /**
     * BR64: Unassigning tables closes their open visit rows with given reason;
     * a table Reserved for that booking returns to Available.
     */
    public function unlinkTables(Reservation $reservation, VisitCloseReason $reason): void
    {
        $visits = $reservation->visits()
            ->whereNull('closed_at')
            ->with('restaurantTable')
            ->get();

        foreach ($visits as $visit) {
            $visit->update([
                'closed_at' => now(),
                'close_reason' => $reason,
            ]);

            $table = $visit->restaurantTable;
            if ($table && $table->status === TableStatus::Reserved) {
                $this->tableStatus->transition($table, TableStatus::Available);
            }
        }
    }

    /** BR36: Customer date/time/party edits locked within 2 hours of booking time. */
    public function isLockedForCustomer(Reservation $reservation): bool
    {
        return now()->greaterThanOrEqualTo(
            $this->bookedAt($reservation)->subHours(2)
        );
    }

    /** BR38: Cancellation < 2 hours before booking is recorded as late. */
    public function isLateCancellation(Reservation $reservation): bool
    {
        return now()->greaterThanOrEqualTo(
            $this->bookedAt($reservation)->subHours(2)
        );
    }

    /** Generates a unique reference code like CR-7K2P9Q. */
    public function generateReferenceCode(): string
    {
        do {
            $code = 'CR-'.strtoupper(Str::random(6));
        } while (Reservation::where('reference_code', $code)->exists());

        return $code;
    }

    /** Carbon parsed booking date and time in Australia/Melbourne. */
    public function bookedAt(Reservation $reservation): Carbon
    {
        $dateStr = $reservation->booking_date instanceof Carbon
            ? $reservation->booking_date->format('Y-m-d')
            : substr((string) $reservation->booking_date, 0, 10);

        $timeStr = is_string($reservation->booking_time)
            ? substr($reservation->booking_time, 0, 5)
            : '00:00';

        return Carbon::parse($dateStr.' '.$timeStr);
    }

    /** 06.4.16: the visit row is the only link between a table and a reservation. */
    public function assignedReservation(RestaurantTable $table): ?Reservation
    {
        return $table->visits()
            ->whereNull('closed_at')
            ->whereNotNull('reservation_id')
            ->latest('visit_id')
            ->first()?->reservation;
    }

    /** BR02, BR03: the holder, inside [booking - unlock, booking + grace]. */
    public function isHolderWithinWindow(Reservation $reservation, ?Customer $customer): bool
    {
        if ($customer === null || $reservation->customer_id !== $customer->customer_id) {
            return false;
        }

        if ($reservation->status !== ReservationStatus::Confirmed) {
            return false;
        }

        $bookedAt = $this->bookedAt($reservation);
        $before = (int) (Setting::find('holder_unlock_before_minutes')?->setting_value ?? 15);
        $grace = (int) (Setting::find('reservation_grace_minutes')?->setting_value ?? 15);

        return now()->betweenIncluded(
            $bookedAt->copy()->subMinutes($before),
            $bookedAt->copy()->addMinutes($grace),
        );
    }

    /** BR04: reuse the assigned-but-unopened visit; open it and occupy the table. */
    public function seatOnHolderScan(RestaurantTable $table, Reservation $reservation): Visit
    {
        $visit = $table->visits()
            ->whereNull('closed_at')
            ->where('reservation_id', $reservation->reservation_id)
            ->latest('visit_id')
            ->first();

        if ($visit === null) {
            $visit = $table->visits()->create([
                'reservation_id' => $reservation->reservation_id,
                'guest_count' => $reservation->party_size,
                'opened_at' => now(),
            ]);
        } elseif ($visit->opened_at === null) {
            $visit->update(['opened_at' => now()]);
        }

        $reservation->status->ensureCanTransitionTo(ReservationStatus::Seated);
        $reservation->forceFill([
            'status' => ReservationStatus::Seated,
            'seated_at' => now(),
        ])->save();

        if ($table->status !== TableStatus::Occupied) {
            $this->tableStatus->transition($table, TableStatus::Occupied);
        }

        return $visit;
    }

    /** BR41: 'Jane D' — never the full surname. */
    public function holderDisplayName(Reservation $reservation): string
    {
        $name = trim($reservation->customer?->full_name ?? $reservation->guest_name ?? '');

        if ($name === '') {
            return 'a guest';
        }

        $parts = preg_split('/\s+/', $name);
        $first = array_shift($parts);

        return $parts === [] ? $first : $first.' '.strtoupper(substr((string) end($parts), 0, 1));
    }
}
