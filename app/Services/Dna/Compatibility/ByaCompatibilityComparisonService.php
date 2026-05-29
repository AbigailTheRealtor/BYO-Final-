<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaCompatibilityComparisonService — BYA_COMP_V1 Compatibility Comparison Layer
 *
 * Accepts a BYA_NORM_V1 consumer profile (from ByaNormalizationService) and a
 * BYA_AGENT_NORM_V1 agent profile (from ByaAgentResponseNormalizationService) and
 * produces a deterministic, dimension-by-dimension BYA_COMP_V1 comparison payload.
 *
 * This is a pure comparison layer. Its only question is: "how do these two profiles
 * compare across each dimension?" It emits a structured payload that other layers can
 * consume without containing any interpretation of that comparison.
 *
 * GOVERNANCE CONSTRAINTS:
 * - Pure read-only service. Never writes to the database.
 * - No numeric analysis, bias-based analysis, ordering, or advisory-output logic of any kind.
 * - No routes, no UI, no Blade or Livewire changes, no migrations.
 * - Never throws. All error paths return a structurally valid payload.
 * - Output is internal-only structured metadata — never surfaced publicly.
 * - relationship values are strictly: same | similar | different | unknown.
 *   The "similar" value is reserved for future governance-defined mappings;
 *   in Phase I it is never emitted by this service.
 */
class ByaCompatibilityComparisonService
{
    private const VERSION = 'BYA_COMP_V1';

    /**
     * The 12 canonical comparison dimensions, always emitted in this order
     * when at least one profile is structurally valid.
     */
    private const DIMENSIONS = [
        'communication_style',
        'communication_frequency',
        'decision_speed',
        'risk_tolerance',
        'negotiation_style',
        'advisor_expectation',
        'technology_preference',
        'market_education_preference',
        'property_search_involvement',
        'transaction_guidance_level',
        'availability_expectation',
        'personality_style',
    ];

    /**
     * Maps each canonical BYA_COMP_V1 comparison dimension to the trait key used to
     * extract the relevant value from each normalized profile's "traits" array.
     *
     * A null entry means the dimension has no corresponding trait key in the current
     * profile version — that position always resolves to null → relationship: unknown.
     *
     * Format: dimension => [consumer_trait_key, agent_trait_key]
     *
     * MAPPING VERIFICATION TABLE
     * ─────────────────────────────────────────────────────────────────────────────
     *
     * 1. communication_style
     *    Consumer source : communication_channel
     *    Agent source    : communication_channel
     *    Rationale       : Both profiles encode the primary medium of contact
     *                      (phone, email, text, etc.) under this key. This dimension
     *                      captures the channel preference that shapes how each party
     *                      prefers to interact, which is the closest proxy available
     *                      in BYA_NORM_V1 / BYA_AGENT_NORM_V1 for overall style.
     *                      A dedicated style trait (direct vs. collaborative, etc.)
     *                      would require a future profile schema addition.
     *
     * 2. communication_frequency
     *    Consumer source : communication_frequency
     *    Agent source    : communication_frequency
     *    Rationale       : Direct key-for-key match. Both profiles store the desired
     *                      or committed cadence of proactive contact updates here.
     *
     * 3. decision_speed
     *    Consumer source : transaction_pace
     *    Agent source    : transaction_pace
     *    Rationale       : The consumer's timeline sensitivity (e.g. "flexible" vs.
     *                      "urgent") and the agent's timeline management capability
     *                      are both stored in transaction_pace. This is the profile's
     *                      closest structural proxy for how quickly each party moves
     *                      through decisions. A dedicated decision_speed field would
     *                      require a future profile schema addition.
     *
     * 4. risk_tolerance
     *    Consumer source : risk_tolerance
     *    Agent source    : risk_tolerance
     *    Rationale       : Direct key-for-key match. Consumer's transactional risk
     *                      appetite and agent's professional risk posture are both
     *                      stored under risk_tolerance.
     *
     * 5. negotiation_style
     *    Consumer source : negotiation_style
     *    Agent source    : negotiation_style
     *    Rationale       : Direct key-for-key match. Both profiles capture negotiation
     *                      posture (e.g. "collaborative", "competitive") here.
     *
     * 6. advisor_expectation
     *    Consumer source : guidance_level
     *    Agent source    : guidance_level
     *    Rationale       : The consumer's expectation of how much hands-on direction
     *                      they want and the agent's default level of involvement are
     *                      both stored in guidance_level. "Advisor expectation" is the
     *                      demand-side view of this shared dimension.
     *
     * 7. technology_preference
     *    Consumer source : null (structurally unavailable — see note below)
     *    Agent source    : null (structurally unavailable — see note below)
     *    Rationale       : Neither BYA_NORM_V1 nor BYA_AGENT_NORM_V1 currently emits
     *                      a trait slot for technology preference. This dimension always
     *                      resolves to relationship: unknown until a future profile
     *                      version adds the required source fields.
     *                      PLACEHOLDER — awaiting upstream profile schema support.
     *
     * 8. market_education_preference
     *    Consumer source : null (structurally unavailable — see note below)
     *    Agent source    : null (structurally unavailable — see note below)
     *    Rationale       : Neither BYA_NORM_V1 nor BYA_AGENT_NORM_V1 currently emits
     *                      a trait slot for market education preference. This dimension
     *                      always resolves to relationship: unknown until a future
     *                      profile version adds the required source fields.
     *                      PLACEHOLDER — awaiting upstream profile schema support.
     *
     * 9. property_search_involvement
     *    Consumer source : collaboration_style
     *    Agent source    : collaboration_style
     *    Rationale       : collaboration_style encodes the consumer's preferred agent
     *                      operating mode (hands-on, proactive, etc.) and the agent's
     *                      professional style. "Property search involvement" is a
     *                      demand-side label for this shared interaction dimension.
     *
     * 10. transaction_guidance_level
     *    Consumer source : decision_making_style
     *    Agent source    : decision_making_style
     *    Rationale       : The consumer's decision-making approach (data-driven,
     *                      intuitive, etc.) and how the agent supports client decisions
     *                      are both stored in decision_making_style. This dimension
     *                      captures how much guidance is involved at the decision point.
     *
     * 11. availability_expectation
     *    Consumer source : responsiveness_expectation
     *    Agent source    : responsiveness_expectation
     *    Rationale       : The consumer's maximum acceptable agent response time and the
     *                      agent's response time commitment are both stored in
     *                      responsiveness_expectation. "Availability expectation" is the
     *                      demand-side label for this shared dimension.
     *
     * 12. personality_style
     *    Consumer source : representation_philosophy
     *    Agent source    : representation_philosophy
     *    Rationale       : The consumer's high-level beliefs about good representation
     *                      (Seller only, via past_agent_experience) and the agent's
     *                      values-level professional beliefs are both stored in
     *                      representation_philosophy. "Personality style" is the
     *                      informal label for this values alignment dimension.
     */
    private const DIMENSION_TRAIT_MAP = [
        'communication_style'        => ['communication_channel',      'communication_channel'],
        'communication_frequency'    => ['communication_frequency',     'communication_frequency'],
        'decision_speed'             => ['transaction_pace',            'transaction_pace'],
        'risk_tolerance'             => ['risk_tolerance',              'risk_tolerance'],
        'negotiation_style'          => ['negotiation_style',           'negotiation_style'],
        'advisor_expectation'        => ['guidance_level',              'guidance_level'],
        'technology_preference'      => [null,                          null],
        'market_education_preference'=> [null,                          null],
        'property_search_involvement'=> ['collaboration_style',         'collaboration_style'],
        'transaction_guidance_level' => ['decision_making_style',       'decision_making_style'],
        'availability_expectation'   => ['responsiveness_expectation',  'responsiveness_expectation'],
        'personality_style'          => ['representation_philosophy',   'representation_philosophy'],
    ];

