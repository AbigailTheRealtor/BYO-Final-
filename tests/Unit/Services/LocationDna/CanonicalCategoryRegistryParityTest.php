<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\CanonicalCategoryRegistry as Registry;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaRankingProfileService;
use App\Services\LocationDna\LocationDnaSummaryService;
use App\Services\LocationDna\PoiDistanceLookupService;
use ReflectionClass;
use Tests\TestCase;

/**
 * CanonicalCategoryRegistryParityTest — Phase 1, Deliverable 5.
 *
 * The registry claims to be the single authoritative vocabulary. This test makes that claim
 * falsifiable: it RECONSTRUCTS each of the five live category maps purely from the registry and
 * asserts the reconstruction equals the real thing.
 *
 * That is what earns a later batch the right to delete those maps. Until every one of these
 * assertions passes, the registry is a second opinion rather than a source of truth — and a
 * second opinion is worse than no opinion, because it can drift.
 *
 * Nothing in production reads the registry yet. These tests are the whole point of Batch 8.1.
 */
class CanonicalCategoryRegistryParityTest extends TestCase
{
    /** Read a private const without changing production visibility just to satisfy a test. */
    private function privateConst(string $class, string $name): array
    {
        return (new ReflectionClass($class))->getConstant($name);
    }

    // =========================================================================
    // §1 — Seller/Landlord: LocationDnaPoiDistanceService::CATEGORIES
    // =========================================================================

    /** @test */
    public function it_reconstructs_the_location_dna_category_map_exactly(): void
    {
        $reconstructed = [];

        foreach (Registry::enabledFor(Registry::VIEW_SELLER_LANDLORD) as $key) {
            if (Registry::isDerived($key)) {
                continue; // top_rated_dining is derived, never fetched — not in CATEGORIES.
            }

            $legacy = Registry::providerMapping($key, 'google_places')['seller_landlord'];

            // Key order matters: assertSame compares associative arrays with ===.
            $reconstructed[$key] = [
                'google_type'    => $legacy['google_type'],
                'keyword'        => $legacy['keyword'],
                'label'          => Registry::label($key),
                'query_strategy' => $legacy['query_strategy'],
            ];
        }

        $this->assertSame(
            LocationDnaPoiDistanceService::CATEGORIES,
            $reconstructed,
            'The registry no longer reproduces LocationDnaPoiDistanceService::CATEGORIES. '
            . 'It cannot be the source of truth for a map it disagrees with.',
        );
    }

    /** @test */
    public function the_derived_category_is_not_part_of_the_fetched_map(): void
    {
        $this->assertTrue(Registry::isDerived('top_rated_dining'));
        $this->assertArrayNotHasKey('top_rated_dining', LocationDnaPoiDistanceService::CATEGORIES);
    }

    // =========================================================================
    // §2 — Buyer/Tenant: the 7-category subset view
    // =========================================================================

    /** @test */
    public function the_buyer_tenant_view_is_exactly_the_seven_supported_slugs(): void
    {
        $supported = $this->privateConst(PoiDistanceLookupService::class, 'SUPPORTED_CATEGORIES');

        // The live vocabulary is PLURAL ('schools'); the registry is canonical ('school') and
        // carries the plural as an alias. Resolving each slug must land on an enabled category.
        $resolved = array_map(
            static fn (string $slug): string => Registry::resolve($slug),
            $supported,
        );

        sort($resolved);
        $enabled = Registry::enabledFor(Registry::VIEW_BUYER_TENANT);
        sort($enabled);

        $this->assertSame(
            $enabled,
            $resolved,
            'The buyer/tenant subset view has drifted from PoiDistanceLookupService::SUPPORTED_CATEGORIES.',
        );

        $this->assertCount(7, $enabled, 'The SSOT requires buyer/tenant to remain a 7-category subset view.');
    }

