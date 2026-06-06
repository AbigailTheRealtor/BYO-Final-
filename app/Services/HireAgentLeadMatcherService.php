<?php

namespace App\Services;

use App\Models\AgentDefaultProfile;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * HireAgentLeadMatcherService
 *
 * Resolves agent matching for hire-agent leads originating from listing pages.
 *
 * Architecture:
 *  — When listing context is available, routing is LISTING-AGENT-FIRST:
 *      1. Resolve the listing's hired_agent_id from the typed auction model.
 *      2. Run AgentDefaultProfile::findForAgentWithFallback() on that agent.
 *      3. target_agent_id is ALWAYS the listing's hired agent (if one exists),
 *         regardless of whether a preset was found.
 *      4. If no hired agent exists, target_agent_id = null.
 *      Global agent search is NOT performed in the listing flow.
 *  — countMatches() uses global search for UI affordance when no listing context
 *    is available (future-extensible entry point).
 */
class HireAgentLeadMatcherService
{
    /** Map source_listing_type → typed Eloquent model class */
    private const MODEL_MAP = [
        'seller_offer'   => SellerAgentAuction::class,
        'buyer_offer'    => BuyerAgentAuction::class,
        'landlord_offer' => LandlordAgentAuction::class,
        'tenant_offer'   => TenantAgentAuction::class,
    ];

    /**
     * Map source_listing_type → listing_type value used in accepted_bid_summaries.
     *
     * accepted_bid_summaries is the canonical accepted-bid source across all four
     * listing types and avoids per-table column-type incompatibilities:
     *  - buyer/seller/tenant bid tables store accepted as varchar('accepted')
     *  - landlord bid table stores accepted as integer (the model ignores it and
     *    uses acceptedBidSummary()->exists() instead)
     */
    private const ACCEPTED_SUMMARY_TYPE_MAP = [
        'seller_offer'   => 'seller',
        'buyer_offer'    => 'buyer',
        'landlord_offer' => 'landlord',
        'tenant_offer'   => 'tenant',
    ];

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Full match result for the store flow.
     *
     * @return array{
     *   match_status: 'no_match'|'matched',
     *   presets: list<array{agent_id:int, preset_id:int, agent_name:string, match_type:string, service_count:int}>,
     *   target_agent_id: int|null,
     *   source_listing_title: string|null,
     *   source_listing_url: string|null,
     *   source_property_type: string|null,
     * }
     */
    public function match(
        string $sourceListingType,
        int    $sourceListingId,
        string $representationType,
        string $selectedPropertyType
    ): array {
        $snapshot     = $this->listingSnapshot($sourceListingType, $sourceListingId);
        $hiredAgentId = $this->getListingHiredAgentId($sourceListingType, $sourceListingId);

        if (! $hiredAgentId) {
            return array_merge($snapshot, [
                'match_status'    => 'no_match',
                'presets'         => [],
                'target_agent_id' => null,
            ]);
        }

        // Listing has a hired agent — try to find a matching preset
        $preset = AgentDefaultProfile::findForAgentWithFallback(
            $hiredAgentId,
            $representationType,
            $selectedPropertyType
        );

        return array_merge($snapshot, [
            // Always route to the listing's hired agent regardless of preset availability
            'target_agent_id' => $hiredAgentId,
            'match_status'    => $preset ? 'matched' : 'no_match',
            'presets'         => $preset
                ? [$this->candidateEntry($hiredAgentId, $preset, $selectedPropertyType)]
                : [],
        ]);
    }

    /**
     * Payload for the public matchPresets AJAX endpoint (called from the modal).
     * Uses listing-agent-first routing when listing context is provided.
     *
     * When a hired agent + matching preset is found, returns:
     *   { action: 'redirect', url: '/hire/agent/direct/{agentId}/{role}/{propertyType}', ... }
     *
     * When no agent exists or the agent has no matching preset, returns:
     *   { action: 'contact_form', reason: 'no_agent'|'no_preset', ... }
     *
     * @return array{action: 'redirect'|'contact_form', url?: string, reason?: string, match_status: string, count: int, presets: list<mixed>}
     */
    public function matchPresetsForAjax(
        string $sourceListingType,
        int    $sourceListingId,
        string $representationType,
        string $selectedPropertyType
    ): array {
        $hiredAgentId = $this->getListingHiredAgentId($sourceListingType, $sourceListingId);

        if (! $hiredAgentId) {
            return [
                'action'       => 'contact_form',
                'reason'       => 'no_agent',
                'match_status' => 'no_match',
                'count'        => 0,
                'presets'      => [],
            ];
        }

        $preset = AgentDefaultProfile::findForAgentWithFallback(
            $hiredAgentId,
            $representationType,
            $selectedPropertyType
        );

        if (! $preset) {
            return [
                'action'       => 'contact_form',
                'reason'       => 'no_preset',
                'match_status' => 'no_match',
                'count'        => 0,
                'presets'      => [],
            ];
        }

        try {
            $redirectUrl = route('hire.agent.direct.preview', [
                'agentId'      => $hiredAgentId,
                'role'         => $representationType,
                'propertyType' => $selectedPropertyType,
            ]);
        } catch (\Throwable $e) {
            return [
                'action'       => 'contact_form',
                'reason'       => 'no_preset',
                'match_status' => 'no_match',
                'count'        => 0,
                'presets'      => [],
            ];
        }

        $entry = $this->candidateEntry($hiredAgentId, $preset, $selectedPropertyType);

        return [
            'action'       => 'redirect',
            'url'          => $redirectUrl,
            'match_status' => 'matched',
            'count'        => 1,
            'presets'      => [$entry],
        ];
    }

