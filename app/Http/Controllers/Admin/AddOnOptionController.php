<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAddOnOptionRequest;
use App\Http\Requests\Admin\UpdateAddOnOptionRequest;
use App\Models\AddOnGroup;
use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Services\AddOnService;
use Illuminate\Http\RedirectResponse;

class AddOnOptionController extends Controller
{
    public function __construct(private AddOnService $addOns) {}

    public function store(StoreAddOnOptionRequest $request, MenuItem $menuItem, AddOnGroup $group): RedirectResponse
    {
        $this->addOns->createOption($group, $request->validated());

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'option-created');
    }

    public function update(UpdateAddOnOptionRequest $request, MenuItem $menuItem, AddOnGroup $group, AddOnOption $option): RedirectResponse
    {
        $this->addOns->updateOption($option, $request->validated());

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'option-updated');
    }

    public function destroy(MenuItem $menuItem, AddOnGroup $group, AddOnOption $option): RedirectResponse
    {
        $this->addOns->deleteOption($option);

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'option-deleted');
    }
}
