<h3>Pre-Screening</h3>
<!-- Add your form fields here -->
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🔍 Answer a few key questions about household size, income, and any screening concerns to help
                Agents match the Tenant with appropriate properties and Landlords.
            </strong>
        </div>
    </div>
</div>
<!-- Number of Occupants -->
<div class="form-group">
    <label class="fw-bold">Number of Occupants: <span class="text-danger">*</span>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="Enter the total number of occupants.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <input type="number" wire:model="number_occupant" class="form-control has-icon" data-icon="fa-solid fa-users"
            placeholder="Enter number of occupants (e.g., 4)" required>
    </div>
    <span class="error mt-2" id="number_occupant_error"></span>
</div>

<!--2.  Total Monthly Net Household Income: -->
<div class="form-group">
    <label class="fw-bold">Estimated Monthly Net Household Income: <span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total post-tax income for all occupants.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
                <span class="input-group-text-seller">$</span>

        <input type="text" wire:model="monthly_income" class="form-control"
            placeholder="Enter estimated monthly net household income (e.g., 7000)"
            required

            data-error-id="monthly_income_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
        <span class="error mt-2" id="monthly_income_error"></span>

</div>

@if ($property_type === 'Residential Property')
    <!-- Pets Allowed -->
    <div class="form-group">
        <label class="fw-bold">Pets:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Tenant has pets. If so, enter details including the number, type, breed, weight, and whether the pet is a service animal or an emotional support animal.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="pets" id="pets" class="form-control has-icon" data-icon="fa-solid fa-paw">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
        <span class="error mt-2" id="pets_error"></span>
    </div>
@endif

@if ($property_type === 'Residential Property' && $pets === 'Yes')
    <div id="pet-details">
        <!-- Number of Pets Allowed -->
        <div class="form-group">
            <label class="fw-bold">Number of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the total number of pets the Tenant currently has (e.g., 2).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="number_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets (e.g., 2)">
            </div>
            <span class="error mt-2" id="number_of_pets_error"></span>
        </div>

        <!-- Acceptable Pet Types -->
        <div class="form-group">
            <label class="fw-bold">Pet Types:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the types of pets the Tenant has (e.g., Dog, Cat).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="type_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-cat" placeholder="Enter types of pets (e.g., Dog, Cat)">
            </div>
            <span class="error mt-2" id="type_of_pets_error"></span>
        </div>

        <!-- Breed of Pets -->
        <div class="form-group">
            <label class="fw-bold">Breed of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the breed(s) of the Tenant’s pets (e.g., Labrador, Siamese). If the Tenant has multiple pets with different breeds, list them all.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="breed_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" placeholder="Enter breeds of pets (e.g., Labrador, Siamese) ">
            </div>
            <span class="error mt-2" id="breed_of_pets_error"></span>
        </div>

        {{-- <!-- Breed Restrictions -->
        <div class="form-group">
            <label class="fw-bold">Breed Restrictions:</label>
            <div class="input-cover">
                <select wire:model="has_breed_restrictions" class="form-control has-icon" data-icon="fa-solid fa-ban">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        @if ($has_breed_restrictions === 'Yes')
            <div class="form-group">
                <div class="input-cover">
                    <input type="text" wire:model="breed_restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-shield-dog" placeholder="Enter breed restrictions (e.g., No pit bulls)">
                </div>
                <span class="error mt-2" id="breed_restrictions_error"></span>
            </div>
        @endif --}}

        <!-- Maximum Weight -->
        <div class="form-group">
            <label class="fw-bold">Pet Weight (lbs):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the weight of the Tenant’s pet(s) in pounds (e.g., 30 lbs, 50 lbs). If the Tenant has multiple pets, list their weights individually.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="weight_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-weight" placeholder="Enter the weight of pets (e.g., 30 lbs, 50 lbs) ">
            </div>
            <span class="error mt-2" id="weight_of_pets_error"></span>
        </div>

        <!-- Service Animal -->
        <div class="form-group">
            <label class="fw-bold">Service Animal:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select if any of the Tenant’s pets are trained service animals (e.g., for assistance with disabilities).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <select wire:model="service_animal" class="form-control has-icon" data-icon="fa-solid fa-dog">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <span class="error mt-2" id="service_animal_error"></span>
        </div>


        <!-- Emotional Support Animal -->
        <div class="form-group">
            <label class="fw-bold">Emotional Support Animal:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select if any of the Tenant’s pets are emotional support animals, providing therapeutic support.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <select wire:model="support_animal" class="form-control has-icon" data-icon="fa-solid fa-heart">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <span class="error mt-2" id="support_animal_error"></span>
        </div>
    </div>
