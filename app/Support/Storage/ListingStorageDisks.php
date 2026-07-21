<?php

namespace App\Support\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * R2-A (HI-05A) — single resolution point for the disks that back listing
 * media (public photos/videos/documents) and private listing documents.
 *
 * Purpose: keep the (dozens of) call sites from reading env vars or hardcoding
 * disk names, so a later phase can flip local -> object storage by changing one
 * config value. This class is the ONLY place that reads config/listing_storage.
 *
 * Inert by default: config/listing_storage.php defaults resolve to the existing
 * local 'public' / 'private' disks, so behavior is unchanged until a selector is
 * pointed at 's3_public' / 's3_private'.
 *
 * Fail-closed: if a selector names a disk that is not defined in
 * config/filesystems.php, resolution throws. It never silently falls back to a
 * different disk. Exception messages name only the SELECTOR, never any value,
 * credential, bucket, or endpoint.
 */
class ListingStorageDisks
{
    /**
     * Disk name backing PUBLIC listing media. Defaults to local 'public'.
     */
    public function publicDiskName(): string
    {
        return $this->resolveName('public_disk');
    }

    /**
     * Disk name backing PRIVATE listing documents. Defaults to local 'private'.
     */
    public function privateDiskName(): string
    {
        return $this->resolveName('private_disk');
    }

    /**
     * The public listing disk instance.
     */
    public function publicDisk(): Filesystem
    {
        return Storage::disk($this->publicDiskName());
    }

    /**
     * The private listing disk instance.
     */
    public function privateDisk(): Filesystem
    {
        return Storage::disk($this->privateDiskName());
    }

    /**
     * Read a selector from config/listing_storage and validate it against the
     * defined filesystem disks. Fails closed on an unknown disk name.
     *
     * @param  string  $selector  'public_disk' | 'private_disk'
     */
    private function resolveName(string $selector): string
    {
        $name = config("listing_storage.{$selector}");

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException(
                "Listing storage selector [{$selector}] is not configured to a disk name."
            );
        }

        $defined = array_keys((array) config('filesystems.disks', []));
        if (! in_array($name, $defined, true)) {
            // Deliberately does NOT include $name's value beyond the selector
            // context, and never any credential/bucket/endpoint detail.
            throw new InvalidArgumentException(
                "Listing storage selector [{$selector}] points to a disk that is not defined in config/filesystems.php."
            );
        }

        return $name;
    }
}
