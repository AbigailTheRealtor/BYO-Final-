<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;

/**
 * AskAiListingFieldPipelineE2ETest
 *
 * End-to-end pipeline proof for the 10 listing.* field scenarios requested by
 * the source connectivity audit.  Each scenario covers four assertions:
 *
 *   (a) Classification: question → listing_facts + correct normalized_field_key
 *   (b) Field PRESENT:  adapter called once, finalBuilder called once, status=ready
 *   (c) Field NULL:     Guard B fires, adapter/finalBuilder never called,
 *                       answer = field-specific missing-data message
 *   (d) OpenAI DISABLED direct-return fallback: adapter called once (fails),
 *                       finalBuilder never called, status=ready, answer = raw value
 *
 * Scenarios covered:
 *   1.  Seller  — "What are the taxes?"               → listing.annual_property_taxes
 *   2.  Landlord — "What appliances are included?"    → listing.appliances
 *   3.  Seller  — "How many bedrooms?"                → listing.bedrooms
 *   4.  Seller  — "Is this in a flood zone?"          → listing.is_in_flood_zone
 *   5.  Seller  — "What are the HOA fees?"            → listing.hoa_fee
 *   6.  Landlord — "What utilities are included?"     → listing.utilities
 *   7.  Seller  — "Are pets allowed?"                 → listing.pets_allowed
 *   8.  Landlord — "What is the move-in date?"        → listing.available_date
 *   9.  Buyer   — "What financing type is requested?" → listing.financing_type
 *  10.  Tenant  — "What lease length is desired?"     → listing.desired_lease_length
 *
 * Pure PHPUnit — no Laravel container, no DB.
 */
