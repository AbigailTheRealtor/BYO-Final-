<?php

namespace App\Exports;

class ListingExportFormatter
{
    public static function normalizePropertyType(?string $v): ?string
    {
        if (!$v) return $v;
        $v = trim($v);
        $v = preg_replace('/\s+Property$/i', '', $v);
        return $v;
    }

    public static function toText($value): string
    {
        if (is_null($value)) return '';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_array($value)) {
            $flat = self::flatten($value);
            return implode(', ', array_values(array_unique(array_filter(array_map('trim', $flat)))));
        }
        return trim((string)$value);
    }

    public static function toList($value): array
    {
        if (is_null($value)) return [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', self::flatten($value)), fn($v) => $v !== ''));
        }
        $s = trim((string)$value);
        return $s === '' ? [] : [$s];
    }

    public static function flatten(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $out = array_merge($out, self::flatten($v));
            } else {
                $out[] = (string)$v;
            }
        }
        return $out;
    }

    public static function resolveOther($selectionValue, $otherTextValue): array
    {
        $items = self::toList($selectionValue);
        $otherText = trim((string)($otherTextValue ?? ''));

        if ($otherText === '') return $items;

        $result = [];
        $foundOther = false;
        foreach ($items as $it) {
            if (strcasecmp($it, 'Other') === 0 || str_starts_with(strtolower($it), 'other')) {
                $result[] = $otherText;
                $foundOther = true;
            } else {
                $result[] = $it;
            }
        }

        if (!$foundOther && count($items) === 0) {
            $result[] = $otherText;
        }

        return array_values(array_unique(array_filter(array_map('trim', $result), fn($v) => $v !== '')));
    }

    public static function fmtMoney($value): ?string
    {
        if (is_null($value) || $value === '') return null;
        $num = (float)str_replace(',', '', $value);
        return '$' . number_format($num, 2);
    }

    public static function fmtNumber($value): ?string
    {
        if (is_null($value) || $value === '') return null;
        $num = (float)str_replace(',', '', $value);
        return number_format($num, 0);
    }

    public static function fmtPercent($value): ?string
    {
        if (is_null($value) || $value === '') return null;
        return rtrim(rtrim(number_format((float)str_replace(',', '', $value), 2), '0'), '.') . '%';
    }

    public static function fmtYesNo($value): ?string
    {
        if (is_null($value) || $value === '') return null;
        $v = strtolower(trim((string)$value));
        if (in_array($v, ['yes', '1', 'true'])) return 'Yes';
        if (in_array($v, ['no', '0', 'false'])) return 'No';
        return (string)$value;
    }
}
