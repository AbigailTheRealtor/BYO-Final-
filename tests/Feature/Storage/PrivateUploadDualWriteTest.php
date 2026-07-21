<?php

namespace Tests\Feature\Storage;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-B.1 (HI-05A) — security/privacy invariants for private dual-write, plus the
 * byte-for-byte "off by default" guarantee. Faked disks only; no network.
 */
class PrivateUploadDualWriteTest extends TestCase
{
    private const DIR = 'seller-disclosures/9/seller-disclosure';

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
        return UploadedFile::fake()->create('disclosure.pdf', 8);
    }

    /** Byte-for-byte default: dual-write off ⇒ only local private, no secondaries. */
    public function test_off_by_default_matches_local_only_behavior(): void
    {
        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'a.pdf');

        Storage::disk('private')->assertExists($path);
        Storage::disk('s3_private')->assertMissing($path);
        Storage::disk('s3_public')->assertMissing($path);
        Storage::disk('public')->assertMissing($path);
    }

    /** SECURITY: a private file is NEVER mirrored to a public disk. */
    public function test_private_file_never_written_to_a_public_disk(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'b.pdf');

        Storage::disk('s3_private')->assertExists($path);  // correct target
        Storage::disk('s3_public')->assertMissing($path);  // never the public bucket
        Storage::disk('public')->assertMissing($path);
        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
    }

    /** PRIVACY GUARD: refuse to mirror a private file to a secondary that has a public url. */
    public function test_mirror_skipped_when_private_secondary_advertises_public_url(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'filesystems.disks.s3_private.url' => 'https://example-bucket.test/private',
        ]);

        $path = $this->writer()->storePrivate($this->file(), self::DIR, 'c.pdf');

        Storage::disk('private')->assertExists($path);      // primary still written
        Storage::disk('s3_private')->assertMissing($path);   // mirror refused
        Log::shouldHaveReceived('warning')->once();
    }
}
