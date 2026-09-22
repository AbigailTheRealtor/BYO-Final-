<?php

namespace App\Support\ListingPreferences\Taste;

use DateTimeImmutable;

/**
 * One row of `listing_preference_events`, reduced to what learning may read.
 *
 * The reader builds these; the pure classes consume them, so the whole
 * derivation runs — and is unit tested — without a database. Nothing here
 * carries the user id: a record set is ONE customer's history by construction
 * (the reader's query is keyed on user and role), and a record that could name
 * somebody else is a record that could be mixed with somebody else's.
 *
 * `reasonKeys` is the immutable snapshot the writer stored — catalog KEYS, never
 * text. There is no free-text field to parse, and none is added here.
 */
final class TasteEventRecord
{
    /**
     * @param list<string> $reasonKeys
     */
    public function __construct(
        public readonly int $id,
        public readonly string $subjectKey,
        public readonly string $listingType,
        public readonly int $listingId,
        public readonly ?string $toState,
        public readonly array $reasonKeys,
        public readonly DateTimeImmutable $at,
    ) {
    }

    /** The acted-on listing, as the "<type>:<id>" key the facts reader returns. */
    public function refKey(): string
    {
        return $this->listingType . ':' . $this->listingId;
    }
}
