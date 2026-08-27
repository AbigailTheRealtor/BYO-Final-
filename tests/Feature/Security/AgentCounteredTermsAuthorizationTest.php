<?php

namespace Tests\Feature\Security;

use App\Models\AgentCounterTerm;
use App\Models\AgentServiceAuction;
use App\Models\AgentServiceAuctionBid;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * N1 — AgentCounteredTermsController store/update authorization (IDOR) regression.
 *
 * During the HIGH-5 sweep the agent variant was intentionally left un-hardened
 * (its `agent_counter_terms` table had no migration and it already sits behind
 * `agentAuth`). N1 revisited that decision (approved Option 1) and closed the
 * attack surface while the table was still inert. The guard added to each method
 * mirrors BuyerCounteredTermsController exactly:
 *
 *     abort_unless(auth()->check() && $auction && (
 *         (int) $auction->user_id === (int) auth()->id() ||        // auction owner
 *         AgentServiceAuctionBid::where(...auction...)
 *             ->where('user_id', auth()->id())->exists()           // bidding agent
 *     ), 403);
 *
 * Route middleware stack is `web → auth → verified → agentAuth`, so guests and
 * non-agents are already turned away (302) upstream. The IDOR surface that reaches
 * the controller is therefore a *verified agent who is not a party* to the auction
 * — which is exactly what these tests exercise.
 *
 * The security-critical assertion is the FORBIDDEN path (non-party agent -> 403),
 * which aborts before any DB write and is independent of the legacy insert/update
 * column logic (frozen under OFFER_SYSTEM_DO_NOT_TOUCH and out of scope).
 *
 * UPDATE — 2026_08_27_000001_create_agent_counter_terms_table.
 * The table now exists, which changes what this file can assert. Previously the
 * legitimate-party test could only check "not 403", because the insert 500'd on the
 * missing table — a 500 is not a 403, so it passed while the feature was entirely
 * broken. And `test_agent_update_blocks_non_party_agent` was gated on
 * Schema::hasTable() and therefore always skipped; it was the security gate's single
 * standing skip. Both now execute against a real row, and the denial tests assert the
 * victim row is unchanged rather than merely that a 403 came back.
 */
class AgentCounteredTermsAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Authorization is what is under test, not CSRF token state.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /** Skip DB-backed tests when the isolated SQLite test DB is unavailable. */
    private function requireIsolatedDb(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped(
                'Isolated SQLite test DB unavailable in this environment ' .
                '(pre-existing harness issue — the wider suite is affected too). ' .
                'N1 ownership logic is verified by code review + browser testing; ' .
                'this test is CI-ready against a working SQLite DB.'
            );
        }
    }

    // =====================================================================
    // store() — a non-party agent must NOT be able to submit counter terms
    // =====================================================================

    public function test_agent_store_blocks_non_party_agent(): void
    {
        $this->requireIsolatedDb();
        $owner    = User::factory()->asAgent()->create();
        $attacker = User::factory()->asAgent()->create();
        $auction  = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);

        // A verified agent who neither owns nor has bid on the auction is the
        // IDOR actor that clears agentAuth but must be rejected by the guard.
        $this->actingAs($attacker)
            ->post('add-counter-terms', ['agentId' => $auction->id])
            ->assertForbidden();
    }

    // =====================================================================
    // store() — a legitimate party (owner / bidding agent) is NOT blocked,
    // and the write now actually LANDS.
    //
    // This assertion used to be "not 403" only, because `agent_counter_terms`
    // had no migration and the insert 500'd. That made it a false green: a 500
    // is not a 403, so the test passed while the feature was completely broken.
    // 2026_08_27_000001_create_agent_counter_terms_table creates the table, so
    // the row is asserted directly.
    // =====================================================================

    public function test_agent_store_allows_owner_and_bidding_agent(): void
    {
        $this->requireIsolatedDb();
        $owner   = User::factory()->asAgent()->create();
        $bidder  = User::factory()->asAgent()->create();
        $auction = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);
        AgentServiceAuctionBid::forceCreate([
            'agent_service_auction_id' => $auction->id,
            'user_id' => $bidder->id,
        ]);

        $ownerStatus = $this->actingAs($owner)
            ->post('add-counter-terms', $this->payload($auction, 'OWNER-COUNTER'))->status();
        $this->assertNotSame(403, $ownerStatus, 'Auction owner must pass the N1 guard');
        $this->assertLessThan(400, $ownerStatus, 'Auction owner submit must not error');

        $bidderStatus = $this->actingAs($bidder)
            ->post('add-counter-terms', $this->payload($auction, 'BIDDER-COUNTER'))->status();
        $this->assertNotSame(403, $bidderStatus, 'Bidding agent (party) must pass the N1 guard');
        $this->assertLessThan(400, $bidderStatus, 'Bidding agent submit must not error');

        $this->assertSame(2, AgentCounterTerm::where('agent_auction_id', $auction->id)->count());
    }

    /**
     * The legitimate write persists the values the form submits, against the
     * correct auction. This is the path that was unreachable before the table
     * existed — it is the whole point of the repair.
     */
    public function test_a_legitimate_agent_counter_term_persists_its_values(): void
    {
        $this->requireIsolatedDb();
        $owner   = User::factory()->asAgent()->create();
        $auction = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post('add-counter-terms', $this->payload($auction, 'LEGITIMATE-AGENT-COUNTER'));

        $row = AgentCounterTerm::where('agent_auction_id', $auction->id)->latest('id')->first();

        $this->assertNotNull($row, 'The agent counter term did not persist.');
        $this->assertSame((int) $auction->id, (int) $row->agent_auction_id);
        $this->assertSame('30 days', $row->timeframe);
        $this->assertSame('Yes', $row->agentCharge);
        $this->assertSame('LEGITIMATE-AGENT-COUNTER', $row->additionalDetails);
        $this->assertSame(1, (int) $row->status);
        // services arrives as services[] and is stored json_encode()d.
        $this->assertSame(['List the listing'], json_decode($row->services, true));
    }

    /** A counter term for an auction that does not exist must fail closed. */
    public function test_store_fails_closed_for_a_nonexistent_auction(): void
    {
        $this->requireIsolatedDb();
        $agent = User::factory()->asAgent()->create();

        $this->actingAs($agent)
            ->post('add-counter-terms', ['agentId' => 999999])
            ->assertForbidden();

        $this->assertSame(0, AgentCounterTerm::count());
    }

    // =====================================================================
    // update() — a non-party agent must NOT be able to overwrite counter terms.
    //
    // This assertion was the security gate's single standing SKIP: it was gated
    // on Schema::hasTable('agent_counter_terms'), which was never true because no
    // migration created the table. The table now exists, so it runs.
    // =====================================================================

    public function test_agent_update_blocks_non_party_agent(): void
    {
        $this->requireIsolatedDb();

        $owner    = User::factory()->asAgent()->create();
        $attacker = User::factory()->asAgent()->create();
        $auction  = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);
        $counter  = AgentCounterTerm::forceCreate([
            'agent_auction_id'  => $auction->id,
            'timeframe'         => 'ORIGINAL',
            'additionalDetails' => 'ORIGINAL-TERMS',
            'status'            => 1,
        ]);

        $this->actingAs($attacker)
            ->post('update-counter-terms/' . $counter->id, ['timeframe' => 'HIJACKED'])
            ->assertForbidden();

        // The refusal must also have written nothing.
        $counter->refresh();
        $this->assertSame('ORIGINAL', $counter->timeframe);
        $this->assertSame('ORIGINAL-TERMS', $counter->additionalDetails);

        $ownerStatus = $this->actingAs($owner)
            ->post('update-counter-terms/' . $counter->id)->status();
        $this->assertNotSame(403, $ownerStatus, 'Auction owner must pass the N1 guard');
    }

    /** The legitimate update edits the row in place rather than creating a second one. */
    public function test_a_legitimate_update_edits_in_place(): void
    {
        $this->requireIsolatedDb();
        $owner   = User::factory()->asAgent()->create();
        $auction = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);
        $counter = AgentCounterTerm::forceCreate([
            'agent_auction_id'  => $auction->id,
            'timeframe'         => 'ORIGINAL',
            'additionalDetails' => 'ORIGINAL-TERMS',
            'status'            => 1,
        ]);

        $this->actingAs($owner)
            ->post('update-counter-terms/' . $counter->id, [
                'timeframe'         => 'REVISED',
                'agentCharge'       => 'Yes',
                'agentFee'          => 'Yes',
                'additionalDetails' => 'REVISED-TERMS',
            ]);

        $counter->refresh();
        $this->assertSame('REVISED', $counter->timeframe);
        $this->assertSame('REVISED-TERMS', $counter->additionalDetails);
        $this->assertSame(
            1,
            AgentCounterTerm::where('agent_auction_id', $auction->id)->count(),
            'The update created a second row instead of editing in place.'
        );
    }

    /** The form's field names, as posted by resources/views/agent_counter_terms/add.blade.php. */
    private function payload(AgentServiceAuction $auction, string $details): array
    {
        return [
            'agentId'           => $auction->id,
            'timeframe'         => '30 days',
            'agentFee'          => 'Yes',
            'agentFeeOther'     => '',
            'agentCharge'       => 'Yes',
            'agentChargeOther'  => '',
            'services'          => ['List the listing'],
            'other_services'    => 'none',
            'additionalDetails' => $details,
        ];
    }
}
