{{--
  matchmaker-tradeoffs — areas where the property differs from your ideal.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $items (array — each entry has at least 'label')
--}}
@props(['items' => []])

@if(count($items) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-scale-balanced me-2" style="color:#ca8a04;"></i>Tradeoffs
        </h6>
    </div>
    <div class="card-body pt-1 pb-2">
        <ul class="list-unstyled mb-0">
            @foreach($items as $item)
                <li class="d-flex align-items-start gap-2 py-1 border-bottom" style="font-size:.875rem;">
                    <i class="fas fa-arrow-right-arrow-left flex-shrink-0 mt-1" style="color:#ca8a04;font-size:.8rem;"></i>
                    <span>{{ $item['label'] ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
