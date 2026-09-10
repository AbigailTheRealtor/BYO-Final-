{{--
  Location DNA — MapLibre panel (shared).
  ============================================================================
  The ONE place the MapLibre renderer is mounted. Included by both host families:

    * partials/location-dna/map-input.blade.php  — the eight Buyer/Tenant Search
      Areas create/edit surfaces, mode `edit`
    * components/location-dna-map.blade.php      — the read-only detail surface for
      all four roles, mode `display`

  One partial rather than two so the container contract, the data attributes and the
  bundle tag cannot drift between the surface that WRITES geography and the surface
  that reads it. They drifting is how a page comes to render geometry it cannot
  serialise back.

  WHAT THIS DOES NOT DO
  ---------------------
  It does not decide whether MapLibre applies. `LdnaBasemapSurface::enabledFor()` is the
  only gate and the caller asks it; this partial is not reachable otherwise. It also
  never reaches into the spatial-basemap config itself — every value arrives through the
  support class, so a template edit cannot change an archive URL or strip the
  attribution the basemap licence requires. (Written without the literal call syntax on
  purpose: LdnaBasemapSurfaceTest greps for it, and a comment that trips its own
  single-reader assertion would be a comment nobody can keep.)

  Include data
  ------------
    $ldnaMaplibreSurface      string   a LdnaBasemapSurface::* key (for the data attribute
                                       and for debugging a live page; NOT a second gate)
    $ldnaMaplibrePanelId      string   DOM id, unique per panel on the page
    $ldnaMaplibreMode         string   'edit' | 'display'
    $ldnaMaplibreState        array    { polygons, radius_searches, important_places }
    $ldnaMaplibrePropertyPin  ?array   { lat, lng, label } — Seller/Landlord only
    $ldnaMaplibreHeight       string   CSS height for the canvas

  THE BUNDLE TAG IS `@once` AND STATIC, AND BOTH HALVES MATTER
  ------------------------------------------------------------
  `@once` stops a second panel on the same page emitting a second <script>. Static
  (rather than the injected-at-runtime pattern the Google display component uses)
  because the entry point mounts on DOMContentLoaded: a tag appended by script after
  that event has already fired never mounts anything, and the failure looks exactly
  like a dead map. The entry is additionally readyState-aware and exposes
  `window.ldnaMaplibreMount()`, so neither half depends on the other being right.
--}}
@php
    $ldnaMlSurface = $ldnaMaplibreSurface ?? \App\Support\Spatial\LdnaBasemapSurface::DISPLAY;
    $ldnaMlPanelId = $ldnaMaplibrePanelId ?? 'ldna-maplibre-panel';
    $ldnaMlMode    = ($ldnaMaplibreMode ?? 'display') === 'edit' ? 'edit' : 'display';
    $ldnaMlHeight  = $ldnaMaplibreHeight ?? ($ldnaMlMode === 'edit' ? '420px' : '360px');
    $ldnaMlAttrs   = \App\Support\Spatial\LdnaBasemapSurface::containerAttributes();

    /* Normalised to exactly the three keys the renderer's hydrate() reads. Anything else in
       the stored blob (cities, ZIPs, counties, state, notes, flexible_location) is not
       geometry and is not the renderer's business — it stays in the host's own serialiser. */
    $ldnaMlStateRaw = $ldnaMaplibreState ?? [];
    $ldnaMlState = [
        'polygons'         => array_values((array) ($ldnaMlStateRaw['polygons'] ?? [])),
        'radius_searches'  => array_values((array) ($ldnaMlStateRaw['radius_searches'] ?? [])),
        'important_places' => array_values((array) ($ldnaMlStateRaw['important_places'] ?? [])),
    ];

    /* Seller/Landlord carry ONE property pin and no search geometry. Passing an empty
       geometry set alongside it is the correct representation of that, and is why nothing
       here invents a polygon or a circle for them. */
    $ldnaMlPin = $ldnaMaplibrePropertyPin ?? null;
    $ldnaMlPin = (is_array($ldnaMlPin) && isset($ldnaMlPin['lat'], $ldnaMlPin['lng']))
        ? ['lat' => (float) $ldnaMlPin['lat'], 'lng' => (float) $ldnaMlPin['lng'], 'label' => (string) ($ldnaMlPin['label'] ?? '')]
        : null;

    /* Boundary GeoJSON the CALLER already resolved. The renderer never fetches one —
       callers hand it what they have — so this is a pass-through, and a null means the
       page had no boundaries rather than that a lookup failed. */
    $ldnaMlBoundaryGeoJson = $ldnaMaplibreBoundaries ?? null;
    $ldnaMlBoundaryGeoJson = is_array($ldnaMlBoundaryGeoJson) && ! empty($ldnaMlBoundaryGeoJson['features'])
        ? $ldnaMlBoundaryGeoJson
        : null;

    $ldnaMlHasGeometry = count($ldnaMlState['polygons']) > 0
        || count($ldnaMlState['radius_searches']) > 0
        || count($ldnaMlState['important_places']) > 0
        || $ldnaMlPin !== null;

    $ldnaMlCssPath = public_path('js/spatial/ldna-maplibre.css');
