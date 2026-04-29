<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ⚠️  This test calls migrate:fresh against the REAL database — it destroys
 * all rows in every table.  It must NEVER run during normal development.
 *
 * Skipped by default.  Enable only in CI on a throwaway DB:
 *   MIGRATION_FRESH_TEST=true php artisan test --filter=MigrationFreshTest
 */
class MigrationFreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! env('MIGRATION_FRESH_TEST')) {
            $this->markTestSkipped(
                'MigrationFreshTest disabled in dev. '
                . 'Set MIGRATION_FRESH_TEST=true to enable (CI / throwaway DB only).'
            );
        }
    }

    public function test_migrate_fresh_runs_without_errors(): void
    {
        $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertSame(0, $exitCode, 'php artisan migrate:fresh returned a non-zero exit code.');
    }

    public function test_key_tables_exist_after_fresh_migration(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        $keyTables = [
            'users',
            'auctions',
            'seller_agent_auctions',
            'seller_agent_auction_bids',
            'buyer_agent_auctions',
            'buyer_agent_auction_bids',
            'landlord_agent_auctions',
            'landlord_agent_auction_bids',
            'tenant_agent_auctions',
            'tenant_agent_auction_bids',
            'offer_auctions',
            'accepted_bid_summaries',
            'agent_referral_links',
            'referral_visits',
            'user_agents',
            'settings',
            'personal_access_tokens',
        ];

        foreach ($keyTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table '{$table}' to exist after migrate:fresh but it was not found."
            );
        }
    }
}
