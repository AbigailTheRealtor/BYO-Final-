<?php

namespace App\Services\LocationDna\Contract;

/**
 * DimensionKind — the structural family of a canonical dimension.
 *
 * G1c contract core. INERT. Drives the canonical empty value and the validation rule
 * applied by {@see LocationDnaNormalizer}.
 */
enum DimensionKind
{
    /** list<string> — e.g. cities, zip_codes, counties. Canonical empty: []. */
    case StringList;

    /** list<array> of structured entries — polygons, radius_searches. Canonical empty: []. */
    case ObjectList;

    /** string — state, location_notes. Canonical empty: ''. */
    case Text;

    /** bool — flexible_location. Canonical empty: false. */
    case Flag;

    /** associative array — subject_property. Canonical empty: []. */
    case Map;
}
