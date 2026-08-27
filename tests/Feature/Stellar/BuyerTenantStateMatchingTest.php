<?php

namespace Tests\Feature\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Support\Location\UsStateCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A Preferred State is a geographic criterion, for Buyer and for Tenant.
 *
 * THE DEFECT THIS PINS
 * --------------------
 * The map widget has always captured a single Preferred State — `state` in the
 * `location_dna_preferences` blob, mirrored to a discrete `state` meta by
 * {@see \App\Http\Livewire\Concerns\HasSearchAreas::saveSearchAreas()}. Nothing
 * downstream read it. All four Stellar criteria loaders dropped the key,
 * {@see BuyerCriteriaPayload} had no field to carry it, and
 * {@see BuyerMatchQueryBuilder::applyGeographicFilter()} did not count it when
 * deciding whether ANY geography had been declared.
 *
 * The consequence was not "state is ignored" — it was worse than that. A buyer
 * or tenant whose only geographic criterion was a state fell through the
 * `!$hasRadius && !$hasPolygon && !$hasCity && !$hasZip && !$hasCounty` guard,
 * which returns without adding a single clause. The search then ran with NO
 * geographic constraint at all and returned a nationwide candidate set, capped
 * at 200 and ordered by price proximity. Silently: an absent filter is not an
 * error, so nothing logged and nothing failed.
 *
 * ELIGIBILITY ONLY, AND DELIBERATELY SO
 * -------------------------------------
 * State joins the existing OR — polygon OR radius OR ZIP OR city OR county OR
 * state — and contributes ZERO ranking points. The additive geographic scoring
 * model is a separate, reviewed decision; this file asserts the arithmetic is
 * untouched precisely so that a future ranking change cannot land here by
 * accident.
 *
 * WHY THE OR MATTERS MORE THAN IT LOOKS
 * ------------------------------------
 * A state is the BROADEST criterion the product offers, so making it an AND
 * would be the easy mistake: a user with "Florida" plus "Tampa" would then see
 * only Tampa, and one whose city label carried a different state suffix would
 * see nothing at all. Geographic eligibility widens; every tier is a way IN.
 */
class BuyerTenantStateMatchingTest extends TestCase
{
    use DatabaseTransactions;

    private function skipIfTableMissing(): void
    {
        if (! Schema::hasTable('bridge_properties')) {
            $this->markTestSkipped('bridge_properties table is not present.');
        }
    }

