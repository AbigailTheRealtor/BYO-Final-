@extends('layouts.main')
@push('styles')
    <!-- //Listing Description css  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
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
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center justify-content-left">
                        @if ($auction->is_approved == 1)
                          <span class="badge bg-primary me-2">Active</span>
                        @endif
                        @if ($auction->is_approved == 0)
                          <span class="badge bg-warning me-2">Pending</span>
                        @endif
                        @if ($auction->is_sold == 1)
                          <span class="badge bg-success">Sold</span>
                        @endif
                    </div>
                    @if ($auction->user_id == auth()->user()->id)
                        <div class="d-flex justify-content-end align-content-center">
                            <a href="{{route('buyer_agent.auction.edit', $auction->id)}}" class="btn btn-success btn-sm px-3 mb-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing</a>
                            <a href="{{ route('buyer.listings.download', $auction->id) }}" class="btn btn-outline-secondary btn-sm px-3 mb-3 me-2"><i class="fa fa-download me-1"></i>Download Listing Snapshot (PDF)</a>
                        </div>
                    @endif
                </div>
                <!-- Description Box  -->
                <div class="card description">
                    {{-- {{dd(@$auction)}} --}}
                    @if (@$auction->description)
                        <div class="card-header">
                            <h5>Description</h5>
                        </div>
                    @endif
                    <div class="card-body">
                        <div class="row">
                            @if (!@$auction->get && $auction->get->video != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">Video:
                                    <span class="removeBold">
                                        <video src="{{ asset($auction->get->video) }}" style="width:100%;height:29vh;"
                                            controls autoplay></video>

                                    </span>
                                </div>
                            @endif
                            @if (!@$auction->get && $auction->get->photo != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">Photo:
                                    <span class="removeBold">
                                        <img src="{{ asset($auction->get->photo) }}" style="width:100%;height:29vh;" />
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div class="row align-items-center description">
                            <h5>Description:</h5>
                            @if (@$auction->get->description_buyer_specific != null)
                                <p> {{ @$auction->get->description_buyer_specific }}</p>
                            @endif
                        </div>
                        <div class="row align-items-center desired_price_and_terms">
                            <h5>Desired Price and Terms:</h5>
                            @if (@$auction->get->max_price != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Maximum
                                    Budget:
                                    <span class="removeBold">{{ @$auction->get->max_price }}</span>
                                </div>
                            @endif
                            @php
                                $buyerFinancingData = @$auction->get->financings;
                                $buyerFinancingArr = is_array($buyerFinancingData) ? array_filter($buyerFinancingData) : [];
                                $buyerFinancingPills = \App\Helpers\ListingDisplayHelper::normalizeList($buyerFinancingArr, @$auction->get->financingOther);
                            @endphp
                            @if (!empty($buyerFinancingPills))
                                <div class="card-header section-header">
                                    <h4 class="section-title">Financing Details:</h4>
                                </div>
                                <div class="row">
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Offered Currency/Financing:
                                    @foreach ($buyerFinancingPills as $fp)
                                        <span class="removeBold badge bg-secondary">{{ $fp }}</span>
                                    @endforeach
                                </div>

                                @php $buyerFinancingOriginal = is_array($buyerFinancingData) ? array_filter($buyerFinancingData) : []; @endphp

                                @if (in_array('Seller Financing', $buyerFinancingOriginal) && !empty(@$auction->get->sellerFinancing))
                                @php $sfItems = array_values(array_filter(@$auction->get->sellerFinancing, fn($v) => \App\Helpers\ListingDisplayHelper::hasValue($v))); @endphp
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Seller Financing Terms</h6></div>
                                @if (count($sfItems) === 1)
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ $sfItems[0] }}</span></div>
                                @else
                                    @foreach ($sfItems as $sf)
                                    <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $sf }}</span></div>
                                    @endforeach
                                @endif
                                @endif

                                @if (in_array('Assumable', $buyerFinancingOriginal) && !empty(@$auction->get->assumable))
                                @php $asItems = array_values(array_filter(@$auction->get->assumable, fn($v) => \App\Helpers\ListingDisplayHelper::hasValue($v))); @endphp
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Assumable Terms</h6></div>
                                @if (count($asItems) === 1)
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ $asItems[0] }}</span></div>
                                @else
                                    @foreach ($asItems as $at)
                                    <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $at }}</span></div>
                                    @endforeach
                                @endif
                                @endif

                                @if (in_array('Exchange/Trade', $buyerFinancingOriginal) && !empty(@$auction->get->trade))
                                @php $trItems = array_values(array_filter(@$auction->get->trade, fn($v) => \App\Helpers\ListingDisplayHelper::hasValue($v))); @endphp
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Exchange/Trade Terms</h6></div>
                                @if (count($trItems) === 1)
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ $trItems[0] }}</span></div>
                                @else
                                    @foreach ($trItems as $tr)
                                    <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $tr }}</span></div>
                                    @endforeach
                                @endif
                                @endif

                                @if ((in_array('Lease Option', $buyerFinancingOriginal) || in_array('Lease Purchase', $buyerFinancingOriginal)) && !empty(@$auction->get->leaseOptions))
                                @php $loItems = array_values(array_filter(@$auction->get->leaseOptions, fn($v) => \App\Helpers\ListingDisplayHelper::hasValue($v))); @endphp
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Lease Option/Purchase Terms</h6></div>
                                @if (count($loItems) === 1)
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ $loItems[0] }}</span></div>
                                @else
                                    @foreach ($loItems as $lo)
                                    <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $lo }}</span></div>
                                    @endforeach
                                @endif
                                @endif

                                @if (in_array('Cryptocurrency', $buyerFinancingOriginal) && !empty(@$auction->get->cryptocurrency))
                                @php $crItems = array_values(array_filter(@$auction->get->cryptocurrency, fn($v) => \App\Helpers\ListingDisplayHelper::hasValue($v))); @endphp
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Cryptocurrency Terms</h6></div>
                                @if (count($crItems) === 1)
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ $crItems[0] }}</span></div>
                                @else
                                    @foreach ($crItems as $cr)
                                    <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $cr }}</span></div>
                                    @endforeach
                                @endif
                                @endif

                                @if (in_array('NFT', $buyerFinancingOriginal) && !empty(@$auction->get->nft))
                                @php $nfItems = array_values(array_filter(@$auction->get->nft, fn($v) => \App\Helpers\ListingDisplayHelper::hasValue($v))); @endphp
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">NFT Terms</h6></div>
                                @if (count($nfItems) === 1)
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ $nfItems[0] }}</span></div>
                                @else
                                    @foreach ($nfItems as $nf)
                                    <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $nf }}</span></div>
                                    @endforeach
                                @endif
                                @endif

                                @if (in_array('Prepayment Penalty', $buyerFinancingOriginal))
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Prepayment Penalty</h6></div>
                                @if (@$auction->get->prepayment == 'Yes' && !empty(@$auction->get->prepaymentOther))
                                    @foreach (@$auction->get->prepaymentOther as $pp)
                                        @if (\App\Helpers\ListingDisplayHelper::hasValue($pp))
                                        <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $pp }}</span></div>
                                        @endif
                                    @endforeach
                                @elseif (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->prepayment))
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ @$auction->get->prepayment }}</span></div>
                                @endif
                                @endif

                                @if (in_array('Balloon Payment', $buyerFinancingOriginal))
                                <div class="col-12 mt-3 mb-1"><h6 class="fw-bold">Balloon Payment</h6></div>
                                @if (@$auction->get->balloon == 'Yes' && !empty(@$auction->get->balloonpyment))
                                    @foreach (@$auction->get->balloonpyment as $bp)
                                        @if (\App\Helpers\ListingDisplayHelper::hasValue($bp))
                                        <div class="col-md-12 col-12 pt-1"><span class="badge bg-secondary">{{ $bp }}</span></div>
                                        @endif
                                    @endforeach
                                @elseif (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->balloon))
                                    <div class="col-md-12 col-12 pt-1"><span class="removeBold">{{ @$auction->get->balloon }}</span></div>
                                @endif
                                @endif

                                </div>
                            @endif
                            @if (@$auction->get->escrow_amount != null)
                                <div class="row align-items-center">
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Escrow
                                        Amount Offered:
                                        <span
                                            class="badge bg-secondary removeBold">{{ @$auction->get->escrow_amount }}</span>
                                    </div>
                                </div>
                            @endif
                            @if (
                                @$auction->get->contingencies != '' &&
                                    @$auction->get->contingencies != null &&
                                    @$auction->get->contingencies != 'null')
                                @php
                                    $contData = @$auction->get->contingencies;
                                @endphp
                                <div class="row align-items-center">
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Offered
                                        Contingencies:
                                        @if ($contData != null)
                                            @foreach (array_filter($contData) as $key => $item)
                                                <span style="font-size: 16px; margin-top:5px; display: block"
                                                    class="removeBold">&nbsp; {{ $item }}</span>
                                                @if ($auction->get->contingenciesOpt !== null)
                                                    <span
                                                        class="d-inline-block bg-secondary removeBold text-white  px-2 rounded my-1">
                                                        {{ $auction->get->contingenciesOpt }}
                                                    </span>                                                
                                                @elseif (isset($auction->get->contingenciesOptRes) && $auction->get->contingenciesOptRes !== null)
                                                    <span
                                                        class="d-inline-block bg-secondary removeBold text-white  px-2 rounded my-1">
                                                        {{ $auction->get->contingenciesOptRes }}
                                                    </span>  
                                                @else
                                                    @if ($auction->get->contingenciesOffered !== null)
                                                        <span
                                                            class="d-inline-block bg-secondary removeBold text-white  px-2 rounded my-1">
                                                            {{ $auction->get->contingenciesOffered }}</span>
                                                    @endif
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endif
                            <div class="row align-items-center">
                                @if ($auction->get->closeDays !== null)
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Days
                                        Needed to Close:</span>
                                        <span class="badge bg-secondary removeBold">{{ @$auction->get->closeDays }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="row align-items-center">
                                @if ($auction->get->idealDate !== null)
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Ideal
                                        Closing Date:
                                        <span class="removeBold">{{ @$auction->get->idealDate }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="row align-items-center">
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Requested
                                    Buyer’s Credit at Closing From Seller:
                                    @if ($auction->get->creditRes !== null)
                                        <span class="badge bg-secondary removeBold">{{ @$auction->get->creditRes }}</span>
                                    @endif
                                </div>
                                @if ($auction->get->creditRes !== null && $auction->get->creditRes == 'Yes')
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                        Requested Buyer’s Credit Amount from Seller at Closing:
                                        <span
                                            class="badge bg-secondary removeBold">{{ @$auction->get->creditOptYes }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="row align-items-center Listing Information mt-4">
                            <h5>Listing Information:</h5>
                            @if (@$auction->get->cities != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Cities:
                                    @if (gettype(@$auction->get->cities) == 'array')
                                        @foreach (@$auction->get->cities as $city)
                                            <span class="badge bg-secondary removeBold">{{ \App\Helpers\ListingDisplayHelper::stripStateSuffix($city) }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->counties != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Counties:
                                    @if (gettype(@$auction->get->counties) == 'array')
                                        @foreach (@$auction->get->counties as $county)
                                            <span class="badge bg-secondary removeBold">{{ \App\Helpers\ListingDisplayHelper::stripStateSuffix($county) }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->states != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> States:
                                    @if (gettype(@$auction->get->states) == 'array')
                                        @foreach (@$auction->get->states as $city)
                                            <span class="badge bg-secondary removeBold">{{ $city }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->listing_date != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Listing
                                    Date:
                                    <span
                                        class="removeBold">{{ Carbon\Carbon::parse(@$auction->get->listing_date)->format('m-d-Y') }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->expiration_date != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Listing
                                    Expiration Date:
                                    <span
                                        class="removeBold">{{ Carbon\Carbon::parse(@$auction->get->expiration_date)->format('m-d-Y') }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->service_type != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Listing
                                    Service Type:
                                    <span class="removeBold">{{ @$auction->get->service_type }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->representation != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Representation:
                                    <span class="removeBold">{{ @$auction->get->representation }}</span>
                                </div>
                            @endif
                        </div>
                        @if (@$auction->get->titleListing == 'Income Property' || @$auction->get->titleListing == 'Commercial Property')
                            <div class="row align-items-center Listing Information mt-4">
                                @if (@$auction->get->titleListing == 'Income Property')
                                    <h5>Income Property Criteria:</h5>
                                @else
                                    <h5>Preferred Commercial Property Criteria:</h5>
                                @endif
                                @if (@$auction->get->total_number_of_units != null && @$auction->get->titleListing == 'Income Property')
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                        Minimum Total Number of Units Needed:
                                        <span class="removeBold">{{ @$auction->get->total_number_of_units }}</span>
                                    </div>
                                @endif
                                @php
                                    $unitSizePills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->unit_size, @$auction->get->otherBuyerIncome);
                                @endphp
                                @if (!empty($unitSizePills) && @$auction->get->titleListing == 'Income Property')
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Unit
                                        sizes that the buyer is interested in purchasing:
                                        @foreach ($unitSizePills as $item)
                                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->minimum_annual_net_income))
                                    <div class="col-md-12 col-12 fw-bold">
                                        Minimum Annual Net Income:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::fmtMoney(@$auction->get->minimum_annual_net_income) }}</span>
                                    </div>
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->minimum_cap_rate))
                                    <div class="col-md-12 col-12 fw-bold">
                                        Minimum Cap Rate:
                                        <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::fmtPercent(@$auction->get->minimum_cap_rate) }}</span>
                                    </div>
                                @endif
                                @if (@$auction->get->titleListing == 'Commercial Property')
                                    @php
                                        $includedItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->included_in_sale, @$auction->get->licenseOther);
                                    @endphp
                                    @if (!empty($includedItems))
                                    <div class="col-md-12 col-12 fw-bold">
                                        Included in Sale:
                                        @foreach ($includedItems as $item)
                                            <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                    @endif
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details))
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                        Additional Details:
                                        <span class="removeBold">{{ @$auction->get->additional_details }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div class="row align-items-center Listing Information mt-4">
                            <h5>Desired Features:</h5>
                            @php
                                $buyerPropertyStyles = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->property_items);
                            @endphp
                            @if (!empty($buyerPropertyStyles))
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                    <span class="fw-bold">Acceptable Property Styles:</span>
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->property_type))
                                        <span class="removeBold">({{ \App\Helpers\ListingDisplayHelper::normalizePropertyType(@$auction->get->property_type) }})</span>
                                    @endif
                                    <span class="removeBold">{{ implode(', ', $buyerPropertyStyles) }}</span>
                                </div>
                            @endif
                            @php
                                $specialSaleItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->special_sales);
                            @endphp
                            @if (!empty($specialSaleItems))
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Acceptable Special Sales Provisions:
                                    @foreach ($specialSaleItems as $ssItem)
                                        <span class="badge bg-secondary removeBold">{{ $ssItem }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($auction->get->property_type !== 'Vacant Land')
                                @php
                                    $conditionItems = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->prop_condition, @$auction->get->propConditionOther);
                                @endphp
                                @if (!empty($conditionItems))
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Acceptable Property Conditions:
                                    @foreach ($conditionItems as $cItem)
                                        <span class="d-inline-block badge bg-secondary removeBold">{{ $cItem }}</span>
                                    @endforeach
                                </div>
                                @endif
                            @endif
                            @if (@$auction->get->bedrooms && @$auction->get->bedrooms != null && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum
                                    Bedrooms Needed:
                                    <span
                                        class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->bedrooms }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->bathrooms && ($auction->get->bathrooms ?? $auction->get->bathroomsRes) != null && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum
                                    Bathrooms Needed:
                                    @if (@$auction->get->bathrooms != null)
                                        <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->bathrooms }}</span>
                                    @elseif (@$auction->get->bathroomsRes != null)    
                                        <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->bathroomsRes }}</span>
                                    @endif
                                    
                                </div>
                            @endif
                            @if (@$auction->get->min_sqft && ($auction->get->min_sqft ?? $auction->get->min_sqftRes) != null && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum
                                    Heated SqFt Needed:
                                    @if ($auction->get->min_sqft != null)
                                        <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->min_sqft }}</span>
                                    @elseif ($auction->get->min_sqftRes != null)
                                        <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->min_sqftRes }}</span>
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->minimum_total_acreage_needed && @$auction->get->minimum_total_acreage_needed != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum
                                    Total Acreage Needed:
                                    <span
                                        class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->minimum_total_acreage_needed }}</span>
                                </div>
                            @endif
                            @php
                                $acVal = @$auction->get->air_conditioning;
                                $acDisplay = ($acVal && strtolower($acVal) === 'other' && \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->otherAirCondition)) ? @$auction->get->otherAirCondition : $acVal;
                            @endphp
                            @if (\App\Helpers\ListingDisplayHelper::hasValue($acDisplay) && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Air
                                    Conditioning Needed:
                                    <span class="d-inline-block badge bg-secondary removeBold">{{ $acDisplay }}</span>
                                </div>
                            @endif
                            @php
                                $hfVal = @$auction->get->heating_and_fuel;
                                $hfDisplay = ($hfVal && strtolower($hfVal) === 'other' && \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->otherFuel)) ? @$auction->get->otherFuel : $hfVal;
                            @endphp
                            @if (\App\Helpers\ListingDisplayHelper::hasValue($hfDisplay) && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Heating and Fuel Needed:
                                    <span class="d-inline-block badge bg-secondary removeBold">{{ $hfDisplay }}</span>
                                </div>
                            @endif
                            @php
                                $garageVal = @$auction->get->garage ?? @$auction->get->garage_Res;
                                $garageSpaces = @$auction->get->garage_spaces ?? @$auction->get->garage_spaces_Com;
                                if ($garageSpaces === 'Other' && \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->custom_garage)) {
                                    $garageSpaces = @$auction->get->custom_garage;
                                }
                            @endphp
                            @if (\App\Helpers\ListingDisplayHelper::hasValue($garageVal) && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold">
                                    Garage Needed:
                                    <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount($garageVal, $garageSpaces, 'Spaces') }}</span>
                                </div>
                            @endif
                            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->carport) && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold">
                                    Carport Needed:
                                    <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->carport, @$auction->get->carportOptYes, 'Spaces') }}</span>
                                </div>
                            @endif
                            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->pool) && $auction->get->property_type !== 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold">
                                    Pool Needed:
                                    <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesText(@$auction->get->pool, @$auction->get->poolOption) }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->lot_dimensions != null && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Lot
                                    Dimensions Needed:
                                    <span class="removeBold">{{ @$auction->get->lot_dimensions }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->lot_size_square_footage != null && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Lot Size
                                    Square Footage Needed:
                                    <span class="removeBold">{{ @$auction->get->lot_size_square_footage }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->front_footage != null && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Front
                                    Footage Needed:
                                    <span class="removeBold">{{ @$auction->get->front_footage }}</span>
                                </div>
                            @endif
                            @php $roadFrontagePills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->road_frontage, @$auction->get->otherFrontage); @endphp
                            @if (!empty($roadFrontagePills) && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Road
                                    Frontage Needed:
                                    @foreach ($roadFrontagePills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @php $roadSurfacePills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->road_surface_type, @$auction->get->othersurface); @endphp
                            @if (!empty($roadSurfacePills) && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Road
                                    Surface Type Needed:
                                    @foreach ($roadSurfacePills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @php $utilitiesPills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->utilities, @$auction->get->otherUtilities); @endphp
                            @if (!empty($utilitiesPills) && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Utilities
                                    Needed:
                                    @foreach ($utilitiesPills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @php $waterPills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->water, @$auction->get->otherWater); @endphp
                            @if (!empty($waterPills) && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Water
                                    Needed:
                                    @foreach ($waterPills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @php $sewerPills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->sewer, @$auction->get->otherSewer); @endphp
                            @if (!empty($sewerPills) && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Sewer
                                    Needed:
                                    @foreach ($sewerPills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @php $lotFeaturePills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->lot_features, @$auction->get->otherFeature); @endphp
                            @if (!empty($lotFeaturePills) && $auction->get->property_type == 'Vacant Land')
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Lot
                                    Features:
                                    @foreach ($lotFeaturePills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @php
                                $viewOptionVal = @$auction->get->viewOptions ?? @$auction->get->viewOptionsRes ?? @$auction->get->viewOptionsCom;
                                $viewPills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->view, @$auction->get->viewOther);
                            @endphp
                            @if (\App\Helpers\ListingDisplayHelper::hasValue($viewOptionVal))
                                <div class="col-md-9 fw-bold">
                                    <i class="fa-regular fa-check-square"></i> View Preference Needed:
                                    <span class="removeBold">({{ $viewOptionVal }})</span>
                                    @foreach ($viewPills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if (@$auction->get->has_water_access != null && @$auction->get->has_water_access != 'null')
                                <div class="col-md-9 fw-bold">
                                    <i class="fa-regular fa-check-square"></i> Water Access Needed:<span
                                        class="removeBold">({{ @$auction->get->has_water_access }})</span>
                                    @if (gettype(@$auction->get->water_access) == 'array' && @$auction->get->water_access != 'No')
                                        @foreach (@$auction->get->water_access as $item)
                                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->has_water_view != null && @$auction->get->has_water_view != 'null')
                                <div class="col-md-9 fw-bold">
                                    <i class="fa-regular fa-check-square"></i> Water View Needed:<span
                                        class="removeBold">({{ @$auction->get->has_water_view }})</span>
                                    @if (gettype(@$auction->get->water_view) == 'array' && @$auction->get->has_water_view != 'No')
                                        @foreach (@$auction->get->water_view as $item)
                                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->has_water_extra != null && @$auction->get->has_water_extra != 'null')
                                <div class="col-md-9 fw-bold">
                                    <i class="fa-regular fa-check-square"></i> Water Extras Needed:<span
                                        class="removeBold">({{ @$auction->get->has_water_extra }})</span>
                                    @if (gettype(@$auction->get->has_water_extra) == 'array' && @$auction->get->has_water_extra != 'No')
                                        @foreach (@$auction->get->has_water_extra as $item)
                                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->has_water_frontage != null && @$auction->get->has_water_frontage != 'null')
                                <div class="col-md-9 fw-bold">
                                    <i class="fa-regular fa-check-square"></i> Water Frontage Needed:<span
                                        class="removeBold">({{ @$auction->get->has_water_frontage }})</span>
                                    @if (gettype(@$auction->get->water_frontage) == 'array' && @$auction->get->water_frontage != 'No')
                                        @foreach (@$auction->get->water_frontage as $item)
                                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->has_dock))
                                @php $dockPills = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->dock, @$auction->get->dockDescription); @endphp
                                <div class="col-md-9 fw-bold">
                                    <i class="fa-regular fa-check-square"></i> Dock Needed:<span
                                        class="removeBold">({{ @$auction->get->has_dock }})</span>
                                    @foreach ($dockPills as $item)
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->petOptions))
                                <div class="col-md-12 col-12 fw-bold">
                                    Pets:
                                    <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->petOptions, @$auction->get->petsNumber) }}</span>
                                </div>
                                @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->petOptions))
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->petsType))
                                        <div class="col-md-12 col-12 fw-bold">
                                            Pet Types:
                                            <span class="removeBold">{{ @$auction->get->petsType }}</span>
                                        </div>
                                    @endif
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->pet_breed))
                                        <div class="col-md-12 col-12 fw-bold">
                                            Breed:
                                            <span class="removeBold">{{ @$auction->get->pet_breed }}</span>
                                        </div>
                                    @endif
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->petsWeight))
                                        <div class="col-md-12 col-12 fw-bold">
                                            Weight:
                                            <span class="removeBold">{{ @$auction->get->petsWeight }} lbs</span>
                                        </div>
                                    @endif
                                @endif
                            @endif
                            @if (@$auction->get->communitiesOption != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Buyer
                                    Eligibility/Interest in Purchasing in 55-and- Over Communities:
                                    <span class="removeBold">{{ @$auction->get->communitiesOption }}</span>
                                </div>
                            @endif

                            @if (@$auction->get->buyer_intrest_in_purchasing != null)
                                <div class="col-md-12 col-12  fw-bold"><i class="fa-regular fa-check-square"></i> Buyer's
                                    Interest in Purchasing Property in an HOA/Condo/Community Association:
                                    <span
                                        class="badge bg-secondary removeBold">{{ @$auction->get->buyer_intrest_in_purchasing }}</span>
                                </div>
                                @if (@$auction->get->buyer_intrest_in_purchasing == 'Yes')
                                    @if (@$auction->get->maximum_monthly_condo !== null)
                                        <div class="col-md-12 col-12  fw-bold"><i class="fa-regular fa-check-square"></i>
                                            Maximum Monthly Condo and/or HOA Fee:
                                            <span
                                                class="badge bg-secondary removeBold">{{ @$auction->get->maximum_monthly_condo }}</span>
                                        </div>
                                    @endif
                                @endif
                            @endif
                        </div>
                        <div class="row align-items-center Non-Negotiable mt-4">
                            @if (($auction->get->nonNegotiableFactors ?? $auction->get->nonNegotiableFactorsRes) != null)
                                <h5>Non-Negotiable Amenities or Property Features:</h5>
                                <div class="col-md-12 col-12  fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Non-Negotiable Amenities or Property Features:
                                    @if ($auction->get->nonNegotiableFactors != null)
                                        <span class="badge bg-secondary removeBold">{{ @$auction->get->nonNegotiableFactors }}</span>
                                    @elseif ($auction->get->nonNegotiableFactorsRes != null)
                                        <span class="badge bg-secondary removeBold">{{ @$auction->get->nonNegotiableFactorsRes }}</span>
                                    @endif
                                    @if (($auction->get->nonNegotiableFactors || $auction->get->nonNegotiableFactorsRes) == 'Yes')
                                    @if (is_array(@$auction->get->nonNegotiable) && @$auction->get->nonNegotiable && @$auction->get->nonNegotiable !== null)
                                        @foreach ( @$auction->get->nonNegotiable as $item)
                                            <span class="d-inline-block badge bg-secondary removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                @endif
                                </div>
                            @endif
                        </div>
                        <div class="row align-items-center Buyer’s Agent Representation mt-4">
                            <h5>Buyer’s Agent Representation:</h5>
                            @if (isset($auction->get->buyerHaveAgentRepresentation) || isset($auction->get->buyerHaveAgentRepresentationRes) || isset($auction->get->buyerHaveAgentRepresentationCom))
                                <div class="row">
                                    <div class="col-md-12">
                                        <i class="fa-regular fa-check-square"></i>
                                        <span class="fw-bold">Buyer Represented by a Real Estate Agent:</span>
                                        @if ($auction->get->buyerHaveAgentRepresentation != null)
                                            <span class="removeBold">({{ $auction->get->buyerHaveAgentRepresentation }})</span>
                                        @elseif ($auction->get->buyerHaveAgentRepresentationRes != null)
                                            <span class="removeBold">({{ $auction->get->buyerHaveAgentRepresentationRes }})</span>
                                        @elseif ($auction->get->buyerHaveAgentRepresentationCom != null)
                                            <span class="removeBold">({{ $auction->get->buyerHaveAgentRepresentationCom }})</span>
                                        @endif
                                            
                                        @if (($auction->get->buyerHaveAgentRepresentation || $auction->get->buyerHaveAgentRepresentationRes || $auction->get->buyerHaveAgentRepresentationCom) == 'Yes' && 
                                                ($auction->get->buyersAgentCommissionRequested || $auction->get->buyersAgentCommissionRequestedRes || $auction->get->buyersAgentCommissionRequestedCom) !== null)
                                            <div class="col-md-12">
                                                <i class="fa-regular fa-check-square"></i>
                                                <span class="fw-bold">Buyer Requests Seller to Pay Buyer’s Agent Commission:</span>
                                                @if ($auction->get->buyersAgentCommissionRequested != null)
                                                    <span class="removeBold">({{ $auction->get->buyersAgentCommissionRequested }})</span>
                                                @elseif ($auction->get->buyersAgentCommissionRequestedRes != null)
                                                    <span class="removeBold">({{ $auction->get->buyersAgentCommissionRequestedRes }})</span>
                                                @elseif ($auction->get->buyersAgentCommissionRequestedCom != null)
                                                    <span class="removeBold">({{ $auction->get->buyersAgentCommissionRequestedCom }})</span>   
                                                @endif
                                            </div>
                                            @if (($auction->get->buyersAgentCommissionRequested || $auction->get->buyersAgentCommissionRequestedRes || $auction->get->buyersAgentCommissionRequestedCom) == 'Yes' && 
                                                    ($auction->get->buyersAgentCompensationRequested || $auction->get->buyersAgentCompensationRequestedRes || $auction->get->buyersAgentCompensationRequestedCom) !== null)
                                                <div class="col-md-12">
                                                    <i class="fa-regular fa-check-square"></i>
                                                    <span class="fw-bold">What compensation is the buyer requesting the seller to pay to the buyer’s agent?</span>
                                                    @if ($auction->get->buyersAgentCompensationRequested != null)
                                                        <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequested }})</span>
                                                    @elseif ($auction->get->buyersAgentCompensationRequestedRes != null)
                                                        <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequestedRes }})</span>
                                                    @elseif ($auction->get->buyersAgentCompensationRequestedCom != null)
                                                        <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequestedCom }})</span>
                                                    @endif
                                                </div>
                                                @if (($auction->get->buyersAgentCompensationRequested || $auction->get->buyersAgentCompensationRequestedRes || $auction->get->buyersAgentCompensationRequestedCom) !== null &&
                                                        ($auction->get->buyersAgentCompensationRequested || $auction->get->buyersAgentCompensationRequestedRes || $auction->get->buyersAgentCompensationRequestedCom) == 'Other')
                                                    <div class="col-md-12">
                                                        <i class="fa-regular fa-check-square"></i>
                                                        <span class="fw-bold">Requested Amount for Seller to Pay Buyer’s Agent Commission:</span>
                                                        @if ($auction->get->buyersAgentCompensationRequested != null)
                                                            <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequested }})</span>
                                                        @elseif ($auction->get->buyersAgentCompensationRequestedRes != null)
                                                            <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequestedRes }})</span>
                                                        @elseif ($auction->get->buyersAgentCompensationRequestedCom != null)
                                                            <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequestedCom }})</span>   
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif
                                            @if (($auction->get->buyersAgentCommissionRequested == 'No' || $auction->get->buyersAgentCommissionRequestedRes == 'No' || $auction->get->buyersAgentCommissionRequestedCom == 'No') && 
                                                    ($auction->get->buyersAgentCompensationNotOffered || $auction->get->buyersAgentCompensationNotOfferedRes || $auction->get->buyersAgentCompensationNotOfferedCom) != null)
                                                <div class="col-md-12">
                                                    <i class="fa-regular fa-check-square"></i>
                                                    <span class="fw-bold">Agent Compensation if Not Offered by Seller:</span>
                                                    @if ($auction->get->buyersAgentCompensationNotOffered)
                                                        <span class="removeBold">({{ $auction->get->buyersAgentCompensationNotOffered }})</span>
                                                    @elseif ($auction->get->buyersAgentCompensationNotOfferedRes)
                                                        <span class="removeBold res">({{ $auction->get->buyersAgentCompensationNotOfferedRes }})</span>
                                                    @elseif ($auction->get->buyersAgentCompensationNotOfferedCom)
                                                        <span class="removeBold com">({{ $auction->get->buyersAgentCompensationNotOfferedCom }})</span> 
                                                    @endif
                                                </div>
                                                @if (($auction->get->buyersAgentCompensationNotOffered || $auction->get->buyersAgentCompensationNotOfferedRes || $auction->get->buyersAgentCompensationNotOfferedCom) == 'Yes')
                                                    <div class="col-md-12">
                                                        <i class="fa-regular fa-check-square"></i>
                                                        <span class="fw-bold">What compensation is the buyer willing to offer to an agent?</span>
                                                        @if ($auction->get->buyersAgentCompensationNotOffered != null)
                                                            <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequested }})</span>
                                                        @elseif ($auction->get->buyersAgentCompensationNotOfferedRes != null)
                                                            <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequestedRes }})</span>
                                                        @elseif ($auction->get->buyersAgentCompensationNotOfferedCom != null)
                                                            <span class="removeBold">({{ $auction->get->buyersAgentCompensationRequestedCom }})</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="row align-items-center buyer_currently_selling mt-4">
                            @if (isset($auction->get->BuyerSellingProperty) || isset($auction->get->BuyerSellingPropertyRes) || isset($auction->get->BuyerSellingPropertyCom))
                                <h5>Is the buyer currently selling a property?</h5>
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Is the
                                    buyer currently selling a property?
                                    @if ($auction->get->BuyerSellingProperty != null)
                                        <span class="removeBold">{{ @$auction->get->BuyerSellingProperty }}</span>
                                    @elseif ($auction->get->BuyerSellingPropertyRes != null)
                                        <span class="removeBold">{{ @$auction->get->BuyerSellingPropertyRes }}</span>
                                    @elseif ($auction->get->BuyerSellingPropertyCom != null)
                                        <span class="removeBold">{{ @$auction->get->BuyerSellingPropertyCom }}</span> 
                                    @endif
                                </div>
                                @if (($auction->get->BuyerSellingProperty || $auction->get->BuyerSellingPropertyRes || $auction->get->BuyerSellingPropertyCom) == 'Yes')
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-solid fa-link"></i> Link to the
                                        Property listing on Bid Your Offer:
                                        @if ($auction->get->linkToThePropertyListing != null)
                                            <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->linkToThePropertyListing }}</span>
                                        @elseif ($auction->get->linkToThePropertyListingRes != null)
                                            <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->linkToThePropertyListingRes }}</span>
                                        @elseif ($auction->get->linkToThePropertyListingCom != null)
                                            <span class="d-inline-block badge bg-secondary removeBold">{{ @$auction->get->linkToThePropertyListingCom }}</span> 
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>



                        <div class="row" style="flex-wrap: wrap;">
                            {{-- @if (@$auction->get->max_price != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Maximum Price:
                                  <span class="removeBold">{{ @$auction->get->max_price }}</span>
                                </div>
                              @endif --}}
                            {{-- @if (@$auction->get->custom_financings != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Custom financing:
                                  {{ @$auction->get->custom_financings }}</div>
                              @endif --}}

                            {{-- @if (@$auction->get->contingencies != null && @$auction->get->contingencies != 'null')
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                  <span class="fw-bold">Acceptable Property Conditions:</span><br>
                                  @php
                                    $elementToIgnore = 'Other';
                                    $originalArray = $auction->get->contingencies;
                                    $index = array_search($elementToIgnore, $originalArray);
                                    if ($index !== false) {
                                        array_splice($originalArray, $index, 1);
                                    }
                                  @endphp
                                  @foreach (@$originalArray as $item)
                                    <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                  @endforeach
                                  @php $exists = in_array('Other', $auction->get->contingencies); @endphp
                                  @if ($exists)
                                    <span class="badge bg-secondary removeBold"> {{ @$auction->get->contingenciesOffered }}</span>
                                  @endif
                                </div>
                              @endif --}}
                            {{-- @if (@$auction->get->real_estate_included != null)
                                <div class="col-md-12
                                      col-12 removeBold"><i
                                    class="fa-regular fa-check-square"></i><span class="fw-bold">
                                      Real Estate Included:</span>
                                  {{ @$auction->get->real_estate_included }}
                                </div>
                              @endif --}}
                            {{-- @if (@$auction->get->lincenses != (null || 'null'))
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span class="fw-bold">
                                      Licenses Needed:</span>
                                      @if (is_array($auction->get->lincenses))
                                          @foreach ($auction->get->lincenses as $item)
                                              @if ($item == 'Other')
                                                  @continue
                                              @else
                                              <span class="badge bg-secondary">{{ $item }}</span>
                                              @endif
                                          @endforeach
                                      @endif
                                </div>
                              @endif --}}

                            {{-- @if (@$auction->get->prop_conditions != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable Property
                                  Conditions:
                                  @if (gettype(@$auction->get->prop_conditions) == 'array')
                                    @foreach (@$auction->get->prop_conditions as $item)
                                      <span class="badge bg-secondary">{{ $item }}</span>
                                    @endforeach
                                  @endif
                                </div>
                              @endif --}}

                            {{-- @if (@$auction->get->custom_bedrooms != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Bedrooms
                                  Needed:
                                  {{ @$auction->get->custom_bedrooms == '' ? @$auction->get->bedrooms : @$auction->get->custom_bedrooms }}
                                </div>
                              @endif --}}
                            {{-- @if (@$auction->get->bathrooms != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Bathrooms
                                  Needed:
                                  <span class="removeBold">{{@$auction->get->bathrooms?@$auction->get->bathrooms :@$auction->get->custom_bathrooms}}</span>
                                </div>
                              @endif --}}
                            {{-- @if (@$auction->get->garage != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Garage/Parking Features Needed:
                                  <span class="removeBold">
                                    @if (@$auction->get->garage != 'Yes')
                                    {{ @$auction->get->garage }}
                                    @elseif(gettype(@$auction->get->garage) == 'array')
                                    @foreach (@$auction->get->garage as $item)
                                      @if ($item != 'Other')
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                      @else
                                          <span class="badge bg-secondary removeBold">{{ @$auction->get->garageNeedOther }}</span>
                                      @endif
                                      @endforeach
                                    @endif
                                  </span>
                                </div>
                              @endif --}}
                            {{-- @if (@$auction->get->escrow_amount_percent != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Offered Escrow
                                  Amount(% or $): {{ @$auction->get->escrow_amount_percent }}</div>
                              @endif
                              @if (@$auction->get->inspection != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Inspection Period
                                  Offered: {{ @$auction->get->inspection }}</div>
                              @endif
                              @if (@$auction->get->request_seller_premium != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Requested seller
                                  credit at closing (seller's premium): {{ @$auction->get->request_seller_premium }}</div>
                              @endif
                              @if (@$auction->get->request_buyer_premium != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Requested Buyer
                                  Credit Seller at closing (Buyer’s Premium): {{ @$auction->get->request_buyer_premium }}
                                </div>
                              @endif
                              
                              @if (@$auction->get->parking_spaces_needed != null)
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Parking spaces
                                  needed? {{ @$auction->get->parking_spaces_needed }}</div>
                              @endif --}}
                            <div>
                                {{-- @if (@$auction->get->nonNegotiableFactors != null && @$auction->get->nonNegotiableFactors != 'null')
                                <div class="col-md-9 fw-bold">
                                  <i class="fa-regular fa-check-square"></i> Non-negotiable Amenities or Property Features:<span class="removeBold">({{@$auction->get->nonNegotiableFactors}})</span><br>
                                  @if (gettype(@$auction->get->nonNegotiable) == 'array' && @$auction->get->nonNegotiableFactors != 'No')
                                    @foreach (@$auction->get->nonNegotiable as $item)
                                      @if ($item != 'Other')
                                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                                      @else
                                        <span class="badge bg-secondary removeBold">{{ @$auction->get->negotiableOther }}</span>
                                      @endif
                                    @endforeach
                                  @endif
                                </div>
                              @endif --}}
                            </div>
                            {{-- @if (@$auction->get->have_air_conditioning != null)
                                <div class="row">
                                  <div class="col-md-12">
                                    <i class="fa-regular fa-check-square"></i>
                                    <span class="fw-bold">Air Conditioning Preferences and Heating and Fuel Preferences:</span>
                                    @if (@$auction->get->have_air_conditioning == 'Yes')
                                      <span class="badge bg-secondary removeBold">{{ @$auction->get->air_conditioning }}</span>
                                      <span class="badge bg-secondary removeBold">{{ @$auction->get->heating_and_fuel }}</span>
                                    @else
                                      <span class="removeBold">{{ @$auction->get->have_air_conditioning }}</span>
                                    @endif
                                  </div>
                                </div>
                              @endif --}}

                            {{-- @if ($auction->get->escrow_amount != null && $auction->get->escrow_amount != '')
                              <div class="row">
                                <div class="col-md-12">
                                  <i class="fa-regular fa-check-square"></i>
                                  <span class="fw-bold">Offered Escrow Amount:</span><span class="removeBold">({{ $auction->get->escrow_amount }})</span>
                                </div>
                              </div>
                              @endif --}}



                            {{-- @if (@$auction->get->hoa_community != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> HOA/Community
                                  Association: {{ @$auction->get->hoa_community }} </div>
                              @endif
                              @if (@$auction->get->hoa_fee_requirement != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> HOA Fee Requirement:
                                  {{ @$auction->get->hoa_fee_requirement }} </div>
                              @endif
                              @if (@$auction->get->hoa_fee != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Maximum HOA fee
                                  (Monthly rate): ${{ number_format(@$auction->get->hoa_fee) }} </div>
                              @endif
                              @if (@$auction->get->condo_fee != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Maximum Condo fee
                                  (Monthly rate): ${{ number_format(@$auction->get->condo_fee) }} </div>
                              @endif
                              @if (@$auction->get->old_persons_community != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> 55+ over community:
                                  {{ @$auction->get->old_persons_community }} </div>
                              @endif
                              @if (@$auction->get->pets_allowed != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Pets allowed:
                                  {{ @$auction->get->pets_allowed }} </div>
                              @endif
                              @if (@$auction->get->number_of_pets != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> How many pets does
                                  the Buyer have? {{ @$auction->get->number_of_pets }} </div>
                              @endif
                              @if (@$auction->get->pet_bread != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> What is the breed?
                                  {{ @$auction->get->pet_bread }} </div>
                              @endif
                              @if (@$auction->get->pet_weight != null)
                                <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> What is the weight?
                                  {{ @$auction->get->pet_weight }} </div>
                              @endif --}}

                            {{-- <hr>
                              <h4>Investment Information</h4>
                              <div class="row" style="flex-wrap: wrap;">
                                @if (@$auction->get->rental_requirements != null)
                                  <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Rental
                                    Requirements:<br>
                                    {{ @$auction->get->rental_requirements }}
                                  </div>
                                @endif
                                @if (@$auction->get->total_units_needed != null)
                                  <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Total Number of
                                    Units Needed: {{ @$auction->get->total_units_needed }} </div>
                                @endif
                                @if (@$auction->get->annual_income != null)
                                  <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Annual Net Income
                                    Minimum: ${{ @$auction->get->annual_income }} </div>
                                @endif
                                @if (@$auction->get->min_cap_rate != null)
                                  <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Minimum Cap Rate:
                                    ${{ @$auction->get->min_cap_rate }} </div>
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details))
                                  <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Additional Details:
                                    <span class="removeBold">{{ @$auction->get->additional_details }}</span>
                                  </div>
                                @endif
                                @if (@$auction->get->arv != null)
                                  <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> ARV:
                                    {{ @$auction->get->arv }} </div>
                                @endif --}}
                            {{-- </div> --}}
                            {{-- <hr>
                              <h4>Offered Title Company:</h4>
                              <div class="row" style="flex-wrap: wrap;">
                                @if (@$auction->get->title_company != null)
                                  <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Title Company:
                                    {{ @$auction->get->title_company }} </div>
                                @endif
                                @if (@$auction->get->title_agent != null)
                                  <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Title Agent:
                                    {{ @$auction->get->title_agent }} </div>
                                @endif
                                @if (@$auction->get->title_company_phone != null)
                                  <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Title Company
                                    Phone #: {{ @$auction->get->title_company_phone }} </div>
                                @endif
                                @if (@$auction->get->title_company_email != null)
                                  <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Title Company
                                    Email: {{ @$auction->get->title_company_email }} </div>
                                @endif

                              </div>
                              <div class="row" style="flex-wrap: wrap;">
                                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Days needed to
                                  Close: {{ @$auction->get->closing_days }} </div>
                              </div>
                            </div> --}}

                            {{-- <hr>
                                <h4>Description</h4>
                                @if (@$auction->get->description_buyer_specific != null)
                                  <div class="row">
                                    <div class="col-md-12"> {{ @$auction->get->description_buyer_specific }}
                                    </div>
                                  </div>
                                @endif
                                <hr> --}}


                            @if ($created_by->user_type == 'agent')
                                <h4 class="mt-4">Buyer’s Agent Information:</h4>
                            @elseif ($created_by->user_type == 'buyer')
                                <h4 class="mt-4">Buyer’s Information:</h4>
                            @endif

                            <div class="row" style="flex-wrap: wrap;">
                                @if (@$auction->get->agent_first_name != null)
                                    <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>
                                        First Name:
                                        <span class="removeBold"> {{ @$auction->get->agent_first_name }}</span>
                                    </div>
                                @endif
                                @if (@$auction->get->agent_last_name != null)
                                    <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Last
                                        Name:
                                        <span class="removeBold"> {{ @$auction->get->agent_last_name }}</span>
                                    </div>
                                @endif
                                @if (isset($auction->get->agent_phone) || isset($auction->get->agent_phoneRes) || isset($auction->get->agent_phoneCom))
                                    <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>
                                        Phone:
                                        @if ($auction->get->agent_phone != null)
                                            <span class="removeBold"> {{ @$auction->get->agent_phone }}</span>
                                        @elseif ($auction->get->agent_phoneRes != null)
                                            <span class="removeBold"> {{ @$auction->get->agent_phoneRes }}</span>
                                        @elseif ($auction->get->agent_phoneCom != null)
                                            <span class="removeBold"> {{ @$auction->get->agent_phoneCom }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if (@$auction->get->agent_email != null)
                                    <div class="col-md-12 col-12 fw-bold"> <i class="fa-regular fa-check-square"></i>
                                        Email:
                                        <span class="removeBold"> {{ @$auction->get->agent_email }}</span>
                                    </div>
                                @endif
                                @if (isset($auction->get->agent_brokerage) || isset($auction->get->agent_brokerageRes) || isset($auction->get->agent_brokerageCom))
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                        Brokerage:
                                        @if ($auction->get->agent_brokerage != null)
                                            <span class="removeBold">{{ @$auction->get->agent_brokerage }}</span>
                                        @elseif ($auction->get->agent_brokerageRes != null)
                                            <span class="removeBold">{{ @$auction->get->agent_brokerageRes }}</span>
                                        @elseif ($auction->get->agent_brokerageCom != null)
                                            <span class="removeBold">{{ @$auction->get->agent_brokerageCom }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if (@$auction->get->agent_license_no != null)
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                                        Real Estate License:
                                        <span class="removeBold">{{ @$auction->get->agent_license_no }}</span>
                                    </div>
                                @endif
                                @if (@$auction->get->agent_mls_id != null)
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> NAR
                                        Member ID (NRDS
                                        ID):
                                        <span class="removeBold">{{ @$auction->get->agent_mls_id }}</span>
                                    </div>
                                @endif
                                @if (@$auction->get->agent_commission_percent != null)
                                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Agent
                                        Commission:
                                        <span class="removeBold"> {{ @$auction->get->agent_commission_percent }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card review">
                    <div class="card-body d-flex align-items-center">
                        <div class="left d-flex align-items-center">
                            <img class="w-25" src="https://ppt1080.b-cdn.net/images/avatar/none.png" alt="">
                            <div>
                                <p class="mb-0"><a href="{{ route('author', [@$auction->user_id]) }}"><b>User
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
                                <p class="mb-0 opacity-50">{{ @$auction->user->name }} • last online 5 days ago.</p>
                            </div>
                        </div>
                        <div class="right text-center">
                            <a href="{{ route('author', [@$auction->user_id]) }}"><button
                                    class="btn">Message</button></a>
                            <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">View
                                    Profile</button></a>

                        </div>
                    </div>
                </div>
                <!-- End  -->
            </div>
            <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
                @if (@$auction->get->titleListing != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Title of the listing:
                        <h1>{{ @$auction->get->titleListing }}</h1>
                        {{-- <h1>{{ @$auction->get->property_type }}</h1> --}}
                    </div>
                @endif

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
                    $lowest_bid_price = @$auction->bids->min('price') ?? @$auction->max_price;
                    $lowest_bidder = @$auction->bids->where('price', $lowest_bid_price)->first();
                    $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
                @endphp
                @if (@$auction->user_id != $auth_id)
                    <a href="{{ route('auction-chat', ['buyer-criteria', $auction->id]) }}"
                        class="btn btn-success w-100 mb-2">
                        <i class="fa-solid fa-paper-plane"></i> Send Message</a>
                @endif
                @if ($auth_id)
                    @if (in_array(auth()->user()->user_type, ['seller', 'agent']))
                        <button class="btn w-100"
                            onclick="javascript:window.location='{{ route('criteria.auction.bid', @$auction->id) }}';"
                            {{ @$auction->user_id == $auth_id ? 'disabled' : '' }}>
                            <span class="bid">Bid Now </span>
                            <span class="badge bg-light float-end text-dark">{{ @$auction->get->max_price }}</span>
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
                        <form action="{{ route('criteria.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'show']) }}"  method="post">
                          @csrf
                          <button class="btn bg-success btn-sm px-3 mb-3 mt-0">Show Bids</button>
                        </form>
                      @else
                        <form action="{{ route('criteria.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'hide']) }}" method="post">
                          @csrf
                          <button class="btn bg-danger btn-sm px-3 mb-3 mt-0">Hide Bids</button>
                        </form>
                      @endif
                    </div>
                  @endif
                    <div class="card-body">
                        @if ($lowest_bidder)
                            <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p>
                        @else
                            <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">
                                @if ($auction->display_bids == 1 || $auction->user_id == auth()->user()->id)
                                    @foreach (@$auction->bids as $bid)
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
                                                    {{ $bid->get->max_price }} </div>
                                                <div class="col-2">
                                                    Terms↓
                                                </div>
                                            </div>
                                        </div>
                                        <div id="item{{ $bid->id }}" class="accordion-collapse collapse"
                                            aria-labelledby="headingOne" data-bs-parent="#accordionExample">

                                            <div class="accordion-body">
                                                <div id="bidding_history_data">
                                                    <div>
                                                        {{-- <p class="d-flex justify-content-between small">Cash or Financing Type:
                                                            <span>{{ $bid->financing->name }}</span>
                                                        </p> --}}
                                                        @if (isset($bid->get->max_price) && $bid->get->max_price != '')
                                                            <p class="d-flex justify-content-between small">Price:
                                                                <span>{{ $bid->get->max_price }}</span>
                                                            </p>
                                                        @endif
                                                        @if (isset($bid->get->term_financings) && $bid->get->term_financings != '')
                                                            <p class="d-flex justify-content-between align-items-center small">
                                                                Acceptable Currency/Financing:
                                                                <span class="badge bg-secondary">{{ $bid->get->term_financings }}</span>
                                                            </p>
                                                        @endif
                                                        @if (isset($bid->get->property_type) && $bid->get->property_type != '')
                                                            <p class="d-flex justify-content-between small">Property Type:
                                                                <span>{{ $bid->get->property_type }}</span>
                                                            </p>
                                                        @endif

                                                        @if (isset($bid->get->prop_condition) && $bid->get->prop_condition != '')
                                                            <p class="d-flex justify-content-between align-items-center small">
                                                                Property Condition:
                                                                <span class="badge bg-secondary">{{ $bid->get->prop_condition }}</span>
                                                            </p>
                                                        @endif

                                                        @if (isset($bid->get->special_sale) && $bid->get->special_sale != '')
                                                            <p class="d-flex justify-content-between align-items-center small">
                                                                Special Sale Provision:
                                                                <span class="badge bg-secondary">{{ $bid->get->special_sale }}</span>
                                                            </p>
                                                        @endif
                                                        @if (isset($bid->get->bedrooms) && $bid->get->bedrooms != '')
                                                            <p class="d-flex justify-content-between small">Bedrooms:
                                                                <span>{{ $bid->get->custom_bedrooms == '' ? $bid->get->bedrooms : $bid->get->custom_bedrooms }}</span>
                                                            </p>
                                                        @endif
                                                        @if (isset($bid->get->bathrooms) && $bid->get->bathrooms != '')
                                                            <p class="d-flex justify-content-between small">Bathrooms:
                                                                <span>{{ $bid->get->custom_bathrooms == '' ? $bid->get->bathrooms : $bid->get->custom_bathrooms }}</span>
                                                            </p>
                                                        @endif
                                                        @if (isset($bid->get->county) && $bid->get->county != '')
                                                            <p class="d-flex justify-content-between small">County:
                                                                <span>{{ $bid->get->county }}</span>
                                                            </p>
                                                        @endif
                                                        @if (isset($bid->get->total_sqft) && $bid->get->total_sqft != '')
                                                            <p class="d-flex justify-content-between small">Heated Sqft:
                                                                <span>{{ $bid->get->total_sqft }}</span>
                                                            </p>
                                                        @endif
                                                        {{-- @if ($bid->get->escrow_amount_percent != '')
                                                            <p class="d-flex justify-content-between small">Escrow Amount:$ or
                                                            %
                                                            <span>{{ $bid->get->escrow_amount_percent }}</span>
                                                            </p>
                                                        @endif --}}
                                                        {{-- @dd($bid->get) --}}
                                                        @if (isset($bid->get->inspection) && $bid->get->inspection != '')
                                                            <p class="d-flex justify-content-between small">Inspection Period
                                                                Offered:
                                                                <span>{{ $bid->get->inspection }}</span>
                                                            </p>
                                                        @endif

                                                        @if (isset($bid->get->contigencies_accepted_by_seller) && $bid->get->contigencies_accepted_by_seller != '')
                                                            <p class="d-flex justify-content-between small">Contingencies:
                                                                <span>{{ $bid->get->contigencies_accepted_by_seller }}</span>
                                                            </p>
                                                        @endif

                                                        {{-- @if ($bid->get->request_seller_premium != '')
                                                                <p class="d-flex justify-content-between small">Requested Seller's
                                                                Credit:
                                                                <span>{{ $bid->get->request_seller_premium }}</span>
                                                                </p>
                                                            @endif --}}

                                                                                        {{-- @if ($bid->get->request_buyer_premium != '')
                                                                <p class="d-flex justify-content-between small">Offered Buyer’s
                                                                Credit:
                                                                <span>{{ $bid->get->request_buyer_premium }}</span>
                                                                </p>
                                                            @endif --}}

                                                        @if (isset($bid->get->description) && $bid->get->description != '')
                                                            <p class="d-flex justify-content-between small">Description and
                                                                additional details:
                                                                <span>{{ $bid->get->description }}</span>
                                                            </p>
                                                        @endif

                                                        @if (isset($bid->get->lot_size) && $bid->get->lot_size != '')
                                                            <p class="d-flex justify-content-between small">Lot Size:
                                                                <span>{{ $bid->get->lot_size }}</span>
                                                            </p>
                                                        @endif

                                                        @if (isset($bid->get->flood_insurance) && $bid->get->flood_insurance != '')
                                                            <p class="d-flex justify-content-between small">Is flood insurance
                                                                required?
                                                                <span>{{ $bid->get->flood_insurance }}</span>
                                                            </p>
                                                        @endif

                                                        @if (isset($bid->get->agent_commission) && $bid->get->agent_commission != '')
                                                            <p class="d-flex justify-content-between small">Commission Offered
                                                                to Buyer's Agent: %
                                                                <span>{{ $bid->get->agent_commission }}</span>
                                                            </p>
                                                        @endif


                                                        {{-- @if ($bid->get->is_commercial == 'Yes')
                                                            <h5>Commercial Property Information:</h5>
                                                            @if ($bid->get->total_units != '')
                                                                <p class="d-flex justify-content-between small">Total Number of
                                                                    Units:
                                                                    <span>{{ $bid->get->total_units }}</span>
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->unit_sizes != '')
                                                                <p class="d-flex justify-content-between small">Unit sizes:
                                                                    <span>{{ $bid->get->unit_sizes }}</span>
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->annual_income != '')
                                                                <p class="d-flex justify-content-between small">Annual Net
                                                                    Income:
                                                                    <span>{{ $bid->get->annual_income }}</span>
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->cap_rate != '')
                                                                <p class="d-flex justify-content-between small">Cap Rate:
                                                                    <span>{{ $bid->get->cap_rate }}</span>
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->known_repairs != '')
                                                                <p class="d-flex justify-content-between small">Known Repairs
                                                                    that
                                                                    need to be done:
                                                                    <span>{{ $bid->get->known_repairs }}</span>
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->arv != '')
                                                                <p class="d-flex justify-content-between small">ARV:
                                                                    <span>{{ $bid->get->arv }}</span>
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->tenant_pays != '')
                                                                <p class="d-flex justify-content-between small">
                                                                    Tenant Pays:
                                                                    @if (gettype($bid->get->tenant_pays) == 'array')
                                                                        <div>
                                                                            @foreach ($bid->get->tenant_pays as $item)
                                                                                <span
                                                                                    class="badge bg-secondary">{{ $item }}</span>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </p>
                                                            @endif

                                                            @if ($bid->get->landlord_pays != '')
                                                                <p class="d-flex justify-content-between small">
                                                                    Landlord Pays:
                                                                    @if (gettype($bid->get->landlord_pays) == 'array')
                                                                        <div>
                                                                            @foreach ($bid->get->landlord_pays as $item)
                                                                                <span
                                                                                    class="badge bg-secondary">{{ $item }}</span>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </p>
                                                            @endif

                                                            <hr>
                                                        @endif --}}



                                                        @if (@$auction->user_id == $auth_id)
                                                            @if (@$bid->get->video_url !== null && @$bid->get->video_url != '')
                                                                <p>
                                                                    <a href="{{ @$bid->get->video_url }}"
                                                                        class="btn btn-sm btn-primary" target="_blank">View
                                                                        Video</a>
                                                                </p>
                                                            @endif

                                                            @if (@$bid->get->note !== null && @$bid->get->note != '')
                                                                <p>
                                                                    <a href="{{ url(@$bid->get->note) }}"
                                                                        class="btn btn-sm btn-primary" target="_blank">Proof
                                                                        of funds/pre-approval letter</a>
                                                                </p>
                                                            @endif

                                                            @if (@$bid->get->card !== null && @$bid->get->card != '')
                                                                <p>
                                                                    <a href="{{ asset(@$bid->get->card) }}" target="_blank">
                                                                        <img src="{{ asset(@$bid->get->card) }}"
                                                                            alt="" style="width: 150px;"></a>
                                                                </p>
                                                            @endif

                                                            @if (@$bid->get->audio !== null && @$bid->get->audio != '')
                                                                <p>
                                                                    <audio class="audio-fluid" controls style="width: 100%;">
                                                                        <source src="{{ asset(@$bid->get->audio) }}"
                                                                            type="audio/mp3">
                                                                        Your browser does not support the audio tag.
                                                                    </audio>
                                                                </p>
                                                            @endif
                                                        @endif




                                                        @if (@$auction->user_id == $auth_id)
                                                            @if (!@$auction->is_sold)
                                                                <form action="{{ route('acceptBCABid') }}" method="post">
                                                                    @csrf
                                                                    <input type="hidden" name="auction_id"
                                                                        value="{{ @$auction->id }}">
                                                                    <input type="hidden" name="bid_id"
                                                                        value="{{ $bid->id }}">
                                                                    <div style="text-align: right;">
                                                                        <button type="submit"
                                                                            class="btn btn-success btn-sm">Accept</button>
                                                                    </div>
                                                                </form>
                                                            @endif
                                                        @endif
                                                        @auth
                                                            @if (auth()->user()->id == $bid->user->id || (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin'))
                                                            <div class="form-group biddingOperations">
                                                                @if (!$auction->sold)
                                                                <form action="{{ route('buyer-criteria.add.counter-bid', $bid->id) }}" method="get">
                                                                    <div class="d-flex gap-1">
                                                                    <button type="submit" class="btn btn-primary">Counter Bid</button>
                                                                    </div>
                                                                </form>
                                                                @endif
                                                                @php
                                                                $allBids = App\Models\BuyerCriteriaAuctionBid::where('counter_id', $bid->id)->with('meta')
                                                                    ->orderByDesc('created_at')
                                                                    ->get();
                                                                @endphp
                                                                <div class="form-group">
                                                                    @foreach ($allBids as $key => $countBid)
                                                                        <form action="{{ route('agent.landlord.auction.bid.accept', $bid->id) }}" method="post">
                                                                            @csrf
                                                                            <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                                                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                                                        </form>
                                                                    @endforeach
                                                                </div>
                                                                <div class="form-group">
                                                                    {{-- @if (!$auction->sold)
                                                                        @foreach ($allBids as $key => $countBid)
                                                                        @if(isset($countBid->get->first_name))
                                                                            <p class="d-flex justify-content-between small fw-bold">First Name:
                                                                            <span
                                                                                class="removeBold">{{ $countBid->get->first_name }}</span>
                                                                            </p>
                                                                        @endif
                                                                        @if ($countBid->get->offered_price)
                                                                            <p class="d-flex justify-content-between small fw-bold">Offered Price:
                                                                            <span class="removeBold">{{ $countBid->get->offered_price }}</span>
                                                                            </p>
                                                                        @endif

                                                                        @if (isset($countBid->get->lease_terms))
                                                                            @php
                                                                            $data = json_decode($countBid->get->lease_terms, true);
                                                                            @endphp
                                                                            <p class="d-flex justify-content-between small fw-bold">Offered Lease Length:
                                                                            @if (isset($data) && is_array($data) && count($data) > 0)
                                                                                @foreach ($data as $item)
                                                                                <span class="removeBold">{{ $item !== 'Other' ? $item :  $countBid->get->price }}</span>
                                                                                @endforeach
                                                                            @endif
                                                                            </p>
                                                                        @endif

                                                                        @if ($countBid->get->start_date)
                                                                        <p class="d-flex justify-content-between small fw-bold">Offered Lease Start Date:
                                                                            <span class="removeBold">{{ $countBid->get->start_date }}</span>
                                                                        </p>
                                                                        @endif

                                                                        @if(isset($countBid->get->days_until_start_date))
                                                                        <p class="d-flex justify-content-between small fw-bold">Offered Days Until the Lease Start Date:
                                                                            <span class="removeBold">{{ $countBid->get->days_until_start_date }}</span>
                                                                        </p>
                                                                        @endif
                                                                        @if (isset($countBid->get->tenant_requests_commission_amount))
                                                                            <p class="d-flex justify-content-between small fw-bold">Real Estate Commission Offered:
                                                                            <span class="removeBold">{{ $countBid->get->tenant_requests_commission_amount !== 'Other' ? $countBid->get->tenant_requests_commission_amount : $countBid->get->tenant_requests_commission_amount_other }}</span>
                                                                            </p>
                                                                        @endif
                                                                        @if (isset($countBid->get->offer_expiry))
                                                                            <p class="d-flex justify-content-between small fw-bold">Offer Expires:
                                                                            <span class="removeBold">{{ $countBid->get->offer_expiry }}</span>
                                                                            </p>
                                                                        @endif
                                                                        @if (isset($countBid->get->additionalInfo))
                                                                            <p class="d-flex justify-content-between small fw-bold">Additional Details or Countered Terms:
                                                                            <span class="removeBold">{{ $countBid->get->additionalInfo }}</span>
                                                                            </p>
                                                                        @endif
                                                                        @if (@$auction->user_id == $auth_id)
                                                                            @if (!@$auction->is_sold)
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <form action="{{ route('agent.landlord.auction.bid.accept', $bid->id) }}" method="post">
                                                                                @csrf
                                                                                <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                                                                <input type="hidden" name="bid_id" value="{{ $countBid->id }}">
                                                                                @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                                                    <button type="submit" class="badge bg-success p-2 borderless">Accept</button>
                                                                                @endif
                                                                                </form>
                                                                            </div>
                                                                            @endif
                                                                        @endif
                                                                        @endforeach
                                                                    @endif --}}
                                                                </div>
                                                            </div>
                                                            @endif
                                                        @endauth
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- End  -->
                                    @endforeach
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa fa-user"></i> </span>
                </button>
                <!-- End  -->
                <!-- Social Details  -->
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('buyer.criteria.view', @$auction->id), 200) }}
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
                <!-- End  -->
            </div>
        </div>
    </div>
    <hr>
@endsection

@push('scripts')
    {{-- <script src="{{asset('assets/bootstrap-5.2.2/js/twitter-bootstrap.min.js')}}"></script> --}}
    <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
    @if (@$auction->auction_length > 0)
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
