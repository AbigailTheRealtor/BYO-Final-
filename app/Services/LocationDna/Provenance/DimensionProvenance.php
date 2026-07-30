<?php

namespace App\Services\LocationDna\Provenance;

use App\Services\LocationDna\Contract\Dimension;

/**
 * DimensionProvenance — immutable origin record for exactly one dimension.
 *
 * G1e inert provenance model. INERT.
 *
 * Pairs a G1c {@see Dimension} with a {@see LocationDnaProvenanceKind}. Authority is derived from
 * the kind rather than stored alongside it, so the two can never drift apart.
 *
 * PROVENANCE GRANTS NOTHING
 * -------------------------
 * There is deliberately no `mayExpose()`, `mayEdit()`, `mayRead()` or similar. Capability is G1d's
 * and only G1d's: this namespace does not import, invoke or name
 * `LocationDnaCapabilityResolver`. Knowing that the owner authored a polygon tells you who put it
 * there — it says nothing about who may see it.
 */
final class DimensionProvenance
{
    private function __construct(
        public readonly Dimension $dimension,
        public readonly LocationDnaProvenanceKind $kind,
    ) {
    }

    public static function of(Dimension $dimension, LocationDnaProvenanceKind $kind): self
    {
        return new self($dimension, $kind);
    }

    public static function ownerAuthored(Dimension $dimension): self
    {
        return new self($dimension, LocationDnaProvenanceKind::OwnerAuthored);
    }

    public static function ownerCleared(Dimension $dimension): self
    {
        return new self($dimension, LocationDnaProvenanceKind::OwnerCleared);
    }

    public static function unknown(Dimension $dimension): self
    {
        return new self($dimension, LocationDnaProvenanceKind::Unknown);
    }

    public function authority(): ProvenanceAuthority
    {
        return $this->kind->authority();
    }

    public function isAuthoritative(): bool
    {
        return $this->kind->isAuthoritative();
    }

    public function blocksFallbackResurrection(): bool
    {
        return $this->kind->blocksFallbackResurrection();
    }

    /** A new record for the same dimension with a different kind. Never mutates this one. */
    public function withKind(LocationDnaProvenanceKind $kind): self
    {
        return new self($this->dimension, $kind);
    }

    /** Deterministic identity for equivalence comparison. */
    public function signature(): string
    {
        return $this->dimension->value.':'.$this->kind->value;
    }
}
