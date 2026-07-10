<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Schema::table() call per rename. SQLite's Blueprint refuses more than one
     * renameColumn per modification; PostgreSQL emits the same ALTER TABLE … RENAME COLUMN
     * statements either way. No behaviour change on any driver, so no driver guard.
     */
    public function up(): void
    {
        $renames = [
            'listing_title' => 'source_listing_title',
            'listing_url'   => 'source_listing_url',
        ];

        foreach ($renames as $from => $to) {
            Schema::table('hire_agent_leads', function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }

    public function down(): void
    {
        $renames = [
            'source_listing_title' => 'listing_title',
            'source_listing_url'   => 'listing_url',
        ];

        foreach ($renames as $from => $to) {
            Schema::table('hire_agent_leads', function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }
};
