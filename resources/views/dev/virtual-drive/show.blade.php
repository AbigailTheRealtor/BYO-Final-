{{--
    Virtual Drive provider proof — INTERNAL, DEVELOPMENT ONLY.

    STANDALONE ON PURPOSE (no layouts.main). The main layout brings app.js,
    jQuery and several third-party CDNs; this page must contain exactly ONE
    street-level provider and nothing else that draws a map. Google's Terms
    (§3.2.3(e)) forbid Street View imagery and a non-Google map on the same
    screen, so the providers are two pages and neither includes MapLibre.

    NO LISTING DATA IS RENDERED HERE. Everything about a property arrives from
    dev.virtual-drive.api.listings, which reads stored MLS rows through
    Explore's eligibility policy and projection allow-list.

    The provider credential is written into the page only when it is
    configured, and this route only answers when VirtualDriveProofGate allows
    it. With no credential the provider library is never loaded and no request
    reaches Apple or Google.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Virtual Drive proof · {{ $providerLabel }}</title>
    <link rel="stylesheet" href="{{ asset('css/virtual-drive/virtual-drive.css') }}">
</head>
<body class="vd-body">
<div class="vd-shell" id="vd-shell"
     data-provider="{{ $provider }}"
     data-credential="{{ $credential ?? '' }}"
     data-credential-name="{{ $credentialName }}"
     data-library-url="{{ $libraryUrl ?? '' }}"
     data-api-version="{{ $apiVersion ?? '' }}"
     data-listings-endpoint="{{ route('dev.virtual-drive.api.listings') }}"
     data-nearby-radius="{{ $nearbyRadius }}">

    <header class="vd-topbar">
        <div class="vd-topbar-title">
            <span class="vd-internal">Internal proof · not for users</span>
            <strong>{{ $providerLabel }}</strong>
        </div>
        <nav class="vd-provider-nav" aria-label="Street-level provider">
            <a href="{{ route('dev.virtual-drive.apple') }}" class="{{ $provider === 'apple' ? 'is-active' : '' }}">Apple Look Around</a>
            <a href="{{ route('dev.virtual-drive.google') }}" class="{{ $provider === 'google' ? 'is-active' : '' }}">Google Street View</a>
        </nav>
    </header>

    <main class="vd-stage">
        <div class="vd-street" id="vd-street" aria-label="Street-level imagery"></div>

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

<script src="{{ asset('js/virtual-drive/virtual-drive-shell.js') }}"></script>
<script src="{{ asset('js/virtual-drive/' . $providerScript) }}"></script>
</body>
</html>
