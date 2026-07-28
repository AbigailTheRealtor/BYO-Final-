<?php

namespace Tests\Feature\Offers;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression lock for the PostgreSQL bigint/text defect in the ending_soon sort.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS SUITE EXISTS
 * ---------------------------------------------------------------------------
 * The ending_soon sort joins offer_auctions.id (bigint) to the listing's
 * linked_offer_auction_id, which is an EAV meta_value and therefore text.
 * PostgreSQL has no implicit text/bigint comparison and rejects the query
 * outright:
 *
 *     SQLSTATE[42883]: operator does not exist: bigint = text
 *
 * SQLite — the engine this entire test suite runs on — is dynamically typed and
 * compares the two happily. The uncast version therefore passed every test and
 * 500'd on the first real request in production. A conventional feature test
 * CANNOT catch this class of defect on the configured harness.
 *
 * The tests below are written to fail on SQLite anyway, by asserting on the
 * SHAPE of the generated SQL rather than on its behaviour, plus a static guard
 * over the source tree and a genuine PostgreSQL execution test that runs when a
 * pgsql connection is reachable.
 *
 * If any test here fails, the sort is one deploy away from 500ing again.
 */
class EndingSoonPostgresCastTest extends TestCase
{
    use DatabaseTransactions;

    private BiddingWindowService $service;

