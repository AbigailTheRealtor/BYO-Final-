<?php

namespace App\Services\AskAi;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\LandlordAuction;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyAuction;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Models\TenantCriteriaAuction;
use App\Services\Dna\PropertyIntelligenceProfileService;

/**
 * AskAiContextBuilderService — Phase 1 Read-Only Context Assembly
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Read-only context assembly layer for Ask AI (Phase 1).
 * Gathers approved structured data from existing intelligence models into a single
 * safe context object. This is the foundation that future Ask AI phases call before
 * generating any response.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Recalculate, modify, or override any DNA score, compatibility rating,
 *     avatar value, or offer analysis result.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 *   - Call PropertyIntelligenceProfileService::generate() — use buildPayloadReadOnly()
 *     instead; generate() persists location_intelligence_context as a side-effect.
 * ==================================================================================
 */
class AskAiContextBuilderService
{
    public const CONTEXT_VERSION = 'ASK_AI_CONTEXT_V1';

    private PropertyIntelligenceProfileService $propertyIntelligenceService;

    public function __construct(PropertyIntelligenceProfileService $propertyIntelligenceService)
    {
        $this->propertyIntelligenceService = $propertyIntelligenceService;
    }

    /**
     * Canonical listing type names and all accepted aliases.
     */
    private const TYPE_ALIASES = [
        'seller'                   => 'seller',
        'seller_agent_auction'     => 'seller',
        'property_auction'         => 'seller',
        'buyer'                    => 'buyer',
        'buyer_agent_auction'      => 'buyer',
        'buyer_criteria_auction'   => 'buyer',
        'landlord'                 => 'landlord',
        'landlord_agent_auction'   => 'landlord',
        'landlord_auction'         => 'landlord',
        'tenant'                   => 'tenant',
        'tenant_agent_auction'     => 'tenant',
        'tenant_criteria_auction'  => 'tenant',
    ];

    /**
     * Assemble a read-only Ask AI context object for the given listing.
     *
     * Output contract — always returns exactly these top-level keys:
     *   success               bool         — true when listing was found and assembly ran; false otherwise
     *   listing_type          string       — canonical or aliased type as supplied by the caller
     *   listing_id            int          — listing primary key as supplied by the caller
     *   context_version       string       — always 'ASK_AI_CONTEXT_V1'
     *   status                string       — 'assembled' | 'partial' | 'not_found' | 'failed'
     *   listing               array|null
     *   property_intelligence array|null
     *   location_intelligence array|null
     *   buyer_avatar          array|null
     *   tenant_avatar         array|null
     *   compatibility         array|null
     *   offer_analysis        array|null
     *   missing_sources       string[]
     *   warnings              string[]
     *   source_versions       array
     *   assembled_at          string       — ISO-8601 metadata timestamp; represents when this
     *                                        context object was assembled, NOT AI generation time,
     *                                        score computation time, or listing update time.
     *   error                 string|null  — null on non-failed paths; error message on 'failed'
     *
     * @param  string      $listingType  Canonical or aliased listing type string.
     * @param  int         $listingId    Primary key of the listing record.
     * @param  array|null  $options      Optional: demand_listing_type/demand_listing_id or
     *                                  supply_listing_type/supply_listing_id for compatibility.
     * @return array
     */
    public function buildForListing(string $listingType, int $listingId, ?array $options = []): array
    {
        try {
            $canonical = $this->normalizeListingType($listingType);
            $listing   = $this->findListing($canonical, $listingId);

            if ($listing === null) {
                return $this->buildNotFoundResponse($listingType, $listingId);
            }

            $missingSources = [];
            $warnings       = [];

            $listingFields = $this->extractListingFields($listing, $canonical, $listingId);

            $propertyIntelligence = null;
            if (in_array($canonical, ['seller', 'landlord'], true)) {
                $propertyIntelligence = $this->buildPropertyIntelligence(
                    $canonical, $listingId, $missingSources
                );
            }

            $locationIntelligence = $this->buildLocationIntelligence(
                $canonical, $listingId, $missingSources
            );

            $buyerAvatar = null;
            if ($canonical === 'buyer') {
                $buyerAvatar = $this->buildBuyerAvatar($listingId, $missingSources);
            }

            $tenantAvatar = null;
            if ($canonical === 'tenant') {
                $tenantAvatar = $this->buildTenantAvatar($listingId, $missingSources);
            }

            $compatibility = null;
            if ($this->hasPairOptions($options ?? [])) {
                $compatibility = $this->buildCompatibility(
                    $canonical, $listingId, $options ?? [], $warnings
                );
            }

            $offerAnalysis = $this->buildOfferAnalysis($canonical, $listingId);

            $sourceVersions = $this->buildSourceVersions(
                $propertyIntelligence, $locationIntelligence,
                $buyerAvatar, $tenantAvatar, $compatibility
            );

            $status = $this->determineStatus(
                $canonical, $propertyIntelligence, $locationIntelligence,
                $buyerAvatar, $tenantAvatar, $missingSources
            );

            return [
                'success'               => true,
                'listing_type'          => $canonical,
                'listing_id'            => $listingId,
                'context_version'       => self::CONTEXT_VERSION,
                'status'                => $status,
                'listing'               => $listingFields,
                'property_intelligence' => $propertyIntelligence,
                'location_intelligence' => $locationIntelligence,
                'buyer_avatar'          => $buyerAvatar,
                'tenant_avatar'         => $tenantAvatar,
                'compatibility'         => $compatibility,
                'offer_analysis'        => $offerAnalysis,
                'missing_sources'       => $missingSources,
                'warnings'              => $warnings,
                'source_versions'       => $sourceVersions,
                'assembled_at'          => now()->toISOString(),
                'error'                 => null,
            ];
        } catch (\Throwable $e) {
            return $this->buildFailedResponse($listingType, $listingId, $e->getMessage());
        }
    }

