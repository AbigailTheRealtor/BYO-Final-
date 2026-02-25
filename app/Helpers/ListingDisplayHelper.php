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
            if (self::isNoneNa($val)) continue;
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

    public static function normalizeJsonList($value, $otherText = null): array
    {
        if (empty($value)) return [];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
                    $value = collect($decoded)
                        ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
                        ->keys()
                        ->toArray();
                } else {
                    $value = $decoded;
                }
            } else {
                $value = [$value];
            }
        }

        if (is_object($value)) {
            $value = (array) $value;
            if (array_keys($value) !== range(0, count($value) - 1)) {
                $value = collect($value)
                    ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
                    ->keys()
                    ->toArray();
            }
        }

        return self::normalizeList($value, $otherText);
    }

    public static function hasAnyValue(...$values): bool
    {
        foreach ($values as $v) {
            if (self::hasValue($v)) return true;
        }
        return false;
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

    public static function normalizePropertyType($value): string
    {
        if (empty($value) || $value === 'null') return '';
        $val = trim((string) $value);
        if (preg_match('/^(Residential|Commercial|Vacant Land|Business Opportunity|Income)\s+Property$/i', $val, $m)) {
            return ucfirst(strtolower($m[1]));
        }
        if (preg_match('/\s+Property$/i', $val)) {
            return preg_replace('/\s+Property$/i', '', $val);
        }
        return $val;
    }

    public static function stripStateSuffix($value): string
    {
        $val = trim((string) $value);
        return preg_replace('/,\s*[A-Z]{2}$/', '', $val);
    }

    public static function normalizeDuplex($value): string
    {
        return str_replace('½', '1/2', (string) $value);
    }

    public static function isNoneNa($value): bool
    {
        if (is_null($value) || $value === '') return true;
        $lower = strtolower(trim((string) $value));
        return in_array($lower, ['none', 'n/a', 'na']);
    }

    public static function normalizeListDeduped($list, $otherText = null): array
    {
        $items = self::normalizeList($list, $otherText);
        $items = array_map(function($item) {
            return self::normalizeDuplex($item);
        }, $items);
        return array_values(array_unique($items));
    }

    public static function stripStateSuffixList(array $items): array
    {
        return array_map(function($item) {
            return self::stripStateSuffix($item);
        }, $items);
    }

    public static function formatYesParenthetical($parentVal, $detail): string
    {
        $yn = self::formatYesNo($parentVal);
        if ($yn === 'Yes' && self::hasValue($detail)) {
            $detailStr = trim((string) $detail);
            return 'Yes (' . $detailStr . ')';
        }
        return $yn;
    }
}
