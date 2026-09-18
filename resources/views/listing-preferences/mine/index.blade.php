@extends('layouts.main')

@section('title', 'Your saved properties')

@section('content')
<div class="container py-4">

    <h4 class="mb-1">Your properties</h4>
    <p class="text-muted small mb-3">
        Only you can see this. Changing or removing a choice here never changes the listing itself.
    </p>

    @if(! $available)
        {{--
            An agent, seller or landlord account reaching this page is not being
            refused — Save / Maybe / Pass belongs to people who are shopping, and
            saying so is more accurate than a 403.
        --}}
        <div class="card p-4">
            <p class="mb-0">Saving properties is part of shopping as a buyer or a renter, so there is nothing here for this account.</p>
        </div>
    @else
        <ul class="nav nav-tabs mb-3">
            @foreach($tabs as $value => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $state->value === $value ? 'active' : '' }}"
                       href="{{ route('listing-preferences.mine.index', ['state' => $value]) }}">
                        {{ $label }} <span class="text-muted">({{ $counts[$value] ?? 0 }})</span>
                    </a>
                </li>
            @endforeach
            <li class="nav-item ms-auto">
                <a class="nav-link" href="{{ route('listing-preferences.mine.history') }}">History</a>
            </li>
        </ul>

        @if($page === null || $page->total() === 0)
            <div class="card p-4 text-center">
                <p class="mb-0 text-muted">
                    @if($state->value === 'pass')
                        You have not passed on anything yet.
                    @elseif($state->value === 'maybe')
                        Nothing is marked Maybe yet.
                    @else
                        You have not saved anything yet.
                    @endif
                </p>
            </div>
        @else
            @if($state->value === 'pass')
                <p class="text-muted small mb-3">
                    Passing hides nothing permanently. Anything here can be moved back to Maybe or Saved, or removed entirely.
                </p>
            @endif

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                @foreach($page as $preference)
                    @php
                        $cardKey = $preference->listing_type . ':' . $preference->listing_id;
                        $card    = $cards[$cardKey] ?? null;
                    @endphp

                    @if($card)
                        <div class="col">
                            @include('listing-preferences.mine._listing-card', [
                                'card'    => $card,
                                'reasons' => $reasons[$cardKey] ?? [],
                            ])
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-3">
                {{ $page->appends(['state' => $state->value])->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
