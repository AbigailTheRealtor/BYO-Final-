<?php

namespace Tests\Feature\Stellar;

use App\Models\TenantAgentAuction;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ZIP resolution for the Tenant offer-listing matcher payload.
 *
 * THE DEFECT THIS PINS
 * --------------------
 * ZIPs for this workflow live in two stores, and which one holds a given listing's ZIPs depends on
 * when it was last edited:
 *
 *   - the Search Areas map widget writes them into `location_dna_preferences.zip_codes`, and
 *     nothing mirrors them out — `HasSearchAreas::saveSearchAreas()` mirrors state / counties /
 *     cities and stops there;
 *   - the older discrete input writes the legacy `zipCodes` meta and never touches the blob.
 *
 * The loader read only the legacy key, so a tenant who set ZIPs through the map got an empty
 * `preferred_zip_codes` and matched against every ZIP in the dataset.
 *
 * THE AUTHORITY RULE, AND THE CASE THAT DEFINES IT
 * ------------------------------------------------
 * ZIPs now follow the same rule as cities: the blob wins whenever it carries the key, and legacy
 * meta answers only when the blob cannot — absent, unparseable, or predating the key.
 *
 * The case that gives the rule its teeth is `zip_codes: []`. The map is the only ZIP editing
 * surface this workflow has, so an empty array is a user who cleared their ZIPs, not an absence of
 * information — and it must return empty rather than resurrecting a stale legacy value the user
 * has no way to delete. `array_key_exists` is what makes that work; `isset()` or `!empty()` would
 * collapse "cleared" into "unset" and silently re-add the removed ZIPs.
 *
 * This is deliberately NARROWER than {@see \App\Services\LocationDna\LocationMatchAuctionExtractor},
 * which unions the same two sources. That feeds proximity scoring, where a superset only widens a
 * search; this feeds a query filter, where a stale ZIP re-admits inventory the user excluded.
 *
 * READ-PATH ONLY. No writer was touched; both stores are left exactly as they were found.
 */
class TenantOfferListingZipResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private function skipIfTableMissing(): void
    {
        foreach (['tenant_agent_auctions', 'tenant_agent_auction_metas', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'ZipRes',
            'last_name'   => 'Test',
            'name'        => 'ZipRes Test',
            'short_id'    => 'ZIPRES'.uniqid(),
            'user_name'   => 'zipres_'.uniqid(),
            'email'       => 'zipres-'.uniqid().'@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => 'tenant',
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * An approved, unsold tenant OFFER LISTING — the only shape `findRecord()` will return.
     *
     * Built through the query builder rather than the model because `TenantAgentAuction` is fully
     * guarded, which is the same approach `TenantCommercialLeaseMatchingTest` takes.
     */
    private function makeTenantOfferListing(int $userId, array $meta = []): TenantAgentAuction
    {
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

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction;
    }

    /** @return array<string, mixed> the loaded matcher payload */
    private function load(TenantAgentAuction $auction, int $userId): array
    {
        $result = (new TenantOfferListingCriteriaLoader())->loadById($auction->id, [$userId]);

        $this->assertNotNull($result, 'The loader must return a payload for an approved offer listing.');

        return $result;
    }

    /** A Location DNA blob carrying ZIPs the way the map widget writes them. */
    private function blobWithZips(array $zips): string
    {
        return json_encode([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Tampa, FL'],
            'zip_codes' => $zips,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · EACH SOURCE ALONE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * BLOB ONLY — the case that was broken.
     *
     * A listing edited through the map widget has ZIPs in the blob and nothing in the legacy key.
     * Before the fix this returned an empty list, and the tenant matched against every ZIP.
     */
    public function test_zips_present_only_in_the_location_dna_blob_are_returned(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $this->blobWithZips(['33701', '33702']),
        ]);

        $this->assertSame(['33701', '33702'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    /**
     * LEGACY ONLY — the case that already worked and must keep working.
     *
     * This is the backward-compatibility half: records written before the map widget existed carry
     * only `zipCodes`, and the fix must not disturb them.
     */
    public function test_zips_present_only_in_legacy_meta_are_returned(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'zipCodes' => json_encode(['34677', '34683']),
        ]);

        $this->assertSame(['34677', '34683'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    /** A legacy-only listing with a blob that simply has no `zip_codes` key is still fine. */
    public function test_legacy_zips_survive_a_blob_that_carries_no_zip_key(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => json_encode(['state' => 'Florida', 'cities' => ['Tampa, FL']]),
            'zipCodes'                 => json_encode(['34677']),
        ]);

        $this->assertSame(['34677'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · BOTH PRESENT — THE BLOB IS AUTHORITATIVE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * When both stores hold ZIPs, the blob wins outright and legacy is not consulted.
     *
     * 34677 must NOT appear. A union would return it, which is why this assertion is written
     * against the full list rather than merely checking the blob's ZIPs are present.
     */
    public function test_the_blob_wins_when_both_sources_hold_zips(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $this->blobWithZips(['33701', '33702']),
            'zipCodes'                 => json_encode(['34677']),
        ]);

        $zips = $this->load($auction, $userId)['preferred_zip_codes'];

        $this->assertSame(['33701', '33702'], $zips);
        $this->assertNotContains('34677', $zips, 'Legacy meta must not be consulted once the blob carries the key.');
    }

    /**
     * THE CLEAR-ALL CASE, and the reason authority keys on the key rather than the value.
     *
     * A tenant clears every ZIP on the map. The blob is saved with `zip_codes: []` while the legacy
     * meta still holds what it always held — nothing writes to it, and no UI can. If an empty array
     * were treated as "no information", those legacy ZIPs would come back and the user would have
     * no way to remove them.
     */
    public function test_an_empty_blob_zip_array_returns_empty_and_does_not_fall_back(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $this->blobWithZips([]),
            'zipCodes'                 => json_encode(['34677', '33701']),
        ]);

        $this->assertSame(
            [],
            $this->load($auction, $userId)['preferred_zip_codes'],
            'An explicitly cleared ZIP selection must not resurrect legacy values.'
        );
    }

    /** Duplicates inside the authoritative source collapse, and the result stays a clean list. */
    public function test_duplicates_within_the_authoritative_source_are_filtered(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $this->blobWithZips(['33701', '33702', '33701']),
        ]);

        $zips = $this->load($auction, $userId)['preferred_zip_codes'];

        $this->assertSame(['33701', '33702'], $zips);
        $this->assertSame(array_keys($zips), range(0, count($zips) - 1), 'The result must be a list.');
    }

    /** Deduplication applies to the legacy source too, when legacy is the one answering. */
    public function test_duplicates_within_legacy_meta_are_filtered(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'zipCodes' => json_encode(['34677', '34683', '34677']),
        ]);

        $this->assertSame(['34677', '34683'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · NOTHING TO RESOLVE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_no_zips_anywhere_yields_an_empty_list(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId);

        $this->assertSame([], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    /** Blank entries are dropped rather than becoming empty-string ZIPs that match nothing. */
    public function test_blank_entries_are_filtered_from_the_blob(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $this->blobWithZips(['33701', '', '  ']),
        ]);

        $this->assertSame(['33701'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    /** Likewise when legacy meta is the source answering. */
    public function test_blank_entries_are_filtered_from_legacy_meta(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'zipCodes' => json_encode(['', '34677', '   ']),
        ]);

        $this->assertSame(['34677'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    /**
     * A blob whose ZIPs are ALL blank still counts as authoritative.
     *
     * The key is present, so the blob speaks; it just has nothing left to say once blanks are
     * filtered. Falling through to legacy here would be the Clear-All bug wearing a disguise.
     */
    public function test_a_blob_of_only_blank_zips_returns_empty_without_falling_back(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $this->blobWithZips(['', '  ']),
            'zipCodes'                 => json_encode(['34677']),
        ]);

        $this->assertSame([], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    /** A malformed blob never throws and never blocks the legacy half from resolving. */
    public function test_a_malformed_blob_does_not_prevent_legacy_zips_resolving(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => '{not json at all',
            'zipCodes'                 => json_encode(['34677']),
        ]);

        $this->assertSame(['34677'], $this->load($auction, $userId)['preferred_zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · THE READ PATH WRITES NOTHING
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Loading must not reconcile the two stores.
     *
     * The whole safety argument for this fix is that it is a read. If the loader ever "helpfully"
     * mirrored the union back into either key, that would become a silent write on every match run.
     */
    public function test_loading_leaves_both_stores_exactly_as_they_were(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $blob    = $this->blobWithZips(['33701']);
        $legacy  = json_encode(['34677']);
        $auction = $this->makeTenantOfferListing($userId, [
            'location_dna_preferences' => $blob,
            'zipCodes'                 => $legacy,
        ]);

        $this->load($auction, $userId);

        $fresh = $auction->fresh();

        $this->assertSame($blob, $fresh->info('location_dna_preferences'));
        $this->assertSame($legacy, $fresh->info('zipCodes'));
    }
}
