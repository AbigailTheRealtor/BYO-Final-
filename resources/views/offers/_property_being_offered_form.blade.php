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

<form method="POST" action="{{ route('offers.property', $offer) }}" enctype="multipart/form-data" id="property-being-offered-form">
    @csrf

    {{-- ── Address ──────────────────────────────────────────────────────── --}}
    <p class="offer-section-header">Property Address</p>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <label class="form-label fw-semibold">Street Address <span class="text-danger">*</span></label>
            <input type="text" name="prop_street" class="form-control"
                value="{{ old('prop_street', $pm->get('prop_street')) }}"
                placeholder="123 Main St">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
            <input type="text" name="prop_city" class="form-control"
                value="{{ old('prop_city', $pm->get('prop_city')) }}"
                placeholder="City">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">State</label>
            <input type="text" name="prop_state" class="form-control"
                value="{{ old('prop_state', $pm->get('prop_state')) }}"
                placeholder="FL">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">ZIP Code</label>
            <input type="text" name="prop_zip" class="form-control"
                value="{{ old('prop_zip', $pm->get('prop_zip')) }}"
                placeholder="33101" maxlength="10">
        </div>
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

    {{-- ── Match Explanation (dedicated partial) ────────────────────────── --}}
    @include('offers._match_explanation_form', ['pm' => $pm, 'offer' => $offer])

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary btn-sm" id="save-property-info-btn">
            Save Property Information
        </button>
    </div>
</form>
