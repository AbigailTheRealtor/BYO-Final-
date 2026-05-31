<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMarketingReportVersionsTable extends Migration
{
    public function up()
    {
        Schema::create('marketing_report_versions', function (Blueprint $table) {
            $table->id();

            $table->uuid('marketing_report_id');
            $table->string('section_key', 100);
            $table->integer('version_number');

            $table->text('draft_text')->default('');
            $table->jsonb('source_attribution')->default('[]');

            $table->string('status', 50)->default('pending_review');
            $table->string('created_by', 255);

            // Per XH Section 5.1: created_at defaults to now(); no updated_at (rows are immutable)
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(
                ['marketing_report_id', 'section_key', 'version_number'],
                'marketing_report_versions_report_section_version_uq'
            );

            $table->index('marketing_report_id', 'marketing_report_versions_report_id_idx');
            $table->index(
                ['marketing_report_id', 'section_key'],
                'marketing_report_versions_section_key_idx'
            );
            $table->index('created_by', 'marketing_report_versions_created_by_idx');

            $table->foreign('marketing_report_id')
                  ->references('id')
                  ->on('marketing_reports')
                  ->onDelete('restrict');
        });

        DB::statement("
            ALTER TABLE marketing_report_versions
            ADD CONSTRAINT marketing_report_versions_status_check
            CHECK (status IN (
                'pending_review',
                'approved',
                'revised',
                'rejected',
                'internal_note'
            ))
        ");
    }

    public function down()
    {
        Schema::dropIfExists('marketing_report_versions');
    }
}
