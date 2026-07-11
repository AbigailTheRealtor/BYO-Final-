<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use PHPUnit\Framework\TestCase;

/**
 * LocationDnaRankingProfileValidationTest
 *
 * Validates that every POI category's ranking profile produces sensible,
 * consumer-relevant orderings when run against realistic candidate sets.
 *
 * Each test constructs a two-candidate contrast scenario:
 *   - "near/mediocre"  — closer, low review count, lower rating
 *   - "far/quality"    — slightly farther, high review count, high rating
 *
 * Quality-dominant categories assert the quality candidate ranks first.
 * Distance-dominant categories (school, gas_station, transit_station)
 * assert the nearest candidate ranks first, confirming proximity weighting.
 *
 * All tests run offline: no DB, no HTTP, no Google API calls.
 *
 * Reference property: 27.9000, -82.5000 (Tampa, FL area)
 * Near candidate:     27.9015, -82.5000 (~0.10 mi north)
 * Far candidate:      27.9058, -82.5000 (~0.40 mi north)
 * Mid candidate:      27.9044, -82.5000 (~0.30 mi north, used for gas_station)
 */
class LocationDnaRankingProfileValidationTest extends TestCase
{
    private const SOURCE_LAT = 27.9000;
    private const SOURCE_LNG = -82.5000;

    private const NEAR_LAT = 27.9015;
    private const FAR_LAT  = 27.9058;
    private const MID_LAT  = 27.9044;

