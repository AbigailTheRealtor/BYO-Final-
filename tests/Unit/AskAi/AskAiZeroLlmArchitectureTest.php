<?php

namespace Tests\Unit\AskAi;

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiResponseContractService;
use Tests\TestCase;

/**
 * Zero-LLM proof for Ask AI.
 *
 * Ask AI answers deterministically: a supported fact is answered from stored data and an
 * unsupported question is refused. Two classes hold a model client — the answering adapter
 * and the intent normaliser — and both are gated by ONE code constant,
 * AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED. The normaliser used to be gated only
 * by a config flag, which the workspace .env sets to true; this test is what stops that
 * shape of bypass from coming back.
 */
class AskAiZeroLlmArchitectureTest extends TestCase
{
    /** Directories and files that make up the Ask AI answering surface. */
    private const SCOPE = [
        'app/Services/AskAi',
        'app/Support/AskAi',
        'app/Http/Controllers/AskAiListingQuestionController.php',
        'app/Http/Controllers/AskAi',
        'app/Http/Controllers/Admin/AskAiAdminTestController.php',
        'app/Http/Controllers/Admin/AskAiAnalyticsController.php',
    ];

    /** The only Ask AI files allowed to hold a model client — both must read the gate. */
    private const CLIENT_HOLDERS = [
        'app/Services/AskAi/AskAiOpenAiAdapterService.php',
        'app/Services/AskAi/AskAiIntentNormalizerService.php',
    ];

    public function test_llm_answering_is_hard_disabled_in_code(): void
    {
        $this->assertFalse(AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED);
    }

    public function test_only_the_two_gated_classes_hold_a_model_client_or_any_http_transport(): void
    {
        $offenders = [];

        foreach ($this->askAiFiles() as $file) {
            $code = $this->codeOnly((string) file_get_contents(base_path($file)));

            $client    = str_contains($code, 'OpenAiClientService');
            $transport = preg_match('/Http::|GuzzleHttp|curl_init|api\.openai\.com|chat\/completions|file_get_contents\(\s*[\'"]https?:/i', $code) === 1;

            if ($transport) {
                $offenders[] = "{$file}: raw HTTP transport";
            }
            if ($client && !in_array($file, self::CLIENT_HOLDERS, true)) {
                $offenders[] = "{$file}: holds OpenAiClientService";
            }
        }

        $this->assertSame([], $offenders, "Ask AI must have no ungated model path:\n" . implode("\n", $offenders));
    }

    public function test_every_client_holder_reads_the_code_constant(): void
    {
        foreach (self::CLIENT_HOLDERS as $file) {
            $this->assertStringContainsString(
                'LLM_ANSWERING_APPROVED',
                $this->codeOnly((string) file_get_contents(base_path($file))),
                "{$file} holds a model client and must be gated by LLM_ANSWERING_APPROVED."
            );
        }
    }

    public function test_the_normalizer_is_disabled_even_when_its_config_flag_is_on(): void
    {
        config(['ask_ai.enable_openai_intent_normalization' => true]);

        $this->assertFalse($this->normalizer($this->clientThatMustNotBeCalled())->isEnabled());
    }

    public function test_the_normalizer_never_reaches_its_client_even_when_called_directly(): void
    {
        config(['ask_ai.enable_openai_intent_normalization' => true]);

        $normalizer = $this->normalizer($this->clientThatMustNotBeCalled());
        $result     = $normalizer->normalize('how old is the roof', ['listing.roof_type'], 'seller');

        $this->assertNull($result);
        $this->assertSame('failed', $normalizer->getLastStatus());
        $this->assertSame('llm_answering_not_approved', $normalizer->getLastError());
    }

    public function test_the_adapter_never_reaches_its_client(): void
    {
        $result = (new AskAiOpenAiAdapterService($this->clientThatMustNotBeCalled()))
            ->generate(['status' => 'prompt_ready', 'question' => 'What makes this property stand out?']);

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('llm_answering_not_approved', $result['error']);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function clientThatMustNotBeCalled(): OpenAiClientService
    {
        $client = $this->getMockBuilder(OpenAiClientService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $client->expects($this->never())->method('send');

        return $client;
    }

    private function normalizer(OpenAiClientService $client): AskAiIntentNormalizerService
    {
        return new AskAiIntentNormalizerService($client, app(AskAiResponseContractService::class));
    }

    /** @return list<string> repo-relative PHP files in SCOPE */
    private function askAiFiles(): array
    {
        $files = [];

        foreach (self::SCOPE as $entry) {
            $path = base_path($entry);
            if (is_file($path)) {
                $files[] = $entry;
                continue;
            }
            // A scope entry that no longer exists is a failure, not a skip: a renamed
            // directory would otherwise make this guard pass by checking nothing.
            $this->assertDirectoryExists($path, "Ask AI scope entry {$entry} does not exist.");
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->getExtension() === 'php') {
                    $files[] = ltrim(str_replace(base_path(), '', $f->getPathname()), '/');
                }
            }
        }

        $this->assertNotEmpty($files, 'The Ask AI scope resolved to no files — the guard would pass vacuously.');
        sort($files);

        return $files;
    }

    /** Source with comments removed, so a docblock mentioning a class is not a reference. */
    private function codeOnly(string $source): string
    {
        $out = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }
}
