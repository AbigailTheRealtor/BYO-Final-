<div class="card border-0 rounded-3 overflow-hidden" style="border-left:3px solid #049399 !important;box-shadow:0 1px 4px rgba(0,0,0,.06);">
    <div class="card-body py-3 px-3">

        {{-- Top row: agent info + status badge --}}
        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div>
                <div class="fw-bold mb-1" style="font-size:.95rem;color:#1a2333;">
                    {{ $agent->user_name ?? 'Agent' }}
                </div>
                @if($agent && $agent->brokerage)
                    <div class="text-muted small mb-1" style="font-size:.8rem;">{{ $agent->brokerage }}</div>
                @endif
                <div class="d-flex flex-wrap gap-3" style="font-size:.78rem;color:#666;">
                    @if($agent && $agent->email)
                        <span><i class="fa fa-envelope me-1 opacity-50"></i>{{ $agent->email }}</span>
                    @endif
                    @if($agent && $agent->phone)
                        <span><i class="fa fa-phone me-1 opacity-50"></i>{{ $agent->phone }}</span>
                    @endif
                </div>
            </div>
            <span class="badge rounded-pill px-3 py-2"
                  style="{{ $statusStyle }}font-size:.68rem;max-width:160px;white-space:normal;text-align:center;line-height:1.3;">
                {{ $sigStatus }}
            </span>
        </div>

        {{-- Bottom row: listing info + action buttons --}}
        <div class="mt-2 pt-2 border-top d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div style="font-size:.78rem;color:#888;">
                <span class="fw-semibold" style="color:#049399;">{{ $roleLabel }}</span>
                &middot;
                {{ $listingAddr }}
                @if($listingCode && $listingCode !== $listingAddr)
                    <span class="ms-1" style="font-family:monospace;font-size:.7rem;color:#aaa;">({{ $listingCode }})</span>
                @endif
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('accepted-bid-summary.view', $summary->id) }}"
                   class="btn btn-sm"
                   style="background:#049399;color:#fff;font-size:.75rem;white-space:nowrap;">
                    View Summary
                </a>
                <a href="{{ route('messages') }}"
                   class="btn btn-sm btn-outline-secondary"
                   style="font-size:.75rem;white-space:nowrap;">
                    Message Agent
                </a>
                @if($summary->summary_pdf_path)
                    <a href="{{ route('accepted-bid-summary.download-pdf', $summary->id) }}"
                       class="btn btn-sm btn-outline-secondary"
                       style="font-size:.75rem;white-space:nowrap;"
                       target="_blank">
                        Download PDF
                    </a>
                @endif
            </div>
        </div>

    </div>
</div>
