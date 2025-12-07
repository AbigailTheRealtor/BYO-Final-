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
<h3>Property Details </h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🏠 Describe the property being offered for sale — including location, size, style, layout, and key
                features.
            </strong>
        </div>
    </div>
</div>
<!-- Property Address -->
<div class="form-group mb-3">
    <label class="fw-bold"> Property Address:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the address of the property being offered for sale.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover position-relative">
        <input type="text" wire:model="address" class="form-control has-icon" data-icon="fa-solid fa-map-pin"
            placeholder="Enter property address" wire:keydown.arrow-up="decrementHighlight('address')"
            wire:keydown.arrow-down="incrementHighlight('address')" wire:keydown.enter.prevent="selectAddressSuggestion"
            autocomplete="off" required>

        @if (count($addressSuggestions) > 0)
            <div class="autocomplete-dropdown">
                <ul class="list-group">
                    @foreach ($addressSuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedAddressIndex === $index ? 'active' : '' }}"
                            wire:click="selectAddressSuggestion('{{ $suggestion }}')">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

</div>
<div class="alert alert-warning mt-3 p-2 small">
    <strong> 🛡️ Privacy Notice: </strong> Your full property address is only visible to the platform admin. On the
    public listing, only your City, County, State, and ZIP code will be displayed. This helps protect your privacy
    while still allowing Agents to understand your general location.
</div>
@if ($property_type != 'Vacant Land')
    <div>
        <!-- Number of Pet(s) -->
        <div class="form-group">
            <label class="fw-bold">Unit Number:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the full unit identifier (e.g., “2B”, “PH-1”), if applicable.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="number_of_unit" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter unit number">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
    </div>
@endif
<!-- Acceptable Cities -->

