<?php

namespace Tests\Support\Http;

use Illuminate\Http\Client\Factory;

/**
 * Backport of `Http::preventStrayRequests()` for Laravel 8.83 (erratum E-39).
 *
 * `Tests\TestCase` binds an instance of this factory into the container as
 * `Illuminate\Http\Client\Factory`, which is what the `Http` facade resolves. Every
 * pending request it hands out refuses to reach the live network when no
 * `Http::fake()` stub matched.
 *
 * Tests that legitimately exercise an HTTP-facade code path (Bridge, FEMA, Census
 * TIGER, NCES, MLS import) keep working unchanged: they call `Http::fake([...])`, a
 * stub matches, and the guard never fires. Tests that forgot to stub now fail loudly
 * with `StrayHttpRequestException` instead of silently making a real request.
 *
 * This closes the half of INV-11 that `BlocksGooglePlacesHttpClient` cannot see:
 * that client guards the container-bound **Guzzle** `ClientInterface`, whereas the
 * `Http` facade builds its own Guzzle client and bypasses the container entirely.
 * `GeocodeSelleryLandlordListings.php:127` calls Google through the facade and was
 * invisible to every guard that existed before this class.
 */
class StrayRequestGuardFactory extends Factory
{
    protected function newPendingRequest()
    {
        return new GuardedPendingRequest($this);
    }
}
