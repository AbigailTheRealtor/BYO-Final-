<?php

namespace Tests\Feature\Security;

use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\User;
use App\Support\Listing\ListingFlag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seller QUERIES read `is_sold` / `is_approved` by the same contract as the model.
 *
 * THE DEFECT. `seller_agent_auctions.is_sold` and `.is_approved` are varchar columns
 * (DEFAULT '0'). The Hire Seller and Seller Offer Listing wizards publish PHP true / 0,
 * stored as '1' / '0'; accepting a bid stores is_sold = true, i.e. '1'; the multi-role
 * wizard stores the string 'false'. The model reads every one of those through
 * ListingFlag (true, 1, '1', 'true' are set; everything else is not). The Seller
 * QUERIES compared against the strings 'false' / 'true' instead — so a live listing
 * stored as '0' was missing from every "not sold" filter, an accepted listing stored as
 * '1' from every "sold" filter, and `is_approved = 'true'`, which no Seller writer
 * produces, matched nothing at all.
 *
 * Every test below drives a real page through its real query and judges the ids it
 * received. The matrix at the top pins the rule on this table: for every representable
 * raw value, the query answer equals ListingFlag::isTrue(). The columns are NOT NULL, so
 * NULL cannot be stored here.
 */
class SellerSoldQueryTest extends TestCase
{
    use DatabaseTransactions;

    /** Raw value => whether the contract calls it set. PHP true / 1 are stored as '1'. */
    private const RAW = [
        'true (bool)'  => [true, true],
        '1 (int)'      => [1, true],
        "'1'"          => ['1', true],
        "'true'"       => ['true', true],
        'false (bool)' => [false, false],
        '0 (int)'      => [0, false],
        "'0'"          => ['0', false],
        "'false'"      => ['false', false],
        "''"           => ['', false],
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['user_type' => 'seller']);
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    /** A Seller listing whose two flags hold exactly the given raw values. */
    private function sellerListing(mixed $approved, mixed $sold, array $attributes = [], ?User $owner = null): SellerAgentAuction
    {
        $listing = SellerAgentAuction::forceCreate($attributes + [
            'user_id'     => ($owner ?? $this->owner)->id,
            'address'     => 'Seller listing ' . uniqid(),
            'is_draft'    => false,
            'is_archived' => false,
        ]);

        // Every Seller wizard saves this; the owner hub's Edit link is built from it.
        $listing->saveMeta('user_type', 'seller');

        // As a writer left it, bypassing the model.
        DB::table('seller_agent_auctions')->where('id', $listing->id)
            ->update(['is_approved' => $approved, 'is_sold' => $sold]);

        return $listing->fresh();
    }

    /**
     * One row per state, in each form a real writer stores:
     *   live      '1' / '0'      Hire Seller or Seller Offer Listing publish
     *   liveMulti '1' / 'false'  multi-role wizard publish
     *   pending   '0' / '0'      not yet approved
     *   legacy    'false'/'false' draft path, 2026-01 to 2026-03
     *   accepted  '1' / '1'      acceptSABid / accept flow
     *   soldText  '1' / 'true'
     *   draft     '1' / '0', is_draft — a wizard draft keeps the model default '1'
     */
    private function states(?User $owner = null): array
    {
        return [
            'live'      => $this->sellerListing('1', '0', [], $owner),
            'liveMulti' => $this->sellerListing('1', 'false', [], $owner),
            'pending'   => $this->sellerListing('0', '0', [], $owner),
            'legacy'    => $this->sellerListing('false', 'false', [], $owner),
            'accepted'  => $this->sellerListing('1', '1', [], $owner),
            'soldText'  => $this->sellerListing('1', 'true', [], $owner),
            'draft'     => $this->sellerListing('1', '0', ['is_draft' => true], $owner),
        ];
    }