    // =========================================================================
    // Listing Resolution
    // =========================================================================

    /**
     * Normalize an input listing type string to one of the four canonical values.
     * Returns the input unchanged when no alias is found (will resolve as not_found).
     */
    protected function normalizeListingType(string $listingType): string
    {
        return self::TYPE_ALIASES[strtolower($listingType)] ?? $listingType;
    }

    /**
     * Resolve the primary listing model for the given canonical type and ID.
     * Returns null when no record exists.
     */
    protected function findListing(string $canonicalType, int $listingId): ?object
    {
        return match ($canonicalType) {
            'seller'   => PropertyAuction::find($listingId),
            'buyer'    => BuyerCriteriaAuction::find($listingId),
            'landlord' => LandlordAuction::find($listingId),
            'tenant'   => TenantCriteriaAuction::find($listingId),
            default    => null,
        };
    }

    /**
     * Extract the approved listing fields from the resolved model.
     *
     * Fields: listing_type, listing_id, listing_title, city, state, county,
     * property_type, listing_status, created_at, updated_at.
     */
    protected function extractListingFields(object $listing, string $canonicalType, int $listingId): array
    {
        $infoGet = function (string $key) use ($listing): ?string {
            if (method_exists($listing, 'info')) {
                $val = $listing->info($key);
                return ($val !== false && $val !== null) ? (string) $val : null;
            }
            return null;
        };

        $nativeGet = function (string $key) use ($listing): ?string {
            return isset($listing->{$key}) ? (string) $listing->{$key} : null;
        };

        $resolve = function (string $key) use ($infoGet, $nativeGet): ?string {
            return $nativeGet($key) ?? $infoGet($key);
        };

        return [
            'listing_type'   => $canonicalType,
            'listing_id'     => $listingId,
            'listing_title'  => $infoGet('listing_title') ?? $infoGet('title') ?? $nativeGet('title'),
            'city'           => $resolve('city'),
            'state'          => $resolve('state'),
            'county'         => $resolve('county'),
            'property_type'  => $infoGet('property_type') ?? $nativeGet('property_type'),
            'listing_status' => $nativeGet('is_approved') !== null
                ? ($listing->is_approved ? 'approved' : 'pending')
                : ($infoGet('status') ?? null),
            'created_at'     => isset($listing->created_at) ? (string) $listing->created_at : null,
            'updated_at'     => isset($listing->updated_at) ? (string) $listing->updated_at : null,
        ];
    }

