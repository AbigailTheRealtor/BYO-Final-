<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;

/**
 * BuyerPropertyCompatibilityService — Buyer ↔ Property Compatibility Engine V1
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC, READ-ONLY INTERPRETATION LAYER. It compares
 * one buyer DNA profile against one property DNA profile and returns a neutral
 * compatibility report describing signal alignment, signal conflict, and missing
 * information across seven comparison dimensions.
 *
 * This service MUST NEVER:
 *   - Read from, write to, or modify any database row, model, or connection.
 *   - Call any AI, OpenAI, language model, embedding, ML, or external HTTP service.
 *   - Express or imply whether a buyer should or should not purchase a property.
 *   - Express or imply whether a property is a good, bad, suitable, or ideal fit
 *     for any buyer.
 *   - Produce any recommendation, ranking, endorsement, or preference ordering.
 *   - Produce any numeric match score, desirability indicator, or probability estimate.
 *   - Infer, imply, or output protected-class characteristics (age, family status,
 *     disability, race, religion, marital status, national origin).
 *   - Emit any reason string that describes what a buyer or platform should do
 *     with the reported signal information.
 *   - Modify, extend, or call CompatibilityEngine, BuyerAvatarService,
 *     PropertyPersonalityService, or any other existing service.
 *   - Create routes, controllers, views, or schema changes of any kind.
 *
 * Every reason string in signal entries describes what the signals show — not what
 * a buyer or the platform should do with that information. Classification is fully
 * deterministic and reproducible from the profile data passed in by the caller.
 * No randomness is applied.
 * ==================================================================================
 */
class BuyerPropertyCompatibilityService
{
    /**
     * Avatar-to-personality pairing table for the lifestyle/personality dimension.
     * Maps buyer primary_avatar → property primary_personality.
     * Only the four V1-approved pairings are listed.
     */
    private const AVATAR_PERSONALITY_PAIRINGS = [
        'Waterfront Buyer'   => 'Waterfront Property',
        'Vacation Buyer'     => 'Coastal Lifestyle Property',
        'Commercial Buyer'   => 'Commercial Flexibility Property',
        'Investor Buyer'     => 'Investment-Oriented Property',
    ];

