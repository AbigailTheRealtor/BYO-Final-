<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\ListingImport\Sync\MlsListingSyncService;
use App\Services\ListingImport\Sync\MlsSyncOutcome;
use App\Support\Listing\ListingPhotoEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MLS LIVE SYNC.
 *
 * An MLS-linked listing must not sit stale after its source changes, and it must
 * not lose anything its owner wrote in the process. Those two sentences pull in
 * opposite directions, so every test here is on one side or the other of that
 * line.
 *
 * NO LIVE BRIDGE REQUEST IS MADE. Every source response is an Http::fake over
 * the real BridgeApiService, so these exercise the actual OData call path,
 * normalizer, adapter and allow-lists rather than a stubbed service.
 */
class MlsListingLiveSyncTest extends TestCase
{
    use RefreshDatabase;

    private const KEY   = 'SYNC-KEY-1';
    private const MLS   = 'SYNC-MLS-1';
    private const T0    = '2026-09-01T10:00:00.000Z';
    private const T1    = '2026-09-08T10:00:00.000Z';

    /**
     * What the faked Bridge endpoint will answer with, next time it is asked.
     *
     * ONE Http::fake() is registered, in setUp, and it delegates here.
     *
     * That indirection is not tidiness — it is the difference between these
     * tests working and silently passing. `Http::fake()` MERGES stubs rather
     * than replacing them, and the FIRST matching stub answers. So calling
     * `Http::fake(['*' => …])` a second time inside a test does nothing: the
     * original stub keeps answering, and a test that changes the source and then
     * asserts the listing followed it is really asserting that the listing
     * ignored a change that never arrived. Two tests here failed exactly that
     * way before this was a single mutable responder.
     *
     * @var \Closure(): \GuzzleHttp\Promise\PromiseInterface
     */
    private $sourceResponder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceResponder = fn () => Http::response(['value' => []], 200);

        Http::fake(fn () => ($this->sourceResponder)());

