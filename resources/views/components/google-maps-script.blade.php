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
    <script async defer src="{{ $src }}"></script>
@else
    <div style="border: 2px solid #f59e0b; background-color: #fffbeb; color: #92400e; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 4px 0;">
        &#9888; Google Maps is not configured for this environment &mdash; address autocomplete is unavailable.
    </div>
@endif
