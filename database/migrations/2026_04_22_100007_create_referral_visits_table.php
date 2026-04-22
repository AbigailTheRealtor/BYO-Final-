<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferralVisitsTable extends Migration
{
    public function up()
    {
        Schema::create('referral_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('referral_code');
            $table->string('session_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('landing_url')->nullable();
            $table->unsignedBigInteger('visitor_user_id')->nullable();
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->boolean('converted_to_signup')->default(false);
            $table->boolean('converted_to_listing')->default(false);
            $table->boolean('converted_to_hire')->default(false);
            $table->timestamps();

            $table->index('agent_id');
            $table->index('referral_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('referral_visits');
    }
}
