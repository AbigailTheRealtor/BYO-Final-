@php
    $bedroomsRes = [
        ['name' => '1'],
        ['name' => '2'],
        ['name' => '3'],
        ['name' => '4'],
        ['name' => '5'],
        ['name' => '6'],
        ['name' => '7'],
        ['name' => '8'],
        ['name' => '9'],
        ['name' => '10'],
        ['name' => 'Other'],
    ];

    $bathroomsRes = [
        ['name' => '1'],
        ['name' => '1.5'],
        ['name' => '2'],
        ['name' => '2.5'],
        ['name' => '3'],
        ['name' => '3.5'],
        ['name' => '4'],
        ['name' => '4.5'],
        ['name' => '5'],
        ['name' => '6'],
        ['name' => '7'],
        ['name' => '8'],
        ['name' => '9'],
        ['name' => '10'],
        ['name' => 'Other'],
    ];

    $property_items_buyer = [
        // Residential (alphabetical order)
        ['name' => '½ Duplex', 'class' => 'residential-length'],
        ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
        ['name' => 'Condominium', 'class' => 'residential-length'],
        ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
        ['name' => 'Farm', 'class' => 'residential-length'],
        ['name' => 'Garage Condo', 'class' => 'residential-length'],
        ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
        ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
        ['name' => 'Modular Home', 'class' => 'residential-length'],
        ['name' => 'Single Family Residence', 'class' => 'residential-length'],
        ['name' => 'Townhouse', 'class' => 'residential-length'],
        ['name' => 'Villa', 'class' => 'residential-length'],

        // Income (alphabetical order)
        ['name' => 'Duplex', 'class' => 'income-length'],
        ['name' => 'Five or More', 'class' => 'income-length'],
        ['name' => 'Quadplex', 'class' => 'income-length'],
        ['name' => 'Triplex', 'class' => 'income-length'],

        // Business (alphabetical order)
        ['name' => 'Agriculture', 'class' => 'business-length'],
        ['name' => 'Assembly Building', 'class' => 'business-length'],
        ['name' => 'Business', 'class' => 'business-length'],
        ['name' => 'Five or More', 'class' => 'business-length'],
        ['name' => 'Hotel/Motel', 'class' => 'business-length'],
        ['name' => 'Industrial', 'class' => 'business-length'],
        ['name' => 'Mixed Use', 'class' => 'business-length'],
        ['name' => 'Office', 'class' => 'business-length'],
        ['name' => 'Restaurant', 'class' => 'business-length'],
        ['name' => 'Retail', 'class' => 'business-length'],
        ['name' => 'Warehouse', 'class' => 'business-length'],

        // Vacant Land
        ['name' => 'Agricultural', 'class' => 'vacant-land-length'],
        ['name' => 'Billboard Site', 'class' => 'vacant-land-length'],
        ['name' => 'Business', 'class' => 'vacant-land-length'],
        ['name' => 'Cattle', 'class' => 'vacant-land-length'],
        ['name' => 'Commercial', 'class' => 'vacant-land-length'],
        ['name' => 'Farm', 'class' => 'vacant-land-length'],
        ['name' => 'Fisher', 'class' => 'vacant-land-length'],
        ['name' => 'Highway Frontage', 'class' => 'vacant-land-length'],
        ['name' => 'Horses', 'class' => 'vacant-land-length'],
        ['name' => 'Industrial', 'class' => 'vacant-land-length'],
        ['name' => 'Land Fill', 'class' => 'vacant-land-length'],
        ['name' => 'Livestock', 'class' => 'vacant-land-length'],
        ['name' => 'Mixed Use', 'class' => 'vacant-land-length'],
        ['name' => 'Multi Family', 'class' => 'vacant-land-length'],
        ['name' => 'Nursery Orchard', 'class' => 'vacant-land-length'],
        ['name' => 'Pasture', 'class' => 'vacant-land-length'],
        ['name' => 'Poultry', 'class' => 'vacant-land-length'],
        ['name' => 'Ranch', 'class' => 'vacant-land-length'],
        ['name' => 'Residential', 'class' => 'vacant-land-length'],
        ['name' => 'Retail', 'class' => 'vacant-land-length'],
        ['name' => 'Row Crops', 'class' => 'vacant-land-length'],
        ['name' => 'Sod Farm', 'class' => 'vacant-land-length'],
        ['name' => 'Subdivision', 'class' => 'vacant-land-length'],
        ['name' => 'Timber', 'class' => 'vacant-land-length'],
        ['name' => 'Tracts', 'class' => 'vacant-land-length'],
        ['name' => 'Trans/Cell Tower', 'class' => 'vacant-land-length'],
        ['name' => 'Tree Farm', 'class' => 'vacant-land-length'],
        ['name' => 'Unimproved Land', 'class' => 'vacant-land-length'],
        ['name' => 'Well Field', 'class' => 'vacant-land-length'],
        ['name' => 'Other', 'class' => 'vacant-land-length', 'id' => 'vacant-land-length-other'],

        // Commercial
        ['name' => 'Agriculture', 'class' => 'commercial-length'],
        ['name' => 'Assembly Building', 'class' => 'commercial-length'],
        ['name' => 'Business', 'class' => 'commercial-length'],
        ['name' => 'Five or More', 'class' => 'commercial-length'],
        ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
        ['name' => 'Industrial', 'class' => 'commercial-length'],
        ['name' => 'Mixed Use', 'class' => 'commercial-length'],
        ['name' => 'Office', 'class' => 'commercial-length'],
        ['name' => 'Restaurant', 'class' => 'commercial-length'],
        ['name' => 'Retail', 'class' => 'commercial-length'],
        ['name' => 'Warehouse', 'class' => 'commercial-length'],
    ];
