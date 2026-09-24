<?php

namespace Tests\Unit\Spatial\ChainRegistry;

use PHPUnit\Framework\TestCase;

/**
 * The chain registry ships PURE and UNREFERENCED. These guards read source, not behaviour:
 *   * nothing outside its own namespace references it — no Location DNA, Ask AI, Buyer/Tenant,
 *     Smart Tags or brand-query wiring. The ONE exception is the offline v2 extraction recipe
 *     (OFFLINE_CONSUMERS), which validates its rescue lanes against the registry and runs the
 *     matcher for accounting and rescue verdicts; it refuses production and has no DB or
 *     network path of its own;
 *   * its config has exactly one reader;
 *   * it has no database, network, cache, log, Google or environment path;
 *   * it contains no similarity or edit-distance function, so identity cannot become fuzzy;
 *   * every test of it is in the spatial CI manifest, so it cannot drop out of the gate.
 */
class ChainRegistryStructuralGuardTest extends TestCase
{
    private const NAMESPACE_DIR = 'app/Services/Spatial/ChainRegistry';
    private const CONFIG_FILE = 'config/poi_chain_registry.php';

    /**
     * The only files outside the namespace that may reference it: the offline extraction recipe,
     * and the offline v2 importer's gate and command, which re-run the census over the rows they
     * import (docs/spatial/overture-v2-corpus-schema.md §4). Both refuse production; neither is
     * reachable from a request. Exact paths, never a prefix a runtime file could slip under.
     */
    private const OFFLINE_CONSUMERS = [
        'app/Console/Commands/CorpusExtractOvertureV2.php',
        'app/Console/Commands/CorpusImportOvertureV2.php',
        'app/Services/Spatial/OvertureExtractV2/OvertureExtractV2Config.php',
        'app/Services/Spatial/OvertureExtractV2/OvertureV2MatcherCensus.php',
        'app/Services/Spatial/OvertureV2Import/OvertureV2ImportGate.php',
    ];

    /** Application source directories a consumer would live in. */
    private const APP_DIRS = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes'];

    private static function root(): string
    {
        return dirname(__DIR__, 4);
    }

    /** @return list<string> repository-relative paths */
    private static function files(string $dir, array $extensions = ['php']): array
    {
        $base = self::root() . '/' . $dir;
        if (! is_dir($base)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
                $out[] = substr($file->getPathname(), strlen(self::root()) + 1);
            }
        }
        sort($out);

        return $out;
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(self::root() . '/' . $relative);
    }

    /** PHP source with comments and docblocks removed: prose may say "no fuzzy matching". */
    private static function code(string $relative): string
    {
        $out = '';
        foreach (token_get_all(self::read($relative)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    public function test_nothing_outside_the_namespace_references_it(): void
    {
        $offenders = [];
        foreach (self::APP_DIRS as $dir) {
            foreach (self::files($dir, ['php', 'js', 'vue']) as $file) {
                if (str_starts_with($file, self::NAMESPACE_DIR . '/') || in_array($file, self::OFFLINE_CONSUMERS, true)) {
                    continue;
                }
                $src = str_ends_with($file, '.php') ? self::code($file) : self::read($file);
                if (str_contains($src, 'Spatial\\ChainRegistry') || str_contains($src, 'Spatial\\\\ChainRegistry')) {
                    $offenders[] = $file;
                }
            }
        }

        $this->assertSame([], $offenders, 'The chain registry has no runtime consumer yet.');
    }

    public function test_every_offline_consumer_exists_and_refuses_production(): void
    {
        foreach (self::OFFLINE_CONSUMERS as $file) {
            $this->assertFileExists(self::root() . '/' . $file, 'a stale allowlist entry is a hole');
        }
        $command = self::code('app/Console/Commands/CorpusExtractOvertureV2.php');
        $this->assertStringContainsString("environment('production')", $command);
        $this->assertStringNotContainsString('DB::', $command);

        // The importer writes, so it refuses production through the shared guard as well, and its
        // gate — the class that runs the matcher — is handed no connection at all.
        $import = self::code('app/Console/Commands/CorpusImportOvertureV2.php');
        $this->assertStringContainsString('$this->refusesProductionDatabase()', $import);
        $this->assertStringContainsString("environment('production')", $import);
        $gate = self::code('app/Services/Spatial/OvertureV2Import/OvertureV2ImportGate.php');
        $this->assertStringNotContainsString('DB::', $gate);
        $this->assertStringNotContainsString('Connection', $gate);
    }

    public function test_the_config_has_exactly_one_reader(): void
    {
        $readers = [];
        foreach (self::APP_DIRS as $dir) {
            foreach (self::files($dir) as $file) {
                if ($file !== self::CONFIG_FILE && str_contains(self::code($file), 'poi_chain_registry')) {
                    $readers[] = $file;
                }
            }
        }

        $this->assertSame([self::NAMESPACE_DIR . '/ChainRegistry.php'], $readers);
    }

    public function test_the_namespace_has_no_io_path(): void
    {
        $forbidden = [
            'DB::', 'Http::', 'Cache::', 'Log::', 'Storage::', 'Queue::', 'Redis::', 'Schema::',
            'Illuminate\\Support\\Facades', 'Illuminate\\Database', 'GuzzleHttp', 'ClientInterface',
            'curl_', 'file_get_contents', 'fopen(', 'fsockopen', 'stream_socket', 'http://', 'https://',
            'Google', 'google', 'env(', 'dispatch(', 'event(',
        ];

        $files = self::files(self::NAMESPACE_DIR);
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $src = self::code($file);
            foreach ($forbidden as $token) {
                $this->assertStringNotContainsString($token, $src, "{$file} contains {$token}");
            }
        }
    }

    public function test_the_namespace_has_no_similarity_function(): void
    {
        foreach (self::files(self::NAMESPACE_DIR) as $file) {
            $src = strtolower(self::code($file));
            foreach (['levenshtein', 'similar_text', 'soundex', 'metaphone', 'trigram', 'similarity(', 'fuzzy'] as $token) {
                $this->assertStringNotContainsString($token, $src, "{$file} contains {$token}");
            }
        }
    }

    public function test_the_config_is_data_only(): void
    {
        $src = self::code(self::CONFIG_FILE);
        foreach (['env(', 'function', '=>fn', 'new ', 'config('] as $token) {
            $this->assertStringNotContainsString($token, $src, "config contains {$token}");
        }
    }

    public function test_the_chain_registry_is_not_a_required_production_flag(): void
    {
        $this->assertStringNotContainsString('chain', strtolower(self::read('config/required_production_flags.php')));
    }

    public function test_every_chain_registry_test_is_in_the_spatial_manifest(): void
    {
        $manifest = array_filter(array_map(
            static fn (string $line): string => trim(explode('#', $line, 2)[0]),
            explode("\n", self::read('tests/spatial-ci-files.txt'))
        ));

        foreach (self::files('tests/Unit/Spatial/ChainRegistry') as $test) {
            if (str_ends_with($test, 'Test.php')) {
                $this->assertContains($test, $manifest, "{$test} must be listed in tests/spatial-ci-files.txt");
            }
        }
    }
}
