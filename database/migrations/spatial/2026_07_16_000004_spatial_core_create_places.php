<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 04/11 — places + composite GiST (SSOT §7.2 / §7.3 / §7.4).
 *
 * The owned places corpus: one row per real-world place, shared by every listing.
 * LIST-partitioned by corpus_version; partitions are created at IMPORT time
 * (Phase 2 import batch), not here — this batch stands up the partitioned parent
 * and its partitioned indexes only.
 *
 *   • geom     geography(Geometry,4326)  — Point | Polygon | LineString (§7.4)
 *   • centroid geography(Point,4326)      — markers + Valhalla snap points
 *   • surrogate PK (A1): CMS/NCES/FAA/USGS/GTFS/OSM rows have no GERS id
 *
 * places_cat_geom — the composite gist(category_key, geom) — is the index the
 * whole KNN contract rests on (SIA-D40/D41). It CANNOT exist without btree_gist
 * (migration 01). FK → place_categories (migration 02). Additive, no consumers.
 */
class SpatialCoreCreatePlaces extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS places (
              place_id         bigserial,
              corpus_version   text NOT NULL,
              source           text NOT NULL,
              source_ref       text NOT NULL,
              gers_id          text,
              geom             geography(Geometry,4326) NOT NULL,
              centroid         geography(Point,4326)    NOT NULL,
              category_key     text NOT NULL REFERENCES place_categories,
              name             text,
              brand            text,
              confidence       numeric(4,3),
              source_count     smallint,
              authority_metric numeric,
              attrs            jsonb,
              first_seen       timestamptz,
              last_seen        timestamptz,
              PRIMARY KEY (corpus_version, place_id),
              UNIQUE (corpus_version, source, source_ref)
            ) PARTITION BY LIST (corpus_version)
        SQL);

        // ▲ A2/E-5: composite GiST — requires btree_gist. Without it a
        //   category-filtered KNN degrades to an index walk with a filter (§7.3).
        $conn->statement(
            'CREATE INDEX IF NOT EXISTS places_cat_geom ON places USING gist (category_key, geom)'
        );
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        // CASCADE drops partitions + the partitioned index atomically.
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS places CASCADE');
    }

    private function guardSpatialConnection(): void
    {
        $name = $this->getConnection();
        $conf = config("database.connections.{$name}");

        if (empty($conf) || (empty($conf['url']) && empty($conf['host']))) {
            throw new \RuntimeException(
                "[B1.2/spatial] Connection [{$name}] is not configured. Set SPATIAL_DATABASE_URL "
                . "(or SPATIAL_PGHOST/SPATIAL_PGDATABASE), then run: "
                . "php artisan migrate --path=database/migrations/spatial --database=pgsql_spatial"
            );
        }

        $driver = DB::connection($name)->getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException(
                "[B1.2/spatial] Connection [{$name}] resolves to driver [{$driver}], not 'pgsql'. "
                . "Refusing to execute PostGIS DDL. Use --database=pgsql_spatial."
            );
        }
    }
}
