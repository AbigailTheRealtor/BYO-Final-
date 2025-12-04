@extends('layouts.main')
@push('styles')
<style>
    #myTab .nav-link {
    font-size: 14px;
    padding: 5px;
    border: 1px solid rgba(0,0,0,0.2);
    margin: 1px;
    border-radius: 5px;
}
</style>
@endpush
@section('content')
    <div class="mainDashboard">
        <div class="container">
            @include('layouts.partials.dashboard_user_section')
            <div class="dashboardContentDetails mt-3">
                <div class="card">
                    <div class="row">
                        @include('layouts.partials.sidenav')
                        <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                            <h1>My Bids</h1>
                            <div class="p-2">
                                <ul class="nav nav-tabs pb-2" id="myTab" role="tablist">
                                    @php
                                        $id = auth()->id();
                                    @endphp
                                    @if(auth()->check())
                                    <li class="nav-item" role="presentation">
                                      <a href="{{route('myBids', 'seller-property')}}" class="nav-link {{ ($type=='seller-property')?"active":"" }}">Property Listing (Sale)</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'landlord-property')}}" class="nav-link {{ ($type=='landlord-property')?"active":"" }}">Property Listing (Rental)</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'buyer-criteria')}}" class="nav-link {{ ($type=='buyer-criteria')?"active":"" }}">Buyer's Criteria</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'tenant-criteria')}}" class="nav-link {{ ($type=='tenant-criteria')?"active":"" }}">Tenant's Criteria</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'agent-service')}}" class="nav-link {{ ($type=='agent-service')?"active":"" }}">Agent Service Needed</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'buyer-agent')}}" class="nav-link {{ ($type=='buyer-agent')?"active":"" }}">Hiring Buyer's Agent</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'seller-agent')}}" class="nav-link {{ ($type=='seller-agent')?"active":"" }}">Hiring Seller's Agent</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'landlord-agent')}}" class="nav-link {{ ($type=='landlord-agent')?"active":"" }}">Hiring Landlord's Agent</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a href="{{route('myBids', 'tenant-agent')}}" class="nav-link {{ ($type=='tenant-agent')?"active":"" }}">Hiring Tenant's Agent</a>
                                    </li>
                                    @endif
                                </ul>
                            </div>

                            @yield('bids-content')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
@endpush
