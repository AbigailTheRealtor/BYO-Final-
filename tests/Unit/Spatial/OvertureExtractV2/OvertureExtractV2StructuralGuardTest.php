<?php

namespace Tests\Unit\Spatial\OvertureExtractV2;

use PHPUnit\Framework\TestCase;

/**
 * The v2 extraction recipe is OFFLINE and INERT. These guards read source, not behaviour:
 *   * its namespace has no database, network, cache, queue, log, file or environment path — the
 *     command alone reads and writes files, and it refuses production;
 *   * its config has exactly one reader;
 *   * nothing outside the recipe references it: no Location DNA, runtime, import, schema or
 *     materialization path consumes the extract yet;
 *   * the v1 extractor is untouched by it, and it never uses v1's category fallback chain;
 *   * no migration exists for it;
 *   * every test of it is in the spatial CI manifest.
 */
class OvertureExtractV2StructuralGuardTest extends TestCase
{
    private const NAMESPACE_DIR = 'app/Services/Spatial/OvertureExtractV2';
    private const COMMAND = 'app/Console/Commands/CorpusExtractOvertureV2.php';
    private const CONFIG_FILE = 'config/overture_extract_v2.php';
    private const APP_DIRS = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes'];

    private static function root(): string
    {
        return dirname(__DIR__, 4);
    }

    /** @return list<string> */
    private static function files(string $dir, array $ext = ['php']): array
    {
        $base = self::root() . '/' . $dir;
        if (! is_dir($base)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && in_array($f->getExtension(), $ext, true)) {
                $out[] = substr($f->getPathname(), strlen(self::root()) + 1);
            }
        }
        sort($out);

        return $out;
    }

    private static function code(string $relative): string
    {
        $out = '';
        foreach (token_get_all((string) file_get_contents(self::root() . '/' . $relative)) as $t) {
            if (is_array($t)) {
                if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                    continue;
                }
                $out .= $t[1];
            } else {
                $out .= $t;
            }
        }

        return $out;
    }

    public function test_the_namespace_has_no_io_path(): void
    {
        $forbidden = [
            'DB::', 'Http::', 'Cache::', 'Log::', 'Storage::', 'Queue::', 'Redis::', 'Schema::',
            'Illuminate\Support\Facades', 'Illuminate\Database', 'GuzzleHttp', 'ClientInterface', 'curl_',
            'file_get_contents', 'file_put_contents', 'fopen(', 'fwrite(', 'fsockopen', 'stream_socket', 'unlink(',
            'http://', 'https://', 's3://', 'env(', 'dispatch(', 'event(', 'shell_exec', 'exec(', 'proc_open', 'duckdb',
        ];
        foreach (self::files(self::NAMESPACE_DIR) as $file) {
            $code = self::code($file);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $code, "{$file} contains {$needle}");
            }
        }
    }

    public function test_the_command_touches_no_database_or_network_and_refuses_production(): void
    {
        $code = self::code(self::COMMAND);
        $this->assertStringContainsString("app()->environment('production')", $code);
        foreach (['DB::', 'Http::', 'GuzzleHttp', 'curl_', 's3://', 'https://', 'Schema::', 'duckdb'] as $needle) {
            $this->assertStringNotContainsString($needle, $code, $needle);
        }
        // Production refusal comes before anything is read.
        $this->assertLessThan(strpos($code, 'OvertureExtractV2Config::load'), strpos($code, "environment('production')"));
    }

    public function test_the_config_has_exactly_one_reader(): void
    {
        $readers = [];
        foreach (self::APP_DIRS as $dir) {
            foreach (self::files($dir) as $file) {
                if ($file !== self::CONFIG_FILE && str_contains(self::code($file), 'overture_extract_v2')) {
                    $readers[] = $file;
                }
            }
        }
        $this->assertSame([self::NAMESPACE_DIR . '/OvertureExtractV2Config.php'], $readers);
    }

    public function test_nothing_but_the_command_consumes_the_recipe(): void
    {
        $offenders = [];
        foreach (self::APP_DIRS as $dir) {
            foreach (self::files($dir, ['php', 'js', 'vue']) as $file) {
                if (str_starts_with($file, self::NAMESPACE_DIR . '/') || $file === self::COMMAND) {
                    continue;
                }
                if (str_contains(self::code($file), 'OvertureExtractV2')) {
                    $offenders[] = $file;
                }
            }
        }
        $this->assertSame([], $offenders, 'no Location DNA, import, schema or runtime consumer yet');
    }

    public function test_no_category_fallback_and_v1_is_untouched(): void
    {
        $code = self::code(self::NAMESPACE_DIR . '/OvertureV2Normalizer.php');
        $this->assertStringNotContainsString('OverturePlaceNormalizer', $code);
        $this->assertStringNotContainsString('OvertureCategoryMap', $code);
        $this->assertSame(1, substr_count($code, '->mapSource('), 'taxonomy.primary is the one classifier');
        $this->assertMatchesRegularExpression('/mapSource\(\$token\)/', $code);
        $this->assertDoesNotMatchRegularExpression("/mapSource\\([^)]*(categories_primary|basic_category)/", $code);
        foreach (self::files('app/Services/Spatial') as $file) {
            if (str_starts_with($file, self::NAMESPACE_DIR . '/')) {
                continue;
            }
            $this->assertStringNotContainsString('OvertureExtractV2', self::code($file), "{$file} (v1 must not depend on v2)");
        }
    }

    public function test_no_migration_or_schema_for_the_recipe(): void
    {
        foreach (self::files('database') as $file) {
            $src = strtolower((string) file_get_contents(self::root() . '/' . $file));
            $this->assertStringNotContainsString('overture-extract-v2', $src, $file);
            $this->assertStringNotContainsString('overtureextractv2', $src, $file);
        }
    }

    public function test_every_recipe_test_is_in_the_spatial_manifest(): void
    {
        $manifest = (string) file_get_contents(self::root() . '/tests/spatial-ci-files.txt');
        $tests = array_merge(
            self::files('tests/Unit/Spatial/OvertureExtractV2'),
            ['tests/Feature/Spatial/CorpusExtractOvertureV2CommandTest.php'],
        );
        foreach ($tests as $test) {
            if (str_ends_with($test, 'Test.php')) {
                $this->assertStringContainsString($test, $manifest, "{$test} missing from tests/spatial-ci-files.txt");
            }
        }
    }
}
