<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ClearTablesRequest;
use App\Http\Requests\Staff\SeatTableRequest;
use App\Models\RestaurantTable;
use App\Services\TableStatusService;
use Illuminate\Http\RedirectResponse;

/** FR17, FR18, UC17, UC18, S23. */
class TableController extends Controller
{
    public function __construct(private TableStatusService $tableStatus) {}

    public function seat(SeatTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $tableIds = array_unique([$table->table_id, ...$request->validated('other_table_ids', [])]);
        $tables = RestaurantTable::whereIn('table_id', $tableIds)->get();

        $this->tableStatus->seatGroup($tables, $request->user('staff'), $request->validated('guest_count'));

        return back()->with('status', 'table-seated');
    }

    public function clear(ClearTablesRequest $request): RedirectResponse
    {
        $tables = RestaurantTable::whereIn('table_id', $request->validated('table_ids'))->get();

        $this->tableStatus->clearGroup($tables, $request->user('staff'), $request->boolean('force'));

        return back()->with('status', 'table-cleared');
    }
}