@endphp

@once
    @if (file_exists($ldnaMlCssPath))
        <link rel="stylesheet" href="{{ asset('js/spatial/ldna-maplibre.css') }}">
    @endif
    <style>
        .ldna-maplibre-wrap { position: relative; width: 100%; }
        .ldna-maplibre-canvas {
            width: 100%; border-radius: 6px; border: 1px solid #ced4da;
            background: #eceff2; overflow: hidden;
        }
        .ldna-maplibre-status {
            display: flex; align-items: center; gap: .4rem;
            font-size: .8rem; line-height: 1.35; color: #92400e;
            background: #fffbeb; border: 1px solid #fcd34d; border-radius: 4px;
            padding: .45rem .7rem; margin-top: .4rem;
        }
        .ldna-maplibre-status[hidden] { display: none !important; }
        /* The subject property's pin. A DOM marker, not a symbol layer: the style declares
           no glyphs and no sprite on purpose, so no symbol layer can carry a label. */
        .ldna-property-pin {
            width: 18px; height: 18px; border-radius: 50%;
            background: #2563eb; border: 2px solid #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.4);
        }
        .ldna-maplibre-empty {
            position: absolute; inset: 0; display: flex; align-items: center;
            justify-content: center; text-align: center; padding: 1rem;
            color: #64748b; font-size: .9rem; pointer-events: none;
        }
    </style>
    <script defer src="{{ \App\Support\Spatial\LdnaBasemapSurface::bundleUrl() }}"></script>
@endonce

<div class="ldna-maplibre-wrap" wire:ignore>
    <div id="{{ $ldnaMlPanelId }}"
         class="ldna-maplibre-canvas"
         style="height: {{ $ldnaMlHeight }};"
         data-ldna-maplibre
         data-ldna-surface="{{ $ldnaMlSurface }}"
         data-ldna-mode="{{ $ldnaMlMode }}"
         data-ldna-state="{{ json_encode($ldnaMlState) }}"
         @if ($ldnaMlPin) data-ldna-property-pin="{{ json_encode($ldnaMlPin) }}" @endif
         @if ($ldnaMlBoundaryGeoJson) data-ldna-boundaries="{{ json_encode($ldnaMlBoundaryGeoJson) }}" @endif
         @if ($ldnaMlHasGeometry) data-ldna-fit="1" @endif
         @foreach ($ldnaMlAttrs as $ldnaMlKey => $ldnaMlValue)
             data-{{ $ldnaMlKey }}="{{ $ldnaMlValue }}"
         @endforeach
    >
        {{-- State 3 of the eight in the degraded-state contract: no saved geometry yet.
             Replaced by the canvas the moment the renderer paints, and it is inside the
             container rather than absolutely positioned over it, so it cannot survive as
             an overlay on a map that DID load — which is precisely how the Google panel's
             placeholder became a permanent grey box. --}}
        @if (! $ldnaMlHasGeometry && $ldnaMlMode === 'edit')
            <div class="ldna-maplibre-empty">
                <span><i class="fa-solid fa-map me-2"></i>No saved areas yet — add a city or ZIP above, or use the draw tools.</span>
            </div>
        @endif
    </div>

    {{-- States 4, 5 and 7: tiles unavailable, renderer failed, archive unreachable. The
         renderer reports through onStatus and this is where the sentence lands. Scoped to
         this wrapper, not page-global, so two panels cannot write into one another's box. --}}
    <div class="ldna-maplibre-status" data-ldna-map-status hidden role="status"></div>

    <noscript>
        <div class="ldna-maplibre-status">
            &#9888; The map needs JavaScript. Your saved search areas are listed below and are unaffected.
        </div>
    </noscript>
</div>
