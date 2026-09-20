<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemSizeRequest;
use App\Http\Requests\Admin\UpdateMenuItemSizeRequest;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Services\MenuItemSizeService;
use Illuminate\Http\RedirectResponse;

class MenuItemSizeController extends Controller
{
    public function __construct(private MenuItemSizeService $sizes) {}

    public function store(StoreMenuItemSizeRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $this->sizes->create($menuItem, $request->validated());

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'size-created');
    }

    public function update(UpdateMenuItemSizeRequest $request, MenuItem $menuItem, MenuItemSize $size): RedirectResponse
    {
        $this->sizes->update($size, $request->validated());

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'size-updated');
    }

    public function destroy(MenuItem $menuItem, MenuItemSize $size): RedirectResponse
    {
        $this->sizes->delete($size);

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'size-deleted');
    }
}