    /** @test */
    public function it_reconstructs_the_buyer_tenant_google_map_exactly(): void
    {
        $live = $this->privateConst(GooglePlacesPoiAdapter::class, 'CATEGORY_MAP');

        $reconstructed = [];

        foreach (Registry::enabledFor(Registry::VIEW_BUYER_TENANT) as $key) {
            $legacy = Registry::providerMapping($key, 'google_places')['buyer_tenant'];

            // Re-key onto the live PLURAL slug this category is reached by.
            $slug = Registry::get($key)['aliases'][0] ?? $key;

            $reconstructed[$slug] = ['type' => $legacy['type'], 'keyword' => $legacy['keyword']];
        }

        // Compared by content, not declaration order: the two maps are ordered differently and
        // nothing depends on the order of either.
        ksort($live);
        ksort($reconstructed);

        $this->assertSame($live, $reconstructed);
    }

    // =========================================================================
    // §3 — Ranking profiles and exclusion rules
    // =========================================================================

    /** @test */
    public function every_ranking_profile_belongs_to_a_registered_category(): void
    {
        $profiled = array_keys(LocationDnaRankingProfileService::profiles());
        $profiled = array_values(array_diff($profiled, ['default'])); // 'default' is a fallback, not a category

        sort($profiled);

        // Every seller/landlord category — the 19 fetched plus the derived one — has a profile.
        $expected = Registry::enabledFor(Registry::VIEW_SELLER_LANDLORD);
        sort($expected);

        $this->assertSame(
            $expected,
            $profiled,
            'Ranking profiles and the registry disagree about which categories exist.',
        );
    }

    /** @test */
    public function the_exclusion_policy_flags_match_the_live_exclusion_rules(): void
    {
        $live = array_keys($this->privateConst(LocationDnaPoiDistanceService::class, 'CATEGORY_EXCLUSION_RULES'));
        sort($live);

        $flagged = Registry::withExclusionPolicy();
        sort($flagged);

        // The registry records WHICH categories have an exclusion policy. The rules themselves —
        // Google-type strings and brand regexes — deliberately stay with their consumer: they are
        // provider-coupled and are re-sourced during the Google-removal phases.
        $this->assertSame(
            $live,
            $flagged,
            'A category gained or lost an exclusion rule without the registry being updated.',
        );
    }

    // =========================================================================
    // §4 — Summary groupings
    // =========================================================================

    /** @test */
    public function every_category_referenced_by_a_thematic_block_is_registered(): void
    {
        $blocks = $this->privateConst(LocationDnaSummaryService::class, 'THEMATIC_BLOCKS');

        $unknown = [];

        foreach ($blocks as $blockKey => $categoryMap) {
            foreach (array_keys($categoryMap) as $category) {
                if (Registry::tryResolve($category) === null) {
                    $unknown[] = "{$blockKey}.{$category}";
                }
            }
        }

        $this->assertSame([], $unknown, 'THEMATIC_BLOCKS references categories the registry does not know.');
    }

    // =========================================================================
    // §5 — The registry adds no Google dependency
    // =========================================================================

    /** @test */
    public function google_is_recorded_as_legacy_and_never_as_an_active_provider(): void
    {
        $this->assertTrue(Registry::isLegacyProvider('google_places'));

        foreach (Registry::keys() as $key) {
            $mapping = Registry::providerMapping($key, 'google_places');

            if ($mapping === []) {
                continue; // urgent_care, highway_access, top_rated_dining have no Google mapping.
            }

            $this->assertSame(
                Registry::PROVIDER_LEGACY,
                $mapping['status'],
                "Category '{$key}' records Google as something other than a legacy provider.",
            );
        }
    }

    /** @test */
    public function open_data_providers_are_planned_and_may_be_empty(): void
    {
        // Phase 2 has not run. A planned provider with no parameters is the honest state; this
        // test exists so "empty" stays a deliberate answer rather than an oversight.
        $this->assertSame(
            Registry::PROVIDER_PLANNED,
            Registry::providerMapping('hospital', 'cms')['status'],
        );

        $this->assertSame(
            Registry::PROVIDER_PLANNED,
            Registry::providerMapping('airport', 'faa_nasr')['status'],
        );

        $this->assertSame([], Registry::providerMapping('grocery_store', 'a_provider_that_does_not_exist'));
    }
}
