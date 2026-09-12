{{--
    Virtual Drive comparison page — INTERNAL, DEVELOPMENT ONLY.

    THIS PAGE LOADS NEITHER STREET-LEVEL PROVIDER. It includes no provider
    script and emits no credential. It lists the same stored homes and links
    each one to the Apple page and the Google page, where imagery starts only
    when the launch button is pressed.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Virtual Drive comparison</title>
    <link rel="stylesheet" href="{{ asset('css/virtual-drive/virtual-drive.css') }}">
</head>
<body class="vd-body">
<div class="vd-compare" id="vd-compare"
     data-listings-endpoint="{{ route('dev.virtual-drive.api.listings') }}"
     data-apple-url="{{ route('dev.virtual-drive.apple') }}"
     data-google-url="{{ route('dev.virtual-drive.google') }}"
     data-shared-coordinate-listing="{{ $sharedCoordinateKey }}">

    <header class="vd-topbar">
        <div class="vd-topbar-title">
            <span class="vd-internal">Internal proof · not for users</span>
            <strong>Virtual Drive comparison</strong>
        </div>
        <nav class="vd-provider-nav" aria-label="Street-level provider">
            <a href="{{ route('dev.virtual-drive.apple') }}">Apple Look Around</a>
            <a href="{{ route('dev.virtual-drive.google') }}">Google Street View</a>
        </nav>
    </header>

    <main class="vd-compare-main">
        <h1>Same homes, two providers</h1>
        <p class="vd-compare-lede">
            <a class="vd-compare-link" href="{{ route('dev.virtual-drive.google', ['view' => 'customer', 'listing' => $defaultListingKey]) }}">Customer preview — start on Manasota Key Road</a>
            Recommended for judging the experience: imagery there is ~35 m from the home, with a neighbour 33 m away. The preview hides the instrumentation; the counters still run.
        </p>
        <p class="vd-compare-lede">This page loads neither street-level provider. Pick a home and open it on one provider's page. Imagery starts only when you press that page's launch button. On the Google page that button starts one billable Street View session; reloading the page never starts one.</p>

        {{-- Said here because this is where somebody chooses a provider. The page
             still loads neither and still emits no credential of either kind. --}}
        @if (! $googleEnabled)
            <p class="vd-compare-lede vd-compare-refused">{{ $googleRefusal }}</p>
        @endif

        <div class="vd-table-scroll">
            <table class="vd-compare-table">
                <thead>
                    <tr><th>Home</th><th>Sign</th><th>Price</th><th>MLS coordinate</th><th>Test</th></tr>
                </thead>
                <tbody id="vd-compare-rows">
                    <tr><td colspan="5" class="vd-muted">Loading stored listings…</td></tr>
                </tbody>
            </table>
        </div>

        <p class="vd-compare-note">Run the same checklist on both providers for each home. The observation sheet on each provider page keeps your answers in this browser; copy them from either page, or from here.</p>

        <section class="vd-observations" id="vd-observations" data-mode="summary" aria-label="Observation sheet"></section>
    </main>
</div>

<script src="{{ asset('js/virtual-drive/virtual-drive-compare.js') }}"></script>
<script src="{{ asset('js/virtual-drive/virtual-drive-observations.js') }}"></script>
</body>
</html>
