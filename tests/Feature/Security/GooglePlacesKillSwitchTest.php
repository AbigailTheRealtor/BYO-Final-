<?php

namespace Tests\Feature\Security;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 0 / S2 — the master kill switch, wired.
 *
 * `config/google_places.php` has carried `enabled`, `daily_limit`, and `hourly_limit`
 * since the 2026-07-05 incident, and until this commit
 * `grep -rn "google_places\." app/` returned **zero hits**. The circuit breaker written
 * in response to a $1,223 incident did not exist in code.
 *
 * These tests prove GOOGLE_PLACES_ENABLED=false short-circuits BOTH Nearby Search
 * callers before any HTTP call is attempted, by binding an HTTP client that explodes
 * if it is ever touched.
 *
 * @see docs/architecture/SPATIAL-INTELLIGENCE-PLATFORM.md §17 Phase 0 item 7
 */
class GooglePlacesKillSwitchTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 90210;

    /**
     * `Tests\TestCase` already binds `Tests\Support\BlocksGooglePlacesHttpClient`
     * into the container for every test: a Guzzle client that throws on any outbound
     * request. Because both Nearby callers resolve `ClientInterface` from the
     * container (Phase 0 / S1b), these tests fail loudly if the kill switch ever
     * lets a call through. No mock of our own is needed — we are exercising the real
     * production seam.
     */

    /** @test */
    public function path_b_google_places_poi_adapter_short_circuits_when_the_switch_is_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']); // key present, switch off

        $results = app(GooglePlacesPoiAdapter::class)->search(27.77, -82.64, 'schools', 5, 10);

        $this->assertSame([], $results, 'The adapter must return empty results without calling Google.');
    }

    /** @test */
    public function path_b_adapter_short_circuits_before_the_api_key_check(): void
    {
        // Order matters: the switch is the outermost guard. Even with a valid-looking
        // key, no call is attempted for any category.
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        foreach (['schools', 'parks', 'shopping', 'hospitals', 'gyms', 'airports', 'downtown'] as $category) {
            $this->assertSame([], app(GooglePlacesPoiAdapter::class)->search(27.77, -82.64, $category, 5, 10));
        }
    }

    /** @test */
    public function path_a_location_dna_poi_distance_service_short_circuits_when_the_switch_is_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.7676,
            'geocoded_lng'   => -82.6403,
        ]);

        $output = app(LocationDnaPoiDistanceService::class)
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($output['success']);
        $this->assertSame('failed', $output['status']);
        $this->assertSame('google_places_disabled', $output['error']);
    }

    /** @test */
    public function the_api_key_guard_still_fires_when_the_switch_is_on_but_the_key_is_absent(): void
    {
        // The two guards are independent. Removing one must not silently disable the other.
        config(['google_places.enabled' => true]);
        config(['services.google.places_key' => '']);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.7676,
            'geocoded_lng'   => -82.6403,
        ]);

        $output = app(LocationDnaPoiDistanceService::class)
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($output['success']);
        $this->assertSame('missing_google_api_key', $output['error']);
    }

    // =====================================================================
    // GEOCODING — the path the original kill switch never covered.
    //
    // GooglePlacesPoiAdapter honoured `google_places.enabled`; LocationDnaGeocodeService
    // did not. Its only gate was `blank($apiKey)`, so a key present in the environment
    // was enough to send an outbound Geocoding request with the switch off. These tests
    // pin the fail-closed behaviour.
    //
    // Product direction is a NON-GOOGLE resolver. None of these tests assert that
    // enabling Google geocoding is desirable — they assert that it cannot happen
    // unless someone explicitly turns it on.
    // =====================================================================

    /** The exact contract a caller sees when no approved resolver can produce a coordinate. */
    private const UNRESOLVED_ERROR = 'non_google_geocoder_unavailable';

    private function addressWithoutCoordinates(): array
    {
        return [
            'address' => '14047 Marguerite Dr',
            'city'    => 'Madeira Beach',
            'state'   => 'FL',
            'zip'     => '33708',
        ];
    }

    /** @test */
    public function geocoding_makes_zero_outbound_calls_when_the_switch_is_off_despite_a_valid_key(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']); // key present, switch off

        // No client injected: the service resolves ClientInterface from the container,
        // where TestCase has bound BlocksGooglePlacesHttpClient. If the guard ever
        // regresses, that client throws and names the 2026-07-05 incident.
        $output = app(LocationDnaGeocodeService::class)->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            $this->addressWithoutCoordinates(),
        );

        $this->assertFalse($output['success']);
        $this->assertSame('skipped', $output['status']);
        $this->assertSame(self::UNRESOLVED_ERROR, $output['error']);
        $this->assertNull($output['lat']);
        $this->assertNull($output['lng']);
    }

    /**
     * The guard must sit ahead of the credential check, not behind it.
     *
     * Counts invocations rather than using `expects($this->never())`: geocodeForListing()
     * wraps everything in `catch (Throwable)`, which SWALLOWS a PHPUnit expectation
     * failure and reports it as status='failed'. A counter read after the call survives
     * that catch-all and states plainly whether an outbound request was attempted.
     */
    public function test_geocoding_never_touches_the_http_client_when_the_switch_is_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        $calls  = 0;
        $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
        $client->method('request')->willReturnCallback(function () use (&$calls) {
            $calls++;
            throw new \RuntimeException('outbound request attempted with the kill switch off');
        });

        $output = (new LocationDnaGeocodeService($client))->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            $this->addressWithoutCoordinates(),
        );

        $this->assertSame(0, $calls, 'The geocoder must issue ZERO outbound requests when the switch is off.');
        $this->assertSame('skipped', $output['status']);
        $this->assertSame(self::UNRESOLVED_ERROR, $output['error']);
    }

    /** The unresolved state is recorded, not silently dropped — "unknown" must be legible later. */
    public function test_geocoding_persists_the_unresolved_reason_on_the_record(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        app(LocationDnaGeocodeService::class)->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            $this->addressWithoutCoordinates(),
        );

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($record, 'The address attempt must still be recorded.');
        $this->assertSame('skipped', $record->geocode_status);
        $this->assertSame(self::UNRESOLVED_ERROR, $record->geocode_error);
        $this->assertNull($record->geocoded_lat);
        $this->assertNull($record->geocoded_lng);
        $this->assertSame('14047 Marguerite Dr', $record->source_address);
    }

    /**
     * Pre-supplied coordinates are the whole point of failing closed rather than hard-
     * erroring: a listing that already knows where it is must flow through the pipeline
     * untouched, switch off or not.
     */
    public function test_pre_supplied_coordinates_still_resolve_with_the_switch_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        $calls  = 0;
        $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
        $client->method('request')->willReturnCallback(function () use (&$calls) {
            $calls++;
            throw new \RuntimeException('pre-supplied coordinates must not trigger geocoding');
        });

        $output = (new LocationDnaGeocodeService($client))->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            $this->addressWithoutCoordinates() + ['pre_lat' => '27.7960', 'pre_lng' => '-82.7998'],
        );

        $this->assertSame(0, $calls, 'Pre-supplied coordinates must bypass geocoding entirely.');

        $this->assertTrue($output['success']);
        $this->assertSame('geocoded', $output['status']);
        $this->assertSame('saved_meta', $output['source']);
        $this->assertSame(27.7960, $output['lat']);
        $this->assertSame(-82.7998, $output['lng']);
    }

    /** A previously geocoded record keeps serving its cached coordinate; the guard sits after the cache. */
    public function test_cached_coordinates_still_resolve_with_the_switch_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '14047 Marguerite Dr',
            'source_city'    => 'Madeira Beach',
            'source_state'   => 'FL',
            'source_zip'     => '33708',
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.7960,
            'geocoded_lng'   => -82.7998,
        ]);

        $calls  = 0;
        $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
        $client->method('request')->willReturnCallback(function () use (&$calls) {
            $calls++;
            throw new \RuntimeException('a cached coordinate must not trigger geocoding');
        });

        $output = (new LocationDnaGeocodeService($client))->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            $this->addressWithoutCoordinates(),
        );

        $this->assertSame(0, $calls, 'A cached coordinate must be served without an outbound request.');
        $this->assertTrue($output['success']);
        $this->assertSame('geocoded', $output['status']);
    }

    /**
     * A missing coordinate must not become an exception. `skipped` is a normal outcome
     * the pipeline already understands, so a publish/save cannot crash on it.
     */
    public function test_the_pipeline_reports_skipped_rather_than_failing_when_coordinates_are_unresolvable(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        $output = app(LocationDnaGeocodeService::class)->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            $this->addressWithoutCoordinates(),
        );

        $this->assertNotSame('failed', $output['status'], 'Unresolvable is not a failure — nothing was attempted.');
        $this->assertSame('skipped', $output['status']);
    }

    /**
     * The five Google call sites frozen under INV-8 and SIA-D34. They still construct
     * bare Guzzle clients and are therefore uninstrumentable. Closing them is Q12.
     *
     * This list is an *upper bound*, not a requirement: if a future, explicitly
     * approved exception clears one of these files, `only_frozen_files_still_construct…`
     * keeps passing. What it will never allow is a bare client appearing anywhere else.
     */
    private const FROZEN_FILES = [
        'app/Http/Livewire/TenantAgentAuction.php',
        'app/Http/Livewire/TenantAgentAuctionEdit.php',
    ];

    /**
     * The single sanctioned place a Guzzle client may be constructed: the container
     * binding itself, which wraps it in the handler stack carrying
     * GoogleOutboundTelemetryMiddleware. Everything else must resolve from it.
     */
    private const ALLOWED_CLIENT_FACTORY = [
        'app/Providers/AppServiceProvider.php',
    ];

    /**
     * Every file cleared by Batches 1–3. Each is locked behind the assertions below.
     */
    private const CLEARED_FILES = [
        // Batch 1
        'app/Services/LocationDna/GooglePlacesPoiAdapter.php',
        'app/Services/LocationDna/LocationDnaPoiDistanceService.php',
        'app/Services/LocationDna/LocationDnaGeocodeService.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        // Batch 3 — Tenant
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        // Batch 3 — Buyer
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        // Batch 3 — Seller
        'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
        // Batch 3 — Landlord
        'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        // Batch 3 — the E-40 Http-facade caller
        'app/Console/Commands/GeocodeSelleryLandlordListings.php',
    ];

    /**
     * Matches every way a bare Guzzle client can be constructed, including the
     * fully-qualified form. Grepping for `new Client(` alone is what caused E-38 to
     * undercount 18 call sites as 4 — do not narrow this pattern.
     */
    private const BARE_CLIENT_PATTERN = '/new\s+\\\\?(GuzzleHttp\\\\)?Client\s*\(/';

    private function sourceWithoutComments(string $relativePath): string
    {
        $absolute = base_path($relativePath);
        $this->assertFileExists($absolute, "{$relativePath} is missing — update CLEARED_FILES.");

        return self::stripComments(file_get_contents($absolute));
    }

    /**
     * Strip comments so prose describing the old pattern does not trip these checks.
     *
     * Do NOT collapse the two passes into one alternation under the `/s` flag. With `/s`
     * the dot matches newlines, so a `//.*$` branch deletes everything from the first
     * line comment to the end of the file — it reduced TenantAgentAuction.php from
     * 257,742 bytes to 912 and made every assertion built on it pass vacuously.
     * Block comments need `/s`; line comments must never have it.
     */
    private static function stripComments(string $source): string
    {
        return preg_replace(
            ['#/\*.*?\*/#s', '#//[^\n]*#'],
            '',
            $source,
        );
    }

    /** @test */
    public function the_comment_stripper_removes_comments_without_eating_the_file(): void
    {
        $source = <<<'PHP'
        <?php
        // a line comment mentioning new Client()
        $keep = 1;
        /* a block comment mentioning new \GuzzleHttp\Client() */
        $client = new Client();
        PHP;

        $stripped = self::stripComments($source);

        $this->assertStringContainsString('$keep = 1;', $stripped, 'The stripper ate live code.');
        $this->assertStringContainsString('$client = new Client();', $stripped);
        $this->assertStringNotContainsString('a line comment', $stripped);
        $this->assertStringNotContainsString('a block comment', $stripped);
        $this->assertMatchesRegularExpression(self::BARE_CLIENT_PATTERN, $stripped);
    }

    /** @test */
    public function no_cleared_file_constructs_a_bare_guzzle_client(): void
    {
        // S1b. A bare client cannot be intercepted by the container binding, so it
        // bypasses GoogleOutboundTelemetryMiddleware and every test-suite guard.
        foreach (self::CLEARED_FILES as $file) {
            $this->assertDoesNotMatchRegularExpression(
                self::BARE_CLIENT_PATTERN,
                $this->sourceWithoutComments($file),
                "{$file} constructs a bare Guzzle client. Resolve ClientInterface from the "
                . 'container instead (Phase 0 / S1b).',
            );
        }
    }

    /** @test */
    public function no_cleared_file_calls_the_guzzle_get_helper(): void
    {
        // `get()` is Guzzle __call magic and is NOT declared on ClientInterface. A
        // container-resolved client typed as the interface must use request('GET', …),
        // or the call breaks the moment anything binds a strict interface implementation.
        foreach (self::CLEARED_FILES as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/\$client->get\s*\(/',
                $this->sourceWithoutComments($file),
                "{$file} calls \$client->get(). Use request('GET', …) — get() is not on ClientInterface.",
            );
        }
    }

    /** @test */
    public function only_frozen_files_still_construct_a_bare_google_client(): void
    {
        // The census, enforced. Any NEW bare Guzzle client anywhere in app/ that calls a
        // Google host fails this test, whatever role or file it is added to.
        $offenders = [];

        foreach ($this->allPhpFilesUnderApp() as $absolute) {
            $relative = ltrim(str_replace(base_path(), '', $absolute), '/');
            $source   = file_get_contents($absolute);

            if (!str_contains($source, 'maps.googleapis.com')) {
                continue;
            }

            if (preg_match(self::BARE_CLIENT_PATTERN, self::stripComments($source))) {
                $offenders[] = $relative;
            }
        }

        // Guard against a vacuous pass. The five frozen call sites are known to exist, so
        // a scan that finds nothing means the scan itself is broken — which is exactly
        // what a bad comment-stripper did to the original version of this assertion.
        $this->assertNotEmpty(
            $offenders,
            'The census scan found no bare Google clients at all. The five frozen call '
            . 'sites in ' . implode(' and ', self::FROZEN_FILES) . ' should have matched. '
            . 'The scan is broken; it is not proving anything.',
        );

        $unexpected = array_values(array_diff(
            $offenders,
            self::FROZEN_FILES,
            self::ALLOWED_CLIENT_FACTORY,
        ));

        $this->assertSame(
            [],
            $unexpected,
            "These files construct a bare Guzzle client and call Google, but are neither "
            . "frozen nor the sanctioned container binding:\n  "
            . implode("\n  ", $unexpected)
            . "\nRoute them through app(ClientInterface::class). See erratum E-38.",
        );
    }

    /** @test */
    public function no_file_calls_google_through_the_http_facade(): void
    {
        // Erratum E-40. The Http facade builds its own Guzzle client and never consults
        // the container, so such a call is invisible to the telemetry middleware AND to
        // BlocksGooglePlacesHttpClient. E-38's Guzzle-only census missed exactly this.
        $offenders = [];

        foreach ($this->allPhpFilesUnderApp() as $absolute) {
            $stripped = self::stripComments(file_get_contents($absolute));

            if (preg_match('/Http::[^;]*googleapis\.com/s', $stripped)) {
                $offenders[] = ltrim(str_replace(base_path(), '', $absolute), '/');
            }
        }

        $this->assertSame([], $offenders, 'Google is reachable via the Http facade in: ' . implode(', ', $offenders));
    }

    /** @return string[] */
    private function allPhpFilesUnderApp(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        $this->assertNotEmpty($files);

        return $files;
    }
}
