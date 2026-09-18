<?php

namespace Tests\Feature\Canonical;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Canonical\Adapters\ByoListingAdapter;
use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingResolver;
use App\Services\Canonical\CanonicalListingVocabulary as V;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-4 — the canonical core listing vocabulary, BYO first.
 *
 * Every assertion runs through the real resolver or adapter against real rows,
 * because the risks being pinned are all about what a real stored value turns
 * into: a demand-named meta key becoming a supply fact correctly, a Your Terms
 * price NOT becoming a list price, an absent value staying absent.
 */
class CanonicalListingCoreVocabularyTest extends TestCase
{
    use DatabaseTransactions;

    // ── Seller ──────────────────────────────────────────────────────────────

    public function test_seller_byo_populates_the_core_supply_facts(): void
    {
        $c = $this->seller([
            'property_type'         => 'Residential',
            'bedrooms'              => '3',
            'bathrooms'             => '2.5',
            'minimum_heated_square' => '1,850',
            'year_built'            => '1998',
            'pool_needed'           => 'Yes',
            'garage_needed'         => 'No',
            'listing_status'        => 'Active',
            'address'               => '123 Oak Lane',
            'property_city'         => 'Tampa',
            'property_state'        => 'FL',
            'property_zip'          => '33602',
            'property_county'       => 'Hillsborough',
        ]);

        $this->assertTrue($c->isSupply());
        $this->assertFalse($c->isDemand());
        $this->assertSame('sale', $c->transactionType());
        $this->assertSame('Active', $c->standardStatus());
        $this->assertSame('offer_listing', $c->workflow());
        $this->assertSame('Residential', $c->propertyType());
        $this->assertSame(3, $c->bedrooms());
        $this->assertSame(2.5, $c->bathrooms());
        $this->assertSame(1850.0, $c->livingAreaSqft());
        $this->assertSame(1998, $c->yearBuilt());
        $this->assertTrue($c->hasPool());
        $this->assertFalse($c->hasGarage());
        $this->assertSame('123 Oak Lane', $c->addressLine());
        $this->assertSame('Tampa', $c->city());
        $this->assertSame('FL', $c->state());
        $this->assertSame('33602', $c->postalCode());
        $this->assertSame('Hillsborough', $c->county());

        // Provenance survives: the supply fact says which demand-named meta key it came from.
        $this->assertSame('minimum_heated_square', $c->fieldMeta(V::PROPERTY_LIVING_AREA_SQFT)['source_field']);
        $this->assertSame('pool_needed', $c->fieldMeta(V::PROPERTY_POOL)['source_field']);
        $this->assertSame('byo:seller_agent', $c->fieldMeta(V::PROPERTY_BEDROOMS)['source']);
    }

    public function test_the_sellers_desired_sale_price_is_your_terms_and_never_the_list_price(): void
    {
        $c = $this->seller([
            'property_type'  => 'Residential',
            'maximum_budget' => '450000',
            'buy_now_price'  => '470000',
            'starting_price' => '400000',
        ]);

        $this->assertNull($c->listPrice());
        $this->assertFalse($c->has(V::LISTING_LIST_PRICE));
    }

    public function test_an_mls_linked_seller_carries_the_feeds_price_and_status(): void
    {
        $c = $this->seller([
            'property_type'            => 'Residential',
            'maximum_budget'           => '450000',
            'listing_status'           => 'Active',
            'mls_listing_key'          => 'CANON-K1',
            'mls_standard_status'      => 'Pending',
            'mls_list_price'           => '525000',
            'mls_source_property_type' => 'Residential',
        ]);

        $this->assertSame(525000.0, $c->listPrice());
        $this->assertSame('mls_list_price', $c->fieldMeta(V::LISTING_LIST_PRICE)['source_field']);
        $this->assertSame('Pending', $c->standardStatus(), 'the feed status wins over the stored BYO word');
    }

    // ── Landlord ────────────────────────────────────────────────────────────

