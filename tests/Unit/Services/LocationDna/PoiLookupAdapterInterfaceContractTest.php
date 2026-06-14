<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * PoiLookupAdapterInterfaceContractTest
 *
 * Verifies the 7-key shape contract for both PoiLookupAdapterInterface implementations.
 * StubPoiLookupAdapter is called directly (returns []).
 * GooglePlacesPoiAdapter is constructed with a mocked GuzzleHttp\ClientInterface.
 * The real Guzzle HTTP client is never instantiated in this file.
 */
class PoiLookupAdapterInterfaceContractTest extends TestCase
{
    private const ITEM_KEYS = [
        'category', 'name', 'address', 'latitude', 'longitude', 'distance_miles', 'source',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.places_key' => 'test-key',
            'location_dna.poi.timeout'   => 5,
        ]);
    }

    /** StubPoiLookupAdapter always returns [] */
    public function test_stub_adapter_returns_empty_array(): void
    {
        $adapter = new StubPoiLookupAdapter();
        $result  = $adapter->search(27.9506, -82.4572, 'schools', 10, 5);

        $this->assertSame([], $result);
    }

    /** GooglePlacesPoiAdapter normalises every result item to exactly the 7-key contract */
    public function test_google_places_adapter_returns_items_with_correct_7_key_shape(): void
    {
        $fakeBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'name'     => 'Riverside Elementary',
                    'vicinity' => '100 School Rd, Tampa, FL',
                    'geometry' => [
                        'location' => ['lat' => 27.9700, 'lng' => -82.4600],
                    ],
                ],
            ],
        ]);

        $mockResponse = new Response(200, [], $fakeBody);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $adapter = new GooglePlacesPoiAdapter($mockClient);
        $items   = $adapter->search(27.9506, -82.4572, 'schools', 10, 5);

        $this->assertNotEmpty($items);
        $item = $items[0];

        foreach (self::ITEM_KEYS as $key) {
            $this->assertArrayHasKey($key, $item, "Item missing required key '{$key}'");
        }
        $this->assertCount(count(self::ITEM_KEYS), $item, 'Item must carry exactly 7 keys');

        $this->assertSame('schools', $item['category']);
        $this->assertSame('Riverside Elementary', $item['name']);
        $this->assertSame('100 School Rd, Tampa, FL', $item['address']);
        $this->assertIsFloat($item['latitude']);
        $this->assertIsFloat($item['longitude']);
        $this->assertIsFloat($item['distance_miles']);
        $this->assertSame('google_places', $item['source']);
    }

    /** GooglePlacesPoiAdapter returns [] when the HTTP client throws */
    public function test_google_places_adapter_returns_empty_array_on_http_error(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('request')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $adapter = new GooglePlacesPoiAdapter($mockClient);
        $result  = $adapter->search(27.9506, -82.4572, 'schools', 10, 5);

        $this->assertSame([], $result);
    }

    /** GooglePlacesPoiAdapter respects the limit parameter */
    public function test_google_places_adapter_respects_result_limit(): void
    {
        $places = array_map(fn($i) => [
            'name'     => "Place {$i}",
            'vicinity' => "{$i} Test Ave",
            'geometry' => ['location' => ['lat' => 27.96 + $i * 0.001, 'lng' => -82.46]],
        ], range(1, 10));

        $fakeBody     = json_encode(['status' => 'OK', 'results' => $places]);
        $mockResponse = new Response(200, [], $fakeBody);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('request')->willReturn($mockResponse);

        $adapter = new GooglePlacesPoiAdapter($mockClient);
        $items   = $adapter->search(27.9506, -82.4572, 'schools', 10, 3);

        $this->assertCount(3, $items, 'Adapter must honour the $limit parameter');
    }

    /** StubPoiLookupAdapter satisfies the PoiLookupAdapterInterface type constraint */
    public function test_stub_adapter_implements_interface(): void
    {
        $this->assertInstanceOf(PoiLookupAdapterInterface::class, new StubPoiLookupAdapter());
    }

    /** GooglePlacesPoiAdapter satisfies the PoiLookupAdapterInterface type constraint */
    public function test_google_places_adapter_implements_interface(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $this->assertInstanceOf(PoiLookupAdapterInterface::class, new GooglePlacesPoiAdapter($mockClient));
    }
}
