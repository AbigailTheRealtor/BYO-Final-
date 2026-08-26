<?php

namespace Tests\Feature\HireAgent;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * S2 — `legacyInverted` on x-hire-agent.field.
 *
 * Seller writes eight rows inside out: the row div carries `removeBold` and the LABEL is wrapped in
 * a `fw-bold` span, with the value bare beside it. Buyer, landlord and tenant have none of that
 * shape, and until this milestone the shared component could not emit it — so converting those
 * eight rows would have changed flag-off markup, which is the one thing the conversion is not
 * allowed to do.
 *
 * WHY THIS FILE RENDERS THE COMPONENT DIRECTLY RATHER THAN THROUGH A ROLE PAGE. The property under
 * test is a property of the component: given these props, what markup comes out. Reaching it
 * through a listing means building a fixture whose meta happens to drive the row, and then asserting
 * on one row inside a 3,000-line page — which tests the view's guards at least as much as the
 * component's branch. HireAgentFieldPresentationTest goes the other way for the rows it covers and
 * that is right for them; this one is about the branch itself, and Blade::render is the established
 * idiom for that in tests/Feature/Viho.
 *
 * The seller CONVERSION — which rows pass this flag and what they read — is not asserted here. It
 * lands in the same commit as the rows themselves.
 */
class HireAgentFieldInvertedRowTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Render x-hire-agent.field with the given attribute string and slot.
     *
     * The attributes are written as a Blade tag rather than passed as an array because that is how
     * a call site writes them, and the compiler's own parsing of `:bound="expr"` versus
     * `plain="text"` is part of what a caller has to get right.
     */
    private function render(string $attributes, string $slot = ''): string
    {
        return Blade::render("<x-hire-agent.field {$attributes}>{$slot}</x-hire-agent.field>");
    }

    /** Collapse inter-element whitespace the way the DOM-equivalence coverage does. */
    private function normalise(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    // ── The legacy shape ─────────────────────────────────────────────────────

    /**
     * The inverted branch emits Seller's row shape exactly: bold label in a span, bare value, and
     * `removeBold` on the row div rather than on a wrapper around the value.
     *
     * This is the assertion the whole flag exists to satisfy, so it pins the element tree rather
     * than only the text — the text was never in question, since both shapes read the same.
     */
    public function test_the_inverted_branch_emits_the_seller_row_shape(): void
    {
        $html = $this->render('label="Assignment Contract" value="Yes" :legacy-inverted="true"');

        $this->assertSame(
            '<div class="col-md-12 col-12 pt-2 removeBold"> '
            . '<span class="fw-bold">Assignment Contract:</span> Yes </div>',
            $this->normalise($html)
        );
    }

    /**
     * The label carries the colon and the caller does not.
     *
     * The colon lives inside the label span in the original markup, so this is the migration
     * mistake most available to a converter: passing "Assignment Contract:" would render a double
     * colon here and leave a stray one in the redesign's `viho-kv-label`.
     */
    public function test_the_caller_passes_no_colon(): void
    {
        $html = $this->render('label="Target Closing Date" value="March 1, 2026" :legacy-inverted="true"');

        $this->assertStringContainsString('<span class="fw-bold">Target Closing Date:</span>', $html);
        $this->assertStringNotContainsString('::', $html);
    }

    /** Its text normalises to what the hand-written row normalises to — label, colon, space, value. */
    public function test_its_text_matches_the_hand_written_row(): void
    {
        $handWritten = '<div class="col-md-12 col-12 pt-2 removeBold">'
            . '<span class="fw-bold">Occupant Type:</span>'
            . ' Owner'
            . '</div>';

        $component = $this->render('label="Occupant Type" value="Owner" :legacy-inverted="true"');

        $this->assertSame(
            $this->normalise($this->xpath($handWritten)->document->textContent),
            $this->normalise($this->xpath($component)->document->textContent)
        );
    }

    /** A slot is emitted bare too — the computed rows (Occupied Until, Desired Sale Price) need it. */
    public function test_a_slot_value_is_emitted_without_a_wrapper(): void
    {
        $html = $this->render('label="Desired Sale Price" :legacy-inverted="true"', '$1,250,000');

        $this->assertSame(
            '<div class="col-md-12 col-12 pt-2 removeBold"> '
            . '<span class="fw-bold">Desired Sale Price:</span> $1,250,000 </div>',
            $this->normalise($html)
        );

        // The value must NOT gain the wrapper the ordinary shape uses — that would nest a
        // removeBold inside the row div that already carries one.
        $this->assertSame(0, $this->xpath($html)->query('//div/span[@class="removeBold"]')->length);
    }

    /** An absent value renders nothing at all, exactly as every other shape does. */
    public function test_an_absent_value_renders_no_row(): void
    {
        $this->assertSame('', $this->normalise($this->render('label="Occupied Until" :legacy-inverted="true"')));
        $this->assertSame('', $this->normalise($this->render('label="Occupied Until" value="" :legacy-inverted="true"')));
    }

    /** An explicit width still wins, so the escape hatch the other shapes rely on is intact. */
    public function test_an_explicit_width_overrides_the_inverted_default(): void
    {
        $html = $this->render('label="Occupant Type" value="Owner" :legacy-inverted="true" width="col-6 custom"');

        $this->assertStringContainsString('<div class="col-6 custom">', $html);
        $this->assertStringNotContainsString('col-md-12', $html);
    }

    // ── Isolation from the shapes that already existed ───────────────────────

    /**
     * WITHOUT the flag, the ordinary shape is untouched — same classes, same order, same tree.
     *
     * The prop's default is what makes buyer, landlord and tenant safe, and defaulting `width` to
     * null to let the shape pick its own default is the change most able to break that quietly.
     * This is the row those three views render 282 times between them.
     */
    public function test_the_ordinary_shape_is_unchanged_when_the_flag_is_absent(): void
    {
        $html = $this->render('label="Listing Date" value="March 1, 2026"');

        $this->assertSame(
            '<div class="col-md-12 col-12 pt-2 fw-bold">Listing Date: '
            . '<span class="removeBold">March 1, 2026</span> </div>',
            $this->normalise($html)
        );
    }

    /** And the two other legacy shapes keep their own class list and wrappers. */
    public function test_the_other_legacy_shapes_are_unchanged(): void
    {
        $bare = $this->render('label="Amenities" :bare-slot="true"', '<span class="removeBold badge bg-secondary">Pool</span>');
        $this->assertSame(
            '<div class="col-md-12 col-12 pt-2 fw-bold">Amenities: '
            . '<span class="removeBold badge bg-secondary">Pool</span> </div>',
            $this->normalise($bare)
        );

        $row = $this->render('label="Lease Length" value="12 months" :legacy-row="true" width="col-12 fw-bold pt-2"');
        $this->assertSame(
            '<div class="row" style="flex-wrap: wrap;"> '
            . '<div class="col-12 fw-bold pt-2">Lease Length: '
            . '<span class="removeBold">12 months</span> </div> </div>',
            $this->normalise($row)
        );
    }

    // ── The convergence property ─────────────────────────────────────────────

    /**
     * WITH THE REDESIGN ON, THE FLAG MAKES NO DIFFERENCE — and that is the reason it is a legacy
     * concern and nothing more.
     *
     * Both shapes reach the same x-viho.kv call and become the same cell, so a converted seller row
     * is indistinguishable from an ordinary one on the redesigned page. If this ever stops being
     * true, the flag has grown a second job and the eight rows have become a permanent divergence
     * rather than a preserved one.
     */
    public function test_the_redesign_branch_is_identical_with_and_without_the_flag(): void
    {
        $withFlag = $this->render('label="Occupant Type" value="Owner" :redesign="true" :legacy-inverted="true"');
        $without  = $this->render('label="Occupant Type" value="Owner" :redesign="true"');

        $this->assertSame($this->normalise($without), $this->normalise($withFlag));
        $this->assertStringContainsString('viho-kv', $withFlag);
    }

    /** The redesigned label still carries no colon, inverted or not. */
    public function test_the_redesigned_inverted_label_carries_no_colon(): void
    {
        $html = $this->render('label="Assignment Contract" value="Yes" :redesign="true" :legacy-inverted="true"');

        $label = $this->xpath($html)->query('//*[contains(concat(" ", normalize-space(@class), " "), " viho-kv-label ")]');

        $this->assertSame(1, $label->length, 'The redesign branch must emit one kv label.');
        $this->assertStringNotContainsString(':', trim($label->item(0)->textContent));
    }

    // ── Scope: nobody else may pass it ───────────────────────────────────────

    /**
     * NO OTHER ROLE VIEW PASSES THE FLAG, asserted at source.
     *
     * "Buyer, landlord and tenant stay byte-identical" is a claim about which call sites exist, not
     * about what one render produces, so it is checked where it is true. A view acquiring this flag
     * would be adopting Seller's row shape, which is a decision to take deliberately rather than
     * discover in a diff.
     *
     * Seller is absent from this list ON PURPOSE and stays absent: this milestone adds the branch,
     * and the eight call sites arrive with the row conversion.
     */
    public function test_no_other_role_view_passes_the_inverted_flag(): void
    {
        foreach (['buyer', 'landlord', 'tenant'] as $role) {
            $path = base_path("resources/views/hire_{$role}_agent/view.blade.php");

            $this->assertStringNotContainsString(
                'legacy-inverted',
                (string) file_get_contents($path),
                "The {$role} view must not adopt Seller's inverted row shape."
            );
        }
    }
}
