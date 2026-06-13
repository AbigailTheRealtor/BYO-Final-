<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * P1A — Safe Mapping Defect Fix
 *
 * Renames the meta key `renewal_fee_flat_free` → `renewal_fee_flat_fee` in
 * three landlord bid/counter meta tables:
 *   - landlord_agent_auction_bid_metas   (FK: landlord_agent_auction_bid_id)
 *   - landlord_counter_bidding_meta      (FK: counter_bidding_id)
 *   - landlord_counter_terms_meta        (FK: counter_term_id)
 *
 * Background: AgentBidMapperService has always emitted the canonical key
 * `renewal_fee_flat_fee`, but all landlord bid and counter components stored
 * and loaded the value under the misspelled key `renewal_fee_flat_free`.
 * As a result, preset → bid pre-fill silently dropped the flat renewal fee.
 *
 * Collision handling (lossless):
 *   When both keys already exist for the same owner row, the canonical row is
 *   kept and the stale row is deleted.  Before deletion, if the canonical row
 *   has an empty value but the stale row has a non-empty value, the stale
 *   value is written into the canonical row first — ensuring no data is lost.
 *
 * Rollback: mirrors the same lossless per-row logic in reverse for all three
 * tables.
 */
return new class extends Migration
{
    private const OLD_KEY = 'renewal_fee_flat_free';
    private const NEW_KEY = 'renewal_fee_flat_fee';

    private const TABLES = [
        'landlord_agent_auction_bid_metas' => 'landlord_agent_auction_bid_id',
        'landlord_counter_bidding_meta'    => 'counter_bidding_id',
        'landlord_counter_terms_meta'      => 'counter_term_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $fkColumn) {
            if (!Schema::hasTable($table)) {
                Log::info("P1A migration up: skipping {$table} (table does not exist)");
                continue;
            }
            $this->renameKey($table, $fkColumn, self::OLD_KEY, self::NEW_KEY, 'up');
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $fkColumn) {
            if (!Schema::hasTable($table)) {
                Log::info("P1A migration down: skipping {$table} (table does not exist)");
                continue;
            }
            $this->renameKey($table, $fkColumn, self::NEW_KEY, self::OLD_KEY, 'down');
        }
    }

    /**
     * Per-row rename with lossless collision handling.
     *
     * @param string $table     Meta table name
     * @param string $fkColumn  FK column linking to the owner row
     * @param string $fromKey   Source meta_key to migrate away from
     * @param string $toKey     Target meta_key to migrate toward
     * @param string $direction 'up' or 'down' (for logging only)
     */
    private function renameKey(string $table, string $fkColumn, string $fromKey, string $toKey, string $direction): void
    {
        if (!Schema::hasTable($table)) {
            Log::warning("P1A migration {$direction}: skipping {$table} (table does not exist)");
            return;
        }

        $renamed = 0;
        $deleted = 0;
        $valueRescued = 0;

        if (!Schema::hasTable($table)) {
            Log::info("P1A migration {$direction}: skipping {$table} (table does not exist)");
            return;
        }

        $staleRows = DB::table($table)
            ->where('meta_key', $fromKey)
            ->select(['id', $fkColumn, 'meta_value'])
            ->get();

        foreach ($staleRows as $staleRow) {
            $ownerId = $staleRow->{$fkColumn};

            $canonicalRow = DB::table($table)
                ->where($fkColumn, $ownerId)
                ->where('meta_key', $toKey)
                ->select(['id', 'meta_value'])
                ->first();

            if ($canonicalRow) {
                // Collision: canonical key already exists for this owner row.
                // Preserve value if canonical is blank/null but stale has a value.
                // Strict checks are used so that '0' (a valid flat fee) is never
                // treated as missing/empty and accidentally discarded or overwritten.
                $canonicalIsBlank = ($canonicalRow->meta_value === null || $canonicalRow->meta_value === '');
                $staleHasValue    = ($staleRow->meta_value !== null && $staleRow->meta_value !== '');

                if ($canonicalIsBlank && $staleHasValue) {
                    DB::table($table)
                        ->where('id', $canonicalRow->id)
                        ->update(['meta_value' => $staleRow->meta_value]);
                    $valueRescued++;
                }

                // Delete the stale row to prevent duplicate meta_key.
                DB::table($table)
                    ->where('id', $staleRow->id)
                    ->delete();
                $deleted++;
            } else {
                // No collision — rename in-place.
                DB::table($table)
                    ->where('id', $staleRow->id)
                    ->update(['meta_key' => $toKey]);
                $renamed++;
            }
        }

        Log::info("P1A migration {$direction}: {$table}", [
            'key_renamed'    => $renamed,
            'duplicate_deleted' => $deleted,
            'value_rescued'  => $valueRescued,
        ]);
    }
};
