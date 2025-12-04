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

    table .small {
        font-size: 14px;
    }

    .image {
        width: 200px;
    }

    .url {
        margin-left: 2px;
    }
</style>
@endpush

@section('content')
@php
$auth_id = auth()->user() ? auth()->user()->id : 0;
$meta = @$auction->meta;
@endphp
<!-- Gallery Start Here  -->
<div class="container listingDescription">
    <div class="row">
        <div class="col-sm-12 col-md-8 col-lg-8 leftCol">

            <!-- Description Box  -->
            <div class="card description">
                <div class="col-md-12 col-12 fw-bold image">
                    @php
                    $photos = json_decode(@$auction->info('photos'));
                    @endphp
                    @if (gettype($photos) == 'array')
                    @foreach ($photos as $photo)
                    <img src={{ asset($photo) }}>
                    @endforeach
                    @endif
                </div>
                <div class="col-md-12 col-12 fw-bold url"> Video Tour of property: {{ @$auction->info('video_url') }}
                </div>
                <hr>

                @if (@$auction->info('description'))
                <div class="card-header">
                    <h5>Description</h5>
                </div>
                @endif
                <div class="card-body">

                    @if (@$auction->info('description'))
                    <p>
                        {{ @$auction->info('description') }}
                    </p>
                    <hr>
                    @endif
                    <h5>Basic Information</h5>
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-md-12 col-12 fw-bold">Address: {{ @$auction->address }}</div>
                    </div>
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-md-12 col-12 fw-bold">County: {{ @$auction->county }}</div>
                    </div>
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-md-12 col-12 fw-bold">Listing Date:
                            {{ Carbon\Carbon::parse(@$auction->listing_date)->format('M d, Y') }}
                        </div>
                    </div>
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-md-12 col-12 fw-bold">Expiration Date:
                            {{ Carbon\Carbon::parse(@$auction->expiration_date)->format('M d, Y') }}
                        </div>
                    </div>
                    <hr>
                    <h5>Features</h5>
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-md-4 col-6 fw-bold"><i class="fa-solid fa-hotel"></i> Property Type:
                            {{ @$auction->info('property_type') }}
                        </div>
                        <div class="col-md-4 col-6 fw-bold"><i class="fa-solid fa-hotel"></i> Auction Type:
                            {{ @$auction->info('auction_type') }}
                        </div>
                        @if (@$auction->info('auction_length') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa-solid fa-hotel"></i> Auction Length:
                            {{ @$auction->info('auction_length') }}
                        </div>
                        @endif
                        @if (@$auction->info('bedrooms') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-bed"></i> Bedrooms:
                            {{ @$auction->info('bedrooms') }}
                        </div>
                        @endif
                        @if (@$auction->info('other_bedrooms') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-bed"></i> Other_Bedrooms:
                            {{ @$auction->info('other_bedrooms') }}
                        </div>
                        @endif
                        @if (@$auction->info('bathrooms') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-bath"></i> Bathrooms:
                            {{ @$auction->info('bathrooms') }}
                        </div>
                        @endif
                        @if (@$auction->info('other_bathrooms') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-bath"></i> Other_Bathrooms:
                            {{ @$auction->info('other_bathrooms') }}
                        </div>
                        @endif
                        @if (@$auction->info('year_built') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-calendar"></i> Year Built:
                            {{ @$auction->info('year_built') }}
                        </div>
                        @endif
                        @if (@$auction->info('heated_sqft') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-calculator"></i> Heated Sqft:
                            {{ @$auction->info('heated_sqft') }}
                        </div>
                        @endif
                        @if (@$auction->info('sqft_total') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-calculator"></i> Sqft Total:
                            {{ @$auction->info('sqft_total') }}
                        </div>
                        @endif
                        @if (@$auction->info('leasable_sqft') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-calculator"></i> Net Leaseable Sqft:
                            {{ @$auction->info('leasable_sqft') }}
                        </div>
                        @endif
                        @if (@$auction->info('lease_type') != null)
                        <div class="col-md-4 col-6 fw-bold"><i class="fa fa-calculator"></i> Lease Type:
                            {{ @$auction->info('lease_type') }}
                        </div>
                        @endif
                        {{-- <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Frequency in which
                                the Lease Amount is paid: {{ @$auction->info('lease_amount_type') }}
                    </div> --}}
                    @if (@$auction->info('lease_term') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Lease Terms:
                        {{ @$auction->info('lease_term') }}
                    </div>
                    @endif
                    @if (@$auction->info('custom_lease_term') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Custom Lease Term:
                        {{ @$auction->info('custom_lease_term') }}
                    </div>
                    @endif
                    @if (@$auction->info('monthly_lease_price') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Monthly Lease Price:
                        ${{ @$auction->info('monthly_lease_price') }}
                    </div>
                    @endif
                    @if (@$auction->info('commercial_price') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Price SF/YR (Commercial):
                        ${{ @$auction->info('commercial_price') }}
                    </div>
                    @endif
                    @if (@$auction->info('residential_price') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Leased Price per
                        Sqft(Residential):
                        ${{ @$auction->info('residential_price') }}
                    </div>
                    @endif
                    @if (@$auction->info('terms_of_lease') != null && @$auction->info('terms_of_lease') != 'null')
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Terms of Lease:
                        @if (@$auction->info('terms_of_lease') != '' && @$auction->info('terms_of_lease') != 'null')
                        @foreach (json_decode(@$auction->info('terms_of_lease')) as $term_of_lease)
                        <span class="badge bg-secondary">{{ $term_of_lease }}</span>
                        @endforeach
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('owner_pays') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Owner Pays:
                        @if (gettype(json_decode(@$auction->info('owner_pays'))) == 'array')
                        @if (@$auction->info('owner_pays') != '' && @$auction->info('owner_pays') != 'null')
                        @foreach (json_decode(@$auction->info('owner_pays')) as $owner_pays)
                        <span class="badge bg-secondary">{{ $owner_pays }}</span>
                        @endforeach
                        @endif
                        @else
                        <span class="badge bg-secondary">{{ @$auction->info('owner_pays') }}</span>
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('tenant_pays') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Tenant Pays:
                        @if (@$auction->info('tenant_pays') != '' && @$auction->info('tenant_pays') != 'null')
                        @foreach (json_decode(@$auction->info('tenant_pays')) as $tenant_pays)
                        <span class="badge bg-secondary">{{ $tenant_pays }}</span>
                        @endforeach
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('furnishings') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Furnishings:
                        {{ @$auction->info('furnishings') }}
                    </div>
                    @endif
                    @if (@$auction->info('allowed_tenants') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Max number of tenants allowed:
                        {{ @$auction->info('allowed_tenants') }}
                    </div>
                    @endif
                    @if (@$auction->info('pool') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Pool:
                        {{ @$auction->info('pool') }}
                    </div>
                    @endif
                    @if (@$auction->info('carport') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Carport:
                        {{ @$auction->info('carport') }}
                    </div>
                    @endif
                    @if (@$auction->info('garage') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Garage:
                        {{ @$auction->info('garage') }}
                    </div>
                    @endif
                    @if (@$auction->info('garage_spaces') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> How many garage
                        spaces? {{ @$auction->info('garage_spaces') }}
                    </div>
                    @endif
                    @if (@$auction->info('parking_spaces') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> How many parking
                        spaces are available? {{ @$auction->info('parking_spaces') }}
                    </div>
                    @endif
                    @if (@$auction->info('floors_in_property') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Floors In Property:
                        {{ @$auction->info('floors_in_property') }}
                    </div>
                    @endif
                    @if (@$auction->info('lot_size') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Lot Size:
                        {{ @$auction->info('lot_size') }}
                    </div>
                    @endif
                    @if (@$auction->info('has_water_view') != null && @$auction->info('has_water_view') != 'null')
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Water View:
                        {{ @$auction->info('has_water_view') }}
                    </div>
                    @endif
                    @if (@$auction->info('water_view') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Water View:
                        @if (@$auction->info('water_view') != '' && @$auction->info('water_view') != 'null')
                        @foreach (json_decode(@$auction->info('water_view')) as $water_view)
                        <span class="badge bg-secondary">{{ $water_view }}</span>
                        @endforeach
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('water_extras') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Water Extras:
                        @if (@$auction->info('water_extras') != '' && @$auction->info('water_extras') != 'null')
                        @foreach (json_decode(@$auction->info('water_extras')) as $water_extras)
                        <span class="badge bg-secondary">{{ $water_extras }}</span>
                        @endforeach
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('appliances') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Appliances Included:
                        @if (@$auction->info('appliances') != '' && @$auction->info('appliances') != 'null')
                        @foreach (json_decode(@$auction->info('appliances')) as $appliances)
                        <span class="badge bg-secondary">{{ $appliances }}</span>
                        @endforeach
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('ac_type') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Air Conditioning
                        Type: {{ @$auction->info('ac_type') }}
                    </div>
                    @endif
                    @if (@$auction->info('amenities') != null && @$auction->info('amenities') != 'null')
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Amenities:
                        @if (@$auction->info('amenities') != '' && @$auction->info('amenities') != 'null')
                        @foreach (json_decode(@$auction->info('amenities')) as $amenities)
                        <span class="badge bg-secondary">{{ $amenities }}</span>
                        @endforeach
                        @endif
                    </div>
                    @endif
                    @if (@$auction->info('application_fee_cost') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Application fee
                        cost:
                        ${{ @$auction->info('application_fee_cost') }}
                    </div>
                    @endif
                    @if (@$auction->info('listing_id') != null)
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> MLS ID #:
                        {{ @$auction->info('listing_id') }}
                    </div>
                    @endif

                    {{-- Nisar Changing start --}}
                    @if (@$auction->info('video'))
                    <div class="col-md-12 col-12 fw-bold url"> Video of property:
                        {{ @$auction->info('video') }}
                    </div>

                    <video width="320" height="240" controls>
                        <source src="{{ URL::asset(@$auction->info('video')) }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    @endif
                    {{-- End --}}

                </div>

                <hr>
                <h4>Landlord/Landlords Agent Info</h4>
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Name:
                        {{ @$auction->info('agent_name') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Brokerage:
                        {{ @$auction->info('agent_brokerage') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> License Number:
                        {{ @$auction->info('agent_license_no') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Phone:
                        {{ @$auction->info('agent_phone') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Email:
                        {{ @$auction->info('agent_email') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> MLD ID:
                        {{ @$auction->info('agent_mls_id') }}
                    </div>
                </div>

                <hr>
                <h4>Offered Price and Terms:</h4>
                <div class="row" style="flex-wrap: wrap;">

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Rental Price:
                        ${{ @$auction->info('offer_rental_price') }} </div>
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Lease Terms:
                        {{ @$auction->info('offer_lease_term') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Offered Move in
                        Date:
                        {{ @$auction->info('offer_move_date') }}
                    </div>


                </div>



                <div class="row" style="flex-wrap: wrap;">

                    @if (@$auction->info('public_private_contract_term') == 'Yes')
                    <hr>
                    <h4>If Landlord has an offer less than then Ideal price and terms what are the lowest
                        countered
                        terms Landlord will go (Lowest Terms Seller will accept):</h4>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Rental Price:
                        ${{ @$auction->info('offer_rental_price') }} </div>
                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Lease Terms:
                        {{ @$auction->info('special_offer_lease_term') }}
                    </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Security
                        Deposit:
                        ${{ @$auction->info('special_offer_security_deposit') }} </div>

                    <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> offered Move in
                        Date:
                        {{ @$auction->info('special_offer_move_date') }}
                    </div>
                    @endif


                    {{-- <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> How many occupants
                                will landlord accept?
                                @if (@$auction->info('special_offer_allowed_occupants') != '' && @$auction->info('special_offer_allowed_occupants') != 'null')
                                    @foreach (json_decode(@$auction->info('special_offer_allowed_occupants')) as $special_offer_allowed_occupants)
                                        <span class="badge bg-secondary">{{ $special_offer_allowed_occupants }}</span>
                    @endforeach
                    @endif
                </div> --}}

                {{-- <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Will seller accept
                                a pet?
                                @if (@$auction->info('special_offer_allow_pets') != '' && @$auction->info('special_offer_allow_pets') != 'null')
                                    @foreach (json_decode(@$auction->info('special_offer_allow_pets')) as $special_offer_allow_pets)
                                        <span class="badge bg-secondary">{{ $special_offer_allow_pets }}</span>
                @endforeach
                @endif
            </div> --}}

            {{-- <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> How many pets will
                                landlord accept? {{ @$auction->info('special_offer_number_of_pets_allowed') }}
        </div>
        <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Minimum credit
            rating seller will accept: {{ @$auction->info('special_offer_min_credit_ratings') }} </div>
        <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Minimum household
            net income tenant must make to qualify for rental:
            {{ @$auction->info('special_offer_min_net_income') }}
        </div>
        <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Will landlord
            accept a prior eviction (within the past 7 years?):
            {{ @$auction->info('special_offer_prior_eviction') }}
        </div>
        <div class="col-md-12 col-12 fw-bold"><i class="fa fa-check-square-o"></i> Will landlord
            accept a prior felony? {{ @$auction->info('special_offer_prior_felony') }} </div> --}}

    </div>

</div>
</div>
<!-- End  -->
<!-- Location Box  -->
{{-- <div class="card location">
                    <div class="card-header">
                        <h5>Location</h5>
                    </div>
                    <div class="card-body">
                        <div class="locationMap position-relative">
                            <img class="w-100"
                                src="https://bidyouroffer.com/wp-content/uploads/2022/09/map-placeholder.jpg"
                                alt="">
                            <div class="right position-absolute">
                                <button class="btn btn-sm"><i class="fa fa-map-marker"></i> View Map</button>
                                <button class="btn btn-sm">Get Direction</button>

                            </div>
                        </div>
                        <br>
                        <p><b>534 Pinellas Bayway S</b></p>
                    </div>
                </div> --}}
<!-- End  -->
<!-- Review  -->
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
            <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">Message</button></a>
            <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">View
                    Profile</button></a>

        </div>
    </div>
</div>
<!-- End  -->
</div>
<div class="col-sm-12 col-md-4 col-lg-4 rightCol">
    <h1>{{ @$auction->address }}</h1>
    <hr>
    @inject('carbon', 'Carbon\Carbon')
    @php
    $lowest_bid_price = ''; //@$auction->bids->min('price') ?? @$auction->max_price;
    $lowest_bidder = ''; //@$auction->bids->where('price', $lowest_bid_price)->first();
    $my_bid = ''; //@$auction->bids->where('user_id', $auth_id)->first();
    @endphp
    @if (@$auction->user_id != $auth_id)
    <a href="{{ route('auction-chat', ['landlord-property', $auction->id]) }}" class="btn btn-success w-100 mb-2">
        <i class="fa-solid fa-paper-plane"></i> Send Message</a>
    @endif
    @if ($auth_id)
    @if (in_array(auth()->user()->user_type, ['seller', 'agent']))
    <button class="btn w-100" onclick="javascript:window.location='{{ route('agent.landlord.auction.bid', @$auction->id) }}';" {{ @$auction->user_id == $auth_id ? 'disabled' : '' }}>
        <span class="bid">Bid Now </span>
        <span class="badge bg-light float-end text-dark">${{ @$auction->info('monthly_lease_price') }}</span>
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
        <div class="card-body">
            @if ($lowest_bidder)
            <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p>
            @else
            <p>No one has bid on this auction.</p>
            @endif
            <div class="accordion" id="accordionExample">
                <div class="accordion-item border-0">
                    @foreach (@$auction->bids as $bid)
                    <!-- Item loop -->
                    <div class="accordion" type="button" data-bs-toggle="collapse" data-bs-target="#item{{ $bid->id }}" aria-expanded="true" aria-controls="item{{ $bid->id }}">
                        <div class="d-flex small accordion mr-0 text-center">
                            <div class="col-1">
                                <span class="badge">{{ $loop->iteration }}</span>
                            </div>
                            <div class="col-4">
                                {{ $bid->user->name }}
                            </div>
                            <div class="col-4 text-right">
                                ${{ $bid->get->offer_rental_price }} </div>
                            <div class="col-2">
                                Terms↓
                            </div>
                        </div>
                    </div>
                    <div id="item{{ $bid->id }}" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                            <div id="bidding_history_data">
                                <div>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th colspan="2">Tenant/Tenant’s Agent Info</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <th class="small">First Name</th>
                                                <td class="small">{{ $bid->get->first_name }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Last Name</th>
                                                <td class="small">{{ $bid->get->last_name }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Phone number</th>
                                                <td class="small">{{ $bid->get->agent_phone }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Email</th>
                                                <td class="small">{{ $bid->get->agent_email }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Brokerage</th>
                                                <td class="small">{{ $bid->get->agent_brokerage }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Real Estate License #:</th>
                                                <td class="small">{{ $bid->get->agent_license_no }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">MLS ID #</th>
                                                <td class="small">{{ $bid->get->agent_mls_id }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th colspan="2">Terms offered by the tenant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <th class="small">Rental Price</th>
                                                <td class="small">${{ $bid->get->offer_rental_price }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">Lease Terms</th>
                                                <td class="small">{{ $bid->get->offer_lease_term }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Security Deposit</th>
                                                <td class="small">
                                                    ${{ $bid->get->offer_security_deposit }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">Offered Move in Date</th>
                                                <td class="small">{{ $bid->get->offer_move_date }}</td>
                                            </tr>
                                            <tr>
                                                <th class="small">How many occupants?</th>
                                                <td class="small">
                                                    @if ($bid->get->offer_occupants != '' && $bid->get->offer_occupants != 'null')
                                                    @foreach (json_decode($bid->get->offer_occupants) as $item)
                                                    <span class="badge bg-secondary">{{ $item }}</span>
                                                    @endforeach
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">Does tenant have a pet?</th>
                                                <td class="small">
                                                    @if ($bid->get->offer_have_pets != '' && $bid->get->offer_have_pets != 'null')
                                                    @foreach (json_decode($bid->get->offer_have_pets) as $item)
                                                    <span class="badge bg-secondary">{{ $item }}</span>
                                                    @endforeach
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">How many pets does the tenant have</th>
                                                <td class="small">{{ $bid->get->offer_number_of_pets }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">Credit Score Rating:</th>
                                                <td class="small">
                                                    {{ $bid->get->offer_min_credit_ratings }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">Household Net Income:</th>
                                                <td class="small">${{ $bid->get->offer_min_net_income }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">Has tenant had any prior evictions?</th>
                                                <td class="small">{{ $bid->get->offer_prior_eviction }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="small">Has tenant been convicted of a felony?
                                                </th>
                                                <td class="small">{{ $bid->get->offer_prior_felony }}
                                                </td>
                                            </tr>
                                            {{-- <tr>
                                                                <th class="small">If more than 1 tenant applies for the
                                                                    rental. Tenant agrees to pay $50 more up to what
                                                                    amount?</th>
                                                                <td class="small">
                                                                    ${{ $bid->get->offer_agree_to_pay_more }}</td>
                                            </tr> --}}
                                        </tbody>
                                    </table>

                                    {{-- <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th colspan="2">If landlord rejects that offer what is
                                                                    the highest and best terms offered by tenant</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <th class="small">Rental Price</th>
                                                                <td class="small">
                                                                    ${{ $bid->get->special_offer_rental_price }}
                                    </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Lease Terms</th>
                                        <td class="small">
                                            {{ $bid->get->special_offer_lease_term }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Move in Date</th>
                                        <td class="small">
                                            {{ $bid->get->special_offer_move_date }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">How many occupants?</th>
                                        <td class="small">
                                            @if ($bid->get->special_offer_occupants != '' && $bid->get->special_offer_occupants != 'null')
                                            @foreach (json_decode($bid->get->special_offer_occupants) as $item)
                                            <span class="badge bg-secondary">{{ $item }}</span>
                                            @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Does tenant have a pet?</th>
                                        <td class="small">
                                            @if ($bid->get->special_offer_have_pets != '' && $bid->get->special_offer_have_pets != 'null')
                                            @foreach (json_decode($bid->get->special_offer_have_pets) as $item)
                                            <span class="badge bg-secondary">{{ $item }}</span>
                                            @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">How many pets does the tenant have</th>
                                        <td class="small">
                                            {{ $bid->get->special_offer_number_of_pets }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Credit score rating</th>
                                        <td class="small">
                                            {{ $bid->get->special_offer_min_credit_ratings }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Household net income</th>
                                        <td class="small">
                                            ${{ $bid->get->special_offer_min_net_income }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Has tenant had a prior eviction (within
                                            the past 7 years?)</th>
                                        <td class="small">
                                            {{ $bid->get->special_offer_prior_eviction }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">Has tenant been convicted of a felony?
                                        </th>
                                        <td class="small">
                                            {{ $bid->get->special_offer_prior_felony }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="small">If more than 1 tenant applies for the
                                            rental. Tenant agrees to pay $50 more up to what
                                            amount?</th>
                                        <td class="small">
                                            ${{ $bid->get->special_offer_agree_to_pay_more }}</td>
                                    </tr>
                                    </tbody>
                                    </table> --}}

                                    @if (@$auction->user_id == $auth_id)
                                    @if (!@$auction->is_sold)
                                    <form action="{{ route('agent.landlord.auction.bid.accept', $bid->id) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                        <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                        <div style="text-align: right;">
                                            <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                        </div>
                                    </form>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End  -->
                    @endforeach

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
            {{ qr_code(route('agent.landlord.auction', @$auction->id), 200) }}
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
                <input type="text" readonly="" id="copylink" value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
                <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0" style="min-width:60px;">Copy</button>
            </div>

        </div>
    </div>
    <!-- End  -->
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
                <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg" class="card-img-top" alt="...">
                <div class="card-body pb-2 pt-2">
                    <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                    <div class="houseDetails mb-1">
                        <span>
                            <span class="d-inline-flex justify-content-center align-items-center gap-1"><img src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon" width="15"><b>
                                    4</b></span>
                            <span class="d-inline-flex justify-content-center align-items-center gap-1"><img src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon" width="15"><b>
                                    2</b></span>
                            <span class="d-inline-flex justify-content-center align-items-center gap-1"><img src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}" alt="bed icon" width="15"><b> 1,643 </b>Sq Ft</span>
                        </span>
                        - House for sale
                    </div>
                    <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
                    <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg><b>28d 03:15:29</b></p>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-6 left">
                            <!-- Barcode  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
                            <!-- <div hidden class="w-50">
                                                                                                                                  <div id="qr-content" class="text-center w-75 m-auto">
                                                                                                                                    <img class="w-50" src="https://www.freepnglogos.com/uploads/qr-code-png/qr-code-file-bangla-mobile-code-0.png" alt="">
                                                                                                                                    <p>Scan QR code to view on mobile device.</p>
                                                                                                                                  </div>
                                                                                                                                </div> -->
                            <!-- Message  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                </path>
                            </svg>
                            <!-- FAvourite  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
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
                <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg" class="card-img-top" alt="...">
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
                    <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span class="float-end"><span><b>MLS
                                    ID</b></span> <span>#12345</span></span></p>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-6 left">
                            <!-- Barcode  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
                            <!-- Message  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                </path>
                            </svg>
                            <!-- FAvourite  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
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
{{-- <script src="{{asset('assets/bootstrap-5.2.2/js/twitter-bootstrap.min.js')}}"></script> --}}
<script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
{{-- <script>
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
</script> --}}
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