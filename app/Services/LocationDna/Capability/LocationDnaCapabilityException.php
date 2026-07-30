<?php

namespace App\Services\LocationDna\Capability;

use RuntimeException;

/**
 * LocationDnaCapabilityException — a context that cannot be resolved at all.
 *
 * G1d inert capability resolver. INERT.
 *
 * Reserved for a context that is internally CONTRADICTORY, e.g. claiming owner relationship on a
 * fully public surface. An unknown or incomplete context is NOT an exception — it resolves to a
 * fully denied capability set, because default-deny is the safe outcome and an exception a caller
 * might catch is not. This class exists only for the case where silently denying would hide a
 * caller bug that will recur.
 */
final class LocationDnaCapabilityException extends RuntimeException
{
    public static function contradictoryContext(string $why): self
    {
        return new self("Contradictory Location DNA access context: {$why}");
    }
}
