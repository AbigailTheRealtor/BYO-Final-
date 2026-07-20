<?php

namespace App\Services\Spatial\Gate1;

use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use RuntimeException;

/**
 * Gate1RankSanityEvaluator — the Hybrid Gate 1 runtime validation framework (Option E).
 *
 * PART OF: Phase 2 Batch 2D Part B — Hybrid Gate 1 Harness.
 *
 * WHAT IT DOES
 * ------------
 * SSOT §9.3 defines Gate 1 as a rank-sanity check: rank the nearest candidates per
 * (listing × category) pair and count "embarrassments" — a clearly-wrong POI (a ranked "grocery
 * store" that is a gas station; a "hospital" that is a dentist) surfaced inside the top-N window.
 * Pass at ≤3%.
 *
 * This evaluator runs that check LIVE, in memory, over labelled scenarios:
 *
 *   1. Rank each scenario's candidates through the real `LocationDnaRankingEngine`.
 *   2. Take the top-N by rank.
 *   3. For each, read the scenario's `legitimate` label. An illegitimate POI inside the window
 *      is an embarrassment.
 *   4. Aggregate into an embarrassment rate + per-category breakdown + a named offender list.
 *
 * PROVIDER-AGNOSTIC — CORPUS-READY (Class-2)
 * ------------------------------------------
 * It consumes RAW candidate arrays (the engine's input shape) exactly like PoiBaselineDiffHarness,
 * so the same evaluator runs unchanged at Class-2 against a real `CorpusPoiAdapter` — the ground
 * truth swaps from synthetic labels to the frozen corpus baseline; the pipeline does not move.
 *
 * WHAT IT DELIBERATELY DOES NOT DO (Part B scope — owner decision)
 * ----------------------------------------------------------------
 *   - It does NOT apply `CATEGORY_EXCLUSION_RULES` (they live inside the 1,584-LOC
 *     `LocationDnaPoiDistanceService`; extracting them is out of scope). It measures the PURE
 *     ranking engine's category discrimination. Exclusion-rule integration is Class-2.
 *   - It touches NO corpus, NO PostGIS, NO network. Pure computation over injected scenarios.
 *   - It reads only rank ORDER and POI NAME — never `ranking_score` (erratum E-48).
 *
 * @see \App\Services\LocationDna\LocationDnaRankingEngine
 * @see \App\Services\LocationDna\PoiBaselineDiffHarness
 * @see \Tests\Unit\Spatial\Gate1\Gate1RankSanityEvaluatorTest
 */
final class Gate1RankSanityEvaluator
{
    private readonly LocationDnaRankingEngine $engine;
    private readonly int $topN;
    private readonly float $threshold;

    public function __construct(
        ?LocationDnaRankingEngine $engine = null,
        ?int $topN = null,
        ?float $threshold = null,
    ) {
        $this->engine    = $engine ?? new LocationDnaRankingEngine();
        $this->topN      = $topN ?? (int) config('spatial_gate1.top_n', 3);
        $this->threshold = $threshold ?? (float) config('spatial_gate1.embarrassment_threshold', 0.03);

        if ($this->topN < 1) {
            throw new RuntimeException('Gate1RankSanityEvaluator top_n must be >= 1.');
        }
    }

    public function evaluate(Gate1ScenarioSet $set): Gate1Report
    {
        $evaluatedSlots     = 0;
        $embarrassmentCount = 0;
        $perCategory        = [];
        $offenders          = [];

        foreach ($set->all() as $scenario) {
            $category   = $scenario->category();
            $legitimate = $scenario->legitimacyByName();

            // Rank the raw candidates through the real engine, then read only the ordered names.
            $ranked = $this->engine->rankCandidates(
                $category,
                PoiCandidate::fromGooglePlaces($scenario->rawCandidates()),
                $scenario->sourceLat(),
                $scenario->sourceLng(),
            );

            $window = array_slice($ranked, 0, $this->topN);

            $perCategory[$category] ??= ['slots' => 0, 'embarrassments' => 0];

            foreach ($window as $position => $row) {
                $name = (string) ($row['name'] ?? '');

                // Every ranked name must be one the scenario labelled — the engine passes names
                // through untouched, so a miss is a contract violation, not a soft case. Fail loud.
                if (! array_key_exists($name, $legitimate)) {
                    throw new RuntimeException(
                        "Gate1 scenario '{$scenario->key()}' ranked an unlabelled POI '{$name}'."
                    );
                }

                $evaluatedSlots++;
                $perCategory[$category]['slots']++;

                if ($legitimate[$name] === false) {
                    $embarrassmentCount++;
                    $perCategory[$category]['embarrassments']++;
                    $offenders[] = [
                        'scenario' => $scenario->key(),
                        'category' => $category,
                        'rank'     => $position + 1,
                        'name'     => $name,
                    ];
                }
            }
        }

        ksort($perCategory);

        return new Gate1Report(
            scenarioCount:      $set->count(),
            evaluatedSlots:     $evaluatedSlots,
            embarrassmentCount: $embarrassmentCount,
            topN:               $this->topN,
            threshold:          $this->threshold,
            perCategory:        $perCategory,
            offenders:          $offenders,
        );
    }
}
