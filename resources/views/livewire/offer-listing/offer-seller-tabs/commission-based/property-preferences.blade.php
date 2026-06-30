@php
    // Appliances list matching Landlord Agent (same order) with legacy options for backwards compatibility
    $applianceOptions = [
        ['name' => 'Bar Fridge'],
        ['name' => 'Built-In Oven'],
        ['name' => 'Central Vacuum'],
        ['name' => 'Convection Oven'],
        ['name' => 'Cooktop'],
        ['name' => 'Dishwasher'],
        ['name' => 'Disposal'],
        ['name' => 'Dryer'],
        ['name' => 'Electric Water Heater'],
        ['name' => 'Exhaust Fan'],
        ['name' => 'Freezer'],
        ['name' => 'Garbage Disposal'],
        ['name' => 'Gas Water Heater'],
        ['name' => 'Ice Maker'],
        ['name' => 'Indoor Grill'],
        ['name' => 'Kitchen Reverse Osmosis System'],
        ['name' => 'Microwave'],
        ['name' => 'Oven'],
        ['name' => 'Range Electric'],
        ['name' => 'Range Gas'],
        ['name' => 'Range Hood'],
        ['name' => 'Refrigerator'],
        ['name' => 'Solar Hot Water'],
        ['name' => 'Solar Hot Water Owned'],
        ['name' => 'Solar Hot Water Rented'],
        ['name' => 'Stove/Range'],
        ['name' => 'Tankless Water Heater'],
        ['name' => 'Touchless Faucet'],
        ['name' => 'Trash Compactor'],
        ['name' => 'Washer'],
        ['name' => 'Washer/Dryer Combo'],
        ['name' => 'Water Filtration System'],
        ['name' => 'Water Heater'],
        ['name' => 'Water Purifier'],
        ['name' => 'Water Softener'],
        ['name' => 'Whole House R.O. System'],
        ['name' => 'Wine Cooler'],
        ['name' => 'Wine Refrigerator'],
        ['name' => 'None'],
        ['name' => 'Other'],
    ];

    // Bathroom options — sourced from config/property_types.php (shared source of truth).
    // config('property_types.bathroom_options') returns flat strings; map to ['name' => X] for
    // backwards-compatible @foreach $opt['name'] access used throughout this template.
    $bathroomOptions = array_map(fn($v) => ['name' => $v], config('property_types.bathroom_options'));

    // Business type options (for Commercial property type)
    $business_type = [
        ['name' => 'Aeronautical'],
        ['name' => 'Agriculture'],
        ['name' => 'Arts and Entertainment'],
        ['name' => 'Assembly Hall'],
        ['name' => 'Assisted Living'],
        ['name' => 'Auto Dealer'],
        ['name' => 'Auto Service'],
        ['name' => 'Bar/Tavern/Lounge'],
        ['name' => 'Barber/Beauty'],
        ['name' => 'Car Wash'],
        ['name' => 'Child Care'],
        ['name' => 'Church'],
        ['name' => 'Commercial'],
        ['name' => 'Concession Trailers/Vehicles'],
        ['name' => 'Construction/Contractor'],
        ['name' => 'Convenience Store'],
        ['name' => 'Distribution'],
        ['name' => 'Distributor Routine Ven'],
        ['name' => 'Education/School'],
        ['name' => 'Farm'],
        ['name' => 'Fashion/Specialty'],
        ['name' => 'Flex Space'],
        ['name' => 'Florist/Nursery'],
        ['name' => 'Food & Beverage'],
        ['name' => 'Gas Station'],
        ['name' => 'Grocery'],
        ['name' => 'Heavy Weight Sales Service'],
        ['name' => 'Hotel/Motel'],
        ['name' => 'Industrial'],
        ['name' => 'Light Items Sales Only'],
        ['name' => 'Manufacturing'],
        ['name' => 'Marine/Marina'],
        ['name' => 'Medical'],
        ['name' => 'Mixed'],
        ['name' => 'Mobile/Trailer Park'],
        ['name' => 'Personal Service'],
        ['name' => 'Professional Service'],
        ['name' => 'Professional/Office'],
        ['name' => 'Recreation'],
        ['name' => 'Research & Development'],
        ['name' => 'Residential'],
        ['name' => 'Restaurant'],
        ['name' => 'Retail'],
        ['name' => 'Shopping Center/Strip Center'],
        ['name' => 'Storage'],
        ['name' => 'Theatre'],
        ['name' => 'Timberland'],
        ['name' => 'Veterinary'],
        ['name' => 'Warehouse'],
        ['name' => 'Wholesale'],
        ['name' => 'Other'],
    ];

    $property_items_seller = [
        // Residential (alphabetical order)
        ['name' => '½ Duplex', 'class' => 'residential-length'],
        ['name' => '1/3 Triplex', 'class' => 'residential-length'],
        ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
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
        // Commercial (alphabetical order)
        ['name' => 'Agriculture', 'class' => 'commercial-length'],
        ['name' => 'Assembly Building', 'class' => 'commercial-length'],
        ['name' => 'Business', 'class' => 'commercial-length'],
        ['name' => 'Five or More ', 'class' => 'commercial-length'],
        ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
        ['name' => 'Industrial', 'class' => 'commercial-length'],
        ['name' => 'Mixed Use', 'class' => 'commercial-length'],
        ['name' => 'Office', 'class' => 'commercial-length'],
        ['name' => 'Restaurant', 'class' => 'commercial-length'],
        ['name' => 'Retail', 'class' => 'commercial-length'],
        ['name' => 'Warehouse', 'class' => 'commercial-length'],
        // Opportunity
        ['name' => 'Mixed Use', 'class' => 'opportunity-length'],
        ['name' => 'Office', 'class' => 'opportunity-length'],
        ['name' => 'Retail', 'class' => 'opportunity-length'],
        ['name' => 'Industrial', 'class' => 'opportunity-length'],
        ['name' => 'Residential', 'class' => 'opportunity-length'],
        ['name' => 'Other', 'class' => 'opportunity-length'],
        // Vacant Land
        ['name' => 'Agricultural', 'class' => 'vacant-land-length'],
        ['name' => 'Billboard Site', 'class' => 'vacant-land-length'],
        ['name' => 'Business', 'class' => 'vacant-land-length'],
        ['name' => 'Cattle', 'class' => 'vacant-land-length'],
        ['name' => 'Commercial', 'class' => 'vacant-land-length'],
        ['name' => 'Farm', 'class' => 'vacant-land-length'],
        ['name' => 'Fishery', 'class' => 'vacant-land-length'],
        ['name' => 'Highway Frontage', 'class' => 'vacant-land-length'],
        ['name' => 'Horses', 'class' => 'vacant-land-length'],
        ['name' => 'Industrial', 'class' => 'vacant-land-length'],
        ['name' => 'Land Fill', 'class' => 'vacant-land-length'],
        ['name' => 'Livestock', 'class' => 'vacant-land-length'],
        ['name' => 'Mixed Use', 'class' => 'vacant-land-length'],
        ['name' => 'Multi Family', 'class' => 'vacant-land-length'],
        ['name' => 'Nursery', 'class' => 'vacant-land-length'],
        ['name' => 'Orchard', 'class' => 'vacant-land-length'],
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
    ];

    $property_condition_seller = $property_condition_seller ?? [];

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

    // Acreage options — sourced from config/property_types.php (shared source of truth).
    $acreageRes = array_map(fn($v) => ['name' => $v], config('property_types.acreage_options'));

    $tenant_require = [
        ['name' => 'Furnished'],
        ['name' => 'Optional'],
        ['name' => 'Partial'],
        ['name' => 'Turnkey'],
        ['name' => 'Unfurnished'],
    ];

    $preferences = [
        ['name' => 'Beach'],
        ['name' => 'City'],
        ['name' => 'Garden'],
        ['name' => 'Golf Course'],
        ['name' => 'Greenbelt'],
        ['name' => 'Mountain(s)'],
        ['name' => 'Park'],
        ['name' => 'Pool'],
        ['name' => 'Tennis Court'],
        ['name' => 'Trees/Woods'],
        ['name' => 'Water'],
        ['name' => 'Other'],
    ];

    $included_assets = [
        ['name' => 'Furniture, Fixtures, and Equipment (as per attached inventory)'],
        ['name' => 'Advertising Materials'],
        ['name' => 'Contract Rights'],
        ['name' => 'Leases'],
        ['name' => 'Licenses'],
        ['name' => 'Rights under any Agreement for Interests'],
        ['name' => 'Other'],
    ];

    $purchasing_props = [['name' => 'Not Age-Restricted'], ['name' => '55+ Community'], ['name' => '62+ Community']];

    $unit_types = [
        ['name' => '1 Bed/1 Bath'],
        ['name' => '1 Bedroom'],
        ['name' => '2 Bed/1 Bath'],
        ['name' => '2 Bed/2 Bath'],
        ['name' => '2 Bedroom'],
        ['name' => '3 Bed/1 Bath'],
        ['name' => '3 Bed/2 Bath'],
        ['name' => '3 Bedroom'],
        ['name' => '4 Bedroom or More'],
        ['name' => '4+ Bed/1 Bath'],
        ['name' => '4+ Bed/2 Bath'],
        ['name' => 'Apartments'],
        ['name' => 'Efficiency'],
        ['name' => 'Loft'],
        ['name' => "Manager's Unit"],
        ['name' => 'Multi-Level'],
        ['name' => 'Penthouse'],
        ['name' => 'Studio'],
        ['name' => 'Other'],
    ];

    $garage_parking_spaces = [
        ['name' => '1 to 5 Spaces'],
        ['name' => '6 to 12 Spaces'],
        ['name' => '13 to 18 Spaces'],
        ['name' => '19 to 30 Spaces'],
        ['name' => 'Airplane Hangar'],
        ['name' => 'Common'],
        ['name' => 'Curb Parking'],
        ['name' => 'Deeded'],
        ['name' => 'Electric Vehicle Charging Station(s)'],
        ['name' => 'Ground Level'],
        ['name' => 'Lighted'],
        ['name' => 'Over 30 Spaces'],
        ['name' => 'RV Parking'],
        ['name' => 'Secured'],
        ['name' => 'Under Building'],
        ['name' => 'Underground'],
        ['name' => 'Valet'],
        ['name' => 'None'],
        ['name' => 'Other'],
    ];

    $non_negotialble_terms_landlord = [
        ['name' => 'Accessibility Features', 'class' => 'residential-length'],
        ['name' => 'Balcony/Patio', 'class' => 'residential-length'],
        ['name' => 'Carpet Floors', 'class' => 'residential-length'],
        ['name' => 'Carport', 'class' => 'residential-length'],
        ['name' => 'Central Air Conditioning', 'class' => 'residential-length'],
        ['name' => 'Central Heating', 'class' => 'residential-length'],
        ['name' => 'Clubhouse', 'class' => 'residential-length'],
        ['name' => 'Covered Carport', 'class' => 'residential-length'],
        ['name' => 'Elevator', 'class' => 'residential-length'],
        ['name' => 'Fireplace', 'class' => 'residential-length'],
        ['name' => 'First Floor Unit', 'class' => 'residential-length'],
        ['name' => 'Fitness Center/Gym', 'class' => 'residential-length'],
        ['name' => 'Garage', 'class' => 'residential-length'],
        ['name' => 'Gated Community', 'class' => 'residential-length'],
        ['name' => 'Hardwood Floors', 'class' => 'residential-length'],
        ['name' => 'HOA Community', 'class' => 'residential-length'],
        ['name' => 'In-Unit Laundry', 'class' => 'residential-length'],
        ['name' => 'On-site Laundry', 'class' => 'residential-length'],
        ['name' => 'On-site Maintenance', 'class' => 'residential-length'],
        ['name' => 'On-site Management', 'class' => 'residential-length'],
        ['name' => 'Outdoor Space', 'class' => 'residential-length'],
        ['name' => 'Pet Friendly', 'class' => 'residential-length'],
        ['name' => 'Playground', 'class' => 'residential-length'],
        ['name' => 'Pool', 'class' => 'residential-length'],
        ['name' => 'Security System', 'class' => 'residential-length'],
        ['name' => 'Specific School District', 'class' => 'residential-length'],
        ['name' => 'Storage Space', 'class' => 'residential-length'],
        ['name' => 'Study/Den/Office', 'class' => 'residential-length'],
        ['name' => 'Tile Floors', 'class' => 'residential-length'],
        ['name' => 'Updated Bathroom', 'class' => 'residential-length'],
        ['name' => 'Updated Kitchen', 'class' => 'residential-length'],
        ['name' => 'Walk-in Closet', 'class' => 'residential-length'],
        ['name' => 'Washer and Dryer', 'class' => 'residential-length'],
        ['name' => 'Washer and Dryer Hookup', 'class' => 'residential-length'],
        ['name' => 'Waterfront', 'class' => 'residential-length'],
        ['name' => 'Other', 'class' => 'residential-length'],
        ['name' => 'Access to Public Transportation', 'class' => 'commercial-length'],
        ['name' => 'Business Center', 'class' => 'commercial-length'],
        ['name' => 'Common Areas', 'class' => 'commercial-length'],
        ['name' => 'Conference Room', 'class' => 'commercial-length'],
        ['name' => 'Elevator', 'class' => 'commercial-length'],
        ['name' => 'Fire Safety Systems', 'class' => 'commercial-length'],
        ['name' => 'Flexibility for Renovations', 'class' => 'commercial-length'],
        ['name' => 'Green Building Certification', 'class' => 'commercial-length'],
        ['name' => 'Gym/Fitness Facilities', 'class' => 'commercial-length'],
        ['name' => 'Handicap Accessibility', 'class' => 'commercial-length'],
        ['name' => 'High-Speed Internet', 'class' => 'commercial-length'],
        ['name' => 'HVAC System', 'class' => 'commercial-length'],
        ['name' => 'Industrial Features', 'class' => 'commercial-length'],
        ['name' => 'Kitchenette/Break Room', 'class' => 'commercial-length'],
        ['name' => 'Loading Dock', 'class' => 'commercial-length'],
        ['name' => 'Lounge Area', 'class' => 'commercial-length'],
        ['name' => 'Natural Lighting', 'class' => 'commercial-length'],
        ['name' => 'Office Space', 'class' => 'commercial-length'],
        ['name' => 'On-site Maintenance', 'class' => 'commercial-length'],
        ['name' => 'On-site Management', 'class' => 'commercial-length'],
        ['name' => 'Open Floor Plan', 'class' => 'commercial-length'],
        ['name' => 'Outdoor Space/Garden', 'class' => 'commercial-length'],
        ['name' => 'Parking Spaces', 'class' => 'commercial-length'],
        ['name' => 'Proximity to Highways', 'class' => 'commercial-length'],
        ['name' => 'Reception Area', 'class' => 'commercial-length'],
        ['name' => 'Restaurant Space', 'class' => 'commercial-length'],
        ['name' => 'Restrooms', 'class' => 'commercial-length'],
        ['name' => 'Retail Frontage', 'class' => 'commercial-length'],
        ['name' => 'Security Guard', 'class' => 'commercial-length'],
        ['name' => 'Security System', 'class' => 'commercial-length'],
        ['name' => 'Signage Opportunities', 'class' => 'commercial-length'],
        ['name' => 'Storage Space', 'class' => 'commercial-length'],
        ['name' => 'Utilities Included', 'class' => 'commercial-length'],
        ['name' => 'Visibility from Main Road', 'class' => 'commercial-length'],
        ['name' => 'Warehouse Space', 'class' => 'commercial-length'],
        ['name' => 'Other', 'class' => 'commercial-length'],
    ];

    $business_assets = $business_assets ?? [];
