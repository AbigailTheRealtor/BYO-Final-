<?php

namespace Tests\Unit\Services\Bridge;

use App\Services\Bridge\CriteriaHashService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Tests\TestCase;

/**
 * G1b · U-G1B-2 — does `CriteriaHashService::hash()` canonicalise nested Location DNA geometry?
 *
 * WHY THIS EXISTS
 * ---------------
 * §5.3 requires any consumer that hashes the contract for cache keys or change
 * detection to hash the **canonicalised** form, never raw bytes, or omission will
 * present as a spurious change. The G1b audit could not settle from static reading
 * whether that holds for nested `polygons` / `radius_searches`, and recorded it as
 * U-G1B-2 — a blocker on shipping G1c's omission capability. This suite settles it.
 *
 * LOCATION
 * --------
 * Placed beside the existing `CriteriaHashServiceTest` in `tests/Unit/Services/Bridge/`
 * rather than in `tests/Feature/Spatial/`, because the service is pure (no DB, no
 * container) and the repository already organises its tests per service here.
 *
 * NOT DUPLICATED
 * --------------
 * `CriteriaHashServiceTest` already covers hex shape, role case-insensitivity,
 * determinism, city-order independence, radius-order independence, differing radius
 * coordinates, and radius key-structure preservation. This suite covers only the
 * U-G1B-2 gaps and deliberately does not restate those.
 *
 * THE REAL SERVICE, THE REAL ENTRY POINT
 * -------------------------------------
 * Every assertion goes through the public `hash(BuyerCriteriaPayload, string $role)`.
 * The canonicalisation algorithm is **not** reimplemented here — the tests compare
 * hashes of differently-shaped inputs and let the real implementation speak.
 *
 * CHARACTERISATION, NOT DESIGN
 * ----------------------------
 * Every assertion records what the service does TODAY, including where that differs
 * from what §5.3 would prefer. No owner decision D-G1-1 … D-G1-6 is assumed.
 *
 * STRUCTURAL FACT THAT SHAPES EVERYTHING BELOW
 * -------------------------------------------
 * `canonicalize()` builds a **fixed whitelist** of 35 named keys off DTO properties
 * (`CriteriaHashService.php:30-66`). The DTO itself only assigns known keys
 * (`BuyerCriteriaPayload.php:89-95`). So `location_notes`, `state`, `schema_version`
 * and any unknown future key are **structurally unable to reach the hash** — not
 * filtered late, but never collected. Several tests below assert that directly.
 */
