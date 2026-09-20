<?php

namespace App\Http\Controllers\Staff\Kds;

use App\Http\Controllers\Controller;
use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Services\MenuAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** FR29, BR14, UC29, S31. */
class AvailabilityController extends Controller
{
    public function __construct(private MenuAvailabilityService $availability)
    {
    }

    public function toggleMenuItem(Request $request, MenuItem $menuItem): RedirectResponse
    {
        Gate::authorize('toggleAvailability', $menuItem);

        $this->availability->toggleMenuItem($menuItem, $request->user('staff'));

        return back()->with('status', 'availability-toggled');
    }

    public function toggleAddOnOption(Request $request, AddOnOption $option): RedirectResponse
    {
        Gate::authorize('toggleAvailability', $option);

        $this->availability->toggleAddOnOption($option, $request->user('staff'));

        return back()->with('status', 'availability-toggled');
    }
}
