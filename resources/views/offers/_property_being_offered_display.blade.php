{{--
    Property Being Offered — read-only display.

    Available from caller:
      $metas — metas collection (plucked key → value), usually $rootMetas
      $offer — Offer model (needed for uploaded photo storage path)

    The Match Explanation section is rendered via the dedicated
    offers._match_explanation_display partial.
--}}
@php
    $pm = $metas;
    $propStreet  = $pm->get('prop_street');
    $propCity    = $pm->get('prop_city');
    $propState   = $pm->get('prop_state');
    $propZip     = $pm->get('prop_zip');
    $propType    = $pm->get('prop_type');
    $propSubtype = $pm->get('prop_subtype');
    $propStatus  = $pm->get('prop_listing_status');
    $propMls     = $pm->get('prop_mls_number');
    $propUrl     = $pm->get('prop_listing_url');
    $propTour    = $pm->get('prop_virtual_tour_url');
    $propVideo   = $pm->get('prop_video_url');
    $propDate    = $pm->get('prop_available_date');
    $propOcc     = $pm->get('prop_occupancy_status');
    $propShowing = $pm->get('prop_showing_availability');
    $propPhotos  = json_decode($pm->get('prop_photos', '[]'), true) ?: [];

    // Photo URLs (newline-separated string → array of safe, non-empty trimmed URLs).
    // Defense-in-depth: reject any entry that is not an absolute http/https URL to prevent
    // javascript:/data: URI injection when rendered into <a href> / <img src>.
    $propPhotoUrls = array_values(array_filter(
        array_map('trim', explode("\n", $pm->get('prop_photo_urls', ''))),
        fn($u) => $u !== '' && preg_match('#^https?://#i', $u)
    ));

    $fmtDate = function ($v) {
        if (!$v) return '—';
        try { return \Carbon\Carbon::parse($v)->format('F j, Y'); }
        catch (\Throwable $e) { return '—'; }
    };

    $hasPhotos  = count($propPhotos) > 0 || count($propPhotoUrls) > 0;
    $propDescription = $pm->get('prop_description');
    $propHighlights  = json_decode($pm->get('prop_highlights', '[]'), true) ?: [];
    $propHighOther   = $pm->get('prop_highlights_other');

    $hasAnyData = $propStreet || $propCity || $propType || $propMls
        || $hasPhotos || $pm->get('match_explanation')
        || $pm->get('prop_description') || $pm->get('prop_highlights');
@endphp

@if(!$hasAnyData)
<p class="text-muted mb-0"><em>No property information provided.</em></p>
@else

{{-- ── Address ──────────────────────────────────────────────────────────── --}}
@if($propStreet || $propCity || $propState || $propZip)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Property Address</p>
<dl class="row mb-3">
    @if($propStreet)
    <dt class="col-sm-3">Street</dt>
    <dd class="col-sm-9">{{ $propStreet }}</dd>
    @endif
    @if($propCity || $propState || $propZip)
    <dt class="col-sm-3">City / State / ZIP</dt>
    <dd class="col-sm-9">{{ implode(', ', array_filter([$propCity, $propState, $propZip])) }}</dd>
    @endif
</dl>
@endif

{{-- ── Property Identification ──────────────────────────────────────────── --}}
@if($propType || $propSubtype || $propStatus || $propMls || $propUrl)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Property Identification</p>
<dl class="row mb-3">
    @if($propType)
    <dt class="col-sm-3">Property Type</dt>
    <dd class="col-sm-9">{{ $propType }}</dd>
    @endif
    @if($propSubtype)
    <dt class="col-sm-3">Style / Subtype</dt>
    <dd class="col-sm-9">{{ $propSubtype }}</dd>
    @endif
    @if($propStatus)
    <dt class="col-sm-3">Listing Status</dt>
    <dd class="col-sm-9">{{ $propStatus }}</dd>
    @endif
    @if($propMls)
    <dt class="col-sm-3">MLS #</dt>
    <dd class="col-sm-9">{{ $propMls }}</dd>
    @endif
    @if($propUrl)
    <dt class="col-sm-3">Listing URL</dt>
    <dd class="col-sm-9"><a href="{{ $propUrl }}" target="_blank" rel="noopener">{{ $propUrl }}</a></dd>
    @endif
</dl>
@endif

{{-- ── Property Description ──────────────────────────────────────────────── --}}
@if($propDescription)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">
    {{ $offer->role === 'tenant' ? 'Rental Description' : 'Property Description' }}