{{-- ///////////////////////// --}}

@if ($countyFieldVisible)

    <div class="form-group mb-3">
        <label class="fw-bold">County:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the county where the property being offered for sale.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover position-relative">
            <input type="text" wire:model="newCounty" wire:keydown.enter.prevent="selectCountySuggestion()"
                wire:keydown.arrow-up.prevent="decrementHighlight('County')"
                wire:keydown.arrow-down.prevent="incrementHighlight('County')"
                class="form-control has-icon @error('newCounty') is-invalid @enderror" data-icon="fa-solid fa-map"
                autocomplete="off" placeholder="Enter county">

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
    </div>

@endif

@if ($cityFieldVisible)
    <div class="form-group mb-3">
        <label class="fw-bold">City:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the cities where you’re interested in renting a property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover position-relative">
            <input type="text" wire:model="newCity" wire:keydown.enter.prevent="selectCitySuggestion()"
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

@if ($zipCodeFieldVisible)
    <div class="form-group mb-3">
        <label class="fw-bold">ZIP Code:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the ZIP code(s) where you’re interested in renting a property.">
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
                    <span class="badge bg-primary rounded-pill" wire:key="zip-badge-{{ $index }}">
                        <i class="fas fa-map-pin me-2"></i>
                        {{ $zip }}
                        <button type="button" class="btn-close btn-close-white ms-2"
                            wire:click="removeZipCode({{ $index }})" aria-label="Remove"></button>
                    </span>
                @endforeach
            @endif
        </div>
    </div>
@endif

<!-- Acceptable Counties -->

@if ($stateFieldVisible)

    <div class="form-group">
        <label class="fw-bold">State:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the state where you’re looking to rent.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover position-relative">
            <input type="text" wire:model="state" wire:keydown.enter.prevent="selectStateSuggestion"
                wire:keydown.arrow-up="decrementHighlight('state')"
                wire:keydown.arrow-down="incrementHighlight('state')"
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
@endif

{{-- /////////////////////// --}}
<div class="form-group">
    <label class="fw-bold"> Property Type:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the type of property being offered for sale.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="property_type" id="property_type" class="form-control has-icon"
            data-icon="fa-solid fa-building" required>
            <option value="">Select</option>
            <option value="Residential" data-display="Residential"> Residential</option>
            <option value="Income" data-display="Income">Income</option>
            <option value="Commercial" data-display="Commercial">Commercial</option>
            <option value="Business" data-display="Business">Business</option>
            {{-- <option value="Opportunity" data-display="Opportunity">Opportunity</option> --}}
            <option value="Vacant Land" data-display="Vacant Land">Vacant Land</option>
        </select>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

<div class="form-group mt-3">
    <label class="fw-bold"> Property Style:<span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the specific architectural or structural style of the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">

        <select wire:model="property_items" id="property_style_select" class="form-control has-icon"
            data-icon="fa-solid fa-home" @if (!$property_type) disabled @endif required>
            <option value="">Select</option>

            @if ($property_type === 'Residential')
                @foreach ($property_items_seller as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Income')
                @foreach ($property_items_seller as $item)
                    @if (str_contains($item['class'], 'income-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial')
                @foreach ($property_items_seller as $item)
                    @if (str_contains($item['class'], 'commercial-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Business')
                @foreach ($property_items_seller as $item)
                    @if (str_contains($item['class'], 'business-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Opportunity')
                @foreach ($property_items_seller as $item)
                    @if (str_contains($item['class'], 'opportunity-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Vacant Land')
                @foreach ($property_items_seller as $item)
                    @if (str_contains($item['class'], 'vacant-land-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @endif
        </select>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

<!-- Other Property Condition Input -->
<div class="form-group other_property_items_seller d-none">
    <div class="input-cover">
        <input type="text" wire:model="other_property_items" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter other land use (e.g., Solar Farm, RV Park, Conservation Easement)">
    </div>
    <span class="error mt-2" id="other_property_items_error"></span>
</div>

<div class="form-group mt-3 business_type_seller d-none">
    <label class="fw-bold">Business Type:</label>
    <div class="input-cover">
        <select wire:model="business_type" id="business_type_seller" class="form-control has-icon"
            data-icon="fa-solid fa-home">
            <option value="">Select</option>
            @foreach ($business_type as $item)
                <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
            @endforeach
        </select>
    </div>
</div>
<!-- Other Business Type Input -->
<div class="form-group other-business-input_seller d-none">
    <div class="input-cover">
        <input type="text" wire:model="other_business_type" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter other business type (e.g., Recording Studio, Event Venue, Repair Shop)">
    </div>
</div>

@if ($property_type != 'Vacant Land')

    <div class="form-group">
        <label class="fw-bold">Property Condition:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the current condition of the property being offered for sale.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="condition_prop" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench" required>
                <option value="">Select</option>
                @foreach ($property_condition_seller as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
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
@endif
<!-- Minimum Bedrooms Needed -->
@if ($property_type === 'Residential')
    <div class="form-group">
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
    <div class="form-group other_bedrooms d-none">
        {{-- <label class="fw-bold">Minimum Bedrooms Needed:</label> --}}
        <div class="input-cover">
            <input type="number" wire:model="other_bedrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bed" placeholder="Enter number of bedrooms (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bedrooms_error"></span>
    </div>
@endif

<!-- Other Bedrooms Input (Hidden by Default) -->

<!-- Minimum Bathrooms Needed -->
@if (in_array($property_type, ['Residential', 'Business', 'Commercial']))

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

<!-- Minimum Heated SqFt Needed -->
{{-- @if (in_array($property_type, ['Residential', 'Business', 'Commercial']))
    <div class="form-group" wire:ignore wire:key="heated-square-select-{{ $property_type }}">
        <label class="fw-bold"> Heated SqFt:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of climate-controlled (heated/cooled) interior space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="minimum_heated_square" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter heated square footage (e.g., 1000)" required>
        </div>
        <span class="error mt-2" id="minimum_heated_square_error"></span>
    </div>
@endif --}}

@if (in_array($property_type, ['Residential', 'Business', 'Commercial']))
    <div class="form-group" wire:ignore wire:key="heated-square-select-{{ $property_type }}">
        <label class="fw-bold">Heated SqFt:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of climate-controlled (heated/cooled) interior space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" id="minimum_heated_square" wire:model="minimum_heated_square"
                class="form-control has-icon" data-icon="fa-solid fa-ruler"
                placeholder="Enter heated square footage (e.g., 1000)" data-error-id="minimum_heated_square_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)" required>
        </div>
        <span class="error mt-2" id="minimum_heated_square_error"></span>
    </div>
@endif

@if ($property_type != 'Vacant Land')
    <div class="form-group">
        <label class="fw-bold">Total SqFt:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the full square footage of the property, including all usable and non-usable areas.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" id="total_square_feet" wire:model="total_square_feet"
                class="form-control has-icon" data-icon="fa-solid fa-ruler"
                placeholder="Enter total square footage (e.g., 2000)" data-error-id="total_square_feet_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
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
@endif

<!-- Minimum Net LeaseableSqFt Needed -->
@if ($property_type === 'Commercial')
    <div class="form-group">
        <label class="fw-bold">Minimum Net Leaseable SqFt Needed:</label>

        <div class="input-cover">
            <input type="text" wire:model="minimum_leaseable" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter net leasable square footage (e.g., 1,500)"
                data-error-id="minimum_leaseable_error" oninput="validateInput(this)" onblur="reformatNumber(this)"
                onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="minimum_leaseable_error"></span>
    </div>
@endif
<!-- Minimum Total Acreage Needed -->
<div class="form-group">
    <label class="fw-bold"> Total Acreage:<span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the total land area of the property, measured in acres.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover">
        <select wire:model="total_acreage" id="total_acreage" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined" required>
            <option value="">Select</option>
            @foreach ($acreageRes as $row_pt)
                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="total_acreage_error"></span>
</div>
<!-- View Preference Needed -->
@if (in_array($property_type, ['Residential', 'Business', 'Commercial', 'Income']))

    <div class="form-group" wire:ignore wire:key="appliance-select-{{ $property_type }}">
        <label class="fw-bold">Appliances Included:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the appliances included with the property. If 'Other' is selected, enter any additional appliances.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover" wire:ignore>
            <select wire:model="appliances" id="appliances" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-plug input-icon2" multiple>
                @foreach ($appliances as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="appliances_error"></span>
    </div>

@endif
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
{{-- @if ($property_type === 'Residential')
                                <div class="form-group">
                                    <label class="fw-bold">Furnishings Needed:<span class="text-danger">*</span></label>
                                    <div class="input-cover">
                                        <select wire:model="tenant_require" id="tenant_require"
                                            class="form-control has-icon" data-icon="fa-solid fa-couch" required>
                                            <option value="">Select</option>
                                            @foreach ($tenant_require as $row_pt)
                                                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <span class="error mt-2" id="furnishings_needed_error"></span>
                                </div>
                            @endif --}}

<!-- Carport Spaces Needed (Residential Only) -->
@if ($property_type === 'Residential')
    <div class="form-group">
        <label c lass="fw-bold">Carport:
        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the property has a carport. If yes, enter the number of available spaces. ">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="carport_needed" id="carport-needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
                {{-- <option value="Optional">Optional</option> --}}
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
@if ($property_type === 'Residential')
    <div class="form-group">
        <label class="fw-bold">Garage:
        </label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the property has a garage. If yes, enter the number of available spaces.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="garage_needed" id="garage-needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>

    <!-- Garage Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group d-none" id="other-garage-needed">
        <label class="fw-bold">Number of Garage Spaces:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_garage_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces (e.g., 2)">
        </div>
        <span class="error mt-2" id="other_garage_needed_error"></span>
    </div>
@endif

<!-- Garage/Parking Spaces Needed -->

@if (in_array($property_type, ['Business', 'Commercial']))

    {{-- @if ($property_type === 'Business') --}}
    {{-- <div class="form-group">
        <label class="fw-bold">Garage/Parking Features:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of garage or parking features available.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="garage_parking_spaces" id="garage_parking_spaces" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" required>
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
        <span class="error mt-2" id="garage_parking_spaces_error"></span>
    </div> --}}

    <!-- Garage/Parking Spaces Type Dropdown -->
    {{-- <div class="form-group" id="garage_parking_spaces_option_wrapper">
        <label class="fw-bold">Garage/Parking Features:</label>

        <div class="input-cover">
            <select wire:model="garage_parking_spaces_option" id="garage_parking_spaces_option"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse input-icon2" multiple>
                @foreach ($garage_parking_spaces as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="garage_parking_spaces_option_error"></span>
    </div>

    <!-- Other Parking Space Text Input -->
    <div class="form-group d-none" id="other_parking_space_wrapper">
        <label class="fw-bold">Other Garage/Parking Features:</label>
        <div class="input-cover">
            <input type="text" wire:model="other_parking_space_wrapper" id="other_parking_space"
                class="form-control has-icon" data-icon="fa-solid fa-warehouse"
                placeholder="Enter other garage/parking features (e.g., Tandem Parking, Gated Entry, Shared Driveway)">
        </div>
    </div> --}}

    <div class="form-group" wire:ignore wire:key="parking-features-select-{{ $property_type }}">
        <label class="fw-bold">Garage/Parking Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of garage or parking features available. If “Other” is selected, enter any additional garage or parking features in the provided field.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="garage_parking_spaces_option" id="garage_parking_spaces_option_landlord"
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
@if ($property_type === 'Residential' or $property_type === 'Income')
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
                {{-- <option value="Optional">Optional</option> --}}
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
<!-- View Preference Needed -->

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
<div class="form-group" id="other_preferences" style="display: {{ $is_other_visible ? 'block' : 'none' }}">
    <div class="input-cover">
        <input type="text" wire:model="other_preferences" class="form-control has-icon"
            data-icon="fa-solid fa-tree" placeholder="Enter view (e.g., Lake, Desert, Courtyard)">
    </div>
    <span class="error mt-2" id="other_preferences_error"></span>
</div>

<!-- Eligibility/Interest in Leasing in 55-and-Over Communities -->
@if ($property_type === 'Residential')
    <div class="form-group">
        <label class="fw-bold">Age-Restricted Community:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate if the property is part of an age-restricted community under federal housing laws. 55+ communities typically require at least one occupant to be 55 or older, while 62+ housing requires all residents to be 62 or older.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="leasing_55_plus" id="leasing_55_plus" class="form-control has-icon"
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
{{-- @if (in_array($property_type, ['Residential', 'Income', 'Commercial'])) --}}

@if ($property_type != 'Vacant Land')

    <!-- Non-Negotiable Amenities and Property Features -->
    <div class="form-group" wire:ignore.self>
        <label class="fw-bold">Amenities and Property Features:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the amenities and property features included with the property. If 'Other' is selected, enter any additional amenities or features.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="non_negotiable_amenities" id="non_negotiable_amenities"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock input-icon2"
                @if (!$property_type) disabled @endif multiple>
                <option value="">Select</option>
                @if (in_array($property_type, ['Residential', 'Income']))
                    @foreach ($non_negotialble_terms_landlord as $item)
                        @if (str_contains($item['class'], 'residential-length'))
                            <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif(in_array($property_type, ['Business', 'Commercial']))
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
    <!-- Other Non-Negotiable Amenities and Property Features Input (Hidden by Default) -->
    <div class="form-group other_non_negotiable_amenities @if (!in_array('Other', $non_negotiable_amenities ?? [])) d-none @endif">
        <div class="input-cover">
            @if (in_array($property_type, ['Residential', 'Income']))
                <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-lock"
                    placeholder="Enter amenities or property features (e.g., Sauna, Ev Charger, Outdoor Kitchen)">
            @elseif(in_array($property_type, ['Business', 'Commercial']))
                <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-lock"
                    placeholder="Enter amenities or property features (e.g., Rooftop Access, Backup Generator, Freight Elevator)">
            @endif
        </div>
        <span class="error mt-2" id="other_non_negotiable_amenities_error"></span>
    </div>
@endif
@if (in_array($property_type, ['Residential', 'Income']))
    <div class="form-group">
        <label class="fw-bold">Pets Allowed:
        </label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether pets are allowed. If so, provide details on pet types, number, weight limits, and any restrictions.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="pets" id="pets" class="form-control has-icon" data-icon="fa-solid fa-paw">
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
    </div>
@endif
@if ($pets === 'Yes')
    <!-- Pet Details (Hidden by Default) -->
    <div id="pet-details">
        <!-- Number of Pet(s) -->
        <div class="form-group">
            <label class="fw-bold">Number of Pets Allowed:</label>
            <div class="input-cover">
                <input type="number" wire:model="number_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets allowed (e.g., 2)">
            </div>
            <span class="error mt-2" id="number_of_pets_error"></span>
        </div>
        <!-- Type of Pet(s) -->
        <div class="form-group">
            <label class="fw-bold">Acceptable Pet Types:</label>
            <div class="input-cover">
                <input type="text" wire:model="type_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-cat" placeholder="Enter acceptable pet types (e.g., Dog, Cat)">
            </div>
            <span class="error mt-2" id="type_of_pets_error"></span>
        </div>
        <!-- Breed of Pet(s) -->
        {{-- <div class="form-group">
            <label class="fw-bold">Breed of Pet(s):</label>
            <div class="input-cover">
                <input type="text" wire:model="breed_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" placeholder="Enter breed(s) of pets (e.g., Labrador, Siamese)">
            </div>
            <span class="error mt-2" id="breed_of_pets_error"></span>
        </div> --}}

        <!-- Weight of Pet(s) -->
        <div class="form-group">
            <label class="fw-bold">Maximum Weight Per Pet (lbs):</label>
            <div class="input-cover">
                <input type="number" wire:model="weight_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-weight" placeholder="Enter maximum weight per pet (e.g., 45)">
            </div>
            <span class="error mt-2" id="weight_of_pets_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Pet Restrictions</label>

            <div class="input-cover">
                <input type="text" wire:model="breed_restrictions" class="form-control has-icon"
                    data-icon="fa-solid fa-shield-dog" placeholder="Enter pet restrictions (e.g., No Pit Bulls)">
            </div>
            <span class="error mt-2" id="breed_restrictions_error"></span>
        </div>
    </div>
@endif
@if ($property_type === 'Business')
    <div class="form-group">
        <label class="fw-bold">Business & Real Estate Purchase Requirements:<span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the purchase includes both the real estate and the business, or only the business operation.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="real_estate_purchase" class="form-control has-icon" data-icon="fas fa-building"
                required>
                <option value="">Select</option>
                <option value="Real Estate Building and Business">Real Estate Building and Business </option>
                <option value="Business Only">Business Only </option>
            </select>
        </div>
        <span class="error mt-2" id="pets_error"></span>
    </div>
@endif
@if (in_array($property_type, ['Commercial', 'Business', 'Income']))

    <div>
        <div class="form-group">
            <label class="fw-bold">Included Property or Business Assets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the assets included in the sale (e.g., equipment, intellectual property, rights, or licenses).">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            <div class="input-cover">
                {{-- no wire:ignore, wire:model binds directly --}}
                <select wire:model="assets" id="assets" class="form-control has-icon select2-multiple"
                    data-icon="fas fa-building input-icon2" multiple>
                    <option value="Goodwill and Business Name">Goodwill and Business Name</option>
                    <option value="Furniture, Fixtures, and Equipment (as per attached inventory)">
                        Furniture, Fixtures, and Equipment (as per attached inventory)
                    </option>
                    <option value="Advertising Materials">Advertising Materials</option>
                    <option value="Contract Rights">Contract Rights</option>
                    <option value="Leases">Leases</option>
                    <option value="Licenses">Licenses</option>
                    <option value="Rights under any Agreement for Interests">
                        Rights under any Agreement for Interests
                    </option>
                    <option value="Other">Other</option>
                </select>
            </div>
            @error('assets')
                <span class="error text-danger">{{ $message }}</span>
            @enderror
        </div>

        {{-- this block truly only appears when $assets_visible is true --}}
        @if ($assets_visible)
            <div class="form-group other_assets mt-3">
                <div class="input-cover">
                    <input type="text" wire:model.defer="assets_other" class="form-control has-icon"
                        data-icon="fas fa-building"
                        placeholder=" Enter any included assets (e.g., Inventory, Customer Lists, Trademarks, Software Rights)">
                </div>
                @error('assets_other')
                    <span class="error text-danger">{{ $message }}</span>
                @enderror
            </div>
        @endif
    </div>

@endif
@if ($property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Total Number of Units:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of rental or income-generating units on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="unit_number" class="form-control has-icon"
                data-icon="fa-solid fa-layer-group" placeholder="Enter total number of units (e.g., 4)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Total Number of Buildings:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of separate buildings located on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="number" wire:model="unit_buildings" class="form-control has-icon"
                data-icon="fa-solid fa-building" placeholder="Enter total number of buildings (e.g., 2)">
        </div>
    </div>
@endif
@if (in_array($property_type, ['Income', 'Commercial', 'Business']))
    <div class="form-group">
        <label class="fw-bold"> Annual Net Income:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the annual net income the property or business generates after all operating expenses.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <span class="input-group-text-seller">$</span>

            <input type="text" wire:model="minimum_annual_net_income" class="form-control has-icon"
                placeholder="Enter annual net income (e.g., 85000)"

                 data-error-id="minimum_annual_net_income_error"
        oninput="validateInput(this)"
        onblur="reformatNumber(this)"
        onpaste="handlePaste(event)"
                >
        </div>
        <span class="error mt-2" id="minimum_annual_net_income_error"></span>
    </div>
@endif
@if (in_array($property_type, ['Income', 'Commercial', 'Business']))
    <div class="form-group">
        <label class="fw-bold"> Cap Rate:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the capitalization rate (Cap Rate), which reflects the property’s income potential relative to its price.Formula: Cap Rate = Net Operating Income ÷ Purchase Price">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="text" wire:model="minimum_cap_rate" class="form-control has-icon percentage-value-set"
                placeholder="Enter cap rate (e.g., 6.5)"
                 data-error-id="minimum_cap_rate_error"
        oninput="validateInput(this)"
        onblur="reformatNumber(this)"
        onpaste="handlePaste(event)"
                >

            <span class="input-group-text-seller">%</span>

        </div>
        <span class="error mt-2" id="minimum_cap_rate_error"></span>
    </div>
@endif

{{-- @if ($property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Unit Type:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the types of units included in the sale. ">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="number_of_unit" id="number_of_unit" class="form-control has-icon"
                data-icon="fa-solid fa-home">
                <option value="">Select</option>
                @foreach ($unit_types as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="number_of_unit_error"></span>
    </div>
    @if ($number_of_unit === 'Other')
        <div class="form-group">
            <label class="fw-bold">Units Type: </label>
            <div class="input-cover">
                <input type="number" wire:model="number_of_unit_other" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter units type (e.g., 4)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
    @endif
    @if ($number_of_unit && $number_of_unit !== 'Other')
        <div class="form-group">
            <label class="fw-bold">Beds / Unit: </label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of bedrooms included in this unit type.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="beds_unit" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter number of bedrooms (e.g., 2)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
        <div class="form-group">
            <label class="fw-bold">Baths / Unit: </label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of bathrooms in this unit type. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="baths_unit" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter number of bathrooms (e.g., 1.5)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Number of Garage Spaces:</label><span class="ms-2" data-bs-toggle="tooltip"
                data-bs-html="true" title="Enter the number of garage spaces available for this unit type. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="garage_spaces" class="form-control has-icon"
                    data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces (e.g., 1)">
            </div>
            <span class="error mt-2" id="garage_spaces_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Number of Carport Spaces:</label><span class="ms-2" data-bs-toggle="tooltip"
                data-bs-html="true" title="Enter the number of carport spaces available for this unit type. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="carport_spaces" class="form-control has-icon"
                    data-icon="fa-solid fa-car" placeholder="Enter number of carport spaces (e.g., 2)">
            </div>
            <span class="error mt-2" id="carport_spaces_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Number of Units:</label><span class="ms-2" data-bs-toggle="tooltip"
                data-bs-html="true" title="Enter the total number of units of this type included in the property. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="number_of_units" class="form-control has-icon"
                    data-icon="fa-solid fa-th-large" placeholder="Enter total number of this unit type (e.g., 4)">
            </div>
            <span class="error mt-2" id="number_of_units_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Number Occupied:</label><span class="ms-2" data-bs-toggle="tooltip"
                data-bs-html="true" title="Enter how many of these units are currently occupied by tenants. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model="number_occupied" class="form-control has-icon"
                    data-icon="fa-solid fa-user-check"
                    placeholder="Enter number of units currently occupied (e.g., 3)">
            </div>
            <span class="error mt-2" id="number_occupied_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Expected Rent:</label><span class="ms-2" data-bs-toggle="tooltip"
                data-bs-html="true" title="Enter the expected monthly rent per unit for this unit type.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="number" wire:model="expected_rent" class="form-control has-icon"
                    placeholder="Enter expected monthly rent (e.g., 1500)">
            </div>
            <span class="error mt-2" id="expected_rent_error"></span>
        </div>

        <div class="form-group">
            <label class="fw-bold">Unit Type Description:</label><span class="ms-2" data-bs-toggle="tooltip"
                data-bs-html="true"
                title="Provide a short description of this unit type (e.g., layout, location, unique features).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="unit_type_description" class="form-control has-icon"
                    data-icon="fa-solid fa-align-left"
                    placeholder="Enter a brief description of this unit type (e.g., Upstairs 2/1 with balcony)">
            </div>
            <span class="error mt-2" id="unit_type_description_error"></span>
        </div>
    @endif
@endif --}}

@if ($property_type === 'Income')
    <div class="unit-types-container">
        @foreach ($unit_type_configurations as $index => $unitConfig)
            <div class="unit-type-section mb-4 p-3 border rounded">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Unit Type Configuration #{{ $index + 1 }}</h5>
                    @if ($index > 0)
                        <button type="button" class="btn btn-sm btn-danger"
                            wire:click="removeUnitType({{ $index }})">
                            Remove
                        </button>
                    @endif
                </div>

                <div class="form-group">
                    <label class="fw-bold">Unit Type:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Enter the types of units included in the sale.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <select wire:model="unit_type_configurations.{{ $index }}.unit_type"
                            class="form-control has-icon" data-icon="fa-solid fa-home">
                            <option value="">Select</option>
                            @foreach ($unit_types as $row_pt)
                                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <span class="error mt-2" id="unit_type_{{ $index }}_error"></span>
                </div>

                @if (!empty($unit_type_configurations[$index]['unit_type']))
                    <div class="form-group">
                        <label class="fw-bold">Beds / Unit: </label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the number of bedrooms included in this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number" wire:model="unit_type_configurations.{{ $index }}.beds_unit"
                                class="form-control has-icon" data-icon="fa-solid fa-home"
                                placeholder="Enter number of bedrooms (e.g., 2)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Baths / Unit: </label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the number of bathrooms in this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.baths_unit"
                                class="form-control has-icon" data-icon="fa-solid fa-home"
                                placeholder="Enter number of bathrooms (e.g., 1.5)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Number of Garage Spaces:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the number of garage spaces available for this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.garage_spaces"
                                class="form-control has-icon" data-icon="fa-solid fa-warehouse"
                                placeholder="Enter number of garage spaces (e.g., 1)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Number of Carport Spaces:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the number of carport spaces available for this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.carport_spaces"
                                class="form-control has-icon" data-icon="fa-solid fa-car"
                                placeholder="Enter number of carport spaces (e.g., 2)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Number of Other Spaces:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the number of other parking spaces available for this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.other_spaces"
                                class="form-control has-icon" data-icon="fa-solid fa-square-parking"
                                placeholder="Enter number of other spaces (e.g., 4)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Number of Units:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the total number of units of this type included in the property.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.number_of_units"
                                class="form-control has-icon" data-icon="fa-solid fa-th-large"
                                placeholder="Enter total number of this unit type (e.g., 4)">
                        </div>

                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Number Occupied:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter how many of these units are currently occupied by Tenants.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.number_occupied"
                                class="form-control has-icon" data-icon="fa-solid fa-user-check"
                                placeholder="Enter number of units currently occupied (e.g., 3)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Expected Rent:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the expected monthly rent per unit for this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <span class="input-group-text-seller">$</span>

                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.expected_rent"
                                class="form-control has-icon" placeholder="Enter expected monthly rent (e.g., 1500)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold">Unit Type Description:</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Provide a short description of this unit type (e.g., layout, location, unique features).">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="text"
                                wire:model="unit_type_configurations.{{ $index }}.unit_type_description"
                                class="form-control has-icon" data-icon="fa-solid fa-align-left"
                                placeholder="Enter a brief description (e.g., Upstairs 2/1 with balcony)">
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="form-group mt-3">
            <button type="button" class="btn btn-primary" wire:click="addUnitType">
                <i class="fa-solid fa-plus me-2"></i>Add Another Unit Type
            </button>
        </div>
    </div>
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
