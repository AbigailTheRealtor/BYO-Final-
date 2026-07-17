<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B1).
 *
 * The FIRST-SLICE Overture-primary-category → canonical-category map, and the
 * canonical category registry it reconciles against. Pure, side-effect-free,
 * cluster-independent — this class is the single source of truth from which
 * both the guarded seeders and the offline normalizer derive.
 *
 * Contract (owner decision):
 *   • Exactly the 8 Overture PRIMARY categories below are mapped.
 *   • PRIMARY category only — alternate-category matches are IGNORED. The
 *     normalizer never inspects `categories.alternate`.
 *   • `fitness_center` and `gym` both collapse to canonical `gym`; the map is
 *     therefore many-to-one (8 source categories → 7 canonical keys).
 *
 * The corresponding PostGIS rows (place_categories / place_category_mappings)
 * are authored as GUARDED seeders (SpatialFirstSliceCategorySeeder,
 * SpatialOvertureCategoryMappingSeeder) but are NOT run against a cluster in
 * this batch.
 */
final class OvertureCategoryMap
{
    /** The canonical `source` tag for every mapping row and normalized record. */
    public const SOURCE = 'overture';

    /**
     * Overture primary category → canonical category_key. Exactly 8 entries.
     * Keys are Overture's lowercase snake_case primary-category tokens.
     */
    private const MAP = [
        'grocery_store'   => 'grocery_store',
        'restaurant'      => 'restaurant',
        'pharmacy'        => 'pharmacy',
        'shopping_center' => 'shopping_center',
        'coffee_shop'     => 'coffee_shop',
        'gym'             => 'gym',
        'fitness_center'  => 'gym',
        'gas_station'     => 'gas_station',
    ];

    /**
     * Canonical registry rows for place_categories (SSOT §7.2). Keyed by
     * category_key. Batch 2A seeds these with rank_strategy=confidence and
     * exclusion_rules=NULL (owner decision). base_source is the corpus tag.
     */
    private const CANONICAL = [
        'grocery_store'   => ['label' => 'Grocery Store',   'thematic_block' => 'daily_convenience'],
        'restaurant'      => ['label' => 'Restaurant',      'thematic_block' => 'daily_convenience'],
        'pharmacy'        => ['label' => 'Pharmacy',        'thematic_block' => 'daily_convenience'],
        'shopping_center' => ['label' => 'Shopping Center', 'thematic_block' => 'daily_convenience'],
        'coffee_shop'     => ['label' => 'Coffee Shop',     'thematic_block' => 'daily_convenience'],
        'gym'             => ['label' => 'Gym & Fitness',   'thematic_block' => 'daily_convenience'],
        'gas_station'     => ['label' => 'Gas Station',     'thematic_block' => 'transportation'],
    ];

    /** Seed value for place_categories.rank_strategy (owner decision). */
    public const RANK_STRATEGY = 'confidence';

    /**
     * Map an Overture PRIMARY category token to its canonical category_key, or
     * null when unmapped. Input is normalized (trim + lowercase) so casing/whitespace
     * variance in the corpus does not silently drop a mapped row. A null/empty
     * primary is unmapped.
     */
    public function mapPrimary(?string $primaryCategory): ?string
    {
        $key = $this->normalizeToken($primaryCategory);
        if ($key === '') {
            return null;
        }

        return self::MAP[$key] ?? null;
    }

    public function isMapped(?string $primaryCategory): bool
    {
        return $this->mapPrimary($primaryCategory) !== null;
    }

    /** Overture source-category tokens that are mapped (8). */
    public function sourceCategories(): array
    {
        return array_keys(self::MAP);
    }

    /** Distinct canonical category_keys the map targets (7). */
    public function mappedCanonicalKeys(): array
    {
        return array_values(array_unique(array_values(self::MAP)));
    }

    /** Registered canonical category_keys (7). */
    public function canonicalKeys(): array
    {
        return array_keys(self::CANONICAL);
    }

    public function canonical(string $categoryKey): ?array
    {
        return self::CANONICAL[$categoryKey] ?? null;
    }

    /**
     * The place_categories seed rows, in registry order. Pure — the seeder and
     * its shape test both consume this.
     *
     * @return array<int,array{category_key:string,label:string,thematic_block:string,base_source:string,rank_strategy:string,exclusion_rules:null,enabled:bool}>
     */
    public function categoryRows(): array
    {
        $rows = [];
        foreach (self::CANONICAL as $key => $meta) {
            $rows[] = [
                'category_key'    => $key,
                'label'           => $meta['label'],
                'thematic_block'  => $meta['thematic_block'],
                'base_source'     => self::SOURCE,
                'rank_strategy'   => self::RANK_STRATEGY,
                'exclusion_rules' => null,
                'enabled'         => true,
            ];
        }

        return $rows;
    }

    /**
     * The place_category_mappings seed rows, in source-category order (8). Pure.
     *
     * @return array<int,array{source:string,source_category:string,category_key:string}>
     */
    public function mappingRows(): array
    {
        $rows = [];
        foreach (self::MAP as $sourceCategory => $categoryKey) {
            $rows[] = [
                'source'          => self::SOURCE,
                'source_category' => $sourceCategory,
                'category_key'    => $categoryKey,
            ];
        }

        return $rows;
    }

    /**
     * Registry reconciliation. Every category_key the map targets MUST be a
     * registered canonical, and every registered canonical MUST be targeted by
     * at least one mapping (no dangling target, no orphan registry row).
     *
     * @return array{dangling:string[],orphans:string[]}
     */
    public function reconcile(): array
    {
        $mapped = $this->mappedCanonicalKeys();
        $registered = $this->canonicalKeys();

        return [
            // mapping target not present in the registry (would violate the FK)
            'dangling' => array_values(array_diff($mapped, $registered)),
            // registry row nothing maps to
            'orphans'  => array_values(array_diff($registered, $mapped)),
        ];
    }

    public function isReconciled(): bool
    {
        $r = $this->reconcile();

        return $r['dangling'] === [] && $r['orphans'] === [];
    }

    private function normalizeToken(?string $token): string
    {
        return strtolower(trim((string) $token));
    }
}
