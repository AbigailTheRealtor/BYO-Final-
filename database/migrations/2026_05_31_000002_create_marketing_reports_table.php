<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMarketingReportsTable extends Migration
{
    public function up()
    {
        Schema::create('marketing_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('profile_id');

            $table->timestampTz('generated_at');

            $table->string('ai_model', 255);
            $table->string('prompt_template_version', 255);
            $table->string('report_contract_version', 255)->default('phase-w-v1');
            $table->string('phase_r_brief_version', 255);
            $table->string('phase_u_readiness_version', 255);

            $table->jsonb('readiness_snapshot');
            $table->jsonb('sections');

            $table->boolean('attribution_verified')->default(false);

            $table->string('status', 50)->default('pending_review');

            // Per XH Section 4.1: created_at and updated_at both default to now()
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('listing_id', 'marketing_reports_listing_id_idx');
            $table->index('profile_id', 'marketing_reports_profile_id_idx');
            $table->index('status', 'marketing_reports_status_idx');
            $table->index('generated_at', 'marketing_reports_generated_at_idx');
            $table->index('attribution_verified', 'marketing_reports_attribution_verified_idx');

            // profile_id: FK → property_dna_profiles.id (bigint → bigint).
            // listing_id: indexed only (no FK — polymorphic, no single target table).
            $table->foreign('profile_id')
                  ->references('id')
                  ->on('property_dna_profiles')
                  ->onDelete('restrict');
        });

        // ── PostgreSQL-only: ALTER TABLE … ADD CONSTRAINT … CHECK ────────────
        //
        // SQLite accepts CHECK only inline at CREATE TABLE, never via ALTER TABLE, and
        // Blueprint has no check() to express it. Emulating this would mean the 12-step
        // SQLite table-rebuild dance, which would build the test schema with different
        // code than production's — a worse outcome than the gap.
        //
        // INTENTIONAL SQLITE INTEGRITY GAP
        // --------------------------------
        // Under the SQLite test harness, `status` is NOT constrained at the database
        // level: a row with status = 'nonsense' inserts cleanly. Application-level
        // validation is the only guard there. Do NOT write a test asserting the database
        // rejects an invalid status — on SQLite it would pass vacuously.
        //
        // PostgreSQL is unchanged: the statement below is byte-for-byte as it shipped.
        // It is also already applied in production and captured in
        // database/schema/pgsql-schema.dump, so on a fresh pgsql database `migrate`
        // loads the dump and never re-runs this migration at all.
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE marketing_reports
            ADD CONSTRAINT marketing_reports_status_check
            CHECK (status IN (
                'pending_review',
                'agent_approved',
                'seller_approved',
                'published',
                'rejected',
                'held_attribution_failure'
            ))
        ");
    }

    public function down()
    {
        // No driver guard needed: dropping the table drops its CHECK constraint with it.
        Schema::dropIfExists('marketing_reports');
    }
}
