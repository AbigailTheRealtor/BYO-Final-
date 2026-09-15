<?php

namespace Tests\Feature\SmartTags;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\SmartTags\ManualSmartTagWriter;
use App\Services\SmartTags\SmartTagAssignmentPurger;
use App\Services\SmartTags\SmartTagDerivationService;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\TestCase;

class SmartTagPurgerAndInertnessTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function purging_is_scoped_to_one_listing_and_keeps_the_audit_trail(): void
    {
        $owner = $this->makeOwner();
        $a = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'Yes']);
        $b = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'Yes']);

        $service = app(SmartTagDerivationService::class);
        $service->deriveNative($a);
        $service->deriveNative($b);
        app(ManualSmartTagWriter::class)->replaceSelections(SmartTagListingRef::fromModel($a), ['kitchen_island'], $owner);

        // Same numeric id on another listing type must survive too.
        SmartTagAssignment::query()->create([
            'listing_type' => 'landlord_agent', 'listing_id' => $a->id, 'tag_key' => 'garage',
            'context' => 'residential.lease', 'state' => 'present', 'winning_source' => 'structured_native_listing',
        ]);

        $counts = app(SmartTagAssignmentPurger::class)->forListing(SmartTagListingRef::fromModel($a));

        $this->assertGreaterThan(0, $counts['evidence']);
        $this->assertSame(0, SmartTagEvidence::query()->where('listing_type', 'seller_agent')->where('listing_id', $a->id)->count());
        $this->assertSame(0, SmartTagAssignment::query()->where('listing_type', 'seller_agent')->where('listing_id', $a->id)->count());
        $this->assertSame(0, SmartTagDerivationState::query()->where('listing_type', 'seller_agent')->where('listing_id', $a->id)->count());

        $this->assertGreaterThan(0, SmartTagAssignment::query()->where('listing_type', 'seller_agent')->where('listing_id', $b->id)->count());
        $this->assertSame(1, SmartTagAssignment::query()->where('listing_type', 'landlord_agent')->where('listing_id', $a->id)->count());
        $this->assertSame(1, SmartTagManualEvent::query()->where('listing_id', $a->id)->count(), 'The audit trail is kept');
    }

    /** @test */
    public function the_existing_bridge_import_writes_no_smart_tags(): void
    {
        $raw = json_decode((string) file_get_contents(base_path('tests/fixtures/mls/bridge/residential_lease.json')), true);
        $raw['ListingKey'] = 'SMARTTAG-INERT-1';

        app(BridgePropertyNormalizer::class)->upsert($raw);

        $this->assertTrue(BridgeProperty::query()->where('listing_key', 'SMARTTAG-INERT-1')->exists());
        $this->assertSame(0, SmartTagEvidence::query()->count());
        $this->assertSame(0, SmartTagAssignment::query()->count());
        $this->assertSame(0, SmartTagDerivationState::query()->count());
    }

    /** @test */
    public function saving_a_native_offer_listing_writes_no_smart_tags(): void
    {
        $this->sellerListing($this->makeOwner(), [
            'property_type' => 'Residential',
            'waterfront' => 'Yes',
            'additional_details' => 'Quartz countertops and a private pool.',
        ]);
        $this->landlordListing($this->makeOwner('landlord'), [
            'property_type' => 'Residential Property',
            'tenant_require' => 'Furnished',
        ]);

        $this->assertSame(0, SmartTagEvidence::query()->count());
        $this->assertSame(0, SmartTagAssignment::query()->count());
        $this->assertSame(0, SmartTagManualEvent::query()->count());
    }

    /** @test */
    public function the_listing_type_registry_never_uses_ambiguous_role_strings(): void
    {
        $this->assertSame(['bridge', 'seller_agent', 'landlord_agent'],
            array_map(static fn (SmartTagListingType $t) => $t->value, SmartTagListingType::cases()));
    }
}
