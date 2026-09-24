<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function index(): View
    {
        $settings = $this->settingService->all();

        $closedWeekdays = array_filter(
            array_map('trim', explode(',', (string) ($settings['closed_weekdays'] ?? '1')))
        );

        return view('admin.settings.index', [
            'settings' => $settings,
            'closedWeekdays' => $closedWeekdays,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'venue_name' => ['required', 'string', 'max:100'],
            'venue_address' => ['required', 'string', 'max:255'],
            'venue_phone' => ['required', 'string', 'max:30'],
            'venue_email' => ['required', 'email', 'max:100'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_x' => ['nullable', 'string', 'max:255'],
            'social_tiktok' => ['nullable', 'string', 'max:255'],
            'social_whatsapp' => ['nullable', 'string', 'max:255'],

            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'closed_weekdays' => ['nullable', 'array'],
            'closed_weekdays.*' => ['integer', 'between:1,7'],

            'reservation_max_days_ahead' => ['required', 'integer', 'between:1,365'],
            'reservation_min_lead_hours' => ['required', 'integer', 'between:0,72'],
            'reservation_max_party_online' => ['required', 'integer', 'between:1,50'],
            'reservation_duration_1_2' => ['required', 'integer', 'between:15,300'],
            'reservation_duration_3_6' => ['required', 'integer', 'between:15,300'],
            'reservation_duration_7_plus' => ['required', 'integer', 'between:15,300'],
            'reservation_request_expiry_minutes' => ['required', 'integer', 'between:10,720'],
            'reservation_reminder_hours' => ['required', 'integer', 'between:1,168'],
            'late_cancellation_hours' => ['required', 'integer', 'between:0,48'],
            'holder_unlock_before_minutes' => ['required', 'integer', 'between:0,120'],
            'reservation_grace_minutes' => ['required', 'integer', 'between:1,120'],
            'reserved_switch_before_minutes' => ['required', 'integer', 'between:1,180'],
            'unassigned_admin_alert_minutes' => ['required', 'integer', 'between:1,180'],

            'qr_stock_buffer_multiplier' => ['required', 'integer', 'between:1,50'],
            'table_idle_autoclear_minutes' => ['required', 'integer', 'between:5,180'],
            'avg_ticket_minutes_kitchen' => ['required', 'integer', 'between:1,60'],
            'avg_ticket_minutes_bar' => ['required', 'integer', 'between:1,60'],
            'call_waiter_cooldown_seconds' => ['required', 'integer', 'between:10,600'],
            'staff_session_timeout_minutes' => ['required', 'integer', 'between:5,480'],
            'regular_badge_visits' => ['required', 'integer', 'between:1,50'],
            'no_show_expiry_months' => ['required', 'integer', 'between:1,60'],
            'public_rating_min_count' => ['required', 'integer', 'between:1,100'],

            'qr_ordering_enabled' => ['nullable', 'boolean'],
            'reservations_online_enabled' => ['nullable', 'boolean'],
            'ai_enabled' => ['nullable', 'boolean'],
        ]);

        $closed = $request->input('closed_weekdays', []);
        $validated['closed_weekdays'] = implode(',', array_filter(array_map('strval', $closed)));

        $validated['qr_ordering_enabled'] = $request->boolean('qr_ordering_enabled') ? '1' : '0';
        $validated['reservations_online_enabled'] = $request->boolean('reservations_online_enabled') ? '1' : '0';
        $validated['ai_enabled'] = $request->boolean('ai_enabled') ? '1' : '0';

        /**
         * @var Staff $admin
         */
        $admin = $request->user('staff');

        $this->settingService->updateMany($validated, $admin);

        return back()->with('status', 'Settings saved successfully.');
    }

    public function toggle(Request $request, string $key): RedirectResponse
    {
        /**
         * @var Staff $admin
         */
        $admin = $request->user('staff');

        try {
            $newBool = $this->settingService->toggleSwitch($key, $admin);
            $label = match ($key) {
                'qr_ordering_enabled' => 'QR Ordering',
                'reservations_online_enabled' => 'Online Reservations',
                'ai_enabled' => 'AI Assistant',
                default => $key,
            };

            $statusText = $newBool ? 'enabled' : 'paused';

            return back()->with('status', "{$label} is now {$statusText}.");
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['switch' => $e->getMessage()]);
        }
    }
}
