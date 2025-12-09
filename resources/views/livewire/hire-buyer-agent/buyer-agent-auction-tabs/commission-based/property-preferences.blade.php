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

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">


        <div>
            <strong>🏡 Describe the type of property the Buyer is seeking — including the preferred location, property
                style, features, and must-have amenities. </strong>
        </div>
    </div>
</div>


<div class="form-group mb-3">
    <label class="fw-bold">Acceptable Cities:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the cities where the Buyer is looking to purchase a property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>



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

<!-- Acceptable Counties -->
<div class="form-group mb-3">
    <label class="fw-bold">Acceptable Counties:<span class="text-danger">*</span>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the counties where the Buyer is looking to purchase a property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover position-relative">
        <input type="text" wire:model="newCounty" wire:keydown.enter.prevent="selectCountySuggestion()"
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
</div>
<div class="form-group">
    <label class="fw-bold">Acceptable State:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the state where the Buyer is looking to purchase a property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


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
    <label class="fw-bold">Acceptable Property Type:<span class="text-danger">*</span>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of property the Buyer is looking to purchase.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>

    <div class="input-cover">
        <select wire:model="property_type" id="property_type" class="form-control has-icon"
            data-icon="fa-solid fa-building" required>
            <option value="">Select</option>
            <option value="Residential" data-display="Residential"> Residential</option>
            <option value="Income" data-display="Income">Income</option>
            <option value="Commercial" data-display="Commercial">Commercial</option>
            <option value="Business" data-display="Business">Business</option>
            <option value="Vacant Land" data-display="Vacant Land">Vacant Land</option>
        </select>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

