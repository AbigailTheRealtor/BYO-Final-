<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\Concerns\HasGeographyCascade;
use App\Http\Livewire\Concerns\HasSearchAreas;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

/**
 * T1 — the legacy geography backfill, extracted and reaching the Create Tenant load paths.
 *
 * WHAT T1 IS, AND WHAT IT DELIBERATELY IS NOT
 * -------------------------------------------
 * It is the PRECONDITION for rendering the geography cascade on a surface that owns legacy ZIP
 * state. It is NOT the cascade wiring: `create_tenant` remains absent from every workflow map and
 * every scope list, and {@see self::create_tenant_is_still_unwired()} pins that.
 *
 * THE GAP IT CLOSES
 * -----------------
 * `HasSearchAreas::loadSearchAreas()` has always folded legacy `cities` and `zipCodes` meta into
 * the in-memory blob. The four Create Offer components never call it — they decode the blob inline
 * — so they never got that rule. Invisible while the blob is only a prefill source; not invisible
 * once a cascade hydrates from the blob and projects all four geography keys back out, because a
 * record whose ZIPs live only in the legacy meta would hydrate none and project an empty list over
 * them.
 *
 * WHY THE EXTRACTION RATHER THAN A SECOND COPY
 * --------------------------------------------
 * Two families need one rule. The Hire family reaches it through `loadSearchAreas()`; the Create
 * family cannot adopt that method wholesale, because its load paths are interleaved with
 * role-specific hydration and a distinct 9B-2 prefill. A duplicated rule is a rule that drifts, and
 * this one is load-bearing in a way that only shows up in stored data.
 *
 * SCOPE OF THIS SUITE
 *   1 · the extraction preserved `loadSearchAreas()` behaviour exactly
 *   2 · existing tenant ZIP data survives a load
 *   3 · the storage format is unchanged
 *   4 · Buyer behaviour is unchanged
 *   5 · nothing was enabled
 */
class CreateTenantLegacyGeographyBackfillTest extends TestCase
{
    use DatabaseTransactions;

    private const FL       = '12';
    private const PINELLAS = '12103';

    /** In the corpus, and therefore matchable to a real cascade selection. */
    private const KNOWN_ZIP = '33767';

    /** Not in the corpus — exercises the preserved-history path. */
    private const UNKNOWN_ZIP = '99999';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('criteria_location_dna.geography_source', 'census');

        DB::table('census_states')->insert([
            ['geoid' => self::FL, 'usps' => 'FL', 'name' => 'Florida'],
        ]);
        DB::table('census_counties')->insert([
            ['geoid' => self::PINELLAS, 'state_geoid' => self::FL, 'countyfp' => '103',
             'name' => 'Pinellas County', 'basename' => 'Pinellas'],
        ]);
        DB::table('census_zctas')->insert([['zcta5' => self::KNOWN_ZIP]]);
        DB::table('census_zcta_counties')->insert([
            ['zcta5' => self::KNOWN_ZIP, 'county_geoid' => self::PINELLAS],
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // HARNESS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A stand-in for the auction model, answering only `info()`.
     *
     * That one method is the whole of the backfill's contract with the model. Building a real
     * auction would drag in the bid form, its drafts and its services to exercise a string lookup.
     *
     * @param  array<string, mixed>  $meta
     */
    private function auction(array $meta): object
    {
        return new class($meta) {
            /** @param array<string, mixed> $meta */
            public function __construct(private array $meta)
            {
            }

            public function info(string $key): mixed
            {
                return $this->meta[$key] ?? null;
            }
        };
    }

    /** A host carrying both traits, as every real Buyer/Tenant surface does. */
    private function host(): object
    {
        return new class {
            use HasSearchAreas;
            use HasGeographyCascade;

            public $state    = '';
            public $counties = [];
            public $cities   = [];
            public $zipCodes = [];

            public function load($auction): void
            {
                $this->loadSearchAreas($auction);
            }

            public function merge(array $ldna, $auction): array
            {
                return $this->mergeLegacyGeographyIntoBlob($ldna, $auction);
            }
        };
    }

    /**
     * Invoke the REAL component class's inherited backfill.
     *
     * `newInstanceWithoutConstructor()` keeps this a test of the rule rather than of Livewire's
     * mount: these components hydrate auctions, resolve drafts and touch several services during a
     * real mount, none of which this suite is asking about.
     *
     * `$ldna` is untyped here for the same reason it is untyped on the method under test: a typed
     * helper would raise the TypeError itself and the guard would never be reached.
     *
     * @param  mixed  $ldna
     * @return array<string, mixed>
     */
    private function mergeVia(string $componentClass, $ldna, array $meta): array
    {
        $reflection = new ReflectionClass($componentClass);
        $component  = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('mergeLegacyGeographyIntoBlob');
        $method->setAccessible(true);

        return $method->invoke($component, $ldna, $this->auction($meta));
    }

    /** Source of a component, for the wiring assertions. */
    private function sourceOf(string $componentClass): string
    {
        return (string) file_get_contents((new ReflectionClass($componentClass))->getFileName());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE EXTRACTION PRESERVED loadSearchAreas() BEHAVIOUR
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The Hire family's path is unchanged by the extraction.
     *
     * `LegacyZipBackfillTest` is the primary guard here and must stay green; this restates the
     * round trip through `loadSearchAreas()` so a regression is attributed to the extraction rather
     * than to the cascade.
     *
     * @test
     */
    public function load_search_areas_still_backfills_both_keys(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => json_encode(['state' => 'Florida']),
            'cities'                   => json_encode(['Clearwater, FL']),
            'zipCodes'                 => json_encode([self::KNOWN_ZIP, self::UNKNOWN_ZIP]),
        ]));

        $this->assertSame(['Clearwater, FL'], $host->existingLocationDna['cities']);
        $this->assertSame(
            [self::KNOWN_ZIP, self::UNKNOWN_ZIP],
            $host->existingLocationDna['zip_codes']
        );

        // The 9B-2 prefill and the raw passthrough are downstream of the extracted block and must
        // be undisturbed by it.
        $this->assertSame('Florida', $host->existingLocationDna['state']);
    }

