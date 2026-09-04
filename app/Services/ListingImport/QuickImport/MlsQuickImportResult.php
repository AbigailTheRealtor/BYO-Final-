<?php

namespace App\Services\ListingImport\QuickImport;

use App\Services\ListingImport\Media\MlsMediaItem;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;

/**
 * The outcome of an MLS quick-import lookup, ready for the confirmation screen.
 *
 * Deliberately carries only what the user is about to be shown and what the
 * writer is about to persist — a headline, the facts, the permitted media and
 * the display-only attributes. It does NOT carry the raw record. Everything
 * that reached this object has already passed an allow-list, so a consumer
 * cannot accidentally reach past the boundary by holding one of these.
 *
 * The failure states are distinct on purpose: "we could not find that number"
 * and "we could not reach the MLS" send the user to two different actions, and
 * telling them the first when the second is true sends someone off to re-check
 * a number that was correct. That distinction already exists in
 * {@see \App\Services\Bridge\BridgeLookupResult}; this preserves it rather than
 * collapsing it back into a single null.
 */
class MlsQuickImportResult
{
    public const STATUS_FOUND       = 'found';
    public const STATUS_NOT_FOUND   = 'not_found';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_NO_FACTS    = 'no_facts';
    public const STATUS_DISABLED    = 'disabled';
    public const STATUS_INVALID     = 'invalid';

    /**
     * @param  array<string,string>   $facts          canonical-key facts from the facts-only boundary
     * @param  list<MlsMediaItem>     $media          permitted, ordered media
     * @param  array<string,mixed>    $headline       address/price/beds/baths/sqft for the confirmation card
     * @param  MlsSupplementalDetails|null $details  the supplemental MLS payload:
     *         property facts with no editable form field, listing agent and
     *         brokerage attribution, and the MLS's own listing context — each
     *         already through its own display allow-list and already stripped of
     *         empty values, so nothing downstream has to re-decide either.
     */
    private function __construct(
        public readonly string $status,
        public readonly array $facts = [],
        public readonly array $media = [],
        public readonly array $headline = [],
        public readonly ?MlsSupplementalDetails $details = null,
        public readonly ?string $listingKey = null,
        public readonly ?string $mlsNumber = null,
        public readonly ?string $mlsStatus = null,
    ) {}

    public static function found(
        array $facts,
        array $media,
        array $headline,
        ?MlsSupplementalDetails $details,
        ?string $listingKey,
        ?string $mlsNumber,
        ?string $mlsStatus,
    ): self {
        return new self(
            status:     self::STATUS_FOUND,
            facts:      $facts,
            media:      $media,
            headline:   $headline,
            details:    $details,
            listingKey: $listingKey,
            mlsNumber:  $mlsNumber,
            mlsStatus:  $mlsStatus,
        );
    }

    public static function notFound(): self
    {
        return new self(self::STATUS_NOT_FOUND);
    }

    public static function unavailable(): self
    {
        return new self(self::STATUS_UNAVAILABLE);
    }

    public static function noFacts(): self
    {
        return new self(self::STATUS_NO_FACTS);
    }

    public static function disabled(): self
    {
        return new self(self::STATUS_DISABLED);
    }

    public static function invalid(): self
    {
        return new self(self::STATUS_INVALID);
    }

    public function isFound(): bool
    {
        return $this->status === self::STATUS_FOUND;
    }

    public function photoCount(): int
    {
        return count($this->media);
    }

    /**
     * The user-facing sentence for a lookup that did not produce a listing.
     *
     * Nothing provider-derived is interpolated — no status code, no response
     * body, no configuration value. Diagnostic detail is logged instead; this
     * string reaches a screen.
     */
    public function message(): string
    {
        return match ($this->status) {
            self::STATUS_UNAVAILABLE => "We couldn't connect to the MLS data service right now. Please try again in a few minutes.",
            self::STATUS_NOT_FOUND   => "We couldn't find a listing matching that MLS #. Please check the number and try again.",
            self::STATUS_NO_FACTS    => 'That MLS listing was found but contains no property details we can import.',
            self::STATUS_DISABLED    => 'MLS import is not available.',
            self::STATUS_INVALID     => 'Please enter an MLS # to continue.',
            default                  => '',
        };
    }
}
