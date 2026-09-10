<?php

namespace App\Services\ListingImport\Sync;

/**
 * What one sync attempt did, in terms a caller can act on.
 *
 * The states are kept distinct rather than collapsed into a boolean because
 * they lead to different actions and, in two cases, to opposite ones.
 *
 * UNAVAILABLE and NOT_FOUND are the pair that matters most. "Bridge did not
 * answer" and "Bridge answered, and this listing is not in the feed" look
 * identical from a null return, and treating the first as the second is how a
 * transport blip becomes a listing marked gone. {@see \App\Services\Bridge\BridgeLookupResult}
 * already draws that line on the lookup side; this preserves it rather than
 * flattening it back out.
 *
 * Nothing here is ever shown to a visitor verbatim — `$detail` may carry a
 * provider failure code for the log, and provider diagnostics do not belong on
 * a page.
 */
final class MlsSyncOutcome
{
    public const SYNCED         = 'synced';          // source changed; listing updated
    public const UNCHANGED      = 'unchanged';       // source identical; nothing written
    public const FRESH          = 'fresh';           // inside the freshness window; not fetched
    public const NOT_MLS_LINKED = 'not_mls_linked';  // a manual listing; never touched
    public const NOT_FOUND      = 'not_found';       // feed reached, record absent
    public const UNAVAILABLE    = 'unavailable';     // feed not reached — NOT a deletion
    public const LOCKED         = 'locked';          // another sync holds this listing
    public const DISABLED       = 'disabled';        // master gate off
    public const UNSUPPORTED    = 'unsupported';     // role has no source record to sync

    private function __construct(
        public readonly string $status,
        public readonly array $changedKeys = [],
        public readonly ?string $sourceStatus = null,
        public readonly ?string $sourceModifiedAt = null,
        public readonly bool $statusUnrecognised = false,
        public readonly ?string $detail = null,
        public readonly int $mediaAdded = 0,
        public readonly int $mediaUpdated = 0,
        public readonly int $mediaRemoved = 0,
        public readonly int $userPhotosPreserved = 0,
    ) {}

    public static function synced(
        array $changedKeys,
        ?string $sourceStatus,
        ?string $sourceModifiedAt,
        bool $statusUnrecognised = false,
        int $mediaAdded = 0,
        int $mediaUpdated = 0,
        int $mediaRemoved = 0,
        int $userPhotosPreserved = 0,
    ): self {
        return new self(
            status:              self::SYNCED,
            changedKeys:         $changedKeys,
            sourceStatus:        $sourceStatus,
            sourceModifiedAt:    $sourceModifiedAt,
            statusUnrecognised:  $statusUnrecognised,
            mediaAdded:          $mediaAdded,
            mediaUpdated:        $mediaUpdated,
            mediaRemoved:        $mediaRemoved,
            userPhotosPreserved: $userPhotosPreserved,
        );
    }

    public static function unchanged(?string $sourceModifiedAt = null): self
    {
        return new self(self::UNCHANGED, sourceModifiedAt: $sourceModifiedAt);
    }

    public static function fresh(): self
    {
        return new self(self::FRESH);
    }

    public static function notMlsLinked(): self
    {
        return new self(self::NOT_MLS_LINKED);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function unavailable(?string $detail = null): self
    {
        return new self(self::UNAVAILABLE, detail: $detail);
    }

    public static function locked(): self
    {
        return new self(self::LOCKED);
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED);
    }

    public static function unsupported(): self
    {
        return new self(self::UNSUPPORTED);
    }

    public function isSynced(): bool
    {
        return $this->status === self::SYNCED;
    }

    /**
     * Did this attempt reach a definite answer about the source?
     *
     * False for UNAVAILABLE and LOCKED — the two states where the listing's
     * stored data is still the best information we have and must be left alone.
     */
    public function isConclusive(): bool
    {
        return ! in_array($this->status, [self::UNAVAILABLE, self::LOCKED], true);
    }

    public function wroteNothing(): bool
    {
        return $this->changedKeys === [] && $this->mediaAdded === 0
            && $this->mediaUpdated === 0 && $this->mediaRemoved === 0;
    }
}
