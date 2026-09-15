<?php

namespace App\Support\Google;

use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;
use App\Support\Telemetry\OutboundCallContext;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Admission for server-side Google Maps Platform requests — a hard request ceiling,
 * enforced at the one place every server-side Google request passes through.
 *
 * WHY THE HTTP CLIENT AND NOT EACH CALLER
 * ---------------------------------------
 * Every server-side Google caller resolves its HTTP client from the container, and that
 * client is built in one place ({@see GoogleHttpClientFactory}). Admission there cannot be
 * forgotten by a new caller, cannot drift between two adapters, and sees retries as what
 * they are — another request — so a retry has to be admitted again like any other.
 *
 * ONE UNIT = ONE OUTBOUND REQUEST, ADMITTED BEFORE IT IS SENT
 * -----------------------------------------------------------
 * Each request of a budgeted family is admitted through the shared
 * {@see ProviderRequestBudget::admit()} — the same atomic, lock-serialised admission the
 * Explore budget uses, so a configured 25 admits exactly 25 however many PHP processes race
 * for the last unit. A refused request is never handed to the transport. A cache hit never
 * reaches this client, so it costs nothing. A request that is admitted and then fails still
 * cost a unit: it was sent.
 *
 * WHAT IS BUDGETED TODAY: NEARBY SEARCH AND GEOCODING, EACH ON ITS OWN ALLOWANCE
 * -------------------------------------------------------------------------------
 * Places Nearby Search — the call behind the 2026-07-05 incident — is admitted against
 * `GOOGLE_PLACES_HOURLY_LIMIT` / `GOOGLE_PLACES_DAILY_LIMIT` (25 / 100) under
 * `GOOGLE_PLACES_ENABLED`. Geocoding is admitted against its own
 * `GOOGLE_GEOCODING_HOURLY_LIMIT` / `GOOGLE_GEOCODING_DAILY_LIMIT` (25 / 100) under its own
 * `GOOGLE_GEOCODING_ENABLED`. Each family is a separate {@see ProviderRequestBudget}
 * (`google_places_nearby`, `google_geocoding`), so spending one never spends the other; they
 * share only the admission lock, which serialises decisions and counts nothing.
 *
 * Places Autocomplete is identified and passes through unbudgeted (telemetry still records
 * it): one request per keystroke on every listing form, so a ceiling sized for either family
 * above would break address entry within minutes. Budgeting another family later is one entry
 * in {@see BUDGETED} plus its config keys — never a second counter system.
 *
 * Browser-side Google (Maps JavaScript, `google.maps.places.Autocomplete` in the Blade
 * templates) never touches this server, so no server budget can govern it; that is Google
 * Cloud's job — a restricted browser key, quotas and billing alerts.
 *
 * FAILS CLOSED
 * ------------
 * A budgeted family is refused when its switch is off, when no credential is configured, when
 * a ceiling is reached, and when admission cannot be decided at all (lock timeout, cache
 * fault, anything thrown). A zero, negative or malformed limit is a ceiling of zero, so it
 * blocks rather than unleashes. The refusal is a {@see GoogleProviderRequestRefused}, never an
 * empty response — callers must be able to tell "we did not ask" from "Google found nothing".
 *
 * The counters and the lock live in the configured cache. On this deployment that is the
 * file cache on a single host, where the lock is exclusive across every PHP process. A
 * multi-host deployment would need them in shared atomic storage (Redis or the database)
 * before the same guarantee held across hosts.
 */
final class GoogleProviderAdmissionMiddleware
{
    public const NAME = 'byo_google_provider_admission';

    /** The event name every admission log line carries. */
    public const EVENT = 'google_provider_admission';

    public const FAMILY_PLACES_NEARBY       = 'places_nearby';
    public const FAMILY_PLACES_AUTOCOMPLETE = 'places_autocomplete';
    public const FAMILY_PLACES_TEXT_SEARCH  = 'places_text_search';
    public const FAMILY_PLACES_DETAILS      = 'places_details';
    public const FAMILY_PLACES_OTHER        = 'places_other';
    public const FAMILY_GEOCODING           = 'geocoding';
    public const FAMILY_MAPS_OTHER          = 'maps_other';

    public const REASON_SWITCHED_OFF       = 'switched_off';
    public const REASON_CREDENTIAL_MISSING = 'credential_missing';

    /**
     * The families admitted against a budget, and the config that governs each.
     *
     * A family absent from this map passes through unbudgeted. That is a deliberate, visible
     * gap — not an oversight — until the family has a limit somebody chose on evidence.
     */
    private const BUDGETED = [
        self::FAMILY_PLACES_NEARBY => [
            'enabled' => 'google_places.enabled',
            'hourly'  => 'google_places.hourly_limit',
            'daily'   => 'google_places.daily_limit',
        ],
        self::FAMILY_GEOCODING => [
            'enabled' => 'google_geocoding.enabled',
            'hourly'  => 'google_geocoding.hourly_limit',
            'daily'   => 'google_geocoding.daily_limit',
        ],
    ];

