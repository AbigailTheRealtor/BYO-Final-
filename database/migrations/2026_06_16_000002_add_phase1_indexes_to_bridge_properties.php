<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
}
