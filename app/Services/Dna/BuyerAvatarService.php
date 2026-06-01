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
     * Avatar version stamp — bump in a future phase when classification rules change.
     */
    private const AVATAR_VERSION = 'BUYER_AVATAR_V1';

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
     * Standardized motivation vocabulary.
     */
    private const MOTIVATION_MAP = [
        'Luxury Buyer'           => ['primary' => 'Lifestyle Upgrade',    'secondary' => 'Investment'],
        'Investor Buyer'         => ['primary' => 'Investment',            'secondary' => 'Cash Flow'],
        'Vacation Buyer'         => ['primary' => 'Lifestyle Upgrade',    'secondary' => 'Retirement Planning'],
        'Move-Up Buyer'          => ['primary' => 'Family Growth',         'secondary' => 'Lifestyle Upgrade'],
        'First-Time Buyer'       => ['primary' => 'Stability',             'secondary' => 'Family Growth'],
        'Budget-Conscious Buyer' => ['primary' => 'Stability',             'secondary' => 'Relocation'],
        'Commercial Buyer'       => ['primary' => 'Investment',            'secondary' => 'Business Expansion'],
        'Waterfront Buyer'       => ['primary' => 'Lifestyle Upgrade',    'secondary' => 'Appreciation'],
        'Downsizing Buyer'       => ['primary' => 'Retirement Planning',  'secondary' => 'Lifestyle Upgrade'],
        'Flexible Buyer'         => ['primary' => 'Relocation',            'secondary' => 'Lifestyle Upgrade'],
        'Unknown Buyer'          => ['primary' => null,                    'secondary' => null],
    ];

    /**
     * Classify a BuyerTenantDnaProfile into structured avatar categories.
     *
     * Reads only lifestyle_tags, deal_breaker_flags, preference_completeness,
     * archetype_label, listing_type, and listing_id from the passed-in profile.
     * No additional database queries are issued.
     *
     * Output contract — always returns exactly these keys:
     *   success                bool
     *   status                 'generated' | 'insufficient_data' | 'failed'
     *   listing_type           'buyer'   (always 'buyer'; this service only processes buyer profiles)
     *   listing_id             int
     *   primary_avatar         string|null
     *   secondary_avatars      array
     *   signals                array
     *   missing_inputs         array
     *   error                  string|null
     *   primary_motivation     string|null
     *   secondary_motivation   string|null
     *   buyer_narrative        string|null
     *   buyer_preference_summary  array
     *   buyer_personality_tags    array
     *   buyer_match_preferences   array
     *   avatar_confidence_score   int
     *   buyer_readiness_score     int
     *   buyer_avatar_version      string
     *
     * @param  BuyerTenantDnaProfile $profile  A cast, in-memory profile instance.
     * @return array
     */
    public function generate(BuyerTenantDnaProfile $profile): array
    {
        // Each output-contract key is defined exactly once here.
        // listing_type is always 'buyer' regardless of what the profile contains
        // — this service is buyer-only.
        $result = [
            'success'                  => false,
            'status'                   => 'insufficient_data',
            'listing_type'             => 'buyer',
            'listing_id'               => (int) ($profile->listing_id ?? 0),
            'primary_avatar'           => null,
            'secondary_avatars'        => [],
            'signals'                  => [],
            'missing_inputs'           => [],
            'error'                    => null,
            'primary_motivation'       => null,
            'secondary_motivation'     => null,
            'buyer_narrative'          => null,
            'buyer_preference_summary' => ['property_types' => [], 'amenities' => [], 'budget_signals' => [], 'financing_signals' => []],
            'buyer_personality_tags'   => [],
            'buyer_match_preferences'  => [],
            'avatar_confidence_score'  => 0,
            'buyer_readiness_score'    => 0,
            'buyer_avatar_version'     => self::AVATAR_VERSION,
        ];

        if (($profile->listing_type ?? '') !== 'buyer') {
            $result['missing_inputs'] = ['listing_type must be buyer'];
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

            // Overwrite only the keys that differ for the generated path.
            $result['success']                  = true;
            $result['status']                   = 'generated';
            $result['listing_id']               = (int) $profile->listing_id;
            $result['primary_avatar']           = $primary;
            $result['secondary_avatars']        = $secondaries;
            $result['signals']                  = $signals;
            $result['missing_inputs']           = $missingInputs;
            $result['primary_motivation']       = $primaryMotivation;
            $result['secondary_motivation']     = $secondaryMotivation;
            $result['buyer_narrative']          = $this->generateNarrative($primary, $signals);
            $result['buyer_preference_summary'] = $this->generatePreferenceSummary($primary, $signals);
            $result['buyer_personality_tags']   = $this->generatePersonalityTags($primary, $signals);
            $result['buyer_match_preferences']  = $this->generateMatchPreferences($signals);
            $result['avatar_confidence_score']  = $this->generateConfidenceScore($completeness, $primary);
            $result['buyer_readiness_score']    = $this->generateReadinessScore($signals);
            $result['buyer_avatar_version']     = $this->generateAvatarVersion();

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
     *   8. Budget-Conscious   — budget set, not pre-approved, not Investor, ≥1 financing signal
     *   9. First-Time Buyer   — budget set, not pre-approved, no financing signals, no prior avatar
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

        // Rule 8 — Budget-Conscious Buyer: budget ceiling set, not pre-approved, not Investor,
        // AND at least one alternative financing signal present (they have explored alternatives
        // but have not secured pre-approval). Requires ≥1 financing signal to be distinct from
        // First-Time Buyer (Rule 9).
        $hasAnyFinancingSignal = $signals['open_to_seller_financing']
            || $signals['open_to_assumable_loan']
            || $signals['open_to_lease_option']
            || $signals['open_to_lease_purchase'];

        if ($signals['budget_ceiling_specified']
            && !$signals['pre_approved']
            && !in_array('Investor Buyer', $candidates, true)
            && $hasAnyFinancingSignal
        ) {
            $candidates[] = 'Budget-Conscious Buyer';
        }

        // Rule 9 — First-Time Buyer: budget set, not pre-approved, no alternative financing
        // signals (no prior financing research), and no prior avatar classification. Distinct
        // from Budget-Conscious by the absence of any financing signal — truly new to the process.
        if ($signals['budget_ceiling_specified']
            && !$signals['pre_approved']
            && !$hasAnyFinancingSignal
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
     * Return [primary_motivation, secondary_motivation] from the standardized vocabulary
     * based on avatar type. Signals parameter is reserved for future signal-based overrides.
     *
     * Vocabulary: Investment, Cash Flow, Appreciation, Retirement Planning,
     * Lifestyle Upgrade, Family Growth, Relocation, Stability, Business Expansion.
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return array{0: string|null, 1: string|null}
     */
    private function generateMotivations(string $primaryAvatar, array $signals): array
    {
        $map = self::MOTIVATION_MAP[$primaryAvatar] ?? ['primary' => null, 'secondary' => null];
        return [$map['primary'], $map['secondary']];
    }

    /**
     * Return a short deterministic template narrative string for the avatar type.
     * Returns null for Unknown Buyer.
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return string|null
     */
    private function generateNarrative(string $primaryAvatar, array $signals): ?string
    {
        $narratives = [
            'Luxury Buyer'           => 'Seeking a premium property that reflects an elevated lifestyle and long-term investment value.',
            'Investor Buyer'         => 'Looking for a property with flexible ownership and financing options to maximize investment returns.',
            'Vacation Buyer'         => 'In search of an ideal vacation or resort retreat for personal enjoyment and seasonal use.',
            'Move-Up Buyer'          => 'Ready to upgrade to a larger home with the amenities needed for the next chapter of life.',
            'First-Time Buyer'       => 'Taking the first step toward homeownership with a clear budget and growing financial confidence.',
            'Budget-Conscious Buyer' => 'Focused on finding the best value within a defined budget to achieve long-term stability.',
            'Commercial Buyer'       => 'Pursuing a commercial property opportunity aligned with business expansion and investment goals.',
            'Waterfront Buyer'       => 'Drawn to waterfront living for its lifestyle appeal and long-term appreciation potential.',
            'Downsizing Buyer'       => 'Simplifying into a right-sized home, ideally in an active adult or 55-plus community.',
            'Flexible Buyer'         => 'Open to a range of property types and purchase structures to find the right opportunity.',
            'Unknown Buyer'          => null,
        ];

        return $narratives[$primaryAvatar] ?? null;
    }

    /**
     * Return a JSON-ready structured array of preference labels grouped by category.
     *
     * Structure:
     *   property_types   — inferred property-type signals (Commercial, Waterfront, Vacation)
     *   amenities        — hard amenity and community requirements (Pool, Garage, 55-Plus)
     *   budget_signals   — financial qualification signals (Pre-Approved, Budget Set)
     *   financing_signals — alternative financing interest signals (Seller Financing, etc.)
     *
     * Each group is an array of label strings. Empty groups are always present as [].
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return array{property_types: list<string>, amenities: list<string>, budget_signals: list<string>, financing_signals: list<string>}
     */
    private function generatePreferenceSummary(string $primaryAvatar, array $signals): array
    {
        $propertyTypes = [];
        if ($signals['commercial_signal'])  { $propertyTypes[] = 'Commercial'; }
        if ($signals['waterfront_signal'])  { $propertyTypes[] = 'Waterfront'; }
        if ($signals['vacation_signal'])    { $propertyTypes[] = 'Vacation/Resort'; }
        if ($signals['has_property_type'] && empty($propertyTypes)) {
            $propertyTypes[] = 'Property Type Specified';
        }

        $amenities = [];
        if ($signals['pool_required'])   { $amenities[] = 'Pool'; }
        if ($signals['garage_required']) { $amenities[] = 'Garage'; }
        if ($signals['seeks_55_plus'])   { $amenities[] = '55-Plus Community'; }

        $budgetSignals = [];
        if ($signals['pre_approved'])             { $budgetSignals[] = 'Pre-Approved'; }
        if ($signals['budget_ceiling_specified']) { $budgetSignals[] = 'Budget Set'; }

        $financingSignals = [];
        if ($signals['open_to_seller_financing']) { $financingSignals[] = 'Seller Financing'; }
        if ($signals['open_to_assumable_loan'])   { $financingSignals[] = 'Assumable Loan'; }
        if ($signals['open_to_lease_option'])     { $financingSignals[] = 'Lease Option'; }
        if ($signals['open_to_lease_purchase'])   { $financingSignals[] = 'Lease Purchase'; }

        return [
            'property_types'    => $propertyTypes,
            'amenities'         => $amenities,
            'budget_signals'    => $budgetSignals,
            'financing_signals' => $financingSignals,
        ];
    }

    /**
     * Return a JSON array of personality labels keyed by avatar type and signal presence.
     * Examples: ['Investment Focused', 'Lifestyle Driven', 'Flexible Financing', 'Amenity Focused']
     *
     * @param  string $primaryAvatar
     * @param  array  $signals
     * @return array
     */
    private function generatePersonalityTags(string $primaryAvatar, array $signals): array
    {
        $baseTags = [
            'Luxury Buyer'           => ['Lifestyle Driven', 'Investment Focused', 'Quality Conscious'],
            'Investor Buyer'         => ['Investment Focused', 'Flexible Financing', 'Return Oriented'],
            'Vacation Buyer'         => ['Lifestyle Driven', 'Leisure Focused', 'Seasonal Buyer'],
            'Move-Up Buyer'          => ['Growth Oriented', 'Amenity Focused', 'Pre-Approved Buyer'],
            'First-Time Buyer'       => ['Goal Oriented', 'Value Conscious', 'Budget Aware'],
            'Budget-Conscious Buyer' => ['Value Conscious', 'Budget Aware', 'Stability Seeking'],
            'Commercial Buyer'       => ['Investment Focused', 'Business Oriented', 'Return Oriented'],
            'Waterfront Buyer'       => ['Lifestyle Driven', 'Amenity Focused', 'Appreciation Minded'],
            'Downsizing Buyer'       => ['Lifestyle Driven', 'Stability Seeking', 'Community Oriented'],
            'Flexible Buyer'         => ['Open Minded', 'Opportunity Driven', 'Adaptable'],
            'Unknown Buyer'          => [],
        ];

        $tags = $baseTags[$primaryAvatar] ?? [];

        // Signal-based additions (avoid duplicates).
        if ($signals['open_to_seller_financing'] || $signals['open_to_assumable_loan']
            || $signals['open_to_lease_option'] || $signals['open_to_lease_purchase']
        ) {
            if (!in_array('Flexible Financing', $tags, true)) {
                $tags[] = 'Flexible Financing';
            }
        }
        if (($signals['pool_required'] || $signals['garage_required'])
            && !in_array('Amenity Focused', $tags, true)
        ) {
            $tags[] = 'Amenity Focused';
        }

        return array_values($tags);
    }

    /**
     * Return a flat JSON array of match-ready preference strings derived directly
     * from signal booleans. Examples: ['Waterfront', 'Pool', 'Garage', 'Seller Financing']
     *
     * @param  array $signals
     * @return array
     */
    private function generateMatchPreferences(array $signals): array
    {
        $prefs = [];

        if ($signals['waterfront_signal'])         { $prefs[] = 'Waterfront'; }
        if ($signals['vacation_signal'])           { $prefs[] = 'Vacation'; }
        if ($signals['commercial_signal'])         { $prefs[] = 'Commercial'; }
        if ($signals['pool_required'])             { $prefs[] = 'Pool'; }
        if ($signals['garage_required'])           { $prefs[] = 'Garage'; }
        if ($signals['seeks_55_plus'])             { $prefs[] = '55-Plus Community'; }
        if ($signals['open_to_seller_financing'])  { $prefs[] = 'Seller Financing'; }
        if ($signals['open_to_assumable_loan'])    { $prefs[] = 'Assumable Loan'; }
        if ($signals['open_to_lease_option'])      { $prefs[] = 'Lease Option'; }
        if ($signals['open_to_lease_purchase'])    { $prefs[] = 'Lease Purchase'; }
        if ($signals['pre_approved'])              { $prefs[] = 'Pre-Approved'; }
        if ($signals['budget_ceiling_specified'])  { $prefs[] = 'Budget Ceiling'; }

        return $prefs;
    }

    /**
     * Return a deterministic confidence score (0–100).
     *   - Unknown Buyer: caps at 20 regardless of completeness.
     *   - Flexible Buyer: caps at 60 regardless of completeness.
     *   - All others: scales proportionally from preference_completeness (0–100).
     *
     * @param  float  $completeness  0–100 preference completeness score.
     * @param  string $primaryAvatar
     * @return int
     */
    private function generateConfidenceScore(float $completeness, string $primaryAvatar): int
    {
        if ($primaryAvatar === 'Unknown Buyer') {
            return min((int) round($completeness * 0.2), 20);
        }

        if ($primaryAvatar === 'Flexible Buyer') {
            return min((int) round($completeness * 0.6), 60);
        }

        return min((int) round($completeness), 100);
    }

    /**
     * Return a deterministic readiness score (0–100) measuring transactional readiness,
     * distinct from profile completeness confidence.
     *
     * Scoring:
     *   pre_approved present:                 +30
     *   budget_ceiling_specified:             +25
     *   at least one financing preference:    +20
     *   property type preference present:     +15
     *   amenity requirement (pool or garage): +10
     *   Total caps at 100.
     *
     * @param  array $signals
     * @return int
     */
    private function generateReadinessScore(array $signals): int
    {
        $score = 0;

        if ($signals['pre_approved']) {
            $score += 30;
        }
        if ($signals['budget_ceiling_specified']) {
            $score += 25;
        }
        if ($signals['open_to_seller_financing'] || $signals['open_to_assumable_loan']
            || $signals['open_to_lease_option'] || $signals['open_to_lease_purchase']
        ) {
            $score += 20;
        }
        if ($signals['has_property_type']) {
            $score += 15;
        }
        if ($signals['pool_required'] || $signals['garage_required']) {
            $score += 10;
        }

        return min($score, 100);
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
