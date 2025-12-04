<style>
    .social {
        display: flex;
        align-items: center;
    }

    .dropdown-toggle::after {
        display: none;
    }

    .btn-apna {
        background: transparent
    }

    .btn-apna i.fa-bell {
        font-size: 16px !important;
        color: #049399;
        padding-top: 5px
    }

    @keyframes bellAnimation {
        0% {
            transform: rotate(-10deg) scale(1);
        }

        50% {
            transform: rotate(10deg) scale(1.3);
        }

        100% {
            transform: rotate(-10deg) scale(1);
        }
    }

    .bell-icon {
        transition: transform 0.2s ease-in-out;
    }

    .bell-icon.bell-animation {
        animation: bellAnimation 0.8s;
        animation-iteration-count: 1;
        color: red;
        /* Change the color of the bell icon to make it more visible */
    }

    .badge12 {
        position: absolute;
        top: -12px;
        right: -6px;
        background: red;
        color: white;
        font-size: 10px;
        padding: 3px 7px 3px 6px;
        border-radius: 50%;
    }

    .dropdown-menu {
        padding: 10px;
    }

    .bell-icon {
        position: relative;
        font-size: 22px;
    }

    badge-count {
        position: absolute;
        top: -4px;
        right: -6px;
        background: red;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 50%;
    }

    /* Notification Dropdown */
    .notification-box {
        width: 260px !important;
        max-height: 300px;
        overflow-y: auto;
        padding: 0;
    }

    /* Notification Items */
    .notification-item {
        font-size: 12px;
        white-space: normal;
        /* allow wrapping */
        line-height: 1.2;
        padding: 8px 12px;
        border-bottom: 1px solid #f1f1f1;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-text {
        font-size: 12px;
        color: #333;
    }
</style>
<head>
      <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-id" content="{{ auth()->id() }}">
    @endauth

