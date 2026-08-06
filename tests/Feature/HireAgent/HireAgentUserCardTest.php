<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M7.5 Track 1 — the owner card on all four Hire Agent detail pages.
 *
 * WHY THIS SUITE IS NOT FLAG-SCOPED, unlike every other M5/M7 suite here. Everything this pins is
 * asserted in BOTH flag states, because the change it covers is not behind the redesign flag. That
 * was a deliberate decision and it is the thing most likely to look like a mistake later, so it is
 * recorded rather than left to the commit message: three of the four defects were fabricated claims
 * about a real, named person — a five-star rating with no rating data behind it, a "last online 5
 * days ago" that was a string literal, and a "..." standing in for a bio that does not exist. A
 * flag exists so a LAYOUT can be reviewed before it ships. Leaving invented facts about a user on
 * the live page until a layout rollout is ready is not what it is for.
 *
 * ALL FOUR ROLES, because the card was copy-pasted to all four and so were its defects. Role
 * symmetry is a standing rule in this codebase and this is exactly the case it exists for: fixing
 * one page would leave three identical bugs behind and no note saying why.
 *
 * THE ONE ASYMMETRY THAT WAS REAL. Buyer already carried `@if ($auser)`; landlord, seller and
 * tenant did not. The fix adopts buyer's spelling rather than inventing a second, and adds to all
 * four the half buyer was missing — a resolvable row with no usable name.
 *
 * WHAT IS DELIBERATELY NOT ASSERTED HERE: anything about where the card sits, how wide it is, or
 * what chrome it carries. This suite is about what the card CLAIMS. Presentation is Track 2 and
 * HireAgentSidebarSurfaceTest.
 */
class HireAgentUserCardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vocabulary that must not survive anywhere in the rendered page.
     *
     * Page-wide rather than card-scoped on purpose. A card-scoped assertion would pass if the
     * fabrication were merely relocated, and "moved the fake rating somewhere else" is not the
     * outcome this milestone is claiming.
     *
     * MATCHED AGAINST WHITESPACE-NORMALISED HTML, and that is not a stylistic choice. The old
     * markup wrapped its link text mid-phrase:
     *
     *     <b>User
     *                 Details</b>
     *
     * so the rendered page never contained the contiguous string "User Details" and asserting on
     * it directly passes against the UNFIXED code — a vacuous test that looks like a real one.
     * This was caught by checking each phrase against `git show HEAD:` before trusting the suite.
     * Normalising the haystack is what makes the assertion bind.
     */
    private const FABRICATIONS = [
        'last online',
        'User Details',
    ];

    /** Collapse every whitespace run to a single space, so a phrase broken across lines matches. */
    private function normalized(string $html): string
    {
        return (string) preg_replace('/\s+/', ' ', $html);
    }

    /** @return array<string, array{0: string}> */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    // ── Fixtures (mirrors HireAgentDetailShellLayoutTest's wiring) ───────────

    /** @return array{0: class-string, 1: string, 2: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail', 'seller-agent'],
            'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction',          'buyer-agent'],
            'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view', 'landlord-agent'],
            'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view',   'tenant-agent'],
        };
    }

    private function makeListing(string $role, int $ownerId): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' owner-card listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Owner Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Owner Street');
        }

        // Seller's controller redirects anything that looks like an Offer Listing.
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ([
            'listing_title'   => ucfirst($role) . ' listing title',
            'budget'          => '654321',
            'property_type'   => 'Residential Property',
            'auction_type'    => 'Traditional',
            'expiration_date' => now()->addDays(30)->toDateTimeString(),
        ] as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function renderAs(string $role, Model $listing, ?User $viewer): string
    {
        [, $route] = $this->wiringFor($role);
        $request   = $viewer ? $this->actingAs($viewer) : $this;

        return $request->get(route($route, $listing->id))->assertOk()->getContent();
    }

    private function enableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => ['seller', 'buyer', 'landlord', 'tenant'],
        ]);
    }

    // ── The fabrications are gone, in both flag states ───────────────────────

    /**
     * The core claim. Asserted with the flag OFF and ON in the same test rather than as two, so a
     * future change that gates this behind the flag fails here rather than silently passing the
     * half that happens to be exercised.
     *
     * @dataProvider roles
     */
    public function test_the_card_states_no_fabricated_rating_recency_or_bio(string $role): void
    {
        foreach ([false, true] as $flagOn) {
            if ($flagOn) {
                $this->enableRedesign();
            }

            $owner = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
            $html  = $this->renderAs($role, $this->makeListing($role, $owner->id), $owner);

            $state = $flagOn ? 'flag on' : 'flag off';
            $flat  = $this->normalized($html);

            foreach (self::FABRICATIONS as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $flat,
                    "{$role} ({$state}): '{$phrase}' must not survive anywhere on the page."
                );
            }

            // The star run, specifically. Matched as the exact markup the card emitted rather than
            // as the substring 'fa-star', because tenant carries an unrelated pre-existing
            // "Agent Highlights" icon that uses the same family and is not this milestone's to
            // remove — asserting on the bare substring would fail for the wrong reason.
            $this->assertStringNotContainsString(
                '<i class="fa-solid fa-star"></i>',
                $html,
                "{$role} ({$state}): the hardcoded five-star run must be gone."
            );
            $this->assertStringNotContainsString(
                'class="start opacity-50"',
                $html,
                "{$role} ({$state}): the star container must be gone with it."
            );
        }
    }

    /** @dataProvider roles */
    public function test_the_owner_name_is_the_link_text(string $role): void
    {
        $owner = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
        $html  = $this->renderAs($role, $this->makeListing($role, $owner->id), $owner);

        $this->assertStringContainsString(
            '<b>Dana Okonkwo</b>',
            $html,
            "{$role}: the card must name the owner, not the destination."
        );
    }

    /**
     * The fallback exists because `name` is a plain column and nothing guarantees it is populated.
     * A row with a first/last pair and no `name` still identifies someone.
     *
     * @dataProvider roles
     */
    public function test_a_row_without_name_falls_back_to_the_first_last_pair(string $role): void
    {
        $owner = User::factory()->create([
            'user_type'  => 'seller',
            'name'       => '',
            'first_name' => 'Priya',
            'last_name'  => 'Raman',
        ]);

        $html = $this->renderAs($role, $this->makeListing($role, $owner->id), $owner);

        $this->assertStringContainsString('<b>Priya Raman</b>', $html, "{$role}: fallback name.");
    }

    // ── The guard ────────────────────────────────────────────────────────────

    /**
     * The defect this replaces: find() returned null, the card rendered anyway, and its three
     * links resolved to /author with no id — which 404s, because UserController::author uses
     * findOrFail. The page itself must still render; only the card is withheld.
     *
     * @dataProvider roles
     */
    public function test_a_missing_owner_row_withholds_the_card_and_the_page_still_renders(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
        $listing = $this->makeListing($role, $owner->id);
        $viewer  = User::factory()->create(['user_type' => 'seller']);

        // The listing outlives its owner row. forceDelete, because a soft-deleted row would still
        // be found by find() and the card would be right to render.
        $owner->forceDelete();

        $html = $this->renderAs($role, $listing, $viewer);

        $this->assertStringNotContainsString('class="card review', $html, "{$role}: no card without an owner.");
        $this->assertStringNotContainsString('View Profile', $html, "{$role}: and none of its controls.");
    }

    /**
     * A resolvable row with nothing usable to call it is as empty as no row at all. Without this,
     * the card renders `<b></b>` — a bold empty anchor where the name belongs.
     *
     * @dataProvider roles
     */
    public function test_a_nameless_owner_row_withholds_the_card(string $role): void
    {
        $owner = User::factory()->create([
            'user_type'  => 'seller',
            'name'       => '',
            'first_name' => '',
            'last_name'  => '',
        ]);

        $html = $this->renderAs($role, $this->makeListing($role, $owner->id), $owner);

        $this->assertStringNotContainsString('class="card review', $html, "{$role}: no card without a name.");
        $this->assertStringNotContainsString('<b></b>', $html, "{$role}: and no empty bold anchor.");
    }

    // ── The two controls are two different destinations ──────────────────────

    /**
     * THE REGRESSION THIS EXISTS FOR. Both controls pointed at `author`, so "Message" and "View
     * Profile" were the same link twice under two labels. Asserting each destination separately
     * would not have caught it — the inequality is the assertion.
     *
     * @dataProvider roles
     */
    public function test_message_reaches_the_conversation_and_view_profile_the_profile(string $role): void
    {
        [, , $chatType] = $this->wiringFor($role);

        $owner   = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
        $listing = $this->makeListing($role, $owner->id);
        $html    = $this->renderAs($role, $listing, $owner);

        $chat    = route('auction-chat', [$chatType, $listing->id]);
        $profile = route('author', [$owner->id]);

        $this->assertNotSame($chat, $profile, 'Precondition: the two routes differ.');

        $this->assertStringContainsString(
            $chat . '"><button class="btn">Message</button>',
            $html,
            "{$role}: Message must reach the conversation."
        );
        $this->assertStringContainsString(
            $profile . '"><button class="btn view-btn">View',
            $html,
            "{$role}: View Profile must still reach the profile."
        );
    }

    // ── No viewer gate was introduced ────────────────────────────────────────

    /**
     * The card carried no authorization condition before this milestone and gains none. It names
     * the listing owner, which the page already shows in its hero and its Owner Info section, and
     * it reads no bid — so HireAgentProposalAccess is not involved and must not appear to be.
     *
     * Guest and authenticated non-owner are compared to each other rather than to a fixed string:
     * the claim is that the card does not vary by viewer, and any drift shows up as a difference.
     *
     * @dataProvider roles
     */
    public function test_the_card_does_not_vary_by_viewer(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
        $listing = $this->makeListing($role, $owner->id);
        $rando   = User::factory()->create(['user_type' => 'seller']);

        $guestCard  = $this->cardOf($this->renderAs($role, $listing, null));
        $viewerCard = $this->cardOf($this->renderAs($role, $listing, $rando));

        $this->assertNotSame('', $guestCard, "{$role}: a guest sees the card.");
        $this->assertSame($guestCard, $viewerCard, "{$role}: the card must not vary by viewer.");
    }

    /** Just the owner card, so a viewer-varying assertion cannot be satisfied by the rest of the page. */
    private function cardOf(string $html): string
    {
        return preg_match('/<div class="card review.*?<\/div>\s*<\/div>\s*<\/div>/is', $html, $m) ? $m[0] : '';
    }
}
