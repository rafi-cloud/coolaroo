<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OverrideTableStatusRequest;
use App\Http\Requests\Admin\StoreTableRequest;
use App\Http\Requests\Admin\UpdateTableRequest;
use App\Models\RestaurantTable;
use App\Services\TableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableController extends Controller
{
    public function __construct(private TableService $tables)
    {
    }

    public function index(): View
    {
        return view('admin.tables.index', ['tables' => RestaurantTable::orderBy('table_number')->get()]);
    }

    public function create(): View
    {
        return view('admin.tables.create');
    }

    public function store(StoreTableRequest $request): RedirectResponse
    {
        $this->tables->create($request->validated());

        return redirect()->route('admin.tables.index')->with('status', 'table-created');
    }

    public function edit(RestaurantTable $table): View
    {
        return view('admin.tables.edit', ['table' => $table]);
    }

    public function update(UpdateTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $warning = $this->tables->update($table, $request->validated());

        return redirect()->route('admin.tables.index')
            ->with('status', 'table-updated')
            ->with('warning', $warning);
    }

    public function deactivate(Request $request, RestaurantTable $table): RedirectResponse
    {
        $this->tables->deactivate($table, $request->user('staff'), $request->string('reason')->value() ?: null);

        return redirect()->route('admin.tables.index')->with('status', 'table-deactivated');
    }

    public function reactivate(Request $request, RestaurantTable $table): RedirectResponse
    {
        $this->tables->reactivate($table, $request->user('staff'));

        return redirect()->route('admin.tables.index')->with('status', 'table-reactivated');
    }

    public function overrideStatus(OverrideTableStatusRequest $request, RestaurantTable $table): RedirectResponse
    {
        $this->tables->overrideStatus(
            $table,
            TableStatus::from($request->validated('status')),
            $request->user('staff'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.tables.index')->with('status', 'table-status-overridden');
    }
}