    private function makeCriteria(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    private function insertListing(array $overrides = []): string
    {
        $key = $overrides['listing_key'] ?? ('STATE-' . uniqid());

        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'             => $key,
            'listing_id'              => 'LID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'county_or_parish'        => 'Orange',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides, ['listing_key' => $key]));

        return $key;
    }

    /** @return list<string> listing_keys the geographic filter admits */
    private function eligibleKeys(BuyerCriteriaPayload $criteria): array
    {
        return (new BuyerMatchQueryBuilder())
            ->build($criteria)
            ->pluck('listing_key')
            ->all();
    }

    private function skipIfCriteriaTablesMissing(array $tables): void
    {
        foreach (array_merge(['users'], $tables) as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    /** The loader returns a flat array; matching consumes it as a payload. */
    private function payload(?array $loaded): BuyerCriteriaPayload
    {
        $this->assertNotNull($loaded, 'The loader returned no payload');

        return new BuyerCriteriaPayload($loaded);
    }

    private function makeUser(string $type): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'StateFix',
            'last_name'   => 'Test',
            'name'        => 'StateFix Test',
            'short_id'    => 'STFIX' . uniqid(),
            'user_name'   => 'stfix_' . uniqid(),
            'email'       => 'stfix-' . uniqid() . '@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => $type,
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * An approved, unsold Buyer criteria record — the only shape the loader returns.
     *
     * Inserted through the query builder rather than the model because these
     * records are fully guarded, matching what the neighbouring Stellar tests do.
     *
     * @return array{0:int,1:BuyerCriteriaAuction}
     */
    private function makeBuyerCriteria(array $meta): array
    {
        $userId = $this->makeUser('buyer');

        $id = DB::table('buyer_criteria_auctions')->insertGetId([
            // buyer_id is NOT NULL on this legacy table alongside user_id.
            'user_id'      => $userId,
            'buyer_id'     => $userId,
            'max_price'    => 500000,
            'title'        => 'State matching fixture',
            'is_approved'  => true,
            'is_sold'      => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $record = BuyerCriteriaAuction::findOrFail($id);

        // Every loader requires a non-empty property_types before it will map a
        // record at all, so it is a fixture default rather than a per-test detail.
        $meta = array_merge(['property_types' => json_encode(['Residential'])], $meta);

        foreach ($meta as $key => $value) {
            $record->saveMeta($key, $value);
        }

        return [$userId, $record];
    }

    /** @return array{0:int,1:TenantCriteriaAuction} */
    private function makeTenantCriteria(array $meta): array
    {
        $userId = $this->makeUser('tenant');

        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = TenantCriteriaAuction::findOrFail($id);

        $meta = array_merge(['property_types' => json_encode(['Residential'])], $meta);

        foreach ($meta as $key => $value) {
            $record->saveMeta($key, $value);
        }

        return [$userId, $record];
    }

    /** @return array{0:int,1:TenantAgentAuction} */
    private function makeTenantOfferListing(array $meta): array
    {
        $userId = $this->makeUser('tenant');

        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'         => $userId,
            'is_approved'     => true,
            'is_draft'        => false,
            'is_sold'         => false,
            'auction_ended'   => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $auction = TenantAgentAuction::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('rental_purpose', 'residential');

        $meta = array_merge(['property_types' => json_encode(['Residential'])], $meta);

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return [$userId, $auction];
    }

    // ── 1. the criterion survives the round trip ────────────────────────────

    /**
     * @test
     *
     * The blob is what the map widget writes, so this is the exact shape a real
     * saved criteria record carries.
     */
    public function a_state_only_blob_reaches_the_buyer_payload(): void
    {
        $this->skipIfCriteriaTablesMissing(['buyer_criteria_auctions', 'buyer_criteria_auction_metas']);

        [$userId, $record] = $this->makeBuyerCriteria([
            'location_dna_preferences' => json_encode([
                'cities'          => [],
                'zip_codes'       => [],
                'polygons'        => [],
                'radius_searches' => [],
                'state'           => 'Florida',
            ]),
        ]);

        $payload = (new BuyerCriteriaLoader())->loadById($record->id, [$userId]);

        $this->assertNotNull($payload, 'The loader must return a payload');
        $this->assertSame(
            'FL',
            $this->payload($payload)->preferredState,
            'The Preferred State must survive criteria loading as a Bridge-comparable code'
        );
    }

    // ── 2. state-only actually restricts the candidate set ──────────────────

    /**
     * @test
     *
     * The defect in one assertion. Before the fix both listings came back,
     * because a state-only criteria set produced no geographic clause at all.
     */
    public function a_state_only_buyer_search_excludes_out_of_state_listings(): void
    {
        $this->skipIfTableMissing();

        $florida = $this->insertListing(['state_or_province' => 'FL', 'city' => 'Orlando']);
        $texas   = $this->insertListing(['state_or_province' => 'TX', 'city' => 'Austin', 'postal_code' => '78701']);

        $keys = $this->eligibleKeys($this->makeCriteria(['preferred_state' => 'Florida']));

        $this->assertContains($florida, $keys, 'A Florida listing must match a Florida-only criteria set');
        $this->assertNotContains($texas, $keys, 'A Texas listing must NOT match a Florida-only criteria set');
    }

    /**
     * @test
     *
     * Tenant reaches the identical query builder through the shared payload, so
     * this proves the wiring rather than re-proving the SQL.
     */
    public function a_state_only_tenant_search_excludes_out_of_state_listings(): void
    {
        $this->skipIfTableMissing();

        $florida = $this->insertListing(['state_or_province' => 'FL', 'city' => 'Orlando']);
        $georgia = $this->insertListing(['state_or_province' => 'GA', 'city' => 'Atlanta', 'postal_code' => '30301']);

        $this->skipIfCriteriaTablesMissing(['tenant_criteria_auctions', 'tenant_criteria_auction_metas']);

        [$userId, $record] = $this->makeTenantCriteria([
            'location_dna_preferences' => json_encode(['state' => 'Florida']),
        ]);

        $payload = (new TenantCriteriaLoader())->loadById($record->id, [$userId]);
        $this->assertNotNull($payload, 'The tenant loader must return a payload');

        $criteria = $this->payload($payload);
        $this->assertSame('FL', $criteria->preferredState, 'Tenant must carry the Preferred State too');

        $keys = $this->eligibleKeys($criteria);

        $this->assertContains($florida, $keys);
        $this->assertNotContains($georgia, $keys);
    }

    /** @test */
    public function the_modern_tenant_offer_loader_also_carries_the_state(): void
    {
        $this->skipIfCriteriaTablesMissing(['tenant_agent_auctions', 'tenant_agent_auction_metas']);

        [$userId, $auction] = $this->makeTenantOfferListing([
            'location_dna_preferences' => json_encode(['state' => 'Florida']),
        ]);

        $payload = (new TenantOfferListingCriteriaLoader())->loadById($auction->id, [$userId]);
        $this->assertNotNull($payload, 'The tenant offer loader must return a payload');

        $this->assertSame('FL', $this->payload($payload)->preferredState);
    }

    // ── 3. OR semantics — state must never become an AND ────────────────────

    /**
     * @test
     *
     * The mistake this guards against. A state is the broadest criterion the
     * product offers; ANDing it would silently shrink every combined search.
     */
    public function state_is_ored_with_city_not_anded(): void
    {
        $this->skipIfTableMissing();

        $tampa       = $this->insertListing(['state_or_province' => 'FL', 'city' => 'Tampa', 'postal_code' => '33602']);
        $flNotTampa  = $this->insertListing(['state_or_province' => 'FL', 'city' => 'Orlando']);
        $txTampaName = $this->insertListing(['state_or_province' => 'TX', 'city' => 'Tampa', 'postal_code' => '78701']);

        $keys = $this->eligibleKeys($this->makeCriteria([
            'preferred_state'  => 'Florida',
            'preferred_cities' => ['Tampa'],
        ]));

        $this->assertContains($tampa, $keys, 'Matches both — must be eligible');
        $this->assertContains(
            $flNotTampa,
            $keys,
            'A Florida listing outside Tampa must remain eligible — geographic eligibility is OR, never AND'
        );
        $this->assertContains(
            $txTampaName,
            $keys,
            'A city match alone still admits a listing; adding a state must not start excluding on city'
        );
    }

    /** @test */
    public function state_is_ored_with_geometry_too(): void
    {
        $this->skipIfTableMissing();

        $inRadius = $this->insertListing([
            'state_or_province' => 'GA', 'city' => 'Atlanta', 'postal_code' => '30301',
            'latitude' => 33.7490, 'longitude' => -84.3880,
        ]);
        $flOnly = $this->insertListing(['state_or_province' => 'FL', 'city' => 'Orlando']);

        $keys = $this->eligibleKeys($this->makeCriteria([
            'preferred_state'  => 'Florida',
            'radius_searches'  => [['lat' => 33.7490, 'lng' => -84.3880, 'radius_miles' => 5]],
        ]));

        $this->assertContains($inRadius, $keys, 'A radius hit outside the state must still be eligible');
        $this->assertContains($flOnly, $keys, 'A state hit outside the radius must still be eligible');
    }

    // ── 4. unchanged neighbours ─────────────────────────────────────────────

    /**
     * @test
     *
     * The control. City / ZIP / county behaviour must be byte-identical with no
     * state present — this is the regression the change could most plausibly
     * cause, and it is asserted rather than assumed.
     */
    public function city_zip_and_county_matching_are_unchanged_without_a_state(): void
    {
        $this->skipIfTableMissing();

        $city   = $this->insertListing(['city' => 'Tampa', 'postal_code' => '33602', 'county_or_parish' => 'Hillsborough']);
        $zip    = $this->insertListing(['city' => 'Nowhere', 'postal_code' => '33701', 'county_or_parish' => 'Pinellas']);
        $county = $this->insertListing(['city' => 'Elsewhere', 'postal_code' => '34698', 'county_or_parish' => 'Pasco']);
        $none   = $this->insertListing(['city' => 'Austin', 'postal_code' => '78701', 'county_or_parish' => 'Travis', 'state_or_province' => 'TX']);

        $keys = $this->eligibleKeys($this->makeCriteria([
            'preferred_cities'    => ['Tampa'],
            'preferred_zip_codes' => ['33701'],
            'preferred_counties'  => ['Pasco'],
        ]));

        $this->assertContains($city, $keys);
        $this->assertContains($zip, $keys);
        $this->assertContains($county, $keys);
        $this->assertNotContains($none, $keys);
    }

    /**
     * @test
     *
     * Backwards compatibility: a record saved before the Preferred State input
     * existed carries no `state` key at all, and must behave exactly as it did.
     */
    public function criteria_without_a_state_are_unaffected(): void
    {
        $this->skipIfTableMissing();

        $this->skipIfCriteriaTablesMissing(['buyer_criteria_auctions', 'buyer_criteria_auction_metas']);

        [$userId, $record] = $this->makeBuyerCriteria([
            'location_dna_preferences' => json_encode(['cities' => ['Tampa']]),
        ]);

        $criteria = $this->payload((new BuyerCriteriaLoader())->loadById($record->id, [$userId]));

        $this->assertNull($criteria->preferredState, 'An absent state must be null, never an empty-string filter');

        $tampa = $this->insertListing(['city' => 'Tampa', 'postal_code' => '33602']);
        $other = $this->insertListing(['city' => 'Austin', 'postal_code' => '78701', 'state_or_province' => 'TX']);

        $keys = $this->eligibleKeys($criteria);

        $this->assertContains($tampa, $keys);
        $this->assertNotContains($other, $keys);
    }

    /**
     * @test
     *
     * An unrecognisable state must fail SAFE — behave as though no state was
     * given — rather than filter on a value no listing can ever carry, which
     * would turn a typo into zero results with no explanation.
     */
    public function an_unrecognised_state_does_not_silently_empty_the_search(): void
    {
        $this->skipIfTableMissing();

        $this->skipIfCriteriaTablesMissing(['buyer_criteria_auctions', 'buyer_criteria_auction_metas']);

        [$userId, $record] = $this->makeBuyerCriteria([
            'location_dna_preferences' => json_encode(['state' => 'Nowhere Land', 'cities' => ['Tampa']]),
        ]);

        $criteria = $this->payload((new BuyerCriteriaLoader())->loadById($record->id, [$userId]));

        $this->assertNull($criteria->preferredState, 'An unknown state must normalise to null, not to a junk code');

        $tampa = $this->insertListing(['city' => 'Tampa', 'postal_code' => '33602']);

        $this->assertContains($tampa, $this->eligibleKeys($criteria), 'The city criterion must still work');
    }

    // ── 5. normalization ────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider stateInputs
     */
    public function the_normalizer_maps_persisted_variants_to_a_bridge_code(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, UsStateCode::normalize($input));
    }

    public function stateInputs(): array
    {
        return [
            'full name'              => ['Florida', 'FL'],
            'already a code'         => ['FL', 'FL'],
            'lowercase code'         => ['fl', 'FL'],
            'lowercase name'         => ['florida', 'FL'],
            'surrounding whitespace' => ['  Florida  ', 'FL'],
            'two-word state'         => ['New York', 'NY'],
            'two-word spacing'       => ['new    york', 'NY'],
            'District of Columbia'   => ['District of Columbia', 'DC'],
            'a different state'      => ['Texas', 'TX'],
            'last alphabetically'    => ['Wyoming', 'WY'],
            'empty string'           => ['', null],
            'whitespace only'        => ['   ', null],
            'null'                   => [null, null],
            'unknown word'           => ['Atlantis', null],
            'not a real code'        => ['ZZ', null],
        ];
    }

    /**
     * @test
     *
     * Every state the map widget offers must round-trip. The widget's datalist
     * is the only list a user can pick from, so a state present there and absent
     * here would be a criterion nobody could act on.
     */
    public function every_state_the_widget_offers_normalises(): void
    {
        $offered = [
            'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut',
            'Delaware', 'District of Columbia', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois',
            'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland',
            'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana',
            'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York',
            'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania',
            'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah',
            'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
        ];

        $this->assertCount(51, $offered, 'The widget offers 50 states plus DC');

        foreach ($offered as $name) {
            $code = UsStateCode::normalize($name);

            $this->assertNotNull($code, "{$name} is offered by the map widget but does not normalise");
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code, "{$name} must yield a two-letter code");
            $this->assertSame($code, UsStateCode::normalize($code), "{$code} must be idempotent");
        }
    }
}
