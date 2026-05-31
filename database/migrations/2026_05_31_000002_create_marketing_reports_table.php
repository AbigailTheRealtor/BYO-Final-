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
        Schema::dropIfExists('marketing_reports');
    }
}
