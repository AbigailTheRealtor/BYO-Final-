<?php

namespace Tests\Feature\LocationDna;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Structural regression guard for public geometry serialisation.
 *
 * CATEGORY — STRUCTURAL, AND SUPPLEMENTARY ONLY.
 * ----------------------------------------------
 * Read this before citing a green run as evidence of anything.
 *
 * These assertions inspect source text and route registration. They prove that
 * the containment call sites are still present and that no NEW obvious
 * serialisation path has been added to a public view. They prove NOTHING about
 * runtime behaviour:
 *
 *   - They cannot prove containment works. PublicGeometryContainmentTest does
 *     that, by asserting on real response bodies.
 *   - They are not browser verification. This project has no browser automation,
 *     so no test here can prove what a browser does with what the server sends.
 *   - A file can satisfy every assertion here and still leak, because a leak can
 *     arrive through a path that names no geometry key at all — a generic
 *     "render every meta key" loop, for instance. A keyword search cannot find
 *     that. Only the route-level body assertions can.
 *
 * This file is a cheap early-warning fence, nothing more.
 */
class PublicGeometryRegressionGuardTest extends TestCase
{
    /** The four public viewer controllers, all of which must apply the projection. */
    private const PUBLIC_CONTROLLERS = [
        'app/Http/Controllers/BuyerOfferListingController.php',
        'app/Http/Controllers/TenantOfferListingController.php',
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
    ];

    /** The four public viewer routes the containment audit covered. */
    private const PUBLIC_ROUTE_NAMES = [
        'offer.listing.buyer.view',
        'offer.listing.tenant.view',
        'buyer.criteria.view',
        'tenant.criteria.auction.view',
    ];

    private function source(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path, "Expected {$relativePath} to exist.");

