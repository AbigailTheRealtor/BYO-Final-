{{-- Bootstrap modal for declining a showing request --}}
<div class="modal fade" id="declineModal-{{ $showing->id }}" tabindex="-1" aria-labelledby="declineModalLabel-{{ $showing->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('showings.decline', $showing) }}">
                @csrf
                @method('PATCH')

                <div class="modal-header">
                    <h5 class="modal-title" id="declineModalLabel-{{ $showing->id }}">Decline Showing Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="owner_message" class="form-control" rows="3"
                                  placeholder="Let the requester know why this slot doesn't work…">{{ old('owner_message') }}</textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-xmark me-1"></i>Decline
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
