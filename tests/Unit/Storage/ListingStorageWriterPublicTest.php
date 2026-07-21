<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * R2-B.2a (HI-05A) — ListingStorageWriter PUBLIC media behavior.
 *
 * Mirrors the R2-B.1 private-path coverage for the public path used by the eight
 * OfferListing components. All disks are faked, so no network occurs. Covers:
 * local-only default, exact returned path, dual-write mirroring, secondary
 * soft-failure, delete parity, and primary hard-failure.
 */
class ListingStorageWriterPublicTest extends TestCase
{
    private const IMAGES = 'auction/images';

    private const VIDEOS = 'auction/videos';

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

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->create('listing-photo.jpg', 24);
    }

    /** (1) default (dual-write off) writes only the local public primary. */
    public function test_local_only_default_writes_primary_only(): void
    {
        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'a.jpg');

        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertMissing($path);
    }

    /**
     * (2) the returned path is byte-identical to what the legacy
     * `$file->storeAs('auction/images', $name, 'public')` call returned, because
     * call sites persist the bare filename to meta and build URLs from it.
     */
    public function test_returned_path_is_unchanged_dir_slash_name(): void
    {
        $name = '3f1a7c8e-0b2d-4a55-9f10-6d2b8c4e1a90.jpg';

        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, $name);

        $this->assertSame(self::IMAGES.'/'.$name, $path);
        $this->assertSame($name, basename($path));
    }

    /** (3) dual-write mirrors identical bytes to the PUBLIC secondary. */
    public function test_dual_write_mirrors_to_public_secondary(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublic($this->photo(), self::VIDEOS, 'b.mp4');

        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertExists($path);
        $this->assertSame(
            Storage::disk('public')->get($path),
            Storage::disk('s3_public')->get($path)
        );
    }

    /** (4) a secondary-write failure never fails the request; primary persists. */
    public function test_secondary_write_failure_is_soft(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 'undefined_secondary_disk',
        ]);

        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'c.jpg');

        Storage::disk('public')->assertExists($path); // authoritative copy intact
        Log::shouldHaveReceived('warning')->once();
    }

    /** (5) delete parity: dual-write removes from both disks. */
    public function test_delete_parity_removes_from_both(): void
    {
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'd.jpg');
        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertExists($path);

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertMissing($path);
        Storage::disk('s3_public')->assertMissing($path);
    }

    /** (6) a secondary-delete failure never fails the request; primary is removed. */
    public function test_secondary_delete_failure_is_soft(): void
    {
        Log::spy();
        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'e.jpg');
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 'undefined_secondary_disk',
        ]);

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertMissing($path); // primary delete still happened
        Log::shouldHaveReceived('warning')->once();
    }

    /** (7) with dual-write off, no secondary is touched at all (no network). */
    public function test_default_never_touches_secondary(): void
    {
        $this->writer()->storePublic($this->photo(), self::IMAGES, 'f.jpg');

        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
    }

    /** (8) PRIMARY failures remain HARD failures — they are not swallowed. */
    public function test_primary_write_failure_is_hard(): void
    {
        config(['listing_storage.public_disk' => 'undefined_primary_disk']);

        $this->expectException(InvalidArgumentException::class);

        $this->writer()->storePublic($this->photo(), self::IMAGES, 'g.jpg');
    }
}
