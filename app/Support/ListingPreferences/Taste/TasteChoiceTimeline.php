<?php

namespace App\Support\ListingPreferences\Taste;

use App\Support\ListingPreferences\ListingPreferenceState;

/**
 * Turns one customer's raw event history into explicit CHOICES, per home.
 *
 * Pure and container-free.
 *
 * THE RULES, and why each one exists:
 *
 *   1. Events are read in (created_at, id) order — the order they happened,
 *      with the id breaking ties so two events in one second cannot swap.
 *
 *   2. A reason revision is not a new choice. Save → Save with different chips
 *      is the customer correcting their answer, so the later snapshot REPLACES
 *      the earlier one. Counting both would let somebody who fiddles with chips
 *      outvote somebody who answered once.
 *
 *   3. A different state is a new choice. Save → Pass is two decisions, and the
 *      Pass is the one in force.
 *
 *   4. A clear closes the open choice without deleting it. The choice it closes
 *      stays in the evidence, SUPERSEDED — undo never rewrites history, and it
 *      is not read as a Pass either ("I withdrew my opinion" is not "I passed").
 *      A later choice of the same state after a clear is a new choice.
 *
 *   5. Only the subject's latest choice, and only while it is still in force,
 *      is CURRENT. Everything before it is superseded: kept, and outweighed.
 *
 * Nothing here looks at the clock. Whether a choice is current depends on what
 * the customer did afterwards, never on how long ago it was — so the same
 * history produces the same timeline whenever it is rebuilt.
 */
final class TasteChoiceTimeline
{
    /**
     * @param  iterable<TasteEventRecord> $events one customer, one seeker role
     * @return list<TasteSubjectHistory>  ordered by subject key
     */
    public static function build(iterable $events): array
    {
        $sorted = [];

        foreach ($events as $event) {
            if ($event instanceof TasteEventRecord) {
                $sorted[] = $event;
            }
        }

        usort($sorted, static function (TasteEventRecord $a, TasteEventRecord $b): int {
            return [$a->at->getTimestamp(), $a->id] <=> [$b->at->getTimestamp(), $b->id];
        });

        /** @var array<string, list<TasteEventRecord>> $bySubject */
        $bySubject = [];

        foreach ($sorted as $event) {
            $bySubject[$event->subjectKey][] = $event;
        }

        ksort($bySubject, SORT_STRING);

        $histories = [];

        foreach ($bySubject as $subjectKey => $subjectEvents) {
            $history = self::subject((string) $subjectKey, $subjectEvents);

            if ($history !== null) {
                $histories[] = $history;
            }
        }

        return $histories;
    }

    /**
     * @param list<TasteEventRecord> $events oldest first
     */
    private static function subject(string $subjectKey, array $events): ?TasteSubjectHistory
    {
        /** @var list<array{state: ListingPreferenceState, reasons: list<string>, first: \DateTimeImmutable, last: \DateTimeImmutable}> $runs */
        $runs = [];
        $open = null;

        foreach ($events as $event) {
            if ($event->toState === null) {
                // Rule 4: a clear closes the open choice and deletes nothing.
                $open = null;
                continue;
            }

            $state = ListingPreferenceState::tryFrom($event->toState);

            if ($state === null) {
                // An unrecognised stored value is not evidence of anything.
                continue;
            }

            $reasons = self::reasonKeys($event->reasonKeys);

            if ($open !== null && $runs[$open]['state'] === $state) {
                // Rule 2: the same choice, with a corrected answer.
                $runs[$open]['reasons'] = $reasons;
                $runs[$open]['last']    = $event->at;
                continue;
            }

            // Rule 3: a new decision.
            $runs[] = ['state' => $state, 'reasons' => $reasons, 'first' => $event->at, 'last' => $event->at];
            $open   = count($runs) - 1;
        }

        if ($runs === []) {
            return null;
        }

        $lastIndex = count($runs) - 1;
        $choices   = [];

        foreach ($runs as $i => $run) {
            $choices[] = new TasteChoice(
                state:      $run['state'],
                reasonKeys: $run['reasons'],
                firstAt:    $run['first'],
                lastAt:     $run['last'],
                // Rule 5.
                current:    $i === $lastIndex && $open === $lastIndex,
            );
        }

        // The listing most recently acted on for this home, clears included —
        // a clear still names which listing the customer was looking at.
        $latest = $events[count($events) - 1];

        return new TasteSubjectHistory($subjectKey, $latest->refKey(), $choices);
    }

    /**
     * Distinct, well-formed catalog keys in a stable order. Anything that is not
     * a key shape is dropped here: the snapshot holds keys, never prose.
     *
     * @param  array<int, mixed> $raw
     * @return list<string>
     */
    private static function reasonKeys(array $raw): array
    {
        $keys = [];

        foreach ($raw as $value) {
            if (is_string($value) && preg_match('/^[a-z][a-z0-9_]{1,62}$/', $value) === 1) {
                $keys[$value] = true;
            }
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return $keys;
    }
}
