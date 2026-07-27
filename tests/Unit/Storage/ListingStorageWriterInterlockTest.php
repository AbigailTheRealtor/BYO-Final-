<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingStorageWriter;
use ArrayObject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-E1 (HI-05A) — the dual-write ORDERING INTERLOCK.
 *
 * Dual-write touches two disks per mutation, and the two directions are not
 * symmetric once object-first reads (R2-D.1 / R2-D.2) are selected:
 *
 *   - WRITE  primary → secondary. A secondary that is missing an object is safe:
 *     the read path falls back to local and serves the correct bytes.
 *   - DELETE secondary → primary. A secondary that RETAINS an object the primary
 *     no longer has is not safe — it is served as though it were current, and
 *     with the local copy gone there is nothing left to fall back to and nothing
 *     that will ever remove it.
 *
 * The interlock is therefore: the secondary copy must be confirmed gone BEFORE
 * the primary is removed, and if it cannot be confirmed gone the primary is
 * retained so both disks stay in the same state. Retaining is recoverable — a
 * retry converges once the secondary is reachable — whereas an orphaned
 * secondary object is not.
 *
 * Faked disks only; no network.
 */
class ListingStorageWriterInterlockTest extends TestCase
{
    private const DIR = 'auction/images';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    private function writer(): ListingStorageWriter
    {
        return app(ListingStorageWriter::class);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->create('photo.jpg', 4);
    }

    /**
     * Replace a faked disk with a pass-through decorator that appends
     * "<label>:<method>" to $events for every call. Returns the real faked disk.
     */
    private function recordDisk(string $disk, string $label, ArrayObject $events)
    {
        $inner = Storage::disk($disk);

        Storage::set($disk, new class($inner, $label, $events)
        {
            public function __construct(private $inner, private string $label, private ArrayObject $events)
            {
            }

            public function __call($method, $args)
            {
                $this->events[] = $this->label.':'.$method;

                return $this->inner->{$method}(...$args);
            }
        });

        return $inner;
    }

    /**
     * Replace a faked disk with one whose delete() fails. $mode:
     *   'throw'  — the disk is unreachable (delete raises).
     *   'silent' — delete reports back but the object is still there.
     * $failures caps how many delete calls fail before behaving normally.
     */
    private function failingDeleteDisk(string $disk, string $mode, int $failures = PHP_INT_MAX)
    {
        $inner = Storage::disk($disk);

        Storage::set($disk, new class($inner, $mode, $failures)
        {
            private int $seen = 0;

            public function __construct(private $inner, private string $mode, private int $failures)
            {
            }

            public function delete($paths)
            {
                if ($this->seen++ < $this->failures) {
                    if ($this->mode === 'throw') {
                        throw new \RuntimeException('object storage unreachable');
                    }

                    return false; // reported, but the object survives
                }

                return $this->inner->delete($paths);
            }

            public function __call($method, $args)
            {
                return $this->inner->{$method}(...$args);
            }
        });

        return $inner;
    }

    /** INTERLOCK 1 — the secondary is cleared before the primary is touched. */
    public function test_delete_clears_the_secondary_before_the_primary(): void
    {
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'a.jpg');

        $events = new ArrayObject();
        $this->recordDisk('public', 'primary', $events);
        $this->recordDisk('s3_public', 'secondary', $events);

        $this->writer()->deletePublic($path);

        $order = $events->getArrayCopy();
        $secondary = array_search('secondary:delete', $order, true);
        $primary = array_search('primary:delete', $order, true);

