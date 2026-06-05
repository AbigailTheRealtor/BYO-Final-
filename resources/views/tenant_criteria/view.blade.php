@extends('layouts.main')
@push('styles')
  <!-- //Listing Description css  -->
  <link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
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

    .removeBold {
      font-weight: normal;
    }

    #bidding_history_data p {
      flex-wrap: wrap;
    }

    /* Section headings — bold, high-contrast */
    .listingDescription .card-body h4 {
      color: #0f172a !important;
      font-weight: 700 !important;
      font-size: 1.15rem;
      margin-top: 0.75rem;
      margin-bottom: 0.35rem;
    }

    /* Field row separators — subtle ruled lines between rows (mt-2 rows only) */
    .listingDescription .card-body .col-md-12.col-12.mt-2,
    .listingDescription .card-body .col-md-12.mt-2 {
      border-bottom: 1px solid #e5e7eb;
      padding-top: 0.45rem;
      padding-bottom: 0.45rem;
    }

    /* Field labels — scoped to field-row column context only */
    .listingDescription .card-body .col-md-12 strong,
    .listingDescription .card-body .col-md-12 .fw-bold {
      color: #64748b;
      font-weight: 500;
    }

    /* Field values (text in mt-2 rows) — dark, semi-bold */
    .listingDescription .card-body .col-md-12.col-12.mt-2,
    .listingDescription .card-body .col-md-12.mt-2 {
      color: #111827;
      font-weight: 600;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    /* Icon color — keep teal accent on small check icons only */
    .listingDescription .card-body .fa-check-square,
    .listingDescription .card-body .fa-square-check {
      color: #049399;
    }

    /* Secondary action buttons (Message, View Profile) — white fill, visible border */
    .listingDescription .right button:not(.btn-success):not(.btn-danger):not(.btn-primary):not(.btn-secondary) {
      background: #ffffff !important;
      border: 1px solid #cbd5e1 !important;
      color: #334155 !important;
      font-weight: 600 !important;
    }
    .listingDescription .right button:not(.btn-success):not(.btn-danger):not(.btn-primary):not(.btn-secondary):hover {
      background: #f8fafc !important;
    }

    /* Primary CTA "Bid Now" / "Login for Bid" button — prominent */
    .listingDescription .rightCol > button.btn,
    .listingDescription .rightCol > a > button.btn {
      font-weight: 600 !important;
    }
  </style>
@endpush

@section('content')
  @php
    $auth_id = auth()->user() ? auth()->user()->id : 0;
    // NOTE: listing_ai_faq is intentionally excluded from this view.
    // It is an internal-only field used by the AI chatbot and must never
    // be rendered publicly on this page or any listing card/preview partial.
  @endphp
  <!-- Gallery Start Here  -->
  <div class="container listingDescription">
    <div class="row">
      <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center justify-content-left">
            @if ($auction->is_approved == 1)
              <span class="badge bg-primary me-2">Active</span>
            @endif
            @if ($auction->is_approved == 0)
              <span class="badge bg-warning me-2">Pending</span>
            @endif
            @if ($auction->is_sold == 1)
              <span class="badge bg-success">Leased</span>
            @endif
          </div>
          @if ($auction->user_id == auth()->user()->id)
            <div class="d-flex justify-content-end align-content-center">
              <a href="{{route('agent.tenant.criteria.auction.edit', $auction->id)}}" class="btn btn-success btn-sm px-3 mb-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing</a>
              {{-- <a href="javascript:void(0)" class="btn btn-success btn-sm px-3 mb-3"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Auction Status</a> --}}
            </div>
          @endif
        </div>
        @php
            $_tenantAiFaq = is_array($auction->listing_ai_faq) ? $auction->listing_ai_faq : (json_decode($auction->listing_ai_faq ?: '{}', true) ?? []);
            $_tenantIsOwner = auth()->check() && $auction->user_id == auth()->id();
        @endphp
        <x-listing-ai-knowledge-base
            listing-type="tenant"
            :listing-id="$auction->id"
            :is-owner="$_tenantIsOwner"
            :ai-faq="$_tenantAiFaq"
            :share-token="$auction->ai_share_token"
            :property-type="$auction->get->property_type ?? ''"
        />
        <!-- Description Box  -->
        <div class="card description">
          <div class="card-body">
            <div class="row">
              @if (isset($auction->get->video))
                <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                  <span class="removeBold">
                    <video src="{{ asset($auction->get->video) }}" style="width:100%;height:29vh;" controls autoplay></video>
                  </span>
                </div>
              @endif
              @if (isset($auction->get->photo))
                <div class="col-md-6 col-6 pt-2 fw-bold">Photo:
                  <span class="removeBold">
                    <img src="{{ asset($auction->get->photo) }}" style="width:100%;height:29vh;" />
                  </span>
                </div>
              @endif
            </div>
            <hr>
            @if (isset($auction->get->description_buyer_specific))
              <div class="row">
                <h4>Description:</h4>

                  <div class="col-md-12 col-12  mt-2">
                    {{ @$auction->get->description_buyer_specific }}
                  </div>
              </div>
              <hr>
            @endif
            <div class="row">
              <h4>Desired Price and Terms:</h4>
              @if (isset($auction->get->monthly_price))
                <div class="col-md-12 col-12 mt-2">
                  <i class="fa-regular fa-check-square"></i> <strong>Maximum Monthly Lease Price:</strong>
                  {{ @$auction->get->monthly_price }}
                </div>
              @endif
              @if (isset($auction->get->leaseLength) && is_array($auction->get->leaseLength))
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Offered Lease Length:</strong>
                  @foreach ($auction->get->leaseLength as $item)
                    @if ($item != 'Other')
                      {{$item . '.'}}
                    @else
                      {{$auction->get->leaseOther}}
                    @endif
                  @endforeach
                </div>
              @endif
            </div>
            <hr>
            <div class="row">
              <h4>Listing Information:</h4>
              @if (@$auction->get->cities != null)
                <div class="col-md-12 col-12  mt-2">
                  <i class="fa-regular fa-check-square"></i> <strong>Cities:</strong>
                  @foreach (@$auction->get->cities as $item)
                    <span class="bg-secondary text-white rounded d-inline-block px-2 my-1"> {{ $item }}</span>
                  @endforeach
                </div>
              @endif
              @if (isset($auction->get->counties))
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Counties:</strong>
                  @foreach (@$auction->get->counties as $item)
                    <span class="bg-secondary text-white rounded d-inline-block px-2 my-1"> {{ $item }}</span>
                  @endforeach
                </div>
              @endif
              @if (isset($auction->get->state))
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>State:</strong>
                  @foreach (@$auction->get->state as $item)
                    <span class="bg-secondary text-white rounded d-inline-block px-2 my-1"> {{ $item }}</span>
                  @endforeach
                </div>
              @endif
                @if (isset($auction->get->listing_date))
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Listing Date:</strong>
                    {{ @$auction->get->listing_date }}
                  </div>
                @endif
                @if (isset($auction->get->expiration_date))
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Expiration Date:</strong>
                    {{ @$auction->get->expiration_date }}
                  </div>
                @endif
                @if (isset($auction->get->listingType))
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Listing Service Type: </strong>
                    {{ @$auction->get->listingType }}
                  </div>
                @endif
                @if (isset($auction->get->representation))
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Representation: </strong>
                    {{ @$auction->get->representation }}
                  </div>
                @endif
            </div>
            <hr>
            <div class="row">
              <h4>Desired Property Features:</h4>
              @if (isset($auction->get->property_type))
                <div class="col-md-12">
                  <i class="fa-regular fa-check-square"></i>
                  <span class="fw-bold">Acceptable Property Styles:
                    <span  class="removeBold">({{ @$auction->get->property_type }})</span>
                  </span>
                  @if (isset($auction->get->property_items) && is_array($auction->get->property_items))
                    @foreach (array_filter(@$auction->get->property_items) as $key => $item)
                      <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                        {{ @$item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->prop_condition) && is_array($auction->get->prop_condition))
                @php
                    $tenantConditionDisplayMap = [
                        'Partially updated (some older finishes OK)' => 'Partially Updated',
                        'Older but clean & well maintained' => 'Older but Clean & Well Maintained',
                        'No preference (open to any condition)' => 'No Preference',
                        'Updated/Renovated' => 'Updated / Renovated',
                        'Partially Updated' => 'Partially Updated',
                        'Older but Clean' => 'Older but Clean & Well Maintained',
                        'No Preference' => 'No Preference',
                    ];
                @endphp
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i> <strong>Acceptable Property Conditions: </strong>
                  @foreach ($auction->get->prop_condition as $item)
                    @php
                        $displayVal = $tenantConditionDisplayMap[$item] ?? $item;
                    @endphp
                    @if ($item != 'Other')
                      {{ $displayVal }}
                    @elseif($item == 'Other')
                      {{ $auction->get->propsOther }}
                    @endif
                  @endforeach
                </div>
              @endif
              @if (isset($auction->get->leaseProp) && @$auction->get->leaseProp != 'Single Room')
                <div class="col-md-12 col-12 mt-2">
                  <i class="fa-regular fa-check-square"></i> <strong>Acceptable Leasing Space:</strong>
                  {{ @$auction->get->leaseProp }}
                </div>
              @elseif(isset($auction->get->leaseProp) && $auction->get->leaseProp == 'Single Room')
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i> <strong>Acceptable Leasing Space:<span class="removeBold">(Single Room)</span></strong>
                  {{ @$auction->get->leasePropOther }}
                </div>
              @endif
              @if (@$auction->get->bedrooms != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Minimum Bedrooms Needed:</strong>
                  {{ !empty($auction->get->bedrooms) ? ($auction->get->bedrooms != 'Other' ? $auction->get->bedrooms : $auction->get->custom_bedrooms) : '' }}
                </div>
              @endif
              @if (@$auction->get->bathrooms != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Minimum Bathrooms Needed:</strong>
                  {{ !empty($auction->get->bathrooms) ? ($auction->get->bathrooms != 'Other' ? $auction->get->bathrooms : $auction->get->custom_bathrooms) : '' }}
                </div>
              @endif
              @if (@$auction->get->minimum_sqft_needed != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> @if($auction->get->property_type == 'Residential Property')<strong>Minimum Heated Sqft Needed:</strong>@else<strong>Minimum Net Leaseable Sqft Needed:</strong>@endif
                  {{ $auction->get->minimum_sqft_needed }}
                </div>
              @endif
              @if (@$auction->get->minimum_sqft_needed != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Minimum Total Acreage Needed:</strong>
                  {{ $auction->get->minimum_sqft_needed }}
                </div>
              @endif
              @if (@$auction->get->parking_feature_garage != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i>
                  <strong>Garage/Parking Features Needed:</strong>
                  {{ @$auction->get->parking_feature_garage != 'Other' ? $auction->get->parking_feature_garage : $auction->get->parkingGarageOther }}
                </div>
              @endif
              @if (isset($auction->get->Furnishings) && is_array($auction->get->Furnishings))
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Furnishings Needed:</strong>
                  @foreach ($auction->get->Furnishings as $item)
                    {{$item . '.'}}
                  @endforeach
                </div>
              @endif
              @if (@$auction->get->garage != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Garage Needed: </strong>
                  {{ $auction->get->garage == 'Yes' ? ($auction->get->garage_opt == 'Other' ? $auction->get->custom_garage : $auction->get->garage_opt) : '(No)' }}
                </div>
              @endif
              @if (@$auction->get->carport != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Carport Needed:</strong>
                  {{ @$auction->get->carport == 'Yes' ? (@$auction->get->carport_opt == 'Other' ? $auction->get->custom_carport : $auction->get->carport_opt) : '(No)' }}
                </div>
              @endif
              @if (@$auction->get->pool != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Pool Needed:</strong>
                  {{ @$auction->get->pool == 'Yes' ? @$auction->get->poolNeededOpt : '(No)' }}
                </div>
              @endif
              @if (isset($auction->get->viewOpt))
                <div class="col-md-12 mt-2">
                  <i class="fa-regular fa-check-square"></i>
                  <span class="fw-bold">View Preference Needed:
                    <span class="removeBold">
                      ({{ @$auction->get->viewOpt }})
                    </span>
                  </span>
                  @if (isset($auction->get->viewReference) && is_array($auction->get->viewReference))
                    @foreach (array_filter($auction->get->viewReference) as $item)
                      @if (@$item != 'No' && @$item != 'Other')
                        <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                          {{ @$item }}</span>
                      @endif
                      @if (@$item == 'Other' && @$auction->get->viewReferenceOther != null)
                        <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                          {{ @$auction->get->viewReferenceOther }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->waterAccessOpt))
                <div class="col-md-12">
                  <i class="fa-regular fa-check-square"></i>
                  <span class="fw-bold">Water Access Needed:<span
                      class="removeBold">({{ @$auction->get->waterAccessOpt }})</span>
                  </span>
                  @if (@$auction->get->waterAccessOpt != 'No' && is_array($auction->get->water_access))
                    @foreach (array_filter(@$auction->get->water_access) as $key => $item)
                      <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                        {{ @$item }}
                      </span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->has_water_view != null)
                <div class="row">
                  <div class="col-md-12">
                    <i class="fa-regular fa-check-square"></i>
                    <span class="fw-bold">Water View Needed:<span
                        class="removeBold">({{ @$auction->get->has_water_view }})</span>
                    </span>
                    @if (@$auction->get->has_water_view != 'No' && is_array($auction->get->water_view))
                      @foreach (array_filter(@$auction->get->water_view) as $key => $item)
                        <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                          {{ @$item }}</span>
                      @endforeach
                    @endif
                  </div>
                </div>
              @endif
              @if (@$auction->get->has_water_extra != null)
                <div class="row">
                  <div class="col-md-12">
                    <i class="fa-regular fa-check-square"></i>
                    <span class="fw-bold">Water Extras Needed:<span
                        class="removeBold">({{ @$auction->get->has_water_extra }})</span>
                    </span>
                    @if (@$auction->get->has_water_extra != 'No ' && is_array($auction->get->water_extras))
                      @foreach (array_filter(@$auction->get->water_extras) as $key => $item)
                        <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                          {{ @$item }}</span>
                      @endforeach
                    @endif
                  </div>
                </div>
              @endif
              @if (@$auction->get->waterFrontageOpt != null)
                <div class="col-md-12">
                  <i class="fa-regular fa-check-square"></i>
                  <span class="fw-bold">Water Frontage Needed:<span
                      class="removeBold">({{ @$auction->get->waterFrontageOpt }})</span>
                  </span>
                  @if (@$auction->get->waterFrontageOpt != 'No' && is_array($auction->get->waterFrontage))
                    @foreach (array_filter(@$auction->get->waterFrontage) as $key => $item)
                      <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                        {{ @$item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->has_dock))
                <div class="row">
                  <div class="col-md-12">
                    <i class="fa-regular fa-check-square"></i>
                    <span class="fw-bold">Dock Needed:</span>
                    @if (@$auction->get->has_dock != 'No' && is_array($auction->get->dock))
                      <span class="removeBold">
                        ({{$auction->get->has_dock}})
                      </span>
                      @foreach (array_filter(@$auction->get->dock) as $item)
                        @if ($item != 'Other')
                          <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                            {{ @$item }}
                          </span>
                        @else
                          <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                            {{ $auction->get->dockDescription }}
                          </span>
                        @endif
                      @endforeach
                    @else
                      <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                        (No)
                      </span>
                    @endif
                  </div>
                </div>
              @endif
              @if (@$auction->get->is_tenant_eligible != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i>
                  <strong>Tenant Eligibility/Interest in Purchasing in 55-and- Over Communities</strong>
                  ({{ @$auction->get->is_tenant_eligible }})
                </div>
              @endif
            </div>
            <hr>
            @if (isset($auction->get->any_non_negotiable_factors))
              <div class="row">
                <h4>Non-Negotiable Amenities or Property Features:</h4>
                <div class="col-md-12 col-12  mt-2">
                  @if ($auction->get->any_non_negotiable_factors != 'No')
                    <span class="removeBold">(Yes)</span>
                    @if(isset($auction->get->negotiable) && is_array($auction->get->negotiable))
                      @foreach ($auction->get->negotiable as $item)
                        @if ($item != 'Other')
                          <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                            {{$item}}
                          </span>
                        @else
                          @if (isset($auction->get->negotiableOther))
                            <span class="d-inline-block bg-secondary text-white  px-2 rounded my-1">
                              {{$auction->get->negotiableOther}}
                            </span>
                          @endif
                        @endif
                      @endforeach
                    @endif
                  @else
                    (No)
                  @endif
                </div>
              </div>
              <hr>
            @endif
            <div class="row">
              <h4>Tenant’s Pre-Screening Criteria:</h4>
              @if (@$auction->get->has_pets != null)
                <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Pets:</strong>
                  {{ @$auction->get->has_pets }}</div>
              @endif
              @if (@$auction->get->has_pets == 'Yes')
                @if (@$auction->get->totalPets != null)
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Number of Pets:</strong>
                    {{ @$auction->get->totalPets }}
                  </div>
                @endif
                @if (@$auction->get->petType != null)
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Type of Pet(s):</strong>
                    {{ @$auction->get->petType }}
                  </div>
                @endif
                @if (@$auction->get->petBreed != null)
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Breed(s) of Pet(s)</strong>
                    {{ @$auction->get->petBreed }}
                  </div>
                @endif
                @if (@$auction->get->petWeight != null)
                  <div class="col-md-12 col-12  mt-2"><i class="fa-regular fa-check-square"></i> <strong>Weight of Pet(s):</strong>
                    {{ @$auction->get->petWeight }}
                  </div>
                @endif
              @endif
              @if (@$auction->get->how_many_occupying != null)
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i> <strong>Number of Occupants: </strong>
                  {{ @$auction->get->how_many_occupying }}
                </div>
              @endif
              @if (@$auction->get->monthly_household_income != null)
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i> <strong>Tenant’s Household Monthly Net Income:
                  </strong> {{ @$auction->get->monthly_household_income }}
                </div>
              @endif
              @if (@$auction->get->tenant_credit_score != null)
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i> <strong>Tenant’s Credit Score Rating:
                  </strong> {{ @$auction->get->tenant_credit_score }}
                </div>
              @endif
              @if (@$auction->get->convicted != null)
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                  <strong>Prior Felony Convictions in the Last 7 Years</strong>
                  @if(@$auction->get->convicted != 'Yes')
                    {{ @$auction->get->convicted }}
                  @elseif(@$auction->get->convicted == 'Yes')
                    {{ @$auction->get->custom_convicted }}
                  @endif
                </div>
              @endif
              @if (@$auction->get->evicted != null)
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                  <strong>Prior Evictions in the Last 7 Years</strong>
                  @if(@$auction->get->evicted != 'Yes')
                      {{ @$auction->get->evicted }}
                  @elseif(@$auction->get->evicted == 'Yes')
                    {{ @$auction->get->custom_evicted }}
                  @endif
                </div>
              @endif
            </div>
            <hr>
            <div class="row">
              <h4>Tenant’s Agent Representation:</h4>
              @if (isset($auction->get->represented))
                <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                  <strong>Tenant Represented by a Real Estate Agent:</strong>
                  <span class="removeBold">{{$auction->get->represented}}</span>
                </div>
              @endif
              @if (isset($auction->get->represented) && $auction->get->represented == 'Yes')
                @if (isset($auction->get->agentCommissionRequested))
                  <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                    <strong>Tenant Requests Landlord to Pay Tenant’s Agent Commission:</strong>
                    <span class="removeBold">{{$auction->get->agentCommissionRequested}}</span>
                  </div>
                @endif
                @if (isset($auction->get->agentCommissionRequested) && $auction->get->agentCommissionRequested == 'Yes' && isset($auction->get->agentCompensationAmountReq))
                  <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                    <strong>Requested Amount for Landlord to Pay Tenant’s Agent Commission:</strong>
                    @if ($auction->get->agentCompensationAmountReq != 'Other')
                      <span class="removeBold">{{$auction->get->agentCompensationAmountReq}}</span>
                    @else
                      <span class="removeBold">{{$auction->get->agentCompensationAmountReqOther}}</span>
                    @endif
                  </div>
                @elseif (isset($auction->get->agentCommissionRequested) && $auction->get->agentCommissionRequested == 'No' && isset($auction->get->tenantPaysTheAgent))
                  <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                    <strong>Agent Compensation if Not Offered by Landlord:</strong>
                    <span class="removeBold">{{$auction->get->tenantPaysTheAgent}}</span>
                  </div>
                  @if ($auction->get->tenantPaysTheAgent != 'No' && isset($auction->get->tenantPaysAmount))
                    <div class="col-md-12 col-12 mt-2"><i class="fa-regular fa-check-square"></i>
                      <strong>Offered Agent Compensation:</strong>
                      <span class="removeBold">{{$auction->get->tenantPaysAmount != 'Other' ? $auction->get->tenantPaysAmount : $auction->get->tenantPaysAmountOther}}</span>
                    </div>
                  @endif
                @endif
              @endif
            </div>
            <hr>
            <div class="row">
              @php
                $user = $auction->user()->first();
              @endphp
              @if ($user->user_type == 'agent')
                <h4>Tenant’s Agent Information:</h4>
              @else
                <h4>Tenant’s Information:</h4>
              @endif
              <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>First Name:</strong>
                {{ @$auction->get->agent_first_name }}
              </div>
              <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>Last Name:</strong>
                {{ @$auction->get->agent_last_name }}
              </div>
              <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>Phone Number:</strong>
                {{ @$auction->get->agent_phone }}
              </div>
              <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>Email:</strong>
                {{ @$auction->get->agent_email }}
              </div>
              @if ($user->user_type == 'agent')
                <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>Brokerage:</strong>
                  {{ @$auction->get->agent_brokerage }}
                </div>
                <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>Real Estate License#:</strong>
                  {{ @$auction->get->agent_license_no }}
                </div>
                <div class="col-md-12 col-12 mt-2"><i class="fa-solid fa-square-check"></i> <strong>NAR Member ID (NRDS ID):</strong>
                  {{ @$auction->get->agent_mls_id }}
                </div>
              @endif
            </div>
          </div>
        </div>
        <div class="card review">
          <div class="card-body d-flex align-items-center">
            <div class="left d-flex align-items-center">
              <img class="w-25" src="https://ppt1080.b-cdn.net/images/avatar/none.png" alt="">
              <div>
                <p class="mb-0"><a href="{{ route('author', [@$auction->user_id]) }}"><b>User Details</b></a><span></span>
                  <span class="start opacity-50">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                  </span>
                </p>
                <p class="mb-0">...</p>
                <p class="mb-0 opacity-50">{{ @$auction->user->name }} • last online 5 days ago.</p>
              </div>
            </div>
            <div class="right text-center">
              <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">Message</button></a>
              <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">View Profile</button></a>
            </div>
          </div>
        </div>
      </div>
      <!--Right Side-->
      <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
        <h1>{{ @$auction->get->titleListing }}</h1>
        <hr>
        @inject('carbon', 'Carbon\Carbon')
        @php
          if (@$auction->auction_length > 0) {
              $start = $carbon::now();
              $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->auction_length);
              $diff = $end->diffInDays($start);
          }
        @endphp
        @if (@$auction->auction_length > 0)
          @php
            $diff_d = $diff;
            $diff_H = $start->diff($end)->format('%H');
            $diff_I = $start->diff($end)->format('%I');
            $diff_S = $start->diff($end)->format('%S');
          @endphp
          <div class="time d-flex justify-content-between text-center flex-wrap pb-2">
            <div>
              <h5><b class="timer-d"> {{ $diff_d }} </b></h5>
              <h6 class="opacity-50">Days</h6>
            </div>
            <div>
              <h5><b class="timer-h"> {{ $diff_H }} </b></h5>
              <h6 class="opacity-50">Hrs</h6>
            </div>
            <div>
              <h5><b class="timer-m"> {{ $diff_I }} </b></h5>
              <h6 class="opacity-50">Mins</h6>
            </div>
            <div>
              <h5><b class="timer-s"> {{ $diff_S }} </b></h5>
              <h6 class="opacity-50">Secs</h6>
            </div>
          </div>
        @endif
        @php
          $highest_bid_price = @$auction->bids->max('price');
          $highest_bidder = @$auction->bids->where('price', $highest_bid_price)->first();
          $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp
        @if (@$auction->user_id != $auth_id)
          <a href="{{ route('auction-chat', ['tenant-criteria', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message</a>
        @endif
        @if ($auth_id)
          @if (in_array(auth()->user()->user_type, ['landlord', 'agent']))
            <button class="btn w-100"
              onclick="javascript:window.location='{{ route('tenant.criteria.auction.bid', @$auction->id) }}';"
              {{ @$auction->user_id == $auth_id ? 'disabled' : '' }}>
              <span class="bid">Bid Now </span>
              <span
                class="badge bg-light float-end text-dark">${{ number_format(@$auction->get->monthly_price) }}</span>
              @if (@$auction->sold)
                <span class="badge bg-danger">Sold</span>
              @endif
              {{-- {{$res}} --}}
            </button>
          @endif
        @else
          <a href="{{ route('login') }}">
            <button class="btn w-100">
              <span class="bid">Login for Bid </span>
              <span class="badge bg-light float-end text-dark">${{ $lowest_bid_price }}</span>
            </button>
          </a>
        @endif
        <!-- Highest Bider -->
        <div class="card higestBider">
          @if($auction->user_id == auth()->user()->id && $auction->bids->count() > 0)
            <div class="d-flex align-items-baseline justify-content-center">
              @if ($auction->display_bids == 0)
                <form action="{{ route('tenant.criteria.bids.visibility', ['id' => $auction->id, 'vis' => 'show']) }}"  method="post">
                  @csrf
                  <button class="btn bg-success btn-sm px-3 mb-3 mt-0">Show Bids</button>
                </form>
              @else
                <form action="{{ route('tenant.criteria.bids.visibility', ['id' => $auction->id, 'vis' => 'hide']) }}" method="post">
                  @csrf
                  <button class="btn bg-danger btn-sm px-3 mb-3 mt-0">Hide Bids</button>
                </form>
              @endif
            </div>
          @endif
          <div class="card-body">
            @if ($highest_bidder)
              <p><b>{{ $highest_bidder->user->name ?? '' }}</b> is the highest bidder.</p>
            @else
              <p>No one has bid on this auction.</p>
            @endif
            <div class="accordion" id="accordionExample">
              <div class="accordion-item border-0">
                {{-- @dd(@$auction->bids) --}}
                @if ($auction->display_bids == 1 || $auction->user_id == Auth::user()->id)
                  @foreach ($bids as $bid)
                    <!-- Item loop -->
                    <div class="accordion" type="button" data-bs-toggle="collapse"
                      data-bs-target="#item{{ $bid->id }}" aria-expanded="true"
                      aria-controls="item{{ $bid->id }}">
                      <div class="d-flex small accordion mr-0 text-center">
                        <div class="col-1">
                          <span class="badge">{{ $loop->iteration }}</span>
                        </div>
                        <div class="col-4">
                          {{ $bid->user->name }} </div>
                        <div class="col-4 text-right">
                          ${{ number_format($bid->get->price) }} </div>
                        <div class="col-2">
                          Terms↓
                        </div>
                      </div>
                    </div>
                    <div id="item{{ $bid->id }}" class="accordion-collapse collapse" aria-labelledby="headingOne"
                      data-bs-parent="#accordionExample">
                      <div class="accordion-body">
                        <div id="bidding_history_data">
                          <div>
                            <table class="table table-bordered">
                              <tbody>
                                @if ($bid->get->first_name)
                                  <tr>
                                    <th class="small">First Name</th>
                                    <td class="small">{{ $bid->get->first_name }}</td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->city))
                                  <tr>
                                    <th class="small">City</th>
                                    <td class="small">
                                      <span>{{ $bid->get->city }}</span>
                                    </td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->county))
                                  <tr>
                                    <th class="small">County</th>
                                    <td class="small">
                                      <span>{{ $bid->get->county }}</span>
                                    </td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->state))
                                  <tr>
                                    <th class="small">State</th>
                                    <td class="small">
                                      <span>{{ $bid->get->state }}</span>
                                    </td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->price))
                                  <tr>
                                    <th class="small">Offered Lease Price:</th>
                                    <td class="small">{{'$' . $bid->get->price }}</td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->leaseTime) && is_array($bid->get->leaseTime))
                                  <tr>
                                    <th class="small">Offered Lease Length:</th>
                                    @foreach ($bid->get->leaseTime as $item)
                                      <td class="small">{{ $item }}</td>
                                    @endforeach
                                  </tr>
                                @endif
                                @if (isset($bid->get->leaseDate))
                                  <tr>
                                    <th class="small">Offered Lease Start Date:</th>
                                    <td class="small">{{ $bid->get->leaseDate }}</td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->offerExpires))
                                  <tr>
                                    <th class="small">Offer Expires:</th>
                                    <td class="small">{{ $bid->get->offerExpires }}</td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->landlordOfferCommission))
                                  <tr>
                                    <th class="small">Offered Agent Commission:</th>
                                    <td class="small">{{ $bid->get->landlordOfferCommission }}</td>
                                  </tr>
                                @endif
                                @if (isset($bid->get->commissionAmmountOffered))
                                  <tr>
                                    <th class="small">Offered Agent Commission Amount:</th>
                                    <td class="small">{{ $bid->get->commissionAmmountOffered !== 'Other' ?  $bid->get->commissionAmmountOffered : $bid->get->landlordPaysAmount }}</td>
                                  </tr>
                                @endif
                                <tr>
                                  <th class="small">Additional Details or Countered Terms:</th>
                                  <td class="small"></td>
                                </tr>
                              </tbody>
                            </table>

                            @if (@$auction->user_id == $auth_id)
                              @if (!@$auction->is_sold)
                                <div class="d-flex justify-content-between align-items-center">
                                  <form action="{{ route('agent.tenant.criteria.auction.bid.accept') }}" method="post">
                                    @csrf
                                    <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                    <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                    <div style="text-align: right;">
                                      <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                    </div>
                                  </form>
                                  <form action="{{ route('tenant.criteria.add.counter-bid', $bid->id) }}" method="get">
                                    <div class="d-flex gap-1">
                                      <button type="submit" class="btn btn-primary  btn-sm">Counter Bid</button>
                                    </div>
                                  </form>
                                </div>
                              @endif
                            @endif
                            @auth
                                @if (auth()->user()->id == $bid->user->id || (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin'))
                                  <div class="form-group biddingOperations">
                                    <h5 class="my-3 text-center"><u>Countered Terms</u></h5>
                                    @php
                                      $allBids = App\Models\TenantCriteriaAuctionBid::where('counter_id', $bid->id)->with('meta')
                                          ->orderByDesc('created_at')
                                          ->get();
                                    @endphp
                                    <div class="form-group">
                                      @if (!$auction->sold)
                                        @if (isset($allBids))
                                          @foreach ($allBids as $key => $countBid)
                                            <table class="table table-bordered">
                                              <tbody>
                                                @php
                                                  $user = \App\Models\User::findOrFail($countBid->user_id);
                                                @endphp
                                                @if (isset($countBid->get->first_name))
                                                  <tr>
                                                    <th class="small">First Name:</th>
                                                    <td class="small">{{ $countBid->get->first_name }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->city) && $user->user_type !== 'tenant')
                                                  <tr>
                                                    <th class="small">City:</th>
                                                    <td class="small">{{ $countBid->get->city }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->county) && $user->user_type !== 'tenant')
                                                  <tr>
                                                    <th class="small">County:</th>
                                                    <td class="small">{{ $countBid->get->county }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->state) && $user->user_type !== 'tenant')
                                                  <tr>
                                                    <th class="small">State:</th>
                                                    <td class="small">{{ $countBid->get->state }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->price))
                                                  <tr>
                                                    <th class="small">{{$user->user_type == 'tenant' ? 'Acceptable Lease Price:' : 'Offered Lease Price:'}}</th>
                                                    <td class="small">{{'$' . $countBid->get->price }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->leaseTime) && is_array($countBid->get->leaseTime))
                                                  <tr>
                                                    <th class="small">{{$user->user_type == 'tenant' ? 'Acceptable Lease Length:' : 'Offered Lease Length:'}}</th>
                                                    @foreach ($countBid->get->leaseTime as $item)
                                                      <td class="small">{{ $item !== 'Other' ? $item : $countBid->get->other_lease_duration }}</td>
                                                    @endforeach
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->leaseDate))
                                                  <tr>
                                                    <th class="small">{{$user->user_type == 'tenant' ? 'Acceptable Lease Start Date:' : 'Offered Lease Start Date:'}}</th>
                                                    <td class="small">{{ $countBid->get->leaseDate }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->landlordOfferCommission))
                                                  <tr>
                                                    <th class="small">{{$user->user_type == 'tenant' ? 'Tenant Requests Landlord to Pay Tenant’s Agent Commission:' : 'Offered Agent Commission:'}}</th>
                                                    <td class="small">{{ $countBid->get->landlordOfferCommission }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->commissionAmmountOffered))
                                                  <tr>
                                                    <th class="small">{{$user->user_type == 'tenant' ? 'Requested Amount for Landlord to Pay Tenant’s Agent Commission:' : 'Offered Agent Commission Amount:'}}</th>
                                                    <td class="small">{{ $countBid->get->commissionAmmountOffered !== 'Other' ?  $countBid->get->commissionAmmountOffered : $countBid->get->landlordPaysAmount }}</td>
                                                  </tr>
                                                @endif
                                                @if (isset($countBid->get->offerExpires))
                                                  <tr>
                                                    <th class="small">Offer Expires:</th>
                                                    <td class="small">{{ $countBid->get->offerExpires }}</td>
                                                  </tr>
                                                @endif
                                                <tr>
                                                  <th class="small">Additional Details or Countered Terms:</th>
                                                  <td class="small"></td>
                                                </tr>
                                              </tbody>
                                            </table>

                                            @if (@$auction->user_id == $auth_id)
                                              @if (!@$auction->is_sold)
                                                <div class="d-flex justify-content-between align-items-center">
                                                  <form action="{{ route('agent.tenant.criteria.auction.bid.accept') }}" method="post" class="w-100">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                                    <input type="hidden" name="bid_id" value="{{ $countBid->id }}">
                                                    @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                      <div class="d-flex justify-content-end">
                                                        <button type="submit" class="btn bg-success btn-sm">Accept</button>
                                                      </div>
                                                    @endif
                                                  </form>
                                                </div>
                                              @endif
                                            @endif
                                          @endforeach
                                        @else
                                          <h6>No Counter Bid</h6>
                                        @endif
                                      @endif
                                    </div>
                                  </div>
                                @endif
                            @endauth
                            {{-- <h5 style="text-decoration: underline">Additional Details or Countered Terms:</h5> --}}
                          </div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                @else
                  <h6 class="text-center text-danger">Bids are hidden!</h6>
                @endif
              </div>
            </div>
          </div>
        </div>
        <button class="btn w-100 mt-0">
          <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
        </button>
        <!-- End  -->
        <!-- Social Details  -->
        <div class="p-4 card">
          <p class="text-600">Share this link via</p>
          <div class="qr-code" style="width: 100%; height:200px;">
            {{ qr_code(route('tenant.criteria.auction.view', @$auction->id), 200) }}
          </div>
          <div class="card-social">
            <ul class="icons">
              <a href="">
                <i class="fa-brands fa-facebook-f"></i>
              </a>

              <a href="">
                <i class="fa-brands fa-twitter"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-instagram"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-pinterest"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-linkedin"></i>
              </a>
            </ul>
            <p class="small opacity-8">Or copy link</p>
            <div class="field">
              <i class="fa-solid fa-link"></i>
              <input type="text" readonly="" id="copylink"
                value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
              <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
            </div>

          </div>
        </div>
        <!-- End  -->
      </div>
    </div>
  </div>



@endsection

@push('scripts')
  {{-- <script src="{{asset('assets/bootstrap-5.2.2/js/twitter-bootstrap.min.js')}}"></script> --}}
  <script src="{{ asset('assets/choices.min.js') }}"></script>
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
    function changeListed(val) {
      if (val == "Yes") {
        // alert("Yes");
        $('.listing_photos').addClass('d-none');
        $('.listing_link').removeClass('d-none');
      } else {
        // alert("No");
        $('.listing_link').addClass('d-none');
        $('.listing_photos').removeClass('d-none');
      }
    }
  </script>
  <script>
    $(function() {
      var multipleCancelButton = new Choices('.multiple', {
        removeItemButton: true,
        // maxItemCount:5,
        // searchResultLimit: 5,
        // renderChoiceLimit: 5
      });
    });
  </script>
@endpush
