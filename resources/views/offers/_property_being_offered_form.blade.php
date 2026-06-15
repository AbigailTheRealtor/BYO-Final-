{{--
    Property Being Offered — editable form.
    Shown for buyer/tenant root draft offers only.

    Available from caller: $offer, $metas (current offer metas plucked key → value).

    Type / subtype option lists come from config('property_types') — single
    source of truth extracted verbatim from the seller and landlord offer-listing
    Livewire forms.

    Property attribute fields are rendered via the shared partial:
      offers/_property_attributes_form.blade.php

    Match explanation fields are rendered via the shared partial:
      offers/_match_explanation_form.blade.php
--}}
@php
    $pm   = $metas;
    $role = $offer->role;   // 'buyer' or 'tenant'

    $savedPropType    = old('prop_type',    $pm->get('prop_type',    ''));
    $savedPropSubtype = old('prop_subtype', $pm->get('prop_subtype', ''));
    $savedPhotos      = json_decode($pm->get('prop_photos', '[]'), true) ?: [];

    // Load type/subtype option lists from config/property_types.php (single source of truth).
    $cfg        = config('property_types');
    $roleConfig = $cfg[$role] ?? $cfg['buyer'];
    $types      = $roleConfig['types'];
    $subtypeMap = $roleConfig['subtypes'];

    $isTenant = $role === 'tenant';