    public function test_landlord_byo_populates_the_core_supply_facts(): void
    {
        $c = $this->landlord([
            'property_type'         => 'Residential Property',
            'bedrooms'              => 'Other',
            'other_bedrooms'        => '12',
            'bathrooms'             => 'Other',
            'other_bathrooms'       => '1.5',
            'minimum_heated_square' => '980',
            'pool_needed'           => 'No',
            'listing_status'        => 'Pending',
            'property_city'         => 'St. Petersburg',
        ]);

        $this->assertTrue($c->isSupply());
        $this->assertSame('lease', $c->transactionType());
        $this->assertSame('Residential', $c->propertyType(), 'landlord wording folds to the shared category');
        $this->assertSame(12, $c->bedrooms());
        $this->assertSame(1.5, $c->bathrooms());
        $this->assertSame(980.0, $c->livingAreaSqft());
        $this->assertFalse($c->hasPool());
        $this->assertSame('Pending', $c->standardStatus());
        $this->assertSame('St. Petersburg', $c->city());
    }

    public function test_the_landlords_desired_rent_is_your_terms_and_never_the_list_price(): void
    {
        $c = $this->landlord([
            'property_type'          => 'Residential Property',
            'desired_rental_amount'  => '2500',
            'lease_amount_frequency' => 'Monthly',
        ]);

        $this->assertNull($c->listPrice());
        $this->assertNull($c->leaseAmountFrequency(), 'the landlord form frequency belongs to Your Terms');
    }

    public function test_an_mls_linked_landlord_takes_the_lease_price_but_never_a_sale_price(): void
    {
        $lease = $this->landlord([
            'property_type'            => 'Residential Property',
            'mls_listing_key'          => 'CANON-L1',
            'mls_list_price'           => '2750',
            'mls_source_property_type' => 'Residential Lease',
        ]);
        $this->assertSame(2750.0, $lease->listPrice());
        $this->assertNull($lease->leaseAmountFrequency(), 'no factual period is stored, so none is assumed');

        $sale = $this->landlord([
            'property_type'            => 'Residential Property',
            'mls_listing_key'          => 'CANON-L2',
            'mls_list_price'           => '184900',
            'mls_source_property_type' => 'Residential',
        ]);
        $this->assertNull($sale->listPrice(), 'a purchase price is never a landlord list price');
    }

    public function test_a_commercial_landlord_listing_is_commercial_and_lease(): void
    {
        $c = $this->landlord(['property_type' => 'Commercial Property']);

        $this->assertSame('Commercial', $c->propertyType());
        $this->assertSame('lease', $c->transactionType());
    }

    // ── Buyer / Tenant: demand stays demand ─────────────────────────────────

    public function test_buyer_and_tenant_criteria_never_become_supply_facts(): void
    {
        $demandMeta = [
            'property_type'         => 'Residential',
            'bedrooms'              => '3',
            'bathrooms'             => '2',
            'minimum_heated_square' => '1500',
            'maximum_budget'        => '400000',
            'pool_needed'           => 'Yes',
            'garage_needed'         => 'Yes',
            'property_city'         => 'Tampa',
            'current_status'        => 'Renting',
        ];

        foreach ([$this->buyer($demandMeta), $this->tenant($demandMeta)] as $c) {
            $this->assertTrue($c->isDemand());
            $this->assertFalse($c->isSupply());

            foreach (array_keys(V::CORE) as $core) {
                $this->assertFalse($c->has($core), "{$c->listingType()} must not carry supply fact {$core}");
            }
            foreach (array_keys($c->all()) as $key) {
                $this->assertSame(V::SIDE_DEMAND, V::sideOf($key), "{$c->listingType()} emitted non-demand key {$key}");
            }

            // Existing demand behaviour is intact.
            $this->assertSame('Renting', $c->get('demand.current_status'));
        }
    }

    // ── Unknown stays unknown ───────────────────────────────────────────────

