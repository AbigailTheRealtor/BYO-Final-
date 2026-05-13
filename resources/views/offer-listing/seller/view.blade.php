@extends('layouts.main')

@php
    $fmtMoney = function($v) {
        if ($v === null || $v === '') return null;
        $raw = preg_replace('/[^0-9.]/', '', (string)$v);
        if ($raw === '' || !is_numeric($raw)) return null;
        return '$' . number_format((float)$raw, 0);
    };
    $fmtPercent = function($v) {
        if ($v === null || $v === '') return null;
        $raw = preg_replace('/[^0-9.]/', '', (string)$v);
        if ($raw === '' || !is_numeric($raw)) return null;
        $num = (float)$raw;
        return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
    };
    $val = fn($key) => $meta[$key] ?? null;
    $str = function($key) use ($meta) { $v = $meta[$key] ?? ''; return is_array($v) ? implode(', ', $v) : $v; };
    $arr = function($key) use ($meta) {
        $v = $meta[$key] ?? [];
        if (is_string($v)) {
            $d = json_decode($v, true);
            if (is_string($d)) { $d = json_decode($d, true); }
            return is_array($d) ? $d : [];
        }
        return is_array($v) ? $v : [];
    };
    $yesNo = fn($v) => match((string)$v) { '1','true','yes','Yes' => 'Yes', '0','false','no','No' => 'No', default => $v };
    $fmtDate = function($v) {
        if ($v === null || $v === '') return null;
        try {
            $d = \Carbon\Carbon::parse((string)$v);
            return $d->format('F j, Y');
        } catch (\Exception $e) {
            return null;
        }
    };
    $subOther = function(array $items, string $otherVal): array {
        if (!$otherVal) return $items;
        return array_map(fn($v) => $v === 'Other' ? $otherVal : $v, $items);
    };
    $orOther = function(string $primary, string $otherVal): string {
        return ($primary === 'Other' && $otherVal !== '') ? $otherVal : $primary;
    };
    $row = function($label, $value) {
        if ($value === null || $value === '' || $value === false) return '';
        return '<div class="row mb-2"><div class="col-md-5 text-muted fw-semibold">' . e($label) . '</div><div class="col-md-7" style="overflow-wrap:break-word;word-break:break-word;">' . e($value) . '</div></div>';
    };
@endphp

@push('styles')
<style>
    .section-card { margin-bottom: 2rem; border-radius: 0.5rem; border: 1px solid #dee2e6; }
    .section-card .card-header { background: #f1f5f9; font-weight: 700; font-size: 1.05rem; padding: 0.75rem 1rem; }
    .section-card .card-body { padding: 1rem 1.25rem; }
    .field-label { color: #6c757d; font-weight: 600; font-size: 0.875rem; }
    .field-value { font-size: 0.925rem; overflow-wrap: break-word; }
    .photo-thumb { width: 120px; height: 90px; object-fit: cover; border-radius: 6px; border: 2px solid #dee2e6; }
    .cover-badge { font-size: 0.7rem; background: #0d6efd; color: #fff; border-radius: 3px; padding: 1px 5px; }
</style>
@endpush

@section('content')
<div class="container py-4">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold">{{ $auction->title ?? ($meta['address'] ?? 'Seller Offer Listing') }}</h2>
            @php
                $addrParts = array_filter([
                    $meta['address'] ?? null,
                    !empty($meta['unit']) ? 'Unit ' . $meta['unit'] : null,
                    $meta['property_city'] ?? null,
                ]);
                $addrState = trim($meta['property_state'] ?? '');
                $addrZip   = trim(($meta['property_zip'] ?? '') ?: ($meta['zip_code'] ?? ''));
                $stateZip  = trim($addrState . ($addrState && $addrZip ? ' ' : '') . $addrZip);
                if ($stateZip) $addrParts[] = $stateZip;
                $fullAddress = implode(', ', array_filter($addrParts));
            @endphp
            @if($fullAddress)
                <p class="text-muted mb-0"><i class="fa-solid fa-location-dot me-1"></i>{{ $fullAddress }}</p>
            @endif
        </div>
        @if(auth()->id() == $auction->user_id)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('offer.listing.seller.edit', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </div>
        @endif
    </div>

    {{-- =====================================================================
         INTENTIONAL FIELD EXCLUSIONS (not rendered on this view page):
         - listing_ai_faq        : AI-generated FAQ, internal content only.
         - photo                 : Livewire temp upload, not a display meta value.
         - video_link            : Agent intro video (seller-info tab), not property media.
         - current_status        : Internal workflow field, not a user-facing detail.
         - state                 : Livewire autocomplete holder; saved as property_state.
         - newCity               : Livewire holder; saved as property_city.
         - newPropertyPhotos     : Livewire file-upload holder; rendered via property_photos.
         - openHouseCount        : Event counter, not persisted listing data.
         - photo_enhancements    : Photo-editing preference flag, no display value.
         - other_preferences     : Internal catch-all, no standard display key.
         - other_services_enabled / other_services.N : Rendered via the services array.
         - prepayment_penalty    : Yes/No toggle; amount rendered as prepayment_penalty_amount.
         - baths_unit / beds_unit / expected_rent / number_occupied : Sub-fields of
           unit_type_configurations JSON; summary rendered via unit_number / unit_buildings.
         - pool_type.community / pool_type.private : Rendered via $arr('pool_type') below.
         - videoTourUrl / virtualTourUrl : Aliases; rendered as video_tour_url / virtual_tour_url.
         - other_building_features, other_current_use, other_current_adjacent_use,
           other_easements, other_electrical_service, other_fences, other_licenses,
           other_non_negotiable_amenities, other_parking_space_wrapper, other_road_frontage,
           other_road_surface_type, other_sale_includes, other_vegetation,
           other_carport_needed, other_garage_needed : "Other" companion inputs for
           land/commercial-specific multi-selects not rendered in this layout.
         ===================================================================== --}}

    {{-- Photos & Tours --}}
    @php
        $propertyPhotos = $meta['property_photos'] ?? [];
        if (is_string($propertyPhotos)) {
            $decoded = json_decode($propertyPhotos, true);
            $propertyPhotos = is_array($decoded) ? $decoded : [];
        }
    @endphp
    @if(count($propertyPhotos) || $str('video_tour_url') || $str('virtual_tour_url'))
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-images me-2"></i>Photos &amp; Tours</div>
        <div class="card-body">
            @php
                $videoUrl = $str('video_tour_url');
                $videoEmbedUrl = null;
                if ($videoUrl) {
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $videoUrl, $vm)) {
                        $videoEmbedUrl = 'https://www.youtube.com/embed/' . $vm[1];
                    } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $vm)) {
                        $videoEmbedUrl = 'https://player.vimeo.com/video/' . $vm[1];
                    }
                }
                $virtualUrl = $str('virtual_tour_url');
                $virtualEmbedUrl = null;
                if ($virtualUrl) {
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $virtualUrl, $vm)) {
                        $virtualEmbedUrl = 'https://www.youtube.com/embed/' . $vm[1];
                    } elseif (preg_match('/vimeo\.com\/(\d+)/', $virtualUrl, $vm)) {
                        $virtualEmbedUrl = 'https://player.vimeo.com/video/' . $vm[1];
                    }
                }
            @endphp
            @if($videoUrl)
                @if($videoEmbedUrl)
                    <div class="ratio ratio-16x9 mb-2" style="max-width:560px;">
                        <iframe src="{{ $videoEmbedUrl }}" title="Video Tour"
                                allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                        </iframe>
                    </div>
                @endif
                <p class="mb-3"><span class="field-label">Video Tour:</span>
                    <a href="{{ $videoUrl }}" target="_blank" rel="noopener" class="ms-1">{{ $videoUrl }}</a>
                </p>
            @endif
            @if($virtualUrl)
                @if($virtualEmbedUrl)
                    <div class="ratio ratio-16x9 mb-2" style="max-width:560px;">
                        <iframe src="{{ $virtualEmbedUrl }}" title="Virtual Tour"
                                allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                        </iframe>
                    </div>
                @endif
                <p class="mb-3"><span class="field-label">3D / Virtual Tour:</span>
                    <a href="{{ $virtualUrl }}" target="_blank" rel="noopener" class="ms-1">{{ $virtualUrl }}</a>
                </p>
            @endif

            @if(count($propertyPhotos))
            @php $galleryIdx = -1; @endphp
            <div class="d-flex flex-wrap gap-2 mt-3">
                @foreach($propertyPhotos as $photo)
                @php
                    $filename = is_array($photo) ? ($photo['filename'] ?? '') : $photo;
                    $isCover  = is_array($photo) && !empty($photo['is_cover']);
                    if ($filename) $galleryIdx++;
                @endphp
                @if($filename)
                <div class="text-center">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#photoModal"
                       data-src="{{ asset('storage/auction/images/' . $filename) }}"
                       data-index="{{ $galleryIdx }}"
                       style="display:block;">
                        <img src="{{ asset('storage/auction/images/' . $filename) }}"
                             alt="Property photo {{ $galleryIdx + 1 }}"
                             class="photo-thumb"
                             onerror="this.style.display='none'">
                    </a>
                    @if($isCover)
                        <div><span class="cover-badge">Cover</span></div>
                    @endif
                </div>
                @endif
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Photo lightbox modal --}}
    @if(count($propertyPhotos))
    <div class="modal fade" id="photoModal" tabindex="-1" aria-label="Property photo viewer" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0 pb-0">
                    <span class="text-white small" id="photoModalCounter"></span>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="photoModalImg" src="" alt="Property photo"
                         style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">
                </div>
                <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="photoModalPrev">&#8249; Prev</button>
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="photoModalNext">Next &#8250;</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var gallery = Array.from(document.querySelectorAll('[data-bs-target="#photoModal"]'))
                           .map(function (el) { return el.getAttribute('data-src'); });
        var currentIndex = 0;

        function showPhoto(idx) {
            if (gallery.length === 0) return;
            if (idx < 0) idx = gallery.length - 1;
            if (idx >= gallery.length) idx = 0;
            currentIndex = idx;
            document.getElementById('photoModalImg').src = gallery[idx];
            document.getElementById('photoModalCounter').textContent = (idx + 1) + ' / ' + gallery.length;
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-bs-target="#photoModal"]');
            if (trigger) {
                e.preventDefault();
                showPhoto(parseInt(trigger.getAttribute('data-index') || '0', 10));
            }
        });

        var photoModalEl = document.getElementById('photoModal');
        if (photoModalEl) {
            photoModalEl.addEventListener('show.bs.modal', function () {
                showPhoto(currentIndex);
            });
        }

        var prevBtn = document.getElementById('photoModalPrev');
        var nextBtn = document.getElementById('photoModalNext');
        if (prevBtn) prevBtn.addEventListener('click', function () { showPhoto(currentIndex - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { showPhoto(currentIndex + 1); });
    })();
    </script>
    @endif
    @endif

    {{-- Property Description --}}
    @if($val('additional_details'))
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-align-left me-2"></i>Property Description</div>
        <div class="card-body">
            <p class="field-value mb-0">{!! nl2br(e($val('additional_details'))) !!}</p>
        </div>
    </div>
    @endif

    {{-- Listing Details --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Listing Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Listing Title', $str('listing_title') ?: $auction->title) !!}
                    {!! $row('Auction Type', $str('auction_type')) !!}
                    {!! $row('Listing Status', $str('listing_status')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Listing Date', $fmtDate($str('listing_date'))) !!}
                    {!! $row('Expiration Date', $fmtDate($str('expiration_date'))) !!}
                    {!! $row('Auction Time', $str('auction_time')) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Property Details --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-house me-2"></i>Property Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Property Type', $str('property_type')) !!}
                    {!! $row('Address', $str('address')) !!}
                    {!! $row('City', $str('property_city')) !!}
                    {!! $row('County', $str('property_county')) !!}
                    {!! $row('State', $str('property_state')) !!}
                    {!! $row('ZIP Code', $str('property_zip') ?: $str('zip_code')) !!}
                    {!! $row('Bedrooms', $orOther($str('bedrooms'), $str('other_bedrooms'))) !!}
                    {!! $row('Bathrooms', $orOther($str('bathrooms'), $str('other_bathrooms'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Heated Sq Ft', $str('minimum_heated_square') ?: null) !!}
                    {!! $row('Sq Ft Heated Source', $str('sqft_heated_source')) !!}
                    {!! $row('Total Sq Ft', $str('total_square_feet')) !!}
                    {!! $row('Acreage', $str('min_acreage') ?: $str('total_acreage')) !!}
                    {!! $row('Year Built', $str('year_built')) !!}
                    {!! $row('Zoning', $str('zoning')) !!}
                    @php
                        $_cond = $str('condition_prop');
                        $_cond = $orOther($_cond, $str('other_property_condition'));
                        $_cond = ($_cond === 'Older but Clean') ? 'Older but Clean & Well Maintained' : $_cond;
                    @endphp
                    {!! $row('Property Condition', $_cond) !!}
                    {!! $row('Pool', $str('pool_needed')) !!}
                    @php
                        $poolTypeRaw  = $arr('pool_type');
                        $poolTypeList = [];
                        if (!empty($poolTypeRaw['community'])) $poolTypeList[] = 'Community';
                        if (!empty($poolTypeRaw['private']))   $poolTypeList[] = 'Private';
                    @endphp
                    {!! $row('Pool Type', count($poolTypeList) ? implode(', ', $poolTypeList) : null) !!}
                </div>
            </div>

            @php $appliances = $subOther($arr('appliances'), $str('other_appliances')); @endphp
            @if(count($appliances))
            <hr>
            <div class="row">
                <div class="col-md-6">{!! $row('Appliances', implode(', ', $appliances)) !!}</div>
            </div>
            @endif

            @php $pItems = $subOther($arr('property_items'), $str('other_property_items')); @endphp
            @if(count($pItems))
            <hr>
            <div class="mb-1"><span class="field-label">Property Items / Amenities</span></div>
            <p class="field-value">{{ implode(', ', $pItems) }}</p>
            @endif

            @php $viewPref = $subOther($arr('view_preference'), $str('other_preferences')); @endphp
            @if(count($viewPref))
            <div class="row">
                <div class="col-md-6">{!! $row('View', implode(', ', $viewPref)) !!}</div>
            </div>
            @endif

            @php $nonNegAmenities = $subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities')); @endphp
            @if(count($nonNegAmenities))
            <div class="row">
                <div class="col-md-6">{!! $row('Non-Negotiable Amenities', implode(', ', $nonNegAmenities)) !!}</div>
            </div>
            @endif

            {{-- MLS Fields --}}
            @php
                $mlsFields = [
                    ['Roof Type',             implode(', ', $subOther($arr('roof_type'),             $str('other_roof_type')))],
                    ['Exterior Construction', implode(', ', $subOther($arr('exterior_construction'),  $str('other_exterior_construction')))],
                    ['Foundation',            implode(', ', $subOther($arr('foundation'),             $str('other_foundation')))],
                    ['Heating & Fuel',        implode(', ', $subOther($arr('heating_and_fuel'),       $str('other_heating_and_fuel')))],
                    ['Air Conditioning',      implode(', ', $subOther($arr('air_conditioning'),       $str('other_air_conditioning')))],
                    ['Water',                 implode(', ', $subOther($arr('water'),                  $str('other_water')))],
                    ['Sewer',                 implode(', ', $subOther($arr('sewer'),                  $str('other_sewer')))],
                    ['Utilities',             implode(', ', $subOther($arr('utilities'),              $str('other_utilities')))],
                ];
                $mlsFields = array_filter($mlsFields, fn($f) => !empty($f[1]));
            @endphp
            @if(count($mlsFields))
            <hr>
            <div class="row">
                @foreach($mlsFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Extended Property Attributes --}}
            @php
                $extPropFields = array_filter([
                    ['Buildable',            $str('buildable')],
                    ['Ceiling Height',       $str('ceiling_height')],
                    ['Lot Dimensions',       $str('lot_dimensions')],
                    ['Front Footage',        $str('front_footage') ? $str('front_footage') . ' ft' : null],
                    ['Total Parcel Count',   $str('total_parcel_count')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($extPropFields))
            <hr>
            <div class="row">
                @foreach($extPropFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Garage & Parking --}}
            @php
                $garageFields = array_filter([
                    ['Garage',                  $str('garage_needed')],
                    ['Garage Spaces',           $str('garage_spaces') ?: $str('other_garage_needed')],
                    ['Carport',                 $str('carport_needed')],
                    ['Carport Spaces',          $str('carport_spaces') ?: $str('other_carport_needed')],
                    ['Garage/Parking Features', $str('garage_parking_spaces') ?: $str('garage_parking_spaces_option')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($garageFields))
            <hr>
            <div class="row">
                @foreach($garageFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Income / Multi-Unit Configuration --}}
            @php
                $unitFields = array_filter([
                    ['Total Number of Units',     $str('unit_number')],
                    ['Total Number of Buildings', $str('unit_buildings')],
                    ['Unit Type',                 $orOther($str('number_of_unit'), $str('number_of_unit_other'))],
                    ['Number of Unit Types',      $str('number_of_units')],
                    ['Unit Type Description',     $str('unit_type_description')],
                    ['Value Determination',       $str('value_determination')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($unitFields))
            <hr>
            <div class="row">
                @foreach($unitFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Business Info --}}
            @php
                $bizFields = array_filter([
                    ['Business Name',                    $str('business_name')],
                    ['Business Type',                    $orOther($str('business_type'), $str('other_business_type'))],
                    ['Year Established',                 $str('year_established')],
                    ['Custom Enhancements / Value-Adds', $str('custom_enhancement')],
                    ['Included Assets (Other)',          $str('assets_other')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($bizFields))
            <hr>
            <div class="row">
                @foreach($bizFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Site Utilities (land/commercial) --}}
            @php
                $siteUtilFields = array_filter([
                    ['Water Available to Site',      $orOther($str('water_available'),    $str('water_available_other'))],
                    ['Sewer Available to Site',      $orOther($str('sewer_available'),    $str('sewer_available_other'))],
                    ['Electric Available to Site',   $orOther($str('electric_available'), $str('electric_available_other'))],
                    ['Gas Available to Site',        $orOther($str('gas_available'),      $str('gas_available_other'))],
                    ['Telecom / Internet Available', $orOther($str('telecom_available'),  $str('telecom_available_other'))],
                    ['Number of Wells',              $str('number_of_wells')],
                    ['Number of Septics',            $str('number_of_septics')],
                    ['Number of Electric Meters',    $str('number_electric_meters')],
                    ['Number of Water Meters',       $str('number_water_meters')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($siteUtilFields))
            <hr>
            <div class="row">
                @foreach($siteUtilFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Pet & Leasing Policy --}}
            @php
                $petLeaseFields = array_filter([
                    ['Age-Restricted Community (55+)', $str('leasing_55_plus')],
                    ['Pets Allowed',                  $str('pets')],
                    ['Number of Pets Allowed',         $str('number_of_pets')],
                    ['Acceptable Pet Types',           $str('type_of_pets')],
                    ['Breed of Pets',                  $str('breed_of_pets')],
                    ['Max Pet Weight (lbs)',            $str('weight_of_pets')],
                    ['Breed Restrictions',             $str('breed_restrictions')],
                    ['Additional Lease Restrictions',  $str('additional_lease_restrictions')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($petLeaseFields))
            <hr>
            <div class="row">
                @foreach($petLeaseFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

        </div>
    </div>

    {{-- Sale Terms --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Sale Terms</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Sale Provision', $orOther($str('sale_provision'), $str('sale_provision_other'))) !!}
                    @if($str('sale_provision_assignment'))
                        {!! $row('Seller Under Contract for Assignment', $str('sale_provision_assignment')) !!}
                        {!! $row('Assignment Fee Type', $str('assignment_fee_type') === '$' ? 'Flat Fee' : ($str('assignment_fee_type') === '%' ? 'Percentage' : $str('assignment_fee_type'))) !!}
                        {!! $row('Assignment Fee Amount', $str('assignment_fee_type') === '$' ? $fmtMoney($str('assignment_fee_amount')) : ($str('assignment_fee_type') === '%' ? $fmtPercent($str('assignment_fee_amount')) : $str('assignment_fee_amount'))) !!}
                    @endif
                    {!! $row('Target Closing Timeframe', $str('target_closing_date')) !!}
                    {!! $row('Occupant Type', $str('occupant_status')) !!}
                    {!! $row('Occupied Until', $str('occupant_tenant')) !!}
                    {!! $row('Desired Sale Price', $fmtMoney($str('maximum_budget'))) !!}
                    {!! $row('Purchase Price', $fmtMoney($str('purchase_price'))) !!}
                    {!! $row('Starting Price', $fmtMoney($str('starting_price'))) !!}
                    {!! $row('Reserve Price', $fmtMoney($str('reserve_price'))) !!}
                    {!! $row('Buy Now Price', $fmtMoney($str('buy_now_price'))) !!}
                </div>
                <div class="col-md-6">
                    @php $ofFinancing = $subOther($arr('offered_financing'), $str('other_financing')); @endphp
                    @if(count($ofFinancing)) {!! $row('Offered Financing', implode(', ', $ofFinancing)) !!} @endif
                    @php
                        $_dpType = $str('down_payment_type');
                        $_dpAmt = $_dpType === '%' ? $fmtPercent($str('down_payment_amount')) : $fmtMoney($str('down_payment_amount'));
                    @endphp
                    {!! $row('Down Payment Amount', $_dpAmt) !!}
                    {!! $row('Buyer Sell Contract', $str('buyer_sell_contract')) !!}
                    {!! $row('Initial Deposit Requested', $fmtMoney($str('initial_deposit_requested'))) !!}
                    {!! $row('Initial Deposit Timeframe', $orOther($str('initial_deposit_timeframe'), $str('initial_deposit_timeframe_other'))) !!}
                    {!! $row('Additional Deposit Requested', $fmtMoney($str('additional_deposit_requested'))) !!}
                    {!! $row('Additional Deposit Timeframe', $orOther($str('additional_deposit_timeframe'), $str('additional_deposit_timeframe_other'))) !!}
                    {!! $row('Escrow Agent Preference', $str('escrow_agent_preference')) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Financing Details (sub-fields per offered_financing type) --}}
    @php
        $ofFin        = $arr('offered_financing');
        $hasCashFin   = in_array('Cash', $ofFin);
        $hasAssumable = in_array('Assumable', $ofFin);
        $hasCrypto    = in_array('Cryptocurrency', $ofFin);
        $hasExchange  = in_array('Exchange/Trade', $ofFin);
        $hasLeaseOpt  = in_array('Lease Option', $ofFin);
        $hasLeasePur  = in_array('Lease Purchase', $ofFin);
        $hasNFT       = in_array('Non-Fungible Token (NFT)', $ofFin);
        $hasSellerFin = in_array('Seller Financing', $ofFin);
        $showFinDetails = $hasCashFin || $hasAssumable || $hasCrypto || $hasExchange
            || $hasLeaseOpt || $hasLeasePur || $hasNFT || $hasSellerFin
            || $str('seller_financing_type') || $str('interest_rate')
            || $str('assumable_loan_type')   || $str('assumable_terms')
            || $str('lease_option_price')    || $str('lease_purchase_price')
            || $str('cryptocurrency_type')   || $str('nft_description')
            || $str('exchange_item_value')   || $str('cash_budget')
            || $str('pre_approved');
    @endphp
    @if($showFinDetails)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Financing Details</div>
        <div class="card-body">

            {{-- Cash --}}
            @if($hasCashFin || $str('cash_budget') || $str('pre_approved'))
            @if($str('cash_budget') || $str('pre_approved') || $str('pre_approval_amount'))
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Cash</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Maximum Cash Budget', $fmtMoney($str('cash_budget'))) !!}
                    {!! $row('Buyer Pre-Approved for a Loan', $str('pre_approved')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Buyer Pre-Approval Amount', $fmtMoney($str('pre_approval_amount'))) !!}
                </div>
            </div>
            @endif

            {{-- Assumable --}}
            @if($hasAssumable || $str('assumable_loan_type') || $str('assumable_terms'))
            @if($str('assumable_terms') || $str('assumable_loan_type') || $str('max_assumable_rate') || $str('max_monthly_payment') || $str('assumable_monthly_escrow') || $str('outstanding_balance') || $str('gap_payment_amount') || $str('assumable_loan_term_remaining') || $str('assumable_loan_origination_date') || $str('assumable_loan_servicer') || $str('assumable_fee_amount') || $str('assumable_occupancy_requirement'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Assumable Mortgage</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Assumable Terms', $str('assumable_terms')) !!}
                    {!! $row('Loan Type', $str('assumable_loan_type')) !!}
                    {!! $row('Interest Rate of Assumable Loan', $str('max_assumable_rate') ? $fmtPercent($str('max_assumable_rate')) : null) !!}
                    {!! $row('Monthly Payment (P&I)', $fmtMoney($str('max_monthly_payment'))) !!}
                    {!! $row('Monthly Escrow (Informational)', $fmtMoney($str('assumable_monthly_escrow'))) !!}
                    {!! $row('Outstanding Loan Balance', $fmtMoney($str('outstanding_balance'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Gap Payment Amount', $str('gap_payment_type') === '$' ? $fmtMoney($str('gap_payment_amount')) : ($str('gap_payment_type') === '%' ? $fmtPercent($str('gap_payment_amount')) : $str('gap_payment_amount'))) !!}
                    {!! $row('Loan Term Remaining', $str('assumable_loan_term_remaining')) !!}
                    {!! $row('Date Loan Originated', $str('assumable_loan_origination_date')) !!}
                    {!! $row('Loan Servicer / Lender', $str('assumable_loan_servicer')) !!}
                    {!! $row('Assumption Fee', $str('assumable_fee_type') === '%' ? $fmtPercent($str('assumable_fee_amount')) : $fmtMoney($str('assumable_fee_amount'))) !!}
                    {!! $row('Occupancy Requirement', $orOther($str('assumable_occupancy_requirement'), $str('assumable_occupancy_other'))) !!}
                </div>
            </div>
            @endif

            {{-- Cryptocurrency --}}
            @if($hasCrypto || $str('cryptocurrency_type'))
            @if($str('cryptocurrency_type') || $str('crypto_percentage') || $str('cash_percentage_crypto') || $str('crypto_exchange_method') || $str('crypto_custodian_wallet') || $str('crypto_transaction_fees') || $str('crypto_transfer_timing'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Cryptocurrency</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Cryptocurrency Type', $str('cryptocurrency_type')) !!}
                    {!! $row('Crypto % of Purchase Price', $str('crypto_percentage') ? $fmtPercent($str('crypto_percentage')) : null) !!}
                    {!! $row('Cash % of Purchase Price', $str('cash_percentage_crypto') ? $fmtPercent($str('cash_percentage_crypto')) : null) !!}
                    {!! $row('Exchange Method', $str('crypto_exchange_method')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Custodian / Wallet', $str('crypto_custodian_wallet')) !!}
                    {!! $row('Transaction Fees Responsibility', $str('crypto_transaction_fees')) !!}
                    {!! $row('Timing of Transfer', $orOther($str('crypto_transfer_timing'), $str('crypto_transfer_timing_other'))) !!}
                </div>
            </div>
            @endif

            {{-- Exchange / Trade --}}
            @if($hasExchange || $str('exchange_item_value'))
            @if($str('other_exchange_item') || $str('exchange_item_value') || $str('exchange_item_condition') || $str('additional_cash') || $str('exchange_transfer_method') || $str('exchange_liens') || $str('exchange_inspection_rights'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Exchange / Trade</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Exchange Item', $str('other_exchange_item')) !!}
                    {!! $row('Estimated Value of Exchange Item', $fmtMoney($str('exchange_item_value'))) !!}
                    {!! $row('Condition of Exchange Item', $str('exchange_item_condition')) !!}
                    {!! $row('Additional Cash Required', $fmtMoney($str('additional_cash'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Transfer Method', $str('exchange_transfer_method')) !!}
                    {!! $row('Liens / Encumbrances', $str('exchange_liens') . ($str('exchange_liens_details') ? ' – ' . $str('exchange_liens_details') : '')) !!}
                    {!! $row('Inspection / Verification Rights', $str('exchange_inspection_rights')) !!}
                </div>
            </div>
            @endif

            {{-- Lease Option --}}
            @if($hasLeaseOpt || $str('lease_option_price'))
            @if($str('lease_option_price') || $str('lease_option_payment') || $str('lease_option_duration') || $str('has_option_fee') || $str('option_fee_amount') || $str('lease_option_fee_credit') || $str('lease_option_fee_credit_percentage') || $str('lease_option_conditions') || $str('lease_option_terms') || $str('lease_option_maintenance') || $str('lease_option_extension_terms'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Lease Option</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Option Purchase Price', $fmtMoney($str('lease_option_price'))) !!}
                    {!! $row('Monthly Payment', $fmtMoney($str('lease_option_payment'))) !!}
                    {!! $row('Duration (Months)', $str('lease_option_duration')) !!}
                    {!! $row('Option Fee Offered', $yesNo($str('has_option_fee'))) !!}
                    {!! $row('Option Fee Amount', $fmtMoney($str('option_fee_amount'))) !!}
                    {!! $row('Option Fee Credit', $str('lease_option_fee_credit')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Option Fee Credit %', $str('lease_option_fee_credit_percentage') ? $fmtPercent($str('lease_option_fee_credit_percentage')) : null) !!}
                    {!! $row('Conditions / Requirements', $str('lease_option_conditions')) !!}
                    {!! $row('Specific Terms', $str('lease_option_terms')) !!}
                    {!! $row('Maintenance / Repair Responsibility', $str('lease_option_maintenance')) !!}
                    {!! $row('Extension Terms', $str('lease_option_extension_terms')) !!}
                </div>
            </div>
            @endif

            {{-- Lease Purchase --}}
            @if($hasLeasePur || $str('lease_purchase_price'))
            @if($str('lease_purchase_price') || $str('lease_purchase_payment') || $str('lease_purchase_duration') || $str('lease_purchase_rent_credit') || $str('lease_purchase_rent_credit_amount') || $str('lease_purchase_deposit') || $str('lease_purchase_conditions') || $str('lease_purchase_terms') || $str('lease_purchase_maintenance') || $str('lease_purchase_extension_terms'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Lease Purchase</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Purchase Price', $fmtMoney($str('lease_purchase_price'))) !!}
                    {!! $row('Monthly Payment', $fmtMoney($str('lease_purchase_payment'))) !!}
                    {!! $row('Duration (Months)', $str('lease_purchase_duration')) !!}
                    {!! $row('Rent Credit Toward Purchase', $str('lease_purchase_rent_credit')) !!}
                    {!! $row('Rent Credit Amount', $fmtMoney($str('lease_purchase_rent_credit_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Non-Refundable Deposit', $fmtMoney($str('lease_purchase_deposit'))) !!}
                    {!! $row('Conditions / Requirements', $str('lease_purchase_conditions')) !!}
                    {!! $row('Specific Terms', $str('lease_purchase_terms')) !!}
                    {!! $row('Maintenance / Repair Responsibility', $str('lease_purchase_maintenance')) !!}
                    {!! $row('Extension Terms', $str('lease_purchase_extension_terms')) !!}
                </div>
            </div>
            @endif

            {{-- Non-Fungible Token (NFT) --}}
            @if($hasNFT || $str('nft_description'))
            @if($str('nft_description') || $str('nft_percentage') || $str('cash_percentage_nft') || $str('nft_valuation_method') || $str('nft_transfer_method') || $str('nft_gas_fees'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Non-Fungible Token (NFT)</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('NFT Description', $str('nft_description')) !!}
                    {!! $row('NFT % of Purchase Price', $str('nft_percentage') ? $fmtPercent($str('nft_percentage')) : null) !!}
                    {!! $row('Cash % of Purchase Price', $str('cash_percentage_nft') ? $fmtPercent($str('cash_percentage_nft')) : null) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('NFT Valuation Method', $str('nft_valuation_method')) !!}
                    {!! $row('NFT Transfer Method', $str('nft_transfer_method')) !!}
                    {!! $row('Gas Fees Responsibility', $str('nft_gas_fees')) !!}
                </div>
            </div>
            @endif

            {{-- Seller Financing --}}
            @if($hasSellerFin || $str('seller_financing_type') || $str('interest_rate'))
            @if($str('seller_financing_type') || $str('seller_down_payment_amount') || $str('interest_rate') || $str('loan_duration') || $str('real_estate_purchase') || $str('prepayment_penalty_amount') || $str('balloon_payment') || $str('balloon_payment_amount') || $str('balloon_payment_date') || $str('seller_amortization_type') || $str('seller_payment_frequency') || $str('seller_late_fee_amount'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Seller Financing</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Down Payment', $fmtMoney($str('seller_down_payment_amount'))) !!}
                    {!! $row('Interest Rate', $str('interest_rate') ? $fmtPercent($str('interest_rate')) : null) !!}
                    {!! $row('Loan Duration (Years)', $str('loan_duration')) !!}
                    {!! $row('Real Estate Purchase Included', $str('real_estate_purchase')) !!}
                    {!! $row('Prepayment Penalty Amount', $fmtMoney($str('prepayment_penalty_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Balloon Payment', $yesNo($str('balloon_payment'))) !!}
                    {!! $row('Balloon Payment Amount', $fmtMoney($str('balloon_payment_amount'))) !!}
                    {!! $row('Balloon Payment Due Date', $str('balloon_payment_date')) !!}
                    {!! $row('Amortization Type', $orOther($str('seller_amortization_type'), $str('seller_amortization_other'))) !!}
                    {!! $row('Payment Frequency', $orOther($str('seller_payment_frequency'), $str('seller_payment_frequency_other'))) !!}
                    {!! $row('Late Payment Fee', $fmtMoney($str('seller_late_fee_amount'))) !!}
                </div>
            </div>
            @endif

        </div>
    </div>
    @endif

    {{-- Seller Sale Terms --}}
    @php
        $sellerTermsFields = array_filter([
            ['Inspection Period', $str('preferred_inspection_period')],
            ['Appraisal Contingency', $str('appraisal_contingency_preference')],
            ['Financing Contingency', $str('financing_contingency_preference')],
            ['Sale of Buyer Property Contingency', $str('sale_of_buyer_property_contingency')],
            ['Seller Contribution / Credit Offered', $yesNo($str('seller_contribution_credit_offered'))],
            ['Seller Contribution Details', $str('seller_contribution_amount_details')],
            ['Possession Preference', $str('possession_preference')],
            ['Possession Details', $str('possession_details')],
            ['Included Personal Property', $str('included_personal_property')],
            ['Excluded Items', $str('excluded_items')],
            ['Home Warranty Offered', $yesNo($str('home_warranty_offered'))],
            ['Home Warranty Details', $str('home_warranty_amount_details')],
            ['HOA / Condo Association Terms', $str('hoa_condo_association_terms')],
            ['Additional Seller Sale Terms', $str('additional_seller_sale_terms')],
        ], fn($f) => !empty($f[1]));
    @endphp
    @if(count($sellerTermsFields))
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-handshake me-2"></i>Seller Sale Terms</div>
        <div class="card-body">
            <div class="row">
                @foreach($sellerTermsFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Broker Compensation & Agency Agreement --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Broker Compensation &amp; Agency Agreement</div>
        <div class="card-body">

            {{-- Purchase Compensation --}}
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Buyer\'s Broker Commission Structure', $str('commission_structure')) !!}
                    @if($str('commission_structure_type') === 'Flat Fee')
                        {!! $row('Buyer\'s Broker Commission Fee', $fmtMoney($str('commission_structure_type_fee_flat'))) !!}
                    @elseif($str('commission_structure_type') === 'Percentage of the Total Purchase Price')
                        {!! $row('Buyer\'s Broker Commission Fee', $fmtPercent($str('commission_structure_type_fee_percentage'))) !!}
                    @elseif($str('commission_structure_type') === 'Percentage of the Total Purchase Price + Flat Fee')
                        @php
                            $_bbPct = $fmtPercent($str('commission_structure_type_fee_percentage_combo'));
                            $_bbFlat = $fmtMoney($str('commission_structure_type_fee_flat_combo'));
                            $_bbCombo = ($_bbPct && $_bbFlat) ? $_bbPct . ' + ' . $_bbFlat : ($_bbPct ?: $_bbFlat);
                        @endphp
                        {!! $row('Buyer\'s Broker Commission Fee', $_bbCombo) !!}
                    @elseif($str('commission_structure_type') === 'other')
                        {!! $row('Buyer\'s Broker Commission Fee', $str('commission_structure_type_fee_other')) !!}
                    @endif
                </div>
                <div class="col-md-6">
                    {!! $row('Seller\'s Broker Purchase Fee Type', $str('purchase_fee_type')) !!}
                    @if($str('purchase_fee_type') === 'percentage')
                        {!! $row('Seller\'s Broker Purchase Fee', $fmtPercent($str('purchase_fee_percentage'))) !!}
                    @elseif($str('purchase_fee_type') === 'flat')
                        {!! $row('Seller\'s Broker Purchase Fee', $fmtMoney($str('purchase_fee_flat'))) !!}
                    @elseif($str('purchase_fee_type') === 'combo')
                        @php
                            $_sbPct = $fmtPercent($str('purchase_fee_percentage_combo'));
                            $_sbFlat = $fmtMoney($str('purchase_fee_flat_combo'));
                            $_sbCombo = ($_sbPct && $_sbFlat) ? $_sbPct . ' + ' . $_sbFlat : ($_sbPct ?: $_sbFlat);
                        @endphp
                        {!! $row('Seller\'s Broker Purchase Fee', $_sbCombo) !!}
                    @elseif($str('purchase_fee_type') === 'other')
                        {!! $row('Seller\'s Broker Purchase Fee', $str('purchase_fee_other')) !!}
                    @endif
                    {!! $row('Nominal Consideration Fee', $fmtMoney($str('nominal'))) !!}
                </div>
            </div>

            {{-- Leasing Compensation --}}
            @if($str('interested_purchase_fee_type'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Leasing Compensation</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Interested in Offering a Lease Agreement', $str('interested_purchase_fee_type')) !!}
                    @if($str('interested_purchase_fee_type') === 'Yes')
                        {!! $row('Seller\'s Broker Leasing Fee Type', $str('seller_leasing_fee_type')) !!}
                        @if($str('seller_leasing_fee_type') === 'Percentage of the Gross Lease Value')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross'))) !!}
                            {!! $row('Sales Tax', $str('sales_tax_option_gross')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Percentage of the Rent Due Each Rental Period')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_rental'))) !!}
                        @elseif(in_array($str('seller_leasing_fee_type'), ["Percentage of the First Month's Rent", "Percentage of Month's Rent"]))
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_month_rent'))) !!}
                            {!! $row('Sales Tax', $str('seller_leasing_gross_sales_tax_first_month')) !!}
                            {!! $row('Number of Months', $str('seller_leasing_gross_no_of_months')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Flat Fee')
                            {{-- Flat Fee: stored as seller_leasing_gross_purchase_fee_flat_amount --}}
                            {!! $row('Leasing Fee', $fmtMoney($str('seller_leasing_gross_purchase_fee_flat_amount'))) !!}
                            {!! $row('Sales Tax', $str('seller_leasing_gross_sales_tax_flat_free_gross')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Flat Fee + Percentage of the Gross Lease Value')
                            {!! $row('Leasing Fee', $fmtMoney($str('seller_leasing_gross_flat_combo')) . ' + ' . $fmtPercent($str('seller_leasing_gross_percentage_combo'))) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Flat Fee + Percentage of the Net Aggregate Rent')
                            {!! $row('Leasing Fee', $fmtMoney($str('seller_leasing_gross_flat_net_combo')) . ' + ' . $fmtPercent($str('seller_leasing_gross_percentage_net_combo'))) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Percentage of Gross Rent')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_percentage'))) !!}
                            {!! $row('Sales Tax', $str('seller_leasing_gross_sales_tax_option_gross')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Percentage of Net Aggregate Rent')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_other'))) !!}
                        @elseif($str('seller_leasing_fee_type') === 'other')
                            {!! $row('Leasing Fee', $str('seller_leasing_gross_purchase_fee_other')) !!}
                        @endif
                    @endif
                </div>
            </div>
            @endif

            {{-- Lease-Option Compensation --}}
            @if($str('interested_lease_option_agreement'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Lease-Option Compensation</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Interested in Lease-Option Agreement', $str('interested_lease_option_agreement')) !!}
                    @if($str('interested_lease_option_agreement') === 'Yes')
                        @php
                            $leaseTypeLabel = $str('lease_type') === 'flat' ? 'Flat Fee' : ($str('lease_type') === 'percent' ? 'Percentage' : $str('lease_type'));
                            $leaseValFmt = $str('lease_type') === 'flat' ? $fmtMoney($str('lease_value')) : ($str('lease_type') === 'percent' ? $fmtPercent($str('lease_value')) : $str('lease_value'));
                            $purchaseTypeLabel = $str('purchase_type') === 'flat' ? 'Flat Fee' : ($str('purchase_type') === 'percent' ? 'Percentage' : $str('purchase_type'));
                            $purchaseValFmt = $str('purchase_type') === 'flat' ? $fmtMoney($str('purchase_value')) : ($str('purchase_type') === 'percent' ? $fmtPercent($str('purchase_value')) : $str('purchase_value'));
                        @endphp
                        {!! $row('Lease-Option Creation Fee', ($leaseTypeLabel ? $leaseTypeLabel . ': ' : '') . $leaseValFmt) !!}
                        {!! $row('If Purchase Option Exercised', ($purchaseTypeLabel ? $purchaseTypeLabel . ': ' : '') . $purchaseValFmt) !!}
                    @endif
                </div>
            </div>
            @endif

            {{-- Agency Agreement --}}
            <hr>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Brokerage Relationship', $str('brokerage_relationship')) !!}
                    {!! $row('Agency Agreement Timeframe', $orOther($str('agency_agreement_timeframe'), $str('agency_agreement_custom'))) !!}
                    {!! $row('Protection Period (Days)', $str('protection_period')) !!}
                    {!! $row('Broker\'s Share of Retained Deposits', $str('retained_deposits') !== '' && $str('retained_deposits') !== null ? $fmtPercent($str('retained_deposits')) : null) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Retainer Fee', $yesNo($str('retainer_fee_option'))) !!}
                    {!! $row('Retainer Fee Amount', $fmtMoney($str('retainer_fee_amount'))) !!}
                    {!! $row('Retainer Fee Application', $str('retainer_fee_application')) !!}
                    {!! $row('Early Termination Fee', $yesNo($str('early_termination_fee_option'))) !!}
                    {!! $row('Early Termination Fee Amount', $fmtMoney($str('early_termination_fee_amount'))) !!}
                    {!! $row('Additional Broker Details', $str('additional_details_broker')) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Financial Details (Income / Commercial / Business property types only) --}}
    {{-- Fields: minimum_annual_net_income, minimum_cap_rate, gross_annual_income, annual_operating_expenses,
         rent_roll_available, operating_statement_available (Income);
         price_per_sqft, existing_lease_type, other_lease_type, lease_expiration, lease_assignable (Commercial);
         annual_revenue, gross_profit, sde_ebitda, inventory_value, ffe_value, reason_for_sale,
         other_reason_for_sale, employee_count, financial_statements_available, tax_returns_available,
         nda_required, business_location_leased + sub-fields (Business) --}}
    @php
        $finPropType = $str('property_type');
        $hasFinancial = in_array($finPropType, ['Income', 'Commercial', 'Business'])
            && ($str('minimum_annual_net_income') || $str('minimum_cap_rate') || $str('gross_annual_income')
                || $str('annual_operating_expenses') || $str('rent_roll_available') || $str('operating_statement_available')
                || $str('price_per_sqft') || $str('existing_lease_type') || $str('lease_expiration') || $str('lease_assignable')
                || $str('annual_revenue') || $str('gross_profit') || $str('sde_ebitda') || $str('inventory_value')
                || $str('ffe_value') || $str('reason_for_sale') || $str('employee_count')
                || $str('financial_statements_available') || $str('tax_returns_available') || $str('nda_required')
                || $str('business_location_leased'));
    @endphp
    @if($hasFinancial)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-chart-line me-2"></i>Financial Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Annual Net Income', $fmtMoney($str('minimum_annual_net_income'))) !!}
                    {!! $row('Cap Rate', $fmtPercent($str('minimum_cap_rate'))) !!}
                    @if($finPropType === 'Income')
                        {!! $row('Gross Annual Income', $fmtMoney($str('gross_annual_income'))) !!}
                        {!! $row('Annual Operating Expenses', $fmtMoney($str('annual_operating_expenses'))) !!}
                        {!! $row('Rent Roll Available', $str('rent_roll_available')) !!}
                        {!! $row('Operating Statement Available', $str('operating_statement_available')) !!}
                    @elseif($finPropType === 'Commercial')
                        {!! $row('Price Per Square Foot', $fmtMoney($str('price_per_sqft'))) !!}
                        {!! $row('Existing Lease Type', $orOther($str('existing_lease_type'), $str('other_lease_type'))) !!}
                        {!! $row('Lease Expiration Date', $str('lease_expiration')) !!}
                        {!! $row('Lease Assignable to Buyer', $str('lease_assignable')) !!}
                    @elseif($finPropType === 'Business')
                        {!! $row('Annual Revenue', $fmtMoney($str('annual_revenue'))) !!}
                        {!! $row('Gross Profit', $fmtMoney($str('gross_profit'))) !!}
                        {!! $row('SDE / EBITDA', $fmtMoney($str('sde_ebitda'))) !!}
                        {!! $row('Inventory Value', $fmtMoney($str('inventory_value'))) !!}
                        {!! $row('FF&E Value', $fmtMoney($str('ffe_value'))) !!}
                        {!! $row('Reason for Sale', $orOther($str('reason_for_sale'), $str('other_reason_for_sale'))) !!}
                        {!! $row('Number of Employees', $str('employee_count')) !!}
                        {!! $row('Financial Statements Available', $str('financial_statements_available')) !!}
                        {!! $row('Tax Returns Available', $str('tax_returns_available')) !!}
                        {!! $row('NDA Required to Access Financials', $str('nda_required')) !!}
                        {!! $row('Business Location Leased', $str('business_location_leased')) !!}
                        @if($str('business_location_leased') === 'Yes')
                            {!! $row('Monthly Rent', $fmtMoney($str('business_lease_monthly_rent'))) !!}
                            {!! $row('Lease Expiration Date', $str('business_lease_expiration')) !!}
                            {!! $row('Lease Renewal Options', $str('business_lease_renewal_options')) !!}
                            {!! $row('Lease Assignable to Buyer', $str('business_lease_assignable')) !!}
                            {!! $row('Additional Lease Terms', $str('business_lease_additional_terms')) !!}
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Tax, Legal, HOA & Disclosures --}}
    @php
        $hasTaxLegal = $str('parcel_id') || $str('annual_property_taxes') || $str('legal_description') || $str('flood_zone_code') || $str('has_cdd') || $str('has_hoa');
    @endphp
    @if($hasTaxLegal)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-landmark me-2"></i>Tax, Legal, HOA &amp; Disclosures</div>
        <div class="card-body">
            <h6 class="fw-semibold mt-3 mb-2">Tax &amp; Legal</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Parcel ID', $str('parcel_id')) !!}
                    {!! $row('Tax Year', $str('tax_year')) !!}
                    {!! $row('Annual Property Taxes', $fmtMoney($str('annual_property_taxes'))) !!}
                    {!! $row('Additional Parcels', $yesNo($str('additional_parcels'))) !!}
                    {!! $row('Additional Parcel IDs', $str('additional_parcel_ids')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Legal Description', $str('legal_description')) !!}
                    {!! $row('Flood Zone Code', $orOther($str('flood_zone_code'), $str('flood_zone_code_other'))) !!}
                    {!! $row('Flood Insurance Required', $yesNo($str('flood_insurance_required'))) !!}
                    {!! $row('Flood Zone Panel', $str('flood_zone_panel')) !!}
                </div>
            </div>

            @if($str('has_cdd'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">CDD / Special Assessments</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Has CDD', $yesNo($str('has_cdd'))) !!}
                    {!! $row('Annual CDD Fee', $fmtMoney($str('annual_cdd_fee'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Has Special Assessments', $yesNo($str('has_special_assessments'))) !!}
                    {!! $row('Special Assessment Amount', $fmtMoney($str('special_assessment_amount'))) !!}
                    {!! $row('Special Assessment Description', $str('special_assessment_description')) !!}
                </div>
            </div>
            @endif

            @if($str('has_hoa'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">HOA / Association</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Has HOA', $yesNo($str('has_hoa'))) !!}
                    {!! $row('Association Type', $orOther($str('association_type'), $str('association_type_other'))) !!}
                    {!! $row('Association Name', $str('association_name')) !!}
                    @php
                        $_freq = $str('association_fee_frequency');
                        $_freqDisplay = $_freq ? (' / ' . $orOther($_freq, $str('association_fee_frequency_other'))) : '';
                    @endphp
                    {!! $row('Association Fee', $fmtMoney($str('association_fee_amount')) . $_freqDisplay) !!}
                    {!! $row('Application Fee', $fmtMoney($str('association_application_fee'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Approval Required', $yesNo($str('association_approval_required'))) !!}
                    {!! $row('Approval Process', $str('association_approval_process')) !!}
                    {!! $row('Leasing Restrictions', $yesNo($str('leasing_restrictions'))) !!}
                    {!! $row('Min Lease Period', $orOther($str('min_lease_period'), $str('min_lease_period_other'))) !!}
                    {!! $row('Max Leases / Year', $str('max_leases_per_year')) !!}
                    {!! $row('Pet Restrictions', $yesNo($str('pet_restrictions'))) !!}
                    {!! $row('Pet Restriction Details', $str('pet_restrictions_detail')) !!}
                </div>
            </div>
            @php
                $assocAmenities = $subOther($arr('association_amenities'), $str('association_amenities_other'));
                $assocIncludes  = $subOther($arr('association_fee_includes'), $str('association_fee_includes_other'));
            @endphp
            @if(count($assocIncludes))
            <div class="row">
                <div class="col-md-6">{!! $row('Fee Includes', implode(', ', $assocIncludes)) !!}</div>
            </div>
            @endif
            @if(count($assocAmenities))
            <div class="row">
                <div class="col-md-6">{!! $row('Association Amenities', implode(', ', $assocAmenities)) !!}</div>
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- Documents & Disclosures --}}
    @php
        $disclosures = [
            ['Seller Disclosure', 'seller_disclosure_available', 'seller_disclosure_file_path'],
            ['Survey', 'survey_available', 'survey_file_path'],
            ['Inspection Report', 'inspection_report_available', 'inspection_report_file_path'],
            ['HOA / Condo Docs', 'hoa_condo_docs_available', 'hoa_condo_docs_file_path'],
            ['Flood Disclosure', 'flood_disclosure_available', 'flood_disclosure_file_path'],
            ['Lead-Based Paint Disclosure', 'lead_based_paint_disclosure', 'lead_based_paint_file_path'],
            ['Environmental Report', 'environmental_report_available', 'environmental_report_file_path'],
        ];
        $hasAnyDisclosure = false;
        foreach ($disclosures as $d) {
            if ($str($d[1]) || $str($d[2])) { $hasAnyDisclosure = true; break; }
        }
        $docRows = $arr('doc_rows');
        $addDocNames = $arr('additional_documents');
    @endphp
    @if($hasAnyDisclosure || count($docRows) || count($addDocNames))
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-folder-open me-2"></i>Documents &amp; Disclosures</div>
        <div class="card-body">
            <div class="row">
            @foreach($disclosures as $d)
            @if($str($d[1]) || $str($d[2]))
            <div class="col-md-6 mb-2 d-flex align-items-baseline gap-2 flex-wrap">
                <span class="field-label">{{ $d[0] }}</span>
                @if($str($d[1]))
                    <span class="badge bg-light text-dark border">{{ $str($d[1]) }}</span>
                @endif
                @if($str($d[2]))
                    <a href="{{ asset('storage/' . $str($d[2])) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                @endif
            </div>
            @endif
            @endforeach
            </div>
            @if(count($addDocNames))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Additional Documents Available</h6>
            <div class="row">
                @foreach($addDocNames as $addDocName)
                <div class="col-md-6 mb-2 d-flex align-items-baseline gap-2 flex-wrap">
                    <span class="field-label">{{ $addDocName }}</span>
                    <span class="badge bg-light text-dark border">Available</span>
                </div>
                @endforeach
            </div>
            @endif
            @if(count($docRows))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Additional Documents</h6>
            <ul class="list-unstyled mb-0">
                @foreach($docRows as $dr)
                <li class="mb-1">
                    <i class="fa-solid fa-file me-1 text-muted"></i>
                    {{ $dr['type'] ?? $dr['label'] ?? 'Document' }}
                    @if(!empty($dr['file_path']))
                        <a href="{{ asset('storage/' . $dr['file_path']) }}" target="_blank" class="ms-2 btn btn-sm btn-outline-secondary py-0">
                            <i class="fa-solid fa-download me-1"></i>Download
                        </a>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </div>
    </div>
    @endif

    {{-- Agent Credentials & Contact Info --}}
    @php
        $hasContact = $str('first_name') || $str('last_name') || $str('email') || $str('phone_number') || $str('agent_brokerage') || $str('agent_license_number') || $str('agent_nar_member_id');
    @endphp
    @if($hasContact)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-id-card me-2"></i>Contact Information</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Name', trim($str('first_name') . ' ' . $str('last_name'))) !!}
                    {!! $row('Email', $str('email')) !!}
                    @php
                        $phone = $str('phone_number');
                        if ($phone && strlen(preg_replace('/\D/', '', $phone)) === 10) {
                            $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
                        }
                    @endphp
                    {!! $row('Phone', $phone) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Brokerage', $str('agent_brokerage')) !!}
                    {!! $row('License Number', $str('agent_license_number')) !!}
                    {!! $row('NAR Member ID', $str('agent_nar_member_id')) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Button (bottom) --}}
    @if(auth()->id() == $auction->user_id)
    <div class="text-end mt-2 mb-4">
        <a href="{{ route('offer.listing.seller.edit', ['auctionId' => $auction->id]) }}"
           class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
        </a>
    </div>
    @endif

</div>
@endsection
