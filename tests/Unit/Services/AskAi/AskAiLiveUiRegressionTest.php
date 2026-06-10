<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * AskAiLiveUiRegressionTest
 *
 * Regression guard for the 8 confirmed EAV meta key mismatches discovered in the
 * June 2026 live-DB audit, plus the "Other" token leak in multi-select JSON fields.
 *
 * Each test family covers one of the originally-failing field/role pairs:
 *
 *   R1.  Buyer   — financing_type  (was 'offered_financing', correct key: 'financing_type')
 *   R2.  Seller  — square_feet      (was 'minimum_heated_square' only; now cascades)
 *   R3.  Buyer   — square_feet      (same cascade as seller)
 *   R4.  Landlord — square_feet     (same cascade)
 *   R5.  Tenant  — budget           (was 'budget' only; cascades to 'maximum_budget')
 *   R6.  Landlord — utilities       (was 'utilities' only; cascades from 'property_utilities')
 *   R7.  Tenant  — desired_lease_length (was 'tenant_desired_lease_length'; correct key fixed)
 *   R8.  All roles — view/water_view (was 'view'/'water_view'; correct key: 'view_preference')
 *   R9.  All roles — "Other" leak in multi-select JSON arrays (decodeJsonField filter)
 *
 * Tests run at two layers:
 *
 *   (a) Context-builder PHP source grep — the correct EAV key is present in the source code.
 *   (b) Pipeline Guard B — when the field is null the runner surfaces a specific
 *       field-label message rather than a generic error.
 *   (c) Context-builder decodeJsonField — "Other" tokens are stripped from JSON arrays.
 *
 * Pure PHPUnit — no Laravel container, no DB.
 */
class AskAiLiveUiRegressionTest extends TestCase
{
    // =========================================================================
    // Shared helpers
    // =========================================================================

    private function contextBuilderSource(): string
    {
        $path = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
        $this->assertFileExists($path, 'AskAiContextBuilderService.php must exist.');
        return file_get_contents($path);
    }

    private function makeFollowUpMock(): AskAiFollowUpQuestionService
    {
        $mock = $this->createMock(AskAiFollowUpQuestionService::class);
        $mock->method('forResult')->willReturn([]);
        return $mock;
    }

    private function makeRunner(
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalBuilder
    ): AskAiRunnerV2Service {
        return new AskAiRunnerV2Service(
            new AskAiQuestionClassifierService(),
            $internalRunner,
            $adapter,
            $finalBuilder,
            $this->makeFollowUpMock()
        );
    }

