{{--
  matchmaker-category-bars — per-category match score progress bars.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $bars (array of {key, label, score, max, pct})
--}}
@props(['bars' => []])

@if(count($bars) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-chart-bar me-2" style="color:#6366f1;"></i>Category Scores
        </h6>
    </div>
    <div class="card-body pt-2 pb-3">
        @foreach($bars as $bar)
        @php
            $barColor = match(true) {
                $bar['pct'] >= 75 => '#16a34a',
                $bar['pct'] >= 50 => '#ca8a04',
                $bar['pct'] >= 25 => '#ea580c',
                default           => '#dc2626',
            };
        @endphp
        <div class="mb-2">
            <div class="d-flex justify-content-between mb-1" style="font-size:.82rem;">
                <span class="text-dark">{{ $bar['label'] }}</span>
                <span class="text-muted">{{ $bar['score'] }} / {{ $bar['max'] }}</span>
            </div>
            <div class="progress" style="height:6px;border-radius:3px;">
                <div class="progress-bar" role="progressbar"
                     style="width:{{ $bar['pct'] }}%;background-color:{{ $barColor }};"
                     aria-valuenow="{{ $bar['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
