<?php

namespace App\Support\LocationDna;

/**
 * LocationDataAttribution — which upstream notices a Location DNA surface owes.
 *
 * `config/location_attribution.php` says what each data source is and what its
 * license compels. This class answers the question a page actually has, which is
 * narrower: given the rows THIS page is about to render, whose notice has to go
 * with them.
 *
 * WHY RESOLUTION IS FROM THE ROWS, NOT FROM THE ACTIVE PROVIDER
 * -------------------------------------------------------------
 * The obvious implementation asks the provider registry which adapter is the
 * effective base and attributes that. It is wrong in both directions.
 *
 * A POI row persists. Switch the corpus off and the rows it wrote are still on
 * the page tomorrow — still owing Overture attribution, while the registry now
 * says Google. Switch the corpus on and yesterday's Google rows are still there
 * until they are refetched, and attributing them to Overture claims provenance
 * they do not have. Provider config describes what will be fetched NEXT; a
 * license obligation is about what is on the screen NOW.
 *
 * So {@see self::forPois()} reads each row's own `provenance_json.provider` and
 * unions the sources those providers map to. A page showing a mix attributes the
 * mix, which is the honest answer and also the only one that stays correct
 * across an activation.
 *
 * WHY `data_source` IS NOT THE FIELD READ, EVEN NOW THAT IT IS CORRECT
 * ---------------------------------------------------------------------
 * `property_location_pois.data_source` used to be written as the literal string
 * `'google_places'` regardless of which adapter answered — it predates the
 * provider registry. {@see \App\Services\LocationDna\LocationDnaPoiDistanceService}
 * now writes the resolved provider there instead, so the two agree on new rows.
 *
 * Attribution still reads `provenance_json.provider`, for two reasons. It is the
 * richer record — provenance carries the license and contributor list beside the
 * provider, so one field cannot drift from the others. And any row written before
 * that correction still carries the old literal, so a reader that trusted
 * `data_source` would credit Google for a corpus row: a false statement about a
 * third party, in the one place where being wrong is a license breach rather than
 * a bug. One field is the attribution source of truth, and it is this one.
 *
 * A ROW WHOSE PROVIDER WE CANNOT PLACE ATTRIBUTES NOTHING
 * -------------------------------------------------------
 * An unknown or absent provider yields no source rather than a default. The
 * component then renders nothing for it. That is deliberate and matches the
 * posture the rest of this subsystem takes toward unknowns
 * ({@see \App\Services\LocationDna\Providers\CorpusPoiCategoryMap}): a guessed
 * attribution is a false claim about someone else's data, and a missing one is
 * a gap we can see in `noticeObligationsOutstanding()`.
 *
 * NO BOOTED CONTAINER REQUIRED. Same reason and same shape as
 * {@see \App\Support\OfferListing\LandlordScreeningPolicy::conf()}: this is called
 * from Blade, and is unit-tested without an application. Pure otherwise — no
 * database, no clock, no network.
 *
 * @see \Tests\Unit\LocationDna\LocationDataAttributionTest
 */
final class LocationDataAttribution
{
    /** Cached file-loaded config for the no-container path. */
    private static ?array $fileConfig = null;

