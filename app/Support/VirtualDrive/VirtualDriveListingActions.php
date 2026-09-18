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
 * something to hide: each unavailable action carries the reason. "Video" is
 * unavailable because the Stellar feed carries no video field that is licensed
 * for display.
 *
 * "SAVE" USED TO BE ONE OF THOSE FINDINGS, AND IS NOT ANY MORE.
 * ------------------------------------------------------------
 * It reported that no Save / Favorite feature existed anywhere in this
 * application, which was true when the proof was written. Listing Preferences
 * Phase 2 and 3A built one, so the finding is obsolete and the action now
 * delegates to it.
 *
 * WHAT DELEGATION MEANS HERE. This class decides only whether the shared
 * control CAN be offered for this listing and this viewer, and says why when it
 * cannot. It stores nothing, reads no preference, resolves no subject and knows
 * no state — {@see VirtualDrivePreferenceControl}, the one seam, asks the shared
 * system the question and the shared Blade control renders the answer. The
 * action keeps the same {key,label,available,url,reason} shape every other
 * action has.
 *
 * THE TRUSTED ID COMES FROM THE CONTROLLER, NEVER THE PROJECTION. A preference
 * is stored against `bridge_properties.id`, and the projection deliberately
 * publishes the MLS ListingKey and an opaque property hash instead. The
 * controller still holds the BridgeProperty model when it builds these actions,
 * so it passes that id in — the projection's allow-list is not widened to make
 * this work.
 *
 * Input is an ExploreListingProjection::toArray() — already an allow-list — so
 * nothing here can surface a field the projection chose not to publish.
 */
final class VirtualDriveListingActions
{
    /**
     * @param  array<string,mixed>  $listing     an ExploreListingProjection::toArray()
     * @param  int|null             $bridgeRowId the trusted `bridge_properties.id`, from the
     *                                           controller's own model — never from the projection
     * @param  string|null          $saveReason  why Save cannot be offered, when it cannot;
     *                                           null means it can
     * @return list<array{key:string,label:string,available:bool,url:?string,reason:?string}>
     */
    public static function for(array $listing, ?int $bridgeRowId = null, ?string $saveReason = null): array
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
            /*
             | Save | Maybe | Pass. Available when a trusted listing id exists
             | AND the shared system has no reason to refuse; the shared control
             | itself is what the shopper actually interacts with, so this
             | action carries no URL.
             */
            self::action(
                'save',
                'Save',
                $bridgeRowId !== null && $bridgeRowId > 0 && $saveReason === null,
                null,
                $saveReason ?? 'This listing has no stable identity to save a preference against.'
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
