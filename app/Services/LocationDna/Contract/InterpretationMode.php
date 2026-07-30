<?php

namespace App\Services\LocationDna\Contract;

/**
 * InterpretationMode — how a missing dimension key is to be read (§5.4).
 *
 * G1c contract core. INERT.
 *
 * §5.4 rule 1: mode is read from `schema_version` ONCE, at hydration, and no consumer
 * re-derives it. That is why the mode travels on the document rather than being recomputed.
 *
 * The two modes correspond to §5.4's S1 and S2. S3 (no blob), S4 (present-but-empty) and
 * S5 (recovered from a legacy mirror) are not modes: S3 and S4 are document states this
 * class does not need to name, and S5 is the legacy adapter's concern — not created in
 * this increment (D-G1-5).
 */
enum InterpretationMode: string
{
    /**
     * S1 · legacy blob written by an all-keys writer; `schema_version` absent.
     *
     * A missing key is INDETERMINATE and is treated as absent. §5.4 rule 2: clearing is not
     * expressible retroactively — a legacy record's missing `polygons` means "unknown,
     * assume never authored", and the system must not later claim the user cleared it.
     */
    case Legacy = 'legacy';

    /**
     * S2 · canonical record; `schema_version` >= 2.
     *
     * A missing key is AUTHORITATIVE: never authored. §5.4 rule 4.
     */
    case Canonical = 'canonical';

    /** True when an absent key may legitimately fall back to a legacy source. */
    public function allowsLegacyFallback(): bool
    {
        return $this === self::Legacy;
    }
}
