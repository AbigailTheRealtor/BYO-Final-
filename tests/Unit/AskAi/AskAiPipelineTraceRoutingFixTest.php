<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * AskAiPipelineTraceRoutingFixTest
 *
 * Regression tests for the four routing gaps and trace-logging elevation
 * identified in manual verification of the AskAi V2 pipeline:
 *
 *   1. emitTrace() elevated from Log::debug to Log::info — every pipeline
 *      execution now writes a structured entry to storage/logs/laravel.log.
 *      The structured trace always includes: question, scope, listing_id,
 *      normalized_field_key, adapter_success, adapter_error, final_status.
 *
 *   2. Seller 121 — "what credit does seller offer" now routes to
 *      listing.seller_credit_offered instead of falling through to OpenAI
 *      blind. New phrases added: "what credit does seller offer",
 *      "what credit is the seller offering", "credit offered by seller",
 *      "does the seller offer any credit", "is the seller offering any credit".
 *
 *   3. Buyer 97 — "what type of financing has this buyer indicated they will
 *      use?" now routes to listing.financing_type. New phrases: "type of
 *      financing", "what type of financing has this buyer indicated",
 *      "how will this buyer finance the purchase".
 *
 *   4. Tenant 170 — "What are the strongest lease requirements I've stated?"
 *      now routes to listing.lease_terms. New phrases: "lease requirements",
 *      "lease terms", "rental requirements", "strongest lease requirements".
 *      Collision guard: "existing tenant lease terms" still routes to
 *      faq_answers.existing_tenant_lease_terms (fires before listing.lease_terms).
 *
 *   5. Landlord 71 — "tell me about the location" now routes to listing.address.
 *      New phrases added; "describe the neighborhood" unchanged (still routes
 *      to faq_answers.neighborhood_character).
 */
