<?php

namespace Tests\Feature\HireAgent;

use App\Models\SellerAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Seller "Representation Preferences & Compatibility" — the two-column parity fix.
 *
 * WHAT WENT WRONG. Seller's representation loop passed `span="full"` to x-hire-agent.field. It was
 * the only one of the four representation loops that did — buyer, landlord and tenant all take the
 * component's `half` default. `full` resolves to `col-12`, so twenty-one short label/value pairs
 * stacked into a single very long column while the equivalent tenant card flowed them two-up.
 * Visual QA on listing 128 caught seller as the odd one out; nothing in the suite did, because no
 * existing test looks at which grid cell class a representation row lands in.
 *
 * WHY THIS FILE EXISTS RATHER THAN A LINE IN AN EXISTING ONE. The suite pins plenty about this
 * section — HireAgentSectionCardDomEquivalenceTest pins its heading and anchor, the registry test
 * pins its audience, HireAgentSellerFieldConversionTest pins its row TEXT — and all of them stayed
 * green through the regression. The gap was never text or identity; it was the cell width, and a
 * width has no natural home in a file about conversion fidelity.
 *
 * THE ASSERTIONS ARE DELIBERATELY BOTH-SIDED. It is not enough to prove the representation rows
 * are two-up now: `span="full"` is CORRECT for a dozen other seller rows (Appliances, Amenities,
 * Additional Details — list-valued or sentence-length, and all three other roles pass `full` for
 * their equivalents too). A fix that swept the attribute out of the file would satisfy a one-sided
 * test and quietly re-flow ten sections nobody asked about, so
 * test_full_span_rows_outside_representation_keep_their_full_width holds the other edge.
 *
 * FLAG-OFF CANNOT MOVE HERE and is asserted anyway. `span` is read only while building
 * $hlaFieldRedesignWidth, inside the field component's redesign branch; the legacy branch never
 * consults it. That makes the flag-off case a proof about the component's structure rather than a
 * risk being covered — which is exactly the claim worth pinning, since the whole rollout rests on
 * the redesign branch being the only thing these edits can reach.
 */
class HireAgentSellerRepresentationLayoutTest extends TestCase
{
    use DatabaseTransactions;

    /** The section's anchor id — shared by the card and the nav entry that scrolls to it. */
    private const SECTION_ID = 'hla-section-representation';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * The seller_specific answers behind the representation card.
     *
     * A spread across the builder's shapes rather than all twenty-one rows: single-valued strings,
     * multi-select arrays (joined with ", " by $repAdd), an "Other" resolution, and a long free-text
     * answer. Enough that a change to how the loop renders shows up, few enough that the expected
     * pairs below stay readable.
     */
    private function sellerSpecific(): array
    {
        return [
            'primary_transaction_goal'       => 'Other',
            'primary_transaction_goal_other' => 'Downsize before retirement',
            'target_sale_timeline'           => '30-60 days',
            'flexibility_on_timeline'        => 'Somewhat flexible',
            'post_sale_plan'                 => 'Buying locally',
            'representation_priorities'      => ['Net proceeds', 'Speed of sale'],
            'communication_style'            => 'Direct and concise',
            'preferred_contact_method'       => ['Text', 'Email'],
            'response_time_expectation'      => 'Same day',
            'negotiation_style'              => 'Collaborative',
            'firm_on_price'                  => 'No',
            'involvement_level'              => 'Weekly updates',
            'open_house_preference'           => 'Weekends only',
            'additional_compatibility_notes' => 'Prefers an agent who has sold in this subdivision before.',
        ];
    }

    /** The label/value pairs the fixture above must produce, as the kv helper spells them. */
    private function expectedPairs(): array
    {
        return [
            // "Other" resolves to the custom value — $repResolve, unchanged by this fix.
            'Primary Transaction Goal'         => 'Downsize before retirement',
            'Target Sale Timeline'             => '30-60 days',
            'Timeline Flexibility'             => 'Somewhat flexible',
            'Post-Sale Plans'                  => 'Buying locally',
            // Arrays join with ", " in the builder, before the component ever sees them.
            'Representation Priorities'        => 'Net proceeds, Speed of sale',
            'Preferred Communication Style'    => 'Direct and concise',
            'Preferred Contact Method'         => 'Text, Email',
            'Expected Agent Response Time'     => 'Same day',
            'Negotiation Style'                => 'Collaborative',
            'Firm on Asking Price'             => 'No',
            'Involvement Level'                => 'Weekly updates',
            'Open House Preference'            => 'Weekends only',
            'Additional Compatibility Notes'   => 'Prefers an agent who has sold in this subdivision before.',
        ];
    }

