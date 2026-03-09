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
<h3>Property Preferences</h3>
<!-- Acceptable Cities -->
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🏠 Describe the type of property the Tenant is seeking — including the preferred location, property style, features, and must-have amenities.
            </strong>
        </div>
    </div>
</div>

<!-- Number of Pet(s) -->
{{-- <div class="form-group">
    <label class="fw-bold">Unit Number:</label>
    <div class="input-cover">
        <input type="text" wire:model="number_of_unit" class="form-control has-icon" data-icon="fa-solid fa-home"
            placeholder="Enter unit number">
    </div>
    <span class="error mt-2" id="number_of_unit_error"></span>
</div> --}}

{{-- <div class="form-group mb-3">
    <label class="fw-bold mb-2">City:<span class="text-danger">*</span></label>
    <div class="input-cover position-relative">
        <input type="text" wire:model="newCity" wire:keydown.enter.prevent="selectCitySuggestion()"
            wire:keydown.arrow-up.prevent="decrementHighlight('City')"
            wire:keydown.arrow-down.prevent="incrementHighlight('City')"
            class="form-control has-icon @error('newCity') is-invalid @enderror" data-icon="fas fa-city"
            autocomplete="off" placeholder="Enter city">

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
</div>

<div class="form-group mb-3">
    <label class="fw-bold mb-2">County:<span class="text-danger">*</span></label>
    <div class="input-cover position-relative">
        <input type="text" wire:model="newCounty" wire:keydown.enter.prevent="selectCountySuggestion()"
            wire:keydown.arrow-up.prevent="decrementHighlight('County')"
            wire:keydown.arrow-down.prevent="incrementHighlight('County')"
            class="form-control has-icon @error('newCounty') is-invalid @enderror" data-icon="fa-solid fa-map"
            autocomplete="off" placeholder="Enter county">

        @if (count($countySuggestions) > 0)
            <div class="autocomplete-dropdown shadow-sm">
                <ul class="list-group">
                    @foreach ($countySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedCountyIndex === $index ? 'bg-light' : '' }}"
                            wire:click="selectCountySuggestion('{{ $suggestion }}')"
                            wire:key="county-suggestion-{{ $index }}">
                            <i class="fas fa-map me-2 text-muted"></i>
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('newCounty')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div> --}}

<div class="form-group mb-3">
    <label class="fw-bold">Acceptable Cities:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the cities where the Tenant is willing to lease a property.<br>Selecting a city will automatically populate the associated county and state when available.">
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

<!-- Acceptable Counties -->
<div class="form-group mb-3">
    <label class="fw-bold">Acceptable Counties:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the counties where the Tenant is willing to lease a property.<br>If a county is selected, the state will automatically populate.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover position-relative">
        <input type="text" wire:model.debounce.300ms="newCounty" wire:keydown.enter.prevent="selectCountySuggestion()"
            wire:keydown.arrow-up.prevent="decrementHighlight('County')"
            wire:keydown.arrow-down.prevent="incrementHighlight('County')"
            class="form-control has-icon @error('newCounty') is-invalid @enderror" data-icon="fa-solid fa-map"
            autocomplete="off" placeholder="Enter county or counties">

        <!-- County Suggestions Dropdown -->
        @if (count($countySuggestions) > 0)
            <div class="autocomplete-dropdown-counties shadow-sm">
                <ul class="list-group">
                    @foreach ($countySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedCountyIndex === $index ? 'bg-light' : '' }}"
                            wire:click="selectCountySuggestion('{{ $suggestion }}')"
                            wire:key="county-suggestion-{{ $index }}">
                            <i class="fas fa-map me-2 text-muted"></i>
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('newCounty')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <!-- Display added counties -->
    <div class="mt-1 counties-container">
        @if (count($counties) > 0)
            @foreach ($counties as $index => $county)
                <span class="badge bg-primary rounded-pill d-inline-flex align-items-center" wire:key="county-badge-{{ $index }}">
                    <i class="fas fa-map me-2"></i>
                    {{ $county }}
                    <button type="button" class="byo-pill-remove ms-2"
                        wire:click="removeCounty({{ $index }})" aria-label="Remove">&times;</button>
                </span>
            @endforeach

        @endif
    </div>
    <span class="error mt-2" id="counties_error"></span>
