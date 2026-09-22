<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * Every explicit choice one customer made about one home, oldest first.
 *
 * A HOME, NOT A LISTING ROW: grouping is by `subject_key`, which already decided
 * that a Bridge row and the BidYourOffer listing imported from it are one
 * property. That is the only grouping there is — no address, parcel or
 * coordinate proximity, so two homes are never merged by being near each other.
 *
 * `refKey` is the listing the customer most recently acted on for this subject,
 * which is where its structured facts are read from.
 */
final class TasteSubjectHistory
{
    /**
     * @param list<TasteChoice> $choices oldest first, never empty
     */
    public function __construct(
        public readonly string $subjectKey,
        public readonly string $refKey,
        public readonly array $choices,
    ) {
    }

    /** The subject's most recent choice, in force or not. */
    public function latest(): TasteChoice
    {
        return $this->choices[count($this->choices) - 1];
    }
}
