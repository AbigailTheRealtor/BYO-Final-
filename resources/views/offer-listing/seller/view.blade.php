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
        if (is_string($v)) { $d = json_decode($v, true); return is_array($d) ? $d : []; }
        return is_array($v) ? $v : [];
    };
    $yesNo = fn($v) => match((string)$v) { '1','true','yes','Yes' => 'Yes', '0','false','no','No' => 'No', default => $v };
    $row = function($label, $value) {
        if ($value === null || $value === '' || $value === false) return '';
        return '<div class="row mb-1"><div class="col-sm-5 text-muted fw-semibold">' . e($label) . '</div><div class="col-sm-7">' . e($value) . '</div></div>';
    };
@endphp

@push('styles')
<style>
    .section-card { margin-bottom: 1.5rem; border-radius: 0.5rem; border: 1px solid #dee2e6; }
    .section-card .card-header { background: #f1f5f9; font-weight: 700; font-size: 1.05rem; padding: 0.75rem 1rem; }
    .section-card .card-body { padding: 1rem 1.25rem; }
    .field-label { color: #6c757d; font-weight: 600; font-size: 0.875rem; }
    .field-value { font-size: 0.925rem; }
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
            @if(!empty($meta['address']))
                <p class="text-muted mb-0"><i class="fa-solid fa-location-dot me-1"></i>{{ $meta['address'] }}</p>
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
            @if($str('video_tour_url'))
                <p><span class="field-label">Video Tour URL:</span>
                    <a href="{{ $str('video_tour_url') }}" target="_blank" rel="noopener">{{ $str('video_tour_url') }}</a>
                </p>
            @endif
            @if($str('virtual_tour_url'))
                <p><span class="field-label">Virtual Tour URL:</span>
                    <a href="{{ $str('virtual_tour_url') }}" target="_blank" rel="noopener">{{ $str('virtual_tour_url') }}</a>
                </p>
            @endif

            @if(count($propertyPhotos))
            <div class="d-flex flex-wrap gap-2 mt-3">
                @foreach($propertyPhotos as $idx => $photo)
                @php
                    $filename = is_array($photo) ? ($photo['filename'] ?? '') : $photo;
                    $isCover  = is_array($photo) && !empty($photo['is_cover']);
                @endphp
                @if($filename)
                <div class="text-center">
                    <img src="{{ asset('storage/auction/images/' . $filename) }}"
                         alt="Property photo {{ $idx + 1 }}"
                         class="photo-thumb"
                         onerror="this.style.display='none'">
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
    @endif

    {{-- Listing Details --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Listing Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Listing Title', $str('listing_title') ?: $auction->title) !!}
                    {!! $row('Service Type', $str('service_type')) !!}
                    {!! $row('Auction Type', $str('auction_type')) !!}
                    {!! $row('Listing Status', $str('listing_status')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Listing Date', $str('listing_date')) !!}
                    {!! $row('Expiration Date', $str('expiration_date')) !!}
                    {!! $row('Auction Time', $str('auction_time')) !!}
                    {!! $row('Working with Agent', $str('working_with_agent')) !!}
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
                    {!! $row('Bedrooms', $str('bedrooms') . ($str('other_bedrooms') ? ' (' . $str('other_bedrooms') . ')' : '')) !!}
                    {!! $row('Bathrooms', $str('bathrooms') . ($str('other_bathrooms') ? ' (' . $str('other_bathrooms') . ')' : '')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Heated Sq Ft', $str('minimum_heated_square') ? ('≥ ' . $str('minimum_heated_square') . ' sq ft') : null) !!}
                    {!! $row('Total Sq Ft', $str('total_square_feet')) !!}
                    {!! $row('Acreage', $str('min_acreage') ?: $str('total_acreage')) !!}
                    {!! $row('Year Built', $str('year_built')) !!}
                    {!! $row('Zoning', $str('zoning')) !!}
                    {!! $row('Property Condition', $str('condition_prop') . ($str('other_property_condition') ? ' – ' . $str('other_property_condition') : '')) !!}
                    {!! $row('Pool', $str('pool_needed')) !!}
                    {!! $row('Video/Tour Link', $str('video_tour_url') ?: $str('virtual_tour_url')) !!}
                </div>
            </div>

            @php $pItems = $arr('property_items'); @endphp
            @if(count($pItems))
            <hr>
            <div class="mb-1"><span class="field-label">Property Items / Amenities</span></div>
            <p class="field-value">{{ implode(', ', $pItems) }}</p>
            @endif

            @php $appliances = $arr('appliances'); @endphp
            @if(count($appliances))
            <div class="mb-1"><span class="field-label">Appliances</span></div>
            <p class="field-value">{{ implode(', ', $appliances) }}
                @if($str('other_appliances')) — {{ $str('other_appliances') }} @endif
            </p>
            @endif

            {{-- MLS Fields --}}
            @php
                $mlsFields = [
                    ['Roof Type', implode(', ', $arr('roof_type'))],
                    ['Exterior Construction', implode(', ', $arr('exterior_construction'))],
                    ['Foundation', implode(', ', $arr('foundation'))],
                    ['Heating & Fuel', implode(', ', $arr('heating_and_fuel'))],
                    ['Air Conditioning', implode(', ', $arr('air_conditioning'))],
                    ['Water', implode(', ', $arr('water'))],
                    ['Sewer', implode(', ', $arr('sewer'))],
                    ['Utilities', implode(', ', $arr('utilities'))],
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
        </div>
    </div>

    {{-- Sale Terms --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Sale Terms</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Sale Provision', $str('sale_provision') . ($str('sale_provision_other') ? ' – ' . $str('sale_provision_other') : '')) !!}
                    {!! $row('Purchase Price', $fmtMoney($str('purchase_price'))) !!}
                    {!! $row('Starting Price', $fmtMoney($str('starting_price'))) !!}
                    {!! $row('Reserve Price', $fmtMoney($str('reserve_price'))) !!}
                    {!! $row('Buy Now Price', $fmtMoney($str('buy_now_price'))) !!}
                    {!! $row('Maximum Budget', $fmtMoney($str('maximum_budget'))) !!}
                </div>
                <div class="col-md-6">
                    @php $ofFinancing = $arr('offered_financing'); @endphp
                    @if(count($ofFinancing)) {!! $row('Offered Financing', implode(', ', $ofFinancing)) !!} @endif
                    {!! $row('Down Payment Type', $str('down_payment_type')) !!}
                    {!! $row('Down Payment Amount', $fmtMoney($str('down_payment_amount'))) !!}
                    {!! $row('Buyer Sell Contract', $str('buyer_sell_contract')) !!}
                    {!! $row('Initial Deposit Requested', $yesNo($str('initial_deposit_requested'))) !!}
                    {!! $row('Initial Deposit Timeframe', $str('initial_deposit_timeframe') . ($str('initial_deposit_timeframe_other') ? ' – ' . $str('initial_deposit_timeframe_other') : '')) !!}
                    {!! $row('Additional Deposit Requested', $yesNo($str('additional_deposit_requested'))) !!}
                    {!! $row('Escrow Agent Preference', $str('escrow_agent_preference')) !!}
                </div>
            </div>
        </div>
    </div>

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
        <div class="card-header"><i class="fa-solid fa-percent me-2"></i>Broker Compensation &amp; Agency Agreement</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Commission Structure', $str('commission_structure')) !!}
                    {!! $row('Purchase Fee Type', $str('purchase_fee_type')) !!}
                    {!! $row('Purchase Fee %', $fmtPercent($str('purchase_fee_percentage'))) !!}
                    {!! $row('Purchase Fee Flat', $fmtMoney($str('purchase_fee_flat'))) !!}
                    {!! $row('Lease Fee Type', $str('lease_fee_type')) !!}
                    {!! $row('Lease Fee %', $fmtPercent($str('lease_fee_percentage'))) !!}
                    {!! $row('Lease Fee Flat', $fmtMoney($str('lease_fee_flat'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Brokerage Relationship', $str('brokerage_relationship')) !!}
                    {!! $row('Agency Agreement Timeframe', $str('agency_agreement_timeframe') . ($str('agency_agreement_custom') ? ' – ' . $str('agency_agreement_custom') : '')) !!}
                    {!! $row('Protection Period', $str('protection_period')) !!}
                    {!! $row('Retainer Fee', $yesNo($str('retainer_fee_option'))) !!}
                    {!! $row('Retainer Fee Amount', $fmtMoney($str('retainer_fee_amount'))) !!}
                    {!! $row('Early Termination Fee', $yesNo($str('early_termination_fee_option'))) !!}
                    {!! $row('Early Termination Fee Amount', $fmtMoney($str('early_termination_fee_amount'))) !!}
                    {!! $row('Additional Broker Details', $str('additional_details_broker')) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Tax, Legal, HOA & Disclosures --}}
    @php
        $hasTaxLegal = $str('parcel_id') || $str('annual_property_taxes') || $str('legal_description') || $str('flood_zone_code') || $str('has_cdd') || $str('has_hoa');
    @endphp
    @if($hasTaxLegal)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-landmark me-2"></i>Tax, Legal, HOA &amp; Disclosures</div>
        <div class="card-body">
            <h6 class="fw-semibold mb-2">Tax &amp; Legal</h6>
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
                    {!! $row('Flood Zone Code', $str('flood_zone_code') . ($str('flood_zone_code_other') ? ' – ' . $str('flood_zone_code_other') : '')) !!}
                    {!! $row('Flood Insurance Required', $yesNo($str('flood_insurance_required'))) !!}
                    {!! $row('Flood Zone Panel', $str('flood_zone_panel')) !!}
                </div>
            </div>

            @if($str('has_cdd'))
            <hr>
            <h6 class="fw-semibold mb-2">CDD / Special Assessments</h6>
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
            <h6 class="fw-semibold mb-2">HOA / Association</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Has HOA', $yesNo($str('has_hoa'))) !!}
                    {!! $row('Association Type', $str('association_type') . ($str('association_type_other') ? ' – ' . $str('association_type_other') : '')) !!}
                    {!! $row('Association Name', $str('association_name')) !!}
                    {!! $row('Association Fee', $fmtMoney($str('association_fee_amount')) . ($str('association_fee_frequency') ? ' / ' . $str('association_fee_frequency') : '')) !!}
                    {!! $row('Application Fee', $fmtMoney($str('association_application_fee'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Approval Required', $yesNo($str('association_approval_required'))) !!}
                    {!! $row('Approval Process', $str('association_approval_process')) !!}
                    {!! $row('Leasing Restrictions', $yesNo($str('leasing_restrictions'))) !!}
                    {!! $row('Min Lease Period', $str('min_lease_period') . ($str('min_lease_period_other') ? ' – ' . $str('min_lease_period_other') : '')) !!}
                    {!! $row('Max Leases / Year', $str('max_leases_per_year')) !!}
                    {!! $row('Pet Restrictions', $yesNo($str('pet_restrictions'))) !!}
                    {!! $row('Pet Restriction Details', $str('pet_restrictions_detail')) !!}
                </div>
            </div>
            @php $assocAmenities = $arr('association_amenities'); $assocIncludes = $arr('association_fee_includes'); @endphp
            @if(count($assocIncludes))
                {!! $row('Fee Includes', implode(', ', $assocIncludes) . ($str('association_fee_includes_other') ? ' – ' . $str('association_fee_includes_other') : '')) !!}
            @endif
            @if(count($assocAmenities))
                {!! $row('Association Amenities', implode(', ', $assocAmenities) . ($str('association_amenities_other') ? ' – ' . $str('association_amenities_other') : '')) !!}
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
    @endphp
    @if($hasAnyDisclosure || count($docRows))
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-folder-open me-2"></i>Documents &amp; Disclosures</div>
        <div class="card-body">
            <div class="row">
            @foreach($disclosures as $d)
            @if($str($d[1]) || $str($d[2]))
            <div class="col-md-6 mb-2">
                <span class="field-label">{{ $d[0] }}</span>
                @if($str($d[1]))
                    <span class="ms-1 badge bg-light text-dark border">{{ $str($d[1]) }}</span>
                @endif
                @if($str($d[2]))
                    <a href="{{ asset('storage/' . $str($d[2])) }}" target="_blank" class="ms-1 btn btn-sm btn-outline-secondary py-0">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                @endif
            </div>
            @endif
            @endforeach
            </div>
            @if(count($docRows))
            <hr>
            <h6 class="fw-semibold mb-2">Additional Documents</h6>
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
        $hasContact = $str('first_name') || $str('email') || $str('phone_number') || $str('agent_brokerage') || $str('agent_license_number');
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
