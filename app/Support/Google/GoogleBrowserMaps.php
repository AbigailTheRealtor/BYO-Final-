<?php

namespace App\Support\Google;

/**
 * The single place this application asks "may a page carry a Google credential, and which one?"
 *
 * WHY A SECOND CLASS BESIDE GoogleCredential
 * ------------------------------------------
 * {@see GoogleCredential} answers for the SERVER key (`GOOGLE_PLACES_API_KEY`) — the credential
 * the container's HTTP client spends behind `GoogleProviderAdmissionMiddleware`'s budgets. That
 * key was also being printed into every page that loads the Maps SDK, including the PUBLIC Offer
 * Listing detail pages. A key in page source is a key anyone can copy, and because that one is
 * also used server-side it cannot be restricted to our own websites — so a copied key spends our
 * money against Places and Geocoding with no ceiling in front of it, because the ceilings live in
 * our process and a stranger's requests never pass through it.
 *
 * This class answers for the BROWSER key instead, and the two never mix.
 *
 * NO FALLBACK, DELIBERATELY
 * -------------------------
 * Nothing here reads `services.google.places_key`. With no browser key configured the loaders
 * render their existing degraded state — the amber "not configured" panel, free-text address
 * entry, no map — which is a visible, honest outcome. A fallback to the server key would restore
 * the exact exposure this class exists to end, and it would do it silently.
 *
 * BOTH HALVES MUST AGREE
 * ----------------------
 * `GOOGLE_MAPS_BROWSER_ENABLED` (default OFF, parsed fail-closed) and a non-empty
 * `GOOGLE_MAPS_BROWSER_KEY`. The switch is the emergency stop that needs no console access; the
 * key's absence is the ordinary "not configured here" state. Either one missing means no
 * credential reaches the page.
 */
final class GoogleBrowserMaps
{
    /** The master switch. Only a real boolean true enables; config parses the environment strictly. */
    public static function enabled(): bool
    {
        return config('google_maps_browser.enabled', false) === true;
    }

    /** The configured browser key, or '' when none is set. Says nothing about the switch. */
    public static function key(): string
    {
        return trim((string) config('google_maps_browser.key', ''));
    }

    /** True when a page may carry the browser credential: the switch is on AND a key exists. */
    public static function available(): bool
    {
        return self::enabled() && self::key() !== '';
    }

    /**
     * The key a view may render, or '' when it may not render one.
     *
     * Views branch on the empty string exactly as they always did, so the degraded panel, the
     * no-op injector and the "View on Google Maps" link all keep working unchanged.
     */
    public static function keyForRender(): string
    {
        return self::available() ? self::key() : '';
    }
}
