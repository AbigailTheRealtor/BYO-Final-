<?php

namespace App\Support;

class Format
{
    public static function money($value): string
    {
        if ($value === null || $value === '') return '';

        $clean = str_replace([',', '$', ' '], '', (string) $value);

        if ($clean === '' || !is_numeric($clean)) return '';

        return '$' . number_format((float) $clean, 0);
    }

    public static function number($value, int $decimals = 0): string
    {
        if ($value === null || $value === '') return '';

        $clean = str_replace([',', '$', ' '], '', (string) $value);

        if ($clean === '' || !is_numeric($clean)) return '';

        return number_format((float) $clean, $decimals);
    }

    public static function percentage($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') return '';

        $clean = str_replace([',', '%', ' '], '', (string) $value);

        if ($clean === '' || !is_numeric($clean)) return '';

        return rtrim(rtrim(number_format((float) $clean, $decimals), '0'), '.') . '%';
    }
}
