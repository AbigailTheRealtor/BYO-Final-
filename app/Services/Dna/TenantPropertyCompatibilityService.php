<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;

/**
 * TenantPropertyCompatibilityService — Tenant ↔ Property Compatibility Engine V1
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC, READ-ONLY INTERPRETATION LAYER. It compares one
 * tenant DNA profile against one landlord/property DNA profile and returns a neutral
 * compatibility report. All logic is rule-based and in-memory only.
 *
 * This service MUST NEVER:
 *   - Read from, write to, or modify any database table, model, or connection.
 *   - Call any AI, OpenAI, language model, embedding, ML, or external HTTP service.
 *   - Recommend, rank, endorse, or preference-order any listing, tenant, or agent.
 *   - Express or imply whether a tenant should or should not lease a property.
 *   - Express or imply whether a property is a good, bad, suitable, or ideal fit.
 *   - Produce any numeric match score, desirability indicator, or probability estimate.
 *   - Infer or output any protected-class characteristic (age, family status, disability,
 *     race, religion, marital status, national origin).
 *   - Modify any schema, add routes, controllers, or UI.
 *   - Persist compatibility results to any database table.
 *
 * Every `reason` string in signal entries must describe what the signals show, not
 * what the tenant or platform should do with that information.
 *
 * Output is fully deterministic and reproducible. Same inputs always produce same output.
 * Each dimension is isolated in its own try/catch so a single failed dimension never
 * aborts the full run.
 * ==================================================================================
 */
