<?php

namespace App\Helpers;

class ListingDisplayHelper
{
    protected static $placeholders = [
        'Example:',
        'Example 1',
        'Example 2',
        'Example: Additional Terms',
        'Example: Payment timing',
    ];

    public static function isPlaceholder($value): bool
    {
        if (empty($value) || $value === 'null') {
            return true;
        }
        $val = trim((string) $value);
        if ($val === '') {
            return true;
        }
        foreach (self::$placeholders as $ph) {
            if (stripos($val, $ph) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function hasValue($value): bool
    {
        if (is_null($value)) return false;
        if (is_array($value)) return !empty(array_filter($value));
        $val = trim((string) $value);
        return $val !== '' && $val !== 'null' && !self::isPlaceholder($val);
    }

    public static function fmtMoney($value): string
    {
        $clean = str_replace(',', '', (string) $value);
        if (!is_numeric($clean)) return (string) $value;
        return '$' . number_format((float) $clean, 2);
    }

    public static function fmtMoneyWhole($value): string
    {
        $clean = str_replace(',', '', (string) $value);
        if (!is_numeric($clean)) return (string) $value;
        $floatVal = (float) $clean;
        if ($floatVal == intval($floatVal)) {
            return '$' . number_format($floatVal, 0);
        }
        return '$' . number_format($floatVal, 2);
    }

    public static function fmtPercent($value): string
    {
        $clean = str_replace(',', '', (string) $value);
        if (!is_numeric($clean)) return (string) $value;
        $floatVal = (float) $clean;
        if ($floatVal == intval($floatVal)) {
            return intval($floatVal) . '%';
        }
        return rtrim(rtrim(number_format($floatVal, 2), '0'), '.') . '%';
    }

    public static function fmtNumber($value): string
    {
        $clean = str_replace(',', '', (string) $value);
        if (!is_numeric($clean)) return (string) $value;
        return number_format((float) $clean, 0);
    }

    public static function fmtDate($value): string
    {
        if (empty($value)) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('M j, Y');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    public static function formatYesNo($value): string
    {
        if (is_null($value) || $value === '' || $value === 'null') return '';
        $lower = strtolower(trim((string) $value));
        if (in_array($lower, ['yes', '1', 'true'])) return 'Yes';
        if (in_array($lower, ['no', '0', 'false'])) return 'No';
        return (string) $value;
    }

    public static function formatYesCount($parentVal, $count, string $suffix = ''): string
    {
        $yn = self::formatYesNo($parentVal);
        if ($yn === 'Yes' && self::hasValue($count)) {
            return 'Yes (' . intval($count) . ($suffix ? ' ' . $suffix : '') . ')';
        }
        return $yn;
    }

    public static function formatYesList($parentVal, $list): string
    {
        $yn = self::formatYesNo($parentVal);
        if ($yn !== 'Yes') return $yn;

        $items = self::normalizeList($list);
        if (!empty($items)) {
            return 'Yes (' . implode(', ', $items) . ')';
        }
        return 'Yes';
    }

    public static function formatYesText($parentVal, $text): string
    {
        $yn = self::formatYesNo($parentVal);
        if ($yn === 'Yes' && self::hasValue($text)) {
            return 'Yes (' . trim((string) $text) . ')';
        }
        return $yn;
    }

    public static function normalizeList($list, $otherText = null): array
    {
        if (empty($list)) return [];

        if (is_string($list)) {
            $decoded = json_decode($list, true);
            $list = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$list];
        }

        if (!is_array($list)) return [];

        $result = [];
        foreach ($list as $item) {
            $val = trim((string) $item);
            if ($val === '' || self::isPlaceholder($val)) continue;
            if (strtolower($val) === 'other') {
                if (self::hasValue($otherText)) {
                    $result[] = trim((string) $otherText);
                }
                continue;
            }
            $result[] = $val;
        }
        return $result;
    }

    public static function isParentNo($value): bool
    {
        $yn = self::formatYesNo($value);
        return $yn === 'No';
    }

    public static function isParentYes($value): bool
    {
        $lower = strtolower(trim((string) $value));
        return in_array($lower, ['yes', '1', 'true']);
    }
}
