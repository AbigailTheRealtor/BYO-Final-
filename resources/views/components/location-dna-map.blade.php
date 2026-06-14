{{--
  Location DNA Map Display Component
  ====================================
  Usage:  <x-location-dna-map :preferences="$locationDnaPreferences" :legacyLocation="$legacyLocation" :boundaryData="$boundaryData ?? null" :floodZoneData="$floodZoneData ?? null" :schoolDistrictData="$schoolDistrictData ?? null" />

  Props:
    $preferences   – decoded array from `location_dna_preferences` meta key, or null
    $legacyLocation – array with keys: cities[], counties[], states[], zip_codes[]
                      pulled from the older separate meta fields
    $boundaryData  – optional; resolved by BoundaryLookupService in the controller:
                       ['geojson_polygons' => [...], 'fallback' => bool]
                     When absent or fallback=true, chip display is used for Tiers 3-5.
    $floodZoneData – optional; resolved by FloodZoneLookupService in the controller:
                       ['flood_zones' => [...], 'available' => bool]
                     When absent or available=false, no flood overlay is rendered.
    $schoolDistrictData – optional; resolved by SchoolDistrictLookupService in the controller:
                       ['school_districts' => [...], 'available' => bool]
                     When absent or available=false, no school district overlay is rendered.

  Priority chain — STRICTLY one tier renders; the first non-empty tier wins:
    1. Custom drawn polygons   → Google Maps Polygon overlays
    2. Radius circles          → Google Maps Circle overlays
    3. City labels/chips       → GeoJSON boundary polygons (or chip fallback)
    4. ZIP code labels/chips   → GeoJSON boundary polygons (or chip fallback)
    5. County labels/chips     → GeoJSON boundary polygons (or chip fallback)
    6. Text-only fallback      → when tiers 1-5 all empty

  Neighborhoods are supplemental text — they are NOT a tier trigger.
  They appear alongside whichever tier renders, or in the fallback block.

  Phase 2 boundary adapter (Tiers 3-5):
    BoundaryLookupService fetches GeoJSON polygon coordinates from the Census TIGER/Line
    REST API and passes them as $boundaryData. When the API returns no data (unknown area,
    network error, timeout), $boundaryData['fallback'] is true and the chip display renders
    unchanged. This ensures the map never goes blank.

  Phase 3A flood zone overlay:
    FloodZoneLookupService fetches FEMA NFHL flood zone polygons and passes them as
    $floodZoneData. When unavailable (timeout, no data, area too large), the map renders
    normally without the overlay. Flood zones are rendered as a second pass on top of
    existing overlays and do NOT affect fitBounds.

  Phase 3B school district overlay:
    SchoolDistrictLookupService fetches Census TIGER/Line school district polygons and
    passes them as $schoolDistrictData. Rendered as a third pass BELOW flood zones by
    zIndex. Does NOT affect fitBounds. When unavailable, map renders normally.

  XSS:
    All user-supplied label content rendered via JavaScript uses textContent, not
    innerHTML, to prevent stored XSS on the public view page.

  Never renders a blank empty map div.
--}}

