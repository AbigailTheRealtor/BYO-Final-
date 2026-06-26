{{--
  property-community — community features card.
  Section 1 (MLS Listing Information).
--}}
@props([
    'communityFeatures' => [],
    'petsAllowed'       => null,
])

@if(count($communityFeatures) > 0 || $petsAllowed)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-people-roof me-2 text-primary"></i>Community
        </h5>
    </div>
    <div class="card-body pt-2">
        @if(count($communityFeatures) > 0)
            <div class="d-flex flex-wrap gap-1 mb-2">
                @foreach($communityFeatures as $feature)
                    <span class="badge bg-light text-dark border" style="font-size:.8rem;font-weight:400;">{{ $feature }}</span>
                @endforeach
            </div>
        @endif
        @if($petsAllowed)
            <div class="text-muted mt-1" style="font-size:.85rem;">
                <i class="fas fa-paw me-1"></i>Pets: {{ $petsAllowed }}
            </div>
        @endif
    </div>
</div>
@endif
