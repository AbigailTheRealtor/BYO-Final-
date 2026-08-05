<?php

namespace Tests\Feature\HireAgent;

use App\Models\LandlordAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M7.4 — field presentation parity with the Offer Listing detail page.
 *
 * The milestone makes two claims and they fail in different ways, so they are asserted separately.
 *
 * THE FIRST IS THE SHAPE. A row that used to read "Label: value" on one full-width line now renders
 * as a 5/7 label/value cell, two to a line, which is the grid the reference page uses. The
 * assertion is on the rendered classes rather than on a screenshot, because the thing that must not
 * regress is that the row went through the shared primitive at all — a row that hand-rolled the
 * same two columns would look right and would drift the moment the primitive changed.
 *
 * THE SECOND IS ABSENCE, and it is the one worth the most care. "Only display fields that have an
 * actual answer" is easy to satisfy for null and easy to get wrong for everything else: the empty
 * string, the literal string "null" that this schema stores when a question was skipped, an empty
 * multi-select array, and a meta key that was never written at all. Each of those reached the page
 * as a labelled row with nothing after the colon, or as a blank line holding space open. So the
 * test does not merely assert the value is missing — it asserts THE LABEL IS MISSING TOO, which is
 * the difference between a hidden value and a hidden row.
 *
 * WHY THE ASSERTIONS RUN AGAINST XPath AND NOT str_contains. A label that is absent from the row
 * but present in the section navigation, a script tag or an aria-label would satisfy a substring
 * search while still rendering the empty row this milestone exists to remove.
 *
 * FLAG OFF IS ASSERTED HERE TOO, because every one of these rows is shared markup: the same
 * component renders the legacy line, and a change that improved the redesign by quietly altering
 * what a non-pilot role emits would pass every redesign assertion in this file.
 */
class HireAgentFieldPresentationTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * A landlord listing carrying only the meta it is given.
     *
     * forceCreate because the model guards mass assignment, and saveMeta because landlord listings
     * hold these fields as EAV meta rather than native columns — CLAUDE.md's schema asymmetry.
     */
    private function listing(array $meta = []): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'seller']);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Landlord field-presentation listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing;
    }

    private function render(LandlordAgentAuction $listing): string
    {
        $this->actingAs($listing->user);

        return $this->get(route('landlord.agent.auction.view', $listing->id))
            ->assertOk()
            ->getContent();
    }

    private function enableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);
    }

    private function disableRedesign(): void
    {
        config(['hire_agent_detail.redesign_enabled' => false]);
    }

    // ── Readers over the rendered HTML ───────────────────────────────────────

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    /** The label texts of every 5/7 grid row on the page. */
    private function gridLabels(string $html): array
    {
        $x   = $this->xpath($html);
        $out = [];

        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-kv-label ')]") as $node) {
            $out[] = trim(preg_replace('/\s+/', ' ', $node->textContent));
        }

        return $out;
    }

    /** The text of every legacy bold row on the page. */
    private function legacyRows(string $html): array
    {
        $x   = $this->xpath($html);
        $out = [];

        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' removeBold ')]/..") as $node) {
            $out[] = trim(preg_replace('/\s+/', ' ', $node->textContent));
        }

        return $out;
    }

    // ── The shape ────────────────────────────────────────────────────────────

    /** An answered field renders as a 5/7 grid row carrying the label and the value. */
    public function test_an_answered_field_renders_as_a_grid_row(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        $this->assertContains(
            'Current Representation Status with Broker',
            $this->gridLabels($html),
            'An answered field must render through the shared 5/7 primitive.'
        );

        $x = $this->xpath($html);
        $this->assertGreaterThan(
            0,
            $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-kv-split ')]")->length,
            'The row must use the split layout, not the inline one.'
        );
    }

    /** The label carries no trailing colon in the redesign — the reference page has none. */
    public function test_a_grid_label_carries_no_trailing_colon(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        foreach ($this->gridLabels($html) as $label) {
            $this->assertStringEndsNotWith(':', $label, "Grid label [{$label}] must not carry a colon.");
        }
    }

    // ── Absence ──────────────────────────────────────────────────────────────

    /**
     * Every shape of "no answer" hides the WHOLE row — label included.
     *
     * Each case is a value this schema genuinely stores. 'null' as a string is the one most likely
     * to be missed by a hand-written guard, because `!= null` is true for it.
     */
    public function dataProviderForAbsentValues(): array
    {
        return [
            'null'          => [null],
            'empty string'  => [''],
            'whitespace'    => ['   '],
            'literal null'  => ['null'],
            'empty array'   => [[]],
        ];
    }

    /** @dataProvider dataProviderForAbsentValues */
    public function test_an_unanswered_field_renders_no_row_at_all($absent): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => $absent]));

        $this->assertNotContains(
            'Current Representation Status with Broker',
            $this->gridLabels($html),
            'An unanswered field must not render its label.'
        );
    }

    /** A meta key that was never written renders nothing, rather than an empty row. */
    public function test_a_missing_meta_key_renders_no_row(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing());

        $this->assertNotContains(
            'Current Representation Status with Broker',
            $this->gridLabels($html),
            'A field whose meta was never written must not render its label.'
        );
    }

    /** No grid row is ever emitted with a label and no value beside it. */
    public function test_no_grid_row_is_emitted_with_an_empty_value(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'working_with_agent' => 'Not Represented',
            'auction_type'       => '',
            'meeting_Preference' => 'null',
        ]));

        $x = $this->xpath($html);

        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-kv-value ')]") as $node) {
            $this->assertNotSame(
                '',
                trim($node->textContent),
                'A grid row rendered a label with nothing beside it.'
            );
        }
    }

    // ── Pill runs ────────────────────────────────────────────────────────────

    /**
     * A multi-select answer renders as a stacked pill run, not as a value in the 5/7 column.
     *
     * Three things are asserted and they are three different regressions. The CELL must carry the
     * badge class, or the stacking rule has nothing to hook onto and the run silently returns to
     * the value column. The PILLS must still be pills — this mode moves a run, it does not restyle
     * one, and a refactor that turned them into comma-separated text would satisfy a label-only
     * assertion. And the pills must be INSIDE the field's own cell rather than merely somewhere on
     * the page, which is what containment proves.
     */
    public function test_a_multi_select_renders_as_a_stacked_pill_run(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'view_preference' => json_encode(['City', 'Greenbelt', 'Lake']),
        ]));

        $x = $this->xpath($html);

        $cells = $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' hla-field-badges ')]");
        $this->assertGreaterThan(0, $cells->length, 'A pill run must render in a badge cell.');

        $cell = null;
        foreach ($cells as $candidate) {
            if (str_contains($candidate->textContent, 'View Preference')) {
                $cell = $candidate;
                break;
            }
        }

        $this->assertNotNull($cell, 'View Preference must render as a badge field.');

        $pills = (new DOMXPath($cell->ownerDocument))->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' badge ')]",
            $cell
        );

        $this->assertSame(3, $pills->length, 'Each selected option keeps its own pill.');

        $text = [];
        foreach ($pills as $pill) {
            $text[] = trim($pill->textContent);
        }

        $this->assertSame(['City', 'Greenbelt', 'Lake'], $text, 'Pill text and order are preserved.');
    }

    /** A badge field spans the card, so the run starts at the left edge rather than mid-row. */
    public function test_a_badge_cell_is_full_width(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'view_preference' => json_encode(['City']),
        ]));

        $x = $this->xpath($html);

        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' hla-field-badges ')]") as $cell) {
            $classes = $cell->getAttribute('class');

            $this->assertStringContainsString('col-12', $classes, 'A badge cell must span the card.');
            $this->assertStringNotContainsString(
                'col-md-6',
                $classes,
                'A half-width badge cell would reopen the gap this mode closes.'
            );
        }
    }

    /**
     * An empty multi-select renders nothing — no label, no cell, no empty pill row.
     *
     * An empty array is the shape this fails on most easily: it is not null, so a `!= null` guard
     * admits it, and it survives as far as the loop that would have emitted the pills — which then
     * emits none, leaving a labelled row with nothing after it.
     *
     * @dataProvider dataProviderForEmptyMultiSelects
     */
    public function test_an_empty_multi_select_renders_no_row($empty): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['view_preference' => $empty]));

        $this->assertStringNotContainsString(
            'View Preference',
            $html,
            'An empty multi-select must not render its label.'
        );
    }

    /** @return array<string, array{0: mixed}> */
    public function dataProviderForEmptyMultiSelects(): array
    {
        return [
            'empty array'       => [json_encode([])],
            'array of blanks'   => [json_encode(['', null])],
            'null'              => [null],
            'empty string'      => [''],
        ];
    }

    /**
     * A section whose only answers are empty multi-selects hides, and takes its nav entry with it.
     *
     * This is the combination the section guard is most likely to get wrong: the meta keys EXIST on
     * the listing, so a guard testing presence rather than content would judge the section full and
     * render an empty card.
     */
    public function test_a_section_of_empty_multi_selects_hides_entirely(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'view_preference'          => json_encode([]),
            'non_negotiable_amenities' => json_encode([]),
            'property_items'           => json_encode([]),
        ]));

        $x = $this->xpath($html);

        $this->assertSame(
            0,
            $x->query('//*[@id="hla-section-property-details"]')->length,
            'A section holding only empty multi-selects must not render a card.'
        );

        $this->assertStringNotContainsString(
            '#hla-section-property-details',
            $html,
            'The nav must not offer a section that did not render.'
        );
    }

    /** One populated multi-select is enough to bring that section back. */
    public function test_one_populated_multi_select_restores_the_section(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'view_preference' => json_encode(['Lake']),
        ]));

        $x = $this->xpath($html);

        $this->assertSame(
            1,
            $x->query('//*[@id="hla-section-property-details"]')->length,
            'A populated multi-select must bring its section back.'
        );

        $this->assertStringContainsString(
            '#hla-section-property-details',
            $html,
            'The nav must offer a section that rendered.'
        );
    }

    /** Flag off keeps the pill run exactly where it was — no badge cell, no stacking class. */
    public function test_flag_off_emits_no_badge_cell(): void
    {
        $this->disableRedesign();

        $html = $this->render($this->listing([
            'view_preference' => json_encode(['City', 'Lake']),
        ]));

        $this->assertStringNotContainsString(
            'hla-field-badges',
            $html,
            'The badge cell is a redesign construct and must not leak to the legacy branch.'
        );

        $x     = $this->xpath($html);
        $pills = $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' badge ')]");

        $this->assertGreaterThanOrEqual(2, $pills->length, 'Flag off still renders the pills.');
    }

    // ── Section visibility ───────────────────────────────────────────────────

    /**
     * A section whose every field is unanswered emits no card.
     *
     * The nav is asserted alongside the card deliberately. The two are driven by one boolean, and
     * the failure this guards against is not "the card appeared" — it is the card and the bar
     * disagreeing, which is what an emptiness rule living inside the card component produced.
     */
    public function test_a_section_with_no_answered_fields_emits_no_card(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing());

        $x = $this->xpath($html);

        $this->assertSame(
            0,
            $x->query('//*[@id="hla-section-listing-details"]')->length,
            'An entirely unanswered section must not render a card.'
        );

        $this->assertStringNotContainsString(
            'hla-section-listing-details',
            $html,
            'The nav must not offer a section that did not render.'
        );
    }

    /**
     * The two big sections hide and return on the same rule, and their nav entries follow.
     *
     * These are the sections whose guards read raw meta rather than the derived display values,
     * because the nav is built before those derivations exist. That makes them the two most able to
     * drift: a key missing from the guard list hides a section that still has a row in it. The
     * assertion runs in both directions for exactly that reason — the empty case proves the guard
     * fires, and the populated case proves it fires on the right keys.
     *
     * @dataProvider dataProviderForGuardedSections
     */
    public function test_a_big_section_hides_when_empty_and_returns_with_one_answer(
        string $anchor,
        string $metaKey,
        string $metaValue
    ): void {
        $this->enableRedesign();

        $empty = $this->xpath($this->render($this->listing()));
        $this->assertSame(
            0,
            $empty->query("//*[@id=\"{$anchor}\"]")->length,
            "{$anchor}: an entirely unanswered section must not render."
        );

        $filled     = $this->render($this->listing([$metaKey => $metaValue]));
        $filledPath = $this->xpath($filled);

        $this->assertSame(
            1,
            $filledPath->query("//*[@id=\"{$anchor}\"]")->length,
            "{$anchor}: one answered field must bring the section back."
        );

        $this->assertStringContainsString(
            "#{$anchor}",
            $filled,
            "{$anchor}: the nav must offer a section that rendered."
        );
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public function dataProviderForGuardedSections(): array
    {
        return [
            'property details' => ['hla-section-property-details', 'property_city', 'Redington Beach'],
            'leasing terms'    => ['hla-section-leasing-terms', 'occupant_status', 'Tenant'],
        ];
    }

    /** One answered field is enough to bring the section back. */
    public function test_a_section_with_one_answered_field_renders(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        $x = $this->xpath($html);

        $this->assertSame(
            1,
            $x->query('//*[@id="hla-section-listing-details"]')->length,
            'A section holding one answered field must render.'
        );
    }

    // ── The other branch ─────────────────────────────────────────────────────

    /**
     * Flag off still renders the legacy line, colon and all.
     *
     * The redesign is a landlord pilot; three other roles render this same component every day and
     * must not move. The colon is asserted because it is the one piece of the legacy row this
     * milestone deliberately drops on the other branch.
     */
    public function test_flag_off_renders_the_legacy_row_unchanged(): void
    {
        $this->disableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        $this->assertSame([], $this->gridLabels($html), 'Flag off must emit no grid row.');

        $rows = $this->legacyRows($html);
        $hit  = array_values(array_filter(
            $rows,
            fn ($r) => str_contains($r, 'Current Representation Status with Broker')
        ));

        $this->assertNotEmpty($hit, 'Flag off must still render the legacy row.');
        $this->assertStringContainsString(
            'Current Representation Status with Broker: Not Represented',
            $hit[0],
            'The legacy row keeps its colon and its inline value.'
        );
    }

    /** Flag off hides an unanswered field too — the guard is shared, not redesign-only. */
    public function test_flag_off_still_hides_an_unanswered_field(): void
    {
        $this->disableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => '']));

        $hit = array_filter(
            $this->legacyRows($html),
            fn ($r) => str_contains($r, 'Current Representation Status with Broker')
        );

        $this->assertEmpty($hit, 'An unanswered field must not render on either branch.');
    }
}