    /**
     * Generate a neutral compatibility report by comparing a buyer DNA profile
     * against a property DNA profile.
     *
     * No database reads or writes occur. No AI or external HTTP calls are made.
     * All derivations are deterministic tag/flag comparisons and lookup tables.
     *
     * Output contract — always returns exactly these keys:
     *   success              bool
     *   status               'generated' | 'insufficient_data' | 'failed'
     *   compatibility_type   'buyer_property'
     *   buyer_listing_id     int
     *   property_listing_id  int
     *   buyer_avatar_context         array  (caller pass-through; not modified)
     *   property_personality_context array  (caller pass-through; not modified)
     *   location_context     array  (caller pass-through; not modified)
     *   aligned_signals      array  of signal entries (aligned results)
     *   conflicting_signals  array  of signal entries (conflicting results)
     *   unresolved_signals   array  of signal entries (unresolved / missing data)
     *   missing_inputs       array  of human-readable strings describing absent data
     *   error                string|null
     *
     * Signal entry shapes:
     *   Aligned or conflicting: ['dimension', 'buyer_signal', 'property_signal', 'reason']
     *   Unresolved:             ['dimension', 'missing_side', 'reason']
     *
     * @param  BuyerTenantDnaProfile $buyerProfile       Buyer-side DNA profile (listing_type = 'buyer').
     * @param  PropertyDnaProfile    $propertyProfile     Property-side DNA profile (listing_type = 'seller').
     * @param  array                 $buyerAvatar         Output of BuyerAvatarService::generate() (optional).
     * @param  array                 $propertyPersonality Output of PropertyPersonalityService::generate() (optional).
     * @param  array                 $locationContext     Location DNA summary array (optional).
     * @return array
     */
    public function generate(
        BuyerTenantDnaProfile $buyerProfile,
        PropertyDnaProfile    $propertyProfile,
        array                 $buyerAvatar         = [],
        array                 $propertyPersonality = [],
        array                 $locationContext     = []
    ): array {
        $stub = [
            'success'              => false,
            'status'               => 'insufficient_data',
            'compatibility_type'   => 'buyer_property',
            'buyer_listing_id'     => (int) ($buyerProfile->listing_id ?? 0),
            'property_listing_id'  => (int) ($propertyProfile->listing_id ?? 0),
            'buyer_avatar_context'         => $buyerAvatar,
            'property_personality_context' => $propertyPersonality,
            'location_context'             => $locationContext,
            'aligned_signals'              => [],
            'conflicting_signals'          => [],
            'unresolved_signals'           => [],
            'missing_inputs'               => [],
            'error'                        => null,
        ];

        // Guard 1: buyer listing_type must be 'buyer'.
        if (($buyerProfile->listing_type ?? '') !== 'buyer') {
            $stub['missing_inputs'] = ['buyer_listing_id: listing_type must be buyer'];
            return $stub;
        }

        // Guard 2: property listing_type must be 'seller'.
        if (($propertyProfile->listing_type ?? '') !== 'seller') {
            $stub['missing_inputs'] = ['property_listing_id: listing_type must be seller'];
            return $stub;
        }

        // Guard 3: buyer must have at least one lifestyle tag or deal-breaker flag.
        $buyerTags  = (array) ($buyerProfile->lifestyle_tags    ?? []);
        $buyerFlags = (array) ($buyerProfile->deal_breaker_flags ?? []);
        if (empty($buyerTags) && empty($buyerFlags)) {
            $stub['missing_inputs'] = ['Buyer profile: no lifestyle_tags or deal_breaker_flags present'];
            return $stub;
        }

        // Guard 4: property must have at least one archetype tag, marketing hook,
        // or personality context entry.
        $propertyTags  = (array) ($propertyProfile->ai_buyer_archetype_tags ?? []);
        $propertyHooks = (array) ($propertyProfile->ai_marketing_hooks      ?? []);
        $hasPersonalityContext = !empty($propertyPersonality)
            && isset($propertyPersonality['primary_personality'])
            && $propertyPersonality['primary_personality'] !== null;
        if (empty($propertyTags) && empty($propertyHooks) && !$hasPersonalityContext) {
            $stub['missing_inputs'] = ['Property profile: no archetype tags, marketing hooks, or personality context present'];
            return $stub;
        }

        try {
            $aligned     = [];
            $conflicting = [];
            $unresolved  = [];
            $missing     = [];

            // Dimension 1 — Property type.
            $this->computePropertyTypeDimension(
                $buyerTags, $propertyTags,
                $aligned, $conflicting, $unresolved, $missing
            );

            // Dimension 2 — Financing / deal structure (four sub-signals).
            $this->computeFinancingDimensions(
                $buyerTags, $buyerFlags, $propertyTags,
                $aligned, $conflicting, $unresolved, $missing
            );

            // Dimension 3 — Amenity (pool, garage).
            $this->computeAmenityDimension(
                $buyerTags, $buyerFlags, $propertyTags,
                $aligned, $unresolved, $missing
            );

            // Dimension 4 — Waterfront / coastal.
            $this->computeWaterfrontDimension(
                $buyerTags, $buyerFlags, $propertyTags,
                $propertyPersonality, $locationContext,
                $aligned, $unresolved, $missing
            );

            // Dimension 5 — Commercial interest.
            $this->computeCommercialDimension(
                $buyerTags, $buyerFlags, $propertyTags,
                $propertyProfile, $propertyPersonality,
                $aligned, $unresolved, $missing
            );

            // Dimension 6 — Budget.
            $this->computeBudgetDimension(
                $buyerFlags, $propertyProfile,
                $unresolved, $missing
            );

            // Dimension 7 — Lifestyle / personality avatar pairing.
            $this->computeAvatarPersonalityDimension(
                $buyerAvatar, $propertyPersonality,
                $aligned, $unresolved, $missing
            );

            return [
                'success'                      => true,
                'status'                       => 'generated',
                'compatibility_type'           => 'buyer_property',
                'buyer_listing_id'             => (int) ($buyerProfile->listing_id ?? 0),
                'property_listing_id'          => (int) ($propertyProfile->listing_id ?? 0),
                'buyer_avatar_context'         => $buyerAvatar,
                'property_personality_context' => $propertyPersonality,
                'location_context'             => $locationContext,
                'aligned_signals'              => $aligned,
                'conflicting_signals'          => $conflicting,
                'unresolved_signals'           => $unresolved,
                'missing_inputs'               => $missing,
                'error'                        => null,
            ];
        } catch (\Throwable $e) {
            return array_merge($stub, [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Dimension computation methods
    // -------------------------------------------------------------------------

    /**
     * Dimension 1 — Property type alignment.
     *
     * Buyer source:    lifestyle_tags  `prefers-type:{value}`
     * Property source: ai_buyer_archetype_tags `type:{value}`
     *
     * Aligned when both sides present and values match (case-insensitive).
     * Conflicting when both sides present and values differ.
     * Unresolved when either side is absent.
     */
    private function computePropertyTypeDimension(
        array $buyerTags,
        array $propertyTags,
        array &$aligned,
        array &$conflicting,
        array &$unresolved,
        array &$missing
    ): void {
        $buyerType    = $this->extractTagValue($buyerTags,    'prefers-type:');
        $propertyType = $this->extractTagValue($propertyTags, 'type:');

        if ($buyerType === null && $propertyType === null) {
            $unresolved[] = [
                'dimension'    => 'property_type',
                'missing_side' => 'both',
                'reason'       => 'Neither the buyer profile nor the property profile carries a property type signal.',
            ];
            $missing[] = 'Buyer property type preference (prefers-type:* tag)';
            $missing[] = 'Property type tag (type:* archetype tag)';
            return;
        }

        if ($buyerType === null) {
            $unresolved[] = [
                'dimension'    => 'property_type',
                'missing_side' => 'buyer',
                'reason'       => 'The property carries a type signal (' . $propertyType . ') but the buyer profile has no prefers-type tag.',
            ];
            $missing[] = 'Buyer property type preference (prefers-type:* tag)';
            return;
        }

        if ($propertyType === null) {
            $unresolved[] = [
                'dimension'    => 'property_type',
                'missing_side' => 'property',
                'reason'       => 'The buyer profile indicates a type preference (' . $buyerType . ') but the property has no type:* archetype tag.',
            ];
            $missing[] = 'Property type tag (type:* archetype tag)';
            return;
        }

        if (strtolower(trim($buyerType)) === strtolower(trim($propertyType))) {
            $aligned[] = [
                'dimension'       => 'property_type',
                'buyer_signal'    => 'prefers-type:' . $buyerType,
                'property_signal' => 'type:' . $propertyType,
                'reason'          => 'The buyer\'s stated property type preference (' . $buyerType . ') matches the property\'s type tag (' . $propertyType . ').',
            ];
        } else {
            $conflicting[] = [
                'dimension'       => 'property_type',
                'buyer_signal'    => 'prefers-type:' . $buyerType,
                'property_signal' => 'type:' . $propertyType,
                'reason'          => 'The buyer\'s stated property type preference (' . $buyerType . ') differs from the property\'s type tag (' . $propertyType . ').',
            ];
        }
    }

    /**
     * Dimension 2 — Financing / deal structure.
     *
     * Four independent sub-signals are evaluated:
     *   seller-financing: buyer `open-to:seller-financing` ↔ property `financing:seller-financed`
     *   assumable-loan:   buyer `open-to:assumable-loan`   ↔ property `financing:assumable`
     *   lease-option:     buyer `open-to:lease-option`     ↔ property `structure:lease-option`
     *   lease-purchase:   buyer `open-to:lease-purchase`   ↔ property `structure:lease-purchase`
     *
     * Aligned when buyer interest matches property offering.
     * Unresolved when buyer has interest but property lacks the signal.
     * No conflict is emitted without an explicit opposing tag.
     */
    private function computeFinancingDimensions(
        array $buyerTags,
        array $buyerFlags,
        array $propertyTags,
        array &$aligned,
        array &$conflicting,
        array &$unresolved,
        array &$missing
    ): void {
        $sub = [
            [
                'dimension'         => 'financing_seller',
                'buyerTag'          => 'open-to:seller-financing',
                'propertyTag'       => 'financing:seller-financed',
                'buyerLabel'        => 'open-to:seller-financing',
                'propertyLabel'     => 'financing:seller-financed',
                'alignedReason'     => 'The buyer profile indicates openness to seller financing and the property carries a seller-financed tag.',
                'unresolvedReason'  => 'The buyer profile indicates openness to seller financing but the property has no seller-financed tag.',
            ],
            [
                'dimension'         => 'financing_assumable',
                'buyerTag'          => 'open-to:assumable-loan',
                'propertyTag'       => 'financing:assumable',
                'buyerLabel'        => 'open-to:assumable-loan',
                'propertyLabel'     => 'financing:assumable',
                'alignedReason'     => 'The buyer profile indicates openness to an assumable loan and the property carries an assumable financing tag.',
                'unresolvedReason'  => 'The buyer profile indicates openness to an assumable loan but the property has no assumable financing tag.',
            ],
            [
                'dimension'         => 'structure_lease_option',
                'buyerTag'          => 'open-to:lease-option',
                'propertyTag'       => 'structure:lease-option',
                'buyerLabel'        => 'open-to:lease-option',
                'propertyLabel'     => 'structure:lease-option',
                'alignedReason'     => 'The buyer profile indicates interest in a lease-option arrangement and the property carries a lease-option structure tag.',
                'unresolvedReason'  => 'The buyer profile indicates interest in a lease-option arrangement but the property has no lease-option structure tag.',
            ],
            [
                'dimension'         => 'structure_lease_purchase',
                'buyerTag'          => 'open-to:lease-purchase',
                'propertyTag'       => 'structure:lease-purchase',
                'buyerLabel'        => 'open-to:lease-purchase',
                'propertyLabel'     => 'structure:lease-purchase',
                'alignedReason'     => 'The buyer profile indicates interest in a lease-purchase arrangement and the property carries a lease-purchase structure tag.',
                'unresolvedReason'  => 'The buyer profile indicates interest in a lease-purchase arrangement but the property has no lease-purchase structure tag.',
            ],
        ];

        foreach ($sub as $s) {
            $buyerInterested  = $this->hasTag($buyerTags, $s['buyerTag']);
            $propertyOffers   = $this->hasTag($propertyTags, $s['propertyTag']);

            if (!$buyerInterested) {
                continue;
            }

            if ($propertyOffers) {
                $aligned[] = [
                    'dimension'       => $s['dimension'],
                    'buyer_signal'    => $s['buyerLabel'],
                    'property_signal' => $s['propertyLabel'],
                    'reason'          => $s['alignedReason'],
                ];
            } else {
                $unresolved[] = [
                    'dimension'    => $s['dimension'],
                    'missing_side' => 'property',
                    'reason'       => $s['unresolvedReason'],
                ];
            }
        }
    }

    /**
     * Dimension 3 — Amenity (pool, garage).
     *
     * Pool:
     *   Buyer source:    `requires:pool` lifestyle tag OR `pool_required` deal_breaker_flag
     *   Property source: `amenity:pool` archetype tag
     *
     * Garage:
     *   Buyer source:    `requires:garage` lifestyle tag OR `garage_required` deal_breaker_flag
     *   Property source: `amenity:garage` OR `parking:garage` archetype tag
     *
     * Aligned when buyer requires + property has the amenity.
     * Unresolved when buyer requires + property lacks (no explicit opposing vocab).
     * If buyer has no amenity requirement, no entry is emitted.
     */
    private function computeAmenityDimension(
        array $buyerTags,
        array $buyerFlags,
        array $propertyTags,
        array &$aligned,
        array &$unresolved,
        array &$missing
    ): void {
        $poolRequired   = $this->hasTag($buyerTags, 'requires:pool')
                       || $this->hasDealBreakerFlag($buyerFlags, 'pool_required');
        $garageRequired = $this->hasTag($buyerTags, 'requires:garage')
                       || $this->hasDealBreakerFlag($buyerFlags, 'garage_required');

        $propertyHasPool   = $this->hasTag($propertyTags, 'amenity:pool');
        $propertyHasGarage = $this->hasTag($propertyTags, 'amenity:garage')
                          || $this->hasTag($propertyTags, 'parking:garage');

        if ($poolRequired) {
            if ($propertyHasPool) {
                $aligned[] = [
                    'dimension'       => 'amenity_pool',
                    'buyer_signal'    => 'pool_required',
                    'property_signal' => 'amenity:pool',
                    'reason'          => 'The buyer profile includes a pool requirement and the property carries an amenity:pool tag.',
                ];
            } else {
                $unresolved[] = [
                    'dimension'    => 'amenity_pool',
                    'missing_side' => 'property',
                    'reason'       => 'The buyer profile includes a pool requirement but the property has no amenity:pool tag.',
                ];
                $missing[] = 'Property pool amenity signal (amenity:pool tag)';
            }
        }

        if ($garageRequired) {
            if ($propertyHasGarage) {
                $aligned[] = [
                    'dimension'       => 'amenity_garage',
                    'buyer_signal'    => 'garage_required',
                    'property_signal' => 'amenity:garage or parking:garage',
                    'reason'          => 'The buyer profile includes a garage requirement and the property carries a garage tag.',
                ];
            } else {
                $unresolved[] = [
                    'dimension'    => 'amenity_garage',
                    'missing_side' => 'property',
                    'reason'       => 'The buyer profile includes a garage requirement but the property has no amenity:garage or parking:garage tag.',
                ];
                $missing[] = 'Property garage signal (amenity:garage or parking:garage tag)';
            }
        }
    }

    /**
     * Dimension 4 — Waterfront / coastal.
     *
     * Buyer source:    `waterfront_required` deal_breaker_flag OR `prefers-type:Waterfront` tag
     * Property source: `feature:waterfront` OR `amenity:waterfront` archetype tag,
     *                  OR property personality primary_personality = 'Waterfront Property',
     *                  OR location context coastal_features present
     *
     * Aligned when buyer indicates waterfront + property/location has at least one supporting signal.
     * Unresolved when buyer indicates waterfront + property/location has no supporting signal.
     * No entry emitted when buyer has no waterfront signal.
     */
    private function computeWaterfrontDimension(
        array $buyerTags,
        array $buyerFlags,
        array $propertyTags,
        array $propertyPersonality,
        array $locationContext,
        array &$aligned,
        array &$unresolved,
        array &$missing
    ): void {
        $buyerWaterfront = $this->hasDealBreakerFlag($buyerFlags, 'waterfront_required')
                        || $this->hasTagPrefix($buyerTags, 'prefers-type:', 'waterfront');

        if (!$buyerWaterfront) {
            return;
        }

        $propertyWaterfront = $this->hasTag($propertyTags, 'feature:waterfront')
                           || $this->hasTag($propertyTags, 'amenity:waterfront');

        $personalityWaterfront = isset($propertyPersonality['primary_personality'])
                              && $propertyPersonality['primary_personality'] === 'Waterfront Property';

        $coastalFeaturesPresent = !empty($locationContext['coastal_features'])
                               || !empty($locationContext['coastal']);

        if ($propertyWaterfront || $personalityWaterfront || $coastalFeaturesPresent) {
            $propertySide = $propertyWaterfront
                ? ($this->hasTag($propertyTags, 'feature:waterfront') ? 'feature:waterfront' : 'amenity:waterfront')
                : ($personalityWaterfront ? 'personality:Waterfront Property' : 'location:coastal_features');

            $aligned[] = [
                'dimension'       => 'waterfront',
                'buyer_signal'    => 'waterfront_required',
                'property_signal' => $propertySide,
                'reason'          => 'The buyer profile carries a waterfront signal and the property or location context has a corresponding waterfront or coastal indicator.',
            ];
        } else {
            $unresolved[] = [
                'dimension'    => 'waterfront',
                'missing_side' => 'property',
                'reason'       => 'The buyer profile carries a waterfront signal but neither the property tags, property personality, nor location context contains a waterfront or coastal indicator.',
            ];
            $missing[] = 'Property waterfront signal (feature:waterfront, amenity:waterfront, personality, or coastal location context)';
        }
    }

    /**
     * Dimension 5 — Commercial interest.
     *
     * Buyer source:    `commercial_interest` deal_breaker_flag OR `prefers-type:Commercial` tag
     * Property source: `use:commercial` archetype tag, OR non-null commercial_score,
     *                  OR property personality primary_personality = 'Commercial Flexibility Property'
     *
     * Aligned when buyer indicates commercial interest + property has at least one supporting signal.
     * Unresolved when buyer indicates commercial interest + property has no supporting signal.
     * No entry emitted when buyer has no commercial signal.
     */
    private function computeCommercialDimension(
        array $buyerTags,
        array $buyerFlags,
        array $propertyTags,
        PropertyDnaProfile $propertyProfile,
        array $propertyPersonality,
        array &$aligned,
        array &$unresolved,
        array &$missing
    ): void {
        $buyerCommercial = $this->hasDealBreakerFlag($buyerFlags, 'commercial_interest')
                        || $this->hasTagPrefix($buyerTags, 'prefers-type:', 'commercial');

        if (!$buyerCommercial) {
            return;
        }

        $propertyCommercialTag   = $this->hasTag($propertyTags, 'use:commercial');
        $propertyCommercialScore = ($propertyProfile->commercial_score ?? null) !== null;
        $personalityCommercial   = isset($propertyPersonality['primary_personality'])
                                && $propertyPersonality['primary_personality'] === 'Commercial Flexibility Property';

        if ($propertyCommercialTag || $propertyCommercialScore || $personalityCommercial) {
            $propertySide = $propertyCommercialTag
                ? 'use:commercial'
                : ($propertyCommercialScore ? 'commercial_score' : 'personality:Commercial Flexibility Property');

            $aligned[] = [
                'dimension'       => 'commercial_interest',
                'buyer_signal'    => 'commercial_interest',
                'property_signal' => $propertySide,
                'reason'          => 'The buyer profile carries a commercial interest signal and the property has a corresponding commercial indicator.',
            ];
        } else {
            $unresolved[] = [
                'dimension'    => 'commercial_interest',
                'missing_side' => 'property',
                'reason'       => 'The buyer profile carries a commercial interest signal but the property has no use:commercial tag, commercial_score, or commercial personality.',
            ];
            $missing[] = 'Property commercial signal (use:commercial tag, commercial_score, or Commercial Flexibility Property personality)';
        }
    }

    /**
     * Dimension 6 — Budget.
     *
     * Only compared when the property carries an explicit price signal.
     * No affordability logic is invented. No purchase guidance is emitted.
     *
     * If the property has no explicit price signal, emit unresolved only.
     * If the buyer has no budget signal, emit unresolved only.
     * No comparison between values is performed — presence/absence only.
     */
    private function computeBudgetDimension(
        array $buyerFlags,
        PropertyDnaProfile $propertyProfile,
        array &$unresolved,
        array &$missing
    ): void {
        $buyerHasBudget = $this->hasDealBreakerFlag($buyerFlags, 'budget_ceiling_specified');

        // PropertyDnaProfile does not currently carry an explicit asking-price dimension.
        // This dimension is unresolved until a property price signal is introduced.
        $propertyHasPrice = false;

        if (!$propertyHasPrice) {
            $unresolved[] = [
                'dimension'    => 'budget',
                'missing_side' => 'property',
                'reason'       => 'The property profile does not carry an explicit price signal; the budget dimension cannot be compared.',
            ];
            $missing[] = 'Property asking price signal (no price dimension present in current property DNA schema)';
            return;
        }

        if (!$buyerHasBudget) {
            $unresolved[] = [
                'dimension'    => 'budget',
                'missing_side' => 'buyer',
                'reason'       => 'The buyer profile does not carry a budget ceiling signal; the budget dimension cannot be compared.',
            ];
            $missing[] = 'Buyer budget ceiling (budget_ceiling_specified deal_breaker_flag)';
        }
    }

    /**
     * Dimension 7 — Lifestyle / personality avatar pairing.
     *
     * Compares buyerAvatar['primary_avatar'] against propertyPersonality['primary_personality']
     * using the four V1-approved pairings:
     *   Waterfront Buyer   ↔ Waterfront Property
     *   Vacation Buyer     ↔ Coastal Lifestyle Property
     *   Commercial Buyer   ↔ Commercial Flexibility Property
     *   Investor Buyer     ↔ Investment-Oriented Property
     *
     * Aligned when a known pairing matches.
     * Unresolved when either side is absent, or the combination has no approved pairing.
     */
    private function computeAvatarPersonalityDimension(
        array $buyerAvatar,
        array $propertyPersonality,
        array &$aligned,
        array &$unresolved,
        array &$missing
    ): void {
        $primaryAvatar      = $buyerAvatar['primary_avatar']      ?? null;
        $primaryPersonality = $propertyPersonality['primary_personality'] ?? null;

        if ($primaryAvatar === null && $primaryPersonality === null) {
            $unresolved[] = [
                'dimension'    => 'avatar_personality_pairing',
                'missing_side' => 'both',
                'reason'       => 'Neither a buyer avatar nor a property personality was provided; the pairing dimension cannot be evaluated.',
            ];
            $missing[] = 'Buyer primary avatar (buyerAvatar output)';
            $missing[] = 'Property primary personality (propertyPersonality output)';
            return;
        }

        if ($primaryAvatar === null) {
            $unresolved[] = [
                'dimension'    => 'avatar_personality_pairing',
                'missing_side' => 'buyer',
                'reason'       => 'No buyer avatar was provided; the pairing dimension cannot be evaluated.',
            ];
            $missing[] = 'Buyer primary avatar (buyerAvatar output)';
            return;
        }

        if ($primaryPersonality === null) {
            $unresolved[] = [
                'dimension'    => 'avatar_personality_pairing',
                'missing_side' => 'property',
                'reason'       => 'No property personality was provided; the pairing dimension cannot be evaluated.',
            ];
            $missing[] = 'Property primary personality (propertyPersonality output)';
            return;
        }

        $expectedPersonality = self::AVATAR_PERSONALITY_PAIRINGS[$primaryAvatar] ?? null;

        if ($expectedPersonality !== null && $expectedPersonality === $primaryPersonality) {
            $aligned[] = [
                'dimension'       => 'avatar_personality_pairing',
                'buyer_signal'    => $primaryAvatar,
                'property_signal' => $primaryPersonality,
                'reason'          => 'The buyer avatar (' . $primaryAvatar . ') and property personality (' . $primaryPersonality . ') match a recognized V1 pairing.',
            ];
        } else {
            $unresolved[] = [
                'dimension'    => 'avatar_personality_pairing',
                'missing_side' => 'none',
                'reason'       => 'The buyer avatar (' . $primaryAvatar . ') and property personality (' . $primaryPersonality . ') do not form a recognized V1 pairing; no pairing signal is present.',
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Tag and flag utility methods
    // -------------------------------------------------------------------------

    /**
     * Return true when $tag appears in the $tags array (exact match).
     */
    private function hasTag(array $tags, string $tag): bool
    {
        return in_array($tag, array_map('strval', $tags), true);
    }

    /**
     * Extract the suffix value from the first tag that starts with $prefix.
     * Returns null when no such tag is found.
     *
     * Example: extractTagValue(['type:SingleFamily'], 'type:') → 'SingleFamily'
     */
    private function extractTagValue(array $tags, string $prefix): ?string
    {
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if (str_starts_with($tag, $prefix)) {
                return substr($tag, strlen($prefix));
            }
        }
        return null;
    }

    /**
     * Return true when at least one tag starts with $prefix and its suffix
     * contains $contains (case-insensitive).
     *
     * Example: hasTagPrefix(['prefers-type:Waterfront'], 'prefers-type:', 'waterfront') → true
     */
    private function hasTagPrefix(array $tags, string $prefix, string $contains): bool
    {
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if (str_starts_with($tag, $prefix)) {
                $suffix = strtolower(substr($tag, strlen($prefix)));
                if (str_contains($suffix, strtolower($contains))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return true when any deal-breaker flag record has flag === $flagName.
     *
     * Each flag record may be an array with a 'flag' key or a plain scalar.
     */
    private function hasDealBreakerFlag(array $flags, string $flagName): bool
    {
        foreach ($flags as $record) {
            $record = (array) $record;
            if (($record['flag'] ?? '') === $flagName) {
                return true;
            }
        }
        return false;
    }
}
