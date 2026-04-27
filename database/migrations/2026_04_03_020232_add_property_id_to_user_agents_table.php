<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPropertyIdToUserAgentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('user_agents') && !Schema::hasColumn('user_agents', 'property_id')) {
            Schema::table('user_agents', function (Blueprint $table) {
                $table->unsignedBigInteger('property_id')->nullable()->after('agent_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('user_agents') && Schema::hasColumn('user_agents', 'property_id')) {
            Schema::table('user_agents', function (Blueprint $table) {
                $table->dropColumn('property_id');
            });
        }
    }
}
