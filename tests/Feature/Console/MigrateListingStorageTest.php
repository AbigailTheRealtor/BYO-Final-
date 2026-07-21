<?php

namespace Tests\Feature\Console;

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

    public function test_verify_only_needs_no_confirm_and_writes_nothing(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA'); // not on dest

        $this->artisan('listing-storage:migrate', ['--scope' => 'public', '--verify-only' => true])
            ->assertExitCode(0);

        Storage::disk('s3_public')->assertMissing('a.jpg');
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
