<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matching V2 — C7 persistence slice (summary table).
 *
 * Non-destructive, additive table holding ONE materialized summary row per
 * matched subject per version: the ranked-match counts, tier histogram, and
 * discovery metadata from an OrchestratedMatchResult (C6). Child per-match rows
 * live in matching_v2_matches.
 *
 * Addressed by (subject_type, subject_id) — the canonical (listing_type,
 * listing_id) convention used by dna_scores — plus a `version` tag so a scoring
 * change invalidates old rows by version bump + re-materialize (read-time
 * re-gate; see docs/matching-v2-c7-persistence-scope.md §5).
 *
 * Touches nothing that already exists. Populated ONLY in staging/dev when both
 * MATCHING_V2_ENABLED and MATCHING_V2_PERSISTENCE_ENABLED are on; the writer
 * hard-refuses in production.
 */
class CreateMatchingV2MatchRunsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('matching_v2_match_runs')) {
            return;
        }

        Schema::create('matching_v2_match_runs', function (Blueprint $table) {
            $table->id();

            // Subject addressing — same convention as dna_scores.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            // MatchDirection::name — ListingToDemands | DemandToListings (nullable
            // for an unsupported/inert subject).
            $table->string('direction')->nullable();

            // Materialization version tag (config matching.persistence.version).
            // Part of the uniqueness key; the reader only trusts the current value.
            $table->string('version');

            // Counts + discovery metadata carried straight from OrchestratedMatchResult.
            $table->unsignedInteger('determined_count')->default(0);
            $table->unsignedInteger('undetermined_count')->default(0);
            $table->unsignedInteger('candidates_considered')->default(0);
            $table->boolean('candidate_pool_truncated')->default(false);

            // Zero-filled 4-tier histogram {exact,strong,similar,opportunity}.
            $table->json('tier_counts')->nullable();

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            // One summary per subject per version (idempotent upsert key).
            $table->unique(['subject_type', 'subject_id', 'version']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('matching_v2_match_runs');
    }
}
