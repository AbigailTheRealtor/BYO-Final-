<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * The offline IMPORT acceptance gate. Given the normalized records staged for a
 * corpus_version (and, optionally, the row count the ledger/COPY payload claim),
 * it runs the pre-load invariants and returns a structured verdict. Pure and
 * deterministic — no DB, no cluster; it is the authoring-time analogue of the
 * post-load SQL acceptance queries in the 2C spike.
 *
 * Invariants (owner decision — a load that fails any of these must NOT flip live):
 *   • non_empty            — the corpus staged at least one row
 *   • source_uniform       — every row carries the expected source tag
 *   • identity_present     — source_ref present (half of the natural key)
 *   • identity_unique      — no duplicate (source, source_ref) — mirrors the
 *                            places UNIQUE (corpus_version, source, source_ref)
 *                            so a dup fails offline, not on the live COPY
 *   • category_registered  — every category_key is a registered canonical
 *   • confidence_floor     — every confidence is present and ≥ the floor
 *   • coordinates_valid    — lon∈[-180,180], lat∈[-90,90], finite
 *   • row_count_reconciles — record count == the claimed count (when supplied)
 *
 * Each check reports up to a few offending samples so a failure is diagnosable
 * without dumping the whole corpus.
 */
final class CorpusImportAcceptance
{
    private const SAMPLE_LIMIT = 5;

    public function __construct(
        private readonly OvertureCategoryMap $map = new OvertureCategoryMap(),
        private readonly float $confidenceMin = 0.90,
        private readonly string $expectedSource = OvertureCategoryMap::SOURCE,
    ) {
    }

    /**
     * @param iterable<NormalizedPlaceRecord> $records
     * @return array{passed:bool,row_count:int,checks:array<int,array{name:string,passed:bool,detail:string}>,failures:list<string>}
     */
    public function evaluate(iterable $records, ?int $expectedRowCount = null): array
    {
        $records = is_array($records) ? array_values($records) : iterator_to_array($records, false);
        $count = count($records);

        $registered = array_flip($this->map->canonicalKeys());

        $badSource = [];
        $missingId = [];
        $unregistered = [];
        $lowConfidence = [];
        $badCoords = [];
        $identitySeen = [];
        $duplicateId = [];

        foreach ($records as $r) {
            if ($r->source !== $this->expectedSource) {
                $badSource[] = "{$r->source_ref}:{$r->source}";
            }
            if (trim((string) $r->source_ref) === '') {
                $missingId[] = $r->category_key;
            }

            // Natural key (source, source_ref) must be unique — the places table
            // enforces it; catch it here before the live COPY fails on it.
            $identity = $r->source . "\x1f" . $r->source_ref;
            if (isset($identitySeen[$identity])) {
                $duplicateId[] = "{$r->source}:{$r->source_ref}";
            } else {
                $identitySeen[$identity] = true;
            }
            if (!isset($registered[$r->category_key])) {
                $unregistered[] = $r->category_key;
            }
            if ($r->confidence === null || $r->confidence < $this->confidenceMin) {
                $lowConfidence[] = $r->source_ref . ':' . ($r->confidence === null ? 'null' : (string) $r->confidence);
            }
            if (!$this->coordinateValid($r->lon, $r->lat)) {
                $badCoords[] = sprintf('%s:(%s,%s)', $r->source_ref, $r->lon, $r->lat);
            }
        }

        $checks = [];
        $checks[] = $this->check('non_empty', $count > 0, $count > 0 ? "{$count} rows" : 'no rows staged');
        $checks[] = $this->check('source_uniform', $badSource === [], $this->offenders($badSource, "expected source [{$this->expectedSource}]"));
        $checks[] = $this->check('identity_present', $missingId === [], $this->offenders($missingId, 'rows missing source_ref'));
        $checks[] = $this->check('identity_unique', $duplicateId === [], $this->offenders(array_values(array_unique($duplicateId)), 'duplicate (source, source_ref)'));
        $checks[] = $this->check('category_registered', $unregistered === [], $this->offenders(array_values(array_unique($unregistered)), 'unregistered category_key'));
        $checks[] = $this->check('confidence_floor', $lowConfidence === [], $this->offenders($lowConfidence, sprintf('below floor %.2f', $this->confidenceMin)));
        $checks[] = $this->check('coordinates_valid', $badCoords === [], $this->offenders($badCoords, 'out-of-range coordinates'));

        if ($expectedRowCount !== null) {
            $ok = $expectedRowCount === $count;
            $checks[] = $this->check(
                'row_count_reconciles',
                $ok,
                $ok ? "records == claimed ({$count})" : "records {$count} != claimed {$expectedRowCount}"
            );
        }

        $failures = [];
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $failures[] = $c['name'];
            }
        }

        return [
            'passed'    => $failures === [],
            'row_count' => $count,
            'checks'    => $checks,
            'failures'  => $failures,
        ];
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
