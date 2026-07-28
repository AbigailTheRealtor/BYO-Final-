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

  Phase 0 (Spatial UI Integration) — this partial is no longer diagnostic-only. It now
  also performs the HONEST DEGRADATION the audit called for: on rejection it hides any
  `[data-byo-maps-loading]` placeholder and reveals the `[data-byo-maps-unavailable]`
  notice beside it. Without this the search-area panel sits on "Loading map…" forever,
  which tells the user something is in progress when nothing is. Purely additive — a
  page with neither attribute is unaffected.

  Used by: <x-google-maps-script>, <x-google-maps-deferred-loader>, and the
  location-dna-map component's self-booting injector.
--}}
@once
<script>
(function () {
    if (typeof window.gm_authFailure === 'function') { return; }

    function revealMapsUnavailableNotice() {
        try {
            document.querySelectorAll('[data-byo-maps-loading]').forEach(function (el) {
                el.style.display = 'none';
            });
            document.querySelectorAll('[data-byo-maps-unavailable]').forEach(function (el) {
                el.style.display = 'flex';
            });
        } catch (e) { /* a degraded map must never break the page either */ }
    }

    window.gm_authFailure = function () {
        console.error('[BYO Maps] Google rejected the Maps API key (gm_authFailure).');
        revealMapsUnavailableNotice();
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
