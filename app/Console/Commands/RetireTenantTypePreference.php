<?php

namespace App\Console\Commands;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retire the Fair Housing `tenant_type_preference` field from stored landlord listings.
 *
 * WHY A COMMAND AND NOT A MIGRATION. Nothing schema-shaped changes here: the data lives inside a
 * JSON string in one EAV row (`landlord_agent_auction_metas.meta_value` where
 * `meta_key = 'compatibility_preferences'`), so this is a read-modify-write over content, not a
 * column operation. And `deploy/start-production.sh` is the single owner of migrations on a
 * Laravel 8 app with no migration lock, where a failed migration stops the deploy — a rewrite over
 * an unknown number of blobs does not belong in that position. A command can also be dry-run,
 * reviewed, and re-run, which a migration cannot.
 *
 * WHY REMOVING THE FORM CONTROL WAS NOT ENOUGH. The listing detail page renders from the stored
 * blob, so every value already submitted kept rendering after the control was deleted. And Save
 * Edit did not rewrite landlord compatibility data at all before this branch, so there was no path
 * by which a stale value would age out on its own.
 *
 *
 * ── TWO PHASES, AND THE ORDER IS THE SAFETY PROPERTY ────────────────────────────────────────
 *
 * PHASE A — PLAN. Scans every candidate row and computes, for each affected listing, the exact
 * original blob and the exact remediated blob. It reads and computes; it writes nothing, ever,
 * in either mode. A dry run is Phase A and then a report.
 *
 * PHASE B — BACKUP, VERIFY, THEN WRITE. Entered only under `--write`. Every affected listing's
 * ORIGINAL blob is persisted first, then every backup is READ BACK OUT OF THE DATABASE and
 * checksum-verified, and only after all of them verify does the first compatibility blob change.
 * A failure anywhere in backup or verification aborts with FAILURE having performed zero
 * remediation writes.
 *
 * This ordering is the whole point. The earlier version accumulated originals in memory and
 * flushed them to a file after the last write had already landed, so a timeout or an OOM midway
 * left rows remediated with no rollback record at all.
 *
 *
 * ── WHERE THE BACKUP LIVES, AND WHY NOT A FILE ─────────────────────────────────────────────
 *
 * IN THE DATABASE, beside the data it protects, under the dedicated meta key
 * `fair_housing_backup_compatibility_preferences`.
 *
 * It used to be a JSON file under `storage/app/fair-housing`. That is not a rollback path on this
 * deployment: the Replit container is rebuilt from the image on deploy and on restart, `storage/`
 * carries no persistent mount, and the file is not in git — so the undo for a destructive JSON-key
 * deletion evaporated at the next restart, silently, while the deletion stayed. A row in
 * `landlord_agent_auction_metas` survives exactly as long as the listing it can restore.
 *
 * NOTHING AT RUNTIME READS THIS KEY. Every consumer of listing meta either calls `info($key)` for
 * a named key, reads `$auction->get->$namedKey`, or walks an explicit field whitelist
 * (`LandlordFieldMap::sections()` for the PDF packet, `CANONICAL_SOURCE_MAP` for Ask AI). Nothing
 * enumerates meta keys and renders what it finds, so this row is inert to the application.
 *
 * WRITTEN ONCE PER LISTING, NEVER OVERWRITTEN. The envelope holds the true pre-remediation
 * original. A later run that finds a backup already present leaves it alone and says so — being
 * able to get back to the value the landlord actually submitted matters more than being able to
 * get back to whatever it had become by the second run.
 *
 *
 * ── USAGE ──────────────────────────────────────────────────────────────────────────────────
 *
 *   php artisan hireagent:retire-tenant-type                    # Phase A only. Reads. Writes nothing.
 *   php artisan hireagent:retire-tenant-type --write            # Phase A, then backup+verify+write.
 *   php artisan hireagent:retire-tenant-type --list-backups     # Read-only: which runs can be restored.
 *   php artisan hireagent:retire-tenant-type --restore=RUN_ID   # Undo one identified run.
 *
 * RUN ORDER: deploy the code first. The allowlist in CompatibilityPreferencePolicy must already be
 * refusing the retired keys, or this command cleans rows that the application immediately re-dirties.
 *
 * RUNNING `--write` AGAINST ANY REAL DATABASE REQUIRES SEPARATE EXPLICIT APPROVAL. Nothing in this
 * file grants it, and it has not been run against any database.
 */
