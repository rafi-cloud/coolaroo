<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeactivateStaffRequest;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Services\StaffAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffAccountController extends Controller
{
    public function __construct(private StaffAccountService $staffAccounts) {}

    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => Staff::with('role')->orderBy('full_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.create', ['roles' => Role::all()]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->staffAccounts->create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role_id' => $data['role_id'],
            'password_hash' => $data['password'],
        ]);

        return redirect()->route('admin.staff.index')->with('status', 'staff-created');
    }

    public function edit(Staff $staff): View
    {
        return view('admin.staff.edit', ['staff' => $staff, 'roles' => Role::all()]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->staffAccounts->update($staff, $request->validated());

        return redirect()->route('admin.staff.index')->with('status', 'staff-updated');
    }

    public function deactivate(DeactivateStaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->staffAccounts->deactivate($staff, $request->user('staff'), $request->validated('reason'));

        return redirect()->route('admin.staff.index')->with('status', 'staff-deactivated');
    }

    public function reactivate(Request $request, Staff $staff): RedirectResponse
    {
        $this->staffAccounts->reactivate($staff, $request->user('staff'));

        return redirect()->route('admin.staff.index')->with('status', 'staff-reactivated');
    }
}
