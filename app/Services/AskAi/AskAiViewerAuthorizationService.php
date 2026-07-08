<?php

namespace App\Services\AskAi;

use Illuminate\Support\Facades\DB;

/**
 * AskAiViewerAuthorizationService — Ask AI Viewer Authorization & Field Redaction (Phase A, Part J / C-B)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Resolve the authorization scope of an Ask AI requester for a given listing and
 * redact confidential applicant fields from the assembled context BEFORE it reaches the
 * model. Implements the fail-closed access-control policy of
 * docs/ask-ai-kb-replacement-spec.md Part J (C-B).
 *
 * Policy (Part J.6 — most-restrictive, fail-closed, owner direction):
 *   - guest / unverified requester      → scope 'public'    (all applicant data redacted)
 *   - listing owner (the tenant)        → scope 'owner'     (no redaction)
 *   - landlord/agent with an ACCEPTED    → scope 'authorized' (authorized subset only;
 *     in-platform deal on THAT listing                        criminal/eviction/credit
 *                                                             specifics never exposed)
 *   - anything that cannot be confidently verified → 'public' (default-deny)
 *
 * Verified relationship source (confirmed in code, Part J.7):
 *   accepted_bid_summaries WHERE listing_type='tenant' AND listing_id=:id
 *   AND agent_user_id=:requesterId  → the accepted landlord/agent counterparty.
 *   (Existence of this row is what the platform treats as an accepted deal; the loosely
 *   typed `accepted` varchar on the bid table is intentionally NOT trusted.)
 *   Chat/lead tables and tenant_criteria_auction_bids are DENY (cannot be verified).
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Write to the database (it performs read-only authorization checks only).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiViewerAuthorizationService
{
    public const SCOPE_OWNER      = 'owner';
    public const SCOPE_AUTHORIZED = 'authorized';
    public const SCOPE_PUBLIC     = 'public';

    /**
     * Listing-type aliases → owner table (mirrors AskAiListingQuestionController::OWNER_TABLES).
     */
    private const OWNER_TABLES = [
        'seller'                  => 'seller_agent_auctions',
        'seller_agent_auction'    => 'seller_agent_auctions',
        'property_auction'        => 'seller_agent_auctions',
        'buyer'                   => 'buyer_agent_auctions',
        'buyer_agent_auction'     => 'buyer_agent_auctions',
        'buyer_criteria_auction'  => 'buyer_agent_auctions',
        'landlord'                => 'landlord_agent_auctions',
        'landlord_agent_auction'  => 'landlord_agent_auctions',
        'landlord_auction'        => 'landlord_agent_auctions',
        'tenant'                  => 'tenant_agent_auctions',
        'tenant_agent_auction'    => 'tenant_agent_auctions',
        'tenant_criteria_auction' => 'tenant_agent_auctions',
    ];

    /**
     * Canonical role for each listing-type alias.
     */
    private const CANONICAL_ROLE = [
        'seller'                  => 'seller',
        'seller_agent_auction'    => 'seller',
        'property_auction'        => 'seller',
        'buyer'                   => 'buyer',
        'buyer_agent_auction'     => 'buyer',
        'buyer_criteria_auction'  => 'buyer',
        'landlord'                => 'landlord',
        'landlord_agent_auction'  => 'landlord',
        'landlord_auction'        => 'landlord',
        'tenant'                  => 'tenant',
        'tenant_agent_auction'    => 'tenant',
        'tenant_criteria_auction' => 'tenant',
    ];

    /**
     * Tenant applicant FAQ answer keys that may be summarized ONLY for the owner or an
     * authorized landlord/agent (Part J.3). Redacted for the 'public' scope.
     */
    private const APPLICANT_SENSITIVE_FAQ_KEYS = [
        'faq_q12', // chance of breaking the lease early
        'faq_q15', // most recent tenancy length / why moving
        'faq_q17', // landlord/employer references available
        'faq_q18', // source and stability of income
        'faq_q20', // biggest concern / hesitation
        'tenant_prior_conduct',  // disclosed prior rental conduct (Phase C addition)
        'tenant_cosigner',       // co-signer / guarantor availability (Phase C addition)
        'tenant_application_readiness',
    ];

    /**
     * Native listing keys that carry applicant financial detail. Redacted for 'public';
     * available to owner and authorized viewers (Part J.3 — disclosed income source/amount).
     */
    private const APPLICANT_SENSITIVE_NATIVE_KEYS = [
        'monthly_income', 'household_income', 'gross_monthly_income', 'annual_income',
        'income_requirement', 'income_requirement_amount', 'income_multiplier',
        'employment_status', 'employer', 'employment_type', 'income_source',
    ];

    /**
     * Keys whose specifics are NEVER exposed through Ask AI to any non-owner — criminal,
     * eviction, credit, and background-report data (Part J.4 / FCRA-adjacent). Redacted
     * for both 'authorized' and 'public'.
     */
    private const NEVER_EXPOSE_KEYS = [
        'credit_score', 'credit_score_range', 'credit_history', 'credit_report',
        'eviction', 'eviction_history', 'evictions', 'prior_eviction',
        'criminal', 'criminal_history', 'criminal_record', 'felony', 'misdemeanor',
        'background_check', 'background_report', 'bankruptcy', 'bankruptcies',
    ];

    /**
     * Compliance-restricted listing tokens (C2s). Mirrors SnapshotFactVisibility's
     * 'restricted' tier and covers the aliases the context builder actually emits
     * (e.g. 'hoa_fee', 'security_deposit_amount', 'annual_cdd_fee', 'max_rent').
     * Stripped from context['listing'] for every non-owner scope.
     *
     * Matched on whole '_'-delimited key segments (see stripRestrictedComplianceKeys),
     * NOT raw substrings, so unrelated keys are never caught — e.g. the 'rent' token
     * matches 'max_rent'/'rent_amount' but not 'current_use' or 'rental_purpose'.
     */
    private const RESTRICTED_COMPLIANCE_TOKENS = [
        'flood_zone', 'flood_zone_code', 'is_in_flood_zone',
        'security_deposit', 'security_deposit_amount',
        'hoa', 'hoa_fee', 'max_hoa_fee',
        'cdd',
        'rent', 'rental_price', 'min_rent', 'max_rent',
        'income_requirement', 'income_multiplier',
        'seller_financing',
    ];

    /**
     * Resolve the authorization scope of a requester for a listing.
     *
     * @param  int|null $userId       Authenticated requester id, or null for a guest.
     * @param  string   $listingType  Canonical or aliased listing type.
     * @param  int      $listingId    Listing primary key.
     * @return string                 One of SCOPE_OWNER | SCOPE_AUTHORIZED | SCOPE_PUBLIC.
     */
    public function resolveScope(?int $userId, string $listingType, int $listingId): string
    {
        if (! $userId) {
            return self::SCOPE_PUBLIC;
        }

        $alias = strtolower($listingType);
        $table = self::OWNER_TABLES[$alias] ?? null;
        if ($table === null) {
            return self::SCOPE_PUBLIC; // unknown type → fail closed
        }

        try {
            $isOwner = DB::table($table)
                ->where('id', $listingId)
                ->where('user_id', $userId)
                ->exists();
        } catch (\Throwable) {
            return self::SCOPE_PUBLIC; // any error → fail closed
        }

        if ($isOwner) {
            return self::SCOPE_OWNER;
        }

        // Verified accepted-deal relationship (tenant listings only, per Part J.7).
        $role = self::CANONICAL_ROLE[$alias] ?? $alias;
        if ($role === 'tenant' && $this->hasAcceptedTenantRelationship($userId, $listingId)) {
            return self::SCOPE_AUTHORIZED;
        }

        return self::SCOPE_PUBLIC;
    }

    /**
     * Redact confidential applicant fields from the assembled context per scope.
     * Only tenant listings carry applicant data today; other roles pass through unchanged
     * (buyer-side sensitivity is a documented future extension — Part J note).
     *
     * @param  array  $context      The context array from AskAiContextBuilderService.
     * @param  string $listingType  Canonical or aliased listing type.
     * @param  string $scope        Resolved scope.
     * @return array                The context with confidential keys removed as needed.
     */
    public function redactContext(array $context, string $listingType, string $scope): array
    {
        $role = self::CANONICAL_ROLE[strtolower($listingType)] ?? strtolower($listingType);

        // C2s — compliance-restricted listing fields (SnapshotFactVisibility's
        // 'restricted' tier: flood zone, HOA/CDD, security deposit, rent pricing/
        // terms, income requirement, seller financing) are surfaced only to the
        // listing owner. Strip them for every non-owner scope (public AND
        // authorized) across ALL roles — this runs before the role-specific
        // applicant handling below so seller/buyer/landlord/tenant are all covered.
        if ($scope !== self::SCOPE_OWNER && isset($context['listing']) && is_array($context['listing'])) {
            $context['listing'] = $this->stripRestrictedComplianceKeys($context['listing']);
        }

        if ($role !== 'tenant') {
            return $context;
        }

        // Owner sees their own disclosures in full.
        if ($scope === self::SCOPE_OWNER) {
            return $context;
        }

        // Criminal/eviction/credit specifics are never exposed to any non-owner.
        $stripKeys = self::NEVER_EXPOSE_KEYS;

        // Public (unverified) viewers additionally lose all applicant-sensitive fields.
        if ($scope !== self::SCOPE_AUTHORIZED) {
            $stripKeys = array_merge($stripKeys, self::APPLICANT_SENSITIVE_NATIVE_KEYS);
        }

        if (isset($context['listing']) && is_array($context['listing'])) {
            $context['listing'] = $this->stripKeys($context['listing'], $stripKeys);
        }

        // FAQ applicant answers: never-expose tier is dropped for all non-owners;
        // the authorized-only tier is dropped only for the public scope.
        $faqStrip = ($scope === self::SCOPE_AUTHORIZED) ? [] : self::APPLICANT_SENSITIVE_FAQ_KEYS;
        if (! empty($faqStrip) && isset($context['faq_answers']) && is_array($context['faq_answers'])) {
            $context['faq_answers'] = $this->stripKeys($context['faq_answers'], $faqStrip);
        }

        return $context;
    }

    /**
     * Verify an accepted in-platform deal exists between this requesting agent/landlord
     * and the specific tenant listing (Part J.7 — accepted_bid_summaries is authoritative).
     */
    private function hasAcceptedTenantRelationship(int $userId, int $listingId): bool
    {
        try {
            return DB::table('accepted_bid_summaries')
                ->where('listing_type', 'tenant')
                ->where('listing_id', $listingId)
                ->where('agent_user_id', $userId)
                ->exists();
        } catch (\Throwable) {
            return false; // table/column missing or error → fail closed
        }
    }

    /**
     * Case-insensitive substring denylist strip. A context key is removed when it equals
     * or contains any denied token, so variant field names (e.g. 'gross_monthly_income')
     * are covered without an exhaustive enumeration. Removing an absent key is a no-op.
     *
     * @param  array    $fields
     * @param  string[] $deniedTokens
     * @return array
     */
    private function stripKeys(array $fields, array $deniedTokens): array
    {
        if (empty($deniedTokens)) {
            return $fields;
        }

        foreach (array_keys($fields) as $key) {
            $lower = strtolower((string) $key);
            foreach ($deniedTokens as $token) {
                if ($lower === $token || str_contains($lower, $token)) {
                    unset($fields[$key]);
                    break;
                }
            }
        }

        return $fields;
    }

    /**
     * Strip compliance-restricted listing fields (C2s) using whole-segment token
     * matching. A key is removed when a RESTRICTED_COMPLIANCE_TOKENS entry equals
     * the key, or appears as a complete '_'-delimited segment span within it
     * (prefix 'token_', suffix '_token', or infix '_token_'). Segment matching
     * (not substring) prevents false positives such as 'current_use' matching the
     * 'rent' token. Removing an absent key is a no-op.
     *
     * @param  array $fields
     * @return array
     */
    private function stripRestrictedComplianceKeys(array $fields): array
    {
        foreach (array_keys($fields) as $key) {
            $lower = strtolower((string) $key);
            foreach (self::RESTRICTED_COMPLIANCE_TOKENS as $token) {
                if (
                    $lower === $token
                    || str_starts_with($lower, $token . '_')
                    || str_ends_with($lower, '_' . $token)
                    || str_contains($lower, '_' . $token . '_')
                ) {
                    unset($fields[$key]);
                    break;
                }
            }
        }

        return $fields;
    }
}
