<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts the (listing_type, listing_id) plain index on property_location_dna
 * into a unique constraint, guaranteeing one geocode record per listing.
 *
 * This migration is fully idempotent and safe in all three scenarios:
 *
 *   A) Fresh install (updated 000005) — the table was created with the unique
 *      constraint already in place and no plain index exists.
 *      → DROP INDEX IF EXISTS is a no-op.
 *      → The unique-add is caught as "already exists" and silently skipped.
 *
 *   B) Existing environment (original 000005) — the table has a plain index
 *      and no unique constraint.
 *      → The plain index is dropped.
 *      → The unique constraint is added.
 *
 *   C) Re-run after partial failure — intermediate state handled gracefully
 *      because every step checks before acting.
 */
class UniqueConstraintPropertyLocationDna extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('property_location_dna')) {
            return;
        }

        // Step 1: Drop the plain index if it still exists.
        // DROP INDEX IF EXISTS is supported by both PostgreSQL and SQLite 3.x,
        // so this is a safe no-op when the index is already absent (scenario A).
        DB::statement(
            'DROP INDEX IF EXISTS "property_location_dna_listing_type_listing_id_index"'
        );

        // Step 2: Add the unique constraint.
        // On fresh installs (scenario A) this constraint already exists, so
        // the QueryException "already exists" is caught and swallowed.
        // Any other exception (real schema error) is re-thrown.
        try {
            Schema::table('property_location_dna', function (Blueprint $table) {
                $table->unique(['listing_type', 'listing_id']);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'already exists')) {
                throw $e;
            }
            // Unique constraint already present — expected on fresh installs.
        }
    }

    public function down()
    {
        if (! Schema::hasTable('property_location_dna')) {
            return;
        }

        // Remove the unique constraint if it exists.
        try {
            Schema::table('property_location_dna', function (Blueprint $table) {
                $table->dropUnique(['listing_type', 'listing_id']);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'does not exist')) {
                throw $e;
            }
        }

        // Restore the plain index.
        Schema::table('property_location_dna', function (Blueprint $table) {
            $table->index(['listing_type', 'listing_id']);
        });
    }
}
