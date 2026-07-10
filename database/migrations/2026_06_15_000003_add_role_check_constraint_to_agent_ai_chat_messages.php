<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tighten the role column on agent_ai_chat_messages to only allow
 * 'user' or 'assistant'. Enforced at the database level via a CHECK
 * constraint so invalid roles are rejected before reaching the application.
 *
 * PostgreSQL-only. SQLite accepts CHECK only inline at CREATE TABLE, never via
 * ALTER TABLE, so both up() and down() are guarded on the driver.
 *
 * INTENTIONAL SQLITE INTEGRITY GAP
 * --------------------------------
 * Under the SQLite test harness, `agent_ai_chat_messages.role` is NOT constrained at
 * the database level: a row with role = 'system' inserts cleanly. Application-level
 * validation is the only guard there. Do NOT write a test asserting the database
 * rejects an invalid role — on SQLite it would pass vacuously.
 *
 * Unlike the marketing_reports CHECKs, this migration postdates
 * database/schema/pgsql-schema.dump, so it still executes on a fresh PostgreSQL
 * database. Its pgsql branch is byte-for-byte as it shipped.
 */
class AddRoleCheckConstraintToAgentAiChatMessages extends Migration
{
    public function up(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement(
            "ALTER TABLE agent_ai_chat_messages
             ADD CONSTRAINT agent_ai_chat_messages_role_check
             CHECK (role IN ('user', 'assistant'))"
        );
    }

    public function down(): void
    {
        // Symmetric guard. DROP CONSTRAINT is equally PostgreSQL-only, and on SQLite
        // there is no constraint to drop because up() never created one.
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement(
            'ALTER TABLE agent_ai_chat_messages
             DROP CONSTRAINT IF EXISTS agent_ai_chat_messages_role_check'
        );
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
}
