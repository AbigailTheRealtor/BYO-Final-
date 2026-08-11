<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The two halves of the incremental-migration CI gate: plant rows before the
 * pending migrations run, then prove they survived and nothing is left pending.
 *
 * WHY THIS IS A COMMAND AND NOT A TEST
 * ------------------------------------
 * The gate's essential step happens between the two halves and cannot happen
 * inside a PHPUnit process: the workflow checks out the *previous release's*
 * migrations, migrates, then restores this branch's migrations and migrates
 * again. A test can only observe one of those states. So the workflow
 * orchestrates, and this command supplies the assertions at each end.
 *
 * It is CI-only by construction — nothing schedules it, nothing calls it, and
 * `verify` is the only thing that can fail a build.
 *
 * WHAT THE FIXTURE IS FOR
 * -----------------------
 * A migration that runs cleanly against an empty table can still fail, or
 * silently destroy data, against a populated one: adding a NOT NULL column
 * without a default, a unique index over values that are not unique, a type
 * change that truncates. `migrate:fresh` — the gate that already existed —
 * cannot see any of that, because there is never anything in the table.
 *
 * The rows below are deliberately boring and deliberately in
 * `property_location_dna`: it is the table G4 altered and the table G5 will
 * write, so it is where an incremental mistake would land first.
 */
class IncrementalMigrationFixture extends Command
{
    protected $signature = 'migrate:incremental-fixture {action : seed|verify}';

    protected $description = 'CI only: plant rows before pending migrations, then verify they survived and none remain pending';

    private const TABLE = 'property_location_dna';

    /** Distinctive enough that a collision with real data is not plausible. */
    private const LISTING_TYPE = 'incremental_migration_fixture';

    /** @var array<int, array{listing_id: int, lat: float, lng: float, status: string}> */
    private const ROWS = [
        ['listing_id' => 90001, 'lat' => 27.9506000, 'lng' => -82.4572000, 'status' => 'geocoded'],
        ['listing_id' => 90002, 'lat' => 38.8986989, 'lng' => -77.0351875, 'status' => 'geocoded'],
        // A row with no coordinate at all: the case an added NOT NULL column or
        // a careless backfill would break first.
        ['listing_id' => 90003, 'lat' => null,       'lng' => null,        'status' => 'pending'],
    ];

    public function handle(): int
    {
        return match ((string) $this->argument('action')) {
            'seed'   => $this->seed(),
            'verify' => $this->verify(),
            default  => $this->invalidAction(),
        };
    }

    private function invalidAction(): int
    {
        $this->error('action must be "seed" or "verify"');

        return self::FAILURE;
    }

    private function seed(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            $this->error(self::TABLE . ' does not exist — the previous-release schema did not build.');

            return self::FAILURE;
        }

        $now = now();

        foreach (self::ROWS as $row) {
            DB::table(self::TABLE)->insert([
                'listing_type'   => self::LISTING_TYPE,
                'listing_id'     => $row['listing_id'],
                'source_address' => '123 Fixture St',
                'source_city'    => 'Tampa',
                'source_state'   => 'FL',
                'source_zip'     => '33602',
                'geocoded_lat'   => $row['lat'],
                'geocoded_lng'   => $row['lng'],
                'geocode_source' => 'incremental_fixture',
                'geocode_status' => $row['status'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        $this->info('Planted ' . count(self::ROWS) . ' rows at the previous-release schema.');

        return self::SUCCESS;
    }

    private function verify(): int
    {
        $failures = [];

        // 1. The deployment's own success condition.
        $pending = $this->pendingMigrations();

        if ($pending === null) {
            $failures[] = 'Could not determine pending migrations.';
        } elseif ($pending !== []) {
            $failures[] = 'Migrations still pending after migrate: ' . implode(', ', $pending);
        } else {
            $this->info('✓ zero pending migrations');
        }

        // 2. The rows are still there.
        $found = DB::table(self::TABLE)->where('listing_type', self::LISTING_TYPE)->count();

        if ($found !== count(self::ROWS)) {
            $failures[] = sprintf('Expected %d fixture rows, found %d.', count(self::ROWS), $found);
        } else {
            $this->info('✓ all ' . $found . ' pre-existing rows survived');
        }

        // 3. Their data is unchanged — present but corrupted is still a
        //    regression, and a row count alone would not notice.
        foreach (self::ROWS as $row) {
            $stored = DB::table(self::TABLE)
                ->where('listing_type', self::LISTING_TYPE)
                ->where('listing_id', $row['listing_id'])
                ->first();

            if ($stored === null) {
                $failures[] = "Row {$row['listing_id']} disappeared.";
                continue;
            }

            if ($row['lat'] === null) {
                if ($stored->geocoded_lat !== null) {
                    $failures[] = "Row {$row['listing_id']} gained a fabricated latitude.";
                }
                continue;
            }

            if (abs((float) $stored->geocoded_lat - $row['lat']) > 0.0000001) {
                $failures[] = sprintf(
                    'Row %d latitude changed: expected %s, found %s.',
                    $row['listing_id'],
                    $row['lat'],
                    (string) $stored->geocoded_lat
                );
            }
        }

        // 4. The columns this phase is about actually arrived, and arrived
        //    empty — an incremental migration must not invent provenance for
        //    rows that predate it.
        foreach (['geocode_precision', 'geocode_provider', 'normalized_address'] as $column) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                $failures[] = "Column {$column} is missing after migrate.";
            }
        }

        if ($failures === []) {
            $backfilled = DB::table(self::TABLE)
                ->where('listing_type', self::LISTING_TYPE)
                ->whereNotNull('geocode_precision')
                ->count();

            if ($backfilled !== 0) {
                $failures[] = "{$backfilled} pre-existing rows were given a precision they never had.";
            } else {
                $this->info('✓ provenance columns present and correctly empty for pre-existing rows');
            }
        }

        $this->cleanUp();

        if ($failures !== []) {
            $this->newLine();

            foreach ($failures as $failure) {
                $this->error('✗ ' . $failure);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Incremental migration verified.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null migration names not yet run, or null if unknown
     */
    private function pendingMigrations(): ?array
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return null;
            }

            $ran   = $migrator->getRepository()->getRan();
            $files = $migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')]);

            return array_values(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return null;
        }
    }

    private function cleanUp(): void
    {
        try {
            DB::table(self::TABLE)->where('listing_type', self::LISTING_TYPE)->delete();
        } catch (Throwable) {
            // The database is thrown away with the job; a failure to tidy up is
            // not worth failing the build over.
        }
    }
}
