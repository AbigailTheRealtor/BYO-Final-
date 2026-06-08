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
            'has_cdd'               => self::normalizeBoolean($v),

            'furnished'             => self::normalizeFurnishing($v),
            'flood_zone_code'       => self::normalizeFloodZone($v),
            'lease_amount_frequency'=> self::normalizeLeaseFrequency($v),
            'association_fee_frequency' => self::normalizeHoaFeeFrequency($v),

            default                 => $v,
        };
    }

    // ─── Boolean ─────────────────────────────────────────────────────────────

    /**
     * Coerce MLS Yes/No/Y/N variants to lowercase "yes" or "no".
     * Values that don't match are returned as-is (e.g. pool type strings).
     */
    public static function normalizeBoolean(string $value): string
    {
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
