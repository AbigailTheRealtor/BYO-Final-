<?php

namespace App\Services\Canonical;

/**
 * CanonicalListing — a source-neutral, in-memory projection of a listing.
 *
 * This is the Wave 1 realization of §F1 of the frozen roadmap: instead of a
 * physical canonical mega-table, a resolver/adapter layer projects any source
 * (BYO role listings today; Bridge/RESO, RentCast, ATTOM, CSV later) onto one
 * canonical field vocabulary. Downstream DNA / scoring / matching read ONLY
 * from here, never from a source-specific column.
 *
 * Each canonical field carries its provenance metadata (source, source_field,
 * source_reliability, freshness) so confidence (§F4) and explainability (§F5)
 * can trace every value back to where it came from.
 *
 * Fields are only present when the source actually populated them, so has()/
 * present() are meaningful signals for data-completeness scoring.
 */
class CanonicalListing
{
    /** @var array<string,mixed> canonical_key => normalized value */
    private array $fields;

    /** @var array<string,array<string,mixed>> canonical_key => provenance meta */
    private array $meta;

    private string $listingType;

    private int $listingId;

    /**
     * @param array<string,mixed> $fields
     * @param array<string,array<string,mixed>> $meta
     */
    public function __construct(string $listingType, int $listingId, array $fields = [], array $meta = [])
    {
        $this->listingType = $listingType;
        $this->listingId   = $listingId;
        $this->fields      = $fields;
        $this->meta        = $meta;
    }

    public function listingType(): string
    {
        return $this->listingType;
    }

    public function listingId(): int
    {
        return $this->listingId;
    }

    /** A canonical key exists (even if its value is null). */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    /** A canonical key exists AND carries a non-null value. */
    public function present(string $key): bool
    {
        return $this->has($key) && $this->fields[$key] !== null;
    }

    /** @return mixed the normalized value, or $default when absent/null. */
    public function get(string $key, $default = null)
    {
        return $this->present($key) ? $this->fields[$key] : $default;
    }

    /** @return array<string,mixed> provenance metadata for a canonical field. */
    public function fieldMeta(string $key): array
    {
        return $this->meta[$key] ?? [];
    }

    /** @return array<string,mixed> all present canonical fields. */
    public function all(): array
    {
        return $this->fields;
    }
}
