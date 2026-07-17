<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B2).
 *
 * Pure, cluster-free normalizer: raw Overture place rows → canonical
 * NormalizedPlaceRecord objects. No I/O, no DB, no network — the offline
 * command feeds it decoded fixture rows and the DuckDB extract feeds it the
 * same shape at scale.
 *
 * Filtering contract (owner decision):
 *   • PRIMARY category only. `categories.primary` is mapped via
 *     OvertureCategoryMap; `categories.alternate` is NEVER inspected.
 *   • confidence floor: rows with confidence < confidence_min (or null) are
 *     dropped and COUNTED.
 *   • Unmapped primary categories are dropped and TALLIED — never lost.
 *
 * Determinism: input order is preserved for kept rows; identical input yields
 * an identical result (canonical ordering for the on-disk extract is applied
 * separately by NormalizedExtractIo).
 */
final class OverturePlaceNormalizer
{
    public function __construct(
        private readonly OvertureCategoryMap $map,
        private readonly float $confidenceMin = 0.90,
    ) {
    }

    /**
     * @param iterable<array<string,mixed>> $rawRows decoded raw Overture rows
     */
    public function normalize(iterable $rawRows): NormalizationResult
    {
        $records = [];
        $total = 0;
        $rejUnmapped = 0;
        $rejLowConf = 0;
        $rejInvalid = 0;
        $unmappedTally = [];

        foreach ($rawRows as $raw) {
            $total++;

            $gersId = $this->str($raw['id'] ?? null);
            [$lon, $lat] = $this->coordinates($raw);

            // 1) Structurally unusable rows are counted, not lost.
            if ($gersId === null || $lon === null || $lat === null) {
                $rejInvalid++;
                continue;
            }

            // 2) PRIMARY category only. Alternate categories are ignored.
            $primary = $this->primaryCategory($raw);
            $categoryKey = $this->map->mapPrimary($primary);
            if ($categoryKey === null) {
                $rejUnmapped++;
                $token = $primary === null ? '(none)' : strtolower(trim($primary));
                $unmappedTally[$token] = ($unmappedTally[$token] ?? 0) + 1;
                continue;
            }

            // 3) Confidence floor.
            $confidence = $this->confidence($raw);
            if ($confidence === null || $confidence < $this->confidenceMin) {
                $rejLowConf++;
                continue;
            }

            $records[] = new NormalizedPlaceRecord(
                source: OvertureCategoryMap::SOURCE,
                source_ref: $gersId,
                gers_id: $gersId,
                category_key: $categoryKey,
                name: $this->name($raw),
                brand: $this->brand($raw),
                confidence: $confidence,
                source_count: $this->sourceCount($raw),
                lon: $lon,
                lat: $lat,
                geometry_type: 'Point',
            );
        }

        return new NormalizationResult(
            records: $records,
            totalInput: $total,
            rejectedUnmapped: $rejUnmapped,
            rejectedLowConfidence: $rejLowConf,
            rejectedInvalid: $rejInvalid,
            unmappedTally: $unmappedTally,
        );
    }

    /** Overture PRIMARY category token, or null. Alternate is never read. */
    private function primaryCategory(array $raw): ?string
    {
        $categories = $raw['categories'] ?? null;
        if (is_array($categories)) {
            return $this->str($categories['primary'] ?? null);
        }

        return null;
    }

    private function confidence(array $raw): ?float
    {
        if (!array_key_exists('confidence', $raw) || $raw['confidence'] === null) {
            return null;
        }

        return (float) $raw['confidence'];
    }

    /**
     * source_count = number of DISTINCT contributing datasets in `sources[]`
     * (Overture multiplicity signal). Falls back to 1 when sources is absent —
     * a place always has at least the Overture record itself.
     */
    private function sourceCount(array $raw): int
    {
        $sources = $raw['sources'] ?? null;
        if (!is_array($sources) || $sources === []) {
            return 1;
        }

        $datasets = [];
        foreach ($sources as $s) {
            if (is_array($s) && isset($s['dataset']) && $s['dataset'] !== '') {
                $datasets[strtolower((string) $s['dataset'])] = true;
            }
        }

        return $datasets === [] ? 1 : count($datasets);
    }

    /** @return array{0: float|null, 1: float|null} [lon, lat] */
    private function coordinates(array $raw): array
    {
        $geom = $raw['geometry'] ?? null;
        if (is_array($geom) && isset($geom['coordinates']) && is_array($geom['coordinates'])) {
            $c = $geom['coordinates'];
            if (array_key_exists(0, $c) && array_key_exists(1, $c) && is_numeric($c[0]) && is_numeric($c[1])) {
                return [(float) $c[0], (float) $c[1]];
            }
        }

        // Flat fallback shape (lon/lat columns).
        if (is_numeric($raw['lon'] ?? null) && is_numeric($raw['lat'] ?? null)) {
            return [(float) $raw['lon'], (float) $raw['lat']];
        }

        return [null, null];
    }

    private function name(array $raw): ?string
    {
        $names = $raw['names'] ?? null;
        if (is_array($names)) {
            return $this->str($names['primary'] ?? null);
        }

        return $this->str($raw['name'] ?? null);
    }

    private function brand(array $raw): ?string
    {
        $brand = $raw['brand'] ?? null;
        if (is_array($brand)) {
            $names = $brand['names'] ?? null;
            if (is_array($names)) {
                return $this->str($names['primary'] ?? null);
            }

            return $this->str($brand['wikidata'] ?? null);
        }

        return $this->str($brand);
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
