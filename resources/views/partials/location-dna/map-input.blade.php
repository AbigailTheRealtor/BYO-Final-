{{--
  Location Preferences Map Input Partial
  ======================================
  Include on any Buyer/Tenant Criteria create or edit form.

  Props:
    $existingLocationDna  – decoded array from the `location_dna_preferences` meta key
    $mapPanelId           – unique DOM id for the map div (default: ldna-map-panel)

  BOUNDARY DATA SOURCES
  ─────────────────────
  • Cities / Counties / States  →  Nominatim OpenStreetMap API (free, no key required)
  • ZIP Codes                   →  US Census Bureau TIGERweb ZCTA2020 REST API (free, public)
  • Neighborhoods               →  No reliable free GeoJSON source exists for US neighborhoods.
                                   Neighborhood data is used for MATCHING/SCORING only.
                                   No boundary polygon is rendered.

  CANONICAL JSON (stored in meta key `location_dna_preferences`):
  {
    "cities":          ["Orlando, FL"],
    "zip_codes":       ["32801"],
    "neighborhoods":   ["Downtown"],
    "counties":        ["Orange County, FL"],
    "polygons":        [ { "label": "…", "path": [{lat,lng},…] } ],
    "radius_searches": [ { "address":"…", "lat":0, "lng":0, "radius_miles":5 } ],
    "flexible_location": false,
    "location_notes": ""
  }
--}}