    // =========================================================================
    // Property Intelligence Context (seller and landlord only)
    //
    // READ-ONLY: calls PropertyIntelligenceProfileService::buildPayloadReadOnly()
    // which derives all approved intelligence fields from the persisted profile
    // WITHOUT calling save(). The caller (generate()) is the only path that writes.
    //
    // Approved fields returned:
    //   property_strengths, property_highlights, property_positioning,
    //   property_target_audiences, property_personality_tags, property_story,
    //   location_intelligence_context (from persisted column, not re-fetched),
    //   property_intelligence_version, source_profile_id, source_profile_version,
    //   source_profile_computed_at.
    // =========================================================================

    /**
     * Resolve the latest active PropertyDnaProfile for a listing.
     */
    protected function findPropertyDnaProfile(string $canonicalType, int $listingId): ?PropertyDnaProfile
    {
        return PropertyDnaProfile::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->latest('computed_at')
            ->first();
    }

    /**
     * Assemble the property intelligence context section.
     * Returns null and appends 'property_intelligence' to $missingSources on failure.
     *
     * Calls PropertyIntelligenceProfileService::buildPayloadReadOnly() — no DB writes.
     */
    protected function buildPropertyIntelligence(
        string $canonicalType,
        int $listingId,
        array &$missingSources
    ): ?array {
        $profile = $this->findPropertyDnaProfile($canonicalType, $listingId);

        if ($profile === null) {
            $missingSources[] = 'property_intelligence';
            return null;
        }

        $payload = $this->propertyIntelligenceService->buildPayloadReadOnly($profile);

        if (!($payload['success'] ?? false)) {
            $missingSources[] = 'property_intelligence';
            return null;
        }

        return [
            'property_strengths'            => $payload['property_strengths'],
            'property_highlights'           => $payload['property_highlights'],
            'property_positioning'          => $payload['property_positioning'],
            'property_target_audiences'     => $payload['property_target_audiences'],
            'property_personality_tags'     => $payload['property_personality_tags'],
            'property_story'                => $payload['property_story'],
            'location_intelligence_context' => $payload['location_intelligence_context'] ?? null,
            'property_intelligence_version' => $payload['property_intelligence_version'],
            'source_profile_id'             => $profile->id,
            'source_profile_version'        => $profile->version ?? null,
            'source_profile_computed_at'    => isset($profile->computed_at)
                ? (string) $profile->computed_at
                : null,
        ];
    }

    // =========================================================================
    // Location Intelligence Context (all listing types)
    // =========================================================================

    /**
     * Resolve the latest PropertyLocationDna for a listing.
     */
    protected function findPropertyLocationDna(string $canonicalType, int $listingId): ?PropertyLocationDna
    {
        return PropertyLocationDna::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->latest('generated_at')
            ->first();
    }

    /**
     * Assemble the location intelligence context section.
     * Returns null and appends 'location_intelligence' to $missingSources on failure.
     *
     * lifestyle_scores, lifestyle_categories, and location_narrative are extracted
     * from sub-keys within lifestyle_json when available.
     * lifestyle_version is extracted from lifestyle_json['version'] when present.
     */
    protected function buildLocationIntelligence(
        string $canonicalType,
        int $listingId,
        array &$missingSources
    ): ?array {
        $locationDna = $this->findPropertyLocationDna($canonicalType, $listingId);

        if ($locationDna === null) {
            $missingSources[] = 'location_intelligence';
            return null;
        }

        $lifestyleJson = $locationDna->lifestyle_json;
        $lifestyleArr  = is_array($lifestyleJson) ? $lifestyleJson : [];

        return [
            'lifestyle_json'         => $lifestyleJson,
            'lifestyle_scores'       => $lifestyleArr['scores'] ?? null,
            'lifestyle_categories'   => $lifestyleArr['categories'] ?? null,
            'location_narrative'     => $lifestyleArr['narrative'] ?? null,
            'lifestyle_version'      => $lifestyleArr['version'] ?? null,
            'geocode_status'         => $locationDna->geocode_status ?? null,
            'generated_at'           => isset($locationDna->generated_at)
                ? (string) $locationDna->generated_at
                : null,
        ];
    }

