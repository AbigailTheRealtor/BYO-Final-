<?php

namespace App\Services\Location\Coordinates;

/**
 * How much a stored coordinate's provenance can be relied on.
 *
 * Three states rather than a boolean, because "we cannot vouch for this" and
 * "a person deliberately chose this" are both distinct from the ordinary case
 * and call for opposite handling: the first must be re-derived, the second must
 * be left alone.
 *
 * WHY THIS IS NOT "TRUSTED / UNTRUSTED"
 * ------------------------------------
 * A trust boolean would have to decide, in one bit, both whether the record is
 * complete and whether the source outranks whatever else could answer. Those are
 * different questions with different owners — completeness is a property of the
 * stored row and lives here; precedence is a property of the ladder and lives in
 * the rungs. Collapsing them is how a coordinate ends up "trusted" because it
 * was well-recorded, regardless of what recorded it.
 *
 * So this enum answers only the first: is the provenance a statement we can
 * read. {@see PropertyCoordinateMeta::classify()} produces it.
 */
enum CoordinateProvenanceStatus: string
{
    /**
     * Recorded by the platform: a ladder rung resolved it and stamped which
     * rung, how precise, and which address. No person was involved, and none is
     * claimed.
     */
    case Automatic = 'automatic';

    /**
     * A person overrode the automatic answer, and the record says who and why.
     *
     * Only reachable through {@see PropertyCoordinateResult::manual()}, which
     * refuses to construct one without both.
     */
    case Manual = 'manual';

    /**
     * The provenance is absent, partial, unreadable, or self-contradictory —
     * a legacy row written before provenance existed, a coordinate that never
     * came through the ladder, or a manual claim nobody signed.
     *
     * Not an error state. Most coordinates in an established database are here,
     * and the correct response is to re-derive rather than to distrust the
     * number itself.
     */
    case Incomplete = 'incomplete';

    /** True when the provenance is a complete, self-consistent statement. */
    public function isComplete(): bool
    {
        return $this !== self::Incomplete;
    }
}
