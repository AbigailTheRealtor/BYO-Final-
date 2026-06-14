<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AskAiRunnerV2DescriptionFallbackUnsupportedTest
 *
 * Verifies the "unsupported-question description fallback" path (Step 1a-desc)
 * in AskAiRunnerV2Service.
 *
 * Terminology:
 *   hit  = adapter returned a real answer (non-sentinel), status → 'ready'
 *   miss = adapter returned INFORMATION_NOT_IN_DESCRIPTION sentinel, status → 'insufficient_context'
 *
 * Test inventory:
 *   A. Hit — real answer extracted → status='ready', answer_source='description_fallback'.
 *   B. Miss — sentinel returned → status='insufficient_context', answer_source='description_fallback_miss'.
 *   C. Flag off — enableDescriptionFallback=false → path not entered.
 *   D. Prohibited bypass — classifier returns 'prohibited' → path not entered.
 *   E. Empty description — loadListingDescription returns null → path not entered.
 *   F. Normalizer disabled — plausibility gate (not normalizer) gates Step 1a-desc; listing
 *      question with normalizer=disabled still enters fallback → status='ready'.
 *   G. Off-topic question — "What is the weather today?" passes Stage 1 (not a greeting/ack)
 *      but fails Stage 2 (isListingRelatedQuestion → false, no property/RE signals);
 *      description fallback NOT entered → status='unsupported'.
 *   H. loadListingDescription returns null for unknown listing type.
 *   I. loadListingDescription returns null for landlord (no description field).
 *   J. loadListingDescription returns null when no DB row exists (real DB).
 *   K. loadListingDescription returns description for existing tenant row (real EAV DB).
 *   L. loadListingDescription returns description for existing seller row (real DB, skipped if absent).
 *
 * Tests A–G use a stub subclass that overrides loadListingDescription() to control
 * what description the pipeline sees, avoiding DB inserts into the schema which
 * has NOT NULL constraints on many columns.
 *
 * Tests H–L exercise the real protected method via reflection; J–L use manual
 * DB transactions to keep the schema clean.
 */
class AskAiRunnerV2DescriptionFallbackUnsupportedTest extends TestCase
{
    // =========================================================================
    // Stub runner factory
    // =========================================================================

