<?php

namespace Tests\Unit\Listing;

use App\Services\ListingImport\MlsNormalizer;
use App\Support\Listing\FloodZoneCode;
use PHPUnit\Framework\TestCase;

/**
 * `flood_zone_code` must hold a FEMA designation or nothing.
 *
 * The field is written from three directions that disagreed about what belongs in it: a
 * Seller/Landlord select whose own options include `Unknown` and `Other` (with a free-text
 * branch behind `Other`), an MLS importer that uppercased whatever it was handed, and that
 * same importer's "Flood Insurance Required" branch, which stored the literal string `yes` —
 * a boolean answer about INSURANCE — as the property's ZONE.
 *
 * RECOGNITION, NOT EXTRACTION, is the property under test. "Zone AE" does not yield "AE" and
 * a Yes/No flood flag does not yield a zone: either would invent a FEMA determination the
 * record does not make. That is why the invalid cases below assert null rather than a
 * best-effort designation.
 *
 * A plain PHPUnit TestCase — this class touches no container, no config and no database.
 */
class FloodZoneCodeTest extends TestCase
{
    /**
     * @dataProvider usableCodes
     */
    public function test_a_designation_is_recognised_and_uppercased(string $input, string $expected): void
    {
        $this->assertSame($expected, FloodZoneCode::canonical($input));
        $this->assertTrue(FloodZoneCode::isUsable($input));
    }

    public static function usableCodes(): array
    {
        return [
            'X stays X'                  => ['X', 'X'],
            'AE stays AE'                => ['AE', 'AE'],
            'VE stays VE'                => ['VE', 'VE'],
            'lowercase x uppercases'     => ['x', 'X'],
            'lowercase ae uppercases'    => ['ae', 'AE'],
            'lowercase ve uppercases'    => ['ve', 'VE'],
            'surrounding space trimmed'  => ['  ae  ', 'AE'],
            'single letter zone A'       => ['A', 'A'],
            'shallow flooding AH'        => ['AH', 'AH'],
            'sheet flow AO'              => ['AO', 'AO'],
            'coastal V'                  => ['V', 'V'],
            'undetermined D'             => ['D', 'D'],
            'alphanumeric A99'           => ['a99', 'A99'],
            'four characters AR12'       => ['ar12', 'AR12'],
        ];
    }

    /**
     * @dataProvider unusableValues
     */
    public function test_a_value_that_is_not_a_designation_is_refused(mixed $input): void
    {
        $this->assertNull(FloodZoneCode::canonical($input));
        $this->assertFalse(FloodZoneCode::isUsable($input));
    }

    public static function unusableValues(): array
    {
        return [
            // Boolean-like answers. NO, NA, YES, NONE and TBD all satisfy the shape rule,
            // so the sentinel list — not the pattern — is what refuses them.
            'yes'            => ['yes'],
            'Yes'            => ['Yes'],
            'YES'            => ['YES'],
            'no'             => ['no'],
            'No'             => ['No'],
            'true boolean'   => [true],
            'false boolean'  => [false],

            // The form's own non-answers.
            'unknown'        => ['unknown'],
            'Unknown'        => ['Unknown'],
            'other'          => ['Other'],
            'n/a'            => ['N/A'],
            'na'             => ['NA'],
            'none'           => ['None'],
            'tbd'            => ['TBD'],
            'flood'          => ['Flood'],

            // Blank and whitespace.
            'empty string'   => [''],
            'only spaces'    => ['   '],
            'null'           => [null],

            // Prose. None of these is parsed down to a designation.
            'zone ae'            => ['Zone AE'],
            'ae with risk note'  => ['AE - high risk'],
            'sentence'           => ['This property is in a flood zone'],
            'insurance phrase'   => ['Flood Insurance Required'],
            'too long'           => ['AE123'],
            'punctuation'        => ['A-E'],
            'array'              => [['AE']],
        ];
    }

    public function test_a_flood_flag_never_becomes_a_zone(): void
    {
        // The specific defect: a boolean answer about insurance stored as the zone code.
        foreach (['yes', 'no', 'Yes', 'No', true, false, 1, 0] as $flag) {
            $this->assertNull(FloodZoneCode::canonical($flag),
                'A flood flag must never resolve to a zone designation.');
        }
    }

    /* ================================================================== */
    /* The MLS importer, through the same rule                             */
    /* ================================================================== */

    /**
     * @dataProvider importerCases
     */
    public function test_the_mls_normalizer_stores_a_designation_or_nothing(string $input, string $expected): void
    {
        $this->assertSame($expected, MlsNormalizer::normalize('flood_zone_code', $input));
    }

    public static function importerCases(): array
    {
        return [
            'AE stays AE'                      => ['AE', 'AE'],
            'X stays X'                        => ['X', 'X'],
            'VE stays VE'                      => ['VE', 'VE'],
            'lowercase normalizes up'          => ['ae', 'AE'],
            'lowercase x normalizes up'        => ['x', 'X'],

            // The defect this fixes: these produced the literal string 'yes' in the ZONE
            // field. The insurance signal still reaches the listing — from the MLS's own
            // "Flood Insurance Reqd" field, through normalizeBoolean(), where it belongs.
            'flood insurance phrase'           => ['Flood Insurance Required', ''],
            'insurance required phrase'        => ['Insurance Required', ''],

            'yes is not a code'                => ['yes', ''],
            'no is not a code'                 => ['no', ''],
            'unknown is not a code'            => ['Unknown', ''],
            'n/a is not a code'                => ['N/A', ''],
            'other is not a code'              => ['Other', ''],
            'prose is not a code'              => ['Zone AE', ''],
            'annotated code is not a code'     => ['AE - high risk', ''],
        ];
    }
}
