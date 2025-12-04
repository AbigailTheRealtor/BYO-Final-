<div class="p-2">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        @if($user->user_type == "agent")
        <li class="nav-item" role="presentation">
          <a href="{{route('author', $id)}}?type=0" class="nav-link {{ ($type==0)?"active":"" }}">Property Listing (Sale)</a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=1" class="nav-link {{ ($type==1)?"active":"" }}">Property Listing (Rental)</a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=2" class="nav-link {{ ($type==2)?"active":"" }}">Buyer's Criteria</a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=3" class="nav-link {{ ($type==3)?"active":"" }}">Tenant's Criteria</a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=4" class="nav-link {{ ($type==4)?"active":"" }}">Agent Service Needed</a>
        </li>
        @endif
        @if($user->user_type == "buyer")
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=0" class="nav-link {{ ($type==0)?"active":"" }}">Hiring Buyer's Agent</a>
        </li>
        @endif
        @if($user->user_type == "seller")
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=0" class="nav-link {{ ($type==0)?"active":"" }}">Hiring Seller's Agent</a>
        </li>
        @endif
        @if($user->user_type == "landlord")
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=0" class="nav-link {{ ($type==0)?"active":"" }}">Hiring Landlord's Agent</a>
        </li>
        @endif
        @if($user->user_type == "tenant")
        <li class="nav-item" role="presentation">
            <a href="{{route('author', $id)}}?type=0" class="nav-link {{ ($type==0)?"active":"" }}">Hiring Tenant's Agent</a>
        </li>
        @endif


    </ul>
</div>
