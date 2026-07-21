<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * Pure transform: a canonical {@see AuthorityRecord} → one `authority_staging` row, in the exact
 * COLUMN ORDER the Class-2 COPY consumes. The offline counterpart of the staging load; it emits PHP
 * scalars, never touches a DB, and is deterministic. Mirrors {@see PlaceRowMaterializer} (2C) and
 * {@see PlaceAuthorityLinkMaterializer} (C1).
 *
 * `authority_staging` is a Class-2 STAGING table authored in the spike SQL manifest
 * (`spikes/phase-2-batch-2d-part-c2-authority-overlay-import/sql/stage_authority_overlay.sql`), NOT
 * a Class-1 migration — the SSOT defines no such table, so nothing is migrated here. The Class-2 SQL
 * builds `geom`/`centroid` geography from `lon`/`lat` with `ST_MakePoint`; this offline row carries
 * the plain degrees, matching how C1's `link_authority.sql` assumes staged coordinates.
 *
 * The SqlManifest drift test asserts the recipe's COPY column list equals this COLUMNS constant.
 *
 * @see \Tests\Unit\Spatial\AuthorityStagingMaterializerTest
 */
final class AuthorityStagingMaterializer
{
    /**
     * COPY target columns, in order — the wire order for the Class-2 stage. Keep stable.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'authority_source',
        'authority_ref',
        'name',
        'lon',
        'lat',
        'authority_metric',
    ];

    /**
     * Materialize one record into a column-keyed row.
     *
     * @return array<string,mixed>
     */
    public function materialize(AuthorityRecord $record): array
    {
        return [
            'authority_source' => $record->authority_source,
            'authority_ref'    => $record->authority_ref,
            'name'             => $record->name,
            'lon'              => $record->lon,
            'lat'              => $record->lat,
            'authority_metric' => $record->authority_metric,
        ];
    }

    /**
     * Ordered scalar row (values in COLUMNS order).
     *
     * @return list<mixed>
     */
    public function toRow(AuthorityRecord $record): array
    {
        $assoc = $this->materialize($record);

        return array_map(static fn (string $col) => $assoc[$col], self::COLUMNS);
    }

    /**
     * @param  iterable<AuthorityRecord> $records
     * @return list<list<mixed>>
     */
    public function toRows(iterable $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = $this->toRow($record);
        }

        return $rows;
    }
}