@php
  $prefs       = is_array($preferences)    ? $preferences    : [];
  $legacy      = is_array($legacyLocation) ? $legacyLocation : [];

  $polygons    = $prefs['polygons']        ?? [];
  $radii       = $prefs['radius_searches'] ?? [];
  $dnaCities   = $prefs['cities']          ?? [];
  $dnaZips     = $prefs['zip_codes']       ?? [];
  $dnaNeigh    = $prefs['neighborhoods']   ?? [];
  $flex        = $prefs['flexible_location'] ?? false;
  $notes       = $prefs['location_notes']  ?? '';

  /* Legacy location fields as fallback sources */
  $legCities   = array_values(array_filter((array)($legacy['cities']  ?? [])));
  $legCounties = array_values(array_filter((array)($legacy['counties'] ?? [])));
  $legZips     = array_values(array_filter((array)($legacy['zip_codes'] ?? [])));

  /* Merge DNA cities/zips with legacy for chip display */
  $allCities   = array_values(array_unique(array_merge($dnaCities, $legCities)));
  $allZips     = array_values(array_unique(array_merge($dnaZips, $legZips)));
  $allCounties = array_values(array_filter(array_unique($legCounties)));

  /* ── Priority chain triggers (neighborhoods are NOT in the chain) ───────── */
  $hasPolygons = count($polygons) > 0;
  $hasRadii    = count($radii) > 0;
  $hasCities   = count($allCities) > 0;
  $hasZips     = count($allZips) > 0;
  $hasCounties = count($allCounties) > 0;

  /* hasMapData is true only if at least one chain tier has data */
  $hasMapData  = $hasPolygons || $hasRadii || $hasCities || $hasZips || $hasCounties;

  /* Determine active tier (strict: only one tier renders) */
  if ($hasPolygons)       $tier = 'polygons';
  elseif ($hasRadii)      $tier = 'radii';
  elseif ($hasCities)     $tier = 'cities';
  elseif ($hasZips)       $tier = 'zips';
  elseif ($hasCounties)   $tier = 'counties';
  else                    $tier = 'fallback';

  /* Boundary polygon data from Phase 2 service (Tiers 3-5 only) */
  $bd = isset($boundaryData) && is_array($boundaryData) ? $boundaryData : null;
  $geoPolygons   = ($bd && !empty($bd['geojson_polygons'])) ? $bd['geojson_polygons'] : [];
  $useBoundaryMap = !empty($geoPolygons)
                    && in_array($tier, ['cities', 'zips', 'counties'], true);

  /* ── Phase 3A: Flood zone overlay data ───────────────────────────────────── */
  $fzd          = isset($floodZoneData) && is_array($floodZoneData) ? $floodZoneData : null;
  $floodZones   = ($fzd && !empty($fzd['available']) && !empty($fzd['flood_zones']))
                    ? $fzd['flood_zones']
                    : [];
  $hasFloodZones = !empty($floodZones)
                   && ($tier === 'polygons' || $tier === 'radii' || $useBoundaryMap);

  /* Unique zone designations for the legend (stable sorted order) */
  $floodZoneLegend = [];
  if ($hasFloodZones) {
      foreach ($floodZones as $fz) {
          $zd = $fz['zone_designation'] ?? '';
          if ($zd !== '' && !in_array($zd, $floodZoneLegend, true)) {
              $floodZoneLegend[] = $zd;
          }
      }
      sort($floodZoneLegend);
  }

  /* ── Phase 3B: School district overlay data ──────────────────────────────── */
  $sdd              = isset($schoolDistrictData) && is_array($schoolDistrictData) ? $schoolDistrictData : null;
  $schoolDistricts  = ($sdd && !empty($sdd['available']) && !empty($sdd['school_districts']))
                        ? $sdd['school_districts']
                        : [];
  $hasSchoolDistricts = !empty($schoolDistricts)
                        && ($tier === 'polygons' || $tier === 'radii' || $useBoundaryMap);

  /* Unique district names for the legend (insertion order, then deduped) */
  $schoolDistrictLegend = [];
  if ($hasSchoolDistricts) {
      foreach ($schoolDistricts as $sd) {
          $dn = $sd['district_name'] ?? '';
          if ($dn !== '' && !in_array($dn, $schoolDistrictLegend, true)) {
              $schoolDistrictLegend[] = $dn;
          }
      }
  }

  $componentId = 'ldna-display-' . uniqid();
  $mapsKey     = config('services.google.places_key') ?: env('GOOGLE_PLACES_API_KEY', '');
@endphp