</p>
<div class="mb-3" style="white-space:pre-wrap;font-size:0.95rem;">{{ $propDescription }}</div>
@endif

{{-- ── Property Highlights ────────────────────────────────────────────────── --}}
@if(count($propHighlights) > 0 || $propHighOther)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">
    {{ $offer->role === 'tenant' ? 'Rental Highlights' : 'Property Highlights' }}
</p>
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($propHighlights as $hl)
    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:0.82rem;font-weight:500;padding:0.35em 0.65em;">{{ $hl }}</span>
    @endforeach
    @if($propHighOther)
    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:0.82rem;font-weight:500;padding:0.35em 0.65em;">{{ $propHighOther }}</span>
    @endif
</div>
@endif

{{-- ── Property Attributes ──────────────────────────────────────────────── --}}
@php
    $attrCondition   = $pm->get('prop_attr_condition');
    $attrBedrooms    = $pm->get('prop_attr_bedrooms');
    $attrOtherBeds   = $pm->get('prop_attr_other_bedrooms');
    $attrBathrooms   = $pm->get('prop_attr_bathrooms');
    $attrOtherBaths  = $pm->get('prop_attr_other_bathrooms');
    $attrHeatedSqft  = $pm->get('prop_attr_heated_sqft');
    $attrNetLeasable = $pm->get('prop_attr_net_leasable_sqft');
    $attrTotalSqft   = $pm->get('prop_attr_total_sqft');
    $attrSqftSource  = $pm->get('prop_attr_sqft_source');
    $attrAcreage     = $pm->get('prop_attr_total_acreage');
    $attrGarage      = $pm->get('prop_attr_garage');
    $attrGarSpaces   = $pm->get('prop_attr_garage_spaces');
    $attrPool        = $pm->get('prop_attr_pool');
    $attrPoolPrivate = $pm->get('prop_attr_pool_private');
    $attrPoolComm    = $pm->get('prop_attr_pool_community');
    $attrYearBuilt   = $pm->get('prop_attr_year_built');
    $attrZoning      = $pm->get('prop_attr_zoning');

    $bedroomDisplay = $attrBedrooms === 'Other' && $attrOtherBeds
        ? $attrOtherBeds
        : $attrBedrooms;
    $bathroomDisplay = $attrBathrooms === 'Other' && $attrOtherBaths
        ? $attrOtherBaths
        : $attrBathrooms;

    $poolTypeLabels = array_filter([
        $attrPoolPrivate  ? 'Private'   : null,
        $attrPoolComm     ? 'Community' : null,
    ]);

    $hasAttrData = $attrCondition || $bedroomDisplay || $bathroomDisplay
        || $attrHeatedSqft || $attrTotalSqft || $attrSqftSource || $attrAcreage
        || $attrGarage || $attrPool || $attrYearBuilt || $attrZoning;
@endphp
@if($hasAttrData)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Property Attributes</p>
<dl class="row mb-3">
    @if($attrCondition)
    <dt class="col-sm-3">Property Condition</dt>
    <dd class="col-sm-9">{{ $attrCondition }}</dd>
    @endif
    @if($bedroomDisplay)
    <dt class="col-sm-3">Bedrooms</dt>
    <dd class="col-sm-9">{{ $bedroomDisplay }}</dd>
    @endif
    @if($bathroomDisplay)
    <dt class="col-sm-3">Bathrooms</dt>
    <dd class="col-sm-9">{{ $bathroomDisplay }}</dd>
    @endif
    @if($attrHeatedSqft)
    <dt class="col-sm-3">Heated SqFt</dt>
    <dd class="col-sm-9">{{ $attrHeatedSqft }}</dd>
    @endif
    @if($attrTotalSqft)
    <dt class="col-sm-3">Total SqFt</dt>
    <dd class="col-sm-9">{{ $attrTotalSqft }}</dd>
    @endif
    @if($attrSqftSource)
    <dt class="col-sm-3">SqFt Source</dt>
    <dd class="col-sm-9">{{ $attrSqftSource }}</dd>
    @endif
    @if($attrAcreage)
    <dt class="col-sm-3">Total Acreage</dt>
    <dd class="col-sm-9">{{ $attrAcreage }}</dd>
    @endif
    @if($attrGarage)
    <dt class="col-sm-3">Garage</dt>
    <dd class="col-sm-9">{{ $attrGarage }}{{ $attrGarSpaces ? ' (' . $attrGarSpaces . ' spaces)' : '' }}</dd>
    @endif
    @if($attrPool)
    <dt class="col-sm-3">Pool</dt>
    <dd class="col-sm-9">{{ $attrPool }}{{ count($poolTypeLabels) ? ' — ' . implode(', ', $poolTypeLabels) : '' }}</dd>
    @endif
    @if($attrNetLeasable)
    <dt class="col-sm-3">Net Leasable SqFt</dt>
    <dd class="col-sm-9">{{ $attrNetLeasable }}</dd>
    @endif
    @if($attrYearBuilt)
    <dt class="col-sm-3">Year Built</dt>
    <dd class="col-sm-9">{{ $attrYearBuilt }}</dd>
    @endif
    @if($attrZoning)
    <dt class="col-sm-3">Zoning</dt>
    <dd class="col-sm-9">{{ $attrZoning }}</dd>
    @endif
