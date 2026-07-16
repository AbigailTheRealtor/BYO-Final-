<?php

namespace Tests\Support\Spatial;

/**
 * B1.2 — mixed-geometry KNN EXPLAIN plan-shape assertion engine (pure).
 *
 * Parses PostgreSQL `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` output for the
 * SSOT §7.5 over-fetch KNN and verifies the plan matches the Stage 0b baseline
 * (SIA-D41). This is the machine-checkable core of the E-50 acceptance gate
 * (plan §6). It is exercised NOW by ExplainPlanShapeTest against captured/
 * synthetic EXPLAIN JSON; the live EXPLAIN runs later, on the verification
 * cluster, and is fed through this same engine.
 *
 * Required shape (all must hold):
 *   1. an Index Scan using `places_cat_geom`
 *   2. its Index Cond filters on `category_key`
 *   3. its Order By is the KNN operator `<->` (index-provided ordering)
 *   4. NO Seq Scan anywhere
 *   5. NO Sort node anywhere (order comes from the index, not a top-level sort)
 *   6. NO node reports "Rows Removed by Filter" > 0 (zero filter-walk)
 *
 * A partitioned `places` scans under an Append of per-partition Index Scans, so
 * the whole plan tree is traversed, not just the root.
 */
final class ExplainPlanShape
{
    public const COMPOSITE_INDEX = 'places_cat_geom';

    /**
     * @param  array|string  $explain  Decoded EXPLAIN JSON (array) or the raw JSON string.
     * @param  string|null   $expectedCategory  If given, Index Cond must reference this value.
     * @return array{pass:bool,failures:string[],checks:array<string,bool>}
     */
    public function evaluate($explain, ?string $expectedCategory = null): array
    {
        $root = $this->rootPlan($explain);
        $nodes = [];
        $this->flatten($root, $nodes);

        // The composite-index KNN scan node(s).
        $knnScans = array_filter($nodes, function (array $n): bool {
            return ($n['Node Type'] ?? null) === 'Index Scan'
                && ($n['Index Name'] ?? null) === self::COMPOSITE_INDEX;
        });

        $checks = [];

        $checks['composite_index_used'] = $knnScans !== [];

        $checks['index_cond_on_category'] = $this->anyMatches(
            $knnScans,
            fn (array $n) => isset($n['Index Cond']) && str_contains($n['Index Cond'], 'category_key')
        );

        if ($expectedCategory !== null) {
            $checks['index_cond_expected_category'] = $this->anyMatches(
                $knnScans,
                fn (array $n) => isset($n['Index Cond']) && str_contains($n['Index Cond'], $expectedCategory)
            );
        }

        $checks['knn_order_by_in_scan'] = $this->anyMatches(
            $knnScans,
            fn (array $n) => isset($n['Order By']) && str_contains($n['Order By'], '<->')
        );

        $checks['no_seq_scan'] = ! $this->anyMatches(
            $nodes,
            fn (array $n) => ($n['Node Type'] ?? '') === 'Seq Scan'
        );

        $checks['no_sort'] = ! $this->anyMatches(
            $nodes,
            fn (array $n) => in_array($n['Node Type'] ?? '', ['Sort', 'Incremental Sort'], true)
        );

        $checks['zero_rows_removed_by_filter'] = ! $this->anyMatches(
            $nodes,
            fn (array $n) => ((int) ($n['Rows Removed by Filter'] ?? 0)) > 0
        );

        $failures = [];
        foreach ($checks as $name => $ok) {
            if (! $ok) {
                $failures[] = $name;
            }
        }

        return [
            'pass' => $failures === [],
            'failures' => $failures,
            'checks' => $checks,
        ];
    }

    /** Extract the root Plan node from FORMAT JSON output (array or string). */
    private function rootPlan($explain): array
    {
        if (is_string($explain)) {
            $explain = json_decode($explain, true);
            if (! is_array($explain)) {
                throw new \InvalidArgumentException('EXPLAIN payload is not valid JSON.');
            }
        }

        // FORMAT JSON yields a single-element array of { "Plan": {...} }.
        if (array_is_list($explain) && isset($explain[0])) {
            $explain = $explain[0];
        }

        if (! isset($explain['Plan']) || ! is_array($explain['Plan'])) {
            throw new \InvalidArgumentException('EXPLAIN payload has no "Plan" node.');
        }

        return $explain['Plan'];
    }

    /** Depth-first flatten of the plan tree into a flat node list. */
    private function flatten(array $node, array &$out): void
    {
        $out[] = $node;
        foreach ($node['Plans'] ?? [] as $child) {
            if (is_array($child)) {
                $this->flatten($child, $out);
            }
        }
    }

    /** @param callable(array):bool $pred */
    private function anyMatches(array $nodes, callable $pred): bool
    {
        foreach ($nodes as $n) {
            if ($pred($n)) {
                return true;
            }
        }
        return false;
    }
}
