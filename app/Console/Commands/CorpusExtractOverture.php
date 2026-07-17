<?php

namespace App\Console\Commands;

use App\Services\Spatial\CorpusSizingProjector;
use App\Services\Spatial\NormalizedExtractIo;
use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\OverturePlaceNormalizer;
use Illuminate\Console\Command;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B2).
 *
 * OFFLINE Overture Places extractor. Reads raw-Overture-shaped NDJSON rows,
 * normalizes them (primary-category-only mapping + confidence>=floor), and
 * writes the canonical normalized NDJSON extract, then reports counts and a
 * storage projection.
 *
 * HARD constraints (owner decision — enforced here):
 *   • REFUSES to run in the production environment.
 *   • NEVER opens a PostGIS / pgsql_spatial connection (touches no DB at all).
 *   • Works against COMMITTED FIXTURES with DuckDB NOT installed. The DuckDB
 *     GeoParquet path (spikes/.../sql/extract_places.sql) is authored for the
 *     later Class-2 live run; when DuckDB is absent this command uses the
 *     fixture/decoded-NDJSON code path.
 */
class CorpusExtractOverture extends Command
{
    protected $signature = 'corpus:extract-overture
        {--region=pinellas : Region key from config/overture_places.php regions}
        {--input= : Raw Overture NDJSON input (defaults to the committed fixture)}
        {--output= : Destination for the normalized NDJSON extract}
        {--confidence-min= : Override the confidence floor (default from config)}';

    protected $description = 'OFFLINE: normalize raw Overture places into the canonical NDJSON extract (no PostGIS, refuses production)';

    public function handle(): int
    {
        // ── Guard 1: never in production. No override flag by design. ──────────
        if (app()->environment('production')) {
            $this->error('[Batch 2A] corpus:extract-overture is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Live corpus load / staging / materialization is deferred to the Class-2 phase.');

            return self::FAILURE;
        }

        $config = config('overture_places');
        $region = (string) $this->option('region');

        if (!isset($config['regions'][$region])) {
            $this->error("Unknown region [{$region}]. Known: " . implode(', ', array_keys($config['regions'])));

            return self::FAILURE;
        }

        $input = (string) ($this->option('input') ?: base_path($config['default_fixture']));
        if (!is_file($input)) {
            $this->error("Raw Overture input not found: {$input}");

            return self::FAILURE;
        }

        $confidenceMin = $this->option('confidence-min') !== null
            ? (float) $this->option('confidence-min')
            : (float) $config['confidence_min'];

        $this->info('[Batch 2A] Overture OFFLINE extract');
        $this->line("  release pin : {$config['release']}");
        $this->line("  region      : {$region}");
        $this->line("  input       : {$input}");
        $this->line('  duckdb      : ' . ($this->duckdbAvailable() ? 'present (unused — fixture path)' : 'absent (fixture path)'));
        $this->line(sprintf('  confidence  : >= %.2f', $confidenceMin));

        // ── Read raw rows (decode NDJSON; no DuckDB, no network, no DB). ───────
        $rawRows = $this->readRawNdjson($input);

        $normalizer = new OverturePlaceNormalizer(new OvertureCategoryMap(), $confidenceMin);
        $result = $normalizer->normalize($rawRows);

        // ── Write the canonical normalized extract. ───────────────────────────
        $io = new NormalizedExtractIo();
        $output = (string) ($this->option('output')
            ?: storage_path("app/spatial/overture/{$region}_normalized_places.ndjson"));
        $written = $io->writeFile($output, $result->records);

        // ── Report. ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('  total input             : ' . $result->totalInput);
        $this->line('  kept                    : ' . $result->keptCount());
        $this->line('  rejected (unmapped)     : ' . $result->rejectedUnmapped);
        $this->line('  rejected (low confid.)  : ' . $result->rejectedLowConfidence);
        $this->line('  rejected (invalid)      : ' . $result->rejectedInvalid);
        $this->line('  fully accounted         : ' . ($result->isFullyAccounted() ? 'yes' : 'NO'));

        if ($result->unmappedTally !== []) {
            $this->newLine();
            $this->line('  unmapped primary categories (counted, not lost):');
            foreach ($result->unmappedTally as $token => $count) {
                $this->line("    - {$token}: {$count}");
            }
        }

        $projector = CorpusSizingProjector::fromConfig($config['sizing']);
        $sizing = $projector->project($result->keptCount());
        $this->newLine();
        $this->line('  storage projection (planning proxies):');
        $this->line("    kept rows      : {$sizing['rows']}");
        $this->line("    total (~{$projector->bytesPerRowTotal()} B/row) : {$sizing['total_human']}");
        $this->line("    gist  (~{$projector->gistBytesPerRow()} B/row) : {$sizing['gist_human']}");

        $this->newLine();
        $this->info("  wrote {$written} normalized rows → {$output}");

        return self::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readRawNdjson(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return $rows;
        }
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        fclose($fh);

        return $rows;
    }

    /** DuckDB presence probe — informational only; the command never requires it. */
    private function duckdbAvailable(): bool
    {
        $which = @shell_exec('command -v duckdb 2>/dev/null');

        return is_string($which) && trim($which) !== '';
    }
}
