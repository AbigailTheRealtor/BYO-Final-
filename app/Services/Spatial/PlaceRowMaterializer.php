<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * Pure transform: a canonical NormalizedPlaceRecord (Batch 2A) + a corpus_version
 * → one `places` row, in the exact COPY column order. This is the offline
 * counterpart of a real INSERT; it emits PHP scalars (and EWKT geography text),
 * never touches a DB, and is deterministic.
 *
 * Column contract (B1.2 migration 04 — places):
 *   • place_id is bigserial → assigned by PostgreSQL, NEVER materialized here.
 *   • geom / centroid are geography(4326); we emit `SRID=4326;POINT(lon lat)`
 *     EWKT, which the geography input function parses directly on COPY. For the
 *     first slice every place is a Point, so geom == centroid.
 *   • gers_id is a first-class column; attrs carries the geometry_type tag.
 *   • authority_metric is left NULL (first slice has no authority signal).
 *   • confidence maps to numeric(4,3); source_count to smallint.
 *
 * Coordinate ranges are validated defensively (the acceptance gate is the real
 * check, but an out-of-range lon/lat would materialize an invalid geography).
 */
final class PlaceRowMaterializer
{
    /**
     * COPY target columns, in order. place_id is intentionally absent (serial).
     * This IS the wire order consumed by CorpusCopyLoader — keep it stable.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'corpus_version',
        'source',
        'source_ref',
        'gers_id',
        'geom',
        'centroid',
        'category_key',
        'name',
        'brand',
        'confidence',
        'source_count',
        'authority_metric',
        'attrs',
        'first_seen',
        'last_seen',
    ];

    /**
     * Materialize one record into a column-keyed row. $stamp (ISO-8601, optional)
     * fills first_seen/last_seen — passed in rather than read from the clock so
     * the transform stays pure and testable.
     *
     * @return array<string,mixed>
     */
    public function materialize(NormalizedPlaceRecord $record, string $corpusVersion, ?string $stamp = null): array
    {
        $version = trim($corpusVersion);
        if ($version === '') {
            throw new \InvalidArgumentException('corpus_version must be a non-empty string.');
        }

        $this->assertCoordinate($record->lon, $record->lat);
        $point = $this->pointEwkt($record->lon, $record->lat);

        return [
            'corpus_version'   => $version,
            'source'           => $record->source,
            'source_ref'       => $record->source_ref,
            'gers_id'          => $record->gers_id,
            'geom'             => $point,
            'centroid'         => $point,
            'category_key'     => $record->category_key,
            'name'             => $record->name,
            'brand'            => $record->brand,
            'confidence'       => $record->confidence,
            'source_count'     => $record->source_count,
            'authority_metric' => null,
            'attrs'            => json_encode(
                ['geometry_type' => $record->geometry_type],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'first_seen'       => $stamp,
            'last_seen'        => $stamp,
        ];
    }

    /**
     * Ordered scalar row (values in COLUMNS order) — the shape CorpusCopyLoader
     * serializes into COPY text.
     *
     * @return list<mixed>
     */
    public function toRow(NormalizedPlaceRecord $record, string $corpusVersion, ?string $stamp = null): array
    {
        $assoc = $this->materialize($record, $corpusVersion, $stamp);

        return array_map(static fn (string $col) => $assoc[$col], self::COLUMNS);
    }

    /**
     * @param iterable<NormalizedPlaceRecord> $records
     * @return list<list<mixed>>
     */
    public function toRows(iterable $records, string $corpusVersion, ?string $stamp = null): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = $this->toRow($record, $corpusVersion, $stamp);
        }

        return $rows;
    }

    /** `SRID=4326;POINT(lon lat)` — degrees rendered without scientific notation. */
    public function pointEwkt(float $lon, float $lat): string
    {
        return sprintf('SRID=4326;POINT(%s %s)', $this->degrees($lon), $this->degrees($lat));
    }

    private function assertCoordinate(float $lon, float $lat): void
    {
        if (!is_finite($lon) || !is_finite($lat)) {
            throw new \InvalidArgumentException('Coordinates must be finite numbers.');
        }
        if ($lon < -180.0 || $lon > 180.0) {
            throw new \InvalidArgumentException("Longitude {$lon} out of range [-180, 180].");
        }
        if ($lat < -90.0 || $lat > 90.0) {
            throw new \InvalidArgumentException("Latitude {$lat} out of range [-90, 90].");
        }
    }

    /** Fixed-precision degrees (10 dp ≈ 0.01 mm), trailing zeros trimmed. */
    private function degrees(float $value): string
    {
        $s = rtrim(rtrim(sprintf('%.10f', $value), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
