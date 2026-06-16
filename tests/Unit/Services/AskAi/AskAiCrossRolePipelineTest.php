<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiResponseContractService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiCrossRolePipelineTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Validates classifier routing, contract shapes, allowed_context contents,
 * and source_versions keys across all four listing roles.
 *
 * Coverage map:
 *   A  (seller)   — listing_facts questions route correctly
 *   B  (seller)   — financing questions route to listing_facts
 *   C  (seller)   — agent questions route to agent_profile
 *   D  (seller)   — description questions route to listing_facts
 *   E  (buyer)    — listing_facts questions route correctly
 *   F  (buyer)    — agent questions route to agent_profile
 *   G  (buyer)    — match-criteria questions route to buyer_tenant_match
 *   H  (landlord) — listing_facts questions route correctly
 *   I  (landlord) — agent questions route to agent_profile
 *   J  (landlord) — pet policy question routes to listing_facts
 *   K  (tenant)   — listing_facts questions route correctly
 *   L  (tenant)   — agent questions route to agent_profile
 *   M  (contract) — listing_facts allowed_context includes agent_profile and agent_presets
 *   N  (contract) — listing_facts is contract_ready without agent context (agent is supplemental)
 *   O  (contract) — listing_facts is contract_ready with null agent_profile
 *   P  (contract) — agent_profile contract is always contract_ready (no required_sources)
 *   Q  (contract) — listing_facts required_sources is exactly ['listing']
 *   R  (versions) — buildSourceVersions has agent_profile_available key
 *   S  (versions) — buildSourceVersions has agent_presets_count key
 *   T  (versions) — agent_profile_available=true when agentProfile is set
 *   U  (versions) — agent_presets_count reflects preset_count when agentPresets is set
 *   V  (versions) — agent_profile_available=false in buildEmptyPayload source_versions
 *   W  (chip)     — buildChipContext return shape includes agent_profile key
 *   X  (chip)     — buildChipContext return shape includes agent_presets key
 *   Y  (compound) — compound listing+agent question routes to listing_facts (agent is supplemental)
 *   Z  (compound) — listing_facts allowed_context contains both faq_answers and agent_profile
 */
class AskAiCrossRolePipelineTest extends TestCase
{
    // =========================================================================
    // Case A — Seller listing_facts routing
    // =========================================================================

