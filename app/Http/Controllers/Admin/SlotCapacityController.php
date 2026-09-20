<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSlotCapacityRequest;
use App\Http\Requests\Admin\UpdateSlotCapacityRequest;
use App\Models\SlotCapacity;
use App\Services\SlotCapacityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotCapacityController extends Controller
{
    public function __construct(private SlotCapacityService $slots) {}

    public function index(): View
    {
        return view('admin.slots.index', ['slots' => SlotCapacity::orderBy('slot_time')->get()]);
    }

    public function create(): View
    {
        return view('admin.slots.create');
    }

    public function store(StoreSlotCapacityRequest $request): RedirectResponse
    {
        $this->slots->create($request->validated(), $request->user('staff'));

        return redirect()->route('admin.slots.index')->with('status', 'slot-created');
    }

    public function edit(SlotCapacity $slot): View
    {
        return view('admin.slots.edit', ['slot' => $slot]);
    }

    public function update(UpdateSlotCapacityRequest $request, SlotCapacity $slot): RedirectResponse
    {
        $this->slots->update($slot, $request->validated(), $request->user('staff'));

        return redirect()->route('admin.slots.index')->with('status', 'slot-updated');
    }

    public function destroy(Request $request, SlotCapacity $slot): RedirectResponse
    {
        $this->slots->delete($slot, $request->user('staff'));

        return redirect()->route('admin.slots.index')->with('status', 'slot-deleted');
    }
}
