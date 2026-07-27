<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MigrateListingStorage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-C (HI-05A) — listing-storage:migrate command. Fake disks only; no network.
 */
class MigrateListingStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    public function test_write_mode_refuses_without_confirm(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');

        $this->artisan('listing-storage:migrate', ['--scope' => 'public'])
            ->assertExitCode(1);

        Storage::disk('s3_public')->assertMissing('a.jpg'); // nothing written
    }

    public function test_dry_run_writes_nothing_and_persists_no_manifest(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--dry-run' => true])
            ->assertExitCode(0);

        Storage::disk('s3_public')->assertMissing('a.jpg');
        $this->assertEmpty(Storage::disk('private')->allFiles('_migration-manifests'));
    }

    public function test_confirmed_public_migration_copies_and_writes_manifest(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');
        Storage::disk('public')->put('.gitignore', '*'); // excluded

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true])
            ->assertExitCode(0);

        Storage::disk('s3_public')->assertExists('auction/images/a.jpg');
        Storage::disk('s3_public')->assertMissing('.gitignore');
        $this->assertNotEmpty(Storage::disk('private')->allFiles('_migration-manifests'));
    }

    public function test_private_scope_never_touches_public_secondary(): void
    {
        Storage::disk('private')->put('landlord-disclosures/1/d.pdf', 'SECRET');

        $this->artisan('listing-storage:migrate', ['--scope' => 'private', '--confirm' => true])
            ->assertExitCode(0);

        Storage::disk('s3_private')->assertExists('landlord-disclosures/1/d.pdf');
        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
    }

    public function test_idempotent_second_run_reports_skipped(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');
        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true])->assertExitCode(0);
        // Second run: identical → skipped, exit 0, no changes.
        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true])->assertExitCode(0);

        $this->assertSame('DATA', Storage::disk('s3_public')->get('a.jpg'));
    }

    public function test_conflict_returns_failure_and_does_not_overwrite(): void
    {
        Storage::disk('public')->put('a.jpg', 'ABC');
        Storage::disk('s3_public')->put('a.jpg', 'DIFFERENT');

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true])
            ->assertExitCode(1);

        $this->assertSame('DIFFERENT', Storage::disk('s3_public')->get('a.jpg')); // untouched
    }

    /**
     * R2-E1: --verify-only is strictly read-only. It must not write to the
     * secondary AND must not persist a manifest to the private disk — the
     * previous behavior, which fell through to persist() because only --dry-run
     * was gated. A read-only audit that writes is not a read-only audit.
     */
    public function test_verify_only_needs_no_confirm_and_writes_nothing(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA'); // not on dest

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--verify-only' => true])
            ->assertExitCode(0);

        Storage::disk('s3_public')->assertMissing('a.jpg');
        $this->assertEmpty(Storage::disk('private')->allFiles('_migration-manifests'));
        $this->assertEmpty(Storage::disk('private')->allFiles()); // nowhere else either
    }

    /**
     * An explicit --manifest path must not become a write escape hatch: the
     * option names where a manifest WOULD go, it does not authorize writing one.
     */
    public function test_verify_only_with_explicit_manifest_option_still_writes_nothing(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');

        $this->artisan('listing-storage:migrate', [
            '--scope' => 'public',
            '--verify-only' => true,
            '--manifest' => 'custom/verify-report.json',
        ])->assertExitCode(0);

        Storage::disk('private')->assertMissing('custom/verify-report.json');
        $this->assertEmpty(Storage::disk('private')->allFiles());
        Storage::disk('s3_public')->assertMissing('a.jpg');
    }

    /**
     * --resume READS a prior manifest to skip completed keys; under --verify-only
     * it must not emit a new one.
     */
    public function test_verify_only_with_resume_still_writes_nothing(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');
        Storage::disk('public')->put('b.jpg', 'DATA2');
        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true])
            ->assertExitCode(0);

        $afterMigrate = Storage::disk('private')->allFiles('_migration-manifests');
        $this->assertCount(1, $afterMigrate); // the write run's manifest

        // Snapshot CONTENT, not just the file list: persist() names manifests
        // '...migrate-<Ymd_His>.json', so a second write inside the same second
        // silently OVERWRITES rather than adding a file. Comparing names only
        // would pass while the write still happened.
        $contentsBefore = Storage::disk('private')->get($afterMigrate[0]);

        $this->artisan('listing-storage:migrate', [
            '--scope' => 'public',
            '--verify-only' => true,
            '--resume' => true,
        ])->assertExitCode(0);

        // Same manifest set as before, byte-identical: the verify run wrote nothing.
        $this->assertSame($afterMigrate, Storage::disk('private')->allFiles('_migration-manifests'));
        $this->assertSame($contentsBefore, Storage::disk('private')->get($afterMigrate[0]));
    }

    /**
     * Removing the manifest write must not change what --verify-only REPORTS:
     * a genuine mismatch is still a failure exit.
     */
    public function test_verify_only_still_fails_on_conflict(): void
    {
        Storage::disk('public')->put('a.jpg', 'ABC');
        Storage::disk('s3_public')->put('a.jpg', 'DIFFERENT');

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--verify-only' => true])
            ->assertExitCode(1);

        $this->assertSame('DIFFERENT', Storage::disk('s3_public')->get('a.jpg')); // untouched
        $this->assertEmpty(Storage::disk('private')->allFiles());
    }

    /**
     * A not-yet-migrated object is missing_on_dest — expected mid-population, and
     * not a failure. Guards the exit-code semantics alongside the conflict case.
     */
    public function test_verify_only_reports_missing_on_dest_without_failing(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA'); // never migrated

        $exit = Artisan::call('listing-storage:migrate', ['--scope' => 'public', '--verify-only' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('missing_on_dest', $output);
        $this->assertEmpty(Storage::disk('private')->allFiles());
    }

    /**
     * R2-E1: a full population enumerates far more objects than a terminal can
     * absorb. --dry-run still shows records, but only the first N, and it says
     * plainly how many of how many it showed — silent truncation would read as
     * "that is the whole plan".
     */
    public function test_dry_run_caps_record_output_and_reports_truncation(): void
    {
        $limit = MigrateListingStorage::MAX_PRINTED_RECORDS;
        $total = $limit + 5;
        for ($i = 0; $i < $total; $i++) {
            Storage::disk('public')->put(sprintf('a/%03d.jpg', $i), 'X');
        }

        $exit = Artisan::call('listing-storage:migrate', ['--scope' => 'public', '--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertSame($limit, substr_count($output, '"relative_key"'), 'record output is not capped');
        $this->assertStringContainsString("Showing first {$limit} of {$total} records", $output);
        $this->assertStringContainsString('5 not shown', $output);
        $this->assertEmpty(Storage::disk('private')->allFiles('_migration-manifests'));
    }

    /**
     * Bounding the RECORD list must not bound the COUNTS: the summary still
     * accounts for every processed object, displayed or not.
     */
    public function test_dry_run_summary_counts_all_records_not_only_displayed(): void
    {
        $limit = MigrateListingStorage::MAX_PRINTED_RECORDS;
        $total = $limit + 5;
        for ($i = 0; $i < $total; $i++) {
            Storage::disk('public')->put(sprintf('a/%03d.jpg', $i), 'X');
        }

        Artisan::call('listing-storage:migrate', ['--scope' => 'public', '--dry-run' => true]);
        $output = Artisan::output();

        // JSON summary block and the rendered table both report the full count.
        $this->assertStringContainsString('"would_migrate": '.$total, $output);
        $this->assertMatchesRegularExpression('/would_migrate\s*\|\s*'.$total.'\b/', $output);
    }

    /**
     * Under the cap nothing is hidden, so no truncation notice should appear —
     * the common small-scope/--prefix run keeps its full, unannotated output.
     */
    public function test_dry_run_below_cap_prints_every_record_without_a_truncation_notice(): void
    {
        Storage::disk('public')->put('a/1.jpg', 'X');
        Storage::disk('public')->put('a/2.jpg', 'Y');
        Storage::disk('public')->put('a/3.jpg', 'Z');

        Artisan::call('listing-storage:migrate', ['--scope' => 'public', '--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(3, substr_count($output, '"relative_key"'));
        $this->assertStringNotContainsString('Showing first', $output);
        $this->assertStringNotContainsString('not shown', $output);
    }

    public function test_resume_skips_keys_recorded_done(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');
        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true])->assertExitCode(0);

        // Make the source differ from the destination. Without --resume this key
        // would now be a conflict (exit 1); with --resume it is skipped (exit 0).
        Storage::disk('public')->put('a.jpg', 'CHANGED-CONTENT');

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true, '--resume' => true])
            ->assertExitCode(0);

        // Destination retains the originally-migrated content (not overwritten).
        $this->assertSame('DATA', Storage::disk('s3_public')->get('a.jpg'));
    }

    public function test_limit_caps_processing(): void
    {
        Storage::disk('public')->put('a/1.jpg', 'X');
        Storage::disk('public')->put('a/2.jpg', 'Y');
        Storage::disk('public')->put('a/3.jpg', 'Z');

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--confirm' => true, '--limit' => 1])
            ->assertExitCode(0);

        $this->assertCount(1, Storage::disk('s3_public')->allFiles());
    }
}
