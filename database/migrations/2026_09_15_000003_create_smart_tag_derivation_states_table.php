<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tags Phase 1 — derivation change detection.
 *
 * One row per listing. Independent hashes for structured inputs, MLS remarks and
 * the native listing description, plus the tagger version, so unchanged inputs
 * are never re-derived and unchanged prose is never reparsed.
 *
 * Additive and reversible. Nothing writes to it in Phase 1.
 */
class CreateSmartTagDerivationStatesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('smart_tag_derivation_states')) {
            return;
        }

        Schema::create('smart_tag_derivation_states', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type', 32);
            $table->unsignedBigInteger('listing_id');
            $table->string('context', 32)->nullable();
            $table->string('structured_inputs_hash', 64)->nullable();
            $table->string('mls_remarks_hash', 64)->nullable();
            $table->string('native_description_hash', 64)->nullable();
            $table->string('tagger_version', 64);
            $table->timestamp('derived_at')->nullable();

            $table->timestamps();

            $table->unique(['listing_type', 'listing_id'], 'smart_tag_derivation_states_listing_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('smart_tag_derivation_states');
    }
}
