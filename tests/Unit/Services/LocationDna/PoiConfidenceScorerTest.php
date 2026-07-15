<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\PoiConfidenceScorer;
use Tests\TestCase;

/**
 * PoiConfidenceScorerTest — Batch 3.
 *
 * Pins the approved canonical POI confidence contract (docs/canonical-field-mapping-spec.md §2)
 * as executable fact. The formula was extracted from GooglePlacesPoiAdapter so ONE definition
 * feeds both the adapter's envelope confidence and the persisted
 * property_location_pois.confidence; these assertions are what stops either consumer drifting.
 *
 * Approved contract:
 *   unrated (rating === null) → 0.5   · rated 0 reviews → 0.6
 *   rated 100 reviews → 0.75          · rated 200+ reviews → 0.9
 *   absent/negative review count → treated as 0 reviews · rounded to 3 dp
 */
class PoiConfidenceScorerTest extends TestCase
{
    private function scorer(): PoiConfidenceScorer
    {
        return new PoiConfidenceScorer();
    }

    /** @test */
    public function an_unrated_poi_scores_structural_confidence(): void
    {
        $this->assertSame(0.5, $this->scorer()->score(null, 0));
        // Review volume is irrelevant when there is no rating — no quality signal is fabricated.
        $this->assertSame(0.5, $this->scorer()->score(null, 5000));
    }

    /** @test */
    public function a_rated_poi_with_zero_reviews_scores_the_base(): void
    {
        $this->assertSame(0.6, $this->scorer()->score(4.5, 0));
    }

    /** @test */
    public function a_rated_poi_scales_with_review_volume(): void
    {
        $this->assertSame(0.75, $this->scorer()->score(4.5, 100));
    }

    /** @test */
    public function a_rated_poi_saturates_at_the_ceiling(): void
    {
        $this->assertSame(0.9, $this->scorer()->score(4.5, 200));
        // Beyond saturation the confidence never exceeds the ceiling.
        $this->assertSame(0.9, $this->scorer()->score(4.5, 5000));
    }

    /** @test */
    public function a_negative_review_count_is_treated_as_zero(): void
    {
        $this->assertSame(0.6, $this->scorer()->score(4.5, -10));
    }

    /** @test */
    public function confidence_keys_on_review_volume_not_the_star_rating(): void
    {
        // A 1.0★ and a 5.0★ with the same review count carry the same confidence:
        // confidence expresses how much we trust the signal, not how good the place is.
        $this->assertSame(
            $this->scorer()->score(1.0, 100),
            $this->scorer()->score(5.0, 100),
        );
    }

    /** @test */
    public function confidence_is_rounded_to_three_decimals(): void
    {
        // 50 reviews → 0.6 + 0.3 × (50/200 = 0.25) = 0.675
        $this->assertSame(0.675, $this->scorer()->score(4.0, 50));
    }
}
