<?php

namespace App\Console\Commands;

use App\Services\Spatial\BoundaryImportAcceptance;
use App\Services\Spatial\BoundaryNormalizationResult;
use App\Services\Spatial\BoundaryRecord;
use App\Services\Spatial\BoundaryRowMaterializer;
use App\Services\Spatial\BoundarySource;
use Illuminate\Console\Command;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3 (boundary import authoring).
 *
 * Source-agnostic OFFLINE boundary import DRY-RUN / plan author, shared by every registered boundary
 * source (PAD-US = C3a, Census TIGER county/place/ZCTA/school-district = C3b, …).
 * Reads a raw boundary NDJSON, normalizes it through
 * the registered {@see BoundarySource} adapter, gates the result with {@see BoundaryImportAcceptance},
 * and writes the artifacts an operator would use at Class-2:
 *   • boundaries.ndjson — the canonical BoundaryRecord rows (the staging/load input)
 *   • staging.json      — the ordered boundaries COPY rows (columns == materializer COLUMNS; geom EWKT)
 *   • summary.json      — counts, kind, acceptance verdict
 *   • rejects.json      — the counted, reasoned rejects (never silently dropped)
 *
 * HARD constraints (owner decision — enforced here):
 *   • REFUSES to run in production. There is NO execute path against a cluster.
 *   • Opens NO pgsql_spatial connection and reads NO SPATIAL_* secret — it reads one local NDJSON
 *     file and writes plan artifacts to local disk. Live staging + load (COPY into boundaries, then
 *     ST_Subdivide into boundaries_parts) is the Class-2 recipe in the per-slice spikes under spikes/.
 */
class CorpusImportBoundaries extends Command
{
    protected $signature = 'corpus:import-boundaries
        {--source= : Boundary source key from config/spatial_boundaries.php (e.g. padus)}
        {--in= : Raw source NDJSON (defaults to the committed synthetic fixture for the source)}
        {--out-dir= : Directory for the dry-run artifacts}';

    protected $description = 'OFFLINE: author a boundary import plan (raw source → BoundaryRecord staging) — no PostGIS, refuses production, never executes';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('[corpus:import-boundaries] corpus:import-boundaries is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Live boundary staging + load (ST_Subdivide into boundaries_parts) is deferred to the Class-2 phase.');

            return self::FAILURE;
        }

        $sources = (array) config('spatial_boundaries.sources', []);
        $key = (string) $this->option('source');

        if ($key === '' || !isset($sources[$key])) {
            $this->error("Unknown --source [{$key}]. Known: " . implode(', ', array_keys($sources)));

            return self::FAILURE;
        }

        $cfg = $sources[$key];
        /** @var BoundarySource $source */
        $source = new $cfg['class']();

        $inPath = (string) ($this->option('in') ?: base_path($cfg['fixture']));
        if (!is_file($inPath)) {
            $this->error("Raw source NDJSON not found: {$inPath}");

            return self::FAILURE;
        }

        $outDir = (string) ($this->option('out-dir')
            ?: storage_path(config('spatial_boundaries.out_dir', 'app/spatial/boundaries') . '/' . $key));

        $rawRows = $this->readRawNdjson($inPath);
        $result = $source->normalize($rawRows);

        $acceptance = new BoundaryImportAcceptance(null, [$source->kind()]);
        $verdict = $acceptance->evaluate($result->records);

        $this->info('[corpus:import-boundaries] Boundary import — DRY RUN (no PostGIS, nothing executed)');
        $this->line("  source   : {$key} ({$cfg['label']})");
        $this->line("  kind     : {$source->kind()}");
        $this->line("  input    : {$inPath} (" . $result->totalInput . ' raw rows)');
        $this->newLine();
        $this->line('  normalization:');
        $this->line('    kept                     : ' . $result->keptCount());
        $this->line('    rejected_invalid_geometry : ' . $result->rejectedInvalidGeometry);
        $this->line('    rejected_invalid_field   : ' . $result->rejectedInvalidField);
        $this->line('    fully_accounted          : ' . ($result->isFullyAccounted() ? 'yes' : 'NO'));
        $this->newLine();
        $this->line('  acceptance:');
        foreach ($verdict['checks'] as $check) {
            $this->line('    ' . ($check['passed'] ? '✓' : '✗') . " {$check['name']}: {$check['detail']}");
        }

        if (!$verdict['passed']) {
            $this->newLine();
            $this->error('[corpus:import-boundaries] Acceptance FAILED: ' . implode(', ', $verdict['failures']));
            $this->line('No boundary artifacts written.');
            $this->writeArtifact($outDir, 'rejects.json', $this->json($this->rejects($result, $verdict)));

            return self::FAILURE;
        }

        $materializer = new BoundaryRowMaterializer();
        // Derived from the source key (D-C3b-2) — an offline placeholder tag; the real
        // corpus_version is assigned at Class-2 load time.
        $corpusVersion = "{$key}-authoring-fixture";
        $staging = [
            'columns'        => BoundaryRowMaterializer::COLUMNS,
            'corpus_version' => $corpusVersion,
            'rows'           => $materializer->toRows($result->records, $corpusVersion),
        ];

        $summary = [
            'source'        => $key,
            'kind'          => $source->kind(),
            'normalization' => $result->summary(),
            'acceptance'    => ['passed' => $verdict['passed'], 'checks' => $verdict['checks']],
        ];

        $this->writeArtifact($outDir, 'boundaries.ndjson', BoundaryRecord::toNdjson($result->records));
        $this->writeArtifact($outDir, 'staging.json', $this->json($staging));
        $this->writeArtifact($outDir, 'summary.json', $this->json($summary));
        $this->writeArtifact($outDir, 'rejects.json', $this->json($this->rejects($result, $verdict)));

        $this->newLine();
        $this->line('  artifacts written (DRY RUN — nothing executed against a cluster):');
        foreach (['boundaries.ndjson', 'staging.json', 'summary.json', 'rejects.json'] as $f) {
            $this->line('    - ' . rtrim($outDir, '/') . '/' . $f);
        }
        $this->newLine();
        $this->info('[corpus:import-boundaries] boundary plan authored. Live load + ST_Subdivide is a Class-2 concern.');

        return self::SUCCESS;
    }

    /**
     * @param array{failures:list<string>} $verdict
     * @return array<string,mixed>
     */
    private function rejects(BoundaryNormalizationResult $result, array $verdict): array
    {
        return [
            'rejected_invalid_geometry' => $result->rejectedInvalidGeometry,
            'rejected_invalid_field'    => $result->rejectedInvalidField,
            'reject_reasons'            => $result->rejectReasons,
            'acceptance_failures'       => $verdict['failures'],
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
