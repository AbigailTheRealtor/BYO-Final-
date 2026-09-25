<?php

namespace Tests\Feature\Stellar\Matching\Parity;

use App\Models\BridgeProperty;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Stellar\Matching\Baseline\PreConvergenceScoringBaselineTest;
use Tests\Support\Matching\CanonicalFactsParity;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B — every allowed difference is real, and reaches only what it declares.
 *
 * One demonstration per registry entry: a synthetic row that constructs exactly that
 * difference, an assertion that the harness attributes it to that entry and no other,
 * and an assertion — over the A0 criteria matrix plus cases chosen to exercise the
 * field — that the scores and explanations it moves are a subset of its declared
 * `propagates` (empty for AD-1 and AD-4: it must move nothing at all).
 *
 * Also here: registry discipline, error parity (the nested-array crash reproduces on
 * both paths) and comparability (rows with no canonical identity never reach the
 * builder).
 */
class CanonicalFactsAllowedDifferenceTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;
    use CanonicalFactsParity;

    /**
     * Raw overrides that keep AD-1 out of a demonstration of another entry: every
     * committed fixture stores false on these three (AD-1), and a stated true is carried
     * identically by both paths.
     */
    private const ISOLATE = ['PoolPrivateYN' => true, 'GarageYN' => true, 'WaterfrontYN' => true];

    private const DEMONSTRATIONS = [
        'AD-1' => 'test_ad1_a_stored_false_is_unknown_canonically_and_moves_nothing',
        'AD-2' => 'test_ad2_an_invalid_coordinate_pair_is_dropped_canonically',
        'AD-3' => 'test_ad3_a_non_positive_or_implausible_number_is_unknown_canonically',
        'AD-4' => 'test_ad4_a_rent_period_on_a_sale_record_is_dropped_and_moves_nothing',
        'AD-5' => 'test_ad5_a_reso_alias_or_unrecognised_type_resolves_to_the_recognised_type',
        'AD-6' => 'test_ad6_surrounding_whitespace_is_trimmed_canonically',
    ];

    // ── Registry discipline ───────────────────────────────────────────────────

    public function test_the_registry_is_the_six_planned_entries_each_closed_and_demonstrated(): void
    {
        $this->assertSame(array_keys(self::DEMONSTRATIONS), array_keys(self::$ALLOWED_DIFFERENCES));
        $this->assertLessThanOrEqual(6, count(self::$ALLOWED_DIFFERENCES));

        $fields   = self::factFields();
        $outcomes = ['exception', 'listing_key', 'total_score', 'category_scores', 'important_places', 'why_this_matches',
                     'tradeoffs', 'caution_flags', 'missing_data', 'why_not', 'confidence', 'recommendations'];

        foreach (self::$ALLOWED_DIFFERENCES as $id => $entry) {
            $this->assertNotSame('', trim($entry['reason']), "{$id} needs a reason");
            $this->assertNotSame('', trim($entry['closes_at']), "{$id} needs a closing stage");
            $this->assertNotEmpty($entry['fields'], "{$id} must name its fields");
            $this->assertSame([], array_diff($entry['fields'], $fields), "{$id} names a field ListingMatchFacts does not have");
            $this->assertSame([], array_diff($entry['propagates'], $outcomes), "{$id} names an outcome the harness does not compare");
            $this->assertTrue(method_exists($this, self::DEMONSTRATIONS[$id]), "{$id} needs its demonstration test");
        }

        $this->assertSame([], self::$ALLOWED_DIFFERENCES['AD-1']['propagates']);
        $this->assertSame([], self::$ALLOWED_DIFFERENCES['AD-4']['propagates']);
    }

    public function test_an_undescribed_value_shape_is_not_accepted_by_a_field_name_alone(): void
    {
        $a = $this->legacyFacts($this->storeBaselineFixture('residential'));

        // AD-1 accepts false → null, not true → null or false → true.
        $this->assertNull(self::allowedDifference('poolPrivate', true, null, $a));
        $this->assertNull(self::allowedDifference('poolPrivate', false, true, $a));
        // AD-3 accepts a non-positive price, not a positive one.
        $this->assertNull(self::allowedDifference('listPrice', '184900.00', null, $a));
        // AD-6 accepts a trim, not a different city.
        $this->assertNull(self::allowedDifference('city', ' ST PETERSBURG ', 'TAMPA', $a));
        // No entry names bedrooms-like or residual fields.
        $this->assertNull(self::allowedDifference('lotSizeSqft', 1, 2, $a));
        $this->assertNull(self::allowedDifference('leaseTerm', '12 Months', null, $a));
    }

    // ── One demonstration per entry ───────────────────────────────────────────

    public function test_ad1_a_stored_false_is_unknown_canonically_and_moves_nothing(): void
    {
        $row = $this->storeBaselineFixture('residential', ['PoolPrivateYN' => false, 'GarageYN' => false, 'WaterfrontYN' => false], 'ad1');

        $this->assertDemonstrates('AD-1', 'residential', $row, ['poolPrivate', 'garage', 'waterfront'], [
            'wants_all'   => ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true],
            'wants_none'  => ['wants_pool' => false, 'wants_garage' => false, 'wants_waterfront' => false],
        ]);
    }

    public function test_ad2_an_invalid_coordinate_pair_is_dropped_canonically(): void
    {
        $record = $this->fixtureRecord('residential');
        $row    = $this->storeBaselineFixture('residential', self::ISOLATE, 'ad2', ['latitude' => 0, 'longitude' => 0]);

        $this->assertDemonstrates('AD-2', 'residential', $row, ['latitude', 'longitude'], [
            'radius'          => ['radius_searches' => [['lat' => 0.0, 'lng' => 0.0, 'radius_miles' => 5]]],
            'important_place' => ['important_places' => [
                ['type' => 'Work', 'address' => 'Office', 'lat' => (float) $record['Latitude'], 'lng' => (float) $record['Longitude'], 'distance_pref' => 'miles', 'distance_value' => 3],
            ]],
        ]);
    }

    public function test_ad3_a_non_positive_or_implausible_number_is_unknown_canonically(): void
    {
        $row = $this->storeBaselineFixture('residential', self::ISOLATE, 'ad3', ['list_price' => 0, 'living_area' => 0, 'year_built' => 1600]);

        $this->assertDemonstrates('AD-3', 'residential', $row, ['listPrice', 'livingArea', 'yearBuilt'], [
            'price' => ['min_price' => 100000, 'max_price' => 250000],
            'size'  => ['min_sqft' => 500, 'max_sqft' => 1500, 'year_built_min' => 1980],
        ]);
    }

    public function test_ad4_a_rent_period_on_a_sale_record_is_dropped_and_moves_nothing(): void
    {
        $row = $this->storeBaselineFixture('residential', ['LeaseAmountFrequency' => 'Monthly'] + self::ISOLATE, 'ad4');

        $this->assertDemonstrates('AD-4', 'residential', $row, ['leaseFrequency'], [
            'price' => ['min_price' => 100000, 'max_price' => 250000],
        ]);
    }

    public function test_ad5_a_reso_alias_or_unrecognised_type_resolves_to_the_recognised_type(): void
    {
        $alias = $this->storeBaselineFixture('income', self::ISOLATE, 'ad5_alias', ['property_type' => 'ResidentialIncome']);
        $this->assertSame('Income', $this->canonicalFacts($alias)->propertyType);
        $this->assertDemonstrates('AD-5', 'income', $alias, ['propertyType'], [
            'income' => ['property_types' => ['Income', 'ResidentialIncome']],
        ]);

        $unknown = $this->storeBaselineFixture('residential', self::ISOLATE, 'ad5_unknown', ['property_type' => 'Farm']);
        $this->assertNull($this->canonicalFacts($unknown)->propertyType);
        $this->assertDemonstrates('AD-5', 'residential', $unknown, ['propertyType'], [
            'farm' => ['property_types' => ['Farm']],
        ]);
    }

    public function test_ad6_surrounding_whitespace_is_trimmed_canonically(): void
    {
        $record = $this->fixtureRecord('residential');
        $row    = $this->storeBaselineFixture('residential', self::ISOLATE, 'ad6', [
            'listing_key'       => '  ' . self::baselineKey('residential', 'ad6') . ' ',
            'city'              => ' ' . $record['City'] . ' ',
            'state_or_province' => ' ' . $record['StateOrProvince'],
            'postal_code'       => $record['PostalCode'] . ' ',
            'county_or_parish'  => '   ',
        ]);

        $this->assertDemonstrates('AD-6', 'residential', $row, ['listingKey', 'city', 'stateOrProvince', 'postalCode', 'countyOrParish'], [
            'zip'    => ['preferred_zip_codes' => [$record['PostalCode']]],
            'county' => ['preferred_counties' => [$record['CountyOrParish']]],
        ]);
    }

    // ── Error parity and comparability ────────────────────────────────────────

    public function test_the_nested_array_crash_reproduces_identically_on_both_paths(): void
    {
        $row = $this->storeBaselineFixture('residential_lease', [
            'CommunityFeatures'    => [['Pool', 'Gym']],
            'AssociationAmenities' => [['Clubhouse']],
            'LeaseTerm'            => ['12 Months'],
        ], 'nested');

        $a = $this->legacyFacts($row);
        $b = $this->canonicalFacts($row);
        $this->assertNotNull($b);

        foreach ([
            'community' => ['property_types' => ['Residential Lease'], 'community_feature_keywords' => ['pool']],
            'lease'     => ['property_types' => ['Residential Lease'], 'preferred_lease_terms' => ['1 Year']],
        ] as $case => $criteria) {
            $payload = $this->baselinePayload($criteria);
            $legacy  = $this->outcome($a, $row, $payload);

            $this->assertNotNull($legacy['exception'], "{$case}: the legacy path must still crash (the defect is carried, not fixed)");
            $this->assertSame($legacy, $this->outcome($b, $row, $payload), "{$case}: both paths must fail the same way");
        }
    }

    public function test_a_row_with_no_canonical_identity_never_reaches_the_builder(): void
    {
        $unrecognised = $this->storeBaselineFixture('residential', [], 'other_provider', ['provider' => 'some_other_mls']);
        $keyless      = $this->storeBaselineFixture('residential', [], 'keyless');
        $keyless->listing_key = '   ';

        $this->assertNull($this->canonicalFacts($unrecognised));
        $this->assertNull($this->canonicalFacts($keyless));
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The row's facts differ on exactly $fields, every difference is attributed to
     * $id, and across the A0 matrix of the fixture $slug the row was built from, plus $extraCases, the outcome keys that move are
     * within $id's declared propagation.
     *
     * @param list<string> $fields
     * @param array<string,array<string,mixed>> $extraCases criteria overrides (property_types defaulted)
     */
    private function assertDemonstrates(string $id, string $slug, BridgeProperty $row, array $fields, array $extraCases): void
    {
        $a = $this->legacyFacts($row);
        $b = $this->canonicalFacts($row);
        $this->assertNotNull($b, "{$id}: the synthetic row must be comparable");

        $differences = self::factsDifferences($a, $b);
        $this->assertSame($fields, array_keys($differences), "{$id}: the row must differ on exactly its fields");

        foreach ($differences as $field => $values) {
            $this->assertSame($id, self::allowedDifference($field, $values['legacy'], $values['canonical'], $a),
                "{$id}: {$field} must be attributed to {$id}");
        }

        $type  = $a->propertyType ?? 'Residential';
        $cases = $this->a0Cases($slug);
        foreach ($extraCases as $case => $criteria) {
            $cases["extra_{$case}"] = $criteria + ['property_types' => [$type]];
        }

        $allowed = self::$ALLOWED_DIFFERENCES[$id]['propagates'];
        foreach ($cases as $case => $criteria) {
            $payload = $this->baselinePayload($criteria);
            $moved   = self::outcomeDifferences($this->outcome($a, $row, $payload), $this->outcome($b, $row, $payload));

            $this->assertSame([], array_values(array_diff($moved, $allowed)),
                "{$id}/{$case}: moved " . json_encode($moved) . ', declared ' . json_encode($allowed));
        }
    }

    /** The A0 oracle's full criteria matrix for one fixture, unchanged. */
    private function a0Cases(string $slug): array
    {
        $oracle = new PreConvergenceScoringBaselineTest('test_category_output_matches_the_pre_convergence_snapshot');

        return (fn () => $this->casesFor($slug, $slug))->call($oracle);
    }
}
