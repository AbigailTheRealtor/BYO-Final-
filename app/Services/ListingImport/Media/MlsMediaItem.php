<?php

namespace App\Services\ListingImport\Media;

/**
 * One permitted media object from an MLS feed, normalised and provider-agnostic.
 *
 * The counterpart to {@see \App\Services\Property\PropertyCandidate} for media:
 * the extractor is the only layer that knows the source's field names, and every
 * consumer downstream sees this shape regardless of which MLS it came from.
 *
 * IDENTITY IS `mediaKey`, AND THAT MATTERS MORE THAN IT LOOKS
 * ----------------------------------------------------------
 * Re-importing or refreshing a listing must not duplicate its gallery, and the
 * only thing that reliably says "this is the same photograph as before" is the
 * provider's own key. A URL cannot do that job: feeds re-issue URLs when they
 * move CDNs, re-encode, or rotate a signature, and matching on URL would make
 * every such change look like a brand-new photo. Ordering cannot do it either —
 * inserting one photo at the front shifts every subsequent position.
 *
 * An item with no usable key is therefore not constructed at all; the extractor
 * drops it. A gallery entry that cannot be recognised again is one that would
 * duplicate on the next refresh, forever.
 *
 * NO BYTES, EVER
 * --------------
 * `$url` is a reference to the provider's own copy. Nothing in this codebase
 * downloads it, and no field here holds image data or a local path. That is the
 * whole of the reference-only hosting mode — see config/mls_media.php.
 *
 * Immutable by construction.
 */
class MlsMediaItem
{
    public function __construct(
        /** Provider-unique media identifier (RESO `MediaKey`). Never empty. */
        public readonly string $mediaKey,

        /** The listing this media belongs to (RESO `ListingKey`), when the feed states it. */
        public readonly ?string $listingKey,

        /** Absolute https URL at the PROVIDER's host. Never rehosted. */
        public readonly string $url,

        /**
         * Zero-based display position, assigned by the extractor.
         *
         * Derived from the feed's own `Order` where present, otherwise from the
         * array position. Either way it is dense and starts at zero, so the
         * gallery has a total order even when the feed's values are sparse,
         * duplicated or one-based.
         */
        public readonly int $sequence,

        /** True when the feed explicitly marks this as the preferred/primary image. */
        public readonly bool $isPreferred = false,

        /** RESO `MediaCategory`, verbatim. Null when the feed omits it. */
        public readonly ?string $category = null,

        /** Short caption/description, when the feed supplies one and it is permitted. */
        public readonly ?string $caption = null,

        /** Feed-side last-modified stamp, used to detect a replaced image. */
        public readonly ?string $modificationTimestamp = null,

        /**
         * The feed's own public-display permission, when it states one.
         *
         * Three-valued on purpose. `false` is an explicit refusal and is
         * honoured absolutely; `null` means the feed said nothing, which is not
         * the same as consent and is why MlsMediaPolicy still applies its own
         * allow-lists on top.
         */
        public readonly ?bool $permittedForPublicDisplay = null,
    ) {}

    /**
     * The stable identity used by the gallery: `mls:<mediaKey>`.
     *
     * Namespaced because it shares a collection with user-uploaded entries,
     * which are identified by bare filename. A prefix means no MLS key can ever
     * collide with — or be mistaken for — a file on our own disk, which is the
     * distinction the delete path depends on to refuse the wrong operation.
     */
    public function entryKey(): string
    {
        return 'mls:' . $this->mediaKey;
    }

    /**
     * The shape persisted inside the listing's `property_photos` collection.
     *
     * Deliberately carries provenance (`source`, `provider`, `listing_key`,
     * `media_key`) alongside the display fields. That metadata is what keeps
     * MLS-origin media distinguishable from user uploads at every later point —
     * refresh, reorder, delete, render — rather than becoming indistinguishable
     * the moment it lands in the same array.
     *
     * `is_cover` is intentionally NOT set here. Exactly one entry in a gallery
     * may carry it, which is a property of the collection and not of any single
     * item; MlsListingGallerySync decides it once, with the whole set in view.
     */
    public function toGalleryEntry(string $provider = 'bridge'): array
    {
        $entry = [
            'source'      => 'mls',
            'provider'    => $provider,
            'media_key'   => $this->mediaKey,
            'url'         => $this->url,
            'sequence'    => $this->sequence,
        ];

        if ($this->listingKey !== null && $this->listingKey !== '') {
            $entry['listing_key'] = $this->listingKey;
        }

        if ($this->caption !== null && $this->caption !== '') {
            $entry['caption'] = $this->caption;
        }

        if ($this->modificationTimestamp !== null && $this->modificationTimestamp !== '') {
            $entry['modified_at'] = $this->modificationTimestamp;
        }

        return $entry;
    }
}
