<?php

namespace App\Services\Dna\Relevance;

use Illuminate\Support\Facades\DB;

/**
 * OnPlatformCandidateAttributeResolver — Matching V2 consumption slice 2B.
 *
 * The one shipped resolver. It reads the on-platform *_agent auction tables to
 * produce provider-neutral CandidateAttributeProfile objects for the current
 * (dna_scores) candidate universe, addressed by (listing_type, listing_id) where
 * listing_id is the *_agent_auctions primary key.
 *
 * GOVERNANCE: PURE READ-ONLY — only SELECTs. BATCHED — a fixed, small number of
 * queries per distinct listing_type (meta, lifecycle, geo), never per candidate.
 * Query count is O(number of listing_types), not O(candidates).
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §6
 */
class OnPlatformCandidateAttributeResolver implements CandidateAttributeResolverInterface
{
    /**
     * The on-platform table topology per listing_type. property_location_dna shares
     * the same (listing_type, listing_id) vocabulary, so geo joins directly.
     */
    private const TYPES = [
        'seller_agent'   => ['table' => 'seller_agent_auctions',   'meta' => 'seller_agent_auction_metas',   'fk' => 'seller_agent_auction_id'],
        'landlord_agent' => ['table' => 'landlord_agent_auctions', 'meta' => 'landlord_agent_auction_metas', 'fk' => 'landlord_agent_auction_id'],
        'buyer_agent'    => ['table' => 'buyer_agent_auctions',    'meta' => 'buyer_agent_auction_metas',    'fk' => 'buyer_agent_auction_id'],
        'tenant_agent'   => ['table' => 'tenant_agent_auctions',   'meta' => 'tenant_agent_auction_metas',   'fk' => 'tenant_agent_auction_id'],
    ];

    private const META_KEYS = ['leasing_55_plus', 'property_type', 'workflow_type'];

    public function resolveMany(string $side, array $tuples): array
    {
        // Group listing_ids by listing_type so each type is queried once.
        $idsByType = [];
        foreach ($tuples as $tuple) {
            $type = (string) $tuple['listing_type'];
            $id   = (int) $tuple['listing_id'];
            $idsByType[$type][] = $id;
        }

        $profiles = [];

        foreach ($idsByType as $type => $ids) {
            $ids  = array_values(array_unique($ids));
            $meta = self::TYPES[$type] ?? null;

            if ($meta === null) {
                // Unknown/foreign type reaching the on-platform resolver: cannot verify
                // eligibility → mark ineligible (the eligibility gate drops it), senior
                // unknown (fail-open). A future provider gets its own resolver.
                foreach ($ids as $id) {
                    $profiles[CandidateAttributeProfile::key($type, $id)] =
                        new CandidateAttributeProfile($type, $id, $side, false, null, null, null, null, null, null, null);
                }
                continue;
            }

            $metaByIdKey = $this->loadMeta($meta['meta'], $meta['fk'], $ids);
            $lifecycle   = $this->loadLifecycle($meta['table'], $ids);
            $geo         = $this->loadGeo($type, $ids);

            foreach ($ids as $id) {
                $km = $metaByIdKey[$id] ?? [];
                $lc = $lifecycle[$id] ?? null;
                $gp = $geo[$id] ?? null;

                $approved = $lc !== null && $this->truthy($lc->is_approved);
                $sold     = $lc !== null && $this->truthy($lc->is_sold);
                $isOfferListing = ($km['workflow_type'] ?? null) === 'offer_listing';

                $eligible = $lc !== null && $approved && ! $sold && $isOfferListing;

                $profiles[CandidateAttributeProfile::key($type, $id)] = new CandidateAttributeProfile(
                    listingType: $type,
                    listingId: $id,
                    side: $side,
                    isEligibleListing: $eligible,
                    age55: $this->tristate($km['leasing_55_plus'] ?? null),
                    propertyType: isset($km['property_type']) && $km['property_type'] !== '' ? (string) $km['property_type'] : null,
                    lat: $gp !== null ? $this->floatOrNull($gp->geocoded_lat) : null,
                    lng: $gp !== null ? $this->floatOrNull($gp->geocoded_lng) : null,
                    city: $gp !== null ? $this->stringOrNull($gp->source_city) : null,
                    zip: $gp !== null ? $this->stringOrNull($gp->source_zip) : null,
                    county: $gp !== null ? $this->stringOrNull($gp->source_county) : null,
                );
            }
        }

        return $profiles;
    }

    /**
     * @param int[] $ids
     * @return array<int,array<string,string|null>> [$id][$metaKey] => $metaValue
     */
    private function loadMeta(string $metaTable, string $fk, array $ids): array
    {
        $rows = DB::table($metaTable)
            ->whereIn($fk, $ids)
            ->whereIn('meta_key', self::META_KEYS)
            ->get([$fk, 'meta_key', 'meta_value']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->{$fk}][(string) $row->meta_key] = $row->meta_value;
        }

        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int,object>
     */
    private function loadLifecycle(string $table, array $ids): array
    {
        $rows = DB::table($table)
            ->whereIn('id', $ids)
            ->get(['id', 'is_approved', 'is_sold']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = $row;
        }

        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int,object>
     */
    private function loadGeo(string $listingType, array $ids): array
    {
        $rows = DB::table('property_location_dna')
            ->where('listing_type', $listingType)
            ->whereIn('listing_id', $ids)
            ->get(['listing_id', 'geocoded_lat', 'geocoded_lng', 'source_city', 'source_zip', 'source_county']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->listing_id] = $row;
        }

        return $out;
    }

    /** Normalize a leasing_55_plus meta value to a tristate: true / false / null(unknown). */
    private function tristate($value): ?bool
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $lower = strtolower(trim((string) $value));
        if (in_array($lower, ['yes', '1', 'true'], true)) {
            return true;
        }
        if (in_array($lower, ['no', '0', 'false'], true)) {
            return false;
        }
        return null;
    }

    private function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }

    private function floatOrNull($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }
}
