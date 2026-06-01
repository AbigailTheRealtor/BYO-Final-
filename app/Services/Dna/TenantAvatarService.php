<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;

/**
 * TenantAvatarService — Tenant Avatar Engine V1
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC CLASSIFICATION LAYER ONLY. It classifies a
 * persisted BuyerTenantDnaProfile (listing_type = 'tenant') into structured avatar
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
 *   - Process listing_type values other than 'tenant'.
 *   - Infer or proxy signals from preference_completeness or any dimension score.
 *     All signals must be derived from explicitly persisted lifestyle_tags or
 *     deal_breaker_flags values only.
 *
 * Classification is fully deterministic and reproducible from persisted profile data.
 * Rule priority order is fixed and documented below. No randomness is applied.
 *
 * NOTE — Protected-class avatar types (Family Tenant, Student Tenant, Senior Tenant,
 *   Professional Tenant, Retiree Tenant) are explicitly out of scope and must never
 *   be added to this service.
 * ==================================================================================
 */
class TenantAvatarService
{
    /**
     * Minimum preference_completeness (0–100) required to attempt classification.
     * Profiles below this threshold return status = 'insufficient_data'.
     */
    private const MIN_COMPLETENESS = 20.0;

    /**
     * Avatar version stamp — bump in a future phase when classification rules change.
     */
    private const AVATAR_VERSION = 'TENANT_AVATAR_V1';

    /**
     * Ordered list of all allowed avatar type values.
     * Priority among them is determined by the rule order in classify().
     */
    private const AVATAR_TYPES = [
        'Commercial Tenant',
        'Lease-Option Tenant',
        'Pet-Conscious Tenant',
        'Amenity-Focused Tenant',
        'Space-Focused Tenant',
        'Budget-Conscious Tenant',
        'Flexible Tenant',
        'Unknown Tenant',
    ];

    /**
     * Standardized motivation vocabulary for tenant avatar types.
     * Vocabulary: Pet-Friendly Required, Budget Constrained, Amenity Access,
     * Space Requirements, Lease Flexibility, Commercial Use, Stable Housing.
     *
     * Amenity-Focused Tenant secondary is signal-driven — see generateMotivations().
     */
    private const MOTIVATION_MAP = [
        'Commercial Tenant'      => ['primary' => 'Commercial Use',        'secondary' => 'Lease Flexibility'],
        'Lease-Option Tenant'    => ['primary' => 'Lease Flexibility',     'secondary' => 'Stable Housing'],
        'Pet-Conscious Tenant'   => ['primary' => 'Pet-Friendly Required', 'secondary' => 'Stable Housing'],
        'Space-Focused Tenant'   => ['primary' => 'Space Requirements',    'secondary' => 'Stable Housing'],
        'Budget-Conscious Tenant'=> ['primary' => 'Budget Constrained',    'secondary' => 'Stable Housing'],
        'Flexible Tenant'        => ['primary' => 'Stable Housing',        'secondary' => null],
        'Unknown Tenant'         => ['primary' => null,                    'secondary' => null],
    ];

