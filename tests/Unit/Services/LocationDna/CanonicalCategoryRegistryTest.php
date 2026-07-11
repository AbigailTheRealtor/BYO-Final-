<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\CanonicalCategoryRegistry as Registry;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * CanonicalCategoryRegistryTest — the registry's own contract.
 *
 * Parity with the five live maps is asserted separately, in
 * CanonicalCategoryRegistryParityTest. This file covers the guarantees the registry makes on
 * its own terms: deterministic alias resolution, failing closed on the unknown, and disabled
 * categories that cannot drift into being active.
 */
class CanonicalCategoryRegistryTest extends TestCase
{
    // =========================================================================
    // §1 — Shape of the vocabulary
    // =========================================================================

    /** @test */
    public function it_registers_twenty_three_canonical_categories_plus_one_derived(): void
    {
        // The 23 is the SSOT's own number for Phase 3b ("activate all 23 categories"):
        // 19 currently fetched + the 4 never-built. `top_rated_dining` is derived, not fetched,
        // which is why it sits outside that count — and why Phase 3b deletes it.
        $all     = Registry::keys();
        $derived = array_filter($all, static fn (string $k): bool => Registry::isDerived($k));

        $this->assertCount(24, $all);
        $this->assertCount(1, $derived);
        $this->assertCount(23, array_diff($all, $derived));
    }

    /** @test */
    public function every_category_declares_a_label_and_a_state_for_every_view(): void
    {
        foreach (Registry::keys() as $key) {
            $definition = Registry::get($key);

            $this->assertNotSame('', $definition['label'], "Category '{$key}' has no label.");

            foreach (Registry::VIEWS as $view) {
                $this->assertArrayHasKey(
                    $view,
                    $definition['views'],
                    "Category '{$key}' does not declare a state for view '{$view}'.",
                );
                $this->assertIsBool($definition['views'][$view]);
            }
        }
    }

    // =========================================================================
    // §2 — Aliases resolve deterministically
    // =========================================================================

    /**
     * @test
     * @dataProvider aliasPairs
     */
    public function aliases_resolve_to_their_canonical_key(string $alias, string $canonical): void
    {
        $this->assertSame($canonical, Registry::resolve($alias));
        $this->assertSame($canonical, Registry::resolve($canonical), 'Resolution must be idempotent.');
    }

    public static function aliasPairs(): array
    {
        // The plural/singular split is the drift this registry exists to end: the buyer/tenant
        // path says 'schools', Location DNA says 'school', and nothing reconciled them.
        return [
            'schools'   => ['schools', 'school'],
            'parks'     => ['parks', 'park'],
            'hospitals' => ['hospitals', 'hospital'],
            'gyms'      => ['gyms', 'gym'],
            'shopping'  => ['shopping', 'shopping_center'],
            'airports'  => ['airports', 'airport'],
        ];
    }

    /** @test */
    public function no_alias_collides_with_a_canonical_key_or_another_alias(): void
    {
        $seen = [];

        foreach (Registry::all() as $key => $definition) {
            $seen[] = $key;

            foreach ($definition['aliases'] as $alias) {
                $this->assertFalse(
                    Registry::has($alias),
                    "Alias '{$alias}' collides with a canonical key.",
                );
                $seen[] = $alias;
            }
        }

        $this->assertSame(
            count($seen),
            count(array_unique($seen)),
            'An identifier is claimed twice; resolution would not be deterministic.',
        );
    }

    // =========================================================================
    // §3 — Unknown input fails closed
    // =========================================================================

    /** @test */
    public function resolving_an_unknown_category_throws(): void
    {
        // A silent default here would let a typo select the wrong ranking profile or persist an
        // unrecognised poi_category — failures that are invisible until the data is wrong.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown POI category 'supermarket'");

        Registry::resolve('supermarket');
    }

    /** @test */
    public function try_resolve_returns_null_rather_than_guessing(): void
    {
        $this->assertNull(Registry::tryResolve('supermarket'));
        $this->assertNull(Registry::tryResolve(''));
        $this->assertSame('school', Registry::tryResolve('schools'));
    }

