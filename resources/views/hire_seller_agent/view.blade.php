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

        /* Service category title styling */
        .service-category-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        /* Base button style (matches Tenant) */
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

        /* Accept (green) */
        .btn-accept {
            background-color: #28a745 !important;
            color: #ffffff !important;
        }

        .btn-accept:hover {
            background-color: #218838 !important;
        }

        /* Reject (red) */
        .btn-reject {
            background-color: #dc3545 !important;
            color: #ffffff !important;
        }

        .btn-reject:hover {
            background-color: #c82333 !important;
        }

        /* Counter (blue) */
        .btn-counter {
            background-color: #0d6efd !important;
            color: #ffffff !important;
        }

        .btn-counter:hover {
            background-color: #0b5ed7 !important;
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
                            'Active'       => 'fa-check-circle',
                            'Pending'      => 'fa-clock',
                            'Hired Agent'  => 'fa-user',
                            'Expired'      => 'fa-times-circle',
                        ];
                        $_statusStyle = $_statusStyles[$auction->status] ?? 'background-color:#6b7280;color:#fff;';
                        $_statusIcon  = $_statusIcons[$auction->status] ?? 'fa-circle';
                    @endphp
                    <span class="badge" style="{{ $_statusStyle }} font-size:0.875rem;border-radius:9999px;padding:0.25rem 0.75rem;font-weight:500;box-shadow:0 1px 2px rgba(0,0,0,.05);"><i class="fa {{ $_statusIcon }} me-1"></i>Status: {{ $auction->status }}</span>
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
                    {{-- PDF download button hidden from UI (backend route preserved) --}}
                </div>
                @endif
                <hr>

                 @inject('carbon', 'Carbon\Carbon')

    @php
    // 🔹 Determine listing type: Traditional vs Bidding Period
    $listingType = trim($auction->get->auction_type ?? '');
    $isTraditionalListing = (strtolower($listingType) === 'traditional' || empty($listingType));
    $isBiddingPeriodListing = (strtolower($listingType) === 'bidding period');

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

    // 🔹 For Bidding Period: timer active means actions are locked
    // For Traditional: actions are always unlocked (no timer restriction)
    $isBiddingTimerActive = $isBiddingPeriodListing && $expiration && !$isExpired;
    $canTakeAction = $isTraditionalListing || ($isBiddingPeriodListing && $isExpired);

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
            <div class="alert alert-warning text-center mt-2 mb-0 p-2">
                <strong>Bidding Ended</strong>
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
            <i class="fa fa-check-circle"></i> You have already placed a bid
        </div>
        <button class="btn w-100 btn-secondary" disabled>
            <span class="bid">Bid Already Placed</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>

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
            <i class="fa fa-trophy"></i> <strong>An agent has been hired</strong>
        </div>
        <button class="btn w-100 btn-success" disabled>
            <span class="bid">Hired Agent</span>
        </button>
        @elseif($auction->status === 'Pending')
        <div class="alert alert-warning text-center mb-2">
            <i class="fa fa-pause-circle"></i> <strong>This listing is pending &mdash; not accepting new bids</strong>
        </div>
        <button class="btn w-100 btn-warning" disabled>
            <span class="bid">Pending</span>
        </button>
        @else
        <div class="alert alert-warning text-center mb-2">
            <strong>Bidding Period Ended</strong>
        </div>
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
        @php
            // === SELLER BID SECTION: VARIABLE SETUP (matches Tenant pattern) ===
            $bidsByOrder = $auction->bids->sortBy('created_at')->values();
            $agentNumberMap = [];
            foreach ($bidsByOrder as $index => $orderedBid) {
                $agentNumberMap[$orderedBid->id] = $index + 1;
            }
            $lastBidderNumber = null;
            if ($lowest_bidder) {
                $lastBidderNumber = $agentNumberMap[$lowest_bidder->id] ?? null;
            }
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            $isAgentViewer  = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
            // Traditional: agents cannot see other agents' bid info
            $canSeeBidSummary = $isListingOwner || !$isAgentViewer || $isBiddingPeriodListing;
            $otherBidsExist   = $auction->bids->where('user_id', '!=', $auth_id)->count() > 0;
        @endphp

        {{-- Last Bidder Info (hidden for Bidding Period to avoid timing hints to agents) --}}
        @if ($canSeeBidSummary && !($isBiddingPeriodListing && $isAgentViewer && !$isListingOwner))
            @if ($lowest_bidder && $lastBidderNumber)
            <p class="mb-3"><b>Agent {{ $lastBidderNumber }}</b> was the last bidder.</p>
            @else
            <p class="mb-3">No one has bid on this auction.</p>
            @endif
        @elseif (!$canSeeBidSummary)
            <p class="text-muted mb-3"><i class="fa fa-lock me-1"></i> Bid information is private for traditional listings.</p>
        @endif

        {{-- Agent Visibility Info Messages --}}
        @if ($isAgentViewer && !$isListingOwner)
            @if ($isTraditionalListing && $otherBidsExist)
            <div class="alert alert-info small mb-3 py-2">
                <i class="fa fa-lock me-1"></i> <strong>Traditional Listing:</strong> You can only view your own bid. Other agents' bids remain private.
            </div>
            @elseif ($isBiddingPeriodListing && !$isExpired && !$userHasBid)
            <div class="alert alert-warning small mb-3 py-2">
                <i class="fa fa-info-circle me-1"></i> <strong>Bidding Period:</strong> Submit your bid to view anonymized competing bids (Broker Terms, Services, and Match Scores only).
            </div>
            @elseif ($isBiddingPeriodListing && !$isExpired && $userHasBid && $otherBidsExist)
            <div class="alert alert-info small mb-3 py-2">
                <i class="fa fa-eye me-1"></i> <strong>Bidding Period:</strong> Anonymized competing bids are visible below (Broker Terms, Services, Match Scores only).
            </div>
            {{-- Competing Bids Display for Bidding Period --}}
            @php
                $competingBidsService = app(\App\Services\CompetingBidsService::class);
                $competingBids = $competingBidsService->getCompetingBids($auction->id, $auth_id);
            @endphp
            @if(count($competingBids) > 0)
            <div class="mb-4">
                <h6 class="fw-bold mb-3" style="color: #049399;"><i class="fa fa-users me-2"></i>Competing Bids ({{ count($competingBids) }})</h6>
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
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="d-flex flex-wrap gap-3">
                                    @php
                                        $cBrokerScore    = $compBid['match_score']['broker_comp_percent'];
                                        $cBrokerColor    = $cBrokerScore >= 80 ? '#28a745' : ($cBrokerScore >= 50 ? '#ffc107' : '#dc3545');
                                        $cServicesScore  = $compBid['match_score']['services_percent'];
                                        $cServicesColor  = $cServicesScore >= 80 ? '#28a745' : ($cServicesScore >= 50 ? '#ffc107' : '#dc3545');
                                    @endphp
                                    <div class="d-flex align-items-center">
                                        <span class="badge me-2" style="background: {{ $cBrokerColor }}; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem;">{{ $cBrokerScore }}%</span>
                                        <span class="small text-muted">Broker Compensation ({{ $compBid['match_score']['broker_comp_matched'] }}/{{ $compBid['match_score']['broker_comp_total'] }} fields)</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge me-2" style="background: {{ $cServicesColor }}; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem;">{{ $cServicesScore }}%</span>
                                        <span class="small text-muted">Offered Services ({{ $compBid['match_score']['services_matched'] }}/{{ $compBid['match_score']['services_total'] }} services)</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="fw-semibold small" style="color: #049399;">Offered Services:</div>
                                <div class="small">{{ count($compBid['offered_services']['standard']) + count($compBid['offered_services']['other']) }} Services</div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="fw-semibold small" style="color: #049399;">Broker Compensation Summary:</div>
                                @if(count($compBid['broker_compensation']) > 0)
                                    @php
                                        $cCommStructure   = $compBid['broker_compensation']['commission_structure'] ?? null;
                                        $cPurchaseFeeType = $compBid['broker_compensation']['purchase_fee_type'] ?? null;
                                    @endphp
                                    @if($cCommStructure)
                                    <div class="small">Structure: {{ $cCommStructure }}</div>
                                    @endif
                                    @if($cPurchaseFeeType)
                                    <div class="small">Fee Type: {{ $cPurchaseFeeType }}</div>
                                    @endif
                                @else
                                    <div class="small text-muted">Not specified</div>
                                @endif
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <small class="text-muted fst-italic">Compared to Your Bid</small>
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
                            $agentNumber   = $agentNumberMap[$bid->id] ?? $loop->iteration;
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

                            // Seller Bid Visibility Logic (matches Tenant pattern):
                            // - Traditional: Agents can ONLY see their own bids (not other agents' bids)
                            // - Bidding Period: Agents can see anonymized bids ONLY if they submitted a bid first
                            // - Listing Owner: Always sees all bids
                            $isAgent    = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
                            $canViewBid = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $isAgent && $userHasBid);
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
                            // Use latest active counter terms as baseline when present (same as Tenant flow)
                            $latestCounter   = \App\Models\SellerCounterTerm::with('meta')
                                ->where('seller_agent_auction_bid_id', $bid->id)
                                ->where('status', 1)
                                ->latest('updated_at')
                                ->first();
                            $propertyType    = data_get($auction, 'get.property_type', '');
                            $bidDataArr      = (array) data_get($bid, 'get', []);
                            $auctionDataArr  = (array) data_get($auction, 'get', []);

                            if ($latestCounter && $latestCounter->meta->count()) {
                                $baselineData  = $latestCounter->meta->pluck('meta_value', 'meta_key')->toArray();
                                $baselineLabel = $isListingOwner ? 'Your Counter Terms' : "Seller's Counter Terms";
                            } else {
                                $baselineData  = $auctionDataArr;
                                $baselineLabel = $isListingOwner ? 'Your Original Terms' : "Seller's Original Terms";
                            }

                            $scoreResult     = \App\Helpers\SellerBidMatchScoreHelper::calculate($baselineData, $bidDataArr, null, $propertyType);
                            $totalScore      = $scoreResult['overall_percent'] ?? 100;
                            $brokerScore     = $scoreResult['broker_comp_percent'] ?? 100;
                            $servicesScore   = $scoreResult['services_percent'] ?? 100;
                            $brokerTotal     = $scoreResult['broker_comp_total'] ?? 0;
                            $brokerMatched   = $scoreResult['broker_comp_matched'] ?? 0;
                            $servicesTotal   = $scoreResult['services_total'] ?? 0;
                            $servicesMatched = $scoreResult['services_matched'] ?? 0;
                            $totalScoreColor = \App\Helpers\SellerBidMatchScoreHelper::scoreColor($totalScore);
                        @endphp

                        <!-- Bid Card - Collapsible Accordion Design (matches Tenant) -->
                        <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

                            <!-- A) Card Header - Clickable to expand/collapse -->
                            <div class="card-header d-flex justify-content-between align-items-center bid-accordion-header"
                                 style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
                                 data-target="bidCollapse-{{ data_get($bid, 'id') }}"
                                 aria-expanded="false">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
                                    <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
                                </div>
                                <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusLabel }}</span>
                            </div>

                            <!-- Collapsible Content -->
                            <div class="bid-collapse-content" id="bidCollapse-{{ data_get($bid, 'id') }}" style="display: none;">
                            <div class="card-body" style="padding: 20px;">

                                <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

                                <!-- B) Offered Services Count -->
                                <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Offered Services:</span> {{ $totalServicesCount }} Services
                                </p>
                                <hr style="margin: 15px 0; border-color: #e0e0e0;">

                                <!-- B2) Match Score Summary (Compact Display on Bid Card) -->
                                @php
                                    $showMatchScoreOnCard = $isListingOwner || ($isBiddingPeriodListing && $isAgentViewer && $userHasBid);
                                @endphp
                                @if ($showMatchScoreOnCard)
                                <div class="match-score-summary mb-3 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span style="font-weight: 600; color: #1a3a5c; font-size: 1rem;">
                                            <i class="fa fa-chart-pie me-2"></i>Match Score
                                        </span>
                                        <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1rem; padding: 6px 12px;">
                                            {{ $totalScore }}%
                                        </span>
                                    </div>
                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Broker Compensation:</span>
                                                <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">{{ $brokerMatched }}/{{ $brokerTotal }} fields</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Offered Services:</span>
                                                <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">{{ $servicesMatched }}/{{ $servicesTotal }} services</div>
                                        </div>
                                    </div>
                                    <div class="mt-2 small text-muted">
                                        <i class="fa fa-info-circle me-1"></i>Compared to: {{ $baselineLabel }}
                                    </div>
                                </div>
                                @endif

                                <!-- C) Broker Compensation Summary -->
                                <h6 style="font-weight: 600; color: #1a3a5c; font-size: 1.15rem; margin-bottom: 12px;">Broker Compensation Summary:</h6>
                                <div class="mb-3">
                                    <p class="mb-1" style="font-size: 1rem; color: #333;">
                                        <span style="font-weight: 600;">Seller's Broker Purchase Fee:</span>
                                    </p>
                                    <p class="mb-0" style="font-size: 1rem; color: #555;">{{ $sellerPurchaseFeeDisplay }}</p>
                                </div>

                                <!-- D) View Full Bid link / Lock / BP lockout -->
                                @if ($isListingOwner || $isBidOwner)
                                    @if ($isBiddingPeriodListing && $isBiddingTimerActive && $isListingOwner && !$isBidOwner)
                                    <span style="color: #999; font-size: 1rem; font-weight: 500; cursor: not-allowed;"
                                          title="Bids can be viewed when the bidding period ends.">
                                        <i class="fa fa-lock me-1"></i> View Full Bid
                                    </span>
                                    <div class="text-muted small mt-1">
                                        <i class="fa fa-clock me-1"></i> Bids can be viewed when the bidding period ends.
                                    </div>
                                    @else
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                       style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                        View Full Bid
                                    </a>
                                    @endif
                                @elseif ($isBiddingPeriodListing && $isAgentViewer && !$isBidOwner)
                                <a href="#" data-bs-toggle="modal" data-bs-target="#limitedBidModal{{ data_get($bid, 'id') }}"
                                   style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                    View Full Services &amp; Broker Compensation Terms
                                </a>
                                @else
                                <span style="color: #888; font-style: italic; font-size: 0.95rem;">
                                    <i class="fa fa-lock me-1"></i> Private - visible only to listing creator
                                </span>
                                @endif

                                <!-- E) Edit Actions for Bid Owner -->
                                @if ($canEditWithdraw)
                                <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                    <a href="{{ route('add_seller_agent_bid', $auction->id) }}?edit={{ data_get($bid, 'id') }}"
                                       class="btn btn-primary bid-action-btn">
                                        <i class="fa fa-edit me-1"></i> Edit Bid
                                    </a>
                                </div>
                                @elseif ($isBidOwner && $isExpired)
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa fa-clock me-1"></i> Bidding has ended - edit unavailable
                                    </span>
                                </div>
                                @elseif ($isBidOwner && ($bidAccepted === 'accepted' || $bidAccepted === 'rejected'))
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa fa-lock me-1"></i> Bid {{ $bidAccepted }} - edit unavailable
                                    </span>
                                    @if($bidAccepted === 'accepted')
                                    @php $bidOwnerSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->first(); @endphp
                                    @if($bidOwnerSummary)
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <a href="{{ route('accepted-bid-summary.view', $bidOwnerSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                                        </a>
                                        @if(!$bidOwnerSummary->isAgentSigned())
                                        <a href="{{ route('accepted-bid-summary.sign-form', $bidOwnerSummary->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fa fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                                        </a>
                                        @endif
                                        @if($bidOwnerSummary->isFullySigned())
                                        <a href="{{ route('accepted-bid-summary.download-pdf', $bidOwnerSummary->id) }}" class="btn btn-success btn-sm">
                                            <i class="fa fa-download me-1"></i> Download Signed PDF
                                        </a>
                                        @endif
                                    </div>
                                    @endif
                                    @endif
                                </div>
                                @endif

                            </div>
                            </div> {{-- End collapse div --}}
                        </div> {{-- End bid card --}}

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
                                            <i class="fa fa-lock me-2"></i> Private Compensation &amp; Agreement Terms
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="background: #fafafa; padding: 25px; max-height: 80vh; overflow-y: auto;">

                                        {{-- ========== MATCH SCORE PANEL ========== --}}
                                        <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0" style="color: #1a3a5c; font-weight: 600;">
                                                    <i class="fa fa-chart-pie me-2"></i>Match Score
                                                </h6>
                                                <span class="badge" style="background: {{ $getScoreColor($totalScore) }}; font-size: 1.1rem; padding: 8px 16px;">
                                                    {{ $totalScore }}% Match
                                                </span>
                                            </div>
                                            <p class="small text-muted mb-3">
                                                Comparing to: <strong>{{ $baselineLabel }}</strong>
                                            </p>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($brokerScore) }};">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="small fw-semibold">Broker Compensation</span>
                                                            <span class="badge" style="background: {{ $getScoreColor($brokerScore) }};">{{ $brokerScore }}%</span>
                                                        </div>
                                                        <div class="small text-muted mt-1">
                                                            {{ $brokerMatched }}/{{ $brokerTotal }} fields match
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($servicesScore) }};">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="small fw-semibold">Offered Services</span>
                                                            <span class="badge" style="background: {{ $getScoreColor($servicesScore) }};">{{ $servicesScore }}%</span>
                                                        </div>
                                                        <div class="small text-muted mt-1">
                                                            {{ $servicesMatched }}/{{ $servicesTotal }} services match
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- ========== END MATCH SCORE PANEL ========== --}}

                                        <!-- 1. Agent Overview & Qualifications -->
                                        @if ($isListingOwner || $isBidOwner)
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa fa-user-tie me-2"></i>Agent Overview &amp; Qualifications
                                            </h6>

                                            @if (data_get($bid, 'get.bio'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">About Agent:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.bio') }}</div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.why_hire_you'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Why Hire This Agent:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.why_hire_you') }}</div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.what_sets_you_apart'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">What Sets This Agent Apart:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.what_sets_you_apart') }}</div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.marketing_plan'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Marketing Strategy:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.marketing_plan') }}</div>
                                            </div>
                                            @endif

                                            @php
                                                $sellerReviewLinks = data_get($bid, 'get.reviews_links', []);
                                                $hasAnySellerReviewUrl = !empty(array_filter((array) $sellerReviewLinks, fn($rl) => !empty(is_object($rl) ? $rl->url : ($rl['url'] ?? ''))));
                                            @endphp
                                            @if ($hasAnySellerReviewUrl)
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Review Links:</div>
                                                <div>
                                                    @foreach ($sellerReviewLinks as $reviewLink)
                                                    @php $rlUrlVal = is_object($reviewLink) ? $reviewLink->url : ($reviewLink['url'] ?? ''); @endphp
                                                    @if (!empty($rlUrlVal))
                                                    <div class="mb-1">
                                                        @php
                                                            $rlFinal = $rlUrlVal;
                                                            if (!str_starts_with($rlFinal, 'http://') && !str_starts_with($rlFinal, 'https://')) {
                                                                $rlFinal = 'https://' . $rlFinal;
                                                            }
                                                            $rlText = is_object($reviewLink) ? ($reviewLink->text ?? '') : ($reviewLink['text'] ?? '');
                                                        @endphp
                                                        <a href="{{ $rlFinal }}" target="_blank" class="text-primary text-decoration-none">
                                                            <i class="fa fa-external-link-alt me-1"></i>
                                                            {{ !empty($rlText) ? $rlText : $rlUrlVal }}
                                                        </a>
                                                    </div>
                                                    @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.website_link'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Website Link:</div>
                                                <div>
                                                    @php
                                                        $wLink = data_get($bid, 'get.website_link');
                                                        if (!empty($wLink) && !str_starts_with($wLink, 'http://') && !str_starts_with($wLink, 'https://')) {
                                                            $wLink = 'https://' . $wLink;
                                                        }
                                                    @endphp
                                                    <a href="{{ $wLink }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                                        <i class="fa fa-globe me-1"></i> Visit Website
                                                    </a>
                                                </div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.social_media'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Social Media Platforms:</div>
                                                <div>
                                                    @foreach (data_get($bid, 'get.social_media') as $social)
                                                    @php $socialArray = (array) $social; @endphp
                                                    @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                                                    <div class="mb-1">
                                                        @php
                                                            $socialUrl = $socialArray['url'];
                                                            if (!str_starts_with($socialUrl, 'http://') && !str_starts_with($socialUrl, 'https://')) {
                                                                $socialUrl = 'https://' . $socialUrl;
                                                            }
                                                        @endphp
                                                        <a href="{{ $socialUrl }}" target="_blank" class="text-primary text-decoration-none">
                                                            <i class="fab fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
                                                            {{ !empty($socialArray['text']) ? $socialArray['text'] : $socialArray['platform'] }}
                                                        </a>
                                                    </div>
                                                    @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.year_licensed'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Licensed Year:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.year_licensed') }}</div>
                                            </div>
                                            @endif

                                        </div>
                                        @endif

                                        <!-- 2. Offered Services (grouped bullet points, matches Tenant) -->
                                        @php
                                            $allBidMeta = (array) data_get($bid, 'get', []);
                                            $services   = $allBidMeta['services'] ?? [];
                                            if (is_string($services)) { $services = json_decode($services, true) ?? []; }
                                            $services = array_filter((array)$services, fn($s) => !empty(trim((string)$s)) && $s !== 'Other');

                                            $normalizeStr = fn($s) => strtolower(trim(preg_replace('/[\x{2018}\x{2019}]/u', "'", preg_replace('/[\x{201C}\x{201D}]/u', '"', $s))));

                                            // Build selected services normalized map
                                            $selectedNormalized = [];
                                            foreach ($services as $svc) {
                                                $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                $selectedNormalized[$normalizeStr($svc)] = $displaySvc;
                                            }

                                            // Determine property type from bid meta, falling back to auction
                                            $bidPropertyType = $allBidMeta['property_type']
                                                ?? data_get($auction, 'get.property_type', 'Residential');
                                            // Normalize to short form
                                            $bidPropNorm = strtolower(trim($bidPropertyType));
                                            if (str_contains($bidPropNorm, 'income')) {
                                                $bidPropKey = 'Income';
                                            } elseif (str_contains($bidPropNorm, 'commercial')) {
                                                $bidPropKey = 'Commercial';
                                            } elseif (str_contains($bidPropNorm, 'business')) {
                                                $bidPropKey = 'Business';
                                            } elseif (str_contains($bidPropNorm, 'vacant') || str_contains($bidPropNorm, 'land')) {
                                                $bidPropKey = 'Vacant Land';
                                            } else {
                                                $bidPropKey = 'Residential';
                                            }

                                            $sellerServicesConfig = [
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
                                                        "Coordinate Letter of Intent (LOI) submissions, counteroffers, and contract revisions",
                                                        "Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods",
                                                        "Manage communication with the Buyer\u2019s Agent or Buyer",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Assist with inspection-related negotiations and Buyer requests for repairs or credits",
                                                        "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                                        "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '🧾 Closing Coordination & Transaction Management' => [
                                                        "Coordinate inspections, appraisals, and estoppel certificate delivery with the Buyer\u2019s Agent or Buyer, as applicable",
                                                        "Provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                        "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                        "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity",
                                                        "Assist in estimating Capitalization Rate (Cap Rate), Price per Square Foot, or Gross Rent Multiplier (GRM) based on listing details and commercial comparables",
                                                        "Provide general insight on likely Buyer types (e.g., Owner-User, Investor, 1031 Exchange Buyer), common value drivers, and investment strategies",
                                                        "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                        "Provide general guidance on lease structures, expense ratios, and Tenant impacts",
                                                    ],
                                                ],
                                                'Business' => [
                                                    '📢 Business Marketing & Listing Promotion' => [
                                                        "List the Business Opportunity on the local Multiple Listing Service (MLS)",
                                                        "List the Business Opportunity on Crexi.com",
                                                        "List the Business Opportunity on LoopNet.com",
                                                        "List the Business Opportunity on BizBuySell.com",
                                                        "List the Business Opportunity on BizQuest.com",
                                                        "List the Business Opportunity on BusinessesForSale.com",
                                                        "Create a branded flyer summarizing the Business\u2019s key features (e.g., industry, cash flow, assets)",
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
                                                    '🛠️ Listing Preparation & Confidential Marketing' => [
                                                        "Conduct a preliminary Seller consultation to gather details about the Business\u2019s operations, assets, and goals",
                                                        "Provide a business sale checklist to collect financials, licenses, lease terms, and key operational details",
                                                        "Assist with preparing a non-confidential teaser or executive summary for marketing purposes",
                                                        "Organize internal documentation such as profit and loss statements, balance sheets, FF&E summaries, inventory lists, and staffing overviews (as available)",
                                                        "Refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only \u2014 no endorsement or warranty is made)",
                                                        "Compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries",
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
                                                        "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                                        "Ensure proper notice is provided if the property or business premises is occupied",
                                                        "Install a real estate sign on the property",
                                                        "Install a lockbox for Agent access",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Coordinate directly with Tenant(s) or business staff to arrange access for showings",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📉 Offer & Contract Management' => [
                                                        "Present all Letters of Intent (LOIs) or formal offers to the Seller and summarize key deal terms",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Negotiate deal terms such as purchase price, deposit structure, contingencies, transition period, and asset allocation",
                                                        "Coordinate revisions, counteroffers, and ongoing communication with the Buyer or their representatives",
                                                        "Manage communication with the Buyer\u2019s Broker or Buyer",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Monitor contract contingencies and organize delivery of due diligence materials such as leases, vendor contracts, tax filings, and financial statements",
                                                        "Refer the Seller to legal counsel for formal contract drafting and execution (referrals only \u2014 no legal advice provided)",
                                                        "Provide referrals to Business Attorneys, Escrow Officers, or Business Transfer Specialists (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '📃 Closing Coordination & Transaction Management' => [
                                                        "Coordinate Buyer inspections, management interviews, and site visits as applicable",
                                                        "Provide a transaction checklist and track key deadlines throughout the escrow period",
                                                        "Coordinate with the Buyer\u2019s Attorney, Escrow Officer, or designated Closing Facilitator",
                                                        "Review the Settlement Statement and coordinate corrections with relevant parties",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a business market overview with insights from recent comparable listings",
                                                        "Identify likely Buyer types (e.g., Owner-Operator, Investor, Franchisee) and discuss common deal structures (e.g., asset sale, stock sale)",
                                                        "Provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention",
                                                        "Provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods",
                                                        "Provide referrals to business valuation, accounting, or legal professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                ],
                                                'Vacant Land' => [
                                                    '📢 Property Marketing & Listing Promotion' => [
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
                                                    '🛠️ Listing Preparation & Research' => [
                                                        "Provide a checklist to gather parcel data (e.g., APN, lot size, zoning, utilities, and access)",
                                                        "Assist with collecting public records, flood zone data, and land use information (as available)",
                                                        "Provide referrals to surveyors, soil testers, or land service professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '📸 Photography, Video & Virtual Media' => [
                                                        "Provide professional property photography",
                                                        "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                        "Provide a video overview or narrated walkthrough",
                                                        "Provide a 3D virtual tour (if applicable)",
                                                        "Provide digital enhancements to media assets",
                                                        "Provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)",
                                                    ],
                                                    '🏡 Showings & Access Coordination' => [
                                                        "Install a real estate sign on the property",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📉 Offer & Contract Management' => [
                                                        "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Negotiate price, due diligence timelines, and closing terms",
                                                        "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                        "Manage communication with the Buyer\u2019s Agent or Buyer",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews",
                                                        "Provide referrals to Attorneys, Title Companies, Escrow Officers, or Land Use Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '📃 Closing Coordination & Transaction Management' => [
                                                        "Coordinate surveys, site visits, or environmental access with the Buyer or Buyer\u2019s Agent, as applicable",
                                                        "Coordinate with Title, Escrow, and/or Attorney to prepare for Closing",
                                                        "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on recent land sales, zoning categories, and location-based trends",
                                                        "Provide general insight on permitted uses, utility access, parcel features, and Buyer demand in the area",
                                                        "Recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest",
                                                        "Provide general guidance on Seller obligations, disclosure requirements, and listing preparation",
                                                    ],
                                                ],
                                            ];

                                            $propConfig = $sellerServicesConfig[$bidPropKey] ?? $sellerServicesConfig['Residential'];

                                            // Build flat normalized config map for unmapped detection
                                            $configFlatNorm = [];
                                            foreach ($propConfig as $catSvcs) {
                                                foreach ($catSvcs as $s) {
                                                    $configFlatNorm[$normalizeStr($s)] = true;
                                                }
                                            }

                                            // Find unmapped services
                                            $unmappedSvcs = [];
                                            foreach ($services as $svc) {
                                                $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                if (!isset($configFlatNorm[$normalizeStr($svc)]) && !isset($configFlatNorm[$normalizeStr($displaySvc)])) {
                                                    $unmappedSvcs[] = $displaySvc;
                                                }
                                            }

                                            $rawOtherSvcsModal = $allBidMeta['other_services'] ?? null;
                                            $otherSvcsModal = is_string($rawOtherSvcsModal)
                                                ? json_decode($rawOtherSvcsModal, true) ?? []
                                                : ($rawOtherSvcsModal ?? []);
                                            $otherSvcsModal = array_filter((array)$otherSvcsModal, fn($s) => is_string($s) && !empty(trim($s)));

                                            $hasAnyServices = !empty($services) || !empty($otherSvcsModal) || !empty($unmappedSvcs);
                                        @endphp

                                        @if ($hasAnyServices)
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                            </h6>

                                            @foreach ($propConfig as $category => $catSvcs)
                                                @php
                                                    $selectedInCat = array_filter($catSvcs, fn($s) => isset($selectedNormalized[$normalizeStr($s)]));
                                                @endphp
                                                @if (count($selectedInCat) > 0)
                                                <div class="mb-3">
                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $category }}</div>
                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                        @foreach ($catSvcs as $service)
                                                            @if (isset($selectedNormalized[$normalizeStr($service)]))
                                                            <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $selectedNormalized[$normalizeStr($service)] }}</li>
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                </div>
                                                @endif
                                            @endforeach

                                            {{-- Unmapped services (not in property-type config) are silently skipped,
                                                 matching listing view behavior (listing view only shows category-matched services). --}}

                                            @if (!empty($otherSvcsModal))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                    @foreach ($otherSvcsModal as $otherService)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherService }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- 3. Agent Presentation & Promotional Materials -->
                                        @if (data_get($bid, 'get.presentation_link') ||
                                             data_get($bid, 'get.video_upload') ||
                                             data_get($bid, 'get.business_card_link') ||
                                             data_get($bid, 'get.business_card') ||
                                             data_get($bid, 'get.promoMaterials'))
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa fa-chart-line me-2"></i>Agent Presentation &amp; Promotional Materials
                                            </h6>

                                            <!-- Virtual Presentation -->
                                            @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                            <div class="mb-4">
                                                <div class="fw-semibold mb-2" style="color: #049399;">Virtual Agent Presentation</div>
                                                @if (data_get($bid, 'get.presentation_link'))
                                                <div class="mb-2">
                                                    @php
                                                        $presentationLink = data_get($bid, 'get.presentation_link');
                                                        if (!empty($presentationLink) && !str_starts_with($presentationLink, 'http://') && !str_starts_with($presentationLink, 'https://')) {
                                                            $presentationLink = 'https://' . $presentationLink;
                                                        }
                                                    @endphp
                                                    <a href="{{ $presentationLink }}" target="_blank" class="text-primary text-decoration-none">
                                                        <i class="fa fa-external-link-alt me-1"></i> Watch Presentation
                                                    </a>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.video_upload'))
                                                <div class="mb-2">
                                                    <div class="fw-medium mb-1" style="color: #049399;">Uploaded Video:</div>
                                                    @if (is_string(data_get($bid, 'get.video_upload')))
                                                    <video controls style="width: 100%; max-width: 400px; border-radius: 6px; background: #000;">
                                                        <source src="{{ asset('storage/' . data_get($bid, 'get.video_upload')) }}" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                    @else
                                                    <div class="text-muted"><i class="fa fa-video me-1"></i> Video file uploaded</div>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            <!-- Business Card -->
                                            @if (data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card'))
                                            <div class="mb-4">
                                                <div class="fw-semibold mb-2" style="color: #049399;">Business Card:</div>
                                                @if (data_get($bid, 'get.business_card_link'))
                                                <div class="mb-3">
                                                    @php
                                                        $businessCardLink = data_get($bid, 'get.business_card_link');
                                                        if (!empty($businessCardLink) && !str_starts_with($businessCardLink, 'http://') && !str_starts_with($businessCardLink, 'https://')) {
                                                            $businessCardLink = 'https://' . $businessCardLink;
                                                        }
                                                    @endphp
                                                    <a href="{{ $businessCardLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                        <i class="fa fa-external-link-alt me-1"></i> View Business Card (Link)
                                                    </a>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.business_card'))
                                                <div class="mb-2">
                                                    @if (is_string(data_get($bid, 'get.business_card')))
                                                    @php
                                                        $businessCardPath = data_get($bid, 'get.business_card');
                                                        $businessCardExt  = strtolower(pathinfo($businessCardPath, PATHINFO_EXTENSION));
                                                        $businessCardUrl  = asset('storage/' . $businessCardPath);
                                                    @endphp
                                                    @if (in_array($businessCardExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                    <div class="business-card-preview mb-2">
                                                        <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer">
                                                            <img src="{{ $businessCardUrl }}" style="max-width: 450px; width: 100%; height: auto; border-radius: 8px; border: 2px solid #e0e0e0;" alt="Business Card" class="img-fluid">
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-2 mt-2">
                                                        <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm"><i class="fa fa-expand me-1"></i> View Full Size</a>
                                                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-success btn-sm"><i class="fa fa-download me-1"></i> Download</a>
                                                    </div>
                                                    @else
                                                    <div class="d-flex align-items-center p-3 border rounded bg-light">
                                                        <i class="fa fa-file-alt fa-2x text-muted me-3"></i>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-medium">Business Card File</div>
                                                            <small class="text-muted">{{ strtoupper($businessCardExt) }} file</small>
                                                        </div>
                                                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-primary btn-sm"><i class="fa fa-download me-1"></i> Download</a>
                                                    </div>
                                                    @endif
                                                    @else
                                                    <div class="text-muted"><i class="fa fa-id-card me-1"></i> Business card uploaded</div>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            <!-- Marketing Materials -->
                                            @if (data_get($bid, 'get.promoMaterials'))
                                            @php
                                                $hasAnyMaterials = false;
                                                $promoMaterialsRaw = data_get($bid, 'get.promoMaterials', []);
                                                $promoMaterialsNormalized = [];
                                                if (is_array($promoMaterialsRaw) || is_object($promoMaterialsRaw)) {
                                                    foreach ($promoMaterialsRaw as $m) {
                                                        $mArr = is_object($m) ? (array) $m : (is_array($m) ? $m : []);
                                                        $promoMaterialsNormalized[] = $mArr;
                                                        if (!empty($mArr['type']) || !empty($mArr['link']) || !empty($mArr['files'])) {
                                                            $hasAnyMaterials = true;
                                                        }
                                                    }
                                                }
                                            @endphp
                                            <div>
                                                <div class="fw-semibold mb-2" style="color: #049399;">Marketing Materials:</div>
                                                @if ($hasAnyMaterials)
                                                @foreach ($promoMaterialsNormalized as $index => $material)
                                                @php
                                                    $matType  = data_get($material, 'type', '');
                                                    $matOther = data_get($material, 'other', '');
                                                    $matLink  = data_get($material, 'link', '');
                                                    $matFiles = data_get($material, 'files', []);
                                                    if (is_object($matFiles)) { $matFiles = (array) $matFiles; }
                                                @endphp
                                                @if (!empty($matType) || !empty($matLink) || !empty($matFiles))
                                                <div class="mb-3 p-3 border rounded bg-light">
                                                    @if (!empty($matType))
                                                    <div class="fw-medium mb-2" style="color: #049399; font-size: 1rem;">
                                                        <i class="fa fa-folder-open me-1"></i>
                                                        {{ $matType }}@if ($matType === 'Other' && !empty($matOther)) - {{ $matOther }}@endif
                                                    </div>
                                                    @endif
                                                    @if (!empty($matLink))
                                                    <div class="mb-2">
                                                        @php
                                                            $matLinkFull = (!str_starts_with($matLink, 'http://') && !str_starts_with($matLink, 'https://')) ? 'https://' . $matLink : $matLink;
                                                        @endphp
                                                        <a href="{{ $matLinkFull }}" target="_blank" class="text-primary text-decoration-none">
                                                            <i class="fa fa-external-link-alt me-1"></i> View Material
                                                        </a>
                                                    </div>
                                                    @endif
                                                    @if (!empty($matFiles) && is_array($matFiles))
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @foreach ($matFiles as $matFile)
                                                        @php
                                                            $mfPath = is_object($matFile) ? ($matFile->path ?? '') : (is_array($matFile) ? ($matFile['path'] ?? '') : (string) $matFile);
                                                            $mfExt  = strtolower(pathinfo($mfPath, PATHINFO_EXTENSION));
                                                            $mfUrl  = $mfPath ? asset('storage/' . $mfPath) : '';
                                                        @endphp
                                                        @if ($mfUrl)
                                                        @if (in_array($mfExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                        <a href="{{ $mfUrl }}" target="_blank" rel="noopener noreferrer">
                                                            <img src="{{ $mfUrl }}" style="max-width: 120px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;" alt="Material">
                                                        </a>
                                                        @else
                                                        <a href="{{ $mfUrl }}" download class="btn btn-outline-secondary btn-sm">
                                                            <i class="fa fa-download me-1"></i> {{ strtoupper($mfExt) ?: 'File' }}
                                                        </a>
                                                        @endif
                                                        @endif
                                                        @endforeach
                                                    </div>
                                                    @endif
                                                </div>
                                                @endif
                                                @endforeach
                                                @else
                                                <div class="text-muted small">No marketing materials uploaded.</div>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- 4. Broker Compensation & Agency Agreement Terms -->
                                        @php
                                            $bidBrokerHasAny = data_get($bid, 'get.commission_structure') ||
                                                data_get($bid, 'get.purchase_fee_type') ||
                                                data_get($bid, 'get.nominal') ||
                                                data_get($bid, 'get.commission_structure_type') ||
                                                data_get($bid, 'get.interested_purchase_fee_type') ||
                                                data_get($bid, 'get.interested_lease_option_agreement') ||
                                                data_get($bid, 'get.protection_period') ||
                                                data_get($bid, 'get.early_termination_fee_option') ||
                                                data_get($bid, 'get.retainer_fee_option') ||
                                                data_get($bid, 'get.retained_deposits') ||
                                                data_get($bid, 'get.agency_agreement_timeframe') ||
                                                data_get($bid, 'get.brokerage_relationship') ||
                                                data_get($bid, 'get.additional_details_broker');
                                        @endphp
                                        @if ($bidBrokerHasAny)
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa fa-handshake me-2"></i>Broker Compensation &amp; Agency Agreement Terms
                                            </h6>

                                            <!-- A) Seller's Broker Compensation -->
                                            @php
                                                $bidCommStruct = data_get($bid, 'get.commission_structure');
                                                $bidPurchaseFeeType = data_get($bid, 'get.purchase_fee_type');
                                                $bidNominal = data_get($bid, 'get.nominal');
                                                $bidCommStructType = data_get($bid, 'get.commission_structure_type');
                                                // Build Buyer's Broker Commission Fee display
                                                $bidBuyerBrokerFee = null;
                                                if ($bidCommStructType === 'Flat Fee' && data_get($bid, 'get.commission_structure_type_fee_flat')) {
                                                    $bidBuyerBrokerFee = $fmtMoney(data_get($bid, 'get.commission_structure_type_fee_flat'));
                                                } elseif ($bidCommStructType === 'Percentage of the Total Purchase Price' && data_get($bid, 'get.commission_structure_type_fee_percentage')) {
                                                    $bidBuyerBrokerFee = ($fmtPercent(data_get($bid, 'get.commission_structure_type_fee_percentage')) ?? '-') . ' of Total Purchase Price';
                                                } elseif ($bidCommStructType === 'Flat Fee + Percentage' && (data_get($bid, 'get.commission_structure_type_fee_flat_combo') || data_get($bid, 'get.commission_structure_type_fee_percentage_combo'))) {
                                                    $bbfParts = [];
                                                    if (data_get($bid, 'get.commission_structure_type_fee_percentage_combo')) $bbfParts[] = ($fmtPercent(data_get($bid, 'get.commission_structure_type_fee_percentage_combo')) ?? '') . ' of Total Purchase Price';
                                                    if (data_get($bid, 'get.commission_structure_type_fee_flat_combo')) $bbfParts[] = $fmtMoney(data_get($bid, 'get.commission_structure_type_fee_flat_combo'));
                                                    $bidBuyerBrokerFee = implode(' + ', array_filter($bbfParts));
                                                } elseif (strtolower($bidCommStructType ?? '') === 'other' && data_get($bid, 'get.commission_structure_type_fee_other')) {
                                                    $bidBuyerBrokerFee = data_get($bid, 'get.commission_structure_type_fee_other');
                                                } elseif ($bidCommStructType) {
                                                    $bidBuyerBrokerFee = $bidCommStructType;
                                                }
                                                $showSellerBrokerComp = $bidCommStruct || $bidPurchaseFeeType || $bidNominal || $bidCommStructType;
                                            @endphp
                                            @if ($showSellerBrokerComp)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Seller's Broker Compensation</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if ($bidPurchaseFeeType)
                                                    <li class="mb-1"><span class="fw-semibold">Seller's Broker Purchase Fee:</span> {{ $sellerPurchaseFeeDisplay }}</li>
                                                    @endif
                                                    @if ($bidNominal)
                                                    <li class="mb-1"><span class="fw-semibold">Nominal Consideration Fee:</span> {{ $fmtMoney($bidNominal) ?? '-' }}</li>
                                                    @endif
                                                    @if ($bidCommStruct)
                                                    <li class="mb-1"><span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $bidCommStruct }}</li>
                                                    @endif
                                                    @if ($bidBuyerBrokerFee)
                                                    <li class="mb-1"><span class="fw-semibold">Buyer's Broker Commission Fee:</span> {{ $bidBuyerBrokerFee }}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- B) Lease Terms -->
                                            @php
                                                $bidInterestedLease = data_get($bid, 'get.interested_purchase_fee_type');
                                                $showLeaseTerms = strtolower(trim($bidInterestedLease ?? '')) === 'yes';
                                                $bidLeasingFeeType = data_get($bid, 'get.seller_leasing_fee_type');
                                                // Build leasing fee amount display (mirrors listing view logic)
                                                $bidLeasingFeeAmt = null;
                                                if ($bidLeasingFeeType === 'Flat Fee' && data_get($bid, 'get.seller_leasing_gross_purchase_fee_flat_amount')) {
                                                    $bidLeasingFeeAmt = $fmtMoney(data_get($bid, 'get.seller_leasing_gross_purchase_fee_flat_amount'));
                                                } elseif ($bidLeasingFeeType === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.seller_leasing_gross')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross')) ?? '-') . ' of the Gross Lease Value';
                                                } elseif ($bidLeasingFeeType === 'Percentage of the Rent Due Each Rental Period' && data_get($bid, 'get.seller_leasing_gross_rental')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_rental')) ?? '-') . ' of the Rent Due Each Rental Period';
                                                } elseif ($bidLeasingFeeType === "Percentage of the First Month's Rent" && data_get($bid, 'get.seller_leasing_gross_month_rent')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_month_rent')) ?? '-') . " of the First Month's Rent";
                                                } elseif ($bidLeasingFeeType === "Percentage of Month's Rent" && data_get($bid, 'get.seller_leasing_gross_month_rent')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_month_rent')) ?? '-') . " of Month's Rent";
                                                    $bidLeasingMonths = data_get($bid, 'get.seller_leasing_gross_no_of_months');
                                                    if (!empty($bidLeasingMonths) && $bidLeasingMonths != 'null') {
                                                        $bidLeasingFeeAmt .= ' x ' . intval($bidLeasingMonths) . ' Months';
                                                    }
                                                } elseif ($bidLeasingFeeType === 'Percentage of Net Aggregate Rent' && (data_get($bid, 'get.seller_leasing_gross_other') ?: data_get($bid, 'get.seller_leasing_gross'))) {
                                                    $netAggVal = data_get($bid, 'get.seller_leasing_gross_other') ?: data_get($bid, 'get.seller_leasing_gross');
                                                    $bidLeasingFeeAmt = ($fmtPercent($netAggVal) ?? '-') . ' of Net Aggregate Rent';
                                                } elseif ($bidLeasingFeeType === 'Percentage of Gross Rent' && (data_get($bid, 'get.seller_leasing_gross_percentage') || data_get($bid, 'get.seller_leasing_gross_ross_percentage_rent'))) {
                                                    $grossRentVal = data_get($bid, 'get.seller_leasing_gross_percentage') ?? data_get($bid, 'get.seller_leasing_gross_ross_percentage_rent');
                                                    $bidLeasingFeeAmt = ($fmtPercent($grossRentVal) ?? '-') . ' of Gross Rent';
                                                } elseif ($bidLeasingFeeType === 'Flat Fee + Percentage of the Gross Lease Value' && (data_get($bid, 'get.seller_leasing_gross_flat_combo') || data_get($bid, 'get.seller_leasing_gross_percentage_combo'))) {
                                                    $lfParts = [];
                                                    if (data_get($bid, 'get.seller_leasing_gross_flat_combo')) $lfParts[] = $fmtMoney(data_get($bid, 'get.seller_leasing_gross_flat_combo'));
                                                    if (data_get($bid, 'get.seller_leasing_gross_percentage_combo')) $lfParts[] = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_percentage_combo')) ?? '') . ' of Gross Lease Value';
                                                    $bidLeasingFeeAmt = implode(' + ', array_filter($lfParts));
                                                } elseif ($bidLeasingFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent' && (data_get($bid, 'get.seller_leasing_gross_flat_net_combo') || data_get($bid, 'get.seller_leasing_gross_percentage_net_combo'))) {
                                                    $lfParts = [];
                                                    if (data_get($bid, 'get.seller_leasing_gross_flat_net_combo')) $lfParts[] = $fmtMoney(data_get($bid, 'get.seller_leasing_gross_flat_net_combo'));
                                                    if (data_get($bid, 'get.seller_leasing_gross_percentage_net_combo')) $lfParts[] = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_percentage_net_combo')) ?? '') . ' of Net Aggregate Rent';
                                                    $bidLeasingFeeAmt = implode(' + ', array_filter($lfParts));
                                                } elseif (strtolower($bidLeasingFeeType ?? '') === 'other' && data_get($bid, 'get.seller_leasing_gross_purchase_fee_other')) {
                                                    $bidLeasingFeeAmt = data_get($bid, 'get.seller_leasing_gross_purchase_fee_other');
                                                } elseif ($bidLeasingFeeType) {
                                                    $bidLeasingFeeAmt = $bidLeasingFeeType;
                                                }
                                            @endphp
                                            @if ($bidInterestedLease)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Lease Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1"><span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $bidInterestedLease }}</li>
                                                    @if ($showLeaseTerms && $bidLeasingFeeAmt)
                                                    <li class="mb-1"><span class="fw-semibold">Seller's Broker Leasing Fee:</span> {{ $bidLeasingFeeAmt }}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- C) Lease-Option Terms -->
                                            @php
                                                $bidLeaseOption = data_get($bid, 'get.interested_lease_option_agreement');
                                                $showLeaseOption = strtolower(trim($bidLeaseOption ?? '')) === 'yes';
                                                $bidLeaseType = data_get($bid, 'get.lease_type');
                                                $bidLeaseValue = data_get($bid, 'get.lease_value');
                                                $bidPurchaseType = data_get($bid, 'get.purchase_type');
                                                $bidPurchaseValue = data_get($bid, 'get.purchase_value');
                                                // Format lease-option creation fee (mirrors listing view: % of Total Purchase Price)
                                                $bidLeaseOptionFee = null;
                                                if ($bidLeaseValue) {
                                                    if (in_array($bidLeaseType, ['%', 'percent']) || str_contains((string)($bidLeaseValue ?? ''), '%')) {
                                                        $bidLeaseOptionFee = str_replace('%', '', $bidLeaseValue) . '% of Total Purchase Price';
                                                    } else {
                                                        $bidLeaseOptionFee = $fmtMoney($bidLeaseValue);
                                                    }
                                                }
                                                // Format purchase option exercise fee
                                                $bidPurchaseOptFee = null;
                                                if ($bidPurchaseValue) {
                                                    if (in_array($bidPurchaseType, ['%', 'percent']) || str_contains((string)($bidPurchaseValue ?? ''), '%')) {
                                                        $bidPurchaseOptFee = str_replace('%', '', $bidPurchaseValue) . '% of Total Purchase Price';
                                                    } else {
                                                        $bidPurchaseOptFee = $fmtMoney($bidPurchaseValue);
                                                    }
                                                }
                                            @endphp
                                            @if ($bidLeaseOption)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1"><span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $bidLeaseOption }}</li>
                                                    @if ($showLeaseOption && $bidLeaseOptionFee)
                                                    <li class="mb-1"><span class="fw-semibold">Compensation for Creating Lease-Option Agreement:</span> {{ $bidLeaseOptionFee }}</li>
                                                    @endif
                                                    @if ($showLeaseOption && $bidPurchaseOptFee)
                                                    <li class="mb-1"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $bidPurchaseOptFee }}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- D) Legal Terms -->
                                            @php
                                                $bidEarlyTermOpt = data_get($bid, 'get.early_termination_fee_option');
                                                $bidEarlyTermAmt = data_get($bid, 'get.early_termination_fee_amount');
                                                $bidRetainedDep  = data_get($bid, 'get.retained_deposits');
                                                $bidRetainerOpt  = data_get($bid, 'get.retainer_fee_option');
                                                $bidRetainerAmt  = data_get($bid, 'get.retainer_fee_amount');
                                                $bidRetainerApp  = data_get($bid, 'get.retainer_fee_application');
                                                $bidProtPeriod   = data_get($bid, 'get.protection_period');
                                                $bidAgencyTf     = data_get($bid, 'get.agency_agreement_timeframe');
                                                $bidAgencyCus    = data_get($bid, 'get.agency_agreement_custom');
                                                $bidAgencyDsp    = strtolower(trim($bidAgencyTf ?? '')) === 'other' ? ($bidAgencyCus ?: 'Other') : ($bidAgencyTf ?: '');
                                                $showLegalTerms  = $bidEarlyTermOpt || $bidRetainedDep || $bidRetainerOpt || $bidProtPeriod || $bidAgencyTf;
                                            @endphp
                                            @if ($showLegalTerms)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if ($bidEarlyTermOpt)
                                                    <li class="mb-1"><span class="fw-semibold">Early Termination Fee:</span> {!! \App\Helpers\ListingDisplayHelper::formatYesParenthetical($bidEarlyTermOpt, $fmtMoney($bidEarlyTermAmt)) !!}</li>
                                                    @endif
                                                    @if ($bidRetainerOpt)
                                                    <li class="mb-1"><span class="fw-semibold">Retainer Fee:</span> {!! \App\Helpers\ListingDisplayHelper::formatYesParenthetical($bidRetainerOpt, $fmtMoney($bidRetainerAmt)) !!}</li>
                                                        @if (strtolower($bidRetainerOpt) === 'yes' && $bidRetainerApp)
                                                        <li class="mb-1 ps-3"><span class="fw-semibold">Retainer Fee Application:</span> {{ $bidRetainerApp }}</li>
                                                        @endif
                                                    @endif
                                                    @if ($bidRetainedDep)
                                                    <li class="mb-1"><span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $fmtPercent($bidRetainedDep) }}</li>
                                                    @endif
                                                    @if ($bidProtPeriod)
                                                    <li class="mb-1"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $bidProtPeriod }} days</li>
                                                    @endif
                                                    @if ($bidAgencyTf)
                                                    <li class="mb-1"><span class="fw-semibold">Seller Agency Agreement Timeframe:</span> {{ $bidAgencyDsp }}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- E) Brokerage Relationship -->
                                            @if (data_get($bid, 'get.brokerage_relationship'))
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ data_get($bid, 'get.brokerage_relationship') }}</li>
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- F) Additional Terms -->
                                            @if (data_get($bid, 'get.additional_details_broker'))
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Additional Terms</h6>
                                                <div class="ps-3 text-muted">{{ data_get($bid, 'get.additional_details_broker') }}</div>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- 4. Agent Credentials and Contact Information -->
                                        @if ($isListingOwner || $isBidOwner)
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa fa-address-card me-2"></i>Agent Credentials and Contact Information
                                            </h6>
                                            <div class="row">
                                                @if (data_get($bid, 'get.first_name'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">First Name</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.first_name') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.last_name'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Last Name</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.last_name') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.phone'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Phone Number</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.phone') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.email'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Email</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.email') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.brokerage'))
                                                <div class="col-12 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Brokerage</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.brokerage') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.license_no'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Real Estate License #</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.license_no') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.nar_id'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">NAR Member ID</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.nar_id') }}</div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        <!-- 5. Additional Details -->
                                        @if (data_get($bid, 'get.additional_details'))
                                        <div class="mb-4">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa fa-info-circle me-2"></i>Additional Details
                                            </h6>
                                            <p class="text-muted">{{ data_get($bid, 'get.additional_details') }}</p>
                                        </div>
                                        @endif


                                    </div>{{-- End modal-body --}}

                                    {{-- ===== TENANT-STYLE MODAL FOOTER ===== --}}
                                    <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                                        {{-- Confidential notice --}}
                                        <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                            <i class="fa fa-shield-alt me-2"></i>
                                            <strong>Confidential:</strong> This information is private and only visible to you.
                                        </div>

                                        {{-- ── Listing owner: action buttons when bid is undecided ── --}}
                                        @if ($state === '0' && $isOwnerRow && !in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true))
                                            @if ($isBiddingPeriodListing && $isBiddingTimerActive)
                                            <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                                <i class="fa fa-clock me-1"></i> <strong>Actions unlock when the bidding period ends.</strong>
                                            </div>
                                            @elseif ($isTraditionalListing && $isExpired)
                                            <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                <i class="fa fa-clock me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
                                            </div>
                                            @else
                                            <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                                                <form action="{{ route('acceptSABid') }}" method="post" style="margin: 0;"
                                                      onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                    <button type="submit" class="btn btn-success btn-accept" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa fa-check me-1"></i> Accept Bid
                                                    </button>
                                                </form>
                                                <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}"
                                                   class="btn btn-primary btn-counter" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                    <i class="fa fa-exchange-alt me-1"></i> Counter Bid
                                                </a>
                                                <form action="{{ route('rejectSABid') }}" method="post" style="margin: 0;"
                                                      onsubmit="return confirm('Are you sure you want to reject this bid?');">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                    <button type="submit" class="btn btn-danger btn-reject" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa fa-times me-1"></i> Reject Bid
                                                    </button>
                                                </form>
                                            </div>
                                            @endif
                                        @endif

                                        {{-- ── Accepted state ── --}}
                                        @if ($state === 'accepted')
                                        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                                            <i class="fa fa-check-circle me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                This bid has been accepted.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted this bid.
                                            @endif
                                        </div>
                                        @php
                                            $absFooterBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->first();
                                        @endphp
                                        @if ($absFooterBidSummary && (Auth::id() == $ownerId || data_get($bid, 'user_id') == Auth::id()))
                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                            <a href="{{ route('accepted-bid-summary.view', $absFooterBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                                            </a>
                                            @if (data_get($bid, 'user_id') == Auth::id() && !$absFooterBidSummary->isAgentSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa fa-signature me-1"></i> E-Sign Acknowledgement
                                            </a>
                                            @endif
                                            @if (Auth::id() == $ownerId && !$absFooterBidSummary->isOwnerSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa fa-signature me-1"></i> Seller: E-Sign Acknowledgement
                                            </a>
                                            @endif
                                            @if ($absFooterBidSummary->isFullySigned())
                                            <a href="{{ route('accepted-bid-summary.download-pdf', $absFooterBidSummary->id) }}" class="btn btn-success btn-sm">
                                                <i class="fa fa-download me-1"></i> Download Signed PDF
                                            </a>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- ── Rejected state ── --}}
                                        @elseif ($state === 'rejected')
                                        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                                            <i class="fa fa-times-circle me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                This bid has been rejected.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected this bid.
                                            @endif
                                        </div>

                                        {{-- ── Countered state ── --}}
                                        @elseif ($state === 'countered')
                                        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                            <i class="fa fa-exchange-alt me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                You have submitted a counter offer for this bid.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} has submitted a counter offer.
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                            <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn btn-warning btn-sm text-dark">
                                                <i class="fa fa-eye me-1"></i> View Counter Terms
                                            </a>
                                            @if (Auth::id() == $ownerId)
                                            <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="fa fa-edit me-1"></i> Edit Counter Terms
                                            </a>
                                            @endif
                                        </div>

                                        {{-- ── Pending state: agent viewing their own bid ── --}}
                                        @elseif ($state === '0' && !$isOwnerRow && data_get($bid, 'user_id') == Auth::id())
                                        <div class="alert alert-secondary w-100 mb-0 py-1 small text-center">
                                            <i class="fa fa-eye me-2"></i> <strong>Your Private Terms:</strong> Waiting for a response from {{ trim($ownerFirst . ' ' . $ownerLast) }}.
                                        </div>
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

        @if ($auction->bids->count() > 0)
        <div class="alert alert-warning mt-3 p-2 small">
            <strong>🛡️ Compliance Note:</strong> No Broker Compensation, Agency Agreement Terms, or Counter Offers are ever displayed publicly. These must remain private to avoid antitrust/commission advertising issues.
        </div>
        @endif
                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa fa-user"></i> </span>
                </button>
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('seller.agent.auction.detail', @$auction->id), 200) }}
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
