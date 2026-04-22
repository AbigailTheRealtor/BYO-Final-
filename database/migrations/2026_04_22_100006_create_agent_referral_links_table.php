<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentReferralLinksTable extends Migration
{
    public function up()
    {
        Schema::create('agent_referral_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('code')->unique();
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('click_count')->default(0);
            $table->integer('signup_count')->default(0);
            $table->integer('listing_count')->default(0);
            $table->integer('hire_count')->default(0);
            $table->timestamps();

            $table->index('agent_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_referral_links');
    }
}
