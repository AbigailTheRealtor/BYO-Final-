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

    /*
     | THE `nonPilotRoles` PROVIDER AND ITS ASSERTION ARE GONE, AND THE LIST EMPTYING IS WHY.
     |
     | It named the roles that render no redesign markup at all, whatever the config says, and the
     | premise it encoded was "scope here is enforced by which files render the component". Roles
     | left it one at a time as that stopped being true of them: BUYER in M7 Phase 4 when its
     | wrapper card decomposed, TENANT at T2 when its sections were wrapped, and SELLER at S1 — its
     | view now names x-hire-agent.detail-section at nine call sites, so it is CAPABLE of
     | decomposing and asserting that it cannot would be asserting the bug.
     |
     | Seller was the last entry. A data provider returning nothing is an error rather than a
     | vacuous pass, so the pair is removed rather than emptied — and the claim is not dropped, it
     | has nowhere left to point: all four role views render the component, so "which files render
     | it" no longer distinguishes anybody. What still holds the line is unchanged and is asserted
     | elsewhere for every role:
     |
     |   · the flag-OFF page is one legacy card — HireAgentSectionCardDomEquivalenceTest, which
     |     parameterises all four roles;
     |   · an allowlist entry added by mistake cannot decompose a page — that is now the ROLE
     |     ALLOWLIST's job for every role alike, asserted by HireAgentDetailRedesignFlagTest.
     |
     | The distinction the old note drew between "which files exist" and "the allowlist" is
     | genuinely over: from S1 on, rollout scope is configuration and nothing else.
     */

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

            // M7.4 — Leasing Terms and Owner Info gained a has-content guard, so "sparse" would
            // otherwise mean "absent" for these two rather than "present but nearly empty". One
            // answer each keeps them rendering, which is what the assertions below are about; the
            // fixture stays sparse in the sense that matters — no OPTIONAL section is satisfied.
            'occupant_status' => 'Tenant',
            'first_name'      => 'Abby',
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
     * @param string $viewer owner|guest|stranger|qualifying_agent
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
            // The only tier that admits the agent-to-agent appendix. Ownership plus an agent
            // user_type is the simplest qualifying relationship — HireAgentDetailAudience resolves
            // an agent who posted the request to the agent tier, which is the agent-posted listing
            // the Owner Info heading has always modelled.
            'qualifying_agent' => (function () use ($owner) {
                $owner->forceFill(['user_type' => 'agent'])->save();

                return $this->actingAs($owner->fresh());
            })(),
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
        'hla-section-property'   => 'fa-solid fa-house',
        'hla-section-terms'      => 'fa-solid fa-file-contract',
        'hla-section-additional-details' => 'fa-solid fa-circle-info',
        'hla-section-representation'     => 'fa-solid fa-handshake',
        'hla-section-referral'           => 'fa-solid fa-share-nodes',
        'hla-section-role-info'          => 'fa-solid fa-id-card',
        'hla-section-agent-credentials'  => 'fa-solid fa-address-card',
    ];

    /** Every rendered section card carries exactly the icon approved for it — and every one does. */
    public function test_every_section_card_carries_its_approved_icon(): void
    {
        $this->enableRedesign();

        // AS A QUALIFYING AGENT, because that is the only tier admitting every section — the
        // agent-to-agent appendix (referral, agent credentials) is withheld from owner and guest.
        $icons = $this->cardIcons($this->render('landlord', $this->richMeta(), 'qualifying_agent'));

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
        foreach (['owner', 'guest', 'qualifying_agent'] as $viewer) {
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
                'hla-section-agent-credentials',
                'hla-section-listing-details',
                'hla-section-property',
                'hla-section-referral',
                'hla-section-representation',
                'hla-section-role-info',
                'hla-section-terms',
            ],
            array_keys($seen),
            'Every landlord listing section must be reachable from the nav across the viewers '
            . 'that may see them. Services and Broker Compensation are absent by design — they '
            . 'are proposal terms, not listing sections.'
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
            'hla-section-additional-details',
            'hla-section-representation',
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
            'hla-section-property',
            'hla-section-terms',
            'hla-section-role-info',
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
     * `hla-section-terms` and `hla-section-role-info` render unconditionally while their
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
     *
     * ── M7.4 FIXED IT, AND FIXED IT THE WAY THE PARAGRAPH ABOVE SAID IT WOULD HAVE TO ────────
     * The expectation is now the empty set. Every section on this page derives its card AND its
     * nav entry from one "this section has content" predicate, so no card can render blank and no
     * entry can point at a card that did not render.
     *
     * The predicate is what M7.2 declined to build, for the reason recorded above: for the two
     * largest sections it is dozens of field checks that must agree with the section's own
     * conditions. M7.4 could take it on because the rows themselves moved first — every row now
     * hides itself through one component, so the section-level question reduces to "does any of
     * this section's stored meta hold an answer", asked of the raw keys rather than of the
     * conditions. The duplication the nav's documentation warns about is still there in principle;
     * what changed is that it is now one list per section, asserted in both directions by
     * HireAgentFieldPresentationTest, rather than a second copy of the section's control flow.
     *
     * The empty set is the tightest this assertion can be, so a NEW id appearing here now means a
     * section gained content that can vanish without its guard noticing.
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
            [],
            $empty,
            'A section rendered an empty card. Since M7.4 every section derives its card and its '
            . 'nav entry from one has-content predicate, so an id here means that predicate no '
            . 'longer covers everything the section can render.'
        );
    }

    // ── Negotiation terms are not listing sections, at any tier ──────────────

    /**
     * NO VIEWER REACHES A SERVICES OR BROKER COMPENSATION CARD, on a listing that has both.
     *
     * This block used to assert compensation's visibility in three directions — hidden from a
     * guest, hidden without data, shown to any authenticated viewer — because the section was real
     * and behind a bare `Auth::check()`. Both sections are gone from the page: an agent proposes
     * services and compensation on a bid, and the client accepts, rejects or counters them there.
     *
     * EVERY TIER IS EXERCISED, including the qualifying agent, because the widest audience is
     * where a surviving section would still be reachable. richMeta() populates both subjects, so
     * the absences below are the rule holding rather than the fixture being empty.
     */
    public function test_no_viewer_reaches_a_negotiation_term_card(): void
    {
        $this->enableRedesign();

        foreach (['guest', 'stranger', 'owner', 'qualifying_agent'] as $viewer) {
            $x = $this->render('landlord', $this->richMeta(), $viewer);

            foreach (['hla-section-services', 'hla-section-compensation'] as $retired) {
                $this->assertNotContains(
                    $retired,
                    $this->cardIds($x),
                    "{$viewer}: reached the retired [{$retired}] card."
                );
                $this->assertNotContains(
                    $retired,
                    $this->navTargets($x),
                    "{$viewer}: was offered the retired [{$retired}] nav entry."
                );
            }

            // Positive control: the page rendered real sections for this viewer, so the absences
            // above are a removal rather than a blank page.
            $this->assertContains('hla-section-listing-details', $this->cardIds($x), "{$viewer}: nothing rendered.");
        }
    }

    // ── Rollout scope ────────────────────────────────────────────────────────

    /*
     | test_non_pilot_roles_emit_no_section_cards lived here and retired at S1 with its provider.
     | See the note where that provider stood, at the top of this class.
     */

    /**
     * S1 — the seller page decomposes when the allowlist grants it, and its guard map is complete.
     *
     * THE POSITIVE HALF OF THE TEST THAT RETIRED ABOVE, and it is here rather than nowhere because
     * retiring the negative one left seller's flag-ON branch with no coverage at all: no other test
     * in this suite renders seller with the redesign enabled, so a scaffold that threw on every
     * seller page would have gone green.
     *
     * IT IS MOSTLY A SMOKE TEST, AND DELIBERATELY SO. S1 wraps the sections and stops; the fields
     * inside them are still legacy rows and the nav, quick actions and sidebar are not built yet.
     * What can be asserted now is that the page renders, that the sections it claims are the ones
     * the registry admits, and — the part with teeth — that resolveForRole() does not throw. That
     * call demands a guard for EVERY section scoped to seller and refuses both a missing and an
     * extra one, so this is what proves the nine-entry guard map matches the registry rather than
     * approximately matching it.
     *
     * The expected ids are the sections richMeta() populates, in document order. Financing,
     * representation, referral and agent-credentials are absent because that fixture answers
     * none of them for a seller listing — their presence is a later milestone's assertion, not a
     * gap here.
     */
    public function test_seller_decomposes_into_section_cards_when_allowlisted(): void
    {
        $this->enableRedesign(['seller']);

        $x = $this->render('seller', $this->richMeta());

        $this->assertSame(
            [
                'hla-section-listing-details',
                'hla-section-property',
                'hla-section-terms',
                'hla-section-additional-details',
                'hla-section-role-info',
            ],
            $this->cardIds($x),
            'Seller must decompose into exactly the sections the registry admits for this listing.'
        );
    }

    /**
     * S1 — and it stays one legacy card while the allowlist withholds it.
     *
     * The half of the retired test that is still true of seller and still worth pinning: being
     * CAPABLE of decomposing must not mean doing it. This is the same claim
     * HireAgentSectionCardDomEquivalenceTest makes from the DOM side; asserted here too because
     * this is the file where the capability was added.
     */
    public function test_seller_stays_one_card_while_the_allowlist_withholds_it(): void
    {
        $this->enableRedesign(['landlord']);

        $x = $this->render('seller', $this->richMeta());

        $this->assertSame([], $this->cardIds($x), 'Seller must not decompose off the allowlist.');
        $this->assertSame(
            1,
            $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-card ')]")->length,
            'Seller must keep its single legacy listing card.'
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
