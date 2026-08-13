<?php

namespace App\Services\ListingImport\Media;

use Illuminate\Support\Facades\Log;

/**
 * Reads the permitted photographs out of a raw Bridge/RESO property record.
 *
 * WHERE THE MEDIA ACTUALLY IS
 * ---------------------------
 * On the Property resource itself, not behind a second request. The live field
 * audit (docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md, 25-record sample) records
 * `Media` as an array populated in 25/25 records, alongside `PhotosCount`,
 * `PhotosChangeTimestamp` and `STELLAR_TotalPhotosCount`. Since
 * BridgeApiService issues no `$select`, that array is already inside every
 * `bridge_properties.raw_json` this application has ever cached — this class
 * adds no API call and no new provider surface, it reads what we already hold.
 *
 * WHY EVERY FIELD IS OPTIONAL HERE
 * --------------------------------
 * The Media object's exact shape could not be verified against a live feed when
 * this was written: no Bridge credentials in the environment, no Media fixture,
 * and the only in-repo sample truncated at
 * `[{"MediaKey":"…","MediaCat…`. So the mapping is written against the RESO Web
 * API Media resource and treats every single field as absent-by-default,
 * accepting the common casing variants each one appears under.
 *
 * The consequence is deliberate: an unrecognised or malformed entry is SKIPPED,
 * never guessed at and never partially constructed. A gallery that quietly
 * omits a photograph is a cosmetic defect; a gallery that renders a broken
 * `src`, a document, or somebody else's listing is not.
 *
 * ORDERING IS RECONSTRUCTED, NOT TRUSTED
 * --------------------------------------
 * See assignSequence(). The feed's `Order` is used when it is present and
 * usable, but the output is always a dense zero-based sequence, because the
 * brief's requirement is that MLS ordering is preserved — and "preserved"
 * has to mean something even when the feed's own values are sparse, duplicated,
 * one-based, or missing on half the entries.
 */
class MlsMediaExtractor
{
    /** RESO `MediaKey`, and the variants seen in the wild. */
    private const KEY_FIELDS = ['MediaKey', 'MediaObjectID', 'MediaObjectId', 'Key'];

    /** The URL. `MediaURL` is the RESO name; the rest are common aliases. */
    private const URL_FIELDS = ['MediaURL', 'MediaUrl', 'Url', 'URL', 'MediaUri', 'Uri'];

    /** Display position. */
    private const ORDER_FIELDS = ['Order', 'MediaOrder', 'Sequence', 'DisplayOrder'];

    /** Primary/preferred image marker. */
    private const PREFERRED_FIELDS = ['PreferredPhotoYN', 'PreferredPhoto', 'IsPrimary', 'PrimaryYN'];

    /** RESO `MediaCategory`. */
    private const CATEGORY_FIELDS = ['MediaCategory', 'Category', 'MediaType'];

    /**
     * Caption text.
     *
     * `ShortDescription` first, then `LongDescription`. Neither is
     * PublicRemarks: these are per-image captions ("Kitchen", "Rear elevation"),
     * not the listing's marketing narrative, which stays excluded.
     */
    private const CAPTION_FIELDS = ['ShortDescription', 'Caption', 'LongDescription', 'Description'];

    /** Feed-side modification stamp. */
    private const MODIFIED_FIELDS = ['MediaModificationTimestamp', 'ModificationTimestamp', 'ChangedDate'];

    /** The feed's own public-display permission. */
    private const PUBLIC_DISPLAY_FIELDS = ['PermittedForPublicDisplay', 'PublicDisplayYN', 'InternalOnlyYN'];

    /** Fields whose truth is INVERTED relative to "may be displayed publicly". */
    private const INVERTED_PUBLIC_DISPLAY_FIELDS = ['InternalOnlyYN'];

    public function __construct(
        private readonly MlsMediaPolicy $policy,
    ) {}

