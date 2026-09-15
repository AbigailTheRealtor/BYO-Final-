<?php

namespace App\Services\SmartTags;

use App\Services\SmartTags\Derivation\TagEvidence;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Per-source evidence → one canonical assignment per tag. Pure.
 *
 * 1. EVIDENCE FILTER. Only evidence recorded for the listing's CURRENT context,
 *    for an active tag that applies there, counts. "Absent" counts only from a
 *    structured source and only for a negatable tag — a description or an owner
 *    can never create a confirmed "no".
 *
 * 2. PER-TAG PRECEDENCE. The strongest source with an opinion decides:
 *    structured_mls > structured_native_listing > manual_listing_owner >
 *    mls_remarks / native_listing_description. A weaker source that disagrees
 *    sets has_conflict. So a structured "No" outranks a manual or described
 *    "present", and re-deriving never needs to touch an owner's selection.
 *
 * 3. CROSS-TAG CONFLICTS. Two PRESENT tags declared to conflict (move_in_ready vs
 *    fixer_upper; furnished vs unfurnished) cannot both stand. A tag survives
 *    only if no conflicting present tag is backed by an equal or stronger
 *    source; the survivor records whom it overrode. At equal strength both are
 *    dropped — unknown is the honest answer to contradictory evidence of the
 *    same weight.
 */
final class SmartTagResolver
{
    /**
     * @param TagEvidence[] $evidence
     */
    public static function resolve(array $evidence, ?SmartTagContext $context): SmartTagResolution
    {
        if ($context === null) {
            return new SmartTagResolution([], []);
        }

        /** @var array<string, TagEvidence[]> $byTag */
        $byTag = [];
        foreach ($evidence as $item) {
            if ($item->context !== $context) {
                continue;
            }
            $definition = SmartTagTaxonomy::get($item->tagKey);
            if ($definition === null || ! $definition->isActive() || ! $definition->appliesTo($context)) {
                continue;
            }
            if ($item->state === SmartTagState::Absent && (! $item->source->canAssertAbsent() || ! $definition->negatable)) {
                continue;
            }
            $byTag[$item->tagKey][] = $item;
        }

        /** @var array<string, array{winner: TagEvidence, conflict: bool}> $perTag */
        $perTag = [];
        foreach ($byTag as $tag => $items) {
            usort($items, static fn (TagEvidence $a, TagEvidence $b) => [$a->source->precedenceRank(), $a->state === SmartTagState::Absent ? 0 : 1]
                <=> [$b->source->precedenceRank(), $b->state === SmartTagState::Absent ? 0 : 1]);
            $winner = $items[0];
            $conflict = false;
            foreach ($items as $other) {
                if ($other->state !== $winner->state) {
                    $conflict = true;
                    break;
                }
            }
            $perTag[$tag] = ['winner' => $winner, 'conflict' => $conflict];
        }

        $present = array_filter($perTag, static fn (array $r) => $r['winner']->state === SmartTagState::Present);

        $assignments = [];
        $dropped = [];

        foreach ($perTag as $tag => $row) {
            $winner = $row['winner'];
            $overrode = [];

            if ($winner->state === SmartTagState::Present) {
                $definition = SmartTagTaxonomy::get($tag);
                $rank = $winner->source->precedenceRank();
                $survives = true;

                foreach ($present as $otherTag => $otherRow) {
                    if ($otherTag === $tag || ! $definition->conflictsWith($otherTag)) {
                        continue;
                    }
                    $otherRank = $otherRow['winner']->source->precedenceRank();
                    if ($otherRank <= $rank) {
                        $survives = false;
                        break;
                    }
                    $overrode[] = $otherTag;
                }

                if (! $survives) {
                    $dropped[] = $tag;
                    continue;
                }
            }

            sort($overrode);

            $assignments[$tag] = new ResolvedSmartTag(
                tagKey: $tag,
                state: $winner->state,
                winningSource: $winner->source,
                context: $context,
                hasConflict: $row['conflict'] || $overrode !== [],
                conflictTags: $overrode,
            );
        }

        ksort($assignments);
        sort($dropped);

        return new SmartTagResolution($assignments, $dropped);
    }
}
