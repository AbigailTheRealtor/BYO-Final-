<?php

namespace App\Services\LocationDna\Capability;

/**
 * LocationDnaPurpose — why the document is being accessed.
 *
 * G1d inert capability resolver. INERT.
 *
 * Purpose is a separate axis from surface and viewer because v1.2 §7 requires capability to be
 * purpose-specific rather than a blanket private-document grant. G1b F-G1B-3 showed the cost of
 * the blanket alternative: 29 of 44 consumers treat "can I see it" and "may I act on it" as one
 * question. Read and Edit are therefore different purposes, and neither implies the other.
 */
enum LocationDnaPurpose: string
{
    /** Render or return the document, or part of it. */
    case Read = 'read';

    /** Mutate the document through the G1c command vocabulary. */
    case Edit = 'edit';

    /** Read-only preview of one's own private document. */
    case Preview = 'preview';

    /** Internal matching / filtering. */
    case Matching = 'matching';

    /** Assemble Bridge criteria for an outbound query or cache key. */
    case BridgeCriteria = 'bridge_criteria';

    /** Persist canonical state. */
    case Persistence = 'persistence';

    /** Repair a diverged legacy mirror. */
    case LegacyRepair = 'legacy_repair';

    /** Read the retained snapshot. */
    case SnapshotRead = 'snapshot_read';

    /** Unresolved or unrecognised. Always fully denied. */
    case Unknown = 'unknown';

    public static function fromNameOrUnknown(?string $name): self
    {
        if ($name === null) {
            return self::Unknown;
        }

        return self::tryFrom(strtolower(trim($name))) ?? self::Unknown;
    }

    public function isMutating(): bool
    {
        return $this === self::Edit || $this === self::Persistence || $this === self::LegacyRepair;
    }
}
