<?php

namespace Tests\Unit\SmartTags;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Structural guarantees, asserted against source rather than remembered:
 *
 *   • no AI, network or image service is reachable from Smart Tags;
 *   • the two config files have exactly one reader;
 *   • Phase 1 is INERT — nothing outside the Smart Tags namespace calls the
 *     derivation, writer or purger services, and the Bridge importer, MLS sync,
 *     matching and Offer Listing components do not reference Smart Tags at all.
 */
class SmartTagArchitectureGuardTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return array<string, string> path => contents */
    private function phpFiles(string $relativeDir): array
    {
        $dir = $this->root() . '/' . $relativeDir;
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $out[substr($file->getPathname(), strlen($this->root()) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, string> */
    private function smartTagSources(): array
    {
        return array_merge(
            $this->phpFiles('app/Support/SmartTags'),
            $this->phpFiles('app/Services/SmartTags'),
            ['config/smart_tags.php' => (string) file_get_contents($this->root() . '/config/smart_tags.php')],
            ['config/smart_tag_sources.php' => (string) file_get_contents($this->root() . '/config/smart_tag_sources.php')],
        );
    }

    /** @test */
    public function smart_tags_reach_no_ai_network_or_image_service(): void
    {
        $forbidden = [
            'OpenAi', 'OpenAI', 'openai', 'Anthropic', 'Guzzle', 'Http::', 'ClientInterface', 'curl_', 'file_get_contents(\'http',
            'fsockopen', 'stream_socket_client', 'AskAi', 'AgentAi', 'gpt-', 'image_url', 'input_image', 'Imagick', 'imagecreate', 'GooglePlaces',
            'ProviderRequestBudget', 'BridgeApiService', 'LazyBridgeImportService',
        ];

        foreach ($this->smartTagSources() as $path => $source) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $source, "{$path} references {$needle}");
            }
        }
    }

    /** @test */
    public function the_smart_tag_config_files_have_exactly_one_reader(): void
    {
        $offenders = [];
        foreach (['app', 'routes', 'resources/views', 'database', 'config'] as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                if ($path === 'app/Support/SmartTags/SmartTagConfig.php') {
                    continue;
                }
                // A real read: config('smart_tags…') or a require/include of the file.
                if (preg_match('/config\(\s*[\'"]smart_tag|\b(?:require|include)(?:_once)?\b[^;\n]*smart_tag/', $source)) {
                    $offenders[] = $path;
                }
            }
        }

        $this->assertSame([], $offenders, 'Only SmartTagConfig may read the Smart Tag config files');
    }

    /** @test */
    public function phase_one_is_inert_nothing_outside_smart_tags_calls_it(): void
    {
        $services = ['SmartTagDerivationService', 'SmartTagEvidenceWriter', 'ManualSmartTagWriter', 'SmartTagAssignmentPurger',
                     'SmartTagAssignmentProjector', 'BridgeStructuredTagDeriver', 'NativeListingTagDeriver', 'ListingDescriptionTagParser'];

        $offenders = [];
        foreach (['app', 'routes', 'resources/views', 'database', 'config'] as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                if (str_starts_with($path, 'app/Services/SmartTags/') || str_starts_with($path, 'app/Support/SmartTags/')) {
                    continue;
                }
                foreach ($services as $service) {
                    // A code reference (import, construction, static call, class constant), not a docblock mention.
                    $code = '/(?:use\s+App\\\\Services\\\\SmartTags\\\\(?:[\w\\\\]*\\\\)?' . $service . '\b|new\s+\\\\?(?:[\w\\\\]*\\\\)?' . $service . '\b|\b' . $service . '::)/';
                    if (preg_match($code, $source)) {
                        $offenders[] = "{$path} → {$service}";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Phase 1 must not be wired into any application path');
    }

    /** @test */
    public function import_sync_matching_explore_and_location_dna_do_not_reference_smart_tags(): void
    {
        $untouched = [
            'app/Services/Bridge', 'app/Services/ListingImport', 'app/Services/Stellar', 'app/Services/Explore',
            'app/Services/LocationDna', 'app/Services/Dna', 'app/Http/Livewire', 'app/Jobs', 'app/Observers', 'app/Console',
        ];

        foreach ($untouched as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                $this->assertDoesNotMatchRegularExpression('/SmartTag|smart_tag/i', $source, "{$path} references Smart Tags");
            }
        }
    }
}
