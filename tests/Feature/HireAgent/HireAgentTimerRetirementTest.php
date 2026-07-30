<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Milestone 3 — retirement of the legacy Hire Agent listing countdown.
 *
 * WHAT WAS WRONG. Each Hire Agent detail view computed its expiry twice over. For a listing whose
 * auction_type was "Bidding Period" or "Auction (Timer)" it SYNTHESISED one from created_at plus
 * an auction_time string ("14 Days"); only a "Traditional" listing used the listing's own
 * expiration_date. The synthesised value drove a live Days/Hrs/Mins/Secs countdown — and then
 * flowed into $isExpired, which gates the Bid button. So on those listings an elapsed countdown,
 * not the listing's status, decided whether an agent could propose. The client-side half went
 * further: onTimerEnd faded the Bid button out of the DOM when the clock hit zero.
 *
 * The two concepts were also wired together in the other direction. Submitting a bid on a Bidding
 * Period listing pushed expiration_date forward by one day, so the seller's stated deadline was
 * partly a function of bidding activity.
 *
 * WHAT THIS FILE ASSERTS. The governing rule is that the Hire Agent timer must never be connected
 * to the listing expiration date. The timer is now gone; expiration_date remains, doing only what
 * it always did for Traditional listings. Concretely:
 *
 *   - no countdown markup, no timer text, no JavaScript countdown on any of the four roles;
 *   - expiration_date is never rendered as a countdown, for any listing type;
 *   - a listing whose retired timer has "elapsed" is NOT expired and still accepts proposals;
 *   - rendering a detail page mutates no listing status;
 *   - proposal availability follows listing status and expiration_date, nothing else.
 *
 * Every absence assertion is paired with a positive control, because "no countdown rendered" is
 * trivially true of a page that failed to render. The controls assert the page really is the
 * listing detail page before concluding anything from what is missing.
 *
 * @see HireAgentTimerExpirationIsolationTest for the static source-level half of the rule.
 */
class HireAgentTimerRetirementTest extends TestCase
{
    use DatabaseTransactions;

    /** Countdown markup the retired timer emitted. */
    private const TIMER_MARKUP = [
        'timer-d', 'timer-h', 'timer-m', 'timer-s',
        'data-expiration',
        'timer.jquery',
    ];

    /** Copy that only a countdown or a bidding period could produce. */
    private const TIMER_COPY = [
        'Bidding Ended',
        'Bidding Period Length',
        'No Time Limit',
        'Public Bid Notice',
    ];

