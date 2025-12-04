@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="assets/css/addListing.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.css">
    <style>
        .choices__list {
            z-index: 999;
        }
    </style>
@endpush
@section('content')
    {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
    <div class="container pt-5 pb-5">
        <div class="card">
            <div class="row">
                <div class="col-12 p-4">
                    <form class="p-4 pt-0" action="{{ route('buyer_agent.auction.update') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id" value="{{$id}}">
                        <div class="container">
                            <div class="title">
                                <h4>Edit Buyer's Criteria</h4>
                            </div>


                            <div class="row">
                                <div class="form-group col-md-6 mt-4">
                                    <label>Add Title:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="title" class="form-control" placeholder="eg. Need Home for single family" value="{{$auction->title}}" required>
                                    @if ($errors->has('title'))
                                        <div class="small error">{{ $errors->first('title') }}</div>
                                    @endif
                                </div>

                                <div class="form-group col-md-3 mt-4 d-none">
                                    <label>Buyer:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="buyer_id" class="form-select"  >
                                        @foreach (auth()->user()->buyers as $buyer)
                                        <option value="{{$buyer->user->id}}">{{$buyer->user->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('buyer_id'))
                                        <div class="small error">{{ $errors->first('buyer_id') }}</div>
                                    @endif
                                </div>


                                <div class="form-group col-md-3 mt-4">
                                    <label>Maximum Price:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-text">
                                        <i class="fa fa-dollar" id="basic-addon1"></i>
                                        </div>
                                        <input type="number" class="form-control border-start-0 mb-0" name="max_price"
                                            min="0" required placeholder="0.00" value="{{ $auction->max_price }}">
                                    </div>
                                    @if ($errors->has('max_price'))
                                        <div class="small error">{{ $errors->first('max_price') }}</div>
                                    @endif
                                </div>

                            {{-- </div>

                            <div class="row"> --}}



                                <div class="form-group mt-4 col-md-3">
                                    <label>
                                        Auction Type
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select mb-0" name="auction_type" id="auction_type"
                                        onchange="changeAuctionType(this.value);" required>
                                        <option value="Normal" {{ $auction->auction_type == 'Normal' ? 'selected' : '' }}>Normal
                                            (Timer)</option>
                                        <option value="Traditional"
                                            {{ $auction->auction_type == 'Traditional' ? 'selected' : '' }}>Traditional (No Timer)
                                        </option>
                                    </select>
                                    @if ($errors->has('auction_type'))
                                        <div class="small error">{{ $errors->first('auction_type') }}</div>
                                    @endif
                                    <span class="opacity-50 small">Here you can choose the format of your auction.</span>
                                </div>



                                <div class="form-group col-md-3 mt-4">
                                    <label>
                                        Auction Length
                                        <span class="text-danger auction-length-required">*</span>
                                    </label>
                                    <select class="form-select mb-0 auction_length" name="auction_length" required>
                                        <option value="1" class="normal-length"
                                            {{ $auction->auction_length == '1' ? 'selected' : '' }}>1 Day</option>
                                        <option value="3" class="normal-length"
                                            {{ $auction->auction_length == '3' ? 'selected' : '' }}>3 Days</option>
                                        <option value="5" class="normal-length"
                                            {{ $auction->auction_length == '5' ? 'selected' : '' }}>5 Days</option>
                                        <option value="7" class="normal-length"
                                            {{ $auction->auction_length == '7' ? 'selected' : '' }}>7 Days</option>
                                        <option value="10" class="normal-length"
                                            {{ $auction->auction_length == '10' ? 'selected' : '' }}>10 Days</option>
                                        <option value="14" class="normal-length"
                                            {{ $auction->auction_length == '14' ? 'selected' : '' }}>14 Days</option>
                                        <option value="21" class="normal-length"
                                            {{ $auction->auction_length == '21' ? 'selected' : '' }}>21 Days</option>
                                        <option value="30" class="normal-length"
                                            {{ $auction->auction_length == '30' ? 'selected' : '' }}>30 Days</option>
                                        <option value="45" class="normal-length"
                                            {{ $auction->auction_length == '45' ? 'selected' : '' }}>45 Days</option>
                                        <option value="60" class="normal-length"
                                            {{ $auction->auction_length == '60' ? 'selected' : '' }}>60 Days</option>
                                        <option value="75" class="normal-length"
                                            {{ $auction->auction_length == '75' ? 'selected' : '' }}>75 Days</option>
                                        <option value="90" class="normal-length"
                                            {{ $auction->auction_length == '90' ? 'selected' : '' }}>90 Days</option>
                                        <option value="-1" class="traditional-length d-none"
                                            {{ $auction->auction_length == '-1' ? 'selected' : '' }}>No time limit
                                        </option>
                                    </select>
                                    @if ($errors->has('auction_length'))
                                        <div class="small error">{{ $errors->first('auction_length') }}</div>
                                    @endif
                                    <span class="opacity-50 small">Select the number of days you would like the auction to
                                        run for.</span>
                                </div>


                            {{-- </div>

                            <div class="row"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>Property Style Buyer is looking for: <span class="text-danger">*</span></label>

                                    <select name="property_type_id" class="form-select" required>
                                        @foreach ($property_types as $pt)
                                            <option value="{{$pt->id}}" {{$auction->property_type_id==$pt->id?"selected":""}} >{{$pt->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('property_type_id'))
                                        <div class="small error">{{ $errors->first('property_type_id') }}</div>
                                    @endif
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Bedrooms Needed: <span class="text-danger">*</span></label>
                                    <select name="bedrooms" class="form-select" required>
                                        @foreach ($bedrooms as $bedroom)
                                            <option value="{{$bedroom->name}}"  {{$auction->bedrooms ==$bedroom->name ?"selected":""}}>{{$bedroom->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('bedrooms'))
                                        <div class="small error">{{ $errors->first('bedrooms') }}</div>
                                    @endif
                                </div>


                                <div class="form-group col-md-3 mt-4">
                                    <label>Bathrooms Needed: <span class="text-danger">*</span></label>
                                    <select name="bathrooms" class="form-select" required>
                                        @foreach ($bathrooms as $bathroom)
                                            <option value="{{$bathroom->name}}" {{$auction->bathrooms ==$bathroom->name ?"selected":""}} >{{$bathroom->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('bathrooms'))
                                        <div class="small error">{{ $errors->first('bathrooms') }}</div>
                                    @endif
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Minimum Sqft Needed:</label>
                                    <input type="number" name="sqft" class="form-control" value="{{$auction->sqft}}">
                                    <small class="text-small">(Optional)</small>
                                </div>

                            {{-- </div>



                            <div class="row"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>
                                        Cities interested in:
                                        {{-- <span class="text-danger">*</span> --}}
                                    </label>
                                    <select name="city_ids[]" class="form-select multiple" multiple placeholder="Select Cities"
                                        required>
                                        @foreach ($cities as $city)
                                            <option value="{{ $city->id }}"
                                                @if($auction->cities)
                                                {{ in_array($city->id, $auction->cities->pluck('city_id')->toArray()) ? 'selected' : '' }}
                                                @endif
                                                >{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('city_ids'))
                                        <div class="small error">{{ $errors->first('city_ids') }}</div>
                                    @endif
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>
                                        Counties interested in:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="county_ids[]" class="form-select multiple" multiple placeholder="Select Counties"
                                        required>
                                        @foreach ($counties as $county)
                                            <option value="{{ $county->id }}"
                                                @if($auction->counties)
                                                {{ in_array($county->id, $auction->counties->pluck('county_id')->toArray()) ? 'selected' : '' }}
                                                @endif
                                                >{{ $county->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('county_ids'))
                                        <div class="small error">{{ $errors->first('county_ids') }}</div>
                                    @endif
                                </div>

                                <div class="form-group mt-4 col-md-3">
                                    <label>
                                        State
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="state_id" class="form-select" required>
                                        {{-- <option value="">Select City</option> --}}
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}"
                                                {{ $auction->state_id == $state->id ? 'selected' : '' }}>
                                                {{ ucwords($state->name) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('state_id'))
                                        <div class="small error">{{ $errors->first('state_id') }}</div>
                                    @endif
                                </div>


                            {{-- </div>

                            <div class="row mt-4"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>Financing/ Currency Offered: <span class="text-danger">*</span></label>
                                    <select name="financing_id[]" multiple class="form-select multiple" required>
                                        @foreach ($financings as $financing)
                                            <option value="{{$financing->id}}" {{$auction->financing_id?in_array($financing->id,json_decode($auction->financing_id))?"selected":"":""}} >{{$financing->name}}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- <div class="form-group col-md-4 mt-4">
                                    <label>If Buyer selects loan, is Buyer pre-approved?</label>
                                    <input type="text" name="loan_pre_approved" class="form-control" />
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-4 mt-4">
                                    <label>How much is Buyer’s preapproval amount? </label>
                                    <input type="text" name="preapproval_amount" class="form-control" />
                                    <small class="text-small">(Optional)</small>
                                </div> --}}
                            {{-- </div>


                            <div class="row mt-4"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>Pool:</label>
                                    <select name="pool" class="form-select" >
                                        <option value="Yes" {{($auction->pool=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->pool=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Carport:</label>
                                    <select name="carport" class="form-select" >
                                        <option value="Yes" {{($auction->carport=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->carport=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Garage:</label>
                                    <select name="garage" class="form-select" >
                                        <option value="Yes" {{($auction->garage=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->garage=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Garage Spaces:</label>
                                    <select name="garage_spaces" class="form-select" >
                                        <option value="1+" {{($auction->garage_spaces=="1+")?"selected":""}} >1+</option>
                                        <option value="2+" {{($auction->garage_spaces=="2+")?"selected":""}} >2+</option>
                                        <option value="3+" {{($auction->garage_spaces=="3+")?"selected":""}} >3+</option>
                                        <option value="4+" {{($auction->garage_spaces=="4+")?"selected":""}} >4+</option>
                                        <option value="5+" {{($auction->garage_spaces=="5+")?"selected":""}} >5+</option>
                                        <option value="6+" {{($auction->garage_spaces=="6+")?"selected":""}} >6+</option>
                                        <option value="7+" {{($auction->garage_spaces=="7+")?"selected":""}} >7+</option>
                                        <option value="8+" {{($auction->garage_spaces=="8+")?"selected":""}} >8+</option>
                                        <option value="9+" {{($auction->garage_spaces=="9+")?"selected":""}} >9+</option>
                                        <option value="10+" {{($auction->garage_spaces=="10+")?"selected":""}} >10+</option>
                                    </select>
                                    {{-- <small class="text-small">(Optional)</small> --}}
                                </div>
                            {{-- </div>


                            <div class="row mt-4"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>Water View:</label>
                                    <select name="water_view" class="form-select" >
                                        <option value="Yes" {{($auction->water_view=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->water_view=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Water View Needed:</label>
                                    <select name="water_view_ids[]" class="form-select multiple" multiple >
                                        @foreach ($water_views as $wv)
                                        <option value="{{$wv->id}}" @if($auction->water_views) {{(in_array($wv->id, $auction->water_views->pluck('water_view_id')->toArray()))?"selected":""}} @endif >{{$wv->name}}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Water Extra:</label>
                                    <select name="water_extra" class="form-select" >
                                        <option value="Yes" {{($auction->water_extra=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->water_extra=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>
                            {{-- </div>



                            <div class="row mt-4"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>Water Extras:</label>
                                    <select name="water_extras[]" class="form-select multiple" multiple >
                                        @foreach ($water_extras as $we)
                                        <option value="{{$we->id}}" @if($auction->water_extras) {{(in_array($we->id,$auction->water_extras->pluck('water_extra_id')->toArray()))?"selected":""}} @endif>{{$we->name}}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>HOA/Community Association:</label>
                                    <select name="hoa" class="form-select" >
                                        <option value="Yes" {{($auction->hoa=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->hoa=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>HOA Fee Requirement:</label>
                                    <select name="hoa_fee_requirement" class="form-select" >
                                        <option value="Yes" {{($auction->hoa_fee_requirement=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->hoa_fee_requirement=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>
                            {{-- </div>


                            <div class="row mt-4"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>Maximum HOA fee (Monthly rate):</label>
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <i class="fa fa-dollar" id="basic-addon1"></i>
                                        </div>
                                        <input type="text" name="max_hoa_fee" class="form-control" value="{{$auction->max_hoa_fee}}" />

                                    </div>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Condo:</label>
                                    <select name="condo" class="form-select" >
                                        <option value="Yes" {{($auction->condo=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->condo=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Condo Fee Requirement:</label>
                                    <select name="max_condo_fee" class="form-select" >
                                        <option value="Yes" {{($auction->max_condo_fee=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->max_condo_fee=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>
                            {{-- </div>


                            <div class="row mt-4"> --}}
                                <div class="form-group col-md-3 mt-4">
                                    <label>55+ over community:</label>
                                    <select name="old_community" class="form-select" >
                                        <option value="Yes" {{($auction->old_community=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->old_community=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="form-group col-md-3 mt-4">
                                    <label>Pets Allowed:</label>
                                    <select name="pets_allowed" class="form-select" >
                                        <option value="Yes" {{($auction->pets_allowed=="Yes")?"selected":""}} >Yes</option>
                                        <option value="No" {{($auction->pets_allowed=="No")?"selected":""}} >No</option>
                                    </select>
                                    <small class="text-small">(Optional)</small>
                                </div>

                                <div class="col-md-12 form-group mt-4">
                                    <label>Any additional details that buyer wants to include:</label>
                                    <textarea name="description" class="form-control" >{{$auction->description}}</textarea>
                                </div>

                            </div>



                            <div class="card mt-4">
                                <div  class="card-header text-white bg-secondary">If Buyer has pets fill out the following:</div>
                                <div class="card-body p-2">
                                    <div class="row mt-4">
                                        <div class="form-group col-md-4">
                                            <label>How many pets does the Buyer have?</label>
                                            <input type="text" name="number_of_pets" class="form-control" value="{{$auction->number_of_pets}}">
                                            <small class="text-small">(Optional)</small>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label>What is the breed?</label>
                                            <input type="text" name="pets_breed" class="form-control" value="{{$auction->pets_breed}}">
                                            <small class="text-small">(Optional)</small>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label>What is the weight?</label>
                                            <input type="text" name="pets_weight" class="form-control" value="{{$auction->pets_weight}}">
                                            <small class="text-small">(Optional)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="card mt-4">
                                <div  class="card-header text-white bg-secondary">If Buyer is an investor looking to purchase an investment property, please fill out the following:</div>
                                <div class="card-body p-2">
                                        <div class="row mt-4">
                                            <div class="form-group col-md-12">
                                                <label>Rental Requirements: (List any requirements you need): Example: home located in a short-term rental zone that allows for Airbnbs</label>
                                                <textarea name="rental_requirements" class="form-control">{{$auction->rental_requirements}}</textarea>
                                                <small class="text-small">(Optional)</small>
                                            </div>
                                        </div>


                                        <div class="row mt-4">
                                            <div class="form-group col-md-4">
                                                <label>Total Number of Units Needed:</label>
                                                <input type="text" name="units_needed" class="form-control" value="{{$auction->units_needed}}">
                                                <small class="text-small">(Optional)</small>
                                            </div>

                                            <div class="form-group col-md-4">
                                                <label>Annual Net Income Minimum:</label>
                                                <div class="input-group">
                                                    <div class="input-group-text">
                                                        <i class="fa fa-dollar" id="basic-addon1"></i>
                                                    </div>
                                                <input type="text" name="anual_income" class="form-control" value="{{$auction->anual_income}}">
                                                </div>
                                                <small class="text-small">(Optional)</small>
                                            </div>

                                            <div class="form-group col-md-4">
                                                <label>Minimum Cap Rate:</label>
                                                <div class="input-group">
                                                    <div class="input-group-text">
                                                        <i class="fa fa-dollar" id="basic-addon1"></i>
                                                    </div>
                                                <input type="text" name="min_cap_rate" class="form-control" value="{{$auction->min_cap_rate}}">
                                                </div>
                                                <small class="text-small">(Optional)</small>
                                            </div>
                                        </div>

                                        <div class="row mt-4 d-none">
                                            <div class="form-group col-md-12">
                                                <label>Any additional details:</label>
                                                <textarea name="additional_details" class="form-control">{{$auction->additional_details}}</textarea>
                                                <small class="text-small">(Optional)</small>
                                            </div>
                                        </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div  class="card-header text-white bg-secondary">Buyer/Buyer’s Agent Info</div>
                                <div class="card-body p-2">
                                    <div class="row">
                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Full Name:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="buyer_name" class="form-control" required value="{{$auction->buyer_name}}">

                                        </div>


                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Brokerage:
                                                <span class="text-danger">*</span>
                                            </label>
                                            {{-- <div class="input-group">
                                                <div class="input-group-text">
                                                    <i class="fa fa-dollar" id="basic-addon1"></i>
                                                </div> --}}
                                            <input type="text" name="buyer_brokerage" placeholder="" required class="form-control"
                                                value="{{ $auction->buyer_brokerage }}" />
                                            {{-- </div> --}}
                                        </div>

                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                License Number:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="buyer_license_no" placeholder="" required class="form-control"
                                                value="{{ $auction->buyer_license_no }}" />
                                        </div>
                                    </div>


                                    <div class="row">
                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Phone:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="buyer_phone" class="form-control" required value="{{$auction->buyer_phone}}">

                                        </div>


                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Email:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="buyer_email" placeholder="" required class="form-control"
                                                value="{{ $auction->buyer_email }}" />
                                        </div>

                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                MLS ID:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="buyer_mls_id" placeholder="" required class="form-control"
                                                value="{{ $auction->buyer_mls_id }}" />
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="card mt-4">
                                <div  class="card-header text-white bg-secondary">Terms offered by Buyer</div>
                                <div class="card-body p-2">
                                    <div class="row">

                                        <div class="col-md-4 form-group mt-4">
                                            <label>Escrow Amount Offered: $ or % <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-text p-0">
                                                    <select name="escrow_amount_in" class="form-select" required style="background-color: #e9ecef; border:0;">
                                                        <option value="$" {{$auction->escrow_amount_in=="$"?"selected":""}}>$</option>
                                                        <option value="%" {{$auction->escrow_amount_in=="%"?"selected":""}}>%</option>
                                                    </select>
                                                </div>
                                                <input type="number" placeholder="0.00" name="escrow_amount" value="{{$auction->escrow_amount}}" class="form-control">
                                            </div>
                                        </div>

                                        <div class="col-md-4 form-group mt-4">
                                            <label>Inspection Period Days Offered: <span class="text-danger">*</span></label>
                                            <input type="text" name="inspection_period" value="{{$auction->inspection_period}}" required class="form-control">
                                        </div>

                                        <div class="col-md-4 form-group mt-4">
                                            <label>Days Needed to Close: <span class="text-danger">*</span></label>
                                            <input type="text" name="closing_days" value="{{$auction->closing_days}}" required class="form-control">
                                        </div>

                                        <div class="col-md-4 form-group mt-4">
                                            <label>Contingencies: <span class="text-danger">*</span></label>
                                            <input type="text" name="contingencies" value="{{$auction->contingencies}}" required class="form-control">
                                        </div>


                                        <div class="col-md-4 form-group mt-4">
                                            <label>Seller's Premium:</label>
                                            <div class="input-group">
                                                <div class="input-group-text p-0">
                                                    <select name="seller_premium_in" class="form-select" style="background-color: #e9ecef; border:0;">
                                                        <option value="$" {{$auction->seller_premium_in=="$"?"selected":""}}>$</option>
                                                        <option value="%" {{$auction->seller_premium_in=="%"?"selected":""}}>%</option>
                                                    </select>
                                                </div>
                                                <input type="number" placeholder="0.00" name="seller_premium" value="{{$auction->buyer_premium}}" class="form-control">
                                            </div>
                                            <small class="text-small">(Optional)</small>
                                        </div>

                                        <div class="col-md-4 form-group mt-4">
                                            <label>Buyer's Premium:</label>
                                            <div class="input-group">
                                                <div class="input-group-text p-0">
                                                    <select name="buyer_premium_in" class="form-select" style="background-color: #e9ecef; border:0;">
                                                        <option value="$" {{$auction->buyer_premium_in=="$"?"selected":""}}>$</option>
                                                        <option value="%" {{$auction->buyer_premium_in=="%"?"selected":""}}>%</option>
                                                    </select>
                                                </div>
                                                <input type="number" placeholder="0.00" name="buyer_premium" value="{{$auction->buyer_premium}}" class="form-control">
                                            </div>
                                            <small class="text-small">(Optional)</small>
                                        </div>

                                    </div>
                                </div>
                            </div>





                            <div class="form-group mt-4 text-right">
                                <button class="btn btn-lg text-600 btn-success" type="submit">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js"></script>
    <script>
        function changeAuctionType(v) {
            if (v == "Normal") {
                $('.auction_length').val("1");
                $('.traditional-length').addClass('d-none');
                $('.normal-length').removeClass('d-none');
            } else {
                $('.auction_length').val("-1");
                $('.traditional-length').removeClass('d-none');
                $('.normal-length').addClass('d-none');
            }
        }
        document.getElementById('auction_type').change();
    </script>
    <script>
        $(function () {
            $('.datetimepicker').datetimepicker({});
            $(document).ready(function () {
                var multipleCancelButton = new Choices('.multiple', {
                    removeItemButton: true,
                    // maxItemCount:5,
                    // searchResultLimit: 5,
                    // renderChoiceLimit: 5
                });
            });
        });
    </script>
@endpush
