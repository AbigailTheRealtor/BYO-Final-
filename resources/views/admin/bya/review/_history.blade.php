<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fa-solid fa-clock-rotate-left text-secondary"></i>
        <h6 class="mb-0">Review History</h6>
        @if($reviewLogs->isNotEmpty())
        <span class="badge badge-secondary ms-auto" style="font-size:.7rem;">{{ $reviewLogs->count() }} {{ $reviewLogs->count() === 1 ? 'entry' : 'entries' }}</span>
        @endif
    </div>
    <div class="card-body p-0" style="font-size:.85rem;">

        @if($reviewLogs->isEmpty())
        <div class="p-4 text-center text-muted">
            <i class="fa-solid fa-inbox fa-lg mb-2 d-block"></i>
            No review entries yet. Submit the first review above.
        </div>
        @else

        @php
            $statusBadges = [
                'pending_review'      => 'badge-secondary',
                'in_review'           => 'badge-info',
                'approved'            => 'badge-success',
                'approved_with_notes' => 'badge-primary',
                'flagged'             => 'badge-warning',
                'rejected'            => 'badge-danger',
            ];
            $checklistItems = \App\Models\ByaReviewLog::CHECKLIST_ITEMS;
            $statusLabels   = \App\Models\ByaReviewLog::STATUSES;
        @endphp

        <div class="list-group list-group-flush">
            @foreach($reviewLogs as $log)
            @php
                $badgeClass = $statusBadges[$log->status] ?? 'badge-secondary';
                $checklist  = $log->fair_housing_checklist ?? [];
                $allClear   = !empty($checklist) && !in_array(false, array_map('boolval', array_filter($checklist, fn($v) => $v !== null)), true)
                              && count(array_filter($checklist, fn($v) => $v === true)) === count($checklistItems);
            @endphp
            <div class="list-group-item">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <span class="badge {{ $badgeClass }} mr-2" style="font-size:.78rem;">{{ $statusLabels[$log->status] ?? $log->status }}</span>
                        <strong>{{ optional($log->reviewer)->first_name }} {{ optional($log->reviewer)->last_name }}</strong>
                        <span class="text-muted small ml-1">(ID: {{ $log->reviewer_user_id }})</span>
                    </div>
                    <small class="text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }} UTC</small>
                </div>

                @if($log->notes)
                <div class="mb-2 p-2 bg-light border rounded" style="font-size:.83rem;white-space:pre-wrap;">{{ $log->notes }}</div>
                @endif

                <div style="font-size:.8rem;">
                    <strong class="d-block mb-1">Fair Housing Checklist:</strong>
                    @if(empty($checklist))
                        <span class="text-muted">No checklist answers recorded.</span>
                    @else
                    <table class="table table-sm table-bordered mb-0" style="font-size:.78rem;">
                        <tbody>
                            @foreach($checklistItems as $key => $label)
                            @php $val = $checklist[$key] ?? null; @endphp
                            <tr>
                                <td style="width:60%;">{{ $label }}</td>
                                <td class="text-center" style="width:40%;">
                                    @if($val === true)
                                        <span class="badge badge-success" style="font-size:.72rem;"><i class="fa-solid fa-check mr-1"></i>Clear</span>
                                    @elseif($val === false)
                                        <span class="badge badge-danger" style="font-size:.72rem;"><i class="fa-solid fa-xmark mr-1"></i>Concern</span>
                                    @else
                                        <span class="badge badge-light text-muted border" style="font-size:.72rem;">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        @endif
    </div>
</div>
