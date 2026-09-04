<?php

namespace Tests\Unit\ListingImport\Mls;

use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use Tests\TestCase;

/**
 * The supplemental MLS payload: what gets persisted, what never does, and the
 * rule that no listing ever renders an empty row.
 */
class MlsSupplementalDetailsTest extends TestCase
{
    private function record(array $overrides = []): array
    {
        return array_merge([
            'ListingKey'                     => 'KEY-1',
            'ListingId'                      => 'MLS-1',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,

            'LaundryFeatures'   => ['Laundry Closet', 'Inside'],
            'SubdivisionName'   => 'Bradford Acres',
            'CommunityFeatures' => ['Pool', 'Gated'],
            'DaysOnMarket'      => 16,
            'ListOfficeName'    => 'Example Realty Group',
            'ListAgentFullName' => 'Jordan Blake',
            'ListAgentEmail'    => 'jordan.blake@example.com',
        ], $overrides);
    }

    private function flatten(MlsSupplementalDetails $d): string
    {
        $out = '';

        foreach ($d->sections as $section) {
            $out .= $section['title'] . ' ';
            foreach ($section['rows'] as $row) {
                $out .= $row['label'] . '=' . $row['value'] . ' ';
            }
        }

        return $out;
    }

    /** @test */
    public function it_groups_facts_contacts_and_listing_context_separately(): void
    {
        $details = MlsSupplementalDetails::fromRecord($this->record(), 'seller');

        $this->assertNotEmpty($details->group('facts'));
        $this->assertNotEmpty($details->group('contacts'));
        $this->assertNotEmpty($details->group('listing'));

        $rendered = $this->flatten($details);

        $this->assertStringContainsString('Laundry=Laundry Closet, Inside', $rendered);
        $this->assertStringContainsString('Subdivision=Bradford Acres', $rendered);
        $this->assertStringContainsString('Days on Market=16', $rendered);
        $this->assertStringContainsString('Brokerage=Example Realty Group', $rendered);
    }

    /**
     * @test
     *
     * THE HARD REQUIREMENT: a Bridge field that is null, blank, whitespace, an
     * empty array or an empty object must not become a row, and a section whose
     * every field is empty must not become a section. There is no placeholder,
     * no dash, and no "not provided".
     */
    public function empty_bridge_values_never_become_rows_or_sections(): void
    {
        $details = MlsSupplementalDetails::fromRecord($this->record([
            'LaundryFeatures'       => [],
            'SubdivisionName'       => '   ',
            'CommunityFeatures'     => null,
            'ArchitecturalStyle'    => ['', '   '],
            'PropertyCondition'     => new \stdClass(),
            'ElementarySchool'      => '',
            'STELLAR_DockDimensions' => null,
        ]), 'seller');

        foreach ($details->sections as $section) {
            $this->assertNotEmpty($section['rows'], "Section {$section['title']} rendered with no rows");

            foreach ($section['rows'] as $row) {
                $this->assertNotSame('', trim($row['value']), "Row {$row['label']} rendered with an empty value");
                $this->assertNotSame('', trim($row['label']), 'A row rendered with no label');
            }
        }

        $rendered = $this->flatten($details);

        $this->assertStringNotContainsString('Laundry=', $rendered);
        $this->assertStringNotContainsString('Subdivision=', $rendered);
        $this->assertStringNotContainsString('Community Features=', $rendered);
        $this->assertStringNotContainsString('Elementary School=', $rendered);
    }

    /**
     * @test
     *
     * A record whose only populated fields are empty ones produces nothing at
     * all, so the listing page renders no MLS Details card rather than an empty
     * one with a heading.
     */
    public function a_record_with_nothing_to_show_produces_an_empty_payload(): void
    {
        $details = MlsSupplementalDetails::fromRecord([
            'SubdivisionName'   => null,
            'CommunityFeatures' => [],
        ], 'seller');

        $this->assertTrue($details->isEmpty());
        $this->assertSame(0, $details->rowCount());
    }

    /**
     * @test
     *
     * Zero is a value; false is not. "Application Fee: $0" is a fact a renter
     * acts on. "Waterfront: No" beside forty other negatives is noise that
     * buries the facts they came for.
     */
    public function zero_renders_and_a_false_flag_does_not(): void
    {
        $details = MlsSupplementalDetails::fromRecord($this->record([
            'STELLAR_ApplicationFee' => 0,
            'CarportSpaces'          => 0,
            'SpaYN'                  => false,
            'STELLAR_DockYN'         => 'No',
        ]), 'seller');

        $rendered = $this->flatten($details);

        $this->assertStringContainsString('Application Fee=0', $rendered);
        $this->assertStringContainsString('Carport Spaces=0', $rendered);
        $this->assertStringNotContainsString('Spa=', $rendered);
        $this->assertStringNotContainsString('Dock=', $rendered);
    }

    /**
     * @test
     *
     * Re-importing an unchanged record must produce a byte-identical blob. That
     * is what makes a refresh idempotent and what makes a diff between two
     * imports mean something.
     */
    public function the_payload_is_deterministic_for_an_unchanged_record(): void
    {
        $record = $this->record();

        $a = MlsSupplementalDetails::fromRecord($record, 'seller')->toArray();
        $b = MlsSupplementalDetails::fromRecord($record, 'seller')->toArray();

        unset($a['generated_at'], $b['generated_at']);

        $this->assertSame(json_encode($a), json_encode($b));
    }

