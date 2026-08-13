<?php

namespace Tests\Feature\Security;

use App\Console\Commands\GeocodeSelleryLandlordListings;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use ReflectionMethod;
use Tests\TestCase;

/**
 * `app:geocode-seller-landlord-listings` must honour the Google kill switch.
 *
 * WHAT THIS CLOSES
 * ----------------
 * The command backfills `property_lat`/`property_lng` by calling the Google
 * Geocoding API. Until this suite its only gate was the credential: with a key
 * sitting in the environment it would issue outbound requests even while
 * `GOOGLE_PLACES_ENABLED` was false. Every other Google coordinate path already
 * failed closed on that switch — {@see \App\Services\LocationDna\LocationDnaGeocodeService}
 * checks it as its outermost guard — so this command was the one property
 * coordinate path the switch did not reach.
 *
 * HOW ZERO-REQUEST IS PROVEN
 * --------------------------
 * The command resolves its HTTP client from the container (erratum E-40), so the
 * binding here is a Guzzle client whose handler fails the test the moment it is
 * invoked. "No request was made" is therefore asserted by the absence of a
 * failure, not by trusting a return value. Nothing in this file can reach Google:
 * there is no real handler to reach it with.
 *
 * This is containment for a legacy path, not an endorsement of it. The approved
 * coordinate sources are Bridge, the address-point corpus and the Census
 * geocoder, all reached through
 * {@see \App\Services\Location\Coordinates\PropertyCoordinateResolver}.
 */
class GeocodeBackfillKillSwitchTest extends TestCase
{
    /**
     * A client that turns any outbound attempt into a test failure.
     *
     * Bound for every test in this file, including the ones that expect the
     * command to refuse — if a refusal path ever started making a request first,
     * that is exactly the regression this file exists to catch.
     */
    private function bindClientThatMustNeverBeCalled(): void
    {
        $stack = HandlerStack::create(function (): never {
            $this->fail('The command made an outbound Google request while the kill switch was off');
        });

        $this->app->instance(ClientInterface::class, new Client(['handler' => $stack]));
    }

    // ── the switch ──────────────────────────────────────────────────────────

    public function test_it_refuses_to_run_when_the_kill_switch_is_off(): void
    {
        $this->bindClientThatMustNeverBeCalled();

        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-live-looking-key']);

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('GOOGLE_PLACES_ENABLED is false. Refusing to geocode: no Google request will be made.')
            ->assertExitCode(1);
    }

    public function test_a_present_credential_is_not_permission_to_geocode(): void
    {
        // The precise hole this closes: a key in the environment used to be
        // sufficient on its own.
        $this->bindClientThatMustNeverBeCalled();

        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-live-looking-key']);

        $this->artisan('app:geocode-seller-landlord-listings')->assertExitCode(1);
    }

    public function test_the_kill_switch_is_checked_before_the_credential(): void
    {
        // With BOTH absent, the operator must be told the switch is off rather
        // than being sent to configure a key that would not have helped.
        $this->bindClientThatMustNeverBeCalled();

        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => '']);

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('GOOGLE_PLACES_ENABLED is false. Refusing to geocode: no Google request will be made.')
            ->assertExitCode(1);
    }

    public function test_dry_run_is_refused_too(): void
    {
        // --dry-run issues no request today, but the refusal belongs at the
        // boundary so a future edit cannot reopen the hole beneath it.
        $this->bindClientThatMustNeverBeCalled();

        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-live-looking-key']);

        $this->artisan('app:geocode-seller-landlord-listings', ['--dry-run' => true])
            ->expectsOutput('GOOGLE_PLACES_ENABLED is false. Refusing to geocode: no Google request will be made.')
            ->assertExitCode(1);
    }

    // ── the credential gate still works ─────────────────────────────────────

    public function test_it_still_refuses_when_enabled_but_the_credential_is_missing(): void
    {
        // The pre-existing gate is unchanged; the kill switch was added in front
        // of it, not in place of it.
        $this->bindClientThatMustNeverBeCalled();

        config(['google_places.enabled' => true]);
        config(['services.google.places_key' => '']);

        $this->artisan('app:geocode-seller-landlord-listings')
            ->expectsOutput('GOOGLE_PLACES_API_KEY is not configured. Cannot geocode.')
            ->assertExitCode(1);
    }

    // ── the guard is real, not incidental ───────────────────────────────────

    public function test_the_command_reads_the_shared_kill_switch_and_not_a_second_flag(): void
    {
        // A copy of this switch under another name would drift out of agreement
        // with every other Google caller. Pin the config key by source.
        $source = file_get_contents(
            (new \ReflectionClass(GeocodeSelleryLandlordListings::class))->getFileName()
        );

        $this->assertStringContainsString(
            "config('google_places.enabled'",
            $source,
            'The command must consult the shared kill switch'
        );
    }

    public function test_the_geocode_helper_is_the_only_outbound_call_site(): void
    {
        // If a second outbound call site is ever added, the single boundary guard
        // stops being sufficient and this test says so.
        $source = file_get_contents(
            (new \ReflectionClass(GeocodeSelleryLandlordListings::class))->getFileName()
        );

        $this->assertSame(
            1,
            substr_count($source, 'maps.googleapis.com'),
            'A new outbound call site needs its own guard, or the boundary guard needs to cover it'
        );

        $this->assertTrue(
            (new ReflectionMethod(GeocodeSelleryLandlordListings::class, 'geocode'))->isPrivate(),
            'The outbound helper stays private so handle() remains the only entry point'
        );
    }
}
