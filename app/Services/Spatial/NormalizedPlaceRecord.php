<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B2).
 *
 * Immutable DTO for one normalized place in the canonical NDJSON extract.
 * The field order of toArray() IS the on-disk NDJSON key order — keep it stable
 * (the extract is asserted deterministic + idempotent).
 *
 * This is the offline (cluster-free) counterpart of a future `places` row; it
 * carries only what the offline slice produces. It deliberately does NOT model
 * PostGIS geometry — geometry is carried as plain lon/lat degrees (EPSG:4326)
 * plus a geometry_type tag, and materialization into geography() is a later
 * Class-2 concern.
 */
final class NormalizedPlaceRecord
{
    public function __construct(
        public readonly string $source,
        public readonly string $source_ref,
        public readonly ?string $gers_id,
        public readonly string $category_key,
        public readonly ?string $name,
        public readonly ?string $brand,
        public readonly ?float $confidence,
        public readonly int $source_count,
        public readonly float $lon,
        public readonly float $lat,
        public readonly string $geometry_type = 'Point',
    ) {
    }

    /**
     * Canonical NDJSON shape. Key order is the wire format — do not reorder.
     */
    public function toArray(): array
    {
        return [
            'source'        => $this->source,
            'source_ref'    => $this->source_ref,
            'gers_id'       => $this->gers_id,
            'category_key'  => $this->category_key,
            'name'          => $this->name,
            'brand'         => $this->brand,
            'confidence'    => $this->confidence,
            'source_count'  => $this->source_count,
            'lon'           => $this->lon,
            'lat'           => $this->lat,
            'geometry_type' => $this->geometry_type,
        ];
    }

    /**
     * Reconstruct from a decoded NDJSON line. Round-trips exactly with
     * toArray(): fromArray($r->toArray()) == $r.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['source', 'source_ref', 'category_key', 'lon', 'lat'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new \InvalidArgumentException(
                    "NormalizedPlaceRecord::fromArray() missing required key [{$required}]."
                );
            }
        }

        return new self(
            source: (string) $data['source'],
            source_ref: (string) $data['source_ref'],
            gers_id: isset($data['gers_id']) ? (string) $data['gers_id'] : null,
            category_key: (string) $data['category_key'],
            name: isset($data['name']) ? (string) $data['name'] : null,
            brand: isset($data['brand']) ? (string) $data['brand'] : null,
            confidence: isset($data['confidence']) ? (float) $data['confidence'] : null,
            source_count: (int) ($data['source_count'] ?? 0),
            lon: (float) $data['lon'],
            lat: (float) $data['lat'],
            geometry_type: (string) ($data['geometry_type'] ?? 'Point'),
        );
    }
}
