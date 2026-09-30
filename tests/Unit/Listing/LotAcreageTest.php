<?php

namespace Tests\Unit\Listing;

use App\Support\Listing\LotAcreage;
use PHPUnit\Framework\TestCase;

/**
 * LotAcreage reads an acreage field with its unit preserved. Pure — no application booted.
 */
class LotAcreageTest extends TestCase
{
    /** @dataProvider readings */
    public function test_reading(mixed $stored, ?string $kind, ?string $display): void
    {
        $reading = LotAcreage::fromStored($stored);

        $this->assertSame($kind, $reading?->kind, var_export($stored, true));
        $this->assertSame($display, $reading?->display(), var_export($stored, true));
    }

    public static function readings(): array
    {
        return [
            // A bare number in an acreage field is ACRES, digits exactly as stored.
            'decimal'             => ['5.2', LotAcreage::KIND_ACRES, '5.2 acres'],
            'float'               => [5.2, LotAcreage::KIND_ACRES, '5.2 acres'],
            'trailing zero kept'  => ['5.20', LotAcreage::KIND_ACRES, '5.20 acres'],
            'leading dot'         => ['.5', LotAcreage::KIND_ACRES, '.5 acres'],
            'singular'            => ['1', LotAcreage::KIND_ACRES, '1 acre'],
            'singular decimal'    => ['1.0', LotAcreage::KIND_ACRES, '1.0 acre'],
            'grouped'             => ['1,250.5', LotAcreage::KIND_ACRES, '1,250.5 acres'],
            'stated acres'        => ['5.2 acres', LotAcreage::KIND_ACRES, '5.2 acres'],
            'stated ac'           => ['12 ac', LotAcreage::KIND_ACRES, '12 acres'],
            // An explicit square-foot value stays square feet — never converted.
            'sq ft'               => ['12,680 sq ft', LotAcreage::KIND_SQUARE_FEET, '12,680 square feet'],
            'sqft'                => ['12680sqft', LotAcreage::KIND_SQUARE_FEET, '12680 square feet'],
            'square feet'         => ['544500 Square Feet', LotAcreage::KIND_SQUARE_FEET, '544500 square feet'],
            // Bands verbatim, with or without the unit suffix Stellar leaves off.
            'band'                => ['5 to less than 10 acres', LotAcreage::KIND_BAND, '5 to less than 10 acres'],
            'band singular'       => ['0 to less than 1/4 acre', LotAcreage::KIND_BAND, '0 to less than 1/4 acre'],
            'stellar band'        => ['1/4 to less than 1/2', LotAcreage::KIND_BAND, '1/4 to less than 1/2 acre'],
            'band case'           => ['500+ ACRES', LotAcreage::KIND_BAND, '500+ acres'],
            // Nothing stateable.
            'non-applicable'      => ['Non-Applicable', null, null],
            'zero'                => ['0', null, null],
            'zero decimal'        => ['0.00', null, null],
            'negative'            => ['-3', null, null],
            'blank'               => ['  ', null, null],
            'null'                => [null, null, null],
            'bool'                => [true, null, null],
            'array'               => [['5'], null, null],
            'free text'           => ['about five acres', null, null],
            'range'               => ['5-10', null, null],
            'bad grouping'        => ['12,68', null, null],
            'unknown unit'        => ['5 hectares', null, null],
        ];
    }

    public function test_first_of_follows_the_page_precedence_and_does_not_skip_a_present_value(): void
    {
        $this->assertSame('3 acres', LotAcreage::firstOf('3', '5 to less than 10 acres')?->display());
        $this->assertSame('5 to less than 10 acres', LotAcreage::firstOf('', null, '5 to less than 10 acres')?->display());
        // The first PRESENT value decides, as `$a ?: $b` does on the page — an unreadable
        // one is not silently replaced by a different field's value.
        $this->assertNull(LotAcreage::firstOf('Non-Applicable', '2'));
    }

    public function test_display_stored_keeps_an_unreadable_value_exactly_as_the_page_printed_it(): void
    {
        $this->assertSame('5.2 acres', LotAcreage::displayStored('5.2'));
        $this->assertSame('Non-Applicable', LotAcreage::displayStored('Non-Applicable'));
        $this->assertSame('about five acres', LotAcreage::displayStored(' about five acres '));
        $this->assertSame('', LotAcreage::displayStored(null));
        $this->assertSame('', LotAcreage::displayStored(''));
    }
}