class AskAiListingFieldPipelineE2ETest extends TestCase
{
    // =========================================================================
    // Shared helpers
    // =========================================================================

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
     * Mock internalRunner: listing field IS populated with $value.
     * Guard B will NOT fire; OpenAI will be invoked.
     */
    private function makeRunnerWithListingField(string $field, mixed $value): AskAiInternalRunnerService
    {
        $mock          = $this->createMock(AskAiInternalRunnerService::class);
        $allowedCtx    = ['listing' => [$field => $value]];
        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => $allowedCtx,
            'required_disclosures' => ['Information is sourced directly from the listing data.'],
            'source_attribution'   => ['required_sources' => ['listing']],
            'refusal_template'     => null,
        ];
        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['listing_type' => 'test', $field => $value]],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);
        return $mock;
    }

    /**
     * Mock internalRunner: listing field IS present in allowed_context but with null value.
     * Guard B WILL fire because array_key_exists fires and the value is null.
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

    private function makeAdapterSuccess(): array
    {
        return [
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => 'AI-generated answer based on listing data.',
            'model'        => 'gpt-4o-mini',
            'error'        => null,
        ];
    }

    private function makeAdapterFailure(): array
    {
        return [
            'success'      => false,
            'status'       => 'failed',
            'raw_response' => null,
            'model'        => null,
            'error'        => 'OpenAI unavailable.',
        ];
    }

    private function makeFinalResponse(): array
    {
        return [
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'AI-generated answer based on listing data.',
            'disclosures'        => ['Information is sourced directly from the listing data.'],
            'source_attribution' => ['required_sources' => ['listing']],
            'refusal_message'    => null,
            'error'              => null,
        ];
    }

    // =========================================================================
    // Helper: assert Guard B fires for a given question/role/field/label
    // =========================================================================

    private function assertGuardBFiresWithMessage(
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

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run($role, 1, $question);

        $this->assertFalse($result['success'], 'Guard B must short-circuit pipeline (success=false).');
        $this->assertSame('insufficient_context', $result['status'], 'Guard B status must be insufficient_context.');
        $this->assertSame(
            $expectedLabel . ' has not been provided for this listing.',
            $result['final_response']['answer'],
            "Guard B message must use specific label for listing.{$field}."
        );
        $this->assertSame('listing.' . $field, $result['classification']['normalized_field_key'] ?? null,
            "normalized_field_key must be listing.{$field}.");
    }

    private function assertDirectReturnFallback(
        string $role,
        string $question,
        string $field,
        mixed $fieldValue
    ): void {
        $internalRunner = $this->makeRunnerWithListingField($field, $fieldValue);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterFailure());
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run($role, 1, $question);

        $this->assertTrue($result['success'], 'Direct-return fallback must succeed (success=true).');
        $this->assertSame('ready', $result['status'], 'Direct-return fallback must produce ready status.');
        $this->assertSame(
            (string) $fieldValue,
            $result['final_response']['answer'],
            "Direct-return must surface raw listing.{$field} value."
        );
    }

    // =========================================================================
    // 1. Seller — "What are the taxes?" → listing.annual_property_taxes
    // =========================================================================

    public function test_taxes_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What are the taxes?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('annual_property_taxes', '4200');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'What are the taxes?');

        $this->assertSame(
            'listing.annual_property_taxes',
            $result['classification']['normalized_field_key'] ?? null,
            '"What are the taxes?" must resolve to listing.annual_property_taxes.'
        );
    }

    public function test_taxes_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('annual_property_taxes', '4200');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('seller', 1, 'What are the taxes?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_taxes_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('seller', 'What are the taxes?', 'annual_property_taxes', 'Annual property tax information');
    }

    public function test_taxes_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'What are the taxes?', 'annual_property_taxes', '4200');
    }

    // =========================================================================
    // 2. Landlord — "What appliances are included?" → listing.appliances
    // =========================================================================

    public function test_appliances_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What appliances are included?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('appliances', 'Refrigerator, dishwasher, microwave, washer/dryer');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'What appliances are included?');

        $this->assertSame(
            'listing.appliances',
            $result['classification']['normalized_field_key'] ?? null,
            '"What appliances are included?" must resolve to listing.appliances.'
        );
    }

    public function test_appliances_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('appliances', 'Refrigerator, dishwasher, microwave');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('landlord', 1, 'What appliances are included?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_appliances_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('landlord', 'What appliances are included?', 'appliances', 'Included appliances information');
    }

    public function test_appliances_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('landlord', 'What appliances are included?', 'appliances', 'Refrigerator, dishwasher, microwave, washer/dryer');
    }

    // =========================================================================
    // 3. Seller — "How many bedrooms?" → listing.bedrooms
    // =========================================================================

    public function test_bedrooms_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('How many bedrooms?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('bedrooms', 3);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame(
            'listing.bedrooms',
            $result['classification']['normalized_field_key'] ?? null,
            '"How many bedrooms?" must resolve to listing.bedrooms.'
        );
    }

    public function test_bedrooms_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('bedrooms', 3);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('seller', 1, 'How many bedrooms?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_bedrooms_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('seller', 'How many bedrooms?', 'bedrooms', 'Bedroom information');
    }

    public function test_bedrooms_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'How many bedrooms?', 'bedrooms', 3);
    }

    // =========================================================================
    // 4. Seller — "Is this in a flood zone?" → listing.is_in_flood_zone
    // =========================================================================

    public function test_flood_zone_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('Is this in a flood zone?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('is_in_flood_zone', 'Yes — FEMA Zone AE');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'Is this in a flood zone?');

        $this->assertSame(
            'listing.is_in_flood_zone',
            $result['classification']['normalized_field_key'] ?? null,
            '"Is this in a flood zone?" must resolve to listing.is_in_flood_zone.'
        );
    }

    public function test_flood_zone_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('is_in_flood_zone', 'No — Zone X');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('seller', 1, 'Is this in a flood zone?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_flood_zone_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('seller', 'Is this in a flood zone?', 'is_in_flood_zone', 'Flood zone status information');
    }

    public function test_flood_zone_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'Is this in a flood zone?', 'is_in_flood_zone', 'Yes — FEMA Zone AE');
    }

    // =========================================================================
    // 5. Seller — "What are the HOA fees?" → listing.hoa_fee
    // =========================================================================

    public function test_hoa_fees_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What are the HOA fees?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('hoa_fee', '350/month');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'What are the HOA fees?');

        $this->assertSame(
            'listing.hoa_fee',
            $result['classification']['normalized_field_key'] ?? null,
            '"What are the HOA fees?" must resolve to listing.hoa_fee.'
        );
    }

    public function test_hoa_fees_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('hoa_fee', '350/month');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('seller', 1, 'What are the HOA fees?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_hoa_fees_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('seller', 'What are the HOA fees?', 'hoa_fee', 'HOA fee information');
    }

    public function test_hoa_fees_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'What are the HOA fees?', 'hoa_fee', '$350/month');
    }

    // =========================================================================
    // 6. Landlord — "What utilities are included?" → listing.utilities
    // =========================================================================

    public function test_utilities_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What utilities are included?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('utilities', 'Water, trash, sewer');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'What utilities are included?');

        $this->assertSame(
            'listing.utilities',
            $result['classification']['normalized_field_key'] ?? null,
            '"What utilities are included?" must resolve to listing.utilities.'
        );
    }

    public function test_utilities_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('utilities', 'Water, trash, sewer');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('landlord', 1, 'What utilities are included?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_utilities_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('landlord', 'What utilities are included?', 'utilities', 'Included utilities information');
    }

    public function test_utilities_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('landlord', 'What utilities are included?', 'utilities', 'Water, trash, sewer');
    }

    // =========================================================================
    // 7. Seller — "Are pets allowed?" → listing.pets_allowed
    // =========================================================================

    public function test_pets_allowed_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('Are pets allowed?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('pets_allowed', 'Yes — dogs and cats up to 50 lbs');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'Are pets allowed?');

        $this->assertSame(
            'listing.pets_allowed',
            $result['classification']['normalized_field_key'] ?? null,
            '"Are pets allowed?" must resolve to listing.pets_allowed.'
        );
    }

    public function test_pets_allowed_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('pets_allowed', 'Yes');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('seller', 1, 'Are pets allowed?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_pets_allowed_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('seller', 'Are pets allowed?', 'pets_allowed', 'Pet policy information');
    }

    public function test_pets_allowed_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'Are pets allowed?', 'pets_allowed', 'Yes — dogs and cats up to 50 lbs');
    }

    // =========================================================================
    // 8. Landlord — "What is the move-in date?" → listing.available_date
    // =========================================================================

    public function test_move_in_date_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What is the move-in date?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('available_date', '2026-08-01');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'What is the move-in date?');

        $this->assertSame(
            'listing.available_date',
            $result['classification']['normalized_field_key'] ?? null,
            '"What is the move-in date?" must resolve to listing.available_date.'
        );
    }

    public function test_move_in_date_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('available_date', '2026-08-01');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('landlord', 1, 'What is the move-in date?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_move_in_date_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('landlord', 'What is the move-in date?', 'available_date', 'Available date information');
    }

    public function test_move_in_date_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('landlord', 'What is the move-in date?', 'available_date', '2026-08-01');
    }

    // =========================================================================
    // 9. Buyer — "What financing type is requested?" → listing.financing_type
    // =========================================================================

    public function test_financing_type_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What financing type is requested?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('financing_type', 'Conventional — 20% down');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('buyer', 1, 'What financing type is requested?');

        $this->assertSame(
            'listing.financing_type',
            $result['classification']['normalized_field_key'] ?? null,
            '"What financing type is requested?" must resolve to listing.financing_type.'
        );
    }

    public function test_financing_type_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('financing_type', 'FHA');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('buyer', 1, 'What financing type is requested?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_financing_type_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('buyer', 'What financing type is requested?', 'financing_type', 'Financing type information');
    }

    public function test_financing_type_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('buyer', 'What financing type is requested?', 'financing_type', 'Conventional — 20% down');
    }

    // =========================================================================
    // 10. Tenant — "What lease length is desired?" → listing.desired_lease_length
    // =========================================================================

    public function test_desired_lease_length_question_classifies_and_detects_correct_listing_key(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What lease length is desired?');
        $this->assertSame('listing_facts', $result['question_type']);

        $internalRunner = $this->makeRunnerWithListingField('desired_lease_length', '12 months');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('tenant', 1, 'What lease length is desired?');

        $this->assertSame(
            'listing.desired_lease_length',
            $result['classification']['normalized_field_key'] ?? null,
            '"What lease length is desired?" must resolve to listing.desired_lease_length.'
        );
    }

    public function test_desired_lease_length_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('desired_lease_length', '12 months');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $result = $this->makeRunner($internalRunner, $adapter, $finalBuilder)->run('tenant', 1, 'What lease length is desired?');
        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_desired_lease_length_field_null_guard_b_returns_field_specific_message(): void
    {
        $this->assertGuardBFiresWithMessage('tenant', 'What lease length is desired?', 'desired_lease_length', 'Tenant desired lease length information');
    }

    public function test_desired_lease_length_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('tenant', 'What lease length is desired?', 'desired_lease_length', '12 months');
    }

    // =========================================================================
    // Cross-cutting: OpenAI-disabled direct-return covers all 10 key families
    // =========================================================================

    /**
     * @dataProvider openaiDisabledKeyFamilyProvider
     */
    public function test_all_10_reviewer_fields_have_working_direct_return_fallback(
        string $role,
        string $question,
        string $field,
        string $value
    ): void {
        $this->assertDirectReturnFallback($role, $question, $field, $value);
    }

    public static function openaiDisabledKeyFamilyProvider(): array
    {
        return [
            'taxes'               => ['seller',   'What are the taxes?',               'annual_property_taxes', '4200'],
            'appliances'          => ['landlord',  'What appliances are included?',     'appliances',            'Refrigerator, dishwasher'],
            'bedrooms'            => ['seller',    'How many bedrooms?',                'bedrooms',              '3'],
            'flood zone'          => ['seller',    'Is this in a flood zone?',          'is_in_flood_zone',      'Zone X'],
            'hoa fees'            => ['seller',    'What are the HOA fees?',            'hoa_fee',               '$350/month'],
            'utilities'           => ['landlord',  'What utilities are included?',      'utilities',             'Water, trash'],
            'pets allowed'        => ['seller',    'Are pets allowed?',                 'pets_allowed',          'Yes'],
            'move-in date'        => ['landlord',  'What is the move-in date?',         'available_date',        '2026-08-01'],
            'financing type'      => ['buyer',     'What financing type is requested?', 'financing_type',        'Conventional'],
            'desired lease length'=> ['tenant',    'What lease length is desired?',     'desired_lease_length',  '12 months'],
        ];
    }

    // =========================================================================
    // Cross-cutting: all 10 questions classify as listing_facts
    // =========================================================================

    /**
     * @dataProvider reviewerQuestionClassificationProvider
     */
    public function test_all_10_reviewer_questions_classify_as_listing_facts(string $question): void
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Question '{$question}' must classify as listing_facts."
        );
    }

    public static function reviewerQuestionClassificationProvider(): array
    {
        return [
            'taxes'                => ['What are the taxes?'],
            'appliances'           => ['What appliances are included?'],
            'bedrooms'             => ['How many bedrooms?'],
            'flood zone'           => ['Is this in a flood zone?'],
            'hoa fees'             => ['What are the HOA fees?'],
            'utilities'            => ['What utilities are included?'],
            'pets allowed'         => ['Are pets allowed?'],
            'move-in date'         => ['What is the move-in date?'],
            'financing type'       => ['What financing type is requested?'],
            'desired lease length' => ['What lease length is desired?'],
        ];
    }

    // =========================================================================
    // Cross-cutting: all 10 Guard B messages are field-specific (never generic)
    // =========================================================================

    /**
     * @dataProvider guardBMessageProvider
     */
    public function test_all_10_reviewer_fields_produce_specific_guard_b_messages(
        string $role,
        string $question,
        string $field,
        string $expectedLabel
    ): void {
        $this->assertGuardBFiresWithMessage($role, $question, $field, $expectedLabel);
    }

    public static function guardBMessageProvider(): array
    {
        return [
            'taxes'               => ['seller',  'What are the taxes?',               'annual_property_taxes', 'Annual property tax information'],
            'appliances'          => ['landlord', 'What appliances are included?',     'appliances',            'Included appliances information'],
            'bedrooms'            => ['seller',   'How many bedrooms?',                'bedrooms',              'Bedroom information'],
            'flood zone'          => ['seller',   'Is this in a flood zone?',          'is_in_flood_zone',      'Flood zone status information'],
            'hoa fees'            => ['seller',   'What are the HOA fees?',            'hoa_fee',               'HOA fee information'],
            'utilities'           => ['landlord', 'What utilities are included?',      'utilities',             'Included utilities information'],
            'pets allowed'        => ['seller',   'Are pets allowed?',                 'pets_allowed',          'Pet policy information'],
            'move-in date'        => ['landlord', 'What is the move-in date?',         'available_date',        'Available date information'],
            'financing type'      => ['buyer',    'What financing type is requested?', 'financing_type',        'Financing type information'],
            'desired lease length'=> ['tenant',   'What lease length is desired?',     'desired_lease_length',  'Tenant desired lease length information'],
        ];
    }

    // =========================================================================
    // Q13: "What are the seller financing terms?" — now classifies listing_facts
    // =========================================================================

    public function test_seller_financing_terms_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What are the seller financing terms?');
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            '"What are the seller financing terms?" must now classify as listing_facts (not unsupported).'
        );
    }

    public function test_seller_will_finance_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('Will the seller finance?');
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            '"Will the seller finance?" must classify as listing_facts.'
        );
    }

    public function test_owner_financing_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('Is owner financing available?');
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            '"Is owner financing available?" must classify as listing_facts.'
        );
    }

    // =========================================================================
    // Q14: "What are the lease option terms?" — now classifies listing_facts
    // =========================================================================

    public function test_lease_option_terms_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('What are the lease option terms?');
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            '"What are the lease option terms?" must classify as listing_facts (not unsupported).'
        );
    }

    public function test_lease_option_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('Is there a lease option available?');
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            '"Is there a lease option available?" must classify as listing_facts.'
        );
    }

    // =========================================================================
    // Q11: "What is the garage situation?" — now maps to listing.garage
    // =========================================================================

    public function test_garage_situation_resolves_to_listing_garage(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('garage', '2-car attached');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'What is the garage situation?');

        $this->assertSame(
            'listing.garage',
            $result['classification']['normalized_field_key'] ?? null,
            '"What is the garage situation?" must resolve to listing.garage.'
        );
    }

    public function test_garage_situation_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'What is the garage situation?', 'garage', '2-car attached');
    }

    // =========================================================================
    // Q12: "Is this property in a flood zone?" — now maps to listing.is_in_flood_zone
    // =========================================================================

    public function test_is_property_in_flood_zone_resolves_to_listing_field(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('is_in_flood_zone', 'Zone X');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'Is this property in a flood zone?');

        $this->assertSame(
            'listing.is_in_flood_zone',
            $result['classification']['normalized_field_key'] ?? null,
            '"Is this property in a flood zone?" must resolve to listing.is_in_flood_zone.'
        );
    }

    public function test_is_property_in_flood_zone_openai_disabled_direct_return_fallback(): void
    {
        $this->assertDirectReturnFallback('seller', 'Is this property in a flood zone?', 'is_in_flood_zone', 'Zone X');
    }

    // =========================================================================
    // Q6: "Is there an HOA?" — short phrase now maps to listing.hoa_association
    // =========================================================================

    public function test_is_there_an_hoa_resolves_to_listing_hoa_association(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('hoa_association', 'Sunset Ridge HOA');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);
        $adapter->method('generate')->willReturn($this->makeAdapterSuccess());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'Is there an HOA?');

        $this->assertSame(
            'listing.hoa_association',
            $result['classification']['normalized_field_key'] ?? null,
            '"Is there an HOA?" must resolve to listing.hoa_association.'
        );
    }

    // =========================================================================
    // Final listing_facts adapter-failed fallback
    //
    // When a listing_facts question has no specific field key detected AND the
    // adapter fails, the pipeline must return status='insufficient_context' with
    // a clean user-friendly message — never the generic error banner.
    // =========================================================================

    /**
     * Mock an internalRunner that returns a full (non-narrowed) listing_facts
     * prompt package — simulating a question whose field key could not be pinned
     * to a single listing.* or faq_answers.* entry.
     */
    private function makeRunnerWithFullListingContext(): AskAiInternalRunnerService
    {
        $mock = $this->createMock(AskAiInternalRunnerService::class);
        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => ['listing' => ['asking_price' => '500000', 'bedrooms' => '3']],
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];
        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['listing_type' => 'seller', 'asking_price' => '500000']],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);
        return $mock;
    }

    public function test_listing_facts_adapter_failed_final_fallback_returns_insufficient_context(): void
    {
        $internalRunner = $this->makeRunnerWithFullListingContext();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterFailure());
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'What are the seller financing terms?');

        $this->assertFalse(
            $result['success'],
            'Universal prompt-ready fallback must set success=false.'
        );
        $this->assertSame(
            'insufficient_context',
            $result['status'],
            'Universal prompt-ready fallback must return insufficient_context, not failed.'
        );
        $this->assertSame(
            'A response could not be generated right now. Please try again shortly.',
            $result['final_response']['answer'] ?? null,
            'Universal prompt-ready fallback answer must be the clean try-again message.'
        );
        $this->assertNull(
            $result['error'],
            'Universal prompt-ready fallback must set error=null (not the adapter error string).'
        );
    }

    /**
     * Q3: A property_standout question (e.g. "What are the highlights of this property?")
     * must NOT return the generic error banner on adapter failure.
     * The universal prompt-ready adapter-failed fallback must cover property_standout
     * (which does NOT classify as listing_facts) — otherwise it would fall through to
     * finalResponseBuilder and return status='failed'.
     *
     * "highlight" is a keyword in the property_standout classifier block.
     */
    public function test_property_standout_adapter_failed_returns_insufficient_context_not_failed(): void
    {
        $internalRunner = $this->makeRunnerWithFullListingContext();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterFailure());
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'What are the highlights of this property?');

        $classifiedType = $result['classification']['question_type'] ?? null;
        $this->assertSame(
            'property_standout',
            $classifiedType,
            'Sanity: question must classify as property_standout (not listing_facts) to prove the fallback is type-agnostic.'
        );

        $this->assertFalse(
            $result['success'],
            'Q3 adapter failure: success must be false.'
        );
        $this->assertSame(
            'insufficient_context',
            $result['status'],
            'Q3 adapter failure: status must be insufficient_context, never failed.'
        );
        $this->assertSame(
            'A response could not be generated right now. Please try again shortly.',
            $result['final_response']['answer'] ?? null,
            'Q3 adapter failure: answer must be the clean try-again message.'
        );
    }

    public function test_universal_fallback_does_not_fire_when_listing_direct_return_handled_it(): void
    {
        $internalRunner = $this->makeRunnerWithListingField('asking_price', '500000');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterFailure());
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'What is the asking price?');

        $this->assertTrue(
            $result['success'],
            'listing.* direct-return fallback must succeed (not be swallowed by universal fallback).'
        );
        $this->assertSame(
            'ready',
            $result['status'],
            'listing.* direct-return fallback must return ready status.'
        );
        $this->assertSame(
            '500000',
            $result['final_response']['answer'] ?? null,
            'listing.* direct-return must surface the raw field value, not the try-again message.'
        );
    }
}