    public static function make(): callable
    {
        return static fn (callable $handler): callable =>
            static function (RequestInterface $request, array $options) use ($handler) {
                $family = self::familyOf($request);

                if ($family === null || ! isset(self::BUDGETED[$family])) {
                    return $handler($request, $options);
                }

                $refusal = self::admit($family);

                self::record($request, $family, $refusal);

                if ($refusal !== null) {
                    return Create::rejectionFor(new GoogleProviderRequestRefused($family, $refusal));
                }

                return $handler($request, $options);
            };
    }

    /** Which Google API family a request belongs to, or null when it is not a Google host. */
    public static function familyOf(RequestInterface $request): ?string
    {
        $host = strtolower($request->getUri()->getHost());
        $path = strtolower($request->getUri()->getPath());

        if ($host === 'places.googleapis.com') {
            return match (true) {
                str_contains($path, ':searchnearby') => self::FAMILY_PLACES_NEARBY,
                str_contains($path, ':searchtext')   => self::FAMILY_PLACES_TEXT_SEARCH,
                str_contains($path, ':autocomplete') => self::FAMILY_PLACES_AUTOCOMPLETE,
                default                              => self::FAMILY_PLACES_OTHER,
            };
        }

        if ($host !== 'maps.googleapis.com' && $host !== 'maps.google.com') {
            return null;
        }

        return match (true) {
            str_starts_with($path, '/maps/api/place/nearbysearch') => self::FAMILY_PLACES_NEARBY,
            str_starts_with($path, '/maps/api/place/autocomplete') => self::FAMILY_PLACES_AUTOCOMPLETE,
            str_starts_with($path, '/maps/api/place/textsearch')   => self::FAMILY_PLACES_TEXT_SEARCH,
            str_starts_with($path, '/maps/api/place/details')      => self::FAMILY_PLACES_DETAILS,
            str_starts_with($path, '/maps/api/place/')             => self::FAMILY_PLACES_OTHER,
            str_starts_with($path, '/maps/api/geocode')            => self::FAMILY_GEOCODING,
            default                                                => self::FAMILY_MAPS_OTHER,
        };
    }

    /** Whether a family is admitted against a budget at all. */
    public static function isBudgeted(string $family): bool
    {
        return isset(self::BUDGETED[$family]);
    }

    /**
     * The shared budget a family is admitted against, or null when it is not budgeted.
     *
     * `(int)` of a missing, zero, negative or malformed limit is at most zero, and a ceiling
     * of zero admits nothing — a typo blocks the provider rather than unleashing it.
     */
    public static function budgetFor(string $family): ?ProviderRequestBudget
    {
        $keys = self::BUDGETED[$family] ?? null;

        if ($keys === null) {
            return null;
        }

        return new ProviderRequestBudget(
            'google_' . $family,
            (int) config($keys['hourly'], 0),
            (int) config($keys['daily'], 0),
        );
    }

    /** Null when the request may be sent — and has been charged — otherwise why not. */
    private static function admit(string $family): ?string
    {
        try {
            $keys = self::BUDGETED[$family];

            // Only a real boolean true enables. config/google_places.php and
            // config/google_geocoding.php parse the environment strictly; this holds for
            // a value set any other way too.
            if (config($keys['enabled'], false) !== true) {
                return self::REASON_SWITCHED_OFF;
            }

            if (GoogleCredential::absent()) {
                return self::REASON_CREDENTIAL_MISSING;
            }

            return ProviderRequestBudget::admit([$family => self::budgetFor($family)]);
        } catch (Throwable) {
            return ProviderRequestBudget::REASON_ADMISSION_UNAVAILABLE;
        }
    }

    /**
     * One structured line per admission decision.
     *
     * The endpoint PATH only — the credential travels in the query string, so the query is
     * never logged. No coordinates, no address, no response payload.
     */
    private static function record(RequestInterface $request, string $family, ?string $refusal): void
    {
        try {
            $keys  = self::BUDGETED[$family];
            $spent = self::budgetFor($family)?->spent();

            $context = [
                'family'       => $family,
                'decision'     => $refusal === null ? 'allowed' : 'blocked',
                'reason'       => $refusal,
                'endpoint'     => $request->getUri()->getPath(),
                'hourly_spent' => $spent['hourly'] ?? null,
                'hourly_limit' => (int) config($keys['hourly'], 0),
                'daily_spent'  => $spent['daily'] ?? null,
                'daily_limit'  => (int) config($keys['daily'], 0),
                'listing_type' => OutboundCallContext::listingType(),
                'listing_id'   => OutboundCallContext::listingId(),
            ];

            $refusal === null
                ? Log::info(self::EVENT, $context)
                : Log::warning(self::EVENT, $context);
        } catch (Throwable) {
            // Logging must never change the decision it describes.
        }
    }
}
