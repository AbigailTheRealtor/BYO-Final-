{{--
  property-hoa-taxes — HOA details + annual taxes + CDD.
  Section 1 (MLS Listing Information).
--}}
@props([
    'hoa'           => false,
    'hoaFeeDisplay' => null,
    'hoaFrequency'  => null,
    'hoaName'       => null,
    'hoaAmenities'  => [],
    'taxAnnual'     => null,
    'cdd'           => false,
])

@if($hoa || $taxAnnual !== null || $cdd)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h5 class="mb-0 fw-semibold" style="font-size:.95rem;">
            <i class="fas fa-receipt me-2 text-primary"></i>Fees &amp; Taxes
        </h5>
    </div>
    <div class="card-body pt-2" style="font-size:.9rem;">

        @if($hoa)
            <div class="mb-2 pb-2 border-bottom">
                <div class="fw-semibold mb-1">HOA</div>
                @if($hoaFeeDisplay)
                    <div>
                        <span class="fw-semibold">{{ $hoaFeeDisplay }}</span>
                        @if($hoaFrequency) <span class="text-muted">/ {{ $hoaFrequency }}</span> @endif
                    </div>
                @endif
                @if($hoaName)
                    <div class="text-muted mt-1" style="font-size:.83rem;">{{ $hoaName }}</div>
                @endif
                @if(count($hoaAmenities) > 0)
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($hoaAmenities as $amenity)
                            <span class="badge bg-light text-dark border" style="font-size:.78rem;font-weight:400;">{{ $amenity }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if($taxAnnual !== null)
            <div class="mb-2 @if($cdd) pb-2 border-bottom @endif">
                <span class="text-muted">Annual Taxes:</span>
                <span class="fw-semibold ms-1">${{ number_format($taxAnnual, 0) }}</span>
            </div>
        @endif

        @if($cdd)
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-circle-info text-info"></i>
                <span class="text-muted" style="font-size:.85rem;">
                    This property is within a Community Development District (CDD).
                    Additional annual assessments may apply.
                </span>
            </div>
        @endif

    </div>
</div>
@endif
