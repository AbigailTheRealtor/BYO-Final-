<?php

namespace Tests\Feature\Location;

use Tests\TestCase;

/**
 * The four Create Offer components resolve coordinates before they dispatch,
 * and G5 added no dispatch of its own.
 *
 * WHY STRUCTURAL
 * --------------
 * What G5 changed in these components is one call per dispatch site and its
 * position relative to the dispatch. Position is the whole correctness argument
 * — the pipeline prefers `pre_lat`/`pre_lng` over geocoding, so resolving first
 * is what lets the already-dispatched job carry the coordinate and its
 * provenance into `property_location_dna`; resolving afterwards would win that
 * race only by luck. Ordering inside a 228 KB component is not something a
 * behavioural test observes, and it is exactly what a careless edit breaks.
 *
 * The behaviour those calls produce is covered against the real service, the
 * real adapters and the real pipeline in
 * {@see CreateOfferCoordinateIntegrationTest}.
 */
class CreateOfferCoordinateWiringTest extends TestCase
{
    /**
     * The dispatch baseline recorded before G5 touched anything.
     *
     * These numbers are the point of the test, not documentation of it. Location
     * DNA dispatch already existed here; G5's invariant is that it did not grow.
     */
    private const DISPATCH_SITES = [
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php'         => 1,
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php'     => 3,
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php'     => 2,
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php' => 3,
    ];

    /**
     * 17 before G6, 21 after. G6 added four — the Hire Agent publish boundaries,
     * which had no Location DNA dispatch at all. The Create Offer nine above are
     * unchanged, which is what {@see self::DISPATCH_SITES} pins independently;
     * this number exists to catch a dispatch appearing anywhere else.
     *
     * @see \Tests\Feature\HireAgent\HireAgentCoordinateWiringTest
     */
    private const APP_WIDE_DISPATCH_BASELINE = 21;

    private function source(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, "{$path} must be readable");

