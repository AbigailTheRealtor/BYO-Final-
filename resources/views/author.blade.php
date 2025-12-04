@extends('layouts.main')
@php
   if ($user->user_type == 'agent') {
        $user_type = 'Real Estate Agent';
    } else {
        $user_type = ucfirst($user->user_type);
    }
@endphp
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/author.css') }}" />
@endpush
@section('content')
    @inject('carbon', 'Carbon\Carbon')
    <div class="mainAuthor">
        <div class="container">
            <div class="card">
                <div style="max-height: 300px; overflow: hidden;">
                    <img src="{{ $user->cover_photo ? asset('/images/cover/' . $user->cover_photo) : asset('assets/pictures/author/banner.jpg') }}"
                        style="width:100%; height:100%; object-fit: cover;" class="card-img-top" alt="banner">
                </div>
                <div class="card-body">
                    <!-- Review  -->
                    <div class="review container">
                        <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
                            <div class="left d-flex align-items-end flex-wrap">
                                <div class="position-relative image bg-light rounded-circle">
                                    <img src="{{ $user->avatar ? asset('images/avatar/' . $user->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png' }}"
                                        alt="">
                                    <span></span>
                                </div>
                                <div class="mt-5 ms-2">
                                    <p class="mb-1"><span><b>{{ $user->name }}</b></span>
                                        <span class="star opacity-50" data-bs-container="body" tabindex="0"
                                            data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top"
                                            data-bs-content="0 stars based on 0 reviews.">
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                            <i class="fa fa-star"></i>
                                        </span>
                                    </p>
                                    <span class="mb-0 opacity-50"><b class="span">{{ $user_type }}</b><span
                                            class="span"> 233
                                            VIEWS</span> <span>1 SUBSCRIBERS</span></span>
                                </div>
                            </div>
                            <div class="right text-center">
                                <button class="btn btn-lg ">Message Me</button>
                            </div>
                        </div>
                    </div>
                    <!-- End  -->
                </div>
            </div>
            <div class="buyerOfferContentDetails mt-3 row">
                <div class="col-sm-12 col-md-6 col-lg-9">
                    <div class="cardsDetails row  justify-content-start">
                            @include('author_inc.listing_menu')




                        @yield('listing')




                    </div>
                </div>

                <div class="col-sm-12 col-md-6 col-lg-3">
                    <div class="card">

                        @inject('country', 'App\Models\Country')
                        <div class="card-body">
                            <h5 class="mb-3">About </h5>
                            <div class="qr-code" style="width: 100%;">
                                {{qr_code(route('short.uri', $user->short_id), 150)}}
                            </div>
                            <ul class="list-group list-group-flush small text-uppercase">
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Joined</strong> <span
                                        class="mb-0">{{ $carbon::parse($user->created_at)->format('M d, Y') }}</span>
                                </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap"> <strong>Last
                                        Online</strong> <span class="mb-0">13 Days ago</span> </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap"> <strong>User
                                            Verified</strong>
                                        @if ($user->email_verified_at)
                                            <span class="mb-0"><span
                                                    class="onlinebadge online text-dark badge border px-2 bg-white"><i
                                                        class="fa fa-award text-success"></i> Email Verified</span></span>
                                        @else
                                            <span class="mb-0"><span
                                                    class="onlinebadge online text-dark badge border px-2 bg-white"> Not
                                                    Verified</span></span>
                                        @endif
                                </li>
                                {{-- <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Location</strong> <span
                                        class="mb-0">{{ $user->country_id ? $country::whereId($user->country_id)->first()->name : '' }}
                                        <span class="flag flag-af ppt_locationflag"></span></span>
                                </li> --}}
                                @if(auth()->user() && auth()->user()->user_type == 'agent')
                                @inject('laravelVideoEmbed', 'LaravelVideoEmbed')
                                @if($laravelVideoEmbed::parse($user->intro_video, false, false, ["width"=>"100%", "height"=>"auto"]))
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    {{-- <span class="mb-0" style="text-transform: none;">{{$user->intro_video}}</span> --}}
                                    <div class="w-100">
                                        {!! $laravelVideoEmbed::parse($user->intro_video, false, false, ["width"=>"100%", "height"=>"auto"]) !!}
                                    </div>
                                </li>
                                @endif
                                @endif
                                @if(auth()->user() && auth()->user()->user_type == 'agent')
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Phone Number</strong>
                                    <span class="mb-0">{{$user->phone}}</span>
                                </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Email</strong>
                                    <span class="mb-0" style="text-transform: none;">{{$user->email}}</span>
                                </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Brokerage</strong>
                                    <span class="mb-0" style="text-transform: none;">{{$user->brokerage}}</span>
                                </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Real Estate License #</strong>
                                    <span class="mb-0" style="text-transform: none;">{{$user->license_no}}</span>
                                </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Website</strong>
                                    <span class="mb-0" style="text-transform: none;"><a href="{{$user->website}}" target="_blank">{{$user->website}}</a></span>
                                </li>
                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Credit Offering</strong>
                                    <span class="mb-0" style="text-transform: none;">${{$user->credit_offered}}</span>
                                </li>
                                {{-- <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Services Offering</strong>
                                    <div>
                                        <ul>
                                            @foreach (json_decode($user->services) as $service)
                                                <li>{{$service}}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </li> --}}
                                @endif

                                <li class="list-group-item d-flex px-0 justify-content-between flex-wrap">
                                    <strong>Additional Details:</strong>
                                    <span class="mb-0" style="text-transform: none;">{{$user->description}}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="card-footer bg-white border-top text-center">
                            <button>Add Friend</button>
                            <button>Block User</button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
@endsection
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>

@endpush