    public function test_a_bare_seller_row_fabricates_nothing(): void
    {
        $c = $this->seller([]);

        $this->assertSame('sale', $c->transactionType(), 'the role alone decides the transaction');
        foreach (array_keys(V::CORE) as $key) {
            if (in_array($key, [V::LISTING_TRANSACTION_TYPE, V::LISTING_WORKFLOW], true)) {
                continue;
            }
            $this->assertFalse($c->has($key), "{$key} must be absent, not defaulted");
        }
        $this->assertNull($c->standardStatus(), 'no Active default');
        $this->assertNull($c->coordinates());
    }

    /** @dataProvider unparseableValues */
    public function test_unparseable_values_are_absent_rather_than_guessed(string $key, string $value, string $canonical): void
    {
        $c = $this->seller(['property_type' => 'Residential', $key => $value]);

        $this->assertFalse($c->has($canonical), "{$key}='{$value}' must not produce {$canonical}");
    }

    public static function unparseableValues(): array
    {
        return [
            'bedrooms Other with no box'   => ['bedrooms', 'Other', V::PROPERTY_BEDROOMS],
            'bedrooms zero'                => ['bedrooms', '0', V::PROPERTY_BEDROOMS],
            'bedrooms fractional'          => ['bedrooms', '3.5', V::PROPERTY_BEDROOMS],
            'bathrooms quarter'            => ['bathrooms', '2.25', V::PROPERTY_BATHROOMS],
            'bathrooms prose'              => ['bathrooms', 'two', V::PROPERTY_BATHROOMS],
            'living area prose'            => ['minimum_heated_square', 'large', V::PROPERTY_LIVING_AREA_SQFT],
            'living area zero'             => ['minimum_heated_square', '0', V::PROPERTY_LIVING_AREA_SQFT],
            'year two digits'              => ['year_built', '98', V::PROPERTY_YEAR_BUILT],
            'year far future'              => ['year_built', '2999', V::PROPERTY_YEAR_BUILT],
            'pool optional'                => ['pool_needed', 'Optional', V::PROPERTY_POOL],
            'status hired agent'           => ['listing_status', 'Hired Agent', V::LISTING_STANDARD_STATUS],
            'status unrecognised'          => ['listing_status', 'Live', V::LISTING_STANDARD_STATUS],
        ];
    }

    public function test_a_labelled_bedroom_count_normalizes_at_the_boundary(): void
    {
        $this->assertSame(3, $this->seller(['bedrooms' => '3 Beds'])->bedrooms());
    }

    public function test_an_unrecognised_property_type_is_unknown_not_residential(): void
    {
        $this->assertNull($this->seller(['property_type' => 'Farm'])->propertyType());
    }

    public function test_a_feature_the_form_does_not_show_for_the_type_is_not_a_fact(): void
    {
        // Seller renders pool only for Residential/Income and garage only for
        // Residential (MlsFieldMap::propertyTypeApplicability). A stale Yes left by
        // an earlier property type says nothing about vacant land.
        $c = $this->seller(['property_type' => 'Vacant Land', 'pool_needed' => 'Yes', 'garage_needed' => 'Yes']);

        $this->assertNull($c->hasPool());
        $this->assertNull($c->hasGarage());
    }

    public function test_a_sold_listing_has_no_asserted_market_status(): void
    {
        $c = $this->seller(['listing_status' => 'Active'], ['is_sold' => true]);

        $this->assertNull($c->standardStatus());
    }

    public function test_the_feeds_status_is_used_only_when_recognised(): void
    {
        $unknown = $this->seller(['mls_listing_key' => 'CANON-S1', 'mls_standard_status' => 'Sold Maybe']);
        $this->assertNull($unknown->standardStatus());

        $spelling = $this->seller(['mls_listing_key' => 'CANON-S2', 'mls_standard_status' => 'Cancelled']);
        $this->assertSame('Canceled', $spelling->standardStatus());
    }

    // ── Coordinates ─────────────────────────────────────────────────────────

    public function test_unprovenanced_meta_coordinates_are_never_canonical(): void
    {
        $c = $this->seller($this->addressMeta() + ['property_lat' => '27.95', 'property_lng' => '-82.45']);

        $this->assertNull($c->coordinates());
        $this->assertFalse($c->has(V::LOCATION_LATITUDE));
    }

