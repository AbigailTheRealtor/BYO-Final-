<?php

namespace App\Services\SmartTags\Derivation;

use App\Models\BridgeProperty;

/**
 * Reads a Bridge record: native bridge_properties columns (`column`) and
 * raw_json RESO fields (`field`).
 *
 * Pure over two arrays, so it can be exercised without a database row.
 */
final class BridgeRecordAccessor implements StructuredValueAccessor
{
    /**
     * @param array<string, mixed> $columns bridge_properties attributes
     * @param array<string, mixed> $raw     decoded raw_json
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $raw,
    ) {
    }

    public static function fromModel(BridgeProperty $property): self
    {
        $raw = json_decode((string) $property->getRawOriginal('raw_json'), true);

        return new self($property->getAttributes(), is_array($raw) ? $raw : []);
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    public function column(string $name): mixed
    {
        return $this->columns[$name] ?? null;
    }

    public function boolean(array $rule): ?bool
    {
        return self::toBool($this->read($rule));
    }

    public function scalar(array $rule): ?string
    {
        $value = $this->read($rule);

        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value) && array_is_list($value) && count($value) === 1 && is_string($value[0])) {
            return trim($value[0]) === '' ? null : trim($value[0]);
        }

        return null;
    }

    public function values(array $rule): array
    {
        $value = $this->read($rule);

        $items = match (true) {
            is_array($value)  => $value,
            is_string($value) => explode(',', $value),
            default           => [],
        };

        $out = [];
        foreach ($items as $item) {
            if (is_string($item) || is_numeric($item)) {
                $clean = trim((string) $item);
                if ($clean !== '') {
                    $out[] = $clean;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public function number(array $rule): ?float
    {
        $value = $this->read($rule);

        return is_numeric($value) ? (float) $value : null;
    }

    public function flag(array $rule): ?bool
    {
        return null; // Bridge records carry no JSON-object flags.
    }

    public function inputsFor(array $rules): array
    {
        $inputs = [];
        foreach ($rules as $rule) {
            if (isset($rule['column'])) {
                $inputs['column:' . $rule['column']] = $this->columns[$rule['column']] ?? null;
            } elseif (isset($rule['field'])) {
                $inputs['field:' . $rule['field']] = $this->raw[$rule['field']] ?? null;
            }
        }
        $inputs['column:property_type'] = $this->columns['property_type'] ?? ($this->raw['PropertyType'] ?? null);
        ksort($inputs);

        return $inputs;
    }

    /** @param array<string, mixed> $rule */
    private function read(array $rule): mixed
    {
        if (isset($rule['column'])) {
            return $this->columns[$rule['column']] ?? null;
        }

        return isset($rule['field']) ? ($this->raw[$rule['field']] ?? null) : null;
    }

    private static function toBool(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value)  => $value === 1 ? true : ($value === 0 ? false : null),
            is_string($value) => match (strtolower(trim($value))) {
                'y', 'yes', 'true', '1', 't'  => true,
                'n', 'no', 'false', '0', 'f'  => false,
                default                       => null,
            },
            default => null,
        };
    }
}
