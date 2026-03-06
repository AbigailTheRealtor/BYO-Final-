@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
<style>
    /* Your custom styles here */
    .wizard-steps-progress {
        height: 5px;
        width: 100%;
        background-color: #CCC;
        position: absolute;
        top: 0;
        left: 0;
    }

    .steps-progress-percent {
        height: 100%;
        width: 0%;
        background-color: #11b7cf;
    }

    .wizard-step {
        display: none;
    }

    .wizard-step.active {
        display: block;
    }

    .tab-content {
        padding: 20px;
        border: 1px solid #ddd;
        border-top: none;
    }

    .nav-tabs .nav-link {
        border: 1px solid #ddd;
        border-bottom: none;
        margin-right: 5px;
        padding: 10px 20px;
        background-color: #f8f9fa;
    }

    .nav-tabs .nav-link.active {
        background-color: #fff;
        border-bottom: 1px solid #fff;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        font-weight: bold;
    }

    .form-control {
        min-height: 50px;
    }

    .input-cover .form-control {
        padding-left: 50px;
        /* Ensure the input text doesn't overlap the icon */
        width: 100%;
        /* Ensure the input field takes full width */
    }

    .nav-tabs .nav-link.active {
        background-color: #049399 !important;
        color: white !important;
        border-color: #049399 !important;
    }

    .input-cover {
        position: relative;
        display: flex;
        align-items: center;
        /* Center the icon vertically */
    }

    .input-cover .input-icon {
        position: absolute;
        left: 10px;
        font-size: 25px;
        color: #11b7cf;
        pointer-events: none;
        top: 50%;
        transform: translateY(-50%);
        /* Center the icon vertically */
    }

    .has-icon {
        padding-left: 40px;
    }

    .error {
        display: block;
        color: red;
        font-size: 14px;
        margin-top: 5px;
        /* Add space between input-cover and error message */
        width: 100%;
        /* Ensure the error message takes full width */
    }

    .d-none {
        display: none;
    }

    .hidden {
        display: none;
    }

    .badge {
        /* font-size: 0.9rem;
                                                                                                                                                                                                                                        padding: 0.5em 0.75em; */
        display: inline-flex;
        align-items: center;
    }

    .badge a {
        opacity: 0.7;
    }

    .badge a:hover {
        opacity: 1;
        text-decoration: none;
    }

    .autocomplete-dropdown {
        position: absolute;
        z-index: 1000;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 0 0 4px 4px;
        background: white;
        margin-top: 232px;
    }

    .autocomplete-dropdown .list-group-item {
        cursor: pointer;
        border: none;
        border-bottom: 1px solid #f0f0f0;
        padding: 0.4rem 1rem;
        /* Reduced padding */
        font-size: 0.875rem;
        /* Slightly smaller font */
        display: flex;
        align-items: center;
        min-height: 36px;
    }

    .autocomplete-dropdown .list-group-item:hover {
        background-color: #f8f9fa;
    }

    .autocomplete-dropdown-counties {
        position: absolute;
        z-index: 1000;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 0 0 4px 4px;
        background: white;
        margin-top: 88px;
    }

    .autocomplete-dropdown-counties .list-group-item {
        cursor: pointer;
        border: none;
        border-bottom: 1px solid #f0f0f0;
        padding: 0.4rem 1rem;
        /* Reduced padding */
        font-size: 0.875rem;
        /* Slightly smaller font */
        display: flex;
        align-items: center;
        min-height: 36px;
    }

    .autocomplete-dropdown-counties .list-group-item:hover {
        background-color: #f8f9fa;
    }

    #save-button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
        /* This prevents clicks */
    }

    .service-option-card {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .service-option-card:hover {
        border-color: #adb5bd;
        transform: translateY(-2px);
    }

    .service-option-card.active-service {
        border-color: #0d6efd;
        box-shadow: 0 0 0 1px #0d6efd;
        background-color: #f8f9fa;
    }

    .service-option-card .form-check-input {
        position: absolute;
        opacity: 0;
    }

    .service-option-card .form-check-label {
        cursor: pointer;
        width: 100%;
    }

    .service-option-card ul li {
        position: relative;
        padding-left: 0;
    }

    .btn-status {
        transition: all 0.3s ease;
        border-width: 2px;
        font-weight: 500;
        padding-top: 0.6rem;
        padding-bottom: 0.6rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-status:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-check:checked+.btn-status {
        color: white !important;
        border-color: transparent;
    }

    .btn-check:checked+.btn-outline-success {
        background-color: var(--bs-success);
    }

    .btn-check:checked+.btn-outline-warning {
        background-color: var(--bs-warning);
        color: #000 !important;
    }

    .btn-check:checked+.btn-outline-primary {
        background-color: var(--bs-primary);
    }

    .status-icon {
        font-size: 1.1rem;
        margin-right: 0.5rem;
    }

    .status-badge {
        font-size: 0.6rem;
        padding: 0.35em 0.4em;
        display: none;
    }

    .btn-check:checked+.btn-status .status-badge {
        display: block;
    }

    /* Style for the select2 dropdown */
    .select2-container--default .select2-results>.select2-results__options {
        max-height: 300px;
        /* Reduced from default 400px */
        overflow-y: auto;
    }

    /* Style for individual options */
    .select2-container--default .select2-results__option {
        padding: 6px 12px;
        /* Reduced padding */
        font-size: 0.9rem;
        /* Slightly smaller font */
        line-height: 1.3;
        /* Tighter line spacing */
    }

    /* Style for the dropdown container */
    .select2-container--default .select2-selection--multiple {
        min-height: 38px;
        /* Standard form control height */
        padding: 2px 8px;
    }

    .tooltip-inner {
        font-size: 12px;
        /* Decrease text size */
        padding: 12px 16px;
        /* Increase padding: top-bottom 12px, left-right 16px */
        max-width: 250px;
        /* Optional: set max width */
        text-align: center;
        /* Optional: center text */
        background-color: #222;
        /* Optional: override background */
        color: #fff;
        /* Optional: override text color */
    }

    .tooltip.bs-tooltip-top .tooltip-arrow::before,
    .tooltip.bs-tooltip-bottom .tooltip-arrow::before,
    .tooltip.bs-tooltip-left .tooltip-arrow::before,
    .tooltip.bs-tooltip-right .tooltip-arrow::before {
        background-color: #222;
        /* Match the tooltip background */
    }

    @media (max-width: 768px) {
        .status-text {
            font-size: 0.9rem;
        }

        .status-icon {
            margin-right: 0.3rem;
            font-size: 1rem;
        }

        .user-selected {
            margin-left: -15px !important;
        }
    }

    @media (max-width: 320px) {
        .commission-based-agent {
            margin-left: -43px !important;
        }
    }

    .compensation_tab {
        display: contents;
    }

    .input-cover {
        position: relative;
    }

    .input-icon-end {
        font-size: 25px;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #11b7cf;
    }

    .input-group-text-seller {
        display: flex;
        align-items: center;
        padding: 0.7rem 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        text-align: center;
        white-space: nowrap;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: .375rem;
    }

    .input-group-text-seller+input.form-control {
        padding-left: 8px;
    }

    .percentage-value-set {
        padding-left: 9px !important;
    }

    .user-selected {
        color: #0ce7ef;
        font-size: 18px;
    }

    .set-button {
        gap: 3px;
    }
</style>
@endpush

@php

$property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];

$property_condition = [
['name' => 'Updated / Renovated'],
['name' => 'Partially updated (some older finishes OK)'],
['name' => 'Older but clean & well maintained'],
['name' => 'No preference (open to any condition)'],
];
$property_condition_seller = [
['name' => 'No updates needed: Completely updated'],
['name' => 'Currently being built'],
['name' => 'New Construction'],
['name' => 'Not updated: Requires a complete update'],
['name' => 'Pre-Construction'],
['name' => 'Semi-updated: Needs minor updates'],
['name' => 'Tear Down: Requires complete demolition and reconstruction'],
];
$property_condition_landlord = [
['name' => 'New Construction'],
['name' => 'No updates needed: Completely updated'],
['name' => 'Semi-updated: Needs minor updates'],
['name' => 'Not updated: Requires a complete update'],
];
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

$bathrooms = [
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

$acreageRes = [
['name' => '0 to less than 1/4 acre'],
['name' => '1/4 to less than 1/2 acre'],
['name' => '1/2 to less than 1 acre'],
['name' => '1 to less than 2 acres'],
['name' => '2 to less than 5 acres'],
['name' => '5 to less than 10 acres'],
['name' => '10 to less than 20 acres'],
['name' => '20 to less than 50 acres'],
['name' => '50 to less than 100 acres'],
['name' => '100 to less than 200 acres'],
['name' => '200 to less than 500 acres'],
['name' => '500+ acres'],
['name' => 'Non-Applicable'],
];

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
$preferences_seller = [
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

$appliances = [
['name' => 'Bar Fridge'],
['name' => 'Built-In Oven'],
['name' => 'Convection Oven'],
['name' => 'Cooktop'],
['name' => 'Dishwasher'],
['name' => 'Disposal'],
['name' => 'Dryer'],
['name' => 'Electric Water Heater'],
['name' => 'Exhaust Fan'],
['name' => 'Freezer'],
['name' => 'Gas Water Heater'],
['name' => 'Ice Maker'],
['name' => 'Indoor Grill'],
['name' => 'Kitchen Reverse Osmosis System'],
['name' => 'Microwave'],
['name' => 'Range Electric'],
['name' => 'Range Gas'],
['name' => 'Range Hood'],
['name' => 'Refrigerator'],
['name' => 'Solar Hot Water'],
['name' => 'Solar Hot Water Owned'],
['name' => 'Solar Hot Water Rented'],
['name' => 'Tankless Water Heater'],
['name' => 'Touchless Faucet'],
['name' => 'Trash Compactor'],
['name' => 'Washer'],
['name' => 'Water Filtration System'],
['name' => 'Water Purifier'],
['name' => 'Water Softener'],
['name' => 'Whole House R.O. System'],
['name' => 'Wine Refrigerator'],
['name' => 'None'],
['name' => 'Other'],
];
$non_negotialble_terms = [
['name' => '55 and Over Community', 'class' => 'residential-length'],
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
$tenantPays = [
['name' => 'Association Fees'],
['name' => 'Capital Expenses'],
['name' => 'Common Area Maintenance'],
['name' => 'Condominium Fees'],
['name' => 'Electricity'],
['name' => 'Gas'],
['name' => 'Liability Insurance'],
['name' => 'Parking Fee'],
['name' => 'Pro-Rated'],
['name' => 'Property Insurance'],
['name' => 'Property Taxes'],
['name' => 'Reserves'],
['name' => 'Sewer'],
['name' => 'Trash Collection'],
['name' => 'Water'],
// ['name' => 'None'],
['name' => 'Other'],
];

$ownerPays = [
['name' => 'Cable TV'],
['name' => 'Electricity'],
['name' => 'Gas'],
['name' => 'Grounds Care'],
['name' => 'Insurance'],
['name' => 'Internet'],
['name' => 'Laundry'],
['name' => 'Management'],
['name' => 'Pest Control'],
['name' => 'Pool Maintenance'],
['name' => 'Recreational'],
['name' => 'Repairs'],
['name' => 'Security'],
['name' => 'Sewer'],
['name' => 'Taxes'],
['name' => 'Telephone'],
['name' => 'Trash Collection'],
['name' => 'Water'],
['name' => 'None'],
['name' => 'Other'],
];
$purchasing_props = [['name' => 'Not Age-Restricted'], ['name' => '55+ Community'], ['name' => '62+ Community']];

$lease_for = [
['name' => '3 Months'],
['name' => '6 Months'],
['name' => '9 Months'],
['name' => '1 Year'],
['name' => '2 Years'],
['name' => '3-5 Years'],
['name' => '5+ Years'],
['name' => 'Other'],
];
$lease_for_res = [
['name' => '3 Months'],
['name' => '6 Months'],
['name' => '9 Months'],
['name' => '1 Year'],
['name' => '2 Years'],
['name' => 'Month to Month'],
['name' => 'Other'],
];
$lease_for_com = [
['name' => '6 Months'],
['name' => '1 Year'],
['name' => '2 Years'],
['name' => '3 to 5 Years'],
['name' => '6+ Years'],
['name' => 'Month to Month'],
['name' => 'Other'],
];

// Define the updated services array
$services = [
['name' => 'List the Tenant’s rental criteria on BidYourOffer.com.'],
[
'name' =>
'Market the Tenant’s rental criteria across various real estate groups, pages, and affiliates directing interested parties to the Tenant’s criteria listing on BidYourOffer.com.',
],
[
'name' =>
'Promote the Tenant’s rental criteria on social media platforms directing interested parties to the Tenant’s criteria listing on BidYourOffer.com.',
],
[
'name' =>
'Launch an online marketing campaign to drive traffic to the Tenant’s criteria listing on BidYourOffer.com.',
],
[
'name' =>
'Conduct email marketing campaigns targeting agents and potential Landlords, linking to the Tenant’s criteria listing on BidYourOffer.com.',
],
[
'name' =>
'Implement neighborhood marketing efforts in the Tenant’s desired area directing interested parties to the Tenant’s criteria listing on BidYourOffer.com.',
],
[
'name' =>
'Send prompt email notifications with properties that match the Tenant’s criteria as soon as they are listed, ensuring access to the most up-to-date options.',
],
['name' => 'Schedule and accompany the Tenant on property viewings and showings.'],
['name' => 'Arrange video tours of the Tenant’s preferred properties.'],
[
'name' =>
'Conduct a thorough Rental Market Analysis (RMA) to assess property values and rental pricing strategies.',
],
['name' => 'Assist with the Tenant’s rental application process, providing guidance and support.'],
['name' => 'Help the Tenant understand lease terms and potential penalties before signing.'],
[
'name' =>
'Negotiate lease terms on behalf of the Tenant, including rental price, lease duration, and additional clauses or provisions.',
],
['name' => 'Coordinate with property managers, Landlords, and Agents to expedite application processing.'],
['name' => 'Coordinate and oversee the move-in process, including inspections and key handovers.'],
['name' => 'Advocate for security deposit refunds and ensure fair lease terms.'],
[
'name' =>
'Provide moving assistance resources, including utility setup, moving companies, and renter’s insurance.',
],
[
'name' =>
'Help the Tenant establish a rental history report through recognized services (e.g., Experian RentBureau, RentReporters, or similar platforms) to support future leasing or homeownership goals.',
],
['name' => 'Provide guidance on lease renewal options and negotiate rent adjustments if necessary.'],
['name' => 'Other – Specify additional services as needed.'],
];

$property_items = [
['name' => '1/2 Duplex', 'class' => 'residential-length'],
['name' => '1/3 Triplex', 'class' => 'residential-length'],
['name' => '1/4 Quadplex', 'class' => 'residential-length'],
['name' => 'Apartment', 'class' => 'residential-length'],
['name' => 'Condo-Hotel', 'class' => 'residential-length'],
['name' => 'Condominium', 'class' => 'residential-length'],
['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
['name' => 'Farm', 'class' => 'residential-length'],
['name' => 'Garage Condo', 'class' => 'residential-length'],
['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
['name' => 'Modular Home', 'class' => 'residential-length'],

['name' => 'Townhouse', 'class' => 'residential-length'],
['name' => 'Single Family Residence', 'class' => 'residential-length'],
['name' => 'Unimproved Land', 'class' => 'residential-length'],

['name' => 'Villa', 'class' => 'residential-length'],

['name' => 'Duplex', 'class' => 'income-length'],
['name' => 'Triplex', 'class' => 'income-length'],
['name' => 'Quadplex', 'class' => 'income-length'],
['name' => 'Five or More (Residential units)', 'class' => 'income-length'],

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
];
$property_items_seller = [
['name' => '1/2 Duplex', 'class' => 'residential-length'],
// ['name' => '1/3 Triplex', 'class' => 'residential-length'],
// ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
// ['name' => 'Apartments', 'class' => 'residential-length'],
['name' => 'Condo-Hotel', 'class' => 'residential-length'],
['name' => 'Condominium', 'class' => 'residential-length'],
['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
['name' => 'Farm', 'class' => 'residential-length'],
['name' => 'Garage Condo', 'class' => 'residential-length'],
['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
['name' => 'Modular Home', 'class' => 'residential-length'],

['name' => 'Townhouse', 'class' => 'residential-length'],
['name' => 'Single Family Residence', 'class' => 'residential-length'],
// ['name' => 'Unimproved Land', 'class' => 'residential-length'],

['name' => 'Villa', 'class' => 'residential-length'],

['name' => 'Duplex', 'class' => 'income-length'],
['name' => 'Triplex', 'class' => 'income-length'],
['name' => 'Quadplex', 'class' => 'income-length'],
['name' => 'Five or More (Residential units)', 'class' => 'income-length'],

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
// ['name' => 'Orchard', 'class' => 'vacant-land-length'],
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

$property_items_buyer = [
// Residential (alphabetical order)
['name' => '1/2 Duplex', 'class' => 'residential-length'],
// ['name' => '1/3 Triplex', 'class' => 'residential-length'],
// ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
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
// ['name' => 'Orchard', 'class' => 'vacant-land-length'],
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

// commercial
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
];

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

$credit_score = [['name' => 'Poor'], ['name' => 'Fair'], ['name' => 'Good'], ['name' => 'Excellent']];

$auction_lengths = [
['name' => '1 Day'],
['name' => '3 Days'],
['name' => '5 Days'],
['name' => '7 Days'],
['name' => '10 Days'],
['name' => '14 Days'],
['name' => '21 Days'],
['name' => '30 Days'],
['name' => '45 Days'],
['name' => '60 Days'],
['name' => '75 Days'],
['name' => '90 Days'],
// ['name' => 'No time limit'],
];
$auction_lengths_seller = [
['name' => '1 Day'],
['name' => '3 Days'],
['name' => '5 Days'],
['name' => '7 Days'],
['name' => '10 Days'],
['name' => '14 Days'],
// ['name' => '21 Days'],
// ['name' => '30 Days'],
// ['name' => '45 Days'],
// ['name' => '60 Days'],
// ['name' => '75 Days'],
// ['name' => '90 Days'],
// ['name' => 'No time limit'],
];
$auction_lengths_flat_fee = [
['name' => '1 hour'],
['name' => '2 hours'],
['name' => '3 hours'],
['name' => '4 hours'],
['name' => '5 hours'],
['name' => '6 hours'],
['name' => '7 hours'],
['name' => '8 hours'],
['name' => '9 hours'],
['name' => '10 hours'],
['name' => '11 hours'],
['name' => '12 hours'],
['name' => '1 day'],
['name' => '2 days'],
['name' => '3 days'],
['name' => '5 days'],
['name' => '7 days'],
['name' => '10 days'],
['name' => '14 days'],
['name' => '21 days'],
['name' => '30 days'],
];
$leasing_space = [
[
'name' =>
'Guests are [allowed/not allowed]; any restrictions include [insert details, e.g., visiting hours, overnight stay rules]',
'class' => 'residential-length',
],
[
'name' => 'Tenants have access to common areas such as the [kitchen/living room/backyard]',
'class' => 'residential-length',
],
[
'name' =>
'Maintenance issues are handled by [landlord/property manager/agent/tenant]; response time is typically [insert timeframe]',
'class' => 'residential-length',
],
[
'name' => 'Utilities: [included in rent/split among tenants/individually metered]',
'class' => 'residential-length',
],
[
'name' =>
'Common areas are cleaned and maintained [insert frequency and by whom, e.g., weekly by landlord]',
'class' => 'residential-length',
],
[
'name' =>
'Storage space available includes [insert size or type, e.g., closet, basement section, garage shelf]',
'class' => 'residential-length',
],
[
'name' => 'Bathroom facilities are [private/shared]',
'class' => 'residential-length',
],
[
'name' => 'The room available for lease is approximately [insert square footage]',
'class' => 'residential-length',
],

[
'name' => 'Shared amenities include [e.g., conference rooms, parking facilities]',
'class' => 'commercial-length',
],
[
'name' => 'Building hours are [insert hours], with [yes/no] 24/7 access available',
'class' => 'commercial-length',
],
[
'name' => 'Zoning allows for [insert permitted uses]; restrictions may apply',
'class' => 'commercial-length',
],
[
'name' =>
'Maintenance and repairs are handled by [e.g., property management, agent, landlord, tenant responsibilities]',
'class' => 'commercial-length',
],
[
'name' => 'Utilities are [insert details on how they\'re divided or included]',
'class' => 'commercial-length',
],
[
'name' => 'Common areas are professionally cleaned and maintained on a [daily/weekly] basis',
'class' => 'commercial-length',
],
[
'name' => 'The space features a [describe layout, e.g., open floor plan, private offices, etc.]',
'class' => 'commercial-length',
],
[
'name' => 'Storage space of [insert size or description] is included',
'class' => 'commercial-length',
],
[
'name' => 'A designated reception area is [included/not included]',
'class' => 'commercial-length',
],
[
'name' => 'The leasable unit is approximately [insert square footage]',
'class' => 'commercial-length',
],
[
'name' => 'Neighboring tenants include [list business types or names if notable]',
'class' => 'commercial-length',
],
];
$acceptable_leasing_space = $property_type === 'Commercial Property' 
    ? [
        ['name' => 'Entire Property'],
        ['name' => 'Single Room'],
    ]
    : [
        ['name' => 'Accessory Unit / Guest Suite (ADU)'],
        ['name' => 'Entire Property'],
        ['name' => 'Single Room'],
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
// ['name' => 'Other'],
];
$unit_types_buyer = [
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

$seller_property = [
[
'name' => 'Assignment Contract',
'description' =>
'The Seller is assigning their contractual rights to another Buyer (commonly used in wholesaling).⚠️ If the Seller is under contract to purchase a property and intends to assign their purchase rights to another Buyer, they are considered the Seller of that contract. If the Seller is looking to purchase an assignment contract, they are considered the Buyer of that contract. In that case, please switch to a Buyer listing instead.',
],
['name' => 'Auction', 'description' => 'The property will be sold through a public bidding process.'],
[
'name' => 'Bank Owned/REO',
'description' => 'The property has been foreclosed on and is now owned by the bank (Real Estate Owned).',
],
[
'name' => 'Government Owned',
'description' => 'The property is owned by a government entity (e.g., HUD, VA).',
],
[
'name' => 'Probate Listing',
'description' =>
"The property is part of a deceased owner's estate and requires probate court approval to sell.",
],
[
'name' => 'Short Sale',
'description' =>
'The Seller owes more on the mortgage than the property’s value and is seeking lender approval to sell for less.',
],
['name' => 'None', 'description' => 'No special sale provisions apply — standard sale.'],
['name' => 'Other', 'description' => 'A special provision not listed; user should specify details.'],
];

$buyer_property = [
[
'name' => 'Assignment Contract',
'description' => 'The Buyer is open to purchasing a property where the Seller is assigning their contractual rights to another Buyer (commonly used in wholesaling).
⚠️If the Buyer is looking to purchase an assignment contract, they are considered the Buyer of that contract. If the Buyer is already under contract and wishes to assign their rights to another Buyer, they are considered the Seller of that contract. In that case, please switch to a Seller listing instead.',
],
[
'name' => 'Auction',
'description' => 'The Buyer is willing to purchase a property through a public bidding process.',
],
[
'name' => 'Bank Owned/REO',
'description' =>
'The Buyer is open to properties that have been foreclosed on and are now owned by the bank (Real Estate Owned)',
],
[
'name' => 'Government Owned',
'description' => 'The Buyer is open to properties owned by a government entity (e.g., HUD, VA).',
],
[
'name' => 'Probate Listing',
'description' =>
'The Buyer is open to purchasing properties that are part of a deceased owner’s estate and require probate court approval to sell.',
],
[
'name' => 'Short Sale',
'description' =>
"The Buyer is willing to purchase a property where the Seller owes more than the property's market value and needs lender approval to sell for less.",
],
[
'name' => 'None',
'description' => 'The Buyer is only seeking standard sales with no special sale provisions.',
],
[
'name' => 'Other',
'description' =>
'The Buyer is open to a special sale scenario not listed here — please specify the details.',
],
];

$financing_options_seller = [
[
'name' => 'Assumable',
'description' =>
'Allows an existing mortgage to be assumed by a Buyer, subject to lender approval. This may be beneficial if the existing interest rate is lower than current market rates.',
],
[
'name' => 'Cash',
'description' => 'Purchase is completed without financing, with the full price paid in cash and no financing contingency.',
],
[
'name' => 'Conventional',
'description' => 'Uses a traditional mortgage that meets standard underwriting guidelines.',
],
[
'name' => 'FHA',
'description' =>
'Uses a loan backed by the Federal Housing Administration, typically requiring the property to meet condition standards.',
],
[
'name' => 'Jumbo',
'description' =>
'Uses a loan that exceeds conforming loan limits and often requires stricter borrower qualifications.',
],
[
'name' => 'VA',
'description' =>
'Uses a VA-backed loan available to eligible veterans and active-duty service members.',
],
[
'name' => 'No-Doc',
'description' =>
'Uses a loan requiring limited or no income documentation.',
],
[
'name' => 'Non-QM',
'description' =>
'Uses a Non-Qualified Mortgage that allows alternative income verification methods.',
],
[
'name' => 'USDA',
'description' =>
'Uses a USDA-backed loan for eligible rural properties and qualifying buyers.',
],
[
'name' => 'Cryptocurrency',
'description' =>
'Uses digital currency (e.g., Bitcoin or Ethereum) as full or partial consideration, subject to Seller acceptance.',
],
[
'name' => 'Exchange/Trade',
'description' => 'Includes another asset as part of the purchase consideration in a trade.',
],
[
'name' => 'Lease Option',
'description' =>
'Allows the property to be leased with an option to purchase later under pre-agreed terms.',
],
[
'name' => 'Lease Purchase',
'description' =>
'Allows the property to be leased now with a commitment to purchase later, often with a portion of rent credited toward the purchase price.',
],
[
'name' => 'Non-Fungible Token (NFT)',
'description' =>
'Uses a verified digital asset as full or partial consideration, subject to Seller approval.',
],
[
'name' => 'Seller Financing',
'description' => 'Purchase price is financed in whole or in part directly by the Seller.',
],
[
'name' => 'Other',
'description' => 'Uses an alternative financing or consideration method not listed above.',
],
];
$financing_options = [
[
'name' => 'Assumable',
'description' =>
'Allows an existing mortgage to be assumed by a Buyer, subject to lender approval. This may be beneficial if the existing interest rate is lower than current market rates.',
],
[
'name' => 'Cash',
'description' => 'Purchase is completed without financing, with the full price paid in cash and no financing contingency.',
],
[
'name' => 'Conventional',
'description' => 'Uses a traditional mortgage that meets standard underwriting guidelines.',
],
[
'name' => 'Cryptocurrency',
'description' =>
'Uses digital currency (e.g., Bitcoin or Ethereum) as full or partial consideration, subject to Seller acceptance.',
],
[
'name' => 'Exchange/Trade',
'description' => 'Includes another asset as part of the purchase consideration in a trade.',
],
[
'name' => 'FHA',
'description' =>
'Uses a loan backed by the Federal Housing Administration, typically requiring the property to meet condition standards.',
],
[
'name' => 'Jumbo',
'description' =>
'Uses a loan that exceeds conforming loan limits and often requires stricter borrower qualifications.',
],
[
'name' => 'Lease Option',
'description' =>
'Allows the property to be leased with an option to purchase later under pre-agreed terms.',
],
[
'name' => 'Lease Purchase',
'description' =>
'Allows the property to be leased now with a commitment to purchase later, often with a portion of rent credited toward the purchase price.',
],
[
'name' => 'Non-Fungible Token (NFT)',
'description' =>
'Uses a verified digital asset as full or partial consideration, subject to Seller approval.',
],
[
'name' => 'No-Doc',
'description' =>
'Uses a loan requiring limited or no income documentation.',
],
[
'name' => 'Non-QM',
'description' =>
'Uses a Non-Qualified Mortgage that allows alternative income verification methods.',
],
[
'name' => 'Seller Financing',
'description' => 'Purchase price is financed in whole or in part directly by the Seller.',
],
[
'name' => 'USDA',
'description' =>
'Uses a USDA-backed loan for eligible rural properties and qualifying buyers.',
],
[
'name' => 'VA',
'description' =>
'Uses a VA-backed loan available to eligible veterans and active-duty service members.',
],
[
'name' => 'Other',
'description' => 'Uses an alternative financing or consideration method not listed above.',
],
];

$occupant_types = [['name' => 'Tenant'], ['name' => 'Vacant'], ['name' => 'Occupied']];
$occupant_types_seller = [['name' => 'Owner'], ['name' => 'Tenant'], ['name' => 'Vacant']];
$desired_rental_amount = [['name' => 'Tenant'], ['name' => 'Vacant'], ['name' => 'Occupied']];

$lease_term_options = [
['name' => '1–7 Days'],
['name' => '1 Week'],
['name' => '2 Weeks'],
['name' => '1 Month'],
['name' => '2 Months'],
['name' => '3 Months'],
['name' => '4 Months'],
['name' => '5 Months'],
['name' => '6 Months'],
['name' => '7 Months'],
['name' => '8–12 Months'],
['name' => '1–2 Years'],
['name' => '2+ Years'],
['name' => 'Month-to-Month'],
['name' => 'Seasonal Lease'],
['name' => 'No Minimum / Flexible'],
];

$residential_lease_term_options = [
['name' => '3 Months'],
['name' => '6 Months'],
['name' => '9 Months'],
['name' => '1 Year'],
['name' => '2 Years'],
['name' => 'Month-to-Month'],
];

$Commercial_lease_term_options = [
['name' => '6 Months'],
['name' => '1 Year'],
['name' => '2 Years'],
['name' => '3-5 Years'],
['name' => '6+ Years'],
['name' => 'Month-to-Month'],
];

$rent_includes = [
['name' => 'Cable TV'],
['name' => 'Electricity'],
['name' => 'Gas'],
['name' => 'Grounds Care'],
['name' => 'Insurance'],
['name' => 'Internet'],
['name' => 'Laundry'],
['name' => 'Management'],
['name' => 'Pest Control'],
['name' => 'Pool Maintenance'],
['name' => 'Recreational'],
['name' => 'Repairs'],
['name' => 'Security'],
['name' => 'Sewer'],
['name' => 'Taxes'],
['name' => 'Telephone'],
['name' => 'Trash Collection'],
['name' => 'Water'],
['name' => 'None'],
['name' => 'Other'],
];
$lease_types = [
['name' => 'Absolute (Triple) Net'],
// ['name' => 'Triple Net (NNN)'],
['name' => 'Gross Lease'],
['name' => 'Gross Percentages'],
['name' => 'Ground Lease'],
['name' => 'Lease Option'],
['name' => 'Modified Gross'],
['name' => 'Net Lease'],
['name' => 'Net Net'],
['name' => 'Pass Throughs'],
['name' => 'Purchase Option'],
['name' => 'Renewal Option'],
['name' => 'Sale-Leaseback'],
['name' => 'Seasonal'],
['name' => 'Special Available (CLO)'],
['name' => 'Varied Terms'],
['name' => 'Other'],
// ['name' => 'Full Service Gross'],
// ['name' => 'Percentage Lease'],
];

@endphp

<div class="container pt-5 pb-5">
    <div class="card">
        <div class="row">
            <div class="col-12 p-4">

                <!-- DEBUG: {{ $listingId ? 'DRAFT/EDIT MODE - LISTING ID: ' . $listingId : 'CREATE MODE' }} - COMPONENT: TenantAgentAuction (CREATE) -->

                @if ($hasDrafts && !$listingId)
                <div class="modal fade" id="draftModal" tabindex="-1" aria-labelledby="draftModalLabel"
                    aria-hidden="true" wire:ignore.self>
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="draftModalLabel">Load Saved Draft</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>You have saved drafts. Would you like to load one?</p>
                                <div class="list-group">
                                    @foreach ($this->getDrafts() as $draft)
                                    @php $draftVersion = $draft->info('draft_version') ?? null; @endphp
                                    <div
                                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <a class="btn btn-link text-start flex-grow-1"
                                            href="{{ route('hire.agent.auction.draft', ['user_type' => $user_type, 'listingId' => $draft->id]) }}">
                                            {{ $draft->title }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" style="border-color: #dc3545; color: #dc3545;"
                                            data-bs-dismiss="modal"
                                            wire:click="deleteDraft('{{ $draft->id }}')" wire:ignore.self
                                            onclick="setTimeout(() => { window.location = '{{ route('hire.agent.auction' , ['user_type' => $user_type])}}' }, 100)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" style="background-color: #6c757d; color: #fff; border-color: #6c757d;" data-bs-dismiss="modal"
                                    wire:click="startNew">
                                    Start New
                                </button>
                                <button type="button" class="btn btn-danger" style="background-color: #dc3545; color: #fff; border-color: #dc3545;" data-bs-dismiss="modal"
                                    wire:click="deleteAllDrafts" wire:ignore.self
                                    onclick="setTimeout(() => { window.location = '{{ route('hire.agent.auction' , ['user_type' => $user_type])}}' }, 100)">
                                    <i class="fas fa-trash me-1"></i> Delete All Drafts
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- @if (session()->has('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
            </div>
            @endif

            @if (session()->has('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
            @endif --}}

            <div id="wizard-form-container" class="container pt-5 pb-5" data-service-type="{{ $service_type }}">

                <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                    <strong>Please complete the required fields before submitting.</strong>
                    <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                </div>

                <form id="create-auction-form" wire:submit.prevent="store">
                    <!-- Tab Navigation -->

                    @if ($service_type === 'full_service')

                    @php
                    // Safe slug function - removes special chars, keeps only a-z, 0-9, and dashes
                    $safeSlug = function($str) {
                        $slug = strtolower($str);
                        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug); // Remove special chars like &
                        $slug = preg_replace('/[\s]+/', '-', $slug); // Replace spaces with dashes
                        $slug = preg_replace('/-+/', '-', $slug); // Collapse multiple dashes
                        return trim($slug, '-');
                    };

                    $baseTabs = ['Listing Details'];

                    // Conditionally set Property tab label based on user type
                    $propertyTab = match ($user_type) {
                    'tenant', 'buyer' => 'Property Preferences',
                    'seller', 'landlord' => 'Property Details',
                    };

                    $propertyId = $safeSlug($propertyTab);

                    // Define rest tabs excluding 'Pre-Screening' for landlord
                    $firstRest =
                    $user_type === 'buyer'
                    ? 'Purchasing Terms'
                    : ($user_type === 'seller'
                    ? 'Sale Terms'
                    : 'Leasing Terms');
                    $restTabs = [$firstRest, 'Services', 'Additional Details', 'Broker Compensation & Agency Agreement Terms'];
                    if ($user_type !== 'landlord' and $user_type !== 'buyer' and $user_type !== 'seller') {
                    array_splice($restTabs, 1, 0, 'Pre-Screening');
                    }

                    $infoTabs = [
                    'tenant' => 'Tenant Information',
                    'seller' => 'Seller Information',
                    'buyer' => 'Buyer Information',
                    'landlord' => 'Landlord Information',
                    ];

                    $allTabs = array_merge($baseTabs, [$propertyTab], $restTabs, [
                    $infoTabs[$user_type] ?? null,
                    ]);
                    @endphp

                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        @foreach ($allTabs as $index => $tab)
                        @if ($tab)
                        @php $tabSlug = $safeSlug($tab); @endphp
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                wire:click="setActiveTab({{ $index }})"
                                id="{{ $tabSlug }}-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#{{ $tabSlug }}"
                                type="button" role="tab"
                                aria-controls="{{ $tabSlug }}"
                                aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                {{ $tab }}
                            </button>
                        </li>
                        @endif
                        @endforeach
                    </ul>
                    @else
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        @foreach (['Listing Details', 'Location and Meeting Details', 'Service Selection and Pricing', 'Additional Details'] as $index => $tab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                wire:click="setActiveTab({{ $index }})"
                                id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab" data-bs-toggle="tab"
                                data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}"
                                type="button" role="tab"
                                aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                {{ $tab }}
                            </button>
                        </li>
                        @endforeach

                        <!-- Dynamic Information Tab Based on User Type -->
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                wire:click="setActiveTab(4)" id="information-tab" data-bs-toggle="tab"
                                data-bs-target="#information" type="button" role="tab"
                                aria-controls="information"
                                aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                @if ($user_type === 'tenant')
                                Tenant Information
                                @elseif($user_type === 'seller')
                                Seller Information
                                @elseif($user_type === 'buyer')
                                Buyer Information
                                @elseif($user_type === 'landlord')
                                Landlord Information
                                @endif
                            </button>
                        </li>
                    </ul>

                    @endif

                    <!-- Tab Content -->
                    <div class="tab-content" id="myTabContent">

                        <!-- Listing Details Tab -->
                        <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}" id="listing-details"
                            role="tabpanel" aria-labelledby="listing-details-tab">
                            @if ($user_type === 'tenant')
                            @include('livewire.tenant-agent-auction-tabs.commission-based.listing-details')
                            @elseif($user_type === 'seller')
                            @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.listing-details')
                            @elseif($user_type === 'buyer')
                            @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.listing-details')
                            @elseif($user_type === 'landlord')
                            @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.listing-details')
                            @endif
                        </div>

                        @if ($service_type === 'full_service')
                        <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                            id="{{ $propertyId }}" role="tabpanel"
                            aria-labelledby="{{ $propertyId }}-tab">
                            @switch($user_type)
                            @case('tenant')
                            @include('livewire.tenant-agent-auction-tabs.commission-based.property-details')
                            @break

                            @case('seller')
                            @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.property-preferences')
                            @break

                            @case('buyer')
                            @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.property-preferences')
                            @break

                            @case('landlord')
                            @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.property-preferences')
                            @break
                            @endswitch
                        </div>

                        <!-- Leasing Terms Tab -->

                        @if (in_array($user_type, ['landlord', 'tenant']))
                        <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                            id="leasing-terms" role="tabpanel" aria-labelledby="leasing-terms-tab">
                            @endif
                            @if ($user_type === 'seller')
                            <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                                id="sale-terms" role="tabpanel" aria-labelledby="sale-terms-tab">
                                @endif
                                @if ($user_type === 'buyer')
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                                    id="purchasing-terms" role="tabpanel" aria-labelledby="purchasing-terms-tab">
                                    @endif
                                    @if ($user_type === 'tenant')
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.leasing-terms')
                                    @elseif($user_type === 'seller')
                                    @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.seller-terms')
                                    @elseif($user_type === 'buyer')
                                    @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.purchasing-terms')
                                    @elseif($user_type === 'landlord')
                                    @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.lease-terms')
                                    @endif
                                </div>

                                <!-- Conditional Pre-Screening Tab -->
                                @if ($user_type !== 'landlord' and $user_type !== 'buyer' and $user_type !== 'seller')
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="pre-screening"
                                    role="tabpanel" aria-labelledby="pre-screening-tab">
                                    @if ($user_type === 'tenant')
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.pre-screening')
                                    @elseif($user_type === 'seller')
                                    @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.seller-terms')
                                    @endif
                                </div>
                                @endif

                                <!-- Services Tab - Adjust index based on user_type -->

                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? 3 : 4) ? 'show active' : '' }}"
                                    id="services" role="tabpanel" aria-labelledby="services-tab">

                                    @if ($user_type === 'tenant')
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.services')
                                    @elseif($user_type === 'seller')
                                    @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.services')
                                    @elseif($user_type === 'buyer')
                                    @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.services')
                                    @elseif($user_type === 'landlord')
                                    @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.services')
                                    @endif
                                </div>

                                <!-- Additional Details Tab - Adjust index based on user_type -->

                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? 4 : 5) ? 'show active' : '' }}"
                                    id="additional-details" role="tabpanel" aria-labelledby="additional-details-tab">

                                    @if ($user_type === 'tenant')
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.additional-details')
                                    @elseif($user_type === 'seller')
                                    @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.additional-details')
                                    @elseif($user_type === 'buyer')
                                    @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.additional-details')
                                    @elseif($user_type === 'landlord')
                                    @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.additional-details')
                                    @endif
                                </div>

                                <!-- Broker Compensation Tab - Adjust index based on user_type -->

                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? 5 : 6) ? 'show active' : '' }}"
                                    id="broker-compensation-agency-agreement-terms" role="tabpanel" aria-labelledby="broker-compensation-agency-agreement-terms-tab">

                                    @if ($user_type === 'tenant')
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.broker-compensation')
                                    @elseif($user_type === 'seller')
                                    @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.broker-compensation')
                                    @elseif($user_type === 'buyer')
                                    @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.broker-compensation')
                                    @elseif($user_type === 'landlord')
                                    @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.broker-compensation')
                                    @endif
                                </div>

                                <!-- Info Tab - Adjust index based on user_type -->
                                @php
                                    $infoTabId = match($user_type) {
                                        'tenant' => 'tenant-information',
                                        'seller' => 'seller-information',
                                        'buyer' => 'buyer-information',
                                        'landlord' => 'landlord-information',
                                        default => 'tenant-information'
                                    };
                                @endphp
                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? 6 : 7) ? 'show active' : '' }}"
                                    id="{{ $infoTabId }}" role="tabpanel" aria-labelledby="{{ $infoTabId }}-tab">

                                    @if ($user_type === 'tenant')
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.tenant-info')
                                    @elseif($user_type === 'seller')
                                    @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.seller-info')
                                    @elseif($user_type === 'buyer')
                                    @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.buyer-info')
                                    @elseif($user_type === 'landlord')
                                    @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.landlord-info')
                                    @endif
                                </div>
                                @elseif($service_type === 'limited_service')
                                <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                                    id="location-and-meeting-details" role="tabpanel"
                                    aria-labelledby="location-and-meeting-details-tab">
                                    @include('livewire.tenant-agent-auction-tabs.flat-fee.location-and-meeting-details')

                                </div>
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                                    id="service-selection-and-pricing" role="tabpanel"
                                    aria-labelledby="service-selection-and-pricing-tab">
                                    @include('livewire.tenant-agent-auction-tabs.flat-fee.service_selection_pricing')

                                </div>

                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}"
                                    id="additional-details" role="tabpanel" aria-labelledby="additional-details-tab">

                                    @include('livewire.tenant-agent-auction-tabs.commission-based.additional-details')

                                </div>

                                <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}" id="information"
                                    role="tabpanel" aria-labelledby="information-tab">
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.tenant-info')
                                </div>
                                @endif
                            </div>
                            <!-- Navigation Buttons -->
                            <div class="d-flex justify-content-between form-group mt-4">
                                <div>
                                    <button type="button" class="btn btn-secondary wizard-step-back">Previous</button>
                                </div>
                                <div class="d-flex justify-content-between set-button">

                                    <button type="button" class="btn btn-outline-primary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                        <span wire:loading.remove wire:target="saveDraft"><i class="fas fa-save me-1"></i> Save Draft</span>
                                        <span wire:loading wire:target="saveDraft">Saving...</span>
                                    </button>

                                    <button type="button" class="btn btn-primary wizard-step-next">Next</button>

                                    <button type="submit" class="btn btn-success wizard-step-finish" id="save-button" wire:loading.attr="disabled" wire:target="store">
                                        <span wire:loading.remove wire:target="store">Submit</span>
                                        <span wire:loading wire:target="store">Submitting...</span>
                                    </button>
                                </div>

                            </div>

                </form>
            </div>
        </div>
    </div>
