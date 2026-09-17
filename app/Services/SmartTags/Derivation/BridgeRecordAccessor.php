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

    /**
     * The inputs a set of rules reads, AS THOSE RULES INTERPRET THEM.
     *
     * WHY NOT THE RAW VALUES. This used to record `$this->columns[$column]`
     * directly, which made the hash answer "was this model written or read?" as
     * well as "did the data change?". `bridge_properties.waterfront_yn` and
     * `pool_private_yn` are booleans: immediately after `updateOrCreate()` the
     * attribute is PHP `true`, and after the row is read back it is `1`. Those are
     * different canonical JSON, so the same unchanged listing produced two
     * different SHA-256 hashes depending on which code path had looked at it —
     * and a caller deriving from a freshly written model re-derived every row a
     * caller reading fresh rows had already done, and vice versa.
     *
     * Every value here is therefore taken through the SAME accessor method the
     * rule engine will use for that rule's kind, so the hash and the derivation
     * agree on what a value means. `true`, `1`, `"1"`, `"Y"` and `"yes"` all
     * become boolean `true` because {@see self::toBool()} says so — this class
     * invents no second vocabulary, it just stops hashing before interpretation.
     *
     * KEYED BY READING, NOT BY KIND. Two rules may read one field with different
     * kinds — `Vegetation` is read by both `any` and `vocab`, `ParkingFeatures` by
     * both `vocab` and `prefix`. Keying by the field alone would let the later
     * rule's interpretation silently overwrite the earlier one; keying by the
     * ACCESSOR METHOD keeps every distinct interpretation and collapses the ones
     * that are genuinely identical (all four of those kinds read `values()`).
     *
     * An unrecognised kind falls back to the raw value rather than being dropped:
     * change detection that silently stops watching a field is worse than change
     * detection that is occasionally too sensitive.
     */
    public function inputsFor(array $rules): array
    {
        $inputs = [];

        foreach ($rules as $rule) {
            $target = self::targetKey($rule);

            if ($target === null) {
                continue;
            }

            [$reading, $value] = $this->interpret($rule);

            $inputs[$target . '#' . $reading] = $value;
        }

        // The property type is not a rule input — it selects the CONTEXT, and a
        // context change must make a listing stale on its own.
        $inputs['column:property_type'] = $this->columns['property_type'] ?? ($this->raw['PropertyType'] ?? null);
        ksort($inputs);

        return $inputs;
    }

    /**
     * Which accessor method the rule engine uses for each rule kind.
     *
     * This mirrors the switch in {@see StructuredRuleEngine::evaluateRule()} and
     * exists so the hash cannot drift from it: a kind added there without an entry
     * here falls back to the raw value, which is safe but visibly coarse.
     *
     * @var array<string, string>
     */
    private const READINGS = [
        'boolean'   => 'boolean',
        'equals'    => 'scalar',
        'any'       => 'values',
        'prefix'    => 'values',
        'nonempty'  => 'values',
        'vocab'     => 'values',
        'number_gt' => 'number',
        'flag'      => 'flag',
    ];

    /**
     * @param array<string, mixed> $rule
     * @return array{0: string, 1: mixed} the reading used, and the interpreted value
     */
    private function interpret(array $rule): array
    {
        $reading = self::READINGS[(string) ($rule['kind'] ?? '')] ?? null;

        if ($reading === null) {
            return ['raw', $this->read($rule)];
        }

        return [$reading, match ($reading) {
            'boolean' => $this->boolean($rule),
            'scalar'  => $this->scalar($rule),
            'values'  => $this->values($rule),
            'number'  => $this->number($rule),
            'flag'    => $this->flag($rule),
        }];
    }

    /** @param array<string, mixed> $rule */
    private static function targetKey(array $rule): ?string
    {
        if (isset($rule['column'])) {
            return 'column:' . $rule['column'];
        }

        return isset($rule['field']) ? 'field:' . $rule['field'] : null;
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
