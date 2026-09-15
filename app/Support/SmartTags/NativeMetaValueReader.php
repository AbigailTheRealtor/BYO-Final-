<?php

namespace App\Support\SmartTags;

/**
 * Reads native Offer Listing meta values in every shape they are actually stored in.
 *
 * The wizards do not store values consistently, and Smart Tags must not be the
 * code that assumes they do:
 *
 *   • plain strings                     "Yes", "Residential"
 *   • JSON-quoted strings               "\"Furnished\""  (Landlord Edit, Seller property_items)
 *   • JSON arrays                       ["Open Floorplan","Vaulted Ceiling(s)"]
 *   • JSON objects                      {"private":true,"community":false}  (pool_type)
 *   • the literal "[]"                  Landlord Create saves single-selects through
 *                                       ensureArray(), which turns "Furnished" into []
 *   • unfiltered MLS strings            MLS import copies feed values into native arrays
 *
 * An empty value, "[]", "null" and "Other" are all UNKNOWN. "[]" in particular
 * must never read as "unfurnished" or "no property style".
 *
 * Pure: takes a raw meta map (meta_key => raw meta_value string), returns values.
 */
final class NativeMetaValueReader
{
    /** Values that carry no answer. */
    private const EMPTY_VALUES = ['', '[]', '{}', 'null', '""'];

    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(private readonly array $meta)
    {
    }

    /**
     * @param iterable<object{meta_key: string, meta_value: mixed}>|array<string, mixed> $rows
     */
    public static function fromMetaRows(iterable $rows): self
    {
        $meta = [];
        foreach ($rows as $key => $row) {
            if (is_object($row) && isset($row->meta_key)) {
                $meta[(string) $row->meta_key] = $row->meta_value;
            } elseif (is_string($key)) {
                $meta[$key] = $row;
            }
        }

        return new self($meta);
    }

    public function raw(string $key): mixed
    {
        return $this->meta[$key] ?? null;
    }

    /**
     * A single answer, or null when there is none.
     */
    public function scalar(string $key): ?string
    {
        $decoded = $this->decode($this->meta[$key] ?? null);

        if (is_string($decoded)) {
            $value = self::clean($decoded);
            return $value === null || strcasecmp($value, 'Other') === 0 ? null : $value;
        }

        if (is_int($decoded) || is_float($decoded)) {
            return (string) $decoded;
        }

        if (is_bool($decoded)) {
            return $decoded ? 'Yes' : 'No';
        }

        // An array holding exactly one string is a single-select saved as an array.
        if (is_array($decoded) && array_is_list($decoded) && count($decoded) === 1 && is_string($decoded[0])) {
            $value = self::clean($decoded[0]);
            return $value === null || strcasecmp($value, 'Other') === 0 ? null : $value;
        }

        return null;
    }

    /**
     * Every answer of a multi-select (or a single-select, as a one-item list).
     *
     * @return string[]
     */
    public function list(string $key): array
    {
        $decoded = $this->decode($this->meta[$key] ?? null);

        $items = match (true) {
            is_array($decoded)  => array_is_list($decoded) ? $decoded : [],
            is_string($decoded) => [$decoded],
            default             => [],
        };

        $out = [];
        foreach ($items as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $value = self::clean((string) $item);
            if ($value !== null && strcasecmp($value, 'Other') !== 0) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Yes/No as a boolean, or null when unanswered or unrecognised.
     */
    public function yesNo(string $key): ?bool
    {
        $value = $this->scalar($key);
        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            'yes', 'y', 'true', '1', 'on'  => true,
            'no', 'n', 'false', '0', 'off' => false,
            default                        => null,
        };
    }

    /**
     * A flag inside a JSON object: flag('pool_type', 'private').
     */
    public function flag(string $key, string $subKey): ?bool
    {
        $decoded = $this->decode($this->meta[$key] ?? null);
        if (! is_array($decoded) || ! array_key_exists($subKey, $decoded)) {
            return null;
        }

        $value = $decoded[$subKey];

        return match (true) {
            is_bool($value)                                                  => $value,
            is_int($value)                                                   => $value === 1 ? true : ($value === 0 ? false : null),
            is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)  => true,
            is_string($value) && in_array(strtolower($value), ['0', 'false', 'no', 'off', ''], true) => false,
            default                                                          => null,
        };
    }

    public function number(string $key): ?float
    {
        $value = $this->scalar($key);
        if ($value === null) {
            return null;
        }

        $clean = str_replace([',', '$', ' '], '', $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Canonical comparison form for an option string: trimmed, whitespace
     * collapsed, en/em dashes read as hyphens, case-folded. So "Elevator – None",
     * "Elevator - None" and "Five or More " compare equal to their clean forms.
     */
    public static function normalizeOption(string $value): string
    {
        $value = str_replace(["\u{2013}", "\u{2014}", "\u{2012}", "\u{00A0}"], ['-', '-', '-', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }

    private function decode(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        if (is_array($raw) || is_bool($raw) || is_int($raw) || is_float($raw)) {
            return $raw;
        }

        $string = trim((string) $raw);
        if (in_array($string, self::EMPTY_VALUES, true)) {
            return null;
        }

        $first = $string[0];
        if ($first === '[' || $first === '{' || $first === '"') {
            $decoded = json_decode($string, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $string;
    }

    private static function clean(string $value): ?string
    {
        $value = trim($value);

        return in_array($value, self::EMPTY_VALUES, true) ? null : $value;
    }
}
