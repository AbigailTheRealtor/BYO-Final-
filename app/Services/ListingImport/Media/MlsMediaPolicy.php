<?php

namespace App\Services\ListingImport\Media;

use Illuminate\Support\Facades\Log;

/**
 * The single place that decides whether an MLS media object may be attached to
 * a BidYourOffer listing, and whether the feature is permitted to run at all.
 *
 * WHY THIS IS A CLASS AND NOT AN `if` IN THE IMPORTER
 * --------------------------------------------------
 * Four separate surfaces need the same answer: the extractor (should I parse?),
 * the gallery sync (may I write?), the Livewire flow (should I offer this?) and
 * the view layer (may I render this?). A rule duplicated across four call sites
 * is a rule that will eventually be four different rules, and the one that
 * drifts is the one that republishes licensed imagery.
 *
 * FAIL CLOSED, ALWAYS
 * -------------------
 * Every method here answers "no" for anything it does not positively recognise.
 * A media category it has never seen, a URL scheme it cannot vouch for, a
 * hosting mode that is not the one implemented — all rejected. The alternative
 * shape (reject a known-bad list, allow the rest) fails open the moment the feed
 * adds a column, and the failure mode is a licensing breach rather than a
 * missing photo.
 *
 * WHAT THIS CLASS DOES NOT DECIDE
 * -------------------------------
 * Ownership. Whether *this user* may attach media to *this listing* is an
 * authorization question answered by the caller's owner-scoped resolution, and
 * merging the two would hide which of them a given call site actually satisfies.
 * See the note on the same separation in DeletesOwnedListingMedia.
 */
class MlsMediaPolicy
{
    /** The only hosting mode implemented. See config/mls_media.php. */
    public const HOSTING_REFERENCE = 'reference';

    /**
     * Is MLS media import permitted to do anything at all, for this role?
     *
     * Three independent conditions, all required:
     *   · the master flag is on;
     *   · the data-license acknowledgement has been made;
     *   · the hosting mode is one this code actually implements;
     *   · the role is one the feature is built for.
     *
     * Callers must ask this before extracting, before writing and before
     * rendering — not once at the top of a flow. A flag can change between a
     * listing being imported and its page being viewed, and the answer that
     * matters at render time is the one that holds at render time.
     */
    public function enabledForRole(?string $role): bool
    {
        if (! config('mls_media.enabled', false)) {
            return false;
        }

        if (! config('mls_media.license_acknowledged', false)) {
            return false;
        }

        if (! $this->hostingModeSupported()) {
            return false;
        }

        return in_array($role, (array) config('mls_media.roles', []), true);
    }

    /**
     * Whether the configured hosting mode is the implemented one.
     *
     * Rejects rather than defaults. The identical mistake was made once already
     * in this codebase — CRITERIA_LDNA_GEOGRAPHY_SOURCE used to fall through to
     * `eloquent` on an unrecognised value, so a typo silently served legacy data
     * and looked exactly like success. Here the stakes are higher: falling
     * through to a permissive mode would mean copying licensed bytes because
     * somebody misspelled a config value.
     */
    public function hostingModeSupported(): bool
    {
        $mode = config('mls_media.hosting_mode', self::HOSTING_REFERENCE);

        if ($mode === self::HOSTING_REFERENCE) {
            return true;
        }

        Log::warning('[MLS MEDIA] refusing to run under an unimplemented hosting mode', [
            'configured' => is_scalar($mode) ? (string) $mode : gettype($mode),
            'supported'  => self::HOSTING_REFERENCE,
        ]);

        return false;
    }

    /**
     * May this media item be attached to a listing and rendered?
     *
     * Applied per item, after extraction, because a single gallery routinely
     * mixes photographs with documents, branded tours and entries the feed has
     * flagged as not publicly displayable.
     */
    public function allowsItem(MlsMediaItem $item): bool
    {
        // The feed's own answer wins whenever it gives one. If Stellar says an
        // object is not for public display, no allow-list of ours overrides it.
        if ($item->permittedForPublicDisplay === false) {
            return false;
        }

        if (! $this->allowsUrl($item->url)) {
            return false;
        }

        return $this->allowsCategory($item->category);
    }

    /**
     * Only absolute https URLs.
     *
     * http is rejected, not upgraded: an image referenced over plain http on an
     * https listing page is a mixed-content block in every current browser, so
     * "upgrading" it would produce a broken image that looks like our bug. Data
     * and javascript URIs are rejected because a feed value reaches an `src`
     * attribute and must never be able to carry anything but a fetch.
     */
    public function allowsUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $url = trim($url);

        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }

    /**
     * Category allow-list, or the uncategorised allowance.
     *
     * Normalised on both sides so "FloorPlan", "Floor Plan" and "floor plan"
     * are the same answer — feeds are not consistent about this and a
     * whitespace difference is not a licensing distinction.
     */
    public function allowsCategory(?string $category): bool
    {
        $category = $category !== null ? trim($category) : '';

        if ($category === '') {
            return (bool) config('mls_media.allow_uncategorised', true);
        }

        $needle  = $this->normaliseCategory($category);
        $allowed = (array) config('mls_media.allowed_categories', []);

        foreach ($allowed as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            if ($this->normaliseCategory($candidate) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * The per-listing image ceiling, floored at zero.
     *
     * A negative or non-numeric configured value becomes 0 — "attach nothing" —
     * rather than "attach everything", for the same fail-closed reason as the
     * rest of this class.
     */
    public function maxImages(): int
    {
        $max = (int) config('mls_media.max_images', 50);

        return $max > 0 ? $max : 0;
    }

    private function normaliseCategory(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
    }
}
