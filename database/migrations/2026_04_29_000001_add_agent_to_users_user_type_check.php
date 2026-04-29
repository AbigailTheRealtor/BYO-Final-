<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the users.user_type check constraint to include 'agent'.
 *
 * The base create_users_table migration now defines the enum with 'agent'
 * included, so migrate:fresh on a blank DB picks it up automatically.
 * This migration exists for environments where create_users_table has
 * already run with the old five-value constraint.
 */
class AddAgentToUsersUserTypeCheck extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_user_type_check
            CHECK (user_type IN ('admin','buyer','seller','buyer_agent','seller_agent','agent','tenant'))");
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_user_type_check
            CHECK (user_type IN ('admin','buyer','seller','buyer_agent','seller_agent'))");
    }
}
