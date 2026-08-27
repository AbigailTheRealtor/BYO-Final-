<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The section bar must not be able to hide an entry it is offering.
 *
 * ── WHAT WENT WRONG, AND WHY NO EXISTING TEST CAUGHT IT ──────────────────────────────────────
 *
 * `x-viho.section-nav` renders one nowrap flex row inside a list that is `overflow-x: auto` with
 * its scrollbar suppressed in both engines. That pairing has no visible failure state: when the
 * labels are wider than the main column the tail of the row is off-screen with no scrollbar, no
 * fade and nothing at all to say it is there.
 *
 * The tail is always the owner-info entry, because it is last in document order — "Agent's Info"
 * when the listing owner is an agent. Landlord offers six entries and fits. Seller, tenant and
 * buyer offer seven, because they also carry Financing (seller, buyer) or Pre-Screening (tenant),
 * and they do not fit: measured against the ~966px main column, seller ran ~1070px, tenant ~1106px
 * and buyer ~1150px. So the entry was half-cut on two roles and entirely past the edge on buyer,
 * whose labels are the longest — which is why buyer read as MISSING the section rather than as
 * clipping it.
 *
 * IT WAS NEVER A REGISTRY, GUARD OR RESOLVER DEFECT, and this file does not re-test those.
 * HireAgentBuyerSectionNavTest already proves the buyer bar and the buyer sections agree for every
 * audience, and it passed throughout — correctly, because the anchor and the section were both in
 * the DOM the whole time. Every existing test in this directory reads the rendered HTML, and the
 * defect was that correct HTML could not be seen. That is the gap this file fills, from both ends:
 *
 *   · the MARKUP end — owner-info is the last entry the bar offers, on the role that overflowed
 *     worst, and it resolves to a section that actually rendered;
 *   · the STYLESHEET end — the density rules and the wrap ceiling that stop that last entry from
 *     being clipped are actually shipped to the page.
 *
 * Asserting stylesheet text is the weaker half of the pair and is used deliberately, exactly as
 * HireAgentSectionNavTest asserts the sticky-offset declarations: these rules have no DOM effect to
 * observe from PHP, and the failure being guarded against is a well-meaning revert of a rule whose
 * absence is invisible in every other test. It pins the contract; it does not claim to measure a
 * layout.
 */
class HireAgentSectionNavOverflowTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The entry that overflowed, and the only one whose position is load-bearing here.
     *
     * It is asserted as LAST rather than merely present: last is what put it past the edge, so a
     * change that reordered the registry and moved something else into that position would move
     * the risk with it, and this file would then be guarding the wrong entry.
     */
    private const OVERFLOWING_ENTRY = 'hla-section-role-info';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * A buyer listing populated so that all seven public sections render.
     *
     * Buyer on purpose: it is the widest bar of the four, so it is the role where a regression in
     * the density rules resurfaces first. The meta keys mirror HireAgentBuyerSectionNavTest's
     * fixture rather than inventing a second set — one description of what makes a buyer section
     * appear, not two that can drift apart.
     */
    private function fullyPopulatedBuyerListing(): BuyerAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'buyer']);

        $listing = BuyerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Buyer section-nav overflow listing',
            'address'     => '100 Shell Street',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('workflow_type', 'hire_agent');

        $listing->saveMeta('auction_type', 'Traditional');                      // Listing Details
        $listing->saveMeta('cities', json_encode(['Tampa, FL']));               // Property Preferences
        $listing->saveMeta('maximum_budget', '500000');                         // Purchasing Terms
        $listing->saveMeta('offered_financing', json_encode(['Cash']));         // Financing Details
        $listing->saveMeta('additional_details', 'Evenings only.');             // Additional Details
        $listing->saveMeta('compatibility_preferences', json_encode([           // Representation
            'buyer_specific' => ['primary_transaction_goal' => 'Primary residence'],
        ]));
        $listing->saveMeta('first_name', 'Abby');                               // Owner / Agent's Info

        return $listing->fresh();
    }

    private function enableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => ['landlord', 'buyer', 'seller', 'tenant'],
        ]);
    }

    private function renderBuyer(): string
    {
        $listing = $this->fullyPopulatedBuyerListing();

        return $this->get(route('buyer.view-auction', $listing->id))->assertOk()->getContent();
    }

    // ── Readers ──────────────────────────────────────────────────────────────

    /** The section ids the bar OFFERS, in document order. */
    private function navTargets(string $html): array
    {
        preg_match_all('/<a\b[^>]*\bdata-viho-section-nav-link\b[^>]*>/i', $html, $anchors);

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

    // ── The entry that was being hidden ──────────────────────────────────────

    /**
     * The reported symptom, stated as the invariant it violated: buyer offers the owner-info entry,
     * it is the LAST one offered, and it points at a section the page rendered.
     *
     * All three clauses matter together. Presence alone passed before this fix and would pass
     * again after a revert; it is the conjunction of "last" and "resolves" that describes the
     * entry actually at risk of being scrolled out of sight.
     */
    public function test_buyer_offers_the_owner_info_entry_last_and_it_resolves_to_a_rendered_section(): void
    {
        $this->enableRedesign();

        $html = $this->renderBuyer();

        $nav     = $this->navTargets($html);
        $anchors = $this->anchorIds($html);

        $this->assertNotEmpty($nav, 'A fully populated buyer listing must offer entries.');

        $this->assertContains(
            self::OVERFLOWING_ENTRY,
            $nav,
            "Buyer must offer the owner-info entry — its absence is the reported 'Agent's Info is missing'."
        );

        $this->assertSame(
            self::OVERFLOWING_ENTRY,
            end($nav),
            'Owner-info is last in document order; if it moved, the entry this file guards moved with it.'
        );

        $this->assertContains(
            self::OVERFLOWING_ENTRY,
            $anchors,
            'The entry must resolve to a section that rendered — a bar entry with no anchor is a dead link.'
        );
    }

    /**
     * The label the viewer was looking for, on the listing where they looked for it.
     *
     * Asserted through the rendered heading rather than the registry default, because the owner
     * being an agent is what flips "Buyer's Info" to "Agent's Info" — reading the config would
     * assert the fallback and never exercise the override that the report was actually about.
     */
    public function test_an_agent_owned_buyer_listing_names_the_entry_agents_info(): void
    {
        $this->enableRedesign();

        $owner = User::factory()->create(['user_type' => 'agent']);

        $listing = BuyerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Agent-owned buyer request',
            'address'     => '100 Shell Street',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('first_name', 'Abby');

        $html = $this->get(route('buyer.view-auction', $listing->id))->assertOk()->getContent();

        $this->assertContains(self::OVERFLOWING_ENTRY, $this->navTargets($html));
        $this->assertStringContainsString(
            'Agent&#039;s Info',
            $html,
            "An agent-owned buyer request must name the section \"Agent's Info\"."
        );
    }

    /**
     * No id is emitted twice.
     *
     * A duplicate would make the entry's `href="#…"` ambiguous — the browser scrolls to the first
     * match — so the bar could point at the wrong copy of a section while every existing
     * agreement assertion still passed, since both sets would still contain the id.
     */
    public function test_no_section_id_is_emitted_twice(): void
    {
        $this->enableRedesign();

        $anchors = $this->anchorIds($this->renderBuyer());

        $this->assertSame(
            array_values(array_unique($anchors)),
            array_values($anchors),
            'Duplicate section ids make a nav target ambiguous: ' . implode(', ', $anchors)
        );
    }

    // ── The rules that stop it being clipped ─────────────────────────────────

    /**
     * The density rules reach the page.
     *
     * Both values are asserted as the design tokens rather than as computed lengths: the point of
     * the fix is that the bar stays on the spacing and type scale, and a literal `12px` appearing
     * here would be the first magic number in a stylesheet that has none.
     */
    public function test_the_page_ships_the_section_nav_density_rules(): void
    {
        $this->enableRedesign();

        $html = $this->renderBuyer();

        $this->assertMatchesRegularExpression(
            '/\.hla-detail-page\s+\.viho-section-nav-link\s*\{[^}]*padding-left:\s*var\(--viho-space-md\)/s',
            $html,
            'The nav link must take its tightened horizontal padding from --viho-space-md.'
        );

        $this->assertMatchesRegularExpression(
            '/\.hla-detail-page\s+\.viho-section-nav-link\s*\{[^}]*font-size:\s*var\(--viho-font-xs\)/s',
            $html,
            'The nav label must take its tightened size from --viho-font-xs.'
        );
    }

    /**
     * The ceiling that makes the density rules safe.
     *
     * Density alone left buyer within ~20px of the edge, so this is the half that guarantees the
     * outcome rather than merely buying room: above `lg` the row may wrap, and a wrapped row is
     * readable where a scrolled one was not.
     */
    public function test_the_page_ships_the_wrap_ceiling_above_lg(): void
    {
        $this->enableRedesign();

        $html = $this->renderBuyer();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*992px\)\s*\{\s*\.hla-detail-page\s+\.viho-section-nav-list\s*\{[^}]*flex-wrap:\s*wrap/s',
            $html,
            'Above lg the bar must be allowed to wrap rather than scroll a hidden overflow.'
        );

        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*992px\)\s*\{\s*\.hla-detail-page\s+\.viho-section-nav-list\s*\{[^}]*overflow-x:\s*visible/s',
            $html,
            'Wrapping only helps if the list has stopped being a scroll container.'
        );
    }

    /**
     * Small screens keep the behaviour they had.
     *
     * The wrap ceiling is deliberately bounded below by 992px — the width at which the sidebar
     * column appears and at which the clipping was reported. Horizontal scrolling remains the
     * intended small-screen behaviour, so the fix must not be written as an unconditional rule.
     */
    public function test_the_wrap_ceiling_is_bounded_below_and_does_not_apply_unconditionally(): void
    {
        $this->enableRedesign();

        $html = $this->renderBuyer();

        $this->assertDoesNotMatchRegularExpression(
            '/\.hla-detail-page\s+\.viho-section-nav-list\s*\{[^}]*flex-wrap:\s*wrap[^}]*\}(?![^{]*@media)/s',
            preg_replace('/@media[^{]*\{(?:[^{}]|\{[^{}]*\})*\}/s', '', $html),
            'flex-wrap must appear only inside the min-width: 992px query, never as a bare rule.'
        );
    }

    /**
     * With the flag off the page carries none of it.
     *
     * The rules live inside the redesign-gated half of the shared stylesheet. A flag that gates the
     * markup but leaks the stylesheet is not off; it is a page whose legacy bar has quietly been
     * restyled.
     */
    public function test_the_flag_off_page_ships_none_of_the_nav_rules(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);

        $html = $this->renderBuyer();

        // The ATTRIBUTE, not the class. `viho/styles.blade.php` is included unconditionally and
        // declares `.viho-section-nav-link` itself, so the class name is in the document's style
        // stack in both flag states — asserting on it would fail against the VIHO layer rather
        // than against the bar. HireAgentSectionNavTest reads `data-viho-section-nav` for the same
        // reason. What must be absent is the rendered bar, and only the markup carries the hook.
        $this->assertStringNotContainsString('data-viho-section-nav-link', $html, 'No bar with the flag off.');

        // The product-layer rules, by contrast, live inside the redesign-gated half of the shared
        // stylesheet, so their absence IS assertable and is the half of the claim worth making.
        $this->assertStringNotContainsString(
            '.hla-detail-page .viho-section-nav-list',
            $html,
            'No nav stylesheet rules with the flag off.'
        );
    }
}
