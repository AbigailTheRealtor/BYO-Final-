<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceSubjectResolver;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Resolving the durable subject a preference is stored against, from rows that
 * already exist.
 */
class ListingPreferenceSubjectResolverTest extends TestCase
{
    use DatabaseTransactions;

    private ListingPreferenceSubjectResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ListingPreferenceSubjectResolver();
    }

    /** @test */
    public function a_bridge_row_resolves_to_its_listing_key(): void
    {
        $bridge = BridgeProperty::create([
            'provider'                => 'stellar_bridge',
            'listing_key'     => 'RESOLVER-MFR-1',
            'listing_id'      => 'A1',
            'standard_status' => 'Active',
            'property_type'   => 'Residential',
        ]);

        $subject = $this->resolver->resolve(
            new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id)
        );

        $this->assertNotNull($subject);
        $this->assertSame('mls:stellar_bridge:RESOLVER-MFR-1', $subject->subjectKey);
        $this->assertSame($bridge->id, $subject->listingId());
    }

    /**
     * A Bridge row with no listing key has no durable identity, so no preference
     * may be stored against it — better than inventing a key that may later
     * belong to something else.
     *
     * @test
     */
    public function a_bridge_row_without_a_listing_key_resolves_to_nothing(): void
    {
        $bridge = BridgeProperty::create([
            'provider'        => 'stellar_bridge',
            'listing_id'      => 'A2',
            'standard_status' => 'Active',
            'property_type'   => 'Residential',
        ]);

        $this->assertNull(
            $this->resolver->resolve(new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id))
        );
    }

    /** @test */
    public function a_native_listing_without_mls_provenance_resolves_to_itself(): void
    {
        $auction = $this->sellerListing();

        $subject = $this->resolver->resolve(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $auction->id)
        );

        $this->assertNotNull($subject);
        $this->assertSame("byo:seller_agent:{$auction->id}", $subject->subjectKey);
        $this->assertFalse($subject->isMlsSubject());
    }

    /**
     * THE collision. One property, two listings, one subject — resolved from the
     * provenance meta the quick importer already writes.
     *
     * @test
     */
    public function an_mls_linked_native_listing_resolves_to_the_same_subject_as_its_bridge_row(): void
    {
        $bridge = BridgeProperty::create([
            'provider'                => 'stellar_bridge',
            'listing_key'     => 'RESOLVER-MFR-SHARED',
            'listing_id'      => 'A3',
            'standard_status' => 'Active',
            'property_type'   => 'Residential',
        ]);

        $auction = $this->sellerListing();
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => MlsQuickImportDraftWriter::META_LISTING_KEY,
            'meta_value'              => 'RESOLVER-MFR-SHARED',
        ]);

        $fromBridge = $this->resolver->resolve(new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id));
        $fromNative = $this->resolver->resolve(new SmartTagListingRef(SmartTagListingType::SellerAgent, $auction->id));

        $this->assertNotNull($fromBridge);
        $this->assertNotNull($fromNative);
        $this->assertTrue($fromBridge->sameSubjectAs($fromNative));
        $this->assertSame('mls:stellar_bridge:RESOLVER-MFR-SHARED', $fromNative->subjectKey);

        // The acted-on listing is still recorded distinctly.
        $this->assertSame(SmartTagListingType::SellerAgent, $fromNative->listingType());
        $this->assertSame($auction->id, $fromNative->listingId());
    }

    /**
     * A results page resolves a page of listings at once; the batched form must
     * not become an N+1 against the meta tables.
     *
     * @test
     */
    public function many_refs_resolve_in_one_query_per_listing_type(): void
    {
        $bridgeA = BridgeProperty::create(['provider' => 'stellar_bridge', 'listing_key' => 'RESOLVER-BATCH-A', 'listing_id' => 'B1', 'standard_status' => 'Active', 'property_type' => 'Residential']);
        $bridgeB = BridgeProperty::create(['provider' => 'stellar_bridge', 'listing_key' => 'RESOLVER-BATCH-B', 'listing_id' => 'B2', 'standard_status' => 'Active', 'property_type' => 'Residential']);
        $noKey   = BridgeProperty::create(['provider' => 'stellar_bridge', 'listing_id' => 'B3', 'standard_status' => 'Active', 'property_type' => 'Residential']);
        $auction = $this->sellerListing();

        $refs = [
            new SmartTagListingRef(SmartTagListingType::Bridge, $bridgeA->id),
            new SmartTagListingRef(SmartTagListingType::Bridge, $bridgeB->id),
            new SmartTagListingRef(SmartTagListingType::Bridge, $noKey->id),
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $auction->id),
        ];

        \DB::enableQueryLog();
        \DB::flushQueryLog();

        $resolved = $this->resolver->resolveMany($refs);

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, count($queries), 'one query per listing type, not per listing');

        $this->assertSame('mls:stellar_bridge:RESOLVER-BATCH-A', $resolved["bridge:{$bridgeA->id}"]->subjectKey);
        $this->assertSame('mls:stellar_bridge:RESOLVER-BATCH-B', $resolved["bridge:{$bridgeB->id}"]->subjectKey);
        $this->assertSame("byo:seller_agent:{$auction->id}", $resolved["seller_agent:{$auction->id}"]->subjectKey);

        // The keyless Bridge row is omitted rather than given an invented key.
        $this->assertArrayNotHasKey("bridge:{$noKey->id}", $resolved);
    }

    /** @test */
    public function the_resolver_writes_nothing(): void
    {
        $bridge = BridgeProperty::create([
            'provider'                => 'stellar_bridge',
            'listing_key'     => 'RESOLVER-READONLY',
            'listing_id'      => 'C1',
            'standard_status' => 'Active',
            'property_type'   => 'Residential',
        ]);

        $before = $bridge->fresh()->toArray();

        $this->resolver->resolve(new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id));

        $this->assertSame($before, $bridge->fresh()->toArray());
        $this->assertSame(0, \DB::table('listing_preferences')->count());
        $this->assertSame(0, \DB::table('listing_preference_events')->count());
    }

    private function sellerListing(): SellerAgentAuction
    {
        return SellerAgentAuction::create([
            'user_id' => 900001,
            'address' => '1 Resolver Way, St. Petersburg, FL 33701',
        ]);
    }
}
