<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use PHPUnit\Framework\TestCase;

/**
 * PropertyQaP0PrivacyGuardTest — regression guards for the P0 privacy/correctness fixes.
 *
 * These cover the three defects the Property Q&A audit found in code that was already
 * running, independently of the Property Q&A surface itself:
 *
 *   1. SnapshotFactVisibility was DEFAULT-OPEN — any key nobody had classified became
 *      'public_allowed'. Now an explicit allow-list; anything unrecognised is owner-only.
 *   2. AskAiContextBuilderService aliased the seller's DESIRED MINIMUM cap rate and net
 *      income as the property's ACTUAL cap rate and NOI — a negotiating floor disclosed
 *      as a property fact, and a wrong number stated as true.
 *   3. Buyer/tenant avatar psychographics (motivation, readiness, personality) sat outside
 *      every redaction path and were readable by any non-owner.
 *
 * Deliberately extends PHPUnit's TestCase with no application boot: every unit under test
 * here is pure (static classifiers, a public const map, and array-only redaction), so these
 * guards must keep passing even with no container bound.
 */
class PropertyQaP0PrivacyGuardTest extends TestCase
{
    // =====================================================================
    // 1. Fail-closed fact visibility
    // =====================================================================

    /**
     * The core of the fix: a key nobody has classified must never be public.
     * Under the old default-open model every one of these returned 'public_allowed'.
     */
    public function test_unknown_keys_are_never_public_for_any_role(): void
    {
        $unknownKeys = [
            'some_field_added_next_quarter',
            'internal_agent_notes',
            'private_negotiation_preference',
            'seller_walk_away_price',
            'owner_phone_number',
            '',
            'UPPERCASE_UNKNOWN',
            'utilities_included_maybe',
        ];

        foreach (['seller', 'landlord', 'buyer', 'tenant', null, 'not_a_role'] as $role) {
            foreach ($unknownKeys as $key) {
                $this->assertNotSame(
                    SnapshotFactVisibility::PUBLIC_ALLOWED,
                    SnapshotFactVisibility::classify($key, $role),
                    sprintf(
                        "Unrecognised key '%s' must not be public for role '%s' — visibility is an allow-list.",
                        $key,
                        $role ?? 'NULL'
                    )
                );
            }
        }
    }

