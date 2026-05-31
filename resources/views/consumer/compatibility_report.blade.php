<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compatibility Insight</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { background: #f8f9fa; font-family: sans-serif; }
        .consumer-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .disclaimer-banner {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 5px solid #e6a817;
            border-radius: 4px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: .9rem;
            color: #5a4a00;
        }
        .disclaimer-banner strong { display: block; margin-bottom: .35rem; font-size: .95rem; }
        .card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: #f1f3f5;
            border-bottom: 1px solid #dee2e6;
            padding: .65rem 1rem;
            font-weight: 600;
            font-size: .9rem;
        }
        .card-body { padding: 1rem; }
        .summary-sentence { font-size: .95rem; line-height: 1.6; color: #333; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th {
            background: #f1f3f5;
            border: 1px solid #dee2e6;
            padding: .5rem .75rem;
            text-align: left;
            font-weight: 600;
        }
        td { border: 1px solid #dee2e6; padding: .5rem .75rem; vertical-align: top; }
        .badge {
            display: inline-block;
            padding: .2em .55em;
            border-radius: 3px;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .badge-full-alignment         { background: #d4edda; color: #155724; }
        .badge-partial-alignment      { background: #d1ecf1; color: #0c5460; }
        .badge-adjacent-compatibility { background: #fff3cd; color: #856404; }
        .badge-neutral-compatibility  { background: #e2e3e5; color: #383d41; }
        .badge-incompatible-alignment { background: #f8d7da; color: #721c24; }
        .badge-insufficient-data      { background: #e2e3e5; color: #6c757d; }
        .text-muted { color: #6c757d; }
        .empty-notice { color: #6c757d; font-style: italic; font-size: .88rem; }
    </style>
</head>
<body>
<div class="consumer-container">

    {{-- ============================================================
         DISCLAIMER BANNER (required, always shown)
         ============================================================ --}}
    <div class="disclaimer-banner" role="note">
        <strong>Compatibility Insight</strong>
        These insights are based only on submitted platform responses. They are informational only
        and do not rank, recommend, endorse, approve, or disqualify any agent.
        You are responsible for your own hiring decision.
    </div>

    {{-- ============================================================
         SUMMARY SENTENCE
         ============================================================ --}}
    <div class="card">
        <div class="card-header">Overall Summary</div>
        <div class="card-body">
            @if($summarySentence)
                <p class="summary-sentence">{{ $summarySentence }}</p>
            @else
                <p class="empty-notice">No summary available for this report.</p>
            @endif
        </div>
    </div>

    {{-- ============================================================
         PER-DIMENSION TABLE
         ============================================================ --}}
    <div class="card">
        <div class="card-header">Compatibility Insights by Dimension</div>
        <div class="card-body" style="padding:0;">
            @if(empty($dimensions))
                <div style="padding:1rem;" class="empty-notice">No dimension insights available for this report.</div>
            @else
            @php
                $badgeMap = [
                    'full_alignment'         => 'badge-full-alignment',
                    'partial_alignment'      => 'badge-partial-alignment',
                    'adjacent_compatibility' => 'badge-adjacent-compatibility',
                    'neutral_compatibility'  => 'badge-neutral-compatibility',
                    'incompatible_alignment' => 'badge-incompatible-alignment',
                    'insufficient_data'      => 'badge-insufficient-data',
                ];
                $labelMap = [
                    'full_alignment'         => 'Full Alignment',
                    'partial_alignment'      => 'Partial Alignment',
                    'adjacent_compatibility' => 'Adjacent Compatibility',
                    'neutral_compatibility'  => 'Neutral',
                    'incompatible_alignment' => 'Incompatible',
                    'insufficient_data'      => 'Insufficient Data',
                ];
            @endphp
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:22%;">Dimension</th>
                            <th style="width:22%;">Alignment</th>
                            <th>Insight</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dimensions as $row)
                        @php
                            $cat        = $row['alignment_category'] ?? null;
                            $badgeCls   = $badgeMap[$cat] ?? 'badge-insufficient-data';
                            $badgeLabel = $labelMap[$cat] ?? ($cat ?? '—');
                        @endphp
                        <tr>
                            <td>
                                <span style="font-size:.8rem;color:#555;">{{ $row['label'] ?? '—' }}</span>
                            </td>
                            <td>
                                @if($cat)
                                    <span class="badge {{ $badgeCls }}">{{ $badgeLabel }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td style="line-height:1.55;">
                                @if(!empty($row['sentence']))
                                    {{ $row['sentence'] }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>
</body>
</html>
