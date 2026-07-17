<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\OvertureCategoryMap;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (B1) — the Overture→canonical taxonomy, registry reconciliation, and
 * primary-category-only behavior. Pure; no DB, no cluster.
 */
class OvertureCategoryMapTest extends TestCase
{
    private OvertureCategoryMap $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->map = new OvertureCategoryMap();
    }

    /** @test */
    public function it_maps_exactly_the_eight_first_slice_source_categories(): void
    {
        $expected = [
            'grocery_store'   => 'grocery_store',
            'restaurant'      => 'restaurant',
            'pharmacy'        => 'pharmacy',
            'shopping_center' => 'shopping_center',
            'coffee_shop'     => 'coffee_shop',
            'gym'             => 'gym',
            'fitness_center'  => 'gym',
            'gas_station'     => 'gas_station',
        ];

        $this->assertSame(array_keys($expected), $this->map->sourceCategories());
        foreach ($expected as $source => $canonical) {
            $this->assertSame($canonical, $this->map->mapPrimary($source), "map[{$source}]");
        }
    }

    /** @test */
    public function fitness_center_and_gym_both_collapse_to_canonical_gym(): void
    {
        $this->assertSame('gym', $this->map->mapPrimary('gym'));
        $this->assertSame('gym', $this->map->mapPrimary('fitness_center'));
    }

    /** @test */
    public function unmapped_and_empty_primaries_return_null(): void
    {
        foreach (['bar', 'hotel', 'school', 'library', 'unknown', '', null] as $token) {
            $this->assertNull($this->map->mapPrimary($token), 'unmapped: ' . var_export($token, true));
            $this->assertFalse($this->map->isMapped($token));
        }
    }

    /** @test */
    public function mapping_normalizes_case_and_whitespace(): void
    {
        $this->assertSame('restaurant', $this->map->mapPrimary('  Restaurant '));
        $this->assertSame('gym', $this->map->mapPrimary('GYM'));
        $this->assertSame('grocery_store', $this->map->mapPrimary('Grocery_Store'));
    }

    /** @test */
    public function eight_source_categories_collapse_to_seven_canonical_keys(): void
    {
        $this->assertCount(8, $this->map->sourceCategories());
        $this->assertCount(7, $this->map->mappedCanonicalKeys());
        $this->assertCount(7, $this->map->canonicalKeys());
    }

    /** @test */
    public function the_registry_reconciles_with_the_mappings(): void
    {
        $r = $this->map->reconcile();
        $this->assertSame([], $r['dangling'], 'mapping targets missing from the registry');
        $this->assertSame([], $r['orphans'], 'registry rows nothing maps to');
        $this->assertTrue($this->map->isReconciled());

        // Every mapping target is a registered canonical key (same set).
        $mapped = $this->map->mappedCanonicalKeys();
        $registered = $this->map->canonicalKeys();
        sort($mapped);
        sort($registered);
        $this->assertSame($registered, $mapped);
    }

    /** @test */
    public function category_rows_carry_the_owner_mandated_seed_values(): void
    {
        $rows = $this->map->categoryRows();
        $this->assertCount(7, $rows);

        $keys = [];
        foreach ($rows as $row) {
            $keys[] = $row['category_key'];
            $this->assertSame('confidence', $row['rank_strategy'], 'rank_strategy must be confidence');
            $this->assertNull($row['exclusion_rules'], 'exclusion_rules must be NULL');
            $this->assertSame('overture', $row['base_source']);
            $this->assertTrue($row['enabled']);
            $this->assertNotSame('', $row['label']);
            $this->assertNotSame('', $row['thematic_block']);
        }
        $this->assertSame($this->map->canonicalKeys(), $keys);
    }

    /** @test */
    public function mapping_rows_are_eight_overture_rows_targeting_seven_keys(): void
    {
        $rows = $this->map->mappingRows();
        $this->assertCount(8, $rows);

        $sourceCats = [];
        $targets = [];
        foreach ($rows as $row) {
            $this->assertSame('overture', $row['source']);
            $sourceCats[] = $row['source_category'];
            $targets[$row['category_key']] = true;
        }

        $this->assertSame($this->map->sourceCategories(), $sourceCats);
        $this->assertCount(7, $targets);
        // Every target is a registered canonical (no dangling FK).
        foreach (array_keys($targets) as $key) {
            $this->assertContains($key, $this->map->canonicalKeys());
        }
    }
}
