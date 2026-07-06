<?php

namespace App\Services\Stellar\MatchCheck;

use App\Models\BridgeProperty;

/**
 * Detects whether a Bridge/Stellar property is For Sale (→ Buyer criteria) or
 * For Rent (→ Tenant criteria), for Match Check auto-selection (Phase 4 · F5).
 *
 * Signal = the RESO PropertyType string. In this feed the confirmed values are:
 *   Sale   (buyer):  'Residential', 'Income', 'Commercial Sale', 'Business Opportunity', 'Vacant Land'
 *   Rental (tenant): 'Residential', 'Commercial Lease'
 *
 * Note that residential RENTALS reuse PropertyType 'Residential' — the same string
 * as residential SALES — so a bare 'Residential'/'Commercial' is genuinely ambiguous
 * and returns null. Per F5, a null result means "cannot auto-detect": the caller
 * falls back to the user's preferred criteria / manual switch / empty-state, and
 * never silently scores with the wrong engine.
 *
 * StandardStatus / MlsStatus are intentionally NOT consulted: in this feed they carry
 * listing lifecycle (Active/Pending/Closed), not sale-vs-lease tenure, so they cannot
 * disambiguate a residential rental from a residential sale. PropertyType is the only
 * reliable tenure signal today.
 */
class CriteriaIntentDetector
{
    public const BUYER  = 'buyer';
    public const TENANT = 'tenant';

    /**
     * PropertyType values that only ever denote a sale (→ buyer), lowercased.
     * 'residential' and 'commercial' are excluded because they are tenure-ambiguous.
     */
    private const SALE_ONLY_TYPES = [
        'income',
        'business opportunity',
        'vacant land',
        'land',
        'farm',
    ];

    public function detectFromModel(BridgeProperty $listing): ?string
    {
        return $this->detectFromType($listing->property_type);
    }

    /**
     * @return 'buyer'|'tenant'|null  null = ambiguous / undetectable (caller falls back per F5)
     */
    public function detectFromType(?string $propertyType): ?string
    {
        $pt = strtolower(trim((string) $propertyType));

        if ($pt === '') {
            return null;
        }

        // 'Commercial Lease', 'Residential Lease' → rental.
        if (str_contains($pt, 'lease')) {
            return self::TENANT;
        }

        // 'Commercial Sale' → sale.
        if (str_contains($pt, 'sale')) {
            return self::BUYER;
        }

        if (in_array($pt, self::SALE_ONLY_TYPES, true)) {
            return self::BUYER;
        }

        // Bare 'residential' / 'commercial' — tenure-ambiguous in this feed.
        return null;
    }
}
