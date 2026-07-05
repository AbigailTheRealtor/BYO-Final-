<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (Direct MLS Import) — indexes supporting single-record lookups by
 * BridgeListingLookupService: MLS number (listing_id) and address components
 * (city, postal_code, unparsed_address). Before this, only listing_key was
 * indexed (unique), so MLS#/address lookups table-scanned bridge_properties.
 *
 * Driver-aware: on PostgreSQL (production) uses CREATE INDEX CONCURRENTLY so the
 * live table is not locked; on any other driver (SQLite test harness) falls back
 * to the standard Schema builder. Transaction wrapper disabled because
 * CONCURRENTLY cannot run inside a transaction.
 */
class AddLookupIndexesToBridgeProperties extends Migration
{
    public $withinTransaction = false;

    /** name => column(s) */
    private const INDEXES = [
        'bridge_properties_listing_id_idx'       => 'listing_id',
        'bridge_properties_city_idx'             => 'city',
        'bridge_properties_postal_code_idx'      => 'postal_code',
        'bridge_properties_unparsed_address_idx' => 'unparsed_address',
    ];

    public function up(): void
    {
        if ($this->isPostgres()) {
            foreach (self::INDEXES as $name => $column) {
                DB::statement(
                    "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON bridge_properties ({$column})"
                );
            }
            return;
        }

        Schema::table('bridge_properties', function (Blueprint $table) {
            foreach (self::INDEXES as $name => $column) {
                $table->index($column, $name);
            }
        });
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            foreach (array_keys(self::INDEXES) as $name) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
            }
            return;
        }

        Schema::table('bridge_properties', function (Blueprint $table) {
            foreach (array_keys(self::INDEXES) as $name) {
                $table->dropIndex($name);
            }
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
}
