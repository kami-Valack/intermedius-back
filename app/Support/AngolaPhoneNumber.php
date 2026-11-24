<?php

namespace App\Support;

class AngolaPhoneNumber
{
    public static function international(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (! is_string($digits)) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 9) {
            $digits = '244'.$digits;
        }

        return preg_match('/^2449[0-9]{8}$/D', $digits) ? $digits : null;
    }

    public static function national(string $phone): ?string
    {
        $international = self::international($phone);

        return $international ? substr($international, 3) : null;
    }
}
