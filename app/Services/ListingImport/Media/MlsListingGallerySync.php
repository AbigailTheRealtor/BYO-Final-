<?php

namespace App\Services\ListingImport\Media;

use App\Support\Listing\ListingPhotoEntry;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles a listing's photo gallery against the MLS's current media set.
 *
 * This is the class that answers every "what happens when the MLS changes?"
 * question, so the answers are stated here rather than scattered across the
 * flow. It is a pure transformer over a collection — it performs NO
 * authorization and NO persistence. The caller hands it an owner-resolved
 * listing's stored entries and writes the result back. Merging ownership into a
 * reconciler would hide which call sites had actually satisfied it.
 *
 * THE RULES
 * ---------
 *
 * **Identity is the provider's media key.** Not the URL, not the position. A
 * feed that re-issues URLs on a CDN move, or re-encodes an image, or inserts one
 * photo at the front, changes neither. Matching on anything else makes routine
 * feed churn look like a wholly new gallery and duplicates it on every refresh.
 *
 * **User uploads are never touched.** Not reordered relative to each other, not
 * dropped, not converted into MLS media. A refresh that deleted the photographs
 * a seller took themselves would be destroying their work to satisfy a
 * background sync, and no amount of "the MLS is authoritative" justifies that.
 * They are counted in the result so the caller can prove it.
 *
 * **A photo the MLS withdrew is withdrawn here.** When the incoming set is
 * non-empty, MLS entries absent from it are removed. The source stopped
 * publishing that image; continuing to show it would be republishing content
 * that was pulled, which is exactly the licensing posture this feature is
 * supposed to respect.
 *
 * **An EMPTY incoming set removes nothing.** This is the one asymmetry, and it
 * is deliberate. "The feed returned no media" and "this listing genuinely has no
 * photographs" are indistinguishable at this layer, and they are also the two
 * outcomes of a transport failure and a real change. Treating the ambiguous case
 * as "delete the whole gallery" means one bad response empties a live listing.
 * Off-market, withdrawn and expired listings are handled by an explicit
 * {@see detachAll()} call from the caller that actually knows the status —
 * never inferred from an empty array.
 *
 * **MLS order is authoritative until the owner overrules it.** On a gallery the
 * owner has not rearranged, MLS photos sit in feed order, ahead of user uploads.
 * Once the owner reorders, `$orderCustomized` is true and their arrangement
 * survives every subsequent refresh; newly-arrived MLS photos are appended
 * rather than injected, so a sync can never silently reshuffle a gallery the
 * owner deliberately arranged.
 *
 * **The cover follows the feed's preferred image, then feed order.** An explicit
 * `PreferredPhotoYN` wins; otherwise the first MLS photo in sequence. An owner's
 * explicit choice outranks both and is preserved — see resolveCover().
 */
