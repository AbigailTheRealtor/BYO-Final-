<?php

namespace App\Support\ListingPreferences\Taste;

use App\Support\ListingPreferences\ListingPreferenceState;
use DateTimeImmutable;

/**
 * ONE explicit decision about one home: a run of events that put the subject in
 * the same state.
 *
 * A reason revision (Save → Save with different chips) is the SAME choice with
 * a corrected answer, so the later snapshot replaces the earlier one rather than
 * counting twice. A different state, or the same state after a clear, is a new
 * choice.
 *
 * `current` is true only for the subject's latest choice while it is still in
 * force. A choice that was later changed or cleared is SUPERSEDED: it keeps its
 * place in the evidence (clearing never erases history) but weighs less than a
 * choice the customer still holds.
 */
final class TasteChoice
{
    /**
     * @param list<string> $reasonKeys the final snapshot of this run
     */
    public function __construct(
        public readonly ListingPreferenceState $state,
        public readonly array $reasonKeys,
        public readonly DateTimeImmutable $firstAt,
        public readonly DateTimeImmutable $lastAt,
        public readonly bool $current,
    ) {
    }
}
