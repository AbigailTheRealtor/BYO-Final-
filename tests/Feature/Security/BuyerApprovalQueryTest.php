<?php

namespace Tests\Feature\Security;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\User;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Support\Listing\ListingFlag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Buyer QUERIES read `is_approved` / `is_sold` by the same contract as the model.
 *
 * THE DEFECT. `buyer_agent_auctions.is_approved` and `.is_sold` are varchar columns,
 * and both Buyer publish paths (the Hire Buyer wizard and the Buyer Offer Listing
 * wizard) store the strings 'true' and 'false'. PR #159 made the MODEL read those by
 * ListingFlag's contract — true, 1, '1' and 'true' are set, everything else is not —
 * but a query never goes through the model. `where('is_approved', true)` binds the
 * integer 1 and matches '1' alone; `where('is_sold', false)` matches '0' alone. So a
 * wizard-published Buyer listing, which the model calls approved and unsold, was
 * absent from every query written that way: MLS matching, Match Check, the public
 * profile page, the agent's Buyer tabs and the admin lists.
 *
 * WHAT IS ASSERTED. Each page or service below is driven through its real query and
 * judged by what it returned. The matrix at the top pins the rule itself: for every
 * representable raw value, the query answer equals ListingFlag::isTrue(). The columns
 * are NOT NULL on both engines, so NULL cannot be stored here; whereNotTrue() still
 * treats it as not set, which the builder test checks structurally.
 */
