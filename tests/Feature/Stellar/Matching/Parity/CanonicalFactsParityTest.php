<?php

namespace Tests\Feature\Stellar\Matching\Parity;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Stellar\Matching\Baseline\PreConvergenceScoringBaselineTest;
use Tests\Support\Matching\CanonicalFactsParity;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B — the canonical path builds the facts the settled Bridge path builds, and they
 * score and explain identically, over the P1-A0 oracle's own listings and criteria.
 *
 * The listings (the seven committed Stellar fixtures plus A0's variants) and the
 * criteria matrix are taken from PreConvergenceScoringBaselineTest itself, by calling
 * its private listingsFor() / casesFor(), so this suite can never drift from the
 * oracle's matrix and no A0 file is edited. If A0 renames them, this fails loudly.
 *
 * On these real fixtures exactly one registered difference may appear — AD-1
 * (a stored false on pool / garage / waterfront reads as unknown canonically) — and it
 * must not move a single score, place row or explanation.
 */
class CanonicalFactsParityTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;
    use CanonicalFactsParity;

    private ?PreConvergenceScoringBaselineTest $oracle = null;

    /** @return array<string,array{string}> */
    public static function categories(): array
    {
        return PreConvergenceScoringBaselineTest::categories();
    }

    /** @dataProvider categories */
    public function test_canonical_facts_score_and_explain_like_the_bridge_facts(string $slug): void
    {
        $fired    = [];
        $compared = 0;

        foreach ($this->a0('listingsFor', $slug) as $label => $row) {
            $a = $this->legacyFacts($row);
            $b = $this->canonicalFacts($row);

            $this->assertNotNull($b, "{$label}: every committed fixture must resolve to a canonical listing — none may be 'not comparable'");

            foreach (self::factsDifferences($a, $b) as $field => $values) {
                $id = self::allowedDifference($field, $values['legacy'], $values['canonical'], $a);

                $this->assertNotNull($id, sprintf(
                    '%s: undeclared facts difference on %s (origin %s): legacy %s, canonical %s',
                    $label,
                    $field,
                    json_encode(\App\Services\Stellar\Matching\CanonicalListingMatchFactsBuilder::ORIGIN[$field]),
                    var_export($values['legacy'], true),
                    var_export($values['canonical'], true),
                ));
                $fired[$id][] = "{$label}.{$field}";
            }

            foreach ($this->a0('casesFor', $slug, $label) as $case => $criteria) {
                $payload = $this->baselinePayload($criteria);
                $legacy  = $this->outcome($a, $row, $payload);

                $this->assertSame([], self::outcomeDifferences($legacy, $this->outcome($b, $row, $payload)),
                    "{$label}/{$case}: canonical facts must score and explain exactly like the Bridge facts");
                $compared++;
            }

            // Anchor: the legacy side of the comparison IS the live path (score() + build()).
            $anchor = $this->baselinePayload($this->a0('casesFor', $slug, $label)['baseline_city_only']);
            $live   = $this->liveOutcome($row, $anchor);
            $this->assertSame($live, array_intersect_key($this->outcome($a, $row, $anchor), $live),
                "{$label}: the legacy side of the comparison must be the live scoring path");
        }

        $this->assertGreaterThan(0, $compared);
        $this->assertSame([], array_diff(array_keys($fired), ['AD-1']),
            "{$slug}: only AD-1 may fire on the committed fixtures; fired: " . json_encode($fired));
    }

    /** AD-1 genuinely occurs on the real fixtures — the registry entry is not speculative. */
    public function test_ad1_fires_on_the_committed_fixtures(): void
    {
        $row = $this->storeBaselineFixture('residential');

        $differences = self::factsDifferences($this->legacyFacts($row), $this->canonicalFacts($row));

        $this->assertSame(['poolPrivate', 'garage', 'waterfront'], array_keys($differences));
        foreach ($differences as $values) {
            $this->assertSame(['legacy' => false, 'canonical' => null], $values);
        }
    }

    /** @return array<string,mixed> the live path's own outcome for one row. */
    private function liveOutcome(BridgeProperty $row, $payload): array
    {
        $scorer  = new BuyerMatchScorer();
        $builder = new BuyerMatchResultBuilder();
        $batch   = $builder->build($scorer->score($row, $payload), $payload);

        return [
            'listing_key'      => $batch->listingKey,
            'total_score'      => $batch->totalScore,
            'category_scores'  => $batch->categoryScores,
            'important_places' => $batch->importantPlaceMatches,
            'why_this_matches' => $batch->whyThisMatches,
            'tradeoffs'        => $batch->tradeoffs,
            'caution_flags'    => $batch->cautionFlags,
            'missing_data'     => $batch->missingData,
        ];
    }

    /** Call one of the A0 oracle's private matrix methods, unchanged. */
    private function a0(string $method, mixed ...$args): mixed
    {
        $this->oracle ??= new PreConvergenceScoringBaselineTest('test_category_output_matches_the_pre_convergence_snapshot');

        return (fn () => $this->{$method}(...$args))->call($this->oracle);
    }
}
