<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('staff.profile', ['staff' => $request->user('staff')]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $staff = $request->user('staff');

        $staff->update([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
        ]);

        if ($request->filled('password')) {
            $staff->update(['password_hash' => $data['password']]);
        }

        return back()->with('status', 'profile-updated');
    }
}
