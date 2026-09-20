<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\TrustService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private readonly TrustService $trustService,
    ) {}

    /**
     * FR103, UC38, S38: Searchable customer list with trust badges.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $query = Customer::query()->withCount([
            'reservations',
            'reservations as completed_reservations_count' => fn ($q) => $q->where('status', ReservationStatus::Completed),
            'reservations as uncleared_no_shows_count' => fn ($q) => $q->whereNotNull('no_show_at')->whereNull('no_show_cleared_at'),
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        foreach ($customers as $customer) {
            $customer->trust_badge = $this->trustService->badge($customer);
        }

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    /**
     * FR09, UC38, S38: View customer profile, trust breakdown and reservation history.
     */
    public function show(Customer $customer): View
    {
        $profile = $this->trustService->profile($customer);

        $reservations = $customer->reservations()
            ->with(['slot', 'visits.restaurantTable', 'noShowClearedBy', 'noShowBy'])
            ->orderByDesc('booking_date')
            ->orderByDesc('booking_time')
            ->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'profile' => $profile,
            'reservations' => $reservations,
        ]);
    }
}
