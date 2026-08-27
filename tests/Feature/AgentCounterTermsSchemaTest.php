<?php

namespace Tests\Feature;

use App\Models\AgentCounterTerm;
use App\Models\AgentServiceAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `agent_counter_terms` must exist after migrating, with the shape the live code writes.
 *
 * Sibling of MissingCounterTablesSchemaTest, and the same kind of proof: migrate:fresh
 * proving a migration RAN says nothing about whether it created anything. This asserts
 * the OUTCOME.
 *
 * The expected columns are not a wish list — every one of them is written by
 * AgentCounteredTermsController::store()/update(), and every one appears in the
 * September 2024 dump (`database/byo2.sql`) that is the only surviving record of the
 * hand-made original table.
 *
 * The absence assertions matter as much as the presence ones: `user_id`,
 * `parent_counter_id` and any meta table would be invented semantics. No code reads
 * them, the historical table had none, and production has none.
 */
class AgentCounterTermsSchemaTest extends TestCase
{
    use DatabaseTransactions;

    /** Exactly what store()/update() write, plus the key and timestamps. */
    private const EXPECTED_COLUMNS = [
        'id',
        'agent_auction_id',
        'timeframe',
        'agentFee',
        'agentFeeOther',
        'agentCharge',
        'agentChargeOther',
        'services',
        'other_services',
        'additionalDetails',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @test */
    public function the_agent_counter_terms_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('agent_counter_terms'),
            'agent_counter_terms is missing — the live agent counter-terms write path 500s without it.'
        );
    }

    /** @test */
    public function it_has_every_column_the_controller_writes(): void
    {
        foreach (self::EXPECTED_COLUMNS as $column) {
            $this->assertTrue(
                Schema::hasColumn('agent_counter_terms', $column),
                "agent_counter_terms is missing `{$column}`, which the controller writes."
            );
        }
    }

    /**
     * @test
     *
     * Guards against a later "consistency" pass bolting on columns from a sibling
     * role. Nothing reads these, so adding them would only invent semantics.
     */
    public function it_does_not_carry_invented_columns(): void
    {
        foreach (['user_id', 'parent_counter_id'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('agent_counter_terms', $column),
                "agent_counter_terms must not declare `{$column}` — no code writes or reads it."
            );
        }
    }

    /** @test */
    public function it_has_no_meta_table_because_nothing_reads_one(): void
    {
        foreach (['agent_counter_terms_meta', 'agent_counter_term_metas'] as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "`{$table}` must not exist — AgentCounterTerm declares no meta relation."
            );
        }
    }

    /**
     * @test
     *
     * The 2024 table had `id int(255)` with no PRIMARY KEY and no AUTO_INCREMENT,
     * which is why its own dumped data contains duplicate `id = 0` rows.
     * `update()` calls findOrFail($id), so a usable key is required.
     */
    public function its_primary_key_autoincrements(): void
    {
        $owner   = User::factory()->asAgent()->create();
        $auction = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);

        $a = AgentCounterTerm::forceCreate(['agent_auction_id' => $auction->id, 'status' => 1]);
        $b = AgentCounterTerm::forceCreate(['agent_auction_id' => $auction->id, 'status' => 1]);

        $this->assertNotNull($a->id);
        $this->assertNotSame((int) $a->id, (int) $b->id, 'ids must be distinct — the historical table had no key.');
    }

    /** @test */
    public function status_defaults_to_one_as_the_original_definition_did(): void
    {
        $owner   = User::factory()->asAgent()->create();
        $auction = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);

        $id = DB::table('agent_counter_terms')->insertGetId(['agent_auction_id' => $auction->id]);

        $this->assertSame(1, (int) DB::table('agent_counter_terms')->where('id', $id)->value('status'));
    }

    /**
     * @test
     *
     * The column name is historical shorthand: there has never been an
     * `agent_auctions` table, and both controller methods resolve it with
     * AgentServiceAuction::find(). The FK must therefore target
     * `agent_service_auctions`, and a real auction id must be accepted.
     */
    public function the_foreign_key_accepts_a_real_agent_service_auction(): void
    {
        $owner   = User::factory()->asAgent()->create();
        $auction = AgentServiceAuction::forceCreate(['user_id' => $owner->id]);

        $row = AgentCounterTerm::forceCreate(['agent_auction_id' => $auction->id, 'status' => 1]);

        $this->assertSame((int) $auction->id, (int) $row->fresh()->agent_auction_id);
        $this->assertFalse(Schema::hasTable('agent_auctions'), 'there has never been an agent_auctions table');
    }
}
