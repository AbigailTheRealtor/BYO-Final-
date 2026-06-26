{{--
  matchmaker-flood-zone — FEMA flood zone analysis card.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Flood zone data comes from the Location DNA pipeline (separate from the
  distance summary). This component renders a placeholder until the pipeline
  integrates flood zone data into the summary contract.
  Props: $locationSummary (full LocationDnaSummaryService response, or [])
--}}
@props(['locationSummary' => []])

@php
    $status = $locationSummary['status'] ?? null;
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-water me-2" style="color:#0369a1;"></i>Flood Zone
        </h6>
    </div>
    <div class="card-body pt-2 pb-3">
        <div class="d-flex align-items-start gap-2 text-muted" style="font-size:.875rem;">
            <i class="fas fa-hourglass-half text-secondary flex-shrink-0 mt-1"></i>
            <div>
                Detailed FEMA flood zone analysis is being integrated into the Location DNA pipeline.
                Once available, this section will show the flood zone designation, Special Flood Hazard Area
                status, and flood insurance guidance for this property.
            </div>
        </div>
    </div>
</div>