    /**
     * Classify a BuyerTenantDnaProfile into structured tenant avatar categories.
     *
     * Reads only lifestyle_tags, deal_breaker_flags, preference_completeness,
     * archetype_label, listing_type, and listing_id from the passed-in profile.
     * No additional database queries are issued.
     *
     * Output contract — always returns exactly these keys:
     *   success                   bool
     *   status                    'generated' | 'insufficient_data' | 'failed'
     *   listing_type              'tenant'   (always 'tenant'; this service only processes tenant profiles)
     *   listing_id                int
     *   primary_avatar            string|null
     *   secondary_avatars         array
     *   signals                   array
     *   missing_inputs            array
     *   error                     string|null
     *   primary_motivation        string|null
     *   secondary_motivation      string|null
     *   tenant_narrative          string|null
     *   tenant_preference_summary array
     *   tenant_personality_tags   array
     *   tenant_match_preferences  array
     *   avatar_confidence_score   int
     *   tenant_avatar_version     string
     *
     * @param  BuyerTenantDnaProfile $profile  A cast, in-memory profile instance.
     * @return array
     */
    public function generate(BuyerTenantDnaProfile $profile): array
    {
        // listing_type is always 'tenant' in the output contract regardless of
        // what the profile actually contains — this service is tenant-only.
        $result = [
            'success'                   => false,
            'status'                    => 'insufficient_data',
            'listing_type'              => 'tenant',
            'listing_id'                => (int) ($profile->listing_id ?? 0),
            'primary_avatar'            => null,
            'secondary_avatars'         => [],
            'signals'                   => [],
            'missing_inputs'            => [],
            'error'                     => null,
            'primary_motivation'        => null,
            'secondary_motivation'      => null,
            'tenant_narrative'          => null,
            'tenant_preference_summary' => ['property_types' => [], 'amenities' => [], 'budget_signals' => [], 'space_requirements' => []],
            'tenant_personality_tags'   => [],
            'tenant_match_preferences'  => [],
            'avatar_confidence_score'   => 0,
            'tenant_avatar_version'     => self::AVATAR_VERSION,
        ];

        if (($profile->listing_type ?? '') !== 'tenant') {
            $result['missing_inputs'] = ['listing_type must be tenant'];
            return $result;
        }

        $completeness = (float) ($profile->preference_completeness ?? 0.0);

        if ($completeness < self::MIN_COMPLETENESS) {
            $result['missing_inputs'] = $this->buildMissingInputsFromCompleteness(
                (array) ($profile->lifestyle_tags ?? []),
                (array) ($profile->deal_breaker_flags ?? [])
            );
            return $result;
        }

        try {
            $lifestyleTags    = (array) ($profile->lifestyle_tags    ?? []);
            $dealBreakerFlags = (array) ($profile->deal_breaker_flags ?? []);

            $signals       = $this->extractSignals($lifestyleTags, $dealBreakerFlags);
            $missingInputs = $this->buildMissingInputs($signals);

            [$primary, $secondaries] = $this->classify($signals);

            [$primaryMotivation, $secondaryMotivation] = $this->generateMotivations($primary, $signals);

            $result['success']                   = true;
            $result['status']                    = 'generated';
            $result['listing_id']                = (int) $profile->listing_id;
            $result['primary_avatar']            = $primary;
            $result['secondary_avatars']         = $secondaries;
            $result['signals']                   = $signals;
            $result['missing_inputs']            = $missingInputs;
            $result['primary_motivation']        = $primaryMotivation;
            $result['secondary_motivation']      = $secondaryMotivation;
            $result['tenant_narrative']          = $this->generateNarrative($primary, $signals);
            $result['tenant_preference_summary'] = $this->generatePreferenceSummary($primary, $signals);
            $result['tenant_personality_tags']   = $this->generatePersonalityTags($primary, $signals);
            $result['tenant_match_preferences']  = $this->generateMatchPreferences($signals);
            $result['avatar_confidence_score']   = $this->generateConfidenceScore($completeness, $primary);
            $result['tenant_avatar_version']     = $this->generateAvatarVersion();

            return $result;
        } catch (\Throwable $e) {
            $result['status'] = 'failed';
            $result['error']  = $e->getMessage();
            return $result;
        }
    }

