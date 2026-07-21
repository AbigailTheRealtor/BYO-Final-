<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C1 (cross-source authority linking).
 *
 * The offline acceptance gate for a matcher result — the authoring-time analogue of the Class-2
 * post-link SQL checks. Pure and deterministic; no DB, no cluster. Returns the same
 * {passed, checks[], failures[]} verdict shape as CorpusImportAcceptance (Batch 2C).
 *
 * Invariants (a link set that fails any of these must NOT be loaded):
 *   • pk_unique          — no duplicate (authority_source, authority_ref) among links
 *     (mirrors place_authority_links PRIMARY KEY)
 *   • method_valid       — every match_method ∈ {spatial_name, exact, manual}
 *   • score_in_range     — every match_score ∈ (0,1]; spatial_name ≥ the similarity floor
 *   • within_radius      — every spatial_name link's place is within the match radius (recomputed)
 *   • no_orphan_place_ref— every linked place's (source, source_ref) exists in the input places
 *   • ambiguous_excluded — no ambiguous authority record appears in the link set
 *   • fully_partitioned  — every authority record is in exactly one bucket (linked/unlinked/ambiguous)
 *
 * @see \Tests\Unit\Spatial\AuthorityLinkAcceptanceTest
 */
final class AuthorityLinkAcceptance
{
    private const SAMPLE_LIMIT = 5;

    private readonly AuthorityLinkMatcher $matcher;
    private readonly NameNormalizer $normalizer;

    public function __construct(?AuthorityLinkMatcher $matcher = null, ?NameNormalizer $normalizer = null)
    {
        $this->matcher = $matcher ?? new AuthorityLinkMatcher();
        $this->normalizer = $normalizer ?? new NameNormalizer();
    }

    /**
     * @param  array{linked: list<array>, unlinked: list<AuthorityRecord>, ambiguous: list<array>} $result
     * @param  list<NormalizedPlaceRecord> $places
     * @return array{passed:bool,checks:array<int,array{name:string,passed:bool,detail:string}>,failures:list<string>}
     */
    public function evaluate(array $result, array $places): array
    {
        $linked = $result['linked'];
        $unlinked = $result['unlinked'];
        $ambiguous = $result['ambiguous'];

        $placeKeys = [];
        foreach ($places as $p) {
            $placeKeys[$p->source . "\x1f" . $p->source_ref] = true;
        }

        $seenPk = [];
        $dupPk = [];
        $badMethod = [];
        $badScore = [];
        $outOfRadius = [];
        $orphan = [];

        foreach ($linked as $l) {
            $a = $l['authority'];
            $p = $l['place'];
            $pk = $a->authority_source . "\x1f" . $a->authority_ref;
            if (isset($seenPk[$pk])) {
                $dupPk[] = "{$a->authority_source}:{$a->authority_ref}";
            } else {
                $seenPk[$pk] = true;
            }

            if (!in_array($l['method'], PlaceAuthorityLinkMaterializer::METHODS, true)) {
                $badMethod[] = "{$a->authority_ref}:{$l['method']}";
            }

            $score = $l['score'];
            $scoreOk = $score > 0.0 && $score <= 1.0
                && ($l['method'] !== 'spatial_name' || $score >= $this->matcher->similarityMin());
            if (!$scoreOk) {
                $badScore[] = "{$a->authority_ref}:{$score}";
            }

            if ($l['method'] === 'spatial_name') {
                $dist = $this->matcher->haversineMeters($a->lat, $a->lon, $p->lat, $p->lon);
                if ($dist > $this->matcher->radiusMeters()) {
                    $outOfRadius[] = sprintf('%s:%.1fm', $a->authority_ref, $dist);
                }
            }

            if (!isset($placeKeys[$p->source . "\x1f" . $p->source_ref])) {
                $orphan[] = "{$p->source}:{$p->source_ref}";
            }
        }

        // Bucket disjointness + completeness.
        $bucketKeys = [];
        $collision = [];
        foreach ([$this->keysOfLinked($linked), $this->keysOf($unlinked), $this->keysOfAmbiguous($ambiguous)] as $keys) {
            foreach ($keys as $k) {
                if (isset($bucketKeys[$k])) {
                    $collision[] = $k;
                } else {
                    $bucketKeys[$k] = true;
                }
            }
        }

        // An ambiguous record must never also be a link (a stricter restatement of the above).
        $ambiguousKeys = array_flip($this->keysOfAmbiguous($ambiguous));
        $ambiguousLinked = [];
        foreach ($this->keysOfLinked($linked) as $k) {
            if (isset($ambiguousKeys[$k])) {
                $ambiguousLinked[] = $k;
            }
        }

        $checks = [];
        $checks[] = $this->check('pk_unique', $dupPk === [], $this->offenders(array_values(array_unique($dupPk)), 'duplicate (authority_source, authority_ref)'));
        $checks[] = $this->check('method_valid', $badMethod === [], $this->offenders($badMethod, 'invalid match_method'));
        $checks[] = $this->check('score_in_range', $badScore === [], $this->offenders($badScore, 'match_score out of range / below floor'));
        $checks[] = $this->check('within_radius', $outOfRadius === [], $this->offenders($outOfRadius, "beyond {$this->matcher->radiusMeters()}m"));
        $checks[] = $this->check('no_orphan_place_ref', $orphan === [], $this->offenders(array_values(array_unique($orphan)), 'link references unknown place'));
        $checks[] = $this->check('ambiguous_excluded', $ambiguousLinked === [], $this->offenders($ambiguousLinked, 'ambiguous record also linked'));
        $checks[] = $this->check('fully_partitioned', $collision === [], $this->offenders(array_values(array_unique($collision)), 'authority record in >1 bucket'));

        $failures = [];
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $failures[] = $c['name'];
            }
        }

        return ['passed' => $failures === [], 'checks' => $checks, 'failures' => $failures];
    }

    /** @param list<array{authority: AuthorityRecord}> $linked @return list<string> */
    private function keysOfLinked(array $linked): array
    {
        return array_map(static fn (array $l): string => $l['authority']->authority_source . "\x1f" . $l['authority']->authority_ref, $linked);
    }

    /** @param list<AuthorityRecord> $records @return list<string> */
    private function keysOf(array $records): array
    {
        return array_map(static fn (AuthorityRecord $r): string => $r->authority_source . "\x1f" . $r->authority_ref, $records);
    }

    /** @param list<array{authority: AuthorityRecord}> $ambiguous @return list<string> */
    private function keysOfAmbiguous(array $ambiguous): array
    {
        return array_map(static fn (array $a): string => $a['authority']->authority_source . "\x1f" . $a['authority']->authority_ref, $ambiguous);
    }

    /** @return array{name:string,passed:bool,detail:string} */
    private function check(string $name, bool $passed, string $detail): array
    {
        return ['name' => $name, 'passed' => $passed, 'detail' => $passed && $detail === '' ? 'ok' : $detail];
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
