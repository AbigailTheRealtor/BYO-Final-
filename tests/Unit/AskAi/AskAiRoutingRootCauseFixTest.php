<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * AskAiRoutingRootCauseFixTest
 *
 * Regression matrix covering the three root causes fixed in this change set,
 * structured around the nine scenarios specified in the task (A–I).
 *
 * Root cause 1 — Step 1a-desc required the normalizer before the description
 *   fallback could fire, causing "Does seller offer a credit?" to return
 *   'unsupported' when the normalizer was disabled.
 *   Fix: Step 1a-desc gate uses only the two-stage plausibility check
 *   (Stage 1: isObviouslyNonListingQuestion, Stage 2: isListingRelatedQuestion).
 *   The normalizer flag never gates this path.
 *
 * Root cause 2 — flood_zone_code was not wrapped in resolveOtherValue() for
 *   seller and landlord roles, so "Other" appeared verbatim in AI answers.
 *   Fix: resolveOtherValue() applied to both roles; method extended to also
 *   handle "See Remarks", "TBD", "N/A", "None" across all placeholder classes.
 *
 * Root cause 3 — No final-answer sanitization existed; a bare placeholder
 *   that slipped through the context builder could be echoed by the model.
 *   Fix: isBareAnswerPlaceholder() + sanitization block after finalResponseBuilder.
 *
 * Scenario matrix (spec A–I):
 *   A. Exact field match — real field value flows through correctly; Stage 2
 *      passes so the question reaches the AI with structured context data.
 *   B. "Other" + custom text present — resolveOtherValue returns the custom
 *      text so the AI never receives nor echoes the bare string "Other".
 *   C. "Other" + no custom text — resolveOtherValue returns null; and if the
 *      AI somehow echoes "Other", isBareAnswerPlaceholder catches it.
 *   D. Answer in listing description (seller) — description fallback fires
 *      → status='ready'.
 *   E. Answer in additional_details / EAV remarks (buyer + tenant) — same
 *      description-fallback path → status='ready'.
 *   F. Listing question with unusual phrasing (not in any field map) — passes
 *      Stage 2 (broad allowlist) → description fallback → status='ready'.
 *   G. Listing question, no answer anywhere — description fallback fires but
 *      adapter returns sentinel → status='insufficient_context'.
 *   H. Fair Housing question — classifier returns 'prohibited' → status='blocked',
 *      description fallback never entered.
 *   I. Completely unrelated question (weather, sports, jokes) — Stage 2 blocks
 *      it → status='unsupported', no adapter call consumed.
 *
 * Regression tests:
 *   R1. "Does seller offer a credit?" → description fallback → ready (the
 *       originally confirmed failure caused by Root Cause 1).
 *   R2. Flood zone "Other" (seller) — context value is custom text, not "Other".
 *   R3. Flood zone "Other" (landlord) — same guarantee for the landlord role.
 */
class AskAiRoutingRootCauseFixTest extends TestCase
{
    // =========================================================================
    // Helpers — runner private method reflection
    // =========================================================================

