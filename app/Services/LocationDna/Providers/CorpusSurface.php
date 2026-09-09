<?php

namespace App\Services\LocationDna\Providers;

/**
 * CorpusSurface — the identity of the LOCAL CORPUS a POI read would be answered from.
 *
 * WHY THIS EXISTS AS ITS OWN CLASS
 * --------------------------------
 * {@see LocationProviderRegistry::capabilityHash()} hashes `config/location_providers.php`
 * and nothing else: enabled providers, the capability map, the regional overrides. It is
 * the right answer to "which provider is routed", and it is a complete answer for every
 * provider that is a network service, because a network service has no other state.
 *
 * A local corpus does. Which import the Overture adapter serves is pinned in
 * `config/overture_corpus_poi.php` — a different file, deliberately, because pinning an
 * import is a different decision from routing to a provider. So the capability hash
 * cannot see it, and everything keyed on the capability hash alone treated two different
 * corpora as one surface.
 *
 * That had two consequences, both silent, and both of them bite during exactly the
 * operation the version pin exists to make safe — activating a second import so it can be
 * verified before it is trusted:
 *
 *   {@see \App\Services\LocationDna\LocationDnaPoiTileCache}  the tile key was unchanged,
 *       so the previous corpus's raw candidates were served under the new pin for the
 *       remainder of the tile TTL (7 days by default).
 *
 *   {@see \App\Services\LocationDna\LocationDnaVersionService::fetchVersion()}  the stamp
 *       written to every row's `pois_fetch_version` was unchanged, so already-persisted
 *       rows from the previous corpus read as current and were never refetched.
 *
 * Fixing them in two places would have meant two definitions of "the corpus surface" that
 * must agree forever. This is the one definition; both call it.
 *
 * NOT PART OF THE PROVIDER REGISTRY. The registry answers routing questions from one
 * config file and is used to reason about providers generally; folding a single provider's
 * private pin into its hash would make `capabilityHash()` mean something other than what
 * its name and its other callers say.
 *
 * Pure and deterministic. Reads config, touches nothing else.
 *
 * @see \Tests\Unit\LocationDna\PoiTileCacheCorpusIdentityTest
 * @see \Tests\Unit\LocationDna\CorpusSurfaceTest
 */
final class CorpusSurface
{
    /**
     * A short stable token for the current corpus surface.
     *
     * Mixes the adapter's own gate with the pinned corpus version. The gate belongs here
     * because it, too, lives outside `location_providers.php` and, too, decides whether the
     * corpus or a network provider answers — it is a second gate in a second file that the
     * capability hash cannot see.
     */
    public static function token(): string
    {
        return substr(hash('sha256', implode('|', [
            self::enabled() ? '1' : '0',
            self::version(),
        ])), 0, 16);
    }

    /** Whether the corpus adapter's own gate is on. */
    public static function enabled(): bool
    {
        return (bool) config('overture_corpus_poi.enabled', false);
    }

    /**
     * The pinned corpus version, normalised to '' when nothing is pinned.
     *
     * null and '' both mean "no version pinned" — the adapter reports itself unavailable
     * either way — so they are one surface and must hash identically. Splitting them would
     * double the fetches for two spellings of the same state.
     */
    public static function version(): string
    {
        $version = config('overture_corpus_poi.corpus_version');

        return ($version === null || $version === '') ? '' : (string) $version;
    }
}
