<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL-only. CREATE INDEX CONCURRENTLY has no SQLite equivalent — SQLite's parser
 * fails at `CONCURRENTLY`, before it even reaches `IF NOT EXISTS`.
 *
 * NO SQLITE FALLBACK INDEX IS CREATED. This is a deliberate project decision, not an
 * oversight. An index changes the plan the query planner picks, never the rows a query
 * returns, so no test's correctness can depend on one. Nine of these thirteen are PARTIAL
 * indexes (`WHERE … = TRUE`) that Blueprint::index() cannot express at all, so any
 * fallback would be a full index — schema divergence bought for zero coverage.
 *
 * If a specific test ever demonstrates an index is required for CORRECTNESS rather than
 * speed, add only that one index here and cite the failing test by name.
 *
 * The pgsql branch is byte-for-byte as it shipped. Postdates
 * database/schema/pgsql-schema.dump, so it still executes on a fresh PostgreSQL database.
 */
class AddPhase1IndexesToBridgeProperties extends Migration
{
    /**
     * Disable transaction wrapper — CREATE INDEX CONCURRENTLY cannot run
     * inside a PostgreSQL transaction. Laravel honours this flag to skip
     * the transaction wrapper for this migration class.
     */
    public $withinTransaction = false;

    public function up()
    {
        if (! $this->isPostgres()) {
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
        // Symmetric guard: on SQLite up() created nothing, so there is nothing to drop.
        if (! $this->isPostgres()) {
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
