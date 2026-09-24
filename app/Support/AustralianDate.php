<?php

namespace App\Support;

use DateTimeInterface;

class AustralianDate
{
    public const DATE = 'd/m/Y';

    public const DATE_TIME = 'd/m/Y g:i A';

    public const TIME = 'g:i A';

    public static function date(DateTimeInterface $date): string
    {
        return $date->format(self::DATE);
    }

    /** Clock time alone, for live screens where the date is always today. */
    public static function time(DateTimeInterface $date): string
    {
        return $date->format(self::TIME);
    }

    public static function dateTime(DateTimeInterface $date): string
    {
        return $date->format(self::DATE_TIME);
    }
}