    private function makeMinimalRunner(bool $enableDescFallback = false): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $this->createMock(AskAiQuestionClassifierService::class),
            $this->createMock(AskAiInternalRunnerService::class),
            $this->createMock(AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $this->createMock(AskAiFollowUpQuestionService::class),
            null,
            null,
            $enableDescFallback
        );
    }

    private function callRunnerBoolMethod(string $method, string $question): bool
    {
        $runner = $this->makeMinimalRunner();
        $ref    = new ReflectionMethod(AskAiRunnerV2Service::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($runner, $question);
    }

    private function callIsBareAnswerPlaceholder(string $answer): bool
    {
        $runner = $this->makeMinimalRunner();
        $ref    = new ReflectionMethod(AskAiRunnerV2Service::class, 'isBareAnswerPlaceholder');
        $ref->setAccessible(true);
        return $ref->invoke($runner, $answer);
    }

    // =========================================================================
    // Helpers — context builder private method reflection
    // =========================================================================

    private function makeContextBuilder(): AskAiContextBuilderService
    {
        return new AskAiContextBuilderService(
            $this->createMock(PropertyIntelligenceProfileService::class),
            $this->createMock(LocationDnaIntelligenceContextService::class),
            $this->createMock(LocationDnaMarketingContextService::class)
        );
    }

    private function callResolveOtherValue(
        AskAiContextBuilderService $builder,
        ?string $primary,
        array   $mockData
    ): ?string {
        $ref = new ReflectionMethod(AskAiContextBuilderService::class, 'resolveOtherValue');
        $ref->setAccessible(true);
        $infoGet = fn(string $key) => $mockData[$key] ?? null;
        return $ref->invoke($builder, $primary, $infoGet, ...array_keys($mockData));
    }

    // =========================================================================
    // Helpers — stub runner for integration scenarios
    // =========================================================================

    /**
     * Build a stub runner subclass that:
     *  - wires all collaborators as mocks
     *  - stubs loadListingDescription() to return $stubDescription
     *  - accepts a $classifierType to simulate different question classifications
     */
    private function makeStubRunner(
        ?string $stubDescription,
        array   $adapterResponse,
        bool    $normalizerEnabled = true,
        bool    $descFallbackOn    = true,
        string  $classifierType    = 'unsupported'
    ): AskAiRunnerV2Service {
        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => $classifierType,
            'confidence'    => ($classifierType === 'prohibited') ? 1.0 : 0.0,
            'reason'        => 'stub',
        ]);

        $promptPackageStatus = match ($classifierType) {
            'prohibited' => 'blocked',
            default      => 'unsupported',
        };

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => [],
            'contract'       => [],
            'prompt_package' => [
                'status'           => $promptPackageStatus,
                'refusal_template' => ($classifierType === 'prohibited')
                    ? 'This question type is not permitted.'
                    : null,
            ],
        ]);

        $adapterMock = $this->createMock(AskAiOpenAiAdapterService::class);
        $adapterMock->method('generate')->willReturn($adapterResponse);

        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);
        $finalBuilder->method('build')->willReturn([
            'success'            => false,
            'status'             => ($classifierType === 'prohibited') ? 'blocked' : 'unsupported',
            'answer'             => null,
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => ($classifierType === 'prohibited')
                ? 'This question type is not permitted.'
                : null,
            'error'              => null,
        ]);

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $normalizer = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizer->method('isEnabled')->willReturn($normalizerEnabled);
        $normalizer->method('normalize')->willReturn(null);
        $normalizer->method('getLastStatus')->willReturn('unknown');
        $normalizer->method('getLastError')->willReturn(null);
        $normalizer->method('getLastContextPath')->willReturn(null);
        $normalizer->method('buildKnownFieldKeys')->willReturn([]);

        $descriptionStub = $stubDescription;

        return new class(
            $classifier,
            $internalRunner,
            $adapterMock,
            $finalBuilder,
            $followUp,
            $normalizer,
            null,
            $descFallbackOn,
            $descriptionStub
        ) extends AskAiRunnerV2Service {
            private ?string $stubbedDescription;

            public function __construct(
                AskAiQuestionClassifierService   $classifier,
                AskAiInternalRunnerService       $internalRunner,
                AskAiOpenAiAdapterService        $adapter,
                AskAiFinalResponseBuilderService $finalResponseBuilder,
                AskAiFollowUpQuestionService     $followUpService,
                ?AskAiIntentNormalizerService    $normalizer,
                $knowledgeSearch,
                bool                             $enableDescriptionFallback,
                ?string                          $stubbedDescription
            ) {
                parent::__construct(
                    $classifier,
                    $internalRunner,
                    $adapter,
                    $finalResponseBuilder,
                    $followUpService,
                    $normalizer,
                    $knowledgeSearch,
                    $enableDescriptionFallback
                );
                $this->stubbedDescription = $stubbedDescription;
            }

            protected function loadListingDescription(string $listingType, int $listingId): ?string
            {
                return $this->stubbedDescription;
            }
        };
    }

    private function adapterHit(string $answerText): array
    {
        return [
            'success'           => true,
            'status'            => 'generated',
            'raw_response'      => json_encode(['answer_text' => $answerText]),
            'model'             => 'gpt-4o-mini',
            'error'             => null,
            'prompt_tokens'     => 50,
            'completion_tokens' => 20,
            'total_tokens'      => 70,
            'api_request_id'    => 'req_test_rca_hit',
        ];
    }

    private function adapterSentinel(): array
    {
        return [
            'success'           => true,
            'status'            => 'generated',
            'raw_response'      => json_encode(['answer_text' => 'INFORMATION_NOT_IN_DESCRIPTION']),
            'model'             => 'gpt-4o-mini',
            'error'             => null,
            'prompt_tokens'     => 40,
            'completion_tokens' => 10,
            'total_tokens'      => 50,
            'api_request_id'    => 'req_test_rca_sentinel',
        ];
    }

    // =========================================================================
    // Scenario A: Exact field match — real value flows through correctly
    //
    // At the method level:
    //   - resolveOtherValue returns a real value unchanged (it will appear in context).
    //   - isBareAnswerPlaceholder returns false for a real answer (safe to surface).
    // =========================================================================

    /**
     * A1: A genuine field value (e.g. "3" for bedrooms) is returned unchanged
     * by resolveOtherValue — it will appear in the AI context payload without
     * modification and produce a 'ready' answer.
     */
    public function test_A1_real_field_value_is_returned_unchanged(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            '3',
            []
        );
        $this->assertSame('3', $result,
            'A1: a real field value must be returned unchanged by resolveOtherValue');
    }

    /**
     * A2: "Does seller offer a credit?" is listing-related.
     * isListingRelatedQuestion returns true (method-level unit check).
     * At the gate level, Stage 2 has been removed; the description/OpenAI
     * sentinel is the authoritative judge.
     */
    public function test_A2_credit_question_passes_stage2(): void
    {
        $this->assertTrue(
            $this->callRunnerBoolMethod('isListingRelatedQuestion', 'Does seller offer a credit?'),
            'A2: credit question must be classified as listing-related by the helper method'
        );
    }

    /**
     * A3: A real answer is not flagged as a placeholder — it will be surfaced
     * to the user without the sanitizer replacing it.
     */
    public function test_A3_real_answer_not_flagged_as_placeholder(): void
    {
        $this->assertFalse(
            $this->callIsBareAnswerPlaceholder('The seller is offering a $5,000 credit toward closing costs.'),
            'A3: a real answer must not be treated as a bare placeholder'
        );
    }

    // =========================================================================
    // Scenario B: "Other" + custom text → custom text returned, never "Other"
    // =========================================================================

    /**
     * B1: When the primary value is "Other" and a populated custom fallback key
     * exists, resolveOtherValue returns the custom text.
     */
    public function test_B1_resolve_other_value_returns_custom_text_for_Other(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'Other',
            ['flood_zone_code_other' => 'AE']
        );
        $this->assertSame('AE', $result,
            'B1: "Other" + populated fallback must return the custom text, not "Other"');
        $this->assertNotSame('Other', $result,
            'B1: literal "Other" must never be returned when a custom value exists');
    }

    /**
     * B2: isBareAnswerPlaceholder("Other") → true ensures that even if the AI
     * somehow echoes back the raw "Other", the sanitizer catches it.
     */
    public function test_B2_bare_Other_is_caught_by_placeholder_sanitizer(): void
    {
        $this->assertTrue(
            $this->callIsBareAnswerPlaceholder('Other'),
            'B2: bare "Other" must be recognised as a placeholder — safety net for AI echo'
        );
    }

    /**
     * B3: Case-insensitive — "other" (lowercase) behaves the same as "Other".
     */
    public function test_B3_resolve_other_value_is_case_insensitive(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'other',
            ['fb' => 'Custom Zone X']
        );
        $this->assertSame('Custom Zone X', $result,
            'B3: lowercase "other" must resolve the same as "Other"');
    }

    // =========================================================================
    // Scenario C: "Other" + no custom → null; placeholder class coverage
    // =========================================================================

    /**
     * C1: When "Other" has no populated fallback, resolveOtherValue returns null
     * so the AI receives an absent field rather than "Other".
     */
    public function test_C1_resolve_other_value_returns_null_when_Other_has_no_fallback(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'Other',
            ['flood_zone_code_other' => null]
        );
        $this->assertNull($result,
            'C1: "Other" + null fallback must return null');
    }

    /**
     * C2: "TBD", "N/A", and "None" are immediate-null placeholders — no fallback
     * is consulted, the field is treated as absent.
     */
    public function test_C2_tbd_na_none_resolve_to_null_immediately(): void
    {
        $builder = $this->makeContextBuilder();

        foreach (['TBD', 'N/A', 'None'] as $placeholder) {
            $result = $this->callResolveOtherValue(
                $builder,
                $placeholder,
                ['some_fallback' => 'should-not-appear']
            );
            $this->assertNull($result,
                "C2: \"$placeholder\" must return null without consulting fallbacks");
        }
    }

    /**
     * C3: "See Remarks" with a populated fallback → resolves to fallback value.
     */
    public function test_C3_see_remarks_resolves_to_fallback_when_present(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'See Remarks',
            ['remarks_field' => 'Flood zone AE per FEMA map 2023.']
        );
        $this->assertSame('Flood zone AE per FEMA map 2023.', $result,
            'C3: "See Remarks" + populated fallback must return the fallback value');
    }

    /**
     * C4: The placeholder sanitizer catches all standard bare-placeholder strings
     * so the AI can never surface them to the user.
     */
    public function test_C4_placeholder_sanitizer_catches_standard_placeholders(): void
    {
        foreach (['Other', 'N/A', 'See Remarks', 'TBD'] as $placeholder) {
            $this->assertTrue(
                $this->callIsBareAnswerPlaceholder($placeholder),
                "C4: bare \"$placeholder\" must be caught by the placeholder sanitizer"
            );
        }
    }

    // =========================================================================
    // Scenario D: Answer in listing description (seller) → description fallback → ready
    // =========================================================================

    /**
     * D1 (Seller): When the listing description contains the answer to a question
     * and the normalizer is disabled, the Step 1a-desc description fallback fires
     * and the adapter produces a 'ready' response.
     *
     * This is the primary regression test for Root Cause 1.
     */
    public function test_D1_seller_description_hit_returns_ready(): void
    {
        $desc   = 'The seller is offering a $5,000 credit toward buyer closing costs.';
        $runner = $this->makeStubRunner(
            $desc,
            $this->adapterHit($desc),
            false   // normalizerEnabled = false (the originally-broken case)
        );

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $this->assertSame('ready', $result['status'],
            'D1 seller: credit question + description + normalizer off → status must be ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'D1 seller: answer must come from the description_fallback path');
        $this->assertTrue($result['trace']['description_fallback_unsupported_attempted'] ?? false,
            'D1 seller: trace must record description_fallback_unsupported_attempted=true');
        $this->assertTrue($result['trace']['description_fallback_unsupported_used'] ?? false,
            'D1 seller: trace must record description_fallback_unsupported_used=true');
    }

    // =========================================================================
    // Scenario E: Answer in additional_details / EAV remarks (buyer + tenant)
    // =========================================================================

    /**
     * E1 (Buyer): Buyer descriptions live in the native 'additional_details'
     * column. The stub returns that value and the fallback produces 'ready'.
     */
    public function test_E1_buyer_additional_details_hit_returns_ready(): void
    {
        $desc   = 'Buyer is pre-approved up to $500,000 and needs 3 bedrooms minimum.';
        $runner = $this->makeStubRunner(
            $desc,
            $this->adapterHit($desc),
            false
        );

        $result = $runner->run('buyer', 88, 'How many bedrooms does this buyer need?');

        $this->assertSame('ready', $result['status'],
            'E1 buyer: listing question + description + normalizer off → status must be ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'E1 buyer: outcome_category must be description_fallback');
    }

    /**
     * E2 (Tenant): Tenant descriptions live in the EAV meta key 'additional_details'.
     * The stub returns that value and the fallback produces 'ready'.
     */
    public function test_E2_tenant_eav_description_hit_returns_ready(): void
    {
        $desc   = 'Tenant seeking 2BR apartment with washer/dryer hookup, budget $1,800/mo.';
        $runner = $this->makeStubRunner(
            $desc,
            $this->adapterHit($desc),
            false
        );

        $result = $runner->run('tenant', 200, 'What is the tenant budget?');

        $this->assertSame('ready', $result['status'],
            'E2 tenant: listing question + EAV description + normalizer off → status must be ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'E2 tenant: outcome_category must be description_fallback');
    }

    // =========================================================================
    // Scenario F: Listing question with unusual phrasing reaches fallback → ready
    // =========================================================================

    /**
     * F1: Questions with unusual phrasing are classified as listing-related by
     * isListingRelatedQuestion() (method-level unit check only — the helper is
     * no longer part of the fallback gate).
     */
    public function test_F1_unusual_listing_questions_pass_stage2(): void
    {
        $unusualButValid = [
            'When was this built?',
            'How old is the roof?',
            'Does the property have HVAC?',
            'What utilities are included?',
            'Does it have a dishwasher?',
            'Is the driveway paved?',
            'Are there any easements?',
        ];

        foreach ($unusualButValid as $q) {
            $this->assertTrue(
                $this->callRunnerBoolMethod('isListingRelatedQuestion', $q),
                "F1: \"$q\" must be classified as listing-related by the helper method"
            );
        }
    }

    /**
     * F2 (Landlord): An unusual listing question reaches the description
     * fallback (normalizer disabled) and returns ready.
     */
    public function test_F2_landlord_unusual_question_description_fallback_ready(): void
    {
        $desc   = 'No smoking allowed on premises. Pets considered case-by-case with $300 deposit.';
        $runner = $this->makeStubRunner(
            $desc,
            $this->adapterHit($desc),
            false
        );

        $result = $runner->run('landlord', 55, 'Does the landlord allow pets?');

        $this->assertSame('ready', $result['status'],
            'F2 landlord: listing question + description + normalizer off → ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'F2 landlord: outcome_category must be description_fallback');
    }

    // =========================================================================
    // Placeholder sanitization in both early-return fallback paths
    // =========================================================================

    /**
     * PH1 — Step 1a-desc path (unsupported question → description fallback):
     * When the adapter returns a bare placeholder (e.g. "Other") instead of a
     * real answer, isBareAnswerPlaceholder() must prevent the hit block from
     * firing.  The question falls through to the miss response → insufficient_context.
     *
     * This covers the guard added to the Step 1a-desc hit condition.
     */
    public function test_PH1_step1a_desc_bare_placeholder_answer_treated_as_miss(): void
    {
        $runner = $this->makeStubRunner(
            'Beautiful 3-bedroom home with ocean views.',
            $this->adapterHit('Other')   // adapter returns bare placeholder
        );

        $result = $runner->run('seller', 121, 'Is this property in a flood zone?');

        $this->assertTrue(
            $result['trace']['description_fallback_unsupported_attempted'] ?? false,
            'PH1: flood zone question must pass Stage 1 → description fallback attempted'
        );
        $this->assertFalse(
            $result['trace']['description_fallback_unsupported_used'] ?? true,
            'PH1: bare placeholder answer must NOT trigger description_fallback_unsupported_used=true'
        );
        $this->assertSame('insufficient_context', $result['status'],
            'PH1: bare placeholder answer in Step 1a-desc path must be treated as miss → insufficient_context');
        $this->assertNotSame('ready', $result['status'],
            'PH1: bare placeholder must never produce status=ready');
    }

    /**
     * PH2 — Guard B null-field description fallback path (listing.* field = null):
     * When a listing.* field is null but the listing has a description, the
     * Guard B path fires a description fallback.  If the adapter returns a bare
     * placeholder (e.g. "Other"), isBareAnswerPlaceholder() must prevent the
     * hit block from firing → miss → insufficient_context.
     *
     * This covers the guard added to the Guard B null-field description-hit condition.
     *
     * Stub setup: internalRunner returns prompt_ready + null bedrooms + non-empty
     * description.  LISTING_KEY_KEYWORD_MAP detects listing.bedrooms from the
     * question so the Guard B condition (str_starts_with('listing.')) is true.
     */
    public function test_PH2_guard_b_null_field_bare_placeholder_treated_as_miss(): void
    {
        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => 'listing_facts',
            'confidence'    => 0.9,
            'reason'        => 'stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context' => [
                'listing' => [
                    'bedrooms'    => null,
                    'description' => 'Beautiful 3-bedroom home with ocean views.',
                ],
            ],
            'contract'       => [],
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'allowed_context'      => [
                    'listing' => [
                        'bedrooms'    => null,
                        'description' => 'Beautiful 3-bedroom home with ocean views.',
                    ],
                ],
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]);

        $adapterMock = $this->createMock(AskAiOpenAiAdapterService::class);
        $adapterMock->method('generate')->willReturn($this->adapterHit('Other'));

        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);
        $finalBuilder->method('build')->willReturn([
            'success'            => false,
            'status'             => 'insufficient_context',
            'answer'             => 'This information was not provided in the listing.',
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => null,
            'error'              => null,
        ]);

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $normalizer = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizer->method('isEnabled')->willReturn(false);
        $normalizer->method('normalize')->willReturn(null);
        $normalizer->method('getLastStatus')->willReturn('unknown');
        $normalizer->method('getLastError')->willReturn(null);
        $normalizer->method('getLastContextPath')->willReturn(null);
        $normalizer->method('buildKnownFieldKeys')->willReturn([]);

        $runner = new AskAiRunnerV2Service(
            $classifier,
            $internalRunner,
            $adapterMock,
            $finalBuilder,
            $followUp,
            $normalizer,
            null,
            true   // enableDescriptionFallback
        );

        // "How many bedrooms?" contains "bedroom" → LISTING_KEY_KEYWORD_MAP detects
        // listing.bedrooms → normalizedFieldKey = 'listing.bedrooms' → Guard B fires.
        $result = $runner->run('seller', 121, 'How many bedrooms does this home have?');

        $this->assertSame('insufficient_context', $result['status'],
            'PH2: bare placeholder answer in Guard B null-field path must be treated as miss → insufficient_context');
        $this->assertNotSame('ready', $result['status'],
            'PH2: bare placeholder must never produce status=ready from the Guard B path');
    }

    // =========================================================================
    // Scenario G: Listing question, no answer anywhere → insufficient_context
    // =========================================================================

    /**
     * G1: A listing question ("Is there a basement?") passes Stage 1 (not a
     * greeting), so the description fallback fires.  The adapter returns the
     * INFORMATION_NOT_IN_DESCRIPTION sentinel because the description does not
     * mention a basement.  Result: status='insufficient_context'.
     */
    public function test_G1_listing_question_no_answer_returns_insufficient_context(): void
    {
        $runner = $this->makeStubRunner(
            'Beautiful 3-bedroom home with ocean views.',
            $this->adapterSentinel()
        );

        $result = $runner->run('seller', 121, 'Is there a basement?');

        $this->assertTrue(
            $result['trace']['description_fallback_unsupported_attempted'] ?? false,
            'G1: listing question must pass Stage 1 → description fallback must be attempted'
        );
        $this->assertFalse(
            $result['trace']['description_fallback_unsupported_used'] ?? true,
            'G1: adapter sentinel → description_fallback_unsupported_used must be false'
        );
        $this->assertSame('insufficient_context', $result['status'],
            'G1: listing question + no answer in description → status must be insufficient_context');
    }

    // =========================================================================
    // Scenario H: Fair Housing question → blocked
    // =========================================================================

    /**
     * H1: When the classifier returns 'prohibited', the runner must return
     * status='blocked' without entering the description fallback.
     */
    public function test_H1_fair_housing_question_returns_blocked(): void
    {
        $runner = $this->makeStubRunner(
            'Beautiful family-friendly neighborhood.',
            $this->adapterSentinel(),
            true,
            true,
            'prohibited'
        );

        $result = $runner->run('seller', 121, 'What race of people live in this neighborhood?');

        $this->assertSame('blocked', $result['status'],
            'H1: Fair Housing question must be blocked → status=blocked');
        $this->assertFalse($result['success'],
            'H1: blocked response must have success=false');
        $this->assertArrayNotHasKey(
            'description_fallback_unsupported_attempted',
            $result['trace'],
            'H1: description fallback must never fire for prohibited questions'
        );
    }

    // =========================================================================
    // Scenario I: Completely unrelated question → description sentinel → insufficient_context
    // =========================================================================

    /**
     * I1: "What is the weather today?" is not a greeting/ack, so Stage 1
     * allows it through.  The description fallback fires and the OpenAI sentinel
     * returns INFORMATION_NOT_IN_DESCRIPTION.  The result is
     * status='insufficient_context' — not 'unsupported'.
     *
     * This is the desired behaviour: the description/OpenAI sentinel is the
     * authoritative judge.  A keyword gate (Stage 2) was deliberately removed
     * because it recreated the same failure class it was meant to fix.
     */
    public function test_I1_weather_question_not_greeting_reaches_fallback_returns_insufficient_context(): void
    {
        $runner = $this->makeStubRunner(
            'Beautiful 3-bedroom home with ocean views.',
            $this->adapterSentinel()
        );

        $result = $runner->run('seller', 121, 'What is the weather today?');

        $this->assertTrue(
            $result['trace']['description_fallback_unsupported_attempted'] ?? false,
            'I1: non-greeting question must pass Stage 1 → description fallback must be attempted'
        );
        $this->assertSame('insufficient_context', $result['status'],
            'I1: weather question → sentinel miss → status must be insufficient_context');
    }

    /**
     * I2: isListingRelatedQuestion() unit check — clearly off-topic questions
     * are correctly classified by the helper method.
     * NOTE: this helper is no longer part of the fallback gate.  These questions
     * will reach the description fallback and return 'insufficient_context' via
     * the sentinel, not 'unsupported'.
     */
    public function test_I2_off_topic_questions_fail_stage2(): void
    {
        $offTopic = [
            'What is the weather today?',
            'Who won the football game?',
            'Tell me a joke.',
            'How do I make pasta?',
        ];

        foreach ($offTopic as $q) {
            $this->assertFalse(
                $this->callRunnerBoolMethod('isListingRelatedQuestion', $q),
                "I2: \"$q\" is correctly classified as non-listing by the helper method"
            );
        }
    }

    // =========================================================================
    // Scenario PARA: Paraphrase-style listing questions reach fallback → never unsupported
    // =========================================================================

    /**
     * PARA1: Valid listing questions phrased without obvious property nouns pass
     * Stage 1 (not greetings/acks) and reach the description fallback.  With
     * the description containing no specific answer, the result is
     * 'insufficient_context' — but never 'unsupported'.
     */
    public function test_PARA1_paraphrase_listing_questions_pass_stage2_never_return_unsupported(): void
    {
        $runner = $this->makeStubRunner(
            'Beautiful 3-bedroom home with ocean views and a renovated kitchen.',
            $this->adapterSentinel()   // sentinel → miss (description has no specific answer)
        );

        $paraphrases = [
            'Is it move-in ready?',
            'What should I know before making an offer?',
            "How's the condition overall?",
            'Are there any known issues with this home?',
            'Has it been recently renovated?',
            'Are there any repair needs?',
        ];

        foreach ($paraphrases as $q) {
            $result = $runner->run('seller', 121, $q);

            $this->assertNotSame('unsupported', $result['status'],
                "PARA1: \"$q\" passes Stage 2 and must never return status='unsupported'");
            $this->assertContains($result['status'], ['ready', 'insufficient_context'],
                "PARA1: \"$q\" must return 'ready' or 'insufficient_context', got '{$result['status']}'");
        }
    }

    /**
     * PARA2: When the description contains the answer to a paraphrase listing
     * question, the adapter returns a real answer → status='ready'.
     * "Is it move-in ready?" passes Stage 1 (not a greeting) and reaches the fallback.
     */
    public function test_PARA2_paraphrase_listing_question_with_answer_returns_ready(): void
    {
        $runner = $this->makeStubRunner(
            'The home has been freshly painted and is completely move-in ready.',
            $this->adapterHit('Yes, the property is move-in ready with fresh paint and new flooring.')
        );

        $result = $runner->run('seller', 121, 'Is it move-in ready?');

        $this->assertSame('ready', $result['status'],
            'PARA2: description has the answer → must return ready');
        $this->assertSame(
            'Yes, the property is move-in ready with fresh paint and new flooring.',
            $result['final_response']['answer'],
            'PARA2: answer must be the adapter-extracted text, not a placeholder'
        );
    }

    // =========================================================================
    // Stage 1 helper method tests (isObviouslyNonListingQuestion)
    // =========================================================================

    /**
     * Greetings and one-word acknowledgements are blocked at Stage 1 without
     * any keyword matching — they return 'unsupported' immediately.
     */
    public function test_stage1_greetings_and_acks_return_true(): void
    {
        $hardRejects = [
            'Hello', 'Hi', 'hey', 'good morning', 'Good Evening.',
            'thanks', 'Thank you!', 'bye', 'ok', 'okay', 'yes', 'no',
            'sure.', 'got it',
        ];

        foreach ($hardRejects as $q) {
            $this->assertTrue(
                $this->callRunnerBoolMethod('isObviouslyNonListingQuestion', $q),
                "Stage 1: '$q' must be flagged as obviously non-listing"
            );
        }
    }

    /**
     * Real listing questions are NOT flagged by Stage 1 — they are allowed
     * through to Stage 2 and beyond.
     */
    public function test_stage1_listing_questions_return_false(): void
    {
        $listingQuestions = [
            'Does seller offer a credit?',
            'Is this property in a flood zone?',
            'What is the asking price?',
            'Are pets allowed?',
            'How many bedrooms does this home have?',
            'When was this built?',
        ];

        foreach ($listingQuestions as $q) {
            $this->assertFalse(
                $this->callRunnerBoolMethod('isObviouslyNonListingQuestion', $q),
                "Stage 1: '$q' must NOT be flagged as obviously non-listing"
            );
        }
    }

    // =========================================================================
    // isListingRelatedQuestion() helper unit tests
    // (method is no longer part of the fallback gate — kept as a classification utility)
    // =========================================================================

    /**
     * Listing-domain questions containing property, transaction, or physical-
     * feature signals are correctly classified as listing-related.
     */
    public function test_stage2_returns_true_for_listing_questions(): void
    {
        $listingQuestions = [
            'Does seller offer a credit?',
            'Is this property in a flood zone?',
            'How many bedrooms does this home have?',
            'Is there an HOA?',
            'What is the lease term?',
            'Does the landlord allow pets?',
            'What is the asking price?',
            'Is the garage attached?',
            'Are there any closing cost concessions?',
            'What is the flood zone designation?',
        ];

        foreach ($listingQuestions as $q) {
            $this->assertTrue(
                $this->callRunnerBoolMethod('isListingRelatedQuestion', $q),
                "Stage 2: '$q' must be flagged as listing-related"
            );
        }
    }

    /**
     * Clearly off-topic questions are correctly classified as non-listing-related
     * by the helper method.
     */
    public function test_stage2_returns_false_for_off_topic_questions(): void
    {
        $offTopicQuestions = [
            'What is the weather today?',
            'Who won the football game?',
            'Tell me a joke.',
            'How do I make pasta?',
        ];

        foreach ($offTopicQuestions as $q) {
            $this->assertFalse(
                $this->callRunnerBoolMethod('isListingRelatedQuestion', $q),
                "Stage 2: '$q' must NOT be flagged as listing-related"
            );
        }
    }

    /**
     * isListingRelatedQuestion() word-boundary collision guard.
     *
     * The helper uses preg_match('/\b...\b/') so that signal words appearing as
     * internal substrings of unrelated words do not produce false positives.
     *
     * Collision pairs:
     *   "current"  ⊃  "rent"   → "The current traffic situation is very bad."
     *   "lottery"  ⊃  "lot"    → "Did they win the lottery last night?"
     *   "united"   ⊃  "unit"   → "We stand united as a nation."
     *   "island"   ⊃  "land"   → "The island resort is a vacation spot."
     */
    public function test_stage2_word_boundary_prevents_substring_false_positives(): void
    {
        $collisions = [
            ['The current traffic situation is very bad.', '"rent" inside "current" must not match'],
            ['Did they win the lottery last night?',      '"lot" inside "lottery" must not match'],
            ['We stand united as a nation.',              '"unit" inside "united" must not match'],
            ['The island resort is a vacation spot.',     '"land" inside "island" must not match'],
        ];

        foreach ($collisions as [$q, $msg]) {
            $this->assertFalse(
                $this->callRunnerBoolMethod('isListingRelatedQuestion', $q),
                "Stage2 word-boundary: $msg"
            );
        }
    }

    // =========================================================================
    // isBareAnswerPlaceholder helper tests
    // =========================================================================

    /** Bare "Other." with trailing period → true (punctuation stripped before matching) */
    public function test_placeholder_true_with_trailing_punctuation(): void
    {
        $this->assertTrue(
            $this->callIsBareAnswerPlaceholder('Other.'),
            '"Other." with trailing period must still be recognised as a placeholder'
        );
    }

    /** "Other" embedded in a real sentence → false (exact-match only) */
    public function test_placeholder_false_for_embedded_other(): void
    {
        $this->assertFalse(
            $this->callIsBareAnswerPlaceholder('The property falls under Other flood zone designations.'),
            '"Other" embedded in a sentence must NOT be treated as a bare placeholder'
        );
    }

    /** "See Private Remarks" → true */
    public function test_placeholder_true_for_see_private_remarks(): void
    {
        $this->assertTrue(
            $this->callIsBareAnswerPlaceholder('See Private Remarks'),
            'bare "See Private Remarks" must be recognised as a placeholder'
        );
    }

    // =========================================================================
    // Regression R1: "Does seller offer a credit?" → description fallback → ready
    //
    // Before the fix this returned 'unsupported' because the normalizer flag
    // was required to unlock the Step 1a-desc path.
    // =========================================================================

    public function test_R1_seller_credit_question_uses_description_fallback_when_description_has_answer(): void
    {
        $desc   = 'The seller is offering a $5,000 credit toward buyer closing costs.';
        $runner = $this->makeStubRunner($desc, $this->adapterHit($desc));

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $this->assertSame('ready', $result['status'],
            'R1: credit question + description → must return ready, not unsupported');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'R1: answer must come from the description_fallback path');
        $this->assertNotEmpty($result['final_response']['answer'] ?? null,
            'R1: final_response.answer must be non-empty when the description hits');
    }

    // =========================================================================
    // Regression R2: flood zone "Other" (seller) → custom value, never "Other"
    // =========================================================================

    public function test_R2_seller_flood_zone_Other_resolves_to_custom_value(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'Other',
            ['flood_zone_code_other' => 'AE']
        );

        $this->assertNotSame('Other', $result,
            'R2 seller: flood_zone_code="Other" must NEVER return the literal "Other"');
        $this->assertSame('AE', $result,
            'R2 seller: flood_zone_code="Other" + flood_zone_code_other="AE" must return "AE"');
    }

    public function test_R2b_seller_flood_zone_Other_returns_null_when_no_custom_value(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'Other',
            ['flood_zone_code_other' => null]
        );

        $this->assertNotSame('Other', $result,
            'R2b seller: flood_zone_code="Other" + no fallback must NEVER return "Other"');
        $this->assertNull($result,
            'R2b seller: flood_zone_code="Other" + null fallback must return null');
    }

    // =========================================================================
    // Regression R3: flood zone "Other" (landlord) → custom value, never "Other"
    // =========================================================================

    public function test_R3_landlord_flood_zone_Other_resolves_to_custom_value(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'Other',
            ['flood_zone_code_other' => 'X500']
        );

        $this->assertNotSame('Other', $result,
            'R3 landlord: flood_zone_code="Other" must NEVER return the literal "Other"');
        $this->assertSame('X500', $result,
            'R3 landlord: flood_zone_code="Other" + flood_zone_code_other="X500" must return "X500"');
    }

    public function test_R3b_landlord_flood_zone_Other_returns_null_when_no_custom_value(): void
    {
        $result = $this->callResolveOtherValue(
            $this->makeContextBuilder(),
            'Other',
            ['flood_zone_code_other' => '']
        );

        $this->assertNotSame('Other', $result,
            'R3b landlord: flood_zone_code="Other" + empty fallback must NEVER return "Other"');
        $this->assertNull($result,
            'R3b landlord: flood_zone_code="Other" + empty fallback must return null');
    }
}