</head>
<div class="header">

    <!-- <div class="container"> -->
    <div class="subHeader">
        <div class="container d-flex justify-content-between">
            <div class="left">
                <a href="{{ route('sellerWorks') }}">How it works for Sellers</a>
                <a href="{{ route('sellerWorksAgent') }}">How it works for Seller’s Agents</a>
                <a href="{{ route('buyerWorks') }}">How it works for Buyer’s</a>
            </div>
            <div class="right d-flex">
                @if (auth()->user())
                    {{-- <a class="a" href="{{ route('logout') }}">Sign Out </a> --}}
                    <div class="dropdown dropdown-bubble">
                        <a class="a" href="#" data-bs-toggle="dropdown" aria-expanded="false"><i
                                class="fa-solid fa-user-large text-black"></i> {{ auth()->user()->name }} <i
                                class="fa fa-angle-down"></i></a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item"
                                    href="{{ auth()->user()->user_type == 'admin' ? route('admin.dashboard') : route('dashboard') }}"><i
                                        class="fa-solid fa-house-user me-2 text-black"></i> Dashboard</a>
                            </li>
                            <li>
                                @if (auth()->user()->user_type != 'admin')
                                    <a class="dropdown-item" href="{{ route('settings') }}"><i
                                            class="fa-solid fa-user-tie me-2 text-black"></i> Profile Settings</a>
                                @endif
                                <a class="dropdown-item" href="{{ route('password.change') }}"><i
                                        class="fa-solid fa-key me-2 text-black"></i> Change Password</a>
                            </li>
                            <li>
                                <hr class="dropdown-divider" />
                            </li>
                            <li><a class="dropdown-item" href="{{ route('logout') }}"><i
                                        class="fa-solid fa-right-from-bracket me-2 text-black"></i> Sign Out</a></li>

                        </ul>
                    </div>
                @else
                    <a class="a" href="{{ route('login') }}">Sign In </a>
                    <a class="a" href="{{ route('register') }}">Register </a>
                @endif
                {{-- Notification Bell Dropdown --}}
                <div class="social">
                    @if (auth()->user())
                        @php $unread = auth()->user()->unreadNotifications; @endphp

                        <li class="nav-item dropdown">
                            <a class="nav-link" id="notificationBell" data-bs-toggle="dropdown">
                                <button class="btn btn-apna dropdown-toggle" type="button">
                                    <i class="fa-solid fa-bell bell-icon">
                                        @if ($unread->count())
                                            <span class="badge12 bg-danger">{{ $unread->count() }}</span>
                                        @endif
                                    </i>
                                </button>
                            </a>

                            <ul id="notificationDropdown" class="dropdown-menu dropdown-menu-end"
                                style="width:300px; max-height:350px; overflow-y:auto;">
                                @forelse($unread as $note)
                                    <li class="dropdown-item notification-item"
                                        onclick="markAsRead('{{ $note->id }}', this)" style="cursor:pointer;">
                                        {{ $note->data['message'] ?? 'New Notification' }}
                                        <br>
                                        <small class="text-muted">{{ $note->created_at->diffForHumans() }}</small>
                                    </li>
                                @empty
                                    <li class="dropdown-item text-center text-muted">No new notifications</li>
                                @endforelse

                                @if ($unread->count())
                                    <hr class="my-1">
                                    <li class="dropdown-item text-center">
                                        <button class="btn btn-sm btn-light w-100" onclick="markAllAsRead()">Mark All as
                                            Read</button>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                </div>

            </div>
        </div>
    </div>
    <div class="navbar desktop-navbar">
        <div class="container d-flex justify-content-between">
            <div class="logo">
                <a href="{{ route('home') }}">
                    <img src="{{ asset(get_setting('logo')) }}" alt="" />
                </a>
            </div>
            <nav>
                <a class="item" href="{{ route('home') }}">Home</a>
                <span class="dropdown">
                    <span class="item" type="span" data-bs-toggle="dropdown" aria-expanded="false"> Seller
                    </span>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="{{ route('sellerWorks') }}"><i
                                    class="fa fa-angle-right"></i> How it works
                                for Sellers</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#"><i class="fa fa-angle-right"></i> List
                                Property</a>
                        </li>
                    </ul>
                </span>
                <span class="dropdown">
                    <span class="item" type="span" data-bs-toggle="dropdown" aria-expanded="false"> Buyer
                    </span>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="{{ route('buyerWorks') }}"><i class="fa fa-angle-right"></i>
                                How it works
                                for Buyers</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('searchListing') }}"><i
                                    class="fa fa-angle-right"></i> Make an
                                Offer on a Property</a>
                        </li>
                    </ul>
                </span>
                <span class="dropdown">
                    <span class="item" type="span" data-bs-toggle="dropdown" aria-expanded="false"> Agents
                    </span>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="{{ route('sellerWorksAgent') }}"><i
                                    class="fa fa-angle-right"></i> How it
                                works for Seller’s Agents</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('buyerWorksAgent') }}"><i
                                    class="fa fa-angle-right"></i> How it
                                works for Buyer’s Agent</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('searchListing') }}"><i
                                    class="fa fa-angle-right"></i> Bid on
                                a Property For a Buyer</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('searchListing') }}"><i
                                    class="fa fa-angle-right"></i> List a
                                Property For a Seller</a>
                        </li>
                    </ul>
                </span>
                <a class="item" href="{{ route('search.agents') }}">Search Agents</a>
                <a class="item" href="{{ route('searchListing') }}">Search Listings</a>
                <a class="item" href="{{ route('faqs') }}">FAQ</a>

                @if (auth()->user() && in_array(auth()->user()->user_type, ['agent']))
                    <span class="dropdown">
                        <button class="btn" type="btn" data-bs-toggle="dropdown" aria-expanded="false"> Add
                            Listing</button>
                        <ul class="dropdown-menu" style="margin-top:0px;">
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('agent.landlord.auction.add') }}">Add
                                    Property Listing (Rental)</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item" href="{{ route('add-listing') }}">Add
                                    Property Listing
                                    (Sale)</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('buyer_agent.auction.add') }}">Add
                                    Buyer Criteria Listing</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('agent.tenant.criteria.auction.add') }}">Add Tenant Criteria
                                    Listing</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('agent.service.auction.add') }}">Add
                                    Service Auction</a>
                            </li>

                            {{-- <li>
                            <a style="color: #333;" class="dropdown-item" href="{{ route('agent.referral.auction.add') }}">Add Referral Auction</a>
                        </li> --}}
                        </ul>
                    </span>
                @endif
                @if (auth()->user() && auth()->user()->user_type == 'seller')
                    <a class="item" href="{{ route('sellerAgentHireAuction') }}"><button class="btn">Hire
                            Seller's
                            Agent</button></a>
                @elseif (auth()->user() && auth()->user()->user_type == 'buyer')
                    <a class="item" href="{{ route('buyer.add-auction') }}"><button class="btn">Hire Buyer's
                            Agent</button></a>
                @elseif (auth()->user() && auth()->user()->user_type == 'landlord')
                    <a class="item" href="{{ route('landlord.hire.agent.auction') }}"><button class="btn">Hire
                            Landlord's
                            Agent</button></a>
                @elseif (auth()->user() && auth()->user()->user_type == 'tenant')
                    {{-- <a class="item" href="{{ route('hire.agent.auction') }}"><button class="btn">Hire Tenant's
              Agent</button></a> --}}

                    <span class="dropdown">
                        <button class="btn" type="btn" data-bs-toggle="dropdown" aria-expanded="false"> Hire
                            Agent
                        </button>
                        <ul class="dropdown-menu" style="margin-top:0px;">
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}"> Hire
                                    Tenant's
                                    Agent</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('hire.agent.auction', ['user_type' => 'landlord']) }}"> Hire
                                    Landlord's
                                    Agent</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('hire.agent.auction', ['user_type' => 'buyer']) }}"> Hire Buyer's
                                    Agent</a>
                            </li>
                            <li>
                                <a style="color: #333;" class="dropdown-item"
                                    href="{{ route('hire.agent.auction', ['user_type' => 'seller']) }}"> Hire
                                    Seller's
                                    Agent</a>
                            </li>

                        </ul>
                    </span>
                @endif
            </nav>
        </div>
    </div>
    <!-- Mobile Navbar -->
    <nav class="navbar mobile-navbar bg-light fixed-top">
        <div class="container-fluid p-0">
            <a class="logo" href="{{ route('home') }}"><img src="{{ asset('assets/pictures/logo.png') }}"
                    alt="" /></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end w-100" tabindex="-1" id="offcanvasNavbar"
                aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <a class="logo" href="{{ route('home') }}">
                        <h5 class="offcanvas-title" id="offcanvasNavbarLabel"><img width="25%"
                                src="{{ asset('assets/pictures/logo.png') }}" alt="" /></h5>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                        aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul>
                        <li><a href="{{ route('home') }}">Home</a></li>
                        <li>
                            <span class="dropdown">
                                <span type="span" data-bs-toggle="dropdown" aria-expanded="false"> Seller
                                </span>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('sellerWorks') }}"><i
                                                class="fa fa-angle-right"></i> How
                                            it works for Sellers</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#"><i class="fa fa-angle-right"></i>
                                            List Property</a>
                                    </li>
                                </ul>
                            </span>
                        </li>
                        <li>
                            <span class="dropdown">
                                <span type="span" data-bs-toggle="dropdown" aria-expanded="false"> Buyer
                                </span>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('buyerWorks') }}"><i
                                                class="fa fa-angle-right"></i> How
                                            it works for Buyers</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('searchListing') }}"><i
                                                class="fa fa-angle-right"></i>
                                            Make an Offer on a Property</a>
                                    </li>
                                </ul>
                            </span>
                        </li>
                        <li>
                            <span class="dropdown">
                                <span type="span" data-bs-toggle="dropdown" aria-expanded="false"> Agents
                                </span>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('sellerWorksAgent') }}"><i
                                                class="fa fa-angle-right"></i> How it works for Seller’s Agents</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('buyerWorksAgent') }}"><i
                                                class="fa fa-angle-right"></i>
                                            How it works for Buyer’s Agent</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('searchListing') }}"><i
                                                class="fa fa-angle-right"></i>
                                            Bid on a Property For a Buyer</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('searchListing') }}"><i
                                                class="fa fa-angle-right"></i>
                                            List a Property For a Seller</a>
                                    </li>
                                </ul>
                            </span>
                        </li>
                        <li>
                            <a href="{{ route('searchListing') }}">Search Listings</a>
                        </li>
                        <li><a href="{{ route('faqs') }}">FAQ</a></li>
                        <li>
                            <a href="{{ route('add-listing') }}"><button class="btn">Add a
                                    Listing</button></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <!-- </div> -->
