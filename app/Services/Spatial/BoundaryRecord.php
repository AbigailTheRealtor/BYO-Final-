<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * Immutable DTO for one normalized boundary staged for the `boundaries` table (SSOT §7.2). The
 * offline counterpart of a boundaries staging row; it carries the identity (`kind`, `external_ref`),
 * the canonical GeoJSON MultiPolygon `geometry`, and a `attrs` jsonb payload.
 *
 * Owner decisions:
 *   • Geometry is canonical GeoJSON MultiPolygon (never WKT) in the DTO, NDJSON, and fixtures. WKT/
 *     EWKT is produced only at materialization time by {@see BoundaryRowMaterializer}.
 *   • NO centroid field — `boundaries` / `boundaries_parts` do not require one; any future centroid
 *     is authored in Class-2 SQL (PostGIS), never synthesized offline.
 *   • PAD-US acreage lives ONLY in `attrs['acres']`; no place row, no `places.authority_metric`.
 *
 * toArray()/fromArray() round-trip exactly; toNdjson() is deterministically ordered by
 * (kind, external_ref) so output is stable regardless of input order.
 *
 * @see \App\Services\Spatial\PadUsBoundarySource
 * @see \Tests\Unit\Spatial\BoundaryRecordTest
 */
final class BoundaryRecord
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param array{type:string,coordinates:array} $geometry canonical GeoJSON MultiPolygon
     * @param array<string,mixed>                   $attrs    jsonb payload (e.g. acres, name, source)
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $external_ref,
        public readonly array $geometry,
        public readonly array $attrs = [],
    ) {
    }

    /** Canonical NDJSON shape. Key order is the wire format — do not reorder. */
    public function toArray(): array
    {
        return [
            'kind'         => $this->kind,
            'external_ref' => $this->external_ref,
            'geometry'     => $this->geometry,
            'attrs'        => $this->attrs,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['kind', 'geometry'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new \InvalidArgumentException(
                    "BoundaryRecord::fromArray() missing required key [{$required}]."
                );
            }
        }

        return new self(
            kind: (string) $data['kind'],
            external_ref: isset($data['external_ref']) ? (string) $data['external_ref'] : null,
            geometry: (array) $data['geometry'],
            attrs: isset($data['attrs']) && is_array($data['attrs']) ? $data['attrs'] : [],
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
            throw new \RuntimeException("Boundary extract not found: {$path}");
        }

        return self::fromNdjson((string) file_get_contents($path));
    }

    /**
     * Canonical NDJSON for a set of records, ordered by (kind, external_ref) so output is
     * deterministic regardless of input order.
     *
     * @param iterable<self> $records
     */
    public static function toNdjson(iterable $records): string
    {
        $arr = is_array($records) ? array_values($records) : iterator_to_array($records, false);
        usort($arr, static fn (self $a, self $b): int =>
            [$a->kind, (string) $a->external_ref] <=> [$b->kind, (string) $b->external_ref]);

        if ($arr === []) {
            return '';
        }

        $lines = array_map(static fn (self $r): string => json_encode($r->toArray(), self::JSON_FLAGS), $arr);

        return implode("\n", $lines) . "\n";
    }
}
