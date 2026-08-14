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
     * @param  bool    $private    delete from the private disk instead of the public one
     */
    private function deleteOwnedListingMedia($auction, string $metaKey, string $directory, bool $private = false): void
    {
        // The record is the only source of the filename. The Livewire property is display state.
        $storedFilename = $auction->info($metaKey);

        if ($this->isDeletableStoredMediaFilename($storedFilename)) {
            $this->deleteOwnedMediaFile($directory, $storedFilename, $private);
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

    /**
     * S5 — delete ONE entry from a collection-backed media meta, then rewrite the remainder.
     *
     * WHY THIS IS NOT THE SCALAR METHOD. `property_photos` holds a JSON array, and the user is
     * choosing WHICH of several photos to remove. That makes the client's value a SELECTOR — a
     * legitimate and necessary input — where the scalar case has no client input at all. The
     * distinction this method draws is between selecting and authorizing:
     *
     *   · the client says WHICH entry;
     *   · the owned record decides WHETHER that entry exists and WHAT string is used as a path.
     *
     * So the selector is matched against the persisted array with a strict in_array(), and the
     * value actually concatenated into the path is the one read back OUT of that array — never
     * the one that arrived. A selector that is not a member is refused outright. Array position
     * is not used as authority: the client's local ordering can legitimately drift from the
     * persisted order (reordering, unsaved additions), and deleting by index would then remove a
     * different photo than the one the user clicked.
     *
     * @param  mixed   $auction    an auction resolved owner-scoped by the caller
     * @param  string  $metaKey    'property_photos'
     * @param  string  $directory  storage-relative media directory, without trailing slash
     * @param  mixed   $selected   the client's chosen entry — selector only, never a path
     * @param  bool    $private    delete from the private disk instead of the public one
     * @return bool                true when an entry was removed from the collection
     */
    private function deleteOwnedListingMediaFromCollection(
        $auction,
        string $metaKey,
        string $directory,
        $selected,
        bool $private = false
    ): bool {
        if (! is_string($selected) || $selected === '') {
            return false;
        }

        $stored = $auction->info($metaKey);

        $entries = [];
        if (is_array($stored)) {
            $entries = $stored;
        } elseif (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            $entries = is_array($decoded) ? $decoded : [];
        }

        // Membership of the owned set is the whole authorization. Strict comparison so a
        // loosely-equal value cannot stand in for a stored one.
        $position = array_search($selected, $entries, true);

        // The collection may also hold structured MLS media, which a strict scalar comparison
        // can never match. Resolve those through the entry model, which decides membership on
        // ListingPhotoEntry::key(). A user upload's key IS its filename, so the scalar path above
        // still answers first for every pre-existing collection and its behaviour is untouched.
        if ($position === false) {
            $position = $this->locateCollectionEntry($entries, $selected);
        }

        if ($position === false) {
            Log::warning('[LISTING MEDIA] refused to delete an entry absent from the owned collection', [
                'auction_id' => $auction->id ?? null,
                'meta_key'   => $metaKey,
            ]);

            return false;
        }

        // Read the path back OUT of the persisted collection rather than reusing the input.
        $storedFilename = $entries[$position];

        // MLS-SOURCED MEDIA IS NOT DELETED HERE, AND NOT BECAUSE IT IS AWKWARD.
        //
        // Under reference-only hosting there is no file of ours behind an MLS entry — the bytes
        // live at the provider and were never copied. So there is nothing on our disk this could
        // legitimately remove, and any path it constructed would either not exist or, far worse,
        // coincidentally name a real file belonging to something else.
        //
        // The entry is left in place rather than merely skipped-and-dropped: the MLS gallery is
        // reconciled as a SET by MlsListingGallerySync, and an entry silently removed here would
        // simply return on the next refresh. Owner control over MLS media is exercised through
        // the operations that are actually meaningful for it — choosing the cover, arranging the
        // order, and detaching the MLS gallery as a whole.
        if ($this->isMlsSourcedCollectionEntry($storedFilename)) {
            Log::info('[LISTING MEDIA] refused to delete an MLS-sourced entry; it is managed by the MLS sync', [
                'auction_id' => $auction->id ?? null,
                'meta_key'   => $metaKey,
            ]);

            return false;
        }

        if ($this->isDeletableStoredMediaFilename($storedFilename)) {
            $this->deleteOwnedMediaFile($directory, $storedFilename, $private);
        } elseif ($this->promotedUserFilename($storedFilename) !== null) {
            // A user upload promoted to array form (it carries a cover flag). The filename is
            // read back out of the OWNED record exactly as in the scalar case, and is validated
            // by the same allow-list before it can reach the filesystem.
            $this->deleteOwnedMediaFile($directory, $this->promotedUserFilename($storedFilename), $private);
        } else {
            // Refused rather than repaired, exactly as in the scalar case. The entry is still
            // dropped below: the record should stop pointing at something unusable.
            Log::warning('[LISTING MEDIA] refused to delete a malformed stored collection entry', [
                'auction_id' => $auction->id ?? null,
                'meta_key'   => $metaKey,
                'directory'  => $directory,
            ]);
        }

        array_splice($entries, $position, 1);

        if (empty($entries)) {
            $auction->deleteMeta($metaKey);
        } else {
            $auction->saveMeta($metaKey, array_values($entries));
        }

        return true;
    }

    /**
     * Position of the entry a selector addresses, or false.
     *
     * Delegates membership to ListingPhotoEntry so there is exactly one definition of "which
     * entry did the user click" across delete, reorder and cover selection. An entry the model
     * cannot interpret is not a member — same fail-closed posture as everything else here.
     *
     * @param  list<mixed>  $entries
     * @return int|false
     */
    private function locateCollectionEntry(array $entries, string $selector)
    {
        foreach ($entries as $position => $raw) {
            $entry = \App\Support\Listing\ListingPhotoEntry::fromStored($raw);

            if ($entry !== null && $entry->matchesSelector($selector)) {
                return $position;
            }
        }

        return false;
    }

    /**
     * Is this stored entry MLS-sourced media rather than something we host?
     *
     * Read straight off the persisted value, not inferred from its shape: an entry is MLS media
     * because the record says `source: mls`, not because it happens to be an array.
     */
    private function isMlsSourcedCollectionEntry(mixed $stored): bool
    {
        $entry = \App\Support\Listing\ListingPhotoEntry::fromStored($stored);

        return $entry !== null && $entry->isMls();
    }

    /**
     * The filename behind a user upload stored in array form, if it is safe to delete.
     *
     * Runs the SAME allow-list as the scalar path — separators, traversal syntax and NUL bytes
     * are rejected identically. The array wrapper changes where the filename is read from; it
     * does not change what a filename is permitted to be. Returns null when there is no usable
     * local file, which callers treat as "refuse", never as "repair".
     */
    private function promotedUserFilename(mixed $stored): ?string
    {
        $entry = \App\Support\Listing\ListingPhotoEntry::fromStored($stored);

        if ($entry === null || ! $entry->hasLocalFile()) {
            return null;
        }

        return $this->isDeletableStoredMediaFilename($entry->filename) ? $entry->filename : null;
    }

    /**
     * The single place a media path is handed to storage.
     *
     * Both callers above have already established two things: the directory is a server-side
     * literal, and the filename came out of the owned record and passed
     * isDeletableStoredMediaFilename(). Neither the disk nor the directory is ever client-supplied
     * — this method takes a bool, not a disk name, precisely so no call site can be talked into
     * writing one in.
     */
    private function deleteOwnedMediaFile(string $directory, string $filename, bool $private): void
    {
        $writer = app(ListingStorageWriter::class);
        $path   = $directory . '/' . $filename;

        if ($private) {
            $writer->deletePrivate($path);

            return;
        }

        $writer->deletePublic($path);
    }
}
