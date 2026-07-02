<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beyond-MLS Property DNA — Wave 1 / Phase 2 slice.
 *
 * Non-destructive, additive table for symmetric per-side DNA scores (§F2 of
 * docs/beyond-mls-property-dna-roadmap.md). Mirrors the property_location_dna
 * pattern: (listing_type, listing_id)-addressed, versioned, one row per
 * (score_key, side). Carries F4 confidence + data_completeness (distinct from
 * the score value, i.e. quality is not conflated with coverage) and an F5
 * explanation string.
 *
 * This table touches nothing that already exists (property_location_dna,
 * listing_compatibility_scores, bid_score_snapshots, *_agent_auctions are all
 * left untouched).
 */
class CreateDnaScoresTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('dna_scores')) {
            return;
        }

        Schema::create('dna_scores', function (Blueprint $table) {
            $table->id();

            // Canonical addressing — same convention as property_location_dna.
            $table->string('listing_type');           // e.g. landlord_agent, tenant_agent
            $table->unsignedBigInteger('listing_id');

            $table->string('score_key');               // e.g. pet_friendliness
            $table->string('side');                    // property | demand

            // Quality score (0–100). Null when the decisive input is absent —
            // deliberately NOT conflated with data_completeness below.
            $table->unsignedSmallInteger('value')->nullable();

            // F4 — coverage of the canonical inputs this score depends on (0–100).
            $table->unsignedSmallInteger('data_completeness')->nullable();

            // F4 — derived, non-inflating (<= data_completeness). 0–100.
            $table->unsignedSmallInteger('confidence')->nullable();

            // F5 — neutral, factual, human-readable rationale.
            $table->text('explanation')->nullable();

            // F2 — inputs snapshot (which canonical fields/values fed the score).
            $table->json('inputs_json')->nullable();

            // F2 — reproducibility (model + feature-set version tag).
            $table->string('version')->nullable();

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['listing_type', 'listing_id', 'score_key', 'side']);
            $table->index('score_key');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dna_scores');
    }
}
