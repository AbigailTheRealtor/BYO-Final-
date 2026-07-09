{{--
  Deferred Google Maps SDK loader — Phase 0 item 1.
  ============================================================================
  Defines window.loadGoogleMapsScript(), which injects the Maps SDK *on demand* rather
  than at page load. Four legacy views (hire_landlord_agent/edit, landlord_auction/add,
  landlord_auction/edit, seller_property/edit) each carried a byte-identical copy of
  this function, its key guard, and the amber panel. They now share this component.

  Why this is NOT <x-google-maps-script>
  --------------------------------------
  The canonical component emits `<script async defer src=…>` at render time. These four
  views instead register 'loadGoogleMapsScript' in a deferred-initialisation list and
  call it later. Swapping in the canonical component would load the SDK on every page
  view — a different, and more expensive, network behaviour. Consolidating the *source
  of truth* (URL, key guard, telemetry, degraded panel) without changing *load timing*
  is the point.

  Adds what the copies lacked: the gm_authFailure telemetry partial, emitted before the
  SDK can ever be injected.

  Props
  -----
  callback   (string, default 'initializeMap') — global fn the SDK calls once ready
  libraries  (string, default 'places')
--}}
@props([
    'callback'  => 'initializeMap',
    'libraries' => 'places',
])

@php
    $mapsKey = \App\Support\Google\GoogleCredential::value();
@endphp

@if($mapsKey !== '')
    <x-google-maps-auth-telemetry />

    <script>
        function loadGoogleMapsScript() {
            // Idempotent: the deferred-init list can fire more than once per page.
            if (typeof google !== 'undefined' && google.maps) { return; }
            if (document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]')) { return; }

            var script = document.createElement('script');
            script.src = {{ Illuminate\Support\Js::from(
                'https://maps.googleapis.com/maps/api/js?key=' . $mapsKey . '&libraries=' . $libraries . '&callback=' . $callback
            ) }};
            script.async = true;
            script.defer = true;

            document.body.appendChild(script);
        }
    </script>
@else
    {{-- No credential: never inject the SDK, and give the deferred-init list a no-op so
         calling loadGoogleMapsScript() does not throw a ReferenceError. --}}
    <script>
        function loadGoogleMapsScript() { /* Google Maps not configured — see x-google-maps-unavailable */ }
    </script>

    <x-google-maps-unavailable />
@endif