@endif
<!-- Screening Concerns -->
<div class="form-group mt-4">
    <label class="fw-bold">Rental History Disclosure:<span
            class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Is there anything in your rental, credit, criminal, or background history that you would like the landlord to be aware of before reviewing this offer?">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


    <div class="input-cover">
        <select wire:model="screening_concerns" class="form-control has-icon" id="screening_concerns"
            data-icon="fa-solid fa-list" required>
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
    <span class="error mt-2 text-danger" id="screening_concerns_error"></span>
</div>

@if ($screening_concerns === 'Yes')
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model="screening_concerns_explanation" class="form-control has-icon"
                data-icon="fa-solid fa-list"
                placeholder="Enter screening concerns (e.g., low credit score, prior eviction, background check issues)">
        </div>
    </div>
@endif

{{-- Phase B T-06: Credit Score Range --}}
<div class="form-group mt-4">
    <label class="fw-bold">Credit Score Range:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Optionally indicate the Tenant's approximate credit score range. This is voluntary and used only to help Agents find appropriate matches. It does not affect listing access.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="credit_score_range" class="form-control has-icon"
            data-icon="fa-solid fa-credit-card">
            <option value="">Select</option>
            <option value="Excellent 750+">Excellent 750+</option>
            <option value="Good 700-749">Good 700–749</option>
            <option value="Fair 650-699">Fair 650–699</option>
            <option value="Below 650">Below 650</option>
            <option value="Prefer not to disclose">Prefer not to disclose</option>
        </select>
    </div>
</div>

{{-- Phase D Tenant T-07: Smoking Preference --}}
<div class="form-group mt-4">
    <label class="fw-bold">Smoking Preference:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the Tenant's smoking preference for the rental property (e.g., Non-Smoking, Smoking Allowed, No Preference).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="smoking_preference" class="form-control has-icon" data-icon="fa-solid fa-ban">
            <option value="">Select</option>
            <option value="Non-Smoking">Non-Smoking</option>
            <option value="Smoking Allowed">Smoking Allowed</option>
            <option value="No Preference">No Preference</option>
        </select>
    </div>
</div>

{{--
<!-- Tenant(s) Credit Score Rating -->
<div class="form-group">
    <label class="fw-bold">Tenant(s) Credit Score Rating:</label>
    <div class="input-cover has-select-icon">
        <!-- Dropdown for Predefined Services -->
        <select id="credit_scroe_rating" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-credit-card" wire:model="credit_scroe_rating" multiple>
            @foreach ($credit_score as $score)
                <option value="{{ $score['name'] }}">{{ $score['name'] }}</option>
            @endforeach
        </select>
    </div>
    </select>
    <span class="error mt-2" id="credit_scroe_rating_error"></span>
</div>

<!-- Prior Eviction(s) in the Last 7 Years -->
<div class="form-group">
    <label class="fw-bold">Prior Eviction(s) in the Last 7 Years:</label>
    <div class="input-cover">
        <select wire:model="prior_eviction" class="form-control has-icon" id="prior_eviction"
            data-icon="fa-solid fa-triangle-exclamation">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
    <span class="error mt-2 text-danger" id="prior_eviction_error"></span>
</div>

<!-- Show the explanation field if the user selects "Yes" -->
@if ($prior_eviction === 'Yes')
    <div class="form-group mt-3">
        <label class="fw-bold d-block">Please Explain:</label> <!-- Changed to d-block -->
        <div class="mt-2"> <!-- Removed input-cover class -->
            <textarea wire:model="eviction_explanation" class="form-control" rows="4" style="margin-left: 0;"></textarea> <!-- Explicit left margin -->
        </div>
        <span class="error mt-2 text-danger" id="eviction_explanation_error"></span>
    </div>
@endif

<!-- Prior Felony Conviction(s) in the Last 7 Years -->
<div class="form-group mt-4">
    <label class="fw-bold">Prior Felony Conviction(s) in the Last 7 Years:</label>
    <div class="input-cover">
        <select wire:model="prior_felony" class="form-control has-icon" id="prior_felony"
            data-icon="fa-solid fa-triangle-exclamation">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
    <span class="error mt-2 text-danger" id="prior_felony_error"></span>
</div>

<!-- Show the explanation field if the user selects "Yes" -->
@if ($prior_felony === 'Yes')
    <div class="form-group mt-3">
        <label class="fw-bold d-block">Please Explain:</label> <!-- Changed to d-block -->
        <div class="mt-2"> <!-- Removed input-cover class -->
            <textarea wire:model="prior_felony_explanation" class="form-control" rows="4" style="margin-left: 0;"></textarea> <!-- Explicit left margin -->
        </div>
        <span class="error mt-2 text-danger" id="prior_felony_explanation_error"></span>
    </div>
@endif --}}