    /** @test */
    public function it_round_trips_through_storage(): void
    {
        $original = MlsSupplementalDetails::fromRecord($this->record(), 'seller');
        $restored = MlsSupplementalDetails::fromStored($original->toArray());

        $this->assertSame($original->rowCount(), $restored->rowCount());
        $this->assertSame($this->flatten($original), $this->flatten($restored));
    }

    /**
     * @test
     *
     * A blob that is missing, malformed, or written by a version this code does
     * not know renders nothing rather than raising inside a listing page. The
     * facts the user can edit are unaffected either way, so a hard failure here
     * would cost more than it caught.
     */
    public function a_malformed_or_future_blob_renders_nothing_instead_of_raising(): void
    {
        $this->assertTrue(MlsSupplementalDetails::fromStored(null)->isEmpty());
        $this->assertTrue(MlsSupplementalDetails::fromStored('not json')->isEmpty());
        $this->assertTrue(MlsSupplementalDetails::fromStored(['version' => 999])->isEmpty());
        $this->assertTrue(MlsSupplementalDetails::fromStored(['version' => 1])->isEmpty());
    }

    /**
     * @test
     *
     * A stored row with a blank value is dropped on the way out too. Blobs
     * written before a fix, or hand-edited ones, must not be able to put an
     * empty row on a page.
     */
    public function stored_rows_with_no_value_are_dropped_at_read_time(): void
    {
        $restored = MlsSupplementalDetails::fromStored([
            'version'  => 1,
            'sections' => [
                ['title' => 'Interior', 'group' => 'facts', 'rows' => [
                    ['key' => 'A', 'label' => 'Good', 'value' => 'Yes'],
                    ['key' => 'B', 'label' => 'Blank', 'value' => '   '],
                    ['key' => 'C', 'label' => '', 'value' => 'Orphan'],
                ]],
                ['title' => 'Exterior', 'group' => 'facts', 'rows' => []],
            ],
        ]);

        $this->assertSame(1, $restored->rowCount());
        $this->assertCount(1, $restored->sections);
    }

    /**
     * @test
     *
     * A URL that is not an absolute https URL never becomes an href, even when
     * it is already in storage. The blob is a database row, and a row that
     * reaches an attribute is checked where it is used.
     */
    public function unsafe_stored_urls_are_stripped_on_the_way_out(): void
    {
        $restored = MlsSupplementalDetails::fromStored([
            'version'  => 1,
            'sections' => [
                ['title' => 'MLS Information', 'group' => 'listing', 'rows' => [
                    ['key' => 'A', 'label' => 'Tour', 'value' => 'View', 'url' => 'javascript:alert(1)'],
                    ['key' => 'B', 'label' => 'Tour 2', 'value' => 'View', 'url' => 'http://insecure.example.com/'],
                    ['key' => 'C', 'label' => 'Email', 'value' => 'a', 'link' => 'mailto:not-an-email'],
                    ['key' => 'D', 'label' => 'Tour 3', 'value' => 'View', 'url' => 'https://ok.example.com/t'],
                ]],
            ],
        ]);

        $rows = $restored->sections[0]['rows'];

        $this->assertNull($rows[0]['url']);
        $this->assertNull($rows[1]['url']);
        $this->assertNull($rows[2]['link']);
        $this->assertSame('https://ok.example.com/t', $rows[3]['url']);
    }

    /**
     * @test
     *
     * A listing the feed has withdrawn renders no contacts and no listing
     * context. Property facts are unaffected — they are not gated on this — but
     * attribution and market data are.
     */
    public function a_withdrawn_listing_renders_no_contacts_or_listing_context(): void
    {
        $details = MlsSupplementalDetails::fromRecord(
            $this->record(['InternetEntireListingDisplayYN' => false]),
            'seller'
        );

        $this->assertSame([], $details->group('contacts'));
        $this->assertSame([], $details->group('listing'));
        $this->assertNotEmpty($details->group('facts'));
    }

    /**
     * @test
     *
     * Facts that reached an editable Create Offer field are not repeated, so
     * the listing does not show the same thing twice with the MLS copy reading
     * as a second, conflicting claim.
     */
    public function tier_one_facts_are_not_repeated_for_a_role_that_maps_them(): void
    {
        $record = $this->record(['SubdivisionName' => 'Bradford Acres', 'Zoning' => 'RSF-3']);

        $rendered = $this->flatten(MlsSupplementalDetails::fromRecord($record, 'seller'));

        // Zoning is Tier 1 on both roles — shown from the editable field.
        $this->assertStringNotContainsString('Zoning=RSF-3', $rendered);
        // Subdivision has no editable equivalent — MLS Details is its only home.
        $this->assertStringContainsString('Subdivision=Bradford Acres', $rendered);
    }

    /**
     * @test
     *
     * Role asymmetry is respected. `office_area_sqft` maps on Landlord only, and
     * the landlord listing page does not render it — so it must appear under MLS
     * Details for BOTH roles rather than vanishing on one.
     */
    public function a_fact_with_no_rendered_destination_for_a_role_still_appears(): void
    {
        $record = $this->record(['STELLAR_OfficeRetailSpaceSqFt' => 3762]);

        foreach (['seller', 'landlord'] as $role) {
            $this->assertStringContainsString(
                'Office / Retail Space=3762',
                $this->flatten(MlsSupplementalDetails::fromRecord($record, $role)),
                "Office/retail space vanished for {$role}"
            );
        }
    }
}
