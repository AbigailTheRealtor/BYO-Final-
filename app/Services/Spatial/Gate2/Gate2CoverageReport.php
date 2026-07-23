<?php

namespace App\Services\Spatial\Gate2;

/**
 * Gate2CoverageReport — the structured evidence summary of a Gate 2 coverage matrix.
 *
 * PART OF: Phase 2 Batch 2D Part C3d-a — Gate 2 corpus-coverage EVIDENCE SCHEMA.
 *
 * WHAT IT CARRIES
 * ---------------
 * Factual roll-ups over the {@see CoverageMatrix} for a PRODUCT OWNER to read: per-territory
 * counts of present / absent / unmeasured cells, the honest gap and unmeasured lists, and the E-32
 * PR watch-dataset verification block (the four datasets to verify explicitly for Puerto Rico).
 *
 * WHAT IT DELIBERATELY DOES NOT CARRY (C3d-a scope — owner-gated)
 * --------------------------------------------------------------
 * No `passed` key. No coverage ratio, percentage, numerator, denominator, or threshold. The SSOT
 * defines no Gate 2 formula and states acceptance is per-category by the product owner. This report
 * makes that explicit in an `acceptance` block whose every automated flag is false and whose
 * disposition is "deferred to C3d-b". A reader can never mistake this schema for a passing gate.
 *
 * Pure and deterministic — territory roll-ups follow the matrix's declared axis order; no timestamps,
 * no environment, no DB.
 *
 * @see \App\Services\Spatial\Gate2\CoverageMatrix
 * @see \Tests\Unit\Spatial\Gate2\Gate2CoverageReportTest
 */
final class Gate2CoverageReport
{
    public function __construct(
        private readonly CoverageMatrix $matrix,
        private readonly string $prTerritory = 'PR',
    ) {
    }

    public function matrix(): CoverageMatrix
    {
        return $this->matrix;
    }

    /** @return array{total:int,measured:int,present:int,absent:int,unmeasured:int} */
    public function cellTotals(): array
    {
        $cells      = $this->matrix->cells();
        $present    = count($this->matrix->present());
        $absent     = count($this->matrix->gaps());
        $unmeasured = count($this->matrix->unmeasured());

        return [
            'total'      => count($cells),
            'measured'   => $present + $absent,
            'present'    => $present,
            'absent'     => $absent,
            'unmeasured' => $unmeasured,
        ];
    }

    /**
     * Per-territory roll-up in the matrix's declared axis order.
     *
     * @return array<string,array{total:int,present:int,absent:int,unmeasured:int}>
     */
    public function perTerritory(): array
    {
        $out = [];
        foreach ($this->matrix->territories() as $territory) {
            $present = $absent = $unmeasured = 0;
            foreach ($this->matrix->cellsForTerritory($territory) as $cell) {
                if ($cell->isPresent()) {
                    $present++;
                } elseif ($cell->isAbsent()) {
                    $absent++;
                } else {
                    $unmeasured++;
                }
            }
            $out[$territory] = [
                'total'      => $present + $absent + $unmeasured,
                'present'    => $present,
                'absent'     => $absent,
                'unmeasured' => $unmeasured,
            ];
        }

        return $out;
    }

    /**
     * The E-32 PR verification block: for each PR watch dataset, its PR cells and their observed
     * status. This is evidence for the product owner, not a verdict.
     *
     * @return array<string,list<array{category:string,status:string,present_count:int|null,note:string|null}>>
     */
    public function prWatchVerification(): array
    {
        $out = [];
        foreach ($this->matrix->prWatchDatasets() as $dataset) {
            $out[$dataset] = [];
        }
        foreach ($this->matrix->prWatchCells($this->prTerritory) as $cell) {
            $out[$cell->dataset][] = [
                'category'      => $cell->category,
                'status'        => $cell->status(),
                'present_count' => $cell->presentCount,
                'note'          => $cell->note,
            ];
        }

        return $out;
    }

    /**
     * The honesty statement. Every automated flag is false; disposition is deferred. There is NO
     * `passed` key by design.
     *
     * @return array<string,mixed>
     */
    public function acceptance(): array
    {
        return [
            'coverage_metric_defined'  => false,
            'numerator_defined'        => false,
            'denominator_defined'      => false,
            'threshold_defined'        => false,
            'automated_pass_fail'      => false,
            'product_owner_acceptance' => 'deferred to C3d-b (Class-2)',
            'note' => 'C3d-a authors the Gate 2 evidence schema only. The SSOT defines no coverage '
                . 'formula and states acceptance is per-category by the product owner. Real corpus '
                . 'measurement and acceptance are C3d-b.',
        ];
    }

    /** Deterministic wire shape for summary.json. */
    public function toArray(): array
    {
        return [
            'batch'                 => 'phase-2-batch-2d-part-c3d-a',
            'gate'                  => 'Gate 2 — corpus coverage (evidence schema)',
            'scope'                 => 'C3d-a evidence schema only — no coverage metric, no threshold, no '
                . 'pass/fail, no real corpus.',
            'territories'           => $this->matrix->territories(),
            'pr_watch_datasets'     => $this->matrix->prWatchDatasets(),
            'cell_totals'           => $this->cellTotals(),
            'per_territory'         => $this->perTerritory(),
            'pr_watch_verification' => $this->prWatchVerification(),
            'gaps'                  => array_map(static fn (CoverageCell $c): array => $c->toArray(), $this->matrix->gaps()),
            'unmeasured'            => array_map(static fn (CoverageCell $c): array => $c->toArray(), $this->matrix->unmeasured()),
            'acceptance'            => $this->acceptance(),
        ];
    }
}
