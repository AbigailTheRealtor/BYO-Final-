<?php

namespace Tests\Feature\Storage;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-B.2b (HI-05A) — isolation invariants for the auction/bid PUBLIC-media path
 * that R2-B.2b routes through storePublicAuto() (auto-named uploads such as bid
 * promo materials / business cards, and offer property photos).
 *
 * The load-bearing invariant, exactly as for the R2-B.2a OfferListing path:
 * public media must never write into, nor delete out of, private storage — even
 * when the public secondary selector is misconfigured to name a private disk.
 * Faked disks only; no network.
 */
class AuctionBidPublicMediaTest extends TestCase
{
    private const PROMO = 'auction/promo-materials';

    private const DOCS = 'auction/documents';

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
        return UploadedFile::fake()->create('promo.pdf', 8, 'application/pdf');
    }

    /** Byte-for-byte default: dual-write off ⇒ only local public, no secondaries. */
    public function test_off_by_default_matches_local_only_behavior(): void
    {
        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertMissing($path);
        Storage::disk('s3_private')->assertMissing($path);
        Storage::disk('private')->assertMissing($path);
    }

    /** SECURITY: an auto-named public file is NEVER mirrored into private storage. */
    public function test_auto_named_public_file_never_written_to_private_storage(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        Storage::disk('s3_public')->assertExists($path);   // correct target
        Storage::disk('s3_private')->assertMissing($path); // never the private bucket
        Storage::disk('private')->assertMissing($path);
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
    }

    /**
     * ISOLATION GUARD (write): a misconfigured public secondary (pointed at the
     * private secondary) must refuse the mirror rather than write public bid
     * media into the private bucket. Primary stays authoritative.
     */
    public function test_public_write_refused_when_public_secondary_points_at_private_disk(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 's3_private',
        ]);

        $path = $this->writer()->storePublicAuto($this->file(), self::DOCS, 'card.pdf');

        Storage::disk('public')->assertExists($path);      // primary still written
        Storage::disk('s3_private')->assertMissing($path); // mirror refused
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * ISOLATION GUARD (delete): the bid delete path (deletePublic) must never
     * remove a private object that happens to share the relative key, under the
     * same misconfiguration.
     */
    public function test_public_delete_never_removes_a_private_object(): void
    {
        Log::spy();
        // A private object that shares the relative key of the public file.
        Storage::disk('s3_private')->put(self::DOCS.'/card.pdf', 'private-bytes');
        $path = $this->writer()->storePublicAuto($this->file(), self::DOCS, 'card.pdf');

        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 's3_private',
        ]);

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertMissing($path);                    // primary removed
        Storage::disk('s3_private')->assertExists(self::DOCS.'/card.pdf'); // private survives
        $this->assertSame('private-bytes', Storage::disk('s3_private')->get(self::DOCS.'/card.pdf'));
        Log::shouldHaveReceived('warning')->once();
    }

    /** Dual-write parity: an auto-named file mirrors to, and deletes from, both disks. */
    public function test_dual_write_parity_store_and_delete_across_both_disks(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);
        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertExists($path);

        $this->writer()->deletePublic($path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('s3_public')->assertMissing($path);
    }
}
