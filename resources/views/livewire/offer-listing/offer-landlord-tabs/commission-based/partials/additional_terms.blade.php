<!-- Additional Terms -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Additional Terms:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Include any additional or custom compensation terms, conditions, or agreements not covered above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <textarea wire:model.lazy="additional_details_broker" class="form-control mt-2" rows="3"
        x-on:input="$el.nextElementSibling.querySelector('small').textContent = $el.value.length + ' characters'"
        placeholder="Enter additional broker compensation terms (e.g., custom arrangements, referral notes)"></textarea>
    <div class="text-end mt-1"><small class="text-muted">{{ strlen($additional_details_broker ?? '') }} characters</small></div>
</div>
