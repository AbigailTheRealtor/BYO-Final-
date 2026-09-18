@extends('layouts.main')

@section('title', 'Your preference history')

@section('content')
<div class="container py-4">

    <h4 class="mb-1">Your history</h4>
    <p class="text-muted small mb-3">
        A record of the choices you have made. Only you can see this, and it cannot be edited.
    </p>

    <a class="btn btn-sm btn-outline-secondary mb-3" href="{{ route('listing-preferences.mine.index') }}">
        Back to your properties
    </a>

    @if(! $available)
        <div class="card p-4">
            <p class="mb-0">Saving properties is part of shopping as a buyer or a renter, so there is nothing here for this account.</p>
        </div>
    @elseif(count($rows) === 0)
        <div class="card p-4 text-center">
            <p class="mb-0 text-muted">You have not made any choices yet.</p>
        </div>
    @else
        {{--
            Compact on purpose. This is a record a person can scan, not an
            analytics dashboard: what the property was, what changed, and when.
        --}}
        <div class="list-group">
            @foreach($rows as $row)
                @php
                    $card = $row['ref'] ? ($cards[$row['ref']->type->value . ':' . $row['ref']->id] ?? null) : null;
                @endphp
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold">
                                @if($card && $card->available)
                                    @if($card->url)
                                        <a href="{{ $card->url }}" class="text-decoration-none">{{ $card->title }}</a>
                                    @else
                                        {{ $card->title }}
                                    @endif
                                @else
                                    <span class="text-muted">A listing that is no longer available</span>
                                @endif
                            </div>

                            {{-- The SENTENCE, never a stored value. A withdrawal
                                 reads "Preference removed"; no null, no subject
                                 key, no database id ever reaches this page. --}}
                            <div class="small">{{ $row['summary'] }}</div>

                            @if(count($row['reasons']) > 0)
                                <div class="mt-1">
                                    @foreach($row['reasons'] as $reason)
                                        <span class="badge bg-light text-dark border me-1">{{ $reason }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="text-end small text-muted">
                            @if($row['at'])
                                <div>{{ $row['at']->format('M j, Y') }}</div>
                                <div>{{ $row['at']->format('g:i a') }}</div>
                            @endif
                            @if($row['surface'])
                                <div>{{ $row['surface'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            {{ $events->links() }}
        </div>
    @endif
</div>
@endsection