        return $contents;
    }

    /** Line numbers of every match, 1-indexed. @return list<int> */
    private function lines(string $source, string $needle): array
    {
        $hits = [];

        foreach (preg_split('/\R/', $source) ?: [] as $i => $line) {
            if (str_contains($line, $needle)) {
                $hits[] = $i + 1;
            }
        }

        return $hits;
    }

    // ── the dispatch baseline is unchanged ──────────────────────────────────

    public function test_no_create_offer_component_gained_a_dispatch(): void
    {
        foreach (self::DISPATCH_SITES as $path => $expected) {
            $this->assertCount(
                $expected,
                $this->lines($this->source($path), 'ComputeLocationDna::dispatch'),
                basename($path) . ' must still have exactly ' . $expected . ' dispatch site(s) — G5 adds none'
            );
        }
    }

    public function test_the_application_wide_dispatch_count_is_unchanged(): void
    {
        $total = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $total += count($this->lines((string) file_get_contents($file->getPathname()), 'ComputeLocationDna::dispatch'));
            }
        }

        $this->assertSame(
            self::APP_WIDE_DISPATCH_BASELINE,
            $total,
            'G5 must neither add nor remove a Location DNA dispatch anywhere'
        );
    }

    public function test_the_shared_service_dispatches_nothing(): void
    {
        foreach ([
            'app/Services/Location/PropertyCoordinatePersistenceService.php',
            'app/Http/Livewire/OfferListing/Concerns/ResolvesPropertyCoordinates.php',
        ] as $path) {
            $code = $this->codeWithoutComments($this->source($path));

            $this->assertStringNotContainsString('ComputeLocationDna', $code, basename($path));
            $this->assertStringNotContainsString('LocationDnaPipelineRunner', $code, basename($path));
            $this->assertStringNotContainsString('dispatch(', $code, basename($path));
        }
    }

    // ── resolution happens, and happens first ───────────────────────────────

    public function test_every_dispatch_site_resolves_coordinates_immediately_before_it(): void
    {
        foreach (self::DISPATCH_SITES as $path => $expected) {
            $source = $this->source($path);

            $resolves   = $this->lines($source, 'resolvePropertyCoordinates(');
            $dispatches = $this->lines($source, 'ComputeLocationDna::dispatch');

            $this->assertCount(
                $expected,
                $resolves,
                basename($path) . ' must resolve coordinates once per dispatch site'
            );

            foreach ($dispatches as $i => $dispatchLine) {
                $this->assertLessThan(
                    $dispatchLine,
                    $resolves[$i],
                    basename($path) . ": resolution must precede the dispatch at line {$dispatchLine}"
                );
            }
        }
    }

    public function test_each_component_uses_the_shared_trait(): void
    {
        // Rather than four copies of the resolver orchestration.
        foreach (array_keys(self::DISPATCH_SITES) as $path) {
            $this->assertStringContainsString(
                'use ResolvesPropertyCoordinates;',
                $this->source($path),
                basename($path) . ' must use the shared concern'
            );
        }
    }

    /**
     * Four Create Offer components (G5) plus four Hire Agent components (G6).
     *
     * The point of the assertion is unchanged and is not about the number: the
     * trait resolves ONE property's coordinate, so it may only ever reach flows
     * that have one property. Buyer and Tenant carry multi-area search criteria
     * and no property_lat at all, and must never appear in this list.
     */
    public function test_the_trait_is_used_by_exactly_the_seller_landlord_property_components(): void
    {
        $users = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Livewire'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'use ResolvesPropertyCoordinates;')) {
                $users[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        sort($users);
        $expected = array_merge(array_keys(self::DISPATCH_SITES), [
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        ]);
        sort($expected);

        $this->assertSame($expected, $users, 'Buyer and Tenant must not acquire coordinate resolution');
    }

    // ── save boundaries only ────────────────────────────────────────────────

    public function test_resolution_is_never_triggered_from_a_render_or_lifecycle_hook(): void
    {
        // A geocoder behind a keystroke is how a free public service stops being
        // available to us. The G4 caps exist to survive that mistake, not to
        // license it.
        foreach (array_keys(self::DISPATCH_SITES) as $path) {
            $source = $this->source($path);

            foreach ($this->lines($source, 'resolvePropertyCoordinates(') as $line) {
                $enclosing = $this->enclosingFunction($source, $line);

                $this->assertNotNull($enclosing, basename($path) . ": line {$line} is not inside a method");

                foreach (['render', 'mount', 'hydrate', 'dehydrate', 'updating', 'updated'] as $forbidden) {
                    $this->assertStringStartsNotWith(
                        $forbidden,
                        $enclosing,
                        basename($path) . ": resolution must not run from {$enclosing}()"
                    );
                }
            }
        }
    }

    // ── untouched neighbours ────────────────────────────────────────────────

    public function test_has_mls_import_is_untouched_and_still_fails_closed(): void
    {
        // Shared with Buyer/Tenant, so G5 left it alone. Its legacy Google
        // fallback stays dead behind GOOGLE_PLACES_ENABLED=false — recorded as a
        // separate cleanup item, not smuggled into a coordinate change.
        $source = $this->source('app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php');

        $this->assertStringContainsString(
            'LocationDnaGeocodeService',
            $source,
            'The legacy fallback is expected to still be here, unchanged'
        );
        $this->assertStringNotContainsString(
            'resolvePropertyCoordinates',
            $source,
            'G5 must not wire the shared MLS concern'
        );
        $this->assertFalse(
            config('google_places.enabled'),
            'The legacy Google path must remain fail-closed'
        );
    }

    public function test_g5_adds_no_google_reference(): void
    {
        foreach ([
            'app/Services/Location/PropertyCoordinatePersistenceService.php',
            'app/Services/Location/Coordinates/PropertyCoordinateMeta.php',
            'app/Services/Location/Coordinates/Adapters/StandardCoordinateLadder.php',
            'app/Http/Livewire/OfferListing/Concerns/ResolvesPropertyCoordinates.php',
        ] as $path) {
            $code = $this->codeWithoutComments($this->source($path));

            foreach (['googleapis', 'maps.googleapis', 'GOOGLE_PLACES', 'GooglePlaces', 'GoogleGeocoder'] as $needle) {
                $this->assertStringNotContainsString($needle, $code, basename($path) . " must not reference {$needle}");
            }
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** The name of the method a given line sits in, scanning upwards. */
    private function enclosingFunction(string $source, int $line): ?string
    {
        $lines = preg_split('/\R/', $source) ?: [];

        for ($i = $line - 1; $i >= 0; $i--) {
            if (preg_match('/function\s+(\w+)\s*\(/', $lines[$i] ?? '', $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function codeWithoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    }
}
