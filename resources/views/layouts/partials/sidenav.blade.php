<div class="col-sm-12 col-md-3 col-lg-3 leftCol">
    <!-- DashBoard  -->
    <a href="{{ route('dashboard') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122">
                    </path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-2">
                    <b>Dashboard</b>
                </div>
                <div class="opacity-50 text-400 small">Here you'll find your account overview, recent account notices
                    and alerts.</div>
            </div>
        </div>
    </a>
    <!-- End  -->

    <!-- My Bid  -->
    <a href="{{ route('myBids') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2h2m3-4H9a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-1m-1 4l-3 3m0 0l-3-3m3 3V3">
                    </path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-2"><b>My Bid </b><span class="badge bg-danger ms-2">6</span></div>
                <div class="opacity-50 text-400 small">Any bids you have made or recieved can be found here.</div>
            </div>
        </div>
    </a>
    <!-- End  -->
    @if (in_array(auth()->user()->user_type, ['agent']))

        <a href="{{ route('tenant.biding.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Tenant's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->tenant_agent_auction_bid->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('landlord.biding.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Landlord's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->landlord_agent_auction_bid->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('buyer.biding.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Buyer's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->buyer_agent_auction_bid->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('seller.biding.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Seller's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->seller_agent_auction_bid->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>

        <!-- Property Auctions  -->
        <a href="{{ route('myAuctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Property Auctions</b>
                        @php
                            if (auth()->user()->user_type == 'agent') {
                                $my_pac = auth()->user()->property_auctions->count();
                            } elseif (auth()->user()->user_type == 'seller') {
                                $my_pac = auth()->user()->seller_properties->count();
                            } else {
                                $my_pac = 0;
                            }
                        @endphp
                        @if ($my_pac)
                            <span class="badge bg-danger ms-2">{{ $my_pac }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing auctions.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->

        <!-- Property Auctions  -->
        <a href="{{ route('agent.landlord.auctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Auctions for Landlords</b>
                        @php
                            if (auth()->user()->user_type == 'agent') {
                                $my_pac = auth()->user()->property_auctions->count();
                            } elseif (auth()->user()->user_type == 'seller') {
                                $my_pac = auth()->user()->seller_properties->count();
                            } else {
                                $my_pac = 0;
                            }
                        @endphp
                        @if ($my_pac)
                            <span class="badge bg-danger ms-2">{{ $my_pac }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing auctions.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->

        <!-- QR Code Settings  -->
        <a href="{{ route('agent.qr.settings') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>QR Code Settings</b>
                    </div>
                    <div class="opacity-50 text-400 small">Here you can Change the url of your qr code.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->
    @endif

    @if (auth()->user()->user_type == 'seller')

        <!-- Hire Seller's Agent Auctions  -->
        {{-- <a href="{{ route('seller.service.auction.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Service Auctions</b>
                        @php
                            $my_saa_count = auth()
                                ->user()
                                ->seller_agent_auctions->count();
                        @endphp
                        @if ($my_saa_count)
                            <span class="badge bg-danger ms-2">{{ $my_saa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your service auctions.</div>
                </div>
            </div>
        </a> --}}
        <!-- End  -->
        <!-- Hire Seller's Agent Auctions  -->
        <a href="{{ route('hireSellerAgentHireAuctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Hire Seller's Agent Auctions</b>
                        @php
                            $my_saa_count = auth()->user()->seller_agent_auctions->count();
                        @endphp
                        @if ($my_saa_count)
                            <span class="badge bg-danger ms-2">{{ $my_saa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing auctions.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->

        <!-- My Agents for seller  -->
        <a href="{{ route('seller.agents') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <span class="fa fa-users"></span>
                    {{-- <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg> --}}
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>My Agents</b>
                        @php
                            $my_saa_count = auth()->user()->seller_agent_auctions->count();
                        @endphp
                        {{-- @if ($my_pac) --}}
                        {{-- <span class="badge bg-danger ms-2">{{$my_saa_count}}</span> --}}
                        {{-- @endif --}}
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view your agents.</div>
                </div>
            </div>
        </a>
        <!-- End  -->
    @endif

    @if (in_array(auth()->user()->user_type, ['buyer']))
        <!-- Hire Buyer's Agent Auctions  -->
        <a href="{{ route('buyer.agent.auctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Hire Buyer's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->buyer_agent_auctions->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->

        <!-- My Agents for seller  -->
        <a href="{{ route('buyer.agents') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <span class="fa fa-users"></span>
                    {{-- <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg> --}}
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>My Agents</b>
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view your agents.</div>
                </div>
            </div>
        </a>
        <!-- End  -->
    @endif

    @if (in_array(auth()->user()->user_type, ['landlord']))
        <!-- Hire Buyer's Agent Auctions  -->
        <a href="{{ route('landlord.agent.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Hire Landlord's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->buyer_agent_auctions->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->

    @endif

    @if (in_array(auth()->user()->user_type, ['tenant']))
        <!-- Hire Buyer's Agent Auctions  -->
        <a href="{{ route('tenant.agent.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Hire Tenant's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->tenant_agent_auctions->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>

        <a href="{{ route('landlord.agent.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-100 mb-2"><b style="font-size: 15px">Hire Landlord's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->landlord_agent_auctions->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('buyer.agent.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Hire Buyer's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->buyer_agent_auctions->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('hireSellerAgentHireAuctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Hire Seller's Agent Auctions</b>
                        @php
                            $my_baa_count = auth()->user()->seller_agent_auctions->count();
                        @endphp
                        @if ($my_baa_count)
                            <span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view, manage and upgrade your existing
                        auctions.
                    </div>
                </div>
            </div>
        </a>
        <!-- End  -->

    @endif

    @if (in_array(auth()->user()->user_type, ['agent']))
        <!-- My Agents for seller  -->
        <a href="{{ route('agent.service.auctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <span class="fa fa-paper-plane-o"></span>
                    {{-- <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg> --}}
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Service Auctions</b>
                        @php
                            $my_asa_count = auth()->user()->agent_service_auctions->count();
                        @endphp
                        @if ($my_asa_count)
                            <span class="badge bg-danger ms-2">{{ $my_asa_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view your service auctions.</div>
                </div>
            </div>
        </a>
        <!-- End  -->
    @endif

    @if (in_array(auth()->user()->user_type, ['agent']))
        <a href="{{ route('buyer.criteria.auctions') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <span class="fa fa-paper-plane-o"></span>
                    {{-- <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg> --}}
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Buyer Criteria Auctions</b>
                        @php
                            $my_bca_count =
                                auth()->user()->user_type == 'agent'
                                    ? auth()->user()->criteria_auctions->count()
                                    : auth()->user()->buyer_criteria_auctions->count();
                        @endphp
                        @if ($my_bca_count)
                            <span class="badge bg-danger ms-2">{{ $my_bca_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view your Buyer Criteria auctions.</div>
                </div>
            </div>
        </a>

        <a href="{{ route('agent.tenant.criteria.auctions.list') }}">
            <div class="d-flex flex-row p-3 border-end border-bottom">
                <div class="me-3">
                    <span class="fa fa-paper-plane-o"></span>
                    {{-- <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg> --}}
                </div>
                <div class="w-100">
                    <div class="text-600 mb-2"><b>Tenant Criteria Auctions</b>
                        @php
                            $my_bca_count =
                                auth()->user()->user_type == 'agent'
                                    ? auth()->user()->criteria_auctions->count()
                                    : auth()->user()->buyer_criteria_auctions->count();
                        @endphp
                        @if ($my_bca_count)
                            <span class="badge bg-danger ms-2">{{ $my_bca_count }}</span>
                        @endif
                    </div>
                    <div class="opacity-50 text-400 small">Here you can view your Tenant Criteria auctions.</div>
                </div>
            </div>
        </a>
    @endif

    <!-- Messages  -->
    <a href="{{ route('messages') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                    </path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-2">
                    <b>Messages</b>
                </div>
                <div class="opacity-50 text-400 small">Here you can chat with other users using our private message
                    system.</div>
            </div>
        </div>
    </a>
    <!-- End  -->
    <!-- My Friends  -->
    <a href="{{ route('myFriends') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-2"><b>My Friends</b><span class="badge bg-danger ms-2">1</span></div>
                <div class="opacity-50 text-400 small">Build a friends list and manage your website connections here.
                </div>
            </div>
        </div>
    </a>
    <!-- End  -->
    <!-- Settings  -->
    <a href="{{ route('settings') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2">
                    </path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-2">
                    <b>Profile Settings</b>
                </div>
                <div class="opacity-50 text-400 small">Contact details, email, password and other account details can
                    be found here.</div>
            </div>
        </div>
    </a>
    <!-- End  -->
</div>
