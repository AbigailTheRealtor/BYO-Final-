<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M0 — name-leading indexes for {@see \App\Services\LocationDna\Criteria\Search\LocationPlaceSearchRepository}.
 *
 * WHY NEW INDEXES ARE NEEDED AT ALL WHEN THE COLUMNS ARE ALREADY INDEXED
 * ----------------------------------------------------------------------
 * Every existing name index in the corpus is a COMPOSITE LED BY STATE —
 * `census_counties_state_name_index` is `(state_geoid, name)`, `census_places_state_name_index` is
 * `(state_geoid, name)`, `location_places_state_key_index` is `(state_geoid, name_key)`. Those
 * serve the cascade perfectly, because enumeration always knows its state. Search does not: a user
 * types "Clearwater" before choosing anything, and a nationwide query cannot use an index whose
 * leading column it has no value for.
 *
 * `lower(...)` AND `text_pattern_ops`, BOTH DELIBERATE
 * ----------------------------------------------------
 * The repository compares `lower(column) LIKE 'term%'`, so the index must be on that same
 * expression — an index on the bare column would simply not be used. `text_pattern_ops` is what
 * makes a `LIKE 'x%'` prefix scan index-eligible regardless of the database collation; without it a
 * non-C collation silently falls back to a sequential scan and the index looks present while doing
 * nothing.
 *
 * POSTGRES ONLY, AND THAT IS NOT A GAP
 * -------------------------------------
 * Production is Postgres; the suite runs SQLite in-memory, which supports neither expression
 * indexes of this form nor `text_pattern_ops`. It does not need them: the whole corpus is ~110,000
 * rows and a SQLite scan of the largest table (32,188 places) is immaterial in a test. Guarding on
 * the driver keeps the suite honest rather than pretending the index exists there.
 *
 * NO TRIGRAM INDEX YET. `pg_trgm` is installed and fuzzy matching is planned, but M1 implements
 * exact, prefix and word-initial matching only. An index nothing queries is unverifiable overhead;
 * it lands with the fuzzy layer that uses it.
 *
 * SAFE TO RUN AND TO REVERSE. Creating an index changes no data, and these are static reference
 * tables of a few tens of thousands of rows, so the write lock is momentary. `IF NOT EXISTS` makes
 * re-running a no-op.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> index name => the expression it covers
     */
    private const INDEXES = [
        // THE CANONICAL PLACE LAYER — the tier a search hits most often, and the only index here
        // that carries both cities and neighbourhoods. `name_key` is the stored match surface, so
        // this is the column the repository actually compares.
        //
        // There is deliberately NO `census_places` index: search reads the canonical layer, which
        // mirrors all 32,188 census places, so an index on the published-name column would be
        // built and maintained for a query nobody issues.
        'location_places_lower_name_key_search_index' => 'location_places (lower(name_key) text_pattern_ops)',

        // Counties: both published forms — "Autauga County" and the bare "Autauga" — because the
        // repository matches either and a user types either. The canonical layer holds no counties,
        // so this tier still reads the census table.
        'census_counties_lower_name_search_index'     => 'census_counties (lower(name) text_pattern_ops)',
        'census_counties_lower_basename_search_index' => 'census_counties (lower(basename) text_pattern_ops)',

        // ZCTAs: the primary key is a char(5), which a collated LIKE prefix scan cannot use. The
        // ZIP tier stays on the ZCTA roster for identifier parity with the cascade — see
        // LocationPlaceSearchRepository::searchZips().
        'census_zctas_zcta5_search_index' => 'census_zctas (zcta5 text_pattern_ops)',
    ];

    public function up(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach (self::INDEXES as $name => $definition) {
            if (! $this->tableFor($definition)) {
                continue;
            }

            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$definition}");
        }
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Does the table this definition targets exist?
     *
     * The corpus tables are created by their own migrations, but an environment that has never run
     * `census:import-geography` still has them empty rather than absent — so this guards against
     * ordering surprises, not against emptiness. Indexing an empty table is harmless and correct.
     */
    private function tableFor(string $definition): bool
    {
        $table = strtok($definition, ' ');

        return $table !== false && Schema::hasTable($table);
    }
};
