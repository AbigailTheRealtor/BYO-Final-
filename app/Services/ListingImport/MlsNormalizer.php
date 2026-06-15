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
            // Form-select Yes/No fields: return Title Case "Yes"/"No" so values
            // match <option value="Yes"> / <option value="No"> exactly.
            // Also handles "None" → "No" and multi-value garage strings like
            // "Yes, Attached, 1 Spaces" → "Yes".
            'pool', 'pool_needed',
            'garage', 'garage_needed',
            'carport', 'carport_needed'     => self::normalizeFormYesNo($v),

            // Boolean storage fields: lowercase "yes" / "no" (not form selects)
            'waterfront',
            'additional_parcels',
            'has_hoa',
            'has_cdd',
            'flood_insurance_required',
            'has_special_assessments',
            'pets_allowed',
            'inventory_included',
            'seller_financing_yn'           => self::normalizeBoolean($v),

            'furnished'                     => self::normalizeFurnishing($v),
            'sewer'                         => self::normalizeSewer($v),
            'flood_zone_code'               => self::normalizeFloodZone($v),
            'lease_amount_frequency'        => self::normalizeLeaseFrequency($v),
            'association_fee_frequency'     => self::normalizeHoaFeeFrequency($v),
            'lease_rate_type'               => self::normalizeLeaseRateType($v),

            'cap_rate'                      => self::normalizeCapRate($v),
            'net_operating_income'          => self::normalizeNetOperatingIncome($v),

            default                         => $v,
        };
    }

    // ─── Form-select Yes/No ───────────────────────────────────────────────────

    /**
     * Normalize pool / garage / carport values to Title Case "Yes" or "No",
     * matching the form's <option value="Yes"> / <option value="No"> exactly.
     *
     * Special handling:
     *   "None"                  → "No"   (MLS emits "Pool: None" when no pool)
     *   "Yes, Attached, 1 Spaces" → "Yes"  (first comma-token extracted)
     *   "Y/N: Yes"              → "Yes"  (Y/N prefix stripped)
     *
     * Non-yes/no values (e.g. "In Ground", "2 Car") are returned as-is so
     * they remain visible in the import preview.
     */
    public static function normalizeFormYesNo(string $value): string
    {
        // Strip leading "Y/N:" prefix emitted by some MLS exports.
        $value = preg_replace('/^Y\/N\s*:\s*/i', '', trim($value));

        // For comma-separated values like "Yes, Attached, 1 Spaces", use only
        // the first token before the comma.
        $firstToken = trim(explode(',', $value)[0]);

        $lower = strtolower($firstToken);

        if (in_array($lower, ['yes', 'y', 'true', '1'], true)) {
            return 'Yes';
        }

        // 'none' is the MLS way of saying "no pool / no garage / no carport".
        if (in_array($lower, ['no', 'n', 'false', '0', 'none'], true)) {
            return 'No';
        }

        return $value; // pass-through for descriptive values like "In Ground", "2 Car"
    }

    // ─── Boolean ─────────────────────────────────────────────────────────────

    /**
     * Coerce MLS Yes/No/Y/N variants to lowercase "yes" or "no".
     * Values that don't match are returned as-is (e.g. pool type strings).
     *
     * Also strips the "Y/N:" prefix that some MLS exports emit for boolean fields
     * (e.g. "Additional Parcels Y/N: Y/N:No" → "No" → "no").
     *
     * Note: pool/garage/carport use normalizeFormYesNo() instead (Title Case).
     * This method is reserved for boolean storage fields (has_hoa, has_cdd, etc.)
     * that do not correspond to a form select element.
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
     * Normalize MLS Furnishings dropdown to the Title Case value expected by
     * form select elements (tenant_require / building_features).
     *
     * MLS options: Furnished, Negotiable, Partial, Turnkey, Unfurnished
     *
     * Title Case is required because:
     *   - Landlord: tenant_require select uses <option value="{{ $row_pt['name'] }}">
     *     where names are Title Case ('Furnished', 'Unfurnished', etc.).
     *   - Seller: building_features merge logic applies ucfirst() internally, so
     *     Title Case output here is consistent with its own normalization step.
     */
    public static function normalizeFurnishing(string $value): string
    {
        return match (strtolower(trim($value))) {
            'furnished'   => 'Furnished',
            'negotiable'  => 'Negotiable',
            'partial'     => 'Partial',
            'turnkey'     => 'Turnkey',
            'unfurnished' => 'Unfurnished',
            default       => $value,
        };
    }

    // ─── Sewer ───────────────────────────────────────────────────────────────

    /**
     * Normalize MLS sewer values to match form select options.
     *
     * Form options: Aerobic Septic, PEP-Holding Tank, Private Sewer, Public Sewer,
     *               Septic Needed, Septic Tank, None, Other
     *
     * Handles comma-separated MLS multi-values, deduplicates, and maps common
     * shorthand MLS tokens (e.g. "Connected" → "Public Sewer").
     */
    public static function normalizeSewer(string $value): string
    {
        $tokens    = array_map('trim', explode(',', $value));
        $result    = [];
        $seen      = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            $normalized = self::normalizeSewerToken($token);
            if (!in_array($normalized, $seen, true)) {
                $seen[]   = $normalized;
                $result[] = $normalized;
            }
        }

        return implode(', ', $result);
    }

    /**
     * Normalize a single sewer token to the nearest form option.
     */
    private static function normalizeSewerToken(string $token): string
    {
        static $formOptions = [
            'Aerobic Septic', 'PEP-Holding Tank', 'Private Sewer', 'Public Sewer',
            'Septic Needed', 'Septic Tank', 'None', 'Other',
        ];

        // Exact case-insensitive match against a known form option.
        foreach ($formOptions as $opt) {
            if (strcasecmp($token, $opt) === 0) {
                return $opt;
            }
        }

        $lower = strtolower($token);

        // "Connected" / "Water Connected" / "Municipal" → public sewer is connected.
        // "Water Connected" appears in some MLS exports inside the Sewer field as
        // shorthand for a fully connected public sewer utility.
        if ($lower === 'connected'
            || $lower === 'water connected'
            || str_contains($lower, 'municipal')
        ) {
            return 'Public Sewer';
        }

        // Partial matches for common MLS shorthand values.
        if (str_contains($lower, 'public')) {
            return 'Public Sewer';
        }
        if (str_contains($lower, 'aerobic')) {
            return 'Aerobic Septic';
        }
        if (str_contains($lower, 'private')) {
            return 'Private Sewer';
        }
        if (str_contains($lower, 'septic needed')) {
            return 'Septic Needed';
        }
        if (str_contains($lower, 'septic')) {
            return 'Septic Tank';
        }
        if ($lower === 'none') {
            return 'None';
        }

        return $token; // pass-through for unrecognised values
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

    // ─── Cap Rate ────────────────────────────────────────────────────────────

    /**
     * Normalize MLS Cap Rate value.
     * Strips trailing % sign and returns a plain numeric string (e.g. "8.5" not "8.5%").
     */
    public static function normalizeCapRate(string $value): string
    {
        $v = trim($value);
        // Strip trailing % sign
        $v = rtrim($v, '%');
        return trim($v);
    }

    // ─── Net Operating Income ─────────────────────────────────────────────────

    /**
     * Normalize MLS Net Operating Income (NOI) value.
     * Strips leading $ sign and comma separators, returns plain numeric string.
     */
    public static function normalizeNetOperatingIncome(string $value): string
    {
        return preg_replace('/[^\d.]/', '', $value);
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
