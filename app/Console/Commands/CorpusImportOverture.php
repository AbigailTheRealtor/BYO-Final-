<?php

namespace App\Console\Commands;

use App\Services\Spatial\CorpusActivationService;
use App\Services\Spatial\CorpusCopyLoader;
use App\Services\Spatial\CorpusImportAcceptance;
use App\Services\Spatial\CorpusImportLedger;
use App\Services\Spatial\CorpusPartitionManager;
use App\Services\Spatial\CorpusSizingProjector;
use App\Services\Spatial\NormalizedExtractIo;
use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\PlaceRowMaterializer;
use Illuminate\Console\Command;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * OFFLINE import DRY-RUN / plan AUTHOR. Reads a canonical normalized NDJSON
 * extract (Batch 2A output), runs the import framework end-to-end WITHOUT a
 * cluster, and writes the load artifacts an operator would later execute:
 *   • the acceptance verdict (gate — a failure aborts before any artifact)
 *   • the partition DDL (create staging → check → attach)
 *   • the COPY payload + COPY statement
 *   • the ledger row (corpus_imports) + INSERT
 *   • the activation plan (attach + status flip)
 *
 * HARD constraints (owner decision — enforced here):
 *   • REFUSES to run in production. There is NO execute path at all.
 *   • Opens NO pgsql_spatial connection and reads NO SPATIAL_* secret — it only
 *     reads an NDJSON file and writes plan artifacts to local disk.
 *   • Live COPY / ATTACH / ledger writes are deferred to the Class-2 phase; the
 *     authored SQL here is the recipe that phase runs.
 */
class CorpusImportOverture extends Command
{
    protected $signature = 'corpus:import-overture
        {--region=pinellas : Region key from config/overture_places.php regions}
        {--input= : Normalized NDJSON extract (defaults to the extract command output path)}
        {--corpus-version= : Override the derived corpus_version tag}
        {--dataset=overture-places : Ledger dataset tag}
        {--previous-version= : Currently-active version to supersede on activation}
        {--out-dir= : Directory for the dry-run artifacts}';

    protected $description = 'OFFLINE: author the Overture import plan (partition/COPY/ledger/activation) from a normalized extract — no PostGIS, refuses production, never executes';

