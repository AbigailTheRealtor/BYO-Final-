<?php

namespace Tests\Feature\Location;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\Location\Lookup\AddressLookupQuery;
use App\Services\Location\Lookup\AddressLookupResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The HTTP surface of the address lookup: who may call it, how often, and
 * exactly what comes back.
 *
 * The point of most of this file is negative. The endpoint fronts a third-party
 * provider, and the failure that matters is not "the wrong coordinate" — the
 * service tests cover that — but "something about our infrastructure went out
 * over the wire". So the response is asserted field by field, and the absence
 * of a provider name, a provider URL, an upstream status and a credential is
 * asserted directly rather than inferred from the shape looking about right.
 */
class AddressLookupEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = 'geocoding.geo.census.gov/*';
    private const ROUTE    = '/location/address-lookup';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('census_geocoder.enabled', true);
    }

    private function matchBody(): array
    {
        return [
            'result' => [
                'addressMatches' => [[
                    'tigerLine'         => ['side' => 'R', 'tigerLineId' => '104530163'],
                    'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
                    'addressComponents' => [
                        'zip'         => '33602',
                        'streetName'  => 'MADISON',
                        'city'        => 'TAMPA',
                        'state'       => 'FL',
                        'suffixType'  => 'ST',
                        'fromAddress' => '301',
                        'toAddress'   => '399',
                    ],
                    'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
                ]],
            ],
        ];
    }

    // ── access ──────────────────────────────────────────────────────────────

    public function test_an_authenticated_user_can_locate_an_address(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602']);

        $response->assertOk();
        $response->assertJson([
            'ok'      => true,
            'address' => '315 Madison St Tampa FL 33602',
        ]);
        $this->assertEqualsWithDelta(27.948434712759, $response->json('lat'), 0.0000001);
        $this->assertEqualsWithDelta(-82.458094358643, $response->json('lng'), 0.0000001);
    }

    public function test_a_guest_is_refused_and_no_request_is_sent(): void
    {
        // Not because an address is a secret, but because an unauthenticated
        // lookup box is a geocoding proxy anyone can point a script at — and
        // the ceiling it would spend is the whole application's.
        Http::fake();

        $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602'])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_route_carries_auth_csrf_and_its_own_throttle(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === ltrim(self::ROUTE, '/') && in_array('POST', $r->methods(), true));

        $this->assertNotNull($route, 'the address lookup route is not registered');

        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('throttle:address-lookup', $middleware);

        // Inside the `web` group, so a cross-site POST carrying a logged-in
        // user's cookies is rejected. Asserted structurally — through the group
        // and the kernel — because Laravel skips the CSRF check while running
        // tests, so no request made from here could ever demonstrate it.
        $this->assertContains('web', $middleware);
        $this->assertContains(
            VerifyCsrfToken::class,
            app(\App\Http\Kernel::class)->getMiddlewareGroups()['web'],
            'the web group no longer verifies CSRF'
        );
    }

    // ── what crosses the wire ───────────────────────────────────────────────

    public function test_the_success_response_carries_only_application_fields(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);
        $this->actingAs(User::factory()->create());

        $payload = $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602'])->json();

        $this->assertSame(['ok', 'address', 'lat', 'lng', 'precision'], array_keys($payload));
    }

    public function test_no_provider_detail_reaches_the_browser(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);
        $this->actingAs(User::factory()->create());

        $body = $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602'])
            ->getContent();

        foreach ([
            'census',            // the provider's name
            'geocoding.geo',     // its hostname
            'benchmark',         // its request parameters
            'tigerLine',         // its response payload
            'addressMatches',
            'apiKey',
            'api_key',
        ] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $body, "response leaks: {$forbidden}");
        }
    }

    public function test_a_provider_outage_is_an_ordinary_answer_and_not_a_5xx(): void
    {
        // A 5xx here would make an ordinary miss indistinguishable from a broken
        // endpoint to every retry policy and error reporter in between, and
        // would tempt a client into retrying something that cannot succeed.
        Http::fake([self::ENDPOINT => Http::response('', 500)]);
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602']);

        $response->assertOk();
        $response->assertExactJson([
            'ok'      => false,
            'message' => AddressLookupResult::FAILURE_MESSAGE,
        ]);
    }

    public function test_a_failed_lookup_carries_no_coordinate_at_all(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);
        $this->actingAs(User::factory()->create());

        $payload = $this->postJson(self::ROUTE, ['address' => '999999 Nowhere Rd, Tampa, FL 33602'])->json();

        $this->assertArrayNotHasKey('lat', $payload);
        $this->assertArrayNotHasKey('lng', $payload);
        $this->assertArrayNotHasKey('address', $payload);
    }

    // ── input ───────────────────────────────────────────────────────────────

    public function test_a_missing_address_is_a_validation_error(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->postJson(self::ROUTE, [])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_an_over_long_address_is_refused_before_the_provider(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->postJson(self::ROUTE, [
            'address' => str_repeat('a', AddressLookupQuery::MAX_INPUT_LENGTH + 1),
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_a_thin_address_answers_without_spending_a_request(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->postJson(self::ROUTE, ['address' => '11687 Oxford Street North'])
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => AddressLookupResult::FAILURE_MESSAGE]);

        Http::assertNothingSent();
    }

    // ── rationing ───────────────────────────────────────────────────────────

    public function test_the_per_identity_rate_limit_returns_429(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);
        $this->actingAs(User::factory()->create());

        // Twenty a minute is already far more than typing into a form produces;
        // past it is a stuck key, a script or a bug.
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(self::ROUTE, ['address' => "31{$i} E Madison St, Tampa, FL 33602"])
                ->assertOk();
        }

        $this->postJson(self::ROUTE, ['address' => '999 E Madison St, Tampa, FL 33602'])
            ->assertStatus(429);
    }

    public function test_the_limit_is_per_identity_and_not_global(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(self::ROUTE, ['address' => "31{$i} E Madison St, Tampa, FL 33602"]);
        }
        $this->postJson(self::ROUTE, ['address' => '999 E Madison St, Tampa, FL 33602'])
            ->assertStatus(429);

        // A second person is unaffected by the first's exhausted allowance.
        $this->actingAs(User::factory()->create());
        $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602'])
            ->assertOk();
    }

    public function test_repeating_one_lookup_does_not_repeat_the_provider_call(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);
        $this->actingAs(User::factory()->create());

        // The double-click case. The browser suppresses it too, but a server
        // that depends on the browser behaving is not protected.
        foreach (range(1, 5) as $ignored) {
            $this->postJson(self::ROUTE, ['address' => '315 E Madison St, Tampa, FL 33602'])->assertOk();
        }

        Http::assertSentCount(1);
    }
}
