@extends('layouts.main')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
@endpush
@section('content')
    <div class="mainDashboard">
        <form class="settings-for" method="POST" action="{{route('settings')}}" enctype="multipart/form-data">
            @csrf
            <div class="container">

                @include('layouts.partials.dashboard_user_section')

                <div class="dashboardContentDetails mt-3 mb-5">
                    <div class="card">
                        <div class="row">

                            @include('layouts.partials.sidenav')

                            <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                                <div class="container mt-5 mySettings">
                                    <h1>Profile Settings</h1>
                                    <p>Please keep your account details updated.</p>
                                    <hr>
                                    <!-- Accordion Starrt Here -->
                                    <div class="accordion accordion-flush" id="accordionFlushExample">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapseOne"
                                                    aria-expanded="false" aria-controls="flush-collapseOne">
                                                    <b>Username</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapseOne" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">The username or email can be used
                                                            to login.</div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                <div class="col-md-6 py-2">
                                                                    <div class="d-flex justify-content-between">
                                                                        <label>Username</label>
                                                                        <svg data-bs-container="body" tabindex="0"
                                                                            data-bs-toggle="popover"
                                                                            data-bs-trigger="hover focus"
                                                                            data-bs-placement="top"
                                                                            data-bs-content="The username cannot be changed once created."
                                                                            xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-6 w-6" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                                            </path>
                                                                        </svg>
                                                                    </div>

                                                                    <input class="form-control" name="username" data-type="username"
                                                                        type="text" disabled="" value="{{$user->user_name}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <div class="d-flex justify-content-between">
                                                                        <label>Display Name</label>
                                                                        <svg data-bs-container="body" tabindex="0"
                                                                            data-bs-toggle="popover"
                                                                            data-bs-trigger="hover focus"
                                                                            data-bs-placement="top"
                                                                            data-bs-content="Where possible we will display this name instead of your username."
                                                                            xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-6 w-6" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                                            </path>
                                                                        </svg>
                                                                    </div>
                                                                    <input type="text" data-required="1"
                                                                        class="form-control setting-form-field" name="name" id="name" value="{{$user->name}}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item d-none">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse2"
                                                    aria-expanded="false" aria-controls="flush-collapse2">
                                                    <b>Password</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse2" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">Only complete these fields if you
                                                            wish to change the
                                                            existing password.</div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                <div class="col-md-6 py-2">
                                                                    <label>Password</label>
                                                                    <input class="form-control setting-form-field" name="password"
                                                                        type="password" value="">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Password Re-type</label>
                                                                    <input type="password" data-required="1" name="confirm_password"
                                                                        class="form-control  setting-form-field" value="">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse3"
                                                    aria-expanded="false" aria-controls="flush-collapse3">
                                                    <b>Email & Phone</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse3" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">A valid email is required.</div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                <div class="col-md-6 py-2">
                                                                    <label>Email</label>
                                                                    <input class="form-control" data-type="email" name="email"
                                                                        type="email" readonly value="{{$user->email}}">
                                                                </div>
                                                                <div class="col-md-6 py-2 d-grid">
                                                                    <label>Mobile Number</label>
                                                                    <input type="phone" name="phone" type="tel"
                                                                        class="form-control" value="{{$user->phone}}"
                                                                        id="phone">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse4"
                                                    aria-expanded="false" aria-controls="flush-collapse4">
                                                    <b>About Me</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse4" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">This information will be
                                                            displayed on your public profile.
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12 textarea">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                {{-- @if(auth()->user()->user_type == 'agent') --}}
                                                                <div class="col-md-6 py-2">
                                                                    <label>Brokerage</label>
                                                                    <input type="text" name="brokerage" class="form-control " value="{{$user->brokerage}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Real Estate License #</label>
                                                                    <input type="text" name="license_no" class="form-control" value="{{$user->license_no}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Intro Video</label>
                                                                    <input type="url" name="intro_video" class="form-control" value="{{$user->intro_video}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Promotional marketing material</label>
                                                                    <input type="url" name="promotional_material" class="form-control" value="{{$user->promotional_material}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Business Card</label>
                                                                    <input type="url" name="business_card" class="form-control" value="{{$user->business_card}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>My Website</label>
                                                                    <input type="url" name="website" class="form-control" value="{{$user->website}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>My Review Link</label>
                                                                    <input type="url" name="review" class="form-control" value="{{$user->review}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Facebook</label>
                                                                    <input type="text" name="facebook" class="form-control" value="{{$user->facebook}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>YouTube</label>
                                                                    <input type="text" name="youtube" class="form-control" value="{{$user->youtube}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Twitter</label>
                                                                    <input type="text" name="twitter" class="form-control" value="{{$user->twitter}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Instagram</label>
                                                                    <input type="text" name="instagram" class="form-control" value="{{$user->instagram}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>LinkedIn</label>
                                                                    <input type="text" name="linkedin" class="form-control" value="{{$user->linkedin}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Contract Listing terms</label>
                                                                    <input type="text" name="listing_term" class="form-control" value="{{$user->listing_term}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Why you should be hired as an agent</label>
                                                                    <input type="text" name="hired_agent" class="form-control" value="{{$user->hired_agent}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>What sets you apart from other agents?</label>
                                                                    <input type="text" name="apart_agent" class="form-control" value="{{$user->apart_agent}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Credit Offering</label>
                                                                    <input type="text" name="credit_offered" class="form-control" value="{{$user->credit_offered}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Services Offering</label>
                                                                    <select name="services[]" class="form-select muiSelect" multiple>
                                                                        @foreach ($services as $service)
                                                                        <option value="{{$service->name}}" @if($user->services) {{in_array($service->name, json_decode($user->services))?"selected":""}}  @endif >{{$service->name}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                {{-- @endif --}}
                                                                <div class="col-md-12 py-2">
                                                                    <label>Marketing Plan</label>
                                                                    <textarea style="min-height:120px;" class="form-control " name="marketing_plan">{{$user->marketing_plan}}</textarea>
                                                                </div>
                                                                <div class="col-md-12 py-2">
                                                                    <label>Additional Details</label>
                                                                    <textarea style="min-height:120px;" class="form-control " name="description">{{$user->description}}</textarea>
                                                                </div>
                                                                <div class="col-md-12 py-2">
                                                                    <label>Bio</label>
                                                                    <textarea style="min-height:120px;" class="form-control " name="bio">{{$user->bio}}</textarea>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse5"
                                                    aria-expanded="false" aria-controls="flush-collapse5">
                                                    <b>Preferences</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse5" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">Where possible we will only show
                                                            content related to your
                                                            preferences.</div>
                                                    </div>
                                                    <div class="col-md-12 textarea">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                <div class="col-md-12 py-2">
                                                                    <label>Show Me</label>
                                                                    <select class="form-select muiSelec" name="search_preferences[]" id="choices-multiple-remove-button" placeholder="Select Property Type" multiple>
                                                                        @foreach ($property_types as $property_type)
                                                                            <option value="{{$property_type->id}}" {{(@in_array($property_type->id, @json_decode($user->search_preferences) ?? []))?"selected":""}} >{{$property_type->name}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse6"
                                                    aria-expanded="false" aria-controls="flush-collapse6">
                                                    <b>Basic Details</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse6" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">This information is not displayed
                                                            on your profile.</div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                <div class="col-md-6 py-2">
                                                                    <label>First Name</label>
                                                                    <input class="form-control" data-required="1" name="first_name"
                                                                        type="text" value="{{$user->first_name}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Last Name</label>
                                                                    <input type="text" data-required="1" name="last_name"
                                                                        class="form-control " value="{{$user->last_name}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>MY Languages</label>
                                                                    <select name="language" class="form-select"
                                                                        id="user-language">
                                                                        <option value="en_US" {{$user->language=="en_US"?"selected":""}} >English</option>
                                                                        <option value="es_ES" {{$user->language=="es_ES"?"selected":""}}>Spanish</option>
                                                                        <option value="fr_FR" {{$user->language=="fr_FR"?"selected":""}}>French</option>
                                                                        <option value="zh_CN" {{$user->language=="zh_CN"?"selected":""}}>Chinese</option>
                                                                        <option value="de_DE" {{$user->language=="de_DE"?"selected":""}}>German</option>
                                                                        <option value="ru_RU" {{$user->language=="ru_RU"?"selected":""}}>Russian</option>
                                                                        <option value="ar" {{$user->language=="ar"?"selected":""}}>Arabic</option>
                                                                        <option value="ja" {{$user->language=="ja"?"selected":""}}>Japanese</option>
                                                                        <option value="ko_KR" {{$user->language=="ko_KR"?"selected":""}}>Korean</option>
                                                                        <option value="it_IT" {{$user->language=="it_IT"?"selected":""}}>Italian</option>
                                                                        <option value="nl_NL" {{$user->language=="nl_NL"?"selected":""}}>Dutch</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 py-2 d-none">
                                                                    <label>Pick your account type</label>
                                                                    <input type="text" data-required="1"
                                                                        class="form-control " value="">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse7"
                                                    aria-expanded="false" aria-controls="flush-collapse7">
                                                    <b>Address</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse7" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="fs-7 mt-3 opacity-50">Displayed on invoices and
                                                            purchase reciepts.</div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="py-3">
                                                            <div class="row">
                                                                <div class="col-md-6 py-2 d-none">
                                                                    <label>My Location</label>
                                                                    <select name="country_id" id="country_id" class="form-select">
                                                                        {{-- <option value="">Select Location</option> --}}
                                                                        @foreach ($countries as $country)
                                                                        <option value="{{$country->id}}" {{ ($country->id==$user->country_id)?"selected":"" }} >{{ucwords($country->name)}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>State</label>
                                                                    <select class="form-select" name="state_id" id="state_id" tabindex="7">
                                                                        @foreach ($states as $state)
                                                                        <option value="{{$state->id}}" {{$user->state_id==$state->id?"selected":""}}>
                                                                            {{ucfirst($state->name)}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>City</label>
                                                                    <select class="form-select" name="city_id" id="city_id" tabindex="7">
                                                                        @foreach ($cities as $city)
                                                                        <option value="{{$city->id}}" {{$user->city_id==$city->id?"selected":""}}>
                                                                            {{$city->name}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>County</label>
                                                                    <select class="form-select" name="county_id" id="county_id" tabindex="7">
                                                                        @foreach ($counties as $county)
                                                                        <option value="{{$county->id}}" {{$user->county_id==$county->id?"selected":""}}>
                                                                            {{$county->name}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Address 1</label>
                                                                    <input class="form-control" name="address1" type="text" value="{{$user->address1}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Address 2</label>
                                                                    <input type="text" name="address2" data-required="1" class="form-control " value="{{$user->address2}}">
                                                                </div>

                                                                <div class="col-md-6 py-2 d-none">
                                                                    <label>Town</label>
                                                                    <input class="form-control"
                                                                        type="text" name="town" value="{{$user->town}}">
                                                                </div>
                                                                <div class="col-md-6 py-2">
                                                                    <label>Zipcode</label>
                                                                    <input type="text" name="zip" data-required="1"
                                                                        class="form-control " value="{{$user->zip}}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-17"
                                                    aria-expanded="false" aria-controls="flush-17">
                                                    <b>User Avatar</b>
                                                </div>
                                            </h2>
                                            <div id="flush-17" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-6 py-2">
                                                        <div>
                                                            <label>Avatar</label>
                                                        </div>
                                                        <div class="row">
                                                            @php
                                                                $j=1;
                                                            @endphp
                                                            @for ($i=1; $i<=30; $i++)
                                                            <div class="col-4 col-md-2 text-center px-0">
                                                                <label for="av{{$i}}">
                                                                    <img alt="img" class="lazy img-fluid ppt-lazy"
                                                                        src="{{asset('/images/avatar/'.$i.'.png')}}">
                                                                </label>
                                                                <input class="user-avatar" id="av{{$i}}" type="radio" value="{{$i.'.png'}}" {{$i.'.png'==$user->avatar?"checked":""}} name="myavatar">
                                                            </div>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 py-2">
                                                        <div class="d-flex justify-content-between">
                                                            <label>Photo</label>
                                                        </div>
                                                        <div class="shadow-sm border slimPic" style="position: relative;" >
                                                            <input type="file" class="avatar-picker" name="avatar"
                                                                    accept="image/jpeg,image/png,image/gif,image/bmp"
                                                                    style="margin-bottom: 100%;position:absolute; top:0;left:0; width:100%; height:100%; z-index:99; opacity: 0;" id="imageFile1">
                                                            <div class="subDiv" style="z-index: 89;">
                                                                <label class="pic-label" for="">
                                                                    <i class="fa fa-upload opacity-50 mb-3"></i>
                                                                    <span class="small fw-bold opacity-50">Select Photo
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <img src="{{asset('images/avatar/'.$user->avatar)}}" class="avatar-image {{(in_array($user->avatar,['1.png','2.png','3.png','4.png','5.png','6.png','7.png','8.png','9.png','10.png','11.png','12.png','13.png','14.png','15.png','16.png','17.png','18.png','19.png','20.png','21.png','22.png','23.png','24.png','25.png','26.png','27.png','28.png','29.png','30.png']) || !$user->avatar)?"d-none":""}}" style="position: absolute; top:0;left:0;width:100%; height:100%; object-fit: cover; z-index: 90;" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse8"
                                                    aria-expanded="false" aria-controls="flush-collapse8">
                                                    <b>Background</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse8" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <p class="fs-7 mt-3 mb-3 opacity-50">The background image is
                                                            displayed on your public
                                                            profile
                                                            page.
                                                        </p>
                                                        <p class="mb-0"><b>Background Image</b></p>
                                                        <p class="small">Upload your own artwork (1300px / 200px)</p>
                                                    </div>
                                                    <div class="col-md-12 py-2 w-100 ">
                                                        <div class="shadow-sm border slimPic mb-4" style="height: 12rem; position: relative;">
                                                            <input type="file" class="cover-picker" name="cover_photo"
                                                                    accept="image/jpeg,image/png,image/gif,image/bmp"
                                                                    style="position:absolute; top:0;left:0; width:100%; height:100%; z-index:99; opacity: 10; opacity:0;" id="imageFile">
                                                            <div class="subDiv" style="left:40%">
                                                                <label class="pic-label">
                                                                    <i class="fa fa-upload opacity-50 mb-3"></i>
                                                                    <span class="small fw-bold opacity-50">Select Photo
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <img src="{{asset('images/cover/'.$user->cover_photo)}}" class="user-cover-image {{(in_array($user->cover_photo,['1.jpg','2.jpg','3.jpg','4.jpg','5.jpg','6.jpg','7.jpg','8.jpg','9.jpg','10.jpg','11.jpg','12.jpg','13.jpg','14.jpg']) || !$user->cover_photo)?"d-none":""}}" style="position: absolute; top:0;left:0;width:100%; height:100%; object-fit: cover; z-index: 90;" />
                                                        </div>
                                                        <p class="small mb-3">
                                                            or select a default display image.</p>
                                                    </div>
                                                    @for ($i=1; $i<=14; $i++)
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic{{$i}}">
                                                            <img src="{{asset("/images/cover/$i.jpg")}}" alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic{{$i}}" type="radio" value="{{"$i.jpg"}}" {{("$i.jpg"==$user->cover_photo)?"checked":""}} name="mycover">
                                                    </div>
                                                    @endfor
                                                    {{-- <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic1">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/1.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" checked id="pic1" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/1.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic2">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/2.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic2" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/2.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic3">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/3.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic3" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/3.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic4">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/4.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic4" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/4.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic5">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/5.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic5" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/5.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic6">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/6.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic6" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/6.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic7">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/7.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic7" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/7.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic8">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/8.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic8" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/8.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic9">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/9.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic9" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/9.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic10">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/10.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic10" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/10.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic11">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/11.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic11" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/11.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic12">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/12.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic12" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/12.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic13">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/13.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic13" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/13.jpg" name="mycover">
                                                    </div>
                                                    <div class="col-md-6 col-lg-4 text-center p-2">
                                                        <label for="pic14">
                                                            <img src="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/14.jpg"
                                                                alt="img" class="img-fluid">
                                                        </label>
                                                        <input class="mt-3 cover-photo" id="pic14" type="radio"
                                                            value="https://premiumpress1063.b-cdn.net/_demoimagesv10/backgroundimages/14.jpg" name="mycover">
                                                    </div> --}}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <div class="accordion-button collapsed ps-0 pe-0"
                                                    data-bs-toggle="collapse" data-bs-target="#flush-collapse9"
                                                    aria-expanded="false" aria-controls="flush-collapse9">
                                                    <b>Delete Account</b>
                                                </div>
                                            </h2>
                                            <div id="flush-collapse9" class="accordion-collapse collapse"
                                                aria-labelledby="flush-headingOne"
                                                data-bs-parent="#accordionFlushExample">
                                                <div class="row">
                                                    <div class="col-md-12 py-2 card">
                                                        <div class="p-3">
                                                            <p>We're really sad to see you go, but we understand that
                                                                situations change. </p>
                                                            <p>Click to 'delete account' button below to confirm you wish to
                                                                delete your account.</p>
                                                            <p>Once processed, you must logout and remain logged out for 30
                                                                days.</p>
                                                            <button type="button">Delete Account</button>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-lg mt-4 w-25 mb-4 change" type="submit">Save Changes</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
<script src="{{asset('assets/js/custom.js')}}"></script>
<script>
    $(function () {
        $('#country_id').on('change', function () {
            var country_id = $(this).val();
            $.ajax({
                type: "GET",
                url: "{{route('getStates')}}",
                data: {country_id},
                dataType: "json",
                success: function (response) {
                    if(response.success){
                        $('#state_id').html('');
                        var option = `<option value="">Select Region</option>`;
                            $('#state_id').append(option);
                        $.map(response.states, function (state, index) {
                            var option = `<option value="${state.id}">${state.name}</option>`;
                            $('#state_id').append(option);
                        });
                    }
                },
                error: function (error) {
                    console.log("Error: ", error);
                }
            });
        });


        $('.avatar-picker').on('change', function () {
            // alert('ok');
            $('.user-avatar').attr("checked" , false );
            var uri = window.URL.createObjectURL(this.files[0]);
            $('.avatar-image').attr('src', uri);
            $('.avatar-image').removeClass('d-none');
            // alert('ok');
        });

        $('.cover-picker').on('change', function () {
            $('.cover-photo').attr("checked" , false );
            var uri = window.URL.createObjectURL(this.files[0]);
            $('.user-cover-image').attr('src', uri);
            $('.user-cover-image').removeClass('d-none');
        });

    });
    //  ////////////////MultiSelect Tabs  //////////////////////////
    $(document).ready(function () {
      var multipleCancelButton = new Choices('.muiSelect', {
        removeItemButton: true,
        // maxItemCount:5,
        // searchResultLimit: 5,
        // renderChoiceLimit: 5

      });
      var multipleCancelButton = new Choices('.muiSelec', {
        removeItemButton: true,
      });

    });
</script>
@endpush
