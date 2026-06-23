<?php

namespace App\Services\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves which Buyer Criteria, Tenant Criteria, and modern Offer Listing records
 * a user may access on the Stellar results page.
 *
 * Access rule:
 *  - Any authenticated user sees their own active criteria / offer listing records.
 *  - Agents additionally see active records owned by their buyer clients.
 *    "Client" is defined as any user with a user_agents row where agent_id = agent.id.
 *
 * Modern offer listing types are returned alongside legacy criteria types.
 * Legacy records continue to work during the transition period.
 *
 * Returned items have shape:
 *   ['id' => int, 'type' => 'buyer'|'tenant'|'buyer_offer'|'tenant_offer',
 *    'label' => string, 'created_at' => Carbon]
 *
 * Type tokens:
 *   'buyer'        — legacy BuyerCriteriaAuction record
 *   'tenant'       — legacy TenantCriteriaAuction record
 *   'buyer_offer'  — modern BuyerAgentAuction offer listing (workflow_type='offer_listing')
 *   'tenant_offer' — modern TenantAgentAuction offer listing (workflow_type='offer_listing')
 */
class CriteriaListingResolver
{
    /**
     * Return all user IDs whose criteria this user may access.
     * For agents, includes their own ID plus all client IDs from user_agents.
     * For non-agents, returns only their own ID.
     *
     * @return int[]
     */
    public function resolveAllowedUserIds(User $user): array
    {
        $ids = [$user->id];

        if ($user->user_type === 'agent' && Schema::hasTable('user_agents')) {
            $clientIds = DB::table('user_agents')
                ->where('agent_id', $user->id)
                ->pluck('user_id')
                ->toArray();

            $ids = array_values(array_unique(array_merge($ids, $clientIds)));
        }

        return $ids;
    }

