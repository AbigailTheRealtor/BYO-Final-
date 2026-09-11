<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Google\GoogleHttpClientFactory;
use App\Support\Google\GoogleProviderAdmissionMiddleware as Admission;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * `app:geocode-seller-landlord-listings` can actually COMPLETE a successful geocode.
 *
 * Every earlier test of this command proves a refusal or a failure. None ever reached the
 * success path — and the success path was broken: `saveMeta()` wrote `created_at` /
 * `updated_at` into `property_auction_metas`, which has no timestamp columns, so the first
 * geocode Google answered threw a QueryException after the request had already been paid for.
 *
 * These run against the real schema shape (the migrated in-memory schema) through the
 * production client stack over a fake transport, with the shared Geocoding budget live, so
 * they also pin the budget behaviour on a run that succeeds: every request is charged, the run
 * stops at the first refusal, and no row is written that Google did not answer.
 *
 * ZERO live Google requests. The key is a fake string.
 */
class GeocodeBackfillSuccessPathTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<RequestInterface> every request that reached the fake transport */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google_places.enabled'         => true,
            'google_geocoding.enabled'      => true,
            'google_geocoding.hourly_limit' => 25,
            'google_geocoding.daily_limit'  => 100,
            'services.google.places_key'    => 'fake-test-key',
        ]);

        Cache::flush();

        $this->app->instance(ClientInterface::class, GoogleHttpClientFactory::make(
            function (RequestInterface $request) {
                $this->sent[] = $request;
                $n = count($this->sent);

                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                    'status'  => 'OK',
                    'results' => [[
                        'geometry'          => ['location' => ['lat' => 27.9506 + $n / 1000, 'lng' => -82.4572]],
                        'place_id'          => "ChIJ-test-{$n}",
                        'formatted_address' => "{$n} Main St, Tampa, FL 33602, USA",
                    ]],
                ])));
            }
        ));
    }

    /** A seller listing with an address and no coordinates — exactly what the command selects. */
    private function sellerListing(string $address): int
    {
        // DB::table, not Eloquent: no observer, so no Location DNA dispatch on insert.
        $id = DB::table('property_auctions')->insertGetId([
            'user_id'      => User::factory()->create()->id,
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'title'        => 'Backfill success fixture',
            'address'      => $address,
            'city_id'      => 1,
            'state_id'     => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('property_auction_metas')->insert([
            'property_auction_id' => $id,
            'meta_key'            => 'address',
            'meta_value'          => $address,
        ]);

        return $id;
    }

    /** @return array<string, string> meta_key => meta_value for one listing */
    private function metaFor(int $id): array
    {
        return DB::table('property_auction_metas')
            ->where('property_auction_id', $id)
            ->pluck('meta_value', 'meta_key')
            ->all();
    }

    /** @test */
    public function a_successful_geocode_is_written_and_the_command_completes(): void
    {
        $first  = $this->sellerListing('1 First St, Tampa, FL');
        $second = $this->sellerListing('2 Second St, Tampa, FL');

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('Done — updated: 2, skipped: 0, failed: 0')
            ->assertExitCode(0);

        $this->assertCount(2, $this->sent, 'one request per listing');
        $this->assertSame(['hourly' => 2, 'daily' => 2], Admission::budgetFor(Admission::FAMILY_GEOCODING)->spent());

        foreach ([$first, $second] as $id) {
            $meta = $this->metaFor($id);

            $this->assertNotEmpty($meta['property_lat'] ?? null, "listing {$id} got a latitude");
            $this->assertSame('-82.4572', $meta['property_lng'] ?? null);
            $this->assertStringStartsWith('ChIJ-test-', $meta['google_place_id'] ?? '');
            $this->assertStringContainsString('Main St, Tampa', $meta['formatted_address'] ?? '');
        }
    }

    /** A second run finds nothing left to do and sends nothing. @test */
    public function a_rerun_sends_nothing_for_listings_already_geocoded(): void
    {
        $this->sellerListing('1 First St, Tampa, FL');

        $this->artisan('app:geocode-seller-landlord-listings')->assertExitCode(0);
        $this->assertCount(1, $this->sent);

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('Done — updated: 0, skipped: 0, failed: 0')
            ->assertExitCode(0);

        $this->assertCount(1, $this->sent, 'the stored coordinate costs nothing');
    }

    /** An existing meta key is updated in place — one row per key, never a duplicate. @test */
    public function an_existing_meta_key_is_updated_not_duplicated(): void
    {
        $id = $this->sellerListing('1 First St, Tampa, FL');

        DB::table('property_auction_metas')->insert([
            'property_auction_id' => $id,
            'meta_key'            => 'formatted_address',
            'meta_value'          => 'stale value',
        ]);

        $this->artisan('app:geocode-seller-landlord-listings')->assertExitCode(0);

        $rows = DB::table('property_auction_metas')
            ->where('property_auction_id', $id)
            ->where('meta_key', 'formatted_address')
            ->pluck('meta_value');

        $this->assertCount(1, $rows);
        $this->assertSame('1 Main St, Tampa, FL 33602, USA', $rows[0]);
    }

    /**
     * Success, then the ceiling: the admitted rows are written, the run stops at the first
     * refusal, and the row Google was never asked about is left without a coordinate.
     *
     * @test
     */
    public function successes_are_kept_and_the_run_stops_at_the_first_refusal(): void
    {
        $ids = [
            $this->sellerListing('1 First St, Tampa, FL'),
            $this->sellerListing('2 Second St, Tampa, FL'),
            $this->sellerListing('3 Third St, Tampa, FL'),
        ];

        $budget = Admission::budgetFor(Admission::FAMILY_GEOCODING);
        for ($i = 0; $i < 23; $i++) {
            $budget->recordRequest(); // two units left in this hour
        }

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('  Google Geocoding request budget reached (geocoding_provider_hourly_cap_reached). Stopping: no further Google request will be made.')
            ->expectsOutput('Stopped — updated: 2, skipped: 0, failed: 0, not processed: 1')
            ->assertExitCode(1);

        $this->assertCount(2, $this->sent, 'exactly the two admitted requests went out — no retry loop');
        $this->assertSame(25, $budget->spent()['hourly']);

        $withCoordinates = array_filter($ids, fn (int $id) => ! empty($this->metaFor($id)['property_lat'] ?? null));

        $this->assertCount(2, $withCoordinates, 'the two answered rows were written');
        $this->assertCount(1, array_diff($ids, $withCoordinates), 'and the refused row carries no coordinate');
    }
}
