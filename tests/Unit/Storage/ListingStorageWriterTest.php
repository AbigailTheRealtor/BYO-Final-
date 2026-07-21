<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-B.1 (HI-05A) — ListingStorageWriter behavior.
 *
 * All disks are faked, so no network occurs. Covers: local-only default,
 * successful dual-write, secondary-write soft-failure, delete parity, and
 * edit/resave safety.
 */
class ListingStorageWriterTest extends TestCase
{
    private const DIR = 'landlord-disclosures/1/landlord-disclosure';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('s3_private');
        Storage::fake('s3_public');
    }

    private function writer(): ListingStorageWriter
    {
        return app(ListingStorageWriter::class);
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('disclosure.pdf', 12);
    }

    /** (1) default (dual-write off) writes only the local primary. */
    public function test_local_only_default_writes_primary_only(): void
    {
        // dual_write defaults to false.
        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'a.pdf');

        $this->assertSame(self::DIR.'/a.pdf', $path);
        Storage::disk('private')->assertExists($path);
        Storage::disk('s3_private')->assertMissing($path);
    }

    /** (2) dual-write mirrors identical bytes to the private secondary. */
    public function test_dual_write_mirrors_to_private_secondary(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'b.pdf');

        Storage::disk('private')->assertExists($path);
        Storage::disk('s3_private')->assertExists($path);
        $this->assertSame(
            Storage::disk('private')->get($path),
            Storage::disk('s3_private')->get($path)
        );
    }

    /** (3) a secondary-write failure never fails the request; primary persists. */
    public function test_secondary_write_failure_is_soft(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            // Point the secondary at an undefined disk → Storage::disk() throws,
            // which the writer must catch and swallow.
            'listing_storage.private_secondary_disk' => 'undefined_secondary_disk',
        ]);

        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'c.pdf');

        Storage::disk('private')->assertExists($path); // primary authoritative copy is intact
        Log::shouldHaveReceived('warning')->once();
    }

    /** (4) delete parity: dual-write removes from both disks. */
    public function test_delete_parity_removes_from_both(): void
    {
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'd.pdf');
        Storage::disk('private')->assertExists($path);
        Storage::disk('s3_private')->assertExists($path);

        $this->writer()->deletePrivate($path);

        Storage::disk('private')->assertMissing($path);
        Storage::disk('s3_private')->assertMissing($path);
    }

    /** (5) edit/resave: new file on both disks, old removed from both. */
    public function test_edit_resave_new_written_old_removed(): void
    {
        config(['listing_storage.dual_write' => true]);
        $old = $this->writer()->storePrivate($this->file(), self::DIR, 'old.pdf');
        $new = $this->writer()->storePrivate($this->file(), self::DIR, 'new.pdf');
        $this->writer()->deletePrivate($old);

        Storage::disk('private')->assertExists($new);
        Storage::disk('s3_private')->assertExists($new);
        Storage::disk('private')->assertMissing($old);
        Storage::disk('s3_private')->assertMissing($old);
    }

    /** (7) with dual-write off, the secondary is never touched (no network). */
    public function test_default_never_touches_secondary(): void
    {
        $this->writer()->storePrivate($this->file(), self::DIR, 'e.pdf');
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
    }
}