class MlsListingGallerySync
{
    /**
     * Merge an incoming MLS media set into an existing gallery.
     *
     * @param  mixed              $storedPhotos     the listing's persisted property_photos meta
     * @param  list<MlsMediaItem> $incoming         permitted, ordered media from the extractor
     * @param  bool               $orderCustomized  the owner has arranged this gallery by hand
     * @param  string             $provider         provenance stamp for new entries
     */
    public function sync(
        mixed $storedPhotos,
        array $incoming,
        bool $orderCustomized = false,
        string $provider = 'bridge',
    ): MlsGallerySyncResult {
        $existing = ListingPhotoEntry::collection($storedPhotos);

        $existingMls  = [];
        $existingUser = [];
        foreach ($existing as $entry) {
            if ($entry->isMls()) {
                $existingMls[(string) $entry->mediaKey] = $entry;
            } else {
                $existingUser[] = $entry;
            }
        }

        // The ambiguous case. Nothing is removed; the gallery is returned as it
        // stands so a bad response cannot empty a live listing. See class note.
        if ($incoming === []) {
            return new MlsGallerySyncResult(
                entries:             $existing,
                unchanged:           count($existingMls),
                userPhotosPreserved: count($existingUser),
                coverKey:            $this->resolveCover($existing)?->key(),
            );
        }

        $added = $updated = $unchanged = 0;

        /** @var list<ListingPhotoEntry> $incomingEntries */
        $incomingEntries = [];
        $incomingKeys    = [];

        foreach ($incoming as $item) {
            $incomingKeys[$item->mediaKey] = true;

            $entryArray = $item->toGalleryEntry($provider);
            $prior      = $existingMls[$item->mediaKey] ?? null;

            if ($prior === null) {
                $added++;
            } elseif ($this->differs($prior, $item)) {
                // A replaced or moved image: same identity, new content. Updated
                // in place rather than removed-and-re-added, so an owner's cover
                // choice and the gallery's shape survive the change.
                $updated++;
            } else {
                $unchanged++;
            }

            // The owner's cover choice is a property of the collection and is
            // reapplied wholesale by resolveCover() below, so it is not carried
            // on the individual entry here.
            $entry = ListingPhotoEntry::fromStored($entryArray);
            if ($entry !== null) {
                $incomingEntries[] = $entry;
            }
        }

        $removed = 0;
        foreach ($existingMls as $mediaKey => $entry) {
            if (! isset($incomingKeys[$mediaKey])) {
                $removed++;
            }
        }

        $entries = $orderCustomized
            ? $this->mergePreservingOwnerOrder($existing, $incomingEntries)
            : array_merge($incomingEntries, $existingUser);

        $entries = $this->applyCover($entries, $existing, $incoming);

        if ($removed > 0) {
            Log::info('[MLS MEDIA] dropped gallery entries the feed no longer carries', [
                'removed'  => $removed,
                'retained' => count($incomingEntries),
            ]);
        }

        return new MlsGallerySyncResult(
            entries:             $entries,
            added:               $added,
            updated:             $updated,
            removed:             $removed,
            unchanged:           $unchanged,
            userPhotosPreserved: count($existingUser),
            coverKey:            $this->resolveCover($entries)?->key(),
        );
    }

    /**
     * Remove every MLS-sourced entry, keeping user uploads intact.
     *
     * The explicit counterpart to the empty-set rule: this is what a caller
     * invokes when it KNOWS the listing went off-market, was withdrawn or
     * expired. Making it a separate method rather than a sync special-case means
     * a gallery can only be emptied by code that positively decided to empty it.
     *
     * The user's own photographs survive, because the listing itself has not
     * stopped being theirs.
     */
    public function detachAll(mixed $storedPhotos): MlsGallerySyncResult
    {
        $existing = ListingPhotoEntry::collection($storedPhotos);

        $userEntries = array_values(array_filter($existing, fn (ListingPhotoEntry $e) => $e->isUser()));
        $removed     = count($existing) - count($userEntries);

        // A cover flag that pointed at a detached MLS photo would leave the
        // gallery with no cover at all, so it is re-derived over what remains.
        $userEntries = $this->applyCover($userEntries, [], []);

        return new MlsGallerySyncResult(
            entries:             $userEntries,
            removed:             $removed,
            userPhotosPreserved: count($userEntries),
            coverKey:            $this->resolveCover($userEntries)?->key(),
        );
    }

    /**
     * Set the cover to an owner-chosen entry, or return null when the selector
     * addresses nothing in the collection.
     *
     * The selector is matched against the collection's own keys — an entry that
     * is not a member cannot become the cover, which is what stops a crafted
     * selector attaching a reference to media the listing does not hold.
     *
     * @param  list<ListingPhotoEntry>  $entries
     * @return list<ListingPhotoEntry>|null
     */
    public function chooseCover(array $entries, string $selector): ?array
    {
        $found = false;
        foreach ($entries as $entry) {
            if ($entry->matchesSelector($selector)) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return null;
        }

        $out = [];
        foreach ($entries as $entry) {
            $chosen = $entry->matchesSelector($selector);
            // Stamped as an OWNER decision, which is what makes it outrank the
            // feed's preferred photo on every subsequent refresh.
            $out[] = $entry->withCover($chosen, byOwner: $chosen);
        }

        return $out;
    }

    /**
     * Has this image materially changed since we last saw it?
     *
     * The modification stamp is checked first because it is the feed's own
     * statement that the object changed. URL and caption are checked as well:
     * a feed that moves a photograph without touching its stamp still needs the
     * stored reference updated, or the gallery renders a URL that no longer
     * resolves.
     */
    private function differs(ListingPhotoEntry $prior, MlsMediaItem $incoming): bool
    {
        if ($prior->modifiedAt !== null
            && $incoming->modificationTimestamp !== null
            && $prior->modifiedAt !== $incoming->modificationTimestamp
        ) {
            return true;
        }

        if ($prior->url !== $incoming->url) {
            return true;
        }

        if (($prior->caption ?? '') !== ($incoming->caption ?? '')) {
            return true;
        }

        return $prior->sequence !== $incoming->sequence;
    }

