<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beyond-MLS Property DNA — Phase 13 (production DNA generation) slice.
 *
 * Additive generation provenance for dna_scores so every score can be audited
 * to its origin and safely rebuilt as algorithms evolve:
 *
 *   - generated_by       who/what produced the row: system | ai | user | imported
 *   - generator_version  the generator/algorithm version (e.g. PET_FRIENDLINESS_V1,
 *                        LOCATION_BRIDGE_V1). Distinct from the legacy `version`
 *                        column, which is retained and kept in sync for backward
 *                        compatibility with existing readers/tests.
 *   - source_version     the version of the UPSTREAM data the score was derived
 *                        from (e.g. the Location DNA lifestyle_json version for
 *                        bridged scores); null for pure in-model scalar scores.
 *
 * `computed_at` (already present) serves as generated_at.
 *
 * Non-destructive: nullable / defaulted columns only. Touches no other table.
 */
class AddProvenanceColumnsToDnaScoresTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('dna_scores')) {
            return;
        }

        Schema::table('dna_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('dna_scores', 'generated_by')) {
                // system | ai | user | imported
                $table->string('generated_by')->default('system')->after('version');
            }
            if (! Schema::hasColumn('dna_scores', 'generator_version')) {
                $table->string('generator_version')->nullable()->after('generated_by');
            }
            if (! Schema::hasColumn('dna_scores', 'source_version')) {
                $table->string('source_version')->nullable()->after('generator_version');
            }
        });

        // Separate statement so the columns exist before indexing (SQLite).
        Schema::table('dna_scores', function (Blueprint $table) {
            $table->index('generated_by');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('dna_scores')) {
            return;
        }

        Schema::table('dna_scores', function (Blueprint $table) {
            $table->dropIndex(['generated_by']);
            $table->dropColumn(['generated_by', 'generator_version', 'source_version']);
        });
    }
}
