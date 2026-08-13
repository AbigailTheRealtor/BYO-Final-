<?php

namespace App\Support\Listing;

/**
 * One entry in a listing's `property_photos` collection, whatever shape it is
 * stored in.
 *
 * THE PROBLEM THIS SOLVES
 * -----------------------
 * `property_photos` has always been a JSON array of bare filename strings, and
 * three separate mechanisms depend on that:
 *
 *   · DeletesOwnedListingMedia::deleteOwnedListingMediaFromCollection() matches
 *     the client's selector with a strict in-array search and then uses the
 *     matched STRING as a storage path;
 *   · applyPhotoOrder() decides membership with `in_array($fname, …, true)`;
 *   · isDeletableStoredMediaFilename() returns false for any non-string.
 *
 * MLS-sourced media cannot be a bare filename — it has no file on our disk, and
 * it must stay distinguishable from user uploads for the rest of its life. Drop
 * a structured entry into that array naively and delete refuses every MLS photo
 * while reorder silently discards them, because neither mechanism recognises the
 * value as a member of the collection it is standing in.
 *
 * So the shape widens in exactly one place — here — and every consumer asks this
 * class instead of inspecting the raw value.
 *
 * WHY USER ENTRIES STAY BARE STRINGS
 * ----------------------------------
 * {@see toStorage()} returns the original string for an ordinary user upload.
 * That is not a micro-optimisation: it means no migration, no backfill, and no
 * rewrite of any listing that never touches MLS import. Every row already in the
 * database round-trips byte-identically, so "did this change break existing
 * listings?" has a structural answer rather than a hopeful one.
 *
 * TWO IDENTITIES, ONE NAMESPACE
 * -----------------------------
 * A user photo is identified by its filename, an MLS photo by `mls:<media_key>`.
 * The prefix is what guarantees an MLS key can never be mistaken for a path on
 * our own disk — which is precisely the confusion the delete path must not make.
 * {@see matchesSelector()} accepts either form for user entries so that every
 * pre-existing caller, which sends a bare filename, keeps working untouched.
 */
final class ListingPhotoEntry
{
    public const SOURCE_USER = 'user';
    public const SOURCE_MLS  = 'mls';

    private function __construct(
        public readonly string $source,
        /** Disk-relative filename under auction/images. User entries only. */
        public readonly ?string $filename,
        /** Absolute provider URL. MLS entries only — never a local path. */
        public readonly ?string $url,
        public readonly ?string $mediaKey,
        public readonly ?string $provider,
        public readonly ?string $listingKey,
        public readonly ?string $caption,
        public readonly ?string $modifiedAt,
        public readonly int $sequence,
        public readonly bool $isCover,
        /**
         * Was this cover chosen by the listing owner, rather than derived?
         *
         * WHY THE DISTINCTION IS PERSISTED AND NOT INFERRED
         * -------------------------------------------------
         * A sync stamps `is_cover` on whichever image the feed prefers. On the
         * NEXT sync that stamp is indistinguishable from a human decision — so a
         * reconciler that honours "the existing cover" would treat its own
         * previous output as an owner's choice and freeze the feed's first
         * answer forever, never following a changed primary photo.
         *
         * Recording who decided is the only way for the precedence rule
         * ("an owner outranks the feed, the feed outranks position") to survive
         * more than one refresh.
         */
        public readonly bool $coverChosenByOwner,
        /** The value as persisted, so toStorage() can round-trip it exactly. */
        private readonly string|array $original,
    ) {}

    /**
     * Interpret a persisted entry, or null when it is unusable.
     *
     * Returning null rather than a placeholder is the same fail-closed posture
     * the media policy takes: an entry we cannot understand is one we must not
     * render, reorder, or hand to a filesystem call.
     */
    public static function fromStored(mixed $raw): ?self
    {
        if (is_string($raw)) {
            $filename = trim($raw);

            return $filename === '' ? null : new self(
                source:     self::SOURCE_USER,
                filename:   $filename,
                url:        null,
                mediaKey:   null,
                provider:   null,
                listingKey: null,
                caption:    null,
                modifiedAt: null,
                sequence:           0,
                isCover:            false,
                coverChosenByOwner: false,
                original:           $raw,
            );
        }

        if (! is_array($raw)) {
            return null;
        }

        $source = self::str($raw['source'] ?? null);

        return $source === self::SOURCE_MLS
            ? self::mlsFromArray($raw)
            : self::userFromArray($raw);
    }

    /**
     * Every usable entry in a stored collection, in stored order.
     *
     * @param  mixed  $stored  the meta value: a JSON string, an array, or nothing
     * @return list<self>
     */
    public static function collection(mixed $stored): array
    {
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            $stored  = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($stored)) {
            return [];
        }

