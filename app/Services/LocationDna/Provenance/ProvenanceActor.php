<?php

namespace App\Services\LocationDna\Provenance;

/**
 * ProvenanceActor — who is driving a provenance transition.
 *
 * G1e inert provenance model. INERT.
 *
 * WHY THIS AXIS EXISTS
 * --------------------
 * Without it, "legacy fallback becomes owner authored" cannot be distinguished from "the system
 * quietly promoted a fallback value to authored". Those are the same from-and-to pair and
 * completely different events. §5.4 rule 5 forbids the second — a mirror-recovered value "does
 * not become authored until the user next writes that dimension" — so the actor is what makes
 * the rule expressible.
 *
 * There are exactly three, and deliberately no `Force` or `Bypass` case: an escape hatch would
 * become the path every future caller takes.
 */
enum ProvenanceActor: string
{
    /** A deliberate act by the record owner, through the G1c command vocabulary. */
    case ExplicitOwner = 'explicit_owner';

    /** The system acting on its own — enrichment, normalisation, import, a cache refresh. */
    case AutomaticSystem = 'automatic_system';

    /** A migration or lazy repair writing storage without claiming authorship. */
    case MigrationRepair = 'migration_repair';

    public function isExplicitOwnerAction(): bool
    {
        return $this === self::ExplicitOwner;
    }
}