    private function ids(iterable $rows): array
    {
        return collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    private function idsOf(array $states, array $names): array
    {
        return collect($names)->map(fn ($n) => $states[$n]->id)->sort()->values()->all();
    }

    // =====================================================================
    // The rule, on the Seller table
    // =====================================================================

    /** @test */
    public function every_raw_value_is_selected_by_the_query_exactly_when_the_model_calls_it_set(): void
    {
        foreach (['is_sold', 'is_approved'] as $column) {
            $rows = [];
            foreach (self::RAW as $label => [$raw, $set]) {
                $rows[$label] = $column === 'is_sold'
                    ? $this->sellerListing('1', $raw)
                    : $this->sellerListing($raw, '0');
            }
            $ids = array_map(fn ($row) => $row->id, $rows);

            $true    = ListingFlag::whereTrue(SellerAgentAuction::whereIn('id', $ids), $column)->pluck('id')->all();
            $notTrue = ListingFlag::whereNotTrue(SellerAgentAuction::whereIn('id', $ids), $column)->pluck('id')->all();

            foreach (self::RAW as $label => [$raw, $set]) {
                $id = $rows[$label]->id;
                $this->assertSame($set, in_array($id, $true), "{$column} {$label}: whereTrue()");
                $this->assertSame(! $set, in_array($id, $notTrue), "{$column} {$label}: whereNotTrue()");
                $this->assertSame($set, ListingFlag::isTrue(DB::table('seller_agent_auctions')->where('id', $id)->value($column)), "{$column} {$label}: the model contract");
            }
        }
    }

    // =====================================================================
    // The queries that misfiled Seller listings
    // =====================================================================

    /** @test */
    public function the_owner_hire_hub_files_every_listing_by_its_meaning(): void
    {
        $s = $this->states();

        $tab = fn (string $type) => $this->actingAs($this->owner)
            ->get(route('hireSellerAgentHireAuctions', ['type' => $type]))->assertOk()->original->getData();

        $this->assertSame($this->idsOf($s, ['live', 'liveMulti']), $this->ids($tab('2')['auctions']), 'Live');
        $this->assertSame($this->idsOf($s, ['pending', 'legacy']), $this->ids($tab('1')['auctions']), 'Pending');
        $data = $tab('3');
        $this->assertSame($this->idsOf($s, ['accepted', 'soldText']), $this->ids($data['auctions']), 'Sold — an accepted listing is sold');
        $this->assertSame([2, 2, 2], [$data['pendingApprovalCount'], $data['liveCount'], $data['soldCount']]);
    }

    /** @test */
    public function the_agent_seller_tabs_file_every_listing_the_agent_bid_on_by_its_meaning(): void
    {
        $agent = User::factory()->asAgent()->create();
        $s = $this->states();
        foreach ($s as $listing) {
            SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $listing->id, 'user_id' => $agent->id]);
        }

        $tab = fn (string $type) => $this->actingAs($agent)
            ->get(route('seller.biding.auctions.list', ['type' => $type]))->assertOk()->original->getData();

        $this->assertSame($this->idsOf($s, ['live', 'liveMulti']), $this->ids($tab('2')['auctions']), 'Live');
        $this->assertSame($this->idsOf($s, ['pending', 'legacy']), $this->ids($tab('1')['auctions']), 'Pending');
        $data = $tab('3');
        $this->assertSame($this->idsOf($s, ['accepted', 'soldText']), $this->ids($data['auctions']), 'Sold');
        $this->assertSame([2, 2, 2], [$data['pendingApprovalCount'], $data['liveCount'], $data['soldCount']]);
    }

    /** @test */
    public function the_admin_seller_lists_find_rows_in_every_stored_form(): void
    {
        $admin = User::factory()->asAdmin()->create();
        $s = $this->states();

        $list = fn (int $type) => $this->ids($this->actingAs($admin)
            ->get(route('admin.sellerAgentAuctions', ['type' => $type]))->assertOk()->original->getData()['auctions']);

        $sold = $list(2);
        $this->assertContains($s['accepted']->id, $sold, 'Sold tab — accepted listings');
        $this->assertContains($s['soldText']->id, $sold);
        $this->assertNotContains($s['live']->id, $sold);
        $this->assertNotContains($s['liveMulti']->id, $sold);

        $pending = $list(0);
        $this->assertContains($s['pending']->id, $pending, 'Pending tab');
        $this->assertContains($s['legacy']->id, $pending, "Pending tab — a 'false' row is where an admin approves it");
        $this->assertNotContains($s['live']->id, $pending);

        $approved = $list(1);
        $this->assertContains($s['live']->id, $approved, 'Approved tab');
        $this->assertNotContains($s['legacy']->id, $approved);
    }

    /** @test */
    public function the_dashboard_counts_live_seller_listings_in_every_stored_form(): void
    {
        $this->states();

        $counts = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->original->getData()['listingCounts'];

        $this->assertSame(2, $counts['seller'], "'1'/'0' and '1'/'false' are both live; nothing else is");
    }

    /** @test */
    public function the_public_profile_shows_live_seller_listings_and_nothing_pending_sold_draft_or_archived(): void
    {
        $s = $this->states();
        $archived = $this->sellerListing('1', '0', ['is_archived' => true]);

        $visitor = User::factory()->create(['user_type' => 'buyer']);
        $shown = $this->ids($this->actingAs($visitor)->get(route('author', $this->owner->id))
            ->assertOk()->original->getData()['pAuctions']->items());

        $this->assertSame($this->idsOf($s, ['live', 'liveMulti']), $shown);
        $this->assertNotContains($archived->id, $shown);
    }

    /** @test */
    public function a_visitor_to_the_tenant_profile_seller_tab_sees_only_live_listings(): void
    {
        $tenant = User::factory()->create(['user_type' => 'tenant']);
        $s = $this->states($tenant);

        $visitor = User::factory()->create(['user_type' => 'buyer']);
        $shown = $this->ids($this->actingAs($visitor)->get(route('author', ['id' => $tenant->id, 'type' => 1]))
            ->assertOk()->original->getData()['pAuctions']->items());

        $this->assertSame($this->idsOf($s, ['live', 'liveMulti']), $shown);
    }
}
