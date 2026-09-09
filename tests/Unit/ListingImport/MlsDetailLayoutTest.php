<?php

namespace Tests\Unit\ListingImport;

use App\Services\ListingImport\Mls\MlsDetailLayout;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use PHPUnit\Framework\TestCase;

/**
 * Where each stored MLS section lands on a listing page, and what — if anything
 * — is allowed to disappear on the way.
 *
 * No application is booted: placement is a pure function of the stored payload,
 * and it is rendered from Blade during a page render where a container
 * dependency would be a liability.
 */
class MlsDetailLayoutTest extends TestCase
{
    /** Build a stored blob of the shape MlsSupplementalDetails::fromStored() accepts. */
    private function stored(array $sections): array
    {
        return [
            'version'     => MlsSupplementalDetails::VERSION,
            'listing_key' => 'KEY-1',
            'mls_number'  => 'MLS-1',
            'permissions' => [],
            'sections'    => array_map(
                static fn (array $s) => [
                    'title' => $s['title'],
                    'group' => $s['group'],
                    'rows'  => array_map(
                        static fn (array $r) => [
                            'key'   => $r['key'] ?? '',
                            'label' => $r['label'],
                            'value' => $r['value'],
                            'url'   => null,
                            'link'  => null,
                        ],
                        $s['rows'],
                    ),
                ],
                $sections,
            ),
        ];
    }

    private function layout(array $sections): MlsDetailLayout
    {
        return MlsDetailLayout::from(MlsSupplementalDetails::fromStored($this->stored($sections)));
    }

    private function titles(MlsDetailLayout $layout, string $slot): array
    {
        return array_map(static fn (array $s) => $s['title'], $layout->slot($slot));
    }

    private function labels(MlsDetailLayout $layout, string $slot): array
    {
        $out = [];

        foreach ($layout->slot($slot) as $section) {
            foreach ($section['rows'] as $row) {
                $out[] = $row['label'];
            }
        }

        return $out;
    }

    // ─── Placement ───────────────────────────────────────────────────────────

    /**
     * @test
     *
     * THE DUPLICATION THIS CLASS REMOVES. The listing page has its own "Property
     * Details" card, so the MLS section of the same name must merge into it
     * rather than become a second card describing the same house.
     */
    public function the_property_details_section_merges_into_the_pages_own_card(): void
    {
        $layout = $this->layout([
            ['title' => 'Property Details', 'group' => 'facts', 'rows' => [
                ['label' => 'Property Sub-Type', 'value' => 'Condominium'],
            ]],
        ]);

        $this->assertSame(['Property Details'], $this->titles($layout, MlsDetailLayout::SLOT_PROPERTY));
        $this->assertSame([], $layout->slot(MlsDetailLayout::SLOT_FACTS));
    }

    /** @test */
    public function hoa_and_tax_sections_merge_into_the_pages_tax_and_hoa_card(): void
    {
        $layout = $this->layout([
            ['title' => 'HOA / Association', 'group' => 'facts', 'rows' => [['label' => 'Fee Includes', 'value' => 'Cable TV']]],
            ['title' => 'Taxes / Financial', 'group' => 'facts', 'rows' => [['label' => 'Tax Block', 'value' => '017']]],
        ]);

        $this->assertSame(
            ['HOA / Association', 'Taxes / Financial'],
            $this->titles($layout, MlsDetailLayout::SLOT_TAX_HOA),
        );
        $this->assertSame([], $layout->slot(MlsDetailLayout::SLOT_FACTS));
    }

    /**
     * @test
     *
     * Sections with no canonical card of the same name have nothing to collide
     * with, so they become ordinary cards of their own.
     */
    public function sections_with_no_canonical_counterpart_become_their_own_cards(): void
    {
        $layout = $this->layout([
            ['title' => 'Interior', 'group' => 'facts', 'rows' => [['label' => 'Flooring', 'value' => 'Laminate']]],
            ['title' => 'Lease / Rental', 'group' => 'facts', 'rows' => [['label' => 'Minimum Lease', 'value' => '6 Months']]],
        ]);

        $this->assertSame(['Interior', 'Lease / Rental'], $this->titles($layout, MlsDetailLayout::SLOT_FACTS));
    }

    /** @test */
    public function contacts_and_mls_bookkeeping_get_their_own_slots(): void
    {
        $layout = $this->layout([
            ['title' => 'Listing Agent / Brokerage', 'group' => 'contacts', 'rows' => [['label' => 'Listing Agent', 'value' => 'A Sweeney']]],
            ['title' => 'MLS Information', 'group' => 'listing', 'rows' => [['label' => 'MLS #', 'value' => 'TB1']]],
        ]);

        $this->assertSame(['Listing Agent / Brokerage'], $this->titles($layout, MlsDetailLayout::SLOT_CONTACTS));
        $this->assertSame(['MLS Information'], $this->titles($layout, MlsDetailLayout::SLOT_MLS_INFO));
    }

    /**
     * @test
     *
     * A title this class has never heard of must still be published. Widening
     * MlsFieldCatalog is a reviewed decision; losing the result of one silently
     * on the page is not. The section's own group chooses the slot.
     */
    public function an_unrecognised_section_is_placed_rather_than_dropped(): void
    {
        $layout = $this->layout([
            ['title' => 'Solar & Energy', 'group' => 'facts', 'rows' => [['label' => 'Solar Owned', 'value' => 'Yes']]],
            ['title' => 'Some New Contact', 'group' => 'contacts', 'rows' => [['label' => 'Person', 'value' => 'X']]],
            ['title' => 'Some New Context', 'group' => 'listing', 'rows' => [['label' => 'Thing', 'value' => 'Y']]],
        ]);

        $this->assertContains('Solar & Energy', $this->titles($layout, MlsDetailLayout::SLOT_FACTS));
        $this->assertContains('Some New Contact', $this->titles($layout, MlsDetailLayout::SLOT_CONTACTS));
        $this->assertContains('Some New Context', $this->titles($layout, MlsDetailLayout::SLOT_MLS_INFO));
        $this->assertSame(3, $layout->rowCount());
    }

