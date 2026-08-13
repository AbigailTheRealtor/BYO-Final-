<?php

namespace Tests\Unit\ListingImport;

use App\Services\ListingImport\MlsPropertyDetailsPresenter;
use Tests\TestCase;

/**
 * MlsPropertyDetailsPresenter — the display boundary for Layer C.
 *
 * The compliance tests here are the reason the class is an allow-list. The raw
 * record this presenter reads carries PublicRemarks, ShowingInstructions, agent
 * and office fields, compensation and contact details, and holding them is
 * permitted while publishing them is not. These tests are what make a leak a
 * failing build rather than a licensing incident.
 */
class MlsPropertyDetailsPresenterTest extends TestCase
{
    private function presenter(): MlsPropertyDetailsPresenter
    {
        return new MlsPropertyDetailsPresenter();
    }

    /** A record shaped like a real Stellar one: facts mixed with restricted fields. */
    private function record(array $overrides = []): array
    {
        return array_merge([
            'Appliances'            => ['Dishwasher', 'Microwave', 'Range'],
            'Flooring'              => ['Tile', 'Carpet'],
            'Heating'               => ['Central'],
            'Cooling'               => ['Central Air'],
            'Roof'                  => 'Shingle',
            'ConstructionMaterials' => ['Block', 'Stucco'],
            'PoolPrivateYN'         => true,
            'AssociationYN'         => true,
            'AssociationFee'        => 350,
            'Zoning'                => 'RSF-3',
            'Sewer'                 => ['Public Sewer'],
            'WaterSource'           => ['Public'],

            // Restricted — present in the record, must never be in the output.
            'PublicRemarks'         => 'STUNNING POOL HOME WITH SOARING CEILINGS!',
            'PrivateRemarks'        => 'Seller motivated, call listing agent directly.',
            'ShowingInstructions'   => 'Lockbox on front door, code 1234.',
            'LockBoxLocation'       => 'Front door',
            'ListAgentFullName'     => 'Jordan Blake',
            'ListAgentDirectPhone'  => '555-0100',
            'ListOfficeName'        => 'Blake Realty Group',
            'BuyerAgencyCompensation' => '2.5%',
            'AssociationName'       => 'Pat Morgan',
            'AssociationPhone'      => '555-0199',
            'ListingKey'            => 'STELLAR-MFR-1',
            'OriginatingSystemKey'  => 'SYS-9',
            'Media'                 => [['MediaKey' => 'm1', 'MediaURL' => 'https://cdn/x.jpg']],
        ], $overrides);
    }

    /** Every rendered value, flattened, so a leak can be searched for. */
    private function flatten(array $sections): string
    {
        $out = '';
        foreach ($sections as $section => $rows) {
            $out .= $section . ' ';
            foreach ($rows as $row) {
                $out .= $row['label'] . ' ' . $row['value'] . ' ';
            }
        }

        return $out;
    }

    // ─── Compliance ──────────────────────────────────────────────────────────

