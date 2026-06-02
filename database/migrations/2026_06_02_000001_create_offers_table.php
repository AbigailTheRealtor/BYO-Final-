<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateOffersTable extends Migration
{
    public function up()
    {
        // In --pretend mode the DB connection mocks select() → [], making hasTable()
        // always return false. Skip the guard then; the FK constraint enforces it at runtime.
        if (!DB::connection()->pretending() && !Schema::hasTable('offer_auctions')) {
            throw new \RuntimeException(
                'Migration create_offers_table requires offer_auctions to exist. ' .
                'Run create_offer_auctions_table first.'
            );
        }

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_auction_id')
                  ->constrained('offer_auctions')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('role', 20);

            // Intended statuses (all seven must be storable from Phase 1A):
            //   draft      - being composed; not yet sent; only submitter can see it
            //   submitted  - formally submitted; visible to listing owner
            //   countered  - counter-offer issued (Phase 2 stub; no transition logic in Phase 1A)
            //   accepted   - listing owner accepted; terminal state
            //   rejected   - listing owner rejected; terminal state
            //   withdrawn  - submitter withdrew before decision; terminal state
            //   expired    - reserved for future use; do not implement before explicitly scoped
            $table->string('status', 30)->default('draft');

            $table->json('listing_snapshot')->nullable();
            $table->unsignedBigInteger('parent_offer_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->foreign('parent_offer_id')
                  ->references('id')
                  ->on('offers')
                  ->nullOnDelete();
            $table->index('parent_offer_id');
            $table->index('status');
            $table->index(['offer_auction_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['parent_offer_id']);
        });
        Schema::dropIfExists('offers');
    }
}
