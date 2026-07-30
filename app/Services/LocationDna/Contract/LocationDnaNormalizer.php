<?php

namespace App\Services\LocationDna\Contract;

/**
 * LocationDnaNormalizer — pure canonicalisation of already-authored dimension values.
 *
 * G1c contract core. INERT. Approved by D-G1-1 (option 1-B) and D-G1-3.
 *
 * WHAT IT NEVER DOES (D-G1-1 / D-G1-2 approved clarifications)
 * -----------------------------------------------------------
 *   - never infers user mutation intent — that is the command layer's job
 *   - never merges legacy mirrors — that is LegacyMirrorAdapter's, not created here
 *   - never turns omission into clear
 *   - never turns null into a canonical authored value — null throws
 *   - never silently accepts malformed geometry — it throws instead
 *   - never applies a public projection — PublicGeometryProjection stays separate
 *
 * ORDERING RULES (D-G1-3 approved)
 * --------------------------------
 * Polygon VERTEX order is semantically meaningful and is therefore PRESERVED — vertices are
 * never sorted, because two polygons sharing a vertex set in different order are different
 * shapes. Polygon COLLECTION order and radius-search COLLECTION order are approved as NOT
 * meaningful, so both are normalised deterministically.
 *
 * Every method is pure: inputs are taken by value and returned as new values.
 */
final class LocationDnaNormalizer
{
    /** Metres per mile, per §5.1's measured conversion constant. */
    public const METRES_PER_MILE = 1609.34;

    /**
     * Normalise one authored value for one dimension.
     *
     * @throws LocationDnaContractException on null, malformed shape or invalid geometry
     */
    public function normalize(Dimension $dimension, mixed $value): mixed
    {
        if ($value === null) {
            throw LocationDnaContractException::authoredNull($dimension->value);
        }

        return match ($dimension->kind()) {
            DimensionKind::StringList => $this->normalizeStringList($dimension, $value),
            DimensionKind::ObjectList => $dimension === Dimension::Polygons
                ? $this->normalizePolygons($value)
                : $this->normalizeRadiusSearches($value),
            DimensionKind::Text => $this->normalizeText($dimension, $value),
            DimensionKind::Flag => $this->normalizeFlag($dimension, $value),
            DimensionKind::Map  => $this->normalizeMap($dimension, $value),
        };
    }

    /** Normalise every present dimension of a raw canonical array. Unknown keys are ignored. */
    public function normalizeAll(array $dimensions): array
    {
        $out = [];

        foreach ($dimensions as $key => $value) {
            $dimension = Dimension::tryFromKey((string) $key);

            if ($dimension === null) {
                continue;
            }

            $out[$dimension->value] = $this->normalize($dimension, $value);
        }

        ksort($out);

        return $out;
    }

    // ── administrative labels ────────────────────────────────────────────────

    /**
     * Administrative label lists: trim, drop blanks, de-duplicate, sort deterministically.
     *
     * Order carries no meaning for a set of place names, so it is normalised — which also
     * makes the revision token insensitive to the order the editor happened to emit.
     * De-duplication is case-sensitive on purpose: "Tampa" and "tampa" are not provably the
     * same published place name, and silently collapsing them would be a data decision this
     * layer is not entitled to make.
     */
    private function normalizeStringList(Dimension $dimension, mixed $value): array
    {
        if (! is_array($value)) {
            throw LocationDnaContractException::invalidDimensionValue(
                $dimension->value,
                'expected a list of strings, got '.get_debug_type($value).'.',
            );
        }

        $clean = [];

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                throw LocationDnaContractException::invalidDimensionValue(
                    $dimension->value,
                    'every entry must be a string, found '.get_debug_type($entry).'.',
                );
            }

            $trimmed = trim($entry);

