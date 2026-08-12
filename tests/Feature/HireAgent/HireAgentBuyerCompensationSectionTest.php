<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Buyer Broker Compensation section, after its rows moved onto x-hire-agent.field.
 *
 * WHAT WENT WRONG, AND WHY IT NEEDED A TEST OF ITS OWN
 * ---------------------------------------------------
 * Buyer reached the section resolver with its ROWS still written as legacy Bootstrap columns. Most
 * sections survived that: a legacy row is `col-md-12 col-12`, which Bootstrap sizes at 100%, so it
 * fills a line of `.hla-field-grid` and reads correctly by accident.
 *
 * Broker Compensation is the one section built from SUB-SECTIONS — six headings, each with its own
 * rows and closing rule — and two of its elements carried no width class at all: the `<h5>` and the
 * `.broker-compensation-section` wrapper. `.hla-field-grid` is `display: flex`, so a child with no
 * width is sized to its CONTENT and shares a line with its neighbour. The result was a heading
 * squeezed into a narrow left column with its own rows beside it, and the rule inside that wrapper
 * clipped to half the card.
 *
 * A separate defect sat next to it: one rule was emitted unconditionally where every other rule in
 * the section is guarded by the same condition as its heading. On a purchase listing — no lease
 * terms — it rendered directly beneath the previous rule, giving two lines with a band of empty
 * card between them.
 *
 * WHAT THIS FILE PINS. Not the pixels, which no test here can see, but the four DOM facts the
 * layout is made of: rows are field cells, headings are full-width cells, a rule never appears
 * without a sub-section to close, and the card never renders empty. Plus the two properties the
 * refactor was not allowed to disturb — the audience rules, and the flag-off markup.
 *
 * @see HireAgentSectionCardDomEquivalenceTest — the flag-off guarantee for all four roles.
 * @see HireAgentBuyerSectionNavTest — the audience tiers and the nav contract in full.
 */
class HireAgentBuyerCompensationSectionTest extends TestCase
{
    use DatabaseTransactions;

    private const CARD_ID = 'hla-section-compensation';

    /** The legacy row spelling, which the redesign must no longer emit in this card. */
    private const LEGACY_ROW_CLASS = 'col-md-12 col-12 pt-2 fw-bold';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * A purchase listing: compensation, legal, brokerage and additional terms — and NO lease terms.
     *
     * The absence is the point. This is the shape that exposed the unguarded rule, and it is also
     * the common case: most buyers are not asking about a lease-option.
     */
    private function purchaseCompensationMeta(): array
    {
        return [
            'workflow_type'             => 'hire_agent',
            'commission_structure'      => 'Percentage of the Total Purchase Price',
            'purchase_fee_type'         => 'Flat Fee',
            'purchase_fee_flat'         => '9500',
            'protection_period'         => '90',
            'brokerage_relationship'    => 'Single Agency',
            'additional_details_broker' => 'Open to discussing a tiered structure.',
        ];
    }

    /** Everything the section can show, including the lease-option sub-sections. */
    private function fullCompensationMeta(): array
    {
        return $this->purchaseCompensationMeta() + [
            'interested_lease_option'           => 'Yes',
            'lease_fee_type'                    => 'flat',
            'lease_fee_flat'                    => '1200',
            'interested_lease_option_agreement' => 'Yes',
            'lease_type'                        => 'percent',
            'lease_value'                       => '3',
            'purchase_type'                     => 'percent',
            'purchase_value'                    => '2',
            'early_termination_fee_option'      => 'Yes',
            'early_termination_fee_amount'      => '500',
            'retainer_fee_option'               => 'No',
            'agency_agreement_timeframe'        => '6 months',
        ];
    }

