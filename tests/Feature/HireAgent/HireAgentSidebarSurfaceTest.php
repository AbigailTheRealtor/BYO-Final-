<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M7.5 Track 2 — the landlord sidebar becomes a card, and the card is what sticks.
 *
 * WHAT CHANGED, AND WHY EACH HALF NEEDED THE OTHER.
 *
 * The sidebar was a bare stack: alerts, two horizontal rules and a CTA sitting directly on the page
 * background, beside a main column made entirely of cards. Measured against the Offer Listing
 * sidebar — the approved reference, NOT modified by this milestone — that column should be one
 * card: white, bordered, rounded, shadowed, padded.
 *
 * M7.1 had already put `hla-sidebar-sticky` on the sidebar COLUMN and recorded, in the stylesheet,
 * why it did nothing: a column carrying a populated proposal console is as tall as the main column,
 * and an element that is never shorter than its container never sticks. It named the fix — Offer
 * Listing sticks an inner card — and deferred it. Introducing the card is what makes the sticky
 * possible, which is why the two ship together rather than as two milestones.
 *
 * THE CONSOLE IS A SIBLING, NOT A CHILD, and this suite pins that as a structural fact rather than
 * a styling preference. Two reasons, and the second is the one that matters:
 *
 *   · The console brings its own card chrome (Bootstrap `.card`, plus `.hla-surface-card` under
 *     the redesign). Nesting renders border inside border and shadow inside shadow.
 *   · Its contents are gated by HireAgentProposalAccess. M7.4 fenced `.hla-surface-card` to
 *     geometry precisely so that a styling change could never be where an authorization
 *     regression hides inside a visual diff. Keeping the console outside the wrapper keeps that
 *     fence intact — no selector this milestone adds can reach a proposal card.
 *
 * FLAG OFF CHANGES NOTHING remains the guarantee, and it is asserted here for the SIDEBAR
 * specifically. Note the deliberate scope: the M7.5 owner-card fix is NOT behind the flag, so
 * "the whole page is unchanged with the flag off" is no longer true and this suite does not claim
 * it. The sidebar is untouched by Track 1, so the narrower claim is both true and the one worth
 * pinning. HireAgentUserCardTest covers the other half.
 */
class HireAgentSidebarSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function nonPilotRoles(): array
    {
        return ['seller' => ['seller'], 'buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0: class-string, 1: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail'],
            'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction'],
            'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view'],
            'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view'],
        };
    }

    private function makeListing(string $role, int $ownerId): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' sidebar listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Sidebar Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Sidebar Street');
        }
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

    /**
     * A landlord listing whose owner is looking at a real proposal.
     *
     * The console must be POPULATED for the sibling assertion to mean anything: with no proposal
     * and no owner the console is withheld entirely, and "the console is not inside the card"
     * would pass because there is no console at all.
     *
     * @return array{owner: User, listing: LandlordAgentAuction}
     */
    private function landlordWithConsole(): array
    {
        $owner = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
        $agent = User::factory()->create(['user_type' => 'agent', 'name' => 'Sam Reyes']);

        /** @var LandlordAgentAuction $listing */
        $listing = $this->makeListing('landlord', $owner->id);

        LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $listing->id,
            'user_id'                   => $agent->id,
        ]);

        return ['owner' => $owner, 'listing' => $listing->fresh()];
    }

    private function renderAs(string $role, Model $listing, ?User $viewer): string
    {
        [, $route] = $this->wiringFor($role);
        $request   = $viewer ? $this->actingAs($viewer) : $this;

        return $request->get(route($route, $listing->id))->assertOk()->getContent();
    }

    private function enableRedesign(array $roles = ['landlord']): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => $roles,
        ]);
    }

    /** The rendered sidebar column, so sidebar claims cannot be satisfied by the rest of the page. */
    private function sidebar(string $html): string
    {
        return preg_match('/<div class="[^"]*rightCol[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $m)
            ? $m[0]
            : '';
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    // ── Flag off: the sidebar is untouched ───────────────────────────────────

    public function test_flag_off_emits_no_sidebar_card_and_keeps_both_rules(): void
    {
        $this->assertFalse(config('hire_agent_detail.redesign_enabled'), 'Precondition: flag off.');

        $s    = $this->landlordWithConsole();
        $html = $this->renderAs('landlord', $s['listing'], $s['owner']);

        $this->assertStringNotContainsString('hla-sidebar-card', $html, 'No card class with the flag off.');
        $this->assertStringNotContainsString('data-hire-agent-sidebar-card', $html);
        $this->assertStringNotContainsString('.hla-detail-page .hla-sidebar-card', $html, 'And no rule.');

        // M5.4's two separators still render, exactly as they did before M7.5. Their condition's
        // first arm (`! $hlaDetailRedesign`) already made the flag-off answer unconditional, so
        // replacing it with a plain @unless had to leave this branch byte-identical.
        $this->assertSame(
            2,
            substr_count($this->sidebar($html), '<hr>'),
            'Both legacy separators must survive with the flag off.'
        );
    }

    /**
     * The sidebar column, rendered flag-off, must be byte-identical to what it was before M7.5.
     *
     * Scoped to the sidebar on purpose — see the class docblock. Track 1 changes the owner card in
     * both flag states, so a whole-page byte claim would be false, and asserting it anyway would
     * make this suite the thing that has to be edited every time the page legitimately changes.
     */
    public function test_flag_off_sidebar_carries_no_m7_5_markup_at_all(): void
    {
        $s       = $this->landlordWithConsole();
        $sidebar = $this->sidebar($this->renderAs('landlord', $s['listing'], $s['owner']));

        $this->assertNotSame('', $sidebar, 'Precondition: the sidebar was located.');

        foreach (['hla-sidebar-card', 'hla-sidebar-sticky', 'data-hire-agent-sidebar-card'] as $token) {
            $this->assertStringNotContainsString($token, $sidebar, "Flag off must not emit {$token}.");
        }
    }

    // ── Flag on: the card exists, and it is what sticks ──────────────────────

    public function test_flag_on_wraps_the_sidebar_stack_in_a_card(): void
    {
        $this->enableRedesign();

        $s    = $this->landlordWithConsole();
        $html = $this->renderAs('landlord', $s['listing'], $s['owner']);

        $this->assertStringContainsString(
            'class="hla-surface-card hla-sidebar-card hla-sidebar-sticky"',
            $html,
            'The card composes M7.4 geometry, the M7.5 surface, and the sticky.'
        );
        $this->assertStringContainsString('data-hire-agent-sidebar-card', $html);
    }

    /**
     * THE FIX M7.1 DEFERRED. The class must be on the card and NOT on the column — asserting only
     * that the class exists somewhere would pass in the broken arrangement it replaces.
     */
    public function test_the_sticky_is_on_the_card_not_on_the_column(): void
    {
        $this->enableRedesign();

        $s = $this->landlordWithConsole();
        $x = $this->xpath($this->renderAs('landlord', $s['listing'], $s['owner']));

        $onCard = $x->query('//div[@data-hire-agent-sidebar-card]');
        $this->assertSame(1, $onCard->length, 'Exactly one sidebar card.');
        $this->assertStringContainsString(
            'hla-sidebar-sticky',
            (string) $onCard->item(0)->getAttribute('class'),
            'The card carries the sticky.'
        );

        $column = $x->query('//div[@data-hire-agent-sidebar]');
        $this->assertSame(1, $column->length, 'Exactly one sidebar column.');
        $this->assertStringNotContainsString(
            'hla-sidebar-sticky',
            (string) $column->item(0)->getAttribute('class'),
            'The column must NOT still carry it — that is the arrangement M7.5 replaces.'
        );
    }

    /**
     * The structural decision, pinned. If a later change wraps the console for tidiness, this
     * fails — which is the point: the console is outside the card so that no geometry rule from a
     * styling milestone can select into HireAgentProposalAccess-gated markup.
     */
    public function test_the_proposal_console_is_a_sibling_of_the_card_not_a_descendant(): void
    {
        $this->enableRedesign();

        $s = $this->landlordWithConsole();
        $x = $this->xpath($this->renderAs('landlord', $s['listing'], $s['owner']));

        $console = $x->query('//div[contains(concat(" ", normalize-space(@class), " "), " higestBider ")]');
        $this->assertSame(1, $console->length, 'Precondition: the console rendered.');

        $this->assertSame(
            0,
            $x->query('//div[@data-hire-agent-sidebar-card]//div[contains(concat(" ", normalize-space(@class), " "), " higestBider ")]')->length,
            'The console must not be inside the sidebar card.'
        );
        $this->assertSame(
            1,
            $x->query('//div[@data-hire-agent-sidebar]/div[contains(concat(" ", normalize-space(@class), " "), " higestBider ")]')->length,
            'It must be a direct child of the column, beside the card.'
        );
    }

    /** A card's edge and padding are the separation the two rules were standing in for. */
    public function test_flag_on_retires_both_separator_rules_from_the_sidebar(): void
    {
        $this->enableRedesign();

        $s       = $this->landlordWithConsole();
        $sidebar = $this->sidebar($this->renderAs('landlord', $s['listing'], $s['owner']));

        $this->assertNotSame('', $sidebar, 'Precondition: the sidebar was located.');
        $this->assertSame(0, substr_count($sidebar, '<hr>'), 'No separator rules inside the card.');
    }

    // ── The stylesheet ───────────────────────────────────────────────────────

    public function test_the_surface_rule_ships_with_the_class_and_reads_tokens(): void
    {
        $this->enableRedesign();

        $s    = $this->landlordWithConsole();
        $html = $this->renderAs('landlord', $s['listing'], $s['owner']);

        $this->assertStringContainsString('.hla-detail-page .hla-sidebar-card', $html);
        $this->assertStringContainsString('background: var(--viho-card-bg)', $html);
        $this->assertStringContainsString('padding: var(--viho-space-lg)', $html);
    }

    /**
     * The pair existed because the sticky element was the whole column, which could exceed the
     * viewport. A short card cannot, so they only stood to put an internal scrollbar on a card
     * with no affordance that it scrolls. Their removal is part of the fix, not a tidy-up.
     *
     * SCOPED TO THE RULE BODY, not the page. `overflow-y: auto` is declared by the site header
     * partial on every page in the application, so a page-wide assertion fails against correct
     * code — which it did, on the first run of this test. The claim is about what the sticky rule
     * declares, so the sticky rule is what gets read.
     */
    public function test_the_sticky_rule_no_longer_clips_and_scrolls_its_own_contents(): void
    {
        $this->enableRedesign();

        $s    = $this->landlordWithConsole();
        $html = $this->renderAs('landlord', $s['listing'], $s['owner']);

        $this->assertStringContainsString('@media (min-width: 992px)', $html, 'Suppressed where columns stack.');

        $rule = $this->stickyRuleBody($html);
        $this->assertNotSame('', $rule, 'Precondition: the sticky rule was located.');

        $this->assertStringContainsString('position: sticky', $rule);
        $this->assertStringNotContainsString('max-height', $rule, 'The card must not clip itself.');
        $this->assertStringNotContainsString('overflow-y', $rule, 'And must not scroll its own contents.');
    }

    /** The declaration block of `.hla-detail-page .hla-sidebar-sticky`, braces excluded. */
    private function stickyRuleBody(string $html): string
    {
        return preg_match('/\.hla-detail-page\s+\.hla-sidebar-sticky\s*\{(.*?)\}/s', $html, $m)
            ? $m[1]
            : '';
    }

    // ── Role scope ───────────────────────────────────────────────────────────

    /**
     * The redesign's markup lives in the landlord view, so role scope is enforced by which files
     * exist — but the allowlist is what the shell reads, and a role turned on without the markup
     * must still not sprout a half-built sidebar.
     *
     * @dataProvider nonPilotRoles
     */
    public function test_no_other_role_gets_a_sidebar_card_while_landlord_is_the_pilot(string $role): void
    {
        $this->enableRedesign(['landlord']);

        $owner = User::factory()->create(['user_type' => 'seller', 'name' => 'Dana Okonkwo']);
        $html  = $this->renderAs($role, $this->makeListing($role, $owner->id), $owner);

        $this->assertStringNotContainsString('hla-sidebar-card', $html, "{$role} must not get the card.");
        $this->assertStringNotContainsString('data-hire-agent-sidebar-card', $html);
    }

    // ── Nothing about proposal visibility moved ──────────────────────────────

    /**
     * The console's own gate is untouched by this milestone. Asserted here rather than left to
     * HireAgentProposalAccessTest because the change being guarded is structural — the console was
     * re-parented relative to a new wrapper, and a re-parenting is exactly the kind of edit that
     * can silently move markup out from behind a condition.
     */
    public function test_a_competing_agent_still_receives_no_console(): void
    {
        $this->enableRedesign();

        $s     = $this->landlordWithConsole();
        $rival = User::factory()->create(['user_type' => 'agent', 'name' => 'Rival Agent']);

        $x = $this->xpath($this->renderAs('landlord', $s['listing'], $rival));

        $this->assertSame(
            0,
            $x->query('//div[contains(concat(" ", normalize-space(@class), " "), " higestBider ")]')->length,
            'A competing agent must see no proposal console at all.'
        );
    }
}