@endphp
<h3>Property Details</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🏠 Describe the property being offered for sale — including location, size, style, layout, and key
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
        <input type="text" id="seller-offer-street-address" wire:model="address"
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

<input type="hidden" id="seller-offer-property-lat" wire:model="property_lat">
<input type="hidden" id="seller-offer-property-lng" wire:model="property_lng">
<input type="hidden" id="seller-offer-google-place-id" wire:model="google_place_id">

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


@if ($zipCodeFieldVisible)
    <div class="form-group mb-3">
        <label class="fw-bold">ZIP Code:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the ZIP code(s) where you’re interested in selling a property.">
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

<!-- Acceptable Counties -->

@if ($stateFieldVisible)

    <div class="form-group">
        <label class="fw-bold">State: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the state where you’re looking to sell.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover position-relative">
            <input type="text" wire:model.defer="state" wire:keydown.enter.prevent="selectStateSuggestion"
                wire:keydown.arrow-up="decrementHighlight('state')"
                wire:keydown.arrow-down="incrementHighlight('state')"
                class="form-control has-icon @error('state') is-invalid @enderror" data-icon="fa-solid fa-flag-usa"
                autocomplete="off" placeholder="Enter state (e.g., FL)" required>

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
    <label class="fw-bold">Property Type: <span class="text-danger">*</span></label>
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
    <span class="error mt-2" id="leasing_space_type_error"></span>
</div>

<div class="form-group mt-3">
    <label class="fw-bold">Property Style: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the specific architectural or structural style of the property.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover has-select-icon">

        <select wire:model.defer="property_items" id="property_style_select" class="form-control has-icon"
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
    <span class="error mt-2" id="leasing_space_style_error"></span>
</div>

<!-- Other Property Style Input -->
<div class="form-group other_property_items_seller {{ $property_items === 'Other' ? '' : 'd-none' }}">
    <div class="input-cover">
        <input type="text" wire:model.defer="other_property_items" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter property style (e.g., Solar farm, RV park, Conservation easement)"
            @if ($property_items === 'Other') required @endif>
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
        <input type="text" wire:model.defer="other_business_type" class="form-control has-icon"
            data-icon="fa-solid fa-home"
            placeholder="Enter business type (e.g., Recording studio, Event venue, Repair shop)">
    </div>
</div>

