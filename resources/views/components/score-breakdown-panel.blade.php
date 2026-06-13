{{--
    Score Breakdown Panel — P5 Full Match Enhancements
    ────────────────────────────────────────────────────
    Renders an expandable accordion panel explaining why a compatibility score
    was produced. Classifies each evaluated field as:

      strong  — both sides match
      weak    — both sides provided data but it conflicts
      partial — services field with some (not all) matches
      missing — one or both sides did not provide data (NOT a poor fit)

    Variables expected:
      $breakdown  array   Result from ScoreBreakdownService::breakdown()
--}}
@php
    $sbScoreData   = $breakdown['score_data']      ?? [];
    $sbFields      = $breakdown['field_breakdown']  ?? [];
    $sbSummary     = $breakdown['summary']          ?? [];
    $sbFieldSet    = $breakdown['active_field_set'] ?? 'none';
    $sbScoreType   = $sbScoreData['score_type']     ?? 'none';
    $sbScore       = $sbScoreData['score']          ?? null;
    $sbState       = $sbScoreData['readiness_state'] ?? 'not_ready';

    $sbShowPanel   = ($sbScoreType !== 'none' && !empty($sbFields));
    $sbPanelId     = 'scoreBreakdownPanel_' . substr(md5(json_encode($sbSummary)), 0, 8);

    $sbResultConfig = [
        'strong'  => ['icon' => 'fa-circle-check',      'color' => '#28a745', 'label' => 'Strong Match',   'bg' => '#d4edda', 'text' => '#155724'],
        'weak'    => ['icon' => 'fa-triangle-exclamation','color' => '#fd7e14','label' => 'Weak Match',     'bg' => '#fff3cd', 'text' => '#856404'],
        'partial' => ['icon' => 'fa-circle-half-stroke', 'color' => '#0d6efd', 'label' => 'Partial Match', 'bg' => '#cce5ff', 'text' => '#004085'],
        'missing' => ['icon' => 'fa-circle-minus',       'color' => '#6c757d', 'label' => 'Not Provided',  'bg' => '#f8f9fa', 'text' => '#6c757d'],
    ];

    $sbSetLabel = match($sbFieldSet) {
        'full_match'  => 'Full Match',
        'quick_match' => 'Quick Match',
        default       => '',
    };
@endphp

@if($sbShowPanel)
<div class="score-breakdown-wrapper mt-4 mb-3">
    {{-- ── Accordion trigger ── --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
        <button class="btn btn-sm d-flex align-items-center gap-2"
                style="background:#f0fafa;border:1px solid #049399;color:#049399;font-weight:600;padding:6px 14px;border-radius:6px;"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $sbPanelId }}"
                aria-expanded="false"
                aria-controls="{{ $sbPanelId }}">
            <i class="fa-solid fa-magnifying-glass-chart"></i>
            <span>Score Breakdown</span>
            @if($sbScore !== null)
            <span class="badge text-white ms-1"
                  style="background:#049399;font-size:0.78rem;">{{ $sbScore }}%</span>
            @endif
            <i class="fa-solid fa-chevron-down ms-1" style="font-size:0.75rem;"></i>
        </button>

        {{-- Summary badges --}}
        <div class="d-flex flex-wrap gap-1 align-items-center">
            @foreach(['strong','partial','weak','missing'] as $sbCat)
            @if(($sbSummary[$sbCat] ?? 0) > 0)
            @php $sbCfg = $sbResultConfig[$sbCat]; @endphp
            <span class="badge"
                  style="background:{{ $sbCfg['bg'] }};color:{{ $sbCfg['text'] }};border:1px solid {{ $sbCfg['color'] }};font-size:0.75rem;padding:4px 8px;">
                <i class="fa-solid {{ $sbCfg['icon'] }} me-1"></i>
                {{ $sbSummary[$sbCat] }} {{ $sbCfg['label'] }}
            </span>
            @endif
            @endforeach
        </div>
    </div>

    {{-- ── Collapsible content ── --}}
    <div class="collapse" id="{{ $sbPanelId }}">
        <div class="card card-body p-0 mt-2"
             style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">

            {{-- Header row --}}
            <div class="d-flex align-items-center justify-content-between px-3 py-2"
                 style="background:#f0fafa;border-bottom:1px solid #dee2e6;">
                <span style="font-weight:600;color:#049399;font-size:0.9rem;">
                    <i class="fa-solid fa-list-check me-1"></i>
                    Field-by-Field Breakdown
                    @if($sbSetLabel)
                    <span class="badge ms-1"
                          style="background:#049399;color:#fff;font-size:0.7rem;vertical-align:middle;">
                        {{ $sbSetLabel }}
                    </span>
                    @endif
                </span>
                <span style="font-size:0.8rem;color:#6c757d;">
                    {{ $sbSummary['total'] ?? 0 }} fields evaluated
                </span>
            </div>

            {{-- Missing fields notice --}}
            @if(($sbSummary['missing'] ?? 0) > 0)
            <div class="px-3 py-2 d-flex align-items-start gap-2"
                 style="background:#f8f9fa;border-bottom:1px solid #dee2e6;font-size:0.82rem;color:#6c757d;">
                <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
                <span>
                    <strong>Note:</strong>
                    Fields marked "Not Provided" mean the data was not submitted on one or both sides.
                    This does <em>not</em> indicate a poor fit — missing fields are excluded from scoring.
                </span>
            </div>
            @endif

            {{-- Field rows --}}
            <div class="list-group list-group-flush">
                @foreach($sbFields as $sbRow)
                @php
                    $sbCfg      = $sbResultConfig[$sbRow['result']] ?? $sbResultConfig['missing'];
                    $sbIsArray  = is_array($sbRow['listing_value']) || is_array($sbRow['bid_value']);
                @endphp
                <div class="list-group-item px-3 py-2"
                     style="border-left:3px solid {{ $sbCfg['color'] }};">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">

                        {{-- Left: label + indicator --}}
                        <div class="flex-grow-1" style="min-width:160px;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fa-solid {{ $sbCfg['icon'] }}"
                                   style="color:{{ $sbCfg['color'] }};font-size:0.85rem;flex-shrink:0;"></i>
                                <span style="font-weight:600;font-size:0.88rem;color:#34465c;">
                                    {{ $sbRow['label'] }}
                                </span>
                                <span class="badge"
                                      style="background:{{ $sbCfg['bg'] }};color:{{ $sbCfg['text'] }};border:1px solid {{ $sbCfg['color'] }};font-size:0.72rem;">
                                    {{ $sbCfg['label'] }}
                                </span>
                            </div>
                            @if($sbRow['note'])
                            <div style="font-size:0.8rem;color:#6c757d;margin-left:1.4rem;">
                                {{ $sbRow['note'] }}
                            </div>
                            @endif
                        </div>

                        {{-- Right: listing vs bid values (skip for array fields — note covers it) --}}
                        @if(!$sbIsArray && $sbRow['result'] !== 'missing')
                        <div class="d-flex gap-3 flex-wrap"
                             style="font-size:0.82rem;min-width:200px;max-width:420px;">
                            <div>
                                <span style="font-weight:600;color:#6c757d;font-size:0.75rem;display:block;">Listing</span>
                                <span style="color:#34465c;">{{ $sbRow['listing_value'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span style="font-weight:600;color:#6c757d;font-size:0.75rem;display:block;">Bid</span>
                                <span style="color:#34465c;">{{ $sbRow['bid_value'] ?? '—' }}</span>
                            </div>
                        </div>
                        @elseif($sbIsArray && !empty($sbRow['listing_value']) && !empty($sbRow['bid_value']))
                        <div style="font-size:0.82rem;min-width:200px;max-width:420px;">
                            <span style="font-weight:600;color:#6c757d;font-size:0.75rem;display:block;">Listing requested</span>
                            <ul class="mb-0 ps-3" style="color:#34465c;">
                                @foreach((array)$sbRow['listing_value'] as $sbSvc)
                                @php
                                    $sbSvcMatched = in_array($sbSvc, (array)$sbRow['bid_value'], true);
                                @endphp
                                <li style="color:{{ $sbSvcMatched ? '#28a745' : '#fd7e14' }};">
                                    <i class="fa-solid {{ $sbSvcMatched ? 'fa-check' : 'fa-xmark' }} me-1"
                                       style="font-size:0.75rem;"></i>{{ $sbSvc }}
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                    </div>
                </div>
                @endforeach
            </div>

        </div>
    </div>
</div>
@endif
