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
 * Verifies the 9-key shape contract for both PoiLookupAdapterInterface implementations
 * (Stage D added the canonical 'confidence' and 'last_refreshed' envelope keys).
 * StubPoiLookupAdapter is called directly (returns []).
 * GooglePlacesPoiAdapter is constructed with a mocked GuzzleHttp\ClientInterface.
 * The real Guzzle HTTP client is never instantiated in this file.
 */
class PoiLookupAdapterInterfaceContractTest extends TestCase
{
    private const ITEM_KEYS = [
        'category', 'name', 'address', 'latitude', 'longitude', 'distance_miles', 'source',
        'confidence', 'last_refreshed',
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

    /** GooglePlacesPoiAdapter normalises every result item to exactly the 9-key contract */
    public function test_google_places_adapter_returns_items_with_correct_9_key_shape(): void
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
        $this->assertCount(count(self::ITEM_KEYS), $item, 'Item must carry exactly 9 keys');

        $this->assertSame('schools', $item['category']);
        $this->assertSame('Riverside Elementary', $item['name']);
        $this->assertSame('100 School Rd, Tampa, FL', $item['address']);
        $this->assertIsFloat($item['latitude']);
        $this->assertIsFloat($item['longitude']);
        $this->assertIsFloat($item['distance_miles']);
        $this->assertSame('google_places', $item['source']);

        // Unrated place in this fixture → structural existence confidence, not a fabricated rating.
        $this->assertSame(0.5, $item['confidence']);
        $this->assertIsString($item['last_refreshed']);
        $this->assertNotEmpty($item['last_refreshed']);
    }

    /**
     * confidence is derived from the rating signal (canonical-field-mapping-spec §2):
     * a highly-rated, high-review place saturates toward the cap; the field is
     * derived, never a passthrough of Google's raw rating.
     */
    public function test_google_places_adapter_derives_confidence_from_rating_signal(): void
    {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-05T12:00:00+00:00'));

        $fakeBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'name'               => 'Popular Park',
                    'vicinity'           => '1 Park Ave',
                    'geometry'           => ['location' => ['lat' => 27.97, 'lng' => -82.46]],
                    'rating'             => 4.7,
                    'user_ratings_total' => 500, // >= saturation (200) → caps at 0.9
                ],
                [
                    'name'               => 'Quiet Park',
                    'vicinity'           => '2 Park Ave',
                    'geometry'           => ['location' => ['lat' => 27.98, 'lng' => -82.46]],
                    // no rating → structural 0.5
                ],
            ],
        ]);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('request')->willReturn(new Response(200, [], $fakeBody));

        $adapter = new GooglePlacesPoiAdapter($mockClient);
        $items   = $adapter->search(27.9506, -82.4572, 'parks', 10, 5);

        $byName = [];
        foreach ($items as $item) {
            $byName[$item['name']] = $item;
        }

        $this->assertSame(0.9, $byName['Popular Park']['confidence']); // 0.6 + 0.3*min(1, 500/200)
        $this->assertSame(0.5, $byName['Quiet Park']['confidence']);   // structural, unrated
        $this->assertSame('2026-07-05T12:00:00+00:00', $byName['Popular Park']['last_refreshed']);

        \Illuminate\Support\Carbon::setTestNow();
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
