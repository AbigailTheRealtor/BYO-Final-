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

    /* Section Title Hierarchy - Larger, bold, spaced, more prominent */
    .card-header h4,
    .section-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        color: #0f1a24;
    }

    /* Broker Compensation subsection headers - breathing room */
    h5.mt-3.mb-2 {
        padding-top: 0.75rem;
        margin-top: 1rem !important;
    }

    /* Broker Compensation section text - match other section text color */
    .broker-compensation-section,
    .broker-compensation-section p,
    .broker-compensation-section .col-md-12,
    .broker-compensation-section .fw-bold {
        color: #34465c !important;
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

<div class="container listingDescription">
    <div class="row">
        <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
            <div class="card description">
                <div class="card-header">
                    <h4 style="margin-left: 15px; margin-top: 10px;">Listing Details: </h5>
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
                            Current Representation Status with Broker?
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
                    <div class="card-header">
                        <h4>Property Details: </h4>
                    </div>

                    <div class="row" style="flex-wrap: wrap;">

                        @if (@$auction->get->address != null)
                        @if (auth()->check() && auth()->user()->id == @$auction->user_id)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            
                            Property Address:
                            <span class="removeBold">{{ @$auction->get->address }}</span>
                        </div>
                        @endif
                        @endif

                        @if (@$auction->get->cities != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"> City:
                            @if (gettype(@$auction->get->cities) == 'array')
                            @foreach (@$auction->get->cities as $item)
                            <span class="removeBold">{{ $item }}</span>
                            @endforeach
                            @endif
                        </div>
                        @endif
                        @if (@$auction->get->counties != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            County:
                            @if (gettype(@$auction->get->counties) == 'array')
                            @foreach (@$auction->get->counties as $item)
                            <span class="removeBold">{{ $item }}</span>
                            @endforeach
                            @endif
                        </div>
                        @endif






                        @if (!empty($auction->get->zipCodes) && is_array($auction->get->zipCodes))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Zip Code:

                            @foreach ($auction->get->zipCodes as $zip)
                            <span class="removeBold badge bg-secondary">
                                {{ @$zip }}
                            </span>
                            @endforeach

                        </div>
                        @endif






                        @if (@$auction->get->state != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"> State:
                            <span class="removeBold">{{ @$auction->get->state }}</span>
                        </div>
                        @endif

                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Property
                            Style :<span class="removeBold"> {{ @$auction->get->property_type }}</span><br>

                            <span class="removeBold badge bg-secondary">{{@$auction->get->property_items}}</span>

                        </div>


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
                    @if (@$auction->get->condition_prop != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"> Property Condition:
                        <span class="removeBold">{{ @$auction->get->condition_prop }}</span>
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
            @if (@$auction->get->bathrooms != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Bathrooms:
                <span class="removeBold">{{ @$auction->get->bathrooms !== 'Other' ? @$auction->get->bathrooms : @$auction->get->other_bathrooms }}</span>
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
            @if (@$auction->get->tenant_require != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Furnishings:
                <span class="removeBold">
                    <?php
                    $tenantRequire = @$auction->get->tenant_require;
                    $tenantRequire = trim($tenantRequire, '"');
                    ?>
                    <span class="removeBold badge bg-secondary">{{ $tenantRequire }}</span>
                </span>
            </div>
            @endif
            @if (@$auction->get->carport_needed != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Carport :
                <span class="removeBold">
                    <span
                        class="removeBold badge bg-secondary">{{ @$auction->get->carport_needed }}</span>

                </span>
            </div>
            @endif
            @if (@$auction->get->other_carport_needed != '')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Number of Carport Spaces:
                <span class="removeBold">
                    <span
                        class="removeBold badge bg-secondary">{{ @$auction->get->other_carport_needed }}</span>

                </span>
            </div>
            @endif
            @if (@$auction->get->garage_needed != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Garage:
                <span class="removeBold">
                    <span
                        class="removeBold badge bg-secondary">{{ @$auction->get->garage_needed }}</span>

                </span>
            </div>
            @endif
            @if (@$auction->get->other_garage_needed != '')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Number of Garage Spaces:
                <span class="removeBold">
                    <span
                        class="removeBold badge bg-secondary">{{ @$auction->get->other_garage_needed }}</span>

                </span>
            </div>
            @endif
            @endif

            @if ($isCommercial)
            @php
                $garageParkingOptions = @$auction->get->garage_parking_spaces_option;
                if (is_string($garageParkingOptions)) {
                    $garageParkingOptions = json_decode($garageParkingOptions, true);
                }
                if (!is_array($garageParkingOptions)) {
                    $garageParkingOptions = [];
                }
                $garageParkingOptions = array_filter($garageParkingOptions, fn($v) => $v !== 'Other');
                $otherParking = @$auction->get->other_parking_space_wrapper;
                if (!empty($otherParking)) {
                    $garageParkingOptions[] = $otherParking;
                }
            @endphp
            @if (!empty($garageParkingOptions))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Garage/Parking Features:
                @foreach ($garageParkingOptions as $parkingOption)
                <span class="removeBold badge bg-secondary">{{ $parkingOption }}</span>
                @endforeach
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
            $displayViews = [];

            // Add all selected view preferences, filtering out "Other"
            if (!empty($auction->get->view_preference) && $auction->get->view_preference != 'null') {
                foreach ($auction->get->view_preference as $item) {
                    if ($item !== 'Other') {
                        $displayViews[] = $item;
                    }
                }
            }

            // Add the custom preferences if they exist
            if (!empty($auction->get->other_preferences)) {
                $displayViews[] = $auction->get->other_preferences;
            }
            @endphp

            @if (!empty($displayViews))
            <div class="col-md-12 col-12 pt-2 fw-bold"> View:
                @foreach ($displayViews as $item)
                <span class="removeBold badge bg-secondary">
                    {{ $item }}
                </span>
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
            @if (!empty($auction->get->non_negotiable_amenities) || !empty($auction->get->other_non_negotiable_amenities))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Amenities and Property Features:

                @php
                $displayAmenities = [];

                // Process non_negotiable_amenities array
                if (!empty($auction->get->non_negotiable_amenities)) {
                foreach ($auction->get->non_negotiable_amenities as $item) {
                if ($item === 'Other' && !empty($auction->get->other_non_negotiable_amenities)) {
                // Replace "Other" with the custom value
                $displayAmenities[] = $auction->get->other_non_negotiable_amenities;
                } elseif ($item !== 'Other') {
                // Keep all other values except "Other"
                $displayAmenities[] = $item;
                }
                }
                }

                // Add custom amenities if they exist but "Other" wasn't selected
                if (!empty($auction->get->other_non_negotiable_amenities) &&
                (empty($auction->get->non_negotiable_amenities) ||
                !in_array('Other', $auction->get->non_negotiable_amenities))) {
                $displayAmenities[] = $auction->get->other_non_negotiable_amenities;
                }
                @endphp

                @foreach ($displayAmenities as $amenity)
                <span class="removeBold badge bg-secondary">{{ $amenity }}</span>
                @endforeach
            </div>
            @endif


            @if (@$auction->get->pets)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Pets Allowed:
                <span class="removeBold">
                    {{ @$auction->get->pets }}</span>
            </div>
            @endif
            @if (@$auction->get->pets == 'Yes')
            <div class="col-md-12 col-12 pt-2 fw-bold">
               Number of Pets Allowed:
                <span class="removeBold">
                    {{ @$auction->get->number_of_pets != '' ? @$auction->get->number_of_pets : '' }}</span>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"> Acceptable Pet Types:
                <span class="removeBold">
                    {{ @$auction->get->type_of_pets != '' ? @$auction->get->type_of_pets : '' }}</span>
            </div>

            <div class="col-md-12 col-12 pt-2 fw-bold">
               Maximum Weight Per Pet (lbs):
                <span class="removeBold">
                    {{ @$auction->get->weight_of_pets != '' ? @$auction->get->weight_of_pets : '' }}</span>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"> Pet
                Restrictions:
                <span class="removeBold">
                    {{ @$auction->get->breed_restrictions != '' ? @$auction->get->breed_restrictions : '' }}</span>
            </div>
            @endif

        </div>
        <hr>
        <div class="card-header">
            <h4>Leasing Terms: </h4>
        </div>
        @if (@$auction->get->occupant_status != '' && @$auction->get->occupant_status != 'null')
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold">  Occupant Type:
                <span class="removeBold">{{ @$auction->get->occupant_status }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->occupant_tenant != '' && @$auction->get->occupant_tenant != 'null')
            <div class="row" style="flex-wrap: wrap;">
                <div class="col-12 fw-bold">  Occupied Until:
                    <span class="removeBold">
                        @php
                            // Format the date from Y-m-d to F j, Y (e.g., January 9, 2026)
                            $date = \Carbon\Carbon::parse($auction->get->occupant_tenant);
                            echo $date->format('F j, Y');
                        @endphp
                    </span>
                </div>
            </div>
        @endif

        @if (@$auction->get->leasing_spaces != '' && @$auction->get->leasing_spaces != 'null')
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold">  Leasing Space:
                <span class="removeBold">{{ @$auction->get->leasing_spaces }}</span>
            </div>
        </div>
        @endif

        @if (@$auction->get->restrictions != '' && @$auction->get->restrictions != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Restrictions Include:
                <span class="removeBold">{{ $auction->get->restrictions }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->maintenance_by != '' && @$auction->get->maintenance_by != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance and Repairs Are Handled By:
                <span class="removeBold">{{ $auction->get->maintenance_by }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->maintenance_response_time != '' && @$auction->get->maintenance_response_time != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance Response Time:
                <span class="removeBold">{{ $auction->get->maintenance_response_time }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->included_storage_space_res_both != '' && @$auction->get->included_storage_space_res_both != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $auction->get->included_storage_space_res_both }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->storage_space_res_both != '' && @$auction->get->storage_space_res_both != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $auction->get->storage_space_res_both }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->included_storage_space_com_entire != '' && @$auction->get->included_storage_space_com_entire != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $auction->get->included_storage_space_com_entire }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->storage_space_com_entire != '' && @$auction->get->storage_space_com_entire != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $auction->get->storage_space_com_entire }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->guests_allowed != '' && @$auction->get->guests_allowed != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Guests are:
                <span class="removeBold">{{ $auction->get->guests_allowed }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->common_areas_access != '' && @$auction->get->common_areas_access != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Shared Areas Available:
                <span class="removeBold">{{ $auction->get->common_areas_access }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->utilities != '' && @$auction->get->utilities != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Utilities:
                <span class="removeBold">{{ $auction->get->utilities }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->common_areas_cleaning != '' && @$auction->get->common_areas_cleaning != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Common Area Maintenance:
                <span class="removeBold">{{ $auction->get->common_areas_cleaning }}</span>
            </div>
        </div>
        @endif

        @if (@$auction->get->included_storage_space_res_single != '' && @$auction->get->included_storage_space_res_single != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $auction->get->included_storage_space_res_single }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->storage_space_res_single != '' && @$auction->get->storage_space_res_single != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $auction->get->storage_space_res_single }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->included_storage_space_com_single != '' && @$auction->get->included_storage_space_com_single != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $auction->get->included_storage_space_com_single }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->storage_space_com_single != '' && @$auction->get->storage_space_com_single != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $auction->get->storage_space_com_single }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->included_storage_space != '' && @$auction->get->included_storage_space != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $auction->get->included_storage_space }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->storage_space != '' && @$auction->get->storage_space != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $auction->get->storage_space }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->bathroom_facilities != '' && @$auction->get->bathroom_facilities != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Bathroom Facilities:
                <span class="removeBold">{{ $auction->get->bathroom_facilities }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->room_size != '' && @$auction->get->room_size != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Approximate Room Size:
                <span class="removeBold">{{ $auction->get->room_size }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->shared_amenities != '' && @$auction->get->shared_amenities != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Shared Amenities Include:
                <span class="removeBold">{{ $auction->get->shared_amenities }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->building_hours != '' && @$auction->get->building_hours != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Building Hours:
                <span class="removeBold">{{ $auction->get->building_hours }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->access_24_7 != '' && @$auction->get->access_24_7 != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">24/7 Access Available:
                <span class="removeBold">{{ $auction->get->access_24_7 }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->zoning_allows != '' && @$auction->get->zoning_allows != 'null')
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Zoning Allows:
                <span class="removeBold">{{ $auction->get->zoning_allows }}</span>
            </div>
        </div>
        @endif

        @php
        $tenantPays = is_string($auction->get->tenant_pays)
        ? json_decode($auction->get->tenant_pays, true)
        : $auction->get->tenant_pays;

        $ownerPays = is_string($auction->get->owner_pays)
        ? json_decode($auction->get->owner_pays, true)
        : $auction->get->owner_pays;

        $termsOfLease = is_string($auction->get->terms_of_lease)
        ? json_decode($auction->get->terms_of_lease, true)
        : $auction->get->terms_of_lease;
        @endphp


        @if (count($tenantPays) > 0)
        <div class="col-md-12 col-12 pt-2 fw-bold">
             Tenant Pays:
            <ul>
                @foreach ($tenantPays as $tenant_pay)
                <li style="font-size: 16px;">{{ $tenant_pay }}</li>
                @endforeach
            </ul>
        </div>

        @endif


        @if (!empty($ownerPays))
        <div class="col-md-12 col-12 pt-2 fw-bold">
             Owner Pays:
            <ul>
                @foreach ($ownerPays as $owner_pay)
                <li style="font-size: 16px;">{{ $owner_pay }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if (!empty($termsOfLease))
        <div class="col-md-12 col-12 pt-2 fw-bold">
             Terms of Lease:
            <ul>
                @foreach ($termsOfLease as $lease)
                <li style="font-size: 16px;">{{ $lease }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if (@$auction->get->desired_rental_amount != '' && @$auction->get->desired_rental_amount != 'null')
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold">  Desired Rental
                Amount:
                <span class="removeBold">${{ @$auction->get->desired_rental_amount }}</span>
            </div>
        </div>
        @endif
        @if (@$auction->get->lease_amount_frequency != '' && @$auction->get->lease_amount_frequency != 'null')
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold">  Lease Amount
                Frequency:
                <span class="removeBold">{{ @$auction->get->lease_amount_frequency }}</span>
            </div>
        </div>
        @endif

        @if (@$auction->get->desired_lease_length != null || @$auction->get->other_lease_term != null)
        <div class="col-md-12 col-12 pt-2 fw-bold"> Desired Lease Term:
            @foreach (@$auction->get->desired_lease_length as $item)
                @if ($item !== 'Other')
                <span class="removeBold badge bg-secondary">{{ @$item }}</span>
                @endif
            @endforeach
            @if (@$auction->get->other_lease_term)
            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_lease_term }}</span>
            @endif
        </div>
        @endif
        @if (@$auction->get->rent_includes != null || @$auction->get->other_rent_include != null)
        <div class="col-md-12 col-12 pt-2 fw-bold"> Rent Includes:
            @foreach (@$auction->get->rent_includes as $item)
                @if ($item !== 'Other')
                <span class="removeBold badge bg-secondary">{{ @$item }}</span>
                @endif
            @endforeach
            @if (@$auction->get->other_rent_include)
            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_rent_include }}</span>
            @endif
        </div>
        @endif


        <hr>

        @php
        // Check if services exist before showing the section
        $hasServices = !empty(@$auction->get->services) || !empty(@$auction->get->other_services);
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
                    $matchedServices = array_filter($allServices, function($service) use ($categoryServices) {
                        return in_array($service, $categoryServices);
                    });
                @endphp
                @if (!empty($matchedServices))
                <div class="mt-3">
                    <strong>{{ $categoryName }}</strong>
                    <ul class="services">
                        @foreach ($matchedServices as $service)
                        <li style="font-size: 16px;">{{ $service }}</li>
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
        </div>
        @endif
        <hr>
        @if (@$auction->get->additional_details != null)
        <div class="card-header">
            <h4>Additional Details: </h4>
        </div>

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Details: <span
                class="removeBold">{{ $auction->get->additional_details ?? '' }}</span>
        </div>
        @endif

        <hr />
        <div class="card-header section-header">
            <h4 class="section-title">Broker Compensation & Agency Agreement Terms</h4>
        </div>

        <div class="broker-compensation-section">

        <!-- Landlord's Broker Compensation Sub-section -->
        <h5 class="mt-3 mb-2"><strong>Landlord's Broker Compensation:</strong></h5>

        @if (@$auction->get->purchase_fee_type != null)
        @php
            // Build combined Landlord's Broker Lease Fee display
            $landlordLeaseFeeType = $canon(@$auction->get->purchase_fee_type ?? '');
            $landlordLeaseFeeCombined = '—';
            
            if ($landlordLeaseFeeType === 'Flat Fee' && @$auction->get->purchase_fee_flat) {
                $landlordLeaseFeeCombined = $fmtMoney(@$auction->get->purchase_fee_flat);
            } elseif ($landlordLeaseFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->purchase_fee_rental_period) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_rental_period) . ' of rent due each rental period';
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

        @if (@$auction->get->tenant_broker_commission_structure != 'no_compensation')
        @php
            // Build combined Tenant's Broker Fee display
            $tenantFeeType = $canon(@$auction->get->tenant_broker_fee_structure ?? '');
            $tenantFeeCombined = '—';
            
            if ($tenantFeeType === 'Flat Fee' && @$auction->get->tenant_broker_flat_fee) {
                $tenantFeeCombined = $fmtMoney(@$auction->get->tenant_broker_flat_fee);
            } elseif ($tenantFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->tenant_broker_percentage) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_percentage) . ' of rent due each rental period';
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
        <h5 class="mt-3 mb-2"><strong>Payment Timing & Renewal Terms:</strong></h5>

        @if (@$auction->get->broker_fee_timing != null)
        @php
            $paymentTimingDisplay = @$auction->get->broker_fee_timing;
            $daysValue = @$auction->get->broker_fee_days_from_rent ?? @$auction->get->broker_fee_days_after_lease ?? @$auction->get->broker_fee_days_after_rent ?? null;
            
            if ($paymentTimingDisplay === 'other' || $paymentTimingDisplay === 'Other') {
                $paymentTimingDisplay = @$auction->get->broker_fee_timing_other ?? '';
            }
            
            // Add days if applicable
            if ($daysValue && (
                strpos($paymentTimingDisplay, 'Calendar Days') !== false || 
                strpos($paymentTimingDisplay, 'Rent Collected') !== false
            )) {
                $paymentTimingDisplay .= ' (' . $daysValue . ' days)';
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
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_percentage) . ' of rent due each rental period';
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
            $salesTaxValue = @$auction->get->renewal_fee_sales_tax_lease_value ?? @$auction->get->renewal_fee_sales_tax_first_month ?? @$auction->get->renewal_fee_sales_tax_flat_fee ?? null;
        @endphp
        @if (!empty($salesTaxValue) && $salesTaxValue !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ $salesTaxValue === 'including' ? 'Including Sales Tax' : ($salesTaxValue === 'excluding' ? 'Excluding Sales Tax' : $salesTaxValue) }}</span>
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
        <h5 class="mt-3 mb-2"><strong>Property Management:</strong></h5>

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
                $pmFeeCombined = $fmtPercent(@$auction->get->interested_in_property_management_fee_rental_periord) . ' of rent due each rental period';
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
        <h5 class="mt-3 mb-2"><strong>Lease-Option Details:</strong></h5>

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
        <h5 class="mt-3 mb-2"><strong>Purchase Fee Details:</strong></h5>

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
        <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>

        @if (@$auction->get->protection_period != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Protection Period Timeframe:
            <span class="removeBold">{{ $auction->get->protection_period }} days</span>
        </div>
        @endif

        @if ($isResidential && @$auction->get->early_termination_fee_option != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Early Termination Fee:
            <span class="removeBold">{{ $auction->get->early_termination_fee_option == 'yes' ? 'Yes' : 'No' }}</span>
        </div>
        @endif

        @if ($isResidential && @$auction->get->early_termination_fee_option == 'yes' && @$auction->get->early_termination_fee_amount != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Termination Fee Amount:
            <span class="removeBold">{{ $fmtMoney($auction->get->early_termination_fee_amount) }}</span>
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
        <h5 class="mt-3 mb-2"><strong>Brokerage Relationship:</strong></h5>

        @if (@$auction->get->brokerage_relationship != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Acceptable Brokerage Relationship:
            <span class="removeBold">{{ $auction->get->brokerage_relationship ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->additional_details_broker != null)
        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Additional Terms Sub-section -->
        <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Terms:
            <span class="removeBold">{{ $auction->get->additional_details_broker }}</span>
        </div>
        @endif

        </div> <!-- end broker-compensation-section -->
        <hr />
        <div class="card-header">
            <h4>Landlord's Info </h4>
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
        <a href="{{ route('landlord.hire.agent.auction.edit', ['auctionId' => $auction->id]) }}" 
           class="btn btn-outline-primary btn-sm">
            <i class="fa fa-edit me-1"></i> Edit Listing
        </a>
    </div>
    @endif
    <hr>

    {{-- 🏆 Display Winner Information if Listing is Sold --}}
    @php
        $acceptedBid = $auction->bids->where('accepted', 'accepted')->first();
        // Check for accepted counter bids
        $acceptedCounterBid = null;
        foreach ($auction->bids as $bid) {
            $counterBid = \App\Models\LandlordCounterBidding::where('landlord_agent_auction_bid_id', $bid->id)
                            ->where('accepted', 'accepted')
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
            <i class="fa fa-check-circle me-3" style="font-size: 28px; color: #28a745;"></i>
            <div class="flex-grow-1">
                <h5 class="mb-1 fw-bold">🎉 Listing Sold!</h5>
                @if($acceptedCounterBid)
                    <p class="mb-1">
                        <strong>Accepted Counter Offer from:</strong>
                        {{ $acceptedCounterBid->user->first_name ?? '' }} {{ $acceptedCounterBid->user->last_name ?? '' }}
                    </p>
                    <small class="text-muted">
                        <i class="fa fa-calendar-check"></i>
                        Accepted on {{ \Carbon\Carbon::parse($acceptedCounterBid->accepted_date)->format('M j, Y g:i A') }}
                    </small>
                @elseif($acceptedBid)
                    <p class="mb-1">
                        <strong>Purchased by:</strong>
                        {{ $acceptedBid->user->first_name ?? '' }} {{ $acceptedBid->user->last_name ?? '' }}
                    </p>
                    <small class="text-muted">
                        <i class="fa fa-calendar-check"></i>
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
    $isSold = $auction->is_sold;

    // ⏱ Calculate remaining time if not expired
    if ($expiration && !$isExpired && !$isSold) {
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


        {{-- ⏳ Countdown Timer --}}
        @if (!$isSold)
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
        @else
            <div class="alert alert-success text-center mt-2 mb-0 p-2">
                <strong><i class="fa fa-check-circle"></i> Bidding Closed - Listing Sold</strong>
            </div>
        @endif

        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp


        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
            @if (!$isExpired && !$isSold)
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
                    onclick="window.location='{{ route('agent.landlord.agent.auction.bid', @$auction->id) }}';">
                    <span class="bid">Bid Now</span>
                    <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
                </button>
                @endif
            @elseif($isSold)
                <div class="alert alert-success text-center mb-2">
                    <i class="fa fa-trophy"></i> <strong>This listing has been sold</strong>
                </div>
                <button class="btn w-100 btn-success" disabled>
                    <span class="bid">Sold</span>
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
                @else
                <p>No one has bid on this auction.</p>
                @endif
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        @php
                        $rawState = data_get($bid, 'accepted', '0');
                        $state = in_array($rawState, [null, 0, '0'], true) ? '0' : (string) $rawState;
                        $isOwnerRow = data_get($auction, 'user_id') == $auth_id;
                        $hasAcceptedCounterBid = false;

                        // Get counter bids for this bid
                        $counterBids = \App\Models\LandlordCounterBidding::with('meta', 'user')
                            ->where('landlord_agent_auction_bid_id', data_get($bid, 'id'))
                            ->orderBy('created_at', 'desc')
                            ->get();

                        // Check if this bid has any accepted counter bid
                        $acceptedCounterBidForThisBid = $counterBids->where('accepted', 'accepted')->first();
                        $hasAcceptedCounterBid = $acceptedCounterBidForThisBid ? true : false;

                        // Check if this bid or its counter bid is accepted
                        $bidIsAccepted = $state === 'accepted' || $hasAcceptedCounterBid;
                        @endphp

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
                                    @if($bidIsAccepted)
                                        <span class="text-success">✓ Accepted</span>
                                    @else
                                        <span class="text-primary">Terms ↓</span>
                                    @endif
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

                                        <!-- Services Offered -->
                                        @php $servicesList = (array) data_get($bid,'get.services',[]); @endphp
                                        @if (!empty($servicesList))
                                        <div>
                                            <label style="font-size: large;"> Services Offered:</label>
                                            <ul class="services services-offered">
                                                @foreach ($servicesList as $service)
                                                @if ($service == 'Other')
                                                @continue
                                                @endif
                                                <li class="alert-font"
                                                    style="font-size: 16px; margin-top:15px;">
                                                    {{ $service }}
                                                </li>
                                                @endforeach
                                            </ul>
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
                                                        <div class="row">

                                                            <!-- About Agent -->
                                                            @if (data_get($bid, 'get.bio'))
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">About Agent:
                                                                </div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.bio') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if (data_get($bid, 'get.what_sets_you_apart'))
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">What Sets This Agent Apart:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.what_sets_you_apart') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                            <!-- Why Hire You -->
                                                            @if (data_get($bid, 'get.why_hire_you'))
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Why Hire This Agent:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.why_hire_you') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if (data_get($bid, 'get.marketing_plan'))
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Marketing Stategy:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.marketing_plan') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if (data_get($bid, 'get.year_licensed'))
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Licensed Since:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.year_licensed') }}
                                                                </div>
                                                            </div>
                                                            @endif


                                                            <!-- Reviews Links -->
                                                            @if (data_get($bid, 'get.reviews_links'))
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Reviews
                                                                    Links:</div>
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
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Website
                                                                    Link:</div>
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
                                                            <div class="mb-3 col-md-6">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Social
                                                                    Media:</div>
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

                                                    </div>

                                                    <!-- 2. Broker Compensation & Agency Agreement Terms -->
                                                    @if (data_get($bid, 'get.purchase_fee_type') ||
                                                    data_get($bid, 'get.interested_lease_option_agreement') ||
                                                    data_get($bid, 'get.interested_in_selling') ||
                                                    data_get($bid, 'get.broker_fee_timing') ||
                                                    data_get($bid, 'get.renewal_fee_type') ||
                                                    data_get($bid, 'get.expansion_commission_percentage') ||
                                                    data_get($bid, 'get.tenant_broker_commission_structure') ||
                                                    data_get($bid, 'get.protection_period') ||
                                                    data_get($bid, 'get.early_termination_fee_option') ||
                                                    data_get($bid, 'get.agency_agreement_timeframe') ||
                                                    data_get($bid, 'get.interested_in_property_management') ||
                                                    data_get($bid, 'get.brokerage_relationship') ||
                                                    data_get($bid, 'get.additional_details_broker'))
                                                    <div class="mb-5">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms:
                                                        </h6>

                                                        <!-- Landlord's Broker Lease Fee -->
                                                        @if (data_get($bid, 'get.purchase_fee_type'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Landlord's Broker Lease Fee:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_type') }}</div>
                                                        </div>

                                                        <!-- Residential Property Lease Fee Details -->
                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'Flat Fee' && data_get($bid, 'get.purchase_fee_flat'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Flat Fee Amount:</div>
                                                            <div class="text-muted">${{ data_get($bid, 'get.purchase_fee_flat') }}</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Rent Due Each Rental Period' && data_get($bid, 'get.purchase_fee_rental_period'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Rent Due Each Rental Period:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_rental_period') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.purchase_fee_percentage_combo'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Gross Lease Value:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_percentage_combo') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if ($canon(data_get($bid, 'get.purchase_fee_type') ?? '') === 'Percentage of the First Month\'s Rent' && data_get($bid, 'get.purchase_fee_flat_combo'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of First Month's Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_flat_combo') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'other' && data_get($bid, 'get.purchase_fee_other'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Other Lease Fee Structure:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_other') }}</div>
                                                        </div>
                                                        @endif

                                                        <!-- Commercial Property Lease Fee Details -->
                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Net Aggregate Rent' && data_get($bid, 'get.purchase_fee_net_aggregate'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Net Aggregate Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_net_aggregate') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Gross Rent' && data_get($bid, 'get.purchase_fee_gross_rent'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Gross Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_gross_rent') }}%</div>
                                                        </div>
                                                        @if (data_get($bid, 'get.sales_tax_option_gross'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Sales Tax:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.sales_tax_option_gross') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if ($canon(data_get($bid, 'get.purchase_fee_type') ?? '') === 'Percentage of Month\'s Rent' && data_get($bid, 'get.purchase_fee_monthly_percentage'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Month's Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_monthly_percentage') }}%</div>
                                                        </div>
                                                        @if (data_get($bid, 'get.purchase_fee_months'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Number of Months:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_months') }} months</div>
                                                        </div>
                                                        @endif
                                                        @if (data_get($bid, 'get.sales_tax_option_monthly'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Sales Tax:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.sales_tax_option_monthly') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'Flat Fee' && data_get($bid, 'get.purchase_fee_flat_commercial'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Flat Fee Amount:</div>
                                                            <div class="text-muted">${{ data_get($bid, 'get.purchase_fee_flat_commercial')}}</div>
                                                        </div>
                                                        @if (data_get($bid, 'get.sales_tax_option_flat'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Sales Tax:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.sales_tax_option_flat') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (data_get($bid, 'get.purchase_fee_type') === 'other' && data_get($bid, 'get.purchase_fee_other_commercial'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Other Lease Fee Structure:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.purchase_fee_other_commercial') }}</div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Lease Option Agreement -->
                                                        @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                                                        <div class="mt-4 pt-3 border-top">
                                                            <h6 class="mb-3" style="color: #049399; font-weight: 600;">Lease-Option Agreement Details:</h6>

                                                            @if (data_get($bid, 'get.lease_value'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Lease Option Compensation:</div>
                                                                <div class="text-muted">
                                                                    @if (data_get($bid, 'get.lease_type') === 'percent')
                                                                    {{ data_get($bid, 'get.lease_value') }}% of option consideration
                                                                    @else
                                                                    ${{ data_get($bid, 'get.lease_value')}} flat fee
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.purchase_value'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Purchase Option Compensation:</div>
                                                                <div class="text-muted">
                                                                    @if (data_get($bid, 'get.purchase_type') === 'percent')
                                                                    {{ data_get($bid, 'get.purchase_value') }}% of total purchase price
                                                                    @else
                                                                    ${{ data_get($bid, 'get.purchase_value') }} flat fee
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Interested in Selling Section -->
                                                        @if (data_get($bid, 'get.interested_in_selling') === 'Yes')
                                                        <div class="mt-4 pt-3 border-top">
                                                            <h6 class="mb-3" style="color: #049399; font-weight: 600;">Purchase Fee Details:</h6>

                                                            @if (data_get($bid, 'get.interested_in_selling_type'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Purchase Fee Type:</div>
                                                                <div class="text-muted">{{ data_get($bid, 'get.interested_in_selling_type') }}</div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.interested_in_selling_type') === 'Percentage of the Total Purchase Price' && data_get($bid, 'get.landlord_broker_purchase_price'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Purchase Percentage:</div>
                                                                <div class="text-muted">{{ data_get($bid, 'get.landlord_broker_purchase_price') }}%</div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.interested_in_selling_type') === 'Percentage of the Total Purchase Price + Flat Fee')
                                                            @if (data_get($bid, 'get.landlord_broker_percentage_price'))
                                                            <div class="mb-2">
                                                                <div class="fw-semibold" style="color: #049399;">Purchase Percentage:</div>
                                                                <div class="text-muted">{{ data_get($bid, 'get.landlord_broker_percentage_price') }}%</div>
                                                            </div>
                                                            @endif
                                                            @if (data_get($bid, 'get.landlord_broker_dollar_price'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Additional Flat Fee:</div>
                                                                <div class="text-muted">${{ data_get($bid, 'get.landlord_broker_dollar_price') }}</div>
                                                            </div>
                                                            @endif
                                                            @endif

                                                            @if (data_get($bid, 'get.interested_in_selling_type') === 'Flat Fee' && data_get($bid, 'get.landlord_broker_flate_fee'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Purchase Flat Fee:</div>
                                                                <div class="text-muted">${{ data_get($bid, 'get.landlord_broker_flate_fee') }}</div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.interested_in_selling_type') === 'Other' && data_get($bid, 'get.landlord_broker_other'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold" style="color: #049399;">Other Purchase Fee Structure:</div>
                                                                <div class="text-muted">{{ data_get($bid, 'get.landlord_broker_other') }}</div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Payment Timing for Broker Fees -->
                                                        @if (data_get($bid, 'get.broker_fee_timing'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Payment Timing for Broker Fees:</div>
                                                            <div class="text-muted">
                                                                @if (data_get($bid, 'get.broker_fee_timing') === 'full_execution')
                                                                Full amount upon execution of lease, sales contract, or other transfer agreement
                                                                @elseif (data_get($bid, 'get.broker_fee_timing') === '50% due upon execution, 50% due upon commencement of agreement')
                                                                50% due upon execution, 50% due upon commencement of agreement
                                                                @elseif (data_get($bid, 'get.broker_fee_timing') === '50% due upon execution, 50% due upon occupancy of premises')
                                                                50% due upon execution, 50% due upon occupancy of premises
                                                                @else
                                                                {{ data_get($bid, 'get.broker_fee_timing') }}
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <!-- Residential Payment Timing Details -->
                                                        @if (data_get($bid, 'get.broker_fee_timing') === 'Deducted from Rent Collected' && data_get($bid, 'get.broker_fee_days_from_rent'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Calendar Days to Pay Balance:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.broker_fee_days_from_rent') }} days</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.broker_fee_timing') === 'Paid Within Calendar Days After Executed Lease' && data_get($bid, 'get.broker_fee_days_after_lease'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Calendar Days to Pay After Executed Lease:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.broker_fee_days_after_lease') }} days</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.broker_fee_timing') === 'Paid Within Calendar Days of Tenant Rent Payment' && data_get($bid, 'get.broker_fee_days_after_rent'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Calendar Days to Pay After Tenant Rent Payment:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.broker_fee_days_after_rent') }} days</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.broker_fee_timing') === 'other' && data_get($bid, 'get.broker_fee_timing_other'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Custom Payment Arrangement:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.broker_fee_timing_other') }}</div>
                                                        </div>
                                                        @endif

                                                        @if (in_array(data_get($bid, 'get.broker_fee_timing'), ['50% due upon execution, 50% due upon commencement of agreement', '50% due upon execution, 50% due upon occupancy of premises']) && data_get($bid, 'get.broker_fee_days_after_due_event'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Calendar Days to Pay Second Installment:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.broker_fee_days_after_due_event') }} days</div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Lease Renewal/Extension Fee -->
                                                        @if (data_get($bid, 'get.renewal_fee_type'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Lease Renewal/Extension Fee:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_type') }}</div>
                                                        </div>

                                                        <!-- Residential Renewal Fees -->
                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Rent Due Each Rental Period' && data_get($bid, 'get.renewal_fee_percentage'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Rent Due Each Rental Period:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_percentage') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.renewal_fee_lease_value'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Gross Lease Value:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_lease_value') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if ($canon(data_get($bid, 'get.renewal_fee_type') ?? '') === 'Percentage of the First Month\'s Rent' && data_get($bid, 'get.renewal_fee_first_month'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of First Month's Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_first_month') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'Flat Fee' && data_get($bid, 'get.renewal_fee_flat_free'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Flat Fee Amount:</div>
                                                            <div class="text-muted">${{data_get($bid, 'get.renewal_fee_flat_free') }}</div>
                                                        </div>
                                                        @endif

                                                        <!-- Commercial Renewal Fees -->
                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Net Aggregate Rent' && data_get($bid, 'get.renewal_fee_percentage'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Net Aggregate Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_percentage') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Gross Rent' && data_get($bid, 'get.renewal_fee_lease_value'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Gross Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_lease_value') }}%</div>
                                                        </div>
                                                        @if (data_get($bid, 'get.renewal_fee_sales_tax_lease_value'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Sales Tax:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.renewal_fee_sales_tax_lease_value') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if ($canon(data_get($bid, 'get.renewal_fee_type') ?? '') === 'Percentage of Month\'s Rent' && data_get($bid, 'get.renewal_fee_first_month'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Month's Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_first_month') }}%</div>
                                                        </div>
                                                        @if (data_get($bid, 'get.renewal_fee_no_of_months'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Number of Months:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_no_of_months') }} months</div>
                                                        </div>
                                                        @endif
                                                        @if (data_get($bid, 'get.renewal_fee_sales_tax_first_month'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Sales Tax:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.renewal_fee_sales_tax_first_month') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'Flat Fee' && data_get($bid, 'get.renewal_fee_flat_free'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Flat Fee Amount:</div>
                                                            <div class="text-muted">${{ data_get($bid, 'get.renewal_fee_flat_free') }}</div>
                                                        </div>
                                                        @if (data_get($bid, 'get.renewal_fee_sales_tax_flat_fee'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Sales Tax:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.renewal_fee_sales_tax_flat_fee') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (data_get($bid, 'get.renewal_fee_type') === 'other' && data_get($bid, 'get.renewal_fee_custom'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Custom Renewal Fee Structure:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.renewal_fee_custom') }}</div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Expansion Commission for Lease Amendment (Commercial only) -->
                                                        @if (data_get($bid, 'get.expansion_commission_percentage'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Expansion Commission for Lease Amendment:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.expansion_commission_percentage') }}% of original commission</div>
                                                        </div>
                                                        @endif

                                                        <!-- Tenant's Broker Commission Structure (Residential only) -->
                                                        @if (data_get($bid, 'get.tenant_broker_commission_structure'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Tenant's Broker Commission Fee:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.tenant_broker_commission_structure') }}</div>
                                                        </div>

                                                        @if (data_get($bid, 'get.tenant_broker_fee_structure'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Tenant's Broker Commission Fee Structure:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.tenant_broker_fee_structure') }}</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.tenant_broker_percentage'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Rent Due Each Rental Period:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.tenant_broker_percentage') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.tenant_broker_gross_lease'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Gross Lease Value:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.tenant_broker_gross_lease') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.tenant_broker_first_month_rent'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of First Month's Rent:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.tenant_broker_first_month_rent') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.tenant_broker_flat_fee'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Flat Fee Amount:</div>
                                                            <div class="text-muted">${{ data_get($bid, 'get.tenant_broker_flat_fee') }}</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.tenant_broker_other'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Other Commission Arrangement:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.tenant_broker_other') }}</div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Protection Period -->
                                                        @if (data_get($bid, 'get.protection_period'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Protection Period Timeframe:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.protection_period') }} days</div>
                                                        </div>
                                                        @endif

                                                        <!-- Early Termination Fee -->
                                                        @if (data_get($bid, 'get.early_termination_fee_option'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Early Termination Fee:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.early_termination_fee_option') === 'yes' ? 'Yes' : 'No' }}</div>
                                                        </div>

                                                        @if (data_get($bid, 'get.early_termination_fee_option') === 'yes' && data_get($bid, 'get.early_termination_fee_amount'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Termination Fee Amount:</div>
                                                            <div class="text-muted">${{data_get($bid, 'get.early_termination_fee_amount') }}</div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Agency Agreement Timeframe -->
                                                        @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Landlord Agency Agreement Timeframe:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.agency_agreement_timeframe') }}</div>
                                                        </div>

                                                        @if (data_get($bid, 'get.agency_agreement_timeframe') === 'Other' && data_get($bid, 'get.agency_agreement_custom'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Custom Timeframe:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.agency_agreement_custom') }}</div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Property Management -->
                                                        @if (data_get($bid, 'get.interested_in_property_management'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Interested in Property Management:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.interested_in_property_management') === 'yes' ? 'Yes' : 'No' }}</div>
                                                        </div>

                                                        @if (data_get($bid, 'get.interested_in_property_management') === 'yes' && data_get($bid, 'get.interested_in_property_management_fee'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Property Management Fee Type:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.interested_in_property_management_fee') }}</div>
                                                        </div>

                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.interested_in_property_management_fee_gross_lease'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Gross Lease Value:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.interested_in_property_management_fee_gross_lease') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Percentage of the Rent Due Each Rental Period' && data_get($bid, 'get.interested_in_property_management_fee_rental_periord'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Percentage of Rent Due Each Rental Period:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.interested_in_property_management_fee_rental_periord') }}%</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Flat Fee' && data_get($bid, 'get.interested_in_property_management_fee_flate_free'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Flat Fee Amount:</div>
                                                            <div class="text-muted">${{ data_get($bid, 'get.interested_in_property_management_fee_flate_free') }}</div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Other' && data_get($bid, 'get.interested_in_property_management_fee_other'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold" style="color: #049399;">Other Property Management Fee:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.interested_in_property_management_fee_other') }}</div>
                                                        @endif
                                                        @endif
                                                        @endif

                                                        <!-- Brokerage Relationship -->
                                                        @if (data_get($bid, 'get.brokerage_relationship'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Acceptable Brokerage Relationship:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.brokerage_relationship') }}</div>
                                                        </div>
                                                        @endif

                                                        <!-- Additional Terms -->
                                                        @if (data_get($bid, 'get.additional_details_broker'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold" style="color: #049399;">Additional Terms:</div>
                                                            <div class="text-muted">{{ data_get($bid, 'get.additional_details_broker') }}</div>
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
                                                            Terms & Details:
                                                        </h6>

                                                        <!-- Additional Terms -->
                                                        @if (data_get($bid, 'get.additional_details_broker'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Additional Terms:</div>
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
                                                                Additional Details:</div>
                                                            <div class="text-muted"
                                                                style="font-style: italic;">
                                                                {{ data_get($bid, 'get.additional_details') }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif




                                                    <!-- Services Offered -->
                                                    @php
                                                    $biding_services = is_string(data_get($bid, 'get.services'))
                                                    ? json_decode(data_get($bid, 'get.services'), true)
                                                    : data_get($bid, 'get.services');
                                                    @endphp

                                                    @if (!empty($biding_services) && is_array($biding_services))
                                                    <div class="mb-4">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-list-alt me-2"></i>Services Offered
                                                        </h6>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @foreach ($biding_services as $biding_service)
                                                            @if ($biding_service == 'Other')
                                                            @continue
                                                            @endif
                                                            <span class="badge bg-light text-dark border" style="padding: 8px 12px; font-size: 14px; max-width: 200px; word-wrap: break-word; white-space: normal; line-height: 1.4;">
                                                                {{ $biding_service }}
                                                            </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    @endif

                                                    <!-- Other Services -->
                                                    @php
                                                    $biding_other_services = is_string(data_get($bid, 'get.other_services'))
                                                    ? json_decode(data_get($bid, 'get.other_services'), true)
                                                    : data_get($bid, 'get.other_services');
                                                    @endphp

                                                    @if (!empty($biding_other_services) && is_array($biding_other_services))
                                                    <div class="mb-4">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-plus-circle me-2"></i>Other Services
                                                        </h6>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @foreach ($biding_other_services as $biding_other_service)
                                                            @if ($biding_other_service == 'Other')
                                                            @continue
                                                            @endif
                                                            <span class="badge bg-light text-dark border" style="padding: 8px 12px; font-size: 14px; max-width: 200px; word-wrap: break-word; white-space: normal; line-height: 1.4;">
                                                                {{ $biding_other_service }}
                                                            </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    @endif
                                                    <br />

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
                                                            Presentation & Promotional Materials:
                                                        </h6>

                                                        <!-- Virtual Presentation Section -->
                                                        @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                                        <div class="mb-4">
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">Virtual
                                                                Agent Presentation:</div>

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
                                                                Business Card:</div>

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
                                                                            File:
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
                                                                Marketing Materials:</div>

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
                                                            Information:
                                                        </h6>

                                                        <div class="row">
                                                            <!-- First Name -->
                                                            @if (data_get($bid, 'get.first_name'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">First
                                                                    Name:</div>
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
                                                                    Name:</div>
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
                                                                    Number:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.phone') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Email -->
                                                            @if (data_get($bid, 'get.email'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Email:
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
                                                                    Brokerage:</div>
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
                                                                    Estate License #:</div>
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
                                                                    Member ID:</div>
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
                                    $counterBids = \App\Models\LandlordCounterBidding::with(
                                    'meta',
                                    'user',
                                    )
                                    ->where('landlord_agent_auction_bid_id', data_get($bid, 'id'))
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

                                    // Check if any counter bid is accepted for this main bid
                                    $hasAcceptedCounterBid = $counterBids->contains('accepted', 'accepted');
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

                                                    @php $allMeta = $counterBid->getAllMeta(); @endphp
                                                    @if (!empty($allMeta['purchase_fee_type']) || !empty($allMeta['interested_lease_option_agreement']) || !empty($allMeta['interested_in_selling']) || !empty($allMeta['broker_fee_timing']) || !empty($allMeta['renewal_fee_type']) || !empty($allMeta['protection_period']) || !empty($allMeta['early_termination_fee_option']) || !empty($allMeta['agency_agreement_timeframe']) || !empty($allMeta['interested_in_property_management']) || !empty($allMeta['brokerage_relationship']))
                                                    <div class="mb-4">
                                                        <h6 class="mb-3" style="font-weight: 600; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
                                                            Broker Compensation & Agency Agreement Terms
                                                        </h6>

                                                        <!-- Landlord's Broker Lease Fee -->
                                                        @if (!empty($allMeta['purchase_fee_type']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Landlord's Broker Lease Fee Type:</span> {{ $allMeta['purchase_fee_type'] }}</div>
                                                        @endif

                                                        <!-- Residential Property Lease Fee Amounts -->
                                                        @if (!empty($allMeta['purchase_fee_flat']) && $allMeta['purchase_fee_type'] === 'Flat Fee')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Flat Fee Amount:</span> ${{ $allMeta['purchase_fee_flat'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_rental_period']) && $allMeta['purchase_fee_type'] === 'Percentage of the Rent Due Each Rental Period')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Rent Due Each Rental Period:</span> {{ $allMeta['purchase_fee_rental_period'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_percentage_combo']) && $allMeta['purchase_fee_type'] === 'Percentage of the Gross Lease Value')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Gross Lease Value:</span> {{ $allMeta['purchase_fee_percentage_combo'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_flat_combo']) && $canon($allMeta['purchase_fee_type'] ?? '') === 'Percentage of the First Month\'s Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of First Month's Rent:</span> {{ $allMeta['purchase_fee_flat_combo'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_other']) && $allMeta['purchase_fee_type'] === 'other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Other Lease Fee Structure:</span> {{ $allMeta['purchase_fee_other'] }}</div>
                                                        @endif

                                                        <!-- Commercial Property Lease Fee Amounts -->
                                                        @if (!empty($allMeta['purchase_fee_net_aggregate']) && $allMeta['purchase_fee_type'] === 'Percentage of the Net Aggregate Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Net Aggregate Rent:</span> {{ $allMeta['purchase_fee_net_aggregate'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_gross_rent']) && $allMeta['purchase_fee_type'] === 'Percentage of the Gross Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Gross Rent:</span> {{ $allMeta['purchase_fee_gross_rent'] }}%</div>
                                                        @if (!empty($allMeta['sales_tax_option_gross']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Sales Tax:</span> {{ $allMeta['sales_tax_option_gross'] === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}</div>
                                                        @endif
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_monthly_percentage']) && $canon($allMeta['purchase_fee_type'] ?? '') === 'Percentage of Month\'s Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Month's Rent:</span> {{ $allMeta['purchase_fee_monthly_percentage'] }}%</div>
                                                        @if (!empty($allMeta['purchase_fee_months']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Number of Months:</span> {{ $allMeta['purchase_fee_months'] }} months</div>
                                                        @endif
                                                        @if (!empty($allMeta['sales_tax_option_monthly']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Sales Tax:</span> {{ $allMeta['sales_tax_option_monthly'] === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}</div>
                                                        @endif
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_flat_commercial']) && $allMeta['purchase_fee_type'] === 'Flat Fee')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Flat Fee Amount:</span> ${{ $allMeta['purchase_fee_flat_commercial']}}</div>
                                                        @if (!empty($allMeta['sales_tax_option_flat']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Sales Tax:</span> {{ $allMeta['sales_tax_option_flat'] === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}</div>
                                                        @endif
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_other_commercial']) && $allMeta['purchase_fee_type'] === 'other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Other Lease Fee Structure:</span> {{ $allMeta['purchase_fee_other_commercial'] }}</div>
                                                        @endif

                                                        <!-- Lease Option Agreement -->
                                                        @if (!empty($allMeta['interested_lease_option_agreement']) && $allMeta['interested_lease_option_agreement'] === 'Yes')
                                                        <div class="mt-3 pt-3 border-top">
                                                            <h6 class="mb-2" style="font-size: 14px; font-weight: 600;">Lease-Option Agreement Details</h6>

                                                            @if (!empty($allMeta['lease_value']))
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Lease Option Compensation:</span>
                                                                @if (!empty($allMeta['lease_type']) && $allMeta['lease_type'] === 'percent')
                                                                {{ $allMeta['lease_value'] }}%
                                                                @else
                                                                ${{ $allMeta['lease_value'] }}
                                                                @endif
                                                            </div>
                                                            @endif

                                                            @if (!empty($allMeta['purchase_value']))
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Purchase Option Compensation:</span>
                                                                @if (!empty($allMeta['purchase_type']) && $allMeta['purchase_type'] === 'percent')
                                                                {{ $allMeta['purchase_value'] }}%
                                                                @else
                                                                ${{$allMeta['purchase_value'] }}
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Interested in Selling -->
                                                        @if (!empty($allMeta['interested_in_selling']) && $allMeta['interested_in_selling'] === 'Yes')
                                                        <div class="mt-3 pt-3 border-top">
                                                            <h6 class="mb-2" style="font-size: 14px; font-weight: 600;">Purchase Fee Details</h6>

                                                            @if (!empty($allMeta['interested_in_selling_type']))
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Purchase Fee Type:</span> {{ $allMeta['interested_in_selling_type'] }}</div>
                                                            @endif

                                                            @if (!empty($allMeta['landlord_broker_purchase_price']) && $allMeta['interested_in_selling_type'] === 'Percentage of the Total Purchase Price')
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Purchase Percentage:</span> {{ $allMeta['landlord_broker_purchase_price'] }}%</div>
                                                            @endif

                                                            @if ($allMeta['interested_in_selling_type'] === 'Percentage of the Total Purchase Price + Flat Fee')
                                                            @if (!empty($allMeta['landlord_broker_percentage_price']))
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Purchase Percentage:</span> {{ $allMeta['landlord_broker_percentage_price'] }}%</div>
                                                            @endif
                                                            @if (!empty($allMeta['landlord_broker_dollar_price']))
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Purchase Flat Fee:</span> ${{ $allMeta['landlord_broker_dollar_price'] }}</div>
                                                            @endif
                                                            @endif

                                                            @if (!empty($allMeta['landlord_broker_flate_fee']) && $allMeta['interested_in_selling_type'] === 'Flat Fee')
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Purchase Flat Fee:</span> ${{ $allMeta['landlord_broker_flate_fee'] }}</div>
                                                            @endif

                                                            @if (!empty($allMeta['landlord_broker_other']) && $allMeta['interested_in_selling_type'] === 'Other')
                                                            <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Other Purchase Fee Structure:</span> {{ $allMeta['landlord_broker_other'] }}</div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Payment Timing for Broker Fees -->
                                                        @if (!empty($allMeta['broker_fee_timing']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Payment Timing for Broker Fees:</span> {{ $allMeta['broker_fee_timing'] }}</div>

                                                        @if (!empty($allMeta['broker_fee_days_from_rent']) && $allMeta['broker_fee_timing'] === 'Deducted from Rent Collected')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Calendar Days to Pay Balance:</span> {{ $allMeta['broker_fee_days_from_rent'] }} days</div>
                                                        @endif

                                                        @if (!empty($allMeta['broker_fee_days_after_lease']) && $allMeta['broker_fee_timing'] === 'Paid Within Calendar Days After Executed Lease')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Calendar Days to Pay After Executed Lease:</span> {{ $allMeta['broker_fee_days_after_lease'] }} days</div>
                                                        @endif

                                                        @if (!empty($allMeta['broker_fee_days_after_rent']) && $allMeta['broker_fee_timing'] === 'Paid Within Calendar Days of Tenant Rent Payment')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Calendar Days to Pay After Tenant Rent Payment:</span> {{ $allMeta['broker_fee_days_after_rent'] }} days</div>
                                                        @endif

                                                        @if (!empty($allMeta['broker_fee_timing_other']) && $allMeta['broker_fee_timing'] === 'other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Custom Payment Arrangement:</span> {{ $allMeta['broker_fee_timing_other'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['broker_fee_days_after_due_event']) && ($allMeta['broker_fee_timing'] === '50% due upon execution, 50% due upon commencement of agreement' || $allMeta['broker_fee_timing'] === '50% due upon execution, 50% due upon occupancy of premises'))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Calendar Days to Pay Second Installment:</span> {{ $allMeta['broker_fee_days_after_due_event'] }} days</div>
                                                        @endif

                                                        @if (!empty($allMeta['split_payment_due']) && $allMeta['broker_fee_timing'] === 'split_payment')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Remaining 50% Due Upon:</span> {{ $allMeta['split_payment_due'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['split_payment_due_other']) && $allMeta['split_payment_due'] === 'Other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Custom Payment Due:</span> {{ $allMeta['split_payment_due_other'] }}</div>
                                                        @endif
                                                        @endif

                                                        <!-- Lease Renewal/Extension Fee -->
                                                        @if (!empty($allMeta['renewal_fee_type']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Lease Renewal/Extension Fee:</span> {{ $allMeta['renewal_fee_type'] }}</div>

                                                        @if (!empty($allMeta['renewal_fee_percentage']) && $allMeta['renewal_fee_type'] === 'Percentage of the Rent Due Each Rental Period')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Rent Due Each Rental Period:</span> {{ $allMeta['renewal_fee_percentage'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_lease_value']) && $allMeta['renewal_fee_type'] === 'Percentage of the Gross Lease Value')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Gross Lease Value:</span> {{ $allMeta['renewal_fee_lease_value'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_first_month']) && $canon($allMeta['renewal_fee_type'] ?? '') === 'Percentage of the First Month\'s Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of First Month's Rent:</span> {{ $allMeta['renewal_fee_first_month'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_flat_free']) && $allMeta['renewal_fee_type'] === 'Flat Fee')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Flat Fee Amount:</span> ${{ $allMeta['renewal_fee_flat_free'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_sales_tax_lease_value']) && $allMeta['renewal_fee_type'] === 'Percentage of the Gross Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Sales Tax:</span> {{ $allMeta['renewal_fee_sales_tax_lease_value'] === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_no_of_months']) && $canon($allMeta['renewal_fee_type'] ?? '') === 'Percentage of Month\'s Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Number of Months:</span> {{ $allMeta['renewal_fee_no_of_months'] }} months</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_sales_tax_first_month']) && $canon($allMeta['renewal_fee_type'] ?? '') === 'Percentage of Month\'s Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Sales Tax:</span> {{ $allMeta['renewal_fee_sales_tax_first_month'] === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_sales_tax_flat_fee']) && $allMeta['renewal_fee_type'] === 'Flat Fee')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Sales Tax:</span> {{ $allMeta['renewal_fee_sales_tax_flat_fee'] === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['renewal_fee_custom']) && $allMeta['renewal_fee_type'] === 'other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Custom Renewal Fee Structure:</span> {{ $allMeta['renewal_fee_custom'] }}</div>
                                                        @endif
                                                        @endif

                                                        <!-- Expansion Commission for Lease Amendment -->
                                                        @if (!empty($allMeta['expansion_commission_percentage']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Expansion Commission for Lease Amendment:</span> {{ $allMeta['expansion_commission_percentage'] }}% of original commission</div>
                                                        @endif

                                                        <!-- Tenant's Broker Commission Structure -->
                                                        @if (!empty($allMeta['tenant_broker_commission_structure']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Tenant's Broker Commission Fee:</span> {{ $allMeta['tenant_broker_commission_structure'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['tenant_broker_fee_structure']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Tenant's Broker Commission Fee Structure:</span> {{ $allMeta['tenant_broker_fee_structure'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['tenant_broker_percentage']) && $allMeta['tenant_broker_fee_structure'] === 'Percentage of the Rent Due Each Rental Period')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Rent Due Each Rental Period:</span> {{ $allMeta['tenant_broker_percentage'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['tenant_broker_gross_lease']) && $allMeta['tenant_broker_fee_structure'] === 'Percentage of the Gross Lease Value')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Gross Lease Value:</span> {{ $allMeta['tenant_broker_gross_lease'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['tenant_broker_first_month_rent']) && $canon($allMeta['tenant_broker_fee_structure'] ?? '') === 'Percentage of the First Month\'s Rent')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of First Month's Rent:</span> {{ $allMeta['tenant_broker_first_month_rent'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['tenant_broker_flat_fee']) && $allMeta['tenant_broker_fee_structure'] === 'Flat fee')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Flat Fee Amount:</span> ${{ ($allMeta['tenant_broker_flat_fee']) }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['tenant_broker_other']) && $allMeta['tenant_broker_fee_structure'] === 'Other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Other Commission Arrangement:</span> {{ $allMeta['tenant_broker_other'] }}</div>
                                                        @endif

                                                        <!-- Protection Period -->
                                                        @if (!empty($allMeta['protection_period']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Protection Period:</span> {{ $allMeta['protection_period'] }} days</div>
                                                        @endif

                                                        <!-- Early Termination Fee -->
                                                        @if (!empty($allMeta['early_termination_fee_option']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Early Termination Fee:</span> {{ $allMeta['early_termination_fee_option'] === 'yes' ? 'Yes' : 'No' }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['early_termination_fee_amount']) && $allMeta['early_termination_fee_option'] === 'yes')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Termination Fee Amount:</span> ${{($allMeta['early_termination_fee_amount']) }}</div>
                                                        @endif

                                                        <!-- Agency Agreement Timeframe -->
                                                        @if (!empty($allMeta['agency_agreement_timeframe']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Agency Agreement Timeframe:</span> {{ $allMeta['agency_agreement_timeframe'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['agency_agreement_custom']) && $allMeta['agency_agreement_timeframe'] === 'Other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Custom Timeframe:</span> {{ $allMeta['agency_agreement_custom'] }}</div>
                                                        @endif

                                                        <!-- Property Management -->
                                                        @if (!empty($allMeta['interested_in_property_management']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Interested in Property Management:</span> {{ $allMeta['interested_in_property_management'] === 'yes' ? 'Yes' : 'No' }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['interested_in_property_management_fee']) && $allMeta['interested_in_property_management'] === 'yes')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Property Management Fee Type:</span> {{ $allMeta['interested_in_property_management_fee'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['interested_in_property_management_fee_gross_lease']) && $allMeta['interested_in_property_management_fee'] === 'Percentage of the Gross Lease Value')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Gross Lease Value:</span> {{ $allMeta['interested_in_property_management_fee_gross_lease'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['interested_in_property_management_fee_rental_periord']) && $allMeta['interested_in_property_management_fee'] === 'Percentage of the Rent Due Each Rental Period')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Percentage of Rent Due Each Rental Period:</span> {{ $allMeta['interested_in_property_management_fee_rental_periord'] }}%</div>
                                                        @endif

                                                        @if (!empty($allMeta['interested_in_property_management_fee_flate_free']) && $allMeta['interested_in_property_management_fee'] === 'Flat Fee')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Flat Fee Amount:</span> ${{ $allMeta['interested_in_property_management_fee_flate_free'] }}</div>
                                                        @endif

                                                        @if (!empty($allMeta['interested_in_property_management_fee_other']) && $allMeta['interested_in_property_management_fee'] === 'Other')
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Other Property Management Fee Structure:</span> {{ $allMeta['interested_in_property_management_fee_other'] }}</div>
                                                        @endif

                                                        <!-- Brokerage Relationship -->
                                                        @if (!empty($allMeta['brokerage_relationship']))
                                                        <div class="mb-1" style="font-size: 12px;"><span style="font-size: 13px; font-weight: 600;">Brokerage Relationship:</span> {{ $allMeta['brokerage_relationship'] }}</div>
                                                        @endif

                                                        <!-- Additional Terms -->
                                                        @if (!empty($allMeta['additional_details_broker']))
                                                        <div class="mt-3">
                                                            <div style="font-size: 13px; font-weight: 600; margin-bottom: 5px;">
                                                                Additional Terms:
                                                            </div>
                                                            <div style="font-size: 12px;">
                                                                {{ $allMeta['additional_details_broker'] }}
                                                            </div>
                                                        </div>
                                                        @endif
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
                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px; border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
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
                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px; border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
                                                                {{ $other_service }}
                                                            </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    @endif

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
                                                    <div
                                                        class="counter-response-buttons mt-3 pt-3 border-top">
                                                        <h6>Respond to this Counter Offer:</h6>
                                                        @if ($isExpired)
                                                        {{-- 🔹 Show expired message if auction expired --}}
                                                        <div class="alert alert-warning text-center mt-2 mb-0 p-2" style="font-size: 15px">
                                                            <strong>Bidding/Counter Period Ended</strong>
                                                        </div>
                                                        @else

                                                        <div
                                                            class="d-flex gap-3 flex-wrap justify-content-between">

                                                            <form class="d-inline"
                                                                action="{{ route('landlord.hire.agent.auction.counter.bid.accept') }}"
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
                                                                action="{{ route('landlord.hire.agent.auction.counter.bid.reject') }}"
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
                                                                action="{{ route('landlord.agent.add.counter-bid') }}"
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
                                                        @endif
                                                    </div>

                                                    @elseif($counterState === 'accepted')
                                                    <div class="alert alert-success mt-3 pt-3 border-top">
                                                        <i class="fa fa-check-circle"></i> This counter offer has been accepted
                                                    </div>
                                                    @elseif($counterState === 'rejected')
                                                    <div class="alert alert-danger mt-3 pt-3 border-top">
                                                        <i class="fa fa-times-circle"></i> This counter offer has been rejected
                                                    </div>
                                                    @elseif($bidIsAccepted || $isSold)
                                                    <div class="alert alert-info mt-3 pt-3 border-top">
                                                        <i class="fa fa-info-circle"></i> This bid is no longer available for counter offers
                                                    </div>
                                                    @elseif($isExpired)
                                                    <div class="alert alert-warning mt-3 pt-3 border-top">
                                                        <i class="fa fa-clock"></i> Bidding period has ended
                                                    </div>
                                                    @else
                                                    <div class="alert alert-secondary mt-3 pt-3 border-top">
                                                        <i class="fa fa-clock"></i> Waiting for response
                                                    </div>
                                                    @endif

                                                    <!-- Counter footer status -->
                                                    <div class="mt-3 pt-3 border-top">
                                                        @if ($counterState === 'accepted')
                                                        <div class="alert alert-success mb-0 py-1 small">
                                                            ✅ This counter bid has been accepted.
                                                        </div>
                                                        @elseif ($counterState === 'rejected')
                                                        <div class="alert alert-danger mb-0 py-1 alert-font">
                                                            ❌ This counter bid has been rejected.
                                                        </div>
                                                        @elseif ($counterState === '0')
                                                        <div class="alert alert-secondary mb-0 py-1 small">
                                                            ⏳ Waiting for response...
                                                        </div>
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

                                                                    {{-- Main Bid Actions --}}
                                    @php
                                        $ownerFirst = data_get($auction, 'user.first_name', '');
                                        $ownerLast = data_get($auction, 'user.last_name', '');
                                        $agentFirst = data_get($bid, 'user.first_name', '');
                                        $agentLast = data_get($bid, 'user.last_name', '');
                                        $ownerId = data_get($auction, 'user_id');
                                    @endphp

                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                        @if ($state === '0' && $isOwnerRow && !$isSold && !$hasAcceptedCounterBid && !$isExpired)
                                            <div class="biding-btn">
                                                <form
                                                    action="{{ route('landlord.hire.agent.auction.bid.accept') }}"
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
                                                    action="{{ route('landlord.hire.agent.auction.bid.reject') }}"
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
                                                    action="{{ route('landlord.agent.add.counter-bid') }}"
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
                                        @elseif($state === 'accepted')
                                            @if (Auth::id() == $ownerId)
                                            <div class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                ✅ This bid has been accepted.
                                            </div>
                                            @else
                                            <div class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                ✅ {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted the bid.
                                            </div>
                                            @endif
                                        @elseif($state === 'rejected')
                                            @if (Auth::id() == $ownerId)
                                            <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                ❌ This bid has been rejected.
                                            </div>
                                            @else
                                            <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                ❌ {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected the bid.
                                            </div>
                                            @endif
                                        @elseif($hasAcceptedCounterBid)
                                            <div class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                ✅ A counter offer has been accepted for this bid.
                                            </div>
                                        @elseif($isSold)
                                            <div class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                🏆 Listing has been sold
                                            </div>
                                        @elseif($isExpired)
                                            <div class="alert alert-warning mt-2 w-100 mb-0 py-1 small">
                                                ⏳ Bidding period has ended
                                            </div>
                                        @elseif($state === '0')
                                            @if (data_get($bid, 'user_id') == Auth::id())
                                            <div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                                ⏳ Waiting for response from {{ trim($ownerFirst . ' ' . $ownerLast) }}...
                                            </div>
                                            @else
                                            <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                                ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
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
<!-- Recommmended Section  -->
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
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
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

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('saveSABid') }}" method="post" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                <div class="modal-body">
                    <div class="d-flex justify-content-between mb-2">
                        <div style="font-size:18px; font-weight:bold;">
                            ${{ $auction->min_price }}</div>
                        <div style="color: rgba(0,0,0,0.5);">Price Now</div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row form-group">
                                <div class="col-md-6">
                                    <label>Full Name: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" required
                                        value="{{ old('name') }}" />
                                    @if ($errors->has('name'))
                                    <div class="small error">{{ $errors->first('name') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <label>Phone Number: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="phone"
                                        value="{{ old('phone') }}" required />
                                    @if ($errors->has('phone'))
                                    <div class="small error">{{ $errors->first('phone') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-6">
                                    <label>Email: <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email"
                                        value="{{ old('email') }}" required />
                                    @if ($errors->has('email'))
                                    <div class="small error">{{ $errors->first('email') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <label>Brokerage: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="brokerage" id="brokerage"
                                        required value="{{ old('brokerage') }}">
                                    {{-- </div> --}}
                                    @if ($errors->has('brokerage'))
                                    <div class="small error">{{ $errors->first('brokerage') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6 d-none">
                                    <label>MLS ID: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="mls_id"
                                        value="{{ old('mls_id') }}" />
                                    @if ($errors->has('mls_id'))
                                    <div class="small error">{{ $errors->first('mls_id') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-6">
                                    <label>Real Estate License #: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="license_no" required
                                        value="{{ old('license_no') }}" />
                                    @if ($errors->has('license_no'))
                                    <div class="small error">{{ $errors->first('license_no') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <label>Commission Offered %: <span class="text-danger">*</span></label>
                                    <div class="d-flex align-items-baseline">
                                        <i class="fa fa-percent"></i>
                                        <input type="number" class="form-control border-start-0"
                                            name="price_percent" id="price_percent" min="0"
                                            max="100" required placeholder="0.00"
                                            value="{{ old('price_percent') }}">
                                    </div>
                                    @if ($errors->has('price_percent'))
                                    <div class="small error">{{ $errors->first('price_percent') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="row form-group d-none">
                                <div class="col-md-6">
                                    <label>County the agent is in:</label>
                                    <select class="form-control" name="county_id">
                                        @foreach ($counties as $county)
                                        <option value="{{ $county->id }}"
                                            {{ old('county_id') == $county->id ? 'selected' : '' }}>
                                            {{ $county->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Offering Price $: <span class="text-danger">*</span></label>
                                    <div class="d-flex align-items-baseline">
                                        <i class="fa fa-dollar"></i>
                                        <input type="number" class="form-control border-start-0" name="price"
                                            id="price" min="0" placeholder="0.00"
                                            value="{{ old('price') }}">
                                    </div>
                                    @if ($errors->has('price'))
                                    <div class="small error">{{ $errors->first('price') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-6">
                                    <label>Reviews link:</label>
                                    <input type="text" class="form-control" name="reviews_link"
                                        value="{{ old('reviews_link') }}" />
                                </div>
                                <div class="col-md-6">
                                    <label>Website link:</label>
                                    <input type="text" class="form-control" name="website_link"
                                        value="{{ old('website_link') }}" />
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-12 mb-2">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="text-center">Social Media Platforms</th>
                                            </tr>
                                            <tr>
                                                <th>Type</th>
                                                <th>Link</th>
                                            </tr>
                                        </thead>
                                        <tbody class="social-links">
                                            <tr>
                                                <td>
                                                    <select name="socialType[]" class="form-select">
                                                        <option value="Facebook">Facebook</option>
                                                        <option value="YouTube">YouTube</option>
                                                        <option value="LinkedIn">LinkedIn</option>
                                                        <option value="Twitter">Twitter</option>
                                                        <option value="Instagram">Instagram</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="social_link[]"
                                                        class="form-control">
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="2" class="text-right">
                                                    <a class="btn btn-primary add-row">Add New Row</a>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Any details agent wants to share with the seller (Why should the Seller pick
                                    you
                                    as their agent):</label>
                                <textarea class="form-control" name="additional_details">{{ old('additional_details') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label>Contract Listing Terms (Time amount for listing agreement):</label>
                                <textarea class="form-control" name="listing_terms">{{ old('listing_terms') }}</textarea>
                            </div>
                            <div class="form-group d-none">
                                <label>Why should the Seller pick you as their agent?</label>
                                <textarea class="form-control" name="why_seller_pick_me">{{ old('why_seller_pick_me') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label>Services offered to Seller: </label>
                                <textarea class="form-control" name="services_description">{{ old('services_description') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group d-none">
                                <div>
                                    <label>Video:</label>
                                    <div class="d-flex align-items-baseline">
                                        <input type="file" class="form-control" name="video">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-12 mb-2">
                                <label>Video URL: (Virtual Listing Presentation)</label>
                                <div class="d-flex align-items-baseline">
                                    <input type="text" class="form-control" name="video_url">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Audio:</label>
                                <div class="d-flex align-items-baseline">
                                    <input type="file" class="form-control" name="audio">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Note:</label>
                                <div class="d-flex align-items-baseline">
                                    <input type="file" class="form-control" name="note">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Comparative Market Analysis:</label>
                                <div class="d-flex align-items-baseline">
                                    <input type="file" class="form-control" name="market_analysis">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-secondary">Bid Now</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
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
            $('.time').html("<div class='w-100 text-center text-danger fw-bold'>Bidding Ended</div>");
            $('.bid-btn').fadeOut(300, function() {
                $(this).after(
                    "<div class='alert alert-warning text-center mt-2 mb-0 p-2'><strong>Bidding Ended</strong></div>"
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
@endpush
