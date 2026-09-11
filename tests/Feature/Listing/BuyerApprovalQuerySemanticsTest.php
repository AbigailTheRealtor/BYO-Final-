<?php

namespace Tests\Feature\Listing;

use App\Models\BuyerAgentAuction;
use App\Models\User;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Buyer `is_approved` QUERIES must mean what the Buyer model reads.
 *
 * THE DEFECT THIS FILE PINS
 * -------------------------
 * `buyer_agent_auctions.is_approved` is a varchar column. The Buyer publish
 * paths (HireBuyerAgent\BuyerAgentAuction::store and
 * OfferListing\Buyer\BuyerOfferListing::store) assign the string 'true', which
 * the column keeps verbatim when the row is inserted by that publish. PR #159
 * made the MODEL read 'true' as approved — but several QUERIES compare with
 * `where('is_approved', true)` / `1`, which binds as '1' and matches '1' only.
 * An approved 'true' row is therefore dropped from the admin queues, the
 * agent's bidding list, public author profiles and the Stellar criteria /
 * Match Check loaders, while every model-level reader calls it approved.
 *
 * The not-approved side has the mirror defect: `where('is_approved', false)` /
 * `0` binds as '0' and misses 'false' and '', both of which the model reads as
 * not approved — so such a row is in neither admin tab.
 *
 * WHY THE TEST DATABASE IS TRUSTWORTHY HERE
 * -----------------------------------------
 * The suite runs on SQLite. For a VARCHAR column SQLite compares exactly as
 * PostgreSQL does — a bound PHP true becomes 1 and is compared as '1' — which
 * was proven against a throwaway PostgreSQL 16 instance during the audit, and
 * is pinned here by the precondition test so a divergence cannot pass silently.
 * (Landlord and Tenant are real boolean columns on PostgreSQL and are out of
 * scope.)
 *
 * A read-only production count on 2026-09-11 found no 'true' rows: every Buyer
 * row is '0' or '1'. The defect is latent — it bites the first time a Buyer
 * publishes without having saved a draft first.
 */
class BuyerApprovalQuerySemanticsTest extends TestCase
{
    use DatabaseTransactions;

    /** Stored representations the Buyer model reads as APPROVED. */
    private const APPROVED = ["'1'" => '1', "'true'" => 'true'];

    /** Stored representations the Buyer model reads as NOT APPROVED. */
    private const NOT_APPROVED = ["'0'" => '0', "'false'" => 'false', "''" => ''];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['user_type' => 'buyer']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * One published (non-draft, not sold, not archived) Buyer row per raw
     * approval value, keyed by that value's label.
     *
     * @return array<string,int> label => id
     */
    private function publishedRows(?int $userId = null, bool $offerListing = true): array
    {
        $ids = [];

        foreach (self::APPROVED + self::NOT_APPROVED as $label => $raw) {
            $row = BuyerAgentAuction::create([
                'user_id'  => $userId ?? $this->owner->id,
                'title'    => "BUYER APPROVAL QUERY {$label}",
                'is_draft' => false,
            ]);

            if ($offerListing) {
                $row->saveMeta('workflow_type', 'offer_listing');
            }

            // The raw value a writer left behind — not one Eloquent chose.
            DB::table('buyer_agent_auctions')->where('id', $row->id)->update([
                'is_approved' => $raw,
                'is_sold'     => '0',
            ]);

            $ids[$label] = (int) $row->id;
        }

        return $ids;
    }

    /**
     * Assert a query result holds exactly the approved or the not-approved rows.
     *
     * @param  array<string,int>  $rows  label => id, as publishedRows() returned
     * @param  iterable<int>      $found ids the path returned
     */
    private function assertHolds(string $context, array $rows, iterable $found, bool $approved): void
    {
        $found = collect($found)->map(fn ($id) => (int) $id)->all();

        foreach ($rows as $label => $id) {
            $isApproved = array_key_exists($label, self::APPROVED);

            $this->assertSame(
                $isApproved === $approved,
                in_array($id, $found, true),
                "{$context}: raw {$label} " . ($isApproved === $approved ? 'must be listed' : 'must not be listed'),
            );
        }
    }

    // ── Precondition: the test database compares as PostgreSQL does ────────

