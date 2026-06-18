<?php

namespace App\Services\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves which Buyer Criteria and Tenant Criteria listings a user may access
 * on the Stellar results page.
 *
 * Access rule:
 *  - Any authenticated user sees their own active criteria records.
 *  - Agents additionally see active criteria owned by their buyer clients.
 *    "Client" is defined as any user with a user_agents row where agent_id = agent.id.
 *    This is the same relationship used by ShowingPolicy for granting agents access to
 *    their clients' listing views — it is the canonical platform agent-client link.
 *
 * Returned items have shape:
 *   ['id' => int, 'type' => 'buyer'|'tenant', 'label' => string, 'created_at' => Carbon]
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
     * Return all accessible criteria listings for $user, sorted newest-first.
     *
     * @return array<int, array{id: int, type: string, label: string, created_at: \Carbon\Carbon}>
     */
    public function resolveAccessible(User $user): array
    {
        $allowedUserIds = $this->resolveAllowedUserIds($user);
        $items = [];

        // -----------------------------------------------------------------------
        // Buyer Criteria Auctions
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
        // Tenant Criteria Auctions
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

        usort($items, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $items;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildBuyerLabel(BuyerCriteriaAuction $record): string
    {
        $beds = $record->bedrooms ? "{$record->bedrooms}BR " : '';

        // Prefer the first preferred city as location context (e.g. "3BR Tampa").
        // Falls back to the user-supplied title, then the record ID.
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

        // Prefer the first preferred city as location context (e.g. "2BR Miami").
        // Falls back to the user-supplied titleListing, then the record ID.
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
