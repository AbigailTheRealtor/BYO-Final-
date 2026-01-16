<style>
    .input-cover .input-icon2 {
        z-index: 1 !important;
    }

    .input-cover .select2 .selection .select2-selection--multiple {
        padding-left: 44px !important;
        padding-bottom: 0 !important;
    }

    .input-cover .select2 .selection .select2-selection--multiple input {
        font-size: 1rem !important;
    }
</style>
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
    <label class="fw-bold"> Street Address:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the street address of the property (e.g., 123 Main Street). City, County, and State will be entered separately below.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="address" class="form-control has-icon" data-icon="fa-solid fa-map-pin"
            placeholder="Enter street address (e.g., 123 Main Street)" required>
    </div>
</div>
<div class="alert alert-warning mt-3 p-2 small">
    <strong>🛡️ On the listing, only your City, County, State, and ZIP code are displayed.</strong> Your full address is shared with the Agent you hire only after an Agent is selected, helping protect your privacy while still allowing Agents to understand your general location.
</div>

<!-- Property City -->
<div class="form-group mb-3">
    <label class="fw-bold">City:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the city where the property is located.<br>Selecting a city will automatically populate the county and state.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover position-relative">
        <input type="text" wire:model.live.debounce.300ms="property_city"
            wire:keydown.enter.prevent="selectPropertyCitySuggestion()"
            wire:keydown.arrow-up.prevent="decrementPropertyCityHighlight()"
            wire:keydown.arrow-down.prevent="incrementPropertyCityHighlight()"
            class="form-control has-icon @error('property_city') is-invalid @enderror" 
            data-icon="fas fa-city"
            autocomplete="off" 
            placeholder="Enter city"
            required>

        @if (!empty($propertyCitySuggestions) && count($propertyCitySuggestions) > 0)
            <div class="autocomplete-dropdown shadow-sm">
                <ul class="list-group">
                    @foreach ($propertyCitySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ ($highlightedPropertyCityIndex ?? -1) === $index ? 'bg-light' : '' }}"
                            wire:click="selectPropertyCitySuggestion('{{ $suggestion }}')"
                            wire:key="property-city-suggestion-{{ $index }}">
                            <i class="fas fa-city me-2 text-muted"></i>
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
    <label class="fw-bold">State:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the state where the property is located.<br>This will be automatically populated when a city or county is selected.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="property_state" 
            class="form-control has-icon @error('property_state') is-invalid @enderror" 
            data-icon="fa-solid fa-flag-usa"
            placeholder="Enter state" 
            required>
        @error('property_state')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Property County -->
<div class="form-group mb-3">
    <label class="fw-bold">County:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the county where the property is located.<br>This may be automatically populated when a city is selected.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="property_county" 
            class="form-control has-icon @error('property_county') is-invalid @enderror" 
            data-icon="fa-solid fa-map"
            placeholder="Enter county" 
            required>
        @error('property_county')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Property ZIP Code -->
<div class="form-group mb-3">
    <label class="fw-bold">ZIP Code:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the ZIP code where the property is located.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="text" wire:model="property_zip" 
            class="form-control has-icon @error('property_zip') is-invalid @enderror" 
            data-icon="fa-solid fa-map-pin"
            placeholder="Enter ZIP code" 
            required
            maxlength="10">
        @error('property_zip')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Acceptable Cities -->

