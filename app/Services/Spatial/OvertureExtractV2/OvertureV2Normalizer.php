<?php

namespace App\Services\Spatial\OvertureExtractV2;

use App\Services\Spatial\OvertureTaxonomyMapV2;

/**
 * `overture-extract-v2`: raw bounding-box rows in, two lanes and a complete rejection tally out.
 * Pure and deterministic — no database, no network, no file access, no clock. The caller streams
 * rows in; the same rows in any order produce the same result.
 *
 * CHECKS, IN ORDER (each row gets exactly one outcome):
 *   1. `id` missing / blank                                  → missing_source_id
 *   2. `id` seen more than once in the input                 → duplicate_source_id (every copy)
 *   3. a text field that is not text / null                  → malformed_field
 *   4. geometry not a finite WGS84 POINT                     → malformed_geometry
 *   5. point outside the recipe box                          → outside_bounding_box
 *   6. confidence missing / not a finite number in [0, 1]    → malformed_confidence
 *   7. confidence < floor (the floor itself is eligible)     → confidence_below_floor
 *   8. status `permanently_closed` / any unmapped status     → status_permanently_closed / status_unrecognised
 *   9. `taxonomy.primary` ∈ the 16 import tokens             → BASE, with its canonical key
 *  10. otherwise, selected by the diagnostic selector        → SUPPLEMENTARY (no canonical key)
 *      else                                                  → taxonomy_null / taxonomy_not_allowlisted
 *
 * `taxonomy.primary` alone classifies. `categories.primary` and `basic_category` are carried as
 * provenance and are never read as a category: no fallback, so a null or unlisted token can
 * never become a corpus row by another field's say-so. The raw projection is a fixed field set; a
 * row with a missing or extra field means the SQL drifted and aborts the run.
 */
final class OvertureV2Normalizer
{
    /** The exact projection of scripts/overture-v2/extract_bbox_raw.sql. */
    public const RAW_FIELDS = [
        'id', 'name', 'brand_name', 'brand_wikidata', 'taxonomy_primary', 'categories_primary', 'basic_category',
        'operating_status', 'confidence', 'geometry_type', 'lon', 'lat',
        'address_freeform', 'address_locality', 'address_postcode', 'address_region', 'address_country',
    ];

    private const TEXT_FIELDS = [
        'name', 'brand_name', 'brand_wikidata', 'taxonomy_primary', 'categories_primary', 'basic_category',
        'operating_status', 'geometry_type',
        'address_freeform', 'address_locality', 'address_postcode', 'address_region', 'address_country',
    ];

    private readonly OvertureTaxonomyMapV2 $taxonomy;

    public function __construct(private readonly OvertureExtractV2Config $config)
    {
        $this->taxonomy = new OvertureTaxonomyMapV2();
    }

    public function config(): OvertureExtractV2Config
    {
        return $this->config;
    }

    /**
     * @param iterable<array<string, mixed>> $rows
     */
    public function run(iterable $rows): OvertureV2ExtractResult
    {
        $outcomes = [];      // id => record|rejection code, for ids seen once so far
        $duplicates = [];    // id => occurrence count, for ids seen more than once
        $missingId = 0;
        $input = 0;

        foreach ($rows as $row) {
            $input++;
            if (! is_array($row)) {
                throw new InvalidOvertureExtractV2("row {$input} is not an object");
            }
            $this->assertShape($row, $input);

            $id = $row['id'];
            if (! is_string($id) || trim($id) === '') {
                $missingId++;
                continue;
            }
            if (isset($duplicates[$id])) {
                $duplicates[$id]++;
                continue;
            }
            if (array_key_exists($id, $outcomes)) {
                unset($outcomes[$id]);
                $duplicates[$id] = 2;
                continue;
            }
            $outcomes[$id] = $this->classify($row);
        }

        return OvertureV2ExtractResult::fromOutcomes(
            $this->config,
            $input,
            $missingId,
            array_sum($duplicates),
            count($duplicates),
            $outcomes,
        );
    }

