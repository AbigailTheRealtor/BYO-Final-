<?php

namespace App\Support\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * R2-B.1 (HI-05A) — centralized write path for listing documents/media.
 *
 * Routes uploads and deletes through the ListingStorageDisks resolver and adds
 * OPTIONAL, temporary dual-write mirroring to object storage. Local remains the
 * authoritative primary; the secondary is best-effort.
 *
 * Invariants:
 *   - With STORAGE_DUAL_WRITE=false (default) this is byte-for-byte equivalent to
 *     the previous `$file->storeAs($dir, $name, 'private')` / `disk('private')
 *     ->delete($path)` calls (the private selector defaults to the local 'private'
 *     disk).
 *   - PRIMARY write/delete failures propagate (hard fail) exactly as before.
 *   - SECONDARY (object-storage) write/delete failures NEVER fail the user
 *     request — they are logged and swallowed.
 *   - A private file is only ever mirrored to the PRIVATE secondary disk, never a
 *     public one; and never to a secondary that advertises a public URL.
 *
 * This class performs NO migration of existing files and reads nothing back for
 * delivery — reads are unchanged (dual-read is a later phase).
 */
class ListingStorageWriter
{
    public function __construct(private ListingStorageDisks $disks)
    {
    }

    /**
     * Store a PRIVATE listing document to the primary disk (+ optional mirror).
     * Returns the stored relative path (identical on both disks).
     */
    public function storePrivate(UploadedFile $file, string $dir, string $name): string
    {
        return $this->store($file, $dir, $name, true);
    }

    /**
     * Delete a PRIVATE listing document from the primary disk (+ optional mirror).
     */
    public function deletePrivate(string $path): void
    {
        $this->delete($path, true);
    }

    /**
     * Core store: primary (authoritative) then optional secondary mirror.
     */
    private function store(UploadedFile $file, string $dir, string $name, bool $private): string
    {
        $primaryName = $private ? $this->disks->privateDiskName() : $this->disks->publicDiskName();

        // Primary write — same semantics as the previous literal storeAs() call.
        $path = $file->storeAs($dir, $name, $primaryName);
        if ($path === false || $path === null || $path === '') {
            throw new RuntimeException('Listing storage primary write failed.');
        }

        if ($this->dualWriteEnabled()) {
            $this->mirrorWrite($primaryName, $this->secondaryName($private), $path, $private);
        }

        return $path;
    }

    /**
     * Core delete with parity: primary then optional secondary.
     */
    private function delete(string $path, bool $private): void
    {
        $primaryName = $private ? $this->disks->privateDiskName() : $this->disks->publicDiskName();

        // Primary delete — authoritative, same semantics as before.
        Storage::disk($primaryName)->delete($path);

        if ($this->dualWriteEnabled()) {
            try {
                Storage::disk($this->secondaryName($private))->delete($path);
            } catch (Throwable $e) {
                // Soft fail: never break the user action on a secondary miss.
                Log::warning('listing-storage dual-delete secondary failed', [
                    'path' => $path,
                    'disk' => $this->secondaryName($private),
                ]);
            }
        }
    }

    /**
     * Best-effort copy of the just-written primary object to the secondary disk.
     * Failures are logged and swallowed (never propagated).
     */
    private function mirrorWrite(string $primaryName, string $secondaryName, string $path, bool $private): void
    {
        // Privacy guard: refuse to mirror a private file to a disk that advertises
        // a public URL (a misconfiguration that could expose disclosures).
        if ($private && ! empty(config("filesystems.disks.{$secondaryName}.url"))) {
            Log::warning('listing-storage dual-write skipped: private secondary advertises a public url', [
                'disk' => $secondaryName,
            ]);

            return;
        }

        try {
            $primary = Storage::disk($primaryName);
            $secondary = Storage::disk($secondaryName);

            $stream = $primary->readStream($path);
            if ($stream === false || $stream === null) {
                throw new RuntimeException('Could not open primary stream for mirroring.');
            }
            $secondary->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (Throwable $e) {
            // Soft fail: primary already holds the authoritative copy.
            Log::warning('listing-storage dual-write secondary failed', [
                'path' => $path,
                'disk' => $secondaryName,
            ]);
        }
    }

    private function dualWriteEnabled(): bool
    {
        return (bool) config('listing_storage.dual_write', false);
    }

    private function secondaryName(bool $private): string
    {
        return $private
            ? (string) config('listing_storage.private_secondary_disk', 's3_private')
            : (string) config('listing_storage.public_secondary_disk', 's3_public');
    }
}