    /**
     * Extract boolean/scalar signal variables from the persisted lifestyle_tags and
     * deal_breaker_flags arrays only.
     *
     * Maps the known tag/flag vocabulary to named signal variables. Handles both
     * tag-based and flag-based sources for signals that may be expressed through
     * either vocabulary (e.g. commercial_signal from 'prefers-type:Commercial' tag
     * OR a 'commercial_interest' flag; pool_required from 'requires:pool' tag OR a
     * 'pool_required' flag).
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
            'lease_option_signal'      => false,
            'has_pets'                 => false,
            'pool_required'            => false,
            'garage_required'          => false,
            'has_property_type'        => false,
            'space_requirement'        => false,
            'budget_ceiling_specified' => false,
            'budget_value'             => null,
        ];

        foreach ($lifestyleTags as $tag) {
            $tag = (string) $tag;

            if ($tag === 'has-pets') {
                $signals['has_pets'] = true;
                continue;
            }
            if ($tag === 'open-to:lease-option') {
                $signals['lease_option_signal'] = true;
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
            if (str_starts_with($tag, 'prefers-type:')) {
                $signals['has_property_type'] = true;
                $typeValue = strtolower(substr($tag, strlen('prefers-type:')));
                if (str_contains($typeValue, 'commercial')) {
                    $signals['commercial_signal'] = true;
                }
                continue;
            }
            // Space requirement signals from bedroom/bathroom/sqft tags.
            if (str_starts_with($tag, 'min-bedrooms:')
                || str_starts_with($tag, 'min-bathrooms:')
                || str_starts_with($tag, 'min-sqft:')
            ) {
                $signals['space_requirement'] = true;
                continue;
            }
        }

        foreach ($dealBreakerFlags as $flagRecord) {
            $flagRecord = (array) $flagRecord;
            $flag       = (string) ($flagRecord['flag'] ?? '');
            $value      = $flagRecord['value'] ?? null;

            if ($flag === 'commercial_interest') {
                $signals['commercial_signal'] = true;
            }
            if ($flag === 'pool_required') {
                $signals['pool_required'] = true;
            }
            if ($flag === 'garage_required') {
                $signals['garage_required'] = true;
            }
            if ($flag === 'space_requirement') {
                $signals['space_requirement'] = true;
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
        }

        return $signals;
    }

    /**
     * Apply ordered, deterministic classification rules to the extracted signals.
     *
     * Rules are evaluated in priority order. The first matching rule sets the
     * primary avatar. All other matching rules populate secondary_avatars.
     * 'Flexible Tenant' is the fallback when signals are present but no specific
     * avatar rule fires. 'Unknown Tenant' is the fallback when no meaningful
     * signals are present at all.
     *
     * Avatar type priority (highest to lowest):
     *   1. Commercial Tenant      — commercial property type signal (tag or flag)
     *   2. Lease-Option Tenant    — open-to:lease-option tag
     *   3. Pet-Conscious Tenant   — has-pets tag
     *   4. Amenity-Focused Tenant — pool_required or garage_required (tag or flag)
     *   5. Space-Focused Tenant   — space requirement signal (bedroom/bathroom/sqft)
     *   6. Budget-Conscious Tenant— budget ceiling specified (flag)
     *   7. Flexible Tenant        — signals present but no rule above fired
     *   8. Unknown Tenant         — no meaningful signals found
     *
     * @param  array $signals  Signal map from extractSignals().
     * @return array{0: string, 1: array}  [primary_avatar, secondary_avatars]
     */
    private function classify(array $signals): array
    {
        $candidates = [];

        // Rule 1 — Commercial Tenant: commercial property type signal (tag or flag).
        if ($signals['commercial_signal']) {
            $candidates[] = 'Commercial Tenant';
        }

        // Rule 2 — Lease-Option Tenant: open-to:lease-option tag.
        if ($signals['lease_option_signal']) {
            $candidates[] = 'Lease-Option Tenant';
        }

        // Rule 3 — Pet-Conscious Tenant: has-pets tag.
        if ($signals['has_pets']) {
            $candidates[] = 'Pet-Conscious Tenant';
        }

        // Rule 4 — Amenity-Focused Tenant: pool or garage required (tag or flag).
        if ($signals['pool_required'] || $signals['garage_required']) {
            $candidates[] = 'Amenity-Focused Tenant';
        }

        // Rule 5 — Space-Focused Tenant: bedroom/bathroom/sqft space requirement signal.
        if ($signals['space_requirement']) {
            $candidates[] = 'Space-Focused Tenant';
        }

        // Rule 6 — Budget-Conscious Tenant: budget ceiling specified.
        if ($signals['budget_ceiling_specified']) {
            $candidates[] = 'Budget-Conscious Tenant';
        }

        // Fallback — Flexible Tenant when signals exist but none of the above fired.
        // Unknown Tenant when no meaningful signals are present.
        if (empty($candidates)) {
            $hasAnyMeaningfulSignal = $signals['has_property_type']
                || $signals['commercial_signal']
                || $signals['lease_option_signal']
                || $signals['has_pets']
                || $signals['pool_required']
                || $signals['garage_required']
                || $signals['space_requirement']
                || $signals['budget_ceiling_specified'];

            $candidates[] = $hasAnyMeaningfulSignal ? 'Flexible Tenant' : 'Unknown Tenant';
        }

        $primary     = $candidates[0];
        $secondaries = array_values(array_slice($candidates, 1));

        return [$primary, $secondaries];
    }

    /**
     * Return [primary_motivation, secondary_motivation] from the standardized tenant vocabulary.
     *
     * Vocabulary: Pet-Friendly Required, Budget Constrained, Amenity Access,
     * Space Requirements, Lease Flexibility, Commercial Use, Stable Housing.
     *
     * Amenity-Focused Tenant secondary is signal-driven:
     *   - garage_required (only) → Space Requirements
     *   - otherwise              → Stable Housing
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return array{0: string|null, 1: string|null}
     */
    private function generateMotivations(string $primaryAvatar, array $signals): array
    {
        if ($primaryAvatar === 'Amenity-Focused Tenant') {
            $secondary = ($signals['garage_required'] && !$signals['pool_required'])
                ? 'Space Requirements'
                : 'Stable Housing';
            return ['Amenity Access', $secondary];
        }

        $map = self::MOTIVATION_MAP[$primaryAvatar] ?? ['primary' => null, 'secondary' => null];
        return [$map['primary'], $map['secondary']];
    }

