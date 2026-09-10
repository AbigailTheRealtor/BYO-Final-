<?php

namespace Tests\Feature\Product;

use App\Support\Product\ProductSurfaceCatalog;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every registered route resolves to EXACTLY ONE product disposition.
 *
 * This is the no-drop contract for the product boundary, and it is the reason
 * the runtime gate can refuse on a positive identification rather than guessing:
 * a route nobody classified fails the build here, naming the method and URI, long
 * before it can leak into a BidYourAgent deployment or 404 on a shared one.
 *
 * There is deliberately no generic bucket. Adding a route means deciding, in
 * ProductSurfaceCatalog, which product it belongs to.
 */
class ProductSurfaceContractTest extends TestCase
{
    /** @test */
    public function every_registered_route_has_exactly_one_product_disposition(): void
    {
        $unclassified = [];
        $ambiguous    = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $method = $this->primaryMethod($route);
            $uri    = $route->uri();

            $hits = ProductSurfaceCatalog::dispositionsFor($method, $uri);

            if ($hits === []) {
                $unclassified[] = sprintf('%s %s (%s)', $method, $uri, $route->getName() ?: 'unnamed');
            } elseif (count($hits) > 1) {
                $ambiguous[] = sprintf(
                    '%s %s (%s) matches: %s',
                    $method,
                    $uri,
                    $route->getName() ?: 'unnamed',
                    implode(', ', $hits)
                );
            }
        }

        $this->assertSame([], $unclassified, sprintf(
            "%d route(s) are not classified in ProductSurfaceCatalog. Decide which product "
            . "each belongs to and add it — there is no generic bucket:\n  %s",
            count($unclassified),
            implode("\n  ", $unclassified)
        ));

        $this->assertSame([], $ambiguous, sprintf(
            "%d route(s) match more than one disposition. A route belongs to exactly one "
            . "product; overlapping patterns make the answer depend on evaluation order:\n  %s",
            count($ambiguous),
            implode("\n  ", $ambiguous)
        ));
    }

    /** @test */
    public function the_hire_agent_surface_is_never_classified_as_bidyouroffer(): void
    {
        // The four consumer Hire Agent entry points, the four agent bid pages and
        // the seller accept/reject endpoints restored by PR #143 / PR #145.
        $mustSurvive = [
            ['GET',  'hire/agent/seller'],
            ['GET',  'buyer/add-auction'],
            ['GET',  'landlord/hire/agent/auction'],
            ['GET',  'hire/agent/auction/{user_type?}'],
            ['POST', 'hire/agent/seller/bid/accept'],
            ['POST', 'hire/agent/seller/bid/reject'],
            ['GET',  'agent/seller/bid/add/{auctionId}'],
            ['GET',  'buyer/agent/auction/bid/{auctionId}'],
            ['GET',  'landlord/agent/auction/bid/{auctionId}'],
            ['GET',  'tenant/agent/auction/bid/{auctionId}'],
        ];

        foreach ($mustSurvive as [$method, $uri]) {
            $this->assertSame(
                ProductSurfaceCatalog::BIDYOURAGENT,
                ProductSurfaceCatalog::dispositionFor($method, $uri),
                "{$method} {$uri} must be a BidYourAgent surface."
            );
        }
    }

    /** @test */
    public function the_offer_listing_surface_is_classified_as_bidyouroffer_only(): void
    {
        $mustBeRefused = [
            ['GET',  'offer-listing/seller'],
            ['GET',  'offer-listing/buyer'],
            ['GET',  'offer-listing/landlord'],
            ['GET',  'offer-listing/tenant/{user_type?}'],
            ['GET',  'search/properties-auctions'],
            ['GET',  'search/seller-listings'],
            ['GET',  'my-showings/manage'],
            ['GET',  'match-check'],
            ['GET',  'stellar/buyer/results'],
            ['GET',  'add-listing'],
            ['GET',  'offer/listing/{offer_type?}'],
        ];

        foreach ($mustBeRefused as [$method, $uri]) {
            $this->assertSame(
                ProductSurfaceCatalog::BIDYOUROFFER_ONLY,
                ProductSurfaceCatalog::dispositionFor($method, $uri),
                "{$method} {$uri} must be a BidYourOffer-only surface."
            );
        }
    }

    /** @test */
    public function shared_account_surfaces_are_never_bidyouroffer_only(): void
    {
        foreach ([['GET', 'dashboard'], ['GET', 'settings'], ['GET', 'messages'],
                  ['GET', 'my-listings'], ['GET', 'login'], ['GET', '/']] as [$method, $uri]) {
            $this->assertNotSame(
                ProductSurfaceCatalog::BIDYOUROFFER_ONLY,
                ProductSurfaceCatalog::dispositionFor($method, $uri),
                "{$method} {$uri} is shared infrastructure and must never be refused by product."
            );
        }
    }

    private function primaryMethod(RoutingRoute $route): string
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        return $methods[0] ?? 'GET';
    }
}
