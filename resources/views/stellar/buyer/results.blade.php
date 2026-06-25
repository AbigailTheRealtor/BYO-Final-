@extends('layouts.main')

@section('title', 'Matched Listings')

@section('content')
<div class="container-fluid py-4">

    {{-- ===================================================================
         Criteria selector strip
         Shown when results are displayed OR when select_criteria state is active.
         Hidden for no_criteria_listings, import_unavailable, and no_inventory.
    =================================================================== --}}
    @if(!in_array($emptyState, ['import_unavailable', 'no_inventory', 'no_criteria_listings']) && count($criteriaList) > 0)
        <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded border bg-light flex-wrap" style="font-size:.9rem;">
            <i class="fas fa-filter text-secondary flex-shrink-0"></i>
            @if($selectedCriteriaLabel)
                <span class="text-muted me-1">Matching against:</span>
                <span class="fw-semibold text-truncate" style="max-width:260px;" title="{{ $selectedCriteriaLabel }}">
                    {{ $selectedCriteriaLabel }}
                </span>
            @endif

            @if(count($criteriaList) > 1)
                <span class="text-muted">·</span>
                <div class="d-flex align-items-center gap-1">
                    <label for="criteria-switcher" class="text-muted mb-0">Switch profile:</label>
                    <select id="criteria-switcher" class="form-select form-select-sm" style="width:auto;max-width:280px;"
                            onchange="if(this.value) window.location.href = this.value;">
                        <option value="">Choose&hellip;</option>
                        @foreach($criteriaList as $item)
                            <option value="{{ route('stellar.buyer.results', ['criteria_type' => $item['type'], 'criteria_id' => $item['id']]) }}"
                                {{ ($selectedCriteriaType === $item['type'] && $selectedCriteriaId === $item['id']) ? 'selected' : '' }}>
                                {{ $item['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    @endif

    {{-- ===================================================================
         Page header
    =================================================================== --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-0 fw-semibold" style="font-size:1.35rem;">
                <i class="fas fa-search me-2 text-primary"></i>Your Matched Listings
            </h2>
            @if(!$emptyState && isset($total) && $total > 0)
                <small class="text-muted">
                    {{ $total }} listing{{ $total !== 1 ? 's' : '' }} match your criteria &mdash;
                    sorted by <strong>Best Match</strong>
                </small>
            @endif
        </div>
        @if(!empty($mapPins))
        <button class="btn btn-outline-secondary btn-sm" id="stellar-map-toggle"
                onclick="stellarToggleMapView()" title="Toggle map view">
            <i class="fas fa-map me-1" id="stellar-map-icon"></i><span id="stellar-map-label">Map View</span>
        </button>
        @else
        <button class="btn btn-outline-secondary btn-sm" disabled title="No mappable results">
            <i class="fas fa-map me-1"></i>Map View
        </button>
        @endif
    </div>

    {{-- ===================================================================
         Empty states
    =================================================================== --}}
    @if($emptyState === 'import_unavailable')
        <div class="alert alert-info d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-circle-info fa-lg mt-1 text-info flex-shrink-0"></i>
            <div>
                <strong>Listing data is being set up.</strong><br>
                Please check back shortly.
            </div>
        </div>

    @elseif($emptyState === 'no_inventory')
        <div class="alert alert-info d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-circle-info fa-lg mt-1 text-info flex-shrink-0"></i>
            <div>
                <strong>Stellar MLS listing data is not yet available.</strong><br>
                Check back soon &mdash; our data import runs regularly and new listings will appear here shortly.
            </div>
        </div>

    @elseif($emptyState === 'no_criteria_listings')
        {{-- No accessible criteria records of either type --}}
        <div class="alert alert-warning d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-triangle-exclamation fa-lg mt-1 text-warning flex-shrink-0"></i>
            <div>
                <strong>No Buyer or Tenant Criteria listings found.</strong><br>
                To see matched listings you need an active Buyer Criteria or Tenant Criteria profile.
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <a href="{{ $buyerCriteriaAddUrl }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-plus me-1"></i>Create Buyer Criteria
                    </a>
                    <a href="{{ $tenantCriteriaAddUrl }}" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-plus me-1"></i>Create Tenant Criteria
                    </a>
                </div>
            </div>
        </div>

    @elseif($emptyState === 'select_criteria')
        {{-- Multiple criteria profiles — user must choose one --}}
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-semibold">
                    <i class="fas fa-list-check me-2 text-primary"></i>Choose a Criteria Profile
                </h5>
                <small class="text-muted">Select which criteria profile you want to match listings against.</small>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($criteriaList as $item)
                        <a href="{{ route('stellar.buyer.results', ['criteria_type' => $item['type'], 'criteria_id' => $item['id']]) }}"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-4">
                            <i class="fas {{ $item['type'] === 'tenant' ? 'fa-key' : 'fa-house' }} text-primary fa-fw"></i>
                            <div>
                                <div class="fw-semibold">{{ $item['label'] }}</div>
                                <small class="text-muted">
                                    {{ $item['type'] === 'tenant' ? 'Tenant Criteria' : 'Buyer Criteria' }}
                                    &middot; Created {{ \Carbon\Carbon::parse($item['created_at'])->format('M j, Y') }}
                                </small>
                            </div>
                            <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

    @elseif($emptyState === 'no_criteria')
        <div class="alert alert-warning d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-triangle-exclamation fa-lg mt-1 text-warning flex-shrink-0"></i>
            <div>
                @if($selectedCriteriaId)
                    {{-- A specific profile was selected but its data is incomplete --}}
                    <strong>The selected criteria profile isn't complete yet.</strong><br>
                    Make sure the profile has a property type set before running a match.
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        @if($selectedCriteriaEditUrl)
                            <a href="{{ $selectedCriteriaEditUrl }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-sliders me-1"></i>Edit Criteria
                            </a>
                        @endif
                        @if(count($criteriaList) > 1)
                            <a href="{{ route('stellar.buyer.results') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-list me-1"></i>Choose Different Profile
                            </a>
                        @endif
                    </div>
                @else
                    {{-- Buyer with no criteria at all — original behavior preserved --}}
                    <strong>Your buyer profile isn't complete yet.</strong><br>
                    Set up your home criteria to see matched listings.
                    <div class="mt-2">
                        <a href="{{ url('/buyer') }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-sliders me-1"></i>Set Up My Criteria
                        </a>
                    </div>
                @endif
            </div>
        </div>

    @elseif($emptyState === 'no_location')
        <div class="alert alert-warning d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-location-dot fa-lg mt-1 text-warning flex-shrink-0"></i>
            <div>
                <strong>Your search doesn't include a location yet.</strong><br>
                Add preferred cities, ZIP codes, or draw a custom search area to see matched listings.
                <div class="mt-2 d-flex flex-wrap gap-2">
                    @if($selectedCriteriaEditUrl)
                        <a href="{{ $selectedCriteriaEditUrl }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-map-pin me-1"></i>Add Preferred Locations
                        </a>
                    @endif
                    @if(count($criteriaList) > 1)
                        <a href="{{ route('stellar.buyer.results') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-list me-1"></i>Choose Different Profile
                        </a>
                    @endif
                </div>
            </div>
        </div>

    @elseif($emptyState === 'no_matches')
        <div class="alert alert-secondary d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-house-circle-xmark fa-lg mt-1 flex-shrink-0"></i>
            <div>
                <strong>No active listings match your current search criteria.</strong><br>
                Try widening your price range, expanding your location preferences, or relaxing your minimum bedroom or bathroom requirements.
                <div class="mt-2 d-flex flex-wrap gap-2">
                    @if($selectedCriteriaEditUrl)
                        <a href="{{ $selectedCriteriaEditUrl }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-sliders me-1"></i>Edit My Criteria
                        </a>
                    @endif
                    @if(count($criteriaList) > 1)
                        <a href="{{ route('stellar.buyer.results') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-list me-1"></i>Choose Different Profile
                        </a>
                    @endif
                </div>
            </div>
        </div>

    @else
        {{-- Map panel — hidden until user clicks Map View. Initialised lazily on first open. --}}
        <div id="stellar-map-panel" style="display:none; height:480px; border-radius:8px;
             border:1px solid #e2e8f0; margin-bottom:1rem; overflow:hidden;"></div>

        {{-- ===============================================================
             Results grid
        =============================================================== --}}
        <div id="stellar-results-grid">
            <div class="row row-cols-1 row-cols-lg-2 g-3 mb-4">
                @foreach($results as $index => $card)
                    <div class="col">
                        <x-stellar.buyer-result-card
                            :card="$card"
                            :is-top="$index === 0 && $paginator->currentPage() === 1"
                            :criteria-id="$selectedCriteriaId"
                            :criteria-type="$selectedCriteriaType ?? 'buyer'"
                        />
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($paginator->hasPages())
                <div class="d-flex justify-content-center mt-3">
                    {{ $paginator->links() }}
                </div>
            @endif
        </div>
    @endif

</div>

{{-- Accordion toggle helper — defined unconditionally so cards work even when mapPins is empty. --}}
@push('scripts')
<script>
window.sbToggle = function (btn, id) {
    var target = document.getElementById(id);
    if (!target) return;
    var willShow = !target.classList.contains('show');
    bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).toggle();
    btn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    btn.classList.toggle('collapsed', !willShow);
};
</script>
@endpush

@if(!empty($mapPins))
{{-- Load the Maps API only when there are pins to show. --}}
<x-google-maps-script :libraries="''" :callback="'stellarMapsReady'" />

@push('scripts')
<script>
(function () {
    var _mapsLoaded  = false;
    var _mapOpened   = false;
    var _mapInstance = null;
    var _pins        = @json($mapPins);

    /* Called by the Maps API script once it finishes loading. */
    window.stellarMapsReady = function () {
        _mapsLoaded = true;
        /* If the user already clicked Map View before the API finished loading, init now. */
        if (_mapOpened && !_mapInstance) {
            _doInitMap();
        }
    };

    window.stellarToggleMapView = function () {
        var panel  = document.getElementById('stellar-map-panel');
        var grid   = document.getElementById('stellar-results-grid');
        var label  = document.getElementById('stellar-map-label');
        var icon   = document.getElementById('stellar-map-icon');
        var mapVisible = panel.style.display !== 'none';

        if (mapVisible) {
            panel.style.display = 'none';
            grid.style.display  = '';
            label.textContent   = 'Map View';
            icon.className      = 'fas fa-map me-1';
        } else {
            panel.style.display = '';
            grid.style.display  = 'none';
            label.textContent   = 'List View';
            icon.className      = 'fas fa-list me-1';
            _mapOpened = true;
            if (_mapsLoaded && !_mapInstance) {
                _doInitMap();
            }
        }
    };

    function _esc(str) {
        var d = document.createElement('div');
        d.textContent = String(str || '');
        return d.innerHTML;
    }

    function _doInitMap() {
        var el = document.getElementById('stellar-map-panel');
        if (!el) return;

        var bounds = new google.maps.LatLngBounds();
        _mapInstance = new google.maps.Map(el, {
            zoom: 8,
            center: { lat: 27.9944024, lng: -81.7602544 },
            mapTypeId: 'roadmap',
            disableDefaultUI: true,
            zoomControl: true,
            fullscreenControl: true,
        });

        _pins.forEach(function (pin) {
            var pos    = { lat: pin.lat, lng: pin.lng };
            var marker = new google.maps.Marker({ position: pos, map: _mapInstance, title: pin.address || '' });

            var html = '<div style="font-size:.875rem;max-width:220px;line-height:1.4;">'
                + (pin.address    ? '<div style="font-weight:600;">'                          + _esc(pin.address)    + '</div>' : '')
                + (pin.city       ? '<div style="color:#64748b;font-size:.8rem;">'            + _esc(pin.city)       + '</div>' : '')
                + (pin.price_display ? '<div style="color:#15803d;font-weight:600;margin-top:.2rem;">' + _esc(pin.price_display) + '</div>' : '')
                + (pin.score_display ? '<div style="color:#0369a1;font-size:.8rem;">Match: '  + _esc(pin.score_display) + '</div>' : '')
                + '</div>';

            var iw = new google.maps.InfoWindow({ content: html });
            marker.addListener('click', function () { iw.open(_mapInstance, marker); });
            bounds.extend(new google.maps.LatLng(pin.lat, pin.lng));
        });

        if (!bounds.isEmpty()) {
            _mapInstance.fitBounds(bounds);
        }
    }
})();
</script>
@endpush
@endif

@endsection
