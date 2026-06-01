<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correction migration: converts the three jsonb columns introduced by
 * 2026_06_01_000001_add_narrative_columns_to_listing_compatibility_scores
 * from jsonb → json to match the project-wide convention used by all other
 * json columns in listing_compatibility_scores (score_explanation, deal_breaker_flags,
 * compatibility_trait_results, etc.).
 *
 * This migration is a no-op on environments where those columns were already
 * created as json (i.e. if the original migration was re-run after the edit).
 * On environments where they are already jsonb it converts them.
 *
 * PostgreSQL-only (confirmed platform requirement). The USING cast is valid on PG
 * for jsonb → json conversion.
 */
class FixJsonbToJsonInCompatibilityNarrativeColumns extends Migration
{
    public function up()
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Only alter if the columns are currently jsonb.
        // This guards against double-applying on environments that already have json.
        $columns = ['compatibility_summary_json', 'compatibility_highlights', 'compatibility_warnings'];

        foreach ($columns as $column) {
            $currentType = DB::selectOne(
                "SELECT data_type FROM information_schema.columns
                 WHERE table_name = 'listing_compatibility_scores'
                   AND column_name = ?",
                [$column]
            );

            if ($currentType && $currentType->data_type === 'jsonb') {
                DB::statement(
                    "ALTER TABLE listing_compatibility_scores
                     ALTER COLUMN {$column} TYPE json
                     USING {$column}::text::json"
                );
            }
        }
    }

    public function down()
    {
        // Intentionally a no-op (append-only migration philosophy).
    }
}
