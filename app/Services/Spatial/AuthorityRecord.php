<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C1 (cross-source authority linking).
 *
 * Immutable DTO for one authority-source record (CMS, NCES, PAD-US, USGS, FAA, GTFS, …) staged for
 * linking against the owned `places` corpus. The offline counterpart of an authority staging row;
 * it carries only what the linker needs — identity, a name to match on, a point, and the
 * authority's own metric (`authority_metric`, e.g. CMS stars / PAD-US acreage / NTD ridership).
 *
 * It deliberately does NOT model PostGIS geometry — geometry is plain lon/lat degrees (EPSG:4326).
 * Mirrors NormalizedPlaceRecord (Batch 2A); toArray()/fromArray() round-trip exactly.
 *
 * @see \App\Services\Spatial\AuthorityLinkMatcher
 */
final class AuthorityRecord
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(
        public readonly string $authority_source,
        public readonly string $authority_ref,
        public readonly ?string $name,
        public readonly float $lon,
        public readonly float $lat,
        public readonly ?float $authority_metric = null,
    ) {
    }

    /** Canonical NDJSON shape. Key order is the wire format — do not reorder. */
    public function toArray(): array
    {
        return [
            'authority_source' => $this->authority_source,
            'authority_ref'    => $this->authority_ref,
            'name'             => $this->name,
            'lon'              => $this->lon,
            'lat'              => $this->lat,
            'authority_metric' => $this->authority_metric,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['authority_source', 'authority_ref', 'lon', 'lat'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new \InvalidArgumentException(
                    "AuthorityRecord::fromArray() missing required key [{$required}]."
                );
            }
        }

        return new self(
            authority_source: (string) $data['authority_source'],
            authority_ref: (string) $data['authority_ref'],
            name: isset($data['name']) ? (string) $data['name'] : null,
            lon: (float) $data['lon'],
            lat: (float) $data['lat'],
            authority_metric: isset($data['authority_metric']) ? (float) $data['authority_metric'] : null,
        );
    }

    /**
     * Parse an NDJSON string into records (blank lines ignored).
     *
     * @return list<self>
     */
    public static function fromNdjson(string $ndjson): array
    {
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $ndjson) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $records[] = self::fromArray($decoded);
        }

        return $records;
    }

    /** @return list<self> */
    public static function readFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Authority extract not found: {$path}");
        }

        return self::fromNdjson((string) file_get_contents($path));
    }

    /**
     * Canonical NDJSON for a set of records, ordered by (authority_source, authority_ref) so the
     * output is deterministic regardless of input order.
     *
     * @param iterable<self> $records
     */
    public static function toNdjson(iterable $records): string
    {
        $arr = is_array($records) ? array_values($records) : iterator_to_array($records, false);
        usort($arr, static fn (self $a, self $b): int => [$a->authority_source, $a->authority_ref] <=> [$b->authority_source, $b->authority_ref]);

        if ($arr === []) {
            return '';
        }

        $lines = array_map(static fn (self $r): string => json_encode($r->toArray(), self::JSON_FLAGS), $arr);

        return implode("\n", $lines) . "\n";
    }
}
