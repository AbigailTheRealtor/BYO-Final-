{{--
  matchmaker-caution — things to know / caution flags.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $items (array of {severity: 'warning'|'info', label})
--}}
@props(['items' => []])

@if(count($items) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-triangle-exclamation me-2" style="color:#ea580c;"></i>Things to Know
        </h6>
    </div>
    <div class="card-body pt-1 pb-2">
        <ul class="list-unstyled mb-0">
            @foreach($items as $item)
            @php
                $severity = $item['severity'] ?? 'info';
                $iconColor = $severity === 'warning' ? '#ea580c' : '#0369a1';
                $icon      = $severity === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info';
            @endphp
                <li class="d-flex align-items-start gap-2 py-1 border-bottom" style="font-size:.875rem;">
                    <i class="fas {{ $icon }} flex-shrink-0 mt-1" style="color:{{ $iconColor }};font-size:.85rem;"></i>
                    <span>{{ $item['label'] ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