    public function handle(): int
    {
        // ── Guard: never in production. No override flag by design. ────────────
        if (app()->environment('production')) {
            $this->error('[Batch 2C] corpus:import-overture is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Live corpus load / partition attach / activation is deferred to the Class-2 phase.');

            return self::FAILURE;
        }

        $config = config('overture_places');
        $region = (string) $this->option('region');

        if (!isset($config['regions'][$region])) {
            $this->error("Unknown region [{$region}]. Known: " . implode(', ', array_keys($config['regions'])));

            return self::FAILURE;
        }

        $input = (string) ($this->option('input')
            ?: storage_path("app/spatial/overture/{$region}_normalized_places.ndjson"));
        if (!is_file($input)) {
            $this->error("Normalized extract not found: {$input}");
            $this->line('Produce one first with: php artisan corpus:extract-overture');

            return self::FAILURE;
        }

        $corpusVersion = (string) ($this->option('corpus-version')
            ?: "overture-{$config['release']}-{$region}");
        $dataset = (string) $this->option('dataset');
        $previousVersion = $this->option('previous-version') !== null
            ? (string) $this->option('previous-version')
            : null;

        $partitions = new CorpusPartitionManager();
        $partition = $partitions->partitionName($corpusVersion);

        $outDir = (string) ($this->option('out-dir')
            ?: storage_path("app/spatial/overture/import/{$partition}"));

        $this->info('[Batch 2C] Overture OFFLINE import — DRY RUN (no PostGIS, nothing executed)');
        $this->line("  release        : {$config['release']}");
        $this->line("  region         : {$region}");
        $this->line("  corpus_version : {$corpusVersion}");
        $this->line("  partition      : {$partition}");
        $this->line("  input          : {$input}");
        $this->line("  out dir        : {$outDir}");

        // ── Read the normalized extract (no DB, no network). ───────────────────
        $records = (new NormalizedExtractIo())->readFile($input);

        // ── Acceptance gate. A failure aborts before any load artifact. ────────
        $acceptance = new CorpusImportAcceptance(new OvertureCategoryMap(), (float) $config['confidence_min']);
        $verdict = $acceptance->evaluate($records);

        $this->newLine();
        $this->line('  acceptance:');
        foreach ($verdict['checks'] as $check) {
            $mark = $check['passed'] ? '✓' : '✗';
            $this->line("    {$mark} {$check['name']}: {$check['detail']}");
        }

        if (!$verdict['passed']) {
            $this->newLine();
            $this->error('[Batch 2C] Acceptance FAILED: ' . implode(', ', $verdict['failures']));
            $this->line('No load artifacts written. Fix the extract and re-run.');

            // Record the failed attempt in a ledger row (authored, not persisted).
            $failed = (new CorpusImportLedger())->build(
                dataset: $dataset,
                corpusVersion: $corpusVersion,
                rowCount: $verdict['row_count'],
                territoryCoverage: $this->territory($region, $config),
                status: CorpusImportLedger::STATUS_FAILED,
                notes: ['failures' => $verdict['failures']],
            );
            $this->writeArtifact($outDir, 'ledger_failed.json', $this->json($failed));

            return self::FAILURE;
        }

        $rowCount = $verdict['row_count'];

        // ── Materialize places rows + author the COPY payload. ─────────────────
        $materializer = new PlaceRowMaterializer();
        $rows = $materializer->toRows($records, $corpusVersion);

        $loader = new CorpusCopyLoader();
        $payloadPath = rtrim($outDir, '/') . '/copy_payload.txt';
        $written = $loader->writePayload($payloadPath, $rows);
        $copyStatement = $loader->copyStatement($partition);
        $psqlCopy = $loader->psqlCopyStatement($partition, $payloadPath);

        // ── Author the partition DDL (create staging → check → attach). ────────
        $ddl = implode("\n", [
            '-- 1. staging table off the parent',
            $partitions->createStagingTableSql($corpusVersion) . ';',
            '-- 2. COPY the corpus in (payload: ' . $payloadPath . ')',
            $psqlCopy,
            '-- 3. pin CHECK so ATTACH is an O(1) metadata flip',
            $partitions->addCheckConstraintSql($corpusVersion) . ';',
            '-- 4. attach as a live partition',
            $partitions->attachPartitionSql($corpusVersion) . ';',
        ]) . "\n";
        $this->writeArtifact($outDir, 'partition_load.sql', $ddl);

        // ── Ledger row (staging) + INSERT. ─────────────────────────────────────
        $ledgerSvc = new CorpusImportLedger(CorpusSizingProjector::fromConfig($config['sizing']));
        $ledgerRow = $ledgerSvc->build(
            dataset: $dataset,
            corpusVersion: $corpusVersion,
            rowCount: $rowCount,
            territoryCoverage: $this->territory($region, $config),
            status: CorpusImportLedger::STATUS_STAGING,
            notes: ['source' => $config['source'], 'confidence_min' => $config['confidence_min']],
        );
        $this->writeArtifact($outDir, 'ledger.json', $this->json([
            'insert_sql' => $ledgerSvc->insertSql(),
            'row'        => $ledgerRow,
        ]));

        // ── Activation plan. ───────────────────────────────────────────────────
        $activation = new CorpusActivationService($partitions, $ledgerSvc);
        $plan = $activation->plan($corpusVersion, $previousVersion);
        $this->writeArtifact($outDir, 'activate.sql', $activation->renderScript($plan));

        // ── Sizing projection (planning proxies). ──────────────────────────────
        $projector = CorpusSizingProjector::fromConfig($config['sizing']);
        $sizing = $projector->project($rowCount);

        // ── Report. ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->line("  materialized rows : {$rowCount} (COPY payload rows: {$written})");
        $this->line("  copy statement    : {$copyStatement}");
        $this->line('  storage projection (planning proxies):');
        $this->line("    total (~{$projector->bytesPerRowTotal()} B/row) : {$sizing['total_human']}");
        $this->line("    gist  (~{$projector->gistBytesPerRow()} B/row) : {$sizing['gist_human']}");
        $this->newLine();
        $this->line('  artifacts written (DRY RUN — nothing executed against a cluster):');
        foreach (['partition_load.sql', 'copy_payload.txt', 'ledger.json', 'activate.sql'] as $f) {
            $this->line('    - ' . rtrim($outDir, '/') . '/' . $f);
        }
        $this->newLine();
        $this->info('[Batch 2C] import plan authored. Live load is a Class-2 concern.');

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function territory(string $region, array $config): array
    {
        return [
            'region' => $region,
            'bbox'   => $config['regions'][$region],
            'crs'    => 'EPSG:4326',
        ];
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
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