    /**
     * Compare a consumer profile and an agent profile and produce a BYA_COMP_V1 payload.
     *
     * The consumer profile must be a BYA_NORM_V1 payload array produced by
     * ByaNormalizationService. The agent profile must be a BYA_AGENT_NORM_V1 payload
     * array produced by ByaAgentResponseNormalizationService.
     *
     * Returns a BYA_COMP_V1 payload containing all 12 canonical dimensions when at
     * least one profile is structurally valid, or a stub payload with dimensions: []
     * when both profiles are invalid, non-arrays, or empty.
     *
     * Never throws. All exceptions are caught and result in the stub payload.
     *
     * @param  mixed  $consumerProfile  BYA_NORM_V1 payload array.
     * @param  mixed  $agentProfile     BYA_AGENT_NORM_V1 payload array.
     * @return array                    BYA_COMP_V1 payload array.
     */
    public function compare(mixed $consumerProfile, mixed $agentProfile): array
    {
        try {
            $consumerValid = $this->isStructurallyValid($consumerProfile);
            $agentValid    = $this->isStructurallyValid($agentProfile);

            if (!$consumerValid && !$agentValid) {
                return $this->buildStubPayload();
            }

            $consumerTraits  = $consumerValid ? (is_array($consumerProfile['traits'] ?? null) ? $consumerProfile['traits'] : []) : [];
            $agentTraits     = $agentValid    ? (is_array($agentProfile['traits']    ?? null) ? $agentProfile['traits']    : []) : [];

            $consumerVersion = $consumerValid ? ($consumerProfile['normalization_version'] ?? null) : null;
            $agentVersion    = $agentValid    ? ($agentProfile['normalization_version']    ?? null) : null;

            $dimensions = [];

            foreach (self::DIMENSIONS as $dimension) {
                $dimensions[$dimension] = $this->computeDimension(
                    $dimension,
                    $consumerTraits,
                    $agentTraits
                );
            }

            return [
                'comparison_version'      => self::VERSION,
                'consumer_profile_version'=> $consumerVersion,
                'agent_profile_version'   => $agentVersion,
                'dimensions'              => $dimensions,
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload();
        }
    }

    // -------------------------------------------------------------------------
    // Dimension computation
    // -------------------------------------------------------------------------

    /**
     * Compute a single comparison dimension from consumer and agent trait arrays.
     *
     * Each dimension emits: consumer, agent, relationship.
     * The method is wrapped so that a failure in any single dimension never
     * prevents the remaining dimensions from being computed.
     */
    private function computeDimension(
        string $dimension,
        array  $consumerTraits,
        array  $agentTraits
    ): array {
        try {
            [$consumerKey, $agentKey] = self::DIMENSION_TRAIT_MAP[$dimension] ?? [null, null];

            $consumerValue = $this->extractTraitValue($consumerTraits, $consumerKey);
            $agentValue    = $this->extractTraitValue($agentTraits,    $agentKey);

            $relationship = $this->resolveRelationship($consumerValue, $agentValue);

            return [
                'consumer'     => $consumerValue,
                'agent'        => $agentValue,
                'relationship' => $relationship,
            ];
        } catch (\Throwable $e) {
            return [
                'consumer'     => null,
                'agent'        => null,
                'relationship' => 'unknown',
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Relationship resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the relationship between a consumer value and an agent value.
     *
     * Rules (Phase I):
     *   - Either value is null → unknown
     *   - Both values are identical (after normalization) → same
     *   - Values differ → different
     *
     * NOTE: "similar" is a valid relationship value in BYA_COMP_V1 but is reserved
     * for future governance-defined similarity mappings (e.g. near-equivalent response
     * time tiers). Phase I never emits "similar" — it is never reachable from this
     * method. When governance defines the similarity tables, they are added here.
     */
    private function resolveRelationship(mixed $consumerValue, mixed $agentValue): string
    {
        if ($consumerValue === null || $agentValue === null) {
            return 'unknown';
        }

        if ($this->valuesAreIdentical($consumerValue, $agentValue)) {
            return 'same';
        }

        return 'different';
    }

    /**
     * Determine whether two non-null values are identical for comparison purposes.
     *
     * Scalar values are compared directly (case-sensitive string equality for strings).
     * Array values are compared as sorted, re-indexed lists so that ordering differences
     * between the consumer and agent selections do not produce false "different" results.
     * Mixed types (one scalar, one array) are never identical.
     */
    private function valuesAreIdentical(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            $aNorm = $this->normalizeArrayValue($a);
            $bNorm = $this->normalizeArrayValue($b);
            return $aNorm === $bNorm;
        }

        if (is_array($a) || is_array($b)) {
            return false;
        }

        return (string) $a === (string) $b;
    }

    /**
     * Normalize an array value for comparison: sort, re-index, and cast each
     * element to string. This ensures two arrays that differ only in element
     * ordering are still treated as identical.
     */
    private function normalizeArrayValue(array $arr): array
    {
        $strings = array_map('strval', $arr);
        sort($strings);
        return array_values($strings);
    }

    // -------------------------------------------------------------------------
    // Value extraction
    // -------------------------------------------------------------------------

    /**
     * Extract the comparable value from a profile's traits array for a given key.
     *
     * Returns null when:
     *   - The trait key is null (dimension has no mapping in the current profile version)
     *   - The trait key is absent from the traits array
     *   - The slot's value is null
     *
     * The slot shape from both normalization services is: {value: mixed|null, missing: bool}
     */
    private function extractTraitValue(array $traits, ?string $key): mixed
    {
        if ($key === null) {
            return null;
        }

        $slot = $traits[$key] ?? null;

        if (!is_array($slot)) {
            return null;
        }

        return $slot['value'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Structural validity
    // -------------------------------------------------------------------------

    /**
     * Determine whether a profile is structurally valid for comparison purposes.
     *
     * A profile is structurally valid when it is a non-empty array. The service
     * does not require specific keys to be present — a profile with unexpected
     * structure is still valid and simply yields null for all trait extractions.
     */
    private function isStructurallyValid(mixed $profile): bool
    {
        return is_array($profile) && !empty($profile);
    }

    // -------------------------------------------------------------------------
    // Stub payload
    // -------------------------------------------------------------------------

    /**
     * Build the stub payload returned when both profiles are invalid, non-arrays,
     * or empty, or when an unrecoverable exception escapes the comparison loop.
     *
     * dimensions: [] signals to callers that no comparison was possible.
     * consumer_profile_version and agent_profile_version are null in the stub because
     * no valid profiles were available to read version metadata from.
     */
    private function buildStubPayload(): array
    {
        return [
            'comparison_version'       => self::VERSION,
            'consumer_profile_version' => null,
            'agent_profile_version'    => null,
            'dimensions'               => [],
        ];
    }
}