    /** @test */
    public function the_test_database_reproduces_the_postgresql_varchar_truth_table(): void
    {
        $rows = $this->publishedRows();

        $match = fn ($q) => collect($q->whereIn('id', $rows)->pluck('id'))
            ->map(fn ($id) => array_search((int) $id, $rows, true))->sort()->values()->all();

        // Exactly what PostgreSQL 16 returned for the same column in the audit.
        $this->assertSame(["'1'"], $match(DB::table('buyer_agent_auctions')->where('is_approved', true)));
        $this->assertSame(["'1'"], $match(DB::table('buyer_agent_auctions')->where('is_approved', 1)));
        $this->assertSame(["'0'"], $match(DB::table('buyer_agent_auctions')->where('is_approved', false)));
        $this->assertSame(["'true'"], $match(DB::table('buyer_agent_auctions')->where('is_approved', 'true')));
        $this->assertSame(["'1'", "'true'"], $match(DB::table('buyer_agent_auctions')->whereIn('is_approved', ['1', 'true'])));

        // And the model — PR #159 — reads every one of them by its meaning.
        foreach ($rows as $label => $id) {
            $this->assertSame(array_key_exists($label, self::APPROVED), BuyerAgentAuction::find($id)->is_approved, "model {$label}");
        }
    }

    // ── F. Admin queues ────────────────────────────────────────────────────