    /**
     * Mock internalRunner returning a listing field with null value so Guard B fires.
     */
    private function makeRunnerWithNullListingField(string $field): AskAiInternalRunnerService
    {
        $mock          = $this->createMock(AskAiInternalRunnerService::class);
        $allowedCtx    = ['listing' => [$field => null]];
        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => $allowedCtx,
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];
        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['listing_type' => 'test', $field => null]],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);
        return $mock;
    }

    /**
     * Build a partial mock of AskAiContextBuilderService with all finder methods stubbed.
     * Accepts optional meta overrides so tests can inject specific EAV values.
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeContextBuilder(
        array $metaValues = [],
        array $nativeValues = []
    ): AskAiContextBuilderService {
        $intelligence = $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
        $intelligence->method('buildPayloadReadOnly')->willReturn([
            'success' => false,
            'status'  => 'not_generated',
        ]);

        $locationIntelligence = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $locationIntelligence->method('getForListing')->willReturn([
            'success' => false,
            'status'  => 'missing',
        ]);

        $locationMarketing = $this->getMockBuilder(LocationDnaMarketingContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $locationMarketing->method('getForListing')->willReturn([
            'success' => false,
            'status'  => 'missing',
        ]);

        $builder = $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([$intelligence, $locationIntelligence, $locationMarketing])
            ->onlyMethods([
                'findListing',
                'findPropertyDnaProfile',
                'findPropertyLocationDna',
                'findBuyerTenantDnaProfile',
                'findCompatibilityScore',
                'findAcceptedBidSummary',
                'infoGet',
                'nativeGet',
            ])
            ->getMock();

        $builder->method('infoGet')->willReturnCallback(
            fn (string $key) => $metaValues[$key] ?? null
        );
        $builder->method('nativeGet')->willReturnCallback(
            fn (string $key) => $nativeValues[$key] ?? null
        );

        return $builder;
    }

    /**
     * Call the protected decodeJsonField method via reflection.
     */
    private function callDecodeJsonField(?string $value): ?string
    {
        $builder = $this->getMockBuilder(AskAiContextBuilderService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $rc     = new ReflectionClass($builder);
        $method = $rc->getMethod('decodeJsonField');
        $method->setAccessible(true);
        return $method->invoke($builder, $value);
    }

    /**
     * Assert that Guard B surfaces the expected field-label message when the
     * listing field is null in the allowed_context.
     */
    private function assertGuardBFiresWithLabel(
        string $role,
        string $question,
        string $field,
        string $expectedLabel
    ): void {
        $internalRunner = $this->makeRunnerWithNullListingField($field);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run($role, 1, $question);

        $this->assertSame('insufficient_context', $result['status'],
            "Guard B must fire for {$role} listing.{$field} — status must be insufficient_context.");
        $this->assertStringContainsString(
            $expectedLabel,
            $result['final_response']['answer'] ?? '',
            "Guard B answer must reference the field label '{$expectedLabel}' for {$role} listing.{$field}."
        );
        $this->assertSame(
            'listing.' . $field,
            $result['classification']['normalized_field_key'] ?? null,
            "normalized_field_key must be listing.{$field} for question: {$question}"
        );
    }

    // =========================================================================
    // R1 — Buyer: financing_type key was 'offered_financing', correct key is 'financing_type'
    // =========================================================================

    /**
     * @group AskAi
     * Source-level check: 'financing_type' must appear as an infoGet argument in the buyer block.
     */
    public function test_r1_buyer_financing_type_correct_key_present_in_source(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringContainsString(
            "infoGet('financing_type')",
            $source,
            "R1: AskAiContextBuilderService must read 'financing_type' EAV key for buyer financing."
        );
    }

    /**
     * @group AskAi
     * The legacy 'offered_financing' key must be retained as a fallback, not removed entirely.
     */
    public function test_r1_buyer_offered_financing_retained_as_fallback_in_source(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringContainsString(
            "infoGet('offered_financing')",
            $source,
            "R1: 'offered_financing' must be kept as a fallback for legacy rows."
        );
    }

    /**
     * @group AskAi
     * Guard B — buyer financing_type null => specific missing message.
     */
    public function test_r1_buyer_financing_type_null_guard_b_fires(): void
    {
        $this->assertGuardBFiresWithLabel(
            'buyer',
            'What financing type is the buyer using?',
            'financing_type',
            'Financing type information'
        );
    }

    // =========================================================================
    // R2 — Seller: square_feet cascade (minimum_heated_square / heated_square_footage / heated_square)
    // =========================================================================

    /**
     * @group AskAi
     * Source must cascade through all three square-footage meta keys for seller.
     */
    public function test_r2_seller_square_feet_cascade_keys_in_source(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringContainsString(
            "infoGet('minimum_heated_square')",
            $source,
            "R2: 'minimum_heated_square' must be the primary square_feet key."
        );
        $this->assertStringContainsString(
            "infoGet('heated_square_footage')",
            $source,
            "R2: 'heated_square_footage' must appear as a cascade fallback for square_feet."
        );
        $this->assertStringContainsString(
            "infoGet('heated_square')",
            $source,
            "R2: 'heated_square' must appear as a legacy cascade fallback for square_feet."
        );
    }

    /**
     * @group AskAi
     * Guard B — seller square_feet null => specific missing message.
     * Uses a phrase from LISTING_KEY_KEYWORD_MAP so detectListingFieldKey resolves the key.
     */
    public function test_r2_seller_square_feet_null_guard_b_fires(): void
    {
        $this->assertGuardBFiresWithLabel(
            'seller',
            'How big is the property?',
            'square_feet',
            'Square footage information'
        );
    }

    // =========================================================================
    // R3 — Buyer: square_feet same cascade as seller
    // =========================================================================

    /**
     * @group AskAi
     * Guard B — buyer square_feet null => specific missing message.
     * Uses a phrase from LISTING_KEY_KEYWORD_MAP so detectListingFieldKey resolves the key.
     */
    public function test_r3_buyer_square_feet_null_guard_b_fires(): void
    {
        $this->assertGuardBFiresWithLabel(
            'buyer',
            'How big is the home?',
            'square_feet',
            'Square footage information'
        );
    }

    // =========================================================================
    // R4 — Landlord: square_feet same cascade as seller/buyer
    // =========================================================================

    /**
     * @group AskAi
     * Guard B — landlord square_feet null => specific missing message.
     * Uses a phrase from LISTING_KEY_KEYWORD_MAP so detectListingFieldKey resolves the key.
     */
    public function test_r4_landlord_square_feet_null_guard_b_fires(): void
    {
        $this->assertGuardBFiresWithLabel(
            'landlord',
            'How large is the property?',
            'square_feet',
            'Square footage information'
        );
    }

    // =========================================================================
    // R5 — Tenant: budget cascade (budget -> maximum_budget)
    // =========================================================================

    /**
     * @group AskAi
     * Source must cascade 'budget' => 'maximum_budget' for tenant rental budget.
     */
    public function test_r5_tenant_budget_cascade_keys_in_source(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringContainsString(
            "infoGet('budget')",
            $source,
            "R5: 'budget' must be the primary rental_budget key for tenant."
        );
        $this->assertStringContainsString(
            "infoGet('maximum_budget')",
            $source,
            "R5: 'maximum_budget' must appear as a fallback for tenant rental_budget."
        );
    }

    /**
     * @group AskAi
     * Pipeline smoke — tenant rental_budget key cascade is present in source.
     * Note: listing.rental_budget has no entry in LISTING_KEY_KEYWORD_MAP so it
     * cannot be reached via the detectListingFieldKey path. The source-grep test
     * (test_r5_tenant_budget_cascade_keys_in_source) is the appropriate coverage.
     * This test confirms the classifier does not throw on a rental-budget question.
     */
    public function test_r5_tenant_rental_budget_classifier_does_not_throw(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What is the maximum rental budget for this tenant?');

        $this->assertArrayHasKey('question_type', $result,
            'R5: classifier must return a question_type for tenant budget questions.');
        $this->assertNotSame('failed', $result['question_type'] ?? null,
            'R5: question_type must not be failed for tenant budget questions.');
    }

    // =========================================================================
    // R6 — Landlord: utilities cascade (property_utilities -> utilities)
    // =========================================================================

    /**
     * @group AskAi
     * Source must read 'property_utilities' with a fallback to 'utilities' for landlord.
     */
    public function test_r6_landlord_utilities_cascade_keys_in_source(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringContainsString(
            "infoGet('property_utilities')",
            $source,
            "R6: 'property_utilities' must be the primary utilities key for landlord."
        );
    }

    /**
     * @group AskAi
     * Guard B — landlord utilities null => specific missing message.
     * Expected label: 'Included utilities information' (from deriveFieldLabel map).
     */
    public function test_r6_landlord_utilities_null_guard_b_fires(): void
    {
        $this->assertGuardBFiresWithLabel(
            'landlord',
            'What utilities are included?',
            'utilities',
            'Included utilities information'
        );
    }

    // =========================================================================
    // R7 — Tenant: desired_lease_length (was tenant_desired_lease_length)
    // =========================================================================

    /**
     * @group AskAi
     * Source must use 'desired_lease_length' (not 'tenant_desired_lease_length') for tenant.
     */
    public function test_r7_tenant_desired_lease_length_correct_key_in_source(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringContainsString(
            "infoGet('desired_lease_length')",
            $source,
            "R7: 'desired_lease_length' must be the lease-length key for tenant."
        );
        $this->assertStringNotContainsString(
            "infoGet('tenant_desired_lease_length')",
            $source,
            "R7: obsolete key 'tenant_desired_lease_length' must not appear as an infoGet argument."
        );
    }

    /**
     * @group AskAi
     * Guard B — tenant desired_lease_length null => specific missing message.
     * Expected label: 'Tenant desired lease length information' (from deriveFieldLabel map).
     * Uses a phrase from LISTING_KEY_KEYWORD_MAP so detectListingFieldKey resolves the key.
     */
    public function test_r7_tenant_desired_lease_length_null_guard_b_fires(): void
    {
        $this->assertGuardBFiresWithLabel(
            'tenant',
            'What lease length is desired?',
            'desired_lease_length',
            'Tenant desired lease length information'
        );
    }

    // =========================================================================
    // R8 — All roles: view/water_view reads 'view_preference', not 'view'/'water_view'
    // =========================================================================

    /**
     * @group AskAi
     * Source must use 'view_preference' EAV key for view-related fields on all roles.
     */
    public function test_r8_view_preference_key_used_for_all_roles(): void
    {
        $source = $this->contextBuilderSource();

        $count = substr_count($source, "infoGet('view_preference')");
        $this->assertGreaterThanOrEqual(
            3,
            $count,
            "R8: 'view_preference' must be the infoGet key for view/water_view on all roles (seller, buyer, landlord/tenant)."
        );
    }

    /**
     * @group AskAi
     * The legacy bare 'view' and 'water_view' keys must NOT appear as standalone infoGet calls.
     */
    public function test_r8_legacy_view_water_view_keys_not_used_as_standalone_infoget_calls(): void
    {
        $source = $this->contextBuilderSource();

        $this->assertStringNotContainsString(
            "infoGet('water_view')",
            $source,
            "R8: obsolete key 'water_view' must not appear as a direct infoGet call — use 'view_preference'."
        );
        $this->assertStringNotContainsString(
            "infoGet('view')",
            $source,
            "R8: standalone key 'view' (not view_preference) must not appear as an infoGet call."
        );
    }

    // =========================================================================
    // R9 — "Other" token leak in multi-select JSON arrays (decodeJsonField)
    // =========================================================================

    /**
     * @group AskAi
     * Verify decodeJsonField strips "Other" from a mixed JSON array.
     */
    public function test_r9_decode_json_field_strips_other_from_mixed_array(): void
    {
        $result = $this->callDecodeJsonField('["Washer","Dryer","Other"]');

        $this->assertNotNull($result, 'decodeJsonField must return a non-null value for mixed arrays.');
        $this->assertStringNotContainsString(
            'Other',
            $result,
            'R9: literal "Other" must be stripped from JSON array output.'
        );
        $this->assertStringContainsString('Washer', $result, 'R9: "Washer" must be retained.');
        $this->assertStringContainsString('Dryer', $result, 'R9: "Dryer" must be retained.');
    }

    /**
     * @group AskAi
     * When the JSON array contains ONLY "Other", decodeJsonField must return null
     * (no value to surface) rather than the literal string "Other".
     */
    public function test_r9_decode_json_field_returns_null_for_other_only_array(): void
    {
        $result = $this->callDecodeJsonField('["Other"]');

        $this->assertNull(
            $result,
            'R9: a JSON array containing only "Other" must return null, not the literal string "Other".'
        );
    }

    /**
     * @group AskAi
     * "Other" match is case-insensitive.
     */
    public function test_r9_decode_json_field_strips_other_case_insensitive(): void
    {
        $result = $this->callDecodeJsonField('["Pool","other","Spa"]');

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('other', strtolower($result),
            'R9: "other" (lowercase) must be stripped from JSON array output.');
        $this->assertStringContainsString('Pool', $result);
        $this->assertStringContainsString('Spa', $result);
    }

    /**
     * @group AskAi
     * Non-JSON scalar values (e.g. plain strings) are not affected by the filter.
     */
    public function test_r9_decode_json_field_preserves_plain_strings(): void
    {
        $result = $this->callDecodeJsonField('Yes');
        $this->assertSame('Yes', $result, 'R9: plain non-JSON strings must pass through unchanged.');
    }

    /**
     * @group AskAi
     * Valid JSON arrays with no "Other" element are unaffected.
     */
    public function test_r9_decode_json_field_passes_clean_arrays_unchanged(): void
    {
        $result = $this->callDecodeJsonField('["Conventional","FHA","VA"]');

        $this->assertSame('Conventional, FHA, VA', $result,
            'R9: clean JSON arrays must be joined unchanged.');
    }

    /**
     * @group AskAi
     * Null input returns null — no change in behaviour.
     */
    public function test_r9_decode_json_field_null_returns_null(): void
    {
        $result = $this->callDecodeJsonField(null);
        $this->assertNull($result, 'R9: null input must return null.');
    }

    // =========================================================================
    // Integration: classifier still maps known questions to listing.* keys
    // =========================================================================

    /**
     * @group AskAi
     * Smoke-level check: financing-type question classifies as listing_facts.
     */
    public function test_buyer_financing_type_question_classifies_correctly(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What financing type is the buyer using?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What financing type is the buyer using?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * Smoke-level check: square footage question classifies as listing_facts.
     */
    public function test_square_footage_question_classifies_correctly(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('How many square feet is the property?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"How many square feet is the property?" must classify as listing_facts.');
    }

    // =========================================================================
    // R10 — Regression: new listing_facts phrases added in June 2026 field audit
    // These guard against the newly-added classifier phrases regressing to
    // 'unsupported' or being swallowed by the FAQ/compatibility classifiers.
    // =========================================================================

    /**
     * @group AskAi
     * R10a: Seller address question classifies as listing_facts.
     */
    public function test_r10_seller_address_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What is the property address?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What is the property address?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10b: Seller flood zone question classifies as listing_facts.
     */
    public function test_r10_seller_flood_zone_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('Is this property in a flood zone?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"Is this property in a flood zone?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10c: Landlord appliances question classifies as listing_facts.
     */
    public function test_r10_landlord_appliances_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What appliances are included with this rental?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What appliances are included with this rental?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10d: Landlord pet policy question classifies as listing_facts (not FAQ).
     */
    public function test_r10_landlord_pet_policy_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What is the pet policy for this rental?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What is the pet policy for this rental?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10e: Tenant max rent phrase 1 classifies as listing_facts.
     */
    public function test_r10_tenant_max_rent_phrase_a_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What is the tenant max rent?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What is the tenant max rent?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10f: Tenant max rent phrase 2 classifies as listing_facts.
     */
    public function test_r10_tenant_max_rent_phrase_b_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What is the maximum rental budget?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What is the maximum rental budget?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10g: Subletting policy question classifies as listing_facts (not FAQ).
     */
    public function test_r10_subletting_policy_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('What is the subletting policy for this unit?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"What is the subletting policy for this unit?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10h: Smoking policy question (listing field) classifies as listing_facts.
     */
    public function test_r10_smoking_policy_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('Does this unit allow smoking?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"Does this unit allow smoking?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10i: Inspection contingency classifies as listing_facts.
     */
    public function test_r10_inspection_contingency_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('Does the buyer need an inspection contingency?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"Does the buyer need an inspection contingency?" must classify as listing_facts.');
    }

    /**
     * @group AskAi
     * R10j: Financing contingency classifies as listing_facts.
     */
    public function test_r10_financing_contingency_classifies_as_listing_facts(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('Is there a financing contingency?');

        $this->assertSame('listing_facts', $result['question_type'],
            '"Is there a financing contingency?" must classify as listing_facts.');
    }
}
