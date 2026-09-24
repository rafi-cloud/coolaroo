<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\SlotCapacity;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AvailabilityService
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function isOnlineReservationsEnabled(): bool
    {
        return $this->settingService->getBool('reservations_online_enabled', true);
    }

    /**
     * @return array<int>
     */
    public function getClosedWeekdays(): array
    {
        $val = (string) $this->settingService->get('closed_weekdays', '1');
        if ($val === '') {
            return [];
        }

        return array_map('intval', array_filter(explode(',', $val), 'is_numeric'));
    }

    public function isClosedWeekday(CarbonInterface|string $date): bool
    {
        $carbon = is_string($date)
            ? Carbon::parse($date, 'Australia/Melbourne')
            : $date->copy()->setTimezone('Australia/Melbourne');

        return in_array($carbon->dayOfWeekIso, $this->getClosedWeekdays(), true);
    }

    public function getMaxDaysAhead(): int
    {
        return $this->settingService->getInt('reservation_max_days_ahead', 60);
    }

    public function getMinLeadHours(): int
    {
        return $this->settingService->getInt('reservation_min_lead_hours', 2);
    }

    public function getMaxPartyOnline(): int
    {
        return $this->settingService->getInt('reservation_max_party_online', 10);
    }

    public function getDurationMinutes(int $partySize): int
    {
        if ($partySize <= 2) {
            return $this->settingService->getInt('reservation_duration_1_2', 90);
        }

        if ($partySize <= 6) {
            return $this->settingService->getInt('reservation_duration_3_6', 120);
        }

        return $this->settingService->getInt('reservation_duration_7_plus', 150);
    }

    public function isDateWithinWindow(CarbonInterface|string $date): bool
    {
        $carbon = is_string($date)
            ? Carbon::parse($date, 'Australia/Melbourne')->startOfDay()
            : $date->copy()->setTimezone('Australia/Melbourne')->startOfDay();

        $today = Carbon::now('Australia/Melbourne')->startOfDay();
        $maxDate = $today->copy()->addDays($this->getMaxDaysAhead());

        return $carbon->gte($today) && $carbon->lte($maxDate);
    }

    public function isLeadTimeValid(CarbonInterface|string $date, string $slotTime): bool
    {
        $dateStr = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
        $slotDateTime = Carbon::parse("{$dateStr} {$slotTime}", 'Australia/Melbourne');
        $minAllowed = Carbon::now('Australia/Melbourne')->addHours($this->getMinLeadHours());

        return $slotDateTime->gte($minAllowed);
    }

    public function getBookedCovers(int $slotId, CarbonInterface|string $date, ?int $ignoreReservationId = null): int
    {
        $dateStr = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;

        $query = Reservation::query()
            ->where('slot_id', $slotId)
            ->whereDate('booking_date', $dateStr)
            ->whereIn('status', [
                ReservationStatus::Requested,
                ReservationStatus::Confirmed,
                ReservationStatus::Seated,
            ]);

        if ($ignoreReservationId !== null) {
            $query->where('reservation_id', '!=', $ignoreReservationId);
        }

        return (int) $query->sum('party_size');
    }

    public function hasSlotCapacity(
        int|SlotCapacity $slot,
        CarbonInterface|string $date,
        int $partySize,
        ?int $ignoreReservationId = null
    ): bool {
        $slotModel = $slot instanceof SlotCapacity ? $slot : SlotCapacity::find($slot);
        if (! $slotModel || ! $slotModel->is_active) {
            return false;
        }

        $booked = $this->getBookedCovers($slotModel->slot_id, $date, $ignoreReservationId);

        return ($slotModel->max_covers - $booked) >= $partySize;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getAvailableSlots(CarbonInterface|string $date, int $partySize, bool $isOnline = true): array
    {
        $dateStr = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;

        $slots = SlotCapacity::query()
            ->where('is_active', true)
            ->orderBy('slot_time')
            ->get();

        $result = [];

        foreach ($slots as $slot) {
            $slotTimeStr = substr($slot->slot_time, 0, 5);
            $leadTimeOk = ! $isOnline || $this->isLeadTimeValid($dateStr, $slot->slot_time);
            $bookedCovers = $this->getBookedCovers($slot->slot_id, $dateStr);
            $remainingCovers = max(0, $slot->max_covers - $bookedCovers);
            $hasCapacity = $remainingCovers >= $partySize;

            $isAvailable = $leadTimeOk && $hasCapacity;
            $reason = null;

            if (! $leadTimeOk) {
                $reason = 'lead_time';
            } elseif (! $hasCapacity) {
                $reason = 'capacity';
            }

            $result[] = [
                'slot_id' => $slot->slot_id,
                'slot_time' => $slotTimeStr,
                'max_covers' => $slot->max_covers,
                'booked_covers' => $bookedCovers,
                'remaining_covers' => $remainingCovers,
                'is_available' => $isAvailable,
                'unavailable_reason' => $reason,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function checkDateAvailability(CarbonInterface|string $date, int $partySize = 2, bool $isOnline = true): array
    {
        if ($isOnline && ! $this->isOnlineReservationsEnabled()) {
            return [
                'status' => 'paused',
                'message' => 'Online bookings are temporarily paused. Please call the venue directly.',
                'slots' => [],
            ];
        }

        $carbon = is_string($date)
            ? Carbon::parse($date, 'Australia/Melbourne')->startOfDay()
            : $date->copy()->setTimezone('Australia/Melbourne')->startOfDay();

        $today = Carbon::now('Australia/Melbourne')->startOfDay();
        $maxDays = $this->getMaxDaysAhead();

        if ($carbon->lt($today)) {
            return [
                'status' => 'past_date',
                'message' => 'Selected date is in the past.',
                'slots' => [],
            ];
        }

        if ($carbon->gt($today->copy()->addDays($maxDays))) {
            return [
                'status' => 'exceeds_max_days',
                'message' => "Reservations can only be made up to {$maxDays} days in advance.",
                'slots' => [],
            ];
        }

        if ($this->isClosedWeekday($carbon)) {
            return [
                'status' => 'closed',
                'message' => 'The restaurant is closed on this day.',
                'slots' => [],
            ];
        }

        if ($partySize < 1) {
            return [
                'status' => 'invalid_party',
                'message' => 'Party size must be at least 1 guest.',
                'slots' => [],
            ];
        }

        $maxParty = $this->getMaxPartyOnline();
        if ($isOnline && $partySize > $maxParty) {
            return [
                'status' => 'party_too_large',
                'message' => "Online bookings are available for up to {$maxParty} guests. For larger parties, please contact the venue.",
                'slots' => [],
            ];
        }

        $slots = $this->getAvailableSlots($carbon->toDateString(), $partySize, $isOnline);

        return [
            'status' => 'available',
            'date' => $carbon->toDateString(),
            'party_size' => $partySize,
            'duration_minutes' => $this->getDurationMinutes($partySize),
            'slots' => $slots,
        ];
    }
}
