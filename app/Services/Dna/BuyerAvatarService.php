<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;

/**
 * BuyerAvatarService — Buyer Avatar Engine V1
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC CLASSIFICATION LAYER ONLY. It classifies a
 * persisted BuyerTenantDnaProfile (listing_type = 'buyer') into structured avatar
 * categories using rule-based signal extraction.
 *
 * This service MUST NEVER:
 *   - Perform AI reasoning, language model inference, embedding lookup, or ML logic.
 *   - Issue additional database queries beyond reading the passed-in profile object.
 *   - Write, update, or persist any data to the database.
 *   - Infer, imply, or output protected-class characteristics (family status, age,
 *     race, religion, disability, marital status).
 *   - Produce recommendations, rankings, or numeric compatibility scores.
 *   - Generate narrative copy, endorsements, or matchmaking language.
 *   - Process listing_type values other than 'buyer'.
 *   - Infer or proxy signals from preference_completeness or any dimension score.
 *     All signals must be derived from explicitly persisted lifestyle_tags or
 *     deal_breaker_flags values only.
 *
 * Classification is fully deterministic and reproducible from persisted profile data.
 * Rule priority order is fixed and documented below. No randomness is applied.
 *
 * NOTE — Relocation Buyer:
 *   This avatar type is reserved but not classified in V1. The BuyerTenantDnaGenerator
 *   computes a timeline_flexibility dimension slot but does not emit a lifestyle_tag or
 *   deal_breaker_flag for it, so no explicit timeline signal is available in the profile.
 *   Relocation Buyer classification will be added when the generator is updated to emit
 *   a dedicated timeline tag or flag (future phase).
 * ==================================================================================
 */
class BuyerAvatarService
{
    /**
     * Minimum preference_completeness (0–100) required to attempt classification.
     * Profiles below this threshold return status = 'insufficient_data'.
     */
    private const MIN_COMPLETENESS = 20.0;

    /**
     * Ordered list of all allowed avatar type values.
     * Priority among them is determined by the rule order in classify().
     *
     * 'Relocation Buyer' is listed here for taxonomy completeness but is not
     * classified in V1 (no explicit timeline signal in the current profile).
     */
    private const AVATAR_TYPES = [
        'Commercial Buyer',
        'Waterfront Buyer',
        'Investor Buyer',
        'Vacation Buyer',
        'Downsizing Buyer',
        'Luxury Buyer',
        'Move-Up Buyer',
        'Budget-Conscious Buyer',
        'Relocation Buyer',
        'First-Time Buyer',
        'Flexible Buyer',
        'Unknown Buyer',
    ];

