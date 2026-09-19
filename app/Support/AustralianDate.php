<?php

namespace App\Support;

use DateTimeInterface;

class AustralianDate
{
    public const DATE = 'd/m/Y';

    public const DATE_TIME = 'd/m/Y g:i A';

    public static function date(DateTimeInterface $date): string
    {
        return $date->format(self::DATE);
    }

    public static function dateTime(DateTimeInterface $date): string
    {
        return $date->format(self::DATE_TIME);
    }
}
