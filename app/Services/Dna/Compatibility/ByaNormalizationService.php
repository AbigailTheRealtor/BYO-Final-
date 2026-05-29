<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaNormalizationService — Milestone 2: BYA_NORM_V1 Consumer Normalization Engine
 *
 * Reads a consumer listing's raw `compatibility_preferences` EAV meta blob and emits
 * a frozen BYA_NORM_V1 payload conforming to the contract defined in
 * `docs/BIDYOURAGENT_NORMALIZATION_SCHEMA.md`.
 *
 * GOVERNANCE CONSTRAINTS:
 * - Pure read-only service. Never writes to the database.
 * - No scoring, weighting, matching, ranking, or recommendation logic.
 * - No agent-side bid data is read or compared.
 * - No Blade, Livewire, route, controller, or migration changes.
 * - Never throws. All error paths return a structurally valid stub payload.
 * - Output is internal-only structured metadata — never surfaced publicly.
 */
class ByaNormalizationService
{
    private const VERSION = 'BYA_NORM_V1';

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
     * Exact reason string for the in-slot proxy_risk_flags entry (Section 4.3.3).
     */
    private const PROXY_FLAG_REASON_IN_SLOT =
        'tenant_type_preference includes options that may correlate with protected-class characteristics. ' .
        'Scoring use is restricted to agent stated specialization; this value must never weight or penalize ' .
        'agents on a demographic basis.';

    /**
     * Exact reason string for the top-level proxy_risk_flags entry (Section 6).
     */
    private const PROXY_FLAG_REASON_TOP_LEVEL =
        'tenant_type_preference includes options (Individual / Family, Young Professionals, Students, ' .
        'Corporate / Relocation, Small Business, Retail Business, Office Tenant) that may correlate with ' .
        'protected-class characteristics under the Fair Housing Act. Scoring use is restricted to matching ' .
        'an agent\'s stated commercial-versus-residential tenant specialization. This field must never be ' .
        'used to weight, penalize, or filter agents on the basis of which demographic group they serve.';

