<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiResponseContractService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiResponseContractServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * AskAiResponseContractService is stateless and requires no mocking.
 *
 * Test coverage (cases A–O):
 *   A. Each supported type returns 'contract_ready' when required context is present
 *   B. Missing required context returns 'insufficient_context' with missing_required_sources populated
 *   C. 'prohibited' type always returns 'refusal_required'
 *   D. Unknown type returns 'unsupported'
 *   E. 'contract_version' is always 'ASK_AI_RESPONSE_CONTRACT_V1' in every response
 *   F. No OpenAI or HTTP calls exist in the service file (static grep on non-comment lines)
 *   G. No write calls exist in the service file (static grep on non-comment lines)
 *   H. Source attribution rule is required in every 'contract_ready' response that uses platform data
 *   I. Educational questions carry the 'General Educational Information' disclosure label
 *   M. listing_facts — full coverage (allowed_context, response_rules, disclosures, shape)
 *   N. FAQ shape compatibility — contract service handles both legacy raw-string and enriched object
 *      shape faq_answers entries without error; it never reads individual entry values
 *   O. Normalizer–contract path compatibility — real listing_facts contract contains the umbrella
 *      or leaf paths required by AskAiIntentNormalizerService; narrowing guard can match them.
 *      Proves end-to-end that faq_answers leaf paths returned by the normalizer are permissible
 *      under the real contract without mocking AskAiResponseContractService.
 */
class AskAiResponseContractServiceTest extends TestCase
{
    /**
     * The required keys present in every contract response, regardless of status.
     */
    private const REQUIRED_CONTRACT_KEYS = [
        'success',
        'status',
        'question_type',
        'allowed_context',
        'required_sources',
        'missing_required_sources',
        'response_rules',
        'required_disclosures',
        'refusal_template',
        'contract_version',
    ];

    /**
     * All nine supported question types defined by the spec.
     */
    private const ALL_QUESTION_TYPES = [
        'property_standout',
        'suited_audience',
        'buyer_tenant_match',
        'compatibility_signals',
        'missing_data',
        'marketing_angles',
        'educational',
        'listing_facts',
        'prohibited',
    ];

