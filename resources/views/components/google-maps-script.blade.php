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
    {{-- Phase 0 / S3b — browser credential telemetry. Emitted BEFORE the SDK loads so the
         callback exists when the SDK looks for it. Extracted to a shared partial in
         Batch 5 so the deferred loaders get it too. --}}
    <x-google-maps-auth-telemetry />

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
    <x-google-maps-unavailable />
@endif
