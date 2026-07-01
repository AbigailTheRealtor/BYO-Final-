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
