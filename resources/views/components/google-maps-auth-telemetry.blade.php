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

  Diagnostic only. No behaviour change. Telemetry must never break the page, hence the
  try/catch and the .catch() on the fetch.

  Used by: <x-google-maps-script>, <x-google-maps-deferred-loader>, and the
  location-dna-map component's self-booting injector.
--}}
@once
<script>
(function () {
    if (typeof window.gm_authFailure === 'function') { return; }

    window.gm_authFailure = function () {
        console.error('[BYO Maps] Google rejected the Maps API key (gm_authFailure).');
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