    /**
     * Build a testable AskAiRunnerV2Service subclass that:
     *  - stubs all collaborators (classifier, adapter, etc.)
     *  - overrides loadListingDescription() to return $stubDescription
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
            'confidence'    => $classifierType === 'prohibited' ? 1.0 : 0.0,
            'reason'        => 'stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => null,
            'contract'       => null,
            'prompt_package' => ['status' => $classifierType],
        ]);

        $adapterMock = $this->createMock(AskAiOpenAiAdapterService::class);
        $adapterMock->method('generate')->willReturn($adapterResponse);

        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);
        $finalBuilder->method('build')->willReturn([
            'success'            => false,
            'status'             => $classifierType === 'prohibited' ? 'blocked' : 'unsupported',
            'answer'             => null,
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => null,
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
            'api_request_id'    => 'req_test_123',
        ];
    }

    private function adapterMiss(): array
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
            'api_request_id'    => 'req_test_456',
        ];
    }

    // =========================================================================
    // A. Hit path
    // =========================================================================

    /**
     * When the adapter extracts a non-sentinel answer, the runner returns
     * status='ready' with answer_source='description_fallback'.
     *
     * Trace must include:
     *   description_fallback_unsupported_attempted = true
     *   description_fallback_unsupported_used      = true
     *   final_status                               = 'ready'
     */
    public function test_hit_returns_ready_with_description_fallback_source(): void
    {
        $desc = 'The seller is offering a $5,000 credit toward buyer closing costs.';
        $runner = $this->makeStubRunner($desc, $this->adapterHit($desc));

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $this->assertSame('ready', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertSame('description_fallback', $result['outcome_category']);

        $fr = $result['final_response'];
        $this->assertSame('ready', $fr['status']);
        $this->assertSame('description_fallback', $fr['source']['answer_source']);
        $this->assertContains('Listing description.', $fr['source_attribution']);
        $this->assertNotEmpty($fr['answer']);

        $trace = $result['trace'];
        $this->assertTrue($trace['description_fallback_unsupported_attempted'] ?? false,
            'Trace must set description_fallback_unsupported_attempted=true on hit');
        $this->assertTrue($trace['description_fallback_unsupported_used'] ?? false,
            'Trace must set description_fallback_unsupported_used=true on hit');
        $this->assertSame('ready', $trace['final_status']);
    }

    // =========================================================================
    // B. Miss path
    // =========================================================================

    /**
     * When the adapter returns the INFORMATION_NOT_IN_DESCRIPTION sentinel, the
     * runner returns status='insufficient_context' with the standard miss message.
     *
     * Trace must include:
     *   description_fallback_unsupported_attempted = true
     *   description_fallback_unsupported_used      = false
     *   final_status                               = 'insufficient_context'
     */
    public function test_miss_returns_insufficient_context_with_description_message(): void
    {
        $runner = $this->makeStubRunner(
            'This home features a stunning pool and three-car garage.',
            $this->adapterMiss()
        );

        $result = $runner->run('seller', 121, 'Does the seller offer closing cost assistance?');

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertSame('description_fallback_miss', $result['outcome_category']);

        $fr = $result['final_response'];
        $this->assertSame('insufficient_context', $fr['status']);
        $this->assertSame(
            'This information was not provided in the listing description.',
            $fr['answer']
        );
        $this->assertSame('description_fallback_miss', $fr['source']['answer_source']);

        $trace = $result['trace'];
        $this->assertTrue($trace['description_fallback_unsupported_attempted'] ?? false,
            'Trace must set description_fallback_unsupported_attempted=true on miss');
        $this->assertFalse($trace['description_fallback_unsupported_used'] ?? true,
            'Trace must set description_fallback_unsupported_used=false on miss');
        $this->assertSame('insufficient_context', $trace['final_status']);
    }

    // =========================================================================
    // C. Flag off
    // =========================================================================

    /**
     * When enableDescriptionFallback=false, the Step 1a-desc block is not
     * entered and the final status remains 'unsupported'.
     */
    public function test_flag_off_skips_description_fallback(): void
    {
        $runner = $this->makeStubRunner(
            'The seller is offering a $5,000 credit toward buyer closing costs.',
            $this->adapterHit('irrelevant'),
            true,
            false   // descFallbackOn = false
        );

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $this->assertSame('unsupported', $result['status']);
        $this->assertArrayNotHasKey(
            'description_fallback_unsupported_attempted',
            $result['trace'],
            'Fallback trace key must be absent when flag is off'
        );
    }

    // =========================================================================
    // D. Prohibited bypass
    // =========================================================================

    /**
     * When the classifier returns 'prohibited', questionType is never
     * 'unsupported', so the description fallback block is never reached.
     */
    public function test_prohibited_question_bypasses_description_fallback(): void
    {
        $runner = $this->makeStubRunner(
            'This home is located in a lovely area.',
            $this->adapterHit('irrelevant'),
            true,
            true,
            'prohibited'
        );

        $result = $runner->run('seller', 121, 'What race of people live in this neighborhood?');

        $this->assertArrayNotHasKey(
            'description_fallback_unsupported_attempted',
            $result['trace'],
            'Fallback must not fire for prohibited questions'
        );
    }

    // =========================================================================
    // E. Empty description
    // =========================================================================

    /**
     * When loadListingDescription() returns null (listing has no description),
     * the fallback block enters the condition but the inner 'if (is_string...)'
     * guard prevents the OpenAI call. The trace key is absent.
     *
     * This is also the expected live behavior for Listing 121 (seller), which
     * currently has an empty description in the database.
     */
    public function test_empty_description_skips_description_fallback(): void
    {
        $runner = $this->makeStubRunner(
            null,   // null → loadListingDescription returns null
            $this->adapterHit('irrelevant')
        );

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $this->assertSame('unsupported', $result['status']);
        $this->assertArrayNotHasKey(
            'description_fallback_unsupported_attempted',
            $result['trace'],
            'Fallback trace key must be absent when description is null/empty'
        );
    }

    // =========================================================================
    // F. Normalizer disabled — plausibility gate replaces normalizer check
    // =========================================================================

    /**
     * When normalizer->isEnabled() returns false, the Step 1a-desc fallback
     * should STILL fire for listing-related questions.  The gate now uses a
     * deterministic two-stage plausibility check instead of requiring the
     * normalizer, so disabling the normalizer must not suppress valid answers.
     *
     * "Does seller offer a credit?" contains signals ('seller', 'credit') that
     * pass isListingRelatedQuestion(), and it is not a hard-rejected greeting,
     * so isObviouslyNonListingQuestion() returns false.  The adapter returns a
     * hit → status='ready'.
     */
    public function test_normalizer_disabled_does_not_block_description_fallback(): void
    {
        $runner = $this->makeStubRunner(
            'The seller is offering a $5,000 credit toward buyer closing costs.',
            $this->adapterHit('The seller is offering a $5,000 credit toward buyer closing costs.'),
            false   // normalizerEnabled = false (no longer gates the fallback)
        );

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $this->assertTrue(
            $result['trace']['description_fallback_unsupported_attempted'] ?? false,
            'F: normalizer-disabled must NOT suppress the fallback for listing questions'
        );
        $this->assertTrue(
            $result['trace']['description_fallback_unsupported_used'] ?? false,
            'F: adapter returned a hit, so description_fallback_unsupported_used must be true'
        );
        $this->assertSame('ready', $result['status'],
            'F: listing question + description hit → status must be ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'F: outcome_category must be description_fallback on a hit');
    }

    // =========================================================================
    // G. Off-topic question — Stage 2 blocks before description fallback → unsupported
    // =========================================================================

    /**
     * "What is the weather today?" passes Stage 1 (not a greeting/ack) but
     * contains no property/RE signal word, so Stage 2 (isListingRelatedQuestion)
     * returns false and the Step 1a-desc description fallback is NOT entered.
     *
     * The result is status='unsupported' — the correct outcome for a genuinely
     * unrelated question — rather than consuming an adapter call.
     *
     * Valid listing questions phrased in unusual ways (e.g. "Is it move-in
     * ready?", "How's the condition?", "Are there repair needs?") are covered
     * by the broad signal list in isListingRelatedQuestion() — "move",
     * "condition", and "repair" are all included signals.
     */
    public function test_off_topic_question_blocked_by_stage2_returns_unsupported(): void
    {
        $runner = $this->makeStubRunner(
            'Beautiful 3-bedroom home with ocean views.',
            $this->adapterMiss()   // adapter must NOT be called for description fallback
        );

        $result = $runner->run('seller', 121, 'What is the weather today?');

        // Stage 2 blocks weather questions — the description fallback is never attempted.
        $this->assertArrayNotHasKey(
            'description_fallback_unsupported_attempted',
            $result['trace'],
            'G: Stage 2 must block weather question — description fallback must NOT be attempted'
        );
        $this->assertSame('unsupported', $result['status'],
            'G: weather question blocked by Stage 2 → status must be unsupported');
    }

    // =========================================================================
    // H. loadListingDescription — unknown type → null
    // =========================================================================

    /**
     * An unrecognised listing type (not in the canonical alias map) returns null
     * without touching the database.
     */
    public function test_load_listing_description_returns_null_for_unknown_type(): void
    {
        $runner = $this->makeStubRunner(null, $this->adapterHit('irrelevant'));
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($runner, 'unknown_type', 999));
    }

    // =========================================================================
    // I. loadListingDescription — landlord always returns null
    // =========================================================================

    /**
     * Landlord has no public freetext description field (neither a native
     * column nor a description-equivalent EAV meta key). loadListingDescription
     * must return null immediately for any landlord listing ID.
     */
    public function test_load_listing_description_returns_null_for_landlord(): void
    {
        $runner = $this->makeStubRunner(null, $this->adapterHit('irrelevant'));
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        // Landlord aliases
        foreach (['landlord', 'landlord_agent_auction', 'landlord_auction'] as $alias) {
            $this->assertNull(
                $method->invoke($runner, $alias, 1),
                "Expected null for landlord alias '$alias'"
            );
        }
    }

    // =========================================================================
    // J. loadListingDescription — absent DB row → null (real DB)
    // =========================================================================

    /**
     * When no seller row exists for the given ID, the real method returns null.
     */
    public function test_load_listing_description_returns_null_when_listing_absent(): void
    {
        $this->beginManualTransaction();

        $runner = $this->makeStubRunner(null, $this->adapterHit('irrelevant'));
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($runner, 'seller', 99999999));
    }

