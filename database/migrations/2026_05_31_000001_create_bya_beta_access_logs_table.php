<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateByaBetaAccessLogsTable extends Migration
{
    public function up()
    {
        Schema::create('bya_beta_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('listing_compatibility_score_id');
            $table->boolean('allowed');
            $table->string('denial_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            $table->foreign('listing_compatibility_score_id')
                  ->references('id')
                  ->on('listing_compatibility_scores')
                  ->onDelete('restrict');

            $table->index('user_id');
            $table->index('listing_compatibility_score_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bya_beta_access_logs');
    }
}
