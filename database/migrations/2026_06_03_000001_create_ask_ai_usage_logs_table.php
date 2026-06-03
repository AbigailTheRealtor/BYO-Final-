<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAskAiUsageLogsTable extends Migration
{
    public function up()
    {
        Schema::create('ask_ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type')->nullable();
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('question_hash', 64)->nullable();
            $table->string('question_type')->nullable();
            $table->string('status')->nullable();
            $table->boolean('success')->default(false);
            $table->string('model')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('status');
            $table->index('listing_type');
            $table->index('question_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ask_ai_usage_logs');
    }
}
