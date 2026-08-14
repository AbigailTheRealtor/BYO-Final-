<?php

namespace App\Support\Listing;

/**
 * How a USER-uploaded entry's stored value becomes a storage key.
 *
 * This exists because the codebase genuinely has two conventions, both live, and
 * both correct for the rows they were written for:
 *
 *   · Most Livewire upload paths store a BARE FILENAME and every reader prefixes
 *     `auction/images/` — the Seller and Landlord public listing pages do this.
 *   · Several older controllers store the COMPLETE RELATIVE PATH, already
 *     including the directory (see LandlordAgentAuctionController, which writes
 *     `'auction/images/' . $imageName` straight into the meta). The agent-facing
 *     listing view was written against those rows and passes the stored value to
 *     the resolver unmodified.
 *
 * Prefixing a value that already carries its directory yields
 * `auction/images/auction/images/x.jpg`; not prefixing a bare filename yields a
 * key at the storage root. Either mistake is a silently broken image, so the
 * choice cannot be a default — it has to be stated by the caller that knows
 * which rows it is reading.
 *
 * It is an enum rather than a boolean deliberately. `forRole($stored, $role, true)`
 * at a call site tells a reader nothing, and the whole reason this type exists is
 * that the divergence was invisible until it caused a bug.
 *
 * NOTE THIS GOVERNS USER UPLOADS ONLY. An MLS-sourced entry is referenced at the
 * provider's own URL and never has a storage key at all, under either convention.
 */
enum ListingPhotoPathConvention
{
    /** Stored value is a bare filename; the upload directory is prefixed. */
    case UploadDirectoryPrefixed;

    /** Stored value is already a complete relative key; it is used as-is. */
    case StoredValueIsRelativeKey;
}
