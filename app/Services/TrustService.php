<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;

/**
 * FR09, BR40: Customer Trust Profile & Badge calculation on read.
 */
class TrustService
{
    public const BADGE_FLAGGED = 'Flagged';
    public const BADGE_REGULAR = 'Regular';
    public const BADGE_NEW = 'New';

    /**
     * BR40: Badge calculated on read:
     * - Flagged if >= 1 uncleared no-show in last 12 months.
     * - Regular if >= 3 completed visits and not Flagged.
     * - Else New.
     */
    public function badge(?Customer $customer): string
    {
        if ($customer === null) {
            return self::BADGE_NEW;
        }

        $unclearedNoShows = $customer->reservations()
            ->whereNotNull('no_show_at')
            ->whereNull('no_show_cleared_at')
            ->where('no_show_at', '>=', now()->subMonths(12))
            ->count();

        if ($unclearedNoShows >= 1) {
            return self::BADGE_FLAGGED;
        }

        $completedVisits = $customer->reservations()
            ->where('status', ReservationStatus::Completed)
            ->count();

        if ($completedVisits >= 3) {
            return self::BADGE_REGULAR;
        }

        return self::BADGE_NEW;
    }

    /**
     * FR09: Full trust profile with badge and historical counts.
     *
     * @return array<string, mixed>
     */
    public function profile(?Customer $customer): array
    {
        if ($customer === null) {
            return [
                'customer_id' => null,
                'customer_name' => 'Guest',
                'email' => null,
                'phone' => null,
                'badge' => self::BADGE_NEW,
                'completed_visits_count' => 0,
                'cancellations_count' => 0,
                'late_cancellations_count' => 0,
                'uncleared_no_shows_count' => 0,
                'total_no_shows_count' => 0,
                'cleared_no_shows_count' => 0,
                'history' => [],
            ];
        }

        $badge = $this->badge($customer);

        $completedCount = $customer->reservations()
            ->where('status', ReservationStatus::Completed)
            ->count();

        $cancellationsCount = $customer->reservations()
            ->where('status', ReservationStatus::Cancelled)
            ->count();

        $lateCancellationsCount = $customer->reservations()
            ->where('status', ReservationStatus::Cancelled)
            ->where('is_late_cancellation', true)
            ->count();

        $unclearedNoShowsCount = $customer->reservations()
            ->whereNotNull('no_show_at')
            ->whereNull('no_show_cleared_at')
            ->where('no_show_at', '>=', now()->subMonths(12))
            ->count();

        $totalNoShowsCount = $customer->reservations()
            ->whereNotNull('no_show_at')
            ->count();

        $clearedNoShowsCount = $customer->reservations()
            ->whereNotNull('no_show_cleared_at')
            ->count();

        $history = $customer->reservations()
            ->with('slot')
            ->latest('booking_date')
            ->latest('booking_time')
            ->take(10)
            ->get()
            ->map(fn (Reservation $r) => [
                'reservation_id' => $r->reservation_id,
                'reference_code' => $r->reference_code,
                'booking_date' => $r->booking_date->format('Y-m-d'),
                'booking_time' => substr($r->booking_time, 0, 5),
                'party_size' => $r->party_size,
                'status' => $r->status->value,
                'is_late_cancellation' => (bool) $r->is_late_cancellation,
                'is_no_show' => (bool) $r->no_show_at,
                'no_show_cleared' => (bool) $r->no_show_cleared_at,
            ])
            ->toArray();

        return [
            'customer_id' => $customer->customer_id,
            'customer_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'badge' => $badge,
            'completed_visits_count' => $completedCount,
            'cancellations_count' => $cancellationsCount,
            'late_cancellations_count' => $lateCancellationsCount,
            'uncleared_no_shows_count' => $unclearedNoShowsCount,
            'total_no_shows_count' => $totalNoShowsCount,
            'cleared_no_shows_count' => $clearedNoShowsCount,
            'history' => $history,
        ];
    }
}
