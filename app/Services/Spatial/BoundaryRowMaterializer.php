<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * Pure transform: a canonical {@see BoundaryRecord} + a corpus_version → one `boundaries` row, in
 * the exact COLUMN ORDER the Class-2 COPY consumes. Converts canonical GeoJSON MultiPolygon
 * coordinates into deterministic `SRID=4326;MULTIPOLYGON(...)` EWKT (the geography input parses it on
 * COPY). Emits PHP scalars, never touches a DB, and is deterministic. Mirrors
 * {@see PlaceRowMaterializer} (2C).
 *
 * Column contract (B1.2 migration 06 — boundaries):
 *   • id is bigserial → assigned by PostgreSQL, NEVER materialized here.
 *   • geom is geography(MultiPolygon,4326); we emit MULTIPOLYGON EWKT.
 *   • attrs is jsonb (JSON-encoded here).
 *   • NO centroid column exists on boundaries — nothing is synthesized.
 *   • boundaries_parts is NOT materialized offline: it needs the DB-assigned boundary_id and
 *     ST_Subdivide, both Class-2 (see the spike sql/load_padus_boundaries.sql).
 *
 * @see \Tests\Unit\Spatial\BoundaryRowMaterializerTest
 */
final class BoundaryRowMaterializer
{
    /**
     * COPY target columns, in order — mirrors migration 06. id (bigserial) is intentionally absent.
     * The SqlManifest drift test asserts the recipe's COPY column list equals this constant.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'kind',
        'external_ref',
        'attrs',
        'geom',
        'corpus_version',
    ];

    /**
     * Materialize one record into a column-keyed row.
     *
     * @return array<string,mixed>
     */
    public function materialize(BoundaryRecord $record, string $corpusVersion): array
    {
        $version = trim($corpusVersion);
        if ($version === '') {
            throw new \InvalidArgumentException('corpus_version must be a non-empty string.');
        }

        return [
            'kind'           => $record->kind,
            'external_ref'   => $record->external_ref,
            'attrs'          => json_encode($record->attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            'geom'           => $this->multiPolygonEwkt($record->geometry),
            'corpus_version' => $version,
        ];
    }

    /**
     * Ordered scalar row (values in COLUMNS order).
     *
     * @return list<mixed>
     */
    public function toRow(BoundaryRecord $record, string $corpusVersion): array
    {
        $assoc = $this->materialize($record, $corpusVersion);

        return array_map(static fn (string $col) => $assoc[$col], self::COLUMNS);
    }

    /**
     * @param  iterable<BoundaryRecord> $records
     * @return list<list<mixed>>
     */
    public function toRows(iterable $records, string $corpusVersion): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = $this->toRow($record, $corpusVersion);
        }

        return $rows;
    }

    /**
     * Canonical GeoJSON MultiPolygon coordinates → `SRID=4326;MULTIPOLYGON(((lon lat, …),(hole…)),…)`.
     * Polygon/ring/position order is preserved; degrees rendered without scientific notation.
     *
     * @param array{type:string,coordinates:array} $geometry
     */
    public function multiPolygonEwkt(array $geometry): string
    {
        $polygons = [];
        foreach ($geometry['coordinates'] as $polygon) {
            $rings = [];
            foreach ($polygon as $ring) {
                $positions = [];
                foreach ($ring as $pos) {
                    $positions[] = $this->degrees((float) $pos[0]) . ' ' . $this->degrees((float) $pos[1]);
                }
                $rings[] = '(' . implode(', ', $positions) . ')';
            }
            $polygons[] = '(' . implode(', ', $rings) . ')';
        }

        return 'SRID=4326;MULTIPOLYGON(' . implode(', ', $polygons) . ')';
    }

    /** Fixed-precision degrees (10 dp ≈ 0.01 mm), trailing zeros trimmed, -0 normalized. */
    private function degrees(float $value): string
    {
        $s = rtrim(rtrim(sprintf('%.10f', $value), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