    /**
     * Classify a BuyerTenantDnaProfile into structured avatar categories.
     *
     * Reads only lifestyle_tags, deal_breaker_flags, preference_completeness,
     * archetype_label, listing_type, and listing_id from the passed-in profile.
     * No additional database queries are issued.
     *
     * Output contract — always returns exactly these keys:
     *   success          bool
     *   status           'generated' | 'insufficient_data' | 'failed'
     *   listing_type     'buyer'   (always 'buyer'; this service only processes buyer profiles)
     *   listing_id       int
     *   primary_avatar   string|null
     *   secondary_avatars array
     *   signals          array
     *   missing_inputs   array
     *   error            string|null
     *
     * @param  BuyerTenantDnaProfile $profile  A cast, in-memory profile instance.
     * @return array
     */
    public function generate(BuyerTenantDnaProfile $profile): array
    {
        // listing_type is always 'buyer' in the output contract regardless of
        // what the profile actually contains — this service is buyer-only.
        $stub = [
            'success'           => false,
            'status'            => 'insufficient_data',
            'listing_type'      => 'buyer',
            'listing_id'        => (int) ($profile->listing_id ?? 0),
            'primary_avatar'    => null,
            'secondary_avatars' => [],
            'signals'           => [],
            'missing_inputs'    => [],
            'error'             => null,
        ];

        if (($profile->listing_type ?? '') !== 'buyer') {
            $stub['missing_inputs'] = ['listing_type must be buyer'];
            return $stub;
        }

        $completeness = (float) ($profile->preference_completeness ?? 0.0);

        if ($completeness < self::MIN_COMPLETENESS) {
            $stub['missing_inputs'] = $this->buildMissingInputsFromCompleteness(
                (array) ($profile->lifestyle_tags ?? []),
                (array) ($profile->deal_breaker_flags ?? [])
            );
            return $stub;
        }

        try {
            $lifestyleTags    = (array) ($profile->lifestyle_tags    ?? []);
            $dealBreakerFlags = (array) ($profile->deal_breaker_flags ?? []);

            $signals       = $this->extractSignals($lifestyleTags, $dealBreakerFlags);
            $missingInputs = $this->buildMissingInputs($signals);

            [$primary, $secondaries] = $this->classify($signals);

            return [
                'success'           => true,
                'status'            => 'generated',
                'listing_type'      => 'buyer',
                'listing_id'        => (int) $profile->listing_id,
                'primary_avatar'    => $primary,
                'secondary_avatars' => $secondaries,
                'signals'           => $signals,
                'missing_inputs'    => $missingInputs,
                'error'             => null,
            ];
        } catch (\Throwable $e) {
            return array_merge($stub, [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract boolean/scalar signal variables from the persisted lifestyle_tags and
     * deal_breaker_flags arrays only.
     *
     * Maps the known tag/flag vocabulary to named signal variables. Handles both
     * tag-based and flag-based sources for signals that may be expressed through
     * either vocabulary (e.g. waterfront_signal from 'prefers-type:Waterfront' tag
     * OR a 'waterfront_required' flag; commercial_signal from 'prefers-type:Commercial'
     * tag OR a 'commercial_interest' flag).
     *
     * No signal is inferred from preference_completeness or any aggregate score.
     * All signals must originate from explicitly persisted tag or flag values.
     *
     * Protected-class characteristics (family status, age, race, religion,
     * disability, marital status) are never inferred or surfaced as signals.
     *
     * @param  string[] $lifestyleTags      Persisted lifestyle tag strings.
     * @param  array[]  $dealBreakerFlags   Persisted deal-breaker flag records.
     * @return array<string, mixed>
     */
    private function extractSignals(array $lifestyleTags, array $dealBreakerFlags): array
    {
        $signals = [
            'commercial_signal'        => false,
            'waterfront_signal'        => false,
            'vacation_signal'          => false,
            'has_property_type'        => false,
            'pool_required'            => false,
            'garage_required'          => false,
            'open_to_seller_financing' => false,
            'open_to_assumable_loan'   => false,
            'open_to_lease_option'     => false,
            'open_to_lease_purchase'   => false,
            'pre_approved'             => false,
            'budget_ceiling_specified' => false,
            'budget_value'             => null,
            'seeks_55_plus'            => false,
        ];

        foreach ($lifestyleTags as $tag) {
            $tag = (string) $tag;

            if ($tag === 'has-pets') {
                continue;
            }
            if ($tag === 'seeks:55-plus-community') {
                $signals['seeks_55_plus'] = true;
                continue;
            }
            if ($tag === 'requires:pool') {
                $signals['pool_required'] = true;
                continue;
            }
            if ($tag === 'requires:garage') {
                $signals['garage_required'] = true;
                continue;
            }
            if ($tag === 'open-to:seller-financing') {
                $signals['open_to_seller_financing'] = true;
                continue;
            }
            if ($tag === 'open-to:assumable-loan') {
                $signals['open_to_assumable_loan'] = true;
                continue;
            }
            if ($tag === 'open-to:lease-option') {
                $signals['open_to_lease_option'] = true;
                continue;
            }
            if ($tag === 'open-to:lease-purchase') {
                $signals['open_to_lease_purchase'] = true;
                continue;
            }
            if ($tag === 'financial:pre-approved') {
                $signals['pre_approved'] = true;
                continue;
            }
            if (str_starts_with($tag, 'prefers-type:')) {
                $signals['has_property_type'] = true;
                $typeValue = strtolower(substr($tag, strlen('prefers-type:')));
                if (str_contains($typeValue, 'commercial')) {
                    $signals['commercial_signal'] = true;
                }
                if (str_contains($typeValue, 'waterfront')) {
                    $signals['waterfront_signal'] = true;
                }
                if (str_contains($typeValue, 'vacation') || str_contains($typeValue, 'resort')) {
                    $signals['vacation_signal'] = true;
                }
                continue;
            }
        }

        foreach ($dealBreakerFlags as $flagRecord) {
            $flagRecord = (array) $flagRecord;
            $flag       = (string) ($flagRecord['flag'] ?? '');
            $value      = $flagRecord['value'] ?? null;

            if ($flag === 'pool_required') {
                $signals['pool_required'] = true;
            }
            if ($flag === 'garage_required') {
                $signals['garage_required'] = true;
            }
            if ($flag === 'budget_ceiling_specified') {
                $signals['budget_ceiling_specified'] = true;
                if ($value !== null && $value !== '') {
                    $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $value);
                    if ($numeric > 0) {
                        $signals['budget_value'] = $numeric;
                    }
                }
            }
            // Flag-based equivalents for property-type signals — forward-compatible
            // with generators that may emit these flags directly rather than via
            // a prefers-type:* lifestyle tag.
            if ($flag === 'waterfront_required') {
                $signals['waterfront_signal'] = true;
            }
            if ($flag === 'commercial_interest') {
                $signals['commercial_signal'] = true;
            }
        }

        return $signals;
    }

    /**
     * Apply ordered, deterministic classification rules to the extracted signals.
     *
     * Rules are evaluated in priority order. The first matching rule sets the
     * primary avatar. All other matching rules populate secondary_avatars.
     * 'Flexible Buyer' is the fallback when signals are present but no specific
     * avatar rule fires. 'Unknown Buyer' is the fallback when no meaningful
     * signals are present at all.
     *
     * Avatar type priority (highest to lowest):
     *   1. Commercial Buyer   — commercial property type signal (tag or flag)
     *   2. Waterfront Buyer   — waterfront property type signal (tag or flag)
     *   3. Investor Buyer     — 2+ alternative financing/ownership signals
     *   4. Vacation Buyer     — vacation/resort property type signal
     *   5. Downsizing Buyer   — 55-plus community preference
     *   6. Luxury Buyer       — pre-approved + budget > $750,000
     *   7. Move-Up Buyer      — pre-approved + hard amenity requirement (non-Luxury)
     *   8. Budget-Conscious   — budget set, not pre-approved, not Investor
     *   9. First-Time Buyer   — not pre-approved, budget set, no prior avatar
     *  10. Flexible Buyer     — signals present but no specific rule matched
     *  11. Unknown Buyer      — no meaningful signals found
     *
     * NOTE — 'Relocation Buyer' is not classified in V1. See class-level governance block.
     *
     * @param  array $signals  Signal map from extractSignals().
     * @return array{0: string, 1: array}  [primary_avatar, secondary_avatars]
     */
    private function classify(array $signals): array
    {
        $candidates = [];

        // Rule 1 — Commercial Buyer: commercial property type signal (tag or flag).
        if ($signals['commercial_signal']) {
            $candidates[] = 'Commercial Buyer';
        }

        // Rule 2 — Waterfront Buyer: waterfront property type signal (tag or flag).
        if ($signals['waterfront_signal']) {
            $candidates[] = 'Waterfront Buyer';
        }

        // Rule 3 — Investor Buyer: two or more alternative financing/ownership signals.
        $investorSignalCount = (int) $signals['open_to_lease_option']
            + (int) $signals['open_to_lease_purchase']
            + (int) $signals['open_to_seller_financing']
            + (int) $signals['open_to_assumable_loan'];
        if ($investorSignalCount >= 2) {
            $candidates[] = 'Investor Buyer';
        }

        // Rule 4 — Vacation Buyer: vacation/resort property type signal.
        if ($signals['vacation_signal']) {
            $candidates[] = 'Vacation Buyer';
        }

        // Rule 5 — Downsizing Buyer: 55-plus community preference.
        if ($signals['seeks_55_plus']) {
            $candidates[] = 'Downsizing Buyer';
        }

        // Rule 6 — Luxury Buyer: pre-approved with budget above $750,000.
        if ($signals['pre_approved']
            && $signals['budget_ceiling_specified']
            && $signals['budget_value'] !== null
            && $signals['budget_value'] > 750000
        ) {
            $candidates[] = 'Luxury Buyer';
        }

        // Rule 7 — Move-Up Buyer: pre-approved with hard amenity requirements
        // (pool or garage), not already classified as Luxury Buyer.
        if ($signals['pre_approved']
            && ($signals['pool_required'] || $signals['garage_required'])
            && !in_array('Luxury Buyer', $candidates, true)
        ) {
            $candidates[] = 'Move-Up Buyer';
        }

        // Rule 8 — Budget-Conscious Buyer: budget ceiling set but not pre-approved,
        // and not primarily an investor pattern.
        if ($signals['budget_ceiling_specified']
            && !$signals['pre_approved']
            && !in_array('Investor Buyer', $candidates, true)
        ) {
            $candidates[] = 'Budget-Conscious Buyer';
        }

        // Rule 9 — First-Time Buyer: not pre-approved, budget set, no prior avatar.
        if (!$signals['pre_approved']
            && $signals['budget_ceiling_specified']
            && empty($candidates)
        ) {
            $candidates[] = 'First-Time Buyer';
        }

        // Fallback — Flexible Buyer when signals exist but none of the above fired.
        // Unknown Buyer when no meaningful signals are present.
        if (empty($candidates)) {
            $hasAnyMeaningfulSignal = $signals['has_property_type']
                || $signals['pool_required']
                || $signals['garage_required']
                || $signals['open_to_seller_financing']
                || $signals['open_to_assumable_loan']
                || $signals['open_to_lease_option']
                || $signals['open_to_lease_purchase']
                || $signals['pre_approved']
                || $signals['budget_ceiling_specified']
                || $signals['seeks_55_plus'];

            $candidates[] = $hasAnyMeaningfulSignal ? 'Flexible Buyer' : 'Unknown Buyer';
        }

        $primary     = $candidates[0];
        $secondaries = array_values(array_slice($candidates, 1));

        return [$primary, $secondaries];
    }

    /**
     * Build the missing_inputs list from extracted signals.
     *
     * Reports the human-readable signal dimension names that were absent (false/null)
     * so callers know what additional profile data would improve classification.
     *
     * @param  array $signals  Signal map from extractSignals().
     * @return string[]
     */
    private function buildMissingInputs(array $signals): array
    {
        $missing = [];

        $presenceSignals = [
            'has_property_type'        => 'Property type preference',
            'pre_approved'             => 'Pre-approval status',
            'budget_ceiling_specified' => 'Budget ceiling',
            'open_to_seller_financing' => 'Seller financing interest',
            'open_to_assumable_loan'   => 'Assumable loan interest',
            'open_to_lease_option'     => 'Lease option interest',
            'open_to_lease_purchase'   => 'Lease purchase interest',
        ];

        foreach ($presenceSignals as $signalKey => $label) {
            if (empty($signals[$signalKey])) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Build a missing_inputs list for profiles that did not reach the minimum
     * completeness threshold. Inspects what tags/flags are present to determine
     * which core dimension slots are absent.
     *
     * @param  string[] $lifestyleTags
     * @param  array[]  $dealBreakerFlags
     * @return string[]
     */
    private function buildMissingInputsFromCompleteness(array $lifestyleTags, array $dealBreakerFlags): array
    {
        $presentTags  = array_flip(array_map('strval', $lifestyleTags));
        $presentFlags = [];
        foreach ($dealBreakerFlags as $f) {
            $f = (array) $f;
            if (!empty($f['flag'])) {
                $presentFlags[$f['flag']] = true;
            }
        }

        $missing = [];

        if (!isset($presentFlags['budget_ceiling_specified'])) {
            $missing[] = 'Budget ceiling';
        }
        if (!array_key_exists('financial:pre-approved', $presentTags)) {
            $missing[] = 'Pre-approval status';
        }

        $hasPropertyType = false;
        foreach ($lifestyleTags as $tag) {
            if (str_starts_with((string) $tag, 'prefers-type:')) {
                $hasPropertyType = true;
                break;
            }
        }
        if (!$hasPropertyType) {
            $missing[] = 'Property type preference';
        }

        if (
            !array_key_exists('open-to:seller-financing', $presentTags)
            && !array_key_exists('open-to:assumable-loan', $presentTags)
            && !array_key_exists('open-to:lease-option', $presentTags)
            && !array_key_exists('open-to:lease-purchase', $presentTags)
        ) {
            $missing[] = 'Financing preference or alternative purchase interest';
        }

        $missing[] = 'Additional preference dimensions (profile completeness below minimum threshold)';

        return $missing;
    }
}
