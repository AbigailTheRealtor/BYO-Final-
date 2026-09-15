<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\TenantAgentAuction;
use App\Http\Livewire\TenantAgentAuctionEdit;
use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The two frozen tenant components no longer build their own Guzzle client.
 *
 * TenantAgentAuction and TenantAgentAuctionEdit constructed five bare
 * `new \GuzzleHttp\Client()` instances for Google Places Autocomplete and Geocoding. A bare
 * client resolves nothing from the container, so the network guard, the outbound telemetry
 * and the admission middleware never saw those requests (erratum E-38). They now resolve the
 * shared container client — the only change made to either component.
 *
 * Each behavioural test checks the source FIRST, before invoking anything: if a bare client
 * ever came back, invoking the method would open a real socket, so the test stops short of it.
 *
 * ZERO live Google requests — the container client runs over a fake transport.
 */
class TenantGoogleClientRoutingTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<RequestInterface> every request that reached the fake transport */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.google.places_key'    => 'fake-test-key',
            // Geocoding is budgeted and OFF by default; these cases prove ROUTING, so they
            // opt in. The switched-off and over-budget behaviour of the same methods is
            // TenantGeocodingFailureSafetyTest's job.
            'google_geocoding.enabled'      => true,
            'google_geocoding.hourly_limit' => 25,
            'google_geocoding.daily_limit'  => 100,
        ]);

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
                $this->sent[] = $request;

                $body = str_contains($request->getUri()->getPath(), '/place/autocomplete')
                    ? ['status' => 'OK', 'predictions' => [['description' => '123 Main St, Tampa, FL, USA']]]
                    : ['status' => 'OK', 'results' => [[
                        'address_components' => [
                            ['long_name' => 'Tampa', 'types' => ['locality']],
                            ['long_name' => 'Florida', 'types' => ['administrative_area_level_1']],
                            ['long_name' => '33602', 'types' => ['postal_code']],
                            ['long_name' => 'Hillsborough County', 'types' => ['administrative_area_level_2']],
                        ],
                    ]]];

                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body)));
            }
        ));
    }

    /** @return array<string, array{0: class-string, 1: string}> */
    public function components(): array
    {
        return [
            'TenantAgentAuction'     => [TenantAgentAuction::class, 'app/Http/Livewire/TenantAgentAuction.php'],
            'TenantAgentAuctionEdit' => [TenantAgentAuctionEdit::class, 'app/Http/Livewire/TenantAgentAuctionEdit.php'],
        ];
    }

    /**
     * @test
     * @dataProvider components
     */
    public function the_component_constructs_no_bare_guzzle_client(string $class, string $path): void
    {
        $this->assertNoBareClient($path);
    }

    /**
     * @test
     * @dataProvider components
     */
    public function address_suggestions_go_through_the_shared_client(string $class, string $path): void
    {
        $this->assertNoBareClient($path);

        $before = GoogleOutboundTelemetryMiddleware::counter();

        $suggestions = $this->invoke($class, 'getPlaceSuggestionsFromApi', ['123 Main', 'address']);

        $this->assertSame(['123 Main St, Tampa, FL, USA'], $suggestions);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('/maps/api/place/autocomplete', $this->sent[0]->getUri()->getPath());
        $this->assertSame($before + 1, GoogleOutboundTelemetryMiddleware::counter(), 'telemetry saw it: the shared stack');
    }

    /**
     * @test
     * @dataProvider components
     */
    public function address_details_go_through_the_shared_client(string $class, string $path): void
    {
        $this->assertNoBareClient($path);

        $details = $this->invoke($class, 'getAddressDetailsFromApi', ['123 Main St, Tampa, FL']);

        $this->assertSame('Tampa', $details['city']);
        $this->assertSame('33602', $details['zipCode']);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('/maps/api/geocode', $this->sent[0]->getUri()->getPath());
    }

    /** The county lookup exists only on the create component. @test */
    public function the_county_state_lookup_goes_through_the_shared_client(): void
    {
        $this->assertNoBareClient('app/Http/Livewire/TenantAgentAuction.php');

        $this->invoke(TenantAgentAuction::class, 'extractStateFromCountyUsingAPI', ['Hillsborough County']);

        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('/maps/api/geocode', $this->sent[0]->getUri()->getPath());
    }

    private function assertNoBareClient(string $path): void
    {
        $source = (string) file_get_contents(base_path($path));

        $this->assertDoesNotMatchRegularExpression(
            '/new\s+\\\\?(GuzzleHttp\\\\)?Client\s*\(/',
            $source,
            "{$path} must not construct its own Guzzle client"
        );
    }

    /** Invoke a component's (private) Google helper without booting the component. */
    private function invoke(string $class, string $method, array $args): mixed
    {
        $component = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($component, $args);
    }
}