        config([
            'bridge.dataset'                         => 'test-dataset',
            'bridge.token'                           => 'test-token',
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'mls_media.roles'                        => ['seller', 'landlord'],
            'mls_sync.enabled'                       => true,
            'mls_sync.roles'                         => ['seller', 'landlord'],
            // Freshness is exercised by its own test; elsewhere it must not mask
            // the behaviour under examination.
            'mls_sync.freshness_minutes'             => 0,
            'mls_sync.retry_after_minutes'           => 0,
            // The terminal window is the same dial for off-market listings, and
            // it must be flattened here too: several tests below sync a listing
            // to Closed/Expired and then sync it again, and a 24-hour default
            // would answer FRESH and quietly assert nothing.
            'mls_sync.terminal_freshness_minutes'    => 0,
            // Stale-on-access and the schedule are exercised by their own files.
            'mls_sync.lazy_refresh_enabled'          => false,
        ]);
    }

    // =====================================================================
    // Harness
    // =====================================================================

    private function raw(array $overrides = [], int $photos = 3): array
    {
        $raw = array_merge([
            'ListingKey'                     => self::KEY,
            'ListingId'                      => self::MLS,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'UnparsedAddress'                => '6817 Stones Throw Circle N',
            'City'                           => 'ST PETERSBURG',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33710',
            'ListPrice'                      => 184900,
            'BedroomsTotal'                  => 2,
            'BathroomsTotalInteger'          => 2,
            'LivingArea'                     => 1100,
            'YearBuilt'                      => 1974,
            'SubdivisionName'                => 'Stones Throw',
            'ModificationTimestamp'          => self::T0,
            'StatusChangeTimestamp'          => self::T0,
            'PriceChangeTimestamp'           => self::T0,
            'PhotosChangeTimestamp'          => self::T0,
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $overrides);

        $media = [];
        for ($i = 1; $i <= $photos; $i++) {
            $media[] = [
                'MediaKey'      => self::KEY . "-m{$i}",
                'MediaURL'      => "https://cdn.example.com/" . self::KEY . "-{$i}.jpg",
                'Order'         => $i,
                'MediaCategory' => 'Photo',
                'Permission'    => ['Public'],
            ];
        }

        $raw['Media'] = $media;

        return $raw;
    }

    /** Seed the local cache so the IMPORT (local-first) can find the record. */
    private function seedCache(array $raw): BridgeProperty
    {
        BridgeProperty::where('listing_key', $raw['ListingKey'])->delete();

        return BridgeProperty::create([
            'listing_key'           => $raw['ListingKey'],
            'listing_id'            => $raw['ListingId'],
            'standard_status'       => $raw['StandardStatus'],
            'mls_status'            => $raw['MlsStatus'] ?? null,
            'property_type'         => $raw['PropertyType'],
            'unparsed_address'      => $raw['UnparsedAddress'],
            'city'                  => $raw['City'],
            'state_or_province'     => $raw['StateOrProvince'],
            'postal_code'           => $raw['PostalCode'],
            'list_price'            => $raw['ListPrice'],
            'bedrooms_total'        => $raw['BedroomsTotal'] ?? null,
            'bathrooms_total_integer' => $raw['BathroomsTotalInteger'] ?? null,
            'living_area'           => $raw['LivingArea'] ?? null,
            'year_built'            => $raw['YearBuilt'] ?? null,
            'modification_timestamp' => $raw['ModificationTimestamp'] ?? null,
            'raw_json'              => json_encode($raw),
            'imported_at'           => now(),
        ]);
    }

    /** What the SOURCE will answer with from here on. */
    private function sourceReturns(array $raw): void
    {
        $this->sourceResponder = fn () => Http::response(['value' => [$raw]], 200);
    }

    /** The source answers, and has no such record. */
    private function sourceHasNoRecord(): void
    {
        $this->sourceResponder = fn () => Http::response(['value' => []], 200);
    }

    /** The source cannot be reached — a transport or server fault, not an answer. */
    private function sourceFailsWith(int $status): void
    {
        $this->sourceResponder = fn () => Http::response('', $status);
    }

    private function sourceTimesOut(): void
    {
        $this->sourceResponder = function (): never {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        };
    }

    private function import(User $user, string $role = 'seller'): object
    {
        $result = app(MlsQuickImportService::class)->lookup(self::MLS, $role);

        $this->assertTrue($result->isFound(), 'Fixture import did not find the seeded record');

        return app(Meta::class)->materialise($role, $user->id, $result);
    }

    private function sync(object $listing, string $role = 'seller', bool $force = false): MlsSyncOutcome
    {
        return app(MlsListingSyncService::class)->sync($listing, $role, $force);
    }

    private function metaOf(object $listing): array
    {
        return $listing->fresh()->get->toArray();
    }

    // =====================================================================
    // 1. Identity
    // =====================================================================

    /** @test */
    public function an_imported_listing_stays_bound_to_its_stable_mls_source_id(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $meta = $this->metaOf($listing);

        $this->assertSame(self::KEY, $meta[Meta::META_LISTING_KEY]);
        $this->assertSame(self::MLS, $meta[Meta::META_MLS_NUMBER]);

        // And the sync resolves the source by that key, not by address.
        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->assertTrue($this->sync($listing)->isSynced());

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "ListingKey eq '" . self::KEY . "'"));
    }

    // =====================================================================
    // 2-6. MLS facts follow the feed
    // =====================================================================

    /** @test */
    public function a_newer_mls_price_updates_the_listing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->assertSame('184900', (string) $this->metaOf($listing)['maximum_budget']);

        $this->sourceReturns($this->raw([
            'ListPrice'             => 179900,
            'ModificationTimestamp' => self::T1,
        ]));

        $outcome = $this->sync($listing);
        $meta    = $this->metaOf($listing);

        $this->assertTrue($outcome->isSynced());
        $this->assertSame('179900', (string) $meta[Meta::META_LIST_PRICE], 'Authoritative MLS price did not follow the feed');
        $this->assertSame('179900', (string) $meta['maximum_budget'], 'Mapped price field did not follow the feed');
    }

    /** @test */
    public function newer_property_facts_update_the_listing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw([
            'BedroomsTotal'          => 4,
            'BathroomsTotalInteger'  => 3,
            'LivingArea'             => 1450,
            'YearBuilt'              => 1975,
            'ModificationTimestamp'  => self::T1,
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $meta = $this->metaOf($listing);

        $this->assertSame('4', (string) $meta['bedrooms']);
        $this->assertSame('3', (string) $meta['bathrooms']);
        $this->assertSame('1450', (string) $meta['minimum_heated_square']);
        $this->assertSame('1975', (string) $meta['year_built']);
    }

    /** @test */
    public function a_user_edited_mls_fact_is_still_brought_back_into_line_and_the_old_value_is_recoverable(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        // The owner corrects a fact by hand.
        $listing->saveMeta('bedrooms', '9');

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $outcome = $this->sync($listing);

        $meta = $this->metaOf($listing);

        // Stellar is authoritative for facts, by owner decision.
        $this->assertSame('2', (string) $meta['bedrooms']);
        $this->assertContains('bedrooms', $outcome->changedKeys);

        // But the overwrite is never unrecoverable.
        $journal = $meta[Meta::META_SYNC_JOURNAL] ?? [];
        $this->assertNotEmpty($journal, 'No rollback journal was written for an overwritten value');
        $this->assertSame('9', (string) end($journal)['previous']['bedrooms']);
    }

    /** @test */
    public function the_supplemental_mls_payload_and_its_agent_attribution_refresh_wholesale(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $before = $this->metaOf($listing)[Meta::META_PROPERTY_DETAILS] ?? [];
        $this->assertNotEmpty($before, 'Import produced no supplemental payload to refresh');

        $this->sourceReturns($this->raw([
            'SubdivisionName'       => 'Renamed Subdivision',
            'ModificationTimestamp' => self::T1,
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $after = json_encode($this->metaOf($listing)[Meta::META_PROPERTY_DETAILS] ?? []);

        $this->assertStringContainsString('Renamed Subdivision', $after);
        $this->assertStringNotContainsString('Stones Throw', $after, 'A retracted MLS fact survived the refresh');
    }

    /** @test */
    public function an_mls_status_change_is_stored_verbatim_in_both_status_fields(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw([
            'StandardStatus'         => 'Closed',
            'MlsStatus'              => 'Sold',
            'ModificationTimestamp'  => self::T1,
            'StatusChangeTimestamp'  => self::T1,
        ]));

        $outcome = $this->sync($listing);
        $meta    = $this->metaOf($listing);

        // The two vocabularies disagree on real records and are kept apart.
        $this->assertSame('Closed', $meta[Meta::META_STANDARD_STATUS]);
        $this->assertSame('Sold', $meta[Meta::META_SOURCE_STATUS]);
        $this->assertSame('Closed', $outcome->sourceStatus);
        $this->assertFalse($outcome->statusUnrecognised);
    }

    /** @test */
    public function an_mls_linked_listing_reports_the_stellar_status_and_ignores_the_byo_expiration_timer(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        // A BYO expiration date that has already passed, and a manually chosen
        // status. Neither may override Stellar on an MLS-linked listing.
        $listing->saveMeta('expiration_date', now()->subYear()->toDateString());
        $listing->saveMeta('listing_status', 'Pending');

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->sync($listing);

        $this->assertSame('Active', $listing->fresh()->status, 'A BYO timer overrode the Stellar status');
    }

    /** @test */
    public function an_mls_linked_listing_reflects_stellar_expiry_without_losing_the_record(): void
    {
        $this->seedCache($this->raw());
        $user    = User::factory()->create();
        $listing = $this->import($user);

        $this->sourceReturns($this->raw([
            'StandardStatus'        => 'Expired',
            'MlsStatus'             => 'Expired',
            'ModificationTimestamp' => self::T1,
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $fresh = $listing->fresh();

        $this->assertSame('Expired', $fresh->status);
        // Nothing is destroyed by an expiry.
        $this->assertNotNull(SellerAgentAuction::find($listing->id));
        $this->assertNotEmpty($this->metaOf($listing)['property_photos'] ?? []);
    }

    /** @test */
    public function an_unrecognised_status_is_preserved_verbatim_and_flagged_rather_than_dropped(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw([
            'StandardStatus'        => 'Zorbulated',
            'MlsStatus'             => 'Zorbulated',
            'ModificationTimestamp' => self::T1,
        ]));

        $outcome = $this->sync($listing);
        $meta    = $this->metaOf($listing);

        $this->assertTrue($outcome->statusUnrecognised);
        $this->assertSame('Zorbulated', $meta[Meta::META_STANDARD_STATUS], 'An unknown status was not preserved verbatim');
        $this->assertSame('1', (string) $meta[Meta::META_STATUS_UNRECOGNISED]);
        // It must NOT silently fall back to the BYO status.
        $this->assertSame('Zorbulated', $listing->fresh()->status);
    }

    // =====================================================================
    // 7-8. Change detection
    // =====================================================================

    /** @test */
    public function an_unchanged_modification_timestamp_mutates_nothing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->sync($listing);

        $before = $this->metaOf($listing);

        // Same record, same timestamp, a changed price that must NOT be applied:
        // an unchanged ModificationTimestamp means the feed says nothing moved.
        $this->sourceReturns($this->raw([
            'ListPrice'             => 999999,
            'ModificationTimestamp' => self::T1,
        ]));

        $outcome = $this->sync($listing);
        $after   = $this->metaOf($listing);

        $this->assertSame(MlsSyncOutcome::UNCHANGED, $outcome->status);
        $this->assertTrue($outcome->wroteNothing());
        $this->assertSame($before['maximum_budget'], $after['maximum_budget']);
        $this->assertSame($before[Meta::META_LIST_PRICE] ?? null, $after[Meta::META_LIST_PRICE] ?? null);
    }

    /** @test */
    public function a_newer_modification_timestamp_triggers_a_sync(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T0]));
        $this->sync($listing);

        $this->sourceReturns($this->raw([
            'ListPrice'             => 175000,
            'ModificationTimestamp' => self::T1,
        ]));

        $outcome = $this->sync($listing);

        $this->assertSame(MlsSyncOutcome::SYNCED, $outcome->status);

        // Compared as an INSTANT, not as a string. The marker round-trips
        // through `bridge_properties.modification_timestamp`, a timestamp
        // column, so it comes back as '2026-09-08 10:00:00' rather than as the
        // ISO-8601 the feed sent. That is also why MlsListingSyncService
        // compares instants: a string comparison would report a change every
        // time the value crossed the database, and every sync would rewrite
        // every field forever.
        $this->assertTrue(
            \Carbon\CarbonImmutable::parse(self::T1)
                ->equalTo(\Carbon\CarbonImmutable::parse($this->metaOf($listing)[Meta::META_SOURCE_MODIFIED_AT])),
            'The stored change marker does not represent the source instant'
        );
    }

    // =====================================================================
    // 9-13. BYO data is never overwritten
    // =====================================================================

    /**
     * @test
     *
     * The central protection. Every one of these is a value the owner authored,
     * and a sync that "wins" on any of them has broken the contract.
     */
    public function byo_terms_listing_method_bidding_config_and_conditional_answers_all_survive(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $owned = [
            'listing_method'          => 'Full Service',
            'service_type'            => 'Full Service',
            'auction_type'            => 'Bidding Period',
            'auction_time'            => '7 Days',
            'contract_terms'          => 'Seller pays no concessions.',
            'important_info'          => 'Cat on the premises.',
            'seller_motivation'       => 'Relocating',
            'price_firmness'          => 'Firm',
            'offered_financing'       => ['Conventional', 'Cash'],
            'starting_price'          => '150000',
            'reserve_price'           => '170000',
            'buy_now_price'           => '200000',
            'bidding_starts_at'       => '2026-09-01 00:00:00',
            'bidding_ends_at'         => '2026-09-08 00:00:00',
            'expiration_date'         => '2026-12-31',
            'offer_requirements'      => 'Proof of funds required.',
            'showing_instructions'    => 'Call first.',
            'listing_status'          => 'Pending',
        ];

        foreach ($owned as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        $this->sourceReturns($this->raw([
            'ListPrice'             => 179900,
            'BedroomsTotal'         => 5,
            'StandardStatus'        => 'Pending',
            'ModificationTimestamp' => self::T1,
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $after = $this->metaOf($listing);

        foreach ($owned as $key => $value) {
            $this->assertSame(
                $value,
                $after[$key] ?? null,
                "MLS sync overwrote the BidYourOffer-owned key '{$key}'"
            );
        }
    }

    /** @test */
    public function bids_offers_and_history_are_untouched_by_a_sync(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $bidder = User::factory()->create();
        $bid = new \App\Models\SellerAgentAuctionBid();
        $bid->seller_agent_auction_id = $listing->id;
        $bid->user_id                 = $bidder->id;
        $bid->save();

        $this->sourceReturns($this->raw([
            'StandardStatus'        => 'Closed',
            'ModificationTimestamp' => self::T1,
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $this->assertNotNull(
            \App\Models\SellerAgentAuctionBid::find($bid->id),
            'A sync removed a bid'
        );
        $this->assertSame(
            1,
            \App\Models\SellerAgentAuctionBid::where('seller_agent_auction_id', $listing->id)->count()
        );
    }

    // =====================================================================
    // 14-15. Failure behaviour
    // =====================================================================

    /** @test */
    public function a_source_failure_keeps_the_last_known_good_values(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->sync($listing);

        $before = $this->metaOf($listing);

        $this->sourceFailsWith(500);

        $outcome = $this->sync($listing);
        $after   = $this->metaOf($listing);

        $this->assertSame(MlsSyncOutcome::UNAVAILABLE, $outcome->status);
        $this->assertFalse($outcome->isConclusive());

        foreach (['maximum_budget', 'bedrooms', Meta::META_LIST_PRICE, Meta::META_STANDARD_STATUS] as $key) {
            $this->assertSame($before[$key] ?? null, $after[$key] ?? null, "Key '{$key}' was lost on a provider failure");
        }

        $this->assertNotEmpty($after['property_photos'] ?? [], 'Photographs were lost on a provider failure');
        $this->assertSame(MlsSyncOutcome::UNAVAILABLE, $after[Meta::META_SYNC_ERROR]);
    }

    /** @test */
    public function an_unavailable_source_is_never_treated_as_a_deletion(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceTimesOut();

        $outcome = $this->sync($listing);

        $this->assertSame(MlsSyncOutcome::UNAVAILABLE, $outcome->status);
        $this->assertNotNull(SellerAgentAuction::find($listing->id));
        $this->assertNotEmpty($this->metaOf($listing)['property_photos'] ?? []);
        // "Gone from the feed" is a DIFFERENT outcome, and this is not it.
        $this->assertNotSame(MlsSyncOutcome::NOT_FOUND, $outcome->status);
    }

    /** @test */
    public function a_record_absent_from_the_feed_leaves_the_listing_standing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceHasNoRecord();

        $outcome = $this->sync($listing);

        $this->assertSame(MlsSyncOutcome::NOT_FOUND, $outcome->status);
        $this->assertNotNull(SellerAgentAuction::find($listing->id));
        $this->assertNotEmpty($this->metaOf($listing)['property_photos'] ?? []);
    }

    // =====================================================================
    // 16. Idempotency
    // =====================================================================

    /** @test */
    public function syncing_twice_against_the_same_source_changes_nothing_the_second_time(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw([
            'ListPrice'             => 179900,
            'BedroomsTotal'         => 4,
            'ModificationTimestamp' => self::T1,
        ]));

        $first = $this->sync($listing);
        $snapshot = $this->metaOf($listing);

        // Forced, so neither the freshness window nor the unchanged-source
        // short-circuit can be what makes this pass.
        $second = $this->sync($listing, 'seller', true);
        $after  = $this->metaOf($listing);

        $this->assertTrue($first->isSynced());
        $this->assertTrue($second->isSynced());
        $this->assertSame([], $second->changedKeys, 'A repeated forced sync reported changes');

        foreach (['maximum_budget', 'bedrooms', Meta::META_LIST_PRICE, 'property_photos'] as $key) {
            $this->assertEquals($snapshot[$key] ?? null, $after[$key] ?? null, "Key '{$key}' churned on a repeat sync");
        }

        $this->assertCount(
            count(ListingPhotoEntry::collection($snapshot['property_photos'] ?? null)),
            ListingPhotoEntry::collection($after['property_photos'] ?? null),
            'Photographs were duplicated by a repeat sync'
        );
    }

    // =====================================================================
    // 17. Negative control — a manual listing is never refreshed
    // =====================================================================

    /** @test */
    public function a_manual_non_mls_listing_is_never_refreshed_from_bridge(): void
    {
        $this->sourceReturns($this->raw());

        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'  => $user->id,
            'address'  => '1 Manual Street',
            'title'    => 'Manual listing',
            'is_draft' => false,
        ]);

        $listing->saveMeta('maximum_budget', '123456');
        $listing->saveMeta('expiration_date', now()->subYear()->toDateString());

        $outcome = $this->sync($listing);

        $this->assertSame(MlsSyncOutcome::NOT_MLS_LINKED, $outcome->status);
        Http::assertNothingSent();

        // And its own lifecycle logic is untouched: the BYO expiration still
        // expires a listing that owns its own lifecycle.
        $this->assertSame('123456', (string) $this->metaOf($listing)['maximum_budget']);
        $this->assertSame('Expired', $listing->fresh()->status);
    }

    // =====================================================================
    // 18. Media ownership
    // =====================================================================

    /** @test */
    public function a_media_refresh_never_removes_a_user_upload(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $stored     = ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);
        $withUser   = ListingPhotoEntry::toStorageCollection($stored);
        $withUser[] = 'auction/images/my-own-photo.jpg';

        $listing->saveMeta('property_photos', $withUser);

        // The feed replaces its whole photo set.
        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1], photos: 5));

        $outcome = $this->sync($listing);
        $after   = ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);

        $userEntries = array_values(array_filter($after, fn (ListingPhotoEntry $e) => ! $e->isMls()));
        $mlsEntries  = array_values(array_filter($after, fn (ListingPhotoEntry $e) => $e->isMls()));

        $this->assertCount(1, $userEntries, "The owner's own upload was removed by an MLS sync");
        $this->assertCount(5, $mlsEntries, 'The MLS photo set did not follow the feed');
        $this->assertSame(1, $outcome->userPhotosPreserved);
    }

    /** @test */
    public function a_source_response_carrying_no_media_empties_nothing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $before = ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);
        $this->assertNotEmpty($before);

        $raw = $this->raw(['ModificationTimestamp' => self::T1]);
        unset($raw['Media']);
        $this->sourceReturns($raw);

        $this->sync($listing);

        $after = ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);

        $this->assertCount(count($before), $after, 'A thin media response emptied a live gallery');
    }

    // =====================================================================
    // 19-20. Freshness, locking and role scope
    // =====================================================================

    /** @test */
    public function a_listing_inside_the_freshness_window_is_not_fetched_at_all(): void
    {
        config(['mls_sync.freshness_minutes' => 360]);

        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->assertTrue($this->sync($listing)->isSynced());

        $sentAfterFirstSync = Http::recorded()->count();

        $outcome = $this->sync($listing);

        $this->assertSame(MlsSyncOutcome::FRESH, $outcome->status);
        Http::assertSentCount($sentAfterFirstSync);
    }

    /** @test */
    public function a_landlord_lease_listing_syncs_its_rent_but_a_sale_record_never_becomes_a_rent(): void
    {
        // A LEASE record: the rent may be synced.
        $lease = $this->raw([
            'PropertyType'          => 'Residential Lease',
            'ListPrice'             => 2400,
            'ModificationTimestamp' => self::T0,
        ]);

        $this->seedCache($lease);
        $listing = $this->import(User::factory()->create(), 'landlord');

        $this->sourceReturns(array_merge($lease, [
            'ListPrice'             => 2500,
            'ModificationTimestamp' => self::T1,
        ]));

        $this->assertTrue($this->sync($listing, 'landlord')->isSynced());
        $this->assertSame('2500', (string) $this->metaOf($listing)['desired_rental_amount']);

        // A SALE record on a landlord listing: the sale price must never land in
        // the rent field, and must not be stored as the authoritative rent.
        $this->sourceReturns(array_merge($lease, [
            'PropertyType'          => 'Residential',
            'ListPrice'             => 450000,
            'ModificationTimestamp' => '2026-09-09T10:00:00.000Z',
        ]));

        $this->sync($listing, 'landlord');

        $meta = $this->metaOf($listing);

        $this->assertSame('2500', (string) $meta['desired_rental_amount'], 'A sale price was written into the rent field');
        $this->assertNotSame('450000', (string) ($meta[Meta::META_LIST_PRICE] ?? ''));
    }

    // =====================================================================
    // The console backstop
    // =====================================================================

    /** @test */
    public function the_backstop_command_reconciles_an_mls_linked_listing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw([
            'ListPrice'             => 171500,
            'ModificationTimestamp' => self::T1,
        ]));

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])
            ->assertExitCode(0);

        $this->assertSame('171500', (string) $this->metaOf($listing)[Meta::META_LIST_PRICE]);
    }

    /** @test */
    public function the_backstop_command_in_dry_run_mode_sends_nothing_and_writes_nothing(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $before = $this->metaOf($listing);
        $sent   = Http::recorded()->count();

        $this->sourceReturns($this->raw([
            'ListPrice'             => 1,
            'ModificationTimestamp' => self::T1,
        ]));

        $this->artisan('mls:sync-listings', ['--role' => 'seller', '--dry-run' => true])
            ->assertExitCode(0);

        Http::assertSentCount($sent);
        $this->assertEquals($before, $this->metaOf($listing), 'A dry run changed the listing');
    }

    /** @test */
    public function the_backstop_command_skips_a_manual_listing(): void
    {
        $this->sourceReturns($this->raw());

        $user = User::factory()->create();
        SellerAgentAuction::create([
            'user_id'  => $user->id,
            'address'  => '1 Manual Street',
            'title'    => 'Manual listing',
            'is_draft' => false,
        ]);

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertExitCode(0);

        // A listing with no MLS identifier is not even a candidate, so nothing
        // was asked of Bridge on its behalf.
        Http::assertNothingSent();
    }

    /** @test */
    public function the_master_gate_stops_every_sync(): void
    {
        config(['mls_sync.enabled' => false]);

        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw());

        $this->assertSame(MlsSyncOutcome::DISABLED, $this->sync($listing)->status);
        Http::assertNothingSent();
    }
    // =====================================================================
    // 14. Status transitions, one by one
    // =====================================================================

    /**
     * Sync the listing to a given source status and return what it now reports.
     *
     * Each step advances ModificationTimestamp, because a source whose stamp has
     * not moved is correctly treated as unchanged and would make the transition
     * a no-op that still passed.
     */
    private function transitionTo(object $listing, string $standard, string $mls, string $at): string
    {
        $this->sourceReturns($this->raw([
            'StandardStatus'         => $standard,
            'MlsStatus'              => $mls,
            'ModificationTimestamp'  => $at,
            'StatusChangeTimestamp'  => $at,
        ]));

        $this->assertTrue($this->sync($listing)->isSynced(), "Transition to {$standard} did not sync");

        return (string) $listing->fresh()->status;
    }

    /**
     * @test
     * @dataProvider statusTransitions
     *
     * Every transition the owner's contract names, in both directions where
     * both directions are real. The listing's reported status must be the
     * Stellar string, verbatim, at every step — never a BidYourOffer word.
     */
    public function an_mls_linked_listing_follows_each_stellar_status_transition(
        string $fromStandard,
        string $fromMls,
        string $toStandard,
        string $toMls,
    ): void {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->assertSame(
            $fromStandard,
            $this->transitionTo($listing, $fromStandard, $fromMls, '2026-09-02T10:00:00.000Z'),
        );

        $this->assertSame(
            $toStandard,
            $this->transitionTo($listing, $toStandard, $toMls, '2026-09-03T10:00:00.000Z'),
        );

        // And the two vocabularies stay apart at the destination.
        $meta = $this->metaOf($listing);
        $this->assertSame($toStandard, $meta[Meta::META_STANDARD_STATUS]);
        $this->assertSame($toMls, $meta[Meta::META_SOURCE_STATUS]);
    }

    public static function statusTransitions(): array
    {
        return [
            'Active → Pending'                      => ['Active', 'Active', 'Pending', 'Pending'],
            'Pending → Active'                      => ['Pending', 'Pending', 'Active', 'Active'],
            'Active → Closed'                       => ['Active', 'Active', 'Closed', 'Sold'],
            'Pending → Closed'                      => ['Pending', 'Pending', 'Closed', 'Sold'],
            'Active → Active Under Contract'        => ['Active', 'Active', 'Active Under Contract', 'Pending'],
            'Active Under Contract → Active'        => ['Active Under Contract', 'Pending', 'Active', 'Active'],
            'Coming Soon → Active'                  => ['Coming Soon', 'Coming Soon', 'Active', 'Active'],
        ];
    }

    /**
     * @test
     * @dataProvider ownerDeclaredStatuses
     *
     * The five statuses the owner's contract names that the 2026-09-10 probe
     * could NOT confirm in this dataset. They are handled — stored verbatim,
     * reported verbatim, not flagged as unknown, and the listing survives — but
     * nothing here should be read as evidence the feed emits them.
     */
    public function an_owner_declared_status_is_handled_without_being_claimed_as_probe_confirmed(string $status): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $reported = $this->transitionTo($listing, $status, $status, '2026-09-04T10:00:00.000Z');

        $this->assertSame($status, $reported);
        $this->assertNull($this->metaOf($listing)[Meta::META_STATUS_UNRECOGNISED] ?? null);

        // Recognised, but explicitly NOT among the strings a live request
        // actually returned.
        $this->assertTrue(\App\Support\Listing\MlsSourceStatus::isRecognised($status));
        $this->assertFalse(\App\Support\Listing\MlsSourceStatus::isProbeConfirmed($status));

        // And the record is still there. A status is never a deletion.
        $this->assertNotNull($listing->fresh());
        $this->assertSame(self::KEY, $this->metaOf($listing)[Meta::META_LISTING_KEY]);
    }

    public static function ownerDeclaredStatuses(): array
    {
        return [['Expired'], ['Withdrawn'], ['Canceled'], ['Cancelled'], ['Temporarily Off Market']];
    }

    /**
     * @test
     *
     * The other half of the expiration rule: a MANUAL listing's own expiration
     * date must keep working exactly as it did. The MLS rule is an override for
     * MLS-linked listings, not a change to the platform's own lifecycle.
     */
    public function a_manual_listing_still_expires_on_its_own_byo_date(): void
    {
        $manual = SellerAgentAuction::create([
            'user_id'  => User::factory()->create()->id,
            'address'  => '2 Manual Way',
            'is_draft' => false,
        ]);

        $manual->saveMeta('expiration_date', now()->subYear()->toDateString());

        $this->assertSame('Expired', $manual->fresh()->status);
        Http::assertNothingSent();
    }

    // =====================================================================
    // 15. Property, association, tax and attribution facts
    // =====================================================================

    /**
     * @test
     *
     * The owner's contract makes Stellar authoritative for far more than price
     * and status. This moves one fact from each family named in it and proves
     * the listing followed — while the BYO fields beside them stay byte-identical.
     */
    public function association_tax_and_feature_facts_all_follow_the_feed(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        // The seller's own terms, written before the sync.
        $listing->saveMeta('desired_sale_price', '180000');
        $listing->saveMeta('contract_terms', 'Seller pays title.');
        $listing->saveMeta('listing_method', 'Bidding Period');

        $before = $this->metaOf($listing);

        $this->sourceReturns($this->raw([
            'ModificationTimestamp'  => self::T1,

            // Property
            'BedroomsTotal'          => 3,
            'YearBuilt'              => 1981,
            'LotSizeAcres'           => 0.25,
            'Zoning'                 => 'R-1',

            // Association / financial
            'AssociationYN'          => true,
            'AssociationFee'         => 455,
            'TaxAnnualAmount'        => 2310,
            'TaxYear'                => 2025,
            'ParcelNumber'           => '11-22-33-44-555',

            // Features
            'Appliances'             => ['Dishwasher', 'Range'],
            'InteriorFeatures'       => ['Ceiling Fans(s)'],
            'Roof'                   => ['Shingle'],
            'Cooling'                => ['Central Air'],
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $meta = $this->metaOf($listing);

        // Facts followed…
        $this->assertSame('3', (string) $meta['bedrooms']);
        $this->assertSame('1981', (string) $meta['year_built']);
        $this->assertSame('R-1', (string) $meta['zoning']);
        // `has_hoa` and the fee, not the association's NAME: the Bridge prefill
        // allow-list carries no `associationName`, so no canonical fact for it
        // is ever produced on this path. MlsFieldMap has a target for it because
        // the URL/text importer can supply one — a reminder that the map is the
        // union of what every import path can reach, and the allow-list is what
        // THIS one does.
        $this->assertNotEmpty((string) $meta['has_hoa']);
        $this->assertSame('455', (string) $meta['association_fee_amount']);
        $this->assertSame('2310', (string) $meta['annual_property_taxes']);
        $this->assertSame('2025', (string) $meta['tax_year']);
        $this->assertSame('11-22-33-44-555', (string) $meta['parcel_id']);
        $this->assertContains('Dishwasher', (array) $meta['appliances']);

        // …and every BYO term is byte-identical.
        foreach (['desired_sale_price', 'contract_terms', 'listing_method'] as $key) {
            $this->assertSame($before[$key], $meta[$key], "Sync altered the BYO field [{$key}]");
        }
    }

    /**
     * @test
     *
     * PublicRemarks is RESTRICTED, and sync must not become the path that
     * publishes it on a schedule.
     *
     * The containment is structural rather than a filter: MlsListingPrefillService
     * reads only its own ALLOWED_FIELDS map, which produces no `description`
     * canonical key at all, so no marketing prose ever reaches the projector.
     * This asserts the result, so that adding a remarks entry to that allow-list
     * — a licensing decision — cannot be done silently.
     */
    public function marketing_prose_never_reaches_the_listing_through_a_sync(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $prose = 'STUNNING waterfront retreat with breathtaking sunsets!';

        $this->sourceReturns($this->raw([
            'ModificationTimestamp' => self::T1,
            'PublicRemarks'         => $prose,
            'PrivateRemarks'        => 'Lockbox code 1234. Seller motivated.',
            'ShowingInstructions'   => 'Call listing agent first.',
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        $encoded = json_encode($this->metaOf($listing));

        $this->assertStringNotContainsString($prose, $encoded);
        $this->assertStringNotContainsString('Lockbox code', $encoded);
        $this->assertStringNotContainsString('Call listing agent', $encoded);
    }

    /**
     * @test
     *
     * A sync must not start the Location DNA pipeline.
     *
     * `QUEUE_CONNECTION` is `sync` here and no process runs `queue:work`, so a
     * dispatched job runs INLINE. Left at the lookup layer's default, an
     * unattended sweep would run Google Places, FEMA and Census work per
     * listing — paid traffic, started by a background timer, on a feature with
     * its own separate gates.
     */
    public function a_sync_never_dispatches_the_location_dna_pipeline(): void
    {
        \Illuminate\Support\Facades\Bus::fake();

        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));

        $this->assertTrue($this->sync($listing)->isSynced());

        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\ComputeLocationDna::class);
    }

    // =====================================================================
    // 16. Media does not churn
    // =====================================================================

    /**
     * @test
     *
     * An unchanged photo set must not be rewritten. The gallery is stored as one
     * meta blob, so a sync that rebuilt it every hour would rewrite the row,
     * move the listing's updated_at, and — because the sweep orders by
     * updated_at — reshuffle the queue on every pass for no reason.
     */
    public function an_unchanged_photo_set_is_not_rewritten(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        // A real change elsewhere, so the sync definitely runs.
        $this->sourceReturns($this->raw([
            'ListPrice'             => 181000,
            'ModificationTimestamp' => self::T1,
        ]));

        $outcome = $this->sync($listing);

        $this->assertTrue($outcome->isSynced());
        $this->assertSame(0, $outcome->mediaAdded, 'The same three photos should not be re-added');
        $this->assertSame(0, $outcome->mediaRemoved);
    }

    // =====================================================================
    // 17. Stones Throw, end to end
    // =====================================================================

    /**
     * @test
     *
     * THE OWNER'S WORKED EXAMPLE, as one continuous story: 6817 Stones Throw
     * Circle N at $184,900, Stellar drops it to $179,900, and the Estimated
     * Monthly Payment calculator opens at the new figure without anybody
     * re-importing anything — while the seller's own asking price, which is a
     * different number meaning a different thing, does not move.
     *
     * Hermetic: a fixture record and a faked endpoint. It does not depend on any
     * live listing id.
     */
    public function the_stones_throw_price_change_reaches_the_payment_calculator_and_leaves_the_sellers_terms_alone(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        // The seller's own term — deliberately NOT the MLS figure.
        $listing->saveMeta('desired_sale_price', '180000');

        // ── Before ──────────────────────────────────────────────────────────
        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->assertTrue($this->sync($listing)->isSynced());

        $meta = $this->metaOf($listing);
        $this->assertSame('184900', (string) $meta[Meta::META_LIST_PRICE]);
        $this->assertSame(184900.0, $this->calculatorPriceFor($meta));

        // ── Stellar reprices ────────────────────────────────────────────────
        $this->sourceReturns($this->raw([
            'ListPrice'             => 179900,
            'ModificationTimestamp' => '2026-09-09T10:00:00.000Z',
            'PriceChangeTimestamp'  => '2026-09-09T10:00:00.000Z',
        ]));

        $this->assertTrue($this->sync($listing)->isSynced());

        // ── After ───────────────────────────────────────────────────────────
        $meta = $this->metaOf($listing);

        $this->assertSame('179900', (string) $meta[Meta::META_LIST_PRICE]);
        $this->assertSame(179900.0, $this->calculatorPriceFor($meta), 'A fresh calculator render must open at the new MLS price');

        // The seller's asking price is theirs and did not move.
        $this->assertSame('180000', (string) $meta['desired_sale_price']);

        // And the price-change marker was recorded from the feed's own field.
        $this->assertSame('2026-09-09T10:00:00.000Z', (string) $meta[Meta::META_SOURCE_PRICE_CHANGED_AT]);
    }

    /**
     * What the seller listing controller's calculator would open at, for this
     * listing's stored meta.
     *
     * Calls the real private builder by reflection rather than restating its
     * fallback chain: a copy of that chain here would keep passing after the
     * real one changed, which is the only way this assertion could mislead.
     */
    private function calculatorPriceFor(array $meta): ?float
    {
        $method = new \ReflectionMethod(\App\Http\Controllers\SellerOfferListingController::class, 'buildCalcData');
        $method->setAccessible(true);

        $data = $method->invoke(app(\App\Http\Controllers\SellerOfferListingController::class), $meta);

        return $data['price'];
    }

    /**
     * @test
     *
     * A calculator what-if is a browser-side scenario. Nothing about it may
     * write back to the stored MLS price, the listing, or the seller's terms.
     */
    public function the_calculator_is_read_only_with_respect_to_the_stored_price(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->sync($listing);

        $before = $this->metaOf($listing);

        // Render the calculator repeatedly, as a visitor moving a slider would.
        for ($i = 0; $i < 3; $i++) {
            $this->calculatorPriceFor($this->metaOf($listing));
        }

        $this->assertSame($before, $this->metaOf($listing), 'Rendering the calculator changed stored data');
    }

    // =====================================================================
    // 18. Last-successful-sync semantics
    // =====================================================================

    /**
     * @test
     *
     * `mls_synced_at` is what a "MLS synced: …" line on the page would read.
     * It must advance only on a successful authoritative refresh — never on a
     * provider failure, or the page would claim currency it does not have.
     */
    public function the_last_successful_sync_stamp_advances_only_on_success(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->assertTrue($this->sync($listing)->isSynced());

        $afterSuccess = $this->metaOf($listing);
        $syncedAt     = (string) $afterSuccess[Meta::META_SYNCED_AT];
        $this->assertNotEmpty($syncedAt);

        // Now the provider stops answering.
        $this->travel(1)->minute();
        $this->sourceFailsWith(503);

        $this->assertSame(MlsSyncOutcome::UNAVAILABLE, $this->sync($listing)->status);

        $afterFailure = $this->metaOf($listing);

        $this->assertSame($syncedAt, (string) $afterFailure[Meta::META_SYNCED_AT], 'A failure advanced the success stamp');
        $this->assertNotEmpty($afterFailure[Meta::META_SYNC_ATTEMPTED_AT], 'The attempt should still be recorded');
        $this->assertNotEmpty($afterFailure[Meta::META_SYNC_ERROR]);

        // And the known-good facts are all still there.
        $this->assertSame('184900', (string) $afterFailure[Meta::META_LIST_PRICE]);
    }

    /** @test */
    public function a_not_found_record_does_not_advance_the_success_stamp_either(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $this->sourceReturns($this->raw(['ModificationTimestamp' => self::T1]));
        $this->sync($listing);

        $syncedAt = (string) $this->metaOf($listing)[Meta::META_SYNCED_AT];

        $this->travel(1)->minute();
        $this->sourceHasNoRecord();

        $this->assertSame(MlsSyncOutcome::NOT_FOUND, $this->sync($listing)->status);

        $meta = $this->metaOf($listing);

        $this->assertSame($syncedAt, (string) $meta[Meta::META_SYNCED_AT]);
        $this->assertSame('184900', (string) $meta[Meta::META_LIST_PRICE], 'A record absent from the feed must not blank the price');
    }

    // =====================================================================
    // 19. The overwrite journal
    // =====================================================================

    /**
     * @test
     *
     * The journal records MLS-fact replacement so an overwrite is recoverable.
     * It must never be treated as licence to overwrite a BYO term — a protected
     * key should not appear in it, because a protected key is never written.
     */
    public function the_overwrite_journal_records_only_mls_facts_and_is_bounded(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $listing->saveMeta('contract_terms', 'Seller pays title.');

        // Twelve successive source changes, one more than the journal keeps.
        for ($i = 1; $i <= 12; $i++) {
            $this->sourceReturns($this->raw([
                'BedroomsTotal'         => $i,
                'ModificationTimestamp' => sprintf('2026-10-%02dT10:00:00.000Z', $i),
            ]));

            $this->sync($listing);
        }

        $journal = $this->metaOf($listing)[Meta::META_SYNC_JOURNAL] ?? [];

        $this->assertIsArray($journal);
        $this->assertLessThanOrEqual(10, count($journal), 'The journal is a safety net, not an unbounded audit log');
        $this->assertNotEmpty($journal);

        // Nothing protected was ever journalled, because nothing protected was
        // ever written.
        $encoded = json_encode($journal);
        foreach (\App\Services\ListingImport\Sync\MlsSyncFieldPolicy::protectedMetaKeys() as $protected) {
            $this->assertStringNotContainsString('"' . $protected . '"', $encoded);
        }

        $this->assertSame('Seller pays title.', (string) $this->metaOf($listing)['contract_terms']);
    }
    // =====================================================================
    // 20. Address and coordinates — the invariant, held by not moving
    // =====================================================================

    /**
     * @test
     *
     * THE INVARIANT: a new address must never remain paired with the old
     * property coordinate.
     *
     * Phase 1A holds it the only way that is safe today — by moving NEITHER.
     * Both the address block and the lat/lng pair are in MlsSyncFieldPolicy's
     * never-sync list, so the forbidden pairing cannot arise.
     *
     * Syncing the address properly needs the coordinate ladder to re-resolve the
     * point atomically with it, and `PropertyCoordinateResolverInterface` is
     * deliberately bound to nothing until G5. Writing the feed's own lat/lng
     * instead would bypass CoordinatePrecision and record no provenance — a
     * coordinate with no provider and no precision, which is the exact defect
     * the ladder exists to prevent. So this stays still, on purpose, and this
     * test is what stops somebody "fixing" it in isolation.
     */
    public function an_address_change_in_the_feed_moves_neither_the_address_nor_the_coordinates(): void
    {
        $this->seedCache($this->raw());
        $listing = $this->import(User::factory()->create());

        $before = $this->metaOf($listing);

        $this->sourceReturns($this->raw([
            'UnparsedAddress'       => '999 Somewhere Else Boulevard',
            'City'                  => 'TAMPA',
            'PostalCode'            => '33601',
            'Latitude'              => 27.9506,
            'Longitude'             => -82.4572,
            'ListPrice'             => 181500,
            'ModificationTimestamp' => self::T1,
        ]));

        // The sync still runs and still does its job for everything else.
        $this->assertTrue($this->sync($listing)->isSynced());

        $after = $this->metaOf($listing);

        $this->assertSame('181500', (string) $after[Meta::META_LIST_PRICE], 'The price should still have followed the feed');

        foreach (['address', 'property_city', 'property_zip', 'property_lat', 'property_lng'] as $key) {
            $this->assertSame(
                $before[$key] ?? null,
                $after[$key] ?? null,
                "Sync moved [{$key}] — address and coordinates must move together, through the ladder, or not at all"
            );
        }
    }

    /**
     * @test
     *
     * The policy's reasons are part of the contract, not decoration: each
     * excluded key carries a sentence saying why, so an exclusion cannot be
     * quietly deleted as an oversight.
     */
    public function every_never_synced_field_states_a_reason(): void
    {
        $reasons = \App\Services\ListingImport\Sync\MlsSyncFieldPolicy::neverSyncReasons();

        foreach (['address', 'city', 'state', 'zip', 'county', 'latitude', 'longitude'] as $key) {
            $this->assertArrayHasKey($key, $reasons);
            $this->assertNotSame('', trim($reasons[$key]));
        }
    }
}