    private function makeListing(array $meta, User $owner): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Buyer compensation listing',
            'address'     => '100 Shell Street',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function qualifyingAgent(BuyerAgentAuction $listing): User
    {
        $agent = User::factory()->create(['user_type' => 'agent']);

        BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $listing->id,
            'user_id'                => $agent->id,
        ]);

        return $agent;
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

        return $this->get(route('buyer.view-auction', $listing->id))->assertOk()->getContent();
    }

    private function asUser(User $viewer, BuyerAgentAuction $listing): string
    {
        return $this->actingAs($viewer)
            ->get(route('buyer.view-auction', $listing->id))
            ->assertOk()
            ->getContent();
    }

    // ── DOM helpers ──────────────────────────────────────────────────────────

    private function xpath(string $html): DOMXPath
    {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    /** Whole-token class match — `contains(@class, 'x')` also matches `x-something`. */
    private function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    private function card(DOMXPath $x): ?DOMElement
    {
        $found = $x->query('//*[@id="' . self::CARD_ID . '"]');

        return $found->length ? $found->item(0) : null;
    }

    /** Every anchor id the page rendered, in document order. */
    private function anchorIds(DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query('//*[starts-with(@id, "hla-section-")]') as $n) {
            $out[] = $n->getAttribute('id');
        }

        return $out;
    }

    /** Every section the nav bar offers, in document order. */
    private function navTargets(DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query('//a[' . $this->hasClass('viho-section-nav-link') . ']') as $n) {
            $out[] = ltrim($n->getAttribute('href'), '#');
        }

        return $out;
    }

    // ── 1. The rows are field cells ──────────────────────────────────────────

    /**
     * Every row in the card is a field cell, and no legacy row survives in it.
     *
     * BOTH HALVES MATTER. Counting the new cells alone would pass while the old markup sat beside
     * them, which is the state a half-finished conversion leaves behind — and the legacy row is
     * exactly what the flex grid mis-sizes.
     */
    public function test_the_redesigned_card_renders_field_cells_and_no_legacy_rows(): void
    {
        $this->enableRedesign();

        $owner = User::factory()->create(['user_type' => 'buyer']);
        $x     = $this->xpath($this->asUser($owner, $this->makeListing($this->fullCompensationMeta(), $owner)));
        $card  = $this->card($x);

        $this->assertNotNull($card, 'The owner must reach the compensation card.');

        $this->assertGreaterThan(
            0,
            $x->query('.//*[' . $this->hasClass('hla-field') . ']', $card)->length,
            'The card must render its rows as field cells.'
        );

        $this->assertSame(
            0,
            $x->query('.//*[@class="' . self::LEGACY_ROW_CLASS . '"]', $card)->length,
            'A legacy row survived the conversion; the flex grid cannot size one correctly.'
        );
    }

    /**
     * The rows carry their real values, so the conversion moved data rather than losing it.
     *
     * Decoded, because a label now reaches the page through `{{ $label }}` and the apostrophe in
     * "Buyer's" is emitted as an entity. The text is what a reader sees either way.
     */
    public function test_the_rows_keep_their_labels_and_values(): void
    {
        $this->enableRedesign();

        $owner = User::factory()->create(['user_type' => 'buyer']);
        $html  = $this->asUser($owner, $this->makeListing($this->fullCompensationMeta(), $owner));

        $x    = $this->xpath($html);
        $text = preg_replace('/\s+/', ' ', $this->card($x)->textContent);

        foreach ([
            "Buyer's Broker Commission Structure",
            'Percentage of the Total Purchase Price',
            "Buyer's Broker Purchase Fee",
            'Protection Period Timeframe',
            'Acceptable Brokerage Relationship',
            'Single Agency',
            'Additional Terms',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text, "The card lost [{$expected}].");
        }
    }

    // ── 2. Sub-headings own their line ───────────────────────────────────────

    /**
     * Each sub-heading is a full-width cell in the redesign, and an unadorned h5 with the flag off.
     *
     * THE DEFECT IN ONE ASSERTION. A heading with no width class is content-sized in a flex row and
     * its own rows come up beside it. `col-12` is what puts it on a line of its own.
     */
    public function test_sub_headings_are_full_width_cells_only_in_the_redesign(): void
    {
        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->fullCompensationMeta(), $owner);

        $this->enableRedesign();
        $x        = $this->xpath($this->asUser($owner, $listing));
        $headings = $x->query('.//h5', $this->card($x));

        $this->assertGreaterThan(0, $headings->length, 'The card must render its sub-headings.');

        foreach ($headings as $h5) {
            $this->assertStringContainsString(
                'col-12',
                $h5->getAttribute('class'),
                'A sub-heading with no width class shares its line with the rows below it.'
            );
        }

        $this->disableRedesign();
        $legacy = $this->xpath($this->asUser($owner, $listing));

        foreach ($legacy->query('//h5[' . $this->hasClass('mt-3') . ']') as $h5) {
            $this->assertSame(
                'mt-3 mb-2',
                $h5->getAttribute('class'),
                'Flag off must keep the original heading class exactly.'
            );
        }
    }

    // ── 3. No rule without a sub-section, no empty card ──────────────────────

    /**
     * A separator never renders with nothing between it and the one before it.
     *
     * Walks the card's rendered children in order and fails on two rules in a row. That is the
     * shape the unguarded rule produced on a purchase listing, and it is the one a reader sees as
     * a band of empty card.
     */
    public function test_no_separator_renders_without_a_sub_section_to_close(): void
    {
        $this->enableRedesign();

        $owner = User::factory()->create(['user_type' => 'buyer']);
        $x     = $this->xpath($this->asUser($owner, $this->makeListing($this->purchaseCompensationMeta(), $owner)));
        $card  = $this->card($x);

        $this->assertNotNull($card, 'The owner must reach the compensation card.');

        $sawRule = false;

        foreach ($x->query('.//div[' . $this->hasClass('my-3') . ' or ' . $this->hasClass('hla-field') . ' or self::h5]', $card) as $node) {
            $isRule = $x->query('.//hr', $node)->length > 0;

            if ($isRule && $sawRule) {
                $this->fail('Two separators in a row: a sub-section rendered its rule with no content.');
            }

            $sawRule = $isRule;
        }

        // And the card must not END on a rule, which is the same defect at the foot of the card.
        $rules = $x->query('.//hr', $card);
        if ($rules->length) {
            $last     = $rules->item($rules->length - 1);
            $trailing = $x->query('following::*[' . $this->hasClass('hla-field') . '][ancestor::*[@id="' . self::CARD_ID . '"]]', $last);

            $this->assertGreaterThan(0, $trailing->length, 'The card ends on a separator with nothing after it.');
        }
    }

    /**
     * The card renders only when it has rows, and when it renders it has them.
     *
     * A heading and a rule with no fields between them is an empty card wearing a section's
     * clothes — the resolver is handed a visibility answer per section precisely so that cannot
     * happen, and this asserts the answer is honest for this one.
     */
    public function test_the_card_is_absent_when_empty_and_populated_when_present(): void
    {
        $this->enableRedesign();

        $owner = User::factory()->create(['user_type' => 'buyer']);

        // Nothing but the workflow marker: no compensation data of any kind.
        $bare = $this->xpath($this->asUser($owner, $this->makeListing(['workflow_type' => 'hire_agent'], $owner)));

        $this->assertNull($this->card($bare), 'A listing with no compensation data must render no card.');
        $this->assertNotContains(self::CARD_ID, $this->navTargets($bare), 'The nav must not offer an absent card.');

        $owner2 = User::factory()->create(['user_type' => 'buyer']);
        $rich   = $this->xpath($this->asUser($owner2, $this->makeListing($this->purchaseCompensationMeta(), $owner2)));

        $this->assertNotNull($this->card($rich), 'A listing with compensation data must render the card.');
        $this->assertGreaterThan(
            0,
            $rich->query('.//*[' . $this->hasClass('hla-field') . ']', $this->card($rich))->length,
            'The card rendered with no rows in it.'
        );
    }

    // ── 4. The audience rules the refactor must not have moved ───────────────

    /**
     * The public — anonymous, and a logged-in agent with no relationship — reads none of it.
     *
     * Asserted on the TEXT as well as the anchor, because a leak that dropped the card but kept its
     * rows would satisfy an id check and still publish the terms.
     */
    public function test_the_public_cannot_see_the_compensation_section(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->fullCompensationMeta(), $owner);

        $stranger = User::factory()->create(['user_type' => 'agent']);

        foreach ([
            'guest'            => $this->asGuest($listing),
            'unrelated agent'  => $this->asUser($stranger, $listing),
        ] as $who => $html) {
            $x = $this->xpath($html);

            $this->assertNull($this->card($x), "[{$who}] reached the compensation card.");
            $this->assertNotContains(self::CARD_ID, $this->anchorIds($x), "[{$who}] reached the anchor.");
            $this->assertNotContains(self::CARD_ID, $this->navTargets($x), "[{$who}] was offered the section by name.");

            $this->assertStringNotContainsString(
                "Buyer's Broker Commission Structure",
                html_entity_decode($html, ENT_QUOTES),
                "[{$who}] read a compensation row."
            );
        }
    }

    /**
     * The owner and a qualifying agent both read it, with its rows.
     *
     * The owner is the viewer the section exists for — these are the terms proposals are measured
     * against — and an agent who has proposed reads everything the owner reads.
     */
    public function test_the_owner_and_a_qualifying_agent_both_read_it(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->fullCompensationMeta(), $owner);
        $agent   = $this->qualifyingAgent($listing);

        foreach (['owner' => $owner, 'qualifying agent' => $agent] as $who => $viewer) {
            $html = $this->asUser($viewer, $listing);
            $x    = $this->xpath($html);

            $this->assertNotNull($this->card($x), "[{$who}] must reach the compensation card.");
            $this->assertContains(self::CARD_ID, $this->navTargets($x), "[{$who}] must be offered the section.");

            $this->assertStringContainsString(
                "Buyer's Broker Commission Structure",
                html_entity_decode($html, ENT_QUOTES),
                "[{$who}] must read the rows, not just the card."
            );
        }
    }

    // ── 5. The nav still describes the page ──────────────────────────────────

    /**
     * Every section the bar offers rendered, and every section that rendered is offered.
     *
     * The refactor changed what the compensation card CONTAINS, and a section that fell out of the
     * document while staying in the bar would be a dead link in the most prominent place on the
     * page. Checked for both tiers that can see this card.
     */
    public function test_the_nav_and_the_rendered_sections_still_agree(): void
    {
        $this->enableRedesign();

        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->makeListing($this->fullCompensationMeta(), $owner);
        $agent   = $this->qualifyingAgent($listing);

        foreach (['owner' => $owner, 'qualifying agent' => $agent] as $who => $viewer) {
            $x = $this->xpath($this->asUser($viewer, $listing));

            $this->assertSame(
                $this->anchorIds($x),
                $this->navTargets($x),
                "[{$who}]: the bar and the document disagree about which sections exist."
            );
        }
    }

    // ── 6. Flag off is untouched ─────────────────────────────────────────────

    /**
     * With the flag off the section keeps its legacy markup exactly.
     *
     * The rows go back to being Bootstrap columns, the wrapper the redesign drops is present, and
     * none of the redesign's cell classes appear. HireAgentSectionCardDomEquivalenceTest holds this
     * line for the page as a whole; this narrows it to the section that changed.
     */
    public function test_flag_off_keeps_the_legacy_compensation_markup(): void
    {
        $this->disableRedesign();

        $owner = User::factory()->create(['user_type' => 'buyer']);
        $html  = $this->asUser($owner, $this->makeListing($this->fullCompensationMeta(), $owner));
        $x     = $this->xpath($html);

        $this->assertNull($this->card($x), 'Flag off must emit no section anchor.');

        $this->assertGreaterThan(
            0,
            $x->query('//*[@class="' . self::LEGACY_ROW_CLASS . '"]')->length,
            'Flag off must keep the legacy row markup.'
        );

        $this->assertGreaterThan(
            0,
            $x->query('//div[' . $this->hasClass('broker-compensation-section') . ']')->length,
            'Flag off must keep the legacy compensation wrapper.'
        );

        $this->assertSame(
            0,
            $x->query('//*[' . $this->hasClass('hla-field') . ']')->length,
            'Flag off must emit none of the redesign cell classes.'
        );

        $this->assertStringContainsString(
            "Buyer's Broker Commission Structure",
            html_entity_decode($html, ENT_QUOTES),
            'Flag off must still render the rows.'
        );
    }
}