</div>
{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
    integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"
    integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"
    integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous">
</script> --}}


<script>
document.addEventListener("DOMContentLoaded", function () {

    // Grab CSRF token and user ID dynamically
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : null;

    if (!csrfToken) {
        console.error("CSRF token not found. Notifications will not work.");
        return;
    }

    const dropdown = document.querySelector('#notificationDropdown');
    const bellIcon = document.querySelector('.bell-icon');

    function timeAgo(date) {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);
        let interval = Math.floor(seconds / 31536000);
        if(interval>=1) return interval+" year"+(interval>1?"s":"")+" ago";
        interval=Math.floor(seconds/2592000);
        if(interval>=1) return interval+" month"+(interval>1?"s":"")+" ago";
        interval=Math.floor(seconds/86400);
        if(interval>=1) return interval+" day"+(interval>1?"s":"")+" ago";
        interval=Math.floor(seconds/3600);
        if(interval>=1) return interval+" hour"+(interval>1?"s":"")+" ago";
        interval=Math.floor(seconds/60);
        if(interval>=1) return interval+" minute"+(interval>1?"s":"")+" ago";
        return seconds+" second"+(seconds>1?"s":"")+" ago";
    }

    function renderBadge(count) {
        let badge = document.querySelector('.badge12');
        if (badge) badge.remove();
        if (count>0 && bellIcon) {
            const span = document.createElement('span');
            span.className='badge12 bg-danger';
            span.style.cssText='position:absolute; top:-5px; right:-5px; font-size:0.7em; padding:2px 5px; border-radius:50%;';
            span.innerText=count;
            bellIcon.appendChild(span);
        }
    }

    function renderNotifications(notifications) {
        dropdown.innerHTML='';
        if(!notifications.length){
            dropdown.innerHTML='<li class="dropdown-item text-center text-muted">No new notifications</li>';
            return;
        }

        notifications.forEach(note=>{
            const li=document.createElement('li');
            li.className='dropdown-item notification-item';
            li.style.cursor='pointer';
            li.innerHTML=`${note.data.message}<br><small class="text-muted">${timeAgo(note.created_at)}</small>`;
            li.onclick=()=>markAsRead(note.id, li);
            dropdown.appendChild(li);
        });

        // Add "Mark All as Read"
        const hr=document.createElement('hr');
        hr.className='my-1';
        dropdown.appendChild(hr);
        const markAllLi=document.createElement('li');
        markAllLi.className='dropdown-item text-center';
        markAllLi.innerHTML=`<button class="btn btn-sm btn-light w-100" onclick="markAllAsRead()">Mark All as Read</button>`;
        dropdown.appendChild(markAllLi);
    }

    function fetchNotifications() {
        fetch('{{ route("notifications.fetch") }}', {
            headers: {'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':csrfToken}
        })
        .then(res=>res.json())
        .then(data=>{
            renderNotifications(data);
            renderBadge(data.length);
        })
        .catch(console.error);
    }

    window.markAsRead = function(id, el){
        fetch('{{ route("notifications.markRead") }}',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
            body:JSON.stringify({id})
        })
        .then(res=>res.json())
        .then(()=>{
            if(el) el.remove();
            const remaining = dropdown.querySelectorAll('.notification-item').length;
            renderBadge(remaining);
            if(!remaining) dropdown.innerHTML='<li class="dropdown-item text-center text-muted">No new notifications</li>';
        })
        .catch(console.error);
    }

    window.markAllAsRead = function(){
        fetch('{{ route("notifications.markAllRead") }}',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
        })
        .then(res=>res.json())
        .then(()=>{
            dropdown.innerHTML='<li class="dropdown-item text-center text-muted">No new notifications</li>';
            renderBadge(0);
        })
        .catch(console.error);
    }

    // Auto refresh every 30 seconds
    setInterval(fetchNotifications, 30000);

    // Fetch when bell clicked
    const bell=document.querySelector('#notificationBell');
    if(bell) bell.addEventListener('click', fetchNotifications);

    // Initial fetch
    fetchNotifications();

});
</script>
