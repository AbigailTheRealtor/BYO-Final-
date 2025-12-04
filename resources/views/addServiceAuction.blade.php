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
                    <form class="p-4 pt-0" action="{{ route('agent.service.auction.save') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="container">
                            <div class="title">
                                <h4>Add Agent Service auction</h4>
                            </div>

                            <div class="row">
                                <div class="form-group mt-4 col-md-4">
                                    <label>
                                        Auction Type
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select mb-0" name="auction_type" id="auction_type"
                                        onchange="changeAuctionType(this.value);">
                                        <option value="Normal" {{ old('auction_type') == 'Normal' ? 'selected' : '' }}>Normal
                                            (Timer)</option>
                                        <option value="Traditional"
                                            {{ old('auction_type') == 'Traditional' ? 'selected' : '' }}>Traditional (No Timer)
                                        </option>
                                    </select>
                                    @if ($errors->has('auction_type'))
                                        <div class="small error">{{ $errors->first('auction_type') }}</div>
                                    @endif
                                    <span class="opacity-50 small">Here you can choose the format of your auction.</span>
                                </div>



                                <div class="form-group col-md-4 mt-4">
                                    <label>
                                        Auction Length
                                        <span class="text-danger auction-length-required">*</span>
                                    </label>
                                    <select class="form-select mb-0 auction_length" name="auction_length" required>
                                        <option value="30M" class="normal-length" {{ old('auction_length') == '30M' ? 'selected' : '' }}>30 Minutes</option>
                                        <option value="60M" class="normal-length" {{ old('auction_length') == '60M' ? 'selected' : '' }}>1 Hour</option>
                                        <option value="120M" class="normal-length" {{ old('auction_length') == '120M' ? 'selected' : '' }}>2 Hour</option>
                                        <option value="180M" class="normal-length" {{ old('auction_length') == '180M' ? 'selected' : '' }}>3 Hour</option>
                                        <option value="240M" class="normal-length" {{ old('auction_length') == '240M' ? 'selected' : '' }}>4 Hour</option>
                                        <option value="300M" class="normal-length" {{ old('auction_length') == '300M' ? 'selected' : '' }}>5 Hour</option>
                                        <option value="360M" class="normal-length" {{ old('auction_length') == '360M' ? 'selected' : '' }}>6 Hour</option>
                                        <option value="420M" class="normal-length" {{ old('auction_length') == '420M' ? 'selected' : '' }}>7 Hour</option>
                                        <option value="480M" class="normal-length" {{ old('auction_length') == '480M' ? 'selected' : '' }}>8 Hour</option>
                                        <option value="540M" class="normal-length" {{ old('auction_length') == '540M' ? 'selected' : '' }}>9 Hour</option>
                                        <option value="600M" class="normal-length" {{ old('auction_length') == '600M' ? 'selected' : '' }}>10 Hour</option>
                                        <option value="660M" class="normal-length" {{ old('auction_length') == '660M' ? 'selected' : '' }}>11 Hour</option>
                                        <option value="720M" class="normal-length" {{ old('auction_length') == '720M' ? 'selected' : '' }}>12 Hour</option>
                                        <option value="1" class="normal-length"
                                            {{ old('auction_length') == '1' ? 'selected' : '' }}>1 Day</option>
                                        <option value="3" class="normal-length"
                                            {{ old('auction_length') == '3' ? 'selected' : '' }}>3 Days</option>
                                        <option value="5" class="normal-length"
                                            {{ old('auction_length') == '5' ? 'selected' : '' }}>5 Days</option>
                                        <option value="7" class="normal-length"
                                            {{ old('auction_length') == '7' ? 'selected' : '' }}>7 Days</option>
                                        <option value="10" class="normal-length"
                                            {{ old('auction_length') == '10' ? 'selected' : '' }}>10 Days</option>
                                        <option value="14" class="normal-length"
                                            {{ old('auction_length') == '14' ? 'selected' : '' }}>14 Days</option>
                                        <option value="21" class="normal-length"
                                            {{ old('auction_length') == '21' ? 'selected' : '' }}>21 Days</option>
                                        <option value="30" class="normal-length"
                                            {{ old('auction_length') == '30' ? 'selected' : '' }}>30 Days</option>
                                        <option value="45" class="normal-length"
                                            {{ old('auction_length') == '45' ? 'selected' : '' }}>45 Days</option>
                                        <option value="60" class="normal-length"
                                            {{ old('auction_length') == '60' ? 'selected' : '' }}>60 Days</option>
                                        <option value="75" class="normal-length"
                                            {{ old('auction_length') == '75' ? 'selected' : '' }}>75 Days</option>
                                        <option value="90" class="normal-length"
                                            {{ old('auction_length') == '90' ? 'selected' : '' }}>90 Days</option>
                                        <option value="-1" class="traditional-length d-none"
                                            {{ old('auction_length') == '-1' ? 'selected' : '' }}>No time limit
                                        </option>
                                    </select>
                                    @if ($errors->has('auction_length'))
                                        <div class="small error">{{ $errors->first('auction_length') }}</div>
                                    @endif
                                    <span class="opacity-50 small">Select the number of days you would like the auction to
                                        run for.</span>
                                </div>



                                <div class="form-group col-md-4 mt-4">
                                    <label>
                                        Service:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="service_id" class="form-select">
                                        @foreach ($services as $service)
                                            <option value="{{$service->id}}" {{ old('service_id')==$service->id?"selected":"" }} >{{$service->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('service_id'))
                                        <div class="small error">{{ $errors->first('service_id') }}</div>
                                    @endif
                                </div>


                                {{-- <div class="form-group col-md-4 mt-4">
                                    <label>
                                        Add Title / Address.
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="address" id="form_title" placeholder="eg. Used Sony TV"
                                        class="form-control" required value="{{ old('address') }}" />
                                    @if ($errors->has('address'))
                                        <div class="small error">{{ $errors->first('address') }}</div>
                                    @endif
                                </div> --}}

                            </div>

                            <div class="row">
                                <div class="form-group">
                                    <label>Description of the service the agent is requesting: <span class="text-danger">*</span></label>
                                    <textarea name="description" class="form-control" cols="30" rows="3" required>{{old('description')}}</textarea>
                                    @if ($errors->has('description'))
                                        <div class="small error">{{ $errors->first('description') }}</div>
                                    @endif
                                </div>
                            </div>



                            <div class="row">
                                <div class="form-group col-md-4 mt-4">
                                    <label>
                                        City
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="city_id" class="form-select" placeholder="Select City"
                                        required>
                                        @foreach ($cities as $city)
                                            <option value="{{ $city->id }}"
                                                {{ old('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('city_id'))
                                        <div class="small error">{{ $errors->first('city_id') }}</div>
                                    @endif
                                </div>

                                <div class="form-group col-md-4 mt-4">
                                    <label>
                                        County
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="county_id" class="form-select" placeholder="Select County"
                                        required>
                                        @foreach ($counties as $county)
                                            <option value="{{ $county->id }}"
                                                {{ old('county_id') == $county->id ? 'selected' : '' }}>{{ $county->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('county_id'))
                                        <div class="small error">{{ $errors->first('county_id') }}</div>
                                    @endif
                                </div>

                                <div class="form-group mt-4 col-md-4 d-none">
                                    <label>
                                        State
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select name="state_id" class="form-select" required>
                                        {{-- <option value="">Select City</option> --}}
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}"
                                                {{ old('state_id') == $state->id ? 'selected' : '' }}>
                                                {{ ucwords($state->name) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('state_id'))
                                        <div class="small error">{{ $errors->first('state_id') }}</div>
                                    @endif
                                </div>

                                <div class="form-group mt-4 col-md-4">
                                    <label>
                                        When does the service need to take place?
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group mb-3">
                                        <input type="text" name="required_at" id="required_at" required readonly class="form-control datetimepicker" value="{{old('required_at')}}">
                                        <label class="input-group-text" for="required_at">
                                            <i class="fa fa-calendar"></i>
                                        </label>
                                      </div>

                                    @if ($errors->has('required_at'))
                                        <div class="small error">{{ $errors->first('required_at') }}</div>
                                    @endif
                                </div>


                            </div>


                            <div class="row">
                                <div class="form-group">
                                    <label>Public Note:</label>
                                    <textarea name="public_notes" class="form-control" cols="30" rows="3">{{old('public_notes')}}</textarea>
                                </div>
                            </div>




                            <div class="row">
                                <div class="form-group col-md-4 mt-4">
                                    <label>
                                        Minimum Price Agent is willing to accept $ or %:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-text p-0">
                                        {{-- <i class="fa fa-dollar" id="basic-addon1"></i> --}}
                                        <select name="min_price_in" class="form-select" style="background-color: #e9ecef; border: 0;">
                                            <option value="$">$</option>
                                            <option value="%">%</option>
                                        </select>
                                        </div>
                                        <input type="number" class="form-control border-start-0 mb-0" name="min_price"
                                            min="0" required placeholder="0.00" value="{{ old('min_price') }}">
                                    </div>
                                    @if ($errors->has('min_price'))
                                        <div class="small error">{{ $errors->first('min_price') }}</div>
                                    @endif
                                </div>


                                {{-- <div class="form-group col-md-4 mt-4">
                                    <label>
                                        Minimum Price Agent is willing to accept %:
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-text">
                                        <i class="fa fa-percent" id="basic-addon1"></i>
                                        </div>

                                        <input type="number" class="form-control border-start-0 mb-0" name="min_price_percent"
                                            min="0" required placeholder="0.00" value="{{ old('min_price_percent') }}">
                                    </div>
                                    @if ($errors->has('min_price_percent'))
                                        <div class="small error">{{ $errors->first('min_price_percent') }}</div>
                                    @endif
                                </div> --}}
                            </div>

                            <div class="card mt-4">
                                <div  class="card-header text-white bg-secondary">Listing Agent Info</div>
                                <div class="card-body p-2">
                                    <div class="row">
                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Name:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="agent_name" required class="form-control" value="{{old('agent_name')}}">
                                        </div>


                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Brokerage:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="agent_brokerage" placeholder="" class="form-control"
                                                value="{{ old('agent_brokerage') }}" required />
                                        </div>

                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                License Number:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="agent_license_no" placeholder="" class="form-control"
                                                value="{{ old('agent_license_no') }}" required />
                                        </div>
                                    </div>


                                    <div class="row">
                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Phone:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="agent_phone" required class="form-control" value="{{old('agent_phone')}}">
                                        </div>


                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Email:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="agent_email" placeholder="" class="form-control"
                                                value="{{ old('agent_email') }}" required />
                                        </div>

                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                MLS ID:
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="agent_mls_id" placeholder="" class="form-control"
                                                value="{{ old('agent_mls_id') }}" required />
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="card mt-4">
                                <div  class="card-header text-white bg-secondary">The following information is visible after a winning bid has been selected:</div>
                                <div class="card-body p-2">
                                    <div class="row">
                                        <div class="form-group mt-0 col-md-12">
                                            <label>
                                                Private Notes:
                                            </label>
                                            <textarea name="private_notes" class="form-control" cols="30" rows="3">{{old('private_notes')}}</textarea>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group mt-4 col-md-12">
                                            <label>
                                                Information on the person the client is meeting:
                                            </label>
                                            <textarea name="meeting_info" class="form-control" cols="30" rows="6" placeholder="
                                            ">{{old('meeting_info')?old('meeting_info'):"If agent needs to meet someone please provide the information on the person the agent needs to meet:
Full Name:
Phone:
Email:
Service this person provides:
Address where the agent needs to meet:"}}</textarea>
                                            {{-- <small>(Buyer, Seller, inspector etc.) Include name, phone number and email address</small> --}}
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                Address of the property:
                                            </label>
                                            <input type="text" name="address" class="form-control" value="{{old('address')}}">
                                        </div>


                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                MLS ID:
                                                {{-- <span class="text-danger">*</span> --}}
                                            </label>
                                            <input type="text" name="mls_id" placeholder="" class="form-control"
                                                value="{{ old('mls_id') }}" />
                                        </div>

                                        <div class="form-group mt-4 col-md-4">
                                            <label>
                                                MLS Listing Link:
                                                {{-- <span class="text-danger">*</span> --}}
                                            </label>
                                            <input type="text" name="mls_listing_link" placeholder="" class="form-control"
                                                value="{{ old('mls_listing_link') }}" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group mt-4 col-md-4">
                                    <label>
                                        Video:
                                    </label>
                                    <input type="file" name="video" class="form-control" >
                                </div>


                                <div class="form-group mt-4 col-md-4">
                                    <label>
                                        Audio:
                                        {{-- <span class="text-danger">*</span> --}}
                                    </label>
                                    <input type="file" name="audio" class="form-control" >
                                </div>

                                <div class="form-group mt-4 col-md-4">
                                    <label>
                                        Note:
                                    </label>
                                    <input type="file" name="note" class="form-control" >
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
            $('.datetimepicker').datetimepicker({
                format: 'Y-m-d g:i A',
                formatTime: 'g:i A',
            });
        });
    </script>
@endpush
