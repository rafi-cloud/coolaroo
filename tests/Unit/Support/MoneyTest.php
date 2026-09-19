<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_format_adds_a_dollar_sign_and_two_decimals(): void
    {
        $this->assertSame('$12.50', Money::format('12.5'));
        $this->assertSame('$0.00', Money::format(0));
        $this->assertSame('$1,234.50', Money::format(1234.5));
    }

    public function test_gst_is_total_divided_by_eleven_rounded_to_cents(): void
    {
        $this->assertSame('10.00', Money::gst('110.00'));
        $this->assertSame('1.00', Money::gst('11.00'));
        $this->assertSame('9.09', Money::gst('100.00'));
    }
}