    private LocationDnaRankingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new LocationDnaRankingEngine();
    }

    // =========================================================================
    // Shared helpers
    // =========================================================================

    /**
     * Build a minimal Google Places result array.
     *
     * Required keys: lat, lng, types, rating, user_ratings_total, name.
     */
    private function makeCandidate(array $overrides = []): array
    {
        return array_merge([
            'name'               => 'Test Place',
            'geometry'           => [
                'location' => [
                    'lat' => self::NEAR_LAT,
                    'lng' => self::SOURCE_LNG,
                ],
            ],
            'types'              => ['point_of_interest', 'establishment'],
            'rating'             => 4.0,
            'user_ratings_total' => 50,
        ], $overrides);
    }

    /**
     * Build a candidate placed at a specific latitude (longitude stays at source).
     */
    private function candidateAt(float $lat, array $overrides = []): array
    {
        $base = $overrides;
        $base['geometry'] = ['location' => ['lat' => $lat, 'lng' => self::SOURCE_LNG]];
        return $this->makeCandidate($base);
    }

    /**
     * Run the engine and return [topRankedCandidate, scoreDelta].
     * scoreDelta = winner score - runner-up score (always positive).
     */
    private function rank(string $category, array $candidates): array
    {
        $ranked = $this->engine->rankCandidates(
            $category,
            PoiCandidate::fromGooglePlaces($candidates),
            self::SOURCE_LAT,
            self::SOURCE_LNG
        );

        $this->assertNotEmpty($ranked, "rankCandidates must return non-empty results for category '{$category}'");

        $top  = $ranked[0];
        $delta = count($ranked) > 1
            ? $top['_ranking']['ranking_score'] - $ranked[1]['_ranking']['ranking_score']
            : 0.0;

        return [$top, $delta];
    }

    // =========================================================================
    // 1. grocery_store — quality dominant
    //    near: 10 reviews, 3.5★ | far: 600 reviews, 4.7★
    //    Expected: far/quality candidate wins (delta ~34.89)
    // =========================================================================

    /** @test */
    public function grocery_store_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Corner Deli (gas station)',
            'types'              => ['supermarket'],
            'rating'             => 3.5,
            'user_ratings_total' => 10,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Publix Super Market',
            'types'              => ['supermarket', 'grocery_or_supermarket'],
            'rating'             => 4.7,
            'user_ratings_total' => 600,
        ]);

        [$top, $delta] = $this->rank('grocery_store', [$near, $far]);

        $this->assertSame('Publix Super Market', $top['name'],
            'High-volume, high-rated supermarket must outrank nearby mediocre option');
        $this->assertGreaterThan(5.0, $delta, 'Score delta must be meaningful (>5 points)');
    }

    // =========================================================================
    // 2. restaurant — quality dominant
    //    near: 8 reviews, 3.4★ | far: 400 reviews, 4.6★
    //    Expected: far/quality wins (delta ~34.63)
    // =========================================================================

    /** @test */
    public function restaurant_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Greasy Spoon Diner',
            'types'              => ['restaurant'],
            'rating'             => 3.4,
            'user_ratings_total' => 8,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Columbia Restaurant',
            'types'              => ['restaurant', 'meal_delivery'],
            'rating'             => 4.6,
            'user_ratings_total' => 400,
        ]);

        [$top, $delta] = $this->rank('restaurant', [$near, $far]);

        $this->assertSame('Columbia Restaurant', $top['name'],
            'High-rated, well-reviewed restaurant must outrank nearby low-quality option');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 3. top_rated_dining — quality dominant, strong review weight
    //    near: 5 reviews, 3.3★ | far: 800 reviews, 4.8★
    //    Expected: far/quality wins (delta ~51.02)
    // =========================================================================

    /** @test */
    public function top_rated_dining_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Fast Taco',
            'types'              => ['restaurant'],
            'rating'             => 3.3,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Bern\'s Steak House',
            'types'              => ['restaurant', 'cafe'],
            'rating'             => 4.8,
            'user_ratings_total' => 800,
        ]);

        [$top, $delta] = $this->rank('top_rated_dining', [$near, $far]);

        $this->assertSame('Bern\'s Steak House', $top['name'],
            'Top-rated dining must strongly favor quality over proximity');
        $this->assertGreaterThan(10.0, $delta);
    }

    // =========================================================================
    // 4. beach — quality dominant (review volume signals recognized beach)
    //    near: 10 reviews, 3.5★ | far: 1200 reviews, 4.6★
    //    Expected: far/quality wins (delta ~39.43)
    // =========================================================================

    /** @test */
    public function beach_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Unnamed Sand Patch',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 10,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Clearwater Beach',
            'types'              => ['natural_feature', 'park', 'point_of_interest'],
            'rating'             => 4.6,
            'user_ratings_total' => 1200,
        ]);

        [$top, $delta] = $this->rank('beach', [$near, $far]);

        $this->assertSame('Clearwater Beach', $top['name'],
            'Named, well-reviewed beach must outrank an obscure nearby sand patch');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 5. beach_access — quality dominant
    //    near: 5 reviews, 3.5★ | far: 120 reviews, 4.5★
    //    Expected: far/quality wins (delta ~24.32)
    // =========================================================================

    /** @test */
    public function beach_access_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Unmarked Alley Access',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Sunset Beach Public Access #7',
            'types'              => ['point_of_interest', 'park', 'natural_feature'],
            'rating'             => 4.5,
            'user_ratings_total' => 120,
        ]);

        [$top, $delta] = $this->rank('beach_access', [$near, $far]);

        $this->assertSame('Sunset Beach Public Access #7', $top['name'],
            'Named, reviewed beach access must outrank a nearby obscure access point');
        $this->assertGreaterThan(3.0, $delta);
    }

    // =========================================================================
    // 6. park — quality dominant
    //    near: 10 reviews, 3.5★ | far: 500 reviews, 4.6★
    //    Expected: far/quality wins (delta ~36.27)
    // =========================================================================

    /** @test */
    public function park_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Neglected Lot',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 10,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Hillsborough River State Park',
            'types'              => ['park', 'point_of_interest'],
            'rating'             => 4.6,
            'user_ratings_total' => 500,
        ]);

        [$top, $delta] = $this->rank('park', [$near, $far]);

        $this->assertSame('Hillsborough River State Park', $top['name'],
            'Well-reviewed county/city park must outrank a nearby low-quality green space');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 7. waterfront_park — quality dominant
    //    near: 5 reviews, 3.5★ | far: 200 reviews, 4.5★
    //    Expected: far/quality wins (delta ~32.54)
    // =========================================================================

    /** @test */
    public function waterfront_park_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Unnamed Waterfront Strip',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Ballast Point Park',
            'types'              => ['park', 'point_of_interest'],
            'rating'             => 4.5,
            'user_ratings_total' => 200,
        ]);

        [$top, $delta] = $this->rank('waterfront_park', [$near, $far]);

        $this->assertSame('Ballast Point Park', $top['name'],
            'Established waterfront park with many reviews must outrank an obscure nearby option');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 8. dog_park — quality dominant
    //    near: 5 reviews, 3.5★ | far: 80 reviews, 4.5★
    //    Expected: far/quality wins (delta ~28.94)
    // =========================================================================

    /** @test */
    public function dog_park_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Muddy Yard Dog Run',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Al Lopez Park Off-Leash Area',
            'types'              => ['park', 'point_of_interest'],
            'rating'             => 4.5,
            'user_ratings_total' => 80,
        ]);

        [$top, $delta] = $this->rank('dog_park', [$near, $far]);

        $this->assertSame('Al Lopez Park Off-Leash Area', $top['name'],
            'Established dog park with solid reviews must outrank a nearby unknown option');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 9. school — DISTANCE dominant (distance_weight=0.40)
    //    near: 8 reviews, 3.8★ | far: 80 reviews, 4.5★
    //    Expected: NEAREST wins — distance dominates for school proximity (delta ~14.60)
    // =========================================================================

    /** @test */
    public function school_nearest_candidate_wins_confirming_distance_dominance(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Roosevelt Elementary School',
            'types'              => ['school', 'point_of_interest'],
            'rating'             => 3.8,
            'user_ratings_total' => 8,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Academy of Excellence',
            'types'              => ['school'],
            'rating'             => 4.5,
            'user_ratings_total' => 80,
        ]);

        [$top, $delta] = $this->rank('school', [$near, $far]);

        $this->assertSame('Roosevelt Elementary School', $top['name'],
            'For schools, proximity is the dominant consumer value — nearest must win');
        $this->assertGreaterThan(3.0, $delta,
            'Distance advantage must produce a meaningful score gap');
    }

    // =========================================================================
    // 10. hospital — quality dominant
    //     near: 5 reviews, 3.3★ | far: 300 reviews, 4.4★
    //     Expected: far/quality wins (delta ~19.56)
    // =========================================================================

    /** @test */
    public function hospital_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Small Urgent Clinic',
            'types'              => ['hospital'],
            'rating'             => 3.3,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Tampa General Hospital',
            'types'              => ['hospital', 'health'],
            'rating'             => 4.4,
            'user_ratings_total' => 300,
        ]);

        [$top, $delta] = $this->rank('hospital', [$near, $far]);

        $this->assertSame('Tampa General Hospital', $top['name'],
            'Well-reviewed, reputable hospital must outrank a nearby low-rated clinic');
        $this->assertGreaterThan(3.0, $delta);
    }

    // =========================================================================
    // 11. pharmacy — quality dominant
    //     near: 5 reviews, 3.5★ | far: 200 reviews, 4.5★
    //     Expected: far/quality wins (delta ~16.44)
    // =========================================================================

    /** @test */
    public function pharmacy_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Generic Rx Corner',
            'types'              => ['pharmacy'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'CVS Pharmacy',
            'types'              => ['pharmacy', 'drugstore'],
            'rating'             => 4.5,
            'user_ratings_total' => 200,
        ]);

        [$top, $delta] = $this->rank('pharmacy', [$near, $far]);

        $this->assertSame('CVS Pharmacy', $top['name'],
            'Established pharmacy chain with strong reviews must outrank nearby low-rated option');
        $this->assertGreaterThan(3.0, $delta);
    }

    // =========================================================================
    // 12. golf_course — quality dominant
    //     near: 5 reviews, 3.5★ | far: 300 reviews, 4.5★
    //     Expected: far/quality wins (delta ~24.86)
    // =========================================================================

    /** @test */
    public function golf_course_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Scrubby Links',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'TPC Tampa Bay',
            'types'              => ['point_of_interest', 'establishment'],
            'rating'             => 4.5,
            'user_ratings_total' => 300,
        ]);

        [$top, $delta] = $this->rank('golf_course', [$near, $far]);

        $this->assertSame('TPC Tampa Bay', $top['name'],
            'Premium golf course with high review volume must outrank a nearby low-rated option');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 13. marina — quality dominant
    //     near: 5 reviews, 3.5★ | far: 200 reviews, 4.5★
    //     Expected: far/quality wins (delta ~23.04)
    // =========================================================================

    /** @test */
    public function marina_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Rundown Dock',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Harbour Island Marina',
            'types'              => ['point_of_interest', 'establishment'],
            'rating'             => 4.5,
            'user_ratings_total' => 200,
        ]);

        [$top, $delta] = $this->rank('marina', [$near, $far]);

        $this->assertSame('Harbour Island Marina', $top['name'],
            'Full-service marina with solid reviews must outrank a nearby dilapidated dock');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 14. boat_ramp — quality dominant (distance_weight=0.30 does not override)
    //     near: 5 reviews, 3.5★ | far: 80 reviews, 4.5★
    //     Expected: far/quality wins (delta ~11.97)
    // =========================================================================

    /** @test */
    public function boat_ramp_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Cracked Concrete Ramp',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Picnic Island Boat Ramp',
            'types'              => ['point_of_interest', 'park'],
            'rating'             => 4.5,
            'user_ratings_total' => 80,
        ]);

        [$top, $delta] = $this->rank('boat_ramp', [$near, $far]);

        $this->assertSame('Picnic Island Boat Ramp', $top['name'],
            'Maintained public boat ramp with good reviews must outrank a nearby neglected ramp');
        $this->assertGreaterThan(3.0, $delta);
    }

    // =========================================================================
    // 15. gym — quality dominant
    //     near: 5 reviews, 3.5★ | far: 200 reviews, 4.6★
    //     Expected: far/quality wins (delta ~27.02)
    // =========================================================================

    /** @test */
    public function gym_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Dusty Weights Room',
            'types'              => ['gym'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'LA Fitness',
            'types'              => ['gym', 'health', 'point_of_interest'],
            'rating'             => 4.6,
            'user_ratings_total' => 200,
        ]);

        [$top, $delta] = $this->rank('gym', [$near, $far]);

        $this->assertSame('LA Fitness', $top['name'],
            'Established gym with high reviews must outrank a nearby low-rated option');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 16. shopping_center — quality dominant
    //     near: 5 reviews, 3.5★ | far: 600 reviews, 4.6★
    //     Expected: far/quality wins (delta ~28.82)
    // =========================================================================

    /** @test */
    public function shopping_center_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Struggling Strip Mall',
            'types'              => ['point_of_interest'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'International Plaza and Bay Street',
            'types'              => ['shopping_mall', 'department_store', 'point_of_interest'],
            'rating'             => 4.6,
            'user_ratings_total' => 600,
        ]);

        [$top, $delta] = $this->rank('shopping_center', [$near, $far]);

        $this->assertSame('International Plaza and Bay Street', $top['name'],
            'Full shopping mall with large review volume must outrank a nearby mediocre strip mall');
        $this->assertGreaterThan(5.0, $delta);
    }

    // =========================================================================
    // 17. gas_station — DISTANCE dominant (distance_weight=0.55)
    //     near: 2 reviews, 3.2★, 0.10 mi | farther: 180 reviews, 4.1★, 0.30 mi
    //     Expected: NEAREST wins — distance weight (0.55) overcomes quality gap (delta ~10.83)
    //
    //     Note: The nearest wins even though the farther station has 90× more reviews
    //     and a higher rating. This is the intended behavior — gas stations are a
    //     convenience utility where proximity (one block vs three) determines usefulness.
    // =========================================================================

    /** @test */
    public function gas_station_nearest_candidate_wins_confirming_distance_dominance(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Shell Station',
            'types'              => ['gas_station'],
            'rating'             => 3.2,
            'user_ratings_total' => 2,
        ]);

        $far = $this->candidateAt(self::MID_LAT, [
            'name'               => 'Chevron with TechTron',
            'types'              => ['gas_station', 'fuel'],
            'rating'             => 4.1,
            'user_ratings_total' => 180,
        ]);

        [$top, $delta] = $this->rank('gas_station', [$near, $far]);

        $this->assertSame('Shell Station', $top['name'],
            'For gas stations, proximity is the dominant value — nearest must win even with far fewer reviews');
        $this->assertGreaterThan(2.0, $delta,
            'Distance weight (0.55) must produce a meaningful score gap over the quality candidate');
    }

    // =========================================================================
    // 18. transit_station — DISTANCE dominant (distance_weight=0.65)
    //     near: 5 reviews, 3.5★ | far: 200 reviews, 4.5★
    //     Expected: NEAREST wins — distance overwhelmingly dominates (delta ~29.18)
    // =========================================================================

    /** @test */
    public function transit_station_nearest_candidate_wins_confirming_distance_dominance(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Marion Transit Center Stop A',
            'types'              => ['transit_station'],
            'rating'             => 3.5,
            'user_ratings_total' => 5,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Tampa Union Station',
            'types'              => ['transit_station', 'bus_station'],
            'rating'             => 4.5,
            'user_ratings_total' => 200,
        ]);

        [$top, $delta] = $this->rank('transit_station', [$near, $far]);

        $this->assertSame('Marion Transit Center Stop A', $top['name'],
            'Transit stops a few blocks farther are nearly useless — proximity must dominate');
        $this->assertGreaterThan(10.0, $delta,
            'Distance weight (0.65) must produce a large score gap for transit stations');
    }

    // =========================================================================
    // 19. coffee_shop — quality dominant
    //     near: 12 reviews, 3.5★, 0.10 mi | far: 820 reviews, 4.6★, 0.40 mi
    //     Expected: far/quality wins (delta ~13.80)
    // =========================================================================

    /** @test */
    public function coffee_shop_quality_candidate_outranks_nearby_mediocre(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Gas Station Drip Coffee',
            'types'              => ['cafe'],
            'rating'             => 3.5,
            'user_ratings_total' => 12,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Buddy Brew Coffee',
            'types'              => ['cafe', 'coffee_shop'],
            'rating'             => 4.6,
            'user_ratings_total' => 820,
        ]);

        [$top, $delta] = $this->rank('coffee_shop', [$near, $far]);

        $this->assertSame('Buddy Brew Coffee', $top['name'],
            'High-quality independent café with 820 reviews must outrank nearby drive-through drip coffee');
        $this->assertGreaterThan(3.0, $delta);
    }

    // =========================================================================
    // ranking_reasons_json spot-checks
    // =========================================================================

    /**
     * @test
     * Spot-check: grocery_store top candidate must have non-empty ranking_reasons_json
     * containing at least one positive signal (string starting with "+").
     */
    public function grocery_store_top_candidate_has_positive_ranking_reason(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Budget Mart',
            'types'              => ['supermarket'],
            'rating'             => 3.4,
            'user_ratings_total' => 8,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Whole Foods Market',
            'types'              => ['supermarket', 'grocery_or_supermarket'],
            'rating'             => 4.6,
            'user_ratings_total' => 800,
        ]);

        $ranked = $this->engine->rankCandidates('grocery_store', PoiCandidate::fromGooglePlaces([$near, $far]), self::SOURCE_LAT, self::SOURCE_LNG);

        $top     = $ranked[0];
        $reasons = $top['_ranking']['ranking_reasons_json'] ?? [];

        $this->assertNotEmpty($reasons,
            'ranking_reasons_json for top grocery_store candidate must not be empty');

        $positiveReasons = array_filter($reasons, fn($r) => str_starts_with($r, '+'));
        $this->assertNotEmpty($positiveReasons,
            'At least one positive signal (starting with "+") must be present for the top grocery_store result');

        $this->assertSame('Whole Foods Market', $top['name']);
    }

    /**
     * @test
     * Spot-check: coffee_shop top candidate must have non-empty ranking_reasons_json
     * with at least one positive signal. Low-quality near candidate must have at least
     * one negative signal (string starting with "-").
     */
    public function coffee_shop_reasons_json_contains_positive_and_negative_signals(): void
    {
        $near = $this->candidateAt(self::NEAR_LAT, [
            'name'               => 'Mediocre Drive-Through',
            'types'              => ['cafe'],
            'rating'             => 3.2,
            'user_ratings_total' => 2,
        ]);

        $far = $this->candidateAt(self::FAR_LAT, [
            'name'               => 'Roasting Room',
            'types'              => ['cafe', 'coffee_shop'],
            'rating'             => 4.7,
            'user_ratings_total' => 950,
        ]);

        $ranked = $this->engine->rankCandidates('coffee_shop', PoiCandidate::fromGooglePlaces([$near, $far]), self::SOURCE_LAT, self::SOURCE_LNG);

        $top    = $ranked[0];
        $bottom = $ranked[1];

        $topReasons    = $top['_ranking']['ranking_reasons_json'] ?? [];
        $bottomReasons = $bottom['_ranking']['ranking_reasons_json'] ?? [];

        $this->assertNotEmpty($topReasons,
            'ranking_reasons_json must be non-empty for the top coffee_shop candidate');

        $positiveSignals = array_filter($topReasons, fn($r) => str_starts_with($r, '+'));
        $this->assertNotEmpty($positiveSignals,
            'Top coffee_shop candidate must carry at least one "+" signal in ranking_reasons_json');

        $negativeSignals = array_filter($bottomReasons, fn($r) => str_starts_with($r, '-'));
        $this->assertNotEmpty($negativeSignals,
            'Low-quality nearby candidate must carry at least one "-" signal in ranking_reasons_json');

        $this->assertSame('Roasting Room', $top['name']);
    }

    // =========================================================================
    // Edge case: empty candidates list returns empty array
    // =========================================================================

    /** @test */
    public function rank_candidates_returns_empty_array_for_empty_input(): void
    {
        $result = $this->engine->rankCandidates('grocery_store', [], self::SOURCE_LAT, self::SOURCE_LNG);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Edge case: single candidate is returned with scores attached
    // =========================================================================

    /** @test */
    public function rank_candidates_returns_single_candidate_with_scores(): void
    {
        $candidate = $this->makeCandidate([
            'name'               => 'Only Pharmacy',
            'types'              => ['pharmacy', 'drugstore'],
            'rating'             => 4.2,
            'user_ratings_total' => 75,
        ]);

        $ranked = $this->engine->rankCandidates('pharmacy', PoiCandidate::fromGooglePlaces([$candidate]), self::SOURCE_LAT, self::SOURCE_LNG);

        $this->assertCount(1, $ranked);
        $this->assertArrayHasKey('_ranking', $ranked[0]);
        $this->assertArrayHasKey('ranking_score',            $ranked[0]['_ranking']);
        $this->assertArrayHasKey('category_match_score',     $ranked[0]['_ranking']);
        $this->assertArrayHasKey('review_confidence_score',  $ranked[0]['_ranking']);
        $this->assertArrayHasKey('consumer_relevance_score', $ranked[0]['_ranking']);
        $this->assertArrayHasKey('ranking_reasons_json',     $ranked[0]['_ranking']);
        $this->assertGreaterThan(0.0, $ranked[0]['_ranking']['ranking_score']);
    }
}
