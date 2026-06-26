{{--
  matchmaker-why — why this property matches your criteria.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $items (array of {label, score_contribution})
--}}
@props(['items' => []])

@if(count($items) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-circle-check me-2" style="color:#16a34a;"></i>Why This Matches
        </h6>
    </div>
    <div class="card-body pt-1 pb-2">
        <ul class="list-unstyled mb-0">
            @foreach($items as $item)
                <li class="d-flex align-items-center gap-2 py-1 border-bottom" style="font-size:.875rem;">
                    <i class="fas fa-check-circle flex-shrink-0" style="color:#16a34a;font-size:.85rem;"></i>
                    <span class="flex-grow-1">{{ $item['label'] }}</span>
                    @if(($item['score_contribution'] ?? 0) > 0)
                        <span class="badge rounded-pill" style="background:#dcfce7;color:#166534;font-size:.73rem;">
                            +{{ $item['score_contribution'] }} pts
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
