<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Writer;
use App\Services\ListingImport\Sync\MlsFactProjection;
use App\Services\ListingImport\Sync\MlsSyncFieldPolicy;
use App\Support\Listing\PropertyTypeVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stellar PropertyType → BYO property type → MLS feature applicability → Your Terms.
 *
 * WHY THIS IS NOT A MAPPER TEST
 * -----------------------------
 * QuickImportPropertyTypeRenderTest already pins the vocabulary function. The
 * defect this file exists for is what happens AFTER the string is right, and it
 * has three layers that were only ever checked at the first:
 *
 *   1. the stored `property_type` — the mapper's output;
 *   2. which MLS facts were actually WRITTEN — property-type-specific feature
 *      applicability, which MlsFieldMap has expressed since the URL/text importer
 *      needed it and which MlsFactProjection (the path Quick Import and the
 *      unattended sync both write through) did not consult at all;
 *   3. which Your Terms branches RENDER — the canonical partials gate on exact
 *      equality against BYO vocabulary.
 *
 * A listing can be labelled `Income`, be written Residential-only garage and
 * carport values, and render the Vacant-Land-suppressed block, all at once. So
 * every accepted case below asserts all three, and asserts the NEGATIVE too: the
 * other property type's specific fields must not have been selected.
 *
 * THE CASE THAT MOTIVATED IT
 * --------------------------
 * `Residential Income` used to normalise to Seller `Residential`, because the
 * mapper tested the substring `residential` before `income`. A multi-family sale
 * therefore entered the Residential track and inherited Residential-only feature
 * applicability by being mislabelled — while BidYourOffer has a separate,
 * populated `Income` category that nothing ever reached. Relabelling alone would
 * have been cosmetic: the applicability gate has to move with it, which is why
 * both are asserted here on the same record.
 */
class MlsQuickImportPropertyTypeMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private User $seller;
    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => false,
            'mls_media.license_acknowledged'         => false,
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
        ]);

        $this->seller   = User::factory()->create(['user_type' => 'seller']);
        $this->landlord = User::factory()->create(['user_type' => 'landlord']);
    }

    /**
     * A Bridge record carrying a property type and BOTH type-gated features.
     *
     * PoolPrivateYN and CarportYN are always present so the applicability
     * assertions measure the gate rather than an absent source value. A test
     * whose fixture omitted them would pass for the wrong reason on every type.
     */
    private function seedRecord(string $mls, string $propertyType, array $overrides = []): void
    {
        BridgeProperty::create(array_merge([
            'listing_key'             => $mls . '-KEY',
            'listing_id'              => $mls,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => $propertyType,
            'property_sub_type'       => 'Condominium',
            'list_price'              => 425000,
            'unparsed_address'        => '55 Matrix Avenue',
            'city'                    => 'TAMPA',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'year_built'              => 2004,
            // Native columns, not raw_json: BridgePropertyCandidateAdapter reads
            // pool and garage from the normalised columns (carport is the one it
            // takes from the raw record), and a fixture that set only raw_json
            // would leave every applicability assertion below passing because the
            // fact was absent rather than because the gate excluded it.
            'pool_private_yn'         => true,
            'garage_yn'               => true,
            'raw_json'                => json_encode([
                'ListingKey'      => $mls . '-KEY',
                'ListingId'       => $mls,
                'PropertyType'    => $propertyType,
                'UnparsedAddress' => '55 Matrix Avenue',
                'PoolPrivateYN'   => true,
                'CarportYN'       => true,
                'GarageYN'        => true,
            ]),
        ], $overrides));
    }

    private function driveTo(string $role, string $mls): \Livewire\Testing\TestableLivewire
    {
        return Livewire::actingAs($role === 'seller' ? $this->seller : $this->landlord)
            ->test($role === 'seller' ? SellerMlsQuickImport::class : LandlordMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty');
    }

    private function toTerms(string $role, string $mls): \Livewire\Testing\TestableLivewire
    {
        return $this->driveTo($role, $mls)
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');
    }

    private function listing(string $role, $id)
    {
        return $role === 'seller'
            ? SellerAgentAuction::find($id)->fresh()
            : LandlordAgentAuction::find($id)->fresh();
    }

    // =====================================================================
    // A. ACCEPTED — stored type, source preserved, applicability, terms
    // =====================================================================

    /**
     * @dataProvider acceptedRecords
     * @test
     */
    public function an_accepted_record_lands_in_the_right_property_type(
        string $role,
        string $sourceType,
        string $expectedType
    ): void {
        $mls = 'QI-MX-' . substr(md5($role . $sourceType), 0, 8);
        $this->seedRecord($mls, $sourceType);

        $component = $this->driveTo($role, $mls);

        $this->assertSame(
            'method',
            $component->get('step'),
            "{$sourceType} must be importable by a {$role}"
        );

        $listing = $this->listing($role, $component->get('listingId'));

        // 1. The stored BYO property type.
        $this->assertSame(
            $expectedType,
            (string) $listing->info('property_type'),
            "{$sourceType} must be stored as {$expectedType} for a {$role}"
        );

        // 2. The feed's own wording is preserved, not replaced by the translation.
        $this->assertSame(
            $sourceType,
            (string) $listing->info(Writer::META_SOURCE_PTYPE),
            'the raw MLS property type must survive normalisation'
        );

        // 3. The component carries the canonical value the Blades compare against.
        $this->assertSame($expectedType, $component->get('property_type'));

        // 4. It is a category this role actually offers.
        $this->assertContains(
            $expectedType,
            PropertyTypeVocabulary::categoriesForRole($role),
            "{$expectedType} is not a category the {$role} form offers"
        );
    }

    public function acceptedRecords(): array
    {
        return [
            // Proven on real cached Stellar records
            'residential sale → seller Residential'   => ['seller',   'Residential',          'Residential'],
            'commercial sale → seller Commercial'     => ['seller',   'Commercial Sale',      'Commercial'],
            'business opportunity → seller Business'  => ['seller',   'Business Opportunity', 'Business'],
            'residential lease → landlord Residential'=> ['landlord', 'Residential Lease',    'Residential Property'],

            // Composed fixtures / declared support
            'income → seller Income'                  => ['seller',   'Income',               'Income'],
            'vacant land → seller Vacant Land'        => ['seller',   'Vacant Land',          'Vacant Land'],
            'commercial lease → landlord Commercial'  => ['landlord', 'Commercial Lease',     'Commercial Property'],

            // THE FIX. RESO's own spelling of the Income category.
            'residential income → seller Income'      => ['seller',   'Residential Income',   'Income'],
        ];
    }

    /**
     * @test
     *
     * PropertySubType must not move the answer. It is a style/fact field and has
     * no target in MlsFieldMap; the classification is PropertyType's alone.
     */
    public function a_condominium_sub_type_is_still_seller_residential(): void
    {
        $this->seedRecord('QI-MX-CONDO', 'Residential', ['property_sub_type' => 'Condominium']);

        $component = $this->driveTo('seller', 'QI-MX-CONDO');
        $listing   = $this->listing('seller', $component->get('listingId'));

        $this->assertSame('Residential', (string) $listing->info('property_type'));
    }

    // =====================================================================
    // B. FEATURE APPLICABILITY — the second layer
    // =====================================================================

    /**
     * @test
     *
     * Income is the case the whole change turns on. It renders a pool control and
     * NO garage or carport control, so an Income import must write the first and
     * neither of the other two — even though the feed supplied all three.
     */
    public function an_income_import_does_not_inherit_residential_only_features(): void
    {
        $this->seedRecord('QI-MX-INC2', 'Residential Income');

        $component = $this->driveTo('seller', 'QI-MX-INC2');
        $listing   = $this->listing('seller', $component->get('listingId'));

        $this->assertSame('Income', (string) $listing->info('property_type'));

        // Applicable to Income → written.
        $this->assertNotFalse(
            $listing->info('pool_needed'),
            'pool is applicable to Seller Income and must be imported'
        );

        // Residential-only → must NOT be written.
        foreach (['garage_needed', 'carport_needed'] as $residentialOnly) {
            $this->assertFalse(
                $listing->info($residentialOnly),
                "{$residentialOnly} renders no input on a Seller Income listing, so importing it "
                . 'writes a value the user can neither see nor correct'
            );
        }
    }

    /** @test */
    public function a_residential_import_does_receive_the_residential_only_features(): void
    {
        $this->seedRecord('QI-MX-RES2', 'Residential');

        $component = $this->driveTo('seller', 'QI-MX-RES2');
        $listing   = $this->listing('seller', $component->get('listingId'));

        $this->assertSame('Residential', (string) $listing->info('property_type'));

        foreach (['pool_needed', 'garage_needed', 'carport_needed'] as $applicable) {
            $this->assertNotFalse(
                $listing->info($applicable),
                "{$applicable} is applicable to Seller Residential and must be imported"
            );
        }
    }

    /**
     * @test
     *
     * Vacant Land renders none of the three. This is the case
     * MlsFieldMap::propertyTypeApplicability() was originally written for, now
     * enforced on the projection path too.
     */
    public function a_vacant_land_import_receives_no_type_gated_features(): void
    {
        $this->seedRecord('QI-MX-LAND2', 'Vacant Land');

        $component = $this->driveTo('seller', 'QI-MX-LAND2');
        $listing   = $this->listing('seller', $component->get('listingId'));

        $this->assertSame('Vacant Land', (string) $listing->info('property_type'));

        foreach (['pool_needed', 'garage_needed', 'carport_needed'] as $inapplicable) {
            $this->assertFalse(
                $listing->info($inapplicable),
                "{$inapplicable} renders no input on a Vacant Land listing"
            );
        }
    }

    /**
     * @test
     *
     * The projection is the shared write path, so the gate is asserted directly
     * on it as well — the flow tests above prove it works end to end, this proves
     * the rule lives where both write paths can see it.
     */
    public function the_projection_gate_is_shared_by_import_and_sync(): void
    {
        $facts = ['property_type' => 'Residential Income', 'pool' => 'Yes', 'garage' => 'Yes', 'carport' => 'Yes'];

        foreach ([MlsFactProjection::MODE_IMPORT, MlsFactProjection::MODE_SYNC] as $mode) {
            $writes = (new MlsFactProjection())->project(
                role:               'seller',
                facts:              $facts,
                existing:           [],
                mode:               $mode,
                sourcePropertyType: 'Residential Income',
            );

            $this->assertSame('Income', $writes['property_type'] ?? null, $mode);
            $this->assertArrayHasKey('pool_needed', $writes, $mode);
            $this->assertArrayNotHasKey('garage_needed', $writes, $mode);
            $this->assertArrayNotHasKey('carport_needed', $writes, $mode);
        }
    }

    /**
     * @test
     *
     * A type-gated key whose property type cannot be established is SKIPPED, not
     * written. The gate's whole purpose is to avoid writing a field the form does
     * not render, and an unknown type cannot rule that out.
     */
    public function an_unknown_property_type_writes_no_type_gated_feature(): void
    {
        $writes = (new MlsFactProjection())->project(
            role:               'seller',
            facts:              ['pool' => 'Yes', 'garage' => 'Yes', 'carport' => 'Yes', 'bedrooms' => '3'],
            existing:           [],
            mode:               MlsFactProjection::MODE_IMPORT,
            sourcePropertyType: null,
        );

        $this->assertArrayNotHasKey('pool_needed', $writes);
        $this->assertArrayNotHasKey('garage_needed', $writes);
        $this->assertArrayNotHasKey('carport_needed', $writes);

        // Ungated facts are unaffected — this is an additive gate, not a filter
        // on everything.
        $this->assertArrayHasKey('bedrooms', $writes);
    }

    /**
     * @test
     *
     * carport was missing from the shared applicability map even though its input
     * sits inside the same conditional as garage on both blades. Pinned so it
     * cannot be dropped again.
     */
    public function the_shared_applicability_map_covers_every_type_gated_control(): void
    {
        $seller = MlsFieldMap::propertyTypeApplicability('seller');
        $this->assertSame(['Residential', 'Income'], $seller['pool']);
        $this->assertSame(['Residential'], $seller['garage']);
        $this->assertSame(['Residential'], $seller['carport']);

        $landlord = MlsFieldMap::propertyTypeApplicability('landlord');
        $this->assertSame(['Residential Property'], $landlord['pool']);
        $this->assertSame(['Residential Property'], $landlord['garage']);
        $this->assertSame(['Residential Property'], $landlord['carport']);
    }

    // =====================================================================
    // C. YOUR TERMS — the third layer, rendered not inferred
    // =====================================================================

    /**
     * @test
     *
     * Seller Vacant Land is the one seller type the canonical Sale Terms partial
     * gates on (`@if ($property_type != 'Vacant Land')`), so it is the seller
     * case where a wrong label visibly changes the question set. Every other
     * seller type must SEE that block; Vacant Land must not.
     */
    public function the_seller_terms_branch_follows_the_property_type(): void
    {
        $this->seedRecord('QI-MX-TRES', 'Residential');
        $residential = $this->toTerms('seller', 'QI-MX-TRES');
        $this->assertSame('terms', $residential->get('step'));
        $residentialHtml = (string) $residential->lastRenderedDom;

        $this->seedRecord('QI-MX-TLAND', 'Vacant Land');
        $land = $this->toTerms('seller', 'QI-MX-TLAND');
        $this->assertSame('terms', $land->get('step'));
        $landHtml = (string) $land->lastRenderedDom;

        // Both render the SAME canonical partial — no per-type question catalog.
        $this->assertSame(
            'livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms',
            $residential->instance()->canonicalTermsPartial()
        );
        $this->assertSame(
            $residential->instance()->canonicalTermsPartial(),
            $land->instance()->canonicalTermsPartial()
        );

        // …and the property-type-gated block differs between them.
        $this->assertStringContainsString('occupant_status', $residentialHtml);
        $this->assertStringNotContainsString('occupant_status', $landHtml);
    }

    /** @test */
    public function seller_income_renders_the_same_canonical_terms_as_seller_residential(): void
    {
        $this->seedRecord('QI-MX-TINC', 'Residential Income');
        $income = $this->toTerms('seller', 'QI-MX-TINC');

        $this->assertSame('terms', $income->get('step'));
        $this->assertSame('Income', $income->get('property_type'));

        // Income is not Vacant Land, so it keeps the gated block — the assertion
        // that would have failed while Residential Income was mislabelled as
        // anything outside the seller vocabulary.
        $this->assertStringContainsString('occupant_status', (string) $income->lastRenderedDom);

        $this->assertSame(
            'livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms',
            $income->instance()->canonicalTermsPartial()
        );
    }

    /**
     * @test
     *
     * The landlord partial is the one that gates heavily, and the two categories
     * must reach different branches of it.
     */
    public function the_landlord_terms_branch_follows_the_property_type(): void
    {
        $this->seedRecord('QI-MX-TLR', 'Residential Lease');
        $res = $this->toTerms('landlord', 'QI-MX-TLR');
        $resHtml = (string) $res->lastRenderedDom;

        $this->seedRecord('QI-MX-TLC', 'Commercial Lease');
        $com = $this->toTerms('landlord', 'QI-MX-TLC');
        $comHtml = (string) $com->lastRenderedDom;

        $this->assertSame(
            'livewire.offer-listing.offer-landlord-tabs.commission-based.lease-terms',
            $res->instance()->canonicalTermsPartial()
        );
        $this->assertSame(
            $res->instance()->canonicalTermsPartial(),
            $com->instance()->canonicalTermsPartial()
        );

        // Commercial-only sections.
        $this->assertStringContainsString('cam_nnn_additional_rent_charges', $comHtml);
        $this->assertStringNotContainsString('cam_nnn_additional_rent_charges', $resHtml);

        // Residential-only section.
        $this->assertStringContainsString('utilities', $resHtml);
    }

    /**
     * @dataProvider sellerTypeExpectations
     * @test
     *
     * The full Seller enumeration, all three layers at once: every category the
     * form offers, the type-gated features it may and may not receive, and the
     * canonical Sale Terms partial with its one property-type branch.
     *
     * Listed exhaustively rather than sampled because "Residential, Income,
     * Commercial, Business, Vacant Land are separate and not interchangeable" is
     * the claim under test, and a sample cannot make it.
     *
     * @param  list<string>  $applicable
     * @param  list<string>  $inapplicable
     */
    public function every_seller_category_gets_its_own_features_and_terms(
        string $sourceType,
        string $expectedType,
        array $applicable,
        array $inapplicable,
        bool $seesOccupantBlock
    ): void {
        $mls = 'QI-SE-' . substr(md5($sourceType), 0, 8);
        $this->seedRecord($mls, $sourceType);

        $component = $this->toTerms('seller', $mls);

        $this->assertSame('terms', $component->get('step'), "{$sourceType} must reach the terms step");
        $this->assertSame($expectedType, $component->get('property_type'));

        $listing = $this->listing('seller', $component->get('listingId'));
        $this->assertSame($expectedType, (string) $listing->info('property_type'));

        foreach ($applicable as $key) {
            $this->assertNotFalse(
                $listing->info($key),
                "{$key} is applicable to Seller {$expectedType} and must be imported"
            );
        }

        foreach ($inapplicable as $key) {
            $this->assertFalse(
                $listing->info($key),
                "{$key} renders no input on a Seller {$expectedType} listing, so importing it "
                . 'writes a value the user can neither see nor correct'
            );
        }

        // One canonical partial for every seller category — no per-type catalog.
        $this->assertSame(
            'livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms',
            $component->instance()->canonicalTermsPartial()
        );

        // …whose single property-type branch follows the category.
        $html = (string) $component->lastRenderedDom;

        $seesOccupantBlock
            ? $this->assertStringContainsString('occupant_status', $html)
            : $this->assertStringNotContainsString('occupant_status', $html);
    }

    public function sellerTypeExpectations(): array
    {
        $all = ['pool_needed', 'garage_needed', 'carport_needed'];

        return [
            'Residential'        => ['Residential',          'Residential', $all, [], true],
            'Income'             => ['Income',               'Income',      ['pool_needed'], ['garage_needed', 'carport_needed'], true],
            'Residential Income' => ['Residential Income',   'Income',      ['pool_needed'], ['garage_needed', 'carport_needed'], true],
            'Commercial'         => ['Commercial Sale',      'Commercial',  [], $all, true],
            'Business'           => ['Business Opportunity', 'Business',    [], $all, true],
            'Vacant Land'        => ['Vacant Land',          'Vacant Land', [], $all, false],
        ];
    }

    // =====================================================================
    // D. BLOCKED — the fail-closed half
    // =====================================================================

    /**
     * @dataProvider blockedRecords
     * @test
     */
    public function a_record_this_role_cannot_import_is_refused_before_anything_is_written(
        string $role,
        ?string $sourceType,
        string $expectedFragment
    ): void {
        $mls = 'QI-BLK-' . substr(md5($role . (string) $sourceType), 0, 8);
        $this->seedRecord($mls, (string) $sourceType);

        $component = Livewire::actingAs($role === 'seller' ? $this->seller : $this->landlord)
            ->test($role === 'seller' ? SellerMlsQuickImport::class : LandlordMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing');

        // Refused at lookup — the user is told before being shown a property.
        $this->assertSame('lookup', $component->get('step'));
        $this->assertStringContainsString($expectedFragment, (string) $component->get('errorMessage'));

        // …and refused again at the write boundary, which is the one that matters:
        // acceptProperty() is reachable without step 1.
        $component->call('acceptProperty');

        $this->assertNull(
            $component->get('listingId'),
            'a refused record must not materialise a draft listing'
        );
        $this->assertSame('lookup', $component->get('step'));

        // Nothing was written for this user at all.
        $this->assertSame(
            0,
            $role === 'seller'
                ? SellerAgentAuction::where('user_id', $this->seller->id)->count()
                : LandlordAgentAuction::where('user_id', $this->landlord->id)->count(),
            'a refused record must leave no listing row behind'
        );

        // …and the wizard cannot be walked forward past the refusal.
        $component->call('chooseMethod', 'Traditional')->call('continueToTerms');
        $this->assertSame('lookup', $component->get('step'));

        $component->call('continueToReview');
        $this->assertNotSame('review', $component->get('step'), 'a refused record must never reach Review');

        $component->call('publish');
        $this->assertSame(
            0,
            $role === 'seller'
                ? SellerAgentAuction::where('user_id', $this->seller->id)->count()
                : LandlordAgentAuction::where('user_id', $this->landlord->id)->count(),
            'a refused record must never reach Publish'
        );
    }

    public function blockedRecords(): array
    {
        $rental  = 'rental listing';
        $forSale = 'for-sale listing';
        $unknown = 'create this listing manually';

        return [
            // Seller may not import a lease — the mirror of the landlord rule.
            'seller + residential lease'   => ['seller',   'Residential Lease',    $rental],
            'seller + commercial lease'    => ['seller',   'Commercial Lease',     $rental],

            // Landlord may not import a sale, in any of its flavours.
            'landlord + residential sale'  => ['landlord', 'Residential',          $forSale],
            'landlord + commercial sale'   => ['landlord', 'Commercial Sale',      $forSale],
            'landlord + income'            => ['landlord', 'Income',               $forSale],
            'landlord + residential income'=> ['landlord', 'Residential Income',   $forSale],
            'landlord + business'          => ['landlord', 'Business Opportunity', $forSale],
            'landlord + vacant land'       => ['landlord', 'Vacant Land',          $forSale],

            // Unclassifiable, for either role. Farm and Manufactured In Park are
            // real RESO members with no BidYourOffer category — deliberately
            // unrecognised rather than folded into a neighbouring one.
            'seller + farm'                => ['seller',   'Farm',                 $unknown],
            'landlord + farm'              => ['landlord', 'Farm',                 $unknown],
            'seller + manufactured in park'=> ['seller',   'Manufactured In Park', $unknown],
            'seller + nonsense'            => ['seller',   'Houseboat',            $unknown],
            'seller + missing type'        => ['seller',   '',                     $unknown],
            'landlord + missing type'      => ['landlord', '',                     $unknown],
        ];
    }

    /**
     * @test
     *
     * The refusal must never default to a category. Silently choosing Residential
     * is the failure this whole gate exists to prevent, and it is the one a
     * future "be more forgiving" change would reintroduce.
     */
    public function a_refused_record_leaves_the_component_with_no_property_type(): void
    {
        $this->seedRecord('QI-BLK-NONE', 'Farm');

        $component = $this->driveTo('seller', 'QI-BLK-NONE');

        $this->assertSame('', (string) $component->get('property_type'));
        $this->assertNotSame('Residential', $component->get('property_type'));
        $this->assertNotSame('Commercial', $component->get('property_type'));
    }

    // =====================================================================
    // E. PRICE MISMATCH — Part 14
    // =====================================================================

    /**
     * @test
     *
     * The seller half of the price guard, asserted on the policy directly because
     * the eligibility guard now refuses the record before seeding is reached.
     * Both layers are deliberate and both are pinned.
     */
    public function a_lease_price_may_never_seed_a_sellers_sale_price(): void
    {
        foreach (['Residential Lease', 'Commercial Lease'] as $lease) {
            $this->assertFalse(
                MlsSyncFieldPolicy::allowsPriceSync('seller', $lease),
                "a {$lease} ListPrice is a monthly rent and must never reach maximum_budget"
            );
        }

        // The projection is where that refusal actually stops the write.
        $writes = (new MlsFactProjection())->project(
            role:               'seller',
            facts:              ['property_type' => 'Residential Lease', 'price' => '2400'],
            existing:           [],
            mode:               MlsFactProjection::MODE_IMPORT,
            sourcePropertyType: 'Residential Lease',
        );

        $this->assertArrayNotHasKey('maximum_budget', $writes);
    }

    /** @test */
    public function a_sale_price_may_never_seed_a_landlords_rent(): void
    {
        foreach (['Residential', 'Commercial Sale', 'Income', 'Vacant Land', 'Business Opportunity'] as $sale) {
            $this->assertFalse(
                MlsSyncFieldPolicy::allowsPriceSync('landlord', $sale),
                "a {$sale} ListPrice is a purchase price and must never reach desired_rental_amount"
            );
        }

        $writes = (new MlsFactProjection())->project(
            role:               'landlord',
            facts:              ['property_type' => 'Commercial Sale', 'price' => '425000'],
            existing:           [],
            mode:               MlsFactProjection::MODE_IMPORT,
            sourcePropertyType: 'Commercial Sale',
        );

        $this->assertArrayNotHasKey('desired_rental_amount', $writes);
    }

    /** @test */
    public function the_matching_transaction_still_seeds_the_price(): void
    {
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('seller', 'Residential'));
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('seller', 'Commercial Sale'));
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Residential Lease'));
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Commercial Lease'));
    }

    /**
     * @test
     *
     * Import and sync must agree. Two implementations of one safety rule is how
     * they come to disagree, and this is the rule whose disagreement publishes a
     * sale price as a monthly rent.
     */
    public function the_import_seed_and_the_sync_policy_agree(): void
    {
        $this->seedRecord('QI-PRICE-LL', 'Residential Lease');
        $landlord = $this->driveTo('landlord', 'QI-PRICE-LL');
        $this->assertSame('425000', (string) $landlord->get('desired_rental_amount'));
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Residential Lease'));

        $this->seedRecord('QI-PRICE-S', 'Residential');
        $seller = $this->driveTo('seller', 'QI-PRICE-S');
        $this->assertSame('425000', (string) $seller->get('maximum_budget'));
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('seller', 'Residential'));
    }

    // =====================================================================
    // F. STATUS MUST NEVER CONTROL PROPERTY TYPE — Part 15
    // =====================================================================

    /**
     * @dataProvider statusValues
     * @test
     *
     * StandardStatus and MlsStatus are MARKET status. Moving a listing between
     * Active, Pending and Closed must not move it between Residential and
     * Commercial — they are different fields answering different questions, and
     * this is the anti-drift proof that nothing has started reading one for the
     * other.
     */
    public function market_status_cannot_change_the_property_type(string $standard, string $mls): void
    {
        $number = 'QI-ST-' . substr(md5($standard . $mls), 0, 8);

        $this->seedRecord($number, 'Residential', [
            'standard_status' => $standard,
            'mls_status'      => $mls,
        ]);

        $component = $this->driveTo('seller', $number);
        $listing   = $this->listing('seller', $component->get('listingId'));

        $this->assertSame(
            'Residential',
            (string) $listing->info('property_type'),
            "StandardStatus '{$standard}' / MlsStatus '{$mls}' must not affect the property type"
        );
    }

    public function statusValues(): array
    {
        return [
            'active'                => ['Active', 'Active'],
            'pending'               => ['Pending', 'Pending'],
            'closed vs sold'        => ['Closed', 'Sold'],
            'under contract'        => ['Active Under Contract', 'Pending'],
            'coming soon'           => ['Coming Soon', 'Coming Soon'],
        ];
    }

    /**
     * @test
     *
     * The structural half: no status string appears anywhere in the classifier.
     */
    public function the_classifier_reads_no_status_field(): void
    {
        foreach ([
            'app/Support/Listing/PropertyTypeVocabulary.php',
            'app/Services/ListingImport/QuickImport/MlsQuickImportEligibility.php',
        ] as $relative) {
            $source = file_get_contents(base_path($relative));

            // Strip docblocks — both files EXPLAIN that status is not consulted,
            // and that prose must not be mistaken for a read.
            $code = preg_replace('#/\*.*?\*/#s', '', $source) ?? '';

            foreach (['standardStatus', 'StandardStatus', 'mlsStatus', 'MlsStatus', 'mls_status'] as $statusRef) {
                $this->assertStringNotContainsString(
                    $statusRef,
                    $code,
                    $relative . ' must not read a market status to decide a property type'
                );
            }
        }
    }

    // =====================================================================
    // G. USER-CORRECTED VALUES SURVIVE RE-IMPORT — Part 16
    // =====================================================================

    /**
     * @test
     *
     * MODE_IMPORT precedence is "the user wins", and this change must not have
     * weakened it. A seller who corrected the property type — or any populated
     * editable field — keeps their answer when the same record is imported again.
     */
    public function a_user_corrected_value_survives_a_re_import(): void
    {
        $writes = (new MlsFactProjection())->project(
            role:     'seller',
            facts:    ['property_type' => 'Residential', 'bedrooms' => '3', 'pool' => 'Yes'],
            existing: ['property_type' => 'Income', 'bedrooms' => '4'],
            mode:     MlsFactProjection::MODE_IMPORT,
            sourcePropertyType: 'Residential',
        );

        // Populated editable fields are left alone.
        $this->assertArrayNotHasKey('property_type', $writes);
        $this->assertArrayNotHasKey('bedrooms', $writes);

        // And applicability follows the type the listing will actually HAVE —
        // the user's `Income`, not the feed's `Residential` — so a re-import
        // cannot write back the Residential-only garage control that the Income
        // form does not render. Reverting a correction by a side door while
        // property_type itself is correctly left alone is the specific way this
        // gate could have undone the "user wins" rule.
        $this->assertArrayHasKey('pool_needed', $writes);
        $this->assertArrayNotHasKey('garage_needed', $writes);
        $this->assertArrayNotHasKey('carport_needed', $writes);
    }

    /**
     * @test
     *
     * The sync mirror: Stellar owns property_type there, so the features follow
     * the INCOMING type in the same pass rather than the stored one. The two
     * modes must disagree here, and this pins that they do.
     */
    public function sync_judges_applicability_against_the_incoming_type(): void
    {
        $writes = (new MlsFactProjection())->project(
            role:     'seller',
            facts:    ['property_type' => 'Residential', 'garage' => 'Yes', 'pool' => 'Yes'],
            existing: ['property_type' => 'Income'],
            mode:     MlsFactProjection::MODE_SYNC,
            sourcePropertyType: 'Residential',
        );

        $this->assertSame('Residential', $writes['property_type'] ?? null);
        $this->assertArrayHasKey('garage_needed', $writes);
        $this->assertArrayHasKey('pool_needed', $writes);
    }

    /**
     * @test
     *
     * With no incoming type, applicability falls back to what the listing already
     * is. A routine sync carrying no property_type must not blank a listing's
     * type-gated facts by treating the type as unknown.
     */
    public function applicability_falls_back_to_the_stored_property_type(): void
    {
        $writes = (new MlsFactProjection())->project(
            role:     'seller',
            facts:    ['garage' => 'Yes', 'pool' => 'Yes'],
            existing: ['property_type' => 'Residential'],
            mode:     MlsFactProjection::MODE_SYNC,
            sourcePropertyType: 'Residential',
        );

        $this->assertArrayHasKey('garage_needed', $writes);
        $this->assertArrayHasKey('pool_needed', $writes);
    }

    /** @test */
    public function normalisation_is_idempotent_across_a_re_import(): void
    {
        foreach ([
            ['Residential', 'seller'], ['Income', 'seller'], ['Commercial', 'seller'],
            ['Business', 'seller'], ['Vacant Land', 'seller'],
            ['Residential Property', 'landlord'], ['Commercial Property', 'landlord'],
        ] as [$value, $role]) {
            $once  = PropertyTypeVocabulary::forRole($value, $role);
            $twice = PropertyTypeVocabulary::forRole($once, $role);

            $this->assertSame($value, $once, "{$value} / {$role}");
            $this->assertSame($once, $twice, "{$value} / {$role} is not idempotent");
        }
    }

    // =====================================================================
    // H. ONE MAPPER — Part 2 / Part 8
    // =====================================================================

    /**
     * @test
     *
     * No second Stellar → internal property-type interpretation. The canonical
     * mapper is the only thing allowed to turn a feed word into a BYO category,
     * and the inline copy that used to live in LandlordOfferListingEdit is gone.
     */
    public function nothing_outside_the_vocabulary_interprets_a_property_type(): void
    {
        $offenders = [];

        foreach ([
            'app/Http/Livewire/OfferListing',
            'app/Services/ListingImport',
        ] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir))
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents($file->getPathname())) ?? '';

                // The signature of a hand-rolled property-type branch: a
                // classification substring test that assigns a BYO category.
                if (preg_match('/str_contains\([^)]*,\s*[\'"](commercial|residential)[\'"]\s*\)/i', $code)) {
                    $offenders[] = str_replace(base_path() . '/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'these files classify a property type themselves instead of calling '
            . 'PropertyTypeVocabulary::forRole(): ' . implode(', ', $offenders)
        );
    }

    /** @test */
    public function the_landlord_edit_screen_uses_the_canonical_mapper(): void
    {
        $source = file_get_contents(
            base_path('app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php')
        );

        $this->assertStringContainsString(
            "PropertyTypeVocabulary::forRole((string) \$rawPt, 'landlord')",
            $source
        );
    }
}
