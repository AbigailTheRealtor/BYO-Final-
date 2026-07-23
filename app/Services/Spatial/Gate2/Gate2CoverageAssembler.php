<?php

namespace App\Services\Spatial\Gate2;

use InvalidArgumentException;

/**
 * Gate2CoverageAssembler — builds a {@see CoverageMatrix} from a declared coverage input.
 *
 * PART OF: Phase 2 Batch 2D Part C3d-a — Gate 2 corpus-coverage EVIDENCE SCHEMA.
 *
 * WHAT IT DOES
 * ------------
 * Consumes a decoded input describing the axes (datasets + their categories, territories, the E-32
 * PR watch datasets) and a list of observations (per (dataset × category × territory): was it
 * measured, and if so how many owned-corpus features were present). It assembles the FULL cartesian
 * grid and overlays the observations; any cell without an observation is `unmeasured` — never a
 * silent hole (the honesty the SSOT requires).
 *
 * PROVIDER-AGNOSTIC — CORPUS-READY (Class-2)
 * ------------------------------------------
 * Exactly like {@see \App\Services\Spatial\Gate1\Gate1RankSanityEvaluator}, it consumes a raw input
 * shape, not a data source. In Class-1 the observations come from a synthetic fixture; at Class-2
 * (C3d-b) the SAME assembler runs unchanged over observations produced by the AUTHORED-NOT-RUN
 * `coverage_matrix.sql` against a real loaded corpus. The pipeline does not move.
 *
 * FAIL CLOSED (structural integrity, NOT a coverage metric)
 * ---------------------------------------------------------
 *   • empty datasets or territories → reject (a matrix that describes nothing must not be reported).
 *   • every required territory (FL, PR, AK, rural_CONUS) MUST be present — this is how "report PR
 *     and AK separately" (E-32) is enforced structurally.
 *   • every PR watch dataset MUST be a declared dataset — so its PR cells are always in the grid.
 *   • an observation referencing an undeclared dataset / category / territory → reject.
 * None of these is a threshold or a pass/fail on coverage; they guarantee the evidence is complete
 * and honest before a product owner ever reads it.
 *
 * Touches NO corpus, NO PostGIS, NO network, NO secret. File reading (fromJsonFile) is the only I/O.
 *
 * @see \App\Services\Spatial\Gate2\CoverageMatrix
 * @see \Tests\Unit\Spatial\Gate2\Gate2CoverageAssemblerTest
 */
final class Gate2CoverageAssembler
{
    /** @var list<string> */
    private readonly array $requiredTerritories;

    /**
     * @param list<string> $requiredTerritories territories the matrix must cover (defaults to the
     *                                           SSOT set; the command passes config('spatial_gate2.*'))
     */
    public function __construct(array $requiredTerritories = ['FL', 'PR', 'AK', 'rural_CONUS'])
    {
        $this->requiredTerritories = array_values($requiredTerritories);
    }

