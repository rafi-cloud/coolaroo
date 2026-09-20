<?php

namespace App\Services;

use App\Events\SettingSwitched;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Support\Facades\Cache;

/**
 * FR91, FR96, FR97, FR98, BR49, BR58: Settings management, cache, typed getters,
 * venue details, and operational switches.
 */
class SettingService
{
    private const CACHE_KEY = 'app_settings_map';
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Get all settings as a key => value map.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            try {
                return Setting::query()->pluck('setting_value', 'setting_key')->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key);

        return $val !== null ? (int) $val : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key);

        return $val !== null ? filter_var($val, FILTER_VALIDATE_BOOLEAN) : $default;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Set a setting value, invalidating the settings cache.
     */
    public function set(string $key, string $value, ?Staff $actor = null): Setting
    {
        $setting = Setting::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $value,
                'updated_by_staff_id' => $actor?->staff_id,
                'updated_at' => now(),
            ]
        );

        $this->clearCache();

        return $setting;
    }

    /**
     * FR91: Bulk update settings with change detection, audit logging, and switch broadcasting.
     *
     * @param array<string, mixed> $values
     * @return list<string> list of updated setting keys
     */
    public function updateMany(array $values, Staff $admin): array
    {
        $current = $this->all();
        $switches = ['qr_ordering_enabled', 'reservations_online_enabled', 'ai_enabled'];
        $changed = [];

        foreach ($values as $key => $newValue) {
            $newValue = (string) $newValue;
            $oldValue = (string) ($current[$key] ?? '');

            if ($oldValue !== $newValue) {
                $setting = Setting::updateOrCreate(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $newValue,
                        'updated_by_staff_id' => $admin->staff_id,
                        'updated_at' => now(),
                    ]
                );

                $this->auditLogger->log(
                    $admin,
                    'setting_update',
                    $setting,
                    "Updated {$key} from '{$oldValue}' to '{$newValue}'"
                );

                if (in_array($key, $switches, true)) {
                    event(new SettingSwitched($key, $newValue));
                }

                $changed[] = $key;
            }
        }

        if (! empty($changed)) {
            $this->clearCache();
        }

        return $changed;
    }

    /**
     * FR96, FR97, FR98, BR58, BR49: Toggle an operational switch.
     */
    public function toggleSwitch(string $key, Staff $admin): bool
    {
        $allowed = ['qr_ordering_enabled', 'reservations_online_enabled', 'ai_enabled'];
        if (! in_array($key, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid switch key: {$key}");
        }

        $current = $this->getBool($key, true);
        $newBool = ! $current;
        $newValue = $newBool ? '1' : '0';

        $setting = Setting::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $newValue,
                'updated_by_staff_id' => $admin->staff_id,
                'updated_at' => now(),
            ]
        );

        $this->auditLogger->log(
            $admin,
            'setting_update',
            $setting,
            "Switched {$key} to '{$newValue}'"
        );

        event(new SettingSwitched($key, $newValue));
        $this->clearCache();

        return $newBool;
    }

    /**
     * Venue information bundle for header, footer, AI, emails (FR91).
     *
     * @return array<string, mixed>
     */
    public function venue(): array
    {
        $all = $this->all();

        $opening = $all['opening_time'] ?? '11:00';
        $closing = $all['closing_time'] ?? '23:00';
        $closedWeekdays = (string) ($all['closed_weekdays'] ?? '1');

        return [
            'name' => $all['venue_name'] ?? 'Coolaroo Restaurant & Bistro',
            'address' => $all['venue_address'] ?? '412 Sydney Road, Coolaroo VIC 3048',
            'phone' => $all['venue_phone'] ?? '(03) 9302 4453',
            'email' => $all['venue_email'] ?? 'bookings@coolaroo.com.au',
            'opening_time' => $opening,
            'closing_time' => $closing,
            'closed_weekdays' => $closedWeekdays,
            'formatted_hours' => $this->formatOpeningHours($opening, $closing, $closedWeekdays),
            'socials' => [
                'facebook' => $all['social_facebook'] ?? '',
                'instagram' => $all['social_instagram'] ?? '',
                'x' => $all['social_x'] ?? '',
                'tiktok' => $all['social_tiktok'] ?? '',
                'whatsapp' => $all['social_whatsapp'] ?? '',
            ],
            'qr_ordering_enabled' => ($all['qr_ordering_enabled'] ?? '1') === '1',
            'reservations_online_enabled' => ($all['reservations_online_enabled'] ?? '1') === '1',
            'ai_enabled' => ($all['ai_enabled'] ?? '1') === '1',
        ];
    }

    /**
     * Format venue trading hours human-readably.
     * e.g., ["Tue to Sun, 11am to 11pm", "Monday: closed"]
     *
     * @return list<string>
     */
    private function formatOpeningHours(string $opening, string $closing, string $closedWeekdays): array
    {
        $openTime = date('ga', strtotime("2000-01-01 {$opening}"));
        $closeTime = date('ga', strtotime("2000-01-01 {$closing}"));

        $closedDaysList = array_values(array_filter(array_map('trim', explode(',', $closedWeekdays))));

        $dayNames = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        if (empty($closedDaysList)) {
            return ["Daily, {$openTime} to {$closeTime}"];
        }

        if ($closedDaysList === ['1']) {
            return [
                "Tue to Sun, {$openTime} to {$closeTime}",
                'Monday: closed',
            ];
        }

        $closedNames = array_map(fn ($d) => $dayNames[(int) $d] ?? "Day {$d}", $closedDaysList);

        return [
            "Open {$openTime} to {$closeTime}",
            implode(', ', $closedNames) . ': closed',
        ];
    }
}