@if ($cityFieldVisible)
    <div class="form-group mb-3">
        <label class="fw-bold mb-2"> City:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the city where the rental property is located. Required for search and filtering accuracy.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover position-relative">
            <input type="text" wire:model.debounce.300ms="newCity" wire:keydown.enter.prevent="selectCitySuggestion()"
                wire:keydown.arrow-up.prevent="decrementHighlight('City')"
                wire:keydown.arrow-down.prevent="incrementHighlight('City')"
                class="form-control has-icon @error('newCity') is-invalid @enderror" data-icon="fas fa-city"
                autocomplete="off" placeholder="Enter city or cities">

            <!-- City Suggestions Dropdown -->
            @if (count($citySuggestions) > 0)
                <div class="autocomplete-dropdown shadow-sm">
                    <ul class="list-group">
                        @foreach ($citySuggestions as $index => $suggestion)
                            <li class="list-group-item {{ $highlightedCityIndex === $index ? 'bg-light' : '' }}"
                                wire:click="selectCitySuggestion('{{ $suggestion }}')"
                                wire:key="city-suggestion-{{ $index }}">
                                <i class="fas fa-city me-2 text-muted"></i>
                                {{ $suggestion }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @error('newCity')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>

        <!-- Display added cities -->
        <div class="mt-1 cities-container">
            @if (count($cities) > 0)
                @foreach ($cities as $index => $city)
                    <span class="badge bg-primary rounded-pill d-inline-flex align-items-center" wire:key="city-badge-{{ $index }}">
                        <i class="fas fa-city me-2"></i>
                        {{ $city }}
                        <button type="button" class="byo-pill-remove ms-2"
                            wire:click="removeCity({{ $index }})" aria-label="Remove">&times;</button>
                    </span>
                @endforeach

            @endif
        </div>
    </div>
@endif


<!-- Acceptable State -->
@if ($stateFieldVisible)
    <div class="form-group">
        <label class="fw-bold"> State:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the state where the rental property is located. Required for search and filtering accuracy.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover position-relative">
            <input type="text" wire:model="state" class="form-control has-icon" data-icon="fa-solid fa-flag-usa"
                required wire:keydown.arrow-up="decrementHighlight('state')"
                wire:keydown.arrow-down="incrementHighlight('state')"
                wire:keydown.enter.prevent="selectStateSuggestion" autocomplete="off" placeholder="Enter state"
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
        <label class="fw-bold">Zip Code:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the ZIP code for the rental property. Required for search and filtering accuracy.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover position-relative">
            <input type="text" wire:model="zip_code" wire:keydown.enter.prevent="selectZipCodeSuggestion()"
                wire:keydown.arrow-up.prevent="decrementHighlight('ZipCode')"
                wire:keydown.arrow-down.prevent="incrementHighlight('ZipCode')"
                class="form-control has-icon @error('zip_code') is-invalid @enderror" data-icon="fas fa-map-pin"
                autocomplete="off" placeholder="Enter one or more ZIP codes">

            @if (count($zipCodeSuggestions) > 0)
                <div class="autocomplete-dropdown shadow-sm">
                    <ul class="list-group">
                        @foreach ($zipCodeSuggestions as $index => $suggestion)
                            <li class="list-group-item {{ $highlightedZipCodeIndex === $index ? 'bg-light' : '' }}"
                                wire:click="selectZipCodeSuggestion('{{ $suggestion }}')"
                                wire:key="zip-suggestion-{{ $index }}">
                                <i class="fas fa-map-pin me-2 text-muted"></i>
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
                        <i class="fas fa-map-pin me-2"></i>
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
    <label class="fw-bold"> Property Type:<span class="text-danger">*</span></label>

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
    <label class="fw-bold"> Property Style:<span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the specific architectural or structural style of the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">

        <select wire:model="property_items" class="form-control has-icon" data-icon="fa-solid fa-home input-icon2"
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

<div class="form-group">
    <label class="fw-bold">Property Condition:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the current condition of the property being offered for lease.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="condition_prop" id="condition_prop" class="form-control has-icon"
            data-icon="fa-solid fa-screwdriver-wrench" required>
            <option value="">Select</option>
            @foreach ($property_condition as $row_pt)
                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
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

@if ($property_type === 'Residential Property')
    <div class="form-group" >
        <label class="fw-bold"> Bedrooms:<span class="text-danger">*</span></label>

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
        <label class="fw-bold"> Bathrooms :<span class="text-danger">*</span></label>

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
    <!-- Other Bathrooms Input (Hidden by Default) -->
    <div class="form-group other_bathrooms d-none">
        {{-- <label class="fw-bold">Minimum Bathrooms Needed:</label> --}}
        <div class="input-cover">
            <input type="number" wire:model="other_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath" placeholder="Enter number of bathrooms (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bathrooms_error"></span>
    </div>
@endif
<!-- Minimum Heated Sqft Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold"> Heated SqFt:<span class="text-danger">*</span></label>

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
        <label class="fw-bold"> Net Leasable SqFt:<span class="text-danger">*</span></label>
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
    <label class="fw-bold"> Total SqFt:</label>

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
        <select wire:model="sqft_heated_source" class="form-control has-icon" data-icon="fas fa-ruler">
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
    <label class="fw-bold"> Total Acreage:</label>

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
<div class="form-group">
    <label class="fw-bold">Appliances Included:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the appliances included with the property. If 'Other' is selected, enter any additional appliances.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="appliances" id="appliances" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-plug input-icon2" multiple>
            @foreach ($appliances as $row_pt)
                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="appliances_error"></span>
</div>

@if ($showOtherAppliances)
    <div class="form-group" id="other_appliances">
        <div class="input-cover">
            <input type="text" wire:model="other_appliances" class="form-control has-icon"
                data-icon="fa-solid fa-plug"
                placeholder="Enter appliances (e.g., Air Fryer Oven, Induction Cooktop, Double Oven)"
                @if (in_array('Other', $appliances)) required @endif>
        </div>
        <span class="error mt-2" id="other_appliances_error"></span>
    </div>
@endif
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

    <!-- Carport Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group d-none" id="other-carport-needed">
        <label class="fw-bold">Number of Carport Spaces:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_carport_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of carport spaces (e.g., 1) ">
        </div>
        <span class="error mt-2" id="other_carport_needed_error"></span>
    </div>
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
        <div class="input-cover">
            <select wire:ignore wire:model="garage_parking_spaces_option" id="garage_parking_spaces_option_landlord"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse input-icon2" multiple>
                @foreach ($garage_parking_spaces as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
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
                placeholder="Enter garage/parking features (e.g., Tandem Parking, Gated Entry, Shared Driveway)">
        </div>
    </div>

@endif

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

@if ($pool_needed === 'Yes')
    <!-- Pool Type Selection (Shows only if "Yes" is selected) -->
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

<div class="form-group">
    <label class="fw-bold">View:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the property’s view. If “Other” is selected, describe the view.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:ignore wire:model="view_preference" id="view_preference"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-tree input-icon2" multiple>
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
<div class="form-group" id="other_preferences" style="display: {{ $this->is_other_visible ? 'block' : 'none' }}">
    <label class="fw-bold">Other View:</label>
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
<div class="form-group" wire:ignore.self>
    <label class="fw-bold"> Amenities and Property Features:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the amenities and property features included with the property. If 'Other' is selected, enter any additional amenities or features.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="non_negotiable_amenities" id="non_negotiable_amenities"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock input-icon2"
            @if (!$property_type) disabled @endif multiple>
            <option value="">Select</option>
            @if ($property_type === 'Residential Property')
                @foreach ($non_negotialble_terms_landlord as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial Property')
                @foreach ($non_negotialble_terms_landlord as $item)
                    @if (str_contains($item['class'], 'commercial-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
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
            @if ($property_type === 'Residential Property') placeholder="Enter amenities or property features (e.g., Sauna, EV Charger, Outdoor Kitchen)"
        @elseif($property_type === 'Commercial Property')
            placeholder="Enter amenities or property features (e.g., Rooftop Access, Backup Generator, Freight Elevator)" @endif>

    </div>
    <span class="error mt-2" id="other_non_negotiable_amenities_error"></span>
</div>

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
                        placeholder="Enter breed restrictions (e.g., no Pit Bulls)">
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
                        data-icon="fa-solid fa-shield-dog" placeholder="Enter pet restrictions (e.g., No Pit Bulls)">
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

<script>
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
    });
</script>
