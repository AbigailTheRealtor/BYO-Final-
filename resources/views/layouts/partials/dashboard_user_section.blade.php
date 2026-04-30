@php
    $user = Auth::user();
    if ($user->user_type == 'buyer') {
        $user_type = 'Buyer';
    } elseif ($user->user_type == 'seller') {
        $user_type = 'Seller';
    } elseif ($user->user_type == 'agent') {
        $user_type = 'Real Estate Agent';
    } elseif ($user->user_type == 'landlord') {
        $user_type = 'Landlord';
    } elseif ($user->user_type == 'tenant') {
        $user_type = 'Tenant';
    } elseif ($user->user_type == 'admin') {
        $user_type = 'Admin';
    } else {
        $user_type = '';
    }
@endphp

<style>
    .dropdown-menu .dropdown-item {
        padding: 8px 16px;
    }

    .dropdown-menu .dropdown-divider {
        margin: 0;
    }

    .right .dropdown {
        display: inline-block;
        position: relative;
    }

    .right .dropdown .dropdown-menu {
        z-index: 1050;
        position: absolute;
        top: 100%;
        left: 0;
        min-width: 200px;
    }

    .right .dropdown:hover .dropdown-menu {
        display: block;
    }
</style>

<div class="card mb-0">
    <div class="card-body py-3">
        <div class="review container">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <a href="{{ route('author', $user) }}" class="text-decoration-none">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="position-relative" style="width:56px;height:56px;border-radius:50%;overflow:hidden;flex-shrink:0;">
                            <x-avatar-img :avatar="$user->avatar" :alt="$user->name" style="width:100%;height:100%;object-fit:cover;" />
                        </div>
                        <div>
                            <div class="fw-bold text-dark mb-0">{{ auth()->user()->name }}</div>
                            <div class="text-muted small">{{ $user_type }} &bull; {{ auth()->user()->email }}</div>
                            <div class="mt-1">
                                <span class="text-warning opacity-75" style="font-size:.75rem;">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if (auth()->user() && in_array(auth()->user()->user_type, ['buyer', 'landlord', 'tenant', 'seller']))
                        <span class="dropdown">
                            <button class="btn btn-primary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Hire Agent <i class="fa-solid fa-angle-down ms-1"></i>
                            </button>
                            <ul class="dropdown-menu" style="margin-top:0px;">
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}"><i class="fa-solid fa-key me-2 text-muted"></i>Hire Tenant's Agent</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'landlord']) }}"><i class="fa-solid fa-building me-2 text-muted"></i>Hire Landlord's Agent</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'buyer']) }}"><i class="fa-solid fa-search me-2 text-muted"></i>Hire Buyer's Agent</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'seller']) }}"><i class="fa-solid fa-gavel me-2 text-muted"></i>Hire Seller's Agent</a>
                                </li>
                            </ul>
                        </span>
                    @elseif(auth()->user() && auth()->user()->user_type == 'agent')
                        <span class="dropdown">
                            <button class="btn btn-primary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Hire Agent <i class="fa-solid fa-angle-down ms-1"></i>
                            </button>
                            <ul class="dropdown-menu" style="margin-top:0px;">
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}"><i class="fa-solid fa-key me-2 text-muted"></i>Hire Tenant's Agent</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'landlord']) }}"><i class="fa-solid fa-building me-2 text-muted"></i>Hire Landlord's Agent</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'buyer']) }}"><i class="fa-solid fa-search me-2 text-muted"></i>Hire Buyer's Agent</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'seller']) }}"><i class="fa-solid fa-gavel me-2 text-muted"></i>Hire Seller's Agent</a>
                                </li>
                            </ul>
                        </span>
                    @endif

                    <button class="btn btn-outline-secondary" onclick="window.location = '{{ route('logout') }}';">Logout</button>
                </div>
            </div>
        </div>
    </div>
</div>
