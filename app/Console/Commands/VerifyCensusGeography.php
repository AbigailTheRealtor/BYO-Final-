<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-2 — is the Census corpus actually present and internally consistent?
 *
 * WHY A COMMAND AND NOT A CHECK IN THE BINDING
 * --------------------------------------------
 * Selecting `geography_source=census` with no corpus behind it does not fail. Every tier simply
 * enumerates empty, and the cascade renders dropdowns with nothing in them — no exception, no log
 * line, nothing that distinguishes "the data is missing" from "this state genuinely has no
 * counties". That is a worse outcome than a hard error, so something has to ask the question.
 *
 * It is not the container binding that asks it. A binding that queried a table would run that
 * query every time the repository is resolved, and would couple service resolution to database
 * availability on every request — including all the requests that never touch geography. So the
 * check lives here, to be run deliberately: before switching a source, and in the deploy sequence
 * of any environment that has switched.
 *
 * WHAT "CONSISTENT" MEANS HERE
 * ----------------------------
 * Not just "rows exist". The importer records what it wrote in `census_geography_meta`, so this
 * compares the recorded row count against the actual one. A table that was truncated, partially
 * restored, or written by something other than the importer will match on existence and fail
 * here — which is the entire reason the metadata table was created.
 *
 * READ-ONLY. It reports; it repairs nothing. Anything it finds is fixed by running the importer.
 */
class VerifyCensusGeography extends Command
{
    protected $signature = 'census:verify-geography
        {--vintage=2020 : The vintage the corpus should hold}';

    protected $description = 'Verify the census_* corpus is present, populated and consistent with its recorded metadata.';

    /** dataset => the table it populates. Mirrors the importer. */
    private const DATASETS = [
        'states'         => 'census_states',
        'counties'       => 'census_counties',
        'places'         => 'census_places',
        'place_counties' => 'census_place_counties',
        'zctas'          => 'census_zctas',
        'zcta_counties'  => 'census_zcta_counties',
    ];

    private const META_TABLE = 'census_geography_meta';

    public function handle(): int
    {
        $vintage  = (string) $this->option('vintage');
        $failures = [];
        $rows     = [];

        // ── 1 · Every table exists ───────────────────────────────────────────
        //
        // Checked first and separately: with no tables there is nothing to count, and a missing
        // table means the migrations have not run — a different problem with a different fix from
        // an empty one.
        $missing = [];

        foreach ([...array_values(self::DATASETS), self::META_TABLE] as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->error(
                'Missing table(s): '.implode(', ', $missing)
                .'. The Phase 1d-1 migrations have not run on this connection.'
            );

            return 1;
        }

        // ── 2 · Every dataset is populated, recorded, and agrees with its record ──
        foreach (self::DATASETS as $dataset => $table) {
            $actual = DB::table($table)->count();

            $meta = DB::table(self::META_TABLE)
                ->where('dataset', $dataset)
                ->where('vintage', $vintage)
                ->first();

            $recorded = $meta === null ? null : (int) $meta->row_count;
            $status   = [];

            if ($actual === 0) {
                $status[]   = 'EMPTY';
                $failures[] = "{$table} holds no rows.";
            }

            if ($meta === null) {
                $status[]   = 'NO METADATA';
                $failures[] = "No {$dataset} metadata recorded for vintage {$vintage}.";
            } else {
                if (trim((string) $meta->vintage) === '') {
                    $status[]   = 'NO VINTAGE';
                    $failures[] = "{$dataset} metadata carries no vintage.";
                }

                if ($recorded !== $actual) {
                    $status[]   = 'COUNT MISMATCH';
                    $failures[] = sprintf(
                        '%s holds %s row(s) but its metadata records %s. The table has been '
                        .'changed by something other than the importer.',
                        $table,
                        number_format($actual),
                        number_format((int) $recorded)
                    );
                }
            }

            $rows[] = [
                $dataset,
                number_format($actual),
                $recorded === null ? '—' : number_format($recorded),
                $meta === null ? '—' : (string) $meta->vintage,
                $meta?->imported_at === null ? '—' : (string) $meta->imported_at,
                $status === [] ? 'OK' : implode(', ', $status),
            ];
        }

        $this->table(
            ['Dataset', 'Rows', 'Recorded', 'Vintage', 'Imported at', 'Status'],
            $rows
        );

        // ── 3 · Nothing recorded under a vintage nobody asked for ────────────
        //
        // A corpus half-written at one vintage and half at another satisfies every check above,
        // because each dataset is inspected on its own. This is the one question that has to be
        // asked across all of them.
        $vintages = DB::table(self::META_TABLE)
            ->distinct()
            ->orderBy('vintage')
            ->pluck('vintage')
            ->map(static fn ($v): string => trim((string) $v))
            ->all();

        if (count($vintages) > 1) {
            $failures[] = sprintf(
                'The corpus records more than one vintage (%s). Every table must be pinned to a '
                .'single vintage or relationships cross publications.',
                implode(', ', $vintages)
            );
        }

        if ($failures !== []) {
            $this->newLine();
            $this->error('Census corpus verification FAILED:');

            foreach ($failures as $failure) {
                $this->line('  • '.$failure);
            }

            $this->newLine();
            $this->line('Run `php artisan census:import-geography` to rebuild the corpus.');

            return 1;
        }

        $this->info("Census corpus verified for vintage {$vintage}: all six datasets present, populated and consistent with their recorded metadata.");

        return 0;
    }
}
