<?php

namespace Tests\Feature\HireAgent;

use App\Models\LandlordAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M5.2b — the Hire Agent section navigation.
 *
 * Two claims are worth testing and they are different claims.
 *
 * The first is that the flag is real: with HIRE_AGENT_DETAIL_REDESIGN_ENABLED off, the page must
 * carry no bar, no anchors and no script. A flag that gates the markup but leaks the anchors, or
 * pushes the scroll listener regardless, is not off — it is merely invisible, and the difference
 * shows up the first time something else on the page collides with it.
 *
 * The second is the one that actually protects a viewer: EVERY NAV ENTRY HAS A SECTION AND EVERY
 * SECTION HAS AN ENTRY. The nav conditions are a hand-copied duplicate of the section conditions —
 * that is the deliberate design M5.2a enabled by hoisting the guards — and a duplicate is exactly
 * the thing that drifts. So the assertion is not "the expected labels appear"; it is that the two
 * sets derived from the rendered HTML are equal. A drift in either direction fails: a link to a
 * section that did not render, or a section the nav forgot.
 *
 * The compensation entry is checked separately and in BOTH directions. A nav entry reading
 * "Broker Compensation" shown to an anonymous visitor would leak the existence and the name of a
 * section they are never served, in the most prominent place on the page. Asserting only that a
 * guest does not see it would pass just as happily if the entry existed for nobody — which is the
 * failure mode HireAgentDetailViewPrivacyTest documents running into for real — so the
 * authenticated case is asserted alongside it to keep the guest case honest.
 */
class HireAgentSectionNavTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Sections that render for every viewer of every listing, so they are always expected.
     *
     * M7.2 added two, both because decomposition turned an existing piece of the page into a
     * section that the nav now has to account for:
     *
     *   · listing-details — was the WRAPPER card's heading. When the wrapper stopped existing it
     *     became the first section card, and a section card with an anchor and no entry breaks the
     *     both-directions invariant this file enforces.
     *   · owner-info      — always rendered and was never offered. Harmless as a sub-heading in a
     *     long card; as the LAST CARD on the page, a card the nav declines to mention reads as
     *     something withheld.
     *
     * Both are unconditional, like the two that were here before, so neither carries a guard.
     * Order is document order — the assertions below compare sequences, not sets.
     */
    private const ALWAYS_PRESENT = [
        'hla-section-listing-details',
        'hla-section-property-details',
        'hla-section-leasing-terms',
        'hla-section-owner-info',
    ];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * A listing with every optional section populated.
     *
     * Landlord listings hold these fields as EAV meta, not native columns (CLAUDE.md's schema
     * asymmetry), so each one is planted with saveMeta. forceCreate because the model guards mass
     * assignment.
     */
    private function listingWithEverySection(): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'seller']);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Landlord section-nav listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('address', '100 Test Street');

        // Services  →  $hasServices
        $listing->saveMeta('services', ['Professional photography']);

        // Additional Details  →  $additionalDetailsStr
        $listing->saveMeta('additional_details', 'Some additional detail copy.');

        // Representation Preferences  →  $repRows
        $listing->saveMeta('compatibility_preferences', json_encode([
            'landlord_specific' => ['primary_leasing_goal' => 'Maximise rent'],
        ]));

        // Broker Compensation  →  $hasLandlordBrokerCompData
        $listing->saveMeta('purchase_fee_type', 'Flat Fee');

        // Referral & Cooperation  →  $referralPctDisplay
        $listing->saveMeta('referral_percentage', '25');

        return $listing;
    }

    /** The same listing with none of the optional sections populated. */
    private function listingWithNoOptionalSections(): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'seller']);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Landlord bare listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('address', '100 Test Street');

        return $listing;
    }

    private function url(LandlordAgentAuction $listing): string
    {
        return route('landlord.agent.auction.view', $listing->id);
    }

    private function enableRedesign(): void
    {
        config(['hire_agent_detail.redesign_enabled' => true]);
    }

    // ── Readers over the rendered HTML ───────────────────────────────────────

    /** The section ids the nav OFFERS, in document order. */
    private function navTargets(string $html): array
    {
        preg_match_all(
            '/<a\b[^>]*\bdata-viho-section-nav-link\b[^>]*>/i',
            $html,
            $anchors
        );

        $ids = [];

        foreach ($anchors[0] as $anchor) {
            if (preg_match('/href="#([^"]+)"/', $anchor, $href)) {
                $ids[] = $href[1];
            }
        }

        return $ids;
    }

    /** The section ids the page actually RENDERED, in document order. */
    private function anchorIds(string $html): array
    {
        preg_match_all('/id="(hla-section-[a-z-]+)"/', $html, $matches);

        return $matches[1];
    }

    /**
     * Just the nav element.
     *
     * Assertions about what the bar does or does not NAME have to be scoped to the bar. "Broker
     * Compensation" is ordinary vocabulary elsewhere on this page — it heads a block inside the
     * proposal markup too — so a page-wide string assertion would be testing the wrong thing and
     * would fail for a reason that has nothing to do with the nav.
     */
    private function navMarkup(string $html): string
    {
        return preg_match('/<nav\b[^>]*data-viho-section-nav\b.*?<\/nav>/is', $html, $m) ? $m[0] : '';
    }

    // ── The flag ─────────────────────────────────────────────────────────────

    /**
     * Flag off: no bar, no anchors, no script, no offset declaration.
     *
     * Asserted against a listing where every section IS populated, so every entry the redesign
     * would produce is suppressed by the flag alone rather than by having nothing to show.
     */
    public function test_the_flag_off_page_carries_no_navigation_at_all(): void
    {
        $this->assertFalse(config('hire_agent_detail.redesign_enabled'), 'Precondition: flag off.');

        $html = $this->get($this->url($this->listingWithEverySection()))
            ->assertOk()
            ->getContent();

        // `data-viho-section-nav` covers both halves at once: it is the attribute the bar renders
        // AND the selector the highlighting script looks for, so its absence means neither shipped.
        $this->assertStringNotContainsString('data-viho-section-nav', $html, 'No bar and no script with the flag off.');
        $this->assertStringNotContainsString('hla-section-', $html, 'No anchors with the flag off.');

        // The offset must not be DECLARED. The shared VIHO stylesheet legitimately mentions the
        // custom property — it reads it, as `var(--viho-section-nav-offset, 0px)` — because the M1
        // stylesheet ships for every viewer and is inert without markup to style. What the flag
        // gates is this page declaring a value for it, so that is what is asserted.
        $this->assertDoesNotMatchRegularExpression(
            '/--viho-section-nav-offset:\s*\d/',
            $html,
            'The consumer must declare no sticky offset with the flag off.'
        );

        $this->assertSame([], $this->navTargets($html));
        $this->assertSame([], $this->anchorIds($html));
        $this->assertSame('', $this->navMarkup($html));
    }

    /** Flag on: the bar renders, with the behaviour the product owns attached to it. */
    public function test_the_flag_on_page_renders_the_navigation(): void
    {
        $this->enableRedesign();

        $html = $this->get($this->url($this->listingWithEverySection()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-viho-section-nav', $html);
        $this->assertStringContainsString('viho-section-nav-link', $html);
        $this->assertStringContainsString('--viho-section-nav-offset', $html, 'The consumer must declare the sticky offset.');
        $this->assertNotEmpty($this->navTargets($html));
    }

    /** The offset the primitive refuses to guess is supplied here, at both breakpoints. */
    public function test_the_consumer_supplies_the_sticky_offset_for_both_breakpoints(): void
    {
        $this->enableRedesign();

        $html = $this->get($this->url($this->listingWithEverySection()))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/--viho-section-nav-offset:\s*0px/',
            $html,
            'Desktop has no fixed chrome above the bar, so the offset is 0.'
        );
        $this->assertMatchesRegularExpression(
            '/--viho-section-nav-offset:\s*104px/',
            $html,
            'Tablet and mobile must clear the 104px header bar.'
        );
        $this->assertStringContainsString('max-width: 991.98px', $html);
    }

    // ── The claim that matters ───────────────────────────────────────────────

    /**
     * Every entry has a section and every section has an entry — fully populated listing.
     *
     * Set equality in both directions, derived from the HTML rather than from a hard-coded list,
     * so this fails on drift in either direction rather than on a list somebody forgot to update.
     */
    public function test_nav_entries_and_rendered_sections_agree_exactly(): void
    {
        $this->enableRedesign();

        $listing = $this->listingWithEverySection();

        $this->actingAs($listing->user);

        $html = $this->get($this->url($listing))->assertOk()->getContent();

        $nav     = $this->navTargets($html);
        $anchors = $this->anchorIds($html);

        $this->assertNotEmpty($nav, 'A fully populated listing must offer entries.');

        $this->assertSame(
            [],
            array_values(array_diff($nav, $anchors)),
            'The nav offers a section that did not render — a link to nothing.'
        );
        $this->assertSame(
            [],
            array_values(array_diff($anchors, $nav)),
            'A section rendered with no nav entry — the bar is incomplete.'
        );

        // Order is the reading order of the page, not merely the same set.
        $this->assertSame($anchors, $nav, 'The bar must follow the document order of the sections.');

        foreach (self::ALWAYS_PRESENT as $id) {
            $this->assertContains($id, $nav, "{$id} renders for every listing and must be offered.");
        }
    }

    /**
     * The same equality on a listing with NO optional sections.
     *
     * This is the half that catches an entry hard-coded into the bar: with nothing populated, only
     * the unconditional sections may appear, and the five optional ids must be absent from both the
     * nav and the document.
     */
    public function test_absent_sections_are_offered_no_entries(): void
    {
        $this->enableRedesign();

        $listing = $this->listingWithNoOptionalSections();

        $this->actingAs($listing->user);

        $html = $this->get($this->url($listing))->assertOk()->getContent();

        $nav     = $this->navTargets($html);
        $anchors = $this->anchorIds($html);

        $this->assertSame($anchors, $nav);
        $this->assertSame(self::ALWAYS_PRESENT, $nav, 'Only the unconditional sections may be offered.');

        foreach ([
            'hla-section-services',
            'hla-section-additional-details',
            'hla-section-representation',
            'hla-section-compensation',
            'hla-section-referral',
        ] as $absent) {
            $this->assertNotContains($absent, $nav, "{$absent} did not render and must not be offered.");
        }
    }

    // ── Compensation, in both directions ─────────────────────────────────────

    /**
     * A guest is offered no compensation entry.
     *
     * The section itself sits behind Auth::check(), so an entry for it would advertise, by name, a
     * section the viewer can never reach.
     */
    public function test_a_guest_is_offered_no_compensation_entry(): void
    {
        $this->enableRedesign();

        $html = $this->get($this->url($this->listingWithEverySection()))->assertOk()->getContent();

        $this->assertNotContains(
            'hla-section-compensation',
            $this->navTargets($html),
            'A guest must not be offered the compensation section.'
        );
        $this->assertStringNotContainsString(
            'Broker Compensation',
            $this->navMarkup($html),
            'The bar must not name a section an anonymous visitor is never served.'
        );

        // The nav must still agree with what did render.
        $this->assertSame($this->anchorIds($html), $this->navTargets($html));
    }

    /**
     * An authenticated viewer IS offered it.
     *
     * Without this, the guest assertion above would pass just as well if the entry were broken for
     * everyone — the vacuous-pass trap HireAgentDetailViewPrivacyTest fell into once already.
     */
    public function test_an_authenticated_viewer_is_offered_the_compensation_entry(): void
    {
        $this->enableRedesign();

        $listing = $this->listingWithEverySection();

        $this->actingAs($listing->user);

        $html = $this->get($this->url($listing))->assertOk()->getContent();

        $this->assertContains(
            'hla-section-compensation',
            $this->navTargets($html),
            'The compensation section renders for an authenticated viewer, so it must be offered.'
        );
    }
}
