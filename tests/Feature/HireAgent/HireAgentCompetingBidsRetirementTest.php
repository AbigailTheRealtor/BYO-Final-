<?php

namespace Tests\Feature\HireAgent;

use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Milestone 2, second checkpoint — retirement of the legacy Hire Agent competing-bids surfaces.
 *
 * The first checkpoint stopped the four Hire detail views from calling the legacy stack but left
 * the stack itself standing and routable: an agent who knew the URL could still GET
 * `tenant/agent/auction/{id}/competing-bids` and be served anonymised summaries of every rival
 * proposal, and `.../competing-bids/data` returned the same payload as JSON. Hiding a surface in
 * Blade is not the same as removing it, and this file is the difference.
 *
 * HireAgentDetailViewPrivacyTest asserts the components are structurally gone (no class, no view,
 * no route registration). That is necessary but not sufficient — a deleted controller with a
 * surviving catch-all or a helpfully-added redirect would still satisfy it. What is asserted here
 * is REACHABILITY: the two URLs resolve to nothing, for every category of viewer.
 *
 * WHY 404 AND NOT A REDIRECT. Sending a competing agent to the listing detail page would be a
 * disclosure in itself — it confirms the listing exists, that it has a competing-bids concept,
 * and (for a guessed id) that the id is real. A 404 confirms nothing. The assertions below reject
 * 3xx explicitly rather than merely accepting "not 200", because a redirect is the specific
 * plausible regression here, and `assertStatus(404)` alone would pass on a 500 too.
 *
 * @see docs/investigations/hire-agent-listing-framework-implementation-plan.md §2
 */
class HireAgentCompetingBidsRetirementTest extends TestCase
{
    use DatabaseTransactions;

    /** The two retired URL shapes, relative to a listing id. */
    private function retiredUrls(int $auctionId): array
    {
        return [
            'page' => "/tenant/agent/auction/{$auctionId}/competing-bids",
            'data' => "/tenant/agent/auction/{$auctionId}/competing-bids/data",
        ];
    }

    /**
     * A real Tenant hire listing with two rival agent proposals on it.
     *
     * The fixture is deliberately NOT minimal: the retired endpoints only ever produced output
     * when the viewer had themselves submitted a bid and at least one competitor had too. A test
     * that hit these URLs with no bids present would 404 for the wrong reason — there would have
     * been nothing to disclose even before the deletion. Planting both bids means a surviving
     * endpoint would have had something real to leak, so the 404 is load-bearing.
     *
     * CLAUDE.md schema asymmetry: tenant_agent_auctions carries `address` as EAV meta rather than
     * a native column, and the model guards mass assignment — hence saveMeta and forceCreate.
     *
     * @return array{owner: User, mine: User, rival: User, outsider: User, listing: Model}
     */
    private function scenario(): array
    {
        $owner    = User::factory()->create(['user_type' => 'seller', 'name' => 'ListingOwnerPerson']);
        $mine     = User::factory()->create(['user_type' => 'agent',  'name' => 'MyOwnAgentPerson']);
        $rival    = User::factory()->create(['user_type' => 'agent',  'name' => 'RivalAgentPerson']);
        $outsider = User::factory()->create(['user_type' => 'agent',  'name' => 'UninvolvedAgentPerson']);

        $listing = TenantAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Tenant hire-agent listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $listing->saveMeta('address', '100 Test Street');

        $this->makeBid($listing->id, $mine->id, '111.11');
        $this->makeBid($listing->id, $rival->id, '987654.32');

        return compact('owner', 'mine', 'rival', 'outsider', 'listing');
    }