    /** @test */
    public function an_unknown_view_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown category view 'agent'");

        Registry::enabledFor('agent');
    }

    /** @test */
    public function querying_an_unknown_category_for_a_view_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Registry::isEnabledFor('supermarket', Registry::VIEW_SELLER_LANDLORD);
    }

    // =========================================================================
    // §4 — Disabled categories cannot accidentally become active
    // =========================================================================

    /**
     * @test
     * @dataProvider neverBuiltCategories
     */
    public function the_four_never_built_categories_are_disabled_for_location_dna(string $key): void
    {
        $this->assertTrue(Registry::has($key), "The SSOT requires '{$key}' to be registered.");
        $this->assertFalse(
            Registry::isEnabledFor($key, Registry::VIEW_SELLER_LANDLORD),
            "'{$key}' has never been built for Location DNA and must not be enabled for it.",
        );
    }

    public static function neverBuiltCategories(): array
    {
        return [
            'airport'        => ['airport'],
            'urgent_care'    => ['urgent_care'],
            'highway_access' => ['highway_access'],
            'downtown'       => ['downtown'],
        ];
    }

    /** @test */
    public function airport_and_downtown_stay_enabled_for_buyer_tenant(): void
    {
        // This is the regression a single global `enabled => false` would have caused: both are
        // live buyer/tenant slugs today. Per-view enablement is what keeps that path intact while
        // still recording that Location DNA has never had them.
        $this->assertTrue(Registry::isEnabledFor('airport', Registry::VIEW_BUYER_TENANT));
        $this->assertTrue(Registry::isEnabledFor('downtown', Registry::VIEW_BUYER_TENANT));

        $this->assertFalse(Registry::isEnabledFor('airport', Registry::VIEW_SELLER_LANDLORD));
        $this->assertFalse(Registry::isEnabledFor('downtown', Registry::VIEW_SELLER_LANDLORD));
    }

    /** @test */
    public function categories_without_an_open_data_source_are_not_marked_for_corpus_import(): void
    {
        // VIEW_CORPUS is a PLANNING signal — "Phase 2 should import a source for this" — and must
        // never be read as a runtime gate. 'downtown' is a fuzzy keyword search, not an entity in
        // any authority dataset; 'highway_access' has no source and no code. Neither may be
        // silently promoted into the import set.
        $this->assertFalse(Registry::isEnabledFor('downtown', Registry::VIEW_CORPUS));
        $this->assertFalse(Registry::isEnabledFor('highway_access', Registry::VIEW_CORPUS));

        // The derived category is computed, never imported.
        $this->assertFalse(Registry::isEnabledFor('top_rated_dining', Registry::VIEW_CORPUS));
    }

    /** @test */
    public function the_never_built_categories_carry_no_persisted_history(): void
    {
        // They were never fetched, so they can hold no persisted poi_category value. Guarding this
        // stops a future batch from assuming a backfill exists for them.
        foreach (self::neverBuiltCategories() as [$key]) {
            $this->assertFalse(
                Registry::isDerived($key),
                "'{$key}' is not derived; it simply was never built.",
            );
        }
    }

    // =========================================================================
    // §5 — Deprecations are recorded, not executed
    // =========================================================================

    /** @test */
    public function phase_3b_deprecations_are_recorded_without_being_acted_on(): void
    {
        $this->assertSame(
            ['phase' => '3b', 'action' => 'merge', 'merge_into' => 'gym'],
            Registry::deprecation('fitness_center'),
        );

        $this->assertSame(
            ['phase' => '3b', 'action' => 'remove', 'merge_into' => null],
            Registry::deprecation('top_rated_dining'),
        );

        // Recorded ≠ applied. Both are still enabled for Location DNA today, exactly as they are
        // in production. Phase 3b executes these; Batch 8.1 only writes them down.
        $this->assertTrue(Registry::isEnabledFor('fitness_center', Registry::VIEW_SELLER_LANDLORD));
        $this->assertTrue(Registry::isEnabledFor('top_rated_dining', Registry::VIEW_SELLER_LANDLORD));

        $this->assertNull(Registry::deprecation('grocery_store'));
    }
}
