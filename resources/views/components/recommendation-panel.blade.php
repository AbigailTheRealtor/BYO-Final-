{{--
    Recommendation Panel — P6 Advanced Matching & Recommendations
    ──────────────────────────────────────────────────────────────
    Renders a fit indicator for consumers based on a RecommendationService
    consumerFitRecommendation() result. Appears alongside the Score Breakdown
    panel on bid card views.

    Guardrails enforced here:
      - No superlative labels ("Best agent", "Top agent", etc.)
      - Panel only renders when recommendation_type is not 'not_scored'
      - Reasons are sourced entirely from field-level ScoreBreakdownService data
      - Default sort order is never altered by this component

    Variables expected:
      $recommendation  array   Result from RecommendationService::consumerFitRecommendation()
--}}
@php
    $rcType    = $recommendation['recommendation_type'] ?? 'not_scored';
    $rcScore   = $recommendation['score']   ?? null;
    $rcLabel   = $recommendation['label']   ?? null;
    $rcReasons = $recommendation['reasons'] ?? [];

    $rcShowPanel = ($rcType !== 'not_scored' && $rcLabel !== null);

    $rcTypeConfig = [
        'strong_fit'  => [
            'bg'     => '#d4edda',
            'border' => '#28a745',
            'text'   => '#155724',
            'icon'   => 'fa-circle-check',
            'badge'  => '#28a745',
        ],
        'good_fit' => [
            'bg'     => '#cce5ff',
            'border' => '#0d6efd',
            'text'   => '#004085',
            'icon'   => 'fa-circle-check',
            'badge'  => '#0d6efd',
        ],
        'partial_fit' => [
            'bg'     => '#fff3cd',
            'border' => '#fd7e14',
            'text'   => '#856404',
            'icon'   => 'fa-circle-half-stroke',
            'badge'  => '#fd7e14',
        ],
    ];

    $rcCfg     = $rcTypeConfig[$rcType] ?? $rcTypeConfig['partial_fit'];
    $rcPanelId = 'recPanel_' . substr(md5(json_encode($rcReasons)), 0, 8);
@endphp

@if($rcShowPanel)
<div class="recommendation-panel-wrapper mt-3 mb-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2"
         style="background:{{ $rcCfg['bg'] }};border:1px solid {{ $rcCfg['border'] }};border-radius:8px;padding:10px 16px;">

        {{-- Left: icon + label --}}
        <div class="d-flex align-items-center gap-2 flex-grow-1">
            <i class="fa-solid {{ $rcCfg['icon'] }}"
               style="color:{{ $rcCfg['border'] }};font-size:1rem;flex-shrink:0;"></i>
            <span style="font-weight:600;color:{{ $rcCfg['text'] }};font-size:0.9rem;">
                {{ $rcLabel }}
            </span>
            @if($rcScore !== null)
            <span class="badge text-white ms-1"
                  style="background:{{ $rcCfg['badge'] }};font-size:0.78rem;">
                {{ $rcScore }}% compatibility
            </span>
            @endif
        </div>

        {{-- Right: expand reasons if any --}}
        @if(!empty($rcReasons))
        <button class="btn btn-sm"
                style="font-size:0.8rem;color:{{ $rcCfg['text'] }};background:transparent;border:none;padding:0 4px;white-space:nowrap;"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $rcPanelId }}"
                aria-expanded="false"
                aria-controls="{{ $rcPanelId }}">
            <i class="fa-solid fa-info-circle me-1"></i>Why?
        </button>
        @endif
    </div>

    {{-- Collapsible reasons list --}}
    @if(!empty($rcReasons))
    <div class="collapse" id="{{ $rcPanelId }}">
        <div class="card card-body p-0 mt-1"
             style="border:1px solid {{ $rcCfg['border'] }};border-radius:0 0 8px 8px;border-top:none;overflow:hidden;">
            <div class="px-3 py-2"
                 style="background:{{ $rcCfg['bg'] }};font-size:0.82rem;color:{{ $rcCfg['text'] }};">
                <strong style="display:block;margin-bottom:4px;">
                    <i class="fa-solid fa-list-check me-1"></i>Based on your criteria:
                </strong>
                <ul class="mb-0 ps-3" style="line-height:1.7;">
                    @foreach($rcReasons as $rcReason)
                    <li>{{ $rcReason }}</li>
                    @endforeach
                </ul>
                <div class="mt-2" style="font-size:0.75rem;color:{{ $rcCfg['text'] }};opacity:0.75;">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Fit indicators are based on compatibility scoring — not an overall ranking.
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif
