<?php

namespace App\Support\Listing;

/**
 * The one place a source property type becomes BidYourOffer's own vocabulary.
 *
 * WHY THIS EXISTS
 * ---------------
 * BYO does not use RESO's property-type words, and the two roles do not even
 * use the same words as each other:
 *
 *   Landlord forms say  "Residential Property" / "Commercial Property"
 *   Seller/Buyer/Tenant say "Residential" / "Commercial" / "Business" /
 *                           "Income" / "Vacant Land"
 *
 * A feed says "Residential Lease", "Commercial Sale", "Business Opportunity".
 * Every one of those has to be translated before a canonical Blade compares it,
 * because the canonical Landlord Leasing Terms partial gates fourteen
 * conditional sections on an EXACT match against its two words.
 *
 * THE BUG THIS CLOSES
 * -------------------
 * The URL/text importer translated; MLS Quick Import did not. So a landlord who
 * imported a Residential Lease record carried the literal string "Residential
 * Lease" into a Blade asking `$property_type === 'Residential Property'`, which
 * is false — and utilities, maintenance responsibility and response time,
 * renewal details, rent escalation, storage, owner-pays, terms of lease, the
 * commercial lease terms and CAM/NNN simply did not render. The properties were
 * all there; the sections that show them were switched off.
 *
 * The fix is normalisation at the boundary, NOT teaching the Blade to accept
 * feed vocabulary. One internal vocabulary, translated once on the way in, is
 * the only version of this that stays correct — aliases scattered through views
 * multiply with every feed quirk and every new tab.
 *
 * The rules below are moved verbatim from
 * HasMlsImport::normalizePropertyTypeForRole(), which now delegates here, so the
 * URL/text path's long-standing behaviour is unchanged and the two paths cannot
 * drift.
 */
final class PropertyTypeVocabulary
{
    /**
     * Translate a source property type into the vocabulary $role's forms use.
     *
     * Substring matching, not equality: feeds say "Residential Lease",
     * "Residential Income", "Single Family Residence" and half a dozen other
     * things that all mean the same thing to a BYO form.
     *
     * An unrecognised value passes through untouched. That is deliberate — a
     * value already in BYO vocabulary must survive a second pass unchanged
     * (the function is idempotent), and a genuinely unknown type is better left
     * visible than silently rewritten into a category it may not belong to.
     */
    public static function forRole(string $value, string $role): string
    {
        $v     = trim($value);
        $lower = strtolower($v);

        if ($role === 'landlord') {
            // Landlord blades use "Residential Property" / "Commercial Property".
            if (str_contains($lower, 'commercial'))  return 'Commercial Property';
            if (str_contains($lower, 'residential')) return 'Residential Property';

            return $v;
        }

        // Seller, buyer, tenant: short-form values (no " Property" suffix).
        if (str_contains($lower, 'residential')   || str_contains($lower, 'single family')
            || str_contains($lower, 'condominium') || str_contains($lower, 'condo')
            || str_contains($lower, 'townhome')    || str_contains($lower, 'townhouse')
            || str_contains($lower, 'mobile home')) {
            return 'Residential';
        }

        // "Business Opportunity" → 'Business'. Must precede the 'commercial'
        // check: some exports say "Business, Commercial".
        if (str_contains($lower, 'business')) {
            return 'Business';
        }

        if (str_contains($lower, 'commercial')) {
            return 'Commercial';
        }

        if (str_contains($lower, 'income') || str_contains($lower, 'multifamily')
            || str_contains($lower, 'multi-family') || str_contains($lower, 'multi family')) {
            return 'Income';
        }

        if (str_contains($lower, 'vacant') || str_contains($lower, 'land')) {
            return 'Vacant Land';
        }

        return $v; // already-normalised or unrecognised — pass through
    }
}