    // =========================================================================
    // Buyer Avatar Context (buyer listings only)
    // =========================================================================

    /**
     * Resolve the latest active BuyerTenantDnaProfile for a listing.
     */
    protected function findBuyerTenantDnaProfile(string $canonicalType, int $listingId): ?BuyerTenantDnaProfile
    {
        return BuyerTenantDnaProfile::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->latest('computed_at')
            ->first();
    }

    /**
     * Assemble the buyer avatar context section.
     * Returns null and appends 'buyer_avatar' to $missingSources when profile is absent.
     */
    protected function buildBuyerAvatar(int $listingId, array &$missingSources): ?array
    {
        $profile = $this->findBuyerTenantDnaProfile('buyer', $listingId);

        if ($profile === null) {
            $missingSources[] = 'buyer_avatar';
            return null;
        }

        return [
            'avatar_type'                => $profile->avatar_type ?? null,
            'primary_motivation'         => $profile->primary_motivation ?? null,
            'secondary_motivation'       => $profile->secondary_motivation ?? null,
            'buyer_narrative'            => $profile->buyer_narrative ?? null,
            'buyer_preference_summary'   => $profile->buyer_preference_summary ?? null,
            'buyer_personality_tags'     => $profile->buyer_personality_tags ?? null,
            'buyer_match_preferences'    => $profile->buyer_match_preferences ?? null,
            'avatar_confidence_score'    => $profile->avatar_confidence_score ?? null,
            'buyer_readiness_score'      => $profile->buyer_readiness_score ?? null,
            'buyer_avatar_version'       => $profile->buyer_avatar_version ?? null,
        ];
    }

    // =========================================================================
    // Tenant Avatar Context (tenant listings only)
    // =========================================================================

    /**
     * Assemble the tenant avatar context section.
     * Returns null and appends 'tenant_avatar' to $missingSources when profile is absent.
     */
    protected function buildTenantAvatar(int $listingId, array &$missingSources): ?array
    {
        $profile = $this->findBuyerTenantDnaProfile('tenant', $listingId);

        if ($profile === null) {
            $missingSources[] = 'tenant_avatar';
            return null;
        }

        return [
            'avatar_type'                => $profile->avatar_type ?? null,
            'primary_motivation'         => $profile->primary_motivation ?? null,
            'secondary_motivation'       => $profile->secondary_motivation ?? null,
            'tenant_narrative'           => $profile->tenant_narrative ?? null,
            'tenant_preference_summary'  => $profile->tenant_preference_summary ?? null,
            'tenant_personality_tags'    => $profile->tenant_personality_tags ?? null,
            'tenant_match_preferences'   => $profile->tenant_match_preferences ?? null,
            'avatar_confidence_score'    => $profile->avatar_confidence_score ?? null,
            'tenant_avatar_version'      => $profile->tenant_avatar_version ?? null,
        ];
    }

    // =========================================================================
    // Compatibility Context (only when pair options are supplied)
    // =========================================================================

    /**
     * Returns true when $options contains a complete demand+supply pair.
     */
    protected function hasPairOptions(array $options): bool
    {
        return isset($options['demand_listing_type'], $options['demand_listing_id'],
                     $options['supply_listing_type'], $options['supply_listing_id']);
    }

