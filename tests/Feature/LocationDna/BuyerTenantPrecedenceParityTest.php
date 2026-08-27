<?php

namespace Tests\Feature\LocationDna;

use App\Http\Controllers\BuyerCriteriaAuctionController;
use App\Http\Controllers\BuyerOfferListingController;
use App\Http\Controllers\TenantCriteriaAuctionController;
use App\Http\Controllers\TenantOfferListingController;
use App\Services\LocationDna\BoundaryLookupService;
use App\Services\LocationDna\LocationDnaEnrichmentRunner;
use App\Services\LocationDna\LocationIntelligenceComposer;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Buyer and Tenant must obey ONE canonical precedence, because they run the same
 * code — not because two parallel implementations were separately taught the same
 * rules.
 *
 * The precedence rules themselves are proven once, against the services that own
 * them:
 *   @see \Tests\Unit\Services\LocationDna\BoundaryLookupServicePrecedenceTest
 *   @see \Tests\Unit\Services\LocationDna\LocationDnaPoiGeometryPrecedenceTest
 *
 * Re-asserting those rules per role would not be additional proof — it would be
 * the same assertions against the same object. What actually needs proving, and
 * what nothing proved before, is the WIRING: that all four consumer entry points
 * resolve the one implementation those tests pin, so a precedence fix cannot
 * reach one role and miss another.
 *
 * Seller and Landlord are deliberately absent. They are the supply side: they
 * hold a property at a known location and declare no search preferences, so no
 * preference-precedence applies to them.
 */
class BuyerTenantPrecedenceParityTest extends TestCase
{
    /** The four consumer entry points that render Location DNA geography. */
    private function consumerEntryPoints(): array
    {
        return [
            'buyer criteria auction'  => [BuyerCriteriaAuctionController::class, 'view'],
            'tenant criteria auction' => [TenantCriteriaAuctionController::class, 'view'],
            'buyer offer listing'     => [BuyerOfferListingController::class, 'view'],
            'tenant offer listing'    => [TenantOfferListingController::class, 'view'],
        ];
    }

    /** @return list<string> the type names this method asks the container for */
    private function injectedTypes(string $class, string $method): array
    {
        $params = (new ReflectionClass($class))->getMethod($method)->getParameters();

        $types = [];

        foreach ($params as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $types[] = $type->getName();
            }
        }

        return $types;
    }

    public function test_both_roles_inject_the_same_boundary_lookup_service(): void
    {
        foreach ($this->consumerEntryPoints() as $label => [$class, $method]) {
            $this->assertContains(
                BoundaryLookupService::class,
                $this->injectedTypes($class, $method),
                "The {$label} entry point must resolve the shared BoundaryLookupService"
            );
        }
    }

    public function test_both_roles_reach_the_same_poi_geometry_derivation(): void
    {
        // The runner that owns Polygon > Radius is reached through the composer,
        // which every consumer entry point injects.
        foreach ($this->consumerEntryPoints() as $label => [$class, $method]) {
            $this->assertContains(
                LocationIntelligenceComposer::class,
                $this->injectedTypes($class, $method),
                "The {$label} entry point must resolve the shared LocationIntelligenceComposer"
            );
        }

        $composerDependencies = $this->injectedTypes(LocationIntelligenceComposer::class, '__construct');

        $this->assertContains(
            LocationDnaEnrichmentRunner::class,
            $composerDependencies,
            'The composer must delegate to the runner that owns POI geometry precedence'
        );
    }

    public function test_the_container_serves_one_boundary_lookup_implementation(): void
    {
        $first  = $this->app->make(BoundaryLookupService::class);
        $second = $this->app->make(BoundaryLookupService::class);

        $this->assertInstanceOf(BoundaryLookupService::class, $first);
        $this->assertSame(
            get_class($first),
            get_class($second),
            'Both roles must resolve the same concrete precedence implementation'
        );
    }

    public function test_seller_and_landlord_are_not_wired_to_preference_precedence(): void
    {
        // Guards the scope boundary: the supply side declares no search preferences,
        // so if a Seller/Landlord controller ever starts resolving the buyer-side
        // geography services, that is a design change that must be made deliberately.
        foreach (['SellerOfferListingController', 'LandlordOfferListingController'] as $controller) {
            $class = "App\\Http\\Controllers\\{$controller}";

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod('view')) {
                continue;
            }

            $this->assertNotContains(
                BoundaryLookupService::class,
                $this->injectedTypes($class, 'view'),
                "{$controller} must stay outside preference-precedence behaviour"
            );
        }
    }
}
