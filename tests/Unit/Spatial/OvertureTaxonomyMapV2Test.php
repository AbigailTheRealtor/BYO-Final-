<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\OvertureTaxonomyMapV2;
use PHPUnit\Framework\TestCase;

/**
 * Corpus v2 crosswalk: `taxonomy.primary` → canonical key. Pure; no container, no DB.
 *
 * Pins the approved 16-token contract exactly, and that everything outside it fails closed.
 */
class OvertureTaxonomyMapV2Test extends TestCase
{
    /** The approved contract, verbatim. */
    private const APPROVED = [
        'restaurant'           => 'restaurant',
        'gas_station'          => 'gas_station',
        'gym'                  => 'gym',
        'grocery_store'        => 'grocery_store',
        'coffee_shop'          => 'coffee_shop',
        'pharmacy'             => 'pharmacy',
        'shopping_mall'        => 'shopping_center',
        'convenience_store'    => 'convenience_store',
        'fast_food_restaurant' => 'fast_food_restaurant',
        'cafe'                 => 'cafe',
        'burger_restaurant'    => 'burger_restaurant',
        'department_store'     => 'department_store',
        'chicken_restaurant'   => 'chicken_restaurant',
        'drugstore'            => 'drugstore',
        'taco_restaurant'      => 'taco_restaurant',
        'superstore'           => 'superstore',
    ];

    private OvertureTaxonomyMapV2 $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->map = new OvertureTaxonomyMapV2();
    }

    public function test_the_crosswalk_is_exactly_the_approved_sixteen(): void
    {
        $this->assertSame(self::APPROVED, $this->map->mappings());
        $this->assertCount(16, $this->map->sourceTokens());
        $this->assertCount(16, $this->map->canonicalKeys());
    }

    public function test_every_approved_token_maps_to_its_canonical_key(): void
    {
        foreach (self::APPROVED as $token => $canonical) {
            $this->assertSame($canonical, $this->map->mapSource($token), $token);
            $this->assertTrue($this->map->isImported($token), $token);
        }
    }

    public function test_shopping_mall_maps_to_the_existing_shopping_center_key(): void
    {
        $this->assertSame('shopping_center', $this->map->mapSource('shopping_mall'));
    }

    public function test_the_legacy_shopping_center_token_is_not_a_v2_source_token(): void
    {
        // `shopping_center` is the categories.primary spelling. Supplied as a taxonomy token
        // it must not silently act as `shopping_mall`.
        $this->assertNull($this->map->mapSource('shopping_center'));
        $this->assertFalse($this->map->isImported('shopping_center'));
    }

    public function test_fitness_center_is_rejected(): void
    {
        $this->assertNull($this->map->mapSource('fitness_center'));
        $this->assertNotContains('fitness_center', $this->map->sourceTokens());
    }

    /**
     * @dataProvider failClosedTokens
     */
    public function test_anything_not_named_fails_closed(?string $token): void
    {
        $this->assertNull($this->map->mapSource($token));
        $this->assertFalse($this->map->isImported($token));
    }

    /** @return array<string, array{0: ?string}> */
    public static function failClosedTokens(): array
    {
        return [
            'null'                       => [null],
            'empty'                      => [''],
            'whitespace'                 => ['   '],
            'unknown'                    => ['definitely_not_a_category'],
            'fitness_center'             => ['fitness_center'],
            'legacy shopping_center'     => ['shopping_center'],
            // Measured never-import tokens (PR 0 census).
            'money_transfer_service'     => ['money_transfer_service'],
            'atm'                        => ['atm'],
            'package_locker'             => ['package_locker'],
            'ev_charging_station'        => ['ev_charging_station'],
            'liquor_store'               => ['liquor_store'],
            'beer_wine_spirits_store'    => ['beer_wine_spirits_store'],
            'truck_gas_station'          => ['truck_gas_station'],
            'truck_stop'                 => ['truck_stop'],
            'mexican_restaurant'         => ['mexican_restaurant'],
            'bakery'                     => ['bakery'],
            'eyewear_store'              => ['eyewear_store'],
            'shopping'                   => ['shopping'],
            // basic_category values are not classifiers here.
            'basic: fueling_station'     => ['fueling_station'],
            'basic: food_and_beverage'   => ['food_and_beverage_store'],
            // No partial or similarity matching.
            'prefix of a real token'     => ['fast_food'],
            'superset of a real token'   => ['cafe_bar'],
        ];
    }

    public function test_only_case_and_surrounding_whitespace_are_normalised(): void
    {
        $this->assertSame('cafe', $this->map->mapSource('  CAFE '));
        $this->assertSame('shopping_center', $this->map->mapSource('Shopping_Mall'));
        $this->assertNull($this->map->mapSource('shopping mall'));
    }

    public function test_the_v1_crosswalk_is_untouched_by_the_v2_contract(): void
    {
        // v1 still reads categories.primary with its own eight tokens; v2 does not alter it.
        $v1 = new OvertureCategoryMap();

        $this->assertSame('shopping_center', $v1->mapPrimary('shopping_center'));
        $this->assertSame('gym', $v1->mapPrimary('fitness_center'));
        $this->assertNull($v1->mapPrimary('shopping_mall'));
        $this->assertNull($v1->mapPrimary('cafe'));
        $this->assertCount(8, $v1->sourceCategories());
    }
}