    /**
     * Return a short deterministic template narrative string for the avatar type.
     * Returns null for Unknown Tenant and insufficient_data paths.
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return string|null
     */
    private function generateNarrative(string $primaryAvatar, array $signals): ?string
    {
        $narratives = [
            'Commercial Tenant'      => 'Seeking a commercial space that aligns with business operational needs and lease flexibility.',
            'Lease-Option Tenant'    => 'Open to a lease-option arrangement as a path toward future ownership with flexible entry terms.',
            'Pet-Conscious Tenant'   => 'Requires a pet-friendly rental that accommodates their pets comfortably and without restriction.',
            'Amenity-Focused Tenant' => 'Prioritizing specific amenities as non-negotiable requirements for their rental search.',
            'Space-Focused Tenant'   => 'Looking for a rental that meets defined minimum space requirements for bedrooms, bathrooms, or square footage.',
            'Budget-Conscious Tenant'=> 'Focused on finding quality rental value within a clearly defined budget ceiling.',
            'Flexible Tenant'        => 'Open to a range of rental options and property types to find the right fit.',
            'Unknown Tenant'         => null,
        ];

        return $narratives[$primaryAvatar] ?? null;
    }

    /**
     * Return a JSON-ready structured array of preference labels grouped by category.
     *
     * Structure:
     *   property_types   — inferred property-type signals (Commercial, or generic)
     *   amenities        — hard amenity requirements (Pool, Garage, Pet Friendly)
     *   budget_signals   — financial signals (Budget Set)
     *   space_requirements — space dimension signals (Space Requirements)
     *
     * Each group is an array of label strings. Empty groups are always present as [].
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return array{property_types: list<string>, amenities: list<string>, budget_signals: list<string>, space_requirements: list<string>}
     */
    private function generatePreferenceSummary(string $primaryAvatar, array $signals): array
    {
        $propertyTypes = [];
        if ($signals['commercial_signal']) {
            $propertyTypes[] = 'Commercial';
        } elseif ($signals['has_property_type']) {
            $propertyTypes[] = 'Property Type Specified';
        }

        $amenities = [];
        if ($signals['pool_required'])   { $amenities[] = 'Pool'; }
        if ($signals['garage_required']) { $amenities[] = 'Garage'; }
        if ($signals['has_pets'])        { $amenities[] = 'Pet Friendly'; }

        $budgetSignals = [];
        if ($signals['budget_ceiling_specified']) { $budgetSignals[] = 'Budget Set'; }
        if ($signals['lease_option_signal'])      { $budgetSignals[] = 'Lease Option'; }

        $spaceRequirements = [];
        if ($signals['space_requirement']) { $spaceRequirements[] = 'Space Requirements'; }

        return [
            'property_types'    => $propertyTypes,
            'amenities'         => $amenities,
            'budget_signals'    => $budgetSignals,
            'space_requirements'=> $spaceRequirements,
        ];
    }

    /**
     * Return a JSON array of personality labels keyed by avatar type and signal presence.
     * Examples: ['Pet Friendly Required', 'Budget Focused', 'Amenity Focused', 'Space Focused']
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return array
     */
    private function generatePersonalityTags(string $primaryAvatar, array $signals): array
    {
        $baseTags = [
            'Commercial Tenant'      => ['Business Oriented', 'Commercial Focused', 'Return Oriented'],
            'Lease-Option Tenant'    => ['Flexibility Seeking', 'Lease Option Interested', 'Stable Housing'],
            'Pet-Conscious Tenant'   => ['Pet Friendly Required', 'Lifestyle Driven', 'Stable Housing'],
            'Amenity-Focused Tenant' => ['Amenity Focused', 'Lifestyle Driven'],
            'Space-Focused Tenant'   => ['Space Focused', 'Stability Seeking'],
            'Budget-Conscious Tenant'=> ['Budget Focused', 'Value Conscious'],
            'Flexible Tenant'        => ['Open Minded', 'Adaptable'],
            'Unknown Tenant'         => [],
        ];

        $tags = $baseTags[$primaryAvatar] ?? [];

        // Signal-based additions (avoid duplicates).
        if (($signals['pool_required'] || $signals['garage_required'])
            && !in_array('Amenity Focused', $tags, true)
        ) {
            $tags[] = 'Amenity Focused';
        }
        if ($signals['space_requirement'] && !in_array('Space Focused', $tags, true)) {
            $tags[] = 'Space Focused';
        }
        if ($signals['budget_ceiling_specified'] && !in_array('Budget Focused', $tags, true)) {
            $tags[] = 'Budget Focused';
        }

        return array_values($tags);
    }

