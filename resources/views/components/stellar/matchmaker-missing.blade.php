{{--
  matchmaker-missing — listing data the scorer couldn't evaluate.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $items (array of {label})
--}}
@props(['items' => []])

@if(count($items) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-circle-question me-2 text-secondary"></i>Missing Information
        </h6>
    </div>
    <div class="card-body pt-1 pb-2">
        <p class="text-muted mb-2" style="font-size:.82rem;">
            The listing did not disclose these fields. They could not be included in your match score.
        </p>
        <ul class="mb-0" style="font-size:.875rem;padding-left:1.2rem;">
            @foreach($items as $item)
                <li>{{ $item['label'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif
