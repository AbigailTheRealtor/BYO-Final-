<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeCriteriaFetchCacheTable extends Migration
{
    public function up()
    {
        Schema::create('bridge_criteria_fetch_cache', function (Blueprint $table) {
            $table->id();
            $table->string('criteria_hash', 64)->unique();
            $table->string('role', 20);
            $table->timestamp('last_fetched_at')->nullable();
            $table->integer('record_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bridge_criteria_fetch_cache');
    }
}
