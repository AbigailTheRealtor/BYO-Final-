<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceReader;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The batch read path, and the property that makes it worth having.
 *
 * WHAT IS ACTUALLY BEING TESTED. Not that `currentMany()` returns the right
 * answers — `current()` already did that — but that the number of queries stops
 * growing when the number of cards does. A correct implementation that still
 * issued one query per listing would pass every behavioural assertion here and
 * be useless, so the counts are the test.
 *
 * COUNTS ARE UPPER BOUNDS, NOT EXACT NUMBERS. An exact assertion pins the
 * current implementation's shape — one more eager load, a different driver's
 * transaction bookkeeping, and a green test turns red for nothing. A bound that
 * is far below N is what distinguishes batched from linear, and it is the only
 * thing worth failing the build over.
 */
class ListingPreferenceBatchReadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
    }

    /**
     * The headline property: 30 listings do not cost 30× what 3 cost.
     *
     * @test
     */
    public function reading_many_current_states_does_not_scale_with_the_number_of_listings(): void
    {
        $user = $this->buyer();

        $small = $this->sellerRefs(3);
        $large = $this->sellerRefs(30);

        $reader = app(ListingPreferenceReader::class);

        $smallQueries = $this->countQueries(fn () => $reader->currentMany((int) $user->id, SeekerRole::Buyer, $small));
        $largeQueries = $this->countQueries(fn () => $reader->currentMany((int) $user->id, SeekerRole::Buyer, $large));

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            'query count must be independent of how many listings are read'
        );

        // And it is a small constant, not merely a stable large one.
        $this->assertLessThanOrEqual(
            4,
            $largeQueries,
            'a batched read of one listing type should be a handful of queries'
        );
    }

    /**
     * The same guarantee for contexts, which is the second per-card read the
     * card surfaces would otherwise make.
     *
     * @test
     */
    public function reading_many_contexts_does_not_scale_with_the_number_of_listings(): void
    {
        $reader = app(ListingPreferenceReader::class);

        $small = $this->sellerRefs(3);
        $large = $this->sellerRefs(30);

        $this->assertSame(
            $this->countQueries(fn () => $reader->contextForMany($small)),
            $this->countQueries(fn () => $reader->contextForMany($large)),
            'context resolution must be batched per listing type'
        );

        $this->assertLessThanOrEqual(2, $this->countQueries(fn () => $reader->contextForMany($large)));
    }

    /**
     * Mixed types cost one batch per TYPE, because they live in different
     * tables — not one per listing.
     *
     * @test
     */
    public function mixed_listing_types_cost_a_batch_per_type_not_per_listing(): void
    {
        $user = $this->buyer();

        $refs = array_merge($this->sellerRefs(12), $this->landlordRefs(12), $this->bridgeRefs(12));

        $queries = $this->countQueries(
            fn () => app(ListingPreferenceReader::class)->currentMany((int) $user->id, SeekerRole::Buyer, $refs)
        );

        $this->assertLessThanOrEqual(
            8,
            $queries,
            '36 listings across three types must not approach 36 queries'
        );
    }

    /**
     * The batched answer and the single answer must be the same answer. If they
     * can differ, the optimisation is a second source of truth.
     *
     * @test
     */
    public function the_batched_read_agrees_with_the_single_read(): void
    {
        $user   = $this->buyer();
        $reader = app(ListingPreferenceReader::class);
        $writer = app(ListingPreferenceWriter::class);

        $saved  = $this->sellerListing();
        $passed = $this->sellerListing();
        $silent = $this->sellerListing();

        $writer->setState((int) $user->id, SeekerRole::Buyer, $this->ref($saved), ListingPreferenceState::Save, ['updated_kitchen']);
        $writer->setState((int) $user->id, SeekerRole::Buyer, $this->ref($passed), ListingPreferenceState::Pass, []);

        $refs = [$this->ref($saved), $this->ref($passed), $this->ref($silent)];
        $many = $reader->currentMany((int) $user->id, SeekerRole::Buyer, $refs);

        foreach ($refs as $ref) {
            $this->assertSame(
                $reader->current((int) $user->id, SeekerRole::Buyer, $ref),
                $many["{$ref->type->value}:{$ref->id}"],
                'batched and single reads must agree for every listing'
            );
        }

        $this->assertSame('save', $many["seller_agent:{$saved->id}"]['state']);
        $this->assertSame(['updated_kitchen'], $many["seller_agent:{$saved->id}"]['reasons']);
        $this->assertSame('pass', $many["seller_agent:{$passed->id}"]['state']);
        $this->assertNull($many["seller_agent:{$silent->id}"]['state']);
    }

    /**
     * Every requested ref comes back, including ones with nothing stored and
     * ones with no durable subject. A missing key would push the caller into
     * per-card reads to find out which.
     *
     * @test
     */
    public function every_requested_ref_is_present_in_the_result(): void
    {
        $user = $this->buyer();

        // A Bridge row with NO listing key has no durable identity at all.
        $keyless = BridgeProperty::create([
            'provider'      => 'stellar_bridge',
            'listing_key'   => null,
            'property_type' => 'Residential',
        ]);

        $refs = [
            $this->ref($this->sellerListing()),
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $keyless->id),
        ];

        $many = app(ListingPreferenceReader::class)->currentMany((int) $user->id, SeekerRole::Buyer, $refs);

        $this->assertCount(2, $many);
        foreach ($refs as $ref) {
            $this->assertArrayHasKey("{$ref->type->value}:{$ref->id}", $many);
            $this->assertSame(['state' => null, 'reasons' => []], $many["{$ref->type->value}:{$ref->id}"]);
        }
    }

    /**
     * ONE PROPERTY, TWO SURFACES, ONE ANSWER.
     *
     * A Bridge row and the BidYourOffer listing imported from it are the same
     * house. A Pass expressed on the map must be the same Pass in results, and
     * the batched read is where a page would otherwise learn otherwise.
     *
     * @test
     */
    public function a_bridge_row_and_its_byo_listing_report_the_same_state(): void
    {
        $user       = $this->buyer();
        $listingKey = 'STELLAR-BATCH-0001';

        $bridge = BridgeProperty::create([
            'provider'      => 'stellar_bridge',
            'listing_key'   => $listingKey,
            'property_type' => 'Residential',
        ]);

        $byo = $this->sellerListing();
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $byo->id,
            'meta_key'                => MlsQuickImportDraftWriter::META_LISTING_KEY,
            'meta_value'              => $listingKey,
        ]);

        $bridgeRef = new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id);
        $byoRef    = $this->ref($byo);

        // Expressed once, on the BYO listing.
        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            $byoRef,
            ListingPreferenceState::Pass,
            [],
        );

        $many = app(ListingPreferenceReader::class)
            ->currentMany((int) $user->id, SeekerRole::Buyer, [$bridgeRef, $byoRef]);

        $this->assertSame('pass', $many["bridge:{$bridge->id}"]['state']);
        $this->assertSame('pass', $many["seller_agent:{$byo->id}"]['state']);
    }

    /**
     * Role separation survives batching: a tenant's answers are not a buyer's.
     *
     * @test
     */
    public function the_batched_read_is_scoped_to_the_seeker_role(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing();
        $ref     = $this->ref($listing);

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            $ref,
            ListingPreferenceState::Save,
            [],
        );

        $reader = app(ListingPreferenceReader::class);

        $this->assertSame('save', $reader->currentMany((int) $user->id, SeekerRole::Buyer, [$ref])["seller_agent:{$listing->id}"]['state']);
        $this->assertNull($reader->currentMany((int) $user->id, SeekerRole::Tenant, [$ref])["seller_agent:{$listing->id}"]['state']);
    }

    /** One customer's preferences are never another's. @test */
    public function the_batched_read_is_scoped_to_the_viewer(): void
    {
        $mine    = $this->buyer();
        $theirs  = $this->buyer();
        $listing = $this->sellerListing();
        $ref     = $this->ref($listing);

        app(ListingPreferenceWriter::class)->setState(
            (int) $mine->id,
            SeekerRole::Buyer,
            $ref,
            ListingPreferenceState::Save,
            [],
        );

        $reader = app(ListingPreferenceReader::class);

        $this->assertSame('save', $reader->currentMany((int) $mine->id, SeekerRole::Buyer, [$ref])["seller_agent:{$listing->id}"]['state']);
        $this->assertNull($reader->currentMany((int) $theirs->id, SeekerRole::Buyer, [$ref])["seller_agent:{$listing->id}"]['state']);
    }

    /**
     * The batched context resolution must agree with the single one, including
     * on the unresolvable case — where null means "could not answer", not a
     * default.
     *
     * @test
     */
    public function batched_contexts_agree_with_single_contexts_including_unresolvable_ones(): void
    {
        $reader = app(ListingPreferenceReader::class);

        $house = $this->sellerListing('Residential');
        $land  = $this->sellerListing('Vacant Land');
        $junk  = $this->sellerListing('Not A Real Property Type');

        $refs = [$this->ref($house), $this->ref($land), $this->ref($junk)];
        $many = $reader->contextForMany($refs);

        $this->assertSame(SmartTagContext::ResidentialSale, $many["seller_agent:{$house->id}"]);
        $this->assertSame(SmartTagContext::LandSale, $many["seller_agent:{$land->id}"]);
        $this->assertNull($many["seller_agent:{$junk->id}"]);

        foreach ($refs as $ref) {
            $this->assertSame(
                $reader->contextFor($ref),
                $many["{$ref->type->value}:{$ref->id}"],
                'batched and single context resolution must agree'
            );
        }

        // Unresolvable is PRESENT-AND-NULL, never missing.
        $this->assertArrayHasKey("seller_agent:{$junk->id}", $many);
    }

    /** An empty request costs nothing at all. @test */
    public function an_empty_batch_issues_no_queries(): void
    {
        $reader = app(ListingPreferenceReader::class);

        $this->assertSame(0, $this->countQueries(fn () => $reader->currentMany(1, SeekerRole::Buyer, [])));
        $this->assertSame(0, $this->countQueries(fn () => $reader->contextForMany([])));
    }

    // ---------------------------------------------------------------- helpers

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $work();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function ref(SellerAgentAuction $listing): SmartTagListingRef
    {
        return new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id);
    }

    /** @return list<SmartTagListingRef> */
    private function sellerRefs(int $count): array
    {
        $refs = [];
        for ($i = 0; $i < $count; $i++) {
            $refs[] = $this->ref($this->sellerListing());
        }

        return $refs;
    }

    /** @return list<SmartTagListingRef> */
    private function landlordRefs(int $count): array
    {
        $refs = [];
        for ($i = 0; $i < $count; $i++) {
            $listing = LandlordAgentAuction::create(['user_id' => 900420]);
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $listing->id,
                'meta_key'                  => 'property_type',
                'meta_value'                => 'Residential Property',
            ]);
            $refs[] = new SmartTagListingRef(SmartTagListingType::LandlordAgent, (int) $listing->id);
        }

        return $refs;
    }

    /** @return list<SmartTagListingRef> */
    private function bridgeRefs(int $count): array
    {
        $refs = [];
        for ($i = 0; $i < $count; $i++) {
            $row = BridgeProperty::create([
                'provider'      => 'stellar_bridge',
                'listing_key'   => 'STELLAR-BATCH-' . uniqid('', true),
                'property_type' => 'Residential',
            ]);
            $refs[] = new SmartTagListingRef(SmartTagListingType::Bridge, (int) $row->id);
        }

        return $refs;
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function sellerListing(string $propertyType = 'Residential'): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => 900400,
            'address' => '77 Batch Road, St. Petersburg, FL 33701',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_type',
            'meta_value'              => $propertyType,
        ]);

        return $auction;
    }
}
