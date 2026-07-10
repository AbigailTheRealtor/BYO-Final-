<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\PoiCandidate;
use Tests\TestCase;

/**
 * Phase 1 — `PoiCandidate` coercion semantics.
 *
 * Every assertion here pins a cast that was COPIED from `LocationDnaRankingEngine`, not
 * chosen. Where the engine is lenient, this object is lenient in the same direction and to
 * the same degree. The corpus-level proof lives in `PoiCandidateGoldenMasterParityTest`;
 * this file documents the edges that 995 rows of real Google data never happened to hit.
 */
class PoiCandidateTest extends TestCase
{
    /** A well-formed Nearby Search result, as Google actually returns one. */
    private function googlePlace(array $overrides = []): array
    {
        return array_replace([
            'name'               => 'Seminole Lake Country Club',
            'geometry'           => ['location' => ['lat' => 27.8279375, 'lng' => -82.7631472]],
            'types'              => ['health', 'point_of_interest', 'establishment'],
            'rating'             => 4.2,
            'user_ratings_total' => 338,
            'vicinity'           => '6100 Seminole Blvd, Seminole',
        ], $overrides);
    }

    /** @test */
    public function it_adapts_a_well_formed_google_place(): void
    {
        $candidate = PoiCandidate::fromGooglePlace($this->googlePlace());

        $this->assertSame('Seminole Lake Country Club', $candidate->name());
        $this->assertSame(27.8279375, $candidate->latitude());
        $this->assertSame(-82.7631472, $candidate->longitude());
        $this->assertSame(['health', 'point_of_interest', 'establishment'], $candidate->types());
        $this->assertSame(4.2, $candidate->rating());
        $this->assertSame(338, $candidate->reviewCount());
        $this->assertSame('6100 Seminole Blvd, Seminole', $candidate->address());
    }

    /** @test */
    public function a_missing_rating_is_null_and_is_not_confused_with_a_zero_rating(): void
    {
        // 151 of the 995 frozen candidates carry no `rating` key. The engine branches on
        // `isset()`, awarding a neutral 40.0 relevance rather than scoring them as 0.0★ —
        // so collapsing absent to 0.0 here would reprice a sixth of the corpus.
        $place = $this->googlePlace();
        unset($place['rating']);

        $this->assertNull(PoiCandidate::fromGooglePlace($place)->rating());

        // `isset()` treats an explicit null as absent. Mirror that exactly.
        $this->assertNull(PoiCandidate::fromGooglePlace($this->googlePlace(['rating' => null]))->rating());

        // A genuine 0.0 rating is a value, not an absence.
        $this->assertSame(0.0, PoiCandidate::fromGooglePlace($this->googlePlace(['rating' => 0]))->rating());
    }

    /** @test */
    public function a_missing_or_null_review_count_collapses_to_zero(): void
    {
        // The engine reads `(int) ($place['user_ratings_total'] ?? 0)`, and `(int) null === 0`,
        // so absent and null are indistinguishable downstream. Both yield 0, which the engine
        // scores as "- no reviews".
        $place = $this->googlePlace();
        unset($place['user_ratings_total']);

        $this->assertSame(0, PoiCandidate::fromGooglePlace($place)->reviewCount());
        $this->assertSame(0, PoiCandidate::fromGooglePlace($this->googlePlace(['user_ratings_total' => null]))->reviewCount());
    }

    /** @test */
    public function a_missing_coordinate_becomes_the_origin_because_that_is_what_the_engine_has_always_done(): void
    {
        // Null Island is a real place to the haversine. This is not a good default, but it is
        // the CURRENT default, and Phase 1 ships zero behaviour change. Fixing it is a decision
        // with its own baseline diff, not a side effect of introducing a value object.
        $place = $this->googlePlace();
        unset($place['geometry']);

        $candidate = PoiCandidate::fromGooglePlace($place);

        $this->assertSame(0.0, $candidate->latitude());
        $this->assertSame(0.0, $candidate->longitude());
    }

    /** @test */
    public function a_missing_name_becomes_an_empty_string(): void
    {
        $place = $this->googlePlace();
        unset($place['name']);

        $this->assertSame('', PoiCandidate::fromGooglePlace($place)->name());
    }

