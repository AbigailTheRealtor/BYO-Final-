<?php

namespace Tests\Support\Http;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;

/**
 * A `PendingRequest` whose stub handler refuses to fall through to the real network.
 *
 * Laravel 8's `PendingRequest::buildStubHandler()` calls `$handler($request, $options)`
 * — the live Guzzle handler — whenever no registered `Http::fake()` stub returns a
 * response. That fall-through is the stray request. Laravel 9 added
 * `preventStrayRequests()` to throw there; this class backports precisely that branch
 * and changes nothing else.
 *
 * Note the eager `->map->__invoke(...)` below: Laravel evaluates **every** stub
 * callback for every request, then takes the first non-null result. That is why the
 * guard cannot be implemented as a throwing `Http::fake(callable)` — such a callback
 * would fire even for requests another stub had already matched. The guard belongs
 * here, after resolution, where "no stub matched" is actually knowable.
 *
 * @see StrayRequestGuardFactory
 * @see erratum E-39
 */
class GuardedPendingRequest extends PendingRequest
{
    public function buildStubHandler()
    {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                $response = ($this->stubCallbacks ?? collect())
                    ->map
                    ->__invoke((new Request($request))->withData($options['laravel_data'] ?? []), $options)
                    ->filter()
                    ->first();

                if (is_null($response)) {
                    // Laravel 8 would call $handler($request, $options) here and hit the network.
                    throw StrayHttpRequestException::forUrl(
                        $request->getMethod(),
                        (string) $request->getUri(),
                    );
                }

                $response = is_array($response) ? Factory::response($response) : $response;

                $sink = $options['sink'] ?? null;

                if ($sink) {
                    $response->then($this->sinkStubHandler($sink));
                }

                return $response;
            };
        };
    }
}
