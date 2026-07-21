<?php

namespace App\Services\Spatial\Overlay;

use App\Services\Spatial\AuthorityOverlayNormalizationResult;
use App\Services\Spatial\AuthorityOverlaySource;
use App\Services\Spatial\AuthorityRecord;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * Reference OVERLAY importer: CMS Hospital Overall Star Rating → {@see AuthorityRecord}. The
 * emitted records are the input to the Batch 2D Part C1 linker (`corpus:link-authority`), which
 * matches each CMS hospital to its Overture place and populates `place_authority_links` +
 * `places.authority_metric` (SSOT §8.2 — "Overture carries no CCN … fuzzy name + spatial matching").
 *
 * Contract (SSOT §7.2/§8.1/§14.1; owner decisions):
 *   • authority_source = 'cms'; authority_ref = the CCN (Facility ID) — the registry natural key.
 *   • authority_metric = the Hospital overall rating in [1,5].
 *   • D3 — a row whose star rating is absent / "Not Available" / non-numeric is KEPT with
 *     authority_metric = NULL: identity is authoritative even when CMS suppresses the rating for low
 *     volume. Only structurally-invalid rows (missing CCN / name / coordinates) are rejected.
 *   • D4 — a numeric rating OUTSIDE [1,5] is rejected (out-of-domain), never clamped.
 *   • D5 — the CMS star file carries an ADDRESS, not coordinates; sourcing lon/lat (geocode, or a
 *     coordinate-bearing CMS/POS extract) is a Class-2 concern. The offline adapter consumes rows
 *     that already carry lon/lat (the synthetic fixture supplies them).
 *
 * Pure and deterministic — no DB, no network, no geocoding.
 *
 * @see \Tests\Unit\Spatial\CmsHospitalOverlaySourceTest
 */
final class CmsHospitalOverlaySource implements AuthorityOverlaySource
{
    private const REF_KEYS   = ['ccn', 'facility_id', 'provider_id'];
    private const NAME_KEYS  = ['facility_name', 'hospital_name', 'name'];
    private const STAR_KEYS  = ['hospital_overall_rating', 'overall_rating', 'star_rating', 'rating'];
    private const LON_KEYS   = ['lon', 'longitude', 'lng', 'x'];
    private const LAT_KEYS   = ['lat', 'latitude', 'y'];

    public function sourceKey(): string
    {
        return 'cms';
    }

    public function target(): string
    {
        return 'link';
    }

    public function metricLabel(): ?string
    {
        return 'cms_overall_star_rating';
    }

    /** @return array{0: float, 1: float} */
    public function metricDomain(): array
    {
        return [1.0, 5.0];
    }

    public function normalize(iterable $rawRows): AuthorityOverlayNormalizationResult
    {
        $records = [];
        $total = 0;
        $rejInvalid = 0;
        $rejOutOfDomain = 0;
        $reasons = [];

        foreach ($rawRows as $raw) {
            $total++;

            $ref  = $this->firstOf($raw, self::REF_KEYS);
            $name = $this->firstOf($raw, self::NAME_KEYS);
            [$lon, $lat] = $this->coordinates($raw);

            if ($ref === null || $name === null || $lon === null || $lat === null) {
                $rejInvalid++;
                $reasons['invalid_missing_field'] = ($reasons['invalid_missing_field'] ?? 0) + 1;
                continue;
            }

            [$metric, $domainError] = $this->rating($raw);
            if ($domainError) {
                $rejOutOfDomain++;
                $reasons['metric_out_of_domain'] = ($reasons['metric_out_of_domain'] ?? 0) + 1;
                continue;
            }

            $records[] = new AuthorityRecord(
                authority_source: $this->sourceKey(),
                authority_ref: $ref,
                name: $name,
                lon: $lon,
                lat: $lat,
                authority_metric: $metric,
            );
        }

        return new AuthorityOverlayNormalizationResult(
            records: $records,
            totalInput: $total,
            rejectedInvalid: $rejInvalid,
            rejectedOutOfDomain: $rejOutOfDomain,
            rejectReasons: $reasons,
        );
    }

    /**
     * Resolve the overall star rating. Returns [metric, domainError].
     * Absent / "Not Available" / non-numeric → [null, false] (kept, no metric).
     * Numeric outside [1,5] → [null, true] (rejected out-of-domain).
     *
     * @return array{0: float|null, 1: bool}
     */
    private function rating(array $raw): array
    {
        $value = null;
        foreach (self::STAR_KEYS as $k) {
            if (array_key_exists($k, $raw) && $raw[$k] !== null) {
                $value = $raw[$k];
                break;
            }
        }

        if ($value === null) {
            return [null, false];
        }
        $s = trim((string) $value);
        if ($s === '' || !is_numeric($s)) {
            // CMS encodes suppressed ratings as "Not Available" — keep identity, drop the metric.
            return [null, false];
        }

        $metric = (float) $s;
        [$min, $max] = $this->metricDomain();
        if ($metric < $min || $metric > $max) {
            return [null, true];
        }

        return [$metric, false];
    }

    /** @param list<string> $keys */
    private function firstOf(array $raw, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw)) {
                $s = $this->str($raw[$k]);
                if ($s !== null) {
                    return $s;
                }
            }
        }

        return null;
    }

    /** @return array{0: float|null, 1: float|null} [lon, lat] */
    private function coordinates(array $raw): array
    {
        $lon = $this->num($raw, self::LON_KEYS);
        $lat = $this->num($raw, self::LAT_KEYS);

        return [$lon, $lat];
    }

    /** @param list<string> $keys */
    private function num(array $raw, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw) && is_numeric($raw[$k])) {
                return (float) $raw[$k];
            }
        }

        return null;
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
