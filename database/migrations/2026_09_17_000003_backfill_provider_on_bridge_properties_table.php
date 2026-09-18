<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 *  DO NOT MODIFY THE VALUE IN THIS FILE AFTER RELEASE.
 *
 *  It is a FROZEN HISTORICAL SNAPSHOT of which provider issued every
 *  `bridge_properties` row that existed on 2026-09-17. A fresh `migrate` in five
 *  years must write exactly what it wrote on the day it shipped.
 *
 *  If the platform ever ingests a second provider, WRITE A NEW MIGRATION. Do not
 *  edit the literal below, and do not re-point it at application code.
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Step 2 of 2 — populate `bridge_properties.provider` for existing rows.
 *
 * WHY EVERY EXISTING ROW IS STELLAR/BRIDGE, AS A FACT RATHER THAN AN ASSUMPTION
 * ----------------------------------------------------------------------------
 * There has only ever been one ingestion path into this table — the Bridge Data
 * Output API, reached through a single configured `BRIDGE_DATASET` and a single
 * `BRIDGE_SERVER_TOKEN`. No second provider has been configured, connected or
 * imported. So the backfill is not a guess about each row; it is the only value
 * any row could have.
 *
 * WHY THIS FILE DEPENDS ON NOTHING IN app/
 * ----------------------------------------
 * The value is spelled out as a literal rather than read from
 * {@see \App\Support\Listing\MlsProvider}. That enum is application code: its
 * cases can be renamed, its backing values can be changed, and either would
 * silently alter what a `migrate:fresh` under a September 2026 filename writes.
 * A historical migration must mean today what it will mean in five years, so it
 * reads no enum, no model and no config — only raw `DB::table()` against a
 * literal table name. This is the same rule
 * `2026_08_27_000003_backfill_workflow_type_on_agent_auction_tables.php`
 * established, and for the same reason.
 *
 * The cost is accepted and stated: the literal here and the enum's case value
 * are two spellings of one string, and a test asserts they agree TODAY. They
 * are permitted to diverge tomorrow, because they answer different questions —
 * the enum says "what does this platform call its providers now?", this file
 * says "what did the 2026-09-17 backfill write?".
 *
 * IDEMPOTENT AND RESUMABLE
 * ------------------------
 * Rows are walked in primary-key order in bounded chunks, and only rows whose
 * provider is still NULL are written. Re-running is a clean no-op; interrupting
 * it leaves a partially-populated table that a re-run completes.
 */
return new class extends Migration
{
    private const TABLE  = 'bridge_properties';
    private const COLUMN = 'provider';

    /**
     * FROZEN VOCABULARY — a literal, not an imported constant.
     *
     * Matches `App\Support\Listing\MlsProvider::StellarBridge->value` as of
     * 2026-09-17, and matches the string
     * `ExploreListingProjector::PROVIDER_STELLAR_BRIDGE` already publishes.
     */
    private const STELLAR_BRIDGE = 'stellar_bridge';

    private const CHUNK = 500;

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $written = 0;
        $lastId  = 0;

        while (true) {
            $ids = DB::table(self::TABLE)
                ->whereNull(self::COLUMN)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $lastId = (int) end($ids);

            $written += DB::table(self::TABLE)
                ->whereIn('id', $ids)
                ->whereNull(self::COLUMN)
                ->update([self::COLUMN => self::STELLAR_BRIDGE]);
        }

        Log::info('[bridge_provider_backfill] complete', [
            'table'    => self::TABLE,
            'provider' => self::STELLAR_BRIDGE,
            'written'  => $written,
        ]);
    }

    /**
     * ROLLBACK IS A DELIBERATE NO-OP FOR ROW DATA.
     *
     * This migration populated a column; it did not create it. By the time
     * anyone rolls back, the same column also holds values written by ORDINARY
     * RUNTIME OPERATION — every Bridge upsert since deploy stamps it — and
     * nothing distinguishes those from the ones this migration wrote. Nulling
     * the column wholesale would look like a rollback while destroying live
     * data this migration never created.
     *
     * The ownership boundary is explicit:
     *
     *     …_000002 owns the SCHEMA — its down() drops the column, taking every
     *                                value with it, which IS the meaningful undo.
     *     …_000003 owns the DATA   — and cannot safely un-populate, so it does
     *                                nothing.
     *
     * Rolling this migration back alone is therefore safe and lossless, and
     * leaves a state a re-run of up() handles correctly: already-populated rows
     * are skipped by the NULL filter.
     */
    public function down(): void
    {
        Log::info('[bridge_provider_backfill] rollback is a no-op by design — this '
            . 'migration populated data it cannot distinguish from later runtime writes. '
            . 'Roll back 2026_09_17_000002 to remove the column and every value with it.');
    }
};
