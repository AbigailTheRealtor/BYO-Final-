{{-- Bootstrap modal for approving a showing request --}}
<div class="modal fade" id="approveModal-{{ $showing->id }}" tabindex="-1" aria-labelledby="approveModalLabel-{{ $showing->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('showings.approve', $showing) }}">
                @csrf
                @method('PATCH')

                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel-{{ $showing->id }}">Approve Showing Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Requested: <strong>{{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}</strong>
                        from <strong>{{ \Carbon\Carbon::parse($showing->requested_start_time)->format('g:i A') }}</strong>
                        to <strong>{{ \Carbon\Carbon::parse($showing->requested_end_time)->format('g:i A') }}</strong>
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmed Date <span class="text-muted fw-normal">(defaults to requested)</span></label>
                        <input type="date" name="approved_date" class="form-control"
                               value="{{ old('approved_date', $showing->requested_date?->format('Y-m-d')) }}">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="approved_start_time" class="form-control"
                                   value="{{ old('approved_start_time', $showing->requested_start_time) }}">
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="approved_end_time" class="form-control"
                                   value="{{ old('approved_end_time', $showing->requested_end_time) }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message to Requester <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="owner_message" class="form-control" rows="3"
                                  placeholder="Any instructions or notes for the visit…">{{ old('owner_message') }}</textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i>Approve Showing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
