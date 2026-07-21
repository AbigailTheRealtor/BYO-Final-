<?php

namespace App\Console\Commands;

use App\Services\Spatial\AuthorityOverlayAcceptance;
use App\Services\Spatial\AuthorityOverlaySource;
use App\Services\Spatial\AuthorityRecord;
use App\Services\Spatial\AuthorityStagingMaterializer;
use Illuminate\Console\Command;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * OFFLINE authority-overlay import DRY-RUN / plan author. Reads a raw authority-source NDJSON,
 * normalizes it through the registered {@see AuthorityOverlaySource} adapter, gates the result with
 * {@see AuthorityOverlayAcceptance}, and writes the artifacts an operator would use at Class-2:
 *   • overlay.ndjson  — the canonical AuthorityRecord rows (the C1 linker / staging input)
 *   • staging.json    — the ordered authority_staging COPY rows (columns == materializer COLUMNS)
 *   • summary.json    — counts, target, metric label, thresholds, acceptance verdict
 *   • rejects.json    — the counted, reasoned rejects (never silently dropped)
 *
 * HARD constraints (owner decision — enforced here):
 *   • REFUSES to run in production. There is NO execute path against a cluster.
 *   • Opens NO pgsql_spatial connection and reads NO SPATIAL_* secret — it reads one local NDJSON
 *     file and writes plan artifacts to local disk. Live staging + load (COPY into authority_staging,
 *     then the C1 linker for overlays or INSERT INTO places for base sources) is the Class-2 recipe
 *     in spikes/phase-2-batch-2d-part-c2-authority-overlay-import/sql/.
 */
class CorpusImportAuthorityOverlay extends Command
{
    protected $signature = 'corpus:import-authority-overlay
        {--source= : Authority source key from config/spatial_authority_overlay.php (e.g. cms, usgs-boat-ramp)}
        {--in= : Raw source NDJSON (defaults to the committed synthetic fixture for the source)}
        {--out-dir= : Directory for the dry-run artifacts}';

    protected $description = 'OFFLINE: author an authority-overlay import plan (raw source → AuthorityRecord staging) — no PostGIS, refuses production, never executes';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('[Batch 2D Part C2] corpus:import-authority-overlay is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Live authority-overlay staging + load is deferred to the Class-2 phase.');

            return self::FAILURE;
        }

        $sources = (array) config('spatial_authority_overlay.sources', []);
        $key = (string) $this->option('source');

        if ($key === '' || !isset($sources[$key])) {
            $this->error("Unknown --source [{$key}]. Known: " . implode(', ', array_keys($sources)));

            return self::FAILURE;
        }

        $cfg = $sources[$key];
        /** @var AuthorityOverlaySource $source */
        $source = new $cfg['class']();

        $inPath = (string) ($this->option('in') ?: base_path($cfg['fixture']));
        if (!is_file($inPath)) {
            $this->error("Raw source NDJSON not found: {$inPath}");

            return self::FAILURE;
        }

        $outDir = (string) ($this->option('out-dir')
            ?: storage_path(config('spatial_authority_overlay.out_dir', 'app/spatial/authority/overlay') . '/' . $key));

        $rawRows = $this->readRawNdjson($inPath);
        $result = $source->normalize($rawRows);

        $acceptance = new AuthorityOverlayAcceptance($source->sourceKey(), $source->metricDomain());
        $verdict = $acceptance->evaluate($result->records);

        $this->info('[Batch 2D Part C2] Authority-overlay import — DRY RUN (no PostGIS, nothing executed)');
        $this->line("  source   : {$key} ({$cfg['label']})");
        $this->line("  target   : {$source->target()} (" . ($source->target() === 'link' ? 'overlay → place_authority_links' : 'base source → places') . ')');
        $this->line('  metric   : ' . ($source->metricLabel() ?? '(membership — no numeric metric)'));
        $this->line("  input    : {$inPath} (" . $result->totalInput . ' raw rows)');
        $this->newLine();
        $this->line('  normalization:');
        $this->line('    kept                 : ' . $result->keptCount());
        $this->line('    rejected_invalid     : ' . $result->rejectedInvalid);
        $this->line('    rejected_out_of_domain : ' . $result->rejectedOutOfDomain);
        $this->line('    fully_accounted      : ' . ($result->isFullyAccounted() ? 'yes' : 'NO'));
        $this->newLine();
        $this->line('  acceptance:');
        foreach ($verdict['checks'] as $check) {
            $this->line('    ' . ($check['passed'] ? '✓' : '✗') . " {$check['name']}: {$check['detail']}");
        }

        if (!$verdict['passed']) {
            $this->newLine();
            $this->error('[Batch 2D Part C2] Acceptance FAILED: ' . implode(', ', $verdict['failures']));
            $this->line('No overlay artifacts written.');
            $this->writeArtifact($outDir, 'rejects.json', $this->json($this->rejects($result, $verdict)));

            return self::FAILURE;
        }

        $materializer = new AuthorityStagingMaterializer();
        $staging = [
            'columns' => AuthorityStagingMaterializer::COLUMNS,
            'target'  => $source->target(),
            'rows'    => $materializer->toRows($result->records),
        ];

        $summary = [
            'source'        => $key,
            'authority_source' => $source->sourceKey(),
            'target'        => $source->target(),
            'metric_label'  => $source->metricLabel(),
            'metric_domain' => $source->metricDomain(),
            'normalization' => $result->summary(),
            'acceptance'    => ['passed' => $verdict['passed'], 'checks' => $verdict['checks']],
        ];

        $this->writeArtifact($outDir, 'overlay.ndjson', AuthorityRecord::toNdjson($result->records));
        $this->writeArtifact($outDir, 'staging.json', $this->json($staging));
        $this->writeArtifact($outDir, 'summary.json', $this->json($summary));
        $this->writeArtifact($outDir, 'rejects.json', $this->json($this->rejects($result, $verdict)));

        $this->newLine();
        $this->line('  artifacts written (DRY RUN — nothing executed against a cluster):');
        foreach (['overlay.ndjson', 'staging.json', 'summary.json', 'rejects.json'] as $f) {
            $this->line('    - ' . rtrim($outDir, '/') . '/' . $f);
        }
        $this->newLine();
        $this->info('[Batch 2D Part C2] overlay plan authored. Live staging + load is a Class-2 concern.');

        return self::SUCCESS;
    }

    /**
     * @param array{failures:list<string>} $verdict
     * @return array<string,mixed>
     */
    private function rejects(\App\Services\Spatial\AuthorityOverlayNormalizationResult $result, array $verdict): array
    {
        return [
            'rejected_invalid'       => $result->rejectedInvalid,
            'rejected_out_of_domain' => $result->rejectedOutOfDomain,
            'reject_reasons'         => $result->rejectReasons,
            'acceptance_failures'    => $verdict['failures'],
        ];
    }

    /**
     * Parse a raw NDJSON file into decoded assoc rows (blank lines ignored).
     *
     * @return list<array<string,mixed>>
     */
    private function readRawNdjson(string $path): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $rows[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $rows;
    }

    private function writeArtifact(string $dir, string $file, string $contents): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(rtrim($dir, '/') . '/' . $file, $contents);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }
}
