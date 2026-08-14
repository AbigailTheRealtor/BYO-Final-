<?php

namespace App\Support\Listing;

/**
 * One photograph, resolved to the exact URL a page should render.
 *
 * WHY THIS IS NOT {@see ListingPhotoEntry}
 * ----------------------------------------
 * `ListingPhotoEntry` is the STORED shape: it knows a user upload has a filename
 * and an MLS photo has a provider URL, and it deliberately knows nothing about
 * disks, URL builders or the media licence. That separation is what lets the
 * delete and reorder paths reason about identity without also having to reason
 * about rendering.
 *
 * This is the DISPLAYABLE shape, and by the time one exists three questions have
 * already been answered: may this be shown at all, where does its URL come from,
 * and is it the cover. A view holding one of these has no decision left to make
 * and therefore no opportunity to make a different one than the view next door —
 * which is precisely how the seller page and the landlord page came to disagree
 * about photographs in the first place.
 *
 * There is no `filename` property on purpose. A view that could reach a filename
 * could concatenate it onto a directory, and the whole point of routing MLS media
 * through here is that no caller is able to build a local path for something we
 * do not host.
 */
final class ListingPhotoView
{
    public function __construct(
        /** Ready to place in a `src` attribute. Never a path fragment. */
        public readonly string $url,
        /** The entry's stable identity — {@see ListingPhotoEntry::key()}. */
        public readonly string $key,
        public readonly bool $isMls,
        public readonly bool $isCover,
        public readonly ?string $caption,
    ) {}

    public function isUser(): bool
    {
        return ! $this->isMls;
    }
}