    /**
     * Refresh a gallery whose order the owner arranged by hand.
     *
     * Every surviving entry holds its existing position — that is the whole
     * point — and genuinely new MLS photos are appended at the end. Appending
     * rather than inserting is the conservative choice: an owner who put their
     * best shot first should not find a newly-syndicated photo ahead of it
     * because the feed happened to number it zero.
     *
     * @param  list<ListingPhotoEntry>  $existing
     * @param  list<ListingPhotoEntry>  $incoming
     * @return list<ListingPhotoEntry>
     */
    private function mergePreservingOwnerOrder(array $existing, array $incoming): array
    {
        $incomingByKey = [];
        foreach ($incoming as $entry) {
            $incomingByKey[$entry->key()] = $entry;
        }

        $out  = [];
        $seen = [];

        foreach ($existing as $entry) {
            if ($entry->isUser()) {
                $out[]  = $entry;
                continue;
            }

            $key = $entry->key();

            // Present in both: keep the OWNER's position, take the FEED's
            // content. Dropping the refreshed URL to preserve position would
            // preserve a reference that may no longer resolve.
            if (isset($incomingByKey[$key])) {
                $out[]      = $incomingByKey[$key];
                $seen[$key] = true;
            }
            // Absent from the feed: withdrawn, so not carried forward.
        }

        foreach ($incoming as $entry) {
            if (! isset($seen[$entry->key()])) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Put exactly one cover flag on the collection.
     *
     * Precedence, highest first:
     *   1. an entry the owner already marked — an explicit human decision, and
     *      the one thing a background refresh has no business overruling;
     *   2. the feed's `PreferredPhotoYN`;
     *   3. the first entry in display order.
     *
     * The flag is cleared from every other entry on the way through, so a
     * gallery can never end up with two covers after a merge — a state the view
     * blades resolve by picking whichever they meet first, i.e. arbitrarily.
     *
     * @param  list<ListingPhotoEntry>  $entries
     * @param  list<ListingPhotoEntry>  $previous
     * @param  list<MlsMediaItem>       $incoming
     * @return list<ListingPhotoEntry>
     */
    private function applyCover(array $entries, array $previous, array $incoming): array
    {
        if ($entries === []) {
            return [];
        }

        $coverKey = null;
        $byOwner  = false;

        // Only an OWNER's prior choice is honoured. A cover this class stamped
        // on a previous run is its own output, not a decision — treating it as
        // one would freeze the feed's first answer and mean a changed primary
        // photo could never take effect. See ListingPhotoEntry::$coverChosenByOwner.
        foreach ($previous as $entry) {
            if ($entry->isCover && $entry->coverChosenByOwner) {
                $coverKey = $entry->key();
                $byOwner  = true;
                break;
            }
        }

        // …and only if that entry still exists.
        if ($coverKey !== null && ! $this->containsKey($entries, $coverKey)) {
            $coverKey = null;
            $byOwner  = false;
        }

        if ($coverKey === null) {
            foreach ($incoming as $item) {
                if ($item->isPreferred) {
                    $coverKey = $item->entryKey();
                    break;
                }
            }
        }

        if ($coverKey !== null && ! $this->containsKey($entries, $coverKey)) {
            $coverKey = null;
        }

        $coverKey ??= $entries[0]->key();

        $out = [];
        foreach ($entries as $entry) {
            $isCover = $entry->key() === $coverKey;
            $out[]   = $entry->withCover($isCover, byOwner: $isCover && $byOwner);
        }

        return $out;
    }

    /** @param list<ListingPhotoEntry> $entries */
    private function containsKey(array $entries, string $key): bool
    {
        foreach ($entries as $entry) {
            if ($entry->key() === $key) {
                return true;
            }
        }

        return false;
    }

    /** @param list<ListingPhotoEntry> $entries */
    private function resolveCover(array $entries): ?ListingPhotoEntry
    {
        foreach ($entries as $entry) {
            if ($entry->isCover) {
                return $entry;
            }
        }

        return $entries[0] ?? null;
    }
}
