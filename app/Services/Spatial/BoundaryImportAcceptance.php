<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * The offline acceptance gate for a normalized boundary batch — the authoring-time analogue of the
 * Class-2 post-stage SQL checks. Pure and deterministic; no DB, no cluster. Returns the same
 * {passed, row_count, checks[], failures[]} verdict shape as {@see CorpusImportAcceptance} (2C) and
 * {@see AuthorityOverlayAcceptance} (C2).
 *
 * Invariants (a batch that fails any of these must NOT be staged):
 *   • non_empty            — at least one record was produced
 *   • kind_valid           — every record's kind ∈ the allowed set (C3a: {protected_area})
 *   • geometry_multipolygon— every geometry is a structurally-valid canonical MultiPolygon
 *                            (type, closed rings ≥4 positions, finite in-range coordinates)
 *   • ref_present          — external_ref present (source-level identity; nullable in the table)
 *   • ref_unique           — no duplicate external_ref — HARD FAIL (owner decision: PAD-US
 *                            multi-row/unit aggregation is deferred; duplicates are never merged,
 *                            concatenated, arbitrarily picked, or given invented part ids)
 *   • acres_non_negative   — a PRESENT attrs.acres is numeric and ≥ 0; NULL allowed (identity is
 *                            authoritative even when acreage is absent)
 *   • row_count_reconciles — record count == the claimed count (when supplied)
 *
 * Geometry validity here is STRUCTURAL only; topological validity (ST_IsValid / ST_MakeValid) is a
 * Class-2 concern authored in the spike SQL, never applied offline.
 *
 * @see \Tests\Unit\Spatial\BoundaryImportAcceptanceTest
 */
final class BoundaryImportAcceptance
{
    private const SAMPLE_LIMIT = 5;

    private readonly BoundaryGeometry $geometry;
    /** @var list<string> */
    private readonly array $allowedKinds;

    /**
     * @param list<string> $allowedKinds defaults to the C3a set {protected_area}
     */
    public function __construct(?BoundaryGeometry $geometry = null, array $allowedKinds = ['protected_area'])
    {
        $this->geometry = $geometry ?? new BoundaryGeometry();
        $this->allowedKinds = $allowedKinds;
    }

    /**
     * @param  iterable<BoundaryRecord> $records
     * @return array{passed:bool,row_count:int,checks:array<int,array{name:string,passed:bool,detail:string}>,failures:list<string>}
     */
    public function evaluate(iterable $records, ?int $expectedRowCount = null): array
    {
        $records = is_array($records) ? array_values($records) : iterator_to_array($records, false);
        $count = count($records);

        $allowed = array_flip($this->allowedKinds);
        $badKind = [];
        $badGeometry = [];
        $missingRef = [];
        $badAcres = [];
        $seen = [];
        $duplicate = [];

        foreach ($records as $i => $r) {
            $ref = $r->external_ref ?? "#{$i}";

            if (!isset($allowed[$r->kind])) {
                $badKind[] = "{$ref}:{$r->kind}";
            }
            if (!$this->geometry->isValidMultiPolygon($r->geometry)) {
                $badGeometry[] = $ref;
            }
            if (trim((string) $r->external_ref) === '') {
                $missingRef[] = "#{$i}";
            } else {
                if (isset($seen[$r->external_ref])) {
                    $duplicate[] = $r->external_ref;
                } else {
                    $seen[$r->external_ref] = true;
                }
            }

            $acres = $r->attrs['acres'] ?? null;
            if ($acres !== null && (!is_numeric($acres) || (float) $acres < 0.0)) {
                $badAcres[] = "{$ref}:" . var_export($acres, true);
            }
        }

        $checks = [];
        $checks[] = $this->check('non_empty', $count > 0, $count > 0 ? "{$count} records" : 'no records produced');
        $checks[] = $this->check('kind_valid', $badKind === [], $this->offenders($badKind, 'kind not in {' . implode(',', $this->allowedKinds) . '}'));
        $checks[] = $this->check('geometry_multipolygon', $badGeometry === [], $this->offenders($badGeometry, 'invalid MultiPolygon geometry'));
        $checks[] = $this->check('ref_present', $missingRef === [], $this->offenders($missingRef, 'records missing external_ref'));
        $checks[] = $this->check('ref_unique', $duplicate === [], $this->offenders(array_values(array_unique($duplicate)), 'duplicate external_ref (deferred — never merged)'));
        $checks[] = $this->check('acres_non_negative', $badAcres === [], $this->offenders($badAcres, 'attrs.acres non-numeric or negative'));

        if ($expectedRowCount !== null) {
            $ok = $expectedRowCount === $count;
            $checks[] = $this->check('row_count_reconciles', $ok, $ok ? "records == claimed ({$count})" : "records {$count} != claimed {$expectedRowCount}");
        }

        $failures = [];
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $failures[] = $c['name'];
            }
        }

        return ['passed' => $failures === [], 'row_count' => $count, 'checks' => $checks, 'failures' => $failures];
    }

    /** @return array{name:string,passed:bool,detail:string} */
    private function check(string $name, bool $passed, string $detail): array
    {
        return ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }

    /** @param list<string> $offenders */
    private function offenders(array $offenders, string $label): string
    {
        if ($offenders === []) {
            return 'ok';
        }
        $sample = array_slice($offenders, 0, self::SAMPLE_LIMIT);
        $more = count($offenders) > self::SAMPLE_LIMIT ? ' …(+' . (count($offenders) - self::SAMPLE_LIMIT) . ')' : '';

        return sprintf('%d %s: %s%s', count($offenders), $label, implode(', ', $sample), $more);
    }
}