    /**
     * Permitted photographs from a raw property record, in display order.
     *
     * @param  array<string,mixed>  $record  a decoded Bridge/RESO property record
     * @return list<MlsMediaItem>            ordered, deduplicated, policy-filtered
     */
    public function fromRecord(array $record): array
    {
        $media = $record['Media'] ?? null;

        if (! is_array($media) || $media === []) {
            return [];
        }

        $listingKey = $this->stringOrNull($record['ListingKey'] ?? null);

        $candidates = [];
        $position   = 0;
        $skipped    = 0;

        foreach ($media as $raw) {
            if (! is_array($raw)) {
                $skipped++;
                continue;
            }

            $item = $this->buildItem($raw, $listingKey, $position);

            if ($item === null) {
                $skipped++;
                continue;
            }

            if (! $this->policy->allowsItem($item)) {
                $skipped++;
                continue;
            }

            $candidates[] = $item;
            $position++;
        }

        if ($skipped > 0) {
            // Logged rather than silent: a listing showing 18 of its 24 photos
            // should be diagnosable without re-deriving the feed by hand.
            Log::info('[MLS MEDIA] skipped media entries during extraction', [
                'listing_key' => $listingKey,
                'skipped'     => $skipped,
                'kept'        => count($candidates),
            ]);
        }

        $ordered = $this->assignSequence($this->deduplicate($candidates));

        return $this->applyCap($ordered, $listingKey);
    }

    /**
     * One raw media object, or null when it cannot be used.
     *
     * Two fields are load-bearing and their absence is fatal to the entry:
     * a key (without which the photo can never be recognised again on refresh)
     * and a URL (without which there is nothing to show).
     */
    private function buildItem(array $raw, ?string $recordListingKey, int $position): ?MlsMediaItem
    {
        $mediaKey = $this->firstString($raw, self::KEY_FIELDS);
        if ($mediaKey === null) {
            return null;
        }

        $url = $this->firstString($raw, self::URL_FIELDS);
        if ($url === null) {
            return null;
        }

        // The media object may name its own listing. Prefer it — a feed that
        // states the relationship is more authoritative than our assumption —
        // but fall back to the record's key when it is silent.
        $listingKey = $this->firstString($raw, ['ResourceRecordKey', 'ListingKey', 'ResourceRecordID'])
            ?? $recordListingKey;

        return new MlsMediaItem(
            mediaKey:                  $mediaKey,
            listingKey:                $listingKey,
            url:                       $url,
            // Provisional: reassigned densely by assignSequence() once the whole
            // set is known. The feed's own Order is read there, not here.
            sequence:                  $this->firstInt($raw, self::ORDER_FIELDS) ?? $position,
            isPreferred:               $this->firstBool($raw, self::PREFERRED_FIELDS) === true,
            category:                  $this->firstString($raw, self::CATEGORY_FIELDS),
            caption:                   $this->firstString($raw, self::CAPTION_FIELDS),
            modificationTimestamp:     $this->firstString($raw, self::MODIFIED_FIELDS),
            permittedForPublicDisplay: $this->publicDisplayFlag($raw),
        );
    }

    /**
     * The feed's own public-display permission, normalised so that true always
     * means "may be shown".
     *
     * `InternalOnlyYN` states the opposite of the others, so it is inverted
     * rather than read as-is. Reading it naively would turn "this image is
     * internal only" into "this image is cleared for public display", which is
     * the single worst way for this method to be wrong.
     */
    private function publicDisplayFlag(array $raw): ?bool
    {
        foreach (self::PUBLIC_DISPLAY_FIELDS as $field) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }

            $value = $this->toBool($raw[$field]);
            if ($value === null) {
                continue;
            }

