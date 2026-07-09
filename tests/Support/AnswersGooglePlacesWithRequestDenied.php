<?php

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;

/**
 * A container-bound Guzzle client that answers every Google request the way Google
 * itself answers a **blank or invalid API key**: HTTP 200 with a `REQUEST_DENIED`
 * body and no results.
 *
 * WHY THIS EXISTS
 * ---------------
 * Several Livewire address-autocomplete methods have no try/catch (pre-existing, and
 * deliberately preserved by Batch 3). Before Batch 3 they constructed a bare
 * `new \GuzzleHttp\Client()` and therefore issued a **real outbound request to
 * maps.googleapis.com on every test run** — invisible to `BlocksGooglePlacesHttpClient`,
 * which only guards the container binding. The tests passed because Google returns
 * `REQUEST_DENIED` for the blank test key, yielding an empty `predictions` array.
 *
 * Routing those call sites through the container (Batch 3) makes the guard visible to
 * them, so they now throw. Binding this double restores exactly the response the tests
 * were unknowingly depending on — **without** any packet leaving the process, and
 * without weakening `BlocksGooglePlacesHttpClient` for every other test.
 *
 * Prefer this over relaxing the guard: the guard is the invariant, this is the fixture.
 *
 * @see erratum E-38, E-41
 * @see \Tests\Support\BlocksGooglePlacesHttpClient
 */
class AnswersGooglePlacesWithRequestDenied
{
    public const BODY = '{"status":"REQUEST_DENIED","error_message":"The provided API key is invalid.","predictions":[],"results":[]}';

    public static function make(): Client
    {
        // A plain handler callable rather than MockHandler: MockHandler's queue is
        // consumed per request, and these components can issue several per test.
        $stack = HandlerStack::create(
            static fn () => Create::promiseFor(
                new Response(200, ['Content-Type' => 'application/json'], self::BODY),
            ),
        );

        return new Client(['handler' => $stack]);
    }
}
