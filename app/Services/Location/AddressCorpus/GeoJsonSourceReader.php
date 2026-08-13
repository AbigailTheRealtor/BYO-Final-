<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\AddressCorpus\Contracts\AddressSourceReader;
use Generator;
use RuntimeException;

/**
 * Streams a GeoJSON FeatureCollection feature by feature, without loading it.
 *
 * WHY THIS IS NOT `json_decode(file_get_contents(...))`
 * ----------------------------------------------------
 * A county address-point export is hundreds of megabytes of nested JSON, and
 * `json_decode` builds the entire structure in memory before returning the first
 * feature. That is not a slow version of this class; it is a different program
 * that fails on the data we intend to read. So the file is scanned as bytes,
 * feature objects are extracted by brace depth, and each one is decoded on its
 * own — peak memory is one feature, whatever the file weighs.
 *
 * The scan tracks string state and escapes, because a `"` inside a street name
 * and a `}` inside a comment field are both ordinary and both would otherwise
 * end a feature early.
 *
 * CRS: FAIL CLOSED, ALWAYS
 * ------------------------
 * This is the dangerous part of reading GIS data, and it is dangerous precisely
 * because the failure looks like success. Hillsborough's authoritative service
 * stores geometry in EPSG:2237 — NAD83 / Florida West, in US survey feet — where
 * a coordinate pair reads roughly `(578000, 1310000)`. Nothing about those two
 * numbers announces that they are not degrees. Interpreted as longitude and
 * latitude they are silently, enormously wrong.
 *
 * This application deliberately contains no reprojection engine: the authoritative
 * ArcGIS services export WGS84 directly with `outSR=4326`, and a projection
 * library is a large dependency to add for a problem the publisher already
 * solves. So the rule is to refuse anything not demonstrably WGS84:
 *
 *   • A `crs` member naming anything other than CRS84 / EPSG:4326 → refuse.
 *   • No `crs` member → RFC 7946 mandates WGS84, which is accepted.
 *   • The first feature's coordinates outside degree range → refuse, naming
 *     projected coordinates as the likely cause.
 *
 * The first-feature check is what actually catches State Plane, because an
 * ArcGIS export in 2237 carries no `crs` member either. After the first feature
 * the container is established, so a later out-of-range pair is one bad row and
 * becomes a normal counted reject rather than stopping the run.
 *
 * NO NETWORK. NO DATABASE.
 * ------------------------
 * Reads a caller-supplied local path and nothing else. It cannot fetch a service,
 * and there is no persistence code on this path to review.
 */
final class GeoJsonSourceReader implements AddressSourceReader
{
    /** Injected onto every row so a normalizer sees geometry as ordinary fields. */
    public const LATITUDE  = '__latitude';
    public const LONGITUDE = '__longitude';

    /** CRS identifiers that mean WGS84 lon/lat, folded to lowercase. */
    private const WGS84_CRS = [
        'urn:ogc:def:crs:ogc:1.3:crs84',
        'urn:ogc:def:crs:ogc::crs84',
        'urn:ogc:def:crs:epsg::4326',
        'crs84',
        'epsg:4326',
        '4326',
    ];

    private const CHUNK = 65536;

    /** How much of the head is inspected for the envelope. */
    private const ENVELOPE_BYTES = 65536;

    /** Guard against scanning a whole file looking for a `features` key. */
    private const MAX_PREAMBLE = 1048576;

    private bool $firstFeatureChecked = false;

