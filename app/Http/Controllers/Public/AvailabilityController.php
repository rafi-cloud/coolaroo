<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR61, BR32-BR35, BR58: Reservation availability API.
 */
class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::tomorrow('Australia/Melbourne')->toDateString());
        $partySize = max(1, (int) $request->query('party_size', 2));

        $result = $this->availabilityService->checkDateAvailability($date, $partySize, isOnline: true);

        return response()->json([
            'online_enabled' => $this->availabilityService->isOnlineReservationsEnabled(),
            'closed_weekdays' => $this->availabilityService->getClosedWeekdays(),
            'max_days_ahead' => $this->availabilityService->getMaxDaysAhead(),
            'max_party_online' => $this->availabilityService->getMaxPartyOnline(),
            'min_lead_hours' => $this->availabilityService->getMinLeadHours(),
            'availability' => $result,
        ]);
    }
}
