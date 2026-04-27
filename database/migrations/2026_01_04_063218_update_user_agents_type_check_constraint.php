<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateUserAgentsTypeCheckConstraint extends Migration
{
    public function up()
    {
        if (Schema::hasTable('user_agents')) {
            DB::statement('ALTER TABLE user_agents DROP CONSTRAINT IF EXISTS user_agents_type_check');
            DB::statement("ALTER TABLE user_agents ADD CONSTRAINT user_agents_type_check CHECK (type IN ('seller', 'buyer', 'tenant', 'landlord'))");
        }
    }

    public function down()
    {
        if (Schema::hasTable('user_agents')) {
            DB::statement('ALTER TABLE user_agents DROP CONSTRAINT IF EXISTS user_agents_type_check');
            DB::statement("ALTER TABLE user_agents ADD CONSTRAINT user_agents_type_check CHECK (type IN ('seller', 'buyer'))");
        }
    }
}
