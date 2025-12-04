@extends('layouts.main')
@push('styles')
    <!-- //Listing Description css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
    <style>
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

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

        ul.leasing {
            --icon-size: 0.8em;
            --gutter: .5em;
            padding: 0 0 0 calc(var(--icon-size) + 1em);
            margin-bottom: 5px;
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

        ul li::marker {
            content: "\f101";
            /* FontAwesome Unicode */
            font-family: FontAwesome;
            font-size: var(--icon-size);
            /* color: #006e9f; */
            color: #11b7cf;
        }

        ul.services li {
            padding-left: var(--gutter);
            color: #34465c;
            list-style: none;
            /* Remove default list style */
            position: relative;
            /* Set position relative for ::before pseudo-element */
        }

        ul.services li::before {
            content: "\f101";
            /* FontAwesome icon content */
            font-family: FontAwesome;
            font-size: var(--icon-size);
            /* Set the desired icon size */
            position: absolute;
            /* Position the icon */
            left: -1.5em;
            /* Adjust the icon position */
            color: #11b7cf;
            /* Set the icon color */
        }

        .removeBold {
            font-weight: normal;
        }
    </style>
@endpush
@section('content')
    <div class="container listingDescription">
        <div class="row mb-5">
            <div class="col-12">
                @if (isset($bid->get->offered_price))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Offered Price:
                        <span class="removeBold">{{ $bid->get->offered_price }}</span>
                    </div>
                @endif
                @if (isset($bid->get->lease_terms) && is_array($bid->get->lease_terms))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Offered Lease Length:
                        @foreach ($bid->get->lease_terms as $term)
                            <span class="removeBold">{{ $term !== 'Other' ? $term :  $bid->get->price }}</span>
                        @endforeach
                    </div>
                @endif
                @if (isset($bid->get->start_date))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Offered Lease Start Date:
                        <span class="removeBold">{{ $bid->get->start_date}}</span>
                    </div>
                @endif
                @if (isset($bid->get->days_until_start_date))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Days Until Lease Start Date:
                        <span class="removeBold">{{ $bid->get->days_until_start_date}}</span>
                    </div>
                @endif
                @if (isset($bid->get->occupants))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>How many people will be occupying the property?
                        <span class="removeBold">{{ $bid->get->occupants }}</span>
                    </div>
                @endif
                @if (isset($bid->get->petOpt))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Do the tenant(s) have any pets?
                        <span class="removeBold">{{ $bid->get->petOpt }}</span>
                    </div>
                @endif
                @if (isset($bid->get->pets))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>How many pets does the tenant have?
                        <span class="removeBold">{{ $bid->get->pets }}</span>
                    </div>
                @endif
                @if (isset($bid->get->petTypes))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What type of pet(s) does the tenant have?
                        <span class="removeBold">{{ $bid->get->petTypes }}</span>
                    </div>
                @endif
                @if (isset($bid->get->petBreed))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What breed(s) are the pet(s)?
                        <span class="removeBold">{{ $bid->get->petBreed }}</span>
                    </div>
                @endif
                @if (isset($bid->get->petWeight))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What is the weight of the pet(s)? 
                        <span class="removeBold">{{ $bid->get->petWeight }}</span>
                    </div>
                @endif
                @if (isset($bid->get->scoreRating))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What is the credit score rating of the tenant(s)?
                        <span class="removeBold">{{ $bid->get->scoreRating }}</span>
                    </div>
                @endif
                @if (isset($bid->get->monthlyIncome))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What is the total household monthly net income of the tenant(s)?
                        <span class="removeBold">{{ $bid->get->monthlyIncome }}</span>
                    </div>
                @endif
                @if (isset($bid->get->evictions))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Have the tenant(s) had any prior evictions within the last 7 years?
                        <span class="removeBold">{{ $bid->get->evictions }}</span>
                    </div>
                @endif
                @if (isset($bid->get->evictionsYes))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Explain when and what for:
                        <span class="removeBold">{{ $bid->get->evictionsYes }}</span>
                    </div>
                @endif
                @if (isset($bid->get->convicted))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Have the tenant(s) been convicted of a felony within the last 7 years?
                        <span class="removeBold">{{ $bid->get->convicted }}</span>
                    </div>
                @endif
                @if (isset($bid->get->convictedYes))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Explain when and what for:
                        <span class="removeBold">{{ $bid->get->convictedYes }}</span>
                    </div>
                @endif
                @if (isset($bid->get->violations))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Have the tenant(s) been involved in any prior lease violations?
                        <span class="removeBold">{{ $bid->get->violations }}</span>
                    </div>
                @endif
                @if (isset($bid->get->violationsYes))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Explain when and what for:
                        <span class="removeBold">{{ $bid->get->violationsYes }}</span>
                    </div>
                @endif
                @if (isset($bid->get->outstanding))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Do the tenant(s) have any outstanding balances with previous landlords?
                        <span class="removeBold">{{ $bid->get->outstanding }}</span>
                    </div>
                @endif
                @if (isset($bid->get->outstandingYes))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Explain when, why, and how much the tenant owes any previous landlords:
                        <span class="removeBold">{{ $bid->get->outstandingYes }}</span>
                    </div>
                @endif
                @if (isset($bid->get->tenant_represented))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Are the tenant(s) represented by a real estate agent?
                        <span class="removeBold">{{ $bid->get->tenant_represented }}</span>
                    </div>
                @endif
                @if (isset($bid->get->compensation_acceptable))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What compensation is acceptable to the tenant(s)’ broker?
                        <span class="removeBold">{{ $bid->get->compensation_acceptable }}</span>
                    </div>
                @endif
                @if (isset($bid->get->offer_expiry))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>When will the offer expire?
                        <span class="removeBold">{{ $bid->get->offer_expiry }}</span>
                    </div>
                @endif
                @if (isset($bid->get->escalation_clause))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Would the tenant(s) like to set an escalation clause to automatically increase their bid price and terms up to a maximum amount specified by them in the event of multiple offers?
                        <span class="removeBold">{{ $bid->get->escalation_clause }}</span>
                    </div>
                @endif
                @if (isset($bid->get->autobid_price))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Autobid Price:
                        <span class="removeBold">{{ $bid->get->autobid_price }}</span>
                    </div>
                @endif
                @if (isset($bid->get->autobid_days_start_date))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Autobid Days Until the Lease Start Date:
                        <span class="removeBold">{{ $bid->get->autobid_days_start_date }}</span>
                    </div>
                @endif
                @if (isset($bid->get->autobid_lease_length))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Autobid Lease Length in Months:
                        <span class="removeBold">{{ $bid->get->autobid_lease_length }}</span>
                    </div>
                @endif
                @if (isset($bid->get->additionalInfo))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Please provide any additional information that the tenant(s) would like to include:
                        <span class="removeBold">{{ $bid->get->additionalInfo }}</span>
                    </div>
                @endif
                
                @if (isset($bid->get->first_name))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>First Name:
                        <span class="removeBold">{{ $bid->get->first_name }}</span>
                    </div>
                @endif
                @if (isset($bid->get->last_name))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Last Name:
                        <span class="removeBold">{{ $bid->get->last_name }}</span>
                    </div>
                @endif
                @if (isset($bid->get->agent_phone))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Phone Number:
                        <span class="removeBold">{{ $bid->get->agent_phone }}</span>
                    </div>
                @endif
                @if (isset($bid->get->agent_email))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Email:
                        <span class="removeBold">{{ $bid->get->agent_email }}</span>
                    </div>
                @endif
                @if (isset($bid->get->agent_brokerage))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Brokerage:
                        <span class="removeBold">{{ $bid->get->agent_brokerage }}</span>
                    </div>
                @endif
                @if (isset($bid->get->agent_license_no))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Real Estate License #:
                        <span class="removeBold">{{ $bid->get->agent_license_no }}</span>
                    </div>
                @endif
                @if (isset($bid->get->agent_mls_id))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>NAR Member ID (NRDS ID):
                        <span class="removeBold">{{ $bid->get->agent_mls_id }}</span>
                    </div>
                @endif
               
            </div>
        </div>
    </div>
@endsection