<h4>Experience & Service Area</h4>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📍 Share your experience and the areas you serve. This information helps clients evaluate your track record and local market knowledge.</strong>
        </div>
    </div>
</div>

<!-- Year Licensed -->
<div class="form-group required-field">
    <label class="fw-bold">Year Agent Got Licensed:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the year the Agent was first licensed. This gives clients a general idea of your experience level in the industry.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="number" wire:model="year_licensed" placeholder="Enter year licensed (e.g., 2015)" id="year_licensed"
            class="form-control has-icon @error('year_licensed') is-invalid @enderror" min="1900" max="{{ date('Y') }}"
            data-icon="fa-solid fa-calendar">
    </div>
    @error('year_licensed')<span class="text-danger small">{{ $message }}</span>@enderror
    <span class="error mt-2" id="year_licensed_error"></span>
</div>

<!-- Availability -->
<hr class="my-4">
<h5 class="fw-bold mb-3">Availability</h5>

<div class="form-group">
    <label class="fw-bold">Average Response Time:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="How quickly do you typically respond to client inquiries? This helps clients know what to expect when working with you.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="avg_response_time" id="avg_response_time"
            class="form-control has-icon" data-icon="fa-solid fa-clock"
            placeholder="e.g. Within 1 hour">
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">Current Availability Status:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate your current capacity to take on new clients. This helps clients understand if you're ready to engage immediately.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="availability_status" id="availability_status"
            class="form-control has-icon" data-icon="fa-solid fa-user-check">
            <option value="">Select availability</option>
            <option value="Actively Taking New Clients">Actively Taking New Clients</option>
            <option value="Limited Availability">Limited Availability</option>
            <option value="By Referral Only">By Referral Only</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Available in the Evenings?</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Let clients know if you are reachable or available for showings and calls during evening hours.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <select wire:model="evenings_available" id="evenings_available"
                    class="form-control has-icon" data-icon="fa-solid fa-moon">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Available on Weekends?</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Let clients know if you are available for showings, calls, or meetings on Saturdays and Sundays.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <select wire:model="weekends_available" id="weekends_available"
                    class="form-control has-icon" data-icon="fa-solid fa-calendar-week">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Experience & Track Record -->
<hr class="my-4">
<h5 class="fw-bold mb-3">Experience &amp; Track Record</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Years of Experience:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the total number of years you have been actively working as a licensed real estate agent.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="years_experience" id="years_experience"
                    class="form-control has-icon" placeholder="e.g., 8"
                    min="0" data-icon="fa-solid fa-briefcase">
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Transactions in Last 12 Months:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the approximate number of transactions you have completed in the past 12 months. This helps clients gauge your recent market activity.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="transactions_last_12_months" id="transactions_last_12_months"
                    class="form-control has-icon" placeholder="e.g., 15"
                    min="0" data-icon="fa-solid fa-handshake">
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Full-Time Agent?</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether real estate is your full-time profession. Full-time agents are generally more accessible and dedicated to their clients.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <select wire:model="is_full_time" id="is_full_time"
                    class="form-control has-icon" data-icon="fa-solid fa-star">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Primary Areas Served:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Briefly describe the primary geographic areas or markets where you have the most experience and transaction history.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="primary_areas_served" id="primary_areas_served"
                    class="form-control has-icon" placeholder="e.g., Downtown Miami, Coral Gables, Brickell"
                    data-icon="fa-solid fa-map-marker-alt">
            </div>
        </div>
    </div>
</div>

<!-- Service Areas -->
<hr class="my-4">
<h5 class="fw-bold mb-3">Service Areas</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Cities Served:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="List the cities where you actively provide real estate services. Separate multiple cities with commas.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="cities_served" id="cities_served"
                    class="form-control has-icon" placeholder="e.g., Miami, Fort Lauderdale, Hollywood"
                    data-icon="fa-solid fa-city">
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="fw-bold">Counties Served:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="List the counties where you actively work. Separate multiple counties with commas.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="counties_served" id="counties_served"
                    class="form-control has-icon" placeholder="e.g., Miami-Dade, Broward, Palm Beach"
                    data-icon="fa-solid fa-map">
            </div>
        </div>
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">Neighborhoods Served:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="List specific neighborhoods or subdivisions where you have strong market knowledge. Separate multiple entries with commas.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="neighborhoods_served" id="neighborhoods_served"
            class="form-control has-icon" placeholder="e.g., Wynwood, Design District, South Beach"
            data-icon="fa-solid fa-location-dot">
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">Additional Notes on Service Areas:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Use this space to provide any additional context about your geographic coverage, travel radius, or specialty markets.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <textarea wire:model="areas_notes" id="areas_notes"
            class="form-control has-icon" rows="3"
            placeholder="Any additional details about your service area coverage or geographic specialization"></textarea>
    </div>
</div>
