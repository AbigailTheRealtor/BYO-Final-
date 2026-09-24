<?php

namespace Tests\Feature\Spatial;

use Tests\TestCase;

/**
 * `corpus:extract-overture-v2` end to end against the committed fixture: three files, a manifest
 * whose counts, pins and checksums agree with the files, and refusals that write nothing.
 */
class CorpusExtractOvertureV2CommandTest extends TestCase
{
    private const FIXTURE = 'tests/fixtures/spatial/overture-v2/raw_bbox_sample.ndjson';

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        $this->out = sys_get_temp_dir() . '/overture-v2-' . bin2hex(random_bytes(6));
        mkdir($this->out);
    }

    protected function tearDown(): void
    {
        @chmod($this->out, 0755);
        foreach (array_merge(glob($this->out . '/*') ?: [], glob($this->out . '/.*.tmp-*') ?: []) as $f) {
            unlink($f);
        }
        rmdir($this->out);
        parent::tearDown();
    }

    private function extract(string $input, array $extra = []): int
    {
        return $this->artisan('corpus:extract-overture-v2', array_merge([
            '--input' => $input,
            '--output-dir' => $this->out,
        ], $extra))->run();
    }

    public function test_writes_both_lanes_and_a_reconciled_manifest(): void
    {
        $this->assertSame(0, $this->extract(base_path(self::FIXTURE), ['--release-row-count' => '100']));

        $m = json_decode((string) file_get_contents($this->out . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(5, $m['counts']['base_corpus_rows']);
        $this->assertSame(6, $m['counts']['supplementary_rows']);
        $this->assertSame(11, $m['counts']['matcher_analysis_rows']);
        $this->assertTrue($m['counts']['fully_accounted']);
        $this->assertSame(74, $m['source']['outside_bbox_sql']);

        foreach (['base.ndjson' => 5, 'supplementary.ndjson' => 6] as $file => $rows) {
            $bytes = (string) file_get_contents($this->out . '/' . $file);
            $this->assertSame($rows, substr_count($bytes, "\n"), $file);
            $this->assertSame(hash('sha256', $bytes), $m['outputs'][$file]['sha256'], $file);
        }
        foreach (explode("\n", trim((string) file_get_contents($this->out . '/base.ndjson'))) as $line) {
            $this->assertSame('base', json_decode($line, true)['lane']);
        }

        $this->assertSame('overture-extract-v2', $m['recipe']['recipe_version']);
        $this->assertSame('2026-08-19.0', $m['recipe']['release']);
        $this->assertSame('overture-taxonomy-v2.0', $m['recipe']['taxonomy_map_version']);
        $this->assertSame('chain-registry-v2', $m['chain_registry']['registry_version']);
        $this->assertSame('b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f', $m['chain_registry']['rule_hash']);
        $this->assertSame(['cvs_shopping' => 2], $m['matcher_census']['rescued_by_lane']);
        $this->assertSame(['admitted' => 2, 'refused' => 2], $m['matcher_census']['rescue_verdicts']);
        $this->assertSame(0, $m['matcher_census']['ambiguous_rows']);
        $this->assertSame(21, count($m['recipe']['diagnostic_selector']['name_brand_patterns']));
        $this->assertTrue($m['recipe']['diagnostic_selector']['any_brand_wikidata']);

        // The written supplementary lane carries resolved verdicts only; `rescued` iff admitted.
        $verdicts = [];
        foreach (explode("\n", trim((string) file_get_contents($this->out . '/supplementary.ndjson'))) as $line) {
            $row = json_decode($line, true);
            $this->assertNull($row['category_key']);
            $this->assertNotSame('pending', $row['rescue_verdict']);
            $this->assertSame($row['rescue_verdict'] === 'admitted', $row['materialization_policy'] === 'rescued', $row['source_ref']);
            $verdicts[$row['source_ref']] = $row['rescue_verdict'];
        }
        $this->assertSame('admitted', $verdicts['fx-09']);
        $this->assertSame('refused', $verdicts['fx-10']);
        $this->assertSame('not_candidate', $verdicts['fx-14']);
        $this->assertSame([], glob($this->out . '/.*.tmp-*'), 'no temporary file is left behind');
    }

    public function test_a_rerun_replaces_every_file_and_leaves_no_stale_manifest(): void
    {
        file_put_contents($this->out . '/manifest.json', '{"stale": true}');
        $this->assertSame(0, $this->extract(base_path(self::FIXTURE)));
        $m = json_decode((string) file_get_contents($this->out . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('stale', $m);
        $this->assertSame(hash_file('sha256', $this->out . '/base.ndjson'), $m['outputs']['base.ndjson']['sha256']);
    }

    public function test_an_unwritable_output_directory_fails_and_leaves_nothing(): void
    {
        chmod($this->out, 0555);
        if (is_writable($this->out)) {
            $this->markTestSkipped('running as a user that can write to a read-only directory');
        }
        $this->assertSame(1, $this->extract(base_path(self::FIXTURE)));
        chmod($this->out, 0755);
        $this->assertSame([], glob($this->out . '/*'));
        $this->assertSame([], glob($this->out . '/.*.tmp-*'));
    }

    public function test_refuses_production_and_writes_nothing(): void
    {
        $this->app['env'] = 'production';
        $this->assertSame(1, $this->extract(base_path(self::FIXTURE)));
        $this->assertSame([], glob($this->out . '/*'));
    }

    public function test_a_malformed_line_aborts_and_writes_nothing(): void
    {
        $bad = $this->out . '/bad-input.ndjson';
        file_put_contents($bad, file_get_contents(base_path(self::FIXTURE)) . "not json\n");
        $this->assertSame(1, $this->extract($bad));
        $this->assertSame([$bad], glob($this->out . '/*'));
    }

    public function test_a_drifted_projection_aborts_and_writes_nothing(): void
    {
        $bad = $this->out . '/drift.ndjson';
        file_put_contents($bad, json_encode(['id' => 'x', 'name' => 'y']) . "\n");
        $this->assertSame(1, $this->extract($bad));
        $this->assertSame([$bad], glob($this->out . '/*'));
    }

    public function test_bad_arguments_are_refused(): void
    {
        $this->assertSame(1, $this->extract($this->out . '/missing.ndjson'));
        $this->assertSame(1, $this->extract(base_path(self::FIXTURE), ['--release-row-count' => '-5']));
        $this->assertSame([], glob($this->out . '/*'));
    }
}
