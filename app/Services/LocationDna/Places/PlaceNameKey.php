<?php

namespace App\Services\LocationDna\Places;

/**
 * Phase 1d-3 — the one normalisation `location_places.name_key` is written with.
 *
 * IT MUST AGREE WITH THE HYDRATOR, EXACTLY
 * ----------------------------------------
 * {@see App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator} normalises a
 * stored label before comparing it to the corpus, and this layer has to answer the same question
 * about the same label. If the two forms drifted apart, a label that the cascade resolves would
 * fail to resolve here — or worse, the reverse — and the disagreement would look like missing
 * data rather than like a normalisation bug.
 *
 * The hydrator's own copy is deliberately NOT refactored to call this. It is on the live Hire
 * Buyer path, it is covered by its own characterisation tests, and rewriting working matching
 * code to share a helper is a change with real downside and no user-visible upside. Agreement is
 * pinned by test instead — see `PlaceNameKeyParityTest`, which asserts this class and the
 * hydrator produce identical output across the corpus's awkward names. If someone changes one,
 * that test fails rather than the geography quietly going wrong.
 *
 * WHAT IT DOES, AND THE ONE THING IT REFUSES TO DO
 * ------------------------------------------------
 * Lowercase, collapse internal whitespace, fold a LEADING `Saint` / `St.` / `St` to `st `. The
 * fold is prefix-only and whole-word: `Sainte Genevieve` and `Stevensville` are left alone, and
 * a mid-name `Saint` is not touched — `Port Saint Joe` does not become `Port St. Joe` here, the
 * same restraint the hydrator shows, because equating names in the middle is a guess rather than
 * a recognition.
 */
final class PlaceNameKey
{
    /** The comparison form of a place or county name. */
    public static function of(string $value): string
    {
        $keyed = trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($value))));

        return (string) preg_replace('/^(?:saint|st\.|st)\s+/', 'st ', $keyed);
    }

    /**
     * Remove a trailing `, ST` — the suffix stored labels carry and no corpus name does.
     *
     * Mirrors the hydrator's `stripStateSuffix()` so a caller can hand this layer a raw stored
     * label rather than having to know which of the two vocabularies it is holding.
     */
    public static function stripStateSuffix(string $label): string
    {
        return (string) preg_replace('/\s*,\s*[A-Za-z]{2}\s*$/', '', trim($label));
    }
}
