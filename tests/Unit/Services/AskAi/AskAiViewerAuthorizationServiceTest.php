<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiViewerAuthorizationService;
use Tests\TestCase;

/**
 * Part J / C-B — viewer authorization scope + applicant-field redaction.
 * Focuses on the pure, security-critical redaction logic and the no-DB scope branches.
 */
class AskAiViewerAuthorizationServiceTest extends TestCase
{
    private AskAiViewerAuthorizationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AskAiViewerAuthorizationService();
    }

    private function tenantContext(): array
    {
        return [
            'listing' => [
                'desired_lease_length' => '12 months',
                'monthly_income'       => '$7,500',
                'credit_score'         => '720',
                'eviction_history'     => 'none',
                'pets_allowed'         => 'Yes',
            ],
            'faq_answers' => [
                'faq_q10' => 'Prefers unfurnished.',
                'faq_q17' => 'References available.',
                'faq_q18' => 'Salaried W-2 employee.',
                'tenant_prior_conduct' => 'Two late payments in 2023.',
            ],
        ];
    }

    /**
     * A listing block mixing C2s compliance-restricted fields (with the aliases the
     * context builder actually emits) and known-safe public fields, incl. two
     * false-positive guards ('current_use', 'rental_purpose') that must NOT be caught
     * by the segment matcher despite containing the 'rent' token as a substring.
     */
    private function restrictedListingContext(): array
    {
        return [
            'listing' => [
                // Restricted (must be stripped for every non-owner scope):
                'flood_zone_code'                => 'AE',
                'is_in_flood_zone'               => true,
                'security_deposit_amount'        => 5000,
                'hoa_fee'                        => 250,
                'max_hoa_fee'                    => 400,
                'annual_cdd_fee'                 => 1200,
                'max_rent'                       => 3000,
                'min_rent'                       => 2000,
                'rent_amount'                    => 2500,
                'income_requirement'             => 7500,
                'seller_financing_interest_rate' => 6.5,
                // Safe public fields (must always survive):
                'bedrooms'                       => 3,
                'bathrooms'                      => 2,
                'city'                           => 'Tampa',
                'current_use'                    => 'residential',
                'rental_purpose'                 => 'primary residence',
            ],
            'faq_answers' => [],
        ];
    }

    /** @return string[] The restricted keys expected to be stripped for non-owners. */
    private function restrictedKeys(): array
    {
        return [
            'flood_zone_code', 'is_in_flood_zone', 'security_deposit_amount',
            'hoa_fee', 'max_hoa_fee', 'annual_cdd_fee',
            'max_rent', 'min_rent', 'rent_amount',
            'income_requirement', 'seller_financing_interest_rate',
        ];
    }

    /** @return string[] Known-safe public keys that must survive redaction. */
    /** Public for every role (SnapshotFactVisibility / the criteria allowlist, or a base key). */
    private function safeKeys(): array
    {
        return ['bedrooms', 'bathrooms', 'city'];
    }

    /**
     * Owner-only facts. They used to survive for non-owners — the redaction was a deny-list of
     * compliance tokens — and were answered to public viewers. They were also the token
     * matcher's false-positive guards; that precision is still pinned by the owner-scope tests,
     * where nothing is stripped.
     */
    private function ownerOnlyKeys(): array
    {
        return ['current_use', 'rental_purpose'];
    }

    /**
     * @dataProvider nonOwnerScopeAndRoleProvider
     */
    public function test_restricted_compliance_fields_stripped_for_non_owner(string $role, string $scope): void
    {
        $out = $this->svc->redactContext($this->restrictedListingContext(), $role, $scope);

        foreach ($this->restrictedKeys() as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $out['listing'],
                "Restricted key '{$key}' must be stripped for role={$role} scope={$scope}"
            );
        }

        // Safe public fields must survive for non-owners.
        foreach ($this->safeKeys() as $key) {
            $this->assertArrayHasKey(
                $key,
                $out['listing'],
                "Safe key '{$key}' must survive for role={$role} scope={$scope}"
            );
        }

        // Owner-only facts are removed for non-owners by the public-fact allowlist — except
        // on a tenant listing's AUTHORIZED scope, which exists so a landlord with an accepted
        // deal sees the applicant's disclosures (never-expose keys aside).
        if (!($role === 'tenant' && $scope === AskAiViewerAuthorizationService::SCOPE_AUTHORIZED)) {
            foreach ($this->ownerOnlyKeys() as $key) {
                $this->assertArrayNotHasKey($key, $out['listing'], "Owner-only key '{$key}' must not reach role={$role} scope={$scope}");
            }
        }
    }

    public static function nonOwnerScopeAndRoleProvider(): array
    {
        $cases = [];
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            foreach ([
                AskAiViewerAuthorizationService::SCOPE_PUBLIC,
                AskAiViewerAuthorizationService::SCOPE_AUTHORIZED,
            ] as $scope) {
                $cases["{$role}/{$scope}"] = [$role, $scope];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider ownerRoleProvider
     */
    public function test_owner_scope_keeps_restricted_compliance_fields(string $role): void
    {
        $ctx = $this->restrictedListingContext();
        $out = $this->svc->redactContext($ctx, $role, AskAiViewerAuthorizationService::SCOPE_OWNER);

        // Owner sees everything, restricted and safe alike — nothing is stripped.
        $this->assertSame($ctx, $out, "Owner scope must not strip anything for role={$role}");
    }

    public static function ownerRoleProvider(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    public function test_guest_with_no_user_resolves_to_public(): void
    {
        $this->assertSame(
            AskAiViewerAuthorizationService::SCOPE_PUBLIC,
            $this->svc->resolveScope(null, 'tenant', 1)
        );
    }

    public function test_unknown_listing_type_resolves_to_public(): void
    {
        $this->assertSame(
            AskAiViewerAuthorizationService::SCOPE_PUBLIC,
            $this->svc->resolveScope(99, 'not_a_real_type', 1)
        );
    }

    public function test_owner_scope_redacts_nothing(): void
    {
        $ctx = $this->tenantContext();
        $out = $this->svc->redactContext($ctx, 'tenant', AskAiViewerAuthorizationService::SCOPE_OWNER);
        $this->assertSame($ctx, $out);
    }

    public function test_public_scope_redacts_all_applicant_fields(): void
    {
        $out = $this->svc->redactContext($this->tenantContext(), 'tenant', AskAiViewerAuthorizationService::SCOPE_PUBLIC);

        // Never-expose native fields gone.
        $this->assertArrayNotHasKey('credit_score', $out['listing']);
        $this->assertArrayNotHasKey('eviction_history', $out['listing']);
        // Applicant-sensitive native fields gone for public.
        $this->assertArrayNotHasKey('monthly_income', $out['listing']);

        // Batch 0: the whole Knowledge Base is gone for a non-owner — the applicant-sensitive
        // answers AND the ordinary ones (faq_q10) that used to be kept.
        $this->assertArrayNotHasKey('faq_answers', $out);

        // Kept: on the tenant criteria card's public allowlist.
        $this->assertArrayHasKey('desired_lease_length', $out['listing']);
        // Removed: the applicant's own pet disclosure is not on that allowlist, so a public
        // viewer's context no longer carries it (it used to, and could be answered).
        $this->assertArrayNotHasKey('pets_allowed', $out['listing']);
    }

    public function test_authorized_scope_keeps_applicant_subset_but_drops_never_expose(): void
    {
        $out = $this->svc->redactContext($this->tenantContext(), 'tenant', AskAiViewerAuthorizationService::SCOPE_AUTHORIZED);

        // Never-expose still gone even for an authorized landlord/agent.
        $this->assertArrayNotHasKey('credit_score', $out['listing']);
        $this->assertArrayNotHasKey('eviction_history', $out['listing']);

        // Authorized native subset retained: income source/amount.
        $this->assertArrayHasKey('monthly_income', $out['listing']);

        // Batch 0: an authorized counterparty is still not the owner, so the tenant's
        // Knowledge Base answers (references, income source, prior conduct) are removed too.
        $this->assertArrayNotHasKey('faq_answers', $out);
    }

    /**
     * Replaces test_non_tenant_listing_is_never_redacted, whose premise — a non-tenant,
     * non-owner context comes back byte-for-byte unchanged — was the Knowledge Base leak.
     * The rest of that premise still holds: nothing but faq_answers is removed here.
     */
    public function test_non_tenant_non_owner_listing_keeps_only_public_facts(): void
    {
        // This test used to assert the opposite — that a non-owner of a non-tenant listing
        // lost ONLY the Knowledge Base, so monthly income and a credit score passed straight
        // through to a public viewer. The listing context is now an allowlist.
        $ctx = [
            'listing'     => ['bedrooms' => 3, 'monthly_income' => '$5,000', 'credit_score' => '700'],
            'faq_answers' => ['faq_q18' => 'whatever'],
        ];
        $out = $this->svc->redactContext($ctx, 'seller', AskAiViewerAuthorizationService::SCOPE_PUBLIC);

        $this->assertSame(['listing' => ['bedrooms' => 3]], $out);
    }

    // -------------------------------------------------------------------------
    // Batch 0 — the Knowledge Base (faq_answers) is owner-only in every context
    // -------------------------------------------------------------------------

    private const KB_SENTINEL = 'KB-SENTINEL-roof-replaced-2019-by-owner';

    /**
     * A context carrying the Knowledge Base in the enriched shape the context builder
     * emits, with the sentinel buried in nested answer text, alongside listing fields,
     * seller minimums and an avatar section, so side effects on other sections show up.
     */
    private function contextWithKnowledgeBase(): array
    {
        return [
            'listing' => [
                'bedrooms'                  => 3,
                'city'                      => 'Tampa',
                'minimum_cap_rate'          => '7.5',
                'minimum_annual_net_income' => '120000',
                'hoa_fee'                   => 250,
            ],
            'faq_answers' => [
                'roof_age_and_condition' => [
                    'answer_text'           => self::KB_SENTINEL,
                    'question_label'        => 'How old is the roof, and what condition is it in?',
                    'question_group'        => 'Property Condition & Systems',
                    'intelligence_category' => 'condition',
                ],
                'seller_motivation_for_selling' => [
                    'answer_text'    => 'Relocating for work — ' . self::KB_SENTINEL,
                    'question_label' => 'Why is the owner selling the property?',
                ],
                'faq_q18'  => 'Legacy raw-string answer ' . self::KB_SENTINEL,
                'nested'   => ['deeper' => ['deepest' => self::KB_SENTINEL]],
            ],
            'buyer_avatar' => ['primary_motivation' => 'first home'],
            'agent_profile' => ['display_name' => 'Pat Agent'],
        ];
    }

    public static function allRolesProvider(): array
    {
        return [
            'seller'   => ['seller'],
            'landlord' => ['landlord'],
            'buyer'    => ['buyer'],
            'tenant'   => ['tenant'],
        ];
    }

    /**
     * Every non-owner scope the service can be handed, including values no caller should
     * produce. Anything that is not exactly SCOPE_OWNER must fail closed.
     */
    public static function nonOwnerKnowledgeBaseProvider(): array
    {
        $scopes = [
            'public'               => AskAiViewerAuthorizationService::SCOPE_PUBLIC,
            'authorized'           => AskAiViewerAuthorizationService::SCOPE_AUTHORIZED,
            'empty scope'          => '',
            'unknown scope'        => 'guest',
            'uppercase owner'      => 'OWNER',
            'padded owner'         => ' owner',
        ];

        $cases = [];
        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            foreach ($scopes as $label => $scope) {
                $cases["{$role} / {$label}"] = [$role, $scope];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider allRolesProvider
     */
    public function test_owner_retains_the_complete_knowledge_base(string $role): void
    {
        $ctx = $this->contextWithKnowledgeBase();
        $out = $this->svc->redactContext($ctx, $role, AskAiViewerAuthorizationService::SCOPE_OWNER);

        $this->assertSame($ctx, $out, "Owner context must be returned unchanged for role={$role}");
        $this->assertSame($ctx['faq_answers'], $out['faq_answers']);
    }

    /**
     * @dataProvider nonOwnerKnowledgeBaseProvider
     */
    public function test_non_owner_loses_the_entire_knowledge_base(string $role, string $scope): void
    {
        $out = $this->svc->redactContext($this->contextWithKnowledgeBase(), $role, $scope);

        $this->assertArrayNotHasKey(
            'faq_answers',
            $out,
            "faq_answers must be removed for role={$role} scope='{$scope}'"
        );

        // Gone, not renamed, blanked or moved: no nested answer text survives anywhere.
        $serialized = json_encode($out);
        $this->assertStringNotContainsString(self::KB_SENTINEL, $serialized);
        $this->assertStringNotContainsString('roof_age_and_condition', $serialized);
        $this->assertStringNotContainsString('seller_motivation_for_selling', $serialized);
        $this->assertStringNotContainsString('faq_', $serialized);
    }

    /**
     * Knowledge Base removal changes nothing else. For every role and every non-owner
     * scope, redacting a context WITH faq_answers yields exactly what redacting the same
     * context WITHOUT it yields — so no other section (seller minimums included) became
     * more or less visible as a side effect.
     *
     * @dataProvider nonOwnerKnowledgeBaseProvider
     */
    public function test_knowledge_base_removal_has_no_side_effect_on_other_sections(string $role, string $scope): void
    {
        $with    = $this->contextWithKnowledgeBase();
        $without = $with;
        unset($without['faq_answers']);

        $this->assertSame(
            $this->svc->redactContext($without, $role, $scope),
            $this->svc->redactContext($with, $role, $scope)
        );
    }

    /**
     * Seller minimums (the seller's DESIRED minimum cap rate and net income) are owner-only in
     * SnapshotFactVisibility. This docblock used to say this layer never stripped them because
     * they were "kept out of public answers" elsewhere — they were not: the deterministic
     * direct-return path answered them to public viewers from this very context. The public-fact
     * allowlist now removes them here, where every downstream path reads from.
     */
    public function test_seller_minimums_are_removed_for_non_owners(): void
    {
        $out = $this->svc->redactContext(
            $this->contextWithKnowledgeBase(),
            'seller',
            AskAiViewerAuthorizationService::SCOPE_PUBLIC
        );

        $this->assertArrayNotHasKey('minimum_cap_rate', $out['listing']);
        $this->assertArrayNotHasKey('minimum_annual_net_income', $out['listing']);
        $this->assertArrayNotHasKey('hoa_fee', $out['listing'], 'C2s compliance stripping still applies');
        $this->assertArrayNotHasKey('buyer_avatar', $out, 'Avatar stripping still applies');
        $this->assertSame(['display_name' => 'Pat Agent'], $out['agent_profile']);
    }

    /**
     * The tenant branch used to return a tenant's Knowledge Base minus a subset; the
     * non-tenant early return used to return it whole. Neither path can skip the removal.
     */
    public function test_neither_early_return_path_can_bypass_knowledge_base_removal(): void
    {
        foreach (['seller', 'landlord', 'buyer'] as $nonTenant) {
            $out = $this->svc->redactContext($this->contextWithKnowledgeBase(), $nonTenant, AskAiViewerAuthorizationService::SCOPE_PUBLIC);
            $this->assertArrayNotHasKey('faq_answers', $out, "non-tenant early return: {$nonTenant}");
        }

        foreach ([AskAiViewerAuthorizationService::SCOPE_PUBLIC, AskAiViewerAuthorizationService::SCOPE_AUTHORIZED] as $scope) {
            $out = $this->svc->redactContext($this->tenantContext(), 'tenant', $scope);
            $this->assertArrayNotHasKey('faq_answers', $out, "tenant branch: {$scope}");
        }
    }

    /**
     * Listing-type aliases and an unrecognised listing type reach the same removal:
     * ownership, not the type string, decides.
     */
    public function test_listing_type_aliases_and_unknown_types_still_remove_knowledge_base(): void
    {
        foreach ([
            'property_auction', 'seller_agent_auction', 'landlord_auction', 'landlord_agent_auction',
            'buyer_criteria_auction', 'buyer_agent_auction', 'tenant_criteria_auction',
            'tenant_agent_auction', 'TENANT', 'not_a_real_type', '',
        ] as $type) {
            $out = $this->svc->redactContext($this->contextWithKnowledgeBase(), $type, AskAiViewerAuthorizationService::SCOPE_PUBLIC);
            $this->assertArrayNotHasKey('faq_answers', $out, "listing type '{$type}'");
        }
    }

    public function test_context_without_knowledge_base_is_handled_for_non_owner(): void
    {
        $ctx = ['listing' => ['bedrooms' => 3]];

        $this->assertSame($ctx, $this->svc->redactContext($ctx, 'seller', AskAiViewerAuthorizationService::SCOPE_PUBLIC));
        $this->assertArrayNotHasKey(
            'faq_answers',
            $this->svc->redactContext($ctx + ['faq_answers' => null], 'landlord', AskAiViewerAuthorizationService::SCOPE_PUBLIC)
        );
        $this->assertArrayNotHasKey(
            'faq_answers',
            $this->svc->redactContext($ctx + ['faq_answers' => 'not-an-array ' . self::KB_SENTINEL], 'buyer', AskAiViewerAuthorizationService::SCOPE_PUBLIC)
        );
    }
}