class TenantPropertyCompatibilityService
{
    /**
     * Generate a compatibility report comparing a tenant DNA profile against a
     * landlord/property DNA profile.
     *
     * Output contract — always returns exactly these keys:
     *   success                     bool
     *   status                      'generated' | 'insufficient_data' | 'failed'
     *   tenant_listing_id           int
     *   property_listing_id         int
     *   compatibility_type          'tenant_property'
     *   aligned_signals             array  — signal entries where tenant and property signals agree
     *   conflicting_signals         array  — signal entries where a deterministic conflict exists
     *   unresolved_signals          array  — signal entries where one or both sides have no signal
     *   tenant_avatar_context       array|null
     *   property_personality_context array|null
     *   location_context            array|null
     *   missing_inputs              array
     *   error                       string|null
     *
     * Signal entry shape (aligned / conflicting):
     *   ['dimension' => string, 'tenant_signal' => string, 'property_signal' => string, 'reason' => string]
     *
     * Signal entry shape (unresolved):
     *   ['dimension' => string, 'missing_side' => string, 'reason' => string]
     *
     * @param  BuyerTenantDnaProfile $tenantProfile            Demand-side DNA profile (listing_type = 'tenant')
     * @param  PropertyDnaProfile    $propertyProfile          Supply-side DNA profile (listing_type = 'landlord')
     * @param  array                 $tenantAvatarContext       Optional tenant avatar output (passed through)
     * @param  array                 $propertyPersonalityContext Optional property personality output (passed through)
     * @param  array                 $locationContext           Optional Location DNA summary (coastal_features, etc.)
     * @return array
     */
    public function generate(
        BuyerTenantDnaProfile $tenantProfile,
        PropertyDnaProfile    $propertyProfile,
        array $tenantAvatarContext       = [],
        array $propertyPersonalityContext = [],
        array $locationContext           = []
    ): array {
        $stub = [
            'success'                      => false,
            'status'                       => 'insufficient_data',
            'tenant_listing_id'            => (int) ($tenantProfile->listing_id ?? 0),
            'property_listing_id'          => (int) ($propertyProfile->listing_id ?? 0),
            'compatibility_type'           => 'tenant_property',
            'aligned_signals'              => [],
            'conflicting_signals'          => [],
            'unresolved_signals'           => [],
            'tenant_avatar_context'        => !empty($tenantAvatarContext) ? $tenantAvatarContext : null,
            'property_personality_context' => !empty($propertyPersonalityContext) ? $propertyPersonalityContext : null,
            'location_context'             => !empty($locationContext) ? $locationContext : null,
            'missing_inputs'               => [],
            'error'                        => null,
        ];

        // ---- Insufficient-data guard ----
        $missingInputs = $this->checkInsufficientData($tenantProfile, $propertyProfile, $propertyPersonalityContext);
        if (!empty($missingInputs)) {
            $stub['missing_inputs'] = $missingInputs;
            return $stub;
        }

        try {
            $tenantTags    = (array) ($tenantProfile->lifestyle_tags     ?? []);
            $tenantFlags   = (array) ($tenantProfile->deal_breaker_flags ?? []);
            $propertyTags  = (array) ($propertyProfile->ai_buyer_archetype_tags ?? []);
            $propertyHooks = (array) ($propertyProfile->ai_marketing_hooks      ?? []);

            $aligned     = [];
            $conflicting = [];
            $unresolved  = [];

            // Run each dimension and distribute results
            $this->distributeSignals(
                $this->evaluatePropertyTypeAlignment($tenantTags, $propertyTags),
                $aligned, $conflicting, $unresolved
            );

            $this->distributeSignals(
                $this->evaluateLeaseStructureAlignment($tenantTags, $propertyTags),
                $aligned, $conflicting, $unresolved
            );

            $this->distributeSignals(
                $this->evaluatePetAlignment($tenantTags, $tenantFlags, $propertyTags),
                $aligned, $conflicting, $unresolved
            );

            $this->distributeSignals(
                $this->evaluateAmenityAlignment($tenantTags, $tenantFlags, $propertyTags),
                $aligned, $conflicting, $unresolved
            );

            $this->distributeSignals(
                $this->evaluateCommercialAlignment($tenantTags, $tenantFlags, $propertyTags),
                $aligned, $conflicting, $unresolved
            );

            $this->distributeSignals(
                $this->evaluateWaterfrontLifestyleAlignment($tenantTags, $propertyTags, $propertyHooks),
                $aligned, $conflicting, $unresolved
            );

            $this->distributeSignals(
                $this->evaluateLocationAlignment($tenantTags, $locationContext),
                $aligned, $conflicting, $unresolved
            );

            return array_merge($stub, [
                'success'                      => true,
                'status'                       => 'generated',
                'aligned_signals'              => $aligned,
                'conflicting_signals'          => $conflicting,
                'unresolved_signals'           => $unresolved,
                'tenant_avatar_context'        => !empty($tenantAvatarContext) ? $tenantAvatarContext : null,
                'property_personality_context' => !empty($propertyPersonalityContext) ? $propertyPersonalityContext : null,
                'location_context'             => !empty($locationContext) ? $locationContext : null,
                'missing_inputs'               => [],
                'error'                        => null,
            ]);
        } catch (\Throwable $e) {
            return array_merge($stub, [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Insufficient-data guard
    // -------------------------------------------------------------------------

    /**
     * Validate the four guard conditions and return a list of missing input
     * descriptions. An empty return means all guards passed.
     *
     * Guard conditions:
     *   1. Tenant profile listing_type must be 'tenant'
     *   2. Property profile listing_type must be 'landlord'
     *   3. Tenant profile must have at least one lifestyle_tag or deal_breaker_flag
     *   4. Property profile must have at least one archetype tag, marketing hook,
     *      or a non-empty propertyPersonalityContext (caller-supplied personality output)
     */
    private function checkInsufficientData(
        BuyerTenantDnaProfile $tenantProfile,
        PropertyDnaProfile    $propertyProfile,
        array $propertyPersonalityContext = []
    ): array {
        $missing = [];

        if (($tenantProfile->listing_type ?? '') !== 'tenant') {
            $missing[] = 'tenant_listing_id: listing_type must be tenant';
        }

        if (($propertyProfile->listing_type ?? '') !== 'landlord') {
            $missing[] = 'property_listing_id: listing_type must be landlord';
        }

        $tenantTags  = (array) ($tenantProfile->lifestyle_tags     ?? []);
        $tenantFlags = (array) ($tenantProfile->deal_breaker_flags ?? []);
        if (empty($tenantTags) && empty($tenantFlags)) {
            $missing[] = 'tenant profile: no lifestyle_tags or deal_breaker_flags';
        }

        $propertyTags  = (array) ($propertyProfile->ai_buyer_archetype_tags ?? []);
        $propertyHooks = (array) ($propertyProfile->ai_marketing_hooks      ?? []);
        if (empty($propertyTags) && empty($propertyHooks) && empty($propertyPersonalityContext)) {
            $missing[] = 'property profile: no archetype tags, marketing hooks, or personality context';
        }

        return $missing;
    }

    // -------------------------------------------------------------------------
    // Signal distribution
    // -------------------------------------------------------------------------

    /**
     * Route an array of signal entries (each carrying a '_bucket' key of
     * 'aligned', 'conflicting', or 'unresolved') into the matching output arrays.
     * The '_bucket' key is stripped before appending.
     *
     * @param  array  $signals
     * @param  array  &$aligned
     * @param  array  &$conflicting
     * @param  array  &$unresolved
     */
    private function distributeSignals(
        array $signals,
        array &$aligned,
        array &$conflicting,
        array &$unresolved
    ): void {
        foreach ($signals as $entry) {
            $bucket = $entry['_bucket'] ?? 'unresolved';
            unset($entry['_bucket']);

            if ($bucket === 'aligned') {
                $aligned[] = $entry;
            } elseif ($bucket === 'conflicting') {
                $conflicting[] = $entry;
            } else {
                $unresolved[] = $entry;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function hasTag(array $tags, string $tag): bool
    {
        return in_array($tag, $tags, true);
    }

    private function extractTagValue(array $tags, string $prefix): ?string
    {
        foreach ($tags as $tag) {
            if (str_starts_with((string) $tag, $prefix)) {
                return substr((string) $tag, strlen($prefix));
            }
        }
        return null;
    }

    private function hasDealBreakerFlag(array $flags, string $flagName): bool
    {
        foreach ($flags as $flagRecord) {
            $flagRecord = (array) $flagRecord;
            if (($flagRecord['flag'] ?? '') === $flagName) {
                return true;
            }
        }
        return false;
    }

    private function hasHookTrait(array $hooks, string $trait): bool
    {
        foreach ($hooks as $hook) {
            if (!is_array($hook)) {
                continue;
            }
            if (strtolower((string) ($hook['trait'] ?? '')) === strtolower($trait)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build an aligned signal entry (with _bucket for routing).
     */
    private function aligned(string $dimension, string $tenantSignal, string $propertySignal, string $reason): array
    {
        return [
            '_bucket'        => 'aligned',
            'dimension'      => $dimension,
            'tenant_signal'  => $tenantSignal,
            'property_signal'=> $propertySignal,
            'reason'         => $reason,
        ];
    }

    /**
     * Build a conflicting signal entry (with _bucket for routing).
     */
    private function conflicting(string $dimension, string $tenantSignal, string $propertySignal, string $reason): array
    {
        return [
            '_bucket'        => 'conflicting',
            'dimension'      => $dimension,
            'tenant_signal'  => $tenantSignal,
            'property_signal'=> $propertySignal,
            'reason'         => $reason,
        ];
    }

    /**
     * Build an unresolved signal entry (with _bucket for routing).
     */
    private function unresolved(string $dimension, string $missingSide, string $reason): array
    {
        return [
            '_bucket'      => 'unresolved',
            'dimension'    => $dimension,
            'missing_side' => $missingSide,
            'reason'       => $reason,
        ];
    }

    // -------------------------------------------------------------------------
    // Dimension evaluation methods — each wrapped in try/catch
    // -------------------------------------------------------------------------

    /**
     * Property type alignment: Does the property's stated type match the tenant's type preference?
     *
     * Supply source: archetype tag `type:{value}`
     * Demand source: lifestyle tag `prefers-type:{value}`
     * Rule: both present and same value → aligned; both present and different value → conflicting;
     *       either absent → unresolved
     *
     * @return array  Signal entries (may be empty, or contain one entry)
     */
    private function evaluatePropertyTypeAlignment(array $tenantTags, array $propertyTags): array
    {
        try {
            $propertyType = $this->extractTagValue($propertyTags, 'type:');
            $tenantType   = $this->extractTagValue($tenantTags,   'prefers-type:');

            if ($propertyType === null && $tenantType === null) {
                return [$this->unresolved(
                    'property_type_alignment',
                    'both',
                    'Neither the property type tag nor the tenant property type preference tag is present.'
                )];
            }
            if ($propertyType === null) {
                return [$this->unresolved(
                    'property_type_alignment',
                    'property',
                    'The property profile does not carry a type tag; tenant preference signal is present.'
                )];
            }
            if ($tenantType === null) {
                return [$this->unresolved(
                    'property_type_alignment',
                    'tenant',
                    'The tenant profile does not carry a property type preference tag; property type signal is present.'
                )];
            }

            if (strtolower(trim($propertyType)) === strtolower(trim($tenantType))) {
                return [$this->aligned(
                    'property_type_alignment',
                    'prefers-type:' . $tenantType,
                    'type:' . $propertyType,
                    'The tenant\'s stated property type preference matches the property\'s stated type.'
                )];
            }

            return [$this->conflicting(
                'property_type_alignment',
                'prefers-type:' . $tenantType,
                'type:' . $propertyType,
                'The tenant\'s stated property type preference does not match the property\'s stated type.'
            )];
        } catch (\Throwable $e) {
            return [$this->unresolved('property_type_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }

    /**
     * Lease structure alignment: Do lease-option/lease-purchase availability and interest align?
     *
     * Supply source: archetype tags `structure:lease-option`, `structure:lease-purchase`
     * Demand source: lifestyle tags `open-to:lease-option`, `open-to:lease-purchase`
     * Rule: demand interested in lease structure that property provides → aligned;
     *       demand interested but property has no such structure → conflicting;
     *       neither side expresses interest → unresolved
     *
     * @return array  Signal entries
     */
    private function evaluateLeaseStructureAlignment(array $tenantTags, array $propertyTags): array
    {
        try {
            $propertyLeaseOption   = $this->hasTag($propertyTags, 'structure:lease-option');
            $propertyLeasePurchase = $this->hasTag($propertyTags, 'structure:lease-purchase');
            $tenantLeaseOption     = $this->hasTag($tenantTags,   'open-to:lease-option');
            $tenantLeasePurchase   = $this->hasTag($tenantTags,   'open-to:lease-purchase');

            $signals = [];

            if ($tenantLeaseOption) {
                if ($propertyLeaseOption) {
                    $signals[] = $this->aligned(
                        'lease_structure_alignment',
                        'open-to:lease-option',
                        'structure:lease-option',
                        'The tenant has expressed interest in a lease-option arrangement and the property offers it.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'lease_structure_alignment',
                        'open-to:lease-option',
                        'no structure:lease-option',
                        'The tenant has expressed interest in a lease-option arrangement but the property does not indicate this structure.'
                    );
                }
            }

            if ($tenantLeasePurchase) {
                if ($propertyLeasePurchase) {
                    $signals[] = $this->aligned(
                        'lease_structure_alignment',
                        'open-to:lease-purchase',
                        'structure:lease-purchase',
                        'The tenant has expressed interest in a lease-purchase arrangement and the property offers it.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'lease_structure_alignment',
                        'open-to:lease-purchase',
                        'no structure:lease-purchase',
                        'The tenant has expressed interest in a lease-purchase arrangement but the property does not indicate this structure.'
                    );
                }
            }

            if (empty($signals)) {
                $signals[] = $this->unresolved(
                    'lease_structure_alignment',
                    'tenant',
                    'The tenant has not expressed interest in lease-option or lease-purchase arrangements.'
                );
            }

            return $signals;
        } catch (\Throwable $e) {
            return [$this->unresolved('lease_structure_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }

    /**
     * Pet alignment: Does the property's pet policy match the tenant's pet status?
     *
     * Supply source: archetype tag `policy:pets-allowed`
     * Demand source: lifestyle tag `has-pets` or deal_breaker_flag `pet_required`
     * Rule: tenant has pets + property allows → aligned;
     *       tenant has pets + property does NOT indicate pets-allowed → conflicting;
     *       tenant has no pets signal → unresolved (no conflict regardless)
     *
     * @return array  Signal entries
     */
    private function evaluatePetAlignment(array $tenantTags, array $tenantFlags, array $propertyTags): array
    {
        try {
            $tenantHasPets     = $this->hasTag($tenantTags, 'has-pets')
                              || $this->hasDealBreakerFlag($tenantFlags, 'pet_required');
            $propertyAllowsPets = $this->hasTag($propertyTags, 'policy:pets-allowed');

            if (!$tenantHasPets) {
                return [$this->unresolved(
                    'pet_alignment',
                    'tenant',
                    'The tenant profile does not carry a pet signal; no pet policy comparison is possible.'
                )];
            }

            if ($propertyAllowsPets) {
                return [$this->aligned(
                    'pet_alignment',
                    'has-pets',
                    'policy:pets-allowed',
                    'The tenant\'s pet signal is present and the property indicates a pets-allowed policy.'
                )];
            }

            return [$this->conflicting(
                'pet_alignment',
                'has-pets',
                'no policy:pets-allowed',
                'The tenant\'s pet signal is present but the property does not indicate a pets-allowed policy.'
            )];
        } catch (\Throwable $e) {
            return [$this->unresolved('pet_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }

    /**
     * Amenity alignment: Do the tenant's amenity requirements match the property's amenity signals?
     *
     * Supply source: archetype tags `amenity:pool`, `parking:garage`, `amenity:garage`
     * Demand source: lifestyle tags `requires:pool`, `requires:garage`
     *                and deal_breaker_flags `pool_required`, `garage_required`
     * Rule: tenant requires amenity that property provides → aligned;
     *       tenant requires amenity that property lacks → conflicting;
     *       no tenant amenity requirements → unresolved
     *
     * @return array  Signal entries
     */
    private function evaluateAmenityAlignment(array $tenantTags, array $tenantFlags, array $propertyTags): array
    {
        try {
            $tenantRequiresPool   = $this->hasTag($tenantTags, 'requires:pool')
                                 || $this->hasDealBreakerFlag($tenantFlags, 'pool_required');
            $tenantRequiresGarage = $this->hasTag($tenantTags, 'requires:garage')
                                 || $this->hasDealBreakerFlag($tenantFlags, 'garage_required');

            $propertyHasPool   = $this->hasTag($propertyTags, 'amenity:pool');
            $propertyHasGarage = $this->hasTag($propertyTags, 'amenity:garage')
                              || $this->hasTag($propertyTags, 'parking:garage');

            $signals = [];

            if ($tenantRequiresPool) {
                if ($propertyHasPool) {
                    $signals[] = $this->aligned(
                        'amenity_alignment',
                        'requires:pool',
                        'amenity:pool',
                        'The tenant requires a pool and the property indicates a pool amenity.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'amenity_alignment',
                        'requires:pool',
                        'no amenity:pool',
                        'The tenant requires a pool but the property does not indicate a pool amenity.'
                    );
                }
            }

            if ($tenantRequiresGarage) {
                if ($propertyHasGarage) {
                    $signals[] = $this->aligned(
                        'amenity_alignment',
                        'requires:garage',
                        'amenity:garage',
                        'The tenant requires a garage and the property indicates garage availability.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'amenity_alignment',
                        'requires:garage',
                        'no amenity:garage',
                        'The tenant requires a garage but the property does not indicate garage availability.'
                    );
                }
            }

            if (empty($signals)) {
                $signals[] = $this->unresolved(
                    'amenity_alignment',
                    'tenant',
                    'The tenant profile carries no pool or garage requirement signals.'
                );
            }

            return $signals;
        } catch (\Throwable $e) {
            return [$this->unresolved('amenity_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }

    /**
     * Commercial alignment: Does the tenant's commercial interest align with the property's commercial use signal?
     *
     * Supply source: archetype tag `use:commercial`
     * Demand source: lifestyle tag `prefers-type:Commercial` or deal_breaker_flag `commercial_interest`
     * Rule: tenant has commercial interest + property has commercial tag → aligned;
     *       tenant has commercial interest + property has no commercial tag → conflicting;
     *       no commercial signal on either side → unresolved
     *
     * @return array  Signal entries
     */
    private function evaluateCommercialAlignment(array $tenantTags, array $tenantFlags, array $propertyTags): array
    {
        try {
            $tenantCommercial    = $this->hasDealBreakerFlag($tenantFlags, 'commercial_interest');
            if (!$tenantCommercial) {
                $typeValue = $this->extractTagValue($tenantTags, 'prefers-type:');
                if ($typeValue !== null && str_contains(strtolower($typeValue), 'commercial')) {
                    $tenantCommercial = true;
                }
            }

            $propertyCommercial = $this->hasTag($propertyTags, 'use:commercial');

            if (!$tenantCommercial && !$propertyCommercial) {
                return [$this->unresolved(
                    'commercial_alignment',
                    'both',
                    'Neither the tenant nor the property profile carries a commercial use signal.'
                )];
            }

            if (!$tenantCommercial) {
                return [$this->unresolved(
                    'commercial_alignment',
                    'tenant',
                    'The property carries a commercial use signal but the tenant profile does not indicate commercial interest.'
                )];
            }

            if ($propertyCommercial) {
                return [$this->aligned(
                    'commercial_alignment',
                    'commercial_interest',
                    'use:commercial',
                    'The tenant has indicated commercial interest and the property carries a commercial use signal.'
                )];
            }

            return [$this->conflicting(
                'commercial_alignment',
                'commercial_interest',
                'no use:commercial',
                'The tenant has indicated commercial interest but the property does not carry a commercial use signal.'
            )];
        } catch (\Throwable $e) {
            return [$this->unresolved('commercial_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }

    /**
     * Waterfront/lifestyle avatar-to-personality alignment:
     * Compares the tenant's waterfront lifestyle signal against the property's waterfront personality signals.
     *
     * Supply source: archetype tags `amenity:waterfront`, `feature:waterfront`;
     *                marketing hook trait `waterfront` or `coastal`
     * Demand source: lifestyle tag `prefers-type:Waterfront` or any tag containing 'waterfront'
     * Rule: tenant has waterfront signal + property has waterfront signal → aligned;
     *       tenant has waterfront signal + property lacks waterfront signal → conflicting;
     *       no tenant waterfront signal → unresolved
     *
     * @return array  Signal entries
     */
    private function evaluateWaterfrontLifestyleAlignment(
        array $tenantTags,
        array $propertyTags,
        array $propertyHooks
    ): array {
        try {
            // Tenant waterfront signal: any tag containing 'waterfront'
            $tenantWaterfront = false;
            foreach ($tenantTags as $tag) {
                if (str_contains(strtolower((string) $tag), 'waterfront')) {
                    $tenantWaterfront = true;
                    break;
                }
            }

            // Property waterfront signal: archetype tag or hook trait
            $propertyWaterfront = $this->hasTag($propertyTags, 'amenity:waterfront')
                               || $this->hasTag($propertyTags, 'feature:waterfront')
                               || $this->hasHookTrait($propertyHooks, 'waterfront')
                               || $this->hasHookTrait($propertyHooks, 'coastal');

            if (!$tenantWaterfront) {
                return [$this->unresolved(
                    'waterfront_lifestyle_alignment',
                    'tenant',
                    'The tenant profile does not carry a waterfront lifestyle signal.'
                )];
            }

            if ($propertyWaterfront) {
                return [$this->aligned(
                    'waterfront_lifestyle_alignment',
                    'waterfront_lifestyle_signal',
                    'waterfront_property_signal',
                    'The tenant\'s waterfront lifestyle signal is present and the property carries a waterfront or coastal signal.'
                )];
            }

            return [$this->conflicting(
                'waterfront_lifestyle_alignment',
                'waterfront_lifestyle_signal',
                'no waterfront_property_signal',
                'The tenant\'s waterfront lifestyle signal is present but the property does not carry a waterfront or coastal signal.'
            )];
        } catch (\Throwable $e) {
            return [$this->unresolved('waterfront_lifestyle_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }

    /**
     * Location alignment: Compares explicit tenant lifestyle signals against explicit Location DNA context arrays.
     *
     * Tenant lifestyle signals compared:
     *   - `prefers-type:Coastal` or waterfront-related tags → checked against coastal_features
     *   - `requires:pool` or pool signal → checked against outdoor_recreation (amenities)
     *   - `requires:garage` → not a location signal; skipped
     *
     * Location DNA context arrays consulted (from $locationContext):
     *   - `coastal_features`     — presence of coastal/beach/waterfront location signals
     *   - `daily_convenience`    — presence of daily convenience signals
     *   - `outdoor_recreation`   — presence of outdoor/recreation signals
     *   - `transportation`       — presence of transportation signals
     *
     * IMPORTANT: No demographic assumptions are made. Only explicit signal-to-context comparisons.
     *
     * Rule: tenant has a lifestyle signal that correlates with a location context dimension →
     *       check if that location context is present and signal accordingly.
     *       If the location context array is empty → unresolved (no context to compare against).
     *
     * @return array  Signal entries
     */
    private function evaluateLocationAlignment(array $tenantTags, array $locationContext): array
    {
        try {
            if (empty($locationContext)) {
                return [$this->unresolved(
                    'location_alignment',
                    'property',
                    'No Location DNA context was provided; location alignment comparison is not possible.'
                )];
            }

            $coastalFeatures    = (array) ($locationContext['coastal_features']    ?? []);
            $dailyConvenience   = (array) ($locationContext['daily_convenience']   ?? []);
            $outdoorRecreation  = (array) ($locationContext['outdoor_recreation']  ?? []);
            $transportation     = (array) ($locationContext['transportation']       ?? []);

            $signals = [];

            // Coastal/waterfront lifestyle signal vs. coastal_features context
            $tenantCoastal = false;
            foreach ($tenantTags as $tag) {
                $tagLower = strtolower((string) $tag);
                if (str_contains($tagLower, 'waterfront') || str_contains($tagLower, 'coastal')) {
                    $tenantCoastal = true;
                    break;
                }
            }

            if ($tenantCoastal) {
                if (!empty($coastalFeatures)) {
                    $signals[] = $this->aligned(
                        'location_alignment',
                        'coastal_lifestyle_signal',
                        'coastal_features_present',
                        'The tenant\'s coastal or waterfront lifestyle signal is present and the location context includes coastal features.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'location_alignment',
                        'coastal_lifestyle_signal',
                        'no coastal_features',
                        'The tenant\'s coastal or waterfront lifestyle signal is present but the location context does not include coastal features.'
                    );
                }
            }

            // Outdoor/recreation lifestyle signal vs. outdoor_recreation context
            $tenantOutdoor = false;
            foreach ($tenantTags as $tag) {
                $tagLower = strtolower((string) $tag);
                if (str_contains($tagLower, 'outdoor') || str_contains($tagLower, 'recreation')) {
                    $tenantOutdoor = true;
                    break;
                }
            }

            if ($tenantOutdoor) {
                if (!empty($outdoorRecreation)) {
                    $signals[] = $this->aligned(
                        'location_alignment',
                        'outdoor_recreation_signal',
                        'outdoor_recreation_present',
                        'The tenant\'s outdoor or recreation lifestyle signal is present and the location context includes outdoor recreation features.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'location_alignment',
                        'outdoor_recreation_signal',
                        'no outdoor_recreation',
                        'The tenant\'s outdoor or recreation lifestyle signal is present but the location context does not include outdoor recreation features.'
                    );
                }
            }

            // Daily convenience signal — if tenant has a convenience lifestyle tag
            $tenantConvenience = false;
            foreach ($tenantTags as $tag) {
                $tagLower = strtolower((string) $tag);
                if (str_contains($tagLower, 'convenience') || str_contains($tagLower, 'walkable')) {
                    $tenantConvenience = true;
                    break;
                }
            }

            if ($tenantConvenience) {
                if (!empty($dailyConvenience)) {
                    $signals[] = $this->aligned(
                        'location_alignment',
                        'daily_convenience_signal',
                        'daily_convenience_present',
                        'The tenant\'s convenience lifestyle signal is present and the location context includes daily convenience features.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'location_alignment',
                        'daily_convenience_signal',
                        'no daily_convenience',
                        'The tenant\'s convenience lifestyle signal is present but the location context does not include daily convenience features.'
                    );
                }
            }

            // Transportation signal
            $tenantTransit = false;
            foreach ($tenantTags as $tag) {
                $tagLower = strtolower((string) $tag);
                if (str_contains($tagLower, 'transit') || str_contains($tagLower, 'transportation')) {
                    $tenantTransit = true;
                    break;
                }
            }

            if ($tenantTransit) {
                if (!empty($transportation)) {
                    $signals[] = $this->aligned(
                        'location_alignment',
                        'transportation_signal',
                        'transportation_present',
                        'The tenant\'s transportation lifestyle signal is present and the location context includes transportation features.'
                    );
                } else {
                    $signals[] = $this->conflicting(
                        'location_alignment',
                        'transportation_signal',
                        'no transportation',
                        'The tenant\'s transportation lifestyle signal is present but the location context does not include transportation features.'
                    );
                }
            }

            if (empty($signals)) {
                $signals[] = $this->unresolved(
                    'location_alignment',
                    'tenant',
                    'The tenant profile does not carry lifestyle signals that correspond to available Location DNA context dimensions.'
                );
            }

            return $signals;
        } catch (\Throwable $e) {
            return [$this->unresolved('location_alignment', 'error', 'Evaluation error: ' . $e->getMessage())];
        }
    }
}
