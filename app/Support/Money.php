<?php

namespace App\Support;

class Money
{
    public static function kz($value): int
    {
        return (int) round((float) $value);
    }

    public static function percentOf(int $amount, string|float $percent): int
    {
        return self::kz($amount * ((float) $percent) / 100);
    }
}
