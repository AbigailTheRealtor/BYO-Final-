{{--
    Match Readiness Badge
    ─────────────────────
    Variables expected:
      $readiness  array   Result from MatchReadinessService::evaluate()
      $hasBid     bool    Whether the agent has placed a bid on this listing
--}}
@if($hasBid ?? false)
@php
    $mrState = $readiness['state'] ?? 'not_ready';
    if ($mrState === 'full_match_ready') {
        $mrStyle   = 'background:#155724;color:#fff;';
        $mrIcon    = 'fa-solid fa-star';
        $mrLabel   = 'Full Match Ready';
        $mrTooltip = 'This bid has all fields required for a detailed Full Match comparison. Clients can evaluate your full offer side-by-side with other agents.';
    } elseif ($mrState === 'quick_match_ready') {
        $mrStyle   = 'background:#0a3d62;color:#fff;';
        $mrIcon    = 'fa-solid fa-bolt';
        $mrLabel   = 'Quick Match Ready';
        $mrTooltip = 'This bid has the core fields needed for Quick Match — clients can rank your offer against others quickly. Fill in remaining compensation fields to reach Full Match.';
    } else {
        $mrStyle   = 'background:#6c757d;color:#fff;';
        $mrIcon    = 'fa-solid fa-circle-exclamation';
        $mrLabel   = 'Not Ready';
        $mrTooltip = 'This bid is missing key compensation fields required for matching. Complete the broker compensation section to become Quick Match Ready.';
    }
@endphp
<span class="badge"
      style="{{ $mrStyle }}padding:6px 10px;border-radius:4px;cursor:default;"
      data-bs-toggle="tooltip"
      data-bs-placement="top"
      title="{{ $mrTooltip }}">
    <i class="{{ $mrIcon }} me-1"></i>{{ $mrLabel }}
</span>
@endif
