<?php

namespace App\Services\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Support\Listing\ListingFlag;
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
 * ONLY OFFER-LISTING CRITERIA ARE SELECTABLE. The two legacy criteria types are
 * deliberately NOT returned — see LEGACY_TYPES below. No record is deleted, no
 * data is migrated, and the legacy view routes are untouched; they are simply
 * not offered as something that can be matched, because they cannot be.
 *
 * Returned items have shape:
 *   ['id' => int, 'type' => 'buyer_offer'|'tenant_offer',
 *    'label' => string, 'created_at' => Carbon]
 *
 * Type tokens:
 *   'buyer_offer'  — BuyerAgentAuction offer listing (workflow_type='offer_listing')
 *   'tenant_offer' — TenantAgentAuction offer listing (workflow_type='offer_listing')
 */
class CriteriaListingResolver
{
    /**
     * The legacy criteria type tokens, retired from live selection.
     *
     * WHY THEY ARE RETIRED RATHER THAN REPAIRED
     * -----------------------------------------
     * Neither legacy flow can produce a correct match, and neither can be created
     * or edited any more — the evidence is in the repository, not in a judgement
     * call:
     *
     *   · Every legacy criteria WRITE surface is already dead. All four Blade
     *     views (buyer_criteria/add, buyer_criteria/edit, tenant_criteria/add,
     *     tenant_criteria/edit) post to route names that are defined NOWHERE in
     *     routes/ — 'buyer_agent.auction.add', 'buyer_agent.auction.update',
     *     'agent.tenant.criteria.auction.add', 'agent.tenant.criteria.auction.edit'.
     *     Laravel's route() helper throws RouteNotFoundException for an undefined
     *     name, and each call sits unconditionally in the form action, so every
     *     one of those pages 500s on render. No new legacy record can be created
     *     and no existing one can be edited.
     *
     *   · StellarBuyerResultsController already points its "add criteria" links at
     *     /offer-listing/buyer and /offer-listing/tenant/tenant — the modern flows.
     *
     *   · BuyerCriteriaLoader returns null for every record the legacy controller
     *     ever wrote: property_types is never written and property_type_id is never
     *     assigned, so the required-property-types guard rejects the record. Only 5
     *     of the 32 criteria keys that loader reads are written by the form at all.
     *
     *   · TenantCriteriaLoader resolves rental seekers to PropertyType 'Residential'
     *     — the FOR-SALE type, as TenantOfferListingCriteriaLoader documents — or to
     *     'Commercial', which is not a Bridge PropertyType value at all, and then
     *     discards the monthly budget. It does not fail; it returns confident,
     *     wrong results.
     *
     * Repairing the key vocabulary would mean inventing a storage contract for
     * forms that cannot run. Offering an un-matchable record in the switcher is
     * the user-visible harm, so that is what is removed.
     *
     * NOTHING IS DELETED. The tables, the rows, the models, the loaders and the
     * public /criteria/view and /tenant/criteria/auction/view routes all stay
     * exactly as they are. A product decision to revive either flow restores one
     * entry here — after the write surfaces and the loader contract are repaired.
     *
     * @var list<string>
     */
    public const LEGACY_TYPES = ['buyer', 'tenant'];

    /** Is this criteria type retired from live matching selection? */
    public static function isRetiredLegacyType(string $type): bool
    {
        return in_array($type, self::LEGACY_TYPES, true);
    }

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
     * Offer-listing records only (type='buyer_offer'/'tenant_offer'). The legacy
     * criteria types are retired from selection — see self::LEGACY_TYPES.
     *
     * @return array<int, array{id: int, type: string, label: string, created_at: \Carbon\Carbon}>
     */
    public function resolveAccessible(User $user): array
    {
        $allowedUserIds = $this->resolveAllowedUserIds($user);
        $items = [];

        // Legacy 'buyer' / 'tenant' criteria records are deliberately NOT collected.
        // They cannot produce a correct match and can no longer be created or
        // edited; see self::LEGACY_TYPES for the evidence. Their rows are left
        // untouched in the database.

        // -----------------------------------------------------------------------
        // Modern: Buyer Offer Listing records (buyer_agent_auctions)
        // -----------------------------------------------------------------------
        if (Schema::hasTable('buyer_agent_auctions') && Schema::hasTable('buyer_agent_auction_metas')) {
            $offerListingIds = DB::table('buyer_agent_auction_metas')
                ->where('meta_key', 'workflow_type')
                ->where('meta_value', 'offer_listing')
                ->pluck('buyer_agent_auction_id');

            if ($offerListingIds->isNotEmpty()) {
                $buyerOfferQuery = BuyerAgentAuction::whereIn('id', $offerListingIds)
                    ->whereIn('user_id', $allowedUserIds);

                // buyer_agent_auctions' flags are varchar and the wizard publishes
                // 'true' / 'false'; ListingFlag reads every stored form, as the model does.
                ListingFlag::whereTrue($buyerOfferQuery, 'is_approved');
                ListingFlag::whereNotTrue($buyerOfferQuery, 'is_sold');

                $buyerOfferRecords = $buyerOfferQuery->orderBy('created_at', 'desc')->get();

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

    /**
     * Resolve the single "preferred" accessible criteria/offer-listing record to
     * auto-select for Match Check (Phase 4 · F5), or null when none should be auto-picked.
     *
     * Rules:
     *  - Agents (and other power users with type 'agent') NEVER get an auto-default —
     *    they must choose explicitly, so this returns null for them (F5).
     *  - For a consumer, returns the newest accessible record; when $intent is given
     *    ('buyer' or 'tenant'), only records of that side are considered, so a rental
     *    property auto-selects Tenant criteria and a sale auto-selects Buyer criteria.
     *  - Returns null when the consumer has no matching record (the caller then shows an
     *    empty-state prompting them to create the right criteria — never a wrong-engine score).
     *
     * Reuses resolveAccessible(), which is already sorted newest-first and access-scoped.
     *
     * @param  string|null  $intent  'buyer' | 'tenant' | null (no side filter)
     * @return array{id: int, type: string, label: string, created_at: \Carbon\Carbon}|null
     */
    public function resolvePreferred(User $user, ?string $intent = null): ?array
    {
        if ($user->user_type === 'agent') {
            return null;
        }

        $items = $this->resolveAccessible($user);

        if ($intent !== null) {
            $wanted = $intent === 'tenant'
                ? ['tenant', 'tenant_offer']
                : ['buyer', 'buyer_offer'];

            $items = array_values(array_filter(
                $items,
                fn(array $item) => in_array($item['type'], $wanted, true)
            ));
        }

        return $items[0] ?? null;
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
