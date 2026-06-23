<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\LocationDnaRankingProfileService;
use Tests\TestCase;

/**
 * LocationDnaRankingEngineTest
 *
 * Unit tests for the LocationDnaRankingEngine — no DB, no HTTP.
 *
 * Covers four consumer-reasonableness assertions:
 *   (A) Publix (supermarket, 2383 reviews, 4.6★) outranks B S Food & Gas (3 reviews, 3.7★, nearer)
 *       in the grocery_store category.
 *   (B) Archibald Beach Park (high reviews, named POI type) outranks a municipality-name-only
 *       beach result (locality type) in the beach category.
 *   (C) A developed city park (high reviews, park type) outranks an unnamed natural feature
 *       (natural_feature type, no reviews) in the park category.
 *   (D) A 4.8★ / 300-review restaurant outranks a 5.0★ / 19-review restaurant in the
 *       dining category.
 */
class LocationDnaRankingEngineTest extends TestCase
{
    private LocationDnaRankingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new LocationDnaRankingEngine();
    }

    private const SOURCE_LAT = 27.8307;
    private const SOURCE_LNG = -82.8006;

    /** Build a minimal Google Places result object for testing. */
    private function makePlaceCandidate(
        string $name,
        float  $lat,
        float  $lng,
        array  $types,
        ?float $rating,
        int    $reviewCount,
    ): array {
        $result = [
            'name'     => $name,
            'vicinity' => '123 Test Ave',
            'geometry' => [
                'location' => ['lat' => $lat, 'lng' => $lng],
            ],
            'types' => $types,
        ];

        if ($rating !== null) {
            $result['rating']              = $rating;
            $result['user_ratings_total']  = $reviewCount;
        }

        return $result;
    }

    // =========================================================================
    // (A) Grocery — Publix outranks B S Food & Gas
    // =========================================================================

    /** @test */
    public function publix_outranks_bs_food_and_gas_in_grocery_category(): void
    {
        // B S Food & Gas: nearer (0.72 mi), but tiny review count and low rating
        $bsFoodGas = $this->makePlaceCandidate(
            name:        'B S Food & Gas',
            lat:         27.8368,
            lng:         -82.7940,
            types:       ['supermarket', 'atm', 'grocery_or_supermarket', 'finance', 'food'],
            rating:      3.7,
            reviewCount: 3,
        );

        // Publix: slightly farther (0.93 mi), but 2383 reviews and 4.6★
        $publix = $this->makePlaceCandidate(
            name:        'Publix Super Market on 113th St',
            lat:         27.8268,
            lng:         -82.7880,
            types:       ['supermarket', 'florist', 'liquor_store', 'grocery_or_supermarket', 'food'],
            rating:      4.6,
            reviewCount: 2383,
        );

        $ranked = $this->engine->rankCandidates(
            category:  'grocery_store',
            candidates: [$bsFoodGas, $publix],
            sourceLat:  self::SOURCE_LAT,
            sourceLng:  self::SOURCE_LNG,
        );

        $this->assertCount(2, $ranked);
        $this->assertSame('Publix Super Market on 113th St', $ranked[0]['name'],
            'Publix should rank #1 above B S Food & Gas based on review count and rating');
        $this->assertSame('B S Food & Gas', $ranked[1]['name'],
            'B S Food & Gas should rank #2 despite being nearer');

        // Confirm score fields are present on both results
        $this->assertArrayHasKey('_ranking', $ranked[0]);
        $this->assertArrayHasKey('ranking_score', $ranked[0]['_ranking']);
        $this->assertGreaterThan($ranked[1]['_ranking']['ranking_score'], $ranked[0]['_ranking']['ranking_score'],
            'Publix ranking_score must exceed B S Food & Gas ranking_score');
    }

    // =========================================================================
    // (B) Beach — Named park outranks municipality-name result
    // =========================================================================

    /** @test */
    public function archibald_beach_park_outranks_municipality_name_beach(): void
    {
        // Municipality name: political/locality type — these are penalized
        $municipalityBeach = $this->makePlaceCandidate(
            name:        'Indian Shores',
            lat:         27.8500,
            lng:         -82.8450,
            types:       ['locality', 'political'],
            rating:      4.8,
            reviewCount: 132,
        );

        // Archibald Beach Park: proper named beach park with high reviews
        $archibald = $this->makePlaceCandidate(
            name:        'Archibald Beach Park',
            lat:         27.8480,
            lng:         -82.8420,
            types:       ['park', 'point_of_interest', 'establishment'],
            rating:      4.7,
            reviewCount: 3117,
        );

        $ranked = $this->engine->rankCandidates(
            category:   'beach',
            candidates: [$municipalityBeach, $archibald],
            sourceLat:  self::SOURCE_LAT,
            sourceLng:  self::SOURCE_LNG,
        );

        $this->assertCount(2, $ranked);
        $this->assertSame('Archibald Beach Park', $ranked[0]['name'],
            'Archibald Beach Park (park type, 3117 reviews) should outrank municipality "Indian Shores"');
        $this->assertSame('Indian Shores', $ranked[1]['name'],
            'Municipality-typed "Indian Shores" should rank second');
        $this->assertGreaterThan($ranked[1]['_ranking']['ranking_score'], $ranked[0]['_ranking']['ranking_score'],
            'Archibald ranking_score must exceed Indian Shores ranking_score');
    }

    // =========================================================================
    // (C) Park — Developed city park outranks unnamed natural feature
    // =========================================================================

    /** @test */
    public function developed_city_park_outranks_unnamed_natural_feature(): void
    {
        // Unnamed natural area: natural_feature type, no reviews — typically
        // an island or tidal area accessible only by water
        $naturalFeature = $this->makePlaceCandidate(
            name:        'Big Hook island',
            lat:         27.8365,
            lng:         -82.7950,
            types:       ['natural_feature', 'point_of_interest'],
            rating:      null,
            reviewCount: 0,
        );

        // Developed city park: proper park with high reviews and rating
        $cityPark = $this->makePlaceCandidate(
            name:        'Seminole City Park',
            lat:         27.8398,
            lng:         -82.7987,
            types:       ['park', 'point_of_interest', 'establishment'],
            rating:      4.7,
            reviewCount: 1201,
        );

        $ranked = $this->engine->rankCandidates(
            category:   'park',
            candidates: [$naturalFeature, $cityPark],
            sourceLat:  self::SOURCE_LAT,
            sourceLng:  self::SOURCE_LNG,
        );

        $this->assertCount(2, $ranked);
        $this->assertSame('Seminole City Park', $ranked[0]['name'],
            'Developed city park (park type, 1201 reviews, 4.7★) should outrank unnamed natural feature');
        $this->assertSame('Big Hook island', $ranked[1]['name'],
            'Unnamed natural feature with no reviews should rank second');
        $this->assertGreaterThan($ranked[1]['_ranking']['ranking_score'], $ranked[0]['_ranking']['ranking_score'],
            'City park ranking_score must exceed natural feature ranking_score');
    }

    // =========================================================================
    // (D) Dining — 4.8★/300-review restaurant outranks 5.0★/19-review restaurant
    // =========================================================================

    /** @test */
    public function high_confidence_restaurant_outranks_low_review_five_star_outlier(): void
    {
        // 5.0★ with only 19 reviews — statistically fragile, potential outlier
        $hotdogStand = $this->makePlaceCandidate(
            name:        'Noel\'s Awesome Hotdogs',
            lat:         27.8420,
            lng:         -82.7980,
            types:       ['meal_takeaway', 'restaurant', 'food'],
            rating:      5.0,
            reviewCount: 19,
        );

        // 4.8★ with 300 reviews — well-established, high consumer confidence
        $establishedRestaurant = $this->makePlaceCandidate(
            name:        'Quality Restaurant',
            lat:         27.8410,
            lng:         -82.7970,
            types:       ['restaurant', 'food', 'point_of_interest'],
            rating:      4.8,
            reviewCount: 300,
        );

        $ranked = $this->engine->rankCandidates(
            category:   'restaurant',
            candidates: [$hotdogStand, $establishedRestaurant],
            sourceLat:  self::SOURCE_LAT,
            sourceLng:  self::SOURCE_LNG,
        );

        $this->assertCount(2, $ranked);
        $this->assertSame('Quality Restaurant', $ranked[0]['name'],
            '4.8★/300-review restaurant should outrank 5.0★/19-review restaurant');
        $this->assertSame('Noel\'s Awesome Hotdogs', $ranked[1]['name'],
            '5.0★/19-review outlier should rank second');
        $this->assertGreaterThan($ranked[1]['_ranking']['ranking_score'], $ranked[0]['_ranking']['ranking_score'],
            'Established restaurant ranking_score must exceed low-review outlier ranking_score');
    }

    // =========================================================================
    // Additional structural / contract tests
    // =========================================================================

    /** @test */
    public function rank_candidates_returns_empty_array_for_empty_input(): void
    {
        $result = $this->engine->rankCandidates(
            category:   'grocery_store',
            candidates: [],
            sourceLat:  self::SOURCE_LAT,
            sourceLng:  self::SOURCE_LNG,
        );

        $this->assertSame([], $result);
    }

    /** @test */
    public function ranked_result_carries_all_four_score_fields(): void
    {
        $place = $this->makePlaceCandidate(
            name:        'Test Place',
            lat:         27.8400,
            lng:         -82.8000,
            types:       ['supermarket', 'grocery_or_supermarket'],
            rating:      4.5,
            reviewCount: 500,
        );

        $ranked = $this->engine->rankCandidates(
            category:   'grocery_store',
            candidates: [$place],
            sourceLat:  self::SOURCE_LAT,
            sourceLng:  self::SOURCE_LNG,
        );

        $this->assertCount(1, $ranked);
        $r = $ranked[0]['_ranking'];

        $this->assertArrayHasKey('category_match_score', $r);
        $this->assertArrayHasKey('review_confidence_score', $r);
        $this->assertArrayHasKey('consumer_relevance_score', $r);
        $this->assertArrayHasKey('ranking_score', $r);
        $this->assertArrayHasKey('ranking_reasons_json', $r);

        $this->assertIsFloat($r['category_match_score']);
        $this->assertIsFloat($r['review_confidence_score']);
        $this->assertIsFloat($r['consumer_relevance_score']);
        $this->assertIsFloat($r['ranking_score']);
        $this->assertIsArray($r['ranking_reasons_json']);

        // All scores must be in [0, 100]
        $this->assertGreaterThanOrEqual(0.0, $r['category_match_score']);
        $this->assertLessThanOrEqual(100.0, $r['category_match_score']);
        $this->assertGreaterThanOrEqual(0.0, $r['review_confidence_score']);
        $this->assertLessThanOrEqual(100.0, $r['review_confidence_score']);
        $this->assertGreaterThanOrEqual(0.0, $r['consumer_relevance_score']);
        $this->assertLessThanOrEqual(100.0, $r['consumer_relevance_score']);
        $this->assertGreaterThanOrEqual(0.0, $r['ranking_score']);
        $this->assertLessThanOrEqual(100.0, $r['ranking_score']);
    }

    /** @test */
    public function preferred_types_produce_higher_match_score_than_penalized_types(): void
    {
        $preferred = $this->makePlaceCandidate(
            name:        'Good Match',
            lat:         27.8400,
            lng:         -82.8000,
            types:       ['supermarket', 'grocery_or_supermarket'],
            rating:      4.0,
            reviewCount: 100,
        );

        $penalized = $this->makePlaceCandidate(
            name:        'Bad Match',
            lat:         27.8400,
            lng:         -82.8000,
            types:       ['gas_station', 'convenience_store'],
            rating:      4.0,
            reviewCount: 100,
        );

        $goodRanked = $this->engine->rankCandidates('grocery_store', [$preferred], self::SOURCE_LAT, self::SOURCE_LNG);
        $badRanked  = $this->engine->rankCandidates('grocery_store', [$penalized], self::SOURCE_LAT, self::SOURCE_LNG);

        $this->assertGreaterThan(
            $badRanked[0]['_ranking']['category_match_score'],
            $goodRanked[0]['_ranking']['category_match_score'],
            'Preferred-type match score must exceed penalized-type match score',
        );
    }

    /** @test */
    public function ranking_reasons_json_contains_human_readable_strings(): void
    {
        $place = $this->makePlaceCandidate(
            name:        'Some Store',
            lat:         27.8400,
            lng:         -82.8000,
            types:       ['supermarket', 'grocery_or_supermarket', 'gas_station'],
            rating:      3.5,
            reviewCount: 5,
        );

        $ranked = $this->engine->rankCandidates('grocery_store', [$place], self::SOURCE_LAT, self::SOURCE_LNG);

        $reasons = $ranked[0]['_ranking']['ranking_reasons_json'];
        $this->assertIsArray($reasons);
        $this->assertNotEmpty($reasons, 'Should have at least one ranking reason string');

        foreach ($reasons as $reason) {
            $this->assertIsString($reason, 'Each ranking reason must be a string');
            $this->assertNotEmpty($reason, 'Ranking reason must not be an empty string');
        }
    }

    // =========================================================================
    // Category coverage assertion — every CATEGORIES key must have a dedicated profile
    // =========================================================================

    /**
     * @test
     *
     * Asserts that every canonical category key defined in
     * LocationDnaPoiDistanceService::CATEGORIES has a dedicated entry in
     * LocationDnaRankingProfileService::profiles() (i.e. the key is explicitly
     * present and is NOT served by the 'default' fallback).
     *
     * This prevents future category additions from silently falling through to
     * the generic default profile, which would produce distance-only ranking
     * without any consumer-relevant type signals.
     */
    public function every_poi_category_has_a_dedicated_ranking_profile(): void
    {
        $profiles   = LocationDnaRankingProfileService::profiles();
        $categories = array_keys(LocationDnaPoiDistanceService::CATEGORIES);

        $missing = [];
        foreach ($categories as $categoryKey) {
            if (! array_key_exists($categoryKey, $profiles)) {
                $missing[] = $categoryKey;
            }
        }

        $this->assertEmpty(
            $missing,
            'The following CATEGORIES keys have no dedicated ranking profile and will '
            . "fall back to the generic 'default' profile. Add a profile for each:\n  - "
            . implode("\n  - ", $missing),
        );
    }

    /** @test */
    public function candidates_are_returned_sorted_highest_ranking_score_first(): void
    {
        $worst = $this->makePlaceCandidate(
            name:        'Worst',
            lat:         27.9000,
            lng:         -82.9000,
            types:       ['gas_station'],
            rating:      2.0,
            reviewCount: 1,
        );

        $middle = $this->makePlaceCandidate(
            name:        'Middle',
            lat:         27.8500,
            lng:         -82.8200,
            types:       ['grocery_or_supermarket'],
            rating:      3.5,
            reviewCount: 50,
        );

        $best = $this->makePlaceCandidate(
            name:        'Best',
            lat:         27.8350,
            lng:         -82.8050,
            types:       ['supermarket', 'grocery_or_supermarket'],
            rating:      4.8,
            reviewCount: 1000,
        );

        $ranked = $this->engine->rankCandidates(
            'grocery_store',
            [$worst, $middle, $best],
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $scores = array_column(array_column($ranked, '_ranking'), 'ranking_score');

        for ($i = 0; $i < count($scores) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $scores[$i + 1],
                $scores[$i],
                "Candidate at position {$i} must have ranking_score >= position " . ($i + 1),
            );
        }
    }
}