    /** @test */
    public function the_admin_approved_queue_lists_every_approved_buyer_row(): void
    {
        $rows  = $this->publishedRows();
        $admin = User::factory()->create(['user_type' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.buyerAgentAuctions', ['type' => 1]));
        $response->assertStatus(200);

        $this->assertHolds('admin approved tab', $rows, $response->original->getData()['auctions']->pluck('id'), true);
    }

    /** @test */
    public function the_admin_pending_queue_lists_every_unapproved_buyer_row(): void
    {
        $rows  = $this->publishedRows();
        $admin = User::factory()->create(['user_type' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.buyerAgentAuctions'));
        $response->assertStatus(200);

        $this->assertHolds('admin pending tab', $rows, $response->original->getData()['auctions']->pluck('id'), false);
    }

    // ── I. The agent's bidding list ────────────────────────────────────────

    private function bid(int $auctionId, int $agentId): void
    {
        DB::table('buyer_agent_auction_bids')->insert([
            'buyer_agent_auction_id' => $auctionId,
            'user_id'                => $agentId,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    /**
     * One bid per agent isolates the APPROVAL predicate: with a single id the
     * list's ungrouped `orWhere('id', …)` chain (next test) cannot interfere.
     *
     * @test
     */
    public function the_agent_bidding_list_tabs_classify_each_approval_representation(): void
    {
        foreach ($this->publishedRows() as $label => $id) {
            $agent = User::factory()->create(['user_type' => 'agent']);
            $this->bid($id, $agent->id);

            $approved = array_key_exists($label, self::APPROVED);

            $live    = $this->actingAs($agent)->get(route('buyer.biding.auctions.list', ['type' => 2]))->assertStatus(200)->original->getData();
            $pending = $this->actingAs($agent)->get(route('buyer.biding.auctions.list', ['type' => 1]))->assertStatus(200)->original->getData();

            $this->assertSame($approved, $live['auctions']->pluck('id')->contains($id), "raw {$label}: Live tab");
            $this->assertSame(! $approved, $pending['auctions']->pluck('id')->contains($id), "raw {$label}: Pending tab");
            $this->assertSame($approved ? 1 : 0, $live['liveCount'], "raw {$label}: Live count");
            $this->assertSame($approved ? 0 : 1, $live['pendingApprovalCount'], "raw {$label}: Pending count");
        }
    }

    /**
     * SEPARATE DEFECT, FOUND DURING THIS AUDIT: the list builds its base query as
     * `where('id', a)->orWhere('id', b)…` and then appends the tab filters with no
     * grouping, so SQL's AND-before-OR applies them to the LAST bid's row only.
     * Every other row the agent bid on lands in every tab.
     *
     * @test
     */
    public function the_agent_bidding_list_applies_its_tab_filters_to_every_bid(): void
    {
        $rows  = $this->publishedRows();
        $agent = User::factory()->create(['user_type' => 'agent']);

        // A sold listing in each approved representation, and an approved draft.
        $sold = [];
        foreach (self::APPROVED as $label => $raw) {
            $row = BuyerAgentAuction::create(['user_id' => $this->owner->id, 'title' => "BUYER SOLD {$label}", 'is_draft' => false]);
            DB::table('buyer_agent_auctions')->where('id', $row->id)->update(['is_approved' => $raw, 'is_sold' => '1']);
            $sold[$label] = (int) $row->id;
        }
        $draft = (int) BuyerAgentAuction::create(['user_id' => $this->owner->id, 'title' => 'BUYER DRAFT', 'is_draft' => true])->id;

        foreach ([...array_values($rows), ...array_values($sold), $draft] as $id) {
            $this->bid($id, $agent->id);
        }

        $tab = fn (int $type) => $this->actingAs($agent)->get(route('buyer.biding.auctions.list', ['type' => $type]))->assertStatus(200)->original->getData();

        $live = $tab(2);
        $liveIds = $live['auctions']->pluck('id');
        $this->assertHolds('agent bidding list Live tab', $rows, $liveIds, true);

        $pending = $tab(1);
        $pendingIds = $pending['auctions']->pluck('id');
        $this->assertHolds('agent bidding list Pending tab', $rows, $pendingIds, false);

        $soldTab = $tab(3);
        $soldIds = $soldTab['auctions']->pluck('id');

        foreach ($sold as $label => $id) {
            $this->assertTrue($soldIds->contains($id), "sold raw {$label}: Sold tab");
            $this->assertFalse($liveIds->contains($id), "sold raw {$label}: must not be Live");
            $this->assertFalse($pendingIds->contains($id), "sold raw {$label}: must not be Pending");
        }
        foreach ($rows as $label => $id) {
            $this->assertFalse($soldIds->contains($id), "unsold raw {$label}: must not be Sold");
        }
        foreach ([$liveIds, $pendingIds, $soldIds] as $ids) {
            $this->assertFalse($ids->contains($draft), 'an approved draft is in no tab');
        }

        $this->assertSame(count(self::APPROVED), $live['liveCount'], 'Live count');
        $this->assertSame(count(self::NOT_APPROVED), $live['pendingApprovalCount'], 'Pending count');
        $this->assertSame(count(self::APPROVED), $live['soldCount'], 'Sold count');
    }

    // ── I / H. Public author profiles ──────────────────────────────────────

    /** @test */
    public function a_buyer_author_profile_lists_every_approved_buyer_row(): void
    {
        $rows = $this->publishedRows(null, false);

        $response = $this->get(route('author', ['id' => $this->owner->id]));
        $response->assertStatus(200);

        $this->assertHolds('buyer author profile', $rows, collect($response->original->getData()['pAuctions']->items())->pluck('id'), true);
    }

    /** @test */
    public function a_tenant_author_profiles_buyer_tab_lists_every_approved_row_to_a_visitor(): void
    {
        $tenant = User::factory()->create(['user_type' => 'tenant']);
        $rows   = $this->publishedRows($tenant->id, false);

        $visitor  = User::factory()->create(['user_type' => 'buyer']);
        $response = $this->actingAs($visitor)->get(route('author', ['id' => $tenant->id, 'type' => 2]));
        $response->assertStatus(200);

        $this->assertHolds('tenant author profile, Buyer tab', $rows, collect($response->original->getData()['pAuctions']->items())->pluck('id'), true);
    }

    // ── G. Stellar criteria / Match Check ──────────────────────────────────

    /** @test */
    public function the_stellar_criteria_picker_offers_every_approved_buyer_offer_listing(): void
    {
        $rows = $this->publishedRows();

        $items = collect(app(CriteriaListingResolver::class)->resolveAccessible($this->owner))
            ->where('type', 'buyer_offer')
            ->pluck('id');

        $this->assertHolds('CriteriaListingResolver buyer_offer', $rows, $items, true);
    }

    /** @test */
    public function the_buyer_offer_criteria_loader_finds_an_approved_listing_whatever_its_stored_form(): void
    {
        $loader = app(BuyerOfferListingCriteriaLoader::class);

        foreach (self::APPROVED + self::NOT_APPROVED as $label => $raw) {
            $user = User::factory()->create(['user_type' => 'buyer']);
            $row  = BuyerAgentAuction::create(['user_id' => $user->id, 'title' => "LOADER {$label}", 'is_draft' => false]);
            $row->saveMeta('workflow_type', 'offer_listing');
            DB::table('buyer_agent_auctions')->where('id', $row->id)->update(['is_approved' => $raw, 'is_sold' => '0']);

            $approved = array_key_exists($label, self::APPROVED);

            $this->assertSame($approved, $loader->load((int) $user->id) !== null, "load() raw {$label}");
            $this->assertSame($approved, $loader->loadById((int) $row->id, [(int) $user->id]) !== null, "loadById() raw {$label}");
        }
    }

    // ── Paths already correct: kept correct ────────────────────────────────

    /** @test */
    public function the_paths_that_already_read_every_representation_still_do(): void
    {
        $rows = $this->publishedRows();

        // The Dashboard's Buyer count and the sidebar badge share this predicate.
        $count = BuyerAgentAuction::where('user_id', $this->owner->id)
            ->whereIn('is_approved', ['true', '1', true])->whereIn('is_sold', ['false', '0', false])->where('is_draft', false)->count();
        $this->assertSame(count(self::APPROVED), $count);

        // The public Buyer search page's three-way predicate.
        $search = BuyerAgentAuction::whereIn('id', $rows)->where(function ($q) {
            $q->where('is_approved', 'true')->orWhere('is_approved', '1')->orWhere('is_approved', 1);
        })->pluck('id');
        $this->assertHolds('search predicate', $rows, $search, true);
    }
}