    public function test_an_exact_provenanced_coordinate_for_the_current_address_is_canonical(): void
    {
        $c = $this->seller($this->addressMeta(), [], function (int $id): void {
            $this->locationDna('seller_agent', $id, 'rooftop');
        });

        $this->assertSame(['lat' => 27.9506, 'lng' => -82.4572], $c->coordinates());
        $this->assertSame('property_location_dna.geocoded_lat', $c->fieldMeta(V::LOCATION_LATITUDE)['source_field']);
    }

    public function test_a_coarse_coordinate_is_absent_not_approximated(): void
    {
        $c = $this->seller($this->addressMeta(), [], function (int $id): void {
            $this->locationDna('seller_agent', $id, 'zip_centroid');
        });

        $this->assertNull($c->coordinates());
    }

    public function test_a_coordinate_for_a_previous_address_is_absent(): void
    {
        $c = $this->seller(['address' => '999 Different Rd'] + $this->addressMeta(), [], function (int $id): void {
            $this->locationDna('seller_agent', $id, 'rooftop');
        });

        $this->assertNull($c->coordinates());
    }

    // ── Vocabulary discipline ───────────────────────────────────────────────

    public function test_every_emitted_key_is_declared_and_correctly_typed(): void
    {
        $full = [
            'property_type' => 'Residential', 'bedrooms' => '4', 'bathrooms' => '3',
            'minimum_heated_square' => '2200', 'year_built' => '2005', 'pool_needed' => 'Yes',
            'garage_needed' => 'Yes', 'listing_status' => 'Active', 'pets' => 'Yes',
            'waterfront' => 'Yes', 'water_view' => 'Bay', 'total_acreage' => '0.25',
            'mls_listing_key' => 'CANON-V1', 'mls_list_price' => '600000',
            'mls_source_property_type' => 'Residential',
        ] + $this->addressMeta();

        foreach ([$this->seller($full), $this->landlord(['property_type' => 'Residential Property'] + $full)] as $c) {
            foreach ($c->all() as $key => $value) {
                $this->assertTrue(V::isDeclared($key), "undeclared canonical key {$key}");
                $this->assertTrue(V::valueMatchesType($key, $value), "{$key} carries a " . get_debug_type($value));
                $this->assertSame(V::SIDE_SUPPLY, V::sideOf($key), "supply row emitted demand key {$key}");
            }
        }
    }

    public function test_proprietary_terms_never_reach_the_canonical_listing(): void
    {
        // Distinctive sentinels, so a leak is unmistakable whatever key it lands under.
        $terms = [
            'maximum_budget'            => '731001',
            'desired_sale_price'        => '731002',
            'buy_now_price'             => '731003',
            'starting_price'            => '731004',
            'reserve_price'             => '731005',
            'purchase_price'            => '731006',
            'desired_rental_amount'     => '731007',
            'starting_rent'             => '731008',
            'reserve_rent'              => '731009',
            'lease_now_price'           => '731010',
            'offered_financing'         => 'SENTINEL-FINANCING',
            'auction_type'              => 'SENTINEL-AUCTION',
            'expiration_date'           => '2031-01-31',
            'compatibility_preferences' => 'SENTINEL-COMPAT',
            'security_deposit_amount'   => '731011',
        ];

        foreach ([$this->seller($terms), $this->landlord($terms)] as $c) {
            foreach ($c->all() as $key => $value) {
                $flat = is_array($value) ? implode('|', $value) : (string) $value;
                $this->assertStringNotContainsString('7310', $flat, "{$key} carries a Your Terms figure");
                $this->assertStringNotContainsString('SENTINEL', $flat, "{$key} carries a workflow term");
                $this->assertArrayNotHasKey(
                    $c->fieldMeta($key)['source_field'] ?? '',
                    $terms,
                    "{$key} was read from a Your Terms key"
                );
            }
        }
    }