        $this->assertNotFalse($secondary, 'secondary delete never happened: '.implode(', ', $order));
        $this->assertNotFalse($primary, 'primary delete never happened: '.implode(', ', $order));
        $this->assertLessThan($primary, $secondary, 'secondary must be cleared first: '.implode(', ', $order));
    }

    /**
     * INTERLOCK 2 — an unreachable secondary retains the primary.
     *
     * The state that must never exist is "secondary has it, primary does not".
     * Both disks keeping the object is consistent and recoverable.
     */
    public function test_delete_retains_the_primary_when_the_secondary_cannot_be_cleared(): void
    {
        Log::spy();
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'b.jpg');
        $secondary = $this->failingDeleteDisk('s3_public', 'throw');

        $this->writer()->deletePublic($path); // soft: never fails the request

        Storage::disk('public')->assertExists($path);   // primary retained
        $secondary->assertExists($path);                // secondary still holds it
        Log::shouldHaveReceived('warning')->once();
    }

    /** INTERLOCK 2b — a delete the secondary reports but does not honour. */
    public function test_delete_retains_the_primary_when_the_secondary_silently_keeps_the_object(): void
    {
        Log::spy();
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'c.jpg');
        $secondary = $this->failingDeleteDisk('s3_public', 'silent');

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertExists($path);
        $secondary->assertExists($path);
        Log::shouldHaveReceived('warning')->once();
    }

    /** Private documents get the same interlock — this is the sensitive scope. */
    public function test_private_delete_retains_the_primary_when_the_secondary_cannot_be_cleared(): void
    {
        Log::spy();
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePrivate(
            UploadedFile::fake()->create('disclosure.pdf', 4),
            'seller-disclosures/9',
            'd.pdf'
        );
        $secondary = $this->failingDeleteDisk('s3_private', 'throw');

        $this->writer()->deletePrivate($path);

        Storage::disk('private')->assertExists($path);
        $secondary->assertExists($path);
    }

    /**
     * NOT an interlock failure: the object was never mirrored.
     *
     * During R2-E1 most keys predate dual-write and are not on the secondary yet.
     * "Already absent" is the desired end state, so the primary delete proceeds —
     * otherwise the interlock would block every delete until a full population
     * completed.
     */
    public function test_delete_proceeds_when_the_object_was_never_mirrored(): void
    {
        config(['listing_storage.dual_write' => true]);
        Storage::disk('public')->put(self::DIR.'/e.jpg', 'LOCAL-ONLY');

        $this->writer()->deletePublic(self::DIR.'/e.jpg');

        Storage::disk('public')->assertMissing(self::DIR.'/e.jpg');
        $this->assertEmpty(Storage::disk('s3_public')->allFiles());
    }

    /** A retry converges once the secondary recovers. */
    public function test_delete_converges_on_retry_once_the_secondary_recovers(): void
    {
        config(['listing_storage.dual_write' => true]);
        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'f.jpg');
        $secondary = $this->failingDeleteDisk('s3_public', 'throw', 1); // first attempt only

        $this->writer()->deletePublic($path);
        Storage::disk('public')->assertExists($path); // held back

        $this->writer()->deletePublic($path); // retry

        Storage::disk('public')->assertMissing($path);
        $secondary->assertMissing($path);
    }

    /**
     * NOT an interlock failure: the public secondary selector is misconfigured to
     * a private disk. The mirror was refused too, so there is no secondary copy to
     * keep in step — a config error must not block the user's delete.
     */
    public function test_refused_public_secondary_does_not_block_the_primary_delete(): void
    {
        Log::spy();
        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'g.jpg');
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 's3_private',
        ]);

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertMissing($path);
        Log::shouldHaveReceived('warning')->once();
    }

    /** WRITE ordering is the reverse and must stay that way: primary first. */
    public function test_write_still_reaches_the_primary_before_the_secondary(): void
    {
        config(['listing_storage.dual_write' => true]);

        $events = new ArrayObject();
        $this->recordDisk('public', 'primary', $events);
        $this->recordDisk('s3_public', 'secondary', $events);

        $this->writer()->storePublic($this->photo(), self::DIR, 'h.jpg');

        $order = $events->getArrayCopy();
        $firstPrimary = null;
        $secondaryWrite = array_search('secondary:writeStream', $order, true);
        foreach ($order as $i => $event) {
            if (str_starts_with($event, 'primary:')) {
                $firstPrimary = $i;
                break;
            }
        }

        $this->assertNotNull($firstPrimary, 'primary was never written: '.implode(', ', $order));
        $this->assertNotFalse($secondaryWrite, 'secondary was never mirrored: '.implode(', ', $order));
        $this->assertLessThan($secondaryWrite, $firstPrimary, 'primary must be written first: '.implode(', ', $order));
    }

    /**
     * The asymmetry, pinned: a failed MIRROR stays soft and keeps the primary,
     * because a secondary missing an object degrades to a correct local read.
     */
    public function test_mirror_write_failure_remains_soft(): void
    {
        Log::spy();
        config([
            'listing_storage.dual_write' => true,
            'listing_storage.public_secondary_disk' => 'undefined_secondary_disk',
        ]);

        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'i.jpg');

        Storage::disk('public')->assertExists($path);
        Log::shouldHaveReceived('warning')->once();
    }

    /** Dual-write off stays byte-for-byte: primary delete, no secondary contact. */
    public function test_dual_write_off_deletes_the_primary_directly(): void
    {
        $path = $this->writer()->storePublic($this->photo(), self::DIR, 'j.jpg');
        $secondary = $this->failingDeleteDisk('s3_public', 'throw'); // would raise if touched

        $this->writer()->deletePublic($path);

        Storage::disk('public')->assertMissing($path);
        $this->assertEmpty($secondary->allFiles());
    }
}
