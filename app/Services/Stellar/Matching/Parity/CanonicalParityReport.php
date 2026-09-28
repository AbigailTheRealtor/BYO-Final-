<?php

namespace App\Services\Stellar\Matching\Parity;

/**
 * P1-B2 — the offline parity runner's report. Immutable once built.
 *
 * Three blocks, and the split is what makes a run provable:
 *  - `result`  everything the comparison found. Deterministic for the same data and the
 *              same options: associative keys are sorted, lists keep the runner's
 *              deterministic (row id) order, and nothing time- or host-dependent is in it.
 *              `result_digest` is a SHA-256 over its canonical JSON, so two runs over one
 *              dataset can be shown identical by comparing one string.
 *  - `cost`    timings, query count, throughput — measured, NOT digested.
 *  - `run`     when and where it ran — NOT digested.
 *
 * Nothing here is persisted by the application: the command prints it, or writes it to a
 * file the operator names.
 */
final class CanonicalParityReport
{
    public const SCHEMA = 'canonical-matching-parity/v1';

    public const VERDICT_PASS               = 'PASS';
    public const VERDICT_UNDECLARED         = 'UNDECLARED_DIFFERENCE';
    public const VERDICT_ERROR_MISMATCH     = 'ERROR_MISMATCH';
    public const VERDICT_UNRESOLVABLE       = 'CANONICAL_UNRESOLVABLE';

    /** @var array<string,mixed> */
    public readonly array $result;

    /** @param array<string,mixed> $result @param array<string,mixed> $cost @param array<string,mixed> $run */
    public function __construct(array $result, public readonly array $cost, public readonly array $run)
    {
        $this->result = self::canonicalize($result);
    }

    public function digest(): string
    {
        return hash('sha256', self::encode($this->result));
    }

    public function undeclaredCount(): int
    {
        return (int) ($this->result['listings']['status'][CanonicalMatchingParityRunner::STATUS_UNDECLARED] ?? 0);
    }

    public function errorMismatchCount(): int
    {
        return (int) ($this->result['listings']['status'][CanonicalMatchingParityRunner::STATUS_ERROR_MISMATCH] ?? 0);
    }

    public function unresolvableCount(): int
    {
        return (int) ($this->result['listings']['status'][CanonicalMatchingParityRunner::STATUS_UNRESOLVABLE] ?? 0);
    }

    /** The worst finding, in exit-code precedence order. */
    public function verdict(bool $failOnUnresolvable = false): string
    {
        return match (true) {
            $this->errorMismatchCount() > 0                         => self::VERDICT_ERROR_MISMATCH,
            $this->undeclaredCount() > 0                            => self::VERDICT_UNDECLARED,
            $failOnUnresolvable && $this->unresolvableCount() > 0   => self::VERDICT_UNRESOLVABLE,
            default                                                 => self::VERDICT_PASS,
        };
    }

    /** @return array<string,mixed> */
    public function toArray(bool $failOnUnresolvable = false): array
    {
        return [
            'schema'        => self::SCHEMA,
            'result'        => $this->result,
            'result_digest' => $this->digest(),
            'verdict'       => $this->verdict($failOnUnresolvable),
            'cost'          => $this->cost,
            'run'           => $this->run,
        ];
    }

    public function toJson(bool $failOnUnresolvable = false): string
    {
        return json_encode($this->toArray($failOnUnresolvable), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) . "\n";
    }

    public static function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** Sort every associative array's keys, recursively; lists keep their order. */
    public static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $value = array_map([self::class, 'canonicalize'], $value);

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
