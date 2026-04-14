<div class="col-sm-12 col-md-3 col-lg-3 leftCol">

    {{-- ===== HIRE AGENT (Quick Action) ===== --}}
    @if (in_array(auth()->user()->user_type, ['seller', 'buyer', 'landlord', 'tenant', 'agent']))
    <div class="px-3 pt-3 pb-2">
        @if (auth()->user()->user_type === 'seller')
            <a href="{{ route('sellerAgentHireAuction') }}" class="btn btn-primary w-100 fw-semibold">+ Hire Agent</a>
        @elseif (auth()->user()->user_type === 'buyer')
            <a href="{{ route('buyer.add-auction') }}" class="btn btn-primary w-100 fw-semibold">+ Hire Agent</a>
        @elseif (auth()->user()->user_type === 'landlord')
            <a href="{{ route('landlord.hire.agent.auction') }}" class="btn btn-primary w-100 fw-semibold">+ Hire Agent</a>
        @elseif (auth()->user()->user_type === 'tenant')
            <a href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}" class="btn btn-primary w-100 fw-semibold">+ Hire Agent</a>
        @elseif (auth()->user()->user_type === 'agent')
            <div class="dropdown">
                <button class="btn btn-primary w-100 fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">Hire Agent</button>
                <ul class="dropdown-menu w-100">
                    <li><a class="dropdown-item" href="{{ route('agent.landlord.auction.add') }}">Add Property Listing (Rental)</a></li>
                    <li><a class="dropdown-item" href="{{ route('add-listing') }}">Add Property Listing (Sale)</a></li>
                    <li><a class="dropdown-item" href="{{ route('buyer_agent.auction.add') }}">Add Buyer Criteria Listing</a></li>
                    <li><a class="dropdown-item" href="{{ route('agent.tenant.criteria.auction.add') }}">Add Tenant Criteria Listing</a></li>
                    <li><a class="dropdown-item" href="{{ route('agent.service.auction.add') }}">Add Service Auction</a></li>
                </ul>
            </div>
        @endif
    </div>
    @endif

    {{-- ===== DASHBOARD ===== --}}
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">Dashboard</div>
    <a href="{{ route('dashboard') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Dashboard</b></div>
                <div class="opacity-50 text-400 small">Account overview, recent notices and alerts.</div>
            </div>
        </div>
    </a>

    {{-- ===== MY LISTINGS ===== --}}
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">My Listings</div>

    @if (in_array(auth()->user()->user_type, ['agent', 'seller']))
    <a href="{{ route('myAuctions') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-home" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Property Listings</b>
                    @php
                        if (auth()->user()->user_type == 'agent') {
                            $my_pac = auth()->user()->property_auctions->count();
                        } elseif (auth()->user()->user_type == 'seller') {
                            $my_pac = auth()->user()->seller_properties->count();
                        } else { $my_pac = 0; }
                    @endphp
                    @if ($my_pac)<span class="badge bg-danger ms-2">{{ $my_pac }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">View and manage your active property listings.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['agent']))
    <a href="{{ route('agent.landlord.auctions') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-building" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Rental Listings</b></div>
                <div class="opacity-50 text-400 small">Manage your rental property listings for landlords.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['agent']))
    <a href="{{ route('buyer.criteria.auctions') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-search" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Buyer Criteria Listings</b>
                    @php
                        $my_bca_count = auth()->user()->user_type == 'agent'
                            ? auth()->user()->criteria_auctions->count()
                            : auth()->user()->buyer_criteria_auctions->count();
                    @endphp
                    @if ($my_bca_count)<span class="badge bg-danger ms-2">{{ $my_bca_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where buyers define criteria for agent bids.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('agent.tenant.criteria.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-key" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Tenant Criteria Listings</b></div>
                <div class="opacity-50 text-400 small">Listings where tenants define criteria for agent bids.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['seller']))
    <a href="{{ route('hireSellerAgentHireAuctions') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-gavel" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Agent Listings</b>
                    @php $my_saa_count = auth()->user()->seller_agent_auctions->count(); @endphp
                    @if ($my_saa_count)<span class="badge bg-danger ms-2">{{ $my_saa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to represent you as a Seller.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['buyer']))
    <a href="{{ route('buyer.agent.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-gavel" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Agent Listings</b>
                    @php $my_baa_count = auth()->user()->buyer_agent_auctions->count(); @endphp
                    @if ($my_baa_count)<span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to represent you as a Buyer.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['landlord']))
    <a href="{{ route('landlord.agent.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-gavel" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Agent Listings</b>
                    @php $my_laa_count = auth()->user()->landlord_agent_auctions->count(); @endphp
                    @if ($my_laa_count)<span class="badge bg-danger ms-2">{{ $my_laa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to represent you as a Landlord.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['tenant']))
    <div class="small text-uppercase text-muted fw-bold px-3 pt-2 pb-1" style="letter-spacing:.07em;font-size:.68rem;">Hire Agent Listings</div>
    <a href="{{ route('tenant.agent.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-gavel" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Tenant's Agent</b>
                    @php $my_taa_count = auth()->user()->tenant_agent_auctions->count(); @endphp
                    @if ($my_taa_count)<span class="badge bg-danger ms-2">{{ $my_taa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to represent you as a Tenant.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('landlord.agent.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-building" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Landlord's Agent</b>
                    @php $my_llaa_count = auth()->user()->landlord_agent_auctions->count(); @endphp
                    @if ($my_llaa_count)<span class="badge bg-danger ms-2">{{ $my_llaa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to manage your property as a Landlord.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('buyer.agent.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-home" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Buyer's Agent</b>
                    @php $my_tbaa_count = auth()->user()->buyer_agent_auctions->count(); @endphp
                    @if ($my_tbaa_count)<span class="badge bg-danger ms-2">{{ $my_tbaa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to represent you as a Buyer.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('hireSellerAgentHireAuctions') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-sign-out" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Hire Seller's Agent</b>
                    @php $my_tsaa_count = auth()->user()->seller_agent_auctions->count(); @endphp
                    @if ($my_tsaa_count)<span class="badge bg-danger ms-2">{{ $my_tsaa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Listings where agents bid to represent you as a Seller.</div>
            </div>
        </div>
    </a>
    @endif

    {{-- ===== MY BIDS ===== --}}
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">My Bids</div>

    @if (in_array(auth()->user()->user_type, ['tenant']))
    <a href="{{ route('myBids', 'agent-bids') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-gavel" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>My Bids</b>
                    @php
                        $pending_agent_bids_count = \App\Models\TenantAgentAuctionBid::whereHas('auction', function($q) {
                            $q->where('user_id', auth()->id());
                        })->whereIn('status', ['Active', 'Countered'])->count();
                    @endphp
                    @if ($pending_agent_bids_count)<span class="badge bg-danger ms-2">{{ $pending_agent_bids_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">View and respond to bids from agents on your listings.</div>
            </div>
        </div>
    </a>
    @else
    <a href="{{ route('myBids') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-gavel" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>My Bids</b></div>
                <div class="opacity-50 text-400 small">Bids you have made or received on your listings.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['agent']))
    <a href="{{ route('tenant.biding.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-check-circle" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Tenant Agent Bids</b>
                    @php $my_baa_count = auth()->user()->tenant_agent_auction_bid->count(); @endphp
                    @if ($my_baa_count)<span class="badge bg-danger ms-2">{{ $my_baa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Bids you've placed on Tenant hire-agent listings.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('landlord.biding.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-check-circle" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Landlord Agent Bids</b>
                    @php $my_lbaa_count = auth()->user()->landlord_agent_auction_bid->count(); @endphp
                    @if ($my_lbaa_count)<span class="badge bg-danger ms-2">{{ $my_lbaa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Bids you've placed on Landlord hire-agent listings.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('buyer.biding.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-check-circle" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Buyer Agent Bids</b>
                    @php $my_bbaa_count = auth()->user()->buyer_agent_auction_bid->count(); @endphp
                    @if ($my_bbaa_count)<span class="badge bg-danger ms-2">{{ $my_bbaa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Bids you've placed on Buyer hire-agent listings.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('seller.biding.auctions.list') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-check-circle" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Seller Agent Bids</b>
                    @php $my_sbaa_count = auth()->user()->seller_agent_auction_bid->count(); @endphp
                    @if ($my_sbaa_count)<span class="badge bg-danger ms-2">{{ $my_sbaa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Bids you've placed on Seller hire-agent listings.</div>
            </div>
        </div>
    </a>
    <a href="{{ route('agent.service.auctions') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-briefcase" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Service Auction Bids</b>
                    @php $my_asa_count = auth()->user()->agent_service_auctions->count(); @endphp
                    @if ($my_asa_count)<span class="badge bg-danger ms-2">{{ $my_asa_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">Bids and activity from service auctions.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['landlord']))
    <a href="{{ route('myBids', 'hire-landlord-agent-bids') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-check-circle" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Agent Bids Received</b>
                    @php
                        $pending_landlord_agent_bids_count = \App\Models\LandlordAgentAuctionBid::whereHas('auction', function($q) {
                            $q->where('user_id', auth()->id());
                        })->get()->filter(function($bid) {
                            return in_array($bid->bid_status, ['Active', 'Countered']);
                        })->count();
                    @endphp
                    @if ($pending_landlord_agent_bids_count)<span class="badge bg-danger ms-2">{{ $pending_landlord_agent_bids_count }}</span>@endif
                </div>
                <div class="opacity-50 text-400 small">View and manage bids from agents on your listings.</div>
            </div>
        </div>
    </a>
    @endif

    {{-- ===== ACTIVITY ===== --}}
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">Activity</div>

    @if (in_array(auth()->user()->user_type, ['seller']))
    <a href="{{ route('seller.agents') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-users" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>My Agents</b></div>
                <div class="opacity-50 text-400 small">View and communicate with your hired agents.</div>
            </div>
        </div>
    </a>
    @endif

    @if (in_array(auth()->user()->user_type, ['buyer']))
    <a href="{{ route('buyer.agents') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa fa-users" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>My Agents</b></div>
                <div class="opacity-50 text-400 small">View and communicate with your hired agents.</div>
            </div>
        </div>
    </a>
    @endif

    <a href="{{ route('myFriends') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Connections</b></div>
                <div class="opacity-50 text-400 small">Manage your network of connections and referrals.</div>
            </div>
        </div>
    </a>

    {{-- ===== MESSAGES ===== --}}
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">Messages</div>

    <a href="{{ route('messages') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Messages</b></div>
                <div class="opacity-50 text-400 small">Private messaging with clients, agents, and connections.</div>
            </div>
        </div>
    </a>

    {{-- ===== QR CODE SETTINGS (Agents only) ===== --}}
    @if (in_array(auth()->user()->user_type, ['agent']))
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">Referral Dashboard</div>

    <a href="{{ route('agent.qr.settings') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3"><i class="fa-solid fa-qrcode" style="font-size:1.1rem;line-height:1.5rem;"></i></div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>QR Code &amp; Hire Me Widget</b></div>
                <div class="opacity-50 text-400 small">Customize your referral QR code and Hire Me landing link.</div>
            </div>
        </div>
    </a>
    @endif

    {{-- ===== PROFILE / SETTINGS ===== --}}
    <div class="small text-uppercase text-muted fw-bold px-3 pt-3 pb-1" style="letter-spacing:.07em;font-size:.7rem;">Profile &amp; Settings</div>

    <a href="{{ route('settings') }}">
        <div class="d-flex flex-row p-3 border-end border-bottom">
            <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                </svg>
            </div>
            <div class="w-100">
                <div class="text-600 mb-1"><b>Profile Settings</b></div>
                <div class="opacity-50 text-400 small">Update your contact details, email, password, and account information.</div>
            </div>
        </div>
    </a>

</div>
