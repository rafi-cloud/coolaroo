<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAddOnGroupRequest;
use App\Http\Requests\Admin\UpdateAddOnGroupRequest;
use App\Models\AddOnGroup;
use App\Models\MenuItem;
use App\Services\AddOnService;
use Illuminate\Http\RedirectResponse;

class AddOnGroupController extends Controller
{
    public function __construct(private AddOnService $addOns) {}

    public function store(StoreAddOnGroupRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $this->addOns->createGroup($menuItem, $request->validated());

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'group-created');
    }

    public function update(UpdateAddOnGroupRequest $request, MenuItem $menuItem, AddOnGroup $group): RedirectResponse
    {
        $this->addOns->updateGroup($group, $request->validated());

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'group-updated');
    }

    public function destroy(MenuItem $menuItem, AddOnGroup $group): RedirectResponse
    {
        $this->addOns->deleteGroup($group);

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'group-deleted');
    }
}
