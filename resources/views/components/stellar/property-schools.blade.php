{{--
  property-schools — school district info card.
  Section 1 (MLS Listing Information).
--}}
@props([
    'elementary' => null,
    'middle'     => null,
    'high'       => null,
])

@if($elementary || $middle || $high)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-graduation-cap me-2 text-primary"></i>Schools
        </h5>
    </div>
    <div class="card-body pt-2" style="font-size:.9rem;">
        <dl class="mb-0">
            @if($elementary)
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <dt class="fw-normal text-muted">Elementary</dt>
                    <dd class="mb-0 text-end">{{ $elementary }}</dd>
                </div>
            @endif
            @if($middle)
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <dt class="fw-normal text-muted">Middle School</dt>
                    <dd class="mb-0 text-end">{{ $middle }}</dd>
                </div>
            @endif
            @if($high)
                <div class="d-flex justify-content-between py-1">
                    <dt class="fw-normal text-muted">High School</dt>
                    <dd class="mb-0 text-end">{{ $high }}</dd>
                </div>
            @endif
        </dl>
    </div>
</div>
@endif
