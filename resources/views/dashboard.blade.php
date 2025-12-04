@extends('layouts.main')
@php
    $user = auth()->user();
@endphp
@section('content')
    <div class="mainDashboard">
        <div class="container">

            @include('layouts.partials.dashboard_user_section')

            <div class="dashboardContentDetails mt-3">
                <div class="card">
                    <div class="row">

                        @include('layouts.partials.sidenav')

                        <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                            <div class="container mt-5">
                                <h1>Welcome Back</h1>
                                <p>Here's an overview of your account.</p>
                                <!-- Section 1  -->
                                <div class="p-3 p-md-4 mt-2 mb-5 card">
                                    <div class="d-md-flex justift-content-between userDetails">
                                        <div class="d-md-flex border-end pr-3 w-100 me-3 mb-3">
                                            <div class="me-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                    </path>
                                                </svg>
                                            </div>
                                            <a href="/" class="text-black text-decoration-none">
                                                <span>
                                                    <div class="text-600">My Username</div>
                                                    <div class="small opacity-80 text-sm-start" style="max-width: 150px">
                                                        {{ $user->user_name }}
                                                    </div>
                                                </span>
                                            </a>
                                        </div>

                                        <div class="d-md-flex border-end pr-3 w-100 me-3 mb-3">
                                            <div class="me-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                    </path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                            <a href="/" class="text-black text-decoration-none">
                                                <span>
                                                    <div class="text-600">My Location</div>
                                                    <div class="text-sm-start opacity-80" style="max-width: 150px">
                                                        Afghanistan</div>
                                                </span>
                                            </a>
                                        </div>

                                        <div class="d-md-flex w-100">
                                            <div class="me-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                                    </path>
                                                </svg>
                                            </div>
                                            <a href="/" class="text-black text-decoration-none">
                                                <span>
                                                    <div class="text-600">My Email</div>
                                                    <div class="text-sm-start opacity-80 text-truncate"
                                                        style="max-width: 150px">{{ $user->email }}
                                                    </div>
                                                </span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <!-- End  -->
                                <!-- Section 2 -->
                                <div class="bg-light p-3 p-md-5 rounded-2 mb-4 position-relative">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="lh-30">
                                                <div class="fs-5 text-600 mb-2">Welcome back!</div>
                                                We are constantly updating and improving our service. If you have any
                                                questions or feedback get in
                                                touch - we would love to hear them.
                                            </div>
                                            <div class="mt-3 fs-lg"><a href="/" class="badge text-bg-light p-2"
                                                    style="border: 1px solid #e0e0e0;background-color: #fff!important;">Contact
                                                    us</a></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="my-4 my-md-0">
                                                <div class="position-relative">
                                                    <img src="{{ asset('assets/pictures/dashboard.jpg') }}"
                                                        class="img-fluid rounded-2" alt="img" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End  -->
                                <div class="fw-bold">Recent Notices</div>
                                <div class="opacity-50 mt-4 fw-bold">No notifications found.</div>
                                <div class="notification">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
