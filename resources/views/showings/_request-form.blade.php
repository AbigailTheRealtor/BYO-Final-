{{--
    Showing request form partial — self-contained, renders inside a Bootstrap modal.

    Required variable: $auctionId (the OfferAuction ID for the listing)
    Optional: $modalId (used to scope the success alert; defaults to 'showingRequest')
--}}
@php
    $modalId = $modalId ?? 'showingRequest';
@endphp

<form method="POST" action="{{ route('showings.store') }}">
    @csrf
    <input type="hidden" name="offer_auction_id" value="{{ $auctionId }}">

    <div class="modal-body p-4">
        @if(session('success') && str_contains((string) session('success'), 'showing request'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->has('offer_auction_id') || $errors->has('requested_date') || $errors->has('requested_start_time') || $errors->has('requested_end_time'))
            <div class="alert alert-danger mb-3" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach(['offer_auction_id','requested_date','requested_start_time','requested_end_time','requester_message'] as $showingField)
                        @foreach($errors->get($showingField) as $errMsg)
                            <li>{{ $errMsg }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem;">
                    Preferred Date <span class="text-danger">*</span>
                </label>
                <input type="date"
                       class="form-control @error('requested_date') is-invalid @enderror"
                       name="requested_date"
                       value="{{ old('requested_date') }}"
                       min="{{ date('Y-m-d') }}"
                       required>
                @error('requested_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem;">
                    Start Time <span class="text-danger">*</span>
                </label>
                <input type="time"
                       class="form-control @error('requested_start_time') is-invalid @enderror"
                       name="requested_start_time"
                       value="{{ old('requested_start_time') }}"
                       required>
                @error('requested_start_time')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem;">
                    End Time <span class="text-danger">*</span>
                </label>
                <input type="time"
                       class="form-control @error('requested_end_time') is-invalid @enderror"
                       name="requested_end_time"
                       value="{{ old('requested_end_time') }}"
                       required>
                @error('requested_end_time')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" style="font-size:.85rem;">
                    Message <span class="text-muted fw-normal">(optional)</span>
                </label>
                <textarea class="form-control @error('requester_message') is-invalid @enderror"
                          name="requester_message"
                          rows="3"
                          maxlength="1000"
                          placeholder="Any questions or notes for the owner…">{{ old('requester_message') }}</textarea>
                @error('requester_message')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text" style="font-size:.75rem;">Max 1,000 characters</div>
            </div>
        </div>
    </div>

    <div class="modal-footer border-0 pb-4">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-calendar-check me-1"></i>Send Request
        </button>
    </div>
</form>
