<?php

namespace App\Services\Spatial\Gate2;

use InvalidArgumentException;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-a (Gate 2 evidence schema).
 *
 * CoverageCell — one immutable (dataset × category × territory) cell of the Gate 2 corpus-coverage
 * matrix (SSOT §5 / §18 / E-32). It records an OBSERVATION, never a metric:
 *
 *   • `measured`      — was the owned corpus actually queried for this cell?
 *   • `present_count` — how many owned-corpus features were observed (raw count), or null if not
 *                       measured. A NOT-MEASURED cell is NOT a zero cell — that distinction is the
 *                       whole point (the INV-5 sibling: "not measured" must never read as "measured
 *                       zero"). A measured-zero cell is an honest gap; an unmeasured cell is unknown.
 *
 * WHAT THIS DELIBERATELY DOES NOT CARRY (C3d-a scope — owner-gated)
 * ----------------------------------------------------------------
 * No coverage ratio, no percentage, no numerator/denominator, no threshold, no pass/fail verdict.
 * The SSOT does not define a Gate 2 coverage formula and states acceptance is per-category by the
 * PRODUCT OWNER — so this schema stores evidence and stops there. `status()` is a factual
 * DESCRIPTION of the observation (unmeasured / absent / present), not a score and not a verdict;
 * whether "present" or "absent" is acceptable for a category is a C3d-b product decision.
 *
 * Pure and deterministic — no DB, no PostGIS, no network, no secrets.
 *
 * @see \App\Services\Spatial\Gate2\CoverageMatrix
 * @see \Tests\Unit\Spatial\Gate2\CoverageCellTest
 */
final class CoverageCell
{
    public const STATUS_UNMEASURED = 'unmeasured';
    public const STATUS_ABSENT     = 'absent';
    public const STATUS_PRESENT    = 'present';

    public function __construct(
        public readonly string $dataset,
        public readonly string $category,
        public readonly string $territory,
        public readonly bool $measured,
        public readonly ?int $presentCount = null,
        public readonly ?string $note = null,
    ) {
        if ($dataset === '' || $category === '' || $territory === '') {
            throw new InvalidArgumentException('CoverageCell requires non-empty dataset, category, and territory.');
        }

        if ($measured) {
            if ($presentCount === null || $presentCount < 0) {
                throw new InvalidArgumentException(
                    "CoverageCell [{$dataset}/{$category}/{$territory}] is measured; present_count must be a non-negative integer."
                );
            }
        } elseif ($presentCount !== null) {
            // Guard the exact honesty invariant: a not-measured cell may not smuggle a count.
            throw new InvalidArgumentException(
                "CoverageCell [{$dataset}/{$category}/{$territory}] is not measured; present_count must be null "
                . '(a not-measured cell is not a zero cell).'
            );
        }
    }

    /**
     * A factual description of the observation — NOT a coverage score and NOT a pass/fail verdict.
     * unmeasured = the corpus was not queried; absent = queried, zero features; present = queried,
     * one or more features.
     */
    public function status(): string
    {
        if (! $this->measured) {
            return self::STATUS_UNMEASURED;
        }

        return $this->presentCount > 0 ? self::STATUS_PRESENT : self::STATUS_ABSENT;
    }

    public function isUnmeasured(): bool
    {
        return ! $this->measured;
    }

    /** Measured, and zero features observed — an honest gap, not a failure. */
    public function isAbsent(): bool
    {
        return $this->measured && $this->presentCount === 0;
    }

    public function isPresent(): bool
    {
        return $this->measured && ($this->presentCount ?? 0) > 0;
    }

    /** Stable identity within a matrix. */
    public function key(): string
    {
        return $this->dataset . '|' . $this->category . '|' . $this->territory;
    }

    /** Canonical shape. Key order is the wire format — do not reorder. */
    public function toArray(): array
    {
        return [
            'dataset'       => $this->dataset,
            'category'      => $this->category,
            'territory'     => $this->territory,
            'measured'      => $this->measured,
            'present_count' => $this->presentCount,
            'status'        => $this->status(),
            'note'          => $this->note,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['dataset', 'category', 'territory'] as $req) {
            if (! array_key_exists($req, $data)) {
                throw new InvalidArgumentException("CoverageCell::fromArray() missing required key [{$req}].");
            }
        }

        // `status` is DERIVED, never trusted from input — it cannot be forged here.
        $measured = (bool) ($data['measured'] ?? false);
        $present  = $data['present_count'] ?? null;

        return new self(
            dataset: (string) $data['dataset'],
            category: (string) $data['category'],
            territory: (string) $data['territory'],
            measured: $measured,
            presentCount: $present === null ? null : (int) $present,
            note: isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
        );
    }
}