    public function __construct(private readonly string $path)
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("Source file not found: {$this->path}");
        }
    }

    public function describe(): string
    {
        return 'GeoJSON FeatureCollection (streamed, WGS84 required)';
    }

    /**
     * Every feature as a flat array: its properties plus `__latitude`/`__longitude`.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $this->assertEnvelope();

        $handle = $this->open();
        $this->firstFeatureChecked = false;

        try {
            $window     = '';
            $inFeatures = false;
            $awaiting   = false;   // saw "features", waiting for ':' then '['
            $collecting = false;
            $depth      = 0;
            $buffer     = '';
            $inString   = false;
            $escaped    = false;
            $scanned    = 0;

            while (! feof($handle)) {
                $chunk = fread($handle, self::CHUNK);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $length = strlen($chunk);

                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];

                    if (! $inFeatures) {
                        if ($awaiting) {
                            if ($char === ':' || $char === ' ' || $char === "\n" || $char === "\r" || $char === "\t") {
                                continue;
                            }

                            if ($char === '[') {
                                $inFeatures = true;
                                $awaiting   = false;

                                continue;
                            }

                            // "features" appeared somewhere that was not the key.
                            $awaiting = false;
                        }

                        $scanned++;

                        if ($scanned > self::MAX_PREAMBLE) {
                            throw new RuntimeException(
                                'No "features" array found in the first megabyte of ' . basename($this->path)
                                . ' — this does not look like a GeoJSON FeatureCollection.'
                            );
                        }

                        // Rolling window, so the preamble never accumulates.
                        $window = substr($window . $char, -10);

                        if ($window === '"features"') {
                            $awaiting = true;
                            $window   = '';
                        }

                        continue;
                    }

                    if (! $collecting) {
                        if ($char === '{') {
                            $collecting = true;
                            $depth      = 1;
                            $buffer     = '{';
                        } elseif ($char === ']') {
                            return;   // end of the features array
                        }

                        continue;
                    }

                    $buffer .= $char;

                    if ($inString) {
                        if ($escaped) {
                            $escaped = false;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === '"') {
                            $inString = false;
                        }

                        continue;
                    }

                    if ($char === '"') {
                        $inString = true;
                    } elseif ($char === '{') {
                        $depth++;
                    } elseif ($char === '}') {
                        $depth--;

                        if ($depth === 0) {
                            yield $this->flatten($buffer);

                            $collecting = false;
                            $buffer     = '';
                        }
                    }
                }
            }

            if ($collecting) {
                throw new RuntimeException(
                    'Truncated GeoJSON: ' . basename($this->path) . ' ended inside a feature.'
                );
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Whether the envelope is a FeatureCollection in a CRS we accept.
     *
     * Reported rather than thrown so the command can render the same
     * "schema does not match" failure it renders for a renamed NAD header. The
     * property names mirror the tabular reader's so callers need no special case.
     *
     * @return array{ok: bool, missing_required: list<string>, missing_required_groups: list<string>, missing_optional: list<string>, header: list<string>}
     */
    public function assertSchema(): array
    {
        $missing = [];
        $header  = [];

        try {
            $this->assertEnvelope();
            // Only sampled once the envelope is trusted. Reading a feature out of
            // a file we have already refused would re-raise the same failure and
            // turn a reported problem into an uncaught one.
            $header = $this->sampleKeys();
        } catch (RuntimeException $e) {
            $missing[] = $e->getMessage();
        }

        return [
            'ok'                      => $missing === [],
            'missing_required'        => $missing,
            'missing_required_groups' => [],
            'missing_optional'        => [],
            'header'                  => $header,
        ];
    }

    /**
     * The property names on the first feature, for the report's "Found:" line.
     *
     * @return list<string>
     */
    public function sampleKeys(): array
    {
        foreach ($this->rows() as $row) {
            return array_values(array_filter(
                array_keys($row),
                static fn (string $k): bool => $k !== self::LATITUDE && $k !== self::LONGITUDE
            ));
        }

        return [];
    }

    /** Refuses anything that is not a WGS84 FeatureCollection. */
    private function assertEnvelope(): void
    {
        $handle = $this->open();

        try {
            $head = (string) fread($handle, self::ENVELOPE_BYTES);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! preg_match('/"type"\s*:\s*"FeatureCollection"/i', $head)) {
            throw new RuntimeException(
                'Not a GeoJSON FeatureCollection: ' . basename($this->path)
                . ' does not declare "type":"FeatureCollection" in its opening bytes.'
            );
        }

        // A `crs` member is pre-RFC-7946 and is the only place a file states a
        // projection. If one is present it must name WGS84; if absent, RFC 7946
        // mandates WGS84 and the first-feature check below is the real guard.
        if (preg_match('/"crs"\s*:\s*\{.*?"name"\s*:\s*"([^"]+)"/is', $head, $m)) {
            $named = strtolower(trim($m[1]));

            if (! in_array($named, self::WGS84_CRS, true)) {
                throw new RuntimeException(
                    "Refusing a non-WGS84 source: {$named}. Export the layer with outSR=4326 — this "
                    . 'application does not reproject, and reading projected coordinates as degrees '
                    . 'would place every address silently and enormously wrong.'
                );
            }
        }
    }

    /**
     * One decoded feature → a flat row of properties plus its point.
     *
     * @return array<string, mixed>
     */
    private function flatten(string $json): array
    {
        $feature = json_decode($json, true);

        if (! is_array($feature)) {
            throw new RuntimeException('Malformed GeoJSON feature: ' . json_last_error_msg());
        }

        $geometry = $feature['geometry'] ?? null;

        if (! is_array($geometry)) {
            throw new RuntimeException('GeoJSON feature has no geometry — an address corpus needs a point.');
        }

        $type = (string) ($geometry['type'] ?? '');

        // Deliberately strict. A parcel polygon or a street segment in an address
        // feed is not a point we may take a centroid of and call an address — the
        // caller asked for address points, and silently degrading the geometry is
        // exactly how a parcel centroid ends up labelled as an address point.
        if ($type !== 'Point') {
            throw new RuntimeException(
                "Unsupported geometry [{$type}] — this corpus accepts Point features only."
            );
        }

        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2
            || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
            throw new RuntimeException('GeoJSON Point has no usable [longitude, latitude] pair.');
        }

        $longitude = (float) $coordinates[0];
        $latitude  = (float) $coordinates[1];

        if (! $this->firstFeatureChecked) {
            $this->firstFeatureChecked = true;
            $this->assertPlausibleDegrees($latitude, $longitude);
        }

        $properties = $feature['properties'] ?? [];

        if (! is_array($properties)) {
            $properties = [];
        }

        $properties[self::LATITUDE]  = $latitude;
        $properties[self::LONGITUDE] = $longitude;

        return $properties;
    }

    /**
     * The check that actually catches State Plane.
     *
     * An ArcGIS export in EPSG:2237 carries no `crs` member, so the envelope
     * looks identical to a WGS84 one. What differs is magnitude: feet north of a
     * false origin run to seven digits, and no latitude exceeds 90.
     */
    private function assertPlausibleDegrees(float $latitude, float $longitude): void
    {
        if (abs($latitude) <= 90.0 && abs($longitude) <= 180.0) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing a source whose first coordinate (%.3f, %.3f) is outside WGS84 degree range. '
            . 'This is what a projected coordinate system looks like — EPSG:2237 (Florida West, US feet) '
            . 'is the expected culprit for Tampa Bay data. Re-export the layer with outSR=4326; this '
            . 'application does not reproject.',
            $latitude,
            $longitude
        ));
    }

    /** @return resource */
    private function open()
    {
        $handle = str_ends_with(strtolower($this->path), '.gz')
            ? @gzopen($this->path, 'r')
            : @fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot open source: {$this->path}");
        }

        return $handle;
    }
}