    /**
     * Supported types that require platform data (non-educational, non-prohibited).
     */
    private const PLATFORM_DATA_TYPES = [
        'property_standout',
        'suited_audience',
        'buyer_tenant_match',
        'compatibility_signals',
        'missing_data',
        'marketing_angles',
        'listing_facts',
    ];

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiResponseContractService.php';
    }

    private function makeService(): AskAiResponseContractService
    {
        return new AskAiResponseContractService();
    }

    /**
     * Build a minimal context stub that satisfies the required sources for a given type.
     */
    private function makeContextFor(string $questionType): array
    {
        return match ($questionType) {
            'property_standout', 'suited_audience', 'marketing_angles' => [
                'property_intelligence' => [
                    'property_highlights'       => ['Pool', 'Garage'],
                    'property_strengths'        => ['Pool', 'Garage'],
                    'property_positioning'      => 'Move-Up Home',
                    'property_target_audiences' => ['Move-Up Families'],
                    'property_personality_tags' => ['Outdoor Living'],
                    'property_story'            => 'A great home.',
                ],
                'listing' => [
                    'listing_id'    => 1,
                    'listing_type'  => 'seller',
                    'property_type' => 'Single Family',
                ],
            ],
            'buyer_tenant_match', 'compatibility_signals' => [
                'compatibility' => [
                    'overall_score'               => 85.0,
                    'compatibility_highlights'    => ['Price match'],
                    'compatibility_warnings'      => [],
                    'compatibility_summary_json'  => ['result' => 'strong'],
                    'physical_match_score'        => 88.0,
                    'financial_match_score'       => 82.0,
                    'terms_match_score'           => 79.0,
                    'location_match_score'        => 90.0,
                    'compatibility_narrative'     => 'Strong match.',
                ],
            ],
            'missing_data' => [
                'listing' => [
                    'listing_id'     => 1,
                    'listing_type'   => 'seller',
                    'property_type'  => 'Single Family',
                    'listing_status' => 'approved',
                ],
                'missing_sources' => ['property_intelligence'],
            ],
            'listing_facts' => [
                'listing' => [
                    'listing_id'    => 1,
                    'listing_type'  => 'seller',
                    'property_type' => 'Single Family',
                    'bedrooms'      => '4',
                    'bathrooms'     => '2',
                    'asking_price'  => '450000',
                    'square_feet'   => '2200',
                    'year_built'    => '2005',
                    'pool'          => '1',
                    'hoa_fee'       => '200',
                    'pets_allowed'  => '1',
                ],
                'faq_answers' => [
                    'roof_age'   => 'The roof was replaced in 2020.',
                    'hvac_notes' => 'HVAC was serviced in 2024.',
                ],
            ],
            'educational' => [],
            default => [],
        };
    }

    // =========================================================================
    // Case A — each supported type returns 'contract_ready' with required context
    // =========================================================================

    public function test_case_A_property_standout_returns_contract_ready_with_required_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', $this->makeContextFor('property_standout'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertSame('property_standout', $result['question_type']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_suited_audience_returns_contract_ready_with_required_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('suited_audience', $this->makeContextFor('suited_audience'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_buyer_tenant_match_returns_contract_ready_with_required_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', $this->makeContextFor('buyer_tenant_match'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_compatibility_signals_returns_contract_ready_with_required_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('compatibility_signals', $this->makeContextFor('compatibility_signals'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_missing_data_returns_contract_ready_with_required_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('missing_data', $this->makeContextFor('missing_data'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_marketing_angles_returns_contract_ready_with_required_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('marketing_angles', $this->makeContextFor('marketing_angles'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_educational_returns_contract_ready_with_empty_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_A_listing_facts_returns_contract_ready_with_listing_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertSame('listing_facts', $result['question_type']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    // =========================================================================
    // Case B — missing required context returns 'insufficient_context'
    // =========================================================================

    public function test_case_B_property_standout_returns_insufficient_context_when_property_intelligence_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    public function test_case_B_suited_audience_returns_insufficient_context_when_property_intelligence_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('suited_audience', ['property_intelligence' => null]);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    public function test_case_B_buyer_tenant_match_returns_insufficient_context_when_compatibility_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('compatibility', $result['missing_required_sources']);
    }

    public function test_case_B_compatibility_signals_returns_insufficient_context_when_compatibility_null(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('compatibility_signals', ['compatibility' => null]);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('compatibility', $result['missing_required_sources']);
    }

    public function test_case_B_missing_data_returns_insufficient_context_when_listing_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('missing_data', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('listing', $result['missing_required_sources']);
    }

    public function test_case_B_marketing_angles_returns_insufficient_context_when_property_intelligence_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('marketing_angles', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    public function test_case_B_listing_facts_returns_insufficient_context_when_listing_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('listing', $result['missing_required_sources']);
    }

    public function test_case_B_listing_facts_returns_insufficient_context_when_listing_null(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', ['listing' => null]);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('listing', $result['missing_required_sources']);
    }

    public function test_case_B_insufficient_context_carries_all_required_contract_keys(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', []);

        foreach (self::REQUIRED_CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in insufficient_context response");
        }
    }

    // =========================================================================
    // Case C — 'prohibited' always returns 'refusal_required'
    // =========================================================================

    public function test_case_C_prohibited_returns_refusal_required_with_empty_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('prohibited', []);

        $this->assertSame('refusal_required', $result['status']);
    }

    public function test_case_C_prohibited_returns_refusal_required_even_with_full_context(): void
    {
        $service = $this->makeService();
        $fullContext = [
            'property_intelligence' => ['property_highlights' => ['Pool']],
            'compatibility'         => ['overall_score' => 90.0],
            'listing'               => ['listing_id' => 1],
        ];

        $result = $service->buildContract('prohibited', $fullContext);

        $this->assertSame('refusal_required', $result['status']);
    }

    public function test_case_C_prohibited_refusal_template_is_non_null_string(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('prohibited', []);

        $this->assertNotNull($result['refusal_template']);
        $this->assertIsString($result['refusal_template']);
        $this->assertNotEmpty($result['refusal_template']);
    }

    public function test_case_C_prohibited_carries_all_required_contract_keys(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('prohibited', []);

        foreach (self::REQUIRED_CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in refusal_required response");
        }
    }

    // =========================================================================
    // Case D — unknown type returns 'unsupported'
    // =========================================================================

    public function test_case_D_unknown_type_returns_unsupported(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('unknown_type', []);

        $this->assertSame('unsupported', $result['status']);
        $this->assertSame('unknown_type', $result['question_type']);
    }

    public function test_case_D_empty_string_type_returns_unsupported(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('', []);

        $this->assertSame('unsupported', $result['status']);
    }

    public function test_case_D_unsupported_carries_all_required_contract_keys(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('not_a_real_type', []);

        foreach (self::REQUIRED_CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in unsupported response");
        }
    }

    // =========================================================================
    // Case E — 'contract_version' is always 'ASK_AI_RESPONSE_CONTRACT_V1'
    // =========================================================================

    public function test_case_E_contract_version_constant_is_correct(): void
    {
        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', AskAiResponseContractService::CONTRACT_VERSION);
    }

    public function test_case_E_contract_version_present_in_contract_ready_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $result['contract_version']);
    }

    public function test_case_E_contract_version_present_in_insufficient_context_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', []);

        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $result['contract_version']);
    }

    public function test_case_E_contract_version_present_in_refusal_required_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('prohibited', []);

        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $result['contract_version']);
    }

    public function test_case_E_contract_version_present_in_unsupported_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('xyz_unknown', []);

        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $result['contract_version']);
    }

    // =========================================================================
    // Case F — No OpenAI or HTTP calls exist in the service file
    // =========================================================================

    public function test_case_F_service_file_contains_no_openai_or_http_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedImports = [
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'ChatGPT::',
        ];

        foreach ($prohibitedImports as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not import or call '{$term}'"
            );
        }

        $prohibitedHttpCalls = [
            'Http::post',
            'Http::get',
            'Http::put',
            'curl_exec',
            'file_get_contents(\'http',
            'file_get_contents("http',
        ];

        foreach ($prohibitedHttpCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not contain HTTP call '{$term}'"
            );
        }
    }

    // =========================================================================
    // Case G — No write calls exist in the service file
    // =========================================================================

    public function test_case_G_service_file_contains_no_write_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedWriteCalls = [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            '->insert(',
            'DB::statement(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
        ];

        foreach ($prohibitedWriteCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not contain write call '{$term}'"
            );
        }
    }

    // =========================================================================
    // Case H — Source attribution rule present in every 'contract_ready' response
    //           that uses platform data (non-educational, non-prohibited)
    // =========================================================================

    public function test_case_H_contract_ready_property_standout_has_attribution_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', $this->makeContextFor('property_standout'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertNotEmpty($result['required_sources']);

        $rulesText = implode(' ', $result['response_rules']);
        $this->assertStringContainsStringIgnoringCase(
            'attribut',
            $rulesText,
            "property_standout contract_ready response must include an attribution rule"
        );
    }

    public function test_case_H_contract_ready_suited_audience_has_attribution_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('suited_audience', $this->makeContextFor('suited_audience'));

        $this->assertSame('contract_ready', $result['status']);
        $rulesText = implode(' ', $result['response_rules']);
        $this->assertStringContainsStringIgnoringCase('attribut', $rulesText);
    }

    public function test_case_H_contract_ready_buyer_tenant_match_has_attribution_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', $this->makeContextFor('buyer_tenant_match'));

        $this->assertSame('contract_ready', $result['status']);
        $rulesText = implode(' ', $result['response_rules']);
        $this->assertStringContainsStringIgnoringCase('attribut', $rulesText);
    }

    public function test_case_H_contract_ready_compatibility_signals_has_attribution_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('compatibility_signals', $this->makeContextFor('compatibility_signals'));

        $this->assertSame('contract_ready', $result['status']);
        $rulesText = implode(' ', $result['response_rules']);
        $this->assertStringContainsStringIgnoringCase('attribut', $rulesText);
    }

    public function test_case_H_contract_ready_marketing_angles_has_attribution_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('marketing_angles', $this->makeContextFor('marketing_angles'));

        $this->assertSame('contract_ready', $result['status']);
        $rulesText = implode(' ', $result['response_rules']);
        $this->assertStringContainsStringIgnoringCase('attribut', $rulesText);
    }

    public function test_case_H_contract_ready_missing_data_has_attribution_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('missing_data', $this->makeContextFor('missing_data'));

        $this->assertSame('contract_ready', $result['status']);
        $rulesText = implode(' ', $result['response_rules']);
        $this->assertStringContainsStringIgnoringCase(
            'attribut',
            $rulesText,
            "missing_data contract_ready response must include an attribution rule"
        );
    }

    public function test_case_H_all_platform_data_types_have_non_empty_required_sources(): void
    {
        $service = $this->makeService();
        foreach (self::PLATFORM_DATA_TYPES as $type) {
            $result = $service->buildContract($type, $this->makeContextFor($type));
            $this->assertNotEmpty(
                $result['required_sources'],
                "Question type '{$type}' must declare required_sources for source attribution"
            );
        }
    }

    // =========================================================================
    // Case I — Educational carries 'General Educational Information' disclosure
    // =========================================================================

    public function test_case_I_educational_returns_contract_ready(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertSame('contract_ready', $result['status']);
    }

    public function test_case_I_educational_carries_general_educational_information_disclosure(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $disclosuresText = implode(' ', $result['required_disclosures']);
        $this->assertStringContainsString(
            'General Educational Information',
            $disclosuresText,
            "Educational responses must carry the 'General Educational Information' disclosure label"
        );
    }

    public function test_case_I_educational_has_no_required_sources(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertEmpty($result['required_sources']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_I_educational_refusal_template_is_null(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertNull($result['refusal_template']);
    }

    public function test_case_I_educational_has_response_rules(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertNotEmpty($result['response_rules']);
    }

    // =========================================================================
    // Case J — 'success' field is true only for contract_ready; false otherwise
    // =========================================================================

    public function test_case_J_success_is_true_for_contract_ready(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success'], "'success' must be true when status is contract_ready");
    }

    public function test_case_J_success_is_false_for_insufficient_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success'], "'success' must be false when status is insufficient_context");
    }

    public function test_case_J_success_is_false_for_refusal_required(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('prohibited', []);

        $this->assertSame('refusal_required', $result['status']);
        $this->assertFalse($result['success'], "'success' must be false when status is refusal_required");
    }

    public function test_case_J_success_is_false_for_unsupported(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('not_a_known_type', []);

        $this->assertSame('unsupported', $result['status']);
        $this->assertFalse($result['success'], "'success' must be false when status is unsupported");
    }

    public function test_case_J_success_is_true_for_every_contract_ready_type(): void
    {
        $service = $this->makeService();
        foreach (self::PLATFORM_DATA_TYPES as $type) {
            $result = $service->buildContract($type, $this->makeContextFor($type));
            $this->assertTrue(
                $result['success'],
                "'{$type}' with required context must return success=true"
            );
        }

        $educational = $service->buildContract('educational', []);
        $this->assertTrue($educational['success'], "'educational' must return success=true");
    }

    // =========================================================================
    // Case K — buyer_tenant_match allowed_context includes buyer/tenant avatar fields
    // =========================================================================

    public function test_case_K_buyer_tenant_match_allowed_context_includes_buyer_avatar_fields(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', $this->makeContextFor('buyer_tenant_match'));

        $this->assertSame('contract_ready', $result['status']);
        $this->assertContains(
            'buyer_avatar.avatar_type',
            $result['allowed_context'],
            "buyer_tenant_match allowed_context must include buyer_avatar.avatar_type"
        );
        $this->assertContains(
            'buyer_avatar.buyer_match_preferences',
            $result['allowed_context'],
            "buyer_tenant_match allowed_context must include buyer_avatar.buyer_match_preferences"
        );
    }

    public function test_case_K_buyer_tenant_match_allowed_context_includes_tenant_avatar_fields(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', $this->makeContextFor('buyer_tenant_match'));

        $this->assertContains(
            'tenant_avatar.avatar_type',
            $result['allowed_context'],
            "buyer_tenant_match allowed_context must include tenant_avatar.avatar_type"
        );
        $this->assertContains(
            'tenant_avatar.tenant_match_preferences',
            $result['allowed_context'],
            "buyer_tenant_match allowed_context must include tenant_avatar.tenant_match_preferences"
        );
    }

    public function test_case_K_buyer_tenant_match_allowed_context_includes_compatibility_fields(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', $this->makeContextFor('buyer_tenant_match'));

        $this->assertContains(
            'compatibility.compatibility_highlights',
            $result['allowed_context'],
            "buyer_tenant_match allowed_context must include compatibility.compatibility_highlights"
        );
        $this->assertContains(
            'compatibility.compatibility_summary_json',
            $result['allowed_context'],
            "buyer_tenant_match allowed_context must include compatibility.compatibility_summary_json"
        );
    }

    // =========================================================================
    // Case L — Location DNA context paths are included in allowed_context for the
    //           correct question types (property_standout, marketing_angles, educational)
    // =========================================================================

    public function test_case_L_property_standout_allowed_context_includes_nearest_highlights(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', $this->makeContextFor('property_standout'));

        $this->assertContains(
            'location_intelligence.nearest_highlights',
            $result['allowed_context'],
            "property_standout allowed_context must include location_intelligence.nearest_highlights"
        );
    }

    public function test_case_L_property_standout_allowed_context_includes_available_categories(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('property_standout', $this->makeContextFor('property_standout'));

        $this->assertContains(
            'location_intelligence.available_categories',
            $result['allowed_context'],
            "property_standout allowed_context must include location_intelligence.available_categories"
        );
    }

    public function test_case_L_marketing_angles_allowed_context_includes_marketing_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('marketing_angles', $this->makeContextFor('marketing_angles'));

        $this->assertContains(
            'location_intelligence.marketing_context',
            $result['allowed_context'],
            "marketing_angles allowed_context must include location_intelligence.marketing_context"
        );
    }

    public function test_case_L_marketing_angles_allowed_context_includes_available_categories(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('marketing_angles', $this->makeContextFor('marketing_angles'));

        $this->assertContains(
            'location_intelligence.available_categories',
            $result['allowed_context'],
            "marketing_angles allowed_context must include location_intelligence.available_categories"
        );
    }

    public function test_case_L_educational_allowed_context_includes_location_narrative(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertContains(
            'location_intelligence.location_narrative',
            $result['allowed_context'],
            "educational allowed_context must include location_intelligence.location_narrative"
        );
    }

    public function test_case_L_educational_allowed_context_includes_available_categories(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('educational', []);

        $this->assertContains(
            'location_intelligence.available_categories',
            $result['allowed_context'],
            "educational allowed_context must include location_intelligence.available_categories"
        );
    }

    public function test_case_L_location_intelligence_paths_not_in_buyer_tenant_match(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('buyer_tenant_match', $this->makeContextFor('buyer_tenant_match'));

        $locationPaths = array_filter(
            $result['allowed_context'],
            static fn (string $p): bool => str_starts_with($p, 'location_intelligence.')
        );

        $this->assertEmpty(
            $locationPaths,
            "buyer_tenant_match must not include location_intelligence paths in allowed_context"
        );
    }

    public function test_case_L_location_intelligence_registry_allows_educational_type(): void
    {
        $registry = new \App\Services\AskAi\AskAiKnowledgeSourceRegistry();
        $source   = $registry->getSource('location_intelligence');

        $this->assertNotNull($source);
        $this->assertContains(
            'educational',
            $source['allowed_for_question_types'],
            "location_intelligence registry entry must allow educational question type"
        );
    }

    // =========================================================================
    // Case M — listing_facts contract: required sources, allowed context,
    //          disclosures, governance, and response rules
    // =========================================================================

    public function test_case_M_listing_facts_required_source_is_listing_only(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertContains('listing', $result['required_sources'],
            "listing_facts required_sources must include 'listing'");
        $this->assertCount(1, $result['required_sources'],
            "listing_facts must require exactly one source: 'listing'");
    }

    public function test_case_M_listing_facts_allowed_context_includes_core_structural_fields(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $expectedFields = [
            'listing.bedrooms',
            'listing.bathrooms',
            'listing.asking_price',
            'listing.rent_amount',
            'listing.max_rent',
            'listing.square_feet',
            'listing.year_built',
        ];

        foreach ($expectedFields as $field) {
            $this->assertContains($field, $result['allowed_context'],
                "listing_facts allowed_context must include '{$field}'");
        }
    }

    public function test_case_M_listing_facts_allowed_context_includes_amenity_fields(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $expectedFields = [
            'listing.pool',
            'listing.pets_allowed',
            'listing.hoa_fee',
            'listing.showing_instructions',
            'listing.appliances',
            'listing.utilities',
        ];

        foreach ($expectedFields as $field) {
            $this->assertContains($field, $result['allowed_context'],
                "listing_facts allowed_context must include amenity field '{$field}'");
        }
    }

    public function test_case_M_listing_facts_allowed_context_includes_faq_answers(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertContains('faq_answers', $result['allowed_context'],
            "listing_facts allowed_context must include 'faq_answers'");
    }

    public function test_case_M_listing_facts_has_source_attribution_disclosure(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertNotEmpty($result['required_disclosures'],
            "listing_facts must have at least one required_disclosure");

        $disclosureText = implode(' ', $result['required_disclosures']);
        $this->assertStringContainsStringIgnoringCase('listing data', $disclosureText,
            "listing_facts disclosure must reference 'listing data'");
        $this->assertStringContainsStringIgnoringCase('independently', $disclosureText,
            "listing_facts disclosure must advise independent verification");
    }

    public function test_case_M_listing_facts_refusal_template_is_null(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertNull($result['refusal_template'],
            "listing_facts must not have a refusal_template");
    }

    public function test_case_M_listing_facts_response_rules_address_null_field_behavior(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertNotEmpty($result['response_rules']);
        $rulesText = strtolower(implode(' ', $result['response_rules']));

        $this->assertTrue(
            str_contains($rulesText, 'null') || str_contains($rulesText, 'absent') || str_contains($rulesText, 'unavailable'),
            "listing_facts response_rules must address null/missing field behavior"
        );
    }

    public function test_case_M_listing_facts_response_rules_prohibit_protected_class_references(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $rulesText = strtolower(implode(' ', $result['response_rules']));
        $this->assertStringContainsString('protected class', $rulesText,
            "listing_facts response_rules must explicitly prohibit protected class references");
    }

    public function test_case_M_listing_facts_allowed_context_does_not_include_protected_class_paths(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $contextText = strtolower(implode(' ', $result['allowed_context']));

        foreach (['race', 'religion', 'national_origin', 'familial_status', 'disability'] as $term) {
            $this->assertStringNotContainsString($term, $contextText,
                "listing_facts allowed_context must not include protected-class path: '{$term}'");
        }
    }

    public function test_case_M_listing_facts_carries_all_required_contract_keys(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        foreach (self::REQUIRED_CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result,
                "listing_facts contract missing required key '{$key}'");
        }
    }

    public function test_case_M_listing_facts_success_is_true_when_contract_ready(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertTrue($result['success']);
    }

    public function test_case_M_listing_facts_success_is_false_when_insufficient_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', []);

        $this->assertFalse($result['success']);
    }

    // =========================================================================
    // Case N — FAQ shape compatibility (legacy raw-string vs enriched object)
    // =========================================================================

    /**
     * The contract service only checks top-level source presence via isset($context[$source]).
     * It never reads individual faq_answers entry values.
     * Both the legacy string shape and the enriched array shape must therefore produce
     * identical contract_ready outcomes for listing_facts.
     */
    public function test_case_N_listing_facts_contract_ready_with_legacy_string_faq_shape(): void
    {
        $service = $this->makeService();

        $context = [
            'listing' => ['listing_id' => 1, 'listing_type' => 'seller'],
            'faq_answers' => [
                'roof_age'  => 'Replaced in 2020',
                'hvac'      => 'Serviced annually',
            ],
        ];

        $result = $service->buildContract('listing_facts', $context);

        $this->assertSame('contract_ready', $result['status'],
            "listing_facts with legacy string FAQ values must produce contract_ready");
        $this->assertTrue($result['success']);
    }

    public function test_case_N_listing_facts_contract_ready_with_enriched_object_faq_shape(): void
    {
        $service = $this->makeService();

        $context = [
            'listing' => ['listing_id' => 1, 'listing_type' => 'seller'],
            'faq_answers' => [
                'roof_age' => [
                    'config_key'            => 'roof_age',
                    'answer_text'           => 'Replaced in 2020',
                    'question_label'        => 'How old is the roof?',
                    'question_group'        => 'Property Condition & Maintenance',
                    'intelligence_category' => 'property_condition_maintenance',
                ],
                'hvac' => [
                    'config_key'            => 'hvac',
                    'answer_text'           => 'Serviced annually',
                    'question_label'        => 'What is the HVAC maintenance history?',
                    'question_group'        => 'Property Condition & Maintenance',
                    'intelligence_category' => 'property_condition_maintenance',
                ],
            ],
        ];

        $result = $service->buildContract('listing_facts', $context);

        $this->assertSame('contract_ready', $result['status'],
            "listing_facts with enriched object FAQ values must produce contract_ready");
        $this->assertTrue($result['success']);
    }

    public function test_case_N_listing_facts_contract_ready_with_mixed_faq_shapes(): void
    {
        $service = $this->makeService();

        $context = [
            'listing' => ['listing_id' => 1, 'listing_type' => 'landlord'],
            'faq_answers' => [
                'parking_details' => 'One assigned spot',
                'laundry' => [
                    'config_key'            => 'laundry',
                    'answer_text'           => 'In-unit washer/dryer',
                    'question_label'        => 'Is laundry available?',
                    'question_group'        => 'Property Amenities',
                    'intelligence_category' => 'property_amenities',
                ],
            ],
        ];

        $result = $service->buildContract('listing_facts', $context);

        $this->assertSame('contract_ready', $result['status'],
            "listing_facts with mixed legacy and enriched FAQ shapes must produce contract_ready");
        $this->assertTrue($result['success']);
    }

    public function test_case_N_contract_service_does_not_mutate_faq_answers_entries(): void
    {
        $service = $this->makeService();

        $enrichedEntry = [
            'config_key'            => 'roof_age',
            'answer_text'           => 'Replaced in 2020',
            'question_label'        => 'How old is the roof?',
            'question_group'        => 'Property Condition & Maintenance',
            'intelligence_category' => 'property_condition_maintenance',
        ];

        $context = [
            'listing'     => ['listing_id' => 1, 'listing_type' => 'seller'],
            'faq_answers' => ['roof_age' => $enrichedEntry],
        ];

        $contextBefore = json_encode($context);
        $service->buildContract('listing_facts', $context);
        $contextAfter = json_encode($context);

        $this->assertSame($contextBefore, $contextAfter,
            "buildContract() must not mutate the faq_answers entries in the passed context");
    }

    public function test_case_N_contract_service_does_not_mutate_legacy_faq_answers(): void
    {
        $service = $this->makeService();

        $context = [
            'listing'     => ['listing_id' => 1, 'listing_type' => 'seller'],
            'faq_answers' => ['roof_age' => 'Replaced in 2020'],
        ];

        $contextBefore = json_encode($context);
        $service->buildContract('listing_facts', $context);
        $contextAfter = json_encode($context);

        $this->assertSame($contextBefore, $contextAfter,
            "buildContract() must not mutate legacy faq_answers string entries in the passed context");
    }

    public function test_case_N_listing_facts_contract_ready_with_null_metadata_faq_entries(): void
    {
        $service = $this->makeService();

        $context = [
            'listing' => ['listing_id' => 1, 'listing_type' => 'seller'],
            'faq_answers' => [
                'unknown_custom_key' => [
                    'config_key'            => 'unknown_custom_key',
                    'answer_text'           => 'Some answer',
                    'question_label'        => null,
                    'question_group'        => null,
                    'intelligence_category' => null,
                ],
            ],
        ];

        $result = $service->buildContract('listing_facts', $context);

        $this->assertSame('contract_ready', $result['status'],
            "listing_facts with null-metadata enriched FAQ entries must still produce contract_ready");
        $this->assertTrue($result['success']);
    }

    public function test_case_N_listing_facts_contract_ready_with_empty_faq_answers(): void
    {
        $service = $this->makeService();

        $context = [
            'listing'     => ['listing_id' => 1, 'listing_type' => 'seller'],
            'faq_answers' => [],
        ];

        $result = $service->buildContract('listing_facts', $context);

        $this->assertSame('contract_ready', $result['status'],
            "listing_facts with empty faq_answers must produce contract_ready (faq_answers is not a required source)");
        $this->assertTrue($result['success']);
    }

    public function test_case_N_faq_answers_in_listing_facts_allowed_context(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertContains('faq_answers', $result['allowed_context'],
            "'faq_answers' must be listed in listing_facts allowed_context");
    }

    public function test_case_N_faq_answers_not_a_required_source_for_listing_facts(): void
    {
        $service = $this->makeService();
        $result  = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertNotContains('faq_answers', $result['required_sources'],
            "'faq_answers' must not be a required_source — it is optional supplemental data");
    }

    // =========================================================================
    // Case O — Normalizer–contract path compatibility (real contract, no mocks)
    // =========================================================================

    // ── O1 ── Real listing_facts contract contains 'faq_answers' umbrella ─────

    public function test_case_O1_real_listing_facts_contract_contains_faq_answers_umbrella(): void
    {
        $service = new AskAiResponseContractService();
        $paths   = $service->getListingFactsAllowedPaths();

        $this->assertContains(
            'faq_answers',
            $paths,
            "The real listing_facts allowed_context must contain the 'faq_answers' umbrella entry. "
            . "AskAiIntentNormalizerService resolves FAQ questions to 'faq_answers.<key>' leaf paths; "
            . "the umbrella is required for the prefix-match guard in AskAiInternalRunnerService to fire."
        );
    }

    // ── O2 ── Prefix-match guard accepts FAQ leaf paths against real contract ──

    public function test_case_O2_faq_leaf_path_is_permitted_by_real_contract_via_prefix(): void
    {
        // This test uses the REAL AskAiResponseContractService (not mocked) to prove that
        // a FAQ leaf path returned by the normalizer would actually pass the narrowing guard.
        // The guard accepts 'faq_answers.hvac_system_age' if 'faq_answers' is in allowed_context.
        $service = new AskAiResponseContractService();
        $paths   = $service->getListingFactsAllowedPaths();

        // Replicate the normalizedKeyIsPermitted() logic here to prove the real contract paths
        // satisfy it for a representative FAQ leaf key.
        $leafKey = 'faq_answers.hvac_system_age';

        $exactMatch = in_array($leafKey, $paths, true);

        $prefixMatch = false;
        foreach ($paths as $entry) {
            if (str_starts_with($leafKey, $entry . '.')) {
                $prefixMatch = true;
                break;
            }
        }

        $this->assertTrue(
            $exactMatch || $prefixMatch,
            "The leaf key '{$leafKey}' must be permitted by the real listing_facts allowed_context "
            . "via either exact match or prefix match. "
            . "Current allowed_context paths: " . implode(', ', $paths)
        );
    }

    public function test_case_O2_different_faq_leaf_path_also_permitted_by_real_contract(): void
    {
        $service = new AskAiResponseContractService();
        $paths   = $service->getListingFactsAllowedPaths();

        $leafKey = 'faq_answers.roof_age_and_condition';

        $permitted = in_array($leafKey, $paths, true)
            || array_reduce($paths, function (bool $carry, string $entry) use ($leafKey): bool {
                return $carry || str_starts_with($leafKey, $entry . '.');
            }, false);

        $this->assertTrue(
            $permitted,
            "The leaf key '{$leafKey}' must be permitted by the real listing_facts allowed_context."
        );
    }

    // ── O3 ── listing.* exact paths are permitted by real contract ────────────

    public function test_case_O3_listing_dot_bedrooms_is_permitted_by_real_contract(): void
    {
        $service = new AskAiResponseContractService();
        $paths   = $service->getListingFactsAllowedPaths();

        $this->assertContains(
            'listing.bedrooms',
            $paths,
            "'listing.bedrooms' must be in the real listing_facts allowed_context for exact-match narrowing to work"
        );
    }

    public function test_case_O3_listing_dot_asking_price_is_permitted_by_real_contract(): void
    {
        $service = new AskAiResponseContractService();
        $paths   = $service->getListingFactsAllowedPaths();

        $this->assertContains(
            'listing.asking_price',
            $paths,
            "'listing.asking_price' must be in the real listing_facts allowed_context"
        );
    }

    // ── O4 ── getListingFactsAllowedPaths() is consistent with buildContract() ─

    public function test_case_O4_get_listing_facts_allowed_paths_matches_build_contract_output(): void
    {
        $service  = new AskAiResponseContractService();
        $direct   = $service->getListingFactsAllowedPaths();
        $contract = $service->buildContract('listing_facts', $this->makeContextFor('listing_facts'));

        $this->assertSame(
            $direct,
            $contract['allowed_context'],
            "getListingFactsAllowedPaths() must return the same array as buildContract()['allowed_context'] "
            . "for listing_facts — the normalizer reads the former; the guard checks the latter."
        );
    }
}
