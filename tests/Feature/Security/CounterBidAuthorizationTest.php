<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\BuyerBidderAuth;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\PropertyAuction;
use App\Models\PropertyAuctionBid;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * HI-02 — Counter-bid accept/reject/delete IDOR and debug-code removal.
 *
 * PropertyAuctionBidController::{acceptPABid,rejectPABid,destroy} previously
 * trusted the request-supplied bid_id / auction_id / route {id} with no
 * ownership check, so any authenticated bidder could accept, reject, or delete
 * a bid on any auction. rejectPABid also terminated the request with a raw
 * debug dump on failure.
 *
 * Authorization is now derived from the persisted models:
 *   - accept / reject: the auction OWNER only, and the bid must belong to the
 *     named auction;
 *   - delete (destroyCounter): party guard — the auction owner OR the bid's own
 *     author.
 *
 * The route middleware (BuyerBidderAuth) and CSRF are disabled here so the
 * tests exercise the CONTROLLER's own authorization, independent of the
 * persona gate in front of it.
 */
class CounterBidAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([VerifyCsrfToken::class, BuyerBidderAuth::class]);

        // The repo ships no migration for property_auction_bid_metas, yet the
        // PropertyAuctionBid model eager-loads its `meta` relation on every fetch.
        // Create a minimal stand-in so the controller can load a bid. Runs inside
        // the DatabaseTransactions wrapper, so it is rolled back after each test.
        if (! Schema::hasTable('property_auction_bid_metas')) {
            Schema::create('property_auction_bid_metas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('property_auction_bid_id')->index();
                $t->string('meta_key')->nullable();
                $t->longText('meta_value')->nullable();
                $t->timestamps();
            });
        }
    }

    /**
     * Build a property_auctions row. The model defaults is_draft/is_approved/sold
     * (production columns absent from the test schema), so the phantom defaults
     * are cleared before filling only the real, required columns.
     */
    private function makeAuction(int $userId): PropertyAuction
    {
        $a = new PropertyAuction();
        $a->setRawAttributes([]);
        $a->forceFill([
            'user_id'      => $userId,
            'title'        => 'Test Auction',
            'address'      => '123 Test St',
            'city_id'      => 1,
            'state_id'     => 1,
            'auction_type' => 'Normal',
        ]);
        $a->save(); // HasListingId sets listing_id on creating
        return $a;
    }

    private function makeBid(int $userId, int $auctionId): PropertyAuctionBid
    {
        $b = new PropertyAuctionBid();
        $b->forceFill([
            'user_id'             => $userId,
            'property_auction_id' => $auctionId,
            'financing_id'        => 1,
            'price'               => 100000,
        ]);
        $b->save();
        return $b;
    }

    private function scenario(): array
    {
        $owner    = User::factory()->create();   // auction owner (seller side)
        $bidder   = User::factory()->create();   // placed the bid
        $attacker = User::factory()->create();   // unrelated third party

        $auction = $this->makeAuction($owner->id);
        $bid     = $this->makeBid($bidder->id, $auction->id);

        return compact('owner', 'bidder', 'attacker', 'auction', 'bid');
    }

    // ── accept ──────────────────────────────────────────────────────────
    public function test_unrelated_user_cannot_accept_bid(): void
    {
        ['attacker' => $attacker, 'auction' => $auction, 'bid' => $bid] = $this->scenario();

        $this->actingAs($attacker)
            ->post('/property/listing/accept-pa-bid', [
                'bid_id'       => $bid->id,
                'auction_id'   => $auction->id,
                'counterPrice' => 100000,
            ])
            ->assertForbidden();
    }

    public function test_bid_from_another_auction_cannot_be_accepted(): void
    {
        ['owner' => $owner, 'bid' => $bid] = $this->scenario();
        // A second auction the owner controls; the bid belongs to the FIRST one.
        $otherAuction = $this->makeAuction($owner->id);

        $this->actingAs($owner)
            ->post('/property/listing/accept-pa-bid', [
                'bid_id'       => $bid->id,
                'auction_id'   => $otherAuction->id,
                'counterPrice' => 100000,
            ])
            ->assertNotFound();
    }

    // ── reject ──────────────────────────────────────────────────────────
    public function test_unrelated_user_cannot_reject_bid(): void
    {
        ['attacker' => $attacker, 'auction' => $auction, 'bid' => $bid] = $this->scenario();

        $this->actingAs($attacker)
            ->post('/property/listing/reject-pa-bid', [
                'bid_id'     => $bid->id,
                'auction_id' => $auction->id,
            ])
            ->assertForbidden();

        $this->assertNotSame('rejected', $bid->fresh()->accepted);
    }

    public function test_owner_can_reject_bid(): void
    {
        ['owner' => $owner, 'auction' => $auction, 'bid' => $bid] = $this->scenario();

        $this->actingAs($owner)
            ->post('/property/listing/reject-pa-bid', [
                'bid_id'     => $bid->id,
                'auction_id' => $auction->id,
            ])
            ->assertStatus(302);

        $this->assertSame('rejected', $bid->fresh()->accepted);
    }

    // ── delete (destroyCounter) ─────────────────────────────────────────
    public function test_unrelated_user_cannot_delete_counter_bid(): void
    {
        ['attacker' => $attacker, 'bid' => $bid] = $this->scenario();

        $this->actingAs($attacker)
            ->post('/property/listing/' . $bid->id)
            ->assertForbidden();

        $this->assertNotNull(PropertyAuctionBid::find($bid->id));
    }

    public function test_owner_can_delete_counter_bid(): void
    {
        ['owner' => $owner, 'bid' => $bid] = $this->scenario();

        $this->actingAs($owner)
            ->post('/property/listing/' . $bid->id)
            ->assertStatus(302);

        $this->assertNull(PropertyAuctionBid::find($bid->id));
    }

    public function test_bidder_can_delete_own_counter_bid(): void
    {
        ['bidder' => $bidder, 'bid' => $bid] = $this->scenario();

        $this->actingAs($bidder)
            ->post('/property/listing/' . $bid->id)
            ->assertStatus(302);

        $this->assertNull(PropertyAuctionBid::find($bid->id));
    }

    // ── debug-code removal ──────────────────────────────────────────────
    public function test_controller_has_no_live_debug_termination(): void
    {
        // Runtime-triggering the failure catch (a DB exception inside update())
        // is impractical to force deterministically, so a targeted source scan is
        // the reliable guard that no live debug-dump termination was
        // reintroduced. Comment lines are skipped so a historical commented-out
        // debug call does not register as a live one.
        $lines = file(app_path('Http/Controllers/PropertyAuctionBidController.php'));
        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/\bdd\s*\(/', $line, 'live dd on line ' . ($i + 1));
            $this->assertDoesNotMatchRegularExpression('/\bvar_dump\s*\(/', $line, 'live var_dump on line ' . ($i + 1));
        }
    }
}
