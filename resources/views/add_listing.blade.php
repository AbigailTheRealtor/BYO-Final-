@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="assets/css/addListing.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
    <style>
        .choices__list {
            z-index: 999;
        }
    </style>
@endpush
@section('content')
    <div class="container addListing">
        <div class="card">
            <div class="row">
                <div class="col-sm-12 col-md-4 col lg-4">
                    <div class="position-relative leftCol">
                        <div class="overlay"></div>
                        <div class="bg-none container position-absolute bottom-0 mb-3">
                            <div class="bg-white shadow text-dark mb-4 p-3 rounded-2">
                                <div class="mb-2"><i class="fa fa-info-circle me-2"></i> Quick Tip</div>
                                <p>Increase your page views with a catchy title!</p>
                            </div>
                            <button class="btn border-1 border-dark w-100 text-white p-3" type="button">Save</button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12 col-md-8 col-lg-8 position-relative">
                    <!-- Tab panes -->
                    <div class="tab-content basic">



                        <!-- Tab1  -->
                        <div class="tab-pane container {{ $step == 1 ? 'active' : '' }} p-0  pb-2" id="home">
                            <form action="{{ route('save-pl-step1') }}" method="POST">
                                @csrf
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="update" value="{{ $update }}">
                                <div class="rightBody container">
                                    <div class="title">
                                        <span class="title-number">01</span>
                                        {{ $id > 0 ? 'Update Auction' : 'Create a new auction' }}
                                    </div>
                                    <div class="form-group mt-4 d-none">
                                        <label>
                                            Seller
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" name="seller_id" id="seller_id">
                                            @inject('users', 'App\Models\User')
                                            @foreach (auth()->user()->sellers as $seller)
                                                <option value="{{ $seller->user->id }}"
                                                    {{ @$auction->seller_id == $seller->user->id ? 'selected' : '' }}>
                                                    {{ $seller->user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mt-4">
                                        <label>
                                            Add Title / Address.
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="input" name="address" id="form_title" placeholder="eg. Used Sony TV"
                                            class="form-control" required value="{{ @$auction->address }}" />
                                    </div>

                                    <div class="form-group">{{-- autocomplete_search --}}
                                        <label>
                                            City
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="city_id" class="form-control multiselect" placeholder="Select City"
                                            required>
                                            @foreach ($cities as $city)
                                                <option value="{{ $city->id }}"
                                                    {{ @$auction->city_id == $city->id ? 'selected' : '' }}>
                                                    {{ $city->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>


                                    <div class="form-group mt-4">
                                        <label>
                                            State
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="state" id="autocomplete_search"
                                            class="autocomplete_search form-control">
                                    </div>

                                    <div class="form-group mt-4">
                                        <label>
                                            State
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="state_id" class="form-control" required>
                                            {{-- <option value="">Select City</option> --}}
                                            @foreach ($states as $state)
                                                <option value="{{ $state->id }}"
                                                    {{ @$auction->state_id == $state->id ? 'selected' : '' }}>
                                                    {{ ucwords($state->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- <label>Property Type <span class="text-danger">*</span> </label>
                                    <div class="my-2">
                                        <select class="multiselect" placeholder="Select Property Type" multiple
                                            required>
                                            <option value="Townhouse">Townhouse</option>
                                            <option value="multiFamily">Multi-family</option>
                                            <option value="Condo">Condo</option>
                                            <option value="Land/Lots">Land/Lots</option>
                                            <option value="Apartments">Apartments</option>
                                            <option value="Manufactured">Manufactured</option>
                                            <option value="Javascript">Javascript</option>
                                            <option value="Condo-Hotel">Condo-Hotel</option>
                                            <option value="Villa">Villa</option>
                                            <option value="Single Family Home">Single Family Home</option>
                                        </select>
                                    </div> --}}

                                    {{-- <div class="form-group mt-4">
                                        <label class="mt-2">Where are you located? </label>
                                        <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2">
                                            <div class="d-grid text-center my-4" data-bs-toggle="modal"
                                                data-bs-target="#myLocation"><i class="fa fa-map-marker fs-3"></i><span
                                                    class="small opacity-50 mt-2">Change</span></div>
                                        </div>
                                    </div> --}}
                                </div>
                                <div class="rightFooter">
                                    <button class="btn btn-lg text-600 btnNext position-absolute" type="submit">Next <i
                                            class="fa fa-arrow-right ms-2"></i></button>
                                </div>
                            </form>
                        </div>




                        <!-- Tab2  -->
                        <div class="tab-pane container p-0  {{ $step == 2 ? 'active' : '' }} pb-2" id="menu1">
                            <form action="{{ route('save-pl-step2') }}" method="POST">
                                @csrf
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="update" value="{{ $update }}">
                                <div class="rightBody container">
                                    <div class="title">
                                        <span class="title-number">02</span>
                                        Description
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="d-flex justify-content-between">
                                            <label>
                                                Description
                                                <span class="text-danger">*</span>
                                            </label>
                                            <span class="opacity-50 small"><b>100</b> characters remaining</span>
                                        </div>
                                        <textarea name="description" class="form-control rounded-0 required-field" required>{{ @$auction->description }}</textarea>
                                        @if ($errors->has('description'))
                                            <div class="small error">{{ $errors->first('description') }}</div>
                                        @endif
                                    </div>
                                    <label>Keywords <span class="text-danger">*</span> </label>
                                    <div class="my-2">
                                        <input type="text" name="keywords" class="form-control mb-0"
                                            value="{{ @$auction->keywords }}" required>
                                        @if ($errors->has('keywords'))
                                            <div class="small error">{{ $errors->first('keywords') }}</div>
                                        @endif
                                        <span class="small opacity-50">Add key words of the best property features, So
                                            People
                                            can search for
                                            them. Separate each keyword with a comma. Sample keywords: Pool, Waterfront,
                                            Garage,
                                            Dock,
                                            etc</span>
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <label class="me-2 fs-4">
                                                Bedrooms <span class="text-danger">*</span>
                                            </label>
                                            <div class="header-divider"></div>
                                        </div>
                                        @if ($errors->has('bedroom_id'))
                                            <div class="small error">{{ $errors->first('bedroom_id') }}</div>
                                        @endif
                                        {{-- <select class="form-control" name="bedroom_id" > --}}
                                        @foreach ($bedrooms as $bedroom)
                                            {{-- <option value="{{$bedroom->id}}">{{$bedroom->name}}</option> --}}
                                            <label class="m-2">{{ $bedroom->name }} <input type="radio"
                                                    name="bedroom_id" value="{{ $bedroom->id }}"
                                                    {{ @$auction->bedroom_id == $bedroom->id ? 'checked' : '' }}></label>
                                        @endforeach
                                        {{-- </select> --}}
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <label class="me-2 fs-4">
                                                Bathrooms <span class="text-danger">*</span>
                                            </label>
                                            <div class="header-divider"></div>
                                        </div>
                                        @if ($errors->has('bathroom_id'))
                                            <div class="small error">{{ $errors->first('bathroom_id') }}</div>
                                        @endif
                                        <div>
                                            @foreach ($bathrooms as $bathroom)
                                                <label class="m-2">{{ $bathroom->name }} <input type="radio"
                                                        name="bathroom_id" value="{{ $bathroom->id }}"
                                                        {{ @$auction->bathroom_id == $bathroom->id ? 'checked' : '' }}></label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="rightFooter">
                                    <a href="{{ route('add-listing') . '?step=1&id=' . $id }}"
                                        class="btn btn-lg text-600 btnPrevious position-absolute" type="button"><i
                                            class="fa fa-arrow-left me-2"></i> Previous
                                    </a>
                                    <button class="btn btn-lg text-600 btnNext position-absolute " type="submit">Next <i
                                            class="fa fa-arrow-right ms-2"></i></button>
                                </div>

                            </form>

                        </div>











                        <!-- Tab3  -->
                        <div class="tab-pane container {{ $step == 3 ? 'active' : '' }}" id="menu2">
                            <form action="{{ route('save-pl-step3') }}" method="POST">
                                @csrf
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="update" value="{{ $update }}">
                                <div class="rightBody container row">
                                    <div class="title">
                                        <span class="title-number">03</span>
                                        Details
                                    </div>
                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <label class="me-2 fs-4">
                                            Details
                                        </label>
                                        <div class="header-divider"></div>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Financing Accepted
                                        </label>
                                        <div>
                                            <select class="multiselect" name="financing_ids[]"
                                                placeholder="Select Financing Accepted" multiple>
                                                @foreach ($financings as $financing)
                                                    <option value="{{ $financing->id }}"
                                                        {{ @$auction->financing ? (in_array($financing->id, @$auction->financing->pluck('financing_id')->toArray()) == 'Yes' ? 'selected' : '') : null }}>
                                                        {{ $financing->name }}</option>
                                                    {{-- <label class="col-md-3"><input type="checkbox" name="financing_id[]" > <span style="font-weight: normal;">{{$financing->name}}</span></label> --}}
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Auction Type
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select mb-0" name="autcion_type" id="autcion_type"
                                            onchange="changeAuctionType(this.value);">
                                            <option value="Normal"
                                                {{ @$auction->autcion_type == 'Normal' ? 'selected' : '' }}>
                                                Normal (Timer)</option>
                                            <option value="Traditional"
                                                {{ @$auction->autcion_type == 'Traditional' ? 'selected' : '' }}>
                                                Traditional (No
                                                Timer)</option>
                                        </select>
                                        @if ($errors->has('autcion_type'))
                                            <div class="small error">{{ $errors->first('autcion_type') }}</div>
                                        @endif
                                        <span class="opacity-50 small">Here you can choose the format of your
                                            auction.</span>
                                    </div>
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
                                        window.onload = (event) => {
                                            $('autcion_type').change();
                                        };
                                    </script>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Auction Length
                                            <span class="text-danger auction-length-required">*</span>
                                        </label>
                                        <select class="form-select mb-0 auction_length" name="auction_length" required>
                                            <option value="1" class="normal-length"
                                                {{ @$auction->auction_length == '1' ? 'selected' : '' }}>1 Day</option>
                                            <option value="3" class="normal-length"
                                                {{ @$auction->auction_length == '3' ? 'selected' : '' }}>3 Days</option>
                                            <option value="5" class="normal-length"
                                                {{ @$auction->auction_length == '5' ? 'selected' : '' }}>5 Days</option>
                                            <option value="7" class="normal-length"
                                                {{ @$auction->auction_length == '7' ? 'selected' : '' }}>7 Days</option>
                                            <option value="10" class="normal-length"
                                                {{ @$auction->auction_length == '10' ? 'selected' : '' }}>10 Days</option>
                                            <option value="14" class="normal-length"
                                                {{ @$auction->auction_length == '14' ? 'selected' : '' }}>14 Days</option>
                                            <option value="21" class="normal-length"
                                                {{ @$auction->auction_length == '21' ? 'selected' : '' }}>21 Days</option>
                                            <option value="30" class="normal-length"
                                                {{ @$auction->auction_length == '30' ? 'selected' : '' }}>30 Days</option>
                                            <option value="45" class="normal-length"
                                                {{ @$auction->auction_length == '45' ? 'selected' : '' }}>45 Days</option>
                                            <option value="60" class="normal-length"
                                                {{ @$auction->auction_length == '60' ? 'selected' : '' }}>60 Days</option>
                                            <option value="-1" class="traditional-length d-none"
                                                {{ @$auction->auction_length == '-1' ? 'selected' : '' }}>No time limit
                                            </option>
                                        </select>
                                        @if ($errors->has('auction_length'))
                                            <div class="small error">{{ $errors->first('auction_length') }}</div>
                                        @endif
                                        <span class="opacity-50 small">Select the number of days you would like the auction
                                            to
                                            run for.</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Starting Price
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="d-flex align-items-baseline">
                                            <i class="fa fa-dollar" id="basic-addon1"></i>
                                            <input type="number" class="form-control border-start-0 mb-0"
                                                name="starting_price" min="0" required placeholder="0.00"
                                                value="{{ @$auction->starting_price }}">
                                        </div>
                                        @if ($errors->has('starting_price'))
                                            <div class="small error">{{ $errors->first('starting_price') }}</div>
                                        @endif
                                        <span class="opacity-50 small">This is the price the bidding will start at.</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Buy Now Price
                                        </label>
                                        <div class="d-flex align-items-baseline">
                                            <i class="fa fa-dollar" id="basic-addon1"></i>
                                            <input type="number" name="buy_now_price"
                                                class="form-control border-start-0 mb-0" min="0"
                                                placeholder="0.00" value="{{ @$auction->buy_now_price }}">
                                        </div>
                                        <span class="opacity-50 small">Here you can set a price for the user to buy this
                                            item
                                            outright.</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Reserve Price
                                        </label>
                                        <div class="d-flex align-items-baseline">
                                            <i class="fa fa-dollar" id="basic-addon1"></i>
                                            <input type="number" class="form-control border-start-0 mb-0"
                                                name="reserve_price" min="0" placeholder="0.00"
                                                value="{{ @$auction->reserve_price }}">
                                        </div>
                                        <span class="opacity-50 small">Here you can set the lowest price your willing to
                                            sell
                                            this item
                                            for.</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Type of Property
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" name="property_type_id">
                                            @foreach ($property_types as $pt)
                                                <option value="{{ $pt->id }}"
                                                    @if (@$auction->property_type) {{ in_array($pt->id, @$auction->property_type->pluck('property_type_id')->toArray()) ? 'selected' : '' }} @endif>
                                                    {{ $pt->name }}</option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('property_type_id'))
                                            <div class="small error">{{ $errors->first('property_type_id') }}</div>
                                        @endif
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Heated Sqft
                                        </label>
                                        <input type="number" name="heated_sqft" class="form-control mb-0"
                                            value="{{ @$auction->heated_sqft }}">
                                        <span class="opacity-50 small">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Year Built
                                        </label>
                                        <input type="text" name="year_built" class="form-control mb-0"
                                            value="{{ @$auction->year_built }}">
                                        <span class="opacity-50 small">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            County
                                        </label>
                                        <select class="form-control" name="county_id">
                                            <option value="">Select County</option>
                                            @foreach ($counties as $county)
                                                <option value="{{ $county->id }}"
                                                    {{ @$auction->county_id == $county->id ? 'selected' : '' }}>
                                                    {{ $county->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Air Conditioning Type
                                        </label>
                                        <select class="multiselect" name="ac_type_id[]"
                                            placeholder="Select Air Conditioning Type" multiple>
                                            @foreach ($ac_types as $ac_type)
                                                <option value="{{ $ac_type->id }}"
                                                    {{ @$auction->ac_types ? (in_array($ac_type->id, @$auction->ac_types->pluck('air_conditioning_type_id')->toArray()) == 'Yes' ? 'selected' : '') : null }}>
                                                    {{ $ac_type->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Heating/Fuel
                                        </label>
                                        <select class="multiselect" name="fuel_id[]" placeholder="Select Heating/Fuel"
                                            multiple>
                                            @foreach ($heating_fuel as $hf)
                                                <option value="{{ $hf->id }}"
                                                    {{ @$auction->fuel ? (in_array($hf->id, @$auction->fuel->pluck('heating_fuel_id')->toArray()) == 'Yes' ? 'selected' : '') : null }}>
                                                    {{ $hf->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>Special Sale Provision:</label>
                                        <select name="sale_provision" class="form-select">
                                            <option value="Auction"
                                                {{ @$auction->sale_provision == 'Auction' ? 'selected' : '' }}>Auction
                                            </option>
                                            <option value="Bank Owned/REO"
                                                {{ @$auction->sale_provision == 'Bank Owned/REO' ? 'selected' : '' }}>Bank
                                                Owned/REO</option>
                                            <option value="Government Owned"
                                                {{ @$auction->sale_provision == 'Government Owned' ? 'selected' : '' }}>
                                                Government Owned</option>
                                            <option value="Probate Listing"
                                                {{ @$auction->sale_provision == 'Probate Listing' ? 'selected' : '' }}>
                                                Probate
                                                Listing</option>
                                            <option value="Short Sale"
                                                {{ @$auction->sale_provision == 'Short Sale' ? 'selected' : '' }}>Short
                                                Sale
                                            </option>
                                            <option value="None"
                                                {{ @$auction->sale_provision == 'None' ? 'selected' : '' }}>
                                                None</option>
                                            <option value="Assignment Contract (Wholesale)"
                                                {{ @$auction->sale_provision == 'Assignment Contract (Wholesale)' ? 'selected' : '' }}>
                                                Assignment Contract (Wholesale)</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>Listing Service Type:</label>
                                        <select name="service_type" class="form-select">
                                            <option value="Full Service"
                                                {{ @$auction->service_type == 'Full Service' ? 'selected' : '' }}>Full
                                                Service
                                            </option>
                                            <option value="Limited Service"
                                                {{ @$auction->service_type == 'Limited Service' ? 'selected' : '' }}>
                                                Limited
                                                Service</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Pool
                                        </label>
                                        <select class="form-select mb-0" name="pool">
                                            <option value="Yes" {{ @$auction->pool == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No" {{ @$auction->pool == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="opacity-50 small">(Yes or No)</span>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Pool
                                        </label>
                                        <select class="form-select mb-0" name="pool_type">
                                            <option value="Private"
                                                {{ @$auction->pool_type == 'Private' ? 'selected' : '' }}>
                                                Private</option>
                                            <option value="Community"
                                                {{ @$auction->pool_type == 'Community' ? 'selected' : '' }}>Community
                                            </option>
                                        </select>
                                        <span class="opacity-50 small">(Private or Community)</span>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Carport
                                        </label>
                                        <select class="form-control mb-0" name="carport">
                                            <option value="Yes" {{ @$auction->carport == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No" {{ @$auction->carport == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="opacity-50 small">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Garage
                                        </label>
                                        <select class="form-control mb-0" name="garage">
                                            <option value="Yes" {{ @$auction->garage == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No" {{ @$auction->garage == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="opacity-50 small">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Garage Spaces
                                        </label>
                                        <select name="garage_spaces" class="form-control">
                                            <option value="1"
                                                {{ @$auction->garage_spaces == '1' ? 'selected' : '' }}>1
                                            </option>
                                            <option value="2"
                                                {{ @$auction->garage_spaces == '2' ? 'selected' : '' }}>2
                                            </option>
                                            <option value="3"
                                                {{ @$auction->garage_spaces == '3' ? 'selected' : '' }}>3
                                            </option>
                                            <option value="4"
                                                {{ @$auction->garage_spaces == '4' ? 'selected' : '' }}>4
                                            </option>
                                            <option value="5"
                                                {{ @$auction->garage_spaces == '5' ? 'selected' : '' }}>5
                                            </option>
                                            <option value="6"
                                                {{ @$auction->garage_spaces == '6' ? 'selected' : '' }}>6
                                            </option>
                                            <option value="7"
                                                {{ @$auction->garage_spaces == '7' ? 'selected' : '' }}>7
                                            </option>
                                            <option value="8"
                                                {{ @$auction->garage_spaces == '8' ? 'selected' : '' }}>8
                                            </option>
                                            <option value="9"
                                                {{ @$auction->garage_spaces == '9' ? 'selected' : '' }}>9
                                            </option>
                                            <option value="10+"
                                                {{ @$auction->garage_spaces == '10+' ? 'selected' : '' }}>10+
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Water View
                                        </label>
                                        <select class="form-control mb-0" name="water_view">
                                            <option value="Yes"
                                                {{ @$auction->water_view == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No" {{ @$auction->water_view == 'No' ? 'selected' : '' }}>
                                                No
                                            </option>
                                        </select>
                                        <span class="opacity-50 small">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Water View
                                        </label>
                                        <select class="multiselect" name="water_view_id[]"
                                            placeholder="Select Water View" multiple>
                                            @foreach ($water_views as $wv)
                                                <option value="{{ $wv->id }}"
                                                    {{ @$auction->water_view_types ? (in_array($wv->id, @$auction->water_view_types->pluck('water_view_type_id')->toArray()) == 'Yes' ? 'selected' : '') : null }}>
                                                    {{ $wv->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Water Extras
                                        </label>
                                        <select class="form-control mb-0" name="water_extras">
                                            <option value="Yes"
                                                {{ @$auction->water_extra == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No"
                                                {{ @$auction->water_extra == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="opacity-50 small">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Water Extras
                                        </label>
                                        <select class="multiselect" name="water_extra_id[]"
                                            placeholder="Select Water Extras" multiple>
                                            @foreach ($water_extras as $we)
                                                <option value="{{ $we->id }}"
                                                    {{ @$auction->water_extra ? (in_array($we->id, @$auction->water_extra->pluck('water_extra_id')->toArray()) == 'Yes' ? 'selected' : '') : null }}>
                                                    {{ $we->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Appliances Included
                                        </label>
                                        <select class="multiselect" name="appliance_id[]"
                                            placeholder="Select Appliances Included" multiple>
                                            @foreach ($appliances as $appliance)
                                                <option value="{{ $appliance->id }}"
                                                    {{ @$auction->appliance ? (in_array($appliance->id, @$auction->appliance->pluck('appliance_id')->toArray()) == 'Yes' ? 'selected' : '') : null }}>
                                                    {{ $appliance->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            HOA/Community Association
                                        </label>
                                        <select class="form-select mb-0" name="hoa_association">
                                            <option value="Yes"
                                                {{ @$auction->hoa_association == 'Yes' ? 'selected' : '' }}>
                                                Yes</option>
                                            <option value="No"
                                                {{ @$auction->hoa_association == 'No' ? 'selected' : '' }}>
                                                No</option>
                                        </select>
                                        <span class="opacity-50 small">(Yes or No)</span>
                                    </div>

                                    <div class="form-group col-md-12 mt-4">
                                        <label>
                                            If HOA: please provide name and contact information for the Association Manager:
                                        </label>
                                        <div>
                                            <input type="text" name="hoa_manager_contact" class="form-control"
                                                value="{{ @$auction->hoa_manager_contact }}" />
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            HOA Fee Requirement
                                        </label>
                                        <select class="form-select mb-0" name="hoa_fee_requirement">
                                            <option value="Required"
                                                {{ @$auction->hoa_fee_requirement == 'Required' ? 'selected' : '' }}>
                                                Required
                                            </option>
                                            <option value="Optional"
                                                {{ @$auction->hoa_fee_requirement == 'Optional' ? 'selected' : '' }}>
                                                Optional
                                            </option>
                                            <option value="No"
                                                {{ @$auction->hoa_fee_requirement == 'No' ? 'selected' : '' }}>No</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            HOA Fee
                                        </label>
                                        <input type="text" class="form-control mb-0" name="hoa_fee"
                                            value="{{ @$auction->hoa_fee }}">
                                        <span class="opacity-50 small">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            HOA Payment Schedule
                                        </label>
                                        <select class="form-select mb-0" name="hoa_payment_schedule">
                                            <option value="N/A"
                                                {{ @$auction->hoa_payment_schedule == 'N/A' ? 'selected' : '' }}>N/A
                                            </option>
                                            <option value="Annual"
                                                {{ @$auction->hoa_payment_schedule == 'Annual' ? 'selected' : '' }}>Annual
                                            </option>
                                            <option value="Monthly"
                                                {{ @$auction->hoa_payment_schedule == 'Monthly' ? 'selected' : '' }}>
                                                Monthly
                                            </option>
                                            <option value="Quarterly"
                                                {{ @$auction->hoa_payment_schedule == 'Quarterly' ? 'selected' : '' }}>
                                                Quarterly
                                            </option>
                                            <option value="Semi-Annually"
                                                {{ @$auction->hoa_payment_schedule == 'Semi-Annually' ? 'selected' : '' }}>
                                                Semi-
                                                Annually</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Condo Fee
                                        </label>
                                        <input type="text" class="form-control mb-0" name="condo_fee"
                                            value="{{ @$auction->condo_fee }}">
                                        <span class="opacity-50 small">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Condo Fee Schedule
                                        </label>
                                        <select class="form-select mb-0" name="condo_fee_schedule">
                                            <option value="Annual"
                                                {{ @$auction->condo_fee_schedule == 'Annual' ? 'selected' : '' }}>Annual
                                            </option>
                                            <option value="Semi-Annually"
                                                {{ @$auction->condo_fee_schedule == 'Semi-Annually' ? 'selected' : '' }}>
                                                Semi-
                                                Annually</option>
                                            <option value="Monthly"
                                                {{ @$auction->condo_fee_schedule == 'Monthly' ? 'selected' : '' }}>Monthly
                                            </option>
                                            <option value="Quarterly"
                                                {{ @$auction->condo_fee_schedule == 'Quarterly' ? 'selected' : '' }}>
                                                Quarterly
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Fee Includes
                                        </label>
                                        <select class="multiselect" name="fee_includes[]"
                                            placeholder="Select Fee Include" multiple>
                                            @foreach ($fee_includes as $fi)
                                                <option value="{{ $fi->id }}"
                                                    {{ @$auction->fee_includes ? (in_array($fi->id, @$auction->fee_includes->pluck('fee_include_id')->toArray()) ? 'selected' : '') : null }}>
                                                    {{ $fi->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            55+ over community
                                        </label>
                                        <select class="form-select mb-0" name="old_community">
                                            <option value="Yes"
                                                {{ @$auction->old_community == 'Yes' ? 'selected' : '' }}>
                                                Yes</option>
                                            <option value="No"
                                                {{ @$auction->old_community == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="small opacity-50">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Rental Restrictions
                                        </label>
                                        <select class="form-select mb-0" name="rental_restrictions">
                                            <option value="Yes"
                                                {{ @$auction->rental_restrictions == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No"
                                                {{ @$auction->rental_restrictions == 'No' ? 'selected' : '' }}>No</option>
                                        </select>
                                        <span class="small opacity-50">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-12 mt-4">
                                        <label>
                                            If Rental Restrictions list:
                                        </label>
                                        <textarea style="width:100%; height:100px !important;" name="rental_restrictions_desription"
                                            class="form-control mb-0">{{ @$auction->rental_restrictions_desription }}</textarea>
                                        <span class="small opacity-50">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Pets Allowed
                                        </label>
                                        <select class="form-select mb-0" name="pets_allowed">
                                            <option value="Yes"
                                                {{ @$auction->pets_allowed == 'Yes' ? 'selected' : '' }}>Yes
                                            </option>
                                            <option value="No"
                                                {{ @$auction->pets_allowed == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="small opacity-50">(Yes or No)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Number of Pets Allowed
                                        </label>
                                        <select name="number_of_pets_allowed" class="form-select mb-0">
                                            <option value="1"
                                                {{ @$auction->number_of_pets_allowed == '1' ? 'selected' : '' }}>1
                                            </option>
                                            <option value="2"
                                                {{ @$auction->number_of_pets_allowed == '2' ? 'selected' : '' }}>2
                                            </option>
                                            <option value="3"
                                                {{ @$auction->number_of_pets_allowed == '3' ? 'selected' : '' }}>3
                                            </option>
                                            <option value="4"
                                                {{ @$auction->number_of_pets_allowed == '4' ? 'selected' : '' }}>4
                                            </option>
                                            <option value="5"
                                                {{ @$auction->number_of_pets_allowed == '5' ? 'selected' : '' }}>5
                                            </option>
                                            <option value="6"
                                                {{ @$auction->number_of_pets_allowed == '6' ? 'selected' : '' }}>6
                                            </option>
                                            <option value="7"
                                                {{ @$auction->number_of_pets_allowed == '7' ? 'selected' : '' }}>7
                                            </option>
                                            <option value="8"
                                                {{ @$auction->number_of_pets_allowed == '8' ? 'selected' : '' }}>8
                                            </option>
                                            <option value="9"
                                                {{ @$auction->number_of_pets_allowed == '9' ? 'selected' : '' }}>9
                                            </option>
                                            <option value="10+"
                                                {{ @$auction->number_of_pets_allowed == '10+' ? 'selected' : '' }}>10+
                                            </option>
                                        </select>
                                        <span class="small opacity-50">Number of pets</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Max Pet Weight
                                        </label>
                                        <input type="text" class="form-control mb-0" name="max_pet_weight"
                                            value="{{ @$auction->max_pet_weight }}">
                                        <span class="small opacity-50">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-12 mt-4">
                                        <label>
                                            Pet restrictions
                                        </label>
                                        <textarea style="width:100%; height:100px !important;" name="pet_restrictions" class="form-control mb-0">{{ @$auction->mls_id }}</textarea>
                                        <span class="small opacity-50">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            MLS ID
                                        </label>
                                        <input type="text" name="mls_id" class="form-control mb-0"
                                            value="{{ @$auction->mls_id }}">
                                        <span class="small opacity-50">(Custom input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Is Property in a Flood Zone?
                                        </label>
                                        <select class="form-select" name="is_in_flood_zone">
                                            <option value="Yes"
                                                {{ @$auction->flood_zone_code == 'Yes' ? 'selected' : '' }}>
                                                Yes</option>
                                            <option value="No"
                                                {{ @$auction->flood_zone_code == 'No' ? 'selected' : '' }}>
                                                No</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            What is the flood zone code?
                                        </label>
                                        <input type="text" class="form-control mb-0" name="flood_zone_code"
                                            value="{{ @$auction->flood_zone_code }}">
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Floors in Unit/Home:
                                        </label>
                                        <input type="text" class="form-control mb-0" name="number_of_floors_in_unit"
                                            value="{{ @$auction->number_of_floors_in_unit }}">
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Total Number of Floors
                                        </label>
                                        <input type="text" class="form-control mb-0" name="total_number_of_floors"
                                            value="{{ @$auction->total_number_of_floors }}">
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Building Elevator
                                        </label>
                                        <select class="form-select mb-0" name="elevator">
                                            <option value="Yes" {{ @$auction->elevator == 'Yes' ? 'selected' : '' }}>
                                                Yes
                                            </option>
                                            <option value="No" {{ @$auction->elevator == 'No' ? 'selected' : '' }}>No
                                            </option>
                                        </select>
                                        <span class="small opacity-50">(Yes or No)</span>
                                    </div>


                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <label class="me-2 mt-5 fs-4">
                                            Income/commercial property Info:
                                        </label>
                                        <div class="header-divider"></div>
                                    </div>

                                    <div class="form-group col-md-12 mt-4">
                                        <label>
                                            Add in section for Income/Commercial Property info:
                                        </label>
                                        <textarea type="text" name="property_info" class="form-control mb-0">{{ @$auction->property_info }}</textarea>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Total number of buildings:
                                        </label>
                                        <input type="text" name="number_of_buildings" class="form-control mb-0"
                                            value="{{ @$auction->number_of_buildings }}">
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Total number of units:
                                        </label>
                                        <input type="text" name="number_of_units" class="form-control mb-0"
                                            value="{{ @$auction->number_of_units }}">
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Unit sizes for each unit:
                                        </label>
                                        <input type="text" name="unit_sizes" class="form-control mb-0"
                                            value="{{ @$auction->unit_sizes }}">
                                        <span class="small opacity-50">(example 2- 1/1., 2- 2/2)</span>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Sqft heated for each unit:
                                        </label>
                                        <input type="sqft" name="sqft_for_each_unit" class="form-control mb-0"
                                            value="{{ @$auction->sqft_for_each_unit }}">
                                        {{-- <span class="small opacity-50">(example 2- 1/1., 2- 2/2)</span> --}}
                                    </div>


                                    <div class="form-group col-md-12 mt-4">
                                        <label>
                                            Are units occupied or vacant? (List how many are occupied and how many are
                                            vacant):
                                        </label>
                                        <textarea type="text" name="occupied_vacant_info" class="form-control mb-0">{{ @$auction->occupied_vacant_info }}</textarea>
                                        {{-- <span class="small opacity-50">(example 2- 1/1., 2- 2/2)</span> --}}
                                    </div>

                                    <div class="form-group col-md-12 mt-4">
                                        <label>
                                            Current rental amount:(List the rental amounts received for each unit)
                                        </label>
                                        <textarea type="text" name="current_rental_amount" class="form-control mb-0">{{ @$auction->current_rental_amount }}</textarea>
                                        {{-- <span class="small opacity-50">(example 2- 1/1., 2- 2/2)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Expected rental amount:
                                        </label>
                                        <input type="text" name="expected_rental_amount" class="form-control mb-0"
                                            value="{{ @$auction->expected_rental_amount }}">
                                        <span class="small opacity-50">(If not currently rented)</span>
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Annual gross income:
                                        </label>
                                        <input type="text" name="anual_gross_income" class="form-control mb-0"
                                            value="{{ @$auction->anual_gross_income }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Annual expenses:
                                        </label>
                                        <input type="text" name="anual_expense" class="form-control mb-0"
                                            value="{{ @$auction->anual_expense }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Annual net income:
                                        </label>
                                        <input type="text" name="anual_net_income" class="form-control mb-0"
                                            value="{{ @$auction->anual_net_income }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Total monthly rent:
                                        </label>
                                        <input type="text" name="total_monthly_rent" class="form-control mb-0"
                                            value="{{ @$auction->total_monthly_rent }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Lease terms:
                                        </label>
                                        <input type="text" name="lease_terms" class="form-control mb-0"
                                            value="{{ @$auction->lease_terms }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Utilities Tenant pays:
                                        </label>
                                        <input type="text" name="tenant_pays" class="form-control mb-0"
                                            value="{{ @$auction->tenant_pays }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>

                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Utilities Landlord pays:
                                        </label>
                                        <input type="text" name="landlord_pays" class="form-control mb-0"
                                            value="{{ @$auction->landlord_pays }}">
                                        {{-- <span class="small opacity-50">(If not currently rented)</span> --}}
                                    </div>


                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <label class="me-2 mt-5 fs-4">
                                            Auction Terms:
                                        </label>
                                        <div class="header-divider"></div>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Escrow Amount
                                        </label>
                                        <input type="text" class="form-control mb-0" name="escrow_amount"
                                            value="{{ @$auction->terms->escrow_amount }}">
                                        <span class="opacity-50 small">In $ (Minimum Acceptable Escrow Amount)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Inspection Period
                                        </label>
                                        <input type="text" class="form-control mb-0" name="inspection_perion"
                                            value="{{ @$auction->terms->inspection_perion }}">
                                        <span class="opacity-50 small">(Maximum Acceptable Inspection Period)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Buyer’s Agent Commission
                                        </label>
                                        <input type="text" class="form-control mb-0" name="buyer_agent_commission"
                                            value="{{ @$auction->terms->buyer_agent_commission }}">
                                        <span class="opacity-50 small">(Custom Input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Buyer’s Premium
                                        </label>
                                        <input type="text" class="form-control mb-0" name="buyer_premium"
                                            value="{{ @$auction->terms->buyer_premium }}">
                                        <span class="opacity-50 small">(Custom Input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Seller’s Premium
                                        </label>
                                        <input type="text" class="form-control mb-0" name="seller_premium"
                                            value="{{ @$auction->terms->seller_premium }}">
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Success fee to be paid by
                                        </label>
                                        <select class="form-select" name="success_fee_to_be_paid_by">
                                            <option value="Seller"
                                                {{ @$auction->terms->additional_remarks == 'Seller' ? 'selected' : '' }}>
                                                Seller
                                            </option>
                                            <option value="Buyer"
                                                {{ @$auction->terms->additional_remarks == 'Buyer' ? 'selected' : '' }}>
                                                Buyer
                                            </option>
                                            <option value="Seller's agent"
                                                {{ @$auction->terms->additional_remarks == "Seller's agent" ? 'selected' : '' }}>
                                                Seller’s agent</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Any Additional Remarks
                                        </label>
                                        <input type="text" class="form-control mb-0" name="additional_remarks"
                                            value="{{ @$auction->terms->additional_remarks }}">
                                        <span class="opacity-50 small">(Custom Input)</span>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Property list date
                                        </label>
                                        <input type="text" class="form-control mb-0" name="property_list_date"
                                            value="{{ @$auction->terms->property_list_date }}">
                                        <span class="opacity-50 small">(Custom Input)</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <label class="me-2 mt-5 fs-4">
                                            Seller/Seller’s Agent Info
                                        </label>
                                        <div class="header-divider"></div>
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Name: <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control mb-0" required name="seller_name"
                                            value="{{ @$auction->seller_name }}">
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Brokerage: <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control mb-0" required name="brokerage"
                                            value="{{ @$auction->brokerage }}">
                                        {{-- <span class="opacity-50 small">(If you are agent)</span> --}}
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            License number: <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control mb-0" required name="license_number"
                                            value="{{ @$auction->license_number }}">
                                        {{-- <span class="opacity-50 small">(If you are agent)</span> --}}
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Phone number: <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control mb-0" required name="phone_number"
                                            value="{{ @$auction->phone_number }}">
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Email: <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control mb-0" required name="email"
                                            value="{{ @$auction->email }}">
                                    </div>
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            MLS ID # <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control mb-0" required
                                            name="seller_agent_mls_id" value="{{ @$auction->seller_agent_mls_id }}">
                                        {{-- <span class="opacity-50 small">(If listed on the MLS)</span> --}}
                                    </div>
                                </div>


                                <div class="form-group col-md-6 mt-4">
                                    <label>
                                        Is Seller looking to purchase another property?
                                    </label>
                                    <select class="form-select mb-0" name="looking_another_property">
                                        <option value="Yes"
                                            {{ @$auction->looking_another_property == 'Yes' ? 'selected' : '' }}>Yes
                                        </option>
                                        <option value="No"
                                            {{ @$auction->looking_another_property == 'No' ? 'selected' : '' }}>No
                                        </option>
                                    </select>
                                    <span class="opacity-50 small">(Yes or No)</span>
                                </div>



                                <div class="rightFooter">
                                    <a href="{{ route('add-listing') . '?step=2&id=' . $id }}"
                                        class="btn btn-lg text-600 btnPrevious position-absolute" type="button"><i
                                            class="fa fa-arrow-left me-2"></i>Previous
                                    </a>
                                    <button class="btn btn-lg text-600 btnNext position-absolute " type="submit">Next <i
                                            class="fa fa-arrow-right ms-2"></i></button>
                                </div>
                            </form>
                        </div>
                        <!-- Tab4  -->
                        <div class="tab-pane container {{ $step == 4 ? 'active' : '' }}" id="menu3">
                            <form action="{{ route('save-pl-step4') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="update" value="{{ $update }}">
                                <div class="rightBody container row">
                                    <div class="title">
                                        <span class="title-number">04</span>
                                        Media
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="me-2 fs-4">
                                            My Photos
                                        </label>
                                        <div class="header-divider"></div>
                                        <span class="position-absolute bg-white px-3 fs-5" style="right:22px;">
                                            <span id="tatal_images">0</span><span class="opacity-5 text-500">/</span>
                                            <span class="_total">10</span>
                                        </span>
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2"
                                            style="position: relative;">
                                            <div class="text-center my-4">
                                                <label for="photo" class="d-grid ">
                                                    <i class="fa fa-file-image-o fs-3"></i><span
                                                        class="small opacity-50 mt-2">add new</span>
                                                </label>
                                            </div>
                                            <input type="file" name="photos[]" multiple id="photo_files"
                                                onchange="checkImages();"
                                                style="position: absolute; top:0; left:0; width:100%;height:100%; opacity: 0; cursor: pointer;">
                                        </div>
                                    </div>
                                    <script>
                                        function checkImages() {
                                            var totalImages = $('#photo_files')[0].files.length;
                                            $('#tatal_images').text(totalImages);
                                            // alert(totalImages);
                                        }
                                    </script>
                                    <div class="d-flex justify-content-between align-items-center mt-5">
                                        <label class="me-2 fs-4">
                                            My Videos
                                        </label>
                                        <div class="header-divider"></div>
                                        <span class="position-absolute bg-white px-3 fs-5" style="right:22px;">
                                            <span id="tatal_videos">0</span><span class="opacity-5 text-500">/</span>
                                            <span class="_total">10</span>
                                        </span>
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2"
                                            style="position: relative;">
                                            <div class="text-center my-4">
                                                <label for="photo" class="d-grid ">
                                                    <i class="fa fa-file-video-o fs-3"></i><span
                                                        class="small opacity-50 mt-2">add new</span>
                                                </label>
                                            </div>
                                            <input type="file" name="videos[]" multiple id="video_files"
                                                onchange="checkVideos();"
                                                style="position: absolute; top:0; left:0; width:100%;height:100%; opacity: 0; cursor: pointer;">
                                        </div>
                                    </div>
                                    <script>
                                        function checkVideos() {
                                            var total = $('#video_files')[0].files.length;
                                            $('#tatal_videos').text(total);
                                        }
                                    </script>
                                    <div class="d-flex justify-content-between align-items-center mt-5">
                                        <label class="me-2 fs-4">
                                            My Audio Files
                                        </label>
                                        <div class="header-divider"></div>
                                        <span class="position-absolute bg-white px-3 fs-5" style="right:22px;">
                                            <span id="tatal_audios">0</span><span class="opacity-5 text-500">/</span>
                                            <span class="_total">10</span>
                                        </span>
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2"
                                            style="position: relative;">
                                            <div class="text-center my-4">
                                                <label for="photo" class="d-grid ">
                                                    <i class="fa fa-file-video-o fs-3"></i><span
                                                        class="small opacity-50 mt-2">add new</span>
                                                </label>
                                            </div>
                                            <input type="file" name="audios[]" multiple id="audio_files"
                                                onchange="checkAudios();"
                                                style="position: absolute; top:0; left:0; width:100%;height:100%; opacity: 0; cursor: pointer;">
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    function checkAudios() {
                                        var total = $('#audio_files')[0].files.length;
                                        $('#tatal_audios').text(total);
                                    }
                                </script>
                                <div class="rightFooter">
                                    <a href="{{ route('add-listing') . '?step=3&id=' . $id }}"
                                        class="btn btn-lg text-600 btnPrevious position-absolute" type="button"><i
                                            class="fa fa-arrow-left me-2"></i>Previous
                                    </a>
                                    <button class="btn btn-lg text-600 btnNext position-absolute " type="submit">Next <i
                                            class="fa fa-arrow-right ms-2"></i></button>
                                </div>
                            </form>
                        </div>
                        <!-- Tab5  -->
                        <div class="tab-pane container fade" id="menu4">
                            <div class="rightBody container row">
                                <div class="title">
                                    <span class="title-number">04</span>
                                    Media
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="me-2 fs-4">
                                        My Photos
                                    </label>
                                    <div class="header-divider"></div>
                                    <span class="position-absolute bg-white px-3 fs-5" style="right:22px;">
                                        <span>0</span><span class="opacity-5 text-500">/</span> <span
                                            class="_total">10</span>
                                    </span>
                                </div>
                                <div class="form-group mt-4">
                                    <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2">
                                        <div class="d-grid text-center my-4" data-bs-toggle="modal"
                                            data-bs-target="#myLocation"><i class="fa fa-image fs-3"></i><span
                                                class="small opacity-50 mt-2">add new</span></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-5">
                                    <label class="me-2 fs-4">
                                        My Videos
                                    </label>
                                    <div class="header-divider"></div>
                                    <span class="position-absolute bg-white px-3 fs-5" style="right:22px;">
                                        <span>0</span><span class="opacity-5 text-500">/</span> <span
                                            class="_total">10</span>
                                    </span>
                                </div>
                                <div class="form-group mt-4">
                                    <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2">
                                        <div class="d-grid text-center my-4" data-bs-toggle="modal"
                                            data-bs-target="#myLocation"><i class="fa fa-file-video-o fs-3"></i><span
                                                class="small opacity-50 mt-2">add new</span></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-5">
                                    <label class="me-2 fs-4">
                                        My Audio Files
                                    </label>
                                    <div class="header-divider"></div>
                                    <span class="position-absolute bg-white px-3 fs-5" style="right:22px;">
                                        <span>0</span><span class="opacity-5 text-500">/</span> <span
                                            class="_total">10</span>
                                    </span>
                                </div>
                                <div class="form-group mt-4">
                                    <div class="locationCard col-sm-12 col-md-3 col-lg-3 rounded-2">
                                        <div class="d-grid text-center my-4" data-bs-toggle="modal"
                                            data-bs-target="#myLocation"><i class="fa fa-file-audio-o fs-3"></i><span
                                                class="small opacity-50 mt-2">add new</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="rightFooter">
                                <button class="btn btn-lg text-600 btnPrevious position-absolute" type="button"><i
                                        class="fa fa-arrow-left me-2"></i>Back
                                </button>
                                <button class="btn btn-lg text-600 btnNext position-absolute" type="button">Next <i
                                        class="fa fa-arrow-right ms-2"></i></button>
                            </div>
                        </div>
                        <!-- Tab6  -->
                        <div class="tab-pane container {{ $step == 5 ? 'active' : '' }}" id="end">
                            <form action="{{ route('save-pl-step5') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="id" value="{{ $id }}">
                                <input type="hidden" name="update" value="{{ $update }}">
                                <div class="rightBody container row">
                                    <div class="title">
                                        <span class="title-number">05</span>
                                        Finish
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="me-2 fs-4">
                                            Summary
                                        </label>
                                        <div class="header-divider"></div>
                                    </div>
                                    <div class="col-md-8 mt-5">
                                        <div class="p-4 bg-light rounded">
                                            <div class="row">
                                                <div class="col-6">
                                                    <span class="fs-6">Created</span>
                                                </div>
                                                <div class="col-6">
                                                    <span>Just Now&nbsp;</span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="fs-6">Expiry Date</span>
                                                </div>
                                                <div class="col-6">
                                                    @inject('carbon', 'Carbon\Carbon')
                                                    <span>{{ round(@$auction->auction_length) > 0? $carbon::parse(@$auction->created_at)->addDays(round(@$auction->auction_length))->format('M d, Y'): 'Never' }}&nbsp;</span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="fs-6">Views</span>
                                                </div>
                                                <div class="col-6">
                                                    <span>0&nbsp;</span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="fs-6">Status</span>
                                                </div>
                                                <div class="col-6">
                                                    <span>Pending Approval&nbsp;</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rightFooter">
                                    {{-- <button class="btn btn-lg text-600 btnPrevious position-absolute" type="button"><i
                                        class="fa fa-arrow-left me-2"></i>Back
                                </button> --}}
                                    <button style="background:#049399!important;color:#fff;"
                                        class="btn btn-lg text-600 btnNext position-absolute"
                                        type="submit">Finish</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <ul class="nav mt-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#home">
                    <div class="tabsNumber"><span>01</span> Basics</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#menu1">
                    <div class="tabsNumber"><span>02</span> Description</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#menu2">
                    <div class="tabsNumber"><span>03</span> Details</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#menu3">
                    <div class="tabsNumber"><span>04</span> Media</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#end">
                    <div class="tabsNumber"><span>05</span> Finish</div>
                </a>
            </li>
        </ul>
    </div>

    <!-- Modals Of Basics  -->
    <div class="modal fade" id="myLocation" tabindex="-1" aria-labelledby="myLocationLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content container">
                <div class="modal-body row">
                    <h2 class="fs-md mb-4">My Location</h2>
                    <div class="form-group mt-4 col-md-12 p-0">
                        <label class="fw-bold mb-2">
                            Add Title / Address.
                        </label>
                        <input type="input" name="" id="form_title" placeholder="" class="form-control" />
                    </div>
                    <div class="col-md-6 py-2 p-0">
                        <label>County</label>
                        <select class="form-select mb-3">
                            <option value="197">Glades County </option>
                            <option value="213">Sumter County </option>
                            <option value="229">Calhoun County </option>
                            <option value="198">Citrus County </option>
                            <option value="214">Jefferson County </option>
                            <option value="230">Suwannee County </option>
                            <option value="183">Broward County </option>
                            <option value="199">Lake County </option>
                            <option value="215">Polk County </option>
                            <option value="231">Martin County </option>
                            <option value="184">Gilchrist County </option>
                            <option value="200">Hillsborough County </option>
                            <option value="216">Clay County </option>
                            <option value="232">Union County </option>
                            <option value="185">Pinellas County </option>
                            <option value="201">Walton County </option>
                            <option value="217">Taylor County </option>
                            <option value="233">Levy County </option>
                            <option value="186">Miami-Dade County </option>
                            <option value="202">Flagler County </option>
                            <option value="218">Orange County </option>
                            <option value="240">Florida </option>
                            <option value="187">Palm Beach County </option>
                            <option value="203">Collier County </option>
                            <option value="219">Franklin County </option>
                            <option value="188">Duval County </option>
                            <option value="204">Gadsden County </option>
                            <option value="220">Osceola County </option>
                            <option value="189">Nassau County </option>
                            <option value="205">Liberty County </option>
                            <option value="221">Gulf County </option>
                            <option value="190">Washington County </option>
                            <option value="206">Alachua County </option>
                            <option value="222">Madison County </option>
                            <option value="191">Brevard County </option>
                            <option value="207">Okaloosa County </option>
                            <option value="223">St. Lucie County </option>
                            <option value="192">Escambia County </option>
                            <option value="208">Okeechobee County </option>
                            <option value="224">Bradford County </option>
                            <option value="193">Pasco County </option>
                            <option value="209">Volusia County </option>
                            <option value="225">Marion County </option>
                            <option value="194">Hendry County </option>
                            <option value="210">Wakulla County </option>
                            <option value="226">Santa Rosa County </option>
                            <option value="195">St. Johns County </option>
                            <option value="211">Columbia County </option>
                            <option value="227">Indian River County </option>
                            <option value="196">Hernando County </option>
                            <option value="212">Lafayette County </option>
                            <option value="228">Hamilton County </option>

                        </select>
                    </div>
                    <div class="col-md-6 py-2">
                        <label>City</label>
                        <input type="text" data-required="1" class="form-control " value="">
                    </div>
                    <div class="col-md-12 py-2" id="map" style="height:400px;background:grey"></div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button> -->
                    <button type="button" class="btn float-end">Continue</button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- //////////// End ////////////////// -->
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
    <script>
        $(".btnNext").click(function() {
            /* const nextTabLinkEl = $(".nav .active").closest("li").next("li").find("a")[0];
            const nextTab = new bootstrap.Tab(nextTabLinkEl);
            nextTab.show(); */

        });

        $(".btnPrevious").click(function() {
            const prevTabLinkEl = $(".nav .active").closest("li").prev("li").find("a")[0];
            const prevTab = new bootstrap.Tab(prevTabLinkEl);
            prevTab.show();
            // prevTabLinkEl.hide()
        });
        //  ////////////////MultiSelect Tabs  //////////////////////////
        $(document).ready(function() {
            var multipleCancelButton = new Choices('.multiselect', {
                removeItemButton: true,
                // maxItemCount:5,
                // searchResultLimit: 5,
                // renderChoiceLimit: 5
            });



        });
    </script>
    <!-- ??????????????????????? Google Map ????????????????????????????????????? -->
    <script>
        function myMap() {
            var mapOptions = {
                center: new google.maps.LatLng(51.5, -0.12),
                zoom: 10,
                mapTypeId: google.maps.MapTypeId.HYBRID
            }
            var map = new google.maps.Map(document.getElementById("map"), mapOptions);
        }
    </script>

    <script>
        // google.maps.event.addDomListener(window, 'load', initialize);
        function initialize() {
            var input = document.getElementById('autocomplete_search');
            var autocomplete = new google.maps.places.Autocomplete(input);

            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                // place variable will have all the information you are looking for.
                $('#lat').val(place.geometry['location'].lat());
                $('#long').val(place.geometry['location'].lng());
            });
        }
    </script>

    {{-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBu-916DdpKAjTmJNIgngS6HL_kDIKU0aU&libraries=places&callback=myMap"> --}}
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{env('GOOGLE_PLACES_API_KEY')}}&libraries=places&callback=initialize">
    </script>
@endpush
