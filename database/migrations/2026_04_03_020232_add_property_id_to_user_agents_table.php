<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPropertyIdToUserAgentsTable extends Migration
{
    public function up()
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('agent_id');
        });
    }

    public function down()
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('property_id');
        });
    }
}