    public function roles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    /** @return array{0: class-string, 1: class-string, 2: string, 3: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   SellerAgentAuctionBid::class,   'seller_agent_auction_id',   'seller.agent.auction.detail'],
            'buyer'    => [BuyerAgentAuction::class,    BuyerAgentAuctionBid::class,    'buyer_agent_auction_id',    'buyer.view-auction'],
            'landlord' => [LandlordAgentAuction::class, LandlordAgentAuctionBid::class, 'landlord_agent_auction_id', 'landlord.agent.auction.view'],
            'tenant'   => [TenantAgentAuction::class,   TenantAgentAuctionBid::class,   'tenant_agent_auction_id',   'tenant.agent.auction.view'],
        };
    }

    /**
     * A listing carrying the FULL legacy timer configuration.
     *
     * auction_type and auction_time are planted deliberately, and auction_time is set to a window
     * that elapsed long ago ("1 Days" against a listing created 30 days back). Before this
     * checkpoint that combination produced a rendered countdown reading "Bidding Ended" and a
     * suppressed Bid button. If any of that machinery survived, these fixtures would still trip
     * it — which is what makes the absence assertions meaningful rather than vacuous.
     *
     * expiration_date is left in the FUTURE, so the listing is genuinely live by its own
     * lifecycle field. Timer-says-ended vs expiration-says-live is precisely the case that
     * separates the two concepts.
     *
     * CLAUDE.md schema asymmetry: `address` is native on seller/buyer, EAV meta on
     * landlord/tenant; the Landlord/Tenant models guard mass assignment, hence forceCreate.
     */
    private function makeListing(string $role, int $ownerId, string $expirationDate, string $auctionType = 'Bidding Period'): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' hire-agent listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Test Street';
        }

        $listing = $auctionClass::forceCreate($attributes);
        $listing->created_at = now()->subDays(30);
        $listing->save();

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Test Street');
        }

        // The retired timer's inputs. Kept in the fixture on purpose.
        $listing->saveMeta('auction_type', $auctionType);
        $listing->saveMeta('auction_time', '1 Days');
        $listing->saveMeta('expiration_date', $expirationDate);

        return $listing->fresh();
    }

    private function makeBid(string $role, int $listingId, int $userId): Model
    {
        [, $bidClass, $fk] = $this->wiringFor($role);

        $bid = $bidClass::forceCreate([$fk => $listingId, 'user_id' => $userId]);

        if (in_array($role, ['seller', 'buyer'], true)) {
            $bid->brokerage = '250.00';
            $bid->save();
        } else {
            $bid->saveMeta('purchase_fee_type', 'Flat Fee');
            $bid->saveMeta('purchase_fee_flat', '250.00');
        }

        return $bid;
    }

    private function urlFor(string $role, int $listingId): string
    {
        [, , , $routeName] = $this->wiringFor($role);

        return route($routeName, $listingId);
    }

    /**
     * Positive control: this really is the detail page for the listing under test, so the
     * absence assertions mean something.
     *
     * Asserted through viewData('auction') rather than by looking for the address in the HTML —
     * the four roles render address fields differently and a text probe would silently become a
     * no-op on whichever role stopped printing it, which is exactly the failure mode a control
     * exists to prevent.
     */
    private function assertIsDetailPage($response, int $listingId): void
    {
        $response->assertOk();

        $served = $response->viewData('auction');

        $this->assertNotNull($served, 'The detail page must hand the view an $auction.');
        $this->assertSame(
            $listingId,
            (int) $served->id,
            'Control: the page under test must be the detail page for the listing we built.'
        );
    }

    // ── 1-3: no countdown markup, no timer text, no JS countdown ─────────────

    /**
     * @dataProvider roles
     */
    public function test_detail_page_renders_no_countdown_markup(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        foreach (self::TIMER_MARKUP as $needle) {
            $response->assertDontSee($needle, false);
        }
    }

    /**
     * @dataProvider roles
     */
    public function test_detail_page_renders_no_timer_text(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        foreach (self::TIMER_COPY as $needle) {
            $response->assertDontSee($needle, false);
        }

        // The unit labels the countdown rendered beside its digits.
        $body = $response->getContent();
        foreach (['>Days<', '>Hrs<', '>Mins<', '>Secs<'] as $label) {
            $this->assertStringNotContainsString($label, $body, "Countdown unit label {$label} must not render.");
        }
    }

    /**
     * @dataProvider roles
     */
    public function test_detail_page_initializes_no_javascript_countdown(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $body = $response->getContent();
        foreach (['countdown: true', 'onTimerEnd', '.timer(', 'secondsToTimerString', 'formatCountdown'] as $js) {
            $this->assertStringNotContainsString($js, $body, "JavaScript countdown hook [{$js}] must not be emitted.");
        }
    }

    // ── 4: expiration_date is not turned into a visible timer ────────────────

    /**
     * The expiry date may be shown as a DATE. What must never appear is that date rendered as
     * time remaining. A listing expiring in exactly 7 days is the sharp case: if anything were
     * still converting expiration_date into a countdown, "7" would surface next to a Days label.
     *
     * @dataProvider roles
     */
    public function test_expiration_date_is_not_rendered_as_a_countdown(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(7)->toDateTimeString(), 'Traditional');

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $body = $response->getContent();
        foreach (['>Days<', '>Hrs<', '>Mins<', '>Secs<', 'timer-d', 'data-expiration'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $body,
                'expiration_date must not be re-expressed as remaining time.'
            );
        }
    }

    // ── 5: an elapsed timer changes nothing ──────────────────────────────────

    /**
     * The fixture's auction_time elapsed 29 days ago while expiration_date is 10 days out.
     * Pre-retirement this rendered as expired and withdrew the Bid button. Now the listing is
     * live, because only expiration_date decides that.
     *
     * @dataProvider roles
     */
    public function test_elapsed_legacy_timer_does_not_expire_a_live_listing(string $role): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $response = $this->actingAs($agent)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $response->assertDontSee('This listing has expired', false);
        $response->assertDontSee('Bidding Ended', false);
    }

    /**
     * Timer completion must not mutate listing status. The controllers used to call
     * autoTransitionBpToPending() on every detail render; the listing below has a long-elapsed
     * timer, so if any such transition survived, a GET would be enough to trigger it.
     *
     * @dataProvider roles
     */
    public function test_rendering_a_detail_page_does_not_mutate_listing_status(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $before = [
            'is_sold'     => $listing->is_sold,
            'is_draft'    => $listing->is_draft,
            'is_approved' => $listing->is_approved,
            'status'      => $listing->status,
        ];

        $this->actingAs($owner)->get($this->urlFor($role, $listing->id))->assertOk();

        $after = $listing->fresh();

        $this->assertSame($before['is_sold'], $after->is_sold);
        $this->assertSame($before['is_draft'], $after->is_draft);
        $this->assertSame($before['is_approved'], $after->is_approved);
        $this->assertSame($before['status'], $after->status, 'A page view must never change listing status.');
        $this->assertNotSame('Pending', $after->status, 'An elapsed legacy timer must not auto-Pend a listing.');
    }

    // ── 6: proposal availability follows listing status ──────────────────────

    /**
     * Live listing, long-elapsed legacy timer: the agent can still propose.
     *
     * @dataProvider roles
     */
    public function test_agent_can_still_propose_when_only_the_legacy_timer_elapsed(string $role): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $response = $this->actingAs($agent)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $response->assertSee('Bid Now', false);
    }

    /**
     * Genuinely past its expiration_date: no proposal. Same page, same viewer, one field
     * different — which is the control proving the assertion above is not just "the button is
     * always there".
     *
     * @dataProvider roles
     */
    public function test_agent_cannot_propose_once_the_listing_expiration_date_has_passed(string $role): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->subDays(3)->toDateTimeString());

        $response = $this->actingAs($agent)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $response->assertDontSee('Bid Now', false);
        $response->assertSee('This listing has expired', false);
    }

    /**
     * A Pending listing takes no proposals — status, not elapsed time.
     *
     * @dataProvider roles
     */
    public function test_pending_listing_takes_no_proposals(string $role): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());
        $listing->saveMeta('listing_status', 'Pending');

        $response = $this->actingAs($agent)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $response->assertDontSee('Bid Now', false);
    }

    // ── 7-9: Milestone 2 privacy guarantees still hold ───────────────────────

    /**
     * @dataProvider roles
     */
    public function test_owner_still_reviews_every_proposal(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $rival   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $a = $this->makeBid($role, $listing->id, $mine->id);
        $b = $this->makeBid($role, $listing->id, $rival->id);

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $response->assertOk();

        $served = $response->viewData('auction')->bids->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$a->id, $b->id])->sort()->values()->all(),
            $served,
            'Retiring the timer must not narrow owner proposal review.'
        );
    }

    /**
     * @dataProvider roles
     */
    public function test_submitting_agent_still_sees_their_own_proposal(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $myBid = $this->makeBid($role, $listing->id, $mine->id);

        $response = $this->actingAs($mine)->get($this->urlFor($role, $listing->id));
        $response->assertOk();

        $this->assertSame(
            [$myBid->id],
            $response->viewData('auction')->bids->pluck('id')->all(),
            'An agent must still be served their own proposal.'
        );
    }

    /**
     * @dataProvider roles
     */
    public function test_competing_agent_still_cannot_see_another_proposal(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $rival   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id, now()->addDays(10)->toDateTimeString());

        $myBid    = $this->makeBid($role, $listing->id, $mine->id);
        $rivalBid = $this->makeBid($role, $listing->id, $rival->id);

        $response = $this->actingAs($mine)->get($this->urlFor($role, $listing->id));
        $response->assertOk();

        $served = $response->viewData('auction')->bids->pluck('id')->all();

        $this->assertContains($myBid->id, $served, 'Positive control: the viewer sees their own proposal.');
        $this->assertNotContains(
            $rivalBid->id,
            $served,
            'A competing proposal must remain invisible — retiring the timer must not reopen it.'
        );
    }
}