    /** @dataProvider sellerListingFactsProvider */
    public function test_case_A_seller_listing_facts_questions_route_correctly(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Seller question \"{$question}\" should route to listing_facts"
        );
    }

    public static function sellerListingFactsProvider(): array
    {
        return [
            ['What is the asking price for this property?'],
            ['How many bedrooms does this home have?'],
            ['What is the square footage?'],
            ['Does this property have a pool?'],
            ['When was this home built?'],
            ['What is the annual property tax?'],
        ];
    }

    // =========================================================================
    // Case B — Seller financing questions route to listing_facts
    // =========================================================================

    /** @dataProvider sellerFinancingProvider */
    public function test_case_B_seller_financing_questions_route_to_listing_facts(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Financing question \"{$question}\" should route to listing_facts"
        );
    }

    public static function sellerFinancingProvider(): array
    {
        return [
            ['What financing options does the seller accept?'],
            ['Is FHA financing accepted?'],
            ['Does the seller accept VA loans?'],
            ['What are the seller financing terms?'],
            ['Is conventional financing allowed?'],
            ['Does the seller accept USDA loans?'],
        ];
    }

    // =========================================================================
    // Case C — Seller agent questions route to agent_profile
    // =========================================================================

    /** @dataProvider sellerAgentProvider */
    public function test_case_C_seller_agent_questions_route_to_agent_profile(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'agent_profile',
            $result['question_type'],
            "Seller agent question \"{$question}\" should route to agent_profile"
        );
    }

    public static function sellerAgentProvider(): array
    {
        return [
            ['What services does the agent provide?'],
            ['Who is the listing agent?'],
            ['What is the agent bio?'],
            ['Tell me about the agent'],
        ];
    }

    // =========================================================================
    // Case D — Seller description questions route to listing_facts
    // =========================================================================

    /** @dataProvider sellerDescriptionProvider */
    public function test_case_D_seller_description_questions_route_to_listing_facts(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Description question \"{$question}\" should route to listing_facts"
        );
    }

    public static function sellerDescriptionProvider(): array
    {
        return [
            ['Tell me about this property'],
            ['Describe this home'],
        ];
    }

    // =========================================================================
    // Case E — Buyer listing_facts routing
    // =========================================================================

    /** @dataProvider buyerListingFactsProvider */
    public function test_case_E_buyer_listing_facts_questions_route_correctly(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Buyer question \"{$question}\" should route to listing_facts"
        );
    }

    public static function buyerListingFactsProvider(): array
    {
        return [
            ['What is the maximum purchase price for this buyer?'],
            ['How many bedrooms is this buyer looking for?'],
            ['What is the buyer financing type?'],
            ['Is the buyer pre-approved for a loan?'],
        ];
    }

    // =========================================================================
    // Case F — Buyer agent questions route to agent_profile
    // =========================================================================

    public function test_case_F_buyer_agent_question_routes_to_agent_profile(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('Who is the agent representing this buyer?');

        $this->assertSame('agent_profile', $result['question_type']);
    }

    // =========================================================================
    // Case G — Buyer match-criteria questions route to buyer_tenant_match
    // =========================================================================

    /** @dataProvider buyerMatchCriteriaProvider */
    public function test_case_G_buyer_match_criteria_questions_route_correctly(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'buyer_tenant_match',
            $result['question_type'],
            "Buyer match question \"{$question}\" should route to buyer_tenant_match"
        );
    }

    public static function buyerMatchCriteriaProvider(): array
    {
        return [
            ['Does this listing match my criteria?'],
            ['How well does this property match buyer requirements?'],
        ];
    }

    // =========================================================================
    // Case H — Landlord listing_facts routing
    // =========================================================================

    /** @dataProvider landlordListingFactsProvider */
    public function test_case_H_landlord_listing_facts_questions_route_correctly(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Landlord question \"{$question}\" should route to listing_facts"
        );
    }

    public static function landlordListingFactsProvider(): array
    {
        return [
            ['What is the monthly rent for this property?'],
            ['What lease length is available?'],
            ['Are utilities included in the rent?'],
            ['What is the security deposit amount?'],
        ];
    }

    // =========================================================================
    // Case I — Landlord agent questions route to agent_profile
    // =========================================================================

    public function test_case_I_landlord_agent_question_routes_to_agent_profile(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What are the agent credentials for this rental listing?');

        $this->assertSame('agent_profile', $result['question_type']);
    }

    // =========================================================================
    // Case J — Landlord pet policy routes to listing_facts
    // =========================================================================

    public function test_case_J_landlord_pet_policy_routes_to_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What is the pet policy for this rental?');

        $this->assertSame('listing_facts', $result['question_type']);
    }

    // =========================================================================
    // Case K — Tenant listing_facts routing
    // =========================================================================

    /** @dataProvider tenantListingFactsProvider */
    public function test_case_K_tenant_listing_facts_questions_route_correctly(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Tenant question \"{$question}\" should route to listing_facts"
        );
    }

    public static function tenantListingFactsProvider(): array
    {
        return [
            ['What is the tenant rental budget?'],
            ['What lease length is desired for this tenant?'],
            ['Does the tenant have pets?'],
        ];
    }

    // =========================================================================
    // Case L — Tenant agent questions route to agent_profile
    // =========================================================================

    public function test_case_L_tenant_agent_question_routes_to_agent_profile(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What services does the agent offer for tenant representation?');

        $this->assertSame('agent_profile', $result['question_type']);
    }

    // =========================================================================
    // Case M — listing_facts allowed_context includes agent_profile and agent_presets
    // =========================================================================

    public function test_case_M_listing_facts_allowed_context_includes_agent_profile(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', [
            'listing' => ['listing_type' => 'seller', 'listing_id' => 1],
        ]);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertContains(
            'agent_profile',
            $result['allowed_context'],
            'listing_facts allowed_context must include agent_profile for compound questions'
        );
    }

    public function test_case_M_listing_facts_allowed_context_includes_agent_presets(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', [
            'listing' => ['listing_type' => 'seller', 'listing_id' => 1],
        ]);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertContains(
            'agent_presets',
            $result['allowed_context'],
            'listing_facts allowed_context must include agent_presets for compound questions'
        );
    }

    // =========================================================================
    // Case N — listing_facts contract_ready without agent context
    // =========================================================================

    public function test_case_N_listing_facts_is_contract_ready_without_agent_context(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', [
            'listing'       => ['listing_type' => 'landlord', 'listing_id' => 5],
            'faq_answers'   => [],
        ]);

        $this->assertSame(
            'contract_ready',
            $result['status'],
            'listing_facts must be contract_ready even when agent_profile is absent — agent is supplemental'
        );
    }

    // =========================================================================
    // Case O — listing_facts contract_ready with null agent_profile
    // =========================================================================

    public function test_case_O_listing_facts_contract_ready_with_null_agent_profile(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', [
            'listing'       => ['listing_type' => 'buyer', 'listing_id' => 3],
            'faq_answers'   => [],
            'agent_profile' => null,
            'agent_presets' => null,
        ]);

        $this->assertSame('contract_ready', $result['status']);
    }

    // =========================================================================
    // Case P — agent_profile contract always contract_ready (no required_sources)
    // =========================================================================

    public function test_case_P_agent_profile_is_contract_ready_with_no_agent_context(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', [
            'listing'       => ['listing_type' => 'tenant', 'listing_id' => 9],
            'agent_profile' => null,
            'agent_presets' => null,
        ]);

        $this->assertSame(
            'contract_ready',
            $result['status'],
            'agent_profile contract must always be contract_ready regardless of agent context'
        );
    }

    public function test_case_P_agent_profile_required_sources_is_empty(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', [
            'listing' => ['listing_type' => 'seller', 'listing_id' => 1],
        ]);

        $this->assertEmpty(
            $result['required_sources'],
            'agent_profile required_sources must be empty so contract is always ready'
        );
    }

    // =========================================================================
    // Case Q — listing_facts required_sources is exactly ['listing']
    // =========================================================================

    public function test_case_Q_listing_facts_required_sources_is_listing_only(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', [
            'listing' => ['listing_type' => 'seller', 'listing_id' => 1],
        ]);

        $this->assertSame(
            ['listing'],
            $result['required_sources'],
            'listing_facts required_sources must be exactly [listing]'
        );
    }

    // =========================================================================
    // Case R — buildSourceVersions has agent_profile_available key
    // =========================================================================

    public function test_case_R_build_source_versions_has_agent_profile_available_key(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $versions = $builder->callBuildSourceVersions(null, null, null, null, null, null, null);

        $this->assertArrayHasKey(
            'agent_profile_available',
            $versions,
            'source_versions must have agent_profile_available key'
        );
    }

    // =========================================================================
    // Case S — buildSourceVersions has agent_presets_count key
    // =========================================================================

    public function test_case_S_build_source_versions_has_agent_presets_count_key(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $versions = $builder->callBuildSourceVersions(null, null, null, null, null, null, null);

        $this->assertArrayHasKey(
            'agent_presets_count',
            $versions,
            'source_versions must have agent_presets_count key'
        );
    }

    // =========================================================================
    // Case T — agent_profile_available=true when agentProfile is non-null
    // =========================================================================

    public function test_case_T_agent_profile_available_is_true_when_profile_loaded(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $versions = $builder->callBuildSourceVersions(
            null, null, null, null, null,
            ['agent_name' => 'Abigail Sweeney'],
            null
        );

        $this->assertTrue(
            $versions['agent_profile_available'],
            'agent_profile_available must be true when agentProfile array is provided'
        );
    }

    public function test_case_T_agent_profile_available_is_false_when_profile_null(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $versions = $builder->callBuildSourceVersions(null, null, null, null, null, null, null);

        $this->assertFalse(
            $versions['agent_profile_available'],
            'agent_profile_available must be false when agentProfile is null'
        );
    }

    // =========================================================================
    // Case U — agent_presets_count reflects preset_count from agentPresets
    // =========================================================================

    public function test_case_U_agent_presets_count_reflects_preset_count(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $versions = $builder->callBuildSourceVersions(
            null, null, null, null, null,
            ['agent_name' => 'Test Agent'],
            ['preset_count' => 7, 'presets' => []]
        );

        $this->assertSame(
            7,
            $versions['agent_presets_count'],
            'agent_presets_count must reflect preset_count from agentPresets'
        );
    }

    public function test_case_U_agent_presets_count_is_zero_when_presets_null(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $versions = $builder->callBuildSourceVersions(null, null, null, null, null, null, null);

        $this->assertSame(
            0,
            $versions['agent_presets_count'],
            'agent_presets_count must be 0 when agentPresets is null'
        );
    }

    // =========================================================================
    // Case V — empty payload source_versions contains agent keys
    // =========================================================================

    public function test_case_V_empty_payload_source_versions_has_agent_keys(): void
    {
        $builder  = $this->makeVersionsTestBuilder();
        $payload  = $builder->callBuildEmptyPayload('not_found', 'seller', 999999);

        $this->assertArrayHasKey('source_versions', $payload);
        $this->assertArrayHasKey(
            'agent_profile_available',
            $payload['source_versions'],
            'source_versions must include agent_profile_available key in empty payload'
        );
        $this->assertArrayHasKey(
            'agent_presets_count',
            $payload['source_versions'],
            'source_versions must include agent_presets_count key in empty payload'
        );
        $this->assertFalse($payload['source_versions']['agent_profile_available']);
        $this->assertSame(0, $payload['source_versions']['agent_presets_count']);
    }

    // =========================================================================
    // Case W — buildChipContext returns agent_profile key
    // =========================================================================

    public function test_case_W_build_chip_context_returns_agent_profile_key(): void
    {
        $builder = $this->makeChipContextTestBuilder();
        $listing = $this->makeMinimalListingStub();
        $result  = $builder->buildChipContext($listing, 'seller');

        $this->assertArrayHasKey(
            'agent_profile',
            $result,
            'buildChipContext must return agent_profile key'
        );
    }

    // =========================================================================
    // Case X — buildChipContext returns agent_presets key for all roles
    // =========================================================================

    public function test_case_X_build_chip_context_returns_agent_presets_key(): void
    {
        $builder = $this->makeChipContextTestBuilder();
        $listing = $this->makeMinimalListingStub();
        $result  = $builder->buildChipContext($listing, 'seller');

        $this->assertArrayHasKey(
            'agent_presets',
            $result,
            'buildChipContext must return agent_presets key'
        );
    }

    /** @dataProvider chipContextRolesProvider */
    public function test_case_X_build_chip_context_returns_agent_keys_for_all_roles(string $role): void
    {
        $builder = $this->makeChipContextTestBuilder();
        $listing = $this->makeMinimalListingStub();
        $result  = $builder->buildChipContext($listing, $role);

        $this->assertArrayHasKey('agent_profile', $result);
        $this->assertArrayHasKey('agent_presets', $result);
    }

    public static function chipContextRolesProvider(): array
    {
        return [
            ['seller'],
            ['buyer'],
            ['landlord'],
            ['tenant'],
        ];
    }

    // =========================================================================
    // Case Y — Compound question routes to listing_facts (agent is supplemental)
    // =========================================================================

    /** @dataProvider compoundListingAgentProvider */
    public function test_case_Y_compound_listing_and_agent_question_routes_to_listing_facts(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Compound question \"{$question}\" with listing context should still route to listing_facts"
        );
    }

    public static function compoundListingAgentProvider(): array
    {
        return [
            ['What is the asking price and what financing does the seller accept?'],
            ['How many bedrooms and what is the square footage of this property?'],
            ['What are the sale terms for this listing?'],
        ];
    }

    // =========================================================================
    // Case Z — listing_facts allowed_context contains both faq_answers and agent_profile
    // =========================================================================

    public function test_case_Z_listing_facts_allowed_context_has_faq_answers_and_agent_profile(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', [
            'listing' => ['listing_type' => 'seller', 'listing_id' => 1],
        ]);

        $allowed = $result['allowed_context'];

        $this->assertContains(
            'faq_answers',
            $allowed,
            'listing_facts allowed_context must include faq_answers'
        );
        $this->assertContains(
            'agent_profile',
            $allowed,
            'listing_facts allowed_context must include agent_profile'
        );
        $this->assertContains(
            'agent_presets',
            $allowed,
            'listing_facts allowed_context must include agent_presets'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeMinimalListingStub(): object
    {
        return new class {
            public int    $id      = 0;
            public ?int   $user_id = null;
            public string $title   = 'Test listing';
        };
    }

    /**
     * Create an anonymous subclass of AskAiContextBuilderService that:
     *  - Bypasses the constructor (no DI dependencies needed)
     *  - Exposes buildSourceVersions (protected) as callBuildSourceVersions (public)
     *  - Exposes buildEmptyPayload (private) as callBuildEmptyPayload (public)
     *
     * Used by Cases R, S, T, U, V to test version-tracking behaviour without a DB.
     */
    private function makeVersionsTestBuilder(): object
    {
        return new class extends AskAiContextBuilderService {
            public function __construct()
            {
            }

            public function callBuildSourceVersions(
                ?array $propertyIntelligence,
                ?array $locationIntelligence,
                ?array $buyerAvatar,
                ?array $tenantAvatar,
                ?array $compatibility,
                ?array $agentProfile = null,
                ?array $agentPresets = null
            ): array {
                return $this->buildSourceVersions(
                    $propertyIntelligence,
                    $locationIntelligence,
                    $buyerAvatar,
                    $tenantAvatar,
                    $compatibility,
                    $agentProfile,
                    $agentPresets
                );
            }

            public function callBuildEmptyPayload(string $status, string $listingType, int $listingId): array
            {
                return $this->buildEmptyPayload($status, $listingType, $listingId);
            }
        };
    }

    /**
     * Create an anonymous subclass of AskAiContextBuilderService that:
     *  - Bypasses the constructor
     *  - Stubs all DB-touching internal methods so buildChipContext runs without a DB
     *  - normalizeListingType passes through the canonical type unchanged
     *
     * Used by Cases W, X to verify buildChipContext return-shape without a DB.
     */
    private function makeChipContextTestBuilder(): object
    {
        return new class extends AskAiContextBuilderService {
            public function __construct()
            {
            }

            protected function normalizeListingType(string $listingType): string
            {
                return $listingType;
            }

            protected function extractListingFields(object $listing, string $canonicalType, int $listingId): array
            {
                return [];
            }

            protected function buildFaqAnswers(object $listing, string $canonicalType): array
            {
                return [];
            }

            protected function buildAgentProfile(string $canonicalType, object $listing): ?array
            {
                return null;
            }

            protected function buildAgentPresets(string $canonicalType, object $listing): ?array
            {
                return null;
            }
        };
    }
}
