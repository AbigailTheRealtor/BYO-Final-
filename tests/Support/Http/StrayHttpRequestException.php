<?php

namespace Tests\Support\Http;

use RuntimeException;

/**
 * Thrown when a test issues an HTTP request through the `Http` facade that no
 * `Http::fake()` stub matched — i.e. a request that would have hit the live network.
 *
 * The Laravel 9 equivalent is `Illuminate\Http\Client\StrayRequestException`, raised
 * by `Http::preventStrayRequests()`. **This application runs Laravel 8.83, where
 * neither exists** (see erratum E-39), so `StrayRequestGuardFactory` backports the
 * behaviour and raises this exception instead.
 */
class StrayHttpRequestException extends RuntimeException
{
    public static function forUrl(string $method, string $url): self
    {
        return new self(
            "BLOCKED stray outbound HTTP request during testing: {$method} {$url}\n"
            . "No Http::fake() stub matched this request, so it would have hit the live network.\n"
            . "Fix by stubbing it — Http::fake(['host/path*' => Http::response([...])]) — or by\n"
            . 'keeping the code path disabled in tests. See INV-11 and erratum E-39.'
        );
    }
}
