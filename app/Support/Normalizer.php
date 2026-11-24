<?php

namespace App\Support;

class Normalizer
{
    public static function text(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public static function fingerprint(string ...$parts): string
    {
        $joined = implode('|', array_map(function ($part) {
            $part = self::text($part);
            $part = \Normalizer::normalize($part, \Normalizer::FORM_D) ?: $part;
            $part = preg_replace('/\p{Mn}+/u', '', $part) ?? $part;
            $part = mb_strtolower($part);
            $part = preg_replace('/[^a-z0-9]/', '', $part) ?? $part;

            return $part;
        }, $parts));

        return hash('sha256', $joined);
    }

    public static function tipologia(int $bedrooms): string
    {
        return $bedrooms >= 4 ? 'T4+' : 'T'.$bedrooms;
    }

    public static function weekDays(array $days): array
    {
        $normalized = [];
        foreach ($days as $day) {
            $day = (int) $day;
            if ($day === 0) {
                $day = 7;
            }
            if ($day >= 1 && $day <= 7) {
                $normalized[] = $day;
            }
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
