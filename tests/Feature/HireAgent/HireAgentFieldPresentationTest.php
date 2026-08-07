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
 *
 * M7.6 ADDS A THIRD CLAIM, and it is about vocabulary rather than shape: a pill means STATE and
 * plain text means DATA, which is the distinction the reference page draws and this one had lost.
 * Nine of the twelve pill runs became ", "-joined text; three stayed pills because they are
 * unbounded token lists. Both halves are asserted, and asserted together in one render, because
 * the regression that matters is not either rendering in isolation — it is the two ceasing to
 * disagree.
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

    /**
     * The `.hla-field` cell whose label is exactly $label, or null.
     *
     * Addressing a row BY ITS LABEL rather than by position is what lets the M7.6 assertions name
     * the field they are about. The page renders ~120 rows and several carry pills, so a test that
     * asserted "some badge cell exists" would keep passing after the specific field it was written
     * for stopped being a badge cell — which is precisely how the test this replaced went stale.
     */
    private function fieldCell(string $html, string $label): ?\DOMElement
    {
        $x = $this->xpath($html);

        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-kv-label ')]") as $node) {
            if (trim(preg_replace('/\s+/', ' ', $node->textContent)) !== $label) {
                continue;
            }

            for ($n = $node; $n !== null; $n = $n->parentNode) {
                if ($n instanceof \DOMElement
                    && str_contains(' ' . $n->getAttribute('class') . ' ', ' hla-field ')) {
                    return $n;
                }
            }
        }

        return null;
    }

    /** The rendered value text of the grid row labelled $label, or null when it did not render. */
    private function gridValue(string $html, string $label): ?string
    {
        $cell = $this->fieldCell($html, $label);

        if ($cell === null) {
            return null;
        }

        $values = (new DOMXPath($cell->ownerDocument))->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' viho-kv-value ')]",
            $cell
        );

        return $values->length === 0
            ? null
            : trim(preg_replace('/\s+/', ' ', $values->item(0)->textContent));
    }

    /** The pills inside the grid row labelled $label. */
    private function pillsIn(string $html, string $label): array
    {
        $cell = $this->fieldCell($html, $label);

        if ($cell === null) {
            return [];
        }

        $out = [];

        foreach ((new DOMXPath($cell->ownerDocument))->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' badge ')]",
            $cell
        ) as $pill) {
            $out[] = trim($pill->textContent);
        }

        return $out;
    }

    /** The declaration block of the first rule whose selector is exactly $selector, braces excluded. */
    private function ruleBody(string $html, string $selector): string
    {
        $quoted = preg_quote($selector, '/');

        return preg_match('/(?:^|[};])\s*' . $quoted . '\s*\{([^}]*)\}/m', $html, $m)
            ? trim($m[1])
            : '';
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
     * The three token fields render as stacked pill runs. They are the only ones left that do.
     *
     * WHY THESE THREE AND NOT ANY MULTI-SELECT. Until M7.6 every multi-select on this page rendered
     * as pills, and the test that stood here asserted it using View Preference. M7.6 moved nine of
     * the twelve runs to comma-separated text to match the reference, View Preference among them,
     * which left that test asserting the opposite of the intended behaviour. It is retargeted here
     * onto the three that deliberately keep their pills — Acceptable Cities, Counties and Zip Code
     * — because they are unbounded enumerations of short tokens, where a forty-item comma list is
     * genuinely worse to scan than forty chips.
     *
     * Three things are asserted and they are three different regressions. The CELL must carry the
     * badge class, or the stacking rule has nothing to hook onto and the run silently returns to
     * the value column. The PILLS must still be pills — a well-meaning extension of M7.6 that
     * converted these three as well would satisfy a label-only assertion. And the pills must be
     * INSIDE the field's own cell rather than merely somewhere on the page, which is what
     * addressing the cell by its label proves.
     *
     * @dataProvider dataProviderForTokenFields
     */
    public function test_a_token_field_renders_as_a_stacked_pill_run(
        string $metaKey,
        string $label,
        array $tokens
    ): void {
        $this->enableRedesign();

        $html = $this->render($this->listing([$metaKey => json_encode($tokens)]));

        $cell = $this->fieldCell($html, $label);
        $this->assertNotNull($cell, "{$label} must render as a field.");

        $this->assertStringContainsString(
            'hla-field-badges',
            $cell->getAttribute('class'),
            "{$label} must keep its badge cell — the stacking rule hooks onto that class."
        );

        $this->assertSame(
            $tokens,
            $this->pillsIn($html, $label),
            "{$label}: each token keeps its own pill, in order."
        );
    }

    /** @return array<string, array{0: string, 1: string, 2: array<int, string>}> */
    public function dataProviderForTokenFields(): array
    {
        return [
            'cities'   => ['cities', 'Acceptable Cities', ['Tampa', 'Clearwater', 'Sarasota']],
            'counties' => ['counties', 'Acceptable Counties', ['Pinellas', 'Hillsborough']],
            'zips'     => ['zipCodes', 'Acceptable Zip Code', ['33701', '33702', '33703']],
        ];
    }

    /** A badge field spans the card, so the run starts at the left edge rather than mid-row. */
    public function test_a_badge_cell_is_full_width(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'cities' => json_encode(['Tampa']),
        ]));

        $x     = $this->xpath($html);
        $cells = $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' hla-field-badges ')]");

        /*
         | The precondition is the point of this line, not ceremony. The body below is a foreach,
         | so a page that emitted NO badge cell at all would iterate zero times and pass — which is
         | what this test did between M7.6 converting View Preference and this fixture being moved
         | onto a field that still has pills. A vacuous pass is worse than a failure here.
         */
        $this->assertGreaterThan(0, $cells->length, 'Precondition: a badge cell rendered.');

        foreach ($cells as $cell) {
            $classes = $cell->getAttribute('class');

            $this->assertStringContainsString('col-12', $classes, 'A badge cell must span the card.');
            /*
             | M7.8 — the half-span class is `col-lg-6`, not `col-md-6`. Asserting the old name here
             | would still pass and guard nothing, which is the vacuous-pass failure the precondition
             | above exists to prevent in the other direction.
             */
            $this->assertStringNotContainsString(
                'col-lg-6',
                $classes,
                'A half-width badge cell would reopen the gap this mode closes.'
            );
        }
    }

    // ── M7.6 — list values render as text ────────────────────────────────────

    /**
     * A converted multi-select renders as ", "-joined text in the value column, with no pills.
     *
     * This is the whole of M7.6 stated as one assertion. The reference page renders every
     * multi-select answer through its `$listRow` closure, which joins with ", " and hands the
     * result to the same label/value row a single-valued field uses; the only pill on that page is
     * a STATUS. So the vocabulary being restored is "a pill means state, plain text means data".
     *
     * THE ABSENCE OF PILLS IS ASSERTED SEPARATELY from the presence of the text, because the two
     * fail independently. The call sites still pass the pill run in the slot — the legacy branch
     * reads it and cannot be changed — so a precedence bug in the component that preferred the slot
     * over `listValue` would render BOTH the text and the pills, or the pills alone, while the
     * label assertion carried on passing.
     *
     * @dataProvider dataProviderForListValueFields
     */
    public function test_a_list_value_field_renders_comma_separated_text(
        array $meta,
        string $label,
        array $items
    ): void {
        $this->enableRedesign();

        $html = $this->render($this->listing($meta));

        $this->assertNotNull($this->fieldCell($html, $label), "{$label} must render as a field.");

        $this->assertSame(
            implode(', ', $items),
            $this->gridValue($html, $label),
            "{$label}: a converted multi-select reads as one joined string."
        );

        $this->assertSame(
            [],
            $this->pillsIn($html, $label),
            "{$label}: the slot's pill run must not reach the redesign branch."
        );

        $cell = $this->fieldCell($html, $label);
        $this->assertStringNotContainsString(
            'hla-field-badges',
            $cell->getAttribute('class'),
            "{$label}: a text row must not carry the badge cell class."
        );
    }

    /**
     * The nine converted rows, reduced to the five that can be reached by fixture.
     *
     * EVERY CASE CARRIES ONLY ITS OWN ANSWER, WHICH IS ITSELF PART OF THE CLAIM. The last three
     * rows live in Leasing Terms, whose visibility guard (`$hlaHasLeasingTerms`) lists the meta
     * keys that make the section render. `tenant_pays`, `owner_pays` and `desired_lease_length`
     * were missing from it — the guard listed the near-miss names `owner_responsible_for` and
     * `desired_lease_term`, so it read as covering rows it did not — and a listing whose only
     * leasing answer was one of the three rendered no section at all. M7.6 added the three keys.
     *
     * These fixtures therefore double as the regression test for that fix: each passes ONE meta
     * key and expects the row, so a guard that loses a key again fails here rather than silently
     * hiding a populated section.
     *
     * THE ONE EXCEPTION IS `property_type`, and it is not a guard concern. The Owner row carries
     * its own `$isCommercial` condition, so it needs a commercial property type to render at all —
     * a property of that row, not of the section it sits in.
     *
     * @return array<string, array{0: array<string, string>, 1: string, 2: array<int, string>}>
     */
    public function dataProviderForListValueFields(): array
    {
        return [
            'view preference' => [
                ['view_preference' => json_encode(['City', 'Greenbelt', 'Lake'])],
                'View Preference',
                ['City', 'Greenbelt', 'Lake'],
            ],
            'amenities' => [
                ['non_negotiable_amenities' => json_encode(['Pool', 'Gym'])],
                'Amenities and Property Features',
                ['Pool', 'Gym'],
            ],
            'tenant pays' => [
                ['tenant_pays' => json_encode(['Electricity', 'Water', 'Internet'])],
                'Tenant Responsible For',
                ['Electricity', 'Water', 'Internet'],
            ],
            'owner pays' => [
                [
                    'property_type' => 'Commercial',
                    'owner_pays'    => json_encode(['Taxes', 'Insurance']),
                ],
                'Owner Responsible For',
                ['Taxes', 'Insurance'],
            ],
            'lease term' => [
                ['desired_lease_length' => json_encode(['12 Months', '24 Months'])],
                'Desired Lease Term',
                ['12 Months', '24 Months'],
            ],
        ];
    }

    /**
     * Every converted list row spans the card, rather than sitting half-width in a two-up line.
     *
     * THIS IS A REGRESSION GUARD WITH A KNOWN CAUSE. Full width used to be implied: `:badges="true"`
     * forced `col-12` for any pill run, so a converted row that dropped `badges` without gaining
     * `span="full"` silently fell back to the half-span default. Three of the nine did exactly
     * that, and the failure is invisible in a diff — the attribute that mattered is the one that is
     * no longer there. Asserting the rendered width on every converted row is the only form of this
     * check that cannot be defeated by the next conversion forgetting the same attribute.
     *
     * THE CLASS NAME MOVED IN M7.8, from `col-md-6` to `col-lg-6`, when the two-up split shifted
     * from the md breakpoint to lg. It is spelled once below rather than in this prose so there is
     * only one place to update if it moves again — a guard naming a class the page no longer emits
     * passes forever and protects nothing.
     *
     * @dataProvider dataProviderForListValueFields
     */
    public function test_a_converted_list_row_spans_the_card(
        array $meta,
        string $label,
        array $items
    ): void {
        $this->enableRedesign();

        $html = $this->render($this->listing($meta));

        $cell = $this->fieldCell($html, $label);
        $this->assertNotNull($cell, "{$label} must render as a field.");

        $this->assertStringNotContainsString(
            'col-lg-6',
            $cell->getAttribute('class'),
            "{$label}: a converted list row must span the card, not sit half-width."
        );
    }

    /**
     * One render, both vocabularies — the distinction M7.6 exists to draw.
     *
     * The per-field tests above each render a listing carrying one answer. This one populates a
     * token field and a converted field TOGETHER, because the regression that would survive both of
     * those tests is a component that reads some page-level state and applies one rendering to
     * every field on it. Two fields disagreeing within a single render is the actual claim.
     */
    public function test_token_fields_keep_pills_while_list_fields_render_text(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing([
            'cities'          => json_encode(['Tampa', 'Sarasota']),
            'view_preference' => json_encode(['City', 'Lake']),
        ]));

        $this->assertSame(
            ['Tampa', 'Sarasota'],
            $this->pillsIn($html, 'Acceptable Cities'),
            'A token field keeps its pills.'
        );

        $this->assertSame([], $this->pillsIn($html, 'View Preference'), 'A list field renders no pills.');
        $this->assertSame('City, Lake', $this->gridValue($html, 'View Preference'), 'It renders text instead.');
    }

    /**
     * Flag off renders the pill run for a converted field, exactly as it did before M7.6.
     *
     * The conversion is redesign-only by construction: the call sites pass the pill run in the slot
     * AND the array as `listValue`, and each branch reads one of them. Three roles render this
     * component every day with the flag off, so the branch that must not move is asserted here on
     * the same field the redesign assertions above convert.
     */
    public function test_flag_off_still_renders_the_pill_run_for_a_converted_field(): void
    {
        $this->disableRedesign();

        $html = $this->render($this->listing([
            'view_preference' => json_encode(['City', 'Greenbelt', 'Lake']),
        ]));

        $this->assertSame([], $this->gridLabels($html), 'Flag off must emit no grid row.');

        $x     = $this->xpath($html);
        $pills = [];

        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' badge ')]") as $pill) {
            $pills[] = trim($pill->textContent);
        }

        foreach (['City', 'Greenbelt', 'Lake'] as $token) {
            $this->assertContains($token, $pills, "Flag off keeps the [{$token}] pill.");
        }

        $this->assertStringNotContainsString(
            'City, Greenbelt, Lake',
            $html,
            'The joined string is a redesign construct and must not leak to the legacy branch.'
        );
    }

    // ── M7.6 — the rules that carry the spacing and the type scale ───────────

    /**
     * The grid spaces its rows by the token, and the primitive's own margin is stood down.
     *
     * ASSERTED AS CSS TEXT, following the convention the sidebar surface test set: these rules have
     * no DOM consequence to observe, and the failure being guarded is that a rule is dropped or
     * renamed, not that an element is missing. Both halves are read because they are one mechanism
     * — a row-gap alone would be doubled by the margin it replaced, which is the bug M7.6 fixed,
     * and a margin reset alone would leave the rows touching.
     */
    public function test_the_field_grid_carries_the_row_gap_and_retires_the_primitive_margin(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        $grid = $this->ruleBody($html, '.hla-detail-page .hla-field-grid');
        $this->assertNotSame('', $grid, 'Precondition: the grid rule was located.');
        $this->assertStringContainsString('row-gap: var(--hla-field-row-gap)', $grid, 'The gap reads the token.');

        /*
         | M7.9 — 0.65rem, not 0.5rem, and the difference is the whole point of the change.
         |
         | M7.6 set this to 0.5rem and justified it as "the reference page's mb-2". Bootstrap's
         | mb-2 IS 0.5rem, but the reference overrides it for exactly these rows —
         | `.lol-view-page .row.mb-2 { margin-bottom: 0.65rem !important; }` — so the class it was
         | named after never governed the spacing it was measured against. Verified in a browser:
         | the reference computes to 10.4px and renders 38 consecutive inter-row gaps of 10.39px.
         |
         | The assertion message no longer names `mb-2`. Naming the class is what made the original
         | wrong, and a guard that repeats the mistaken premise would read as confirming it.
         */
        $this->assertStringContainsString(
            '--hla-field-row-gap: 0.65rem',
            $html,
            'The token matches the row spacing the reference page actually renders (0.65rem).'
        );

        $reset = $this->ruleBody($html, '.hla-detail-page .hla-field .viho-kv');
        $this->assertNotSame('', $reset, 'Precondition: the margin reset was located.');
        $this->assertStringContainsString('margin-bottom: 0', $reset, 'The primitive margin is stood down.');
    }

    /**
     * The two halves carry the reference's sizes, and the value stays the larger of the pair.
     *
     * The literals are asserted against the reference rather than against themselves: Create Offer
     * sets `.875rem` on its `col-md-5` label and `.925rem` on its `col-md-7` value inline on every
     * row. The ORDER is asserted as well as the values, because a transposition renders at exactly
     * the right sizes with the emphasis inverted, which no size-only assertion would catch.
     */
    public function test_the_field_type_scale_matches_the_reference(): void
    {
        $this->enableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        $label = $this->ruleBody($html, '.hla-detail-page .hla-field .viho-kv-label');
        $value = $this->ruleBody($html, '.hla-detail-page .hla-field .viho-kv-value');

        $this->assertNotSame('', $label, 'Precondition: the label rule was located.');
        $this->assertNotSame('', $value, 'Precondition: the value rule was located.');

        $this->assertStringContainsString('font-size: 0.875rem', $label, "The label half matches the reference.");
        $this->assertStringContainsString('font-size: 0.925rem', $value, "The value half matches the reference.");
    }

    /** Flag off ships none of it — the rules are scoped to a page the legacy branch does not emit. */
    public function test_flag_off_emits_no_field_grid_rules(): void
    {
        $this->disableRedesign();

        $html = $this->render($this->listing(['working_with_agent' => 'Not Represented']));

        $this->assertStringNotContainsString('--hla-field-row-gap', $html, 'The token is a redesign construct.');
        $this->assertSame(
            '',
            $this->ruleBody($html, '.hla-detail-page .hla-field .viho-kv-label'),
            'The type scale must not reach the legacy branch.'
        );
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
