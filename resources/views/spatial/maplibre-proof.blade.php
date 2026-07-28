{{--
    MapLibre + PMTiles proof-of-render — Phase 2A, internal only.

    Standalone document by design. It does NOT extend layouts/main.blade.php, so
    app.js (Alpine, Echo, Pusher) and app.css never load here and this page cannot
    perturb — or be perturbed by — any production surface. It reads no listing,
    no model and no database.

    Every value below originates in config/spatial_basemap.php. The archive URL
    appears exactly once, in the data attribute, and is read from there by
    resources/js/spatial/maplibre-proof.js.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>MapLibre PMTiles proof — internal</title>
    {{--
        No stylesheet link. Laravel Mix inlines CSS imported from a JS entry into
        that bundle rather than extracting a sibling .css file, so there is no
        js/spatial/maplibre-proof.css in mix-manifest.json and linking one would
        throw "Unable to locate Mix file". maplibre-gl's stylesheet and this
        page's own styles are both injected by the bundle below.

        The injection happens while the module evaluates — before DOMContentLoaded,
        which is what starts the map — so the map container already has its height
        when MapLibre measures it.
    --}}
</head>
<body>
    <div class="maplibre-proof">
        <h1 class="maplibre-proof__heading">MapLibre + PMTiles proof-of-render</h1>

        <p class="maplibre-proof__note">
            Internal diagnostic. Renders the self-hosted Florida basemap archive through MapLibre
            with the PMTiles protocol &mdash; no Google, no Nominatim, no listing data, no database.
            Until the R2 bucket carries an allowed-origin policy for this origin, the browser will
            block the archive request and the error panel below is the expected result.
        </p>

        @if (empty($pmtilesUrl))
            {{-- Fail visibly rather than booting a map that cannot possibly load. --}}
            <p class="maplibre-proof__unconfigured">
                <strong>No PMTiles archive configured.</strong>
                Set <code>BASEMAP_R2_PUBLIC_URL</code> and <code>BASEMAP_PMTILES_OBJECT_PATH</code>
                (or <code>SPATIAL_PMTILES_URL</code>) in <code>.env</code>. No map is initialised.
            </p>
        @else
            <div class="maplibre-proof__frame">
                <div
                    class="maplibre-proof__map"
                    data-maplibre-proof
                    data-pmtiles-url="{{ $pmtilesUrl }}"
                    data-attribution="{{ $attribution }}"
                    data-longitude="{{ $initialView['longitude'] }}"
                    data-latitude="{{ $initialView['latitude'] }}"
                    data-zoom="{{ $initialView['zoom'] }}"
                    data-max-zoom="{{ $maxZoom }}"
                ></div>

                <div
                    class="maplibre-proof__overlay maplibre-proof__overlay--status"
                    data-maplibre-proof-status
                >
                    Loading the PMTiles archive&hellip;
                </div>

                {{-- Rendered hidden and revealed by JS. Present in the DOM from the
                     start so the error path needs no markup injection. --}}
                <div
                    class="maplibre-proof__overlay maplibre-proof__overlay--error"
                    data-maplibre-proof-error
                    hidden
                >
                    <strong class="maplibre-proof__error-title">Basemap failed to load</strong>
                    <span data-maplibre-proof-error-message></span>
                </div>
            </div>
        @endif
    </div>

    <script src="{{ mix('js/spatial/maplibre-proof.js') }}"></script>
</body>
</html>
