<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiKnowledgeSourceRegistry;
use PHPUnit\Framework\TestCase;

/**
 * AskAiKnowledgeSourceRegistryTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * AskAiKnowledgeSourceRegistry is stateless and requires no mocking.
 *
 * Test coverage (cases A–F):
 *   A. all() returns exactly the ten expected source keys
 *   B. Each source definition contains the required fields
 *   C. isApproved() returns true for all ten valid keys and false for unknown key
 *   D. getSource() returns the correct definition for a valid key and null for unknown
 *   E. requiredVersionKey() returns the version_key string for a valid source and null for unknown
 *   F. Service file contains no prohibited write or OpenAI calls (static grep on non-comment lines)
 */
class AskAiKnowledgeSourceRegistryTest extends TestCase
{
    /**
     * The ten canonical source keys that must be registered.
     */
    private const EXPECTED_SOURCE_KEYS = [
        'listing',
        'property_intelligence',
        'location_intelligence',
        'buyer_avatar',
        'tenant_avatar',
        'compatibility',
        'offer_analysis',
        'governance_documents',
        'agent_profile',
        'agent_presets',
    ];

    /**
     * The required fields every source definition must carry.
     */
    private const REQUIRED_SOURCE_FIELDS = [
        'key',
        'label',
        'description',
        'version_key',
        'allowed_for_question_types',
    ];

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiKnowledgeSourceRegistry.php';
    }

    private function makeRegistry(): AskAiKnowledgeSourceRegistry
    {
        return new AskAiKnowledgeSourceRegistry();
    }

    // =========================================================================
    // Case A — all() returns exactly the ten expected source keys
    // =========================================================================

    public function test_case_A_all_returns_exactly_ten_sources(): void
    {
        $registry = $this->makeRegistry();
        $all      = $registry->all();

        $this->assertIsArray($all);
        $this->assertCount(10, $all, 'all() must return exactly 10 source definitions');
    }

    public function test_case_A_all_returns_all_ten_expected_source_keys(): void
    {
        $registry = $this->makeRegistry();
        $all      = $registry->all();
        $keys     = array_keys($all);

        foreach (self::EXPECTED_SOURCE_KEYS as $expectedKey) {
            $this->assertContains(
                $expectedKey,
                $keys,
                "all() must include source key '{$expectedKey}'"
            );
        }
    }

    public function test_case_A_all_contains_no_extra_source_keys(): void
    {
        $registry = $this->makeRegistry();
        $all      = $registry->all();
        $keys     = array_keys($all);

        sort($keys);
        $expected = self::EXPECTED_SOURCE_KEYS;
        sort($expected);

        $this->assertSame($expected, $keys, 'all() must not contain any extra source keys beyond the ten expected');
    }

    // =========================================================================
    // Case B — Each source definition contains the required fields
    // =========================================================================

    public function test_case_B_each_source_contains_required_fields(): void
    {
        $registry = $this->makeRegistry();

        foreach ($registry->all() as $key => $definition) {
            foreach (self::REQUIRED_SOURCE_FIELDS as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $definition,
                    "Source '{$key}' is missing required field '{$field}'"
                );
            }
        }
    }

    public function test_case_B_each_source_key_field_matches_its_array_key(): void
    {
        $registry = $this->makeRegistry();

        foreach ($registry->all() as $arrayKey => $definition) {
            $this->assertSame(
                $arrayKey,
                $definition['key'],
                "Source '{$arrayKey}': definition['key'] must match the array key"
            );
        }
    }

    public function test_case_B_each_source_label_is_a_non_empty_string(): void
    {
        $registry = $this->makeRegistry();

        foreach ($registry->all() as $key => $definition) {
            $this->assertIsString($definition['label'], "Source '{$key}': label must be a string");
            $this->assertNotEmpty($definition['label'], "Source '{$key}': label must not be empty");
        }
    }

    public function test_case_B_each_source_description_is_a_non_empty_string(): void
    {
        $registry = $this->makeRegistry();

        foreach ($registry->all() as $key => $definition) {
            $this->assertIsString($definition['description'], "Source '{$key}': description must be a string");
            $this->assertNotEmpty($definition['description'], "Source '{$key}': description must not be empty");
        }
    }

    public function test_case_B_each_source_version_key_is_a_non_empty_string(): void
    {
        $registry = $this->makeRegistry();

        foreach ($registry->all() as $key => $definition) {
            $this->assertIsString($definition['version_key'], "Source '{$key}': version_key must be a string");
            $this->assertNotEmpty($definition['version_key'], "Source '{$key}': version_key must not be empty");
        }
    }

    public function test_case_B_each_source_allowed_for_question_types_is_an_array(): void
    {
        $registry = $this->makeRegistry();

        foreach ($registry->all() as $key => $definition) {
            $this->assertIsArray(
                $definition['allowed_for_question_types'],
                "Source '{$key}': allowed_for_question_types must be an array"
            );
        }
    }

    // =========================================================================
    // Case C — isApproved() returns true for all eight valid keys and false for unknown
    // =========================================================================

    public function test_case_C_isApproved_returns_true_for_all_eight_valid_keys(): void
    {
        $registry = $this->makeRegistry();

        foreach (self::EXPECTED_SOURCE_KEYS as $key) {
            $this->assertTrue(
                $registry->isApproved($key),
                "isApproved('{$key}') must return true"
            );
        }
    }

    public function test_case_C_isApproved_returns_false_for_unknown_key(): void
    {
        $registry = $this->makeRegistry();

        $this->assertFalse($registry->isApproved('unknown_source'));
    }

    public function test_case_C_isApproved_returns_false_for_empty_string(): void
    {
        $registry = $this->makeRegistry();

        $this->assertFalse($registry->isApproved(''));
    }

    public function test_case_C_isApproved_returns_false_for_partial_key(): void
    {
        $registry = $this->makeRegistry();

        $this->assertFalse($registry->isApproved('listing_'));
        $this->assertFalse($registry->isApproved('property'));
    }

    // =========================================================================
    // Case D — getSource() returns correct definition for valid key and null for unknown
    // =========================================================================

    public function test_case_D_getSource_returns_array_for_each_valid_key(): void
    {
        $registry = $this->makeRegistry();

        foreach (self::EXPECTED_SOURCE_KEYS as $key) {
            $source = $registry->getSource($key);
            $this->assertIsArray($source, "getSource('{$key}') must return an array");
        }
    }

    public function test_case_D_getSource_returns_correct_definition_for_listing(): void
    {
        $registry = $this->makeRegistry();
        $source   = $registry->getSource('listing');

        $this->assertNotNull($source);
        $this->assertSame('listing', $source['key']);
        $this->assertArrayHasKey('label', $source);
        $this->assertArrayHasKey('description', $source);
        $this->assertArrayHasKey('version_key', $source);
        $this->assertArrayHasKey('allowed_for_question_types', $source);
    }

    public function test_case_D_getSource_returns_correct_definition_for_compatibility(): void
    {
        $registry = $this->makeRegistry();
        $source   = $registry->getSource('compatibility');

        $this->assertNotNull($source);
        $this->assertSame('compatibility', $source['key']);
    }

    public function test_case_D_getSource_returns_null_for_unknown_key(): void
    {
        $registry = $this->makeRegistry();

        $this->assertNull($registry->getSource('nonexistent_source'));
    }

    public function test_case_D_getSource_returns_null_for_empty_string(): void
    {
        $registry = $this->makeRegistry();

        $this->assertNull($registry->getSource(''));
    }

    public function test_case_D_getSource_definition_matches_all_entry_for_each_source(): void
    {
        $registry = $this->makeRegistry();
        $all      = $registry->all();

        foreach (self::EXPECTED_SOURCE_KEYS as $key) {
            $this->assertSame(
                $all[$key],
                $registry->getSource($key),
                "getSource('{$key}') must return the same definition as all()['{$key}']"
            );
        }
    }

    // =========================================================================
    // Case E — requiredVersionKey() returns version_key string or null for unknown
    // =========================================================================

    public function test_case_E_requiredVersionKey_returns_string_for_all_valid_sources(): void
    {
        $registry = $this->makeRegistry();

        foreach (self::EXPECTED_SOURCE_KEYS as $key) {
            $versionKey = $registry->requiredVersionKey($key);
            $this->assertIsString($versionKey, "requiredVersionKey('{$key}') must return a string");
            $this->assertNotEmpty($versionKey, "requiredVersionKey('{$key}') must not be empty");
        }
    }

    public function test_case_E_requiredVersionKey_matches_version_key_field_in_definition(): void
    {
        $registry = $this->makeRegistry();

        foreach (self::EXPECTED_SOURCE_KEYS as $key) {
            $definition = $registry->getSource($key);
            $this->assertSame(
                $definition['version_key'],
                $registry->requiredVersionKey($key),
                "requiredVersionKey('{$key}') must match definition['version_key']"
            );
        }
    }

    public function test_case_E_requiredVersionKey_returns_null_for_unknown_source(): void
    {
        $registry = $this->makeRegistry();

        $this->assertNull($registry->requiredVersionKey('nonexistent_source'));
    }

    public function test_case_E_requiredVersionKey_returns_null_for_empty_string(): void
    {
        $registry = $this->makeRegistry();

        $this->assertNull($registry->requiredVersionKey(''));
    }

    // =========================================================================
    // Case F — Service file contains no prohibited write or OpenAI calls
    // =========================================================================

    public function test_case_F_service_file_contains_no_write_or_openai_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

        $content = file_get_contents($path);

        // Strip comment lines so prohibition keywords in governance docs don't false-positive.
        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        // Prohibited import/namespace patterns
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

        // Prohibited write/HTTP call patterns in non-comment code
        $prohibitedCalls = [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            'Http::post',
            'Http::get',
            'curl_exec',
        ];

        foreach ($prohibitedCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not contain write/HTTP call '{$term}'"
            );
        }
    }
}
