@extends('layouts.main')

{{-- Combined Fee Display Helper Functions (display-only, no storage changes) --}}
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

  $rentalPeriodSuffix = 'of Rent Due Each Rental Period';

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };

  $canon = function($str) {
    if (!is_string($str)) return $str;
    return str_replace(["\xe2\x80\x99", "\xe2\x80\x98", "\xe2\x80\x9c", "\xe2\x80\x9d"], ["'", "'", '"', '"'], $str);
  };

  // Determine if property is Residential or Commercial (case-insensitive, handles variations)
  $propertyType = strtolower(trim($auction->get->property_type ?? ''));
  $isResidential = str_contains($propertyType, 'residential') || 
                   str_contains($propertyType, 'single-family') || 
                   str_contains($propertyType, 'single family') ||
                   str_contains($propertyType, 'condo') ||
                   str_contains($propertyType, 'townhouse') ||
                   str_contains($propertyType, 'apartment');
  $isCommercial = str_contains($propertyType, 'commercial') || 
                  str_contains($propertyType, 'industrial') ||
                  str_contains($propertyType, 'office') ||
                  str_contains($propertyType, 'retail') ||
                  str_contains($propertyType, 'warehouse');
  // Default to Residential if neither is explicitly set
  if (!$isResidential && !$isCommercial && !empty($propertyType)) {
      $isResidential = true;
  }
@endphp

@push('styles')
<!-- //Listing Description css  -->
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
<!-- Toastr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">

