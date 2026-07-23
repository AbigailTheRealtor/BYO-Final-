<?php

namespace App\Services\Spatial\Gate2;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-a (Gate 2 evidence schema).
 *
 * CoverageMatrix — the assembled dataset × territory grid the Gate 2 evidence report renders
 * (SSOT §5 / §18 / E-32). It is the structural container demanded by the SSOT's "dataset × territory
 * matrix, PR and AK reported separately"; it is NOT a metric.
 *
 * COMPLETENESS BY CONSTRUCTION
 * ----------------------------
 * Assembled from the full cartesian product of (dataset × its categories × territories). Every
 * expected cell is therefore present in the grid; a cell nobody measured is a first-class
 * `unmeasured` CoverageCell, never a silent hole. That is the honesty the SSOT requires — "not
 * measured" is visible and distinct from "measured zero".
 *
 * NO METRIC (C3d-a scope — owner-gated)
 * -------------------------------------
 * `gaps()` / `unmeasured()` / `present()` are factual partitions of the observations, not scores.
 * There is no coverage ratio, no threshold, no pass/fail here — those are undefined in the SSOT and
 * deferred to C3d-b product-owner acceptance.
 *
 * Pure and deterministic — cells are held in a stable (dataset, category, territory) order fixed by
 * the assembler; no DB, no network, no secrets.
 *
 * @see \App\Services\Spatial\Gate2\Gate2CoverageAssembler
 * @see \Tests\Unit\Spatial\Gate2\CoverageMatrixTest
 */
final class CoverageMatrix
{
    /**
     * @param list<string>              $territories        declared territory axis, in order
     * @param list<string>              $datasets           declared dataset keys, in order
     * @param array<string,list<string>> $datasetCategories dataset key => its categories, in order
     * @param list<string>              $prWatchDatasets    the E-32 PR watch datasets
     * @param list<CoverageCell>        $cells              deterministically ordered cells
     */
    public function __construct(
        private readonly array $territories,
        private readonly array $datasets,
        private readonly array $datasetCategories,
        private readonly array $prWatchDatasets,
        private readonly array $cells,
    ) {
    }

    /** @return list<string> */
    public function territories(): array
    {
        return $this->territories;
    }

    /** @return list<string> */
    public function datasets(): array
    {
        return $this->datasets;
    }

    /** @return array<string,list<string>> */
    public function datasetCategories(): array
    {
        return $this->datasetCategories;
    }

    /** @return list<string> */
    public function prWatchDatasets(): array
    {
        return $this->prWatchDatasets;
    }

    /** @return list<CoverageCell> */
    public function cells(): array
    {
        return $this->cells;
    }

    public function cell(string $dataset, string $category, string $territory): ?CoverageCell
    {
        $key = $dataset . '|' . $category . '|' . $territory;
        foreach ($this->cells as $cell) {
            if ($cell->key() === $key) {
                return $cell;
            }
        }

        return null;
    }

    /** @return list<CoverageCell> */
    public function cellsForTerritory(string $territory): array
    {
        return array_values(array_filter($this->cells, static fn (CoverageCell $c): bool => $c->territory === $territory));
    }

    /**
     * Measured, zero-feature cells — honest gaps in the corpus. NOT failures: whether a gap is
     * acceptable for a category is a C3d-b product-owner decision.
     *
     * @return list<CoverageCell>
     */
    public function gaps(): array
    {
        return array_values(array_filter($this->cells, static fn (CoverageCell $c): bool => $c->isAbsent()));
    }

    /** @return list<CoverageCell> */
    public function unmeasured(): array
    {
        return array_values(array_filter($this->cells, static fn (CoverageCell $c): bool => $c->isUnmeasured()));
    }

    /** @return list<CoverageCell> */
    public function present(): array
    {
        return array_values(array_filter($this->cells, static fn (CoverageCell $c): bool => $c->isPresent()));
    }

    /**
     * The E-32 explicit-verification surface: every cell of a PR watch dataset in the PR territory.
     * These are the datasets most likely to be silently missing for Puerto Rico, so the schema keeps
     * them front-and-centre for the product owner.
     *
     * @return list<CoverageCell>
     */
    public function prWatchCells(string $prTerritory): array
    {
        $watch = array_flip($this->prWatchDatasets);

        return array_values(array_filter(
            $this->cells,
            static fn (CoverageCell $c): bool => $c->territory === $prTerritory && isset($watch[$c->dataset]),
        ));
    }

    /** Deterministic wire shape for matrix.json. */
    public function toArray(): array
    {
        return [
            'territories'       => $this->territories,
            'datasets'          => array_map(
                fn (string $key): array => ['key' => $key, 'categories' => $this->datasetCategories[$key] ?? []],
                $this->datasets,
            ),
            'pr_watch_datasets' => $this->prWatchDatasets,
            'cells'             => array_map(static fn (CoverageCell $c): array => $c->toArray(), $this->cells),
        ];
    }
}
