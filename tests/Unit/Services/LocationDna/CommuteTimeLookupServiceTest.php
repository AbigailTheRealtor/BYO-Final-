<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\CommuteTimeAdapterInterface;
use App\Services\LocationDna\CommuteTimeLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * CommuteTimeLookupServiceTest
 *
 * Verifies CommuteTimeLookupService::resolve() using a mocked adapter so that
 * no real HTTP calls are made.
 *
 * Test coverage:
 *   (a) Empty destinations returns [] without calling the adapter
 *   (b) Destination with missing lat/lng is silently skipped
 *   (c) Adapter \Throwable returns [] and logs a warning
 *   (d) Successful call returns entries with all required normalized keys
 *   (e) Second identical call hits cache; adapter is NOT called a second time
 *   (f) Destinations list exceeding max_destinations is truncated before adapter call
 */
class CommuteTimeLookupServiceTest extends TestCase
{
    private function makeService(CommuteTimeAdapterInterface $adapter): CommuteTimeLookupService
    {
        return new CommuteTimeLookupService($adapter);
    }

    private function neverCalledAdapter(): CommuteTimeAdapterInterface
    {
        $mock = Mockery::mock(CommuteTimeAdapterInterface::class);
        $mock->shouldNotReceive('lookup');
        return $mock;
    }

    private function sampleDestination(string $label = 'Work'): array
    {
        return [
            'label'   => $label,
            'address' => '123 Main St, Tampa, FL',
            'lat'     => 27.9506,
            'lng'     => -82.4572,
        ];
    }

    private function stubResultFor(array $destination, string $mode = 'driving'): array
    {
        return [
            'destination_label'   => $destination['label'],
            'destination_address' => $destination['address'],
            'destination_lat'     => (float) $destination['lat'],
            'destination_lng'     => (float) $destination['lng'],
            'travel_mode'         => $mode,
            'travel_time_minutes' => null,
            'distance_miles'      => null,
            'source'              => 'stub',
        ];
    }

    /** (a) Empty destinations returns [] without calling adapter */
    public function test_empty_destinations_returns_empty_without_calling_adapter(): void
    {
        $adapter = $this->neverCalledAdapter();
        $service = $this->makeService($adapter);

        $result = $service->resolve(27.9, -82.4, []);

        $this->assertSame([], $result);
    }

    /** (b) Destination with missing lat or lng is silently skipped */
    public function test_destination_missing_lat_or_lng_is_skipped(): void
    {
        $validDest = $this->sampleDestination('Work');
        $noLat     = ['label' => 'No-lat', 'address' => 'X', 'lng' => -82.0];
        $noLng     = ['label' => 'No-lng', 'address' => 'Y', 'lat' => 27.0];
        $neither   = ['label' => 'Neither', 'address' => 'Z'];

        $expectedResult = [$this->stubResultFor($validDest)];

        $adapter = Mockery::mock(CommuteTimeAdapterInterface::class);
        $adapter->shouldReceive('lookup')
            ->once()
            ->with(
                Mockery::type('float'),
                Mockery::type('float'),
                Mockery::on(fn (array $dests) => count($dests) === 1 && $dests[0]['label'] === 'Work'),
                Mockery::type('array')
            )
            ->andReturn($expectedResult);

        $service = $this->makeService($adapter);
        Cache::flush();

        $result = $service->resolve(27.9, -82.4, [$noLat, $noLng, $neither, $validDest]);

        $this->assertSame($expectedResult, $result);
    }

    /** (c) Adapter \Throwable returns [] and logs a warning */
    public function test_adapter_throwable_returns_empty_and_logs_warning(): void
    {
        $adapter = Mockery::mock(CommuteTimeAdapterInterface::class);
        $adapter->shouldReceive('lookup')
            ->once()
            ->andThrow(new \RuntimeException('Provider unavailable'));

        Log::shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/CommuteTimeLookupService.*adapter/i'),
                Mockery::type('array')
            );

        $service = $this->makeService($adapter);
        Cache::flush();

        $result = $service->resolve(27.9, -82.4, [$this->sampleDestination()]);

        $this->assertSame([], $result);
    }

    /** (d) Successful call returns entries with all required normalized keys */
    public function test_successful_call_returns_all_required_normalized_keys(): void
    {
        $dest   = $this->sampleDestination('Office');
        $entry  = $this->stubResultFor($dest, 'driving');

        $adapter = Mockery::mock(CommuteTimeAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn([$entry]);

        $service = $this->makeService($adapter);
        Cache::flush();

        $result = $service->resolve(28.0, -82.5, [$dest], ['driving']);

        $this->assertCount(1, $result);
        $row = $result[0];

        $requiredKeys = [
            'destination_label',
            'destination_address',
            'destination_lat',
            'destination_lng',
            'travel_mode',
            'travel_time_minutes',
            'distance_miles',
            'source',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $row, "Missing required key: {$key}");
        }

        $this->assertSame('Office', $row['destination_label']);
        $this->assertSame('driving', $row['travel_mode']);
        $this->assertNull($row['travel_time_minutes']);
        $this->assertNull($row['distance_miles']);
        $this->assertSame('stub', $row['source']);
    }

    /** (e) Second identical call hits cache; adapter NOT called a second time */
    public function test_second_identical_call_hits_cache_not_adapter(): void
    {
        $dest   = $this->sampleDestination('Gym');
        $entry  = $this->stubResultFor($dest);

        $adapter = Mockery::mock(CommuteTimeAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn([$entry]);

        $service = $this->makeService($adapter);
        Cache::flush();

        $first  = $service->resolve(27.9, -82.4, [$dest], ['driving']);
        $second = $service->resolve(27.9, -82.4, [$dest], ['driving']);

        $this->assertSame($first, $second);
    }

    /** (f) Destinations list exceeding max_destinations is truncated before adapter call */
    public function test_destinations_exceeding_max_are_truncated_before_adapter_call(): void
    {
        config(['location_dna.commute_time.max_destinations' => 3]);

        $destinations = array_map(
            fn (int $i) => ['label' => "Dest{$i}", 'address' => "{$i} St", 'lat' => 27.0 + $i * 0.01, 'lng' => -82.0],
            range(1, 5)
        );

        $adapter = Mockery::mock(CommuteTimeAdapterInterface::class);
        $adapter->shouldReceive('lookup')
            ->once()
            ->with(
                Mockery::type('float'),
                Mockery::type('float'),
                Mockery::on(fn (array $dests) => count($dests) === 3),
                Mockery::type('array')
            )
            ->andReturn([]);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/max_destinations/i'),
                Mockery::type('array')
            );

        $service = $this->makeService($adapter);
        Cache::flush();

        $result = $service->resolve(27.9, -82.4, $destinations);

        $this->assertSame([], $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
