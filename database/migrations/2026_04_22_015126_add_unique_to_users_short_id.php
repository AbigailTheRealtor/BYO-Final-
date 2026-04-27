<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueToUsersShortId extends Migration
{
    public function up()
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'short_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('short_id', 'users_short_id_unique');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_short_id_unique');
            });
        }
    }
}