    // ─── The one thing that is dropped ───────────────────────────────────────

    /**
     * @test
     *
     * MlsRelatedResources already tries to suppress a related-resource row the
     * contacts section has shown, but it compares LABEL and value — and the two
     * presenters deliberately label the same fact differently ("Agent Phone" vs
     * "Direct Phone"). So the same phone number reached the page three times.
     */
    public function a_related_row_repeating_a_contact_value_is_dropped(): void
    {
        $layout = $this->layout([
            ['title' => 'Listing Agent / Brokerage', 'group' => 'contacts', 'rows' => [
                ['label' => 'Agent Phone', 'value' => '727-776-2013'],
                ['label' => 'Agent Email', 'value' => 'a@example.com'],
            ]],
            ['title' => 'Listing Agent Contact', 'group' => 'related', 'rows' => [
                ['label' => 'Direct Phone',  'value' => '727-776-2013'],
                ['label' => 'Email',         'value' => 'a@example.com'],
                ['label' => 'State Licence', 'value' => '3259307'],
            ]],
        ]);

        $this->assertSame(
            ['Agent Phone', 'Agent Email', 'State Licence'],
            $this->labels($layout, MlsDetailLayout::SLOT_CONTACTS),
            'only the genuinely new related row should survive',
        );
    }

    /**
     * @test
     *
     * Contacts rows are never deduplicated against EACH OTHER. An agent phone and
     * a brokerage phone that happen to match are two facts about two parties, and
     * collapsing them would assert something the feed never said.
     */
    public function two_contact_rows_sharing_a_value_are_both_kept(): void
    {
        $layout = $this->layout([
            ['title' => 'Listing Agent / Brokerage', 'group' => 'contacts', 'rows' => [
                ['label' => 'Agent Phone',     'value' => '727-776-2013'],
                ['label' => 'Brokerage Phone', 'value' => '727-776-2013'],
            ]],
        ]);

        $this->assertSame(
            ['Agent Phone', 'Brokerage Phone'],
            $this->labels($layout, MlsDetailLayout::SLOT_CONTACTS),
        );
    }

    /**
     * @test
     *
     * A related section left with nothing after deduplication produces no card at
     * all, rather than an empty heading.
     */
    public function a_related_section_emptied_by_deduplication_produces_no_card(): void
    {
        $layout = $this->layout([
            ['title' => 'Listing Agent / Brokerage', 'group' => 'contacts', 'rows' => [
                ['label' => 'Agent Phone', 'value' => '727-776-2013'],
            ]],
            ['title' => 'Listing Agent Contact', 'group' => 'related', 'rows' => [
                ['label' => 'Direct Phone', 'value' => '727-776-2013'],
            ]],
        ]);

        $this->assertSame(['Listing Agent / Brokerage'], $this->titles($layout, MlsDetailLayout::SLOT_CONTACTS));
    }

    // ─── Nothing else is lost ────────────────────────────────────────────────

    /**
     * @test
     *
     * Every row of a payload with no related-resource repeats reaches a slot.
     * This is the "all available MLS information remains visible" guarantee
     * stated as an invariant rather than as an intention.
     */
    public function every_row_of_a_payload_reaches_a_slot(): void
    {
        $sections = [
            ['title' => 'Property Details', 'group' => 'facts',    'rows' => [['label' => 'A', 'value' => '1'], ['label' => 'B', 'value' => '2']]],
            ['title' => 'Interior',         'group' => 'facts',    'rows' => [['label' => 'C', 'value' => '3']]],
            ['title' => 'HOA / Association','group' => 'facts',    'rows' => [['label' => 'D', 'value' => '4']]],
            ['title' => 'Listing Agent / Brokerage', 'group' => 'contacts', 'rows' => [['label' => 'E', 'value' => '5']]],
            ['title' => 'Open Houses',      'group' => 'related',  'rows' => [['label' => 'Open House', 'value' => 'Sat 1-3']]],
            ['title' => 'MLS Information',  'group' => 'listing',  'rows' => [['label' => 'F', 'value' => '6']]],
        ];

        $details = MlsSupplementalDetails::fromStored($this->stored($sections));
        $layout  = MlsDetailLayout::from($details);

        $this->assertSame(7, $details->rowCount());
        $this->assertSame(7, $layout->rowCount(), 'placement must not lose a row');
    }

    /** @test */
    public function an_absent_or_empty_payload_produces_an_empty_layout(): void
    {
        foreach ([null, MlsSupplementalDetails::empty(), 'not a payload'] as $input) {
            $layout = MlsDetailLayout::from($input);

            $this->assertTrue($layout->isEmpty());
            $this->assertSame(0, $layout->rowCount());

            foreach (MlsDetailLayout::SLOTS as $slot) {
                $this->assertSame([], $layout->slot($slot));
            }
        }
    }

    /** @test */
    public function every_known_section_title_has_an_icon(): void
    {
        $this->assertSame('fa-solid fa-couch', MlsDetailLayout::iconFor('Interior'));
        $this->assertSame('fa-solid fa-circle-info', MlsDetailLayout::iconFor('Something Unheard Of'));
    }
}
