<?php

namespace Tests\Feature;

use DateTime;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * NFR13: timezone, AUD currency helper, Australian date format.
 */
class LocalisationTest extends TestCase
{
    public function test_app_timezone_is_australia_melbourne(): void
    {
        $this->assertSame('Australia/Melbourne', config('app.timezone'));
        $this->assertSame('Australia/Melbourne', date_default_timezone_get());
    }

    public function test_money_directive_renders_formatted_currency(): void
    {
        $html = Blade::render('@money($amount)', ['amount' => '12.5']);

        $this->assertSame('$12.50', trim($html));
    }

    public function test_au_date_directive_renders_australian_format(): void
    {
        $html = Blade::render('@auDate($date)', ['date' => new DateTime('2026-12-31')]);

        $this->assertSame('31/12/2026', trim($html));
    }

    public function test_au_date_time_directive_renders_australian_format(): void
    {
        $html = Blade::render('@auDateTime($date)', ['date' => new DateTime('2026-12-31 14:05:00')]);

        $this->assertSame('31/12/2026 2:05 PM', trim($html));
    }
}
