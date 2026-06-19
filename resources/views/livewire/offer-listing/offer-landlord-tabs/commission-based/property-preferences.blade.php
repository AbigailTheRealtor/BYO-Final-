
<h3>Property Details</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🏠 Describe the property being offered for rent — including location, size, style, layout, and key
                features.
            </strong>
        </div>
    </div>
</div>
<!-- Street Address -->
<div class="form-group mb-3">
    <label class="fw-bold">Street Address: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the street address of the property (e.g., 123 Main Street). City, County, and State will be entered separately below.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" id="landlord-offer-street-address" wire:model="address"
            class="form-control has-icon" data-icon="fa-solid fa-map-pin"
            placeholder="Enter street address (e.g., 123 Main Street)" required
            autocomplete="off">
    </div>
</div>

<!-- Unit / Apt / Suite -->
<div class="form-group mb-3">
    <label class="fw-bold">Unit / Apt / Suite:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the unit, apartment, or suite number if applicable (e.g., Apt 4B, Suite 200).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="unit_address"
            class="form-control has-icon" data-icon="fa-solid fa-door-open"
            placeholder="e.g., Apt 4B, Suite 200 (optional)"
            autocomplete="off">
    </div>
</div>

<input type="hidden" id="landlord-offer-property-lat" wire:model="property_lat">
<input type="hidden" id="landlord-offer-property-lng" wire:model="property_lng">
<input type="hidden" id="landlord-offer-google-place-id" wire:model="google_place_id">

<div class="alert alert-warning mt-3 p-2 small">
    <strong>🛡️ On the listing, only your City, County, State, and ZIP code are displayed.</strong> Your full address is shared with the Agent you hire only after an Agent is selected, helping protect your privacy while still allowing Agents to understand your general location.
</div>


<!-- Property City -->
<div class="form-group mb-3">
    <label class="fw-bold">City: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the city where the property is located. Selecting a city will automatically populate the county and state.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover position-relative">
        <input type="text" wire:model.live.debounce.300ms="property_city"
            wire:keydown.enter.prevent="selectPropertyCitySuggestion()"
            wire:keydown.arrow-up.prevent="decrementPropertyCityHighlight()"
            wire:keydown.arrow-down.prevent="incrementPropertyCityHighlight()"
            class="form-control has-icon @error('property_city') is-invalid @enderror" 
            data-icon="fa-solid fa-city"
            autocomplete="off" 
            placeholder="Enter city (e.g., Miami)"
            required>

        @if (!empty($propertyCitySuggestions) && count($propertyCitySuggestions) > 0)
            <div class="autocomplete-dropdown shadow-sm">
                <ul class="list-group">
                    @foreach ($propertyCitySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ ($highlightedPropertyCityIndex ?? -1) === $index ? 'bg-light' : '' }}"
                            @mousedown.prevent="$wire.selectPropertyCitySuggestion('{{ $suggestion }}')"
                            wire:key="property-city-suggestion-{{ $index }}">
                            <i class="fa-solid fa-city me-2 text-muted"></i>
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('property_city')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Property State -->
<div class="form-group mb-3">
    <label class="fw-bold">State: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the state where the property is located. This will be automatically populated when a city or county is selected.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="property_state" 
            class="form-control has-icon @error('property_state') is-invalid @enderror" 
            data-icon="fa-solid fa-flag-usa"
            placeholder="Enter state (e.g., FL)" 
            required>
        @error('property_state')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Property ZIP Code -->
<div class="form-group mb-3">
    <label class="fw-bold">ZIP Code: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the ZIP code where the property is located.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="property_zip" 
            class="form-control has-icon @error('property_zip') is-invalid @enderror" 
            data-icon="fa-solid fa-map-pin"
            placeholder="Enter ZIP code (e.g., 33101)" 
            required
            maxlength="10">
        @error('property_zip')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Property County -->
<div class="form-group mb-3">
    <label class="fw-bold">County: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the county where the property is located. This may be automatically populated when a city is selected.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="property_county" 
            class="form-control has-icon @error('property_county') is-invalid @enderror" 
            data-icon="fa-solid fa-map"
            placeholder="Enter county (e.g., Miami-Dade County)" 
            required>
        @error('property_county')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Acceptable State -->
