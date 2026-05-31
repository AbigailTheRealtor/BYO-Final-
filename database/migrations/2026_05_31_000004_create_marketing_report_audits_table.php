<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMarketingReportAuditsTable extends Migration
{
    public function up()
    {
        Schema::create('marketing_report_audits', function (Blueprint $table) {
            $table->id();

            $table->string('event_type', 50);

            $table->uuid('report_id')->nullable();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->timestampTz('event_at');

            $table->jsonb('event_data');

            // Per XH Section 6.1: created_at defaults to now(); no updated_at (append-only table)
            $table->timestampTz('created_at')->useCurrent();

            $table->index('event_type', 'marketing_report_audits_event_type_idx');
            $table->index('report_id', 'marketing_report_audits_report_id_idx');
            $table->index('listing_id', 'marketing_report_audits_listing_id_idx');
            $table->index('actor_id', 'marketing_report_audits_actor_id_idx');
            $table->index('event_at', 'marketing_report_audits_event_at_idx');

            $table->foreign('report_id')
                  ->references('id')
                  ->on('marketing_reports')
                  ->onDelete('restrict');

            // profile_id: FK → property_dna_profiles.id (bigint → bigint).
            // listing_id: indexed only (no FK — polymorphic, no single target table).
            $table->foreign('profile_id')
                  ->references('id')
                  ->on('property_dna_profiles')
                  ->onDelete('restrict');
        });

        DB::statement("
            ALTER TABLE marketing_report_audits
            ADD CONSTRAINT marketing_report_audits_event_type_check
            CHECK (event_type IN (
                'generation',
                'review',
                'readiness_failure',
                'attribution_failure'
            ))
        ");

        // Append-only enforcement trigger — PostgreSQL only.
        // BEFORE UPDATE OR DELETE raises an exception unconditionally.
        // No application role, admin operation, migration, or seeder may UPDATE
        // or DELETE any row in this table. Error corrections must be inserted as
        // new rows per Section 7.3 of the Phase XH schema plan.
        //
        // Non-PostgreSQL environments (e.g. SQLite in local dev): the trigger is
        // skipped. The append-only design intent is documented here and must be
        // enforced at the application layer until deployed against PostgreSQL.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION marketing_report_audits_append_only()
                RETURNS trigger
                LANGUAGE plpgsql
                AS \$\$
                BEGIN
                    RAISE EXCEPTION
                        'marketing_report_audits is append-only: UPDATE and DELETE are prohibited. '
                        'To correct an audit record, insert a new row with event_data.corrects_audit_id '
                        'referencing the original record id (see Phase XH schema plan Section 7.3).';
                    RETURN NULL;
                END;
                \$\$;
            ");

            DB::unprepared("
                CREATE TRIGGER marketing_report_audits_no_update_delete
                BEFORE UPDATE OR DELETE ON marketing_report_audits
                FOR EACH ROW EXECUTE FUNCTION marketing_report_audits_append_only();
            ");
        }
    }

    public function down()
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS marketing_report_audits_no_update_delete ON marketing_report_audits');
            DB::unprepared('DROP FUNCTION IF EXISTS marketing_report_audits_append_only()');
        }
        Schema::dropIfExists('marketing_report_audits');
    }
}