</div>

<div class="form-group">
    <label class="fw-bold">Acceptable State:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the state where the Tenant is looking to lease a property.<br>This may be automatically filled based on the counties selected.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover position-relative">
        <input type="text" wire:model="state" wire:keydown.enter.prevent="selectStateSuggestion"
            wire:keydown.arrow-up="decrementHighlight('state')" wire:keydown.arrow-down="incrementHighlight('state')"
            class="form-control has-icon @error('state') is-invalid @enderror" data-icon="fa-solid fa-flag-usa"
            autocomplete="off" placeholder="Enter state" required>

        @if (count($stateSuggestions) > 0)
            <div class="autocomplete-dropdown-counties shadow-sm">
                <ul class="list-group">
                    @foreach ($stateSuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedStateIndex === $index ? 'bg-light' : '' }}"
                            wire:click="selectStateSuggestion('{{ $suggestion }}')"
                            wire:key="state-suggestion-{{ $index }}">
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('state')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Property Type Dropdown -->

<div class="form-group">
    <label class="fw-bold"> Acceptable Property Type:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of property the Tenant is looking to lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


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
    <label class="fw-bold"> Acceptable Property Styles:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the Tenant’s preferred architectural or structural styles.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover" wire:ignore>
        @php
            $selectedPropertyItems = $this->property_items ?? [];
            if (is_string($selectedPropertyItems)) {
                $selectedPropertyItems = json_decode($selectedPropertyItems, true) ?? [];
            }
        @endphp

        <select id="property_items" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-home input-icon2" multiple
            data-property-type="{{ $property_type }}"
            data-selected="{{ json_encode($selectedPropertyItems) }}">
            @if ($property_type === 'Residential Property')
                @foreach ($property_items as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}" {{ in_array($item['name'], $selectedPropertyItems) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial Property')
                @foreach ($property_items as $item)
                    @if (str_contains($item['class'], 'commercial-length'))
                        <option value="{{ $item['name'] }}" {{ in_array($item['name'], $selectedPropertyItems) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @endif
        </select>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

@php
    $conditionOptions = $property_condition;
    if (isset($user_type) && $user_type === 'landlord' && isset($property_condition_landlord)) {
        $conditionOptions = $property_condition_landlord;
        if (!empty($condition_prop_buyer) && is_array($condition_prop_buyer)) {
            $optionNames = array_column($conditionOptions, 'name');
            foreach ($condition_prop_buyer as $saved) {
                if (!in_array($saved, $optionNames)) {
                    $conditionOptions[] = ['name' => $saved];
                }
            }
        }
    }
@endphp
<div class="form-group">
    <label class="fw-bold">
        @if (isset($user_type) && $user_type === 'landlord')
            Property Condition:
        @else
            Acceptable Property Conditions:
        @endif
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="{{ isset($user_type) && $user_type === 'landlord' ? 'Select the condition of the property.' : 'Select the property conditions that are acceptable to the Tenant.' }}">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>

    <div class="input-cover" wire:key="condition-prop-buyer-wrapper" wire:ignore>
              <select id="condition_prop_buyer"
                class="condition_prop_buyer form-control has-icon select2-multiple"
                data-icon="fa-solid fa-screwdriver-wrench input-icon2" multiple
                style="visibility:hidden;height:0;overflow:hidden">
            @php
                $displayMapping = [
                    'Updated/Renovated' => 'Updated / Renovated',
                    'Partially Updated' => 'Partially Updated',
                    'Older but Clean' => 'Older but Clean & Well Maintained',
                    'No Preference' => 'No Preference',
                    'Partially updated (some older finishes OK)' => 'Partially Updated',
                    'Older but clean & well maintained' => 'Older but Clean & Well Maintained',
                    'No preference (open to any condition)' => 'No Preference',
                ];
            @endphp
            @foreach ($conditionOptions as $row_pt)
                @php
                    $displayName = $displayMapping[$row_pt['name']] ?? $row_pt['name'];
                @endphp
                <option value="{{ $row_pt['name'] }}">{{ $displayName }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="condition_prop_error"></span>
</div>

<!-- Other Property Condition Input (Hidden by Default) -->
<div class="form-group other_property_condition d-none">
    <label class="fw-bold">Other Property Condition:</label>
    <div class="input-cover">
        <input type="text" wire:model="other_property_condition" class="form-control has-icon"
            data-icon="fa-solid fa-home">
    </div>
    <span class="error mt-2" id="other_property_condition_error"></span>
</div>

<!-- Minimum Bedrooms Needed -->
<div wire:key="tenant-property-fields-{{ $property_type ?? 'none' }}">
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Minimum Bedrooms Needed:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the minimum number of bedrooms the Tenant requires.">
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
@endif

@if ($property_type === 'Residential Property')
<!-- Other Bedrooms Input (Hidden by Default) -->
<div class="form-group other_bedrooms @if (($this->bedrooms ?? '') !== 'Other') d-none @endif">
    {{-- <label class="fw-bold">Minimum Bedrooms Needed:</label> --}}
    <div class="input-cover">
        <input type="number" wire:model="other_bedrooms" class="form-control has-icon" data-icon="fa-solid fa-bed"
            placeholder="Enter minimum bedrooms needed (e.g., 11)">
    </div>
    <span class="error mt-2" id="other_bedrooms_error"></span>
</div>
@endif

<!-- Minimum Bathrooms Needed -->
<div class="form-group">
    <label class="fw-bold">Minimum Bathrooms Needed:<span class="text-danger">*</span>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the minimum number of bathrooms the Tenant requires.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>

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
<div class="form-group other_bathrooms @if (($this->bathrooms ?? '') !== 'Other') d-none @endif">
    <div class="input-cover">
        <input type="number" wire:model="other_bathrooms" class="form-control has-icon"
            data-icon="fa-solid fa-bath" placeholder="Enter minimum bathrooms needed (e.g., 11)">
    </div>
    <span class="error mt-2" id="other_bathrooms_error"></span>
</div>

<!-- Minimum Heated Sqft Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold"> Minimum Heated SqFt Needed:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum heated (climate-controlled) square footage the Tenant requires.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="minimum_heated_square" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter minimum heated square footage needed (e.g., 1000)"

                 data-error-id="minimum_heated_square_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)"
                >
        </div>
        <span class="error mt-2" id="minimum_heated_square_error"></span>
    </div>
@endif

<!-- Minimum Net LeaseableSqft Needed -->
@if ($property_type === 'Commercial Property')
    <div class="form-group">
        <label class="fw-bold">Minimum Net Leasable Sqft Needed:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum net leaseable square footage the Tenant requires, excluding shared areas like hallways or lobbies.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="minimum_leaseable" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter net leasable square footage (e.g., 1500)"

                data-error-id="minimum_leaseable_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="minimum_leaseable_error"></span>
    </div>
@endif

{{-- <div class="form-group">
    <label class="fw-bold">Minimum Total Sqft: </label>

     <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
      title="Enter the minimum total square footage for the property, including all usable and non-usable spaces.">
    <i class="fa-solid fa-circle-info"></i>
     </span>

    <div class="input-cover">
        <input type="number" wire:model="total_square_feet" class="form-control has-icon"
            data-icon="fa-solid fa-ruler" placeholder="Enter total square footage (e.g., 2000)">
    </div>
</div> --}}

{{-- <div class="form-group mb-3">
    <label class="fw-bold mb-2">Sqft Heated Source:</label>
    <div class="input-cover">
        <select wire:model="sqft_heated_source" class="form-control has-icon" data-icon="fas fa-ruler">
            <option value="">Select source</option>
            <option value="Appraisal">Appraisal</option>
            <option value="Builder">Builder</option>
            <option value="Measured">Measured</option>
            <option value="Owner Provided">Owner Provided</option>
            <option value="Public Records">Public Records</option>
        </select>
    </div>
</div> --}}

<!-- Minimum Total Acreage Needed -->
{{-- <div class="form-group">
    <label class="fw-bold">Minimum Total Acreage Needed:</label>

     <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
      title="Specify the minimum land area you require for a property.">
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
</div> --}}

<!-- View Preference Needed -->
{{-- <div class="form-group">
    <label class="fw-bold">Appliances:</label>
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

<div class="form-group" id="other_appliances" style="display: none;">
    <div class="input-cover">
        <input type="text" wire:model="other_appliances" class="form-control has-icon"
            data-icon="fa-solid fa-plug" placeholder="Enter other appliances (e.g., warming drawer) ">
    </div>
    <span class="error mt-2" id="other_appliances_error"></span>
</div> --}}

<!-- Furnishings Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Furnishings Needed:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the Tenant requires the property to be furnished, optional, partially furnished, turnkey, or unfurnished.">
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
        <label class="fw-bold">Carport Needed:

        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Tenant needs a carport, and specify the number of spaces.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="carport_needed" id="carport-needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                <option value="Optional">Optional</option>
            </select>
        </div>

    </div>

    <!-- Carport Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group {{ ($carport_needed === 'Yes' || (!empty($other_carport_needed) && $other_carport_needed !== '0')) ? '' : 'd-none' }}" id="other-carport-needed">
        <label class="fw-bold">Number of Carport Spaces Needed:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_carport_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of carport spaces needed (e.g., 1)">
        </div>
        <span class="error mt-2" id="other_carport_needed_error"></span>
    </div>
@endif

<!-- Garage Spaces Needed (Residential Only) -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Garage Needed:
        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Tenant needs a garage, and specify the number of spaces.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="garage_needed" id="garage-needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                <option value="Optional">Optional</option>
            </select>
        </div>
    </div>


    <!-- Garage Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group {{ ($garage_needed === 'Yes' || (!empty($other_garage_needed) && $other_garage_needed !== '0')) ? '' : 'd-none' }}" id="other-garage-needed">
        <label class="fw-bold">Number of Garage Spaces Needed:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_garage_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces needed (e.g., 1)">
        </div>
        <span class="error mt-2" id="other_garage_needed_error"></span>
    </div>
@endif

<!-- Garage/Parking Spaces Needed -->
@if ($property_type === 'Commercial Property')
    <div class="form-group">
        <label class="fw-bold">Garage/Parking Features Needed:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the garage or parking features the Tenant requires.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="garage_parking_spaces" id="garage_parking_spaces" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                {{-- <option value="Optional">Optional</option> --}}
            </select>
        </div>
        <span class="error mt-2" id="garage_parking_spaces_error"></span>
    </div>
@endif
<!-- Garage/Parking Spaces Type Dropdown -->
<div class="form-group d-none" id="garage_parking_spaces_option_wrapper" wire:ignore>
    <label class="fw-bold">Garage/Parking Features:</label>

    <div class="input-cover">

        @php
            $selectedGarageOptions = $this->garage_parking_spaces_option ?? [];
            if (is_string($selectedGarageOptions)) {
                $selectedGarageOptions = json_decode($selectedGarageOptions, true) ?? [];
            }
        @endphp
        <select id="garage_parking_spaces_option"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse input-icon2" multiple>

            @foreach ($garage_parking_spaces as $row_pt)
                <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $selectedGarageOptions) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="garage_parking_spaces_option_error"></span>
</div>
<!-- Other Parking Space Text Input -->
<div class="form-group d-none" id="other_parking_space_wrapper">
    {{-- <label class="fw-bold">Other Garage/Parking Features:</label> --}}
    <div class="input-cover">

        <input type="text" wire:model="other_parking_space_wrapper" id="other_parking_space"
            class="form-control has-icon" data-icon="fa-solid fa-warehouse" placeholder="Enter garage/parking features needed (e.g., Tandem Parking, Gated Entry, Shared Driveway)">
    </div>
</div>

<!-- Pool Needed -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Pool Needed:
        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether a pool is a required feature. If “Yes,” you’ll be prompted to select the preferred type(s): Private or Community.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="pool_needed" id="pool_needed" class="form-control has-icon"
                data-icon="fa-solid fa-water">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                <option value="Optional">Optional</option>
            </select>
        </div>
    </div>
@endif

@if ($property_type === 'Residential Property' && $pool_needed === 'Yes')
    <!-- Pool Type Selection (Shows only for Residential and if "Yes" is selected) -->
    <div class="form-group" id="pool_type_wrapper">
        <label class="fw-bold">Select Pool Type:</label>
        <div class="form-check">
            <input type="checkbox" wire:model="pool_type.private" id="pool_private" class="form-check-input">
            <label class="form-check-label" for="pool_private">Private</label>
        </div>
        <div class="form-check">
            <input type="checkbox" wire:model="pool_type.community" id="pool_community" class="form-check-input">
            <label class="form-check-label" for="pool_community">Community</label>
        </div>
    </div>
@endif
<!-- View Preference Needed -->

<div class="form-group">
    <label class="fw-bold">View Preference Needed:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title='Select the Tenant’s preferred property view. If "Other" is selected, describe the view.'>
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover" wire:ignore>
        <select id="view_preference" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-tree input-icon2" multiple>
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
    <div class="input-cover">
        <input type="text" wire:model="other_preferences" class="form-control has-icon"
            data-icon="fa-solid fa-tree" placeholder="Enter view preference (e.g., Lake, Desert, Courtyard)">
    </div>
    <span class="error mt-2" id="other_preferences_error"></span>
</div>


<!-- Eligibility/Interest in Leasing in 55-and-Over Communities -->
@if ($property_type === 'Residential Property')
    <div class="form-group">
        <label class="fw-bold">Age-Restricted Community:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the Tenant is eligible for and interested in leasing in an age-restricted community under federal housing laws. 55+ communities typically require at least one occupant to be 55 or older, while 62+ communities require all occupants to be 62 or older.">
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
        <label class="fw-bold">Non-Negotiable Amenities and Property Features:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the essential amenities or features the Tenant requires in the property. If “Other” is selected, specify any additional must-have amenities or features.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


        @php
            $selectedAmenities = $this->non_negotiable_amenities ?? [];
            if (is_string($selectedAmenities)) {
                $selectedAmenities = json_decode($selectedAmenities, true) ?? [];
            }
        @endphp
        <div class="input-cover" wire:ignore>
            <select id="non_negotiable_amenities"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock input-icon2"
                @if (!$property_type) disabled @endif multiple>
                @if (in_array($property_type, ['Residential Property']))
                    @foreach ($non_negotialble_terms as $item)
                        @if (str_contains($item['class'], 'residential-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], $selectedAmenities) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif(in_array($property_type, ['Commercial Property']))
                    @foreach ($non_negotialble_terms as $item)
                        @if (str_contains($item['class'], 'commercial-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], $selectedAmenities) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @endif
            </select>
        </div>
        <span class="error mt-2" id="non_negotiable_amenities_error"></span>
    </div>
    <!-- Other Non-Negotiable Amenities and Property Features Input (Hidden by Default) -->
    <div class="form-group other_non_negotiable_amenities @if (!in_array('Other', $non_negotiable_amenities ?? [])) d-none @endif">
        <div class="input-cover">
            @if (in_array($property_type, ['Residential Property']))
                <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-lock"
                    placeholder="Enter non-negotiable amenities or features (e.g., Sauna, EV Charger, Outdoor Kitchen)">
            @elseif(in_array($property_type, ['Commercial Property']))
                <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-lock"
                    placeholder="Enter non-negotiable amenities or features (e.g., Rooftop Access, Backup Generator, Freight Elevator)">
            @endif
        </div>
        <span class="error mt-2" id="other_non_negotiable_amenities_error"></span>
    </div>
</div>
