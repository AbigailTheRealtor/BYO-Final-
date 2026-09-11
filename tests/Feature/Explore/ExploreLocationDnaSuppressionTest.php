<?php

namespace Tests\Feature\Explore;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Explore\ExploreInventoryService;
use App\Services\Explore\ExploreTransactionType;
use App\Services\Explore\ExploreViewport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\Feature\Explore\Support\FakeBridgeApi;
use Tests\TestCase;

/**
 * Explore never starts Location DNA — and so never reaches Google Places
 * through it.
 *
 * THE HIDDEN PATH THESE CLOSE
 * ---------------------------
 * Explore reuses the shared importer, and the importer dispatches
 * ComputeLocationDna for every new or re-addressed Bridge row. That job's POI
 * step can call Google Places, whose declared daily/hourly limits are read by
 * no code — so with GOOGLE_PLACES_ENABLED on, one discovery pass (up to 500
 * rows, inline, because the queue runs `sync`) could have become hundreds of
 * Places requests nobody on this surface would ever see.
 *
 * Every Explore test here asserts BOTH that no dispatch happened and that no
 * HTTP request left the application. The job itself is faked, so even a
 * dispatch that slipped through could not run the pipeline against a real
 * provider.
 *
 * ZERO live Bridge requests (FakeBridgeApi). ZERO live Google requests.
 */
class ExploreLocationDnaSuppressionTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private FakeBridgeApi $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'explore.enabled'                       => true,
            'explore.discovery.enabled'             => true,
            'mls_media.enabled'                     => true,
            'mls_media.license_acknowledged'        => true,
            'explore.provider_budget.global_hourly' => 1000,
            'explore.provider_budget.global_daily'  => 5000,
            'explore.provider_budget.actor_hourly'  => 1000,
            'explore.provider_budget.actor_daily'   => 5000,
        ]);

        Cache::flush();
        Bus::fake([ComputeLocationDna::class]);
        Http::fake();

        $this->provider = new FakeBridgeApi();
        $this->app->instance(BridgeApiService::class, $this->provider);
    }

    private function discover(array $query = []): void
    {
        $query = array_merge(['bbox' => $this->bboxAroundDefault()], $query);

        $this->getJson('/api/explore/listings?' . http_build_query($query))->assertOk();
    }

    /** A — a cold tile imports new rows and starts no Location DNA. @test */
    public function a_cold_tile_imports_new_listings_without_dispatching_location_dna(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DNA-NEW-1'])];

        $this->discover(['transaction_type' => 'sale']);

        $this->assertSame(1, $this->provider->providerRequestCount());
        $this->assertTrue(BridgeProperty::where('listing_key', 'DNA-NEW-1')->exists(), 'the row was imported');

        Bus::assertNotDispatched(ComputeLocationDna::class);
        Http::assertNothingSent();
    }

    /** B — a re-addressed row is updated and starts no Location DNA. @test */
    public function a_re_addressed_listing_is_updated_without_dispatching_location_dna(): void
    {
        $this->makeListing(['listing_key' => 'DNA-MOVED', 'unparsed_address' => '1 Old Road'])
            ->forceFill(['imported_at' => now()->subDays(30)])
            ->save();

        // The address change is exactly what makes the normalizer ask for DNA.
        $this->provider->records = [$this->providerRecord([
            'listing_key'      => 'DNA-MOVED',
            'unparsed_address' => '2 New Road',
        ])];

        $this->discover(['transaction_type' => 'sale']);

        $this->assertSame('2 New Road', BridgeProperty::where('listing_key', 'DNA-MOVED')->value('unparsed_address'));

        Bus::assertNotDispatched(ComputeLocationDna::class);
        Http::assertNothingSent();
    }

    /** C — the property panel's refresh starts no Location DNA. @test */
    public function the_property_panel_refresh_does_not_dispatch_location_dna(): void
    {
        $this->makeListing(['listing_key' => 'DNA-PANEL', 'unparsed_address' => '5 Before Street'])
            ->forceFill(['imported_at' => now()->subDays(30)])
            ->save();

        $this->provider->records = [$this->providerRecord([
            'listing_key'      => 'DNA-PANEL',
            'unparsed_address' => '6 After Street',
        ])];

        $this->getJson('/api/explore/listings/DNA-PANEL')->assertOk();

        $this->assertCount(1, $this->provider->singleCalls, 'the panel really did refresh the record');
        $this->assertSame('6 After Street', BridgeProperty::where('listing_key', 'DNA-PANEL')->value('unparsed_address'));

        Bus::assertNotDispatched(ComputeLocationDna::class);
        Http::assertNothingSent();
    }

    /**
     * D — the opt-out is Explore's alone. The same importer, called the way the
     * buyer criteria search calls it, still dispatches; so does the lookup
     * service at its default.
     *
     * @test
     */
    public function non_explore_callers_still_dispatch_location_dna(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DNA-BUYER-1'])];

        $payload = app(ExploreInventoryService::class)->payloadFor(
            ExploreViewport::fromString($this->bboxAroundDefault()),
            ExploreTransactionType::SALE->propertyTypes(),
        );

        app(LazyBridgeImportService::class)->importForCriteria($payload, 'buyer');

        Bus::assertDispatched(ComputeLocationDna::class, 1);

        $this->provider->records[] = $this->providerRecord(['listing_key' => 'DNA-LOOKUP-1']);
        app(BridgeListingLookupService::class)->refreshByListingKey('DNA-LOOKUP-1');

        Bus::assertDispatched(ComputeLocationDna::class, 2);
    }

    /**
     * E — with Google Places switched ON, Explore discovery still does not
     * enter the Location DNA path, for new sale and rental rows alike.
     *
     * @test
     */
    public function explore_does_not_start_location_dna_even_with_google_places_enabled(): void
    {
        config(['google_places.enabled' => true]);

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'DNA-PLACES-SALE']),
            $this->providerRentalRecord(['listing_key' => 'DNA-PLACES-RENT']),
        ];

        // Unfiltered: both passes, both rows new.
        $this->discover();

        $this->assertSame(
            2,
            BridgeProperty::whereIn('listing_key', ['DNA-PLACES-SALE', 'DNA-PLACES-RENT'])->count(),
            'both rows were imported'
        );

        Bus::assertNotDispatched(ComputeLocationDna::class);
        Http::assertNothingSent();
    }
}