@endphp

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<style>
    #save-property-info-btn { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
    #save-property-info-btn:hover { background:#1d4ed8; border-color:#1d4ed8; }
    /* Tailwind Preflight sets background-color:transparent on [type='submit'], which
       beats Bootstrap's .btn-primary. Scoped ID override (higher specificity) restores the correct blue. */
    .offer-section-header {
        font-size: 0.9rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.4rem;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    .offer-section-header:first-child { margin-top: 0; }
</style>
<form method="POST" action="{{ route('offers.property', $offer) }}" enctype="multipart/form-data" id="property-being-offered-form">
    @csrf

    {{-- ── Address ──────────────────────────────────────────────────────── --}}
    <p class="offer-section-header">Property Address</p>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <label class="form-label fw-semibold">Street Address <span class="text-danger">*</span></label>
            <input type="text" id="offer-prop-street-address" name="prop_street" class="form-control"
                value="{{ old('prop_street', $pm->get('prop_street')) }}"
                placeholder="123 Main St" autocomplete="off">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
            <input type="text" id="offer-prop-city" name="prop_city" class="form-control"
                value="{{ old('prop_city', $pm->get('prop_city')) }}"
                placeholder="City">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">State</label>
            <input type="text" id="offer-prop-state" name="prop_state" class="form-control"
                value="{{ old('prop_state', $pm->get('prop_state')) }}"
                placeholder="FL">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">ZIP Code</label>
            <input type="text" id="offer-prop-zip" name="prop_zip" class="form-control"
                value="{{ old('prop_zip', $pm->get('prop_zip')) }}"
                placeholder="33101" maxlength="10">
        </div>
        {{-- Hidden coordinate + county fields populated by Google Places autocomplete --}}
        <input type="hidden" id="offer-prop-lat"      name="prop_lat"             value="{{ old('prop_lat',             $pm->get('prop_lat',             '')) }}">
        <input type="hidden" id="offer-prop-lng"      name="prop_lng"             value="{{ old('prop_lng',             $pm->get('prop_lng',             '')) }}">
        <input type="hidden" id="offer-prop-place-id" name="prop_google_place_id" value="{{ old('prop_google_place_id', $pm->get('prop_google_place_id', '')) }}">
        <input type="hidden" id="offer-prop-county"   name="prop_county"          value="{{ old('prop_county',          $pm->get('prop_county',          '')) }}">
    </div>

    {{-- ── Property Type & Subtype ──────────────────────────────────────── --}}
    <p class="offer-section-header">Property Identification</p>

    <div x-data="{
        propType: '{{ addslashes($savedPropType) }}',
        propSubtype: '{{ addslashes($savedPropSubtype) }}'
    }">

        <div class="row g-3 mb-3">
            {{-- Type — options driven by config('property_types.{role}.types') --}}
            <div class="col-md-6">
                <label class="form-label fw-semibold">Property Type <span class="text-danger">*</span></label>
                <select name="prop_type" class="form-select" x-model="propType" @change="propSubtype = ''">
                    <option value="">Select</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}"
                            {{ $savedPropType === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Subtype — one <select> per type, shown/hidden via Alpine --}}
            <div class="col-md-6" x-show="propType !== ''"
                style="display:{{ $savedPropType ? 'block' : 'none' }}">
                <label class="form-label fw-semibold">Property Style / Subtype</label>

                @foreach($subtypeMap as $typeName => $subtypes)
                <select name="prop_subtype" class="form-select"
                    x-show="propType === '{{ addslashes($typeName) }}'"
                    :disabled="propType !== '{{ addslashes($typeName) }}'"
                    x-model="propSubtype">
                    <option value="">Select</option>
                    @foreach($subtypes as $sub)
                        <option value="{{ $sub }}"
                            {{ $savedPropType === $typeName && $savedPropSubtype === $sub ? 'selected' : '' }}>
                            {{ $sub }}
                        </option>
                    @endforeach
                </select>
                @endforeach
            </div>
        </div>

        {{-- Listing Status / MLS / URL --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Listing Status</label>
                <select name="prop_listing_status" class="form-select">
                    <option value="">Select</option>
                    @foreach(['Active','Pending','Coming Soon','Off Market','Sold'] as $s)
                        <option value="{{ $s }}"
                            {{ old('prop_listing_status', $pm->get('prop_listing_status')) === $s ? 'selected' : '' }}>
                            {{ $s }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">MLS #</label>
                <input type="text" name="prop_mls_number" class="form-control"
                    value="{{ old('prop_mls_number', $pm->get('prop_mls_number')) }}"
                    placeholder="e.g. A12345678">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Listing URL</label>
                <input type="url" name="prop_listing_url" class="form-control"
                    value="{{ old('prop_listing_url', $pm->get('prop_listing_url')) }}"
                    placeholder="https://...">
            </div>
        </div>

        {{-- ── Property Attribute groups (type-conditional, inside x-data scope) ── --}}
        @include('offers._property_attributes_form', [
            'pm'       => $pm,
            'isTenant' => $isTenant,
        ])

    </div>{{-- end x-data --}}

    {{-- ── Property Description ─────────────────────────────────────────── --}}
    <p class="offer-section-header">{{ $isTenant ? 'Rental Description' : 'Property Description' }}</p>

    <div class="mb-3">
        <label class="form-label fw-semibold">
            {{ $isTenant ? 'Rental Description' : 'Property Description' }}
        </label>
        <textarea name="prop_description" class="form-control" rows="5"
            placeholder="{{ $isTenant ? 'Describe this rental property — amenities, condition, neighbourhood highlights...' : 'Describe this property — features, condition, recent upgrades, neighbourhood highlights...' }}">{{ old('prop_description', $pm->get('prop_description')) }}</textarea>
        <div class="form-text">
            Required before submitting. Give the {{ $isTenant ? 'tenant' : 'buyer' }} a clear picture of the property.
        </div>
    </div>

    {{-- ── Property Highlights (buyer: purchase; tenant: rental) ──────────── --}}
    <p class="offer-section-header">{{ $isTenant ? 'Rental Highlights' : 'Property Highlights' }}</p>

    @php
        $savedHighlights = json_decode($pm->get('prop_highlights', '[]'), true) ?: [];
        $buyerHighlights = [
            'Move-in ready',
            'Recently renovated',
            'Updated kitchen',
            'Updated bathrooms',
            'New roof',
            'New HVAC',
            'Open floor plan',
            'New appliances',
            'Hardwood floors',
            'Smart home features',
            'Energy efficient',
            'Private pool',
            'Large yard',
            'Waterfront',
            'Gated community',
            'Low HOA',
            'No HOA',
            'Great school district',
        ];
        $tenantHighlights = [
            'Furnished',
            'Partially furnished',
            'Pet-friendly',
            'All utilities included',
            'Water / sewer included',
            'In-unit washer/dryer',
            'Shared laundry',
            'Private parking',
            'Garage included',
            'Pool access',
            'Gym access',
            'Central A/C',
            'Recently renovated',
            'New appliances',
            'Gated community',
            'Short-term lease ok',
        ];
        $highlightOptions = $isTenant ? $tenantHighlights : $buyerHighlights;
        $savedOtherHighlight = in_array('Other', $savedHighlights) ? '' : '';
        $savedOtherText = $pm->get('prop_highlights_other', '');
    @endphp

    <div class="mb-3">
        <div class="row g-2">
            @foreach($highlightOptions as $hl)
            <div class="col-md-4 col-sm-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                        name="prop_highlights[]" value="{{ $hl }}"
                        id="hl_{{ Str::slug($hl) }}"
                        {{ in_array($hl, $savedHighlights) ? 'checked' : '' }}>
                    <label class="form-check-label" for="hl_{{ Str::slug($hl) }}">{{ $hl }}</label>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-2">
            <label class="form-label fw-semibold">Other highlight (optional)</label>
            <input type="text" name="prop_highlights_other" class="form-control"
                value="{{ old('prop_highlights_other', $savedOtherText) }}"
                placeholder="e.g. Corner unit, Private dock...">
        </div>
        <div class="form-text">Select all that apply. Highlights are shown to the {{ $isTenant ? 'tenant' : 'buyer' }} in the summary.</div>
    </div>

    {{-- ── Media & Availability ─────────────────────────────────────────── --}}
    <p class="offer-section-header">Media &amp; Availability</p>

    @if(count($savedPhotos) > 0)
    <div class="mb-3">
        <label class="form-label fw-semibold">Uploaded Photos</label>
        <div class="d-flex flex-wrap gap-2">
            @foreach($savedPhotos as $photo)
            <img src="{{ asset('storage/offer-property-photos/' . $offer->id . '/' . $photo) }}"
                alt="{{ $photo }}"
                style="width:80px;height:60px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
            @endforeach
        </div>
        <small class="text-muted">New uploads will be added to these photos.</small>
    </div>
    @endif

    <div class="mb-3">
        <label class="form-label fw-semibold">Upload Photos</label>
        <input type="file" name="prop_photos[]" class="form-control" multiple accept="image/*">
        <div class="form-text">Upload up to 20 photos (JPEG, PNG, WebP — max 5 MB each).</div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Photo URLs <span class="text-muted fw-normal">(optional — one per line)</span></label>
        <textarea name="prop_photo_urls" class="form-control" rows="3"
            placeholder="https://example.com/photo1.jpg&#10;https://example.com/photo2.jpg">{{ old('prop_photo_urls', $pm->get('prop_photo_urls')) }}</textarea>
        <div class="form-text">
            Alternative to file uploads — paste externally hosted photo URLs, one per line.
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Virtual Tour URL</label>
        <input type="url" name="prop_virtual_tour_url" class="form-control"
            value="{{ old('prop_virtual_tour_url', $pm->get('prop_virtual_tour_url')) }}"
            placeholder="https://...">
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Video URL</label>
        <input type="url" name="prop_video_url" class="form-control"
            value="{{ old('prop_video_url', $pm->get('prop_video_url')) }}"
            placeholder="https://youtube.com/...">
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Available Date</label>
            <input type="date" name="prop_available_date" class="form-control"
                value="{{ old('prop_available_date', $pm->get('prop_available_date')) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Occupancy Status</label>
            <select name="prop_occupancy_status" class="form-select">
                <option value="">Select</option>
                @foreach(['Vacant','Owner Occupied','Tenant Occupied'] as $s)
                    <option value="{{ $s }}"
                        {{ old('prop_occupancy_status', $pm->get('prop_occupancy_status')) === $s ? 'selected' : '' }}>
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Showing Availability</label>
            <input type="text" name="prop_showing_availability" class="form-control"
                value="{{ old('prop_showing_availability', $pm->get('prop_showing_availability')) }}"
                placeholder="e.g. Weekdays 9am–5pm, by appointment">
        </div>
    </div>

    {{-- ── Property Description ──────────────────────────────────────────── --}}
    <p class="offer-section-header">{{ $isTenant ? 'Rental Description' : 'Property Description' }}</p>
    <div class="mb-3">
        <label class="form-label fw-semibold">{{ $isTenant ? 'Rental Description' : 'Property Description' }}</label>
        <textarea name="prop_description" id="prop_description" class="form-control" rows="5"
            placeholder="{{ $isTenant ? 'Describe the rental property — features, neighbourhood, what makes it a great fit...' : 'Describe the property — features, neighbourhood, why it stands out...' }}">{{ old('prop_description', $pm->get('prop_description')) }}</textarea>
        <div class="form-text">Give a detailed overview of the property to help the {{ $isTenant ? 'tenant' : 'buyer' }} understand what is being offered.</div>
    </div>

    {{-- ── Highlights ─────────────────────────────────────────────────────── --}}
    <p class="offer-section-header">{{ $isTenant ? 'Rental Highlights' : 'Property Highlights' }}</p>
    @php
        $savedHighlights = json_decode($pm->get('prop_highlights', '[]'), true) ?: [];

        $buyerHighlightOptions = [
            'Move-in Ready',
            'Recently Renovated / Updated',
            'New Kitchen & Appliances',
            'Open Floor Plan',
            'Home Office / Flex Space',
            'In-ground Pool',
            'Garage / Covered Parking',
            'Gated / HOA Community',
            'Energy Efficient / Solar',
            'Smart Home Features',
            'Near Top-Rated Schools',
            'Near Shopping & Dining',
            'Near Public Transit',
            'Quiet / Low-Traffic Street',
        ];

        $tenantHighlightOptions = [
            'Move-in Ready',
            'Recently Renovated',
            'New Appliances',
            'In-unit Laundry',
            'Covered Parking',
            'Gated/Secured Building',
            'Pet Friendly',
            'Pool Access',
            'Fitness Center',
            'Near Public Transit',
            'Near Top-Rated Schools',
            'Furnished Option',
            'Utilities Included',
            'Short-term Lease Available',
        ];

        $highlightOptions = $isTenant ? $tenantHighlightOptions : $buyerHighlightOptions;
    @endphp
    <div class="mb-3">
        <div class="d-flex flex-wrap gap-2">
            @foreach($highlightOptions as $hl)
            @php $hlChecked = in_array($hl, $savedHighlights); @endphp
            {{-- onchange on the hidden checkbox updates label classes.
                 No onclick on label — the browser's native label→input association
                 toggles the checkbox; then onchange fires to sync CSS. --}}
            <label class="d-inline-flex align-items-center gap-1 px-3 py-2 rounded border
                {{ $hlChecked ? 'bg-primary text-white border-primary' : 'bg-white text-dark border-secondary' }}"
                style="cursor:pointer;font-size:0.875rem;user-select:none;">
                <input type="checkbox" name="prop_highlights[]" value="{{ $hl }}"
                    {{ $hlChecked ? 'checked' : '' }}
                    style="display:none;"
                    onchange="var l=this.closest('label');if(this.checked){l.classList.add('bg-primary','text-white','border-primary');l.classList.remove('bg-white','text-dark','border-secondary');}else{l.classList.remove('bg-primary','text-white','border-primary');l.classList.add('bg-white','text-dark','border-secondary');}">
                {{ $hl }}
            </label>
            @endforeach
        </div>
        <div class="form-text mt-2">Select all that apply to this {{ $isTenant ? 'rental' : 'property' }}.</div>
    </div>

    {{-- ── Match Explanation (dedicated partial) ────────────────────────── --}}
    @include('offers._match_explanation_form', ['pm' => $pm, 'offer' => $offer])

    @push('scripts')
    <script>
    window.byoInitOfferPropPlaces = function() {
        var input = document.getElementById('offer-prop-street-address');
        if (!input || !window.google || !window.google.maps || !window.google.maps.places) { return; }
        if (input._byoPlacesAttached) { return; }
        input._byoPlacesAttached = true;

        var ac = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            componentRestrictions: { country: 'us' },
            fields: ['address_components', 'geometry', 'place_id']
        });

        google.maps.event.addDomListener(input, 'keydown', function(e) {
            if (e.keyCode === 13) { e.preventDefault(); }
        });

        ac.addListener('place_changed', function() {
            var place = ac.getPlace();
            if (!place || !place.geometry || !place.geometry.location) { return; }

            var lat     = place.geometry.location.lat();
            var lng     = place.geometry.location.lng();
            var placeId = place.place_id || '';

            var streetNum = '', route = '', city = '', state = '', zip = '';
            if (place.address_components) {
                place.address_components.forEach(function(c) {
                    var t = c.types;
                    if (t.indexOf('street_number')            !== -1)                  streetNum = c.long_name;
                    if (t.indexOf('route')                    !== -1)                  route     = c.long_name;
                    if (t.indexOf('locality')                 !== -1)                  city      = c.long_name;
                    if (t.indexOf('sublocality_level_1')      !== -1 && !city)         city      = c.long_name;
                    if (t.indexOf('administrative_area_level_1') !== -1)               state     = c.short_name;
                    if (t.indexOf('postal_code')              !== -1)                  zip       = c.long_name;
                });
            }

            var street = streetNum ? (streetNum + ' ' + route).trim() : route;

            input.value = street;
            var county = '';
            if (place.address_components) {
                place.address_components.forEach(function(c) {
                    var t = c.types;
                    if (t.indexOf('administrative_area_level_2') !== -1) county = c.long_name.replace(/ County$/, '');
                });
            }

            var cityEl    = document.getElementById('offer-prop-city');
            var stateEl   = document.getElementById('offer-prop-state');
            var zipEl     = document.getElementById('offer-prop-zip');
            var countyEl  = document.getElementById('offer-prop-county');
            var latEl     = document.getElementById('offer-prop-lat');
            var lngEl     = document.getElementById('offer-prop-lng');
            var placeIdEl = document.getElementById('offer-prop-place-id');

            if (cityEl  && city)    { cityEl.value  = city; }
            if (stateEl && state)   { stateEl.value = state; }
            if (zipEl   && zip)     { zipEl.value   = zip; }
            if (countyEl && county) { countyEl.value = county; }
            if (latEl)              { latEl.value   = String(lat); }
            if (lngEl)              { lngEl.value   = String(lng); }
            if (placeIdEl)          { placeIdEl.value = placeId; }
        });
    };
    </script>
    <x-google-maps-script callback="byoInitOfferPropPlaces" />
    @endpush

    <div class="d-flex gap-2 mt-4">
        {{-- Explicit styles override any parent CSS conflicts: always blue bg, white text,
             pointer cursor, darker hover, full-width on mobile, greyed disabled state. --}}
        <style>
            #save-property-info-btn {
                background-color: #0d6efd !important;
                color: #fff !important;
                border: 1px solid #0d6efd !important;
                cursor: pointer !important;
                min-width: 10rem;
                white-space: nowrap;
            }
            #save-property-info-btn:hover:not(:disabled) {
                background-color: #0b5ed7 !important;
                border-color: #0a58ca !important;
            }
            #save-property-info-btn:disabled,
            #save-property-info-btn[disabled] {
                background-color: #6c757d !important;
                border-color: #6c757d !important;
                color: #fff !important;
                cursor: not-allowed !important;
                opacity: 0.65 !important;
            }
            @media (max-width: 575.98px) {
                #save-property-info-btn { width: 100% !important; }
            }
        </style>
        <button type="submit" class="btn btn-primary btn-sm" id="save-property-info-btn">
            Save Property Information
        </button>
    </div>
</form>
