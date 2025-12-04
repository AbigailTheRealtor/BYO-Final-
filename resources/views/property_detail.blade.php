@extends('layouts.main')
@push('styles')
    <!-- //Listing Description css  -->
    <link rel="stylesheet" href="{{asset('assets/css/listingDescription.css')}}" />
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

        .fa-dollar {
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

        .form-control, .form-select {
            border-radius: 0.25rem;
            box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
            border-radius: 0.25rem;
            background-color: #fafafb;
            margin-bottom: 15px;
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
                <!-- Galery Start Here  -->
                <div class="row d-flex flex-wrap" data-toggle="modal" data-target="#lightbox">
                    <!-- Main Video Baner  -->
                    @php
                        $mediaImage = @$auction->mediaImages[0];
                    @endphp
                    <div class="col-sm-12 col-md-6 col-lg-8">
                        <img class="w-100" src="{{asset('auction/images/'.@$mediaImage->name)}}" data-target="#indicators"
                            data-slide-to="{{@$mediaImage->id}}" alt="" />
                    </div>
                    <!-- Small Images  -->
                    <div class="col-sm-12 col-md-4 col-lg-4">
                        <div class="row">
                            @foreach (@$auction->mediaImages as $image)
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('auction/images/'.$image->name)}}" data-target="#indicators"
                                    data-slide-to="{{$image->id}}" alt="" />
                            </div>
                            @endforeach
                            {{-- <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div>
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div>
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div>
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div>
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div>
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div>
                            <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                                <img class="w-100" src="{{asset('assets/pictures/buyerWork/buyerWork.jpg')}}" data-target="#indicators"
                                    data-slide-to="0" alt="" />
                            </div> --}}
                        </div>
                    </div>

                    <!-- <div class="col-12 col-md-6 col-lg-3">
                            <img src="https://source.unsplash.com/random/201" data-target="#indicators" data-slide-to="1" alt="" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <img src="https://source.unsplash.com/random/202" data-target="#indicators" data-slide-to="2" alt="" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <img src="https://source.unsplash.com/random/203" data-target="#indicators" data-slide-to="3" alt="" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <img src="https://source.unsplash.com/random/204" data-target="#indicators" data-slide-to="3" alt="" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <img src="https://source.unsplash.com/random/205" data-target="#indicators" data-slide-to="4" alt="" />
                        </div> -->
                </div>
                <!-- Modal -->
                <div class="modal fade" id="lightbox" role="dialog" tabindex="-1" aria-labelledby="exampleModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <button type="button" class="close text-right p-2" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <div id="indicators" class="carousel slide" data-interval="false">
                                <ol class="carousel-indicators">
                                    @foreach (@$auction->mediaImages as $image)
                                    <li data-target="#indicators" data-slide-to="{{$image->id}}" class="active"></li>
                                    @endforeach
                                    <!-- <li data-target="#indicators" data-slide-to="1"></li>
                                        <li data-target="#indicators" data-slide-to="2"></li>
                                        <li data-target="#indicators" data-slide-to="3"></li>
                                        <li data-target="#indicators" data-slide-to="4"></li>
                                        <li data-target="#indicators" data-slide-to="5"></li> -->
                                </ol>
                                <div class="carousel-inner">
                                    @php
                                        $i=1;
                                    @endphp
                                    @foreach (@$auction->mediaImages as $image)
                                    <div class="carousel-item {{ ($i++ == 1) ? "active":"" }}">
                                            <img class="d-block w-100" src="{{asset('auction/images/'.$image->name)}}"
                                            alt="First slide">
                                    </div>
                                    @endforeach

                                    {{-- <div class="carousel-item active">

                                        <img class="d-block w-100" src="https://source.unsplash.com/random/200"
                                            alt="First slide">
                                    </div>
                                    <div class="carousel-item">
                                        <img class="d-block w-100" src="https://source.unsplash.com/random/201"
                                            alt="Second slide">
                                    </div>
                                    <div class="carousel-item">
                                        <img class="d-block w-100" src="https://source.unsplash.com/random/202"
                                            alt="Third slide">
                                    </div>
                                    <div class="carousel-item">
                                        <img class="d-block w-100" src="https://source.unsplash.com/random/203"
                                            alt="Fourth slide">
                                    </div>
                                    <div class="carousel-item">
                                        <img class="d-block w-100" src="https://source.unsplash.com/random/204"
                                            alt="Fifth slide">
                                    </div>
                                    <div class="carousel-item">
                                        <img class="d-block w-100" src="https://source.unsplash.com/random/205"
                                            alt="Sixth slide">
                                    </div> --}}
                                </div>
                                <a class="carousel-control-prev" href="#indicators" role="button" data-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="sr-only">Previous</span>
                                </a>
                                <a class="carousel-control-next" href="#indicators" role="button" data-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="sr-only">Next</span>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- End  -->
                <!-- Description Box  -->
                <div class="card description">
                    <div class="card-header">
                        <h5>Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">{{@$auction->description}}</p>
                        <hr />
                        <h4>Features</h4>
                        <div class="row" style="flex-wrap: wrap;">
                            @if(@$auction->bedroom)
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-bed"></i> Bedrooms: {{@$auction->bedroom->name}}</div>
                            @endif
                            @if(@$auction->bathroom)
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-bath"></i> Bathrooms: {{@$auction->bathroom->name}} </div>
                            @endif
                            @if(@$auction->heated_sqft)
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-calculator"></i> Heated Sqft: {{@$auction->heated_sqft}} </div>
                            @endif
                            @if(@$auction->year_built)
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-calendar"></i> Year Built: {{@$auction->year_built}} </div>
                            @endif
                            @if(@$auction->pool)
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-swimming-pool"></i> Pool: {{@$auction->pool}} </div>
                            @endif
                            @if(@$auction->pool_type)
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-swimming-pool"></i> Pool: {{@$auction->pool_type}} </div>
                            @endif
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-car"></i> Carport: {{@$auction->carport}} </div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-warehouse"></i> Garage: {{@$auction->garage}} </div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1"><i class="fa fa-warehouse"></i> Garage Spaces: {{@$auction->garage_spaces}} </div>
                            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa fa-circle-dot"></i> Special Sale Provision: {{@$auction->sale_provision}} </div>
                            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa fa-circle-dot"></i> Listing Service Type: {{@$auction->service_type}} </div>
                            {{-- <div class="col-md-3 fw-bold"><i class="fa fa-water"></i> Water View: {{@$auction->water_view}} </div>
                            <div class="col-md-3 fw-bold"><i class="fa fa-water"></i> Water Extra: {{@$auction->water_extras}} </div>
                            <div class="col-md-6 fw-bold"><i class="fa fa-check"></i> HOA/Community Association: {{@$auction->hoa_association}} </div> --}}

                        </div>
                        <hr>
                        <h4>Water View</h4>
                        <div class="row" style="flex-wrap: wrap;">
                            <div class="col-md-3 fw-bold">Water View: {{@$auction->water_view}} </div>
                            @if(@$auction->water_view_types)
                            <div class="col-md-9 fw-bold">
                                Water View:
                                @inject('water_tiew', 'App\Models\WaterViewType')
                                @foreach (@$auction->water_view_types as $wvt)
                                <span class="badge bg-secondary">{{$water_tiew::whereId($wvt->water_view_type_id)->first()->name}}</span>
                                @endforeach
                            </div>
                            @endif
                            <div class="col-md-3 fw-bold">Water Extra: {{@$auction->water_extras}} </div>
                            @if(@$auction->water_extra && count(@$auction->water_extra)>0)
                            <div class="col-md-9 fw-bold">
                                Water Extra:
                                @inject('water_extra', 'App\Models\WaterExtra')
                                @foreach (@$auction->water_extra as $we)
                                <span class="badge bg-secondary">{{$water_extra::whereId($we->water_extra_id)->first()->name}}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        <hr>
                        @if(@$auction->ac_types)
                        <h4>Air Conditioning Type</h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            @inject('ac_type', 'App\Models\AirConditioningType')
                            <div class="col-md-12 fw-bold">
                                @foreach (@$auction->ac_types as $at)
                                <span class="badge bg-secondary">{{$ac_type::whereId($at->air_conditioning_type_id)->first()->name}}</span>
                                @endforeach
                            </div>
                        </div>
                        <hr>
                        @endif
                        @if(@$auction->fuel)
                        <h4>Heating/Fuel</h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            @inject('hf', 'App\Models\HeatingFuel')
                            <div class="col-md-12 fw-bold">
                                @foreach (@$auction->fuel as $fuel)
                                <span class="badge bg-secondary">{{$hf::whereId($fuel->heating_fuel_id)->first()->name}}</span>
                                @endforeach
                            </div>
                        </div>
                        <hr>
                        @endif
                        @if(@$auction->appliance)
                        <h4>Appliances Included</h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            @inject('apl', 'App\Models\Appliance')
                            <div class="col-md-12 fw-bold">
                                @foreach (@$auction->appliance as $appliance)
                                <span class="badge bg-secondary">{{$apl::whereId($appliance->appliance_id)->first()->name}}</span>
                                @endforeach
                            </div>
                        </div>
                        <hr>
                        @endif
                        @if(@$auction->fee_includes != "" && count(@$auction->fee_includes) > 0)
                        <h4>Fee Includes </h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            @inject('finc', 'App\Models\FeeInclude')
                            <div class="col-md-12 fw-bold">
                                @foreach (@$auction->fee_includes as $fi)
                                <span class="badge bg-secondary">{{$finc::whereId($fi->fee_include_id)->first()->name}}</span>
                                @endforeach
                            </div>
                        </div>
                        <hr>
                        @endif

                        @if(@$auction->financing)
                        <h4>Financing Accepted</h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            @inject('financing', 'App\Models\Financing')
                            <div class="col-md-12 fw-bold">
                                @foreach (@$auction->financing as $fin)
                                <span class="badge bg-secondary">{{$financing::whereId($fin->financing_id)->first()->name}}</span>
                                @endforeach
                            </div>
                        </div>
                        <hr />
                        @endif
                        <div class="d-flex" style="flex-wrap: wrap;">
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Starting Price: ${{@$auction->starting_price}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Buy Now Price: ${{@$auction->buy_now_price}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Reserve Price: ${{@$auction->reserve_price}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Type of Property:
                                @if(@$auction->property_type)
                                    @foreach (@$auction->property_type as $pType)
                                        <span>{{$pType->property_type->name}}</span>
                                    @endforeach
                                @endif
                            </div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">County: {{@$auction->county->name}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">City: {{@$auction->city->name}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">State: {{@$auction->state->name}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">HOA/Community Association: {{@$auction->hoa_association}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">HOA/Community contact information: {{@$auction->hoa_manager_contact}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">HOA Fee Requirement: {{@$auction->hoa_fee_requirement}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">HOA Fee Requirement: {{@$auction->hoa_fee_requirement}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">HOA Fee: {{@$auction->hoa_fee}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">HOA Payment Schedule: {{@$auction->hoa_payment_schedule}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Condo Fee: {{@$auction->condo_fee}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Condo Fee Schedule: {{@$auction->condo_fee_schedule}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">55+ over community: {{@$auction->old_community}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Rental Restrictions: {{@$auction->rental_restrictions}}</div>
                            <div class="col-md-12 col-12 fw-bold mt-1 mb-1">Rental Restrictions list: {{@$auction->rental_restrictions_desription}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Pets Allowed: {{@$auction->pets_allowed}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Number of Pets Allowed: {{@$auction->number_of_pets_allowed}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Max Pet Weight: {{@$auction->max_pet_weight}}</div>
                            <div class="col-md-12 col-12 fw-bold mt-1 mb-1">Pet restrictions: {{@$auction->pet_restrictions}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">MLS ID: {{@$auction->mls_id}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Is Property in a Flood Zone? {{@$auction->is_in_flood_zone}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">flood zone code: {{@$auction->flood_zone_code}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Floors in Unit/Home: {{@$auction->number_of_floors_in_unit}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Total Number of Floors: {{@$auction->total_number_of_floors}}</div>
                            <div class="col-md-6 col-12 fw-bold mt-1 mb-1">Building Elevator: {{@$auction->elevator}}</div>
                        </div>
                        <hr>
                        <h4>Auction Terms</h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Escrow Amount: {{@$auction->terms->escrow_amount}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Inspection Period: {{@$auction->terms->inspection_perion}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Buyer’s Agent Commission: {{@$auction->terms->buyer_agent_commission}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Buyer’s Premium: {{@$auction->terms->buyer_premium}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Seller’s Premium: {{@$auction->terms->seller_premium}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Success fee to be paid by: {{@$auction->terms->success_fee_to_be_paid_by}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Any Additional Remarks: {{@$auction->terms->additional_remarks}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Property list date: {{@$auction->terms->property_list_date}}</div>
                        </div>
                        <hr>
                        <h4>Seller/Seller’s Agent Info</h4>
                        <div class="d-flex" style="flex-wrap: wrap;">
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Name: {{@$auction->seller_name}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Brokerage: {{@$auction->brokerage}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">License number: {{@$auction->license_number}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Phone number: {{@$auction->phone_number}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">Email: {{@$auction->email}}</div>
                            <div class="col-md-4 col-6 fw-bold mt-1 mb-1">MLS ID: {{@$auction->seller_agent_mls_id}}</div>
                            <div class="col-md-12 col-12 fw-bold mt-1 mb-1">Is Seller looking to purchase another property?: {{@$auction->looking_another_property}}</div>
                        </div>

                    </div>
                </div>
                <!-- End  -->
                <!-- Location Box  -->
                <div class="card location d-none">
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
                </div>
                <!-- End  -->
                <!-- Review  -->
                <div class="card review">
                    <div class="card-body d-flex align-items-center">
                        <div class="left d-flex align-items-center">
                            <img class="w-25" src="https://ppt1080.b-cdn.net/images/avatar/none.png" alt="">
                            <div>
                                <p class="mb-0"><a href="{{route('author',[@$auction->user_id])}}"><b>Seller Details</b></a><span></span>
                                    <span class="start opacity-50">
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                    </span>
                                </p>
                                <p class="mb-0">...</p>
                                <p class="mb-0 opacity-50">AbbyS1 • last online 5 days ago.</p>
                            </div>
                        </div>
                        <div class="right text-center">
                            <a href="author.html"><button class="btn">Message</button></a>
                            <a href="author.html"><button class="btn">View Profile</button></a>

                        </div>
                    </div>
                </div>
                <!-- End  -->
            </div>
            <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
                <h1>{{@$auction->address}}</h1>
                <hr>
                @inject("carbon", 'Carbon\Carbon' )
                @php
                if(@$auction->auction_length>0)
                    {
                        $start = $carbon::now();
                        $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->auction_length);
                        $diff = $end->diffInDays($start);
                    }
                @endphp
                @if(@$auction->auction_length>0)
                @php
                    $diff_d = $diff;
                    $diff_H = $start->diff($end)->format('%H');
                    $diff_I = $start->diff($end)->format('%I');
                    $diff_S = $start->diff($end)->format('%S');
                @endphp
                <div class="time d-flex justify-content-between text-center flex-wrap pb-2">
                    <div>
                        <h5><b class="timer-d"> {{$diff_d}} </b></h5>
                        <h6 class="opacity-50">Days</h6>
                    </div>
                    <div>
                        <h5><b class="timer-h"> {{$diff_H}} </b></h5>
                        <h6 class="opacity-50">Hrs</h6>
                    </div>
                    <div>
                        <h5><b class="timer-m"> {{$diff_I}} </b></h5>
                        <h6 class="opacity-50">Mins</h6>
                    </div>
                    <div>
                        <h5><b class="timer-s"> {{$diff_S}} </b></h5>
                        <h6 class="opacity-50">Secs</h6>
                    </div>
                    {{-- <div>
                        <h5><b>{{$diff}}</b></h5>
                        <h6 class="opacity-50">Days</h6>
                    </div>
                    <div>
                        <h5><b> {{$start->diff($end)->format('%H')}} </b></h5>
                        <h6 class="opacity-50">Hrs</h6>
                    </div>
                    <div>
                        <h5><b>{{$start->diff($end)->format('%I')}}</b></h5>
                        <h6 class="opacity-50">Mins</h6>
                    </div>
                    <div>
                        <h5><b>{{$start->diff($end)->format('%S')}}</b></h5>
                        <h6 class="opacity-50">Secs</h6>
                    </div> --}}
                </div>
                @endif
                    @php
                        $highest_bid_price = @$auction->bids->max('price') ?? @$auction->starting_price;
                        $highest_bidder = @$auction->bids->where('price',$highest_bid_price)->first();
                        $my_bid = @$auction->bids->where('user_id',$auth_id)->first();
                    @endphp
                @if($auth_id)
                    @if(in_array(auth()->user()->user_type, ["buyer","agent"]))
                    <button class="btn w-100" data-bs-toggle="modal" data-bs-target="#exampleModal" {{(@$auction->user_id==$auth_id)?"disabled":""}}>
                        <span class="bid">Bid Now </span>
                        <span class="badge bg-light float-end text-dark">${{number_format($highest_bid_price,2,'.',',')}}</span>
                        @if(@$auction->sold)
                        <span class="badge bg-danger">Sold</span>
                        @endif
                        {{-- {{$res}} --}}
                    </button>
                    @endif
                @else
                <a href="{{route('login')}}">
                    <button class="btn w-100">
                        <span class="bid">Login for Bid </span>
                        <span class="badge bg-light float-end text-dark">${{number_format($highest_bid_price,2,'.',',')}}</span>
                    </button>
                </a>
                @endif
                <!-- Highest Bider   -->
                <div class="card higestBider">
                    <div class="card-body">
                        @if($highest_bidder)
                        <p><b>{{$highest_bidder->user->name??""}}</b> is the highest bidder.</p>
                        @else
                        <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">
                                @foreach (@$auction->bids as $bid)
                                    <!-- Item loop -->
                                    <div class="accordion" type="button" data-bs-toggle="collapse" data-bs-target="#item1"
                                        aria-expanded="true" aria-controls="item1">
                                        <div class="d-flex small accordion mr-0 text-center">
                                            <div class="col-1">
                                                <span class="badge">{{$loop->iteration}}</span>
                                            </div>
                                            <div class="col-4">
                                                {{$bid->user->name}} </div>
                                            <div class="col-4 text-right">
                                                ${{number_format($bid->price,2,'.',',')}} </div>
                                            <div class="col-2">
                                                Terms↓
                                            </div>
                                        </div>
                                    </div>
                                    <div id="item1" class="accordion-collapse collapse" aria-labelledby="headingOne"
                                        data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <div id="bidding_history_data">
                                                <div>
                                                    <p class="d-flex justify-content-between small">Cash or Financing Type:
                                                        <span>{{$bid->financing->name}}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">Escrow Amount:
                                                        <span>{{$bid->escrow_amount}}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">Closing Date:
                                                        <span>{{$bid->closing_date}}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">Inspection Period:
                                                        <span>{{$bid->inspection_period}}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">Contingencies:
                                                        <span>{{$bid->contingencies}}</span>
                                                    </p>

                                                    <p class="d-flex justify-content-between small">Seller Premium:
                                                        <span>{{$bid->seller_premium}}</span>
                                                    </p>

                                                    <p class="d-flex justify-content-between small">Buyer Premium:
                                                        <span>{{$bid->buyer_premium}}</span>
                                                    </p>
                                                    @if(@$auction->user_id==$auth_id)
                                                    @if($bid->video_url)
                                                        <p>
                                                            <a href="{{$bid->video_url}}" class="btn btn-sm btn-primary" target="_blank">View Video</a>
                                                        </p>
                                                    @endif

                                                    @if($bid->letter)
                                                        <p>
                                                            <a href="{{asset('auction/bid/letters/'.$bid->letter)}}" download class="btn btn-sm btn-primary" target="_blank">Proof of funds/pre-approval letter </a>
                                                        </p>
                                                    @endif

                                                    @if($bid->card)
                                                        <p>
                                                            <img src="{{asset('auction/bid/cards/'.$bid->card)}}" alt="" style="width: 150px;">
                                                        </p>
                                                    @endif

                                                    @if($bid->audio)
                                                        <p>
                                                            <audio class="audio-fluid" controls style="width: 100%;">
                                                                <source src="{{ asset('auction/bid/audios/' . $bid->audio) }}"
                                                                    type="audio/mp3">
                                                                Your browser does not support the audio tag.
                                                            </audio>
                                                        </p>
                                                    @endif
                                                    @endif

                                                    @if(@$auction->user_id==$auth_id)
                                                        @if(!@$auction->sold)
                                                        <form action="{{route('acceptPABid')}}" method="post">
                                                            @csrf
                                                            <input type="hidden" name="auction_id" value="{{@$auction->id}}">
                                                            <input type="hidden" name="bid_id" value="{{$bid->id}}">
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

                                {{-- <!-- Item 1 -->
                                <div class="accordion" type="button" data-bs-toggle="collapse" data-bs-target="#item1"
                                    aria-expanded="true" aria-controls="item1">
                                    <div class="d-flex small accordion mr-0 text-center">
                                        <div class="col-1">
                                            <span class="badge">1</span>
                                        </div>
                                        <div class="col-4">
                                            Aiden Samuel </div>
                                        <div class="col-4 text-right">
                                            $902,000.00 </div>
                                        <div class="col-2">
                                            Terms↓
                                        </div>
                                    </div>
                                </div>
                                <div id="item1" class="accordion-collapse collapse" aria-labelledby="headingOne"
                                    data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div id="bidding_history_data">
                                            <div>
                                                <p class="d-flex justify-content-between small">Cash or Financing Type:
                                                    <span>DASASD</span>
                                                </p>
                                                <p class="d-flex justify-content-between small">Escrow Amount:
                                                    <span>ADSASDD</span>
                                                </p>
                                                <p class="d-flex justify-content-between small">Closing Date:
                                                    <span>ASDASDSA</span>
                                                </p>
                                                <p class="d-flex justify-content-between small">Inspection Period:
                                                    <span>DASDSA</span>
                                                </p>
                                                <p class="d-flex justify-content-between small">Contingencies:
                                                    <span>DASD</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End  -->
                                <!-- Item 2 -->
                                <div class="accordion" type="button" data-bs-toggle="collapse" data-bs-target="#item2"
                                    aria-expanded="true" aria-controls="item2">
                                    <div class="d-flex small accordion mr-0 text-center">
                                        <div class="col-1">
                                            <span class="badge badge-primary">2</span>
                                        </div>
                                        <div class="col-4">
                                            Admin Bid Your Offer</div>
                                        <div class="col-4 text-right">
                                            $901,000.00 </div>
                                        <div class="col-2">
                                            Terms↓
                                        </div>
                                    </div>
                                </div>
                                <div id="item2" class="accordion-collapse collapse" aria-labelledby="headingOne"
                                    data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div>
                                            <p class="d-flex justify-content-between small">Cash or Financing Type:
                                                <span>fvbb</span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Escrow Amount:
                                                <span>cxfbfcb</span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Closing Date:
                                                <span>cbcbc</span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Inspection Period:
                                                <span>bcbb</span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Contingencies:
                                                <span>cbcvb</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <!-- End  -->
                                <!-- Item 3 -->
                                <div class="accordion" type="button" data-bs-toggle="collapse" data-bs-target="#item3"
                                    aria-expanded="true" aria-controls="item3">
                                    <div class="d-flex small accordion mr-0 text-center">
                                        <div class="col-1">
                                            <span class="badge badge-primary">3</span>
                                        </div>
                                        <div class="col-4">
                                            Admin Bid Your Offer</div>
                                        <div class="col-4 text-right">
                                            $900,000.00 </div>
                                        <div class="col-2">
                                            Terms↓
                                        </div>
                                    </div>
                                </div>
                                <div id="item3" class="accordion-collapse collapse " aria-labelledby="headingOne"
                                    data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div>
                                            <p class="d-flex justify-content-between small">Cash or Financing Type:
                                                <span></span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Escrow Amount:
                                                <span></span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Closing Date:
                                                <span></span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Inspection Period:
                                                <span></span>
                                            </p>
                                            <p class="d-flex justify-content-between small">Contingencies:
                                                <span></span>
                                            </p>
                                        </div>
                                    </div>

                                </div>
                                <!-- End  --> --}}
                            </div>

                        </div>
                    </div>
                </div>
                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa fa-user"></i> </span>
                </button>
                <!-- End  -->
                <!-- Details Of Items -->
                <div class="card p-4">
                    <ul>
                        {{-- <li><span class="col-6">Type of Property</span> <span class="col-6">Condo</span></li>
                        <li><span class="col-6">Bedrooms</span> <span class="col-6">3</span></li>
                        <li><span class="col-6">Half Bathrooms</span> <span class="col-6">0</span></li>
                        <li><span class="col-6">Bathrooms</span> <span class="col-6">3</span></li>
                        <li><span class="col-6">Heated Sqft</span> <span class="col-6">1775</span></li>
                        <li><span class="col-6">Year Built</span> <span class="col-6">1985</span></li>
                        <li><span class="col-6">County</span> <span class="col-6">Pinellas</span></li>
                        <li><span class="col-6">Air Conditioning Type</span> <span class="col-6">Central Air</span></li>
                        <li><span class="col-6">Pool</span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">Carport</span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">Garage Spaces</span> <span class="col-6">1</span></li>
                        <li><span class="col-6">Water View</span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">Water View</span> <span class="col-6">Canal, Intracoastal
                                Waterway</span>
                        </li>
                        <li><span class="col-6">Water Extras</span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">Water Extras</span> <span class="col-6">Assigned Boat Slip,
                                Dockw/Electric</span></li>
                        <li><span class="col-6">Appliances Included</span> <span class="col-6">Dishwasher, Dryer, Range
                                Electric, Refrigerator, Washer, Wine Refrigerator</span></li>
                        <li><span class="col-6">HOA/Community Association</span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">HOA Fee Requirement</span> <span class="col-6">Required</span></li>
                        <li><span class="col-6">HOA Fee</span> <span class="col-6">66</span></li>
                        <li><span class="col-6">HOA Payment Schedule</span> <span class="col-6">Quarterly</span></li>
                        <li><span class="col-6">Condo Fee</span> <span class="col-6">861</span></li>
                        <li><span class="col-6">Condo Fee Schedule</span> <span class="col-6">Monthly</span></li>
                        <li><span class="col-6">Fee Includes</span> <span class="col-6">Community Pool,
                                Insurance,Maintenance Grounds, Maintenance Repairs, Pool Maintenance, Sewer,
                                Trash</span>
                        </li>
                        <li><span class="col-6">55+ over community</span> <span class="col-6">No</span></li>
                        <li><span class="col-6">Pets Allowed</span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">Number of Pets Allowed</span> <span class="col-6">2</span></li>
                        <li><span class="col-6">MLS ID</span> <span class="col-6">U8170275</span></li>
                        <li><span class="col-6">Is Property in a Flood Zone? </span> <span class="col-6">Yes</span></li>
                        <li><span class="col-6">What is the flood zone code? </span> <span class="col-6">AE</span></li>
                        <li><span class="opacity-50">Auction Terms:</span> </li>
                        <li><span class="col-6">Escrow Amount</span> <span class="col-6">$10,000</span></li>
                        <li><span class="col-6">Inspection Period</span> <span class="col-6">7 days</span></li>
                        <li><span class="col-6">Buyer’s Agent Commission</span> <span class="col-6">2%</span></li>
                        <li><span class="col-6">Buyer’s Premium</span> <span class="col-6">1%</span></li>
                        <li><span class="col-6">Seller’s Premium </span> <span class="col-6">None</span></li>
                        <li><span class="col-6">Success fee to be paid by</span> <span class="col-6">Seller</span></li>
                        <li><span class="col-6">Property list date</span> <span class="col-6">07/17/2022</span></li>
                        <li><span class="col-6">Financing Accepted</span> <span class="col-6">Cash, Conventional,
                                Other,Non-QM, Jumbo</span></li>
                        <li><span class="opacity-50">Seller/Seller’s Agent Info</span> </li>
                        <li><span class="col-6">Name:</span> <span class="col-6">Abigail Sweeney</span></li>
                        <li><span class="col-6">Brokerage:</span> <span class="col-6">Innovate Real Estate</span></li>
                        <li><span class="col-6">License number:</span> <span class="col-6">BK3259307</span></li>
                        <li><span class="col-6">Phone number:</span> <span class="col-6">727-776-2013</span></li>
                        <li><span class="col-6">Email: </span> <span class="col-6"><a class="text-dark"
                                    href="mailto:Abigail@BidYourOffer.com"
                                    style="text-decoration:underline;">Abigail@BidYourOffer.com</a></span></li>
                        <li><span class="col-6">MLS ID # </span> <span class="col-6">U8170275</span></li> --}}
                    </ul>
                </div>
                <!-- End -->
                <!-- Social Details  -->
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
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
                                        src="{{asset('assets/fontawesome/svgs/thin/bed-front.svg')}}" alt="bed icon"
                                        width="15"><b>
                                        4</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{asset('assets/fontawesome/svgs/thin/bath.svg')}}" alt="bed icon" width="15"><b>
                                        2</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{asset('assets/fontawesome/svgs/thin/ruler-triangle.svg')}}" alt="bed icon"
                                        width="15"><b> 1,643 </b>Sq Ft</span>
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
                                <!-- <div hidden class="w-50">
                            <div id="qr-content" class="text-center w-75 m-auto">
                              <img class="w-50" src="https://www.freepnglogos.com/uploads/qr-code-png/qr-code-file-bangla-mobile-code-0.png" alt="">
                              <p>Scan QR code to view on mobile device.</p>
                            </div>
                          </div> -->
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
                                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
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


    <!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{route('savePABid')}}" method="post" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="auction_id" value="{{@$auction->id}}">
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-2">
                    <div style="font-size:18px; font-weight:bold;">${{number_format(@$auction->starting_price,2,'.',',')}}</div>
                    <div style="color: rgba(0,0,0,0.5);">Price Now</div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="price">Price:</label>
                            <div class="d-flex align-items-baseline">
                                <i class="fa fa-dollar"></i>
                                <input type="number" class="form-control border-start-0" name="price" id="price" min="0" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="financing_id">Currency/Financing Type:</label>
                            <div class="d-flex align-items-baseline">
                                <select class="form-control form-select" name="financing_id" id="financing_id" required>
                                    <option value="">Select</option>
                                    @foreach ($financings as $row)
                                        <option value="{{$row->id}}">{{$row->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="escrow_amount">Escrow Amount:</label>
                            <div class="d-flex align-items-baseline">
                                <i class="fa fa-dollar"></i>
                                <input type="number" class="form-control border-start-0" name="escrow_amount" id="escrow_amount" min="0" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="closing_date">Closing Date:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="date" class="form-control" name="closing_date" id="closing_date" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="inspection_period">Inspection Period:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="text" class="form-control" name="inspection_period" id="inspection_period" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contingencies">Contingencies:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="text" class="form-control" name="contingencies" id="contingencies" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group d-none">
                            <label for="video_file">Video:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="file" class="form-control" name="video_file" id="video_file" >
                            </div>
                        </div>


                        <div class="form-group col-md-12 mb-2">
                            <label>Video URL:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="text" class="form-control" name="video_url">
                            </div>
                        </div>

                        @if(auth()->user())
                        <div class="form-group">
                            <label for="card">{{auth()->user()->user_type=="agent"?"Business Card":"Driver’s License"}}:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="file" class="form-control" name="card" id="card" >
                            </div>
                        </div>
                        @endif


                        <div class="form-group">
                            <label for="letter">Proof of funds/pre-approval letter:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="file" class="form-control" name="letter" id="letter" >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="audio">Audio:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="file" class="form-control" name="audio" id="audio" >
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Offered Seller’s Premium (Credit amount Seller will pay Buyer at closing) (Optional):</label>
                            <div class="d-flex align-items-baseline">
                                <input type="text" class="form-control" name="seller_premium" >
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Offered Buyer’s Premium (Credit amount Buyer will pay the Seller at closing) (Optional):</label>
                            <div class="d-flex align-items-baseline">
                                <input type="text" class="form-control" name="seller_premium" >
                            </div>
                        </div>
                    </div>

                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-secondary">Bid Now</button>
                </div>

            </div>
            {{-- <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Save changes</button>
            </div> --}}
        </form>
      </div>
    </div>
</div>

@endsection

@push('scripts')
{{-- <script src="{{asset('assets/bootstrap-5.2.2/js/twitter-bootstrap.min.js')}}"></script> --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    var durations = '{{$diff_d}}d{{$diff_H}}h{{$diff_I}}m{{$diff_S}}s';
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
@endpush
