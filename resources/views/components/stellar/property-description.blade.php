{{--
  property-description — public remarks card.
  Section 1 (MLS Listing Information).
  Props: $remarks (string|null)
--}}
@props(['remarks' => null])

@if($remarks)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-align-left me-2 text-primary"></i>Description
        </h5>
    </div>
    <div class="card-body pt-1" style="font-size:.9rem;line-height:1.75;white-space:pre-line;">{{ $remarks }}</div>
</div>
@endif
