<?php

namespace App\Support\Listing;

use App\Services\ListingImport\Media\MlsMediaPolicy;
use App\Support\Storage\ListingMediaUrl;

/**
 * The one place a stored `property_photos` collection becomes something a page
 * can render.
 *
 * THE GAP THIS CLOSES
 * -------------------
 * `property_photos` widened to hold MLS-sourced entries alongside bare upload
 * filenames, and the write side learned about that immediately — the gallery
 * sync, the reorder, the delete and the quick-import review all ask
 * {@see ListingPhotoEntry}. The public listing pages did not. Each of them
 * carried its own inline copy of the same idiom:
 *
 *     $fn = is_array($ph) ? ($ph['filename'] ?? '') : $ph;
 *     … ListingMediaUrl::get('auction/images/' . $fn)
 *
 * For an MLS entry `filename` is absent, so those pages skipped the photograph
 * silently. A listing could therefore show its imported gallery on the review
 * screen and then publish to a page with no photographs on it — the same data,
 * two answers, because the rule lived in five views instead of one class. The
 * shared partial was worse: it filtered with `trim((string) $p)`, which casts an
 * array to the literal string `"Array"` (emitting an array-to-string warning),
 * kept the entry as non-empty, and then concatenated it into a storage path —
 * producing a request for `auction/images/Array`.
 *
 * So the decision moves here, once, and views iterate {@see ListingPhotoView}
 * objects that are already resolved.
 *
 * THIS CLASS DOES NOT RELAX THE MEDIA POLICY — IT ENFORCES IT AGAIN
 * -----------------------------------------------------------------
 * Being able to render MLS media is not permission to render it. Every MLS entry
 * is re-checked against {@see MlsMediaPolicy::enabledForRole()} at render time,
 * which is the third independent check on the same question — extraction and
 * write being the first two. That is deliberate: a flag can change between the
 * import that attached a photograph and the page view that would display it, and
 * the answer that governs a render is the one that holds when the render happens.
 *
 * With the media flags at their defaults (both false, see config/mls_media.php)
 * this class emits **no MLS photograph at all**, for any role, on any page. The
 * capability added here is dormant in exactly the same sense as the rest of the
 * feature, and turning it on remains the two-flag decision it was.
 *
 * FAIL CLOSED, AS EVERYWHERE ELSE ON THIS PATH
 * --------------------------------------------
 * An entry that cannot be interpreted, a user entry with no filename, an MLS
 * entry whose URL the policy will not vouch for — each is DROPPED, not rendered
 * as a placeholder and not repaired. A photograph nobody can account for is one
 * nobody should see.
 */
final class ListingGalleryView
{
    /**
     * Where user uploads live. Named once so no caller re-types it, and so the
     * grep for "which code can build a local media path" has one honest answer.
     */
    public const UPLOAD_DIRECTORY = 'auction/images';

    /** @param  list<ListingPhotoView>  $photos */
    private function __construct(private readonly array $photos) {}

    /**
     * Resolve a stored collection for a given role.
     *
     * `$role` is what the media policy is asked about. A null or unrecognised
     * role yields no MLS photographs rather than an error: a surface that cannot
     * say which role it is displaying has not established that MLS media is
     * permitted there, and the safe reading of "I don't know" is "no".
     *
     * @param  mixed  $storedPhotos  the `property_photos` meta value, in any of
     *                               its stored forms: JSON string, array, null
     */
    public static function forRole(mixed $storedPhotos, ?string $role): self
    {
        $policy     = app(MlsMediaPolicy::class);
        $mlsAllowed = $policy->enabledForRole($role);

        $photos = [];

        foreach (ListingPhotoEntry::collection($storedPhotos) as $entry) {
            $view = self::resolve($entry, $policy, $mlsAllowed);

            if ($view !== null) {
                $photos[] = $view;
            }
        }

        return new self($photos);
    }

    /**
     * The role a listing model represents, or null when it is not one we know.
     *
     * Kept here rather than in each view so that "which role is this page?" and
     * "may this page show MLS media?" cannot be answered by two different rules.
     * Matched on the class, not on a `user_type` string, because the model IS the
     * role on these tables and a meta value is client-influenced in a way a class
     * name is not.
     */
    public static function roleForAuction(mixed $auction): ?string
    {
        if (! is_object($auction)) {
            return null;
        }

        return match (class_basename($auction)) {
            'SellerAgentAuction'   => 'seller',
            'LandlordAgentAuction' => 'landlord',
            'BuyerAgentAuction'    => 'buyer',
            'TenantAgentAuction'   => 'tenant',
            default                => null,
        };
    }

    /**
     * Resolve one entry, or null when it must not be shown.
     */
    private static function resolve(ListingPhotoEntry $entry, MlsMediaPolicy $policy, bool $mlsAllowed): ?ListingPhotoView
    {
        if ($entry->isMls()) {
            // The gate, restated at render time. See the class note.
            if (! $mlsAllowed) {
                return null;
            }

            // Re-validated rather than trusted because it was validated once at
            // import: this string is about to become an `src` attribute, and the
            // record it came from is older than this request. The policy admits
            // absolute https URLs only — never data:, never javascript:, never a
            // relative fragment that would resolve against our own origin.
            if (! $policy->allowsUrl($entry->url)) {
                return null;
            }

            return new ListingPhotoView(
                url:     (string) $entry->url,
                key:     $entry->key(),
                isMls:   true,
                isCover: $entry->isCover,
                caption: $entry->caption,
            );
        }

        // A user entry with no filename has no file behind it. Dropping it is the
        // same answer the delete path gives such an entry.
        if (! $entry->hasLocalFile()) {
            return null;
        }

        return new ListingPhotoView(
            url:     ListingMediaUrl::get(self::UPLOAD_DIRECTORY . '/' . $entry->filename),
            key:     $entry->key(),
            isMls:   false,
            isCover: $entry->isCover,
            caption: $entry->caption,
        );
    }

    /** @return list<ListingPhotoView> */
    public function photos(): array
    {
        return $this->photos;
    }

    /**
     * Just the URLs, in gallery order — for the hero carousel, which needs a
     * plain JSON array and nothing else.
     *
     * @return list<string>
     */
    public function urls(): array
    {
        return array_map(static fn (ListingPhotoView $p) => $p->url, $this->photos);
    }

    /**
     * Index of the photograph to open on, defaulting to the first.
     *
     * Zero when the gallery is empty as well as when nothing is flagged: callers
     * guard on emptiness before indexing, and returning null here would only move
     * that guard into every view.
     */
    public function coverIndex(): int
    {
        foreach ($this->photos as $index => $photo) {
            if ($photo->isCover) {
                return $index;
            }
        }

        return 0;
    }

    public function count(): int
    {
        return count($this->photos);
    }

    public function isEmpty(): bool
    {
        return $this->photos === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->photos !== [];
    }
}