    /**
     * The attribution definitions, with or without a booted application.
     *
     * @return array<string, mixed>
     */
    private static function conf(): array
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config('location_attribution');
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return $fromContainer;
                    }
                }
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }

        if (self::$fileConfig === null) {
            $path   = __DIR__ . '/../../../config/location_attribution.php';
            $loaded = is_file($path) ? require $path : [];
            self::$fileConfig = is_array($loaded) ? $loaded : [];
        }

        return self::$fileConfig;
    }

    /**
     * Every configured source, keyed by source id, in declaration order.
     *
     * The data-sources page renders this whole list — including the sources whose
     * `attribution_required` is false. A licenses page that named only what it was
     * forced to name would be a shorter page and a less useful one.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allSources(): array
    {
        $sources = self::conf()['sources'] ?? [];

        return is_array($sources) ? $sources : [];
    }

    /**
     * One source descriptor by id, with its own id merged in, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function source(string $sourceId): ?array
    {
        $source = self::allSources()[$sourceId] ?? null;

        return is_array($source) ? ['id' => $sourceId] + $source : null;
    }

    /**
     * The source descriptors a single provider id owes.
     *
     * An unmapped provider owes nothing — see the class note. `stub` is the clear
     * case: fixture data has no upstream to credit.
     *
     * @return list<array<string, mixed>>
     */
    public static function forProvider(string $providerId): array
    {
        $map = self::conf()['provider_sources'] ?? [];
        $ids = is_array($map) && isset($map[$providerId]) ? (array) $map[$providerId] : [];

        $out = [];
        foreach ($ids as $sourceId) {
            $source = self::source((string) $sourceId);
            if ($source !== null) {
                $out[] = $source;
            }
        }

        return $out;
    }

    /**
     * The source descriptors owed by a set of provider ids, de-duplicated and
     * returned in `sources` declaration order rather than in discovery order.
     *
     * Declaration order because the list is user-visible: it must not reshuffle
     * because one listing happened to have its grocery store fetched before its
     * pharmacy.
     *
     * @param  iterable<string> $providerIds
     * @return list<array<string, mixed>>
     */
    public static function forProviders(iterable $providerIds): array
    {
        $wanted = [];
        foreach ($providerIds as $providerId) {
            if (! is_string($providerId) || $providerId === '') {
                continue;
            }
            foreach (self::forProvider($providerId) as $source) {
                $wanted[$source['id']] = true;
            }
        }

        $out = [];
        foreach (array_keys(self::allSources()) as $sourceId) {
            if (isset($wanted[$sourceId])) {
                $out[] = self::source($sourceId);
            }
        }

        return $out;
    }

    /**
     * The source descriptors owed by a collection of persisted POI rows.
     *
     * Accepts anything iterable yielding models, stdClass rows or arrays, because
     * the callers differ: the agent panel passes an Eloquent collection, the
     * buyer/tenant path passes mapped arrays, and a test passes plain objects.
     *
     * @param  iterable<mixed> $pois
     * @return list<array<string, mixed>>
     */
    public static function forPois(iterable $pois): array
    {
        $providerIds = [];

        foreach ($pois as $poi) {
            $provider = self::providerOf($poi);
            if ($provider !== null) {
                $providerIds[$provider] = true;
            }
        }

        return self::forProviders(array_keys($providerIds));
    }

    /**
     * The provider id recorded on one POI row, or null when it records none.
     *
     * Reads `provenance_json.provider`. Tolerates the JSON arriving already cast
     * to an array (the model casts it) or still as a string (a raw query row).
     */
    private static function providerOf(mixed $poi): ?string
    {
        $provenance = null;

        if (is_array($poi)) {
            $provenance = $poi['provenance_json'] ?? null;
        } elseif (is_object($poi)) {
            $provenance = $poi->provenance_json ?? null;
        }

        if (is_string($provenance)) {
            $decoded    = json_decode($provenance, true);
            $provenance = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($provenance)) {
            return null;
        }

        $provider = $provenance['provider'] ?? null;

        return (is_string($provider) && $provider !== '') ? $provider : null;
    }

    /**
     * Sources whose license obliges us to reproduce an upstream NOTICE that we
     * have not yet verified we hold.
     *
     * This is the pre-activation gate in machine-readable form. A non-empty return
     * means at least one provider must not be publishing to users yet — see
     * {@see self::providerBlockedByNotice()}.
     *
     * @return list<array<string, mixed>>
     */
    public static function noticeObligationsOutstanding(): array
    {
        $out = [];

        foreach (self::allSources() as $sourceId => $source) {
            if (($source['notice_required'] ?? false) === true
                && ($source['notice_verified'] ?? false) !== true) {
                $out[] = ['id' => $sourceId] + $source;
            }
        }

        return $out;
    }

    /**
     * Whether this provider is blocked from publishing by an unmet NOTICE
     * obligation on any source it maps to.
     *
     * Deliberately NOT consulted by the adapter or the registry. This phase does
     * not activate anything, and wiring a licensing check into the fetch path
     * would be a behaviour change smuggled in under a documentation change. It is
     * an assertion surface: the readiness tests and the operator-facing canary
     * command read it, and a human decides.
     */
    public static function providerBlockedByNotice(string $providerId): bool
    {
        foreach (self::forProvider($providerId) as $source) {
            if (($source['notice_required'] ?? false) === true
                && ($source['notice_verified'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }
}
