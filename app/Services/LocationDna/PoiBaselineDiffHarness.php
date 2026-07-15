<?php

namespace App\Services\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;

/**
 * PoiBaselineDiffHarness — Phase 1, Deliverable 6 (Batch 4). The dual-run / baseline-diff
 * harness the roadmap requires here and Phase-3a Gate 3 consumes.
 *
 * WHAT IT DOES
 * ------------
 * Runs two POI sources over the same query and reports how their RANKED outputs differ,
 * on two independent axes:
 *
 *   - MEMBERSHIP  — which POIs each side surfaced (by name).
 *   - ORDER       — the relative order of the POIs BOTH sides surfaced.
 *
 * That is the whole comparison. It is exactly what a corpus-vs-Google diff needs, and it is
 * deliberately all it does.
 *
 * WHY NO SCORES (erratum E-48)
 * ----------------------------
 * `LocationDnaRankingEngine` normalises the distance component by the maximum distance across
 * the candidate set it is handed, so `ranking_score` is a property of the SET, not of the POI:
 * swap in a provider that returns a different set and identical POIs score differently. Diffing
 * raw scores would therefore report arithmetic noise as semantic difference. This harness reads
 * only the ORDER the engine produced (array position) and the POI identity — it never reads,
 * emits, stores, or compares `ranking_score` (or any score). The diff structure has no score key.
 *
 * PURITY
 * ------
 * Stateless and side-effect free: it calls the pure `rankCandidates()` and compares outputs in
 * memory. No persistence, no config or fixture mutation, no provider fetch of its own beyond the
 * two injected fetchers. Google is never privileged and never contacted by this class.
 *
 * IDENTITY
 * --------
 * POIs are identified by `name` — the identity the frozen golden-master fixture itself uses
 * (its candidates carry no `place_id`). Duplicate names within one group collapse to set
 * membership; a `place_id`-bearing corpus can key on `place_id` at Gate 3 with no redesign.
 *
 * @see \App\Services\LocationDna\LocationDnaRankingEngine
 * @see \Tests\Unit\Services\LocationDna\PoiBaselineDiffHarnessTest
 */
final class PoiBaselineDiffHarness
{
    private readonly LocationDnaRankingEngine $engine;

    public function __construct(?LocationDnaRankingEngine $engine = null)
    {
        $this->engine = $engine ?? new LocationDnaRankingEngine();
    }

    /**
     * Diff two RAW candidate sets: rank each through the engine, then compare membership + order.
     *
     * @param  array<int, array>  $baselineRaw   Raw provider rows for the baseline side.
     * @param  array<int, array>  $candidateRaw  Raw provider rows for the candidate side.
     * @return array              A GroupDiff (see class docblock / diff structure).
     */
    public function diffRanked(
        string $category,
        array $baselineRaw,
        array $candidateRaw,
        float $lat,
        float $lng,
    ): array {
        return $this->buildGroupDiff(
            $category,
            $this->rankToNames($category, $baselineRaw, $lat, $lng),
            $this->rankToNames($category, $candidateRaw, $lat, $lng),
        );
    }

    /**
     * Diff a live-ranked candidate set against a FROZEN baseline order (a pre-computed list of
     * names in rank order — e.g. the golden-master fixture's `expected` column). This is the
     * self-diff-against-the-baseline shape Gate 3 uses.
     *
     * @param  array<int, array>   $candidateRaw          Raw provider rows for the candidate side.
     * @param  array<int, string>  $baselineOrderedNames  Baseline POI names, already in rank order.
     */
    public function diffAgainstBaselineOrder(
        string $category,
        array $candidateRaw,
        array $baselineOrderedNames,
        float $lat,
        float $lng,
    ): array {
        return $this->buildGroupDiff(
            $category,
            array_values(array_map(static fn ($n): string => (string) $n, $baselineOrderedNames)),
            $this->rankToNames($category, $candidateRaw, $lat, $lng),
        );
    }

    /**
     * Dual-run driver: fetch raw candidates from two adapters for the same query, then diff.
     * This is the seam Phase-3a wires a real `CorpusPoiAdapter` into — no change to this class.
     *
     * @param  array  $meta  The category descriptor (as `NearbyPoiFetcherInterface` expects).
     */
    public function diffFetchers(
        string $category,
        NearbyPoiFetcherInterface $baseline,
        NearbyPoiFetcherInterface $candidate,
        array $meta,
        float $lat,
        float $lng,
    ): array {
        return $this->diffRanked(
            $category,
            $baseline->fetchNearby($lat, $lng, $meta),
            $candidate->fetchNearby($lat, $lng, $meta),
            $lat,
            $lng,
        );
    }

