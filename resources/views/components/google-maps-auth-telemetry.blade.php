{{--
  Google Maps browser-credential telemetry — Phase 0 / S3b.
  ============================================================================
  Google Maps JS invokes window.gm_authFailure() when the key is invalid, revoked,
  expired, or unauthorised for this referrer. Server telemetry
  (GoogleOutboundTelemetryMiddleware) cannot observe the browser's direct calls to
  Google, so this callback is the only way to learn the *browser* key's true state
  without a billed probe (SIA-D32: telemetry, never a probe).

  This partial is the single source of truth for that callback. It must be emitted
  BEFORE any Maps SDK <script> tag, so the callback exists when the SDK looks for it —
  including the deferred loaders, which inject the SDK long after page load.

  Telemetry must never break the page, hence the try/catch and the .catch() on the fetch.

  Phase 0 (Spatial UI Integration) additionally makes the failure *legible to the page*.
  Google calls this callback and then goes quiet — nothing else ever fires, so any UI
  waiting on the SDK waits forever. Recording the rejection on `window` and announcing it
  once lets a map surface stop waiting and say so, instead of spinning indefinitely on
  "Loading map…". This is a notification, not a behaviour change: nothing here retries,
  probes, or loads an alternative provider (SIA-D32 — telemetry, never a probe).

  Used by: <x-google-maps-script>, <x-google-maps-deferred-loader>, and the
  location-dna-map component's self-booting injector.
--}}
@once
<script>
(function () {
    if (typeof window.gm_authFailure === 'function') { return; }

    /* Read by any surface that needs to know the credential is dead — including
       surfaces that boot AFTER the failure has already happened, which an event
       alone would not reach. */
    window.byoMapsAuthFailed = false;

    window.gm_authFailure = function () {
        console.error('[BYO Maps] Google rejected the Maps API key (gm_authFailure).');

        window.byoMapsAuthFailed = true;
        try {
            document.dispatchEvent(new CustomEvent('byo:maps-auth-failed'));
        } catch (e) { /* notification must never break the page */ }

        try {
            fetch(@json(route('telemetry.maps-auth-failure')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ page: window.location.pathname }),
                keepalive: true,
                credentials: 'same-origin',
            }).catch(function () { /* telemetry must never break the page */ });
        } catch (e) { /* telemetry must never break the page */ }
    };
})();
</script>
@endonce