    public function test_the_resolver_returns_the_expanded_listing_and_keeps_dna_keys(): void
    {
        $seller = $this->makeRow(SellerAgentAuction::class, SellerAgentAuctionMeta::class, 'seller_agent_auction_id', [
            'bedrooms' => '2', 'waterfront' => 'Yes', 'pets' => 'Yes',
        ]);

        $c = app(CanonicalListingResolver::class)->resolve('seller_agent', $seller->id);

        $this->assertInstanceOf(CanonicalListing::class, $c);
        $this->assertSame(2, $c->bedrooms());
        $this->assertTrue($c->get('property.waterfront'), 'pre-existing DNA key still present');
        $this->assertTrue($c->get('pet.policy.pets_allowed'), 'pre-existing DNA key still present');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string,string> */
    private function addressMeta(): array
    {
        return [
            'address'         => '123 Oak Lane',
            'property_city'   => 'Tampa',
            'property_state'  => 'FL',
            'property_zip'    => '33602',
            'property_county' => 'Hillsborough',
        ];
    }

    private function locationDna(string $listingType, int $listingId, string $precision): void
    {
        PropertyLocationDna::create([
            'listing_type'      => $listingType,
            'listing_id'        => $listingId,
            'source_address'    => '123 Oak Lane',
            'source_city'       => 'Tampa',
            'source_county'     => 'Hillsborough',
            'source_state'      => 'FL',
            'source_zip'        => '33602',
            'geocoded_lat'      => 27.9506,
            'geocoded_lng'      => -82.4572,
            'geocode_status'    => 'geocoded',
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'bridge_mls',
            'geocode_precision' => $precision,
            'geocoded_at'       => now(),
        ]);
    }

    /** @param array<string,string> $meta */
    private function seller(array $meta, array $columns = [], ?callable $before = null): CanonicalListing
    {
        $row = $this->makeRow(SellerAgentAuction::class, SellerAgentAuctionMeta::class, 'seller_agent_auction_id', $meta, $columns);
        if ($before) {
            $before($row->id);
        }

        return (new ByoListingAdapter())->fromModel($row->fresh(), 'seller_agent', $row->id);
    }

    /** @param array<string,string> $meta */
    private function landlord(array $meta): CanonicalListing
    {
        $row = $this->makeRow(LandlordAgentAuction::class, LandlordAgentAuctionMeta::class, 'landlord_agent_auction_id', $meta);

        return (new ByoListingAdapter())->fromModel($row->fresh(), 'landlord_agent', $row->id);
    }

    /** @param array<string,string> $meta */
    private function buyer(array $meta): CanonicalListing
    {
        $row = $this->makeRow(BuyerAgentAuction::class, null, 'buyer_agent_auction_id', $meta);

        return (new ByoListingAdapter())->fromModel($row->fresh(), 'buyer_agent', $row->id);
    }

    /** @param array<string,string> $meta */
    private function tenant(array $meta): CanonicalListing
    {
        $row = $this->makeRow(TenantAgentAuction::class, null, 'tenant_agent_auction_id', $meta);

        return (new ByoListingAdapter())->fromModel($row->fresh(), 'tenant_agent', $row->id);
    }

    /**
     * @param class-string $modelClass
     * @param class-string|null $metaClass
     * @param array<string,string> $meta
     */
    private function makeRow(string $modelClass, ?string $metaClass, string $foreignKey, array $meta, array $columns = [])
    {
        $user = User::factory()->create();

        $row = $modelClass::unguarded(fn () => $modelClass::create($columns + [
            'user_id'     => $user->id,
            'title'       => 'Canonical P0-4',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]));

        $rows = [[$foreignKey => $row->id, 'meta_key' => 'workflow_type', 'meta_value' => 'offer_listing']];
        foreach ($meta as $key => $value) {
            $rows[] = [$foreignKey => $row->id, 'meta_key' => $key, 'meta_value' => $value];
        }

        $table = $metaClass ? (new $metaClass())->getTable() : $row->meta()->getRelated()->getTable();
        DB::table($table)->insert($rows);

        return $row->fresh();
    }
}
