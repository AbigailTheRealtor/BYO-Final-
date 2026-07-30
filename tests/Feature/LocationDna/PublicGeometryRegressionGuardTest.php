<?php

namespace Tests\Feature\LocationDna;

use Tests\TestCase;

/**
 * G0.1 — structural regression guard for public geometry serialisation.
 *
 * CATEGORY — STRUCTURAL, AND SUPPLEMENTARY ONLY.
 * ----------------------------------------------
 * Read this before citing a green run as evidence of anything.
 *
 * These assertions inspect source text. They prove that the containment call
 * sites are still present and that no NEW obvious serialisation path has been
 * added to a public view. They prove NOTHING about runtime behaviour:
 *
 *   - They cannot prove containment works. PublicGeometryContainmentTest does
 *     that, by asserting on real response bodies.
 *   - They are not browser verification. This project has no browser automation
 *     (G0.1 plan §6.2), so no test in this repository can currently prove what a
 *     browser does with what the server sends.
 *   - A file can satisfy every assertion here and still leak, because the leak
 *     that motivated this suite did not name a geometry key at all: the tenant
 *     offer view printed the whole blob through a generic "iterate every unknown
 *     meta key" loop. A keyword search could not have found it, and cannot find
 *     its successor. Only the route-level body assertions can.
 *
 * v1.2 §16.1 forbids treating structural inspection as equivalent to behavioural
 * verification. This file exists as a cheap early-warning fence, nothing more.
 */
class PublicGeometryRegressionGuardTest extends TestCase
{
    /** The four public viewer routes' controllers, all of which must contain the projection. */
    private const PUBLIC_CONTROLLERS = [
        'app/Http/Controllers/BuyerOfferListingController.php',
        'app/Http/Controllers/TenantOfferListingController.php',
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
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
                . 'PublicGeometryProjection. G0.1 containment may have been removed.'
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
                "{$controller} no longer strips the canonical blob from the decoded meta bag. "
                . 'A catch-all meta renderer would reintroduce the F-P1 exposure.'
            );
        }
    }

    public function test_tenant_criteria_view_projects_its_independent_decode(): void
    {
        // This view decodes location_dna_preferences itself, bypassing the
        // controller variable, so it needs the projection applied locally.
        $view = $this->source('resources/views/tenant_criteria/view.blade.php');

        $this->assertStringContainsString('PublicGeometryProjection', $view);
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
        // geometry (D4). G0.1 must not have touched it. If this fails, the fix
        // over-reached and broke the private path.
        $partial = $this->source('resources/views/partials/location-dna/map-input.blade.php');

        $this->assertStringContainsString('polygons:          @json($ldnaPolygons)', $partial);
        $this->assertStringContainsString('radius_searches:   @json($ldnaRadii)', $partial);
    }

    public function test_no_public_view_serialises_geometry_directly(): void
    {
        // Deliberately narrow: flags a public view that json-encodes a geometry
        // key by name. See the class docblock for why this cannot be exhaustive.
        $publicViews = [
            'resources/views/offer-listing/buyer/view.blade.php',
            'resources/views/offer-listing/tenant/view.blade.php',
            'resources/views/buyer_criteria/view.blade.php',
            'resources/views/tenant_criteria/view.blade.php',
        ];

        foreach ($publicViews as $view) {
            $source = $this->source($view);

            foreach (['@json($polygons)', '@json($radii)', '@json($radius_searches)'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    "{$view} serialises geometry directly ({$needle}). Public views must receive "
                    . 'a projected array instead.'
                );
            }
        }
    }

    public function test_the_four_public_routes_are_still_the_audited_set(): void
    {
        // If a new unauthenticated route starts rendering the shared viewer
        // component, G0.1's audit is stale and containment must be re-checked.
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

        $this->assertSame([
            'buyer_criteria/view.blade.php',
            'offer-listing/buyer/view.blade.php',
            'offer-listing/landlord/view.blade.php',
            'offer-listing/seller/view.blade.php',
            'offer-listing/tenant/view.blade.php',
            'tenant_criteria/view.blade.php',
        ], $consumers, 'The set of views rendering the shared Location DNA viewer has changed. '
            . 'Re-run the G0.1 exposure audit before shipping.');
    }
}
