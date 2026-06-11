<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate (listing_type, listing_id, version) rows introduced
        // before this unique index existed, keeping only the row with the highest id
        // for each combination.
        //
        // The double-subquery wrapper (... FROM (...) AS dedup) is required for
        // MySQL compatibility: MySQL rejects a self-referencing DELETE where the
        // target table appears directly in the FROM clause of the subquery
        // (error 1093). Wrapping in a derived table alias satisfies MySQL,
        // PostgreSQL, and SQLite equally.
        DB::statement("
            DELETE FROM ask_ai_knowledge_snapshots
            WHERE id NOT IN (
                SELECT max_id FROM (
                    SELECT MAX(id) AS max_id
                    FROM ask_ai_knowledge_snapshots
                    GROUP BY listing_type, listing_id, version
                ) AS dedup
            )
        ");

        Schema::table('ask_ai_knowledge_snapshots', function (Blueprint $table) {
            $table->unique(
                ['listing_type', 'listing_id', 'version'],
                'ask_ai_snapshots_unique_version'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ask_ai_knowledge_snapshots', function (Blueprint $table) {
            $table->dropUnique('ask_ai_snapshots_unique_version');
        });
    }
};