    public function fromJsonFile(string $path): CoverageMatrix
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Gate 2 coverage input fixture not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Gate 2 coverage input fixture is not valid JSON: {$path}");
        }

        return $this->fromArray($decoded);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function fromArray(array $data): CoverageMatrix
    {
        $territories     = $this->stringList($data['territories'] ?? null, 'territories');
        $prWatchDatasets = $this->stringList($data['pr_watch_datasets'] ?? null, 'pr_watch_datasets');

        if ($territories === []) {
            throw new InvalidArgumentException('Gate 2 coverage input requires a non-empty `territories` axis (fail closed).');
        }

        // Datasets: [{ key, categories: [...] }, ...] — declared order preserved.
        $rawDatasets = $data['datasets'] ?? null;
        if (! is_array($rawDatasets) || $rawDatasets === []) {
            throw new InvalidArgumentException('Gate 2 coverage input requires a non-empty `datasets` axis (fail closed).');
        }

        $datasetKeys       = [];
        $datasetCategories = [];
        foreach (array_values($rawDatasets) as $i => $ds) {
            if (! is_array($ds) || ! isset($ds['key']) || (string) $ds['key'] === '') {
                throw new InvalidArgumentException("Gate 2 dataset #{$i} is missing a non-empty `key`.");
            }
            $key = (string) $ds['key'];
            if (isset($datasetCategories[$key])) {
                throw new InvalidArgumentException("Gate 2 duplicate dataset key '{$key}'.");
            }
            $categories = $this->stringList($ds['categories'] ?? null, "datasets[{$key}].categories");
            if ($categories === []) {
                throw new InvalidArgumentException("Gate 2 dataset '{$key}' must declare at least one category.");
            }
            $datasetKeys[]            = $key;
            $datasetCategories[$key]  = $categories;
        }

        // Structural completeness — the SSOT's "PR and AK reported separately" (E-32).
        $territorySet = array_flip($territories);
        foreach ($this->requiredTerritories as $required) {
            if (! isset($territorySet[$required])) {
                throw new InvalidArgumentException(
                    "Gate 2 matrix is missing required territory '{$required}'. FL, PR, AK, and rural_CONUS "
                    . 'must each be reported separately (SSOT §5 / E-32).'
                );
            }
        }

        // Each PR watch dataset must be declared, so its PR cells are always in the grid.
        $datasetSet = array_flip($datasetKeys);
        foreach ($prWatchDatasets as $watch) {
            if (! isset($datasetSet[$watch])) {
                throw new InvalidArgumentException(
                    "Gate 2 PR watch dataset '{$watch}' is not a declared dataset (E-32 requires it be verified explicitly)."
                );
            }
        }

        // Index observations by cell key; reject strays referencing undeclared axes.
        $observations = [];
        foreach (array_values((array) ($data['observations'] ?? [])) as $i => $obs) {
            if (! is_array($obs)) {
                throw new InvalidArgumentException("Gate 2 observation #{$i} is not an object.");
            }
            $dataset   = (string) ($obs['dataset'] ?? '');
            $category  = (string) ($obs['category'] ?? '');
            $territory = (string) ($obs['territory'] ?? '');

            if (! isset($datasetSet[$dataset])) {
                throw new InvalidArgumentException("Gate 2 observation #{$i} references undeclared dataset '{$dataset}'.");
            }
            if (! in_array($category, $datasetCategories[$dataset], true)) {
                throw new InvalidArgumentException("Gate 2 observation #{$i} references undeclared category '{$category}' for dataset '{$dataset}'.");
            }
            if (! isset($territorySet[$territory])) {
                throw new InvalidArgumentException("Gate 2 observation #{$i} references undeclared territory '{$territory}'.");
            }

            $key = $dataset . '|' . $category . '|' . $territory;
            if (isset($observations[$key])) {
                throw new InvalidArgumentException("Gate 2 duplicate observation for cell '{$key}'.");
            }
            $observations[$key] = $obs;
        }

        // Assemble the full grid in stable (dataset, category, territory) declared order. A cell with
        // no observation is `unmeasured` — present in the grid, never silently omitted.
        $cells = [];
        foreach ($datasetKeys as $dataset) {
            foreach ($datasetCategories[$dataset] as $category) {
                foreach ($territories as $territory) {
                    $key = $dataset . '|' . $category . '|' . $territory;
                    if (isset($observations[$key])) {
                        $obs      = $observations[$key];
                        $measured = (bool) ($obs['measured'] ?? false);
                        $present  = $obs['present_count'] ?? null;
                        $cells[]  = new CoverageCell(
                            dataset: $dataset,
                            category: $category,
                            territory: $territory,
                            measured: $measured,
                            presentCount: $measured ? (int) $present : null,
                            note: isset($obs['note']) && $obs['note'] !== '' ? (string) $obs['note'] : null,
                        );
                    } else {
                        $cells[] = new CoverageCell(
                            dataset: $dataset,
                            category: $category,
                            territory: $territory,
                            measured: false,
                        );
                    }
                }
            }
        }

        return new CoverageMatrix(
            territories: $territories,
            datasets: $datasetKeys,
            datasetCategories: $datasetCategories,
            prWatchDatasets: $prWatchDatasets,
            cells: $cells,
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException("Gate 2 coverage input `{$label}` must be an array of strings.");
        }
        $out = [];
        foreach ($value as $v) {
            $s = (string) $v;
            if ($s === '') {
                throw new InvalidArgumentException("Gate 2 coverage input `{$label}` contains a blank entry.");
            }
            $out[] = $s;
        }

        return $out;
    }
}
