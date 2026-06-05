<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            // ── Rename to required contract field names ──────────────────────
            $table->renameColumn('listing_type', 'source_listing_type');
            $table->renameColumn('listing_id',   'source_listing_id');
            $table->renameColumn('rep_type',      'representation_type');
            $table->renameColumn('property_type', 'selected_property_type');
        });

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
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            $table->dropColumn(['source_listing_role', 'source_property_type', 'lead_source']);
        });

        Schema::table('hire_agent_leads', function (Blueprint $table) {
            $table->renameColumn('source_listing_type',   'listing_type');
            $table->renameColumn('source_listing_id',     'listing_id');
            $table->renameColumn('representation_type',   'rep_type');
            $table->renameColumn('selected_property_type','property_type');
        });
    }
};