    /**
     * Steps 3–10 for one row whose id is present and unique.
     *
     * @return OvertureV2Record|string a record, or an {@see OvertureV2Rejection} code
     */
    public function classify(array $row): OvertureV2Record|string
    {
        foreach (self::TEXT_FIELDS as $field) {
            if ($row[$field] !== null && ! is_string($row[$field])) {
                return OvertureV2Rejection::MALFORMED_FIELD;
            }
        }

        $lon = $row['lon'];
        $lat = $row['lat'];
        if ($row['geometry_type'] !== 'POINT'
            || ! (is_int($lon) || is_float($lon)) || ! (is_int($lat) || is_float($lat))
            || ! is_finite((float) $lon) || ! is_finite((float) $lat)
            || $lon < -180 || $lon > 180 || $lat < -90 || $lat > 90
        ) {
            return OvertureV2Rejection::MALFORMED_GEOMETRY;
        }
        $lon = (float) $lon;
        $lat = (float) $lat;
        $c = $this->config;
        if ($lon < $c->west || $lon > $c->east || $lat < $c->south || $lat > $c->north) {
            return OvertureV2Rejection::OUTSIDE_BOUNDING_BOX;
        }

        $confidence = $row['confidence'];
        if (! (is_int($confidence) || is_float($confidence)) || ! is_finite((float) $confidence)
            || $confidence < 0 || $confidence > 1
        ) {
            return OvertureV2Rejection::MALFORMED_CONFIDENCE;
        }
        $confidence = (float) $confidence;
        if ($confidence < $c->confidenceMin) {
            return OvertureV2Rejection::CONFIDENCE_BELOW_FLOOR;
        }

        $status = $row['operating_status'];
        if ($status === null) {
            if (! $c->nullStatusEligible) {
                return OvertureV2Rejection::STATUS_UNRECOGNISED;
            }
            $statusKnown = false;
        } elseif (in_array($status, $c->eligibleStatuses, true)) {
            $statusKnown = true;
        } elseif (in_array($status, $c->excludedStatuses, true)) {
            return $status === 'permanently_closed'
                ? OvertureV2Rejection::STATUS_PERMANENTLY_CLOSED
                : OvertureV2Rejection::STATUS_UNRECOGNISED;
        } else {
            return OvertureV2Rejection::STATUS_UNRECOGNISED;
        }

        $token = $row['taxonomy_primary'];
        $categoryKey = $this->taxonomy->mapSource($token);
        if ($categoryKey !== null) {
            return $this->record($row, OvertureV2Record::LANE_BASE, null, [], OvertureV2Record::POLICY_CORPUS,
                $categoryKey, $confidence, $statusKnown, $lon, $lat, OvertureV2Record::ELIGIBILITY_BASE, null);
        }

        if (! $this->selected($row)) {
            return ($token === null || trim($token) === '')
                ? OvertureV2Rejection::TAXONOMY_NULL
                : OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED;
        }

        // Candidacy is by TOKEN, never by name: a candidate is `pending` until the census runs the
        // chain matcher and records `admitted` or `refused` (OvertureV2Record, "RESCUE IS DECIDED").
        $lanes = $c->rescueLanesForToken($token);

        return $this->record(
            $row,
            OvertureV2Record::LANE_SUPPLEMENTARY,
            $lanes === [] ? OvertureV2Record::ROLE_DIAGNOSTIC : OvertureV2Record::ROLE_RESCUE_CANDIDATE,
            $lanes,
            $lanes === [] ? OvertureV2Record::POLICY_MATCHER_ONLY : OvertureV2Record::POLICY_PENDING_RESCUE_VERDICT,
            null,
            $confidence,
            $statusKnown,
            $lon,
            $lat,
            OvertureV2Record::ELIGIBILITY_SUPPLEMENTARY,
            $lanes === [] ? OvertureV2Record::RESCUE_NOT_CANDIDATE : OvertureV2Record::RESCUE_PENDING,
        );
    }

    /** The census diagnostic selector: a chain-like name/brand, or any brand QID. */
    private function selected(array $row): bool
    {
        // A blank QID is not a QID.
        if ($this->config->selectorAnyBrandWikidata && $row['brand_wikidata'] !== null && trim($row['brand_wikidata']) !== '') {
            return true;
        }
        $subject = mb_strtolower(($row['name'] ?? '') . ' ' . ($row['brand_name'] ?? ''), 'UTF-8');
        foreach ($this->config->selectorPatterns as $pattern) {
            if (preg_match($pattern, $subject) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $lanes */
    private function record(
        array $row,
        string $lane,
        ?string $role,
        array $lanes,
        string $policy,
        ?string $categoryKey,
        float $confidence,
        bool $statusKnown,
        float $lon,
        float $lat,
        string $eligibility,
        ?string $rescueVerdict,
    ): OvertureV2Record {
        return new OvertureV2Record(
            $lane,
            $role,
            $lanes,
            $policy,
            $row['id'],
            $this->config->release,
            $this->config->recipeVersion,
            OvertureTaxonomyMapV2::VERSION,
            $row['taxonomy_primary'],
            $categoryKey,
            $row['categories_primary'],
            $row['basic_category'],
            $row['name'],
            $row['brand_name'],
            $row['brand_wikidata'],
            $confidence,
            $row['operating_status'],
            $statusKnown,
            $lon,
            $lat,
            'POINT',
            [
                'freeform' => $row['address_freeform'],
                'locality' => $row['address_locality'],
                'postcode' => $row['address_postcode'],
                'region' => $row['address_region'],
                'country' => $row['address_country'],
            ],
            $eligibility,
            $rescueVerdict,
        );
    }

    private function assertShape(array $row, int $n): void
    {
        $keys = array_keys($row);
        if (count($keys) !== count(self::RAW_FIELDS) || array_diff(self::RAW_FIELDS, $keys) !== []) {
            $missing = implode(', ', array_diff(self::RAW_FIELDS, $keys));
            $extra = implode(', ', array_diff($keys, self::RAW_FIELDS));
            throw new InvalidOvertureExtractV2("row {$n} does not match the recipe projection (missing: [{$missing}], extra: [{$extra}])");
        }
    }
}
