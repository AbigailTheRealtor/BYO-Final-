<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaChipPresenter;
use Tests\TestCase;

/**
 * LocationDnaChipPresenterTest — Phase 7A
 *
 * Covers all required scenarios:
 *   (1)  flexible_location: true → "Flexible Location" chip
 *   (2)  radius_searches present → "Radius Search" chip
 *   (3)  polygons present → "Custom Search Area" chip
 *   (4)  2 cities → "[City1] / [City2] Submarkets" chip
 *   (5)  3+ cities → "Multiple Submarkets" chip
 *   (6)  3+ chips total → first 3 returned, overflow = remainder
 *   (7)  empty preferences → empty chips array, no exception
 *
 * No database, no factories, no HTTP calls — purely in-memory fixture arrays.
 */
class LocationDnaChipPresenterTest extends TestCase
{
    private function makePresenter(): LocationDnaChipPresenter
    {
        return new LocationDnaChipPresenter();
    }

    private function assertChipShape(array $result): void
    {
        $this->assertIsArray($result);
        $this->assertArrayHasKey('chips', $result);
        $this->assertArrayHasKey('overflow', $result);
        $this->assertIsArray($result['chips']);
        $this->assertIsInt($result['overflow']);
    }

    // =========================================================================
    // (1) flexible_location: true → "Flexible Location" chip
    // =========================================================================

    /** @test */
    public function it_generates_flexible_location_chip(): void
    {
        $preferences = ['flexible_location' => true];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertContains('Flexible Location', $result['chips']);
    }

    /** @test */
    public function it_does_not_generate_flexible_chip_when_false(): void
    {
        $preferences = ['flexible_location' => false, 'cities' => ['Tampa']];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertNotContains('Flexible Location', $result['chips']);
    }

    // =========================================================================
    // (2) radius_searches present → "Radius Search" chip
    // =========================================================================

    /** @test */
    public function it_generates_radius_search_chip(): void
    {
        $preferences = [
            'radius_searches' => [
                ['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 5],
            ],
        ];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertContains('Radius Search', $result['chips']);
    }

    /** @test */
    public function it_does_not_generate_radius_chip_when_empty(): void
    {
        $preferences = ['radius_searches' => []];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertNotContains('Radius Search', $result['chips']);
    }

    // =========================================================================
    // (3) polygons present → "Custom Search Area" chip
    // =========================================================================

    /** @test */
    public function it_generates_custom_search_area_chip_for_polygons(): void
    {
        $preferences = [
            'polygons' => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
        ];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertContains('Custom Search Area', $result['chips']);
    }

    /** @test */
    public function it_does_not_generate_polygon_chip_when_empty(): void
    {
        $preferences = ['polygons' => []];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertNotContains('Custom Search Area', $result['chips']);
    }

    // =========================================================================
    // (4) 2 cities → "[City1] / [City2] Submarkets"
    // =========================================================================

    /** @test */
    public function it_generates_two_city_submarket_chip(): void
    {
        $preferences = ['cities' => ['Tampa', 'St. Petersburg']];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertContains('Tampa / St. Petersburg Submarkets', $result['chips']);
    }

    /** @test */
    public function it_does_not_generate_submarket_chip_for_single_city(): void
    {
        $preferences = ['cities' => ['Tampa']];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $chipLabels = $result['chips'];
        foreach ($chipLabels as $chip) {
            $this->assertStringNotContainsString('Submarkets', $chip);
        }
    }

    // =========================================================================
    // (5) 3+ cities → "Multiple Submarkets" chip
    // =========================================================================

    /** @test */
    public function it_generates_multiple_submarkets_chip_for_three_or_more_cities(): void
    {
        $preferences = ['cities' => ['Tampa', 'St. Petersburg', 'Clearwater']];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertContains('Multiple Submarkets', $result['chips']);
    }

    /** @test */
    public function it_uses_multiple_submarkets_not_city_names_for_four_cities(): void
    {
        $preferences = ['cities' => ['Tampa', 'Orlando', 'Miami', 'Jacksonville']];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertContains('Multiple Submarkets', $result['chips']);
        $this->assertNotContains('Tampa / Orlando Submarkets', $result['chips']);
    }

    // =========================================================================
    // (6) 3+ chips total → first 3 returned, overflow = remainder
    // =========================================================================

    /** @test */
    public function it_caps_chips_at_three_and_sets_overflow(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'Orlando'],
            'polygons'          => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
            'radius_searches'   => [
                ['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 10],
            ],
        ];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertCount(3, $result['chips']);
        $this->assertSame(1, $result['overflow']);
    }

    /** @test */
    public function it_sets_overflow_zero_when_three_or_fewer_chips(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'Orlando'],
        ];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertCount(2, $result['chips']);
        $this->assertSame(0, $result['overflow']);
    }

    /** @test */
    public function it_returns_first_three_chips_in_derivation_order(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'Orlando'],
            'polygons'          => [['path' => [['lat' => 1, 'lng' => 1]]]],
            'radius_searches'   => [['center' => ['lat' => 1, 'lng' => 1], 'radius_miles' => 5]],
        ];

        $result = $this->makePresenter()->present($preferences);

        $this->assertSame(['Flexible Location', 'Tampa / Orlando Submarkets', 'Custom Search Area'], $result['chips']);
        $this->assertSame(1, $result['overflow']);
    }

    // =========================================================================
    // (7) Empty preferences → empty chips array, no exception
    // =========================================================================

    /** @test */
    public function it_returns_empty_chips_for_empty_preferences(): void
    {
        $result = $this->makePresenter()->present([]);

        $this->assertChipShape($result);
        $this->assertEmpty($result['chips']);
        $this->assertSame(0, $result['overflow']);
    }

    /** @test */
    public function it_returns_empty_chips_when_all_signals_absent(): void
    {
        $preferences = [
            'flexible_location' => false,
            'cities'            => [],
            'polygons'          => [],
            'radius_searches'   => [],
        ];

        $result = $this->makePresenter()->present($preferences);

        $this->assertChipShape($result);
        $this->assertEmpty($result['chips']);
        $this->assertSame(0, $result['overflow']);
    }

    // =========================================================================
    // Governance — no DB, Eloquent, or OpenAI imports
    // =========================================================================

    /** @test */
    public function the_presenter_service_file_does_not_import_db_eloquent_or_openai(): void
    {
        $source = file_get_contents(
            app_path('Services/LocationDna/LocationDnaChipPresenter.php')
        );

        $this->assertStringNotContainsString('use Illuminate\\Support\\Facades\\DB', $source);
        $this->assertStringNotContainsString('use Illuminate\\Database\\Eloquent', $source);
        $this->assertStringNotContainsString('use OpenAI\\', $source);
        $this->assertStringNotContainsString('use App\\Models\\', $source);
    }
}
