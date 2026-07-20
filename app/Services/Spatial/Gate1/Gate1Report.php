<?php

namespace App\Services\Spatial\Gate1;

/**
 * Gate1Report — the structured verdict of a Gate 1 rank-sanity run.
 *
 * PART OF: Phase 2 Batch 2D Part B — Hybrid Gate 1 Harness, Option E (runtime validation).
 *
 * WHAT IT CARRIES
 * ---------------
 * The embarrassment rate (embarrassments ÷ evaluated top-N slots), the pass/fail verdict against
 * the configured threshold (SSOT §9.3: ≤3%), a per-category breakdown, and the concrete list of
 * offenders — each an illegitimate POI the engine surfaced inside the top-N window, named so a
 * failure is actionable rather than a bare number.
 *
 * SCORES ARE NEVER CARRIED (erratum E-48)
 * ---------------------------------------
 * Like PoiBaselineDiffHarness, this report is about MEMBERSHIP and RANK POSITION, not
 * `ranking_score`. `ranking_score` is set-relative and would report arithmetic noise as semantic
 * difference. No score-bearing key appears anywhere in this structure.
 */
final class Gate1Report
{
    /**
     * @param  array<string, array{slots: int, embarrassments: int}>  $perCategory
     * @param  list<array{scenario: string, category: string, rank: int, name: string}>  $offenders
     */
    public function __construct(
        private readonly int $scenarioCount,
        private readonly int $evaluatedSlots,
        private readonly int $embarrassmentCount,
        private readonly int $topN,
        private readonly float $threshold,
        private readonly array $perCategory,
        private readonly array $offenders,
    ) {}

    /** Embarrassment rate as a fraction of evaluated top-N slots. Zero slots → 0.0. */
    public function rate(): float
    {
        return $this->evaluatedSlots > 0
            ? $this->embarrassmentCount / $this->evaluatedSlots
            : 0.0;
    }

    public function passed(): bool
    {
        return $this->rate() <= $this->threshold;
    }

    public function scenarioCount(): int
    {
        return $this->scenarioCount;
    }

    public function evaluatedSlots(): int
    {
        return $this->evaluatedSlots;
    }

    public function embarrassmentCount(): int
    {
        return $this->embarrassmentCount;
    }

    public function topN(): int
    {
        return $this->topN;
    }

    public function threshold(): float
    {
        return $this->threshold;
    }

    /** @return array<string, array{slots: int, embarrassments: int}> */
    public function perCategory(): array
    {
        return $this->perCategory;
    }

    /** @return list<array{scenario: string, category: string, rank: int, name: string}> */
    public function offenders(): array
    {
        return $this->offenders;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'scenarios'      => $this->scenarioCount,
            'top_n'          => $this->topN,
            'evaluated_slots' => $this->evaluatedSlots,
            'embarrassments' => $this->embarrassmentCount,
            'rate'           => $this->rate(),
            'threshold'      => $this->threshold,
            'passed'         => $this->passed(),
            'per_category'   => $this->perCategory,
            'offenders'      => $this->offenders,
        ];
    }
}
