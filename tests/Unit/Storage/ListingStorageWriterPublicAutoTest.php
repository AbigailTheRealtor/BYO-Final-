<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * R2-B.2b (HI-05A) — ListingStorageWriter::storePublicAuto() behavior.
 *
 * storePublicAuto() is the SOFT-FAIL public writer used by the auction/bid,
 * agent-preset and offer-photo call sites migrated in R2-B.2b. It replaces
 * literal `$file->store($dir, 'public')`, `$file->storeAs($dir, $name, 'public')`
 * and `Storage::disk('public')->putFileAs($dir, $file, $name)` calls whose
 * existing behavior was NON-throwing on a falsey result.
 *
 * Contract under test (vs. the hard-failing storePublic()):
 *   - Auto-name (name=null) mirrors `$file->store($dir, 'public')`.
 *   - Explicit name mirrors `$file->storeAs($dir, $name, 'public')`.
 *   - Returned path is byte-identical to the legacy call it replaced.
 *   - Inert by default: only the local public primary is touched.
 *   - Dual-write mirrors to the PUBLIC secondary; failures are soft.
 *   - The public/private isolation guard is enforced on the mirror.
 *   - A misconfigured PRIMARY disk still fails closed (config error), exactly
 *     as the literal store()/storeAs() call did — this is disk resolution, not
 *     the RuntimeException-on-false path that storePublicAuto intentionally omits.
 *
 * All disks are faked, so no network occurs.
 */
class ListingStorageWriterPublicAutoTest extends TestCase
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

    private function file(string $name = 'promo.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 12, 'application/pdf');
    }

    /** (1) auto-name, dual-write off: only the local public primary is written. */
    public function test_auto_name_local_only_default(): void
    {
        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertMissing($path);
        Storage::disk('s3_private')->assertMissing($path);
    }

    /** (2) auto-name returns "$dir/<hashed-name>.<ext>", same shape as store(). */
    public function test_auto_name_returns_dir_prefixed_hashed_path(): void
    {
        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        $this->assertStringStartsWith(self::PROMO.'/', $path);
        $this->assertSame('pdf', pathinfo($path, PATHINFO_EXTENSION));
        $this->assertNotSame(self::PROMO.'/', $path); // a real basename exists
    }

    /** (3) explicit name returns "$dir/$name", byte-identical to storeAs(). */
    public function test_explicit_name_returns_dir_slash_name(): void
    {
        $name = '3f1a7c8e-0b2d-4a55-9f10-6d2b8c4e1a90.pdf';

        $path = $this->writer()->storePublicAuto($this->file(), self::DOCS, $name);

        $this->assertSame(self::DOCS.'/'.$name, $path);
        $this->assertSame($name, basename($path));
        Storage::disk('public')->assertExists($path);
    }

    /** (4) dual-write mirrors the auto-named file to the PUBLIC secondary. */
    public function test_dual_write_mirrors_auto_named_to_public_secondary(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertExists($path);
        $this->assertSame(
            Storage::disk('public')->get($path),
            Storage::disk('s3_public')->get($path)
        );
    }

    /** (5) dual-write mirrors the explicit-named file to the PUBLIC secondary. */
    public function test_dual_write_mirrors_explicit_named_to_public_secondary(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublicAuto($this->file(), self::DOCS, 'card.pdf');

        Storage::disk('s3_public')->assertExists($path);
    }

    /** (6) a secondary-write failure is soft: the primary path is returned intact. */
    public function test_secondary_write_failure_is_soft(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 'undefined_secondary_disk',
        ]);

        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        $this->assertNotFalse($path);
        Storage::disk('public')->assertExists($path); // authoritative copy intact
        Log::shouldHaveReceived('warning')->once();
    }

    /** (7) dual-write off never touches any secondary disk (no network). */
    public function test_default_never_touches_secondary(): void
    {
        $this->writer()->storePublicAuto($this->file(), self::PROMO);

        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
    }

    /**
     * (8) ISOLATION GUARD: if the public secondary is misconfigured to a private
     * disk, the mirror is refused — auto-named public media is never written into
     * private storage. The primary stays authoritative and the call still returns.
     */
    public function test_isolation_guard_refuses_mirror_to_private_disk(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 's3_private',
        ]);

        $path = $this->writer()->storePublicAuto($this->file(), self::PROMO);

        Storage::disk('public')->assertExists($path);      // primary still written
        Storage::disk('s3_private')->assertMissing($path); // mirror refused
        $this->assertEmpty(Storage::disk('s3_private')->allFiles());
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * (9) A misconfigured PRIMARY disk still fails closed (config error at disk
     * resolution) — identical to what the literal store()/storeAs() call did.
     * This is NOT the RuntimeException-on-false path, which storePublicAuto omits.
     */
    public function test_misconfigured_primary_disk_fails_closed(): void
    {
        config(['listing_storage.public_disk' => 'undefined_primary_disk']);

        $this->expectException(InvalidArgumentException::class);

        $this->writer()->storePublicAuto($this->file(), self::PROMO);
    }

    /** (10) REGRESSION: storePublic() hard-fail and deletePublic() are unchanged. */
    public function test_regression_store_public_and_delete_public_unchanged(): void
    {
        config(['listing_storage.dual_write' => true]);

        $path = $this->writer()->storePublic($this->file(), self::DOCS, 'x.pdf');
        Storage::disk('public')->assertExists($path);
        Storage::disk('s3_public')->assertExists($path);

        $this->writer()->deletePublic($path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('s3_public')->assertMissing($path);
    }
}
