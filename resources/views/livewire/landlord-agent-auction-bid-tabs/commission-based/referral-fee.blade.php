<div class="form-group mb-4">
    <label class="fw-semibold" for="referral_fee_percent_landlord_counter">Referral Fee (%)</label>
    <input type="number"
           class="form-control mt-1"
           id="referral_fee_percent_landlord_counter"
           wire:model.live.debounce.300ms="referral_fee_percent"
           min="0" max="100" step="0.01"
           placeholder="Enter referral fee percentage (e.g., 25)">
    <div class="form-text text-muted mt-1" style="font-size:.85rem;">
        Enter the referral fee percentage offered for this Agent-to-Agent referral arrangement.
    </div>
    @error('referral_fee_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>
