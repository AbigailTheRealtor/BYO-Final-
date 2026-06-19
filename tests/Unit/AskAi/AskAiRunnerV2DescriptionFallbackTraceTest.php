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
 * AskAiRunnerV2DescriptionFallbackTraceTest
 *
 * Provides live traces and structural proofs for the unsupported-question
 * description fallback (Step 1a-desc) to satisfy the verification criteria
 * requested for approval:
 *
 *   1. Live trace — Listing 121 ("Does seller offer a credit?") showing the
 *      correct status for both the empty-description case (current DB state)
 *      and a description-present simulation.
 *   2. Buyer/Tenant description sourcing — confirms correct source fields.
 *   3. Single OpenAI call — adapter.generate() is called exactly once when
 *      the fallback succeeds.
 *   4. No second keyword registry — isRealEstateQuestion() removed; adapter
 *      sentinel handles off-topic questions.
 *   5. Before/After trace table — Seller, Buyer, Tenant, Landlord.
 */
class AskAiRunnerV2DescriptionFallbackTraceTest extends TestCase
{
    // =========================================================================
    // Stub runner factory (identical pattern to the unit test companion file)
    // =========================================================================

    private function makeRunner(
        ?string $stubDescription,
        string  $adapterAnswerText,
        bool    $normalizerEnabled = true,
        bool    $descFallbackOn    = true,
        string  $classifierType    = 'unsupported'
    ): AskAiRunnerV2Service {
        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => $classifierType,
            'confidence'    => 0.0,
            'reason'        => 'stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => null,
            'contract'       => null,
            'prompt_package' => ['status' => $classifierType],
        ]);

        $adapter = $this->createMock(AskAiOpenAiAdapterService::class);
        $adapter->method('generate')->willReturn([
            'success'           => true,
            'status'            => 'generated',
            'raw_response'      => json_encode(['answer_text' => $adapterAnswerText]),
            'model'             => 'gpt-4o-mini',
            'error'             => null,
            'prompt_tokens'     => 50,
            'completion_tokens' => 20,
            'total_tokens'      => 70,
            'api_request_id'    => 'req_trace_stub',
        ]);

        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);
        $finalBuilder->method('build')->willReturn([
            'success'            => false,
            'status'             => 'unsupported',
            'answer'             => null,
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => null,
            'error'              => null,
        ]);
        // Pass-through: coercion behavior is tested in builder unit tests;
        // here we only verify runner routing, so preserve whatever build() returned.
        $finalBuilder->method('coerceToContractStatus')->willReturnArgument(0);

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $normalizer = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizer->method('isEnabled')->willReturn($normalizerEnabled);
        $normalizer->method('normalize')->willReturn(null);
        $normalizer->method('getLastStatus')->willReturn('unknown');
        $normalizer->method('getLastError')->willReturn(null);
        $normalizer->method('getLastContextPath')->willReturn(null);
        $normalizer->method('buildKnownFieldKeys')->willReturn([]);

        $descStub = $stubDescription;

        return new class(
            $classifier,
            $internalRunner,
            $adapter,
            $finalBuilder,
            $followUp,
            $normalizer,
            null,
            $descFallbackOn,
            $descStub
        ) extends AskAiRunnerV2Service {
            private ?string $d;

            public function __construct(
                AskAiQuestionClassifierService   $c,
                AskAiInternalRunnerService       $ir,
                AskAiOpenAiAdapterService        $a,
                AskAiFinalResponseBuilderService $fb,
                AskAiFollowUpQuestionService     $fu,
                ?AskAiIntentNormalizerService    $n,
                $ks,
                bool                             $flag,
                ?string                          $desc
            ) {
                parent::__construct($c, $ir, $a, $fb, $fu, $n, $ks, $flag);
                $this->d = $desc;
            }

            protected function loadListingDescription(string $t, int $id): ?string
            {
                return $this->d;
            }
        };
    }

    // =========================================================================
    // 1. Live trace — Listing 121
    // =========================================================================

    /**
     * Listing 121 (seller) has an empty description in the current database.
     * The fallback enters the outer condition (unsupported + flag + normalizer)
     * but finds a null description, so the OpenAI call is skipped and the result
     * remains 'unsupported'.  This is correct — not a regression.
     */
    public function test_trace_listing_121_empty_description_returns_unsupported(): void
    {
        // Verify the live DB state first
        $liveDesc = DB::table('seller_agent_auctions')->where('id', 121)->value('description');
        $this->assertEmpty($liveDesc, 'Precondition: listing 121 description must be empty in dev DB');

        // Run with null stub (matches live state)
        $runner = $this->makeRunner(null, 'irrelevant');
        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $trace = $result['trace'];

        $this->assertSame('unsupported', $result['status'],
            'Empty description → fallback skips → status remains unsupported');
        $this->assertArrayNotHasKey('description_fallback_unsupported_attempted', $trace,
            'Trace key absent when description is empty (fallback never fired)');
        $this->assertSame('unsupported', $trace['final_status']);
    }

    /**
     * Same listing 121, same question — but now with a description present.
     * This simulates the listing once a seller fills in their description field.
     * The fallback fires, the adapter returns an answer, and the result is 'ready'.
     */
    public function test_trace_listing_121_with_description_returns_ready(): void
    {
        $desc   = 'The seller is offering a $5,000 credit toward buyer closing costs. '
                . 'The home is a 3BR/2BA pool home, move-in ready, priced at $450,000.';
        $answer = 'Yes — the seller is offering a $5,000 credit toward buyer closing costs.';

        $runner = $this->makeRunner($desc, $answer);
        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        $trace = $result['trace'];
        $fr    = $result['final_response'];

        $this->assertSame('ready', $result['status'],
            'Description present + hit answer → status=ready');
        $this->assertTrue($result['success']);
        $this->assertSame('description_fallback', $result['outcome_category']);

        $this->assertSame('ready', $fr['status']);
        $this->assertSame('description_fallback', $fr['source']['answer_source']);
        $this->assertContains('Listing description.', $fr['source_attribution']);
        $this->assertStringContainsString('$5,000', $fr['answer']);

        $this->assertTrue($trace['description_fallback_unsupported_attempted']);
        $this->assertTrue($trace['description_fallback_unsupported_used']);
        $this->assertSame('ready', $trace['final_status']);
    }

    // =========================================================================
    // 2. Description source field verification — Buyer and Tenant
    // =========================================================================

    /**
     * Buyer: the public-facing description is stored in
     * buyer_agent_auctions.additional_details (native column).
     *
     * Verifies the column actually exists in the schema so loadListingDescription
     * can read it without an exception.
     */
    public function test_buyer_description_source_is_native_additional_details_column(): void
    {
        $col = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'buyer_agent_auctions' AND column_name = 'additional_details'"
        );
        $this->assertNotEmpty($col,
            'buyer_agent_auctions.additional_details must exist as a native column');

        // Confirm a buyer question routes correctly through the fallback
        $runner = $this->makeRunner('I need a 3BR near top-rated schools, fully pre-approved.', 'Buyer is pre-approved.');
        $result = $runner->run('buyer', 99, 'Is pre-approval required?');

        $this->assertSame('ready', $result['status']);
        $this->assertSame('description_fallback', $result['outcome_category']);
    }

    /**
     * Tenant: the public-facing description is stored in
     * tenant_agent_auction_metas with meta_key='additional_details' (EAV),
     * NOT in a native column on tenant_agent_auctions.
     *
     * Verifies:
     *  (a) The native table has no additional_details column (would be a schema bug).
     *  (b) The EAV meta table has rows with that meta_key.
     *  (c) A real EAV read via loadListingDescription returns the correct value.
     */
    public function test_tenant_description_source_is_eav_additional_details_meta(): void
    {
        // (a) No native column on tenant_agent_auctions
        $nativeCol = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'tenant_agent_auctions' AND column_name = 'additional_details'"
        );
        $this->assertEmpty($nativeCol,
            'tenant_agent_auctions must NOT have a native additional_details column — data is in EAV');

        // (b) EAV meta key exists
        $metaRow = DB::table('tenant_agent_auction_metas')
            ->where('meta_key', 'additional_details')
            ->whereNotNull('meta_value')
            ->where('meta_value', '!=', '')
            ->where(DB::raw('length(meta_value)'), '>', 5)
            ->select('tenant_agent_auction_id', 'meta_value')
            ->first();

        $this->assertNotNull($metaRow,
            'tenant_agent_auction_metas must have at least one additional_details meta row');

        // (c) Real EAV read via protected method
        $runner = $this->makeRunner(null, 'irrelevant');
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        DB::beginTransaction();
        try {
            $val = $method->invoke($runner, 'tenant', (int) $metaRow->tenant_agent_auction_id);
        } finally {
            DB::rollBack();
        }

        $this->assertSame(trim($metaRow->meta_value), $val,
            'loadListingDescription for tenant must read from EAV meta, not native column');
    }

    /**
     * Landlord: no public-facing description field exists in either the native
     * table or EAV metas. loadListingDescription returns null for every landlord
     * listing ID, so the fallback is always skipped for landlord questions.
     */
    public function test_landlord_description_always_null_no_column_exists(): void
    {
        $nativeCol = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'landlord_agent_auctions' AND column_name = 'description'"
        );
        $this->assertEmpty($nativeCol,
            'landlord_agent_auctions must NOT have a native description column');

        $runner = $this->makeRunner(null, 'irrelevant');
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'loadListingDescription');
        $method->setAccessible(true);

        foreach (['landlord', 'landlord_agent_auction', 'landlord_auction'] as $alias) {
            $this->assertNull(
                $method->invoke($runner, $alias, 1),
                "loadListingDescription must return null for landlord alias '$alias'"
            );
        }
    }

    // =========================================================================
    // 3. Single OpenAI call when fallback succeeds
    // =========================================================================

    /**
     * When the description fallback hits (status='ready'), exactly ONE adapter
     * call is made.  In production, the normalizer makes 1 prior call — that
     * call is mocked here, so the adapter call count of 1 is the fallback call.
     *
     * Before this feature: 1 call (normalizer only), result = 'unsupported'.
     * After this feature:  2 calls total (1 normalizer + 1 adapter), result = 'ready'.
     *
     * The adapter mock is configured with expects(once()) to enforce the
     * single-call constraint at the PHPUnit level.
     */
    public function test_exactly_one_adapter_call_when_description_fallback_hits(): void
    {
        $desc   = 'Seller offers a $5,000 credit toward closing costs.';
        $answer = 'Yes — seller offers a $5,000 credit.';

        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => 'unsupported',
            'confidence'    => 0.0,
            'reason'        => 'stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => null,
            'contract'       => null,
            'prompt_package' => ['status' => 'unsupported'],
        ]);

        // Strict mock: expects EXACTLY one call to generate()
        $adapter = $this->createMock(AskAiOpenAiAdapterService::class);
        $adapter->expects($this->once())
            ->method('generate')
            ->willReturn([
                'success'           => true,
                'status'            => 'generated',
                'raw_response'      => json_encode(['answer_text' => $answer]),
                'model'             => 'gpt-4o-mini',
                'error'             => null,
                'prompt_tokens'     => 50,
                'completion_tokens' => 20,
                'total_tokens'      => 70,
                'api_request_id'    => 'req_single_call',
            ]);

        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);
        $finalBuilder->method('build')->willReturn([
            'success'            => false,
            'status'             => 'unsupported',
            'answer'             => null,
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => null,
            'error'              => null,
        ]);
        $finalBuilder->method('coerceToContractStatus')->willReturnArgument(0);

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $normalizer = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizer->method('isEnabled')->willReturn(true);
        $normalizer->method('normalize')->willReturn(null);
        $normalizer->method('getLastStatus')->willReturn('unknown');
        $normalizer->method('getLastError')->willReturn(null);
        $normalizer->method('getLastContextPath')->willReturn(null);
        $normalizer->method('buildKnownFieldKeys')->willReturn([]);

        $descStub = $desc;
        $runner = new class(
            $classifier, $internalRunner, $adapter, $finalBuilder, $followUp, $normalizer,
            null, true, $descStub
        ) extends AskAiRunnerV2Service {
            private ?string $d;
            public function __construct($c, $ir, $a, $fb, $fu, $n, $ks, bool $flag, ?string $d) {
                parent::__construct($c, $ir, $a, $fb, $fu, $n, $ks, $flag);
                $this->d = $d;
            }
            protected function loadListingDescription(string $t, int $id): ?string { return $this->d; }
        };

        $result = $runner->run('seller', 121, 'Does seller offer a credit?');

        // PHPUnit enforces expects(once()) at teardown — passing test = exactly 1 call
        $this->assertSame('ready', $result['status']);
        $this->assertSame('description_fallback', $result['outcome_category']);
    }

    // =========================================================================
    // 4. No second keyword registry — off-topic question uses adapter sentinel
    // =========================================================================

    /**
     * "What is the weather today?" passes through to the description fallback
     * (no isRealEstateQuestion() guard) and the adapter returns the sentinel.
     * Result: insufficient_context (description_fallback_miss), not 'unsupported'.
     *
     * This confirms:
     *  (a) isRealEstateQuestion() no longer exists in the class.
     *  (b) Off-topic questions are handled by the adapter sentinel, not pre-filtered.
     *  (c) The behavior is correct: the listing description cannot answer a weather
     *      question, so the sentinel fires and the user gets a clear "not found" message.
     */
    public function test_no_keyword_registry_off_topic_uses_adapter_sentinel(): void
    {
        // (a) Confirm isRealEstateQuestion no longer exists
        $this->assertFalse(
            method_exists(AskAiRunnerV2Service::class, 'isRealEstateQuestion'),
            'isRealEstateQuestion() must be removed — no second keyword registry'
        );

        // (b) Off-topic question with description → fallback attempted → sentinel → miss
        $runner = $this->makeRunner(
            'This beautiful 3BR home has a pool and ocean views.',
            'INFORMATION_NOT_IN_DESCRIPTION'  // sentinel
        );

        $result = $runner->run('seller', 121, 'What is the weather today?');

        $this->assertTrue(
            $result['trace']['description_fallback_unsupported_attempted'] ?? false,
            'Fallback must be attempted for off-topic questions — adapter is the guard'
        );
        $this->assertSame('insufficient_context', $result['status'],
            'Sentinel fires → insufficient_context, not a hard error or unsupported');
        $this->assertSame('description_fallback_miss', $result['outcome_category']);
        $this->assertSame(
            'This information was not provided in the listing description.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 5. Before/After trace table — one assertion per role scenario
    // =========================================================================

    /**
     * Seller — question answerable from description.
     * BEFORE (flag off): unsupported
     * AFTER  (flag on):  ready [description_fallback]
     */
    public function test_before_after_seller_answerable_from_description(): void
    {
        $desc   = 'Seller offers a $5,000 closing cost credit. Pool, 3BR/2BA.';
        $before = $this->makeRunner($desc, 'irrelevant', true, false)->run('seller', 121, 'Does seller offer a credit?');
        $after  = $this->makeRunner($desc, 'Seller offers a $5,000 credit.', true, true)->run('seller', 121, 'Does seller offer a credit?');

        $this->assertSame('unsupported', $before['status'], 'BEFORE: unsupported');
        $this->assertSame('ready', $after['status'],        'AFTER:  ready');
        $this->assertSame('description_fallback', $after['final_response']['source']['answer_source']);
    }

    /**
     * Buyer — additional_details holds buyer criteria description.
     * BEFORE (flag off): unsupported
     * AFTER  (flag on):  ready [description_fallback]
     */
    public function test_before_after_buyer_answerable_from_description(): void
    {
        $desc   = 'Looking for a 3BR near top schools. Pre-approved for $550k. Closing in 30 days.';
        $before = $this->makeRunner($desc, 'irrelevant', true, false)->run('buyer', 55, 'Is pre-approval confirmed?');
        $after  = $this->makeRunner($desc, 'Yes — pre-approved for $550k.', true, true)->run('buyer', 55, 'Is pre-approval confirmed?');

        $this->assertSame('unsupported', $before['status'], 'BEFORE: unsupported');
        $this->assertSame('ready', $after['status'],        'AFTER:  ready');
        $this->assertSame('description_fallback', $after['final_response']['source']['answer_source']);
    }

    /**
     * Tenant — additional_details stored in EAV meta.
     * BEFORE (flag off): unsupported
     * AFTER  (flag on):  ready [description_fallback]
     */
    public function test_before_after_tenant_answerable_from_description(): void
    {
        $desc   = 'Need a 3/2 in St. Pete that will allow me to have a pet.';
        $before = $this->makeRunner($desc, 'irrelevant', true, false)->run('tenant', 137, 'Do they allow pets?');
        $after  = $this->makeRunner($desc, 'Tenant explicitly requires a pet-friendly property.', true, true)->run('tenant', 137, 'Do they allow pets?');

        $this->assertSame('unsupported', $before['status'], 'BEFORE: unsupported');
        $this->assertSame('ready', $after['status'],        'AFTER:  ready');
        $this->assertSame('description_fallback', $after['final_response']['source']['answer_source']);
    }

    /**
     * Landlord — no description field exists; fallback always skips.
     * BEFORE (flag off): unsupported
     * AFTER  (flag on):  unsupported (description always null → fallback skips)
     */
    public function test_before_after_landlord_no_description_field_unchanged(): void
    {
        // Stub returns null for any description (matches production loadListingDescription for landlord)
        $before = $this->makeRunner(null, 'irrelevant', true, false)->run('landlord', 77, 'Are pets allowed?');
        $after  = $this->makeRunner(null, 'irrelevant', true, true)->run('landlord', 77, 'Are pets allowed?');

        $this->assertSame('unsupported', $before['status'], 'BEFORE: unsupported');
        $this->assertSame('unsupported', $after['status'],  'AFTER:  still unsupported (no description)');
        $this->assertArrayNotHasKey(
            'description_fallback_unsupported_attempted',
            $after['trace'],
            'Trace key absent — description fallback never fires for landlord'
        );
    }
}
