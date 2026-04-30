@extends('layouts.main')
@push('styles')
<style>
    .userMain .userBlock {
        padding-bottom: 12px;
        margin-bottom: 30px;
        overflow: hidden;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        transition: box-shadow .2s;
    }
    .userMain .userBlock:hover {
        box-shadow: 0 4px 18px rgba(0,0,0,.09);
    }
    .userMain .userBlock .backgrounImg {
        overflow: hidden;
        height: 100px;
    }
    .userMain .userBlock .backgrounImg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .userMain .userBlock .userImg {
        text-align: center;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin: auto;
        overflow: hidden;
        margin-top: -40px;
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,.12);
    }
    .userMain .userBlock .userImg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .userMain .userBlock .userDescription {
        text-align: center;
        padding: 0 12px 4px;
    }
    .userMain .userBlock .userDescription h5 {
        margin-bottom: 2px;
        font-weight: 600;
    }
    .userMain .userBlock .userDescription p {
        margin-bottom: 6px;
        color: #6c757d;
        font-size: .9rem;
    }
    .userMain .userBlock .userDescription .btn {
        border-radius: 6px;
        color: #fff;
    }
    .userMain .userBlock .userDescription .btn:hover {
        opacity: .85;
    }
</style>
@endpush
@section('content')
<div class="buyerOfferContentDetails">
    <div class="container">
        <div class="mb-4">
            <h4 class="fw-bold">Browse Agents</h4>
            <p class="text-muted small">Find licensed real estate agents available to bid for your business. Message any agent to start the conversation.</p>
            <p class="text-muted small mb-0"><strong>{{ $count }}</strong> agent{{ $count != 1 ? 's' : '' }} found</p>
        </div>

        <div class="row userMain">
            @inject('carbon', 'Carbon\Carbon')
            @forelse ($agents as $agent)
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="userBlock">
                    <a href="{{ route('author', $agent->id) }}">
                        <div class="backgrounImg">
                            <img src="{{ asset('images/cover/'.($agent->cover_photo ? $agent->cover_photo : '3.jpg')) }}" alt="Agent cover">
                        </div>
                        <div class="userImg">
                            <x-avatar-img :avatar="$agent->avatar" :alt="$agent->name" />
                        </div>
                    </a>
                    <div class="userDescription mt-2">
                        <a href="{{ route('author', $agent->id) }}"><h5>{{ $agent->name }}</h5></a>
                        <p>@if($agent->city) {{ $agent->city->name }} @else <em>Location not listed</em> @endif</p>
                        <a href="{{ route('messages') }}"><button class="btn btn-success btn-sm"><i class="fa-regular fa-envelope me-1"></i>Message</button></a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card border-0 bg-light rounded-3 p-5 text-center">
                    <div class="text-muted">
                        <i class="fa-solid fa-users fa-3x mb-3 opacity-25"></i>
                        <h5 class="fw-semibold">No agents found</h5>
                        <p class="small mb-0">There are no agents matching your search yet. Check back soon or adjust your filters.</p>
                    </div>
                </div>
            </div>
            @endforelse

            {{ $agents->links('pagination.listing') }}
        </div>

        @if($count > 0)
        <p class="text-center small text-muted mt-2 text-uppercase" style="letter-spacing:.05em;">{{ $count }} result{{ $count != 1 ? 's' : '' }} found</p>
        @endif
    </div>
</div>
@endsection
@push('scripts')
@endpush
