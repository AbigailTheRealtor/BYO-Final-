{{--
  Location DNA Intelligence Summary Component
  ============================================
  Usage: <x-location-dna-intelligence-summary :summaryLines="$locationIntelligenceSummary['summary_lines'] ?? []" />

  Props:
    $summaryLines – array of plain-text summary lines produced by a backend intelligence
                    phase. Renders nothing when the array is empty or the prop is absent.
                    No AI labels, scores, or percentages are shown.

  Output:
    A Bootstrap card with a "Location Intelligence" header and a bullet list of escaped
    lines when $summaryLines is non-empty; nothing at all otherwise.

  XSS:
    Lines are rendered with {{ $line }} — Blade auto-escaping is active.
--}}
@php $summaryLines = $summaryLines ?? []; @endphp

@if(!empty($summaryLines))
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fa-solid fa-location-dot me-1"></i> Location Intelligence
        </h5>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            @foreach($summaryLines as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif
