<?php

/*
 * AI FAQ Storage Path Confirmation (required per task step 1):
 *
 * Path B confirmed. AI FAQ answers are stored as a JSON blob in a single EAV
 * meta key ('listing_ai_faq') via saveMeta() / loadDraft() in all four
 * Livewire components (SellerOfferListing, LandlordOfferListing,
 * BuyerOfferListing, TenantOfferListing and their Edit counterparts).
 * The blob is a JSON-encoded associative array keyed by question_key.
 * There is no dedicated per-question row table in the current codebase.
 * Backfill from EAV meta into the new ai_faq_answers table is Phase F work.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyDnaProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('property_dna_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');
            $table->integer('version');
            $table->timestamp('source_listing_updated_at');

            $table->decimal('physical_score', 5, 2)->nullable();
            $table->decimal('financial_score', 5, 2)->nullable();
            $table->decimal('location_score', 5, 2)->nullable();
            $table->decimal('condition_score', 5, 2)->nullable();
            $table->decimal('legal_score', 5, 2)->nullable();
            $table->decimal('flexibility_score', 5, 2)->nullable();
            $table->decimal('occupant_qualification_score', 5, 2)->nullable();
            $table->decimal('marketing_score', 5, 2)->nullable();
            $table->decimal('compatibility_score', 5, 2)->nullable();
            $table->decimal('commercial_score', 5, 2)->nullable();
            $table->decimal('overall_dna_completeness', 5, 2)->nullable();

            $table->json('ai_buyer_archetype_tags')->nullable();
            $table->json('ai_marketing_hooks')->nullable();

            $table->integer('walk_score')->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-01)');
            $table->integer('transit_score')->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-01)');
            $table->integer('bike_score')->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-01)');
            $table->decimal('school_rating', 5, 2)->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-02)');
            $table->string('flood_zone_verified')->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-03)');
            $table->decimal('estimated_monthly_utilities', 10, 2)->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-05)');

            $table->timestamp('computed_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['listing_type', 'listing_id', 'archived_at']);
            $table->index('overall_dna_completeness');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_dna_profiles');
    }
}
