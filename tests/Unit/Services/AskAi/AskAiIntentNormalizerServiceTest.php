<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiResponseContractService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiIntentNormalizerServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits, no HTTP calls.
 * OpenAiClientService and AskAiResponseContractService are mocked via createMock().
 *
 * Test coverage (cases A–K):
 *   A. normalize() returns the correct canonical key when OpenAI matches a known paraphrase.
 *   B. normalize() returns null when OpenAI returns 'unknown'.
 *   C. normalize() returns null when OpenAI returns a key not in the provided list (hallucination guard).
 *   D. normalize() returns null gracefully when OpenAI throws (timeout / network error).
 *   E. normalize() returns null immediately when the question is empty.
 *   F. normalize() returns null immediately when knownFieldKeys is empty.
 *   G. normalize() returns null when the OpenAI response data does not contain 'normalized_key'.
 *   H. Static governance scan — service file contains no DB facade calls, no direct OpenAI
 *      SDK instantiation, and no hardcoded API keys.
 *   I. Call options — normalize() always passes timeout_seconds=10 and max_tokens=20 to
 *      OpenAiClientService::send(), regardless of the question or keys provided.
 *   J. buildKnownFieldKeys() registry logic:
 *      J1. listing.* paths returned by contractService are included in the result.
 *      J2. Bare 'faq_answers' path is excluded (only leaf faq_answers.* paths are kept).
 *      J3. faq_answers.* leaf paths returned by contractService are included unchanged.
 *      J4. Duplicate paths across contractService output are deduplicated.
 *      J5. Static source scan: all four FAQ config names appear in buildFaqAnswerKeys().
 *   K. Abstract/figurative roof phrases normalize to faq_answers.roof_age_and_condition
 *      when OpenAI resolves them correctly (mocked), and the improved prompt contains the
 *      informal-language instruction and roof metaphor examples.
 */
