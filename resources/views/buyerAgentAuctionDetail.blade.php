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

                            @if (@$auction->get->auction_time != null)
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
                                $financingArray = is_array($financingForChecks) ? $financingForChecks : (is_string($financingForChecks) ? json_decode($financingForChecks, true) ?? [$financingForChecks] : []);
                                
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
        @if (@$auction->user_id != $auth_id)
        <a href="{{ route('auction-chat', ['tenant-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>
        @endif


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



                <div class="card higestBider">
                    <div class="card-body card-body-padding">
                        @if ($lowest_bidder)
                            <p><b>{{ $lowest_bidder->user->first_name ?? '' }}</b> is the last bidder.</p>
                            {{-- <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p> --}}
                        @else
                            <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">

                                @foreach (@$auction->bids as $bid)
                                    <!-- Item loop -->
                                    <div class="accordion" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#item{{ data_get($bid, 'id') }}" aria-expanded="true"
                                        aria-controls="item{{ data_get($bid, 'id') }}">
                                        <div class="d-flex small accordion mr-0 text-center">
                                            <div class="col-1">
                                                <span class="badge">{{ $loop->iteration }}</span>
                                            </div>
                                            <div class="col-4">
                                                {{ data_get($bid, 'user.first_name', '') }}
                                            </div>
                                            <div class="col-4 text-right">
                                                {{ data_get($bid, 'get.agent_fee', data_get($bid, 'agent_fee', '')) }}
                                            </div>
                                            <div class="col-2 d-flex">
                                                Terms↓
                                            </div>
                                        </div>
                                    </div>

                                    <div id="item{{ data_get($bid, 'id') }}" class="accordion-collapse collapse"
                                        aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                        <div class="accordion-body-padding" style="padding: 20px;">
                                            <div id="bidding_history_data">
                                                <div>
                                                    <!-- Agent Information -->
                                                    {{-- <p class="d-flex justify-content-between  align-items-center small"
                                                        style="color: #333;">
                                                        <span>
                                                            Agent First Name:
                                                        </span>
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.first_name', '') }}
                                                        </span>
                                                    </p> --}}
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Licensed Since:
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.year_licensed', '') }}
                                                        </span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Marketing Strategy:
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.marketing_plan', '') }}
                                                        </span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        What Sets This Agent Apart:
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.what_sets_you_apart', '') }}
                                                        </span>
                                                    </p>

                                                    {{-- <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Why Should You Be Hired as Their Agent?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.why_hire_you', '') }}
                                                        </span>
                                                    </p> --}}

                                                    <!-- Services Offered (Categorized Display using ServicesFormatter) -->
                                                    @php 
                                                        $bidServicesList = (array) data_get($bid,'get.services',[]);
                                                        $bidOrderedServices = !empty($bidServicesList) 
                                                            ? \App\Support\ServicesFormatter::orderSelectedServices($bidServicesList, $flowKey)
                                                            : [];
                                                    @endphp
                                                    @if (!empty($bidServicesList))
                                                        <div class="mt-3">
                                                            <label style="font-size: large; font-weight: 600; color: #049399;">
                                                                Services Offered:</label>
                                                            @if (!empty($bidOrderedServices))
                                                                @foreach ($bidOrderedServices as $catName => $catServices)
                                                                    @if (!empty($catServices))
                                                                        <div class="mt-2">
                                                                            <strong>{{ $catName }}</strong>
                                                                            <ul class="services services-offered">
                                                                                @foreach ($catServices as $service)
                                                                                    <li style="font-size: 16px;">{{ $service }}</li>
                                                                                @endforeach
                                                                            </ul>
                                                                        </div>
                                                                    @endif
                                                                @endforeach
                                                            @else
                                                                <ul class="services services-offered">
                                                                    @foreach ($bidServicesList as $service)
                                                                        @if ($service != 'Other')
                                                                            <li style="font-size: 16px;">{{ $service }}</li>
                                                                        @endif
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    {{-- @php $otherServicesList = (array) data_get($bid,'get.other_services',[]); @endphp
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
                                                    @endif --}}

                                                    <!-- PRIVATE DATA SECTION - Only visible to listing owner -->
                                                    @if (data_get($auction, 'user_id') == $auth_id)
                                                        <!-- Button to trigger modal -->
                                                        <div class="text-center">
                                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                                                data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                                                style="margin-top: -25px; padding: 12px 30px; width: 100%; background: #049399; border: none; border-radius: 8px; font-weight: 600; color: white;">
                                                                <i class="fa fa-lock me-2"></i> View Private Compensation &
                                                                Agreement Terms
                                                            </button>
                                                        </div>

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

                                                                        <!-- 1. Agent Overview & Qualifications -->
                                                                        <div class="mb-5">
                                                                            <h6 class="mb-3"
                                                                                style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                                <i class="fa fa-user-tie me-2"></i>Agent
                                                                                Overview & Qualifications
                                                                            </h6>

                                                                            <!-- About Agent -->
                                                                            @if (data_get($bid, 'get.bio'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">About Agent
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ data_get($bid, 'get.bio') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Why Hire You -->
                                                                            @if (data_get($bid, 'get.why_hire_you'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Why Should
                                                                                        You Be Hired</div>
                                                                                    <div class="text-muted">
                                                                                        {{ data_get($bid, 'get.why_hire_you') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Reviews Links -->
                                                                            @if (data_get($bid, 'get.reviews_links'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Reviews
                                                                                        Links</div>
                                                                                    <div>
                                                                                        @foreach (data_get($bid, 'get.reviews_links') as $reviewLink)
                                                                                            @if (!empty($reviewLink->url))
                                                                                                <div class="mb-1">
                                                                                                    <a href="https://{{ $reviewLink->url }}"
                                                                                                        target="_blank"
                                                                                                        class="text-primary text-decoration-none">
                                                                                                        <i
                                                                                                            class="fa fa-external-link-alt me-1"></i>
                                                                                                        {{ !empty($reviewLink->text) ? $reviewLink->text : $reviewLink->url }}
                                                                                                    </a>
                                                                                                </div>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                            <!-- Website Link -->
                                                                            @if (data_get($bid, 'get.website_link'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Website
                                                                                        Link</div>
                                                                                    <div>
                                                                                        <a href="{{ data_get($bid, 'get.website_link') }}"
                                                                                            target="_blank"
                                                                                            class="text-primary text-decoration-none">
                                                                                            <i
                                                                                                class="fa fa-globe me-1"></i>
                                                                                            Visit Website
                                                                                        </a>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Social Media Platforms -->
                                                                            @if (data_get($bid, 'get.social_media'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Social
                                                                                        Media</div>
                                                                                    <div>
                                                                                        @foreach (data_get($bid, 'get.social_media') as $social)
                                                                                            @php
                                                                                                // Convert object to array
                                                                                                $socialArray = (array) $social;
                                                                                            @endphp
                                                                                            @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                                                                                                <div class="mb-1">
                                                                                                    @php
                                                                                                        $socialUrl =
                                                                                                            $socialArray[
                                                                                                                'url'
                                                                                                            ];
                                                                                                        // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                                                        if (
                                                                                                            !empty(
                                                                                                                $socialUrl
                                                                                                            ) &&
                                                                                                            !str_starts_with(
                                                                                                                $socialUrl,
                                                                                                                'http://',
                                                                                                            ) &&
                                                                                                            !str_starts_with(
                                                                                                                $socialUrl,
                                                                                                                'https://',
                                                                                                            )
                                                                                                        ) {
                                                                                                            $socialUrl =
                                                                                                                'https://' .
                                                                                                                $socialUrl;
                                                                                                        }
                                                                                                    @endphp
                                                                                                    <a href="{{ $socialUrl }}"
                                                                                                        target="_blank"
                                                                                                        class="text-primary text-decoration-none">
                                                                                                        <i
                                                                                                            class="fab fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
                                                                                                        @if (!empty($socialArray['text']))
                                                                                                            {{ $socialArray['text'] }}
                                                                                                        @else
                                                                                                            {{ $socialArray['platform'] }}
                                                                                                        @endif
                                                                                                    </a>
                                                                                                </div>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                        </div>

                                                                       <!-- Bid Broker Compensation & Agency Agreement Terms (Label:Value Format) -->
@if (data_get($bid, 'get.commission_structure') ||
     data_get($bid, 'get.purchase_fee_type') ||
     data_get($bid, 'get.interested_lease_option') ||
     data_get($bid, 'get.lease_fee_type') ||
     data_get($bid, 'get.interested_lease_option_agreement') ||
     data_get($bid, 'get.protection_period') ||
     data_get($bid, 'get.early_termination_fee_option') ||
     data_get($bid, 'get.retainer_fee_option') ||
     data_get($bid, 'get.agency_agreement_timeframe') ||
     data_get($bid, 'get.brokerage_relationship') ||
     data_get($bid, 'get.additional_details_broker'))
    <div class="mb-5 broker-compensation-section">
        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
        </h6>

        <!-- 1. Buyer's Broker Compensation -->
        <h6 class="mt-3 mb-2" style="font-weight: 600;">Buyer's Broker Compensation:</h6>
        
        @if (data_get($bid, 'get.commission_structure'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Buyer's Broker Commission Structure:
            <span class="removeBold">{{ data_get($bid, 'get.commission_structure') }}</span>
        </div>
        @endif

        @if (data_get($bid, 'get.purchase_fee_type'))
        @php
            $bidPurchaseFeeType = data_get($bid, 'get.purchase_fee_type') ?? '';
            $bidPurchaseFeeCombined = '—';
            
            if ($bidPurchaseFeeType === 'Flat Fee') {
                $bidPurchaseFeeCombined = $fmtMoney(data_get($bid, 'get.purchase_fee_flat')) ?? '—';
            } elseif ($bidPurchaseFeeType === 'Percentage of the Total Purchase Price') {
                $pct = data_get($bid, 'get.purchase_fee_percentage');
                $bidPurchaseFeeCombined = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '—';
            } elseif ($bidPurchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                $bidPurchaseFeeCombined = $joinParts([
                    $fmtMoney(data_get($bid, 'get.purchase_fee_flat_combo')),
                    data_get($bid, 'get.purchase_fee_percentage_combo') ? ($fmtPercent(data_get($bid, 'get.purchase_fee_percentage_combo')) . ' of Total Purchase Price') : null,
                ]) ?? '—';
            } elseif ($bidPurchaseFeeType === 'other') {
                $bidPurchaseFeeCombined = data_get($bid, 'get.purchase_fee_other') ?? '—';
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Buyer's Broker Purchase Fee:
            <span class="removeBold">{{ $bidPurchaseFeeCombined }}</span>
        </div>
        @endif

        <div class="my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- 2. Buyer's Broker Lease Fee -->
        <h6 class="mt-3 mb-2" style="font-weight: 600;">Buyer's Broker Lease Fee:</h6>

        @if (data_get($bid, 'get.interested_lease_option'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in a Lease Agreement:
            <span class="removeBold">{{ data_get($bid, 'get.interested_lease_option') }}</span>
        </div>
        @endif

        @if (data_get($bid, 'get.interested_lease_option') === 'Yes' && data_get($bid, 'get.lease_fee_type'))
        @php
            $bidLeaseFeeType = data_get($bid, 'get.lease_fee_type') ?? '';
            $bidLeaseFeeCombined = '—';
            
            if ($bidLeaseFeeType === 'flat' && data_get($bid, 'get.lease_fee_flat')) {
                $bidLeaseFeeCombined = $fmtMoney(data_get($bid, 'get.lease_fee_flat'));
            } elseif ($bidLeaseFeeType === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.lease_fee_percentage')) {
                $bidLeaseFeeCombined = $fmtPercent(data_get($bid, 'get.lease_fee_percentage')) . ' of Gross Lease Value';
            } elseif ($bidLeaseFeeType === 'Percentage of Monthly Rent' && data_get($bid, 'get.lease_fee_percentage_monthly_rent')) {
                $display = $fmtPercent(data_get($bid, 'get.lease_fee_percentage_monthly_rent')) . ' of Monthly Rent';
                if (data_get($bid, 'get.lease_fee_percentage_monthly_number')) {
                    $display .= ' x ' . data_get($bid, 'get.lease_fee_percentage_monthly_number') . ' Months';
                }
                $bidLeaseFeeCombined = $display;
            } elseif ($bidLeaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                $bidLeaseFeeCombined = $joinParts([
                    $fmtMoney(data_get($bid, 'get.lease_fee_flat_combo')),
                    data_get($bid, 'get.lease_fee_percentage_combo') ? ($fmtPercent(data_get($bid, 'get.lease_fee_percentage_combo')) . ' of Gross Lease Value') : null,
                ]) ?? '—';
            } elseif ($bidLeaseFeeType === 'Percentage of the Net Aggregate Rent' && data_get($bid, 'get.lease_fee_percentage_net')) {
                $bidLeaseFeeCombined = $fmtPercent(data_get($bid, 'get.lease_fee_percentage_net')) . ' of Net Aggregate Rent';
            } elseif (strtolower($bidLeaseFeeType) === 'other' && data_get($bid, 'get.lease_fee_other')) {
                $bidLeaseFeeCombined = data_get($bid, 'get.lease_fee_other');
            } elseif ($bidLeaseFeeType) {
                $bidLeaseFeeCombined = $bidLeaseFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Buyer's Broker Lease Fee:
            <span class="removeBold">{{ $bidLeaseFeeCombined }}</span>
        </div>
        @endif

        <div class="my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- 3. Lease-Option Details -->
        <h6 class="mt-3 mb-2" style="font-weight: 600;">Lease-Option Details:</h6>

        @if (data_get($bid, 'get.interested_lease_option_agreement'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in a Lease-Option Agreement:
            <span class="removeBold">{{ data_get($bid, 'get.interested_lease_option_agreement') }}</span>
        </div>
        @endif

        @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
            @if (data_get($bid, 'get.lease_value'))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Compensation for Creating the Lease-Option Agreement:
                <span class="removeBold">
                    @if (data_get($bid, 'get.lease_type') === 'percent')
                        {{ data_get($bid, 'get.lease_value') }}%
                    @else
                        {{ \App\Support\Format::money(data_get($bid, 'get.lease_value')) }}
                    @endif
                </span>
            </div>
            @endif
            @if (data_get($bid, 'get.purchase_value'))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Compensation if Purchase Option is Exercised:
                <span class="removeBold">
                    @if (data_get($bid, 'get.purchase_type') === 'percent')
                        {{ data_get($bid, 'get.purchase_value') }}%
                    @else
                        {{ \App\Support\Format::money(data_get($bid, 'get.purchase_value')) }}
                    @endif
                </span>
            </div>
            @endif
        @endif

        <div class="my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- 4. Legal Terms -->
        <h6 class="mt-3 mb-2" style="font-weight: 600;">Legal Terms:</h6>

        @if (data_get($bid, 'get.protection_period'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Protection Period Timeframe:
            <span class="removeBold">{{ data_get($bid, 'get.protection_period') }} Days</span>
        </div>
        @endif

        @if (data_get($bid, 'get.early_termination_fee_option'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Early Termination Fee:
            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(data_get($bid, 'get.early_termination_fee_option'), data_get($bid, 'get.early_termination_fee_amount') ? $fmtMoney(data_get($bid, 'get.early_termination_fee_amount')) : null) }}</span>
        </div>
        @endif

        @if (data_get($bid, 'get.retainer_fee_option'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Retainer Fee:
            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(data_get($bid, 'get.retainer_fee_option'), data_get($bid, 'get.retainer_fee_amount') ? $fmtMoney(data_get($bid, 'get.retainer_fee_amount')) : null) }}</span>
        </div>
        @if (in_array(strtolower(data_get($bid, 'get.retainer_fee_option')), ['yes']))
            @if (data_get($bid, 'get.retainer_fee_application'))
            @php $bidFormattedRetainer = \App\Support\CompensationFormatter::formatRetainerFeeApplication(data_get($bid, 'get.retainer_fee_application')); @endphp
            @if (!empty($bidFormattedRetainer))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Retainer Fee Application:
                <span class="removeBold">{{ $bidFormattedRetainer }}</span>
            </div>
            @endif
            @endif
        @endif
        @endif

        @if (data_get($bid, 'get.agency_agreement_timeframe'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Buyer Agency Agreement Timeframe:
            <span class="removeBold">{{ data_get($bid, 'get.agency_agreement_timeframe') === 'custom' ? data_get($bid, 'get.agency_agreement_custom') : data_get($bid, 'get.agency_agreement_timeframe') }}</span>
        </div>
        @endif

        <div class="my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- 5. Brokerage Relationship -->
        <h6 class="mt-3 mb-2" style="font-weight: 600;">Brokerage Relationship:</h6>

        @if (data_get($bid, 'get.brokerage_relationship'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Acceptable Brokerage Relationship:
            <span class="removeBold">{{ data_get($bid, 'get.brokerage_relationship') }}</span>
        </div>
        @endif

        <div class="my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- 6. Additional Terms -->
        <h6 class="mt-3 mb-2" style="font-weight: 600;">Additional Terms:</h6>

        @if (data_get($bid, 'get.additional_details_broker'))
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Terms:
            <span class="removeBold">{{ data_get($bid, 'get.additional_details_broker') }}</span>
        </div>
        @endif
    </div>
@endif
                                                                        <!-- 3. Additional Terms & Details -->
                                                                        @if (data_get($bid, 'get.additional_details_broker') || data_get($bid, 'get.additional_details'))
                                                                            <div class="mb-5">
                                                                                <h6 class="mb-3"
                                                                                    style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                                    <i
                                                                                        class="fa fa-file-contract me-2"></i>Additional
                                                                                    Terms & Details
                                                                                </h6>

                                                                                <!-- Additional Terms -->
                                                                                @if (data_get($bid, 'get.additional_details_broker'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Additional Terms</div>
                                                                                        <div class="text-muted"
                                                                                            style="font-style: italic;">
                                                                                            {{ data_get($bid, 'get.additional_details_broker') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Additional Details -->
                                                                                @if (data_get($bid, 'get.additional_details'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Additional Details</div>
                                                                                        <div class="text-muted"
                                                                                            style="font-style: italic;">
                                                                                            {{ data_get($bid, 'get.additional_details') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        @endif

                                                                        <!-- 4. Agent Presentation & Promotional Materials -->
                                                                        @if (data_get($bid, 'get.presentation_link') ||
                                                                                data_get($bid, 'get.video_upload') ||
                                                                                data_get($bid, 'get.business_card_link') ||
                                                                                data_get($bid, 'get.business_card') ||
                                                                                data_get($bid, 'get.promoMaterials'))
                                                                            <div class="mb-5">
                                                                                <h6 class="mb-3"
                                                                                    style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                                    <i
                                                                                        class="fa fa-chart-line me-2"></i>Agent
                                                                                    Presentation & Promotional Materials
                                                                                </h6>

                                                                                <!-- Virtual Presentation Section -->
                                                                                @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                                                                    <div class="mb-4">
                                                                                        <div class="fw-semibold mb-2"
                                                                                            style="color: #049399;">Virtual
                                                                                            Agent Presentation</div>

                                                                                        @if (data_get($bid, 'get.presentation_link'))
                                                                                            <div class="mb-2">
                                                                                                @php
                                                                                                    $presentationLink = data_get(
                                                                                                        $bid,
                                                                                                        'get.presentation_link',
                                                                                                    );
                                                                                                    // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                                                    if (
                                                                                                        !empty(
                                                                                                            $presentationLink
                                                                                                        ) &&
                                                                                                        !str_starts_with(
                                                                                                            $presentationLink,
                                                                                                            'http://',
                                                                                                        ) &&
                                                                                                        !str_starts_with(
                                                                                                            $presentationLink,
                                                                                                            'https://',
                                                                                                        )
                                                                                                    ) {
                                                                                                        $presentationLink =
                                                                                                            'https://' .
                                                                                                            $presentationLink;
                                                                                                    }
                                                                                                @endphp
                                                                                                <a href="{{ $presentationLink }}"
                                                                                                    target="_blank"
                                                                                                    class="text-primary text-decoration-none">
                                                                                                    <i
                                                                                                        class="fa fa-external-link-alt me-1"></i>
                                                                                                    Watch Presentation
                                                                                                </a>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.video_upload'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-medium mb-1"
                                                                                                    style="color: #049399;">
                                                                                                    Uploaded Video:</div>
                                                                                                @if (is_string(data_get($bid, 'get.video_upload')))
                                                                                                    <video controls
                                                                                                        style="width: 100%; max-width: 400px; border-radius: 6px; background: #000;">
                                                                                                        <source
                                                                                                            src="{{ asset('storage/' . data_get($bid, 'get.video_upload')) }}"
                                                                                                            type="video/mp4">
                                                                                                        Your browser does
                                                                                                        not support the
                                                                                                        video tag.
                                                                                                    </video>
                                                                                                @else
                                                                                                    <div
                                                                                                        class="text-muted">
                                                                                                        <i
                                                                                                            class="fa fa-video me-1"></i>
                                                                                                        Video file uploaded
                                                                                                    </div>
                                                                                                @endif
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Business Card Section -->
                                                                                @if (data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card'))
                                                                                    <div class="mb-4">
                                                                                        <div class="fw-semibold mb-2"
                                                                                            style="color: #049399;">
                                                                                            Business Card</div>

                                                                                        @if (data_get($bid, 'get.business_card_link'))
                                                                                            <div class="mb-2">
                                                                                                @php
                                                                                                    $businessCardLink = data_get(
                                                                                                        $bid,
                                                                                                        'get.business_card_link',
                                                                                                    );
                                                                                                    // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                                                    if (
                                                                                                        !empty(
                                                                                                            $businessCardLink
                                                                                                        ) &&
                                                                                                        !str_starts_with(
                                                                                                            $businessCardLink,
                                                                                                            'http://',
                                                                                                        ) &&
                                                                                                        !str_starts_with(
                                                                                                            $businessCardLink,
                                                                                                            'https://',
                                                                                                        )
                                                                                                    ) {
                                                                                                        $businessCardLink =
                                                                                                            'https://' .
                                                                                                            $businessCardLink;
                                                                                                    }
                                                                                                @endphp
                                                                                                <a href="{{ $businessCardLink }}"
                                                                                                    target="_blank"
                                                                                                    class="text-primary text-decoration-none">
                                                                                                    <i
                                                                                                        class="fa fa-external-link-alt me-1"></i>
                                                                                                    View Business Card
                                                                                                </a>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.business_card'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-medium mb-1"
                                                                                                    style="color: #049399;">
                                                                                                    Uploaded Business Card:
                                                                                                </div>
                                                                                                @if (is_string(data_get($bid, 'get.business_card')))
                                                                                                    @php
                                                                                                        $businessCardPath = data_get(
                                                                                                            $bid,
                                                                                                            'get.business_card',
                                                                                                        );
                                                                                                        $businessCardExtension = pathinfo(
                                                                                                            $businessCardPath,
                                                                                                            PATHINFO_EXTENSION,
                                                                                                        );
                                                                                                    @endphp

                                                                                                    @if (in_array(strtolower($businessCardExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                                                                        <img src="{{ asset('storage/' . $businessCardPath) }}"
                                                                                                            style="max-width: 300px; max-height: 200px; border-radius: 6px; border: 1px solid #e0e0e0;"
                                                                                                            alt="Business Card"
                                                                                                            class="img-fluid">
                                                                                                    @else
                                                                                                        <div
                                                                                                            class="d-flex align-items-center text-muted">
                                                                                                            <i
                                                                                                                class="fa fa-file me-2"></i>
                                                                                                            <div>
                                                                                                                <div>
                                                                                                                    Business
                                                                                                                    Card
                                                                                                                    File
                                                                                                                </div>
                                                                                                                <small>{{ $businessCardExtension }}
                                                                                                                    file</small>
                                                                                                            </div>
                                                                                                            <a href="{{ asset('storage/' . $businessCardPath) }}"
                                                                                                                target="_blank"
                                                                                                                class="btn btn-sm btn-outline-primary ms-2">
                                                                                                                <i
                                                                                                                    class="fa fa-download"></i>
                                                                                                            </a>
                                                                                                        </div>
                                                                                                    @endif
                                                                                                @else
                                                                                                    <div
                                                                                                        class="text-muted">
                                                                                                        <i
                                                                                                            class="fa fa-id-card me-1"></i>
                                                                                                        Business card
                                                                                                        uploaded
                                                                                                    </div>
                                                                                                @endif
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Marketing Materials Section -->
                                                                                @if (data_get($bid, 'get.promoMaterials'))
                                                                                    <div>
                                                                                        <div class="fw-semibold mb-2"
                                                                                            style="color: #049399;">
                                                                                            Marketing Materials</div>

                                                                                        @foreach (data_get($bid, 'get.promoMaterials') as $index => $material)
                                                                                            @if (!empty($material['type']) || !empty($material['link']) || !empty($material['files']))
                                                                                                <div
                                                                                                    class="mb-3 p-3 border rounded">
                                                                                                    @if (!empty($material['type']))
                                                                                                        <div class="fw-medium mb-2"
                                                                                                            style="color: #049399;">
                                                                                                            {{ $material['type'] }}
                                                                                                            @if ($material['type'] === 'Other' && !empty($material['other']))
                                                                                                                -
                                                                                                                {{ $material['other'] }}
                                                                                                            @endif
                                                                                                        </div>
                                                                                                    @endif

                                                                                                    @if (!empty($material['link']))
                                                                                                        <div
                                                                                                            class="mb-2">
                                                                                                            @php
                                                                                                                $materialLink =
                                                                                                                    $material[
                                                                                                                        'link'
                                                                                                                    ];
                                                                                                                // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                                                                if (
                                                                                                                    !empty(
                                                                                                                        $materialLink
                                                                                                                    ) &&
                                                                                                                    !str_starts_with(
                                                                                                                        $materialLink,
                                                                                                                        'http://',
                                                                                                                    ) &&
                                                                                                                    !str_starts_with(
                                                                                                                        $materialLink,
                                                                                                                        'https://',
                                                                                                                    )
                                                                                                                ) {
                                                                                                                    $materialLink =
                                                                                                                        'https://' .
                                                                                                                        $materialLink;
                                                                                                                }
                                                                                                            @endphp
                                                                                                            <a href="{{ $materialLink }}"
                                                                                                                target="_blank"
                                                                                                                class="text-primary text-decoration-none">
                                                                                                                <i
                                                                                                                    class="fa fa-external-link-alt me-1"></i>
                                                                                                                View
                                                                                                                Material
                                                                                                                Link
                                                                                                            </a>
                                                                                                        </div>
                                                                                                    @endif

                                                                                                    @if (!empty($material['files']))
                                                                                                        <div
                                                                                                            class="mb-2">
                                                                                                            <div class="fw-medium mb-1"
                                                                                                                style="color: #049399;">
                                                                                                                Uploaded
                                                                                                                Files:</div>
                                                                                                            <div
                                                                                                                class="row">
                                                                                                                @foreach ($material['files'] as $fileIndex => $filePath)
                                                                                                                    @if (is_string($filePath))
                                                                                                                        @php
                                                                                                                            $fileExtension = pathinfo(
                                                                                                                                $filePath,
                                                                                                                                PATHINFO_EXTENSION,
                                                                                                                            );
                                                                                                                            $fileName = basename(
                                                                                                                                $filePath,
                                                                                                                            );
                                                                                                                            $imageExtensions = [
                                                                                                                                'jpg',
                                                                                                                                'jpeg',
                                                                                                                                'png',
                                                                                                                                'gif',
                                                                                                                                'webp',
                                                                                                                            ];
                                                                                                                            $isImage = in_array(
                                                                                                                                strtolower(
                                                                                                                                    $fileExtension,
                                                                                                                                ),
                                                                                                                                $imageExtensions,
                                                                                                                            );
                                                                                                                        @endphp

                                                                                                                        <div
                                                                                                                            class="col-md-6 col-lg-4 mb-2">
                                                                                                                            <div
                                                                                                                                class="border rounded p-2 d-flex align-items-center">
                                                                                                                                @if ($isImage)
                                                                                                                                    <img src="{{ asset('storage/' . $filePath) }}"
                                                                                                                                        style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px;"
                                                                                                                                        alt="Marketing Material">
                                                                                                                                @else
                                                                                                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                                                                        style="width: 60px; height: 60px;">
                                                                                                                                        <i
                                                                                                                                            class="fa fa-file text-muted"></i>
                                                                                                                                    </div>
                                                                                                                                @endif
                                                                                                                                <div
                                                                                                                                    class="flex-grow-1">
                                                                                                                                    <div
                                                                                                                                        class="small text-truncate">
                                                                                                                                        {{ $fileName }}
                                                                                                                                    </div>
                                                                                                                                    <small
                                                                                                                                        class="text-muted">{{ strtoupper($fileExtension) }}
                                                                                                                                        file</small>
                                                                                                                                </div>
                                                                                                                                <a href="{{ asset('storage/' . $filePath) }}"
                                                                                                                                    target="_blank"
                                                                                                                                    class="btn btn-sm btn-outline-primary ms-1">
                                                                                                                                    <i
                                                                                                                                        class="fa fa-eye"></i>
                                                                                                                                </a>
                                                                                                                            </div>
                                                                                                                        </div>
                                                                                                                    @else
                                                                                                                        <div
                                                                                                                            class="col-md-6 col-lg-4 mb-2">
                                                                                                                            <div
                                                                                                                                class="border rounded p-2 d-flex align-items-center">
                                                                                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                                                                    style="width: 60px; height: 60px;">
                                                                                                                                    <i
                                                                                                                                        class="fa fa-file text-muted"></i>
                                                                                                                                </div>
                                                                                                                                <div
                                                                                                                                    class="flex-grow-1">
                                                                                                                                    <div
                                                                                                                                        class="small">
                                                                                                                                        File
                                                                                                                                        {{ $fileIndex + 1 }}
                                                                                                                                    </div>
                                                                                                                                    <small
                                                                                                                                        class="text-muted">Uploaded
                                                                                                                                        file</small>
                                                                                                                                </div>
                                                                                                                            </div>
                                                                                                                        </div>
                                                                                                                    @endif
                                                                                                                @endforeach
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    @endif
                                                                                                </div>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        @endif

                                                                        <!-- 5. Agent Information -->
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-3"
                                                                                style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                                <i
                                                                                    class="fa fa-address-card me-2"></i>Agent
                                                                                Information
                                                                            </h6>

                                                                            <div class="row">
                                                                                <!-- First Name -->
                                                                                @if (data_get($bid, 'get.first_name'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">First
                                                                                            Name</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.first_name') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Last Name -->
                                                                                @if (data_get($bid, 'get.last_name'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Last
                                                                                            Name</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.last_name') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Phone Number -->
                                                                                @if (data_get($bid, 'get.phone'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Phone
                                                                                            Number</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.phone') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Email -->
                                                                                @if (data_get($bid, 'get.email'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Email
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.email') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Brokerage -->
                                                                                @if (data_get($bid, 'get.brokerage'))
                                                                                    <div class="col-12 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Brokerage</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.brokerage') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- License Number -->
                                                                                @if (data_get($bid, 'get.license_no'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Real
                                                                                            Estate License #</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.license_no') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- NAR Member ID -->
                                                                                @if (data_get($bid, 'get.nar_id'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">NAR
                                                                                            Member ID</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.nar_id') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>

                                                                    </div>
                                                                    <div class="modal-footer"
                                                                        style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px;">
                                                                        <div class="w-100 mb-3 p-3 text-center"
                                                                            style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                                                            <i class="fa fa-shield me-2"></i>
                                                                            <strong>Confidential:</strong> This information
                                                                            is private and only visible to you as the
                                                                            listing owner.
                                                                        </div>
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal"
                                                                            style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <!-- For non-owners, show appropriate message -->
                                                        @if ($auth_id && data_get($bid, 'user_id') == $auth_id)
                                                            <!-- Agent viewing their own bid -->
                                                            <div class="alert alert-info mt-3 p-3 text-center"
                                                                style="border-radius: 6px; background: #e8f4f5; color: #049399; border: none;">
                                                                <i class="fa fa-eye me-2"></i> <strong>Your Private
                                                                    Terms:</strong> You can see your full compensation terms
                                                                and additional details in your bid management dashboard.
                                                            </div>
                                                        @else
                                                            <!-- Public or other agents -->
                                                            <div class="alert alert-warning mt-3 p-3 text-center"
                                                                style="border-radius: 6px; background: #e8f4f5; color: #049399; border: none;">
                                                                <i class="fa fa-lock me-2"></i> <strong>Private
                                                                    Information:</strong> Broker compensation terms and
                                                                additional details are only visible to the listing owner.
                                                            </div>
                                                        @endif
                                                    @endif

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
                                                        $state = in_array($rawState, [null, 0, '0'], true)
                                                            ? '0'
                                                            : (string) $rawState;
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
    <div class="mb-4 broker-compensation-section">
        <h6 class="mb-3" style="font-weight: 600; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
            Broker Compensation & Agency Agreement Terms
        </h6>

        <!-- 1. Buyer's Broker Compensation -->
        <h6 class="mt-3 mb-2" style="font-weight: 600; font-size: 13px;">Buyer's Broker Compensation:</h6>
        
        @if (!empty($allMeta['commission_structure']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Buyer's Broker Commission Structure:
            <span class="removeBold">{{ $allMeta['commission_structure'] }}</span>
        </div>
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
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Buyer's Broker Purchase Fee:
            <span class="removeBold">{{ $ctrPurchaseVal }}</span>
        </div>
        @endif

        <div class="my-2"><hr style="border-top: 1px solid #eee;"></div>

        <!-- 2. Buyer's Broker Lease Fee -->
        <h6 class="mt-3 mb-2" style="font-weight: 600; font-size: 13px;">Buyer's Broker Lease Fee:</h6>

        @if (!empty($allMeta['interested_lease_option']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Interested in a Lease Agreement:
            <span class="removeBold">{{ $allMeta['interested_lease_option'] }}</span>
        </div>
        @endif

        @if (!empty($allMeta['interested_lease_option']) && $allMeta['interested_lease_option'] === 'Yes' && !empty($allMeta['lease_fee_type']))
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
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Buyer's Broker Lease Fee:
            <span class="removeBold">{{ $ctrLeaseVal }}</span>
        </div>
        @endif

        <div class="my-2"><hr style="border-top: 1px solid #eee;"></div>

        <!-- 3. Lease-Option Details -->
        <h6 class="mt-3 mb-2" style="font-weight: 600; font-size: 13px;">Lease-Option Details:</h6>

        @if (!empty($allMeta['interested_lease_option_agreement']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Interested in a Lease-Option Agreement:
            <span class="removeBold">{{ $allMeta['interested_lease_option_agreement'] }}</span>
        </div>
        @endif

        @if (!empty($allMeta['interested_lease_option_agreement']) && $allMeta['interested_lease_option_agreement'] === 'Yes')
            @if (!empty($allMeta['lease_value']))
            <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
                Compensation for Creating the Lease-Option Agreement:
                <span class="removeBold">
                    @if (($allMeta['lease_type'] ?? '') === 'percent')
                        {{ $allMeta['lease_value'] }}%
                    @else
                        {{ \App\Support\Format::money($allMeta['lease_value']) }}
                    @endif
                </span>
            </div>
            @endif
            @if (!empty($allMeta['purchase_value']))
            <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
                Compensation if Purchase Option is Exercised:
                <span class="removeBold">
                    @if (($allMeta['purchase_type'] ?? '') === 'percent')
                        {{ $allMeta['purchase_value'] }}%
                    @else
                        {{ \App\Support\Format::money($allMeta['purchase_value']) }}
                    @endif
                </span>
            </div>
            @endif
        @endif

        <div class="my-2"><hr style="border-top: 1px solid #eee;"></div>

        <!-- 4. Legal Terms -->
        <h6 class="mt-3 mb-2" style="font-weight: 600; font-size: 13px;">Legal Terms:</h6>

        @if (!empty($allMeta['protection_period']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Protection Period Timeframe:
            <span class="removeBold">{{ $allMeta['protection_period'] }} Days</span>
        </div>
        @endif

        @if (!empty($allMeta['early_termination_fee_option']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Early Termination Fee:
            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical($allMeta['early_termination_fee_option'], !empty($allMeta['early_termination_fee_amount']) ? \App\Support\Format::money($allMeta['early_termination_fee_amount']) : null) }}</span>
        </div>
        @endif

        @if (!empty($allMeta['retainer_fee_option']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Retainer Fee:
            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical($allMeta['retainer_fee_option'], !empty($allMeta['retainer_fee_amount']) ? \App\Support\Format::money($allMeta['retainer_fee_amount']) : null) }}</span>
        </div>
        @if (in_array(strtolower($allMeta['retainer_fee_option']), ['yes']))
            @if (!empty($allMeta['retainer_fee_application']))
            @php $counterFormattedRetainer = \App\Support\CompensationFormatter::formatRetainerFeeApplication($allMeta['retainer_fee_application']); @endphp
            @if (!empty($counterFormattedRetainer))
            <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
                Retainer Fee Application:
                <span class="removeBold">{{ $counterFormattedRetainer }}</span>
            </div>
            @endif
            @endif
        @endif
        @endif

        @if (!empty($allMeta['agency_agreement_timeframe']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Buyer Agency Agreement Timeframe:
            <span class="removeBold">{{ ($allMeta['agency_agreement_timeframe'] === 'custom' && !empty($allMeta['agency_agreement_custom'])) ? $allMeta['agency_agreement_custom'] : $allMeta['agency_agreement_timeframe'] }}</span>
        </div>
        @endif

        <div class="my-2"><hr style="border-top: 1px solid #eee;"></div>

        <!-- 5. Brokerage Relationship -->
        <h6 class="mt-3 mb-2" style="font-weight: 600; font-size: 13px;">Brokerage Relationship:</h6>

        @if (!empty($allMeta['brokerage_relationship']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Acceptable Brokerage Relationship:
            <span class="removeBold">{{ $allMeta['brokerage_relationship'] }}</span>
        </div>
        @endif

        <div class="my-2"><hr style="border-top: 1px solid #eee;"></div>

        <!-- 6. Additional Terms -->
        <h6 class="mt-3 mb-2" style="font-weight: 600; font-size: 13px;">Additional Terms:</h6>

        @if (!empty($allMeta['additional_details_broker']))
        <div class="col-md-12 col-12 pt-2 fw-bold" style="font-size: 12px;">
            Additional Terms:
            <span class="removeBold">{{ $allMeta['additional_details_broker'] }}</span>
        </div>
        @endif
    </div>
@endif
                                                                            <!-- Services Offered (Categorized using ServicesFormatter) -->
                                                                            @php
                                                                                $counterServices = is_string(
                                                                                    $allMeta['services'] ?? ''
                                                                                )
                                                                                    ? json_decode($allMeta['services'] ?? '', true)
                                                                                    : ($allMeta['services'] ?? []);
                                                                                $counterServices = $counterServices ?: [];
                                                                                
                                                                                // Order services using ServicesFormatter
                                                                                $counterOrderedServices = !empty($counterServices)
                                                                                    ? \App\Support\ServicesFormatter::orderSelectedServices($counterServices, $flowKey)
                                                                                    : [];
                                                                            @endphp

                                                                            @if (!empty($counterServices))
                                                                                <div style="margin-top: 20px;">
                                                                                    <label style="font-size: 15px; font-weight: 600; color: #049399; display: block; margin-bottom: 10px;">
                                                                                        Services Offered:
                                                                                    </label>
                                                                                    @if (!empty($counterOrderedServices))
                                                                                        @foreach ($counterOrderedServices as $catName => $catSrvs)
                                                                                            @if (!empty($catSrvs))
                                                                                                <div class="mb-2">
                                                                                                    <strong style="font-size: 13px;">{{ $catName }}</strong>
                                                                                                    <ul class="services services-offered" style="margin-top: 5px;">
                                                                                                        @foreach ($catSrvs as $svc)
                                                                                                            <li style="font-size: 12px;">{{ $svc }}</li>
                                                                                                        @endforeach
                                                                                                    </ul>
                                                                                                </div>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    @else
                                                                                        <ul class="services services-offered">
                                                                                            @foreach ($counterServices as $svc)
                                                                                                @if ($svc != 'Other')
                                                                                                    <li style="font-size: 12px;">{{ $svc }}</li>
                                                                                                @endif
                                                                                            @endforeach
                                                                                        </ul>
                                                                                    @endif
                                                                                </div>
                                                                            @endif

                                                                            @php
                                                                                $counter_other_services = is_string($allMeta['other_services'] ?? '')
                                                                                    ? json_decode($allMeta['other_services'] ?? '', true)
                                                                                    : ($allMeta['other_services'] ?? []);
                                                                                $counter_other_services = $counter_other_services ?: [];
                                                                            @endphp

                                                                            @if (!empty($counter_other_services))
                                                                                <div style="margin-top: 15px;">
                                                                                    <label style="font-size: 13px; font-weight: 600;">
                                                                                        Other Services:
                                                                                    </label>
                                                                                    <ul class="services services-offered">
                                                                                        @foreach ($counter_other_services as $other_svc)
                                                                                            <li style="font-size: 12px;">{{ $other_svc }}</li>
                                                                                        @endforeach
                                                                                    </ul>
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
                                                        <div class="counter-response-buttons mt-3 pt-3 border-top">
                                                            <h6>Respond to this Counter Offer:</h6>

                                                            @if ($isExpired)
                                                                {{-- 🔹 Show expired message if auction expired --}}
                                                                <div class="alert alert-warning text-center mt-2 mb-0 p-2" style="font-size: 15px">
                                                                    <strong>Bidding/Counter Period Ended</strong>
                                                                </div>
                                                            @else
                                                                {{-- 🔹 Active Counter Actions --}}
                                                                <div class="d-flex gap-3 flex-wrap justify-content-between">
                                                                    <form class="d-inline"
                                                                        action="{{ route('buyer.hire.agent.auction.counter.bid.accept') }}"
                                                                        method="post">
                                                                        @csrf
                                                                        <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                        <input type="hidden" name="counter_bid_id" value="{{ data_get($counterBid, 'id') }}">
                                                                        <button type="submit" class="btn-custom btn-accept" style="font-size:16px">Accept</button>
                                                                    </form>

                                                                    <form class="d-inline"
                                                                        action="{{ route('buyer.hire.agent.auction.counter.bid.reject') }}"
                                                                        method="post">
                                                                        @csrf
                                                                        <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                        <input type="hidden" name="counter_bid_id" value="{{ data_get($counterBid, 'id') }}">
                                                                        <button type="submit" class="btn-custom btn-reject" style="font-size:16px">Reject</button>
                                                                    </form>

                                                                    <form class="d-inline"
                                                                        action="{{ route('buyer.hire.agent.auction.bid.counter') }}"
                                                                        method="post">
                                                                        @csrf
                                                                        <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                        <input type="hidden" name="counter_bid_id" value="{{ data_get($counterBid, 'id') }}">
                                                                        <button type="submit" class="btn-custom btn-counter" style="font-size:16px">Counter</button>
                                                                    </form>
                                                                </div>
                                                            @endif
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
                                                    @elseif (!$showCounterBids && $counterBids->count() > 0)
                                                        {{-- Show private message for non-authorized users --}}
                                                        <div class="alert alert-info mt-3 p-2 small">
                                                            <i class="fa fa-lock"></i> <strong>Private Counter
                                                                Bids:</strong>
                                                            Counter bidding history exists but is only visible to the
                                                            listing owner and the bidding agent.
                                                        </div>
                                                    @endif

                                                    {{-- Main Bid Actions (keep this section as is) --}}
                                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                                        @if ($state === '0' && $isOwnerRow && !$isSold)

                                                            @if ($isExpired)
                                                            {{-- 🔹 Show expired message if auction expired --}}
                                                                <div class="alert alert-warning text-center mt-2 mb-0 p-2 ml-2" style="margin-left: 20px;">
                                                                    <strong>Bidding/Counter Period Ended</strong>
                                                                </div>
                                                            @else
                                                            <div class="biding-btn">
                                                                <form
                                                                    action="{{ route('buyer.hire.agent.auction.bid.accept') }}"
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
                                                                    action="{{ route('buyer.hire.agent.auction.bid.reject') }}"
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
                                                                    action="{{ route('buyer.hire.agent.auction.bid.counter') }}"
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
                                                        @endif

                                                        @if ($state === 'accepted')
                                                            @if (Auth::id() == $ownerId)
                                                                <div
                                                                    class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                                    ✅ This bid has been accepted.
                                                                </div>
                                                            @else
                                                                <div
                                                                    class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                                    ✅ {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted
                                                                    the bid.
                                                                </div>
                                                            @endif
                                                        @elseif ($state === 'rejected')
                                                            @if (Auth::id() == $ownerId)
                                                                <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                                    ❌ This bid has been rejected.
                                                                </div>
                                                            @else
                                                                <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                                    ❌ {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected
                                                                    the bid.
                                                                </div>
                                                            @endif
                                                        @elseif ($state === '0')
                                                            @if (data_get($bid, 'user_id') == Auth::id())
                                                                <div
                                                                    class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                                                    ⏳ Waiting for response from
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }}...
                                                                </div>
                                                            @else
                                                                <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                                                    ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }}
                                                                    is pending.
                                                                </div>
                                                            @endif
                                                        @endif
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
@endpush