            return in_array($field, self::INVERTED_PUBLIC_DISPLAY_FIELDS, true)
                ? ! $value
                : $value;
        }

        return null;
    }

    /**
     * Collapse repeated media keys, keeping the first occurrence.
     *
     * Feeds do occasionally repeat an object across pages or size variants. The
     * first occurrence wins because the feed's own leading order is the closest
     * thing to an authoritative statement of which representation it considers
     * primary.
     *
     * @param  list<MlsMediaItem>  $items
     * @return list<MlsMediaItem>
     */
    private function deduplicate(array $items): array
    {
        $seen = [];
        $out  = [];

        foreach ($items as $item) {
            if (isset($seen[$item->mediaKey])) {
                continue;
            }
            $seen[$item->mediaKey] = true;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Sort by the feed's order, then renumber densely from zero.
     *
     * A stable sort is essential, not incidental: when several entries share an
     * `Order` value — or none of them has one — the tie must resolve to the
     * order the feed listed them in. PHP's sort is stable as of 8.0, and this
     * codebase runs 8.2.
     *
     * The renumbering is what makes "MLS photo ordering is preserved" a property
     * the rest of the system can rely on: after this, position N in the array is
     * position N in the gallery, with no gaps to interpret and no dependence on
     * whether the feed counted from 0 or 1.
     *
     * @param  list<MlsMediaItem>  $items
     * @return list<MlsMediaItem>
     */
    private function assignSequence(array $items): array
    {
        usort($items, fn (MlsMediaItem $a, MlsMediaItem $b) => $a->sequence <=> $b->sequence);

        $out = [];
        foreach ($items as $index => $item) {
            $out[] = new MlsMediaItem(
                mediaKey:                  $item->mediaKey,
                listingKey:                $item->listingKey,
                url:                       $item->url,
                sequence:                  $index,
                isPreferred:               $item->isPreferred,
                category:                  $item->category,
                caption:                   $item->caption,
                modificationTimestamp:     $item->modificationTimestamp,
                permittedForPublicDisplay: $item->permittedForPublicDisplay,
            );
        }

        return $out;
    }

    /**
     * Trim to the configured ceiling, keeping the leading images.
     *
     * The cap is applied AFTER ordering so it drops the tail of the gallery
     * rather than an arbitrary subset — and never the preferred image, which
     * sorting has already placed among the leaders in every realistic feed. A
     * truncation is logged, because a silently shortened gallery reads to the
     * user as "the MLS only had 50 photos".
     *
     * @param  list<MlsMediaItem>  $items
     * @return list<MlsMediaItem>
     */
    private function applyCap(array $items, ?string $listingKey): array
    {
        $max = $this->policy->maxImages();

        if (count($items) <= $max) {
            return $items;
        }

        Log::info('[MLS MEDIA] gallery truncated to the configured ceiling', [
            'listing_key' => $listingKey,
            'available'   => count($items),
            'kept'        => $max,
        ]);

        return array_slice($items, 0, $max);
    }

    // ─── Field readers ───────────────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $raw
     * @param  list<string>         $fields
     */
    private function firstString(array $raw, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = $this->stringOrNull($raw[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $raw
     * @param  list<string>         $fields
     */
    private function firstInt(array $raw, array $fields): ?int
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }
            $value = $raw[$field];
            if (is_int($value)) {
                return $value;
            }
            if (is_string($value) && is_numeric(trim($value))) {
                return (int) trim($value);
            }
            if (is_float($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $raw
     * @param  list<string>         $fields
     */
    private function firstBool(array $raw, array $fields): ?bool
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }
            $value = $this->toBool($raw[$field]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Feed booleans arrive as true, "true", "Y", "Yes", 1 and "1" depending on
     * the column. FILTER_NULL_ON_FAILURE keeps an unrecognised value as null so
     * an unparseable flag is never read as `false` — which, for
     * `PermittedForPublicDisplay`, is a meaningfully different claim.
     */
    private function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return match (strtolower($trimmed)) {
            'y', 'yes' => true,
            'n', 'no'  => false,
            default    => filter_var($trimmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