        return (string) file_get_contents($path);
    }

    public function test_every_public_viewer_controller_applies_the_projection(): void
    {
        foreach (self::PUBLIC_CONTROLLERS as $controller) {
            $this->assertStringContainsString(
                'PublicGeometryProjection',
                $this->source($controller),
                "{$controller} serves a public viewer route but no longer references "
                . 'PublicGeometryProjection. Public geometry containment may have been removed.'
            );
        }
    }

    public function test_offer_listing_controllers_strip_the_blob_from_the_meta_bag(): void
    {
        foreach ([
            'app/Http/Controllers/BuyerOfferListingController.php',
            'app/Http/Controllers/TenantOfferListingController.php',
        ] as $controller) {
            $this->assertStringContainsString(
                'stripFromMetaBag',
                $this->source($controller),
                "{$controller} no longer strips the blob from the decoded meta bag. A "
                . 'catch-all meta renderer would be able to reintroduce the exposure.'
            );
        }
    }

    public function test_tenant_criteria_view_has_no_second_decode_of_its_own(): void
    {
        // This view's public bid-list panel used to decode
        // `location_dna_preferences` itself, bypassing the controller variable and
        // printing the radius-centre address and free-text notes. It must consume
        // the controller's already-projected variable instead, so that this page
        // has exactly one containment point rather than a Blade-local mask.
        $view = $this->source('resources/views/tenant_criteria/view.blade.php');

        $this->assertStringNotContainsString(
            "info('location_dna_preferences')",
            $view,
            'tenant_criteria/view.blade.php decodes location_dna_preferences itself again. '
            . 'That is a second, uncontained route from the stored blob to a public page.'
        );

        $this->assertStringContainsString(
            '$dnaPrefs = $locationDnaPreferences ?? null;',
            $view,
            'The public bid-list panel no longer reads the controller-projected variable.'
        );
    }

    public function test_no_public_view_decodes_the_blob_itself(): void
    {
        // Generalises the assertion above across every public viewer template: a
        // template that re-decodes the stored blob has re-created the exposure the
        // controller projection closes, whatever it then chooses to render.
        foreach ($this->publicViews() as $view) {
            $this->assertStringNotContainsString(
                "info('location_dna_preferences')",
                $this->source($view),
                "{$view} decodes location_dna_preferences directly. Public views must "
                . 'receive the projected array from their controller.'
            );
        }
    }

    public function test_shared_component_still_carries_the_presence_indicator(): void
    {
        $component = $this->source('resources/views/components/location-dna-map.blade.php');

        $this->assertStringContainsString('Search area preferences provided', $component);
        $this->assertStringContainsString('Additional location preferences provided', $component);
    }

    public function test_private_editing_surface_still_emits_full_geometry(): void
    {
        // The owner's editor is where authorised users legitimately obtain exact
        // geometry. Containment must not have touched it. If this fails, the fix
        // over-reached and broke the private path.
        $partial = $this->source('resources/views/partials/location-dna/map-input.blade.php');

        $this->assertStringContainsString('polygons:          @json($ldnaPolygons)', $partial);
        $this->assertStringContainsString('radius_searches:   @json($ldnaRadii)', $partial);
    }

    public function test_no_public_view_serialises_geometry_directly(): void
    {
        // Deliberately narrow: flags a public view that json-encodes a geometry
        // key by name. See the class docblock for why this cannot be exhaustive.
        foreach ($this->publicViews() as $view) {
            $source = $this->source($view);

            foreach (['@json($polygons)', '@json($radii)', '@json($radius_searches)'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    "{$view} serialises geometry directly ({$needle}). Public views must "
                    . 'receive a projected array instead.'
                );
            }
        }
    }

    public function test_the_audited_public_routes_still_resolve_to_the_audited_controllers(): void
    {
        // If one of these routes is re-pointed at a controller that does not apply
        // the projection, every other assertion in this file still passes.
        $expected = [
            'offer.listing.buyer.view'     => 'BuyerOfferListingController@view',
            'offer.listing.tenant.view'    => 'TenantOfferListingController@view',
            'buyer.criteria.view'          => 'BuyerCriteriaAuctionController@view',
            'tenant.criteria.auction.view' => 'TenantCriteriaAuctionController@view',
        ];

        foreach (self::PUBLIC_ROUTE_NAMES as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Public viewer route '{$name}' is no longer registered.");
            $this->assertStringContainsString(
                $expected[$name],
                (string) $route->getActionName(),
                "Public viewer route '{$name}' no longer resolves to the audited controller action."
            );
        }
    }

    public function test_the_set_of_views_rendering_the_shared_viewer_is_unchanged(): void
    {
        // If a new route starts rendering the shared viewer component, the
        // containment audit is stale and must be re-run before shipping.
        $consumers = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(resource_path('views') . '/', '', $file->getPathname());

            // The component's own header docblock documents its usage tag; it is
            // the definition, not a consumer.
            if ($relative === 'components/location-dna-map.blade.php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), '<x-location-dna-map')) {
                $consumers[] = $relative;
            }
        }

        sort($consumers);

        // The seller and landlord entries pass `:preferences="null"` and a
        // property pin only — the listing's own address, which those pages exist
        // to publish. They carry no demand-side search geometry.
        $this->assertSame([
            'buyer_criteria/view.blade.php',
            'offer-listing/buyer/view.blade.php',
            'offer-listing/landlord/view.blade.php',
            'offer-listing/seller/view.blade.php',
            'offer-listing/tenant/view.blade.php',
            'tenant_criteria/view.blade.php',
        ], $consumers, 'The set of views rendering the shared Location DNA viewer has changed. '
            . 'Re-run the public geometry exposure audit before shipping.');
    }

    /** @return list<string> */
    private function publicViews(): array
    {
        return [
            'resources/views/offer-listing/buyer/view.blade.php',
            'resources/views/offer-listing/tenant/view.blade.php',
            'resources/views/buyer_criteria/view.blade.php',
            'resources/views/tenant_criteria/view.blade.php',
        ];
    }
}
