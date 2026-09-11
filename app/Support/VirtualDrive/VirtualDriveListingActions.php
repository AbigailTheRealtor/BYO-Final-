<?php

namespace App\Support\VirtualDrive;

/**
 * The listing-card actions the Virtual Drive proof offers, and which ones are
 * real for a given listing.
 *
 * REUSE, NOT NEW WORKFLOWS
 * ------------------------
 * Every available action is a link into something that already exists — the
 * listing's own feed-permitted media, its BidYourOffer listing page (which hosts
 * the real Ask a Question and Schedule Showing forms), or the signed-in MLS
 * detail page. This class builds nothing new and invents no destination.
 *
 * AN UNAVAILABLE ACTION SAYS WHY
 * ------------------------------
 * The proof is a comparison, so a missing capability is a finding, not
 * something to hide: each unavailable action carries the reason. "Save" is
 * always unavailable because no Save / Favorite feature exists anywhere in this
 * application; "Video" because the Stellar feed carries no video field that is
 * licensed for display.
 *
 * Input is an ExploreListingProjection::toArray() — already an allow-list — so
 * nothing here can surface a field the projection chose not to publish.
 */
final class VirtualDriveListingActions
{
    /**
     * @param  array<string,mixed>  $listing
     * @return list<array{key:string,label:string,available:bool,url:?string,reason:?string}>
     */
    public static function for(array $listing): array
    {
        $canonical = self::url($listing['canonical_url'] ?? null);
        $details   = $canonical ?? self::url($listing['detail_url'] ?? null);
        $photos    = is_array($listing['photo_urls'] ?? null) ? $listing['photo_urls'] : [];

        return [
            self::action(
                'photos',
                'Photos',
                ($listing['has_photos'] ?? false) === true && $photos !== [],
                null,
                'No photographs the feed permits us to display.'
            ),
            self::action(
                'video',
                'Video',
                ($listing['has_video'] ?? false) === true,
                self::url($listing['video_url'] ?? null),
                'The Stellar feed carries no video field licensed for display.'
            ),
            self::action(
                'tour',
                '3D Tour',
                ($listing['has_virtual_tour'] ?? false) === true,
                self::url($listing['virtual_tour_url'] ?? null),
                'No unbranded virtual tour on this listing.'
            ),
            self::action(
                'details',
                'Details',
                $details !== null,
                $details,
                'No BidYourOffer listing page exists for this property, and the MLS detail page requires sign-in.'
            ),
            self::action(
                'ask',
                'Ask a Question',
                $canonical !== null,
                $canonical,
                'Ask a Question lives on a BidYourOffer listing page; this MLS property has none.'
            ),
            self::action(
                'save',
                'Save',
                false,
                null,
                'No Save / Favorite feature exists anywhere in this application.'
            ),
            self::action(
                'showing',
                'Schedule Showing',
                ($listing['showing_available'] ?? false) === true && $canonical !== null,
                $canonical,
                'Showings can only be requested against a BidYourOffer listing; this MLS property has none.'
            ),
        ];
    }

    /** @return array{key:string,label:string,available:bool,url:?string,reason:?string} */
    private static function action(string $key, string $label, bool $available, ?string $url, string $reason): array
    {
        return [
            'key'       => $key,
            'label'     => $label,
            'available' => $available,
            'url'       => $available ? $url : null,
            'reason'    => $available ? null : $reason,
        ];
    }

    private static function url(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
