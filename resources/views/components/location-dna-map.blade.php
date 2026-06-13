{{--
  Location DNA Map Display Component
  ====================================
  Usage:  <x-location-dna-map :preferences="$locationDnaPreferences" :legacyLocation="$legacyLocation" />

  Props:
    $preferences   – decoded array from `location_dna_preferences` meta key, or null
    $legacyLocation – array with keys: cities[], counties[], states[], zip_codes[]
                      pulled from the older separate meta fields

  Priority chain — STRICTLY one tier renders; the first non-empty tier wins:
    1. Custom drawn polygons   → Google Maps Polygon overlays
    2. Radius circles          → Google Maps Circle overlays
    3. City labels/chips       → Phase 1 chip adapter (no GeoJSON)
    4. ZIP code labels/chips   → same
    5. County labels/chips     → same
    6. Text-only fallback      → when tiers 1-5 all empty

  Neighborhoods are supplemental text — they are NOT a tier trigger.
  They appear alongside whichever tier renders, or in the fallback block.

  Phase 1 boundary adapter:
    City / ZIP / county values render as labeled text chips (NOT GeoJSON polygons).
    The adapter interface is isolated at tiers 3-5 so a future phase can wire in
    real GeoJSON boundary data without touching the rest of this component.

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
    var polygons  = @json($polygons);
    var radii     = @json($radii);
    var tier      = @json($tier);
    var mapElId   = @json($componentId);

    /* XSS-safe label helper — uses textContent, never innerHTML */
    function safeLabel(text) {
      var strong = document.createElement('strong');
      strong.textContent = String(text || '');
      return strong.outerHTML;
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
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initLdnaDisplay);
    } else {
      initLdnaDisplay();
    }
  })();
  </script>

  {{-- Ensure Maps API is available on public view pages --}}
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

@else
  {{-- ─── Tiers 3-5: Chip-based boundary adapter (Phase 1) ────────────────────────
       STRICT single-tier: only the winning tier's chips are shown.
       Neighborhoods are supplemental and always shown at the bottom.
       Interface contract: only chips for $tier are rendered in the main section.
       Future phases can replace this chip display with a GeoJSON polygon renderer.  --}}
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
        <small class="text-muted ms-auto" style="font-size:.75rem;">
          <i class="fa-solid fa-circle-info"></i> Map boundary outlines coming soon
        </small>
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
