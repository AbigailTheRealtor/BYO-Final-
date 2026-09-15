<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tags Phase 1 — per-source evidence.
 *
 * One row per listing × tag × SOURCE: why the platform believes a listing has
 * (or, from structured data only, does not have) a canonical Smart Tag.
 *
 * Addressed by (listing_type, listing_id), the convention every multi-source
 * table here uses; listing_type values come only from SmartTagListingType
 * (bridge | seller_agent | landlord_agent). No FK on the pair, by the same house
 * convention: there is no single target table. Small enumerations are string
 * columns backed by PHP enums — no ->enum(), which drifts on SQLite.
 *
 * Additive and reversible. Nothing writes to it in Phase 1.
 * See docs/smart-tags/SMART_TAGS_GOVERNANCE.md.
 */
class CreateSmartTagEvidenceTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('smart_tag_evidence')) {
            return;
        }

        Schema::create('smart_tag_evidence', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type', 32);          // bridge | seller_agent | landlord_agent
            $table->unsignedBigInteger('listing_id');
            $table->string('tag_key', 64);               // config/smart_tags.php key
            $table->string('context', 32);               // residential.sale | … | commercial.lease
            $table->string('source', 32);                // structured_mls | mls_remarks | structured_native_listing | native_listing_description | manual_listing_owner
            $table->string('state', 12);                 // present | absent (no row = unknown)
            $table->unsignedSmallInteger('confidence');  // fixed per rule; never displayed
            $table->string('source_field', 80)->nullable();
            $table->string('rule_id', 100)->nullable();  // which rule fired — never listing text
            $table->unsignedBigInteger('set_by_user_id')->nullable(); // manual_listing_owner only
            $table->string('tagger_version', 64)->nullable();         // derived sources only

            $table->timestamps();

            $table->unique(['listing_type', 'listing_id', 'tag_key', 'source'], 'smart_tag_evidence_listing_tag_source_unique');
            $table->index(['listing_type', 'listing_id'], 'smart_tag_evidence_listing_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('smart_tag_evidence');
    }
}
