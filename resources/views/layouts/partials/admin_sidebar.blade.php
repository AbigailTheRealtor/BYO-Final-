<nav style="height: 100%; padding-top:50px;">
    <div class="main-navbar" style="height: 80%;">
        <div id="mainnav" style="height: 100%;">
            <ul class="nav-menu custom-scrollbar" style="height: 100%;">
                <li class="back-btn">
                    <div class="mobile-back text-end"><span>Back</span><i
                            class="fa fa-angle-right ps-2" aria-hidden="true"></i></div>
                </li>
                <li><a class="nav-link" href="{{ route('admin.dashboard') }}"><i
                            data-feather="home"></i><span>Dashboard</span></a></li>
                <li class="dropdown"><a class="nav-link menu-title" href="javascript:void(0)"><i class="icofont icofont-users-alt-2 me-2"></i> <span>Users</span></a>
                    <ul class="nav-submenu menu-content">
                        {{-- <li><a class="submenu-title" href="javascript:void(0)">color version<span class="sub-arrow"><i class="fa fa-chevron-right"></i></span></a> --}}
                        {{-- <ul class="nav-sub-childmenu submenu-content"> --}}
                        <li><a href="{{ route('admin.buyer') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Buyer</a></li>
                        {{-- <li><a href="{{ route('admin.buyerAgent') }}" class="fw-bold"><i class="fa fa-circle-o"></i> Buyer Agent</a></li> --}}
                        <li><a href="{{ route('admin.seller') }}" class="fw-bold"><i class="fa fa-circle-o"></i> Seller</a></li>
                        {{-- <li><a href="{{ route('admin.sellerAgent') }}" class="fw-bold"><i class="fa fa-circle-o"></i> Seller Agent</a></li> --}}
                        <li><a href="{{ route('admin.agent') }}" class="fw-bold"><i class="fa fa-circle-o"></i> Agent</a></li>
                        <li><a href="{{ route('admin.userRequest') }}" class="fw-bold"><i class="fa fa-circle-o"></i> Pending Approval</a></li>
                        <li><a href="{{ route('admin.addAdmin') }}" class="fw-bold"><i class="fa fa-circle-o"></i> Admin Users</a></li>
                        {{-- </ul> --}}
                        {{-- </li> --}}
                    </ul>
                </li>


                <li><a class="nav-link menu-title" href="#"><i class="icofont icofont-growth me-2"></i> <span>Auctions</span></a>
                    <ul class="nav-submenu menu-content">
                        <li><a href="{{ route('admin.propertyAuctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Seller's Property</a></li>
                        <li><a href="{{ route('admin.criteriaAuctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Buyer's Criteria</a></li>
                        <li><a href="{{ route('admin.tenant.criteria.list') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Tenant's Criteria</a></li>
                        <li><a href="{{ route('admin.serviceAuctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Agent Service Needed</a></li>
                        {{-- <li><a href="{{ route('admin.sellerServiceAuctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Seller Service Auctions</a></li> --}}
                        <li><a href="{{ route('admin.sellerAgentAuctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Hire Seller's Agent</a></li>
                        <li><a href="{{ route('admin.buyerAgentAuctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Hire Buyer's Agent</a></li>
                        <li><a href="{{ route('admin.landlord.auctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Auctions for Landlords</a></li>
                        <li><a href="{{ route('admin.landlord.agent.auctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Hire Landlord's Agent</a></li>
                        <li><a href="{{ route('admin.tenant.agent.auctions') }}" class="fw-bold" ><i class="fa fa-circle-o"></i> Hire Tenant's Agent</a></li>
                    </ul>
                </li>
                <li><a class="nav-link" href="{{ route('admin.commonBotQuestions.index') }}"><i class="fa-solid fa-robot"></i> <span>Manage Common Chatbot Questions</span></a></li>
                {{-- <li><a class="nav-link" href="{{ route('admin.cities.index') }}"><i class="fa-solid fa-city"></i> <span>Manage Cities</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.counties.index') }}"><i class="fa-solid fa-globe"></i> <span>Manage Counties</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.agentServices.index') }}"><i class="fa-solid fa-house-laptop"></i> <span>Manage Agent Services</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.sellerServices.index') }}"><i class="fa-solid fa-house-laptop"></i> <span>Manage Seller Services</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.financings.index') }}"><i class="fa-solid fa-coins"></i> <span>Manage Financings</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.appliances.index') }}"><i class="fa-solid fa-sink"></i> <span>Manage Appliances</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.propertyTypes.index') }}"><i class="fa-solid fa-building"></i> <span>Manage Property Types</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.waterViewTypes.index') }}"><i class="fa-solid fa-umbrella-beach"></i> <span>Manage Water Views</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.waterExtras.index') }}"><i class="fa-solid fa-umbrella-beach"></i> <span>Manage Water Extras</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.heatingFuels.index') }}"><i class="fa-solid fa-fire"></i> <span>Manage Heating Fuels</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.feeIncludes.index') }}"><i class="fa-solid fa-tachograph-digital"></i> <span>Manage Fee Includes</span></a></li>
                <li><a class="nav-link" href="{{ route('admin.airConditioningTypes.index') }}"><i class="fa-solid fa-fan"></i> <span>Air Conditioning Types</span></a></li> --}}
                <li><a class="nav-link" href="{{ route('admin.settings') }}"><i class="icofont icofont-wheel me-2"></i> <span>Settings</span></a></li>
            </ul>
        </div>
    </div>
</nav>
