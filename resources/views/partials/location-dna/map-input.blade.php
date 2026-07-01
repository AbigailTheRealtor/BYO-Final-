{{--
  Location Preferences Map Input Partial
  ======================================
  Include on any Buyer/Tenant Criteria create or edit form.

  Props:
    $existingLocationDna  – decoded array from the `location_dna_preferences` meta key
    $mapPanelId           – unique DOM id for the map div (default: ldna-map-panel)

  BOUNDARY DATA SOURCES
  ─────────────────────
  • Cities / Counties  →  Nominatim OpenStreetMap API (free, no key; 1 req/sec queue)
                           City lat/lng from Places Autocomplete is cached and used as
                           a viewbox bias so "Seminole, FL" resolves to Pinellas County,
                           not Seminole County near Orlando.
  • ZIP Codes          →  US Census Bureau TIGERweb ZCTA2020 REST API (primary, free)
                           Nominatim postalcode= search (fallback)
                           Inline warning shown if both fail.
  • Neighborhoods      →  Removed from UI. Existing saved data is preserved in JSON
                           for backward-compatible backend matching.

  DRAW TOOLS (no DrawingManager — custom click-based)
  ─────────────────────────────────────────────────────
  Polygon: click map to add vertices → "Finish Polygon" button closes it.
  Circle:  first click = center, second click = radius edge (Haversine, no geometry lib).

  CANONICAL JSON (stored in meta key `location_dna_preferences`):
  {
    "cities":           ["Seminole, FL"],
    "zip_codes":        ["33708"],
    "neighborhoods":    [],            ← preserved for backend matching, no UI
    "counties":         ["Pinellas County, FL"],
    "polygons":         [ { "label": "…", "path": [{lat,lng},…] } ],
    "radius_searches":  [ { "address":"…", "lat":0, "lng":0, "radius_miles":5 } ],
    "flexible_location": false,
    "location_notes":  ""
  }
--}}

@php
  $ldna        = $existingLocationDna ?? [];
  $ldnaCities  = $ldna['cities']           ?? [];
  $ldnaZips    = $ldna['zip_codes']        ?? [];
  $ldnaNeigh   = $ldna['neighborhoods']    ?? [];   /* preserved, no UI */
  $ldnaCounties= $ldna['counties']         ?? [];
  $ldnaPolygons= $ldna['polygons']         ?? [];
  $ldnaRadii   = $ldna['radius_searches']  ?? [];
  $ldnaFlex    = $ldna['flexible_location'] ?? false;
  $ldnaNotes   = $ldna['location_notes']   ?? '';
  $ldnaJson    = count($ldna) ? json_encode($ldna) : '';
  $mapPanelId  = $mapPanelId ?? 'ldna-map-panel';
@endphp

