<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaAgentResponseNormalizationService — BYA_AGENT_NORM_V1 Agent Normalization Engine
 *
 * Reads an agent bid's compatibility response sections from the EAV meta store
 * (via HasCompatibilityPreferences) and emits a frozen BYA_AGENT_NORM_V1 payload
 * conforming to the same 12-trait canonical shape as the consumer-side BYA_NORM_V1
 * payload produced by ByaNormalizationService.
 *
 * Data is read from the 7 canonical agent response sections:
 *   compatibility_preferences.agent_response.{section}
 *
 * GOVERNANCE CONSTRAINTS:
 * - Pure read-only service. Never writes to the database.
 * - No scoring, weighting, matching, ranking, or recommendation logic.
 * - No consumer-side listing data is read or compared.
 * - No Blade, Livewire, route, controller, or migration changes.
 * - Never throws. All error paths return a structurally valid stub payload.
 * - Output is internal-only structured metadata — never surfaced publicly.
 */
class ByaAgentResponseNormalizationService
{
    private const VERSION = 'BYA_AGENT_NORM_V1';

    private const SUPPORTED_ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /**
     * The 12 canonical trait slot keys, always emitted in this order.
     */
    private const TRAIT_KEYS = [
        'communication_channel',
        'communication_frequency',
        'responsiveness_expectation',
        'negotiation_style',
        'guidance_level',
        'decision_making_style',
        'transaction_pace',
        'risk_tolerance',
        'collaboration_style',
        'representation_priorities',
        'representation_philosophy',
        'property_strategy_fit',
    ];

    /**
     * The 7 canonical agent response section names (from HasCompatibilityPreferences).
     */
    private const SECTIONS = [
        'communication_preferences',
        'negotiation_approach',
        'guidance_style',
        'collaboration_preferences',
        'transaction_strategy',
        'representation_philosophy',
        'representation_priorities',
    ];

    /**
     * Agent-side field names that carry proxy risk — these must never be used
     * as trait values and must be surfaced in proxy_risk_flags only.
     *
     * Key: field name within a section.
     * Value: [section, risk_category]
     */
    private const PROXY_RISK_FIELDS = [
        'agent_tenant_screening_strictness'     => ['representation_philosophy', 'tenant_screening_strictness'],
        'agent_tenant_profile_specialization'   => ['representation_philosophy', 'tenant_profile_specialization'],
        'agent_property_strategy_specialization' => ['transaction_strategy', 'property_strategy_specialization'],
    ];