    /**
     * Count matching agents globally (no listing context).
     * Used by matchPresets AJAX when listing id/type are not provided.
     */
    public function countMatches(string $representationType, string $selectedPropertyType): int
    {
        $agentIds = AgentDefaultProfile::where('role_type', $representationType)
            ->whereIn('property_type', [$selectedPropertyType, AgentDefaultProfile::ROLE_DEFAULT])
            ->whereNotNull('profile_data')
            ->pluck('user_id')
            ->unique();

        $count = 0;
        foreach ($agentIds as $agentId) {
            if (AgentDefaultProfile::findForAgentWithFallback($agentId, $representationType, $selectedPropertyType)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Generate the public URL for a listing.
     * Static so HireAgentLead model can call it directly.
     */
    public static function listingUrl(string $sourceListingType, int $sourceListingId): ?string
    {
        $routeMap = [
            'seller_offer'   => 'offer.listing.seller.view',
            'buyer_offer'    => 'offer.listing.buyer.view',
            'landlord_offer' => 'offer.listing.landlord.view',
            'tenant_offer'   => 'offer.listing.tenant.view',
        ];
        $routeName = $routeMap[$sourceListingType] ?? null;
        if (! $routeName) {
            return null;
        }
        try {
            return route($routeName, ['id' => $sourceListingId]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Resolve the agent ID associated with this listing.
     *
     * Checks candidates in strict priority order, stopping at the first value
     * that resolves to a user with user_type = 'agent':
     *
     *  1. Accepted/winning bid record — query the listing's bid table for a
     *     row with accepted = 'accepted'. This is the authoritative source
     *     and matches what bid detail views display via $bid->user->short_id.
     *  2. hired_agent_id    (EAV meta)
     *  3. accepted_agent_id (EAV meta)
     *  4. winning_agent_id  (EAV meta)
     *  5. selected_agent_id (EAV meta)
     *  6. listing_agent_id  (EAV meta)
     *  7. agent_id          (EAV meta)
     *  8. created_by        (EAV meta)
     *  9. listing.user_id   (native column — covers agent-created listings,
     *     only when that user has user_type = 'agent')
     *
     * Returns null if no agent can be resolved.
     */
    private function getListingHiredAgentId(string $sourceListingType, int $listingId): ?int
    {
        $modelClass = self::MODEL_MAP[$sourceListingType] ?? null;
        if (! $modelClass) {
            return null;
        }
        try {
            $listing = $modelClass::with('meta')->find($listingId);
            if (! $listing) {
                return null;
            }

            // Step 1: accepted bid record — highest priority, matches bid detail views
            $acceptedBidAgentId = $this->getAcceptedBidAgentId($sourceListingType, $listingId);
            if ($acceptedBidAgentId) {
                return $acceptedBidAgentId;
            }

            // Steps 2–8: EAV meta keys in priority order
            $metaKeys = [
                'hired_agent_id',
                'accepted_agent_id',
                'winning_agent_id',
                'selected_agent_id',
                'listing_agent_id',
                'agent_id',
                'created_by',
            ];
            foreach ($metaKeys as $key) {
                $val = $listing->info($key);
                if ($val) {
                    $candidateId = (int) $val;
                    if ($candidateId > 0 && $this->isAgentUser($candidateId)) {
                        return $candidateId;
                    }
                }
            }

            // Step 9: listing.user_id — covers listings created directly by an agent
            $listingOwnerId = $listing->user_id ?? null;
            if ($listingOwnerId && $this->isAgentUser((int) $listingOwnerId)) {
                return (int) $listingOwnerId;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Look up the accepted bid's agent via the accepted_bid_summaries table.
     *
     * accepted_bid_summaries is the canonical accepted-bid source used by every
     * per-role AcceptedBidSummaryService and by the model's getBidStatusAttribute()
     * (e.g. LandlordAgentAuctionBid uses acceptedBidSummary()->exists()). Querying
     * this table instead of the raw bid table avoids column-type incompatibilities
     * (landlord stores accepted as integer; buyer/seller/tenant as varchar).
     *
     * agent_user_id is stored directly on the summary row, so no secondary lookup
     * into the bid table itself is required.
     *
     * Uses DB::table() directly to avoid Eloquent eager-load issues inside
     * transactions and to keep the query lightweight.
     *
     * Returns null when no mapping exists, no accepted summary is found for the
     * listing, or the resolved user is not an agent.
     */
    private function getAcceptedBidAgentId(string $sourceListingType, int $listingId): ?int
    {
        $summaryListingType = self::ACCEPTED_SUMMARY_TYPE_MAP[$sourceListingType] ?? null;
        if (! $summaryListingType) {
            return null;
        }

        try {
            $agentUserId = DB::table('accepted_bid_summaries')
                ->where('listing_type', $summaryListingType)
                ->where('listing_id', $listingId)
                ->whereNotNull('agent_user_id')
                ->orderByDesc('id')
                ->value('agent_user_id');

            if (! $agentUserId) {
                return null;
            }

            $candidateId = (int) $agentUserId;
            if ($candidateId > 0 && $this->isAgentUser($candidateId)) {
                return $candidateId;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check whether a given user ID belongs to a user with user_type = 'agent'.
     * Uses DB::table() directly to avoid Eloquent eager-load issues inside transactions.
     */
    private function isAgentUser(int $userId): bool
    {
        try {
            $type = DB::table('users')->where('id', $userId)->value('user_type');
            return $type === 'agent';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Build a single candidate entry for the response payload.
     *
     * @return array{agent_id:int, preset_id:int, agent_name:string, match_type:string, service_count:int, preset_name:string}
     */
    private function candidateEntry(int $agentId, AgentDefaultProfile $preset, string $selectedPropertyType): array
    {
        $agentName = '';
        try {
            $user      = User::select('id', 'user_name', 'first_name', 'last_name')->find($agentId);
            $agentName = $user?->user_name
                ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));
        } catch (\Throwable $e) {
        }

        $matchType    = $preset->property_type === $selectedPropertyType ? 'exact' : 'role_default';
        $services     = $preset->getData('services') ?? [];
        $serviceCount = is_array($services) ? count($services) : 0;

        // Human-readable preset name for notification payload
        $presetName = trim(
            ($preset->getData('title') ?? '')
            ?: ucfirst(str_replace(['_', '-'], ' ', $preset->role_type ?? ''))
               . (($preset->property_type && $preset->property_type !== AgentDefaultProfile::ROLE_DEFAULT)
                  ? ' – ' . ucfirst(str_replace(['_', '-'], ' ', $preset->property_type))
                  : ' (default)')
        );

        return [
            'agent_id'      => $agentId,
            'preset_id'     => $preset->id,
            'agent_name'    => $agentName,
            'preset_name'   => $presetName,
            'match_type'    => $matchType,
            'service_count' => $serviceCount,
        ];
    }

    /**
     * Build denormalised listing snapshot (source_listing_title, source_listing_url, source_property_type)
     * using the correct typed model for the given source_listing_type.
     *
     * @return array{source_listing_title:string|null, source_listing_url:string|null, source_property_type:string|null}
     */
    private function listingSnapshot(string $sourceListingType, int $sourceListingId): array
    {
        $url      = self::listingUrl($sourceListingType, $sourceListingId);
        $defaults = [
            'source_listing_title' => null,
            'source_listing_url'   => $url,
            'source_property_type' => null,
        ];

        $modelClass = self::MODEL_MAP[$sourceListingType] ?? null;
        if (! $modelClass) {
            return $defaults;
        }

        try {
            $listing = $modelClass::with('meta')->find($sourceListingId);
            if (! $listing) {
                return $defaults;
            }

            $address  = $listing->info('property_address') ?? '';
            $city     = $listing->info('property_city') ?? $listing->info('city') ?? '';
            $title    = trim(implode(', ', array_filter([$address, $city])));
            if (! $title) {
                $listingTitle = $listing->info('listing_title') ?? $listing->title ?? null;
                $title        = $listingTitle ?: (ucfirst(str_replace('_offer', '', $sourceListingType)) . ' Listing #' . $sourceListingId);
            }

            $propType = $listing->info('property_type');

            return [
                'source_listing_title' => $title,
                'source_listing_url'   => $url,
                'source_property_type' => is_array($propType) ? ($propType[0] ?? null) : $propType,
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }
}
