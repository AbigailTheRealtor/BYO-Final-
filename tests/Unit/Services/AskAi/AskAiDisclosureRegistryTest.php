<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiDisclosureRegistry;
use PHPUnit\Framework\TestCase;

class AskAiDisclosureRegistryTest extends TestCase
{
    private AskAiDisclosureRegistry $registry;

    private string $registrySourcePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry           = new AskAiDisclosureRegistry();
        $this->registrySourcePath = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiDisclosureRegistry.php';
    }

    public function test_all_returns_all_11_disclosures(): void
    {
        $all = $this->registry->all();

        $this->assertCount(11, $all);
    }

    public function test_all_contains_every_required_key(): void
    {
        $all = $this->registry->all();

        $expectedKeys = [
            'GENERAL_EDUCATIONAL_INFORMATION',
            'PROPERTY_INTELLIGENCE_DISCLOSURE',
            'LOCATION_INTELLIGENCE_DISCLOSURE',
            'COMPATIBILITY_DISCLOSURE',
            'LIMITED_DATA_DISCLOSURE',
            'NO_BROKERAGE_ADVICE',
            'NO_LEGAL_ADVICE',
            'NO_TAX_ADVICE',
            'NO_LENDING_ADVICE',
            'NO_INVESTMENT_ADVICE',
            'FAIR_HOUSING_DISCLOSURE',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $all, "Missing disclosure key: {$key}");
        }
    }

    public function test_each_disclosure_has_required_fields(): void
    {
        foreach ($this->registry->all() as $key => $disclosure) {
            $this->assertArrayHasKey('key', $disclosure, "Disclosure {$key} missing 'key' field");
            $this->assertArrayHasKey('label', $disclosure, "Disclosure {$key} missing 'label' field");
            $this->assertArrayHasKey('description', $disclosure, "Disclosure {$key} missing 'description' field");

            $this->assertNotEmpty($disclosure['key'], "Disclosure {$key} has empty 'key'");
            $this->assertNotEmpty($disclosure['label'], "Disclosure {$key} has empty 'label'");
            $this->assertNotEmpty($disclosure['description'], "Disclosure {$key} has empty 'description'");
        }
    }

    public function test_get_returns_valid_array_for_known_key(): void
    {
        $disclosure = $this->registry->get('NO_LEGAL_ADVICE');

        $this->assertIsArray($disclosure);
        $this->assertSame('NO_LEGAL_ADVICE', $disclosure['key']);
        $this->assertArrayHasKey('label', $disclosure);
        $this->assertArrayHasKey('description', $disclosure);
    }

    public function test_get_returns_null_for_unknown_key(): void
    {
        $result = $this->registry->get('NONEXISTENT_DISCLOSURE_KEY');

        $this->assertNull($result);
    }

    public function test_exists_returns_true_for_known_key(): void
    {
        $this->assertTrue($this->registry->exists('FAIR_HOUSING_DISCLOSURE'));
    }

    public function test_exists_returns_false_for_unknown_key(): void
    {
        $this->assertFalse($this->registry->exists('NONEXISTENT_DISCLOSURE_KEY'));
    }

    public function test_registry_source_contains_no_openai_references(): void
    {
        $source = file_get_contents($this->registrySourcePath);

        $this->assertStringNotContainsString('OpenAI\\', $source, 'OpenAI SDK namespace import found');
        $this->assertStringNotContainsString('use OpenAI', $source, 'OpenAI SDK use statement found');
        $this->assertStringNotContainsString('openai()', $source, 'OpenAI function call found');
        $this->assertStringNotContainsString('->chat(', $source, 'OpenAI chat call found');
        $this->assertStringNotContainsString('->completions(', $source, 'OpenAI completions call found');
    }

    public function test_registry_source_contains_no_http_client_calls(): void
    {
        $source = file_get_contents($this->registrySourcePath);

        $this->assertStringNotContainsStringIgnoringCase('Http::', $source);
        $this->assertStringNotContainsStringIgnoringCase('Guzzle', $source);
        $this->assertStringNotContainsStringIgnoringCase('curl_init', $source);
        $this->assertStringNotContainsStringIgnoringCase('file_get_contents(\'http', $source);
    }

    public function test_registry_source_contains_no_db_writes(): void
    {
        $source = file_get_contents($this->registrySourcePath);

        $this->assertStringNotContainsStringIgnoringCase('DB::', $source);
        $this->assertStringNotContainsStringIgnoringCase('->save()', $source);
        $this->assertStringNotContainsStringIgnoringCase('->create(', $source);
        $this->assertStringNotContainsStringIgnoringCase('->update(', $source);
        $this->assertStringNotContainsStringIgnoringCase('->delete(', $source);
        $this->assertStringNotContainsStringIgnoringCase('->insert(', $source);
    }
}