<style>
  .ldna-section { margin-top: 2rem; }
  .ldna-section-header {
    display:flex; align-items:center; gap:.5rem;
    background:#f0f9ff; border:1px solid #bae6fd;
    border-radius:6px; padding:.65rem 1rem; margin-bottom:1rem;
  }
  .ldna-section-header h5 { margin:0; font-size:1rem; color:#0369a1; font-weight:700; }
  .ldna-tag-input-wrap {
    display:flex; flex-wrap:wrap; gap:.35rem; align-items:center;
    border:1px solid #ced4da; border-radius:.375rem; padding:.45rem .6rem;
    background:#fafafb; min-height:48px; cursor:text;
  }
  .ldna-tag {
    display:inline-flex; align-items:center; gap:.25rem;
    background:#0369a1; color:#fff; border-radius:20px;
    padding:.2rem .65rem; font-size:.82rem;
  }
  .ldna-tag-remove { cursor:pointer; font-weight:700; line-height:1; }
  .ldna-tag-input {
    border:none; outline:none; flex:1; min-width:120px;
    font-size:.9rem; background:transparent;
  }
  #{{ $mapPanelId }} { width:100%; height:420px; border-radius:6px; border:1px solid #ced4da; }
  .ldna-map-toolbar { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:.5rem; align-items:center; }
  .ldna-map-toolbar .btn { font-size:.82rem; }
  .ldna-drawing-hud {
    display:none; align-items:center; gap:.5rem;
    background:#eff6ff; border:1px solid #bfdbfe;
    border-radius:4px; padding:.4rem .75rem; margin-bottom:.5rem;
    font-size:.82rem;
  }
  .ldna-radius-form { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-top:.5rem; }
  .ldna-hint { font-size:.78rem; color:#0369a1; margin-top:.25rem; }
  .ldna-zip-warn { display:none; font-size:.78rem; color:#dc2626; margin-top:.2rem; }
  .ldna-overlay-item {
    display:flex; align-items:center; gap:.4rem; margin-bottom:.3rem; font-size:.83rem;
  }
  .ldna-overlay-item .ldna-del {
    background:none; border:none; color:#dc2626; cursor:pointer; padding:0 .2rem;
    font-size:.8rem; line-height:1; flex-shrink:0;
  }
  .ldna-overlay-item .ldna-del:hover { color:#991b1b; }
</style>

<div class="ldna-section" wire:ignore>
  <div class="ldna-section-header">
    <i class="fa-solid fa-map-location-dot" style="color:#0369a1;font-size:1.1rem;"></i>
    <h5>Search Areas
      <span style="font-weight:400;font-size:.85rem;color:#64748b;">
        Choose where the property search should focus — add cities, ZIP codes, counties, custom map areas, or a radius.
      </span>
    </h5>
  </div>

  {{-- ── Tag inputs row 1: cities, ZIP codes ── --}}
  <div class="row g-3 mb-3">

    <div class="col-md-6">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Cities
        <small class="text-muted">(type &amp; select)</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-cities-wrap">
        @foreach($ldnaCities as $c)
          <span class="ldna-tag" data-value="{{ $c }}">{{ $c }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'cities')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-cities-input"
          placeholder="e.g. Seminole, FL" autocomplete="off">
      </div>
      <div class="ldna-hint">
        <i class="fa-solid fa-circle-info"></i>
        Selecting a city draws its boundary on the map.
        County bias is used so "Seminole, FL" maps to Pinellas, not Seminole County.
      </div>
    </div>

    <div class="col-md-6">
      <label class="fw-bold" style="font-size:.88rem;">Preferred ZIP Codes
        <small class="text-muted">(type &amp; Enter)</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-zips-wrap">
        @foreach($ldnaZips as $z)
          <span class="ldna-tag" data-value="{{ $z }}">{{ $z }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'zips')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-zips-input"
          placeholder="e.g. 33708" onkeydown="ldnaAddTag(event,'zips')"
          pattern="\d{5}" maxlength="5">
      </div>
      <div class="ldna-hint">
        <i class="fa-solid fa-circle-info"></i> ZIP boundaries drawn from US Census ZCTA data.
      </div>
      <div class="ldna-zip-warn" id="ldna-zip-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span id="ldna-zip-warning-text"></span>
      </div>
    </div>

  </div>

  {{-- ── Tag inputs row 2: counties ── --}}
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Counties
        <small class="text-muted">(type &amp; Enter — e.g. "Pinellas County, FL")</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-counties-wrap">
        @foreach($ldnaCounties as $co)
          <span class="ldna-tag" data-value="{{ $co }}">{{ $co }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'counties')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-counties-input"
          placeholder="Pinellas County, FL" onkeydown="ldnaAddTag(event,'counties')">
      </div>
    </div>
  </div>

  {{-- ── Map toolbar ── --}}
  <label class="fw-bold" style="font-size:.88rem;">Draw Custom Areas on Map
    <small class="text-muted">(polygon = custom shape, circle = two-click radius)</small>
  </label>
  <div class="ldna-map-toolbar">
    <button type="button" id="ldna-draw-btn-polygon"
      class="btn btn-outline-primary btn-sm ldna-draw-btn"
      onclick="ldnaSetDrawMode('polygon')">
      <i class="fa-solid fa-draw-polygon"></i> Draw Polygon
    </button>
    <button type="button" id="ldna-draw-btn-circle"
      class="btn btn-outline-secondary btn-sm ldna-draw-btn"
      onclick="ldnaSetDrawMode('circle')">
      <i class="fa-solid fa-circle-dot"></i> Draw Circle
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm"
      onclick="ldnaClearAllOverlays()">
      <i class="fa-solid fa-trash"></i> Clear Drawings
    </button>
  </div>

  {{-- ── Drawing HUD (shown while drawing mode is active) ── --}}
  <div id="ldna-drawing-hud" class="ldna-drawing-hud">
    <i class="fa-solid fa-pen-to-square text-primary"></i>
    <span id="ldna-hud-status" style="flex:1;color:#1e3a5f;"></span>
    <button type="button" id="ldna-finish-btn"
      class="btn btn-success btn-sm" style="display:none;"
      onclick="ldnaFinishDrawing()">
      <i class="fa-solid fa-check"></i> Finish Polygon
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm"
      onclick="ldnaCancelDrawing()">
      Cancel
    </button>
  </div>

  {{-- ── Map panel ── --}}
  <div style="position:relative;">
    <div id="{{ $mapPanelId }}" wire:ignore></div>
    <div id="{{ $mapPanelId }}-placeholder"
      style="position:absolute;top:0;left:0;right:0;bottom:0;
        display:flex;align-items:center;justify-content:center;background:#f8fafc;
        border-radius:6px;border:1px solid #ced4da;color:#64748b;font-size:.9rem;
        text-align:center;padding:1rem;pointer-events:none;z-index:1;">
      @if(count($ldnaPolygons) || count($ldnaRadii) || count($ldnaCities) || count($ldnaZips) || count($ldnaCounties))
        <span><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading map&hellip;</span>
      @else
        <span><i class="fa-solid fa-map me-2"></i>Add a city or ZIP above, or use the draw tools to mark preferred areas.</span>
      @endif
    </div>
  </div>

  {{-- ── Overlay list (JS populates with delete buttons on map init) ── --}}
  <ul class="list-unstyled mt-1" id="ldna-overlay-list"></ul>

  {{-- ── Radius Search form (B1.6) ── --}}
  <label class="fw-bold mt-2" style="font-size:.88rem;">Radius Search
    <small class="text-muted">(add one or more radius circles)</small>
  </label>
  <div class="ldna-radius-form">
    <div class="d-flex flex-column">
      <label for="ldna-radius-address" class="text-muted mb-1" style="font-size:.78rem;">Radius Search Address</label>
      <input type="text" id="ldna-radius-address" class="form-control form-control-sm"
        placeholder="Enter an address or place for radius search" style="max-width:280px;" autocomplete="off">
    </div>
    <div class="d-flex flex-column">
      <label for="ldna-radius-miles" class="text-muted mb-1" style="font-size:.78rem;">Radius Miles</label>
      <input type="number" id="ldna-radius-miles" class="form-control form-control-sm"
        placeholder="Miles" min="0.1" step="0.1" value="5" style="max-width:90px;">
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm"
      onclick="ldnaAddRadiusSearch()">
      <i class="fa-solid fa-location-crosshairs"></i> Add Radius Search
    </button>
  </div>

  {{-- ── Flexible + notes ── --}}
  <div class="row g-3 mt-1">
    <div class="col-auto d-flex align-items-center gap-2">
      <input type="checkbox" id="ldna-flexible" class="form-check-input"
        {{ $ldnaFlex ? 'checked' : '' }} onchange="ldnaSerialize()">
      <label for="ldna-flexible" class="fw-bold mb-0" style="font-size:.88rem;">
        Flexible location — open to nearby areas
      </label>
    </div>
  </div>
  <div class="row g-3 mt-1">
    <div class="col-12">
      <label class="fw-bold" style="font-size:.88rem;">Location Notes
        <small class="text-muted">(optional)</small>
      </label>
      <textarea id="ldna-location-notes" class="form-control" rows="2"
        placeholder="E.g. Must be within 10 min of I-4. Prefer A-rated school district."
        oninput="ldnaSerialize()">{{ $ldnaNotes }}</textarea>
    </div>
  </div>

  <textarea name="location_dna_preferences" id="ldna-json-field"
    style="display:none;">{{ $ldnaJson }}</textarea>
</div>

<script>
(function () {
  /* ── Re-execution guard ──────────────────────────────────────────────────── */
  var _panelKey = 'ldnaInit_{{ $mapPanelId }}';
  if (window[_panelKey]) return;
  window[_panelKey] = true;

  /* ── State ───────────────────────────────────────────────────────────────── */
  var ldnaState = {
    cities:            @json($ldnaCities),
    zip_codes:         @json($ldnaZips),
    neighborhoods:     @json($ldnaNeigh),   /* preserved for backend matching, no UI */
    counties:          @json($ldnaCounties),
    polygons:          @json($ldnaPolygons),
    radius_searches:   @json($ldnaRadii),
    flexible_location: {{ $ldnaFlex ? 'true' : 'false' }},
    location_notes:    @json($ldnaNotes),
  };

  /* ── Map objects ─────────────────────────────────────────────────────────── */
  var ldnaMap            = null;
  var ldnaMapInitialized = false;
  /* ldnaOverlays: [{type, overlay, label, data?}] — null = deleted */
  var ldnaOverlays       = [];
  var ldnaObservers      = [];

  /* Boundary overlays: key → [google.maps.Data.Feature] */
  var ldnaBoundaryOverlays = {};

  /* Nominatim queue (cities + counties, 1 req/sec) */
  var ldnaBoundaryQueue   = [];
  var ldnaBoundaryRunning = false;

  /* City lat/lng cache — populated by Places Autocomplete, used to bias Nominatim */
  var cityLatLngCache = {};

  /* ── Custom draw state (no DrawingManager) ───────────────────────────────── */
  var ldnaDrawingMode      = null;   /* null | 'polygon' | 'circle' */
  var ldnaPolyVertices     = [];     /* [{lat,lng}] */
  var ldnaPolyMarkers      = [];     /* google.maps.Marker[] */
  var ldnaPolyPreview      = null;   /* google.maps.Polyline */
  var ldnaCircleCenter     = null;   /* {lat,lng} */
  var ldnaCircleMarker     = null;   /* google.maps.Marker */
  var ldnaCirclePreview    = null;   /* preview google.maps.Circle */
  var ldnaDrawClickListener = null;
  var ldnaDrawMouseListener = null;

  /* ═══════════════════════════════════════════════════════════════════════════
     SERIALIZATION
  ═══════════════════════════════════════════════════════════════════════════ */
  window.ldnaSerialize = function () {
    var flexEl  = document.getElementById('ldna-flexible');
    var notesEl = document.getElementById('ldna-location-notes');
    if (flexEl)  ldnaState.flexible_location = flexEl.checked;
    if (notesEl) ldnaState.location_notes    = notesEl.value.trim();

    ldnaState.polygons        = [];
    ldnaState.radius_searches = [];

    ldnaOverlays.forEach(function (item) {
      if (!item) return; /* deleted */
      if (item.type === 'polygon') {
        var path = [];
        item.overlay.getPath().forEach(function (ll) {
          path.push({ lat: ll.lat(), lng: ll.lng() });
        });
        ldnaState.polygons.push({ label: item.label, path: path });
      } else if (item.type === 'circle' || item.type === 'radius_search') {
        var c  = item.overlay.getCenter();
        var rm = parseFloat((item.overlay.getRadius() / 1609.34).toFixed(2));
        var entry = { lat: c.lat(), lng: c.lng(), radius_miles: rm };
        if (item.data && item.data.address) { entry.address = item.data.address; }
        else                                { entry.label   = item.label; }
        ldnaState.radius_searches.push(entry);
      }
    });

    var field = document.getElementById('ldna-json-field');
    if (field) field.value = JSON.stringify(ldnaState);
  };

  /* ═══════════════════════════════════════════════════════════════════════════
     TAG HELPERS
  ═══════════════════════════════════════════════════════════════════════════ */
  function ldnaGroupKey(group) {
    return { cities:'cities', zips:'zip_codes', counties:'counties' }[group] || null;
  }

  function ldnaAddTagValue(group, val) {
    if (!val) return;
    var stateKey = ldnaGroupKey(group);
    if (!stateKey) return;
    if (ldnaState[stateKey].indexOf(val) !== -1) return; /* duplicate */
    ldnaState[stateKey].push(val);

    var wrap  = document.getElementById('ldna-' + group + '-wrap');
    var input = document.getElementById('ldna-' + group + '-input');
    if (!wrap) return;

    var tag = document.createElement('span');
    tag.className     = 'ldna-tag';
    tag.dataset.value = val;
    var labelNode = document.createTextNode(val + ' ');
    var removeBtn = document.createElement('span');
    removeBtn.className   = 'ldna-tag-remove';
    removeBtn.textContent = '×';
    removeBtn.setAttribute('onclick',
      "ldnaRemoveTag(this,'" + group.replace(/'/g, "\\'") + "')");
    tag.appendChild(labelNode);
    tag.appendChild(removeBtn);
    if (input) { wrap.insertBefore(tag, input); } else { wrap.appendChild(tag); }

    ldnaSerialize();

    /* Fetch boundary */
    if (group === 'cities') {
      var cached = cityLatLngCache[val] || null;
      ldnaEnqueueBoundary('city__' + val,
        ldnaCityBoundaryUrl(val, cached ? cached.lat : null, cached ? cached.lng : null));
    }
    if (group === 'zips')     { ldnaFetchZipBoundary(val, 'zip__' + val); }
    if (group === 'counties') { ldnaEnqueueBoundary('county__' + val, ldnaCountyBoundaryUrl(val)); }
  }

  window.ldnaAddTag = function (event, group) {
    if (event.key !== 'Enter' && event.key !== ',') return;
    event.preventDefault();
    var input = document.getElementById('ldna-' + group + '-input');
    if (!input) return;
    var val = input.value.trim();
    if (!val) return;
    input.value = '';
    ldnaAddTagValue(group, val);
  };

  window.ldnaRemoveTag = function (btn, group) {
    var tag = btn.parentElement;
    var val = tag.dataset.value;
    var stateKey = ldnaGroupKey(group);
    if (!stateKey) return;
    ldnaState[stateKey] = ldnaState[stateKey].filter(function (v) { return v !== val; });
    tag.remove();

    var bKey = group === 'cities'   ? ('city__'   + val)
             : group === 'zips'    ? ('zip__'    + val)
             : group === 'counties'? ('county__' + val) : null;
    if (bKey) ldnaClearBoundaryOverlay(bKey);

    ldnaSerialize();
  };

  /* ═══════════════════════════════════════════════════════════════════════════
     PLACES AUTOCOMPLETE — CITIES (locality only, stores lat/lng for bias)
  ═══════════════════════════════════════════════════════════════════════════ */
  function ldnaInitCitiesAutocomplete() {
    var input = document.getElementById('ldna-cities-input');
    if (!input || input._ldnaACAttached) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    input._ldnaACAttached = true;

    /* 'locality' excludes counties/administrative areas — fixes "Seminole, FL" bias */
    var ac = new google.maps.places.Autocomplete(input, {
      types: ['locality'],
      componentRestrictions: { country: 'us' },
    });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

    ac.addListener('place_changed', function () {
      var place = ac.getPlace();
      if (!place || !place.geometry) return;

      /* Build "City, ST" label from address_components */
      var cityName = '', stateName = '';
      (place.address_components || []).forEach(function (comp) {
        if (!cityName  && comp.types.indexOf('locality') !== -1)
          cityName = comp.long_name;
        if (!stateName && comp.types.indexOf('administrative_area_level_1') !== -1)
          stateName = comp.short_name;
      });
      if (!cityName) cityName = place.name;
      var label = stateName ? (cityName + ', ' + stateName) : cityName;

      /* Cache lat/lng so Nominatim gets a viewbox bias for this city */
      var loc = place.geometry.location;
      cityLatLngCache[label] = { lat: loc.lat(), lng: loc.lng() };

      ldnaAddTagValue('cities', label);
      input.value = '';

      /* Pan map to the selected city */
      if (ldnaMap && place.geometry) {
        if (place.geometry.viewport) { ldnaMap.fitBounds(place.geometry.viewport); }
        else { ldnaMap.setCenter(loc); ldnaMap.setZoom(11); }
      }
    });
  }

  /* ── Places Autocomplete for radius address ──────────────────────────────── */
  function ldnaInitRadiusAutocomplete() {
    var input = document.getElementById('ldna-radius-address');
    if (!input || input._ldnaACAttached) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    input._ldnaACAttached = true;
    new google.maps.places.Autocomplete(input, { componentRestrictions: { country: 'us' } });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     BOUNDARY URL BUILDERS
  ═══════════════════════════════════════════════════════════════════════════ */

  /* City: Nominatim with optional viewbox bias (lat/lng from Places Autocomplete) */
  function ldnaCityBoundaryUrl(cityLabel, lat, lng) {
    var q = encodeURIComponent(cityLabel + ', USA');
    var url = 'https://nominatim.openstreetmap.org/search?q=' + q +
              '&format=geojson&polygon_geojson=1&featuretype=city&countrycodes=us&limit=3';
    if (lat !== null && lat !== undefined && lng !== null && lng !== undefined) {
      /* ±0.3° viewbox around the known lat/lng biases Nominatim to the right municipality */
      var m = 0.3;
      url += '&viewbox=' + (lng - m) + ',' + (lat + m) + ',' + (lng + m) + ',' + (lat - m);
      url += '&bounded=0'; /* prefer viewbox but don't exclude outside */
    }
    return url;
  }

  /* County: Nominatim (no geometry restriction — county boundaries auto-found) */
  function ldnaCountyBoundaryUrl(countyLabel) {
    var q = encodeURIComponent(countyLabel + ', USA');
    return 'https://nominatim.openstreetmap.org/search?q=' + q +
           '&format=geojson&polygon_geojson=1&countrycodes=us&limit=1';
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     ZIP BOUNDARY — Census TIGER primary, Nominatim fallback, inline warning
  ═══════════════════════════════════════════════════════════════════════════ */
  function ldnaFetchZipBoundary(zip, key) {
    if (!zip) return;
    /* Primary: US Census Bureau TIGERweb ZCTA2020 — no rate limit, complete US coverage */
    var where = "GEOID20='" + zip + "'";
    var tigerUrl =
      'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/tigerWMS_ZCTA2020' +
      '/MapServer/0/query' +
      '?f=geojson' +
      '&where=' + encodeURIComponent(where) +
      '&outFields=GEOID20' +
      '&returnGeometry=true' +
      '&geometryPrecision=3' +
      '&outSR=4326';

    fetch(tigerUrl, { headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        var feats = data && data.features;
        if (feats && feats.length > 0) {
          ldnaRenderBoundaryFeature(key, feats[0]);
        } else {
          /* No data from TIGER → try Nominatim postalcode */
          ldnaFetchZipViaNominatim(zip, key);
        }
      })
      .catch(function () {
        /* Network or CORS error → try Nominatim postalcode */
        ldnaFetchZipViaNominatim(zip, key);
      });
  }

  function ldnaFetchZipViaNominatim(zip, key) {
    var url = 'https://nominatim.openstreetmap.org/search' +
              '?postalcode=' + encodeURIComponent(zip) +
              '&country=US&format=geojson&polygon_geojson=1&limit=1';
    fetch(url, { headers: { 'Accept': 'application/json', 'Accept-Language': 'en-US,en' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var feats = data && data.features;
        if (feats && feats.length > 0) {
          ldnaRenderBoundaryFeature(key, feats[0]);
        } else {
          ldnaShowZipWarning(zip, 'Boundary data not found for ZIP ' + zip + '. The ZIP will still be used for matching.');
        }
      })
      .catch(function () {
        ldnaShowZipWarning(zip, 'Could not load boundary for ZIP ' + zip + '. Check your connection and try again.');
      });
  }

  function ldnaShowZipWarning(zip, msg) {
    var el  = document.getElementById('ldna-zip-warning');
    var txt = document.getElementById('ldna-zip-warning-text');
    if (el && txt) {
      txt.textContent = msg;
      el.style.display = 'block';
      setTimeout(function () { el.style.display = 'none'; }, 10000);
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     NOMINATIM QUEUE (cities + counties — 1 req/sec rate limit)
  ═══════════════════════════════════════════════════════════════════════════ */
  function ldnaEnqueueBoundary(key, url) {
    ldnaBoundaryQueue = ldnaBoundaryQueue.filter(function (t) { return t.key !== key; });
    ldnaBoundaryQueue.push({ key: key, url: url });
    ldnaBoundaryProcess();
  }

  function ldnaBoundaryProcess() {
    if (ldnaBoundaryRunning || !ldnaBoundaryQueue.length || !ldnaMap) return;
    var task = ldnaBoundaryQueue.shift();
    ldnaBoundaryRunning = true;

    fetch(task.url, {
      headers: { 'Accept': 'application/json', 'Accept-Language': 'en-US,en' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!ldnaMap) return;
        var feats = data && data.features;
        if (feats && feats.length > 0) {
          /* If viewbox was used, Nominatim may return several results — pick the one
             whose name most closely matches (first result after geometry preference) */
          var picked = feats[0];
          ldnaRenderBoundaryFeature(task.key, picked);
        }
      })
      .catch(function (e) { /* silently skip */ })
      .finally(function () {
        ldnaBoundaryRunning = false;
        setTimeout(ldnaBoundaryProcess, 1100); /* respect 1 req/sec */
      });
  }

  /* ── Render a GeoJSON Feature as a styled boundary overlay ─────────────── */
  function ldnaRenderBoundaryFeature(key, feature) {
    if (!ldnaMap) return;
    ldnaClearBoundaryOverlay(key);

    var added = ldnaMap.data.addGeoJson({
      type: 'FeatureCollection',
      features: [Object.assign({}, feature, { properties: { _ldnaKey: key } })]
    });
    ldnaBoundaryOverlays[key] = added;

    /* Fit map to show this boundary */
    var bounds  = new google.maps.LatLngBounds();
    var geo     = feature.geometry;
    var rings   = [];
    if (geo.type === 'Polygon')      { rings = [geo.coordinates[0]]; }
    else if (geo.type === 'MultiPolygon') {
      geo.coordinates.forEach(function (poly) { rings.push(poly[0]); });
    } else if (geo.type === 'Point') {
      bounds.extend({ lat: geo.coordinates[1], lng: geo.coordinates[0] });
    }
    rings.forEach(function (ring) {
      ring.forEach(function (c) { bounds.extend({ lat: c[1], lng: c[0] }); });
    });

    /* If multiple boundaries visible, zoom to show all */
    var allKeys = Object.keys(ldnaBoundaryOverlays);
    if (allKeys.length > 1) {
      var allBounds = new google.maps.LatLngBounds();
      allKeys.forEach(function (k) {
        var fs = ldnaBoundaryOverlays[k];
        if (fs) fs.forEach(function (f) {
          var g = f.getGeometry();
          if (g) g.forEachLatLng(function (ll) { allBounds.extend(ll); });
        });
      });
      if (!allBounds.isEmpty()) { ldnaMap.fitBounds(allBounds); return; }
    }
    if (!bounds.isEmpty()) ldnaMap.fitBounds(bounds);
  }

  function ldnaClearBoundaryOverlay(key) {
    if (!ldnaBoundaryOverlays[key] || !ldnaMap) return;
    ldnaBoundaryOverlays[key].forEach(function (f) {
      try { ldnaMap.data.remove(f); } catch (e) {}
    });
    delete ldnaBoundaryOverlays[key];
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     OVERLAY LIST — items with delete buttons (JS-only, no Blade foreach)
  ═══════════════════════════════════════════════════════════════════════════ */
  function ldnaAddOverlayListItem(idx, label, iconClass) {
    var ol = document.getElementById('ldna-overlay-list');
    if (!ol) return;
    var li = document.createElement('li');
    li.id        = 'ldna-item-' + idx;
    li.className = 'ldna-overlay-item';
    li.innerHTML =
      '<i class="fa-solid ' + iconClass + '"></i>' +
      '<span class="flex-fill">' + label + '</span>' +
      '<button type="button" class="ldna-del" title="Remove" ' +
      'onclick="ldnaRemoveOverlay(' + idx + ')">' +
      '<i class="fa-solid fa-times-circle"></i></button>';
    ol.appendChild(li);
  }

  window.ldnaRemoveOverlay = function (idx) {
    var item = ldnaOverlays[idx];
    if (!item) return;
    try { item.overlay.setMap(null); } catch (e) {}
    ldnaOverlays[idx] = null; /* null-out, preserve indices */
    var li = document.getElementById('ldna-item-' + idx);
    if (li) li.remove();
    ldnaSerialize();
  };

  window.ldnaClearAllOverlays = function () {
    ldnaOverlays.forEach(function (item) {
      if (!item) return;
      try { item.overlay.setMap(null); } catch (e) {}
    });
    ldnaOverlays = [];
    var ol = document.getElementById('ldna-overlay-list');
    if (ol) ol.innerHTML = '';
    ldnaStopDrawing();        /* cancel any in-progress drawing */
    ldnaUpdateDrawButtons(null);
    ldnaSerialize();
  };

  /* ═══════════════════════════════════════════════════════════════════════════
     CUSTOM DRAWING — no DrawingManager (no race condition)
  ═══════════════════════════════════════════════════════════════════════════ */

  /* Haversine distance in meters (no geometry library needed) */
  function ldnaDistanceMeters(p1, p2) {
    var R = 6371000;
    var dLat = (p2.lat - p1.lat) * Math.PI / 180;
    var dLng = (p2.lng - p1.lng) * Math.PI / 180;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(p1.lat * Math.PI / 180) * Math.cos(p2.lat * Math.PI / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function ldnaShowDrawingHUD(status, showFinish) {
    var hud       = document.getElementById('ldna-drawing-hud');
    var hudStatus = document.getElementById('ldna-hud-status');
    var finishBtn = document.getElementById('ldna-finish-btn');
    if (hud)       hud.style.display       = 'flex';
    if (hudStatus) hudStatus.textContent   = status || '';
    if (finishBtn) finishBtn.style.display = showFinish ? '' : 'none';
  }

  function ldnaHideDrawingHUD() {
    var hud = document.getElementById('ldna-drawing-hud');
    if (hud) hud.style.display = 'none';
  }

  function ldnaUpdateDrawButtons(activeMode) {
    document.querySelectorAll('.ldna-draw-btn').forEach(function (btn) {
      btn.classList.remove('btn-primary', 'btn-secondary', 'active');
      if (btn.id === 'ldna-draw-btn-polygon') {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-outline-primary');
      }
      if (btn.id === 'ldna-draw-btn-circle') {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-outline-secondary');
      }
    });
    if (activeMode) {
      var activeBtn = document.getElementById('ldna-draw-btn-' + activeMode);
      if (activeBtn) {
        if (activeMode === 'polygon') {
          activeBtn.classList.remove('btn-outline-primary');
          activeBtn.classList.add('btn-primary', 'active');
        } else {
          activeBtn.classList.remove('btn-outline-secondary');
          activeBtn.classList.add('btn-secondary', 'active');
        }
      }
    }
  }

  function ldnaUpdatePolyPreview() {
    if (ldnaPolyPreview) { ldnaPolyPreview.setMap(null); ldnaPolyPreview = null; }
    if (ldnaPolyVertices.length < 2) return;
    /* Connect all vertices + close back to start for preview */
    var path = ldnaPolyVertices.concat(
      ldnaPolyVertices.length >= 3 ? [ldnaPolyVertices[0]] : []
    );
    ldnaPolyPreview = new google.maps.Polyline({
      path: path,
      strokeColor: '#0369a1', strokeWeight: 2, strokeOpacity: 0.5,
      map: ldnaMap,
    });
  }

  function ldnaStartDrawPolygon() {
    ldnaStopDrawing();
    ldnaDrawingMode  = 'polygon';
    ldnaPolyVertices = [];
    ldnaPolyMarkers  = [];
    ldnaPolyPreview  = null;
    ldnaUpdateDrawButtons('polygon');
    ldnaShowDrawingHUD(
      'Click on the map to add vertices. Need at least 3. Then click [Finish Polygon].',
      true
    );

    ldnaDrawClickListener = ldnaMap.addListener('click', function (e) {
      if (ldnaDrawingMode !== 'polygon') return;
      var lat = e.latLng.lat();
      var lng = e.latLng.lng();
      ldnaPolyVertices.push({ lat: lat, lng: lng });

      var marker = new google.maps.Marker({
        position: { lat: lat, lng: lng },
        map: ldnaMap,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 6, fillColor: '#0369a1', fillOpacity: 1,
          strokeColor: '#ffffff', strokeWeight: 2,
        },
        title: 'Vertex ' + ldnaPolyVertices.length,
        zIndex: 10,
      });
      ldnaPolyMarkers.push(marker);
      ldnaUpdatePolyPreview();

      var hudStatus = document.getElementById('ldna-hud-status');
      if (hudStatus) {
        var n = ldnaPolyVertices.length;
        hudStatus.textContent = n + ' vert' + (n === 1 ? 'ex' : 'ices') + '. ' +
          (n >= 3 ? 'Click [Finish Polygon] or continue adding.' : 'Need at least 3.');
      }
    });
  }

  function ldnaStartDrawCircle() {
    ldnaStopDrawing();
    ldnaDrawingMode   = 'circle';
    ldnaCircleCenter  = null;
    ldnaCircleMarker  = null;
    ldnaCirclePreview = null;
    ldnaUpdateDrawButtons('circle');
    ldnaShowDrawingHUD('Click on the map to set the circle center.', false);

    ldnaDrawClickListener = ldnaMap.addListener('click', function (e) {
      if (ldnaDrawingMode !== 'circle') return;

      if (!ldnaCircleCenter) {
        /* First click — set center */
        ldnaCircleCenter = { lat: e.latLng.lat(), lng: e.latLng.lng() };
        ldnaCircleMarker = new google.maps.Marker({
          position: ldnaCircleCenter,
          map: ldnaMap,
          icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 8, fillColor: '#6b7280', fillOpacity: 1,
            strokeColor: '#fff', strokeWeight: 2,
          },
          title: 'Circle center',
          zIndex: 10,
        });
        var hudStatus = document.getElementById('ldna-hud-status');
        if (hudStatus) hudStatus.textContent = 'Center set. Now click on the map to set the radius edge.';
      } else {
        /* Second click — compute radius and create circle */
        var radiusM = ldnaDistanceMeters(
          ldnaCircleCenter,
          { lat: e.latLng.lat(), lng: e.latLng.lng() }
        );
        if (radiusM < 50) radiusM = 50; /* minimum 50m */
        var center = ldnaCircleCenter; /* capture before stop() clears it */

        ldnaStopDrawing();

        var mi = parseFloat((radiusM / 1609.34).toFixed(1));
        var gmCircle = new google.maps.Circle({
          center: center, radius: radiusM,
          fillColor: '#6b7280', fillOpacity: 0.12,
          strokeColor: '#6b7280', strokeWeight: 2, editable: true,
          map: ldnaMap,
        });
        var idx   = ldnaOverlays.length;
        var label = 'Circle ' + (ldnaOverlays.filter(Boolean).length + 1) + ' (' + mi + ' mi)';
        ldnaOverlays.push({ type: 'circle', overlay: gmCircle, label: label });
        google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
        google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);
        ldnaAddOverlayListItem(idx, label, 'fa-circle-dot text-secondary');
        ldnaUpdateDrawButtons(null);
        ldnaSerialize();
      }
    });

    /* Mouse-move preview after center is set */
    ldnaDrawMouseListener = ldnaMap.addListener('mousemove', function (e) {
      if (ldnaDrawingMode !== 'circle' || !ldnaCircleCenter) return;
      if (ldnaCirclePreview) { ldnaCirclePreview.setMap(null); ldnaCirclePreview = null; }
      var r = ldnaDistanceMeters(
        ldnaCircleCenter,
        { lat: e.latLng.lat(), lng: e.latLng.lng() }
      );
      if (r < 50) return;
      ldnaCirclePreview = new google.maps.Circle({
        center: ldnaCircleCenter, radius: r,
        fillColor: '#6b7280', fillOpacity: 0.06,
        strokeColor: '#6b7280', strokeWeight: 1, strokeOpacity: 0.35,
        clickable: false, map: ldnaMap,
      });
    });
  }

  /* Cancel any in-progress drawing and clean up temp objects */
  function ldnaStopDrawing() {
    ldnaDrawingMode = null;
    /* Polygon temp */
    ldnaPolyMarkers.forEach(function (m) { try { m.setMap(null); } catch (e) {} });
    ldnaPolyMarkers  = [];
    ldnaPolyVertices = [];
    if (ldnaPolyPreview) { try { ldnaPolyPreview.setMap(null); } catch (e) {} }
    ldnaPolyPreview = null;
    /* Circle temp */
    if (ldnaCircleMarker)  { try { ldnaCircleMarker.setMap(null);  } catch (e) {} }
    if (ldnaCirclePreview) { try { ldnaCirclePreview.setMap(null); } catch (e) {} }
    ldnaCircleCenter  = null;
    ldnaCircleMarker  = null;
    ldnaCirclePreview = null;
    /* Map listeners */
    if (ldnaDrawClickListener) {
      google.maps.event.removeListener(ldnaDrawClickListener);
      ldnaDrawClickListener = null;
    }
    if (ldnaDrawMouseListener) {
      google.maps.event.removeListener(ldnaDrawMouseListener);
      ldnaDrawMouseListener = null;
    }
    ldnaHideDrawingHUD();
  }

  /* Public: called by toolbar buttons */
  window.ldnaSetDrawMode = function (mode) {
    if (!ldnaMap) {
      ldnaRequestInit();
      setTimeout(function () { window.ldnaSetDrawMode(mode); }, 600);
      return;
    }
    if (mode === 'polygon') { ldnaStartDrawPolygon(); }
    else if (mode === 'circle') { ldnaStartDrawCircle(); }
    else { ldnaStopDrawing(); ldnaUpdateDrawButtons(null); }
  };

  /* Public: called by "Finish Polygon" button in HUD */
  window.ldnaFinishDrawing = function () {
    if (ldnaDrawingMode !== 'polygon') return;
    if (ldnaPolyVertices.length < 3) {
      var hudStatus = document.getElementById('ldna-hud-status');
      if (hudStatus) hudStatus.textContent = 'Need at least 3 vertices to close the polygon.';
      return;
    }
    var vertices = ldnaPolyVertices.slice(); /* copy before stop() clears it */
    ldnaStopDrawing();

    var gmPoly = new google.maps.Polygon({
      paths: vertices,
      fillColor: '#0369a1', fillOpacity: 0.15,
      strokeColor: '#0369a1', strokeWeight: 2, editable: true,
      map: ldnaMap,
    });
    var idx   = ldnaOverlays.length;
    var label = 'Polygon ' + (ldnaOverlays.filter(Boolean).length + 1);
    ldnaOverlays.push({ type: 'polygon', overlay: gmPoly, label: label });
    google.maps.event.addListener(gmPoly.getPath(), 'set_at',    ldnaSerialize);
    google.maps.event.addListener(gmPoly.getPath(), 'insert_at', ldnaSerialize);
    ldnaAddOverlayListItem(idx, label, 'fa-draw-polygon text-primary');
    ldnaUpdateDrawButtons(null);
    ldnaSerialize();
  };

  /* Public: called by "Cancel" button in HUD */
  window.ldnaCancelDrawing = function () {
    ldnaStopDrawing();
    ldnaUpdateDrawButtons(null);
  };

  /* ═══════════════════════════════════════════════════════════════════════════
     RADIUS SEARCH (address-based, geocoded)
  ═══════════════════════════════════════════════════════════════════════════ */
  window.ldnaAddRadiusSearch = function () {
    var addrEl  = document.getElementById('ldna-radius-address');
    var milesEl = document.getElementById('ldna-radius-miles');
    if (!addrEl || !milesEl) return;
    var address = addrEl.value.trim();
    var miles   = parseFloat(milesEl.value) || 5;
    if (!address) return;

    if (!ldnaMap) { ldnaRequestInit(); setTimeout(window.ldnaAddRadiusSearch, 600); return; }

    var geocoder = new google.maps.Geocoder();
    geocoder.geocode(
      { address: address, componentRestrictions: { country: 'us' } },
      function (results, status) {
        if (status !== 'OK' || !results.length) {
          alert('Could not find location: ' + address); return;
        }
        var loc = results[0].geometry.location;
        var lat = loc.lat(), lng = loc.lng();
        var label   = address + ' (' + miles + ' mi)';
        var gmCircle = new google.maps.Circle({
          center: { lat: lat, lng: lng },
          radius: miles * 1609.34,
          fillColor: '#6b7280', fillOpacity: 0.12,
          strokeColor: '#6b7280', strokeWeight: 2, editable: true,
          map: ldnaMap,
        });
        var idx = ldnaOverlays.length;
        ldnaOverlays.push({
          type: 'radius_search', overlay: gmCircle, label: label,
          data: { address: address, lat: lat, lng: lng, radius_miles: miles },
        });
        google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
        google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);
        ldnaAddOverlayListItem(idx, label, 'fa-circle-dot text-secondary');
        ldnaMap.panTo({ lat: lat, lng: lng });
        ldnaMap.fitBounds(gmCircle.getBounds());
        ldnaSerialize();
        addrEl.value = '';
      }
    );
  };

  /* ═══════════════════════════════════════════════════════════════════════════
     MAP INITIALISATION
  ═══════════════════════════════════════════════════════════════════════════ */
  function ldnaIsContainerVisible() {
    var el = document.getElementById('{{ $mapPanelId }}');
    if (!el) return false;
    var rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function ldnaInitMap() {
    if (ldnaMapInitialized) return;
    ldnaMapInitialized = true;

    ldnaObservers.forEach(function (o) { try { o.disconnect(); } catch (e) {} });
    ldnaObservers = [];

    var ph = document.getElementById('{{ $mapPanelId }}-placeholder');
    if (ph) ph.style.display = 'none';

    ldnaMap = new google.maps.Map(document.getElementById('{{ $mapPanelId }}'), {
      zoom: 8,
      center: { lat: 27.9944024, lng: -81.7602544 }, /* Florida center */
      mapTypeId: 'roadmap',
    });

    /* Style boundary data layer */
    ldnaMap.data.setStyle(function (feature) {
      var key   = feature.getProperty('_ldnaKey') || '';
      var isZip = key.indexOf('zip__') === 0;
      return {
        fillColor:    isZip ? '#7c3aed' : '#0369a1',
        fillOpacity:  0.10,
        strokeColor:  isZip ? '#7c3aed' : '#0369a1',
        strokeWeight: 2,
        strokeOpacity: 0.8,
      };
    });

    /* Trigger resize after tab animation settles */
    setTimeout(function () {
      if (ldnaMap) google.maps.event.trigger(ldnaMap, 'resize');
    }, 300);

    /* ── Re-render saved polygons (with delete buttons) ── */
    ldnaState.polygons.forEach(function (poly, i) {
      if (!poly.path || !poly.path.length) return;
      var gmPoly = new google.maps.Polygon({
        paths: poly.path,
        fillColor: '#0369a1', fillOpacity: 0.15,
        strokeColor: '#0369a1', strokeWeight: 2, editable: true,
        map: ldnaMap,
      });
      var idx   = ldnaOverlays.length;
      var label = poly.label || ('Polygon ' + (i + 1));
      ldnaOverlays.push({ type: 'polygon', overlay: gmPoly, label: label });
      google.maps.event.addListener(gmPoly.getPath(), 'set_at',    ldnaSerialize);
      google.maps.event.addListener(gmPoly.getPath(), 'insert_at', ldnaSerialize);
      ldnaAddOverlayListItem(idx, label, 'fa-draw-polygon text-primary');
    });

    /* ── Re-render saved radius searches + drawn circles (with delete buttons) ── */
    ldnaState.radius_searches.forEach(function (r, i) {
      var lat = r.lat  !== undefined ? parseFloat(r.lat)
              : (r.center ? parseFloat(r.center.lat) : null);
      var lng = r.lng  !== undefined ? parseFloat(r.lng)
              : (r.center ? parseFloat(r.center.lng) : null);
      if (lat === null || lng === null || isNaN(lat) || isNaN(lng)) return;
      var label   = r.address
                  ? (r.address + ' (' + r.radius_miles + ' mi)')
                  : (r.label || ('Circle ' + (i + 1)));
      var gmCircle = new google.maps.Circle({
        center: { lat: lat, lng: lng },
        radius: (parseFloat(r.radius_miles) || 5) * 1609.34,
        fillColor: '#6b7280', fillOpacity: 0.12,
        strokeColor: '#6b7280', strokeWeight: 2, editable: true,
        map: ldnaMap,
      });
      var idx = ldnaOverlays.length;
      ldnaOverlays.push({ type: 'radius_search', overlay: gmCircle, label: label, data: r });
      google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
      google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);
      ldnaAddOverlayListItem(idx, label, 'fa-circle-dot text-secondary');
    });

    /* ── Wire autocompletes ── */
    ldnaInitCitiesAutocomplete();
    ldnaInitRadiusAutocomplete();

    /* ── Fetch boundaries for existing tags (edit-mode reload) ── */
    ldnaState.cities.forEach(function (c) {
      ldnaEnqueueBoundary('city__' + c, ldnaCityBoundaryUrl(c, null, null));
    });
    ldnaState.zip_codes.forEach(function (z) {
      ldnaFetchZipBoundary(z, 'zip__' + z);
    });
    ldnaState.counties.forEach(function (co) {
      ldnaEnqueueBoundary('county__' + co, ldnaCountyBoundaryUrl(co));
    });
    ldnaBoundaryProcess();

    /* ── Set initial map center ── */
    /* Prefer saved radius circles so map is already zoomed to relevant area */
    for (var oi = 0; oi < ldnaOverlays.length; oi++) {
      var ov = ldnaOverlays[oi];
      if (ov && (ov.type === 'radius_search' || ov.type === 'circle') && ov.overlay.getBounds) {
        ldnaMap.fitBounds(ov.overlay.getBounds());
        break;
      }
    }
  }

  /* ── Visibility-aware init (for tabs/hidden panels) ──────────────────────── */
  function ldnaTryInit() {
    if (ldnaMapInitialized) return;
    /* Only need google.maps.Map — no DrawingManager dependency */
    if (typeof google === 'undefined' || !google.maps ||
        typeof google.maps.Map !== 'function') {
      setTimeout(ldnaTryInit, 200);
      return;
    }
    if (ldnaIsContainerVisible()) { ldnaInitMap(); return; }

    /* Container hidden — observe for visibility change */
    var container = document.getElementById('{{ $mapPanelId }}');
    if (!container) { setTimeout(ldnaTryInit, 200); return; }

    var tabPane = container.closest('.tab-pane') || container.parentElement;
    if (tabPane && !tabPane._ldnaMutObs) {
      var mutObs = new MutationObserver(function () {
        if (ldnaMapInitialized) { mutObs.disconnect(); return; }
        if (ldnaIsContainerVisible()) { ldnaInitMap(); mutObs.disconnect(); }
      });
      mutObs.observe(tabPane, { attributes: true, attributeFilter: ['class', 'style'] });
      tabPane._ldnaMutObs = true;
      ldnaObservers.push(mutObs);
    }

    if (!container._ldnaResizeObs) {
      var resizeObs = new ResizeObserver(function () {
        if (ldnaMapInitialized) { resizeObs.disconnect(); return; }
        if (ldnaIsContainerVisible()) { ldnaInitMap(); resizeObs.disconnect(); }
      });
      resizeObs.observe(container);
      container._ldnaResizeObs = true;
      ldnaObservers.push(resizeObs);
    }

    setTimeout(ldnaTryInit, 300);
  }

  window.ldnaRequestInit = function () {
    if (!ldnaMapInitialized) {
      ldnaTryInit();
    } else {
      var ph = document.getElementById('{{ $mapPanelId }}-placeholder');
      if (ph) ph.style.display = 'none';
      if (ldnaMap) {
        google.maps.event.trigger(ldnaMap, 'resize');
        setTimeout(ldnaBoundaryProcess, 100);
      }
    }
  };

  /* ── Bootstrap tab shown event (broad net) ───────────────────────────────── */
  document.addEventListener('shown.bs.tab', function () {
    if (typeof window.ldnaRequestInit === 'function') window.ldnaRequestInit();
  });

  /* ── Public bridge: host blade can drive county boundaries ───────────────── */
  window.ldnaShowCountyBoundary = function (countyLabel) {
    if (!countyLabel) return;
    ldnaEnqueueBoundary('county__' + countyLabel, ldnaCountyBoundaryUrl(countyLabel));
    if (ldnaMap) ldnaBoundaryProcess();
  };
  window.ldnaHideCountyBoundary = function (countyLabel) {
    ldnaClearBoundaryOverlay('county__' + countyLabel);
  };

  /* ── Click on wrap focuses the tag input ─────────────────────────────────── */
  document.querySelectorAll('.ldna-tag-input-wrap').forEach(function (w) {
    w.addEventListener('click', function () {
      var inp = w.querySelector('.ldna-tag-input');
      if (inp) inp.focus();
    });
  });

  /* ── Boot ────────────────────────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ldnaTryInit);
  } else {
    ldnaTryInit();
  }
})();
</script>
