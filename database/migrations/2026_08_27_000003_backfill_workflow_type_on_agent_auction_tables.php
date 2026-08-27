<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 *  DO NOT MODIFY THE CLASSIFICATION RULES IN THIS FILE AFTER RELEASE.
 *
 *  They are a FROZEN HISTORICAL SNAPSHOT of how a row's product was decided on
 *  2026-08-27. A fresh `migrate` in five years must classify exactly the rows this
 *  migration classified on the day it shipped, and put exactly the same value in
 *  each one.
 *
 *  If the way this application decides a row's product ever changes, WRITE A NEW
 *  MIGRATION. Do not edit the rules below, do not "improve" them, and do not
 *  re-point them at application code.
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Step 2 of 2 — classify every existing row that CAN be classified, leave the rest NULL.
 *
 * WHY THIS FILE CARRIES ITS OWN CLASSIFIER
 * ----------------------------------------
 * The first version of this migration called \App\Services\Listing\ListingWorkflowResolver
 * so that a row's product meant the same thing to the backfill and to the runtime guard.
 * That is a good property, and it was bought at a price that turned out to be too high: a
 * historical migration whose behaviour was defined by mutable application code.
 *
 * The mitigation then was a version constant on the resolver that this file pinned and
 * compared. It detected drift only when a developer remembered to bump it. That is
 * detection by convention — the failure mode it was meant to prevent (semantics change,
 * nobody notices, `migrate:fresh` silently produces different data under an August 2026
 * name) survived intact, merely requiring an oversight rather than an intention.
 *
 * So the decision is inlined here instead, and this file now depends on NOTHING from
 * `app/`:
 *
 *   - No resolver, no backfiller, no classification service.
 *   - No Eloquent models. A model is application code too: a global scope, a changed
 *     `$table`, a renamed relation or a new casting rule added later would all change
 *     which rows this migration sees or what it reads from them. Raw `DB::table()` with
 *     literal table names cannot drift.
 *   - No shared vocabulary constants. Even `ListingWorkflow::HIRE_AGENT` is a mutable
 *     value; the literals are spelled out below and are frozen with the rest.
 *
 * What remains is a pure function of (rows, meta rows) → workflow, with no reachable
 * dependency that a future change could alter. See ALSO the migration's proof in
 * tests/Feature/Listing/WorkflowBackfillMigrationDeterminismTest.php, which pins a
 * deliberately hostile resolver into the container and asserts this migration ignores it.
 *
 * THE COST, ACCEPTED
 * ------------------
 * There are now two implementations of "which product is this row?" — this frozen one and
 * the live ListingWorkflowResolver. They agree today, and they are ALLOWED to diverge
 * tomorrow, because they answer different questions:
 *
 *     the resolver  — "what is this row, by today's rules?"     (mutable, by design)
 *     this file     — "what did the 2026-08-27 backfill do?"    (immutable, by design)
 *
 * A future rule change updates the resolver and, if existing data needs revisiting, adds
 * a NEW migration carrying its own frozen snapshot. This one is never touched again.
 *
 * WHAT IT DOES
 * ------------
 * Idempotent: only rows whose column is still NULL are touched, so re-running is a no-op
 * and a partially-completed run resumes cleanly.
 *
 * IT DOES NOT GUESS AND IT DOES NOT FAIL. Rows with conflicting or insufficient evidence
 * keep a NULL column and are counted as unresolved; the counts land in the log under
 * `[workflow_backfill]` and the durable work list is
 * `php artisan listings:workflow-inventory`.
 *
 * NO NOT NULL ENFORCEMENT HERE. That is a separate, later migration, gated on an
 * inventory proving a zero remainder — see the step-1 migration's header.
 */
