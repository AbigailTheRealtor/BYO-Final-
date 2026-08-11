<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M7 Phase 4 — the buyer detail page's wrapper decomposition and its section navigation.
 *
 * The landlord equivalent is HireAgentSectionNavTest. This is a separate file rather than a fifth
 * data set on that one because the two pages are at DIFFERENT POINTS of the same migration and a
 * shared fixture would have to lie about one of them: landlord has nine section cards and buyer has
 * three, so "every section has an entry" is the same invariant over deliberately different sets.
 * The count here is expected to keep changing as sections migrate, which is why every expectation
 * below is derived from sectionMeta() rather than written out.
 *
 * THREE CLAIMS, AND THEY ARE DIFFERENT CLAIMS.
 *
 * 1. THE FLAG IS REAL. With the redesign off for buyer the page must carry no bar, no anchors, no
 *    script and no offset declaration. A flag that gates the markup but leaks the anchors, or
 *    pushes the scroll listener regardless, is not off — it is merely invisible, and the difference
 *    shows up the first time something else on the page collides with it. The flag-off DOM as a
 *    whole is pinned by HireAgentSectionCardDomEquivalenceTest; what is added here is the
 *    navigation, which that file predates.
 *
 * 2. THE WRAPPER IS GONE IN THE REDESIGN BRANCH. This is the substance of the decomposition and the
 *    reason it had to happen before any further section migrates. Phase 2 gave buyer two section
 *    cards and Phase 3 made them reachable, so with the wrapper still unconditional the page
 *    rendered a card inside a card — each drawing its own border, radius and shadow, a shape the
 *    reference page has nowhere. Nesting is asserted directly rather than inferred from a card
 *    count, because a count passes just as happily when the wrapper is gone AND a section failed to
 *    render.
 *
 * 3. EVERY NAV ENTRY HAS A SECTION AND EVERY SECTION HAS AN ENTRY. The nav conditions are a
 *    hand-copied duplicate of the section conditions — the deliberate design the Phase 1 hoist
 *    enabled — and a duplicate is exactly the thing that drifts. So the assertion is not "the
 *    expected labels appear"; it is that the two sets derived from the RENDERED HTML are equal, in
 *    document order. A drift in either direction fails: a link to a section that did not render, or
 *    a section the bar forgot.
 *
 * WHAT THIS FILE DELIBERATELY DOES NOT ASSERT: that buyer offers entries for Property Preferences,
 * Purchasing Terms, Services, Additional Details, Broker Compensation, Referral or Owner Info.
 * Those seven are not migrated, carry no anchor, and an entry for one would be a link to nothing.
 * Listing Details left that list in M7 Phase 5 — it is the section the decomposition created, by
 * taking away the wrapper whose title had been serving as its heading.
 * They arrive one at a time, each in the change that gives it a card. The bare-listing case below
 * is what keeps that honest in the meantime — it fails if an id is ever emitted without an entry.
 */
class HireAgentBuyerSectionNavTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * The listing every test starts from: enough to render the page, NO section satisfied.
     *
     * Buyer listings hold `address` as a native column (CLAUDE.md's schema asymmetry) and the rest
     * as EAV meta. forceCreate because the model guards mass assignment.
     *
     * M7 PHASE 5 EMPTIED THIS FIXTURE, and the emptying is load-bearing rather than tidying. It
     * used to plant `listing_title`, `auction_type` and `expiration_date` as page furniture — and
     * all three are Listing Details keys, so once that section acquired a guard the "bare" listing
     * would have satisfied it and `test_absent_sections_are_offered_no_entries` would have been
     * asserting against a page that renders a card. What is left is `property_type`, which the
     * services formatter reads and which no section guard tests, and `first_name`, which belongs to
     * the unmigrated Owner Info section and therefore cannot produce an anchor.
     */
    private function makeListing(array $meta = []): BuyerAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'seller']);

        $listing = BuyerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Buyer section-nav listing',
            'address'     => '100 Shell Street',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('property_type', 'Residential Property');
        $listing->saveMeta('first_name', 'Abby');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /**
     * One answer per migrated section, each the cheapest field that satisfies that section's guard.
     *
     * Keyed by anchor id so the fixture and the expectations cannot drift: every test below derives
     * its expected ids from these keys rather than repeating a hand-written list, so a section added
     * here without a test, or tested without a fixture, does not silently pass.
     *
     * `auction_type` answers Listing Details. Any one of its seven keys would; this is the cheapest
     * and it is deliberately NOT `expiration_date`, which other suites plant as page furniture and
     * which would therefore read as incidental rather than as the point of the fixture.
     *
     * `offered_financing` alone answers the financing card: its guard is
     * `$hasAnyFinancingDetails || offered_financing != null`, so the second operand is enough and
     * using it states plainly that the disjunction — not the boolean — is what the nav mirrors.
     *
     * `compatibility_preferences.buyer_specific` is what builds $repRows. The key is BUYER_SPECIFIC,
     * not landlord_specific: the two pages read different sub-objects out of the same meta, and a
     * fixture copied from the landlord suite would populate nothing here and pass vacuously.
     *
     * @return array<string, array<string, string>>
     */
    private function sectionMeta(): array
    {
        return [
            'hla-section-listing-details' => [
                'auction_type' => 'Traditional',
            ],
            'hla-section-financing' => [
                'offered_financing' => json_encode(['Cash']),
            ],
            'hla-section-representation' => [
                'compatibility_preferences' => json_encode([
                    'buyer_specific' => ['primary_transaction_goal' => 'Primary residence'],
                ]),
            ],
        ];
    }

    /** Every migrated section satisfied. */
    private function everySectionMeta(): array
    {
        return array_merge(...array_values($this->sectionMeta()));
    }

    /** The anchor ids every migrated section renders, in document order. */
    private function everySectionId(): array
    {
        return array_keys($this->sectionMeta());
    }

    private function url(BuyerAgentAuction $listing): string
    {
        return route('buyer.view-auction', $listing->id);
    }

    /** The master switch AND the allowlist, because both must agree for a role to have it. */
    private function enableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => ['landlord', 'buyer'],
        ]);
    }

    private function disableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);
    }

    private function renderAsOwner(BuyerAgentAuction $listing): string
    {
        return $this->actingAs($listing->user)
            ->get($this->url($listing))
            ->assertOk()
            ->getContent();
    }

    // ── Readers over the rendered HTML ───────────────────────────────────────

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

    /**
     * Just the nav element.
     *
     * Assertions about what the bar does or does not NAME have to be scoped to the bar: the section
     * titles are ordinary vocabulary elsewhere on this page, so a page-wide string assertion would
     * be testing the wrong thing and would fail for a reason unrelated to the nav.
     */
    private function navMarkup(string $html): string
    {
        return preg_match('/<nav\b[^>]*data-viho-section-nav\b.*?<\/nav>/is', $html, $m) ? $m[0] : '';
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

    /** Whole-token class match — `contains(@class, 'viho-card')` also matches viho-card-head. */
    private function classQuery(string $class): string
    {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }

    // ── 1. The flag ──────────────────────────────────────────────────────────

    /**
     * Flag off: no bar, no anchors, no script, no offset declaration.
     *
     * Asserted against a listing where BOTH migrated sections are populated, so everything the
     * redesign would produce is suppressed by the flag alone rather than by having nothing to show.
     */
    public function test_the_flag_off_page_carries_no_navigation_at_all(): void
    {
        $this->disableRedesign();

        $html = $this->renderAsOwner($this->makeListing($this->everySectionMeta()));

        // `data-viho-section-nav` covers both halves at once: it is the attribute the bar renders
        // AND the selector the highlighting script looks for, so its absence means neither shipped.
        $this->assertStringNotContainsString('data-viho-section-nav', $html, 'No bar and no script with the flag off.');
        $this->assertStringNotContainsString('hla-section-', $html, 'No anchors with the flag off.');

        // The offset must not be DECLARED. The shared VIHO stylesheet legitimately mentions the
        // custom property — it READS it, as `var(--viho-section-nav-offset, 0px)` — because that
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

        $html = $this->renderAsOwner($this->makeListing($this->everySectionMeta()));

        $this->assertStringContainsString('data-viho-section-nav', $html);
        $this->assertStringContainsString('viho-section-nav-link', $html);
        $this->assertNotEmpty($this->navTargets($html));
    }

    /**
     * The offset the primitive refuses to guess is supplied here, at both breakpoints.
     *
     * x-viho.section-nav declares `position: sticky` and leaves `top` unset because only the host
     * page knows the height of the chrome above the bar. Buyer is a host page, so buyer answers —
     * and the answers must match landlord's, because both render through the same layout.
     */
    public function test_the_consumer_supplies_the_sticky_offset_for_both_breakpoints(): void
    {
        $this->enableRedesign();

        $html = $this->renderAsOwner($this->makeListing($this->everySectionMeta()));

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

        // The bar's own height is a SEPARATE token from the offset, and the separation is the fix
        // M7.2 shipped without: a scroll target must clear the chrome AND the bar it is scrolled
        // underneath, so reusing one value leaves the target short by the bar's height — 0px of
        // clearance on desktop. Buyer inherits the fix rather than the bug.
        $this->assertMatchesRegularExpression(
            '/--viho-section-nav-height:\s*3\.5rem/',
            $html,
            'The scroll target needs the bar height as its own token.'
        );
    }

    // ── 2. The decomposition ─────────────────────────────────────────────────

    /**
     * Flag ON: the legacy wrapper card is gone.
     *
     * The count is the whole point. Before the decomposition the page rendered the wrapper PLUS one
     * card per migrated section; now it renders the sections alone, so with every one populated the
     * reading column holds exactly as many cards as there are migrated sections and no more.
     */
    public function test_flag_on_replaces_the_wrapper_card_with_sibling_section_cards(): void
    {
        $this->enableRedesign();

        $x = $this->xpath($this->renderAsOwner($this->makeListing($this->everySectionMeta())));

        $this->assertSame(
            $this->everySectionId(),
            array_map(
                fn ($n) => $n->getAttribute('id'),
                iterator_to_array($x->query('//*[starts-with(@id, "hla-section-")]'))
            ),
            'Every migrated section must render as a card, in document order.'
        );

        $this->assertSame(
            count($this->everySectionId()),
            $x->query($this->classQuery('viho-card'))->length,
            'Exactly the section cards — the wrapper must contribute none.'
        );
    }

    /**
     * No card is nested inside another.
     *
     * This is the defect the decomposition removes, asserted directly rather than through a count:
     * a count is satisfied by the wrapper vanishing even if a section also failed to render, and
     * this is not.
     */
    public function test_section_cards_are_siblings_not_nested(): void
    {
        $this->enableRedesign();

        $x = $this->xpath($this->renderAsOwner($this->makeListing($this->everySectionMeta())));

        $this->assertSame(
            0,
            $x->query($this->classQuery('viho-card') . '//*[contains(concat(" ", normalize-space(@class), " "), " viho-card ")]')->length,
            'A section card rendered inside another card — the wrapper is still wrapping.'
        );
    }

    /**
     * Flag OFF: the wrapper is exactly as it was.
     *
     * The other direction of the same change, and the one a decomposition is most able to break.
     * HireAgentSectionCardDomEquivalenceTest pins the whole legacy DOM for all four roles; this
     * states the single-card property here too, so a failure lands in the file that changed it.
     */
    public function test_flag_off_keeps_the_single_legacy_wrapper_card(): void
    {
        $this->disableRedesign();

        $x = $this->xpath($this->renderAsOwner($this->makeListing($this->everySectionMeta())));

        $this->assertSame(
            1,
            $x->query($this->classQuery('viho-card'))->length,
            'Flag off must render the single legacy listing card.'
        );
    }

    // ── 3. The claim that matters ────────────────────────────────────────────

    /**
     * Every entry has a section and every section has an entry — both sections populated.
     *
     * Set equality in both directions, derived from the HTML rather than from a hard-coded list, so
     * this fails on drift rather than on a list somebody forgot to update. Order is asserted too:
     * the bar follows the reading order of the page, not merely the same membership.
     */
    public function test_nav_entries_and_rendered_sections_agree_exactly(): void
    {
        $this->enableRedesign();

        $html = $this->renderAsOwner($this->makeListing($this->everySectionMeta()));

        $nav     = $this->navTargets($html);
        $anchors = $this->anchorIds($html);

        $this->assertNotEmpty($nav, 'A populated listing must offer entries.');

        $this->assertSame(
            [],
            array_values(array_diff($nav, $anchors)),
            'The bar offers a section that did not render — a link to nothing.'
        );
        $this->assertSame(
            [],
            array_values(array_diff($anchors, $nav)),
            'A section rendered with no nav entry — the bar is incomplete.'
        );

        $this->assertSame($anchors, $nav, 'The bar must follow the document order of the sections.');
    }

    /**
     * The same equality with NEITHER section populated: no anchors, no entries, no bar.
     *
     * This is the half that catches an entry hard-coded into the bar, and the half that catches an
     * unmigrated section acquiring an id without acquiring an entry.
     */
    public function test_absent_sections_are_offered_no_entries(): void
    {
        $this->enableRedesign();

        $html = $this->renderAsOwner($this->makeListing());

        $this->assertSame([], $this->anchorIds($html), 'Neither section should render.');
        $this->assertSame([], $this->navTargets($html));

        // x-viho.section-nav renders nothing at all for an empty list, so there is no empty bar
        // sitting at the top of the column either.
        $this->assertSame('', $this->navMarkup($html), 'An empty list must produce no bar.');
    }

    /**
     * Each section independently: its entry appears with it and only with it.
     *
     * This is also the no-empty-card assertion, stated per section rather than as its own test. A
     * listing answering one section renders that section's card ALONE — so the other two, whose
     * guards are false, contributed no bordered, titled, empty box. That is the failure the guards
     * exist to prevent, and it is only visible when the sections are exercised one at a time.
     */
    public function test_each_section_brings_its_own_entry_and_no_others(): void
    {
        $this->enableRedesign();

        foreach ($this->sectionMeta() as $id => $meta) {
            $html = $this->renderAsOwner($this->makeListing($meta));

            $this->assertSame([$id], $this->anchorIds($html), "{$id} should be the only section.");
            $this->assertSame([$id], $this->navTargets($html), "{$id} should be the only entry.");
        }
    }

    /**
     * The complement: a listing answering the OTHER sections renders no Listing Details card.
     *
     * The per-section loop above proves each guard is sufficient. This proves the Listing Details
     * guard is necessary — that its card is absent on a page that is otherwise fully populated,
     * rather than tagging along with whatever else rendered. It is asserted for this section
     * specifically because it is the one whose rows sat unconditionally inside the wrapper before
     * M7 Phase 5, so "renders regardless" is exactly the behaviour it is coming from.
     */
    public function test_listing_details_is_absent_when_none_of_its_seven_keys_is_answered(): void
    {
        $this->enableRedesign();

        $meta = $this->everySectionMeta();
        unset($meta['auction_type']);

        $html = $this->renderAsOwner($this->makeListing($meta));

        $this->assertNotContains('hla-section-listing-details', $this->anchorIds($html), 'An empty Listing Details card rendered.');
        $this->assertNotContains('hla-section-listing-details', $this->navTargets($html), 'A dead Listing Details entry was offered.');
        $this->assertSame($this->anchorIds($html), $this->navTargets($html));
    }

    /**
     * Every one of the seven keys brings the section back on its own.
     *
     * The guard is only safe while it enumerates the section's WHOLE field set: a key the rows read
     * and the guard omits hides a card that still has a row in it. Enumerating them here is what
     * turns "the list looks complete" into something that fails when it stops being.
     */
    public function test_each_listing_details_key_renders_the_section_on_its_own(): void
    {
        $this->enableRedesign();

        foreach ([
            'listing_title'           => 'A title',
            'working_with_agent'      => 'Not represented',
            'desired_agent_hire_date' => '2026-09-01',
            'listing_date'            => '2026-09-02',
            'expiration_date'         => '2026-12-01',
            'auction_type'            => 'Traditional',
            'meeting_Preference'      => 'Video call',
        ] as $key => $value) {
            $html = $this->renderAsOwner($this->makeListing([$key => $value]));

            $this->assertSame(
                ['hla-section-listing-details'],
                $this->anchorIds($html),
                "[{$key}] renders a row, so it must render the section."
            );
            $this->assertSame(['hla-section-listing-details'], $this->navTargets($html));
        }
    }

    /**
     * The bar names no section that is not on the page.
     *
     * The seven unmigrated sections still render as sub-headings with no anchor. An entry naming one
     * would be a dead link; worse, for compensation it would name a section whose rows sit behind an
     * auth block. Asserted against the bar's own markup so the sub-headings themselves do not
     * trigger it.
     */
    public function test_the_bar_names_no_unmigrated_section(): void
    {
        $this->enableRedesign();

        $nav = $this->navMarkup($this->renderAsOwner($this->makeListing($this->everySectionMeta())));

        $this->assertNotSame('', $nav, 'Precondition: the bar rendered.');

        foreach ([
            'Property Preferences',
            'Purchasing Terms',
            'Services',
            'Additional Details',
            'Broker Compensation',
            'Referral',
            "Buyer's Info",
        ] as $unmigrated) {
            $this->assertStringNotContainsString(
                $unmigrated,
                $nav,
                "The bar must not name [{$unmigrated}] — it has no anchor to reach."
            );
        }
    }

    /**
     * A guest gets the same agreement as the owner.
     *
     * Neither migrated section is auth-gated, so the point is not that a guest sees less — it is
     * that the invariant is asserted PER VIEWER rather than once for the most privileged one. That
     * is the property that will matter the moment compensation migrates.
     */
    public function test_the_invariant_holds_for_an_anonymous_viewer(): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing($this->everySectionMeta());

        $html = $this->get($this->url($listing))->assertOk()->getContent();

        $this->assertSame($this->anchorIds($html), $this->navTargets($html));
        $this->assertNotEmpty($this->navTargets($html), 'A guest reaches both migrated sections.');
    }
}