@if ($property_type != 'Vacant Land')

    @php
        // A4.26/A4.27 backward-compat: the canonical Seller condition list changed
        // (legacy values like "Updated/Renovated" / "No Preference" were removed).
        // Keep any previously-saved value selectable so existing listings still
        // display and edit without data loss — never delete the stored value.
        $sellerConditionOptions = $property_condition_seller;
        if (!empty($condition_prop) && is_string($condition_prop)) {
            $sellerConditionOptionNames = array_column($sellerConditionOptions, 'name');
            if (!in_array($condition_prop, $sellerConditionOptionNames, true)) {
                $sellerConditionOptions[] = ['name' => $condition_prop];
            }
        }
    @endphp

    <div class="form-group">
        <label class="fw-bold">Property Condition: <span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the current condition of the property being offered for sale.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="condition_prop" class="form-control has-icon"
                data-icon="fa-solid fa-screwdriver-wrench" required>
                <option value="">Select</option>
                @foreach ($sellerConditionOptions as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['display'] ?? $row_pt['name'] }}</option>
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
<div wire:key="seller-property-fields-{{ $property_type ?? 'none' }}">
@if ($property_type === 'Residential')
    <div class="form-group">
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
    @if ($bedrooms === 'Other')
    <div class="form-group other_bedrooms">
        <div class="input-cover">
            <input type="number" wire:model.defer="other_bedrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bed" placeholder="Enter number of bedrooms (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bedrooms_error"></span>
    </div>
    @endif
@endif

<!-- Other Bedrooms Input (Hidden by Default) -->

