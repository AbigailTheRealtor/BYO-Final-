<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C1 (cross-source authority linking).
 *
 * Pure transform: a resolved match (from AuthorityLinkMatcher) → one `place_authority_links` row,
 * in the exact column order of B1.2 migration 05. The offline counterpart of the Class-2
 * `INSERT INTO place_authority_links … SELECT`; it emits PHP scalars, never touches a DB, and is
 * deterministic.
 *
 * Column contract (migration 05 — place_authority_links):
 *   authority_source, authority_ref, place_source, place_source_ref, match_method,
 *   match_score numeric(4,3), reviewed_by.
 *
 * `match_score` is rounded to 3 dp (decision D5). `reviewed_by` is NULL for automatic
 * `spatial_name` links (only `manual` resolutions carry a reviewer).
 *
 * @see \Tests\Unit\Spatial\PlaceAuthorityLinkMaterializerTest
 */
final class PlaceAuthorityLinkMaterializer
{
    /**
     * COPY/INSERT target columns, in order — mirrors migration 05. Keep stable: the SQL manifest
     * drift test asserts the recipe's column list equals this constant.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'authority_source',
        'authority_ref',
        'place_source',
        'place_source_ref',
        'match_method',
        'match_score',
        'reviewed_by',
    ];

    /** Match methods the table accepts (migration 05 free-text, constrained here). */
    public const METHODS = ['spatial_name', 'exact', 'manual'];

    /**
     * Materialize one resolved link into a column-keyed row.
     *
     * @return array<string,mixed>
     */
    public function materialize(
        string $authoritySource,
        string $authorityRef,
        string $placeSource,
        string $placeSourceRef,
        string $matchMethod,
        ?float $matchScore,
        ?string $reviewedBy = null,
    ): array {
        if (!in_array($matchMethod, self::METHODS, true)) {
            throw new \InvalidArgumentException("Unknown match_method [{$matchMethod}].");
        }

        return [
            'authority_source' => $authoritySource,
            'authority_ref'    => $authorityRef,
            'place_source'     => $placeSource,
            'place_source_ref' => $placeSourceRef,
            'match_method'     => $matchMethod,
            'match_score'      => $matchScore === null ? null : round($matchScore, 3),
            'reviewed_by'      => $reviewedBy,
        ];
    }

    /**
     * Materialize the `linked` set of a matcher result into column-keyed rows, ordered
     * deterministically by (authority_source, authority_ref).
     *
     * @param  list<array{authority: AuthorityRecord, place: NormalizedPlaceRecord, score: float, method: string}> $linked
     * @return list<array<string,mixed>>
     */
    public function materializeLinked(array $linked): array
    {
        $rows = [];
        foreach ($linked as $entry) {
            $rows[] = $this->materialize(
                authoritySource: $entry['authority']->authority_source,
                authorityRef:    $entry['authority']->authority_ref,
                placeSource:     $entry['place']->source,
                placeSourceRef:  $entry['place']->source_ref,
                matchMethod:     $entry['method'],
                matchScore:      $entry['score'],
                reviewedBy:      null,
            );
        }

        usort($rows, static fn (array $a, array $b): int =>
            [$a['authority_source'], $a['authority_ref']] <=> [$b['authority_source'], $b['authority_ref']]);

        return $rows;
    }

    /**
     * Ordered scalar row (values in COLUMNS order).
     *
     * @param  array<string,mixed> $row
     * @return list<mixed>
     */
    public function toRow(array $row): array
    {
        return array_map(static fn (string $col) => $row[$col] ?? null, self::COLUMNS);
    }
}