<div class="form-group mt-3">
    <label class="fw-bold">Acceptable Property Style:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the Buyer’s preferred architectural or structural styles.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <select wire:model="property_items" id="property_items" class="form-control has-icon select2-multiple"
            data-icon="fa-solid fa-home input-icon2" @if (!$property_type) disabled @endif multiple
            required>
            @if ($property_type === 'Residential')
                @foreach ($property_items_buyer as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Income')
                @foreach ($property_items_buyer as $item)
                    @if (str_contains($item['class'], 'income-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial')
                @foreach ($property_items_buyer as $item)
                    @if (str_contains($item['class'], 'commercial-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Business')
                @foreach ($property_items_buyer as $item)
                    @if (str_contains($item['class'], 'business-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Opportunity')
                @foreach ($property_items_buyer as $item)
                    @if (str_contains($item['class'], 'opportunity-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Vacant Land')
                @foreach ($property_items_buyer as $item)
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
<div class="form-group other_property_items d-none">
    <div class="input-cover">
        <input type="text" wire:model="other_property_items" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter other land use (e.g., Solar Farm, RV Park, Conservation Easement)">
    </div>
    <span class="error mt-2" id="other_property_items_error"></span>
</div>

<div class="form-group mt-3 business_type d-none">
    <label class="fw-bold">Business Type:</label>
    <div class="input-cover">
        <select wire:model="business_type" id="business_type" class="form-control has-icon"
            data-icon="fa-solid fa-home">
            <option value="">Select</option>
            @foreach ($business_type as $item)
                <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
            @endforeach
        </select>
    </div>
</div>
<!-- Other Business Type Input -->
<div class="form-group other-business-input d-none">
    <div class="input-cover">
        <input type="text" wire:model="other_business_type" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter other business type (e.g., Recording Studio, Event Venue, Repair Shop)">
    </div>
</div>

@if ($property_type !== 'Vacant Land')
    <div class="form-group" wire:ignore>
        <label class="fw-bold">Acceptable Property Conditions:

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select the property conditions that are acceptable to the Buyer.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>

        </label>

        <div class="input-cover">
            <select wire:model="condition_prop_buyer" id="condition_prop_buyer"
                class="condition_prop_buyer form-control has-icon select2-multiple"
                data-icon="fa-solid fa-screwdriver-wrench input-icon2" multiple>
                @foreach ($property_condition as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="condition_prop_error"></span>
    </div>
@endif
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
@if ($property_type === 'Residential')
    <div class="form-group">
        <label class="fw-bold"> Minimum Bedrooms Needed:<span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the minimum number of bedrooms the Buyer requires.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

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
                data-icon="fa-solid fa-bed" placeholder="Enter minimum bedrooms needed (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bedrooms_error"></span>
    </div>
@endif

<!-- Minimum Bathrooms Needed -->
@if (in_array($property_type, ['Residential', 'Commercial', 'Business']))

    <div class="form-group">
        <label class="fw-bold">Minimum Bathrooms Needed:<span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the minimum number of bathrooms the Buyer requires.">
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
    <div class="form-group other_bathrooms d-none">
        {{-- <label class="fw-bold">Minimum Bathrooms Needed:</label> --}}
        <div class="input-cover">
            <input type="number" wire:model="other_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath" placeholder="Enter minimum bathrooms needed (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bathrooms_error"></span>
    </div>
@endif

<!-- Minimum Heated Sqft Needed -->
@if ($property_type === 'Residential' or $property_type === 'Commercial' or $property_type === 'Business')
    <div class="form-group">
        <label class="fw-bold"> Minimum Heated SqFt Needed:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the minimum heated (climate-controlled) square footage the Buyer requires.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


        <div class="input-cover">
            <input type="text" wire:model="minimum_heated_square" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter minimum heated square footage needed (e.g., 1000)"
                 data-error-id="minimum_heated_square_error" oninput="validateInput(this)"
                onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
        <span class="error mt-2" id="minimum_heated_square_error"></span>
    </div>
@endif

<!-- Minimum Net LeaseableSqft Needed -->
{{-- @if ($property_type === 'Commercial' or $property_type === 'Business')
    <div class="form-group">
        <label class="fw-bold">Minimum Net Leasable SqFt Needed:</label>
        <div class="input-cover">
            <input type="number" wire:model="minimum_leaseable" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter net leasable square footage (e.g., 1500)">
        </div>
        <span class="error mt-2" id="minimum_leaseable_error"></span>
    </div>
@endif --}}

<!-- Minimum Total Acreage Needed -->
<div class="form-group">
    <label class="fw-bold">Minimum Total Acreage Needed:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify the minimum land area (in acres) the Buyer requires. ">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


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
@if ($property_type === 'Residential' or $property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Carport Needed:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer needs a carport, and specify the number of spaces.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


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
    <div class="form-group d-none" id="other-carport-needed">
        <label class="fw-bold">Number of Carport Spaces Needed:

        </label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_carport_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of carport spaces needed (e.g., 1)">
        </div>
        <span class="error mt-2" id="other_carport_needed_error"></span>
    </div>
@endif

<!-- Garage Spaces Needed (Residential Only) -->
@if ($property_type === 'Residential' or $property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Garage Needed:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer needs a garage, and specify the number of spaces.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


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
    <div class="form-group d-none" id="other-garage-needed">
        <label class="fw-bold">Number of Garage Spaces Needed:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model="other_garage_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces needed (e.g., 1)">
        </div>
        <span class="error mt-2" id="other_garage_needed_error"></span>
    </div>
@endif

<!-- Garage/Parking Spaces Needed -->
@if ($property_type === 'Commercial' or $property_type === 'Business')
    <div class="form-group">
        <label class="fw-bold">Garage/Parking Features Needed:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the garage or parking features the Buyer requires.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


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
<div class="form-group d-none" id="garage_parking_spaces_option_wrapper">
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
    <div class="input-cover">

        <input type="text" wire:model="other_parking_space_wrapper" id="other_parking_space"
            class="form-control has-icon" data-icon="fa-solid fa-warehouse"
            placeholder="Enter garage/parking features needed (e.g., Tandem Parking, Gated Entry, Shared Driveway) ">
    </div>
</div>

<!-- Pool Needed -->
@if ($property_type === 'Residential' or $property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Pool Needed:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether a pool is a required feature. If 'Yes,' you’ll be prompted to select the preferred type(s): Private or Community.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


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
    <label class="fw-bold">View Preference Needed:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify any preferred views. If “Other” is selected, describe the type of view the Buyer desires. ">
            <i class="fa-solid fa-circle-info"></i>
        </span>

    </label>


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
            data-icon="fa-solid fa-tree" placeholder="Enter view preference (e.g., Lake, Desert, Courtyard)">
    </div>
    <span class="error mt-2" id="other_preferences_error"></span>
</div>

<!-- Eligibility/Interest in Leasing in 55-and-Over Communities -->
@if ($property_type === 'Residential')
    <div class="form-group">
        <label class="fw-bold">Age-Restricted Community:

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer is eligible for and interested in purchasing in an age-restricted community under federal housing laws. 55+ communities typically require at least one occupant to be 55 or older, while 62+ communities require all residents to be 62 or older.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


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

<!-- Non-Negotiable Amenities and Property Features -->
<div class="form-group" wire:ignore.self>
    <label class="fw-bold">Non-Negotiable Amenities and Property Features:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the essential amenities or features the Buyer requires in the property. If “Other” is selected, specify any additional must-have amenities or features.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <select wire:model="non_negotiable_amenities" id="non_negotiable_amenities"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock input-icon2"
            @if (!$property_type) disabled @endif multiple>
            <option value="">Select</option>
            @if ($property_type === 'Residential' or $property_type === 'Income')
                @foreach ($non_negotialble_terms as $item)
                    @if (str_contains($item['class'], 'residential-length'))
                        <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
                    @endif
                @endforeach
            @elseif ($property_type === 'Commercial' or $property_type === 'Business')
                @foreach ($non_negotialble_terms as $item)
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

    <div class="input-cover">

        @if ($property_type === 'Residential' or $property_type === 'Income')
            <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
                data-icon="fa-solid fa-lock"
                placeholder="Enter non-negotiable amenities or features (e.g., Sauna, EV Charger, Outdoor Kitchen)">
        @elseif ($property_type === 'Commercial' or $property_type === 'Business')
            <input type="text" wire:model="other_non_negotiable_amenities" class="form-control has-icon"
                data-icon="fa-solid fa-lock"
                placeholder="Enter non-negotiable amenities or features (e.g., Rooftop Access, Backup Generator, Freight Elevator) ">
        @endif

    </div>
</div>

{{-- 1) Business & Real Estate Purchase Requirements (Business Opportunity only) --}}
@if ($property_type === 'Business')
    <div class="form-group">
        <label class="fw-bold">
            Business &amp; Real Estate Purchase Requirements:<span class="text-danger">*</span>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select whether the Buyer is looking to purchase both the real estate and the business, or just the business operation.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


        <div class="input-cover">
            <select wire:model="real_estate_purchase" class="form-control has-icon" data-icon="fas fa-building"
                required>
                <option value="">Select</option>
                <option value="Real Estate Building and Business">
                    Real Estate Building and Business
                </option>
                <option value="Business Only">
                    Business Only
                </option>
            </select>
        </div>
        <span class="error mt-2" id="real_estate_purchase_error"></span>
    </div>
@endif

{{-- 2) Required Property or Business Assets (Income, Commercial, Business Opportunity) --}}
@if (in_array($property_type, ['Income', 'Commercial', 'Business']))
    <div class="form-group">
        <label class="fw-bold">
            Required Property or Business Assets:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the assets that must be included (e.g., equipment, intellectual property, rights, or licenses).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


        <div class="input-cover">
            <select wire:model="assets" id="assets" class="form-control has-icon select2-multiple"
                data-icon="fas fa-building input-icon2" multiple>
                <option value="Goodwill and Business Name">
                    Goodwill and Business Name
                </option>
                <option value="Furniture, Fixtures, and Equipment (as per attached inventory)">
                    Furniture, Fixtures, and Equipment (as per attached inventory)
                </option>
                <option value="Advertising Materials">
                    Advertising Materials
                </option>
                <option value="Contract Rights">
                    Contract Rights
                </option>
                <option value="Leases">
                    Leases
                </option>
                <option value="Licenses">
                    Licenses
                </option>
                <option value="Rights under any Agreement for Interests">
                    Rights under any Agreement for Interests
                </option>
                <option value="Other">Other</option>
            </select>
        </div>
        <span class="error mt-2" id="assets_error"></span>
    </div>
@endif
{{-- “Other” text input --}}
<div class="form-group other_assets d-none" wire:ignore>
    <div class="input-cover">
        <input type="text" wire:model="assets_other" class="form-control has-icon" data-icon="fas fa-building"
            placeholder="Enter any included assets (e.g., Inventory, Customer Lists, Trademarks, Software Rights)">
    </div>
    <span class="error mt-2" id="assets_other_error"></span>
</div>

{{-- 3) Income-only fields --}}
@if ($property_type === 'Income')

    {{-- Pets for Income Property (moved above Income Property Criteria) --}}
    <div class="form-group">
        <label class="fw-bold">Pets:<span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer has pets. If so, enter details including the number, type, breed, weight, and whether the pet is a service animal or an emotional support animal.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover">
            <select wire:model="pets" id="pets_income" class="form-control has-icon" data-icon="fa-solid fa-paw"
                required>
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
        <span class="error mt-2" id="pets_error"></span>
    </div>

    @if ($pets === 'Yes')
        <div id="pet-details-income">
            <div class="form-group">
                <label class="fw-bold">Number of Pets:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the total number of pets you currently have (e.g., 2).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="number" wire:model="number_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets (e.g., 2)">
                </div>
                <span class="error mt-2" id="number_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Type of Pets:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the types of pets you own (e.g., Dog, Cat).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="text" wire:model="type_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-cat" placeholder="Enter types of pets (e.g., Dog, Cat)">
                </div>
                <span class="error mt-2" id="type_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Breed of Pets:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the breed(s) of your pets (e.g., Labrador, Siamese).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="text" wire:model="breed_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-dog" placeholder="Enter breeds of pets (e.g., Labrador, Siamese)">
                </div>
                <span class="error mt-2" id="breed_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Weight of Pets (lbs):</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the weight of your pet(s) in pounds.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="text" wire:model="weight_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-weight" placeholder="Enter the weight of pets (e.g., 30 lbs, 50 lbs)">
                </div>
                <span class="error mt-2" id="weight_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Service Animal:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select if any of your pets are trained service animals.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <select wire:model="service_animal" class="form-control has-icon" data-icon="fa-solid fa-heart">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <span class="error mt-2" id="service_animal_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Emotional Support Animal:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select if any of your pets are emotional support animals.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <select wire:model="emotional_support_animal" class="form-control has-icon"
                        data-icon="fa-solid fa-heart">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <span class="error mt-2" id="emotional_support_animal_error"></span>
            </div>
        </div>
    @endif

    {{-- a) Income Property Criteria --}}
    <div class="form-group">
        <label class="fw-bold">Income Property Criteria:</label>
        <div class="input-cover">
            <input type="text" wire:model="property_criteria" class="form-control has-icon"
                data-icon="fas fa-building" placeholder="Please Enter the Buyer's Preferred Income Property Criteria">
        </div>
    </div>

    {{-- b) Acceptable Unit Sizes --}}
    <div class="form-group">
        <label class="fw-bold">Acceptable Number of Units:

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select the unit types or layouts that would be acceptable to the Buyer, such as studios, lofts, or multi-bedroom configurations.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
        </label>

        <div class="input-cover">
            <select wire:model="unit_size" id="unit_size" class="form-control has-icon" data-icon="fas fa-building">
                <option value="">Select</option>
                <option value="1-4 Units">1–4 Units</option>
                <option value="5-10 Units">5–10 Units</option>
                <option value="10+ Units">10+ Units</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    @if ($unit_size === 'Other')
        <div class="form-group">
            <label class="fw-bold">Acceptable Unit Sizes:

            </label>
            <div class="input-cover">
                <input type="text" wire:model="unit_size_other" class="form-control has-icon"
                    data-icon="fas fa-building" placeholder="Enter acceptable number of units (e.g., 15 Units)">
            </div>
        </div>
    @endif

    {{-- c) Minimum Total Number of Units Needed --}}
    {{-- <div class="form-group">
        <label class="fw-bold">Minimum Total Number of Units Needed:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum number of rental units the property must have.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="number_of_unit" id="number_of_unit" class="form-control has-icon"
                data-icon="fa-solid fa-home">
                <option value="">Select</option>
                @foreach ($unit_types_buyer as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="number_of_unit_error"></span>
    </div>
    @if ($number_of_unit === 'Other')
        <div class="form-group">
            <label class="fw-bold">Enter Minimum Total Units:</label>
            <div class="input-cover">
                <input type="number" wire:model="number_of_unit_other" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter minimum number of units needed (e.g., 4)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
    @endif --}}

@endif

{{-- 4) Net Income & Cap Rate (Income, Commercial, Business Opportunity) --}}

{{-- Pets (Residential only - Income has it above Income Property Criteria) --}}
@if ($property_type === 'Residential')
    <div class="form-group">
        <label class="fw-bold">Pets:<span class="text-danger">*</span>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer has pets. If so, enter details including the number, type, breed, weight, and whether the pet is a service animal or an emotional support animal.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover">
            <select wire:model="pets" id="pets" class="form-control has-icon" data-icon="fa-solid fa-paw"
                required>
                <option value="">Select</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
        <span class="error mt-2" id="pets_error"></span>
    </div>
@endif

@if ($pets === 'Yes')
    <div id="pet-details">
        {{-- Number of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Number of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the total number of pets you currently have (e.g., 2).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="number" wire:model="number_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets (e.g., 2)">
            </div>
            <span class="error mt-2" id="number_of_pets_error"></span>
        </div>

        {{-- Type of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Type of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the types of pets you own (e.g., Dog, Cat).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="text" wire:model="type_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-cat" placeholder="Enter types of pets (e.g., Dog, Cat)">
            </div>
            <span class="error mt-2" id="type_of_pets_error"></span>
        </div>

        {{-- Breed of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Breed of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the breed(s) of your pets (e.g., Labrador, Siamese). If you have multiple pets with different breeds, list them all.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="text" wire:model="breed_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" placeholder="Enter breeds of pets (e.g., Labrador, Siamese)">
            </div>
            <span class="error mt-2" id="breed_of_pets_error"></span>
        </div>

        {{-- Weight of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Weight of Pets (lbs):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the weight of your pet(s) in pounds (e.g., 30 lbs, 50 lbs). If you have multiple pets, you can list their weights individually.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="text" wire:model="weight_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-weight" placeholder="Enter the weight of pets (e.g., 30 lbs, 50 lbs)">
            </div>
            <span class="error mt-2" id="weight_of_pets_error"></span>
        </div>

        {{-- Service Animal --}}
        <div class="form-group">
            <label class="fw-bold">Service Animal:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select if any of your pets are trained service animals (e.g., for assistance with disabilities).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <select wire:model="service_animal" class="form-control has-icon" data-icon="fa-solid fa-heart">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <span class="error mt-2" id="service_animal_error"></span>
        </div>

        {{-- Emotional Support Animal --}}
        <div class="form-group">
            <label class="fw-bold">Emotional Support Animal:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select if any of your pets are emotional support animals, providing therapeutic support.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <select wire:model="emotional_support_animal" class="form-control has-icon"
                    data-icon="fa-solid fa-heart">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
            <span class="error mt-2" id="emotional_support_animal_error"></span>
        </div>
    </div>
@endif

@if ($property_type != 'Residential' && $property_type != 'Vacant Land')
    <div>
        <div class="form-group">
            <label class="fw-bold">Minimum Annual Net Income Needed:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the minimum annual net income (after expenses) the property must generate.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>

                <input type="text" wire:model="minimum_annual_net_income" class="form-control"
                    placeholder="Enter minimum annual net income needed (e.g., 50,000)"
                    data-error-id="minimum_annual_net_income_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
    </div>

    <div>
        <div class="form-group">
            <label class="fw-bold">Minimum Cap Rate Needed:

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the lowest capitalization rate (Cap Rate) that the Buyer is willing to accept based on the property's income relative to its purchase price. (Cap Rate = Net Income ÷ Purchase Price)">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <input type="text" wire:model="minimum_cap_rate" class="form-control" style="padding-left: 12px;"
                    placeholder="Enter minimum cap rate needed (e.g., 6.5)"
                    data-error-id="minimum_cap_rate_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                <span class="input-group-text-seller">%</span>
            </div>
        </div>
    </div>
@endif
@if (in_array($property_type, ['Income']))
    <div class="form-group">
        <label class="fw-bold">Acceptable Unit Type:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the types of units included in the sale. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


        <div class="input-cover">
            <select wire:model="number_of_unit_type"
                class="number_of_unit_type form-control has-icon select2-multiple"
                data-icon="fa-solid fa-home input-icon2" multiple>
                @foreach ($unit_types as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    @if (is_array($number_of_unit_type) && in_array('Other', $number_of_unit_type))
        <div class="form-group">
            <label class="fw-bold">Other Unit Type:</label>
            <div class="input-cover">
                <input type="text" wire:model="number_of_unit_type_other" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter acceptable unit types (e.g., Live/Work Unit, Boarding House, Accessory Dwelling Unit)">
            </div>
            <span class="error mt-2" id="number_of_unit_type_error"></span>
        </div>
    @endif
@endif
@if ($property_type !== 'Residential' && $property_type !== 'Vacant Land')
<div class="form-group">
    <label class="fw-bold">Additional Details:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any other important preferences, requirements, or notes not covered above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <textarea wire:model="preferance_details" class="form-control" rows="4"
            style="padding: 10px; font-size: 16px;" placeholder="Enter any additional preferences "></textarea>
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
