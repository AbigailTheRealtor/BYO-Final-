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

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };
@endphp

@push('styles')
<!-- //Listing Description css  -->
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
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

    /* Service category title styling */
    .service-category-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
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

    /* Counter (blue) - always solid blue background, same size as Reject */
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

    /* Left column content - vertically centered with symmetrical padding */
    .leftCol .card.description .card-body {
        padding-top: 1.75rem;
        padding-bottom: 1.75rem;
    }

    .leftCol .card.description {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
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

    /* Fix white space below bid cards - ensure collapse content uses natural height */
    .card.higestBider .accordion-item > .card.mb-3 {
        margin-bottom: 0.75rem;
    }
    .card.higestBider .accordion-item > .card.mb-3 > .collapse {
        height: auto;
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
    <!-- Gallery Start Here  -->
    <div class="container listingDescription">
        <div class="row">
            <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
                <div class="card description">
                    <div class="card-header section-header">
                        <h4 class="section-title">Listing Details:</h4>
                    </div>
                    <div class="card-body">
                        <div class="row" style="flex-wrap: wrap;">
                            @if (@$auction->get->listing_title != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">Listing Title
                                    <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->working_with_agent != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">Current Representation Status with Broker:
                                    <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                                </div>
                            @endif


                            @if (@$auction->get->desired_agent_hire_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">Desired Agent Hire Date:
                                    <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date)) }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->listing_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">Listing Date:
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
                        <div class="card-header section-header">
                            <h4 class="section-title">Property Details:</h4>
                        </div>

                        <div class="row" style="flex-wrap: wrap;">

                            @php
                                $citiesData = @$auction->get->cities;
                                $citiesArray = [];
                                if ($citiesData) {
                                    if (is_string($citiesData)) {
                                        $citiesArray = json_decode($citiesData, true) ?? [];
                                    } elseif (is_array($citiesData)) {
                                        $citiesArray = $citiesData;
                                    }
                                }
                                $citiesArray = array_filter($citiesArray);

                                $countiesData = @$auction->get->counties;
                                $countiesArray = [];
                                if ($countiesData) {
                                    if (is_string($countiesData)) {
                                        $countiesArray = json_decode($countiesData, true) ?? [];
                                    } elseif (is_array($countiesData)) {
                                        $countiesArray = $countiesData;
                                    }
                                }
                                $countiesArray = array_filter($countiesArray);

                                $stateVal = @$auction->get->state ?: @$auction->get->property_state;
                                $zipVal = @$auction->get->zip_code ?: @$auction->get->property_zip;
                                $propertyCityVal = @$auction->get->property_city;
                                $propertyCountyVal = @$auction->get->property_county;

                                $stripState = function($str) {
                                    return trim(preg_replace('/,\s*[A-Z]{2}$/', '', $str));
                                };
                            @endphp

                            @if (!empty($citiesArray))
                                <div class="col-md-12 col-12 pt-2 fw-bold">City:
                                    <span class="removeBold">
                                        {{ implode('; ', array_map($stripState, $citiesArray)) }}
                                    </span>
                                </div>
                            @elseif (!empty($propertyCityVal))
                                <div class="col-md-12 col-12 pt-2 fw-bold">City:
                                    <span class="removeBold">{{ $stripState($propertyCityVal) }}</span>
                                </div>
                            @endif

                            @if (!empty($countiesArray))
                                <div class="col-md-12 col-12 pt-2 fw-bold">County:
                                    <span class="removeBold">
                                        {{ implode('; ', array_map($stripState, $countiesArray)) }}
                                    </span>
                                </div>
                            @elseif (!empty($propertyCountyVal))
                                <div class="col-md-12 col-12 pt-2 fw-bold">County:
                                    <span class="removeBold">{{ $stripState($propertyCountyVal) }}</span>
                                </div>
                            @endif

                            @if (!empty($stateVal))
                                <div class="col-md-12 col-12 pt-2 fw-bold">State:
                                    <span class="removeBold">{{ $stateVal }}</span>
                                </div>
                            @endif

                            @if (!empty($zipVal))
                                <div class="col-md-12 col-12 pt-2 fw-bold">ZIP Code:
                                    <span class="removeBold">{{ $zipVal }}</span>
                                </div>
                            @endif
                            @php
                                $propType = @$auction->get->property_type ?? '';
                            @endphp
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Property Type:<span class="removeBold"> {{ \App\Helpers\ListingDisplayHelper::normalizePropertyType($propType) }}</span>
                            </div>
                            @php
                                $propertyStyleItems = \App\Helpers\ListingDisplayHelper::normalizeList(
                                    @$auction->get->property_items,
                                    @$auction->get->other_property_style
                                );
                            @endphp
                            @if (!empty($propertyStyleItems))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Property Style:
                                    <span class="removeBold">{{ implode(', ', $propertyStyleItems) }}</span>
                                </div>
                            @endif

                            @php
                                $businessTypeValue = @$auction->get->business_type_selected ?: @$auction->get->business_type;
                                $otherBusinessType = @$auction->get->other_business_type;
                            @endphp
                            @if (!empty($businessTypeValue))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Business Type:
                                    @if ($businessTypeValue != 'Other')
                                        <span class="removeBold badge bg-secondary">{{ $businessTypeValue }}</span>
                                    @elseif (!empty($otherBusinessType))
                                        <span class="removeBold badge bg-secondary">{{ $otherBusinessType }}</span>
                                    @endif
                                </div>
                            @endif

                            @php
                                $condRawBuyer = @$auction->get->condition_prop_buyer;
                                if (is_string($condRawBuyer)) {
                                    $condRawBuyerDecoded = json_decode(str_replace('&quot;', '"', $condRawBuyer), true);
                                    if (empty($condRawBuyerDecoded) || (is_array($condRawBuyerDecoded) && count($condRawBuyerDecoded) === 0)) {
                                        $condRawBuyer = null;
                                    }
                                }
                                $condRaw = !empty($condRawBuyer) ? $condRawBuyer : (@$auction->get->condition_prop ?? null);
                                $condOther = @$auction->get->other_property_condition;
                                $conditionItems = [];
                                if (!empty($condRaw)) {
                                    $decoded = is_string($condRaw) ? json_decode(str_replace('"', '"', $condRaw), true) : (array) $condRaw;
                                    if (is_array($decoded)) {
                                        foreach ($decoded as $v) {
                                            $v = is_string($v) ? trim(str_replace('"', '', $v)) : $v;
                                            if ($v !== '' && $v !== null) {
                                                if (strtolower($v) === 'other' && !empty($condOther)) {
                                                    $conditionItems[] = trim($condOther);
                                                } else {
                                                    $conditionItems[] = $v;
                                                }
                                            }
                                        }
                                    } else {
                                        $v = trim(str_replace('"', '', $condRaw));
                                        if ($v !== '') {
                                            $conditionItems[] = $v;
                                        }
                                    }
                                }
                            @endphp
                            @if (!empty($conditionItems) && $propType !== 'Vacant Land')
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Property Condition:
                                <span class="removeBold">{{ implode(', ', $conditionItems) }}</span>
                            </div>
                            @endif

                            @if (in_array($propType, ['Residential']))
                            @if (@$auction->get->bedrooms != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Bedrooms:
                                    <span class="removeBold">
                                        @if (@$auction->get->bedrooms != 'Other')
                                            {{ $auction->get->bedrooms }}
                                        @elseif(@$auction->get->bedrooms == 'Other')
                                            {{ @$auction->get->other_bedrooms }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Commercial', 'Business']))
                            @if (@$auction->get->bathrooms != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Bathrooms:
                                    <span class="removeBold">
                                        @if (@$auction->get->bathrooms != 'Other')
                                            {{ $auction->get->bathrooms }}
                                        @elseif(@$auction->get->bathrooms == 'Other')
                                            {{ @$auction->get->other_bathrooms }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                            @endif

                            @if ($propType === 'Residential')
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Heated Sqft:
                                        <span class="removeBold">
                                            @php
                                                $sqftVal = str_replace(',', '', @$auction->get->minimum_heated_square);
                                                echo is_numeric($sqftVal) ? number_format((float)$sqftVal, 0) : @$auction->get->minimum_heated_square;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                                @php $totalSqFt = @$auction->get->total_square_feet; @endphp
                                @if (!empty($totalSqFt) && $totalSqFt != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total Sqft:
                                        <span class="removeBold">
                                            @php
                                                $totalSqFtClean = str_replace(',', '', $totalSqFt);
                                                echo is_numeric($totalSqFtClean) ? number_format((float)$totalSqFtClean, 0) : $totalSqFt;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                                @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != '' && @$auction->get->sqft_heated_source != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Sqft Heated Source:
                                        <span class="removeBold">{{ @$auction->get->sqft_heated_source }}</span>
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business']))
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Heated Sqft:
                                        <span class="removeBold">
                                            @php
                                                $sqftVal = str_replace(',', '', @$auction->get->minimum_heated_square);
                                                echo is_numeric($sqftVal) ? number_format((float)$sqftVal, 0) : @$auction->get->minimum_heated_square;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                                @php $totalSqFtCom = @$auction->get->total_square_feet; @endphp
                                @if (!empty($totalSqFtCom) && $totalSqFtCom != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total Sqft:
                                        <span class="removeBold">
                                            @php
                                                $totalSqFtComClean = str_replace(',', '', $totalSqFtCom);
                                                echo is_numeric($totalSqFtComClean) ? number_format((float)$totalSqFtComClean, 0) : $totalSqFtCom;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                                @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != '' && @$auction->get->sqft_heated_source != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        SqFt Heated Source:
                                        <span class="removeBold">{{ @$auction->get->sqft_heated_source }}</span>
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential']))
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->carportOptions))
                                    @php
                                        $carportVal = @$auction->get->carportOptions;
                                        $carportSpaces = @$auction->get->custom_carport;
                                        if ($carportVal === 'Yes' && \App\Helpers\ListingDisplayHelper::hasValue($carportSpaces)) {
                                            $carportDisplay = 'Yes (' . $carportSpaces . ' Spaces)';
                                        } else {
                                            $carportDisplay = $carportVal;
                                        }
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Carport:
                                        <span class="removeBold">{{ $carportDisplay }}</span>
                                    </div>
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->garageOptions))
                                    @php
                                        $garageVal = @$auction->get->garageOptions;
                                        $garageSpaces = @$auction->get->custom_garage;
                                        if (in_array($garageVal, ['Yes', 'Optional']) && \App\Helpers\ListingDisplayHelper::hasValue($garageSpaces)) {
                                            $garageDisplay = $garageVal . ' (' . $garageSpaces . ' Spaces)';
                                        } else {
                                            $garageDisplay = $garageVal;
                                        }
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Garage:
                                        <span class="removeBold">{{ $garageDisplay }}</span>
                                    </div>
                                @endif
                            @endif

                            @if ($propType === 'Income')
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Heated SqFt:
                                        <span class="removeBold">
                                            @php
                                                $sqftVal = str_replace(',', '', @$auction->get->minimum_heated_square);
                                                echo is_numeric($sqftVal) ? number_format((float)$sqftVal, 0) : @$auction->get->minimum_heated_square;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                                @php $totalSqFtInc = @$auction->get->total_square_feet; @endphp
                                @if (!empty($totalSqFtInc) && $totalSqFtInc != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total Sqft:
                                        <span class="removeBold">
                                            @php
                                                $totalSqFtIncClean = str_replace(',', '', $totalSqFtInc);
                                                echo is_numeric($totalSqFtIncClean) ? number_format((float)$totalSqFtIncClean, 0) : $totalSqFtInc;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                                @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != '' && @$auction->get->sqft_heated_source != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        SqFt Heated Source:
                                        <span class="removeBold">{{ @$auction->get->sqft_heated_source }}</span>
                                    </div>
                                @endif
                            @endif

                            @if (@$auction->get->total_acreage != null && @$auction->get->total_acreage != '' && @$auction->get->total_acreage != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Total Acreage:
                                    <span class="removeBold">{{ @$auction->get->total_acreage }}</span>
                                </div>
                            @endif

                            @php
                                $appRaw = @$auction->get->appliances;
                                $appOther = @$auction->get->other_appliances;
                                $applianceItems = [];
                                if (!empty($appRaw)) {
                                    $decoded = is_string($appRaw) ? json_decode(str_replace('"', '"', $appRaw), true) : (array) $appRaw;
                                    if (is_array($decoded)) {
                                        foreach ($decoded as $v) {
                                            $v = is_string($v) ? trim(str_replace('"', '', $v)) : $v;
                                            if ($v !== '' && $v !== null) {
                                                if (strtolower($v) === 'other' && !empty($appOther)) {
                                                    $applianceItems[] = trim($appOther);
                                                } else {
                                                    $applianceItems[] = $v;
                                                }
                                            }
                                        }
                                    } else {
                                        $v = trim(str_replace('"', '', $appRaw));
                                        if ($v !== '') {
                                            $applianceItems[] = $v;
                                        }
                                    }
                                }
                            @endphp
                            @if (!empty($applianceItems) && in_array($propType, ['Residential', 'Income', 'Commercial', 'Business']))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Appliances Included:
                                    @if (count($applianceItems) === 1)
                                        <span class="removeBold">{{ $applianceItems[0] }}</span>
                                    @else
                                        @foreach ($applianceItems as $appItem)
                                            <span class="removeBold badge bg-secondary">{{ $appItem }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif

                            @if ($propType === 'Income' && @$auction->get->pool_needed !== null && @$auction->get->pool_needed !== '' && @$auction->get->pool_needed !== 'null')
                                @include('hire_seller_agent.partials.pool-display', ['auction' => $auction])
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @if (@$auction->get->minimum_net_leasable_square != null && @$auction->get->minimum_net_leasable_square != 'null' && @$auction->get->minimum_net_leasable_square != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Net Leasable Square Footage:
                                        <span class="removeBold">
                                            @php
                                                $netSqftVal = str_replace(',', '', @$auction->get->minimum_net_leasable_square);
                                                echo is_numeric($netSqftVal) ? number_format((float)$netSqftVal, 0) : @$auction->get->minimum_net_leasable_square;
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business']))
                                @php
                                    $parkingItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->garage_parking_spaces_option, @$auction->get->other_parking_space_wrapper);
                                @endphp
                                @if (!empty($parkingItems))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Garage/Parking Features:
                                        @foreach ($parkingItems as $feature)
                                            <span class="removeBold badge bg-secondary">{{ $feature }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential']))
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

                            @if ($propType === 'Residential' && @$auction->get->pool_needed !== null && @$auction->get->pool_needed !== '' && @$auction->get->pool_needed !== 'null')
                                @include('hire_seller_agent.partials.pool-display', ['auction' => $auction])
                            @endif

                            @php
                                $viewPrefItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->view_preference, @$auction->get->other_preferences);
                            @endphp
                            @if (!empty($viewPrefItems))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    View:
                                    @foreach ($viewPrefItems as $item)
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if (in_array($propType, ['Residential']))
                                @if (@$auction->get->leasing_55_plus != null && @$auction->get->leasing_55_plus != '')
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Age-Restricted Community:
                                    <span class="removeBold">
                                        {{ @$auction->get->leasing_55_plus }}</span>
                                </div>
                                @endif
                            @endif

                            @if (!in_array($propType, ['Vacant Land']))
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
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->pets))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Pets Allowed:
                                    <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets) }}</span>
                                </div>
                                @endif

                                @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->type_of_pets))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Acceptable Pet Types:
                                        <span class="removeBold">{{ @$auction->get->type_of_pets }}</span>
                                    </div>
                                    @endif

                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->weight_of_pets))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Maximum Weight Per Pet (lbs):
                                        <span class="removeBold">{{ @$auction->get->weight_of_pets }} lbs</span>
                                    </div>
                                    @endif

                                    @php
                                        $petRestrictVal = @$auction->get->breed_of_pets ?: @$auction->get->breed_restrictions ?: @$auction->get->has_breed_restrictions;
                                    @endphp
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue($petRestrictVal))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Pet Restrictions:
                                        <span class="removeBold">{{ $petRestrictVal }}</span>
                                    </div>
                                    @endif
                                @endif
                            @endif


                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @php
                                    $rawAssetsView = @$auction->get->assets;
                                    if (empty($rawAssetsView) || $rawAssetsView === '[]' || $rawAssetsView === 'null') {
                                        $rawAssetsView = @$auction->get->business_assets;
                                    }
                                    $assetItems = \App\Helpers\ListingDisplayHelper::normalizeList($rawAssetsView, @$auction->get->assets_other);
                                @endphp
                                @if (!empty($assetItems))
                                </div>
                                <hr>
                                <div class="card-header section-header">
                                    <h4 class="section-title">Business/Property Assets</h4>
                                </div>
                                <div class="row" style="flex-wrap: wrap;">
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Included Property or Business Assets:
                                        @foreach ($assetItems as $asset)
                                            <span class="removeBold badge bg-secondary">{{ $asset }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                            @if ($propType === 'Business')
                                @php
                                    $realEstatePurchase = @$auction->get->real_estate_purchase;
                                @endphp
                                @if (!empty($realEstatePurchase) && $realEstatePurchase != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Business & Real Estate Purchase Requirements:
                                        <span class="removeBold">{{ $realEstatePurchase }}</span>
                                    </div>
                                @endif
                            @endif


                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @php
                                    $hasNOI = \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->minimum_annual_net_income);
                                    $hasCapRate = \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->minimum_cap_rate);
                                @endphp
                                @if ($hasNOI || $hasCapRate)
                                </div>
                                <hr>
                                <div class="card-header section-header">
                                    <h4 class="section-title">Income & Investment Metrics</h4>
                                </div>
                                <div class="row" style="flex-wrap: wrap;">
                                    @if ($hasNOI)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Annual Net Income:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::fmtMoney(@$auction->get->minimum_annual_net_income) }}</span>
                                    </div>
                                    @endif

                                    @if ($hasCapRate)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Cap Rate:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::fmtPercent(@$auction->get->minimum_cap_rate) }}</span>
                                    </div>
                                    @endif
                                @endif
                            @endif

                            @if ($propType === 'Income')
                                @php
                                    $unitNumber = @$auction->get->unit_number;
                                @endphp
                                @if (!empty($unitNumber) && $unitNumber != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total Number of Units:
                                        <span class="removeBold">{{ $unitNumber }}</span>
                                    </div>
                                @endif

                                @php
                                    $unitBuildings = @$auction->get->unit_buildings;
                                @endphp
                                @if (!empty($unitBuildings) && $unitBuildings != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total Number of Buildings:
                                        <span class="removeBold">{{ $unitBuildings }}</span>
                                    </div>
                                @endif

                                @php
                                    $unitConfigs = @$auction->get->unit_type_configurations;
                                    $unitConfigList = [];
                                    if ($unitConfigs) {
                                        $unitConfigList = is_string($unitConfigs) ? (json_decode($unitConfigs, true) ?? []) : (array)$unitConfigs;
                                    }
                                    $unitConfigList = array_values(array_filter($unitConfigList ?? [], function($unit) {
                                        return !empty($unit['unit_type']) || !empty($unit['beds_unit']) || !empty($unit['baths_unit'])
                                            || !empty($unit['number_of_units']) || !empty($unit['expected_rent']) || !empty($unit['unit_type_description']);
                                    }));
                                @endphp
                                @if (!empty($unitConfigList))
                                    <div class="col-12 pt-3">
                                        <h5 class="fw-bold">Unit Type Configuration</h5>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Unit Type</th>
                                                        <th>Beds</th>
                                                        <th>Baths</th>
                                                        <th>Garage</th>
                                                        <th>Carport</th>
                                                        <th>Other Spaces</th>
                                                        <th># Units</th>
                                                        <th># Occupied</th>
                                                        <th>Expected Rent</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($unitConfigList as $unit)
                                                        <tr>
                                                            <td>{{ @$unit['unit_type'] ?? '' }}</td>
                                                            <td>{{ @$unit['beds_unit'] ?? '' }}</td>
                                                            <td>{{ @$unit['baths_unit'] ?? '' }}</td>
                                                            <td>{{ @$unit['garage_spaces'] ?? '' }}</td>
                                                            <td>{{ @$unit['carport_spaces'] ?? '' }}</td>
                                                            <td>{{ @$unit['other_spaces'] ?? '' }}</td>
                                                            <td>{{ @$unit['number_of_units'] ?? '' }}</td>
                                                            <td>{{ @$unit['number_occupied'] ?? '' }}</td>
                                                            <td>
                                                                @if (!empty($unit['expected_rent']))
                                                                    ${{ number_format((float) str_replace(',', '', $unit['expected_rent']), 2) }}
                                                                @endif
                                                            </td>
                                                            <td>{{ @$unit['unit_type_description'] ?? '' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @endif

                        </div>
                        <hr>
                        <div class="card-header section-header">
                            <h4 class="section-title">Sale Terms</h4>
                        </div>

                        @php
                            $spRaw = @$auction->get->sale_provision;
                            $spOther = @$auction->get->sale_provision_other;
                            $saleProvisionItems = [];
                            if (!empty($spRaw)) {
                                $decoded = is_string($spRaw) ? json_decode(str_replace('"', '"', $spRaw), true) : (array) $spRaw;
                                if (is_array($decoded)) {
                                    foreach ($decoded as $v) {
                                        $v = is_string($v) ? trim(str_replace('"', '', $v)) : $v;
                                        if ($v !== '' && $v !== null) {
                                            if (strtolower($v) === 'other' && !empty($spOther)) {
                                                $saleProvisionItems[] = trim($spOther);
                                            } else {
                                                $saleProvisionItems[] = $v;
                                            }
                                        }
                                    }
                                } else {
                                    $v = trim(str_replace('"', '', $spRaw));
                                    if ($v !== '') {
                                        $saleProvisionItems[] = $v;
                                    }
                                }
                            }
                        @endphp
                        @if (!empty($saleProvisionItems))
                            <div class="col-md-12 col-12 pt-2 fw-bold">Special Sale Provision:
                                @if (count($saleProvisionItems) === 1)
                                    <span class="removeBold">{{ $saleProvisionItems[0] }}</span>
                                @else
                                    @foreach ($saleProvisionItems as $spItem)
                                        <span class="removeBold badge bg-secondary">{{ $spItem }}</span>
                                    @endforeach
                                @endif
                            </div>
                        @endif

                        @if (@$auction->get->sale_provision_assignment != null && @$auction->get->sale_provision_assignment != '')
                            <div class="col-md-12 col-12 pt-2 removeBold">
                                <span class="fw-bold">Assignment Contract:</span>
                                {{ @$auction->get->sale_provision_assignment }}
                            </div>
                            @if (strtolower(@$auction->get->sale_provision_assignment) === 'yes')
                                @if (@$auction->get->buyer_sell_contract != null && @$auction->get->buyer_sell_contract != '')
                                <div class="col-md-12 col-12 pt-2 removeBold">
                                    <span class="fw-bold">Seller Under Contract for Assignment:</span>
                                    {{ @$auction->get->buyer_sell_contract }}
                                </div>
                                @endif
                                @if (@$auction->get->assignment_fee_amount != null && @$auction->get->assignment_fee_amount != '')
                                <div class="col-md-12 col-12 pt-2 removeBold">
                                    <span class="fw-bold">Assignment Contract Fee to Broker:</span>
                                    @php
                                        $assignFeeType = @$auction->get->assignment_fee_type ?? '$';
                                        $assignFeeAmt = @$auction->get->assignment_fee_amount;
                                    @endphp
                                    @if ($assignFeeType === '%' || $assignFeeType === 'percent')
                                        {{ $assignFeeAmt }}%
                                    @else
                                        {{ $fmtMoney($assignFeeAmt) }}
                                    @endif
                                </div>
                                @endif
                            @endif
                        @endif

                        @if (@$auction->get->target_closing_date != null)
                            <div class="col-md-12 col-12 pt-2 removeBold">
                                <span class="fw-bold">Target Closing Date:</span>
                                {{ @$auction->get->target_closing_date }}
                            </div>
                        @endif
                        @if (@$auction->get->occupant_status != null)
                            <div class="col-md-12 col-12 pt-2 removeBold">
                                <span class="fw-bold">Occupant Type:</span>
                                {{ @$auction->get->occupant_status }}
                            </div>
                        @endif
                        @if (@$auction->get->occupant_tenant != '' && @$auction->get->occupant_tenant != 'null')
                            <div class="col-md-12 col-12 pt-2 removeBold">
                                <span class="fw-bold">Occupied Until:</span>
                                @php
                                    $occupiedDate = \Carbon\Carbon::parse($auction->get->occupant_tenant);
                                    echo $occupiedDate->format('F j, Y');
                                @endphp
                            </div>
                        @endif
                        @if (@$auction->get->maximum_budget != null)
                            <div class="col-md-12 col-12 pt-2 removeBold">
                                <span class="fw-bold">Desired Sale Price:</span>
                                @php
                                    $salePriceRaw = str_replace(',', '', @$auction->get->maximum_budget);
                                    echo is_numeric($salePriceRaw) ? '$' . number_format((float)$salePriceRaw, 0) : '$' . @$auction->get->maximum_budget;
                                @endphp
                            </div>
                        @endif

                        @php
                            $financingData = @$auction->get->offered_financing;
                            $financingArray = [];
                            if ($financingData) {
                                $financingArray = is_string($financingData) ? (json_decode($financingData, true) ?? []) : (is_array($financingData) ? $financingData : []);
                            }
                            $financingArray = array_filter($financingArray);
                            $displayOtherFinancing = str_replace('"', '', @$auction->get->other_financing ?? '');
                        @endphp

                        @php
                            $financingPills = \App\Helpers\ListingDisplayHelper::normalizeList($financingArray, $displayOtherFinancing);
                            $financingDisplayOrder = ['Assumable','Cash','Conventional','Cryptocurrency','Exchange/Trade','FHA','Jumbo','Lease Option','Lease Purchase','No-Doc','Non-QM','NFT','Non-Fungible Token (NFT)','Seller Financing','USDA','VA'];
                            usort($financingPills, function($a, $b) use ($financingDisplayOrder) {
                                $aIdx = array_search($a, $financingDisplayOrder);
                                $bIdx = array_search($b, $financingDisplayOrder);
                                if ($aIdx === false && strtolower($a) === 'other') return 1;
                                if ($bIdx === false && strtolower($b) === 'other') return -1;
                                if ($aIdx === false) $aIdx = 999;
                                if ($bIdx === false) $bIdx = 999;
                                return $aIdx - $bIdx;
                            });
                        @endphp
                        @if (!empty($financingPills))
                            <hr>
                            <div class="card-header section-header">
                                <h4 class="section-title">Financing Details</h4>
                            </div>
                            <div class="row">

                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Offered Financing/Currency:
                                @if (count($financingPills) === 1)
                                    <span class="removeBold">{{ $financingPills[0] }}</span>
                                @else
                                    @foreach ($financingPills as $fp)
                                        <span class="removeBold badge bg-secondary">{{ $fp }}</span>
                                    @endforeach
                                @endif
                            </div>

                            @php
                                $financingConfig = config('seller-financing-config.sections');
                                $termOrder = config('seller-financing-config.term_order');
                                $fmtMoneyVal = function($val) {
                                    return '$' . number_format((float)str_replace(',', '', $val));
                                };
                                $getVal = function($key) use ($auction) {
                                    return @$auction->get->$key;
                                };
                            @endphp

                            @foreach ($termOrder as $termName)
                                @if (in_array($termName, $financingArray) && isset($financingConfig[$termName]))
                                    @php
                                        $section = $financingConfig[$termName];
                                        $fields = $section['fields'];
                                        $hasAnyData = false;
                                        foreach ($fields as $field) {
                                            $val = $getVal($field['key']);
                                            if (\App\Helpers\ListingDisplayHelper::hasValue($val)) { $hasAnyData = true; break; }
                                            if (isset($field['alt_keys'])) {
                                                foreach ($field['alt_keys'] as $altKey) {
                                                    if (\App\Helpers\ListingDisplayHelper::hasValue($getVal($altKey))) { $hasAnyData = true; break 2; }
                                                }
                                            }
                                        }
                                    @endphp
                                    @if ($hasAnyData)
                                        <div class="col-12 mt-3 mb-1">
                                            <h6 class="financing-subsection-header">{{ $section['header'] }}</h6>
                                        </div>
                                        @foreach ($fields as $field)
                                            @php
                                                $fieldVal = $getVal($field['key']);
                                                if (empty($fieldVal) && isset($field['alt_keys'])) {
                                                    foreach ($field['alt_keys'] as $altKey) {
                                                        $altVal = $getVal($altKey);
                                                        if (!empty($altVal)) { $fieldVal = $altVal; break; }
                                                    }
                                                }
                                                $showField = \App\Helpers\ListingDisplayHelper::hasValue($fieldVal);
                                                if ($showField && isset($field['show_when'])) {
                                                    $condKey = $field['show_when']['key'];
                                                    $condVal = $field['show_when']['value'];
                                                    $condActual = $getVal($condKey);
                                                    if ($condVal === null) {
                                                        $showField = empty($condActual);
                                                    } elseif (is_array($condVal)) {
                                                        $showField = in_array($condActual, $condVal);
                                                    } else {
                                                        $showField = ($condActual === $condVal);
                                                    }
                                                }
                                            @endphp
                                            @if ($showField)
                                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                                    {{ $field['label'] }}:
                                                    @switch($field['format'])
                                                        @case('text')
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @break
                                                        @case('text_with_suffix')
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}{{ $field['suffix'] ?? '' }}</span>
                                                            @break
                                                        @case('money')
                                                            <span class="removeBold">{!! $fmtMoneyVal($fieldVal) !!}</span>
                                                            @break
                                                        @case('percent')
                                                            <span class="removeBold">{{ $fieldVal }}%</span>
                                                            @break
                                                        @case('badge')
                                                            <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @break
                                                        @case('money_or_percent')
                                                            @php
                                                                $typeKey = $field['type_key'] ?? '';
                                                                $typeVal = $getVal($typeKey);
                                                                $percentVal = $field['type_percent_value'] ?? '%';
                                                            @endphp
                                                            @if ($typeVal === $percentVal)
                                                                <span class="removeBold">{{ $fieldVal }}%</span>
                                                            @else
                                                                <span class="removeBold">{!! $fmtMoneyVal($fieldVal) !!}</span>
                                                            @endif
                                                            @break
                                                        @case('badge_or_other')
                                                            @php
                                                                $otherVal = $field['other_value'] ?? 'Other';
                                                                $otherKey = $field['other_key'] ?? '';
                                                                $otherText = $getVal($otherKey);
                                                                $isMultiBadge = !empty($field['multi']);
                                                                $badgeItems = [];
                                                                if ($isMultiBadge) {
                                                                    $rawB = $fieldVal;
                                                                    if (is_string($rawB)) {
                                                                        $decodedB = json_decode(str_replace('&quot;', '"', $rawB), true);
                                                                        $badgeItems = is_array($decodedB) ? $decodedB : ($rawB !== '' ? [$rawB] : []);
                                                                    } else {
                                                                        $badgeItems = is_array($rawB) ? $rawB : [];
                                                                    }
                                                                }
                                                            @endphp
                                                            @if ($isMultiBadge)
                                                                @foreach ($badgeItems as $bItem)
                                                                    @php $bItem = trim(str_replace('"', '', (string) $bItem)); @endphp
                                                                    @if ($bItem === $otherVal && !empty($otherText))
                                                                        <span class="removeBold badge bg-secondary">{{ trim($otherText) }}</span>
                                                                    @elseif ($bItem !== '')
                                                                        <span class="removeBold badge bg-secondary">{{ $bItem }}</span>
                                                                    @endif
                                                                @endforeach
                                                            @elseif ($fieldVal === $otherVal && !empty($otherText))
                                                                <span class="removeBold badge bg-secondary">{{ $otherText }}</span>
                                                            @else
                                                                <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @endif
                                                            @break
                                                        @case('text_or_other')
                                                            @php
                                                                $otherVal = $field['other_value'] ?? 'Other';
                                                                $otherKey = $field['other_key'] ?? '';
                                                                $otherText = $getVal($otherKey);
                                                                $isMulti = !empty($field['multi']);
                                                                $multiItems = [];
                                                                if ($isMulti) {
                                                                    $raw = $fieldVal;
                                                                    if (is_string($raw)) {
                                                                        $decoded = json_decode(str_replace('&quot;', '"', $raw), true);
                                                                        $multiItems = is_array($decoded) ? $decoded : ($raw !== '' ? [$raw] : []);
                                                                    } else {
                                                                        $multiItems = is_array($raw) ? $raw : [];
                                                                    }
                                                                    $displayItems = [];
                                                                    foreach ($multiItems as $mi) {
                                                                        $mi = trim(str_replace('"', '', (string) $mi));
                                                                        if ($mi === $otherVal && !empty($otherText)) {
                                                                            $displayItems[] = trim($otherText);
                                                                        } elseif ($mi !== '') {
                                                                            $displayItems[] = $mi;
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            @if ($isMulti)
                                                                <span class="removeBold">{{ implode(', ', $displayItems) }}</span>
                                                            @elseif ($fieldVal === $otherVal && !empty($otherText))
                                                                <span class="removeBold">{{ $otherText }}</span>
                                                            @else
                                                                <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @endif
                                                            @break
                                                        @case('badge_with_details')
                                                            <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @php
                                                                $detailTrigger = $field['detail_trigger'] ?? 'Yes';
                                                                $detailKey = $field['detail_key'] ?? '';
                                                                $detailVal = $getVal($detailKey);
                                                                $triggers = is_array($detailTrigger) ? $detailTrigger : [$detailTrigger];
                                                            @endphp
                                                            @if (in_array($fieldVal, $triggers) && !empty($detailVal))
                                                                @if (isset($field['detail_format']) && $field['detail_format'] === 'money')
                                                                    <span class="removeBold">({!! $fmtMoneyVal($detailVal) !!})</span>
                                                                @else
                                                                    <span class="removeBold">({{ $detailVal }})</span>
                                                                @endif
                                                            @endif
                                                            @break
                                                        @case('text_with_details')
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @php
                                                                $detailTrigger = $field['detail_trigger'] ?? 'Yes';
                                                                $detailKey = $field['detail_key'] ?? '';
                                                                $detailVal = $getVal($detailKey);
                                                                $triggers = is_array($detailTrigger) ? $detailTrigger : [$detailTrigger];
                                                            @endphp
                                                            @if (in_array($fieldVal, $triggers) && !empty($detailVal))
                                                                @if (isset($field['detail_format']) && $field['detail_format'] === 'money')
                                                                    <span class="removeBold">({!! $fmtMoneyVal($detailVal) !!})</span>
                                                                @else
                                                                    <span class="removeBold">({{ $detailVal }})</span>
                                                                @endif
                                                            @endif
                                                            @break
                                                        @case('yes_parenthetical')
                                                            @php
                                                                $amtKey = $field['amount_key'] ?? '';
                                                                $amtVal = $getVal($amtKey);
                                                                $cleanVal = str_replace('"', '', $fieldVal);
                                                            @endphp
                                                            @if (strtolower($cleanVal) === 'yes' && !empty($amtVal))
                                                                <span class="removeBold">Yes ({!! $fmtMoneyVal($amtVal) !!})</span>
                                                            @else
                                                                <span class="removeBold">{{ $cleanVal }}</span>
                                                            @endif
                                                            @break
                                                        @default
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                    @endswitch
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                @endif
                            @endforeach
                            </div>
                        @endif

                        <hr>

                        @php
                        // Check if services exist before showing the section
                        $hasServices = !empty(@$auction->get->services) || !empty(@$auction->get->other_services);
                        @endphp

                        @if ($hasServices)
                        <div class="card-header section-header services-section-header">
                            <h4 class="section-title">Services:</h4>
                        </div>

                        @php
                        // Define seller service categories based on property type
                        $propType = @$auction->get->property_type ?? '';
                        $isResidential = in_array($propType, ['Residential', 'Residential Property']);
                        $isCommercial = in_array($propType, ['Commercial', 'Commercial Property']);
                        $isIncome = in_array($propType, ['Income', 'Income Property']);
                        $isVacantLand = in_array($propType, ['Vacant Land']);
                        $isBusinessOpportunity = in_array($propType, ['Business', 'Business Opportunity']);

                        // Residential seller service categories (exact match with form)
                        $residentialCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "Create a branded flyer featuring the property’s key highlights",
                                "Post the property on Facebook Marketplace",
                                "Post the property on Craigslist under the \"Homes for Sale\" category",
                                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                "Promote the listing on Facebook in Real Estate or Community Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Presentation" => [
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
                                "Ensure proper notice is provided if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer’s Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📑 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate price, terms, and contingencies with the Buyer’s Agent or Buyer",
                                "Manage communications with the Buyer’s Agent or Buyer",
                                "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with inspection-related negotiations and Buyer requests for repairs",
                                "Monitor contract milestones, contingency periods, and financing deadlines",
                                "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "🧾 Closing Coordination & Transaction Management" => [
                                "Coordinate scheduling for inspections, appraisals, and other requested evaluations",
                                "Coordinate with the Buyer’s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions",
                                "Provide general insight on local market trends, seasonal timing, and pricing thresholds",
                                "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                            ],
                        ];

                        // Commercial seller service categories (exact match with form)
                        $commercialCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "List the property on Crexi.com",
                                "List the property on LoopNet.com",
                                "Create a branded flyer summarizing the property’s investment highlights and key selling points",
                                "Post the property on Craigslist under the \"Commercial for Sale\" category",
                                "Promote the listing on Facebook in Commercial or Investor Real Estate Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Asset Presentation" => [
                                "Conduct a property walkthrough and provide recommendations for listing readiness",
                                "Provide a visual consultation on interior layout, cleanliness, and overall presentation",
                                "Provide a curb appeal consultation focused on exterior appearance and first impressions",
                                "Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only — no endorsement or warranty is made)",
                                "Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)",
                                "Organize zoning documentation, surveys, and public record reports (as available)",
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
                                "Respond to Buyer inquiries and screen for general qualifications",
                                "Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings",
                                "Ensure proper notice is provided if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer’s Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Coordinate Letter of Intent (LOI) submissions, counteroffers, and contract revisions",
                                "Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods",
                                "Manage communication with the Buyer’s Agent or Buyer",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with inspection-related negotiations and Buyer requests for repairs or credits",
                                "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "🧾 Closing Coordination & Transaction Management" => [
                                "Coordinate inspections, appraisals, and estoppel certificate delivery with the Buyer’s Agent or Buyer, as applicable",
                                "Provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                "Coordinate with the Buyer’s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity",
                                "Assist in estimating Capitalization Rate (Cap Rate), Price per Square Foot, or Gross Rent Multiplier (GRM) based on listing details and commercial comparables",
                                "Provide general insight on likely Buyer types (e.g., Owner-User, Investor, 1031 Exchange Buyer), common value drivers, and investment strategies",
                                "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                "Provide general guidance on lease structures, expense ratios, and Tenant impacts",
                            ],
                        ];

                        // Income seller service categories
                        $incomeCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "List the property on Crexi.com",
                                "List the property on LoopNet.com",
                                "Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)",
                                "Post the property on Craigslist under the \"Multi-Family for Sale\" category",
                                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                "Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Investment or Real Estate Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Investment Packaging" => [
                                "Conduct a property walkthrough and provide recommendations for listing readiness",
                                "Provide a custom listing preparation checklist",
                                "Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)",
                                "Provide a visual consultation focused on interior layout, cleanliness, and unit presentation",
                                "Provide a curb appeal consultation focused on exterior maintenance and first impressions",
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
                            "🏘️ Showings & Access Coordination" => [
                                "Respond to Buyer inquiries and screen for general qualifications",
                                "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                "Ensure proper notice is provided if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer’s Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies",
                                "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                "Manage communication with the Buyer’s Agent or Buyers",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with inspection-related negotiations and Buyer requests for repairs",
                                "Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports",
                                "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only — no endorsement or warranty is made",
                            ],
                            "🧾 Closing Coordination & Transaction Management" => [
                                "Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                "Coordinate with the Buyer’s Agent, Buyer’s Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity",
                                "Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables ",
                                "Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies",
                                "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                "Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines",
                            ],
                        ];

                        // Business seller service categories
                        $businessCategories = [
                            "📢 Business Marketing & Listing Promotion" => [
                                "List the Business Opportunity on the local Multiple Listing Service (MLS)",
                                "List the Business Opportunity on Crexi.com",
                                "List the Business Opportunity on LoopNet.com",
                                "List the Business Opportunity on BizBuySell.com",
                                "List the Business Opportunity on BizQuest.com",
                                "List the Business Opportunity on BusinessesForSale.com",
                                "Create a branded flyer summarizing the Business’s key features (e.g., industry, cash flow, assets)",
                                "Post the Business Opportunity on Craigslist under the \"Business for Sale\" category",
                                "Promote the listing on Facebook in Business Buyer, Franchise, or Investor Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Business Acquisition, Startup, or Investor Groups",
                                "Upload a TikTok video summarizing the Business Opportunity",
                                "Upload a YouTube video summarizing the Business Opportunity",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Confidential Marketing" => [
                                "Conduct a preliminary Seller consultation to gather details about the Business’s operations, assets, and goals",
                                "Provide a business sale checklist to collect financials, licenses, lease terms, and key operational details",
                                "Assist with preparing a non-confidential teaser or executive summary for marketing purposes",
                                "Organize internal documentation such as profit and loss statements, balance sheets, FF&E summaries, inventory lists, and staffing overviews (as available)",
                                "Refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only — no endorsement or warranty is made)",
                                "Compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries",
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
                                "Respond to Buyer inquiries and screen for general qualifications",
                                "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                "Ensure proper notice is provided if the property or business premises is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer’s Agents",
                                "Coordinate directly with Tenant(s) or business staff to arrange access for showings",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all Letters of Intent (LOIs) or formal offers to the Seller and summarize key deal terms",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate deal terms such as purchase price, deposit structure, contingencies, transition period, and asset allocation",
                                "Coordinate revisions, counteroffers, and ongoing communication with the Buyer or their representatives",
                                "Manage communication with the Buyer’s Broker or Buyer",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Monitor contract contingencies and organize delivery of due diligence materials such as leases, vendor contracts, tax filings, and financial statements",
                                "Refer the Seller to legal counsel for formal contract drafting and execution (referrals only — no legal advice provided)",
                                "Provide referrals to Business Attorneys, Escrow Officers, or Business Transfer Specialists (referrals only — no endorsement or warranty is made)",
                            ],
                            "📃 Closing Coordination & Transaction Management" => [
                                "Coordinate Buyer inspections, management interviews, and site visits as applicable",
                                "Provide a transaction checklist and track key deadlines throughout the escrow period",
                                "Coordinate with the Buyer’s Attorney, Escrow Officer, or designated Closing Facilitator",
                                "Review the Settlement Statement and coordinate corrections with relevant parties",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a business market overview with insights from recent comparable listings",
                                "Identify likely Buyer types (e.g., Owner-Operator, Investor, Franchisee) and discuss common deal structures (e.g., asset sale, stock sale)",
                                "Provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention",
                                "Provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods",
                                "Provide referrals to business valuation, accounting, or legal professionals (referrals only — no endorsement or warranty is made)",
                            ],
                        ];

                        // Vacant Land seller service categories
                        $vacantLandCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property in the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "List the property on LandWatch.com",
                                "List the property on Land.com",
                                "List the property on LandAndFarm.com",
                                "Create a branded flyer highlighting lot features, zoning, and potential use",
                                "Post the listing on Facebook Marketplace",
                                "Post the listing on Craigslist under the \"Land for Sale\" category",
                                "Share the listing on Nextdoor in Neighborhood or Rural Groups",
                                "Promote the listing on Facebook in Land Buyers, Developers, or Homesteader Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Land Acquisition or Investment Groups",
                                "Upload a TikTok video summarizing the land opportunity",
                                "Upload a YouTube video summarizing the land opportunity (e.g., drone tour, narrated overview)",
                                "Launch a mass email campaign promoting the listing Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Research" => [
                                "Provide a checklist to gather parcel data (e.g., APN, lot size, zoning, utilities, and access)",
                                "Assist with collecting public records, flood zone data, and land use information (as available)",
                                "Provide referrals to surveyors, soil testers, or land service professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "📸 Photography, Video & Virtual Media" => [
                                "Provide professional property photography",
                                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                "Provide a video overview or narrated walkthrough",
                                "Provide a 3D virtual tour (if applicable)",
                                "Provide digital enhancements to media assets",
                                "Provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)",
                            ],
                            "🏡 Showings & Access Coordination" => [
                                "Install a real estate sign on the property",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer’s Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate price, due diligence timelines, and closing terms",
                                "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                "Manage communication with the Buyer’s Agent or Buyer",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews",
                                "Provide referrals to Attorneys, Title Companies, Escrow Officers, or Land Use Professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "📃 Closing Coordination & Transaction Management" => [
                                "Coordinate surveys, site visits, or environmental access with the Buyer or Buyer’s Agent, as applicable",
                                "Coordinate with Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on recent land sales, zoning categories, and location-based trends",
                                "Provide general insight on permitted uses, utility access, parcel features, and Buyer demand in the area",
                                "Recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest",
                                "Provide general guidance on Seller obligations, disclosure requirements, and listing preparation",
                            ],
                        ];

                        // Select appropriate categories based on property type
                        if ($isIncome) {
                            $categories = $incomeCategories;
                        } elseif ($isCommercial) {
                            $categories = $commercialCategories;
                        } elseif ($isBusinessOpportunity) {
                            $categories = $businessCategories;
                        } elseif ($isVacantLand) {
                            $categories = $vacantLandCategories;
                        } else {
                            $categories = $residentialCategories;
                        }
                        $allServicesRaw = is_array(@$auction->get->services) ? $auction->get->services : [];
                        $allServices = array_map('trim', $allServicesRaw);
                        $otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];

                        $canon = function($str) {
                            $str = str_replace(["\u{2018}", "\u{2019}", "\u{201A}", "\u{2032}"], "'", $str);
                            $str = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{2033}"], '"', $str);
                            $str = str_replace(["\u{2014}", "\u{2013}"], '-', $str);
                            return trim($str);
                        };

                        $allServicesCanon = array_map($canon, $allServices);

                        // Check if we have any services that match categories
                        $hasMatchedServices = false;
                        foreach ($categories as $categoryServices) {
                            $matched = array_filter($categoryServices, fn($s) => in_array($canon($s), $allServicesCanon));
                            if (!empty($matched)) {
                                $hasMatchedServices = true;
                                break;
                            }
                        }
                        @endphp

                        @php
                            $photoEnhRaw = @$auction->get->photo_enhancements ?? null;
                            $photoEnhancements = [];
                            if ($photoEnhRaw) {
                                $photoEnhancements = is_string($photoEnhRaw) ? (json_decode($photoEnhRaw, true) ?? []) : (array)$photoEnhRaw;
                            }
                            $customEnhancement = @$auction->get->custom_enhancement ?? '';

                            // Filter and reorder enhancements by the valid set for the property type
                            $enhancementValidMap = [
                                'Residential'  => ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement'],
                                'Income'       => ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement'],
                                'Commercial'   => ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement'],
                                'Business'     => ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture)', 'Virtual twilight photography', 'Color correction or sky replacement'],
                                'Vacant Land'  => ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion', 'Object removal (e.g., clutter, signage)', 'Sky replacement or color correction', 'Virtual twilight effect'],
                            ];
                            $validEnhSet = $enhancementValidMap[$propType] ?? null;
                            if ($validEnhSet !== null && !empty($photoEnhancements)) {
                                $filtered = array_values(array_filter($validEnhSet, function($opt) use ($photoEnhancements) {
                                    return in_array($opt, $photoEnhancements);
                                }));
                                if (in_array('Other', $photoEnhancements)) {
                                    $filtered[] = 'Other';
                                }
                                $photoEnhancements = $filtered;
                            }
                        @endphp

                        <div class="col-md-12 col-12 pt-2">
                            @if ($hasMatchedServices)
                                @foreach ($categories as $categoryName => $categoryServices)
                                    @php
                                        $matchedServices = array_values(array_filter($categoryServices, function($service) use ($allServicesCanon, $canon) {
                                            return in_array($canon($service), $allServicesCanon);
                                        }));
                                    @endphp
                                    @if (!empty($matchedServices))
                                    <div class="mt-3">
                                        <strong>{{ $categoryName }}</strong>
                                        <ul class="services">
                                            @foreach ($matchedServices as $service)
                                            <li style="font-size: 16px;">{{ trim($service) }}</li>
                                            @if (in_array(trim($service), ['Provide digital photo enhancements', 'Provide digital enhancements to media assets']) && !empty($photoEnhancements))
                                                <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.25rem; margin-bottom: 0.25rem;">
                                                    @foreach ($photoEnhancements as $enhancement)
                                                        @if ($enhancement !== 'Other')
                                                        <li style="font-size: 15px;" class="removeBold">{{ $enhancement }}</li>
                                                        @endif
                                                    @endforeach
                                                    @if (in_array('Other', $photoEnhancements) && !empty($customEnhancement))
                                                    <li style="font-size: 15px;" class="removeBold">{{ $customEnhancement }}</li>
                                                    @endif
                                                </ul>
                                            @endif
                                            @endforeach
                                        </ul>
                                        @if (str_contains($categoryName, 'Photography, Video & Virtual Media'))
                                            <p style="font-size: 14px; font-style: italic; margin-top: 0.25rem; margin-bottom: 0;">Note: These services may be provided by the Agent or through a third-party vendor.</p>
                                        @endif
                                    </div>
                                    @endif
                                @endforeach
                            @else
                                @if (!empty($allServices))
                                <div class="mt-3">
                                    <strong>📋 Services Requested</strong>
                                    <ul class="services">
                                        @foreach ($allServices as $service)
                                        <li style="font-size: 16px;">{{ trim($service) }}</li>
                                        @if (in_array(trim($service), ['Provide digital photo enhancements', 'Provide digital enhancements to media assets']) && !empty($photoEnhancements))
                                            <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.25rem; margin-bottom: 0.25rem;">
                                                @foreach ($photoEnhancements as $enhancement)
                                                    @if ($enhancement !== 'Other')
                                                    <li style="font-size: 15px;" class="removeBold">{{ $enhancement }}</li>
                                                    @endif
                                                @endforeach
                                                @if (in_array('Other', $photoEnhancements) && !empty($customEnhancement))
                                                <li style="font-size: 15px;" class="removeBold">{{ $customEnhancement }}</li>
                                                @endif
                                            </ul>
                                        @endif
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            @endif

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
                                $ccsRawSeller = @$auction->get->client_custom_services;
                                $clientCustomServicesSeller = is_array($ccsRawSeller)
                                    ? $ccsRawSeller
                                    : (is_string($ccsRawSeller) ? (json_decode($ccsRawSeller, true) ?? []) : []);
                                $clientCustomServicesSeller = array_values(array_filter($clientCustomServicesSeller, fn($s) => is_string($s) && trim($s) !== ''));
                            @endphp
                            @if (!empty($clientCustomServicesSeller))
                            <div class="mt-3">
                                <strong>📋 Client Requested Services</strong>
                                <ul class="services">
                                    @foreach ($clientCustomServicesSeller as $ccs)
                                    <li style="font-size: 16px;">{{ $ccs }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                        @endif

                        <hr>
                        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details))
                            <div class="card-header section-header">
                                <h4 class="section-title">Additional Details:</h4>
                            </div>

                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Additional Details: <span
                                    class="removeBold">{{ $auction->get->additional_details }}</span>
                            </div>
                        @endif

                        @include('partials.listing-photos-tours-documents')

                        {{-- C9: Representation Preferences & Compatibility display (public; parity with tenant hire view). --}}
                        @php
                            $rawCompatView = $auction->info('compatibility_preferences');
                            $compatView    = ($rawCompatView !== null && $rawCompatView !== '')
                                ? (json_decode($rawCompatView, true) ?? [])
                                : [];
                            $ssView = $compatView['seller_specific'] ?? [];

                            $repResolve = function(string $val, string $otherVal): string {
                                return ($val === 'Other' && !empty($otherVal)) ? $otherVal : $val;
                            };
                            $repResolveArr = function(array $vals, string $otherVal): array {
                                return array_values(array_filter(array_map(function($v) use ($otherVal) {
                                    return ($v === 'Other' && !empty($otherVal)) ? $otherVal : $v;
                                }, $vals)));
                            };
                            $repRows = [];
                            $repAdd = function(string $label, $raw, string $otherVal = '') use (&$repRows, $repResolve, $repResolveArr) {
                                if (empty($raw) || $raw === '' || $raw === [] || $raw === '[]') return;
                                $display = is_array($raw) ? implode(', ', $repResolveArr($raw, $otherVal)) : $repResolve((string)$raw, $otherVal);
                                if (!empty($display)) { $repRows[] = ['label' => $label, 'value' => $display]; }
                            };

                            $repAdd('Primary Transaction Goal', $ssView['primary_transaction_goal'] ?? '', $ssView['primary_transaction_goal_other'] ?? '');
                            $repAdd('Target Sale Timeline', $ssView['target_sale_timeline'] ?? '', '');
                            $repAdd('Representation Priorities', $ssView['representation_priorities'] ?? [], '');
                            $repAdd('Preferred Communication Style', $ssView['communication_style'] ?? '', '');
                            $repAdd('Negotiation Style', $ssView['negotiation_style'] ?? '', '');
                            $repAdd('Preferred Agent Working Style', $ssView['preferred_agent_working_style'] ?? '', '');
                            $repAdd('Decision Makers Involved', $ssView['additional_decision_makers'] ?? '', '');
                            $repAdd('What Did Not Work Well with Past Agents', $ssView['what_did_not_work_before'] ?? '', '');
                            $repAdd('Additional Compatibility Notes', $ssView['additional_compatibility_notes'] ?? '', '');
                        @endphp

                        @if (!empty($repRows))
                        <hr />
                        <div class="card-header section-header">
                            <h4 class="section-title">Representation Preferences &amp; Compatibility:</h4>
                        </div>
                        @foreach ($repRows as $repRow)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            {{ $repRow['label'] }}:
                            <span class="removeBold">{{ $repRow['value'] }}</span>
                        </div>
                        @endforeach
                        @endif

                        @if (Auth::check()) {{-- broker compensation: hidden from anonymous visitors --}}
                        @php
                            $hasSellerBrokerCompData = !empty(@$auction->get->purchase_fee_type)
                                || !empty(@$auction->get->commission_structure)
                                || !empty(@$auction->get->broker_fee_timing)
                                || !empty(@$auction->get->protection_period)
                                || !empty(@$auction->get->agency_agreement_timeframe)
                                || !empty(@$auction->get->early_termination_fee_option)
                                || !empty(@$auction->get->interested_purchase_fee_type)
                                || !empty(@$auction->get->brokerage_relationship);
                        @endphp
                        @if ($hasSellerBrokerCompData)
                        <hr />
                        <div class="card-header section-header">
                            <h4 class="section-title">Broker Compensation & Agency Agreement Terms</h4>
                        </div>

                        <div class="broker-compensation-section">

                        @php
                            $hasPurchaseFee = !empty(@$auction->get->purchase_fee_type);
                            $hasLeaseFee = !empty(@$auction->get->lease_fee_type);
                        @endphp
                        <!-- Broker Compensation Sub-section -->
                        @if ($hasPurchaseFee || $hasLeaseFee)
                        <h5 class="mt-3 mb-2"><strong>Broker Compensation:</strong></h5>
                        @endif
                        @if ($hasPurchaseFee)
                        @php
                            $purchaseFeeType = @$auction->get->purchase_fee_type ?? '';
                            $sellerLeaseFeeCombined = '—';

                            if ($purchaseFeeType === 'flat' && @$auction->get->purchase_fee_flat) {
                                $sellerLeaseFeeCombined = $fmtMoney(@$auction->get->purchase_fee_flat);
                            } elseif ($purchaseFeeType === 'percentage' && @$auction->get->purchase_fee_percentage) {
                                $sellerLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_percentage) . ' of Total Purchase Price';
                            } elseif ($purchaseFeeType === 'combo' && (@$auction->get->purchase_fee_percentage_combo || @$auction->get->purchase_fee_flat_combo)) {
                                $parts = [];
                                if (@$auction->get->purchase_fee_percentage_combo) $parts[] = $fmtPercent(@$auction->get->purchase_fee_percentage_combo) . ' of Total Purchase Price';
                                if (@$auction->get->purchase_fee_flat_combo) $parts[] = $fmtMoney(@$auction->get->purchase_fee_flat_combo);
                                $sellerLeaseFeeCombined = implode(' + ', $parts);
                            } elseif ($purchaseFeeType === 'other' && @$auction->get->purchase_fee_other) {
                                $sellerLeaseFeeCombined = @$auction->get->purchase_fee_other;
                            } elseif ($purchaseFeeType) {
                                $sellerLeaseFeeCombined = $purchaseFeeType;
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Seller's Broker Purchase Fee:
                            <span class="removeBold">{{ $sellerLeaseFeeCombined }}</span>
                        </div>
                        @elseif ($hasLeaseFee)
                        @php
                            $sellerLeaseFeeType = @$auction->get->lease_fee_type ?? '';
                            $sellerLeaseFeeCombined = '—';
                            
                            if ($sellerLeaseFeeType === 'Flat Fee' && @$auction->get->lease_fee_flat) {
                                $sellerLeaseFeeCombined = $fmtMoney(@$auction->get->lease_fee_flat);
                            } elseif ($sellerLeaseFeeType === 'Percentage of the Total Purchase Price' && @$auction->get->lease_fee_percentage) {
                                $sellerLeaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage) . ' of Total Purchase Price';
                            } elseif ($sellerLeaseFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->lease_fee_percentage_combo) {
                                $sellerLeaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage_combo) . ' of Gross Lease Value';
                            } elseif ($sellerLeaseFeeType === "Percentage of the Monthly Rent" && @$auction->get->lease_fee_percentage_monthly_rent) {
                                $sellerLeaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage_monthly_rent) . ' of Monthly Rent';
                            } elseif ($sellerLeaseFeeType === 'Flat Fee + Percentage' && (@$auction->get->lease_fee_flat_combo || @$auction->get->lease_fee_percentage_combo)) {
                                $parts = [];
                                if (@$auction->get->lease_fee_percentage_combo) $parts[] = $fmtPercent(@$auction->get->lease_fee_percentage_combo) . ' of Total Purchase Price';
                                if (@$auction->get->lease_fee_flat_combo) $parts[] = $fmtMoney(@$auction->get->lease_fee_flat_combo);
                                $sellerLeaseFeeCombined = implode(' + ', $parts);
                            } elseif ($sellerLeaseFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->lease_fee_percentage_net) {
                                $sellerLeaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage_net) . ' of Net Aggregate Rent';
                            } elseif (strtolower($sellerLeaseFeeType) === 'other' && @$auction->get->lease_fee_other) {
                                $sellerLeaseFeeCombined = @$auction->get->lease_fee_other;
                            } elseif ($sellerLeaseFeeType) {
                                $sellerLeaseFeeCombined = $sellerLeaseFeeType;
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Seller's Broker Purchase Fee:
                            <span class="removeBold">{{ $sellerLeaseFeeCombined }}</span>
                        </div>
                        @endif

                        @php
                            $nominalVal = @$auction->get->nominal;
                        @endphp
                        @if (!empty($nominalVal) && $nominalVal != 'null')
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Nominal Consideration Fee:
                            <span class="removeBold">${{ number_format((float) str_replace(',', '', $nominalVal), 2) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->commission_structure != null)
                        @php
                            $commStructureRaw = str_replace('"', '', @$auction->get->commission_structure);
                            $commStructureMap = [
                                'Out-of-Pocket Payment' => "Seller to Pay Buyer's Broker Separately",
                                'Included in Offer' => "Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission",
                            ];
                            $commStructureDisplay = $commStructureMap[$commStructureRaw] ?? $commStructureRaw;
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Buyer's Broker Commission Structure:
                            <span class="removeBold">{{ $commStructureDisplay }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->commission_structure_type != null && str_replace('"', '', @$auction->get->commission_structure) !== "No Compensation Offered to the Buyer's Broker")
                        @php
                            $buyerBrokerFeeType = @$auction->get->commission_structure_type ?? '';
                            $buyerBrokerFee = '—';
                            
                            if ($buyerBrokerFeeType === 'Flat Fee' && @$auction->get->commission_structure_type_fee_flat) {
                                $buyerBrokerFee = $fmtMoney(@$auction->get->commission_structure_type_fee_flat);
                            } elseif ($buyerBrokerFeeType === 'Percentage of the Total Purchase Price' && @$auction->get->commission_structure_type_fee_percentage) {
                                $buyerBrokerFee = $fmtPercent(@$auction->get->commission_structure_type_fee_percentage) . ' of Total Purchase Price';
                            } elseif ($buyerBrokerFeeType === 'Flat Fee + Percentage' && (@$auction->get->commission_structure_type_fee_flat_combo || @$auction->get->commission_structure_type_fee_percentage_combo)) {
                                $parts = [];
                                if (@$auction->get->commission_structure_type_fee_percentage_combo) $parts[] = $fmtPercent(@$auction->get->commission_structure_type_fee_percentage_combo) . ' of Total Purchase Price';
                                if (@$auction->get->commission_structure_type_fee_flat_combo) $parts[] = $fmtMoney(@$auction->get->commission_structure_type_fee_flat_combo);
                                $buyerBrokerFee = implode(' + ', $parts);
                            } elseif (strtolower($buyerBrokerFeeType) === 'other' && @$auction->get->commission_structure_type_fee_other) {
                                $buyerBrokerFee = @$auction->get->commission_structure_type_fee_other;
                            } elseif ($buyerBrokerFeeType) {
                                $buyerBrokerFee = $buyerBrokerFeeType;
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Buyer's Broker Commission Fee:
                            <span class="removeBold">{{ $buyerBrokerFee }}</span>
                        </div>
                        @endif

                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                        <!-- Lease Terms Sub-section -->
                        @if (@$auction->get->interested_purchase_fee_type != null)
                        <h5 class="mt-3 mb-2"><strong>Lease Terms:</strong></h5>
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Interested in Offering a Lease Agreement:
                            <span class="removeBold">{{ in_array(strtolower(@$auction->get->interested_purchase_fee_type ?? ''), ['yes']) ? 'Yes' : 'No' }}</span>
                        </div>
                        @if (in_array(strtolower(@$auction->get->interested_purchase_fee_type ?? ''), ['yes']) && @$auction->get->seller_leasing_fee_type != null)
                        @php
                            $sellerLeasingFee = '—';
                            $leasingType = @$auction->get->seller_leasing_fee_type;
                            if ($leasingType === 'Flat Fee' && @$auction->get->seller_leasing_gross_purchase_fee_flat_amount) {
                                $sellerLeasingFee = $fmtMoney(@$auction->get->seller_leasing_gross_purchase_fee_flat_amount);
                            } elseif ($leasingType === 'Percentage of the Gross Lease Value' && @$auction->get->seller_leasing_gross) {
                                $sellerLeasingFee = $fmtPercent(@$auction->get->seller_leasing_gross) . ' of the Gross Lease Value';
                            } elseif ($leasingType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->seller_leasing_gross_rental) {
                                $sellerLeasingFee = $fmtPercent(@$auction->get->seller_leasing_gross_rental) . ' of the Rent Due Each Rental Period';
                            } elseif ($leasingType === "Percentage of the First Month's Rent" && @$auction->get->seller_leasing_gross_month_rent) {
                                $sellerLeasingFee = $fmtPercent(@$auction->get->seller_leasing_gross_month_rent) . " of the First Month's Rent";
                            } elseif ($leasingType === "Percentage of Month's Rent" && @$auction->get->seller_leasing_gross_month_rent) {
                                $monthsVal = @$auction->get->seller_leasing_gross_no_of_months;
                                $sellerLeasingFee = $fmtPercent(@$auction->get->seller_leasing_gross_month_rent) . " of Month's Rent";
                                if (!empty($monthsVal) && $monthsVal != 'null') {
                                    $sellerLeasingFee .= ' x ' . intval($monthsVal) . ' Months';
                                }
                            } elseif ($leasingType === 'Percentage of Net Aggregate Rent' && @$auction->get->seller_leasing_gross_other) {
                                $sellerLeasingFee = $fmtPercent(@$auction->get->seller_leasing_gross_other) . ' of Net Aggregate Rent';
                            } elseif ($leasingType === 'Percentage of Gross Rent' && (@$auction->get->seller_leasing_gross_percentage || @$auction->get->seller_leasing_gross_ross_percentage_rent)) {
                                $grossRentVal = @$auction->get->seller_leasing_gross_percentage ?? @$auction->get->seller_leasing_gross_ross_percentage_rent;
                                $sellerLeasingFee = $fmtPercent($grossRentVal) . ' of Gross Rent';
                            } elseif ($leasingType === 'Flat Fee + Percentage of the Gross Lease Value' && (@$auction->get->seller_leasing_gross_flat_combo || @$auction->get->seller_leasing_gross_percentage_combo)) {
                                $parts = [];
                                if (@$auction->get->seller_leasing_gross_flat_combo) $parts[] = $fmtMoney(@$auction->get->seller_leasing_gross_flat_combo);
                                if (@$auction->get->seller_leasing_gross_percentage_combo) $parts[] = $fmtPercent(@$auction->get->seller_leasing_gross_percentage_combo) . ' of Gross Lease Value';
                                $sellerLeasingFee = implode(' + ', $parts);
                            } elseif ($leasingType === 'Flat Fee + Percentage of the Net Aggregate Rent' && (@$auction->get->seller_leasing_gross_flat_net_combo || @$auction->get->seller_leasing_gross_percentage_net_combo)) {
                                $parts = [];
                                if (@$auction->get->seller_leasing_gross_flat_net_combo) $parts[] = $fmtMoney(@$auction->get->seller_leasing_gross_flat_net_combo);
                                if (@$auction->get->seller_leasing_gross_percentage_net_combo) $parts[] = $fmtPercent(@$auction->get->seller_leasing_gross_percentage_net_combo) . ' of Net Aggregate Rent';
                                $sellerLeasingFee = implode(' + ', $parts);
                            } elseif (strtolower($leasingType) === 'other' && @$auction->get->seller_leasing_gross_purchase_fee_other) {
                                $sellerLeasingFee = @$auction->get->seller_leasing_gross_purchase_fee_other;
                            } elseif ($leasingType) {
                                $sellerLeasingFee = $leasingType;
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Seller's Broker Leasing Fee:
                            <span class="removeBold">{{ $sellerLeasingFee }}</span>
                        </div>

                        @if (in_array($propType, ['Commercial', 'Business']))
                            @php
                                $salesTaxVal = null;
                                if (in_array($leasingType, ['Flat Fee'])) {
                                    $salesTaxVal = @$auction->get->seller_leasing_gross_sales_tax_flat_free_gross;
                                } elseif (in_array($leasingType, ["Percentage of Month's Rent", "Percentage of the First Month's Rent"])) {
                                    $salesTaxVal = @$auction->get->seller_leasing_gross_sales_tax_first_month;
                                } elseif ($leasingType === 'Percentage of Gross Rent') {
                                    $salesTaxVal = @$auction->get->seller_leasing_gross_sales_tax_option_gross;
                                }
                            @endphp
                            @if (!empty($salesTaxVal) && $salesTaxVal != 'null')
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Sales Tax:
                                <span class="removeBold">{{ ucfirst($salesTaxVal) }} Sales Tax</span>
                            </div>
                            @endif

                            @if ($leasingType === "Percentage of the First Month's Rent")
                                @php $numMonths = @$auction->get->seller_leasing_gross_no_of_months; @endphp
                                @if (!empty($numMonths) && $numMonths != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Number of Months:
                                    <span class="removeBold">{{ $numMonths }}</span>
                                </div>
                                @endif
                            @endif
                        @endif
                        @endif
                        @endif

                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                        <!-- Lease-Option Terms Sub-section -->
                        @if (@$auction->get->interested_lease_option_agreement != null)
                        <h5 class="mt-3 mb-2"><strong>Lease-Option Terms:</strong></h5>
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Interested in Offering a Lease-Option Agreement:
                            <span class="removeBold">{{ in_array(strtolower(@$auction->get->interested_lease_option_agreement ?? ''), ['yes']) ? 'Yes' : 'No' }}</span>
                        </div>
                        @if (in_array(strtolower(@$auction->get->interested_lease_option_agreement ?? ''), ['yes']))
                            @if (@$auction->get->lease_value != '' && @$auction->get->lease_value != 'null')
                            @php
                                $leaseCompDisplay = @$auction->get->lease_value;
                                $leaseType = @$auction->get->lease_type ?? 'flat';
                                if (in_array($leaseType, ['%', 'percent']) || str_contains($leaseCompDisplay ?? '', '%')) {
                                    $leaseCompDisplay = str_replace('%', '', $leaseCompDisplay) . '% of Total Purchase Price';
                                } else {
                                    $leaseCompDisplay = $fmtMoney($leaseCompDisplay);
                                }
                            @endphp
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Compensation for Creating the Lease-Option Agreement:
                                <span class="removeBold">{{ $leaseCompDisplay }}</span>
                            </div>
                            @endif
                            @if (@$auction->get->purchase_value != '' && @$auction->get->purchase_value != 'null')
                            @php
                                $purchaseCompDisplay = @$auction->get->purchase_value;
                                $purchaseType = @$auction->get->purchase_type ?? 'flat';
                                if (in_array($purchaseType, ['%', 'percent']) || str_contains($purchaseCompDisplay ?? '', '%')) {
                                    $purchaseCompDisplay = str_replace('%', '', $purchaseCompDisplay) . '% of Total Purchase Price';
                                } else {
                                    $purchaseCompDisplay = $fmtMoney($purchaseCompDisplay);
                                }
                            @endphp
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Compensation if Purchase Option is Exercised:
                                <span class="removeBold">{{ $purchaseCompDisplay }}</span>
                            </div>
                            @endif
                        @endif
                        @endif

                        @php
                            $hasLegalTerms = !empty(@$auction->get->protection_period)
                                || @$auction->get->early_termination_fee_option != null
                                || !empty(@$auction->get->retainer_fee_option)
                                || (@$auction->get->retained_deposits != null && @$auction->get->retained_deposits != '' && @$auction->get->retained_deposits != 'null')
                                || @$auction->get->agency_agreement_timeframe != null;
                        @endphp
                        @if ($hasLegalTerms)
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        @endif

                        <!-- Legal Terms Sub-section -->
                        @if ($hasLegalTerms)
                        <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>
                        @endif

                        @if (@$auction->get->protection_period != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Protection Period Timeframe:
                            <span class="removeBold">{{ $auction->get->protection_period }} Days</span>
                        </div>
                        @endif

                        @if (@$auction->get->early_termination_fee_option != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Early Termination Fee:
                            <span class="removeBold">{!! \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->early_termination_fee_option, $fmtMoney(@$auction->get->early_termination_fee_amount)) !!}</span>
                        </div>
                        @endif

                        @if (!empty(@$auction->get->retainer_fee_option))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Retainer Fee:
                            <span class="removeBold">{!! \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->retainer_fee_option, $fmtMoney(@$auction->get->retainer_fee_amount)) !!}</span>
                        </div>
                        @if (in_array(strtolower(@$auction->get->retainer_fee_option ?? ''), ['yes']))
                            @if (!empty(@$auction->get->retainer_fee_application))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Retainer Fee Application:
                                <span class="removeBold">{{ @$auction->get->retainer_fee_application }}</span>
                            </div>
                            @endif
                        @endif
                        @endif

                        @if (@$auction->get->retained_deposits != null && @$auction->get->retained_deposits != '' && @$auction->get->retained_deposits != 'null')
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Seller's Broker's Share of Retained Deposits:
                            <span class="removeBold">{{ $fmtPercent(@$auction->get->retained_deposits) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->agency_agreement_timeframe != null)
                        @php
                            $agencyTimeframe = @$auction->get->agency_agreement_timeframe;
                            if (strtolower($agencyTimeframe) === 'other' && @$auction->get->agency_agreement_custom) {
                                $agencyTimeframe = @$auction->get->agency_agreement_custom;
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Seller Agency Agreement Timeframe:
                            <span class="removeBold">{{ $agencyTimeframe }}</span>
                        </div>
                        @endif

                        @php
                            $hasBrokerageRel = @$auction->get->brokerage_relationship != null
                                || \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details_broker);
                        @endphp
                        @if ($hasBrokerageRel)
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        @endif

                        <!-- Brokerage Relationship Sub-section -->
                        @if ($hasBrokerageRel)
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
                        <div class="col-md-12 col-12 pt-2 removeBold">
                            {{ @$auction->get->additional_details_broker }}
                        </div>
                        @endif

                        </div>
                        @endif
                        @endif {{-- /Auth::check() broker compensation --}}

                        @php
                            $referralPct = trim((string)($auction->get->referral_percentage ?? ''));
                            if ($referralPct === '') {
                                $_firstBid = $auction->bids()->orderBy('id', 'asc')->first();
                                if ($_firstBid) {
                                    $referralPct = trim((string)($_firstBid->get->referral_fee_percent ?? ''));
                                }
                                unset($_firstBid);
                            }
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
                        <div class="card-header section-header">
                            <h4 class="section-title">{{ ($auction->user && $auction->user->user_type === 'agent') ? "Agent's Info" : "Seller Info" }}</h4>
                        </div>

                        @if (!empty($auction->get->first_name))
                            <div class="col-md-12 col-12 pt-2 fw-bold">First
                                Name:
                                <span class="removeBold">
                                    {{ $auction->get->first_name }}
                                </span>
                            </div>
                        @endif

                        @if (@$auction->get->current_status != null && @$auction->get->current_status != '' && @$auction->get->current_status != 'null')
                            <div class="col-md-12 col-12 pt-2 removeBold">
                                <span class="fw-bold">Seller's Current Status:</span>
                                {{ @$auction->get->current_status }}
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
                                            if (strpos($videoLink, 'watch?v=') !== false) {
                                                $youtubeEmbedUrl = str_replace('watch?v=', 'embed/', $videoLink);
                                            } elseif (strpos($videoLink, 'youtu.be/') !== false) {
                                                $videoId = basename(parse_url($videoLink, PHP_URL_PATH));
                                                $youtubeEmbedUrl = "https://www.youtube.com/embed/{$videoId}";
                                            } else {
                                                $youtubeEmbedUrl = $videoLink;
                                            }
                                            $youtubeEmbedUrl .=
                                                (strpos($youtubeEmbedUrl, '?') === false ? '?' : '&') .
                                                'autoplay=1&mute=1';
                                        @endphp

                                        <div class="col-md-6 col-6 pt-2 fw-bold">Personal Video:
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
                                            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $videoLink, $matches);
                                            $vimeoVideoId =
                                                $matches[1] ?? basename(parse_url($videoLink, PHP_URL_PATH));
                                            $vimeoEmbedUrl = "https://player.vimeo.com/video/{$vimeoVideoId}?autoplay=1&muted=1";
                                        @endphp

                                        <div class="col-md-6 col-6 pt-2 fw-bold">Personal Video:
                                            <span class="removeBold">
                                                <iframe src="{{ $vimeoEmbedUrl }}" width="100%" height="315"
                                                    frameborder="0"
                                                    allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                                                    allowfullscreen>
                                                </iframe>
                                            </span>
                                        </div>
                                    @else
                                        <div class="col-md-12 col-12 pt-2 fw-bold">Personal Video:
                                            <span class="removeBold">
                                                <a href="{{ $videoLink }}" target="_blank" rel="noopener noreferrer">{{ $videoLink }}</a>
                                            </span>
                                        </div>
                                    @endif
                                @else
                                    <div class="col-md-12 col-12 pt-2 fw-bold">Personal Video:
                                        <span class="removeBold">{{ $auction->get->video_link }}</span>
                                    </div>
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
                    <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => 'seller']) }}" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
                    </a>
                    {{-- PDF download button hidden from UI (backend route preserved) --}}
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

    // 🔹 Timer is informational only — actions are never locked by the BP timer
    $isBiddingTimerActive = $isBiddingPeriodListing && $expiration && !$isExpired;
    $canTakeAction = true; // Soft deadline: timer never locks bid actions

    // ⏱ Calculate remaining time if not expired (only for Bidding Period)
    if ($isBiddingPeriodListing && $expiration && !$isExpired) {
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
        <a href="{{ route('auction-chat', ['seller-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>


        {{-- ⏳ Countdown Timer - Only shown for Bidding Period listings --}}
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



        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp

        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
        @if (!$isExpired && !in_array($auction->is_sold, [true,'true',1,'1'], true) && $auction->status !== 'Pending' && $auction->status !== 'Hired Agent')
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
            onclick="window.location='{{ route('add_seller_agent_bid', @$auction->id) }}';">
            <span class="bid">Bid Now</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>
        @endif

        @elseif($auction->status === 'Hired Agent' || in_array($auction->is_sold, [true,'true',1,'1'], true))
        <div class="alert alert-success text-center mb-2">
            <i class="fa-solid fa-trophy"></i> <strong>An agent has been hired</strong>
        </div>
        <div class="status-pill status-hired w-100 d-flex justify-content-center">
            <i class="fa-solid fa-trophy me-2"></i>Hired Agent
        </div>
        @elseif($auction->status === 'Pending')
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
            // === SELLER BID SECTION: VARIABLE SETUP (matches Tenant pattern) ===
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
            $lastBidderNumber = null;
            if ($lowest_bidder) {
                $lastBidderNumber = $agentNumberMap[$lowest_bidder->user_id] ?? null;
            }
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            $isAgentViewer  = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
            // Traditional: agents cannot see other agents' bid info
            $canSeeBidSummary = $isListingOwner || !$isAgentViewer || $isBiddingPeriodListing || $isTraditionalListing;
            $otherBidsExist   = $auction->bids->where('user_id', '!=', $auth_id)->count() > 0;
        @endphp

        {{-- Last Bidder Info (hidden for Bidding Period to avoid timing hints to agents) --}}
        @if ($canSeeBidSummary && !($isBiddingPeriodListing && $isAgentViewer && !$isListingOwner))
            @if ($lowest_bidder && $lastBidderNumber)
            <p class="mb-3"><b>Agent {{ $lastBidderNumber }}</b> was the last bidder.</p>
            @else
            <p class="mb-3">No agents have submitted a bid yet.</p>
            @endif
        @endif

        {{-- Agent Visibility Info Messages (Bidding Period only) --}}
        @if ($isAgentViewer && !$isListingOwner)
            @if ($isBiddingPeriodListing && !$isExpired && !$userHasBid)
            <div class="alert alert-warning small mb-3 py-2">
                <i class="fa-solid fa-circle-info me-1"></i> <strong>Bidding Period:</strong> Submit your bid to view competing bids (Offered Services and Terms Match summaries only). Agent identities and compensation details remain confidential.
            </div>
            @elseif ($isBiddingPeriodListing && !$isExpired && $userHasBid && $otherBidsExist)
            <div class="alert alert-info small mb-3 py-2">
                <i class="fa-solid fa-eye me-1"></i> <strong>Bidding Period:</strong> Competing bids are visible below (Offered Services and Terms Match summaries only). Agent identities and compensation details remain confidential.
            </div>
            {{-- Competing Bids Display for Bidding Period --}}
            @php
                $competingBidsService = app(\App\Services\CompetingBidsService::class);
                $competingBids = $competingBidsService->getCompetingBids($auction->id, $auth_id);
            @endphp
            @if(count($competingBids) > 0)
            <div class="mb-4">
                <h6 class="fw-bold mb-3" style="color: #049399;"><i class="fa-solid fa-users me-2"></i>Competing Bids ({{ count($competingBids) }})</h6>
                @foreach($competingBids as $compBid)
                <div class="card mb-3" style="border-radius: 10px; border: 1px solid #e0e0e0;">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; border-radius: 10px 10px 0 0; padding: 12px 16px;">
                        <span class="fw-bold" style="font-family: 'Lufga', sans-serif;">{{ $compBid['anonymous_label'] }}</span>
                        @php
                            $cOverallScore = $compBid['match_score']['overall_percent'];
                            $cScoreColor   = $cOverallScore >= 80 ? '#28a745' : ($cOverallScore >= 50 ? '#ffc107' : '#dc3545');
                        @endphp
                        <span class="badge" style="background: {{ $cScoreColor }}; color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem;">
                            {{ $cOverallScore }}% Match
                        </span>
                    </div>
                    <div class="card-body" style="padding: 16px;">
                        @php
                            $cSvcTotal   = $compBid['match_score']['services_baseline_total'] ?? $compBid['match_score']['services_total'] ?? 0;
                            $cSvcMatched = $compBid['match_score']['services_matched_count'] ?? $compBid['match_score']['services_matched'] ?? 0;
                            $cSvcExtra   = $compBid['match_score']['services_extra_count'] ?? 0;
                            $cSvcMissing = $compBid['match_score']['services_missing_count'] ?? 0;
                            $cTrmTotal   = $compBid['match_score']['broker_comp_total'] ?? 0;
                            $cTrmMatched = $compBid['match_score']['broker_comp_matched'] ?? 0;
                            $cTrmChanged = $compBid['match_score']['terms_changed_count'] ?? 0;
                            $cTrmAdded   = $compBid['match_score']['terms_added_count'] ?? 0;
                            $cTrmMissing = max(0, $cTrmTotal - $cTrmMatched - $cTrmChanged);
                        @endphp
                        <div class="row">
                            {{-- Offered Services Row --}}
                            <div class="col-12 mb-2">
                                <span class="fw-semibold small" style="color: #049399;">Offered Services:</span>
                                <span class="small ms-1">
                                    @if($cSvcTotal > 0)
                                        <span style="color: #28a745; font-weight: 600;">{{ $cSvcMatched }}/{{ $cSvcTotal }} matched</span>
                                        @if($cSvcExtra > 0) <span class="text-muted">&bull; {{ $cSvcExtra }} extra</span>@endif
                                        @if($cSvcMissing > 0) <span style="color: #dc3545;">&bull; {{ $cSvcMissing }} missing</span>@endif
                                    @else
                                        <span class="text-muted">No services requested</span>
                                    @endif
                                </span>
                                @if($cSvcExtra > 0)
                                <div class="mt-1" style="font-size: 0.75rem; color: #6c757d; font-style: italic; margin-left: 0.25rem;">&#11088; Extra Value Added &mdash; does not affect match score</div>
                                @endif
                            </div>
                            {{-- Terms Match Row --}}
                            <div class="col-12 mb-2">
                                <span class="fw-semibold small" style="color: #049399;">Terms Match:</span>
                                <span class="small ms-1">
                                    @if($cTrmTotal > 0)
                                        <span style="color: #28a745; font-weight: 600;">{{ $cTrmMatched }}/{{ $cTrmTotal }} matched</span>
                                        @if($cTrmChanged > 0) <span style="color: #dc3545;">&bull; {{ $cTrmChanged }} changed</span>@endif
                                        @if($cTrmAdded > 0) <span class="text-muted">&bull; {{ $cTrmAdded }} added</span>@endif
                                        @if($cTrmMissing > 0) <span style="color: #dc3545;">&bull; {{ $cTrmMissing }} missing</span>@endif
                                    @else
                                        <span class="text-muted">No terms provided</span>
                                    @endif
                                </span>
                                <div class="mt-1" style="font-size: 0.75rem; color: #6c757d; font-style: italic; margin-left: 0.25rem;">&mdash; affects match score</div>
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <small class="text-muted fst-italic">Compared to Your Bid &mdash; Agent identities and compensation details remain confidential.</small>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
            @endif
        @endif

        <div class="card higestBider" id="bids-section">
            <div class="card-body card-body-padding">
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        @php
                            $agentNumber   = $agentNumberMap[$bid->user_id] ?? $loop->iteration;
                            $bidState      = data_get($bid, 'accepted', 'active');
                            // Check if a Seller counter term exists for this bid (never overrides terminal accepted/rejected states)
                            $hasSellerCounter = \App\Models\SellerCounterTerm::where('seller_agent_auction_bid_id', data_get($bid, 'id'))->where('status', 1)->exists();
                            $isTerminalState  = in_array((string)$bidState, ['accepted', 'rejected'], true);
                            $effectiveBidState = (!$isTerminalState && $hasSellerCounter) ? 'countered' : (string)$bidState;
                            $bidStatusLabel = match($effectiveBidState) {
                                'accepted'  => 'Accepted',
                                'rejected'  => 'Rejected',
                                'countered' => 'Countered',
                                default     => 'Active',
                            };
                            $bidStatusColor = match($effectiveBidState) {
                                'accepted'  => '#28a745',
                                'rejected'  => '#dc3545',
                                'countered' => '#ffc107',
                                default     => '#1a4a6e',
                            };
                            $servicesList        = (array) data_get($bid, 'get.services', []);
                            $additionalServices  = (array) data_get($bid, 'get.other_services', []);
                            $totalServicesCount  = count(array_filter($servicesList, fn($s) => $s !== 'Other')) + count($additionalServices);
                            $isBidOwner          = (data_get($bid, 'user_id') == $auth_id);
                            $bidAccepted         = data_get($bid, 'accepted');
                            $canEditWithdraw     = $isBidOwner && !$isExpired && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';
                            $isOtherAgentsBid    = !$isListingOwner && !$isBidOwner;

                            // Seller Bid Visibility Logic (matches Tenant pattern):
                            // - Traditional: Agents see all bid cards but can only open View Full Bid on their own bid
                            // - Bidding Period: Agents can see anonymized bids ONLY if they submitted a bid first
                            // - Listing Owner: Always sees all bids
                            $isAgent    = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
                            $canViewBid = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $isAgent && $userHasBid) || ($isTraditionalListing && $isAgent);
                            if (!$canViewBid && $isAgent) { continue; }

                            // Build Seller purchase fee display for card body summary
                            $sellerCommStructure        = data_get($bid, 'get.commission_structure', 'Not specified');
                            $sellerPurchaseFeeType      = data_get($bid, 'get.purchase_fee_type', '');
                            $sellerPurchaseFeeFlat      = data_get($bid, 'get.purchase_fee_flat', '');
                            $sellerPurchaseFeePerc      = data_get($bid, 'get.purchase_fee_percentage', '');
                            $sellerPurchaseFeeFlatCombo = data_get($bid, 'get.purchase_fee_flat_combo', '');
                            $sellerPurchaseFeePercCombo = data_get($bid, 'get.purchase_fee_percentage_combo', '');
                            $sellerPurchaseFeeOther     = data_get($bid, 'get.purchase_fee_other', '');

                            $sellerPurchaseFeeDisplay = '-';
                            if ($sellerPurchaseFeeType === 'flat' && $sellerPurchaseFeeFlat) {
                                $sellerPurchaseFeeDisplay = $fmtMoney($sellerPurchaseFeeFlat) ?? '-';
                            } elseif ($sellerPurchaseFeeType === 'percentage' && $sellerPurchaseFeePerc) {
                                $sellerPurchaseFeeDisplay = ($fmtPercent($sellerPurchaseFeePerc) ?? '-') . ' of Total Purchase Price';
                            } elseif ($sellerPurchaseFeeType === 'combo') {
                                $sellerPurchaseFeeDisplay = $joinParts([
                                    $sellerPurchaseFeePercCombo ? ($fmtPercent($sellerPurchaseFeePercCombo) . ' of Total Purchase Price') : null,
                                    $fmtMoney($sellerPurchaseFeeFlatCombo),
                                ]) ?? '-';
                            } elseif ($sellerPurchaseFeeType === 'other' && $sellerPurchaseFeeOther) {
                                $sellerPurchaseFeeDisplay = $sellerPurchaseFeeOther;
                            }

                            // === MATCH SCORE CALCULATION via SellerBidMatchScoreHelper ===
                            // ── Seller Match Score Baseline ─────────────────────────────────────────────────────────
                            // BASELINE POLICY: Card score ALWAYS uses the original auction listing terms as baseline
                            // to ensure a consistent denominator across all bids on the same listing.
                            // Counter comparison is computed separately for the dual-score display (authorized only).
                            $latestCounter   = \App\Models\SellerCounterTerm::with('meta')
                                ->where('seller_agent_auction_bid_id', $bid->id)
                                ->where('status', 1)
                                ->latest('updated_at')
                                ->first();
                            // Note: ->get accessor on SellerAgentAuction / SellerAgentAuctionBid calls getGetAttribute()
                            // which queries the meta table directly each access — always fresh, no eager-load dependency.
                            $propertyType    = data_get($auction, 'get.property_type', '');
                            $bidDataArr      = (array) data_get($bid, 'get', []);
                            $auctionDataArr  = (array) data_get($auction, 'get', []);

                            // Card score always uses original listing baseline
                            $baselineData  = $auctionDataArr;
                            $baselineLabel = $isListingOwner ? 'Your Original Terms' : "Seller's Original Terms";

                            $scoreResult     = \App\Helpers\SellerBidMatchScoreHelper::calculate($baselineData, $bidDataArr, null, $propertyType);
                            $totalScore      = $scoreResult['overall_percent'] ?? 100;
                            $brokerScore     = $scoreResult['broker_comp_percent'] ?? 100;
                            $servicesScore   = $scoreResult['services_percent'] ?? 100;
                            $brokerTotal     = $scoreResult['broker_comp_total'] ?? 0;
                            $brokerMatched   = $scoreResult['broker_comp_matched'] ?? 0;
                            $servicesTotal   = $scoreResult['services_baseline_total'] ?? 0;
                            $servicesMatched = $scoreResult['services_matched_count'] ?? 0;
                            $servicesExtraCount   = $scoreResult['services_extra_count'] ?? 0;
                            $servicesMissingCount = $scoreResult['services_missing_count'] ?? 0;
                            $brokerMismatches = $scoreResult['changed_terms'] ?? [];
                            $termsChangedCount = $scoreResult['terms_changed_count'] ?? 0;
                            $termsAddedCount   = $scoreResult['terms_added_count'] ?? 0;
                            $mismatchStyle    = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                            $mismatchBadge    = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Mismatch</span>';
                            $totalScoreColor = \App\Helpers\SellerBidMatchScoreHelper::scoreColor($totalScore);
                            $getScoreColor   = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::scoreColor((int)$s);
                            /**
                             * ZERO-BASELINE / NO-DATA GUARD
                             *
                             * If there is no comparable baseline match data, do not display 100%.
                             * Render "No match data available" instead.
                             *
                             * This behavior is locked by QA baseline documentation.
                             * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
                             */
                            $hasAnyBaseline  = ($brokerTotal > 0 || $servicesTotal > 0);

                            // Dual-score: $scoreResult is already listing-based; compute counter score separately
                            $originalScore = $scoreResult;
                            if ($latestCounter && $latestCounter->meta->count()) {
                                $latestCounterScore = \App\Helpers\SellerBidMatchScoreHelper::calculate(
                                    $latestCounter->meta->pluck('meta_value', 'meta_key')->toArray(),
                                    $bidDataArr, null, $propertyType
                                );
                                $showDualScore = true;
                            } else {
                                $latestCounterScore = null;
                                $showDualScore = false;
                            }
                        @endphp

                        <!-- Bid Card - Collapsible Accordion Design (matches Tenant) -->
                        <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

                            <!-- A) Card Header - Clickable to expand/collapse -->
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

                            <!-- Collapsible Content -->
                            <div class="bid-collapse-content" id="bidCollapse-{{ data_get($bid, 'id') }}" style="display: none;">
                            <div class="card-body" style="padding: 20px;">

                                @if($isListingOwner || $isBidOwner)
                                <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

                                {{-- Counter Offer Notice Banner — visible immediately on accordion expand (owner/agent only) --}}
                                @if ($latestCounter && ($isListingOwner || $isBidOwner))
                                @php $scBidCardCounterFromOwner = ($latestCounter->user_id == data_get($auction, 'user_id')); @endphp
                                <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3"
                                     style="background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 0.9rem;">
                                    <i class="fa-solid fa-right-left mt-1" style="color: #e6a800; flex-shrink: 0;"></i>
                                    <div>
                                        @if ($isListingOwner && $scBidCardCounterFromOwner)
                                            <strong>Counter Offer Sent.</strong>
                                        @elseif ($isListingOwner && !$scBidCardCounterFromOwner)
                                            <strong>Counter Offer Received.</strong>
                                        @elseif ($isBidOwner && $scBidCardCounterFromOwner)
                                            <strong>Counter Offer Received.</strong>
                                        @elseif ($isBidOwner && !$scBidCardCounterFromOwner)
                                            <strong>Counter Offer Sent.</strong>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                {{-- ── Counter action row — directly on bid card ── --}}
                                @if ($latestCounter && ($isListingOwner || $isBidOwner) && $bidState !== 'accepted' && $bidState !== 'rejected')
                                @php $bidCardViewerSentLatestSeller = ($isListingOwner && $scBidCardCounterFromOwner) || ($isBidOwner && !$scBidCardCounterFromOwner); @endphp
                                @if ($bidCardViewerSentLatestSeller)
                                {{-- WAITING: single row — View CT + Edit CT --}}
                                <div class="d-flex gap-2 align-items-center mb-2">
                                    <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                    </a>
                                    <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                    </a>
                                </div>
                                @else
                                {{-- RESPONSE: View CT only — Accept/Counter Back/Reject are on View Counter Terms page --}}
                                <div class="d-flex align-items-center mb-2">
                                    <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                    </a>
                                </div>
                                @endif
                                @endif

                                <!-- B) Offered Services Count -->
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
                                    <span style="font-weight: 500; color: #856404; font-size: 0.95rem;" title="Extra services were included by the Agent beyond the Seller&#39;s original request. These do not increase the match score but may provide additional value.">Extra Value Added: {{ $servicesExtraCount }} {{ $servicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
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

                                <!-- B2) Match Score Summary (Compact Display on Bid Card) -->
                                @php
                                    $showMatchScoreOnCard = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $isAgentViewer && $userHasBid);
                                @endphp
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
                                                <div style="font-size: 0.75rem; color: #6c757d;">vs. Seller's Original Request</div>
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
                                        Match Score compares this bid only to the Seller's original request. Added services or added terms are shown for transparency but do not increase the score.
                                    </div>
                                    @endif
                                </div>
                                @endif

                                <!-- D) View Full Bid link -->
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

                                <!-- E) Edit Actions for Bid Owner -->
                                @if ($canEditWithdraw)
                                <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                    <a href="{{ route('add_seller_agent_bid', $auction->id) }}?edit={{ data_get($bid, 'id') }}"
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
                                    @if($bidAccepted === 'accepted')
                                    @php
                                        $bidOwnerSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
                                            ->where('agent_user_id', data_get($bid, 'user_id'))
                                            ->first();
                                    @endphp
                                    @if($bidOwnerSummary)
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <a href="{{ route('accepted-bid-summary.view', $bidOwnerSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="fa-solid fa-file-lines me-1"></i> View Accepted Bid Summary
                                        </a>
                                        @if(!$bidOwnerSummary->isAgentSigned())
                                        <a href="{{ route('accepted-bid-summary.sign-form', $bidOwnerSummary->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                                        </a>
                                        @endif
                                        @if($bidOwnerSummary->isFullySigned())
                                        <a href="{{ route('accepted-bid-summary.download-pdf', $bidOwnerSummary->id) }}" class="btn btn-success btn-sm">
                                            <i class="fa-solid fa-download me-1"></i> Download Signed PDF
                                        </a>
                                        @endif
                                    </div>
                                    @endif
                                    @endif
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
                                        @php $lcColorSell = $getScoreColor($latestCounterScore['overall_percent']); @endphp
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColorSell }};">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                    <span class="badge" style="background: {{ $lcColorSell }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $latestCounterScore['overall_percent'] }}%</span>
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
                            </div> {{-- End collapse div --}}
                        </div> {{-- End bid card --}}

                        {{-- ===== INLINE COUNTER BIDDING HISTORY ===== --}}
                        @php
                            $sellerCounterBids = \App\Models\SellerCounterTerm::with('meta', 'user')
                                ->where('seller_agent_auction_bid_id', data_get($bid, 'id'))
                                ->orderBy('created_at', 'desc')
                                ->get();
                            $showSellerCounterBids = ($isListingOwner || $isBidOwner);
                        @endphp

                        @if ($showSellerCounterBids && $sellerCounterBids->count() > 0)
                        <div class="counter-bids-section mt-4" id="counter-section-{{ data_get($bid, 'id') }}">
                            <div class="counter-bids-toggle"
                                style="cursor: pointer;"
                                onclick="event.stopPropagation(); var target = document.getElementById('counterBids{{ data_get($bid, 'id') }}'); var arrow = this.querySelector('.counter-arrow'); if(target.style.display === 'none' || target.style.display === '') { target.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; } else { target.style.display = 'none'; arrow.style.transform = 'rotate(0deg)'; }">
                                <div class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                    <h5 class="mb-0" style="color: #2c3e50;">Counter Bidding History</h5>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2">{{ $sellerCounterBids->count() }} counter offers</span>
                                        <span class="counter-arrow" style="transition: transform 0.3s;">↓</span>
                                    </div>
                                </div>
                            </div>

                            <div id="counterBids{{ data_get($bid, 'id') }}"
                                class="counter-bids-content"
                                style="display: none;"
                                aria-labelledby="counterBidsHeading{{ data_get($bid, 'id') }}">
                                <div class="accordion-body p-3 border border-top-0 rounded-bottom counter-font">
                                    @foreach ($sellerCounterBids as $counterBid)
                                    @php
                                        $scIsOwner = data_get($auction, 'user_id') == $auth_id;
                                        $scIsAgent = data_get($bid, 'user_id') == $auth_id;
                                        $scIsCounterFromOwner = $counterBid->user_id == data_get($auction, 'user_id');
                                        $scIsCounterFromAgent = $counterBid->user_id == data_get($bid, 'user_id');

                                        $scRawBidState = data_get($bid, 'accepted', '0');
                                        $scBidState = in_array($scRawBidState, [null, 0, '0', 'no', 'pending'], true)
                                            ? '0'
                                            : (string) $scRawBidState;

                                        // Seller uses integer status: 1 = active/pending, 0 = rejected
                                        $scRawCounterState = data_get($counterBid, 'status', '0');
                                        // Normalize: 0 or '0' → rejected; 1 or '1' → pending ('0' in canonical form)
                                        $scCounterState = in_array((string)$scRawCounterState, ['0'], true)
                                            ? 'rejected'
                                            : (in_array((string)$scRawCounterState, ['1'], true) ? '0' : (string) $scRawCounterState);

                                        $scShowCounterActions = false;
                                        if ($scBidState === '0' && $scCounterState === '0') {
                                            if ($scIsOwner && $scIsCounterFromAgent) {
                                                $scShowCounterActions = true;
                                            }
                                            if ($scIsAgent && $scIsCounterFromOwner) {
                                                $scShowCounterActions = true;
                                            }
                                        }

                                        $scOwnerFirst = data_get($auction, 'user.first_name', '');
                                        $scOwnerLast  = data_get($auction, 'user.last_name', '');
                                        $scAgentFirst = data_get($bid, 'user.first_name', '');
                                        $scAgentLast  = data_get($bid, 'user.last_name', '');

                                        $scActorUserId = $scIsCounterFromOwner ? data_get($bid, 'user_id') : data_get($auction, 'user_id');
                                        $scActorFirst  = $scIsCounterFromOwner ? $scAgentFirst : $scOwnerFirst;
                                        $scActorLast   = $scIsCounterFromOwner ? $scAgentLast  : $scOwnerLast;

                                        $scCreatorFirst = data_get($counterBid, 'user.first_name', '');
                                        $scCreatorLast  = data_get($counterBid, 'user.last_name', '');

                                        // Get all meta for this counter term
                                        $scAllMeta = $counterBid->getAllMeta();

                                        // Changed badge helper: compare counter value against original bid
                                        $scIsChanged = function($counterVal, $origKey) use ($bid) {
                                            $origVal = data_get($bid, 'get.' . $origKey, null);
                                            $normalizeVal = function($v) {
                                                if (is_null($v) || $v === '') return '';
                                                if (is_array($v) || is_object($v)) return json_encode($v);
                                                $v = trim((string) $v);
                                                return preg_replace('/[\s$,%]/', '', strtolower($v));
                                            };
                                            return $normalizeVal($counterVal) !== $normalizeVal($origVal);
                                        };

                                        $scChangedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                        $scChangedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';

                                        // Purchase fee display
                                        $scPurchaseFeeType = $scAllMeta['purchase_fee_type'] ?? '';
                                        $scPurchaseFeeDisplay = $scPurchaseFeeType;
                                        if ($scPurchaseFeeType === 'flat' && !empty($scAllMeta['purchase_fee_flat'])) {
                                            $scPurchaseFeeDisplay = '$' . number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_flat']), 2);
                                        } elseif ($scPurchaseFeeType === 'percentage' && !empty($scAllMeta['purchase_fee_percentage'])) {
                                            $scPurchaseFeeDisplay = rtrim(rtrim(number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_percentage']), 2), '0'), '.') . '% of Total Purchase Price';
                                        } elseif ($scPurchaseFeeType === 'combo') {
                                            $pctPart = !empty($scAllMeta['purchase_fee_percentage_combo']) ? (rtrim(rtrim(number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_percentage_combo']), 2), '0'), '.') . '% of Total Purchase Price') : null;
                                            $flatPart = !empty($scAllMeta['purchase_fee_flat_combo']) ? ('$' . number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_flat_combo']), 2)) : null;
                                            $scPurchaseFeeDisplay = implode(' + ', array_filter([$pctPart, $flatPart])) ?: $scPurchaseFeeType;
                                        } elseif ($scPurchaseFeeType === 'other' && !empty($scAllMeta['purchase_fee_other'])) {
                                            $scPurchaseFeeDisplay = $scAllMeta['purchase_fee_other'];
                                        }

                                        // ── Services diff (counter vs original bid) ──
                                        $scCtrSvcsRaw = is_string($scAllMeta['services'] ?? '') ? json_decode($scAllMeta['services'] ?? '', true) ?? [] : ($scAllMeta['services'] ?? []);
                                        $scCtrSvcsRaw = array_values(array_filter((array)$scCtrSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));
                                        $scOtherSvcs  = is_string($scAllMeta['other_services'] ?? '') ? json_decode($scAllMeta['other_services'] ?? '', true) ?? [] : ($scAllMeta['other_services'] ?? []);
                                        $scOtherSvcs  = array_values(array_filter((array)$scOtherSvcs, fn($s) => is_string($s) && trim($s) !== ''));

                                        // Unicode-safe normalizer (matches main bid section normalizeStr)
                                        $scNormStr = function($s) {
                                            $s = (string)$s;
                                            // Convert literal \uXXXX escape sequences to actual Unicode (handles copy-pasted JSON strings in config)
                                            $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/i', fn($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $s);
                                            // Normalize curly/smart quotes to straight equivalents
                                            $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
                                            $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
                                            return strtolower(trim($s));
                                        };

                                        // Build normalized lookup for counter services
                                        $scSelectedNormalized = [];
                                        foreach ($scCtrSvcsRaw as $svc) {
                                            $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                            $scSelectedNormalized[$scNormStr($svc)]        = $displaySvc;
                                            $scSelectedNormalized[$scNormStr($displaySvc)] = $displaySvc;
                                        }

                                        // Property type → config key
                                        $scBidPropTypeRaw = $scAllMeta['property_type'] ?? data_get($auction, 'get.property_type', 'Residential');
                                        $scPropNorm = strtolower(trim($scBidPropTypeRaw));
                                        if (str_contains($scPropNorm, 'income')) {
                                            $scBidPropKey = 'Income';
                                        } elseif (str_contains($scPropNorm, 'commercial')) {
                                            $scBidPropKey = 'Commercial';
                                        } elseif (str_contains($scPropNorm, 'business')) {
                                            $scBidPropKey = 'Business';
                                        } elseif (str_contains($scPropNorm, 'vacant') || str_contains($scPropNorm, 'land')) {
                                            $scBidPropKey = 'Vacant Land';
                                        } else {
                                            $scBidPropKey = 'Residential';
                                        }

                                        // Seller services config (same structure as main bid section)
                                        $scSellerServicesConfig = [
                                            'Residential' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                    "Create a branded flyer featuring the property\u2019s key highlights",
                                                    "Post the property on Facebook Marketplace",
                                                    "Post the property on Craigslist under the \"Homes for Sale\" category",
                                                    "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                    "Promote the listing on Facebook in Real Estate or Community Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                                                    "Upload a TikTok video walkthrough of the property",
                                                    "Upload a YouTube video walkthrough of the property",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Presentation' => [
                                                    "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                    "Provide a custom listing preparation checklist",
                                                    "Collect property details and prepare MLS remarks and a public listing description",
                                                    "Provide a visual consultation for interior layout, cleanliness, and presentation",
                                                    "Provide a curb appeal consultation focused on exterior presentation",
                                                    "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough tour",
                                                    "Provide a 3D virtual tour",
                                                    "Provide virtual staging (digital enhancements only; no physical staging)",
                                                    "Provide digital photo enhancements",
                                                    "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                ],
                                                '🏡 Showings & Access Coordination' => [
                                                    "Ensure proper notice is provided if the property is occupied",
                                                    "Install a real estate sign on the property",
                                                    "Install a lockbox for Agent access",
                                                    "Schedule and attend showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📑 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate price, terms, and contingencies with the Buyer\u2019s Agent or Buyer",
                                                    "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                    "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate scheduling for inspections, appraisals, and other requested evaluations",
                                                    "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Final Walkthrough",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions",
                                                    "Provide general insight on local market trends, seasonal timing, and pricing thresholds",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                                                ],
                                            ],
                                            'Income' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                    "List the property on Crexi.com",
                                                    "List the property on LoopNet.com",
                                                    "Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)",
                                                    "Post the property on Craigslist under the \"Multi-Family for Sale\" category",
                                                    "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                    "Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Investment or Real Estate Groups",
                                                    "Upload a TikTok video walkthrough of the property",
                                                    "Upload a YouTube video walkthrough of the property",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Investment Packaging' => [
                                                    "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                    "Provide a custom listing preparation checklist",
                                                    "Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)",
                                                    "Provide a visual consultation focused on interior layout, cleanliness, and unit presentation",
                                                    "Provide a curb appeal consultation focused on exterior maintenance and first impressions",
                                                    "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough tour",
                                                    "Provide a 3D virtual tour",
                                                    "Provide virtual staging (digital enhancements only; no physical staging)",
                                                    "Provide digital photo enhancements",
                                                    "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                ],
                                                '🏘️ Showings & Access Coordination' => [
                                                    "Respond to Buyer inquiries and screen for general qualifications",
                                                    "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                                    "Ensure proper notice is provided if the property is occupied",
                                                    "Install a real estate sign on the property",
                                                    "Install a lockbox for Agent access",
                                                    "Schedule and attend showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📉 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Manage communication with the Buyer\u2019s Agent or Buyers",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                    "Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports",
                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only \u2014 no endorsement or warranty is made",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                    "Coordinate with the Buyer\u2019s Agent, Buyer\u2019s Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Final Walkthrough",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity",
                                                    "Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables",
                                                    "Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines",
                                                ],
                                            ],
                                            'Commercial' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "List the property on Crexi.com",
                                                    "List the property on LoopNet.com",
                                                    "Create a branded flyer summarizing the property\u2019s investment highlights and key selling points",
                                                    "Post the property on Craigslist under the \"Commercial for Sale\" category",
                                                    "Promote the listing on Facebook in Commercial or Investor Real Estate Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                                    "Upload a TikTok video walkthrough of the property",
                                                    "Upload a YouTube video walkthrough of the property",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Asset Presentation' => [
                                                    "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                    "Provide a visual consultation on interior layout, cleanliness, and overall presentation",
                                                    "Provide a curb appeal consultation focused on exterior appearance and first impressions",
                                                    "Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only \u2014 no endorsement or warranty is made)",
                                                    "Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)",
                                                    "Organize zoning documentation, surveys, and public record reports (as available)",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough tour",
                                                    "Provide a 3D virtual tour",
                                                    "Provide virtual staging (digital enhancements only; no physical staging)",
                                                    "Provide digital photo enhancements",
                                                    "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                ],
                                                '🏢 Showings & Access Coordination' => [
                                                    "Respond to Buyer inquiries and screen for general qualifications",
                                                    "Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings",
                                                    "Ensure proper notice is provided if the property is occupied",
                                                    "Install a real estate sign on the property",
                                                    "Install a lockbox for Agent access",
                                                    "Schedule and attend showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📉 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate price, deal structure, deposits, and Buyer contingencies",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Assist with inspection, environmental, and due diligence negotiations",
                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                    "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Compile and review relevant transaction documentation (as available)",
                                                    "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable commercial sales, current market conditions, and asset class trends",
                                                    "Provide general insight on local market trends, timing, and pricing thresholds",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                                                ],
                                            ],
                                            'Business' => [
                                                '📢 Business Marketing & Listing Promotion' => [
                                                    "List the business on BizBuySell.com",
                                                    "List the business on BizQuest.com",
                                                    "Promote the listing on Facebook in Business-for-Sale or Entrepreneur Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn targeting investors, entrepreneurs, and business buyers",
                                                    "Upload a TikTok video summarizing the business opportunity",
                                                    "Upload a YouTube video summarizing the business opportunity",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Business Listing Preparation & Packaging' => [
                                                    "Conduct a business overview consultation to understand operations, financials, and key selling points",
                                                    "Assist with assembling a business overview packet summarizing revenue, expenses, and key operations (based on information provided by Seller)",
                                                    "Provide guidance on organizing materials such as P&L summaries, tax returns, and operational documents for Buyer review",
                                                    "Provide referrals to Business Attorneys, CPAs, or Business Valuation experts (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🏢 Buyer Screenings & Meetings' => [
                                                    "Respond to Buyer inquiries and conduct preliminary screening conversations",
                                                    "Prepare and distribute a Non-Disclosure Agreement (NDA) prior to sharing sensitive business information",
                                                    "Coordinate and schedule Buyer meetings, walkthroughs, or virtual tours",
                                                    "Facilitate preliminary meetings between the Buyer and Seller (as appropriate)",
                                                ],
                                                '📑 Offer & Contract Management' => [
                                                    "Present all offers or Letters of Intent (LOIs) to the Seller and summarize key terms",
                                                    "Assist with negotiations on purchase price, deal structure, earnest money, and contingencies",
                                                    "Coordinate due diligence requests and document sharing between parties",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements and related documents to all parties",
                                                    "Monitor contract contingencies and key transaction milestones",
                                                    "Provide referrals to Business Attorneys, CPAs, or Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate with the Buyer\u2019s representatives, Business Attorney, and Escrow Officer to prepare for Closing",
                                                    "Assist with organizing final closing documents, transfer instructions, and bill of sale coordination",
                                                    "Confirm delivery of final executed agreements and relevant disclosures to all parties",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide general insight on local market demand, buyer profiles, and pricing expectations",
                                                    "Advise on positioning the business for sale, including presentation improvements and key value drivers",
                                                    "Recommend adjustments to pricing or marketing strategy if the business is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, confidentiality considerations, and transition planning",
                                                ],
                                            ],
                                            'Vacant Land' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                    "List the property on LandWatch.com",
                                                    "List the property on LandFlip.com",
                                                    "List the property on Lands of America",
                                                    "Create a branded flyer highlighting the land\u2019s key features and potential uses",
                                                    "Post the property on Craigslist under the \"Land for Sale\" category",
                                                    "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                    "Promote the listing on Facebook in Real Estate or Land Buyer Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Real Estate or Development Groups",
                                                    "Upload a TikTok video walkthrough or overview of the land",
                                                    "Upload a YouTube video walkthrough or overview of the land",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Land Presentation' => [
                                                    "Conduct a site walkthrough and provide recommendations for listing readiness",
                                                    "Provide a custom land listing preparation checklist",
                                                    "Collect and organize available property information (e.g., zoning, utilities, access, surveys)",
                                                    "Prepare MLS remarks and a public listing description highlighting land use potential",
                                                    "Provide referrals to surveyors, engineers, or land use consultants (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough or site overview",
                                                    "Provide digital photo enhancements",
                                                ],
                                                '🔍 Showings & Site Access Coordination' => [
                                                    "Schedule and attend property showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📑 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate price, terms, and contingencies with the Buyer\u2019s Agent or Buyer",
                                                    "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Monitor contract milestones, contingency periods, and financing or due diligence deadlines",
                                                    "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable land sales, local development activity, and market conditions",
                                                    "Provide general insight on local market trends, typical buyer profiles, and land use considerations",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, required disclosures, and land listing considerations",
                                                ],
                                            ],
                                        ];
                                        $scPropConfig = $scSellerServicesConfig[$scBidPropKey] ?? $scSellerServicesConfig['Residential'];

                                        // Build flat config norm map for unmapped detection
                                        $scConfigFlatNorm = [];
                                        foreach ($scPropConfig as $scCatSvcs) {
                                            foreach ($scCatSvcs as $scS) {
                                                $scConfigFlatNorm[$scNormStr($scS)] = true;
                                            }
                                        }
                                        $scUnmappedSvcs = array_values(array_filter($scCtrSvcsRaw, fn($s) => !isset($scConfigFlatNorm[$scNormStr($s)])));

                                        // Diff: added (in counter but not in original bid)
                                        $scOrigBidSvcsRaw = (array) data_get($bid, 'get.services', []);
                                        if (is_string(data_get($bid, 'get.services', []))) $scOrigBidSvcsRaw = json_decode(data_get($bid, 'get.services', '[]'), true) ?: [];
                                        $scOrigBidSvcsRaw = array_values(array_filter($scOrigBidSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));
                                        $scOrigBidSvcsNorm = array_map($scNormStr, $scOrigBidSvcsRaw);
                                        $scCtrSvcIsAdded = fn(string $s): bool => !in_array($scNormStr($s), $scOrigBidSvcsNorm, true);

                                        // Diff: removed (in original bid but not in counter)
                                        $scCtrSvcsNormFlat = array_map($scNormStr, $scCtrSvcsRaw);
                                        $scCtrRemovedSvcs = array_values(array_filter($scOrigBidSvcsRaw, fn($s) => !in_array($scNormStr($s), $scCtrSvcsNormFlat, true)));

                                        // Other services diff
                                        $scOrigOtherRaw = data_get($bid, 'get.other_services', []);
                                        if (is_string($scOrigOtherRaw)) $scOrigOtherRaw = json_decode($scOrigOtherRaw, true) ?: [];
                                        $scOrigOtherNorm = array_map(fn($s) => strtolower(trim((string)$s)), array_filter((array)$scOrigOtherRaw, fn($s) => is_string($s) && trim($s) !== ''));
                                        $scOtherIsAdded = fn(string $s): bool => !in_array(strtolower(trim($s)), $scOrigOtherNorm, true);
                                        $scOtherRemovedDisplay = array_values(array_filter(
                                            (array)$scOrigOtherRaw,
                                            fn($s) => is_string($s) && trim($s) !== '' && !in_array(strtolower(trim($s)), array_map(fn($x) => strtolower(trim((string)$x)), $scOtherSvcs), true)
                                        ));

                                        // === Format helpers ===
                                        $scFmtMoney = function($v) {
                                            if (empty($v)) return null;
                                            $c = preg_replace('/[^0-9.\-]/', '', (string)$v);
                                            if ($c === '' || !is_numeric($c)) return null;
                                            return '$' . number_format((float)$c, 2);
                                        };
                                        $scFmtPct = function($v) {
                                            if (empty($v)) return null;
                                            $c = preg_replace('/[^0-9.\-]/', '', (string)$v);
                                            if ($c === '' || !is_numeric($c)) return null;
                                            return rtrim(rtrim(number_format((float)$c, 2), '0'), '.') . '%';
                                        };

                                        // === A) Buyer's Broker Commission Fee ===
                                        $scCommStructType = $scAllMeta['commission_structure_type'] ?? '';
                                        $scBuyerBrokerFee = null;
                                        if ($scCommStructType === 'Flat Fee' && !empty($scAllMeta['commission_structure_type_fee_flat'])) {
                                            $scBuyerBrokerFee = $scFmtMoney($scAllMeta['commission_structure_type_fee_flat']);
                                        } elseif ($scCommStructType === 'Percentage of the Total Purchase Price' && !empty($scAllMeta['commission_structure_type_fee_percentage'])) {
                                            $scBuyerBrokerFee = $scFmtPct($scAllMeta['commission_structure_type_fee_percentage']) . ' of Total Purchase Price';
                                        } elseif ($scCommStructType === 'Flat Fee + Percentage' && (!empty($scAllMeta['commission_structure_type_fee_flat_combo']) || !empty($scAllMeta['commission_structure_type_fee_percentage_combo']))) {
                                            $bbfParts = [];
                                            if (!empty($scAllMeta['commission_structure_type_fee_percentage_combo'])) $bbfParts[] = ($scFmtPct($scAllMeta['commission_structure_type_fee_percentage_combo']) ?? '') . ' of Total Purchase Price';
                                            if (!empty($scAllMeta['commission_structure_type_fee_flat_combo'])) $bbfParts[] = $scFmtMoney($scAllMeta['commission_structure_type_fee_flat_combo']);
                                            $scBuyerBrokerFee = implode(' + ', array_filter($bbfParts)) ?: null;
                                        } elseif (strtolower($scCommStructType) === 'other' && !empty($scAllMeta['commission_structure_type_fee_other'])) {
                                            $scBuyerBrokerFee = $scAllMeta['commission_structure_type_fee_other'];
                                        } elseif ($scCommStructType) {
                                            $scBuyerBrokerFee = $scCommStructType;
                                        }

                                        // === B) Seller's Broker Leasing Fee ===
                                        $scLeasingFeeType = $scAllMeta['seller_leasing_fee_type'] ?? '';
                                        $scLeasingFeeDisplay = null;
                                        if ($scLeasingFeeType === 'Flat Fee' && !empty($scAllMeta['seller_leasing_gross_purchase_fee_flat_amount'])) {
                                            $scLeasingFeeDisplay = $scFmtMoney($scAllMeta['seller_leasing_gross_purchase_fee_flat_amount']);
                                        } elseif ($scLeasingFeeType === 'Percentage of the Gross Lease Value' && !empty($scAllMeta['seller_leasing_gross'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross']) . ' of the Gross Lease Value';
                                        } elseif ($scLeasingFeeType === 'Percentage of the Rent Due Each Rental Period' && !empty($scAllMeta['seller_leasing_gross_rental'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross_rental']) . ' of the Rent Due Each Rental Period';
                                        } elseif ($scLeasingFeeType === "Percentage of the First Month's Rent" && !empty($scAllMeta['seller_leasing_gross_month_rent'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross_month_rent']) . " of the First Month's Rent";
                                        } elseif ($scLeasingFeeType === "Percentage of Month's Rent" && !empty($scAllMeta['seller_leasing_gross_month_rent'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross_month_rent']) . " of Month's Rent";
                                            if (!empty($scAllMeta['seller_leasing_gross_no_of_months']) && $scAllMeta['seller_leasing_gross_no_of_months'] != 'null') {
                                                $scLeasingFeeDisplay .= ' x ' . intval($scAllMeta['seller_leasing_gross_no_of_months']) . ' Months';
                                            }
                                        } elseif ($scLeasingFeeType === 'Percentage of Net Aggregate Rent') {
                                            $scNetAggVal = $scAllMeta['seller_leasing_gross_other'] ?? $scAllMeta['seller_leasing_gross'] ?? null;
                                            if ($scNetAggVal) $scLeasingFeeDisplay = $scFmtPct($scNetAggVal) . ' of Net Aggregate Rent';
                                        } elseif ($scLeasingFeeType === 'Percentage of Gross Rent') {
                                            $scGrossRentVal = $scAllMeta['seller_leasing_gross_percentage'] ?? $scAllMeta['seller_leasing_gross_ross_percentage_rent'] ?? null;
                                            if ($scGrossRentVal) $scLeasingFeeDisplay = $scFmtPct($scGrossRentVal) . ' of Gross Rent';
                                        } elseif ($scLeasingFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                            $scLfParts = [];
                                            if (!empty($scAllMeta['seller_leasing_gross_flat_combo'])) $scLfParts[] = $scFmtMoney($scAllMeta['seller_leasing_gross_flat_combo']);
                                            if (!empty($scAllMeta['seller_leasing_gross_percentage_combo'])) $scLfParts[] = $scFmtPct($scAllMeta['seller_leasing_gross_percentage_combo']) . ' of Gross Lease Value';
                                            $scLeasingFeeDisplay = implode(' + ', array_filter($scLfParts)) ?: null;
                                        } elseif ($scLeasingFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                                            $scLfParts = [];
                                            if (!empty($scAllMeta['seller_leasing_gross_flat_net_combo'])) $scLfParts[] = $scFmtMoney($scAllMeta['seller_leasing_gross_flat_net_combo']);
                                            if (!empty($scAllMeta['seller_leasing_gross_percentage_net_combo'])) $scLfParts[] = $scFmtPct($scAllMeta['seller_leasing_gross_percentage_net_combo']) . ' of Net Aggregate Rent';
                                            $scLeasingFeeDisplay = implode(' + ', array_filter($scLfParts)) ?: null;
                                        } elseif (strtolower($scLeasingFeeType) === 'other' && !empty($scAllMeta['seller_leasing_gross_purchase_fee_other'])) {
                                            $scLeasingFeeDisplay = $scAllMeta['seller_leasing_gross_purchase_fee_other'];
                                        } elseif ($scLeasingFeeType) {
                                            $scLeasingFeeDisplay = $scLeasingFeeType;
                                        }

                                        // === C) Lease-Option Term fee displays ===
                                        $scLeaseValue   = $scAllMeta['lease_value'] ?? '';
                                        $scLeaseType2   = $scAllMeta['lease_type'] ?? '';
                                        $scPurchaseValue = $scAllMeta['purchase_value'] ?? '';
                                        $scPurchaseType2 = $scAllMeta['purchase_type'] ?? '';
                                        $scLeaseOptionFee = null;
                                        if ($scLeaseValue) {
                                            if (in_array($scLeaseType2, ['%', 'percent']) || str_contains((string)$scLeaseValue, '%')) {
                                                $scLeaseOptionFee = str_replace('%', '', $scLeaseValue) . '% of Total Purchase Price';
                                            } else {
                                                $scLeaseOptionFee = $scFmtMoney($scLeaseValue);
                                            }
                                        }
                                        $scPurchaseOptFee = null;
                                        if ($scPurchaseValue) {
                                            if (in_array($scPurchaseType2, ['%', 'percent']) || str_contains((string)$scPurchaseValue, '%')) {
                                                $scPurchaseOptFee = str_replace('%', '', $scPurchaseValue) . '% of Total Purchase Price';
                                            } else {
                                                $scPurchaseOptFee = $scFmtMoney($scPurchaseValue);
                                            }
                                        }

                                        // === D) Legal Terms fields ===
                                        $scEarlyTermAmt = $scAllMeta['early_termination_fee_amount'] ?? '';
                                        $scRetainerAmt  = $scAllMeta['retainer_fee_amount'] ?? '';
                                        $scRetainerApp  = $scAllMeta['retainer_fee_application'] ?? '';
                                        $scRetainedDep  = $scAllMeta['retained_deposits'] ?? '';
                                        $scAgencyTfDisplay = strtolower(trim($scAllMeta['agency_agreement_timeframe'] ?? '')) === 'other'
                                            ? ($scAllMeta['agency_agreement_custom'] ?? 'Other')
                                            : ($scAllMeta['agency_agreement_timeframe'] ?? '');

                                        $scHasBrokerComp =
                                            !empty($scAllMeta['purchase_fee_type']) || !empty($scAllMeta['commission_structure']) ||
                                            !empty($scAllMeta['nominal']) || !empty($scAllMeta['commission_structure_type']) ||
                                            !empty($scAllMeta['interested_purchase_fee_type']) || !empty($scAllMeta['interested_lease_option_agreement']) ||
                                            !empty($scAllMeta['protection_period']) || !empty($scAllMeta['early_termination_fee_option']) ||
                                            !empty($scAllMeta['retainer_fee_option']) || !empty($scAllMeta['retained_deposits']) ||
                                            !empty($scAllMeta['agency_agreement_timeframe']) || !empty($scAllMeta['brokerage_relationship']) ||
                                            !empty($scAllMeta['additional_details_broker']) || !empty($scAllMeta['additional_details']);
                                    @endphp

                                    <div class="counter-bid-card mb-3 p-3 border rounded mt-2">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                            <h6 class="mb-0">
                                                @if ($counterBid->user_id == Auth::id())
                                                    Your Counter Offer
                                                @else
                                                    Counter Offer from {{ data_get($counterBid, 'user.first_name') }} {{ data_get($counterBid, 'user.last_name') }}
                                                @endif
                                            </h6>
                                            <small class="text-muted">{{ optional($counterBid->created_at)->format('M j, Y g:i A') }}</small>
                                        </div>

                                        {{-- ── Broker Compensation & Agency Agreement Terms ── --}}
                                        @if ($scHasBrokerComp)
                                        <div class="mb-4">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa-solid fa-handshake me-2"></i>Broker Compensation &amp; Agency Agreement Terms
                                            </h6>

                                            {{-- A) Broker Compensation --}}
                                            @if (!empty($scAllMeta['purchase_fee_type']) || !empty($scAllMeta['commission_structure']) || !empty($scAllMeta['nominal']) || !empty($scAllMeta['commission_structure_type']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">A) Broker Compensation</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if (!empty($scAllMeta['purchase_fee_type']))
                                                    @php $scPFChg = $scIsChanged($scAllMeta['purchase_fee_type'], 'purchase_fee_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scPFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller's Broker Purchase Fee:</span> {{ $scPurchaseFeeDisplay }}{!! $scPFChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['nominal']))
                                                    @php $scNomChg = $scIsChanged($scAllMeta['nominal'], 'nominal'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scNomChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Nominal Consideration Fee:</span> {{ $scFmtMoney($scAllMeta['nominal']) }}{!! $scNomChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['commission_structure']))
                                                    @php $scCSChg = $scIsChanged($scAllMeta['commission_structure'], 'commission_structure'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scCSChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $scAllMeta['commission_structure'] }}{!! $scCSChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['commission_structure_type']) && $scBuyerBrokerFee)
                                                    @php $scCSTChg = $scIsChanged($scAllMeta['commission_structure_type'], 'commission_structure_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scCSTChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Fee:</span> {{ $scBuyerBrokerFee }}{!! $scCSTChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- B) Lease Terms --}}
                                            @if (!empty($scAllMeta['interested_purchase_fee_type']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">B) Lease Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scIPFTChg = $scIsChanged($scAllMeta['interested_purchase_fee_type'], 'interested_purchase_fee_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scIPFTChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $scAllMeta['interested_purchase_fee_type'] }}{!! $scIPFTChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower(trim($scAllMeta['interested_purchase_fee_type'])) === 'yes' && $scLeasingFeeDisplay)
                                                    @php $scLFChg = $scIsChanged($scLeasingFeeType, 'seller_leasing_fee_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scLFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller's Broker Leasing Fee:</span> {{ $scLeasingFeeDisplay }}{!! $scLFChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- C) Lease-Option Terms --}}
                                            @if (!empty($scAllMeta['interested_lease_option_agreement']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">C) Lease-Option Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scILOAChg = $scIsChanged($scAllMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scILOAChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $scAllMeta['interested_lease_option_agreement'] }}{!! $scILOAChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower(trim($scAllMeta['interested_lease_option_agreement'])) === 'yes')
                                                        @if ($scLeaseOptionFee)
                                                        @php $scLOFChg = $scIsChanged($scLeaseType2, 'lease_type'); @endphp
                                                        <li class="mb-1" style="font-size: 12px; {{ $scLOFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Compensation for Creating Lease-Option Agreement:</span> {{ $scLeaseOptionFee }}{!! $scLOFChg ? $scChangedBadge : '' !!}</li>
                                                        @endif
                                                        @if ($scPurchaseOptFee)
                                                        @php $scPOFChg = $scIsChanged($scPurchaseType2, 'purchase_type'); @endphp
                                                        <li class="mb-1" style="font-size: 12px; {{ $scPOFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $scPurchaseOptFee }}{!! $scPOFChg ? $scChangedBadge : '' !!}</li>
                                                        @endif
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- D) Legal Terms --}}
                                            @if (!empty($scAllMeta['early_termination_fee_option']) || !empty($scAllMeta['retainer_fee_option']) || $scRetainedDep || !empty($scAllMeta['protection_period']) || !empty($scAllMeta['agency_agreement_timeframe']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">D) Legal Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if (!empty($scAllMeta['early_termination_fee_option']))
                                                    @php $scETFChg = $scIsChanged($scAllMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scETFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ ucfirst($scAllMeta['early_termination_fee_option']) }}{!! $scETFChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower($scAllMeta['early_termination_fee_option']) === 'yes' && $scEarlyTermAmt)
                                                    @php $scETAChg = $scIsChanged($scEarlyTermAmt, 'early_termination_fee_amount'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scETAChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $scFmtMoney($scEarlyTermAmt) }}{!! $scETAChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @endif
                                                    @if (!empty($scAllMeta['retainer_fee_option']))
                                                    @php $scRFOChg = $scIsChanged($scAllMeta['retainer_fee_option'], 'retainer_fee_option'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRFOChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Retainer Fee:</span> {{ ucfirst($scAllMeta['retainer_fee_option']) }}{!! $scRFOChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower($scAllMeta['retainer_fee_option']) === 'yes' && $scRetainerAmt)
                                                    @php $scRAChg = $scIsChanged($scRetainerAmt, 'retainer_fee_amount'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRAChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Retainer Fee Amount:</span> {{ $scFmtMoney($scRetainerAmt) }}{!! $scRAChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (strtolower($scAllMeta['retainer_fee_option'] ?? '') === 'yes' && $scRetainerApp)
                                                    @php $scRAppChg = $scIsChanged($scRetainerApp, 'retainer_fee_application'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRAppChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Retainer Fee Application:</span> {{ $scRetainerApp }}{!! $scRAppChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @endif
                                                    @if ($scRetainedDep)
                                                    @php $scRDChg = $scIsChanged($scRetainedDep, 'retained_deposits'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRDChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $scFmtPct($scRetainedDep) }}{!! $scRDChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['protection_period']))
                                                    @php $scPPChg = $scIsChanged($scAllMeta['protection_period'], 'protection_period'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scPPChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $scAllMeta['protection_period'] }} days{!! $scPPChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['agency_agreement_timeframe']))
                                                    @php $scATChg = $scIsChanged($scAllMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scATChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller Agency Agreement Timeframe:</span> {{ $scAgencyTfDisplay }}{!! $scATChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- E) Brokerage Relationship --}}
                                            @if (!empty($scAllMeta['brokerage_relationship']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">E) Brokerage Relationship</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scBRChg = $scIsChanged($scAllMeta['brokerage_relationship'], 'brokerage_relationship'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scBRChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $scAllMeta['brokerage_relationship'] }}{!! $scBRChg ? $scChangedBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- F) Additional Terms --}}
                                            @if (!empty($scAllMeta['additional_details_broker']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">F) Additional Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scADBChg = $scIsChanged($scAllMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scADBChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Additional Terms:</span> {{ $scAllMeta['additional_details_broker'] }}{!! $scADBChg ? $scChangedBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- G) Referral Fee --}}
                                            @if ($auction->isCreatedByAgent() && !empty($scAllMeta['referral_fee_percent']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">G) Referral Fee</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scRefFeeChg = $scIsChanged($scAllMeta['referral_fee_percent'], 'referral_fee_percent'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRefFeeChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Referral Fee (%):</span> {{ $scAllMeta['referral_fee_percent'] }}%{!! $scRefFeeChg ? $scChangedBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- Additional Details --}}
                                        @if (!empty($scAllMeta['additional_details']))
                                        <div class="mb-3">
                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;"><i class="fa-solid fa-circle-info me-1"></i>Additional Details</div>
                                            @php $scADChg = $scIsChanged($scAllMeta['additional_details'], 'additional_details'); @endphp
                                            <div class="ps-3" style="font-size: 12px; {{ $scADChg ? $scChangedStyle : '' }}">{{ $scAllMeta['additional_details'] }}{!! $scADChg ? $scChangedBadge : '' !!}</div>
                                        </div>
                                        @endif

                                        {{-- Services Offered (Tenant-pattern: direct config loop) --}}
                                        @if (!empty($scCtrSvcsRaw) || !empty($scOtherSvcs))
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                            </h6>

                                            @foreach ($scPropConfig as $scCategory => $scCatSvcs)
                                                @php
                                                $scSelectedInCat = array_filter($scCatSvcs, fn($s) => isset($scSelectedNormalized[$scNormStr($s)]));
                                                @endphp
                                                @if (count($scSelectedInCat) > 0)
                                                <div class="mb-3">
                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $scCategory }}</div>
                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                        @foreach ($scCatSvcs as $scService)
                                                            @php
                                                            $scServiceNorm = $scNormStr($scService);
                                                            $scServiceDisplay = $scSelectedNormalized[$scServiceNorm] ?? null;
                                                            @endphp
                                                            @if ($scServiceDisplay !== null)
                                                                @if ($scCtrSvcIsAdded($scServiceDisplay))
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                    <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $scServiceDisplay }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                </li>
                                                                @else
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $scServiceDisplay }}</li>
                                                                @endif
                                                                @if (in_array(strtolower(trim($scService)), ['provide digital photo enhancements', 'provide digital enhancements to media assets']))
                                                                @php
                                                                    $scCtrPhotoEnhRaw = $scAllMeta['photo_enhancements'] ?? [];
                                                                    if (is_string($scCtrPhotoEnhRaw)) $scCtrPhotoEnhRaw = json_decode($scCtrPhotoEnhRaw, true) ?: [];
                                                                    $scCtrCustomEnh = $scAllMeta['custom_enhancement'] ?? '';
                                                                    $scEnhOrder = ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'];
                                                                @endphp
                                                                @if (!empty($scCtrPhotoEnhRaw))
                                                                <ul style="padding-left: 1.5rem; margin: 4px 0; list-style: disc;">
                                                                    @foreach ($scEnhOrder as $scEnh)
                                                                        @if (in_array($scEnh, $scCtrPhotoEnhRaw))
                                                                            @if ($scEnh === 'Other' && !empty($scCtrCustomEnh))
                                                                                <li style="font-size: 0.85rem;">{{ $scCtrCustomEnh }}</li>
                                                                            @elseif ($scEnh !== 'Other')
                                                                                <li style="font-size: 0.85rem;">{{ $scEnh }}</li>
                                                                            @endif
                                                                        @endif
                                                                    @endforeach
                                                                </ul>
                                                                @endif
                                                                @endif
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                </div>
                                                @endif
                                            @endforeach

                                            @if (!empty($scUnmappedSvcs))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                    @foreach ($scUnmappedSvcs as $scUnmappedSvc)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $scCtrSvcIsAdded((string)$scUnmappedSvc) ? 'background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;' : '' }}">
                                                        @if ($scCtrSvcIsAdded((string)$scUnmappedSvc))<i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>@endif{{ $scUnmappedSvc }}@if ($scCtrSvcIsAdded((string)$scUnmappedSvc)) <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>@endif
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif

                                            @if (!empty($scOtherSvcs))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                    @foreach ($scOtherSvcs as $scOtherSvc)
                                                    @if ($scOtherIsAdded($scOtherSvc))
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                        <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $scOtherSvc }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                    </li>
                                                    @else
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $scOtherSvc }}</li>
                                                    @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- Removed Services --}}
                                            @if (!empty($scCtrRemovedSvcs) || !empty($scOtherRemovedDisplay))
                                            <div class="mb-3 mt-2 p-3" style="background-color: #fff5f5; border-radius: 6px; border: 1px solid #f5c6cb;">
                                                <div class="fw-bold mb-1" style="color: #dc3545; font-size: 0.95rem;">
                                                    <i class="fa-solid fa-minus-circle me-1"></i>Removed Services
                                                </div>
                                                <ul class="services mb-0" style="margin-top: 0.5rem; padding-left: 1.2rem; list-style: none;">
                                                    @foreach ($scCtrRemovedSvcs as $svc)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>{{ $svc }}
                                                    </li>
                                                    @endforeach
                                                    @foreach ($scOtherRemovedDisplay as $svc)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>{{ $svc }}
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- Counter action banner (link to View Counter Terms page where actions live) --}}
                                        @if ($scShowCounterActions)
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                                <i class="fa-solid fa-right-left me-1"></i>
                                                @if ($scIsCounterFromOwner)
                                                    {{ trim($scOwnerFirst . ' ' . $scOwnerLast) }} has submitted a counter offer.
                                                @else
                                                    {{ trim($scAgentFirst . ' ' . $scAgentLast) }} has submitted a counter offer.
                                                @endif
                                            </div>
                                            <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                                <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                </a>
                                                @if ($scIsOwner)
                                                <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        {{-- Counter footer status --}}
                                        <div class="mt-3 pt-3 border-top">
                                            @if ($scCounterState === 'accepted')
                                            @if (Auth::id() == $scActorUserId)
                                            <div class="alert alert-success mb-0 py-1 small">
                                                ✅ This counter bid has been accepted.
                                            </div>
                                            @else
                                            <div class="alert alert-success mb-0 py-1 small">
                                                ✅ {{ trim($scActorFirst . ' ' . $scActorLast) }} accepted the counter bid.
                                            </div>
                                            @endif
                                            @elseif ($scCounterState === 'rejected')
                                            @if (Auth::id() == $scActorUserId)
                                            <div class="alert alert-danger mb-0 py-1 small">
                                                ❌ This counter bid has been rejected.
                                            </div>
                                            @else
                                            <div class="alert alert-danger mb-0 py-1 alert-font">
                                                ❌ {{ trim($scActorFirst . ' ' . $scActorLast) }} rejected the counter bid.
                                            </div>
                                            @endif
                                            @elseif ($scCounterState === '0')
                                            @if ($counterBid->user_id == Auth::id())
                                            <div class="alert alert-secondary mb-0 py-1 small">
                                                ⏳ Waiting for response from {{ $scIsCounterFromOwner ? trim($scAgentFirst . ' ' . $scAgentLast) : trim($scOwnerFirst . ' ' . $scOwnerLast) }}...
                                            </div>
                                            @else
                                            <div class="alert alert-light mb-0 py-1 small" style="font-size:13px;">
                                                ⏳ Counter bid from {{ trim($scCreatorFirst . ' ' . $scCreatorLast) }} is pending.
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
                        {{-- ===== END INLINE COUNTER BIDDING HISTORY ===== --}}

                        @if ($isListingOwner || $isBidOwner)
                        {{-- Private Data Modal - visible to listing owner OR bid owner (agent) --}}
                        @php
                            $rawState   = data_get($bid, 'accepted');
                            $_isTerminal = in_array((string)$rawState, ['accepted', 'rejected'], true);
                            $state      = (!$_isTerminal && $hasSellerCounter) ? 'countered' : ((!$rawState || $rawState === '0') ? '0' : (string) $rawState);
                            $isOwnerRow = ($auth_id == data_get($auction, 'user_id'));
                            $ownerFirst = data_get($auction, 'user.first_name', '');
                            $ownerLast  = data_get($auction, 'user.last_name', '');
                            $agentFirst = data_get($bid, 'user.first_name', '');
                            $agentLast  = data_get($bid, 'user.last_name', '');
                            $ownerId    = data_get($auction, 'user_id');
                        @endphp
                        <div class="modal fade"
                             id="privateDataModal{{ data_get($bid, 'id') }}"
                             tabindex="-1"
                             aria-labelledby="privateDataModalLabel{{ data_get($bid, 'id') }}"
                             aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content" style="border-radius: 10px; border: none;">
                                    <div class="modal-header text-white"
                                         style="background: #049399; border-bottom: none; padding: 20px;">
                                        <h5 class="modal-title"
                                            id="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                            style="font-weight: 600;">
                                            <i class="fa-solid fa-lock me-2"></i> Private Compensation &amp; Agreement Terms
                                        </h5>
                                    </div>
                                    <div class="modal-body" style="background: #fafafa; padding: 25px;">
                                        @include('partials.bid_detail_body.seller')
                                    </div>{{-- End modal-body --}}

                                    {{-- ===== TENANT-STYLE MODAL FOOTER ===== --}}
                                    <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                                        {{-- Confidential notice --}}
                                        <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                            <i class="fa-solid fa-shield-halved me-2"></i>
                                            <strong>Confidential:</strong> This information is private and only visible to you.
                                        </div>

                                        {{-- ── Listing owner: action buttons when bid is undecided ── --}}
                                        @if ($state === '0' && $isOwnerRow && !in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true))
                                            @if ($isTraditionalListing && $isExpired)
                                            <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                <i class="fa-solid fa-clock me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
                                            </div>
                                            @else
                                            <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                                                <form action="{{ route('acceptSABid') }}" method="post" style="margin: 0;"
                                                      onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                    <button type="submit" class="btn btn-success btn-accept" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa-solid fa-check me-1"></i> Accept Bid
                                                    </button>
                                                </form>
                                                <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}"
                                                   class="btn btn-primary btn-counter" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                    <i class="fa-solid fa-right-left me-1"></i> Counter Bid
                                                </a>
                                                <form action="{{ route('rejectSABid') }}" method="post" style="margin: 0;"
                                                      onsubmit="return confirm('Are you sure you want to reject this bid?');">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                    <button type="submit" class="btn btn-danger btn-reject" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa-solid fa-xmark me-1"></i> Reject Bid
                                                    </button>
                                                </form>
                                            </div>
                                            @endif
                                        @endif

                                        {{-- ── Accepted state ── --}}
                                        @if ($state === 'accepted')
                                        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                                            <i class="fa-solid fa-circle-check me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                This bid has been accepted.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted this bid.
                                            @endif
                                        </div>
                                        @php
                                            $absFooterBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
                                                ->where('agent_user_id', data_get($bid, 'user_id'))
                                                ->first();
                                        @endphp
                                        @if ($absFooterBidSummary && (Auth::id() == $ownerId || data_get($bid, 'user_id') == Auth::id()))
                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                            <a href="{{ route('accepted-bid-summary.view', $absFooterBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="fa-solid fa-file-lines me-1"></i> View Accepted Bid Summary
                                            </a>
                                            @if (data_get($bid, 'user_id') == Auth::id() && !$absFooterBidSummary->isAgentSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-signature me-1"></i> E-Sign Acknowledgement
                                            </a>
                                            @endif
                                            @if (Auth::id() == $ownerId && !$absFooterBidSummary->isOwnerSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-signature me-1"></i> Seller: E-Sign Acknowledgement
                                            </a>
                                            @endif
                                            @if ($absFooterBidSummary->isFullySigned())
                                            <a href="{{ route('accepted-bid-summary.download-pdf', $absFooterBidSummary->id) }}" class="btn btn-success btn-sm">
                                                <i class="fa-solid fa-download me-1"></i> Download Signed PDF
                                            </a>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- ── Rejected state ── --}}
                                        @elseif ($state === 'rejected')
                                        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                This bid has been rejected.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected this bid.
                                            @endif
                                        </div>

                                        {{-- ── Countered state ── --}}
                                        @elseif ($state === 'countered')
                                        @php $scFooterLatestFromOwner = $latestCounter && ($latestCounter->user_id == $ownerId); @endphp
                                        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                            <i class="fa-solid fa-right-left me-1"></i>
                                            @if (($scFooterLatestFromOwner && Auth::id() == $ownerId) || (!$scFooterLatestFromOwner && Auth::id() != $ownerId))
                                                <strong>Counter Offer Sent.</strong>
                                            @else
                                                <strong>Counter Offer Received.</strong>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                            @if (($scFooterLatestFromOwner && Auth::id() == $ownerId) || (!$scFooterLatestFromOwner && Auth::id() != $ownerId))
                                            {{-- Viewer sent latest counter — show View CT + Edit CT --}}
                                            <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                            </a>
                                            <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                            </a>
                                            @else
                                            {{-- Other party sent latest: View CT only — actions on View Counter Terms page --}}
                                            <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                            </a>
                                            @endif
                                        </div>

                                        {{-- ── Pending state ── --}}
                                        @elseif ($state === '0')
                                        @if (data_get($bid, 'user_id') == Auth::id())
                                        <div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                            ⏳ Waiting for a response from {{ trim($ownerFirst . ' ' . $ownerLast) }}...
                                        </div>
                                        @else
                                        <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                            ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
                                        </div>
                                        @endif
                                        @endif

                                        {{-- ── Close button ── --}}
                                        <div class="w-100 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                                                    style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">
                                                Close
                                            </button>
                                        </div>
                                    </div>{{-- End modal-footer --}}

                                </div>{{-- End modal-content --}}
                            </div>{{-- End modal-dialog --}}
                        </div>{{-- End modal --}}
                        @endif


                        @endforeach
                    </div>{{-- End accordion-item --}}

                </div>
            </div>
        </div>

                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
                </button>
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('seller.agent.auction.detail', @$auction->id), 200) }}
                    </div>
                    <div class="card-social">
                        <ul class="icons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-twitter"></i>
                            </a>
                            <a href="">
                                <i class="fa-brands fa-instagram"></i>
                            </a>
                            <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-pinterest"></i>
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-linkedin"></i>
                            </a>
                        </ul>
                        <p class="small opacity-8">Or copy link</p>
                        <div class="field">
                            <i class="fa-solid fa-link"></i>
                            <input type="text" readonly="" id="copylink"
                                value="{{ route('seller.agent.auction.detail', $auction->id) }}">
                            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                                style="min-width:60px;">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
    @if (@$auction->get->auction_length_days > 0)
        <script>
            var durations = '{{ $diff_d }}d{{ $diff_H }}h{{ $diff_I }}m{{ $diff_S }}s';
            $('.timer-d').timer({
                countdown: true,
                duration: durations,
                format: '%d'
            });
            $('.timer-h').timer({
                countdown: true,
                duration: durations,
                format: '%h'
            });
            $('.timer-m').timer({
                countdown: true,
                duration: durations,
                format: '%m'
            });
            $('.timer-s').timer({
                countdown: true,
                duration: durations,
                format: '%s'
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
