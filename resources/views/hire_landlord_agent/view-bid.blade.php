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
                @if (isset($bid->get->listing_terms))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What is the timeframe offered by the agent for the landlord agency agreement?
                        <span class="removeBold">{{ $bid->get->listing_terms !== 'Other' ? $bid->get->listing_terms : $bid->get->custom_listing_terms }}</span>
                    </div>
                @endif
                @if (isset($bid->get->broker_compensation))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What compensation will the listing broker accept from the owner for their services?
                        <span class="removeBold">{{ $bid->get->broker_compensation . '(' . $bid->get->compensation_percent . ')' }}</span>
                    </div>
                @endif
                @if (isset($bid->get->handle_compensation))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>How would the listing broker prefer to handle compensation for a tenant's broker?
                        <span class="removeBold">{{ $bid->get->handle_compensation}}</span>
                    </div>
                @endif
                @if (isset($bid->get->compensation_amount))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What compensation is being offered to the tenant's broker?
                        <span class="removeBold">{{ $bid->get->compensation_amount . (isset($bid->get->compensation_tenant_broker) ? '(' . $bid->get->compensation_tenant_broker . ')' : '')}}</span>
                    </div>
                @endif
                @if (isset($bid->get->payment_timing))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Payment Timing for Broker Fees:
                        <span class="removeBold">{{ $bid->get->payment_timing . (isset($bid->get->payment_timing_days) ? '(' . $bid->get->payment_timing_days . ')' : '')}}</span>
                    </div>
                @endif
                @if (isset($bid->get->early_termination))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Early Termination:
                        <span class="removeBold">{{ $bid->get->early_termination . (isset($bid->get->early_termination_amount) ? '(' . $bid->get->early_termination_amount . ')' : '')}}</span>
                    </div>
                @endif
                @if (isset($bid->get->protection_period))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Protection Period:
                        <span class="removeBold">{{ $bid->get->protection_period . (isset($bid->get->protection_period_days) ? '(' . $bid->get->protection_period_days . ')' : '')}}</span>
                    </div>
                @endif
                @if (isset($bid->get->compensation_new_lease))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>If the owner enters into a new lease or renewal with a tenant placed by the broker, does the broker require compensation?
                        <span class="removeBold">{{ $bid->get->compensation_new_lease }}</span>
                    </div>
                @endif
                @if (isset($bid->get->compensation_new_lease_percent))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>If the owner enters into a new lease or renewal with a tenant placed by the broker, does the broker require compensation?
                        <span class="removeBold">{{ $bid->get->compensation_new_lease_percent . (isset($bid->get->compensation_new_lease_amount) ? '(' . $bid->get->compensation_new_lease_amount . ')' : '') }}</span>
                    </div>
                @endif
                @if (isset($bid->get->bio))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>About Agent:
                        <span class="removeBold">{{ $bid->get->bio }}</span>
                    </div>
                @endif
                @if (isset($bid->get->why_hire_you))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Why should you be hired as their agent?
                        <span class="removeBold">{{ $bid->get->why_hire_you }}</span>
                    </div>
                @endif
                @if (isset($bid->get->what_sets_you_apart))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What sets you apart from other agents?
                        <span class="removeBold">{{ $bid->get->what_sets_you_apart }}</span>
                    </div>
                @endif
                @if (isset($bid->get->marketing_plan))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What is your marketing strategy?
                        <span class="removeBold">{{ $bid->get->marketing_plan }}</span>
                    </div>
                @endif
                @if (isset($bid->get->website_link) && is_array($bid->get->website_link))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Website Link:
                        @foreach ($bid->get->website_link as $item)
                            <span class="removeBold">{{ $item }}</span>
                        @endforeach
                    </div>
                @endif
                @if (isset($bid->get->reviews_link) && is_array($bid->get->reviews_link))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Reviews Link:
                        @foreach ($bid->get->reviews_link as $item)
                            <span class="removeBold">{{ $item }}</span>
                        @endforeach
                    </div>
                @endif
                @if (isset($bid->get->socialType) && is_array($bid->get->socialType))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Social Type:
                        @foreach ($bid->get->socialType as $item)
                            <span class="removeBold">{{ $item }}</span>
                        @endforeach
                    </div>
                @endif
                @if (isset($bid->get->social_link) && is_array($bid->get->social_link))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Social Link:
                        @foreach ($bid->get->social_link as $item)
                            <span class="removeBold">{{ $item }}</span>
                        @endforeach
                    </div>
                @endif
                @if (isset($bid->get->licensed))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>What year did the agent get licensed?
                        <span class="removeBold">{{ $bid->get->licensed }}</span>
                    </div>
                @endif
                @if (isset($bid->get->services) && is_array($bid->get->services))
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>Select the included services that the agent will provide to the landlord:
                        <br>
                        @foreach ($bid->get->services as $item)
                            @if ($item !== 'Other')
                                <span class="removeBold">{{ $item . ',' }}</span>
                                <br>
                            @else
                                @php
                                    $rawOther = $bid->get->other_services ?? [];
                                    $parsedOther = is_array($rawOther)
                                        ? $rawOther
                                        : (is_string($rawOther) ? (json_decode($rawOther, true) ?: (strlen(trim($rawOther)) ? [$rawOther] : [])) : []);
                                @endphp
                                @foreach ($parsedOther as $otherItem)
                                    @if (!empty(trim((string) $otherItem)))
                                        <span class="removeBold">{{ trim((string) $otherItem) . ',' }}</span>
                                        <br>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
@push('scripts')
@endpush