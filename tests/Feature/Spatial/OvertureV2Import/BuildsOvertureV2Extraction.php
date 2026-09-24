<?php

namespace Tests\Feature\Spatial\OvertureV2Import;

use App\Services\Spatial\OvertureV2Import\OvertureV2ImportContract;
use Illuminate\Support\Facades\Artisan;

/**
 * Builds small, REAL `overture-extract-v2` outputs for the importer tests by running the committed
 * extraction command over a committed raw fixture — never a hand-written manifest, so the tests
 * see exactly what the extractor emits. Also re-signs deliberately tampered files, so a test can
 * reach the row-level checks behind the checksum gate.
 *
 * `import_sample.ndjson` extracts to 5 base + 5 supplementary rows: 2 admitted CVS rescues (`store`,
 * `store_in_target`), 1 refused rescue, 2 diagnostic `matcher_only` rows, and 7 memberships —
 * a 7-Eleven + Speedway co-brand, a Publix pharmacy department (`storefront_unconfirmed`), a Wawa
 * fuel row and a base CVS `store_in_target`.
 */
trait BuildsOvertureV2Extraction
{
    /** A test-only version: not declared in config/overture_v2_corpus.php, whose pins are real. */
    protected string $fixtureVersion = 'overture-2026-08-19.0-fl-r90';

    /** @var list<string> */
    private array $extractDirs = [];

    protected function tearDownExtractions(): void
    {
        foreach ($this->extractDirs as $dir) {
            foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }
        $this->extractDirs = [];
    }

    protected function extractFixture(string $fixture = 'import_sample.ndjson'): string
    {
        $dir = sys_get_temp_dir() . '/overture-v2-import-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->extractDirs[] = $dir;

        $code = Artisan::call('corpus:extract-overture-v2', [
            '--input' => base_path('tests/fixtures/spatial/overture-v2/' . $fixture),
            '--output-dir' => $dir,
            '--release-row-count' => '100',
        ]);
        $this->assertSame(0, $code, Artisan::output());

        return $dir;
    }

    /** @return array<string, mixed> the whole config/overture_v2_corpus.php shape, for fromConfig() */
    protected function contractConfigFor(string $dir, array $override = [], ?string $version = null): array
    {
        $m = $this->manifest($dir);

        return ['import_contracts' => [($version ?? $this->fixtureVersion) => array_merge([
            'source_release' => $m['recipe']['release'],
            'extract_recipe_version' => $m['recipe']['recipe_version'],
            'taxonomy_map_version' => $m['recipe']['taxonomy_map_version'],
            'registry_version' => $m['chain_registry']['registry_version'],
            'registry_rule_hash' => $m['chain_registry']['rule_hash'],
            'base_rows' => $m['counts']['base_corpus_rows'],
            'supplementary_rows' => $m['counts']['supplementary_rows'],
            'matcher_analysis_rows' => $m['counts']['matcher_analysis_rows'],
            'base_sha256' => $m['outputs']['base.ndjson']['sha256'],
            'supplementary_sha256' => $m['outputs']['supplementary.ndjson']['sha256'],
        ], $override)]];
    }

    protected function contractFor(string $dir, array $override = [], ?string $version = null): OvertureV2ImportContract
    {
        return OvertureV2ImportContract::fromConfig($this->contractConfigFor($dir, $override, $version), $version ?? $this->fixtureVersion);
    }

    /** @return array<string, mixed> */
    protected function manifest(string $dir): array
    {
        return json_decode((string) file_get_contents($dir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function editManifest(string $dir, callable $edit): void
    {
        $m = $this->manifest($dir);
        $edit($m);
        file_put_contents($dir . '/manifest.json', json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
    }

    /** Rewrites the row carrying $sourceRef in $file, then re-signs the manifest's outputs. */
    protected function editRow(string $dir, string $file, string $sourceRef, callable $edit): void
    {
        $lines = explode("\n", rtrim((string) file_get_contents($dir . '/' . $file), "\n"));
        $found = false;
        foreach ($lines as $i => $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if ($row['source_ref'] === $sourceRef) {
                $edit($row);
                $lines[$i] = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                $found = true;
            }
        }
        $this->assertTrue($found, "fixture row {$sourceRef} not found in {$file}");
        file_put_contents($dir . '/' . $file, implode("\n", $lines) . "\n");
        $this->resign($dir);
    }

    /** Records the files' current SHA-256 in the manifest, as a consistent (tampered) extraction would. */
    protected function resign(string $dir): void
    {
        $this->editManifest($dir, function (array &$m) use ($dir): void {
            foreach (['base.ndjson', 'supplementary.ndjson'] as $file) {
                $m['outputs'][$file]['sha256'] = hash_file('sha256', $dir . '/' . $file);
            }
        });
    }
}
