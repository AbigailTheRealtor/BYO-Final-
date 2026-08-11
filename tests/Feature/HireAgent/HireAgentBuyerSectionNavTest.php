<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The buyer detail page, rendered — the first role wired to the section resolver.
 *
 * HireAgentSectionRegistryTest proves the registry and the resolver agree about who may read what.
 * This file proves the PAGE does: that the tiers the registry declares actually reach the browser,
 * for real viewers, on real listings, with the audience resolved by the controller rather than
 * asserted into place.
 *
 * THE FOUR VIEWERS, AND WHY EACH IS HERE:
 *
 *   guest             — anonymous. The route carries no auth, so this is a real visitor.
 *   unrelated agent   — an agent user_type with no connection to this listing. THE DISCRIMINATING
 *                       CASE: they are the reason "agent" is a relationship and not a user type,
 *                       and they must read exactly what the guest reads.
 *   owner             — the client evaluating proposals. Adds Services and Broker Compensation.
 *   qualifying agent  — has proposed on this listing. Adds Referral and Agent Credentials.
 *
 * EVERY DENIAL IS ASSERTED NEXT TO ITS COMPLEMENT. "A guest does not see Broker Compensation"
 * passes just as happily when the section is broken for everybody, which is the vacuous pass
 * HireAgentDetailViewPrivacyTest records falling into for real. So each withheld section is also
 * shown to arrive for the viewer who should have it, built from the same fixture.
 *
 * WHAT THIS FILE IS NOT ABOUT. The per-bid proposal cards carry the AGENT's own services and
 * compensation and are narrowed by HireAgentProposalAccess. They are a different body of data with
 * a similar name, they are untouched by the registry, and there is one test at the end that says
 * so rather than leaving it to inference.
 */
class HireAgentBuyerSectionNavTest extends TestCase
{
    use DatabaseTransactions;

    /** Sections every viewer of a populated listing reads. */
    private const PUBLIC_SECTIONS = [
        'hla-section-listing-details',
        'hla-section-property',
        'hla-section-terms',
        'hla-section-financing',
        'hla-section-additional-details',
        'hla-section-representation',
        'hla-section-role-info',
    ];

    /** What the owner tier adds — the material proposals are evaluated against. */
    private const PARTICIPANT_SECTIONS = ['hla-section-services', 'hla-section-compensation'];

    /** What the agent tier adds on top — agent-to-agent business. */
    private const AGENT_SECTIONS = ['hla-section-referral', 'hla-section-agent-credentials'];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * Every section populated, keyed by the anchor it produces.
     *
     * Keyed so the expectations below are derived from the fixture rather than hand-written twice.
     * `agent-credentials` is absent here on purpose: it depends on the listing OWNER being an
     * agent, not on listing meta, so it is supplied by the fixture that creates such an owner.
     */
    private function sectionMeta(): array
    {
        return [
            'hla-section-listing-details'    => ['auction_type' => 'Traditional'],
            'hla-section-property'           => ['cities' => json_encode(['Tampa, FL'])],
            'hla-section-terms'              => ['maximum_budget' => '500000'],
            'hla-section-financing'          => ['offered_financing' => json_encode(['Cash'])],
            'hla-section-additional-details' => ['additional_details' => 'Evenings only.'],
            'hla-section-representation'     => ['compatibility_preferences' => json_encode([
                'buyer_specific' => ['primary_transaction_goal' => 'Primary residence'],
            ])],
            'hla-section-role-info'          => ['first_name' => 'Abby'],
            'hla-section-services'           => ['services' => json_encode(['List on the MLS'])],
            'hla-section-compensation'       => ['commission_structure' => 'Percentage'],
            'hla-section-referral'           => ['referral_percentage' => '25'],
        ];
    }

    private function everySectionMeta(): array
    {
        return array_merge(...array_values($this->sectionMeta()));
    }

    private function makeListing(array $meta = [], ?User $owner = null): BuyerAgentAuction
    {
        $owner ??= User::factory()->create(['user_type' => 'buyer']);

        $listing = BuyerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Buyer section listing',
            'address'     => '100 Shell Street',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        // NOTHING BEYOND workflow_type. `property_type` was here as page furniture and had to go:
        // it renders an "Acceptable Property Type" row, so it belongs to the property guard, and
        // planting it on every fixture made the property card render on listings meant to be bare.
        // The services formatter reads it with a 'Residential' fallback, so its absence is safe.
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /** An agent who has proposed on this listing, and therefore reads it as the agent audience. */
    private function qualifyingAgent(BuyerAgentAuction $listing, string $type = 'agent'): User
    {
        $agent = User::factory()->create(['user_type' => $type]);

        BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $listing->id,
            'user_id'                => $agent->id,
        ]);

