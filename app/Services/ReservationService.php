<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Events\ReservationAlert;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * FR64, BR31, BR33, BR58: Staff creates a phone booking.
     * Confirmed immediately; allowed even when online reservations paused.
     */
    public function createPhoneBooking(Staff $staff, array $data): Reservation
    {
        $partySize = (int) ($data['party_size'] ?? 0);
        if ($partySize < 1) {
            throw ValidationException::withMessages([
                'party_size' => 'Party size must be at least 1 guest.',
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

        $customerId = $data['customer_id'] ?? null;
        $guestName = $data['guest_name'] ?? null;
        $guestPhone = $data['guest_phone'] ?? null;

        if (! $customerId && (empty(trim((string) $guestName)) || empty(trim((string) $guestPhone)))) {
            throw ValidationException::withMessages([
                'guest' => 'Either an existing customer account or guest name and phone number is required.',
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

        if (isset($data['special_requests']) && strlen($data['special_requests']) > 500) {
            throw ValidationException::withMessages([
                'special_requests' => 'Special requests cannot exceed 500 characters.',
            ]);
        }

        return DB::transaction(function () use ($staff, $slot, $bookingDate, $partySize, $customerId, $guestName, $guestPhone, $data) {
            SlotCapacity::whereKey($slot->slot_id)->lockForUpdate()->first();

            if (! $this->availability->hasSlotCapacity($slot, $bookingDate, $partySize)) {
                throw ValidationException::withMessages([
                    'slot' => 'No availability for the selected time slot and party size.',
                ]);
            }

            $referenceCode = $this->generateReferenceCode();

            $reservation = new Reservation();
            $reservation->forceFill([
                'customer_id' => $customerId,
                'guest_name' => $customerId ? null : $guestName,
                'guest_phone' => $customerId ? null : $guestPhone,
                'slot_id' => $slot->slot_id,
                'reference_code' => $referenceCode,
                'booking_date' => $bookingDate->toDateString(),
                'booking_time' => $slot->slot_time,
                'party_size' => $partySize,
                'special_requests' => $data['special_requests'] ?? null,
                'status' => ReservationStatus::Confirmed,
                'created_by_staff_id' => $staff->staff_id,
                'reviewed_by_staff_id' => $staff->staff_id,
                'reviewed_at' => now(),
            ]);
            $reservation->save();

            $this->auditLogger->log($staff, 'reservation_phone_created', $reservation);

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
     * FR65, FR95, BR04, BR07, BR33, BR64: Assign or reassign tables to a reservation.
     * Checks active tables, total seats >= party_size, no overlapping reservation windows.
     * If assigned inside T-30, transitions Available tables to Reserved with place-sign alert.
     *
     * @param array<int> $tableIds
     * @return Collection<int, Visit>
     */
    public function assignTables(Reservation $reservation, array $tableIds, ?Staff $staff = null): Collection
    {
        if (empty($tableIds)) {
            throw ValidationException::withMessages([
                'table_ids' => 'At least one table must be selected for assignment.',
            ]);
        }

        return DB::transaction(function () use ($reservation, $tableIds, $staff) {
            $lockedRes = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $tables = RestaurantTable::whereIn('table_id', $tableIds)->lockForUpdate()->get();

            if ($tables->count() !== count(array_unique($tableIds))) {
                throw ValidationException::withMessages([
                    'table_ids' => 'One or more selected tables could not be found.',
                ]);
            }

            // BR07: Inactive tables cannot be assigned
            foreach ($tables as $table) {
                if (! $table->is_active) {
                    throw ValidationException::withMessages([
                        'table_ids' => "Table {$table->table_number} is inactive and cannot be assigned.",
                    ]);
                }
            }

            // BR33: Seats >= party size
            $totalSeats = $tables->sum('seat_capacity');
            if ($totalSeats < $lockedRes->party_size) {
                throw ValidationException::withMessages([
                    'table_ids' => "Selected tables seat {$totalSeats} guests, but the party size is {$lockedRes->party_size}.",
                ]);
            }

            // BR33: Overlap check per table
            $start1 = $this->bookedAt($lockedRes);
            $duration1 = $this->availability->getDurationMinutes($lockedRes->party_size);
            $end1 = $start1->copy()->addMinutes($duration1);

            foreach ($tables as $table) {
                $otherVisits = $table->visits()
                    ->whereNull('closed_at')
                    ->where('reservation_id', '!=', $lockedRes->reservation_id)
                    ->whereNotNull('reservation_id')
                    ->with('reservation')
                    ->get();

                foreach ($otherVisits as $otherVisit) {
                    $otherRes = $otherVisit->reservation;
                    if (! $otherRes || in_array($otherRes->status, [ReservationStatus::Cancelled, ReservationStatus::Declined, ReservationStatus::Completed, ReservationStatus::NoShow], true)) {
                        continue;
                    }

                    if ($otherRes->booking_date->toDateString() === $lockedRes->booking_date->toDateString()) {
                        $start2 = $this->bookedAt($otherRes);
                        $duration2 = $this->availability->getDurationMinutes($otherRes->party_size);
                        $end2 = $start2->copy()->addMinutes($duration2);

                        if ($start1->isBefore($end2) && $start2->isBefore($end1)) {
                            throw ValidationException::withMessages([
                                'table_ids' => "Table {$table->table_number} is already assigned to reservation {$otherRes->reference_code} at ".substr($otherRes->booking_time, 0, 5).".",
                            ]);
                        }
                    }
                }
            }

            // Unassign any previously assigned tables first (reassignment per FR95, BR64)
            $this->unlinkTables($lockedRes, VisitCloseReason::Unassigned);

            // Check if inside T-30 window (BR04)
            $isToday = $lockedRes->booking_date->isToday();
            $insideT30 = $isToday && now()->betweenIncluded(
                $start1->copy()->subMinutes(30),
                $start1->copy()->addMinutes(15)
            );

            $visits = collect();

            foreach ($tables as $table) {
                $visit = $table->visits()->create([
                    'reservation_id' => $lockedRes->reservation_id,
                    'guest_count' => $lockedRes->party_size,
                    'opened_at' => null, // BR04: opened_at NULL until occupied
                ]);
                $visits->push($visit);

                if ($insideT30) {
                    if ($table->status === TableStatus::Available) {
                        $this->tableStatus->transition($table, TableStatus::Reserved, $staff);
                        event(new ReservationAlert($lockedRes, ReservationAlert::PLACE_SIGN));
                    } elseif ($table->status === TableStatus::Occupied) {
                        event(new ReservationAlert($lockedRes, ReservationAlert::STILL_OCCUPIED));
                    }
                }
            }

            $this->auditLogger->log($staff, 'reservation_tables_assigned', $lockedRes);

            return $visits;
        });
    }

    /**
     * FR95, BR64: Unassign all tables linked to a reservation.
     * Closes visit rows with close_reason = unassigned; returns Reserved tables to Available.
     */
    public function unassignTables(Reservation $reservation, ?Staff $staff = null): void
    {
        DB::transaction(function () use ($reservation, $staff) {
            $lockedRes = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $this->unlinkTables($lockedRes, VisitCloseReason::Unassigned);

            $this->auditLogger->log($staff, 'reservation_tables_unassigned', $lockedRes);
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

    /**
     * FR69, BR03, BR04: Staff seats a reservation.
     * Opens all assigned visit rows and transitions linked tables to Occupied.
     */
    public function seatReservation(Reservation $reservation, ?Staff $staff = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Seated);

            $visits = $locked->visits()
                ->whereNull('closed_at')
                ->with('restaurantTable')
                ->lockForUpdate()
                ->get();

            if ($visits->isEmpty()) {
                throw ValidationException::withMessages([
                    'table' => 'Reservation must have tables assigned before seating.',
                ]);
            }

            foreach ($visits as $visit) {
                if ($visit->opened_at === null) {
                    $visit->update([
                        'opened_at' => now(),
                        'opened_by_staff_id' => $staff?->staff_id ?? $visit->opened_by_staff_id,
                    ]);
                }

                $table = $visit->restaurantTable;
                if ($table && $table->status !== TableStatus::Occupied) {
                    $this->tableStatus->transition($table, TableStatus::Occupied, $staff);
                }
            }

            $locked->forceFill([
                'status' => ReservationStatus::Seated,
                'seated_at' => now(),
            ])->save();

            $this->auditLogger->log($staff, 'reservation_seated', $locked);

            return $locked;
        });
    }

    /**
     * FR70, BR39: Staff marks a reservation as no-show after grace period expires.
     * Transitions reservation to NoShow, records staff and timestamp, closes visits with reason no_show,
     * and returns Reserved tables to Available.
     */
    public function markNoShow(Reservation $reservation, ?Staff $staff = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::NoShow);

            $graceMinutes = (int) (Setting::find('reservation_grace_minutes')?->setting_value ?? 15);
            $graceCutoff = $this->bookedAt($locked)->addMinutes($graceMinutes);

            if (now()->lt($graceCutoff)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot mark as no-show until the grace period has expired ({$graceMinutes} minutes after booking time).",
                ]);
            }

            $locked->forceFill([
                'status' => ReservationStatus::NoShow,
                'no_show_at' => now(),
                'no_show_by_staff_id' => $staff?->staff_id,
            ])->save();

            $this->unlinkTables($locked, VisitCloseReason::NoShow);

            $this->auditLogger->log($staff, 'reservation_no_show', $locked);

            return $locked;
        });
    }

    /**
     * FR10: Clear no-show flag on a reservation.
     * Records admin, reason and timestamp; audits action; recalculates trust badge on read.
     */
    public function clearNoShow(Reservation $reservation, Staff $admin, string $reason): Reservation
    {
        return DB::transaction(function () use ($reservation, $admin, $reason) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReservationStatus::NoShow || $locked->no_show_at === null) {
                throw ValidationException::withMessages([
                    'reservation' => 'Only reservations marked as no-show can have their no-show flag cleared.',
                ]);
            }

            if ($locked->no_show_cleared_at !== null) {
                throw ValidationException::withMessages([
                    'reservation' => 'This no-show flag has already been cleared.',
                ]);
            }

            $locked->forceFill([
                'no_show_cleared_by_staff_id' => $admin->staff_id,
                'no_show_cleared_at' => now(),
                'no_show_clear_reason' => $reason,
            ])->save();

            $this->auditLogger->log($admin, 'no_show_cleared', $locked, $reason);

            return $locked;
        });
    }

    /** BR04, FR69: reuse the assigned-but-unopened visits; open them and occupy all linked tables. */
    public function seatOnHolderScan(RestaurantTable $table, Reservation $reservation): Visit
    {
        return DB::transaction(function () use ($table, $reservation) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->firstOrFail();

            $locked->status->ensureCanTransitionTo(ReservationStatus::Seated);

            $visits = $locked->visits()
                ->whereNull('closed_at')
                ->with('restaurantTable')
                ->lockForUpdate()
                ->get();

            $primaryVisit = null;

            if ($visits->isEmpty()) {
                $primaryVisit = $table->visits()->create([
                    'reservation_id' => $locked->reservation_id,
                    'guest_count' => $locked->party_size,
                    'opened_at' => now(),
                ]);
                $visits = collect([$primaryVisit]);
            }

            foreach ($visits as $visit) {
                if ($visit->opened_at === null) {
                    $visit->update(['opened_at' => now()]);
                }

                if ($visit->table_id === $table->table_id) {
                    $primaryVisit = $visit;
                }

                $t = $visit->restaurantTable ?? $table;
                if ($t && $t->status !== TableStatus::Occupied) {
                    $this->tableStatus->transition($t, TableStatus::Occupied);
                }
            }

            $locked->forceFill([
                'status' => ReservationStatus::Seated,
                'seated_at' => now(),
            ])->save();

            $this->auditLogger->log(null, 'reservation_seated_holder_scan', $locked);

            return $primaryVisit ?? $visits->first();
        });
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