    /**
     * Return all accessible criteria / offer-listing records for $user, sorted newest-first.
     *
     * Includes both legacy criteria records (type='buyer'/'tenant') and modern offer-listing
     * records (type='buyer_offer'/'tenant_offer'). Legacy support is preserved during
     * the transition period — both sources are merged and surfaced in the criteria switcher.
     *
     * @return array<int, array{id: int, type: string, label: string, created_at: \Carbon\Carbon}>
     */
    public function resolveAccessible(User $user): array
    {
        $allowedUserIds = $this->resolveAllowedUserIds($user);
        $items = [];

        // -----------------------------------------------------------------------
        // Legacy: Buyer Criteria Auctions
        // -----------------------------------------------------------------------
        if (Schema::hasTable('buyer_criteria_auctions')) {
            $buyerRecords = BuyerCriteriaAuction::whereIn('user_id', $allowedUserIds)
                ->where('is_approved', true)
                ->where('is_sold', false)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($buyerRecords as $record) {
                $items[] = [
                    'id'         => $record->id,
                    'type'       => 'buyer',
                    'label'      => $this->buildBuyerLabel($record),
                    'created_at' => $record->created_at,
                ];
            }
        }

        // -----------------------------------------------------------------------
        // Legacy: Tenant Criteria Auctions
        // -----------------------------------------------------------------------
        if (Schema::hasTable('tenant_criteria_auctions')) {
            $tenantRecords = TenantCriteriaAuction::whereIn('user_id', $allowedUserIds)
                ->where('is_approved', true)
                ->where('is_sold', false)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($tenantRecords as $record) {
                $items[] = [
                    'id'         => $record->id,
                    'type'       => 'tenant',
                    'label'      => $this->buildTenantLabel($record),
                    'created_at' => $record->created_at,
                ];
            }
        }

        // -----------------------------------------------------------------------
        // Modern: Buyer Offer Listing records (buyer_agent_auctions)
        // -----------------------------------------------------------------------
        if (Schema::hasTable('buyer_agent_auctions') && Schema::hasTable('buyer_agent_auction_metas')) {
            $offerListingIds = DB::table('buyer_agent_auction_metas')
                ->where('meta_key', 'workflow_type')
                ->where('meta_value', 'offer_listing')
                ->pluck('buyer_agent_auction_id');

            if ($offerListingIds->isNotEmpty()) {
                $buyerOfferRecords = BuyerAgentAuction::whereIn('id', $offerListingIds)
                    ->whereIn('user_id', $allowedUserIds)
                    ->where('is_approved', true)
                    ->where('is_sold', false)
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($buyerOfferRecords as $record) {
                    $items[] = [
                        'id'         => $record->id,
                        'type'       => 'buyer_offer',
                        'label'      => $this->buildBuyerOfferLabel($record),
                        'created_at' => $record->created_at,
                    ];
                }
            }
        }

        // -----------------------------------------------------------------------
        // Modern: Tenant Offer Listing records (tenant_agent_auctions)
        // -----------------------------------------------------------------------
        if (Schema::hasTable('tenant_agent_auctions') && Schema::hasTable('tenant_agent_auction_metas')) {
            $offerListingIds = DB::table('tenant_agent_auction_metas')
                ->where('meta_key', 'workflow_type')
                ->where('meta_value', 'offer_listing')
                ->pluck('tenant_agent_auction_id');

            if ($offerListingIds->isNotEmpty()) {
                $tenantOfferRecords = TenantAgentAuction::whereIn('id', $offerListingIds)
                    ->whereIn('user_id', $allowedUserIds)
                    ->where('is_approved', true)
                    ->where('is_sold', false)
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($tenantOfferRecords as $record) {
                    $items[] = [
                        'id'         => $record->id,
                        'type'       => 'tenant_offer',
                        'label'      => $this->buildTenantOfferLabel($record),
                        'created_at' => $record->created_at,
                    ];
                }
            }
        }

        usort($items, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $items;
    }

    // =========================================================================
    // Private label builders
    // =========================================================================

    private function buildBuyerLabel(BuyerCriteriaAuction $record): string
    {
        $beds = $record->bedrooms ? "{$record->bedrooms}BR " : '';

        $location = $this->firstCity($record->info('preferred_cities'));
        if ($location !== null) {
            return "Buyer Criteria – {$beds}{$location}";
        }

        $name = ($record->title && trim($record->title) !== '')
            ? trim($record->title)
            : "#{$record->id}";

        return "Buyer Criteria – {$beds}{$name}";
    }

    private function buildTenantLabel(TenantCriteriaAuction $record): string
    {
        $bedsRaw = $record->info('bedrooms');
        $bedStr  = ($bedsRaw && is_numeric($bedsRaw)) ? "{$bedsRaw}BR " : '';

        $location = $this->firstCity($record->info('cities'));
        if ($location !== null) {
            return "Tenant Criteria – {$bedStr}{$location}";
        }

        $titleListing = $record->info('titleListing') ?: null;
        $name         = ($titleListing && trim($titleListing) !== '')
            ? trim($titleListing)
            : "#{$record->id}";

        return "Tenant Criteria – {$bedStr}{$name}";
    }

    private function buildBuyerOfferLabel(BuyerAgentAuction $record): string
    {
        $bedsRaw = $record->info('bedrooms');
        $bedStr  = ($bedsRaw && is_numeric($bedsRaw)) ? "{$bedsRaw}BR " : '';

        $propertyType = $record->info('property_type') ?: 'Buyer';

        $location = $this->firstCityFromLdnaOrMeta($record);
        if ($location !== null) {
            return "Buyer Offer – {$bedStr}{$location} ({$propertyType})";
        }

        $countiesRaw = $record->info('counties');
        if ($countiesRaw) {
            $counties = is_array($countiesRaw) ? $countiesRaw : json_decode($countiesRaw, true);
            if (is_array($counties) && !empty($counties)) {
                return "Buyer Offer – {$bedStr}{$counties[0]} ({$propertyType})";
            }
        }

        return "Buyer Offer – {$bedStr}#{$record->id} ({$propertyType})";
    }

    private function buildTenantOfferLabel(TenantAgentAuction $record): string
    {
        $bedsRaw = $record->info('bedrooms');
        $bedStr  = ($bedsRaw && is_numeric($bedsRaw)) ? "{$bedsRaw}BR " : '';

        $propertyType = $record->info('property_type') ?: 'Tenant';

        $location = $this->firstCityFromLdnaOrMetaTenant($record);
        if ($location !== null) {
            return "Tenant Offer – {$bedStr}{$location} ({$propertyType})";
        }

        $countiesRaw = $record->info('counties');
        if ($countiesRaw) {
            $counties = is_array($countiesRaw) ? $countiesRaw : json_decode($countiesRaw, true);
            if (is_array($counties) && !empty($counties)) {
                return "Tenant Offer – {$bedStr}{$counties[0]} ({$propertyType})";
            }
        }

        return "Tenant Offer – {$bedStr}#{$record->id} ({$propertyType})";
    }

    // =========================================================================
    // Private location helpers
    // =========================================================================

    /**
     * Extract first city from a BuyerAgentAuction's LDNA blob or fallback meta keys.
     */
    private function firstCityFromLdnaOrMeta(BuyerAgentAuction $record): ?string
    {
        $ldnaRaw = $record->info('location_dna_preferences');
        if ($ldnaRaw) {
            $ldna = is_array($ldnaRaw) ? $ldnaRaw : (json_decode($ldnaRaw, true) ?? []);
            if (!empty($ldna['cities'])) {
                return $this->firstCity($ldna['cities']);
            }
        }
        return $this->firstCity($record->info('preferred_cities'));
    }

    /**
     * Extract first city from a TenantAgentAuction's LDNA blob or fallback meta keys.
     */
    private function firstCityFromLdnaOrMetaTenant(TenantAgentAuction $record): ?string
    {
        $ldnaRaw = $record->info('location_dna_preferences');
        if ($ldnaRaw) {
            $ldna = is_array($ldnaRaw) ? $ldnaRaw : (json_decode($ldnaRaw, true) ?? []);
            if (!empty($ldna['cities'])) {
                return $this->firstCity($ldna['cities']);
            }
        }
        return $this->firstCity($record->info('cities'));
    }

    /**
     * Extract the first non-empty city string from a meta value.
     * Handles already-decoded arrays, JSON strings, and scalar strings.
     * Returns null when no usable city is found.
     */
    private function firstCity(mixed $raw): ?string
    {
        if ($raw === null || $raw === false || $raw === '') {
            return null;
        }

        $cities = null;

        if (is_array($raw)) {
            $cities = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $cities  = is_array($decoded) ? $decoded : [$raw];
        }

        if (!is_array($cities)) {
            return null;
        }

        foreach ($cities as $city) {
            if (is_string($city) && trim($city) !== '') {
                return trim($city);
            }
        }

        return null;
    }
}
