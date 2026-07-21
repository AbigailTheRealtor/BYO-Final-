<?php

namespace Tests\Feature\Storage;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-B.2a (HI-05A) — isolation invariants for the PUBLIC listing-media path,
 * plus the "off by default" guarantee. Faked disks only; no network.
 *
 * The load-bearing invariant here: public media must never write into, nor
 * delete out of, private storage — even when the public secondary selector is
 * misconfigured to name a private disk.
 */
class PublicMediaUploadTest extends TestCase
{
    private const IMAGES = 'auction/images';

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
        return UploadedFile::fake()->create('listing-photo.jpg', 8);
    }

    /** Byte-for-byte default: dual-write off ⇒ only local public, no secondaries. */
    public function test_off_by_default_matches_local_only_behavior(): void
    {
        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'a.jpg');

        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertMissing($path);
        Storage::disk('s3_private')->assertMissing($path);
        Storage::disk('private')->assertMissing($path);
    }

    /** SECURITY: a public file is NEVER mirrored into private storage. */
    public function test_public_file_never_written_to_private_storage(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'b.jpg');

        Storage::disk('s3_public')->assertExists($path);   // correct target
        Storage::disk('s3_private')->assertMissing($path); // never the private bucket
        Storage::disk('private')->assertMissing($path);
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
    }

    /**
     * ISOLATION GUARD (write): if the public secondary selector is misconfigured
     * to the private secondary, refuse the mirror rather than write public media
     * into the private bucket. Primary stays authoritative.
     */
    public function test_public_write_refused_when_public_secondary_points_at_private_disk(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 's3_private',
        ]);

        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'c.jpg');

        Storage::disk('public')->assertExists($path);      // primary still written
        Storage::disk('s3_private')->assertMissing($path); // mirror refused
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * ISOLATION GUARD (delete): the same misconfiguration must not let a public
     * delete remove a private object that happens to share the relative key.
     */
    public function test_public_delete_never_removes_a_private_object(): void
    {
        Log::spy();
        // A private document that shares the relative key of the public photo.
        Storage::disk('s3_private')->put(self::IMAGES.'/d.jpg', 'private-bytes');
        $path = $this->writer()->storePublic($this->photo(), self::IMAGES, 'd.jpg');

        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 's3_private',
        ]);

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertMissing($path);              // primary removed
        Storage::disk('s3_private')->assertExists(self::IMAGES.'/d.jpg'); // private survives
        $this->assertSame('private-bytes', Storage::disk('s3_private')->get(self::IMAGES.'/d.jpg'));
        Log::shouldHaveReceived('warning')->once();
    }

    /** REGRESSION: R2-B.1 private behavior is unchanged by the public path. */
    public function test_private_path_behavior_is_unchanged(): void
    {
        config(['listing_storage.dual_write' => true]);

        $private = $this->writer()->storePrivate(
            UploadedFile::fake()->create('disclosure.pdf', 4),
            'seller-disclosures/1/seller-disclosure',
            'p.pdf'
        );
        $public = $this->writer()->storePublic($this->photo(), self::IMAGES, 'q.jpg');

        Storage::disk('private')->assertExists($private);
        Storage::disk('s3_private')->assertExists($private);
        Storage::disk('s3_public')->assertMissing($private);

        Storage::disk('public')->assertExists($public);
        Storage::disk('s3_public')->assertExists($public);
        Storage::disk('s3_private')->assertMissing($public);
    }
}
