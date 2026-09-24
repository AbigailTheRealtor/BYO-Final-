<?php

namespace App\Console\Commands;

use App\Console\Concerns\RefusesProductionDatabase;
use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureV2Import\InvalidOvertureV2Import;
use App\Services\Spatial\OvertureV2Import\OvertureV2CorpusImporter;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportContract;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportGate;
use App\Support\Safeguards\ProductionDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports one validated `overture-extract-v2` output into the v2 spatial tables — IMPORT ONLY.
 *
 *   * Validates first, always: the declared contract for --corpus-version, the manifest, the
 *     files' checksums and counts, the running taxonomy / chain-registry pins, every row, and the
 *     chain census. Any disagreement refuses before a connection is opened.
 *   * Writes NOTHING unless --write is given. Without it the command is a dry run that proves the
 *     extraction is importable.
 *   * With --write: one atomic transaction into overture_v2_* on the connection NAMED by
 *     --database. There is no default target: --write without --database refuses before any
 *     connection is opened, so the configured spatial database is only ever written by an
 *     operator who typed its name. The connection must be a configured pgsql connection other than
 *     the application's default, with PostGIS. A ready corpus is never overwritten; re-importing
 *     it is a no-op.
 *   * Refuses production twice, with no override: the shared ProductionDatabaseGuard (any
 *     production signal in the environment or in any configured connection) and the application
 *     environment itself.
 *   * CANNOT ACTIVATE. It has no activation path, sets no flag and pins no version; Location DNA
 *     and every Overture provider gate are untouched. The best state it produces is `ready`.
 *   * Reads local files only — no network.
 *
 * Schema and contract: docs/spatial/overture-v2-corpus-schema.md.
 */
class CorpusImportOvertureV2 extends Command
{
    use RefusesProductionDatabase;

    protected $signature = 'corpus:import-overture-v2
        {--corpus-version= : A corpus version declared in config/overture_v2_corpus.php}
        {--extract-dir= : Directory holding base.ndjson, supplementary.ndjson and manifest.json from corpus:extract-overture-v2}
        {--write : Write to the named database (default: validate only, no connection opened)}
        {--database= : REQUIRED with --write, no default: the pgsql/PostGIS connection to write to}';

    protected $description = 'Validate, and with --write import, an Overture v2 extraction into the overture_v2_* spatial tables (import only, never activates, refuses production)';

    public function handle(): int
    {
        if ($this->refusesProductionDatabase()) {
            return ProductionDatabaseGuard::EXIT_REFUSED;
        }

        if (app()->environment('production')) {
            $this->error('corpus:import-overture-v2 REFUSES to run in production.');

            return self::FAILURE;
        }

        $version = (string) $this->option('corpus-version');
        $dir = (string) $this->option('extract-dir');
        if ($version === '' || $dir === '') {
            $this->error('--corpus-version and --extract-dir are required.');

            return self::FAILURE;
        }

        // The write target is decided before anything else is read, and only from what was typed.
        $connection = null;
        if ($this->option('write')) {
            $connection = $this->writeTarget();
            if ($connection === null) {
                return self::FAILURE;
            }
        }

        // Exact duplicate detection and the census keep every row in memory (~55k for Florida).
        ini_set('memory_limit', '2G');

        try {
            $contract = OvertureV2ImportContract::forVersion($version);
            $plan = (new OvertureV2ImportGate(ChainRegistry::load()))->validate($contract, $dir);
        } catch (InvalidOvertureV2Import $e) {
            $this->error('REFUSED, nothing written: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("overture v2 import — {$version}");
        $this->line(sprintf('  release / recipe / taxonomy : %s / %s / %s', $contract->sourceRelease, $contract->extractRecipeVersion, $contract->taxonomyMapVersion));
        $this->line(sprintf('  registry                    : %s  %s', $contract->registryVersion, $contract->registryRuleHash));
        $this->line(sprintf('  base rows (imported)        : %d', $plan->baseRows));
        $this->line(sprintf('  rescued rows (imported)     : %d', $plan->rescueAdmittedRows));
        $this->line(sprintf('  refused / diagnostic (not imported, counted) : %d / %d', $plan->rescueRefusedRows, $plan->diagnosticRows));
        $this->line(sprintf('  matcher-analysis rows       : %d  (base + supplementary; not a corpus count)', $plan->matcherAnalysisRows()));
        $this->line(sprintf('  chain memberships           : %d', $plan->membershipCount));

        if ($connection === null) {
            $this->info('VALIDATED — dry run, nothing written (pass --write --database=<connection> to import).');

            return self::SUCCESS;
        }

        try {
            $outcome = (new OvertureV2CorpusImporter())->import(DB::connection($connection), $plan);
        } catch (InvalidOvertureV2Import $e) {
            $this->error('FAILED, rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($outcome === OvertureV2CorpusImporter::ALREADY_READY) {
            $this->info("ALREADY READY — {$version} was imported from these exact files before; nothing written.");

            return self::SUCCESS;
        }
        $this->info("IMPORTED — {$version} is ready (not active; nothing was activated).");

        return self::SUCCESS;
    }

    /**
     * The connection --write may use, or null after reporting why not. Reads configuration only;
     * no connection is opened here. There is deliberately no fallback: an absent --database is a
     * refusal, never the configured spatial connection.
     */
    private function writeTarget(): ?string
    {
        $name = $this->option('database');
        if (! is_string($name) || trim($name) === '') {
            $this->error('REFUSED, nothing written: --write requires an explicit --database=<connection>. There is no default write target.');

            return null;
        }
        $connections = config('database.connections');
        if (! is_array($connections) || ! is_array($connections[$name] ?? null)) {
            $this->error("REFUSED, nothing written: [{$name}] is not a configured database connection.");

            return null;
        }
        if ($name === config('database.default')) {
            $this->error("REFUSED, nothing written: [{$name}] is the application's default connection, not a spatial one.");

            return null;
        }
        if (($connections[$name]['driver'] ?? null) !== 'pgsql') {
            $this->error("REFUSED, nothing written: connection [{$name}] is not PostgreSQL; the v2 corpus needs PostGIS.");

            return null;
        }

        return $name;
    }
}