class G1bCriteriaHashCharacterisationTest extends TestCase
{
    private CriteriaHashService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CriteriaHashService();
    }

    private function payload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    private function hash(array $overrides, string $role = 'buyer'): string
    {
        return $this->service->hash($this->payload($overrides), $role);
    }

    /** A polygon whose `path` is a list of associative {lat,lng} entries. */
    private function polygon(array $path, string $label = 'Area 1'): array
    {
        return ['label' => $label, 'path' => $path];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1-2 · associative key ordering, at the top level and nested inside geometry
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · nested associative key order does not affect the hash.
     *
     * `normaliseArray()` ksorts at **every** recursion level, so `{lat,lng}` and
     * `{lng,lat}` inside a polygon path canonicalise identically. This is the core
     * of U-G1B-2 and it holds.
     */
    public function test_nested_associative_key_order_inside_polygon_path_does_not_affect_hash(): void
    {
        $a = $this->hash(['polygons' => [$this->polygon([
            ['lat' => 27.9506, 'lng' => -82.4572],
            ['lat' => 27.9606, 'lng' => -82.4472],
        ])]]);

        $b = $this->hash(['polygons' => [$this->polygon([
            ['lng' => -82.4572, 'lat' => 27.9506],
            ['lng' => -82.4472, 'lat' => 27.9606],
        ])]]);

        $this->assertSame($a, $b, 'Nested associative keys must canonicalise regardless of order.');
    }

    /** CHARACTERISED · the same holds for radius-search entry keys. */
    public function test_nested_associative_key_order_inside_radius_search_does_not_affect_hash(): void
    {
        $a = $this->hash(['radius_searches' => [
            ['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 3.5, 'address' => '1 Main St'],
        ]]);

        $b = $this->hash(['radius_searches' => [
            ['address' => '1 Main St', 'radius_miles' => 3.5, 'lng' => -82.4, 'lat' => 27.9],
        ]]);

        $this->assertSame($a, $b);
    }

    /** CHARACTERISED · top-level payload key order is irrelevant (the DTO fixes it). */
    public function test_top_level_input_key_order_does_not_affect_hash(): void
    {
        $a = $this->hash(['max_price' => 400000, 'min_bedrooms' => 3, 'polygons' => [$this->polygon([['lat' => 1, 'lng' => 2]])]]);
        $b = $this->hash(['polygons' => [$this->polygon([['lng' => 2, 'lat' => 1]])], 'min_bedrooms' => 3, 'max_price' => 400000]);

        $this->assertSame($a, $b);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3-6 · list ordering — where the hash is DELIBERATELY order-blind
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · identical list ordering is of course identical.
     *
     * The control for the three order tests that follow.
     */
    public function test_identical_list_ordering_produces_identical_hash(): void
    {
        $poly = [$this->polygon([['lat' => 1, 'lng' => 1], ['lat' => 2, 'lng' => 2]])];

        $this->assertSame($this->hash(['polygons' => $poly]), $this->hash(['polygons' => $poly]));
    }

    /**
     * CHARACTERISED DEFECT-CLASS · reordering a polygon's `path` coordinates does
     * NOT change the hash.
     *
     * `normaliseArray()` value-sorts every list it meets (`CriteriaHashService.php:83-87`),
     * including the `path` list **inside** a polygon. Vertex order is semantically
     * meaningful for a polygon — two different shapes can share a vertex set — so the
     * hash cannot distinguish them.
     *
     * Recorded as observed behaviour, not repaired. §5.3 asks for order-independence
     * "where order is not meaningful"; this suite establishes that the current
     * implementation applies it unconditionally, including where order IS meaningful.
     */
    public function test_reordered_polygon_path_coordinates_do_not_change_the_hash(): void
    {
        $forward = $this->hash(['polygons' => [$this->polygon([
            ['lat' => 27.90, 'lng' => -82.40],
            ['lat' => 27.95, 'lng' => -82.45],
            ['lat' => 27.99, 'lng' => -82.49],
        ])]]);

        $shuffled = $this->hash(['polygons' => [$this->polygon([
            ['lat' => 27.99, 'lng' => -82.49],
            ['lat' => 27.90, 'lng' => -82.40],
            ['lat' => 27.95, 'lng' => -82.45],
        ])]]);

        $this->assertSame(
            $forward,
            $shuffled,
            'CHARACTERISATION: vertex order is erased by the list value-sort. '
            .'Two distinct polygons sharing a vertex set hash identically.'
        );
    }

    /** CHARACTERISED · reordering entries within `radius_searches` does not change the hash. */
    public function test_reordered_radius_searches_entries_do_not_change_the_hash(): void
    {
        $one = ['lat' => 27.1, 'lng' => -82.1, 'radius_miles' => 1];
        $two = ['lat' => 28.2, 'lng' => -83.2, 'radius_miles' => 2];

        $this->assertSame(
            $this->hash(['radius_searches' => [$one, $two]]),
            $this->hash(['radius_searches' => [$two, $one]])
        );
    }

    /** CHARACTERISED · reordering entries within `polygons` does not change the hash. */
    public function test_reordered_polygons_entries_do_not_change_the_hash(): void
    {
        $a = $this->polygon([['lat' => 1, 'lng' => 1]], 'North');
        $b = $this->polygon([['lat' => 2, 'lng' => 2]], 'South');

        $this->assertSame(
            $this->hash(['polygons' => [$a, $b]]),
            $this->hash(['polygons' => [$b, $a]])
        );
    }

    /** CONTROL · genuinely different geometry still produces a different hash. */
    public function test_different_polygon_coordinates_do_produce_a_different_hash(): void
    {
        $this->assertNotSame(
            $this->hash(['polygons' => [$this->polygon([['lat' => 27.9, 'lng' => -82.4]])]]),
            $this->hash(['polygons' => [$this->polygon([['lat' => 40.7, 'lng' => -74.0]])]]),
            'The hash must still be sensitive to actual coordinate values.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 7-9 · unknown keys, schema_version — structurally excluded
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · unknown future keys cannot affect the hash.
     *
     * They are never collected: the DTO assigns only known keys and `canonicalize()`
     * reads only DTO properties. This is stronger than "stripped" — there is no code
     * path by which they could arrive.
     */
    public function test_unknown_future_keys_do_not_affect_the_hash(): void
    {
        $base = $this->hash([]);
        $withUnknown = $this->hash([
            'some_future_dimension' => ['a', 'b'],
            'commute'               => ['max_minutes' => 30],
            'neighborhoods'         => ['Old Northeast'],
        ]);

        $this->assertSame($base, $withUnknown, 'Unknown keys never reach the hash input.');
    }

    /** CHARACTERISED · a higher `schema_version` does not affect the hash. */
    public function test_higher_schema_version_does_not_affect_the_hash(): void
    {
        $this->assertSame($this->hash([]), $this->hash(['schema_version' => 99]));
    }

    /** CHARACTERISED · absent vs explicit current `schema_version` are indistinguishable. */
    public function test_missing_schema_version_equals_explicit_schema_version(): void
    {
        $this->assertSame($this->hash([]), $this->hash(['schema_version' => 2]));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 10-11 · omission versus clearing — the §5.3 question
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · a MISSING geometry key and an EMPTY geometry array produce the
     * SAME hash.
     *
     * `normaliseArray()` drops an array whose normalised form is empty
     * (`CriteriaHashService.php:82`), and the DTO defaults a missing key to `[]`
     * (`BuyerCriteriaPayload.php:92-93`) — so both routes converge before hashing.
     *
     * **This is the direct answer to U-G1B-2's stated risk, and it is favourable:**
     * G1c's omission capability cannot cause spurious cache-key churn here, because
     * omission and canonical-empty are already equivalent to this hash.
     *
     * The mirror-image consequence is recorded and NOT repaired: a user who
     * *intentionally clears* all geometry produces the same hash as one who never
     * authored any, so a clear alone will not invalidate a cached Bridge result.
     */
    public function test_missing_geometry_key_and_empty_geometry_array_hash_identically(): void
    {
        $missing = $this->hash([]);
        $empty   = $this->hash(['polygons' => [], 'radius_searches' => []]);

        $this->assertSame(
            $missing,
            $empty,
            'CHARACTERISATION: omission and explicit clearing are equivalent to this hash.'
        );
    }

    /** CHARACTERISED · an absent nested key and an explicit `null` hash identically. */
    public function test_absent_nested_key_and_explicit_null_hash_identically(): void
    {
        $absent = $this->hash(['max_price' => 400000]);
        $null   = $this->hash(['max_price' => 400000, 'min_bedrooms' => null]);

        $this->assertSame($absent, $null, 'Nulls are dropped before hashing.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 12-14 · value normalisation and malformed input
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · empty string and null are NOT equivalent.
     *
     * `null` is dropped (`:76`); `''` is neither numeric nor an array, so it falls to
     * the else branch (`:94`) and is retained as `''`. The two therefore differ.
     */
    public function test_empty_string_and_null_are_not_equivalent(): void
    {
        $this->assertNotSame(
            $this->hash(['hoa_preference' => '']),
            $this->hash(['hoa_preference' => null]),
            'CHARACTERISATION: "" is retained while null is dropped.'
        );
    }

    /**
     * CHARACTERISED · a numeric string and its integer form hash identically.
     *
     * `is_string($value) && is_numeric($value)` casts to int, or float when the string
     * contains a `.` (`:91-92`). Asserted on a nested geometry value, since that is
     * the U-G1B-2 surface.
     */
    public function test_numeric_string_and_integer_hash_identically_inside_geometry(): void
    {
        $this->assertSame(
            $this->hash(['radius_searches' => [['lat' => 27, 'lng' => -82, 'radius_miles' => 3]]]),
            $this->hash(['radius_searches' => [['lat' => '27', 'lng' => '-82', 'radius_miles' => '3']]]),
            'Numeric strings are normalised to numbers before hashing.'
        );
    }

    /** CHARACTERISED · a float-bearing numeric string normalises to float, not int. */
    public function test_decimal_numeric_string_normalises_to_float(): void
    {
        $this->assertSame(
            $this->hash(['radius_searches' => [['radius_miles' => 3.5]]]),
            $this->hash(['radius_searches' => [['radius_miles' => '3.5']]])
        );
    }

    /**
     * CHARACTERISED · a malformed-but-hashable nested value does not throw and does
     * change the hash.
     *
     * A polygon with no `path`, and a radius entry with no coordinates, are both
     * structurally valid arrays. The service hashes them without error — it performs
     * no shape validation, consistent with G1b's F-G1B-4 (no consumer validates shape).
     */
    public function test_malformed_nested_geometry_is_hashed_without_error(): void
    {
        $malformed = $this->hash([
            'polygons'        => [['label' => 'no path at all']],
            'radius_searches' => [['radius_miles' => 5]],
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $malformed);
        $this->assertNotSame($this->hash([]), $malformed, 'Malformed geometry still contributes to the hash.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 15-16 · location_notes and administrative labels
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · `location_notes` cannot affect the hash.
     *
     * It is not a DTO property and not in the 35-key whitelist. A change to the user's
     * free-text notes therefore does not invalidate a cached Bridge result.
     */
    public function test_location_notes_changes_do_not_affect_the_hash(): void
    {
        $base  = $this->hash([]);
        $noted = $this->hash(['location_notes' => 'Near the water, walkable. Second floor preferred.']);

        $this->assertSame($base, $noted, 'location_notes never reaches the hash input.');
    }

    /**
     * CHARACTERISED · administrative-label changes DO affect the hash — but only via
     * the DTO's own `preferred_*` names.
     *
     * The canonical contract's `cities` / `counties` / `state` are **not** DTO keys;
     * the DTO uses `preferred_cities` / `preferred_counties` and has no `state` at all.
     * So a change to canonical `cities` does not move the hash, while a change to
     * `preferred_cities` does. Recorded because it is a live vocabulary mismatch
     * between the canonical contract and this consumer.
     */
    public function test_administrative_label_changes_affect_the_hash_only_under_dto_names(): void
    {
        // Canonical contract names — not DTO keys, so inert.
        $this->assertSame(
            $this->hash([]),
            $this->hash(['cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL']),
            'CHARACTERISATION: canonical `cities`/`counties`/`state` do not reach this hash.'
        );

        // DTO names — these do move it.
        $this->assertNotSame(
            $this->hash([]),
            $this->hash(['preferred_cities' => ['Tampa']]),
            'preferred_cities is a DTO key and does affect the hash.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Input mutation
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · hashing does not mutate the supplied payload.
     *
     * The DTO's properties are `readonly` and PHP copies arrays by value, so the
     * internal `usort` and `ksort` operate on copies. Asserted rather than assumed,
     * because a mutating hash would silently corrupt the geometry of any caller that
     * hashed before persisting.
     */
    public function test_hashing_does_not_mutate_the_supplied_payload(): void
    {
        $path = [
            ['lat' => 27.99, 'lng' => -82.49],
            ['lat' => 27.90, 'lng' => -82.40],
        ];

        $payload = $this->payload(['polygons' => [$this->polygon($path)]]);
        $before  = $payload->polygons;

        $this->service->hash($payload, 'buyer');

        $this->assertSame($before, $payload->polygons, 'The payload must not be mutated by hashing.');
        $this->assertSame(27.99, $payload->polygons[0]['path'][0]['lat'], 'Original vertex order preserved.');
    }

    /** CHARACTERISED · repeat calls are stable and side-effect free. */
    public function test_repeat_calls_are_stable(): void
    {
        $payload = $this->payload(['polygons' => [$this->polygon([['lat' => 1, 'lng' => 2]])]]);

        $first  = $this->service->hash($payload, 'buyer');
        $second = $this->service->hash($payload, 'buyer');
        $third  = $this->service->hash($payload, 'buyer');

        $this->assertSame($first, $second);
        $this->assertSame($second, $third);
    }
}
