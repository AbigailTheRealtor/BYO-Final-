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

    .fa-dollar,
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

    /* Financing Details section - subsection headers */
    .financing-subsection-header {
        font-weight: 700 !important;
        color: #374151 !important;
        margin-bottom: 0;
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
            color: white;
            border: none;
        }

        .biding-btn {
            width: 31.5%;
        }

        /* Accept (green) */
        .btn-accept {
            background-color: #28a745;
        }

        .btn-accept:hover {
            background-color: #218838;
        }

        /* Reject (red) */
        .btn-reject {
            background-color: #dc3545;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }

        /* Counter (orange) */
        .btn-counter {
            background-color: #f0ad4e;
            color: #212529;
            /* dark text for better contrast */
        }

        .btn-counter:hover {
            background-color: #ec971f;
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
    </style>
@endpush
@section('content')
    @php
        $auth_id = auth()->user() ? auth()->user()->id : 0;
    @endphp
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
                                <div class="col-md-12 col-12 pt-2 fw-bold">Current Representation Status with Broker?
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


                            @if (@$auction->get->auction_time != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                  Auction Length:
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
                                Property Type:<span class="removeBold"> {{ $propType }}</span>
                            </div>
                            @php
                                $propertyStyleValue = @$auction->get->property_items;
                                $propertyStyleDisplay = '';
                                if (is_array($propertyStyleValue)) {
                                    $propertyStyleDisplay = implode(', ', $propertyStyleValue);
                                } elseif (is_string($propertyStyleValue) && !empty($propertyStyleValue)) {
                                    $decoded = json_decode($propertyStyleValue, true);
                                    $propertyStyleDisplay = is_array($decoded) ? implode(', ', $decoded) : $propertyStyleValue;
                                }
                            @endphp
                            @if (!empty($propertyStyleDisplay))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Property Style:<span class="removeBold"> {{ $propertyStyleDisplay }}</span>
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

                            @if ($propType !== 'Vacant Land')
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Property Condition:
                                @php
                                    $conditionData = @$auction->get->condition_prop_buyer;
                                    $conditionArray = null;
                                    if ($conditionData) {
                                        if (is_string($conditionData)) {
                                            $conditionArray = json_decode($conditionData, true);
                                        } elseif (is_array($conditionData)) {
                                            $conditionArray = $conditionData;
                                        }
                                    }
                                    $conditionProp = @$auction->get->condition_prop;
                                @endphp
                                @if (is_array($conditionArray) && !empty(array_filter($conditionArray)))
                                    @foreach (array_filter($conditionArray) as $item)
                                        @if ($item != 'Other')
                                            <span class="removeBold"> {{ $item }}</span>
                                        @elseif (@$auction->get->other_property_condition)
                                            <span class="removeBold"> {{ @$auction->get->other_property_condition }}</span>
                                        @endif
                                    @endforeach
                                @elseif (!empty($conditionProp))
                                    <span class="removeBold"> {{ $conditionProp }}</span>
                                @endif
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

                            @if (in_array($propType, ['Commercial', 'Business']))
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total SqFt:
                                        <span class="removeBold">
                                            @php
                                                $sqftVal = str_replace(',', '', @$auction->get->minimum_heated_square);
                                                echo is_numeric($sqftVal) ? number_format((float)$sqftVal, 0) : @$auction->get->minimum_heated_square;
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

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (
                                    @$auction->get->garageOptions != null &&
                                        @$auction->get->garageOptions != 'null' &&
                                        @$auction->get->garageOptions != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Garage<span class="removeBold"> ({{ @$auction->get->garageOptions }})</span>:
                                        @if (@$auction->get->garageOptions == 'Yes' || @$auction->get->garageOptions == 'Optional')
                                            <span class="removeBold"> {{ @$auction->get->custom_garage }}</span>
                                        @else
                                            <span class="removeBold"> {{ @$auction->get->garageOptions }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if (
                                    @$auction->get->carportOptions != null &&
                                        @$auction->get->carportOptions != 'null' &&
                                        @$auction->get->carportOptions != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Carport<span class="removeBold"> ({{ @$auction->get->carportOptions }})</span>:
                                        @if (@$auction->get->carportOptions == 'Yes')
                                            <span class="removeBold"> {{ @$auction->get->custom_carport }}</span>
                                        @else
                                            <span class="removeBold"> {{ @$auction->get->carportOptions }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Total SqFt:
                                        <span class="removeBold">
                                            @php
                                                $sqftVal = str_replace(',', '', @$auction->get->minimum_heated_square);
                                                echo is_numeric($sqftVal) ? number_format((float)$sqftVal, 0) : @$auction->get->minimum_heated_square;
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

                            @if (in_array($propType, ['Residential', 'Income']))
                                @php
                                    $appliancesData = @$auction->get->appliances;
                                    $appliancesList = [];
                                    if ($appliancesData) {
                                        $appliancesList = is_string($appliancesData) ? (json_decode($appliancesData, true) ?? []) : (array)$appliancesData;
                                    }
                                    $otherAppliances = @$auction->get->other_appliances ?? '';
                                @endphp
                                @if (!empty($appliancesList))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Appliances Included:
                                        @foreach ($appliancesList as $appliance)
                                            @if ($appliance !== 'Other')
                                                <span class="removeBold badge bg-secondary">{{ $appliance }}</span>
                                            @endif
                                        @endforeach
                                        @if (in_array('Other', $appliancesList) && !empty($otherAppliances))
                                            <span class="removeBold badge bg-secondary">{{ $otherAppliances }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if ($propType === 'Income' && @$auction->get->pool_needed !== null && @$auction->get->pool_needed !== '' && @$auction->get->pool_needed !== 'null')
                                @include('hire_seller_agent.partials.pool-display', ['auction' => $auction])
                            @endif

                            @if (@$auction->get->total_acreage != null && @$auction->get->total_acreage != '' && @$auction->get->total_acreage != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Total Acreage:
                                    <span class="removeBold">{{ @$auction->get->total_acreage }}</span>
                                </div>
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
                                    $garageParkingData = @$auction->get->garage_parking_spaces_option;
                                    $garageParkingList = [];
                                    if ($garageParkingData) {
                                        $garageParkingList = is_string($garageParkingData) ? (json_decode($garageParkingData, true) ?? []) : (array)$garageParkingData;
                                    }
                                    $otherParkingWrapper = @$auction->get->other_parking_space_wrapper ?? '';
                                @endphp
                                @if (!empty($garageParkingList))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Garage/Parking Features:
                                        @foreach ($garageParkingList as $gpItem)
                                            @if ($gpItem !== 'Other')
                                                <span class="removeBold badge bg-secondary">{{ $gpItem }}</span>
                                            @endif
                                        @endforeach
                                        @if (in_array('Other', $garageParkingList) && !empty($otherParkingWrapper))
                                            <span class="removeBold badge bg-secondary">{{ $otherParkingWrapper }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (@$auction->get->carport_needed != null)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                       Carport:
                                        <span class="removeBold">
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->carport_needed }}</span>
                                        </span>
                                    </div>
                                @endif
                                @if (@$auction->get->other_carport_needed != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                      Carport Spaces:
                                        <span class="removeBold">
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_carport_needed }}</span>
                                        </span>
                                    </div>
                                @endif
                                @if (@$auction->get->garage_needed != null)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                      Garage:
                                        <span class="removeBold">
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->garage_needed }}</span>
                                        </span>
                                    </div>
                                @endif
                                @if (@$auction->get->other_garage_needed != '')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                      Garage Spaces:
                                        <span class="removeBold">
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_garage_needed }}</span>
                                        </span>
                                    </div>
                                @endif
                            @endif

                            @if ($propType === 'Residential' && @$auction->get->pool_needed !== null && @$auction->get->pool_needed !== '' && @$auction->get->pool_needed !== 'null')
                                @include('hire_seller_agent.partials.pool-display', ['auction' => $auction])
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (@$auction->get->view_preference != null || @$auction->get->other_preferences != null)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        View:
                                        @foreach (@$auction->get->view_preference as $item)
                                            @if ($item !== 'Other')
                                            <span class="removeBold badge bg-secondary">{{ @$item }}</span>
                                            @endif
                                        @endforeach
                                        @if (@$auction->get->other_preferences)
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_preferences }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (@$auction->get->leasing_55_plus != null && @$auction->get->leasing_55_plus != '')
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Age-Restricted Community:
                                    <span class="removeBold">
                                        {{ @$auction->get->leasing_55_plus }}</span>
                                </div>
                                @endif
                            @endif

                            @if (!in_array($propType, ['Vacant Land']))
                                @if (@$auction->get->non_negotiable_amenities != null || @$auction->get->other_non_negotiable_amenities != null)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Amenities and Property Features:
                                        @if (gettype(@$auction->get->non_negotiable_amenities) == 'array')
                                            @foreach (@$auction->get->non_negotiable_amenities as $item)
                                                @if ($item !== 'Other')
                                                <span class="removeBold badge bg-secondary">{{ @$item }}</span>
                                                @endif
                                            @endforeach
                                        @endif
                                        @if (@$auction->get->other_non_negotiable_amenities)
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_non_negotiable_amenities }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (@$auction->get->pets != null)
                                <div class="col-md-12 col-12 pt-2 removeBold">
                                    <span class="fw-bold">Pets Allowed:</span>
                                    {{ @$auction->get->pets }}
                                </div>
                                @endif

                                @if (@$auction->get->pets === "Yes" || @$auction->get->pets === "1" || @$auction->get->pets === 1)
                                    @if (@$auction->get->number_of_pets != null)
                                    <div class="col-md-12 col-12 pt-2 removeBold">
                                        <span class="fw-bold">Number of Pets Allowed:</span>
                                        {{ @$auction->get->number_of_pets }}
                                    </div>
                                    @endif

                                    @if (@$auction->get->type_of_pets)
                                    <div class="col-md-12 col-12 pt-2 removeBold">
                                        <span class="fw-bold">Acceptable Pet Types:</span>
                                        {{ @$auction->get->type_of_pets }}
                                    </div>
                                    @endif

                                    @if (@$auction->get->weight_of_pets)
                                    <div class="col-md-12 col-12 pt-2 removeBold">
                                        <span class="fw-bold">Maximum Weight Per Pet (lbs):</span>
                                        {{ @$auction->get->weight_of_pets }}
                                    </div>
                                    @endif

                                    @php
                                        $petRestrictVal = @$auction->get->breed_of_pets ?: @$auction->get->breed_restrictions ?: @$auction->get->has_breed_restrictions;
                                    @endphp
                                    @if (!empty($petRestrictVal) && $petRestrictVal != 'null')
                                    <div class="col-md-12 col-12 pt-2 removeBold">
                                        <span class="fw-bold">Pet Restrictions:</span>
                                        {{ $petRestrictVal }}
                                    </div>
                                    @endif
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
                                    $assetsData = @$auction->get->assets;
                                    $assetsList = [];
                                    if ($assetsData) {
                                        $assetsList = is_string($assetsData) ? (json_decode($assetsData, true) ?? []) : (array)$assetsData;
                                    }
                                @endphp
                                @if (!empty($assetsList))
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Included Property or Business Assets:
                                        @foreach ($assetsList as $asset)
                                            <span class="removeBold badge bg-secondary">{{ $asset }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @php
                                    $minAnnualNet = @$auction->get->minimum_annual_net_income;
                                @endphp
                                @if (!empty($minAnnualNet) && $minAnnualNet != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Annual Net Income:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', $minAnnualNet), 2) }}</span>
                                    </div>
                                @endif

                                @php
                                    $minCapRate = @$auction->get->minimum_cap_rate;
                                @endphp
                                @if (!empty($minCapRate) && $minCapRate != 'null')
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Cap Rate:
                                        <span class="removeBold">{{ $minCapRate }}%</span>
                                    </div>
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

                         @if (@$auction->get->sale_provision != '' && @$auction->get->sale_provision != 'null')
                            <div class="col-md-12 col-12 pt-2  fw-bold">Special Sale
                                Provision:
                                @if (gettype(@$auction->get->sale_provision) == 'array')
                                    @foreach ($auction->get->sale_provision as $sale)
                                        @if ($sale != 'Other')
                                            <span class="removeBold badge bg-secondary">
                                                {{ $sale }}
                                            </span>
                                        @else
                                            <br>
                                            <ul class="leasing">
                                                <li style="font-size:16px;">
                                                    <span class="removeBold">
                                                        {{ $auction->get->sale_provision_other }}</span>
                                                </li>
                                            </ul>
                                        @endif
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

                        @if (!empty($financingArray))
                            <hr>
                            <div class="col-12">
                                <div class="card-header section-header">
                                    <h4 class="section-title">Financing Details</h4>
                                </div>
                            </div>

                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Offered Financing/Currency:
                                @foreach ($financingArray as $financingItem)
                                    @if ($financingItem != 'Other')
                                        <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $financingItem) }}</span>
                                    @endif
                                @endforeach
                                @if (in_array('Other', $financingArray) && $displayOtherFinancing)
                                    <span class="removeBold badge bg-secondary">{{ $displayOtherFinancing }}</span>
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
                                            if (!empty($val)) { $hasAnyData = true; break; }
                                            if (isset($field['alt_keys'])) {
                                                foreach ($field['alt_keys'] as $altKey) {
                                                    if (!empty($getVal($altKey))) { $hasAnyData = true; break 2; }
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
                                                $showField = !empty($fieldVal);
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
                                                            @endphp
                                                            @if ($fieldVal === $otherVal && !empty($otherText))
                                                                <span class="removeBold badge bg-secondary">{{ $otherText }}</span>
                                                            @else
                                                                <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
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
                                                        @default
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                    @endswitch
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                @endif
                            @endforeach
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
                        @endphp

                        <div class="col-md-12 col-12 pt-2">
                            @if ($hasMatchedServices)
                                @foreach ($categories as $categoryName => $categoryServices)
                                    @php
                                        $matchedServices = array_filter($categoryServices, function($service) use ($allServicesCanon, $canon) {
                                            return in_array($canon($service), $allServicesCanon);
                                        });
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
                        </div>
                        @endif

                        <hr>
                        @if (@$auction->get->additional_details != null)
                            <div class="card-header section-header">
                                <h4 class="section-title">Additional Details:</h4>
                            </div>

                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Additional Details:<span
                                    class="removeBold">{{ $auction->get->additional_details ?? '' }}</span>
                            </div>
                        @endif

                        <hr />
                        <div class="card-header section-header">
                            <h4 class="section-title">Broker Compensation & Agency Agreement Terms</h4>
                        </div>

                        <div class="broker-compensation-section">

                        <!-- Broker Compensation Sub-section -->
                        <h5 class="mt-3 mb-2"><strong>Broker Compensation:</strong></h5>

                        @php
                            $hasPurchaseFee = !empty(@$auction->get->purchase_fee_type);
                            $hasLeaseFee = !empty(@$auction->get->lease_fee_type);
                        @endphp
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

                        @if (@$auction->get->commission_structure_type != null)
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
                                $sellerLeasingFee = $fmtPercent(@$auction->get->seller_leasing_gross_month_rent) . " of Month's Rent";
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
                                $leaseType = @$auction->get->lease_type ?? '$';
                                if ($leaseType === '%') {
                                    $leaseCompDisplay = $leaseCompDisplay . '% of Total Purchase Price';
                                } else {
                                    $leaseCompDisplay = $fmtMoney($leaseCompDisplay);
                                }
                            @endphp
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Compensation (When Option Is Created):
                                <span class="removeBold">{{ $leaseCompDisplay }}</span>
                            </div>
                            @endif
                            @if (@$auction->get->purchase_value != '' && @$auction->get->purchase_value != 'null')
                            @php
                                $purchaseCompDisplay = @$auction->get->purchase_value;
                                $purchaseType = @$auction->get->purchase_type ?? '$';
                                if ($purchaseType === '%') {
                                    $purchaseCompDisplay = $purchaseCompDisplay . '% of Total Purchase Price';
                                } else {
                                    $purchaseCompDisplay = $fmtMoney($purchaseCompDisplay);
                                }
                            @endphp
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Compensation (If Purchase Option Is Exercised):
                                <span class="removeBold">{{ $purchaseCompDisplay }}</span>
                            </div>
                            @endif
                        @endif
                        @endif

                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                        <!-- Legal Terms Sub-section -->
                        <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>

                        @if (@$auction->get->protection_period != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Protection Period Timeframe:
                            <span class="removeBold">{{ $auction->get->protection_period }} Days</span>
                        </div>
                        @endif

                        @if (@$auction->get->early_termination_fee_option != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Early Termination Fee:
                            <span class="removeBold">{{ ucfirst(strtolower(@$auction->get->early_termination_fee_option)) }}</span>
                        </div>
                        @if (in_array(strtolower(@$auction->get->early_termination_fee_option ?? ''), ['yes', '1', 'true']) && @$auction->get->early_termination_fee_amount)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Termination Fee Amount:
                            <span class="removeBold">{{ $fmtMoney(@$auction->get->early_termination_fee_amount) }}</span>
                        </div>
                        @endif
                        @endif

                        @if (!empty(@$auction->get->retainer_fee_option))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Retainer Fee:
                            <span class="removeBold">{{ in_array(strtolower(@$auction->get->retainer_fee_option ?? ''), ['yes']) ? 'Yes' : 'No' }}</span>
                        </div>
                        @if (in_array(strtolower(@$auction->get->retainer_fee_option ?? ''), ['yes']))
                            @if (!empty(@$auction->get->retainer_fee_amount))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Retainer Fee Amount:
                                <span class="removeBold">{{ $fmtMoney(@$auction->get->retainer_fee_amount) }}</span>
                            </div>
                            @endif
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

                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                        <!-- Brokerage Relationship Sub-section -->
                        <h5 class="mt-3 mb-2"><strong>Brokerage Relationship:</strong></h5>

                        @if (@$auction->get->brokerage_relationship != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Brokerage Relationship:
                            <span class="removeBold">{{ $auction->get->brokerage_relationship ?? '' }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->additional_details_broker != null && @$auction->get->additional_details_broker != '')
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>
                        <div class="col-md-12 col-12 pt-2 removeBold">
                            {{ @$auction->get->additional_details_broker }}
                        </div>
                        @endif

                        </div>

                        <hr />
                        <div class="card-header section-header">
                            <h4 class="section-title">Seller Info</h4>
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
                            <img class="w-25"
                                src="{{ $auser->avatar ? asset('images/avatar/' . $auser->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png' }}"
                                alt="">
                            <div>
                                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>User
                                            Details</b></a><span></span>
                                    <span class="start opacity-50">
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
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

                @php
                    $auth_id = auth()->id();
                @endphp
                @if($auth_id && $auth_id == @$auction->user_id)
                <div class="mb-2">
                    <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => 'seller']) }}" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-edit me-1"></i> Edit Listing
                    </a>
                </div>
                @endif
                <hr>

                 @inject('carbon', 'Carbon\Carbon')

    @php
    // 🕒 Auction start time (when auction began)
    $start_time = $auction->get->created_at ?? $auction->created_at ?? $carbon::now();

    // 🔹 Get auction_time value
    $auction_time = trim($auction->get->auction_time ?? '');
    $useAuctionTime = !empty($auction_time) && strtolower($auction_time) !== 'null';

    if ($useAuctionTime) {
    // 🔸 CASE 1: Use auction_time (e.g. "14 Days", "2 Weeks", "5 Hours")
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
    } else {
    // 🔸 CASE 2: Fallback to expiration_date (Traditional)
    $expiration = !empty($auction->get->expiration_date)
    ? $carbon::parse($auction->get->expiration_date)
    : null;
    }

    // 🧾 Determine if expired
    $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;

    // ⏱ Calculate remaining time if not expired
    if ($expiration && !$isExpired) {
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


        {{-- ⏳ Countdown Timer --}}
        @if ($expiration && !$isExpired)
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
        <div class="alert alert-warning text-center mt-2 mb-0 p-2">
            <strong>Bidding Ended</strong>
        </div>
        @endif



        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp

        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
        @if (!$isExpired)
        @if ($userHasBid)
        {{-- User already placed a bid --}}
        <div class="alert alert-info text-center mb-2">
            <i class="fa fa-check-circle"></i> You have already placed a bid
        </div>
        <button class="btn w-100 btn-secondary" disabled>
            <span class="bid">Bid Already Placed</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>

        {{-- Optional: Allow editing their bid --}}
        <!-- <button class="btn w-100 btn-outline-primary mt-2"
                onclick="window.location='{{ route('agent.tenant.agent.auction.bid', @$auction->id) }}';">
                <i class="fa fa-edit"></i> Edit Your Bid
            </button> -->
        @else
        {{-- User can place a bid --}}
        <button class="btn w-100 bid-btn"
            onclick="window.location='{{ route('agent.tenant.agent.auction.bid', @$auction->id) }}';">
            <span class="bid">Bid Now</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>
        @endif

        @endif

        @if (@$auction->sold)
        <span class="badge bg-danger w-100 mt-2">Sold</span>
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
                <div class="card higestBider">
                    <div class="card-body card-body-padding">
                        @if ($lowest_bidder)
                            <p><b>{{ $lowest_bidder->user->first_name ?? '' }}</b> is the lowest bidder.</p>
                            {{-- <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p> --}}
                        @else
                            <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">

                                @foreach (@$auction->bids as $bid)
                                    <!-- Item loop -->
                                    <div class="accordion-header" style="cursor: pointer;" role="button" data-bs-toggle="collapse"
                                        data-bs-target="#item{{ data_get($bid, 'id') }}" aria-expanded="false"
                                        aria-controls="item{{ data_get($bid, 'id') }}">
                                        <div class="d-flex small mr-0 text-center p-2 border rounded mb-1" style="background: #f8f9fa;">
                                            <div class="col-1">
                                                <span class="badge bg-primary">{{ $loop->iteration }}</span>
                                            </div>
                                            <div class="col-5">
                                                {{ data_get($bid, 'user.first_name', '') }}
                                            </div>
                                            <div class="col-3 text-right">
                                                {{ data_get($bid, 'get.agent_fee', data_get($bid, 'agent_fee', '')) }}
                                            </div>
                                            <div class="col-3 d-flex justify-content-end">
                                                <span class="text-primary">Terms ↓</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="item{{ data_get($bid, 'id') }}" class="accordion-collapse collapse"
                                        aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                        <div class="accordion-body-padding" style="padding: 20px;">
                                            <div id="bidding_history_data">
                                                <div>
                                                    <!-- Agent Information -->
                                                    <p class="d-flex justify-content-between  align-items-center small"
                                                        style="color: #333;">
                                                        <span>
                                                            Agent First Name:
                                                        </span>
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.first_name', '') }}
                                                        </span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Year Agent Got Licensed:
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.year_licensed', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- Why Hire You -->
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Why Should You Be Hired as Their Agent?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.why_hire_you', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- What Sets You Apart -->
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        What Sets You Apart From Other Agents?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.what_sets_you_apart', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- Marketing Strategy -->
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        What Is Your Marketing Strategy?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.marketing_plan', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- Services Offered -->
                                                    @php $servicesList = (array) data_get($bid,'get.services',[]); @endphp
                                                    @if (!empty($servicesList))
                                                        <div>
                                                            <label style="font-size: large;">Services Offered by the
                                                                Agent:</label>
                                                            <ul class="services services-offered">
                                                                @foreach ($servicesList as $service)
                                                                    @if ($service == 'Other')
                                                                        @continue
                                                                    @endif
                                                                    <li class="alert-font"
                                                                        style="font-size: 16px; margin-top:15px;">
                                                                        {{ $service }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif

                                                    @php $otherServicesList = (array) data_get($bid,'get.other_services',[]); @endphp
                                                    @if (!empty($otherServicesList))
                                                        <div>
                                                            <label style="font-size: large;">Other Services Offered by the
                                                                Agent:</label>
                                                            <ul class="services services-offered">
                                                                @foreach ($otherServicesList as $service)
                                                                    <li style="font-size: 16px; margin-top:15px;">
                                                                        {{ $service }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif

                                                    <!-- Counter Bids -->
                                                    @php
                                                        $counterBids = \App\Models\TenantCounterBidding::with(
                                                            'meta',
                                                            'user',
                                                        )
                                                            ->where('tenant_agent_auction_bid_id', data_get($bid, 'id'))
                                                            ->orderBy('created_at', 'desc')
                                                            ->get();
                                                    @endphp

                                                    @if ($counterBids->count() > 0)
                                                        <div class="counter-bids-section mt-4">
                                                            <!-- Counter Bids Accordion Header -->
                                                            <div class="accordion" type="button"
                                                                data-bs-toggle="collapse"
                                                                data-bs-target="#counterBids{{ data_get($bid, 'id') }}"
                                                                aria-expanded="false"
                                                                aria-controls="counterBids{{ data_get($bid, 'id') }}">
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                                                    <h5 class="mb-0" style="color: #2c3e50;">Counter
                                                                        Bidding History</h5>
                                                                    <div class="d-flex align-items-center">
                                                                        <span
                                                                            class="badge bg-secondary me-2">{{ $counterBids->count() }}
                                                                            counter offers</span>
                                                                        <span class="accordion-arrow">↓</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Counter Bids Accordion Content -->
                                                            <div id="counterBids{{ data_get($bid, 'id') }}"
                                                                class="accordion-collapse collapse"
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
                                                                                [null, 0, '0'],
                                                                                true,
                                                                            )
                                                                                ? '0'
                                                                                : (string) $rawBidState;

                                                                            $rawCounterState = data_get(
                                                                                $counterBid,
                                                                                'accepted',
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
                                                                                $counterState === '0'
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

                                                                            @php $allMeta = $counterBid->getAllMeta(); @endphp
                                                                            @if (!empty($allMeta['commission_structure']))
                                                                                <div class="mb-2"><strong>Commission
                                                                                        Structure:</strong>
                                                                                    {{ $allMeta['commission_structure'] }}
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($allMeta['lease_fee_type']))
                                                                                <div class="mb-2"><strong>Lease Fee
                                                                                        Type:</strong>
                                                                                    {{ $allMeta['lease_fee_type'] }}</div>
                                                                            @endif
                                                                            @if (!empty($allMeta['lease_fee_flat']))
                                                                                <div class="mb-2"><strong>Lease Flat
                                                                                        Fee:</strong>
                                                                                    ${{ number_format((float)str_replace(',', '', $allMeta['lease_fee_flat']), 2) }}
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($allMeta['lease_fee_percentage']))
                                                                                <div class="mb-2"><strong>Lease
                                                                                        Percentage:</strong>
                                                                                    {{ $allMeta['lease_fee_percentage'] }}%
                                                                                </div>
                                                                            @endif

                                                                            <!-- Services Offered -->
                                                                            @php
                                                                                $services = is_string(
                                                                                    $allMeta['services'],
                                                                                )
                                                                                    ? json_decode(
                                                                                        $allMeta['services'],
                                                                                        true,
                                                                                    )
                                                                                    : $allMeta['services'];
                                                                            @endphp

                                                                            @if (!empty($services))
                                                                                <div style="margin-top: 20px;">
                                                                                    <label
                                                                                        style="font-size: 18px; font-weight: bold; display: block; margin-bottom: 10px;">
                                                                                        Services:
                                                                                    </label>
                                                                                    <div
                                                                                        style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                                                        @foreach ($services as $service)
                                                                                            @if ($service == 'Other')
                                                                                                @continue
                                                                                            @endif
                                                                                            <span
                                                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px;
                                                                                                    border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
                                                                                                {{ $service }}
                                                                                            </span>
                                                                                        @endforeach
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                            @php
                                                                                $other_services = is_string(
                                                                                    $allMeta['other_services'],
                                                                                )
                                                                                    ? json_decode(
                                                                                        $allMeta['other_services'],
                                                                                        true,
                                                                                    )
                                                                                    : $allMeta['other_services'];
                                                                            @endphp
                                                                            @if (!empty($other_services))
                                                                                <div style="margin-top: 20px;">
                                                                                    <label
                                                                                        style="font-size: 18px; font-weight: bold; display: block; margin-bottom: 10px;">
                                                                                        Other Services:
                                                                                    </label>
                                                                                    <div
                                                                                        style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                                                        @foreach ($other_services as $other_service)
                                                                                            <span
                                                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px;
                                                                                                    border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
                                                                                                {{ $other_service }}
                                                                                            </span>
                                                                                        @endforeach
                                                                                    </div>
                                                                                </div>
                                                                            @endif



                                                                            <!-- Counter actions (only when both pending & viewer is the other party) -->


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

                                                                            @if ($showCounterActions)
                                                                                <div
                                                                                    class="counter-response-buttons mt-3 pt-3 border-top">
                                                                                    <h6>Respond to this Counter Offer:</h6>
                                                                                    <div
                                                                                        class="d-flex gap-3 flex-wrap justify-content-between">
                                                                                        <form class="d-inline"
                                                                                            action="{{ route('tenant.hire.agent.auction.counter.bid.accept') }}"
                                                                                            method="post">
                                                                                            @csrf
                                                                                            <input type="hidden"
                                                                                                name="auction_id"
                                                                                                value="{{ data_get($auction, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="bid_id"
                                                                                                value="{{ data_get($bid, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="counter_bid_id"
                                                                                                value="{{ data_get($counterBid, 'id') }}">
                                                                                            <button type="submit"
                                                                                                class="btn-custom btn-accept"
                                                                                                style="font-size:16px">Accept</button>
                                                                                        </form>

                                                                                        <form class="d-inline"
                                                                                            action="{{ route('tenant.hire.agent.auction.counter.bid.reject') }}"
                                                                                            method="post">
                                                                                            @csrf
                                                                                            <input type="hidden"
                                                                                                name="auction_id"
                                                                                                value="{{ data_get($auction, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="bid_id"
                                                                                                value="{{ data_get($bid, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="counter_bid_id"
                                                                                                value="{{ data_get($counterBid, 'id') }}">
                                                                                            <button type="submit"
                                                                                                class="btn-custom btn-reject"
                                                                                                style="font-size:16px">Reject</button>
                                                                                        </form>

                                                                                        <form class="d-inline"
                                                                                            action="{{ route('tenant.hire.agent.auction.bid.counter') }}"
                                                                                            method="post">
                                                                                            @csrf
                                                                                            <input type="hidden"
                                                                                                name="auction_id"
                                                                                                value="{{ data_get($auction, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="bid_id"
                                                                                                value="{{ data_get($bid, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="counter_bid_id"
                                                                                                value="{{ data_get($counterBid, 'id') }}">
                                                                                            <button type="submit"
                                                                                                class="btn-custom btn-counter"
                                                                                                style="font-size:16px">Counter</button>
                                                                                        </form>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- ===== Counter footer status (names for OTHER PARTY only) ===== -->
                                                                            <div class="mt-3 pt-3 border-top">
                                                                                @if ($counterState === 'accepted')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        {{-- Actor sees simple message --}}
                                                                                        <div
                                                                                            class="alert alert-success  mb-0 py-1 small">
                                                                                            ✅
                                                                                            This counter bid has been
                                                                                            accepted.
                                                                                        </div>
                                                                                    @else
                                                                                        {{-- Other party (creator) sees actor name --}}
                                                                                        <div
                                                                                            class="alert alert-success mb-0 py-1 small">
                                                                                            ✅
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            accepted the counter bid.</div>
                                                                                    @endif
                                                                                @elseif ($counterState === 'rejected')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 small">
                                                                                            ❌ This
                                                                                            counter bid has been rejected.
                                                                                        </div>
                                                                                    @else
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 alert-font">
                                                                                            ❌
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            rejected the counter bid.</div>
                                                                                    @endif
                                                                                @elseif ($counterState === '0')
                                                                                    @if ($counterBid->user_id == Auth::id())
                                                                                        <div
                                                                                            class="alert alert-secondary mb-0 py-1 small">
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
                                                                            <!-- ===== /Counter footer status ===== -->
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{-- Main Bid Actions --}}
                                                    @php
                                                        $rawState = data_get($bid, 'accepted', '0');
                                                        $state = in_array($rawState, [null, 0, '0'], true)
                                                            ? '0'
                                                            : (string) $rawState;
                                                        $isOwnerRow = data_get($auction, 'user_id') == $auth_id;

                                                        // Names for messages
                                                        $ownerFirst = data_get($auction, 'user.first_name', '');
                                                        $ownerLast = data_get($auction, 'user.last_name', '');
                                                        $agentFirst = data_get($bid, 'user.first_name', '');
                                                        $agentLast = data_get($bid, 'user.last_name', '');

                                                        // For main bid accepted/rejected: actor is ALWAYS owner
                                                        $ownerId = data_get($auction, 'user_id');
                                                    @endphp

                                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                                        {{-- When main bid pending, OWNER can Accept/Reject/Counter --}}
                                                        @if ($state === '0' && $isOwnerRow && !data_get($auction, 'is_sold'))
                                                            <div class="biding-btn">
                                                                <form
                                                                    action="{{ route('tenant.hire.agent.auction.bid.accept') }}"
                                                                    method="post">
                                                                    @csrf
                                                                    <input type="hidden" name="auction_id"
                                                                        value="{{ data_get($auction, 'id') }}">
                                                                    <input type="hidden" name="bid_id"
                                                                        value="{{ data_get($bid, 'id') }}">
                                                                    <button type="submit"
                                                                        class="btn-custom btn-accept">Accept</button>
                                                                </form>
                                                            </div>
                                                            <div class="biding-btn">
                                                                <form
                                                                    action="{{ route('tenant.hire.agent.auction.bid.reject') }}"
                                                                    method="post">
                                                                    @csrf
                                                                    <input type="hidden" name="auction_id"
                                                                        value="{{ data_get($auction, 'id') }}">
                                                                    <input type="hidden" name="bid_id"
                                                                        value="{{ data_get($bid, 'id') }}">
                                                                    <button type="submit"
                                                                        class="btn-custom btn-reject">Reject</button>
                                                                </form>
                                                            </div>
                                                            <div class="biding-btn">
                                                                <form
                                                                    action="{{ route('tenant.hire.agent.auction.bid.counter') }}"
                                                                    method="post">
                                                                    @csrf
                                                                    <input type="hidden" name="auction_id"
                                                                        value="{{ data_get($auction, 'id') }}">
                                                                    <input type="hidden" name="bid_id"
                                                                        value="{{ data_get($bid, 'id') }}">
                                                                    <button type="submit"
                                                                        class="btn-custom btn-counter">Counter</button>
                                                                </form>
                                                            </div>
                                                        @endif

                                                        <!-- ===== Main bid footer status (names for OTHER PARTY only) ===== -->
                                                        @if ($state === 'accepted')
                                                            @if (Auth::id() == $ownerId)
                                                                {{-- Owner (actor) sees simple message --}}
                                                                <div
                                                                    class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                                    ✅ This bid
                                                                    has been accepted.</div>
                                                            @else
                                                                {{-- Agent (other party) sees owner's name --}}
                                                                <div
                                                                    class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                                    ✅
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted
                                                                    the
                                                                    bid.</div>
                                                            @endif
                                                        @elseif ($state === 'rejected')
                                                            @if (Auth::id() == $ownerId)
                                                                <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                                    ❌ This bid
                                                                    has been rejected.</div>
                                                            @else
                                                                <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                                    ❌
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected
                                                                    the
                                                                    bid.</div>
                                                            @endif
                                                        @elseif ($state === '0')
                                                            @if (data_get($bid, 'user_id') == Auth::id())
                                                                <div
                                                                    class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                                                    ⏳
                                                                    Waiting for response from
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }}...</div>
                                                            @else
                                                                <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">⏳
                                                                    Bid from
                                                                    {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
                                                                </div>
                                                            @endif
                                                        @endif
                                                        <!-- ===== /Main bid footer status ===== -->
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if ($auction->bids->count() > 0)
                                    <div class="alert alert-warning mt-3 p-2 small">
                                        <strong> 🛡️ Compliance Note: </strong> No Broker Compensation, Agency Agreement
                                        Terms,
                                        or Counter Offers are ever displayed publicly. These must remain private to avoid
                                        antitrust/commission advertising issues.
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>
                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa fa-user"></i> </span>
                </button>
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('tenant.agent.view.auction.view', @$auction->id), 200) }}
                    </div>
                    <div class="card-social">
                        <ul class="icons">
                            <a href="">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="">
                                <i class="fab fa-pinterest"></i>
                            </a>
                            <a href="">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </ul>
                        <p class="small opacity-8">Or copy link</p>
                        <div class="field">
                            <i class="fa fa-link"></i>
                            <input type="text" readonly="" id="copylink"
                                value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
                            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                                style="min-width:60px;">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="container buyerOfferContentDetails">
        <h3 class="text-600 mb-4">Recommended For You</h3>
        <div class="cardsDetails row  justify-content-start">
            <!-- Card 1 -->
            <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                <div class="card ">
                    <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                        class="card-img-top" alt="...">
                    <div class="card-body pb-2 pt-2">
                        <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                        <div class="houseDetails mb-1">
                            <span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon"
                                        width="15"><b>
                                        4</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon"
                                        width="15"><b>
                                        2</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}"
                                        alt="bed icon" width="15"><b> 1,643 </b>Sq Ft</span>
                            </span>
                            - House for sale
                        </div>
                        <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span
                                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
                        <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg><b>28d 03:15:29</b></p>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-6 left">
                                <!-- Barcode  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                            </div>
                            <div class="col-6 right text-end">
                                <b>$1,000</b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                <div class="card ">
                    <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                        class="card-img-top" alt="...">
                    <div class="card-body">
                        <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                        <div class="houseDetails">
                            <span>
                                <span><b>4</b> bds</span>
                                <span><b>2</b> ba</span>
                                <span><b>1,643</b> sqft</span>
                            </span>
                            - House for sale
                        </div>
                        <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span
                                class="float-end"><span><b>MLS
                                        ID</b></span> <span>#12345</span></span></p>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-6 left">
                                <!-- Barcode  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <!-- Message  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                            </div>
                            <div class="col-6 right text-end">
                                <b>$1,000</b>
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
@endpush
