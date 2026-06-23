<?php

namespace Tests\Unit\Services\Bridge;

use Tests\TestCase;
use App\Services\Bridge\CriteriaHashService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

class CriteriaHashServiceTest extends TestCase
{
    private CriteriaHashService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CriteriaHashService();
    }

    private function makePayload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    public function test_hash_is_64_character_hex_string(): void
    {
        $hash = $this->service->hash($this->makePayload(), 'buyer');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_same_payload_same_role_produces_same_hash(): void
    {
        $payloadA = $this->makePayload(['max_price' => 400000, 'min_bedrooms' => 3]);
        $payloadB = $this->makePayload(['max_price' => 400000, 'min_bedrooms' => 3]);

        $this->assertSame(
            $this->service->hash($payloadA, 'buyer'),
            $this->service->hash($payloadB, 'buyer'),
        );
    }

    public function test_different_role_produces_different_hash(): void
    {
        $payload = $this->makePayload();

        $this->assertNotSame(
            $this->service->hash($payload, 'buyer'),
            $this->service->hash($payload, 'tenant'),
        );
    }

    public function test_different_max_price_produces_different_hash(): void
    {
        $a = $this->makePayload(['max_price' => 300000]);
        $b = $this->makePayload(['max_price' => 500000]);

        $this->assertNotSame(
            $this->service->hash($a, 'buyer'),
            $this->service->hash($b, 'buyer'),
        );
    }

    public function test_null_field_payload_differs_from_populated_variant(): void
    {
        $withBeds    = $this->makePayload(['min_bedrooms' => 3]);
        $withoutBeds = $this->makePayload();

        $this->assertNotSame(
            $this->service->hash($withBeds, 'buyer'),
            $this->service->hash($withoutBeds, 'buyer'),
        );
    }

    public function test_city_order_does_not_affect_hash(): void
    {
        $a = $this->makePayload(['preferred_cities' => ['Tampa', 'Orlando']]);
        $b = $this->makePayload(['preferred_cities' => ['Orlando', 'Tampa']]);

        $this->assertSame(
            $this->service->hash($a, 'buyer'),
            $this->service->hash($b, 'buyer'),
        );
    }

    public function test_role_comparison_is_case_insensitive(): void
    {
        $payload = $this->makePayload();

        $this->assertSame(
            $this->service->hash($payload, 'buyer'),
            $this->service->hash($payload, 'BUYER'),
        );
    }

    public function test_hash_is_stable_across_multiple_calls(): void
    {
        $payload = $this->makePayload([
            'max_price'        => 350000,
            'min_bedrooms'     => 2,
            'preferred_cities' => ['St. Petersburg'],
            'wants_pool'       => true,
        ]);

        $first  = $this->service->hash($payload, 'buyer');
        $second = $this->service->hash($payload, 'buyer');
        $third  = $this->service->hash($payload, 'buyer');

        $this->assertSame($first, $second);
        $this->assertSame($second, $third);
    }

    public function test_fully_populated_payload_produces_consistent_hash(): void
    {
        $payload = $this->makePayload([
            'property_types'            => ['Residential'],
            'max_price'                 => 600000,
            'ideal_price'               => 500000,
            'min_bedrooms'              => 3,
            'min_bathrooms'             => 2,
            'min_sqft'                  => 1500,
            'max_sqft'                  => 3000,
            'year_built_min'            => 2000,
            'year_built_max'            => 2024,
            'preferred_cities'          => ['Tampa', 'Clearwater'],
            'preferred_counties'        => ['Hillsborough'],
            'wants_pool'                => true,
            'wants_garage'              => true,
            'wants_waterfront'          => false,
            'max_monthly_hoa'           => 200,
            'is_55_plus_eligible'       => false,
            'wants_pet_friendly'        => true,
            'community_feature_keywords' => ['gated', 'tennis'],
        ]);

        $hashA = $this->service->hash($payload, 'buyer');
        $hashB = $this->service->hash($payload, 'buyer');

        $this->assertSame($hashA, $hashB);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hashA);
    }

    public function test_radius_searches_order_does_not_affect_hash(): void
    {
        $radiusA = ['lat' => 27.9944, 'lng' => -82.4451, 'radius_miles' => 10];
        $radiusB = ['lat' => 28.0558, 'lng' => -82.3989, 'radius_miles' => 5];

        $payloadAB = $this->makePayload(['radius_searches' => [$radiusA, $radiusB]]);
        $payloadBA = $this->makePayload(['radius_searches' => [$radiusB, $radiusA]]);

        $this->assertSame(
            $this->service->hash($payloadAB, 'buyer'),
            $this->service->hash($payloadBA, 'buyer'),
        );
    }

    public function test_different_radius_search_coordinates_produce_different_hash(): void
    {
        $payloadA = $this->makePayload([
            'radius_searches' => [['lat' => 27.9944, 'lng' => -82.4451, 'radius_miles' => 10]],
        ]);
        $payloadB = $this->makePayload([
            'radius_searches' => [['lat' => 28.0558, 'lng' => -82.3989, 'radius_miles' => 10]],
        ]);

        $this->assertNotSame(
            $this->service->hash($payloadA, 'buyer'),
            $this->service->hash($payloadB, 'buyer'),
        );
    }

    public function test_radius_search_key_structure_is_preserved(): void
    {
        $withRadius    = $this->makePayload([
            'radius_searches' => [['lat' => 27.9944, 'lng' => -82.4451, 'radius_miles' => 10]],
        ]);
        $withoutRadius = $this->makePayload();

        $this->assertNotSame(
            $this->service->hash($withRadius, 'buyer'),
            $this->service->hash($withoutRadius, 'buyer'),
        );
    }
}
