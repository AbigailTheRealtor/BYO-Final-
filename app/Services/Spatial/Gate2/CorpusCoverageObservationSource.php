<?php

namespace App\Services\Spatial\Gate2;

use Closure;
use InvalidArgumentException;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-b (Gate 2 Florida pilot, Class-2).
 *
 * CorpusCoverageObservationSource — the LIVE sibling of the C3d-a synthetic fixture. It executes the
 * {@see CoverageQueryCatalog}'s read-only COUNTs against the loaded corpus and emits observations in
 * the EXACT shape {@see Gate2CoverageAssembler} already consumes:
 *
 *     { dataset, category, territory, measured: true, present_count: <int> }
 *
 * The assembler then overlays these on the full grid; any cell WITHOUT an observation is `unmeasured`.
 * The pipeline does not move — Class-1 fed the assembler synthetic observations; this feeds it real
 * ones (Gate2CoverageAssembler doc / D-C3d-a-6).
 *
 * HONESTY INVARIANTS
 * ------------------
 *   • Emits ONLY measured cells (one per catalog query). A cell the catalog does not query is simply
 *     absent from the output → the assembler renders it `unmeasured`. It is NEVER emitted as a zero.
 *   • A genuine zero from the corpus is emitted as `measured: true, present_count: 0` — an honest
 *     `absent` gap, distinct from unmeasured. The source does not, and cannot, turn "not measured"
 *     into "measured zero": the two arise from entirely different code paths (no query vs a query
 *     that returned 0).
 *   • Performs NO writes. It holds a count-only runner (a Closure the command binds to the pgsql_spatial
 *     connection) and calls it once per query; the runner returns an int and does nothing else.
 *
 * Pure orchestration otherwise — no DB handle of its own, no PostGIS knowledge, no secret, no clock.
 *
 * @see \App\Services\Spatial\Gate2\CoverageQueryCatalog
 * @see \App\Services\Spatial\Gate2\Gate2CoverageAssembler
 * @see \Tests\Unit\Spatial\Gate2\CorpusCoverageObservationSourceTest
 */
final class CorpusCoverageObservationSource
{
    /**
     * @param CoverageQueryCatalog $catalog the measurable-tuple → COUNT query catalog
     * @param Closure(string,list<mixed>):int $countRunner executes one read-only COUNT (sql, bindings)
     *        against pgsql_spatial and returns the non-negative row count. Injected so tests pass a
     *        deterministic fake and the "only pgsql_spatial" binding lives in the command wiring.
     */
    public function __construct(
        private readonly CoverageQueryCatalog $catalog,
        private readonly Closure $countRunner,
    ) {
    }

    /**
     * Run the catalog's queries for the requested territories and return assembler-shape observations.
     *
     * @param  list<string> $territories territories to measure (the Florida pilot passes ['FL'])
     * @return list<array{dataset:string,category:string,territory:string,measured:bool,present_count:int}>
     */
    public function observe(array $territories): array
    {
        $observations = [];
        foreach ($this->catalog->queriesFor($territories) as $query) {
            $count = ($this->countRunner)($query['sql'], $query['bindings']);

            if (! is_int($count) || $count < 0) {
                throw new InvalidArgumentException(sprintf(
                    'Coverage count for [%s/%s/%s] must be a non-negative integer; got %s.',
                    $query['dataset'],
                    $query['category'],
                    $query['territory'],
                    var_export($count, true),
                ));
            }

            // A measured cell — including a real zero (an honest `absent`). Never an unmeasured stand-in.
            $observations[] = [
                'dataset'       => $query['dataset'],
                'category'      => $query['category'],
                'territory'     => $query['territory'],
                'measured'      => true,
                'present_count' => $count,
            ];
        }

        return $observations;
    }
}
