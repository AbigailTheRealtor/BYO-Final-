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
    private function safeKeys(): array
    {
        return ['bedrooms', 'bathrooms', 'city', 'current_use', 'rental_purpose'];
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
        // Applicant-sensitive FAQ gone for public.
        $this->assertArrayNotHasKey('faq_q17', $out['faq_answers']);
        $this->assertArrayNotHasKey('faq_q18', $out['faq_answers']);
        $this->assertArrayNotHasKey('tenant_prior_conduct', $out['faq_answers']);

        // Non-sensitive fields retained.
        $this->assertArrayHasKey('desired_lease_length', $out['listing']);
        $this->assertArrayHasKey('pets_allowed', $out['listing']);
        $this->assertArrayHasKey('faq_q10', $out['faq_answers']);
    }

    public function test_authorized_scope_keeps_applicant_subset_but_drops_never_expose(): void
    {
        $out = $this->svc->redactContext($this->tenantContext(), 'tenant', AskAiViewerAuthorizationService::SCOPE_AUTHORIZED);

        // Never-expose still gone even for an authorized landlord/agent.
        $this->assertArrayNotHasKey('credit_score', $out['listing']);
        $this->assertArrayNotHasKey('eviction_history', $out['listing']);

        // Authorized subset retained: income source/amount + references + conduct.
        $this->assertArrayHasKey('monthly_income', $out['listing']);
        $this->assertArrayHasKey('faq_q17', $out['faq_answers']);
        $this->assertArrayHasKey('faq_q18', $out['faq_answers']);
        $this->assertArrayHasKey('tenant_prior_conduct', $out['faq_answers']);
    }

    public function test_non_tenant_listing_is_never_redacted(): void
    {
        $ctx = [
            'listing'     => ['monthly_income' => '$5,000', 'credit_score' => '700'],
            'faq_answers' => ['faq_q18' => 'whatever'],
        ];
        $out = $this->svc->redactContext($ctx, 'seller', AskAiViewerAuthorizationService::SCOPE_PUBLIC);
        $this->assertSame($ctx, $out);
    }
}
