<?php

namespace App\Support\Telemetry;

use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Guzzle middleware recording every server-side outbound request to Google Maps
 * Platform (Phase 0 / S3a).
 *
 * Pushed onto the handler stack of the container-bound ClientInterface, so it
 * observes all three server-side Google callers at once without either of them
 * knowing it exists.
 *
 * WHY THE RESPONSE BODY IS PARSED
 * -------------------------------
 * Google Maps Platform returns **HTTP 200 with `{"status":"REQUEST_DENIED"}`** for
 * an invalid, revoked, or unauthorised key. HTTP status alone therefore cannot tell
 * a working credential from a dead one. `google_status` is the field that can, and
 * it is the reason this middleware exists: it answers the credential question from
 * our own logs, with no paid probe (SIA-D32).
 *
 * Under SIA-D25 the platform is Google-free by design, so any outbound Google call
 * from the server is a defect. Calls are logged at `warning`; a rejected credential
 * is logged at `error`. INV-1 asserts the counter stays at zero.
 *
 * Telemetry must never break the call it observes: every failure path is swallowed.
 */
class GoogleOutboundTelemetryMiddleware
{
    /** Cache key backing `outbound_google_requests_total` (INV-1). */
    public const COUNTER_KEY = 'telemetry:outbound_google_requests_total';

    private const GOOGLE_HOSTS = [
        'maps.googleapis.com',
        'maps.google.com',
    ];

    /** Bodies larger than this are not parsed for a `status` field. */
    private const MAX_PARSED_BODY_BYTES = 1_048_576;

    /** Google `status` values that indicate the credential itself was rejected. */
    private const CREDENTIAL_REJECTED_STATUSES = [
        'REQUEST_DENIED',
        'OVER_QUERY_LIMIT',
    ];

    public static function make(): callable
    {
        return static fn (callable $handler): callable =>
            static function (RequestInterface $request, array $options) use ($handler) {
                if (! self::isGoogleHost($request->getUri()->getHost())) {
                    return $handler($request, $options);
                }

                $startedAt = microtime(true);

                return $handler($request, $options)->then(
                    static function (ResponseInterface $response) use ($request, $startedAt) {
                        self::record($request, $startedAt, $response, null);

                        return $response;
                    },
                    static function ($reason) use ($request, $startedAt) {
                        self::record($request, $startedAt, null, $reason);

                        return Create::rejectionFor($reason);
                    },
                );
            };
    }

    public static function isGoogleHost(string $host): bool
    {
        return in_array(strtolower($host), self::GOOGLE_HOSTS, true);
    }

    public static function counter(): int
    {
        return (int) Cache::get(self::COUNTER_KEY, 0);
    }

    private static function record(
        RequestInterface $request,
        float $startedAt,
        ?ResponseInterface $response,
        mixed $reason,
    ): void {
        try {
            $google = $response !== null ? self::extractGoogleStatus($response) : [];
            $status = $google['status'] ?? null;
            $rejected = $status !== null && in_array($status, self::CREDENTIAL_REJECTED_STATUSES, true);

            $context = [
                'host'                => $request->getUri()->getHost(),
                'endpoint'            => $request->getUri()->getPath(),
                'method'              => $request->getMethod(),
                'http_status'         => $response?->getStatusCode(),
                'google_status'       => $status,
                'google_error'        => $google['error_message'] ?? null,
                'credential_rejected' => $rejected,
                'duration_ms'         => (int) round((microtime(true) - $startedAt) * 1000),
                'listing_type'        => OutboundCallContext::listingType(),
                'listing_id'          => OutboundCallContext::listingId(),
                'transport_error'     => $reason instanceof Throwable ? $reason->getMessage() : null,
            ];

            self::incrementCounter();

            $rejected
                ? Log::error('outbound_google_request.credential_rejected', $context)
                : Log::warning('outbound_google_request', $context);
        } catch (Throwable) {
            // Never let telemetry break the call it observes.
        }
    }

    private static function incrementCounter(): void
    {
        if (! Cache::has(self::COUNTER_KEY)) {
            Cache::forever(self::COUNTER_KEY, 0);
        }

        Cache::increment(self::COUNTER_KEY);
    }

    /**
     * Read Google's in-body `status` without consuming the stream the caller
     * is about to read.
     *
     * @return array{status?: string|null, error_message?: string|null}
     */
    private static function extractGoogleStatus(ResponseInterface $response): array
    {
        $body = $response->getBody();

        if (! $body->isSeekable() || $body->getSize() > self::MAX_PARSED_BODY_BYTES) {
            return [];
        }

        $raw = (string) $body;
        $body->rewind();

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        return [
            'status'        => is_string($decoded['status'] ?? null) ? $decoded['status'] : null,
            'error_message' => is_string($decoded['error_message'] ?? null) ? $decoded['error_message'] : null,
        ];
    }
}
