<?php

namespace App\Console\Commands;

use App\Services\Schema\ProvenanceSchemaReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Print what this release is about to start against. Never fails the deploy.
 *
 * WHY A REPORT AND NOT A GATE
 * ---------------------------
 * Everything below is a question somebody has had to guess at from outside the
 * running container: which environment mode is active, whether debug output is
 * on, which database is attached, whether the schema is current. In the
 * workspace those are one command away. In a deployment they are not
 * inspectable at all — which is how the provenance migration came to be merged,
 * released, and still missing from the deployed schema without anything
 * reporting it.
 *
 * One line in the deployment log answers all of them permanently.
 *
 * It is deliberately NOT a gate. `deploy/start-production.sh` calls it with
 * `|| true`, and it exits zero even when it prints a warning. A preflight that
 * can refuse to start the application is a new way for deploys to fail, and the
 * conditions it reports on are exactly the ones whose semantics are not yet
 * established (see the APP_DEBUG note below). Blocking a release on a rule we
 * cannot yet verify would be guessing with the production deploy as the
 * experiment. `php artisan migrate` immediately after is the real gate.
 *
 * THE APP_DEBUG WARNING
 * ---------------------
 * `.replit` declares APP_ENV=local and APP_DEBUG=true under `[userenv.shared]`,
 * and defines no production override. Whether a Replit VM deployment inherits
 * `[userenv.shared]` is **not documented** — the published `.replit`
 * configuration reference does not mention `[userenv]` at all — so it could not
 * be established by reading anything available to us.
 *
 * Rather than change those values on a guess, this prints them. If a deployment
 * really does inherit them, the very next deploy log says so in plain text and
 * the question is settled by evidence instead of argument. APP_DEBUG=true in a
 * production deployment renders full stack traces to visitors, including
 * database credentials and environment values, so it is worth settling.
 */
class DeployPreflight extends Command
{
    protected $signature = 'deploy:preflight';

    protected $description = 'Report the environment, database and schema state this release will start against (informational; never fails)';

    public function handle(): int
    {
        $this->line('── deploy preflight ────────────────────────────────');

        $env   = (string) config('app.env');
        $debug = (bool) config('app.debug');

        $this->line('  APP_ENV        ' . $env);
        $this->line('  APP_DEBUG      ' . ($debug ? 'true' : 'false'));
        $this->line('  DB_CONNECTION  ' . (string) config('database.default'));
        $this->line('  DB_DATABASE    ' . $this->databaseName());
        $this->line('  pending migrs  ' . $this->pendingMigrationCount());
        $this->line('  provenance     ' . $this->provenanceState());

        if ($debug) {
            $this->newLine();
            $this->warn('  APP_DEBUG is TRUE.');
            $this->line('  If this is a production deployment, stack traces — including database');
            $this->line('  credentials and environment values — are rendered to visitors.');
            $this->line('  See deploy/DEPLOYMENT.md ("Environment mode").');
        }

        $this->line('────────────────────────────────────────────────────');

        // Always zero. This reports; migrate gates.
        return self::SUCCESS;
    }

    /** Database name only — never the host, user or password. */
    private function databaseName(): string
    {
        try {
            $name = DB::connection()->getDatabaseName();

            return $name !== '' ? (string) $name : '(unknown)';
        } catch (Throwable) {
            return '(unreachable)';
        }
    }

    private function pendingMigrationCount(): string
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return 'unknown (migrations table absent — first deploy?)';
            }

            $ran = $migrator->getRepository()->getRan();

            // Mirror Laravel's own BaseCommand::getMigrationPaths(): the application's
            // migration directory is MERGED with the paths packages registered, never
            // treated as a fallback for them.
            //
            // `Migrator::paths()` returns only the extras registered through
            // loadMigrationsFrom(), and Sanctum registers one — so `?:` never fell back
            // and database_path('migrations') was excluded from the scan entirely. This
            // reported "0 pending" while application migrations were genuinely pending.
            //
            // Order matters and matches the framework: registered paths first, the
            // application path last. getMigrationFiles() keys by migration name, so for a
            // migration both ship (personal_access_tokens) the later entry wins — which
            // must be the application's copy.
            $files = $migrator->getMigrationFiles(
                array_merge($migrator->paths(), [database_path('migrations')])
            );

            return (string) count(array_diff(array_keys($files), $ran));
        } catch (Throwable $e) {
            return '(could not determine: ' . $e->getMessage() . ')';
        }
    }

    private function provenanceState(): string
    {
        return ProvenanceSchemaReadiness::isReady()
            ? 'ready'
            : 'NOT READY — coordinate provenance will be skipped';
    }
}
