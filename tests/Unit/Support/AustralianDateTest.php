<?php

namespace Tests\Unit\Support;

use App\Support\AustralianDate;
use DateTime;
use PHPUnit\Framework\TestCase;

class AustralianDateTest extends TestCase
{
    public function test_date_is_day_month_year(): void
    {
        $this->assertSame('31/12/2026', AustralianDate::date(new DateTime('2026-12-31')));
    }

    public function test_date_time_is_day_month_year_then_twelve_hour_time(): void
    {
        $this->assertSame(
            '31/12/2026 2:05 PM',
            AustralianDate::dateTime(new DateTime('2026-12-31 14:05:00')),
        );
    }
}
