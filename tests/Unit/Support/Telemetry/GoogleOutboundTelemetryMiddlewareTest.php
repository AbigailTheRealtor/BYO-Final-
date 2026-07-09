<?php

namespace Tests\Unit\Support\Telemetry;

use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use App\Support\Telemetry\OutboundCallContext;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase 0 / S3a — server-side outbound Google telemetry.
 *
 * The load-bearing assertion is `it_detects_a_rejected_credential_behind_an_http_200`:
 * Google answers an invalid or revoked key with **HTTP 200 and a REQUEST_DENIED body**,
 * so HTTP status alone cannot distinguish a working credential from a dead one. Reading
 * the in-body `status` is what lets Phase 0 telemetry answer the credential question
 * from our own logs, with no paid probe (SIA-D32).
 */
class GoogleOutboundTelemetryMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(GoogleOutboundTelemetryMiddleware::COUNTER_KEY);
        OutboundCallContext::clear();
    }

    private function clientWith(Response $response): Client
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(GoogleOutboundTelemetryMiddleware::make(), 'telemetry');

        return new Client(['handler' => $stack]);
    }

    /** @test */
    public function it_counts_a_request_to_a_google_maps_host(): void
    {
        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());

        $this->clientWith(new Response(200, [], '{"status":"OK","results":[]}'))
            ->request('GET', 'https://maps.googleapis.com/maps/api/place/nearbysearch/json');

        $this->assertSame(1, GoogleOutboundTelemetryMiddleware::counter());
    }

    /** @test */
    public function it_ignores_hosts_that_are_not_google_maps(): void
    {
        $this->clientWith(new Response(200, [], '{}'))
            ->request('GET', 'https://example.test/anything');

        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());
    }

    /** @test */
    public function it_detects_a_rejected_credential_behind_an_http_200(): void
    {
        Log::spy();

        $this->clientWith(new Response(200, [], '{"status":"REQUEST_DENIED","error_message":"The provided API key is invalid."}'))
            ->request('GET', 'https://maps.googleapis.com/maps/api/place/nearbysearch/json');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) {
                return $message === 'outbound_google_request.credential_rejected'
                    && $context['http_status'] === 200
                    && $context['google_status'] === 'REQUEST_DENIED'
                    && $context['credential_rejected'] === true
                    && $context['google_error'] === 'The provided API key is invalid.';
            })
            ->once();
    }

    /** @test */
    public function a_healthy_response_is_logged_as_a_warning_not_an_error(): void
    {
        // Under SIA-D25 any outbound Google call is a defect, so even OK is a warning.
        Log::spy();

        $this->clientWith(new Response(200, [], '{"status":"OK","results":[]}'))
            ->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) {
                return $message === 'outbound_google_request'
                    && $context['google_status'] === 'OK'
                    && $context['credential_rejected'] === false
                    && $context['endpoint'] === '/maps/api/geocode/json';
            })
            ->once();

        Log::shouldNotHaveReceived('error');
    }

    /** @test */
    public function it_attributes_a_request_to_the_listing_that_provoked_it(): void
    {
        Log::spy();
        OutboundCallContext::for('seller_agent_auction', 4242);

        $this->clientWith(new Response(200, [], '{"status":"OK"}'))
            ->request('GET', 'https://maps.googleapis.com/maps/api/place/nearbysearch/json');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m, array $c) => $c['listing_type'] === 'seller_agent_auction' && $c['listing_id'] === 4242)
            ->once();
    }

    /** @test */
    public function it_does_not_consume_the_response_body_the_caller_is_about_to_read(): void
    {
        $response = $this->clientWith(new Response(200, [], '{"status":"OK","results":[{"name":"Publix"}]}'))
            ->request('GET', 'https://maps.googleapis.com/maps/api/place/nearbysearch/json');

        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame('Publix', $body['results'][0]['name']);
    }
}
