<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['opening_time', 'time', '11:00'],
            ['closing_time', 'time', '23:00'],
            ['qr_stock_buffer_multiplier', 'int', '5'],
            ['reservation_min_lead_hours', 'int', '2'],
            ['reservation_max_party_online', 'int', '10'],
            ['reservation_duration_1_2', 'int', '90'],
            ['reservation_duration_3_6', 'int', '120'],
            ['reservation_duration_7_plus', 'int', '150'],
            ['reservation_request_expiry_minutes', 'int', '60'],
            ['reservation_reminder_hours', 'int', '24'],
            ['late_cancellation_hours', 'int', '2'],
            ['holder_unlock_before_minutes', 'int', '15'],
            ['reservation_grace_minutes', 'int', '15'],
            ['reserved_switch_before_minutes', 'int', '30'],
            ['unassigned_admin_alert_minutes', 'int', '15'],
            ['no_show_expiry_months', 'int', '12'],
            ['regular_badge_visits', 'int', '3'],
            ['table_idle_autoclear_minutes', 'int', '45'],
            ['avg_ticket_minutes_kitchen', 'int', '8'],
            ['avg_ticket_minutes_bar', 'int', '3'],
            ['public_rating_min_count', 'int', '10'],
            ['call_waiter_cooldown_seconds', 'int', '120'],
            ['staff_session_timeout_minutes', 'int', '30'],
            ['closed_weekdays', 'string', '1'],
            ['reservation_max_days_ahead', 'int', '60'],
            ['venue_name', 'string', 'Coolaroo Restaurant & Bistro'],
            ['venue_address', 'string', 'xxx Sydney Road, Coolaroo VIC 3048'],
            ['venue_phone', 'string', '(03) 9302 4453'],
            ['venue_email', 'string', 'bookings@coolaroo.com.au'],
            ['social_facebook', 'string', ''],
            ['social_instagram', 'string', ''],
            ['social_x', 'string', ''],
            ['social_tiktok', 'string', ''],
            ['social_whatsapp', 'string', ''],
            ['qr_ordering_enabled', 'bool', '1'],
            ['reservations_online_enabled', 'bool', '1'],
            ['ai_enabled', 'bool', '1'],
        ];

        foreach ($settings as [$key, $type, $value]) {
            Setting::create([
                'setting_key' => $key,
                'setting_value' => $value,
                'value_type' => $type,
            ]);
        }
    }
}
