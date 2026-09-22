<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * One customer's explicit history for one seeker role, AND whether it is whole.
 *
 * `complete` is false when the reader's safety ceiling bound: more history
 * exists than was read. An incomplete history is never derived from, because a
 * partial read can cut one home's sequence in the middle — the Pass that
 * superseded a Save may be exactly the event that fell outside the window — and
 * a profile built from it would state a direction the customer's full history
 * does not support. So an incomplete history carries no records at all: there is
 * nothing for a later change to derive from by accident.
 */
final class TasteEvidence
{
    /**
     * @param list<TasteEventRecord> $records oldest first; empty when incomplete
     */
    private function __construct(
        public readonly array $records,
        public readonly bool $complete,
    ) {
    }

    /** @param list<TasteEventRecord> $records */
    public static function complete(array $records): self
    {
        return new self($records, true);
    }

    public static function truncated(): self
    {
        return new self([], false);
    }
}