    /**
     * Return a flat JSON array of match-ready preference strings derived directly
     * from signal booleans.
     * Examples: ['Pool', 'Garage', 'Pet Friendly', 'Lease Option']
     *
     * @param  array $signals
     * @return array
     */
    private function generateMatchPreferences(array $signals): array
    {
        $prefs = [];

        if ($signals['commercial_signal'])         { $prefs[] = 'Commercial'; }
        if ($signals['pool_required'])             { $prefs[] = 'Pool'; }
        if ($signals['garage_required'])           { $prefs[] = 'Garage'; }
        if ($signals['has_pets'])                  { $prefs[] = 'Pet Friendly'; }
        if ($signals['lease_option_signal'])       { $prefs[] = 'Lease Option'; }
        if ($signals['space_requirement'])         { $prefs[] = 'Space Requirements'; }
        if ($signals['budget_ceiling_specified'])  { $prefs[] = 'Budget Ceiling'; }

        return $prefs;
    }

    /**
     * Return a deterministic confidence score (0–100).
     *   - Unknown Tenant: caps at 20 regardless of completeness.
     *   - Flexible Tenant: caps at 60 regardless of completeness.
     *   - All others: scales proportionally from preference_completeness (0–100).
     *
     * @param  float  $completeness  0–100 preference completeness score.
     * @param  string $primaryAvatar
     * @return int
     */
    private function generateConfidenceScore(float $completeness, string $primaryAvatar): int
    {
        if ($primaryAvatar === 'Unknown Tenant') {
            return min((int) round($completeness * 0.2), 20);
        }

        if ($primaryAvatar === 'Flexible Tenant') {
            return min((int) round($completeness * 0.6), 60);
        }

        return min((int) round($completeness), 100);
    }

    /**
     * Return the current avatar version constant string.
     * Bump the constant value in a future phase when classification rules change.
     *
     * @return string
     */
    private function generateAvatarVersion(): string
    {
        return self::AVATAR_VERSION;
    }

    /**
     * Build the missing_inputs list from extracted signals.
     *
     * Reports the human-readable signal dimension names that were absent (false/null)
     * so callers know what additional profile data would improve classification.
     *
     * Required dimensions: property type preference, budget ceiling, pet status,
     * lease-option interest, amenity requirements, space requirements, commercial
     * use interest.
     *
     * @param  array $signals  Signal map from extractSignals().
     * @return string[]
     */
    private function buildMissingInputs(array $signals): array
    {
        $missing = [];

        $presenceSignals = [
            'has_property_type'        => 'Property type preference',
            'budget_ceiling_specified' => 'Budget ceiling',
            'has_pets'                 => 'Pet status',
            'lease_option_signal'      => 'Lease-option interest',
            'pool_required'            => 'Amenity requirements (pool)',
            'garage_required'          => 'Amenity requirements (garage)',
            'space_requirement'        => 'Space requirements',
            'commercial_signal'        => 'Commercial use interest',
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
        if (!array_key_exists('has-pets', $presentTags)) {
            $missing[] = 'Pet status';
        }
        if (!array_key_exists('open-to:lease-option', $presentTags)) {
            $missing[] = 'Lease-option interest';
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
            !array_key_exists('requires:pool', $presentTags)
            && !array_key_exists('requires:garage', $presentTags)
            && !isset($presentFlags['pool_required'])
            && !isset($presentFlags['garage_required'])
        ) {
            $missing[] = 'Amenity requirements';
        }

        $hasSpaceReq = false;
        foreach ($lifestyleTags as $tag) {
            $tag = (string) $tag;
            if (str_starts_with($tag, 'min-bedrooms:')
                || str_starts_with($tag, 'min-bathrooms:')
                || str_starts_with($tag, 'min-sqft:')
            ) {
                $hasSpaceReq = true;
                break;
            }
        }
        if (!$hasSpaceReq && !isset($presentFlags['space_requirement'])) {
            $missing[] = 'Space requirements';
        }

        $missing[] = 'Additional preference dimensions (profile completeness below minimum threshold)';

        return $missing;
    }
}
