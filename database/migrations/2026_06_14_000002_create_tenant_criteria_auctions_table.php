<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantCriteriaAuctionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            Schema::create('tenant_criteria_auctions', function (Blueprint $table) {
                $table->id();
                $table->string('ai_share_token', 64)->nullable()->unique();
                $table->bigInteger('user_id');
                $table->string('auction_type')->nullable();
                $table->string('auction_length')->nullable();
                $table->timestamp('listing_date')->nullable();
                $table->timestamp('expiration_date')->nullable();
                $table->boolean('is_approved')->default(true);
                $table->boolean('is_sold')->default(false);
                $table->boolean('is_paid')->default(false);
                $table->boolean('is_draft')->default(false);
                $table->boolean('display_bids')->default(true);
                $table->json('listing_ai_faq')->nullable();
                $table->unsignedBigInteger('referring_agent_id')->nullable();
                $table->string('referral_source_code')->nullable();
                $table->timestamp('referral_captured_at')->nullable();
                $table->boolean('referral_locked')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('tenant_criteria_auctions');
    }
}
