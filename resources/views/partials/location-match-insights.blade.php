{{--
    Location Match Insights Partial
    ================================
    Reusable read-only display for LocationMatchIntegrationService output.

    Props:
      $insights  — string[]  Human-readable insight strings from
                             LocationMatchIntegrationService::build()['insights'].
                             When empty or absent, renders the no-data notice.

    Governance:
      - No field-awareness logic lives here; all matching/insight logic lives in
        the service layer (LocationMatchEngine + LocationMatchInsightService).
      - This partial only renders — it never computes.
--}}
@php
    $locationInsightList = isset($insights) && is_array($insights) ? array_values(array_filter($insights)) : [];
@endphp

<div class="mt-4">
    <div class="p-3 rounded mb-3" style="background: #f0f4ff; border: 1px solid #c7d4f5;">
        <h6 class="mb-0 fw-bold" style="color: #1a3a8f;">
            <i class="fa-solid fa-map-location-dot me-2"></i>Location Match
        </h6>
        <div class="small text-muted mt-1">How well this listing's location aligns with saved preferences.</div>
    </div>

    @if (empty($locationInsightList))
        <div class="p-3 rounded" style="background: #fafafa; border: 1px solid #e9ecef;">
            <span class="small text-muted">
                <i class="fa-solid fa-circle-info me-1"></i>No location match data available.
            </span>
        </div>
    @else
        <div class="p-3 rounded" style="background: #fafafa; border: 1px solid #e9ecef;">
            <ul class="mb-0 ps-3" style="list-style: disc;">
                @foreach ($locationInsightList as $insight)
                    <li class="small mb-1">{{ $insight }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