<style>
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    /* Firefox */
    input[type=number] {
        -moz-appearance: textfield;
    }

    .fa-dollar-sign,
    .fa-percent {
        padding: 0 20px;
        background: #facd34;
        color: #fff;
        border: 0;
        font-weight: 700 !important;
        line-height: 39px !important;
        margin-right: -5px;
        z-index: 1;
        border-radius: 3px 0 0 3px;
    }

    .form-control,
    .form-select {
        border-radius: 0.25rem;
        box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
        border-radius: 0.25rem;
        background-color: #fafafb;
        margin-bottom: 15px;
    }

    /* Section Title Hierarchy - Larger, bold, spaced, more prominent */
    .card-header h4,
    .section-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        color: #0f1a24;
    }

    /* SECTION HEADER BAR — shorter + true vertical centering */
    .card-header.section-header {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start;
        padding: 12px 18px !important;
        min-height: 0 !important;
        margin-top: 1.25rem;
    }

    /* SECTION TITLE TEXT — remove default heading spacing */
    .section-header .section-title {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1 !important;
        display: block;
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #0f1a24;
    }

    /* Services section - extra breathing room before header */
    .services-section-header {
        margin-top: 0.75rem !important;
    }

    hr {
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }

    /* Field row styling - improved line-height for scan-readability */
    .col-md-12.col-12.pt-2.fw-bold {
        line-height: 1.6;
        padding-top: 0.6rem !important;
        padding-bottom: 0.2rem;
    }

    .field-row {
        padding: 0.5rem 0;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .field-label {
        font-weight: 600;
        color: #34465c;
    }

    .field-value {
        font-weight: normal;
        color: #34465c;
    }

    /* Broker Compensation subsection headers - breathing room */
    h5.mt-3.mb-2 {
        padding-top: 0.75rem;
        margin-top: 1rem !important;
    }

    /* Fix blank space under section headers - reduce gap to first content */
    .card-body {
        padding-top: 12px !important;
    }

    .card-body > :first-child {
        margin-top: 0 !important;
    }

    /* Broker Compensation section text - match other section text color */
    .broker-compensation-section,
    .broker-compensation-section p,
    .broker-compensation-section .col-md-12,
    .broker-compensation-section .fw-bold {
        color: #34465c !important;
    }

    ul {
        --icon-size: 1em;
        --gutter: .5em;
        padding: 0 0 0 calc(var(--icon-size) + 2em);
    }

    ul li {
        padding-left: var(--gutter);
        color: #34465c;
    }

    ul:not(.services) li::marker {
        content: "\f101";
        /* FontAwesome Unicode */
        font-family: FontAwesome;
        font-size: var(--icon-size);
        /* color: #006e9f; */
        color: #11b7cf;
    }

    /* Services section - Tighter spacing and indentation */
    ul.services {
        list-style: none !important;
        padding-left: 1.2em;
        margin-top: 0.35rem;
        margin-bottom: 0.5rem;
    }

    ul.services li {
        padding: 0.15rem 0;
        color: #34465c;
        position: relative;
        padding-left: 0;
        list-style: none !important;
        line-height: 1.4;
    }

    ul.services li::marker {
        content: none !important;
    }

    ul.services li::before {
        content: "•";
        position: absolute;
        left: -0.9em;
        color: #34465c;
        font-size: 1.1em;
    }

    .removeBold {
        font-weight: normal;
    }

    /* Base button style */
    .btn-custom {
        width: 100% !important;
        color: white !important;
        border: none;
        padding: 10px 20px;
        min-width: 120px;
        font-weight: 500;
        border-radius: 4px;
        cursor: pointer;
        text-align: center;
        display: inline-block;
    }

    .biding-btn {
        width: 31.5%;
    }

    /* Accept (green) - always solid green background */
    .btn-accept {
        background-color: #28a745 !important;
        color: #ffffff !important;
    }

    .btn-accept:hover {
        background-color: #218838 !important;
    }

    /* Reject (red) - always solid red background */
    .btn-reject {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }

    .btn-reject:hover {
        background-color: #c82333 !important;
    }

    /* Counter (blue) - always solid blue background */
    .btn-counter {
        background-color: #0d6efd !important;
        color: #ffffff !important;
    }

    .btn-counter:hover {
        background-color: #0b5ed7 !important;
    }

    .view-btn {
        padding: 6px !important;
    }

    .services-offered {
        padding: 23px !important;
    }

    @media screen and (max-width: 800px) {
        .accordion-body-padding {
            padding: 7px !important;
        }

        .alert-font {
            font-size: 10px;
        }

        .counter-font {
            font-size: 15px;
        }
    }

    /* Bid card accordion chevron rotation (custom JS toggle) */
    .bid-accordion-header .bid-chevron {
        transition: transform 0.3s ease;
    }
    .bid-accordion-header:hover {
        background-color: #f8f9fa !important;
    }

    /* Bid action buttons - matched sizing for Edit bid */
    .bid-action-btn {
        min-width: 140px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.875rem;
        border: none !important;
        box-shadow: none;
    }
    .bid-action-btn:hover {
        opacity: 0.9;
    }
</style>
@endpush


@section('content')
@php
$auth_id = auth()->user() ? auth()->user()->id : 0;
@endphp
@if (session('success'))
<div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
@endif
@if (session('error'))
<div class="container mt-3">
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
@endif

<div class="container listingDescription">
    <div class="row">
        <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
            <div class="card description">
                <div class="card-header">
                    <h4 style="margin-left: 15px; margin-top: 10px;">Listing Details: </h4>
                </div>
                <div class="card-body">
                    <div class="row" style="flex-wrap: wrap;">
                        @if (@$auction->get->listing_title != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Title
                            <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->working_with_agent != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Current Representation Status with Broker:
                            <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->desired_agent_hire_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Desired Agent Hire Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->listing_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->listing_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->expiration_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Expiration Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->expiration_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->auction_type != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Type:
                            <span class="removeBold"> {{ @$auction->get->auction_type }}
                            </span>
                        </div>
                        @endif

                        @if (strtolower(trim($auction->get->auction_type ?? '')) === 'bidding period' && @$auction->get->auction_time != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Bidding Period Length:
                            <span class="removeBold"> {{ @$auction->get->auction_time }}
                            </span>
                        </div>
                        @endif
                        @if (@$auction->get->meeting_Preference != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Meeting Preference:
                            <span class="removeBold"> {{ @$auction->get->meeting_Preference }}
                            </span>
                        </div>
                        @endif

                    </div>
                    <hr>
                    <div class="card-header">
                        <h4>Property Details: </h4>
                    </div>

                    <div class="row" style="flex-wrap: wrap;">

                        @php
                            $stripState = function($str) {
                                return trim(preg_replace('/,\s*[A-Z]{2}$/', '', $str));
                            };

                            $propertyCityVal = @$auction->get->property_city;
                            $propertyCountyVal = @$auction->get->property_county;
                            $propertyStateVal = @$auction->get->property_state ?: @$auction->get->state;
                            $propertyZipVal = @$auction->get->property_zip ?: @$auction->get->zip_code;

                            $rawCities = @$auction->get->cities;
                            if (is_string($rawCities)) { $rawCities = json_decode($rawCities, true); }
                            $rawCities = is_array($rawCities) ? $rawCities : [];
                            $cleanCities = array_map(function($city) use ($stripState) {
                                return $stripState($city);
                            }, array_filter($rawCities));

                            $rawCounties = @$auction->get->counties;
                            if (is_string($rawCounties)) { $rawCounties = json_decode($rawCounties, true); }
                            $rawCounties = is_array($rawCounties) ? $rawCounties : [];
                            $cleanCounties = array_map(function($county) use ($stripState) {
                                return $stripState($county);
                            }, array_filter($rawCounties));

                            $stateVal = null;
                            $rawStates = @$auction->get->states;
                            if (is_string($rawStates)) { $rawStates = json_decode($rawStates, true); }
                            if (is_array($rawStates) && !empty($rawStates)) {
                                $stateVal = implode('; ', $rawStates);
                            } elseif (!empty(@$auction->get->state)) {
                                $stateVal = @$auction->get->state;
                            }

                            $rawZips = @$auction->get->zipCodes;
                            if (is_string($rawZips)) { $rawZips = json_decode($rawZips, true); }
                            $rawZips = is_array($rawZips) ? array_filter($rawZips) : [];
                        @endphp

                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyCityVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            City:
                            <span class="removeBold">{{ $stripState($propertyCityVal) }}</span>
                        </div>
                        @endif
                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyCountyVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            County:
                            <span class="removeBold">{{ $stripState($propertyCountyVal) }}</span>
                        </div>
                        @endif
                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyStateVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            State:
                            <span class="removeBold">{{ $propertyStateVal }}</span>
                        </div>
                        @endif
                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyZipVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Zip Code:
                            <span class="removeBold">{{ $propertyZipVal }}</span>
                        </div>
                        @endif

                        @if (!empty($cleanCities))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Cities:
                            @foreach ($cleanCities as $city)
                                <span class="removeBold badge bg-secondary">{{ $city }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if (!empty($cleanCounties))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Counties:
                            @foreach ($cleanCounties as $county)
                                <span class="removeBold badge bg-secondary">{{ $county }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if (!empty($stateVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable State:
                            <span class="removeBold">{{ $stateVal }}</span>
                        </div>
                        @endif
                        @if (!empty($rawZips))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Zip Code:
                            @foreach ($rawZips as $zip)
                                <span class="removeBold badge bg-secondary">{{ $zip }}</span>
                            @endforeach
                        </div>
                        @endif

                        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->property_type))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Property Type:
                            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::normalizePropertyType(@$auction->get->property_type) }}</span>
                        </div>
                        @endif
                        @php
                            $landlordPropertyStyleItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->property_items);
                        @endphp
                        @if (!empty($landlordPropertyStyleItems))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Property Style:
                            <span class="removeBold">{{ implode(', ', $landlordPropertyStyleItems) }}</span>
                        </div>
                        @endif


                        {{-- <div class="col-md-12 col-12 pt-2 fw-bold">
                              Property Type :<span class="removeBold"> {{ @$auction->get->property_type }}</span><br>
                        @if (gettype(@$auction->get->property_items) == 'array')
                        @foreach (@$auction->get->property_items as $item)
                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                        @endforeach
                        @endif
                    </div>

                    @if (@$auction->get->property_items != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"> Property Style:
                        <span class="removeBold">{{ @$auction->get->property_items }}</span>
                    </div>
                    @endif --}}
                    @php
                        $rawLandlordCondition = @$auction->get->condition_prop_buyer;
                        if (empty($rawLandlordCondition)) {
                            $rawLandlordCondition = @$auction->get->condition_prop;
                        }
                        $landlordConditionItems = \App\Helpers\ListingDisplayHelper::normalizeList(
                            $rawLandlordCondition,
                            @$auction->get->other_property_condition
                        );
                        if (empty($landlordConditionItems) && !empty($rawLandlordCondition)) {
                            $landlordConditionItems = is_array($rawLandlordCondition) ? $rawLandlordCondition : [$rawLandlordCondition];
                        }
                        $landlordConditionLabelMap = [
                            'Older but Well Maintained'           => 'Older but Clean & Well Maintained',
                            'Older but clean & well maintained'   => 'Older but Clean & Well Maintained',
                        ];
                        $landlordConditionItems = array_map(function($item) use ($landlordConditionLabelMap) {
                            return $landlordConditionLabelMap[$item] ?? $item;
                        }, $landlordConditionItems);
                    @endphp
                    @if (!empty($landlordConditionItems))
                    <div class="col-md-12 col-12 pt-2 fw-bold"> Property Condition:
                        <span class="removeBold">{{ implode(', ', $landlordConditionItems) }}</span>
                    </div>
                    @endif

                    {{-- @if (@$auction->get->property_type != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Property Type:
                                    <span class="removeBold">{{ @$auction->get->property_type }}</span>
                </div>
                @endif

                @if (@$auction->get->property_items != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i
                        class="fa-regular fa-check-square"></i>Property Style:
                    <span class="removeBold">{{ @$auction->get->property_items }}</span>
                </div>
                @endif --}}

                {{-- <div class="col-md-12 col-12 pt-2 fw-bold">
                                Property
                                Condition:
                                @if (gettype(@$auction->get->condition_prop_buyer) == 'array')
                                    @foreach (array_filter(@$auction->get->condition_prop_buyer) as $item)
                                        <span class="removeBold"> {{ $item }}</span>
                @if ($item == 'Other')
                <span class="removeBold"> {{ @$auction->get->other_property_condition }}</span>
                @endif
                @endforeach
                @endif

            </div> --}}

            @if (@$auction->get->bedrooms != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Bedrooms:
                <span class="removeBold">{{ @$auction->get->bedrooms !== 'Other' ? @$auction->get->bedrooms : @$auction->get->other_bedrooms }}</span>
            </div>
            @endif
            @php
                $bathroomDisplay = @$auction->get->bathrooms !== 'Other' ? @$auction->get->bathrooms : @$auction->get->other_bathrooms;
            @endphp
            @if (\App\Helpers\ListingDisplayHelper::hasValue($bathroomDisplay))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Bathrooms:
                <span class="removeBold">{{ $bathroomDisplay }}</span>
            </div>
            @endif

            @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Heated SqFt:
                <span class="removeBold">

                    {{ str_replace(',', '', $auction->get->minimum_heated_square ?? '') }}


                </span>
            </div>
            @endif
            @if (@$auction->get->minimum_leaseable != null && @$auction->get->minimum_leaseable != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Net Leasable SqFt:
                <span class="removeBold">

                    {{ str_replace(',', '', $auction->get->minimum_leaseable ?? '') }}
                </span>
            </div>
            @endif
            @if (@$auction->get->total_square_feet != null && @$auction->get->total_square_feet != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Total SqFt:
                <span class="removeBold">

                    {{ str_replace(',', '', $auction->get->total_square_feet ?? '') }}
                </span>
            </div>
            @endif
            @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                SqFt Heated Source:
                <span class="removeBold">
                    {{ str_replace(',', '', $auction->get->sqft_heated_source ?? '') }}
                </span>
            </div>
            @endif
            @if (@$auction->get->total_acreage != null && @$auction->get->total_acreage != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Total Acreage:
                <span class="removeBold">
                    {{ @$auction->get->total_acreage != '' ? @$auction->get->total_acreage : '' }}</span>
            </div>
            @endif
            @if (!empty($auction->get->appliances) && is_array($auction->get->appliances) && count($auction->get->appliances) > 0)
            @php
            $appliancesToShow = [];
            foreach ($auction->get->appliances as $appliance) {
            if ($appliance !== 'Other') {
            $appliancesToShow[] = $appliance;
            }
            }
            if (!empty($auction->get->other_appliances)) {
            $appliancesToShow[] = $auction->get->other_appliances;
            }
            @endphp

            @if (count($appliancesToShow) > 0)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Appliances Included:

                @foreach ($appliancesToShow as $appliance)
                <span class="removeBold badge bg-secondary">
                    {{ $appliance }}
                </span>
                @endforeach
            </div>
            @endif
            @endif


            @if ($isResidential)
            @php
                $tenantRequireRaw = @$auction->get->tenant_require;
                $tenantRequireVal = is_string($tenantRequireRaw) ? trim(trim($tenantRequireRaw, '"')) : '';
            @endphp
            @if (!empty($tenantRequireVal) && $tenantRequireVal !== 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Furnishings:
                <span class="removeBold">
                    <span class="removeBold badge bg-secondary">{{ $tenantRequireVal }}</span>
                </span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->carport_needed))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Carport:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->carport_needed, @$auction->get->other_carport_needed, 'Spaces') }}</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->garage_needed))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Garage:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->garage_needed, @$auction->get->other_garage_needed, 'Spaces') }}</span>
            </div>
            @endif
            @endif

            @if ($isCommercial)
            @php
                $parkingRaw = @$auction->get->garage_parking_spaces_option;
                $parkingOther = @$auction->get->other_parking_space_wrapper;
                $parkingOtherStr = is_string($parkingOther) ? trim(trim((string)$parkingOther), '"') : '';
                $parkingOtherHasValue = $parkingOtherStr !== '' && $parkingOtherStr !== 'null';
                $parkingItems = [];
                if (!empty($parkingRaw)) {
                    if (is_string($parkingRaw)) {
                        $decoded = json_decode($parkingRaw, true);
                        $parkingItems = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$parkingRaw];
                    } elseif (is_array($parkingRaw)) {
                        $parkingItems = $parkingRaw;
                    }
                }
                $parkingResult = [];
                $foundParkingOther = false;
                foreach ($parkingItems as $pItem) {
                    $pVal = trim((string)$pItem);
                    $pVal = trim($pVal, '"');
                    if ($pVal === '' || \App\Helpers\ListingDisplayHelper::isPlaceholder($pVal)) continue;
                    if (strtolower($pVal) === 'other') {
                        $foundParkingOther = true;
                        if ($parkingOtherHasValue) {
                            $parkingResult[] = $parkingOtherStr;
                        }
                        continue;
                    }
                    $parkingResult[] = $pVal;
                }
                if (!$foundParkingOther && $parkingOtherHasValue) {
                    $parkingResult[] = $parkingOtherStr;
                }
            @endphp
            @if (!empty($parkingResult))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Garage/Parking Features:
                @if (count($parkingResult) === 1)
                <span class="removeBold">{{ $parkingResult[0] }}</span>
                @else
                @foreach ($parkingResult as $feature)
                <span class="removeBold badge bg-secondary">{{ $feature }}</span>
                @endforeach
                @endif
            </div>
            @endif
            @endif


            @if ($isResidential)
            @php
            // Normalize pool_type to an array of key => bool
            $poolTypeRaw = optional($auction->get)->pool_type;
            if (is_string($poolTypeRaw)) {
            $poolTypeRaw = json_decode($poolTypeRaw, true);
            }
            if (is_object($poolTypeRaw)) {
            $poolTypeRaw = (array) $poolTypeRaw;
            }
            $poolTypeRaw = is_array($poolTypeRaw) ? $poolTypeRaw : [];

            // Format pool types with proper capitalization
            $poolTypes = collect($poolTypeRaw)
            ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
            ->keys()
            ->map(function($type) {
            // Handle specific capitalization cases
            $capitalized = [
            'private' => 'Private',
            'community' => 'Community',
            'indoor' => 'Indoor',
            'outdoor' => 'Outdoor',
            'heated' => 'Heated',
            'saltwater' => 'Saltwater'
            ];

            return $capitalized[strtolower($type)] ?? ucwords($type);
            })
            ->implode(', ');
            @endphp

            @if (optional($auction->get)->pool_needed === 'Yes')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Pool:
                <span class="removeBold">
                    @if (!empty($poolTypes))
                    Yes ({{ $poolTypes }})
                    @else
                    Yes
                    @endif
                </span>
            </div>
            @elseif (optional($auction->get)->pool_needed === 'No')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Pool:
                <span class="removeBold">No</span>
            </div>
            @elseif (optional($auction->get)->pool_needed === 'Optional')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Pool:
                <span class="removeBold">Optional</span>
            </div>
            @endif
            @endif


            @php
                $viewPrefItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->view_preference, @$auction->get->other_preferences);
            @endphp
            @if (!empty($viewPrefItems))
            <div class="col-md-12 col-12 pt-2 fw-bold"> View Preference:
                @foreach ($viewPrefItems as $item)
                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
            @endif
            @if (@$auction->get->leasing_55_plus)

            <div class="col-md-12 col-12 pt-2 fw-bold">
                Age-Restricted Community:
                <span class="removeBold">
                    {{ @$auction->get->leasing_55_plus != '' ? @$auction->get->leasing_55_plus : '' }}</span>
            </div>

            @endif
            @php
                $amenityItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->non_negotiable_amenities, @$auction->get->other_non_negotiable_amenities);
            @endphp
            @if (!empty($amenityItems))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Amenities and Property Features:
                @foreach ($amenityItems as $item)
                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
            @endif

            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->pets))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Pets Allowed:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets) }}</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->type_of_pets))
            <div class="col-md-12 col-12 pt-2 fw-bold"> Acceptable Pet Types:
                <span class="removeBold">{{ @$auction->get->type_of_pets }}</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->weight_of_pets))
            <div class="col-md-12 col-12 pt-2 fw-bold"> Maximum Weight Per Pet (lbs):
                <span class="removeBold">{{ @$auction->get->weight_of_pets }} lbs</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->breed_restrictions))
            <div class="col-md-12 col-12 pt-2 fw-bold"> Pet Restrictions:
                <span class="removeBold">{{ @$auction->get->breed_restrictions }}</span>
            </div>
            @endif
            @endif

        </div>
        <hr>
        <div class="card-header">
            <h4>Leasing Terms: </h4>
        </div>
        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->occupant_status))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">  Occupant Type:
                <span class="removeBold">{{ @$auction->get->occupant_status }}</span>
            </div>
        </div>
        @endif
        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->occupant_tenant))
            <div class="row" style="flex-wrap: wrap;">
                <div class="col-12 fw-bold pt-2">  Occupied Until:
                    <span class="removeBold">
                        @php
                            $date = \Carbon\Carbon::parse($auction->get->occupant_tenant);
                            echo $date->format('F j, Y');
                        @endphp
                    </span>
                </div>
            </div>
        @endif

        @php
            $lsType = trim(@$auction->get->leasing_spaces ?? '');
            $hlp = \App\Helpers\ListingDisplayHelper::class;
            // Resolve storage fields based on listing type and leasing space type
            $lsStorageIncluded = null;
            $lsStorageSize = null;
            if ($isCommercial) {
                if ($lsType === 'Single Room') {
                    $lsStorageIncluded = $hlp::hasValue(@$auction->get->included_storage_space_com_single)
                        ? $auction->get->included_storage_space_com_single
                        : ($hlp::hasValue(@$auction->get->included_storage_space_res_single) ? $auction->get->included_storage_space_res_single : @$auction->get->included_storage_space);
                    $lsStorageSize = $hlp::hasValue(@$auction->get->storage_space_com_single)
                        ? $auction->get->storage_space_com_single
                        : ($hlp::hasValue(@$auction->get->storage_space_res_single) ? $auction->get->storage_space_res_single : @$auction->get->storage_space);
                } else {
                    $lsStorageIncluded = $hlp::hasValue(@$auction->get->included_storage_space_com_entire)
                        ? $auction->get->included_storage_space_com_entire
                        : @$auction->get->included_storage_space;
                    $lsStorageSize = $hlp::hasValue(@$auction->get->storage_space_com_entire)
                        ? $auction->get->storage_space_com_entire
                        : @$auction->get->storage_space;
                }
            } else {
                $lsStorageIncluded = $hlp::hasValue(@$auction->get->included_storage_space_res_both)
                    ? $auction->get->included_storage_space_res_both
                    : ($hlp::hasValue(@$auction->get->included_storage_space_res_single)
                        ? $auction->get->included_storage_space_res_single
                        : @$auction->get->included_storage_space);
                $lsStorageSize = $hlp::hasValue(@$auction->get->storage_space_res_both)
                    ? $auction->get->storage_space_res_both
                    : ($hlp::hasValue(@$auction->get->storage_space_res_single)
                        ? $auction->get->storage_space_res_single
                        : @$auction->get->storage_space);
            }
        @endphp

        @if ($hlp::hasValue($lsType))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">  Leasing Space:
                <span class="removeBold">{{ $lsType }}</span>
            </div>
        </div>
        @endif

        @if ($lsType === 'Single Room')
        {{-- Single Room: strict ordered fields for both Residential and Commercial --}}
        {{-- 2. Guests are --}}
        @if ($hlp::hasValue(@$auction->get->guests_allowed))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Guests are:
                <span class="removeBold">{{ $auction->get->guests_allowed }}</span>
            </div>
        </div>
        @endif
        {{-- 3. Restrictions Include --}}
        @if ($hlp::hasValue(@$auction->get->restrictions))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Restrictions Include:
                <span class="removeBold">{{ $auction->get->restrictions }}</span>
            </div>
        </div>
        @endif
        {{-- 4. Shared Areas Available --}}
        @if ($hlp::hasValue(@$auction->get->common_areas_access))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Shared Areas Available:
                <span class="removeBold">{{ $auction->get->common_areas_access }}</span>
            </div>
        </div>
        @endif
        {{-- 5. Maintenance and Repairs Are Handled By --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_by))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance and Repairs Are Handled By:
                <span class="removeBold">{{ $auction->get->maintenance_by }}</span>
            </div>
        </div>
        @endif
        {{-- 6. Maintenance Response Time --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_response_time))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance Response Time:
                <span class="removeBold">{{ $auction->get->maintenance_response_time }}</span>
            </div>
        </div>
        @endif
        {{-- 7. Utilities --}}
        @if ($hlp::hasValue(@$auction->get->utilities))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Utilities:
                <span class="removeBold">{{ $auction->get->utilities }}</span>
            </div>
        </div>
        @endif
        {{-- 8. Common Area Maintenance --}}
        @if ($hlp::hasValue(@$auction->get->common_areas_cleaning))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Common Area Maintenance:
                <span class="removeBold">{{ $auction->get->common_areas_cleaning }}</span>
            </div>
        </div>
        @endif
        {{-- 9. Included Storage Space --}}
        @if ($hlp::hasValue($lsStorageIncluded))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $lsStorageIncluded }}</span>
            </div>
        </div>
        @endif
        {{-- 10. Storage Space Size --}}
        @if ($hlp::hasValue($lsStorageSize))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $lsStorageSize }}</span>
            </div>
        </div>
        @endif
        {{-- 11. Bathroom Facilities --}}
        @if ($hlp::hasValue(@$auction->get->bathroom_facilities))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Bathroom Facilities:
                <span class="removeBold">{{ $auction->get->bathroom_facilities }}</span>
            </div>
        </div>
        @endif
        {{-- 12. Approximate Room Size --}}
        @if ($hlp::hasValue(@$auction->get->room_size))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Approximate Room Size:
                <span class="removeBold">{{ $auction->get->room_size }}</span>
            </div>
        </div>
        @endif

        @elseif ($lsType === 'Entire Property')
        {{-- Entire Property: strict ordered fields for both Residential and Commercial --}}
        {{-- 2. Restrictions Include --}}
        @if ($hlp::hasValue(@$auction->get->restrictions))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Restrictions Include:
                <span class="removeBold">{{ $auction->get->restrictions }}</span>
            </div>
        </div>
        @endif
        {{-- 3. Maintenance and Repairs Are Handled By --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_by))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance and Repairs Are Handled By:
                <span class="removeBold">{{ $auction->get->maintenance_by }}</span>
            </div>
        </div>
        @endif
        {{-- 4. Maintenance Response Time --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_response_time))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance Response Time:
                <span class="removeBold">{{ $auction->get->maintenance_response_time }}</span>
            </div>
        </div>
        @endif
        {{-- 5. Included Storage Space --}}
        @if ($hlp::hasValue($lsStorageIncluded))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $lsStorageIncluded }}</span>
            </div>
        </div>
        @endif
        {{-- 6. Storage Space Size --}}
        @if ($hlp::hasValue($lsStorageSize))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $lsStorageSize }}</span>
            </div>
        </div>
        @endif
        {{-- 7. Shared Amenities Include --}}
        @if ($hlp::hasValue(@$auction->get->shared_amenities))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Shared Amenities Include:
                <span class="removeBold">{{ $auction->get->shared_amenities }}</span>
            </div>
        </div>
        @endif
        {{-- 8. Building Hours --}}
        @if ($hlp::hasValue(@$auction->get->building_hours))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Building Hours:
                <span class="removeBold">{{ $auction->get->building_hours }}</span>
            </div>
        </div>
        @endif
        {{-- 9. 24/7 Access Available --}}
        @if ($hlp::hasValue(@$auction->get->access_24_7))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">24/7 Access Available:
                <span class="removeBold">{{ $auction->get->access_24_7 }}</span>
            </div>
        </div>
        @endif
        {{-- 10. Zoning Allows --}}
        @if ($hlp::hasValue(@$auction->get->zoning_allows))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Zoning Allows:
                <span class="removeBold">{{ $auction->get->zoning_allows }}</span>
            </div>
        </div>
        @endif
        {{-- 11. Space Features --}}
        @if ($hlp::hasValue(@$auction->get->space_features))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Space Features:
                <span class="removeBold">{{ $auction->get->space_features }}</span>
            </div>
        </div>
        @endif
        {{-- 12. Neighboring Tenants Include --}}
        @if ($hlp::hasValue(@$auction->get->neighboring_tenants))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Neighboring Tenants Include:
                <span class="removeBold">{{ $auction->get->neighboring_tenants }}</span>
            </div>
        </div>
        @endif
        @endif

        @php
            $tenantPayItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->tenant_pays, @$auction->get->other_tenant_pays);
            $ownerPayItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->owner_pays, @$auction->get->other_owner_pays);

            $rawTermsOfLease = $auction->get->terms_of_lease ?? null;
            $termsOfLease = is_string($rawTermsOfLease)
            ? (json_decode($rawTermsOfLease, true) ?? [])
            : (is_array($rawTermsOfLease) ? $rawTermsOfLease : []);
        @endphp

        @if (!empty($tenantPayItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Tenant Responsible For:
                @foreach ($tenantPayItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if ($isCommercial && !empty($ownerPayItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Owner Responsible For:
                @foreach ($ownerPayItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @php
            $leaseTermItems = \App\Helpers\ListingDisplayHelper::normalizeList($termsOfLease, @$auction->get->custom_lease_term);
        @endphp
        @if (!empty($leaseTermItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Terms of Lease:
                @foreach ($leaseTermItems as $lt)
                    <span class="removeBold badge bg-secondary">{{ $lt }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->desired_rental_amount))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Desired Rental Amount:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::fmtMoney(@$auction->get->desired_rental_amount) }}</span>
            </div>
        </div>
        @endif
        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->lease_amount_frequency))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Lease Amount Frequency:
                <span class="removeBold">{{ @$auction->get->lease_amount_frequency }}</span>
            </div>
        </div>
        @endif

        @php
            $desiredLeaseTermItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->desired_lease_length, @$auction->get->other_lease_term);
        @endphp
        @if (!empty($desiredLeaseTermItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">Desired Lease Term:
                @foreach ($desiredLeaseTermItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif
        @php
            $rawRentIncludes = @$auction->get->rent_includes;
            $rawRentIncludesStr = is_string($rawRentIncludes) ? trim(str_replace('"', '', $rawRentIncludes)) : '';
            $isRentNone = (is_array($rawRentIncludes) && count($rawRentIncludes) === 1 && strtolower(trim($rawRentIncludes[0])) === 'none')
                || strtolower($rawRentIncludesStr) === 'none'
                || (is_string($rawRentIncludes) && json_decode($rawRentIncludes, true) === ['None']);
            $rentIncludesItems = $isRentNone ? [] : \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->rent_includes, @$auction->get->other_rent_include);
        @endphp
        @if ($isRentNone)
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">Rent Includes:
                <span class="removeBold">None</span>
            </div>
        </div>
        @elseif (!empty($rentIncludesItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">Rent Includes:
                @foreach ($rentIncludesItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif


        <hr>

        @php
        // Check if services exist before showing the section
        $hasServices = !empty(@$auction->get->services) || !empty(@$auction->get->other_services);

        // Photo enhancements data — needed inside the services loop
        $rawPhotoEnhancements = $auction->get->photo_enhancements ?? null;
        $photoEnhancements = is_string($rawPhotoEnhancements)
            ? (json_decode($rawPhotoEnhancements, true) ?? [])
            : (is_array($rawPhotoEnhancements) ? $rawPhotoEnhancements : []);
        $customEnhancement = $auction->get->custom_enhancement ?? null;
        $enhancementOrder = [
            'Basic edits (brightness, contrast, cropping)',
            'Twilight conversion (convert daytime photo to sunset look)',
            'Object removal (e.g., cars, trash cans, furniture, etc.)',
            'Virtual twilight photography',
            'Color correction or sky replacement',
            'Other',
        ];
        @endphp

        @if ($hasServices)
        <div class="card-header section-header services-section-header">
            <h4 class="section-title">Services: </h4>
        </div>

        @php
        // Landlord Residential service categories (exact match with listing creation form)
        $landlordResidentialCategories = [
            "📢 Rental Marketing & Listing Promotion" => [
                "List the property on the local Multiple Listing Service (MLS)",
                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                "Create a branded flyer featuring the property's key highlights",
                "Post the property on Facebook Marketplace",
                "Post the property on Craigslist in the appropriate \"Homes for Rent\" category",
                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                "Promote the listing on Facebook in Housing or Rental Groups",
                "Share the listing on Instagram using posts, stories, or reels",
                "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                "Upload a TikTok video walkthrough of the property",
                "Upload a YouTube video walkthrough of the property",
                "Launch a mass email campaign promoting the listing",
                "Distribute printed flyers or postcards in target geographic areas",
                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
            ],
            "📋 Listing Presentation & Preparation" => [
                "Conduct a property walkthrough and provide recommendations for listing readiness",
                "Provide a custom listing preparation checklist",
                "Collect property details and prepare MLS remarks and a public listing description",
                "Provide a visual consultation for interior layout, cleanliness, and presentation",
                "Provide a curb appeal consultation focused on exterior presentation",
                "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only — no endorsement or warranty is made",
            ],
            "📸 Photography, Video & Virtual Media" => [
                "Provide professional property photography",
                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                "Provide a video walkthrough tour",
                "Provide a 3D virtual tour",
                "Provide virtual staging (digital enhancements only; no physical staging)",
                "Provide digital photo enhancements",
                "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
            ],
            "🏡 Showings & Access Coordination" => [
                "Ensure proper notice is given if the property is occupied",
                "Install a real estate sign on the property",
                "Install a lockbox for Agent access",
                "Schedule and attend showings with prospective Tenants",
                "Coordinate showings with Tenant's Agents",
                "Collect and relay feedback to the Landlord after showings",
            ],
            "📝 Tenant Application Support" => [
                "Provide a link to an online application platform with third-party screening tools (e.g., credit, background, and eviction checks)",
                "Ensure compliance with Fair Housing laws and screening regulations throughout the application process",
                "Collect and organize application documents submitted by prospective Tenants",
                "Verify basic information provided in the application (e.g., employment, income, and references)",
                "Present complete and organized application packages to the Landlord for review and final selection",
            ],
            "📃 Lease Preparation & Execution" => [
                "Review lease offers submitted by prospective Tenants and summarize key terms",
                "Coordinate lease negotiation with the Tenant or Tenant's Agent",
                "Prepare a state-specific lease agreement using approved forms or templates",
                "Assist with completing required lease disclosures and reviewing key lease terms",
                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                "Confirm receipt of required move-in funds and assist the Landlord in verifying amounts due, payment deadlines, and accepted payment methods",
            ],
            "🚚 Move-In Support & Coordination" => [
                "Coordinate move-in date and key handoff logistics with the Tenant or Tenant's Agent",
                "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                "Verify receipt of all required move-in funds prior to occupancy (e.g., deposit, rent, pet fees)",
                "Provide a utility setup checklist and local provider resources for the Tenant",
                "Share a move-in checklist for documentation and property condition review",
            ],
            "📑 Property Management" => [
                "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
            ],
            "💡 Leasing Strategy & Guidance" => [
                "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                "Advise on lease types and structures (e.g., month-to-month, annual, furnished, corporate, lease-option)",
                "Provide general guidance on Landlord obligations and Tenant rights under state law",
                "Provide general guidance on rental demand, local market conditions, and Tenant expectations",
            ],
        ];

        // Landlord Commercial service categories (exact match with listing creation form)
        $landlordCommercialCategories = [
            "📢 Rental Marketing & Listing Promotion" => [
                "List the property on the local Multiple Listing Service (MLS)",
                "List the property on Crexi.com",
                "List the property on LoopNet.com",
                "Create a branded flyer featuring the property's key highlights",
                "Post the property on Craigslist under the \"Office/Commercial\" category",
                "Promote the listing on Facebook in Commercial Leasing or Business Startup Groups",
                "Share the listing on Instagram using photos, stories, or reels",
                "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                "Upload a TikTok video walkthrough of the property",
                "Upload a YouTube video walkthrough of the property",
                "Launch a mass email campaign promoting the listing",
                "Distribute printed flyers or postcards in target geographic areas",
                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
            ],
            "📋 Listing Presentation & Preparation" => [
                "Conduct a property walkthrough and provide recommendations for listing readiness",
                "Provide a custom listing preparation checklist",
                "Collect property details such as lease terms, square footage, property features, and allowable uses",
                "Prepare a marketing packet including zoning, cap rate references, and permitted uses",
                "Provide a visual consultation focused on interior layout, cleanliness, and presentation",
                "Provide a curb appeal consultation for exterior appearance and signage opportunities",
                "Provide referrals to third-party vendors (e.g., cleaners, sign installers, minor repair vendors). Vendor fees billed separately. Referrals only — no endorsement or warranty is made",
            ],
            "📸 Photography, Video & Virtual Media" => [
                "Provide professional property photography",
                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                "Provide a video walkthrough tour",
                "Provide a 3D virtual tour",
                "Provide virtual staging (digital enhancements only; no physical staging)",
                "Provide digital photo enhancements",
                "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
            ],
            "🏢 Showings & Access Coordination" => [
                "Ensure proper notice is given if the property is occupied",
                "Install a real estate sign on the property",
                "Install a lockbox for Agent access",
                "Schedule and attend showings with prospective Tenants",
                "Coordinate showings with Tenant's Agents",
                "Collect and relay showing feedback to the Landlord",
            ],
            "📝 Tenant Application Support" => [
                "Provide a link to an online application platform or share instructions with prospective Tenants or Tenant's Agents",
                "Ensure compliance with applicable federal, state, and local commercial leasing and anti-discrimination laws",
                "Collect and organize application documents (e.g., business licenses, financials, entity records, references)",
                "Verify basic information provided in the application (e.g., business operations, income sources, references)",
                "Present complete application packages to the Landlord for review and final selection",
            ],
            "📃 Lease Preparation, LOI & Execution" => [
                "Coordinate lease negotiation with the Tenant or Tenant's Agent",
                "Collect and organize Letters of Intent (LOIs) or draft lease proposals",
                "Draft or assist with execution of the final lease agreement using approved forms or templates",
                "Provide and review required lease disclosures and addenda based on state or municipal requirements",
                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                "Verify receipt of required deposits and track rent commencement and key lease dates to ensure move-in readiness",
            ],
            "🚚 Move-In Support & Coordination" => [
                "Coordinate move-in date and key handoff logistics with the Tenant or Tenant's Agent",
                "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or improvements",
                "Verify receipt of all required move-in funds and documents prior to occupancy (e.g., rent, security deposit, insurance certificates)",
                "Provide a utility setup checklist and local provider resources for the Tenant",
                "Share a move-in checklist for documentation and property condition review",
                "Assist with coordination of move-in logistics, including Certificate of Insurance (COI) and vendor access (as agreed)",
            ],
            "📑 Property Management" => [
                "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
            ],
            "💡 Leasing Strategy & Guidance" => [
                "Provide a Comparable Lease Analysis with pricing recommendations based on similar properties, local vacancy trends, and current market conditions",
                "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                "Provide general guidance on Landlord obligations and Tenant rights under applicable commercial leasing laws",
                "Provide general guidance on zoning, permitted uses, occupancy standards, or rent escalation terms",
            ],
        ];

        $landlordCategories = $isCommercial ? $landlordCommercialCategories : $landlordResidentialCategories;
        $allServices = is_array(@$auction->get->services) ? $auction->get->services : [];
        $otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];
        @endphp

        <div class="col-md-12 col-12 pt-2">
            @foreach ($landlordCategories as $categoryName => $categoryServices)
                @php
                    $matchedServices = [];
                    foreach ($categoryServices as $catalogService) {
                        $canonCatalog = trim($canon($catalogService));
                        foreach ($allServices as $savedService) {
                            if (trim($canon($savedService)) === $canonCatalog) {
                                $matchedServices[] = $savedService;
                                break;
                            }
                        }
                    }
                @endphp
                @if (!empty($matchedServices))
                <div class="mt-3">
                    <strong>{{ $categoryName }}</strong>
                    <ul class="services">
                        @foreach ($matchedServices as $service)
                        <li style="font-size: 16px;">{{ $service }}</li>
                        @if (trim($canon($service)) === 'Provide digital photo enhancements' && !empty($photoEnhancements))
                            <ul style="padding-left: 1.5rem; margin: 4px 0;">
                                @foreach ($enhancementOrder as $enh)
                                    @if (in_array($enh, $photoEnhancements))
                                        @if ($enh === 'Other' && !empty($customEnhancement))
                                            <li style="font-size: 14px;">{{ $customEnhancement }}</li>
                                        @elseif ($enh !== 'Other')
                                            <li style="font-size: 14px;">{{ $enh }}</li>
                                        @endif
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                        @endforeach
                    </ul>
                </div>
                @endif
            @endforeach

            @if (!empty($otherServices))
            <div class="mt-3">
                <strong>✍️ Additional Services</strong>
                <ul class="services">
                    @foreach ($otherServices as $other_service)
                    <li style="font-size: 16px;">{{ $other_service }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @php
                $ccsRawLandlord = @$auction->get->client_custom_services;
                $clientCustomServicesLandlord = is_array($ccsRawLandlord)
                    ? $ccsRawLandlord
                    : (is_string($ccsRawLandlord) ? (json_decode($ccsRawLandlord, true) ?? []) : []);
                $clientCustomServicesLandlord = array_values(array_filter($clientCustomServicesLandlord, fn($s) => is_string($s) && trim($s) !== ''));
            @endphp
            @if (!empty($clientCustomServicesLandlord))
            <div class="mt-3">
                <strong>📋 Client Requested Services</strong>
                <ul class="services">
                    @foreach ($clientCustomServicesLandlord as $ccs)
                    <li style="font-size: 16px;">{{ $ccs }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

        </div>
        @endif

        <hr>
        @php
            $additionalDetailsRaw = @$auction->get->additional_details ?? null;
            $additionalDetailsStr = is_string($additionalDetailsRaw) ? trim($additionalDetailsRaw) : null;
        @endphp
        @if (!empty($additionalDetailsStr) && $additionalDetailsStr !== 'null')
        <div class="card-header section-header">
            <h4 class="section-title">Additional Details: </h4>
        </div>

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Details: <span
                class="removeBold">{{ $additionalDetailsStr }}</span>
        </div>
        @endif

        @include('partials.listing-photos-tours-documents')

        @php
            $hasLandlordBrokerCompData = !empty(@$auction->get->purchase_fee_type)
                || !empty(@$auction->get->tenant_broker_commission_structure)
                || !empty(@$auction->get->broker_fee_timing)
                || !empty(@$auction->get->renewal_fee_type)
                || !empty(@$auction->get->protection_period)
                || !empty(@$auction->get->agency_agreement_timeframe)
                || !empty(@$auction->get->early_termination_fee_option)
                || !empty(@$auction->get->interested_in_selling)
                || !empty(@$auction->get->interested_lease_option_agreement)
                || !empty(@$auction->get->interested_in_property_management);
        @endphp
        @if ($hasLandlordBrokerCompData)
        <hr />
        <div class="card-header section-header">
            <h4 class="section-title">Broker Compensation & Agency Agreement Terms</h4>
        </div>

        <div class="broker-compensation-section">

        <!-- Landlord's Broker Compensation Sub-section -->
        @if (@$auction->get->purchase_fee_type != null)
        <h5 class="mt-3 mb-2"><strong>Landlord's Broker Compensation:</strong></h5>
        @endif

        @if (@$auction->get->purchase_fee_type != null)
        @php
            // Build combined Landlord's Broker Lease Fee display
            $landlordLeaseFeeType = $canon(@$auction->get->purchase_fee_type ?? '');
            $landlordLeaseFeeCombined = '—';
            
            if ($landlordLeaseFeeType === 'Flat Fee' && @$auction->get->purchase_fee_flat) {
                $landlordLeaseFeeCombined = $fmtMoney(@$auction->get->purchase_fee_flat);
            } elseif ($landlordLeaseFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->purchase_fee_rental_period) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_rental_period) . " $rentalPeriodSuffix";
            } elseif ($landlordLeaseFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->purchase_fee_percentage_combo) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_percentage_combo) . ' of Gross Lease Value';
            } elseif ($landlordLeaseFeeType === "Percentage of the First Month's Rent" && @$auction->get->purchase_fee_flat_combo) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_flat_combo) . " of First Month's Rent";
            } elseif ($landlordLeaseFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->purchase_fee_net_aggregate) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_net_aggregate) . ' of Net Aggregate Rent';
            } elseif ($landlordLeaseFeeType === 'Percentage of the Gross Rent' && @$auction->get->purchase_fee_gross_rent) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_gross_rent) . ' of Gross Rent';
            } elseif ($landlordLeaseFeeType === "Percentage of Month's Rent" && @$auction->get->purchase_fee_monthly_percentage) {
                $display = $fmtPercent(@$auction->get->purchase_fee_monthly_percentage) . " of Month's Rent";
                if (@$auction->get->purchase_fee_months) {
                    $display .= ' x ' . @$auction->get->purchase_fee_months . ' Months';
                }
                $landlordLeaseFeeCombined = $display;
            } elseif (strtolower($landlordLeaseFeeType) === 'other') {
                $landlordLeaseFeeCombined = @$auction->get->purchase_fee_other ?? @$auction->get->purchase_fee_other_commercial ?? '—';
            } elseif ($landlordLeaseFeeType) {
                $landlordLeaseFeeCombined = $landlordLeaseFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Landlord's Broker Lease Fee:
            <span class="removeBold">{{ $landlordLeaseFeeCombined }}</span>
        </div>
        @endif

        @if ($canon(@$auction->get->purchase_fee_type ?? '') === 'Percentage of the Gross Rent' && !empty(@$auction->get->sales_tax_option_gross) && @$auction->get->sales_tax_option_gross !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ @$auction->get->sales_tax_option_gross === 'including' ? 'Including Sales Tax' : (@$auction->get->sales_tax_option_gross === 'excluding' ? 'Excluding Sales Tax' : $auction->get->sales_tax_option_gross) }}</span>
        </div>
        @endif

        @if ($canon(@$auction->get->purchase_fee_type ?? '') === "Percentage of Month's Rent" && !empty(@$auction->get->sales_tax_option_monthly) && @$auction->get->sales_tax_option_monthly !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ @$auction->get->sales_tax_option_monthly === 'including' ? 'Including Sales Tax' : (@$auction->get->sales_tax_option_monthly === 'excluding' ? 'Excluding Sales Tax' : $auction->get->sales_tax_option_monthly) }}</span>
        </div>
        @endif

        @if ($canon(@$auction->get->purchase_fee_type ?? '') === 'Flat Fee' && !empty(@$auction->get->sales_tax_option_flat) && @$auction->get->sales_tax_option_flat !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ @$auction->get->sales_tax_option_flat === 'including' ? 'Including Sales Tax' : (@$auction->get->sales_tax_option_flat === 'excluding' ? 'Excluding Sales Tax' : $auction->get->sales_tax_option_flat) }}</span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Tenant's Broker Compensation Sub-section (Residential Only) -->
        @if ($isResidential && @$auction->get->tenant_broker_commission_structure != null)
        <h5 class="mt-3 mb-2"><strong>Tenant's Broker Compensation:</strong></h5>

        @if (@$auction->get->tenant_broker_commission_structure != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Tenant's Broker Commission Structure:
            <span class="removeBold">{{ $auction->get->tenant_broker_commission_structure ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->tenant_broker_commission_structure != 'no_compensation' && @$auction->get->tenant_broker_commission_structure != "No Compensation Offered to the Tenant's Broker")
        @php
            // Build combined Tenant's Broker Fee display
            $tenantFeeType = $canon(@$auction->get->tenant_broker_fee_structure ?? '');
            $tenantFeeCombined = '—';
            
            if ($tenantFeeType === 'Flat Fee' && @$auction->get->tenant_broker_flat_fee) {
                $tenantFeeCombined = $fmtMoney(@$auction->get->tenant_broker_flat_fee);
            } elseif ($tenantFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->tenant_broker_percentage) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_percentage) . " $rentalPeriodSuffix";
            } elseif ($tenantFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->tenant_broker_gross_lease) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_gross_lease) . ' of Gross Lease Value';
            } elseif ($tenantFeeType === "Percentage of the First Month's Rent" && @$auction->get->tenant_broker_first_month_rent) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_first_month_rent) . " of First Month's Rent";
            } elseif (strtolower($tenantFeeType) === 'other' && @$auction->get->tenant_broker_other) {
                $tenantFeeCombined = @$auction->get->tenant_broker_other;
            } elseif ($tenantFeeType) {
                $tenantFeeCombined = $tenantFeeType;
            }
        @endphp
        @if ($tenantFeeCombined !== '—')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Tenant's Broker Commission Fee:
            <span class="removeBold">{{ $tenantFeeCombined }}</span>
        </div>
        @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
        @endif

        <!-- Payment Timing & Renewal Terms Sub-section -->
        @if (@$auction->get->broker_fee_timing != null || @$auction->get->renewal_fee_type != null || @$auction->get->expansion_commission_percentage != null)
        <h5 class="mt-3 mb-2"><strong>Payment Timing & Renewal Terms:</strong></h5>
        @endif

        @if (@$auction->get->broker_fee_timing != null)
        @php
            $paymentTimingDisplay = @$auction->get->broker_fee_timing;
            
            $paymentTimingMap = [
                'full_execution' => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
            ];
            if (isset($paymentTimingMap[$paymentTimingDisplay])) {
                $paymentTimingDisplay = $paymentTimingMap[$paymentTimingDisplay];
            }
            
            if ($paymentTimingDisplay === 'other' || $paymentTimingDisplay === 'Other') {
                $paymentTimingDisplay = @$auction->get->broker_fee_timing_other ?? '';
            }
            
            $canonTiming = $canon($paymentTimingDisplay);
            if ($canonTiming === 'Paid Within Calendar Days After Executed Lease' && @$auction->get->broker_fee_days_after_lease) {
                $paymentTimingDisplay = 'Paid Within ' . $auction->get->broker_fee_days_after_lease . ' Calendar Days After Executed Lease';
            } elseif ($canonTiming === 'Paid Within Calendar Days of Tenant Rent Payment' && @$auction->get->broker_fee_days_after_rent) {
                $paymentTimingDisplay = 'Paid Within ' . $auction->get->broker_fee_days_after_rent . ' Calendar Days of Tenant Rent Payment';
            } elseif ($canonTiming === 'Deducted from Rent Collected' && @$auction->get->broker_fee_days_from_rent) {
                $paymentTimingDisplay = 'Deducted from Rent Collected (' . $auction->get->broker_fee_days_from_rent . ' Calendar Days to Pay Balance)';
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Payment Timing for Broker Fees:
            <span class="removeBold">{{ $paymentTimingDisplay }}</span>
        </div>
        @endif

        @if (@$auction->get->broker_fee_days_after_due_event != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Days After Due Event:
            <span class="removeBold">{{ $auction->get->broker_fee_days_after_due_event }} days</span>
        </div>
        @endif

        @if (@$auction->get->renewal_fee_type != null)
        @php
            // Build combined Lease Renewal/Extension Fee display
            $renewalFeeType = $canon(@$auction->get->renewal_fee_type ?? '');
            $renewalFeeCombined = '—';
            
            if ($renewalFeeType === 'Flat Fee' && @$auction->get->renewal_fee_flat_free) {
                $renewalFeeCombined = $fmtMoney(@$auction->get->renewal_fee_flat_free);
            } elseif ($renewalFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->renewal_fee_percentage) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_percentage) . " $rentalPeriodSuffix";
            } elseif ($renewalFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->renewal_fee_lease_value) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_lease_value) . ' of Gross Lease Value';
            } elseif ($renewalFeeType === "Percentage of the First Month's Rent" && @$auction->get->renewal_fee_first_month) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_first_month) . " of First Month's Rent";
            } elseif ($renewalFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->renewal_fee_percentage) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_percentage) . ' of Net Aggregate Rent';
            } elseif ($renewalFeeType === 'Percentage of the Gross Rent' && @$auction->get->renewal_fee_lease_value) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_lease_value) . ' of Gross Rent';
            } elseif ($renewalFeeType === "Percentage of Month's Rent" && @$auction->get->renewal_fee_first_month) {
                $display = $fmtPercent(@$auction->get->renewal_fee_first_month) . " of Month's Rent";
                if (@$auction->get->renewal_fee_no_of_months) {
                    $display .= ' x ' . @$auction->get->renewal_fee_no_of_months . ' Months';
                }
                $renewalFeeCombined = $display;
            } elseif (strtolower($renewalFeeType) === 'other' && @$auction->get->renewal_fee_custom) {
                $renewalFeeCombined = @$auction->get->renewal_fee_custom;
            } elseif ($renewalFeeType) {
                $renewalFeeCombined = $renewalFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Lease Renewal/Extension Fee:
            <span class="removeBold">{{ $renewalFeeCombined }}</span>
        </div>
        @endif

        @php
            $renewalSalesTax = null;
            $canonRenewalType = $canon($renewalFeeType ?? '');
            if (in_array($canonRenewalType, ['Percentage of the Gross Lease Value', 'Percentage of the Gross Rent'])) {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_lease_value;
            } elseif (in_array($canonRenewalType, ["Percentage of the First Month's Rent", "Percentage of Month's Rent"])) {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_first_month;
            } elseif ($canonRenewalType === 'Flat Fee') {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_flat_fee;
            } else {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_lease_value ?? @$auction->get->renewal_fee_sales_tax_first_month ?? @$auction->get->renewal_fee_sales_tax_flat_fee ?? null;
            }
        @endphp
        @if (!empty($renewalSalesTax) && $renewalSalesTax !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ $renewalSalesTax === 'including' ? 'Including Sales Tax' : ($renewalSalesTax === 'excluding' ? 'Excluding Sales Tax' : $renewalSalesTax) }}</span>
        </div>
        @endif

        @if (@$auction->get->expansion_commission_percentage != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Expansion Commission for Lease Amendment:
            <span class="removeBold">{{ $fmtPercent($auction->get->expansion_commission_percentage) }} of original commission</span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Property Management Sub-section -->
        @if (@$auction->get->interested_in_property_management != null)
        <h5 class="mt-3 mb-2"><strong>Property Management:</strong></h5>
        @endif

        @if (@$auction->get->interested_in_property_management != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in Property Management:
            <span class="removeBold">{{ $auction->get->interested_in_property_management === 'yes' ? 'Yes' : 'No' }}</span>
        </div>
        @endif

        @if (@$auction->get->interested_in_property_management === 'yes')
        @php
            // Build combined Property Management Fee display
            $pmFeeType = @$auction->get->interested_in_property_management_fee ?? '';
            $pmFeeCombined = '—';
            
            if ($pmFeeType === 'Flat Fee' && @$auction->get->interested_in_property_management_fee_flate_free) {
                $pmFeeCombined = $fmtMoney(@$auction->get->interested_in_property_management_fee_flate_free);
            } elseif ($pmFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->interested_in_property_management_fee_rental_periord) {
                $pmFeeCombined = $fmtPercent(@$auction->get->interested_in_property_management_fee_rental_periord) . " $rentalPeriodSuffix";
            } elseif ($pmFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->interested_in_property_management_fee_gross_lease) {
                $pmFeeCombined = $fmtPercent(@$auction->get->interested_in_property_management_fee_gross_lease) . ' of Gross Lease Value';
            } elseif (strtolower($pmFeeType) === 'other' && @$auction->get->interested_in_property_management_fee_other) {
                $pmFeeCombined = @$auction->get->interested_in_property_management_fee_other;
            } elseif ($pmFeeType) {
                $pmFeeCombined = $pmFeeType;
            }
        @endphp
        @if ($pmFeeCombined !== '—')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Property Management Fee:
            <span class="removeBold">{{ $pmFeeCombined }}</span>
        </div>
        @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Lease-Option Details Sub-section -->
        @if (@$auction->get->interested_lease_option_agreement != null)
        <h5 class="mt-3 mb-2"><strong>Lease-Option Details:</strong></h5>
        @endif

        @if (@$auction->get->interested_lease_option_agreement != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in Offering a Lease-Option Agreement:
            <span class="removeBold">{{ $auction->get->interested_lease_option_agreement ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->interested_lease_option_agreement === 'Yes')
            @if (@$auction->get->lease_value != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Compensation for Creating the Lease-Option Agreement:
                <span class="removeBold">
                    @if (@$auction->get->lease_type === 'percent')
                        {{ $fmtPercent($auction->get->lease_value) }} of Total Purchase Price
                    @else
                        {{ $fmtMoney($auction->get->lease_value) }}
                    @endif
                </span>
            </div>
            @endif

            @if (@$auction->get->purchase_value != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Compensation if Purchase Option is Exercised:
                <span class="removeBold">
                    @if (@$auction->get->purchase_type === 'percent')
                        {{ $fmtPercent($auction->get->purchase_value) }} of Total Purchase Price
                    @else
                        {{ $fmtMoney($auction->get->purchase_value) }}
                    @endif
                </span>
            </div>
            @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Purchase Fee Details Sub-section -->
        @if (@$auction->get->interested_in_selling != null)
        <h5 class="mt-3 mb-2"><strong>Purchase Fee Details:</strong></h5>
        @endif

        @if (@$auction->get->interested_in_selling != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in Selling:
            <span class="removeBold">{{ $auction->get->interested_in_selling ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->interested_in_selling === 'Yes')
        @php
            // Build combined Landlord's Broker Purchase Fee display
            $purchaseFeeType = @$auction->get->interested_in_selling_type ?? '';
            $purchaseFeeCombined = '—';
            
            if ($purchaseFeeType === 'Flat Fee' && @$auction->get->landlord_broker_flate_fee) {
                $purchaseFeeCombined = $fmtMoney(@$auction->get->landlord_broker_flate_fee);
            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price' && @$auction->get->landlord_broker_purchase_price) {
                $purchaseFeeCombined = $fmtPercent(@$auction->get->landlord_broker_purchase_price) . ' of Total Purchase Price';
            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                $purchaseFeeCombined = $joinParts([
                    @$auction->get->landlord_broker_percentage_price ? ($fmtPercent(@$auction->get->landlord_broker_percentage_price) . ' of Total Purchase Price') : null,
                    $fmtMoney(@$auction->get->landlord_broker_dollar_price),
                ]) ?? '—';
            } elseif (strtolower($purchaseFeeType) === 'other' && @$auction->get->landlord_broker_other) {
                $purchaseFeeCombined = @$auction->get->landlord_broker_other;
            } elseif ($purchaseFeeType) {
                $purchaseFeeCombined = $purchaseFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Landlord's Broker Purchase Fee:
            <span class="removeBold">{{ $purchaseFeeCombined }}</span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Legal Terms Sub-section -->
        @if (@$auction->get->protection_period != null || @$auction->get->agency_agreement_timeframe != null || ($isResidential && @$auction->get->early_termination_fee_option != null))
        <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>
        @endif

        @if (@$auction->get->protection_period != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Protection Period Timeframe:
            <span class="removeBold">{{ $auction->get->protection_period }} days</span>
        </div>
        @endif

        @if ($isResidential && @$auction->get->early_termination_fee_option != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Early Termination Fee:
            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(
                $auction->get->early_termination_fee_option == 'yes' ? 'Yes' : 'No',
                $auction->get->early_termination_fee_option == 'yes' && @$auction->get->early_termination_fee_amount ? $fmtMoney($auction->get->early_termination_fee_amount) : null
            ) }}</span>
        </div>
        @endif

        @if (@$auction->get->agency_agreement_timeframe != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Landlord Agency Agreement Timeframe:
            <span class="removeBold">
                {{ $auction->get->agency_agreement_timeframe === 'Other' ? $auction->get->agency_agreement_custom : $auction->get->agency_agreement_timeframe }}
            </span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Brokerage Relationship Sub-section -->
        @if (@$auction->get->brokerage_relationship != null)
        <h5 class="mt-3 mb-2"><strong>Brokerage Relationship:</strong></h5>
        @endif

        @if (@$auction->get->brokerage_relationship != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Acceptable Brokerage Relationship:
            <span class="removeBold">{{ $auction->get->brokerage_relationship ?? '' }}</span>
        </div>
        @endif

        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details_broker))
        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Terms:
            <span class="removeBold">{{ $auction->get->additional_details_broker }}</span>
        </div>
        @endif

        </div> <!-- end broker-compensation-section -->
        @endif
        @php
            $referralPct = trim((string)($auction->get->referral_percentage ?? ''));
            $referralPctDisplay = $referralPct !== '' ? (str_ends_with($referralPct, '%') ? $referralPct : $referralPct . '%') : '';
        @endphp
        @if ($referralPctDisplay !== '')
        <hr />
        <div class="card-header section-header">
            <h4 class="section-title">Referral & Cooperation Terms</h4>
        </div>
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Referral Fee:
            <span class="removeBold">{{ $referralPctDisplay }}</span>
        </div>
        @endif
        <hr />
        <div class="card-header">
            <h4>{{ ($auction->user && $auction->user->user_type === 'agent') ? "Agent's Info" : "Landlord's Info" }}</h4>
        </div>
        @if (!empty($auction->get->first_name))
        <div class="col-md-12 col-12 pt-2 fw-bold"> First
            Name:
            <span class="removeBold">
                {{ $auction->get->first_name }}
            </span>
        </div>
        @endif

        <div class="row">
            {{-- @if (isset($auction->get->video))
                                <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                                    <span class="removeBold">
                                        <video controls style="width:100%;height:29vh;">
                                            <source src="{{ asset('storage/auction/videos/' . $auction->get->video) }}"
            type="video/mp4">
            Your browser does not support the video tag.
            </video>
            </span>
        </div>
        @endif --}}

        @if (!empty($auction->get->video))
        <div class="col-md-6 col-6 pt-2 fw-bold">Video:
            <span class="removeBold">
                <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                    <source src="{{ asset('storage/auction/videos/' . $auction->get->video) }}"
                        type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </span>
        </div>
        @endif

        @if (isset($auction->get->photo))
        <div class="col-md-6 col-6 pt-2 fw-bold">Photo:
            <span class="removeBold">
                <img src="{{ asset('storage/auction/images/' . $auction->get->photo) }}"
                    style="width:100%;height:29vh;" />
            </span>
        </div>
        @endif

        @if (!empty($auction->get->video_link))
        @if (filter_var($auction->get->video_link, FILTER_VALIDATE_URL))
        @php
        $videoLink = $auction->get->video_link;
        @endphp

        @if (strpos($videoLink, 'youtube.com') !== false || strpos($videoLink, 'youtu.be') !== false)
        @php
        // Convert YouTube URL to embed format
        if (strpos($videoLink, 'watch?v=') !== false) {
        $youtubeEmbedUrl = str_replace('watch?v=', 'embed/', $videoLink);
        } elseif (strpos($videoLink, 'youtu.be/') !== false) {
        $videoId = basename(parse_url($videoLink, PHP_URL_PATH));
        $youtubeEmbedUrl = "https://www.youtube.com/embed/{$videoId}";
        } else {
        $youtubeEmbedUrl = $videoLink;
        }

        // Add autoplay + mute parameters (mute avoids browser block)
        $youtubeEmbedUrl .=
        (strpos($youtubeEmbedUrl, '?') === false ? '?' : '&') .
        'autoplay=1&mute=1';
        @endphp

        <div class="col-md-6 col-6 pt-2 fw-bold">Video Link:
            <span class="removeBold">
                <iframe width="100%" height="315" src="{{ $youtubeEmbedUrl }}"
                    frameborder="0"
                    allow="autoplay; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </span>
        </div>
        @elseif (strpos($videoLink, 'vimeo.com') !== false)
        @php
        // Extract Vimeo video ID from any kind of URL (e.g. /channels/staffpicks/1120141041)
        preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $videoLink, $matches);
        $vimeoVideoId =
        $matches[1] ?? basename(parse_url($videoLink, PHP_URL_PATH));

        // Vimeo autoplay embed URL
        $vimeoEmbedUrl = "https://player.vimeo.com/video/{$vimeoVideoId}?autoplay=1&muted=1";
        @endphp

        <div class="col-md-6 col-6 pt-2 fw-bold">Video Link:
            <span class="removeBold">
                <iframe src="{{ $vimeoEmbedUrl }}" width="100%" height="315"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                    allowfullscreen>
                </iframe>
            </span>
        </div>
        @endif
        @endif
        @endif

    </div>
</div>
</div>
@inject('auctionUser', 'App\Models\User')
@php
$auser = $auctionUser::find(@$auction->user_id);
@endphp
<!-- Review  -->
<div class="card review">
    <div class="card-body d-flex align-items-center">
        <div class="left d-flex align-items-center">
            <x-avatar-img :avatar="$auser->avatar" alt="" class="w-25" />
            <div>
                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>User
                            Details</b></a><span></span>
                    <span class="start opacity-50">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </span>
                </p>
                <p class="mb-0">...</p>
                <p class="mb-0 opacity-50">{{ $auser->name }} • last online 5 days ago.</p>
            </div>
        </div>
        <div class="right text-center">
            <a href="{{ route('author', [$auser->id]) }}"><button class="btn">Message</button></a>
            <a href="{{ route('author', [$auser->id]) }}"><button class="btn view-btn">View
                    Profile</button></a>
        </div>

    </div>
</div>
</div>
<div class="col-sm-12 col-md-4 col-lg-4 rightCol">
    <h1 style="font-size: 1.5rem; font-weight: bold; color: #049399; line-height: 1.3;">{{ @$auction->title }}</h1>
    @if(@$auction->listing_id)
    <div class="mb-2">
        <span class="badge bg-secondary" style="font-size: 0.9rem;">Listing ID: {{ @$auction->listing_id }}</span>
    </div>
    @endif
    @if(@$auction->status)
    <div class="mb-2">
        @php
            $_statusStyles = [
                'Active'       => 'background-color:#16a34a;color:#fff;',
                'Pending'      => 'background-color:#d97706;color:#fff;',
                'Hired Agent'  => 'background-color:#2563eb;color:#fff;',
                'Expired'      => 'background-color:#6b7280;color:#fff;',
            ];
            $_statusIcons = [
                'Active'       => 'fa-circle-check',
                'Pending'      => 'fa-clock',
                'Hired Agent'  => 'fa-user',
                'Expired'      => 'fa-circle-xmark',
            ];
            $_statusStyle        = $_statusStyles[$auction->status] ?? 'background-color:#6b7280;color:#fff;';
            $_statusIcon         = $_statusIcons[$auction->status] ?? 'fa-circle';
            $_displayStatusLabel = $auction->status; // separate label var — never touches the model

            // ── Display-layer expiry override (badge only, no DB change) ──────────
            if (!in_array($auction->status, ['Hired Agent', 'Pending', 'Draft'], true)) {
                $_badgeNow  = \Carbon\Carbon::now();
                $_badgeType = strtolower(trim($auction->get->auction_type ?? ''));
                $_badgeExp  = null;
                if ($_badgeType === 'bidding period') {
                    $_badgeStart = $auction->get->created_at ?? $auction->created_at ?? $_badgeNow;
                    $_badgeTime  = trim($auction->get->auction_time ?? '');
                    if (!empty($_badgeTime) && strtolower($_badgeTime) !== 'null') {
                        $_bp = explode(' ', $_badgeTime);
                        $_bv = (int)($_bp[0] ?? 0);
                        $_bu = strtolower($_bp[1] ?? 'days');
                        $_badgeExp = match(true) {
                            in_array($_bu, ['hour','hours'])     => \Carbon\Carbon::parse($_badgeStart)->addHours($_bv),
                            in_array($_bu, ['week','weeks'])     => \Carbon\Carbon::parse($_badgeStart)->addWeeks($_bv),
                            in_array($_bu, ['minute','minutes']) => \Carbon\Carbon::parse($_badgeStart)->addMinutes($_bv),
                            default                              => \Carbon\Carbon::parse($_badgeStart)->addDays($_bv),
                        };
                    }
                } else {
                    if (!empty($auction->get->expiration_date)) {
                        $_badgeExp = \Carbon\Carbon::parse($auction->get->expiration_date);
                    }
                }
                if ($_badgeExp && $_badgeNow->gte($_badgeExp)) {
                    $_statusStyle        = $_statusStyles['Expired'];
                    $_statusIcon         = $_statusIcons['Expired'];
                    $_displayStatusLabel = 'Expired'; // display only — model not mutated
                }
            }
            $_statusPillClass = match($_displayStatusLabel) {
                'Active'      => 'status-active',
                'Pending'     => 'status-pending',
                'Expired'     => 'status-expired',
                'Hired Agent' => 'status-hired',
                default       => 'status-expired',
            };
        @endphp
        <span class="status-pill {{ $_statusPillClass }}"><i class="fa-solid {{ $_statusIcon }} me-1"></i>Status: {{ $_displayStatusLabel }}</span>
    </div>
    @endif

    @php
        $auth_id = auth()->id();
    @endphp
    @if($auth_id && $auth_id == @$auction->user_id)
    <div class="mb-2">
        <a href="{{ route('landlord.hire.agent.auction.edit', ['auctionId' => $auction->id]) }}" 
           class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
        </a>
        {{-- PDF download button hidden from UI (backend route preserved) --}}
    </div>
    @endif
    <hr>

    {{-- 🏆 Display Winner Information if Listing is Sold --}}
    @php
        $acceptedBid = $auction->bids->where('accepted', 'accepted')->first();
        // Check for accepted counter bids
        $acceptedCounterBid = null;
        foreach ($auction->bids as $bid) {
            $counterBid = \App\Models\LandlordCounterTerm::where('landlord_agent_auction_id', $bid->id)
                            ->where('status', 'accepted')
                            ->first();
            if ($counterBid) {
                $acceptedCounterBid = $counterBid;
                break;
            }
        }
    @endphp

    @if ($auction->is_sold && ($acceptedBid || $acceptedCounterBid))
    <div class="alert alert-success mb-3" style="border-left: 4px solid #28a745;">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-check me-3" style="font-size: 28px; color: #28a745;"></i>
            <div class="flex-grow-1">
                <h5 class="mb-1 fw-bold">🎉 Agent Selected!</h5>
                @if($acceptedCounterBid)
                    <p class="mb-1">
                        <strong>Accepted Counter Offer from:</strong>
                        {{ $acceptedCounterBid->user->first_name ?? '' }} {{ $acceptedCounterBid->user->last_name ?? '' }}
                    </p>
                    <small class="text-muted">
                        <i class="fa-solid fa-calendar-check"></i>
                        Accepted on {{ \Carbon\Carbon::parse($acceptedCounterBid->accepted_date)->format('M j, Y g:i A') }}
                    </small>
                @elseif($acceptedBid)
                    <p class="mb-1">
                        <strong>Purchased by:</strong>
                        {{ $acceptedBid->user->first_name ?? '' }} {{ $acceptedBid->user->last_name ?? '' }}
                    </p>
                    <small class="text-muted">
                        <i class="fa-solid fa-calendar-check"></i>
                        Accepted on {{ \Carbon\Carbon::parse($acceptedBid->accepted_date)->format('M j, Y g:i A') }}
                    </small>
                @endif
            </div>
        </div>
    </div>
    @endif
    <hr>
    @inject('carbon', 'Carbon\Carbon')

    @php
    // 🔹 Determine listing type: Traditional vs Bidding Period
    $listingType = trim($auction->get->auction_type ?? '');
    $isTraditionalListing = (strtolower($listingType) === 'traditional' || empty($listingType));
    $isBiddingPeriodListing = in_array(strtolower($listingType), ['bidding period', 'auction (timer)']);

    // 🕒 Auction start time (when auction began)
    $start_time = $auction->get->created_at ?? $auction->created_at ?? $carbon::now();

    // 🔹 Get auction_time value
    $auction_time = trim($auction->get->auction_time ?? '');
    $useAuctionTime = !empty($auction_time) && strtolower($auction_time) !== 'null';

    if ($useAuctionTime && $isBiddingPeriodListing) {
    // 🔸 CASE 1: Use auction_time (e.g. "14 Days", "2 Weeks", "5 Hours") for Bidding Period
    $auction_duration = $auction_time;
    $duration_parts = explode(' ', trim($auction_duration)); // e.g. ['14', 'Days']
    $duration_value = (int) ($duration_parts[0] ?? 0);
    $duration_unit = strtolower($duration_parts[1] ?? 'days');

    // 🧠 Convert unit into Carbon duration
    switch ($duration_unit) {
    case 'day':
    case 'days':
    $expiration = $carbon::parse($start_time)->addDays($duration_value);
    break;
    case 'hour':
    case 'hours':
    $expiration = $carbon::parse($start_time)->addHours($duration_value);
    break;
    case 'week':
    case 'weeks':
    $expiration = $carbon::parse($start_time)->addWeeks($duration_value);
    break;
    case 'minute':
    case 'minutes':
    $expiration = $carbon::parse($start_time)->addMinutes($duration_value);
    break;
    default:
    $expiration = $carbon::parse($start_time)->addDays($duration_value);
    break;
    }
    } elseif ($isTraditionalListing) {
    // 🔸 CASE 2: Traditional listing - use expiration_date for listing lifecycle only (no timer)
    $expiration = !empty($auction->get->expiration_date)
    ? $carbon::parse($auction->get->expiration_date)
    : null;
    } else {
    // 🔸 CASE 3: Fallback
    $expiration = !empty($auction->get->expiration_date)
    ? $carbon::parse($auction->get->expiration_date)
    : null;
    }

    // 🧾 Determine if expired
    $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
    $isSold = $auction->is_sold;
    $isPending = ($auction->status === 'Pending');

    // 🔹 Timer is informational only — actions are never locked by the BP timer
    $isBiddingTimerActive = $isBiddingPeriodListing && $expiration && !$isExpired;
    $canTakeAction = true; // Soft deadline: timer never locks bid actions

    // ⏱ Calculate remaining time if not expired (only for Bidding Period)
    if ($isBiddingPeriodListing && $expiration && !$isExpired && !$isSold) {
    $now = $carbon::now();
    $diff_d = $now->diffInDays($expiration);
    $diff_H = $now->diff($expiration)->format('%H');
    $diff_I = $now->diff($expiration)->format('%I');
    $diff_S = $now->diff($expiration)->format('%S');
    }
    @endphp


    {{-- 💰 Bid Info --}}
    @php
    $lowest_bid_price = @$auction->bids->min('brokerage') ?? @$auction->get->concession;
    $lowest_bid_price =
    $lowest_bid_price < @$auction->get->concession ? $lowest_bid_price : @$auction->get->concession;
        $lowest_bidder = @$auction->bids->where('brokerage', $lowest_bid_price)->first();
        $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp


        {{-- 📩 Message Button --}}
        <a href="{{ route('auction-chat', ['landlord-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>


        {{-- ⏳ Countdown Timer - Only shown for Bidding Period listings --}}
        @if (!$isSold)
            @if ($isBiddingPeriodListing)
                @if ($isBiddingTimerActive)
                <div class="time d-flex justify-content-between text-center flex-wrap pb-2"
                    data-expiration="{{ $expiration->toIso8601String() }}">
                    <div>
                        <h5><b class="timer-d">{{ $diff_d }}</b></h5>
                        <h6 class="opacity-50">Days</h6>
                    </div>
                    <div>
                        <h5><b class="timer-h">{{ $diff_H }}</b></h5>
                        <h6 class="opacity-50">Hrs</h6>
                    </div>
                    <div>
                        <h5><b class="timer-m">{{ $diff_I }}</b></h5>
                        <h6 class="opacity-50">Mins</h6>
                    </div>
                    <div>
                        <h5><b class="timer-s">{{ $diff_S }}</b></h5>
                        <h6 class="opacity-50">Secs</h6>
                    </div>
                </div>
                @else
                <div class="text-center mt-2 mb-0">
                    <span class="status-pill status-ended w-100 d-flex justify-content-center">Bidding Ended</span>
                </div>
                @endif
            {{-- Traditional listings: No timer displayed --}}
            @endif
        @else
            <div class="alert alert-success text-center mt-2 mb-0 p-2">
                <strong><i class="fa-solid fa-circle-check"></i> Bidding Closed - Agent Selected</strong>
            </div>
        @endif

        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp


        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
            @if (!$isExpired && !$isSold && !$isPending && $auction->status !== 'Hired Agent')
                @if ($userHasBid)
                {{-- User already placed a bid --}}
                <div class="alert alert-info text-center mb-2">
                    <i class="fa-solid fa-circle-check"></i> You have already placed a bid
                </div>
                <div class="status-pill status-disabled w-100 d-flex justify-content-between">
                    <span>Bid Already Placed</span>
                    <span style="font-weight:normal;font-size:.85em;">${{ @$auction->get->budget }}</span>
                </div>
                @else
                {{-- User can place a bid --}}
                <button class="btn w-100 bid-btn"
                    onclick="window.location='{{ route('agent.landlord.agent.auction.bid', @$auction->id) }}';">
                    <span class="bid">Bid Now</span>
                    <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
                </button>
                @endif
            @elseif($auction->status === 'Hired Agent' || $isSold)
                <div class="alert alert-success text-center mb-2">
                    <i class="fa-solid fa-trophy"></i> <strong>An agent has been hired</strong>
                </div>
                <div class="status-pill status-hired w-100 d-flex justify-content-center">
                    <i class="fa-solid fa-trophy me-2"></i>Hired Agent
                </div>
            @elseif($isPending)
                <div class="alert alert-warning text-center mb-2">
                    <i class="fa-solid fa-pause-circle"></i> <strong>This listing is pending &mdash; not accepting new bids</strong>
                </div>
                <div class="status-pill status-pending w-100 d-flex justify-content-center">
                    <i class="fa-solid fa-pause-circle me-2"></i>Pending
                </div>
            @else
            {{-- Expiry catch-all: distinguish BP (timer already showed "Bidding Ended") from Traditional --}}
            @if ($isBiddingPeriodListing)
            {{-- BP: "Bidding Ended" already rendered by the timer block above — no duplicate needed --}}
            @else
                <div class="alert alert-secondary text-center mb-2">
                    <i class="fa-solid fa-calendar-xmark me-1"></i> <strong>This listing has expired</strong>
                </div>
            @endif
            @endif

            @if (@$auction->sold)
            <span class="status-pill status-ended w-100 d-flex justify-content-center mt-2">Sold</span>
            @endif
        @elseif(!$auth_id)
            <a href="{{ route('login') }}">
                <button class="btn w-100">
                    <span class="bid m-0">Login to Bid</span>
                    <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
                </button>
            </a>
        @else
            <div class="alert alert-secondary text-center">
                Only agents can place bids
            </div>
        @endif

        @php
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
            // Build a stable per-agent alias map keyed by user_id.
            // Sort by created_at asc, id asc, user_id asc; first bid per unique agent sets that agent's alias.
            // Excludes the listing owner so alias numbers reflect only competing agents.
            $bidsByOrder = $auction->bids
                ->where('user_id', '!=', data_get($auction, 'user_id'))
                ->sortBy([['created_at', 'asc'], ['id', 'asc'], ['user_id', 'asc']])
                ->values();
            $agentNumberMap = []; // keyed by user_id → alias number
            foreach ($bidsByOrder as $orderedBid) {
                if (!isset($agentNumberMap[$orderedBid->user_id])) {
                    $agentNumberMap[$orderedBid->user_id] = count($agentNumberMap) + 1;
                }
            }
        @endphp

        <div class="card higestBider">
            <div class="card-body card-body-padding">
                @if ($lowest_bidder)
                <p class="mb-3"><b>Agent {{ $agentNumberMap[$lowest_bidder->user_id] ?? '?' }}</b> was the last bidder.</p>
                @else
                <p>No agents have submitted a bid yet.</p>
                @endif
                @php
                    // ── Match Score Baseline (Landlord listing request as the reference) ──────
                    $auctionPropType = $auction->get->property_type ?? 'Residential Property';
                    $landlordBaselineData = json_decode(json_encode($auction->get ?? []), true) ?: [];
                    $getScoreColor = fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::scoreColor((int)$s);
                @endphp

                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        @php
                            $agentNumber = $agentNumberMap[$bid->user_id] ?? $loop->iteration;
                            $rawState = data_get($bid, 'accepted', '0');
                            // 'accepted' column stores 'no' for undecided bids. Treat anything non-terminal as '0'.
                            $_isTerminalCard = in_array((string)$rawState, ['accepted', 'rejected'], true);
                            $state = $_isTerminalCard ? (string) $rawState : '0';
                            $isOwnerRow = $isListingOwner;
                            $hasAcceptedCounterBid = false;

                            // Get counter bids for this bid
                            $counterBids = \App\Models\LandlordCounterTerm::with('meta', 'user')
                                ->where('landlord_agent_auction_id', data_get($bid, 'id'))
                                ->orderBy('created_at', 'desc')
                                ->get();

                            // Check if this bid has any accepted counter bid
                            $acceptedCounterBidForThisBid = $counterBids->where('status', 'accepted')->first();
                            $hasAcceptedCounterBid = $acceptedCounterBidForThisBid ? true : false;
                            $bidIsAccepted = $state === 'accepted' || $hasAcceptedCounterBid;

                            // Parity vars
                            $hasCounterBids = $counterBids->isNotEmpty();
                            $bidStatusLabel = match($state) {
                                'accepted' => 'Accepted',
                                'rejected' => 'Rejected',
                                'countered' => 'Countered',
                                default => $hasCounterBids ? 'Countered' : 'Active',
                            };
                            $bidStatusColor = match($state) {
                                'accepted' => '#28a745',
                                'rejected' => '#dc3545',
                                'countered' => '#ffc107',
                                default => $hasCounterBids ? '#ffc107' : '#1a4a6e',
                            };
                            $servicesList = (array) data_get($bid,'get.services',[]);
                            $additionalServices = (array) data_get($bid,'get.other_services',[]);
                            $totalServicesCount = count(array_filter($servicesList, fn($s) => $s !== 'Other')) + count($additionalServices);
                            $isBidOwner = (data_get($bid, 'user_id') == $auth_id);
                            $bidAccepted = data_get($bid, 'accepted');
                            $canEditWithdraw = $isBidOwner && !$isExpired && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';
                            $isOtherAgentsBid = !$isListingOwner && !$isBidOwner;
                            $isAgent = $auth_id && auth()->user() && in_array(auth()->user()->user_type ?? '', ['agent']);
                            $canViewBid = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $isAgent && $userHasBid) || ($isTraditionalListing && $isAgent);
                            if (!$canViewBid && $isAgent) { continue; }

                            // ── Resolved Landlord Broker Lease Fee display (matching Tenant's commissionFeeDisplay) ──
                            $landlordFeeType = data_get($bid, 'get.purchase_fee_type', '');
                            $landlordFeeDisplay = '—';
                            if ($landlordFeeType === 'Flat Fee' && data_get($bid,'get.purchase_fee_flat')) {
                                $landlordFeeDisplay = $fmtMoney(data_get($bid,'get.purchase_fee_flat'));
                            } elseif ($landlordFeeType === 'Percentage of the Rent Due Each Rental Period' && data_get($bid,'get.purchase_fee_rental_period')) {
                                $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_rental_period')) . " $rentalPeriodSuffix";
                            } elseif ($landlordFeeType === 'Percentage of the Gross Lease Value' && data_get($bid,'get.purchase_fee_percentage_combo')) {
                                $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_percentage_combo')) . ' of Gross Lease Value';
                            } elseif ($landlordFeeType === "Percentage of the First Month's Rent" && data_get($bid,'get.purchase_fee_flat_combo')) {
                                $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_flat_combo')) . " of First Month's Rent";
                            } elseif ($landlordFeeType === 'Percentage of the Net Aggregate Rent' && data_get($bid,'get.purchase_fee_net_aggregate')) {
                                $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_net_aggregate')) . ' of Net Aggregate Rent';
                            } elseif ($landlordFeeType === 'Percentage of the Gross Rent' && data_get($bid,'get.purchase_fee_gross_rent')) {
                                $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_gross_rent')) . ' of Gross Rent';
                            } elseif ($landlordFeeType === "Percentage of Month's Rent" && data_get($bid,'get.purchase_fee_monthly_percentage')) {
                                $_d = $fmtPercent(data_get($bid,'get.purchase_fee_monthly_percentage')) . " of Month's Rent";
                                if (data_get($bid,'get.purchase_fee_months')) $_d .= ' x ' . data_get($bid,'get.purchase_fee_months') . ' Months';
                                $landlordFeeDisplay = $_d;
                            } elseif (strtolower($landlordFeeType) === 'other') {
                                $landlordFeeDisplay = data_get($bid,'get.purchase_fee_other') ?? data_get($bid,'get.purchase_fee_other_commercial') ?? '—';
                            } elseif ($landlordFeeType) {
                                $landlordFeeDisplay = $landlordFeeType;
                            }

                            // ── Tenant Broker structure preview (Residential only) ──────
                            $bidTenantBrokerStructure = data_get($bid,'get.tenant_broker_commission_structure','');
                            $bidTenantBrokerStructureDisplay = '';
                            if ($isResidential && $bidTenantBrokerStructure
                                && $bidTenantBrokerStructure !== 'no_compensation'
                                && $bidTenantBrokerStructure !== "No Compensation Offered to the Tenant's Broker") {
                                $bidTenantBrokerStructureDisplay = $bidTenantBrokerStructure;
                                // Resolve fee sub-value
                                $_tbs = data_get($bid,'get.tenant_broker_fee_structure','');
                                if ($_tbs === 'Percentage of the Rent Due Each Rental Period' && data_get($bid,'get.tenant_broker_percentage')) {
                                    $bidTenantBrokerStructureDisplay .= ' – ' . $fmtPercent(data_get($bid,'get.tenant_broker_percentage')) . ' of Rent Due Each Rental Period';
                                } elseif ($_tbs === 'Percentage of the Gross Lease Value' && data_get($bid,'get.tenant_broker_gross_lease')) {
                                    $bidTenantBrokerStructureDisplay .= ' – ' . $fmtPercent(data_get($bid,'get.tenant_broker_gross_lease')) . ' of Gross Lease Value';
                                } elseif ($_tbs === "Percentage of the First Month's Rent" && data_get($bid,'get.tenant_broker_first_month_rent')) {
                                    $bidTenantBrokerStructureDisplay .= ' – ' . $fmtPercent(data_get($bid,'get.tenant_broker_first_month_rent')) . " of First Month's Rent";
                                } elseif ($_tbs === 'Flat Fee' && data_get($bid,'get.tenant_broker_flat_fee')) {
                                    $bidTenantBrokerStructureDisplay .= ' – ' . $fmtMoney(data_get($bid,'get.tenant_broker_flat_fee')) . ' Flat Fee';
                                } elseif ($_tbs === 'other' && data_get($bid,'get.tenant_broker_other')) {
                                    $bidTenantBrokerStructureDisplay .= ' – Other: ' . data_get($bid,'get.tenant_broker_other');
                                }
                            }

                            // ── Match Score ────────────────────────────────────────────
                            $currentBidData = json_decode(json_encode(data_get($bid, 'get', [])), true) ?: [];
                            // Card score ALWAYS uses original listing baseline to ensure a consistent
                            // denominator across all bids on the same listing.
                            $originalScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
                                $landlordBaselineData, $currentBidData, null, $auctionPropType
                            );
                            // Use the most recently submitted non-terminal counter as the active baseline.
                            // Exclude accepted/rejected records so stale terminal counters are never used as baseline.
                            $latestActiveCounter = $counterBids->filter(fn($c) => !in_array((string)$c->status, ['accepted', 'rejected'], true))->first();
                            // Detect whether any counter exists for this bid (bid-scoped).
                            // This is used exclusively by the footer state machine to determine the 'countered' state.
                            $latestOwnerCounter = \App\Models\LandlordCounterTerm::where('landlord_agent_auction_id', data_get($bid, 'id'))
                                ->orderBy('created_at', 'desc')
                                ->first();
                            if ($latestActiveCounter && $latestActiveCounter->meta->count()) {
                                $counterBaselineData = $latestActiveCounter->meta->pluck('meta_value', 'meta_key')->toArray();
                                $latestCounterScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
                                    $counterBaselineData, $currentBidData, null, $auctionPropType
                                );
                                $showDualScore = true;
                            } else {
                                $latestCounterScore = null;
                                $showDualScore = false;
                            }
                            // Card display always uses original listing baseline score
                            $matchScore = $originalScore;
                            $totalScore       = $matchScore['overall_percent'];
                            $totalScoreColor  = $getScoreColor($totalScore);
                            $servicesScore    = $matchScore['services_match_percent'];
                            $servicesMatched  = $matchScore['services_matched_count'];
                            $servicesTotal    = $matchScore['services_baseline_total'];
                            $servicesMissingCount = $matchScore['services_missing_count'];
                            $servicesExtraCount   = $matchScore['services_extra_count'];
                            $brokerScore      = $matchScore['terms_match_percent'];
                            $brokerMatched    = $matchScore['terms_matched_count'];
                            $brokerTotal      = $matchScore['terms_baseline_total'];
                            $brokerMismatches = $matchScore['changed_terms'];
                            $termsChangedCount = $matchScore['terms_changed_count'];
                            $termsAddedCount   = $matchScore['terms_added_count'];
                            $baselineLabel     = "Landlord's Original Listing";
                            /**
                             * ZERO-BASELINE / NO-DATA GUARD
                             *
                             * If there is no comparable baseline match data, do not display 100%.
                             * Render "No match data available" instead.
                             *
                             * This behavior is locked by QA baseline documentation.
                             * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
                             */
                            $hasAnyBaseline    = ($brokerTotal > 0 || $servicesTotal > 0);
                        @endphp

                        <!-- Bid Card - Collapsible with custom JS toggle -->
                        <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                            <div class="card-header d-flex justify-content-between align-items-center bid-accordion-header"
                                 style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
                                 data-target="bidCollapse-{{ data_get($bid, 'id') }}"
                                 aria-expanded="false">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
                                    <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
                                </div>
                                <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusLabel }}</span>
                            </div>

                            <!-- Collapsible Content - Default collapsed -->
                            <div class="bid-collapse-content" id="bidCollapse-{{ data_get($bid, 'id') }}" style="display: none;">
                            <div class="card-body" style="padding: 20px;">

                                @if($isListingOwner || $isBidOwner)
                                <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

                                {{-- Counter Offer Notice Banner — visible immediately on accordion expand (owner/agent only) --}}
                                @if ($latestOwnerCounter && ($isListingOwner || $isBidOwner))
                                @php $latestOwnerCounterFromLandlord = ($latestOwnerCounter->user_id == data_get($auction, 'user_id')); @endphp
                                <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3"
                                     style="background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 0.9rem;">
                                    <i class="fa-solid fa-right-left mt-1" style="color: #e6a800; flex-shrink: 0;"></i>
                                    <div>
                                        @if ($isListingOwner && $latestOwnerCounterFromLandlord)
                                            <strong>Counter Offer Sent.</strong>
                                        @elseif ($isListingOwner && !$latestOwnerCounterFromLandlord)
                                            <strong>Counter Offer Received.</strong>
                                        @elseif ($isBidOwner && $latestOwnerCounterFromLandlord)
                                            <strong>Counter Offer Received.</strong>
                                        @elseif ($isBidOwner && !$latestOwnerCounterFromLandlord)
                                            <strong>Counter Offer Sent.</strong>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                {{-- ── Counter action row — directly on bid card ── --}}
                                @if ($latestOwnerCounter && ($isListingOwner || $isBidOwner) && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected')
                                @php $bidCardViewerSentLatestLandlord = ($isListingOwner && $latestOwnerCounterFromLandlord) || ($isBidOwner && !$latestOwnerCounterFromLandlord); @endphp
                                @if ($bidCardViewerSentLatestLandlord)
                                {{-- WAITING: single row — View CT + Edit CT --}}
                                <div class="d-flex gap-2 align-items-center mb-2">
                                    <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                    </a>
                                    @if ($isListingOwner)
                                    <a href="{{ route('landlord.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                    </a>
                                    @else
                                    <a href="{{ route('landlord.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                    </a>
                                    @endif
                                </div>
                                @else
                                {{-- RESPONSE: View CT only — Accept/Counter Back/Reject are on View Counter Terms page --}}
                                <div class="d-flex align-items-center mb-2">
                                    <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                    </a>
                                </div>
                                @endif
                                @endif

                                <!-- Offered Services Count Row -->
                                <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Offered Services:</span>
                                    <span style="color: #28a745; font-weight: 600;">{{ $servicesTotal > 0 ? $servicesMatched.'/'.$servicesTotal : 'No services requested' }}</span>{{ $servicesTotal > 0 ? ' matched' : '' }}
                                    @if ($servicesTotal > 0 && $servicesExtraCount > 0)
                                    <span class="text-muted ms-2">&bull; {{ $servicesExtraCount }} extra</span>
                                    @endif
                                    @if ($servicesTotal > 0 && $servicesMissingCount > 0)
                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ $servicesMissingCount }} missing</span>
                                    @endif
                                </p>
                                @if ($servicesExtraCount > 0)
                                <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: 4px 6px;">
                                    <span style="font-size: 0.9rem; line-height: 1.4;">&#11088;</span>
                                    <span style="font-weight: 500; color: #856404; font-size: 0.95rem;" title="Extra services were included by the Agent beyond the Landlord&#39;s original request. These do not increase the match score but may provide additional value.">Extra Value Added: {{ $servicesExtraCount }} {{ $servicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
                                    <span class="text-muted" style="font-size: 0.78rem; font-style: italic;">&mdash; does not affect match score</span>
                                </div>
                                @endif

                                <!-- Terms Match Row -->
                                @if ($hasAnyBaseline && $brokerTotal > 0)
                                <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Terms Match:</span>
                                    <span style="color: #28a745; font-weight: 600;">{{ $brokerMatched }}/{{ $brokerTotal }} matched</span>
                                    @if ($termsChangedCount > 0)
                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ $termsChangedCount }} changed</span>
                                    @endif
                                    @if ($termsAddedCount > 0)
                                    <span class="text-muted ms-2">&bull; {{ $termsAddedCount }} added</span>
                                    @endif
                                    @php $termsMissingCount = max(0, $brokerTotal - $brokerMatched - $termsChangedCount); @endphp
                                    @if ($termsMissingCount > 0)
                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ $termsMissingCount }} missing</span>
                                    @endif
                                </p>
                                <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
                                @elseif ($hasAnyBaseline && $brokerTotal === 0)
                                <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Terms Match:</span>
                                    <span class="text-muted">&mdash;</span>
                                </p>
                                @endif

                                <hr style="margin: 15px 0; border-color: #e0e0e0;">

                                <!-- Match Score Summary (Compact Display on Bid Card) -->
                                @php $showMatchScoreOnCard = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $isAgent && $userHasBid); @endphp
                                @if ($showMatchScoreOnCard && $hasAnyBaseline)
                                <div class="match-score-summary mb-3 p-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.88rem;">
                                    @if ($showDualScore && $originalScore && $latestCounterScore)
                                    {{-- DUAL SCORE: Original Match + Latest Counter Match side-by-side --}}
                                    <div class="mb-2">
                                        <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                            <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                        </span>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        {{-- Original Match --}}
                                        @php
                                            $osColor = $getScoreColor($originalScore['overall_percent']);
                                        @endphp
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #fff; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                    <span class="badge" style="background: {{ $osColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $originalScore['overall_percent'] }}%</span>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #6c757d;">vs. Landlord's Original Request</div>
                                                <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                    <div class="col-6" style="color: {{ $getScoreColor($originalScore['services_match_percent']) }};">Services {{ $originalScore['services_match_percent'] }}%</div>
                                                    <div class="col-6" style="color: {{ $getScoreColor($originalScore['terms_match_percent']) }};">Terms {{ $originalScore['terms_match_percent'] }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Latest Counter Match --}}
                                        @php
                                            $lcColor2 = $getScoreColor($latestCounterScore['overall_percent']);
                                        @endphp
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor2 }};">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                    <span class="badge" style="background: {{ $lcColor2 }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $latestCounterScore['overall_percent'] }}%</span>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #6c757d;">vs. Your Latest Counter</div>
                                                <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                    <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['services_match_percent']) }};">Services {{ $latestCounterScore['services_match_percent'] }}%</div>
                                                    <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['terms_match_percent']) }};">Terms {{ $latestCounterScore['terms_match_percent'] }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="small" style="color: #6c757d; font-style: italic; font-size: 0.76rem;">
                                        <i class="fa-solid fa-circle-info me-1"></i>Added services or terms do not increase either score.
                                    </div>
                                    @else
                                    {{-- SINGLE SCORE fallback --}}
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                            <i class="fa-solid fa-chart-pie me-2"></i>Match Score
                                        </span>
                                        <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1rem; padding: 6px 12px; color: white;">
                                            {{ $totalScore }}%
                                        </span>
                                    </div>
                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Services Match:</span>
                                                <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">
                                                {{ $servicesTotal > 0 ? 'Matched: '.$servicesMatched.'/'.$servicesTotal : 'No services requested' }}
                                                @if ($servicesTotal > 0 && $servicesExtraCount > 0) &bull; Extra: {{ $servicesExtraCount }}@endif
                                                @if ($servicesTotal > 0 && $servicesMissingCount > 0) &bull; Missing: {{ $servicesMissingCount }}@endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Terms Match:</span>
                                                <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">
                                                {{ $brokerTotal > 0 ? 'Matched: '.$brokerMatched.'/'.$brokerTotal : 'No terms provided' }}
                                                @if ($brokerTotal > 0 && $termsChangedCount > 0) &bull; Changed: {{ $termsChangedCount }}@endif
                                                @if ($brokerTotal > 0 && $termsAddedCount > 0) &bull; Added: {{ $termsAddedCount }}@endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 small text-muted">
                                        <i class="fa-solid fa-circle-info me-1"></i>Compared to: {{ $baselineLabel }}
                                    </div>
                                    <div class="mt-1 small" style="color: #6c757d; font-style: italic; font-size: 0.78rem;">
                                        Match Score compares this bid only to the Landlord's original request. Added services or added terms are shown for transparency but do not increase the score.
                                    </div>
                                    @endif
                                </div>
                                @endif

                                <!-- View Full Bid link -->
                                @if ($isListingOwner || $isBidOwner)
                                <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                   style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                    View Full Bid
                                </a>
                                @else
                                <span style="color: #888; font-style: italic; font-size: 0.95rem;">
                                    <i class="fa-solid fa-lock me-1"></i> Full bid details are private
                                </span>
                                @endif
                                <!-- Edit Bid button for bid owner -->
                                @if ($canEditWithdraw)
                                <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                    <a href="{{ route('agent.landlord.agent.auction.bid', $auction->id) }}?edit={{ data_get($bid, 'id') }}"
                                       class="btn btn-primary bid-action-btn">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Bid
                                    </a>
                                </div>
                                @elseif ($isBidOwner && $isExpired)
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa-solid fa-clock me-1"></i> Bidding has ended - edit unavailable
                                    </span>
                                </div>
                                @elseif ($isBidOwner && ($bidAccepted === 'accepted' || $bidAccepted === 'rejected'))
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa-solid fa-lock me-1"></i> Bid {{ $bidAccepted }} - edit unavailable
                                    </span>
                                </div>
                                @endif
                                    <!-- Private Data Section - visible to listing owner or bid owner -->
                                    @if ($isListingOwner || $isBidOwner)
                                    <!-- Private Data Modal -->
                                    <div class="modal fade"
                                        id="privateDataModal{{ data_get($bid, 'id') }}"
                                        tabindex="-1"
                                        aria-labelledby="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content"
                                                style="border-radius: 10px; border: none;">
                                                <div class="modal-header text-white"
                                                    style="background: #049399; border-bottom: none; padding: 20px;">
                                                    <h5 class="modal-title"
                                                        id="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                                        style="font-weight: 600;">
                                                        <i class="fa-solid fa-lock me-2"></i> Private
                                                        Compensation & Agreement Terms
                                                    </h5>
                                                </div>
                                                <div class="modal-body"
                                                    style="background: #fafafa; padding: 25px;">
                                                    @include('partials.bid_detail_body.landlord')
                                                </div>
                                                @php
                                                    // Compute modal-footer state — uses $latestOwnerCounter (owner-scoped) for countered detection
                                                    $_mfRawL    = data_get($bid, 'accepted', '0');
                                                    $_mfTermL   = in_array((string)$_mfRawL, ['accepted', 'rejected'], true);
                                                    $_mfActiveL = isset($latestOwnerCounter) && $latestOwnerCounter !== null;
                                                    // 'accepted' column stores 'no' for undecided bids (not false/'0'/null).
                                                    // Treat anything that is not a terminal state as '0' (undecided).
                                                    $mfStateL   = (!$_mfTermL && $_mfActiveL)
                                                        ? 'countered'
                                                        : ($_mfTermL ? (string)$_mfRawL : '0');
                                                    $mfOwnerIdL    = data_get($auction, 'user_id');
                                                    $mfOwnerFirstL = data_get($auction, 'user.first_name', '');
                                                    $mfOwnerLastL  = data_get($auction, 'user.last_name', '');
                                                    $mfAgentFirstL = data_get($bid, 'user.first_name', '');
                                                    $mfAgentLastL  = data_get($bid, 'user.last_name', '');
                                                    $mfIsOwnerL    = ((int)$auth_id === (int)$mfOwnerIdL);
                                                @endphp
                                                <div class="modal-footer"
                                                    style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                                                    {{-- Confidential notice --}}
                                                    <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                                        <i class="fa-solid fa-shield-halved me-2"></i>
                                                        <strong>Confidential:</strong> This information is private and only visible to you.
                                                    </div>

                                                    {{-- ── Bid action row (shared partial) ── --}}
                                                    @include('hire_landlord_agent.partials.bid_action_row', [
                                                        'bid'                  => $bid,
                                                        'auction'              => $auction,
                                                        'isOwner'              => $mfIsOwnerL,
                                                        'state'                => $mfStateL,
                                                        'isSold'               => in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true),
                                                        'isExpired'            => $isExpired,
                                                        'isTraditionalListing' => $isTraditionalListing,
                                                        'latestOwnerCounter'   => $latestOwnerCounter,
                                                        'ownerFirst'           => $mfOwnerFirstL,
                                                        'ownerLast'            => $mfOwnerLastL,
                                                        'agentFirst'           => $mfAgentFirstL,
                                                        'agentLast'            => $mfAgentLastL,
                                                    ])

                                                    {{-- ── Close button ── --}}
                                                    <div class="w-100 d-flex justify-content-end mt-2">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal"
                                                            style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Counter Bids -->

                                    @php
                                    $counterBids = \App\Models\LandlordCounterTerm::with(
                                    'meta',
                                    'user',
                                    )
                                    ->where('landlord_agent_auction_id', data_get($bid, 'id'))
                                    ->orderBy('created_at', 'desc')
                                    ->get();
                                    @endphp

                                    @php
                                    $rawState = data_get($bid, 'accepted', '0');
                                    $_isTerminalLandlord = in_array((string)$rawState, ['accepted', 'rejected'], true);
                                    $_hasLandlordCounterRecords = $counterBids->count() > 0;
                                    // 'accepted' column stores 'no' for undecided bids. Treat anything non-terminal as '0'.
                                    $state = (!$_isTerminalLandlord && $_hasLandlordCounterRecords)
                                        ? 'countered'
                                        : ($_isTerminalLandlord ? (string)$rawState : '0');
                                    $isOwnerRow = data_get($auction, 'user_id') == $auth_id;

                                    $ownerFirst = data_get($auction, 'user.first_name', '');
                                    $ownerLast = data_get($auction, 'user.last_name', '');
                                    $agentFirst = data_get($bid, 'user.first_name', '');
                                    $agentLast = data_get($bid, 'user.last_name', '');

                                    $ownerId = data_get($auction, 'user_id');

                                    // Add access control for counter bids
                                    $isListingOwner = data_get($auction, 'user_id') == $auth_id;
                                    $isBidOwner = data_get($bid, 'user_id') == $auth_id;
                                    $showCounterBids = $isListingOwner || $isBidOwner;

                                    // Check if any counter bid is accepted for this main bid
                                    $hasAcceptedCounterBid = $counterBids->contains('status', 'accepted');
                                    @endphp

                                    {{-- Counter Bidding Section - Only visible to listing owner and bidding agent --}}
                                    @if ($showCounterBids && $counterBids->count() > 0)
                                    <div class="counter-bids-section mt-4" id="counter-section-{{ data_get($bid, 'id') }}">
                                        <!-- Counter Bids Toggle Header (plain JS, no Bootstrap collapse — avoids flash from outer accordion interference) -->
                                        <div class="counter-bids-toggle"
                                            style="cursor: pointer;"
                                            onclick="event.stopPropagation(); var target = document.getElementById('counterBids{{ data_get($bid, 'id') }}'); var arrow = this.querySelector('.counter-arrow'); if(target.style.display === 'none' || target.style.display === '') { target.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; } else { target.style.display = 'none'; arrow.style.transform = 'rotate(0deg)'; }">
                                            <div
                                                class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                                <h5 class="mb-0" style="color: #2c3e50;">Counter
                                                    Bidding History</h5>
                                                <div class="d-flex align-items-center">
                                                    <span
                                                        class="badge bg-secondary me-2">{{ $counterBids->count() }}
                                                        counter offers</span>
                                                    <span class="counter-arrow" style="transition: transform 0.3s;">↓</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Counter Bids Content -->
                                        <div id="counterBids{{ data_get($bid, 'id') }}"
                                            class="counter-bids-content"
                                            style="display: none;"
                                            aria-labelledby="counterBidsHeading{{ data_get($bid, 'id') }}">
                                            <div
                                                class="accordion-body p-3 border border-top-0 rounded-bottom counter-font">
                                                @foreach ($counterBids as $counterBid)
                                                @php
                                                // Roles
                                                $isOwner =
                                                data_get($auction, 'user_id') ==
                                                $auth_id;
                                                $isAgent =
                                                data_get($bid, 'user_id') == $auth_id;
                                                $isCounterFromOwner =
                                                $counterBid->user_id ==
                                                data_get($auction, 'user_id');
                                                $isCounterFromAgent =
                                                $counterBid->user_id ==
                                                data_get($bid, 'user_id');

                                                // States
                                                $rawBidState = data_get(
                                                $bid,
                                                'accepted',
                                                '0',
                                                );
                                                $bidState = in_array(
                                                $rawBidState,
                                                [null, 0, '0', 'no', 'pending', false],
                                                true,
                                                )
                                                ? '0'
                                                : (string) $rawBidState;

                                                $rawCounterState = data_get(
                                                $counterBid,
                                                'status',
                                                '0',
                                                );
                                                $counterState = in_array(
                                                $rawCounterState,
                                                [null, 0, '0', 'pending'],
                                                true,
                                                )
                                                ? '0'
                                                : (string) $rawCounterState;

                                                // Actions visibility (other party, both pending)
                                                $showCounterActions = false;
                                                if (
                                                $bidState === '0' &&
                                                $counterState === '0' &&
                                                !$hasAcceptedCounterBid &&
                                                !$bidIsAccepted &&
                                                !$isSold &&
                                                !$isExpired
                                                ) {
                                                if ($isOwner && $isCounterFromAgent) {
                                                $showCounterActions = true;
                                                }
                                                if ($isAgent && $isCounterFromOwner) {
                                                $showCounterActions = true;
                                                }
                                                }

                                                // Names
                                                $ownerFirst = data_get(
                                                $auction,
                                                'user.first_name',
                                                '',
                                                );
                                                $ownerLast = data_get(
                                                $auction,
                                                'user.last_name',
                                                '',
                                                );
                                                $agentFirst = data_get(
                                                $bid,
                                                'user.first_name',
                                                '',
                                                );
                                                $agentLast = data_get(
                                                $bid,
                                                'user.last_name',
                                                '',
                                                );

                                                // For counter accepted/rejected: actor is ALWAYS the other party (not the creator)
                                                $actorUserId = $isCounterFromOwner
                                                ? data_get($bid, 'user_id')
                                                : data_get($auction, 'user_id');
                                                $actorFirst = $isCounterFromOwner
                                                ? $agentFirst
                                                : $ownerFirst;
                                                $actorLast = $isCounterFromOwner
                                                ? $agentLast
                                                : $ownerLast;

                                                // Creator names (for "pending" other-party view)
                                                $creatorFirst = data_get(
                                                $counterBid,
                                                'user.first_name',
                                                '',
                                                );
                                                $creatorLast = data_get(
                                                $counterBid,
                                                'user.last_name',
                                                '',
                                                );
                                                @endphp

                                                <div
                                                    class="counter-bid-card mb-3 p-3 border rounded mt-2">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                                        <h6 class="mb-0">
                                                            @if ($counterBid->user_id == Auth::id())
                                                            Your Counter Offer
                                                            @else
                                                            Counter Offer from
                                                            {{ data_get($counterBid, 'user.first_name') }}
                                                            {{ data_get($counterBid, 'user.last_name') }}
                                                            @endif
                                                        </h6>
                                                        <small
                                                            class="text-muted">{{ optional($counterBid->created_at)->format('M j, Y g:i A') }}</small>
                                                    </div>

                                                    @php
                                                        $allMeta = $counterBid->getAllMeta();

                                                        // ── A) Lease Fee composite display ──
                                                        $ctLeaseFeeType = $allMeta['purchase_fee_type'] ?? '';
                                                        $ctLeaseFeeDisplay = $ctLeaseFeeType;
                                                        if ($ctLeaseFeeType === 'Flat Fee') {
                                                            $lf = $allMeta['purchase_fee_flat'] ?? ($allMeta['purchase_fee_flat_commercial'] ?? null);
                                                            if ($lf) $ctLeaseFeeDisplay = '$'.number_format((float)$lf,2).' Flat Fee';
                                                        } elseif ($ctLeaseFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                                            $pct = $allMeta['purchase_fee_rental_period'] ?? null;
                                                            if ($pct) $ctLeaseFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                        } elseif ($ctLeaseFeeType === 'Percentage of the Gross Lease Value') {
                                                            $pct = $allMeta['purchase_fee_percentage_combo'] ?? null;
                                                            if ($pct) $ctLeaseFeeDisplay = $pct.'% of Gross Lease Value';
                                                        } elseif ($canon($ctLeaseFeeType) === "Percentage of the First Month's Rent") {
                                                            $pct = $allMeta['purchase_fee_flat_combo'] ?? null;
                                                            if ($pct) $ctLeaseFeeDisplay = $pct."% of First Month's Rent";
                                                        } elseif ($ctLeaseFeeType === 'Percentage of the Net Aggregate Rent') {
                                                            $pct = $allMeta['purchase_fee_net_aggregate'] ?? null;
                                                            if ($pct) $ctLeaseFeeDisplay = $pct.'% of Net Aggregate Rent';
                                                        } elseif ($ctLeaseFeeType === 'Percentage of the Gross Rent') {
                                                            $pct = $allMeta['purchase_fee_gross_rent'] ?? null;
                                                            if ($pct) $ctLeaseFeeDisplay = $pct.'% of Gross Rent';
                                                        } elseif ($canon($ctLeaseFeeType) === "Percentage of Month's Rent") {
                                                            $pct    = $allMeta['purchase_fee_monthly_percentage'] ?? null;
                                                            $months = $allMeta['purchase_fee_months'] ?? null;
                                                            if ($pct) $ctLeaseFeeDisplay = $pct."% of Month's Rent".($months ? " × $months months" : '');
                                                        } elseif ($ctLeaseFeeType === 'other') {
                                                            $oth = $allMeta['purchase_fee_other'] ?? ($allMeta['purchase_fee_other_commercial'] ?? null);
                                                            $ctLeaseFeeDisplay = 'Other: '.($oth ?: 'See details');
                                                        }

                                                        // ── A) Payment Timing composite display ──
                                                        $ctFeeTimingRaw = $allMeta['broker_fee_timing'] ?? '';
                                                        $ctFeeTimingDisplay = match($ctFeeTimingRaw) {
                                                            'full_execution' => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
                                                            default => $ctFeeTimingRaw,
                                                        };
                                                        if ($ctFeeTimingRaw === 'Deducted from Rent Collected') {
                                                            $d = $allMeta['broker_fee_days_from_rent'] ?? null;
                                                            if ($d) $ctFeeTimingDisplay .= " ($d calendar days)";
                                                        } elseif ($ctFeeTimingRaw === 'Paid Within Calendar Days After Executed Lease') {
                                                            $d = $allMeta['broker_fee_days_after_lease'] ?? null;
                                                            if ($d) $ctFeeTimingDisplay = "Within $d days after executed lease";
                                                        } elseif ($ctFeeTimingRaw === 'Paid Within Calendar Days of Tenant Rent Payment') {
                                                            $d = $allMeta['broker_fee_days_after_rent'] ?? null;
                                                            if ($d) $ctFeeTimingDisplay = "Within $d days of tenant rent payment";
                                                        } elseif ($ctFeeTimingRaw === 'other') {
                                                            $oth = $allMeta['broker_fee_timing_other'] ?? null;
                                                            $ctFeeTimingDisplay = $oth ?: 'Custom arrangement';
                                                        } elseif (in_array($ctFeeTimingRaw, ['50% due upon execution, 50% due upon commencement of agreement','50% due upon execution, 50% due upon occupancy of premises'])) {
                                                            $d2 = $allMeta['broker_fee_days_after_due_event'] ?? null;
                                                            if ($d2) $ctFeeTimingDisplay .= " (second installment within $d2 days)";
                                                        }

                                                        // ── A) Renewal Fee composite display ──
                                                        $ctRenewalFeeType = $allMeta['renewal_fee_type'] ?? '';
                                                        $ctRenewalFeeDisplay = $ctRenewalFeeType;
                                                        if ($ctRenewalFeeType === 'Flat Fee') {
                                                            $flat = $allMeta['renewal_fee_flat_free'] ?? null;
                                                            if ($flat) $ctRenewalFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                                        } elseif ($ctRenewalFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                                            $pct = $allMeta['renewal_fee_percentage'] ?? null;
                                                            if ($pct) $ctRenewalFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                        } elseif ($ctRenewalFeeType === 'Percentage of the Gross Lease Value') {
                                                            $pct = $allMeta['renewal_fee_lease_value'] ?? null;
                                                            if ($pct) $ctRenewalFeeDisplay = $pct.'% of Gross Lease Value';
                                                        } elseif ($canon($ctRenewalFeeType) === "Percentage of the First Month's Rent") {
                                                            $pct = $allMeta['renewal_fee_first_month'] ?? null;
                                                            if ($pct) $ctRenewalFeeDisplay = $pct."% of First Month's Rent";
                                                        } elseif ($ctRenewalFeeType === 'Percentage of the Net Aggregate Rent') {
                                                            $pct = $allMeta['renewal_fee_percentage'] ?? null;
                                                            if ($pct) $ctRenewalFeeDisplay = $pct.'% of Net Aggregate Rent';
                                                        } elseif ($ctRenewalFeeType === 'Percentage of the Gross Rent') {
                                                            $pct = $allMeta['renewal_fee_lease_value'] ?? null;
                                                            if ($pct) $ctRenewalFeeDisplay = $pct.'% of Gross Rent';
                                                        } elseif ($canon($ctRenewalFeeType) === "Percentage of Month's Rent") {
                                                            $pct    = $allMeta['renewal_fee_first_month'] ?? null;
                                                            $months = $allMeta['renewal_fee_no_of_months'] ?? null;
                                                            if ($pct) $ctRenewalFeeDisplay = $pct."% of Month's Rent".($months ? " × $months months" : '');
                                                        } elseif ($ctRenewalFeeType === 'other') {
                                                            $oth = $allMeta['renewal_fee_custom'] ?? null;
                                                            $ctRenewalFeeDisplay = 'Other: '.($oth ?: 'See details');
                                                        }

                                                        // ── B) Tenant Broker — structure and fee SEPARATELY ──
                                                        $ctTenantBrokerStructure  = $allMeta['tenant_broker_commission_structure'] ?? '';
                                                        $ctTenantBrokerFeeDisplay = '';
                                                        $ctTbs = $allMeta['tenant_broker_fee_structure'] ?? '';
                                                        if ($ctTenantBrokerStructure && $ctTbs) {
                                                            if ($ctTbs === 'Percentage of the Rent Due Each Rental Period') {
                                                                $pct = $allMeta['tenant_broker_percentage'] ?? null;
                                                                if ($pct) $ctTenantBrokerFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                            } elseif ($ctTbs === 'Percentage of the Gross Lease Value') {
                                                                $pct = $allMeta['tenant_broker_gross_lease'] ?? null;
                                                                if ($pct) $ctTenantBrokerFeeDisplay = $pct.'% of Gross Lease Value';
                                                            } elseif ($ctTbs === "Percentage of the First Month's Rent") {
                                                                $pct = $allMeta['tenant_broker_first_month_rent'] ?? null;
                                                                if ($pct) $ctTenantBrokerFeeDisplay = $pct."% of First Month's Rent";
                                                            } elseif ($ctTbs === 'Flat Fee') {
                                                                $flat = $allMeta['tenant_broker_flat_fee'] ?? null;
                                                                if ($flat) $ctTenantBrokerFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                                            } elseif ($ctTbs === 'other') {
                                                                $oth = $allMeta['tenant_broker_other'] ?? null;
                                                                if ($oth) $ctTenantBrokerFeeDisplay = 'Other: '.$oth;
                                                            }
                                                        }
                                                        // Combined display for counter-term comparison
                                                        $ctTenantBrokerDisplay = $ctTenantBrokerStructure . ($ctTenantBrokerFeeDisplay ? ' – '.$ctTenantBrokerFeeDisplay : '');

                                                        // ── C) Lease-Option composite displays ──
                                                        $ctLeaseOptInterest = $allMeta['interested_lease_option_agreement'] ?? '';
                                                        $ctLeaseOptionCreatedDisplay   = '-';
                                                        $ctLeaseOptionExercisedDisplay = '-';
                                                        if ($ctLeaseOptInterest === 'Yes') {
                                                            $lt = $allMeta['lease_type'] ?? null;
                                                            $lv = $allMeta['lease_value'] ?? null;
                                                            if ($lt && $lv) {
                                                                $ctLeaseOptionCreatedDisplay = ($lt === 'percent')
                                                                    ? ($fmtPercent($lv) ? $fmtPercent($lv).' of Total Purchase Price' : '-')
                                                                    : ($fmtMoney($lv) ?? '-');
                                                            }
                                                            $pt = $allMeta['purchase_type'] ?? null;
                                                            $pv = $allMeta['purchase_value'] ?? null;
                                                            if ($pt && $pv) {
                                                                $ctLeaseOptionExercisedDisplay = ($pt === 'percent')
                                                                    ? ($fmtPercent($pv) ? $fmtPercent($pv).' of Total Purchase Price' : '-')
                                                                    : ($fmtMoney($pv) ?? '-');
                                                            }
                                                        }

                                                        // ── D) Purchase Fee composite display ──
                                                        $ctSellingInterest  = $allMeta['interested_in_selling'] ?? '';
                                                        $ctPurchaseFeeDisplay = '-';
                                                        if ($ctSellingInterest === 'Yes') {
                                                            $ist = $allMeta['interested_in_selling_type'] ?? '';
                                                            if ($ist === 'Percentage of the Total Purchase Price') {
                                                                $pct = $allMeta['landlord_broker_purchase_price'] ?? null;
                                                                $ctPurchaseFeeDisplay = $pct ? $fmtPercent($pct).' of Total Purchase Price' : $ist;
                                                            } elseif ($ist === 'Percentage of the Total Purchase Price + Flat Fee') {
                                                                $pct  = $allMeta['landlord_broker_percentage_price'] ?? null;
                                                                $flat = $allMeta['landlord_broker_dollar_price'] ?? null;
                                                                $ctPurchaseFeeDisplay = trim(($pct ? $fmtPercent($pct).' of Total Purchase Price' : '').($pct && $flat ? ' + ' : '').($flat ? $fmtMoney($flat) : ''));
                                                                if (!$ctPurchaseFeeDisplay) $ctPurchaseFeeDisplay = $ist;
                                                            } elseif ($ist === 'Flat Fee') {
                                                                $flat = $allMeta['landlord_broker_flate_fee'] ?? null;
                                                                $ctPurchaseFeeDisplay = $flat ? '$'.number_format((float)$flat,2).' Flat Fee' : $ist;
                                                            } elseif ($ist === 'Other') {
                                                                $oth = $allMeta['landlord_broker_other'] ?? null;
                                                                $ctPurchaseFeeDisplay = $oth ? 'Other: '.$oth : 'Other';
                                                            } else {
                                                                $ctPurchaseFeeDisplay = $ist ?: '-';
                                                            }
                                                        }

                                                        // ── E) Agency Agreement Timeframe display ──
                                                        $ctAgencyTimeframe = $allMeta['agency_agreement_timeframe'] ?? '';
                                                        $ctAgencyTimeframeDisplay = (strtolower(trim($ctAgencyTimeframe)) === 'other')
                                                            ? ($allMeta['agency_agreement_custom'] ?? 'Other')
                                                            : $ctAgencyTimeframe;

                                                        // ── E) Property Management Fee composite display ──
                                                        $ctPmFeeDisplay = '-';
                                                        if (($allMeta['interested_in_property_management'] ?? '') === 'yes') {
                                                            $pmFeeType = $allMeta['interested_in_property_management_fee'] ?? '';
                                                            $ctPmFeeDisplay = $pmFeeType;
                                                            if ($pmFeeType === 'Percentage of the Gross Lease Value') {
                                                                $pct = $allMeta['interested_in_property_management_fee_gross_lease'] ?? null;
                                                                if ($pct) $ctPmFeeDisplay = $pct.'% of Gross Lease Value';
                                                            } elseif ($pmFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                                                $pct = $allMeta['interested_in_property_management_fee_rental_periord'] ?? null;
                                                                if ($pct) $ctPmFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                            } elseif ($pmFeeType === 'Flat Fee') {
                                                                $flat = $allMeta['interested_in_property_management_fee_flate_free'] ?? null;
                                                                if ($flat) $ctPmFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                                            } elseif ($pmFeeType === 'Other') {
                                                                $oth = $allMeta['interested_in_property_management_fee_other'] ?? null;
                                                                if ($oth) $ctPmFeeDisplay = 'Other: '.$oth;
                                                            }
                                                        }

                                                        $ctHasBrokerComp = !empty($ctLeaseFeeType) || !empty($ctFeeTimingRaw) || !empty($ctRenewalFeeType)
                                                            || !empty($allMeta['expansion_commission_percentage'])
                                                            || !empty($ctTenantBrokerStructure)
                                                            || !empty($ctLeaseOptInterest)
                                                            || !empty($ctSellingInterest)
                                                            || !empty($allMeta['protection_period'])
                                                            || !empty($allMeta['early_termination_fee_option'])
                                                            || !empty($ctAgencyTimeframe)
                                                            || !empty($allMeta['interested_in_property_management'])
                                                            || !empty($allMeta['brokerage_relationship'])
                                                            || !empty($allMeta['additional_details_broker'])
                                                            || !empty($allMeta['additional_details']);

                                                        // === Diff helpers: counter vs original bid ===
                                                        // Compare two composite display strings (normalized)
                                                        $ctCompositeChanged = function(string $cDisplay, string $oDisplay): bool {
                                                            $norm = fn($v) => preg_replace('/[\s$,]/', '', strtolower(trim($v)));
                                                            return $norm($cDisplay) !== $norm($oDisplay);
                                                        };
                                                        // Compare a single raw meta key to the original bid's stored value
                                                        $ctIsChanged = function($counterVal, string $origKey) use ($bid): bool {
                                                            $origVal = data_get($bid, 'get.' . $origKey, null);
                                                            $norm = fn($v) => preg_replace('/[\s$,%]/', '', strtolower(trim((string)($v ?? ''))));
                                                            return $norm($counterVal) !== $norm($origVal);
                                                        };
                                                        $ctChangedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                                        $ctChangedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';

                                                        // Services diff: counter services vs ORIGINAL BID services
                                                        $origBidSvcsRaw = data_get($bid, 'get.services', []);
                                                        if (is_string($origBidSvcsRaw)) $origBidSvcsRaw = json_decode($origBidSvcsRaw, true) ?: [];
                                                        $origBidSvcsNorm = array_values(array_map(
                                                            fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$s),
                                                            array_filter((array)$origBidSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other')
                                                        ));
                                                        $origBidOtherRaw = data_get($bid, 'get.other_services', []);
                                                        if (is_string($origBidOtherRaw)) $origBidOtherRaw = json_decode($origBidOtherRaw, true) ?: [];
                                                        $origBidOtherNorm = array_values(array_filter(array_map(
                                                            fn($s) => strtolower(trim((string)$s)),
                                                            array_filter((array)$origBidOtherRaw, fn($s) => is_string($s) && trim($s) !== '')
                                                        )));
                                                    @endphp

                                                    @if ($ctHasBrokerComp)
                                                    <div class="mb-4">
                                                        <h6 class="mb-3" style="font-weight: 600; color: #049399; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                        </h6>

                                                        {{-- A) Landlord's Broker Lease Fee --}}
                                                        @if (!empty($ctLeaseFeeType) || !empty($ctFeeTimingRaw) || !empty($ctRenewalFeeType) || !empty($allMeta['expansion_commission_percentage']))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">A) Landlord's Broker Lease Fee</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (!empty($ctLeaseFeeType))
                                                                @php $ctLeaseFeeChg = $ctCompositeChanged($ctLeaseFeeDisplay, $leaseFeeDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctLeaseFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Landlord's Broker Lease Fee:</span> {{ $ctLeaseFeeDisplay }}{!! $ctLeaseFeeChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($ctFeeTimingRaw))
                                                                @php $ctFeeTimingChg = $ctCompositeChanged($ctFeeTimingDisplay, $feeTimingDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctFeeTimingChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ $ctFeeTimingDisplay }}{!! $ctFeeTimingChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($ctRenewalFeeType))
                                                                @php $ctRenewalFeeChg = $ctCompositeChanged($ctRenewalFeeDisplay, $renewalFeeDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctRenewalFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Lease Renewal/Extension Fee:</span> {{ $ctRenewalFeeDisplay }}{!! $ctRenewalFeeChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['expansion_commission_percentage']))
                                                                @php $ctExpChg = $ctIsChanged($allMeta['expansion_commission_percentage'], 'expansion_commission_percentage'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctExpChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Expansion Commission for Lease Amendment:</span> {{ $allMeta['expansion_commission_percentage'] }}% of original commission{!! $ctExpChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- B) Tenant's Broker Compensation --}}
                                                        @if (!empty($ctTenantBrokerStructure))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">B) Tenant's Broker Compensation</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $ctTenantBrokerStructureChg = $ctIsChanged($ctTenantBrokerStructure, 'tenant_broker_commission_structure'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctTenantBrokerStructureChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ $ctTenantBrokerStructure }}{!! $ctTenantBrokerStructureChg ? $ctChangedBadge : '' !!}</li>
                                                                @if ($ctTenantBrokerFeeDisplay)
                                                                @php $ctTenantBrokerFeeChg = $ctCompositeChanged($ctTenantBrokerFeeDisplay, $tenantBrokerFeeDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctTenantBrokerFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $ctTenantBrokerFeeDisplay }}{!! $ctTenantBrokerFeeChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- C) Lease-Option Details --}}
                                                        @if (!empty($ctLeaseOptInterest))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">C) Lease-Option Details</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $ctLeaseOptChg = $ctIsChanged($ctLeaseOptInterest, 'interested_lease_option_agreement'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctLeaseOptChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Interested in Offering a Lease-Option Agreement:</span> {{ $ctLeaseOptInterest }}{!! $ctLeaseOptChg ? $ctChangedBadge : '' !!}</li>
                                                                @if ($ctLeaseOptInterest === 'Yes')
                                                                    @if ($ctLeaseOptionCreatedDisplay !== '-')
                                                                    @php $ctLeaseCreatedChg = $ctCompositeChanged($ctLeaseOptionCreatedDisplay, $leaseOptionCreatedDisplay ?? ''); @endphp
                                                                    <li class="mb-1" style="font-size: 12px; {{ $ctLeaseCreatedChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> {{ $ctLeaseOptionCreatedDisplay }}{!! $ctLeaseCreatedChg ? $ctChangedBadge : '' !!}</li>
                                                                    @endif
                                                                    @if ($ctLeaseOptionExercisedDisplay !== '-')
                                                                    @php $ctLeaseExercisedChg = $ctCompositeChanged($ctLeaseOptionExercisedDisplay, $leaseOptionExercisedDisplay ?? ''); @endphp
                                                                    <li class="mb-1" style="font-size: 12px; {{ $ctLeaseExercisedChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $ctLeaseOptionExercisedDisplay }}{!! $ctLeaseExercisedChg ? $ctChangedBadge : '' !!}</li>
                                                                    @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- D) Purchase Fee Details --}}
                                                        @if (!empty($ctSellingInterest))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">D) Purchase Fee Details</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $ctSellingChg = $ctIsChanged($ctSellingInterest, 'interested_in_selling'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctSellingChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Interested in Selling the Property:</span> {{ $ctSellingInterest }}{!! $ctSellingChg ? $ctChangedBadge : '' !!}</li>
                                                                @if ($ctSellingInterest === 'Yes' && $ctPurchaseFeeDisplay !== '-')
                                                                @php $ctPurchaseFeeChg = $ctCompositeChanged($ctPurchaseFeeDisplay, $purchaseFeeDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctPurchaseFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Purchase Fee:</span> {{ $ctPurchaseFeeDisplay }}{!! $ctPurchaseFeeChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- E) Legal Terms --}}
                                                        @if (!empty($allMeta['protection_period']) || !empty($allMeta['early_termination_fee_option']) || !empty($ctAgencyTimeframe) || !empty($allMeta['interested_in_property_management']))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">E) Legal Terms</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (!empty($allMeta['protection_period']))
                                                                @php $ctProtChg = $ctIsChanged($allMeta['protection_period'], 'protection_period'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctProtChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $allMeta['protection_period'] }} days{!! $ctProtChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['early_termination_fee_option']))
                                                                @php $ctEtfChg = $ctIsChanged($allMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctEtfChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ $allMeta['early_termination_fee_option'] === 'yes' ? 'Yes' : 'No' }}{!! $ctEtfChg ? $ctChangedBadge : '' !!}</li>
                                                                @if ($allMeta['early_termination_fee_option'] === 'yes' && !empty($allMeta['early_termination_fee_amount']))
                                                                @php $ctEtfAmtChg = $ctIsChanged($allMeta['early_termination_fee_amount'], 'early_termination_fee_amount'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctEtfAmtChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($allMeta['early_termination_fee_amount']) ?? ('$'.$allMeta['early_termination_fee_amount']) }}{!! $ctEtfAmtChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @endif
                                                                @if (!empty($ctAgencyTimeframe))
                                                                @php $ctAgencyTfChg = $ctCompositeChanged($ctAgencyTimeframeDisplay, $agencyTimeframeDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctAgencyTfChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Landlord Agency Agreement Timeframe:</span> {{ $ctAgencyTimeframeDisplay }}{!! $ctAgencyTfChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['interested_in_property_management']))
                                                                @php $ctPmChg = $ctIsChanged($allMeta['interested_in_property_management'], 'interested_in_property_management'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctPmChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Interested in Property Management:</span> {{ ($allMeta['interested_in_property_management'] === 'yes') ? 'Yes' : 'No' }}{!! $ctPmChg ? $ctChangedBadge : '' !!}</li>
                                                                @if (($allMeta['interested_in_property_management'] === 'yes') && $ctPmFeeDisplay !== '-')
                                                                @php $ctPmFeeChg = $ctCompositeChanged($ctPmFeeDisplay, $pmFeeDisplay ?? ''); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctPmFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Property Management Fee:</span> {{ $ctPmFeeDisplay }}{!! $ctPmFeeChg ? $ctChangedBadge : '' !!}</li>
                                                                @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- F) Brokerage Relationship --}}
                                                        @if (!empty($allMeta['brokerage_relationship']))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">F) Brokerage Relationship</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $ctBrokerRelChg = $ctIsChanged($allMeta['brokerage_relationship'], 'brokerage_relationship'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctBrokerRelChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $allMeta['brokerage_relationship'] }}{!! $ctBrokerRelChg ? $ctChangedBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- G) Additional Terms --}}
                                                        @if (!empty($allMeta['additional_details_broker']))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">G) Additional Terms</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $ctAddTermsChg = $ctIsChanged($allMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctAddTermsChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Additional Terms:</span> {{ $allMeta['additional_details_broker'] }}{!! $ctAddTermsChg ? $ctChangedBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- H) Referral Fee --}}
                                                        @if ($auction->isCreatedByAgent() && !empty($allMeta['referral_fee_percent']))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">H) Referral Fee</div>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $ctRefFeeChg = $ctIsChanged($allMeta['referral_fee_percent'], 'referral_fee_percent'); @endphp
                                                                <li class="mb-1" style="font-size: 12px; {{ $ctRefFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Referral Fee (%):</span> {{ $allMeta['referral_fee_percent'] }}%{!! $ctRefFeeChg ? $ctChangedBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                    </div>
                                                    @endif

                                                    {{-- Additional Details --}}
                                                    @if (!empty($allMeta['additional_details']))
                                                    <div class="mb-3">
                                                        <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;"><i class="fa-solid fa-circle-info me-1"></i>Additional Details</div>
                                                        @php $ctAddDetailsChg = $ctIsChanged($allMeta['additional_details'], 'additional_details'); @endphp
                                                        <div class="ps-3" style="font-size: 12px; {{ $ctAddDetailsChg ? $ctChangedStyle : '' }}">{{ $allMeta['additional_details'] }}{!! $ctAddDetailsChg ? $ctChangedBadge : '' !!}</div>
                                                    </div>
                                                    @endif



                                                    <!-- Services Offered (diff: counter vs original bid) -->
                                                    @php
                                                    $ctSvcsRaw = $allMeta['services'] ?? [];
                                                    if (is_string($ctSvcsRaw) && !empty($ctSvcsRaw)) {
                                                        $ctSvcsParsed = json_decode($ctSvcsRaw, true) ?: [];
                                                    } else {
                                                        $ctSvcsParsed = is_array($ctSvcsRaw) ? $ctSvcsRaw : [];
                                                    }
                                                    $ctSvcsParsed = array_values(array_filter($ctSvcsParsed, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));

                                                    $ctOtherRaw = $allMeta['other_services'] ?? [];
                                                    if (is_string($ctOtherRaw) && !empty($ctOtherRaw)) {
                                                        $ctOtherParsed = json_decode($ctOtherRaw, true) ?: [];
                                                    } else {
                                                        $ctOtherParsed = is_array($ctOtherRaw) ? $ctOtherRaw : [];
                                                    }
                                                    $ctOtherParsed = array_values(array_filter($ctOtherParsed, fn($s) => is_string($s) && trim($s) !== ''));

                                                    // Normalize counter services for diff
                                                    $ctSvcsNorm = array_map(
                                                        fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$s),
                                                        $ctSvcsParsed
                                                    );

                                                    // Determine added services (in counter but not in original bid)
                                                    $ctSvcIsAdded = fn(string $svc): bool =>
                                                        !in_array(\App\Helpers\LandlordBidMatchScoreHelper::normalizeService($svc), $origBidSvcsNorm, true);

                                                    // Build removed services list (in original bid but not in counter)
                                                    $ctRemovedSvcs = array_filter($origBidSvcsNorm, fn($n) => !in_array($n, $ctSvcsNorm, true));
                                                    // Map back to display text from original bid raw
                                                    $origBidSvcsDisplay = array_values(array_filter(
                                                        is_string(data_get($bid, 'get.services', [])) ? json_decode(data_get($bid, 'get.services', '[]'), true) ?? [] : (array)data_get($bid, 'get.services', []),
                                                        fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'
                                                    ));
                                                    $ctRemovedDisplay = array_values(array_filter($origBidSvcsDisplay, fn($s) =>
                                                        in_array(\App\Helpers\LandlordBidMatchScoreHelper::normalizeService($s), $ctRemovedSvcs, true)
                                                    ));

                                                    // Other services diff
                                                    $ctOtherIsAdded = fn(string $s): bool =>
                                                        !in_array(strtolower(trim($s)), $origBidOtherNorm, true);
                                                    $ctOtherRemovedDisplay = array_values(array_filter(
                                                        is_string(data_get($bid, 'get.other_services', [])) ? json_decode(data_get($bid, 'get.other_services', '[]'), true) ?? [] : (array)data_get($bid, 'get.other_services', []),
                                                        fn($s) => is_string($s) && trim($s) !== '' && !in_array(strtolower(trim($s)), array_map(fn($x) => strtolower(trim($x)), $ctOtherParsed), true)
                                                    ));

                                                    $hasCtSvcs = !empty($ctSvcsParsed) || !empty($ctOtherParsed);

                                                    // Normalizer for category-membership matching (handles smart quotes / Unicode escapes)
                                                    $normForCat = function(string $s): string {
                                                        $s = mb_strtolower(trim($s));
                                                        $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $s);
                                                        $s = str_replace(['\\u2019', '\\u2018', '\\u201c', '\\u201d', '\\u201C', '\\u201D'], ["'", "'", '"', '"', '"', '"'], $s);
                                                        $s = str_replace(["\u{2014}", '\\u2014'], ['-', '-'], $s);
                                                        $s = preg_replace('/\s+/', ' ', $s);
                                                        return trim($s);
                                                    };

                                                    // Category map for grouping services — mirrors the bid-detail partial
                                                    $modalCats = $isCommercial ? $landlordCommercialCategories : $landlordResidentialCategories;
                                                    @endphp

                                                    <div class="mb-4" style="margin-top: 20px;">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                                        </h6>

                                                        @if ($hasCtSvcs)
                                                            @foreach ($modalCats as $catName => $catSvcs)
                                                                @php
                                                                    $normCatKeys = array_map($normForCat, $catSvcs);
                                                                    $inCat = array_filter($ctSvcsParsed, fn($svc) => in_array($normForCat($svc), $normCatKeys));
                                                                @endphp
                                                                @if (!empty($inCat))
                                                                <div class="mb-3">
                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                                        @foreach ($inCat as $svc)
                                                                            @php $svcAdded = $ctSvcIsAdded($svc); @endphp
                                                                            @if ($svcAdded)
                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                                <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $svc }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                            </li>
                                                                            @else
                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $svc }}</li>
                                                                            @endif
                                                                            @if (strtolower(trim($svc)) === 'provide digital photo enhancements')
                                                                            @php
                                                                                $ctPhotoEnhRaw = $allMeta['photo_enhancements'] ?? [];
                                                                                if (is_string($ctPhotoEnhRaw)) $ctPhotoEnhRaw = json_decode($ctPhotoEnhRaw, true) ?: [];
                                                                                $ctCustomEnh = $allMeta['custom_enhancement'] ?? '';
                                                                                $ctEnhOrder = ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'];
                                                                            @endphp
                                                                            @if (!empty($ctPhotoEnhRaw))
                                                                            <ul style="padding-left: 1.5rem; margin: 4px 0; list-style: disc;">
                                                                                @foreach ($ctEnhOrder as $ctEnh)
                                                                                    @if (in_array($ctEnh, $ctPhotoEnhRaw))
                                                                                        @if ($ctEnh === 'Other' && !empty($ctCustomEnh))
                                                                                            <li style="font-size: 0.85rem;">{{ $ctCustomEnh }}</li>
                                                                                        @elseif ($ctEnh !== 'Other')
                                                                                            <li style="font-size: 0.85rem;">{{ $ctEnh }}</li>
                                                                                        @endif
                                                                                    @endif
                                                                                @endforeach
                                                                            </ul>
                                                                            @endif
                                                                            @endif
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                            @endforeach

                                                            @if (!empty($ctOtherParsed))
                                                            <div class="mb-3">
                                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                                    @foreach ($ctOtherParsed as $otherSvc)
                                                                        @php $otherAdded = $ctOtherIsAdded($otherSvc); @endphp
                                                                        @if ($otherAdded)
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                            <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $otherSvc }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                        </li>
                                                                        @else
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherSvc }}</li>
                                                                        @endif
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif

                                                            @if (!empty($ctRemovedDisplay) || !empty($ctOtherRemovedDisplay))
                                                            <div class="mb-3 mt-3 p-3" style="background-color: #fff5f5; border-radius: 6px; border: 1px solid #f5c6cb;">
                                                                <div class="fw-bold mb-1" style="color: #dc3545; font-size: 0.95rem;">
                                                                    <i class="fa-solid fa-minus-circle me-1"></i>Removed Services
                                                                </div>
                                                                <ul class="services mb-0" style="margin-top: 0.5rem; padding-left: 1.2rem; list-style: none;">
                                                                    @foreach ($ctRemovedDisplay as $rSvc)
                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                                        <i class="fa-solid fa-circle-xmark me-1"></i>{{ $rSvc }}
                                                                    </li>
                                                                    @endforeach
                                                                    @foreach ($ctOtherRemovedDisplay as $rSvc)
                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                                        <i class="fa-solid fa-circle-xmark me-1"></i>{{ $rSvc }}
                                                                    </li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif
                                                        @else
                                                        <div class="text-muted" style="font-style: italic;">No services selected for this counter.</div>
                                                        @endif
                                                    </div>

                                                    <!-- Counter actions (only when both pending & viewer is the other party & no counter bid is accepted) -->
                                                    @inject('carbon', 'Carbon\Carbon')


                                                    @php
                                                    // Step 1: Get auction_time and check if it's not empty
                                                    $auctionTime = data_get($auction->get, 'auction_time');

                                                    // Step 2: Base date is $auction->created_at (not from get)
                                                    $baseDate = $carbon::parse($auction->created_at);

                                                    // Step 3: Calculate expiration based on conditions
                                                    if (!empty($auctionTime) && $auctionTime !== "" && $auctionTime !== null) {
                                                    // Extract number from auction_time (e.g., "10 Days" -> 10)
                                                    preg_match('/\d+/', $auctionTime, $matches);
                                                    $days = isset($matches[0]) ? (int)$matches[0] : 0;

                                                    $expiration = $days > 0
                                                    ? $baseDate->copy()->addDays($days)
                                                    : null;
                                                    } else {
                                                    // Use expiration_date from get if auction_time is empty
                                                    $expirationDate = data_get($auction->get, 'expiration_date');
                                                    $expiration = !empty($expirationDate)
                                                    ? $carbon::parse($expirationDate)
                                                    : null;
                                                    }

                                                    // Step 4: Check if expired
                                                    $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
                                                    @endphp

                                                    {{-- Step 6: Display Actions or Expired Message --}}
                                                    @if ($showCounterActions)
                                                    <div class="mt-3 pt-3 border-top">
                                                        {{-- Actions are on View Counter Terms page only --}}
                                                        <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                            <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                        </a>
                                                    </div>
                                                    @endif

                                                    <!-- Counter footer status -->
                                                    <div class="mt-3 pt-3 border-top">
                                                        @if ($counterState === 'accepted')
                                                        @if (Auth::id() == $actorUserId)
                                                        <div class="alert alert-success mb-0 py-1 small">
                                                            ✅ This counter bid has been accepted.
                                                        </div>
                                                        @else
                                                        <div class="alert alert-success mb-0 py-1 small">
                                                            ✅ {{ trim($actorFirst . ' ' . $actorLast) }} accepted the counter bid.
                                                        </div>
                                                        @endif
                                                        @elseif ($counterState === 'rejected')
                                                        @if (Auth::id() == $actorUserId)
                                                        <div class="alert alert-danger mb-0 py-1 small">
                                                            ❌ This counter bid has been rejected.
                                                        </div>
                                                        @else
                                                        <div class="alert alert-danger mb-0 py-1 alert-font">
                                                            ❌ {{ trim($actorFirst . ' ' . $actorLast) }} rejected the counter bid.
                                                        </div>
                                                        @endif
                                                        @elseif ($counterState === '0')
                                                        @if ($counterBid->user_id == Auth::id())
                                                        <div class="alert alert-secondary mb-0 py-1 small">
                                                            ⏳ Waiting for response from
                                                            {{ $isCounterFromOwner ? trim($agentFirst . ' ' . $agentLast) : trim($ownerFirst . ' ' . $ownerLast) }}...
                                                        </div>
                                                        @else
                                                        <div class="alert alert-light mb-0 py-1 small"
                                                            style="font-size:13px;">
                                                            ⏳ Counter bid from
                                                            {{ trim($creatorFirst . ' ' . $creatorLast) }}
                                                            is pending.
                                                        </div>
                                                        @endif
                                                        @endif
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                @else
                                {{-- ===== COMPETITOR SUMMARY (other agent viewing another agent's bid) ===== --}}
                                <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">
                                <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Offered Services:</span>
                                    <span style="color: #28a745; font-weight: 600;">{{ $servicesTotal > 0 ? $servicesMatched.'/'.$servicesTotal : 'No services requested' }}</span>{{ $servicesTotal > 0 ? ' matched' : '' }}
                                </p>
                                <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
                                @if ($hasAnyBaseline && $brokerTotal > 0)
                                <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Terms Match:</span>
                                    <span style="color: #28a745; font-weight: 600;">{{ $brokerMatched }}/{{ $brokerTotal }} matched</span>
                                </p>
                                <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
                                @endif
                                <hr style="margin: 15px 0; border-color: #e0e0e0;">
                                @if ($hasAnyBaseline)
                                <div class="match-score-summary mb-3 p-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.88rem;">
                                    <div class="mb-2">
                                        <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                            <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                        </span>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #fff; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                    <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $totalScore }}%</span>
                                                </div>
                                                <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                    <div class="col-6" style="color: {{ $getScoreColor($originalScore['services_match_percent']) }};">Services {{ $originalScore['services_match_percent'] }}%</div>
                                                    <div class="col-6" style="color: {{ $getScoreColor($originalScore['terms_match_percent']) }};">Terms {{ $originalScore['terms_match_percent'] }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                        @if($showDualScore && $originalScore && $latestCounterScore)
                                        @php $lcColorLandlord = $getScoreColor($latestCounterScore['overall_percent']); @endphp
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColorLandlord }};">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                    <span class="badge" style="background: {{ $lcColorLandlord }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $latestCounterScore['overall_percent'] }}%</span>
                                                </div>
                                                <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                    <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['services_match_percent']) }};">Services {{ $latestCounterScore['services_match_percent'] }}%</div>
                                                    <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['terms_match_percent']) }};">Terms {{ $latestCounterScore['terms_match_percent'] }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="small" style="color: #6c757d; font-style: italic; font-size: 0.76rem;">
                                        <i class="fa-solid fa-circle-info me-1"></i>Added services or terms do not increase either score.
                                    </div>
                                </div>
                                @endif
                                @endif
                                {{-- End 3-branch card body --}}

                            </div>
                            </div>
                        </div>


                    @endforeach


                </div>
            </div>
        </div>