@if ($stateFieldVisible)
    <div class="form-group">
        <label class="fw-bold">State: <span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the state where the rental property is located. Required for search and filtering accuracy.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover position-relative">
            <input type="text" wire:model="state" class="form-control has-icon" data-icon="fa-solid fa-flag-usa"
                required wire:keydown.arrow-up="decrementHighlight('state')"
                wire:keydown.arrow-down="incrementHighlight('state')"
                wire:keydown.enter.prevent="selectStateSuggestion" autocomplete="off" placeholder="Enter state (e.g., FL)"
                required>

            @if (count($stateSuggestions) > 0)
                <div class="autocomplete-dropdown-counties">
                    <ul class="list-group">
                        @foreach ($stateSuggestions as $index => $suggestion)
                            <li class="list-group-item {{ $highlightedStateIndex === $index ? 'active' : '' }}"
                                wire:click="selectStateSuggestion('{{ $suggestion }}')">
                                {{ $suggestion }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <span class="error mt-2" id="state_error"></span>
    </div>
@endif
<!-- Property Type Dropdown -->

@if ($zipCodeFieldVisible)
    <div class="form-group mb-3">
        <label class="fw-bold">ZIP Code:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the ZIP code for the rental property. Required for search and filtering accuracy.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover position-relative">
            <input type="text" wire:model="zip_code" wire:keydown.enter.prevent="selectZipCodeSuggestion()"
                wire:keydown.arrow-up.prevent="decrementHighlight('ZipCode')"
                wire:keydown.arrow-down.prevent="incrementHighlight('ZipCode')"
                class="form-control has-icon @error('zip_code') is-invalid @enderror" data-icon="fa-solid fa-map-pin"
                autocomplete="off" placeholder="Enter one or more ZIP codes (e.g., 33101, 33102)">

            @if (count($zipCodeSuggestions) > 0)
                <div class="autocomplete-dropdown shadow-sm">
                    <ul class="list-group">
                        @foreach ($zipCodeSuggestions as $index => $suggestion)
                            <li class="list-group-item {{ $highlightedZipCodeIndex === $index ? 'bg-light' : '' }}"
                                wire:click="selectZipCodeSuggestion('{{ $suggestion }}')"
                                wire:key="zip-suggestion-{{ $index }}">
                                <i class="fa-solid fa-map-pin me-2 text-muted"></i>
                                {{ $suggestion }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @error('zip_code')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>

        <!-- Display added ZIP codes -->
        <div class="mt-1 zip-container">
            @if (count($zipCodes) > 0)
                @foreach ($zipCodes as $index => $zip)
                    <span class="badge bg-primary rounded-pill d-inline-flex align-items-center" wire:key="zip-badge-{{ $index }}">
                        <i class="fa-solid fa-map-pin me-2"></i>
                        {{ $zip }}
                        <button type="button" class="byo-pill-remove ms-2"
                            wire:click="removeZipCode({{ $index }})" aria-label="Remove">&times;</button>
                    </span>
                @endforeach
            @endif
        </div>
    </div>
@endif

<!-- Property Type Dropdown -->

<div class="form-group">
    <label class="fw-bold">Property Type: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the type of property being offered for lease.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="property_type" id="property_type" class="form-control has-icon"
            data-icon="fa-solid fa-building" required>
            <option value="">Select</option>
            <option value="Residential Property" data-display="Residential Property"> Residential Property</option>
            <option value="Commercial Property" data-display="Commercial Property">Commercial Property</option>
        </select>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

<!-- Property Items Dropdown -->
<div class="form-group mt-3">
    <label class="fw-bold">Property Style: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the specific architectural or structural style of the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">

        <select wire:model="property_items" class="form-control has-icon" data-icon="fa-solid fa-home"
            @if (!$property_type) disabled @endif required>
            <option value="">Select</option>
            @if ($property_type === 'Residential Property')
                @foreach ($property_items as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial Property')
                @foreach ($property_items as $item)
                    @if (str_contains($item['class'], 'commercial-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @endif
        </select>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

@php
    $landlordConditionOptions = isset($property_condition_landlord) ? $property_condition_landlord : [
        ['name' => 'New Construction'],
        ['name' => 'Updated / Renovated'],
        ['name' => 'Partially Updated'],
        ['name' => 'Older but Well Maintained'],
    ];
    if (!empty($condition_prop) && is_string($condition_prop)) {
        $optNames = array_column($landlordConditionOptions, 'name');
        if (!in_array($condition_prop, $optNames)) {
            $landlordConditionOptions[] = ['name' => $condition_prop];
        }
    }
    $landlordConditionLabelMap = [
        'Older but Well Maintained' => 'Older but Clean & Well Maintained',
        'Older but clean & well maintained' => 'Older but Clean & Well Maintained',
    ];
@endphp
<div class="form-group">
    <label class="fw-bold">Property Condition: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the current condition of the property being offered for lease.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="condition_prop" id="condition_prop" class="form-control has-icon"
            data-icon="fa-solid fa-screwdriver-wrench" required>
            <option value="">Select</option>
            @foreach ($landlordConditionOptions as $row_pt)
                <option value="{{ $row_pt['name'] }}">{{ $landlordConditionLabelMap[$row_pt['name']] ?? $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="condition_prop_error"></span>
</div>

<!-- Other Property Condition Input (Hidden by Default) -->
{{-- <div class="form-group other_property_condition d-none">
    <label class="fw-bold">Other Property Condition:</label>
    <div class="input-cover">
        <input type="text" wire:model="other_property_condition" class="form-control has-icon"
            data-icon="fa-solid fa-home">
    </div>
    <span class="error mt-2" id="other_property_condition_error"></span>
</div> --}}

<!-- Minimum Bedrooms Needed -->
<div wire:key="landlord-property-fields-{{ $property_type ?? 'none' }}">
@if ($property_type === 'Residential Property')
    <div class="form-group" >
        <label class="fw-bold">Bedrooms: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the number of bedrooms in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="bedrooms" id="bedrooms" class="form-control has-icon" data-icon="fa-solid fa-bed"
                required>
                <option value="">Select</option>
                @foreach ($bedroomsRes as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="bedrooms_error"></span>
    </div>


    @if($bedrooms==="Other")
    <div class="form-group">
        {{-- <label class="fw-bold">Minimum Bedrooms Needed:</label> --}}
        <div class="input-cover">
            <input type="number" wire:model="other_bedrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bed" placeholder="Enter number of bedrooms (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bedrooms_error"></span>
    </div>
@endif
@endif

<!-- Other Bedrooms Input (Hidden by Default) -->

<!-- Minimum Bathrooms Needed -->
@if (in_array($property_type, ['Residential Property', 'Commercial Property']))

    <div class="form-group">
        <label class="fw-bold">Bathrooms: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the number of bathrooms in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="bathrooms" id="bathrooms" class="form-control has-icon" data-icon="fa-solid fa-bath"
                required>
                <option value="">Select</option>
                @foreach ($bathrooms as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="bathrooms_error"></span>
    </div>
    @if ($this->bathrooms === 'Other')
    <!-- Other Bathrooms Input -->
    <div class="form-group other_bathrooms">
        <div class="input-cover">
            <input type="number" wire:model="other_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath" placeholder="Enter number of bathrooms (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bathrooms_error"></span>
    </div>
    @endif
@endif
<!-- Minimum Heated Sqft Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Heated SqFt: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of climate-controlled (heated/cooled) interior space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="minimum_heated_square" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter heated square footage (e.g., 1000)" required
                data-error-id="minimum_heated_square_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="minimum_heated_square_error"></span>
    </div>
@endif

<!-- Minimum Net LeaseableSqft Needed -->
@if ($property_type === 'Commercial Property')
    <div class="form-group">
        <label class="fw-bold">Net Leasable SqFt: <span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the amount of commercial space available for lease, excluding shared areas like hallways or lobbies.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="minimum_leaseable" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter net leasable square footage (e.g., 1500)" required
                data-error-id="minimum_leaseable_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="minimum_leaseable_error"></span>
    </div>
@endif

<div class="form-group">
    <label class="fw-bold">Total SqFt:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the full square footage of the property, including all usable and non-usable areas.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <input type="text" wire:model="total_square_feet" class="form-control has-icon"
            data-icon="fa-solid fa-ruler" placeholder="Enter total square footage (e.g., 2000)"
            data-error-id="total_square_feet_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
            onpaste="handlePaste(event)">
    </div>
    <span class="error mt-2" id="total_square_feet_error"></span>
</div>

<div class="form-group mb-3">
    <label class="fw-bold mb-2">SqFt Heated Source:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the source used to verify heated square footage.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="sqft_heated_source" class="form-control has-icon" data-icon="fa-solid fa-ruler">
            <option value="">Select</option>
            <option value="Appraisal">Appraisal</option>
            <option value="Builder">Builder</option>
            <option value="Measured">Measured</option>
            <option value="Owner Provided">Owner Provided</option>
            <option value="Public Records">Public Records</option>
        </select>
    </div>
</div>

<!-- Minimum Total Acreage Needed -->
<div class="form-group">
    <label class="fw-bold">Total Acreage:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the total land area of the property, measured in acres.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="total_acreage" id="total_acreage" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined">
            <option value="">Select</option>
            @foreach ($acreageRes as $row_pt)
                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="total_acreage_error"></span>
</div>

<!-- View Preference Needed -->
<div class="form-group" wire:ignore wire:key="landlord-appliances-group">
    <label class="fw-bold">Appliances Included:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the appliances included with the property. If 'Other' is selected, enter any additional appliances.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover has-select-icon" wire:ignore>
        <select id="appliances" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-utensils" data-placeholder="Select" multiple>
            @foreach ($appliances as $row_pt)
                <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->appliances ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="appliances_error"></span>
</div>

<div class="form-group" id="other_appliances"
    style="display: {{ (is_array($this->appliances) && in_array('Other', $this->appliances)) ? 'block' : 'none' }}">
    <div class="input-cover">
        <input type="text" wire:model="other_appliances" class="form-control has-icon"
            data-icon="fa-solid fa-plug"
            placeholder="Enter appliances (e.g., Air fryer oven, Induction cooktop, Double oven)"
            @if (is_array($this->appliances) && in_array('Other', $this->appliances)) required @endif>
    </div>
    <span class="error mt-2" id="other_appliances_error"></span>
</div>
<!-- Furnishings Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Furnishings:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the property is furnished, partially furnished, turnkey, optional, or unfurnished. ">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="tenant_require" id="tenant_require" class="form-control has-icon"
                data-icon="fa-solid fa-couch">
                <option value="">Select</option>
                @foreach ($tenant_require as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="furnishings_needed_error"></span>
    </div>
@endif

<!-- Carport Spaces Needed (Residential Only) -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Carport:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the property has a carport. If yes, enter the number of available spaces.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="carport_needed" id="carport-needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    @if ($carport_needed == 'Yes')
    <!-- Carport Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group" id="other-carport-needed">
        <label class="fw-bold">Number of Carport Spaces:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_carport_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of carport spaces (e.g., 1) ">
        </div>
        <span class="error mt-2" id="other_carport_needed_error"></span>
    </div>
    @endif
@endif

<!-- Garage Spaces Needed (Residential Only) -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Garage:
        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the property has a garage. If yes, enter the number of available spaces.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="garage_needed" class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>
    @if ($garage_needed == 'Yes')
        <!-- Garage Spaces Input (Shown Only When "Yes" is Selected) -->
        <div class="form-group" id="other-garage-needed">
            <label class="fw-bold">Number of Garage Spaces:</label>
            <div class="input-cover">
                <input type="number" min="1" wire:model="other_garage_needed" class="form-control has-icon"
                    data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces (e.g., 2)">
            </div>
            <span class="error mt-2" id="other_garage_needed_error"></span>
        </div>
    @endif
@endif
{{-- garage_parking_spaces --}}
@if ($property_type === 'Commercial Property')

    <div class="form-group">
        <label class="fw-bold">Garage/Parking Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of garage or parking features available. If “Other” is selected, enter any additional garage or parking features in the provided field.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="garage_parking_spaces_option_landlord"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse" data-placeholder="Select" multiple>
                @foreach ($garage_parking_spaces as $row_pt)
                    <option value="{{ $row_pt['name'] }}"
                        {{ in_array($row_pt['name'], $garage_parking_spaces_option ?? []) ? 'selected' : '' }}>
                        {{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Other Parking Space Text Input -->
    <div class="form-group" id="other_garage_parking_spaces_option_landlord"
        style="{{ collect($garage_parking_spaces_option)->contains('Other') ? '' : 'display: none;' }}">
        {{-- <label class="fw-bold">Other Garage/Parking Features:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_parking_space_wrapper" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse"
                placeholder="Enter garage/parking features (e.g., Tandem parking, Gated entry, Shared driveway)">
        </div>
    </div>

@endif

{{-- Parking Terms — placed after Garage/Carport and Garage/Parking Features --}}
<div class="form-group">
    <label class="fw-bold">Parking Terms:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Describe the parking arrangement included with the lease (e.g., 1 assigned space, street parking only, garage included).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <textarea wire:model="parking_terms" class="form-control has-icon landlord-compact-textarea" rows="1"
            data-icon="fa-solid fa-car"
            placeholder="Enter parking terms (e.g., 1 assigned covered space included, 2 guest spaces available)"></textarea>
    </div>
</div>

<!-- Waterfront -->
<div class="form-group">
    <label class="fw-bold">Waterfront:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate whether the property has waterfront access (e.g., directly on a lake, river, canal, or ocean).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="waterfront" id="waterfront" class="form-control has-icon"
            data-icon="fa-solid fa-water">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
</div>

<!-- Water Access -->
<div class="form-group">
    <label class="fw-bold">Water Access:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the type(s) of water access associated with the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover has-select-icon" wire:ignore>
        <select id="water_access" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
            @foreach (['Bay/Harbor', 'Bayou', 'Beach', 'Canal - Freshwater', 'Canal - Saltwater', 'Creek', 'Gulf/Ocean', 'Intracoastal Waterway', 'Lake', 'Pond', 'River', 'Other'] as $opt)
                <option value="{{ $opt }}" {{ in_array($opt, $water_access ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group" style="display: {{ (is_array($water_access ?? []) && in_array('Other', $water_access ?? [])) ? 'block' : 'none' }};" id="other_water_access_wrapper">
    <div class="input-cover">
        <input type="text" wire:model="other_water_access" class="form-control has-icon"
            data-icon="fa-solid fa-water" placeholder="Enter title (e.g., example)">
    </div>
</div>

<!-- Water View -->
<div class="form-group">
    <label class="fw-bold">Water View:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the water view type(s) visible from the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover has-select-icon" wire:ignore>
        <select id="water_view" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-binoculars" data-placeholder="Select" multiple>
            @foreach (['Bay/Harbor - Full', 'Bay/Harbor - Partial', 'Canal', 'Creek/Stream', 'Gulf/Ocean - Full', 'Gulf/Ocean - Partial', 'Intracoastal Waterway', 'Lake', 'Pond', 'River', 'Other'] as $opt)
                <option value="{{ $opt }}" {{ in_array($opt, $water_view ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group" style="display: {{ (is_array($water_view ?? []) && in_array('Other', $water_view ?? [])) ? 'block' : 'none' }};" id="other_water_view_wrapper">
    <div class="input-cover">
        <input type="text" wire:model="other_water_view" class="form-control has-icon"
            data-icon="fa-solid fa-binoculars" placeholder="Enter title (e.g., example)">
    </div>
</div>

<!-- Water Frontage -->
<div class="form-group">
    <label class="fw-bold">Water Frontage:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Type of water body the property fronts (e.g., Intracoastal Waterway, Gulf/Ocean, Lake).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="water_frontage" class="form-control has-icon"
            data-icon="fa-solid fa-water"
            placeholder="e.g., Intracoastal Waterway, Gulf/Ocean, Lake">
    </div>
</div>

<!-- Waterfront Feet -->
<div class="form-group">
    <label class="fw-bold">Waterfront Feet:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Linear footage of waterfront the property has.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="number" wire:model="waterfront_feet" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-horizontal"
            placeholder="e.g., 75" min="0">
    </div>
</div>

<!-- Interior Features -->
<div class="form-group">
    <label class="fw-bold">Interior Features:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the interior features present in the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover has-select-icon" wire:ignore>
        <select id="interior_features" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-house" data-placeholder="Select" multiple>
            @foreach (['Ceiling Fans(s)', 'Crown Molding', 'Eat-in Kitchen', 'Fireplace', 'High Ceilings', 'Kitchen/Family Room Combo', 'Living Room/Dining Room Combo', 'Open Floorplan', 'Primary Bedroom Main Floor', 'Skylight(s)', 'Split Bedroom', 'Stone Counters', 'Granite Counters', 'Quartz Counters', 'Tray Ceiling(s)', 'Vaulted Ceiling(s)', 'Walk-In Closet(s)', 'Wet Bar', 'Window Treatments', 'Other'] as $opt)
                <option value="{{ $opt }}" {{ in_array($opt, $interior_features ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group" style="display: {{ (is_array($interior_features ?? []) && in_array('Other', $interior_features ?? [])) ? 'block' : 'none' }};" id="other_interior_features_wrapper">
    <div class="input-cover">
        <input type="text" wire:model="other_interior_features" class="form-control has-icon"
            data-icon="fa-solid fa-house" placeholder="Enter title (e.g., example)">
    </div>
</div>

<!-- Pool Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Pool:
        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the property has a pool. If yes, indicate the pool type—Private or Community.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="pool_needed" id="pool_needed" class="form-control has-icon"
                data-icon="fa-solid fa-water">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>
@endif

@if ($property_type !== 'Commercial Property' && $pool_needed === 'Yes')
    <!-- Pool Type Selection (Shows only if "Yes" is selected and not Commercial) -->
    <div class="form-group" id="pool_type_wrapper">
        <label class="fw-bold">Select Pool Type:</label>
        <div class="form-check">
            <input type="checkbox" wire:model="pool_type.private" id="pool_private" class="form-check-input">
            <label class="form-check-label" for="pool_private">🏊‍♂️ Private</label>
        </div>
        <div class="form-check">
            <input type="checkbox" wire:model="pool_type.community" id="pool_community" class="form-check-input">
            <label class="form-check-label" for="pool_community">🏢 Community</label>
        </div>
    </div>
@endif

<div class="form-group" wire:ignore wire:key="landlord-view-pref-group">
    <label class="fw-bold">View:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the property’s view. If “Other” is selected, describe the view.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover has-select-icon" wire:ignore>
        <select id="view_preference"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-tree" data-placeholder="Select" multiple>
            @foreach ($preferences as $row_pt)
                <option value="{{ $row_pt['name'] }}"
                    {{ in_array($row_pt['name'], $view_preference ?? []) ? 'selected' : '' }}>
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="view_preference_error"></span>
</div>

<!-- Other Preferences Input (Hidden or Visible based on Livewire state) -->
<div class="form-group" id="other_preferences" style="display: {{ is_array($view_preference ?? []) && in_array('Other', $view_preference ?? []) ? 'block' : 'none' }}">
    <div class="input-cover">
        <input type="text" wire:model="other_preferences" class="form-control has-icon"
            data-icon="fa-solid fa-tree" placeholder="Enter view (e.g., Lake, Desert, Courtyard)">
    </div>
    <span class="error mt-2" id="other_preferences_error"></span>
</div>

<!-- Eligibility/Interest in Leasing in 55-and-Over Communities -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Age-Restricted Community:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if the property is part of an age-restricted community under federal housing laws. 55+ communities typically require at least one occupant to be 55 or older, while 62+ housing requires all residents to be 62 or older.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="leasing_55_plus" id="purchasing_props" class="form-control has-icon"
                data-icon="fa-solid fa-users">
                <option value="">Select</option>
                @foreach ($purchasing_props as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="leasing_55_plus_error"></span>
    </div>
@endif
<!-- Non-Negotiable Amenities and Property Features -->
<div class="form-group" wire:key="landlord-nna-{{ $property_type }}">
    <label class="fw-bold">Amenities and Property Features:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the amenities and property features included with the property. If 'Other' is selected, enter any additional amenities or features.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover has-select-icon" wire:ignore>
        <select id="non_negotiable_amenities"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock" data-placeholder="Select"
            @if (!$property_type) disabled @endif multiple>
            @if ($property_type === 'Residential Property')
                @foreach ($non_negotialble_terms_landlord as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}" {{ in_array($item['name'], $this->non_negotiable_amenities ?? []) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial Property')
                @foreach ($non_negotialble_terms_landlord as $item)
                    @if (str_contains($item['class'], 'commercial-length'))
                        <option value="{{ $item['name'] }}" {{ in_array($item['name'], $this->non_negotiable_amenities ?? []) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @endif
        </select>
    </div>
    <span class="error mt-2" id="non_negotiable_amenities_error"></span>
</div>

<!-- Other  Non-Negotiable Amenities and Property Features  Input (Hidden by Default) -->
<div
    class="form-group other_non_negotiable_amenities {{ in_array('Other', $non_negotiable_amenities ?? []) ? '' : 'd-none' }}">

    {{-- <label class="fw-bold">Non-Negotiable Amenities and Property Features:</label> --}}
    <div class="input-cover">
        <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
            data-icon="fa-solid fa-lock"
            @if ($property_type === 'Residential Property') placeholder="Enter amenities or property features (e.g., Sauna, EV charger, Outdoor kitchen)"
        @elseif($property_type === 'Commercial Property')
            placeholder="Enter amenities or property features (e.g., Rooftop access, Backup generator, Freight elevator)" @endif>

    </div>
    <span class="error mt-2" id="other_non_negotiable_amenities_error"></span>
</div>

{{-- ====== MLS Property Detail Fields — Residential only ====== --}}
@if ($property_type === 'Residential Property')

    <!-- Year Built -->
    <div class="form-group">
        <label class="fw-bold">Year Built:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the property was originally built (e.g., 1995).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="year_built" class="form-control has-icon"
                data-icon="fa-solid fa-calendar" placeholder="Enter year built (e.g., 1998)" min="1800" max="{{ date('Y') }}">
        </div>
        <span class="error mt-2" id="year_built_error"></span>
    </div>

    <!-- Lot Dimensions -->
    <div class="form-group">
        <label class="fw-bold">Lot Dimensions:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the lot dimensions (e.g., 100x200, 150x300).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="lot_dimensions" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter lot dimensions (e.g., 100x200)">
        </div>
        <span class="error mt-2" id="lot_dimensions_error"></span>
    </div>

    <!-- Roof Type -->
    <div class="form-group" wire:key="roof-type-landlord-res">
        <label class="fw-bold">Roof Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of roofing material on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="roof_type_landlord_res" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-house" data-placeholder="Select" multiple>
                @foreach (['Built-Up','Cement','Concrete','Membrane','Metal','Roof Over','Shake','Shingle','Slate','Tile','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($roof_type) && in_array($_opt, $roof_type) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="roof_type_error_landlord_res"></span>
    </div>
    <div class="form-group" id="other_roof_type_landlord_res_wrapper" style="{{ is_array($roof_type) && in_array('Other', $roof_type) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_roof_type" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter roof type (e.g., Foam, TPO)">
        </div>
    </div>

    <!-- Exterior Construction -->
    <div class="form-group" wire:key="exterior-construction-landlord-res">
        <label class="fw-bold">Exterior Construction:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the exterior construction material(s) of the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="exterior_construction_landlord_res" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-building" data-placeholder="Select" multiple>
                @foreach (['Asbestos','Block','Brick','Cedar','Cement Siding','Concrete','HardiPlank Type','ICFs (Insulated Concrete Forms)','Log','Metal Frame','Metal Siding','SIP (Structurally Insulated Panel)','Stone','Stucco','Tilt up Walls','Vinyl Siding','Wood Frame','Wood Frame (FSC)','Wood Siding','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($exterior_construction) && in_array($_opt, $exterior_construction) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="exterior_construction_error_landlord_res"></span>
    </div>
    <div class="form-group" id="other_exterior_construction_landlord_res_wrapper" style="{{ is_array($exterior_construction) && in_array('Other', $exterior_construction) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_exterior_construction" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter exterior construction (e.g., Fiber cement, SIPs)">
        </div>
    </div>

    <!-- Foundation -->
    <div class="form-group" wire:key="foundation-landlord-res">
        <label class="fw-bold">Foundation:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of foundation for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="foundation_landlord_res" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-layer-group" data-placeholder="Select" multiple>
                @foreach (['Basement','Block','Brick/Mortar','Concrete Perimeter','Crawlspace','Pillar/Post/Pier','Slab','Stem Wall','Stilt/On Piling','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($foundation) && in_array($_opt, $foundation) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="foundation_error_landlord_res"></span>
    </div>
    <div class="form-group" id="other_foundation_landlord_res_wrapper" style="{{ is_array($foundation) && in_array('Other', $foundation) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_foundation" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter foundation type (e.g., Helical pier, Pressure-treated wood)">
        </div>
    </div>

    <!-- Heating and Fuel -->
    <div class="form-group">
        <label class="fw-bold">Heating and Fuel:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the heating and fuel types available at the property (e.g., Central, Natural Gas, Heat Pump).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="heating_fuel" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-fire" data-placeholder="Select" multiple>
                @foreach (['Baseboard', 'Central', 'Electric', 'Exhaust Fans', 'Gas', 'Heat Pump', 'Natural Gas', 'Partial', 'Propane', 'Solar', 'Space Heater', 'Wall/Window Unit(s)', 'Zoned', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $heating_fuel ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="heating_fuel_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($heating_fuel ?? []) && in_array('Other', $heating_fuel ?? [])) ? 'block' : 'none' }};" id="other_heating_fuel_wrapper">
        {{-- <label class="fw-bold">Other Heating / Fuel:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_heating_fuel" class="form-control has-icon"
                data-icon="fa-solid fa-fire" placeholder="Enter heating/fuel type (e.g., Wood pellet, Geothermal)">
        </div>
    </div>

    <!-- Air Conditioning -->
    <div class="form-group">
        <label class="fw-bold">Air Conditioning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the air conditioning systems available at the property (e.g., Central Air, Mini-Split Unit(s)).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="air_conditioning" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-wind" data-placeholder="Select" multiple>
                @foreach (['Central Air', 'Humidity Control', 'Mini-Split Unit(s)', 'Wall/Window Unit(s)', 'Zoned', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $air_conditioning ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="air_conditioning_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($air_conditioning ?? []) && in_array('Other', $air_conditioning ?? [])) ? 'block' : 'none' }};" id="other_air_conditioning_wrapper">
        {{-- <label class="fw-bold">Other Air Conditioning:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_air_conditioning" class="form-control has-icon"
                data-icon="fa-solid fa-snowflake" placeholder="Enter air conditioning type (e.g., Evaporative cooler, Geo-thermal)">
        </div>
    </div>

    <!-- Water -->
    <div class="form-group">
        <label class="fw-bold">Water:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the water source(s) available at the property (e.g., Public, Well, Private).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="water" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-droplet" data-placeholder="Select" multiple>
                @foreach (['Canal/Lake for Irrigation', 'Private', 'Public', 'See Remarks', 'Well', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $water ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="water_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($water ?? []) && in_array('Other', $water ?? [])) ? 'block' : 'none' }};" id="other_water_wrapper">
        <div class="input-cover">
            <input type="text" wire:model="other_water" class="form-control has-icon"
                data-icon="fa-solid fa-droplet" placeholder="Enter water source (e.g., Rainwater collection, Shared well)">
        </div>
    </div>

    <!-- Sewer -->
    <div class="form-group">
        <label class="fw-bold">Sewer:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the sewer or waste disposal system(s) used at the property (e.g., Public Sewer, Septic Tank).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sewer" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
                @foreach (['Aerobic Septic', 'PEP-Holding Tank', 'Private Sewer', 'Public Sewer', 'Septic Tank', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $sewer ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sewer_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($sewer ?? []) && in_array('Other', $sewer ?? [])) ? 'block' : 'none' }};" id="other_sewer_wrapper">
        {{-- <label class="fw-bold">Other Sewer:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_sewer" class="form-control has-icon"
                data-icon="fa-solid fa-water" placeholder="Enter sewer type (e.g., Cesspool, Composting)">
        </div>
    </div>

    <!-- Utilities -->
    <div class="form-group">
        <label class="fw-bold">Utilities:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the utilities available or connected at the property (e.g., Electricity Connected, Natural Gas Available, Sewer Connected).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="property_utilities" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-bolt" data-placeholder="Select" multiple>
                @foreach (['BB/HS Internet Available', 'Cable Available', 'Cable Connected', 'Emergency Power', 'Electric - Multiple Meters', 'Electrical Nearby', 'Electricity Available', 'Electricity Connected', 'Fiber Optics', 'Fire Hydrant', 'Mini Sewer', 'Natural Gas Available', 'Natural Gas Connected', 'Phone Available', 'Private', 'Propane', 'Public', 'Sewer Available', 'Sewer Connected', 'Solar', 'Sprinkler Meter', 'Sprinkler Recycled', 'Sprinkler Well', 'Street Lights', 'Underground Utilities', 'Water - Multiple Meters', 'Water Available', 'Water Connected', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $property_utilities ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="property_utilities_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($property_utilities ?? []) && in_array('Other', $property_utilities ?? [])) ? 'block' : 'none' }};" id="other_property_utilities_wrapper">
        {{-- <label class="fw-bold">Other Utilities:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_property_utilities" class="form-control has-icon"
                data-icon="fa-solid fa-bolt" placeholder="Enter utilities (e.g., Fiber internet, Solar power, Generator hookup)">
        </div>
    </div>

    <!-- Laundry Features -->
    <div class="form-group">
        <label class="fw-bold">Laundry Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the laundry options available at the property (e.g., In-Unit Washer/Dryer, Laundry Room, Electric Dryer Hookup).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="laundry_features" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-shirt" data-placeholder="Select" multiple>
                @foreach (['Common Area', 'Corridor Access', 'Electric Dryer Hookup', 'Gas Dryer Hookup', 'In Garage', 'In Kitchen', 'Inside', 'Laundry Chute', 'Laundry Closet', 'Laundry Room', 'Outside', 'Same Floor as Condo Unit', 'Upper Floor', 'Washer Hookup', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $laundry_features ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="laundry_features_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($laundry_features ?? []) && in_array('Other', $laundry_features ?? [])) ? 'block' : 'none' }};" id="other_laundry_features_wrapper">
        {{-- <label class="fw-bold">Other Laundry Features:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_laundry_features" class="form-control has-icon"
                data-icon="fa-solid fa-shirt" placeholder="Enter laundry features (e.g., In-unit, Coin laundry, Laundry room)">
        </div>
    </div>

    <!-- Floor Covering -->
    <div class="form-group">
        <label class="fw-bold">Floor Covering:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the floor covering type(s) present in the property (e.g., Hardwood, Carpet, Ceramic Tile).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="floor_covering" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-layer-group" data-placeholder="Select" multiple>
                @foreach (['Bamboo', 'Brick/Stone', 'Carpet', 'Ceramic Tile', 'Concrete', 'Cork', 'Engineered Hardwood', 'Epoxy', 'Forestry Stewardship Certified', 'Granite', 'Laminate', 'Linoleum', 'Luxury Vinyl', 'Marble', 'Parquet', 'Porcelain Tile', 'Quarry Tile', 'Reclaimed Wood', 'Recycled/Composite Flooring', 'Slate', 'Terrazzo', 'Tile', 'Travertine', 'Vinyl', 'Wood', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $floor_covering ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="floor_covering_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($floor_covering ?? []) && in_array('Other', $floor_covering ?? [])) ? 'block' : 'none' }};" id="other_floor_covering_wrapper">
        {{-- <label class="fw-bold">Other Floor Covering:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_floor_covering" class="form-control has-icon"
                data-icon="fa-solid fa-layer-group" placeholder="Enter floor covering (e.g., Hardwood, Tile, Vinyl plank)">
        </div>
    </div>

    <!-- Security Features -->
    <div class="form-group">
        <label class="fw-bold">Security Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the security features present at the property (e.g., Security System, Smoke Detector(s), Gated Community).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="security_features" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-shield-halved" data-placeholder="Select" multiple>
                @foreach (['Closed Circuit Camera(s)', 'Fire Alarm', 'Fire Sprinkler System', 'Gated Community', 'Key Card Entry', 'Medical Alarm', 'Secured Garage/Parking', 'Security Fencing/Lighting/Alarms', 'Security Gate', 'Security Lights', 'Security System', 'Security System Leased', 'Security System Owned', 'Smoke Detector(s)', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $security_features ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="security_features_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($security_features ?? []) && in_array('Other', $security_features ?? [])) ? 'block' : 'none' }};" id="other_security_features_wrapper">
        <div class="input-cover">
            <input type="text" wire:model="other_security_features" class="form-control has-icon"
                data-icon="fa-solid fa-shield-halved" placeholder="Enter security features (e.g., Deadbolt, Security camera, Intercom)">
        </div>
    </div>

@endif

{{-- ====== MLS Property Detail Fields — Commercial only ====== --}}
@if ($property_type === 'Commercial Property')

    <!-- Year Built -->
    <div class="form-group">
        <label class="fw-bold">Year Built:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the property was originally built (e.g., 1995).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="year_built" class="form-control has-icon"
                data-icon="fa-solid fa-calendar" placeholder="Enter year built (e.g., 1998)" min="1800" max="{{ date('Y') }}">
        </div>
        <span class="error mt-2" id="year_built_error"></span>
    </div>

    <!-- Zoning -->
    <div class="form-group">
        <label class="fw-bold">Zoning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the zoning classification for the property (e.g., C-2, I-1, B-3).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="zoning" class="form-control has-icon"
                data-icon="fa-solid fa-map" placeholder="Enter zoning code (e.g., C-1, B-2)">
        </div>
        <span class="error mt-2" id="zoning_error"></span>
    </div>

    <!-- Total Number of Buildings -->
    <div class="form-group">
        <label class="fw-bold">Total Number of Buildings:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of buildings on the property (e.g., 1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="total_buildings" class="form-control has-icon"
                data-icon="fa-solid fa-building" placeholder="Enter total number of buildings (e.g., 3)" min="0">
        </div>
        <span class="error mt-2" id="total_buildings_error"></span>
    </div>

    <!-- Total Units on Property -->
    <div class="form-group">
        <label class="fw-bold">Total Units on Property:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of rentable or leasable units on the property (e.g., 4).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="total_units_on_property" class="form-control has-icon"
                data-icon="fa-solid fa-hashtag" placeholder="Enter total units on property (e.g., 24)" min="0">
        </div>
        <span class="error mt-2" id="total_units_on_property_error"></span>
    </div>

    <!-- Office / Retail Space SqFt -->
    <div class="form-group">
        <label class="fw-bold">Office / Retail Space SqFt:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of office or retail space in the property (e.g., 2500).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="office_retail_sqft" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined" placeholder="Enter office or retail space sqft (e.g., 5000)" min="0">
        </div>
        <span class="error mt-2" id="office_retail_sqft_error"></span>
    </div>

    <!-- Flex Space SqFt -->
    <div class="form-group">
        <label class="fw-bold">Flex Space SqFt:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of flexible-use space in the property (e.g., 1200).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="flex_space_sqft" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined" placeholder="Enter flex space sqft (e.g., 2500)" min="0">
        </div>
        <span class="error mt-2" id="flex_space_sqft_error"></span>
    </div>

    <!-- Road Surface Type -->
    <div class="form-group">
        <label class="fw-bold">Road Surface Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the road surface type(s) providing access to the property (e.g., Asphalt, Concrete, Paved).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="road_surface_type" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-road" data-placeholder="Select" multiple>
                @foreach (['Asphalt', 'Brick', 'Chip And Seal', 'Concrete', 'Dirt', 'Gravel', 'Limerock', 'Paved', 'Unimproved', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $road_surface_type ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="road_surface_type_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($road_surface_type ?? []) && in_array('Other', $road_surface_type ?? [])) ? 'block' : 'none' }};" id="other_road_surface_type_wrapper">
        {{-- <label class="fw-bold">Other Road Surface Type:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_road_surface_type" class="form-control has-icon"
                data-icon="fa-solid fa-road" placeholder="Enter road surface type (e.g., Cobblestone, Shell)">
        </div>
    </div>

    <!-- Utilities -->
    <div class="form-group">
        <label class="fw-bold">Utilities:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the utilities available or connected at the property (e.g., Electricity Connected, Natural Gas Available, Sewer Connected).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="property_utilities" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-bolt" data-placeholder="Select" multiple>
                @foreach (['BB/HS Internet Available', 'Cable Available', 'Cable Connected', 'Emergency Power', 'Electric - Multiple Meters', 'Electrical Nearby', 'Electricity Available', 'Electricity Connected', 'Fiber Optics', 'Fire Hydrant', 'Mini Sewer', 'Natural Gas Available', 'Natural Gas Connected', 'Phone Available', 'Private', 'Propane', 'Public', 'Sewer Available', 'Sewer Connected', 'Solar', 'Sprinkler Meter', 'Sprinkler Recycled', 'Sprinkler Well', 'Street Lights', 'Underground Utilities', 'Water - Multiple Meters', 'Water Available', 'Water Connected', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $property_utilities ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="property_utilities_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($property_utilities ?? []) && in_array('Other', $property_utilities ?? [])) ? 'block' : 'none' }};" id="other_property_utilities_wrapper">
        {{-- <label class="fw-bold">Other Utilities:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_property_utilities" class="form-control has-icon"
                data-icon="fa-solid fa-bolt" placeholder="Enter utilities (e.g., Fiber internet, Solar power, Generator hookup)">
        </div>
    </div>

    <!-- Water -->
    <div class="form-group">
        <label class="fw-bold">Water:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the water source(s) available at the property (e.g., Public, Well, Private).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="water" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-droplet" data-placeholder="Select" multiple>
                @foreach (['Canal/Lake for Irrigation', 'Private', 'Public', 'See Remarks', 'Well', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $water ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="water_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($water ?? []) && in_array('Other', $water ?? [])) ? 'block' : 'none' }};" id="other_water_wrapper">
        <div class="input-cover">
            <input type="text" wire:model="other_water" class="form-control has-icon"
                data-icon="fa-solid fa-droplet" placeholder="Enter water source (e.g., Rainwater collection, Shared well)">
        </div>
    </div>

    <!-- Sewer -->
    <div class="form-group">
        <label class="fw-bold">Sewer:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the sewer or waste disposal system(s) used at the property (e.g., Public Sewer, Septic Tank).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sewer" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
                @foreach (['Aerobic Septic', 'PEP-Holding Tank', 'Private Sewer', 'Public Sewer', 'Septic Tank', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $sewer ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sewer_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($sewer ?? []) && in_array('Other', $sewer ?? [])) ? 'block' : 'none' }};" id="other_sewer_wrapper">
        {{-- <label class="fw-bold">Other Sewer:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_sewer" class="form-control has-icon"
                data-icon="fa-solid fa-water" placeholder="Enter sewer type (e.g., Cesspool, Composting)">
        </div>
    </div>

    <!-- Heating and Fuel -->
    <div class="form-group">
        <label class="fw-bold">Heating and Fuel:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the heating and fuel types available at the property (e.g., Central, Natural Gas, Heat Pump).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="heating_fuel" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-fire" data-placeholder="Select" multiple>
                @foreach (['Baseboard', 'Central', 'Electric', 'Exhaust Fans', 'Gas', 'Heat Pump', 'Natural Gas', 'Partial', 'Propane', 'Solar', 'Space Heater', 'Wall/Window Unit(s)', 'Zoned', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $heating_fuel ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="heating_fuel_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($heating_fuel ?? []) && in_array('Other', $heating_fuel ?? [])) ? 'block' : 'none' }};" id="other_heating_fuel_wrapper">
        {{-- <label class="fw-bold">Other Heating / Fuel:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_heating_fuel" class="form-control has-icon"
                data-icon="fa-solid fa-fire" placeholder="Enter heating/fuel type (e.g., Wood pellet, Geothermal)">
        </div>
    </div>

    <!-- Air Conditioning -->
    <div class="form-group">
        <label class="fw-bold">Air Conditioning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the air conditioning systems available at the property (e.g., Central Air, Mini-Split Unit(s)).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="air_conditioning" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-wind" data-placeholder="Select" multiple>
                @foreach (['Central Air', 'Humidity Control', 'Mini-Split Unit(s)', 'Wall/Window Unit(s)', 'Zoned', 'None', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $air_conditioning ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="air_conditioning_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($air_conditioning ?? []) && in_array('Other', $air_conditioning ?? [])) ? 'block' : 'none' }};" id="other_air_conditioning_wrapper">
        {{-- <label class="fw-bold">Other Air Conditioning:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_air_conditioning" class="form-control has-icon"
                data-icon="fa-solid fa-snowflake" placeholder="Enter air conditioning type (e.g., Evaporative cooler, Geo-thermal)">
        </div>
    </div>

    <!-- Electrical Service -->
    <div class="form-group">
        <label class="fw-bold">Electrical Service:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the electrical service configuration(s) available at the property (e.g., 200+ Amp Service, 3 Phase, Generator).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="electrical_service" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-plug" data-placeholder="Select" multiple>
                @foreach (['100 Amp Service', '150 Amp Service', '200+ Amp Service', '3 Phase', '110 Volts', '220 Volts', '440 Volts', 'Generator', 'Generator Hook-Up', 'Separate Meters', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $electrical_service ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="electrical_service_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($electrical_service ?? []) && in_array('Other', $electrical_service ?? [])) ? 'block' : 'none' }};" id="other_electrical_service_wrapper">
        {{-- <label class="fw-bold">Other Electrical Service:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_electrical_service" class="form-control has-icon"
                data-icon="fa-solid fa-plug" placeholder="Enter electrical service (e.g., 600 volts, DC power)">
        </div>
    </div>

    <!-- Ceiling Height -->
    <div class="form-group">
        <label class="fw-bold">Ceiling Height:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the ceiling height range for the property (e.g., 10 to 15 Feet, 23+ Feet).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="ceiling_height" class="form-control has-icon" data-icon="fa-solid fa-arrows-up-down">
                <option value="">Select</option>
                @foreach (['8 to 9 Feet', '10 to 15 Feet', '16 to 22 Feet', '23+ Feet', 'Varied'] as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="ceiling_height_error"></span>
    </div>

    <!-- Building Features -->
    <div class="form-group">
        <label class="fw-bold">Building Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the features and amenities included in the building (e.g., Elevator, Loading Dock, Overhead Doors).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="building_features" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-building" data-placeholder="Select" multiple>
                @foreach (['Bathrooms', 'Clear Span', 'Columns', 'Common Lighting', 'Drive-Through', 'Dumpsters', 'Elevator', 'Elevator - None', 'Extra Storage', 'Fencing', 'Fiber Optic', 'Freight Elevator', 'Furnished', 'High Bays', 'Janitorial Services', 'Kitchen Facility', 'Lit Sign on Site', 'Loading Dock', 'Loft', 'Medical Disposal', 'On Site Shower', 'Outside Storage', 'Overhead Doors', 'Pool/Spa', 'Ramp', 'Reception', 'Seating', 'Service Stations', 'Solid Surface Counter', 'Stone Counter', 'Trash Removal', 'Truck Doors', 'Truck Well', 'Waiting Room', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $building_features ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="building_features_error"></span>
    </div>
    <div class="form-group" style="display: {{ (is_array($building_features ?? []) && in_array('Other', $building_features ?? [])) ? 'block' : 'none' }};" id="other_building_features_wrapper">
        {{-- <label class="fw-bold">Other Building Features:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_building_features" class="form-control has-icon"
                data-icon="fa-solid fa-building" placeholder="Enter building features (e.g., Skylight, Automated gate)">
        </div>
    </div>

    <!-- Number Electric Meters -->
    <div class="form-group">
        <label class="fw-bold">Number Electric Meters:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of electric meters on the property (e.g., 1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_electric_meters" class="form-control has-icon"
                data-icon="fa-solid fa-bolt" placeholder="Enter number of electric meters (e.g., 4)" min="0">
        </div>
        <span class="error mt-2" id="number_electric_meters_error"></span>
    </div>

    <!-- Number Water Meters -->
    <div class="form-group">
        <label class="fw-bold">Number Water Meters:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of water meters on the property (e.g., 1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_water_meters" class="form-control has-icon"
                data-icon="fa-solid fa-droplet" placeholder="Enter number of water meters (e.g., 4)" min="0">
        </div>
        <span class="error mt-2" id="number_water_meters_error"></span>
    </div>

    <!-- Number Gas Meters -->
    <div class="form-group">
        <label class="fw-bold">Number Gas Meters:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of gas meters on the property (e.g., 1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_gas_meters" class="form-control has-icon"
                data-icon="fa-solid fa-fire" placeholder="Enter number of gas meters (e.g., 1)" min="0">
        </div>
        <span class="error mt-2" id="number_gas_meters_error"></span>
    </div>

    <!-- Space Type -->
    <div class="form-group">
        <label class="fw-bold">Space Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the condition or availability status of the space (e.g., New, Gray Shell, Sub Let).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="space_type" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-warehouse" data-placeholder="Select" multiple>
                @foreach (['Gray Shell', 'New', 'Re Let', 'Sub Let', 'Vanilla Shell'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $space_type ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="space_type_error"></span>
    </div>

    <!-- Space Classification -->
    <div class="form-group">
        <label class="fw-bold">Space Classification:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the quality classification of the commercial space (A = premium, D = lower-grade).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="space_classification" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-star" data-placeholder="Select" multiple>
                @foreach (['A', 'B', 'C', 'D'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $space_classification ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="space_classification_error"></span>
    </div>

    <!-- Number of Restrooms -->
    <div class="form-group">
        <label class="fw-bold">Number of Restrooms:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of restrooms in the property (e.g., 2).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_of_restrooms" class="form-control has-icon"
                data-icon="fa-solid fa-restroom" placeholder="Enter number of restrooms (e.g., 4)" min="0">
        </div>
        <span class="error mt-2" id="number_of_restrooms_error"></span>
    </div>

    <!-- Number of Offices -->
    <div class="form-group">
        <label class="fw-bold">Number of Offices:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of private offices in the property (e.g., 4).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_of_offices" class="form-control has-icon"
                data-icon="fa-solid fa-door-open" placeholder="Enter number of offices (e.g., 10)" min="0">
        </div>
        <span class="error mt-2" id="number_of_offices_error"></span>
    </div>

    <!-- Number of Conference / Meeting Rooms -->
    <div class="form-group">
        <label class="fw-bold">Number of Conference / Meeting Rooms:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of conference or meeting rooms in the property (e.g., 1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="number_of_conference_rooms" class="form-control has-icon"
                data-icon="fa-solid fa-people-group" placeholder="Enter number of conference or meeting rooms (e.g., 2)" min="0">
        </div>
        <span class="error mt-2" id="number_of_conference_rooms_error"></span>
    </div>

@endif

<!-- Pets Allowed -->
@if ($property_type === 'Residential Property')

    <div class="form-group">
        <label class="fw-bold">Pets Allowed:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether pets are allowed at the property. If &quot;Yes&quot; is selected, provide details including the number of pets allowed, acceptable pet types, maximum weight per pet, and any applicable pet restrictions.">
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
    @if ($pets === 'Yes')
        <div id="pet-details">
            <!-- Number of Pets Allowed -->
            <div class="form-group">
                <label class="fw-bold">Number of Pets Allowed:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the maximum number of pets the Landlord will allow for this property (e.g., 2).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="number" wire:model="number_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets allowed (e.g., 2)">
                </div>
                <span class="error mt-2" id="number_of_pets_error"></span>
            </div>

            <!-- Acceptable Pet Types -->
            <div class="form-group">
                <label class="fw-bold">Acceptable Pet Types:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the types of pets the Landlord will allow (e.g., Dog, Cat).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="type_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-cat" placeholder="Enter acceptable pet types (e.g., Dog, Cat)">
                </div>
                <span class="error mt-2" id="type_of_pets_error"></span>
            </div>

            <!-- Breed of Pets -->
            {{-- <div class="form-group">
            <label class="fw-bold">Breed of Pet(s):</label>
            <div class="input-cover">
                <input type="text" wire:model="breed_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" placeholder="Enter breed(s) of pets (e.g., Labrador, Siamese)">
            </div>
            <span class="error mt-2" id="breed_of_pets_error"></span>
        </div> --}}

            <!-- Breed Restrictions -->
            {{-- <div class="form-group">
            <label class="fw-bold">Breed Restrictions:</label>
            <div class="input-cover">
                <select wire:model="has_breed_restrictions" class="form-control has-icon"
                    data-icon="fa-solid fa-ban">
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
                        data-icon="fa-solid fa-shield-dog"
                        placeholder="Enter breed restrictions (e.g., No pit bulls)">
                </div>
                <span class="error mt-2" id="breed_restrictions_error"></span>
            </div>
        @endif --}}

            <!-- Maximum Weight -->
            <div class="form-group">
                <label class="fw-bold">Maximum Weight Per Pet (lbs):</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the maximum allowed weight for each individual pet, in pounds. Leave blank if there is no weight restriction.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="weight_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-weight" placeholder="Enter maximum weight per pet (e.g., 45)">
                </div>
                <span class="error mt-2" id="weight_of_pets_error"></span>
            </div>
            <div class="form-group">
                <label class="fw-bold">Pet Restrictions</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter any pet restrictions the Landlord requires. Include any HOA or insurance-related restrictions if applicable.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="breed_restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-shield-dog" placeholder="Enter pet restrictions (e.g., No pit bulls)">
                </div>
                <span class="error mt-2" id="breed_restrictions_error"></span>
            </div>
            <!-- Service Animal -->
            {{-- <div class="form-group">
            <label class="fw-bold">Service Animal:</label>
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
            <div class="input-cover">
                <select wire:model="support_animal" class="form-control has-icon" data-icon="fa-solid fa-heart">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <span class="error mt-2" id="support_animal_error"></span>
        </div> --}}
        </div>
    @endif
@endif
</div>

<script>
    // ── MLS structural multi-select field selectors ─────────────────────────
    var _landlordMlsFieldSelectors = {
        roof_type:             ['#roof_type_landlord_res'],
        exterior_construction: ['#exterior_construction_landlord_res'],
        foundation:            ['#foundation_landlord_res'],
    };

    function syncAllSelect2BeforeSave() {
        Object.entries(_landlordMlsFieldSelectors).forEach(function([fieldId, selectors]) {
            selectors.forEach(function(selector) {
                var el = $(selector);
                if (el.length && el.hasClass('select2-hidden-accessible')) {
                    @this.set(fieldId, el.val() || []);
                }
            });
        });
    }

    document.addEventListener('livewire:load', function() {
        const select = document.getElementById('property_type');
        let isDropdownOpen = false;

        // Store original options without emojis
        const originalOptions = {
            'Residential Property': 'Residential Property',
            'Commercial Property': 'Commercial Property'
        };

        // Initialize options with plain text (no emojis)
        Array.from(select.options).forEach(option => {
            if (option.value && originalOptions[option.value]) {
                option.text = originalOptions[option.value];
            }
        });

        // ── Initialize new structural Select2 fields ──────────────────────
        // Delegated change handlers ($(document).on) survive DOM replacement by Livewire.
        var _structuralCfgs = [
            { selector: '#roof_type_landlord_res',             field: 'roof_type',             otherWrapperId: 'other_roof_type_landlord_res_wrapper' },
            { selector: '#exterior_construction_landlord_res', field: 'exterior_construction', otherWrapperId: 'other_exterior_construction_landlord_res_wrapper' },
            { selector: '#foundation_landlord_res',            field: 'foundation',            otherWrapperId: 'other_foundation_landlord_res_wrapper' },
        ];

        _structuralCfgs.forEach(function(cfg) {
            $(document).on('change', cfg.selector, function() {
                var values = $(cfg.selector).val() || [];
                @this.set(cfg.field, values);
                var wrapper = document.getElementById(cfg.otherWrapperId);
                if (wrapper) {
                    wrapper.style.display = values.includes('Other') ? '' : 'none';
                }
            });
        });

        window.initStructuralSelect2 = function() {
            _structuralCfgs.forEach(function(cfg) {
                var $el = $(cfg.selector);
                if (!$el.length || $el.hasClass('select2-hidden-accessible')) return;
                $el.select2({ placeholder: 'Select', allowClear: true, width: '100%', closeOnSelect: false });
                var current = $el.val() || [];
                var wrapper = document.getElementById(cfg.otherWrapperId);
                if (wrapper) {
                    wrapper.style.display = current.includes('Other') ? '' : 'none';
                }
            });
        }

        initStructuralSelect2();

        // Re-init after Livewire re-renders (wire:ignore protects values, but Select2 may need re-attachment)
        window.addEventListener('landlord:propertyTypeChanged', function() {
            setTimeout(initStructuralSelect2, 100);
        });
    });

    // Re-init after Livewire updates (handles tab navigation re-renders)
    document.addEventListener('livewire:update', function() {
        setTimeout(initStructuralSelect2, 50);
    });
</script>
