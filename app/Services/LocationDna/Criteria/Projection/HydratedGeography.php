<?php

namespace App\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\Rules\GeographySelection;

/**
 * Phase 1c — the two halves of a stored blob after hydration: what became a selection, and what
 * the corpus could not resolve.
 *
 * The split is the point. A hydrator that returned only the selection would force its caller to
 * diff the input against the output to discover what had gone missing — and a caller that forgot
 * to would silently drop it. Returning both halves makes preserving history the easy path and
 * losing it the deliberate one.
 */
final class HydratedGeography
{
    public function __construct(
        public readonly GeographySelection $selection,
        public readonly PreservedGeographyLabels $preserved,
    ) {
    }

    public static function empty(): self
    {
        return new self(GeographySelection::empty(), PreservedGeographyLabels::none());
    }
}
