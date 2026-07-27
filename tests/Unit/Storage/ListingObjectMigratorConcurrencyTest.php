<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingObjectMigrator;
use Closure;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-E1 (HI-05A) — the migrator must be safe to run against a LIVE app with
 * dual-write enabled, which is the only way a full population can happen.
 *
 * process() decides everything about a key BEFORE the copy (exists + size +
 * SHA-256) and the streamed upload lands some time later. In that window a live
 * request can delete or replace the same object through ListingStorageWriter,
 * whose mirror/dual-delete has already run against the secondary. The copy then
 * lands on top of that and the secondary diverges from local — silently, since
 * the destination is verified against the now-stale pre-copy hash.
 *
 * Fake disks only; no network. The race is made deterministic by decorating the
 * destination disk so the concurrent mutation happens exactly when the migrator
 * calls writeStream().
 */
class ListingObjectMigratorConcurrencyTest extends TestCase
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

    /**
     * Replace a faked disk with a pass-through decorator that fires $onWrite the
     * moment the migrator streams to it — i.e. mid-copy. Returns the real faked
     * disk so assertions bypass the decorator.
     */
    private function raceOnWrite(string $disk, Closure $onWrite)
    {
        $inner = Storage::disk($disk);

        Storage::set($disk, new class($inner, $onWrite)
        {
            public function __construct(private $inner, private Closure $onWrite)
            {
            }

            public function writeStream($path, $resource, array $options = [])
            {
                ($this->onWrite)();

                return $this->inner->writeStream($path, $resource, $options);
            }

            public function __call($method, $args)
            {
                return $this->inner->{$method}(...$args);
            }
        });

        return $inner;
    }

    /**
     * ORDERING GAP 1 — a live delete lands while the copy is in flight.
     *
     * The dual-delete already ran against the secondary (where the object did not
     * exist yet), so a copy that lands afterwards resurrects a deleted object
     * that nothing will ever delete again.
     */
    public function test_object_deleted_mid_copy_is_not_resurrected_on_the_secondary(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $dest = $this->raceOnWrite('s3_public', function () {
            // A live ListingStorageWriter::deletePublic() during the copy.
            Storage::disk('public')->delete('auction/images/a.jpg');
        });

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', []);

        $this->assertSame(ListingObjectMigrator::SOURCE_VANISHED, $r['status']);
        $this->assertSame(ListingObjectMigrator::E_NONE, $r['error']);
        $dest->assertMissing('auction/images/a.jpg');
        $this->assertEmpty($dest->allFiles());
    }

    /**
     * A vanished private disclosure must not survive in the private bucket, where
     * the R2-D.1 object-first read path would still serve it.
     */
    public function test_private_object_deleted_mid_copy_leaves_no_orphan(): void
    {
        Storage::disk('private')->put('seller-disclosures/9/d.pdf', 'SECRET');

        $dest = $this->raceOnWrite('s3_private', function () {
            Storage::disk('private')->delete('seller-disclosures/9/d.pdf');
        });

        $r = $this->migrator()->process(true, 'seller-disclosures/9/d.pdf', []);

        $this->assertSame(ListingObjectMigrator::SOURCE_VANISHED, $r['status']);
        $dest->assertMissing('seller-disclosures/9/d.pdf');
        $this->assertEmpty($dest->allFiles());
    }

    /**
     * ORDERING GAP 2 — a live replace lands while the copy is in flight.
     *
     * Dual-write has already mirrored the NEW bytes; the migrator's copy of the
     * OLD bytes must not be left on the secondary, and must never be reported as
     * a successful migration. Same byte length on purpose: the check has to be
     * content-based, not a size comparison.
     */
    public function test_object_replaced_mid_copy_is_not_left_stale_on_the_secondary(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $dest = $this->raceOnWrite('s3_public', function () {
            // A live ListingStorageWriter::storePublic() over the same key.
            Storage::disk('public')->put('auction/images/a.jpg', 'WORLD');
        });

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', []);

        $this->assertSame(ListingObjectMigrator::SOURCE_CHANGED, $r['status']);
        $this->assertNotSame(ListingObjectMigrator::MIGRATED, $r['status']);
        // Rolled back rather than left stale: a missing key falls back to local
        // on read, whereas stale bytes would be served as if they were current.
        $dest->assertMissing('auction/images/a.jpg');
    }

    /**
     * A race is transient, not a corruption alarm: the rerun that follows must
     * copy the current bytes and report a clean migration.
     */
    public function test_rerun_after_a_race_converges_on_the_current_bytes(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $dest = $this->raceOnWrite('s3_public', function () {
            Storage::disk('public')->put('auction/images/a.jpg', 'WORLD');
        });
        $this->migrator()->process(false, 'auction/images/a.jpg', []);

        // Second pass, no race this time.
        Storage::set('s3_public', $dest);
        $r = $this->migrator()->process(false, 'auction/images/a.jpg', []);

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        $this->assertSame('WORLD', $dest->get('auction/images/a.jpg'));
    }

    /** Regression: an unraced copy is untouched by the revalidation. */
    public function test_unraced_copy_still_reports_migrated(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', []);

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        $this->assertSame('HELLO', Storage::disk('s3_public')->get('auction/images/a.jpg'));
    }

    /**
     * The whole point of the rollback is to leave no orphan. If the rollback
     * itself does not take, the run must say so — reporting the benign
     * source_vanished here would claim a clean secondary while an orphan sits on
     * it, which is the exact divergence this work exists to prevent.
     */
    public function test_a_rollback_that_does_not_take_is_reported_as_an_error(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $inner = Storage::disk('s3_public');

        Storage::set('s3_public', new class($inner)
        {
            public function __construct(private $inner)
            {
            }

            public function writeStream($path, $resource, array $options = [])
            {
                // Live delete mid-copy, so the migrator will attempt a rollback.
                Storage::disk('public')->delete('auction/images/a.jpg');

                return $this->inner->writeStream($path, $resource, $options);
            }

            /** The rollback delete silently fails; the object survives. */
            public function delete($paths)
            {
                return false;
            }

            public function __call($method, $args)
            {
                return $this->inner->{$method}(...$args);
            }
        });

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', []);

        $this->assertSame(ListingObjectMigrator::ERROR, $r['status']);
        $this->assertSame(ListingObjectMigrator::E_UNKNOWN, $r['error']);
        $this->assertNotSame(ListingObjectMigrator::SOURCE_VANISHED, $r['status']);
        // The orphan really is still there — which is why it must not be silent.
        $this->assertSame('HELLO', $inner->get('auction/images/a.jpg'));
    }
}