    /** A blob that already carries the keys wins over the legacy mirror, in both callers. @test */
    public function a_populated_blob_is_never_overwritten_by_legacy_meta(): void
    {
        $blob = ['cities' => ['Dunedin, FL'], 'zip_codes' => ['34698']];
        $meta = ['cities' => json_encode(['Clearwater, FL']), 'zipCodes' => json_encode(['33767'])];

        foreach ([TenantOfferListing::class, TenantOfferListingEdit::class] as $component) {
            $merged = $this->mergeVia($component, $blob, $meta);

            $this->assertSame(['Dunedin, FL'], $merged['cities'], $component);
            $this->assertSame(['34698'], $merged['zip_codes'], $component);
        }
    }

    /** Running it twice changes nothing. @test */
    public function the_backfill_is_idempotent(): void
    {
        $meta = ['zipCodes' => json_encode([self::KNOWN_ZIP])];

        $once  = $this->mergeVia(TenantOfferListing::class, [], $meta);
        $twice = $this->mergeVia(TenantOfferListing::class, $once, $meta);

        $this->assertSame($once, $twice);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · EXISTING TENANT ZIP DATA SURVIVES LOAD
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The headline claim: a tenant record whose ZIPs live only in the legacy meta now carries them
     * into the blob, on BOTH surfaces.
     *
     * @test
     */
    public function legacy_tenant_zips_reach_the_blob_on_both_surfaces(): void
    {
        $meta = [
            'zipCodes' => json_encode([self::KNOWN_ZIP, self::UNKNOWN_ZIP]),
        ];

        foreach ([TenantOfferListing::class, TenantOfferListingEdit::class] as $component) {
            $merged = $this->mergeVia($component, ['state' => 'Florida'], $meta);

            $this->assertSame(
                [self::KNOWN_ZIP, self::UNKNOWN_ZIP],
                $merged['zip_codes'],
                "{$component}: legacy ZIPs must reach the blob"
            );
        }
    }

    /**
     * Integer-typed legacy ZIPs survive.
     *
     * This is the case the backfill exists for most: `[33767]` rather than `["33767"]`.
     * `GeographySelectionHydrator` accepts strings only and drops anything else silently, so an
     * int-typed ZIP would not even reach the preserved-labels path.
     *
     * @test
     */
    public function integer_typed_legacy_zips_are_cast_rather_than_dropped(): void
    {
        $merged = $this->mergeVia(
            TenantOfferListing::class,
            [],
            ['zipCodes' => json_encode([33767, 99999])]
        );

        $this->assertSame(['33767', '99999'], $merged['zip_codes']);
    }

    /** Blank and duplicate legacy entries are cleaned, not propagated. @test */
    public function blank_and_duplicate_legacy_zips_are_normalised(): void
    {
        $merged = $this->mergeVia(
            TenantOfferListing::class,
            [],
            ['zipCodes' => json_encode([self::KNOWN_ZIP, '', '  ', self::KNOWN_ZIP])]
        );

        $this->assertSame([self::KNOWN_ZIP], $merged['zip_codes']);
    }

    /**
     * A blob that decoded to a non-array is treated as empty rather than fataling.
     *
     * The callers build this argument as `$raw ? (json_decode($raw, true) ?? []) : []`, which does
     * not guarantee an array — a stored JSON scalar decodes to a scalar and `?? []` only catches
     * null. A typed parameter would raise a TypeError here, on the shipped Hire surfaces as well as
     * this one, for exactly the malformed records the backfill exists to rescue.
     *
     * Both currently-stored non-array blobs are falsy (`''` and `'0'`) and never reach this method,
     * so this guards a latent case rather than a live one — which is the point of guarding it.
     *
     * @test
     */
    public function a_non_array_blob_is_treated_as_empty_rather_than_fataling(): void
    {
        foreach ([5, 'text', true, null, 1.5] as $malformed) {
            $merged = $this->mergeVia(
                TenantOfferListing::class,
                $malformed,
                ['zipCodes' => json_encode([self::KNOWN_ZIP])]
            );

            $this->assertSame(
                [self::KNOWN_ZIP],
                $merged['zip_codes'],
                'A malformed blob must still receive the legacy backfill: ' . var_export($malformed, true)
            );
        }
    }

    /** The same malformed input reaches loadSearchAreas() without fataling. @test */
    public function load_search_areas_survives_a_scalar_blob(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => '5',          // truthy, decodes to int
            'zipCodes'                 => json_encode([self::KNOWN_ZIP]),
        ]));

        $this->assertIsArray($host->existingLocationDna);
        $this->assertSame([self::KNOWN_ZIP], $host->existingLocationDna['zip_codes']);
    }

    /** An absent legacy meta adds no key at all — absence is not an empty list. @test */
    public function absent_legacy_meta_adds_no_key(): void
    {
        $merged = $this->mergeVia(TenantOfferListing::class, ['state' => 'Florida'], []);

        $this->assertArrayNotHasKey('zip_codes', $merged);
        $this->assertArrayNotHasKey('cities', $merged);
        $this->assertSame(['state' => 'Florida'], $merged);
    }

    /**
     * Both Tenant load paths actually call the backfill, immediately after decoding the blob.
     *
     * The behavioural tests above prove the RULE; this proves it is REACHED. Source inspection is
     * the established idiom for this in the suite — see
     * `HireBuyerGeographyCascadeWiringTest::the_merge_immediately_precedes_the_write_on_every_surface`
     * — because a real mount would drag in drafts, services and auth to observe one call.
     *
     * @test
     */
    public function both_tenant_load_paths_invoke_the_backfill_after_decoding(): void
    {
        foreach ([TenantOfferListing::class, TenantOfferListingEdit::class] as $component) {
            $source = $this->sourceOf($component);

            $decode = strpos($source, "\$this->location_dna_preferences_json = \$ldnaRaw ?? '';");
            $merge  = strpos($source, 'mergeLegacyGeographyIntoBlob(');

            $this->assertNotFalse($decode, "{$component}: blob decode not found");
            $this->assertNotFalse($merge, "{$component}: backfill is never called");
            $this->assertGreaterThan(
                $decode,
                $merge,
                "{$component}: the backfill must run AFTER the blob is decoded, or it merges into nothing"
            );
        }
    }

    /**
     * With the ZIPs in the blob, a cascade hydration would find them.
     *
     * This is what T1 buys, stated as the outcome rather than the mechanism. The cascade is NOT
     * enabled for tenant here — the host is a bare trait carrier — so this asserts the
     * precondition holds, not that Create Tenant is wired.
     *
     * @test
     */
    public function the_backfilled_blob_is_hydratable_by_the_cascade(): void
    {
        // State and counties are part of the fixture because a ZIP resolves WITHIN the selected
        // counties — the hydrator walks the tiers in order, and a ZIP with no county context above
        // it is unplaceable by construction rather than by absence from the corpus. Omitting them
        // would make this test pass through the preserved-history path for both ZIPs and prove
        // nothing about resolution.
        $merged = $this->mergeVia(
            TenantOfferListing::class,
            ['state' => 'Florida', 'counties' => ['Pinellas County, FL']],
            ['zipCodes' => json_encode([self::KNOWN_ZIP, self::UNKNOWN_ZIP])]
        );

        $hydrated = (new \App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator(
            app(\App\Services\LocationDna\Criteria\CriteriaGeographyRepository::class),
            new \App\Services\LocationDna\Criteria\NullCriteriaNeighborhoodRepository(),
        ))->fromLabels($merged);

        // The corpus ZIP resolves to a real selection...
        $this->assertContains(self::KNOWN_ZIP, $hydrated->selection->zipCodes);

        // ...and the one the corpus cannot match is PRESERVED rather than dropped. Both halves
        // matter: without the second, enabling the cascade would quietly delete unknown ZIPs.
        $this->assertContains(self::UNKNOWN_ZIP, $hydrated->preserved->toArray()['zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · NO STORAGE FORMAT CHANGE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The backfill introduces no key beyond the two it fills.
     *
     * A new key in this document would have to be learned by six consumers, none of which would
     * read it.
     *
     * @test
     */
    public function no_new_blob_key_is_introduced(): void
    {
        $before = [
            'state'             => 'Florida',
            'counties'          => ['Pinellas County, FL'],
            'polygons'          => [['id' => 1]],
            'radius_searches'   => [],
            'flexible_location' => true,
            'location_notes'    => 'near the water',
        ];

        $after = $this->mergeVia(TenantOfferListing::class, $before, [
            'cities'   => json_encode(['Clearwater, FL']),
            'zipCodes' => json_encode([self::KNOWN_ZIP]),
        ]);

        $this->assertSame(
            ['state', 'counties', 'polygons', 'radius_searches', 'flexible_location',
             'location_notes', 'cities', 'zip_codes'],
            array_keys($after)
        );
    }

    /** Everything the map widget owns survives untouched. @test */
    public function map_widget_state_is_never_disturbed(): void
    {
        $before = [
            'polygons'          => [['id' => 1, 'points' => [[1, 2]]]],
            'radius_searches'   => [['lat' => 27.9, 'lng' => -82.8, 'miles' => 5]],
            'flexible_location' => true,
            'location_notes'    => 'near the water',
        ];

        $after = $this->mergeVia(TenantOfferListing::class, $before, [
            'zipCodes' => json_encode([self::KNOWN_ZIP]),
        ]);

        foreach (array_keys($before) as $key) {
            $this->assertSame($before[$key], $after[$key], "{$key} must survive the backfill");
        }
    }

    /**
     * The backfill writes nothing.
     *
     * It takes a blob and returns a blob. The merged value reaches storage only through an explicit
     * save, so loading a record and navigating away must change nothing.
     *
     * @test
     */
    public function the_backfill_performs_no_write(): void
    {
        $writes  = 0;
        $auction = new class($writes) {
            public function __construct(public int &$writes)
            {
            }

            public function info(string $key): mixed
            {
                return $key === 'zipCodes' ? json_encode(['33767']) : null;
            }

            public function saveMeta(string $key, $value): void
            {
                $this->writes++;
            }
        };

        $reflection = new ReflectionClass(TenantOfferListing::class);
        $method     = $reflection->getMethod('mergeLegacyGeographyIntoBlob');
        $method->setAccessible(true);
        $method->invoke($reflection->newInstanceWithoutConstructor(), [], $auction);

        $this->assertSame(0, $writes, 'The backfill must never write to the model.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · BUYER BEHAVIOUR IS UNCHANGED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The Buyer components are untouched by T1.
     *
     * They keep their own inline legacy-`cities` migration and gain no ZIP backfill, because the
     * Buyer family owns no ZIP state at all: no `$zipCodes` property, no `zipCodes` meta write, no
     * ZIP control on the tab. Adding the call there would be inert at best and would widen the
     * blast radius of a change whose whole purpose is to be a narrow precondition.
     *
     * @test
     */
    public function the_buyer_components_do_not_call_the_backfill(): void
    {
        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $component) {
            $this->assertStringNotContainsString(
                'mergeLegacyGeographyIntoBlob',
                $this->sourceOf($component),
                "{$component}: T1 must not reach the Buyer family"
            );
        }
    }

    /** Buyer still owns no ZIP state, so no ZIP rule can apply to it. @test */
    public function the_buyer_components_own_no_zip_state(): void
    {
        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $component) {
            $source = $this->sourceOf($component);

            $this->assertStringNotContainsString('public $zipCodes', $source, $component);
            $this->assertStringNotContainsString("saveMeta('zipCodes'", $source, $component);
        }
    }

    /** Buyer keeps its own inline legacy-cities migration. @test */
    public function the_buyer_components_keep_their_inline_cities_migration(): void
    {
        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $component) {
            $this->assertStringContainsString(
                "\$legacyCitiesRaw = \$auction->info('cities');",
                $this->sourceOf($component),
                "{$component}: the inline cities migration must remain"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · NOTHING WAS ENABLED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The backfill introduced no coupling to the cascade, and enabled nothing.
     *
     * WHAT THIS TEST USED TO ASSERT, AND WHY IT NO LONGER CAN
     * -------------------------------------------------------
     * It originally required the two Tenant components to carry neither cascade trait — true while
     * T1 stood alone, and the point of that assertion was that a PRECONDITION had not quietly
     * become the WIRING. T2 is the wiring step and adds both traits deliberately, so keeping the
     * old form would assert that an approved step had not happened.
     *
     * What survives is the claim that belongs to T1 specifically: the backfill is a blob-merging
     * rule that knows nothing about geography selection, and it did not switch anything on. The
     * wiring's own invariants — role mapping, boot order, gating — are asserted directly by
     * {@see CreateTenantGeographyWiringTest}, which is where they belong.
     *
     * @test
     */
    public function the_backfill_introduced_no_cascade_coupling_and_enabled_nothing(): void
    {
        $trait = (string) file_get_contents(
            base_path('app/Http/Livewire/Concerns/HasSearchAreas.php')
        );

        // The backfill lives in HasSearchAreas and must stay ignorant of the cascade: it merges a
        // blob, it does not resolve, project or validate a geography selection.
        //
        // CODE COUPLING, NOT PROSE. The trait legitimately NAMES the cascade three times in
        // comments — to explain why the ZIP backfill is a precondition, and why the non-array guard
        // mirrors the one in applyGeographyCascadeToPayload(). Those cross-references are the point
        // of the documentation, so a bare string search would forbid exactly the explanation that
        // makes this file understandable. What must not appear is a `use` of the trait or a read of
        // any of its state.
        foreach ([
            'use \App\Http\Livewire\Concerns\HasGeographyCascade',
            'use HasGeographyCascade',
            '$this->geo',                 // geoCascadeEnabled, geoStateId, geoZipCodes, …
            '$this->geographyProjection',
            '$this->applyGeographyCascadeToPayload',
        ] as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $trait,
                "HasSearchAreas must not couple to the cascade ({$marker})."
            );
        }

        // And T1 enabled nothing — still true, and still worth asserting from this suite because
        // the backfill is what makes enabling SAFE, which is exactly when it is tempting to do both
        // in one step.
        $config = require base_path('config/criteria_location_dna.php');
        $this->assertNotContains('create_tenant', $config['geography_cascade_workflows']);
        $this->assertFalse($config['geography_cascade_enabled']);
    }

    /** The preservation constraints this task was given, asserted rather than assumed. @test */
    public function the_frozen_surfaces_are_frozen(): void
    {
        $cascade = (string) file_get_contents(
            base_path('app/Http/Livewire/Concerns/HasGeographyCascade.php')
        );

        $this->assertStringContainsString(
            "private const ZIP_MIRROR_WORKFLOWS = ['hire_tenant'];",
            $cascade,
            'ZIP_MIRROR_WORKFLOWS must be unchanged.'
        );
        $this->assertStringNotContainsString(
            'mergeLegacyGeographyIntoBlob',
            $cascade,
            'HasGeographyCascade must know nothing of the backfill.'
        );

        // The tenant tab's cascade surface is T3's business, not T1's, and it has since landed —
        // guarded by `$geoCascadeEnabled ?? false` and inert while the workflow stays unlisted.
        // What this suite still owns is that the BACKFILL enabled nothing, asserted above against
        // the config rather than against the view.
    }
}
