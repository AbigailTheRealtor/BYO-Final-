{{--
  matchmaker-target-audience — buyer archetype tags from PropertyDnaProfile.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $archetypeTags (array, e.g. ['amenity:waterfront', 'feature:pool', 'use:commercial'])
--}}
@props(['archetypeTags' => []])

@php
    // Strip prefix (amenity:, feature:, use:, etc.) for display
    $displayTags = array_values(array_filter(array_map(function($tag) {
        $stripped = preg_replace('/^[a-z_]+:/', '', (string)$tag);
        return str_replace('_', ' ', $stripped);
    }, $archetypeTags)));
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-users me-2" style="color:#0891b2;"></i>Lifestyle Match
        </h6>
    </div>
    <div class="card-body pt-2 pb-3">
        @if(count($displayTags) > 0)
            <p class="text-muted mb-2" style="font-size:.82rem;">
                This property is well-suited for buyers with these lifestyle preferences:
            </p>
            <div class="d-flex flex-wrap gap-1">
                @foreach($displayTags as $tag)
                    <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:.82rem;font-weight:400;text-transform:capitalize;">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        @else
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size:.875rem;">
                <i class="fas fa-hourglass-half text-secondary"></i>
                Target audience analysis not yet available.
            </div>
        @endif
    </div>
</div>
