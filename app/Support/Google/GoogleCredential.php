<?php

namespace App\Support\Google;

/**
 * The single place the application asks "do we have a usable Google credential?"
 *
 * Phase 0 item 1 (SIP-P15, INV-12): **the credential's state must be irrelevant to
 * correctness.** Nothing crashes, and nothing silently lies, whether the key is alive,
 * dead, blank, or revoked tomorrow.
 *
 * Before this class, every Google caller read `config('services.google.places_key', '')`,
 * interpolated whatever it found into a query string, and issued the request regardless.
 * With a blank key Google answers **HTTP 200 + `{"status":"REQUEST_DENIED"}`** and an
 * empty `predictions` array — which the callers read as "no suggestions." The failure was
 * therefore indistinguishable from a genuine no-match, and it cost one outbound request
 * per keystroke. Erratum E-42 records how long that went unnoticed.
 *
 * Callers must consult `absent()` **before** resolving an HTTP client, so that a missing
 * credential produces zero outbound attempts (INV-11) rather than a doomed one.
 *
 * This intentionally guards on the credential ONLY. It does **not** consult
 * `config('google_places.enabled')`: that kill switch governs the billable Places
 * enrichment path (Nearby Search) and defaults to `false`, so honouring it here would
 * disable address autocomplete on every listing form the moment it was wired in.
 *
 * @see docs/architecture/SPATIAL-INTELLIGENCE-PLATFORM.md §17 Phase 0 item 1
 */
class GoogleCredential
{
    public static function value(): string
    {
        return trim((string) config('services.google.places_key', ''));
    }

    /** True when a credential is configured. Says nothing about whether Google accepts it. */
    public static function present(): bool
    {
        return self::value() !== '';
    }

    /**
     * True when no credential is configured — the caller must degrade rather than call.
     *
     * A present-but-rejected key still reaches Google; that case is observed by
     * GoogleOutboundTelemetryMiddleware, which parses the in-body status (SIA-D32).
     */
    public static function absent(): bool
    {
        return ! self::present();
    }
}