class AskAiIntentNormalizerServiceTest extends TestCase
{
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiIntentNormalizerService.php';
    }

    /**
     * Build a minimal set of known field keys covering both listing.* and faq_answers.* paths.
     *
     * @return string[]
     */
    private function makeKnownFieldKeys(): array
    {
        return [
            'listing.bedrooms',
            'listing.bathrooms',
            'listing.year_built',
            'listing.hoa_fee',
            'listing.rent_amount',
            'listing.pets_allowed',
            'listing.pool',
            'listing.square_feet',
            'listing.asking_price',
            'faq_answers.hvac_system_age',
            'faq_answers.roof_age_and_condition',
            'faq_answers.water_heater_age_type',
            'faq_answers.average_utility_costs',
            'faq_answers.known_defects_issues',
        ];
    }

    /**
     * Build a mock OpenAiClientService that returns the given normalized_key in data.
     *
     * @param  string|null $normalizedKey  The value for the 'normalized_key' field in the response.
     * @return OpenAiClientService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeClientMock(?string $normalizedKey)
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->method('send')->willReturn([
            'data'              => ['normalized_key' => $normalizedKey],
            'model'             => 'gpt-4o',
            'prompt_tokens'     => 10,
            'completion_tokens' => 5,
            'total_tokens'      => 15,
            'api_request_id'    => null,
        ]);
        return $mock;
    }

    /**
     * Build a mock OpenAiClientService that returns data without 'normalized_key'.
     *
     * @return OpenAiClientService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeClientMockWithMissingKey()
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->method('send')->willReturn([
            'data'           => ['some_other_key' => 'irrelevant'],
            'model'          => 'gpt-4o',
            'prompt_tokens'  => 10,
            'total_tokens'   => 10,
            'api_request_id' => null,
        ]);
        return $mock;
    }

    /**
     * Build a mock OpenAiClientService that throws a RuntimeException (simulates timeout).
     *
     * @return OpenAiClientService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeClientMockThatThrows()
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->method('send')->willThrowException(
            new \RuntimeException('Connection timed out after 10 seconds.')
        );
        return $mock;
    }

    /**
     * Build a mock AskAiResponseContractService (not used in normalize() tests — only in registry tests).
     *
     * @return AskAiResponseContractService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeContractServiceMock()
    {
        return $this->createMock(AskAiResponseContractService::class);
    }

    private function makeNormalizer(OpenAiClientService $client): AskAiIntentNormalizerService
    {
        return new AskAiIntentNormalizerService($client, $this->makeContractServiceMock());
    }

    // =========================================================================
    // Case A — Known paraphrases normalize to the correct field key
    // =========================================================================

    public function test_case_A_hvac_paraphrase_normalizes_to_hvac_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.hvac_system_age');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Does the A/C work well?', $keys);

        $this->assertSame('faq_answers.hvac_system_age', $result);
    }

    public function test_case_A_mechanical_systems_paraphrase_normalizes_to_hvac_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.hvac_system_age');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Tell me about the mechanical systems.', $keys);

        $this->assertSame('faq_answers.hvac_system_age', $result);
    }

    public function test_case_A_roof_paraphrase_normalizes_to_roof_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.roof_age_and_condition');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Is the roof in good shape?', $keys);

        $this->assertSame('faq_answers.roof_age_and_condition', $result);
    }

    public function test_case_A_utility_cost_paraphrase_normalizes_to_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.average_utility_costs');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('What do the utilities typically cost per month?', $keys);

        $this->assertSame('faq_answers.average_utility_costs', $result);
    }

    public function test_case_A_bedrooms_paraphrase_normalizes_to_listing_key(): void
    {
        $client     = $this->makeClientMock('listing.bedrooms');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('How many rooms does this place have?', $keys);

        $this->assertSame('listing.bedrooms', $result);
    }

    public function test_case_A_pool_paraphrase_normalizes_to_listing_pool_key(): void
    {
        $client     = $this->makeClientMock('listing.pool');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Does this home come with a swimming pool?', $keys);

        $this->assertSame('listing.pool', $result);
    }

    // =========================================================================
    // Case B — OpenAI returns 'unknown' → null
    // =========================================================================

    public function test_case_B_unknown_response_returns_null(): void
    {
        $client     = $this->makeClientMock('unknown');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Who is the best president?', $keys);

        $this->assertNull($result);
    }

    // =========================================================================
    // Case C — Hallucination guard: OpenAI returns a key not in the list → null
    // =========================================================================

    public function test_case_C_hallucinated_key_not_in_known_list_returns_null(): void
    {
        $client     = $this->makeClientMock('listing.invented_field_that_does_not_exist');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Some question.', $keys);

        $this->assertNull($result);
    }

    public function test_case_C_arbitrary_string_not_in_known_list_returns_null(): void
    {
        $client     = $this->makeClientMock('faq_answers.fake_faq_key');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Some question.', $keys);

        $this->assertNull($result);
    }

    // =========================================================================
    // Case D — OpenAI timeout / exception → null gracefully (no crash)
    // =========================================================================

    public function test_case_D_openai_timeout_returns_null_without_throwing(): void
    {
        $client     = $this->makeClientMockThatThrows();
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Does the A/C work well?', $keys);

        $this->assertNull($result);
    }

    public function test_case_D_generic_throwable_returns_null_without_throwing(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->method('send')->willThrowException(new \Error('Unexpected fatal error'));

        $normalizer = $this->makeNormalizer($mock);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Is there a pool?', $keys);

        $this->assertNull($result);
    }

    // =========================================================================
    // Case E — Empty question → null immediately (OpenAI not called)
    // =========================================================================

    public function test_case_E_empty_question_returns_null_without_calling_openai(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->expects($this->never())->method('send');

        $normalizer = $this->makeNormalizer($mock);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('', $keys);

        $this->assertNull($result);
    }

    // =========================================================================
    // Case F — Empty knownFieldKeys → null immediately (OpenAI not called)
    // =========================================================================

    public function test_case_F_empty_known_field_keys_returns_null_without_calling_openai(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->expects($this->never())->method('send');

        $normalizer = $this->makeNormalizer($mock);

        $result = $normalizer->normalize('Does the A/C work well?', []);

        $this->assertNull($result);
    }

    // =========================================================================
    // Case G — OpenAI response missing 'normalized_key' → null
    // =========================================================================

    public function test_case_G_response_missing_normalized_key_returns_null(): void
    {
        $client     = $this->makeClientMockWithMissingKey();
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Tell me about the A/C system.', $keys);

        $this->assertNull($result);
    }

    public function test_case_G_response_with_empty_string_normalized_key_returns_null(): void
    {
        $client     = $this->makeClientMock('');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Some question.', $keys);

        $this->assertNull($result);
    }

    public function test_case_G_response_with_null_normalized_key_returns_null(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->method('send')->willReturn([
            'data'           => ['normalized_key' => null],
            'model'          => 'gpt-4o',
            'prompt_tokens'  => 10,
            'total_tokens'   => 10,
            'api_request_id' => null,
        ]);

        $normalizer = $this->makeNormalizer($mock);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('Some question.', $keys);

        $this->assertNull($result);
    }

    // =========================================================================
    // Case H — Static governance scan
    // =========================================================================

    public function test_case_H_service_file_exists(): void
    {
        $this->assertFileExists(
            $this->serviceFilePath(),
            'AskAiIntentNormalizerService file does not exist at expected path'
        );
    }

    public function test_case_H_service_file_contains_no_db_facade_calls(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'DB::table(',
            'DB::select(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
            'DB::statement(',
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            '->insert(',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiIntentNormalizerService must not contain DB call '{$term}'"
            );
        }
    }

    public function test_case_H_service_file_contains_no_direct_openai_sdk_instantiation(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'use OpenAI\\',
            'use OpenAi\\',
            'OpenAI::',
            'new OpenAI(',
            'use GuzzleHttp\\',
            'Http::post(',
            'Http::get(',
            'curl_exec(',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiIntentNormalizerService must not directly instantiate or import '{$term}'"
            );
        }
    }

    public function test_case_H_service_file_contains_no_hardcoded_api_keys(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'sk-',
            'OPENAI_API_KEY',
            'openai_api_key',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiIntentNormalizerService must not contain hardcoded API key pattern '{$term}'"
            );
        }
    }

    public function test_case_H_service_file_contains_no_answer_builder_method_calls(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        // The normalizer must not call answer-building service methods. Scan
        // non-comment code lines only; the word 'answer' may appear in the
        // governed prompt instruction string and is not itself prohibited.
        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*')
                    && !str_starts_with($trimmed, '//')
                    && !str_starts_with($trimmed, '/*');
            }
        ));

        // These method-call patterns would indicate the normalizer is building
        // a final listing answer — which it must never do.
        $prohibited = [
            'buildPromptPackage(',
            'buildFinalResponse(',
            'promptBuilder->',
            'finalResponseBuilder->',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiIntentNormalizerService must not call answer-building method '{$term}'"
            );
        }
    }

    // =========================================================================
    // Case I — Call options: timeout_seconds=10 and max_tokens=20 are enforced
    // =========================================================================

    public function test_case_I_normalize_passes_timeout_seconds_10_to_send(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return isset($options['timeout_seconds']) && $options['timeout_seconds'] === 10;
                })
            )
            ->willReturn([
                'data'           => ['normalized_key' => 'listing.bedrooms'],
                'model'          => 'gpt-4o',
                'prompt_tokens'  => 5,
                'total_tokens'   => 7,
                'api_request_id' => null,
            ]);

        $normalizer = $this->makeNormalizer($mock);
        $result     = $normalizer->normalize('How many bedrooms?', $this->makeKnownFieldKeys());

        $this->assertSame('listing.bedrooms', $result);
    }

    public function test_case_I_normalize_passes_max_tokens_20_to_send(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return isset($options['max_tokens']) && $options['max_tokens'] === 20;
                })
            )
            ->willReturn([
                'data'           => ['normalized_key' => 'listing.hoa_fee'],
                'model'          => 'gpt-4o',
                'prompt_tokens'  => 5,
                'total_tokens'   => 7,
                'api_request_id' => null,
            ]);

        $normalizer = new AskAiIntentNormalizerService($mock, $this->makeContractServiceMock());
        $keys       = array_merge($this->makeKnownFieldKeys(), ['listing.hoa_fee']);
        $result     = $normalizer->normalize('Is there an HOA fee?', $keys);

        $this->assertSame('listing.hoa_fee', $result);
    }

    public function test_case_I_normalize_passes_both_call_options_in_single_call(): void
    {
        $mock = $this->createMock(OpenAiClientService::class);
        $mock->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return isset($options['timeout_seconds'], $options['max_tokens'])
                        && $options['timeout_seconds'] === 10
                        && $options['max_tokens'] === 20;
                })
            )
            ->willReturn([
                'data'           => ['normalized_key' => 'faq_answers.hvac_system_age'],
                'model'          => 'gpt-4o',
                'prompt_tokens'  => 5,
                'total_tokens'   => 7,
                'api_request_id' => null,
            ]);

        $normalizer = $this->makeNormalizer($mock);
        $normalizer->normalize('Tell me about the A/C.', $this->makeKnownFieldKeys());
    }

    public function test_case_I_service_file_declares_timeout_seconds_10_in_call_options(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            "'timeout_seconds' => 10",
            $content,
            'AskAiIntentNormalizerService must declare timeout_seconds => 10 in CALL_OPTIONS'
        );
    }

    public function test_case_I_service_file_declares_max_tokens_20_in_call_options(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            "'max_tokens'      => 20",
            $content,
            'AskAiIntentNormalizerService must declare max_tokens => 20 in CALL_OPTIONS'
        );
    }

    // =========================================================================
    // Case J — buildKnownFieldKeys() registry logic
    // =========================================================================

    /**
     * Build a mock AskAiResponseContractService that returns the given listing paths.
     *
     * @param  string[] $paths
     * @return AskAiResponseContractService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeContractServiceMockWithPaths(array $paths)
    {
        $mock = $this->createMock(AskAiResponseContractService::class);
        $mock->method('getListingFactsAllowedPaths')->willReturn($paths);
        return $mock;
    }

    /**
     * Build an AskAiIntentNormalizerService with a mocked contractService returning the given paths.
     */
    private function makeNormalizerWithPaths(array $contractPaths): AskAiIntentNormalizerService
    {
        $clientMock  = $this->createMock(OpenAiClientService::class);
        $contractMock = $this->makeContractServiceMockWithPaths($contractPaths);
        return new AskAiIntentNormalizerService($clientMock, $contractMock);
    }

    // ── J1 ── listing.* paths from contractService are included ───────────────

    public function test_case_J1_listing_star_paths_from_contract_service_are_included(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'listing.bedrooms',
            'listing.hoa_fee',
            'listing.asking_price',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        $this->assertContains('listing.bedrooms', $keys);
        $this->assertContains('listing.hoa_fee', $keys);
        $this->assertContains('listing.asking_price', $keys);
    }

    public function test_case_J1_listing_star_paths_are_preserved_exactly(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'listing.rent_amount',
            'listing.pets_allowed',
            'listing.year_built',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        foreach (['listing.rent_amount', 'listing.pets_allowed', 'listing.year_built'] as $path) {
            $this->assertContains($path, $keys, "Expected path '{$path}' to be in buildKnownFieldKeys() result");
        }
    }

    // ── J2 ── Bare 'faq_answers' path is excluded ────────────────────────────

    public function test_case_J2_bare_faq_answers_path_is_excluded(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'listing.bedrooms',
            'faq_answers',
            'listing.hoa_fee',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        $this->assertNotContains(
            'faq_answers',
            $keys,
            "buildKnownFieldKeys() must exclude the bare 'faq_answers' umbrella path"
        );
    }

    public function test_case_J2_bare_faq_answers_excluded_while_listing_paths_kept(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'faq_answers',
            'listing.bedrooms',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        $this->assertNotContains('faq_answers', $keys);
        $this->assertContains('listing.bedrooms', $keys);
    }

    // ── J3 ── faq_answers.* leaf paths from contractService are included ──────

    public function test_case_J3_faq_answers_leaf_paths_from_contract_service_are_included(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'listing.bedrooms',
            'faq_answers.hvac_system_age',
            'faq_answers.roof_age_and_condition',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        $this->assertContains('faq_answers.hvac_system_age', $keys);
        $this->assertContains('faq_answers.roof_age_and_condition', $keys);
    }

    public function test_case_J3_faq_answers_leaf_path_passes_bare_faq_answers_filter(): void
    {
        // 'faq_answers.some_key' should pass the filter (it !== 'faq_answers')
        // while 'faq_answers' (bare) should be excluded.
        $normalizer = $this->makeNormalizerWithPaths([
            'faq_answers',
            'faq_answers.some_key',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        $this->assertNotContains('faq_answers', $keys);
        $this->assertContains('faq_answers.some_key', $keys);
    }

    // ── J4 ── Duplicates across contractService output are deduplicated ────────

    public function test_case_J4_duplicate_paths_from_contract_service_are_deduplicated(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'listing.bedrooms',
            'listing.bedrooms',
            'listing.hoa_fee',
            'listing.hoa_fee',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        $uniqueKeys = array_unique($keys);
        $this->assertSame(
            count($uniqueKeys),
            count($keys),
            'buildKnownFieldKeys() must not contain duplicate paths'
        );
    }

    public function test_case_J4_result_is_re_indexed_array(): void
    {
        $normalizer = $this->makeNormalizerWithPaths([
            'listing.bedrooms',
            'faq_answers',
            'listing.hoa_fee',
        ]);

        $keys = $normalizer->buildKnownFieldKeys();

        // array_values() ensures sequential 0-based integer keys.
        $this->assertSame(array_values($keys), $keys);
    }

    // ── J5 ── Static source scan: all four FAQ config names in buildFaqAnswerKeys() ──

    public function test_case_J5_ai_faq_seller_config_scanned_in_build_faq_answer_keys(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            'ai_faq_seller',
            $content,
            "buildFaqAnswerKeys() must scan the 'ai_faq_seller' config"
        );
    }

    public function test_case_J5_ai_faq_landlord_config_scanned_in_build_faq_answer_keys(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            'ai_faq_landlord',
            $content,
            "buildFaqAnswerKeys() must scan the 'ai_faq_landlord' config"
        );
    }

    public function test_case_J5_ai_faq_buyer_config_scanned_in_build_faq_answer_keys(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            'ai_faq_buyer',
            $content,
            "buildFaqAnswerKeys() must scan the 'ai_faq_buyer' config"
        );
    }

    public function test_case_J5_tenant_ai_faq_config_scanned_in_build_faq_answer_keys(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            'tenant_ai_faq',
            $content,
            "buildFaqAnswerKeys() must scan the 'tenant_ai_faq' config"
        );
    }

    // =========================================================================
    // Case K — Abstract/figurative roof phrases resolve to roof faq key
    //
    // These tests use mocked OpenAI responses to verify that normalize() correctly
    // returns faq_answers.roof_age_and_condition when OpenAI resolves an abstract
    // roof metaphor to that key. The hallucination guard and all governance rules
    // remain in effect: the mocked key must be present in knownFieldKeys or
    // normalize() would return null.
    //
    // Live verification (with a paid-tier OpenAI API key) is tracked in #2283.
    // =========================================================================

    public function test_case_K1_solid_covering_overhead_resolves_to_roof_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.roof_age_and_condition');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('solid covering overhead', $keys);

        $this->assertSame('faq_answers.roof_age_and_condition', $result);
    }

    public function test_case_K2_top_covering_of_the_house_resolves_to_roof_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.roof_age_and_condition');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('top covering of the house', $keys);

        $this->assertSame('faq_answers.roof_age_and_condition', $result);
    }

    public function test_case_K3_covering_above_the_home_resolves_to_roof_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.roof_age_and_condition');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('covering above the home', $keys);

        $this->assertSame('faq_answers.roof_age_and_condition', $result);
    }

    public function test_case_K4_overhead_structure_resolves_to_roof_faq_key(): void
    {
        $client     = $this->makeClientMock('faq_answers.roof_age_and_condition');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('overhead structure', $keys);

        $this->assertSame('faq_answers.roof_age_and_condition', $result);
    }

    public function test_case_K5_hallucination_guard_still_applies_for_abstract_phrase(): void
    {
        // Even if OpenAI returns a roof-sounding key that isn't in the list,
        // the hallucination guard must reject it and return null.
        $client     = $this->makeClientMock('faq_answers.roof_material_invented');
        $normalizer = $this->makeNormalizer($client);
        $keys       = $this->makeKnownFieldKeys();

        $result = $normalizer->normalize('top covering of the house', $keys);

        $this->assertNull($result);
    }

    public function test_case_K6_prompt_contains_informal_language_instruction(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            'informal, colloquial, or figurative language',
            $content,
            'buildPayload() must instruct OpenAI to handle informal/figurative language'
        );
    }

    public function test_case_K7_prompt_contains_roof_metaphor_examples(): void
    {
        $content = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString(
            'solid covering overhead',
            $content,
            'buildPayload() must include roof metaphor examples to guide OpenAI resolution'
        );
        $this->assertStringContainsString(
            'top covering of the house',
            $content,
            'buildPayload() must include roof metaphor examples to guide OpenAI resolution'
        );
        $this->assertStringContainsString(
            'overhead structure',
            $content,
            'buildPayload() must include roof metaphor examples to guide OpenAI resolution'
        );
    }

    public function test_case_K8_prompt_roof_examples_do_not_hardcode_field_key(): void
    {
        // The prompt must reference the roof *concept*, not hardcode the field key name.
        // Hardcoding would bypass the hallucination guard for future key-list changes.
        $content     = file_get_contents($this->serviceFilePath());
        $payloadLines = [];

        $inPayload = false;
        foreach (explode("\n", $content) as $line) {
            if (str_contains($line, 'buildPayload(')) {
                $inPayload = true;
            }
            if ($inPayload) {
                $payloadLines[] = $line;
            }
            // Stop after closing brace of buildPayload
            if ($inPayload && trim($line) === '}' && count($payloadLines) > 3) {
                break;
            }
        }

        $payloadSource = implode("\n", $payloadLines);

        // The roof examples in the prompt must use a generic description ("roof-related field key")
        // not a hard-coded canonical path (that would bypass the hallucination guard).
        $this->assertStringNotContainsString(
            "'faq_answers.roof_age_and_condition'",
            $payloadSource,
            'buildPayload() must not hardcode faq_answers.roof_age_and_condition in the prompt — it bypasses the hallucination guard'
        );
    }
}