    /**
     * Resolve the latest active ListingCompatibilityScore for the given pair.
     */
    protected function findCompatibilityScore(
        string $demandType,
        int $demandId,
        string $supplyType,
        int $supplyId
    ): ?ListingCompatibilityScore {
        return ListingCompatibilityScore::where('demand_listing_type', $demandType)
            ->where('demand_listing_id', $demandId)
            ->where('supply_listing_type', $supplyType)
            ->where('supply_listing_id', $supplyId)
            ->whereNull('archived_at')
            ->latest('computed_at')
            ->first();
    }

    /**
     * Assemble the compatibility context section.
     * When a pair is requested but no score record exists, appends a warning (not a missing_source).
     */
    protected function buildCompatibility(
        string $canonicalType,
        int $listingId,
        array $options,
        array &$warnings
    ): ?array {
        $demandType = (string) $options['demand_listing_type'];
        $demandId   = (int)    $options['demand_listing_id'];
        $supplyType = (string) $options['supply_listing_type'];
        $supplyId   = (int)    $options['supply_listing_id'];

        $score = $this->findCompatibilityScore($demandType, $demandId, $supplyType, $supplyId);

        if ($score === null) {
            $warnings[] = 'Compatibility data is not available for the requested listing pair.';
            return null;
        }

        return [
            'overall_score'                 => $score->overall_score ?? null,
            'physical_match_score'          => $score->physical_match_score ?? null,
            'financial_match_score'         => $score->financial_match_score ?? null,
            'terms_match_score'             => $score->terms_match_score ?? null,
            'location_match_score'          => $score->location_match_score ?? null,
            'compatibility_summary_json'    => $score->compatibility_summary_json ?? null,
            'compatibility_highlights'      => $score->compatibility_highlights ?? null,
            'compatibility_warnings'        => $score->compatibility_warnings ?? null,
            'compatibility_readiness_score' => $score->compatibility_readiness_score ?? null,
            'compatibility_narrative'       => $score->compatibility_narrative ?? null,
            'score_explanation'             => $score->score_explanation ?? null,
            'version'                       => $score->version ?? null,
            'computed_at'                   => isset($score->computed_at)
                ? (string) $score->computed_at
                : null,
        ];
    }

    // =========================================================================
    // Offer Analysis Context
    // =========================================================================

    /**
     * Resolve the latest AcceptedBidSummary linked to a listing.
     */
    protected function findAcceptedBidSummary(string $canonicalType, int $listingId): ?AcceptedBidSummary
    {
        return AcceptedBidSummary::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->latest()
            ->first();
    }

    /**
     * Assemble the offer analysis context section.
     * Returns null when no accepted bid summary exists — this is not a failure.
     *
     * DATA GOVERNANCE: Only deal-content fields are exposed here. Signature
     * metadata (names, IP addresses, user-agent strings, timezones), user IDs,
     * and any other user-identifying fields are deliberately excluded to prevent
     * PII leakage into the Ask AI context payload. Ask AI phases require only
     * the accepted-terms content to reason about offer status and deal structure.
     *
     * Approved fields:
     *   id, listing_type, listing_id, accepted_bid_id, accepted_counter_id,
     *   summary_html, summary_pdf_path, created_at, updated_at.
     */
    protected function buildOfferAnalysis(string $canonicalType, int $listingId): ?array
    {
        $summary = $this->findAcceptedBidSummary($canonicalType, $listingId);

        if ($summary === null) {
            return null;
        }

        return [
            'id'                  => $summary->id,
            'listing_type'        => $summary->listing_type ?? $canonicalType,
            'listing_id'          => $summary->listing_id ?? $listingId,
            'accepted_bid_id'     => $summary->accepted_bid_id ?? null,
            'accepted_counter_id' => $summary->accepted_counter_id ?? null,
            'summary_html'        => $summary->summary_html ?? null,
            'summary_pdf_path'    => $summary->summary_pdf_path ?? null,
            'created_at'          => isset($summary->created_at) ? (string) $summary->created_at : null,
            'updated_at'          => isset($summary->updated_at) ? (string) $summary->updated_at : null,
        ];
    }

