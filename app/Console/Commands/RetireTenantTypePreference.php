<?php

namespace App\Console\Commands;

use App\Models\LandlordAgentAuction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
 * RUN ORDER: deploy the code first. The allowlist in CompatibilityPreferencePolicy must already be
 * refusing the retired keys, or this command cleans rows that the application immediately re-dirties.
 *
 *   php artisan hireagent:retire-tenant-type                  # dry run, writes nothing
 *   php artisan hireagent:retire-tenant-type --write          # performs the remediation
 *   php artisan hireagent:retire-tenant-type --restore=FILE   # undo, from a backup file
 */
class RetireTenantTypePreference extends Command
{
    protected $signature = 'hireagent:retire-tenant-type
        {--write : Actually persist changes. Without this the command only reports.}
        {--restore= : Path to a backup file written by a previous --write run; restores those blobs verbatim.}
        {--backup-dir= : Where to write the backup (default: storage/app/fair-housing).}
        {--chunk=100 : Listings per query chunk.}';

    protected $description = 'Retire landlord tenant_type_preference from stored compatibility data (dry-run by default).';

    /** The meta key holding the whole role-keyed blob. */
    private const META_KEY = 'compatibility_preferences';

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

        $stats = [
            'listings_scanned'        => 0,
            'listings_changed'        => 0,
            'malformed_blobs'         => 0,
            'residential_keys_removed' => 0,
            'commercial_keys_removed'  => 0,
            'mapped_to_business_use'   => 0,
            'discarded_values'         => 0,
            'discarded_other_text'     => 0,
            'leasing_goal_remapped'    => 0,
        ];
        $backup  = [];
        $samples = [];

        LandlordAgentAuction::query()
            ->whereHas('meta', fn ($q) => $q->where('meta_key', self::META_KEY))
            // Deterministic output: same rows in the same order on every run, so two dry runs
            // are diffable and a reviewer can trust that nothing moved between them.
            ->orderBy('id')
            ->chunkById($chunk, function ($listings) use (&$stats, &$backup, &$samples, $write) {
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
                        $stats['malformed_blobs']++;
                        $this->warn("  listing {$listing->id}: compatibility_preferences is not valid JSON — skipped");
                        continue;
                    }

                    $ls = $blob['landlord_specific'] ?? null;
                    if (!is_array($ls)) {
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

                    $backup[(string) $listing->id] = $rawBlob;

                    if (count($samples) < 10) {
                        $samples[] = ['id' => $listing->id, 'notes' => $result['notes']];
                    }

                    if ($write) {
                        $blob['landlord_specific'] = $result['block'];
                        $listing->saveMeta(self::META_KEY, json_encode($blob));
                    }
                }
            });

        $this->renderReport($stats, $samples);

        if (!$write) {
            $this->line('');
            $this->info('Dry run complete. Nothing was written. Re-run with --write to apply.');
            return self::SUCCESS;
        }

        if ($backup !== []) {
            $path = $this->writeBackup($backup);
            $this->line('');
            $this->info("Backup of {$this->pluralise(count($backup), 'original blob')} written to:");
            $this->line("  {$path}");
            $this->line('  Restore with: php artisan hireagent:retire-tenant-type --restore=' . $path);
        }

        $this->line('');
        $this->info('Remediation complete. Re-running is a no-op.');

        return self::SUCCESS;
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

    /**
     * @param  array<string,string>  $backup  listing id => original meta_value
     */
    private function writeBackup(array $backup): string
    {
        $dir = (string) ($this->option('backup-dir') ?: storage_path('app/fair-housing'));

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Timestamped, never overwritten. A second --write run against rows the first missed must
        // not destroy the first run's undo path.
        $path = rtrim($dir, '/') . '/tenant-type-preference-backup-' . now()->format('Ymd-His') . '.json';

        file_put_contents($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'meta_key'     => self::META_KEY,
            'table'        => 'landlord_agent_auction_metas',
            'note'         => 'Original compatibility_preferences meta_value per landlord listing id, before retirement.',
            'blobs'        => $backup,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function restore(string $path): int
    {
        if (!is_file($path)) {
            $this->error("Backup file not found: {$path}");
            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        $blobs   = $payload['blobs'] ?? null;

        if (!is_array($blobs) || $blobs === []) {
            $this->error('Backup file contains no blobs.');
            return self::FAILURE;
        }

        $restored = 0;
        $missing  = 0;

        DB::transaction(function () use ($blobs, &$restored, &$missing) {
            foreach ($blobs as $id => $raw) {
                $listing = LandlordAgentAuction::find((int) $id);
                if (!$listing) {
                    $missing++;
                    continue;
                }
                $listing->saveMeta(self::META_KEY, $raw);
                $restored++;
            }
        });

        $this->info("Restored {$restored} listing blob(s) from {$path}.");
        if ($missing > 0) {
            $this->warn("{$missing} listing(s) in the backup no longer exist and were skipped.");
        }

        return self::SUCCESS;
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

    private function pluralise(int $n, string $noun): string
    {
        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }
}
