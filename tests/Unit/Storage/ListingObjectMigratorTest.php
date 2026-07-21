<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingObjectMigrator;
use RuntimeException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-C (HI-05A) — ListingObjectMigrator per-object behavior. Fake disks only;
 * no network.
 */
class ListingObjectMigratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    private function migrator(): ListingObjectMigrator
    {
        return app(ListingObjectMigrator::class);
    }

    public function test_enumerate_filters_excludes_and_gitignore(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'A');
        Storage::disk('public')->put('.gitignore', '*');
        Storage::disk('public')->put('_backfill-manifests/x.json', '{}');
        Storage::disk('public')->put('_migration-manifests/y.json', '{}');

        $keys = $this->migrator()->enumerate(false, null);

        $this->assertContains('auction/images/a.jpg', $keys);
        $this->assertNotContains('.gitignore', $keys);
        $this->assertNotContains('_backfill-manifests/x.json', $keys);
        $this->assertNotContains('_migration-manifests/y.json', $keys);
    }

    public function test_migrates_public_to_s3_public_same_key_with_verification(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', ['force_conflicts' => false]);

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        Storage::disk('s3_public')->assertExists('auction/images/a.jpg');
        $this->assertSame('HELLO', Storage::disk('s3_public')->get('auction/images/a.jpg'));
        $this->assertSame(hash('sha256', 'HELLO'), $r['local_sha256']);
        $this->assertSame(hash('sha256', 'HELLO'), $r['dest_verification']['sha256']);
        $this->assertTrue($r['dest_verification']['size_match']);
    }

    public function test_private_never_migrates_to_public_secondary(): void
    {
        Storage::disk('private')->put('landlord-disclosures/1/d.pdf', 'SECRET');

        $r = $this->migrator()->process(true, 'landlord-disclosures/1/d.pdf', []);

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        Storage::disk('s3_private')->assertExists('landlord-disclosures/1/d.pdf');
        Storage::disk('s3_public')->assertMissing('landlord-disclosures/1/d.pdf');
        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
    }

    public function test_refuses_private_secondary_with_public_url(): void
    {
        config(['filesystems.disks.s3_private.url' => 'https://bucket.test/private']);
        Storage::disk('private')->put('landlord-disclosures/1/d.pdf', 'X');

        $r = $this->migrator()->process(true, 'landlord-disclosures/1/d.pdf', []);

        // process() never throws; the refusal surfaces as an error record.
        $this->assertSame(ListingObjectMigrator::ERROR, $r['status']);
        Storage::disk('s3_private')->assertMissing('landlord-disclosures/1/d.pdf');
    }

    public function test_idempotent_rerun_skips_identical(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');
        $this->migrator()->process(false, 'a.jpg', []);

        $r = $this->migrator()->process(false, 'a.jpg', []);

        $this->assertSame(ListingObjectMigrator::SKIPPED_IDENTICAL, $r['status']);
    }

    public function test_conflict_on_different_size_not_overwritten(): void
    {
        Storage::disk('public')->put('a.jpg', 'ABC');       // 3 bytes
        Storage::disk('s3_public')->put('a.jpg', 'DIFFERENT'); // 9 bytes

        $r = $this->migrator()->process(false, 'a.jpg', ['force_conflicts' => false]);

        $this->assertSame(ListingObjectMigrator::CONFLICT, $r['status']);
        $this->assertSame('DIFFERENT', Storage::disk('s3_public')->get('a.jpg')); // untouched
        $this->assertFalse($r['dest_verification']['size_match']);
    }

    public function test_needs_review_on_same_size_different_hash(): void
    {
        Storage::disk('public')->put('a.jpg', 'ABC');
        Storage::disk('s3_public')->put('a.jpg', 'XYZ'); // same size, different content

        $r = $this->migrator()->process(false, 'a.jpg', ['force_conflicts' => false]);

        $this->assertSame(ListingObjectMigrator::NEEDS_REVIEW, $r['status']);
        $this->assertSame('XYZ', Storage::disk('s3_public')->get('a.jpg')); // untouched
        $this->assertTrue($r['dest_verification']['size_match']);
    }

    public function test_force_conflicts_overwrites(): void
    {
        Storage::disk('public')->put('a.jpg', 'ABC');
        Storage::disk('s3_public')->put('a.jpg', 'DIFFERENT');

        $r = $this->migrator()->process(false, 'a.jpg', ['force_conflicts' => true]);

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        $this->assertSame('ABC', Storage::disk('s3_public')->get('a.jpg'));
    }

    public function test_dry_run_does_not_write(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');

        $r = $this->migrator()->process(false, 'a.jpg', ['dry_run' => true]);

        $this->assertSame(ListingObjectMigrator::WOULD_MIGRATE, $r['status']);
        Storage::disk('s3_public')->assertMissing('a.jpg');
    }

    public function test_verify_only_reports_without_writing(): void
    {
        Storage::disk('public')->put('a.jpg', 'DATA');

        // missing on dest
        $r1 = $this->migrator()->process(false, 'a.jpg', ['verify_only' => true]);
        $this->assertSame(ListingObjectMigrator::MISSING_ON_DEST, $r1['status']);
        Storage::disk('s3_public')->assertMissing('a.jpg');

        // identical on dest
        Storage::disk('s3_public')->put('a.jpg', 'DATA');
        $r2 = $this->migrator()->process(false, 'a.jpg', ['verify_only' => true]);
        $this->assertSame(ListingObjectMigrator::SKIPPED_IDENTICAL, $r2['status']);

        // differing on dest
        Storage::disk('public')->put('b.jpg', 'AAA');
        Storage::disk('s3_public')->put('b.jpg', 'BBBB');
        $r3 = $this->migrator()->process(false, 'b.jpg', ['verify_only' => true]);
        $this->assertSame(ListingObjectMigrator::CONFLICT, $r3['status']);
    }

    public function test_source_missing_is_captured_as_error(): void
    {
        $r = $this->migrator()->process(false, 'does/not/exist.jpg', []);
        $this->assertSame(ListingObjectMigrator::ERROR, $r['status']);
        $this->assertSame(ListingObjectMigrator::E_SOURCE_MISSING, $r['error']);
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->migrator()->enumerate(false, '../etc');
    }
}