@endphp


<input type="hidden" wire:model="condition_prop_buyer_json">
<input type="hidden" wire:model.defer="number_of_unit_type_json">
<input type="hidden" wire:model="property_items_json">

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
            title="Enter the cities where the Buyer is willing to purchase a property.<br>Selecting a city will automatically populate the associated county and state when available.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>



    <div class="input-cover position-relative">
        <input type="text" wire:model.live.debounce.300ms="newCity" wire:keydown.enter.prevent="selectCitySuggestion()"
            wire:keydown.arrow-up.prevent="decrementHighlight('City')"
            wire:keydown.arrow-down.prevent="incrementHighlight('City')"
            class="form-control has-icon @error('newCity') is-invalid @enderror" data-icon="fa-solid fa-city"
            autocomplete="off" placeholder="Enter city or cities">


        <!-- City Suggestions Dropdown -->
        @if (count($citySuggestions) > 0)
            <div class="autocomplete-dropdown shadow-sm">
                <ul class="list-group">
                    @foreach ($citySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedCityIndex === $index ? 'bg-light' : '' }}"
                            @mousedown.prevent="$wire.selectCitySuggestion('{{ $suggestion }}')"
                            wire:key="city-suggestion-{{ $index }}">
                            <i class="fa-solid fa-city me-2 text-muted"></i>
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
                    <i class="fa-solid fa-city me-2"></i>
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
            title="Enter the counties where the Buyer is willing to purchase a property.<br>If a county is selected, the state will automatically populate.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover position-relative">
        <input type="text" wire:model.live.debounce.300ms="newCounty" wire:keydown.enter.prevent="selectCountySuggestion()"
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
                            <i class="fa-solid fa-map me-2 text-muted"></i>
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

    <!-- Hidden field for counties validation - validated via Livewire state -->
    <input type="hidden" id="counties_hidden" name="counties_hidden" 
           value="{{ count($counties) > 0 ? json_encode($counties) : '' }}"
           data-livewire-counties="true">

    <!-- Display added counties -->
    <div class="mt-1 counties-container">
        @if (count($counties) > 0)
            @foreach ($counties as $index => $county)
                <span class="badge bg-primary rounded-pill d-inline-flex align-items-center" wire:key="county-badge-{{ $index }}">
                    <i class="fa-solid fa-map me-2"></i>
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
    <label class="fw-bold">Acceptable State:<span class="text-danger">*</span>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the state where the Buyer is looking to purchase a property.<br>This may be automatically filled based on the counties selected.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover position-relative">
        <input type="text" wire:model.defer="state" wire:keydown.enter.prevent="selectStateSuggestion"
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
            data-icon="fa-solid fa-building" data-placeholder="Select" required>
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


    <div wire:key="property-items-{{ $property_type }}">
        <div class="input-cover" wire:ignore>
            <select id="property_items" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-home input-icon2" data-placeholder="Select" @if (!$property_type) disabled @endif multiple
                required>
                <option value=""></option>
                @if ($property_type === 'Residential')
                    @foreach ($property_items_buyer as $item)
                        @if (str_contains($item['class'], 'residential-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], (is_array($this->property_items) ? $this->property_items : [])) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif ($property_type === 'Income')
                    @foreach ($property_items_buyer as $item)
                        @if (str_contains($item['class'], 'income-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], (is_array($this->property_items) ? $this->property_items : [])) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif ($property_type === 'Commercial')
                    @foreach ($property_items_buyer as $item)
                        @if (str_contains($item['class'], 'commercial-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], (is_array($this->property_items) ? $this->property_items : [])) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif ($property_type === 'Business')
                    @foreach ($property_items_buyer as $item)
                        @if (str_contains($item['class'], 'business-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], (is_array($this->property_items) ? $this->property_items : [])) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif ($property_type === 'Opportunity')
                    @foreach ($property_items_buyer as $item)
                        @if (str_contains($item['class'], 'opportunity-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], (is_array($this->property_items) ? $this->property_items : [])) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif ($property_type === 'Vacant Land')
                    @foreach ($property_items_buyer as $item)
                        @if (str_contains($item['class'], 'vacant-land-length'))
                            <option value="{{ $item['name'] }}" {{ in_array($item['name'], (is_array($this->property_items) ? $this->property_items : [])) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @endif
            </select>
        </div>
    </div>
    <span class="error mt-2" id="leasing_space_error"></span>
</div>

<!-- Other Property Style Input (shown when "Other" is selected) -->
<div class="form-group other_property_items_wrapper" style="{{ (is_array($this->property_items) && in_array('Other', $this->property_items)) ? '' : 'display:none;' }}" wire:key="other-property-items-wrapper">
    <div class="input-cover">
        <input type="text" wire:model.defer="other_property_items" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter other land use (e.g., Solar Farm, RV Park, Conservation Easement)">
    </div>
    <span class="error mt-2" id="other_property_items_error"></span>
</div>

{{-- Business Type - shown inline when Business is selected as Property Type --}}
@if ($property_type === 'Business')
<div class="form-group mt-3" wire:key="business-type-wrapper">
    <label class="fw-bold">Business Type:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of business the Buyer is interested in purchasing.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <select wire:model="business_type_selected" id="business_type_inline" class="form-control has-icon"
            data-icon="fa-solid fa-briefcase">
            <option value="">Select</option>
            @foreach ($business_type as $item)
                <option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
            @endforeach
        </select>
    </div>
</div>
@if (($business_type_selected ?? '') === 'Other')
<div class="form-group" wire:key="business-type-other">
    <div class="input-cover">
        <input type="text" wire:model.defer="other_business_type" class="form-control has-icon"
            data-icon="fa-solid fa-briefcase"
            placeholder="Enter other business type (e.g., Recording Studio, Event Venue, Repair Shop)">
    </div>
</div>
@endif
@endif

@if ($property_type !== 'Vacant Land')
    <div class="form-group" wire:key="property-conditions-wrapper">
        <label class="fw-bold">Acceptable Property Conditions:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the property conditions that are acceptable to the Buyer.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover" wire:ignore>
            <select id="condition_prop_buyer"
                class="condition_prop_buyer form-control has-icon select2-multiple"
                data-icon="fa-solid fa-screwdriver-wrench input-icon2" data-placeholder="Select" multiple>
                <option value=""></option>
                @foreach ($property_condition as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->condition_prop_buyer ?? []) ? 'selected' : '' }}>{{ $row_pt['display'] ?? $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="condition_prop_error"></span>
    </div>
@endif
<!-- Other Property Condition Input (shown when "Other" is selected) -->
<div class="form-group other_property_condition_wrapper" style="{{ (is_array($this->condition_prop_buyer) && in_array('Other', $this->condition_prop_buyer)) ? '' : 'display:none;' }}" wire:key="other-property-condition-wrapper">
    <label class="fw-bold">Other Property Condition:</label>
    <div class="input-cover">
        <input type="text" wire:model.defer="other_property_condition" class="form-control has-icon"
            data-icon="fa-solid fa-home">
    </div>
    <span class="error mt-2" id="other_property_condition_error"></span>
</div>

<!-- Minimum Bedrooms Needed -->
<div wire:key="buyer-property-fields-{{ $property_type ?? 'none' }}">
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
    <!-- Other Bedrooms Input (only shown when "Other" is selected) -->
    @if ($bedrooms === 'Other')
        <div class="form-group">
            <div class="input-cover">
                <input type="number" wire:model.defer="other_bedrooms" class="form-control has-icon"
                    data-icon="fa-solid fa-bed" placeholder="Enter minimum bedrooms needed (e.g., 11)">
            </div>
            <span class="error mt-2" id="other_bedrooms_error"></span>
        </div>
    @endif
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
                @foreach ($bathroomsRes as $row_pt)
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
            <input type="number" wire:model.defer="other_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath" placeholder="Enter minimum bathrooms needed (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bathrooms_error"></span>
    </div>
    @endif
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
            <input type="text" wire:model.defer="minimum_heated_square" class="form-control has-icon"
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
            <input type="number" wire:model.defer="minimum_leaseable" class="form-control has-icon"
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

    @if ($carport_needed == 'Yes')
    <!-- Carport Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group" id="other-carport-needed">
        <label class="fw-bold">Number of Carport Spaces Needed:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model.defer="other_carport_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of carport spaces needed (e.g., 1)">
        </div>
        <span class="error mt-2" id="other_carport_needed_error"></span>
    </div>
    @endif
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

    @if ($garage_needed == 'Yes')
    <!-- Garage Spaces Input (Shown Only When "Yes" is Selected) -->
    <div class="form-group" id="other-garage-needed">
        <label class="fw-bold">Number of Garage Spaces Needed:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model.defer="other_garage_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces needed (e.g., 1)">
        </div>
        <span class="error mt-2" id="other_garage_needed_error"></span>
    </div>
    @endif
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
@if ($this->garage_parking_spaces == 'Yes')
<div class="form-group" id="garage_parking_spaces_option_wrapper">
    <label class="fw-bold">Garage/Parking Features:</label>
    <div class="input-cover" wire:ignore>

        <select id="garage_parking_spaces_option"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse input-icon2" data-placeholder="Select" multiple>
            <option value=""></option>
            @foreach ($garage_parking_spaces as $row_pt)
                <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $garage_parking_spaces_option ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="garage_parking_spaces_option_error"></span>
</div>
@endif

<!-- Other Parking Space Text Input -->
@if (in_array('Other', $garage_parking_spaces_option ?? []))
<div class="form-group" id="other_parking_space_wrapper">
    <div class="input-cover">
        <input type="text" wire:model.defer="other_parking_space_wrapper" id="other_parking_space"
            class="form-control has-icon" data-icon="fa-solid fa-warehouse"
            placeholder="Enter garage/parking features needed (e.g., Tandem Parking, Gated Entry, Shared Driveway) ">
    </div>
</div>
@endif

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


    <div class="input-cover" wire:ignore>
        <select id="view_preference"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-tree input-icon2" data-placeholder="Select" multiple>
            <option value=""></option>
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
        <input type="text" wire:model.defer="other_preferences" class="form-control has-icon"
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

<!-- Non-Negotiable Amenities and Property Features (hidden for Vacant Land) -->
@if ($property_type !== 'Vacant Land')
<div class="form-group" wire:key="nna-{{ $property_type }}">
    <label class="fw-bold">Non-Negotiable Amenities and Property Features:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the essential amenities or features the Buyer requires in the property. If “Other” is selected, specify any additional must-have amenities or features.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover" wire:ignore>
        <select wire:model="non_negotiable_amenities" id="non_negotiable_amenities"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock input-icon2"
            data-placeholder="Select" @if (!$property_type) disabled @endif multiple>
            <option value=""></option>
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
            <input type="text" wire:model.defer="other_non_negotiable_amenities" class="form-control has-icon"
                data-icon="fa-solid fa-lock"
                placeholder="Enter non-negotiable amenities or features (e.g., Sauna, EV Charger, Outdoor Kitchen)">
        @elseif ($property_type === 'Commercial' or $property_type === 'Business')
            <input type="text" wire:model.defer="other_non_negotiable_amenities" class="form-control has-icon"
                data-icon="fa-solid fa-lock"
                placeholder="Enter non-negotiable amenities or features (e.g., Rooftop Access, Backup Generator, Freight Elevator) ">
        @endif

    </div>
</div>
@endif

{{-- Pets for Income Property (after Non-Negotiable Amenities, before Required Property or Business Assets) --}}
@if ($property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Pets:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer has pets. If so, enter details including the number, type, breed, weight, and whether the pet is a service animal or an emotional support animal.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover">
            <select wire:model="pets" id="pets_income" class="form-control has-icon" data-icon="fa-solid fa-paw"
                >
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
                    title="Enter the total number of pets the Buyer currently has (e.g., 2).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="number" wire:model.defer="number_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets (e.g., 2)">
                </div>
                <span class="error mt-2" id="number_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Pet Types:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the types of pets the Buyer has (e.g., Dog, Cat).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="text" wire:model.defer="type_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-cat" placeholder="Enter types of pets (e.g., Dog, Cat)">
                </div>
                <span class="error mt-2" id="type_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Breed of Pets:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the breed(s) of the Buyer's pets (e.g., Labrador, Siamese). If the Buyer has multiple pets with different breeds, list them all.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="text" wire:model.defer="breed_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-dog" placeholder="Enter breeds of pets (e.g., Labrador, Siamese)">
                </div>
                <span class="error mt-2" id="breed_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Weight of Pets (lbs):</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the weight of the Buyer's pet(s) in pounds (e.g., 30 lbs, 50 lbs). If the Buyer has multiple pets, list their weights individually.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
                <div class="input-cover">
                    <input type="text" wire:model.defer="weight_of_pets" class="form-control has-icon"
                        data-icon="fa-solid fa-weight" placeholder="Enter the weight of pets (e.g., 30 lbs, 50 lbs)">
                </div>
                <span class="error mt-2" id="weight_of_pets_error"></span>
            </div>

            <div class="form-group">
                <label class="fw-bold">Service Animal:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select if any of the Buyer's pets are trained service animals (e.g., for assistance with disabilities).">
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
                    title="Select if any of the Buyer's pets are emotional support animals, providing therapeutic support.">
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
@endif

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
            <select wire:model="real_estate_purchase" class="form-control has-icon" data-icon="fa-solid fa-building"
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


        <div class="input-cover" wire:ignore>
            <select id="assets" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-building input-icon2" data-placeholder="Select" multiple>
                <option value=""></option>
                <option value="Goodwill and Business Name" {{ in_array('Goodwill and Business Name', $assets ?? []) ? 'selected' : '' }}>
                    Goodwill and Business Name
                </option>
                <option value="Furniture, Fixtures, and Equipment (as per attached inventory)" {{ in_array('Furniture, Fixtures, and Equipment (as per attached inventory)', $assets ?? []) ? 'selected' : '' }}>
                    Furniture, Fixtures, and Equipment (as per attached inventory)
                </option>
                <option value="Advertising Materials" {{ in_array('Advertising Materials', $assets ?? []) ? 'selected' : '' }}>
                    Advertising Materials
                </option>
                <option value="Contract Rights" {{ in_array('Contract Rights', $assets ?? []) ? 'selected' : '' }}>
                    Contract Rights
                </option>
                <option value="Leases" {{ in_array('Leases', $assets ?? []) ? 'selected' : '' }}>
                    Leases
                </option>
                <option value="Licenses" {{ in_array('Licenses', $assets ?? []) ? 'selected' : '' }}>
                    Licenses
                </option>
                <option value="Rights under any Agreement for Interests" {{ in_array('Rights under any Agreement for Interests', $assets ?? []) ? 'selected' : '' }}>
                    Rights under any Agreement for Interests
                </option>
                <option value="Other" {{ in_array('Other', $assets ?? []) ? 'selected' : '' }}>Other</option>
            </select>
        </div>
        <span class="error mt-2" id="assets_error"></span>
    </div>
@endif
{{-- "Other" text input (only for Income, Commercial, Business when "Other" is selected) --}}
@if (in_array($property_type, ['Income', 'Commercial', 'Business']))
<div class="form-group other_assets {{ in_array('Other', $assets ?? []) ? '' : 'd-none' }}" wire:key="other-assets-wrapper">
    <div class="input-cover">
        <input type="text" wire:model.defer="assets_other" class="form-control has-icon" data-icon="fa-solid fa-building"
            placeholder="Enter any included assets (e.g., Inventory, Customer Lists, Trademarks, Software Rights)">
    </div>
    <span class="error mt-2" id="assets_other_error"></span>
</div>
@endif

{{-- 3) Income-only fields --}}
@if ($property_type === 'Income')

    {{-- b) Acceptable Number of Units --}}
    <div class="form-group">
        <label class="fw-bold">Acceptable Number of Units:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select the acceptable unit count range for the investment property (e.g., 1–4 units, 5–10 units, 10+ units). If "Other" is selected, enter a custom unit count or range.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

        <div class="input-cover">
            <select wire:model="unit_size" id="unit_size" class="form-control has-icon" data-icon="fa-solid fa-building">
                <option value="">Select</option>
                <option value="1-4 Units">1–4 Units</option>
                <option value="5-10 Units">5–10 Units</option>
                <option value="10+ Units">10+ Units</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    @if ($unit_size === 'Other')
        <div class="form-group" wire:key="unit-size-other-wrapper">
            <div class="input-cover">
                <input type="text" wire:model.defer="unit_size_other" class="form-control has-icon"
                    data-icon="fa-solid fa-building" placeholder="Enter acceptable number of units (e.g., 15 Units)">
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
                <input type="number" wire:model.defer="number_of_unit_other" class="form-control has-icon"
                    data-icon="fa-solid fa-home" placeholder="Enter minimum number of units needed (e.g., 4)">
            </div>
            <span class="error mt-2" id="number_of_unit_error"></span>
        </div>
    @endif --}}

@endif

@if (in_array($property_type, ['Income']))
    <div class="form-group">
        <label class="fw-bold">Acceptable Unit Type:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the types of units included in the sale. ">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>


        <div class="input-cover" wire:ignore>
            <select wire:model="number_of_unit_type"
                class="number_of_unit_type form-control has-icon select2-multiple"
                data-icon="fa-solid fa-home input-icon2" data-placeholder="Select" multiple>
                <option value=""></option>
                @foreach ($unit_types as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endif

{{-- 4) Net Income & Cap Rate (Income, Commercial, Business Opportunity) --}}

{{-- Pets (Residential only - Income has it above Income Property Criteria) --}}
@if ($property_type === 'Residential')
    <div class="form-group">
        <label class="fw-bold">Pets:
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether the Buyer has pets. If so, enter details including the number, type, breed, weight, and whether the pet is a service animal or an emotional support animal.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

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

{{-- Pet details for Residential only - Income has its own complete Pets section above --}}
@if ($pets === 'Yes' && $property_type === 'Residential')
    <div id="pet-details">
        {{-- Number of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Number of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the total number of pets the Buyer currently has (e.g., 2).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="number" wire:model.defer="number_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets (e.g., 2)">
            </div>
            <span class="error mt-2" id="number_of_pets_error"></span>
        </div>

        {{-- Pet Types --}}
        <div class="form-group">
            <label class="fw-bold">Pet Types:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the types of pets the Buyer has (e.g., Dog, Cat).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="text" wire:model.defer="type_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-cat" placeholder="Enter types of pets (e.g., Dog, Cat)">
            </div>
            <span class="error mt-2" id="type_of_pets_error"></span>
        </div>

        {{-- Breed of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Breed of Pets:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the breed(s) of the Buyer's pets (e.g., Labrador, Siamese). If the Buyer has multiple pets with different breeds, list them all.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="text" wire:model.defer="breed_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" placeholder="Enter breeds of pets (e.g., Labrador, Siamese)">
            </div>
            <span class="error mt-2" id="breed_of_pets_error"></span>
        </div>

        {{-- Weight of Pets --}}
        <div class="form-group">
            <label class="fw-bold">Weight of Pets (lbs):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the weight of the Buyer's pet(s) in pounds (e.g., 30 lbs, 50 lbs). If the Buyer has multiple pets, list their weights individually.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>

            <div class="input-cover">
                <input type="text" wire:model.defer="weight_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-weight" placeholder="Enter the weight of pets (e.g., 30 lbs, 50 lbs)">
            </div>
            <span class="error mt-2" id="weight_of_pets_error"></span>
        </div>

        {{-- Service Animal --}}
        <div class="form-group">
            <label class="fw-bold">Service Animal:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Select if any of the Buyer's pets are trained service animals (e.g., for assistance with disabilities).">
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
                title="Select if any of the Buyer's pets are emotional support animals, providing therapeutic support.">
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

                <input type="text" wire:model.defer="minimum_annual_net_income" class="form-control"
                    placeholder="Enter minimum annual net income needed (e.g., 50000)"
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
                <input type="text" wire:model.defer="minimum_cap_rate" class="form-control percentage-value-set"
                    placeholder="Enter minimum cap rate needed (e.g., 6.5)"
                    data-error-id="minimum_cap_rate_error" oninput="validateInput(this)"
                    onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                <span class="input-group-text-seller">%</span>
            </div>
        </div>
    </div>
@endif
@if ($property_type === 'Income')
<div class="form-group">
    <label class="fw-bold">Additional Details:

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter any other important preferences, requirements, or notes not covered above.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>


    <div class="input-cover">
        <textarea wire:model.defer="preferance_details" class="form-control" rows="4"
            style="padding: 10px; font-size: 16px;" placeholder="Enter any additional preferences "></textarea>
    </div>
</div>
@endif
</div>

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

    // Listen for Select2 sync event from draft load
    window.addEventListener('buyer-agent-select2-sync', function(event) {
        const data = event.detail;
        
        // Set global flag to prevent Livewire sync during Select2 hydration
        window.financingSyncInProgress = true;
        
        // Helper function to sync Select2 values 
        function syncSelect2(selector, values) {
            const $select = $(selector);
            if ($select.length && values && Array.isArray(values)) {
                $select.val(values).trigger('change');
            }
        }
        
        // Sync all Select2 multiselects with draft data
        syncSelect2('#view_preference', data.view_preference);
        syncSelect2('#non_negotiable_amenities', data.non_negotiable_amenities);
        syncSelect2('.pool_type', data.pool_type);
        syncSelect2('#offered_financing', data.offered_financing);
        syncSelect2('#services', data.services);
        syncSelect2('#lease_for', data.lease_for);
        syncSelect2('#credit_scroe_rating', data.credit_scroe_rating);
        syncSelect2('#flat_fee_services', data.flat_fee_services);
        syncSelect2('.number_of_unit_type', data.number_of_unit_type);
        syncSelect2('#condition_prop_buyer', data.condition_prop_buyer);
        syncSelect2('#property_items', data.property_items);
        
        // Clear flag after a short delay to allow all change events to complete
        setTimeout(function() {
            window.financingSyncInProgress = false;
        }, 100);
        
        // Update visibility of "Other" text fields based on synced values
        if (data.view_preference && data.view_preference.includes('Other')) {
            $('#other_preferences').show();
        }
        if (data.non_negotiable_amenities && data.non_negotiable_amenities.includes('Other')) {
            $('#other_non_negotiable_amenities_wrapper').show();
        }
        
        // Trigger financing visibility updates
        if (data.offered_financing) {
            // Show relevant financing sections
            data.offered_financing.forEach(function(type) {
                if (type === 'Cash') $('#cash_section').show();
                if (type === 'Seller Financing') $('#seller_financing_section').show();
                if (type === 'Assumable') $('#assumable_section').show();
                if (type === 'Exchange/Trade') $('#exchange_trade_section').show();
                if (type === 'Lease Option') $('#lease_option_section').show();
                if (type === 'Lease Purchase') $('#lease_purchase_section').show();
                if (type === 'Cryptocurrency') $('#cryptocurrency_section').show();
                if (type === 'NFT') $('#nft_section').show();
            });
        }
    });
</script>