    private function listing(array $extraMeta = []): SellerAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'seller']);

        $listing = SellerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Seller representation layout listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $meta = array_merge([
            'compatibility_preferences' => json_encode([
                'seller_specific' => $this->sellerSpecific(),
            ]),
            // Not part of the representation card. Present so
            // test_full_span_rows_outside_representation_keep_their_full_width has a full-span row
            // to look at, proving the fix was scoped to one loop rather than swept through the file.
            'additional_details' => 'Evening showings preferred.',
        ], $extraMeta);

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing;
    }

    private function enableRedesign(array $roles = ['seller']): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => $roles,
        ]);
    }

    private function disableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => [],
        ]);
    }

    private function render(SellerAgentAuction $listing): DOMXPath
    {
        $response = $this->actingAs($listing->user)
            ->get(route('seller.agent.auction.detail', $listing->id));

        $response->assertOk();

        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    private function norm(?string $s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $s));
    }

    /**
     * An exact-token class test.
     *
     * str_contains would report `col-lg-6 col-12` as carrying "col-12" AND as carrying "col-12"
     * alone, which is precisely the distinction this file turns on — a half cell carries both
     * tokens and a full cell carries only one.
     */
    private function hasClass(\DOMElement $node, string $token): bool
    {
        return in_array($token, preg_split('/\s+/', $this->norm($node->getAttribute('class'))), true);
    }

    /** The `.hla-field` cells inside the representation card, in document order. */
    private function representationCells(DOMXPath $x): array
    {
        $cells = $x->query(
            '//*[@id="' . self::SECTION_ID . '"]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " hla-field ")]'
        );

        return iterator_to_array($cells);
    }

    /** One `.hla-field` cell rendered as "Label: value", via the kv spans inside it. */
    private function cellPair(\DOMElement $cell): string
    {
        $x     = new DOMXPath($cell->ownerDocument);
        $label = $x->query('.//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv-label ")]', $cell);
        $value = $x->query('.//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv-value ")]', $cell);

        return $this->norm($label->item(0)?->textContent) . ': ' . $this->norm($value->item(0)?->textContent);
    }

    // ── Redesign on ──────────────────────────────────────────────────────────

    /** The section still renders, and still under the anchor id the nav scrolls to. */
    public function test_representation_section_renders_under_its_unchanged_anchor_id(): void
    {
        $this->enableRedesign();
        $x = $this->render($this->listing());

        $card = $x->query('//*[@id="' . self::SECTION_ID . '"]');

        $this->assertSame(
            1,
            $card->length,
            'The representation card must render exactly once, under id "' . self::SECTION_ID . '".'
        );

        $this->assertStringContainsString(
            'Representation Preferences & Compatibility',
            $this->norm($card->item(0)->textContent),
            'The card heading must be unchanged by a layout fix.'
        );
    }

    /**
     * THE REGRESSION ITSELF. Every representation row sits in a two-up grid cell.
     *
     * `col-lg-6 col-12` is the component's `half` span: two per line from 992px, full width below
     * it. A row carrying `col-12` WITHOUT `col-lg-6` is the `full` span that caused the single
     * long column, so both tokens are asserted rather than just the presence of one.
     */
    public function test_representation_rows_use_the_two_up_grid_cell(): void
    {
        $this->enableRedesign();
        $cells = $this->representationCells($this->render($this->listing()));

        $this->assertGreaterThan(
            1,
            count($cells),
            'The fixture must produce several representation rows for the two-up claim to mean anything.'
        );

        foreach ($cells as $cell) {
            $pair = $this->cellPair($cell);

            $this->assertTrue(
                $this->hasClass($cell, 'col-lg-6'),
                "Representation row [{$pair}] must take the half span (col-lg-6) so the card flows "
                . 'two-up on desktop, as buyer, landlord and tenant already do. Class was: '
                . $this->norm($cell->getAttribute('class'))
            );

            $this->assertTrue(
                $this->hasClass($cell, 'col-12'),
                "Representation row [{$pair}] must stay full width below lg — col-12 is the "
                . 'responsive half of the half span, not a leftover from span=full.'
            );
        }
    }

    /** Every row still routes through the approved kv primitive rather than hand-written markup. */
    public function test_representation_rows_keep_the_approved_field_structure(): void
    {
        $this->enableRedesign();
        $x     = $this->render($this->listing());
        $cells = $this->representationCells($x);

        foreach ($cells as $cell) {
            $inner = new DOMXPath($cell->ownerDocument);

            $this->assertGreaterThan(
                0,
                $inner->query('.//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv ")]', $cell)->length,
                'Each representation cell must contain a viho-kv split, not bespoke seller markup.'
            );

            $this->assertGreaterThan(
                0,
                $inner->query('.//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv-label ")]', $cell)->length,
                'Each representation cell must carry a viho-kv-label.'
            );
        }

        $this->assertGreaterThan(
            0,
            $x->query(
                '//*[@id="' . self::SECTION_ID . '"]'
                . '//div[contains(concat(" ", normalize-space(@class), " "), " hla-field-grid ")]'
            )->length,
            'The cells must sit inside the shared hla-field-grid the detail-section emits.'
        );
    }

    /** Content is untouched: same labels, same values, same order. */
    public function test_representation_values_survive_the_layout_change(): void
    {
        $this->enableRedesign();
        $cells = $this->representationCells($this->render($this->listing()));

        $rendered = array_map(fn ($cell) => $this->cellPair($cell), $cells);

        $expected = [];
        foreach ($this->expectedPairs() as $label => $value) {
            $expected[] = "{$label}: {$value}";
        }

        foreach ($expected as $pair) {
            $this->assertContains(
                $pair,
                $rendered,
                "Representation row [{$pair}] must still render. A layout fix may not change labels, "
                . 'values or the "Other" resolution.'
            );
        }

        // Order is the questionnaire's order and the builder's; the loop must not reshuffle it.
        $this->assertSame(
            $expected,
            array_values(array_intersect($rendered, $expected)),
            'The representation rows must keep the order $repAdd built them in.'
        );
    }

    /** An unanswered field still declines to render — the builder's empty-drop is untouched. */
    public function test_unanswered_representation_fields_still_render_nothing(): void
    {
        $this->enableRedesign();

        $partial = $this->sellerSpecific();
        unset($partial['negotiation_style'], $partial['post_sale_plan']);

        $listing = $this->listing([
            'compatibility_preferences' => json_encode(['seller_specific' => $partial]),
        ]);

        $rendered = array_map(fn ($cell) => $this->cellPair($cell), $this->representationCells($this->render($listing)));
        $labels   = array_map(fn ($pair) => explode(':', $pair, 2)[0], $rendered);

        $this->assertNotContains('Negotiation Style', $labels, 'An unanswered field must render no row at all.');
        $this->assertNotContains('Post-Sale Plans', $labels, 'An unanswered field must render no row at all.');
        $this->assertContains('Target Sale Timeline', $labels, 'Answered fields around it must be unaffected.');
    }

    /**
     * THE OTHER EDGE. Rows that legitimately span the card still do.
     *
     * Additional Details is sentence-length and passes span="full" in seller exactly as it does in
     * buyer and landlord. If this assertion fails, the fix was applied with a blunt find-and-replace
     * across the file rather than to the representation loop.
     */
    public function test_full_span_rows_outside_representation_keep_their_full_width(): void
    {
        $this->enableRedesign();
        $x = $this->render($this->listing());

        $found = null;

        foreach ($x->query('//div[contains(concat(" ", normalize-space(@class), " "), " hla-field ")]') as $cell) {
            if (str_contains($this->cellPair($cell), 'Additional Details:')) {
                $found = $cell;
                break;
            }
        }

        $this->assertNotNull($found, 'The fixture must render an Additional Details row to test against.');

        $this->assertTrue(
            $this->hasClass($found, 'col-12'),
            'Additional Details must keep its full span.'
        );

        $this->assertFalse(
            $this->hasClass($found, 'col-lg-6'),
            'Additional Details must NOT have become a half cell — span="full" is correct there and '
            . 'the representation fix must not have reached it.'
        );
    }

    // ── Redesign off ─────────────────────────────────────────────────────────

    /**
     * Flag-off output is untouched, as it must be: `span` is read only inside the redesign branch.
     *
     * Asserted as an element tree rather than as text, because the text was never at risk — what
     * this pins is that the legacy branch still emits the ordinary shape and that none of the
     * redesign's grid markup leaked onto an unflagged page.
     */
    public function test_flag_off_representation_rows_keep_their_legacy_shape(): void
    {
        $this->disableRedesign();
        $x = $this->render($this->listing());

        $this->assertSame(
            0,
            $x->query('//*[@id="' . self::SECTION_ID . '"]')->length,
            'With the flag off there is no card wrapper, so no anchor id — the legacy page never had one.'
        );

        $this->assertSame(
            0,
            $x->query('//div[contains(concat(" ", normalize-space(@class), " "), " hla-field ")]')->length,
            'No redesign grid cell may render with the flag off.'
        );

        foreach (['Target Sale Timeline' => '30-60 days', 'Preferred Contact Method' => 'Text, Email'] as $label => $value) {
            $node = $x->query(
                '//div[contains(@class,"col-md-12")][contains(@class,"fw-bold")]'
                . '[span[@class="removeBold"]][contains(., "' . $label . '")]'
            );

            $this->assertGreaterThan(
                0,
                $node->length,
                "Flag-off row [{$label}] must keep the ordinary legacy element tree."
            );

            $this->assertSame(
                "{$label}: {$value}",
                $this->norm($node->item(0)->textContent),
                "Flag-off row [{$label}] must read exactly as it did before the layout fix."
            );
        }
    }

    /** A role outside the allowlist stays on the legacy page even while the master switch is on. */
    public function test_seller_outside_the_allowlist_keeps_the_legacy_layout(): void
    {
        $this->enableRedesign(['landlord']);
        $x = $this->render($this->listing());

        $this->assertSame(
            0,
            $x->query('//*[@id="' . self::SECTION_ID . '"]')->length,
            'The allowlist, not the master switch, decides whether seller gets the redesigned card.'
        );
    }
}
