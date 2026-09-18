{{--
    Virtual Drive provider proof — INTERNAL, DEVELOPMENT ONLY.

    STANDALONE ON PURPOSE (no layouts.main). The main layout brings app.js,
    jQuery and several third-party CDNs; this page must contain exactly ONE
    street-level provider and nothing else that draws a map. Google's Terms
    (§3.2.3(e)) forbid Street View imagery and a non-Google map on the same
    screen, so the providers are two pages and neither includes MapLibre.

    NOTHING STREET-LEVEL LOADS UNTIL THE LAUNCH BUTTON IS PRESSED. The page,
    the listing card, the photos and the nearby list all work before that; the
    provider library is requested only by the shell's launch(), from one
    deliberate click, and the button locks on that click.

    AND A SWITCHED-OFF PROVIDER IS NOT ON THE PAGE AT ALL. When a provider is
    disabled (VIRTUAL_DRIVE_GOOGLE_ENABLED=false), its script is not included,
    so nothing here is capable of constructing a panorama, and the launch panel
    states the server's reason. Everything else on the page still works.

    THE GOOGLE KEY IS NEVER IN THIS MARKUP. data-credential carries an inline
    credential only for a provider whose page delivers one (Apple). Google's
    browser key arrives only in the response to a granted launch claim at
    data-launch-claim-endpoint, which is also where the daily ceiling is spent —
    so a page that was refused holds no means of authenticating to Google.

    A REJECTED KEY STOPS EVERY LATER LAUNCH. When Google rejects the key, the
    shell reports it to data-google-auth-failure-endpoint and the server refuses
    all further claims until `php artisan virtual-drive:google-auth-block --reset`.
    data-google-auth-block carries a standing block into a reloaded page, so it
    starts locked and shows the recorded error and origin.

    TWO VIEWS OF ONE PAGE. ?view=customer hides the instrumentation and the
    observation sheet so the street-level experience and the shopper card are
    what a reviewer sees; the counters still run underneath. The default is the
    developer view.

    NO LISTING DATA IS RENDERED HERE. Everything about a property arrives from
    dev.virtual-drive.api.listings, which reads stored MLS rows through
    Explore's eligibility policy and projection allow-list.

    An inline provider credential is written into the page only when it is
    configured, and this route only answers when VirtualDriveProofGate allows it.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Virtual Drive proof · {{ $providerLabel }}</title>
    <link rel="stylesheet" href="/css/virtual-drive/virtual-drive.css">
</head>
<body class="vd-body vd-view-{{ $viewMode }}">
<div class="vd-shell" id="vd-shell"
     data-provider="{{ $provider }}"
     data-credential="{{ $credential ?? '' }}"
     data-credential-available="{{ $credentialAvailable ? '1' : '0' }}"
     data-provider-enabled="{{ $providerEnabled ? '1' : '0' }}"
     data-launch-claim-endpoint="{{ $claimEndpoint ?? '' }}"
     data-daily-launch-limit="{{ $dailyLaunchLimit }}"
     data-google-auth-failure-endpoint="{{ $authFailureEndpoint ?? '' }}"
     data-google-auth-block="{{ $authBlockJson ?? '' }}"
     data-csrf-token="{{ csrf_token() }}"
     data-credential-name="{{ $credentialName }}"
     data-library-url="{{ $libraryUrl ?? '' }}"
     data-api-version="{{ $apiVersion ?? '' }}"
     data-listings-endpoint="{{ route('dev.virtual-drive.api.listings', [], false) }}"
     data-nearby-radius="{{ $nearbyRadius }}"
     data-nearby-requery="{{ $nearbyRequery }}"
     data-selected-listing="{{ $selectedListing }}"
     data-default-listing="{{ $defaultListing }}"
     data-view-mode="{{ $viewMode }}"
     data-sign-max-distance="{{ $signs['max_distance_meters'] ?? '' }}"
     data-sign-min-distance="{{ $signs['min_distance_meters'] ?? '' }}"
     data-sign-near-width="{{ $signs['near_width_px'] ?? '' }}"
     data-sign-far-width="{{ $signs['far_width_px'] ?? '' }}"
     data-sign-group-radius="{{ $signs['group_radius_meters'] ?? '' }}"
     data-close-coverage="{{ $signs['close_coverage_meters'] ?? '' }}"
     data-launch-label="{{ $launchLabel }}">

    <header class="vd-topbar">
        <div class="vd-topbar-title">
            <span class="vd-internal">Internal proof · not for users{{ $viewMode === 'customer' ? ' · customer preview' : '' }}</span>
            <strong>{{ $providerLabel }}</strong>
        </div>
        <nav class="vd-provider-nav" aria-label="Street-level provider">
            <a href="{{ route('dev.virtual-drive.compare', [], false) }}">Comparison</a>
            <a href="{{ route('dev.virtual-drive.apple', [], false) }}" class="{{ $provider === 'apple' ? 'is-active' : '' }}">Apple Look Around</a>
            <a href="{{ route('dev.virtual-drive.google', [], false) }}" class="{{ $provider === 'google' ? 'is-active' : '' }}">Google Street View</a>
            @if ($viewMode === 'customer')
                <a href="{{ '?' . http_build_query(array_merge(request()->query(), ['view' => 'dev'])) }}">Developer view</a>
            @else
                <a href="{{ '?' . http_build_query(array_merge(request()->query(), ['view' => 'customer'])) }}">Customer preview</a>
            @endif
        </nav>
    </header>

    <main class="vd-stage">
        <div class="vd-street" id="vd-street" aria-label="Street-level imagery"></div>

        {{-- The billing boundary. Nothing street-level exists until this is pressed. --}}
        <div class="vd-launch-panel" id="vd-launch-panel">
            <p class="vd-launch-home" id="vd-launch-home"></p>
            <button type="button" class="vd-launch" id="vd-launch" disabled>Loading listings…</button>
            <p class="vd-launch-note" id="vd-launch-note">{{ $launchNote }}</p>
            {{-- Filled by the shell when Google rejects the key: the exact Maps error
                 and the origin to compare with the key's restrictions. Never the key. --}}
            <div class="vd-auth-failure" id="vd-auth-failure" hidden></div>
            <p class="vd-launch-fallback">Listings stay fully usable without street-level imagery: pick a home and open it normally.</p>
        </div>

        {{-- Screen-fixed sign. Shown only for a provider that cannot pin a marker
             to a coordinate, and captioned so nobody mistakes it for one that can. --}}
        <div class="vd-sign" id="vd-sign" hidden>
            <div class="vd-sign-board">
                <span class="vd-sign-label" id="vd-sign-label"></span>
                <span class="vd-sign-price" id="vd-sign-price"></span>
                <button type="button" class="vd-sign-cta" id="vd-sign-cta">View Home</button>
            </div>
            <p class="vd-sign-caveat" id="vd-sign-caveat"></p>
        </div>

        <div class="vd-hud" id="vd-hud" hidden></div>
        <div class="vd-provider-controls" id="vd-provider-controls"></div>
        <div class="vd-imagery-status" id="vd-imagery-status" role="status" aria-live="polite"></div>

        {{-- The shopper card: what a sign click opens, over the imagery. Pure DOM —
             opening it, switching it or choosing a unit never touches the provider. --}}
        <aside class="vd-shopper" id="vd-shopper" hidden aria-label="Selected listing" aria-live="polite"></aside>
    </main>

    <section class="vd-card" id="vd-card" aria-label="Selected listing">
        <div class="vd-card-nav">
            <button type="button" id="vd-prev">&lsaquo; Previous Home</button>
            <span id="vd-position"></span>
            <button type="button" id="vd-next">Next Home &rsaquo;</button>
        </div>
        <div class="vd-card-body" id="vd-card-body"><p class="vd-muted">Loading listings…</p></div>
        <div class="vd-actions" id="vd-actions"></div>
        <ul class="vd-unavailable-actions" id="vd-unavailable-actions"></ul>
        <div class="vd-nearby">
            <h3>Nearby listings <small id="vd-nearby-note"></small></h3>
            <ol id="vd-nearby"></ol>
        </div>
        <p class="vd-attribution" id="vd-attribution"></p>
        <section class="vd-observations" id="vd-observations" data-mode="sheet" aria-label="Observation sheet"></section>
    </section>

    <aside class="vd-log" aria-label="Instrumentation">
        <details open>
            <summary>Instrumentation</summary>
            <dl id="vd-counters"></dl>
            <ol class="vd-events" id="vd-events"></ol>
        </details>
    </aside>

    <div class="vd-lightbox" id="vd-lightbox" hidden role="dialog" aria-modal="true" aria-label="Listing photos">
        <button type="button" class="vd-lightbox-close" id="vd-lightbox-close" aria-label="Close photos">&times;</button>
        <button type="button" class="vd-lightbox-step" id="vd-lightbox-prev" aria-label="Previous photo">&lsaquo;</button>
        <img id="vd-lightbox-img" alt="">
        <button type="button" class="vd-lightbox-step" id="vd-lightbox-next" aria-label="Next photo">&rsaquo;</button>
        <p class="vd-lightbox-caption" id="vd-lightbox-caption"></p>
    </div>
</div>

{{--
    The Listing Preference stylesheet and delegated behaviour, emitted ONCE and
    BEFORE any control arrives.

    The shopper card's control is rendered server-side, hidden, and MOVED into
    the card by the shell after load — so the delegated behaviour has to be
    listening before any control is shown. This is the only Listing Preference
    line in the Virtual Drive: no state, no endpoints, no reasons.
--}}
<x-listing-preference.assets />

{{--
    One hidden Save | Maybe | Pass control per listing the proof can show.

    The shell reveals the one whose key matches the selected listing and moves
    it into the shopper card. Rendered here, with the page, because a proof
    script must not build listing markup from a server string — the control's
    behaviour is the shared one, already listening thanks to the assets above.

    The KEY is only a selector. What the control carries is the trusted
    `bridge_properties.id`, resolved server-side by VirtualDrivePreferenceControl::pool().
--}}
@foreach(($preferenceControls ?? []) as $lpListingKey => $lpBridgeRowId)
    {{-- Rendered HERE, in the page's own render pass, so the shared
         behaviour's @once holds: one delegated listener for the whole page.
         `surface` selects the Virtual Drive's own write routes. --}}
    <div data-vd-preference-for="{{ $lpListingKey }}" hidden>
        <x-listing-preference.control
            listing-type="bridge"
            :listing-id="$lpBridgeRowId"
            :compact="true"
            :surface="\App\Support\ListingPreferences\ListingPreferenceSurface::VIRTUAL_DRIVE" />
    </div>
@endforeach

<script src="/js/virtual-drive/virtual-drive-signs.js"></script>
<script src="/js/virtual-drive/virtual-drive-shell.js"></script>
<script src="/js/virtual-drive/virtual-drive-observations.js"></script>
{{-- Omitted entirely for a switched-off provider. Not hidden — absent. --}}
@if ($providerScript)
    <script src="/js/virtual-drive/{{ $providerScript }}"></script>
@endif
</body>
</html>