            if ($trimmed !== '') {
                $clean[$trimmed] = true;
            }
        }

        $clean = array_keys($clean);
        sort($clean, SORT_STRING);

        return $clean;
    }

    // ── text ─────────────────────────────────────────────────────────────────

    /**
     * Text dimensions: trim only. Content is preserved verbatim.
     *
     * `location_notes` is private canonical free text (§5.1, D5) and is NEVER truncated,
     * summarised or heuristically sanitised.
     *
     * An empty (or whitespace-only) result is significant: trimming to '' produces this
     * dimension's canonical empty. D-G1-2 forbids an empty string silently standing in for
     * clear, which is enforced at the boundary that matters — {@see DimensionCommand::set()}
     * rejects a canonical-empty set. Normalisation itself is allowed to yield '' because a
     * hydrated stored document may legitimately already be cleared.
     */
    private function normalizeText(Dimension $dimension, mixed $value): string
    {
        if (! is_string($value)) {
            throw LocationDnaContractException::invalidDimensionValue(
                $dimension->value,
                'expected a string, got '.get_debug_type($value).'.',
            );
        }

        return trim($value);
    }

    private function normalizeFlag(Dimension $dimension, mixed $value): bool
    {
        if (! is_bool($value)) {
            throw LocationDnaContractException::invalidDimensionValue(
                $dimension->value,
                'expected a boolean, got '.get_debug_type($value).'.',
            );
        }

        return $value;
    }

    private function normalizeMap(Dimension $dimension, mixed $value): array
    {
        if (! is_array($value)) {
            throw LocationDnaContractException::invalidDimensionValue(
                $dimension->value,
                'expected an object/map, got '.get_debug_type($value).'.',
            );
        }

        ksort($value);

        return $value;
    }

    // ── geometry ─────────────────────────────────────────────────────────────

    /**
     * Polygons: validate structure, PRESERVE vertex order, normalise collection order.
     *
     * A path-less polygon is rejected — G1b F-G1B-4 recorded that no consumer validates
     * shape today and that a path-less polygon reaches renderers unchecked. D-G1-1's
     * approved reject-or-quarantine rule makes that this layer's responsibility.
     */
    private function normalizePolygons(mixed $value): array
    {
        if (! is_array($value)) {
            throw LocationDnaContractException::invalidGeometry(
                Dimension::Polygons->value,
                'expected a list of polygons, got '.get_debug_type($value).'.',
            );
        }

        $polygons = [];

        foreach ($value as $polygon) {
            if (! is_array($polygon)) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::Polygons->value,
                    'each polygon must be an object, found '.get_debug_type($polygon).'.',
                );
            }

            if (! array_key_exists('path', $polygon) || ! is_array($polygon['path']) || $polygon['path'] === []) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::Polygons->value,
                    'a polygon must carry a non-empty `path`; a path-less polygon is not valid canonical geometry.',
                );
            }

            // Vertex order is meaningful (D-G1-3) — validated in place, never sorted.
            $path = [];

            foreach ($polygon['path'] as $vertex) {
                $path[] = $this->normalizeCoordinate(Dimension::Polygons, $vertex);
            }

            $normalized         = $polygon;
            $normalized['path'] = $path;

            if (array_key_exists('label', $normalized)) {
                if (! is_string($normalized['label'])) {
                    throw LocationDnaContractException::invalidGeometry(
                        Dimension::Polygons->value,
                        'a polygon `label` must be a string when present.',
                    );
                }

                $normalized['label'] = trim($normalized['label']);
            }

            ksort($normalized);
            $polygons[] = $normalized;
        }

        return $this->sortCollectionDeterministically($polygons);
    }

    /**
     * Radius searches: validate centre and radius, normalise collection order.
     *
     * A centre-less entry is rejected: without coordinates the renderer has a centre-less
     * circle to draw and matching has nothing to bound.
     */
    private function normalizeRadiusSearches(mixed $value): array
    {
        if (! is_array($value)) {
            throw LocationDnaContractException::invalidGeometry(
                Dimension::RadiusSearches->value,
                'expected a list of radius searches, got '.get_debug_type($value).'.',
            );
        }

        $entries = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::RadiusSearches->value,
                    'each radius search must be an object, found '.get_debug_type($entry).'.',
                );
            }

            if (! array_key_exists('lat', $entry) || ! array_key_exists('lng', $entry)) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::RadiusSearches->value,
                    'a radius search must carry both `lat` and `lng`; a centre-less entry is not valid canonical geometry.',
                );
            }

            $normalized = $entry;
            $centre     = $this->normalizeCoordinate(Dimension::RadiusSearches, ['lat' => $entry['lat'], 'lng' => $entry['lng']]);

            $normalized['lat'] = $centre['lat'];
            $normalized['lng'] = $centre['lng'];

            if (! array_key_exists('radius_miles', $normalized)) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::RadiusSearches->value,
                    'a radius search must carry `radius_miles`; radius is expressed in MILES (§5.1).',
                );
            }

            $radius = $normalized['radius_miles'];

            if (is_string($radius) && is_numeric($radius)) {
                $radius = (float) $radius;
            }

            if (! is_int($radius) && ! is_float($radius)) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::RadiusSearches->value,
                    '`radius_miles` must be numeric, got '.get_debug_type($normalized['radius_miles']).'.',
                );
            }

            if ($radius <= 0) {
                throw LocationDnaContractException::invalidGeometry(
                    Dimension::RadiusSearches->value,
                    '`radius_miles` must be greater than zero.',
                );
            }

            $normalized['radius_miles'] = (float) $radius;

            foreach (['address', 'label'] as $textKey) {
                if (array_key_exists($textKey, $normalized)) {
                    if (! is_string($normalized[$textKey])) {
                        throw LocationDnaContractException::invalidGeometry(
                            Dimension::RadiusSearches->value,
                            "`{$textKey}` must be a string when present.",
                        );
                    }

                    $normalized[$textKey] = trim($normalized[$textKey]);
                }
            }

            ksort($normalized);
            $entries[] = $normalized;
        }

        return $this->sortCollectionDeterministically($entries);
    }

    /** @return array{lat: float, lng: float} */
    private function normalizeCoordinate(Dimension $dimension, mixed $vertex): array
    {
        if (! is_array($vertex) || ! array_key_exists('lat', $vertex) || ! array_key_exists('lng', $vertex)) {
            throw LocationDnaContractException::invalidGeometry(
                $dimension->value,
                'every coordinate must be an object carrying `lat` and `lng`.',
            );
        }

        $lat = $vertex['lat'];
        $lng = $vertex['lng'];

        foreach (['lat' => $lat, 'lng' => $lng] as $name => $raw) {
            if (is_string($raw) && is_numeric($raw)) {
                continue;
            }

            if (! is_int($raw) && ! is_float($raw)) {
                throw LocationDnaContractException::invalidGeometry(
                    $dimension->value,
                    "coordinate `{$name}` must be numeric, got ".get_debug_type($raw).'.',
                );
            }
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90.0 || $lat > 90.0) {
            throw LocationDnaContractException::invalidGeometry($dimension->value, 'latitude out of range.');
        }

        if ($lng < -180.0 || $lng > 180.0) {
            throw LocationDnaContractException::invalidGeometry($dimension->value, 'longitude out of range.');
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Deterministic collection ordering for a dimension whose entry order carries no meaning.
     *
     * Sorts by the canonical JSON of each entry, so the result depends only on content. Used
     * for the `polygons` and `radius_searches` COLLECTIONS — never for a polygon's vertices.
     */
    private function sortCollectionDeterministically(array $entries): array
    {
        usort(
            $entries,
            static fn (array $a, array $b): int => strcmp(
                (string) json_encode($a, JSON_UNESCAPED_UNICODE),
                (string) json_encode($b, JSON_UNESCAPED_UNICODE),
            ),
        );

        return $entries;
    }
}