</div>
</div>

@push('scripts')


{{-- <script>
        // Function to validate input and remove special characters
        function validateTextInput(event) {
            const input = event.target;
            const regex = /[^a-zA-Z0-9\s]/g; // Regular expression to remove anything that's not a letter, number, or space

            if (regex.test(input.value)) {
                alert("Please enter only numbers and characters. Special characters are not allowed."); // Show alert
            }

            input.value = input.value.replace(regex, ''); // Replace special characters with an empty string
        }

        // Event delegation to handle validation for input[type="text"]
        document.addEventListener('DOMContentLoaded', function() {
            // Attach the event listener to the body (or another common ancestor)
            document.body.addEventListener('input', function(event) {
                // Check if the target is an input of type text
                if (event.target && event.target.type === "text") {
                    validateTextInput(event); // Apply validation if it's a text input
                }
            });
        });
    </script> --}}

<script>
    // Store user_type for validation logic - Cities optional for buyer, required for others
    const CURRENT_USER_TYPE = '{{ $user_type ?? "tenant" }}';
    
    // Global array to store tooltip instances
    let tooltipInstances = [];

    function initializeTooltips() {
        // Destroy existing tooltips first to prevent duplicates
        destroyAllTooltips();

        // Initialize new tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));

        tooltipInstances = tooltipTriggerList.map(function(tooltipTriggerEl) {
            // Create new tooltip instance
            const tooltip = new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover focus',
                html: true
            });

            // Add click handler for mobile/alternative interaction
            tooltipTriggerEl.addEventListener('click', function(e) {
                e.stopPropagation();
                tooltip.show();

                // Auto-hide after 3 seconds
                setTimeout(() => {
                    tooltip.hide();
                }, 3000);
            });

            return tooltip;
        });

        // Add global click handler to hide tooltips
        document.addEventListener('click', function(e) {
            if (!e.target.closest('[data-bs-toggle="tooltip"]')) {
                hideAllTooltips();
            }
        });
    }

    function destroyAllTooltips() {
        tooltipInstances.forEach(tooltip => {
            if (tooltip && typeof tooltip.dispose === 'function') {
                tooltip.dispose();
            }
        });
        tooltipInstances = [];
    }

    function hideAllTooltips() {
        tooltipInstances.forEach(tooltip => {
            if (tooltip && typeof tooltip.hide === 'function') {
                tooltip.hide();
            }
        });
    }

    function initExchangeItemSelect2(mode) {
        var $exEl = $('#exchange_item');
        if (!$exEl.length) return;

        if (mode === 'rehydrate') {
            if (!$exEl.hasClass('select2-hidden-accessible')) return;
            var current = [];
            try { current = @this.get('exchange_item') || []; } catch(e) {}
            $exEl.val(current).trigger('change.select2');
            return;
        }

        var isVisible = $exEl.closest('.tab-pane').hasClass('active') || $exEl.is(':visible');
        if (!isVisible && mode !== 'force') return;

        if (mode === 'force' && $exEl.hasClass('select2-hidden-accessible')) {
            $exEl.select2('destroy');
            $exEl.data('exchange-change-bound', false);
        }
        if (!$exEl.hasClass('select2-hidden-accessible')) {
            $exEl.select2({ placeholder: "Select acceptable exchange items", allowClear: true });
        }
        var saved = [];
        try { saved = JSON.parse($exEl.attr('data-selected') || '[]'); } catch(e) {}
        if (!saved.length) {
            try { saved = @this.get('exchange_item') || []; } catch(e) {}
        }
        if (saved.length > 0) {
            $exEl.val(saved).trigger('change.select2');
        }
        if (!$exEl.data('exchange-change-bound')) {
            $exEl.on('change', function(e) {
                @this.set('exchange_item', $(this).val() || []);
            });
            $exEl.data('exchange-change-bound', true);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initial tooltip setup
        initializeTooltips();

        // Reinitialize when tabs are shown
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                setTimeout(initializeTooltips, 50);
                setTimeout(function() { initExchangeItemSelect2('force'); }, 150);
            });
        });
    });

    // Livewire hooks
    document.addEventListener('livewire:load', function() {
        initializeTooltips();
    });

    Livewire.hook('message.processed', (message, component) => {
        setTimeout(initializeTooltips, 10);
        setTimeout(function() { initExchangeItemSelect2('rehydrate'); }, 50);
    });

    // Handle Turbolinks if you're using it
    document.addEventListener('turbolinks:load', function() {
        initializeTooltips();
    });
