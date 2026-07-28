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

    // ---------------------------------------------------------------------
    // Staged forced overwrite (--force-conflicts)
    //
    // A forced overwrite is the only path that reaches the upload with an object
    // already on the destination. Every failure before the final swap must leave
    // that object exactly as it was.
    // ---------------------------------------------------------------------

    /** Options for a forced overwrite. */
    private function force(): array
    {
        return ['force_conflicts' => true];
    }

    /**
     * Install a destination decorator with per-test hooks, and return the real
     * faked disk so assertions bypass the decorator.
     *
     * @param  array<string, mixed>  $hooks  onWrite, throwOnWrite, corruptTo, failMove, record
     */
    private function decorateDest(string $disk, array $hooks)
    {
        $inner = Storage::disk($disk);

        Storage::set($disk, new class($inner, $hooks)
        {
            public function __construct(private $inner, private array $hooks)
            {
            }

            public function writeStream($path, $resource, array $options = [])
            {
                if (isset($this->hooks['record'])) {
                    ($this->hooks['record'])($path);
                }
                if (isset($this->hooks['onWrite'])) {
                    ($this->hooks['onWrite'])();
                }
                if (! empty($this->hooks['throwOnWrite'])) {
                    throw new \RuntimeException('adapter refused the staged write');
                }
                if (isset($this->hooks['corruptTo'])) {
                    // Land bytes that are not what the source holds.
                    return $this->inner->put($path, $this->hooks['corruptTo']);
                }

                return $this->inner->writeStream($path, $resource, $options);
            }

            public function move($from, $to)
            {
                if (! empty($this->hooks['failMove'])) {
                    return false;
                }

                return $this->inner->move($from, $to);
            }

            public function __call($method, $args)
            {
                return $this->inner->{$method}(...$args);
            }
        });

        return $inner;
    }

    /** @return array<int, string> staged objects currently on the disk */
    private function stagedResidue($disk): array
    {
        return $disk->allFiles(ListingObjectMigrator::STAGING_PREFIX);
    }

    /** The happy path still replaces the old object. */
    public function test_forced_overwrite_without_a_race_replaces_the_destination(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        $this->assertSame('NEWBYTES', Storage::disk('s3_public')->get('auction/images/a.jpg'));
        $this->assertTrue($r['staging']['used']);
        $this->assertSame('ok', $r['staging']['swap']);
    }

    /** No staged object survives a success. */
    public function test_no_staging_residue_remains_after_a_successful_forced_overwrite(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertEmpty($this->stagedResidue(Storage::disk('s3_public')));
    }

    /** A source deleted mid-staging must not cost the destination its object. */
    public function test_source_deleted_during_staged_upload_leaves_the_destination_untouched(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $dest = $this->decorateDest('s3_public', [
            'onWrite' => fn () => Storage::disk('public')->delete('auction/images/a.jpg'),
        ]);

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertSame(ListingObjectMigrator::SOURCE_VANISHED, $r['status']);
        $this->assertSame('OLD', $dest->get('auction/images/a.jpg')); // preserved
        $this->assertEmpty($this->stagedResidue($dest));
    }

    /** Same for a source replaced mid-staging. */
    public function test_source_replaced_during_staged_upload_leaves_the_destination_untouched(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $dest = $this->decorateDest('s3_public', [
            'onWrite' => fn () => Storage::disk('public')->put('auction/images/a.jpg', 'OTHERBYT'),
        ]);

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertSame(ListingObjectMigrator::SOURCE_CHANGED, $r['status']);
        $this->assertSame('OLD', $dest->get('auction/images/a.jpg')); // preserved
        $this->assertEmpty($this->stagedResidue($dest));
    }

    /** Bytes that fail verification are never promoted over the original. */
    public function test_staged_verification_failure_leaves_the_destination_untouched(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $dest = $this->decorateDest('s3_public', ['corruptTo' => 'TRUNC']);

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertSame(ListingObjectMigrator::ERROR, $r['status']);
        $this->assertContains($r['error'], [
            ListingObjectMigrator::E_PARTIAL_UPLOAD,
            ListingObjectMigrator::E_CHECKSUM_MISMATCH,
        ]);
        $this->assertSame('OLD', $dest->get('auction/images/a.jpg')); // preserved
        $this->assertEmpty($this->stagedResidue($dest));
    }

    /** A staged write that throws must not have cost the original either. */
    public function test_staged_write_failure_leaves_the_destination_untouched(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $dest = $this->decorateDest('s3_public', ['throwOnWrite' => true]);

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertSame(ListingObjectMigrator::ERROR, $r['status']);
        $this->assertSame('OLD', $dest->get('auction/images/a.jpg')); // preserved
        $this->assertEmpty($this->stagedResidue($dest));
    }

    /**
     * The swap is delete-then-move and is NOT atomic. If the move does not take,
     * the original is already gone — reads fall back to local, which is correct —
     * but this run migrated nothing and must not say otherwise.
     */
    public function test_final_move_failure_is_reported_as_an_error_not_migrated(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $dest = $this->decorateDest('s3_public', ['failMove' => true]);

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertSame(ListingObjectMigrator::ERROR, $r['status']);
        $this->assertNotSame(ListingObjectMigrator::MIGRATED, $r['status']);
        $this->assertSame('move_failed', $r['staging']['swap']);
        // No stale object is served in place of the real one, and no residue.
        $dest->assertMissing('auction/images/a.jpg');
        $this->assertEmpty($this->stagedResidue($dest));
    }

    /** Staged keys live beneath the one authoritative prefix, never a listing key. */
    public function test_staged_keys_are_generated_beneath_the_authoritative_prefix(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $written = [];
        $this->decorateDest('s3_public', ['record' => function ($path) use (&$written) {
            $written[] = $path;
        }]);

        $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertCount(1, $written);
        $this->assertStringStartsWith(ListingObjectMigrator::STAGING_PREFIX.'/', $written[0]);
        $this->assertNotSame('auction/images/a.jpg', $written[0]);
    }

    /** Each attempt gets its own staged key, so concurrent runs cannot collide. */
    public function test_staged_keys_are_unique_per_attempt(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'NEWBYTES');
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLD');

        $written = [];
        $this->decorateDest('s3_public', ['record' => function ($path) use (&$written) {
            $written[] = $path;
        }]);

        $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        // The first overwrite left the destination identical to the source, which
        // the second call would short-circuit as skipped_identical before writing.
        // Make it differ again so a second forced overwrite genuinely happens.
        Storage::disk('s3_public')->put('auction/images/a.jpg', 'OLDAGAIN');
        $this->migrator()->process(false, 'auction/images/a.jpg', $this->force());

        $this->assertCount(2, $written);
        $this->assertNotSame($written[0], $written[1]);
    }

    /** Regression: the destination-missing path must not stage at all. */
    public function test_destination_missing_path_does_not_stage(): void
    {
        Storage::disk('public')->put('auction/images/a.jpg', 'HELLO');

        $written = [];
        $this->decorateDest('s3_public', ['record' => function ($path) use (&$written) {
            $written[] = $path;
        }]);

        $r = $this->migrator()->process(false, 'auction/images/a.jpg', []);

        $this->assertSame(ListingObjectMigrator::MIGRATED, $r['status']);
        $this->assertFalse($r['staging']['used']);
        $this->assertSame(['auction/images/a.jpg'], $written); // written straight to the final key
    }
}
