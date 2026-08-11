<?php

namespace App\Http\Livewire\Concerns;

use App\Support\Storage\ListingStorageWriter;
use Illuminate\Support\Facades\Log;

/**
 * S4 — the deletion target for listing media comes from the OWNED RECORD, never from the client.
 *
 * THE DEFECT THIS CLOSES. Every Hire Agent media-delete path built its storage path by
 * concatenating a directory with a public Livewire property:
 *
 *     deletePublic('auction/images/' . $this->photo);
 *
 * `$this->photo` and `$this->video` are public properties, and Livewire 2 applies client
 * `syncInput` updates to any public property with no allowlist. Owning the listing row therefore
 * authorized the OPERATION but placed no constraint at all on the TARGET.
 *
 * Flysystem 1.x does not save us here. `Util::normalizeRelativePath()` collapses `..` with
 * `array_pop` and only raises once the stack is empty, so against the two-segment prefix these
 * paths use:
 *
 *     'other-listing.jpg'   -> auction/images/other-listing.jpg   (another user's photo)
 *     '../videos/x.mp4'     -> auction/videos/x.mp4               (crosses media directory)
 *     '../../probe.txt'     -> probe.txt                          (anywhere under the disk root)
 *     '../../../../etc/pw'  -> LogicException                     (root escape blocked)
 *
 * and `ListingStorageWriter` adds no normalization of its own. So the reachable blast radius was
 * any single file under the public disk root. Filenames are UUIDs but are not secret — public
 * listing pages publish them as image URLs — so targeting needed no guessing.
 *
 * WHY READING THE PERSISTED META IS NOT SUFFICIENT ON ITS OWN, AND VALIDATION IS MANDATORY.
 * The save paths contain a branch of the form `saveMeta('photo', $this->photo)` for the
 * already-a-string case, so the stored value is itself client-writable: an attacker can persist
 * `../../probe.txt` onto their OWN listing and then have a "read it from the record" delete
 * execute it for them. The persisted value narrows WHOSE value it is; only validation constrains
 * WHAT it may be. Both are required, which is why they live together in one method here.
 *
 * REJECT, DO NOT REPAIR. A malformed value is refused and logged, never rewritten into something
 * acceptable. Normalizing `../../probe.txt` down to `probe.txt` would turn a traversal attempt
 * into a successful deletion of a different file — the sanitizer becoming the exploit. There is
 * no path here from a dangerous stored value to a filesystem call.
 *
 * THE META IS CLEARED EITHER WAY, and that is deliberate. The user asked to remove their media; a
 * value this method refuses to act on is one the record should stop pointing at. Leaving it would
 * strand the listing on an unusable reference with no way to clear it. The cost is an orphaned
 * file, which is strictly preferable to deleting the wrong one.
 *
 * WHAT THIS TRAIT DOES NOT DO. It performs no authorization. The caller must hand it an auction it
 * has ALREADY resolved owner-scoped — the ownership question and the path-authority question are
 * separate, and merging them here would hide which of the two a given call site actually satisfies.
 * It also does not touch `ListingStorageWriter`: a sanitizer pushed down there would silently
 * change semantics for every caller, including `TenantAgentAuctionBid`, which already does this
 * correctly on its own.
 */
trait DeletesOwnedListingMedia
{
    /**
     * Is this stored value a bare media filename we are willing to delete?
     *
     * Allowlist-shaped rather than blocklist-shaped: the value must look like `name.ext` with no
     * path syntax of any kind. Separators and `..` are rejected explicitly (as required), and so
     * are NUL bytes, which truncate paths at the C boundary underneath PHP.
     */
    private function isDeletableStoredMediaFilename($value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        // Path separators — either flavour, since Flysystem rewrites backslashes to forward.
        if (strpbrk($value, "/\\") !== false) {
            return false;
        }

        // Any traversal syntax at all, including the bare '.' and '..' entries.
        if (strpos($value, '..') !== false || $value === '.') {
            return false;
        }

        if (strpos($value, "\0") !== false) {
            return false;
        }

        return true;
    }

    /**
     * Delete the media file recorded on an ALREADY OWNER-RESOLVED auction, then clear its meta.
     *
     * @param  mixed   $auction    an auction resolved owner-scoped by the caller
     * @param  string  $metaKey    'photo' | 'video'
     * @param  string  $directory  storage-relative media directory, without trailing slash
     */
    private function deleteOwnedListingMedia($auction, string $metaKey, string $directory): void
    {
        // The record is the only source of the filename. The Livewire property is display state.
        $storedFilename = $auction->info($metaKey);

        if ($this->isDeletableStoredMediaFilename($storedFilename)) {
            app(ListingStorageWriter::class)->deletePublic($directory . '/' . $storedFilename);
        } elseif ($storedFilename !== false && $storedFilename !== null && $storedFilename !== '') {
            // Present but unusable. Refused rather than repaired — see the class note.
            Log::warning('[LISTING MEDIA] refused to delete a malformed stored media path', [
                'auction_id' => $auction->id ?? null,
                'meta_key'   => $metaKey,
                'directory'  => $directory,
            ]);
        }

        $auction->deleteMeta($metaKey);
    }
}
