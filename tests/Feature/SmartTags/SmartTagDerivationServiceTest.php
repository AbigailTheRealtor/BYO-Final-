<?php

namespace Tests\Feature\SmartTags;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Services\SmartTags\DerivationOutcome;
use App\Services\SmartTags\Derivation\ListingDescriptionTagParser;
use App\Services\SmartTags\SmartTagDerivationService;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\TestCase;

/**
 * The derivation contract end to end on the SQLite test database. Nothing in the
 * application calls this service in Phase 1; these tests are its only callers.
 */
class SmartTagDerivationServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;

    /** Counts parse() calls, delegating to the real parser. */
    private ListingDescriptionTagParser $spy;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();

        $this->spy = new class extends ListingDescriptionTagParser {
            public int $calls = 0;

            public function parse(?string $text, SmartTagContext $context, SmartTagSource $source): array
            {
                $this->calls++;
                return parent::parse($text, $context, $source);
            }
        };

        $this->app->instance(ListingDescriptionTagParser::class, $this->spy);
    }

    private function service(): SmartTagDerivationService
    {
        return $this->app->make(SmartTagDerivationService::class);
    }

    /** @return array<string, string> */
    private function assignments(string $type, int $id): array
    {
        return SmartTagAssignment::query()->where('listing_type', $type)->where('listing_id', $id)
            ->orderBy('tag_key')->pluck('state', 'tag_key')->all();
    }

    /** @test */
    public function a_native_seller_listing_derives_structured_and_description_tags_with_separate_sources(): void
    {
        $listing = $this->sellerListing($this->makeOwner(), [
            'property_type'      => 'Residential',
            'waterfront'         => 'Yes',
            'interior_features'  => ['Vaulted Ceiling(s)'],
            'additional_details' => 'Beautifully renovated kitchen with quartz countertops, white shaker cabinets and stainless steel appliances.',
        ]);

        $outcome = $this->service()->deriveNative($listing);

        $this->assertTrue($outcome->derived);
        $this->assertSame(SmartTagContext::ResidentialSale, $outcome->context);
        $this->assertTrue($outcome->descriptionParsed);

        $sources = SmartTagEvidence::query()->where('listing_id', $listing->id)->pluck('source', 'tag_key')->all();
        $this->assertSame('structured_native_listing', $sources['waterfront']);
        $this->assertSame('structured_native_listing', $sources['vaulted_ceilings']);
        $this->assertSame('native_listing_description', $sources['quartz_countertops']);
        $this->assertSame('native_listing_description', $sources['stainless_appliances']);

        $states = $this->assignments('seller_agent', $listing->id);
        foreach (['waterfront', 'vaulted_ceilings', 'updated_kitchen', 'quartz_countertops', 'white_cabinets', 'shaker_cabinets', 'stainless_appliances'] as $tag) {
            $this->assertSame('present', $states[$tag] ?? null, $tag);
        }
    }

    /** @test */
    public function an_unchanged_description_is_not_reparsed_and_a_changed_one_is(): void
    {
        $listing = $this->sellerListing($this->makeOwner(), [
            'property_type' => 'Residential',
            'additional_details' => 'Quartz countertops.',
        ]);

        $this->service()->deriveNative($listing);
        $this->assertSame(1, $this->spy->calls);

        $second = $this->service()->deriveNative($listing);
        $this->assertSame(1, $this->spy->calls, 'Unchanged description must not be reparsed');
        $this->assertFalse($second->structuredDerived);
        $this->assertFalse($second->descriptionParsed);

        // Cosmetic whitespace change: same normalised hash, still no reparse.
        $listing->saveMeta('additional_details', '  Quartz   countertops.  ');
        $this->service()->deriveNative($listing);
        $this->assertSame(1, $this->spy->calls);

        $listing->saveMeta('additional_details', 'Granite countertops.');
        $changed = $this->service()->deriveNative($listing);
        $this->assertSame(2, $this->spy->calls);
        $this->assertTrue($changed->descriptionParsed);

        $states = $this->assignments('seller_agent', $listing->id);
        $this->assertArrayHasKey('granite_countertops', $states);
        $this->assertArrayNotHasKey('quartz_countertops', $states, 'Evidence from the old description is replaced');
    }

    /** @test */
    public function a_taxonomy_or_rule_change_makes_derivation_stale(): void
    {
        $listing = $this->sellerListing($this->makeOwner(), [
            'property_type' => 'Residential',
            'additional_details' => 'Quartz countertops.',
        ]);

        $this->service()->deriveNative($listing);
        $before = SmartTagDerivationState::query()->where('listing_id', $listing->id)->value('tagger_version');

        config()->set('smart_tags.version', 'test-bumped-version');
        $outcome = $this->service()->deriveNative($listing);

        $this->assertNotSame($before, SmartTagDerivationState::query()->where('listing_id', $listing->id)->value('tagger_version'));
        $this->assertTrue($outcome->structuredDerived);
        $this->assertSame(2, $this->spy->calls);
    }

    /** @test */
    public function removing_the_description_removes_its_evidence_and_leaves_unknown(): void
    {
        $listing = $this->sellerListing($this->makeOwner(), [
            'property_type' => 'Residential',
            'additional_details' => 'Kitchen island and breakfast bar.',
        ]);
        $this->service()->deriveNative($listing);
        $this->assertArrayHasKey('kitchen_island', $this->assignments('seller_agent', $listing->id));

        $listing->saveMeta('additional_details', '');
        $this->service()->deriveNative($listing);

        $this->assertSame([], $this->assignments('seller_agent', $listing->id));
        $this->assertSame(0, SmartTagAssignment::query()->where('listing_id', $listing->id)->where('state', 'absent')->count());
    }

    /** @test */
    public function only_the_public_description_field_is_parsed(): void
    {
        $listing = $this->landlordListing($this->makeOwner('landlord'), [
            'property_type'                 => 'Residential Property',
            'breed_restrictions'            => 'Private pool and fireplace.',
            'pet_restrictions'              => 'Waterfront with private dock.',
            'landlord_approval_conditions'  => 'Quartz countertops and garage.',
            'additional_details_broker'     => 'Updated kitchen.',
            'other_non_negotiable_amenities' => 'Walk-in closet',
            'additional_details'            => 'Kitchen island.',
        ]);

        $this->service()->deriveNative($listing);

        $this->assertSame(['kitchen_island' => 'present'], $this->assignments('landlord_agent', $listing->id));
    }

    /** @test */
    public function landlord_description_withheld_by_the_provider_text_policy_is_not_parsed(): void
    {
        $listing = $this->landlordListing($this->makeOwner('landlord'), [
            'property_type'      => 'Residential Property',
            'additional_details' => 'Private pool and fireplace. No housing vouchers.',
        ]);

        $outcome = $this->service()->deriveNative($listing);

        $this->assertFalse($outcome->descriptionParsed);
        $this->assertSame([], $this->assignments('landlord_agent', $listing->id));
    }

    /** @test */
    public function hire_agent_rows_and_archived_rows_receive_no_tags(): void
    {
        $owner = $this->makeOwner();
        $hire = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'Yes'], 'hire_agent');
        $archived = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'Yes'], 'offer_listing', ['is_archived' => true]);

        $this->assertSame(DerivationOutcome::NOT_AN_OFFER_LISTING, $this->service()->deriveNative($hire)->skippedReason);
        $this->assertSame(DerivationOutcome::ARCHIVED, $this->service()->deriveNative($archived)->skippedReason);
        $this->assertSame(0, SmartTagEvidence::query()->whereIn('listing_id', [$hire->id, $archived->id])->count());
    }

    /** @test */
    public function an_unsupported_property_type_produces_no_derivation(): void
    {
        $listing = $this->sellerListing($this->makeOwner(), ['property_type' => 'Farm', 'waterfront' => 'Yes']);

        $outcome = $this->service()->deriveNative($listing);

        $this->assertSame(DerivationOutcome::NO_CONTEXT, $outcome->skippedReason);
        $this->assertSame(0, SmartTagEvidence::query()->where('listing_id', $listing->id)->count());
        $this->assertSame(0, $this->spy->calls);
    }

    /** @test */
    public function bridge_derivation_stores_structured_tags_and_never_parses_remarks(): void
    {
        $raw = json_decode((string) file_get_contents(base_path('tests/fixtures/mls/bridge/residential_lease.json')), true);
        $raw['PublicRemarks'] = 'Quartz countertops, updated kitchen, move-in ready.';

        $property = BridgeProperty::create([
            'listing_key'     => 'SMARTTAG-TEST-1',
            'property_type'   => 'Residential Lease',
            'standard_status' => 'Active',
            'pool_private_yn' => true,
            'waterfront_yn'   => true,
            'garage_yn'       => true,
            'raw_json'        => json_encode($raw),
        ]);

        $outcome = $this->service()->deriveBridge($property);

        $this->assertTrue($outcome->derived);
        $this->assertContains(DerivationOutcome::MLS_REMARKS_NOT_APPROVED, $outcome->notes);
        $this->assertSame(0, $this->spy->calls, 'PublicRemarks must not be parsed');
        $this->assertSame(0, SmartTagEvidence::query()->where('source', 'mls_remarks')->count());
        $this->assertNull(SmartTagDerivationState::query()->where('listing_type', 'bridge')->value('mls_remarks_hash'));

        $states = $this->assignments('bridge', $property->id);
        $this->assertSame('present', $states['private_pool']);
        $this->assertSame('absent', $states['pets_allowed']);
        $this->assertArrayNotHasKey('quartz_countertops', $states);
        $this->assertArrayNotHasKey('move_in_ready', $states);
    }

    /** @test */
    public function equivalent_bridge_and_native_listings_resolve_to_the_same_stored_canonical_tags(): void
    {
        $property = BridgeProperty::create([
            'listing_key'     => 'SMARTTAG-TEST-2',
            'property_type'   => 'Residential',
            'standard_status' => 'Active',
            'pool_private_yn' => true,
            'waterfront_yn'   => true,
            'raw_json'        => json_encode([
                'PropertyType' => 'Residential', 'PoolPrivateYN' => true, 'WaterfrontYN' => true,
                'InteriorFeatures' => ['Quartz Counters', 'Open Floorplan'], 'FireplaceYN' => false,
            ]),
        ]);

        $listing = $this->sellerListing($this->makeOwner(), [
            'property_type'     => 'Residential',
            'pool_type'         => ['private' => true, 'community' => false],
            'waterfront'        => 'Yes',
            'interior_features' => ['Quartz Counters', 'Open Floorplan'],
        ]);

        $this->service()->deriveBridge($property);
        $this->service()->deriveNative($listing);

        $bridge = SmartTagAssignment::query()->where('listing_type', 'bridge')->where('listing_id', $property->id)
            ->where('state', 'present')->orderBy('tag_key')->pluck('context', 'tag_key')->all();
        $native = SmartTagAssignment::query()->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('state', 'present')->orderBy('tag_key')->pluck('context', 'tag_key')->all();

        $this->assertSame($bridge, $native);
        $this->assertSame(['open_floor_plan', 'private_pool', 'quartz_countertops', 'waterfront'], array_keys($native));
    }
}
