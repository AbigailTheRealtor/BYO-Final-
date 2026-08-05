<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M7.2 — the redesigned side: what the flag being ON actually produces.
 *
 * HireAgentSectionCardDomEquivalenceTest guards the legacy branch and proves the flag-off page did
 * not move. This file guards the branch M7.2 exists to build.
 *
 * THE TWO FAILURES THIS FILE IS BUILT AROUND, both of which a "does the card render" test misses:
 *
 *   1. A NAV ENTRY POINTING AT NOTHING. The nav is built from one set of conditions and the
 *      sections from another, and before M7.2 the anchor was a span the section happened to
 *      contain. Now the id is on the card, so a section that does not render takes its anchor with
 *      it — and the nav entry, built separately, does not know. Every entry is resolved against the
 *      rendered document here, for several different viewers, because "the link works" is only
 *      true per viewer.
 *
 *   2. AN EMPTY CARD. Under the old layout a section whose guard was false contributed nothing
 *      visible — no header, no rule, nothing. As a card it would contribute a bordered, shadowed,
 *      titled box with an empty body, which is worse than the residue M5.5 was written to remove.
 *      The card therefore opens INSIDE each guard, and the sparse fixture below proves it.
 *
 * COMPENSATION IS TESTED AS A VISIBILITY RULE, NOT AS MARKUP. `Auth::check()` and
 * `$hasLandlordBrokerCompData` are unchanged by this milestone and the card opens inside both. The
 * assertions cover all three states — anonymous, authenticated without data, authenticated with
 * data — because the interesting one is the middle, and it is the one a fixture that always
 * populates compensation would never reach.
 */
class HireAgentDetailSectionCardTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: string}> */
    public static function nonPilotRoles(): array
    {
        return ['seller' => ['seller'], 'buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

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

    /** Only what every listing has. Every optional section's guard is false. */
    private function sparseMeta(): array
    {
        return [
            'listing_title'   => 'Sparse listing',
            'auction_type'    => 'Traditional',
            'property_type'   => 'Residential Property',
            'expiration_date' => now()->addDays(30)->toDateTimeString(),
        ];
    }

    /** Every optional section's guard satisfied. */
    private function richMeta(): array
    {
        return array_merge($this->sparseMeta(), [
            'services'                  => json_encode(['List the property on the local Multiple Listing Service (MLS)']),
            'additional_details'        => 'Prefers evening showings.',
            'compatibility_preferences' => json_encode([
                'landlord_specific' => ['primary_leasing_goal' => 'Maximize rent'],
            ]),
            'purchase_fee_type'         => 'Flat Fee',
            'purchase_fee_flat'         => '2500',
            'referral_percentage'       => '25',
        ]);
    }

    private function makeListing(string $role, int $ownerId, array $meta): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' card listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Shell Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Shell Street');
        }
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function enableRedesign(array $roles = ['landlord']): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => $roles,
        ]);
    }

    /** @param string $viewer owner|guest|stranger */
    private function render(string $role, array $meta, string $viewer = 'owner'): DOMXPath
    {
        return $this->xpath($this->renderRaw($role, $meta, $viewer));
    }

    /**
     * The response body, unparsed.
     *
     * The CSS assertions need this: DOMDocument keeps a <style> element's text, but reaching it
     * through XPath and reassembling the declaration is more machinery than reading the source, and
     * the thing under test is the stylesheet the page ships.
     *
     * @param string $viewer owner|guest|stranger
     */
    private function renderRaw(string $role, array $meta, string $viewer = 'owner'): string
    {
        [, $route] = $this->wiringFor($role);

        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, $meta);

        $request = match ($viewer) {
            'owner'    => $this->actingAs($owner),
            'stranger' => $this->actingAs(User::factory()->create(['user_type' => 'agent'])),
            // Guards must be cleared, not merely omitted: actingAs persists for the whole test
            // method, so an un-cleared "guest" silently renders as whoever came before it.
            'guest'    => tap($this, function () {
                auth()->logout();
                $this->app->get('auth')->forgetGuards();
                $this->assertGuest();
            }),
        };

        $response = $request->get(route($route, $listing->id));
        $response->assertOk();

        return $response->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    /** Ids of every section card, in document order. */
    private function cardIds(DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query('//*[starts-with(@id, "hla-section-")]') as $node) {
            $out[] = $node->getAttribute('id');
        }

        return $out;
    }

    /** Fragment targets of every section nav link, in document order. */
    private function navTargets(DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query('//a[starts-with(@href, "#hla-section-")]') as $node) {
            $out[] = ltrim($node->getAttribute('href'), '#');
        }

        return $out;
    }

    /** Icon class carried by each section card's header, keyed by card id. */
    private function cardIcons(DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query('//*[starts-with(@id, "hla-section-")]') as $card) {
            $icon = (new DOMXPath($card->ownerDocument))->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-card-head ')]"
                . "//i[contains(concat(' ', normalize-space(@class), ' '), ' viho-section-header-icon ')]",
                $card
            )->item(0);

            $out[$card->getAttribute('id')] = $icon === null
                ? null
                // x-viho.card appends its own marker class; the caller's value is what precedes it.
                : trim(str_replace('viho-section-header-icon', '', $icon->getAttribute('class')));
        }

        return $out;
    }

    // ── The icon mapping ─────────────────────────────────────────────────────

    /**
     * The approved section icons.
     *
     * NOT invented for this milestone. Each one is the glyph the reference page — the Offer
     * Listing landlord view, resources/views/offer-listing/landlord/view.blade.php — already uses
     * on the card header for the same KIND of section, so the two pages read as one product rather
     * than two teams' guesses at the same idea:
     *
     *   property-details   fa-house          ← its `section-details` "Property Details"
     *   leasing-terms      fa-file-contract  ← its `section-leasing` "Leasing Terms"
     *   compensation       fa-dollar-sign    ← its `section-pricing` "Rental Pricing & Deposits"
     *   owner-info         fa-id-card        ← its `section-contact` "Contact / Landlord Information"
     *   additional-details fa-circle-info    ← its `section-preferences` "Preferences"
     *   services           fa-list-check     ← its `section-overview` "Listing Overview"
     *   referral           fa-share-nodes    ← its Share action
     *
     * Two sections have no counterpart there because the reference page has no equivalent concept:
     * `listing-details` (fa-file-lines) and `representation` (fa-handshake). Both are drawn from
     * the same Font Awesome solid family the reference page uses throughout, so nothing new is
     * introduced to the icon vocabulary — only applied to sections that page does not have.
     *
     * Pinned rather than eyeballed because an icon is the one part of a section header that a
     * reader notices and a test otherwise never looks at: a typo'd class renders as nothing at all
     * and every other assertion in this file still passes.
     */
    private const SECTION_ICONS = [
        'hla-section-listing-details'    => 'fa-solid fa-file-lines',
        'hla-section-property-details'   => 'fa-solid fa-house',
        'hla-section-leasing-terms'      => 'fa-solid fa-file-contract',
        'hla-section-services'           => 'fa-solid fa-list-check',
        'hla-section-additional-details' => 'fa-solid fa-circle-info',
        'hla-section-representation'     => 'fa-solid fa-handshake',
        'hla-section-compensation'       => 'fa-solid fa-dollar-sign',
        'hla-section-referral'           => 'fa-solid fa-share-nodes',
        'hla-section-owner-info'         => 'fa-solid fa-id-card',
    ];

    /** Every rendered section card carries exactly the icon approved for it — and every one does. */
    public function test_every_section_card_carries_its_approved_icon(): void
    {
        $this->enableRedesign();

        $icons = $this->cardIcons($this->render('landlord', $this->richMeta()));

        // The rich fixture satisfies every guard, so the whole mapping should be exercised. A
        // section missing here would let a wrong icon go unasserted rather than fail.
        $this->assertSame(
            array_keys(self::SECTION_ICONS),
            array_keys($icons),
            'The set of section cards drifted from the approved icon mapping.'
        );

        foreach (self::SECTION_ICONS as $id => $expected) {
            $this->assertSame($expected, $icons[$id], "[{$id}] renders the wrong icon.");
        }
    }

    /**
     * The icons are decorative and are announced to nobody.
     *
     * Each duplicates the heading text sitting next to it, so a screen reader that read them would
     * hear the section named twice. x-viho.card marks them aria-hidden; this asserts the section
     * cards actually get that treatment rather than trusting the container to have been used right.
     */
    public function test_section_icons_are_decorative(): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->richMeta());

        $icons = $x->query(
            "//*[starts-with(@id, 'hla-section-')]"
            . "//i[contains(concat(' ', normalize-space(@class), ' '), ' viho-section-header-icon ')]"
        );

        $this->assertGreaterThan(0, $icons->length, 'There must be icons to assert about.');

        foreach ($icons as $icon) {
            $this->assertSame(
                'true',
                $icon->getAttribute('aria-hidden'),
                'A section icon is exposed to assistive technology.'
            );
        }
    }

    /**
     * With the flag off no section icon renders at all.
     *
     * The icon prop is passed unconditionally at every call site; only the component's card branch
     * consumes it. An icon appearing on the legacy page would mean the branch leaked — the same
     * failure HireAgentSectionCardDomEquivalenceTest guards from the other direction.
     */
    public function test_flag_off_renders_no_section_icons(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);

        $x = $this->render('landlord', $this->richMeta());

        $this->assertSame(
            0,
            $x->query(
                "//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-section-header-icon ')]"
            )->length,
            'Flag off must render no section header icon.'
        );
    }

    // ── The scroll offset, which the card must carry because it is the anchor ─

    /**
     * The section cards get a scroll offset, and it clears BOTH the chrome and the bar.
     *
     * ─────────────────────────────────────────────────────────────────────────────────────────
     * THE BUG THIS EXISTS TO PREVENT RECURRING, WHICH NO OTHER ASSERTION IN THIS FILE CATCHES.
     *
     * Before M7.2 the anchor was a zero-height `<span class="viho-section-nav-target">` above each
     * heading, and viho/styles.blade.php gives THAT CLASS its scroll-margin-top. M7.2 deleted the
     * spans and moved the ids onto the cards. The cards inherited the id and not the class, so the
     * offset stopped applying — silently. Every anchor still resolved. Every nav entry still
     * pointed at a real element. The suite stayed green.
     *
     * The only symptom was visual: measured on the running page, clicking a nav entry landed the
     * card at y≈0 with the bar's bottom edge at 46.9px on desktop and 150.9px on mobile, putting
     * the card header underneath the bar in 6 of 7 desktop sections and 7 of 7 on mobile — the
     * exact outcome the milestone exists to fix.
     *
     * So this asserts the DECLARATION, not the rendered geometry, which PHPUnit cannot see. It is
     * a coupling check: the anchors and the thing that offsets them must stay attached.
     * ─────────────────────────────────────────────────────────────────────────────────────────
     */
    public function test_section_cards_declare_a_scroll_offset(): void
    {
        $this->enableRedesign();

        $html = $this->renderRaw('landlord', $this->richMeta());

        $this->assertMatchesRegularExpression(
            '/\.hla-detail-page\s*\[id\^="hla-section-"\]\s*\{[^}]*scroll-margin-top/s',
            $html,
            'The section cards must declare a scroll offset. Without it a nav entry lands the card '
            . 'flush with the viewport top and the sticky bar covers the header.'
        );
    }

    /**
     * The offset clears the bar as well as the chrome — two variables, not one.
     *
     * The original rule reused `--viho-section-nav-offset` alone. That value is where the BAR
     * sticks; a target scrolled to it lands exactly under the bar, short by the bar's own height.
     * On desktop the offset is 0px, so reusing it produced no clearance whatsoever.
     *
     * Asserted as "the declaration references both variables" rather than by pinning a pixel
     * total, so retuning either value stays a one-line change and does not break this test.
     */
    public function test_the_scroll_offset_clears_the_bar_and_not_only_the_chrome(): void
    {
        $this->enableRedesign();

        $html = $this->renderRaw('landlord', $this->richMeta());

        preg_match(
            '/\.hla-detail-page\s*\[id\^="hla-section-"\]\s*\{(?<body>[^}]*)\}/s',
            $html,
            $m
        );

        $this->assertNotEmpty($m['body'] ?? '', 'The scroll offset rule must exist to be checked.');

        foreach (['--viho-section-nav-offset', '--viho-section-nav-height'] as $variable) {
            $this->assertStringContainsString(
                $variable,
                $m['body'],
                "The offset must be built from [{$variable}]. Using the chrome offset alone leaves "
                . 'the target short by the height of the bar it is scrolled underneath.'
            );
        }

        $this->assertStringContainsString(
            '--viho-section-nav-height',
            $html,
            'The bar height variable must be declared, not only referenced.'
        );
    }

    /**
     * The legacy nav-target class is no longer required — the CARD is the anchor.
     *
     * Two things asserted together because either alone would be misleading: no element carries the
     * class, and the ids live on elements that are cards. A page that still emitted the spans would
     * mean the decomposition left its old anchors behind; a page whose ids sat on something other
     * than a card would mean the nav resolves somewhere that is not a section.
     */
    public function test_the_card_is_the_anchor_and_the_legacy_nav_target_class_is_gone(): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->richMeta());

        $this->assertSame(
            0,
            $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-section-nav-target ')]")->length,
            'The legacy nav-target spans must be gone; the card carries the id now.'
        );

        foreach ($x->query('//*[starts-with(@id, "hla-section-")]') as $node) {
            $this->assertStringContainsString(
                'viho-card',
                $node->getAttribute('class'),
                "[{$node->getAttribute('id')}] holds a section anchor but is not a card."
            );
        }
    }

    /** With the flag off the offset rule is not emitted at all. */
    public function test_flag_off_emits_no_scroll_offset_rule(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);

        $this->assertStringNotContainsString(
            '.hla-detail-page [id^="hla-section-"]',
            $this->renderRaw('landlord', $this->richMeta()),
            'The legacy page must push no redesign CSS.'
        );
    }

    /**
     * Every section this page can render resolves from the nav — all nine, not just the ones one
     * fixture happens to produce.
     *
     * The rich fixture alone renders eight; compensation needs an authenticated viewer as well, so
     * the union across viewers is what proves the full set. Written as an explicit expected list so
     * a section that silently stopped rendering fails by name.
     */
    public function test_all_nine_section_anchors_resolve(): void
    {
        $this->enableRedesign();

        $seen = [];
        foreach (['owner', 'guest'] as $viewer) {
            $x = $this->render('landlord', $this->richMeta(), $viewer);

            foreach ($this->navTargets($x) as $target) {
                $this->assertContains(
                    $target,
                    $this->cardIds($x),
                    "{$viewer}: nav entry [#{$target}] resolves to no card."
                );
                $seen[$target] = true;
            }
        }

        ksort($seen);

        $this->assertSame(
            [
                'hla-section-additional-details',
                'hla-section-compensation',
                'hla-section-leasing-terms',
                'hla-section-listing-details',
                'hla-section-owner-info',
                'hla-section-property-details',
                'hla-section-referral',
                'hla-section-representation',
                'hla-section-services',
            ],
            array_keys($seen),
            'All nine sections must be reachable from the nav across the viewers that may see them.'
        );
    }

    // ── Every nav entry resolves to a real card ──────────────────────────────

    /**
     * For each viewer, every nav link points at an element that exists on the page it was rendered
     * on. The viewers differ in what they may see, which is the only way this can fail.
     *
     * @dataProvider viewers
     */
    public function test_every_nav_entry_resolves_to_a_card(string $viewer): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->richMeta(), $viewer);

        $cards = $this->cardIds($x);
        $nav   = $this->navTargets($x);

        $this->assertNotEmpty($nav, "{$viewer}: the nav must render entries to be worth testing.");

        foreach ($nav as $target) {
            $this->assertContains(
                $target,
                $cards,
                "{$viewer}: nav entry [#{$target}] points at no card on this page."
            );
        }
    }

    /** @return array<string, array{0: string}> */
    public static function viewers(): array
    {
        return ['owner' => ['owner'], 'guest' => ['guest'], 'stranger' => ['stranger']];
    }

    /** Ids are unique — a duplicate makes the anchor ambiguous and the nav unreliable. */
    public function test_section_card_ids_are_unique(): void
    {
        $this->enableRedesign();

        $ids = $this->cardIds($this->render('landlord', $this->richMeta()));

        $this->assertSame(array_values(array_unique($ids)), $ids, 'Section card ids must be unique.');
    }

    // ── No empty cards ───────────────────────────────────────────────────────

    /**
     * With every optional guard false, none of those sections emits a card.
     *
     * This is the assertion that proves each card opens INSIDE its guard rather than around it.
     * Written as an explicit list rather than a count so a failure names the section.
     */
    public function test_no_card_renders_for_a_section_whose_guard_is_false(): void
    {
        $this->enableRedesign();

        $ids = $this->cardIds($this->render('landlord', $this->sparseMeta()));

        foreach ([
            'hla-section-services',
            'hla-section-additional-details',
            'hla-section-representation',
            'hla-section-compensation',
            'hla-section-referral',
        ] as $conditional) {
            $this->assertNotContains(
                $conditional,
                $ids,
                "[{$conditional}] rendered an empty card with its guard false."
            );
        }
    }

    /** The unconditional sections still render on the same sparse listing. */
    public function test_unconditional_sections_still_render_when_everything_optional_is_absent(): void
    {
        $this->enableRedesign();

        $ids = $this->cardIds($this->render('landlord', $this->sparseMeta()));

        foreach ([
            'hla-section-listing-details',
            'hla-section-property-details',
            'hla-section-leasing-terms',
            'hla-section-owner-info',
        ] as $unconditional) {
            $this->assertContains($unconditional, $ids, "[{$unconditional}] must always render.");
        }
    }

    /**
     * No CONDITIONAL section renders an empty card, and the two unconditional ones that can are
     * named rather than glossed over.
     *
     * ─────────────────────────────────────────────────────────────────────────────────────────
     * A REAL FINDING, RECORDED RATHER THAN SILENTLY FIXED.
     *
     * `hla-section-leasing-terms` and `hla-section-owner-info` render unconditionally while their
     * CONTENT is made entirely of @if blocks. On a listing sparse enough to satisfy none of them,
     * both emit a card with an empty body.
     *
     * It is not new. Under the legacy layout the same listing emitted a bare "Leasing Terms:"
     * heading with nothing beneath it — the same absence of information, in a form nobody
     * noticed. Decomposition makes it conspicuous by giving it a border and a shadow. This is the
     * class of residue carried-forward item 6 describes and M5.5 removed for the proposal console.
     *
     * WHY M7.2 DOES NOT FIX IT. The obvious fix — suppress a card whose slot is blank — would
     * strand the nav. The nav is built ~500 lines above the content from explicit conditions, so
     * it cannot observe that a section rendered nothing, and the entry would point at a card that
     * no longer exists. Keeping them in step means deriving BOTH from a "this section has
     * content" predicate, and for Leasing Terms that predicate is dozens of field checks that
     * must match the section's own conditions character for character — the exact duplication the
     * nav's own documentation warns produces links to sections that did not render. That is a
     * structural change to how sections declare themselves, not a card change, and it does not
     * ship inside a milestone about card boundaries.
     *
     * So the behaviour is PRESERVED, deliberately: the card renders, the nav entry works, and a
     * sparse listing shows an empty section exactly as it did before — just in a box.
     * ─────────────────────────────────────────────────────────────────────────────────────────
     */
    public function test_no_conditional_section_renders_an_empty_card(): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->sparseMeta());

        $empty = [];

        foreach ($x->query('//*[starts-with(@id, "hla-section-")]') as $card) {
            $body = (new DOMXPath($card->ownerDocument))->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-card-body ')]",
                $card
            )->item(0);

            $this->assertNotNull($body, "[{$card->getAttribute('id')}] has no body.");

            if (trim($body->textContent) === '') {
                $empty[] = $card->getAttribute('id');
            }
        }

        sort($empty);

        $this->assertSame(
            ['hla-section-leasing-terms', 'hla-section-owner-info'],
            $empty,
            'The set of sections that can render empty changed. A NEW id here means a guard was '
            . 'placed around a card instead of inside it; a MISSING one means the known condition '
            . 'was fixed and this expectation should be tightened.'
        );
    }

    // ── Compensation visibility, unchanged by M7.2 ───────────────────────────

    /** Anonymous visitors reach neither the card nor the nav entry naming it. */
    public function test_compensation_is_hidden_from_anonymous_visitors(): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->richMeta(), 'guest');

        $this->assertNotContains('hla-section-compensation', $this->cardIds($x), 'Guest saw the compensation card.');
        $this->assertNotContains('hla-section-compensation', $this->navTargets($x), 'Guest saw the compensation nav entry.');
    }

    /** Authenticated, but the listing carries no compensation data: still nothing. */
    public function test_compensation_is_hidden_when_the_listing_has_no_compensation_data(): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->sparseMeta(), 'owner');

        $this->assertNotContains('hla-section-compensation', $this->cardIds($x));
        $this->assertNotContains('hla-section-compensation', $this->navTargets($x));
    }

    /** Both conditions met: the card renders, for the owner and for another authenticated user. */
    public function test_compensation_renders_when_authenticated_and_data_exists(): void
    {
        $this->enableRedesign();

        foreach (['owner', 'stranger'] as $viewer) {
            $x = $this->render('landlord', $this->richMeta(), $viewer);

            $this->assertContains(
                'hla-section-compensation',
                $this->cardIds($x),
                "{$viewer}: compensation should render when both guards pass."
            );
        }
    }

    // ── Rollout scope ────────────────────────────────────────────────────────

    /**
     * The other three roles emit no section card even with the master switch on.
     *
     * Scope here is enforced by which files render the component — only the landlord view does —
     * rather than by the role allowlist, which governs the shared shell's grid. That distinction
     * matters for rollback and is asserted rather than assumed.
     *
     * @dataProvider nonPilotRoles
     */
    public function test_non_pilot_roles_emit_no_section_cards(string $role): void
    {
        $this->enableRedesign(['landlord', 'seller', 'buyer', 'tenant']);

        $x = $this->render($role, $this->richMeta());

        $this->assertSame([], $this->cardIds($x), "{$role} must not decompose into section cards.");
        $this->assertSame(
            1,
            $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-card ')]")->length,
            "{$role} must keep its single legacy listing card."
        );
    }

    /** The landlord page decomposes into siblings — no card is nested inside another. */
    public function test_section_cards_are_siblings_not_nested(): void
    {
        $this->enableRedesign();

        $x = $this->render('landlord', $this->richMeta());

        $this->assertSame(
            0,
            $x->query(
                "//*[starts-with(@id, 'hla-section-')]//*[starts-with(@id, 'hla-section-')]"
            )->length,
            'Section cards must be siblings; one nested inside another means the wrapper card survived.'
        );
    }
}
