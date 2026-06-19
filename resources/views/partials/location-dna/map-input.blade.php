{{--
  Location Preferences Map Input Partial
  ======================================
  Include on any Buyer/Tenant Criteria create or edit form after the existing
  city/county/state location fields.

  Props (optional, passed via @include):
    $existingLocationDna  – decoded array from the `location_dna_preferences` meta key
                           (used on edit to re-hydrate map state)
    $mapPanelId           – unique DOM id for the map div (default: ldna-map-panel)

  Canonical JSON shape stored in meta key `location_dna_preferences`:
  {
    "cities":          ["Orlando, FL", "Tampa, FL"],   // Places-autocomplete city labels
    "zip_codes":       ["32801", "33602"],              // free-tag ZIP labels
    "neighborhoods":   ["Downtown", "Ybor City"],      // free-tag neighbourhood labels
    "polygons": [                                      // drawn polygon paths
      { "label": "Custom Area 1", "path": [ {lat, lng}, … ] }
    ],
    "radius_searches": [                               // drawn circles / address radius
      { "address": "123 Main St", "lat": 27.9, "lng": -81.7, "radius_miles": 5 }
    ],
    "flexible_location": false,
    "location_notes": "Prefer quiet streets near good schools."
  }

  Phase 1 boundary rendering: city, ZIP, county values render as labeled chips
  on the public map (no external boundary GeoJSON API). A future phase can swap
  in real GeoJSON boundaries by implementing the boundary adapter without
  touching this input partial or the x-location-dna-map display component.
--}}

@php
  $ldna        = $existingLocationDna ?? [];
  $ldnaCities  = $ldna['cities']          ?? [];
  $ldnaZips    = $ldna['zip_codes']       ?? [];
  $ldnaNeigh   = $ldna['neighborhoods']   ?? [];
  $ldnaPolygons = $ldna['polygons']       ?? [];
  $ldnaRadii   = $ldna['radius_searches'] ?? [];
  $ldnaFlex    = $ldna['flexible_location'] ?? false;
  $ldnaNotes   = $ldna['location_notes']  ?? '';
  $ldnaJson    = count($ldna) ? json_encode($ldna) : '';
  $mapPanelId  = $mapPanelId ?? 'ldna-map-panel';
@endphp

