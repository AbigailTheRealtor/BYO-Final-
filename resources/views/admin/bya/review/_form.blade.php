<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
        <i class="fa-solid fa-pen-to-square"></i>
        <h6 class="mb-0">Submit Review Entry</h6>
        <span class="badge badge-light text-primary ms-auto" style="font-size:.7rem;">Admin Only — Append-Only</span>
    </div>
    <div class="card-body" style="font-size:.85rem;">

        @if(session('review_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('review_success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.bya.review.store', $record->id) }}">
            @csrf

            <div class="form-group mb-3">
                <label for="bya_review_status" class="font-weight-bold">Review Status <span class="text-danger">*</span></label>
                <select name="status" id="bya_review_status" class="form-control form-control-sm @error('status') is-invalid @enderror" required>
                    <option value="">— Select a status —</option>
                    @foreach(\App\Models\ByaReviewLog::STATUSES as $value => $label)
                        <option value="{{ $value }}" {{ old('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4">
                <label for="bya_review_notes" class="font-weight-bold">Notes</label>
                <textarea name="notes" id="bya_review_notes"
                    class="form-control form-control-sm @error('notes') is-invalid @enderror"
                    rows="4"
                    maxlength="5000"
                    placeholder="Optional free-text notes for this review action…">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <fieldset class="mb-3">
                <legend class="font-weight-bold" style="font-size:.9rem;">Fair Housing Checklist</legend>
                <p class="text-muted mb-3" style="font-size:.8rem;">
                    Check each item to confirm it has been reviewed and found clear. Leave unchecked if concerns remain or review is pending.
                </p>
                <div class="pl-2">
                    @foreach(\App\Models\ByaReviewLog::CHECKLIST_ITEMS as $key => $label)
                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="fair_housing_checklist[{{ $key }}]"
                            id="chk_{{ $key }}"
                            value="1"
                            {{ old("fair_housing_checklist.{$key}") ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="chk_{{ $key }}">
                            <strong>{{ $label }}</strong>
                        </label>
                    </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="d-flex align-items-center gap-2 mt-3">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Review Entry
                </button>
                <small class="text-muted">This entry will be permanently appended to the review history and cannot be edited or deleted.</small>
            </div>
        </form>
    </div>
</div>
