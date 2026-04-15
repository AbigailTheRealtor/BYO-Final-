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

    /* Consistent row spacing for all buyer listing data rows */
    .card-body .col-md-12.fw-bold,
    .card-body .col-12.fw-bold {
        padding-top: 0.5rem !important;
        padding-bottom: 0.35rem !important;
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

    /* Nested services lists inside another services list */
    ul.services ul.services {
        padding-left: 1.5em;
        margin-top: 0.25rem;
        margin-bottom: 0;
    }

    /* Service category title styling */
    .service-category-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
    }

    .removeBold {
        font-weight: normal;
    }

    /* Financing Details section - subsection headers (darker than text-secondary) */
    .financing-subsection-header {
        font-weight: 700 !important;
        color: #374151 !important;
        margin-bottom: 0;
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

        /* Counter (yellow) */
        .btn-counter {
            background-color: #ffc107 !important;
            color: #000000 !important;
        }

        .btn-counter:hover {
            background-color: #e0a800 !important;
            color: #000000 !important;
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

        /* Counter bidding history — field rows match Tenant typography (12px per li) */
        .counter-bid-card ul.list-unstyled li {
            font-size: 12px;
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
                                    <span class="removeBold">
                                        {{ date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date)) }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->listing_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Listing Date:
                                    <span
                                        class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->listing_date)) }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->expiration_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Expiration Date:
                                    <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->expiration_date)) }}
                                    </span>
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
                            <h4 class="section-title">Property Preferences:</h4>
                        </div>

                        <div class="row" style="flex-wrap: wrap;">

                                    <!-- Location Information -->
                                    @if (@$auction->get->cities != null && count(@$auction->get->cities) > 0)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable Cities:
                                            @foreach (@$auction->get->cities as $item)
                                                <span class="removeBold badge bg-secondary">{{ \App\Helpers\ListingDisplayHelper::stripStateSuffix($item) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (@$auction->get->counties != null && count(@$auction->get->counties) > 0)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable Counties:
                                            @foreach (@$auction->get->counties as $item)
                                                <span class="removeBold badge bg-secondary">{{ \App\Helpers\ListingDisplayHelper::stripStateSuffix($item) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (@$auction->get->zipCodes != null && count(@$auction->get->zipCodes) > 0)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable ZIP Codes:
                                            @foreach (@$auction->get->zipCodes as $item)
                                                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (@$auction->get->state != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable State:
                                            <span class="removeBold">{{ @$auction->get->state }}</span>
                                        </div>
                                    @endif

                                    <!-- Property Type and Style -->
                                    @if (@$auction->get->property_type != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable Property Type:
                                            <span class="removeBold">{{ @$auction->get->property_type }}</span>
                                        </div>
                                    @endif

                                    @php
                                        $detailPropertyStyles = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->property_items, @$auction->get->other_property_items);
                                    @endphp
                                    @if (!empty($detailPropertyStyles))
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable Property Styles:
                                            @foreach ($detailPropertyStyles as $item)
                                                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <!-- Business Type (if applicable) - check both business_type and business_type_selected -->
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
                                        $detailConditions = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->condition_prop_buyer, @$auction->get->other_property_condition);
                                    $conditionDisplayMap = [
                                        'Older but Clean'                => 'Older but Clean & Well Maintained',
                                        'Older but clean & well maintained' => 'Older but Clean & Well Maintained',
                                    ];
                                    $detailConditions = array_map(function($c) use ($conditionDisplayMap) {
                                        return $conditionDisplayMap[$c] ?? $c;
                                    }, $detailConditions);
                                    @endphp
                                    @if (!empty($detailConditions))
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Acceptable Property Conditions:
                                            @if (count($detailConditions) === 1)
                                                <span class="removeBold">{{ $detailConditions[0] }}</span>
                                            @else
                                                @foreach ($detailConditions as $cItem)
                                                    <span class="removeBold badge bg-secondary">{{ $cItem }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif

                                    <!-- Bedrooms and Bathrooms -->
                                    @if (@$auction->get->bedrooms != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Minimum Bedrooms Needed:
                                            <span class="removeBold">
                                                @if (@$auction->get->bedrooms != 'Other')
                                                    {{ @$auction->get->bedrooms }}
                                                @else
                                                    {{ @$auction->get->other_bedrooms }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif

                                    @php
                                        $bathroomsDisplay = (@$auction->get->bathrooms === 'Other')
                                            ? @$auction->get->other_bathrooms
                                            : @$auction->get->bathrooms;
                                    @endphp
                                    @if (!empty($bathroomsDisplay))
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Minimum Bathrooms Needed:
                                            <span class="removeBold">{{ $bathroomsDisplay }}</span>
                                        </div>
                                    @endif

                                    <!-- Square Footage and Acreage -->
                                    @if (@$auction->get->minimum_heated_square != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Minimum Heated SqFt Needed:
                                            <span class="removeBold">{{ @$auction->get->minimum_heated_square }}</span>
                                        </div>
                                    @endif

                                    @if (@$auction->get->total_acreage != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Minimum Total Acreage Needed:
                                            <span class="removeBold">{{ @$auction->get->total_acreage }}</span>
                                        </div>
                                    @endif

                                    @if (@$auction->get->carport_needed != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Carport Needed:
                                            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->carport_needed, @$auction->get->other_carport_needed, 'Spaces') }}</span>
                                        </div>
                                    @endif

                                    @if (@$auction->get->garage_needed != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Garage Needed:
                                            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->garage_needed, @$auction->get->other_garage_needed, 'Spaces') }}</span>
                                        </div>
                                    @endif

                                    <!-- Garage/Parking Features for Commercial/Business -->
                                    @if (@$auction->get->garage_parking_spaces != null)
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Garage Parking Features Needed:
                                            {{-- Skip "Other" in main value when custom text exists --}}
                                            @if (!(@$auction->get->garage_parking_spaces === 'Other' && @$auction->get->other_parking_space_wrapper))
                                                <span class="removeBold">{{ @$auction->get->garage_parking_spaces }}</span>
                                            @endif
                                            @if (@$auction->get->garage_parking_spaces_option && count(@$auction->get->garage_parking_spaces_option) > 0)
                                                @foreach (@$auction->get->garage_parking_spaces_option as $item)
                                                    {{-- Skip "Other" when custom text exists --}}
                                                    @if (!($item === 'Other' && @$auction->get->other_parking_space_wrapper))
                                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                                    @endif
                                                @endforeach
                                            @endif
                                            {{-- Show the custom "Other" text without the word "Other" --}}
                                            @if (@$auction->get->other_parking_space_wrapper)
                                                <span class="removeBold badge bg-secondary">{{ @$auction->get->other_parking_space_wrapper }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    <!-- Pool -->
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

                                // Keep only truthy entries and join their keys (capitalized)
                                $poolTypeList = collect($poolTypeRaw)
                                    ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
                                    ->keys()
                                    ->map(fn($key) => ucfirst($key))
                                    ->implode(', ');
                            @endphp

                            @if (optional($auction->get)->pool_needed === 'Yes')
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Pool Needed:
                                    <span class="removeBold">Yes{{ $poolTypeList !== '' ? ' (' . $poolTypeList . ')' : '' }}</span>
                                </div>
                            @elseif (in_array(optional($auction->get)->pool_needed, ['No', 'Optional'], true))
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Pool Needed:
                                    <span class="removeBold">{{ optional($auction->get)->pool_needed }}</span>
                                </div>
                            @endif

                        <!-- View Preferences -->
                        @if (@$auction->get->view_preference != null && count(@$auction->get->view_preference) > 0)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                View Preference Needed:
                                @foreach (@$auction->get->view_preference as $item)
                                    @if ($item != 'Other')
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endif
                                @endforeach
                                @if (@$auction->get->other_preferences)
                                    <span class="removeBold badge bg-secondary">{{ @$auction->get->other_preferences }}</span>
                                @endif
                            </div>
                        @endif

                        <!-- 55+ Communities -->
                        @if (@$auction->get->leasing_55_plus != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Age-Restricted Community:
                                <span class="removeBold">{{ @$auction->get->leasing_55_plus }}</span>
                            </div>
                        @endif

                        <!-- Non-Negotiable Amenities -->
                        @if (@$auction->get->non_negotiable_amenities != null && count(@$auction->get->non_negotiable_amenities) > 0)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Non-Negotiable Amenities and Property Features:
                                @foreach (@$auction->get->non_negotiable_amenities as $item)
                                    @if ($item != 'Other')
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endif
                                @endforeach
                                @if (@$auction->get->other_non_negotiable_amenities)
                                    <span class="removeBold badge bg-secondary">{{ @$auction->get->other_non_negotiable_amenities }}</span>
                                @endif
                            </div>
                        @endif

                        @if (@$auction->get->property_type == 'Income' && @$auction->get->pets != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Pets:
                                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets) }}</span>
                            </div>

                            @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
                                @if (@$auction->get->type_of_pets)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Pet Types:
                                        <span class="removeBold">{{ @$auction->get->type_of_pets }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->breed_of_pets)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Breed of Pets:
                                        <span class="removeBold">{{ @$auction->get->breed_of_pets }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->weight_of_pets)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Pet Weight (lbs):
                                        <span class="removeBold">{{ @$auction->get->weight_of_pets }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->service_animal)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Service Animal:
                                        <span class="removeBold">{{ @$auction->get->service_animal }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->emotional_support_animal)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Emotional Support Animal:
                                        <span class="removeBold">{{ @$auction->get->emotional_support_animal }}</span>
                                    </div>
                                @endif
                            @endif
                        @endif

                        @if (@$auction->get->property_type != 'Income' && @$auction->get->pets != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Pets:
                                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets) }}</span>
                            </div>

                            @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
                                @if (@$auction->get->type_of_pets)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Pet Types:
                                        <span class="removeBold">{{ @$auction->get->type_of_pets }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->breed_of_pets)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Breed of Pets:
                                        <span class="removeBold">{{ @$auction->get->breed_of_pets }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->weight_of_pets)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Pet Weight (lbs):
                                        <span class="removeBold">{{ @$auction->get->weight_of_pets }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->service_animal)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Service Animal:
                                        <span class="removeBold">{{ @$auction->get->service_animal }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->emotional_support_animal)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Emotional Support Animal:
                                        <span class="removeBold">{{ @$auction->get->emotional_support_animal }}</span>
                                    </div>
                                @endif
                            @endif
                        @endif

                        </div>{{-- end Property Preferences row --}}

                        @php
                            $buyerHasAssets = !empty(@$auction->get->assets) && count((array) @$auction->get->assets) > 0;
                            $buyerHasRealEstate = !empty(@$auction->get->real_estate_purchase);
                            $buyerHasMetrics = !empty(@$auction->get->property_criteria)
                                || !empty(@$auction->get->unit_size)
                                || (!empty(@$auction->get->number_of_unit_type) && count((array) @$auction->get->number_of_unit_type) > 0)
                                || !empty(@$auction->get->minimum_annual_net_income)
                                || !empty(@$auction->get->minimum_cap_rate)
                                || !empty(@$auction->get->preferance_details);
                        @endphp

                        @if ($buyerHasAssets || $buyerHasRealEstate)
                        <hr>
                        <div class="card-header section-header">
                            <h4 class="section-title">Required Property or Business Assets</h4>
                        </div>
                        <div class="row" style="flex-wrap: wrap;">
                            @if ($buyerHasRealEstate)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Business & Real Estate Purchase Requirements:
                                    <span class="removeBold">{{ @$auction->get->real_estate_purchase }}</span>
                                </div>
                            @endif

                            @if ($buyerHasAssets)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Required Property or Business Assets:
                                    @foreach (@$auction->get->assets as $item)
                                        @if ($item != 'Other')
                                            <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                        @endif
                                    @endforeach
                                    @if (@$auction->get->assets_other)
                                        <span class="removeBold badge bg-secondary">{{ @$auction->get->assets_other }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @endif

                        @if ($buyerHasMetrics)
                        <div class="row" style="flex-wrap: wrap;">

                            @if (@$auction->get->unit_size != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Acceptable Number of Units:
                                    <span class="removeBold">
                                        @if (@$auction->get->unit_size != 'Other')
                                            {{ @$auction->get->unit_size }}
                                        @else
                                            {{ @$auction->get->unit_size_other }}
                                        @endif
                                    </span>
                                </div>
                            @endif

                            @if (@$auction->get->number_of_unit_type != null && count(@$auction->get->number_of_unit_type) > 0)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Acceptable Unit Type:
                                    @if (count(@$auction->get->number_of_unit_type) === 1)
                                        <span class="removeBold">{{ @$auction->get->number_of_unit_type[0] }}</span>
                                    @else
                                        @foreach (@$auction->get->number_of_unit_type as $item)
                                            <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif

                            @if (@$auction->get->minimum_annual_net_income != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Minimum Annual Net Income Needed:
                                    <span class="removeBold">{{ \App\Support\Format::money(@$auction->get->minimum_annual_net_income) }}</span>
                                </div>
                            @endif

                            @if (@$auction->get->minimum_cap_rate != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Minimum Cap Rate Needed:
                                    <span class="removeBold">{{ @$auction->get->minimum_cap_rate }}%</span>
                                </div>
                            @endif

                            @if (@$auction->get->preferance_details != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Additional Details:
                                    <span class="removeBold">{{ $auction->get->preferance_details ?? '' }}</span>
                                </div>
                            @endif
                        </div>
                        @endif
                        <hr>
                            <div class="card-header section-header">
                                <h4 class="section-title">Purchasing Terms:</h4>
                            </div>

                            <!-- Special Sale Provisions -->
                            @php
                                $saleProvisionRaw = @$auction->get->sale_provision;
                                if (is_array($saleProvisionRaw)) {
                                    $saleProvisionArray = $saleProvisionRaw;
                                } elseif (is_string($saleProvisionRaw) && !empty($saleProvisionRaw)) {
                                    $decoded = json_decode($saleProvisionRaw, true);
                                    $saleProvisionArray = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$saleProvisionRaw];
                                } else {
                                    $saleProvisionArray = [];
                                }
                            @endphp
                            @if (!empty($saleProvisionArray) && count($saleProvisionArray) > 0)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Acceptable Special Sale Provisions:
                                    @foreach ($saleProvisionArray as $sale)
                                        @if ($sale != 'Other')
                                            @php $displaySale = str_replace('"', '', $sale); @endphp
                                            <span class="removeBold badge bg-secondary">{{ $displaySale }}</span>
                                        @elseif (@$auction->get->sale_provision_other)
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->sale_provision_other }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <!-- Assignment Contract Details -->
                            @if (in_array('Assignment Contract', $saleProvisionArray))
                                @if (@$auction->get->sale_provision_assignment)
                                    @php
                                        $displayAssignment = str_replace('"', '', @$auction->get->sale_provision_assignment);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Buyer Open to Purchasing an Assignment Contract:
                                        <span class="removeBold">{{ $displayAssignment }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->sale_provision_assignment === 'Yes' && @$auction->get->assignment_fee_amount)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Assignment Fee to Broker:
                                        <span class="removeBold">
                                            @php
                                                $feeType = @$auction->get->assignment_fee_type;
                                                $feeAmount = @$auction->get->assignment_fee_amount;
                                                if ($feeType === '$' || $feeType === 'dollar' || empty($feeType)) {
                                                    echo $fmtMoney($feeAmount);
                                                } elseif ($feeType === '%' || $feeType === 'percent') {
                                                    echo $fmtPercent($feeAmount);
                                                } else {
                                                    echo $fmtMoney($feeAmount) . ' ' . $feeType;
                                                }
                                            @endphp
                                        </span>
                                    </div>
                                @endif
                            @endif

                            <!-- Target Closing Date -->
                            @if (@$auction->get->target_closing_date != null)
                                @php
                                    $displayClosingDate = str_replace('"', '', @$auction->get->target_closing_date);
                                @endphp
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Target Closing Date:
                                    <span class="removeBold">{{ $displayClosingDate }}</span>
                                </div>
                            @endif

                            <!-- Maximum Budget -->
                            @if (@$auction->get->maximum_budget != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Maximum Budget:
                                    <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->maximum_budget)) }}</span>
                                </div>
                            @endif

                            @php
                                // Prepare financing items array for conditional checks
                                $financingForChecks = @$auction->get->offered_financing;
                                $_fDecoded = is_string($financingForChecks) ? json_decode($financingForChecks, true) : $financingForChecks;
                                // Ensure always an array regardless of JSON encoding (string vs array)
                                if (is_array($_fDecoded)) {
                                    $financingArray = $_fDecoded;
                                } elseif (is_null($_fDecoded) || $_fDecoded === false) {
                                    $financingArray = is_string($financingForChecks) && !empty($financingForChecks) ? [$financingForChecks] : [];
                                } else {
                                    $financingArray = [$_fDecoded]; // scalar (string decoded from JSON string)
                                }
                                
                                // Check if each financing type has data for grouped display
                                $hasSellerFinancingData = !empty(@$auction->get->purchase_price) || !empty(@$auction->get->down_payment_amount) || !empty(@$auction->get->seller_financing_amount) || !empty(@$auction->get->interest_rate) || !empty(@$auction->get->loan_duration) || !empty(@$auction->get->seller_amortization_type) || !empty(@$auction->get->seller_payment_frequency) || !empty(@$auction->get->seller_late_fee_amount) || !empty(@$auction->get->balloon_payment) || !empty(@$auction->get->balloon_payment_amount) || !empty(@$auction->get->balloon_payment_date) || !empty(@$auction->get->prepayment_penalty) || !empty(@$auction->get->prepayment_penalty_amount);
                                
                                $hasAssumableData = !empty(@$auction->get->assumable_terms) || !empty(@$auction->get->assumable_loan_type) || !empty(@$auction->get->max_assumable_rate) || !empty(@$auction->get->max_monthly_payment) || !empty(@$auction->get->gap_payment_amount);
                                
                                $hasExchangeData = !empty(@$auction->get->exchange_item) || !empty(@$auction->get->exchange_item_value) || !empty(@$auction->get->exchange_item_condition) || !empty(@$auction->get->additional_cash) || !empty(@$auction->get->value_determination) || !empty(@$auction->get->exchange_transfer_method) || !empty(@$auction->get->exchange_liens) || !empty(@$auction->get->exchange_inspection_rights);
                                
                                $hasLeaseOptionData = !empty(@$auction->get->lease_option_price) || !empty(@$auction->get->lease_option_terms) || !empty(@$auction->get->lease_option_duration) || !empty(@$auction->get->lease_option_payment) || !empty(@$auction->get->lease_option_conditions) || !empty(@$auction->get->has_option_fee) || !empty(@$auction->get->option_fee_amount) || !empty(@$auction->get->lease_option_fee_credit) || !empty(@$auction->get->lease_option_fee_credit_percentage) || !empty(@$auction->get->lease_option_maintenance) || !empty(@$auction->get->lease_option_extension_terms);
                                
                                $hasLeasePurchaseData = !empty(@$auction->get->lease_purchase_price) || !empty(@$auction->get->lease_purchase_terms) || !empty(@$auction->get->lease_purchase_duration) || !empty(@$auction->get->lease_purchase_payment) || !empty(@$auction->get->lease_purchase_conditions) || !empty(@$auction->get->lease_purchase_option_fee) || !empty(@$auction->get->lease_purchase_option_fee_amount) || !empty(@$auction->get->lease_purchase_maintenance) || !empty(@$auction->get->lease_purchase_extension_terms) || !empty(@$auction->get->lease_purchase_rent_credit) || !empty(@$auction->get->lease_purchase_rent_credit_amount) || !empty(@$auction->get->lease_purchase_deposit);
                                
                                $hasCryptoData = !empty(@$auction->get->cryptocurrency_type) || !empty(@$auction->get->crypto_percentage) || !empty(@$auction->get->cash_percentage_crypto) || !empty(@$auction->get->crypto_exchange_method) || !empty(@$auction->get->crypto_custodian_wallet) || !empty(@$auction->get->crypto_transaction_fees) || !empty(@$auction->get->crypto_transfer_timing);
                                
                                $hasNftData = !empty(@$auction->get->nft_description) || !empty(@$auction->get->nft_percentage) || !empty(@$auction->get->cash_percentage_nft) || !empty(@$auction->get->nft_valuation_method) || !empty(@$auction->get->nft_transfer_method) || !empty(@$auction->get->nft_gas_fees);
                                
                                // Check if any financing details section should be shown
                                $hasAnyFinancingDetails = 
                                    (in_array('Seller Financing', $financingArray) && $hasSellerFinancingData) ||
                                    (in_array('Assumable', $financingArray) && $hasAssumableData) ||
                                    (in_array('Exchange/Trade', $financingArray) && $hasExchangeData) ||
                                    (in_array('Lease Option', $financingArray) && $hasLeaseOptionData) ||
                                    (in_array('Lease Purchase', $financingArray) && $hasLeasePurchaseData) ||
                                    (in_array('Cryptocurrency', $financingArray) && $hasCryptoData) ||
                                    (in_array('Non-Fungible Token (NFT)', $financingArray) && $hasNftData) ||
                                    (in_array('Cash', $financingArray) && @$auction->get->cash_budget) ||
                                    (count(array_intersect($financingArray, ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'])) > 0 && (@$auction->get->pre_approved || @$auction->get->pre_approval_amount));
                            @endphp

                            @if($hasAnyFinancingDetails || @$auction->get->offered_financing != null)
                                <hr>
                                <div class="col-12">
                                    <div class="card-header section-header">
                                        <h4 class="section-title">Financing Details:</h4>
                                    </div>
                                </div>
                            @endif

                            <!-- Offered Financing/Currency - Now inside Financing Details section -->
                            @if (@$auction->get->offered_financing != null)
                                @php
                                    $financingItems = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->offered_financing, @$auction->get->other_financing);
                                    $financingOrder = ['Assumable','Cash','Conventional','Cryptocurrency','Exchange/Trade','FHA','Jumbo','Lease Option','Lease Purchase','No-Doc','Non-QM','NFT','Non-Fungible Token (NFT)','Seller Financing','USDA','VA'];
                                    usort($financingItems, function($a, $b) use ($financingOrder) {
                                        $aIdx = array_search($a, $financingOrder);
                                        $bIdx = array_search($b, $financingOrder);
                                        if ($aIdx === false && strtolower($a) === 'other') return 1;
                                        if ($bIdx === false && strtolower($b) === 'other') return -1;
                                        if ($aIdx === false) $aIdx = 999;
                                        if ($bIdx === false) $bIdx = 999;
                                        return $aIdx - $bIdx;
                                    });
                                @endphp
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Offered Financing/Currency:
                                    @if (count($financingItems) === 1)
                                        <span class="removeBold">{{ $financingItems[0] }}</span>
                                    @else
                                        @foreach ($financingItems as $fItem)
                                            <span class="removeBold badge bg-secondary">{{ $fItem }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif

                            <!-- Cash Financing Details -->
                            @if (in_array('Cash', $financingArray) && @$auction->get->cash_budget)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Cash Terms</h6>
                                </div>
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Offered Cash Amount:
                                    <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->cash_budget)) }}</span>
                                </div>
                            @endif

                            <!-- Assumable Financing Details -->
                            @if (in_array('Assumable', $financingArray) && $hasAssumableData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Assumable Terms</h6>
                                </div>
                                @if (@$auction->get->assumable_terms)
                                    @php
                                        $displayAssumableTerms = str_replace('"', '', @$auction->get->assumable_terms);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Offered Assumable Terms:
                                        <span class="removeBold">{{ $displayAssumableTerms }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->assumable_loan_type)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Type of Loan:
                                        <span class="removeBold">{{ str_replace('"', '', @$auction->get->assumable_loan_type) }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->max_assumable_rate)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Maximum Interest Rate of Assumable Loan:
                                        <span class="removeBold">{{ @$auction->get->max_assumable_rate }}%</span>
                                    </div>
                                @endif

                                @if (@$auction->get->max_monthly_payment)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Maximum Monthly Payment (Principal & Interest) for Assumable Loan:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->max_monthly_payment)) }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->gap_payment_amount)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Down Payment Buyer Can Afford to Bridge the Gap:
                                        @php
                                            $rawGapType = @$auction->get->gap_payment_type;
                                            $gapType = trim((string) $rawGapType);
                                            $gapValue = str_replace(',', '', @$auction->get->gap_payment_amount);
                                            $isPercent = in_array($gapType, ['%', 'percent', 'percentage'], true);
                                        @endphp
                                        @if ($isPercent)
                                            <span class="removeBold">{{ $gapValue }}%</span>
                                        @else
                                            <span class="removeBold">${{ number_format((float) $gapValue) }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @php
                                $loanTypes = ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'];
                                $selectedLoanTypes = array_values(array_intersect($loanTypes, $financingArray));
                                $hasAnyLoanType = count($selectedLoanTypes) > 0;
                            @endphp
                            @if ($hasAnyLoanType)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">{{ implode(' / ', $selectedLoanTypes) }}</h6>
                                </div>
                                @if (@$auction->get->pre_approved)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Buyer Pre-Approved for a Loan:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->pre_approved, @$auction->get->pre_approval_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->pre_approval_amount)) : null) }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- Cryptocurrency Details - ONLY SHOW IF offered_financing IS "Cryptocurrency" -->
                            @if (in_array('Cryptocurrency', $financingArray) && $hasCryptoData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Cryptocurrency Terms</h6>
                                </div>
                                @if (@$auction->get->cryptocurrency_type)
                                    @php
                                        $displayCryptoType = str_replace('"', '', @$auction->get->cryptocurrency_type);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Offered Cryptocurrency:
                                        <span class="removeBold">{{ $displayCryptoType }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->crypto_percentage)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Percentage of Purchase Price to be Paid with Cryptocurrency:
                                        <span class="removeBold">{{ @$auction->get->crypto_percentage }}%</span>
                                    </div>
                                @endif

                                @if (@$auction->get->cash_percentage_crypto)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Percentage of Purchase Price to be Paid with Cash:
                                        <span class="removeBold">{{ @$auction->get->cash_percentage_crypto }}%</span>
                                    </div>
                                @endif

                                @if (@$auction->get->crypto_exchange_method)
                                    @php
                                        $displayCryptoExchange = str_replace('"', '', @$auction->get->crypto_exchange_method);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Exchange / Conversion Method:
                                        <span class="removeBold">{{ $displayCryptoExchange }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->crypto_custodian_wallet)
                                    @php
                                        $displayCryptoCustodian = str_replace('"', '', @$auction->get->crypto_custodian_wallet);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Custodian / Wallet for Transfer:
                                        <span class="removeBold">{{ $displayCryptoCustodian }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->crypto_transaction_fees)
                                    @php
                                        $displayCryptoFees = str_replace('"', '', @$auction->get->crypto_transaction_fees);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Transaction Fees Responsibility:
                                        <span class="removeBold">{{ $displayCryptoFees }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->crypto_transfer_timing)
                                    @php
                                        $displayCryptoTiming = str_replace('"', '', @$auction->get->crypto_transfer_timing);
                                        $displayCryptoTimingOther = str_replace('"', '', @$auction->get->crypto_transfer_timing_other ?? '');
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Timing of Transfer:
                                        @if (@$auction->get->crypto_transfer_timing === 'Other' && $displayCryptoTimingOther)
                                            <span class="removeBold">{{ $displayCryptoTimingOther }}</span>
                                        @else
                                            <span class="removeBold">{{ $displayCryptoTiming }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            <!-- Exchange/Trade Details - ONLY SHOW IF offered_financing IS "Exchange/Trade" -->
                            @if (in_array('Exchange/Trade', $financingArray) && $hasExchangeData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Exchange/Trade Terms</h6>
                                </div>
                                @if (@$auction->get->exchange_item)
                                    @php
                                        $rawExchangeItem = str_replace('"', '', @$auction->get->exchange_item);
                                        $displayExchangeItem = is_array($rawExchangeItem) ? implode(', ', $rawExchangeItem) : ($rawExchangeItem ?? '');
                                        $displayOtherExchange = str_replace('"', '', @$auction->get->other_exchange_item ?? '');
                                        $exchangeItemIsOther = is_array(@$auction->get->exchange_item)
                                            ? in_array('Other', @$auction->get->exchange_item)
                                            : (@$auction->get->exchange_item === 'Other');
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Acceptable Exchange Item:
                                        @if ($exchangeItemIsOther && @$auction->get->other_exchange_item)
                                            <span class="removeBold">{{ $displayOtherExchange }}</span>
                                        @else
                                            <span class="removeBold">{{ $displayExchangeItem }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if (@$auction->get->exchange_item_value)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Estimated Value of Exchange/Trade Item:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->exchange_item_value)) }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->exchange_item_condition)
                                    @php
                                        $displayExchangeCondition = str_replace('"', '', @$auction->get->exchange_item_condition);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Condition of Exchange/Trade Item:
                                        <span class="removeBold">{{ $displayExchangeCondition }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->additional_cash)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Additional Cash Buyer Will Offer:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->additional_cash)) }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->value_determination)
                                    @php
                                        $displayValueDetermination = str_replace('"', '', @$auction->get->value_determination);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Value of Exchange/Trade Item Determined By:
                                        <span class="removeBold">{{ $displayValueDetermination }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->exchange_transfer_method)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Transfer Method / Logistics:
                                        <span class="removeBold">{{ @$auction->get->exchange_transfer_method }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->exchange_liens)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Liens / Encumbrances Disclosure:
                                        <span class="removeBold">{{ @$auction->get->exchange_liens }}</span>
                                        @if (@$auction->get->exchange_liens === 'Yes' && @$auction->get->exchange_liens_details)
                                            <span class="removeBold">({{ @$auction->get->exchange_liens_details }})</span>
                                        @endif
                                    </div>
                                @endif

                                @if (@$auction->get->exchange_inspection_rights)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Inspection / Verification Rights:
                                        <span class="removeBold">{{ @$auction->get->exchange_inspection_rights }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- Lease Option Details - ONLY SHOW IF offered_financing IS "Lease Option" -->
                            @if (in_array('Lease Option', $financingArray) && $hasLeaseOptionData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Lease Option Terms</h6>
                                </div>
                                {{-- 1. Buyer's Desired Offering Price --}}
                                @if (@$auction->get->lease_option_price)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Buyer's Desired Offering Price for Lease Option:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->lease_option_price)) }}</span>
                                    </div>
                                @endif

                                {{-- 2. Monthly Payment --}}
                                @if (@$auction->get->lease_option_payment)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Monthly Payment Buyer is Offering:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->lease_option_payment)) }}</span>
                                    </div>
                                @endif

                                {{-- 3. Proposed Duration --}}
                                @if (@$auction->get->lease_option_duration)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Proposed Duration of Lease (Months):
                                        <span class="removeBold">{{ @$auction->get->lease_option_duration }}</span>
                                    </div>
                                @endif

                                {{-- 4. Offered Option Fee (inline with amount) --}}
                                @if (@$auction->get->has_option_fee)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Offered Option Fee:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->has_option_fee, @$auction->get->option_fee_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->option_fee_amount)) : null) }}</span>
                                    </div>
                                @endif

                                {{-- 5. Option Fee Credit --}}
                                @if (@$auction->get->lease_option_fee_credit)
                                    @php
                                        $displayFeeCredit = str_replace('"', '', @$auction->get->lease_option_fee_credit);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Option Fee Credit Toward Purchase Price:
                                        <span class="removeBold">{{ $displayFeeCredit }}</span>
                                    </div>
                                @endif

                                {{-- 5a. Percentage of Option Fee Credited (conditional) --}}
                                @if (@$auction->get->lease_option_fee_credit === 'Partial' && @$auction->get->lease_option_fee_credit_percentage)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Percentage of Option Fee Credited:
                                        <span class="removeBold">{{ @$auction->get->lease_option_fee_credit_percentage }}%</span>
                                    </div>
                                @endif

                                {{-- 6. Conditions or Requirements --}}
                                @if (@$auction->get->lease_option_conditions)
                                    @php
                                        $displayLeaseConditions = str_replace('"', '', @$auction->get->lease_option_conditions);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Conditions or Requirements for Lease Option:
                                        <span class="removeBold">{{ $displayLeaseConditions }}</span>
                                    </div>
                                @endif

                                {{-- 7. Specific Terms Proposed --}}
                                @if (@$auction->get->lease_option_terms)
                                    @php
                                        $displayLeaseTerms = str_replace('"', '', @$auction->get->lease_option_terms);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Specific Terms Proposed for Lease Option:
                                        <span class="removeBold">{{ $displayLeaseTerms }}</span>
                                    </div>
                                @endif

                                {{-- 8. Maintenance / Repair Responsibility --}}
                                @if (@$auction->get->lease_option_maintenance)
                                    @php
                                        $displayMaintenance = str_replace('"', '', @$auction->get->lease_option_maintenance);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Maintenance / Repair Responsibility:
                                        <span class="removeBold">{{ $displayMaintenance }}</span>
                                    </div>
                                @endif

                                {{-- 9. Extension Terms --}}
                                @if (@$auction->get->lease_option_extension_terms)
                                    @php
                                        $displayExtension = str_replace('"', '', @$auction->get->lease_option_extension_terms);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Extension Terms:
                                        <span class="removeBold">{{ $displayExtension }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- Lease Purchase Details - ONLY SHOW IF offered_financing IS "Lease Purchase" -->
                            @if (in_array('Lease Purchase', $financingArray) && $hasLeasePurchaseData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Lease Purchase Terms</h6>
                                </div>
                                {{-- 1. Buyer's Desired Offering Price --}}
                                @if (@$auction->get->lease_purchase_price)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Buyer's Desired Offering Price for Lease Purchase:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->lease_purchase_price)) }}</span>
                                    </div>
                                @endif

                                {{-- 2. Monthly Payment --}}
                                @if (@$auction->get->lease_purchase_payment)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Monthly Payment Buyer is Offering:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->lease_purchase_payment)) }}</span>
                                    </div>
                                @endif

                                {{-- 3. Proposed Duration --}}
                                @if (@$auction->get->lease_purchase_duration)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Proposed Duration of Lease (Months):
                                        <span class="removeBold">{{ @$auction->get->lease_purchase_duration }}</span>
                                    </div>
                                @endif

                                {{-- 4. Rent Credit Toward Purchase Price (inline with amount) --}}
                                @if (@$auction->get->lease_purchase_rent_credit)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Rent Credit Toward Purchase Price:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->lease_purchase_rent_credit, in_array(@$auction->get->lease_purchase_rent_credit, ['Yes', 'Partial']) && @$auction->get->lease_purchase_rent_credit_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->lease_purchase_rent_credit_amount)) : null) }}</span>
                                    </div>
                                @endif

                                {{-- 5. Non-Refundable Deposit --}}
                                @if (@$auction->get->lease_purchase_deposit)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Non-Refundable Deposit / Purchase Deposit:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->lease_purchase_deposit)) }}</span>
                                    </div>
                                @endif

                                {{-- 6. Conditions or Requirements --}}
                                @if (@$auction->get->lease_purchase_conditions)
                                    @php
                                        $displayLeasePurchaseConditions = str_replace('"', '', @$auction->get->lease_purchase_conditions);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Conditions or Requirements for Lease Purchase:
                                        <span class="removeBold">{{ $displayLeasePurchaseConditions }}</span>
                                    </div>
                                @endif

                                {{-- 7. Specific Terms Proposed --}}
                                @if (@$auction->get->lease_purchase_terms)
                                    @php
                                        $displayLeasePurchaseTerms = str_replace('"', '', @$auction->get->lease_purchase_terms);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Specific Terms Proposed for Lease Purchase:
                                        <span class="removeBold">{{ $displayLeasePurchaseTerms }}</span>
                                    </div>
                                @endif

                                {{-- 8. Maintenance / Repair Responsibility --}}
                                @if (@$auction->get->lease_purchase_maintenance)
                                    @php
                                        $displayLPMaintenance = str_replace('"', '', @$auction->get->lease_purchase_maintenance);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Maintenance / Repair Responsibility:
                                        <span class="removeBold">{{ $displayLPMaintenance }}</span>
                                    </div>
                                @endif

                                {{-- 9. Extension Terms --}}
                                @if (@$auction->get->lease_purchase_extension_terms)
                                    @php
                                        $displayLPExtension = str_replace('"', '', @$auction->get->lease_purchase_extension_terms);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Extension Terms:
                                        <span class="removeBold">{{ $displayLPExtension }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- NFT Details - ONLY SHOW IF offered_financing IS "Non-Fungible Token (NFT)" -->
                            @if (in_array('Non-Fungible Token (NFT)', $financingArray) && $hasNftData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Non-Fungible Token (NFT) Terms</h6>
                                </div>
                                @if (@$auction->get->nft_description)
                                    @php
                                        $displayNFTDescription = str_replace('"', '', @$auction->get->nft_description);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Offered Non-Fungible Token (NFT):
                                        <span class="removeBold">{{ $displayNFTDescription }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->nft_percentage)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Percentage of Purchase Price to be Paid with NFT:
                                        <span class="removeBold">{{ @$auction->get->nft_percentage }}%</span>
                                    </div>
                                @endif

                                @if (@$auction->get->cash_percentage_nft)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Percentage of Purchase Price to be Paid with Cash:
                                        <span class="removeBold">{{ @$auction->get->cash_percentage_nft }}%</span>
                                    </div>
                                @endif

                                @if (@$auction->get->nft_valuation_method)
                                    @php
                                        $displayNFTValuation = str_replace('"', '', @$auction->get->nft_valuation_method);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        NFT Valuation Method:
                                        <span class="removeBold">{{ $displayNFTValuation }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->nft_transfer_method)
                                    @php
                                        $displayNFTTransfer = str_replace('"', '', @$auction->get->nft_transfer_method);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        NFT Transfer Method:
                                        <span class="removeBold">{{ $displayNFTTransfer }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->nft_gas_fees)
                                    @php
                                        $displayNFTGasFees = str_replace('"', '', @$auction->get->nft_gas_fees);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Transaction Fees Responsibility (Gas Fees):
                                        <span class="removeBold">{{ $displayNFTGasFees }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- Seller Financing Details -->
                            @if (in_array('Seller Financing', $financingArray) && $hasSellerFinancingData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Seller Financing Terms</h6>
                                </div>
                                @if (@$auction->get->purchase_price)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Desired Purchase Price:
                                        <span class="removeBold">${{ number_format((float) str_replace(',', '', @$auction->get->purchase_price)) }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->down_payment_amount)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Desired Down Payment:
                                        <span class="removeBold">{{ @$auction->get->down_payment_type === '%' ? '' : '$' }}{{ number_format((float) str_replace(',', '', @$auction->get->down_payment_amount)) }}{{ @$auction->get->down_payment_type === '%' ? '%' : '' }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->seller_financing_amount)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Desired Seller Financing Amount:
                                        <span class="removeBold">{{ @$auction->get->seller_financing_type === '%' ? '' : '$' }}{{ number_format((float) str_replace(',', '', @$auction->get->seller_financing_amount)) }}{{ @$auction->get->seller_financing_type === '%' ? '%' : '' }}</span>
                                    </div>
                                @endif

                                @if (@$auction->get->interest_rate)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Desired Interest Rate:
                                        <span class="removeBold">{{ @$auction->get->interest_rate }}%</span>
                                    </div>
                                @endif

                                @if (@$auction->get->loan_duration)
                                    @php
                                        $displayLoanDuration = str_replace('"', '', @$auction->get->loan_duration);
                                    @endphp
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Desired Loan Duration:
                                        <span class="removeBold">{{ $displayLoanDuration }}</span>
                                    </div>
                                @endif

                                {{-- Prepayment Penalty (inline with amount) --}}
                                @if (@$auction->get->prepayment_penalty)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Prepayment Penalty:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->prepayment_penalty, @$auction->get->prepayment_penalty_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->prepayment_penalty_amount)) : null) }}</span>
                                    </div>
                                @endif

                                {{-- Balloon Payment (inline with amount) --}}
                                @if (@$auction->get->balloon_payment)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Balloon Payment:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->balloon_payment, @$auction->get->balloon_payment_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->balloon_payment_amount)) : null) }}</span>
                                    </div>

                                @if (@$auction->get->balloon_payment === 'Yes')
                                    @if (@$auction->get->balloon_payment_date)
                                        @php
                                            $displayBalloonDate = str_replace('"', '', @$auction->get->balloon_payment_date);
                                        @endphp
                                        <div class="col-md-12 col-12 pt-2 fw-bold">
                                            Balloon Payment Due Date:
                                            <span class="removeBold">{{ $displayBalloonDate }}</span>
                                        </div>
                                    @endif
                                @endif
                                @endif

                                @if (@$auction->get->seller_amortization_type)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Amortization Type:
                                        @if (@$auction->get->seller_amortization_type === 'Other' && @$auction->get->seller_amortization_other)
                                            <span class="removeBold">{{ @$auction->get->seller_amortization_other }}</span>
                                        @else
                                            <span class="removeBold">{{ @$auction->get->seller_amortization_type }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if (@$auction->get->seller_payment_frequency)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Payment Frequency:
                                        @if (@$auction->get->seller_payment_frequency === 'Other' && @$auction->get->seller_payment_frequency_other)
                                            <span class="removeBold">{{ @$auction->get->seller_payment_frequency_other }}</span>
                                        @else
                                            <span class="removeBold">{{ @$auction->get->seller_payment_frequency }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if (@$auction->get->seller_late_fee_amount)
                                    <div class="col-md-12 col-12 pt-2 fw-bold">
                                        Late Payment Fee:
                                        <span class="removeBold">{{ @$auction->get->seller_late_fee_amount }}</span>
                                    </div>
                                @endif
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
                        // Use ServicesFormatter to order services according to canonical order
                        $propertyType = @$auction->get->property_type ?? 'Residential';
                        
                        // Map property type to config key
                        $propTypeMap = [
                            'Residential' => 'residential',
                            'Residential Property' => 'residential',
                            'Income' => 'income',
                            'Income Property' => 'income',
                            'Commercial' => 'commercial',
                            'Commercial Property' => 'commercial',
                            'Business' => 'business',
                            'Business Opportunity' => 'business',
                            'Land' => 'vacant_land',
                            'Land Property' => 'vacant_land',
                            'Vacant Land' => 'vacant_land',
                        ];
                        
                        $configKey = $propTypeMap[$propertyType] ?? 'residential';
                        $flowKey = 'buyer_agent.' . $configKey;
                        
                        $allServices = is_array(@$auction->get->services) ? $auction->get->services : [];
                        $otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];
                        
                        // Order services using the canonical order from config
                        $orderedServices = \App\Support\ServicesFormatter::orderSelectedServices($allServices, $flowKey);
                        @endphp

                        <div class="col-md-12 col-12 pt-2">
                            @if (!empty($orderedServices))
                                @foreach ($orderedServices as $categoryName => $categoryServices)
                                    @if (!empty($categoryServices))
                                    <div class="mt-3">
                                        <strong>{{ $categoryName }}</strong>
                                        <ul class="services">
                                            @foreach ($categoryServices as $service)
                                            <li style="font-size: 16px;">{{ $service }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif
                                @endforeach
                            @elseif (!empty($allServices))
                                {{-- Fallback: show all services if none match categories --}}
                                <div class="mt-3">
                                    <strong>📋 Services Requested</strong>
                                    <ul class="services">
                                        @foreach ($allServices as $service)
                                        <li style="font-size: 16px;">{{ $service }}</li>
                                        @endforeach
                                    </ul>
                                </div>
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
                                Additional Details: <span
                                    class="removeBold">{{ $auction->get->additional_details ?? '' }}</span>
                            </div>
                        @endif

                        @php
                            $hasBrokerCompData =
                                !empty(@$auction->get->commission_structure) ||
                                !empty(@$auction->get->purchase_fee_type) ||
                                !empty(@$auction->get->interested_lease_option) ||
                                !empty(@$auction->get->lease_fee_type) ||
                                !empty(@$auction->get->interested_lease_option_agreement) ||
                                !empty(@$auction->get->protection_period) ||
                                !empty(@$auction->get->early_termination_fee_option) ||
                                !empty(@$auction->get->retainer_fee_option) ||
                                !empty(@$auction->get->agency_agreement_timeframe) ||
                                !empty(@$auction->get->brokerage_relationship) ||
                                !empty(@$auction->get->additional_details_broker);
                        @endphp
                        @if ($hasBrokerCompData)
                        <hr />
                        <div class="card-header section-header">
                            <h4 class="section-title">Broker Compensation & Agency Agreement Terms:</h4>
                        </div>
                        @endif

                        <!-- Buyer's Broker Compensation Sub-section -->
                        @if (@$auction->get->commission_structure != null || @$auction->get->purchase_fee_type != null)
                        <h5 class="mt-3 mb-2"><strong>Buyer's Broker Compensation:</strong></h5>
                        @endif
                        <div class="broker-compensation-section">

                        @if (@$auction->get->commission_structure != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Buyer's Broker Commission Structure:
                            <span class="removeBold">{{ str_replace('"', '', @$auction->get->commission_structure) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->purchase_fee_type != null)
                        @php
                            $purchaseFeeType = @$auction->get->purchase_fee_type ?? '';
                            $purchaseFeeCombined = '—';
                            
                            if ($purchaseFeeType === 'Flat Fee') {
                                $purchaseFeeCombined = $fmtMoney(@$auction->get->purchase_fee_flat) ?? '—';
                            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price') {
                                $pct = @$auction->get->purchase_fee_percentage;
                                $purchaseFeeCombined = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '—';
                            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                                $purchaseFeeCombined = $joinParts([
                                    $fmtMoney(@$auction->get->purchase_fee_flat_combo),
                                    @$auction->get->purchase_fee_percentage_combo ? ($fmtPercent(@$auction->get->purchase_fee_percentage_combo) . ' of Total Purchase Price') : null,
                                ]) ?? '—';
                            } elseif ($purchaseFeeType === 'other') {
                                $purchaseFeeCombined = @$auction->get->purchase_fee_other ?? '—';
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Buyer's Broker Purchase Fee:
                            <span class="removeBold">{{ $purchaseFeeCombined }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->commission_structure != null || @$auction->get->purchase_fee_type != null)
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        @endif

                        <!-- Buyer's Broker Lease Fee Sub-section -->
                        @if (@$auction->get->interested_lease_option != null || @$auction->get->lease_fee_type != null)
                        <h5 class="mt-3 mb-2"><strong>Buyer's Broker Lease Fee:</strong></h5>
                        @endif

                        @if (@$auction->get->interested_lease_option != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Interested in a Lease Agreement:
                            <span class="removeBold">{{ str_replace('"', '', @$auction->get->interested_lease_option) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->interested_lease_option === 'Yes' && @$auction->get->lease_fee_type != null)
                        @php
                            $leaseFeeType = @$auction->get->lease_fee_type ?? '';
                            $leaseFeeCombined = '—';
                            
                            if ($leaseFeeType === 'flat' && @$auction->get->lease_fee_flat) {
                                $leaseFeeCombined = $fmtMoney(@$auction->get->lease_fee_flat);
                            } elseif ($leaseFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->lease_fee_percentage) {
                                $leaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage) . ' of Gross Lease Value';
                            } elseif ($leaseFeeType === 'Percentage of Monthly Rent' && @$auction->get->lease_fee_percentage_monthly_rent) {
                                $display = $fmtPercent(@$auction->get->lease_fee_percentage_monthly_rent) . ' of Monthly Rent';
                                if (@$auction->get->lease_fee_percentage_monthly_number) {
                                    $display .= ' x ' . @$auction->get->lease_fee_percentage_monthly_number . ' Months';
                                }
                                $leaseFeeCombined = $display;
                            } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                $leaseFeeCombined = $joinParts([
                                    $fmtMoney(@$auction->get->lease_fee_flat_combo),
                                    @$auction->get->lease_fee_percentage_combo ? ($fmtPercent(@$auction->get->lease_fee_percentage_combo) . ' of Gross Lease Value') : null,
                                ]) ?? '—';
                            } elseif ($leaseFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->lease_fee_percentage_net) {
                                $leaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage_net) . ' of Net Aggregate Rent';
                            } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                                $leaseFeeCombined = $joinParts([
                                    $fmtMoney(@$auction->get->lease_fee_flat_combo_net),
                                    @$auction->get->lease_fee_percentage_combo_net ? ($fmtPercent(@$auction->get->lease_fee_percentage_combo_net) . ' of Net Aggregate Rent') : null,
                                ]) ?? '—';
                            } elseif (strtolower($leaseFeeType) === 'other' && @$auction->get->lease_fee_other) {
                                $leaseFeeCombined = @$auction->get->lease_fee_other;
                            } elseif ($leaseFeeType) {
                                $leaseFeeCombined = $leaseFeeType;
                            }
                        @endphp
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Buyer's Broker Lease Fee:
                            <span class="removeBold">{{ $leaseFeeCombined }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->interested_lease_option != null || @$auction->get->lease_fee_type != null)
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        @endif

                        <!-- Lease-Option Details Sub-section -->
                        @if (@$auction->get->interested_lease_option_agreement != null)
                        <h5 class="mt-3 mb-2"><strong>Lease-Option Details:</strong></h5>
                        @endif

                        @if (@$auction->get->interested_lease_option_agreement != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Interested in a Lease-Option Agreement:
                            <span class="removeBold">{{ str_replace('"', '', @$auction->get->interested_lease_option_agreement) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->interested_lease_option_agreement === 'Yes')
                            @if (@$auction->get->lease_value != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Compensation for Creating the Lease-Option Agreement:
                                <span class="removeBold">
                                    @if (@$auction->get->lease_type === 'percent')
                                        {{ @$auction->get->lease_value }}% of Total Purchase Price
                                    @else
                                        {{ \App\Support\Format::money(@$auction->get->lease_value) }}
                                    @endif
                                </span>
                            </div>
                            @endif

                            @if (@$auction->get->purchase_value != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Compensation if Purchase Option is Exercised:
                                <span class="removeBold">
                                    @if (@$auction->get->purchase_type === 'percent')
                                        {{ @$auction->get->purchase_value }}% of Total Purchase Price
                                    @else
                                        {{ \App\Support\Format::money(@$auction->get->purchase_value) }}
                                    @endif
                                </span>
                            </div>
                            @endif
                        @endif

                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                        <!-- Legal Terms Sub-section -->
                        @if (@$auction->get->protection_period != null || @$auction->get->early_termination_fee_option != null || @$auction->get->retainer_fee_option != null || @$auction->get->agency_agreement_timeframe != null)
                        <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>
                        @endif

                        @if (@$auction->get->protection_period != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Protection Period Timeframe:
                            <span class="removeBold">{{ @$auction->get->protection_period }} Days</span>
                        </div>
                        @endif

                        @if (@$auction->get->early_termination_fee_option != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Early Termination Fee:
                            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->early_termination_fee_option, @$auction->get->early_termination_fee_amount ? \App\Support\Format::money(@$auction->get->early_termination_fee_amount) : null) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->retainer_fee_option != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Retainer Fee:
                            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->retainer_fee_option, @$auction->get->retainer_fee_amount ? \App\Support\Format::money(@$auction->get->retainer_fee_amount) : null) }}</span>
                        </div>
                        @if (in_array(strtolower(@$auction->get->retainer_fee_option), ['yes']))
                            @if (@$auction->get->retainer_fee_application)
                            @php $formattedRetainer = \App\Support\CompensationFormatter::formatRetainerFeeApplication(@$auction->get->retainer_fee_application); @endphp
                            @if (!empty($formattedRetainer))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Retainer Fee Application:
                                <span class="removeBold">{{ $formattedRetainer }}</span>
                            </div>
                            @endif
                            @endif
                        @endif
                        @endif

                        @if (@$auction->get->agency_agreement_timeframe != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Buyer Agency Agreement Timeframe:
                            <span class="removeBold">{{ @$auction->get->agency_agreement_timeframe === 'custom' ? @$auction->get->agency_agreement_custom : str_replace('"', '', @$auction->get->agency_agreement_timeframe) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->protection_period != null || @$auction->get->early_termination_fee_option != null || @$auction->get->retainer_fee_option != null || @$auction->get->agency_agreement_timeframe != null)
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        @endif

                        <!-- Brokerage Relationship Sub-section -->
                        @if (@$auction->get->brokerage_relationship != null)
                        <h5 class="mt-3 mb-2"><strong>Brokerage Relationship:</strong></h5>
                        @endif

                        @if (@$auction->get->brokerage_relationship != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Brokerage Relationship:
                            <span class="removeBold">{{ str_replace('"', '', @$auction->get->brokerage_relationship) }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->brokerage_relationship != null)
                        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
                        @endif

                        <!-- Additional Terms Sub-section -->
                        @if (@$auction->get->additional_details_broker != null)
                        <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>
                        @endif

                        @if (@$auction->get->additional_details_broker != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Additional Terms:
                            <span class="removeBold">{{ @$auction->get->additional_details_broker }}</span>
                        </div>
                        @endif

                        </div>
                        <!-- End Broker Compensation Section -->
                        <hr />
                        <div class="card-header section-header">
                            <h4 class="section-title">Buyer’s Info</h4>
                        </div>
                        @if (!empty($auction->get->first_name))
                            <div class="col-md-12 col-12 pt-2 fw-bold">First
                                Name:
                                <span class="removeBold">
                                    {{ $auction->get->first_name }}
                                </span>
                            </div>
                        @endif

                        @if (!empty($auction->get->current_status))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                Buyer's Current Status:
                                <span class="removeBold">{{ $auction->get->current_status }}</span>
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
                @if($auser)
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
                @endif
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
                    <a href="{{ route('buyer.edit-auction', ['auctionId' => $auction->id]) }}" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-edit me-1"></i> Edit Listing
                    </a>
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
        <a href="{{ route('auction-chat', ['buyer-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
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
        // Safe is_sold check: raw DB column is varchar ('0','false','true','1') — never use raw value as bool
        $isSold = in_array($auction->is_sold, [true, 'true', 1, '1'], true);
        @endphp

        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
        @if (!$isExpired && !$isSold && $auction->status !== 'Pending' && $auction->status !== 'Hired Agent')
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
            onclick="window.location='{{ route('agent.buyer.agent.auction.bid', @$auction->id) }}';">
            <span class="bid">Bid Now</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>
        @endif

        @elseif($auction->status === 'Hired Agent' || $isSold)
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
        {{-- Expiry catch-all: distinguish BP (timer already showed "Bidding Ended") from Traditional --}}
        @if ($isBiddingPeriodListing)
        {{-- BP: "Bidding Ended" already rendered by the timer block above — no duplicate needed --}}
        @else
        <div class="alert alert-secondary text-center mb-2">
            <i class="fa fa-calendar-times me-1"></i> <strong>This listing has expired</strong>
        </div>
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

        @php
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            $bidsByOrder    = $auction->bids->sortBy('created_at');
            // Key by user_id so the same agent always gets the same number.
            $agentNumberMap     = [];  // bid_id  → agent number
            $agentUserNumberMap = [];  // user_id → agent number
            $agentIdx           = 0;
            foreach ($bidsByOrder as $orderedBid) {
                $uid = data_get($orderedBid, 'user_id');
                if (!isset($agentUserNumberMap[$uid])) {
                    $agentIdx++;
                    $agentUserNumberMap[$uid] = $agentIdx;
                }
                $agentNumberMap[$orderedBid->id] = $agentUserNumberMap[$uid];
            }
        @endphp

        {{-- Last Bidder Info - outside the card (matches Tenant view layout) --}}
        @if ($lowest_bidder)
            <p class="mb-3"><b>Agent {{ $agentNumberMap[$lowest_bidder->id] ?? '?' }}</b> was the last bidder.</p>
        @else
            <p class="mb-3">No one has bid on this auction.</p>
        @endif

        <div class="card higestBider" id="bids-section">
            <div class="card-body card-body-padding">
                <div id="buyerBidsList">
                                @php
                                // Reload meta once before bid loop to guarantee fresh listing baseline from DB.
                                $auction->load('meta');
                                @endphp

                                @foreach (@$auction->bids as $bid)
                                    @php
                                        $bidId = data_get($bid, 'id');
                                        $bidUser = data_get($bid, 'user_id');
                                        $isBidOwner = ($bidUser == $auth_id);
                                        $_isAgentViewer = $auth_id && auth()->check() && in_array(auth()->user()->user_type ?? '', ['agent']);
                                        $canViewBid = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $_isAgentViewer && $userHasBid) || ($isTraditionalListing && $_isAgentViewer);
                                        if (!$canViewBid && $_isAgentViewer) { continue; }
                                        $agentNumber = $agentNumberMap[$bidId] ?? $loop->iteration;
                                        $bidAccepted = data_get($bid, 'accepted', '0');
                                        $isExpiredBid = $isExpired ?? false;
                                        $canEditWithdraw = $isBidOwner && !$isExpiredBid && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';
                                        $servicesList  = (array) data_get($bid, 'get.services', []);
                                        $servicesCount = count($servicesList);
                                        $commissionSummary = data_get($bid, 'commission_structure', data_get($bid, 'get.commission_structure', ''));
                                        $headerBg = $bidAccepted === 'accepted' ? '#d4edda' : ($bidAccepted === 'rejected' ? '#f8d7da' : '#f8f9fa');

                                        // === MATCH SCORE — baseline-driven (BuyerBidMatchScoreHelper) ===
                                        // $auction->meta reloaded before loop; $bid->get queries DB directly on each call.
                                        $auctionPropType = data_get($auction, 'get.property_type', '');
                                        $listingBaselineData = $auction->meta->pluck('meta_value', 'meta_key')->toArray();
                                        $currentBidData = (array) $bid->get;

                                        // Check for buyer-countered terms (BuyerCounterTerm) — buyer counters the agent.
                                        // buyer_agent_auction_id = auction ID; parent_counter_id = bid ID (per-bid scope).
                                        $latestBuyerCounter = \App\Models\BuyerCounterTerm::with('meta')
                                            ->where('buyer_agent_auction_id', $auction->id)
                                            ->where('parent_counter_id', $bidId)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

                                        // Check for agent-countered terms (BuyerCounterBidding) — agent counters the buyer
                                        $latestAgentCounter = \App\Models\BuyerCounterBidding::with('meta')
                                            ->where('buyer_agent_auction_bid_id', $bidId)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

                                        // Card score ALWAYS uses original listing baseline to ensure consistent
                                        // denominator across all bids on the same listing.
                                        $baselineData = $listingBaselineData;

                                        // Check if bid is in countered state
                                        $hasCounterBids = $latestBuyerCounter || $latestAgentCounter;

                                        // Direction: true = listing OWNER sent the most recent counter.
                                        // BuyerCounterTerm ($latestBuyerCounter) = owner's counter.
                                        // BuyerCounterBidding ($latestAgentCounter) = bidding agent's counter-back.
                                        $_buyerCardLatestFromOwner = false;
                                        if ($latestBuyerCounter && $latestAgentCounter) {
                                            $_buyerCardLatestFromOwner = $latestBuyerCounter->created_at >= $latestAgentCounter->created_at;
                                        } elseif ($latestBuyerCounter) {
                                            $_buyerCardLatestFromOwner = true; // only owner counter exists
                                        }
                                        // elseif only $latestAgentCounter → agent sent latest → remains false
                                        $bidStatusDisplay = match($bidAccepted) {
                                            'accepted' => 'Accepted',
                                            'rejected' => 'Rejected',
                                            'countered' => 'Countered',
                                            default => $hasCounterBids ? 'Countered' : 'Active',
                                        };
                                        $bidStatusColor = match($bidStatusDisplay) {
                                            'Accepted' => '#28a745',
                                            'Rejected' => '#dc3545',
                                            'Countered' => '#ffc107',
                                            default => '#1a4a6e',
                                        };

                                        $score = \App\Helpers\BuyerBidMatchScoreHelper::calculate($baselineData, $currentBidData, null, $auctionPropType);
                                        $overallScore     = $score['overall_percent'];
                                        $scoreColor       = \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$overallScore);
                                        $brokerMismatches = $score['changed_terms'] ?? [];
                                        $brokerAdded      = $score['added_terms'] ?? [];
                                        $buyerBaselineLabel = $isListingOwner ? 'Your Original Terms' : "Buyer's Original Request";
                                        $servicesExtraCount = $score['services_extra_count'] ?? 0;
                                        $matchedServices  = $score['matched_services'] ?? [];
                                        $missingServices  = $score['missing_services'] ?? [];
                                        $extraServices    = $score['extra_services'] ?? [];
                                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                                        $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';
                                    @endphp
                                    <!-- Bid Card - Collapsible Accordion Design -->
                                    <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                                    <!-- A) Card Header - Clickable to expand/collapse (using custom JS toggle) -->
                                    <div class="card-header d-flex justify-content-between align-items-center bid-accordion-header"
                                        style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
                                        data-target="bidCollapse-{{ $bidId }}"
                                        aria-expanded="false">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fa fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
                                            <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
                                        </div>
                                        <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusDisplay }}</span>
                                    </div>

                                    <!-- Collapsible Content - Default collapsed (custom toggle, no Bootstrap) -->
                                    <div class="bid-collapse-content" id="bidCollapse-{{ $bidId }}" style="display: none;">

                                        @php
                                            // Pre-compute compact card variables (Tenant-parity)
                                            $cardServicesMatched   = $score['services_matched_count'] ?? 0;
                                            $cardServicesTotal     = $score['services_baseline_total'] ?? 0;
                                            $cardServicesExtraCount = $score['services_extra_count'] ?? 0;

                                            // Determine if we have a dual-score situation (latest buyer counter or agent counter exists)
                                            $cardShowDualScore = false;
                                            $cardOriginalScore = null;
                                            $cardLatestCounterScore = null;
                                            $cardCounterLabel = 'vs. Latest Counter Terms';
                                            if ($latestBuyerCounter) {
                                                // $score is already listing-based; compute counter comparison separately
                                                $cardOriginalScore = $score;
                                                $cardLatestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate($latestBuyerCounter->getAllMeta(), $currentBidData, null, $auctionPropType);
                                                $cardCounterLabel = $isListingOwner ? 'vs. Your Counter Terms' : "vs. Buyer's Counter Terms";
                                                $cardShowDualScore = true;
                                            } elseif ($latestAgentCounter) {
                                                // Listing owner has sent a counter offer via BuyerCounterBidding
                                                // Left: bid vs original listing; Right: bid vs owner's counter offer terms
                                                $cardOriginalScore = $score;
                                                $agentCounterMeta = $latestAgentCounter->getAllMeta();
                                                $cardLatestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate($agentCounterMeta, $currentBidData, null, $auctionPropType);
                                                $cardCounterLabel = $isListingOwner ? 'vs. Your Counter Offer' : "vs. Owner's Counter Offer";
                                                $cardShowDualScore = true;
                                            }
                                            $cardGetScoreColor = fn($pct) => \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$pct);

                                            // Match score visibility: listing owner OR bid owner OR BP agent with a bid
                                            $cardIsAgentViewer = $auth_id && auth()->user() && in_array(auth()->user()->user_type ?? '', ['agent']);
                                            $cardShowMatchScoreOnCard = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $cardIsAgentViewer && $userHasBid);
                                            /**
                                             * ZERO-BASELINE / NO-DATA GUARD
                                             *
                                             * If there is no comparable baseline match data, do not display 100%.
                                             * Render "No match data available" instead.
                                             *
                                             * This behavior is locked by QA baseline documentation.
                                             * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
                                             */
                                            $cardHasAnyBaseline = (($score['broker_comp_total'] ?? 0) > 0 || $cardServicesTotal > 0);

                                        @endphp

                                        <div class="card-body" style="padding: 20px;">
                                            <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

                                            {{-- Counter Offer Notice Banner — visible immediately on accordion expand (owner/agent only) --}}
                                            @if (($latestAgentCounter || $latestBuyerCounter) && ($isListingOwner || $isBidOwner))
                                            <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3"
                                                 style="background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 0.9rem;">
                                                <i class="fa fa-exchange-alt mt-1" style="color: #e6a800; flex-shrink: 0;"></i>
                                                <div>
                                                    @if ($_buyerCardLatestFromOwner && $isListingOwner)
                                                        <strong>Counter Offer Sent.</strong>
                                                    @elseif ($_buyerCardLatestFromOwner && $isBidOwner)
                                                        <strong>Counter Offer Received.</strong>
                                                    @elseif (!$_buyerCardLatestFromOwner && $isBidOwner)
                                                        <strong>Counter Offer Sent.</strong>
                                                    @elseif (!$_buyerCardLatestFromOwner && $isListingOwner)
                                                        <strong>Counter Offer Received.</strong>
                                                    @endif
                                                </div>
                                            </div>
                                            @endif

                                            {{-- ── Counter action row — directly on bid card ── --}}
                                            @if (($latestAgentCounter || $latestBuyerCounter) && ($isListingOwner || $isBidOwner) && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected')
                                            @if (($_buyerCardLatestFromOwner && $isListingOwner) || (!$_buyerCardLatestFromOwner && $isBidOwner))
                                            {{-- WAITING: single row — View CT + Edit CT --}}
                                            <div class="d-flex gap-2 align-items-center mb-2">
                                                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa fa-eye me-1"></i> View Counter Terms
                                                </a>
                                                @if ($isListingOwner)
                                                <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa fa-edit me-1"></i> Edit Counter Terms
                                                </a>
                                                @else
                                                <a href="{{ route('agent.buyer.hire.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa fa-edit me-1"></i> Edit Counter Terms
                                                </a>
                                                @endif
                                            </div>
                                            @else
                                            {{-- RESPONSE: View CT only — Accept/Counter Back/Reject are on View Counter Terms page --}}
                                            <div class="d-flex align-items-center mb-2">
                                                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa fa-eye me-1"></i> View Counter Terms
                                                </a>
                                            </div>
                                            @endif
                                            @endif

                                            <!-- Offered Services Summary Line -->
                                            <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                                <span style="font-weight: 600;">Offered Services:</span>
                                                <span style="color: #28a745; font-weight: 600;">{{ $cardServicesTotal > 0 ? $cardServicesMatched.'/'.$cardServicesTotal : 'No services requested' }}</span>{{ $cardServicesTotal > 0 ? ' matched' : '' }}
                                                @if ($cardServicesTotal > 0 && $cardServicesExtraCount > 0)
                                                    <span class="text-muted ms-2">&bull; {{ $cardServicesExtraCount }} extra</span>
                                                @endif
                                                @if ($cardServicesTotal > 0 && count($missingServices) > 0)
                                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ count($missingServices) }} missing</span>
                                                @endif
                                            </p>
                                            @if ($cardServicesExtraCount > 0)
                                            <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: 4px 6px;">
                                                <span style="font-size: 0.9rem; line-height: 1.4;">&#11088;</span>
                                                <span style="font-weight: 500; color: #856404; font-size: 0.95rem;" title="Extra services were included by the Agent beyond the Buyer&#39;s original request. These do not increase the match score but may provide additional value.">Extra Value Added: {{ $cardServicesExtraCount }} {{ $cardServicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
                                                <span class="text-muted" style="font-size: 0.78rem; font-style: italic;">&mdash; does not affect match score</span>
                                            </div>
                                            @endif

                                            <!-- Terms Match Row -->
                                            @php
                                                $cardBrokerTotal = $score['broker_comp_total'] ?? 0;
                                                $cardBrokerMatched = $score['broker_comp_matched'] ?? 0;
                                                $cardTermsChangedCount = $score['terms_changed_count'] ?? 0;
                                                $cardTermsAddedCount = $score['terms_added_count'] ?? 0;
                                            @endphp
                                            @if ($cardHasAnyBaseline && $cardBrokerTotal > 0)
                                            <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                                <span style="font-weight: 600;">Terms Match:</span>
                                                <span style="color: #28a745; font-weight: 600;">{{ $cardBrokerMatched }}/{{ $cardBrokerTotal }} matched</span>
                                                @if ($cardTermsChangedCount > 0)
                                                <span class="ms-2" style="color: #dc3545;">&bull; {{ $cardTermsChangedCount }} changed</span>
                                                @endif
                                                @if ($cardTermsAddedCount > 0)
                                                <span class="text-muted ms-2">&bull; {{ $cardTermsAddedCount }} added</span>
                                                @endif
                                                @php $cardTermsMissingCount = max(0, $cardBrokerTotal - $cardBrokerMatched - $cardTermsChangedCount); @endphp
                                                @if ($cardTermsMissingCount > 0)
                                                <span class="ms-2" style="color: #dc3545;">&bull; {{ $cardTermsMissingCount }} missing</span>
                                                @endif
                                            </p>
                                            <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
                                            @elseif ($cardHasAnyBaseline && $cardBrokerTotal === 0)
                                            <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                                <span style="font-weight: 600;">Terms Match:</span>
                                                <span class="text-muted">&mdash;</span>
                                            </p>
                                            @endif

                                            <hr style="margin: 15px 0; border-color: #e0e0e0;">

                                            <!-- B2) Match Score Summary (Compact Display on Bid Card) -->
                                            @if ($cardShowMatchScoreOnCard && $cardHasAnyBaseline)
                                            <div class="match-score-summary mb-3 p-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.88rem;">
                                                @if ($cardShowDualScore && $cardOriginalScore && $cardLatestCounterScore)
                                                {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                                                <div class="mb-2">
                                                    <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                                        <i class="fa fa-chart-pie me-2"></i>Match Summary
                                                    </span>
                                                </div>
                                                <div class="row g-2 mb-2">
                                                    @php $osColor = $cardGetScoreColor($cardOriginalScore['overall_percent']); @endphp
                                                    <div class="col-6">
                                                        <div class="p-2 rounded" style="background: #fff; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                                <span class="badge" style="background: {{ $osColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $cardOriginalScore['overall_percent'] }}%</span>
                                                            </div>
                                                            <div style="font-size: 0.75rem; color: #6c757d;">vs. Buyer's Original Request</div>
                                                            <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardOriginalScore['services_match_percent']) }};">Services {{ $cardOriginalScore['services_match_percent'] }}%</div>
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardOriginalScore['terms_match_percent']) }};">Terms {{ $cardOriginalScore['terms_match_percent'] }}%</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @php $lcColor = $cardGetScoreColor($cardLatestCounterScore['overall_percent']); @endphp
                                                    <div class="col-6">
                                                        <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor }};">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                                <span class="badge" style="background: {{ $lcColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $cardLatestCounterScore['overall_percent'] }}%</span>
                                                            </div>
                                                            <div style="font-size: 0.75rem; color: #6c757d;">{{ $cardCounterLabel }}</div>
                                                            <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardLatestCounterScore['services_match_percent']) }};">Services {{ $cardLatestCounterScore['services_match_percent'] }}%</div>
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardLatestCounterScore['terms_match_percent']) }};">Terms {{ $cardLatestCounterScore['terms_match_percent'] }}%</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="small" style="color: #6c757d; font-style: italic; font-size: 0.76rem;">
                                                    <i class="fa fa-info-circle me-1"></i>Added services or terms do not increase either score.
                                                </div>
                                                @else
                                                {{-- SINGLE SCORE --}}
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                                        <i class="fa fa-chart-pie me-2"></i>Match Score
                                                    </span>
                                                    <span class="badge" style="background: {{ $scoreColor }}; font-size: 1rem; padding: 6px 12px; color: white;">
                                                        {{ $overallScore }}%
                                                    </span>
                                                </div>
                                                <div class="row g-2 small">
                                                    <div class="col-6">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Services Match:</span>
                                                            <span style="color: {{ $cardGetScoreColor($score['services_percent'] ?? 100) }}; font-weight: 600;">{{ $score['services_percent'] ?? 100 }}%</span>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.8rem;">
                                                            {{ ($score['services_baseline_total'] ?? 0) > 0 ? 'Matched: '.($score['services_matched_count'] ?? 0).'/'.($score['services_baseline_total'] ?? 0) : 'No services requested' }}
                                                            @if (($score['services_baseline_total'] ?? 0) > 0 && $cardServicesExtraCount > 0) &bull; Extra: {{ $cardServicesExtraCount }}@endif
                                                            @if (($score['services_baseline_total'] ?? 0) > 0 && count($missingServices) > 0) &bull; Missing: {{ count($missingServices) }}@endif
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Terms Match:</span>
                                                            <span style="color: {{ $cardGetScoreColor($score['broker_comp_percent'] ?? 100) }}; font-weight: 600;">{{ $score['broker_comp_percent'] ?? 100 }}%</span>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.8rem;">
                                                            {{ ($score['broker_comp_total'] ?? 0) > 0 ? 'Matched: '.($score['broker_comp_matched'] ?? 0).'/'.($score['broker_comp_total'] ?? 0) : 'No terms provided' }}
                                                            @if (($score['broker_comp_total'] ?? 0) > 0 && ($score['terms_changed_count'] ?? 0) > 0) &bull; Changed: {{ $score['terms_changed_count'] }}@endif
                                                            @if (($score['broker_comp_total'] ?? 0) > 0 && ($score['terms_added_count'] ?? 0) > 0) &bull; Added: {{ $score['terms_added_count'] }}@endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-2 small text-muted">
                                                    <i class="fa fa-info-circle me-1"></i>Compared to: {{ $buyerBaselineLabel }}
                                                </div>
                                                <div class="mt-1 small" style="color: #6c757d; font-style: italic; font-size: 0.78rem;">
                                                    Match Score compares this bid to the Buyer's original request. Added services or terms do not increase the score.
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            <!-- D) View Full Bid Link - visibility rules by listing type and user -->
                                            @if ($isListingOwner || $isBidOwner)
                                            {{-- Listing Owner or Bid Owner: Full access --}}
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                               style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                                View Full Bid
                                            </a>
                                            @elseif ($isBiddingPeriodListing && $cardIsAgentViewer && !$isBidOwner)
                                            {{-- Bidding Period: Agent viewing another agent's bid - show limited view button --}}
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#limitedBidModal{{ data_get($bid, 'id') }}"
                                               style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                                View Full Services & Broker Compensation Terms
                                            </a>
                                            @else
                                            <span style="color: #888; font-style: italic; font-size: 0.95rem;">
                                                <i class="fa fa-lock me-1"></i> Private - visible only to listing creator
                                            </span>
                                            @endif

                                            <!-- E) Edit Actions for Bid Owner - Same row, matched sizing -->
                                            @if ($canEditWithdraw)
                                            <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                                <a href="{{ route('agent.buyer.agent.auction.bid', $auction->id) }}?edit={{ $bidId }}" class="btn btn-primary bid-action-btn">
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
                                                @php
                                                    $bidOwnerSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', $bidId)->where('agent_user_id', data_get($bid, 'user_id'))->first();
                                                @endphp
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

                                                    <!-- PRIVATE DATA SECTION - Visible to listing owner and bid owner -->
                                                    @if (data_get($auction, 'user_id') == $auth_id || data_get($bid, 'user_id') == $auth_id)

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
                                                                            <i class="fa fa-lock me-2"></i> Private
                                                                            Compensation & Agreement Terms
                                                                        </h5>
                                                                    </div>
                                                                    <div class="modal-body"
                                                                        style="background: #fafafa; padding: 25px;">
                                                                        @include('partials.bid_detail_body.buyer')
                                                                    </div>
                                                                    @php
                                                                        // Compute modal-footer state — available variables: $bidAccepted, $auction, $bid, $auth_id
                                                                        $_mfRawB    = data_get($bid, 'accepted', '0');
                                                                        $_mfTermB   = in_array((string)$_mfRawB, ['accepted', 'rejected'], true);
                                                                        // Use $hasCounterBids (computed in outer bid loop) — catches counters from
                                                                        // either the listing owner (BuyerCounterTerm) or the bidding agent (BuyerCounterBidding).
                                                                        $_mfCounterB = !$_mfTermB && $hasCounterBids;
                                                                        $mfStateB   = $_mfCounterB
                                                                            ? 'countered'
                                                                            : (in_array($_mfRawB, [null, 0, '0', ''], true) ? '0' : (string)$_mfRawB);
                                                                        $mfOwnerIdB    = data_get($auction, 'user_id');
                                                                        $mfOwnerFirstB = data_get($auction, 'user.first_name', '');
                                                                        $mfOwnerLastB  = data_get($auction, 'user.last_name', '');
                                                                        $mfAgentFirstB = data_get($bid, 'user.first_name', '');
                                                                        $mfAgentLastB  = data_get($bid, 'user.last_name', '');
                                                                        $mfIsOwnerB    = ((int)$auth_id === (int)$mfOwnerIdB);
                                                                    @endphp
                                                                    <div class="modal-footer"
                                                                        style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                                                                        {{-- Confidential notice --}}
                                                                        <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                                                            <i class="fa fa-shield-alt me-2"></i>
                                                                            <strong>Confidential:</strong> This information is private and only visible to you.
                                                                        </div>

                                                                        {{-- ── Listing owner: action buttons when bid is undecided ── --}}
                                                                        @if ($mfStateB === '0' && $mfIsOwnerB && !$isSold)
                                                                            @if ($isTraditionalListing && $isExpired)
                                                                            <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                                                <i class="fa fa-clock me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
                                                                            </div>
                                                                            @else
                                                                            <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                                                                                <form action="{{ route('buyer.hire.agent.auction.bid.accept') }}" method="POST" style="margin: 0;"
                                                                                      onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                                                                                    @csrf
                                                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                                    <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                                                    <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 0.95rem; background-color: #28a745 !important; border-color: #28a745 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                                                        <i class="fa fa-check me-1"></i> Accept Bid
                                                                                    </button>
                                                                                </form>
                                                                                <a href="{{ route('buyer.counter-terms', data_get($bid, 'id')) }}"
                                                                                   class="btn btn-primary" style="padding: 10px 20px; font-size: 0.95rem; background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                                                    <i class="fa fa-exchange-alt me-1"></i> Counter Bid
                                                                                </a>
                                                                                <form action="{{ route('buyer.hire.agent.auction.bid.reject') }}" method="POST" style="margin: 0;"
                                                                                      onsubmit="return confirm('Are you sure you want to reject this bid?');">
                                                                                    @csrf
                                                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                                    <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                                                    <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-size: 0.95rem; background-color: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                                                        <i class="fa fa-times me-1"></i> Reject Bid
                                                                                    </button>
                                                                                </form>
                                                                            </div>
                                                                            @endif
                                                                        @endif

                                                                        {{-- ── Accepted state ── --}}
                                                                        @if ($mfStateB === 'accepted')
                                                                        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                                                                            <i class="fa fa-check-circle me-1"></i>
                                                                            @if ($mfIsOwnerB) This bid has been accepted.
                                                                            @else {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }} accepted this bid.
                                                                            @endif
                                                                        </div>
                                                                        @php $mfBidSummaryB = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->where('agent_user_id', data_get($bid, 'user_id'))->first(); @endphp
                                                                        @if ($mfBidSummaryB && ($mfIsOwnerB || data_get($bid, 'user_id') == Auth::id()))
                                                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                                                            <a href="{{ route('accepted-bid-summary.view', $mfBidSummaryB->id) }}" class="btn btn-outline-primary btn-sm">
                                                                                <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                                                                            </a>
                                                                            @if (data_get($bid, 'user_id') == Auth::id() && !$mfBidSummaryB->isAgentSigned())
                                                                            <a href="{{ route('accepted-bid-summary.sign-form', $mfBidSummaryB->id) }}" class="btn btn-primary btn-sm">
                                                                                <i class="fa fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                                                                            </a>
                                                                            @endif
                                                                            @if ($mfIsOwnerB && !$mfBidSummaryB->isTenantSigned())
                                                                            <a href="{{ route('accepted-bid-summary.sign-form', $mfBidSummaryB->id) }}" class="btn btn-primary btn-sm">
                                                                                <i class="fa fa-signature me-1"></i> Buyer: E-Sign Acknowledgement
                                                                            </a>
                                                                            @endif
                                                                            @if ($mfBidSummaryB->isFullySigned())
                                                                            <a href="{{ route('accepted-bid-summary.download-pdf', $mfBidSummaryB->id) }}" class="btn btn-success btn-sm">
                                                                                <i class="fa fa-download me-1"></i> Download Signed PDF
                                                                            </a>
                                                                            @endif
                                                                        </div>
                                                                        @endif

                                                                        {{-- ── Rejected state ── --}}
                                                                        @elseif ($mfStateB === 'rejected')
                                                                        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                                                                            <i class="fa fa-times-circle me-1"></i>
                                                                            @if ($mfIsOwnerB) This bid has been rejected.
                                                                            @else {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }} rejected this bid.
                                                                            @endif
                                                                        </div>

                                                                        {{-- ── Countered state ── --}}
                                                                        @elseif ($mfStateB === 'countered')
                                                                        @php
                                                                            // Viewer sent latest = owner viewer + owner sent latest, OR agent viewer + agent sent latest.
                                                                            $_mfBuyerViewerSentLatest = ($isListingOwner && $_buyerCardLatestFromOwner)
                                                                                                     || ($isBidOwner   && !$_buyerCardLatestFromOwner);
                                                                        @endphp
                                                                        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                                                            <i class="fa fa-exchange-alt me-1"></i>
                                                                            @if ($_mfBuyerViewerSentLatest)
                                                                                <strong>Counter Offer Sent.</strong>
                                                                            @else
                                                                                <strong>Counter Offer Received.</strong>
                                                                            @endif
                                                                        </div>
                                                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                                                            @if ($_mfBuyerViewerSentLatest)
                                                                            {{-- Viewer sent latest — waiting: View CT + Edit CT --}}
                                                                            <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                                <i class="fa fa-eye me-1"></i> View Counter Terms
                                                                            </a>
                                                                            <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                                <i class="fa fa-edit me-1"></i> Edit Counter Terms
                                                                            </a>
                                                                            @else
                                                                            {{-- Other party sent latest: View CT only — actions on View Counter Terms page --}}
                                                                            <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                                <i class="fa fa-eye me-1"></i> View Counter Terms
                                                                            </a>
                                                                            @endif
                                                                        </div>

                                                                        {{-- ── Pending state ── --}}
                                                                        @elseif ($mfStateB === '0')
                                                                        @if (data_get($bid, 'user_id') == Auth::id())
                                                                        <div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                                                            ⏳ Waiting for a response from {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }}...
                                                                        </div>
                                                                        @else
                                                                        <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                                                            ⏳ Bid from {{ trim($mfAgentFirstB . ' ' . $mfAgentLastB) }} is pending.
                                                                        </div>
                                                                        @endif
                                                                        @endif

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

                                                    {{-- Limited Bid Modal: Bidding Period - Agents viewing competitor bids --}}
                                                    @if ($isBiddingPeriodListing && $cardIsAgentViewer && !$isBidOwner && !$isListingOwner)
                                                    @php
                                                        $limitedBrokerMismatches = $brokerMismatches ?? [];
                                                        $limitedIsMismatch = function($field) use ($limitedBrokerMismatches) {
                                                            return isset($limitedBrokerMismatches[$field]);
                                                        };
                                                        $limitedCheckPurchaseFeeFieldMismatch = function() use ($limitedBrokerMismatches) {
                                                            $feeFields = ['purchase_fee_type','purchase_fee_flat','purchase_fee_percentage',
                                                                'purchase_fee_flat_combo','purchase_fee_percentage_combo','purchase_fee_other'];
                                                            foreach ($feeFields as $f) { if (isset($limitedBrokerMismatches[$f])) return true; }
                                                            return false;
                                                        };
                                                        $limitedMismatchStyle = 'background-color: #ffe6e6; padding: 2px 8px; border-radius: 4px; border-left: 3px solid #dc3545;';
                                                        // Build purchase fee display for limited modal
                                                        $lpft = data_get($bid, 'get.purchase_fee_type', '');
                                                        $limitedPurchaseFeeDisplay = '';
                                                        if ($lpft === 'Flat Fee') {
                                                            $v = data_get($bid, 'get.purchase_fee_flat');
                                                            $limitedPurchaseFeeDisplay = $v ? '$' . number_format((float)preg_replace('/[^0-9.]/', '', $v), 2) : '';
                                                        } elseif ($lpft === 'Percentage of the Total Purchase Price') {
                                                            $v = data_get($bid, 'get.purchase_fee_percentage');
                                                            $limitedPurchaseFeeDisplay = $v ? $v . '% of Total Purchase Price' : '';
                                                        } elseif ($lpft === 'Percentage of the Total Purchase Price + Flat Fee') {
                                                            $p1 = data_get($bid, 'get.purchase_fee_flat_combo'); $p2 = data_get($bid, 'get.purchase_fee_percentage_combo');
                                                            $limitedPurchaseFeeDisplay = trim(($p1 ? '$'.number_format((float)preg_replace('/[^0-9.]/','',$p1),2) : '') . ($p1 && $p2 ? ' + ' : '') . ($p2 ? $p2.'% of Total Purchase Price' : ''), ' +');
                                                        } elseif ($lpft === 'other') { $limitedPurchaseFeeDisplay = data_get($bid, 'get.purchase_fee_other', ''); }
                                                        // Services
                                                        $limitedBidSvcs = is_array(data_get($bid, 'get.services', [])) ? data_get($bid, 'get.services', []) : (json_decode(data_get($bid, 'get.services', '[]'), true) ?: []);
                                                        $limitedBidOther = is_array(data_get($bid, 'get.other_services', [])) ? data_get($bid, 'get.other_services', []) : (json_decode(data_get($bid, 'get.other_services', '[]'), true) ?: []);
                                                        $limitedOrderedSvcs = !empty($limitedBidSvcs) ? \App\Support\ServicesFormatter::orderSelectedServices($limitedBidSvcs, $flowKey) : [];
                                                    @endphp
                                                    <div class="modal fade"
                                                        id="limitedBidModal{{ data_get($bid, 'id') }}"
                                                        tabindex="-1"
                                                        aria-labelledby="limitedBidModalLabel{{ data_get($bid, 'id') }}"
                                                        aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content" style="border-radius: 10px; border: none; position: relative; overflow: visible;">
                                                                <button type="button" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; right: 5px; top: -12px; z-index: 1055; background: #333; border: 2px solid #fff; color: #fff; font-size: 1rem; line-height: 1; font-weight: bold; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                                                                    &times;
                                                                </button>
                                                                <div class="modal-header text-white" style="background: #049399; border-bottom: none; padding: 15px 20px; border-radius: 10px 10px 0 0;">
                                                                    <h5 class="modal-title" id="limitedBidModalLabel{{ data_get($bid, 'id') }}" style="font-weight: 600;">
                                                                        <i class="fa fa-handshake me-2"></i> Services & Broker Compensation Terms
                                                                    </h5>
                                                                </div>
                                                                <div class="modal-body" style="background: #fafafa; padding: 25px;">

                                                                    {{-- Anonymous Notice --}}
                                                                    <div class="alert mb-4" style="background: #e8f4f5; color: #049399; border: none; border-radius: 6px;">
                                                                        <i class="fa fa-user-secret me-2"></i>
                                                                        <strong>Anonymous Bid:</strong> Agent identity is not displayed to other agents.
                                                                    </div>

                                                                    {{-- Match Score Panel --}}
                                                                    @if ($cardHasAnyBaseline)
                                                                    <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <h6 class="mb-0" style="color: #1a3a5c; font-weight: 600;">
                                                                                <i class="fa fa-chart-pie me-2"></i>Match Score
                                                                            </h6>
                                                                            <span class="badge" style="background: {{ $cardGetScoreColor($overallScore) }}; font-size: 1.1rem; padding: 8px 16px;">
                                                                                {{ $overallScore }}% Match
                                                                            </span>
                                                                        </div>
                                                                        <p class="small text-muted mb-3">
                                                                            Comparing to: <strong>{{ $buyerBaselineLabel }}</strong>
                                                                        </p>
                                                                        <div class="row g-3">
                                                                            <div class="col-md-6">
                                                                                <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $cardGetScoreColor($score['services_match_percent'] ?? 100) }};">
                                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                                        <span class="small fw-semibold">Services Match</span>
                                                                                        <span class="badge" style="background: {{ $cardGetScoreColor($score['services_match_percent'] ?? 100) }};">{{ $score['services_match_percent'] ?? 100 }}%</span>
                                                                                    </div>
                                                                                    <div class="small text-muted mt-1">
                                                                                        {{ ($score['services_baseline_total'] ?? 0) > 0 ? 'Matched: '.($score['services_matched_count'] ?? 0).'/'.($score['services_baseline_total'] ?? 0) : 'No services requested' }}
                                                                                        @if (($score['services_baseline_total'] ?? 0) > 0 && ($score['services_missing_count'] ?? 0) > 0) &bull; Missing: {{ $score['services_missing_count'] }}@endif
                                                                                    </div>
                                                                                    @if (($score['services_extra_count'] ?? 0) > 0)
                                                                                    <div class="small mt-1 d-flex align-items-center flex-wrap" style="gap: 3px 5px;">
                                                                                        <span>&#11088;</span>
                                                                                        <span style="font-weight: 500; color: #856404;">Extra Value Added: {{ $score['services_extra_count'] }} {{ $score['services_extra_count'] === 1 ? 'Service' : 'Services' }}</span>
                                                                                    </div>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $cardGetScoreColor($score['broker_comp_percent'] ?? 100) }};">
                                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                                        <span class="small fw-semibold">Terms Match</span>
                                                                                        <span class="badge" style="background: {{ $cardGetScoreColor($score['broker_comp_percent'] ?? 100) }};">{{ $score['broker_comp_percent'] ?? 100 }}%</span>
                                                                                    </div>
                                                                                    <div class="small text-muted mt-1">
                                                                                        {{ ($score['broker_comp_total'] ?? 0) > 0 ? 'Matched: '.($score['broker_comp_matched'] ?? 0).'/'.($score['broker_comp_total'] ?? 0) : 'No terms provided' }}
                                                                                        @if (($score['broker_comp_total'] ?? 0) > 0 && ($score['terms_changed_count'] ?? 0) > 0) &bull; Changed: {{ $score['terms_changed_count'] }}@endif
                                                                                        @if (($score['terms_added_count'] ?? 0) > 0) &bull; Added: {{ $score['terms_added_count'] }}@endif
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    @else
                                                                    <div class="text-muted text-center py-3 mb-4" style="font-size: 0.92rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; padding: 16px;">
                                                                        <i class="fa fa-info-circle me-1"></i>No match data available for this listing.
                                                                    </div>
                                                                    @endif

                                                                    {{-- Section 1: Broker Compensation & Agency Agreement Terms --}}
                                                                    @if (data_get($bid, 'get.commission_structure') ||
                                                                        data_get($bid, 'get.purchase_fee_type') ||
                                                                        data_get($bid, 'get.interested_lease_option') ||
                                                                        data_get($bid, 'get.protection_period') ||
                                                                        data_get($bid, 'get.early_termination_fee_option') ||
                                                                        data_get($bid, 'get.retainer_fee_option') ||
                                                                        data_get($bid, 'get.agency_agreement_timeframe') ||
                                                                        data_get($bid, 'get.brokerage_relationship'))
                                                                    <div class="mb-5">
                                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                                        </h6>

                                                                        {{-- A) Buyer's Broker Compensation --}}
                                                                        @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.purchase_fee_type'))
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Buyer's Broker Compensation</h6>
                                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                                @if (data_get($bid, 'get.commission_structure'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Buyer's Broker Commission Structure:</span>
                                                                                    <span style="{{ $limitedIsMismatch('commission_structure') ? $limitedMismatchStyle : '' }}">
                                                                                        {{ data_get($bid, 'get.commission_structure') }}
                                                                                        @if($limitedIsMismatch('commission_structure')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif
                                                                                    </span>
                                                                                </li>
                                                                                @endif
                                                                                @if (data_get($bid, 'get.purchase_fee_type') && $limitedPurchaseFeeDisplay)
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Buyer's Broker Purchase Fee:</span>
                                                                                    <span style="{{ $limitedCheckPurchaseFeeFieldMismatch() ? $limitedMismatchStyle : '' }}">
                                                                                        {{ $limitedPurchaseFeeDisplay }}
                                                                                        @if($limitedCheckPurchaseFeeFieldMismatch()) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif
                                                                                    </span>
                                                                                </li>
                                                                                @endif
                                                                            </ul>
                                                                        </div>
                                                                        @endif

                                                                        {{-- B) Lease Option --}}
                                                                        @if (data_get($bid, 'get.interested_lease_option') || data_get($bid, 'get.interested_lease_option_agreement'))
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Lease-Option Details</h6>
                                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                                @if (data_get($bid, 'get.interested_lease_option'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Interested in Lease Option:</span>
                                                                                    <span style="{{ $limitedIsMismatch('interested_lease_option') ? $limitedMismatchStyle : '' }}">
                                                                                        {{ data_get($bid, 'get.interested_lease_option') }}
                                                                                        @if($limitedIsMismatch('interested_lease_option')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif
                                                                                    </span>
                                                                                </li>
                                                                                @endif
                                                                                @if (data_get($bid, 'get.interested_lease_option_agreement'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Interested in Lease-Option Agreement:</span>
                                                                                    <span style="{{ $limitedIsMismatch('interested_lease_option_agreement') ? $limitedMismatchStyle : '' }}">
                                                                                        {{ data_get($bid, 'get.interested_lease_option_agreement') }}
                                                                                        @if($limitedIsMismatch('interested_lease_option_agreement')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif
                                                                                    </span>
                                                                                </li>
                                                                                @endif
                                                                            </ul>
                                                                        </div>
                                                                        @endif

                                                                        {{-- C) Legal Terms --}}
                                                                        @if (data_get($bid, 'get.protection_period') || data_get($bid, 'get.early_termination_fee_option') || data_get($bid, 'get.retainer_fee_option') || data_get($bid, 'get.agency_agreement_timeframe'))
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Legal Terms</h6>
                                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                                @if (data_get($bid, 'get.protection_period'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Protection Period:</span>
                                                                                    <span style="{{ $limitedIsMismatch('protection_period') ? $limitedMismatchStyle : '' }}">{{ data_get($bid, 'get.protection_period') }} @if($limitedIsMismatch('protection_period')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif</span>
                                                                                </li>
                                                                                @endif
                                                                                @if (data_get($bid, 'get.early_termination_fee_option'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Early Termination Fee:</span>
                                                                                    <span style="{{ $limitedIsMismatch('early_termination_fee_option') ? $limitedMismatchStyle : '' }}">{{ data_get($bid, 'get.early_termination_fee_option') }} @if($limitedIsMismatch('early_termination_fee_option')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif</span>
                                                                                </li>
                                                                                @endif
                                                                                @if (data_get($bid, 'get.retainer_fee_option'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Retainer Fee:</span>
                                                                                    <span style="{{ $limitedIsMismatch('retainer_fee_option') ? $limitedMismatchStyle : '' }}">{{ data_get($bid, 'get.retainer_fee_option') }} @if($limitedIsMismatch('retainer_fee_option')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif</span>
                                                                                </li>
                                                                                @endif
                                                                                @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Agency Agreement Timeframe:</span>
                                                                                    <span style="{{ $limitedIsMismatch('agency_agreement_timeframe') ? $limitedMismatchStyle : '' }}">{{ data_get($bid, 'get.agency_agreement_timeframe') }} @if($limitedIsMismatch('agency_agreement_timeframe')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif</span>
                                                                                </li>
                                                                                @endif
                                                                            </ul>
                                                                        </div>
                                                                        @endif

                                                                        {{-- D) Brokerage Relationship --}}
                                                                        @if (data_get($bid, 'get.brokerage_relationship'))
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Brokerage Relationship</h6>
                                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                                <li class="mb-1 d-flex justify-content-between align-items-start">
                                                                                    <span class="fw-semibold">Acceptable Brokerage Relationship:</span>
                                                                                    <span style="{{ $limitedIsMismatch('brokerage_relationship') ? $limitedMismatchStyle : '' }}">{{ data_get($bid, 'get.brokerage_relationship') }} @if($limitedIsMismatch('brokerage_relationship')) <i class="fa fa-exclamation-triangle text-danger ms-1"></i> @endif</span>
                                                                                </li>
                                                                            </ul>
                                                                        </div>
                                                                        @endif

                                                                    </div>
                                                                    @endif

                                                                    {{-- Section 2: Offered Services (Categorized) --}}
                                                                    @if (!empty($limitedBidSvcs))
                                                                    <div class="mb-4">
                                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                                                        </h6>
                                                                        @if (!empty($limitedOrderedSvcs))
                                                                            @foreach ($limitedOrderedSvcs as $catName => $catSrvs)
                                                                                @if (!empty($catSrvs))
                                                                                <div class="mb-2">
                                                                                    <strong style="font-size: 13px;">{{ $catName }}</strong>
                                                                                    <ul class="list-unstyled ps-3 mb-0" style="margin-top: 4px;">
                                                                                        @foreach ($catSrvs as $svc)
                                                                                            @if ($svc !== 'Other')
                                                                                            <li style="font-size: 12px;">{{ $svc }}</li>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                                @endif
                                                                            @endforeach
                                                                        @else
                                                                            <ul class="list-unstyled ps-3">
                                                                                @foreach ($limitedBidSvcs as $svc)
                                                                                    @if ($svc !== 'Other')
                                                                                    <li style="font-size: 12px;">{{ $svc }}</li>
                                                                                    @endif
                                                                                @endforeach
                                                                            </ul>
                                                                        @endif
                                                                        @if (!empty($limitedBidOther))
                                                                        <div class="mt-2">
                                                                            <div class="fw-semibold" style="font-size: 13px; color: #34465c;">✍️ Additional Services</div>
                                                                            <ul class="list-unstyled ps-3 mb-0" style="margin-top: 4px;">
                                                                                @foreach ($limitedBidOther as $otherSvc)
                                                                                <li style="font-size: 12px;">{{ $otherSvc }}</li>
                                                                                @endforeach
                                                                            </ul>
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                    @endif

                                                                </div>{{-- modal-body --}}
                                                            </div>{{-- modal-content --}}
                                                        </div>{{-- modal-dialog --}}
                                                    </div>{{-- modal fade --}}
                                                    @endif {{-- limitedBidModal condition --}}

                                                    <!-- Counter Bids -->

                                                    @php
                                                        $counterBids = \App\Models\BuyerCounterBidding::with(
                                                            'meta',
                                                            'user',
                                                        )
                                                            ->where('buyer_agent_auction_bid_id', data_get($bid, 'id'))
                                                            ->orderBy('created_at', 'desc')
                                                            ->get();
                                                    @endphp

                                                    @php
                                                        $rawState = data_get($bid, 'accepted', '0');
                                                        $_isTerminalBuyer = in_array((string)$rawState, ['accepted', 'rejected'], true);
                                                        // Per-bid counter check: buyer_agent_auction_id = auction ID, parent_counter_id = bid ID.
                                                        $_perBidBuyerCounterExists = !$_isTerminalBuyer && \App\Models\BuyerCounterTerm::where('buyer_agent_auction_id', data_get($auction, 'id'))
                                                            ->where('parent_counter_id', data_get($bid, 'id'))
                                                            ->exists();
                                                        $state = $_perBidBuyerCounterExists
                                                            ? 'countered'
                                                            : (in_array($rawState, [null, 0, '0'], true) ? '0' : (string) $rawState);
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
                                                    @endphp

                                                    {{-- Counter Bidding Section - Only visible to listing owner and bidding agent --}}
                                                    @if ($showCounterBids && $counterBids->count() > 0)
                                                    <div class="counter-bids-section mt-4" id="counter-section-{{ $bid->id }}">
                                                        <!-- Counter Bids Accordion Header -->
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

                                                        <!-- Counter Bids Accordion Content -->
                                                        <div id="counterBids{{ data_get($bid, 'id') }}"
                                                            class="counter-bids-content"
                                                            style="display: none;">
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
                                                                                [null, 0, '0', 'no', 'pending'],
                                                                                true,
                                                                            )
                                                                                ? '0'
                                                                                : (string) $rawBidState;

                                                                            // BuyerCounterBidding has no status/accepted field — derive state from parent bid
                                                                            $counterState = $bidState;

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

                                                                           @php
                                                                            $allMeta = $counterBid->getAllMeta();

                                                                            // === COMPARISON HELPER: Check if counter value differs from original bid ===
                                                                            $isChanged = function($counterVal, $origKey) use ($bid) {
                                                                                $origVal = data_get($bid, 'get.' . $origKey, null);
                                                                                $normalizeVal = function($v) {
                                                                                    if (is_null($v) || $v === '') return '';
                                                                                    if (is_array($v) || is_object($v)) return json_encode($v);
                                                                                    $v = trim((string) $v);
                                                                                    return preg_replace('/[\s$,%]/', '', strtolower($v));
                                                                                };
                                                                                return $normalizeVal($counterVal) !== $normalizeVal($origVal);
                                                                            };

                                                                            // CSS for changed fields
                                                                            $changedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                                                            $changedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';

                                                                            // Services diff (counter vs original bid)
                                                                            $ctrSvcsRaw = is_string($allMeta['services'] ?? '') ? json_decode($allMeta['services'] ?? '', true) ?? [] : ($allMeta['services'] ?? []);
                                                                            $ctrSvcsRaw = array_filter((array)$ctrSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other');
                                                                            $origBidSvcsRaw = (array) data_get($bid, 'get.services', []);
                                                                            if (is_string(data_get($bid, 'get.services', []))) $origBidSvcsRaw = json_decode(data_get($bid, 'get.services', '[]'), true) ?: [];
                                                                            $origBidSvcsRaw = array_filter($origBidSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other');
                                                                            $normSvc = fn($s) => strtolower(trim((string)$s));
                                                                            $origBidSvcsNorm = array_map($normSvc, array_values($origBidSvcsRaw));
                                                                            $ctrSvcsNorm = array_map($normSvc, array_values($ctrSvcsRaw));
                                                                            $ctrSvcIsAdded = fn(string $s): bool => !in_array($normSvc($s), $origBidSvcsNorm, true);
                                                                            $ctrRemovedSvcs = array_values(array_filter($origBidSvcsRaw, fn($s) => !in_array($normSvc($s), $ctrSvcsNorm, true)));
                                                                            $ctrOtherRaw = is_string($allMeta['other_services'] ?? '') ? json_decode($allMeta['other_services'] ?? '', true) ?? [] : ($allMeta['other_services'] ?? []);
                                                                            $ctrOtherRaw = array_filter((array)$ctrOtherRaw, fn($s) => is_string($s) && trim($s) !== '');
                                                                            $origBidOtherRaw = (array)data_get($bid, 'get.other_services', []);
                                                                            if (is_string(data_get($bid, 'get.other_services', []))) $origBidOtherRaw = json_decode(data_get($bid, 'get.other_services', '[]'), true) ?: [];
                                                                            $origBidOtherNorm = array_map(fn($s) => strtolower(trim((string)$s)), array_filter((array)$origBidOtherRaw));
                                                                            $ctrOtherIsAdded = fn(string $s): bool => !in_array(strtolower(trim($s)), $origBidOtherNorm, true);
                                                                            // $ctrOtherRemoved superseded below by $ctrOtherRemovedFull (normalized approach)

                                                                            // Normalize function: handles Unicode curly quote variants (matching Tenant approach)
                                                                            $normalizeStr = function($s) {
                                                                                $search = ["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D"];
                                                                                $replace = ["'", "'", '"', '"'];
                                                                                return str_replace($search, $replace, $s);
                                                                            };

                                                                            // Build normalized selected-services lookup (keyed by normalized text → display value)
                                                                            $ctrSvcsForLookup = array_filter((array)$ctrSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other');
                                                                            $selectedNormalized = [];
                                                                            foreach ($ctrSvcsForLookup as $svc) {
                                                                                $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                                                $selectedNormalized[$normalizeStr($displaySvc)] = $displaySvc;
                                                                                $selectedNormalized[$normalizeStr($svc)] = $displaySvc;
                                                                            }

                                                                            // Redefine ctrSvcIsAdded using normalized approach (matches Tenant $tnSvcIsAdded)
                                                                            $origBidSvcsNormFull = array_values(array_map(
                                                                                fn($s) => $normalizeStr(function_exists('normalize_service_text') ? normalize_service_text((string)$s) : (string)$s),
                                                                                array_values($origBidSvcsRaw)
                                                                            ));
                                                                            $ctrSvcIsAdded = fn(string $svcDisplay): bool =>
                                                                                !in_array($normalizeStr($svcDisplay), $origBidSvcsNormFull, true);

                                                                            // Redefine ctrRemovedSvcs using normalized approach (matches Tenant $tnRemovedDisplay)
                                                                            $ctrSvcsNormFull = array_values(array_map(
                                                                                fn($s) => $normalizeStr(function_exists('normalize_service_text') ? normalize_service_text((string)$s) : (string)$s),
                                                                                array_values($ctrSvcsForLookup)
                                                                            ));
                                                                            $ctrRemovedSvcs = array_values(array_filter(
                                                                                array_values($origBidSvcsRaw),
                                                                                fn($s) => !in_array($normalizeStr(function_exists('normalize_service_text') ? normalize_service_text((string)$s) : (string)$s), $ctrSvcsNormFull, true)
                                                                            ));

                                                                            // Other services for rendering and removed diff
                                                                            $ctrOtherSvcsForRender = array_values(array_filter((array)$ctrOtherRaw, fn($s) => is_string($s) && !empty(trim($s))));
                                                                            $ctrOtherRemovedFull = array_values(array_filter(
                                                                                (array)$origBidOtherRaw,
                                                                                fn($s) => is_string($s) && trim($s) !== '' && !in_array(strtolower(trim($s)), array_map(fn($x) => strtolower(trim((string)$x)), $ctrOtherSvcsForRender), true)
                                                                            ));

                                                                            $hasAnyCounterServices = !empty($selectedNormalized) || !empty($ctrOtherSvcsForRender);

                                                                            // Buyer service categories based on property type (matching Tenant $servicesConfig pattern)
                                                                            $ctrBidPropType = $allMeta['property_type'] ?? $counterBid->property_type ?? data_get($bid, 'get.property_type') ?? 'Residential Property';

                                                                            $ctrBuyerResidentialCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                                                    "Share the Buyer's purchase criteria on Nextdoor in Neighborhood or Community Groups",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Real Estate or Housing Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Real Estate or Housing Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send email alerts with new listings from the MLS that match the Buyer's purchase criteria",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm availability, purchase terms, and showing instructions",
                                                                                    "Evaluate properties with the Buyer and provide insights on pricing, terms, potential, and overall fit",
                                                                                ],
                                                                                "🏡 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide factual observations on property layout and condition",
                                                                                ],
                                                                                "📝 Offer & Contract Coordination" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller (as permitted under the agency agreement)",
                                                                                    "Manage communications with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Professionals, or Lenders (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, appraisals, and lease audits (if applicable)",
                                                                                    "Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)",
                                                                                    "Provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources (no personal opinions or steering)",
                                                                                    "Offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerIncomeCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                                                    "Share the Buyer's purchase criteria on Nextdoor in Neighborhood or Community Groups",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Real Estate Investor or Multifamily Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Investment or Property Management Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send email alerts with new listings that match the Buyer's purchase criteria",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Sellers to confirm pricing, rental income, expenses, and showing instructions",
                                                                                    "Evaluate investment properties with the Buyer and provide insights on cash flow, cap rates, and value-add potential",
                                                                                ],
                                                                                "🏘 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide observations on tenant occupancy, building condition, and operating expenses",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Professionals, Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Review and provide due diligence documents such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                                                    "Coordinate with the Seller's Agent, Buyer's Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations, rental comps, and Cap Rate estimates (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about financing options, rent control, property taxes, and Landlord responsibilities",
                                                                                    "Provide factual information on rental demand, turnover rates, and sub market conditions using third-party sources",
                                                                                    "Offer general guidance on due diligence steps, lease audits, and estoppel reviews (non-legal advice)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerCommercialCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's criteria on Craigslist under \"Real Estate Wanted – Commercial\"",
                                                                                    "Promote the Buyer's criteria on Facebook in Commercial Real Estate or Investment Groups",
                                                                                    "Share the Buyer's criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's criteria on LinkedIn in Commercial or Investment Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred purchase areas",
                                                                                    "Launch hyperlocal or interest-based digital ad campaigns targeting desired commercial property types",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send listing alerts from real estate platforms that match the Buyer's purchase criteria",
                                                                                    "Send property alerts that match the Buyer's purchase criteria from the MLS or commercial listing platforms",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired listings that meet the Buyer's criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm availability, purchase terms, and showing instructions",
                                                                                    "Analyze building class, property zoning, income potential, and redevelopment opportunities",
                                                                                ],
                                                                                "🏢 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide insights on layout, access, visibility, tenant mix, and surrounding infrastructure",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase agreements or Letters of Intent (LOIs)",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposit structure, timelines, and contingencies with the Seller or Seller's Agent",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with due diligence negotiations, including repair requests or credits",
                                                                                    "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, Commercial Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, appraisals, environmental assessments, and estoppel certificate collection as needed",
                                                                                    "Review and request due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                                                    "Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with recent sales comps, lease comps, and an estimated value range (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about zoning regulations, permitted uses, and rental income potential",
                                                                                    "Provide factual data on traffic counts, commercial market trends, and area demographics using third-party sources (no personal opinions or steering)",
                                                                                    "Offer general guidance on lease types, contingency timelines, due diligence, and environmental risks (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerBusinessCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under \"Business for Sale\" or \"Real Estate Wanted – Commercial\"",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Business Opportunity or Franchise Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Business, Commercial, or Startup Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Business Search, Alerts & Matching" => [
                                                                                    "Send alerts for businesses that match the Buyer's acquisition criteria from MLS, BizBuySell, or other listing platforms",
                                                                                    "Send alerts for businesses that match the Buyer's acquisition criteria from available business listing sources",
                                                                                    "Search for off-market, pre-market, distressed, or recently closed businesses that meet the Buyer's criteria",
                                                                                    "Communicate with the Seller's Broker or Seller to confirm pricing, lease terms, licensing status, and showing availability",
                                                                                    "Analyze financials, lease assignments, business licensing requirements, and overall market positioning",
                                                                                ],
                                                                                "🏢 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property or business showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties or business locations on behalf of the Buyer upon request",
                                                                                    "Provide insights on foot traffic, customer base, operational setup, competitive advantages, and location dynamics",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using appropriate business purchase or asset sale forms",
                                                                                    "Provide the Buyer with required disclosures, financial summaries, and documentation made available by the Seller",
                                                                                    "Negotiate terms such as purchase price, deposit structure, inventory inclusions, non-compete agreements, and contingencies",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Manage communication with the Seller's Broker or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with due diligence coordination, Buyer-requested repairs, and adjustment negotiations",
                                                                                    "Monitor contingency periods, financing milestones, and deal approval timelines",
                                                                                    "Provide referrals to Business Attorneys, CPAs, Escrow Officers, or Lenders (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, licensing verifications, lease assignments, and inventory counts",
                                                                                    "Coordinate with Lenders, Attorneys, Escrow Officers, Title Companies, CPAs, and other involved parties to prepare for Closing",
                                                                                    "Review the Settlement Statement or Closing Worksheet for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and business transition materials",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Review based on similar business sales, financial performance, and industry benchmarks (for informational purposes only — not a formal appraisal or valuation)",
                                                                                    "Answer general questions about licensing, zoning, SBA financing, registration steps, and transition timing (non-legal guidance)",
                                                                                    "Offer general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerVacantLandCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's criteria on Craigslist under \"Real Estate Wanted – Land\"",
                                                                                    "Share the Buyer's criteria on Nextdoor in Neighborhood or Rural Groups",
                                                                                    "Promote the Buyer's criteria on Facebook in Land Buyers, Developers, or Homesteader Groups",
                                                                                    "Share the Buyer's criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's criteria on LinkedIn in Land Acquisition or Investment Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send property alerts for land listings that match the Buyer's goals from MLS and land-specific platforms",
                                                                                    "Send property alerts for land listings that match the Buyer's goals from relevant real estate and land-specific platforms",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm zoning, access, utilities, and pricing",
                                                                                    "Assess development feasibility, land use restrictions, or agricultural potential (non-legal advice)",
                                                                                ],
                                                                                "🏡 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend land visits with the Buyer",
                                                                                    "Coordinate or conduct virtual walkthroughs using maps, aerials, and site photos",
                                                                                    "Preview parcels on behalf of the Buyer upon request",
                                                                                    "Provide observations on topography, road frontage, and surrounding land uses",
                                                                                ],
                                                                                "📜 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with required state or local disclosure forms",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies (as permitted under the agency agreement)",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed documents to all parties",
                                                                                    "Assist with due diligence coordination, including survey review, soil testing, zoning checks, and permit verification (non-legal guidance only)",
                                                                                    "Monitor contract milestones, contingency deadlines, and financing timelines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, Surveyors, or Land Use Consultants (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate surveys, appraisals, inspections, and environmental assessments",
                                                                                    "Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) based on recent land sales, acreage comps, and price-per-acre benchmarks (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about zoning, utilities, development potential, and environmental constraints (non-legal guidance only)",
                                                                                    "Provide factual data on flood zones, wetlands, and land use maps using third-party sources (no legal or engineering advice)",
                                                                                    "Offer general guidance on feasibility timelines, inspection steps, and rural financing considerations (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            if ($ctrBidPropType === 'Income') {
                                                                                $ctrBuyerCategories = $ctrBuyerIncomeCategories;
                                                                            } elseif ($ctrBidPropType === 'Commercial') {
                                                                                $ctrBuyerCategories = $ctrBuyerCommercialCategories;
                                                                            } elseif ($ctrBidPropType === 'Business') {
                                                                                $ctrBuyerCategories = $ctrBuyerBusinessCategories;
                                                                            } elseif ($ctrBidPropType === 'Vacant Land') {
                                                                                $ctrBuyerCategories = $ctrBuyerVacantLandCategories;
                                                                            } else {
                                                                                $ctrBuyerCategories = $ctrBuyerResidentialCategories;
                                                                            }
                                                                            @endphp
@if (
    !empty($allMeta['commission_structure']) ||
    !empty($allMeta['purchase_fee_type']) ||
    !empty($allMeta['interested_lease_option']) ||
    !empty($allMeta['interested_lease_option_agreement']) ||
    !empty($allMeta['protection_period']) ||
    !empty($allMeta['early_termination_fee_option']) ||
    !empty($allMeta['retainer_fee_option']) ||
    !empty($allMeta['agency_agreement_timeframe']) ||
    !empty($allMeta['brokerage_relationship']) ||
    !empty($allMeta['additional_details_broker']))
    <div class="mb-4">
        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
        </h6>

        {{-- A) Buyer's Broker Compensation --}}
        @if (!empty($allMeta['commission_structure']) || !empty($allMeta['purchase_fee_type']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">A) Buyer's Broker Compensation</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @if (!empty($allMeta['commission_structure']))
                <li class="mb-1" style="{{ $isChanged($allMeta['commission_structure'], 'commission_structure') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer's Broker Commission Structure:</span>
                    {{ $allMeta['commission_structure'] }}
                    @if ($isChanged($allMeta['commission_structure'], 'commission_structure')) {!! $changedBadge !!} @endif
                </li>
                @endif
                @if (!empty($allMeta['purchase_fee_type']))
                @php
                    $ctrPurchaseVal = '—';
                    $cpType = $allMeta['purchase_fee_type'];
                    $safeNumber = function($v, $decimals = 2) {
                        if ($v === null || $v === '') return null;
                        $clean = str_replace([',', '$', ' '], '', (string)$v);
                        if ($clean === '' || !is_numeric($clean)) return null;
                        return number_format((float)$clean, $decimals);
                    };
                    if ($cpType === 'Flat Fee' && !empty($allMeta['purchase_fee_flat'])) {
                        $formatted = $safeNumber($allMeta['purchase_fee_flat']);
                        $ctrPurchaseVal = $formatted ? ('$' . $formatted) : '—';
                    } elseif ($cpType === 'Percentage of the Total Purchase Price' && !empty($allMeta['purchase_fee_percentage'])) {
                        $formatted = $safeNumber($allMeta['purchase_fee_percentage']);
                        $ctrPurchaseVal = $formatted ? (rtrim(rtrim($formatted, '0'), '.') . '% of Total Purchase Price') : '—';
                    } elseif ($cpType === 'Percentage of the Total Purchase Price + Flat Fee') {
                        $pctFormatted = $safeNumber($allMeta['purchase_fee_percentage_combo'] ?? null);
                        $pctPart = $pctFormatted ? (rtrim(rtrim($pctFormatted, '0'), '.') . '% of Total Purchase Price') : null;
                        $flatFormatted = $safeNumber($allMeta['purchase_fee_flat_combo'] ?? null);
                        $flatPart = $flatFormatted ? ('$' . $flatFormatted . ' flat') : null;
                        $ctrPurchaseVal = $pctPart && $flatPart ? "$pctPart + $flatPart" : ($pctPart ?? $flatPart ?? '—');
                    } elseif ($cpType === 'other' && !empty($allMeta['purchase_fee_other'])) {
                        $ctrPurchaseVal = $allMeta['purchase_fee_other'];
                    }
                @endphp
                <li class="mb-1" style="{{ $isChanged($allMeta['purchase_fee_type'] ?? '', 'purchase_fee_type') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer's Broker Purchase Fee:</span>
                    {{ $ctrPurchaseVal }}
                    @if ($isChanged($allMeta['purchase_fee_type'] ?? '', 'purchase_fee_type')) {!! $changedBadge !!} @endif
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- B) Lease Fee --}}
        @if (!empty($allMeta['interested_lease_option']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">B) Lease Fee</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                <li class="mb-1" style="{{ $isChanged($allMeta['interested_lease_option'], 'interested_lease_option') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Interested in a Lease Agreement:</span>
                    {{ $allMeta['interested_lease_option'] }}
                    @if ($isChanged($allMeta['interested_lease_option'], 'interested_lease_option')) {!! $changedBadge !!} @endif
                </li>
                @if ($allMeta['interested_lease_option'] === 'Yes' && !empty($allMeta['lease_fee_type']))
                @php
                    $ctrLeaseVal = '—';
                    $lfType = $allMeta['lease_fee_type'];
                    $safeNumberLease = function($v, $decimals = 2) {
                        if ($v === null || $v === '') return null;
                        $clean = str_replace([',', '$', ' '], '', (string)$v);
                        if ($clean === '' || !is_numeric($clean)) return null;
                        return number_format((float)$clean, $decimals);
                    };
                    if ($lfType === 'flat' && !empty($allMeta['lease_fee_flat'])) {
                        $formatted = $safeNumberLease($allMeta['lease_fee_flat']);
                        $ctrLeaseVal = $formatted ? ('$' . $formatted) : '—';
                    } elseif ($lfType === 'Percentage of the Gross Lease Value' && !empty($allMeta['lease_fee_percentage'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_percentage'] . '% of Gross Lease Value';
                    } elseif ($lfType === 'Percentage of Monthly Rent' && !empty($allMeta['lease_fee_percentage_monthly_rent'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_percentage_monthly_rent'] . '% of Monthly Rent';
                        if (!empty($allMeta['lease_fee_percentage_monthly_number'])) {
                            $ctrLeaseVal .= ' x ' . $allMeta['lease_fee_percentage_monthly_number'] . ' Months';
                        }
                    } elseif ($lfType === 'Flat Fee + Percentage of the Gross Lease Value') {
                        $flatFormatted = $safeNumberLease($allMeta['lease_fee_flat_combo'] ?? null);
                        $flatPart = $flatFormatted ? ('$' . $flatFormatted) : null;
                        $pctPart = !empty($allMeta['lease_fee_percentage_combo']) ? ($allMeta['lease_fee_percentage_combo'] . '% of Gross Lease Value') : null;
                        $ctrLeaseVal = $flatPart && $pctPart ? "$flatPart + $pctPart" : ($flatPart ?? $pctPart ?? '—');
                    } elseif ($lfType === 'Percentage of the Net Aggregate Rent' && !empty($allMeta['lease_fee_percentage_net'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_percentage_net'] . '% of Net Aggregate Rent';
                    } elseif (strtolower($lfType) === 'other' && !empty($allMeta['lease_fee_other'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_other'];
                    }
                @endphp
                <li class="mb-1" style="{{ $isChanged($allMeta['lease_fee_type'] ?? '', 'lease_fee_type') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer's Broker Lease Fee:</span>
                    {{ $ctrLeaseVal }}
                    @if ($isChanged($allMeta['lease_fee_type'] ?? '', 'lease_fee_type')) {!! $changedBadge !!} @endif
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- C) Lease-Option Details --}}
        @if (!empty($allMeta['interested_lease_option_agreement']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">C) Lease-Option Details</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                <li class="mb-1" style="{{ $isChanged($allMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Interested in a Lease-Option Agreement:</span>
                    {{ $allMeta['interested_lease_option_agreement'] }}
                    @if ($isChanged($allMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement')) {!! $changedBadge !!} @endif
                </li>
                @if ($allMeta['interested_lease_option_agreement'] === 'Yes')
                    @if (!empty($allMeta['lease_value']))
                    <li class="mb-1" style="{{ $isChanged($allMeta['lease_value'], 'lease_value') ? $changedStyle : '' }}">
                        <span class="fw-semibold">Compensation for Lease-Option Agreement:</span>
                        @if (($allMeta['lease_type'] ?? '') === 'percent') {{ $allMeta['lease_value'] }}%
                        @else {{ \App\Support\Format::money($allMeta['lease_value']) }}
                        @endif
                        @if ($isChanged($allMeta['lease_value'], 'lease_value')) {!! $changedBadge !!} @endif
                    </li>
                    @endif
                    @if (!empty($allMeta['purchase_value']))
                    <li class="mb-1" style="{{ $isChanged($allMeta['purchase_value'], 'purchase_value') ? $changedStyle : '' }}">
                        <span class="fw-semibold">Compensation if Purchase Option is Exercised:</span>
                        @if (($allMeta['purchase_type'] ?? '') === 'percent') {{ $allMeta['purchase_value'] }}%
                        @else {{ \App\Support\Format::money($allMeta['purchase_value']) }}
                        @endif
                        @if ($isChanged($allMeta['purchase_value'], 'purchase_value')) {!! $changedBadge !!} @endif
                    </li>
                    @endif
                @endif
            </ul>
        </div>
        @endif

        {{-- D) Legal Terms --}}
        @if (!empty($allMeta['protection_period']) || !empty($allMeta['early_termination_fee_option']) || !empty($allMeta['retainer_fee_option']) || !empty($allMeta['agency_agreement_timeframe']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">D) Legal Terms</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @if (!empty($allMeta['protection_period']))
                <li class="mb-1" style="{{ $isChanged($allMeta['protection_period'], 'protection_period') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Protection Period Timeframe:</span>
                    {{ $allMeta['protection_period'] }} days
                    @if ($isChanged($allMeta['protection_period'], 'protection_period')) {!! $changedBadge !!} @endif
                </li>
                @endif
                @if (!empty($allMeta['early_termination_fee_option']))
                @php $buyerTermOptChg = $isChanged($allMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                <li class="mb-1" style="{{ $buyerTermOptChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Early Termination Fee:</span>
                    {{ $allMeta['early_termination_fee_option'] }}
                    @if ($buyerTermOptChg) {!! $changedBadge !!} @endif
                </li>
                @if (strtolower($allMeta['early_termination_fee_option']) === 'yes' && !empty($allMeta['early_termination_fee_amount']))
                @php $buyerTermAmtChg = $isChanged($allMeta['early_termination_fee_amount'], 'early_termination_fee_amount'); @endphp
                <li class="mb-1" style="{{ $buyerTermAmtChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Termination Fee Amount:</span>
                    {{ \App\Support\Format::money($allMeta['early_termination_fee_amount']) }}
                    @if ($buyerTermAmtChg) {!! $changedBadge !!} @endif
                </li>
                @endif
                @endif
                @if (!empty($allMeta['retainer_fee_option']))
                @php $buyerRetOptChg = $isChanged($allMeta['retainer_fee_option'], 'retainer_fee_option'); @endphp
                <li class="mb-1" style="{{ $buyerRetOptChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Retainer Fee:</span>
                    {{ $allMeta['retainer_fee_option'] }}
                    @if ($buyerRetOptChg) {!! $changedBadge !!} @endif
                </li>
                @if (strtolower($allMeta['retainer_fee_option']) === 'yes' && !empty($allMeta['retainer_fee_amount']))
                @php $buyerRetAmtChg = $isChanged($allMeta['retainer_fee_amount'], 'retainer_fee_amount'); @endphp
                <li class="mb-1" style="{{ $buyerRetAmtChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Retainer Fee Amount:</span>
                    {{ \App\Support\Format::money($allMeta['retainer_fee_amount']) }}
                    @if ($buyerRetAmtChg) {!! $changedBadge !!} @endif
                </li>
                @endif
                @if (strtolower($allMeta['retainer_fee_option'] ?? '') === 'yes' && !empty($allMeta['retainer_fee_application']))
                @php $buyerRetAppChg = $isChanged($allMeta['retainer_fee_application'], 'retainer_fee_application'); @endphp
                <li class="mb-1" style="{{ $buyerRetAppChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Retainer Fee Application:</span>
                    {{ $allMeta['retainer_fee_application'] === 'applied' ? 'Applied toward final compensation' : ($allMeta['retainer_fee_application'] === 'additional' ? 'Charged in addition to final compensation' : $allMeta['retainer_fee_application']) }}
                    @if ($buyerRetAppChg) {!! $changedBadge !!} @endif
                </li>
                @endif
                @endif
                @if (!empty($allMeta['agency_agreement_timeframe']))
                <li class="mb-1" style="{{ $isChanged($allMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer Agency Agreement Timeframe:</span>
                    {{ ($allMeta['agency_agreement_timeframe'] === 'custom' && !empty($allMeta['agency_agreement_custom'])) ? $allMeta['agency_agreement_custom'] : $allMeta['agency_agreement_timeframe'] }}
                    @if ($isChanged($allMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe')) {!! $changedBadge !!} @endif
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- E) Brokerage Relationship --}}
        @if (!empty($allMeta['brokerage_relationship']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">E) Brokerage Relationship</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                <li class="mb-1" style="{{ $isChanged($allMeta['brokerage_relationship'], 'brokerage_relationship') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Acceptable Brokerage Relationship:</span>
                    {{ $allMeta['brokerage_relationship'] }}
                    @if ($isChanged($allMeta['brokerage_relationship'], 'brokerage_relationship')) {!! $changedBadge !!} @endif
                </li>
            </ul>
        </div>
        @endif

        {{-- F) Additional Terms --}}
        @if (!empty($allMeta['additional_details_broker']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">F) Additional Terms</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @php $addTermsChanged = $isChanged($allMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                <li class="mb-1" style="{{ $addTermsChanged ? $changedStyle : '' }}">
                    <span class="fw-semibold">Additional Terms:</span> {{ $allMeta['additional_details_broker'] }}
                    @if ($addTermsChanged) {!! $changedBadge !!} @endif
                </li>
            </ul>
        </div>
        @endif
    </div>
@endif

                                                                            {{-- Additional Details --}}
                                                                            @if (!empty($allMeta['additional_details']))
                                                                            <div class="mb-3">
                                                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;"><i class="fa fa-info-circle me-1"></i>Additional Details</div>
                                                                                @php $addDetailsChanged = $isChanged($allMeta['additional_details'], 'additional_details'); @endphp
                                                                                <div class="ps-3" style="font-size: 12px; {{ $addDetailsChanged ? $changedStyle : '' }}">{{ $allMeta['additional_details'] }}{!! $addDetailsChanged ? $changedBadge : '' !!}</div>
                                                                            </div>
                                                                            @endif

                                                                            <!-- Services Offered -->
                                                                            @if ($hasAnyCounterServices)
                                                                            <div class="mb-5">
                                                                                <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                                    <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                                                                </h6>

                                                                                @foreach ($ctrBuyerCategories as $catName => $catServices)
                                                                                    @php
                                                                                    $selectedInCat = array_filter($catServices, fn($s) => isset($selectedNormalized[$normalizeStr($s)]));
                                                                                    @endphp
                                                                                    @if (count($selectedInCat) > 0)
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                                                                        <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                                                            @foreach ($catServices as $service)
                                                                                                @php
                                                                                                $serviceNorm = $normalizeStr($service);
                                                                                                $serviceDisplay = $selectedNormalized[$serviceNorm] ?? null;
                                                                                                @endphp
                                                                                                @if ($serviceDisplay !== null)
                                                                                                    @if ($ctrSvcIsAdded($serviceDisplay))
                                                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                                                        <i class="fa fa-plus-circle me-1" style="color: #856404;"></i>{{ $serviceDisplay }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                                                    </li>
                                                                                                    @else
                                                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $serviceDisplay }}</li>
                                                                                                    @endif
                                                                                                @endif
                                                                                            @endforeach
                                                                                        </ul>
                                                                                    </div>
                                                                                    @endif
                                                                                @endforeach

                                                                                @if (!empty($ctrOtherSvcsForRender))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                                                        @foreach ($ctrOtherSvcsForRender as $otherService)
                                                                                            @if ($ctrOtherIsAdded($otherService))
                                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                                                <i class="fa fa-plus-circle me-1" style="color: #856404;"></i>{{ $otherService }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                                            </li>
                                                                                            @else
                                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherService }}</li>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                                @endif

                                                                                @if (!empty($ctrRemovedSvcs) || !empty($ctrOtherRemovedFull))
                                                                                <div class="mb-3 mt-2 p-3" style="background-color: #fff5f5; border-radius: 6px; border: 1px solid #f5c6cb;">
                                                                                    <div class="fw-bold mb-1" style="color: #dc3545; font-size: 0.95rem;">
                                                                                        <i class="fa fa-minus-circle me-1"></i>Removed Services
                                                                                    </div>
                                                                                    <ul class="services mb-0" style="margin-top: 0.5rem; padding-left: 1.2rem; list-style: none;">
                                                                                        @foreach ($ctrRemovedSvcs as $rSvc)
                                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                                                            <i class="fa fa-times-circle me-1"></i>{{ $rSvc }}
                                                                                        </li>
                                                                                        @endforeach
                                                                                        @foreach ($ctrOtherRemovedFull as $rSvc)
                                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                                                            <i class="fa fa-times-circle me-1"></i>{{ $rSvc }}
                                                                                        </li>
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                                @endif
                                                                            </div>
                                                                            @endif


                                                                            <!-- Counter actions (only when both pending & viewer is the other party) -->
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

                                                    @if ($showCounterActions)
                                                        <div class="mt-3 pt-3 border-top">
                                                            <div class="d-flex gap-2 flex-wrap justify-content-center w-100">
                                                                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                    <i class="fa fa-eye me-1"></i> View Counter Terms
                                                                </a>
                                                                @if ($isOwner)
                                                                <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                    <i class="fa fa-edit me-1"></i> Edit Counter Terms
                                                                </a>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif

                                                                            <!-- Counter footer status -->
                                                                    <div class="mt-3 pt-3 border-top">
                                                                                @if ($counterState === 'accepted')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        <div
                                                                                            class="alert alert-success mb-0 py-1 small">
                                                                                            ✅ This counter bid has been
                                                                                            accepted.
                                                                                        </div>
                                                                                    @else
                                                                                        <div
                                                                                            class="alert alert-success mb-0 py-1 small">
                                                                                            ✅
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            accepted the counter bid.
                                                                                        </div>
                                                                                    @endif
                                                                                @elseif ($counterState === 'rejected')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 small">
                                                                                            ❌ This counter bid has been
                                                                                            rejected.
                                                                                        </div>
                                                                                    @else
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 alert-font">
                                                                                            ❌
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            rejected the counter bid.
                                                                                        </div>
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
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif


                                    </div>
                                    {{-- End of bid-collapse-content --}}
                                    </div>
                                    {{-- End of card mb-3 --}}
                                @endforeach

                        </div>
                    </div>
                </div>
            </div>{{-- end rightCol sidebar --}}
        </div>{{-- end main row --}}
        <button class="btn w-100 mt-0">
            <span class="bid m-0"><i class="fa fa-user"></i> </span>
        </button>
        <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('buyer.view-auction', @$auction->id), 200) }}
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
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <!-- Message  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
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

{{-- 🧠 Timer Script --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js"
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>

@if ($expiration && !$isExpired)
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
            $('.time').html("<div class='w-100 text-center text-danger fw-bold'>Bidding Ended</div>");
            $('.bid-btn').fadeOut(300, function() {
                $(this).after(
                    "<div class='alert alert-warning text-center mt-2 mb-0 p-2'><strong>Bidding Ended</div>"
                );
            });
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