    // =========================================================================
    // Source Versions & Status Assembly
    // =========================================================================

    /**
     * Build the source_versions map from available intelligence sections.
     *
     * location_dna_lifestyle_version is populated from location_intelligence['lifestyle_version']
     * when the location intelligence section is available.
     */
    protected function buildSourceVersions(
        ?array $propertyIntelligence,
        ?array $locationIntelligence,
        ?array $buyerAvatar,
        ?array $tenantAvatar,
        ?array $compatibility
    ): array {
        $versions = [
            'ask_ai_context'                => self::CONTEXT_VERSION,
            'property_intelligence_version' => null,
            'location_dna_lifestyle_version'=> null,
            'buyer_avatar_version'          => null,
            'tenant_avatar_version'         => null,
            'compatibility_version'         => null,
        ];

        if ($propertyIntelligence !== null) {
            $versions['property_intelligence_version'] =
                $propertyIntelligence['property_intelligence_version'] ?? null;
        }

        if ($locationIntelligence !== null) {
            $versions['location_dna_lifestyle_version'] =
                $locationIntelligence['lifestyle_version'] ?? null;
        }

        if ($buyerAvatar !== null) {
            $versions['buyer_avatar_version'] = $buyerAvatar['buyer_avatar_version'] ?? null;
        }

        if ($tenantAvatar !== null) {
            $versions['tenant_avatar_version'] = $tenantAvatar['tenant_avatar_version'] ?? null;
        }

        if ($compatibility !== null) {
            $versions['compatibility_version'] = $compatibility['version'] ?? null;
        }

        return $versions;
    }

    /**
     * Determine the final status string.
     *
     * 'assembled' — listing found and at least one intelligence source is populated.
     * 'partial'   — listing found but one or more expected sources are missing.
     */
    protected function determineStatus(
        string $canonicalType,
        ?array $propertyIntelligence,
        ?array $locationIntelligence,
        ?array $buyerAvatar,
        ?array $tenantAvatar,
        array $missingSources
    ): string {
        if (!empty($missingSources)) {
            return 'partial';
        }

        $hasIntelligence = false;

        if (in_array($canonicalType, ['seller', 'landlord'], true)) {
            $hasIntelligence = $propertyIntelligence !== null;
        }

        if ($locationIntelligence !== null) {
            $hasIntelligence = true;
        }

        if ($canonicalType === 'buyer' && $buyerAvatar !== null) {
            $hasIntelligence = true;
        }

        if ($canonicalType === 'tenant' && $tenantAvatar !== null) {
            $hasIntelligence = true;
        }

        return $hasIntelligence ? 'assembled' : 'partial';
    }

    // =========================================================================
    // Fixed-shape response helpers
    // =========================================================================

    /**
     * Build the empty contract-shaped payload used by not_found and failed responses.
     * All optional intelligence sections are null; missing_sources and warnings are empty.
     * Includes success=false plus top-level listing_type/listing_id to match the full contract.
     * The `error` key is always present (null in this base payload).
     */
    private function buildEmptyPayload(string $status, string $listingType, int $listingId): array
    {
        return [
            'success'               => false,
            'listing_type'          => $listingType,
            'listing_id'            => $listingId,
            'context_version'       => self::CONTEXT_VERSION,
            'status'                => $status,
            'listing'               => null,
            'property_intelligence' => null,
            'location_intelligence' => null,
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => null,
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => self::CONTEXT_VERSION,
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at'          => now()->toISOString(),
            'error'                 => null,
        ];
    }

    private function buildNotFoundResponse(string $listingType, int $listingId): array
    {
        return $this->buildEmptyPayload('not_found', $listingType, $listingId);
    }

    private function buildFailedResponse(string $listingType, int $listingId, string $error): array
    {
        $payload          = $this->buildEmptyPayload('failed', $listingType, $listingId);
        $payload['error'] = $error;
        return $payload;
    }
}
