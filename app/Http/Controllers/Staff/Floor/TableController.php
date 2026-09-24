<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignReservationRequest;
use App\Http\Requests\Staff\ClearTablesRequest;
use App\Http\Requests\Staff\SeatReservationRequest;
use App\Http\Requests\Staff\SeatTableRequest;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\ReservationService;
use App\Services\TableStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

class TableController extends Controller
{
    public function __construct(
        private TableStatusService $tableStatus,
        private ReservationService $reservations,
    ) {}

    public function seat(SeatTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $this->tableStatus->seatWalkIn($table, $request->user('staff'), $request->validated('guest_count'));

        return back()->with('status', 'table-seated');
    }

    public function seatReservation(SeatReservationRequest $request, RestaurantTable $table): RedirectResponse
    {
        $reservation = Reservation::findOrFail($request->validated('reservation_id'));

        $this->reservations->seatReservation($reservation, $request->user('staff'));

        return back()
            ->with('status', 'reservation-seated')
            ->with('message', "Reservation {$reservation->reference_code} seated successfully.");
    }

    public function assignReservation(AssignReservationRequest $request, RestaurantTable $table): RedirectResponse
    {
        $reservation = Reservation::findOrFail($request->validated('reservation_id'));

        $tableIds = $this->assignedTableIds($reservation)
            ->push($table->table_id)
            ->unique()
            ->all();

        $this->reservations->assignTables($reservation, $tableIds, $request->user('staff'));

        return back()
            ->with('status', 'reservation-assigned')
            ->with('message', "Table {$table->table_number} given to {$reservation->reference_code}.");
    }

    public function releaseReservation(AssignReservationRequest $request, RestaurantTable $table): RedirectResponse
    {
        $reservation = Reservation::findOrFail($request->validated('reservation_id'));

        $remaining = $this->assignedTableIds($reservation)
            ->reject(fn (int $tableId) => $tableId === $table->table_id)
            ->all();

        if ($remaining === []) {
            $this->reservations->unassignTables($reservation, $request->user('staff'));
        } else {
            $this->reservations->assignTables($reservation, $remaining, $request->user('staff'));
        }

        return back()
            ->with('status', 'reservation-released')
            ->with('message', "Table {$table->table_number} released from {$reservation->reference_code}.");
    }

    /**
     * @return Collection<int, int>
     */
    private function assignedTableIds(Reservation $reservation): Collection
    {
        return $reservation->visits()
            ->whereNull('closed_at')
            ->pluck('table_id');
    }

    public function clear(ClearTablesRequest $request): RedirectResponse
    {
        $tables = RestaurantTable::whereIn('table_id', $request->validated('table_ids'))->get();

        $this->tableStatus->clearGroup($tables, $request->user('staff'), $request->boolean('force'));

        return back()->with('status', 'table-cleared');
    }
}