</dl>
@endif

{{-- ── Photos (uploaded files + URL fallback) ──────────────────────────── --}}
@if($hasPhotos)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Photos</p>
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($propPhotos as $photo)
    <a href="{{ asset('storage/offer-property-photos/' . $offer->id . '/' . $photo) }}" target="_blank" rel="noopener">
        <img src="{{ asset('storage/offer-property-photos/' . $offer->id . '/' . $photo) }}"
            alt="{{ $photo }}"
            style="width:100px;height:75px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
    </a>
    @endforeach
    @foreach($propPhotoUrls as $photoUrl)
    <a href="{{ $photoUrl }}" target="_blank" rel="noopener">
        <img src="{{ $photoUrl }}"
            alt="Property photo"
            onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';"
            style="width:100px;height:75px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
        <span class="d-none align-items-center justify-content-center text-muted"
            style="width:100px;height:75px;border-radius:4px;border:1px solid #dee2e6;font-size:0.7rem;text-align:center;">
            External<br>Photo
        </span>
    </a>
    @endforeach
</div>
@endif

{{-- ── Media Links ──────────────────────────────────────────────────────── --}}
@if($propTour || $propVideo)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Media Links</p>
<dl class="row mb-3">
    @if($propTour)
    <dt class="col-sm-3">Virtual Tour</dt>
    <dd class="col-sm-9"><a href="{{ $propTour }}" target="_blank" rel="noopener">{{ $propTour }}</a></dd>
    @endif
    @if($propVideo)
    <dt class="col-sm-3">Video</dt>
    <dd class="col-sm-9"><a href="{{ $propVideo }}" target="_blank" rel="noopener">{{ $propVideo }}</a></dd>
    @endif
</dl>
@endif

{{-- ── Availability ──────────────────────────────────────────────────────── --}}
@if($propDate || $propOcc || $propShowing)
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Availability</p>
<dl class="row mb-3">
    @if($propDate)
    <dt class="col-sm-3">Available Date</dt>
    <dd class="col-sm-9">{{ $fmtDate($propDate) }}</dd>
    @endif
    @if($propOcc)
    <dt class="col-sm-3">Occupancy Status</dt>
    <dd class="col-sm-9">{{ $propOcc }}</dd>
    @endif
    @if($propShowing)
    <dt class="col-sm-3">Showing Availability</dt>
    <dd class="col-sm-9">{{ $propShowing }}</dd>
    @endif
</dl>
@endif

{{-- ── Property Description ──────────────────────────────────────────────── --}}
@if($pm->get('prop_description'))
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">{{ ($offer->role ?? 'buyer') === 'tenant' ? 'Rental Description' : 'Property Description' }}</p>
<p class="mb-3" style="white-space:pre-wrap;">{{ $pm->get('prop_description') }}</p>
@endif

{{-- ── Highlights ──────────────────────────────────────────────────────────── --}}
@php
    $dispHighlights = json_decode($pm->get('prop_highlights', '[]'), true) ?: [];
    $dispRole = $offer->role ?? 'buyer';
@endphp
@if(count($dispHighlights))
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">{{ $dispRole === 'tenant' ? 'Rental Highlights' : 'Property Highlights' }}</p>
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($dispHighlights as $hl)
    <span class="badge rounded-pill bg-primary" style="font-size:0.8rem;padding:0.35em 0.75em;">{{ $hl }}</span>
    @endforeach
</div>
@endif

{{-- ── Match Explanation (dedicated display partial) ────────────────────── --}}
@include('offers._match_explanation_display', ['metas' => $pm, 'offer' => $offer])

@endif{{-- end hasAnyData --}}
