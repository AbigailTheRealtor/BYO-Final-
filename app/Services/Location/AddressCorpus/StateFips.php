<?php

namespace App\Services\Location\AddressCorpus;

/**
 * FIPS state code ↔ USPS state abbreviation.
 *
 * The address corpus scopes an import by FIPS (`addresses.state_fips`, and the
 * `{"region":"florida","state_fips":"12"}` shape the existing `corpus_imports`
 * ledger already speaks), while NAD identifies a row by its USPS `State`
 * abbreviation. Something has to translate, and it is one closed list rather
 * than a conditional at each call site.
 *
 * Territories are included because the corpus is nationwide-capable by design —
 * Florida is a filter value, not a special case in the code.
 */
final class StateFips
{
    /** FIPS → USPS. */
    public const MAP = [
        '01' => 'AL', '02' => 'AK', '04' => 'AZ', '05' => 'AR', '06' => 'CA',
        '08' => 'CO', '09' => 'CT', '10' => 'DE', '11' => 'DC', '12' => 'FL',
        '13' => 'GA', '15' => 'HI', '16' => 'ID', '17' => 'IL', '18' => 'IN',
        '19' => 'IA', '20' => 'KS', '21' => 'KY', '22' => 'LA', '23' => 'ME',
        '24' => 'MD', '25' => 'MA', '26' => 'MI', '27' => 'MN', '28' => 'MS',
        '29' => 'MO', '30' => 'MT', '31' => 'NE', '32' => 'NV', '33' => 'NH',
        '34' => 'NJ', '35' => 'NM', '36' => 'NY', '37' => 'NC', '38' => 'ND',
        '39' => 'OH', '40' => 'OK', '41' => 'OR', '42' => 'PA', '44' => 'RI',
        '45' => 'SC', '46' => 'SD', '47' => 'TN', '48' => 'TX', '49' => 'UT',
        '50' => 'VT', '51' => 'VA', '53' => 'WA', '54' => 'WV', '55' => 'WI',
        '56' => 'WY',
        // Territories and outlying areas.
        '60' => 'AS', '66' => 'GU', '69' => 'MP', '72' => 'PR', '78' => 'VI',
    ];

    /** The USPS abbreviation for a FIPS code, or null when unrecognised. */
    public static function toUsps(string $fips): ?string
    {
        return self::MAP[self::normalizeFips($fips)] ?? null;
    }

    /** The FIPS code for a USPS abbreviation, or null when unrecognised. */
    public static function toFips(string $usps): ?string
    {
        $usps = strtoupper(trim($usps));

        return array_search($usps, self::MAP, true) ?: null;
    }

    /**
     * Zero-pads a FIPS code to two digits.
     *
     * `--state-fips=12` and `--state-fips=6` are both things an operator types;
     * `6` is California and `06` is the stored form. Normalising here means the
     * command accepts either and stores one.
     */
    public static function normalizeFips(string $fips): string
    {
        $digits = preg_replace('/\D/', '', $fips) ?? '';

        return $digits === '' ? '' : str_pad($digits, 2, '0', STR_PAD_LEFT);
    }
}