@push('styles')
<style>
  .ldna-hero { border-radius: 10px; overflow: hidden; margin-bottom: 1.5rem; }
  .ldna-hero-map { width: 100%; height: 360px; border-radius: 8px;
    border: 1px solid #e2e8f0; background: #f8fafc; }
  .ldna-chip-map-wrap { border-radius: 8px; border: 1px solid #e2e8f0;
    background: linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);
    padding: 1.5rem; min-height: 200px; }
  .ldna-chip-map-title { font-size: .82rem; font-weight: 600; color: #0369a1;
    text-transform: uppercase; letter-spacing: .04em; margin-bottom: .75rem; }
  .ldna-area-chip { display: inline-flex; align-items: center; gap: .35rem;
    border-radius: 20px; padding: .3rem .85rem; font-size: .85rem; font-weight: 600;
    margin: .2rem; border: 1px solid transparent; }
  .ldna-area-chip.city   { background:#dbeafe; color:#1d4ed8; border-color:#93c5fd; }
  .ldna-area-chip.zip    { background:#dcfce7; color:#15803d; border-color:#86efac; }
  .ldna-area-chip.county { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
  .ldna-area-chip.neigh  { background:#f3e8ff; color:#6b21a8; border-color:#d8b4fe; }
  .ldna-fallback-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;
    background: #f8fafc; color: #64748b; }
  .ldna-flex-badge { display:inline-flex;align-items:center;gap:.3rem;
    background:#d1fae5;color:#065f46;border-radius:20px;
    padding:.2rem .75rem;font-size:.8rem;font-weight:600;margin-bottom:.5rem; }
  .ldna-notes-box { font-size:.88rem;color:#374151;margin-top:.5rem;
    border-left:3px solid #0ea5e9;padding-left:.75rem; }
  .ldna-neigh-supplement { margin-top:.75rem; padding-top:.75rem;
    border-top: 1px solid #cbd5e1; }
  /* Flood zone legend */
  .ldna-flood-legend { display:flex; flex-wrap:wrap; gap:.4rem;
    align-items:center; padding:.6rem .85rem; margin-top:.5rem;
    background:#fff8f1; border:1px solid #fed7aa; border-radius:6px;
    font-size:.8rem; }
  .ldna-flood-legend-title { font-weight:700; color:#9a3412;
    text-transform:uppercase; letter-spacing:.04em; margin-right:.3rem; flex-shrink:0; }
  .ldna-flood-chip { display:inline-flex; align-items:center; gap:.28rem;
    border-radius:12px; padding:.18rem .65rem; font-size:.78rem; font-weight:600;
    border:1px solid transparent; white-space:nowrap; }
  /* School district legend */
  .ldna-school-district-legend { display:flex; flex-wrap:wrap; gap:.4rem;
    align-items:center; padding:.6rem .85rem; margin-top:.5rem;
    background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px;
    font-size:.8rem; }
  .ldna-school-district-legend-title { font-weight:700; color:#1e40af;
    text-transform:uppercase; letter-spacing:.04em; margin-right:.3rem; flex-shrink:0; }
  .ldna-school-district-chip { display:inline-flex; align-items:center; gap:.28rem;
    border-radius:12px; padding:.18rem .65rem; font-size:.78rem; font-weight:600;
    background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; white-space:nowrap; }
</style>
@endpush

@if(!$hasMapData)
  {{-- ─── Tier 6: Full fallback ────────────────────────────────────────────────── --}}
  <div class="ldna-fallback-box mb-3">
    <div class="fw-bold mb-2" style="color:#0369a1;">
      <i class="fa-solid fa-map-location-dot me-1"></i> Location Preferences
    </div>
    @php
      $fallbackItems = [];
      if ($legCities)   $fallbackItems[] = 'Cities: '   . implode(', ', $legCities);
      if ($legCounties) $fallbackItems[] = 'Counties: ' . implode(', ', $legCounties);
      $legStates = array_values(array_filter((array)($legacy['states'] ?? [])));
      if ($legStates)   $fallbackItems[] = 'States: '   . implode(', ', $legStates);
      if ($dnaNeigh)    $fallbackItems[] = 'Neighborhoods: ' . implode(', ', $dnaNeigh);
      if ($notes)       $fallbackItems[] = 'Notes: '    . $notes;
    @endphp
    @if(count($fallbackItems))
      <ul style="margin:0;padding-left:1.2rem;">
        @foreach($fallbackItems as $fb)
          <li>{{ $fb }}</li>
        @endforeach
      </ul>
    @else
      <p class="mb-0">No location preferences have been specified for this listing.</p>
    @endif
  </div>

@elseif($tier === 'polygons' || $tier === 'radii')
  {{-- ─── Tiers 1 & 2: Real map with drawn overlays ────────────────────────────── --}}
  <div class="ldna-hero mb-3">
    @if($flex)
      <span class="ldna-flex-badge mb-2">
        <i class="fa-solid fa-arrows-left-right"></i> Location Flexible
      </span>
    @endif
    <div id="{{ $componentId }}" class="ldna-hero-map"></div>

    @if($hasFloodZones)
      @include('components.location-dna-flood-legend', ['floodZoneLegend' => $floodZoneLegend])
    @endif

    @if($hasSchoolDistricts)
      @include('components.location-dna-school-district-legend', ['schoolDistrictLegend' => $schoolDistrictLegend])
    @endif

    @if($dnaNeigh)
      <div class="ldna-neigh-supplement">
        <span style="font-size:.78rem;color:#64748b;font-weight:600;">NEIGHBORHOODS</span><br>
        @foreach($dnaNeigh as $n)
          <span class="ldna-area-chip neigh">
            <i class="fa-solid fa-map-pin" style="font-size:.75rem;"></i> {{ $n }}
          </span>
        @endforeach
      </div>
    @endif
    @if($notes)
      <div class="ldna-notes-box mt-2">
        <i class="fa-solid fa-note-sticky me-1 text-info"></i> {{ $notes }}
      </div>
    @endif
  </div>

  <script>
  (function () {
    var polygons        = @json($polygons);
    var radii           = @json($radii);
    var tier            = @json($tier);
    var mapElId         = @json($componentId);
    var floodZones      = @json($floodZones);
    var schoolDistricts = @json($schoolDistricts);

    /* XSS-safe label helper — uses textContent, never innerHTML */
    function safeLabel(text) {
      var strong = document.createElement('strong');
      strong.textContent = String(text || '');
      return strong.outerHTML;
    }

    /* Return Google Maps style config for a FEMA flood zone designation */
    function floodZoneStyle(zone) {
      var z = String(zone).toUpperCase();
      if (z === 'X' || z.charAt(0) === 'X') {
        return { fillColor: '#16a34a', fillOpacity: 0.15, strokeColor: '#15803d', strokeWeight: 1 };
      }
      if (z === 'VE' || z === 'V' || (z.length > 1 && z.charAt(0) === 'V')) {
        return { fillColor: '#dc2626', fillOpacity: 0.35, strokeColor: '#b91c1c', strokeWeight: 1 };
      }
      if (z.charAt(0) === 'A') {
        return { fillColor: '#f97316', fillOpacity: 0.30, strokeColor: '#ea580c', strokeWeight: 1 };
      }
      return { fillColor: '#94a3b8', fillOpacity: 0.20, strokeColor: '#64748b', strokeWeight: 1 };
    }

    function ringToPath(ring) {
      return ring.map(function (pt) { return { lat: pt[1], lng: pt[0] }; });
    }

    function renderFloodZones(gMap) {
      if (!floodZones || !floodZones.length) return;
      floodZones.forEach(function (fz) {
        var style = floodZoneStyle(fz.zone_designation);
        var paths = (fz.rings || []).map(function (ring) { return ringToPath(ring); });
        if (!paths.length) return;
        new google.maps.Polygon({
          paths: paths,
          fillColor:    style.fillColor,
          fillOpacity:  style.fillOpacity,
          strokeColor:  style.strokeColor,
          strokeWeight: style.strokeWeight,
          map: gMap,
          zIndex: 10,
        });
      });
    }

    /* Phase 3B: render school district outlines — does NOT affect fitBounds.
     * Rendered below flood zones (zIndex 5 < 10). */
    function renderSchoolDistricts(gMap) {
      if (!schoolDistricts || !schoolDistricts.length) return;
      schoolDistricts.forEach(function (sd) {
        var paths = (sd.rings || []).map(function (ring) { return ringToPath(ring); });
        if (!paths.length) return;
        new google.maps.Polygon({
          paths:        paths,
          fillColor:    '#1d4ed8',
          fillOpacity:  0.05,
          strokeColor:  '#1d4ed8',
          strokeWeight: 1.5,
          strokeOpacity: 0.7,
          map:          gMap,
          zIndex:       5,
        });
      });
    }

    function initLdnaDisplay() {
      if (typeof google === 'undefined' || !google.maps) {
        setTimeout(initLdnaDisplay, 200); return;
      }

      var mapEl = document.getElementById(mapElId);
      if (!mapEl) return;

      var bounds    = new google.maps.LatLngBounds();
      var hasBounds = false;
      var gMap = new google.maps.Map(mapEl, {
        zoom: 8,
        center: { lat: 27.9944024, lng: -81.7602544 },
        mapTypeId: 'roadmap',
        disableDefaultUI: true,
        zoomControl: true,
      });

      if (tier === 'polygons') {
        polygons.forEach(function (poly) {
          if (!poly.path || !poly.path.length) return;
          var gmPoly = new google.maps.Polygon({
            paths: poly.path,
            fillColor: '#0369a1', fillOpacity: 0.18,
            strokeColor: '#0369a1', strokeWeight: 2,
            map: gMap,
          });
          poly.path.forEach(function (pt) {
            bounds.extend(new google.maps.LatLng(pt.lat, pt.lng));
            hasBounds = true;
          });
          if (poly.label) {
            var center = poly.path.reduce(function (acc, pt) {
              return { lat: acc.lat + pt.lat / poly.path.length, lng: acc.lng + pt.lng / poly.path.length };
            }, { lat: 0, lng: 0 });
            var iw = new google.maps.InfoWindow({
              content: safeLabel(poly.label),
              position: new google.maps.LatLng(center.lat, center.lng),
            });
            iw.open(gMap);
          }
        });
      }

      if (tier === 'radii') {
        radii.forEach(function (r) {
          if (!r.center) return;
          var gmCircle = new google.maps.Circle({
            center: r.center,
            radius: r.radius_miles * 1609.34,
            fillColor: '#6b7280', fillOpacity: 0.15,
            strokeColor: '#6b7280', strokeWeight: 2,
            map: gMap,
          });
          bounds.union(gmCircle.getBounds());
          hasBounds = true;
          if (r.label) {
            var iw2 = new google.maps.InfoWindow({
              content: safeLabel(r.label),
              position: new google.maps.LatLng(r.center.lat, r.center.lng),
            });
            iw2.open(gMap);
          }
        });
      }

      if (hasBounds) { gMap.fitBounds(bounds); }

      /* Phase 3A: render flood zones on top — does NOT affect fitBounds */
      renderFloodZones(gMap);
      /* Phase 3B: render school district outlines below flood zones — does NOT affect fitBounds */
      renderSchoolDistricts(gMap);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initLdnaDisplay);
    } else {
      initLdnaDisplay();
    }
  })();
  </script>

@elseif($useBoundaryMap)
  {{-- ─── Tiers 3-5: GeoJSON boundary polygon map (Phase 2) ───────────────────────
       Renders a full Google Maps canvas with polygon outlines from Census TIGER data.
       Falls back to chip display when $boundaryData['fallback'] is true (handled by
       the $useBoundaryMap flag above — this block only runs when polygons are available).
       Phase 3A flood zone overlay is rendered as a second pass on top of boundaries.
       Supplemental neighborhoods and notes continue to appear below the map.          --}}
  <div class="ldna-hero mb-3">
    @if($flex)
      <span class="ldna-flex-badge mb-2">
        <i class="fa-solid fa-arrows-left-right"></i> Location Flexible
      </span>
    @endif
    <div id="{{ $componentId }}" class="ldna-hero-map"></div>

    @if($hasFloodZones)
      @include('components.location-dna-flood-legend', ['floodZoneLegend' => $floodZoneLegend])
    @endif

    @if($hasSchoolDistricts)
      @include('components.location-dna-school-district-legend', ['schoolDistrictLegend' => $schoolDistrictLegend])
    @endif

    @if($dnaNeigh)
      <div class="ldna-neigh-supplement">
        <span style="font-size:.78rem;color:#64748b;font-weight:600;">NEIGHBORHOODS</span><br>
        @foreach($dnaNeigh as $n)
          <span class="ldna-area-chip neigh">
            <i class="fa-solid fa-map-pin" style="font-size:.75rem;"></i> {{ $n }}
          </span>
        @endforeach
      </div>
    @endif

    @if($notes)
      <div class="ldna-notes-box mt-2">
        <i class="fa-solid fa-note-sticky me-1 text-info"></i> {{ $notes }}
      </div>
    @endif
  </div>

  <script>
  (function () {
    /*
     * Data structure (matches CensusTigerBoundaryAdapter::extractCoordinates()):
     *
     *   geoRings — array indexed by boundary name (one entry per city/ZIP/county)
     *     Each entry is an array of polygons:
     *       Polygon      → [ [exterior_ring, hole_ring?, ...] ]      (one polygon)
     *       MultiPolygon → [ [exterior1, hole1?], [exterior2], ... ] (N polygons)
     *
     *   A ring is an array of GeoJSON [lng, lat] pairs (NOT {lat,lng} — swap on use).
     *
     * We create one google.maps.Polygon per disconnected polygon piece so that
     * islands and exclaves render as separate shapes rather than as holes.
     */
    var geoRings        = @json($geoPolygons);
    var floodZones      = @json($floodZones);
    var schoolDistricts = @json($schoolDistricts);
    var mapElId         = @json($componentId);

    function ringToPath(ring) {
      return ring.map(function (pt) {
        return { lat: pt[1], lng: pt[0] };
      });
    }

    /* Return Google Maps style config for a FEMA flood zone designation */
    function floodZoneStyle(zone) {
      var z = String(zone).toUpperCase();
      if (z === 'X' || z.charAt(0) === 'X') {
        return { fillColor: '#16a34a', fillOpacity: 0.15, strokeColor: '#15803d', strokeWeight: 1 };
      }
      if (z === 'VE' || z === 'V' || (z.length > 1 && z.charAt(0) === 'V')) {
        return { fillColor: '#dc2626', fillOpacity: 0.35, strokeColor: '#b91c1c', strokeWeight: 1 };
      }
      if (z.charAt(0) === 'A') {
        return { fillColor: '#f97316', fillOpacity: 0.30, strokeColor: '#ea580c', strokeWeight: 1 };
      }
      return { fillColor: '#94a3b8', fillOpacity: 0.20, strokeColor: '#64748b', strokeWeight: 1 };
    }

    function renderFloodZones(gMap) {
      if (!floodZones || !floodZones.length) return;
      floodZones.forEach(function (fz) {
        var style = floodZoneStyle(fz.zone_designation);
        var paths = (fz.rings || []).map(function (ring) { return ringToPath(ring); });
        if (!paths.length) return;
        new google.maps.Polygon({
          paths: paths,
          fillColor:    style.fillColor,
          fillOpacity:  style.fillOpacity,
          strokeColor:  style.strokeColor,
          strokeWeight: style.strokeWeight,
          map: gMap,
          zIndex: 10,
        });
      });
    }

    /* Phase 3B: render school district outlines — does NOT affect fitBounds.
     * Rendered below flood zones (zIndex 5 < 10). */
    function renderSchoolDistricts(gMap) {
      if (!schoolDistricts || !schoolDistricts.length) return;
      schoolDistricts.forEach(function (sd) {
        var paths = (sd.rings || []).map(function (ring) { return ringToPath(ring); });
        if (!paths.length) return;
        new google.maps.Polygon({
          paths:         paths,
          fillColor:     '#1d4ed8',
          fillOpacity:   0.05,
          strokeColor:   '#1d4ed8',
          strokeWeight:  1.5,
          strokeOpacity: 0.7,
          map:           gMap,
          zIndex:        5,
        });
      });
    }

    function initLdnaBoundaryMap() {
      if (typeof google === 'undefined' || !google.maps) {
        setTimeout(initLdnaBoundaryMap, 200); return;
      }

      var mapEl = document.getElementById(mapElId);
      if (!mapEl) return;

      var bounds    = new google.maps.LatLngBounds();
      var hasBounds = false;

      var gMap = new google.maps.Map(mapEl, {
        zoom: 8,
        center: { lat: 27.9944024, lng: -81.7602544 },
        mapTypeId: 'roadmap',
        disableDefaultUI: true,
        zoomControl: true,
      });

      /* Outer loop: each boundary name (city, ZIP, or county) */
      geoRings.forEach(function (polygons) {
        if (!polygons || !polygons.length) return;

        /* Inner loop: each disconnected polygon piece within that boundary.
         * For a simple Polygon this is always length 1.
         * For a MultiPolygon (county with islands, etc.) this is N separate shapes. */
        polygons.forEach(function (rings) {
          if (!rings || !rings.length) return;

          /* rings[0] = exterior ring; rings[1..] = interior holes (GeoJSON convention).
           * Google Maps Polygon with multiple paths uses the same convention,
           * so we pass the rings array directly as paths. */
          var paths = rings.map(function (ring) { return ringToPath(ring); });

          new google.maps.Polygon({
            paths: paths,
            fillColor: '#0369a1',
            fillOpacity: 0.18,
            strokeColor: '#0369a1',
            strokeWeight: 2,
            map: gMap,
          });

          /* Extend bounds using only the exterior ring (rings[0]) to avoid
           * hole coordinates skewing the fit. */
          var exterior = paths[0] || [];
          exterior.forEach(function (pt) {
            bounds.extend(new google.maps.LatLng(pt.lat, pt.lng));
            hasBounds = true;
          });
        });
      });

      if (hasBounds) {
        gMap.fitBounds(bounds);
      }

      /* Phase 3A: render flood zones on top — does NOT affect fitBounds */
      renderFloodZones(gMap);
      /* Phase 3B: render school district outlines below flood zones — does NOT affect fitBounds */
      renderSchoolDistricts(gMap);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initLdnaBoundaryMap);
    } else {
      initLdnaBoundaryMap();
    }
  })();
  </script>

@else
  {{-- ─── Tiers 3-5: Chip-based boundary adapter (Phase 1 fallback) ───────────────
       Renders when $boundaryData is absent, fallback=true, or boundary polygons are
       empty (network failure, unknown area, Census API timeout, etc.).
       STRICT single-tier: only the winning tier's chips are shown.
       Neighborhoods are supplemental and always shown at the bottom.                 --}}
  <div class="ldna-hero mb-3">
    @if($flex)
      <span class="ldna-flex-badge">
        <i class="fa-solid fa-arrows-left-right"></i> Location Flexible
      </span>
    @endif

    <div class="ldna-chip-map-wrap">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-map-location-dot" style="color:#0369a1;font-size:1.25rem;"></i>
        <span class="ldna-chip-map-title">Preferred Locations</span>
      </div>

      {{-- ── Active tier only ── --}}
      @if($tier === 'cities')
        <div class="mb-2">
          <span style="font-size:.78rem;color:#64748b;font-weight:600;">CITIES</span><br>
          @foreach($allCities as $city)
            <span class="ldna-area-chip city">
              <i class="fa-solid fa-city" style="font-size:.75rem;"></i> {{ $city }}
            </span>
          @endforeach
        </div>
      @endif

      @if($tier === 'zips')
        <div class="mb-2">
          <span style="font-size:.78rem;color:#64748b;font-weight:600;">ZIP CODES</span><br>
          @foreach($allZips as $zip)
            <span class="ldna-area-chip zip">
              <i class="fa-solid fa-hashtag" style="font-size:.75rem;"></i> {{ $zip }}
            </span>
          @endforeach
        </div>
      @endif

      @if($tier === 'counties')
        <div class="mb-2">
          <span style="font-size:.78rem;color:#64748b;font-weight:600;">COUNTIES</span><br>
          @foreach($allCounties as $county)
            <span class="ldna-area-chip county">
              <i class="fa-solid fa-tree-city" style="font-size:.75rem;"></i> {{ $county }}
            </span>
          @endforeach
        </div>
      @endif

      {{-- ── Neighborhoods: supplemental, not a tier trigger ── --}}
      @if(count($dnaNeigh))
        <div class="ldna-neigh-supplement">
          <span style="font-size:.78rem;color:#64748b;font-weight:600;">NEIGHBORHOODS</span><br>
          @foreach($dnaNeigh as $n)
            <span class="ldna-area-chip neigh">
              <i class="fa-solid fa-map-pin" style="font-size:.75rem;"></i> {{ $n }}
            </span>
          @endforeach
        </div>
      @endif
    </div>

    @if($notes)
      <div class="ldna-notes-box mt-2">
        <i class="fa-solid fa-note-sticky me-1 text-info"></i> {{ $notes }}
      </div>
    @endif
  </div>
@endif

{{-- ── Maps API loader — one instance regardless of which tier rendered ──────────
     @once ensures this script tag is emitted at most once per page, even when
     multiple location-dna-map components appear on the same view.
     The runtime guard (typeof google check + existing-script check) provides a
     second layer of defence against duplicate network requests.                   --}}
@if($hasMapData && ($tier === 'polygons' || $tier === 'radii' || $useBoundaryMap))
@once
@push('scripts')
<script>
(function () {
  if (typeof google !== 'undefined' && google.maps) return;
  var existing = document.querySelector('script[src*="maps.googleapis.com"]');
  if (existing) return; /* already loading */
  var s = document.createElement('script');
  s.async = true; s.defer = true;
  s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places';
  document.head.appendChild(s);
})();
</script>
@endpush
@endonce
@endif
