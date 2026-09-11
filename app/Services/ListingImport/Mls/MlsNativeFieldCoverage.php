<?php

namespace App\Services\ListingImport\Mls;

use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\ListingImport\MlsListingPrefillService;
use App\Services\ListingImport\Sync\MlsFactProjection;

/**
 * Which Bridge fields a Quick Import writes COMPLETELY into a role's own listing
 * fields — and therefore the only Tier-1 fields MLS Property Details may decline
 * to repeat.
 *
 * WHAT THIS FIXES
 * ---------------
 * MLS Property Details used to omit every Tier-1 field whose canonical key had
 * a target in MlsFieldMap. Having a target is not being written. The
 * 2026-09-11 field-completeness audit traced the difference on the 1,203 real
 * cached Stellar records:
 *
 *   · `BuildingAreaTotal` — no landlord target and no MLS Details row at all:
 *     lost on 415 Residential Lease imports;
 *   · `BusinessType` — a single-select holds the first recognised value, and
 *     the row holding the rest was hidden: lost on 74 imports;
 *   · `LivingAreaSource = Estimated` — no such option on the form, so nothing
 *     was written, and the fact had no row either: lost on 11;
 *   · pool / garage / carport — rightly not written into a control the
 *     property type's form never renders, and then hidden from MLS Details
 *     anyway, so a true value vanished at both layers.
 *
 * NO SECOND MAPPING
 * -----------------
 * The answer comes from the real import pipeline, run on the record without
 * persisting anything: BridgePropertyCandidateAdapter (the one class that knows
 * the Bridge shape), MlsListingPrefillService (the facts-only allow-list) and
 * MlsFactProjection (the one mapping import and sync share). When any of them
 * changes what an import writes, this answer changes with it.
 */
final class MlsNativeFieldCoverage
{
    /**
     * @param  array<string,mixed>  $raw   a decoded Bridge Property record
     * @param  string               $role  'seller' | 'landlord'
     * @return array<string,true>          Bridge field => true
     */
    public static function completelyWritten(array $raw, string $role): array
    {
        $prefill = (new MlsListingPrefillService())->fromCandidate(
            (new BridgePropertyCandidateAdapter())->fromRecord($raw)
        );

        if (! $prefill['success']) {
            return [];
        }

        $written = (new MlsFactProjection())->completelyWrittenKeys(
            $role,
            $prefill['data'],
            is_scalar($raw['PropertyType'] ?? null) ? (string) $raw['PropertyType'] : null,
        );

        $out = [];

        foreach (MlsFieldCatalog::TIER1_BYO as $field => $canonicalKey) {
            if (isset($written[$canonicalKey])) {
                $out[$field] = true;
            }
        }

        return $out;
    }
}
