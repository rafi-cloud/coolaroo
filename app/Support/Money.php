<?php

namespace App\Support;

class Money
{
    public static function format(string|int|float $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    public static function gst(string|int|float $totalAmount): string
    {
        return number_format(((float) $totalAmount) / 11, 2, '.', '');
    }
}
