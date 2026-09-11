<?php

namespace App\Support\Google;

use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * The ONE construction of the outbound HTTP client every server-side Google caller uses.
 *
 * The container binds `ClientInterface` to this (AppServiceProvider), and the tests build the
 * same client over a fake transport — so what a test proves is the production stack itself,
 * not a hand-assembled copy of it that could drift.
 *
 * THE ORDER IS THE POINT. Admission wraps telemetry: a refused request returns before
 * telemetry runs, so it is never counted or logged as an outbound Google request — because it
 * never was one. Telemetry records only what actually left the process.
 */
final class GoogleHttpClientFactory
{
    public const TELEMETRY = 'byo_google_outbound_telemetry';

    /**
     * @param callable|null $handler the transport at the bottom of the stack; null is Guzzle's
     *        default, i.e. the network. Tests pass a fake here and nowhere else.
     */
    public static function make(?callable $handler = null): Client
    {
        $stack = HandlerStack::create($handler);
        $stack->push(GoogleOutboundTelemetryMiddleware::make(), self::TELEMETRY);
        $stack->before(self::TELEMETRY, GoogleProviderAdmissionMiddleware::make(), GoogleProviderAdmissionMiddleware::NAME);

        return new Client(['handler' => $stack]);
    }
}
