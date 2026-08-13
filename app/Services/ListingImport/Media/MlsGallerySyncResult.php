<?php

namespace App\Services\ListingImport\Media;

use App\Support\Listing\ListingPhotoEntry;

/**
 * What a gallery sync actually did, so the caller can say so rather than guess.
 *
 * The counts exist because "24 MLS photos imported" is a claim the confirmation
 * screen makes to the user, and a re-import that changed nothing must be able to
 * say that instead of repeating the original number as though work happened.
 * They also make the refresh contract testable: "a second import adds 0 and
 * duplicates 0" is an assertion, not a hope.
 *
 * Immutable.
 */
class MlsGallerySyncResult
{
    public function __construct(
        /** @var list<ListingPhotoEntry> the complete collection after the sync */
        public readonly array $entries,
        /** MLS photos present now that were not present before. */
        public readonly int $added = 0,
        /** MLS photos already present whose URL, caption or position changed. */
        public readonly int $updated = 0,
        /** MLS photos dropped because the feed no longer carries them. */
        public readonly int $removed = 0,
        /** MLS photos carried through untouched. */
        public readonly int $unchanged = 0,
        /** User uploads in the collection. Never altered by a sync. */
        public readonly int $userPhotosPreserved = 0,
        /** entryKey() of the cover, or null when the gallery is empty. */
        public readonly ?string $coverKey = null,
    ) {}

    public function totalMlsPhotos(): int
    {
        return $this->added + $this->updated + $this->unchanged;
    }

    public function changedAnything(): bool
    {
        return $this->added > 0 || $this->updated > 0 || $this->removed > 0;
    }
}
