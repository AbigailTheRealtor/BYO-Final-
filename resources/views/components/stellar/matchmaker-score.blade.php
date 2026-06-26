{{--
  matchmaker-score — overall match score badge.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $totalScore (int), $scoreDisplay (string)
--}}
@props(['totalScore' => null, 'scoreDisplay' => null])

@if($totalScore !== null)
@php
    $color = match(true) {
        $totalScore >= 75 => '#16a34a',
        $totalScore >= 50 => '#ca8a04',
        $totalScore >= 25 => '#ea580c',
        default           => '#dc2626',
    };
    $label = match(true) {
        $totalScore >= 85 => 'Excellent Match',
        $totalScore >= 70 => 'Strong Match',
        $totalScore >= 50 => 'Good Match',
        $totalScore >= 30 => 'Partial Match',
        default           => 'Low Match',
    };
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex align-items-center gap-4 py-3">
        <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
             style="width:72px;height:72px;background:{{ $color }};color:#fff;">
            <div class="text-center">
                <div class="fw-bold lh-1" style="font-size:1.5rem;">{{ $totalScore }}</div>
                <div style="font-size:.6rem;opacity:.85;">/ 100</div>
            </div>
        </div>
        <div>
            <div class="fw-bold" style="font-size:1.15rem;color:{{ $color }};">{{ $label }}</div>
            @if($scoreDisplay)
                <div class="text-muted" style="font-size:.875rem;">Score: {{ $scoreDisplay }}</div>
            @endif
            <div class="text-muted" style="font-size:.82rem;">Based on your search criteria</div>
        </div>
    </div>
</div>
@endif