</script>
<script>
    let currentServiceType = null;
    let _s2Timers = {};
    let _lastInitTime = 0;
    function debouncedSet(field, value, delay) { clearTimeout(_s2Timers[field]); _s2Timers[field] = setTimeout(function() { safeLivewireSet(field, value); }, delay || 200); }

    function syncAllSelect2BeforeSave() {
        Object.keys(_s2Timers).forEach(function(k) { clearTimeout(_s2Timers[k]); });
        _s2Timers = {};

        var fields = [
            { sel: '.condition_prop_buyer', field: 'condition_prop_buyer', multi: true },
            { sel: '#sale_provision', field: 'sale_provision', multi: false },
            { sel: '#offered_financing', field: 'offered_financing', multi: false },
            { sel: '#garage_parking_spaces_option_landlord', field: 'garage_parking_spaces_option', multi: true },
            { sel: '#leasing_spaces_tenant', field: 'leasing_spaces_tenant', multi: false },
            { sel: '.tenant_pays', field: 'tenant_pays', multi: true },
            { sel: '.lease_term_options', field: 'desired_lease_length', multi: true },
        ];
        fields.forEach(function(f) {
            var $el = $(f.sel);
            if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                var val = $el.first().val();
                if (f.multi && Array.isArray(val)) { val = [...new Set(val)]; }
                safeLivewireSet(f.field, val);
            }
        });

        var $numUnit = $('.number_of_unit_type');
        if ($numUnit.length && $numUnit.hasClass('select2-hidden-accessible')) {
            var nuVals = $numUnit.first().val() || [];
            nuVals = [...new Set(nuVals)];
            safeLivewireSet('number_of_unit_type', nuVals);
            var nutJsonInput = document.querySelector('input[wire\\:model\\.defer="number_of_unit_type_json"]');
            if (nutJsonInput) {
                nutJsonInput.value = JSON.stringify(nuVals);
                nutJsonInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            safeLivewireSet('number_of_unit_type_json', JSON.stringify(nuVals));
        }

        var $pi = $('#property_items');
        if ($pi.length && $pi.hasClass('select2-hidden-accessible')) {
            var piVals = $pi.val() || [];
            safeLivewireSet('property_items', piVals);
            var piJsonInput = document.querySelector('input[wire\\:model="property_items_json"]');
            if (piJsonInput) {
                piJsonInput.value = JSON.stringify(piVals);
                piJsonInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            safeLivewireSet('property_items_json', JSON.stringify(piVals));
        }
    }

    document.addEventListener('submit', function(e) {
        if (e.target && e.target.tagName === 'FORM') {
            syncAllSelect2BeforeSave();
            var $cpb = $('.condition_prop_buyer');
            if ($cpb.length && $cpb.hasClass('select2-hidden-accessible')) {
                var cpbVals = $cpb.val() || [];
                cpbVals = [...new Set(cpbVals)];
                safeLivewireSet('condition_prop_buyer', cpbVals);
                if (typeof syncConditionJsonBridge === 'function') {
                    syncConditionJsonBridge(cpbVals);
                }
            }
        }
    }, true);

    document.addEventListener('DOMContentLoaded', () => {
        // Sync select values from their selected options (fixes draft loading issue)
        syncSelectValues();
        
        // Detect which service is preselected on load
        if (document.getElementById('fullService')?.checked) {
            currentServiceType = 'full_service';
            initializeFullService();
        } else if (document.getElementById('limitedService')?.checked) {
            currentServiceType = 'limited_service';
            initializeLimitedService();
        } else {
            // Default to full service if no service type radio buttons found (limited service removed)
            currentServiceType = 'full_service';
            initializeFullService();
        }

        addIconsToInputs();
        checkRepresentationStatus();
    });

    Livewire.hook('message.processed', () => {
        addIconsToInputs();
        checkRepresentationStatus();

        const fullServiceChecked = document.getElementById('fullService')?.checked;
        const limitedServiceChecked = document.getElementById('limitedService')?.checked;

        let newServiceType = null;
        if (fullServiceChecked) {
            newServiceType = 'full_service';
        } else if (limitedServiceChecked) {
            newServiceType = 'limited_service';
        } else {
            newServiceType = 'full_service';
        }

        if (newServiceType !== currentServiceType) {
            currentServiceType = newServiceType;

            if (typeof isNavigating !== 'undefined' && isNavigating) {
                return;
            }

            removeWizardEventListeners();

            if (currentServiceType === 'full_service') {
                initializeFullService();
            } else if (currentServiceType === 'limited_service') {
                initializeLimitedService();
            }
        }
    });
    
    // Sync select element values from Livewire component data
    // This fixes the issue where DOM select values don't match Livewire state after draft load
    function syncSelectValues() {
        // Find Livewire component
        const wireEl = document.querySelector('[wire\\:id]');
        if (!wireEl || typeof Livewire === 'undefined') return;
        
        const component = Livewire.find(wireEl.getAttribute('wire:id'));
        if (!component) return;
        
        // Sync all selects with wire:model
        document.querySelectorAll('select[wire\\:model]').forEach(select => {
            const wireModel = select.getAttribute('wire:model');
            if (wireModel && component.get) {
                try {
                    const lwValue = component.get(wireModel);
                    if (lwValue && select.value !== lwValue) {
                        console.log('[SyncSelect] Syncing ' + wireModel + ': DOM="' + select.value + '" -> LW="' + lwValue + '"');
                        select.value = lwValue;
                    }
                } catch (e) {
                    // Silently ignore if property doesn't exist
                }
            }
        });
    }
    
    // Listen for draftLoaded browser event to sync values after draft loads
    window.addEventListener('draftLoaded', function() {
        
        setTimeout(function() {
            syncSelectValues();
            syncSelect2MultiSelects();
            if (typeof toggleVacantLand === 'function') toggleVacantLand();
            if (typeof window.updateSaveButton === 'function') {
                window.updateSaveButton();
            }
        }, 200);
    });

    function syncSelect2MultiSelects() {
        var wireEl = document.querySelector('[wire\\:id]');
        if (!wireEl || typeof Livewire === 'undefined') return;
        var component = Livewire.find(wireEl.getAttribute('wire:id'));
        if (!component) return;

        var select2Fields = [
            { id: '#exchange_item', prop: 'exchange_item' },
            { id: '#sale_provision', prop: 'sale_provision' },
            { id: '#offered_financing', prop: 'offered_financing' },
            { id: '.condition_prop_buyer', prop: 'condition_prop_buyer' },
            { id: '#lease_for', prop: 'lease_for' },
            { id: '#property_items', prop: 'property_items' },
            { id: '#view_preference', prop: 'view_preference' },
            { id: '#appliances', prop: 'appliances' },
            { id: '#credit_scroe_rating', prop: 'credit_scroe_rating' },
            { id: '.number_of_unit_type', prop: 'number_of_unit_type' },
            { id: '#garage_parking_spaces_option', prop: 'garage_parking_spaces_option' },
            { id: '#garage_parking_spaces_option_landlord', prop: 'garage_parking_spaces_option' },
            { id: '#leasing_spaces_tenant', prop: 'leasing_spaces_tenant' },
            { id: '#tenant_pays', prop: 'tenant_pays' },
            { id: '#owner_pays', prop: 'owner_pays' },
            { id: '#rent_includes', prop: 'rent_includes' },
            { id: '#desired_lease_length', prop: 'desired_lease_length' },
            { id: '#terms_of_lease', prop: 'terms_of_lease' },
            { id: '#pool_type', prop: 'pool_type' },
            { id: '#non_negotiable_amenities', prop: 'non_negotiable_amenities' },
            { id: '#tenant_require', prop: 'tenant_require' },
            { id: '#garage_parking_spaces_option_buyer', prop: 'garage_parking_spaces_option_buyer' },
        ];

        select2Fields.forEach(function(field) {
            try {
                var $el = $(field.id);
                if ($el.length === 0) return;
                var val = component.get(field.prop);
                if (val && Array.isArray(val) && val.length > 0) {
                    
                    $el.val(val).trigger('change.select2');
                }
            } catch(e) {
                // Silently skip fields that don't exist for this user_type
            }
        });

        // Handle "Other" visibility for multi-selects with "Other" option
        try {
            var tenantPays = component.get('tenant_pays');
            if (tenantPays && Array.isArray(tenantPays) && tenantPays.includes('Other')) {
                $('#other_tenant_pays_wrapper').removeClass('d-none');
            }
            var ownerPays = component.get('owner_pays');
            if (ownerPays && Array.isArray(ownerPays) && ownerPays.includes('Other')) {
                $('#other_owner_pays_wrapper').removeClass('d-none');
            }
            var rentIncludes = component.get('rent_includes');
            if (rentIncludes && Array.isArray(rentIncludes) && rentIncludes.includes('Other')) {
                $('#other_rent_include_wrapper').removeClass('d-none');
            }
            var leaseFor = component.get('lease_for');
            if (leaseFor && Array.isArray(leaseFor) && leaseFor.includes('Other')) {
                $('#other_lease_input_wrapper').removeClass('d-none');
            }
            var viewPref = component.get('view_preference');
            if (viewPref && Array.isArray(viewPref) && viewPref.includes('Other')) {
                $('#other_view_preference_wrapper, #other_preferences_wrapper').removeClass('d-none');
            }
            var nonNeg = component.get('non_negotiable_amenities');
            if (nonNeg && Array.isArray(nonNeg) && nonNeg.includes('Other')) {
                $('#other_non_negotiable_wrapper').removeClass('d-none');
            }
        } catch(e) {
            console.warn('[Select2Sync] Other visibility error:', e);
        }
    }

    function selectService(serviceType) {
        if (currentServiceType === serviceType) return;

        currentServiceType = serviceType;

        // Update card UI
        document.querySelectorAll('.service-option-card').forEach(card => {
            card.classList.remove('active-service');
        });

        const activeCard = document.querySelector(`input[value="${serviceType}"]`)?.closest('.service-option-card');
        if (activeCard) activeCard.classList.add('active-service');

        // Clear old event listeners
        removeWizardEventListeners();

        // Initialize new service logic
        if (serviceType === 'full_service') {
            initializeFullService();
        } else if (serviceType === 'limited_service') {
            initializeLimitedService();
        }

        Livewire.emit('serviceTypeChanged', serviceType);
    }

    function removeWizardEventListeners() {
        const nextBtn = document.querySelector('.wizard-step-next');
        const backBtn = document.querySelector('.wizard-step-back');

        const nextClone = nextBtn?.cloneNode(true);
        const backClone = backBtn?.cloneNode(true);

        if (nextBtn && nextClone) nextBtn.parentNode.replaceChild(nextClone, nextBtn);
        if (backBtn && backClone) backBtn.parentNode.replaceChild(backClone, backBtn);
    }

    // GLOBAL: Helper function to check if element is visible (not hidden by d-none, display:none, collapse, etc.)
    // This is used by validation functions in both Full Service and Limited Service modes
    function isElementVisible(element) {
        if (!element) return false;
        if (element.disabled) return false;
        if (element.type === 'hidden') return false;
        
        // Check if element has zero dimensions (collapsed/hidden via height/width)
        if (element.offsetParent === null && element.tagName !== 'BODY' && element.tagName !== 'HTML') {
            // offsetParent is null for hidden elements (except body/html)
            // However, position:fixed elements also have null offsetParent, so check dimensions
            if (element.offsetHeight === 0 && element.offsetWidth === 0) {
                return false;
            }
        }
        
        // Check for zero dimensions directly
        if (element.offsetHeight === 0 || element.offsetWidth === 0) {
            return false;
        }
        
        // Check for aria-hidden attribute
        if (element.getAttribute('aria-hidden') === 'true') {
            return false;
        }
        
        let el = element;
        while (el && el !== document.body) {
            if (el.classList && (
                el.classList.contains('d-none') || 
                el.classList.contains('hidden') ||
                el.classList.contains('collapse') && !el.classList.contains('show')
            )) {
                return false;
            }
            
            // Check aria-hidden on parent elements too
            if (el.getAttribute('aria-hidden') === 'true') {
                return false;
            }
            
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                return false;
            }
            
            // Check for zero height/width via CSS (collapsed elements)
            if (parseFloat(style.height) === 0 || parseFloat(style.maxHeight) === 0) {
                return false;
            }
            
            el = el.parentElement;
        }
        return true;
    }

    // Safe Livewire setter - prevents errors when Livewire isn't ready yet
    var livewireComponentId = '{{ $_instance->id }}';
    function safeLivewireSet(property, value, defer) {
        try {
            // Check if Livewire and the component exist before calling set
            if (typeof Livewire !== 'undefined' && 
                Livewire.components && 
                Livewire.components.componentsById && 
                Livewire.components.componentsById[livewireComponentId]) {
                var component = Livewire.components.componentsById[livewireComponentId];
                if (component && typeof component.set === 'function') {
                    component.set(property, value, defer || false);
                }
            }
        } catch (e) {
            console.warn('[Livewire] Component not ready for property:', property, e);
        }
    }

    function initializeFullService() {


        if ($('#sale_provision').length && !$('#sale_provision').hasClass('select2-hidden-accessible')) {
            $('#sale_provision').select2({
                placeholder: "Select",
                allowClear: true,
            }).on('change', function() {
                debouncedSet('sale_provision', $(this).val());

                // Add tooltips to selected options dynamically
                $(this).find('option:selected').each(function() {
                    var description = $(this).attr(
                        'title'); // Get the tooltip description from the 'title' attribute
                    $(this).attr('title', description); // Ensure the tooltip will show when hovering
                });

                // Reinitialize tooltips after selection change (if required)
                $(this).find('option').each(function() {
                    var description = $(this).attr('title');
                    if (description) {
                        $(this).tooltip({
                            title: description
                        }); // Apply tooltips to options
                    }
                });
            });
        }

        // Enable Bootstrap tooltips for the entire document
        if ($('#offered_financing').length && !$('#offered_financing').hasClass('select2-hidden-accessible')) {
            $('#offered_financing').select2({
                placeholder: "Select",
                allowClear: true,
            }).on('change', function() {
                debouncedSet('offered_financing', $(this).val());

                // Reapply tooltips to all options dynamically
                $(this).find('option').each(function() {
                    var description = $(this).attr('title');
                    if (description) {
                        $(this).tooltip({
                            title: description
                        }); // Apply tooltip to options
                    }
                });
            });
        }

        initExchangeItemSelect2();

        // Enable Bootstrap tooltips for the entire document
        $('[data-bs-toggle="tooltip"]').tooltip();

        ///////////////////   condition_prop_buyer

        function syncConditionJsonBridge(values) {
            var json = JSON.stringify(values || []);
            var input = document.querySelector('input[wire\\:model="condition_prop_buyer_json"]');
            if (input) {
                input.value = json;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            safeLivewireSet('condition_prop_buyer_json', json);
        }

        function initConditionSelect2() {
            var $sel = $('.condition_prop_buyer');
            if ($sel.hasClass('select2-hidden-accessible')) return;

            $sel.select2({
                placeholder: "Select",
                allowClear: true
            });

            $sel.off('change.cpbSync').on('change.cpbSync', function(e) {
                let data = $(this).val() || [];
                data = [...new Set(data)];
                safeLivewireSet('condition_prop_buyer', data);
                syncConditionJsonBridge(data);
            });
        }

        $(document).ready(function() {
            initConditionSelect2();
        });

        /////////////////////condition_prop_buyer

        let selectEl = $('#garage_parking_spaces_option_landlord');

        if (selectEl.length && !selectEl.hasClass('select2-hidden-accessible')) {
            selectEl.select2({
                placeholder: "Select",
                allowClear: true,
            });

            selectEl.on('change', function() {
                let selectedValues = $(this).val();
                debouncedSet('garage_parking_spaces_option', selectedValues);

                if (selectedValues && selectedValues.includes('Other')) {
                    $('#other_garage_parking_spaces_option_landlord').removeClass('d-none').show();
                } else {
                    $('#other_garage_parking_spaces_option_landlord').addClass('d-none').hide();
                }
            });
        }
        $('.number_of_unit_type').each(function() {
            var $el = $(this);
            if (!$el.hasClass('select2-hidden-accessible')) {
                $el.select2({
                    placeholder: "Select",
                    allowClear: true,
                });

                $el.on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    selectedValues = [...new Set(selectedValues)];
                    debouncedSet('number_of_unit_type', selectedValues);
                });
            }
        });



        
        if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
            $('#property_items').select2({
                placeholder: "Select",
                allowClear: true,
            });

            $('#property_items').on('change', function(e) {
                let selectedValues = $(this).val();
                debouncedSet('property_items', selectedValues);
            });
        }

        


        /////////////// business type

        function toggleBusinessType() {
            // get selected values (Select2 returns an array; plain <select multiple> too)
            var vals = $('#property_items').val() || [];
            if (vals.includes('Business')) {
                $('.business_type').removeClass('d-none');
            } else {
                $('.business_type').addClass('d-none');
            }
        }

        // initialize on page load
        toggleBusinessType();

        // watch for changes
        $('#property_items').on('change', function() {
            toggleBusinessType();
        });

        /////// Vacant Land
        function toggleVacantLand() {
            // get selected values (Select2 returns an array; plain <select multiple> too)
            var vals = $('#property_items').val() || [];
            if (vals.includes('Other')) {
                $('.other_property_items').removeClass('d-none');
            } else {
                $('.other_property_items').addClass('d-none');
            }
        }

        // initialize on page load
        toggleVacantLand();

        // watch for changes — clear other_property_items only on explicit user change
        $('#property_items').on('change', function() {
            var vals = $(this).val() || [];
            if (!vals.includes('Other')) {
                safeLivewireSet('other_property_items', '');
            }
            toggleVacantLand();
        });

        //////////////End Vacant Land

        ///////////////// property Items seller



        // Function to toggle visibility of the "Other Property Condition" input field
        function toggleOtherPropertyCondition(selectElement) {
            const otherFieldContainer = document.querySelector('.other_property_items_seller');

            if (!otherFieldContainer) {
                return;
            }

            if (selectElement.value === 'Other') {
                otherFieldContainer.classList.remove('d-none'); // Show the "Other" input field
            } else {
                otherFieldContainer.classList.add('d-none'); // Hide the "Other" input field
            }
        }

        // Function to attach the event listener to the property type dropdown
        function attachPropertyTypeDropdownListener() {
            const propertySelect = document.getElementById('property_style_select');
            if (propertySelect) {
                // Add the event listener to the select dropdown
                propertySelect.addEventListener('change', function() {
                    toggleOtherPropertyCondition(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                toggleOtherPropertyCondition(propertySelect);
            }
        }

        // Attach the event listener initially
        attachPropertyTypeDropdownListener();

        // Re-attach the event listener after Livewire re-renders the DOM
        Livewire.hook('message.processed', () => {
            attachPropertyTypeDropdownListener();
        });



        function toggleBusinessTypeSeller() {
            var val = $('#property_type').val() || '';
            if (val === 'Business') {
                $('.business_type_seller').removeClass('d-none');
            } else {
                $('.business_type_seller').addClass('d-none');
                $('.other-business-input_seller').addClass('d-none');
                @this.set('business_type_selected', '');
                @this.set('other_business_type', '');
            }
        }

        toggleBusinessTypeSeller();

        $('#property_type').on('change', function() {
            toggleBusinessTypeSeller();
        });

        function toggleBusinessTypeSellerOther() {
            // get selected values (Select2 returns an array; plain <select multiple> too)
            var vals = $('#business_type_seller').val() || [];
            if (vals.includes('Other')) {
                $('.other-business-input_seller').removeClass('d-none');
            } else {
                $('.other-business-input_seller').addClass('d-none');
            }
        }

        // initialize on page load
        toggleBusinessTypeSellerOther();

        // watch for changes
        $('#business_type_seller').on('change', function() {
            toggleBusinessTypeSellerOther();
        });


        // end property seller

        ///// • Business & Real Estate Purchase Requirements


        // cache the wrapper
        const $select = $('#assets');
        const $other = $('.other_assets');

        // initialize once
        if ($select.length && !$select.hasClass('select2-hidden-accessible')) {
            $select.select2({
                placeholder: "Select",
                allowClear: true,
            });

            // when user changes selections…
            $select.on('change', () => {
                const vals = $select.val() || [];
                // push into your Livewire property
                Livewire.emit('assetsOption', vals);
                // show/hide the "Other" text input
                $other.toggleClass('d-none', !vals.includes('Other'));
            });
        }

        //// End •      Business & Real Estate Purchase Requirements
        ///// Selected the business type other then

        function toggleOtherBusinessInput() {
            // read the current value of the Business Type select
            var val = $('#business_type').val();
            if (val === 'Other') {
                $('.other-business-input').removeClass('d-none');
            } else {
                $('.other-business-input').addClass('d-none');
            }
        }

        // run on page load in case it's pre-selected
        toggleOtherBusinessInput();

        // bind to change
        $('#business_type').on('change', toggleOtherBusinessInput);

        ///////////////business type end

        if ($('#credit_scroe_rating').length && !$('#credit_scroe_rating').hasClass('select2-hidden-accessible')) {
            $('#credit_scroe_rating').select2({
                placeholder: "Select credit score rating(s)",
                allowClear: true,
            });

            // Update Livewire property on change
            $('#credit_scroe_rating').on('change', function(e) {
                let selectedValues = $(this).val();
                safeLivewireSet('credit_scroe_rating', selectedValues);
            });
        }

        
        // Initialize Select2 non_negotiable_amenities
        if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
            $('#non_negotiable_amenities')
                .select2({
                    placeholder: "Select",
                    allowClear: true
                })
                .on('select2:select select2:unselect', function(e) {
                    const vals = $(this).val() || [];
                    if (vals.includes('Other')) {
                        $('.other_non_negotiable_amenities').removeClass('d-none');
                    } else {
                        $('.other_non_negotiable_amenities')
                            .addClass('d-none')
                            .find('input').val('').trigger('input');
                        safeLivewireSet('other_non_negotiable_amenities', '');
                    }
                    safeLivewireSet('non_negotiable_amenities', vals);
                });
        }

        var nnInitial = @this.get('non_negotiable_amenities') || [];
        if (nnInitial.length) {
            $('#non_negotiable_amenities').val(nnInitial).trigger('change.select2');
            if (nnInitial.includes('Other')) {
                $('.other_non_negotiable_amenities').removeClass('d-none');
            }
        }
        // End to non_negotiable_amenities



        // Function to toggle "auction time" input field
        function toggleAuctionTime(selectElement) {
            const auctionTimeDiv = document.querySelector('.auction_time');

            if (!auctionTimeDiv) {
                return;
            }

            if (selectElement.value === 'Auction') {
                auctionTimeDiv.classList.remove('d-none'); // Show the auction time field
            } else {
                auctionTimeDiv.classList.add('d-none'); // Hide the auction time field
            }
        }

        // Function to attach the event listener to the auction type dropdown
        function attachAuctionDropdownListener() {
            const auctionDropdown = document.getElementById('auction_type');
            if (auctionDropdown) {
                auctionDropdown.addEventListener('change', function() {
                    toggleAuctionTime(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                toggleAuctionTime(auctionDropdown);
            }
        }

        // Attach the event listener initially
        document.addEventListener('DOMContentLoaded', () => {
            attachAuctionDropdownListener();
        });

        // Re-attach the event listener after Livewire re-renders the DOM
        Livewire.hook('message.processed', () => {
            attachAuctionDropdownListener();
        });

        // Function to toggle "Other Bathrooms" input field
        function toggleOtherBathrooms(selectElement) {
            const otherBathroomsDiv = document.querySelector('.other_bathrooms');

            if (!otherBathroomsDiv) {
                return;
            }

            if (selectElement.value === 'Other') {
                otherBathroomsDiv.classList.remove('d-none'); // Show the "Other" input field
            } else {
                otherBathroomsDiv.classList.add('d-none'); // Hide the "Other" input field
            }
        }

        // Function to attach the event listener to the bathrooms dropdown
        function attachBathroomsDropdownListener() {
            const bathroomsDropdown = document.getElementById('bathrooms');
            if (bathroomsDropdown) {
                bathroomsDropdown.addEventListener('change', function() {
                    toggleOtherBathrooms(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                toggleOtherBathrooms(bathroomsDropdown);
            }
        }

        // Attach the event listener initially
        attachBathroomsDropdownListener();

        // Re-attach the event listener after Livewire re-renders the DOM
        Livewire.hook('message.processed', () => {
            attachBathroomsDropdownListener();
        });

        ///////////////////////garage parking spaces
        function toggleGarageOptions() {
            let garageSelect = document.getElementById('garage_parking_spaces');
            let optionsWrapper = document.getElementById('garage_parking_spaces_option_wrapper');
            let otherInputWrapper = document.getElementById('other_parking_space_wrapper');
            let garageOptions = document.getElementById('garage_parking_spaces_option');

            // First check the main garage/parking spaces selection
            if (garageSelect) {
                if (garageSelect.value === "Yes") {
                    optionsWrapper.classList.remove('d-none'); // Show options dropdown
                } else {
                    optionsWrapper.classList.add('d-none'); // Hide options dropdown
                    otherInputWrapper.classList.add('d-none'); // Also hide other input
                }
            }

            // Then check if "Other" is selected in the options dropdown
            if (garageOptions) {
                // Get all selected options
                let selectedOptions = Array.from(garageOptions.selectedOptions).map(option => option.value);

                // Check if "Other" is among the selected options
                if (selectedOptions.includes("Other") && garageSelect.value === "Yes") {
                    otherInputWrapper.classList.remove('d-none'); // Show input field
                } else {
                    otherInputWrapper.classList.add('d-none'); // Hide input field
                }
            }
        }

        // Initialize on page load
        toggleGarageOptions();

        // Listen for Livewire updates
        Livewire.hook('message.processed', () => {
            toggleGarageOptions();
        });

        // Add event listeners
        let garageSelect = document.getElementById('garage_parking_spaces');
        let garageOptions = document.getElementById('garage_parking_spaces_option');

        if (garageSelect) {
            garageSelect.addEventListener('change', toggleGarageOptions);
        }

        if (garageOptions) {
            garageOptions.addEventListener('change', toggleGarageOptions);
        }

        /////////////////////End garage parking spaces


        function toggleSpaceInput(selectId, inputId) {
            const selectElement = document.getElementById(selectId);
            const inputDiv = document.getElementById(inputId);

            if (!selectElement || !inputDiv) return;

            selectElement.addEventListener('change', function() {
                if (this.value === 'Yes') {
                    inputDiv.classList.remove('d-none');
                } else {
                    inputDiv.classList.add('d-none');
                    // Clear input field when hiding
                    const inputField = inputDiv.querySelector('input');
                    if (inputField) {
                        inputField.value = '';
                        const wireModel = inputField.getAttribute('wire:model');
                        // Only emit if wire:model attribute exists
                        if (wireModel) {
                            Livewire.emit('updateModel', wireModel, '');
                        }
                    }
                }
            });

            // Initialize on page load
            if (selectElement.value === 'Yes') {
                inputDiv.classList.remove('d-none');
            }
        }

        // Attach event listeners
        toggleSpaceInput('carport-needed', 'other-carport-needed');
        toggleSpaceInput('garage-needed', 'other-garage-needed');

        // Reinitialize after Livewire updates
        Livewire.hook('message.processed', () => {
            toggleSpaceInput('carport-needed', 'other-carport-needed');
            toggleSpaceInput('garage-needed', 'other-garage-needed');
        });
        //////////////////////// / view_preference



        // Initialize Select2


        // Initialize Select2
        function initSelect2() {
            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                $('#view_preference').select2({
                    placeholder: "Select",
                    allowClear: true
                });

                // Listen for changes on the select dropdown
                $('#view_preference').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    Livewire.emit('updatePreference', selectedValues); // Emit selected values to Livewire

                    // Clear the other preferences input if "Other" is deselected
                    if (!selectedValues.includes('Other')) {
                        Livewire.emit('updateOtherPreferences', ''); // Clear the "Other" field in Livewire
                    }
                });
            }
        }

        initSelect2();


        ///////////////////// end view_preference

        // garage_parking_spaces_option for multi-select


        const $sel = $('#garage_parking_spaces_option');

        // Initialize Select2 for multi-select
        if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
            $sel.select2({
                placeholder: "Select",
                allowClear: true,
                width: '100%',
            });

            // When the user changes the selection, check for "Other"
            $sel.on('change', function() {
                const vals = $(this).val() || [];

                // Trigger Livewire update for selected values
                Livewire.emit('updateGarageParkingSpaces', vals);

                // Check if "Other" is selected and toggle the input field visibility
                if (vals.includes('Other')) {
                    $('#other_parking_space_wrapper').show(); // Show "Other" input
                } else {
                    $('#other_parking_space_wrapper').hide(); // Hide "Other" input
                    safeLivewireSet('other_parking_space_wrapper', null); // Clear the text input
                }
            });
        }

        




        ///Preference

        // Initialize Select2 for appliances
        function initializeAppliancesSelect2() {
            if ($('#appliances').length && !$('#appliances').hasClass('select2-hidden-accessible')) {
                $('#appliances').select2({
                    placeholder: "Select",
                    allowClear: true
                });

                // Listen for changes on the dropdown and update Livewire
                $('#appliances').on('change', function() {
                    let selectedValuesAppliances = $(this).val();
                    Livewire.emit('updateAppliances', selectedValuesAppliances);

                    // Toggle "Other" input field visibility
                    if (selectedValuesAppliances && selectedValuesAppliances.includes('Other')) {
                        $('#other_appliances').show();
                    } else {
                        $('#other_appliances').hide();
                        safeLivewireSet('other_appliances', null); // Clear other appliances if "Other" is deselected
                    }
                });
            }
        }

        // Initial initialization
        initializeAppliancesSelect2();
        // End Preference




        /////////////////// leasing_spaces

        if ($('#leasing_spaces_tenant').length && !$('#leasing_spaces_tenant').hasClass('select2-hidden-accessible')) {
            $('#leasing_spaces_tenant').select2({
                placeholder: "Select",
                allowClear: true
            });

            $('#leasing_spaces_tenant').on('change', function(e) {
                debouncedSet('leasing_spaces_tenant', $(this).val());
            });
        }

        ///////////////// End leasing_spaces
        ///tenant_pays

        function toggleOtherTenantField(selectedValues) {
            if (selectedValues.includes('Other')) {
                $('.tenant_pays_other').removeClass('d-none');
            } else {
                $('.tenant_pays_other').addClass('d-none');
            }
        }

        // Initialize Select2
        $('.tenant_pays').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    placeholder: "Select",
                    allowClear: true
                });

                // Sync with Livewire and show/hide Other field
                $(this).on('change', function() {
                    let selectedValues = $(this).val() || [];

                    Livewire.emit('updateTenantPays', selectedValues);
                    if (selectedValues && selectedValues.includes('Other')) {
                        $('.tenant_pays_other').show();
                    } else {
                        $('.tenant_pays_other').hide();
                    }
                    debouncedSet('tenant_pays', selectedValues);
                    // toggleOtherTenantField(selectedValues);
                });
            }
        });

        $('.lease_term_options').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    placeholder: "Select",
                    allowClear: true
                });
                $(this).on('change', function() {
                    let selectedValues = $(this).val() || [];
                    Livewire.emit('updateLeaseTermOptions', selectedValues);
                    if (selectedValues && selectedValues.includes('Other')) {
                        $('.other_lease_term').show();
                    } else {
                        $('.other_lease_term').hide();
                        safeLivewireSet('other_lease_term', null);
                    }
                    debouncedSet('desired_lease_length', selectedValues);
                });
            }
        });




        // Initialize Select2


        function initializeSelect2Lease() {
            const selectElement = $('.terms_of_lease');

            // Only initialize if not already initialized
            if (selectElement.hasClass('select2-hidden-accessible')) {
                return; // Already initialized, skip
            }

            selectElement.select2({
                placeholder: "Select",
                allowClear: true,
                width: '100%',
                dropdownParent: selectElement.parent() // Ensure proper positioning
            });

            // Set initial value
            const initialValues = @json($terms_of_lease);
            if (initialValues && initialValues.length > 0) {
                selectElement.val(initialValues).trigger('change');
            }

            // Initial toggle
            toggleLeaseOther(initialValues || []);

            // Listen for changes
            selectElement.on('change', function(e) {
                const selectedValues = $(this).val() || [];

                // Update Livewire
                safeLivewireSet('terms_of_lease', selectedValues);

                // Toggle "Other" input
                toggleLeaseOther(selectedValues);
            });
        }

        function toggleLeaseOther(selectedValues) {
            const otherContainer = $('#otherLeaseContainer');
            if (selectedValues && selectedValues.includes('Other')) {
                otherContainer.removeClass('d-none');
            } else {
                otherContainer.addClass('d-none');
            }
        }

        // Initialize only once
        $(document).ready(function() {
            initializeSelect2Lease();
        });

        // Re-initialize only on load, not on every update
        document.addEventListener('livewire:load', function() {
            initializeSelect2Lease();
        });

        // Remove the livewire:update listener to prevent blinking

        // End tenant_pays
        ///owner_pays

        function toggleOwnerPaysOther(selectedValues) {
            if (selectedValues.includes('Other')) {
                $('.other_owner_pays').removeClass('d-none');
            } else {
                $('.other_owner_pays').addClass('d-none');
            }
        }

        // Init Select2
        $('.owner_pays').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    placeholder: "Select",
                    allowClear: true
                });

                // On change: sync to Livewire & toggle Other
                $(this).on('change', function() {
                    let selectedValues = $(this).val() || [];
                    Livewire.emit('updateOwnerPays', selectedValues);
                    if (selectedValues && selectedValues.includes('Other')) {
                        $('.other_owner_pays').show();
                    } else {
                        $('.other_owner_pays').hide();
                        safeLivewireSet('other_owner_pays', null);
                    }
                    safeLivewireSet('owner_pays', selectedValues);
                });
            }
        });


        // End owner_pays

        ///lease_for

        function toggleLease(selectedLease) {
            if (selectedLease.includes('Other')) {
                $('.other_lease_for').removeClass('d-none');
            } else {
                $('.other_lease_for').addClass('d-none');
            }
        }

        function initSelect2LeaseFor() {
            var $sel = $('.lease_for');
            if ($sel.hasClass('select2-hidden-accessible')) {
                return;
            }

            $sel.select2({
                placeholder: "Select",
                allowClear: true
            });

            var lwValues = @this.get('lease_for') || [];
            if (lwValues.length) {
                $sel.val(lwValues).trigger('change.select2');
            }
            toggleLease($sel.val() || []);

            $sel.off('change').on('change', function() {
                let selectedLease = $(this).val() || [];
                safeLivewireSet('lease_for', selectedLease);
                toggleLease(selectedLease);
            });
        }

        initSelect2LeaseFor();

        // End lease_for
        //rent_includes


        function toggleOtherField(selectedValues) {
            if (selectedValues.includes('Other')) {
                $('.other_rent_input_wrapper').removeClass('d-none');
            } else {
                $('.other_rent_input_wrapper').addClass('d-none');
            }
        }

        // Initialize Select2
        $('.rent_includes').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    placeholder: "Select",
                    allowClear: true
                });

                // Sync Select2 with Livewire on change
                $(this).on('change', function() {
                    let selectedValues = $(this).val() || [];
                    Livewire.emit('updateRentIncludes', selectedValues);
                    if (selectedValues && selectedValues.includes('Other')) {
                        $('.other_rent_input_wrapper').show();
                    } else {
                        $('.other_rent_input_wrapper').hide();
                        safeLivewireSet('other_rent_include', null);
                    }
                    safeLivewireSet('rent_includes', selectedValues);
                    //toggleOtherField(selectedValues);
                });
            }
        });


        // ENd rent_includes


        // Function to toggle "Other Bedrooms" input field
        function toggleOtherBedrooms(selectElement) {
            const otherBedroomsDiv = document.querySelector('.other_bedrooms');

            if (!otherBedroomsDiv) {
                return;
            }

            if (selectElement.value === 'Other') {
                otherBedroomsDiv.classList.remove('d-none'); // Show the "Other" input field
            } else {
                otherBedroomsDiv.classList.add('d-none'); // Hide the "Other" input field
            }
        }

        // Function to attach the event listener to the bedrooms dropdown
        function attachBedroomsDropdownListener() {
            const bedroomsDropdown = document.getElementById('bedrooms'); // Corrected ID
            if (bedroomsDropdown) {
                bedroomsDropdown.addEventListener('change', function() {
                    toggleOtherBedrooms(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                toggleOtherBedrooms(bedroomsDropdown);
            }
        }

        // Attach the event listener initially
        attachBedroomsDropdownListener();

        // Re-attach the event listener after Livewire re-renders the DOM
        Livewire.hook('message.processed', () => {
            attachBedroomsDropdownListener();
        });
        // Function to toggle "Pet Details" input fields
        function togglePetDetails(selectElement) {
            const petDetailsDiv = document.getElementById('pet-details');

            if (!petDetailsDiv) {
                return;
            }

            if (selectElement.value === 'Yes') {
                petDetailsDiv.style.display = 'block'; // Show the pet details fields
            } else {
                petDetailsDiv.style.display = 'none'; // Hide the pet details fields
            }
        }

        // Function to attach the event listener to the pets dropdown
        function attachPetsDropdownListener() {
            const petsDropdown = document.getElementById('pets');
            if (petsDropdown) {
                petsDropdown.addEventListener('change', function() {
                    togglePetDetails(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                togglePetDetails(petsDropdown);
            }
        }

        // Attach the event listener initially
        document.addEventListener('DOMContentLoaded', () => {
            attachPetsDropdownListener();
        });

        // Re-attach the event listener after Livewire re-renders the DOM
        Livewire.hook('message.processed', () => {
            attachPetsDropdownListener();
        });
        // Function to toggle "Other Property Condition" input field
        function toggleOtherCondition(selectElement) {
            const otherConditionDiv = document.querySelector('.other_property_condition');

            if (!otherConditionDiv) {
                return;
            }

            // Show the "Other" input field if "Other" is selected
            if (selectElement.value === 'Other') {
                otherConditionDiv.classList.remove('d-none'); // Show the "Other" input field
            } else {
                otherConditionDiv.classList.add('d-none'); // Hide the "Other" input field
            }
        }

        // Function to attach the event listener to the condition dropdown
        function attachConditionDropdownListener() {
            const conditionDropdown = document.getElementById('condition_prop');
            if (conditionDropdown) {
                conditionDropdown.addEventListener('change', function() {
                    toggleOtherCondition(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                toggleOtherCondition(conditionDropdown);
            }
        }

        // Attach the event listener initially
        document.addEventListener('DOMContentLoaded', () => {
            attachConditionDropdownListener();
        });

        // Re-attach the event listener after Livewire re-renders the DOM
        Livewire.hook('message.processed', () => {
            attachConditionDropdownListener();
        });

        const photoInput = document.getElementById("photo-input");
        const photoError = document.getElementById("photo-error");
        const videoInput = document.getElementById("video-input");
        const videoError = document.getElementById("video-error");
        const videoLoader = document.getElementById("video-loader");
        const photoPreview = document.getElementById("photo-preview");
        // Error flags
        let photoErrorFlag = false;
        let videoErrorFlag = false;

        // Function to validate photo upload
        function validatePhoto(file) {
            if (!file) return true; // No file selected

            if (!file.type.startsWith("image/")) {
                photoError.textContent = "Please upload a valid image file.";
                photoError.style.display = "block";
                photoErrorFlag = true; // Set error flag
                photoInput.value = ""; // Clear input field
                return false;
            }

            if (file.size > 10 * 1024 * 1024) {
                photoError.textContent = "Photo size must be less than 10MB.";
                photoError.style.display = "block";
                photoErrorFlag = true; // Set error flag
                photoInput.value = ""; // Clear input field
                return false;
            }

            photoError.textContent = "";
            photoError.style.display = "none";
            photoErrorFlag = false; // Reset error flag
            return true;
        }

        // Function to validate video upload
        function validateVideo(file) {
            if (!file) return false; // No file selected

            if (!file.type.startsWith("video/")) {
                videoError.textContent = "Please upload a valid video file.";
                videoError.style.display = "block";
                videoErrorFlag = true; // Set error flag
                videoInput.value = ""; // Clear input field
                return false;
            }

           if (file.size > 20 * 1024 * 1024) {
                videoError.textContent =
                    'Video size must be under 20MB. For larger videos, please paste a link instead (e.g., YouTube or Vimeo). For privacy, you can set your video as unlisted on YouTube so only those with the link can view it.';
                videoError.style.display = "block";
                videoErrorFlag = true; // Set error flag
                videoInput.value = ""; // Clear input field
                return false;
            }


            videoError.textContent = "";
            videoError.style.display = "none";
            videoErrorFlag = false; // Reset error flag
            return true;
        }

        // Function to show loader for at least 20 seconds
        function showLoaderForMinimumTime() {
            videoLoader.style.visibility = "visible";

            // Ensure the loader is visible for at least 20 seconds
            setTimeout(() => {
                videoLoader.style.visibility = "hidden";
            }, 30000);
        }

        // Function to handle video upload
        function handleVideoUpload(event) {
            const file = event.target.files[0];

            if (!validateVideo(file)) return;

            // Trigger Livewire video upload and show the loader
            Livewire.emit("upload:start");
            showLoaderForMinimumTime();
        }

        // Function to handle photo upload
        function handlePhotoUpload(event) {
            const file = event.target.files[0];

            if (!validatePhoto(file)) return;

            // Trigger Livewire photo upload and show the loader
            Livewire.emit("upload:start");
            showLoaderForMinimumTime();
        }

        // Attach event listeners for photo and video uploads
        if (photoInput) {
            photoInput.addEventListener("change", handlePhotoUpload);
        }

        if (videoInput) {
            videoInput.addEventListener("change", handleVideoUpload);
        }

        // Livewire event listeners
        Livewire.on("upload:start", () => {
            showLoaderForMinimumTime();
        });

        Livewire.on("upload:finish", () => {
            // Wait for at least 20 seconds before hiding the loader
            setTimeout(() => {
                videoLoader.style.visibility = "hidden";
            }, 30000);
        });


        // Function to check if all required fields are filled
        function checkFormValidity() {
            let allValid = true;

            // Check all tabs for required fields (skip hidden/disabled fields)
            document.querySelectorAll('.tab-pane').forEach(tabPane => {
                tabPane.querySelectorAll('[required]').forEach(field => {
                    // Skip hidden or disabled fields - they should not block form validity
                    if (!isElementVisible(field)) {
                        return;
                    }
                    if (!field.value) {
                        allValid = false;
                    }
                });
            });

            // Enable/disable save button
            const saveButton = document.querySelector('.wizard-step-finish');
            if (saveButton) {
                saveButton.disabled = !allValid;
            }

            return allValid;
        }

    }

    // === WIZARD NAVIGATION (global scope — survives Livewire DOM morphing) ===

    function validateServicesTab(tabContent) {
        if (!tabContent || tabContent.id !== 'services') return true;
        return true;
    }

    // AUTHORITATIVE TAB ORDER - Dynamically derived from actual DOM
    function getTabOrder() {
        const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
        const order = [];
        tabLinks.forEach(link => {
            const target = link.getAttribute('data-bs-target');
            if (target) {
                order.push(target.replace('#', ''));
            }
        });
        return order;
    }

    let TAB_ORDER = null;
    function ensureTabOrder() {
        if (!TAB_ORDER || TAB_ORDER.length === 0) {
            TAB_ORDER = getTabOrder();
        }
        return TAB_ORDER;
    }

    function validateCurrentTab(currentTabContent) {
        if (!currentTabContent) return true;

        let isValid = true;

        const requiredFields = currentTabContent.querySelectorAll(
            'input[required], select[required], textarea[required]');
        if (requiredFields) {
            requiredFields.forEach(function(input) {
                if (!isElementVisible(input)) {
                    input.classList.remove('is-invalid');
                    return;
                }

                if (!input.value) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    const formGroup = input.closest('.form-group');
                    if (formGroup) {
                        const errorMessageContainer = formGroup.querySelector('.error');
                        if (!errorMessageContainer) {
                            const errorMessage = document.createElement('div');
                            errorMessage.className = 'error mt-2';
                            errorMessage.textContent = 'This field is required.';
                            formGroup.appendChild(errorMessage);
                        } else {
                            errorMessageContainer.textContent = 'This field is required.';
                        }
                    }
                } else {
                    input.classList.remove('is-invalid');
                    const formGroup = input.closest('.form-group');
                    if (formGroup) {
                        const errorMessageContainer = formGroup.querySelector('.error');
                        if (errorMessageContainer) {
                            errorMessageContainer.remove();
                        }
                    }
                }
            });
        }

        const citiesOptionalFor = ['buyer', 'tenant'];

        const citiesContainer = currentTabContent.querySelector('.cities-container');
        if (citiesContainer) {
            const cityBadges = citiesContainer.querySelectorAll('.badge');
            if (!citiesOptionalFor.includes(CURRENT_USER_TYPE)) {
                if (!cityBadges || cityBadges.length === 0) {
                    isValid = false;
                    const existingError = citiesContainer.parentNode.querySelector('.error');
                    if (!existingError) {
                        const citiesError = document.createElement('div');
                        citiesError.className = 'error';
                        citiesError.textContent = 'At least one city is required.';
                        citiesContainer.parentNode.insertBefore(citiesError, citiesContainer
                            .nextSibling);
                    }
                } else {
                    const existingError = citiesContainer.parentNode.querySelector('.error');
                    if (existingError) {
                        existingError.remove();
                    }
                }
            } else {
                const existingError = citiesContainer.parentNode.querySelector('.error');
                if (existingError && existingError.textContent.includes('city')) {
                    existingError.remove();
                }
            }
        }

        const countiesContainer = currentTabContent.querySelector('.counties-container');
        const countiesErrorSpan = currentTabContent.querySelector('#counties_error');
        if (countiesContainer) {
            const countyBadges = countiesContainer.querySelectorAll('.badge');
            if (!countyBadges || countyBadges.length === 0) {
                isValid = false;
                if (countiesErrorSpan) {
                    countiesErrorSpan.textContent = 'This field is required.';
                }
            } else {
                if (countiesErrorSpan) {
                    countiesErrorSpan.textContent = '';
                }
            }
        }

        if (currentTabContent.id === 'services') {
            isValid = isValid && validateServicesTab(currentTabContent);
        }

        if (currentServiceType === 'limited_service' && currentTabContent.id === 'service-selection-and-pricing') {
            const understandTerms = currentTabContent.querySelector('#understandTerms');
            if (understandTerms && !understandTerms.checked) {
                isValid = false;
                const existingError = understandTerms.parentNode.querySelector('.error');
                if (!existingError) {
                    const termsError = document.createElement('div');
                    termsError.className = 'error text-danger mt-2';
                    termsError.textContent = 'You must accept the terms to continue';
                    understandTerms.parentNode.appendChild(termsError);
                }
            } else if (understandTerms) {
                const existingError = understandTerms.parentNode.querySelector('.error');
                if (existingError) {
                    existingError.remove();
                }
            }
        }

        return isValid;
    }

    let isNavigating = false;

    function goToNextTab() {
        if (isNavigating) {
            return false;
        }
        isNavigating = true;

        const tabOrder = ensureTabOrder();
        const activeTab = document.querySelector('.nav-link.active');
        if (!activeTab) { isNavigating = false; return false; }

        const currentTarget = activeTab.getAttribute('data-bs-target')?.replace('#', '');
        const currentIndex = tabOrder.indexOf(currentTarget);
        if (currentIndex === -1) { isNavigating = false; return false; }

        const nextTabId = tabOrder[currentIndex + 1];
        if (!nextTabId) { isNavigating = false; return false; }

        const nextTabEl = document.querySelector(`[data-bs-target="#${nextTabId}"]`);
        if (!nextTabEl) { isNavigating = false; return false; }

        bootstrap.Tab.getOrCreateInstance(nextTabEl).show();
        Livewire.emit('setActiveTab', currentIndex + 1);

        setTimeout(() => { isNavigating = false; }, 500);
        return true;
    }

    function goToPrevTab() {
        const tabOrder = ensureTabOrder();
        const activeTab = document.querySelector('.nav-link.active');
        if (!activeTab) return false;

        const currentTarget = activeTab.getAttribute('data-bs-target')?.replace('#', '');
        const currentIndex = tabOrder.indexOf(currentTarget);

        if (currentIndex === -1 || currentIndex === 0) {
            return false;
        }

        const prevTabId = tabOrder[currentIndex - 1];
        const prevTabEl = document.querySelector(`[data-bs-target="#${prevTabId}"]`);
        if (!prevTabEl) return false;

        bootstrap.Tab.getOrCreateInstance(prevTabEl).show();
        Livewire.emit('setActiveTab', currentIndex - 1);
        return true;
    }

    function showValidationBanner(currentTabContent) {
        const banner = document.getElementById('submit-error-banner');
        if (!banner) return;
        const errorList = document.getElementById('submit-error-list');
        if (errorList) {
            errorList.innerHTML = '';
            const invalidFields = currentTabContent.querySelectorAll('.is-invalid');
            const seen = new Set();
            invalidFields.forEach(f => {
                const label = f.closest('.form-group')?.querySelector('label');
                const name = label ? label.textContent.replace(/[*:]/g, '').trim() : (f.placeholder || f.name || 'Required field');
                if (!seen.has(name)) {
                    seen.add(name);
                    const li = document.createElement('li');
                    li.textContent = name;
                    errorList.appendChild(li);
                }
            });
            const errorContainers = currentTabContent.querySelectorAll('.error');
            errorContainers.forEach(ec => {
                const text = ec.textContent.trim();
                if (text && !seen.has(text)) {
                    seen.add(text);
                    const li = document.createElement('li');
                    li.textContent = text;
                    errorList.appendChild(li);
                }
            });
        }
        banner.querySelector('strong').textContent = 'Please complete the required fields before continuing.';
        banner.classList.remove('d-none');
        banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Delegated click handlers — attached once to document, survive Livewire DOM morphing
    if (!window.__wizardDelegatedHandlersBound) {
        window.__wizardDelegatedHandlersBound = true;
        document.addEventListener('click', function(e) {
            if (e.target.closest('.wizard-step-next')) {
                const activeTab = document.querySelector('.nav-link.active');
                if (!activeTab) return;

                const currentTabContent = document.querySelector(activeTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                const banner = document.getElementById('submit-error-banner');
                if (banner) banner.classList.add('d-none');

                const isValid = validateCurrentTab(currentTabContent);

                if (isValid) {
                    goToNextTab();
                } else {
                    showValidationBanner(currentTabContent);
                }
            }

            if (e.target.closest('.wizard-step-back')) {
                goToPrevTab();
            }
        });
    }

    function initializeLimitedService() {

        // Function to check if all required fields are filled
        function checkFormValidity() {
            let allValid = true;

            // Check all tabs for required fields (skip hidden/disabled fields)
            document.querySelectorAll('.tab-pane').forEach(tabPane => {
                tabPane.querySelectorAll('[required]').forEach(field => {
                    // Skip hidden or disabled fields - they should not block form validity
                    if (!isElementVisible(field)) {
                        return;
                    }
                    if (!field.value) {
                        allValid = false;
                    }
                });
            });

            // Enable/disable save button
            const saveButton = document.querySelector('.wizard-step-finish');
            if (saveButton) {
                saveButton.disabled = !allValid;
            }

            return allValid;
        }

        // Next/Back handlers are now delegated at document level (global scope)

        // Add event listeners to update save button state when fields change
        document.addEventListener('DOMContentLoaded', function() {
            // Initial check
            checkFormValidity();

            // Update on any input change
            document.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('change', checkFormValidity);
                field.addEventListener('keyup', checkFormValidity);
            });

            // Special handling for Livewire-updated fields
            document.addEventListener('livewire:update', function() {
                setTimeout(checkFormValidity, 100);
            });
        });
    }



    function addIconsToInputs() {
        document.querySelectorAll('.has-icon').forEach(input => {
            const iconClass = input.getAttribute('data-icon');
            if (iconClass && !input.previousElementSibling?.classList.contains('input-icon')) {
                const icon = document.createElement('i');
                icon.className = `input-icon ${iconClass}`;
                input.parentNode.insertBefore(icon, input);
            }
        });
    }

    function checkRepresentationStatus() {
        const select = document.getElementById('working_with_agent');
        const notice = document.getElementById('representation_notice');
        const nextBtn = document.querySelector('.wizard-step-next');
        const saveBtn = document.querySelector('.wizard-step-finish');

        if (select && notice) {
            const isRepresented = select.value === 'Represented';
            notice.classList.toggle('d-none', !isRepresented);
            nextBtn.disabled = isRepresented;
            saveBtn.disabled = isRepresented;
        }
    }

    Livewire.hook('message.processed', () => {
        addIconsToInputs();
        checkRepresentationStatus();

        if (typeof isNavigating !== 'undefined' && isNavigating) {
            return;
        }

        // Re-detect selected service type after DOM update
        const fullServiceChecked = document.getElementById('fullService')?.checked;
        const limitedServiceChecked = document.getElementById('limitedService')?.checked;

        let newServiceType = null;
        if (fullServiceChecked) {
            newServiceType = 'full_service';
        } else if (limitedServiceChecked) {
            newServiceType = 'limited_service';
        } else {
            newServiceType = 'full_service';
        }

        if (newServiceType !== currentServiceType) {
            currentServiceType = newServiceType;

            if (typeof isNavigating !== 'undefined' && isNavigating) {
                return;
            }

            removeWizardEventListeners();

            if (currentServiceType === 'full_service') {
                initializeFullService();
            } else if (currentServiceType === 'limited_service') {
                initializeLimitedService();
            }
        }

    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const saveButton = document.getElementById('save-button');
        const formContainer = document.getElementById('wizard-form-container');

        // Get all required fields from only active tabs depending on service type
        function getAllRequiredFields() {
            const requiredFields = [];
            const serviceType = formContainer.getAttribute('data-service-type');

            const tabSelector = serviceType === 'full_service' ? [
                '#listing-details',
                '#property-preferences',

                //  '#property-details',
                '#sale-terms',
                '#leasing-terms',
                '#pre-screening',

                '#services',
                '#additional-details',
                '#broker-compensation',
                '#tenant-info'
            ] : [
                '#listing-details',
                '#location-and-meeting-details',
                '#service-selection-and-pricing',
                '#tenant-info'
            ];

            tabSelector.forEach(selector => {
                const tab = document.querySelector(selector);
                if (!tab) return;
                const fields = tab.querySelectorAll('[required]');
                fields.forEach(field => requiredFields.push(field));
                
                // Also include special Livewire-validated fields (e.g., counties)
                const livewireFields = tab.querySelectorAll('[data-livewire-counties]');
                livewireFields.forEach(field => requiredFields.push(field));
            });

            return requiredFields;
        }

        // Helper function to check if element is visible (not hidden by d-none, display:none, collapse, etc.)
        function isFieldVisible(element) {
            if (!element) return false;
            if (element.disabled) return false;
            if (element.type === 'hidden') return false;
            
            // Check if element has zero dimensions (collapsed/hidden via height/width)
            if (element.offsetParent === null && element.tagName !== 'BODY' && element.tagName !== 'HTML') {
                if (element.offsetHeight === 0 && element.offsetWidth === 0) {
                    return false;
                }
            }
            
            // Check for zero dimensions directly
            if (element.offsetHeight === 0 || element.offsetWidth === 0) {
                return false;
            }
            
            // Check for aria-hidden attribute
            if (element.getAttribute('aria-hidden') === 'true') {
                return false;
            }
            
            let el = element;
            while (el && el !== document.body) {
                if (el.classList && (
                    el.classList.contains('d-none') || 
                    el.classList.contains('hidden') ||
                    el.classList.contains('collapse') && !el.classList.contains('show')
                )) {
                    return false;
                }
                
                // Check aria-hidden on parent elements too
                if (el.getAttribute('aria-hidden') === 'true') {
                    return false;
                }
                
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                    return false;
                }
                
                // Check for zero height/width via CSS (collapsed elements)
                if (parseFloat(style.height) === 0 || parseFloat(style.maxHeight) === 0) {
                    return false;
                }
                
                el = el.parentElement;
            }
            return true;
        }

        function isFieldValid(field) {
            // Skip fields without proper name/id attributes (auto-generated or malformed fields)
            if (!field.name && !field.id) {
                return true; // Skip fields without identifiers
            }
            
            // Special handling for counties validation - check Livewire state directly
            if (field.hasAttribute('data-livewire-counties')) {
                try {
                    const componentEl = field.closest('[wire\\:id]');
                    if (componentEl && typeof Livewire !== 'undefined') {
                        const component = Livewire.find(componentEl.getAttribute('wire:id'));
                        if (component && component.get) {
                            const counties = component.get('counties');
                            const isValid = Array.isArray(counties) && counties.length > 0;
                            if (!isValid) {
                                console.log('[Counties Validation] Counties array is empty or not set');
                            }
                            return isValid;
                        }
                    }
                } catch (e) {
                    console.log('[Counties Validation] Error checking Livewire state:', e);
                }
                // Fallback: check DOM value
                return field.value && field.value.trim() !== '' && field.value !== '[]';
            }
            
            // Skip any field that is not visible - regardless of which tab it's in
            // This includes fields hidden by CSS, conditional rendering, inactive tabs, etc.
            if (!isFieldVisible(field)) {
                return true; // Treat invisible fields as valid (they don't block)
            }
            
            // Helper to get Livewire component value as fallback
            function getLivewireValue(fieldId) {
                try {
                    // Get the wire:model attribute
                    const wireModel = field.getAttribute('wire:model') || field.getAttribute('wire:model.defer');
                    if (wireModel && typeof Livewire !== 'undefined') {
                        const component = Livewire.find(field.closest('[wire\\:id]')?.getAttribute('wire:id'));
                        if (component && component.get) {
                            return component.get(wireModel);
                        }
                    }
                } catch (e) {
                    // Silently fail if Livewire not available
                }
                return null;
            }
            
            // Now check the actual value for visible required fields
            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked;
            }
            if (field.type === 'select-one' || field.type === 'select-multiple') {
                let value = field.value;
                // Fallback to Livewire value if DOM value is empty
                if (value === '' || value === null) {
                    const lwValue = getLivewireValue(field.id);
                    if (lwValue !== null && lwValue !== '') {
                        value = lwValue;
                    }
                }
                return value !== '' && value !== null;
            }
            let value = field.value;
            // Fallback to Livewire value if DOM value is empty
            if (!value || value.trim() === '') {
                const lwValue = getLivewireValue(field.id);
                if (lwValue !== null && lwValue !== '') {
                    value = lwValue;
                }
            }
            return value && value.trim() !== '';
        }

        function validateAllTabsStrictly() {
            const requiredFields = getAllRequiredFields();
            let invalidFields = [];

            requiredFields.forEach(field => {
                if (!isFieldValid(field)) {
                    const tab = field.closest('.tab-pane');
                    const tabIndex = [...document.querySelectorAll('.tab-pane')].indexOf(tab) + 1;
                    invalidFields.push({
                        tab: tabIndex,
                        field: field.name || field.id,
                        value: field.value,
                        visible: isFieldVisible(field)
                    });
                }
            });

            if (invalidFields.length > 0) {
                // Debug: Log which fields are blocking submit - use table for better visibility
                console.log('[Submit Debug] Invalid fields blocking submission (' + invalidFields.length + ' fields):');
                console.table(invalidFields);
                invalidFields.forEach((f, i) => {
                    console.log('  Field ' + (i+1) + ': Tab ' + f.tab + ', Name: "' + f.field + '", Value: "' + f.value + '", Visible: ' + f.visible);
                });
                
                // Show visible debug message near submit button
                let debugEl = document.getElementById('validation-debug');
                if (!debugEl) {
                    debugEl = document.createElement('div');
                    debugEl.id = 'validation-debug';
                    debugEl.style.cssText = 'background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 13px;';
                    const submitBtn = document.getElementById('save-button');
                    if (submitBtn && submitBtn.parentElement) {
                        submitBtn.parentElement.insertBefore(debugEl, submitBtn);
                    }
                }
                debugEl.innerHTML = '<strong>Missing required fields:</strong><br>' + 
                    invalidFields.map(f => '- Tab ' + f.tab + ': ' + f.field).join('<br>');
                
                return false;
            }
            
            // Clear debug message when all valid
            const debugEl = document.getElementById('validation-debug');
            if (debugEl) debugEl.remove();
            console.log('[Submit Debug] All fields valid - submit enabled');
            return true;
        }

        function updateSaveButton() {
            // TEMPORARILY DISABLED: Always keep button enabled for testing
            // const allValid = validateAllTabsStrictly();
            // if (allValid) {
            //     saveButton.classList.remove('disabled');
            //     saveButton.removeAttribute('disabled');
            // } else {
            //     saveButton.classList.add('disabled');
            //     saveButton.setAttribute('disabled', 'disabled');
            // }
            
            // Force button enabled for testing
            saveButton.classList.remove('disabled');
            saveButton.removeAttribute('disabled');
            
            // Still run validation to show debug info
            validateAllTabsStrictly();
        }
        
        // Expose updateSaveButton globally for draftLoaded event
        window.updateSaveButton = updateSaveButton;

        function setupGlobalListeners() {
            const allTabs = document.querySelectorAll('.tab-pane');
            const allFields = [];
            allTabs.forEach(tab => {
                const fields = tab.querySelectorAll('input, select, textarea');
                fields.forEach(field => {
                    field.removeEventListener('input', updateSaveButton);
                    field.removeEventListener('change', updateSaveButton);
                    field.addEventListener('input', updateSaveButton);
                    field.addEventListener('change', updateSaveButton);
                    allFields.push(field);
                });
            });
            return allFields;
        }

        // Initial setup - delay to ensure Livewire has synced the DOM values
        setupGlobalListeners();
        // Run validation after a brief delay to allow Livewire to hydrate draft values
        setTimeout(updateSaveButton, 500);

        // Livewire reactivity hook
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('message.processed', () => {
                setTimeout(() => {
                    // Sync select values from Livewire component data (fixes draft loading issue)
                    if (typeof syncSelectValues === 'function') {
                        syncSelectValues();
                    }
                    
                    // Refresh listeners and service type
                    const updatedServiceType = document.querySelector('[data-service-type]');
                    if (updatedServiceType) {
                        formContainer.setAttribute('data-service-type', updatedServiceType
                            .getAttribute('data-service-type'));
                    }

                    setupGlobalListeners();
                    updateSaveButton();
                }, 300);
            });
        }

        const createForm = document.getElementById('create-auction-form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                const banner = document.getElementById('submit-error-banner');
                const errorList = document.getElementById('submit-error-list');
                banner.classList.add('d-none');
                errorList.innerHTML = '';

                const requiredFields = getAllRequiredFields();
                let invalidItems = [];

                requiredFields.forEach(field => {
                    if (!isFieldValid(field)) {
                        const tab = field.closest('.tab-pane');
                        const label = field.closest('.form-group')?.querySelector('label');
                        let fieldName = '';
                        if (label) {
                            fieldName = label.textContent.replace(/[*:]/g, '').trim();
                        } else {
                            fieldName = field.getAttribute('placeholder') || field.name || field.id || 'Unknown field';
                        }
                        invalidItems.push({ field, tab, fieldName });
                    }
                });

                const citiesContainer = document.querySelector('.cities-container');
                const CURRENT_USER_TYPE_LOCAL = typeof CURRENT_USER_TYPE !== 'undefined' ? CURRENT_USER_TYPE : '';
                const citiesOptionalFor = ['buyer', 'tenant'];
                if (citiesContainer && !citiesOptionalFor.includes(CURRENT_USER_TYPE_LOCAL)) {
                    const cityBadges = citiesContainer.querySelectorAll('.badge');
                    if (!cityBadges || cityBadges.length === 0) {
                        const tab = citiesContainer.closest('.tab-pane');
                        invalidItems.push({ field: citiesContainer, tab, fieldName: 'City' });
                    }
                }

                const countiesContainer = document.querySelector('.counties-container');
                if (countiesContainer) {
                    const countyBadges = countiesContainer.querySelectorAll('.badge');
                    if (!countyBadges || countyBadges.length === 0) {
                        const tab = countiesContainer.closest('.tab-pane');
                        invalidItems.push({ field: countiesContainer, tab, fieldName: 'County' });
                    }
                }

                if (invalidItems.length > 0) {
                    e.preventDefault();
                    e.stopPropagation();

                    const seen = new Set();
                    invalidItems.forEach(item => {
                        if (!seen.has(item.fieldName)) {
                            seen.add(item.fieldName);
                            const li = document.createElement('li');
                            li.textContent = item.fieldName;
                            errorList.appendChild(li);
                        }
                    });
                    banner.classList.remove('d-none');

                    const firstItem = invalidItems[0];
                    if (firstItem.tab) {
                        const tabId = firstItem.tab.id;
                        const tabTrigger = document.querySelector('[data-bs-target="#' + tabId + '"], [href="#' + tabId + '"]');
                        if (tabTrigger) {
                            const bsTab = new bootstrap.Tab(tabTrigger);
                            bsTab.show();
                        }
                    }

                    setTimeout(() => {
                        if (firstItem.field) {
                            firstItem.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            if (typeof firstItem.field.focus === 'function' && firstItem.field.tagName !== 'DIV') {
                                firstItem.field.focus();
                            }
                            firstItem.field.classList.add('is-invalid');
                        }
                        banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);

                    return false;
                }

                banner.classList.add('d-none');
            });
        }
    });
</script>
<script>
    document.addEventListener('livewire:load', function() {
        var draftEl = document.getElementById('draftModal');
        if (draftEl) {
            var draftModal = new bootstrap.Modal(draftEl);
            draftModal.show();
        }
    });
</script>

<script>
    function setupEnhancementToggle() {
        const enhancementTriggerValue = "Provide digital photo enhancements";
        const enhancementWrapper = document.getElementById("enhancement-options-wrapper");
        const checkboxes = document.querySelectorAll('.enhancement-trigger');
        
        // Exit early if wrapper doesn't exist on this page
        if (!enhancementWrapper) return;

        function toggleEnhancements() {
            const isChecked = Array.from(checkboxes).some(cb =>
                cb.checked && cb.dataset.value === enhancementTriggerValue
            );
            enhancementWrapper.style.display = isChecked ? 'block' : 'none';
        }

        checkboxes.forEach(cb => {
            cb.removeEventListener('change', toggleEnhancements); // Remove previous to prevent duplication
            cb.addEventListener('change', toggleEnhancements);
        });

        toggleEnhancements(); // Run on init
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupEnhancementToggle();
    });

    // Re-run after Livewire updates DOM
    document.addEventListener("livewire:load", function() {
        Livewire.hook('message.processed', () => {
            setupEnhancementToggle();
        });
    });


    function setupOpenHouseToggle() {
        // Array of checkbox values that should trigger the input
        const triggerValues = [
            "Host in-person open houses",
            "Host scheduled broker previews or commercial tenant tours"
        ];

        const inputWrapper = document.getElementById("open-house-input-wrapper");
        const checkboxes = document.querySelectorAll('.showings-trigger');
        
        // Exit early if wrapper doesn't exist on this page
        if (!inputWrapper) return;

        function toggleInput() {
            const isChecked = Array.from(checkboxes).some(cb =>
                cb.checked && triggerValues.includes(cb.dataset.value)
            );
            inputWrapper.style.display = isChecked ? 'block' : 'none';
        }

        checkboxes.forEach(cb => {
            cb.removeEventListener('change', toggleInput);
            cb.addEventListener('change', toggleInput);
        });

        toggleInput(); // Run on init
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupOpenHouseToggle();
    });

    document.addEventListener("livewire:load", function() {
        Livewire.hook('message.processed', () => {
            setupOpenHouseToggle();
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize event listeners
        setupServiceCheckboxes();

        // Handle Livewire events
        setupLivewireHooks();
    });

    // Store visibility states in memory
    const visibilityStates = {
        enhancements: false,
        customEnhancement: false
    };

    function setupServiceCheckboxes() {
        // Main enhancement checkbox
        const enhancementCheckbox = document.querySelector('[data-enhancement-trigger]');
        if (enhancementCheckbox) {
            // Set initial state
            visibilityStates.enhancements = enhancementCheckbox.checked;
            updateEnhancementVisibility();

            // Add change listener
            enhancementCheckbox.addEventListener('change', function() {
                visibilityStates.enhancements = this.checked;
                updateEnhancementVisibility();

                // Notify Livewire
                Livewire.emit('enhancementVisibilityChanged', this.checked);
            });
        }

        // Other option checkbox
        const otherCheckbox = document.querySelector('[data-other-option]');
        if (otherCheckbox) {
            // Set initial state
            visibilityStates.customEnhancement = otherCheckbox.checked;
            updateCustomEnhancementVisibility();

            // Add change listener
            otherCheckbox.addEventListener('change', function() {
                visibilityStates.customEnhancement = this.checked;
                updateCustomEnhancementVisibility();

                // Notify Livewire
                Livewire.emit('customEnhancementVisibilityChanged', this.checked);
            });
        }
    }

    function setupLivewireHooks() {
        // Handle Livewire initialization
        document.addEventListener('livewire:load', function() {
            // Sync visibility after Livewire updates
            Livewire.hook('message.processed', () => {
                // Enhancement options
                const enhancementCheckbox = document.querySelector('[data-enhancement-trigger]');
                if (enhancementCheckbox) {
                    visibilityStates.enhancements = enhancementCheckbox.checked;
                    updateEnhancementVisibility();
                }

                // Custom enhancement
                const otherCheckbox = document.querySelector('[data-other-option]');
                if (otherCheckbox) {
                    visibilityStates.customEnhancement = otherCheckbox.checked;
                    updateCustomEnhancementVisibility();
                }
            });
        });

        // Listen for Livewire events
        Livewire.on('updateEnhancementVisibility', (visible) => {
            visibilityStates.enhancements = visible;
            updateEnhancementVisibility();
        });

        Livewire.on('updateCustomEnhancementVisibility', (visible) => {
            visibilityStates.customEnhancement = visible;
            updateCustomEnhancementVisibility();
        });
    }

    function updateEnhancementVisibility() {
        const optionsDiv = document.getElementById('enhancementOptions');
        if (optionsDiv) {
            optionsDiv.style.display = visibilityStates.enhancements ? 'block' : 'none';
        }
    }

    function updateCustomEnhancementVisibility() {
        const customDiv = document.getElementById('customEnhancementContainer');
        if (customDiv) {
            customDiv.style.display = visibilityStates.customEnhancement ? 'block' : 'none';
        }
    }
</script>

<script>
    (function() {
        const OPEN_HOUSE_VALUE = 'Install a lockbox for Agent access';
        let boundOnce = false;

        // Delegated change handler: survives DOM swaps.
        function setupDelegatedListener() {
            if (boundOnce) return;
            document.addEventListener('change', onChange, true);
            boundOnce = true;
        }

        function onChange(e) {
            const t = e.target;
            if (!t) return;

            // Only react to our specific checkbox
            if (t.classList && t.classList.contains('service-checkbox') && t.value === OPEN_HOUSE_VALUE) {
                toggleOpenHouseContainer(t.checked);
            }
        }
        // Initialize state (called now and after each Livewire update)
        function initToggle() {
            const checkbox = document.querySelector('.service-checkbox[value="' + OPEN_HOUSE_VALUE + '"]');
            toggleOpenHouseContainer(checkbox ? checkbox.checked : false);
        }

        function toggleOpenHouseContainer(show) {
            const container = document.getElementById('openHouseInputContainer');
            if (!container) return;

            container.style.display = show ? 'block' : 'none';
            if (show) {
                const input = document.getElementById('openHouseCount');
                if (input) setTimeout(() => input.focus(), 0);
            }
        }

        // On first load
        document.addEventListener('DOMContentLoaded', () => {
            setupDelegatedListener();
            initToggle();
        });
        // Livewire v2-safe hook
        document.addEventListener('livewire:load', () => {
            if (window.Livewire && Livewire.hook) {
                Livewire.hook('message.processed', () => {
                    // Re-evaluate after the DOM is patched
                    initToggle();
                });
            }
        });
        // Safety net: if Livewire version differs or DOM nodes move, observe and re-init.
        const mo = new MutationObserver(() => initToggle());
        mo.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    })();
</script>

<script>
    // Function to add a new service input field dynamically
    function addServiceField() {
        let fieldset = document.getElementById('other-services-fieldset');
        let newServiceEntry = document.createElement('div');
        newServiceEntry.classList.add('mb-3', 'service-entry');

        newServiceEntry.innerHTML = `
            <label for="other-services-input" class="form-label">Specify any additional services requested</label>
            <input type="text" class="form-control mb-2" placeholder="Specify any additional services requested">
            <button type="button" class="btn btn-danger btn-sm remove-service" onclick="removeService(this)">❌ Remove</button>
        `;

        fieldset.appendChild(newServiceEntry);
    }
    // Function to remove an individual service input field
    function removeService(button) {
        let serviceEntry = button.closest('.service-entry');
        serviceEntry.remove();
    }
</script>

<script>
    // Function to toggle the enhancement options based on checkbox state
    function toggleEnhancements(checkbox) {
        var enhancementOptions = document.getElementById('enhancement-options');

        // Check if the checkbox is checked
        if (checkbox.value === "Provide digital enhancements to media assets" && checkbox.checked) {
            enhancementOptions.style.display = "block"; // Show the enhancement options
        } else {
            enhancementOptions.style.display = "none"; // Hide the enhancement options
        }
    }
</script>
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('feeTypeChanged', (type) => {
            const input = document.getElementById('assignmentFeeInput');
            if (input) {
                // Focus the input field
                input.focus();

                // Move cursor position based on type
                if (type === '%') {
                    // For percentage, move cursor to end
                    input.setSelectionRange(input.value.length, input.value.length);
                } else {
                    // For flat fee, move cursor to start
                    input.setSelectionRange(0, 0);
                }
            }
        });
    });
</script>

{{-- <script>
        document.addEventListener('DOMContentLoaded', initPhoneFormatter);
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:load', initPhoneFormatter);
            // Re-bind after Livewire DOM updates (v2/v3 compatible enough for this use)
            Livewire.hook?.('message.processed', () => setTimeout(initPhoneFormatter, 0));
        }

        function initPhoneFormatter() {
            const input = document.getElementById('phone_number');
            if (!input) return;

            input.removeEventListener('input', onInputSanitize);
            input.removeEventListener('blur', onBlurFormat);
            input.removeEventListener('paste', onPasteDigitsOnly);
            input.removeEventListener('keydown', blockNonNumericExceptControls);

            input.addEventListener('input', onInputSanitize);
            input.addEventListener('blur', onBlurFormat);
            input.addEventListener('paste', onPasteDigitsOnly);
            input.addEventListener('keydown', blockNonNumericExceptControls);

            // If there is an initial value, render formatted but keep model raw digits
            if (input.value) {
                const digits = onlyDigits(input.value).slice(0, 10);
                input.value = formatUS(digits);
                // single push on load is okay:
                setLivewireModel(input, digits);
            }
        }

        // --- Handlers ---

        // While typing: keep ONLY digits (max 10). Do NOT push to Livewire here.
        function onInputSanitize(e) {
            const input = e.target;
            const selStart = input.selectionStart ?? input.value.length;
            const selEnd = input.selectionEnd ?? input.value.length;

            const digits = onlyDigits(input.value).slice(0, 10);
            input.value = digits; // show plain digits while typing

            // restore caret
            requestAnimationFrame(() => {
                const pos = Math.min(digits.length, selStart);
                input.setSelectionRange(pos, pos);
            });
        }

        // On blur: show formatted, then update Livewire with raw digits
        function onBlurFormat(e) {
            const input = e.target;
            const digits = onlyDigits(input.value).slice(0, 10);
            input.value = formatUS(digits);
            setLivewireModel(input, digits); // <-- single update here
        }

        // Paste: allow only digits; optional immediate push to Livewire after paste
        function onPasteDigitsOnly(e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text') || '';
            const digits = onlyDigits(text).slice(0, 10);
            insertAtCursor(e.target, digits);
            e.target.dispatchEvent(new Event('input', {
                bubbles: true
            }));
            // Optionally push right after paste:
            // setLivewireModel(e.target, onlyDigits(e.target.value).slice(0, 10));
        }

        function blockNonNumericExceptControls(e) {
            const allowedKeyCodes = [8, 9, 13, 27, 46]; // backspace, tab, enter, esc, delete
            const isCtrlCombo = (e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x'].includes(e.key.toLowerCase());
            const isNav = e.key.startsWith?.('Arrow') || ['Home', 'End'].includes(e.key);
            if (allowedKeyCodes.includes(e.keyCode) || isCtrlCombo || isNav) return;
            if (!/^\d$/.test(e.key)) e.preventDefault();
        }

        // --- Helpers ---
        function onlyDigits(v) {
            return (v || '').replace(/\D/g, '');
        }

        function formatUS(d) {
            if (!d) return '';
            if (d.length <= 3) return d;
            if (d.length <= 6) return d.replace(/(\d{3})(\d+)/, '$1-$2');
            return d.replace(/(\d{3})(\d{3})(\d{1,4}).*/, '$1-$2-$3');
        }

        function setLivewireModel(input, rawDigits) {
            if (typeof Livewire === 'undefined') return;
            const componentEl = input.closest('[wire\\:id]');
            if (!componentEl) return;
            const component = Livewire.find?.(componentEl.getAttribute('wire:id'));
            const model = input.getAttribute('wire:model') || input.getAttribute('wire:model.defer') || input.getAttribute(
                'wire:model.live') || input.getAttribute('wire:model.lazy');
            if (component && model) component.set(model.replace(/^.+?:/, ''),
            rawDigits); // strips any "entangle:"-style prefixes if present
        }

        function insertAtCursor(input, text) {
            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? input.value.length;
            const before = input.value.substring(0, start);
            const after = input.value.substring(end);
            input.value = before + text + after;
            const pos = start + text.length;
            requestAnimationFrame(() => input.setSelectionRange(pos, pos));
        }
    </script> --}}

<script>
    // Validate input (allow only numbers, one decimal point, and commas for thousands separators)
    function validateInput(input) {
        let value = input.value;

        // Remove all non-numeric characters, except for one decimal point and commas
        value = value.replace(/[^0-9.,]/g, '');

        // Allow only one decimal point
        if ((value.match(/\./g) || []).length > 1) {
            value = value.replace(/\.(?=.*\.)/, ''); // Remove extra decimal points
        }
        // Remove commas for internal value
        input.value = value.replace(/,/g, ''); // Clean up commas
    }
    // Reformat input on blur (add commas for thousands)
    function formatInput(input) {
        let value = input.value;

        // Remove any non-numeric characters except the decimal point
        let cleanValue = value.replace(/[^0-9.]/g, '');

        // Parse the number and format with commas for thousands
        let numValue = parseFloat(cleanValue.replace(/,/g, ''));

        // Only format if it's a valid number
        if (!isNaN(numValue)) {
            // Format number with commas for readability
            input.value = numValue.toLocaleString();
        } else {
            input.value = ''; // If it's not a valid number, clear the input
        }
    }
</script>

<script>
    function getErrorEl(input) {
        // Prefer explicit linkage via data-error-id; otherwise, fall back to nearest .form-group .error
        const byId = input.dataset.errorId && document.getElementById(input.dataset.errorId);
        if (byId) return byId;
        const group = input.closest('.form-group');
        return group ? group.querySelector('.error') : null;
    }

    // Allow digits, commas, and a single decimal point; format with commas; keep caret stable
    function validateInput(input) {
        const errorEl = getErrorEl(input);
        const oldVal = input.value;
        let caret = input.selectionStart;

        // Count commas before caret for later adjustment
        const commasBefore = (oldVal.slice(0, caret).match(/,/g) || []).length;

        // Keep only digits, commas, periods
        let v = oldVal.replace(/[^0-9.,]/g, '');

        // Only one decimal point
        const firstDot = v.indexOf('.');
        if (firstDot !== -1) {
            // remove any additional dots
            v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
        }

        // No leading dot
        if (v.startsWith('.')) v = v.slice(1);

        // Format with commas
        const formatted = formatNumberWithCommas(v);
        input.value = formatted;

        // Adjust caret by net change in commas before caret
        const commasAfter = (formatted.slice(0, caret).match(/,/g) || []).length;
        const delta = commasAfter - commasBefore;
        const newPos = Math.max(0, Math.min(formatted.length, caret + delta));
        input.setSelectionRange(newPos, newPos);

        // Error message if original had invalid chars
        if (/[^0-9.,]/.test(oldVal)) {
            errorEl && (errorEl.innerText =
                "Please enter a valid number. Use a period for decimals (e.g., 50,000.50). Letters and special characters are not permitted."
            );
        } else {
            errorEl && (errorEl.innerText = "");
        }
    }

    function formatNumberWithCommas(value) {
        const clean = value.replace(/,/g, '');
        const parts = clean.split('.');
        let intPart = parts[0] || '';
        const decPart = parts[1] !== undefined ? '.' + parts[1] : '';

        // insert commas in integer part
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        return intPart + decPart;
    }

    function handlePaste(event) {
        event.preventDefault();
        const input = event.target;
        const errorEl = getErrorEl(input);

        let text = (event.clipboardData || window.clipboardData).getData('text');

        // Strip invalids
        text = text.replace(/[^0-9.,]/g, '');

        // Only one decimal point
        const firstDot = text.indexOf('.');
        if (firstDot !== -1) {
            text = text.slice(0, firstDot + 1) + text.slice(firstDot + 1).replace(/\./g, '');
        }

        // No leading dot
        if (text.startsWith('.')) text = text.slice(1);

        input.value = formatNumberWithCommas(text);
        errorEl && (errorEl.innerText = "");
        // Trigger validation formatting + caret fix once more
        validateInput(input);
    }

    function reformatNumber(input) {
        const errorEl = getErrorEl(input);
        let v = input.value.replace(/,/g, '');
        const parts = v.split('.');
        let intPart = parts[0] || '';
        let decPart = parts[1] || '';

        // Limit to two decimals on blur (optional; remove if you want unlimited)
        if (decPart) decPart = decPart.slice(0, 2);

        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = decPart ? `${intPart}.${decPart}` : intPart;

        errorEl && (errorEl.innerText = "");
    }
</script>


<!-- <script>
 document.getElementById('listing_title').addEventListener('input', function() {
    const inputValue = this.value;
    // Get the first element with the class 'error'
    const errorMessage = document.getElementsByClassName('error')[0];

    // Regular expression to match special characters (other than letters, numbers, and spaces)
    const specialCharPattern = /[^\w\s]/;

    // If special characters are found, remove them and show error message
    if (specialCharPattern.test(inputValue)) {
        // Remove all special characters
        this.value = inputValue.replace(specialCharPattern, '');

        // Display the error message in the span with the class 'error'
        errorMessage.textContent = 'Please enter only numbers and characters. Special characters are not allowed.';
    } else {
        // Clear the error message if input is valid
        errorMessage.textContent = ''; // Empty string clears the error message
    }
});

</script> -->

<!-- DEBUG FINGERPRINT: tenant-agent-auction.blade.php loaded from resources/views/livewire/tenant-agent-auction.blade.php | Component: App\Http\Livewire\TenantAgentAuction -->

@endpush