    /**
     * Normalize an agent bid's compatibility response into a BYA_AGENT_NORM_V1 payload.
     *
     * The $agentBid argument must expose loadCompatibilityPreferences() (via the
     * HasCompatibilityPreferences trait). Any bid model using that trait is accepted.
     *
     * Never throws. Returns a structurally valid payload even when data is missing,
     * malformed, or the role is unrecognised.
     *
     * @param  mixed   $agentBid  An agent bid model instance with HasCompatibilityPreferences.
     * @param  string  $role      One of: seller, buyer, landlord, tenant.
     * @return array              BYA_AGENT_NORM_V1 payload array.
     */
    public function normalize(mixed $agentBid, string $role): array
    {
        try {
            $role = strtolower(trim((string) $role));

            if (!in_array($role, self::SUPPORTED_ROLES, true)) {
                return $this->buildStubPayload($agentBid, 'unknown');
            }

            $sections = $this->loadSections($agentBid);

            $traits = [];
            foreach (self::TRAIT_KEYS as $traitKey) {
                $traits[$traitKey] = $this->resolveSlot($traitKey, $sections);
            }

            return [
                'normalization_version' => self::VERSION,
                'role'                  => $role,
                'bid_id'                => $this->extractBidId($agentBid),
                'traits'                => $traits,
                'informational_context' => $this->buildInformationalContext($sections),
                'proxy_risk_flags'      => $this->buildProxyRiskFlags($sections),
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload($agentBid, $role ?? 'unknown');
        }
    }

    // -------------------------------------------------------------------------
    // Section loading
    // -------------------------------------------------------------------------

    /**
     * Load all 7 compatibility sections from the agent bid via HasCompatibilityPreferences.
     * Returns an array keyed by section name; missing or malformed sections are null.
     * Never throws.
     */
    private function loadSections(mixed $agentBid): array
    {
        try {
            if (method_exists($agentBid, 'loadCompatibilityPreferences')) {
                $loaded = $agentBid->loadCompatibilityPreferences();
                if (is_array($loaded)) {
                    return $loaded;
                }
            }
        } catch (\Throwable $e) {
        }

        return array_fill_keys(self::SECTIONS, null);
    }

    // -------------------------------------------------------------------------
    // Trait slot dispatch
    // -------------------------------------------------------------------------

    /**
     * Dispatch a single trait key to its resolver.
     * Each resolver is isolated; a failure in one never affects another.
     */
    private function resolveSlot(string $trait, array $sections): array
    {
        try {
            return match ($trait) {
                'communication_channel'      => $this->resolveCommunicationChannel($sections),
                'communication_frequency'    => $this->resolveCommunicationFrequency($sections),
                'responsiveness_expectation' => $this->resolveResponsivenessExpectation($sections),
                'negotiation_style'          => $this->resolveNegotiationStyle($sections),
                'guidance_level'             => $this->resolveGuidanceLevel($sections),
                'decision_making_style'      => $this->resolveDecisionMakingStyle($sections),
                'transaction_pace'           => $this->resolveTransactionPace($sections),
                'risk_tolerance'             => $this->resolveRiskTolerance($sections),
                'collaboration_style'        => $this->resolveCollaborationStyle($sections),
                'representation_priorities'  => $this->resolveRepresentationPriorities($sections),
                'representation_philosophy'  => $this->resolveRepresentationPhilosophy($sections),
                'property_strategy_fit'      => $this->resolvePropertyStrategyFit($sections),
                default                      => ['value' => null, 'missing' => false],
            };
        } catch (\Throwable $e) {
            return ['value' => null, 'missing' => false];
        }
    }

    // -------------------------------------------------------------------------
    // Per-trait slot resolvers (role-neutral)
    //
    // Missing section   → skipped: { value: null, missing: false }
    // Missing field key → skipped: { value: null, missing: false }
    // Unknown role      → absent:  { value: null, missing: true  } (only via stub)
    // -------------------------------------------------------------------------

    /**
     * communication_channel — channels the agent reliably uses with clients.
     * Section: communication_preferences → field: agent_communication_channels (multi-select)
     */
    private function resolveCommunicationChannel(array $sections): array
    {
        return $this->slotFromSection(
            $sections['communication_preferences'] ?? null,
            'agent_communication_channels'
        );
    }

    /**
     * communication_frequency — the agent's standard proactive contact cadence.
     * Section: communication_preferences → field: agent_communication_frequency (single-select)
     */
    private function resolveCommunicationFrequency(array $sections): array
    {
        return $this->slotFromSection(
            $sections['communication_preferences'] ?? null,
            'agent_communication_frequency'
        );
    }

    /**
     * responsiveness_expectation — the agent's realistic inbound response time commitment.
     * Section: communication_preferences → field: agent_response_time_commitment (single-select)
     */
    private function resolveResponsivenessExpectation(array $sections): array
    {
        return $this->slotFromSection(
            $sections['communication_preferences'] ?? null,
            'agent_response_time_commitment'
        );
    }

    /**
     * negotiation_style — the agent's negotiation posture and philosophy.
     * Section: negotiation_approach → field: agent_negotiation_style (single-select)
     */
    private function resolveNegotiationStyle(array $sections): array
    {
        return $this->slotFromSection(
            $sections['negotiation_approach'] ?? null,
            'agent_negotiation_style'
        );
    }

    /**
     * guidance_level — the agent's default direction and involvement level.
     * Section: guidance_style → field: agent_guidance_level (single-select)
     */
    private function resolveGuidanceLevel(array $sections): array
    {
        return $this->slotFromSection(
            $sections['guidance_style'] ?? null,
            'agent_guidance_level'
        );
    }

    /**
     * decision_making_style — how the agent supports client decision-making.
     * Section: representation_philosophy → field: agent_decision_support_style (single-select)
     */
    private function resolveDecisionMakingStyle(array $sections): array
    {
        return $this->slotFromSection(
            $sections['representation_philosophy'] ?? null,
            'agent_decision_support_style'
        );
    }

    /**
     * transaction_pace — the agent's timeline management capability.
     * Section: transaction_strategy → field: agent_transaction_pace (single-select)
     */
    private function resolveTransactionPace(array $sections): array
    {
        return $this->slotFromSection(
            $sections['transaction_strategy'] ?? null,
            'agent_transaction_pace'
        );
    }

    /**
     * risk_tolerance — the agent's professional comfort with transactional risk.
     * Section: representation_philosophy → field: agent_risk_posture (single-select)
     *
     * Note: agent_risk_posture is a general risk comfort field. The separate proxy-risk
     * fields agent_tenant_screening_strictness and agent_tenant_profile_specialization
     * are surfaced only in proxy_risk_flags and informational_context — not here.
     */
    private function resolveRiskTolerance(array $sections): array
    {
        return $this->slotFromSection(
            $sections['representation_philosophy'] ?? null,
            'agent_risk_posture'
        );
    }

    /**
     * collaboration_style — the agent's overall professional operating mode.
     * Section: collaboration_preferences → field: agent_collaboration_style (single-select)
     */
    private function resolveCollaborationStyle(array $sections): array
    {
        return $this->slotFromSection(
            $sections['collaboration_preferences'] ?? null,
            'agent_collaboration_style'
        );
    }

    /**
     * representation_priorities — the agent's primary capability strengths (role-scoped multi-select).
     * Section: representation_priorities → field: agent_representation_priorities (multi-select)
     */
    private function resolveRepresentationPriorities(array $sections): array
    {
        return $this->slotFromSection(
            $sections['representation_priorities'] ?? null,
            'agent_representation_priorities'
        );
    }

    /**
     * representation_philosophy — the agent's values-level professional beliefs.
     * Section: representation_philosophy → field: agent_representation_philosophy (single/multi-select)
     */
    private function resolveRepresentationPhilosophy(array $sections): array
    {
        return $this->slotFromSection(
            $sections['representation_philosophy'] ?? null,
            'agent_representation_philosophy'
        );
    }

    /**
     * property_strategy_fit — transaction types and goals the agent has experience with.
     * Section: transaction_strategy → field: agent_strategy_experience (multi-select)
     *
     * Note: agent_property_strategy_specialization is a separate proxy-risk field within
     * this section — it travels to proxy_risk_flags and informational_context only.
     */
    private function resolvePropertyStrategyFit(array $sections): array
    {
        return $this->slotFromSection(
            $sections['transaction_strategy'] ?? null,
            'agent_strategy_experience'
        );
    }

    // -------------------------------------------------------------------------
    // informational_context builder
    // -------------------------------------------------------------------------

    /**
     * Collect all free-text, narrative, and proxy-risk raw values from all 7 sections.
     * These fields must never substitute for trait values.
     *
     * Informational fields sourced per section:
     *   communication_preferences → agent_communication_notes, agent_availability_notes
     *   negotiation_approach      → agent_negotiation_notes
     *   guidance_style            → agent_guidance_notes
     *   collaboration_preferences → agent_availability_windows
     *   transaction_strategy      → agent_strategy_notes
     *   representation_philosophy → agent_philosophy_narrative, agent_philosophy_notes
     *   representation_priorities → agent_priority_notes, plus role-scoped priority notes
     *
     * Proxy-risk raw values are also included here for audit traceability.
     */
    private function buildInformationalContext(array $sections): array
    {
        $context = [];

        // communication_preferences
        $commSection = $sections['communication_preferences'] ?? null;
        if (is_array($commSection)) {
            $this->addInfoScalar($context, $commSection, 'agent_communication_notes');
            $this->addInfoScalar($context, $commSection, 'agent_availability_notes');
        }

        // negotiation_approach
        $negSection = $sections['negotiation_approach'] ?? null;
        if (is_array($negSection)) {
            $this->addInfoScalar($context, $negSection, 'agent_negotiation_notes');
        }

        // guidance_style
        $guidSection = $sections['guidance_style'] ?? null;
        if (is_array($guidSection)) {
            $this->addInfoScalar($context, $guidSection, 'agent_guidance_notes');
        }

        // collaboration_preferences
        $collabSection = $sections['collaboration_preferences'] ?? null;
        if (is_array($collabSection)) {
            $this->addInfoScalar($context, $collabSection, 'agent_availability_windows');
        }

        // transaction_strategy
        $stratSection = $sections['transaction_strategy'] ?? null;
        if (is_array($stratSection)) {
            $this->addInfoScalar($context, $stratSection, 'agent_strategy_notes');
            // Proxy-risk field raw value for audit traceability
            $proxyStratRaw = $stratSection['agent_property_strategy_specialization'] ?? null;
            if ($this->isNonEmpty($proxyStratRaw)) {
                $context['agent_property_strategy_specialization_raw'] = $proxyStratRaw;
            }
        }

        // representation_philosophy
        $philSection = $sections['representation_philosophy'] ?? null;
        if (is_array($philSection)) {
            $this->addInfoScalar($context, $philSection, 'agent_philosophy_narrative');
            $this->addInfoScalar($context, $philSection, 'agent_philosophy_notes');
            // Proxy-risk field raw values for audit traceability
            $screeningRaw = $philSection['agent_tenant_screening_strictness'] ?? null;
            if ($this->isNonEmpty($screeningRaw)) {
                $context['agent_tenant_screening_strictness_raw'] = $screeningRaw;
            }
            $tenantSpecRaw = $philSection['agent_tenant_profile_specialization'] ?? null;
            if ($this->isNonEmpty($tenantSpecRaw)) {
                $context['agent_tenant_profile_specialization_raw'] = $tenantSpecRaw;
            }
        }

        // representation_priorities
        $priorSection = $sections['representation_priorities'] ?? null;
        if (is_array($priorSection)) {
            $this->addInfoScalar($context, $priorSection, 'agent_priority_notes');
            // Role-scoped priority notes
            foreach (['seller', 'buyer', 'landlord', 'tenant'] as $scopedRole) {
                $noteKey = "agent_{$scopedRole}_priority_notes";
                $noteValue = $this->infoScalar($priorSection, $noteKey);
                if ($noteValue !== null) {
                    $context[$noteKey] = $noteValue;
                }
            }
        }

        return $context;
    }

    // -------------------------------------------------------------------------
    // proxy_risk_flags (top-level)
    // -------------------------------------------------------------------------

    /**
     * Build the top-level proxy_risk_flags array.
     *
     * Three agent-side fields carry proxy risk and must be surfaced here only —
     * not as trait values:
     *
     * 1. agent_tenant_screening_strictness (in representation_philosophy):
     *    The agent's stated stance on tenant screening strictness may correlate with
     *    protected-class characteristics under the Fair Housing Act.
     *
     * 2. agent_tenant_profile_specialization (in representation_philosophy):
     *    The type of tenants the agent reports specializing in may proxy for
     *    protected-class demographics.
     *
     * 3. agent_property_strategy_specialization (in transaction_strategy):
     *    The agent's stated transaction type specialization may in some markets
     *    correlate with the demographics of the communities they serve.
     *
     * Empty array when no proxy-risk fields are populated.
     */
    private function buildProxyRiskFlags(array $sections): array
    {
        $flags = [];

        $philSection = $sections['representation_philosophy'] ?? null;

        // 1. Tenant screening strictness
        if (
            is_array($philSection) &&
            $this->isNonEmpty($philSection['agent_tenant_screening_strictness'] ?? null)
        ) {
            $flags[] = [
                'field'   => 'agent_tenant_screening_strictness',
                'section' => 'representation_philosophy',
                'reason'  => 'agent_tenant_screening_strictness reflects the agent\'s stated tenant ' .
                             'screening strictness posture. Scoring use must not disadvantage agents ' .
                             'who serve tenants with non-standard financial profiles, who are ' .
                             'disproportionately members of protected classes under the Fair Housing Act. ' .
                             'This field must never be used to weight, penalize, or filter agents on ' .
                             'a demographic basis.',
            ];
        }

        // 2. Tenant profile specialization
        if (
            is_array($philSection) &&
            $this->isNonEmpty($philSection['agent_tenant_profile_specialization'] ?? null)
        ) {
            $flags[] = [
                'field'   => 'agent_tenant_profile_specialization',
                'section' => 'representation_philosophy',
                'reason'  => 'agent_tenant_profile_specialization indicates the types of tenants the ' .
                             'agent reports specializing in serving. This field may correlate with ' .
                             'protected-class characteristics under the Fair Housing Act. Scoring use ' .
                             'is restricted to matching an agent\'s stated commercial-versus-residential ' .
                             'tenant specialization. This field must never be used to weight, penalize, ' .
                             'or filter agents on a demographic basis.',
            ];
        }

        // 3. Property strategy specialization
        $stratSection = $sections['transaction_strategy'] ?? null;
        if (
            is_array($stratSection) &&
            $this->isNonEmpty($stratSection['agent_property_strategy_specialization'] ?? null)
        ) {
            $flags[] = [
                'field'   => 'agent_property_strategy_specialization',
                'section' => 'transaction_strategy',
                'reason'  => 'agent_property_strategy_specialization reflects the agent\'s stated ' .
                             'transaction type and property strategy specialization. In some markets, ' .
                             'transaction type specialization may correlate with the demographics of ' .
                             'the communities served. This field must be evaluated for disparate impact ' .
                             'risk before any scoring use.',
            ];
        }

        return $flags;
    }

    // -------------------------------------------------------------------------
    // Stub payload (unknown role or total data failure)
    // -------------------------------------------------------------------------

    /**
     * Build a structurally valid payload where all traits are absent (missing: true).
     * Used for unknown roles and unrecoverable data failures.
     */
    private function buildStubPayload(mixed $agentBid, string $role): array
    {
        $traits = [];
        foreach (self::TRAIT_KEYS as $key) {
            $traits[$key] = ['value' => null, 'missing' => true];
        }

        return [
            'normalization_version' => self::VERSION,
            'role'                  => $role,
            'bid_id'                => $this->extractBidId($agentBid),
            'traits'                => $traits,
            'informational_context' => [],
            'proxy_risk_flags'      => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Low-level slot and value helpers
    // -------------------------------------------------------------------------

    /**
     * Build a standard trait slot from a section array and a field key.
     *
     * Three-state model for agent-side normalization:
     *   - Section present + key present + non-empty value → answered  (value: <v>, missing: false)
     *   - Section present + key present + empty/null      → skipped   (value: null, missing: false)
     *   - Section absent (null)                           → skipped   (value: null, missing: false)
     *
     * The "absent" state (missing: true) is ONLY produced by buildStubPayload for unknown roles.
     * A missing section on a valid role is always "skipped", never "absent" — the agent simply
     * did not complete that section yet.
     *
     * @param  array|null  $section  Decoded section array, or null if the section is not present.
     * @param  string      $key      The field name within the section.
     */
    private function slotFromSection(?array $section, string $key): array
    {
        if ($section === null) {
            return ['value' => null, 'missing' => false];
        }

        $rawValue = $section[$key] ?? null;

        if (is_array($rawValue)) {
            $filtered = array_values(
                array_filter($rawValue, static fn ($v) => $v !== null && $v !== '')
            );
            return [
                'value'   => empty($filtered) ? null : $filtered,
                'missing' => false,
            ];
        }

        return [
            'value'   => ($rawValue !== null && $rawValue !== '') ? $rawValue : null,
            'missing' => false,
        ];
    }

    /**
     * Extract a scalar informational value — null when absent or empty.
     */
    private function infoScalar(array $section, string $key): mixed
    {
        $v = $section[$key] ?? null;
        if ($v === '' || $v === []) {
            return null;
        }
        return $v;
    }

    /**
     * Add a scalar informational value to the context array only when it is non-null.
     */
    private function addInfoScalar(array &$context, array $section, string $key): void
    {
        $v = $this->infoScalar($section, $key);
        if ($v !== null) {
            $context[$key] = $v;
        }
    }

    private function extractBidId(mixed $agentBid): ?int
    {
        try {
            if (isset($agentBid->id)) {
                return (int) $agentBid->id;
            }
            if (method_exists($agentBid, 'getKey')) {
                return (int) $agentBid->getKey();
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private function isNonEmpty(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
