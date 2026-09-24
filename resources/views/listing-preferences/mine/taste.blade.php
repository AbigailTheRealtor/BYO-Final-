@extends('layouts.main')

@section('title', 'Your Home Taste')

@section('content')
<div class="container py-4" data-home-taste>

    <h4 class="mb-1">Your Home Taste</h4>
    <p class="text-muted small mb-2">
        Patterns in your own Save, Maybe and Pass choices and the reasons you picked. Only you can see this.
    </p>
    <p class="text-muted small mb-3">
        Some patterns come from reasons you picked. Others are details the homes you chose currently list, and each one says which it is.
        We only show a pattern once several of your choices point the same way, and it changes as you keep choosing.
        @if(\App\Support\ListingPreferences\Taste\TasteDnaAvailability::rerankingEnabled())
            It does not change which homes you are shown or their match scores. When your results are sorted by Best Match,
            it can reorder homes that match your search about equally — and the results page lets you switch that off.
        @else
            It does not change which homes you are shown or the order they appear in.
        @endif
    </p>

    <a class="btn btn-sm btn-outline-secondary mb-3" href="{{ route('listing-preferences.mine.index') }}">
        Back to your properties
    </a>

    @if(! $available)
        <div class="card p-4">
            <p class="mb-0">Saving properties is part of shopping as a buyer or a renter, so there is nothing here for this account.</p>
        </div>
    @elseif($incomplete ?? false)
        <div class="card p-4 text-center" data-home-taste-incomplete>
            <p class="mb-1">We can't summarise your taste right now.</p>
            <p class="mb-0 text-muted small">
                You have made more choices than this page can read in full, and we won't show patterns based on only part of them.
                Your Saved, Maybe and Passed homes are all still kept.
            </p>
        </div>
    @elseif($groups === [])
        <div class="card p-4 text-center" data-home-taste-empty>
            <p class="mb-1">No patterns yet.</p>
            <p class="mb-0 text-muted small">
                Keep using Save, Maybe and Pass — and pick a reason when one fits. Once a few of your choices agree, what they have in common will appear here.
            </p>
        </div>
    @else
        {{--
            Every string below was worded by TasteObservationPresenter. This view
            adds headings and layout and nothing else: no score, no percentage,
            no key and no id reaches it.
        --}}
        @foreach($groups as $group => $observations)
            <section class="mb-4" data-home-taste-group="{{ $group }}">
                <h5 class="mb-2">{{ $labels[$group] ?? '' }}</h5>
                <div class="list-group">
                    @foreach($observations as $observation)
                        <div class="list-group-item" data-home-taste-observation>
                            <div class="fw-semibold">{{ $observation->headline }}</div>
                            <div>{{ $observation->summary }}</div>
                            <div class="text-muted small mt-1">
                                {{ $observation->evidence }} {{ $observation->source }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection
