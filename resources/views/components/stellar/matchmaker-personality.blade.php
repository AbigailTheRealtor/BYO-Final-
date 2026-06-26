{{--
  matchmaker-personality — AI-generated property personality and marketing hooks.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $personality (array|null — merged result from PropertyPersonalityService)
--}}
@props(['personality' => null])

@php
    $primary     = $personality['primary_personality']     ?? null;
    $secondaries = $personality['secondary_personalities'] ?? [];
    $hooks       = $personality['marketing_hooks']         ?? [];
    $hasContent  = $primary || count($secondaries) > 0 || count($hooks) > 0;
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-sparkles me-2" style="color:#8b5cf6;"></i>Property Personality
        </h6>
    </div>
    <div class="card-body pt-2 pb-3">
        @if($hasContent)

            @if($primary)
                <div class="fw-semibold mb-1" style="font-size:.95rem;color:#4c1d95;">
                    {{ $primary }}
                </div>
            @endif

            @if(count($secondaries) > 0)
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @foreach($secondaries as $s)
                        <span class="badge" style="background:#ede9fe;color:#5b21b6;font-size:.78rem;font-weight:400;">
                            {{ $s }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if(count($hooks) > 0)
                <div class="text-muted mb-1" style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">
                    Marketing Highlights
                </div>
                <ul class="mb-0" style="font-size:.875rem;padding-left:1.2rem;">
                    @foreach($hooks as $hook)
                        @php $trait = is_array($hook) ? ($hook['trait'] ?? '') : (string)$hook; @endphp
                        @if($trait)
                            <li>{{ $trait }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif

        @else
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size:.875rem;">
                <i class="fas fa-hourglass-half text-secondary"></i>
                Property personality analysis not yet available.
            </div>
        @endif
    </div>
</div>