return new class extends Migration
{
    // ─── FROZEN VOCABULARY ────────────────────────────────────────────────────
    // Literals, not imported constants. An imported constant is a value someone can
    // change; these cannot be changed from outside this file. Part of the snapshot.

    /** The discriminator column, as added by 2026_08_27_000002. */
    private const COLUMN = 'workflow_type';

    /** The two products. */
    private const HIRE_AGENT    = 'hire_agent';
    private const OFFER_LISTING = 'offer_listing';

    /** The legacy EAV key holding the same concept. Same name, different storage era. */
    private const META_KEY = 'workflow_type';

    /**
     * Meta keys that are decisive evidence of one product, and the product they prove.
     *
     * `mls_quick_import` → OFFER_LISTING. Written only by MlsQuickImportDraftWriter,
     *                      which is reachable only from Create Offer Listing.
     * `service_type`     → HIRE_AGENT. Written by every Hire wizard and by
     *                      HireAgentDirectController; written by no Offer Listing
     *                      component — Offer Listing has no service-type concept.
     *
     * Both were verified exhaustively against the write surface as it stood on
     * 2026-08-27. They are frozen at that reading.
     */
    private const PROVENANCE_QUICK_IMPORT = 'mls_quick_import';
    private const PROVENANCE_SERVICE_TYPE = 'service_type';

    /**
     * table => [meta table, foreign key].
     *
     * Spelled out rather than read off the models' `meta()` relations, for the same
     * reason as everything else here: a relation is application code.
     */
    private const TABLES = [
        'seller_agent_auctions'   => ['seller_agent_auction_metas',   'seller_agent_auction_id'],
        'buyer_agent_auctions'    => ['buyer_agent_auction_metas',    'buyer_agent_auction_id'],
        'landlord_agent_auctions' => ['landlord_agent_auction_metas', 'landlord_agent_auction_id'],
        'tenant_agent_auctions'   => ['tenant_agent_auction_metas',   'tenant_agent_auction_id'],
    ];

    private const CHUNK = 200;

    public function up(): void
    {
        $totals = [];

        foreach (self::TABLES as $table => [$metaTable, $foreignKey]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, self::COLUMN)) {
                continue;
            }

            $totals[$table] = $this->backfillTable($table, $metaTable, $foreignKey);
        }

        Log::info('[workflow_backfill] complete', $totals);
    }

    /**
     * ROLLBACK IS A DELIBERATE NO-OP FOR ROW DATA.
     *
     * This migration populated a column; it did not create it. Rolling a data backfill
     * "back" would mean restoring each row to the value it held beforehand, and that
     * information does not exist — the pre-backfill state was NULL for the rows this
     * migration wrote, and untouched for every other row.
     *
     * The previous version nulled `workflow_type` across all four tables. That was
     * destructive and wrong. By the time anyone rolls back, the column also holds
     * identities written by ORDINARY RUNTIME OPERATION — every wizard save, every Quick
     * Import, every draft version created since deploy stamps it. A blanket null erases
     * all of those too, and nothing distinguishes them from the ones this migration wrote.
     * The result was a database that looked rolled back and had in fact lost live data
     * this migration never created.
     *
     * A per-row ledger recording which ids this migration touched would make a true
     * reversal possible. It is not worth it: it adds a table, a write per row, and a
     * second failure mode, to support an operation whose real-world use is "undo the
     * schema", which the step-1 migration already does correctly.
     *
     * So the ownership boundary is explicit:
     *
     *     000002 owns the SCHEMA  — its down() drops the index and the column, taking
     *                               every value with it, which IS the meaningful undo.
     *     000003 owns the DATA    — and cannot safely un-populate, so it does nothing.
     *
     * Rolling back this migration alone is therefore safe and lossless, and leaves the
     * database in a state a re-run of up() treats correctly: already-populated rows are
     * skipped (the WHERE NULL filter), so re-applying is a clean no-op.
     *
     * Nothing here is left un-reversed that the schema rollback will not reverse.
     */
    public function down(): void
    {
        Log::info('[workflow_backfill] rollback is a no-op by design — this migration '
            . 'populated data it cannot distinguish from later runtime writes. Roll back '
            . '2026_08_27_000002 to remove the workflow_type column and its values.');
    }

    // ─── FROZEN CLASSIFICATION ────────────────────────────────────────────────

    /**
     * @return array<string,int> bucket => count
     */
    private function backfillTable(string $table, string $metaTable, string $foreignKey): array
    {
        $counts  = [self::HIRE_AGENT => 0, self::OFFER_LISTING => 0, 'unresolved' => 0];
        $lastId  = 0;
        $hasMeta = Schema::hasTable($metaTable);

        while (true) {
            $rows = DB::table($table)
                ->select('id', self::COLUMN)
                ->whereNull(self::COLUMN)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $ids    = $rows->pluck('id')->all();
            $lastId = (int) end($ids);

            $meta = $hasMeta ? $this->metaFor($metaTable, $foreignKey, $ids) : [];

            foreach ($rows as $row) {
                $workflow = $this->classify($meta[$row->id] ?? []);

                if ($workflow === null) {
                    $counts['unresolved']++;

                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([self::COLUMN => $workflow]);

                $counts[$workflow]++;
            }
        }

        return $counts;
    }

    /**
     * meta rows for a chunk of listing ids, as [listing id => [key => value]].
     *
     * @param  array<int>  $ids
     * @return array<int,array<string,mixed>>
     */
    private function metaFor(string $metaTable, string $foreignKey, array $ids): array
    {
        $out = [];

        DB::table($metaTable)
            ->select($foreignKey, 'meta_key', 'meta_value')
            ->whereIn($foreignKey, $ids)
            ->orderBy('id')
            ->get()
            ->each(function ($m) use (&$out, $foreignKey) {
                // Last write wins, matching how the application's own `info()` reads a
                // duplicated key. Ordered by id so "last" is deterministic.
                $out[$m->{$foreignKey}][$m->meta_key] = $m->meta_value;
            });

        return $out;
    }

    /**
     * ██ THE FROZEN RULE. DO NOT CHANGE. ██
     *
     * Only rows whose native column is NULL reach here, so the column is not consulted —
     * it is known absent. Evidence considered, in the order it is gathered:
     *
     *   1. the legacy EAV `workflow_type` stamp
     *   2. deterministic provenance (`mls_quick_import` / `service_type`)
     *
     * Any two decisive signals that disagree produce NULL, not a winner. Precedence names
     * a row; it never silences a contradiction. An unrecognised value is never guessed
     * past. No evidence at all is NULL. Every NULL outcome means the same thing to the
     * caller — "this migration will not write this row" — which is the only behaviour the
     * strict runtime policy is safe to sit on top of.
     *
     * @param  array<string,mixed>  $meta  this row's meta, key => value
     * @return string|null  the workflow, or null for unresolved (any reason)
     */
    private function classify(array $meta): ?string
    {
        // ── 1. Legacy EAV stamp ───────────────────────────────────────────────
        $stamp = $this->normalise($meta[self::META_KEY] ?? null);

        if ($stamp !== null && $stamp !== self::HIRE_AGENT && $stamp !== self::OFFER_LISTING) {
            // Unrecognised value in the identity key. Never guessed past.
            return null;
        }

        // ── 2. Deterministic provenance ───────────────────────────────────────
        $quickImport = $this->normalise($meta[self::PROVENANCE_QUICK_IMPORT] ?? null);
        $serviceType = $this->normalise($meta[self::PROVENANCE_SERVICE_TYPE] ?? null);

        $saysOffer = $quickImport !== null && $this->isTruthy($quickImport);
        $saysHire  = $serviceType !== null;

        if ($saysOffer && $saysHire) {
            // Both products' fingerprints on one row: the cross-product corruption
            // signature. Refuse it; do not pick a side.
            return null;
        }

        $provenance = $saysOffer ? self::OFFER_LISTING : ($saysHire ? self::HIRE_AGENT : null);

        // ── 3. Reconcile ──────────────────────────────────────────────────────
        if ($stamp !== null && $provenance !== null && $stamp !== $provenance) {
            return null; // identity contradicts provenance
        }

        return $stamp ?? $provenance; // null when neither is present
    }

    /**
     * Fold every "absent" spelling into a real null.
     *
     * A missing key is null; a blank meta row is ''. Both mean absent and must not be
     * mistaken for a value — a '' treated as present would make every blank-meta row
     * unresolvable.
     */
    private function normalise($value): ?string
    {
        if ($value === null || $value === false || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Frozen truthiness fold for the quick-import flag. */
    private function isTruthy(string $value): bool
    {
        return ! in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
    }
};
