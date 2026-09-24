<?php

namespace Tests\Unit\Stellar\Matching;

use App\Services\Stellar\Matching\CanonicalListingMatchFactsBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Stellar\Matching\Baseline\PreConvergenceScoringBaselineTest;
use Tests\Support\Matching\CanonicalFactsParity;
use Tests\Support\Matching\FakeSecondMlsRecord;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B — the facts → score seam does not know which MLS a listing came from.
 *
 * A record in an alien shape (FakeSecondMlsRecord) is translated, test-only, into a
 * CanonicalListing and a residual, then built and scored exactly like the Stellar
 * residential fixture it describes. Native identity differs; scoring does not.
 *
 * Deferred, honestly: "the same ListingKey from two providers is never merged" needs
 * a second MlsProvider case, and arrives with it. The source-level neutrality guard
 * is CanonicalMatchingInputGuardTest.
 */
class SecondProviderScoringNeutralityTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;
    use CanonicalFactsParity;

    public function test_the_twin_builds_the_stellar_facts_except_its_identity(): void
    {
        $stellar = $this->canonicalFacts($this->storeBaselineFixture('residential'));
        $twin    = $this->twinFacts();

        $this->assertNotNull($stellar);
        $this->assertSame(['listingKey'], array_keys(self::factsDifferences($stellar, $twin)));
        $this->assertSame(FakeSecondMlsRecord::RESIDENTIAL_TWIN['ref'], $twin->listingKey);
    }

    public function test_the_twin_scores_and_explains_like_the_stellar_listing_across_the_criteria_matrix(): void
    {
        $row     = $this->storeBaselineFixture('residential');
        $stellar = $this->canonicalFacts($row);
        $twin    = $this->twinFacts();
        $cases   = $this->a0Cases();

        $this->assertNotEmpty($cases);

        foreach ($cases as $case => $criteria) {
            $payload = $this->baselinePayload($criteria);
            $a = $this->outcome($stellar, $row, $payload);
            $b = $this->outcome($twin, $row, $payload);

            $this->assertNull($a['exception'], "{$case}: the Stellar side must score");
            $this->assertSame(['listing_key'], self::outcomeDifferences($a, $b), "{$case}: only the identity may differ");
        }
    }

    public function test_the_builder_ignores_provenance(): void
    {
        $record   = FakeSecondMlsRecord::RESIDENTIAL_TWIN;
        $residual = FakeSecondMlsRecord::toResidual($record);

        $one = CanonicalListingMatchFactsBuilder::build(FakeSecondMlsRecord::toCanonical($record, 'provider_one'), $residual);
        $two = CanonicalListingMatchFactsBuilder::build(FakeSecondMlsRecord::toCanonical($record, 'provider_two'), $residual);

        $this->assertNotSame(
            FakeSecondMlsRecord::toCanonical($record, 'provider_one')->fieldMeta('location.city'),
            FakeSecondMlsRecord::toCanonical($record, 'provider_two')->fieldMeta('location.city'),
        );
        $this->assertEquals($one, $two);
    }

    private function twinFacts()
    {
        $record = FakeSecondMlsRecord::RESIDENTIAL_TWIN;
        $facts  = CanonicalListingMatchFactsBuilder::build(FakeSecondMlsRecord::toCanonical($record), FakeSecondMlsRecord::toResidual($record));

        $this->assertNotNull($facts);

        return $facts;
    }

    /** The A0 oracle's own criteria matrix for the residential fixture, unchanged. */
    private function a0Cases(): array
    {
        $oracle = new PreConvergenceScoringBaselineTest('test_category_output_matches_the_pre_convergence_snapshot');

        return (fn () => $this->casesFor('residential', 'residential'))->call($oracle);
    }
}
