<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matching V2 — C7 persistence slice (child matches table).
 *
 * Non-destructive, additive table holding ONE row per ranked counterpart within
 * a materialized run (matching_v2_match_runs). Preserves counterpart_type +
 * counterpart_id (C6 Design T — ids can collide across listing_types), the tier,
 * and the ranking position. value/confidence/coverage are stored for internal
 * fidelity only and are never surfaced to any consumer-facing path.
 *
 * See docs/matching-v2-c7-persistence-scope.md §4.2.
 */
class CreateMatchingV2MatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('matching_v2_matches')) {
            return;
        }

        Schema::create('matching_v2_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_run_id')
                ->constrained('matching_v2_match_runs')
                ->cascadeOnDelete();

            // Denormalized subject addressing so children can be read without a join.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            // The ranked counterpart (listing_type preserved; id is a listing_id).
            $table->string('counterpart_type')->nullable();
            $table->unsignedBigInteger('counterpart_id');

            // 0-based best-first rank within the run.
            $table->unsignedInteger('position')->default(0);

            // Tier band + overall relevance.
            $table->string('tier');
            $table->unsignedSmallInteger('value')->nullable();

            // Internal fidelity only — never displayed (scope §1).
            $table->unsignedSmallInteger('confidence')->nullable();
            $table->unsignedSmallInteger('coverage')->nullable();

            $table->timestamps();

            $table->index(['match_run_id', 'position']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('matching_v2_matches');
    }
}