class RetireTenantTypePreference extends Command
{
    protected $signature = 'hireagent:retire-tenant-type
        {--write : Actually persist changes. Without this the command only plans and reports.}
        {--restore= : Run id of a previous --write run, from --list-backups; restores that run\'s originals.}
        {--list-backups : Read-only. List the restorable runs held in the database.}
        {--chunk=100 : Listings per query chunk.}';

    protected $description = 'Retire landlord tenant_type_preference from stored compatibility data (dry-run by default).';

    /** The meta key holding the whole role-keyed blob. */
    private const META_KEY = 'compatibility_preferences';

    /**
     * The meta key holding the durable rollback record.
     *
     * Deliberately prefixed and deliberately not the runtime key: no reader anywhere resolves it,
     * so an extra row per remediated listing changes nothing the application renders.
     */
    private const BACKUP_META_KEY = 'fair_housing_backup_compatibility_preferences';

    /** Envelope format marker. A row that does not carry it is not a backup we will trust. */
    private const BACKUP_SCHEMA = 'fh-tenant-type-backup-v1';

    /** The two keys being retired. */
    private const RETIRED_KEYS = ['tenant_type_preference', 'tenant_type_preference_other'];

    /**
     * The ONLY values that carry forward, and only on a commercial listing.
     *
     * Both name a business use unambiguously. "Small Business" is deliberately absent: it is a
     * SIZE, not a use, and maps equally well to Retail, Office, Personal Services or Professional
     * Services. Guessing would put a value the landlord never chose into a field they never saw,
     * so it is discarded and the landlord answers the new question when they next edit.
     */
    private const COMMERCIAL_VALUE_MAP = [
        'Office Tenant'   => 'Office',
        'Retail Business' => 'Retail',
    ];

    /**
     * Primary Leasing Goal values that changed wording.
     *
     * "High-Quality Tenant Profile" graded a person with no objective referent. "Long-Term Stable
     * Tenant" described the occupant where the others describe the transaction. Both are required-
     * field values, so they must be remapped rather than dropped — a listing left holding a value
     * that is no longer an option fails its own validation on the owner's next edit.
     */
    private const LEASING_GOAL_MAP = [
        'High-Quality Tenant Profile' => 'Reliable Rent Collection',
        'Long-Term Stable Tenant'     => 'Long-Term Tenancy',
    ];

    private const COMMERCIAL = 'Commercial Property';

    public function handle(): int
    {
        if ($this->option('list-backups')) {
            return $this->listBackups();
        }

        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'));
        }

        $write = (bool) $this->option('write');
        $chunk = max(1, (int) $this->option('chunk'));

        $this->line('');
        $this->info($write
            ? 'hireagent:retire-tenant-type — WRITE MODE'
            : 'hireagent:retire-tenant-type — DRY RUN (nothing will be written; pass --write to apply)');
        $this->line('');

        // ── PHASE A — PLAN. Read-only in both modes. ──────────────────────────────────────
        $plan = $this->planRemediation($chunk);

        $this->renderReport($plan['stats'], $plan['samples']);
        $this->reportMalformed($plan['stats']);

        if (!$write) {
            $this->line('');
            $this->info('Dry run complete. Nothing was written. Re-run with --write to apply.');

            return self::SUCCESS;
        }

        if ($plan['entries'] === []) {
            $this->line('');
            $this->info('Remediation complete. Re-running is a no-op.');

            return self::SUCCESS;
        }

        // ── PHASE B — BACKUP, VERIFY, THEN AND ONLY THEN WRITE. ───────────────────────────
        $runId = $this->newRunId();

        $this->line('');
        $this->info("Run id: {$runId}");
        $this->line('  Backing up originals before any listing is modified.');

        try {
            $entries = $this->ensureBackups($plan['entries'], $runId);
        } catch (Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            $this->error('ABORTED. No listing was modified.');

            return self::FAILURE;
        }

        if (!$this->verifyBackups($entries)) {
            $this->error('ABORTED. No listing was modified.');

            return self::FAILURE;
        }

        $created    = count(array_filter($entries, fn ($e) => $e['backup_created']));
        $preExisting = count($entries) - $created;

        $this->info("  Verified {$this->pluralise(count($entries), 'backup')} "
            . "({$created} created this run, {$preExisting} already present from an earlier run).");

        $this->applyRemediation($entries);

        $this->line('');
        $this->info('Remediation complete. Re-running is a no-op.');
        $this->line("  Roll back with: php artisan hireagent:retire-tenant-type --restore={$runId}");

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // PHASE A — planning. Nothing below this heading writes.
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Scan every candidate listing and compute what would change. READ-ONLY.
     *
     * Returns the full plan rather than acting on it, which is what lets `--write` back every
     * original up before the first blob moves: the set of affected listings is known in full
     * before anything is touched.
     *
     * @return array{entries: list<array<string,mixed>>, stats: array<string,int>, samples: list<array<string,mixed>>}
     */
    private function planRemediation(int $chunk): array
    {
        $stats = [
            'listings_scanned'          => 0,
            'listings_changed'          => 0,
            'malformed_json_blobs'      => 0,
            'malformed_landlord_blocks' => 0,
            'residential_keys_removed'  => 0,
            'commercial_keys_removed'   => 0,
            'mapped_to_business_use'    => 0,
            'discarded_values'          => 0,
            'discarded_other_text'      => 0,
            'leasing_goal_remapped'     => 0,
        ];
        $entries = [];
        $samples = [];

        LandlordAgentAuction::query()
            ->whereHas('meta', fn ($q) => $q->where('meta_key', self::META_KEY))
            // Deterministic output: same rows in the same order on every run, so two dry runs
            // are diffable and a reviewer can trust that nothing moved between them.
            ->orderBy('id')
            ->chunkById($chunk, function ($listings) use (&$stats, &$entries, &$samples) {
                foreach ($listings as $listing) {
                    $stats['listings_scanned']++;

                    $rawBlob = $listing->info(self::META_KEY);
                    if (!is_string($rawBlob) || trim($rawBlob) === '') {
                        continue;
                    }

                    $blob = json_decode($rawBlob, true);
                    if (!is_array($blob)) {
                        // Malformed JSON. Counted and skipped — never "repaired". Rewriting a blob
                        // we cannot parse would destroy whatever it holds, and the retired keys
                        // cannot be rendered out of it either, so leaving it is also safe.
                        $stats['malformed_json_blobs']++;
                        $this->warn("  listing {$listing->id}: compatibility_preferences is not valid JSON — skipped");
                        continue;
                    }

                    // ABSENT IS NORMAL; PRESENT-BUT-NOT-AN-ARRAY IS MALFORMED. Conflating the two
                    // is what let a damaged row disappear from the report entirely: a listing that
                    // simply has no landlord answers needs nothing done and is not a problem, while
                    // a `landlord_specific` holding a string or a number is a record we cannot
                    // remediate and the operator has to know remediation was incomplete for it.
                    if (!array_key_exists('landlord_specific', $blob)) {
                        continue;
                    }

                    $ls = $blob['landlord_specific'];
                    if (!is_array($ls)) {
                        $stats['malformed_landlord_blocks']++;
                        $this->warn("  listing {$listing->id}: landlord_specific is not an object — skipped");
                        continue;
                    }

                    $result = $this->remediateLandlordBlock($ls, $this->propertyTypeOf($listing));

                    if (!$result['changed']) {
                        continue;
                    }

                    $stats['listings_changed']++;
                    foreach ($result['stats'] as $k => $v) {
                        $stats[$k] += $v;
                    }

                    $blob['landlord_specific'] = $result['block'];

                    $entries[] = [
                        'id'             => (int) $listing->id,
                        'original_raw'   => $rawBlob,
                        'remediated_raw' => json_encode($blob),
                        'notes'          => $result['notes'],
                        'backup_created' => false,
                    ];

                    if (count($samples) < 10) {
                        $samples[] = ['id' => $listing->id, 'notes' => $result['notes']];
                    }
                }
            });

        return ['entries' => $entries, 'stats' => $stats, 'samples' => $samples];
    }

    /**
     * Apply the retirement to one landlord_specific block.
     *
     * Pure: takes a block, returns a new one. Nothing here reads or writes the database, so the
     * mapping rules are testable without a listing, a user, or a schema.
     *
     * @param  array<string,mixed>  $ls
     * @return array{block: array<string,mixed>, changed: bool, stats: array<string,int>, notes: list<string>}
     */
    public function remediateLandlordBlock(array $ls, ?string $propertyType): array
    {
        $stats = [
            'residential_keys_removed' => 0,
            'commercial_keys_removed'  => 0,
            'mapped_to_business_use'   => 0,
            'discarded_values'         => 0,
            'discarded_other_text'     => 0,
            'leasing_goal_remapped'    => 0,
        ];
        $notes   = [];
        $changed = false;

        // ANYTHING THAT IS NOT EXACTLY THE COMMERCIAL VALUE IS RESIDENTIAL. property_type is EAV,
        // so it can be null, '', a legacy spelling, or absent entirely on an older row. The
        // conservative direction costs a commercial landlord one re-answer; the permissive one
        // leaves a demographic value on a home.
        $isCommercial = is_string($propertyType) && trim($propertyType) === self::COMMERCIAL;

        $hasRetired = array_key_exists('tenant_type_preference', $ls)
            || array_key_exists('tenant_type_preference_other', $ls);

        if ($hasRetired) {
            $value = $ls['tenant_type_preference'] ?? null;
            $value = is_string($value) ? trim($value) : '';
            $other = $ls['tenant_type_preference_other'] ?? null;
            $other = is_string($other) ? trim($other) : '';

            if ($isCommercial && isset(self::COMMERCIAL_VALUE_MAP[$value])) {
                $mapped = self::COMMERCIAL_VALUE_MAP[$value];

                // Merge rather than overwrite: a commercial landlord may already have answered
                // the new question, and re-running must not duplicate or clobber that.
                $existing = $ls['preferred_business_use'] ?? [];
                $existing = is_array($existing) ? array_values(array_filter($existing, 'is_string')) : [];

                if (!in_array($mapped, $existing, true)) {
                    $existing[] = $mapped;
                    $stats['mapped_to_business_use']++;
                    $notes[] = "commercial: '{$value}' -> preferred_business_use['{$mapped}']";
                }
                $ls['preferred_business_use'] = $existing;
                $stats['commercial_keys_removed']++;
            } elseif ($value !== '') {
                $stats['discarded_values']++;
                $stats[$isCommercial ? 'commercial_keys_removed' : 'residential_keys_removed']++;
                $notes[] = ($isCommercial ? 'commercial' : 'residential') . ": '{$value}' discarded";
            } else {
                $stats[$isCommercial ? 'commercial_keys_removed' : 'residential_keys_removed']++;
            }

            if ($other !== '') {
                // NEVER carried into preferred_business_use_other, in either property type. This
                // prose was written under a prompt that asked for a preferred TENANT TYPE, so it
                // cannot be assumed to describe a lawful business use — and reviewing it one
                // listing at a time is not something a bulk command should pretend to do.
                $stats['discarded_other_text']++;
                $notes[] = 'tenant_type_preference_other discarded unreviewed';
            }

            unset($ls['tenant_type_preference'], $ls['tenant_type_preference_other']);
            $changed = true;
        }

        $goal = $ls['primary_leasing_goal'] ?? null;
        if (is_string($goal) && isset(self::LEASING_GOAL_MAP[trim($goal)])) {
            $new = self::LEASING_GOAL_MAP[trim($goal)];
            $ls['primary_leasing_goal'] = $new;
            $stats['leasing_goal_remapped']++;
            $notes[] = "primary_leasing_goal: '" . trim($goal) . "' -> '{$new}'";
            $changed = true;
        }

        return ['block' => $ls, 'changed' => $changed, 'stats' => $stats, 'notes' => $notes];
    }

    /** The listing's stored property type, or null when absent. */
    private function propertyTypeOf(LandlordAgentAuction $listing): ?string
    {
        $value = $listing->info('property_type');

        return is_string($value) ? $value : null;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // PHASE B — backup, verify, write. Reached only under --write.
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Persist a rollback record for every planned listing. Runs BEFORE any remediation write.
     *
     * A listing that already carries a backup keeps the one it has: that envelope holds the value
     * the landlord actually submitted, and a second run's "original" would only be whatever the
     * first run left behind.
     *
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    private function ensureBackups(array $entries, string $runId): array
    {
        foreach ($entries as $i => $entry) {
            if ($this->backupRowFor($entry['id']) !== null) {
                $entries[$i]['backup_created'] = false;
                continue;
            }

            $envelope = [
                'schema'          => self::BACKUP_SCHEMA,
                'run_id'          => $runId,
                'created_at'      => now()->toIso8601String(),
                'table'           => 'landlord_agent_auction_metas',
                'source_meta_key' => self::META_KEY,
                'listing_id'      => $entry['id'],
                'original'        => $entry['original_raw'],
                'sha256'          => hash('sha256', $entry['original_raw']),
                'note'            => 'Original compatibility_preferences meta_value, captured before Fair Housing retirement.',
            ];

            $this->persistBackupEnvelope($entry['id'], (string) json_encode($envelope));
            $entries[$i]['backup_created'] = true;
        }

        return $entries;
    }

    /**
     * Write one backup envelope.
     *
     * Its own method so a test can substitute a failing implementation and prove that a backup
     * storage failure produces zero remediation writes rather than a half-remediated table.
     */
    protected function persistBackupEnvelope(int $listingId, string $json): void
    {
        $listing = LandlordAgentAuction::findOrFail($listingId);
        $listing->saveMeta(self::BACKUP_META_KEY, $json);
    }

    /**
     * Read every backup back OUT OF THE DATABASE and check it.
     *
     * Read back rather than trusting the write: a rollback record that cannot be re-read is not a
     * rollback record, and the moment to discover that is before the data it protects is changed.
     * Fails on the first bad envelope and reports which listing, so the operator has somewhere to
     * look rather than a bare refusal.
     *
     * @param  list<array<string,mixed>>  $entries
     */
    private function verifyBackups(array $entries): bool
    {
        foreach ($entries as $entry) {
            $raw = $this->backupRowFor($entry['id']);

            if ($raw === null) {
                $this->error("Backup verification failed for listing {$entry['id']}: no backup row found after writing it.");

                return false;
            }

            $envelope = json_decode($raw, true);

            if (!$this->envelopeIsIntact($envelope)) {
                $this->error("Backup verification failed for listing {$entry['id']}: the backup envelope is unreadable or its checksum does not match.");

                return false;
            }

            // A backup created in THIS run must be byte-identical to the blob we planned from.
            // A pre-existing one deliberately is not: it holds the true original from an earlier
            // run, which is older than whatever the listing holds today.
            if ($entry['backup_created'] && $envelope['original'] !== $entry['original_raw']) {
                $this->error("Backup verification failed for listing {$entry['id']}: stored original does not match the blob planned from.");

                return false;
            }
        }

        return true;
    }

    /**
     * Write the remediated blobs. The last thing that happens, and only after every backup verified.
     *
     * @param  list<array<string,mixed>>  $entries
     */
    private function applyRemediation(array $entries): void
    {
        DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                $listing = LandlordAgentAuction::find($entry['id']);
                if (!$listing) {
                    continue;
                }
                $listing->saveMeta(self::META_KEY, $entry['remediated_raw']);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Backup inspection and restore.
    // ─────────────────────────────────────────────────────────────────────────────────────

    /** The raw backup envelope for one listing, or null when there is none. */
    private function backupRowFor(int $listingId): ?string
    {
        $row = LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $listingId)
            ->where('meta_key', self::BACKUP_META_KEY)
            ->first();

        return $row && is_string($row->meta_value) ? $row->meta_value : null;
    }

    /** Is this decoded envelope one we are willing to restore from? */
    private function envelopeIsIntact($envelope): bool
    {
        return is_array($envelope)
            && ($envelope['schema'] ?? null) === self::BACKUP_SCHEMA
            && is_string($envelope['run_id'] ?? null)
            && ($envelope['run_id'] ?? '') !== ''
            && is_string($envelope['original'] ?? null)
            && is_string($envelope['sha256'] ?? null)
            && hash_equals($envelope['sha256'], hash('sha256', $envelope['original']));
    }

    /**
     * Read-only. Which runs can be restored, and how many listings each covers.
     */
    private function listBackups(): int
    {
        $rows = LandlordAgentAuctionMeta::query()
            ->where('meta_key', self::BACKUP_META_KEY)
            ->orderBy('landlord_agent_auction_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No Fair Housing backups are stored. Nothing has been remediated.');

            return self::SUCCESS;
        }

        $runs      = [];
        $unreadable = 0;

        foreach ($rows as $row) {
            $envelope = json_decode((string) $row->meta_value, true);

            if (!$this->envelopeIsIntact($envelope)) {
                $unreadable++;
                continue;
            }

            $runId = $envelope['run_id'];
            $runs[$runId]['listings'] = ($runs[$runId]['listings'] ?? 0) + 1;
            $runs[$runId]['created_at'] ??= (string) ($envelope['created_at'] ?? '');
        }

        ksort($runs);

        $this->table(
            ['Run id', 'Listings', 'Created at'],
            collect($runs)->map(fn ($r, $id) => [$id, $r['listings'], $r['created_at']])->values()->all()
        );

        if ($unreadable > 0) {
            $this->warn("{$this->pluralise($unreadable, 'backup row')} could not be read and are excluded. "
                . 'Restore will refuse while any exist.');
        }

        $this->line('');
        $this->line('Restore one with: php artisan hireagent:retire-tenant-type --restore=RUN_ID');

        return self::SUCCESS;
    }

    /**
     * Restore the originals captured by one identified run.
     *
     * NAMED RUN ONLY. There is no "latest", no default and no prompt-free guess: with more than
     * one run stored, silently picking is how the wrong rollback gets applied. The run id comes
     * from --list-backups.
     *
     * FAILS CLOSED. Any unreadable backup row anywhere refuses the whole restore, because an
     * envelope we cannot decode is an envelope whose run we cannot rule out. Verification runs
     * over every matching row before a single blob is written.
     *
     * IDEMPOTENT. Restoring twice writes the same bytes, and the backup rows are left in place so
     * the operation can be repeated or audited afterwards.
     */
    private function restore(string $runId): int
    {
        $runId = trim($runId);

        $rows = LandlordAgentAuctionMeta::query()
            ->where('meta_key', self::BACKUP_META_KEY)
            ->orderBy('landlord_agent_auction_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->error('No Fair Housing backups are stored; there is nothing to restore.');

            return self::FAILURE;
        }

        $planned    = [];
        $seenRunIds = [];

        foreach ($rows as $row) {
            $envelope = json_decode((string) $row->meta_value, true);

            if (!$this->envelopeIsIntact($envelope)) {
                $this->error("Backup row for listing {$row->landlord_agent_auction_id} is unreadable or fails its checksum.");
                $this->error('ABORTED. Nothing was restored: an envelope that cannot be decoded cannot be ruled out of the requested run.');

                return self::FAILURE;
            }

            $seenRunIds[$envelope['run_id']] = true;

            if ($envelope['run_id'] === $runId) {
                $planned[] = [
                    'listing_id' => (int) $row->landlord_agent_auction_id,
                    'original'   => $envelope['original'],
                ];
            }
        }

        if ($planned === []) {
            $this->error("No backup found for run id '{$runId}'.");
            $this->line('Available run ids:');
            foreach (array_keys($seenRunIds) as $known) {
                $this->line("  {$known}");
            }

            return self::FAILURE;
        }

        $restored = 0;
        $missing  = 0;

        DB::transaction(function () use ($planned, &$restored, &$missing) {
            foreach ($planned as $item) {
                $listing = LandlordAgentAuction::find($item['listing_id']);
                if (!$listing) {
                    $missing++;
                    continue;
                }
                $listing->saveMeta(self::META_KEY, $item['original']);
                $restored++;
            }
        });

        $this->info("Restored {$restored} listing blob(s) from run {$runId}.");
        if ($missing > 0) {
            $this->warn("{$missing} listing(s) in that run no longer exist and were skipped.");
        }
        $this->line('  Backup rows were left in place; this restore can be repeated.');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Reporting.
    // ─────────────────────────────────────────────────────────────────────────────────────

    private function newRunId(): string
    {
        return 'fhrt-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }

    /**
     * @param  array<string,int>  $stats
     * @param  list<array{id:int|string,notes:list<string>}>  $samples
     */
    private function renderReport(array $stats, array $samples): void
    {
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all()
        );

        if ($samples !== []) {
            $this->line('');
            $this->info('Sample of affected listings (first ' . count($samples) . '):');
            foreach ($samples as $sample) {
                foreach ($sample['notes'] as $note) {
                    $this->line("  listing {$sample['id']}: {$note}");
                }
            }
        }

        if ($stats['listings_changed'] === 0) {
            $this->line('');
            $this->info('No listing needs remediation. (This is the expected result of a second run.)');
        }
    }

    /**
     * Say plainly that remediation did not cover everything.
     *
     * A skipped row is safe — it is left exactly as found — but silence about it reads as success,
     * and an operator who believes the retirement is complete will not go looking.
     *
     * @param  array<string,int>  $stats
     */
    private function reportMalformed(array $stats): void
    {
        $total = $stats['malformed_json_blobs'] + $stats['malformed_landlord_blocks'];

        if ($total === 0) {
            return;
        }

        $this->line('');
        $this->warn("REMEDIATION INCOMPLETE: could not process {$this->pluralise($total, 'listing')}. "
            . 'Each was left exactly as found.');
        $this->warn("  {$stats['malformed_json_blobs']} with compatibility_preferences that is not valid JSON");
        $this->warn("  {$stats['malformed_landlord_blocks']} with a landlord_specific that is not an object");
        $this->warn('  These listings may still hold retired values. Inspect them individually.');
    }

    private function pluralise(int $n, string $noun): string
    {
        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }
}
