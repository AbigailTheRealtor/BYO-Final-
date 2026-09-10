<?php

namespace App\Services\Explore;

use App\Services\ListingImport\Media\MlsMediaExtractor;
use App\Services\ListingImport\Media\MlsMediaPolicy;

/**
 * What media a property actually has that we are actually permitted to show,
 * and which single action the panel should lead with.
 *
 * TWO QUESTIONS, NOT ONE
 * ----------------------
 * "The feed carries a tour URL" and "we may publish that tour" are different
 * statements, and this class only ever answers the second. Everything here goes
 * through the media authorities that already exist — {@see MlsMediaPolicy} for
 * the licence acknowledgement, the https/category rules and the feed's own
 * per-object PermittedForPublicDisplay flag, and {@see MlsFieldCatalog}'s
 * display classification for the tour URLs.
 *
 * ONLY THE UNBRANDED TOUR
 * -----------------------
 * `VirtualTourURLUnbranded` is classified for display. `VirtualTourURLBranded`,
 * `STELLAR_VirtualTourURLBranded2` and `VirtualTourURLZillow` are RESTRICTED —
 * they carry listing-brokerage or third-party-portal branding — so Explore
 * never offers them. 980 of 1,225 live records carry an unbranded tour, so this
 * is not a hypothetical distinction being drawn for its own sake.
 *
 * VIDEO IS CORRECTLY FALSE TODAY
 * ------------------------------
 * This feed has no video field; video arrives as a Media entry with a video
 * MediaCategory, and `mls_media.allowed_categories` does not list one. So
 * `hasVideo()` is false on every current record — not because video is
 * unimplemented, but because publishing it is not cleared. Adding a video
 * category to that config is the (licensing) decision that turns it on, and
 * the code needs no change. A capability that is false produces no button;
 * Explore shows no dead controls.
 *
 * GOOGLE PHOTOREALISTIC 3D IS NOT A TOUR
 * --------------------------------------
 * The 3D world is the exterior neighbourhood. It is never reported as a
 * property's interior virtual tour and never substitutes for one.
 */
class ExploreMediaCapability
{
    public function __construct(
        private readonly MlsMediaExtractor $extractor,
        private readonly MlsMediaPolicy $policy,
    ) {}

    /**
     * @param array<string,mixed> $raw   decoded Bridge record
     * @return array{
     *     has_photos:bool, photo_count:int, primary_thumbnail:?string,
     *     has_virtual_tour:bool, virtual_tour_url:?string,
     *     has_video:bool, video_url:?string, primary_action:?string
     * }
     */
    public function for(array $raw, ExploreTransactionType $type): array
    {
        $photos = $this->photos($raw, $type);
        $tour   = $this->virtualTourUrl($raw);
        $video  = $this->videoUrl($raw, $type);

        return [
            'has_photos'        => $photos !== [],
            'photo_count'       => count($photos),
            'primary_thumbnail' => $photos[0] ?? null,
            'has_virtual_tour'  => $tour !== null,
            'virtual_tour_url'  => $tour,
            'has_video'         => $video !== null,
            'video_url'         => $video,
            'primary_action'    => $this->primaryAction($tour !== null, $video !== null, $photos !== []),
        ];
    }

    /**
     * Tour, then Video, then Photos — the required priority.
     *
     * Null when a property has no publishable media at all, which the panel
     * renders as no media control rather than as an empty gallery.
     */
    public function primaryAction(bool $hasTour, bool $hasVideo, bool $hasPhotos): ?string
    {
        if ($hasTour) {
            return 'tour';
        }

        if ($hasVideo) {
            return 'video';
        }

        return $hasPhotos ? 'photos' : null;
    }

    /**
     * Publishable photo URLs, in display order.
     *
     * The extractor already applies the policy per item; the role gate is asked
     * here because it is asked at RENDER time everywhere else in this codebase,
     * and a flag that changed since import must take effect now rather than at
     * the next import.
     *
     * @param array<string,mixed> $raw
     * @return list<string>
     */
    public function photos(array $raw, ExploreTransactionType $type): array
    {
        if (! $this->policy->enabledForRole($type->internalRole())) {
            return [];
        }

        $urls = [];

        foreach ($this->extractor->fromRecord($raw) as $item) {
            $urls[] = $item->url;

            if (count($urls) >= max(1, $this->policy->maxImages())) {
                break;
            }
        }

        return $urls;
    }

    /**
     * The unbranded virtual tour URL, or null.
     *
     * `allowsUrl()` rather than a bespoke check: the same https-only,
     * absolute-host rule that governs an image `src` governs a link the
     * consumer is invited to follow out of the site.
     *
     * @param array<string,mixed> $raw
     */
    public function virtualTourUrl(array $raw): ?string
    {
        foreach (['VirtualTourURLUnbranded', 'STELLAR_VirtualTourURLUnbranded2'] as $field) {
            $value = $raw[$field] ?? null;

            if (is_string($value) && $this->policy->allowsUrl($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * A publishable video, or null.
     *
     * Reached only through the same category allow-list as photographs, so a
     * video is publishable exactly when the licence config says its category
     * is. Today no video category is listed and this always returns null.
     *
     * @param array<string,mixed> $raw
     */
    public function videoUrl(array $raw, ExploreTransactionType $type): ?string
    {
        if (! $this->policy->enabledForRole($type->internalRole())) {
            return null;
        }

        foreach ($this->extractor->fromRecord($raw) as $item) {
            if ($this->isVideoCategory($item->category)) {
                return $item->url;
            }
        }

        return null;
    }

    private function isVideoCategory(?string $category): bool
    {
        $normalised = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $category) ?? '');

        return $normalised !== '' && in_array($normalised, ['video', 'listingvideo', 'propertyvideo'], true);
    }
}