class BuyerApprovalQueryTest extends TestCase
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
        $this->owner = User::factory()->create(['user_type' => 'buyer']);
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    /** A Buyer listing whose two flags hold exactly the given raw values. */
    private function buyerListing(mixed $approved, mixed $sold, ?string $workflow = null, array $attributes = []): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::forceCreate($attributes + [
            'user_id'     => $this->owner->id,
            'title'       => 'Buyer listing ' . uniqid(),
            'is_draft'    => false,
            'is_archived' => false,
        ]);

        // Bypass the model, as a writer left it — not as Eloquent would choose.
        DB::table('buyer_agent_auctions')->where('id', $listing->id)
            ->update(['is_approved' => $approved, 'is_sold' => $sold]);

        if ($workflow !== null) {
            $listing->saveMeta('workflow_type', $workflow);
        }

        return $listing->fresh();
    }

    /** What both Buyer publish paths write: HireBuyerAgent\BuyerAgentAuction and OfferListing\Buyer\BuyerOfferListing. */
    private function wizardPublished(?string $workflow = null): BuyerAgentAuction
    {
        return $this->buyerListing('true', 'false', $workflow);
    }

    private function ids(iterable $rows): array
    {
        return collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    // =====================================================================
    // The rule
    // =====================================================================

    /** @test */
    public function every_raw_value_is_selected_by_the_query_exactly_when_the_model_calls_it_set(): void
    {
        foreach (['is_approved', 'is_sold'] as $column) {
            $rows = [];
            foreach (self::RAW as $label => [$raw, $set]) {
                $rows[$label] = $column === 'is_approved'
                    ? $this->buyerListing($raw, '0')
                    : $this->buyerListing('1', $raw);
            }
            $ids = array_map(fn ($row) => $row->id, $rows);

            $true    = ListingFlag::whereTrue(BuyerAgentAuction::whereIn('id', $ids), $column)->pluck('id')->all();
            $notTrue = ListingFlag::whereNotTrue(BuyerAgentAuction::whereIn('id', $ids), $column)->pluck('id')->all();

            foreach (self::RAW as $label => [$raw, $set]) {
                $id = $rows[$label]->id;
                $this->assertSame($set, in_array($id, $true), "{$column} {$label}: whereTrue()");
                $this->assertSame(! $set, in_array($id, $notTrue), "{$column} {$label}: whereNotTrue()");
                $this->assertSame($set, ListingFlag::isTrue(DB::table('buyer_agent_auctions')->where('id', $id)->value($column)), "{$column} {$label}: the model contract");
            }
        }
    }

    /** @test */
    public function the_query_is_an_exact_list_with_no_boolean_coercion_and_null_counts_as_not_set(): void
    {
        $true = ListingFlag::whereTrue(BuyerAgentAuction::query(), 'is_approved');
        $this->assertStringContainsString('"is_approved" in (?, ?)', $true->toSql());
        $this->assertSame(['1', 'true'], $true->getBindings());

        $notTrue = ListingFlag::whereNotTrue(BuyerAgentAuction::query(), 'is_sold');
        $this->assertStringContainsString('("is_sold" not in (?, ?) or "is_sold" is null)', $notTrue->toSql());
        $this->assertSame(['1', 'true'], $notTrue->getBindings());
    }

    // =====================================================================
    // The queries that dropped a wizard-published Buyer listing
    // =====================================================================

    /** @test */
    public function mls_matching_loads_a_wizard_published_buyer_offer_listing(): void
    {
        $published = $this->wizardPublished('offer_listing');
        $loader = app(BuyerOfferListingCriteriaLoader::class);

        $this->assertNotNull($loader->loadById($published->id, [$this->owner->id]), "the 'true'/'false' row a wizard publishes");
        $this->assertNotNull($loader->load($this->owner->id));
    }

    /** @test */
    public function mls_matching_never_loads_an_unapproved_or_sold_buyer_offer_listing(): void
    {
        $loader = app(BuyerOfferListingCriteriaLoader::class);

        foreach ([['false', 'false'], ['0', '0'], ['', 'false'], ['true', 'true'], ['1', '1']] as [$approved, $sold]) {
            $row = $this->buyerListing($approved, $sold, 'offer_listing');
            $this->assertNull($loader->loadById($row->id, [$this->owner->id]), "approved={$approved} sold={$sold} must not be matched");
        }
    }

    /** @test */
    public function match_check_lists_a_wizard_published_buyer_offer_listing_and_nothing_unapproved(): void
    {
        $published = $this->wizardPublished('offer_listing');
        $pending   = $this->buyerListing('false', 'false', 'offer_listing');
        $sold      = $this->buyerListing('true', 'true', 'offer_listing');

        $items = collect(app(CriteriaListingResolver::class)->resolveAccessible($this->owner))
            ->where('type', 'buyer_offer')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($published->id, $items);
        $this->assertNotContains($pending->id, $items);
        $this->assertNotContains($sold->id, $items);
    }

    /** @test */
    public function the_public_profile_lists_a_wizard_published_buyer_listing_and_hides_the_rest(): void
    {
        $published = $this->wizardPublished();
        $legacy    = $this->buyerListing('1', '0');
        $pending   = $this->buyerListing('false', 'false');
        $blank     = $this->buyerListing('', 'false');
        $sold      = $this->buyerListing('true', 'true');
        // Matching 'true' must not publish a draft.
        $draft     = $this->buyerListing('true', 'false', null, ['is_draft' => true]);

        $visitor = User::factory()->create(['user_type' => 'buyer']);
        $response = $this->actingAs($visitor)->get(route('author', $this->owner->id));
        $response->assertOk();

        $this->assertSame(
            $this->ids([$published, $legacy]),
            $this->ids($response->original->getData()['pAuctions']->items()),
            'Approved and unsold, in either stored form — and nothing unapproved or sold.'
        );
    }

    /** @test */
    public function the_agent_buyer_tabs_sort_every_listing_the_agent_bid_on_by_its_meaning(): void
    {
        $agent = User::factory()->asAgent()->create();

        // Two bids at least: the tab query used to OR the ids together, so every filter
        // after the first id escaped it.
        $pending = $this->buyerListing('false', 'false');
        $live    = $this->wizardPublished();
        $sold    = $this->buyerListing('true', 'true');
        foreach ([$pending, $live, $sold] as $listing) {
            BuyerAgentAuctionBid::forceCreate(['buyer_agent_auction_id' => $listing->id, 'user_id' => $agent->id]);
        }

        foreach (['1' => $pending, '2' => $live, '3' => $sold] as $tab => $expected) {
            $data = $this->actingAs($agent)->get(route('buyer.biding.auctions.list', ['type' => $tab]))->assertOk()->original->getData();
            $this->assertSame([$expected->id], $this->ids($data['auctions']), "tab {$tab}");
        }

        $this->assertSame([1, 1, 1], [$data['pendingApprovalCount'], $data['liveCount'], $data['soldCount']]);
    }

    /** @test */
    public function the_admin_buyer_lists_find_rows_in_either_stored_form(): void
    {
        $admin = User::factory()->asAdmin()->create();

        $approved = $this->wizardPublished();
        $pending  = $this->buyerListing('false', 'false');
        $sold     = $this->buyerListing('1', 'true');

        $list = fn (int $type) => $this->ids($this->actingAs($admin)
            ->get(route('admin.buyerAgentAuctions', ['type' => $type]))->assertOk()->original->getData()['auctions']);

        $this->assertContains($approved->id, $list(1), 'Approved tab');
        $this->assertNotContains($pending->id, $list(1));
        $this->assertContains($sold->id, $list(2), 'Sold tab');
        $this->assertNotContains($approved->id, $list(2));
        $this->assertContains($pending->id, $list(0), 'Pending tab — where an admin approves it');
        $this->assertNotContains($approved->id, $list(0));
    }
}
