<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Driver-aware, following the pattern established by
 * 2026_07_05_000001_add_lookup_indexes_to_bridge_properties: on PostgreSQL (production)
 * CREATE INDEX CONCURRENTLY so the live table is never locked; on any other driver (the
 * SQLite test harness) the standard Schema builder. Transaction wrapper disabled because
 * CONCURRENTLY cannot run inside a transaction.
 *
 * The pgsql branch is byte-for-byte as it shipped.
 *
 * SQLITE FIDELITY NOTE — partial indexes are not reproduced
 * --------------------------------------------------------
 * Nine of these are PARTIAL indexes (`WHERE … = TRUE`), and Blueprint::index() cannot
 * express a predicate. The SQLite fallback therefore creates FULL indexes over the same
 * columns. This is a difference in index selectivity, never in query results: an index
 * changes the plan the planner picks, not the rows it returns. No test's correctness can
 * depend on it. The fallback exists so the test schema carries the same index NAMES and
 * columns as production, not the same planner economics.
 *
 * Postdates database/schema/pgsql-schema.dump, so it still executes on a fresh
 * PostgreSQL database.
 */
class AddPhase1IndexesToBridgeProperties extends Migration
{
    /**
     * Disable transaction wrapper — CREATE INDEX CONCURRENTLY cannot run
     * inside a PostgreSQL transaction. Laravel honours this flag to skip
     * the transaction wrapper for this migration class.
     */
    public $withinTransaction = false;

    /**
     * name => column(s), for the SQLite fallback only. Mirrors the PostgreSQL statements
     * below, minus the `WHERE … = TRUE` predicates that Blueprint cannot express.
     */
    private const FALLBACK_INDEXES = [
        'bridge_properties_lat_lng_idx'              => ['latitude', 'longitude'],
        'bridge_properties_county_or_parish_idx'     => 'county_or_parish',
        'bridge_properties_property_sub_type_idx'    => 'property_sub_type',
        'bridge_properties_mls_status_idx'           => 'mls_status',
        'bridge_properties_senior_community_yn_idx'  => 'senior_community_yn',
        'bridge_properties_garage_yn_idx'            => 'garage_yn',
        'bridge_properties_pool_private_yn_idx'      => 'pool_private_yn',
        'bridge_properties_waterfront_yn_idx'        => 'waterfront_yn',
        'bridge_properties_association_yn_idx'       => 'association_yn',
        'bridge_properties_new_construction_yn_idx'  => 'new_construction_yn',
        'bridge_properties_view_yn_idx'              => 'view_yn',
        'bridge_properties_water_view_yn_idx'        => 'water_view_yn',
        'bridge_properties_cdd_yn_idx'               => 'cdd_yn',
    ];

    public function up()
    {
        if (! $this->isPostgres()) {
            Schema::table('bridge_properties', function (Blueprint $table) {
                foreach (self::FALLBACK_INDEXES as $name => $columns) {
                    $table->index($columns, $name);
                }
            });

            return;
        }

        // Composite B-tree on (latitude, longitude) — enables Haversine bounding-box radius search
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_lat_lng_idx
            ON bridge_properties (latitude, longitude)');

        // Individual B-tree indexes on high-cardinality string columns
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_county_or_parish_idx
            ON bridge_properties (county_or_parish)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_property_sub_type_idx
            ON bridge_properties (property_sub_type)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_mls_status_idx
            ON bridge_properties (mls_status)');

        // Partial indexes on boolean columns — only indexes TRUE rows (high selectivity subset)
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_senior_community_yn_idx
            ON bridge_properties (senior_community_yn) WHERE senior_community_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_garage_yn_idx
            ON bridge_properties (garage_yn) WHERE garage_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_pool_private_yn_idx
            ON bridge_properties (pool_private_yn) WHERE pool_private_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_waterfront_yn_idx
            ON bridge_properties (waterfront_yn) WHERE waterfront_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_association_yn_idx
            ON bridge_properties (association_yn) WHERE association_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_new_construction_yn_idx
            ON bridge_properties (new_construction_yn) WHERE new_construction_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_view_yn_idx
            ON bridge_properties (view_yn) WHERE view_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_water_view_yn_idx
            ON bridge_properties (water_view_yn) WHERE water_view_yn = TRUE');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_cdd_yn_idx
            ON bridge_properties (cdd_yn) WHERE cdd_yn = TRUE');
    }

    public function down()
    {
        // Symmetric fallback: drop the plain indexes up() created on the non-pgsql driver.
        if (! $this->isPostgres()) {
            Schema::table('bridge_properties', function (Blueprint $table) {
                foreach (array_keys(self::FALLBACK_INDEXES) as $name) {
                    $table->dropIndex($name);
                }
            });

            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_lat_lng_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_county_or_parish_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_property_sub_type_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_mls_status_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_senior_community_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_garage_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_pool_private_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_waterfront_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_association_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_new_construction_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_view_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_water_view_yn_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_cdd_yn_idx');
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
}