<style>
  .ldna-section { margin-top: 2rem; }
  .ldna-section-header {
    display: flex; align-items: center; gap: .5rem;
    background: #f0f9ff; border: 1px solid #bae6fd;
    border-radius: 6px; padding: .65rem 1rem; margin-bottom: 1rem;
  }
  .ldna-section-header h5 { margin: 0; font-size: 1rem; color: #0369a1; font-weight: 700; }
  .ldna-tag-input-wrap { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center;
    border: 1px solid #ced4da; border-radius: .375rem; padding: .45rem .6rem;
    background: #fafafb; min-height: 48px; cursor: text; }
  .ldna-tag { display: inline-flex; align-items: center; gap: .25rem;
    background: #0369a1; color: #fff; border-radius: 20px;
    padding: .2rem .65rem; font-size: .82rem; }
  .ldna-tag .ldna-tag-remove { cursor: pointer; font-weight: 700; line-height: 1; }
  .ldna-tag-input { border: none; outline: none; flex: 1; min-width: 120px;
    font-size: .9rem; background: transparent; }
  #{{ $mapPanelId }} { width: 100%; height: 380px; border-radius: 6px;
    border: 1px solid #ced4da; margin-top: .5rem; }
  .ldna-map-toolbar { display: flex; flex-wrap: wrap; gap: .5rem;
    margin-bottom: .5rem; align-items: center; }
  .ldna-map-toolbar .btn { font-size: .82rem; }
  .ldna-radius-form { display: flex; gap: .5rem; flex-wrap: wrap;
    align-items: center; margin-top: .5rem; }
  .ldna-radius-form input { max-width: 200px; }
  .ldna-overlay-list { font-size: .83rem; color: #374151; margin-top: .35rem; }
  .ldna-overlay-list li { padding: .1rem 0; }
  .ldna-city-hint { font-size: .78rem; color: #0369a1; margin-top: .2rem; }
</style>

<div class="ldna-section" wire:ignore>
  <div class="ldna-section-header">
    <i class="fa-solid fa-map-location-dot" style="color:#0369a1;font-size:1.1rem;"></i>
    <h5>Location Preferences Map <span style="font-weight:400;font-size:.85rem;color:#64748b;">(optional)</span></h5>
  </div>

  {{-- ── Tag inputs ── --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Cities
        <small class="text-muted">(type &amp; select from dropdown)</small>
      </label>
      <div class="ldna-tag-input-wrap" id="ldna-cities-wrap">
        @foreach($ldnaCities as $c)
          <span class="ldna-tag" data-value="{{ $c }}">{{ $c }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'cities')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-cities-input" placeholder="e.g. Orlando"
          autocomplete="off">
      </div>
      <div class="ldna-city-hint"><i class="fa-solid fa-circle-info"></i> Selecting a city from the dropdown will center the map on that city.</div>
    </div>
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred ZIP Codes <small class="text-muted">(type &amp; press Enter)</small></label>
      <div class="ldna-tag-input-wrap" id="ldna-zips-wrap">
        @foreach($ldnaZips as $z)
          <span class="ldna-tag" data-value="{{ $z }}">{{ $z }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'zips')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-zips-input" placeholder="e.g. 32801"
          onkeydown="ldnaAddTag(event,'zips')">
      </div>
    </div>
    <div class="col-md-4">
      <label class="fw-bold" style="font-size:.88rem;">Preferred Neighborhoods <small class="text-muted">(type &amp; press Enter)</small></label>
      <div class="ldna-tag-input-wrap" id="ldna-neighborhoods-wrap">
        @foreach($ldnaNeigh as $n)
          <span class="ldna-tag" data-value="{{ $n }}">{{ $n }}
            <span class="ldna-tag-remove" onclick="ldnaRemoveTag(this,'neighborhoods')">×</span>
          </span>
        @endforeach
        <input type="text" class="ldna-tag-input" id="ldna-neighborhoods-input" placeholder="e.g. Downtown"
          onkeydown="ldnaAddTag(event,'neighborhoods')">
      </div>
    </div>
  </div>

  {{-- ── Map panel ── --}}
  <label class="fw-bold" style="font-size:.88rem;">Draw Preferred Areas on Map
    <small class="text-muted">(polygon = custom shape, circle = radius around a point)</small>
  </label>
  <div class="ldna-map-toolbar">
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="ldnaSetDrawMode('polygon')">
      <i class="fa-solid fa-draw-polygon"></i> Draw Polygon
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ldnaSetDrawMode('circle')">
      <i class="fa-solid fa-circle-dot"></i> Draw Circle
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="ldnaClearAllOverlays()">
      <i class="fa-solid fa-trash"></i> Clear All
    </button>
    <span class="text-muted" style="font-size:.8rem;">or use the radius search below</span>
  </div>
  <div style="position:relative;">
    <div id="{{ $mapPanelId }}" wire:ignore></div>
    <div id="{{ $mapPanelId }}-placeholder" style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;background:#f8fafc;border-radius:6px;border:1px solid #ced4da;color:#64748b;font-size:.9rem;text-align:center;padding:1rem;pointer-events:none;z-index:1;">
      @if(count($ldnaPolygons) || count($ldnaRadii))
        <span><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading map&hellip;</span>
      @else
        <span><i class="fa-solid fa-map me-2"></i>No map drawings saved yet. Use the toolbar above to draw your preferred areas.</span>
      @endif
    </div>
  </div>

  {{-- ── Overlay list ── --}}
  <ul class="ldna-overlay-list" id="ldna-overlay-list">
    @foreach($ldnaPolygons as $i => $poly)
      <li id="ldna-poly-label-{{ $i }}"><i class="fa-solid fa-draw-polygon text-primary"></i>
        {{ $poly['label'] ?? ('Polygon ' . ($i+1)) }}</li>
    @endforeach
    @foreach($ldnaRadii as $i => $r)
      <li id="ldna-radius-label-{{ $i }}"><i class="fa-solid fa-circle-dot text-secondary"></i>
        {{ $r['address'] ?? $r['label'] ?? ('Radius ' . ($i+1)) }} ({{ $r['radius_miles'] }} mi)</li>
    @endforeach
  </ul>

  {{-- ── Radius search form ── --}}
  <div class="ldna-radius-form mt-2">
    <input type="text" id="ldna-radius-address" class="form-control form-control-sm"
      placeholder="Address or place name" style="max-width:280px;" autocomplete="off">
    <input type="number" id="ldna-radius-miles" class="form-control form-control-sm"
      placeholder="Miles" min="0.1" step="0.1" value="5" style="max-width:90px;">
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="ldnaAddRadiusSearch()">
      <i class="fa-solid fa-location-crosshairs"></i> Add Radius
    </button>
  </div>

  {{-- ── Flexible location + notes ── --}}
  <div class="row g-3 mt-1">
    <div class="col-auto d-flex align-items-center gap-2">
      <input type="checkbox" id="ldna-flexible" class="form-check-input"
        {{ $ldnaFlex ? 'checked' : '' }} onchange="ldnaSerialize()">
      <label for="ldna-flexible" class="fw-bold mb-0" style="font-size:.88rem;">Location is flexible — open to nearby areas</label>
    </div>
  </div>
  <div class="row g-3 mt-1">
    <div class="col-12">
      <label class="fw-bold" style="font-size:.88rem;">Location Notes <small class="text-muted">(optional free text)</small></label>
      <textarea id="ldna-location-notes" class="form-control" rows="2"
        placeholder="E.g. Must be within 10 min of I-4. Prefer A-rated school district."
        oninput="ldnaSerialize()">{{ $ldnaNotes }}</textarea>
    </div>
  </div>

  {{-- ── Hidden serialisation field ── --}}
  <textarea name="location_dna_preferences" id="ldna-json-field"
    style="display:none;">{{ $ldnaJson }}</textarea>
</div>

<script>
(function () {
  /* ── Re-execution guard ──────────────────────────────────────────────────── */
  /* Livewire v2 morphdom re-executes script tags on re-render.
     We store a panel-scoped flag on window so subsequent runs are no-ops. */
  var _panelKey = 'ldnaInit_{{ $mapPanelId }}';
  if (window[_panelKey]) return;
  window[_panelKey] = true;

  /* ── State ────────────────────────────────────────────────────────────────── */
  var ldnaState = {
    cities:          @json($ldnaCities),
    zip_codes:       @json($ldnaZips),
    neighborhoods:   @json($ldnaNeigh),
    polygons:        @json($ldnaPolygons),
    radius_searches: @json($ldnaRadii),
    flexible_location: {{ $ldnaFlex ? 'true' : 'false' }},
    location_notes: @json($ldnaNotes),
  };

  var ldnaMap, ldnaDrawingManager;
  var ldnaOverlays = [];
  var ldnaMapInitialized = false;
  var ldnaObservers = [];  /* ResizeObserver + MutationObserver references */

  /* ── Serialise state to hidden field ─────────────────────────────────────── */
  window.ldnaSerialize = function () {
    var flexEl = document.getElementById('ldna-flexible');
    var notesEl = document.getElementById('ldna-location-notes');
    if (flexEl) ldnaState.flexible_location = flexEl.checked;
    if (notesEl) ldnaState.location_notes = notesEl.value.trim();

    ldnaState.polygons        = [];
    ldnaState.radius_searches = [];
    ldnaOverlays.forEach(function (item) {
      if (item.type === 'polygon') {
        var path = [];
        item.overlay.getPath().forEach(function (latlng) {
          path.push({ lat: latlng.lat(), lng: latlng.lng() });
        });
        ldnaState.polygons.push({ label: item.label || ('Polygon ' + (ldnaState.polygons.length + 1)), path: path });
      } else if (item.type === 'circle' || item.type === 'radius_search') {
        var c = item.overlay.getCenter();
        var rm = (item.overlay.getRadius() / 1609.34).toFixed(2);
        var entry = { lat: c.lat(), lng: c.lng(), radius_miles: parseFloat(rm) };
        if (item.type === 'radius_search' && item.data && item.data.address) {
          entry.address = item.data.address;
        } else {
          entry.label = item.label || ('Radius ' + (ldnaState.radius_searches.length + 1));
        }
        ldnaState.radius_searches.push(entry);
      }
    });

    var jsonVal = JSON.stringify(ldnaState);
    var field = document.getElementById('ldna-json-field');
    if (field) field.value = jsonVal;
  };

  /* ── Tag helpers ──────────────────────────────────────────────────────────── */
  function ldnaGroupKey(group) {
    return { 'cities': 'cities', 'zips': 'zip_codes', 'neighborhoods': 'neighborhoods' }[group];
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
    tag.className = 'ldna-tag';
    tag.dataset.value = val;
    var labelNode = document.createTextNode(val + ' ');
    var removeBtn = document.createElement('span');
    removeBtn.className = 'ldna-tag-remove';
    removeBtn.textContent = '×';
    removeBtn.setAttribute('onclick', 'ldnaRemoveTag(this,\'' + group.replace(/'/g, "\\'") + '\')');
    tag.appendChild(labelNode);
    tag.appendChild(removeBtn);
    if (input) { wrap.insertBefore(tag, input); } else { wrap.appendChild(tag); }
    ldnaSerialize();
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

    /* For ZIP codes: geocode and pan the map to show that area */
    if (group === 'zips' && ldnaMap && typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
      var geocoder = new google.maps.Geocoder();
      geocoder.geocode(
        { address: val + ', USA', componentRestrictions: { country: 'us' } },
        function (results, status) {
          if (status === 'OK' && results.length && ldnaMap) {
            if (results[0].geometry && results[0].geometry.viewport) {
              ldnaMap.fitBounds(results[0].geometry.viewport);
            } else if (results[0].geometry && results[0].geometry.location) {
              ldnaMap.setCenter(results[0].geometry.location);
              ldnaMap.setZoom(13);
            }
          }
        }
      );
    }
  };

  window.ldnaRemoveTag = function (btn, group) {
    var tag = btn.parentElement;
    var val = tag.dataset.value;
    var stateKey = ldnaGroupKey(group);
    if (!stateKey) return;
    ldnaState[stateKey] = ldnaState[stateKey].filter(function (v) { return v !== val; });
    tag.remove();
    ldnaSerialize();
  };

  /* ── Google Places Autocomplete for cities ────────────────────────────────── */
  function ldnaInitCitiesAutocomplete() {
    var input = document.getElementById('ldna-cities-input');
    if (!input || input._ldnaACAttached) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    input._ldnaACAttached = true;

    var ac = new google.maps.places.Autocomplete(input, {
      types: ['(cities)'],
      componentRestrictions: { country: 'us' }
    });
    /* Prevent the Enter key from submitting the form */
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
    });

    ac.addListener('place_changed', function () {
      var place = ac.getPlace();
      if (!place || !place.geometry) return;

      /* Build a readable city label: "City, ST" */
      var cityName = place.name || '';
      if (place.address_components) {
        var stateComp = null;
        for (var i = 0; i < place.address_components.length; i++) {
          var types = place.address_components[i].types;
          if (types.indexOf('administrative_area_level_1') !== -1) {
            stateComp = place.address_components[i];
            break;
          }
        }
        if (stateComp) cityName = cityName + ', ' + stateComp.short_name;
      }

      ldnaAddTagValue('cities', cityName || place.name);
      input.value = '';

      /* Pan the map to the selected city */
      if (ldnaMap && place.geometry) {
        if (place.geometry.viewport) {
          ldnaMap.fitBounds(place.geometry.viewport);
        } else {
          ldnaMap.setCenter(place.geometry.location);
          ldnaMap.setZoom(11);
        }
      }
    });
  }

  /* ── Map initialisation ───────────────────────────────────────────────────── */
  function ldnaIsContainerVisible() {
    var container = document.getElementById('{{ $mapPanelId }}');
    if (!container) return false;
    /* getBoundingClientRect returns all-zeros for display:none ancestors */
    var rect = container.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function ldnaInitMap() {
    if (ldnaMapInitialized) return;
    ldnaMapInitialized = true;
    window.ldnaMapReady = true;

    /* Disconnect all pending observers */
    ldnaObservers.forEach(function (obs) { try { obs.disconnect(); } catch (e) {} });
    ldnaObservers = [];

    /* Hide placeholder */
    var ph = document.getElementById('{{ $mapPanelId }}-placeholder');
    if (ph) ph.style.display = 'none';

    var defaultCenter = { lat: 27.9944024, lng: -81.7602544 };
    ldnaMap = new google.maps.Map(document.getElementById('{{ $mapPanelId }}'), {
      zoom: 8,
      center: defaultCenter,
      mapTypeId: 'roadmap',
    });

    ldnaDrawingManager = new google.maps.drawing.DrawingManager({
      drawingMode: null,
      drawingControl: false,
      polygonOptions:  { fillColor: '#0369a1', fillOpacity: 0.15, strokeColor: '#0369a1', strokeWeight: 2, editable: true },
      circleOptions:   { fillColor: '#6b7280', fillOpacity: 0.12, strokeColor: '#6b7280', strokeWeight: 2, editable: true },
    });
    ldnaDrawingManager.setMap(ldnaMap);

    google.maps.event.addListener(ldnaDrawingManager, 'overlaycomplete', function (e) {
      var idx = ldnaOverlays.length;
      var label = (e.type === 'polygon') ? ('Polygon ' + (idx + 1)) : ('Radius ' + (idx + 1));
      ldnaOverlays.push({ type: e.type, overlay: e.overlay, label: label });
      ldnaDrawingManager.setDrawingMode(null);

      if (e.type === 'polygon') {
        google.maps.event.addListener(e.overlay.getPath(), 'set_at', ldnaSerialize);
        google.maps.event.addListener(e.overlay.getPath(), 'insert_at', ldnaSerialize);
      }
      if (e.type === 'circle') {
        google.maps.event.addListener(e.overlay, 'radius_changed', ldnaSerialize);
        google.maps.event.addListener(e.overlay, 'center_changed', ldnaSerialize);
      }

      var li = document.createElement('li');
      li.id = 'ldna-overlay-label-' + idx;
      var icon = e.type === 'polygon' ? 'fa-draw-polygon text-primary' : 'fa-circle-dot text-secondary';
      li.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + label;
      var overlayList = document.getElementById('ldna-overlay-list');
      if (overlayList) overlayList.appendChild(li);

      ldnaSerialize();
    });

    /* Re-render saved polygons */
    ldnaState.polygons.forEach(function (poly) {
      if (!poly.path || !poly.path.length) return;
      var gmPoly = new google.maps.Polygon({
        paths: poly.path,
        fillColor: '#0369a1', fillOpacity: 0.15,
        strokeColor: '#0369a1', strokeWeight: 2,
        editable: true, map: ldnaMap,
      });
      ldnaOverlays.push({ type: 'polygon', overlay: gmPoly, label: poly.label });
      google.maps.event.addListener(gmPoly.getPath(), 'set_at', ldnaSerialize);
      google.maps.event.addListener(gmPoly.getPath(), 'insert_at', ldnaSerialize);
    });

    /* Re-render saved radius circles */
    ldnaState.radius_searches.forEach(function (r) {
      var centerLat = (r.lat !== undefined) ? parseFloat(r.lat)
                    : (r.center ? parseFloat(r.center.lat) : null);
      var centerLng = (r.lng !== undefined) ? parseFloat(r.lng)
                    : (r.center ? parseFloat(r.center.lng) : null);
      if (centerLat === null || centerLng === null || isNaN(centerLat) || isNaN(centerLng)) return;

      var displayLabel = r.address
        ? (r.address + ' (' + r.radius_miles + ' mi)')
        : (r.label || ('Radius ' + (ldnaOverlays.length + 1)));

      var gmCircle = new google.maps.Circle({
        center: { lat: centerLat, lng: centerLng },
        radius: (parseFloat(r.radius_miles) || 5) * 1609.34,
        fillColor: '#6b7280', fillOpacity: 0.12,
        strokeColor: '#6b7280', strokeWeight: 2,
        editable: true, map: ldnaMap,
      });
      ldnaOverlays.push({ type: 'radius_search', overlay: gmCircle, label: displayLabel, data: r });
      google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
      google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);
    });

    /* Bias viewport */
    if (ldnaOverlays.length > 0) {
      for (var oi = 0; oi < ldnaOverlays.length; oi++) {
        var ov = ldnaOverlays[oi];
        if ((ov.type === 'radius_search' || ov.type === 'circle') && ov.overlay.getBounds) {
          ldnaMap.fitBounds(ov.overlay.getBounds());
          break;
        }
      }
    } else {
      var biasTarget = (ldnaState.cities && ldnaState.cities[0])
                    || (ldnaState.zip_codes && ldnaState.zip_codes[0])
                    || null;
      if (biasTarget) {
        var biasGeocoder = new google.maps.Geocoder();
        biasGeocoder.geocode(
          { address: biasTarget + ', United States', componentRestrictions: { country: 'us' } },
          function (results, status) {
            if (status === 'OK' && results.length && ldnaMap) {
              if (results[0].geometry.viewport) {
                ldnaMap.fitBounds(results[0].geometry.viewport);
              } else {
                ldnaMap.setCenter(results[0].geometry.location);
                ldnaMap.setZoom(10);
              }
            }
          }
        );
      }
    }

    /* Wire up Places Autocomplete for cities now that Maps API is confirmed ready */
    ldnaInitCitiesAutocomplete();

    /* Wire up Places Autocomplete for the radius-search address input */
    ldnaInitRadiusAutocomplete();
  }

  /* ── Places Autocomplete for the radius address input ─────────────────────── */
  function ldnaInitRadiusAutocomplete() {
    var input = document.getElementById('ldna-radius-address');
    if (!input || input._ldnaACAttached) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    input._ldnaACAttached = true;
    new google.maps.places.Autocomplete(input, {
      componentRestrictions: { country: 'us' }
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
    });
  }

  /* ── Public helpers ───────────────────────────────────────────────────────── */
  window.ldnaSetDrawMode = function (mode) {
    if (!ldnaDrawingManager) {
      /* Map not initialised yet — try to init then retry */
      ldnaRequestInit();
      setTimeout(function () { window.ldnaSetDrawMode(mode); }, 500);
      return;
    }
    ldnaDrawingManager.setDrawingMode(
      mode === 'polygon'
        ? google.maps.drawing.OverlayType.POLYGON
        : google.maps.drawing.OverlayType.CIRCLE
    );
  };

  window.ldnaClearAllOverlays = function () {
    ldnaOverlays.forEach(function (item) { item.overlay.setMap(null); });
    ldnaOverlays = [];
    var ol = document.getElementById('ldna-overlay-list');
    if (ol) ol.innerHTML = '';
    ldnaSerialize();
  };

  window.ldnaAddRadiusSearch = function () {
    var addressInput = document.getElementById('ldna-radius-address');
    var milesInput   = document.getElementById('ldna-radius-miles');
    if (!addressInput || !milesInput) return;
    var address = addressInput.value.trim();
    var miles   = parseFloat(milesInput.value) || 5;
    if (!address) return;

    if (!ldnaMap) {
      ldnaRequestInit();
      setTimeout(function () { window.ldnaAddRadiusSearch(); }, 500);
      return;
    }

    var geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: address, componentRestrictions: { country: 'us' } }, function (results, status) {
      if (status !== 'OK' || !results.length) {
        alert('Could not find location: ' + address); return;
      }
      var loc = results[0].geometry.location;
      var lat = loc.lat();
      var lng = loc.lng();
      var displayLabel = address + ' (' + miles + ' mi)';
      var gmCircle = new google.maps.Circle({
        center: { lat: lat, lng: lng },
        radius: miles * 1609.34,
        fillColor: '#6b7280', fillOpacity: 0.12,
        strokeColor: '#6b7280', strokeWeight: 2,
        editable: true, map: ldnaMap,
      });
      var idx = ldnaOverlays.length;
      ldnaOverlays.push({
        type: 'radius_search',
        overlay: gmCircle,
        label: displayLabel,
        data: { address: address, lat: lat, lng: lng, radius_miles: miles }
      });
      google.maps.event.addListener(gmCircle, 'radius_changed', ldnaSerialize);
      google.maps.event.addListener(gmCircle, 'center_changed', ldnaSerialize);

      var li = document.createElement('li');
      li.id = 'ldna-overlay-label-' + idx;
      li.innerHTML = '<i class="fa-solid fa-circle-dot text-secondary"></i> ' + displayLabel;
      var overlayList = document.getElementById('ldna-overlay-list');
      if (overlayList) overlayList.appendChild(li);

      ldnaMap.panTo({ lat: lat, lng: lng });
      ldnaMap.fitBounds(gmCircle.getBounds());
      ldnaSerialize();
      addressInput.value = '';
    });
  };

  /* ── Core initialisation trigger ─────────────────────────────────────────── */
  /* ldnaRequestInit is the primary public entry point — called from:
     (1) the Maps API callback (byoInitBuyerOfferPlaces / byoInitTenantOfferPlaces),
     (2) the shown.bs.tab event listener in the host blade,
     (3) the Next/Back wizard button handlers.
     It is idempotent: safe to call multiple times.                              */
  function ldnaTryInit() {
    if (ldnaMapInitialized) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.drawing) {
      /* Maps API not ready — keep polling at 200 ms intervals */
      setTimeout(ldnaTryInit, 200);
      return;
    }

    if (ldnaIsContainerVisible()) {
      ldnaInitMap();
      return;
    }

    /* Container is hidden (inside inactive tab-pane).
       Strategy 1 — MutationObserver: watch the closest .tab-pane ancestor for
       class changes (Bootstrap adds/removes .show/.active).
       Strategy 2 — ResizeObserver: fired when the element gains layout dimensions.
       Strategy 3 — Polling fallback: catches manual tab switches that toggle
       CSS classes without firing any observer reliably.                         */
    var container = document.getElementById('{{ $mapPanelId }}');
    if (!container) { setTimeout(ldnaTryInit, 200); return; }

    /* MutationObserver on the closest tab-pane ancestor */
    var tabPane = container.closest('.tab-pane') || container.parentElement;
    if (tabPane && !tabPane._ldnaMutObserver) {
      var mutObs = new MutationObserver(function () {
        if (ldnaMapInitialized) { mutObs.disconnect(); return; }
        if (ldnaIsContainerVisible()) { ldnaInitMap(); mutObs.disconnect(); }
      });
      mutObs.observe(tabPane, { attributes: true, attributeFilter: ['class', 'style'] });
      tabPane._ldnaMutObserver = true;
      ldnaObservers.push(mutObs);
    }

    /* ResizeObserver on the map container itself */
    if (!container._ldnaResizeObserver) {
      var resizeObs = new ResizeObserver(function () {
        if (ldnaMapInitialized) { resizeObs.disconnect(); return; }
        if (ldnaIsContainerVisible()) { ldnaInitMap(); resizeObs.disconnect(); }
      });
      resizeObs.observe(container);
      container._ldnaResizeObserver = true;
      ldnaObservers.push(resizeObs);
    }

    /* Fallback poll — stops once initialized */
    setTimeout(ldnaTryInit, 300);
  }

  window.ldnaRequestInit = function () {
    if (typeof google === 'undefined' || !google.maps || !google.maps.drawing) {
      /* Maps API not ready yet — start polling */
      setTimeout(ldnaTryInit, 100);
      return;
    }
    if (!ldnaMapInitialized) {
      ldnaTryInit();
    } else {
      /* Already initialized — re-trigger resize & ensure placeholder is hidden */
      var ph = document.getElementById('{{ $mapPanelId }}-placeholder');
      if (ph) ph.style.display = 'none';
      if (ldnaMap) google.maps.event.trigger(ldnaMap, 'resize');
    }
  };

  /* ── shown.bs.tab hook (for Bootstrap Tab click events) ─────────────────── */
  document.addEventListener('shown.bs.tab', function () {
    if (typeof window.ldnaRequestInit === 'function') window.ldnaRequestInit();
  });

  /* ── Auto-start polling on DOMContentLoaded ───────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ldnaTryInit);
  } else {
    ldnaTryInit();
  }

  /* ── Wrap-click focuses the text input ────────────────────────────────────── */
  document.querySelectorAll('.ldna-tag-input-wrap').forEach(function (wrap) {
    wrap.addEventListener('click', function () {
      var inp = wrap.querySelector('.ldna-tag-input');
      if (inp) inp.focus();
    });
  });
})();
</script>
