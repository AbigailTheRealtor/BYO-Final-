<?php

namespace App\Services\Dna;

use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\TenantCriteriaAuction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyerTenantDnaGenerator
{
    /**
     * Full list of mapped preference dimension slots for buyer/tenant profiles.
     * Every slot present here is included in the preference_completeness calculation.
     *
     * Intentionally skipped dimensions with documented reasons:
     * - "commute_preferences": no structured commute source field exists in the current
     *   schema for buyer or tenant listings. commute_polygon_cache is reserved for a
     *   future phase. Skipped per governance rule — do not bend the rule.
     */
    private const DIMENSION_SLOTS = [
        'property_type_preference',
        'property_style_preference',
        'property_condition_preference',
        'bedroom_preference',
        'bathroom_preference',
        'minimum_sqft_preference',
        'budget',
        'has_preapproval',
        'financing_preference',
        'down_payment_type',
        'has_seller_financing_interest',
        'has_assumable_loan_interest',
        'has_lease_option_interest',
        'has_lease_purchase_interest',
        'has_pets',
        'is_55_plus_preference',
        'pool_preference',
        'garage_preference',
        'carport_preference',
        'desired_lease_length',
        'occupant_status_preference',
        'sale_provision_interest',
        'view_preference',
        'timeline_flexibility',
        'smoking_preference_specified',
        'hoa_preference_specified',
    ];

    public function __construct(private BuyerAvatarProfileService $avatarProfileService)
    {
    }

    public function generate(string $listingType, int $listingId): void
    {
        if ($listingType === 'buyer') {
            $listing = BuyerCriteriaAuction::with('meta')->find($listingId);
        } elseif ($listingType === 'tenant') {
            $listing = TenantCriteriaAuction::with('meta')->find($listingId);
        } else {
            Log::warning("BuyerTenantDnaGenerator: unknown listing_type [{$listingType}] for id [{$listingId}]");
            return;
        }

        if (!$listing) {
            Log::warning("BuyerTenantDnaGenerator: listing not found — type={$listingType} id={$listingId}");
            return;
        }

        $meta = $this->flattenMeta($listing);

        $dimensions = $this->mapDimensions($meta);
        $lifestyleTags = $this->buildLifestyleTags($dimensions);
        $dealBreakerFlags = $this->buildDealBreakerFlags($dimensions);
        $completeness = $this->computeCompleteness($dimensions);

        $profile = $this->persist($listingType, $listingId, $listing->updated_at, [
            'preference_completeness' => $completeness,
            'lifestyle_tags'          => $lifestyleTags,
            'deal_breaker_flags'      => $dealBreakerFlags,
            'archetype_label'         => null,
            'commute_polygon_cache'   => null,
        ]);

        if ($listingType === 'buyer' && $profile !== null) {
            $this->avatarProfileService->compute($profile);
        }
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

    private function mapDimensions(array $meta): array
    {
        $d = [];

        try { $d['property_type_preference'] = $this->getMeta($meta, 'property_type'); } catch (\Throwable $e) { $d['property_type_preference'] = null; }
        try { $d['property_style_preference'] = $this->getMeta($meta, 'property_items'); } catch (\Throwable $e) { $d['property_style_preference'] = null; }
        try { $d['property_condition_preference'] = $this->getMeta($meta, 'condition_prop_buyer') ?? $this->getMeta($meta, 'condition_prop'); } catch (\Throwable $e) { $d['property_condition_preference'] = null; }
        try { $d['bedroom_preference'] = $this->getMeta($meta, 'bedrooms'); } catch (\Throwable $e) { $d['bedroom_preference'] = null; }
        try { $d['bathroom_preference'] = $this->getMeta($meta, 'bathrooms'); } catch (\Throwable $e) { $d['bathroom_preference'] = null; }
        try { $d['minimum_sqft_preference'] = $this->getMeta($meta, 'minimum_heated_square'); } catch (\Throwable $e) { $d['minimum_sqft_preference'] = null; }

        try {
            $budget = $this->getMeta($meta, 'maximum_budget') ?? $this->getMeta($meta, 'budget') ?? $this->getMeta($meta, 'desired_rental_amount');
            $d['budget'] = $budget;
        } catch (\Throwable $e) { $d['budget'] = null; }

        try {
            $preApproved = $this->getMeta($meta, 'pre_approved');
            $d['has_preapproval'] = ($preApproved !== null) ? ($this->isTruthy($preApproved) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['has_preapproval'] = null; }

        try { $d['financing_preference'] = $this->getMeta($meta, 'offered_financing'); } catch (\Throwable $e) { $d['financing_preference'] = null; }
        try { $d['down_payment_type'] = $this->getMeta($meta, 'down_payment_type'); } catch (\Throwable $e) { $d['down_payment_type'] = null; }

        try {
            $sfType = $this->getMeta($meta, 'seller_financing_type');
            $d['has_seller_financing_interest'] = ($sfType !== null && $sfType !== 'none') ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_seller_financing_interest'] = null; }

        try {
            $assumableTerms = $this->getMeta($meta, 'assumable_terms');
            $d['has_assumable_loan_interest'] = ($assumableTerms !== null && $assumableTerms !== 'none') ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_assumable_loan_interest'] = null; }

        try {
            $leaseOptionRaw = $this->getMeta($meta, 'interested_lease_option') ?? $this->getMeta($meta, 'interested_lease_option_agreement');
            $d['has_lease_option_interest'] = ($leaseOptionRaw !== null && $this->isTruthy($leaseOptionRaw)) ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_lease_option_interest'] = null; }

        try {
            $leasePurchaseRaw = $this->getMeta($meta, 'lease_purchase_price');
            $d['has_lease_purchase_interest'] = ($leasePurchaseRaw !== null) ? 'yes' : null;
        } catch (\Throwable $e) { $d['has_lease_purchase_interest'] = null; }

        try {
            $petsRaw = $this->getMeta($meta, 'pets');
            $d['has_pets'] = ($petsRaw !== null) ? ($this->isTruthy($petsRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['has_pets'] = null; }

        try {
            $communityRaw = $this->getMeta($meta, 'leasing_55_plus');
            $d['is_55_plus_preference'] = ($communityRaw !== null) ? ($this->isTruthy($communityRaw) ? 'yes' : 'no') : null;
        } catch (\Throwable $e) { $d['is_55_plus_preference'] = null; }

        try {
            $poolRaw = $this->getMeta($meta, 'pool_needed');
            $d['pool_preference'] = ($poolRaw !== null) ? ($this->isTruthy($poolRaw) ? 'required' : 'not-required') : null;
        } catch (\Throwable $e) { $d['pool_preference'] = null; }

        try {
            $garageRaw = $this->getMeta($meta, 'garage_needed');
            $d['garage_preference'] = ($garageRaw !== null) ? ($this->isTruthy($garageRaw) ? 'required' : 'not-required') : null;
        } catch (\Throwable $e) { $d['garage_preference'] = null; }

        try {
            $carportRaw = $this->getMeta($meta, 'carport_needed');
            $d['carport_preference'] = ($carportRaw !== null) ? ($this->isTruthy($carportRaw) ? 'required' : 'not-required') : null;
        } catch (\Throwable $e) { $d['carport_preference'] = null; }

        try { $d['desired_lease_length'] = $this->getMeta($meta, 'desired_lease_length'); } catch (\Throwable $e) { $d['desired_lease_length'] = null; }
        try { $d['occupant_status_preference'] = $this->getMeta($meta, 'occupant_status'); } catch (\Throwable $e) { $d['occupant_status_preference'] = null; }
        try { $d['sale_provision_interest'] = $this->getMeta($meta, 'sale_provision'); } catch (\Throwable $e) { $d['sale_provision_interest'] = null; }
        try { $d['view_preference'] = $this->getMeta($meta, 'view_preference'); } catch (\Throwable $e) { $d['view_preference'] = null; }

        try {
            $expirationDate = $this->getMeta($meta, 'expiration_date');
            $listingDate = $this->getMeta($meta, 'listing_date');
            $d['timeline_flexibility'] = ($expirationDate !== null || $listingDate !== null) ? 'specified' : null;
        } catch (\Throwable $e) { $d['timeline_flexibility'] = null; }

        // Smoking preference — sourced from `restrictions` meta key (structured select field).
        // Only presence is recorded; raw restriction text must never be stored in DNA payloads.
        // Source field: restrictions (meta key, both buyer and tenant listings).
        try {
            $restrictionsRaw = $this->getMeta($meta, 'restrictions');
            $d['smoking_preference_specified'] = ($restrictionsRaw !== null) ? 'yes' : null;
        } catch (\Throwable $e) { $d['smoking_preference_specified'] = null; }

        // HOA preference — SKIPPED. No dedicated HOA-specific source field exists for buyer
        // or tenant listings in the current schema. Proxy fields (leasing_55_plus,
        // non_negotiable_amenities) were evaluated but rejected: they do not reliably
        // represent HOA preference and would produce systematic false positives.
        // Per governance rule: skip this dimension rather than bend the mapping.
        // Future: implement when a dedicated hoa_preference or hoa_tolerance field is added
        // to buyer/tenant listing forms.
        $d['hoa_preference_specified'] = null;

        return $d;
    }

    private function isTruthy(string $value): bool
    {
        $val = strtolower(trim($value));
        return in_array($val, ['yes', '1', 'true', 'on'], true);
    }

    private function buildLifestyleTags(array $dimensions): array
    {
        $tags = [];

        if (!empty($dimensions['property_type_preference'])) {
            $tags[] = 'prefers-type:' . $dimensions['property_type_preference'];
        }
        if (!empty($dimensions['property_condition_preference'])) {
            $tags[] = 'prefers-condition:' . $dimensions['property_condition_preference'];
        }
        if (($dimensions['has_pets'] ?? null) === 'yes') {
            $tags[] = 'has-pets';
        }
        if (($dimensions['is_55_plus_preference'] ?? null) === 'yes') {
            $tags[] = 'seeks:55-plus-community';
        }
        if (($dimensions['pool_preference'] ?? null) === 'required') {
            $tags[] = 'requires:pool';
        }
        if (($dimensions['garage_preference'] ?? null) === 'required') {
            $tags[] = 'requires:garage';
        }
        if (($dimensions['carport_preference'] ?? null) === 'required') {
            $tags[] = 'requires:carport';
        }
        if (($dimensions['has_lease_option_interest'] ?? null) === 'yes') {
            $tags[] = 'open-to:lease-option';
        }
        if (($dimensions['has_lease_purchase_interest'] ?? null) === 'yes') {
            $tags[] = 'open-to:lease-purchase';
        }
        if (($dimensions['has_seller_financing_interest'] ?? null) === 'yes') {
            $tags[] = 'open-to:seller-financing';
        }
        if (($dimensions['has_assumable_loan_interest'] ?? null) === 'yes') {
            $tags[] = 'open-to:assumable-loan';
        }
        if (($dimensions['has_preapproval'] ?? null) === 'yes') {
            $tags[] = 'financial:pre-approved';
        }
        if (($dimensions['smoking_preference_specified'] ?? null) === 'yes') {
            $tags[] = 'preference:restrictions-specified';
        }
        if (($dimensions['hoa_preference_specified'] ?? null) === 'yes') {
            $tags[] = 'preference:hoa-community-aware';
        }

        return $tags;
    }

    private function buildDealBreakerFlags(array $dimensions): array
    {
        $flags = [];

        if (($dimensions['is_55_plus_preference'] ?? null) === 'yes') {
            $flags[] = ['flag' => '55_plus_required', 'source_field' => 'leasing_55_plus'];
        }
        if (($dimensions['pool_preference'] ?? null) === 'required') {
            $flags[] = ['flag' => 'pool_required', 'source_field' => 'pool_needed'];
        }
        if (($dimensions['garage_preference'] ?? null) === 'required') {
            $flags[] = ['flag' => 'garage_required', 'source_field' => 'garage_needed'];
        }
        if (($dimensions['carport_preference'] ?? null) === 'required') {
            $flags[] = ['flag' => 'carport_required', 'source_field' => 'carport_needed'];
        }
        if (!empty($dimensions['bedroom_preference'])) {
            $flags[] = ['flag' => 'minimum_bedrooms_specified', 'source_field' => 'bedrooms', 'value' => $dimensions['bedroom_preference']];
        }
        if (!empty($dimensions['bathroom_preference'])) {
            $flags[] = ['flag' => 'minimum_bathrooms_specified', 'source_field' => 'bathrooms', 'value' => $dimensions['bathroom_preference']];
        }
        if (!empty($dimensions['minimum_sqft_preference'])) {
            $flags[] = ['flag' => 'minimum_sqft_specified', 'source_field' => 'minimum_heated_square', 'value' => $dimensions['minimum_sqft_preference']];
        }
        if (!empty($dimensions['budget'])) {
            $flags[] = ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget|budget|desired_rental_amount', 'value' => $dimensions['budget']];
        }

        return $flags;
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
            $advisoryKey = crc32('btdna:' . $listingType . ':' . $listingId);
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$advisoryKey]);
        }
    }

    /**
     * Persist a new preference profile version using append-only semantics.
     *
     * Returns the newly created BuyerTenantDnaProfile instance so the caller can
     * dispatch downstream services (e.g. BuyerAvatarProfileService) outside the
     * transaction boundary.
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
    private function persist(string $listingType, int $listingId, $sourceUpdatedAt, array $payload): ?BuyerTenantDnaProfile
    {
        $created = null;

        DB::transaction(function () use ($listingType, $listingId, $sourceUpdatedAt, $payload, &$created) {
            $this->acquireListingLock($listingType, $listingId);

            $prior = BuyerTenantDnaProfile::where('listing_type', $listingType)
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

            $created = BuyerTenantDnaProfile::create(array_merge($payload, [
                'listing_type'              => $listingType,
                'listing_id'                => $listingId,
                'version'                   => $newVersion,
                'source_listing_updated_at' => $sourceUpdatedAt,
                'computed_at'               => now(),
                'archived_at'               => null,
            ]));
        });

        return $created;
    }
}
