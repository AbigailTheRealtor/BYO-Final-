<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * Pure, deterministic canonical-GeoJSON handling for boundary geometry (decision: geometry is
 * canonical GeoJSON MultiPolygon in the DTO, fixtures, and artifacts — never WKT). No DB, no PostGIS.
 *
 * VALIDATION is STRUCTURAL ONLY (owner decision): type, closure, coordinate count, finiteness, and
 * geographic bounds. Topological validity (self-intersection, ring winding / RFC-7946 orientation,
 * ST_IsValid / ST_MakeValid) is a CLASS-2 concern — authored as a verification query in the spike
 * SQL, never applied offline. This class never alters coordinate order or repairs geometry.
 *
 * NORMALIZATION: a structurally-valid GeoJSON `Polygon` is deterministically wrapped into a
 * one-member `MultiPolygon`; a `MultiPolygon` passes through; anything else is rejected. An invalid
 * Polygon is NEVER wrapped.
 *
 * @see \Tests\Unit\Spatial\BoundaryGeometryTest
 */
final class BoundaryGeometry
{
    /** A GeoJSON linear ring needs ≥4 positions (≥3 distinct + the closing repeat). */
    private const MIN_RING_POSITIONS = 4;

    /**
     * Normalize decoded GeoJSON to a canonical MultiPolygon, or null if structurally invalid.
     * A valid Polygon is wrapped to a one-member MultiPolygon. Coordinate order is preserved.
     *
     * @param  mixed $geometry decoded GeoJSON (assoc array expected)
     * @return array{type:string,coordinates:array}|null
     */
    public function normalizeToMultiPolygon(mixed $geometry): ?array
    {
        if (!is_array($geometry) || !isset($geometry['type']) || !array_key_exists('coordinates', $geometry)) {
            return null;
        }

        $type = $geometry['type'];
        $coords = $geometry['coordinates'];

        if ($type === 'Polygon') {
            if (!$this->isValidPolygon($coords)) {
                return null;
            }

            return ['type' => 'MultiPolygon', 'coordinates' => [$coords]];
        }

        if ($type === 'MultiPolygon') {
            if (!$this->isValidMultiPolygonCoordinates($coords)) {
                return null;
            }

            return ['type' => 'MultiPolygon', 'coordinates' => $coords];
        }

        return null;
    }

    /** True iff $geometry is (already) a structurally-valid canonical MultiPolygon. */
    public function isValidMultiPolygon(mixed $geometry): bool
    {
        return is_array($geometry)
            && ($geometry['type'] ?? null) === 'MultiPolygon'
            && array_key_exists('coordinates', $geometry)
            && $this->isValidMultiPolygonCoordinates($geometry['coordinates']);
    }

    /** @param mixed $coords MultiPolygon coordinates: list of polygons */
    private function isValidMultiPolygonCoordinates(mixed $coords): bool
    {
        if (!is_array($coords) || $coords === [] || !array_is_list($coords)) {
            return false;
        }
        foreach ($coords as $polygon) {
            if (!$this->isValidPolygon($polygon)) {
                return false;
            }
        }

        return true;
    }

    /** @param mixed $polygon Polygon coordinates: list of rings (exterior first) */
    private function isValidPolygon(mixed $polygon): bool
    {
        if (!is_array($polygon) || $polygon === [] || !array_is_list($polygon)) {
            return false;
        }
        foreach ($polygon as $ring) {
            if (!$this->isValidRing($ring)) {
                return false;
            }
        }

        return true;
    }

    /** @param mixed $ring list of [lon, lat] positions; closed; ≥4 positions */
    private function isValidRing(mixed $ring): bool
    {
        if (!is_array($ring) || !array_is_list($ring) || count($ring) < self::MIN_RING_POSITIONS) {
            return false;
        }
        foreach ($ring as $pos) {
            if (!$this->isValidPosition($pos)) {
                return false;
            }
        }

        // Closed: first position equals last (numeric, robust to int/float JSON decoding).
        $first = $ring[0];
        $last = $ring[count($ring) - 1];

        return (float) $first[0] === (float) $last[0]
            && (float) $first[1] === (float) $last[1];
    }

    /** @param mixed $pos exactly [lon, lat], finite, in geographic bounds */
    private function isValidPosition(mixed $pos): bool
    {
        if (!is_array($pos) || !array_is_list($pos) || count($pos) !== 2) {
            return false;
        }
        [$lon, $lat] = $pos;
        if (!is_int($lon) && !is_float($lon)) {
            return false;
        }
        if (!is_int($lat) && !is_float($lat)) {
            return false;
        }

        return is_finite((float) $lon) && is_finite((float) $lat)
            && $lon >= -180.0 && $lon <= 180.0
            && $lat >= -90.0 && $lat <= 90.0;
    }
}
