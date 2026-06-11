<?php

namespace App\Services\AskAi\Snapshot;

/**
 * SnapshotFactVisibility — Centralised visibility classification for snapshot facts.
 *
 * GOVERNANCE:
 * - 'public_allowed'  — public-factual fields; safe to surface in Ask AI responses.
 * - 'restricted'      — compliance-sensitive fields (flood zone, financial thresholds,
 *                       income/deposit requirements). Included in the snapshot for
 *                       completeness but must not be surfaced in unauthenticated or
 *                       unqualified Ask AI responses.
 *
 * This list is intentionally conservative: any key not listed here defaults to
 * 'public_allowed'. Add keys here only when they carry a disclosure obligation or
 * carry data that must not be freely surfaced (e.g. flood zone code, security deposit).
 *
 * PII fields (names, phone, email, brokerage) are excluded from the context builder
 * output entirely and are never persisted as facts.
 */
class SnapshotFactVisibility
{
    /**
     * Canonical fact keys that must be stored as 'restricted'.
     * These are compliance-sensitive fields included in the listing context.
     */
    private const RESTRICTED_KEYS = [
        // Flood zone / environmental compliance
        'flood_zone_code',
        'flood_zone_designation',
        'flood_zone_description',
        'is_in_flood_zone',

        // Financial thresholds with disclosure obligations
        'security_deposit',
        'security_deposit_amount',
        'income_requirement',
        'income_requirement_amount',
        'income_multiplier',

        // HOA / CDD amounts (compliance-disclosure fields in seller context)
        'hoa_monthly_fee',
        'hoa_annual_fee',
        'cdd_annual_amount',
        'cdd_monthly_amount',

        // Rental pricing fields used in landlord/tenant contexts
        'rental_price',
        'min_rent',
        'max_rent',

        // Seller financing terms
        'seller_financing_down_payment',
        'seller_financing_interest_rate',
        'seller_financing_term',
    ];

    /**
     * Returns the visibility string for the given canonical fact key.
     *
     * @param  string  $key  Canonical fact key from the listing context.
     * @return 'public_allowed'|'restricted'
     */
    public static function classify(string $key): string
    {
        return in_array($key, self::RESTRICTED_KEYS, true) ? 'restricted' : 'public_allowed';
    }

    /**
     * Derives a human-readable label from a snake_case canonical key.
     * e.g. 'flood_zone_code' → 'Flood Zone Code'
     */
    public static function deriveLabel(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Detects the storage type of a fact value.
     * Returns one of: 'null', 'json', 'numeric', 'boolean', 'string'.
     */
    public static function detectValueType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            return 'json';
        }
        $str = trim((string) $value);
        if ($str === '') {
            return 'null';
        }
        if (str_starts_with($str, '{') || str_starts_with($str, '[')) {
            return 'json';
        }
        if (is_numeric($str)) {
            return 'numeric';
        }
        if (in_array(strtolower($str), ['true', 'false', 'yes', 'no'], true)) {
            return 'boolean';
        }
        return 'string';
    }
}