</div>
<button class="btn w-100 mt-0">
    <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
</button>
<div class="p-4 card">
    <p class="text-600">Share this link via</p>
    <div class="qr-code" style="width: 100%; height:200px;">
        {{ qr_code(route('landlord.agent.auction.view', @$auction->id), 200) }}
    </div>
    <div class="card-social">
        <ul class="icons">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-facebook-f"></i>
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-twitter"></i>
            </a>
            <a href="">
                <i class="fa-brands fa-instagram"></i>
            </a>
            <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-pinterest"></i>
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-linkedin"></i>
            </a>
        </ul>
        <p class="small opacity-8">Or copy link</p>
        <div class="field">
            <i class="fa-solid fa-link"></i>
            <input type="text" readonly="" id="copylink"
                value="{{ route('landlord.agent.auction.view', $auction->id) }}">
            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
        </div>
    </div>
</div>
</div>
</div>
</div>
<hr>
@endsection

{{-- 🧠 Timer Script --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js"
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>

@if ($expiration && !$isExpired && !$isSold)
<script>
    $(document).ready(function() {
        const exp = $('.time').data('expiration');
        const expTime = new Date(exp).getTime();
        const now = new Date().getTime();
        const diffSec = Math.floor((expTime - now) / 1000);
        if (diffSec <= 0) return;

        const durations = Math.floor(diffSec / 86400) + "d" +
            Math.floor((diffSec % 86400) / 3600) + "h" +
            Math.floor((diffSec % 3600) / 60) + "m" +
            (diffSec % 60) + "s";

        $('.timer-d').timer({
            countdown: true,
            duration: durations,
            format: '%d',
            callback: onTimerEnd
        });
        $('.timer-h').timer({
            countdown: true,
            duration: durations,
            format: '%h',
            callback: onTimerEnd
        });
        $('.timer-m').timer({
            countdown: true,
            duration: durations,
            format: '%m',
            callback: onTimerEnd
        });
        $('.timer-s').timer({
            countdown: true,
            duration: durations,
            format: '%s',
            callback: onTimerEnd
        });

        function onTimerEnd() {
            $('.timer-d, .timer-h, .timer-m, .timer-s').timer('remove');
            $('.time').html("<span class='status-pill status-ended w-100 d-flex justify-content-center'>Bidding Ended</span>");
            $('.bid-btn').fadeOut(300, function() {
                $(this).after(
                    "<div class='text-center mt-2 mb-0'><span class='status-pill status-ended w-100 d-flex justify-content-center'>Bidding Ended</span></div>"
                );
            });

            // Refresh page to update bid statuses
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    });
</script>
@endif
<script>
document.querySelectorAll('.bid-accordion-header').forEach(function(header) {
    header.addEventListener('click', function() {
        var targetId = this.getAttribute('data-target');
        var target = document.getElementById(targetId);
        var chevron = this.querySelector('.bid-chevron');
        if (!target) return;
        if (target.style.display === 'none' || target.style.display === '') {
            target.style.display = 'block';
            if (chevron) chevron.style.transform = 'rotate(-180deg)';
            this.setAttribute('aria-expanded', 'true');
        } else {
            target.style.display = 'none';
            if (chevron) chevron.style.transform = 'rotate(0deg)';
            this.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>
@endpush
