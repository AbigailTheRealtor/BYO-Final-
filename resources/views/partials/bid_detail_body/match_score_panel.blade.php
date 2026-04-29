{{--
    Shared Match Score Panel — single-score layout.
    Used by all four bid detail roles (Buyer, Seller, Landlord, Tenant).

    Required $ms_* variables (set by each caller's @php block):
        $ms_has_baseline          bool   — whether there is any baseline data to compare against
        $ms_overall_pct           int    — overall match %
        $ms_overall_color         string — hex color for the overall badge
        $ms_services_pct          int    — services match %
        $ms_services_color        string — hex color for services
        $ms_services_total        int    — total baseline services
        $ms_services_matched      int    — matched services count
        $ms_services_extra_count  int    — extra services added by agent
        $ms_services_missing_count int   — services missing from baseline
        $ms_terms_pct             int    — terms match %
        $ms_terms_color           string — hex color for terms
        $ms_terms_total           int    — total baseline terms
        $ms_terms_matched         int    — matched terms count
        $ms_terms_changed_count   int    — terms changed from baseline
        $ms_terms_added_count     int    — terms added beyond baseline
        $ms_baseline_label        string — e.g. "Your Listing Terms"
--}}
@if (!empty($ms_has_baseline))
<div class="match-score-panel mb-4 p-3"
     style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0" style="color: #1a3a5c; font-weight: 600;">
            <i class="fa-solid fa-chart-pie me-2"></i>Match Score
        </h6>
        <span class="badge"
              style="background: {{ $ms_overall_color }}; font-size: 1.1rem; padding: 8px 16px; color: #fff;">
            {{ $ms_overall_pct }}% Match
        </span>
    </div>
    <p class="small text-muted mb-3">
        <i class="fa-solid fa-circle-info me-1"></i>Match Score compares this bid to the original listing request. Added services or terms are shown for transparency but do not increase the score.<br>
        Comparing to: <strong>{{ $ms_baseline_label ?? 'Your Listing Terms' }}</strong>
    </p>
    <div class="row g-3">
        {{-- Services column --}}
        <div class="col-md-6">
            <div class="p-2 bg-white rounded"
                 style="border-left: 4px solid {{ $ms_services_color }};">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">Services Match</span>
                    <span class="badge" style="background: {{ $ms_services_color }}; color: #fff;">{{ $ms_services_pct }}%</span>
                </div>
                <div class="small text-muted mt-1">
                    {{ ($ms_services_total ?? 0) > 0
                        ? 'Matched Original: '.($ms_services_matched ?? 0).'/'.($ms_services_total ?? 0)
                        : 'No services requested' }}
                </div>
                @if (($ms_services_extra_count ?? 0) > 0)
                <div class="small mt-1 d-flex align-items-center flex-wrap" style="gap: 3px 5px;"
                     title="Extra services were included by the Agent beyond the original request. These do not increase the match score.">
                    <span>&#11088;</span>
                    <span style="font-weight: 500; color: #856404;">
                        Extra Value Added: {{ $ms_services_extra_count }} {{ $ms_services_extra_count === 1 ? 'Service' : 'Services' }}
                    </span>
                </div>
                @endif
                @if (($ms_services_missing_count ?? 0) > 0)
                <div class="small mt-1" style="color: #dc3545;">
                    Missing from Original: {{ $ms_services_missing_count }}
                </div>
                @endif
            </div>
        </div>
        {{-- Terms column --}}
        <div class="col-md-6">
            <div class="p-2 bg-white rounded"
                 style="border-left: 4px solid {{ $ms_terms_color }};">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">Terms Match</span>
                    <span class="badge" style="background: {{ $ms_terms_color }}; color: #fff;">{{ $ms_terms_pct }}%</span>
                </div>
                <div class="small text-muted mt-1">
                    {{ ($ms_terms_total ?? 0) > 0
                        ? 'Matched Original: '.($ms_terms_matched ?? 0).'/'.($ms_terms_total ?? 0)
                        : 'No terms provided' }}
                </div>
                @if (($ms_terms_changed_count ?? 0) > 0)
                <div class="small mt-1" style="color: #dc3545;">
                    Changed from Baseline: {{ $ms_terms_changed_count }}
                </div>
                @endif
                @if (($ms_terms_added_count ?? 0) > 0)
                <div class="small mt-1" style="color: #6c757d;">
                    Added by Agent: {{ $ms_terms_added_count }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@else
<div class="text-muted text-center py-3 mb-4"
     style="font-size: 0.92rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; padding: 16px;">
    <i class="fa-solid fa-circle-info me-1"></i>No match data available for this listing.
</div>
@endif