    /** Every role that carries a bidding window. */
    private const ROLES = ['seller', 'landlord', 'buyer', 'tenant'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BiddingWindowService::class);
    }

    // =====================================================================
    // 1. The cast must be present, for every role.
    //    This is the assertion that would have caught the shipped defect.
    // =====================================================================

    public function test_ends_at_subquery_casts_the_bigint_side_for_every_role(): void
    {
        foreach (self::ROLES as $role) {
            $sql = $this->service->endsAtSubquery($role);

            $this->assertStringContainsString(
                'CAST(oa.id AS TEXT)',
                $sql,
                "[{$role}] offer_auctions.id must be cast to text before comparison with the EAV "
                . 'meta_value. Without it PostgreSQL raises SQLSTATE 42883 while SQLite silently passes.'
            );
        }
    }

    /**
     * The bigint side must never be compared raw.
     *
     * Guards the exact regression: `WHERE oa.id = (SELECT m.meta_value ...)`.
     */
    public function test_ends_at_subquery_never_compares_oa_id_uncast(): void
    {
        foreach (self::ROLES as $role) {
            $sql = $this->service->endsAtSubquery($role);

            $this->assertDoesNotMatchRegularExpression(
                '/\boa\.id\s*=/',
                $sql,
                "[{$role}] found a bare `oa.id =` comparison. The bigint side must be wrapped in CAST(... AS TEXT)."
            );
        }
    }

    /**
     * The cast direction is load-bearing and must not be flipped.
     *
     * Casting meta_value to bigint would "work" until one malformed or empty EAV
     * value aborted the entire query under PostgreSQL. EAV values are free-form
     * and user-reachable; offer_auctions.id is not. Widen the safe side.
     */
    public function test_meta_value_is_never_cast_to_an_integer_type(): void
    {
        foreach (self::ROLES as $role) {
            $sql = $this->service->endsAtSubquery($role);

            $this->assertDoesNotMatchRegularExpression(
                '/CAST\s*\(\s*m\.meta_value\s+AS\s+(BIG)?INT/i',
                $sql,
                "[{$role}] meta_value must not be cast to an integer type — a single non-numeric EAV "
                . 'value would abort the whole query on PostgreSQL.'
            );
        }
    }

    // =====================================================================
    // 2. Canonical timer invariants survive the fix.
    //    The deadline is READ, never derived. Invariants 1, 2, 3, 5, 6, 10.
    // =====================================================================

    public function test_subquery_reads_only_the_stored_canonical_deadline(): void
    {
        foreach (self::ROLES as $role) {
            $sql = $this->service->endsAtSubquery($role);

            $this->assertStringContainsString(
                'oa.bidding_ends_at',
                $sql,
                "[{$role}] the sort must read the stored canonical deadline."
            );

            foreach (['expiration_date', 'auction_time', 'created_at', 'bidding_starts_at'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $sql,
                    "[{$role}] the deadline subquery must never reference {$forbidden} — the deadline is "
                    . 'stored data, not a calculation or a fallback.'
                );
            }
        }
    }

    // =====================================================================
    // 3. Static guard over the source tree.
    //    Catches a future re-introduction anywhere, not just behind the API.
    // =====================================================================

    public function test_no_uncast_offer_auction_join_remains_in_the_source_tree(): void
    {
        $roots = [app_path('Http/Controllers'), app_path('Services'), app_path('Http/Livewire')];

        $offenders = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                // A raw comparison of the offer_auctions alias against an EAV
                // meta_value, with no CAST in between.
                if (preg_match('/\boa\.id\s*=\s*\(\s*SELECT\s+m\.meta_value/i', $contents)) {
                    $offenders[] = str_replace(base_path() . '/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Uncast bigint = text comparison(s) found. PostgreSQL rejects these with SQLSTATE 42883 "
            . "while SQLite passes:\n  " . implode("\n  ", $offenders)
            . "\nUse BiddingWindowService::endsAtSubquery() instead of hand-rolling the join."
        );
    }

    // =====================================================================
    // 4. The ordering executes, and puts uninitialized listings last.
    // =====================================================================

    public function test_ending_soon_order_executes_for_every_role(): void
    {
        $models = [
            'seller'   => SellerAgentAuction::class,
            'landlord' => LandlordAgentAuction::class,
            'buyer'    => BuyerAgentAuction::class,
            'tenant'   => TenantAgentAuction::class,
        ];

        foreach ($models as $role => $model) {
            $query = $model::query();
            $this->service->applyEndingSoonOrder($query, $role);

            $this->assertNotNull(
                $query->limit(1)->get(),
                "[{$role}] the ending_soon ordering must execute without error."
            );
        }
    }

    public function test_uninitialized_listings_sort_last_and_are_not_given_a_deadline(): void
    {
        $soon    = $this->sellerWithWindow(CarbonImmutable::now()->addDay());
        $later   = $this->sellerWithWindow(CarbonImmutable::now()->addDays(9));
        $unknown = $this->seller();   // Bidding Period, never stamped.

        $query = SellerAgentAuction::query()
            ->whereIn('seller_agent_auctions.id', [$soon->id, $later->id, $unknown->id]);

        $this->service->applyEndingSoonOrder($query, 'seller');

        $ordered = $query->pluck('seller_agent_auctions.id')->all();

        $this->assertSame(
            [$soon->id, $later->id, $unknown->id],
            $ordered,
            'Soonest canonical deadline first; a listing with no stored window sorts LAST rather '
            . 'than being handed a synthetic deadline.'
        );
    }

    public function test_expiration_date_does_not_influence_the_ordering(): void
    {
        // The listing expiring first has the LATER bidding deadline. If the sort
        // ever consulted expiration_date the order would invert.
        $lateWindow = $this->sellerWithWindow(CarbonImmutable::now()->addDays(9));
        $lateWindow->saveMeta('expiration_date', CarbonImmutable::now()->addDay()->toDateString());

        $earlyWindow = $this->sellerWithWindow(CarbonImmutable::now()->addDay());
        $earlyWindow->saveMeta('expiration_date', CarbonImmutable::now()->addDays(90)->toDateString());

        $query = SellerAgentAuction::query()
            ->whereIn('seller_agent_auctions.id', [$lateWindow->id, $earlyWindow->id]);

        $this->service->applyEndingSoonOrder($query, 'seller');

        $this->assertSame(
            [$earlyWindow->id, $lateWindow->id],
            $query->pluck('seller_agent_auctions.id')->all(),
            'Ordering must follow bidding_ends_at only. Listing expiration is a separate, '
            . 'permanently independent business concept (Invariants 1, 2, 10).'
        );
    }

    // =====================================================================
    // 5. Genuine PostgreSQL execution, when a pgsql connection is reachable.
    //    Skips rather than fails on the default SQLite harness.
    // =====================================================================

    public function test_ending_soon_sort_executes_against_postgresql(): void
    {
        $this->skipUnlessPostgresIsReachable();

        foreach (self::ROLES as $role) {
            $sql = 'SELECT 1 AS ok WHERE ' . $this->service->endsAtSubquery($role) . ' IS NULL';

            // Correlated against the listing table, so wrap it in a scan of one row.
            [$listingTable] = $this->tablesFor($role);
            $sql = "SELECT (" . $this->service->endsAtSubquery($role) . ") AS ends_at
                    FROM {$listingTable} LIMIT 1";

            try {
                DB::connection('pgsql')->select($sql);
            } catch (\Throwable $e) {
                $this->fail(
                    "[{$role}] ending_soon subquery failed on PostgreSQL: "
                    . explode(PHP_EOL, $e->getMessage())[0]
                );
            }
        }

        $this->assertTrue(true, 'All four ending_soon subqueries executed on PostgreSQL.');
    }

    /**
     * The defect itself, reproduced on PostgreSQL.
     *
     * Proves the guard above is actually load-bearing: the uncast form must
     * still be rejected by the engine. If this ever stops raising, PostgreSQL
     * gained an implicit cast and the whole suite can be revisited.
     */
    public function test_uncast_comparison_is_still_rejected_by_postgresql(): void
    {
        $this->skipUnlessPostgresIsReachable();

        $uncast = "SELECT (SELECT oa.bidding_ends_at FROM offer_auctions oa
                           WHERE oa.id = (SELECT m.meta_value FROM seller_agent_auction_metas m
                                          WHERE m.seller_agent_auction_id = seller_agent_auctions.id
                                            AND m.meta_key = 'linked_offer_auction_id'
                                          LIMIT 1)) AS ends_at
                   FROM seller_agent_auctions LIMIT 1";

        try {
            DB::connection('pgsql')->select($uncast);
            $this->fail('PostgreSQL accepted an uncast bigint = text comparison. Re-evaluate this suite.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString(
                '42883',
                $e->getMessage(),
                'Expected PostgreSQL to reject bigint = text with SQLSTATE 42883.'
            );
        }
    }

    // =====================================================================
    // 6. API guard.
    // =====================================================================

    public function test_unknown_role_is_rejected_rather_than_spliced_into_sql(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->endsAtSubquery('seller_agent_auctions; DROP TABLE users');
    }

    // ------------------------------------------------------------- helpers

    private function tablesFor(string $role): array
    {
        return [
            'seller'   => ['seller_agent_auctions'],
            'landlord' => ['landlord_agent_auctions'],
            'buyer'    => ['buyer_agent_auctions'],
            'tenant'   => ['tenant_agent_auctions'],
        ][$role];
    }

    private function skipUnlessPostgresIsReachable(): void
    {
        if (! config('database.connections.pgsql')) {
            $this->markTestSkipped('No pgsql connection configured.');
        }

        try {
            DB::connection('pgsql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('pgsql connection not reachable from the test environment.');
        }

        if (! \Illuminate\Support\Facades\Schema::connection('pgsql')->hasColumn('offer_auctions', 'bidding_ends_at')) {
            $this->markTestSkipped('pgsql database has not run the canonical window migration.');
        }
    }

    private function seller(array $meta = []): SellerAgentAuction
    {
        $user    = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id'  => $user->id,
            'title'    => 'Ending Soon Fixture',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        $listing->saveMeta('auction_type', 'Bidding Period');

        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh('meta');
    }

    private function sellerWithWindow(CarbonImmutable $endsAt): SellerAgentAuction
    {
        $listing = $this->seller();

        $offerAuction = OfferAuction::create([
            'user_id'           => $listing->user_id,
            'title'             => 'Linked OfferAuction',
            'is_draft'          => false,
            'bidding_starts_at' => CarbonImmutable::now()->subDay(),
            'bidding_ends_at'   => $endsAt,
        ]);

        $listing->saveMeta('linked_offer_auction_id', (string) $offerAuction->id);

        return $listing->fresh('meta');
    }
}
