@push('styles')
<link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
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

    .nav-tabs .nav-link.active {
        background-color: #049399 !important;
        color: white !important;
        border-color: #049399 !important;
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
        background-color: #d97706;
        color: #fff !important;
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
['name' => 'Updated/Renovated', 'display' => 'Updated / Renovated'],
['name' => 'Partially Updated', 'display' => 'Partially Updated'],
['name' => 'Older but Clean', 'display' => 'Older but Clean & Well Maintained'],
['name' => 'No Preference', 'display' => 'No Preference'],
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
['name' => 'Updated / Renovated'],
['name' => 'Partially Updated'],
['name' => 'Older but Well Maintained'],
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

                @if ($hasDrafts)
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
                                            {{ $draft->title ?: 'Untitled Draft – ' . $draft->updated_at->format('m/d/Y') }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" style="border-color: #dc3545; color: #dc3545;"
                                            data-bs-dismiss="modal"
                                            wire:click="deleteDraft('{{ $draft->id }}')" wire:ignore.self
                                            onclick="setTimeout(() => { window.location = '{{ route('hire.agent.auction' , ['user_type' => $user_type])}}' }, 100)">
                                            <i class="fa-solid fa-trash"></i>
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
                                    <i class="fa-solid fa-trash me-1"></i> Delete All Drafts
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if (session()->has('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

            <div id="wizard-form-container" class="container pt-5 pb-5" data-service-type="{{ $service_type }}">

                <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                    <strong>Please complete the required fields before submitting.</strong>
                    <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                </div>

                <form id="create-auction-form" wire:submit.prevent="store" novalidate>
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
                    $isAgentUser = auth()->check() && auth()->user()->user_type === 'agent';

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
                    $restTabs = [$firstRest, 'Additional Details'];
                    if ($user_type !== 'landlord' and $user_type !== 'buyer' and $user_type !== 'seller') {
                    array_splice($restTabs, 1, 0, 'Pre-Screening');
                    }
                    if ($service_type === 'full_service') {
                    $restTabs[] = 'Representation Preferences & Compatibility';
                    }
                    if ($isAgentUser) {
                    $restTabs[] = 'Referral & Cooperation Terms';
                    }

                    $infoTabs = [
                    'tenant' => 'Tenant Information',
                    'seller' => 'Seller Information',
                    'buyer' => 'Buyer Information',
                    'landlord' => 'Landlord Information',
                    ];
                    $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent';

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
                                {{ ($isAgentUser && $tab === ($infoTabs[$user_type] ?? null)) ? 'Agent Credentials & Contact Info' : $tab }}
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
                                @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                Agent Credentials & Contact Info
                                @elseif ($user_type === 'tenant')
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

                                <!-- Additional Details Tab - Adjust index based on user_type -->

                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? 3 : 4) ? 'show active' : '' }}"
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

                                <!-- Representation Preferences & Compatibility Tab — all full_service roles (Task #1169) -->
                                @if ($service_type === 'full_service')
                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? 4 : 5) ? 'show active' : '' }}"
                                    id="representation-preferences-compatibility" role="tabpanel"
                                    aria-labelledby="representation-preferences-compatibility-tab">
                                    @if ($user_type === 'tenant')
                                        @include('livewire.tenant-agent-auction-tabs.commission-based.representation-compatibility')
                                    @elseif ($user_type === 'seller')
                                        @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.representation-compatibility')
                                    @elseif ($user_type === 'buyer')
                                        @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.representation-compatibility')
                                    @elseif ($user_type === 'landlord')
                                        @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.representation-compatibility')
                                    @endif
                                </div>
                                @endif

                                <!-- Referral & Cooperation Terms Tab - Agent only -->
                                @if ($isAgentUser)
                                <div class="tab-pane fade {{ $activeTab === (in_array($user_type, ['landlord', 'buyer', 'seller']) ? ($service_type === 'full_service' ? 5 : 4) : 6) ? 'show active' : '' }}"
                                    id="referral-cooperation-terms" role="tabpanel" aria-labelledby="referral-cooperation-terms-tab">
                                    <div class="p-3">
                                        <h5 class="fw-bold mb-3">Referral &amp; Cooperation Terms</h5>
                                        <div class="mb-4">
                                            <label class="form-label fw-semibold" for="referral_percentage_create">Referral Fee (%) <span class="text-muted fw-normal">(Agent-to-Agent)</span></label>
                                            <input type="number"
                                                   class="form-control"
                                                   id="referral_percentage_create"
                                                   wire:model.defer="referral_percentage"
                                                   min="0" max="100" step="0.01"
                                                   placeholder="e.g., 25">
                                            <div class="form-text text-muted mt-1" style="font-size:.85rem;">
                                                This is the referral fee offered to or requested from the hired Agent or their brokerage. This term is negotiated between agents and is not paid by the client.
                                            </div>
                                            @error('referral_percentage') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Info Tab - Adjust index based on user_type -->
                                @php
                                    $infoTabId = match($user_type) {
                                        'tenant' => 'tenant-information',
                                        'seller' => 'seller-information',
                                        'buyer' => 'buyer-information',
                                        'landlord' => 'landlord-information',
                                        default => 'tenant-information'
                                    };
                                    $infoTabIndex = in_array($user_type, ['landlord', 'buyer', 'seller'])
                                        ? ($service_type === 'full_service' ? ($isAgentUser ? 6 : 5) : ($isAgentUser ? 5 : 4))
                                        : ($isAgentUser ? 7 : 6);
                                @endphp
                                <div class="tab-pane fade {{ $activeTab === $infoTabIndex ? 'show active' : '' }}"
                                    id="{{ $infoTabId }}" role="tabpanel" aria-labelledby="{{ $infoTabId }}-tab">

                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                    @include('livewire.partials.agent-credentials')
                                    @elseif ($user_type === 'tenant')
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
                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                    @include('livewire.partials.agent-credentials')
                                    @else
                                    @include('livewire.tenant-agent-auction-tabs.commission-based.tenant-info')
                                    @endif
                                </div>
                                @endif
                            </div>
                            <!-- Navigation Buttons -->
                            <div class="d-flex justify-content-between form-group mt-4">
                                <div>
                                    <button type="button" class="btn btn-secondary wizard-step-back">Previous</button>
                                </div>
                                <div class="d-flex justify-content-between set-button">

                                    @if(!$listingId || $isDraft)
                                    <button type="button" class="btn btn-outline-primary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                        <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                        <span wire:loading wire:target="saveDraft">Saving...</span>
                                    </button>
                                    @endif

                                    <button type="button" class="btn btn-primary wizard-step-next">Next</button>

                                    <button type="submit" class="btn btn-success wizard-step-finish" id="save-button" wire:loading.attr="disabled" wire:target="store">
                                        <span wire:loading.remove wire:target="store">{{ ($listingId && !$isDraft) ? 'Save Edit' : 'Submit' }}</span>
                                        <span wire:loading wire:target="store">{{ ($listingId && !$isDraft) ? 'Saving...' : 'Submitting...' }}</span>
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
            window.initFullServiceSelect2Multiple($exEl);
        }
        var saved = [];
        try { saved = JSON.parse($exEl.attr('data-selected') || '[]'); } catch(e) {}
        if (!saved.length) {
            try { saved = @this.get('exchange_item') || []; } catch(e) {}
        }
        if (saved.length > 0) {
            $exEl.val(saved).trigger('change.select2');
        }
        $('#other_exchange_item_wrapper').toggle(($exEl.val() || []).includes('Other'));
        if (!$exEl.data('exchange-change-bound')) {
            $exEl.on('change', function(e) {
                var selectedValues = $(this).val() || [];
                @this.set('exchange_item', selectedValues, false);
                $('#other_exchange_item_wrapper').toggle(selectedValues.includes('Other'));
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
                setTimeout(syncSelect2MultiSelects, 200);
                setTimeout(window._updateNextSubmitButtons, 50);
                var target = this.getAttribute('data-bs-target') || this.getAttribute('href') || '';
                if (target === '#representation-preferences-compatibility') {
                    setTimeout(initCompatibilitySelect2, 250);
                }
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
    function debouncedSet(field, value, delay) { clearTimeout(_s2Timers[field]); _s2Timers[field] = setTimeout(function() { safeLivewireSet(field, value, true); }, delay || 200); }

    function syncAllSelect2BeforeSave() {
        Object.keys(_s2Timers).forEach(function(k) { clearTimeout(_s2Timers[k]); });
        _s2Timers = {};

        var fields = [
            { sel: '#property_items', field: 'property_items', multi: true },
            { sel: '#condition_prop_buyer', field: 'condition_prop_buyer', multi: true },
            { sel: '#sale_provision', field: 'sale_provision', multi: true },
            { sel: '#offered_financing', field: 'offered_financing', multi: true },
            { sel: '#garage_parking_spaces_option_landlord', field: 'garage_parking_spaces_option', multi: true },
            { sel: '#garage_parking_spaces_option', field: 'garage_parking_spaces_option', multi: true },
            { sel: '#leasing_spaces_tenant', field: 'leasing_spaces_tenant', multi: true },
            { sel: '#view_preference', field: 'view_preference', multi: true },
            { sel: '.tenant_pays', field: 'tenant_pays', multi: true },
            { sel: '.owner_pays', field: 'owner_pays', multi: true },
            { sel: '.rent_includes', field: 'rent_includes', multi: true },
            { sel: '.terms_of_lease', field: 'terms_of_lease', multi: true },
            { sel: '#appliances', field: 'appliances', multi: true },
            { sel: '.lease_term_options', field: 'desired_lease_length', multi: true },
            { sel: '.lease_for', field: 'lease_for', multi: true },
            { sel: '#non_negotiable_amenities', field: 'non_negotiable_amenities', multi: true },
            // Representation Preferences & Compatibility — tenant/seller share #compat_representation_priorities (Task #1094 / #1169)
            { sel: '#compat_representation_priorities',   field: 'compatibility_preferences.{{ $user_type }}_specific.representation_priorities', multi: true },
            { sel: '#compat_most_important_agent_traits', field: 'compatibility_preferences.tenant_specific.most_important_agent_traits', multi: true },
            // Representation Preferences & Compatibility — seller (Task #1169)
            { sel: '#compat_preferred_contact_method',    field: 'compatibility_preferences.seller_specific.preferred_contact_method', multi: true },
            { sel: '#compat_willing_to_negotiate_on',     field: 'compatibility_preferences.seller_specific.willing_to_negotiate_on', multi: true },
            { sel: '#compat_qualities_most_important',    field: 'compatibility_preferences.seller_specific.qualities_most_important', multi: true },
            { sel: '#compat_showing_availability',        field: 'compatibility_preferences.seller_specific.showing_availability', multi: true },
            // Representation Preferences & Compatibility — buyer/landlord (Task #1169)
            { sel: '#representation_priorities',          field: 'compatibility_preferences.{{ $user_type }}_specific.representation_priorities', multi: true },
        ];
        fields.forEach(function(f) {
            var $el = $(f.sel);
            if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                var val = $el.first().val();
                if (f.multi && Array.isArray(val)) { val = [...new Set(val)]; }
                safeLivewireSet(f.field, val);
                if (f.field === 'condition_prop_buyer') {
                    var _cpbJson = JSON.stringify(val || []);
                    var _cpbJsonInput = document.querySelector('input[wire\\:model="condition_prop_buyer_json"]');
                    if (_cpbJsonInput) {
                        _cpbJsonInput.value = _cpbJson;
                        _cpbJsonInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    safeLivewireSet('condition_prop_buyer_json', _cpbJson);
                }
            }
        });

        var $numUnit = $('.number_of_unit_type');
        if ($numUnit.length && $numUnit.hasClass('select2-hidden-accessible')) {
            var nuVals = $numUnit.first().val() || [];
            nuVals = [...new Set(nuVals)];
            safeLivewireSet('number_of_unit_type', nuVals);
        }
    }

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
    // Track user-changed selects so syncSelectValues() doesn't snap them back
    // during an in-flight Livewire round-trip. Any select the user has touched
    // within the last 2 seconds is protected from auto-sync overwrite.
    if (!window._lwUserChangeListenerAdded) {
        window._lwUserChangeListenerAdded = true;
        document.addEventListener('change', function(e) {
            var tgt = e.target;
            var sel = (tgt.tagName === 'SELECT' && tgt.hasAttribute('wire:model')) ? tgt : null;
            if (!sel && tgt.closest) { sel = tgt.closest('select[wire\\:model]'); }
            if (sel) {
                var prop = sel.getAttribute('wire:model');
                if (prop) {
                    window._lwRecentUserChange = window._lwRecentUserChange || {};
                    window._lwRecentUserChange[prop] = Date.now();
                }
            }
        }, true);
    }

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
                // Skip selects the user has changed within the last 2 seconds —
                // their wire:model (immediate) is already syncing; overwriting here
                // would cause a visible snapback during the Livewire round-trip.
                if (window._lwRecentUserChange && window._lwRecentUserChange[wireModel] &&
                    (Date.now() - window._lwRecentUserChange[wireModel]) < 2000) {
                    return;
                }
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
            { id: '.lease_for', prop: 'lease_for' },
            { id: '#property_items', prop: 'property_items' },
            { id: '#view_preference', prop: 'view_preference' },
            { id: '#appliances', prop: 'appliances' },
            { id: '#credit_scroe_rating', prop: 'credit_scroe_rating' },
            { id: '.number_of_unit_type', prop: 'number_of_unit_type' },
            { id: '#garage_parking_spaces_option', prop: 'garage_parking_spaces_option' },
            { id: '#garage_parking_spaces_option_landlord', prop: 'garage_parking_spaces_option' },
            { id: '#leasing_spaces_tenant', prop: 'leasing_spaces_tenant' },
            { id: '.tenant_pays', prop: 'tenant_pays' },
            { id: '.owner_pays', prop: 'owner_pays' },
            { id: '.rent_includes', prop: 'rent_includes' },
            { id: '.terms_of_lease', prop: 'terms_of_lease' },
            { id: '.lease_term_options', prop: 'desired_lease_length' },
            { id: '#pool_type', prop: 'pool_type' },
            { id: '#non_negotiable_amenities', prop: 'non_negotiable_amenities' },
            { id: '#tenant_require', prop: 'tenant_require' },
            { id: '#garage_parking_spaces_option_buyer', prop: 'garage_parking_spaces_option_buyer' },
            { id: '#assets', prop: 'assets' },
            // Representation Preferences & Compatibility — tenant/seller share #compat_representation_priorities (Task #1094 / #1169)
            { id: '#compat_representation_priorities',       prop: 'compatibility_preferences.{{ $user_type }}_specific.representation_priorities' },
            { id: '#compat_most_important_agent_traits',     prop: 'compatibility_preferences.tenant_specific.most_important_agent_traits' },
            // Representation Preferences & Compatibility — seller (Task #1169)
            { id: '#compat_preferred_contact_method',        prop: 'compatibility_preferences.seller_specific.preferred_contact_method' },
            { id: '#compat_willing_to_negotiate_on',         prop: 'compatibility_preferences.seller_specific.willing_to_negotiate_on' },
            { id: '#compat_qualities_most_important',        prop: 'compatibility_preferences.seller_specific.qualities_most_important' },
            { id: '#compat_showing_availability',            prop: 'compatibility_preferences.seller_specific.showing_availability' },
            // Representation Preferences & Compatibility — buyer/landlord (Task #1169)
            { id: '#representation_priorities',              prop: 'compatibility_preferences.{{ $user_type }}_specific.representation_priorities' },
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

    function rehydrateSelect2FromLivewire(selector, prop) {
        try {
            var wireEl = document.querySelector('[wire\\:id]');
            if (!wireEl) return;
            var component = Livewire.find(wireEl.getAttribute('wire:id'));
            if (!component) return;
            var $el = $(selector);
            if (!$el.length || !$el.hasClass('select2-hidden-accessible')) return;
            var val = component.get(prop);
            if (val && ((Array.isArray(val) && val.length > 0) || (!Array.isArray(val) && val))) {
                $el.val(val).trigger('change.select2');
            }
        } catch(e) {
            console.warn('[rehydrateSelect2] Error for', prop, e);
        }
    }

    function initializeFullService() {


        if ($('#sale_provision').length && !$('#sale_provision').hasClass('select2-hidden-accessible')) {
            window.initFullServiceSelect2Multiple($('#sale_provision'));
            $('#sale_provision').on('change', function() {
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
            window.initFullServiceSelect2Multiple($('#offered_financing'));
            $('#offered_financing').on('change', function() {
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

        function initIncludedAssetsSelect2() {
            var $ia = $('#included_assets');
            if (!$ia.length) return;
            if (!$ia.hasClass('select2-hidden-accessible')) {
                window.initFullServiceSelect2Multiple($ia);
                $ia.off('change.includedAssets').on('change.includedAssets', function() {
                    let selectedValues = $(this).val() || [];
                    @this.set('business_assets', selectedValues, false);
                    $('.other_assets').css('display', selectedValues.includes('Other') ? 'block' : 'none');
                });
            }
            var savedBusinessAssets = @this.get('business_assets') || [];
            if (savedBusinessAssets.length > 0) {
                $ia.val(savedBusinessAssets).trigger('change.select2');
                $('.other_assets').css('display', savedBusinessAssets.includes('Other') ? 'block' : 'none');
            }
        }
        initIncludedAssetsSelect2();
        Livewire.hook('message.processed', () => { initIncludedAssetsSelect2(); });

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

        function toggleOtherPropertyCondition(vals) {
            var $div = $('.other_property_condition');
            if (!$div.length) return;
            if (vals.includes('Other')) {
                $div.removeClass('d-none');
            } else {
                $div.addClass('d-none');
            }
        }

        function initConditionSelect2() {
            var $sel = $('.condition_prop_buyer');
            if ($sel.hasClass('select2-hidden-accessible')) return;

            window.initFullServiceSelect2Multiple($sel);

            $sel.off('change.cpbSync').on('change.cpbSync', function(e) {
                let data = $(this).val() || [];
                data = [...new Set(data)];
                safeLivewireSet('condition_prop_buyer', data, true);
                syncConditionJsonBridge(data);
                toggleOtherPropertyCondition(data);
            });
        }

        $(document).ready(function() {
            initConditionSelect2();
            rehydrateSelect2FromLivewire('.condition_prop_buyer', 'condition_prop_buyer');
            toggleOtherPropertyCondition($('.condition_prop_buyer').val() || []);
        });

        /////////////////////condition_prop_buyer

        function initGarageParkingLandlord() {
            let $sel = $('#garage_parking_spaces_option_landlord');
            if (!$sel.length) return;
            if ($sel.hasClass('select2-hidden-accessible')) {
                return;
            }
            window.initFullServiceSelect2Multiple($sel);
            $sel.off('change.garageLandlordSync').on('change.garageLandlordSync', function() {
                let selectedValues = $sel.val() || [];
                debouncedSet('garage_parking_spaces_option', selectedValues);
                if (selectedValues.includes('Other')) {
                    $('#other_garage_parking_spaces_option_landlord').removeClass('d-none').show();
                } else {
                    $('#other_garage_parking_spaces_option_landlord').addClass('d-none').hide();
                }
            });
            rehydrateSelect2FromLivewire('#garage_parking_spaces_option_landlord', 'garage_parking_spaces_option');
        }
        initGarageParkingLandlord();
        Livewire.hook('message.processed', () => { initGarageParkingLandlord(); });

        function initAcceptableUnitTypeSelect2() {
            $('.number_of_unit_type').each(function() {
                var $el = $(this);
                var _s2Open = false;
                try { _s2Open = !!($el.data('select2') && $el.data('select2').isOpen()); } catch(e) {}
                if (_s2Open) return;
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
                $el.select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
                $el.off('change.unitTypeBuyer').on('change.unitTypeBuyer', function() {
                    let selectedValues = $(this).val() || [];
                    selectedValues = [...new Set(selectedValues)];
                    debouncedSet('number_of_unit_type', selectedValues);
                });
                var nuVals = @this.get('number_of_unit_type') || [];
                if (nuVals.length) {
                    $el.val(nuVals).trigger('change.select2');
                }
            });
        }
        initAcceptableUnitTypeSelect2();
        Livewire.hook('message.processed', () => { initAcceptableUnitTypeSelect2(); });



        
        // All property style options embedded once at render time — used for JS-driven option rebuild
        var tenantPropertyItemsAll = @json($property_items);

        var _lastPropertyTypeForPI = @this.get('property_type') || '';

        function rebuildPropertyItemsOptions(propType, savedVals) {
            var $pi = $('#property_items');
            if (!$pi.length) return;

            var classKey = '';
            if (propType === 'Residential Property') classKey = 'residential-length';
            else if (propType === 'Commercial Property') classKey = 'commercial-length';

            $pi.empty();
            if (classKey) {
                tenantPropertyItemsAll.forEach(function(item) {
                    if (item.class === classKey) {
                        var selected = savedVals && savedVals.indexOf(item.name) !== -1;
                        $pi.append(new Option(item.name, item.name, selected, selected));
                    }
                });
            }
        }

        function initPropertyItemsSelect2() {
            var $pi = $('#property_items');
            if (!$pi.length) return;
            if ($pi.hasClass('select2-hidden-accessible')) {
                var _s2Open = false;
                try { _s2Open = !!($pi.data('select2') && $pi.data('select2').isOpen()); } catch(e) {}
                if (_s2Open) return;
                $pi.select2('destroy');
            }
            $pi.select2({
                placeholder: "Select",
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
            });
            var lwVals = @this.get('property_items') || [];
            if (lwVals.length) {
                $pi.val(lwVals).trigger('change.select2');
            }
            $pi.off('change.piSync').on('change.piSync', function(e) {
                let selectedValues = $(this).val();
                // Update option elements with selected attribute
                $pi.find('option').removeAttr('selected');
                if (selectedValues && selectedValues.length) {
                    selectedValues.forEach(val => {
                        $pi.find(`option[value="${val}"]`).attr('selected', 'selected');
                    });
                }
                safeLivewireSet('property_items', selectedValues, false);
            });
        }
        initPropertyItemsSelect2();
        Livewire.hook('message.processed', () => {
            var currentPT = @this.get('property_type') || '';
            var currentUT = (@this.get('user_type') || '').toLowerCase();
            var lwVals = @this.get('property_items') || [];
            if (currentPT !== _lastPropertyTypeForPI) {
                _lastPropertyTypeForPI = currentPT;
                setTimeout(function() {
                    if (currentUT !== 'buyer') {
                        rebuildPropertyItemsOptions(currentPT, lwVals);
                    }
                    initPropertyItemsSelect2();
                }, 100);
            } else {
                // Property type unchanged — sync display value only; never destroy Select2
                var $pi = $('#property_items');
                if ($pi.length && $pi.hasClass('select2-hidden-accessible')) {
                    var isOpen = false;
                    try { isOpen = $pi.data('select2').isOpen(); } catch(e) {}
                    if (!isOpen) {
                        var domVals = ($pi.val() || []).slice().sort().join(',');
                        var lwSorted = lwVals.slice().sort().join(',');
                        if (domVals !== lwSorted) {
                            $pi.val(lwVals).trigger('change.select2');
                        }
                    }
                }
            }
        });

        


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



        // Function to toggle visibility of the "Other Property Style" input field (seller property style select)
        function toggleOtherPropertyStyleSeller(selectElement) {
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
                    toggleOtherPropertyStyleSeller(this);
                });

                // Manually trigger the toggle function on page load or after Livewire re-renders
                toggleOtherPropertyStyleSeller(propertySelect);
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

        function initAssetsSelect2() {
            var $assetSel = $('#assets');
            if (!$assetSel.length) return;
            if ($assetSel.hasClass('select2-hidden-accessible')) {
                $assetSel.select2('destroy');
            }
            $assetSel.select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
            $assetSel.off('change.assetsBuyer').on('change.assetsBuyer', function() {
                const vals = $assetSel.val() || [];
                debouncedSet('assets', vals);
                $('.other_assets').toggleClass('d-none', !vals.includes('Other'));
            });
            var assetVals = @this.get('assets') || [];
            if (assetVals.length) {
                $assetSel.val(assetVals).trigger('change.select2');
                if (assetVals.includes('Other')) {
                    $('.other_assets').removeClass('d-none');
                }
            }
        }
        initAssetsSelect2();
        Livewire.hook('message.processed', () => { initAssetsSelect2(); });

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
                placeholder: "Select",
                allowClear: true,
            });

            // Update Livewire property on change
            $('#credit_scroe_rating').on('change', function(e) {
                let selectedValues = $(this).val();
                safeLivewireSet('credit_scroe_rating', selectedValues);
            });
        }

        
        function initNonNegotiableAmenitiesSelect2() {
            var $nn = $('#non_negotiable_amenities');
            if (!$nn.length) return;
            var _s2Open = false;
            try { _s2Open = !!($nn.data('select2') && $nn.data('select2').isOpen()); } catch(e) {}
            if (_s2Open) return;
            var currentPT = @this.get('property_type') || '';
            if (currentPT) {
                $nn.prop('disabled', false);
            } else {
                $nn.prop('disabled', true);
            }
            if ($nn.hasClass('select2-hidden-accessible')) {
                $nn.select2('destroy');
            }
            $nn.select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                })
                .on('select2:select select2:unselect', function(e) {
                    const vals = $(this).val() || [];
                    if (vals.includes('Other')) {
                        $('.other_non_negotiable_amenities').removeClass('d-none');
                    } else {
                        $('.other_non_negotiable_amenities')
                            .addClass('d-none')
                            .find('input').val('').trigger('input');
                        safeLivewireSet('other_non_negotiable_amenities', '', true);
                    }
                    safeLivewireSet('non_negotiable_amenities', vals, true);
                });
            var nnVals = @this.get('non_negotiable_amenities') || [];
            if (nnVals.length) {
                $nn.val(nnVals).trigger('change.select2');
                if (nnVals.includes('Other')) {
                    $('.other_non_negotiable_amenities').removeClass('d-none');
                }
            }
        }
        var _lastPropertyTypeForNN = @this.get('property_type') || '';
        initNonNegotiableAmenitiesSelect2();
        Livewire.hook('message.processed', () => {
            var currentPT = @this.get('property_type') || '';
            if (currentPT !== _lastPropertyTypeForNN) {
                _lastPropertyTypeForNN = currentPT;
                initNonNegotiableAmenitiesSelect2();
            }
        });

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

            // Always hide both wrappers when property_type is not Commercial
            var currentPropType = '';
            try { currentPropType = @this.get('property_type') || ''; } catch(e) {}
            var isCommercial = currentPropType === 'Commercial Property';

            if (!isCommercial) {
                if (optionsWrapper) optionsWrapper.classList.add('d-none');
                if (otherInputWrapper) otherInputWrapper.classList.add('d-none');
                return;
            }

            // First check the main garage/parking spaces selection
            if (garageSelect) {
                if (garageSelect.value === "Yes") {
                    if (optionsWrapper) optionsWrapper.classList.remove('d-none'); // Show options dropdown
                } else {
                    if (optionsWrapper) optionsWrapper.classList.add('d-none'); // Hide options dropdown
                    if (otherInputWrapper) otherInputWrapper.classList.add('d-none'); // Also hide other input
                }
            } else {
                // garageSelect not in DOM (shouldn't happen when Commercial, but guard anyway)
                if (optionsWrapper) optionsWrapper.classList.add('d-none');
                if (otherInputWrapper) otherInputWrapper.classList.add('d-none');
            }

            // Then check if "Other" is selected in the options dropdown
            if (garageOptions && garageSelect && garageSelect.value === "Yes") {
                // Use jQuery .val() to get correct values when Select2 is active
                let selectedOptions = $('#garage_parking_spaces_option').val() || [];

                // Check if "Other" is among the selected options
                if (selectedOptions.includes("Other")) {
                    if (otherInputWrapper) otherInputWrapper.classList.remove('d-none'); // Show input field
                } else {
                    if (otherInputWrapper) otherInputWrapper.classList.add('d-none'); // Hide input field
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

        function initSelect2ViewPref() {
            var $vp = $('#view_preference');
            if (!$vp.length) return;
            if ($vp.hasClass('select2-hidden-accessible')) {
                var _s2Open = false;
                try { _s2Open = !!($vp.data('select2') && $vp.data('select2').isOpen()); } catch(e) {}
                if (_s2Open) return;
                $vp.select2('destroy');
            }
            $vp.select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                })
                .on('select2:select select2:unselect', function(e) {
                    const vals = $(this).val() || [];
                    if (vals.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                        safeLivewireSet('other_preferences', '', true);
                    }
                    safeLivewireSet('view_preference', vals, true);
                });
            var vpVals = @this.get('view_preference') || [];
            if (vpVals.length) {
                $vp.val(vpVals).trigger('change.select2');
                if (vpVals.includes('Other')) {
                    $('#other_preferences').show();
                }
            }
        }

        initSelect2ViewPref();

        ///////////////////// end view_preference

        // garage_parking_spaces_option for multi-select (buyer path)

        function initGarageParkingBuyer() {
            var $gps = $('#garage_parking_spaces_option');
            if (!$gps.length) return;
            if ($gps.hasClass('select2-hidden-accessible')) {
                return;
            }
            window.initFullServiceSelect2Multiple($gps);
            $gps.off('change.garageBuyer').on('change.garageBuyer', function() {
                const vals = $(this).val() || [];
                debouncedSet('garage_parking_spaces_option', vals);
                if (vals.includes('Other')) {
                    $('#other_parking_space_wrapper').removeClass('d-none').show();
                } else {
                    $('#other_parking_space_wrapper').addClass('d-none').hide();
                    safeLivewireSet('other_parking_space_wrapper', null);
                }
            });
            rehydrateSelect2FromLivewire('#garage_parking_spaces_option', 'garage_parking_spaces_option');
            var initVals = $gps.val() || [];
            if (initVals.includes('Other')) {
                $('#other_parking_space_wrapper').removeClass('d-none').show();
            }
        }
        initGarageParkingBuyer();
        Livewire.hook('message.processed', () => { initGarageParkingBuyer(); });

        




        ///Preference

        function initializeAppliancesSelect2() {
            var $app = $('#appliances');
            if (!$app.length) return;
            if ($app.hasClass('select2-hidden-accessible')) {
                return;
            }
            window.initFullServiceSelect2Multiple($app);
            $app.off('change.appliancesSync').on('change.appliancesSync', function() {
                let selectedValuesAppliances = $app.val() || [];
                if (selectedValuesAppliances.includes('Other')) {
                    $('#other_appliances').show();
                } else {
                    $('#other_appliances').hide();
                    debouncedSet('other_appliances', null);
                }
                debouncedSet('appliances', selectedValuesAppliances);
            });
            rehydrateSelect2FromLivewire('#appliances', 'appliances');
        }

        initializeAppliancesSelect2();
        Livewire.hook('message.processed', () => { initializeAppliancesSelect2(); });
        // End Preference




        /////////////////// leasing_spaces

        if ($('#leasing_spaces_tenant').length && !$('#leasing_spaces_tenant').hasClass('select2-hidden-accessible')) {
            window.initFullServiceSelect2Multiple($('#leasing_spaces_tenant'));

            $('#leasing_spaces_tenant').on('change', function(e) {
                debouncedSet('leasing_spaces_tenant', $(this).val() || []);
            });
        }
        rehydrateSelect2FromLivewire('#leasing_spaces_tenant', 'leasing_spaces_tenant');

        ///////////////// End leasing_spaces
        ///tenant_pays

        function toggleOtherTenantField(selectedValues) {
            if (selectedValues.includes('Other')) {
                $('.tenant_pays_other_wrapper').show();
            } else {
                $('.tenant_pays_other_wrapper').hide();
            }
        }

        function initTenantPays() {
            $('.tenant_pays').each(function() {
                const $el = $(this);
                if (!$el.hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($el);
                    $el.off('change.tenantPaysSync').on('change.tenantPaysSync', function() {
                        let selectedValues = $el.val() || [];
                        toggleOtherTenantField(selectedValues);
                        debouncedSet('tenant_pays', selectedValues);
                    });
                    rehydrateSelect2FromLivewire('.tenant_pays', 'tenant_pays');
                }
            });
        }
        initTenantPays();
        Livewire.hook('message.processed', () => { initTenantPays(); });

        function initLeaseTermOptions() {
            $('.lease_term_options').each(function() {
                const $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    return;
                }
                window.initFullServiceSelect2Multiple($el);
                $el.off('change.leaseTermSync').on('change.leaseTermSync', function() {
                    let selectedValues = $el.val() || [];
                    if (selectedValues.includes('Other')) {
                        $('.other_lease_term').show();
                    } else {
                        $('.other_lease_term').hide();
                        safeLivewireSet('other_lease_term', null);
                    }
                    debouncedSet('desired_lease_length', selectedValues);
                });
                rehydrateSelect2FromLivewire('.lease_term_options', 'desired_lease_length');
            });
        }
        initLeaseTermOptions();
        Livewire.hook('message.processed', () => { initLeaseTermOptions(); });




        // Initialize Select2


        function initializeSelect2Lease() {
            const selectElement = $('.terms_of_lease');
            if (!selectElement.length) return;
            if (selectElement.hasClass('select2-hidden-accessible')) {
                return;
            }

            selectElement.select2({
                placeholder: "Select",
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                dropdownParent: selectElement.parent()
            });

            selectElement.off('change.leaseSync').on('change.leaseSync', function() {
                const selectedValues = $(this).val() || [];
                debouncedSet('terms_of_lease', selectedValues);
                toggleLeaseOther(selectedValues);
            });

            rehydrateSelect2FromLivewire('.terms_of_lease', 'terms_of_lease');
            var initialVals = selectElement.val() || [];
            toggleLeaseOther(initialVals);
        }

        function toggleLeaseOther(selectedValues) {
            const otherContainer = $('#otherLeaseContainer');
            if (selectedValues && selectedValues.includes('Other')) {
                otherContainer.removeClass('d-none');
            } else {
                otherContainer.addClass('d-none');
            }
        }

        $(document).ready(function() {
            initializeSelect2Lease();
        });

        document.addEventListener('livewire:load', function() {
            initializeSelect2Lease();
        });

        Livewire.hook('message.processed', () => {
            initializeSelect2Lease();
        });

        // End tenant_pays
        ///owner_pays

        function toggleOwnerPaysOther(selectedValues) {
            if (selectedValues.includes('Other')) {
                $('.other_owner_pays').removeClass('d-none');
            } else {
                $('.other_owner_pays').addClass('d-none');
            }
        }

        function initOwnerPays() {
            $('.owner_pays').each(function() {
                const $el = $(this);
                if (!$el.hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($el);
                    $el.off('change.ownerPaysSync').on('change.ownerPaysSync', function() {
                        let selectedValues = $el.val() || [];
                        if (selectedValues.includes('Other')) {
                            $('.other_owner_pays').show();
                        } else {
                            $('.other_owner_pays').hide();
                            safeLivewireSet('other_owner_pays', null);
                        }
                        debouncedSet('owner_pays', selectedValues);
                    });
                    rehydrateSelect2FromLivewire('.owner_pays', 'owner_pays');
                }
            });
        }
        initOwnerPays();
        Livewire.hook('message.processed', () => { initOwnerPays(); });

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
            if (!$sel.length) return;
            var _s2Open = false;
            try { _s2Open = !!($sel.data('select2') && $sel.data('select2').isOpen()); } catch(e) {}
            if (_s2Open) return;
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }

            $sel.select2({
                placeholder: "Select",
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
            });

            var lwValues = @this.get('lease_for') || [];
            if (typeof lwValues === 'string') {
                try { lwValues = JSON.parse(lwValues); } catch(e) { lwValues = []; }
            }
            if (lwValues && lwValues.length) {
                $sel.val(lwValues).trigger('change.select2');
            }
            toggleLease($sel.val() || []);

            $sel.off('change.lfSync').on('change.lfSync', function() {
                let selectedLease = $(this).val() || [];
                // Update option elements with selected attribute
                $sel.find('option').removeAttr('selected');
                if (selectedLease && selectedLease.length) {
                    selectedLease.forEach(val => {
                        $sel.find(`option[value="${val}"]`).attr('selected', 'selected');
                    });
                }
                safeLivewireSet('lease_for', selectedLease, false);
                toggleLease(selectedLease);
            });
        }

        var _lastPropertyTypeForLF = '';
        
        initSelect2LeaseFor();
        Livewire.hook('message.processed', () => {
            var currentPT = @this.get('property_type') || '';
            var lwLease = @this.get('lease_for') || [];
            if (currentPT !== _lastPropertyTypeForLF) {
                _lastPropertyTypeForLF = currentPT;
                setTimeout(function() {
                    var $lf = $('.lease_for');
                    var _s2OpenLF = false;
                    try { _s2OpenLF = !!($lf.data('select2') && $lf.data('select2').isOpen()); } catch(e) {}
                    if (!_s2OpenLF) {
                        if ($lf.hasClass('select2-hidden-accessible')) {
                            $lf.select2('destroy');
                        }
                        initSelect2LeaseFor();
                    }
                }, 100);
            } else {
                // Property type unchanged — sync display value only; never destroy Select2
                var $lf = $('.lease_for');
                if ($lf.length && $lf.hasClass('select2-hidden-accessible')) {
                    var isOpen = false;
                    try { isOpen = $lf.data('select2').isOpen(); } catch(e) {}
                    if (!isOpen) {
                        var domVals = ($lf.val() || []).slice().sort().join(',');
                        var lwSorted = lwLease.slice().sort().join(',');
                        if (domVals !== lwSorted) {
                            $lf.val(lwLease).trigger('change.select2');
                        }
                        toggleLease($lf.val() || []);
                    }
                }
            }
        });

        // End lease_for
        //rent_includes


        function toggleOtherField(selectedValues) {
            if (selectedValues.includes('Other')) {
                $('.other_rent_input_wrapper').removeClass('d-none');
            } else {
                $('.other_rent_input_wrapper').addClass('d-none');
            }
        }

        function initRentIncludes() {
            $('.rent_includes').each(function() {
                const $el = $(this);
                if (!$el.hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($el);
                    $el.off('change.rentIncludesSync').on('change.rentIncludesSync', function() {
                        let selectedValues = $el.val() || [];
                        if (selectedValues.includes('Other')) {
                            $('.other_rent_input_wrapper').show();
                        } else {
                            $('.other_rent_input_wrapper').hide();
                            safeLivewireSet('other_rent_include', null);
                        }
                        debouncedSet('rent_includes', selectedValues);
                    });
                    rehydrateSelect2FromLivewire('.rent_includes', 'rent_includes');
                }
            });
        }
        initRentIncludes();
        Livewire.hook('message.processed', () => { initRentIncludes(); });

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

        const videoInput = document.getElementById("video-input");
        const videoError = document.getElementById("video-error");
        let videoErrorFlag = false;

        function validatePhoto(file) {
            const errEl = document.getElementById("photo-error");
            const inputEl = document.getElementById("photo-input");
            if (!file) return true;

            if (!file.type.startsWith("image/")) {
                if (errEl) { errEl.textContent = "Please upload a valid image file."; errEl.style.display = "block"; }
                if (inputEl) inputEl.value = "";
                return false;
            }

            if (file.size > 10 * 1024 * 1024) {
                if (errEl) { errEl.textContent = "Photo size must be less than 10MB."; errEl.style.display = "block"; }
                if (inputEl) inputEl.value = "";
                return false;
            }

            if (errEl) { errEl.textContent = ""; errEl.style.display = "none"; }
            return true;
        }

        function validateVideo(file) {
            if (!file) return false;

            if (!file.type.startsWith("video/")) {
                if (videoError) { videoError.textContent = "Please upload a valid video file."; videoError.style.display = "block"; }
                if (videoInput) videoInput.value = "";
                return false;
            }

            if (file.size > 20 * 1024 * 1024) {
                if (videoError) {
                    videoError.textContent = 'Video size must be under 20MB. For larger videos, please paste a link instead (e.g., YouTube or Vimeo). For privacy, you can set your video as unlisted on YouTube so only those with the link can view it.';
                    videoError.style.display = "block";
                }
                if (videoInput) videoInput.value = "";
                return false;
            }

            if (videoError) { videoError.textContent = ""; videoError.style.display = "none"; }
            return true;
        }

        function handleVideoUpload(event) {
            const file = event.target.files[0];
            if (!validateVideo(file)) return;
        }

        if (!window.__photoChangeDelegated) {
            window.__photoChangeDelegated = true;
            document.addEventListener('change', function(e) {
                if (e.target && e.target.id === 'photo-input') {
                    const file = e.target.files[0];
                    if (!validatePhoto(file)) {
                        e.stopImmediatePropagation();
                    }
                }
            }, true);
        }

        if (videoInput) {
            videoInput.addEventListener("change", handleVideoUpload);
        }

        // ─── Representation Preferences & Compatibility Select2 (Task #1094) ───────
        function initCompatibilitySelect2() {
            var $wire = @this;
            var compatSingles = [
                { id: '#compat_primary_rental_goal',                    prop: 'compatibility_preferences.tenant_specific.primary_rental_goal' },
                { id: '#compat_timeline_urgency',                       prop: 'compatibility_preferences.tenant_specific.timeline_urgency' },
                { id: '#compat_budget_flexibility',                     prop: 'compatibility_preferences.tenant_specific.budget_flexibility' },
                { id: '#compat_communication_style',                    prop: 'compatibility_preferences.tenant_specific.communication_style' },
                { id: '#compat_contact_frequency',                      prop: 'compatibility_preferences.tenant_specific.contact_frequency' },
                { id: '#compat_preferred_contact_method',               prop: 'compatibility_preferences.tenant_specific.preferred_contact_method' },
                { id: '#compat_preferred_agent_working_style',          prop: 'compatibility_preferences.tenant_specific.preferred_agent_working_style' },
                { id: '#compat_negotiation_style',                      prop: 'compatibility_preferences.tenant_specific.negotiation_style' },
                { id: '#compat_decision_making_style',                  prop: 'compatibility_preferences.tenant_specific.decision_making_style' },
                { id: '#compat_desired_level_of_agent_involvement',     prop: 'compatibility_preferences.tenant_specific.desired_level_of_agent_involvement' },
            ];

            var compatOtherMap = {
                '#compat_primary_rental_goal':                'compat-other-primary-rental-goal-wrapper',
                '#compat_communication_style':                'compat-other-communication-style-wrapper',
                '#compat_timeline_urgency':                   'compat-other-timeline-urgency-wrapper',
                '#compat_desired_level_of_agent_involvement': 'compat-other-desired-level-of-agent-involvement-wrapper',
            };

            compatSingles.forEach(function(f) {
                var el = document.querySelector(f.id);
                if (!el) return;

                // Sync value from live Livewire state so re-renders and draft loads are reflected
                var lwVal = '';
                try { lwVal = $wire.get(f.prop) || ''; } catch(e) {}
                el.value = lwVal;

                // "Other" companion wrapper initial visibility
                var otherWrapperId = compatOtherMap[f.id];
                if (otherWrapperId) {
                    var ow = document.getElementById(otherWrapperId);
                    if (ow) ow.style.display = (lwVal === 'Other') ? 'block' : 'none';
                }

                // Attach native change listener once (flag prevents duplicates across re-renders)
                if (!el._compatSyncBound) {
                    el._compatSyncBound = true;
                    el.addEventListener('change', function() {
                        var val = el.value || '';
                        safeLivewireSet(f.prop, val);
                        if (otherWrapperId) {
                            var ow2 = document.getElementById(otherWrapperId);
                            if (ow2) ow2.style.display = (val === 'Other') ? 'block' : 'none';
                        }
                    });
                }
            });

            // Multi-select: representation_priorities
            var $rp = $('#compat_representation_priorities');
            if ($rp.length) {
                var _rpOpen = false;
                try { _rpOpen = !!($rp.data('select2') && $rp.data('select2').isOpen()); } catch(e) {}
                if (!_rpOpen) {
                    if ($rp.hasClass('select2-hidden-accessible')) {
                        $rp.select2('destroy');
                    }
                    $rp.select2({ placeholder: 'Select', allowClear: true, width: '100%', closeOnSelect: false });
                    var lwRp = [];
                    try {
                        lwRp = $wire.get('compatibility_preferences.tenant_specific.representation_priorities') || [];
                        if (typeof lwRp === 'string') { lwRp = JSON.parse(lwRp) || []; }
                    } catch(e) {}
                    if (lwRp.length) {
                        $rp.val(lwRp).trigger('change.select2');
                        var rpOther = document.getElementById('compat-other-representation-priorities-wrapper');
                        if (rpOther) rpOther.style.display = lwRp.includes('Other') ? 'block' : 'none';
                    }
                }
                $rp.off('change.compatRpSync').on('change.compatRpSync', function() {
                    var vals = $(this).val() || [];
                    safeLivewireSet('compatibility_preferences.tenant_specific.representation_priorities', vals);
                    var rpOtherW = document.getElementById('compat-other-representation-priorities-wrapper');
                    if (rpOtherW) rpOtherW.style.display = vals.includes('Other') ? 'block' : 'none';
                });
            }

            // Multi-select: most_important_agent_traits
            var $mat = $('#compat_most_important_agent_traits');
            if ($mat.length) {
                var _matOpen = false;
                try { _matOpen = !!($mat.data('select2') && $mat.data('select2').isOpen()); } catch(e) {}
                if (!_matOpen) {
                    if ($mat.hasClass('select2-hidden-accessible')) {
                        $mat.select2('destroy');
                    }
                    $mat.select2({ placeholder: 'Select', allowClear: true, width: '100%', closeOnSelect: false });
                    var lwMat = [];
                    try {
                        lwMat = $wire.get('compatibility_preferences.tenant_specific.most_important_agent_traits') || [];
                        if (typeof lwMat === 'string') { lwMat = JSON.parse(lwMat) || []; }
                    } catch(e) {}
                    if (lwMat.length) {
                        $mat.val(lwMat).trigger('change.select2');
                        var matOther = document.getElementById('compat-other-most-important-agent-traits-wrapper');
                        if (matOther) matOther.style.display = lwMat.includes('Other') ? 'block' : 'none';
                    }
                }
                $mat.off('change.compatMatSync').on('change.compatMatSync', function() {
                    var vals = $(this).val() || [];
                    safeLivewireSet('compatibility_preferences.tenant_specific.most_important_agent_traits', vals);
                    var matOtherW = document.getElementById('compat-other-most-important-agent-traits-wrapper');
                    if (matOtherW) matOtherW.style.display = vals.includes('Other') ? 'block' : 'none';
                });
            }

            addIconsToInputs();
        }

        // ─── Seller Representation Preferences & Compatibility Select2 (Task #1169) ──
        function initSellerCompatSelect2Fields() {
            var $wire = @this;
            var sellerSingles = [
                { id: '#compat_communication_style',           prop: 'compatibility_preferences.seller_specific.communication_style' },
                { id: '#compat_negotiation_style',             prop: 'compatibility_preferences.seller_specific.negotiation_style' },
                { id: '#compat_primary_transaction_goal',      prop: 'compatibility_preferences.seller_specific.primary_transaction_goal' },
                { id: '#compat_preferred_agent_working_style', prop: 'compatibility_preferences.seller_specific.preferred_agent_working_style' },
                { id: '#compat_response_time_expectation',     prop: 'compatibility_preferences.seller_specific.response_time_expectation' },
                { id: '#compat_firm_on_price',                 prop: 'compatibility_preferences.seller_specific.firm_on_price' },
                { id: '#compat_flexibility_on_timeline',       prop: 'compatibility_preferences.seller_specific.flexibility_on_timeline' },
                { id: '#compat_post_sale_plan',                prop: 'compatibility_preferences.seller_specific.post_sale_plan' },
                { id: '#compat_past_agent_experience',         prop: 'compatibility_preferences.seller_specific.past_agent_experience' },
                { id: '#compat_decision_making_style',         prop: 'compatibility_preferences.seller_specific.decision_making_style' },
                { id: '#compat_involvement_level',             prop: 'compatibility_preferences.seller_specific.involvement_level' },
                { id: '#compat_open_house_preference',         prop: 'compatibility_preferences.seller_specific.open_house_preference' },
            ];
            sellerSingles.forEach(function(f) {
                var el = document.querySelector(f.id);
                if (!el) return;
                var lwVal = '';
                try { lwVal = $wire.get(f.prop) || ''; } catch(e) {}
                el.value = lwVal;
                if (f.id === '#compat_primary_transaction_goal') {
                    var ptgOw = document.getElementById('compat_ptg_other_wrapper');
                    if (ptgOw) ptgOw.style.display = (lwVal === 'Other') ? '' : 'none';
                }
                if (!el._sellerCompatSyncBound) {
                    el._sellerCompatSyncBound = true;
                    el.addEventListener('change', function() {
                        var val = el.value || '';
                        safeLivewireSet(f.prop, val);
                        if (f.id === '#compat_primary_transaction_goal') {
                            var ptgOw2 = document.getElementById('compat_ptg_other_wrapper');
                            if (ptgOw2) ptgOw2.style.display = (val === 'Other') ? '' : 'none';
                        }
                    });
                }
            });
            var sellerMultis = [
                { id: '#compat_preferred_contact_method',  prop: 'compatibility_preferences.seller_specific.preferred_contact_method' },
                { id: '#compat_willing_to_negotiate_on',   prop: 'compatibility_preferences.seller_specific.willing_to_negotiate_on' },
                { id: '#compat_representation_priorities', prop: 'compatibility_preferences.seller_specific.representation_priorities' },
                { id: '#compat_qualities_most_important',  prop: 'compatibility_preferences.seller_specific.qualities_most_important' },
                { id: '#compat_showing_availability',      prop: 'compatibility_preferences.seller_specific.showing_availability' },
            ];
            sellerMultis.forEach(function(f) {
                var $el = $(f.id);
                if (!$el.length) return;
                var _open = false;
                try { _open = !!($el.data('select2') && $el.data('select2').isOpen()); } catch(e) {}
                if (!_open) {
                    if ($el.hasClass('select2-hidden-accessible')) { $el.select2('destroy'); }
                    $el.select2({ placeholder: 'Select', allowClear: true, width: '100%', closeOnSelect: false });
                    var lwVals = [];
                    try {
                        lwVals = $wire.get(f.prop) || [];
                        if (typeof lwVals === 'string') { lwVals = JSON.parse(lwVals) || []; }
                    } catch(e) {}
                    if (lwVals.length) { $el.val(lwVals).trigger('change.select2'); }
                }
                $el.off('change.sellerCompatSync' + f.id).on('change.sellerCompatSync' + f.id, function() {
                    safeLivewireSet(f.prop, $(this).val() || []);
                });
            });
            addIconsToInputs();
        }

        // ─── Buyer Representation Preferences & Compatibility Select2 (Task #1169) ───
        function initBuyerCompatSelect2Fields() {
            var $wire = @this;
            document.querySelectorAll('[data-compat-field]').forEach(function(el) {
                var compatField = el.getAttribute('data-compat-field');
                var prop = 'compatibility_preferences.buyer_specific.' + compatField;
                var lwVal = '';
                try { lwVal = $wire.get(prop) || ''; } catch(e) {}
                el.value = lwVal;
                if (compatField === 'primary_transaction_goal') {
                    window.dispatchEvent(new CustomEvent('update-ptg-other', { detail: { showOther: lwVal === 'Other' } }));
                }
                if (compatField === 'preferred_agent_working_style') {
                    window.dispatchEvent(new CustomEvent('update-paws-other', { detail: { showOther: lwVal === 'Other' } }));
                }
                if (!el._buyerCompatSyncBound) {
                    el._buyerCompatSyncBound = true;
                    el.addEventListener('change', function() {
                        var val = el.value || '';
                        safeLivewireSet(prop, val);
                        if (compatField === 'primary_transaction_goal') {
                            window.dispatchEvent(new CustomEvent('update-ptg-other', { detail: { showOther: val === 'Other' } }));
                        }
                        if (compatField === 'preferred_agent_working_style') {
                            window.dispatchEvent(new CustomEvent('update-paws-other', { detail: { showOther: val === 'Other' } }));
                        }
                    });
                }
            });
            // Buyer representation_priorities multi-select
            var $buyerRp = $('#compat_representation_priorities');
            if ($buyerRp.length) {
                var _rpOpen = false;
                try { _rpOpen = !!($buyerRp.data('select2') && $buyerRp.data('select2').isOpen()); } catch(e) {}
                if (!_rpOpen) {
                    if ($buyerRp.hasClass('select2-hidden-accessible')) { $buyerRp.select2('destroy'); }
                    $buyerRp.select2({ placeholder: 'Select', allowClear: true, width: '100%', closeOnSelect: false });
                    var lwBuyerRp = [];
                    try {
                        lwBuyerRp = $wire.get('compatibility_preferences.buyer_specific.representation_priorities') || [];
                        if (typeof lwBuyerRp === 'string') { lwBuyerRp = JSON.parse(lwBuyerRp) || []; }
                    } catch(e) {}
                    if (lwBuyerRp.length) {
                        $buyerRp.val(lwBuyerRp).trigger('change.select2');
                        window.dispatchEvent(new CustomEvent('update-rp-other', { detail: { hasOther: lwBuyerRp.includes('Other') } }));
                    }
                }
                $buyerRp.off('change.buyerCompatRpSync').on('change.buyerCompatRpSync', function() {
                    var vals = $(this).val() || [];
                    safeLivewireSet('compatibility_preferences.buyer_specific.representation_priorities', vals);
                    window.dispatchEvent(new CustomEvent('update-rp-other', { detail: { hasOther: vals.includes('Other') } }));
                });
            }
            addIconsToInputs();
        }

        // ─── Landlord Representation Preferences & Compatibility Select2 (Task #1169) ─
        function initLandlordCompatSelect2Fields() {
            var $wire = @this;
            var $landlordRp = $('#compat_representation_priorities_landlord');
            if (!$landlordRp.length) return;
            var _rpOpen = false;
            try { _rpOpen = !!($landlordRp.data('select2') && $landlordRp.data('select2').isOpen()); } catch(e) {}
            if (!_rpOpen) {
                if ($landlordRp.hasClass('select2-hidden-accessible')) { $landlordRp.select2('destroy'); }
                $landlordRp.select2({ placeholder: 'Select', allowClear: true, width: '100%', closeOnSelect: false });
                var lwLandlordRp = [];
                try {
                    lwLandlordRp = $wire.get('compatibility_preferences.landlord_specific.representation_priorities') || [];
                    if (typeof lwLandlordRp === 'string') { lwLandlordRp = JSON.parse(lwLandlordRp) || []; }
                } catch(e) {}
                if (lwLandlordRp.length) { $landlordRp.val(lwLandlordRp).trigger('change.select2'); }
            }
            $landlordRp.off('change.landlordCompatRpSync').on('change.landlordCompatRpSync', function() {
                safeLivewireSet('compatibility_preferences.landlord_specific.representation_priorities', $(this).val() || []);
            });
            addIconsToInputs();
        }

        function _initAllCompatSelect2() {
            if ($('#compat_primary_rental_goal').length)                      { initCompatibilitySelect2(); }
            if ($('#compat_ptg_other_wrapper').length)                        { initSellerCompatSelect2Fields(); }
            if ($('[data-compat-field="primary_transaction_goal"]').length)   { initBuyerCompatSelect2Fields(); }
            if ($('#compat_representation_priorities_landlord').length)         { initLandlordCompatSelect2Fields(); }
        }
        _initAllCompatSelect2();
        Livewire.hook('message.processed', () => { _initAllCompatSelect2(); });
        // ─── End Representation Preferences & Compatibility ───────────────────────

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

    var _isEditOrDraftMode = {{ $listingId ? 'true' : 'false' }};
    window._updateNextSubmitButtons = function() {
        var tabOrder = getTabOrder();
        var activeTab = document.querySelector('.nav-link.active');
        if (!activeTab) return;
        var currentTarget = (activeTab.getAttribute('data-bs-target') || '').replace('#', '');
        var currentIndex = tabOrder.indexOf(currentTarget);
        var isLastTab = (currentIndex === tabOrder.length - 1);
        var nextBtn = document.querySelector('.wizard-step-next');
        var submitBtn = document.querySelector('.wizard-step-finish');
        if (nextBtn) nextBtn.style.display = isLastTab ? 'none' : '';
        if (submitBtn) submitBtn.style.display = '';
    };
    setTimeout(window._updateNextSubmitButtons, 300);
    
    // Call on every tab click and Livewire update
    document.addEventListener('shown.bs.tab', window._updateNextSubmitButtons);
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('message.processed', window._updateNextSubmitButtons);
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

        // Offered Financing (Select2 hides the native select — check explicitly for purchasing-terms tab)
        if (currentTabContent.id === 'purchasing-terms') {
            var ofErrorSpan = currentTabContent.querySelector('#offered_financing_error');
            var ofJqVal = (typeof jQuery !== 'undefined') ? jQuery('#offered_financing').val() : null;
            var ofIsEmpty = !ofJqVal || (Array.isArray(ofJqVal) && ofJqVal.length === 0);
            if (ofIsEmpty) {
                isValid = false;
                if (ofErrorSpan) { ofErrorSpan.textContent = 'This field is required.'; }
            } else {
                if (ofErrorSpan) { ofErrorSpan.textContent = ''; }
            }
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
        if (countiesContainer && CURRENT_USER_TYPE !== 'landlord') {
            const countyBadges = countiesContainer.querySelectorAll('.badge');
            if (!countyBadges || countyBadges.length === 0) {
                isValid = false;
                countiesContainer.classList.add('is-invalid');
                if (countiesErrorSpan) {
                    countiesErrorSpan.textContent = 'This field is required.';
                }
            } else {
                countiesContainer.classList.remove('is-invalid');
                if (countiesErrorSpan) {
                    countiesErrorSpan.textContent = '';
                }
            }
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
                if (text && text !== 'This field is required.' && !seen.has(text)) {
                    const label = ec.closest('.form-group')?.querySelector('label');
                    const fieldName = label ? label.textContent.replace(/[*:]/g, '').trim() : null;
                    if (fieldName && !seen.has(fieldName)) {
                        seen.add(fieldName);
                        const li = document.createElement('li');
                        li.textContent = fieldName;
                        errorList.appendChild(li);
                    }
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
            if (!iconClass) return;
            const cover = input.closest('.input-cover');
            if (cover) {
                if (!cover.querySelector('.input-icon')) {
                    const icon = document.createElement('i');
                    icon.className = `input-icon ${iconClass}`;
                    input.parentNode.insertBefore(icon, input);
                }
            } else if (!input.previousElementSibling?.classList.contains('input-icon')) {
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

    // Seller Sale Terms visibility logic must remain identical between the dedicated Seller path and the shared TenantAgentAuction seller path.
    // If changes are made to one path, they must also be applied to the other to keep both Seller flows consistent.
    function applySellerProvisionVisibility() {
        var data = ($('#sale_provision').val() || []);
        var provisionMap = {
            'Assignment Contract': '#seller-provision-assignment-section',
            'Other': '#seller-provision-other-section',
        };
        Object.keys(provisionMap).forEach(function(option) {
            if (data.includes(option)) {
                $(provisionMap[option]).show();
            } else {
                $(provisionMap[option]).hide();
            }
        });
    }

    function applySellerFinancingVisibility() {
        var data = ($('#offered_financing').val() || []);
        var financingMap = {
            'Assumable': '#seller-financing-assumable-section',
            'Cryptocurrency': '#seller-financing-crypto-section',
            'Exchange/Trade': '#seller-financing-exchange-section',
            'Lease Option': '#seller-financing-leaseoption-section',
            'Lease Purchase': '#seller-financing-leasepurchase-section',
            'Non-Fungible Token (NFT)': '#seller-financing-nft-section',
            'Seller Financing': '#seller-financing-sellerfinancing-section',
            'Other': '#seller-financing-other-section',
        };
        Object.keys(financingMap).forEach(function(option) {
            if (data.includes(option)) {
                $(financingMap[option]).show();
            } else {
                $(financingMap[option]).hide();
            }
        });
    }

    // Buyer Purchasing Terms visibility logic — dispatches Alpine window events for buyer sections.
    // If changes are made to one path, they must also be applied to the other to keep both Buyer flows consistent.
    function applyBuyerProvisionVisibility() {
        var data = ($('#sale_provision').val() || []);
        if (data.includes('Other')) {
            $('.sale_provision_other_wrapper').show();
        } else {
            $('.sale_provision_other_wrapper').hide();
        }
        window.dispatchEvent(new CustomEvent('update-assignment-visibility', {
            detail: { visible: data.includes('Assignment Contract') }
        }));
    }

    function applyBuyerFinancingVisibility() {
        var data = ($('#offered_financing').val() || []);
        if (data.includes('Other')) {
            $('.other_financing_wrapper').show();
        } else {
            $('.other_financing_wrapper').hide();
        }
        var traditionalTypes = ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'];
        var selectedTradLabels = traditionalTypes.filter(function(t) { return data.includes(t); });
        var $tradH5 = document.getElementById('traditional-loan-label-h5');
        if ($tradH5) { $tradH5.innerHTML = '<i class="fa-solid fa-file-invoice-dollar me-2"></i>' + selectedTradLabels.join(' / '); }
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Assumable', visible: data.includes('Assumable') } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Traditional', visible: traditionalTypes.some(function(t) { return data.includes(t); }) } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Cryptocurrency', visible: data.includes('Cryptocurrency') } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Exchange/Trade', visible: data.includes('Exchange/Trade') } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Lease Option', visible: data.includes('Lease Option') } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Lease Purchase', visible: data.includes('Lease Purchase') } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Non-Fungible Token (NFT)', visible: data.includes('Non-Fungible Token (NFT)') } }));
        window.dispatchEvent(new CustomEvent('update-financing-visibility', { detail: { type: 'Seller Financing', visible: data.includes('Seller Financing') } }));
    }

    $(document).on('change', '#sale_provision', function() {
        applySellerProvisionVisibility();
        applyBuyerProvisionVisibility();
    });
    $(document).on('change', '#offered_financing', function() {
        applySellerFinancingVisibility();
        applyBuyerFinancingVisibility();
    });

    function initSelect2(selectorId, wireModel, otherWrapper) {
        // selectorId should be just the ID without '#' prefix
        let $el = $(selectorId.startsWith('#') ? selectorId : '#' + selectorId);
        if (!$el.length) return;
        if ($el.hasClass('select2-hidden-accessible')) return;
        window.initFullServiceSelect2Multiple($el);
        $el.on('change', function() {
            let data = $(this).val() || [];
            debouncedSet(wireModel, data);
            if (otherWrapper) {
                if (data.includes('Other')) {
                    $(otherWrapper).show();
                } else {
                    $(otherWrapper).hide();
                }
            }
        });
    }

    Livewire.hook('message.processed', () => {
        addIconsToInputs();
        checkRepresentationStatus();
        
        // Re-add icons to inputs that might have been replaced by Livewire
        $('.input-cover').each(function() {
            const $container = $(this);
            const $input = $container.find('.has-icon');
            if ($input.length && !$container.find('.input-icon').length) {
                const iconClass = $input.data('icon');
                if (iconClass) {
                    $container.prepend(`<i class="input-icon ${iconClass}"></i>`);
                }
            }
        });

        if (typeof window._updateNextSubmitButtons === 'function') {
            setTimeout(window._updateNextSubmitButtons, 50);
        }

        // Buyer Preferences Select2 Re-init
        initSelect2('#property_items', 'property_items', '.other_property_items_wrapper');
        initSelect2('#condition_prop_buyer', 'condition_prop_buyer', '.other_property_condition_wrapper');
        var _cpbUT = (@this.get('user_type') || '').toLowerCase();
        if (_cpbUT === 'tenant' || _cpbUT === 'buyer') {
            var _cpbEl = $('.condition_prop_buyer');
            if (_cpbEl.length && _cpbEl.hasClass('select2-hidden-accessible')) {
                var _cpbLwVals = @this.get('condition_prop_buyer') || [];
                var _cpbDomVals = (_cpbEl.val() || []).slice().sort().join(',');
                var _cpbLwSorted = _cpbLwVals.slice().sort().join(',');
                var _cpbOpen = false;
                try { _cpbOpen = _cpbEl.data('select2').isOpen(); } catch(e) {}
                if (!_cpbOpen && _cpbDomVals !== _cpbLwSorted) {
                    _cpbEl.val(_cpbLwVals).trigger('change.select2');
                    toggleOtherPropertyCondition(_cpbLwVals);
                }
            }
        }
        initSelect2('#sale_provision', 'sale_provision', '.sale_provision_other_wrapper');
        initSelect2('#offered_financing', 'offered_financing', '.other_financing_wrapper');
        if ($('#sale_provision').length) { applySellerProvisionVisibility(); applyBuyerProvisionVisibility(); }
        if ($('#offered_financing').length) { applySellerFinancingVisibility(); applyBuyerFinancingVisibility(); }

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

            // FIX-04: Derive tab IDs dynamically from the DOM instead of using a hardcoded list.
            // This ensures every role's actual rendered tabs are scanned regardless of what ID
            // the Blade template assigns them.
            const wizardContainer = document.getElementById('wizard-form-container') || formContainer;
            const allTabPanes = wizardContainer ? wizardContainer.querySelectorAll('.tab-pane') : [];

            allTabPanes.forEach(tab => {
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
            
            // For Select2 multi-selects: the original <select> is hidden by Select2,
            // so check if the Select2 container is visible and validate via Livewire state
            if (field.tagName === 'SELECT' && field.multiple && $(field).hasClass('select2-hidden-accessible')) {
                var s2Container = $(field).next('.select2-container');
                if (s2Container.length && s2Container.is(':visible')) {
                    var selectedVals = $(field).val() || [];
                    return selectedVals.length > 0;
                }
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
                const value = field.value;
                return value !== '' && value !== null && value !== undefined;
            }
            const value = field.value;
            return value !== null && value !== undefined && value.trim() !== '';
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

            try {
                var wireEl = document.querySelector('[wire\\:id]');
                if (wireEl && typeof Livewire !== 'undefined') {
                    var comp = Livewire.find(wireEl.getAttribute('wire:id'));
                    if (comp && comp.get) {
                        var serviceType = formContainer.getAttribute('data-service-type');
                        var curUserType = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : 'tenant';
                        if (serviceType === 'full_service') {
                            var lwChecks = [
                                { prop: 'property_type', label: 'Property Type' },
                            ];
                            if (curUserType === 'tenant') {
                                lwChecks.push({ prop: 'lease_for', label: 'Offered Lease Term', isArray: true, domSel: '.lease_for' });
                                lwChecks.push({ prop: 'leasing_spaces_tenant', label: 'Leasing Space', isArray: true, domSel: '#leasing_spaces_tenant' });
                            }
                            lwChecks.forEach(function(chk) {
                                var val = comp.get(chk.prop);
                                var isEmpty;
                                if (chk.isArray) {
                                    if (typeof val === 'string') {
                                        try { val = JSON.parse(val); } catch(e2) {}
                                    }
                                    isEmpty = !val || (Array.isArray(val) && val.length === 0) || val === '' || val === '[]';
                                    if (isEmpty && chk.domSel) {
                                        var $domEl = $(chk.domSel);
                                        if ($domEl.length) {
                                            var domVal = $domEl.val();
                                            if (domVal && ((Array.isArray(domVal) && domVal.length > 0) || (typeof domVal === 'string' && domVal !== ''))) {
                                                isEmpty = false;
                                            }
                                        }
                                    }
                                } else {
                                    isEmpty = !val || val === '';
                                }
                                if (isEmpty) {
                                    invalidFields.push({ tab: 0, field: chk.prop, value: '', visible: true });
                                }
                            });
                        }
                    }
                }
            } catch(e) { }

            if (invalidFields.length > 0) {
                return false;
            }
            return true;
        }

        function updateSaveButton() {
            const allValid = validateAllTabsStrictly();
            if (allValid) {
                saveButton.classList.remove('disabled');
            } else {
                saveButton.classList.add('disabled');
            }
            saveButton.removeAttribute('disabled');
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

            // Stable label map for every required Tenant field.
            // Keyed by wire:model property name — the canonical key used for lookup
            // and deduplication.  Labels match exact wording shown in the live UI.
            const TENANT_FIELD_LABELS = {
                'listing_title':           'Listing Title',
                'working_with_agent':      'Current Representation Status with Broker',
                'desired_agent_hire_date': 'Desired Agent Hire Date',
                'listing_date':            'Listing Date',
                'expiration_date':         'Expiration Date',
                'auction_type':            'Listing Type',
                'auction_time':            'Bidding Period Length',
                'meeting_Preference':      'Meeting Preference',
                'state':                   'Acceptable State',
                'property_type':           'Acceptable Property Type',
                'bedrooms':                'Minimum Bedrooms Needed',
                'bathrooms':               'Minimum Bathrooms Needed',
                'counties':                'Acceptable Counties',
                'budget':                  'Maximum Monthly Lease Price',
                'lease_for':               'Offered Lease Term',
                'lease_date':              'Offered Lease Date',
                'leasing_spaces_tenant':   'Leasing Space',
                'number_occupant':         'Number of Occupants',
                'monthly_income':          'Estimated Monthly Net Household Income',
                'screening_concerns':      'Rental History Disclosure',
                'first_name':              'First Name',
                'last_name':               'Last Name',
                'phone_number':            'Phone Number',
                'email':                   'Email Address',
                'address':                 'Street Address',
                'property_city':           'City',
                'property_state':          'State',
                'property_county':         'County',
                'property_zip':            'ZIP Code',
                'condition_prop':          'Property Condition',
                'occupant_status':         'Occupant Type',
                'occupant_tenant':         'Occupied Until',
                'leasing_spaces':          'Leasing Space',
                'desired_rental_amount':   'Desired Rental Amount',
                'lease_amount_frequency':  'Lease Amount Frequency',
                'desired_lease_length':    'Desired Lease Term',
                // Buyer-specific fields
                'property_items':          'Acceptable Property Style',
                'target_closing_date':     'Target Closing Date',
                'maximum_budget':          'Maximum Budget',
                'offered_financing':       'Offered Financing/Currency',
                'tenant_require':          'Furnishings Needed',
                'pets':                    'Pets',
                'real_estate_purchase':    'Business & Real Estate Purchase Requirements',
                // Seller-specific fields
                'sale_provision':          'Special Sale Provision',
                'current_status':          "Seller's Current Status",
                // Buyer-specific labels
                'buyer_current_status':    "Buyer's Current Status",
            };

            // Returns the canonical property key for a field: wire:model value first,
            // then wire:model.defer / wire:model.lazy, then id, then name.
            function resolveTenantFieldKey(field) {
                return field.getAttribute('wire:model')
                    || field.getAttribute('wire:model.defer')
                    || field.getAttribute('wire:model.lazy')
                    || field.id
                    || field.name
                    || '';
            }

            // Returns the user-facing label for a Tenant field.
            // Prefers TENANT_FIELD_LABELS (stable); falls back to DOM label text.
            // property_type label is role-aware: landlord/seller see 'Property Type'.
            function resolveTenantFieldLabel(field) {
                const key = resolveTenantFieldKey(field);
                if (typeof CURRENT_USER_TYPE !== 'undefined' && CURRENT_USER_TYPE === 'seller') {
                    if (key === 'property_type')    return 'Property Type';
                    if (key === 'property_items')   return 'Property Style';
                    if (key === 'maximum_budget')   return 'Desired Sale Price';
                    if (key === 'offered_financing') return 'Offered Financing/Currency';
                }
                if (typeof CURRENT_USER_TYPE !== 'undefined' && CURRENT_USER_TYPE === 'buyer') {
                    if (key === 'current_status')   return "Buyer's Current Status";
                }
                if (key === 'property_type' && typeof CURRENT_USER_TYPE !== 'undefined' &&
                    (CURRENT_USER_TYPE === 'landlord' || CURRENT_USER_TYPE === 'seller')) {
                    return 'Property Type';
                }
                if (key && TENANT_FIELD_LABELS[key]) {
                    return TENANT_FIELD_LABELS[key];
                }
                const label = field.closest('.form-group')?.querySelector('label');
                if (label) {
                    return label.textContent.replace(/[*:]/g, '').trim();
                }
                return field.getAttribute('placeholder') || field.name || field.id || 'Unknown field';
            }

            // Returns true if the field is hidden by conditional rendering WITHIN its
            // tab pane (d-none / inline display:none), but does NOT treat an inactive
            // tab-pane as hidden — enabling cross-tab validation.
            function isTenantFieldHiddenWithinTab(field) {
                const tabPane = field.closest('.tab-pane');
                if (!tabPane) return false;
                let el = field.parentElement;
                while (el && el !== tabPane) {
                    if (el.classList && el.classList.contains('d-none')) return true;
                    if (el.style && el.style.display === 'none') return true;
                    el = el.parentElement;
                }
                return false;
            }

            // ---- TENANT CORRECTION MODE ------------------------------------------------
            // Tracks whether the form is in guided-correction mode (submit was blocked
            // and we are walking the user through each still-missing required field).
            var _tenantCorrectionMode = false;
            var _tenantMissingItems = [];   // last computed ordered missing-field list

            // Re-runs the tenant validation checks and returns the current ordered list
            // of still-missing required fields.  Mirrors the submit-handler checks so
            // results are always fresh (never stale DOM).
            function tenantGetInvalidItems() {
                var items = [];
                var reqFields = getAllRequiredFields();
                reqFields.forEach(function(field) {
                    if (isTenantFieldHiddenWithinTab(field)) return;
                    // FIX-03: Skip disabled fields — consistent with isFieldVisible() used elsewhere.
                    // A disabled field is not fillable by the user and must not block guided correction.
                    if (field.disabled) return;
                    // FIX-03: Skip Select2 mirror elements — they are managed by Select2 and
                    // validated separately via the Livewire state checks below.
                    if (field.tagName === 'SELECT' && field.multiple &&
                        field.classList.contains('select2-hidden-accessible')) return;
                    var isEmpty = false;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        isEmpty = !field.checked;
                    } else if (field.type === 'select-one' || field.type === 'select-multiple') {
                        isEmpty = field.value === '' || field.value === null || field.value === undefined;
                    } else {
                        isEmpty = !field.value || field.value.trim() === '';
                    }
                    if (isEmpty) {
                        var _fKey = resolveTenantFieldKey(field);
                        items.push({ field: field, tab: field.closest('.tab-pane'), fieldName: resolveTenantFieldLabel(field), key: _fKey });
                    }
                });

                // Counties badge check
                var countiesContainer = document.querySelector('.counties-container');
                if (countiesContainer && (typeof CURRENT_USER_TYPE === 'undefined' || CURRENT_USER_TYPE !== 'landlord')) {
                    var countyBadges = countiesContainer.querySelectorAll('.badge');
                    if (!countyBadges || countyBadges.length === 0) {
                        items.push({ field: countiesContainer, tab: countiesContainer.closest('.tab-pane'), fieldName: TENANT_FIELD_LABELS['counties'] || 'Acceptable Counties', key: 'counties' });
                    }
                }

                // Livewire-state checks (Select2 multi-selects + property_type)
                // Role-aware: tenant-only fields are gated; property_type label varies.
                try {
                    var _wireEl = document.querySelector('[wire\\:id]');
                    if (_wireEl && typeof Livewire !== 'undefined') {
                        var _comp = Livewire.find(_wireEl.getAttribute('wire:id'));
                        if (_comp && _comp.get) {
                            var _svcType = formContainer.getAttribute('data-service-type');
                            var _curUTgi = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : 'tenant';
                            if (_svcType === 'full_service') {
                                // property_type: required for all roles; label is role-aware.
                                // Resolve the actual DOM element so tenantNavigateToItem()
                                // can switch to the correct tab when it is empty.
                                var _ptValGi = _comp.get('property_type');
                                if (!_ptValGi || _ptValGi === '') {
                                    var _ptElGi = document.getElementById('property_type')
                                        || document.querySelector('[wire\\:model="property_type"]')
                                        || document.querySelector('[wire\\:model\\.defer="property_type"]');
                                    var _ptTabGi = _ptElGi ? _ptElGi.closest('.tab-pane') : null;
                                    var _ptLabelGi = (_curUTgi === 'landlord' || _curUTgi === 'seller')
                                        ? 'Property Type'
                                        : (TENANT_FIELD_LABELS['property_type'] || 'Acceptable Property Type');
                                    if (!items.some(function(i) { return i.key === 'property_type'; })) {
                                        items.push({ field: _ptElGi || document.body, tab: _ptTabGi, fieldName: _ptLabelGi, key: 'property_type' });
                                    }
                                }

                                if (_curUTgi === 'tenant') {
                                    // lease_for and leasing_spaces_tenant are tenant-only fields.
                                    var _lwTenantReqs = [
                                        { prop: 'lease_for', label: TENANT_FIELD_LABELS['lease_for'] || 'Offered Lease Term', key: 'lease_for', isArray: true, domSel: '.lease_for' },
                                        { prop: 'leasing_spaces_tenant', label: TENANT_FIELD_LABELS['leasing_spaces_tenant'] || 'Leasing Space', key: 'leasing_spaces_tenant', isArray: true, domSel: '#leasing_spaces_tenant' },
                                    ];
                                    _lwTenantReqs.forEach(function(chk) {
                                        var val = _comp.get(chk.prop);
                                        var isEmpty2;
                                        if (typeof val === 'string') { try { val = JSON.parse(val); } catch(ex) {} }
                                        isEmpty2 = !val || (Array.isArray(val) && val.length === 0) || val === '' || val === '[]';
                                        if (isEmpty2 && chk.domSel) {
                                            var $domEl2 = $(chk.domSel);
                                            if ($domEl2.length) {
                                                var domVal2 = $domEl2.val();
                                                if (domVal2 && ((Array.isArray(domVal2) && domVal2.length > 0) || (typeof domVal2 === 'string' && domVal2 !== ''))) isEmpty2 = false;
                                            }
                                        }
                                        if (isEmpty2) {
                                            items.push({ field: document.body, tab: null, fieldName: chk.label, key: chk.key });
                                        }
                                    });

                                    // Bedrooms Livewire fallback (tenant only)
                                    var _ptVal2 = _comp.get('property_type');
                                    if (_ptVal2 === 'Residential Property') {
                                        var _bedroomsVal2 = _comp.get('bedrooms');
                                        if (!_bedroomsVal2 || _bedroomsVal2 === '') {
                                            if (!items.some(function(i) { return i.key === 'bedrooms'; })) {
                                                items.push({ field: document.body, tab: null, fieldName: TENANT_FIELD_LABELS['bedrooms'] || 'Minimum Bedrooms Needed', key: 'bedrooms' });
                                            }
                                        }
                                    }
                                } else if (_curUTgi === 'landlord') {
                                    // desired_lease_length is a landlord-only Select2 multi-select (wire:ignore).
                                    // It syncs to Livewire via debouncedSet; check Livewire state directly.
                                    var _dllValGi = _comp.get('desired_lease_length');
                                    if (typeof _dllValGi === 'string') { try { _dllValGi = JSON.parse(_dllValGi); } catch(ex3) {} }
                                    var _dllEmptyGi = !_dllValGi || (Array.isArray(_dllValGi) && _dllValGi.length === 0) || _dllValGi === '' || _dllValGi === '[]';
                                    if (_dllEmptyGi) {
                                        var $dllDomGi = $('.lease_term_options');
                                        if ($dllDomGi.length) {
                                            var _dllDomValGi = $dllDomGi.val();
                                            if (_dllDomValGi && Array.isArray(_dllDomValGi) && _dllDomValGi.length > 0) _dllEmptyGi = false;
                                        }
                                    }
                                    if (_dllEmptyGi && !items.some(function(i) { return i.key === 'desired_lease_length'; })) {
                                        var _dllElGi = document.querySelector('.lease_term_options');
                                        var _dllTabGi = _dllElGi ? _dllElGi.closest('.tab-pane') : null;
                                        items.push({ field: _dllElGi || document.body, tab: _dllTabGi, fieldName: TENANT_FIELD_LABELS['desired_lease_length'] || 'Desired Lease Term', key: 'desired_lease_length' });
                                    }
                                    // leasing_spaces: landlord-only native select — prefer Livewire state;
                                    // fall back to DOM value for rapid-change + immediate-submit scenarios.
                                    // (required attr removed from DOM to prevent pre-sync false positives)
                                    var _lsValGi = _comp.get('leasing_spaces');
                                    var _lsEmptyGi = !_lsValGi || _lsValGi === '';
                                    if (_lsEmptyGi) {
                                        var $lsDomGi = document.querySelector('[wire\\:model="leasing_spaces"]');
                                        if ($lsDomGi && $lsDomGi.value && $lsDomGi.value !== '') _lsEmptyGi = false;
                                    }
                                    if (_lsEmptyGi && !items.some(function(i) { return i.key === 'leasing_spaces'; })) {
                                        var _lsElGi = document.querySelector('[wire\\:model="leasing_spaces"]');
                                        var _lsTabGi = _lsElGi ? _lsElGi.closest('.tab-pane') : null;
                                        items.push({ field: _lsElGi || document.body, tab: _lsTabGi, fieldName: TENANT_FIELD_LABELS['leasing_spaces'] || 'Leasing Space', key: 'leasing_spaces' });
                                    }
                                } else if (_curUTgi === 'buyer') {
                                    // Buyer — property_items: Select2 multi-select (wire:ignore), required when property_type is selected
                                    var _ptValBuyer = _comp.get('property_type');
                                    if (_ptValBuyer && _ptValBuyer !== '') {
                                        var _piValBuyer = _comp.get('property_items');
                                        if (typeof _piValBuyer === 'string') { try { _piValBuyer = JSON.parse(_piValBuyer); } catch(exB1) {} }
                                        var _piEmptyBuyer = !_piValBuyer || (Array.isArray(_piValBuyer) && _piValBuyer.length === 0) || _piValBuyer === '[]';
                                        if (_piEmptyBuyer) {
                                            var $piDomBuyer = $('#property_items');
                                            if ($piDomBuyer.length) {
                                                var _piDomValBuyer = $piDomBuyer.val();
                                                if (_piDomValBuyer && Array.isArray(_piDomValBuyer) && _piDomValBuyer.length > 0) _piEmptyBuyer = false;
                                            }
                                        }
                                        if (_piEmptyBuyer && !items.some(function(i) { return i.key === 'property_items'; })) {
                                            var _piElBuyer = document.getElementById('property_items');
                                            var _piTabBuyer = _piElBuyer ? _piElBuyer.closest('.tab-pane') : null;
                                            items.push({ field: _piElBuyer || document.body, tab: _piTabBuyer, fieldName: TENANT_FIELD_LABELS['property_items'] || 'Acceptable Property Style', key: 'property_items' });
                                        }
                                    }

                                    // Buyer — offered_financing: Select2 multi-select (wire:ignore), always required
                                    var _ofValBuyer = _comp.get('offered_financing');
                                    if (typeof _ofValBuyer === 'string') { try { _ofValBuyer = JSON.parse(_ofValBuyer); } catch(exB2) {} }
                                    var _ofEmptyBuyer = !_ofValBuyer || (Array.isArray(_ofValBuyer) && _ofValBuyer.length === 0) || _ofValBuyer === '' || _ofValBuyer === '[]';
                                    if (_ofEmptyBuyer) {
                                        var $ofDomBuyer = $('#offered_financing');
                                        if ($ofDomBuyer.length) {
                                            var _ofDomValBuyer = $ofDomBuyer.val();
                                            if (_ofDomValBuyer && Array.isArray(_ofDomValBuyer) && _ofDomValBuyer.length > 0) _ofEmptyBuyer = false;
                                        }
                                    }
                                    if (_ofEmptyBuyer && !items.some(function(i) { return i.key === 'offered_financing'; })) {
                                        var _ofElBuyer = document.getElementById('offered_financing');
                                        var _ofTabBuyer = _ofElBuyer ? _ofElBuyer.closest('.tab-pane') : null;
                                        items.push({ field: _ofElBuyer || document.body, tab: _ofTabBuyer, fieldName: TENANT_FIELD_LABELS['offered_financing'] || 'Offered Financing/Currency', key: 'offered_financing' });
                                    }

                                    // Buyer — bedrooms: required for Residential only (Livewire check — conditional render)
                                    var _ptBuyer2 = _ptValBuyer || _comp.get('property_type');
                                    if (_ptBuyer2 === 'Residential') {
                                        var _bedroomsValB = _comp.get('bedrooms');
                                        if ((!_bedroomsValB || _bedroomsValB === '') && !items.some(function(i) { return i.key === 'bedrooms'; })) {
                                            var _bedroomsElB = document.getElementById('bedrooms');
                                            var _bedroomsTabB = _bedroomsElB ? _bedroomsElB.closest('.tab-pane') : null;
                                            items.push({ field: _bedroomsElB || document.body, tab: _bedroomsTabB, fieldName: TENANT_FIELD_LABELS['bedrooms'] || 'Minimum Bedrooms Needed', key: 'bedrooms' });
                                        }
                                    }

                                    // Buyer — bathrooms: required for Residential, Commercial, Business
                                    if (_ptBuyer2 === 'Residential' || _ptBuyer2 === 'Commercial' || _ptBuyer2 === 'Business') {
                                        var _bathroomsValB = _comp.get('bathrooms');
                                        if ((!_bathroomsValB || _bathroomsValB === '') && !items.some(function(i) { return i.key === 'bathrooms'; })) {
                                            var _bathroomsElB = document.getElementById('bathrooms');
                                            var _bathroomsTabB = _bathroomsElB ? _bathroomsElB.closest('.tab-pane') : null;
                                            items.push({ field: _bathroomsElB || document.body, tab: _bathroomsTabB, fieldName: TENANT_FIELD_LABELS['bathrooms'] || 'Minimum Bathrooms Needed', key: 'bathrooms' });
                                        }
                                    }

                                    // Buyer — real_estate_purchase: required for Business only
                                    if (_ptBuyer2 === 'Business') {
                                        var _repValB = _comp.get('real_estate_purchase');
                                        if ((!_repValB || _repValB === '') && !items.some(function(i) { return i.key === 'real_estate_purchase'; })) {
                                            var _repElB = document.querySelector('[wire\\:model="real_estate_purchase"]');
                                            var _repTabB = _repElB ? _repElB.closest('.tab-pane') : null;
                                            items.push({ field: _repElB || document.body, tab: _repTabB, fieldName: TENANT_FIELD_LABELS['real_estate_purchase'] || 'Business & Real Estate Purchase Requirements', key: 'real_estate_purchase' });
                                        }
                                    }

                                } else if (_curUTgi === 'seller') {
                                    // Seller: sale_provision is a Select2 multi-select (wire:ignore) — check Livewire state.
                                    var _spValSeller = _comp.get('sale_provision');
                                    if (typeof _spValSeller === 'string') { try { _spValSeller = JSON.parse(_spValSeller); } catch(exS1) {} }
                                    var _spEmptySeller = !_spValSeller || (Array.isArray(_spValSeller) && _spValSeller.length === 0) || _spValSeller === '' || _spValSeller === '[]';
                                    if (_spEmptySeller) {
                                        var $spDomSeller = $('#sale_provision');
                                        if ($spDomSeller.length) {
                                            var _spDomValSeller = $spDomSeller.val();
                                            if (_spDomValSeller && Array.isArray(_spDomValSeller) && _spDomValSeller.length > 0) _spEmptySeller = false;
                                        }
                                    }
                                    if (_spEmptySeller && !items.some(function(i) { return i.key === 'sale_provision'; })) {
                                        var _spElSeller = document.getElementById('sale_provision');
                                        var _spTabSeller = _spElSeller ? _spElSeller.closest('.tab-pane') : null;
                                        items.push({ field: _spElSeller || document.body, tab: _spTabSeller, fieldName: TENANT_FIELD_LABELS['sale_provision'] || 'Special Sale Provision', key: 'sale_provision' });
                                    }

                                    // Seller: offered_financing is a Select2 multi-select (wire:ignore) — check Livewire state.
                                    var _ofValSeller = _comp.get('offered_financing');
                                    if (typeof _ofValSeller === 'string') { try { _ofValSeller = JSON.parse(_ofValSeller); } catch(exS2) {} }
                                    var _ofEmptySeller = !_ofValSeller || (Array.isArray(_ofValSeller) && _ofValSeller.length === 0) || _ofValSeller === '' || _ofValSeller === '[]';
                                    if (_ofEmptySeller) {
                                        var $ofDomSeller = $('#offered_financing');
                                        if ($ofDomSeller.length) {
                                            var _ofDomValSeller = $ofDomSeller.val();
                                            if (_ofDomValSeller && Array.isArray(_ofDomValSeller) && _ofDomValSeller.length > 0) _ofEmptySeller = false;
                                        }
                                    }
                                    if (_ofEmptySeller && !items.some(function(i) { return i.key === 'offered_financing'; })) {
                                        var _ofElSeller = document.getElementById('offered_financing');
                                        var _ofTabSeller = _ofElSeller ? _ofElSeller.closest('.tab-pane') : null;
                                        items.push({ field: _ofElSeller || document.body, tab: _ofTabSeller, fieldName: 'Offered Financing/Currency', key: 'offered_financing' });
                                    }

                                    // Seller-info fields: labels have *, but inputs lack required attr.
                                    // Hybrid check: prefer DOM value (real-time) then Livewire state.
                                    // phone_number uses wire:model.defer so Livewire state only updates on submit.
                                    var _sellerInfoFields = [
                                        { prop: 'first_name',     label: 'First Name',                domSel: '[wire\\:model="first_name"]' },
                                        { prop: 'last_name',      label: 'Last Name',                 domSel: '[wire\\:model="last_name"]' },
                                        { prop: 'phone_number',   label: 'Phone Number',              domSel: '#seller_phone_number' },
                                        { prop: 'email',          label: 'Email Address',             domSel: '[wire\\:model="email"]' },
                                    ];
                                    _sellerInfoFields.forEach(function(chkS) {
                                        var _sEl = document.querySelector(chkS.domSel);
                                        var _sDomVal = _sEl ? _sEl.value : null;
                                        var _sEmpty = !_sDomVal || _sDomVal.trim() === '';
                                        if (_sEmpty) {
                                            var _sLwVal = _comp.get(chkS.prop);
                                            if (_sLwVal && typeof _sLwVal === 'string' && _sLwVal.trim() !== '') _sEmpty = false;
                                        }
                                        if (_sEmpty && !items.some(function(i) { return i.key === chkS.prop; })) {
                                            var _sTab = _sEl ? _sEl.closest('.tab-pane') : null;
                                            items.push({ field: _sEl || document.body, tab: _sTab, fieldName: chkS.label, key: chkS.prop });
                                        }
                                    });
                                }
                            }
                        }
                    }
                } catch(ex2) {}

                // Deduplicate by key
                var seen2 = new Set();
                var deduped2 = [];
                items.forEach(function(item) {
                    var dk = item.key || item.fieldName;
                    if (!seen2.has(dk)) { seen2.add(dk); deduped2.push(item); }
                });
                return deduped2;
            }

            // Navigates the user to a missing-field item: switches Bootstrap tab
            // client-side AND syncs the server-side $activeTab so morphdom does NOT
            // snap the tab back on the next Livewire round-trip.
            function tenantNavigateToItem(item) {
                if (item && item.tab) {
                    var _tabId = item.tab.id;
                    var _tabTrigger = document.querySelector('[data-bs-target="#' + _tabId + '"], [href="#' + _tabId + '"]');
                    if (_tabTrigger) {
                        new bootstrap.Tab(_tabTrigger).show();
                        // Sync server $activeTab — parse the numeric index from wire:click="setActiveTab(N)"
                        var _wc = _tabTrigger.getAttribute('wire:click') || '';
                        var _m = _wc.match(/setActiveTab\((\d+)\)/);
                        if (_m) {
                            try { @this.call('setActiveTab', parseInt(_m[1])); } catch(ex3) {}
                        }
                    }
                }
                setTimeout(function() {
                    if (item && item.field && item.field !== document.body) {
                        item.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (typeof item.field.focus === 'function' && item.field.tagName !== 'DIV') {
                            item.field.focus();
                        }
                        item.field.classList.add('is-invalid');
                    }
                    var _banner2 = document.getElementById('submit-error-banner');
                    if (_banner2) {
                        // Re-assert visibility in case morphdom fired before this timeout
                        _banner2.classList.remove('d-none');
                        _banner2.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 350);
            }

            // Called after each Livewire update when in correction mode.
            // If a previously-missing field is now filled, advance to the next one.
            // When all required fields are complete, exit correction mode silently.
            function tenantAdvanceCorrection() {
                if (!_tenantCorrectionMode) return;
                var freshMissing = tenantGetInvalidItems();

                // Refresh the banner list to reflect current state
                var _errorList2 = document.getElementById('submit-error-list');
                var _banner3   = document.getElementById('submit-error-banner');
                if (_errorList2) {
                    _errorList2.innerHTML = '';
                    freshMissing.forEach(function(item) {
                        var li = document.createElement('li');
                        li.textContent = item.fieldName;
                        _errorList2.appendChild(li);
                    });
                }

                if (freshMissing.length === 0) {
                    // All required fields are now complete.  Exit correction mode,
                    // hide the banner, and navigate back to the submit tab so the
                    // user can submit without having to find it manually.
                    _tenantCorrectionMode = false;
                    _tenantMissingItems = [];
                    if (_banner3) _banner3.classList.add('d-none');
                    // Navigate to the info tab — slug varies by role on the full_service path.
                    var _infoRole = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : '';
                    var _infoSlug = _infoRole === 'landlord' ? '#landlord-information'
                        : _infoRole === 'seller'   ? '#seller-information'
                        : _infoRole === 'buyer'    ? '#buyer-information'
                        : _infoRole === 'tenant'   ? '#tenant-information'
                        : '#information';
                    var _submitTabTrigger = document.querySelector('[data-bs-target="' + _infoSlug + '"]')
                        || document.querySelector('[data-bs-target="#information"]');
                    if (_submitTabTrigger) {
                        new bootstrap.Tab(_submitTabTrigger).show();
                        // Extract the tab index from wire:click instead of hard-coding it.
                        var _infoWc = _submitTabTrigger.getAttribute('wire:click') || '';
                        var _infoM = _infoWc.match(/setActiveTab\((\d+)\)/);
                        if (_infoM) {
                            try { @this.call('setActiveTab', parseInt(_infoM[1])); } catch(ex4) {}
                        }
                    }
                    return;
                }

                // Re-show banner — Livewire DOM-diff re-adds d-none on every setActiveTab
                // round-trip, so we must explicitly remove it after each re-render.
                if (_banner3) _banner3.classList.remove('d-none');

                // Still have missing fields — navigate to the first remaining one
                _tenantMissingItems = freshMissing;
                tenantNavigateToItem(freshMissing[0]);
            }
            // ---- END TENANT CORRECTION MODE -------------------------------------------

            document.addEventListener('submit', function(e) {
                if (!e.target || e.target.id !== 'create-auction-form') return;
                const banner = document.getElementById('submit-error-banner');
                const errorList = document.getElementById('submit-error-list');
                banner.classList.add('d-none');
                errorList.innerHTML = '';

                const requiredFields = getAllRequiredFields();
                let invalidItems = [];

                const _submitUserType = typeof CURRENT_USER_TYPE !== 'undefined' ? CURRENT_USER_TYPE : '';

                requiredFields.forEach(field => {
                    if (_submitUserType === 'tenant' || _submitUserType === 'landlord' || _submitUserType === 'seller' || _submitUserType === 'buyer') {
                        // Tenant/Landlord/Seller/Buyer flow: check required fields across ALL tabs.
                        // Only skip fields that are hidden by conditional rendering within the tab.
                        if (isTenantFieldHiddenWithinTab(field)) return;

                        // FIX-03: Skip disabled fields — a disabled field (e.g. Property Style
                        // when no property type is selected yet) must not block guided correction
                        // or submit validation. Consistent with isFieldVisible() and tenantGetInvalidItems().
                        if (field.disabled) return;

                        // Skip Select2-managed multi-selects — handled separately via Livewire check.
                        if (field.tagName === 'SELECT' && field.multiple &&
                            field.classList.contains('select2-hidden-accessible')) return;

                        // Direct value check (no tab-visibility gate).
                        let isEmpty = false;
                        if (field.type === 'checkbox' || field.type === 'radio') {
                            isEmpty = !field.checked;
                        } else if (field.type === 'select-one' || field.type === 'select-multiple') {
                            isEmpty = field.value === '' || field.value === null || field.value === undefined;
                        } else {
                            isEmpty = !field.value || field.value.trim() === '';
                        }

                        if (isEmpty) {
                            const _fKey = resolveTenantFieldKey(field);
                            invalidItems.push({ field, tab: field.closest('.tab-pane'), fieldName: resolveTenantFieldLabel(field), key: _fKey });
                        }
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
                if (countiesContainer && CURRENT_USER_TYPE_LOCAL !== 'landlord') {
                    const countyBadges = countiesContainer.querySelectorAll('.badge');
                    if (!countyBadges || countyBadges.length === 0) {
                        const tab = countiesContainer.closest('.tab-pane');
                        invalidItems.push({ field: countiesContainer, tab, fieldName: TENANT_FIELD_LABELS['counties'] || 'Acceptable Counties', key: 'counties' });
                    }
                }

                try {
                    var wireEl = document.querySelector('[wire\\:id]');
                    if (wireEl && typeof Livewire !== 'undefined') {
                        var comp = Livewire.find(wireEl.getAttribute('wire:id'));
                        if (comp && comp.get) {
                            var svcType = formContainer.getAttribute('data-service-type');
                            var curUT = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : 'tenant';
                            if (svcType === 'full_service') {
                                // property_type: required for all roles; label and tab are role-aware.
                                // Find the actual DOM element so guided correction can navigate to its tab.
                                var _ptValSub = comp.get('property_type');
                                if (!_ptValSub || _ptValSub === '') {
                                    if (!invalidItems.some(function(i) { return i.key === 'property_type'; })) {
                                        var _ptElSub = document.getElementById('property_type')
                                            || document.querySelector('[wire\\:model="property_type"]')
                                            || document.querySelector('[wire\\:model\\.defer="property_type"]');
                                        var _ptTabSub = _ptElSub ? _ptElSub.closest('.tab-pane') : null;
                                        var _ptLabelSub = (curUT === 'landlord' || curUT === 'seller')
                                            ? 'Property Type'
                                            : (TENANT_FIELD_LABELS['property_type'] || 'Acceptable Property Type');
                                        invalidItems.push({ field: _ptElSub || document.body, tab: _ptTabSub, fieldName: _ptLabelSub, key: 'property_type' });
                                    }
                                }

                                if (curUT === 'tenant') {
                                    var lwReqs = [
                                        { prop: 'lease_for', label: TENANT_FIELD_LABELS['lease_for'] || 'Offered Lease Term', isArray: true, domSel: '.lease_for', key: 'lease_for' },
                                        { prop: 'leasing_spaces_tenant', label: TENANT_FIELD_LABELS['leasing_spaces_tenant'] || 'Leasing Space', isArray: true, domSel: '#leasing_spaces_tenant', key: 'leasing_spaces_tenant' },
                                    ];
                                    lwReqs.forEach(function(chk) {
                                        var val = comp.get(chk.prop);
                                        var isEmpty;
                                        if (typeof val === 'string') {
                                            try { val = JSON.parse(val); } catch(e2) {}
                                        }
                                        isEmpty = !val || (Array.isArray(val) && val.length === 0) || val === '' || val === '[]';
                                        if (isEmpty && chk.domSel) {
                                            var $domEl = $(chk.domSel);
                                            if ($domEl.length) {
                                                var domVal = $domEl.val();
                                                if (domVal && ((Array.isArray(domVal) && domVal.length > 0) || (typeof domVal === 'string' && domVal !== ''))) {
                                                    isEmpty = false;
                                                }
                                            }
                                        }
                                        if (isEmpty) {
                                            invalidItems.push({ field: document.body, tab: null, fieldName: chk.label, key: chk.key });
                                        }
                                    });
                                }

                                // Tenant-specific: validate bedrooms via Livewire state.
                                // The bedrooms select only renders when property_type === 'Residential Property'
                                // (PHP conditional), so it may not be in the DOM due to Livewire re-render
                                // timing.  Reading the Livewire state directly is more reliable.
                                if (curUT === 'tenant') {
                                    var _ptVal = comp.get('property_type');
                                    if (_ptVal === 'Residential Property') {
                                        var _bedroomsVal = comp.get('bedrooms');
                                        var _bedroomsEmpty = !_bedroomsVal || _bedroomsVal === '';
                                        if (_bedroomsEmpty) {
                                            // Only add if not already caught by the DOM scan (dedupe by key).
                                            var _alreadyHasBedrooms = invalidItems.some(function(i) {
                                                return i.key === 'bedrooms';
                                            });
                                            if (!_alreadyHasBedrooms) {
                                                invalidItems.push({ field: document.body, tab: null, fieldName: TENANT_FIELD_LABELS['bedrooms'] || 'Minimum Bedrooms Needed', key: 'bedrooms' });
                                            }
                                        }
                                    }
                                } else if (curUT === 'landlord') {
                                    // desired_lease_length is a landlord-only Select2 multi-select (wire:ignore).
                                    // It syncs to Livewire via debouncedSet; check Livewire state directly.
                                    var _dllVal = comp.get('desired_lease_length');
                                    if (typeof _dllVal === 'string') { try { _dllVal = JSON.parse(_dllVal); } catch(e2) {} }
                                    var _dllEmpty = !_dllVal || (Array.isArray(_dllVal) && _dllVal.length === 0) || _dllVal === '' || _dllVal === '[]';
                                    if (_dllEmpty) {
                                        var $dllDom = $('.lease_term_options');
                                        if ($dllDom.length) {
                                            var _dllDomVal = $dllDom.val();
                                            if (_dllDomVal && Array.isArray(_dllDomVal) && _dllDomVal.length > 0) _dllEmpty = false;
                                        }
                                    }
                                    if (_dllEmpty && !invalidItems.some(function(i) { return i.key === 'desired_lease_length'; })) {
                                        var _dllEl = document.querySelector('.lease_term_options');
                                        var _dllTab = _dllEl ? _dllEl.closest('.tab-pane') : null;
                                        invalidItems.push({ field: _dllEl || document.body, tab: _dllTab, fieldName: TENANT_FIELD_LABELS['desired_lease_length'] || 'Desired Lease Term', key: 'desired_lease_length' });
                                    }
                                    // leasing_spaces: landlord-only native select — prefer Livewire state;
                                    // fall back to DOM value for rapid-change + immediate-submit scenarios.
                                    // (required attr removed from DOM to prevent pre-sync false positives)
                                    var _lsVal = comp.get('leasing_spaces');
                                    var _lsEmpty = !_lsVal || _lsVal === '';
                                    if (_lsEmpty) {
                                        var _lsDomEl = document.querySelector('[wire\\:model="leasing_spaces"]');
                                        if (_lsDomEl && _lsDomEl.value && _lsDomEl.value !== '') _lsEmpty = false;
                                    }
                                    if (_lsEmpty && !invalidItems.some(function(i) { return i.key === 'leasing_spaces'; })) {
                                        var _lsEl = document.querySelector('[wire\\:model="leasing_spaces"]');
                                        var _lsTab = _lsEl ? _lsEl.closest('.tab-pane') : null;
                                        invalidItems.push({ field: _lsEl || document.body, tab: _lsTab, fieldName: TENANT_FIELD_LABELS['leasing_spaces'] || 'Leasing Space', key: 'leasing_spaces' });
                                    }
                                } else if (curUT === 'seller') {
                                    // Seller: sale_provision is a Select2 multi-select (wire:ignore) — check Livewire state.
                                    var _spValSub = comp.get('sale_provision');
                                    if (typeof _spValSub === 'string') { try { _spValSub = JSON.parse(_spValSub); } catch(eS1) {} }
                                    var _spEmptySub = !_spValSub || (Array.isArray(_spValSub) && _spValSub.length === 0) || _spValSub === '' || _spValSub === '[]';
                                    if (_spEmptySub) {
                                        var $spDomSub = $('#sale_provision');
                                        if ($spDomSub.length) {
                                            var _spDomValSub = $spDomSub.val();
                                            if (_spDomValSub && Array.isArray(_spDomValSub) && _spDomValSub.length > 0) _spEmptySub = false;
                                        }
                                    }
                                    if (_spEmptySub && !invalidItems.some(function(i) { return i.key === 'sale_provision'; })) {
                                        var _spElSub = document.getElementById('sale_provision');
                                        var _spTabSub = _spElSub ? _spElSub.closest('.tab-pane') : null;
                                        invalidItems.push({ field: _spElSub || document.body, tab: _spTabSub, fieldName: TENANT_FIELD_LABELS['sale_provision'] || 'Special Sale Provision', key: 'sale_provision' });
                                    }

                                    // Seller: offered_financing is a Select2 multi-select (wire:ignore) — check Livewire state.
                                    var _ofValSub = comp.get('offered_financing');
                                    if (typeof _ofValSub === 'string') { try { _ofValSub = JSON.parse(_ofValSub); } catch(eS2) {} }
                                    var _ofEmptySub = !_ofValSub || (Array.isArray(_ofValSub) && _ofValSub.length === 0) || _ofValSub === '' || _ofValSub === '[]';
                                    if (_ofEmptySub) {
                                        var $ofDomSub = $('#offered_financing');
                                        if ($ofDomSub.length) {
                                            var _ofDomValSub = $ofDomSub.val();
                                            if (_ofDomValSub && Array.isArray(_ofDomValSub) && _ofDomValSub.length > 0) _ofEmptySub = false;
                                        }
                                    }
                                    if (_ofEmptySub && !invalidItems.some(function(i) { return i.key === 'offered_financing'; })) {
                                        var _ofElSub = document.getElementById('offered_financing');
                                        var _ofTabSub = _ofElSub ? _ofElSub.closest('.tab-pane') : null;
                                        invalidItems.push({ field: _ofElSub || document.body, tab: _ofTabSub, fieldName: 'Offered Financing/Currency', key: 'offered_financing' });
                                    }

                                    // Seller-info fields: labels have *, but inputs lack required attr.
                                    // Hybrid check: prefer DOM value (real-time) then Livewire state.
                                    // phone_number uses wire:model.defer so Livewire state only updates on submit.
                                    var _sellerInfoFieldsSub = [
                                        { prop: 'first_name',     label: 'First Name',               domSel: '[wire\\:model="first_name"]' },
                                        { prop: 'last_name',      label: 'Last Name',                domSel: '[wire\\:model="last_name"]' },
                                        { prop: 'phone_number',   label: 'Phone Number',             domSel: '#seller_phone_number' },
                                        { prop: 'email',          label: 'Email Address',            domSel: '[wire\\:model="email"]' },
                                    ];
                                    _sellerInfoFieldsSub.forEach(function(chkSub) {
                                        var _sElSub = document.querySelector(chkSub.domSel);
                                        var _sDomValSub = _sElSub ? _sElSub.value : null;
                                        var _sEmptySub = !_sDomValSub || _sDomValSub.trim() === '';
                                        if (_sEmptySub) {
                                            var _sLwValSub = comp.get(chkSub.prop);
                                            if (_sLwValSub && typeof _sLwValSub === 'string' && _sLwValSub.trim() !== '') _sEmptySub = false;
                                        }
                                        if (_sEmptySub && !invalidItems.some(function(i) { return i.key === chkSub.prop; })) {
                                            var _sTabSub = _sElSub ? _sElSub.closest('.tab-pane') : null;
                                            invalidItems.push({ field: _sElSub || document.body, tab: _sTabSub, fieldName: chkSub.label, key: chkSub.prop });
                                        }
                                    });
                                } else if (curUT === 'buyer') {
                                    // Buyer — offered_financing: Select2 multi-select (wire:ignore)
                                    var _ofValBSub = comp.get('offered_financing');
                                    if (typeof _ofValBSub === 'string') { try { _ofValBSub = JSON.parse(_ofValBSub); } catch(eBF) {} }
                                    var _ofEmptyBSub = !_ofValBSub || (Array.isArray(_ofValBSub) && _ofValBSub.length === 0) || _ofValBSub === '' || _ofValBSub === '[]';
                                    if (_ofEmptyBSub) {
                                        var $ofDomBSub = $('#offered_financing');
                                        if ($ofDomBSub.length) {
                                            var _ofDomBSub = $ofDomBSub.val();
                                            if (_ofDomBSub && Array.isArray(_ofDomBSub) && _ofDomBSub.length > 0) _ofEmptyBSub = false;
                                        }
                                    }
                                    if (_ofEmptyBSub && !invalidItems.some(function(i) { return i.key === 'offered_financing'; })) {
                                        var _ofElBSub = document.getElementById('offered_financing');
                                        var _ofTabBSub = _ofElBSub ? _ofElBSub.closest('.tab-pane') : null;
                                        invalidItems.push({ field: _ofElBSub || document.body, tab: _ofTabBSub, fieldName: 'Offered Financing/Currency', key: 'offered_financing' });
                                    }

                                    // Buyer — property_items: Select2 multi (required when property_type selected)
                                    var _ptValBSub = comp.get('property_type');
                                    if (_ptValBSub && _ptValBSub !== '') {
                                        var _piValBSub = comp.get('property_items');
                                        if (typeof _piValBSub === 'string') { try { _piValBSub = JSON.parse(_piValBSub); } catch(ePIS) {} }
                                        var _piEmptyBSub = !_piValBSub || (Array.isArray(_piValBSub) && _piValBSub.length === 0) || _piValBSub === '[]';
                                        if (_piEmptyBSub) {
                                            var $piDomBSub = $('#property_items');
                                            if ($piDomBSub.length) {
                                                var _piDomBSub = $piDomBSub.val();
                                                if (_piDomBSub && Array.isArray(_piDomBSub) && _piDomBSub.length > 0) _piEmptyBSub = false;
                                            }
                                        }
                                        if (_piEmptyBSub && !invalidItems.some(function(i) { return i.key === 'property_items'; })) {
                                            var _piElBSub = document.getElementById('property_items');
                                            var _piTabBSub = _piElBSub ? _piElBSub.closest('.tab-pane') : null;
                                            invalidItems.push({ field: _piElBSub || document.body, tab: _piTabBSub, fieldName: TENANT_FIELD_LABELS['property_items'] || 'Acceptable Property Style', key: 'property_items' });
                                        }
                                    }

                                    // Buyer — bedrooms (Residential), bathrooms (Residential/Commercial/Business),
                                    // pets (Residential/Income), real_estate_purchase (Business)
                                    if (_ptValBSub === 'Residential') {
                                        var _bedroomsVBS = comp.get('bedrooms');
                                        if ((!_bedroomsVBS || _bedroomsVBS === '') && !invalidItems.some(function(i) { return i.key === 'bedrooms'; })) {
                                            var _bedroomsElBS = document.getElementById('bedrooms');
                                            invalidItems.push({ field: _bedroomsElBS || document.body, tab: _bedroomsElBS ? _bedroomsElBS.closest('.tab-pane') : null, fieldName: TENANT_FIELD_LABELS['bedrooms'] || 'Minimum Bedrooms Needed', key: 'bedrooms' });
                                        }
                                    }
                                    if (_ptValBSub === 'Residential' || _ptValBSub === 'Commercial' || _ptValBSub === 'Business') {
                                        var _bathroomsVBS = comp.get('bathrooms');
                                        if ((!_bathroomsVBS || _bathroomsVBS === '') && !invalidItems.some(function(i) { return i.key === 'bathrooms'; })) {
                                            var _bathroomsElBS = document.getElementById('bathrooms');
                                            invalidItems.push({ field: _bathroomsElBS || document.body, tab: _bathroomsElBS ? _bathroomsElBS.closest('.tab-pane') : null, fieldName: TENANT_FIELD_LABELS['bathrooms'] || 'Minimum Bathrooms Needed', key: 'bathrooms' });
                                        }
                                    }
                                    if (_ptValBSub === 'Residential' || _ptValBSub === 'Income') {
                                        var _petsVBS = comp.get('pets');
                                        if ((!_petsVBS || _petsVBS === '') && !invalidItems.some(function(i) { return i.key === 'pets'; })) {
                                            var _petsElBS = document.getElementById('pets') || document.getElementById('pets_income');
                                            invalidItems.push({ field: _petsElBS || document.body, tab: _petsElBS ? _petsElBS.closest('.tab-pane') : null, fieldName: TENANT_FIELD_LABELS['pets'] || 'Pets', key: 'pets' });
                                        }
                                    }
                                    if (_ptValBSub === 'Business') {
                                        var _repVBS = comp.get('real_estate_purchase');
                                        if ((!_repVBS || _repVBS === '') && !invalidItems.some(function(i) { return i.key === 'real_estate_purchase'; })) {
                                            var _repElBS = document.querySelector('[wire\\:model="real_estate_purchase"]');
                                            invalidItems.push({ field: _repElBS || document.body, tab: _repElBS ? _repElBS.closest('.tab-pane') : null, fieldName: TENANT_FIELD_LABELS['real_estate_purchase'] || 'Business & Real Estate Purchase Requirements', key: 'real_estate_purchase' });
                                        }
                                    }

                                }
                            }
                        }
                    }
                } catch(e) { }

                if (invalidItems.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Build deduplicated list and populate banner
                    const seen = new Set();
                    const deduplicatedItems = [];
                    invalidItems.forEach(item => {
                        const _dedupeKey = item.key || item.fieldName;
                        if (!seen.has(_dedupeKey)) {
                            seen.add(_dedupeKey);
                            deduplicatedItems.push(item);
                            const li = document.createElement('li');
                            li.textContent = item.fieldName;
                            errorList.appendChild(li);
                        }
                    });
                    banner.classList.remove('d-none');

                    if (_submitUserType === 'tenant' || _submitUserType === 'landlord' || _submitUserType === 'seller' || _submitUserType === 'buyer') {
                        // All roles: enter guided correction mode.
                        // tenantNavigateToItem() switches the Bootstrap tab AND syncs
                        // server-side $activeTab so Livewire morphdom cannot snap back.
                        _tenantCorrectionMode = true;
                        _tenantMissingItems = deduplicatedItems;
                        tenantNavigateToItem(deduplicatedItems[0]);
                    }

                    return false;
                }

                // All roles: always clear correction mode on a valid submit so the
                // message.processed hook never interferes with the store() action.
                if (_submitUserType === 'tenant' || _submitUserType === 'landlord' || _submitUserType === 'seller' || _submitUserType === 'buyer') {
                    _tenantCorrectionMode = false;
                    _tenantMissingItems = [];
                }
                syncAllSelect2BeforeSave();
                banner.classList.add('d-none');
            }, true);

            // Tenant correction mode: after each Livewire update check whether a
            // previously-missing field has been filled.  If so, advance to the next
            // still-missing field automatically (guided correction flow).
            // This runs ONLY when _tenantCorrectionMode is true, and ONLY if at least
            // one field that was missing before the Livewire update is now filled.
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', function() {
                    if (!_tenantCorrectionMode) return;
                    setTimeout(function() {
                        var _freshMissing = tenantGetInvalidItems();

                        // Always re-show the banner after every Livewire round-trip.
                        // morphdom re-adds d-none to the banner on every setActiveTab
                        // re-render, so we must remove it unconditionally here.
                        var _bannerPersist = document.getElementById('submit-error-banner');
                        if (_bannerPersist) {
                            _bannerPersist.classList.remove('d-none');
                        }

                        // Also keep the error list current so it reflects the latest
                        // state of missing fields as the user navigates around.
                        var _errorListPersist = document.getElementById('submit-error-list');
                        if (_errorListPersist) {
                            _errorListPersist.innerHTML = '';
                            _freshMissing.forEach(function(item) {
                                var li = document.createElement('li');
                                li.textContent = item.fieldName;
                                _errorListPersist.appendChild(li);
                            });
                        }

                        var _freshKeys = new Set(_freshMissing.map(function(i) { return i.key || i.fieldName; }));
                        var _someFixed = false;
                        _tenantMissingItems.forEach(function(i) {
                            if (!_freshKeys.has(i.key || i.fieldName)) _someFixed = true;
                        });
                        if (_someFixed) {
                            tenantAdvanceCorrection();
                        }
                    }, 450);
                });
            }
        }
    });
</script>
<script>
    @if (!$listingId)
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var draftEl = document.getElementById('draftModal');
            if (draftEl) {
                var draftModal = new bootstrap.Modal(draftEl);
                draftModal.show();
            }
        }, 150);
    });
    @endif

    var _draftModalPending = false;
    function _clearDraftModalPending() { _draftModalPending = false; }
    window.addEventListener('open-draft-modal', function() {
        var draftEl = document.getElementById('draftModal');
        if (!draftEl) return;
        if (draftEl.classList.contains('show')) return;
        if (_draftModalPending) return;
        _draftModalPending = true;
        var instance = bootstrap.Modal.getInstance(draftEl) || new bootstrap.Modal(draftEl);
        draftEl.addEventListener('hidden.bs.modal', _clearDraftModalPending, { once: true });
        draftEl.addEventListener('shown.bs.modal', _clearDraftModalPending, { once: true });
        setTimeout(_clearDraftModalPending, 2000);
        instance.show();
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
            <input type="text" class="form-control mb-2" placeholder="Enter additional services not listed above (e.g., School District Research, Commute Area Research, Furnished Rental Assistance)">
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

    function formatAllMoneyInputsOnLoad() {
        // Format flat-fee wire:key-prefixed inputs (lease_value, purchase_value, etc.)
        ['lease-value-input-flat', 'purchase-value-input-flat'].forEach(function(prefix) {
            document.querySelectorAll('[wire\\:key^="' + prefix + '"]').forEach(function(input) {
                if (input.value && typeof formatWithCommas === 'function') {
                    formatWithCommas(input);
                }
            });
        });
        // Format all remaining money inputs that use reformatNumber on blur
        document.querySelectorAll('input[onblur*="reformatNumber"]').forEach(function(input) {
            if (input.value && input.value.trim() !== '') {
                reformatNumber(input);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() { formatAllMoneyInputsOnLoad(); }, 100);
    });

    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:load', function() {
            formatAllMoneyInputsOnLoad();
            Livewire.hook('message.processed', function() {
                formatAllMoneyInputsOnLoad();
            });
        });
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

<script>
    $(document).ready(function() {
        initSelect2('#property_items', 'property_items', '.other_property_items_wrapper');
        initSelect2('#condition_prop_buyer', 'condition_prop_buyer', '.other_property_condition_wrapper');
        initSelect2('#sale_provision', 'sale_provision', '.sale_provision_other_wrapper');
        initSelect2('#offered_financing', 'offered_financing', '.other_financing_wrapper');
        if ($('#sale_provision').length) { applySellerProvisionVisibility(); applyBuyerProvisionVisibility(); }
        if ($('#offered_financing').length) { applySellerFinancingVisibility(); applyBuyerFinancingVisibility(); }
    });
</script>

@endpush
