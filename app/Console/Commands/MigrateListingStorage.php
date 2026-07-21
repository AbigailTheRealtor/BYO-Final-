<?php

namespace App\Console\Commands;

use App\Support\Storage\ListingObjectMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * R2-C (HI-05A) — resumable, idempotent, non-destructive migration of existing
 * local listing storage to the paired object-storage secondary disks, preserving
 * exact relative keys.
 *
 * Local sources are only READ; nothing local is deleted or modified. Writes to
 * object storage require an explicit --confirm; --dry-run and --verify-only never
 * write. Reruns are idempotent (already-identical objects are skipped) and never
 * overwrite a differing destination unless --force-conflicts is also given.
 *
 * This command does NOT change selectors, does NOT enable dual-write, and is
 * independent of the HI-05A documents:backfill-private command.
 */
class MigrateListingStorage extends Command
{
    protected $signature = 'listing-storage:migrate
        {--scope=all : public | private | all}
        {--prefix= : Restrict to a relative-key prefix (e.g. auction/images)}
        {--dry-run : Plan only — enumerate and decide, but never write or persist a manifest}
        {--verify-only : Verify destination against local (size + SHA-256); never write}
        {--confirm : Required to actually write objects (ignored by --dry-run/--verify-only)}
        {--resume : Skip keys already migrated/identical in the resumed manifest}
        {--manifest= : Manifest path on the private disk (default _migration-manifests/migrate-<ts>.json)}
        {--limit= : Cap the number of objects processed this run}
        {--force-conflicts : Overwrite a differing destination (requires --confirm)}
        {--include-manifests : Also migrate _backfill-manifests (excluded by default)}';

    protected $description = 'HI-05A R2-C: copy existing local listing storage to object-storage secondaries (non-destructive, resumable, idempotent).';

    public function handle(ListingObjectMigrator $migrator): int
    {
        $scope = (string) $this->option('scope');
        if (! in_array($scope, ['public', 'private', 'all'], true)) {
            $this->error("Invalid --scope '{$scope}'. Expected: public, private, all.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $verifyOnly = (bool) $this->option('verify-only');
        $force = (bool) $this->option('force-conflicts');
        $prefix = $this->option('prefix') ?: null;
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $writeMode = ! $dryRun && ! $verifyOnly;

        // Fail closed: real writes require explicit confirmation.
        if ($writeMode && ! $this->option('confirm')) {
            $this->error('Refusing to write. Pass --confirm to migrate, or use --dry-run / --verify-only.');

            return self::FAILURE;
        }
        if ($force && ! $writeMode) {
            $this->warn('--force-conflicts has no effect without a write (--confirm) run.');
        }

        if ((bool) $this->option('include-manifests')) {
            config(['listing_storage.migration.exclude_prefixes' => array_values(array_diff(
                (array) config('listing_storage.migration.exclude_prefixes', []),
                ['_backfill-manifests']
            ))]);
        }

        $scopes = $scope === 'all' ? [false, true] : [$scope === 'private'];

        // Resume: collect keys already done in a prior manifest.
        $done = $this->loadResumeSet();

        $records = [];
        $processed = 0;
        $opts = ['dry_run' => $dryRun, 'verify_only' => $verifyOnly, 'force_conflicts' => $force];

        foreach ($scopes as $private) {
            $label = $private ? 'private' : 'public';
            try {
                $keys = $migrator->enumerate($private, $prefix);
            } catch (\Throwable $e) {
                $this->error("Enumeration failed for {$label} scope.");

                return self::FAILURE;
            }

            foreach ($keys as $key) {
                if ($limit !== null && $processed >= $limit) {
                    $this->warn("Reached --limit={$limit}; stopping (remaining objects NOT processed).");
                    break 2;
                }
                if ($this->option('resume') && isset($done[$label.'::'.$key])) {
                    continue;
                }

                $records[] = $migrator->process($private, $key, $opts) + ['scope' => $label];
                $processed++;

                // Checkpoint periodically so an interrupted write run is resumable.
                if ($writeMode && $processed % 25 === 0) {
                    $this->persist($records);
                }
            }
        }

        return $this->emit($records, $dryRun, $verifyOnly);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function emit(array $records, bool $dryRun, bool $verifyOnly): int
    {
        $summary = [];
        $failed = false;
        foreach ($records as $r) {
            $s = $r['status'] ?? 'unknown';
            $summary[$s] = ($summary[$s] ?? 0) + 1;
            if (in_array($s, [ListingObjectMigrator::ERROR, ListingObjectMigrator::CONFLICT], true)) {
                $failed = true;
            }
        }

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'options' => [
                'scope' => $this->option('scope'),
                'prefix' => $this->option('prefix'),
                'dry_run' => $dryRun,
                'verify_only' => $verifyOnly,
                'force_conflicts' => (bool) $this->option('force-conflicts'),
            ],
            'summary' => $summary,
            'records' => $records,
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($dryRun) {
            $this->line($json); // dry-run persists nothing
        } else {
            $path = $this->persist($records);
            $this->info('Manifest written to private disk: '.$path);
        }

        $this->line('');
        $this->line($dryRun ? 'Migration plan (dry run — no changes):' : ($verifyOnly ? 'Verification result:' : 'Migration complete.'));
        $rows = [];
        foreach ($summary as $status => $count) {
            $rows[] = [$status, $count];
        }
        $rows === [] ? $this->line('No candidate objects found.') : $this->table(['status', 'count'], $rows);

        if ($failed) {
            $this->error('One or more objects had errors or conflicts. Inspect the manifest.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Persist the running manifest to the private disk. Returns the disk path.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    private function persist(array $records): string
    {
        $path = $this->option('manifest')
            ?: '_migration-manifests/migrate-'.now()->format('Ymd_His').'.json';

        $summary = [];
        foreach ($records as $r) {
            $s = $r['status'] ?? 'unknown';
            $summary[$s] = ($summary[$s] ?? 0) + 1;
        }

        Storage::disk(config('listing_storage.private_disk', 'private'))->put($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'records' => $records,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Load the set of already-completed keys from a manifest for --resume.
     *
     * @return array<string, true>
     */
    private function loadResumeSet(): array
    {
        if (! $this->option('resume')) {
            return [];
        }

        $disk = Storage::disk(config('listing_storage.private_disk', 'private'));
        $path = $this->option('manifest');
        if (! $path) {
            $candidates = $disk->files('_migration-manifests');
            sort($candidates);
            $path = end($candidates) ?: null;
        }
        if (! $path || ! $disk->exists($path)) {
            return [];
        }

        $data = json_decode((string) $disk->get($path), true);
        $done = [];
        foreach ($data['records'] ?? [] as $r) {
            if (in_array($r['status'] ?? '', [ListingObjectMigrator::MIGRATED, ListingObjectMigrator::SKIPPED_IDENTICAL], true)) {
                $done[($r['scope'] ?? 'public').'::'.($r['relative_key'] ?? '')] = true;
            }
        }

        return $done;
    }
}