        return $agent;
    }

    private function url(BuyerAgentAuction $listing): string
    {
        return route('buyer.view-auction', $listing->id);
    }

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

    /** Guards must be CLEARED, not merely omitted — actingAs persists for the whole test method. */
    private function asGuest(BuyerAgentAuction $listing): string
    {
        auth()->logout();
        $this->app->get('auth')->forgetGuards();
        $this->assertGuest();

        return $this->get($this->url($listing))->assertOk()->getContent();
    }

    private function asUser(User $viewer, BuyerAgentAuction $listing): string
    {
        return $this->actingAs($viewer)->get($this->url($listing))->assertOk()->getContent();
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

    private function classQuery(string $class): string
    {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }

    /** Does this section card contain anything at all beyond its header? */
    private function cardHasContent(DOMXPath $x, string $id): bool
    {
        $card = $x->query("//*[@id='{$id}']")->item(0);

        if ($card === null) {
            return false;
        }

        $body = $x->query('.//*[contains(concat(" ", normalize-space(@class), " "), " hla-field-grid ")]', $card)->item(0);

        return $body !== null && trim($body->textContent) !== '';
    }

    // ── 1. Legacy is untouched ───────────────────────────────────────────────

    /**
     * Flag off: no bar, no anchors, no script, no offset, one card, the original headings.
     *
     * Asserted against a fully populated listing viewed by the OWNER — the widest audience — so
     * everything the redesign would produce is suppressed by the flag alone rather than by having
     * nothing to show or nobody to show it to.
     */
    public function test_the_flag_off_page_is_unchanged(): void
    {
        $this->disableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->everySectionMeta(), $owner);
        $html    = $this->asUser($owner, $listing);

        $this->assertStringNotContainsString('data-viho-section-nav', $html, 'No bar and no script.');
        $this->assertStringNotContainsString('hla-section-', $html, 'No anchors.');
        $this->assertDoesNotMatchRegularExpression('/--viho-section-nav-offset:\s*\d/', $html, 'No sticky offset.');

        $x = $this->xpath($html);

        $this->assertSame(1, $x->query($this->classQuery('viho-card'))->length, 'The single legacy listing card.');

        $headings = [];
        foreach ($x->query($this->classQuery('viho-section-header-title')) as $node) {
            $headings[] = trim(preg_replace('/\s+/', ' ', $node->textContent));
        }

        $this->assertSame([
            'Listing Details:',
            'Property Preferences:',
            'Purchasing Terms:',
            'Financing Details:',
            'Services:',
            'Additional Details:',
            'Representation Preferences & Compatibility:',
            'Broker Compensation & Agency Agreement Terms:',
            'Referral & Cooperation Terms',
            "Buyer's Info",
        ], $headings, 'The flag-off headings and their order must not move.');
    }

    /** Flag off shows Services and Compensation to everyone, exactly as it always has. */
    public function test_the_flag_off_page_still_shows_services_and_compensation_to_a_guest(): void
    {
        $this->disableRedesign();

        $html = $this->asGuest($this->makeListing($this->everySectionMeta()));

        // Compared against the DECODED document text: Blade escapes `&` to `&amp;` and `'` to
        // `&#039;`, so a raw-markup search for a heading containing either would fail for a reason
        // that has nothing to do with visibility.
        $text = $this->xpath($html)->document->textContent;

        $this->assertStringContainsString('Services:', $text);
        $this->assertStringContainsString('Referral & Cooperation Terms', $text);
    }

    // ── 2. The public tier ───────────────────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function publicViewers(): array
    {
        return ['guest' => ['guest'], 'unrelated buyer' => ['buyer'], 'unrelated agent' => ['agent']];
    }

    /**
     * A public viewer reads the request and nothing private.
     *
     * The 'unrelated agent' case is the discriminating one: an agent user_type with no relationship
     * to this listing must read exactly what an anonymous visitor reads. If being an agent were
     * sufficient, this is the row that would fail while every other assertion still passed.
     *
     * @dataProvider publicViewers
     */
    public function test_a_public_viewer_sees_no_private_section(string $viewer): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing($this->everySectionMeta());

        $html = $viewer === 'guest'
            ? $this->asGuest($listing)
            : $this->asUser(User::factory()->create(['user_type' => $viewer]), $listing);

        $anchors = $this->anchorIds($html);

        $this->assertSame(self::PUBLIC_SECTIONS, $anchors, "{$viewer}: wrong section set.");

        foreach (array_merge(self::PARTICIPANT_SECTIONS, self::AGENT_SECTIONS) as $private) {
            $this->assertNotContains($private, $anchors, "{$viewer} reached [{$private}].");
            $this->assertNotContains($private, $this->navTargets($html), "{$viewer} was offered [{$private}].");
        }

        // The bar must not NAME them either — an entry is a disclosure in its own right.
        $nav = $this->navMarkup($html);
        foreach (['Services', 'Broker Compensation', 'Referral', 'Agent Credentials'] as $label) {
            $this->assertStringNotContainsString($label, $nav, "{$viewer}: the bar named [{$label}].");
        }
    }

    /** And the page body carries no compensation copy for them either. */
    public function test_a_public_viewer_is_served_no_compensation_content(): void
    {
        $this->enableRedesign();

        $html = $this->asGuest($this->makeListing($this->everySectionMeta()));

        $this->assertStringNotContainsString('Broker Compensation & Agency Agreement Terms', $html);
        $this->assertStringNotContainsString("Buyer's Broker Commission Structure", $html);
    }

    // ── 3. The owner tier ────────────────────────────────────────────────────

    /**
     * The owner reads everything public plus Services and Broker Compensation — the material a
     * proposal is measured against — and NOT the agent-to-agent appendix.
     */
    public function test_the_owner_sees_services_and_compensation_but_not_the_agent_sections(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->everySectionMeta(), $owner);
        $html    = $this->asUser($owner, $listing);

        $anchors = $this->anchorIds($html);

        foreach (self::PARTICIPANT_SECTIONS as $private) {
            $this->assertContains($private, $anchors, "The owner must reach [{$private}].");
            $this->assertContains($private, $this->navTargets($html), "The owner must be offered [{$private}].");
        }

        foreach (self::AGENT_SECTIONS as $agentOnly) {
            $this->assertNotContains($agentOnly, $anchors, "The owner reached [{$agentOnly}].");
        }

        $this->assertStringContainsString("Buyer's Broker Commission Structure", $html, 'The rows, not just the card.');
    }

    // ── 4. The agent tier ────────────────────────────────────────────────────

    /**
     * A qualifying agent reads everything, including Referral & Cooperation.
     *
     * Agent Credentials needs an AGENT-OWNED listing and is asserted separately below; on a
     * client-owned request there is no agent whose credentials it could show, so its absence here
     * is correct rather than a gap.
     */
    public function test_a_qualifying_agent_sees_services_compensation_and_referral(): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing($this->everySectionMeta());
        $agent   = $this->qualifyingAgent($listing);
        $html    = $this->asUser($agent, $listing);

        $anchors = $this->anchorIds($html);

        foreach (array_merge(self::PARTICIPANT_SECTIONS, ['hla-section-referral']) as $key) {
            $this->assertContains($key, $anchors, "A qualifying agent must reach [{$key}].");
            $this->assertContains($key, $this->navTargets($html), "…and be offered it.");
        }

        $this->assertStringContainsString('Referral Fee:', $html);
    }

    /** All three agent user types qualify — not just 'agent'. */
    public function test_every_agent_user_type_reaches_the_agent_sections(): void
    {
        foreach (['agent', 'buyer_agent', 'seller_agent'] as $type) {
            $this->enableRedesign();

            $listing = $this->makeListing($this->everySectionMeta());
            $agent   = $this->qualifyingAgent($listing, $type);

            $this->assertContains(
                'hla-section-referral',
                $this->anchorIds($this->asUser($agent, $listing)),
                "[{$type}] must reach the agent sections."
            );
        }
    }

    /**
     * Agent Credentials renders on an AGENT-OWNED request, for a qualifying agent only.
     *
     * The whole section in one test, because its two halves are inseparable: it needs an agent
     * owner to have anything to show, and an agent viewer to be allowed to show it.
     */
    public function test_agent_credentials_renders_for_an_agent_viewing_an_agent_owned_request(): void
    {
        $this->enableRedesign();

        $agentOwner = User::factory()->create(['user_type' => 'seller_agent']);
        $agentOwner->saveMeta('brokerage', 'Acme Realty');
        $agentOwner->saveMeta('license_no', 'SL123456');

        $listing = $this->makeListing($this->everySectionMeta(), $agentOwner);
        $agent   = $this->qualifyingAgent($listing);

        $html = $this->asUser($agent, $listing);

        $this->assertContains('hla-section-agent-credentials', $this->anchorIds($html));
        $this->assertContains('hla-section-agent-credentials', $this->navTargets($html));
        $this->assertStringContainsString('Acme Realty', $html);
        $this->assertStringContainsString('SL123456', $html);

        // The heading above it agrees that the owner is an agent — one opinion per page.
        $this->assertStringContainsString("Agent's Info", $this->xpath($html)->document->textContent);

        // A guest on the same listing gets neither the credentials nor the licence number.
        $guestHtml = $this->asGuest($listing);
        $this->assertNotContains('hla-section-agent-credentials', $this->anchorIds($guestHtml));
        $this->assertStringNotContainsString('SL123456', $guestHtml, 'A licence number leaked to an anonymous visitor.');
    }

    /** A client-owned request has no credentials section, whoever asks. */
    public function test_agent_credentials_is_absent_when_the_owner_is_not_an_agent(): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing($this->everySectionMeta());
        $agent   = $this->qualifyingAgent($listing);

        $this->assertNotContains('hla-section-agent-credentials', $this->anchorIds($this->asUser($agent, $listing)));
    }

    // ── 5. The invariant, per audience ───────────────────────────────────────

    /**
     * Every entry has a section and every section has an entry — for every viewer.
     *
     * Derived from the rendered HTML in both directions, so a drift fails rather than a hand-written
     * list going stale. Asserted PER AUDIENCE because that is where an audience-gated section with
     * an ungated nav entry would show up: as a bar naming something the page did not render.
     */
    public function test_the_bar_and_the_sections_agree_for_every_audience(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->everySectionMeta(), $owner);
        $agent   = $this->qualifyingAgent($listing);
        $other   = User::factory()->create(['user_type' => 'agent']);

        $pages = [
            'owner'           => $this->asUser($owner, $listing),
            'agent'           => $this->asUser($agent, $listing),
            'unrelated agent' => $this->asUser($other, $listing),
            'guest'           => $this->asGuest($listing),
        ];

        foreach ($pages as $label => $html) {
            $this->assertSame(
                $this->anchorIds($html),
                $this->navTargets($html),
                "{$label}: the bar and the rendered sections disagree."
            );
            $this->assertNotEmpty($this->navTargets($html), "{$label}: a populated listing must offer entries.");
        }
    }

    /** No card is nested inside another, for any audience. */
    public function test_section_cards_are_siblings_not_nested(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->everySectionMeta(), $owner);

        $x = $this->xpath($this->asUser($owner, $listing));

        $this->assertSame(
            0,
            $x->query($this->classQuery('viho-card') . '//*[contains(concat(" ", normalize-space(@class), " "), " viho-card ")]')->length,
            'A section card rendered inside another card.'
        );
    }

    /** A bare listing renders no section and no bar. */
    public function test_a_bare_listing_offers_nothing(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing([], $owner);
        $html    = $this->asUser($owner, $listing);

        $this->assertSame([], $this->anchorIds($html));
        $this->assertSame([], $this->navTargets($html));
        $this->assertSame('', $this->navMarkup($html), 'An empty list must produce no bar.');
    }

    /**
     * Each section, alone: it renders, it is offered, and no other section comes with it.
     *
     * VIEWED BY A QUALIFYING AGENT, because that is the only tier that admits every section. Using
     * the owner would have withheld the agent-only sections and made those rows fail for the right
     * reason at the wrong assertion — the tier rules are exercised above, and what this test is
     * about is that one populated section produces exactly one card and one entry.
     */
    public function test_each_section_brings_its_own_entry_and_no_others(): void
    {
        $this->enableRedesign();

        foreach ($this->sectionMeta() as $id => $meta) {
            $listing = $this->makeListing($meta);
            $viewer  = $this->qualifyingAgent($listing);
            $html    = $this->asUser($viewer, $listing);

            $this->assertSame([$id], $this->anchorIds($html), "{$id} should be the only section.");
            $this->assertSame([$id], $this->navTargets($html), "{$id} should be the only entry.");
        }
    }

    // ── 6. The guards are complete, and produce no empty card ────────────────

    /**
     * Every key the Listing Details rows read brings the section back on its own, with content.
     *
     * The guard is only safe while it enumerates the section's WHOLE field set: a key the rows read
     * and the guard omits hides a card that still has a row in it. Asserting CONTENT as well as
     * presence catches the other direction — a key listed in the guard that cannot actually produce
     * a row would render a bordered, titled, empty box.
     */
    public function test_every_listing_details_key_renders_a_non_empty_section(): void
    {
        $this->assertKeysRenderSection('hla-section-listing-details', [
            'listing_title'           => 'A title',
            'working_with_agent'      => 'Not represented',
            'desired_agent_hire_date' => '2026-09-01',
            'listing_date'            => '2026-09-02',
            'expiration_date'         => '2026-12-01',
            'auction_type'            => 'Traditional',
            'meeting_Preference'      => 'Video call',
        ]);
    }

    /** The same for Purchasing Terms. */
    public function test_every_terms_key_renders_a_non_empty_section(): void
    {
        $this->assertKeysRenderSection('hla-section-terms', [
            'sale_provision'      => json_encode(['Subject to inspection']),
            'maximum_budget'      => '500000',
            'target_closing_date' => '2026-10-01',
        ]);
    }

    /**
     * And for Property Preferences, whose guard is the largest on the page.
     *
     * Forty-odd keys across three sub-blocks that share one card. This is the guard most likely to
     * have a hole in it and the one where a hole is most expensive, because the section is the
     * substance of a buyer's request.
     */
    public function test_every_property_key_renders_a_non_empty_section(): void
    {
        $this->assertKeysRenderSection('hla-section-property', [
            'cities'                         => json_encode(['Tampa, FL']),
            'counties'                       => json_encode(['Hillsborough']),
            'zipCodes'                       => json_encode(['33601']),
            'property_type'                  => 'Commercial Property',
            'property_items'                 => json_encode(['Warehouse']),
            'condition_prop_buyer'           => 'Any',
            'other_property_condition'       => 'Needs a roof',
            'business_type'                  => json_encode(['Retail']),
            'business_type_selected'         => 'Retail',
            'other_property_items'           => 'Silo',
            'state'                          => 'FL',
            'bedrooms'                       => '3',
            'bathrooms'                      => '2',
            'minimum_heated_square'          => '1500',
            'total_acreage'                  => '2',
            'carport_needed'                 => 'Yes',
            'garage_needed'                  => 'Yes',
            'garage_parking_spaces'          => '2',
            'view_preference'                => json_encode(['Water']),
            'leasing_55_plus'                => 'No',
            'non_negotiable_amenities'       => json_encode(['Pool']),
            'pets'                           => 'Yes',
            'real_estate_purchase'           => 'Business and real estate',
            'assets'                         => json_encode(['Equipment']),
            'unit_size'                      => '4',
            'number_of_unit_type'            => json_encode(['Duplex']),
            'minimum_annual_net_income'      => '50000',
            'minimum_cap_rate'               => '6',
            'preferance_details'             => 'Corner lot preferred',
        ]);
    }

    /**
     * @param  array<string, string>  $keys  meta key => a value that makes its row render
     */
    private function assertKeysRenderSection(string $id, array $keys): void
    {
        $this->enableRedesign();

        foreach ($keys as $key => $value) {
            $owner   = User::factory()->create(['user_type' => 'buyer']);
            $listing = $this->makeListing([$key => $value], $owner);
            $html    = $this->asUser($owner, $listing);

            $this->assertSame(
                [$id],
                $this->anchorIds($html),
                "[{$key}] renders a row in {$id}, so it must render that section and only that one."
            );

            $this->assertTrue(
                $this->cardHasContent($this->xpath($html), $id),
                "[{$key}] made {$id} render an EMPTY card — the guard lists a key that produces no row."
            );
        }
    }

    // ── 7. The bid cards are a different surface, and are untouched ──────────

    /**
     * The proposal cards still behave exactly as HireAgentProposalAccess decides.
     *
     * The registry governs the LISTING's services and compensation. Each agent's PROPOSAL carries
     * its own, on the per-bid cards, and those are narrowed server-side. Stated here because the
     * two are easy to conflate and this migration is precisely where conflating them would show.
     */
    public function test_the_proposal_cards_are_unchanged_by_the_section_migration(): void
    {
        $this->enableRedesign();

        $owner    = User::factory()->create(['user_type' => 'buyer']);
        $listing  = $this->makeListing($this->everySectionMeta(), $owner);
        $mine     = $this->qualifyingAgent($listing);
        $rival    = $this->qualifyingAgent($listing);

        $ownerHtml = $this->asUser($owner, $listing);
        $rivalHtml = $this->asUser($rival, $listing);

        // The owner is served both proposals; a competing agent is served only their own.
        $countBids = function (string $html): int {
            preg_match_all('/data-target="bidCollapse-(\d+)"/', $html, $m);

            return count(array_unique($m[1] ?? []));
        };

        $this->assertSame(2, $countBids($ownerHtml), 'The owner reviews every proposal.');
        $this->assertSame(1, $countBids($rivalHtml), 'A competing agent sees only their own.');

        // And the redesign did not remove the surface for the owner.
        $this->assertStringContainsString('hla-bid-accordion-header', $ownerHtml);
        $this->assertNotSame(0, $mine->id);
    }
}