    /**
     * Normalize a consumer listing's compatibility preferences into a BYA_NORM_V1 payload.
     *
     * The $listing argument must expose an info() method (the EAV meta accessor) that can
     * return the raw compatibility_preferences JSON blob. Any model using the standard
     * saveMeta/info() EAV pattern is accepted.
     *
     * Never throws. Returns a structurally valid payload even when data is missing,
     * malformed, or the role is unrecognised.
     *
     * @param  mixed   $listing  A listing model instance with an info() accessor.
     * @param  string  $role     One of: seller, buyer, landlord, tenant.
     * @return array             BYA_NORM_V1 payload array.
     */
    public function normalize(mixed $listing, string $role): array
    {
        try {
            $role = strtolower(trim((string) $role));

            if (!in_array($role, self::SUPPORTED_ROLES, true)) {
                return $this->buildStubPayload($listing, 'unknown');
            }

            $raw = $this->extractRawData($listing, $role);

            $traits = [];
            foreach (self::TRAIT_KEYS as $traitKey) {
                $traits[$traitKey] = $this->resolveSlot($traitKey, $role, $raw);
            }

            return [
                'normalization_version' => self::VERSION,
                'role'                  => $role,
                'listing_id'            => $this->extractListingId($listing),
                'traits'                => $traits,
                'informational_context' => $this->buildInformationalContext($role, $raw),
                'proxy_risk_flags'      => $this->buildProxyRiskFlags($role, $raw),
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload($listing, $role ?? 'unknown');
        }
    }

    // -------------------------------------------------------------------------
    // Raw data extraction
    // -------------------------------------------------------------------------

    /**
     * Read the role-specific raw sub-array from the listing's EAV meta.
     *
     * Tries dot-notation access first, then falls back to reading the top-level
     * 'compatibility_preferences' key and decoding the JSON blob. Returns an
     * empty array on any failure — never throws.
     */
    private function extractRawData(mixed $listing, string $role): array
    {
        try {
            $namespace = "{$role}_specific";

            // Attempt 1: dot-notation sub-key (some implementations store this way)
            $dotResult = $listing->info("compatibility_preferences.{$namespace}");
            if ($dotResult !== null && $dotResult !== false && $dotResult !== '') {
                if (is_array($dotResult)) {
                    return $dotResult;
                }
                $decoded = json_decode((string) $dotResult, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }

            // Attempt 2: top-level JSON blob
            $blob = $listing->info('compatibility_preferences');
            if ($blob === null || $blob === false || $blob === '') {
                return [];
            }

            if (is_array($blob)) {
                return is_array($blob[$namespace] ?? null) ? $blob[$namespace] : [];
            }

            $decoded = json_decode((string) $blob, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return [];
            }

            return is_array($decoded[$namespace] ?? null) ? $decoded[$namespace] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Trait slot dispatch
    // -------------------------------------------------------------------------

    /**
     * Dispatch a single trait key to its resolver.
     * Each resolver is isolated; a failure in one never affects another.
     */
    private function resolveSlot(string $trait, string $role, array $raw): array
    {
        try {
            return match ($trait) {
                'communication_channel'      => $this->resolveCommunicationChannel($role, $raw),
                'communication_frequency'    => $this->resolveCommunicationFrequency($role, $raw),
                'responsiveness_expectation' => $this->resolveResponsivenessExpectation($role, $raw),
                'negotiation_style'          => $this->resolveNegotiationStyle($role, $raw),
                'guidance_level'             => $this->resolveGuidanceLevel($role, $raw),
                'decision_making_style'      => $this->resolveDecisionMakingStyle($role, $raw),
                'transaction_pace'           => $this->resolveTransactionPace($role, $raw),
                'risk_tolerance'             => $this->resolveRiskTolerance($role, $raw),
                'collaboration_style'        => $this->resolveCollaborationStyle($role, $raw),
                'representation_priorities'  => $this->resolveRepresentationPriorities($role, $raw),
                'representation_philosophy'  => $this->resolveRepresentationPhilosophy($role, $raw),
                'property_strategy_fit'      => $this->resolvePropertyStrategyFit($role, $raw),
                default                      => ['value' => null, 'missing' => true],
            };
        } catch (\Throwable $e) {
            return ['value' => null, 'missing' => true];
        }
    }

    // -------------------------------------------------------------------------
    // Per-trait slot resolvers
    // -------------------------------------------------------------------------

    /**
     * communication_channel — the medium(s) the consumer prefers to use with their agent.
     *
     * Naming crosswalk (Section 7):
     *   Seller/Buyer  → raw key `preferred_contact_method` (multi-select, correctly named)
     *   Landlord      → raw key `communication_style`      (single-select, stores channel data despite key name)
     *   Tenant        → raw key `communication_style`      (single-select, stores channel data despite key name)
     */
    private function resolveCommunicationChannel(string $role, array $raw): array
    {
        return match ($role) {
            'seller', 'buyer'    => $this->slotFromKey($raw, 'preferred_contact_method'),
            'landlord', 'tenant' => $this->slotFromKey($raw, 'communication_style'),
            default              => ['value' => null, 'missing' => true],
        };
    }

    /**
     * communication_frequency — how often the consumer expects proactive updates.
     *
     * Naming crosswalk (Section 7):
     *   Seller  → raw key `communication_style`     (stores frequency options despite key name)
     *   Buyer   → raw key `communication_style`     (stores frequency options despite key name)
     *   Landlord → raw key `preferred_contact_method` (stores frequency options despite key name)
     *   Tenant  → raw key `contact_frequency`       (correctly named)
     *
     * Note: Buyer `communication_frequency` raw key stores showing/meeting format data and
     * maps to collaboration_style.showing_format_preference — NOT to this trait.
     */
    private function resolveCommunicationFrequency(string $role, array $raw): array
    {
        return match ($role) {
            'seller', 'buyer' => $this->slotFromKey($raw, 'communication_style'),
            'landlord'        => $this->slotFromKey($raw, 'preferred_contact_method'),
            'tenant'          => $this->slotFromKey($raw, 'contact_frequency'),
            default           => ['value' => null, 'missing' => true],
        };
    }

    /**
     * responsiveness_expectation — max acceptable agent response time.
     *
     *   Seller   → `response_time_expectation`
     *   Buyer    → ABSENT (no raw field; the role form never collected this dimension)
     *   Landlord → `response_time_expectation`
     *   Tenant   → ABSENT (preferred_contact_method stores time-of-day, not response time)
     */
    private function resolveResponsivenessExpectation(string $role, array $raw): array
    {
        return match ($role) {
            'seller', 'landlord' => $this->slotFromKey($raw, 'response_time_expectation'),
            'buyer', 'tenant'    => ['value' => null, 'missing' => true],
            default              => ['value' => null, 'missing' => true],
        };
    }

    /**
     * negotiation_style — the consumer's negotiation posture.
     * All four roles share the same raw key name `negotiation_style`.
     */
    private function resolveNegotiationStyle(string $role, array $raw): array
    {
        return $this->slotFromKey($raw, 'negotiation_style');
    }

    /**
     * guidance_level — how much hands-on direction the consumer wants.
     *
     *   Seller   → `involvement_level`
     *   Buyer    → `support_level`
     *   Landlord → `property_management_involvement`
     *   Tenant   → `desired_level_of_agent_involvement`
     */
    private function resolveGuidanceLevel(string $role, array $raw): array
    {
        return match ($role) {
            'seller'   => $this->slotFromKey($raw, 'involvement_level'),
            'buyer'    => $this->slotFromKey($raw, 'support_level'),
            'landlord' => $this->slotFromKey($raw, 'property_management_involvement'),
            'tenant'   => $this->slotFromKey($raw, 'desired_level_of_agent_involvement'),
            default    => ['value' => null, 'missing' => true],
        };
    }

    /**
     * decision_making_style — how the consumer reaches decisions.
     *
     *   Seller, Buyer, Tenant → `decision_making_style`
     *   Landlord → ABSENT (the Landlord form never collected this dimension)
     */
    private function resolveDecisionMakingStyle(string $role, array $raw): array
    {
        return match ($role) {
            'seller', 'buyer', 'tenant' => $this->slotFromKey($raw, 'decision_making_style'),
            'landlord'                  => ['value' => null, 'missing' => true],
            default                     => ['value' => null, 'missing' => true],
        };
    }

    /**
     * transaction_pace — the consumer's timeline sensitivity.
     *
     *   Seller   → `flexibility_on_timeline`
     *   Buyer    → `timeline_flexibility`
     *   Landlord → ABSENT (no timeline flexibility question on the Landlord form)
     *   Tenant   → `timeline_urgency`
     */
    private function resolveTransactionPace(string $role, array $raw): array
    {
        return match ($role) {
            'seller'   => $this->slotFromKey($raw, 'flexibility_on_timeline'),
            'buyer'    => $this->slotFromKey($raw, 'timeline_flexibility'),
            'landlord' => ['value' => null, 'missing' => true],
            'tenant'   => $this->slotFromKey($raw, 'timeline_urgency'),
            default    => ['value' => null, 'missing' => true],
        };
    }

    /**
     * risk_tolerance — the consumer's appetite for transactional risk.
     *
     *   Seller → ABSENT (no standalone risk tolerance question on the Seller form)
     *   Buyer  → `risk_tolerance`
     *   Landlord → `risk_tolerance`
     *   Tenant → ABSENT (budget_flexibility is informational, not this trait)
     */
    private function resolveRiskTolerance(string $role, array $raw): array
    {
        return match ($role) {
            'buyer', 'landlord' => $this->slotFromKey($raw, 'risk_tolerance'),
            'seller', 'tenant'  => ['value' => null, 'missing' => true],
            default             => ['value' => null, 'missing' => true],
        };
    }

    /**
     * collaboration_style — the operating mode and personality the consumer wants in their agent.
     *
     * All roles → `preferred_agent_working_style`
     *
     * Slot exception (Section 4.3.1) — Buyer only:
     *   The raw key `communication_frequency` stores showing/meeting format preference
     *   (In-Person Only, Virtual Tours Accepted, etc.) despite its misleading key name.
     *   This value is attached as the `showing_format_preference` sub-key on this slot
     *   and must NOT be routed to the communication_frequency trait.
     */
    private function resolveCollaborationStyle(string $role, array $raw): array
    {
        $slot = $this->slotFromKey($raw, 'preferred_agent_working_style');

        if ($role === 'buyer') {
            // Buyer 'communication_frequency' key stores showing format preference data.
            // See docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md Section 7 crosswalk #4.
            $showingRaw = $raw['communication_frequency'] ?? null;
            if ($showingRaw !== null && $showingRaw !== '') {
                $slot['showing_format_preference'] = (string) $showingRaw;
            }
        }

        return $slot;
    }

    /**
     * representation_priorities — the specific tasks the consumer most wants their agent to deliver.
     * All four roles share the same raw key name `representation_priorities` (multi-select).
     */
    private function resolveRepresentationPriorities(string $role, array $raw): array
    {
        return $this->slotFromKey($raw, 'representation_priorities');
    }

    /**
     * representation_philosophy — the consumer's high-level beliefs about good representation.
     *
     * Slot exception (Section 4.3.2) — Seller only:
     *   Raw key `past_agent_experience` feeds this trait. The raw value is also echoed
     *   in the `past_agent_experience` sub-key to preserve the original display string
     *   for audit and explanation layers.
     *
     *   Buyer, Landlord, Tenant → ABSENT.
     *   (Tenant `most_important_agent_traits` travels in informational_context, not here.)
     */
    private function resolveRepresentationPhilosophy(string $role, array $raw): array
    {
        if ($role !== 'seller') {
            return ['value' => null, 'missing' => true];
        }

        $rawValue = $raw['past_agent_experience'] ?? null;
        $value    = ($rawValue !== null && $rawValue !== '') ? $rawValue : null;

        $slot = [
            'value'   => $value,
            'missing' => false,
        ];

        // Echo the raw string in the sub-key for audit/explanation traceability.
        if ($value !== null) {
            $slot['past_agent_experience'] = (string) $value;
        }

        return $slot;
    }

    /**
     * property_strategy_fit — the consumer's primary strategic goal for the transaction.
     *
     *   Seller   → `primary_transaction_goal`
     *   Buyer    → `primary_transaction_goal`
     *   Landlord → `primary_leasing_goal`
     *   Tenant   → `primary_rental_goal`
     *
     * Slot exception (Section 4.3.3) — Landlord only, when `tenant_type_preference` is populated:
     *   Embeds a `proxy_risk_flags` sub-array within this slot (Fair Housing governance flag).
     */
    private function resolvePropertyStrategyFit(string $role, array $raw): array
    {
        $slot = match ($role) {
            'seller', 'buyer' => $this->slotFromKey($raw, 'primary_transaction_goal'),
            'landlord'        => $this->slotFromKey($raw, 'primary_leasing_goal'),
            'tenant'          => $this->slotFromKey($raw, 'primary_rental_goal'),
            default           => ['value' => null, 'missing' => true],
        };

        if ($role === 'landlord' && $this->isNonEmpty($raw['tenant_type_preference'] ?? null)) {
            $slot['proxy_risk_flags'] = [
                [
                    'field'  => 'tenant_type_preference',
                    'trait'  => 'property_strategy_fit',
                    'reason' => self::PROXY_FLAG_REASON_IN_SLOT,
                ],
            ];
        }

        return $slot;
    }

    // -------------------------------------------------------------------------
    // informational_context builders
    // -------------------------------------------------------------------------

    private function buildInformationalContext(string $role, array $raw): array
    {
        return match ($role) {
            'seller'   => $this->sellerInfoContext($raw),
            'buyer'    => $this->buyerInfoContext($raw),
            'landlord' => $this->landlordInfoContext($raw),
            'tenant'   => $this->tenantInfoContext($raw),
            default    => [],
        };
    }

    /**
     * Seller informational_context — 11 keys (Section 5.1).
     */
    private function sellerInfoContext(array $raw): array
    {
        return [
            'post_sale_plan'                 => $this->infoScalar($raw, 'post_sale_plan'),
            'target_sale_timeline'           => $this->infoScalar($raw, 'target_sale_timeline'),
            'showing_availability'           => $this->infoArray($raw, 'showing_availability'),
            'open_house_preference'          => $this->infoScalar($raw, 'open_house_preference'),
            'additional_compatibility_notes' => $this->infoScalar($raw, 'additional_compatibility_notes'),
            'what_did_not_work_before'       => $this->infoScalar($raw, 'what_did_not_work_before'),
            'additional_decision_makers'     => $this->infoScalar($raw, 'additional_decision_makers'),
            'primary_transaction_goal_other' => $this->infoScalar($raw, 'primary_transaction_goal_other'),
            'qualities_most_important'       => $this->infoArray($raw, 'qualities_most_important'),
            'willing_to_negotiate_on'        => $this->infoArray($raw, 'willing_to_negotiate_on'),
            'firm_on_price'                  => $this->infoScalar($raw, 'firm_on_price'),
        ];
    }

    /**
     * Buyer informational_context — 6 keys (Section 5.2).
     */
    private function buyerInfoContext(array $raw): array
    {
        return [
            'availability_windows'                => $this->infoScalar($raw, 'availability_windows'),
            'deal_breakers'                       => $this->infoScalar($raw, 'deal_breakers'),
            'additional_compatibility_notes'      => $this->infoScalar($raw, 'additional_compatibility_notes'),
            'primary_transaction_goal_other'      => $this->infoScalar($raw, 'primary_transaction_goal_other'),
            'representation_priorities_other'     => $this->infoScalar($raw, 'representation_priorities_other'),
            'preferred_agent_working_style_other' => $this->infoScalar($raw, 'preferred_agent_working_style_other'),
        ];
    }

    /**
     * Landlord informational_context — 6 keys (Section 5.3).
     */
    private function landlordInfoContext(array $raw): array
    {
        return [
            'additional_representation_notes' => $this->infoScalar($raw, 'additional_representation_notes'),
            'primary_leasing_goal_other'      => $this->infoScalar($raw, 'primary_leasing_goal_other'),
            'tenant_type_preference_other'    => $this->infoScalar($raw, 'tenant_type_preference_other'),
            'lease_duration_preference'       => $this->infoScalar($raw, 'lease_duration_preference'),
            'concessions_willingness'         => $this->infoScalar($raw, 'concessions_willingness'),
            'lease_terms_flexibility'         => $this->infoScalar($raw, 'lease_terms_flexibility'),
        ];
    }

    /**
     * Tenant informational_context — 10 keys (Section 5.4).
     *
     * Naming crosswalk: `preferred_contact_method` (stores time-of-day preference data despite
     * its misleading key name) is remapped to `preferred_contact_time_of_day` here.
     * It must never appear as a trait slot value — only here.
     */
    private function tenantInfoContext(array $raw): array
    {
        return [
            'preferred_contact_time_of_day'            => $this->infoScalar($raw, 'preferred_contact_method'),
            'most_important_agent_traits'              => $this->infoArray($raw, 'most_important_agent_traits'),
            'concerns_or_barriers'                     => $this->infoScalar($raw, 'concerns_or_barriers'),
            'additional_compatibility_notes'           => $this->infoScalar($raw, 'additional_compatibility_notes'),
            'other_primary_rental_goal'                => $this->infoScalar($raw, 'other_primary_rental_goal'),
            'other_representation_priorities'          => $this->infoScalar($raw, 'other_representation_priorities'),
            'other_timeline_urgency'                   => $this->infoScalar($raw, 'other_timeline_urgency'),
            'other_communication_style'                => $this->infoScalar($raw, 'other_communication_style'),
            'other_most_important_agent_traits'        => $this->infoScalar($raw, 'other_most_important_agent_traits'),
            'other_desired_level_of_agent_involvement' => $this->infoScalar($raw, 'other_desired_level_of_agent_involvement'),
        ];
    }

    // -------------------------------------------------------------------------
    // proxy_risk_flags (top-level)
    // -------------------------------------------------------------------------

    /**
     * Build the top-level proxy_risk_flags array (Section 6).
     *
     * At BYA_NORM_V1, only one field is flagged: Landlord `tenant_type_preference`
     * when it is populated with a non-null, non-empty value.
     * All other roles and all Landlord payloads where the field is absent → empty array.
     */
    private function buildProxyRiskFlags(string $role, array $raw): array
    {
        if ($role !== 'landlord') {
            return [];
        }

        if (!$this->isNonEmpty($raw['tenant_type_preference'] ?? null)) {
            return [];
        }

        return [
            [
                'field'  => 'tenant_type_preference',
                'trait'  => 'property_strategy_fit',
                'reason' => self::PROXY_FLAG_REASON_TOP_LEVEL,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Stub payload (unknown role or total data failure)
    // -------------------------------------------------------------------------

    private function buildStubPayload(mixed $listing, string $role): array
    {
        $traits = [];
        foreach (self::TRAIT_KEYS as $key) {
            $traits[$key] = ['value' => null, 'missing' => true];
        }

        return [
            'normalization_version' => self::VERSION,
            'role'                  => $role,
            'listing_id'            => $this->extractListingId($listing),
            'traits'                => $traits,
            'informational_context' => [],
            'proxy_risk_flags'      => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Low-level slot and value helpers
    // -------------------------------------------------------------------------

    /**
     * Build a standard trait slot from a single raw sub-key.
     *
     * Three-state model:
     *   - Key present + non-empty value → answered  (value: <v>, missing: false)
     *   - Key present + empty/null value → skipped  (value: null, missing: false)
     *   - Key absent from raw array → skipped       (value: null, missing: false)
     *
     * Note: The "absent" state (missing: true) is NEVER produced by this helper.
     * It is produced only by the role-specific resolver methods that know a given
     * trait has no structural raw field for a particular role.
     */
    private function slotFromKey(array $raw, string $key): array
    {
        $rawValue = $raw[$key] ?? null;

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
    private function infoScalar(array $raw, string $key): mixed
    {
        $v = $raw[$key] ?? null;
        if ($v === '' || $v === []) {
            return null;
        }
        return $v;
    }

    /**
     * Extract an array informational value — null when absent, empty, or non-array.
     */
    private function infoArray(array $raw, string $key): ?array
    {
        $v = $raw[$key] ?? null;
        if (!is_array($v)) {
            return null;
        }
        $filtered = array_values(array_filter($v, static fn ($item) => $item !== null && $item !== ''));
        return empty($filtered) ? null : $filtered;
    }

    private function extractListingId(mixed $listing): ?int
    {
        try {
            if (isset($listing->id)) {
                return (int) $listing->id;
            }
            if (method_exists($listing, 'getKey')) {
                return (int) $listing->getKey();
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
