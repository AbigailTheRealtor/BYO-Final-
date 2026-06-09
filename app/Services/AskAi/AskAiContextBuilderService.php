<?php

namespace App\Services\AskAi;

use App\Models\AcceptedBidSummary;
use App\Models\AiFaqAnswer;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\LandlordAgentAuction;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;

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
    private LocationDnaIntelligenceContextService $locationDnaIntelligenceService;
    private LocationDnaMarketingContextService $locationDnaMarketingService;

    public function __construct(
        PropertyIntelligenceProfileService $propertyIntelligenceService,
        LocationDnaIntelligenceContextService $locationDnaIntelligenceService,
        LocationDnaMarketingContextService $locationDnaMarketingService
    ) {
        $this->propertyIntelligenceService    = $propertyIntelligenceService;
        $this->locationDnaIntelligenceService = $locationDnaIntelligenceService;
        $this->locationDnaMarketingService    = $locationDnaMarketingService;
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
     * Assemble a lightweight chip context for the listing view suggested-questions chips.
     *
     * Returns only the two keys consumed by AskAiSuggestedQuestionsService::forListing():
     *   'listing'     — array produced by extractListingFields() for the given listing.
     *   'faq_answers' — array produced by buildFaqAnswers() for the given listing.
     *
     * No DNA, no compatibility, no location intelligence, no OpenAI calls are made.
     * This method is deliberately cheap so it can be called during a normal page render.
     *
     * When any exception is thrown (e.g. listing has no `id` property, EAV meta
     * unavailable), an empty array is returned so forListing() falls back to the
     * static pool without surfacing a page error.
     *
     * @param  object  $listing        Resolved listing model instance (already loaded by the controller).
     * @param  string  $canonicalType  One of: 'seller', 'buyer', 'landlord', 'tenant'.
     * @return array{listing: array, faq_answers: array}|array{}
     */
    public function buildChipContext(object $listing, string $canonicalType): array
    {
        try {
            $canonical  = $this->normalizeListingType($canonicalType);
            $listingId  = (int) ($listing->id ?? 0);
            $fields     = $this->extractListingFields($listing, $canonical, $listingId);
            $faqAnswers = $this->buildFaqAnswers($listing, $canonical);

            return [
                'listing'     => $fields,
                'faq_answers' => $faqAnswers,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

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
            $faqAnswers    = $this->buildFaqAnswers($listing, $canonical);

            $propertyIntelligence = null;
            if (in_array($canonical, ['seller', 'landlord'], true)) {
                $propertyIntelligence = $this->buildPropertyIntelligence(
                    $canonical, $listingId, $missingSources
                );
            }

            $locationIntelligence = $this->buildLocationIntelligence(
                $canonical, $listingId, $missingSources, $warnings
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
                'faq_answers'           => $faqAnswers,
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
     *
     * Model-to-table mapping:
     *   seller   → SellerAgentAuction   → seller_agent_auctions
     *   buyer    → BuyerAgentAuction    → buyer_agent_auctions
     *   landlord → LandlordAgentAuction → landlord_agent_auctions
     *   tenant   → TenantAgentAuction   → tenant_agent_auctions
     */
    protected function findListing(string $canonicalType, int $listingId): ?object
    {
        return match ($canonicalType) {
            'seller'   => SellerAgentAuction::find($listingId),
            'buyer'    => BuyerAgentAuction::find($listingId),
            'landlord' => LandlordAgentAuction::find($listingId),
            'tenant'   => TenantAgentAuction::find($listingId),
            default    => null,
        };
    }

    /**
     * Extract the approved listing fields from the resolved model.
     *
     * Returns the base metadata fields (present for all roles) merged with
     * role-specific public-factual fields sourced from native columns and EAV meta.
     *
     * Base fields (all roles): listing_type, listing_id, listing_title, city, state,
     * county, property_type, listing_status, created_at, updated_at.
     *
     * Factual fields (role-specific): bedrooms, bathrooms, asking_price, rent_amount,
     * lease_length, pets_allowed, hoa_fee, pool, parking_spaces, square_feet,
     * year_built, and additional role-appropriate fields. See extractFactualFields().
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

        $base = [
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

        $factual = $this->extractFactualFields($listing, $canonicalType, $infoGet, $nativeGet);

        return array_merge($base, $factual);
    }

    /**
     * Extract role-specific public-factual listing fields.
     *
     * DATA GOVERNANCE: Only fields classified as Public-Factual in
     * ASK_AI_FULL_CONTEXT_MAP.md are included. PII fields (names, phone,
     * email, brokerage), internal workflow fields, and protected-class-adjacent
     * fields are explicitly excluded. Compliance-Sensitive fields (flood zone code,
     * security deposit, income requirement) are included where they carry the
     * listing_facts contract disclosure requirement.
     *
     * JSON meta values (appliances, pet_species_allowed, tenant_pays) are decoded
     * and flattened to a comma-separated string for prompt-friendly consumption.
     *
     * @param  object    $listing       The resolved listing model instance.
     * @param  string    $canonicalType One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @param  callable  $infoGet       EAV meta accessor: info($key) → ?string.
     * @param  callable  $nativeGet     Native column accessor: $listing->{$key} → ?string.
     * @return array
     */
    protected function extractFactualFields(
        object $listing,
        string $canonicalType,
        callable $infoGet,
        callable $nativeGet
    ): array {
        return match ($canonicalType) {

            // -----------------------------------------------------------------
            // Seller — seller_agent_auctions (native columns) + seller_agent_auction_metas (EAV)
            //
            // Almost all property-detail fields are stored in EAV via saveMeta(), not
            // in native columns. Native-only fields: address, description, auction_length,
            // is_sold, bedroom_id (FK fallback), bathroom_id (FK fallback).
            // All other factual fields use infoGet() to read from seller_agent_auction_metas.
            // -----------------------------------------------------------------
            'seller' => [
                'address'              => $nativeGet('address'),
                'description'          => $nativeGet('description'),
                'asking_price'         => $infoGet('maximum_budget'),
                'bedrooms'             => $infoGet('bedrooms') ?? $nativeGet('bedroom_id'),
                'bathrooms'            => $infoGet('bathrooms') ?? $nativeGet('bathroom_id'),
                'square_feet'          => $infoGet('minimum_heated_square'),
                'year_built'           => $infoGet('year_built'),
                'pool'                 => $infoGet('pool_needed'),
                'pool_type'            => $this->decodeJsonField($infoGet('pool_type')),
                'carport'              => $infoGet('carport_needed'),
                'garage'               => $infoGet('garage_needed'),
                'garage_spaces'        => $infoGet('garage_parking_spaces'),
                'hoa_association'      => $infoGet('has_hoa'),
                'hoa_fee'              => $infoGet('association_fee_amount'),
                'hoa_payment_schedule' => $infoGet('association_fee_frequency'),
                'pets_allowed'         => $infoGet('pets'),
                'number_of_pets_allowed' => $infoGet('number_of_pets'),
                'max_pet_weight'       => $infoGet('weight_of_pets'),
                'pet_restrictions'     => $infoGet('pet_restrictions'),
                'rental_restrictions'  => $infoGet('leasing_restrictions'),
                'flood_zone_code'      => $infoGet('flood_zone_code'),
                // disclosure_flags is a governance contract marker for the prompt layer.
                // flood_zone => true does NOT mean the property is in a flood zone — the
                // flood_zone_code scalar carries that data. This flag tells the AI that
                // flood-zone data is present in this context and must be handled with
                // the flood-zone disclosure template. Always set for seller listings.
                'disclosure_flags'     => ['flood_zone' => true],
                'closing_date'         => $infoGet('target_closing_date'),
                'auction_length'       => $nativeGet('auction_length'),
                'sold'                 => $nativeGet('is_sold'),
                'annual_property_taxes' => $infoGet('annual_property_taxes'),
                'service_type'         => $infoGet('service_type'),
            ],

            // -----------------------------------------------------------------
            // Buyer — buyer_agent_auctions (native columns) + buyer_agent_auction_metas (EAV)
            //
            // All property-detail and buyer-criteria fields are stored in EAV via
            // saveMeta(). Native-only fields: address, additional_details (description).
            // All other factual fields use infoGet() to read from buyer_agent_auction_metas.
            // -----------------------------------------------------------------
            'buyer' => [
                'address'                      => $nativeGet('address'),
                'description'                  => $nativeGet('additional_details'),
                'max_price'                    => $infoGet('maximum_budget'),
                'bedrooms'                     => $infoGet('bedrooms'),
                'bathrooms'                    => $infoGet('bathrooms'),
                'square_feet'                  => $infoGet('minimum_heated_square'),
                'pool'                         => $infoGet('pool_needed'),
                'carport'                      => $infoGet('carport_needed'),
                'garage'                       => $infoGet('garage_needed'),
                'garage_spaces'                => $infoGet('garage_parking_spaces'),
                'hoa_acceptable'               => $infoGet('hoa_acceptance'),
                'max_hoa_fee'                  => $infoGet('hoa_max_monthly_fee'),
                'pets_allowed'                 => $infoGet('pets'),
                'pets_detail'                  => $infoGet('type_of_pets'),
                'pets_breed'                   => $infoGet('breed_of_pets'),
                'pets_weight'                  => $infoGet('weight_of_pets'),
                'loan_pre_approved'            => $infoGet('pre_approved'),
                'financing_type'               => $this->decodeJsonField($infoGet('offered_financing')),
                'inspection_period'            => $infoGet('inspection_period_days'),
                'closing_date'                 => $infoGet('target_closing_date'),
                'inspection_contingency_buyer' => $infoGet('inspection_contingency_buyer'),
                'appraisal_contingency_buyer'  => $infoGet('appraisal_contingency_buyer'),
                'financing_contingency_buyer'  => $infoGet('financing_contingency_buyer'),
            ],

            // -----------------------------------------------------------------
            // Landlord — landlord_auction_metas (EAV only via info())
            // -----------------------------------------------------------------
            'landlord' => [
                'rent_amount'               => $infoGet('maximum_budget'),
                'bedrooms'                  => $infoGet('bedrooms'),
                'bathrooms'                 => $infoGet('bathrooms'),
                'square_feet'               => $infoGet('minimum_heated_square'),
                'unit_size'                 => $infoGet('unit_size'),
                'number_of_units'           => $infoGet('number_of_unit'),
                'property_zip'              => $infoGet('property_zip'),
                'property_items'            => $this->decodeJsonField($infoGet('property_items')),
                'condition_prop'            => $infoGet('condition_prop'),
                'appliances'                => $this->decodeJsonField($infoGet('appliances')),
                'available_date'            => $infoGet('available_date'),
                'pet_policy'                => $infoGet('pet_policy'),
                'pet_deposit_fee_rent'      => $infoGet('pet_deposit_fee_rent'),
                'pet_max_weight_lbs'        => $infoGet('pet_max_weight_lbs'),
                'pet_species_allowed'       => $this->decodeJsonField($infoGet('pet_species_allowed')),
                'parking_terms'             => $infoGet('parking_terms'),
                'utilities'                 => $infoGet('utilities'),
                'smoking_policy'            => $infoGet('smoking_policy'),
                'subletting_policy'         => $infoGet('subletting_policy'),
                'has_hoa'                   => $infoGet('has_hoa'),
                'association_name'          => $infoGet('association_name'),
                'association_fee_amount'    => $infoGet('association_fee_amount'),
                'association_fee_frequency' => $infoGet('association_fee_frequency'),
                'association_amenities'     => $this->decodeJsonField($infoGet('association_amenities')),
                'annual_property_taxes'     => $infoGet('annual_property_taxes'),
                'leasing_restrictions'      => $infoGet('leasing_restrictions'),
                'lease_length'              => $infoGet('min_lease_period') ?? $infoGet('minimum_lease_period'),
                'renewal_option'            => $infoGet('renewal_option_offered'),
                'number_of_occupants'       => $infoGet('number_occupant'),
                'additional_lease_terms'    => $infoGet('additional_landlord_lease_terms'),
            ],

            // -----------------------------------------------------------------
            // Tenant — tenant_criteria_auction_metas (EAV via info())
            // -----------------------------------------------------------------
            'tenant' => [
                'max_rent'             => $infoGet('maximum_budget'),
                'bedrooms'             => $infoGet('bedrooms'),
                'bathrooms'            => $infoGet('bathrooms'),
                'desired_lease_length' => $infoGet('tenant_desired_lease_length'),
                'property_items'       => $this->decodeJsonField($infoGet('property_items')),
                'appliances'           => $this->decodeJsonField($infoGet('appliances')),
                'condition_prop'       => $infoGet('condition_prop'),
                'pet_information'      => $infoGet('pet_information'),
                'parking_needed'       => $infoGet('parking_needed'),
                'utilities'            => $infoGet('utilities'),
                'utility_preference'   => $infoGet('utility_preference'),
                'tenant_pays'          => $this->decodeJsonField($infoGet('tenant_pays')),
                'current_status'       => $infoGet('current_status'),
                'number_of_occupants'  => $infoGet('number_of_occupants'),
                'number_of_units'      => $infoGet('number_of_unit'),
            ],

            default => [],
        };
    }

    /**
     * Decode a JSON meta value to a comma-separated string for prompt consumption.
     *
     * Many EAV meta fields store multi-select arrays as JSON (e.g. appliances,
     * pet_species_allowed, tenant_pays). This helper decodes them to a flat,
     * human-readable string. When the value is already a plain string or null,
     * it is returned as-is.
     *
     * OUTPUT FORMAT CONTRACT (established in Phase 1):
     * JSON arrays are decoded to a comma-separated plain string, e.g.:
     *   '["Washer","Dryer","Dishwasher"]'  →  'Washer, Dryer, Dishwasher'
     *   '["Pool","Gym"]'                   →  'Pool, Gym'
     * This string format is intentional — it is prompt-friendly and avoids
     * embedding PHP arrays or raw JSON brackets in the AI context payload.
     * All Ask AI tests assert this string shape (not a PHP array).
     *
     * @param  string|null $value  Raw meta value (JSON string or plain string).
     * @return string|null
     */
    private function decodeJsonField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            $items = array_filter(array_map('strval', $decoded), static fn ($v) => $v !== '');
            return !empty($items) ? implode(', ', array_values($items)) : null;
        }

        return $value;
    }

    /**
     * Resolve a financing_id FK to its human-readable label from the financings table.
     *
     * Returns null when the id is absent, zero, or the DB record cannot be found.
     * A try/catch guards against environments where the table may not be present
     * (e.g. unit-test runs without a database connection).
     *
     * @param  string|null $financingIdRaw  Raw string value of the financing_id column.
     * @return string|null
     */
    protected function resolveFinancingType(?string $financingIdRaw): ?string
    {
        if ($financingIdRaw === null || $financingIdRaw === '' || $financingIdRaw === '0') {
            return null;
        }

        try {
            $name = \Illuminate\Support\Facades\DB::table('financings')
                ->where('id', (int) $financingIdRaw)
                ->value('name');
            return $name !== null ? (string) $name : null;
        } catch (\Throwable) {
            return null;
        }
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
    // FAQ Answers Context (all listing types)
    // =========================================================================

    /**
     * Build the faq_answers map for the listing.
     *
     * Resolution order:
     *   1. Inline JSON stored in the native `listing_ai_faq` column (tenant) or
     *      the `listing_ai_faq` EAV meta key (seller, buyer, landlord).
     *      Expected shape: {"question_key": "answer text", ...}
     *   2. Fallback: rows in `ai_faq_answers` matching listing_type + listing_id.
     *
     * Each entry in the returned array is an enriched object shape:
     *   config_key            — the original question key
     *   answer_text           — the answer text
     *   question_label        — human-readable question label (from config, or null)
     *   question_group        — group/category the question belongs to (from config, or null)
     *   intelligence_category — snake_case category derived from question_group (or null)
     *
     * Returns an empty array when no FAQ answers are available. Exceptions are
     * caught and silenced so a missing or malformed FAQ record never interrupts
     * context assembly.
     *
     * This method always returns the enriched object shape. The prompt builder's
     * sanitizeFaqAnswers() forwards only the four LLM-safe fields and also accepts
     * legacy raw-string entries for robustness when context is assembled from external
     * or cached sources.
     *
     * @param  object $listing       Resolved listing model instance.
     * @param  string $canonicalType One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @return array<string, array{config_key: string, answer_text: string, question_label: string|null, question_group: string|null, intelligence_category: string|null}>
     */
    protected function buildFaqAnswers(object $listing, string $canonicalType): array
    {
        try {
            $raw = null;

            if ($canonicalType === 'tenant') {
                // Try native listing_ai_faq column first (legacy tenant_criteria_auctions has it).
                // Live TenantAgentAuction stores FAQ as EAV meta — fall back to info() when absent.
                $col = $listing->listing_ai_faq ?? null;
                if ($col !== null) {
                    $raw = is_array($col) ? $col : json_decode((string) $col, true);
                }
                if ($raw === null && method_exists($listing, 'info')) {
                    $meta = $listing->info('listing_ai_faq');
                    if ($meta !== null && $meta !== false && $meta !== '') {
                        $raw = is_array($meta) ? $meta : json_decode((string) $meta, true);
                    }
                }
            } else {
                // All other roles store the FAQ as EAV meta
                if (method_exists($listing, 'info')) {
                    $meta = $listing->info('listing_ai_faq');
                    if ($meta !== null && $meta !== false && $meta !== '') {
                        $raw = is_array($meta) ? $meta : json_decode((string) $meta, true);
                    }
                }
            }

            $answers = [];

            if (is_array($raw)) {
                $configIndex = AskAiFaqEnrichmentService::buildConfigIndex($canonicalType);

                foreach ($raw as $qKey => $answerText) {
                    $qKey = (string) $qKey;
                    if ($answerText !== null && $answerText !== '' && $answerText !== false) {
                        $meta = $configIndex[$qKey] ?? [
                            'question_group'        => null,
                            'question_label'        => null,
                            'intelligence_category' => null,
                        ];
                        $answers[$qKey] = [
                            'config_key'            => $qKey,
                            'answer_text'           => (string) $answerText,
                            'question_label'        => $meta['question_label'],
                            'question_group'        => $meta['question_group'],
                            'intelligence_category' => $meta['intelligence_category'],
                        ];
                    }
                }
            }

            // Fallback: query ai_faq_answers table when no inline answers were found
            if (empty($answers)) {
                $dbRows = AiFaqAnswer::where('listing_type', $canonicalType)
                    ->where('listing_id', $listing->id)
                    ->get();

                foreach ($dbRows as $row) {
                    $text = $row->answer_text ?? null;
                    if (!empty($text)) {
                        $normalized    = is_array($row->answer_normalized) ? $row->answer_normalized : [];
                        $qKey          = (string) $row->question_key;
                        $answers[$qKey] = [
                            'config_key'            => $normalized['config_key'] ?? $qKey,
                            'answer_text'           => (string) $text,
                            'question_label'        => $normalized['question_label'] ?? null,
                            'question_group'        => $row->question_group ?? null,
                            'intelligence_category' => $row->intelligence_category ?? null,
                        ];
                    }
                }
            }

            return $answers;
        } catch (\Throwable) {
            return [];
        }
    }

    // =========================================================================
    // Location Intelligence Context (all listing types)
    //
    // Assembles three layers of location data:
    //   1. lifestyle_json sub-fields (scores, categories, narrative, version)
    //      sourced directly from PropertyLocationDna.
    //   2. Structured POI/amenity data (nearest_highlights, thematic blocks,
    //      available_categories, missing_categories) from
    //      LocationDnaIntelligenceContextService — merged when status=available.
    //   3. Marketing-framed thematic context (marketing_context sub-key) from
    //      LocationDnaMarketingContextService — merged when status=available.
    //
    // When the intelligence or marketing services return non-available status,
    // a warning is appended to $warnings. The overall location_intelligence
    // section is still returned (from lifestyle_json) — missing POI data is
    // NOT added to $missingSources because it is optional for all question types.
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
     * Returns null and appends 'location_intelligence' to $missingSources when no DNA record exists.
     *
     * lifestyle_scores, lifestyle_categories, and location_narrative are extracted
     * from sub-keys within lifestyle_json when available.
     * lifestyle_version is extracted from lifestyle_json['version'] when present.
     *
     * When LocationDnaIntelligenceContextService returns status=available, the
     * following keys are merged into the returned array:
     *   nearest_highlights, available_categories, missing_categories,
     *   coastal_features, daily_convenience, outdoor_recreation, transportation.
     *
     * When LocationDnaMarketingContextService returns status=available, the
     * marketing_context sub-key is added containing the four thematic blocks
     * plus available/missing categories in marketing framing.
     *
     * Non-available status from either service appends a warning to $warnings
     * but does not affect $missingSources (both are optional for all question types).
     */
    protected function buildLocationIntelligence(
        string $canonicalType,
        int $listingId,
        array &$missingSources,
        array &$warnings
    ): ?array {
        $locationDna = $this->findPropertyLocationDna($canonicalType, $listingId);

        if ($locationDna === null) {
            $missingSources[] = 'location_intelligence';
            return null;
        }

        $lifestyleJson = $locationDna->lifestyle_json;
        $lifestyleArr  = is_array($lifestyleJson) ? $lifestyleJson : [];

        $context = [
            'lifestyle_json'       => $lifestyleJson,
            'lifestyle_scores'     => $lifestyleArr['scores'] ?? null,
            'lifestyle_categories' => $lifestyleArr['categories'] ?? null,
            'location_narrative'   => $lifestyleArr['narrative'] ?? null,
            'lifestyle_version'    => $lifestyleArr['version'] ?? null,
            'geocode_status'       => $locationDna->geocode_status ?? null,
            'generated_at'         => isset($locationDna->generated_at)
                ? (string) $locationDna->generated_at
                : null,
        ];

        $intelligenceResult = $this->locationDnaIntelligenceService->getForListing($canonicalType, $listingId);

        if ($intelligenceResult['status'] === 'available') {
            $lic = $intelligenceResult['location_intelligence_context'];
            $context['nearest_highlights']   = $lic['nearest_highlights'] ?? null;
            $context['available_categories'] = $lic['available_categories'] ?? null;
            $context['missing_categories']   = $lic['missing_categories'] ?? null;
            $context['coastal_features']     = $lic['coastal_features'] ?? null;
            $context['daily_convenience']    = $lic['daily_convenience'] ?? null;
            $context['outdoor_recreation']   = $lic['outdoor_recreation'] ?? null;
            $context['transportation']       = $lic['transportation'] ?? null;
        } else {
            $warnings[] = 'location_intelligence_context not available: '
                . ($intelligenceResult['error'] ?? $intelligenceResult['status']);
        }

        $marketingResult = $this->locationDnaMarketingService->getForListing($canonicalType, $listingId);

        if ($marketingResult['status'] === 'available') {
            $context['marketing_context'] = $marketingResult['marketing_location_context'];
        } else {
            $warnings[] = 'location_marketing_context not available: '
                . ($marketingResult['error'] ?? $marketingResult['status']);
        }

        return $context;
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

        $result = [
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

        if ($score->compatibility_trait_results !== null) {
            $result['compatibility_trait_results'] = $score->compatibility_trait_results;
        }

        return $result;
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
            'faq_answers'           => [],
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
