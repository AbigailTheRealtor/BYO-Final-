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
     * Whole context sections holding a private profile of the CONSUMER rather than facts
     * about a property. Removed in full for every non-owner scope, for every role.
     *
     * These are psychographic: motivation, readiness, personality tags, narrative and
     * preference summaries, produced by the AI DNA profilers. None of it is a property
     * fact, and none of it belongs to anyone but the listing's owner.
     */
    private const CONFIDENTIAL_CONTEXT_SECTIONS = [
        'buyer_avatar',
        'tenant_avatar',
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
     * The canonical role ('seller' | 'landlord' | 'buyer' | 'tenant') for a listing-type
     * alias, or null for a type Ask AI does not serve. The same table resolveScope() uses.
     */
    public static function canonicalRole(string $listingType): ?string
    {
        return self::CANONICAL_ROLE[strtolower($listingType)] ?? null;
    }

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
     * Redact confidential fields from the assembled context per scope.
     * Every non-owner loses the compliance-restricted listing fields, the consumer avatar
     * sections and the whole faq_answers Knowledge Base, for every role. Only tenant
     * listings carry native applicant data beyond that (buyer-side sensitivity is a
     * documented future extension — Part J note).
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

        // P0 — consumer psychographic profiles. The buyer_avatar / tenant_avatar sections
        // carry primary_motivation, secondary_motivation, readiness and confidence scores,
        // personality tags, narrative and preference summaries: a private profile OF THE
        // CONSUMER, not a fact about a property.
        //
        // These sections were reachable by every non-owner. The two guards above and below
        // both miss them — stripRestrictedComplianceKeys() only ever touches
        // $context['listing'], and the applicant handling below is preceded by an early
        // return for any role that is not 'tenant', so a BUYER's motivation and readiness
        // score were never redacted for any scope. Removed wholesale for every non-owner,
        // across all roles, before that early return can apply.
        if ($scope !== self::SCOPE_OWNER) {
            foreach (self::CONFIDENTIAL_CONTEXT_SECTIONS as $section) {
                unset($context[$section]);
            }
        }

        // Batch 0 — the owner-authored AI Knowledge Base. faq_answers holds every answer
        // the owner typed into the listing's Knowledge Base, and those answers are
        // owner-only (decision D3). This was reachable by non-owners: the early return
        // below skipped every role but tenant, and the tenant branch only stripped the
        // applicant-sensitive subset, so a seller's, landlord's or buyer's complete
        // Knowledge Base — and the remainder of a tenant's — reached a non-owner context
        // and, on a snapshot miss, the model prompt.
        //
        // Removed wholesale, for every non-owner scope and every role, BEFORE either
        // early return can apply. Any scope that is not exactly SCOPE_OWNER — public,
        // authorized, or an unrecognised value — is a non-owner, so this fails closed.
        // A future public surface selects approved keys from the stored answers itself;
        // it must never be served from this collection.
        if ($scope !== self::SCOPE_OWNER) {
            unset($context['faq_answers']);
        }

        // PUBLIC-FACT ALLOWLIST for non-owner listing context.
        //
        // The redaction above is a DENY-list (compliance tokens) and so failed open: every
        // OWNER_ONLY listing fact — a landlord's minimum credit score and eviction policy, a
        // seller's buy-now price, a buyer's pre-approval and financing contingencies — stayed
        // in a non-owner's context, reached the prompt and, on the deterministic path, was
        // answered verbatim to a public viewer. Measured: 153 of 153 owner-only fields.
        //
        // Now a non-owner's listing context keeps only what the public may be told, from the
        // two existing authorities rather than a third list: SnapshotFactVisibility's
        // PUBLIC_ALLOWED tier for Seller/Landlord (the same rule the snapshot search already
        // applies to every non-owner), and the criteria card's public allowlist for
        // Buyer/Tenant. Page-level base keys (title, type, status, place names) are kept.
        //
        // A tenant listing's AUTHORIZED scope is untouched here: it exists so a landlord with
        // an accepted deal sees the applicant's disclosures, and is handled below.
        if ($scope !== self::SCOPE_OWNER
            && !($role === 'tenant' && $scope === self::SCOPE_AUTHORIZED)
            && isset($context['listing']) && is_array($context['listing'])
        ) {
            $context['listing'] = $this->keepOnlyPublicListingFacts($context['listing'], $role);
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

        return $context;
    }

    /** Base keys extractListingFields() writes for every role; page-level, never owner facts. */
    private const PUBLIC_BASE_LISTING_KEYS = [
        'listing_type', 'listing_id', 'listing_title', 'city', 'state', 'county',
        'property_type', 'listing_status', 'created_at', 'updated_at',
    ];

    /**
     * @param  array<string, mixed>  $listing
     * @return array<string, mixed>
     */
    private function keepOnlyPublicListingFacts(array $listing, string $role): array
    {
        $criteria = in_array($role, ['buyer', 'tenant'], true)
            ? array_flip(AskAiPublicPropertyQuestionService::publicCriteriaKeys($role))
            : null;
        // Fields the public card admits beyond the snapshot tier (`admitted_listing` entries,
        // e.g. the financing types a seller will consider). The card and free text must agree.
        $admitted = $criteria === null ? self::admittedListingFields($role) : [];

        foreach (array_keys($listing) as $key) {
            if (in_array($key, self::PUBLIC_BASE_LISTING_KEYS, true)) {
                continue;
            }
            $public = $criteria !== null
                ? isset($criteria[$key])
                : (Snapshot\SnapshotFactVisibility::classify((string) $key, $role) === Snapshot\SnapshotFactVisibility::PUBLIC_ALLOWED
                    || isset($admitted[$key]));
            if (!$public) {
                unset($listing[$key]);
            }
        }

        return $listing;
    }

    /** @return array<string, true> listing fields admitted to the public card by `admitted_listing` entries */
    private static function admittedListingFields(string $role): array
    {
        $fields = [];
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $entry) {
            if (($entry['role'] ?? null) === $role
                && ($entry['source_kind'] ?? null) === 'admitted_listing'
                && preg_match('/^listing\.([a-z0-9_]+)$/', (string) ($entry['source_path'] ?? ''), $m) === 1) {
                $fields[$m[1]] = true;
            }
        }

        return $fields;
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