    /** @test */
    public function no_excluded_field_value_can_reach_the_output(): void
    {
        $rendered = $this->flatten($this->presenter()->present($this->record()));

        $mustNotAppear = [
            'STUNNING POOL HOME',        // PublicRemarks
            'Seller motivated',          // PrivateRemarks
            'Lockbox on front door',     // ShowingInstructions
            'Jordan Blake',              // ListAgentFullName
            '555-0100',                  // ListAgentDirectPhone
            'Blake Realty Group',        // ListOfficeName
            '2.5%',                      // BuyerAgencyCompensation
            'Pat Morgan',                // AssociationName
            '555-0199',                  // AssociationPhone
            'STELLAR-MFR-1',             // ListingKey
            'SYS-9',                     // OriginatingSystemKey
            'https://cdn/x.jpg',         // Media
        ];

        foreach ($mustNotAppear as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $rendered,
                "A restricted value reached the display layer: {$needle}"
            );
        }
    }

    /**
     * @test
     *
     * The allow-list must fail CLOSED. A field the feed introduces tomorrow, or
     * one nobody has cleared, is simply never rendered.
     */
    public function an_unknown_future_field_is_never_rendered(): void
    {
        $sections = $this->presenter()->present($this->record([
            'AgentNotes'                => 'Do not publish this anywhere.',
            'STELLAR_SomeNewColumn2027' => 'unreviewed value',
        ]));

        $rendered = $this->flatten($sections);

        $this->assertStringNotContainsString('Do not publish', $rendered);
        $this->assertStringNotContainsString('unreviewed value', $rendered);
    }

    /**
     * @test
     *
     * Structural guard: the two constants must not overlap. If somebody adds
     * PublicRemarks to FIELDS, this fails rather than the leak shipping.
     */
    public function no_excluded_field_appears_in_the_allow_list(): void
    {
        $allowed = [];
        foreach (MlsPropertyDetailsPresenter::FIELDS as $fields) {
            $allowed = array_merge($allowed, array_keys($fields));
        }

        $overlap = array_intersect($allowed, array_keys(MlsPropertyDetailsPresenter::EXCLUDED));

        $this->assertSame(
            [],
            array_values($overlap),
            'A field listed as excluded is also in the display allow-list'
        );
    }

    /** @test */
    public function every_allow_listed_section_is_non_empty_and_uniquely_named(): void
    {
        $seen = [];
        foreach (MlsPropertyDetailsPresenter::FIELDS as $section => $fields) {
            $this->assertNotEmpty($fields, "Section {$section} is empty");
            foreach (array_keys($fields) as $field) {
                $this->assertArrayNotHasKey($field, $seen, "Field {$field} is allow-listed twice");
                $seen[$field] = true;
            }
        }
    }

    // ─── Rendering ───────────────────────────────────────────────────────────

    /** @test */
    public function permitted_facts_are_rendered_and_grouped(): void
    {
        $sections = $this->presenter()->present($this->record());

        $this->assertArrayHasKey('Interior', $sections);
        $this->assertArrayHasKey('Exterior', $sections);
        $this->assertArrayHasKey('Utilities', $sections);

        $rendered = $this->flatten($sections);
        $this->assertStringContainsString('Dishwasher, Microwave, Range', $rendered);
        $this->assertStringContainsString('Shingle', $rendered);
        $this->assertStringContainsString('RSF-3', $rendered);
    }

    /** @test */
    public function multi_value_fields_keep_the_feeds_own_order(): void
    {
        $sections = $this->presenter()->present(['Appliances' => ['Range', 'Dishwasher', 'Microwave']]);

        $this->assertSame('Range, Dishwasher, Microwave', $sections['Interior'][0]['value']);
    }

    /** @test */
    public function duplicate_values_within_one_field_are_collapsed(): void
    {
        $sections = $this->presenter()->present(['Appliances' => ['Range', 'Range', 'Microwave']]);

        $this->assertSame('Range, Microwave', $sections['Interior'][0]['value']);
    }

    /** @test */
    public function an_empty_record_renders_nothing_at_all(): void
    {
        $this->assertSame([], $this->presenter()->present([]));
        $this->assertFalse($this->presenter()->hasAnything([]));
    }

    /** @test */
    public function a_section_with_no_populated_field_is_omitted_entirely(): void
    {
        $sections = $this->presenter()->present(['Zoning' => 'RSF-3']);

        $this->assertSame(['Property Details'], array_keys($sections));
    }

    /** @test */
    public function null_and_empty_values_are_skipped_safely(): void
    {
        $sections = $this->presenter()->present([
            'Appliances' => [],
            'Flooring'   => null,
            'Roof'       => '   ',
            'Zoning'     => 'RSF-3',
        ]);

        $this->assertSame(['Property Details'], array_keys($sections));
    }

    /**
     * @test
     *
     * "Pool: No / Spa: No / Waterfront: No" buries the facts the reader came
     * for. Absence already carries the meaning.
     */
    public function a_false_flag_is_omitted_rather_than_rendered_as_no(): void
    {
        $sections = $this->presenter()->present([
            'PoolPrivateYN'  => true,
            'WaterfrontYN'   => false,
            'SpaYN'          => 'No',
            'NewConstructionYN' => 'false',
        ]);

        $rendered = $this->flatten($sections);

        $this->assertStringContainsString('Private Pool Yes', $rendered);
        $this->assertStringNotContainsString('Waterfront', $rendered);
        $this->assertStringNotContainsString('Spa', $rendered);
        $this->assertStringNotContainsString('New Construction', $rendered);
    }

    /** @test */
    public function string_and_native_booleans_render_identically(): void
    {
        $a = $this->presenter()->present(['PoolPrivateYN' => true]);
        $b = $this->presenter()->present(['PoolPrivateYN' => 'Y']);
        $c = $this->presenter()->present(['PoolPrivateYN' => 'true']);

        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
    }

    /** @test */
    public function an_alias_pair_does_not_render_the_same_label_twice(): void
    {
        $sections = $this->presenter()->present([
            'Roof'     => 'Shingle',
            'RoofType' => 'Shingle',
        ]);

        $labels = array_column($sections['Exterior'], 'label');
        $this->assertSame($labels, array_unique($labels));
    }

    /** @test */
    public function nested_arrays_and_objects_inside_a_value_are_skipped_not_stringified(): void
    {
        $sections = $this->presenter()->present([
            'Appliances' => ['Range', ['nested' => 'thing'], (object) ['a' => 1], 'Microwave'],
        ]);

        $this->assertSame('Range, Microwave', $sections['Interior'][0]['value']);
    }
}
