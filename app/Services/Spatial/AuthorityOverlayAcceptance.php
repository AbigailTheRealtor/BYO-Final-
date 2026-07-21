<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * The offline acceptance gate for a normalized authority-overlay batch — the authoring-time
 * analogue of the Class-2 post-stage SQL checks. Pure and deterministic; no DB, no cluster. Returns
 * the same {passed, checks[], failures[]} verdict shape as {@see CorpusImportAcceptance} (2C) and
 * {@see AuthorityLinkAcceptance} (C1).
 *
 * Invariants (a batch that fails any of these must NOT be staged):
 *   • non_empty            — at least one record was produced
 *   • source_uniform       — every record carries the expected authority_source
 *   • ref_present          — authority_ref present (half of the place_authority_links PK)
 *   • ref_unique           — no duplicate (authority_source, authority_ref) — mirrors the
 *                            place_authority_links PRIMARY KEY, and places' natural key for base
 *                            sources; a dup fails offline, not on the live load
 *   • name_present         — a non-empty name (the C1 linker matches on normalised name)
 *   • coordinates_valid    — lon∈[-180,180], lat∈[-90,90], finite
 *   • metric_in_domain     — a PRESENT authority_metric is within the source's domain; NULL is
 *                            allowed (identity is authoritative even when the metric is suppressed —
 *                            e.g. CMS "Not Available"); membership sources declare no domain
 *   • row_count_reconciles — record count == the claimed count (when supplied)
 *
 * @see \Tests\Unit\Spatial\AuthorityOverlayAcceptanceTest
 */
final class AuthorityOverlayAcceptance
{
    private const SAMPLE_LIMIT = 5;

    private readonly string $expectedSource;
    /** @var array{0: float, 1: float}|null */
    private readonly ?array $metricDomain;

    /**
     * @param array{0: float, 1: float}|null $metricDomain inclusive [min,max], or null (no metric)
     */
    public function __construct(string $expectedSource, ?array $metricDomain = null)
    {
        $this->expectedSource = $expectedSource;
        $this->metricDomain = $metricDomain;
    }

    /**
     * @param  iterable<AuthorityRecord> $records
     * @return array{passed:bool,row_count:int,checks:array<int,array{name:string,passed:bool,detail:string}>,failures:list<string>}
     */
    public function evaluate(iterable $records, ?int $expectedRowCount = null): array
    {
        $records = is_array($records) ? array_values($records) : iterator_to_array($records, false);
        $count = count($records);

        $badSource = [];
        $missingRef = [];
        $missingName = [];
        $badCoords = [];
        $badMetric = [];
        $seen = [];
        $duplicate = [];

        foreach ($records as $r) {
            if ($r->authority_source !== $this->expectedSource) {
                $badSource[] = "{$r->authority_ref}:{$r->authority_source}";
            }
            if (trim((string) $r->authority_ref) === '') {
                $missingRef[] = $r->authority_source . ':(blank)';
            }

            $identity = $r->authority_source . "\x1f" . $r->authority_ref;
            if (isset($seen[$identity])) {
                $duplicate[] = "{$r->authority_source}:{$r->authority_ref}";
            } else {
                $seen[$identity] = true;
            }

            if (trim((string) $r->name) === '') {
                $missingName[] = "{$r->authority_source}:{$r->authority_ref}";
            }
            if (!$this->coordinateValid($r->lon, $r->lat)) {
                $badCoords[] = sprintf('%s:(%s,%s)', $r->authority_ref, $r->lon, $r->lat);
            }
            if ($this->metricDomain !== null && $r->authority_metric !== null) {
                [$min, $max] = $this->metricDomain;
                if ($r->authority_metric < $min || $r->authority_metric > $max) {
                    $badMetric[] = "{$r->authority_ref}:{$r->authority_metric}";
                }
            }
        }

        $domainLabel = $this->metricDomain === null
            ? 'no metric domain'
            : sprintf('metric outside [%s, %s]', $this->metricDomain[0], $this->metricDomain[1]);

        $checks = [];
        $checks[] = $this->check('non_empty', $count > 0, $count > 0 ? "{$count} records" : 'no records produced');
        $checks[] = $this->check('source_uniform', $badSource === [], $this->offenders($badSource, "expected source [{$this->expectedSource}]"));
        $checks[] = $this->check('ref_present', $missingRef === [], $this->offenders($missingRef, 'records missing authority_ref'));
        $checks[] = $this->check('ref_unique', $duplicate === [], $this->offenders(array_values(array_unique($duplicate)), 'duplicate (authority_source, authority_ref)'));
        $checks[] = $this->check('name_present', $missingName === [], $this->offenders($missingName, 'records missing name'));
        $checks[] = $this->check('coordinates_valid', $badCoords === [], $this->offenders($badCoords, 'out-of-range coordinates'));
        $checks[] = $this->check('metric_in_domain', $badMetric === [], $this->offenders($badMetric, $domainLabel));

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

    private function coordinateValid(float $lon, float $lat): bool
    {
        return is_finite($lon) && is_finite($lat)
            && $lon >= -180.0 && $lon <= 180.0
            && $lat >= -90.0 && $lat <= 90.0;
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