    /**
     * Aggregate a whole corpus of fixture-shaped groups into one DiffReport. Each group is a
     * frozen-baseline self-diff: the candidate side is the group's raw `candidates` ranked live;
     * the baseline side is the group's `expected` names in rank order.
     *
     * @param  iterable<int, array>  $groups  Golden-master-shaped groups
     *         (`key`, `category`, `source_lat`, `source_lng`, `candidates`, `expected`).
     * @return array  A DiffReport (see class docblock / diff structure).
     */
    public function diffCorpus(iterable $groups): array
    {
        $groupDiffs    = [];
        $divergentKeys = [];

        foreach ($groups as $group) {
            $diff = $this->diffAgainstBaselineOrder(
                (string) $group['category'],
                $group['candidates'],
                array_column($group['expected'], 'name'),
                (float) $group['source_lat'],
                (float) $group['source_lng'],
            );

            $key = (string) ($group['key']
                ?? ($group['listing_type'] . '|' . $group['listing_id'] . '|' . $group['category']));

            $diff['key']  = $key;
            $groupDiffs[] = $diff;

            if (! $diff['identical']) {
                $divergentKeys[] = $key;
            }
        }

        return [
            'total_groups'     => count($groupDiffs),
            'identical_groups' => count($groupDiffs) - count($divergentKeys),
            'divergent_groups' => count($divergentKeys),
            'divergent_keys'   => $divergentKeys,
            'groups'           => $groupDiffs,
        ];
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Rank a raw candidate set through the engine and return the POI names IN RANK ORDER.
     * Reads only the array position of the engine's sorted output and each row's `name`.
     * Never reads `_ranking` / `ranking_score`.
     *
     * @param  array<int, array>  $raw
     * @return array<int, string>
     */
    private function rankToNames(string $category, array $raw, float $lat, float $lng): array
    {
        $ranked = $this->engine->rankCandidates(
            $category,
            PoiCandidate::fromGooglePlaces($raw),
            $lat,
            $lng,
        );

        return array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $ranked);
    }

    /**
     * Compare two ordered name lists on membership (set) and relative order (of common members).
     *
     * @param  array<int, string>  $baselineNames   Baseline names, in rank order.
     * @param  array<int, string>  $candidateNames  Candidate names, in rank order.
     */
    private function buildGroupDiff(string $category, array $baselineNames, array $candidateNames): array
    {
        $baseSet = array_keys(array_count_values($baselineNames));
        $candSet = array_keys(array_count_values($candidateNames));

        $onlyBaseline  = array_values(array_diff($baseSet, $candSet));
        $onlyCandidate = array_values(array_diff($candSet, $baseSet));

        // Relative order of the members BOTH sides surfaced — membership-induced rank shifts do
        // not count as order differences (that is what the membership axis is for).
        $commonSet             = array_values(array_intersect($baseSet, $candSet));
        $commonBaselineOrder   = array_values(array_filter($baselineNames, static fn ($n): bool => in_array($n, $commonSet, true)));
        $commonCandidateOrder  = array_values(array_filter($candidateNames, static fn ($n): bool => in_array($n, $commonSet, true)));

        $orderIdentical = $commonBaselineOrder === $commonCandidateOrder;

        $disagreements = [];
        if (! $orderIdentical) {
            $span = max(count($commonBaselineOrder), count($commonCandidateOrder));
            for ($i = 0; $i < $span; $i++) {
                $b = $commonBaselineOrder[$i]  ?? null;
                $c = $commonCandidateOrder[$i] ?? null;
                if ($b !== $c) {
                    $disagreements[] = ['position' => $i + 1, 'baseline' => $b, 'candidate' => $c];
                }
            }
        }

        return [
            'category'   => $category,
            'identical'  => $onlyBaseline === [] && $onlyCandidate === [] && $orderIdentical,
            'membership' => [
                'only_in_baseline'  => $onlyBaseline,
                'only_in_candidate' => $onlyCandidate,
                'common'            => count($commonSet),
            ],
            'order' => [
                'identical'     => $orderIdentical,
                'disagreements' => $disagreements,
            ],
        ];
    }
}
