<?php

namespace App\Console\Commands;

use App\Support\Migrations\MigrationPaths;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Answer one question with an exit code: is this code's complete migration set
 * already applied to the attached database?
 *
 * WHY THIS COMMAND HAD TO BE WRITTEN
 * ----------------------------------
 * Nothing in this application could answer that question mechanically.
 *
 *   * `migrate:status` cannot. On Laravel 8 its handler renders a table and
 *     returns null, which Symfony turns into exit 0. It exits non-zero for
 *     exactly one condition — a missing migrations table — and returns the same
 *     zero whether nothing is pending or everything is. A script that runs it
 *     and checks `$?` is checking that the command ran, not that the schema is
 *     current. There is also no `--pending` option on this version to fall back
 *     to (`StatusCommand::getOptions()` offers `database`, `path`, `realpath`),
 *     so parsing the human-readable table is the only alternative, and a table
 *     whose "No" column is coloured by ANSI escapes is not a contract.
 *
 *   * `deploy:preflight` cannot, by design. It is a report that always exits
 *     zero — `deploy/start-production.sh` even calls it with `|| true` — and its
 *     pending figure is one field in a block of prose that can legitimately read
 *     `unknown (migrations table absent — first deploy?)`. Making it fail would
 *     turn an informational line into a new way for deploys to break.
 *
 * So this is the machine-readable half: one line on stdout, one authoritative
 * exit code, and no side effects at all.
 *
 * FAIL-CLOSED
 * -----------
 * "I could not determine the answer" and "the answer is no" are both non-zero.
 * The whole value of a readiness gate is that a caller can write
 *
 *     php artisan deploy:migrations-pending || <do not proceed>
 *
 * and be right. A gate that exits zero when the migrator could not be resolved,
 * when the repository could not be read, or when discovery found nothing to
 * discover is worse than no gate, because it converts an unknown into a
 * confident yes. Every uncertain branch below therefore exits non-zero, and the
 * pending count is only ever printed when it was genuinely computed.
 *
 * The zero-migrations-discovered branch is part of that. `pending=0` computed
 * over an empty file set is arithmetically true and operationally meaningless —
 * it is precisely what the `?:` path-resolution bug produced. This command
 * refuses to report it.
 *
 * EXIT CODES
 * ----------
 *   0  ready       — every discovered migration is recorded as run
 *   1  pending     — at least one migration is not applied
 *   2  undetermined— the answer could not be established
 *
 * 1 and 2 are separated so a caller can distinguish "migrate and retry" from
 * "something is wrong with the environment". A caller that does not care may
 * treat any non-zero identically, which is the intended default.
 *
 * OUTPUT
 * ------
 * Exactly one line on stdout, always, in every branch:
 *
 *     pending=0
 *     pending=3
 *     error=repository_missing
 *
 * The error values are a closed set of stable tokens, never exception messages.
 * That is deliberate and not merely tidiness: a PDO exception thrown while
 * reading the migrations table routinely carries the DSN, and a DSN carries the
 * host, the database name and sometimes the user. This command is built to be
 * run inside a deployment log, so it must not be capable of putting those there.
 * Under `-v` the pending migration NAMES are added on later lines; migration
 * filenames are already in the repository and reveal nothing the code does not.
 *
 * READ-ONLY
 * ---------
 * Reads the `migrations` table and stats migration directories. It runs no
 * migration, opens no transaction, writes no row, alters no schema, seeds
 * nothing and touches no file. See DeployMigrationsPendingTest, which asserts
 * that rather than trusting this paragraph.
 */
class DeployMigrationsPending extends Command
{
    protected $signature = 'deploy:migrations-pending';

    protected $description = 'Read-only, fail-closed check that every migration this code ships is already applied (exit 0 = ready)';

    /** Every migration this code ships is recorded as run. */
    public const EXIT_READY = 0;

    /** At least one migration is not applied. */
    public const EXIT_PENDING = 1;

    /** The answer could not be established. Never treat as ready. */
    public const EXIT_UNDETERMINED = 2;

    public function handle(): int
    {
        try {
            $migrator = $this->migrator();
        } catch (Throwable) {
            return $this->undetermined('migrator_unavailable');
        }

        // repositoryExists() issues a query, so it can fail for reasons that have
        // nothing to do with the table being absent — an unreachable host, a
        // rejected credential. Those must not be reported as "first deploy?".
        try {
            $repositoryExists = $migrator->repositoryExists();
        } catch (Throwable) {
            return $this->undetermined('repository_unreadable');
        }

        if (! $repositoryExists) {
            return $this->undetermined('repository_missing');
        }

        try {
            $ran = $migrator->getRepository()->getRan();
        } catch (Throwable) {
            return $this->undetermined('repository_read_failed');
        }

        $applicationPath = database_path('migrations');

        if (! is_dir($applicationPath)) {
            return $this->undetermined('application_path_missing');
        }

        try {
            $files = $migrator->getMigrationFiles(MigrationPaths::forScanning($migrator));
        } catch (Throwable) {
            return $this->undetermined('discovery_failed');
        }

        // Discovering nothing is not the same as having nothing pending. This
        // application ships hundreds of migrations; an empty set means the scan
        // looked in the wrong place, which is the exact shape of the bug this
        // command exists to make impossible to ship again.
        if ($files === []) {
            return $this->undetermined('no_migrations_discovered');
        }

        $pending = array_values(array_diff(array_keys($files), $ran));

        $this->line('pending=' . count($pending));

        if ($pending === []) {
            return self::EXIT_READY;
        }

        if ($this->getOutput()->isVerbose()) {
            sort($pending);

            foreach ($pending as $name) {
                $this->line('  ' . $name);
            }
        }

        return self::EXIT_PENDING;
    }

    /**
     * Resolve the migrator without going through app('migrator')'s untyped
     * return, so a mis-bound container entry fails here rather than at the first
     * method call.
     */
    private function migrator(): Migrator
    {
        $migrator = $this->laravel->make('migrator');

        if (! $migrator instanceof Migrator) {
            throw new \RuntimeException('The container did not resolve a Migrator.');
        }

        return $migrator;
    }

    /**
     * One stable token, no exception text. See the class docblock on why the
     * message is never included.
     */
    private function undetermined(string $token): int
    {
        $this->line('error=' . $token);

        return self::EXIT_UNDETERMINED;
    }
}
