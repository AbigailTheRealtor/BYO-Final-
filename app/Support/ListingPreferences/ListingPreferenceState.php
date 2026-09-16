<?php

namespace App\Support\ListingPreferences;

/**
 * The three states a customer may put a listing in: Save, Maybe, Pass.
 *
 * CUSTOMER TERMINOLOGY IS SAVE / MAYBE / PASS, everywhere — config, docs,
 * storage and (later) the UI. Earlier planning notes said "Love/Maybe/Pass";
 * that wording is superseded and must not reappear. "Save" is what the control
 * does; the sentiment lives in the reasons the customer then chooses.
 *
 * A listing has AT MOST ONE state per customer per seeker role at any time —
 * enforced by the unique index on listing_preferences, not by application
 * discipline. Moving Save → Maybe → Pass REPLACES the current state; the
 * transition is recorded as a listing_preference_events row so history is kept
 * without ever stacking contradictory current states.
 *
 * Pass is a display decision and nothing else. It never deletes, hides or
 * alters listing or MLS data, and a passed listing stays recoverable.
 */
enum ListingPreferenceState: string
{
    case Save  = 'save';
    case Maybe = 'maybe';
    case Pass  = 'pass';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * The question asked after this state is chosen.
     *
     * Three different questions, which is why a reason declares the states it
     * may be offered for: "Too expensive" is not an answer to "What do you like
     * about this property?".
     */
    public function prompt(): string
    {
        return match ($this) {
            self::Save  => 'What do you like about this property?',
            self::Maybe => 'What are you unsure about?',
            self::Pass  => "Why isn't this one for you?",
        };
    }

    /**
     * The direction this state carries for future learning.
     *
     * Save is positive, Pass is negative, Maybe is weaker and uncertain — it is
     * deliberately NOT half of a Save. No learning code exists yet; this is the
     * vocabulary that code will use, recorded here so two subsystems cannot
     * later disagree about what Maybe meant.
     */
    public function isPositive(): bool
    {
        return $this === self::Save;
    }

    public function isNegative(): bool
    {
        return $this === self::Pass;
    }

    public function isUncertain(): bool
    {
        return $this === self::Maybe;
    }
}