    /** @test */
    public function a_missing_vicinity_is_null_rather_than_an_empty_string(): void
    {
        // The two existing readers disagree: LocationDnaPoiDistanceService coerces absent to
        // null, GooglePlacesPoiAdapter coerces it to ''. The value object reports the truth and
        // leaves each consumer's coercion where it already lives.
        $place = $this->googlePlace();
        unset($place['vicinity']);

        $this->assertNull(PoiCandidate::fromGooglePlace($place)->address());
    }

    /** @test */
    public function type_tags_are_preserved_verbatim_because_the_engine_compares_them_strictly(): void
    {
        // `in_array($pType, $types, true)`. Casting members to string here would make a
        // previously-unmatched tag match, boosting category_match_score by 25 points.
        $candidate = PoiCandidate::fromGooglePlace($this->googlePlace(['types' => ['restaurant', 0, '1']]));

        $this->assertSame(['restaurant', 0, '1'], $candidate->types());
    }

    /** @test */
    public function a_missing_types_array_becomes_empty(): void
    {
        $place = $this->googlePlace();
        unset($place['types']);

        $this->assertSame([], PoiCandidate::fromGooglePlace($place)->types());
    }

    /** @test */
    public function a_malformed_types_value_is_tolerated_where_the_engine_would_fatal(): void
    {
        // The ONE deliberate divergence, called out in the class docblock. The engine passes
        // `$place['types'] ?? []` straight to `in_array()`, so a scalar would raise a TypeError.
        // No provider has ever sent one. Hardening ships when the engine is normalised onto this
        // type; until then nothing routes through here, so no behaviour changes.
        $this->assertSame([], PoiCandidate::fromGooglePlace($this->googlePlace(['types' => 'restaurant']))->types());
        $this->assertSame([], PoiCandidate::fromGooglePlace($this->googlePlace(['types' => null]))->types());
    }

    /** @test */
    public function numeric_strings_are_coerced_the_way_the_engine_coerces_them(): void
    {
        // Google sends numbers, but the persisted-row re-scoring path in
        // LocationDnaPoiDistanceService rebuilds candidates from database columns, where a
        // decimal arrives as a string. Both must land on the same float.
        $candidate = PoiCandidate::fromGooglePlace($this->googlePlace([
            'geometry'           => ['location' => ['lat' => '27.8279375', 'lng' => '-82.7631472']],
            'rating'             => '4.2',
            'user_ratings_total' => '338',
        ]));

        $this->assertSame(27.8279375, $candidate->latitude());
        $this->assertSame(-82.7631472, $candidate->longitude());
        $this->assertSame(4.2, $candidate->rating());
        $this->assertSame(338, $candidate->reviewCount());
    }

    /** @test */
    public function to_ranking_array_emits_exactly_the_five_scoring_signals(): void
    {
        // If this set ever grows, the engine has gained a dependency and the parity harness in
        // PoiCandidateGoldenMasterParityTest is no longer testing what it claims to.
        $keys = array_keys(PoiCandidate::fromGooglePlace($this->googlePlace())->toRankingArray());

        sort($keys);

        $this->assertSame(['geometry', 'name', 'rating', 'types', 'user_ratings_total'], $keys);
    }

    /** @test */
    public function from_google_places_reindexes_a_sparse_array(): void
    {
        // The engine indexes `$distances[$i]` against `$candidates[$i]`. A gappy or string-keyed
        // array would still work there, but `array_values()` keeps the contract boring.
        $places = [3 => $this->googlePlace(['name' => 'A']), 7 => $this->googlePlace(['name' => 'B'])];

        $candidates = PoiCandidate::fromGooglePlaces($places);

        $this->assertSame([0, 1], array_keys($candidates));
        $this->assertSame(['A', 'B'], array_map(static fn (PoiCandidate $c): string => $c->name(), $candidates));
    }

    /** @test */
    public function an_empty_place_array_adapts_without_error(): void
    {
        $candidate = PoiCandidate::fromGooglePlace([]);

        $this->assertSame('', $candidate->name());
        $this->assertSame(0.0, $candidate->latitude());
        $this->assertSame(0.0, $candidate->longitude());
        $this->assertSame([], $candidate->types());
        $this->assertNull($candidate->rating());
        $this->assertSame(0, $candidate->reviewCount());
        $this->assertNull($candidate->address());
        $this->assertSame([], $candidate->raw());
    }

    /** @test */
    public function from_google_places_maps_an_empty_result_set_to_an_empty_list(): void
    {
        $this->assertSame([], PoiCandidate::fromGooglePlaces([]));
    }
}