    /**
     * A missing or unrecognised role must fail closed, so a caller that forgets to pass
     * the role cannot accidentally publish a fact.
     */
    public function test_missing_or_unknown_role_fails_closed(): void
    {
        // 'bedrooms' IS on the seller allow-list — so this proves the ROLE is what fails,
        // not the key.
        $this->assertSame(
            SnapshotFactVisibility::PUBLIC_ALLOWED,
            SnapshotFactVisibility::classify('bedrooms', 'seller')
        );

        $this->assertSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::classify('bedrooms'));
        $this->assertSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::classify('bedrooms', null));
        $this->assertSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::classify('bedrooms', ''));
        $this->assertSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::classify('bedrooms', 'nonsense'));
    }

    /**
     * Explicitly approved public facts (decision D1) must still work — the fix must not
     * simply lock everything down.
     */
    public function test_explicitly_allowlisted_keys_remain_public(): void
    {
        $sellerPublic = [
            'bedrooms', 'bathrooms', 'square_feet', 'year_built', 'lot_size',
            'asking_price', 'annual_property_taxes', 'has_hoa', 'hoa_fee',
            'hoa_payment_schedule', 'association_fee_includes', 'association_name',
            'association_approval_required', 'has_cdd', 'annual_cdd_fee',
            'has_special_assessments', 'pool', 'garage_spaces', 'utilities', 'sewer',
        ];

        foreach ($sellerPublic as $key) {
            $this->assertSame(
                SnapshotFactVisibility::PUBLIC_ALLOWED,
                SnapshotFactVisibility::classify($key, 'seller'),
                "Approved seller fact '{$key}' must remain public."
            );
        }

        $landlordPublic = [
            'rent_amount', 'available_date', 'lease_length', 'lease_terms',
            'renewal_option', 'utilities', 'smoking_policy', 'subletting_policy',
            'pet_policy', 'pet_fee_amount', 'pet_max_weight_lbs', 'bedrooms', 'parking_terms',
        ];

        foreach ($landlordPublic as $key) {
            $this->assertSame(
                SnapshotFactVisibility::PUBLIC_ALLOWED,
                SnapshotFactVisibility::classify($key, 'landlord'),
                "Approved landlord fact '{$key}' must remain public."
            );
        }
    }

    /**
     * Decision D2 — buyer and tenant listings are search criteria carrying negotiating
     * positions and applicant-adjacent data. No fact is public for those roles, even a
     * key that is public for seller/landlord.
     */
    public function test_buyer_and_tenant_facts_are_never_public(): void
    {
        $keys = array_merge(
            SnapshotFactVisibility::publicKeysForRole('seller'),
            ['max_price', 'max_rent', 'maximum_budget', 'financing_type']
        );

        foreach (['buyer', 'tenant'] as $role) {
            foreach ($keys as $key) {
                $this->assertNotSame(
                    SnapshotFactVisibility::PUBLIC_ALLOWED,
                    SnapshotFactVisibility::classify($key, $role),
                    "No {$role} fact may be public — '{$key}' leaked."
                );
            }

            $this->assertSame([], SnapshotFactVisibility::publicKeysForRole($role));
        }
    }

    /**
     * Seller negotiation figures and applicant data must never be public for ANY role.
     * This is the explicit never-public list from decision D1.
     */
    public function test_negotiation_and_applicant_fields_are_never_public(): void
    {
        $neverPublic = [
            'minimum_cap_rate', 'minimum_annual_net_income', 'maximum_budget',
            'credit_score', 'credit_score_range', 'credit_history',
            'eviction_history', 'criminal_history', 'background_check', 'bankruptcy',
            'monthly_income', 'household_income', 'gross_monthly_income', 'annual_income',
            'employment_status', 'employer', 'income_source',
            'primary_motivation', 'secondary_motivation', 'buyer_readiness_score',
            'avatar_confidence_score', 'buyer_personality_tags',
        ];

        foreach (['seller', 'landlord', 'buyer', 'tenant', null] as $role) {
            foreach ($neverPublic as $key) {
                $this->assertNotSame(
                    SnapshotFactVisibility::PUBLIC_ALLOWED,
                    SnapshotFactVisibility::classify($key, $role),
                    sprintf("'%s' must never be public (role '%s').", $key, $role ?? 'NULL')
                );
            }
        }
    }

    /**
     * Compliance-restricted keys keep their own tier regardless of role, and are never
     * silently promoted onto the public allow-list.
     */
    public function test_restricted_keys_stay_restricted_and_are_not_on_any_allowlist(): void
    {
        foreach (SnapshotFactVisibility::restrictedKeys() as $key) {
            foreach (['seller', 'landlord', 'buyer', 'tenant', null] as $role) {
                $this->assertSame(
                    SnapshotFactVisibility::RESTRICTED,
                    SnapshotFactVisibility::classify($key, $role),
                    "Compliance key '{$key}' must stay restricted."
                );
            }

            $this->assertNotContains($key, SnapshotFactVisibility::publicKeysForRole('seller'));
            $this->assertNotContains($key, SnapshotFactVisibility::publicKeysForRole('landlord'));
        }
    }

    /**
     * 'owner_only' must NOT be conflated with 'restricted'.
     *
     * AskAiKnowledgeSearchService blocks any fact flagged restricted for EVERY scope,
     * the owner included. If unclassified keys defaulted to 'restricted' instead of
     * 'owner_only', every owner would silently lose their own Ask AI answers. This is
     * the guard on that distinction.
     */
    public function test_owner_only_is_a_distinct_tier_from_restricted(): void
    {
        $this->assertSame(
            SnapshotFactVisibility::OWNER_ONLY,
            SnapshotFactVisibility::classify('some_unclassified_key', 'seller')
        );

        $this->assertNotSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::RESTRICTED);
        $this->assertNotSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::PUBLIC_ALLOWED);
    }

    /** Listing-type aliases must resolve to the same tier as their canonical role. */
    public function test_listing_type_aliases_resolve_consistently(): void
    {
        foreach (['seller', 'seller_agent_auction', 'property_auction'] as $alias) {
            $this->assertSame(
                SnapshotFactVisibility::PUBLIC_ALLOWED,
                SnapshotFactVisibility::classify('bedrooms', $alias),
                "Alias '{$alias}' must classify as seller does."
            );
        }

        foreach (['buyer_criteria_auction', 'tenant_criteria_auction'] as $alias) {
            $this->assertSame(
                SnapshotFactVisibility::OWNER_ONLY,
                SnapshotFactVisibility::classify('bedrooms', $alias),
                "Criteria alias '{$alias}' must never be public."
            );
        }
    }

    // =====================================================================
    // 2. Seller minimum figures must not masquerade as actual figures
    // =====================================================================

    /**
     * The exact defect: 'cap_rate' and 'annual_noi' (and 'annual_net_income') were mapped
     * onto the seller's minimum_* meta keys, so an Ask AI cap-rate question was answered
     * with the seller's walk-away figure presented as the property's actual cap rate.
     */
    public function test_no_context_key_aliases_a_seller_minimum_as_an_actual_figure(): void
    {
        $minimumSources = ['minimum_cap_rate', 'minimum_annual_net_income'];

        foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP as $role => $map) {
            foreach ($map as $contextKey => $source) {
                $sources = (array) $source;

                foreach ($sources as $sourceKey) {
                    if (! in_array($sourceKey, $minimumSources, true)) {
                        continue;
                    }

                    // A minimum value may ONLY be exposed under a context key that still
                    // says "minimum". Anything else represents it as an actual figure.
                    $this->assertStringContainsString(
                        'minimum',
                        strtolower((string) $contextKey),
                        sprintf(
                            "[%s] context key '%s' exposes the seller's private '%s' under a name that "
                            . "reads as the property's actual figure. A desired minimum is a negotiating "
                            . "floor, not a property fact.",
                            $role,
                            $contextKey,
                            $sourceKey
                        )
                    );
                }
            }
        }
    }

    /** The specific removed aliases must not come back under any role. */
    public function test_actual_cap_rate_and_noi_context_keys_do_not_exist(): void
    {
        $forbidden = ['cap_rate', 'annual_noi', 'annual_net_income'];

        foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP as $role => $map) {
            foreach ($forbidden as $key) {
                $this->assertArrayNotHasKey(
                    $key,
                    $map,
                    sprintf(
                        "[%s] context key '%s' was removed in P0 — there is no actual-NOI/cap-rate source "
                        . "field on the listing. Use the seller-answered KB keys instead.",
                        $role,
                        $key
                    )
                );
            }
        }
    }

    /**
     * The honestly-named keys survive: the seller's own minimums are still available to
     * the owner under names that say what they are.
     */
    public function test_honestly_named_minimum_keys_are_preserved(): void
    {
        $sellerMap = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'] ?? [];

        $this->assertArrayHasKey('minimum_cap_rate', $sellerMap);
        $this->assertArrayHasKey('minimum_annual_net_income', $sellerMap);
        $this->assertSame('minimum_cap_rate', $sellerMap['minimum_cap_rate']);
        $this->assertSame('minimum_annual_net_income', $sellerMap['minimum_annual_net_income']);
    }

    // =====================================================================
    // 3. Buyer / tenant avatar psychographics
    // =====================================================================

    /**
     * Avatar sections must be stripped for every non-owner scope, for every role.
     *
     * The buyer case is the regression: redactContext() returns early for any role that
     * is not 'tenant', so buyer_avatar was never redacted for anyone.
     */
    public function test_avatar_sections_are_stripped_for_every_non_owner_scope(): void
    {
        $service = new AskAiViewerAuthorizationService();

        $context = [
            'listing'       => ['bedrooms' => 4],
            'buyer_avatar'  => [
                'primary_motivation'      => 'Relocating after a divorce',
                'buyer_readiness_score'   => 92,
                'buyer_personality_tags'  => ['anxious', 'first-time'],
            ],
            'tenant_avatar' => [
                'primary_motivation'     => 'Leaving a bad landlord',
                'avatar_confidence_score' => 77,
            ],
        ];

        $scopes = [
            AskAiViewerAuthorizationService::SCOPE_PUBLIC,
            AskAiViewerAuthorizationService::SCOPE_AUTHORIZED,
        ];

        foreach (['buyer', 'tenant', 'seller', 'landlord'] as $role) {
            foreach ($scopes as $scope) {
                $redacted = $service->redactContext($context, $role, $scope);

                $this->assertArrayNotHasKey(
                    'buyer_avatar',
                    $redacted,
                    "buyer_avatar must be stripped for scope '{$scope}' on role '{$role}'."
                );
                $this->assertArrayNotHasKey(
                    'tenant_avatar',
                    $redacted,
                    "tenant_avatar must be stripped for scope '{$scope}' on role '{$role}'."
                );

                // Belt and braces: no psychographic value may survive anywhere in the payload.
                $flat = json_encode($redacted);
                $this->assertStringNotContainsString('Relocating after a divorce', $flat);
                $this->assertStringNotContainsString('Leaving a bad landlord', $flat);
                $this->assertStringNotContainsString('anxious', $flat);
            }
        }
    }

    /** The owner still sees their own avatar data — the fix must not break owner UX. */
    public function test_owner_scope_retains_avatar_sections(): void
    {
        $service = new AskAiViewerAuthorizationService();

        $context = [
            'listing'      => ['bedrooms' => 4],
            'buyer_avatar' => ['primary_motivation' => 'Upsizing'],
        ];

        $redacted = $service->redactContext(
            $context,
            'buyer',
            AskAiViewerAuthorizationService::SCOPE_OWNER
        );

        $this->assertArrayHasKey('buyer_avatar', $redacted);
        $this->assertSame('Upsizing', $redacted['buyer_avatar']['primary_motivation']);
    }

    /** Redaction must be harmless when the avatar sections are absent entirely. */
    public function test_redaction_is_a_noop_when_avatar_sections_absent(): void
    {
        $service = new AskAiViewerAuthorizationService();

        $redacted = $service->redactContext(
            ['listing' => ['bedrooms' => 3]],
            'seller',
            AskAiViewerAuthorizationService::SCOPE_PUBLIC
        );

        $this->assertSame(3, $redacted['listing']['bedrooms']);
    }
}