    // =========================================================================
    // K. loadListingDescription — tenant EAV meta → real description (real DB)
    // =========================================================================

    /**
     * Verifies the tenant path queries tenant_agent_auction_metas for
     * meta_key='additional_details' rather than a non-existent native column.
     *
     * Tenant ID 137 has meta_value='Need a 3/2 in St. Pete that will allow me
     * to have a pet' in the seeded test data.
     */
    public function test_load_listing_description_returns_tenant_eav_description(): void
    {
        $this->beginManualTransaction();

        // Find a tenant row with a real additional_details meta value
        $row = DB::table('tenant_agent_auction_metas')
            ->where('meta_key', 'additional_details')
            ->where('meta_value', '!=', '')
            ->whereNotNull('meta_value')
            ->where(DB::raw('length(meta_value)'), '>', 5)
            ->select('tenant_agent_auction_id', 'meta_value')
            ->first();

        if ($row === null) {
            $this->markTestSkipped('No tenant_agent_auction_metas row with additional_details found.');
        }

        $runner = $this->makeStubRunner(null, $this->adapterHit('irrelevant'));
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        $result = $method->invoke($runner, 'tenant', (int) $row->tenant_agent_auction_id);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertSame(trim($row->meta_value), $result);
    }

    // =========================================================================
    // L. loadListingDescription — seller native column (real DB, skipped if none)
    // =========================================================================

    /**
     * If any seller listing has a non-empty description, verify the method
     * returns it. Skipped in dev environments where no descriptions are seeded.
     */
    public function test_load_listing_description_returns_seller_native_description(): void
    {
        $this->beginManualTransaction();

        $row = DB::table('seller_agent_auctions')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->where(DB::raw('length(description)'), '>', 5)
            ->select('id', 'description')
            ->first();

        if ($row === null) {
            $this->markTestSkipped('No seller_agent_auctions row with a non-empty description found.');
        }

        $runner = $this->makeStubRunner(null, $this->adapterHit('irrelevant'));
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        $result = $method->invoke($runner, 'seller', (int) $row->id);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertSame(trim($row->description), $result);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    /**
     * Open a DB transaction that is rolled back when the test ends.
     * Used only by tests that touch the real database (J, K, L).
     */
    private function beginManualTransaction(): void
    {
        DB::beginTransaction();
        $this->beforeApplicationDestroyed(fn () => DB::rollBack());
    }
}