@php
  $ldna        = $existingLocationDna ?? [];
  $ldnaCities  = $ldna['cities']          ?? [];
  $ldnaZips    = $ldna['zip_codes']       ?? [];
  $ldnaNeigh   = $ldna['neighborhoods']   ?? [];
  $ldnaCounties= $ldna['counties']        ?? [];
  $ldnaPolygons= $ldna['polygons']        ?? [];
  $ldnaRadii   = $ldna['radius_searches'] ?? [];
  $ldnaFlex    = $ldna['flexible_location'] ?? false;
  $ldnaNotes   = $ldna['location_notes']  ?? '';
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
  .ldna-radius-form { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-top:.5rem; }
  .ldna-hint { font-size:.78rem; color:#0369a1; margin-top:.25rem; }
  .ldna-draw-mode-indicator {
    display:none; font-size:.8rem; color:#0369a1;
    background:#eff6ff; border:1px solid #bfdbfe;
    border-radius:4px; padding:.3rem .6rem;
    align-items:center; gap:.4rem;
  }
  .ldna-draw-mode-indicator.active { display:flex; }
</style>

<div class="ldna-section" wire:ignore>
  <div class="ldna-section-header">
    <i class="fa-solid fa-map-location-dot" style="color:#0369a1;font-size:1.1rem;"></i>
    <h5>Location Preferences Map <span style="font-weight:400;font-size:.85rem;color:#64748b;">(optional — adding any item shows its boundary on the map)</span></h5>
  </div>

  {{-- ── Tag inputs row 1: cities, ZIPs, neighborhoods ── --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Cities
        <small class="text-muted">(type & select)</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-cities-wrap">
        @foreach($ldnaCities as $c)
          <span class="ldna-tag" data-value="{{ $c }}">{{ $c }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'cities')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-cities-input"
          placeholder="e.g. Orlando" autocomplete="off">
      </div>
      <div class="ldna-hint"><i class="fa-solid fa-circle-info"></i> Selecting a city draws its boundary on the map.</div>
    </div>
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred ZIP Codes
        <small class="text-muted">(type & Enter)</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-zips-wrap">
        @foreach($ldnaZips as $z)
          <span class="ldna-tag" data-value="{{ $z }}">{{ $z }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'zips')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-zips-input"
          placeholder="e.g. 32801" onkeydown="ldnaAddTag(event,'zips')">
      </div>
      <div class="ldna-hint"><i class="fa-solid fa-circle-info"></i> ZIP boundaries are drawn from US Census data.</div>
    </div>
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Neighborhoods
        <small class="text-muted">(type & Enter)</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-neighborhoods-wrap">
        @foreach($ldnaNeigh as $n)
          <span class="ldna-tag" data-value="{{ $n }}">{{ $n }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'neighborhoods')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-neighborhoods-input"
          placeholder="e.g. Thornton Park" onkeydown="ldnaAddTag(event,'neighborhoods')">
      </div>
      <div class="ldna-hint"><i class="fa-solid fa-info-circle"></i> Used for matching/scoring. No map boundary — see note below.</div>
    </div>
  </div>

  {{-- ── Tag inputs row 2: counties ── --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Counties
        <small class="text-muted">(type & Enter — e.g. "Orange County, FL")</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-counties-wrap">
        @foreach($ldnaCounties as $co)
          <span class="ldna-tag" data-value="{{ $co }}">{{ $co }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'counties')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-counties-input"
          placeholder="Orange County, FL" onkeydown="ldnaAddTag(event,'counties')">
      </div>
    </div>
  </div>

  {{-- ── Map panel ── --}}
  <label class="fw-bold" style="font-size:.88rem;">Draw Custom Areas on Map
    <small class="text-muted">(polygon = custom shape, circle = radius around a point)</small>
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
    <span id="ldna-draw-mode-indicator" class="ldna-draw-mode-indicator">
      <i class="fa-solid fa-pen"></i>
      <span id="ldna-draw-mode-text">Click on the map to draw. Double-click to finish.</span>
    </span>
  </div>

  <div style="position:relative;">
    <div id="{{ $mapPanelId }}" wire:ignore></div>
    <div id="{{ $mapPanelId }}-placeholder" style="position:absolute;top:0;left:0;right:0;bottom:0;
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

  {{-- ── Overlay list ── --}}
  <ul class="ldna-overlay-list list-unstyled mt-1" id="ldna-overlay-list" style="font-size:.83rem;color:#374151;">
    @foreach($ldnaPolygons as $i => $poly)
      <li><i class="fa-solid fa-draw-polygon text-primary"></i>
        {{ $poly['label'] ?? ('Polygon ' . ($i+1)) }}</li>
    @endforeach
    @foreach($ldnaRadii as $i => $r)
      <li><i class="fa-solid fa-circle-dot text-secondary"></i>
        {{ $r['address'] ?? $r['label'] ?? ('Radius ' . ($i+1)) }} ({{ $r['radius_miles'] }} mi)</li>
    @endforeach
  </ul>

  {{-- ── Radius search form ── --}}
  <div class="ldna-radius-form mt-2">
    <input type="text" id="ldna-radius-address" class="form-control form-control-sm"
      placeholder="Address or place" style="max-width:280px;" autocomplete="off">
    <input type="number" id="ldna-radius-miles" class="form-control form-control-sm"
      placeholder="Miles" min="0.1" step="0.1" value="5" style="max-width:90px;">
    <button type="button" class="btn btn-outline-primary btn-sm"
      onclick="ldnaAddRadiusSearch()">
      <i class="fa-solid fa-location-crosshairs"></i> Add Radius Search
    </button>
  </div>

  {{-- ── Neighborhood note ── --}}
  <div class="alert alert-info py-2 px-3 mt-3" style="font-size:.82rem;">
    <strong>About Neighborhood Boundaries:</strong>
    No free, reliable GeoJSON source exists for US neighborhood boundaries
    (Google Maps and OpenStreetMap both have incomplete/inconsistent coverage).
    Neighborhood names are saved and used for <strong>matching and scoring</strong>
    against agent/listing data — they do not produce map polygons.
    When a verified neighborhood boundary dataset becomes available, this can be upgraded.
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
        <small class="text-muted">(optional)</small></label>
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
  /* ── Re-execution guard (survives Livewire morphdom) ────────────────────── */
  var _panelKey = 'ldnaInit_{{ $mapPanelId }}';
  if (window[_panelKey]) return;
  window[_panelKey] = true;

  /* ── State ───────────────────────────────────────────────────────────────── */
  var ldnaState = {
    cities:           @json($ldnaCities),
    zip_codes:        @json($ldnaZips),
    neighborhoods:    @json($ldnaNeigh),
    counties:         @json($ldnaCounties),
    polygons:         @json($ldnaPolygons),
    radius_searches:  @json($ldnaRadii),
    flexible_location: {{ $ldnaFlex ? 'true' : 'false' }},
    location_notes:   @json($ldnaNotes),
  };

  /* ── Map state ───────────────────────────────────────────────────────────── */
  var ldnaMap             = null;
  var ldnaDrawingManager  = null;
  var ldnaMapInitialized  = false;
  var ldnaOverlays        = [];
  var ldnaObservers       = [];

  /* Boundary overlays: key → array of google.maps.Data.Feature */
  var ldnaBoundaryOverlays = {};

  /* Nominatim request queue (1 req/sec rate limit) */
  var ldnaBoundaryQueue   = [];
  var ldnaBoundaryRunning = false;

  /* ── Serialise to hidden field ───────────────────────────────────────────── */
  window.ldnaSerialize = function () {
    var flexEl  = document.getElementById('ldna-flexible');
    var notesEl = document.getElementById('ldna-location-notes');
    if (flexEl)  ldnaState.flexible_location = flexEl.checked;
    if (notesEl) ldnaState.location_notes    = notesEl.value.trim();

    ldnaState.polygons        = [];
    ldnaState.radius_searches = [];
    ldnaOverlays.forEach(function (item) {
      if (item.type === 'polygon') {
        var path = [];
        item.overlay.getPath().forEach(function (ll) { path.push({ lat: ll.lat(), lng: ll.lng() }); });
        ldnaState.polygons.push({ label: item.label, path: path });
      } else if (item.type === 'circle' || item.type === 'radius_search') {
        var c  = item.overlay.getCenter();
        var rm = parseFloat((item.overlay.getRadius() / 1609.34).toFixed(2));
        var entry = { lat: c.lat(), lng: c.lng(), radius_miles: rm };
        if (item.data && item.data.address) { entry.address = item.data.address; }
        else { entry.label = item.label; }
        ldnaState.radius_searches.push(entry);
      }
    });

    var jsonVal = JSON.stringify(ldnaState);
    var field = document.getElementById('ldna-json-field');
    if (field) field.value = jsonVal;
  };

  /* ── Tag helpers ─────────────────────────────────────────────────────────── */
  function ldnaGroupKey(group) {
    return { cities:'cities', zips:'zip_codes', neighborhoods:'neighborhoods', counties:'counties' }[group];
  }

  function ldnaAddTagValue(group, val) {
    if (!val) return;
    var stateKey = ldnaGroupKey(group);
    if (!stateKey) return;
    if (ldnaState[stateKey].indexOf(val) !== -1) return;
    ldnaState[stateKey].push(val);

    var wrap  = document.getElementById('ldna-' + group + '-wrap');
    var input = document.getElementById('ldna-' + group + '-input');
    if (!wrap) return;
    var tag = document.createElement('span');
    tag.className  = 'ldna-tag';
    tag.dataset.value = val;
    var labelNode = document.createTextNode(val + ' ');
    var removeBtn = document.createElement('span');
    removeBtn.className  = 'ldna-tag-remove';
    removeBtn.textContent = '×';
    removeBtn.setAttribute('onclick', "ldnaRemoveTag(this,'" + group.replace(/'/g, "\\'") + "')");
    tag.appendChild(labelNode);
    tag.appendChild(removeBtn);
    if (input) { wrap.insertBefore(tag, input); } else { wrap.appendChild(tag); }

    ldnaSerialize();

    /* Fetch boundary asynchronously */
    if (group === 'cities')   { ldnaEnqueueBoundary('city__' + val,   ldnaCityBoundaryUrl(val)); }
    if (group === 'zips')     { ldnaEnqueueBoundary('zip__' + val,    ldnaZipBoundaryUrl(val)); }
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

    /* Remove boundary overlay */
    var bKey = group === 'cities' ? ('city__' + val)
             : group === 'zips'  ? ('zip__'  + val)
             : group === 'counties' ? ('county__' + val) : null;
    if (bKey) ldnaClearBoundaryOverlay(bKey);

    ldnaSerialize();
  };

  /* ── Places Autocomplete for cities input ────────────────────────────────── */
  function ldnaInitCitiesAutocomplete() {
    var input = document.getElementById('ldna-cities-input');
    if (!input || input._ldnaACAttached) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    input._ldnaACAttached = true;

    var ac = new google.maps.places.Autocomplete(input, {
      types: ['(cities)'],
      componentRestrictions: { country: 'us' },
    });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

    ac.addListener('place_changed', function () {
      var place = ac.getPlace();
      if (!place || !place.geometry) return;

      var cityName = place.name || '';
      if (place.address_components) {
        for (var i = 0; i < place.address_components.length; i++) {
          if (place.address_components[i].types.indexOf('administrative_area_level_1') !== -1) {
            cityName += ', ' + place.address_components[i].short_name;
            break;
          }
        }
      }
      ldnaAddTagValue('cities', cityName || place.name);
      input.value = '';

      /* Center map on selected city */
      if (ldnaMap && place.geometry) {
        if (place.geometry.viewport) { ldnaMap.fitBounds(place.geometry.viewport); }
        else { ldnaMap.setCenter(place.geometry.location); ldnaMap.setZoom(11); }
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

  /* ── Boundary URL builders ───────────────────────────────────────────────── */
  function ldnaCityBoundaryUrl(cityLabel) {
    /* "Orlando, FL"  →  Nominatim search for city boundary */
    var q = encodeURIComponent(cityLabel + ', USA');
    return 'https://nominatim.openstreetmap.org/search?q=' + q +
           '&format=geojson&polygon_geojson=1&featuretype=city&countrycodes=us&limit=1';
  }

  function ldnaZipBoundaryUrl(zip) {
    /* US Census Bureau TIGERweb ZCTA2020 REST API — free, complete US coverage */
    return 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/tigerWMS_ZCTA2020' +
           '/MapServer/0/query?where=GEOID20%3D%27' + encodeURIComponent(zip) +
           '%27&outFields=GEOID20&returnGeometry=true&geometryPrecision=4&outSR=4326&f=geojson';
  }

  function ldnaCountyBoundaryUrl(countyLabel) {
    /* "Orange County, FL"  →  Nominatim */
    var q = encodeURIComponent(countyLabel + ', USA');
    return 'https://nominatim.openstreetmap.org/search?q=' + q +
           '&format=geojson&polygon_geojson=1&countrycodes=us&limit=1';
  }

  /* ── Boundary fetch queue (respect Nominatim 1 req/sec) ─────────────────── */
  function ldnaEnqueueBoundary(key, url) {
    /* Remove any existing pending request for the same key */
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
      /* Nominatim returns FeatureCollection; Census TIGER returns FeatureCollection too */
      var features = (data.features && data.features.length) ? data.features : null;
      if (features && features.length > 0) {
        ldnaRenderBoundaryFeature(task.key, features[0]);
      }
    })
    .catch(function (e) { console.warn('[ldna boundary] fetch error for', task.key, e); })
    .finally(function () {
      ldnaBoundaryRunning = false;
      /* 1100ms gap between requests — Nominatim requires ≤1 req/sec */
      setTimeout(ldnaBoundaryProcess, 1100);
    });
  }

  function ldnaRenderBoundaryFeature(key, feature) {
    if (!ldnaMap) return;
    ldnaClearBoundaryOverlay(key);

    var added = ldnaMap.data.addGeoJson({
      type: 'FeatureCollection',
      features: [Object.assign({}, feature, { properties: { _ldnaKey: key } })]
    });
    ldnaBoundaryOverlays[key] = added;

    /* Fit map to the new boundary */
    var bounds = new google.maps.LatLngBounds();
    var geo = feature.geometry;
    var coordSets = [];
    if (geo.type === 'Polygon') { coordSets = [geo.coordinates[0]]; }
    else if (geo.type === 'MultiPolygon') {
      geo.coordinates.forEach(function (poly) { coordSets.push(poly[0]); });
    } else if (geo.type === 'Point') {
      bounds.extend({ lat: geo.coordinates[1], lng: geo.coordinates[0] });
    }
    coordSets.forEach(function (ring) {
      ring.forEach(function (c) { bounds.extend({ lat: c[1], lng: c[0] }); });
    });
    if (!bounds.isEmpty()) ldnaMap.fitBounds(bounds);

    /* If multiple boundaries already shown, zoom out to show all */
    var allKeys = Object.keys(ldnaBoundaryOverlays);
    if (allKeys.length > 1) {
      var allBounds = new google.maps.LatLngBounds();
      allKeys.forEach(function (k) {
        var feats = ldnaBoundaryOverlays[k];
        if (feats) feats.forEach(function (f) {
          f.getGeometry() && f.getGeometry().forEachLatLng(function (ll) { allBounds.extend(ll); });
        });
      });
      if (!allBounds.isEmpty()) ldnaMap.fitBounds(allBounds);
    }

    /* Continue processing queue */
    if (!ldnaBoundaryRunning) setTimeout(ldnaBoundaryProcess, 100);
  }

  function ldnaClearBoundaryOverlay(key) {
    if (!ldnaBoundaryOverlays[key] || !ldnaMap) return;
    ldnaBoundaryOverlays[key].forEach(function (feature) {
      try { ldnaMap.data.remove(feature); } catch (e) {}
    });
    delete ldnaBoundaryOverlays[key];
  }

  /* ── DrawingManager helpers ──────────────────────────────────────────────── */
  function ldnaOverlayComplete(e) {
    var idx   = ldnaOverlays.length;
    var label = (e.type === 'polygon') ? ('Polygon ' + (idx + 1)) : ('Radius ' + (idx + 1));
    ldnaOverlays.push({ type: e.type, overlay: e.overlay, label: label });

    if (e.type === 'polygon') {
      google.maps.event.addListener(e.overlay.getPath(), 'set_at',    ldnaSerialize);
      google.maps.event.addListener(e.overlay.getPath(), 'insert_at', ldnaSerialize);
    }
    if (e.type === 'circle') {
      google.maps.event.addListener(e.overlay, 'radius_changed', ldnaSerialize);
      google.maps.event.addListener(e.overlay, 'center_changed', ldnaSerialize);
    }

    var li   = document.createElement('li');
    var icon = e.type === 'polygon' ? 'fa-draw-polygon text-primary' : 'fa-circle-dot text-secondary';
    li.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + label;
    var ol = document.getElementById('ldna-overlay-list');
    if (ol) ol.appendChild(li);

    if (ldnaDrawingManager) ldnaDrawingManager.setDrawingMode(null);
    ldnaUpdateDrawButtons(null);
    ldnaSerialize();
  }

  function ldnaCreateDrawingManager() {
    if (typeof google === 'undefined' || !google.maps ||
        !google.maps.drawing || typeof google.maps.drawing.DrawingManager !== 'function') {
      console.warn('[ldna] DrawingManager constructor not available yet');
      return null;
    }
    try {
      var dm = new google.maps.drawing.DrawingManager({
        drawingMode:    null,
        drawingControl: false,
        polygonOptions:  {
          fillColor: '#0369a1', fillOpacity: 0.15,
          strokeColor: '#0369a1', strokeWeight: 2, editable: true,
        },
        circleOptions: {
          fillColor: '#6b7280', fillOpacity: 0.12,
          strokeColor: '#6b7280', strokeWeight: 2, editable: true,
        },
      });
      dm.setMap(ldnaMap);
      google.maps.event.addListener(dm, 'overlaycomplete', ldnaOverlayComplete);
      return dm;
    } catch (err) {
      console.error('[ldna] DrawingManager creation error:', err);
      return null;
    }
  }

  function ldnaUpdateDrawButtons(activeMode) {
    var indicator = document.getElementById('ldna-draw-mode-indicator');
    var modeText  = document.getElementById('ldna-draw-mode-text');
    document.querySelectorAll('.ldna-draw-btn').forEach(function (btn) {
      btn.classList.remove('btn-primary', 'btn-secondary', 'active');
      if (btn.id === 'ldna-draw-btn-polygon') btn.classList.add('btn-outline-primary');
      if (btn.id === 'ldna-draw-btn-circle')  btn.classList.add('btn-outline-secondary');
    });
    if (activeMode && indicator && modeText) {
      indicator.classList.add('active');
      modeText.textContent = activeMode === 'polygon'
        ? 'Polygon mode: click to add vertices. Double-click to close.'
        : 'Circle mode: click and drag on the map.';
      var activeBtn = document.getElementById('ldna-draw-btn-' + activeMode);
      if (activeBtn) {
        activeBtn.classList.remove(activeMode === 'polygon' ? 'btn-outline-primary' : 'btn-outline-secondary');
        activeBtn.classList.add(activeMode === 'polygon' ? 'btn-primary' : 'btn-secondary', 'active');
      }
    } else if (indicator) {
      indicator.classList.remove('active');
    }
  }

  /* ── Public: set draw mode ───────────────────────────────────────────────── */
  window.ldnaSetDrawMode = function (mode) {
    if (!ldnaMap) {
      /* Map not yet initialized — queue retry */
      ldnaRequestInit();
      setTimeout(function () { window.ldnaSetDrawMode(mode); }, 600);
      return;
    }
    /* Lazily create DrawingManager if missing (handles race where ldnaInitMap
       ran before drawing library was ready) */
    if (!ldnaDrawingManager) {
      ldnaDrawingManager = ldnaCreateDrawingManager();
      if (!ldnaDrawingManager) {
        /* Drawing library still not ready — retry */
        setTimeout(function () { window.ldnaSetDrawMode(mode); }, 600);
        return;
      }
    }
    var overlayType = mode === 'polygon'
      ? google.maps.drawing.OverlayType.POLYGON
      : google.maps.drawing.OverlayType.CIRCLE;
    try {
      ldnaDrawingManager.setDrawingMode(overlayType);
      ldnaUpdateDrawButtons(mode);
    } catch (err) {
      console.error('[ldna] setDrawingMode error:', err);
    }
  };

  window.ldnaClearAllOverlays = function () {
    ldnaOverlays.forEach(function (item) { try { item.overlay.setMap(null); } catch (e) {} });
    ldnaOverlays = [];
    var ol = document.getElementById('ldna-overlay-list');
    if (ol) ol.innerHTML = '';
    if (ldnaDrawingManager) ldnaDrawingManager.setDrawingMode(null);
    ldnaUpdateDrawButtons(null);
    ldnaSerialize();
  };

  window.ldnaAddRadiusSearch = function () {
    var addrEl  = document.getElementById('ldna-radius-address');
    var milesEl = document.getElementById('ldna-radius-miles');
    if (!addrEl || !milesEl) return;
    var address = addrEl.value.trim();
    var miles   = parseFloat(milesEl.value) || 5;
    if (!address) return;

    if (!ldnaMap) { ldnaRequestInit(); setTimeout(window.ldnaAddRadiusSearch, 600); return; }

    var geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: address, componentRestrictions: { country: 'us' } },
      function (results, status) {
        if (status !== 'OK' || !results.length) {
          alert('Could not find location: ' + address); return;
        }
        var loc = results[0].geometry.location;
        var lat = loc.lat(); var lng = loc.lng();
        var label = address + ' (' + miles + ' mi)';
        var gmCircle = new google.maps.Circle({
          center: { lat: lat, lng: lng }, radius: miles * 1609.34,
          fillColor: '#6b7280', fillOpacity: 0.12,
          strokeColor: '#6b7280', strokeWeight: 2, editable: true, map: ldnaMap,
        });
        var idx = ldnaOverlays.length;
        ldnaOverlays.push({ type: 'radius_search', overlay: gmCircle, label: label,
          data: { address: address, lat: lat, lng: lng, radius_miles: miles } });
        google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
        google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);

        var li = document.createElement('li');
        li.innerHTML = '<i class="fa-solid fa-circle-dot text-secondary"></i> ' + label;
        var ol = document.getElementById('ldna-overlay-list');
        if (ol) ol.appendChild(li);

        ldnaMap.panTo({ lat: lat, lng: lng });
        ldnaMap.fitBounds(gmCircle.getBounds());
        ldnaSerialize();
        addrEl.value = '';
      }
    );
  };

  /* ── Map initialisation ──────────────────────────────────────────────────── */
  function ldnaIsContainerVisible() {
    var el = document.getElementById('{{ $mapPanelId }}');
    if (!el) return false;
    var rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function ldnaInitMap() {
    if (ldnaMapInitialized) return;
    ldnaMapInitialized = true;

    /* Disconnect observers — no longer needed */
    ldnaObservers.forEach(function (o) { try { o.disconnect(); } catch (e) {} });
    ldnaObservers = [];

    var ph = document.getElementById('{{ $mapPanelId }}-placeholder');
    if (ph) ph.style.display = 'none';

    var defaultCenter = { lat: 27.9944024, lng: -81.7602544 };
    ldnaMap = new google.maps.Map(document.getElementById('{{ $mapPanelId }}'), {
      zoom: 8, center: defaultCenter, mapTypeId: 'roadmap',
    });

    /* Style the data layer (boundary overlays) */
    ldnaMap.data.setStyle(function (feature) {
      var key = feature.getProperty('_ldnaKey') || '';
      var isZip = key.indexOf('zip__') === 0;
      return {
        fillColor:    isZip ? '#7c3aed' : '#0369a1',
        fillOpacity:  0.1,
        strokeColor:  isZip ? '#7c3aed' : '#0369a1',
        strokeWeight: 2,
        strokeOpacity: 0.8,
      };
    });

    /* Create DrawingManager — wrapped in guard + try-catch */
    ldnaDrawingManager = ldnaCreateDrawingManager();
    /* If drawing library not ready, ldnaSetDrawMode will lazily create it on first click */

    /* Trigger resize after short delay to handle late layout settle */
    setTimeout(function () {
      if (ldnaMap) google.maps.event.trigger(ldnaMap, 'resize');
    }, 250);

    /* Re-render saved polygons */
    ldnaState.polygons.forEach(function (poly) {
      if (!poly.path || !poly.path.length) return;
      var gmPoly = new google.maps.Polygon({
        paths: poly.path,
        fillColor: '#0369a1', fillOpacity: 0.15,
        strokeColor: '#0369a1', strokeWeight: 2, editable: true, map: ldnaMap,
      });
      ldnaOverlays.push({ type: 'polygon', overlay: gmPoly, label: poly.label || 'Polygon' });
      google.maps.event.addListener(gmPoly.getPath(), 'set_at',    ldnaSerialize);
      google.maps.event.addListener(gmPoly.getPath(), 'insert_at', ldnaSerialize);
    });

    /* Re-render saved radius circles */
    ldnaState.radius_searches.forEach(function (r) {
      var lat = r.lat !== undefined ? parseFloat(r.lat) : (r.center ? parseFloat(r.center.lat) : null);
      var lng = r.lng !== undefined ? parseFloat(r.lng) : (r.center ? parseFloat(r.center.lng) : null);
      if (lat === null || lng === null || isNaN(lat) || isNaN(lng)) return;
      var label = r.address ? (r.address + ' (' + r.radius_miles + ' mi)') : (r.label || 'Radius');
      var gmCircle = new google.maps.Circle({
        center: { lat: lat, lng: lng }, radius: (parseFloat(r.radius_miles) || 5) * 1609.34,
        fillColor: '#6b7280', fillOpacity: 0.12,
        strokeColor: '#6b7280', strokeWeight: 2, editable: true, map: ldnaMap,
      });
      ldnaOverlays.push({ type: 'radius_search', overlay: gmCircle, label: label, data: r });
      google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
      google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);
    });

    /* Wire up autocompletes */
    ldnaInitCitiesAutocomplete();
    ldnaInitRadiusAutocomplete();

    /* Fetch boundaries for existing tags (edit mode reload) */
    ldnaState.cities.forEach(function (c) {
      ldnaEnqueueBoundary('city__' + c, ldnaCityBoundaryUrl(c));
    });
    ldnaState.zip_codes.forEach(function (z) {
      ldnaEnqueueBoundary('zip__' + z, ldnaZipBoundaryUrl(z));
    });
    ldnaState.counties.forEach(function (co) {
      ldnaEnqueueBoundary('county__' + co, ldnaCountyBoundaryUrl(co));
    });
    ldnaBoundaryProcess();

    /* Set initial map center — bias to saved radius circles, else first city/zip */
    if (ldnaOverlays.length > 0) {
      for (var oi = 0; oi < ldnaOverlays.length; oi++) {
        var ov = ldnaOverlays[oi];
        if ((ov.type === 'radius_search' || ov.type === 'circle') && ov.overlay.getBounds) {
          ldnaMap.fitBounds(ov.overlay.getBounds()); break;
        }
      }
    }
    /* If no boundaries or overlays exist yet, use first city/zip for centering */
    if (!ldnaState.cities.length && !ldnaState.zip_codes.length &&
        !ldnaState.counties.length && !ldnaOverlays.length) {
      /* Default: Florida center (already set above) */
    }
  }

  /* ── Init request / visibility check ────────────────────────────────────── */
  function ldnaTryInit() {
    if (ldnaMapInitialized) return;
    if (typeof google === 'undefined' || !google.maps ||
        !google.maps.drawing || typeof google.maps.drawing.DrawingManager !== 'function') {
      /* Drawing library not yet ready — wait and retry
         Note: we also accept the case where drawing exists but DrawingManager
         is missing (race condition in Maps API loading). ldnaSetDrawMode will
         lazily re-create it when the user first clicks a draw button. */
      if (typeof google !== 'undefined' && google.maps &&
          typeof google.maps.Map === 'function') {
        /* Main API ready but drawing not yet — init map now, skip DrawingManager guard */
        if (ldnaIsContainerVisible()) { ldnaInitMap(); return; }
      }
      setTimeout(ldnaTryInit, 200);
      return;
    }
    if (ldnaIsContainerVisible()) { ldnaInitMap(); return; }

    /* Container hidden — set up observers to trigger init when it becomes visible */
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
        /* Resume boundary queue in case it stalled */
        setTimeout(ldnaBoundaryProcess, 100);
      }
    }
  };

  /* ── Catch any shown.bs.tab event (broad net) ────────────────────────────── */
  document.addEventListener('shown.bs.tab', function () {
    if (typeof window.ldnaRequestInit === 'function') window.ldnaRequestInit();
  });

  /* ── Expose public function for host-blade county bridges ────────────────── */
  /* Call window.ldnaShowCountyBoundary('Orange County, FL') from the host blade
     to render a county boundary from the main county selection field. */
  window.ldnaShowCountyBoundary = function (countyLabel) {
    if (!countyLabel) return;
    ldnaEnqueueBoundary('county__' + countyLabel, ldnaCountyBoundaryUrl(countyLabel));
    if (ldnaMap) ldnaBoundaryProcess();
  };
  window.ldnaHideCountyBoundary = function (countyLabel) {
    ldnaClearBoundaryOverlay('county__' + countyLabel);
  };

  /* ── Start polling ───────────────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ldnaTryInit);
  } else {
    ldnaTryInit();
  }

  /* ── Click-on-wrap focuses input ─────────────────────────────────────────── */
  document.querySelectorAll('.ldna-tag-input-wrap').forEach(function (w) {
    w.addEventListener('click', function () {
      var inp = w.querySelector('.ldna-tag-input');
      if (inp) inp.focus();
    });
  });
})();
</script>