class AskAiPipelineTraceRoutingFixTest extends TestCase
{
    private function makeRunner(): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $this->createMock(AskAiQuestionClassifierService::class),
            $this->createMock(AskAiInternalRunnerService::class),
            $this->createMock(AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $this->createMock(AskAiFollowUpQuestionService::class),
        );
    }

    private function detectListingFieldKey(string $question): ?string
    {
        $runner = $this->makeRunner();
        $ref    = new ReflectionMethod(AskAiRunnerV2Service::class, 'detectListingFieldKey');
        $ref->setAccessible(true);
        return $ref->invoke($runner, $question);
    }

    private function detectFaqFieldKey(string $question): ?string
    {
        $runner = $this->makeRunner();
        $ref    = new ReflectionMethod(AskAiRunnerV2Service::class, 'detectFaqFieldKey');
        $ref->setAccessible(true);
        return $ref->invoke($runner, $question);
    }

    // =========================================================================
    // Section 1 — emitTrace() elevation: Log::info with structured keys
    // =========================================================================

    /**
     * emitTrace() calls Log::info (not Log::debug) so the trace always
     * appears in storage/logs/laravel.log regardless of the configured log level.
     */
    public function test_emit_trace_calls_log_info_with_structured_keys(): void
    {
        $capturedLevel   = null;
        $capturedChannel = null;
        $capturedContext = null;

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use (
                &$capturedLevel, &$capturedChannel, &$capturedContext
            ) {
                $capturedLevel   = 'info';
                $capturedChannel = $message;
                $capturedContext = $context;
                return true;
            });

        Log::shouldReceive('debug')->never();

        $runner = $this->makeRunner();
        $ref    = new ReflectionMethod(AskAiRunnerV2Service::class, 'emitTrace');
        $ref->setAccessible(true);

        $trace = [
            'question'             => 'what credit does seller offer',
            'question_type'        => 'listing_facts',
            'scope'                => 'seller',
            'listing_id'           => 121,
            'normalized_field_key' => 'listing.seller_credit_offered',
            'adapter_success'      => false,
            'adapter_error'        => 'Connection timeout',
            'final_status'         => 'insufficient_context',
        ];

        $ref->invoke($runner, $trace);

        $this->assertSame('info', $capturedLevel,
            'emitTrace must call Log::info, not Log::debug');

        $this->assertSame('AskAiRunnerV2 trace', $capturedChannel,
            'emitTrace log message must be "AskAiRunnerV2 trace"');

        foreach (['question', 'question_type', 'scope', 'listing_id',
                  'normalized_field_key', 'adapter_success', 'adapter_error',
                  'final_status'] as $key) {
            $this->assertArrayHasKey($key, $capturedContext,
                "emitTrace context must include key: $key");
        }
    }

    /**
     * The trace array built in run() includes scope and listing_id at init time,
     * and adapter_success / adapter_error keys (even when null before adapter call).
     */
    public function test_trace_init_includes_required_structured_keys(): void
    {
        $runner = $this->makeRunner();

        $refClass = new ReflectionClass(AskAiRunnerV2Service::class);

        // Read the LISTING_KEY_KEYWORD_MAP constant to verify it is accessible,
        // then verify the trace keys via a partial run using a mock classifier
        // that returns 'unsupported' so the pipeline exits before the DB call.
        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => 'unsupported',
            'confidence'    => 0.0,
            'reason'        => 'test_stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => [],
            'contract'       => [],
            'prompt_package' => ['status' => 'unsupported'],
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

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $testRunner = new AskAiRunnerV2Service(
            $classifier,
            $internalRunner,
            $this->createMock(AskAiOpenAiAdapterService::class),
            $finalBuilder,
            $followUp,
        );

        $result = $testRunner->run('seller', 121, 'what credit does seller offer');

        $trace = $result['trace'];
        $this->assertArrayHasKey('scope', $trace,
            'trace must include scope key');
        $this->assertArrayHasKey('listing_id', $trace,
            'trace must include listing_id key');
        $this->assertSame('seller', $trace['scope'],
            'trace scope must equal the listingType argument');
        $this->assertSame(121, $trace['listing_id'],
            'trace listing_id must equal the listingId argument');

        $this->assertArrayHasKey('adapter_success', $trace,
            'trace must include adapter_success key');
        $this->assertArrayHasKey('adapter_error', $trace,
            'trace must include adapter_error key');

        $this->assertArrayHasKey('question_type', $trace,
            'trace must include question_type key for downstream diagnostics');
        $this->assertSame('unsupported', $trace['question_type'],
            'trace question_type must match the classified question type');
    }

    /**
     * When Step 1b resolves a listing.* field key deterministically, the trace
     * must carry that key in normalized_field_key (not only in deterministic_field_key).
     * This is the key the diagnostic tooling reads for per-request field attribution.
     */
    public function test_deterministic_listing_field_match_sets_normalized_field_key_in_trace(): void
    {
        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => 'listing_facts',
            'confidence'    => 1.0,
            'reason'        => 'test_stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => ['listing' => ['description' => null]],
            'contract'       => [],
            'prompt_package' => [
                'status'              => 'prompt_ready',
                'allowed_context'     => ['listing' => []],
                'required_disclosures' => [],
                'source_attribution'  => [],
            ],
        ]);

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $testRunner = new AskAiRunnerV2Service(
            $classifier,
            $internalRunner,
            $this->createMock(AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $followUp,
        );

        $result = $testRunner->run('seller', 121, 'what credit does seller offer');
        $trace  = $result['trace'];

        $this->assertSame(
            'listing.seller_credit_offered',
            $trace['normalized_field_key'] ?? null,
            'normalized_field_key must be set in trace when Step 1b resolves a listing.* field key'
        );
        $this->assertSame(
            'listing.seller_credit_offered',
            $trace['deterministic_field_key'] ?? null,
            'deterministic_field_key must also be set — both keys must match for deterministic routes'
        );
    }

    /**
     * When Step 1b resolves a FAQ field key deterministically, the trace must
     * carry that key in both normalized_field_key and faq_key_detected.
     */
    public function test_deterministic_faq_match_sets_normalized_field_key_in_trace(): void
    {
        $classifier = $this->createMock(AskAiQuestionClassifierService::class);
        $classifier->method('classify')->willReturn([
            'question_type' => 'listing_facts',
            'confidence'    => 1.0,
            'reason'        => 'test_stub',
        ]);

        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $internalRunner->method('run')->willReturn([
            'context'        => ['listing' => ['description' => null]],
            'contract'       => [],
            'prompt_package' => [
                'status'              => 'prompt_ready',
                'allowed_context'     => [],
                'required_disclosures' => [],
                'source_attribution'  => [],
            ],
        ]);

        $followUp = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUp->method('forResult')->willReturn([]);

        $testRunner = new AskAiRunnerV2Service(
            $classifier,
            $internalRunner,
            $this->createMock(AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $followUp,
        );

        $result = $testRunner->run('seller', 121, 'seller concessions');
        $trace  = $result['trace'];

        $this->assertSame(
            'faq_answers.seller_concessions_offered',
            $trace['normalized_field_key'] ?? null,
            'normalized_field_key must be set in trace when Step 1b resolves a faq_answers.* field key'
        );
        $this->assertSame(
            'faq_answers.seller_concessions_offered',
            $trace['faq_key_detected'] ?? null,
            'faq_key_detected must also be set for FAQ deterministic routes'
        );
    }

    // =========================================================================
    // Section 2 — Seller credit routing (listing.seller_credit_offered)
    // =========================================================================

    /**
     * "what credit does seller offer" → listing.seller_credit_offered
     * This was the primary failure phrase for Seller 121.
     */
    public function test_seller_what_credit_does_seller_offer_routes_to_credit_offered(): void
    {
        $this->assertSame(
            'listing.seller_credit_offered',
            $this->detectListingFieldKey('what credit does seller offer'),
            '"what credit does seller offer" must route to listing.seller_credit_offered'
        );
    }

    /**
     * "what credit is the seller offering" → listing.seller_credit_offered
     */
    public function test_seller_what_credit_is_the_seller_offering_routes_to_credit_offered(): void
    {
        $this->assertSame(
            'listing.seller_credit_offered',
            $this->detectListingFieldKey('what credit is the seller offering'),
            '"what credit is the seller offering" must route to listing.seller_credit_offered'
        );
    }

    /**
     * "credit offered by seller" → listing.seller_credit_offered
     */
    public function test_seller_credit_offered_by_seller_routes_to_credit_offered(): void
    {
        $this->assertSame(
            'listing.seller_credit_offered',
            $this->detectListingFieldKey('credit offered by seller'),
            '"credit offered by seller" must route to listing.seller_credit_offered'
        );
    }

    /**
     * "does the seller offer any credit" → listing.seller_credit_offered
     */
    public function test_seller_does_the_seller_offer_any_credit_routes_to_credit_offered(): void
    {
        $this->assertSame(
            'listing.seller_credit_offered',
            $this->detectListingFieldKey('does the seller offer any credit'),
            '"does the seller offer any credit" must route to listing.seller_credit_offered'
        );
    }

    /**
     * "is the seller offering any credit" (singular) → listing.seller_credit_offered
     * Pre-existing plural form "is the seller offering any credits" also tested.
     */
    public function test_seller_is_the_seller_offering_any_credit_singular_routes_to_credit_offered(): void
    {
        $this->assertSame(
            'listing.seller_credit_offered',
            $this->detectListingFieldKey('is the seller offering any credit'),
            '"is the seller offering any credit" (singular) must route to listing.seller_credit_offered'
        );
    }

    public function test_seller_is_the_seller_offering_any_credits_plural_still_routes_to_credit_offered(): void
    {
        $this->assertSame(
            'listing.seller_credit_offered',
            $this->detectListingFieldKey('is the seller offering any credits'),
            '"is the seller offering any credits" (plural, pre-existing) must still route to listing.seller_credit_offered'
        );
    }

    /**
     * "seller concessions" must NOT have been broken — still routes to
     * faq_answers.seller_concessions_offered via the FAQ map, not the listing map.
     */
    public function test_seller_concessions_still_routes_via_faq_key(): void
    {
        $faqKey = $this->detectFaqFieldKey('seller concessions');

        $this->assertSame(
            'faq_answers.seller_concessions_offered',
            $faqKey,
            '"seller concessions" must still route to faq_answers.seller_concessions_offered (unchanged)'
        );
    }

    // =========================================================================
    // Section 3 — Buyer financing type routing (listing.financing_type)
    // =========================================================================

    /**
     * "type of financing" (word-order reversal of the pre-existing "financing type")
     * → listing.financing_type. This was the primary failure for Buyer 97.
     */
    public function test_buyer_type_of_financing_routes_to_financing_type(): void
    {
        $this->assertSame(
            'listing.financing_type',
            $this->detectListingFieldKey('type of financing'),
            '"type of financing" must route to listing.financing_type'
        );
    }

    /**
     * "what type of financing has this buyer indicated they will use?"
     * The full original failure phrase from Buyer 97.
     */
    public function test_buyer_what_type_of_financing_has_this_buyer_indicated(): void
    {
        $this->assertSame(
            'listing.financing_type',
            $this->detectListingFieldKey('what type of financing has this buyer indicated they will use?'),
            'The full Buyer 97 question must route to listing.financing_type'
        );
    }

    /**
     * "how will this buyer finance the purchase" → listing.financing_type
     */
    public function test_buyer_how_will_this_buyer_finance_the_purchase(): void
    {
        $this->assertSame(
            'listing.financing_type',
            $this->detectListingFieldKey('how will this buyer finance the purchase'),
            '"how will this buyer finance the purchase" must route to listing.financing_type'
        );
    }

    /**
     * Pre-existing phrase "financing type" must not have been broken.
     */
    public function test_buyer_financing_type_bare_phrase_still_routes(): void
    {
        $this->assertSame(
            'listing.financing_type',
            $this->detectListingFieldKey('financing type'),
            '"financing type" (pre-existing bare phrase) must still route to listing.financing_type'
        );
    }

    // =========================================================================
    // Section 4 — Tenant lease requirements routing (listing.lease_terms)
    // =========================================================================

    /**
     * "lease requirements" → listing.lease_terms
     */
    public function test_tenant_lease_requirements_routes_to_lease_terms(): void
    {
        $this->assertSame(
            'listing.lease_terms',
            $this->detectListingFieldKey('lease requirements'),
            '"lease requirements" must route to listing.lease_terms'
        );
    }

    /**
     * "what are the lease requirements" → listing.lease_terms
     */
    public function test_tenant_what_are_the_lease_requirements_routes_to_lease_terms(): void
    {
        $this->assertSame(
            'listing.lease_terms',
            $this->detectListingFieldKey('what are the lease requirements'),
            '"what are the lease requirements" must route to listing.lease_terms'
        );
    }

    /**
     * "What are the strongest lease requirements I've stated in this listing?"
     * The full original failure phrase from Tenant 170.
     */
    public function test_tenant_strongest_lease_requirements_full_phrase(): void
    {
        $this->assertSame(
            'listing.lease_terms',
            $this->detectListingFieldKey("What are the strongest lease requirements I've stated in this listing?"),
            'The full Tenant 170 question must route to listing.lease_terms'
        );
    }

    /**
     * Bare "lease terms" → listing.lease_terms
     * Added after the more-specific pre-existing phrases so "existing lease terms
     * on this property" still matches the explicit entry first (same key, no change).
     */
    public function test_bare_lease_terms_routes_to_lease_terms(): void
    {
        $this->assertSame(
            'listing.lease_terms',
            $this->detectListingFieldKey('lease terms'),
            'bare "lease terms" must route to listing.lease_terms'
        );
    }

    /**
     * "rental requirements" → listing.lease_terms
     */
    public function test_tenant_rental_requirements_routes_to_lease_terms(): void
    {
        $this->assertSame(
            'listing.lease_terms',
            $this->detectListingFieldKey('rental requirements'),
            '"rental requirements" must route to listing.lease_terms'
        );
    }

    /**
     * COLLISION GUARD — "existing lease terms on this property" is a pre-existing
     * explicit phrase under listing.lease_terms (added before this fix). After adding
     * bare "lease terms" to the same entry, the pre-existing explicit phrase still
     * fires first (declaration order) and routes to listing.lease_terms — no change.
     */
    public function test_collision_guard_existing_lease_terms_on_property_routes_to_lease_terms(): void
    {
        $detected = $this->detectListingFieldKey('existing lease terms on this property');

        $this->assertSame(
            'listing.lease_terms',
            $detected,
            'COLLISION GUARD: "existing lease terms on this property" must still route to '
            . 'listing.lease_terms — the explicit pre-existing phrase fires before bare "lease terms"'
        );
    }

    /**
     * COLLISION GUARD — "existing tenant lease terms" routes via the FAQ map
     * (detectFaqFieldKey) to faq_answers.existing_tenant_lease_terms; the listing map
     * also routes it to listing.lease_terms via bare "lease terms" substring match,
     * but faq questions are handled by detectFaqFieldKey, not detectListingFieldKey.
     */
    public function test_collision_guard_existing_tenant_lease_terms_routes_to_faq_key(): void
    {
        $faqDetected = $this->detectFaqFieldKey('existing tenant lease terms on this property');
        $this->assertSame(
            'faq_answers.existing_tenant_lease_terms',
            $faqDetected,
            'COLLISION GUARD: "existing tenant lease terms" must route to '
            . 'faq_answers.existing_tenant_lease_terms via detectFaqFieldKey()'
        );
    }

    // =========================================================================
    // Section 5 — Landlord location routing (listing.address)
    // =========================================================================

    /**
     * "tell me about the location" → listing.address
     * The primary failure phrase for Landlord 71.
     */
    public function test_landlord_tell_me_about_the_location_routes_to_address(): void
    {
        $this->assertSame(
            'listing.address',
            $this->detectListingFieldKey('tell me about the location'),
            '"tell me about the location" must route to listing.address'
        );
    }

    /**
     * "where is this property located" → listing.address (pre-existing; must not be broken)
     */
    public function test_landlord_where_is_this_property_located_still_routes_to_address(): void
    {
        $this->assertSame(
            'listing.address',
            $this->detectListingFieldKey('where is this property located'),
            '"where is this property located" (pre-existing) must still route to listing.address'
        );
    }

    /**
     * "describe the location of this property" → listing.address
     */
    public function test_landlord_describe_the_location_of_this_property_routes_to_address(): void
    {
        $this->assertSame(
            'listing.address',
            $this->detectListingFieldKey('describe the location of this property'),
            '"describe the location of this property" must route to listing.address'
        );
    }

    /**
     * "describe the neighborhood" must NOT have been broken — still routes to
     * faq_answers.neighborhood_character via the FAQ map.
     */
    public function test_landlord_describe_the_neighborhood_still_routes_via_faq_key(): void
    {
        $faqKey = $this->detectFaqFieldKey('describe the neighborhood');

        $this->assertSame(
            'faq_answers.neighborhood_character',
            $faqKey,
            '"describe the neighborhood" must still route to faq_answers.neighborhood_character (unchanged)'
        );
    }
}
