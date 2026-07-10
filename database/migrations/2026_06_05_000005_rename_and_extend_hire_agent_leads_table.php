<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Rename to required contract field names ──────────────────────────
        //
        // One Schema::table() call per rename. SQLite's Blueprint refuses more than one
        // renameColumn per modification (Blueprint::ensureCommandsAreValid), because each
        // rename is emulated by rebuilding the table. This is a Laravel/SQLite limitation,
        // not a PostgreSQL construct: PostgreSQL emits exactly the same four
        // `ALTER TABLE … RENAME COLUMN` statements either way, so no behaviour changes on
        // any driver and no driver guard is warranted.
        $renames = [
            'listing_type'  => 'source_listing_type',
            'listing_id'    => 'source_listing_id',
            'rep_type'      => 'representation_type',
            'property_type' => 'selected_property_type',
        ];

        foreach ($renames as $from => $to) {
            Schema::table('hire_agent_leads', function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }

        Schema::table('hire_agent_leads', function (Blueprint $table) {
            // ── New attribution fields ────────────────────────────────────────
            // Role extracted from source_listing_type (seller/buyer/landlord/tenant)
            $table->string('source_listing_role', 32)->nullable()->after('source_listing_id');
            // Property type of the source listing itself (from listing meta)
            $table->string('source_property_type', 64)->nullable()->after('source_listing_role');
            // Lead origin channel — extensible for future entry points
            $table->string('lead_source', 64)->default('offer_listing')->after('source_property_type');
        });

        // Backfill source_listing_role from the renamed source_listing_type column
        DB::statement("
            UPDATE hire_agent_leads
               SET source_listing_role = REPLACE(source_listing_type, '_offer', '')
             WHERE source_listing_role IS NULL
        ");
    }

    public function down(): void
    {
        // A single dropColumn() call carrying several columns is ONE command, which SQLite
        // accepts. It is repeated renameColumn calls that it rejects, so only those are split.
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            $table->dropColumn(['source_listing_role', 'source_property_type', 'lead_source']);
        });

        $renames = [
            'source_listing_type'    => 'listing_type',
            'source_listing_id'      => 'listing_id',
            'representation_type'    => 'rep_type',
            'selected_property_type' => 'property_type',
        ];

        foreach ($renames as $from => $to) {
            Schema::table('hire_agent_leads', function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }
};
