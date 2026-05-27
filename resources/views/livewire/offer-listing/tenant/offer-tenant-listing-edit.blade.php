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
            font-weight: 600;
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
        ['name' => '½ Duplex', 'class' => 'residential-length'],
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

                <!-- DEBUG: EDIT MODE - LISTING ID: {{ $listingId ?? 'N/A' }} - COMPONENT: TenantAgentAuctionEdit (EDIT) -->

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

                    <div wire:ignore>
                        <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                            <strong>Please complete the required fields before submitting.</strong>
                            <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                        </div>
                    </div>

                    <form id="edit-auction-form" wire:submit.prevent="update" novalidate>
                        <!-- Tab Navigation -->
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

                                // Define rest tabs — tenant has its own dedicated branch
                                $firstRest =
                                    $user_type === 'buyer'
                                        ? 'Purchasing Terms'
                                        : ($user_type === 'seller'
                                            ? 'Sale Terms'
                                            : 'Leasing Terms');
                                if ($user_type === 'tenant') {
                                    $restTabs = [$firstRest, 'Pre-Screening', 'Description', 'Broker Compensation & Agency Agreement Terms'];
                                } else {
                                    $restTabs = [$firstRest, 'Services', 'Description', 'Broker Compensation & Agency Agreement Terms'];
                                    if ($user_type !== 'landlord' and $user_type !== 'buyer' and $user_type !== 'seller') {
                                        array_splice($restTabs, 1, 0, 'Pre-Screening');
                                    }
                                    if ($isAgentUser) {
                                        $restTabs[] = 'Referral & Cooperation Terms';
                                    }
                                }

                                $infoTabs = [
                                    'tenant' => 'Agent Credentials & Contact Info',
                                    'seller' => 'Seller Information',
                                    'buyer' => 'Buyer Information',
                                    'landlord' => 'Landlord Information',
                                ];
                                $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent';

                                if ($user_type === 'tenant') {
                                    $allTabs = array_merge($baseTabs, [$propertyTab], $restTabs, [
                                        $infoTabs[$user_type] ?? null,
                                        'AI Knowledge Base',
                                    ]);
                                } else {
                                    $allTabs = array_merge($baseTabs, [$propertyTab], $restTabs, [
                                        $infoTabs[$user_type] ?? null,
                                        'AI Knowledge Base',
                                    ]);
                                }

                                $infoTabIndex         = array_search($infoTabs[$user_type] ?? null, $allTabs);
                                $aiQuestionsTabIndex  = array_search('AI Knowledge Base', $allTabs);
                                $brokerCompTabIndex   = array_search('Broker Compensation & Agency Agreement Terms', $allTabs);
                                $descriptionTabIndex  = array_search('Description', $allTabs);
                                $servicesTabIndex     = array_search('Services', $allTabs);
                                $referralTabIndex     = array_search('Referral & Cooperation Terms', $allTabs);
                                $preScreeningTabIndex = array_search('Pre-Screening', $allTabs);

                                // Map tab labels to their actual pane IDs where the auto-slug doesn't match
                                $infoTabId = match($user_type) {
                                    'tenant'   => 'tenant-information',
                                    'seller'   => 'seller-information',
                                    'buyer'    => 'buyer-information',
                                    'landlord' => 'landlord-information',
                                    default    => 'tenant-information',
                                };
                                $tabPaneIdOverrides = [
                                    ($infoTabs[$user_type] ?? '') => $infoTabId,
                                    'Description'                 => 'additional-details',
                                ];
                            @endphp

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach ($allTabs as $index => $tab)
                                    @if ($tab)
                                        @php $tabSlug = $tabPaneIdOverrides[$tab] ?? $safeSlug($tab); @endphp
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
                                            Agent Credentials & Contact Info
                                        @elseif($user_type === 'seller')
                                            Seller Information
                                        @elseif($user_type === 'buyer')
                                            Buyer Information
                                        @elseif($user_type === 'landlord')
                                            Landlord Information
                                        @endif
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 5 ? 'active' : '' }}"
                                        wire:click="setActiveTab(5)"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === 5 ? 'true' : 'false' }}">
                                        AI Knowledge Base
                                    </button>
                                </li>
                            </ul>

                        @endif

                        <!-- Tab Content -->
                        <div class="tab-content" id="myTabContent">

                            <!-- Listing Details Tab -->
                            <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}" id="listing-details"
                                role="tabpanel" aria-labelledby="listing-details-tab">
                                @php $isEditMode = true; @endphp
                                @if ($user_type === 'tenant')
                                    @include('livewire.offer-listing.offer-tenant-tabs.commission-based.listing-details')
                                @elseif($user_type === 'seller')
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.listing-details')
                                @elseif($user_type === 'buyer')
                                    @include('livewire.offer-listing.offer-buyer-tabs.commission-based.listing-details')
                                @elseif($user_type === 'landlord')
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.listing-details')
                                @endif
                            </div>

                            @if ($service_type === 'full_service')
                                <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                                    id="{{ $propertyId }}" role="tabpanel"
                                    aria-labelledby="{{ $propertyId }}-tab">
                                    @switch($user_type)
                                        @case('tenant')
                                            @include('livewire.offer-listing.offer-tenant-tabs.commission-based.property-details')
                                        @break

                                        @case('seller')
                                            @include('livewire.offer-listing.offer-seller-tabs.commission-based.property-preferences')
                                        @break

                                        @case('buyer')
                                            @include('livewire.offer-listing.offer-buyer-tabs.commission-based.property-preferences')
                                        @break

                                        @case('landlord')
                                            @include('livewire.offer-listing.offer-landlord-tabs.commission-based.property-preferences')
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
                                    @include('livewire.offer-listing.offer-tenant-tabs.commission-based.leasing-terms')
                                @elseif($user_type === 'seller')
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms')
                                @elseif($user_type === 'buyer')
                                    @include('livewire.offer-listing.offer-buyer-tabs.commission-based.purchasing-terms')
                                @elseif($user_type === 'landlord')
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.lease-terms')
                                @endif
                        </div>

                        <!-- Conditional Pre-Screening Tab -->
                        @if ($user_type !== 'landlord' and $user_type !== 'buyer' and $user_type !== 'seller')
                            <div class="tab-pane fade {{ $activeTab === $preScreeningTabIndex ? 'show active' : '' }}" id="pre-screening"
                                role="tabpanel" aria-labelledby="pre-screening-tab">
                                @if ($user_type === 'tenant')
                                    @include('livewire.offer-listing.offer-tenant-tabs.commission-based.pre-screening')
                                @elseif($user_type === 'seller')
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms')
                                @endif
                            </div>
                        @endif

                        <!-- Services Tab - Not shown for tenant -->

                        @if ($user_type !== 'tenant')
                        <div class="tab-pane fade {{ $activeTab === $servicesTabIndex ? 'show active' : '' }}"
                            id="services" role="tabpanel" aria-labelledby="services-tab">

                            @if ($user_type === 'seller')
                                @include('livewire.offer-listing.offer-seller-tabs.commission-based.services')
                            @elseif($user_type === 'buyer')
                                @include('livewire.offer-listing.offer-buyer-tabs.commission-based.services')
                            @elseif($user_type === 'landlord')
                                @include('livewire.offer-listing.offer-landlord-tabs.commission-based.services')
                            @endif
                        </div>
                        @endif

                        <!-- Additional Details Tab -->

                        <div class="tab-pane fade {{ $activeTab === $descriptionTabIndex ? 'show active' : '' }}"
                            id="additional-details" role="tabpanel" aria-labelledby="additional-details-tab">

                            @if ($user_type === 'tenant')
                                @include('livewire.offer-listing.offer-tenant-tabs.commission-based.additional-details')
                            @elseif($user_type === 'seller')
                                @include('livewire.offer-listing.offer-seller-tabs.commission-based.additional-details')
                            @elseif($user_type === 'buyer')
                                @include('livewire.offer-listing.offer-buyer-tabs.commission-based.additional-details')
                            @elseif($user_type === 'landlord')
                                @include('livewire.offer-listing.offer-landlord-tabs.commission-based.additional-details')
                            @endif
                        </div>

                        <!-- Broker Compensation Tab -->

                        <div class="tab-pane fade {{ $activeTab === $brokerCompTabIndex ? 'show active' : '' }}"
                            id="broker-compensation-agency-agreement-terms" role="tabpanel" aria-labelledby="broker-compensation-agency-agreement-terms-tab">

                            @if ($user_type === 'tenant')
                                @include('livewire.offer-listing.offer-tenant-tabs.commission-based.broker-compensation', ['isTenantOfferListing' => true])
                            @elseif ($user_type === 'seller')
                                @include('livewire.offer-listing.offer-seller-tabs.commission-based.broker-compensation')
                            @elseif($user_type === 'buyer')
                                @include('livewire.offer-listing.offer-buyer-tabs.commission-based.broker-compensation')
                            @elseif($user_type === 'landlord')
                                @include('livewire.offer-listing.offer-landlord-tabs.commission-based.broker-compensation')
                            @endif
                        </div>

                        <!-- Referral & Cooperation Terms Tab - Agent only, not shown for tenant -->
                        @if ($isAgentUser && $user_type !== 'tenant')
                        <div class="tab-pane fade {{ $activeTab === $referralTabIndex ? 'show active' : '' }}"
                            id="referral-cooperation-terms" role="tabpanel" aria-labelledby="referral-cooperation-terms-tab">
                            <div class="p-3">
                                <h5 class="fw-bold mb-3">Referral &amp; Cooperation Terms</h5>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold" for="referral_percentage_edit2">Referral Fee (%) <span class="text-muted fw-normal">(Agent-to-Agent)</span></label>
                                    <input type="number"
                                           class="form-control"
                                           id="referral_percentage_edit2"
                                           wire:model.defer="referral_percentage"
                                           min="0" max="100" step="0.01"
                                           placeholder="Enter referral percentage (e.g., 25)">
                                    <div class="form-text text-muted mt-1" style="font-size:.85rem;">
                                        This is the referral fee offered to or requested from the hired Agent or their brokerage. This term is negotiated between agents and is not paid by the client.
                                    </div>
                                    @error('referral_percentage') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Info Tab -->
                        @php
                            $infoTabId = match($user_type) {
                                'tenant' => 'tenant-information',
                                'seller' => 'seller-information',
                                'buyer' => 'buyer-information',
                                'landlord' => 'landlord-information',
                                default => 'tenant-information'
                            };
                        @endphp
                        <div class="tab-pane fade {{ $activeTab === $infoTabIndex ? 'show active' : '' }}"
                            id="{{ $infoTabId }}" role="tabpanel" aria-labelledby="{{ $infoTabId }}-tab">

                            @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                @include('livewire.partials.agent-credentials')
                            @elseif ($user_type === 'tenant')
                                @include('livewire.offer-listing.offer-tenant-tabs.commission-based.tenant-info')
                            @elseif($user_type === 'seller')
                                @include('livewire.offer-listing.offer-seller-tabs.commission-based.seller-info')
                            @elseif($user_type === 'buyer')
                                @include('livewire.offer-listing.offer-buyer-tabs.commission-based.buyer-info')
                            @elseif($user_type === 'landlord')
                                @include('livewire.offer-listing.offer-landlord-tabs.commission-based.landlord-info')
                            @endif
                        </div>

                        <!-- AI Knowledge Base Tab (full_service) -->
                        <div class="tab-pane fade {{ $activeTab === $aiQuestionsTabIndex ? 'show active' : '' }}" id="ai-questions"
                            role="tabpanel" aria-labelledby="ai-questions-tab">
                            @include('livewire.offer-listing.shared.ai-questions-input')
                        </div>
                    @elseif($service_type === 'limited_service')
                        <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                            id="location-and-meeting-details" role="tabpanel"
                            aria-labelledby="location-and-meeting-details-tab">
                            @include('livewire.offer-listing.offer-tenant-tabs.flat-fee.location-and-meeting-details')

                        </div>
                        <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                            id="service-selection-and-pricing" role="tabpanel"
                            aria-labelledby="service-selection-and-pricing-tab">
                            @include('livewire.offer-listing.offer-tenant-tabs.flat-fee.service_selection_pricing')

                        </div>

                        <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}"
                            id="additional-details" role="tabpanel" aria-labelledby="additional-details-tab">

                            @include('livewire.offer-listing.offer-tenant-tabs.commission-based.additional-details')

                        </div>

                        <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}" id="information"
                            role="tabpanel" aria-labelledby="information-tab">
                            @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                @include('livewire.partials.agent-credentials')
                            @else
                                @include('livewire.offer-listing.offer-tenant-tabs.commission-based.tenant-info')
                            @endif
                        </div>

                        <!-- AI Knowledge Base Tab (limited_service: index 5) -->
                        <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}" id="ai-questions"
                            role="tabpanel" aria-labelledby="ai-questions-tab">
                            @include('livewire.offer-listing.shared.ai-questions-input')
                        </div>
                        @endif
                </div>






                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between form-group mt-4">
                            <div>
                                <button type="button" class="btn btn-secondary wizard-step-back">Back</button>
                            </div>
                            <div>
                                @if($isDraft)
                                <button type="button" class="btn btn-outline-primary me-2" onclick="syncAllSelect2BeforeSave(); @this.call('saveDraftOnly');" wire:loading.attr="disabled" wire:target="saveDraftOnly">
                                    <span wire:loading.remove wire:target="saveDraftOnly"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraftOnly">Saving...</span>
                                </button>
                                @else
                                <button type="button" onclick="doSaveEditWithSync()" class="btn btn-outline-primary me-2" wire:loading.attr="disabled" wire:target="update">
                                    <span wire:loading.remove wire:target="update"><i class="fa-solid fa-save me-1"></i> Save Edit</span>
                                    <span wire:loading wire:target="update">Saving...</span>
                                </button>
                                @endif

                                <button type="button" class="btn btn-primary wizard-step-next">Next</button>

                                <button type="button" onclick="doSaveEditWithSync()" class="btn btn-success wizard-step-finish"
                                    id="save-button" @if(!$isDraft) style="display:none;" @endif>
                                    Submit
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
                var _exOpen = false;
                try { _exOpen = !!($exEl.data('select2') && $exEl.data('select2').isOpen()); } catch(e) {}
                if (!_exOpen) {
                    $exEl.select2('destroy');
                    $exEl.data('exchange-change-bound', false);
                }
            }
            if (!$exEl.hasClass('select2-hidden-accessible')) {
                $exEl.select2({ placeholder: "Select acceptable exchange items", allowClear: true, width: '100%', closeOnSelect: false });
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
                    @this.set('exchange_item', $(this).val() || [], true);
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
                    setTimeout(function() {
                        rehydrateAllSelect2Fields();
                        if (typeof toggleGarageOptions === 'function') toggleGarageOptions();
                        if (typeof toggleOtherPropertyCondition === 'function') {
                            toggleOtherPropertyCondition($('.condition_prop_buyer').val() || []);
                        }
                        if (typeof toggleLease === 'function') {
                            toggleLease($('.lease_for').val() || []);
                        }
                    }, 200);
                    setTimeout(addIconsToInputs, 300);
                    setTimeout(function() {
                        if (typeof window._updateNextSubmitButtons === 'function') window._updateNextSubmitButtons();
                    }, 50);
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

        function debouncedSet(field, value, delay) {
            clearTimeout(_s2Timers[field]);
            _s2Timers[field] = setTimeout(function() {
                @this.set(field, value, true);
            }, delay || 200);
        }

        // Safe Livewire setter — guards against component not ready
        var _editComponentId = '{{ $_instance->id }}';
        function safeLivewireSet(property, value, defer) {
            try {
                if (typeof Livewire !== 'undefined' &&
                    Livewire.components &&
                    Livewire.components.componentsById &&
                    Livewire.components.componentsById[_editComponentId]) {
                    var _comp = Livewire.components.componentsById[_editComponentId];
                    if (_comp && typeof _comp.set === 'function') {
                        _comp.set(property, value, defer || false);
                    }
                }
            } catch (e) {
                console.warn('[Livewire Edit] Component not ready for property:', property, e);
            }
        }

        function syncAllSelect2BeforeSave() {
            Object.keys(_s2Timers).forEach(function(k) { clearTimeout(_s2Timers[k]); });
            _s2Timers = {};

            var $cpb = $('.condition_prop_buyer');
            if ($cpb.length && $cpb.hasClass('select2-hidden-accessible')) {
                var cpbVals = $cpb.val() || [];
                cpbVals = [...new Set(cpbVals)];
                @this.set('condition_prop_buyer', cpbVals, true);
                var cpbJsonInput = document.querySelector('input[wire\\:model="condition_prop_buyer_json"]');
                if (cpbJsonInput) {
                    cpbJsonInput.value = JSON.stringify(cpbVals);
                    cpbJsonInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                @this.set('condition_prop_buyer_json', JSON.stringify(cpbVals), true);
            }

            var $numUnit = $('.number_of_unit_type');
            if ($numUnit.length && $numUnit.hasClass('select2-hidden-accessible')) {
                var nuVals = $numUnit.val() || [];
                nuVals = [...new Set(nuVals)];
                @this.set('number_of_unit_type', nuVals, true);
                var nutJsonInput = document.querySelector('input[wire\\:model\\.defer="number_of_unit_type_json"]');
                if (nutJsonInput) {
                    nutJsonInput.value = JSON.stringify(nuVals);
                    nutJsonInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                @this.set('number_of_unit_type_json', JSON.stringify(nuVals), true);
            }

            var $pi = $('#property_items');
            if ($pi.length && $pi.hasClass('select2-hidden-accessible')) {
                var piVals = $pi.val() || [];
                @this.set('property_items', piVals, true);
                var piJsonInput = document.querySelector('input[wire\\:model="property_items_json"]');
                if (piJsonInput) {
                    piJsonInput.value = JSON.stringify(piVals);
                    piJsonInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                @this.set('property_items_json', JSON.stringify(piVals), true);
            }

            var $sp = $('#sale_provision');
            if ($sp.length && $sp.hasClass('select2-hidden-accessible')) {
                @this.set('sale_provision', $sp.val(), true);
            }

            var $of = $('#offered_financing');
            if ($of.length && $of.hasClass('select2-hidden-accessible')) {
                @this.set('offered_financing', $of.val(), true);
            }

            var $gps = $('#garage_parking_spaces_option_landlord');
            if ($gps.length && $gps.hasClass('select2-hidden-accessible')) {
                @this.set('garage_parking_spaces_option', $gps.val(), true);
            }

            var $gpsT = $('#garage_parking_spaces_option');
            if ($gpsT.length && $gpsT.hasClass('select2-hidden-accessible')) {
                @this.set('garage_parking_spaces_option', $gpsT.val() || [], true);
            }

            var $ls = $('#leasing_spaces_tenant');
            if ($ls.length && $ls.hasClass('select2-hidden-accessible')) {
                @this.set('leasing_spaces_tenant', $ls.val() || [], true);
            }

            var $vp = $('#view_preference');
            if ($vp.length && $vp.hasClass('select2-hidden-accessible')) {
                @this.set('view_preference', $vp.val() || [], true);
            }

            var $tp = $('.tenant_pays');
            if ($tp.length && $tp.hasClass('select2-hidden-accessible')) {
                @this.set('tenant_pays', $tp.val() || [], true);
            }

            var $dll = $('.lease_term_options');
            if ($dll.length && $dll.hasClass('select2-hidden-accessible')) {
                @this.set('desired_lease_length', $dll.val() || [], true);
            }

            var $lf = $('.lease_for');
            if ($lf.length && $lf.hasClass('select2-hidden-accessible')) {
                var lfVals = $lf.val() || [];
                @this.set('lease_for', lfVals, true);
            }

            var $nna = $('#non_negotiable_amenities');
            if ($nna.length && $nna.hasClass('select2-hidden-accessible')) {
                @this.set('non_negotiable_amenities', $nna.val() || [], true);
            }

            // Seller: property style is a plain (non-Select2) single select — sync explicitly
            var _curUT2 = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : '';
            if (_curUT2 === 'seller') {
                var _pss = document.getElementById('property_style_select');
                if (_pss) {
                    @this.set('property_items', _pss.value, true);
                }
            }
        }

        var _saveEditSyncPending = false;
        var _editCorrectionMode = false;
        var _editMissingItems   = [];

        function doSaveEditWithSync() {
            // Sync Select2 fields first so their values are current for validation
            syncAllSelect2BeforeSave();

            // Run required-field validation before saving
            if (typeof window._editGetInvalidItems === 'function') {
                var invalidItems = window._editGetInvalidItems();
                if (invalidItems.length > 0) {
                    var _banner = document.getElementById('submit-error-banner');
                    var _errList = document.getElementById('submit-error-list');
                    if (_banner && _errList) {
                        _errList.innerHTML = '';
                        var _seen = new Set();
                        invalidItems.forEach(function(item) {
                            if (!_seen.has(item.fieldName)) {
                                _seen.add(item.fieldName);
                                var li = document.createElement('li');
                                li.textContent = item.fieldName;
                                _errList.appendChild(li);
                            }
                        });
                        _banner.classList.remove('d-none');
                    }
                    // Navigate to first missing field once — do not enter repeated
                    // correction mode to prevent message.processed loop flicker.
                    _editMissingItems = invalidItems;
                    if (typeof window._editNavigateToItem === 'function') {
                        window._editNavigateToItem(invalidItems[0]);
                    }
                    return; // abort — do not save
                }
            }

            // All required fields satisfied — call update().
            // All @this.set() calls above used defer=true, so their values
            // are batched into this single request atomically.
            @this.call('update');
        }

        // message.processed auto-advance removed — it caused repeated scroll/focus
        // flicker. Required fields are now enforced and navigated once per save click.

        document.addEventListener('submit', function(e) {
            if (e.target && e.target.tagName === 'FORM') {
                syncAllSelect2BeforeSave();

                var $cpb = $('.condition_prop_buyer');
                if ($cpb.length && $cpb.hasClass('select2-hidden-accessible')) {
                    var cpbVals = $cpb.val() || [];
                    cpbVals = [...new Set(cpbVals)];
                    @this.set('condition_prop_buyer', cpbVals, true);
                    if (typeof syncConditionJsonBridge === 'function') {
                        syncConditionJsonBridge(cpbVals);
                    }
                }
            }
        }, true);

        document.addEventListener('DOMContentLoaded', () => {
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
            if (typeof toggleVacantLand === 'function') toggleVacantLand();
        });

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

        function rehydrateAllSelect2Fields() {
            var fields = [
                { sel: '.condition_prop_buyer', prop: 'condition_prop_buyer' },
                { sel: '#view_preference', prop: 'view_preference' },
                { sel: '#leasing_spaces_tenant', prop: 'leasing_spaces_tenant' },
                { sel: '#exchange_item', prop: 'exchange_item' },
                { sel: '#sale_provision', prop: 'sale_provision' },
                { sel: '#offered_financing', prop: 'offered_financing' },
                { sel: '#property_items', prop: 'property_items' },
                { sel: '#appliances', prop: 'appliances' },
                { sel: '#garage_parking_spaces_option', prop: 'garage_parking_spaces_option' },
                { sel: '#garage_parking_spaces_option_landlord', prop: 'garage_parking_spaces_option' },
                { sel: '.tenant_pays', prop: 'tenant_pays' },
                { sel: '#pool_type', prop: 'pool_type' },
                { sel: '#non_negotiable_amenities', prop: 'non_negotiable_amenities' },
                { sel: '#credit_scroe_rating', prop: 'credit_scroe_rating' },
                { sel: '.lease_for', prop: 'lease_for' },
                { sel: '.number_of_unit_type', prop: 'number_of_unit_type' },
                { sel: '#tenant_require', prop: 'tenant_require' },
                { sel: '#owner_pays', prop: 'owner_pays' },
                { sel: '#rent_includes', prop: 'rent_includes' },
                { sel: '#desired_lease_length', prop: 'desired_lease_length' },
                { sel: '#terms_of_lease', prop: 'terms_of_lease' },
                { sel: '#garage_parking_spaces_option_buyer', prop: 'garage_parking_spaces_option_buyer' },
            ];
            fields.forEach(function(f) {
                rehydrateSelect2FromLivewire(f.sel, f.prop);
            });
        }

        function rehydrateSelect2FromLivewire(selector, prop) {
            try {
                var $el = $(selector);
                if (!$el.length || !$el.hasClass('select2-hidden-accessible')) return;
                var val = @this.get(prop);
                if (val && ((Array.isArray(val) && val.length > 0) || (!Array.isArray(val) && val))) {
                    $el.val(val).trigger('change.select2');
                }
            } catch(e) {
                console.warn('[rehydrateSelect2] Error for', prop, e);
            }
        }

        function initializeFullService() {


            if ($('#sale_provision').length && !$('#sale_provision').hasClass('select2-hidden-accessible')) {
                $('#sale_provision').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                }).on('change', function() {
                    debouncedSet('sale_provision', $(this).val());

                    $(this).find('option:selected').each(function() {
                        var description = $(this).attr(
                            'title');
                        $(this).attr('title', description);
                    });

                    $(this).find('option').each(function() {
                        var description = $(this).attr('title');
                        if (description) {
                            $(this).tooltip({
                                title: description
                            });
                        }
                    });
                });
            }

            if ($('#offered_financing').length && !$('#offered_financing').hasClass('select2-hidden-accessible')) {
                $('#offered_financing').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                }).on('change', function() {
                    debouncedSet('offered_financing', $(this).val());

                    $(this).find('option').each(function() {
                        var description = $(this).attr('title');
                        if (description) {
                            $(this).tooltip({
                                title: description
                            });
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
                @this.set('condition_prop_buyer_json', json);
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

                $sel.select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });

                $sel.off('change.cpbSync').on('change.cpbSync', function(e) {
                    let data = $(this).val() || [];
                    data = [...new Set(data)];
                    @this.set('condition_prop_buyer', data, true);
                    syncConditionJsonBridge(data);
                    toggleOtherPropertyCondition(data);
                });

                var current = @this.get('condition_prop_buyer');
                if (current && current.length) {
                    $sel.val(current).trigger('change.select2');
                    toggleOtherPropertyCondition(Array.isArray(current) ? current : []);
                }
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
                    width: '100%',
                    closeOnSelect: false,
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
                        width: '100%',
                        closeOnSelect: false,
                    });

                    $el.on('change', function(e) {
                        let selectedValues = $(this).val() || [];
                        selectedValues = [...new Set(selectedValues)];
                        debouncedSet('number_of_unit_type', selectedValues);
                    });
                }
            });



            







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
                    safeLivewireSet('property_items', selectedValues, true);
                });
            }
            initPropertyItemsSelect2();
            // One-time sync on edit load: confirm Select2 display values back to Livewire
            setTimeout(function() {
                var $pi = $('#property_items');
                if ($pi.length && $pi.hasClass('select2-hidden-accessible')) {
                    var vals = $pi.val() || [];
                    if (vals.length) {
                        safeLivewireSet('property_items', vals, true);
                    }
                }
            }, 400);
            Livewire.hook('message.processed', () => {
                var currentPT = @this.get('property_type') || '';
                var lwVals = @this.get('property_items') || [];
                if (currentPT !== _lastPropertyTypeForPI) {
                    _lastPropertyTypeForPI = currentPT;
                    setTimeout(function() {
                        rebuildPropertyItemsOptions(currentPT, lwVals);
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
                    @this.set('other_property_items', '');
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

                const otherInput = otherFieldContainer.querySelector('input[type="text"]');

                if (selectElement.value === 'Other') {
                    otherFieldContainer.classList.remove('d-none');
                    if (otherInput) otherInput.setAttribute('required', '');
                } else {
                    otherFieldContainer.classList.add('d-none');
                    if (otherInput) otherInput.removeAttribute('required');
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




            ///// •     Business & Real Estate Purchase Requirements


            // cache the wrapper
            const $select = $('#assets');
            const $other = $('.other_assets');

            if ($select.length && !$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });

                $select.on('change', () => {
                    const vals = $select.val() || [];
                    Livewire.emit('assetsOption', vals);
                    $other.toggleClass('d-none', !vals.includes('Other'));
                });
            }

            //// End •  Business & Real Estate Purchase Requirements

            // Seller — Included Property or Business Assets (#included_assets)
            const $sellerAssetsSelect = $('#included_assets');
            if ($sellerAssetsSelect.length) {
                if ($sellerAssetsSelect.hasClass('select2-hidden-accessible')) {
                    var _saOpen = false;
                    try { _saOpen = !!($sellerAssetsSelect.data('select2') && $sellerAssetsSelect.data('select2').isOpen()); } catch(e) {}
                    if (!_saOpen) {
                        $sellerAssetsSelect.select2('destroy');
                    }
                }
                if (!$sellerAssetsSelect.hasClass('select2-hidden-accessible')) {
                    $sellerAssetsSelect.select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
                    const _sellerSavedAssets = @json(is_array($business_assets) ? $business_assets : []);
                    if (Array.isArray(_sellerSavedAssets) && _sellerSavedAssets.length > 0) {
                        $sellerAssetsSelect.val(_sellerSavedAssets).trigger('change.select2');
                    }
                    $sellerAssetsSelect.on('change', function() {
                        const vals = $(this).val() || [];
                        Livewire.emit('assetsOption', vals);
                    });
                }
            }

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
                    width: '100%',
                    closeOnSelect: false,
                });

                $('#credit_scroe_rating').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('credit_scroe_rating', selectedValues, true);
                });
            }

            function initNonNegotiableAmenitiesSelect2() {
                var $nn = $('#non_negotiable_amenities');
                if (!$nn.length) return;
                var currentPT = @this.get('property_type') || '';
                if (currentPT) {
                    $nn.prop('disabled', false);
                } else {
                    $nn.prop('disabled', true);
                }
                if ($nn.hasClass('select2-hidden-accessible')) {
                    var _s2Open = false;
                    try { _s2Open = !!($nn.data('select2') && $nn.data('select2').isOpen()); } catch(e) {}
                    if (_s2Open) return;
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
                            @this.set('other_non_negotiable_amenities', '', true);
                        }
                        @this.set('non_negotiable_amenities', vals, true);
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

                if (selectElement.value === 'Bidding Period') {
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

                // First check the main garage/parking spaces selection (only exists for Commercial)
                if (garageSelect && optionsWrapper) {
                    if (garageSelect.value === "Yes") {
                        optionsWrapper.classList.remove('d-none'); // Show options dropdown
                    } else {
                        optionsWrapper.classList.add('d-none'); // Hide options dropdown
                        if (otherInputWrapper) otherInputWrapper.classList.add('d-none'); // Also hide other input
                    }
                }

                // Then check if "Other" is selected in the options dropdown
                if (garageOptions && otherInputWrapper) {
                    // Use Select2 value if initialized, otherwise use native selectedOptions
                    var $gSel = $(garageOptions);
                    var selectedOptions;
                    if ($gSel.hasClass('select2-hidden-accessible')) {
                        selectedOptions = $gSel.val() || [];
                    } else {
                        selectedOptions = Array.from(garageOptions.selectedOptions).map(opt => opt.value);
                    }

                    // garageSelect may not exist (non-Commercial): treat as "enabled" in that case
                    var garageEnabled = !garageSelect || garageSelect.value === "Yes";

                    if (selectedOptions.includes("Other") && garageEnabled) {
                        otherInputWrapper.classList.remove('d-none'); // Show input field
                    } else if (!garageEnabled) {
                        otherInputWrapper.classList.add('d-none'); // Hide when garage is No
                    } else {
                        otherInputWrapper.classList.add('d-none'); // Hide when Other not selected
                    }
                }
            }

            // Initialize on page load
            toggleGarageOptions();

            // Listen for Livewire updates
            Livewire.hook('message.processed', () => {
                toggleGarageOptions();
                var $gpsOpt = $('#garage_parking_spaces_option');
                if ($gpsOpt.length && !$gpsOpt.hasClass('select2-hidden-accessible')) {
                    $gpsOpt.select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $gpsOpt.off('change.gpsSyncEdit').on('change.gpsSyncEdit', function() {
                        var selectedValues = $(this).val() || [];
                        debouncedSet('garage_parking_spaces_option', selectedValues);
                    });
                }
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
                            Livewire.emit('updateModel', inputField.getAttribute('wire:model'), '');
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



            function initSelect2() {
                if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                    $('#view_preference').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });

                    $('#view_preference').on('change', function() {
                        let selectedValues = $(this).val() || [];
                        debouncedSet('view_preference', selectedValues);

                        if (selectedValues.includes('Other')) {
                            $('#other_preferences').show();
                        } else {
                            $('#other_preferences').hide();
                            @this.set('other_preferences', '');
                        }
                    });
                }
                rehydrateSelect2FromLivewire('#view_preference', 'view_preference');
            }

            initSelect2();


            ///////////////////// end view_preference

            // garage_parking_spaces_option for multi-select


            const $sel = $('#garage_parking_spaces_option');

            if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
                $sel.select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });

                $sel.on('change', function() {
                    const vals = $(this).val() || [];

                    Livewire.emit('updateGarageParkingSpaces', vals);

                    if (vals.includes('Other')) {
                        $('#other_parking_space_wrapper').show();
                    } else {
                        $('#other_parking_space_wrapper').hide();
                        debouncedSet('other_parking_space_wrapper', null);
                    }
                });

                // Restore saved value on edit load using PHP-rendered value (no timing issue)
                var _savedGarageVals = @json($garage_parking_spaces_option ?? []);
                if (_savedGarageVals && _savedGarageVals.length) {
                    $sel.val(_savedGarageVals).trigger('change.select2');
                    // Show "Other" input immediately if "Other" was saved
                    if (_savedGarageVals.includes('Other')) {
                        $('#other_parking_space_wrapper').show();
                    }
                }
                // Re-run the garage toggle so all visibility is in sync
                if (typeof toggleGarageOptions === 'function') toggleGarageOptions();
            }





            ///Preference

            // Initialize Select2 for appliances
            function initializeAppliancesSelect2() {
                if ($('#appliances').length && !$('#appliances').hasClass('select2-hidden-accessible')) {
                    $('#appliances').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });

                    $('#appliances').on('change', function() {
                        let selectedValuesAppliances = $(this).val();
                        Livewire.emit('updateAppliances', selectedValuesAppliances);

                        if (selectedValuesAppliances && selectedValuesAppliances.includes('Other')) {
                            $('#other_appliances').show();
                        } else {
                            $('#other_appliances').hide();
                            debouncedSet('other_appliances', null);
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
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });

                $('#leasing_spaces_tenant').on('change', function(e) {
                    debouncedSet('leasing_spaces_tenant', $(this).val() || []);
                });
            }
            rehydrateSelect2FromLivewire('#leasing_spaces_tenant', 'leasing_spaces_tenant');

            ///////////////// End leasing_spaces
            ///tenant_pays

            function toggleOtherTenantField(selectedValues) {
                if (selectedValues.includes('Other')) {
                    $('.tenant_pays_other').removeClass('d-none');
                } else {
                    $('.tenant_pays_other').addClass('d-none');
                }
            }

            $('.tenant_pays').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $(this).on('change', function() {
                        let selectedValues = $(this).val() || [];

                        Livewire.emit('updateTenantPays', selectedValues);
                        if (selectedValues && selectedValues.includes('Other')) {
                            $('.tenant_pays_other').show();
                        } else {
                            $('.tenant_pays_other').hide();
                        }
                        debouncedSet('tenant_pays', selectedValues);
                    });
                }
            });

            toggleOtherTenantField($('.tenant_pays').val() || []);








            $('.lease_term_options').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var savedLtVals = @json($desired_lease_length ?? []);
                    if (savedLtVals && savedLtVals.length > 0) {
                        $(this).val(savedLtVals).trigger('change');
                    }
                    $(this).on('change', function() {
                        let selectedValues = $(this).val() || [];
                        if (selectedValues && selectedValues.includes('Other')) {
                            $('.other_lease_term').show();
                        } else {
                            $('.other_lease_term').hide();
                            debouncedSet('other_lease_term', null);
                        }
                        debouncedSet('desired_lease_length', selectedValues);
                    });
                }
            });





            var termsOfLeaseValues = @json($terms_of_lease ?? []);
            if (termsOfLeaseValues && termsOfLeaseValues.length > 0) {
                $('.terms_of_lease').val(termsOfLeaseValues);
            }
            $('.terms_of_lease').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        placeholder: "Select lease terms",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $(this).on('change', function(e) {
                        const selectedValues = $(this).val() || [];
                        debouncedSet('terms_of_lease', selectedValues);
                        toggleLeaseOther(selectedValues);
                    });
                }
            });
            if (termsOfLeaseValues && termsOfLeaseValues.length > 0) {
                $('.terms_of_lease').trigger('change.select2');
                @this.set('terms_of_lease', termsOfLeaseValues);
            }

            function toggleLeaseOther(selectedValues) {
                if (selectedValues.includes('Other')) {
                    $('#otherLeaseContainer').removeClass('d-none');
                } else {
                    $('#otherLeaseContainer').addClass('d-none');
                }
            }

            toggleLeaseOther($('.terms_of_lease').val() || []);

            // End tenant_pays
            ///owner_pays

            function toggleOwnerPaysOther(selectedValues) {
                if (selectedValues.includes('Other')) {
                    $('.other_owner_pays').removeClass('d-none');
                } else {
                    $('.other_owner_pays').addClass('d-none');
                }
            }

            $('.owner_pays').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $(this).on('change', function() {
                        let selectedValues = $(this).val() || [];
                        Livewire.emit('updateOwnerPays', selectedValues);
                        if (selectedValues && selectedValues.includes('Other')) {
                            $('.other_owner_pays').show();
                        } else {
                            $('.other_owner_pays').hide();
                            @this.set('other_owner_pays', null, true);
                        }
                        @this.set('owner_pays', selectedValues, true);
                    });
                }
            });

            toggleOwnerPaysOther($('.owner_pays').val() || []);

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
                if ($sel.hasClass('select2-hidden-accessible')) {
                    var _s2Open = false;
                    try { _s2Open = !!($sel.data('select2') && $sel.data('select2').isOpen()); } catch(e) {}
                    if (_s2Open) return;
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
                    safeLivewireSet('lease_for', selectedLease, true);
                    toggleLease(selectedLease);
                });
            }

            var _lastPropertyTypeForLF = @this.get('property_type') || '';

            initSelect2LeaseFor();
            // One-time sync on edit load: confirm Select2 display values back to Livewire
            setTimeout(function() {
                var $lf = $('.lease_for');
                if ($lf.length && $lf.hasClass('select2-hidden-accessible')) {
                    var vals = $lf.val() || [];
                    if (vals.length) {
                        safeLivewireSet('lease_for', vals, true);
                    }
                }
            }, 400);
            Livewire.hook('message.processed', () => {
                var currentPT = @this.get('property_type') || '';
                var lwLease = @this.get('lease_for') || [];
                if (currentPT !== _lastPropertyTypeForLF) {
                    _lastPropertyTypeForLF = currentPT;
                    setTimeout(function() {
                        var $lf = $('.lease_for');
                        if ($lf.hasClass('select2-hidden-accessible')) {
                            var _lfOpen = false;
                            try { _lfOpen = !!($lf.data('select2') && $lf.data('select2').isOpen()); } catch(e) {}
                            if (_lfOpen) return;
                            $lf.select2('destroy');
                        }
                        initSelect2LeaseFor();
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

            $('.rent_includes').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $(this).on('change', function() {
                        let selectedValues = $(this).val() || [];
                        Livewire.emit('updateRentIncludes', selectedValues);
                        if (selectedValues && selectedValues.includes('Other')) {
                            $('.other_rent_input_wrapper').show();
                        } else {
                            $('.other_rent_input_wrapper').hide();
                            @this.set('other_rent_include', null, true);
                        }
                        @this.set('rent_includes', selectedValues, true);
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

                if (file.size > 10 * 1024 * 1024) {
                    if (videoError) {
                        videoError.textContent = 'Video size must be under 10MB. For larger videos, please paste a link instead (e.g., YouTube or Vimeo). For privacy, you can set your video as unlisted on YouTube so only those with the link can view it.';
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


            // Function to check if all required fields are filled
            function checkFormValidity() {
                let allValid = true;

                // Check all tabs for required fields
                document.querySelectorAll('.tab-pane').forEach(tabPane => {
                    tabPane.querySelectorAll('[required]').forEach(field => {
                        if (!field.value) {
                            allValid = false;
                        }
                    });
                });

                // No longer disable button - let Livewire handle server-side validation
                // const saveButton = document.querySelector('.wizard-step-finish');
                // if (saveButton) {
                //     saveButton.disabled = !allValid;
                // }

                return allValid;
            }

            // Add this function to validate services tab
            function validateServicesTab(tabContent) {
                if (!tabContent || tabContent.id !== 'services') return true;

                let isValid = true;

                // Check at least one service is selected (excluding "Other" checkbox)
                const hasServices = tabContent.querySelectorAll(
                    'input[type="checkbox"][wire\\:model="services"]:checked:not(#other-services-checkbox)'
                ).length > 0;

                // Check "Other Services" if enabled
                const otherCheckbox = tabContent.querySelector('#other-services-checkbox');
                const otherTextarea = tabContent.querySelector('#other-services-input');
                const hasOtherDescription = otherTextarea && otherTextarea.value.trim() !== '';

                // Clear previous errors
                const existingErrors = tabContent.querySelectorAll('.service-error');
                if (existingErrors) {
                    existingErrors.forEach(el => el.remove());
                }
                if (otherTextarea) otherTextarea.classList.remove('is-invalid');

                // Services validation removed - selecting services is now optional
                // No validation error will be shown if no services are selected

                // if (otherCheckbox && otherCheckbox.checked && (!otherTextarea || !hasOtherDescription)) {
                //     isValid = false;
                //     const errorDiv = document.createElement('div');
                //     errorDiv.className = 'service-error error mt-2';
                //     errorDiv.textContent = 'Please describe the additional services you require.';

                //     if (otherTextarea) {
                //         otherTextarea.classList.add('is-invalid');
                //         const container = otherTextarea.closest('.mb-3') || otherTextarea.parentNode;
                //         if (container) {
                //             container.appendChild(errorDiv);
                //         }
                //     }
                // }

                return isValid;
            }
            // Initialize shared wizard navigation (removes old handlers first)
            initializeWizardNavigation();

        }

        function initializeLimitedService() {
            // Initialize shared wizard navigation (removes old handlers first)
            initializeWizardNavigation();
        }



        function isElementVisible(element) {
            if (!element) return false;
            if (element.disabled) return false;
            if (element.type === 'hidden') return false;
            if (element.offsetParent === null && element.tagName !== 'BODY' && element.tagName !== 'HTML') {
                if (element.offsetHeight === 0 && element.offsetWidth === 0) {
                    return false;
                }
            }
            if (element.offsetHeight === 0 || element.offsetWidth === 0) {
                return false;
            }
            const style = window.getComputedStyle(element);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            const parent = element.closest('.d-none, .collapse:not(.show)');
            if (parent) return false;
            return true;
        }

        // Shared wizard navigation handler - called once on load and avoids duplicate handlers
        let wizardNavigationInitialized = false;

        // Derive tab order from visible nav-link elements in the DOM
        let EDIT_TAB_ORDER = null;
        function getEditTabOrder() {
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
        function ensureEditTabOrder() {
            if (!EDIT_TAB_ORDER || EDIT_TAB_ORDER.length === 0) {
                EDIT_TAB_ORDER = getEditTabOrder();
            }
            return EDIT_TAB_ORDER;
        }

        var _isDraftEdit = @json($isDraft);
        window._updateNextSubmitButtons = function() {
            var tabOrder = getEditTabOrder();
            var activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) return;
            var currentTarget = (activeTab.getAttribute('data-bs-target') || '').replace('#', '');
            var currentIndex = tabOrder.indexOf(currentTarget);
            var isLastTab = (currentIndex === tabOrder.length - 1);
            var nextBtn = document.querySelector('.wizard-step-next');
            var submitBtn = document.querySelector('.wizard-step-finish');
            if (nextBtn) nextBtn.style.display = isLastTab ? 'none' : '';
            // Submit only visible for draft listings; published edits use Save Edit only
            if (submitBtn) submitBtn.style.display = _isDraftEdit ? '' : 'none';
        };
        setTimeout(window._updateNextSubmitButtons, 300);

        // Navigation guard to prevent double-firing
        let isEditNavigating = false;

        function goToNextEditTab() {
            if (isEditNavigating) return false;
            isEditNavigating = true;

            const tabOrder = ensureEditTabOrder();
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) { isEditNavigating = false; return false; }

            const currentTarget = activeTab.getAttribute('data-bs-target')?.replace('#', '');
            const currentIndex = tabOrder.indexOf(currentTarget);
            if (currentIndex === -1) { isEditNavigating = false; return false; }

            const nextTabId = tabOrder[currentIndex + 1];
            if (!nextTabId) { isEditNavigating = false; return false; }

            const nextTabEl = document.querySelector(`[data-bs-target="#${nextTabId}"]`);
            if (!nextTabEl) { isEditNavigating = false; return false; }

            bootstrap.Tab.getOrCreateInstance(nextTabEl).show();
            Livewire.emit('setActiveTab', currentIndex + 1);

            setTimeout(() => { isEditNavigating = false; }, 500);
            return true;
        }

        function goToPrevEditTab() {
            const tabOrder = ensureEditTabOrder();
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) return false;

            const currentTarget = activeTab.getAttribute('data-bs-target')?.replace('#', '');
            const currentIndex = tabOrder.indexOf(currentTarget);
            if (currentIndex === -1 || currentIndex === 0) return false;

            const prevTabId = tabOrder[currentIndex - 1];
            const prevTabEl = document.querySelector(`[data-bs-target="#${prevTabId}"]`);
            if (!prevTabEl) return false;

            bootstrap.Tab.getOrCreateInstance(prevTabEl).show();
            Livewire.emit('setActiveTab', currentIndex - 1);
            return true;
        }

        function initializeWizardNavigation() {
            EDIT_TAB_ORDER = getEditTabOrder();
            wizardNavigationInitialized = true;
        }

        function validateCurrentTab(currentTabContent) {
            let isValid = true;

            const requiredFields = currentTabContent.querySelectorAll(
                'input[required], select[required], textarea[required]');
            if (requiredFields) {
                requiredFields.forEach(function(input) {
                    if (!isElementVisible(input)) {
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
                            citiesContainer.parentNode.insertBefore(citiesError, citiesContainer.nextSibling);
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

            if (currentTabContent.id === 'services') {
                isValid = isValid && validateServicesTab(currentTabContent);
            }

            if (currentTabContent.id === 'service-selection-and-pricing') {
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

        function validateServicesTab(tabContent) {
            if (!tabContent || tabContent.id !== 'services') return true;
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
                        goToNextEditTab();
                    } else {
                        showValidationBanner(currentTabContent);
                    }
                }

                if (e.target.closest('.wizard-step-back')) {
                    goToPrevEditTab();
                }
            });
        }
        
        function addIconsToInputs() {
            document.querySelectorAll('.has-icon[data-icon]').forEach(input => {
                const iconClass = input.getAttribute('data-icon');
                if (!iconClass) return;
                const wrapper = input.closest('.input-cover');
                if (!wrapper) return;
                if (input.type === 'file') return;
                wrapper.querySelectorAll('.input-icon:not(.data-icon-rendered)').forEach(el => el.remove());
                if (wrapper.querySelector('.data-icon-rendered')) return;
                const icon = document.createElement('i');
                icon.className = `input-icon ${iconClass} data-icon-rendered`;
                wrapper.insertBefore(icon, wrapper.firstChild);
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

        document.addEventListener('livewire:load', function() {
            addIconsToInputs();
        });

        Livewire.hook('message.processed', () => {
            addIconsToInputs();
            checkRepresentationStatus();
            if (typeof window._updateNextSubmitButtons === 'function') {
                window._updateNextSubmitButtons();
            }

            if (isEditNavigating) return;

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

                removeWizardEventListeners();

                if (currentServiceType === 'full_service') {
                    initializeFullService();
                } else if (currentServiceType === 'limited_service') {
                    initializeLimitedService();
                }

                setTimeout(addIconsToInputs, 300);
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

                // Build the correct info-tab selector based on role
                // Tab pane IDs: tenant-information, seller-information, buyer-information, landlord-information
                const _curRole = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : 'tenant';
                const infoTabId = '#' + _curRole + '-information';

                // Seller and Landlord use #property-details; Buyer and Tenant use #property-preferences
                const propertyTabId = (_curRole === 'seller' || _curRole === 'landlord')
                    ? '#property-details'
                    : '#property-preferences';

                const tabSelector = serviceType === 'full_service' ? [
                    '#listing-details',
                    propertyTabId,
                    '#purchasing-terms',
                    '#sale-terms',
                    '#leasing-terms',
                    '#pre-screening',
                    '#services',
                    '#additional-details',
                    '#broker-compensation-agency-agreement-terms',
                    infoTabId
                ] : [
                    '#listing-details',
                    '#location-and-meeting-details',
                    '#service-selection-and-pricing',
                    infoTabId
                ];

                tabSelector.forEach(selector => {
                    const tab = document.querySelector(selector);
                    if (!tab) return;
                    const fields = tab.querySelectorAll('[required]');
                    fields.forEach(field => requiredFields.push(field));
                });

                return requiredFields;
            }

            function isFieldValid(field) {
                if (field.tagName === 'SELECT' && field.multiple && $(field).hasClass('select2-hidden-accessible')) {
                    var s2Container = $(field).next('.select2-container');
                    if (s2Container.length && s2Container.is(':visible')) {
                        var selectedVals = $(field).val() || [];
                        return selectedVals.length > 0;
                    }
                }
                if (field.type === 'checkbox' || field.type === 'radio') {
                    return field.checked;
                }
                if (field.type === 'select-one' || field.type === 'select-multiple') {
                    return field.value !== '' && field.value !== null;
                }
                return field.value.trim() !== '';
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
                            value: field.value
                        });
                    }
                });

                try {
                    var wireEl = document.querySelector('[wire\\:id]');
                    if (wireEl && typeof Livewire !== 'undefined') {
                        var comp = Livewire.find(wireEl.getAttribute('wire:id'));
                        if (comp && comp.get) {
                            var sType = formContainer.getAttribute('data-service-type');
                            var curUT = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : 'tenant';
                            if (sType === 'full_service') {
                                var lwChecks = [
                                    { prop: 'property_type', label: 'Property Type' },
                                ];
                                if (curUT === 'tenant') {
                                    lwChecks.push({ prop: 'lease_for', label: 'Offered Lease Term', isArray: true, domSel: '.lease_for' });
                                    lwChecks.push({ prop: 'leasing_spaces_tenant', label: 'Leasing Space', isArray: true, domSel: '#leasing_spaces_tenant' });
                                } else if (curUT === 'landlord') {
                                    lwChecks.push({ prop: 'desired_lease_length', label: 'Desired Lease Term', isArray: true, domSel: '.lease_term_options' });
                                    lwChecks.push({ prop: 'leasing_spaces', label: 'Offered Lease Space', isArray: false });
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
                                        invalidFields.push({ tab: 0, field: chk.prop, value: '' });
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

            // Initial setup
            setupGlobalListeners();
            updateSaveButton();

            // Livewire reactivity hook
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', () => {
                    setTimeout(() => {
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

            // ---------------------------------------------------------------
            // EDIT VALIDATION HELPERS — exposed as window globals so that
            // doSaveEditWithSync() (defined in global scope) can call them.
            // ---------------------------------------------------------------

            function editGetInvalidItemsFull() {
                var inv = [];
                var _fc = document.getElementById('wizard-form-container');
                if (!_fc) return inv;

                var rFields = getAllRequiredFields();
                rFields.forEach(function(field) {
                    var isS2Multi = (field.tagName === 'SELECT' && field.multiple && $(field).hasClass('select2-hidden-accessible'));
                    if (!isS2Multi) {
                        // For fields inside a .tab-pane, skip only if they are conditionally hidden
                        // WITHIN the pane (by blade conditionals). Do NOT skip just because the
                        // Bootstrap tab is inactive — inactive panes are display:none at the
                        // .tab-pane level but required fields on other tabs must still be validated.
                        var tabPane = field.closest ? field.closest('.tab-pane') : null;
                        if (tabPane) {
                            var _el = field.parentElement;
                            var _condHidden = false;
                            while (_el && _el !== tabPane) {
                                if (window.getComputedStyle(_el).display === 'none') {
                                    _condHidden = true;
                                    break;
                                }
                                _el = _el.parentElement;
                            }
                            if (_condHidden) return;
                        } else {
                            if (typeof isElementVisible === 'function' && !isElementVisible(field)) return;
                        }
                    }
                    if (!isFieldValid(field)) {
                        var tab  = field.closest('.tab-pane');
                        var lbl  = field.closest('.form-group') && field.closest('.form-group').querySelector('label');
                        var name = lbl ? lbl.textContent.replace(/[*:]/g, '').trim()
                                       : (field.getAttribute('placeholder') || field.name || field.id || 'Required field');
                        inv.push({ field: field, tab: tab, fieldName: name });
                    }
                });

                // Cities / counties (role-dependent)
                var _curUT = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : '';
                var _citiesOptional = ['buyer', 'tenant'];
                var _citiesCont = document.querySelector('.cities-container');
                if (_citiesCont && !_citiesOptional.includes(_curUT)) {
                    if (!_citiesCont.querySelectorAll('.badge').length) {
                        inv.push({ field: _citiesCont, tab: _citiesCont.closest('.tab-pane'), fieldName: 'City' });
                    }
                }
                var _countiesCont = document.querySelector('.counties-container');
                if (_countiesCont && !_countiesCont.querySelectorAll('.badge').length) {
                    inv.push({ field: _countiesCont, tab: _countiesCont.closest('.tab-pane'), fieldName: 'Acceptable Counties' });
                }

                // Livewire array / special fields
                try {
                    var _wireEl = document.querySelector('[wire\\:id]');
                    if (_wireEl && typeof Livewire !== 'undefined') {
                        var _comp = Livewire.find(_wireEl.getAttribute('wire:id'));
                        if (_comp && _comp.get) {
                            var _svc = _fc.getAttribute('data-service-type');
                            if (_svc === 'full_service') {
                                var _lwReqs = [{ prop: 'property_type', label: 'Property Type' }];
                                if (_curUT === 'tenant') {
                                    _lwReqs.push({ prop: 'lease_for',           label: 'Offered Lease Term',  isArray: true,  domSel: '.lease_for' });
                                    _lwReqs.push({ prop: 'leasing_spaces_tenant', label: 'Leasing Space',    isArray: false });
                                } else if (_curUT === 'landlord') {
                                    _lwReqs.push({ prop: 'desired_lease_length', label: 'Desired Lease Term', isArray: true, domSel: '.lease_term_options' });
                                    _lwReqs.push({ prop: 'leasing_spaces',       label: 'Offered Lease Space', isArray: false });
                                }
                                _lwReqs.forEach(function(chk) {
                                    var v = _comp.get(chk.prop), isEmpty;
                                    if (chk.isArray) {
                                        if (typeof v === 'string') { try { v = JSON.parse(v); } catch(e2) {} }
                                        isEmpty = !v || (Array.isArray(v) && v.length === 0) || v === '' || v === '[]';
                                        if (isEmpty && chk.domSel) {
                                            var $d = $(chk.domSel);
                                            if ($d.length) {
                                                var dv = $d.val();
                                                if (dv && ((Array.isArray(dv) && dv.length > 0) || (typeof dv === 'string' && dv !== ''))) isEmpty = false;
                                            }
                                        }
                                    } else {
                                        isEmpty = !v || v === '';
                                    }
                                    if (isEmpty) inv.push({ field: document.body, tab: null, fieldName: chk.label });
                                });
                            }
                        }
                    }
                } catch(e) {}

                return inv;
            }
            window._editGetInvalidItems = editGetInvalidItemsFull;

            function editNavigateToItem(item) {
                if (item && item.tab) {
                    var tId = item.tab.id;
                    var tEl = document.querySelector('[data-bs-target="#' + tId + '"], [href="#' + tId + '"]');
                    if (tEl) {
                        new bootstrap.Tab(tEl).show();
                        var wc = tEl.getAttribute('wire:click') || '';
                        var m  = wc.match(/setActiveTab\((\d+)\)/);
                        // Call setActiveTab once to sync server-side $activeTab so subsequent
                        // Livewire re-renders don't snap the tab back. The message.processed
                        // loop is broken separately (auto-advance hook removed).
                        if (m) { try { @this.call('setActiveTab', parseInt(m[1])); } catch(ex) {} }
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
                    var bn = document.getElementById('submit-error-banner');
                    if (bn) { bn.classList.remove('d-none'); bn.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                }, 350);
            }
            window._editNavigateToItem = editNavigateToItem;

            function editAdvanceCorrection() {
                if (!_editCorrectionMode) return;
                var fresh = editGetInvalidItemsFull();

                var _errList3 = document.getElementById('submit-error-list');
                var _banner4  = document.getElementById('submit-error-banner');
                if (_errList3) {
                    _errList3.innerHTML = '';
                    fresh.forEach(function(item) {
                        var li = document.createElement('li');
                        li.textContent = item.fieldName;
                        _errList3.appendChild(li);
                    });
                }

                if (fresh.length === 0) {
                    _editCorrectionMode = false;
                    _editMissingItems   = [];
                    if (_banner4) _banner4.classList.add('d-none');
                    return;
                }

                if (_banner4) _banner4.classList.remove('d-none');
                _editMissingItems = fresh;
                editNavigateToItem(fresh[0]);
            }
            window._editAdvanceCorrection = editAdvanceCorrection;
            // ---------------------------------------------------------------

            const editForm = document.getElementById('edit-auction-form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const banner = document.getElementById('submit-error-banner');
                    const errorList = document.getElementById('submit-error-list');
                    banner.classList.add('d-none');
                    errorList.innerHTML = '';

                    const requiredFields = getAllRequiredFields();
                    let invalidItems = [];

                    requiredFields.forEach(field => {
                        var isSelect2Multi = (field.tagName === 'SELECT' && field.multiple && $(field).hasClass('select2-hidden-accessible'));
                        if (!isSelect2Multi && typeof isElementVisible === 'function' && !isElementVisible(field)) {
                            return;
                        }
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
                            invalidItems.push({ field: countiesContainer, tab, fieldName: 'Acceptable Counties' });
                        }
                    }

                    try {
                        var wireEl2 = document.querySelector('[wire\\:id]');
                        if (wireEl2 && typeof Livewire !== 'undefined') {
                            var comp2 = Livewire.find(wireEl2.getAttribute('wire:id'));
                            if (comp2 && comp2.get) {
                                var svcType2 = formContainer.getAttribute('data-service-type');
                                var curUT2 = (typeof CURRENT_USER_TYPE !== 'undefined') ? CURRENT_USER_TYPE : 'tenant';
                                if (svcType2 === 'full_service') {
                                    var lwReqs2 = [
                                        { prop: 'property_type', label: 'Property Type' },
                                    ];
                                    if (curUT2 === 'tenant') {
                                        lwReqs2.push({ prop: 'lease_for', label: 'Offered Lease Term', isArray: true, domSel: '.lease_for' });
                                        lwReqs2.push({ prop: 'leasing_spaces_tenant', label: 'Leasing Space', isArray: true, domSel: '#leasing_spaces_tenant' });
                                    } else if (curUT2 === 'landlord') {
                                        lwReqs2.push({ prop: 'desired_lease_length', label: 'Desired Lease Term', isArray: true, domSel: '.lease_term_options' });
                                        lwReqs2.push({ prop: 'leasing_spaces', label: 'Offered Lease Space', isArray: false });
                                    }
                                    lwReqs2.forEach(function(chk) {
                                        var val = comp2.get(chk.prop);
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
                                            invalidItems.push({ field: document.body, tab: null, fieldName: chk.label });
                                        }
                                    });
                                }
                            }
                        }
                    } catch(e) { }

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

                        // Navigate once to the first missing field.
                        // Correction-mode auto-advance removed to prevent flicker.
                        _editMissingItems = invalidItems.slice();
                        editNavigateToItem(invalidItems[0]);

                        return false;
                    }

                    banner.classList.add('d-none');
                });
            }
        });
    </script>
    <script>
        document.addEventListener('livewire:load', function() {
            const draftModal = new bootstrap.Modal(document.getElementById('draftModal'));
            draftModal.show();

        });
    </script>

    <script>
        function setupEnhancementToggle() {
            const enhancementTriggerValue = "Provide digital photo enhancements";
            const enhancementWrapper = document.getElementById("enhancement-options-wrapper");
            const checkboxes = document.querySelectorAll('.enhancement-trigger');

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
            <input type="text" class="form-control mb-2" placeholder="Enter additional services not listed above (e.g., School district research, Commute area research, Furnished rental assistance)">
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
            Livewire.hook('message.processed', () => setTimeout(initPhoneFormatter, 10));
        }

        function initPhoneFormatter() {
            const input = document.getElementById('phone_number');
            if (!input) return;

            // Clean up previous listeners
            input.removeEventListener('input', handlePhoneInput);
            input.removeEventListener('keydown', preventNonNumericInput);
            input.removeEventListener('blur', handlePhoneBlur);
            input.removeEventListener('paste', handlePhonePaste);

            // Format initial value if exists
            if (input.value) {
                formatPhoneNumber(input);
            }

            // Add new listeners
            input.addEventListener('input', handlePhoneInput);
            input.addEventListener('keydown', preventNonNumericInput);
            input.addEventListener('blur', handlePhoneBlur);
            input.addEventListener('paste', handlePhonePaste);
        }

        function preventNonNumericInput(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([8, 9, 13, 27, 46].includes(e.keyCode) ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+X
                (e.ctrlKey && [65, 67, 88].includes(e.keyCode)) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }

            // Prevent if not a number
            if ((e.keyCode < 48 || e.keyCode > 57) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        }

        function handlePhoneInput(e) {
            const input = e.target;
            const cursorPos = input.selectionStart;
            const prevValue = input.value;

            // Format the number
            formatPhoneNumber(input);

            // Maintain cursor position
            if (prevValue.length === cursorPos) {
                // If cursor was at end, keep it at end
                input.setSelectionRange(input.value.length, input.value.length);
            } else {
                // Otherwise try to maintain relative position
                const diff = input.value.length - prevValue.length;
                input.setSelectionRange(cursorPos + diff, cursorPos + diff);
            }

            // Update Livewire model with raw numbers
            updateLivewireModel(input);
        }

        function handlePhoneBlur(e) {
            const input = e.target;
            formatPhoneNumber(input);
            updateLivewireModel(input);
        }

        function handlePhonePaste(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text/plain');
            const numbers = pasteData.replace(/\D/g, '');
            document.execCommand('insertText', false, numbers);
        }

        function formatPhoneNumber(input) {
            // Get only digits and limit to 10 characters
            let numbers = input.value.replace(/\D/g, '').substring(0, 10);

            // Format based on length
            let formatted = numbers;
            if (numbers.length > 6) {
                formatted = numbers.replace(/(\d{3})(\d{3})(\d{1,4})/, '$1-$2-$3');
            } else if (numbers.length > 3) {
                formatted = numbers.replace(/(\d{3})(\d{1,3})/, '$1-$2');
            }

            input.value = formatted;
            return formatted;
        }

        function updateLivewireModel(input) {
            if (typeof Livewire === 'undefined') return;

            const rawValue = input.value.replace(/\D/g, '');
            const componentEl = input.closest('[wire\\:id]');
            if (!componentEl) return;

            const component = Livewire.find(componentEl.getAttribute('wire:id'));
            const model = input.getAttribute('wire:model');
            if (component && model) {
                component.set(model, rawValue);
            }
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

    // -------------------------------------------------------------------
    // LOCKED FIELD CLICK INTERCEPTOR
    // Disabled inputs do not fire events. A transparent overlay div
    // (.locked-field-overlay) inside .locked-field-wrapper intercepts
    // clicks and shows a contextual red notice.
    // -------------------------------------------------------------------
    (function() {
        function showLockedNotice(wrapper, msg) {
            var notice = wrapper.parentElement.querySelector('.locked-click-notice');
            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'alert alert-danger mt-1 py-1 px-2 small locked-click-notice';
                notice.style.cssText = 'border-left:4px solid #dc3545;';
                wrapper.insertAdjacentElement('afterend', notice);
            }
            notice.innerHTML = '<i class="fa-solid fa-lock me-1"></i>' + msg;
            notice.style.display = 'block';
            clearTimeout(notice._hideTimer);
            notice._hideTimer = setTimeout(function() {
                if (notice && notice.parentElement) notice.style.display = 'none';
            }, 5000);
        }

        document.addEventListener('click', function(e) {
            var overlay = e.target.closest('.locked-field-overlay');
            if (overlay) {
                var wrapper = overlay.closest('.locked-field-wrapper');
                if (wrapper) {
                    var msg = wrapper.getAttribute('data-lock-msg') || 'This field cannot be edited after the listing is created.';
                    showLockedNotice(wrapper, msg);
                }
            }
        }, true);
    })();

    // -------------------------------------------------------------------
    // BROWSER EVENT LISTENERS — draft-saved & edit-validation-failed
    // -------------------------------------------------------------------
    window.addEventListener('draft-saved', function(e) {
        var msg = (e.detail && e.detail.message) ? e.detail.message : 'Draft saved successfully!';
        var container = document.getElementById('wizard-form-container') || document.body;
        var toast = document.getElementById('draft-saved-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'draft-saved-toast';
            toast.className = 'alert alert-success alert-dismissible mb-3';
            toast.style.cssText = 'position:sticky;top:70px;z-index:999;';
            toast.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i><span id="draft-saved-msg"></span>'
                + '<button type="button" class="btn-close" onclick="this.parentElement.style.display=\'none\'"></button>';
            var firstChild = container.firstElementChild;
            if (firstChild) container.insertBefore(toast, firstChild); else container.appendChild(toast);
        }
        var msgEl = document.getElementById('draft-saved-msg');
        if (msgEl) msgEl.textContent = msg;
        toast.style.display = 'block';
        clearTimeout(toast._draftTimer);
        toast._draftTimer = setTimeout(function() { if (toast) toast.style.display = 'none'; }, 5000);
        var errBanner = document.getElementById('submit-error-banner');
        if (errBanner) errBanner.classList.add('d-none');
    });

    window.addEventListener('edit-validation-failed', function(e) {
        var fields = (e.detail && e.detail.fields) ? e.detail.fields : [];
        var _banner = document.getElementById('submit-error-banner');
        var _errList = document.getElementById('submit-error-list');
        if (_banner && _errList) {
            _errList.innerHTML = '';
            fields.forEach(function(name) {
                var li = document.createElement('li');
                li.textContent = name;
                _errList.appendChild(li);
            });
            _banner.classList.remove('d-none');
            _banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
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

    function formatAllTenantNumericInputs() {
        var scope = document.querySelector('form') || document;
        scope.querySelectorAll('input[onblur*="reformatNumber"]').forEach(function(inp) {
            if (!inp.value || inp.value.includes(',')) return;
            reformatNumber(inp);
        });
        scope.querySelectorAll('input[onblur*="formatWithCommas"]').forEach(function(inp) {
            if (!inp.value || inp.value.includes(',')) return;
            if (typeof formatWithCommas === 'function') formatWithCommas(inp);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(formatAllTenantNumericInputs, 100);
    });
</script>
@endpush
