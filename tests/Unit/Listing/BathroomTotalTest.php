<?php

namespace Tests\Unit\Listing;

use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Support\Listing\BathroomTotal;
use PHPUnit\Framework\TestCase;

/**
 * The one reading of a bathroom total from RESO components. Pure — no application.
 */
class BathroomTotalTest extends TestCase
{
    /** @dataProvider records */
    public function test_the_canonical_total(array $raw, ?float $expected): void
    {
        $this->assertSame($expected, BathroomTotal::fromRecord($raw));
    }

    public static function records(): array
    {
        return [
            // The live Stellar shape: integer 2 is ROUNDED, the decimal is the truth.
            'decimal wins over the rounded integer'   => [['BathroomsTotalDecimal' => 1.5, 'BathroomsTotalInteger' => 2, 'BathroomsFull' => 1, 'BathroomsHalf' => 1], 1.5],
            'decimal as a numeric string'             => [['BathroomsTotalDecimal' => '2.5'], 2.5],
            'no decimal: full + half'                 => [['BathroomsTotalInteger' => 2, 'BathroomsFull' => 1, 'BathroomsHalf' => 1], 1.5],
            'components as stored strings'            => [['BathroomsFull' => '2', 'BathroomsHalf' => '1'], 2.5],
            'full with an explicit zero half'         => [['BathroomsFull' => 3, 'BathroomsHalf' => 0], 3.0],
            'integer equal to full rules halves out'  => [['BathroomsTotalInteger' => 2, 'BathroomsFull' => 2], 2.0],
            'integer exact when half is zero'         => [['BathroomsTotalInteger' => 2, 'BathroomsHalf' => 0], 2.0],

            // Unknown stays unknown.
            'rounded integer alone is never trusted'  => [['BathroomsTotalInteger' => 2], null],
            'integer with an unknown half count'      => [['BathroomsTotalInteger' => 3, 'BathroomsFull' => 2], null],
            'integer with a half bath is rounded'     => [['BathroomsTotalInteger' => 2, 'BathroomsHalf' => 1], null],
            'a sent decimal that is not in halves'    => [['BathroomsTotalDecimal' => 2.25, 'BathroomsFull' => 2, 'BathroomsHalf' => 0], null],
            'a sent decimal that is not a number'     => [['BathroomsTotalDecimal' => 'two', 'BathroomsFull' => 2, 'BathroomsHalf' => 0], null],
            'quarter baths are not a convention held' => [['BathroomsFull' => 2, 'BathroomsHalf' => 0, 'BathroomsThreeQuarter' => 1], null],
            'commercial placeholder zero'             => [['BathroomsTotalInteger' => 0, 'BathroomsHalf' => 0], null],
            'zero components'                         => [['BathroomsFull' => 0, 'BathroomsHalf' => 0], null],
            'nothing at all'                          => [[], null],
        ];
    }

    public function test_the_total_is_read_from_the_stored_mls_details_rows(): void
    {
        $details = MlsSupplementalDetails::fromStored(['version' => 1, 'sections' => [
            ['title' => 'Interior', 'group' => 'facts', 'rows' => [
                ['key' => 'BathroomsFull', 'label' => 'Full Bathrooms', 'value' => '1'],
                ['key' => 'BathroomsHalf', 'label' => 'Half Bathrooms', 'value' => '1'],
            ]],
        ]]);

        $this->assertSame(1.5, BathroomTotal::fromMlsDetails($details));
    }

    public function test_the_form_spelling(): void
    {
        $this->assertSame('1.5', BathroomTotal::format(1.5));
        $this->assertSame('2', BathroomTotal::format(2.0));
        $this->assertSame('10.5', BathroomTotal::format(10.5));
    }
}
