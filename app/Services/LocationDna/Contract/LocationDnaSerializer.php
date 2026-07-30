<?php

namespace App\Services\LocationDna\Contract;

/**
 * LocationDnaSerializer — deterministic serialisation of the PRIVATE canonical document.
 *
 * G1c contract core. INERT. Approved by D-G1-1 (option 1-B).
 *
 * THIS IS THE PRIVATE DOCUMENT
 * ---------------------------
 * Geometry and `location_notes` are retained in full. Serialisation is NOT an exposure
 * boundary and applies no redaction: `PublicGeometryProjection` remains the separate
 * exposure-boundary mechanism and is unchanged by this increment (D-G1-5). Removing
 * geometry here would silently make the canonical store lossy.
 *
 * OMISSION IS A CAPABILITY, NOT AN ACCIDENT
 * -----------------------------------------
 * §5.2 requires the serializer to be able to OMIT a never-authored key distinguishably from
 * emitting the canonical empty. That is the whole point: an absent dimension is left out, a
 * cleared dimension is emitted as its canonical empty. §5.3 adds the rule that omission must
 * never be produced by a failure (L5) — which is why this class only accepts a valid
 * document and cannot be handed raw input.
 *
 * BYTE-COMPATIBILITY IS WITHDRAWN
 * -------------------------------
 * §5.3 withdraws any byte-identity guarantee. What IS guaranteed is determinism: the same
 * canonical meaning always produces the same output, because keys are sorted at every level.
 */
final class LocationDnaSerializer
{
    /**
     * Serialise to the canonical array shape.
     *
     * Emits present dimensions only, plus `schema_version`, plus retained extensions.
     */
    public function toArray(LocationDnaDocument $document): array
    {
        $out = $document->toDimensionArray();

        // Retained unknown-future and withdrawn-but-present keys, passed through
        // uninterpreted. A canonical dimension always wins over an extension of the same
        // name, which cannot happen via the hydrator but is enforced here regardless.
        foreach ($document->extensions() as $key => $value) {
            if (! array_key_exists($key, $out)) {
                $out[$key] = $value;
            }
        }

        // §5.5: every v1.2 write stamps the current version.
        $out['schema_version'] = LocationDnaDocument::CURRENT_SCHEMA_VERSION;

        ksort($out);

        return $out;
    }

    /**
     * Serialise to a deterministic JSON string.
     *
     * Unicode is left unescaped so user-entered place names and notes survive legibly; G1a's
     * storage characterisation established that unicode round-trips through the meta column.
     */
    public function toJson(LocationDnaDocument $document): string
    {
        return (string) json_encode($this->toArray($document), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The array a writer would persist for a lazy upgrade (§5.5).
     *
     * Stamps the version and records the observed presence set, changing no values.
     */
    public function toArrayForLazyUpgrade(LocationDnaDocument $document): array
    {
        return $this->toArray($document->withLazyUpgrade());
    }
}