<!-- Minimum Bathrooms Needed -->
@if (in_array($property_type, ['Residential', 'Business', 'Commercial']))

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
                @foreach ($bathroomOptions as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="bathrooms_error"></span>
    </div>
    <!-- Other Bathrooms Input (Shows only when Other is selected) -->
    @if ($this->bathrooms === 'Other')
    <div class="form-group other_bathrooms">
        <div class="input-cover">
            <input type="number" wire:model.defer="other_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath" placeholder="Enter number of bathrooms (e.g., 11)">
        </div>
        <span class="error mt-2" id="other_bathrooms_error"></span>
    </div>
    @endif
@endif

<!-- Minimum Heated SqFt Needed -->
{{-- @if (in_array($property_type, ['Residential', 'Business', 'Commercial']))
    <div class="form-group" wire:key="heated-square-select-{{ $property_type }}">
        <label class="fw-bold">Heated SqFt: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of climate-controlled (heated/cooled) interior space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="minimum_heated_square" class="form-control has-icon"
                data-icon="fa-solid fa-ruler" placeholder="Enter heated square footage (e.g., 1000)" required>
        </div>
        <span class="error mt-2" id="minimum_heated_square_legacy_error"></span>
    </div>
@endif --}}

@if (in_array($property_type, ['Residential', 'Business', 'Commercial']))
    <div class="form-group" wire:key="heated-square-select-{{ $property_type }}">
        <label class="fw-bold">Heated SqFt:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total square footage of climate-controlled (heated/cooled) interior space.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" id="minimum_heated_square" wire:model.defer="minimum_heated_square"
                class="form-control has-icon" data-icon="fa-solid fa-ruler"
                placeholder="Enter heated square footage (e.g., 1000)" data-error-id="minimum_heated_square_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
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
            <input type="text" id="total_square_feet" wire:model.defer="total_square_feet"
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
@endif

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
@if (in_array($property_type, ['Residential', 'Business', 'Commercial', 'Income']))

    <div class="form-group" wire:key="appliance-select-{{ $property_type }}">
        <label class="fw-bold">Appliances Included:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the appliances included with the property. If 'Other' is selected, enter any additional appliances.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover has-select-icon" wire:ignore>
            <select id="appliances" class="form-control has-icon select2-multiple"
                data-icon="fa-solid fa-plug" data-placeholder="Select" multiple>
                @foreach ($applianceOptions as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ is_array($appliances) && in_array($row_pt['name'], $appliances) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="appliances_error"></span>
    </div>

@endif
<div class="form-group" id="other_appliances" style="display: {{ ($showOtherAppliances && $property_type !== 'Vacant Land') ? 'block' : 'none' }}">
    <div class="input-cover">
        <input type="text" wire:model.defer="other_appliances" class="form-control has-icon"
            data-icon="fa-solid fa-plug"
            placeholder="Enter appliances (e.g., Air fryer oven, Induction cooktop, Double oven)"
            @if (is_array($appliances) && in_array('Other', $appliances)) required @endif>
    </div>
    <span class="error mt-2" id="other_appliances_error"></span>
</div>
<!-- Furnishings Needed -->
{{-- @if ($property_type === 'Residential')
                                <div class="form-group">
                                    <label class="fw-bold">Furnishings Needed: <span class="text-danger">*</span></label>
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
        <label class="fw-bold">Carport:
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
    @if ($carport_needed == 'Yes')
    <div class="form-group" id="other-carport-needed">
        <label class="fw-bold">Number of Carport Spaces:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model.defer="other_carport_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of carport spaces (e.g., 1) ">
        </div>
        <span class="error mt-2" id="other_carport_needed_error"></span>
    </div>
    @endif
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
    @if ($garage_needed == 'Yes')
    <div class="form-group" id="other-garage-needed">
        <label class="fw-bold">Number of Garage Spaces:</label>
        <div class="input-cover">
            <input type="number" min="1" wire:model.defer="other_garage_needed" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse" placeholder="Enter number of garage spaces (e.g., 2)">
        </div>
        <span class="error mt-2" id="other_garage_needed_error"></span>
    </div>
    @endif
@endif

<!-- Garage/Parking Spaces Needed -->

@if (in_array($property_type, ['Business', 'Commercial']))

    {{-- @if ($property_type === 'Business') --}}
    {{-- <div class="form-group">
        <label class="fw-bold">Garage/Parking Features: <span class="text-danger">*</span></label>

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

        <div class="input-cover has-select-icon">
            <select wire:model="garage_parking_spaces_option" id="garage_parking_spaces_option"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse" data-placeholder="Select" multiple>
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
                placeholder="Enter garage/parking features (e.g., Tandem parking, Gated entry, Shared driveway)">
        </div>
    </div> --}}

    <div class="form-group" wire:key="parking-features-select-{{ $property_type }}">
        <label class="fw-bold">Garage/Parking Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of garage or parking features available. If “Other” is selected, enter any additional garage or parking features in the provided field.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="garage_parking_spaces_option_landlord"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-warehouse" data-placeholder="Select" multiple>
                @foreach ($garage_parking_spaces as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ is_array($garage_parking_spaces_option) && in_array($row_pt['name'], $garage_parking_spaces_option) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Other Parking Space Text Input -->
    <div class="form-group" id="other_garage_parking_spaces_option_landlord"
        style="{{ collect($garage_parking_spaces_option)->contains('Other') ? '' : 'display: none;' }}">
        {{-- <label class="fw-bold">Other Garage/Parking Features:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model.defer="other_parking_space_wrapper" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse"
                placeholder="Enter garage/parking features (e.g., Tandem parking, Gated entry, Shared driveway)">
        </div>
    </div>

@endif

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
                <option value="{{ $opt }}" {{ is_array($water_access) && in_array($opt, $water_access) ? 'selected' : '' }}>{{ $opt }}</option>
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
                <option value="{{ $opt }}" {{ is_array($water_view) && in_array($opt, $water_view) ? 'selected' : '' }}>{{ $opt }}</option>
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
                <option value="{{ $opt }}" {{ is_array($interior_features) && in_array($opt, $interior_features) ? 'selected' : '' }}>{{ $opt }}</option>
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

@if (in_array($property_type, ['Residential', 'Income']) && $pool_needed === 'Yes')
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

<div class="form-group" wire:key="view-preference-{{ $property_type }}">
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
                    {{ is_array($view_preference) && in_array($row_pt['name'], $view_preference) ? 'selected' : '' }}>
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="view_preference_error"></span>
</div>

<!-- Other View Input — shown directly under View when Other is selected -->
<div class="form-group" id="other_preferences" style="display: {{ is_array($view_preference) && in_array('Other', $view_preference) ? 'block' : 'none' }}">
    <div class="input-cover">
        <input type="text" wire:model.defer="other_preferences" class="form-control has-icon"
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
    <div class="form-group" wire:key="nna-{{ $property_type }}">
        <label class="fw-bold">Amenities and Property Features:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the amenities and property features included with the property. If 'Other' is selected, enter any additional amenities or features.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover has-select-icon" wire:ignore>
            <select id="non_negotiable_amenities"
                class="form-control has-icon select2-multiple" data-icon="fa-solid fa-lock"
                data-placeholder="Select" @if (!$property_type) disabled @endif multiple>
                @if (in_array($property_type, ['Residential', 'Income']))
                    @foreach ($non_negotialble_terms_landlord as $item)
                        @if (str_contains($item['class'], 'residential-length'))
                            <option value="{{ $item['name'] }}" {{ is_array($non_negotiable_amenities) && in_array($item['name'], $non_negotiable_amenities) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @elseif(in_array($property_type, ['Business', 'Commercial']))
                    @foreach ($non_negotialble_terms_landlord as $item)
                        @if (str_contains($item['class'], 'commercial-length'))
                            <option value="{{ $item['name'] }}" {{ is_array($non_negotiable_amenities) && in_array($item['name'], $non_negotiable_amenities) ? 'selected' : '' }}>{{ $item['name'] }}</option>
                        @endif
                    @endforeach
                @endif
            </select>
        </div>
        <span class="error mt-2" id="non_negotiable_amenities_error"></span>
    </div>
    <!-- Other Non-Negotiable Amenities and Property Features Input (Hidden by Default) -->
    <div class="form-group other_non_negotiable_amenities" style="{{ is_array($non_negotiable_amenities) && in_array('Other', $non_negotiable_amenities) ? '' : 'display:none;' }}">
        <div class="input-cover">
            @if (in_array($property_type, ['Residential', 'Income']))
                <input type="text" wire:model.defer="other_non_negotiable_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-lock"
                    placeholder="Enter amenities or property features (e.g., Sauna, EV charger, Outdoor kitchen)">
            @elseif(in_array($property_type, ['Business', 'Commercial']))
                <input type="text" wire:model.defer="other_non_negotiable_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-lock"
                    placeholder="Enter amenities or property features (e.g., Rooftop access, Backup generator, Freight elevator)">
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
    </div>
@endif
@if ($pets === 'Yes' && in_array($property_type, ['Residential', 'Income']))
    <!-- Pet Details -->
    <div id="pet-details">
        <!-- Number of Pet(s) -->
        <div class="form-group">
            <label class="fw-bold">Number of Pets Allowed:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the maximum number of pets the Seller will allow for this property (e.g., 2).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model.defer="number_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-hashtag" placeholder="Enter number of pets allowed (e.g., 2)">
            </div>
            <span class="error mt-2" id="number_of_pets_error"></span>
        </div>
        <!-- Type of Pet(s) -->
        <div class="form-group">
            <label class="fw-bold">Acceptable Pet Types:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the types of pets the Seller will allow (e.g., Dog, Cat).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model.defer="type_of_pets" class="form-control has-icon"
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
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the maximum allowed weight for each individual pet, in pounds. Leave blank if there is no weight restriction.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="number" wire:model.defer="weight_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-weight" placeholder="Enter maximum weight per pet (e.g., 45)">
            </div>
            <span class="error mt-2" id="weight_of_pets_error"></span>
        </div>

        {{-- Hidden: breed_restrictions ("Pet Restrictions") is suppressed from the UI to avoid
             label conflict with the HOA pet_restrictions field in the Tax/Legal/HOA tab.
             The model property and save logic are intentionally preserved for data compatibility. --}}
        <div class="form-group" style="display:none">
            <label class="fw-bold">Pet Restrictions</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter any pet restrictions the Seller requires. Include any HOA or insurance-related restrictions if applicable.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model.defer="breed_restrictions" class="form-control has-icon"
                    data-icon="fa-solid fa-shield-dog" placeholder="Enter pet restrictions (e.g., No pit bulls)">
            </div>
            <span class="error mt-2" id="breed_restrictions_error"></span>
        </div>
    </div>
@endif
@if ($property_type === 'Business')
    <div class="form-group">
        <label class="fw-bold">Business & Real Estate Purchase Requirements: <span class="text-danger">*</span></label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select whether the purchase includes both the real estate and the business, or only the business operation.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select wire:model="real_estate_purchase" class="form-control has-icon" data-icon="fa-solid fa-building"
                required>
                <option value="">Select</option>
                <option value="Real Estate Building and Business">Real Estate Building and Business </option>
                <option value="Business Only">Business Only </option>
            </select>
        </div>
        <span class="error mt-2" id="pets_error"></span>
    </div>
@endif

<!-- Included Property or Business Assets — only for Business, Commercial, Income -->
@if (in_array($property_type, ['Business', 'Commercial', 'Income']))
<div class="form-group" wire:key="included-assets-{{ $property_type }}">
    <label class="fw-bold">Included Property or Business Assets:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select any property or business assets included in the sale.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover has-select-icon" wire:ignore>
        <select id="included_assets"
            class="form-control has-icon select2-multiple" data-icon="fa-solid fa-briefcase" data-placeholder="Select" multiple>
            @foreach ($included_assets as $row_pt)
                <option value="{{ $row_pt['name'] }}"
                    {{ is_array($business_assets) && in_array($row_pt['name'], $business_assets) ? 'selected' : '' }}>
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <span class="error mt-2" id="included_assets_error"></span>
</div>
    <div class="form-group other_assets mt-3" style="{{ (is_array($business_assets) && in_array('Other', $business_assets)) ? 'display:block;' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="assets_other" class="form-control has-icon"
                data-icon="fa-solid fa-building"
                placeholder="Enter any included assets (e.g., Inventory, Customer Lists, Trademarks, Software Rights)">
        </div>
    </div>
@endif
@if ($property_type === 'Income')
    <div class="form-group">
        <label class="fw-bold">Total Number of Units:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of units.">
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
            <span class="error mt-2" id="number_of_unit_other_error"></span>
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
            <span class="error mt-2" id="beds_unit_error"></span>
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
            <span class="error mt-2" id="baths_unit_error"></span>
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
                    data-icon="fa-solid fa-table-cells-large" placeholder="Enter total number of this unit type (e.g., 4)">
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
                                class="form-control has-icon" data-icon="fa-solid fa-table-cells-large"
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

                            <input type="text"
                                wire:model="unit_type_configurations.{{ $index }}.expected_rent"
                                class="form-control has-icon" placeholder="Enter expected monthly rent (e.g., 1500)"
                                data-error-id="expected_rent_{{ $index }}_error"
                                oninput="validateInput(this)"
                                onblur="reformatNumber(this)"
                                onpaste="handlePaste(event)">
                        </div>
                        <span class="error mt-2" id="expected_rent_{{ $index }}_error"></span>
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

                    <div class="form-group">
                        <label class="fw-bold">SqFt Heated (per unit):</label>
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                            title="Enter the heated square footage for this unit type.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="input-cover">
                            <input type="number"
                                wire:model="unit_type_configurations.{{ $index }}.sqft_heated"
                                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                placeholder="Enter heated square footage per unit (e.g., 850)">
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="form-group mt-3">
            <button type="button" class="btn btn-primary" wire:click="addUnitType" style="background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important;">
                <i class="fa-solid fa-plus me-2"></i>Add Another Unit Type
            </button>
        </div>
    </div>
@endif

@if (in_array($property_type, ['Residential', 'Income']))

    <div class="form-group">
        <label class="fw-bold">Year Built:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the property was originally built (e.g., 1998).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="year_built" class="form-control has-icon"
                data-icon="fa-solid fa-calendar" placeholder="Enter year built (e.g., 1998)" min="1800" max="2100">
        </div>
        <span class="error mt-2" id="year_built_error"></span>
    </div>

    <div class="form-group" wire:key="roof-type-ri-{{ $property_type }}">
        <label class="fw-bold">Roof Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of roofing material on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="roof_type_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-house" data-placeholder="Select" multiple>
                @foreach (['Built-Up','Cement','Concrete','Membrane','Metal','Roof Over','Shake','Shingle','Slate','Tile','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($roof_type) && in_array($_opt, $roof_type) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="roof_type_error_residential"></span>
    </div>
    <div class="form-group" id="other_roof_type_residential_wrapper" style="{{ is_array($roof_type) && in_array('Other', $roof_type) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_roof_type" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter roof type (e.g., Foam, TPO)">
        </div>
    </div>

    <div class="form-group" wire:key="exterior-construction-ri-{{ $property_type }}">
        <label class="fw-bold">Exterior Construction:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the exterior construction material(s) of the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="exterior_construction_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-building" data-placeholder="Select" multiple>
                @foreach (['Asbestos','Block','Brick','Cedar','Cement Siding','Concrete','HardiPlank Type','ICFs (Insulated Concrete Forms)','Log','Metal Frame','Metal Siding','SIP (Structurally Insulated Panel)','Stone','Stucco','Tilt up Walls','Vinyl Siding','Wood Frame','Wood Frame (FSC)','Wood Siding','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($exterior_construction) && in_array($_opt, $exterior_construction) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="exterior_construction_error_residential"></span>
    </div>
    <div class="form-group" id="other_exterior_construction_residential_wrapper" style="{{ is_array($exterior_construction) && in_array('Other', $exterior_construction) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_exterior_construction" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter exterior construction (e.g., Fiber cement, SIPs)">
        </div>
    </div>

    <div class="form-group" wire:key="foundation-ri-{{ $property_type }}">
        <label class="fw-bold">Foundation:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of foundation for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="foundation_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-layer-group" data-placeholder="Select" multiple>
                @foreach (['Basement','Block','Brick/Mortar','Concrete Perimeter','Crawlspace','Pillar/Post/Pier','Slab','Stem Wall','Stilt/On Piling','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($foundation) && in_array($_opt, $foundation) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="foundation_error_residential"></span>
    </div>
    <div class="form-group" id="other_foundation_residential_wrapper" style="{{ is_array($foundation) && in_array('Other', $foundation) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_foundation" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter foundation type (e.g., Helical pier, Pressure-treated wood)">
        </div>
    </div>

    <div class="form-group" wire:key="heating-and-fuel-ri-{{ $property_type }}">
        <label class="fw-bold">Heating and Fuel:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the heating system(s) and fuel type(s) used in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="heating_and_fuel_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-fire" data-placeholder="Select" multiple>
                @foreach (['Baseboard','Central','Electric','Exhaust Fans','Gas','Heat Pump','Heat Recovery Unit','Natural Gas','Oil','Partial','Propane','Radiant Ceiling','Reverse Cycle','Solar','Space Heater','Wall Furnace','Wall Units / Window Unit','Zoned','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($heating_and_fuel) && in_array($_opt, $heating_and_fuel) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="heating_and_fuel_error_residential"></span>
    </div>
    <div class="form-group" id="other_heating_and_fuel_residential_wrapper" style="{{ is_array($heating_and_fuel) && in_array('Other', $heating_and_fuel) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_heating_and_fuel" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter heating/fuel type (e.g., Wood pellet, Geothermal)">
        </div>
    </div>

    <div class="form-group" wire:key="air-conditioning-ri-{{ $property_type }}">
        <label class="fw-bold">Air Conditioning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of air conditioning in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="air_conditioning_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-snowflake" data-placeholder="Select" multiple>
                @foreach (['Central Air','Humidity Control','Mini-Split Unit(s)','Wall/Window Unit(s)','Zoned','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($air_conditioning) && in_array($_opt, $air_conditioning) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="air_conditioning_error_residential"></span>
    </div>
    <div class="form-group" id="other_air_conditioning_residential_wrapper" style="{{ is_array($air_conditioning) && in_array('Other', $air_conditioning) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_air_conditioning" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter air conditioning type (e.g., Evaporative cooler, Geo-thermal)">
        </div>
    </div>

    <div class="form-group" wire:key="water-ri-{{ $property_type }}">
        <label class="fw-bold">Water:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the water source(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="water_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-droplet" data-placeholder="Select" multiple>
                @foreach (['Canal/Lake For Irrigation','Private','Public','Well','Well Required','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($water) && in_array($_opt, $water) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="water_error_residential"></span>
    </div>
    <div class="form-group" id="other_water_residential_wrapper" style="{{ is_array($water) && in_array('Other', $water) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_water" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter water source (e.g., Rainwater collection, Shared well)">
        </div>
    </div>

    <div class="form-group" wire:key="sewer-ri-{{ $property_type }}">
        <label class="fw-bold">Sewer:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the sewer type(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sewer_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
                @foreach (['Aerobic Septic','PEP-Holding Tank','Private Sewer','Public Sewer','Septic Needed','Septic Tank','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($sewer) && in_array($_opt, $sewer) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sewer_error_residential"></span>
    </div>
    <div class="form-group" id="other_sewer_residential_wrapper" style="{{ is_array($sewer) && in_array('Other', $sewer) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_sewer" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter sewer type (e.g., Cesspool, Composting)">
        </div>
    </div>

    <div class="form-group" wire:key="utilities-ri-{{ $property_type }}">
        <label class="fw-bold">Utilities:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the utilities available or connected at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="utilities_residential" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-bolt" data-placeholder="Select" multiple>
                @foreach (['BB/HS Internet Available','Cable Available','Cable Connected','Electricity Available','Electricity Connected','Fiber Optics','Fire Hydrant','Mini Sewer','Natural Gas Available','Natural Gas Connected','Phone Available','Private','Propane','Public','Sewer Available','Sewer Connected','Solar','Sprinkler Meter','Sprinkler Recycled','Sprinkler Well','Street Lights','Underground Utilities','Water - Multiple Meters','Water Available','Water Connected','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($utilities) && in_array($_opt, $utilities) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="utilities_error_residential"></span>
    </div>
    <div class="form-group" id="other_utilities_residential_wrapper" style="{{ is_array($utilities) && in_array('Other', $utilities) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_utilities" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter utility (e.g., Steam, Geothermal)">
        </div>
    </div>

@endif

@if ($property_type === 'Income')

    <div class="form-group">
        <label class="fw-bold">Number of Water Meters:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of water meters on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="number_water_meters" class="form-control has-icon"
                data-icon="fa-solid fa-gauge" placeholder="Enter number of water meters (e.g., 4)">
        </div>
        <span class="error mt-2" id="number_water_meters_error"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Number of Electric Meters:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the total number of electric meters on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="number_electric_meters" class="form-control has-icon"
                data-icon="fa-solid fa-plug" placeholder="Enter number of electric meters (e.g., 4)">
        </div>
        <span class="error mt-2" id="number_electric_meters_error"></span>
    </div>

@endif

@if ($property_type === 'Commercial')

    <div class="form-group">
        <label class="fw-bold">Year Built:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the property was originally built (e.g., 1998).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="year_built" class="form-control has-icon"
                data-icon="fa-solid fa-calendar" placeholder="Enter year built (e.g., 1998)" min="1800" max="2100">
        </div>
        <span class="error mt-2" id="year_built_error_residential"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Zoning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the zoning classification for the property (e.g., C-1, B-2, I-1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="zoning" class="form-control has-icon"
                data-icon="fa-solid fa-map" placeholder="Enter zoning code (e.g., C-1, B-2)">
        </div>
        <span class="error mt-2" id="zoning_error_residential"></span>
    </div>

    <div class="form-group" wire:key="roof-type-comm-{{ $property_type }}">
        <label class="fw-bold">Roof Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of roofing material on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="roof_type_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-house" data-placeholder="Select" multiple>
                @foreach (['Built-Up','Cement','Concrete','Membrane','Metal','Roof Over','Shake','Shingle','Slate','Tile','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($roof_type) && in_array($_opt, $roof_type) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="roof_type_error_commercial"></span>
    </div>
    <div class="form-group" id="other_roof_type_commercial_wrapper" style="{{ is_array($roof_type) && in_array('Other', $roof_type) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_roof_type" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter roof type (e.g., Foam, TPO)">
        </div>
    </div>

    <div class="form-group" wire:key="exterior-construction-comm-{{ $property_type }}">
        <label class="fw-bold">Exterior Construction:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the exterior construction material(s) of the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="exterior_construction_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-building" data-placeholder="Select" multiple>
                @foreach (['Asbestos','Block','Brick','Cedar','Cement Siding','Concrete','HardiPlank Type','ICFs (Insulated Concrete Forms)','Log','Metal Frame','Metal Siding','SIP (Structurally Insulated Panel)','Stone','Stucco','Tilt up Walls','Vinyl Siding','Wood Frame','Wood Frame (FSC)','Wood Siding','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($exterior_construction) && in_array($_opt, $exterior_construction) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="exterior_construction_error_commercial"></span>
    </div>
    <div class="form-group" id="other_exterior_construction_commercial_wrapper" style="{{ is_array($exterior_construction) && in_array('Other', $exterior_construction) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_exterior_construction" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter exterior construction (e.g., Fiber cement, SIPs)">
        </div>
    </div>

    <div class="form-group" wire:key="foundation-comm-{{ $property_type }}">
        <label class="fw-bold">Foundation:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of foundation for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="foundation_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-layer-group" data-placeholder="Select" multiple>
                @foreach (['Basement','Block','Brick/Mortar','Concrete Perimeter','Crawlspace','Pillar/Post/Pier','Slab','Stem Wall','Stilt/On Piling','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($foundation) && in_array($_opt, $foundation) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="foundation_error_commercial"></span>
    </div>
    <div class="form-group" id="other_foundation_commercial_wrapper" style="{{ is_array($foundation) && in_array('Other', $foundation) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_foundation" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter foundation type (e.g., Helical pier, Pressure-treated wood)">
        </div>
    </div>

    <div class="form-group" wire:key="road-frontage-comm-{{ $property_type }}">
        <label class="fw-bold">Road Frontage:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of road frontage for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="road_frontage_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-road" data-placeholder="Select" multiple>
                @foreach (['Access Road','Alley','Business District','City Street','County Road','Divided Highway','Easement','Highway','Interchange','Interstate','Main Thoroughfare','Private Road','Rail','State Road','Turn Lanes','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($road_frontage) && in_array($_opt, $road_frontage) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="road_frontage_error_commercial"></span>
    </div>
    <div class="form-group" id="other_road_frontage_commercial_wrapper" style="{{ is_array($road_frontage) && in_array('Other', $road_frontage) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_road_frontage" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter road frontage type (e.g., Cul-de-sac, Service road)">
        </div>
    </div>

    <div class="form-group" wire:key="road-surface-type-comm-{{ $property_type }}">
        <label class="fw-bold">Road Surface Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the road surface type(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="road_surface_type_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-road" data-placeholder="Select" multiple>
                @foreach (['Asphalt','Brick','Chip And Seal','Concrete','Dirt','Gravel','Limerock','Paved','Unimproved','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($road_surface_type) && in_array($_opt, $road_surface_type) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="road_surface_type_error_commercial"></span>
    </div>
    <div class="form-group" id="other_road_surface_type_commercial_wrapper" style="{{ is_array($road_surface_type) && in_array('Other', $road_surface_type) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_road_surface_type" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter road surface type (e.g., Cobblestone, Shell)">
        </div>
    </div>

    <div class="form-group" wire:key="utilities-comm-{{ $property_type }}">
        <label class="fw-bold">Utilities:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the utilities available or connected at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="utilities_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-bolt" data-placeholder="Select" multiple>
                @foreach (['BB/HS Internet Capable','Electrical Nearby','Electricity Available','Emergency Power','Natural Gas Available','Phone Available','Private','Public','Sewer Nearby','Solar','Telephone Nearby','Underground Utilities','Water Connected','Water Nearby','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($utilities) && in_array($_opt, $utilities) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="utilities_error_commercial"></span>
    </div>
    <div class="form-group" id="other_utilities_commercial_wrapper" style="{{ is_array($utilities) && in_array('Other', $utilities) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_utilities" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter utility (e.g., Steam, Geothermal)">
        </div>
    </div>

    <div class="form-group" wire:key="water-comm-{{ $property_type }}">
        <label class="fw-bold">Water:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the water source(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="water_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-droplet" data-placeholder="Select" multiple>
                @foreach (['Canal/Lake For Irrigation','Private','Public','Well','Well Required','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($water) && in_array($_opt, $water) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="water_error_commercial"></span>
    </div>
    <div class="form-group" id="other_water_commercial_wrapper" style="{{ is_array($water) && in_array('Other', $water) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_water" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter water source (e.g., Rainwater collection, Shared well)">
        </div>
    </div>

    <div class="form-group" wire:key="sewer-comm-{{ $property_type }}">
        <label class="fw-bold">Sewer:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the sewer type(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sewer_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
                @foreach (['Aerobic Septic','PEP-Holding Tank','Private Sewer','Public Sewer','Septic Needed','Septic Tank','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($sewer) && in_array($_opt, $sewer) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sewer_error_commercial"></span>
    </div>
    <div class="form-group" id="other_sewer_commercial_wrapper" style="{{ is_array($sewer) && in_array('Other', $sewer) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_sewer" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter sewer type (e.g., Cesspool, Composting)">
        </div>
    </div>

    <div class="form-group" wire:key="heating-and-fuel-comm-{{ $property_type }}">
        <label class="fw-bold">Heating and Fuel:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the heating system(s) and fuel type(s) used in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="heating_and_fuel_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-fire" data-placeholder="Select" multiple>
                @foreach (['Baseboard','Central','Central Building','Central Individual','Electric','Exhaust Fans','Gas','Heat Pump','Heat Recovery Unit','Natural Gas','Oil','Partial','Propane','Radiant Ceiling','Reverse Cycle','Solar','Space Heater','Wall Furnace','Wall Units / Window Unit','Zoned','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($heating_and_fuel) && in_array($_opt, $heating_and_fuel) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="heating_and_fuel_error_commercial"></span>
    </div>
    <div class="form-group" id="other_heating_and_fuel_commercial_wrapper" style="{{ is_array($heating_and_fuel) && in_array('Other', $heating_and_fuel) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_heating_and_fuel" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter heating/fuel type (e.g., Wood pellet, Geothermal)">
        </div>
    </div>

    <div class="form-group" wire:key="air-conditioning-comm-{{ $property_type }}">
        <label class="fw-bold">Air Conditioning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of air conditioning in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="air_conditioning_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-snowflake" data-placeholder="Select" multiple>
                @foreach (['A/C Office Only','Central Air','Humidity Control','Mini-Split Unit(s)','Wall/Window Unit(s)','Zoned','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($air_conditioning) && in_array($_opt, $air_conditioning) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="air_conditioning_error_commercial"></span>
    </div>
    <div class="form-group" id="other_air_conditioning_commercial_wrapper" style="{{ is_array($air_conditioning) && in_array('Other', $air_conditioning) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_air_conditioning" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter air conditioning type (e.g., Evaporative cooler, Geo-thermal)">
        </div>
    </div>

    <div class="form-group" wire:key="electrical-service-comm-{{ $property_type }}">
        <label class="fw-bold">Electrical Service:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the electrical service type(s) available at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="electrical_service_commercial" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-plug" data-placeholder="Select" multiple>
                @foreach (['1 Phase (3-Wire)','3 Phase','110 Volts','220 Volts','440 Volts','Separate Meter','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($electrical_service) && in_array($_opt, $electrical_service) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="electrical_service_error_commercial"></span>
    </div>
    <div class="form-group" id="other_electrical_service_commercial_wrapper" style="{{ is_array($electrical_service) && in_array('Other', $electrical_service) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_electrical_service" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter electrical service (e.g., 600 volts, DC power)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Ceiling Height:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the ceiling height range for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="ceiling_height" id="ceiling_height" class="form-control has-icon" data-icon="fa-solid fa-arrows-up-down">
                <option value="">Select</option>
                @foreach (['Under 8 Feet','8-10 Feet','11-14 Feet','15-18 Feet','19-22 Feet','Over 22 Feet'] as $_opt)
                    <option value="{{ $_opt }}" @selected($ceiling_height === $_opt)>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="ceiling_height_error"></span>
    </div>

    <div class="form-group" wire:key="building-features-comm-{{ $property_type }}">
        <label class="fw-bold">Building Features:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the building features available at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="building_features" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-building" data-placeholder="Select" multiple>
                @foreach (['Bathrooms','Clear Span','Columns','Common Lighting','Drive-Through','Dumpsters','Elevator','Elevator – None','Extra Storage','Fencing','Fiber Optic','Freight Elevator','Furnished','High Bays','Janitorial Services','Kitchen Facility','Lit Sign on Site','Loading Dock','Loft','Medical Disposal','On Site Shower','Outside Storage','Overhead Doors','Pool/Spa','Ramp','Reception','Seating','Service Stations','Solid Surface Counter','Stone Counter','Trash Removal','Truck Doors','Truck Well','Waiting Room','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($building_features) && in_array($_opt, $building_features) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="building_features_error"></span>
    </div>
    <div class="form-group" id="other_building_features_wrapper" style="{{ is_array($building_features) && in_array('Other', $building_features) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_building_features" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter building features (e.g., Skylight, automated gate)">
        </div>
    </div>

@endif

@if ($property_type === 'Business')

    <div class="form-group">
        <label class="fw-bold">Business Name:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the legal business name (if different from the listing name).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="business_name" class="form-control has-icon"
                data-icon="fa-solid fa-store" placeholder="Enter business name (e.g., ABC Properties LLC)">
        </div>
        <span class="error mt-2" id="business_name_error"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Year Established:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the business was established (e.g., 2005).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="year_established" class="form-control has-icon"
                data-icon="fa-solid fa-calendar" placeholder="Enter year established (e.g., 2005)" min="1800" max="2100">
        </div>
        <span class="error mt-2" id="year_established_error"></span>
    </div>

    <div class="form-group" wire:key="licenses-biz-{{ $property_type }}">
        <label class="fw-bold">Licenses:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any licenses held or required for the business.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="licenses" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-certificate" data-placeholder="Select" multiple>
                @foreach (['Beer/Wine','Liquor','Off Site','On Site','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($licenses) && in_array($_opt, $licenses) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="licenses_error"></span>
    </div>
    <div class="form-group" id="other_licenses_wrapper" style="{{ is_array($licenses) && in_array('Other', $licenses) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_licenses" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter license (e.g., Catering, entertainment)">
        </div>
    </div>

    <div class="form-group" wire:key="sale-includes-biz-{{ $property_type }}">
        <label class="fw-bold">Sale Includes:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select what is included in the sale of the business.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sale_includes" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-hand-holding-dollar" data-placeholder="Select" multiple>
                @foreach (['Business','Equipment/Fixtures','Furniture','Goodwill','Inventory','Land','Lease Agreement','Liquor License','Parking Lot','Signage','Training','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($sale_includes) && in_array($_opt, $sale_includes) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sale_includes_error"></span>
    </div>
    <div class="form-group" id="other_sale_includes_wrapper" style="{{ is_array($sale_includes) && in_array('Other', $sale_includes) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_sale_includes" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter items included in sale (e.g., Domain Name, Social Media Accounts)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Year Built:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the building was originally built (e.g., 1998).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="year_built" class="form-control has-icon"
                data-icon="fa-solid fa-calendar" placeholder="Enter year built (e.g., 1998)" min="1800" max="2100">
        </div>
        <span class="error mt-2" id="year_built_error_business"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Zoning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the zoning classification for the property (e.g., C-1, B-2, I-1).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="zoning" class="form-control has-icon"
                data-icon="fa-solid fa-map" placeholder="Enter zoning code (e.g., C-1, B-2)">
        </div>
        <span class="error mt-2" id="zoning_error_business"></span>
    </div>

    <div class="form-group" wire:key="roof-type-biz-{{ $property_type }}">
        <label class="fw-bold">Roof Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of roofing material on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="roof_type_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-house" data-placeholder="Select" multiple>
                @foreach (['Built-Up','Cement','Concrete','Membrane','Metal','Roof Over','Shake','Shingle','Slate','Tile','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($roof_type) && in_array($_opt, $roof_type) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="roof_type_error_business"></span>
    </div>
    <div class="form-group" id="other_roof_type_business_wrapper" style="{{ is_array($roof_type) && in_array('Other', $roof_type) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_roof_type" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter roof type (e.g., Foam, TPO)">
        </div>
    </div>

    <div class="form-group" wire:key="exterior-construction-biz-{{ $property_type }}">
        <label class="fw-bold">Exterior Construction:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the exterior construction material(s) of the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="exterior_construction_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-building" data-placeholder="Select" multiple>
                @foreach (['Asbestos','Block','Brick','Cedar','Cement Siding','Concrete','HardiPlank Type','ICFs (Insulated Concrete Forms)','Log','Metal Frame','Metal Siding','SIP (Structurally Insulated Panel)','Stone','Stucco','Tilt up Walls','Vinyl Siding','Wood Frame','Wood Frame (FSC)','Wood Siding','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($exterior_construction) && in_array($_opt, $exterior_construction) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="exterior_construction_error_business"></span>
    </div>
    <div class="form-group" id="other_exterior_construction_business_wrapper" style="{{ is_array($exterior_construction) && in_array('Other', $exterior_construction) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_exterior_construction" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter exterior construction (e.g., Fiber cement, SIPs)">
        </div>
    </div>

    <div class="form-group" wire:key="foundation-biz-{{ $property_type }}">
        <label class="fw-bold">Foundation:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of foundation for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="foundation_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-layer-group" data-placeholder="Select" multiple>
                @foreach (['Basement','Block','Brick/Mortar','Concrete Perimeter','Crawlspace','Pillar/Post/Pier','Slab','Stem Wall','Stilt/On Piling','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($foundation) && in_array($_opt, $foundation) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="foundation_error_business"></span>
    </div>
    <div class="form-group" id="other_foundation_business_wrapper" style="{{ is_array($foundation) && in_array('Other', $foundation) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_foundation" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter foundation type (e.g., Helical pier, Pressure-treated wood)">
        </div>
    </div>

    <div class="form-group" wire:key="utilities-biz-{{ $property_type }}">
        <label class="fw-bold">Utilities:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the utilities available or connected at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="utilities_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-bolt" data-placeholder="Select" multiple>
                @foreach (['BB/HS Internet Available','Cable Available','Cable Connected','Electricity Available','Electricity Connected','Fiber Optics','Fire Hydrant','Mini Sewer','Natural Gas Available','Natural Gas Connected','Phone Available','Private','Propane','Public','Sewer Available','Sewer Connected','Solar','Sprinkler Meter','Sprinkler Recycled','Sprinkler Well','Street Lights','Underground Utilities','Water - Multiple Meters','Water Available','Water Connected','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($utilities) && in_array($_opt, $utilities) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="utilities_error_business"></span>
    </div>
    <div class="form-group" id="other_utilities_business_wrapper" style="{{ is_array($utilities) && in_array('Other', $utilities) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_utilities" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter utility (e.g., Steam, Geothermal)">
        </div>
    </div>

    <div class="form-group" wire:key="water-biz-{{ $property_type }}">
        <label class="fw-bold">Water:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the water source(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="water_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-droplet" data-placeholder="Select" multiple>
                @foreach (['Canal/Lake For Irrigation','Private','Public','Well','Well Required','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($water) && in_array($_opt, $water) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="water_error_business"></span>
    </div>
    <div class="form-group" id="other_water_business_wrapper" style="{{ is_array($water) && in_array('Other', $water) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_water" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter water source (e.g., Rainwater collection, Shared well)">
        </div>
    </div>

    <div class="form-group" wire:key="sewer-biz-{{ $property_type }}">
        <label class="fw-bold">Sewer:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the sewer type(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sewer_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
                @foreach (['Aerobic Septic','PEP-Holding Tank','Private Sewer','Public Sewer','Septic Needed','Septic Tank','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($sewer) && in_array($_opt, $sewer) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sewer_error_business"></span>
    </div>
    <div class="form-group" id="other_sewer_business_wrapper" style="{{ is_array($sewer) && in_array('Other', $sewer) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_sewer" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter sewer type (e.g., Cesspool, Composting)">
        </div>
    </div>

    <div class="form-group" wire:key="heating-and-fuel-biz-{{ $property_type }}">
        <label class="fw-bold">Heating and Fuel:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the heating system(s) and fuel type(s) used in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="heating_and_fuel_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-fire" data-placeholder="Select" multiple>
                @foreach (['Baseboard','Central','Electric','Exhaust Fans','Gas','Heat Pump','Heat Recovery Unit','Natural Gas','Oil','Partial','Propane','Radiant Ceiling','Reverse Cycle','Solar','Space Heater','Wall Furnace','Wall Units / Window Unit','Zoned','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($heating_and_fuel) && in_array($_opt, $heating_and_fuel) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="heating_and_fuel_error_business"></span>
    </div>
    <div class="form-group" id="other_heating_and_fuel_business_wrapper" style="{{ is_array($heating_and_fuel) && in_array('Other', $heating_and_fuel) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_heating_and_fuel" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter heating/fuel type (e.g., Wood pellet, Geothermal)">
        </div>
    </div>

    <div class="form-group" wire:key="air-conditioning-biz-{{ $property_type }}">
        <label class="fw-bold">Air Conditioning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of air conditioning in the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="air_conditioning_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-snowflake" data-placeholder="Select" multiple>
                @foreach (['Central Air','Humidity Control','Mini-Split Unit(s)','Wall/Window Unit(s)','Zoned','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($air_conditioning) && in_array($_opt, $air_conditioning) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="air_conditioning_error_business"></span>
    </div>
    <div class="form-group" id="other_air_conditioning_business_wrapper" style="{{ is_array($air_conditioning) && in_array('Other', $air_conditioning) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_air_conditioning" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter air conditioning type (e.g., Evaporative cooler, Geo-thermal)">
        </div>
    </div>

    <div class="form-group" wire:key="electrical-service-biz-{{ $property_type }}">
        <label class="fw-bold">Electrical Service:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the electrical service type(s) available at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="electrical_service_business" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-plug" data-placeholder="Select" multiple>
                @foreach (['1 Phase (3-Wire)','3 Phase','110 Volts','220 Volts','440 Volts','Separate Meter','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($electrical_service) && in_array($_opt, $electrical_service) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="electrical_service_error_business"></span>
    </div>
    <div class="form-group" id="other_electrical_service_business_wrapper" style="{{ is_array($electrical_service) && in_array('Other', $electrical_service) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_electrical_service" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter electrical service (e.g., 600 volts, DC power)">
        </div>
    </div>

@endif

@if ($property_type === 'Vacant Land')

    <div class="form-group" wire:key="current-use-vl-{{ $property_type }}">
        <label class="fw-bold">Current Use:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the current use(s) of the land.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="current_use" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-map" data-placeholder="Select" multiple>
                @foreach (['Agricultural','Commercial','Industrial','Recreational','Residential','Timber','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($current_use) && in_array($_opt, $current_use) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="current_use_error"></span>
    </div>
    <div class="form-group" id="other_current_use_wrapper" style="{{ is_array($current_use) && in_array('Other', $current_use) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_current_use" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter current use (e.g., Hunting, camping)">
        </div>
    </div>

    <div class="form-group" wire:key="current-adjacent-use-vl-{{ $property_type }}">
        <label class="fw-bold">Current Adjacent Use:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select how the adjacent properties are currently being used.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="current_adjacent_use" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-map-location" data-placeholder="Select" multiple>
                @foreach (['Church','Commercial','Industrial','Mobile Home Park','Multi-Family','Park','Professional Office','Residential','Retail','School','Vacant','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($current_adjacent_use) && in_array($_opt, $current_adjacent_use) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="current_adjacent_use_error"></span>
    </div>
    <div class="form-group" id="other_current_adjacent_use_wrapper" style="{{ is_array($current_adjacent_use) && in_array('Other', $current_adjacent_use) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_current_adjacent_use" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter adjacent use (e.g., Conservation land, Nature preserve)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Zoning:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the zoning classification for the land (e.g., A-1, R-1, C-2).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="zoning" class="form-control has-icon"
                data-icon="fa-solid fa-map" placeholder="Enter zoning code (e.g., A-1, R-1)">
        </div>
        <span class="error mt-2" id="zoning_error_vacant_land"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Water Available to Site:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether water service is available to the site.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="water_available" class="form-control has-icon" data-icon="fa-solid fa-droplet">
                <option value="">Select</option>
                <option value="Yes" @selected($water_available === 'Yes')>Yes</option>
                <option value="No" @selected($water_available === 'No')>No</option>
                <option value="Unknown" @selected($water_available === 'Unknown')>Unknown</option>
                <option value="Nearby – Available but not connected" @selected($water_available === 'Nearby – Available but not connected')>Nearby – Available but not connected</option>
                <option value="Other" @selected($water_available === 'Other')>Other</option>
            </select>
        </div>
    </div>
    @if ($water_available === 'Other')
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model.defer="water_available_other" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter water availability (e.g., Well required, Utility nearby, Extension needed)">
        </div>
    </div>
    @endif

    <div class="form-group">
        <label class="fw-bold">Sewer Available to Site:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether sewer service is available to the site.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select id="sewer_available" wire:model="sewer_available" class="form-control has-icon" data-icon="fa-solid fa-water">
                <option value="">Select</option>
                <option value="Yes" @selected($sewer_available === 'Yes')>Yes</option>
                <option value="No" @selected($sewer_available === 'No')>No</option>
                <option value="Unknown" @selected($sewer_available === 'Unknown')>Unknown</option>
                <option value="Nearby – Available but not connected" @selected($sewer_available === 'Nearby – Available but not connected')>Nearby – Available but not connected</option>
                <option value="Other" @selected($sewer_available === 'Other')>Other</option>
            </select>
        </div>
    </div>
    @if ($sewer_available === 'Other')
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model.defer="sewer_available_other" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter sewer availability (e.g., Septic required, City sewer nearby)">
        </div>
    </div>
    @endif

    <div class="form-group">
        <label class="fw-bold">Electric Available to Site:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether electric service is available to the site.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="electric_available" class="form-control has-icon" data-icon="fa-solid fa-bolt">
                <option value="">Select</option>
                <option value="Yes" @selected($electric_available === 'Yes')>Yes</option>
                <option value="No" @selected($electric_available === 'No')>No</option>
                <option value="Unknown" @selected($electric_available === 'Unknown')>Unknown</option>
                <option value="Nearby – Available but not connected" @selected($electric_available === 'Nearby – Available but not connected')>Nearby – Available but not connected</option>
                <option value="Other" @selected($electric_available === 'Other')>Other</option>
            </select>
        </div>
    </div>
    @if ($electric_available === 'Other')
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model.defer="electric_available_other" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter electric availability (e.g., Solar only, Generator required)">
        </div>
    </div>
    @endif

    <div class="form-group">
        <label class="fw-bold">Gas Available to Site:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether natural gas service is available to the site.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="gas_available" class="form-control has-icon" data-icon="fa-solid fa-fire-flame-simple">
                <option value="">Select</option>
                <option value="Yes" @selected($gas_available === 'Yes')>Yes</option>
                <option value="No" @selected($gas_available === 'No')>No</option>
                <option value="Unknown" @selected($gas_available === 'Unknown')>Unknown</option>
                <option value="Nearby – Available but not connected" @selected($gas_available === 'Nearby – Available but not connected')>Nearby – Available but not connected</option>
                <option value="Other" @selected($gas_available === 'Other')>Other</option>
            </select>
        </div>
    </div>
    @if ($gas_available === 'Other')
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model.defer="gas_available_other" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter gas availability (e.g., Propane only, Not available)">
        </div>
    </div>
    @endif

    <div class="form-group">
        <label class="fw-bold">Telecom / Internet Available to Site:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether telephone or internet service is available to the site.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="telecom_available" class="form-control has-icon" data-icon="fa-solid fa-tower-broadcast">
                <option value="">Select</option>
                <option value="Yes" @selected($telecom_available === 'Yes')>Yes</option>
                <option value="No" @selected($telecom_available === 'No')>No</option>
                <option value="Unknown" @selected($telecom_available === 'Unknown')>Unknown</option>
                <option value="Nearby – Available but not connected" @selected($telecom_available === 'Nearby – Available but not connected')>Nearby – Available but not connected</option>
                <option value="Other" @selected($telecom_available === 'Other')>Other</option>
            </select>
        </div>
    </div>
    @if ($telecom_available === 'Other')
    <div class="form-group">
        <div class="input-cover">
            <input type="text" wire:model.defer="telecom_available_other" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter telecom availability (e.g., Satellite only, Fiber nearby)">
        </div>
    </div>
    @endif

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

    <div class="form-group">
        <label class="fw-bold">Front Footage:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the front footage of the property in linear feet.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model.defer="front_footage" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-horizontal" placeholder="Enter front footage in feet (e.g., 150)">
        </div>
        <span class="error mt-2" id="front_footage_error"></span>
    </div>

    <div class="form-group" wire:key="road-frontage-vl-{{ $property_type }}">
        <label class="fw-bold">Road Frontage:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of road frontage for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="road_frontage_vacant_land" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-road" data-placeholder="Select" multiple>
                @foreach (['Access Road','Alley','Business District','City Street','County Road','Divided Highway','Easement','Highway','Interchange','Interstate','Main Thoroughfare','Private Road','Rail','State Road','Turn Lanes','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($road_frontage) && in_array($_opt, $road_frontage) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="road_frontage_error_vacant_land"></span>
    </div>
    <div class="form-group" id="other_road_frontage_vacant_land_wrapper" style="{{ is_array($road_frontage) && in_array('Other', $road_frontage) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_road_frontage" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter road frontage type (e.g., Cul-de-sac, Service road)">
        </div>
    </div>

    <div class="form-group" wire:key="road-surface-type-vl-{{ $property_type }}">
        <label class="fw-bold">Road Surface Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the road surface type(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="road_surface_type_vacant_land" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-road" data-placeholder="Select" multiple>
                @foreach (['Asphalt','Brick','Chip And Seal','Concrete','Dirt','Gravel','Limerock','Paved','Unimproved','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($road_surface_type) && in_array($_opt, $road_surface_type) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="road_surface_type_error_vacant_land"></span>
    </div>
    <div class="form-group" id="other_road_surface_type_vacant_land_wrapper" style="{{ is_array($road_surface_type) && in_array('Other', $road_surface_type) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_road_surface_type" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter road surface type (e.g., Cobblestone, Shell)">
        </div>
    </div>

    <div class="form-group" wire:key="utilities-vl-{{ $property_type }}">
        <label class="fw-bold">Utilities:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the utilities available or connected at the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="utilities_vacant_land" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-bolt" data-placeholder="Select" multiple>
                @foreach (['BB/HS Internet Available','BB/HS Internet Capable','Cable Available','Cable Connected','Electrical Nearby','Electricity Available','Fiber Optics','Fire Hydrant','Mini Sewer','Natural Gas Available','Phone Available','Private','Propane','Public','Sewer Available','Sewer Connected','Sewer Nearby','Sprinkler Meter','Sprinkler Recycled','Sprinkler Well','Street Lights','Telephone Nearby','Underground Utilities','Utility Pole','Water - Multiple Meters','Water Available','Water Connected','Water Nearby','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($utilities) && in_array($_opt, $utilities) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="utilities_error_vacant_land"></span>
    </div>
    <div class="form-group" id="other_utilities_vacant_land_wrapper" style="{{ is_array($utilities) && in_array('Other', $utilities) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_utilities" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter utility (e.g., Steam, Geothermal)">
        </div>
    </div>

    <div class="form-group" wire:key="water-vl-{{ $property_type }}">
        <label class="fw-bold">Water:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the water source(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="water_vacant_land" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-droplet" data-placeholder="Select" multiple>
                @foreach (['Canal/Lake For Irrigation','Private','Public','Well','Well Required','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($water) && in_array($_opt, $water) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="water_error_vacant_land"></span>
    </div>
    <div class="form-group" id="other_water_vacant_land_wrapper" style="{{ is_array($water) && in_array('Other', $water) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_water" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter water source (e.g., Rainwater collection, Shared well)">
        </div>
    </div>

    <div class="form-group" wire:key="sewer-vl-{{ $property_type }}">
        <label class="fw-bold">Sewer:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the sewer type(s) for the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="sewer_vacant_land" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-water" data-placeholder="Select" multiple>
                @foreach (['Aerobic Septic','PEP-Holding Tank','Private Sewer','Public Sewer','Septic Needed','Septic Tank','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($sewer) && in_array($_opt, $sewer) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="sewer_error_vacant_land"></span>
    </div>
    <div class="form-group" id="other_sewer_vacant_land_wrapper" style="{{ is_array($sewer) && in_array('Other', $sewer) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_sewer" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter sewer type (e.g., Cesspool, Composting)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Number of Wells:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of wells on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="number_of_wells" class="form-control has-icon"
                data-icon="fa-solid fa-water" placeholder="Enter number of wells (e.g., 1)">
        </div>
        <span class="error mt-2" id="number_of_wells_error"></span>
    </div>

    <div class="form-group">
        <label class="fw-bold">Number of Septics:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the number of septic systems on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model.defer="number_of_septics" class="form-control has-icon"
                data-icon="fa-solid fa-circle-nodes" placeholder="Enter number of septic systems (e.g., 1)">
        </div>
        <span class="error mt-2" id="number_of_septics_error"></span>
    </div>

    <div class="form-group" wire:key="fences-vl-{{ $property_type }}">
        <label class="fw-bold">Fences:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type(s) of fencing on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="fences" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-border-all" data-placeholder="Select" multiple>
                @foreach (['Board','Chain Link','Cross Fenced','Fenced','Split Rail','Vinyl','Wire','Wood','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($fences) && in_array($_opt, $fences) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="fences_error"></span>
    </div>
    <div class="form-group" id="other_fences_wrapper" style="{{ is_array($fences) && in_array('Other', $fences) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_fences" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter fence type (e.g., Electric, invisible)">
        </div>
    </div>

    <div class="form-group" wire:key="vegetation-vl-{{ $property_type }}">
        <label class="fw-bold">Vegetation:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the vegetation type(s) present on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="vegetation" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-tree" data-placeholder="Select" multiple>
                @foreach (['Brush','Cleared','Crop','Oak Trees','Partially Wooded','Pasture','Timber','Trees/Wooded','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($vegetation) && in_array($_opt, $vegetation) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="vegetation_error"></span>
    </div>
    <div class="form-group" id="other_vegetation_wrapper" style="{{ is_array($vegetation) && in_array('Other', $vegetation) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_vegetation" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter vegetation type (e.g., Citrus grove, Vineyard)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Buildable:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether the land is buildable.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="buildable" id="buildable" class="form-control has-icon" data-icon="fa-solid fa-check">
                <option value="">Select</option>
                <option value="Yes" @selected($buildable === 'Yes')>Yes</option>
                <option value="No" @selected($buildable === 'No')>No</option>
            </select>
        </div>
        <span class="error mt-2" id="buildable_error"></span>
    </div>

    <div class="form-group" wire:key="easements-vl-{{ $property_type }}">
        <label class="fw-bold">Easements:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any easements that exist on or affect the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="easements" class="form-control has-icon select2-multiple" data-icon="fa-solid fa-road" data-placeholder="Select" multiple>
                @foreach (['Access Road','Drainage','Electric','Telephone','Utilities','Water','None','Other'] as $_opt)
                    <option value="{{ $_opt }}" {{ is_array($easements) && in_array($_opt, $easements) ? 'selected' : '' }}>{{ $_opt }}</option>
                @endforeach
            </select>
        </div>
        <span class="error mt-2" id="easements_error"></span>
    </div>
    <div class="form-group" id="other_easements_wrapper" style="{{ is_array($easements) && in_array('Other', $easements) ? '' : 'display:none;' }}">
        <div class="input-cover">
            <input type="text" wire:model.defer="other_easements" class="form-control has-icon"
                data-icon="fa-solid fa-pen" placeholder="Enter easement type (e.g., Drainage, mineral rights)">
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
</script>
