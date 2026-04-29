<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the users.user_type check constraint to include 'tenant'.
 *
 * The base create_users_table migration and the previous
 * add_agent_to_users_user_type_check migration have also been updated
 * to include 'tenant', so migrate:fresh on a blank DB is correct.
 *
 * This migration exists for environments where the users table has
 * already been created without 'tenant' in the constraint.
 */
class AddTenantToUsersUserTypeCheck extends Migration
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
            CHECK (user_type IN ('admin','buyer','seller','buyer_agent','seller_agent','agent'))");
    }
}
