{{--
  matchmaker-walkability — Walk Score, Transit Score, Bike Score.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $walkScore, $transitScore, $bikeScore (numeric or null)
--}}
@props(['walkScore' => null, 'transitScore' => null, 'bikeScore' => null])

@php
    $hasAny = $walkScore !== null || $transitScore !== null || $bikeScore !== null;

    $scoreLabel = function($score) {
        if ($score === null) return null;
        return match(true) {
            $score >= 90 => "Walker's Paradise",
            $score >= 70 => 'Very Walkable',
            $score >= 50 => 'Somewhat Walkable',
            $score >= 25 => 'Car-Dependent',
            default      => 'Almost All Errands Need a Car',
        };
    };

    $scoreColor = function($score) {
        if ($score === null) return '#9ca3af';
        return match(true) {
            $score >= 70 => '#16a34a',
            $score >= 50 => '#ca8a04',
            default      => '#6b7280',
        };
    };

    $scores = array_filter([
        ['icon' => 'fa-person-walking', 'label' => 'Walk Score',    'value' => $walkScore,    'detail' => $scoreLabel($walkScore)],
        ['icon' => 'fa-bus',            'label' => 'Transit Score',  'value' => $transitScore, 'detail' => null],
        ['icon' => 'fa-bicycle',        'label' => 'Bike Score',     'value' => $bikeScore,    'detail' => null],
    ], fn($s) => $s['value'] !== null);
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-person-walking me-2" style="color:#059669;"></i>Walkability
        </h6>
    </div>
    <div class="card-body pt-2 pb-3">
        @if($hasAny)
            <div class="d-flex flex-wrap gap-3">
                @foreach($scores as $s)
                @php $color = $scoreColor($s['value']); @endphp
                <div class="text-center" style="min-width:80px;">
                    <div class="fw-bold" style="font-size:1.6rem;color:{{ $color }};">{{ $s['value'] }}</div>
                    <div class="text-muted" style="font-size:.75rem;">{{ $s['label'] }}</div>
                    @if($s['detail'])
                        <div style="font-size:.72rem;color:{{ $color }};">{{ $s['detail'] }}</div>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="text-muted mt-2" style="font-size:.75rem;">
                Scores provided by Walk Score&reg;. All rights reserved.
            </div>
        @else
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size:.875rem;">
                <i class="fas fa-hourglass-half text-secondary"></i>
                Walkability scores not yet computed for this property.
            </div>
        @endif
    </div>
</div>
