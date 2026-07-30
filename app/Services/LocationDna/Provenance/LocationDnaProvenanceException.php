<?php

namespace App\Services\LocationDna\Provenance;

use RuntimeException;

/**
 * LocationDnaProvenanceException — malformed or untrusted provenance input.
 *
 * G1e inert provenance model. INERT.
 *
 * Mirrors the G1c safety posture: malformed input is rejected rather than silently becoming
 * trusted, and an unsupported internal version is refused rather than guessed at. A provenance
 * record that cannot be read must never default to `OwnerAuthored`, because that would manufacture
 * authority out of a parse failure — the provenance analogue of L5.
 */
final class LocationDnaProvenanceException extends RuntimeException
{
    public static function malformed(string $why): self
    {
        return new self("Malformed Location DNA provenance: {$why}");
    }

    public static function unsupportedVersion(int $found, int $supported): self
    {
        return new self(
            "Location DNA provenance version {$found} is newer than the supported version {$supported}; "
            .'it is read-only and must not be interpreted.',
        );
    }

    public static function forbiddenTransition(string $why): self
    {
        return new self("Forbidden Location DNA provenance transition: {$why}");
    }
}