    private function makeBid(int $listingId, int $userId, string $amount): Model
    {
        $bid = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $listingId,
            'user_id'                 => $userId,
        ]);

        $bid->saveMeta('purchase_fee_type', 'Flat Fee');
        $bid->saveMeta('purchase_fee_flat', $amount);

        return $bid;
    }

    /**
     * Assert a retired URL is gone, and gone in the right way.
     *
     * 3xx is called out separately from the status assertion so that a regression which
     * reintroduces the surface behind a redirect fails with a message that says so.
     */
    private function assertRetired($response, string $label): void
    {
        $this->assertFalse(
            $response->isRedirect(),
            "{$label}: a retired competing-bids URL must NOT redirect. Sending the viewer to "
            . 'another proposal surface is itself a disclosure — the URL must simply not exist.'
        );

        $response->assertStatus(404);
    }

    // ── The retired endpoints are unreachable for every viewer ───────────────

    /**
     * The competing agent is the whole point of the checkpoint: this is the viewer the legacy
     * endpoints were built to serve, and the one who must now get nothing.
     */
    public function test_competing_agent_cannot_reach_either_retired_endpoint(): void
    {
        $s = $this->scenario();
        $urls = $this->retiredUrls($s['listing']->id);

        foreach ($urls as $kind => $url) {
            $this->assertRetired(
                $this->actingAs($s['mine'])->get($url),
                "submitting agent -> {$kind}"
            );
        }
    }

    /** An agent with no proposal on the listing at all. */
    public function test_uninvolved_agent_cannot_reach_either_retired_endpoint(): void
    {
        $s = $this->scenario();

        foreach ($this->retiredUrls($s['listing']->id) as $kind => $url) {
            $this->assertRetired(
                $this->actingAs($s['outsider'])->get($url),
                "uninvolved agent -> {$kind}"
            );
        }
    }

    /**
     * The owner too. The owner legitimately reviews every proposal, but they do so through the
     * Hire detail view governed by HireAgentProposalAccess — not through this retired surface.
     * Retiring a URL for some viewers and not others would leave the stack half-alive.
     */
    public function test_listing_owner_cannot_reach_either_retired_endpoint(): void
    {
        $s = $this->scenario();

        foreach ($this->retiredUrls($s['listing']->id) as $kind => $url) {
            $this->assertRetired(
                $this->actingAs($s['owner'])->get($url),
                "listing owner -> {$kind}"
            );
        }
    }

    /**
     * Guests. The retired routes sat inside the `agentAuth` middleware group, so before deletion
     * a guest was bounced to login rather than shown data. Route resolution precedes middleware,
     * so after deletion the guest must get a flat 404 — NOT a login redirect, which would still
     * signal that the endpoint exists.
     */
    public function test_guest_cannot_reach_either_retired_endpoint(): void
    {
        $s = $this->scenario();

        foreach ($this->retiredUrls($s['listing']->id) as $kind => $url) {
            $this->assertRetired($this->get($url), "guest -> {$kind}");
        }
    }

    // ── The registrations themselves are gone ────────────────────────────────

    /**
     * Named-route resolution. Any surviving `route('tenant.agent.auction.competing-bids')` call
     * anywhere in the codebase would throw at render time; asserting the names are unregistered
     * is what makes that impossible rather than merely unobserved.
     */
    public function test_retired_route_names_no_longer_resolve(): void
    {
        foreach (['tenant.agent.auction.competing-bids', 'tenant.agent.auction.competing-bids.data'] as $name) {
            $this->assertFalse(
                Route::has($name),
                "Route name [{$name}] must be unregistered after the retirement checkpoint."
            );
        }
    }

    /** No registered URI may still contain the retired path segment. */
    public function test_no_registered_route_uri_mentions_competing_bids(): void
    {
        $matching = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_contains($uri, 'competing-bids'))
            ->values()
            ->all();

        $this->assertSame(
            [],
            $matching,
            'No route may still expose a competing-bids URI: ' . implode(', ', $matching)
        );
    }

    // ── Create Offer is a different feature and stays reachable ──────────────

    /**
     * The retired surface and Create Offer's competing-bids feed share a name and nothing else.
     * Create Offer's feed is governed by PublicOfferFeedService, lives in its own partial, and is
     * explicitly out of scope. If this checkpoint had deleted by name-match rather than by
     * dependency, this partial and its two include sites are what it would have taken with it.
     */
    public function test_create_offer_competing_bids_feature_is_untouched(): void
    {
        $partial = resource_path('views/offer-listing/partials/_competing-bids.blade.php');

        $this->assertFileExists(
            $partial,
            'The Create Offer competing-bids partial must survive the Hire Agent retirement.'
        );

        $body = file_get_contents($partial);
        $this->assertStringContainsString('PublicOfferFeedService', $body);
        $this->assertStringContainsString('$canViewBidFeed', $body);

        foreach (['seller', 'landlord'] as $role) {
            $this->assertStringContainsString(
                "@include('offer-listing.partials._competing-bids'",
                file_get_contents(resource_path("views/offer-listing/{$role}/view.blade.php")),
                "The Create Offer {$role} view must still include the competing-bids partial."
            );
        }
    }
}
