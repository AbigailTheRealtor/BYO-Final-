{{--
  Google Maps Script Loader — Hotfix #2851
  =========================================
  Injects the Google Maps JavaScript API <script> tag with the correct API key
  and requested library set.  Renders a visible amber warning when the key is
  absent so developers notice the misconfiguration immediately.

  Prerequisites
  -------------
  - GOOGLE_PLACES_API_KEY must be set in .env (not only as a Replit secret).
    The artisan serve / php-fpm process reads from .env via phpdotenv; Replit
    secrets alone are NOT injected into the workflow process environment.
  - The key must have the Maps JavaScript API and Places API enabled in Google
    Cloud Console, and must allow the dev/prod domains as referrers.

  Props
  -----
  libraries  (string, default 'places')  — comma-separated Maps JS library list
  callback   (string|null, default null) — global JS function name called once
             the API is ready (e.g. 'byoInitSellerOfferPlaces')
--}}
@props(['libraries' => 'places', 'callback' => null])
@php
    $key = config('services.google.places_key', '');
@endphp

@if($key !== '' && $key !== null)
    @php
        $src = 'https://maps.googleapis.com/maps/api/js?key=' . $key . '&libraries=' . $libraries;
        if ($callback) {
            $src .= '&callback=' . $callback;
        }
    @endphp
    {{-- Phase 0 / S3b — browser credential telemetry.
         Google Maps JS invokes window.gm_authFailure() when the key is invalid, revoked,
         expired, or unauthorised for this referrer. Defined BEFORE the SDK loads so the
         callback exists when the SDK looks for it. Server telemetry cannot observe the
         browser's direct calls to Google; this is how we learn the browser key's true
         state without a billed probe (SIA-D32). Diagnostic only — no behaviour change. --}}
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

    <script async defer src="{{ $src }}"></script>
    {{-- Self-diagnosing warning: if the Maps API fails to load (e.g. RefererNotAllowedMapError),
         a clear console.error fires after 5 s explaining what the developer must do in Google Cloud
         Console. This does NOT change any functional behaviour — it is diagnostic-only. --}}
    <script>
    (function () {
        setTimeout(function () {
            if (typeof google === 'undefined') {
                console.error(
                    '[BYO Maps] Google Maps did not load within 5 seconds. ' +
                    'If you see a RefererNotAllowedMapError or InvalidKeyMapError above, ' +
                    'add this domain to the API key\'s referrer allowlist in Google Cloud Console: ' +
                    window.location.hostname + '\n' +
                    'Common domains to allow:\n' +
                    '  ' + window.location.hostname + '/*\n' +
                    'Also ensure the Maps JavaScript API and Places API are enabled for the key.'
                );
            }
        }, 5000);
    })();
    </script>
@else
    <div style="border: 2px solid #f59e0b; background-color: #fffbeb; color: #92400e; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 4px 0;">
        &#9888; Google Maps is not configured for this environment &mdash; address autocomplete is unavailable.
    </div>
@endif
