
@if ($property_type === 'Commercial Property')
    <!-- Expansion Commission for Lease Amendment (Commercial only) -->
    <div class="form-group mb-4">
        <label class="fw-bold d-flex align-items-center">
            Expansion Commission for Lease Amendment:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the percentage of the original commission to be applied if the leased space expands under a lease amendment. This is typically calculated as a portion of the initial commission structure.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="mt-2">
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                <input type="number" wire:model.lazy="expansion_commission_percentage" class="form-control"
                    placeholder="Enter percentage of original commission for expansion (e.g., 50)">
                <span class="input-group-text">%</span>
            </div>
            @error('expansion_commission_percentage')
                <span class="text-danger small">{{ $message }}</span>
            @enderror
        </div>
    </div>
@endif