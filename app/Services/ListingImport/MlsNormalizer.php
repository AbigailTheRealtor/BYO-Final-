<?php

namespace App\Services\ListingImport;

class MlsNormalizer
{
    /**
     * Normalize a parsed MLS field value to the app-expected format.
     *
     * @param  string $field  Canonical import key (e.g. 'pool', 'lease_amount_frequency')
     * @param  string $value  Raw extracted text
     * @return string         Normalized value
     */
    public static function normalize(string $field, string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return $v;
        }

        return match (strtolower($field)) {
            'pool', 'pool_needed',
            'garage', 'garage_needed',
            'carport', 'carport_needed',
            'waterfront',
            'additional_parcels',
            'has_hoa',
            'has_cdd',
            'flood_insurance_required',
            'has_special_assessments',
            'pets_allowed'              => self::normalizeBoolean($v),

            'furnished'             => self::normalizeFurnishing($v),
            'flood_zone_code'       => self::normalizeFloodZone($v),
            'lease_amount_frequency'=> self::normalizeLeaseFrequency($v),
            'association_fee_frequency' => self::normalizeHoaFeeFrequency($v),
            'lease_rate_type'           => self::normalizeLeaseRateType($v),

            default                 => $v,
        };
    }

    // ─── Boolean ─────────────────────────────────────────────────────────────

    /**
     * Coerce MLS Yes/No/Y/N variants to lowercase "yes" or "no".
     * Values that don't match are returned as-is (e.g. pool type strings).
     *
     * Also strips the "Y/N:" prefix that some MLS exports emit for boolean fields
     * (e.g. "Additional Parcels Y/N: Y/N:No" → "No" → "no").
     */
    public static function normalizeBoolean(string $value): string
    {
        // Strip leading "Y/N:" prefix emitted by some MLS exports before boolean lookup.
        $value = preg_replace('/^Y\/N\s*:\s*/i', '', trim($value));

        $lower = strtolower(trim($value));

        if (in_array($lower, ['yes', 'y', 'true', '1'], true)) {
            return 'yes';
        }

        if (in_array($lower, ['no', 'n', 'false', '0'], true)) {
            return 'no';
        }

        return $value; // pass-through for partial values like "In Ground", "Heated"
    }

    // ─── Furnishing ──────────────────────────────────────────────────────────

    /**
     * Normalize MLS Furnishings dropdown to a consistent lowercase value.
     * MLS options: Furnished, Negotiable, Partial, Turnkey, Unfurnished
     */
    public static function normalizeFurnishing(string $value): string
    {
        return match (strtolower(trim($value))) {
            'furnished'   => 'furnished',
            'negotiable'  => 'negotiable',
            'partial'     => 'partial',
            'turnkey'     => 'turnkey',
            'unfurnished' => 'unfurnished',
            default       => $value,
        };
    }

    // ─── Flood Zone ──────────────────────────────────────────────────────────

    /**
     * Normalize flood zone code strings.
     * Common FEMA zone codes are returned uppercased; "Flood Insurance Required"
     * signals zone AE/VE territory and is normalized to "yes" (has_flood_insurance).
     */
    public static function normalizeFloodZone(string $value): string
    {
        $lower = strtolower(trim($value));

        if (str_contains($lower, 'insurance required') || str_contains($lower, 'flood insurance')) {
            return 'yes';
        }

        // Zone codes like X, AE, VE, A, V, AH, AO — return uppercased
        return strtoupper(trim($value));
    }

    // ─── HOA Fee Frequency ───────────────────────────────────────────────────

    /**
     * Normalize MLS Association Fee Frequency to a consistent lowercase value.
     * MLS options: Monthly, Quarterly, Annually, Semi-Annually, One-Time
     */
    public static function normalizeHoaFeeFrequency(string $value): string
    {
        return match (strtolower(trim($value))) {
            'monthly', 'month'                => 'monthly',
            'quarterly', 'quarter'            => 'quarterly',
            'annually', 'annual', 'yearly'    => 'annually',
            'semi-annually', 'semi-annual',
            'semiannually', 'bi-annual'       => 'semi_annually',
            'one-time', 'one time', 'onetime' => 'one_time',
            default                           => strtolower($value),
        };
    }

    // ─── Lease Rate Type ─────────────────────────────────────────────────────

    /**
     * Normalize MLS Lease Rate Type to a canonical lowercase token.
     *
     * Common MLS values and their canonical forms:
     *   NNN / Triple Net / Net Net Net  → 'nnn'
     *   Gross / Full Service Gross      → 'gross'
     *   Modified Gross / Mod. Gross     → 'modified_gross'
     *   Absolute Net / Absolute NNN     → 'absolute_nnn'
     *   Double Net / Net Net / NN       → 'double_net'
     *   Net / Single Net                → 'net'
     *
     * Unrecognized values are lowercased with spaces replaced by underscores.
     */
    public static function normalizeLeaseRateType(string $value): string
    {
        return match (strtolower(trim($value))) {
            'nnn', 'triple net', 'triple-net', 'net net net'    => 'nnn',
            'gross', 'full service gross', 'full service'        => 'gross',
            'modified gross', 'modified-gross', 'mod. gross',
            'modified gross lease', 'mod gross'                  => 'modified_gross',
            'absolute net', 'absolute nnn', 'absolute triple net' => 'absolute_nnn',
            'double net', 'net net', 'nn'                        => 'double_net',
            'net', 'single net'                                  => 'net',
            default                                              => strtolower(
                str_replace([' ', '-'], '_', trim($value))
            ),
        };
    }

    // ─── Lease Frequency ─────────────────────────────────────────────────────

    /**
     * Normalize MLS Lease Amount Frequency to lowercase.
     * MLS options: Annually, Daily, Monthly, Seasonal, Weekly,
     *              >6 Months <12, 12 Months, 24 Months, Month to Month,
     *              Short Term Lease
     */
    public static function normalizeLeaseFrequency(string $value): string
    {
        return match (strtolower(trim($value))) {
            'annually', 'annual'          => 'annually',
            'daily'                       => 'daily',
            'monthly', 'month'            => 'monthly',
            'seasonal'                    => 'seasonal',
            'weekly', 'week'              => 'weekly',
            'month to month'              => 'month_to_month',
            '12 months', '12-month'       => '12_months',
            '24 months', '24-month'       => '24_months',
            '>6 months <12'               => '6_to_12_months',
            'short term lease', 'short'   => 'short_term',
            default                       => strtolower($value),
        };
    }
}