        $entries = [];
        foreach ($stored as $raw) {
            $entry = self::fromStored($raw);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Persist a list of entries back to the collection shape.
     *
     * @param  list<self>  $entries
     * @return list<string|array>
     */
    public static function toStorageCollection(array $entries): array
    {
        return array_values(array_map(static fn (self $e) => $e->toStorage(), $entries));
    }

    private static function mlsFromArray(array $raw): ?self
    {
        $mediaKey = self::str($raw['media_key'] ?? null);
        $url      = self::str($raw['url'] ?? null);

        // Both are load-bearing: without a key the photo cannot be recognised on
        // the next refresh, without a URL there is nothing to render.
        if ($mediaKey === null || $url === null) {
            return null;
        }

        return new self(
            source:     self::SOURCE_MLS,
            filename:   null,
            url:        $url,
            mediaKey:   $mediaKey,
            provider:   self::str($raw['provider'] ?? null),
            listingKey: self::str($raw['listing_key'] ?? null),
            caption:    self::str($raw['caption'] ?? null),
            modifiedAt: self::str($raw['modified_at'] ?? null),
            sequence:   is_numeric($raw['sequence'] ?? null) ? (int) $raw['sequence'] : 0,
            isCover:    ! empty($raw['is_cover']),
            coverChosenByOwner: ! empty($raw['is_cover']) && ! empty($raw['cover_chosen_by_owner']),
            original:   $raw,
        );
    }

    /**
     * A user upload that has been promoted to array form.
     *
     * This shape exists for one reason: the owner chose it as the cover while
     * the gallery also contains MLS media, and `is_cover` has nowhere to live on
     * a bare string. The four public view blades already read
     * `['filename' => …, 'is_cover' => …]`, so the shape predates this class —
     * nothing was invented here, it was previously just never written.
     */
    private static function userFromArray(array $raw): ?self
    {
        $filename = self::str($raw['filename'] ?? null);

        if ($filename === null) {
            return null;
        }

        return new self(
            source:     self::SOURCE_USER,
            filename:   $filename,
            url:        null,
            mediaKey:   null,
            provider:   null,
            listingKey: null,
            caption:    self::str($raw['caption'] ?? null),
            modifiedAt: null,
            sequence:   is_numeric($raw['sequence'] ?? null) ? (int) $raw['sequence'] : 0,
            isCover:    ! empty($raw['is_cover']),
            coverChosenByOwner: ! empty($raw['is_cover']) && ! empty($raw['cover_chosen_by_owner']),
            original:   $raw,
        );
    }

    public function isMls(): bool
    {
        return $this->source === self::SOURCE_MLS;
    }

    public function isUser(): bool
    {
        return $this->source === self::SOURCE_USER;
    }

    /**
     * The stable identity of this entry within its collection.
     *
     * For a user upload this is the bare filename, unchanged, so that every
     * existing client — the drag-reorder payload, the delete selector — keeps
     * addressing photos exactly as it always has.
     */
    public function key(): string
    {
        return $this->isMls()
            ? 'mls:' . $this->mediaKey
            : (string) $this->filename;
    }

    /**
     * Does a client-supplied selector address this entry?
     *
     * A selector is an input, never an authority: it says WHICH entry the user
     * clicked, and this method's only job is to answer whether that entry is a
     * member of the owned collection. The value the caller then acts on must
     * come back out of the entry, never out of the selector.
     *
     * User entries accept both their bare filename and their `key()` — which are
     * the same string — so callers written before this class existed need no
     * change. MLS entries accept only the namespaced key, so a bare filename can
     * never resolve to one.
     */
    public function matchesSelector(mixed $selector): bool
    {
        if (! is_string($selector) || $selector === '') {
            return false;
        }

        return $selector === $this->key();
    }

    /**
     * Is there a file on OUR disk behind this entry?
     *
     * False for every MLS entry, always. Reference-only hosting means no bytes
     * were ever copied, so there is nothing to delete and any code that reached
     * for a filesystem path here would be acting on a path that does not exist —
     * or, worse, on a coincidentally-matching one.
     */
    public function hasLocalFile(): bool
    {
        return $this->isUser() && $this->filename !== null;
    }

    /**
     * Return this entry with its cover flag set or cleared.
     *
     * `$byOwner` records WHO decided — see the property docblock for why that
     * has to be persisted rather than inferred on the next pass.
     */
    public function withCover(bool $isCover, bool $byOwner = false): self
    {
        if ($isCover === $this->isCover && $byOwner === $this->coverChosenByOwner) {
            return $this;
        }

        $original = is_array($this->original) ? $this->original : ['filename' => $this->filename];

        if ($isCover) {
            $original['is_cover'] = true;
            if ($byOwner) {
                $original['cover_chosen_by_owner'] = true;
            } else {
                unset($original['cover_chosen_by_owner']);
            }
        } else {
            unset($original['is_cover'], $original['cover_chosen_by_owner']);
        }

        // A user entry that is no longer the cover, and carries nothing else,
        // collapses back to a bare string via toStorage(). Cover selection is
        // therefore fully reversible in the stored shape as well as in effect.
        if ($this->isUser() && ! isset($original['source'])) {
            $original['filename'] = $this->filename;
        }

        return self::fromStored($original) ?? $this;
    }

    /**
     * The value to persist.
     *
     * An ordinary user upload with no cover flag round-trips as the bare string
     * it arrived as — see the class note on why that matters.
     */
    public function toStorage(): string|array
    {
        if ($this->isUser() && ! $this->isCover && $this->caption === null) {
            return (string) $this->filename;
        }

        if (is_array($this->original)) {
            return $this->original;
        }

        $entry = ['source' => self::SOURCE_USER, 'filename' => $this->filename];
        if ($this->isCover) {
            $entry['is_cover'] = true;
        }

        return $entry;
    }

    private static function str(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
