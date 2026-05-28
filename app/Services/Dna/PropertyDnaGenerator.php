<?php

namespace App\Services\Dna;

use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\PropertyDnaProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyDnaGenerator
{
    /**
     * Full list of mapped dimension slots. Every slot present here is included
     * in the overall_dna_completeness calculation. If a slot cannot be populated
     * because no source field exists, it is left null and documented in the
     * governance doc with the reason it is skipped.
     *
     * Skipped dimensions (no source field available in current schema):
     * - "lifestyle_amenity_indicator_pool_detail": pool_type is meta but not reliably structured enough to map beyond has_pool
     */
    private const DIMENSION_SLOTS = [
        'property_type',
        'property_style',
        'property_condition',
        'bedrooms',
        'bathrooms',
        'minimum_sqft',
        'total_acreage',
        'has_pool',
        'has_garage',
        'has_carport',
        'has_storage',
        'pets_allowed',
        'is_55_plus',
        'is_commercial',
        'smoking_policy_specified',
        'has_hoa',
        'furnishing_indicator',
        'move_in_timing',
        'occupant_status',
        'lease_length_flexibility',
        'has_lease_option',
        'has_lease_purchase',
        'has_seller_financing',
        'has_assumable_loan',
        'sale_provision_type',
        'offered_financing_types',
        'interested_in_selling',
        'has_video_tour',
        'view_preference',
    ];

    public function generate(string $listingType, int $listingId): void
    {
        if ($listingType === 'seller') {
            $listing = PropertyAuction::with('meta')->find($listingId);
        } elseif ($listingType === 'landlord') {
            $listing = LandlordAuction::with('meta')->find($listingId);
        } else {
            Log::warning("PropertyDnaGenerator: unknown listing_type [{$listingType}] for id [{$listingId}]");
            return;
        }

        if (!$listing) {
            Log::warning("PropertyDnaGenerator: listing not found — type={$listingType} id={$listingId}");
            return;
        }

        $meta = $this->flattenMeta($listing);

        $dimensions = $this->mapDimensions($listing, $meta, $listingType);
        $archetypeTags = $this->buildArchetypeTags($dimensions);
        $marketingHooks = $this->buildMarketingHooks($dimensions);
        $completeness = $this->computeCompleteness($dimensions);
        $scores = $this->computeScores($dimensions);

        $this->persist($listingType, $listingId, $listing->updated_at, [
            'overall_dna_completeness'     => $completeness,
            'ai_buyer_archetype_tags'      => $archetypeTags,
            'ai_marketing_hooks'           => $marketingHooks,
            'physical_score'               => $scores['physical_score'],
            'financial_score'              => $scores['financial_score'],
            'flexibility_score'            => $scores['flexibility_score'],
            'occupant_qualification_score' => $scores['occupant_qualification_score'],
            'marketing_score'              => $scores['marketing_score'],
            'commercial_score'             => $scores['commercial_score'],
            'location_score'               => null,
            'condition_score'              => null,
            'legal_score'                  => null,
            'compatibility_score'          => null,
        ]);
    }

    private function flattenMeta($listing): array
    {
        $result = [];
        foreach ($listing->meta as $row) {
            $result[$row->meta_key] = $row->meta_value;
        }
        return $result;
    }

    private function getMeta(array $meta, string $key): ?string
    {
        $val = $meta[$key] ?? null;
        if ($val === null || $val === '' || $val === 'null') {
            return null;
        }
        return (string) $val;
    }

    private function mapDimensions($listing, array $meta, string $listingType): array
    {
        $d = [];

        try { $d['property_type'] = $this->getMeta($meta, 'property_type'); } catch (\Throwable $e) { $d['property_type'] = null; }
        try { $d['property_style'] = $this->getMeta($meta, 'property_items'); } catch (\Throwable $e) { $d['property_style'] = null; }
        try { $d['property_condition'] = $this->getMeta($meta, 'condition_prop'); } catch (\Throwable $e) { $d['property_condition'] = null; }
        try { $d['bedrooms'] = $this->getMeta($meta, 'bedrooms'); } catch (\Throwable $e) { $d['bedrooms'] = null; }
        try { $d['bathrooms'] = $this->getMeta($meta, 'bathrooms'); } catch (\Throwable $e) { $d['bathrooms'] = null; }
        try { $d['minimum_sqft'] = $this->getMeta($meta, 'minimum_heated_square'); } catch (\Throwable $e) { $d['minimum_sqft'] = null; }
        try { $d['total_acreage'] = $this->getMeta($meta, 'total_acreage'); } catch (\Throwable $e) { $d['total_acreage'] = null; }

        try {
            $poolRaw = $this->getMeta($meta, 'pool_needed');
            $d['has_pool'] = ($poolRaw !== null) ? ($this->isTruthy($poolRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['has_pool'] = null; }

        try {
            $garageRaw = $this->getMeta($meta, 'garage_needed');
            $d['has_garage'] = ($garageRaw !== null) ? ($this->isTruthy($garageRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['has_garage'] = null; }

        try {
            $carportRaw = $this->getMeta($meta, 'carport_needed');
            $d['has_carport'] = ($carportRaw !== null) ? ($this->isTruthy($carportRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['has_carport'] = null; }

        try {
            $storageRaw = $this->getMeta($meta, 'storage_space');
            $d['has_storage'] = ($storageRaw !== null) ? ($this->isTruthy($storageRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['has_storage'] = null; }

        try {
            $petsRaw = $this->getMeta($meta, 'pets');
            $d['pets_allowed'] = ($petsRaw !== null) ? ($this->isTruthy($petsRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['pets_allowed'] = null; }

        try {
            $communityRaw = $this->getMeta($meta, 'leasing_55_plus');
            $d['is_55_plus'] = ($communityRaw !== null) ? ($this->isTruthy($communityRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['is_55_plus'] = null; }

        // is_commercial — deterministic keyword classification derived exclusively from
        // explicit listing category terminology in structured enum fields (property_type,
        // leasing_space). This is NOT AI classification, behavioral inference, probabilistic
        // categorization, or demographic inference. A match against the keyword list means
        // the listing's own stated category contains a commercial-use term; nothing more.
        // Source fields: property_type meta key, leasing_space meta key.
        try {
            $propType = strtolower((string) ($d['property_type'] ?? ''));
            $leasingSpace = strtolower((string) ($this->getMeta($meta, 'leasing_space') ?? ''));
            if ($propType !== '' || $leasingSpace !== '') {
                $commercialKeywords = ['commercial', 'office', 'retail', 'industrial', 'warehouse', 'mixed'];
                $isCommercial = false;
                foreach ($commercialKeywords as $kw) {
                    if (strpos($propType, $kw) !== false || strpos($leasingSpace, $kw) !== false) {
                        $isCommercial = true;
                        break;
                    }
                }
                $d['is_commercial'] = $isCommercial ? 'yes' : 'no';
            } else {
                $d['is_commercial'] = null;
            }
        } catch (\Throwable $e) { $d['is_commercial'] = null; }

        // Smoking policy — determined by presence of a non-null `restrictions` meta key.
        // The `restrictions` field is a structured select (not free text); only presence is
        // recorded here to avoid storing raw user-authored text in DNA payloads.
        // Source field: restrictions (meta key, both seller and landlord listings).
        try {
            $restrictionsRaw = $this->getMeta($meta, 'restrictions');
            $d['smoking_policy_specified'] = ($restrictionsRaw !== null) ? 'yes' : null;
        } catch (\Throwable $e) { $d['smoking_policy_specified'] = null; }

        // HOA indicator — for seller listings, `hoa_association` is a native column on
        // PropertyAuction. For landlord listings, no equivalent native column exists so
        // the dimension is sourced from a meta key with the same name if present.
        // Source fields: hoa_association (native column, seller); hoa_association (meta, landlord).
        try {
            if ($listingType === 'seller' && isset($listing->hoa_association)) {
                $hoaVal = $listing->hoa_association;
                $d['has_hoa'] = ($hoaVal !== null) ? ($hoaVal ? 'yes' : 'no') : null;
            } else {
                $hoaMetaRaw = $this->getMeta($meta, 'hoa_association');
                $d['has_hoa'] = ($hoaMetaRaw !== null) ? ($this->isTruthy($hoaMetaRaw) ? 'yes' : 'no') : null;
            }
        } catch (\Throwable $e) { $d['has_hoa'] = null; }

        // Furnishing indicator — `tenant_require` meta key indicates whether the listing
        // includes furnished/unfurnished terms. Present and non-null = furnishing terms specified.
        // Source field: tenant_require (meta key, both seller and landlord).
        try {
            $furnishingRaw = $this->getMeta($meta, 'tenant_require');
            $d['furnishing_indicator'] = ($furnishingRaw !== null) ? 'specified' : null;
        } catch (\Throwable $e) { $d['furnishing_indicator'] = null; }

        // Move-in timing — sourced from occupancy/vacancy meta keys.
        // `occupied_until` (landlord) or `target_closing_date` (seller/landlord) indicate
        // when the property will be available. Present and non-null = timing specified.
        // Source fields: occupied_until (meta), target_closing_date (meta).
        try {
            $occupiedUntil = $this->getMeta($meta, 'occupied_until');
            $targetClosing = $this->getMeta($meta, 'target_closing_date');
            $moveInRaw = $occupiedUntil ?? $targetClosing;
            $d['move_in_timing'] = ($moveInRaw !== null) ? 'specified' : null;
        } catch (\Throwable $e) { $d['move_in_timing'] = null; }

        try { $d['occupant_status'] = $this->getMeta($meta, 'occupant_status'); } catch (\Throwable $e) { $d['occupant_status'] = null; }
        try { $d['lease_length_flexibility'] = $this->getMeta($meta, 'desired_lease_length'); } catch (\Throwable $e) { $d['lease_length_flexibility'] = null; }

        try {
            $leaseOptionPrice = $this->getMeta($meta, 'lease_option_price');
            $leaseOptionAgreement = $this->getMeta($meta, 'interested_lease_option_agreement');
            $d['has_lease_option'] = ($leaseOptionPrice !== null || $this->isTruthy((string) $leaseOptionAgreement)) ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_lease_option'] = null; }

        try {
            $leasePurchasePrice = $this->getMeta($meta, 'lease_purchase_price');
            $d['has_lease_purchase'] = ($leasePurchasePrice !== null) ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_lease_purchase'] = null; }

        try {
            $sfType = $this->getMeta($meta, 'seller_financing_type');
            $d['has_seller_financing'] = ($sfType !== null && $sfType !== 'none') ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_seller_financing'] = null; }

        try {
            $assumableTerms = $this->getMeta($meta, 'assumable_terms');
            $d['has_assumable_loan'] = ($assumableTerms !== null && $assumableTerms !== 'none') ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_assumable_loan'] = null; }

        try { $d['sale_provision_type'] = $this->getMeta($meta, 'sale_provision'); } catch (\Throwable $e) { $d['sale_provision_type'] = null; }
        try { $d['offered_financing_types'] = $this->getMeta($meta, 'offered_financing'); } catch (\Throwable $e) { $d['offered_financing_types'] = null; }

        try {
            $interestedRaw = $this->getMeta($meta, 'interested_in_selling');
            $d['interested_in_selling'] = ($interestedRaw !== null) ? ($this->isTruthy($interestedRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['interested_in_selling'] = null; }

        try {
            $videoLink = $this->getMeta($meta, 'video_link');
            $d['has_video_tour'] = ($videoLink !== null && strlen(trim($videoLink)) > 0) ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_video_tour'] = null; }

        try { $d['view_preference'] = $this->getMeta($meta, 'view_preference'); } catch (\Throwable $e) { $d['view_preference'] = null; }

        return $d;
    }

    private function isTruthy(string $value): bool
    {
        $val = strtolower(trim($value));
        return in_array($val, ['yes', '1', 'true', 'on'], true);
    }

    private function buildArchetypeTags(array $dimensions): array
    {
        $tags = [];

        if (!empty($dimensions['property_type'])) {
            $tags[] = 'type:' . $dimensions['property_type'];
        }
        if (!empty($dimensions['property_style'])) {
            $tags[] = 'style:' . $dimensions['property_style'];
        }
        if (!empty($dimensions['property_condition'])) {
            $tags[] = 'condition:' . $dimensions['property_condition'];
        }
        if (($dimensions['has_pool'] ?? null) === 'yes') {
            $tags[] = 'amenity:pool';
        }
        if (($dimensions['has_garage'] ?? null) === 'yes') {
            $tags[] = 'parking:garage';
        }
        if (($dimensions['has_carport'] ?? null) === 'yes') {
            $tags[] = 'parking:carport';
        }
        if (($dimensions['has_storage'] ?? null) === 'yes') {
            $tags[] = 'feature:storage';
        }
        if (($dimensions['pets_allowed'] ?? null) === 'yes') {
            $tags[] = 'policy:pets-allowed';
        }
        if (($dimensions['is_55_plus'] ?? null) === 'yes') {
            $tags[] = 'community:55-plus';
        }
        if (($dimensions['is_commercial'] ?? null) === 'yes') {
            $tags[] = 'use:commercial';
        }
        if (($dimensions['has_hoa'] ?? null) === 'yes') {
            $tags[] = 'governance:hoa';
        }
        if (($dimensions['smoking_policy_specified'] ?? null) === 'yes') {
            $tags[] = 'policy:restrictions-specified';
        }
        if (($dimensions['furnishing_indicator'] ?? null) === 'specified') {
            $tags[] = 'feature:furnishing-terms-specified';
        }
        if (($dimensions['move_in_timing'] ?? null) === 'specified') {
            $tags[] = 'timing:move-in-specified';
        }
        if (($dimensions['has_lease_option'] ?? null) === 'yes') {
            $tags[] = 'structure:lease-option';
        }
        if (($dimensions['has_lease_purchase'] ?? null) === 'yes') {
            $tags[] = 'structure:lease-purchase';
        }
        if (($dimensions['has_seller_financing'] ?? null) === 'yes') {
            $tags[] = 'financing:seller-financed';
        }
        if (($dimensions['has_assumable_loan'] ?? null) === 'yes') {
            $tags[] = 'financing:assumable';
        }
        if (($dimensions['has_video_tour'] ?? null) === 'yes') {
            $tags[] = 'marketing:video-tour';
        }

        return $tags;
    }

    private function buildMarketingHooks(array $dimensions): array
    {
        $hooks = [];

        foreach ([
            'property_type'            => 'property_type',
            'bedrooms'                 => 'bedrooms',
            'bathrooms'                => 'bathrooms',
            'minimum_sqft'             => 'minimum_sqft',
            'total_acreage'            => 'total_acreage',
            'occupant_status'          => 'occupant_status',
            'lease_length_flexibility' => 'lease_length',
            'sale_provision_type'      => 'sale_provision',
            'offered_financing_types'  => 'financing_types',
            'view_preference'          => 'view',
        ] as $dimKey => $hookKey) {
            if (!empty($dimensions[$dimKey])) {
                $hooks[] = ['trait' => $hookKey, 'value' => $dimensions[$dimKey]];
            }
        }

        return $hooks;
    }

    private function computeCompleteness(array $dimensions): float
    {
        $total = count(self::DIMENSION_SLOTS);
        $filled = 0;
        foreach (self::DIMENSION_SLOTS as $slot) {
            if (isset($dimensions[$slot]) && $dimensions[$slot] !== null && $dimensions[$slot] !== '') {
                $filled++;
            }
        }
        return $total > 0 ? round(($filled / $total) * 100, 2) : 0.0;
    }

    /**
     * Computes per-group dimension coverage metrics.
     *
     * IMPORTANT — these are COVERAGE METRICS, not quality or ranking scores:
     * Each value represents the fraction of relevant dimension slots that were
     * populated (non-null) for that group: (filled slots / total slots) × 100.
     * They measure data completeness only. They must never be interpreted as
     * quality, desirability, ranking, recommendation, or valuation scores, and
     * must never be used to rank listings against each other or assess
     * occupant/buyer suitability.
     *
     * occupant_qualification_score — despite the column name (schema-defined,
     * not renamed in Phase E), this is a COMPLETENESS COVERAGE METRIC for the
     * occupant-policy dimension group (55+ flag, pet policy, occupancy status,
     * restrictions, furnishing indicator). It is NOT a tenant screening score,
     * tenant qualification assessment, risk evaluation, or approval metric.
     * It must not be used or referenced for any occupant screening purpose.
     */
    private function computeScores(array $dimensions): array
    {
        $physicalFields      = ['property_type', 'property_style', 'property_condition', 'bedrooms', 'bathrooms', 'minimum_sqft', 'total_acreage', 'has_pool', 'has_garage', 'has_carport', 'has_storage'];
        $financialFields     = ['offered_financing_types', 'has_seller_financing', 'has_assumable_loan', 'has_lease_option', 'has_lease_purchase', 'sale_provision_type'];
        $flexibilityFields   = ['has_lease_option', 'has_lease_purchase', 'has_seller_financing', 'lease_length_flexibility', 'sale_provision_type'];
        $occupantFields      = ['is_55_plus', 'pets_allowed', 'occupant_status', 'smoking_policy_specified', 'furnishing_indicator'];
        $marketingFields     = ['has_video_tour', 'has_pool', 'view_preference', 'has_garage'];
        $commercialFields    = ['is_commercial'];

        return [
            'physical_score'               => $this->coverageMetric($dimensions, $physicalFields),
            'financial_score'              => $this->coverageMetric($dimensions, $financialFields),
            'flexibility_score'            => $this->coverageMetric($dimensions, $flexibilityFields),
            'occupant_qualification_score' => $this->coverageMetric($dimensions, $occupantFields),
            'marketing_score'              => $this->coverageMetric($dimensions, $marketingFields),
            'commercial_score'             => $this->coverageMetric($dimensions, $commercialFields),
        ];
    }

    /**
     * Returns the dimension coverage metric for a field group:
     * (count of non-null populated slots / total slots) × 100.
     * This is a field-presence completeness measure — not a weighted formula,
     * not a quality assessment, and not a ranking or valuation computation.
     */
    private function coverageMetric(array $dimensions, array $fields): ?float
    {
        $total = count($fields);
        if ($total === 0) {
            return null;
        }
        $filled = 0;
        foreach ($fields as $f) {
            if (isset($dimensions[$f]) && $dimensions[$f] !== null && $dimensions[$f] !== '') {
                $filled++;
            }
        }
        return round(($filled / $total) * 100, 2);
    }

    /**
     * Acquires a per-listing mutual exclusion lock before the persist transaction reads
     * or writes, so concurrent jobs cannot race on first-ever insert (the case where
     * lockForUpdate alone has no rows to lock).
     *
     * On PostgreSQL (the project's primary database): uses pg_advisory_xact_lock, which
     * is transaction-scoped and released automatically on commit/rollback.
     * On other drivers: no advisory lock; relies on the wrapping DB::transaction() for
     * best-effort ordering. Duplicate-version violations on non-PostgreSQL drivers are
     * caught by the job's top-level try/catch and logged without rethrowing.
     */
    private function acquireListingLock(string $listingType, int $listingId): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $advisoryKey = crc32('pdna:' . $listingType . ':' . $listingId);
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$advisoryKey]);
        }
    }

    /**
     * Persist a new profile version using append-only semantics.
     *
     * Idempotency contract (satisfies governance rule #13):
     * - Acquires a per-listing mutual exclusion lock (driver-conditional, see
     *   acquireListingLock) before any read or write to prevent concurrent duplicate
     *   active rows even when no prior profile row exists.
     * - Queries the highest-versioned row regardless of archived_at, so a partial prior
     *   run (archived but not yet created) still produces the correct next version.
     * - Wraps lock acquisition, archive, and create in a single DB transaction;
     *   on PostgreSQL the advisory lock is released automatically on commit/rollback.
     */
    private function persist(string $listingType, int $listingId, $sourceUpdatedAt, array $payload): void
    {
        DB::transaction(function () use ($listingType, $listingId, $sourceUpdatedAt, $payload) {
            $this->acquireListingLock($listingType, $listingId);

            $prior = PropertyDnaProfile::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->orderByDesc('version')
                ->first();

            $newVersion = 1;

            if ($prior) {
                if (is_null($prior->archived_at)) {
                    $prior->archived_at = now();
                    $prior->save();
                }
                $newVersion = $prior->version + 1;
            }

            PropertyDnaProfile::create(array_merge($payload, [
                'listing_type'              => $listingType,
                'listing_id'                => $listingId,
                'version'                   => $newVersion,
                'source_listing_updated_at' => $sourceUpdatedAt,
                'computed_at'               => now(),
                'archived_at'               => null,
            ]));
        });
    }
}
