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

        @media (max-width: 768px) {
            .status-text {
                font-size: 0.9rem;
            }

            .status-icon {
                margin-right: 0.3rem;
                font-size: 1rem;
            }
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

    $property_condition_seller = $property_condition;

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

    $bathroomOptions = [
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

    // Appliances list matching Landlord Agent with legacy options for backwards compatibility
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

    // Define the updated services array (renamed to avoid overriding the Livewire $services property)
    $_tenantAgentServiceList = [
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
    $acceptable_leasing_space = [
        ['name' => 'Entire Property'],
        ['name' => 'Single Room'],
        ['name' => 'Open to Leasing Either Entire Property or Single Room'],
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

    $seller_property = [
        ['name' => 'Assignment Contract'],
        ['name' => 'Auction'],
        ['name' => 'Bank Owned/REO'],
        ['name' => 'Government Owned'],
        ['name' => 'None'],
        ['name' => 'Probate Listing'],
        ['name' => 'Short Sale'],
        ['name' => 'Other'],
    ];
@endphp

<div class="container pt-5 pb-5">
    <div class="card">
        <div class="row">
            <div class="col-12 p-4">

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

                <div id="wizard-form-container" class="container pt-5 pb-5" data-service-type="{{ $service_type }}" data-user-type="{{ $user_type }}">

                    <form id="create-auction-form" wire:submit.prevent="store" novalidate>
                        <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                            <strong>Please complete the required fields before submitting.</strong>
                            <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                        </div>
                        <!-- Tab Navigation -->

                        @if ($service_type === 'full_service')
                            @php $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent'; @endphp

                            @php
                                // Sequential counter: tabs 0-3 come from the foreach loop.
                                // Insert any new seller-only tabs here; $sellerInfoIndex
                                // auto-adjusts without touching other values.
                                $nextTabIdx      = 4;
                                $compatIndex     = ($user_type === 'seller') ? $nextTabIdx++ : null;
                                $sellerInfoIndex = $nextTabIdx;
                            @endphp
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (['Listing Details', 'Property Preferences', 'sale Terms', 'Additional Details'] as $index => $tab)
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
                                @if ($user_type === 'seller')
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $compatIndex ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $compatIndex }})"
                                            id="representation-compatibility-tab" data-bs-toggle="tab"
                                            data-bs-target="#representation-compatibility"
                                            type="button" role="tab"
                                            aria-controls="representation-compatibility"
                                            aria-selected="{{ $activeTab === $compatIndex ? 'true' : 'false' }}">
                                            Representation Preferences &amp; Compatibility
                                        </button>
                                    </li>
                                @endif
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $sellerInfoIndex ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $sellerInfoIndex }})"
                                        id="seller-information-tab" data-bs-toggle="tab"
                                        data-bs-target="#seller-information"
                                        type="button" role="tab"
                                        aria-controls="seller-information"
                                        aria-selected="{{ $activeTab === $sellerInfoIndex ? 'true' : 'false' }}">
                                        {{ $isAgentUser ? 'Agent Credentials & Contact Info' : 'Seller Information' }}
                                    </button>
                                </li>
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
                                    id="property-preferences" role="tabpanel"
                                    aria-labelledby="property-preferences-tab">

                                    @if ($user_type === 'tenant')
                                        @include('livewire.tenant-agent-auction-tabs.commission-based.property-details')
                                    @elseif($user_type === 'seller')
                                        @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.property-preferences')
                                    @elseif($user_type === 'buyer')
                                        @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.property-preferences')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.property-preferences')
                                    @endif

                                </div>

                                <!-- Leasing Terms Tab -->
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}" id="sale-terms"
                                    role="tabpanel" aria-labelledby="sale-terms-tab">
                                    @if ($user_type === 'tenant')
                                        @include('livewire.tenant-agent-auction-tabs.commission-based.leasing-terms')
                                    @elseif($user_type === 'seller')
                                        @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.seller-terms')
                                    @elseif($user_type === 'buyer')
                                        @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.purchasing-terms')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.purchasing-terms')
                                    @endif
                                </div>

                                <!-- Additional Details Tab -->
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}"
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

                                @if ($user_type === 'seller')
                                    {{-- Representation Preferences & Compatibility Tab (seller full service only) --}}
                                    <div class="tab-pane fade {{ $activeTab === $compatIndex ? 'show active' : '' }}"
                                        id="representation-compatibility" role="tabpanel"
                                        aria-labelledby="representation-compatibility-tab">
                                        @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.representation-compatibility')
                                    </div>
                                @endif

                                <!-- Seller Info Tab -->
                                <div class="tab-pane fade {{ $activeTab === $sellerInfoIndex ? 'show active' : '' }}" id="seller-information"
                                    role="tabpanel" aria-labelledby="seller-information-tab">
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
                    @endif
                </div>
                <!-- Navigation Buttons -->
                <div class="d-flex justify-content-between form-group mt-4">
                    <div>
                        <button type="button" class="btn btn-secondary wizard-step-back">Back</button>
                    </div>
                    <div>

                        @php $isTrueEdit = !$isDraft && !empty($listingId); @endphp

                        @if (!$isTrueEdit)
                        <button type="button" class="btn btn-outline-primary me-2" onclick="syncAllSelect2BeforeSave(); @this.call('saveDraft');" wire:loading.attr="disabled" wire:target="saveDraft">
                            <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                            <span wire:loading wire:target="saveDraft">Saving...</span>
                        </button>
                        @endif

                        <button type="button" class="btn btn-primary wizard-step-next">Next</button>

                        <button type="submit" class="btn btn-success wizard-step-finish" id="save-button" wire:loading.attr="disabled" wire:target="store">
                            <span wire:loading.remove wire:target="store">{{ $isTrueEdit ? 'Save Edit' : 'Submit' }}</span>
                            <span wire:loading wire:target="store">{{ $isTrueEdit ? 'Saving...' : 'Submitting...' }}</span>
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
    <script>
        let currentServiceType = null;
        let _s2Timers = {};
        let _lastInitTime = 0;
        let _bathroomsDropdownHandler = null;

        function debouncedSet(field, value, delay) {
            clearTimeout(_s2Timers[field]);
            _s2Timers[field] = setTimeout(function() {
                @this.set(field, value);
            }, delay || 200);
        }

        function syncAllSelect2BeforeSave() {
            Object.keys(_s2Timers).forEach(function(k) { clearTimeout(_s2Timers[k]); });
            _s2Timers = {};
            var fields = {
                property_items: '#property_items',
                non_negotiable_amenities: '#non_negotiable_amenities',
                appliances: '#appliances',
                exchange_item: '#exchange_item',
                sale_provision: '#sale_provision',
                offered_financing: '#offered_financing',
                view_preference: '#view_preference',
            };
            Object.entries(fields).forEach(function([field, selector]) {
                var el = $(selector);
                if (el.length && el.hasClass('select2-hidden-accessible')) {
                    @this.set(field, el.val() || []);
                }
            });
            // Representation Preferences & Compatibility (seller full service only)
            var _syncCompatContainer = document.getElementById('wizard-form-container');
            if (_syncCompatContainer && _syncCompatContainer.getAttribute('data-service-type') === 'full_service') {
                var compatFields = {
                    '#compat_preferred_contact_method': 'compatibility_preferences.seller_specific.preferred_contact_method',
                    '#compat_willing_to_negotiate_on':  'compatibility_preferences.seller_specific.willing_to_negotiate_on',
                    '#compat_representation_priorities': 'compatibility_preferences.seller_specific.representation_priorities',
                    '#compat_qualities_most_important': 'compatibility_preferences.seller_specific.qualities_most_important',
                    '#compat_showing_availability':     'compatibility_preferences.seller_specific.showing_availability',
                };
                Object.entries(compatFields).forEach(function([selector, prop]) {
                    var el = $(selector);
                    if (el.length && el.hasClass('select2-hidden-accessible')) {
                        @this.set(prop, el.val() || []);
                    }
                });
                var compatSingleFields = [
                    { selector: '#compat_communication_style',           prop: 'compatibility_preferences.seller_specific.communication_style' },
                    { selector: '#compat_response_time_expectation',     prop: 'compatibility_preferences.seller_specific.response_time_expectation' },
                    { selector: '#compat_negotiation_style',             prop: 'compatibility_preferences.seller_specific.negotiation_style' },
                    { selector: '#compat_firm_on_price',                 prop: 'compatibility_preferences.seller_specific.firm_on_price' },
                    { selector: '#compat_primary_transaction_goal',      prop: 'compatibility_preferences.seller_specific.primary_transaction_goal' },
                    { selector: '#compat_flexibility_on_timeline',       prop: 'compatibility_preferences.seller_specific.flexibility_on_timeline' },
                    { selector: '#compat_post_sale_plan',                prop: 'compatibility_preferences.seller_specific.post_sale_plan' },
                    { selector: '#compat_past_agent_experience',         prop: 'compatibility_preferences.seller_specific.past_agent_experience' },
                    { selector: '#compat_decision_making_style',         prop: 'compatibility_preferences.seller_specific.decision_making_style' },
                    { selector: '#compat_involvement_level',             prop: 'compatibility_preferences.seller_specific.involvement_level' },
                    { selector: '#compat_preferred_agent_working_style', prop: 'compatibility_preferences.seller_specific.preferred_agent_working_style' },
                    { selector: '#compat_open_house_preference',         prop: 'compatibility_preferences.seller_specific.open_house_preference' },
                ];
                compatSingleFields.forEach(function(item) {
                    var el = $(item.selector);
                    if (el.length && el.hasClass('select2-hidden-accessible')) {
                        @this.set(item.prop, el.val() || '', false);
                    }
                });
            }
        }

        // Centralized, idempotent initializer for all Representation Preferences & Compatibility
        // Select2 fields. Safe to call from any lifecycle hook (initial load, message.processed,
        // draftLoaded) — duplicate initialization and duplicate listeners are prevented via
        // hasClass('select2-hidden-accessible') and jQuery data flags.
        window.initCompatSelect2Fields = function() {
            var compatMultis = [
                { id: '#compat_preferred_contact_method',  prop: 'compatibility_preferences.seller_specific.preferred_contact_method',  flag: 'compat-pcm-bound' },
                { id: '#compat_willing_to_negotiate_on',   prop: 'compatibility_preferences.seller_specific.willing_to_negotiate_on',   flag: 'compat-wtn-bound' },
                { id: '#compat_representation_priorities', prop: 'compatibility_preferences.seller_specific.representation_priorities',  flag: 'compat-rp-bound' },
                { id: '#compat_qualities_most_important',  prop: 'compatibility_preferences.seller_specific.qualities_most_important',   flag: 'compat-qmi-bound' },
                { id: '#compat_showing_availability',      prop: 'compatibility_preferences.seller_specific.showing_availability',      flag: 'compat-sa-bound' },
            ];
            compatMultis.forEach(function(item) {
                var $el = $(item.id);
                if (!$el.length) return;
                if (!$el.hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($el);
                }
                $el.off('change.' + item.flag).on('change.' + item.flag, (function(p) { return function() { @this.set(p, $(this).val() || [], false); }; })(item.prop));
                var saved = @this.get(item.prop) || [];
                $el.val(saved).trigger('change.select2');
            });

            // otherCompanion: DOM id of a wrapper div to show/hide when value === 'Other'.
            // null = no companion. To add Other support to a future field, set otherCompanion
            // to the wrapper id and add the companion input to the partial — no loop changes needed.
            var compatSingles = [
                { id: '#compat_communication_style',           prop: 'compatibility_preferences.seller_specific.communication_style',           flag: 'compat-cs-bound',   otherCompanion: null },
                { id: '#compat_response_time_expectation',     prop: 'compatibility_preferences.seller_specific.response_time_expectation',     flag: 'compat-rte-bound',  otherCompanion: null },
                { id: '#compat_negotiation_style',             prop: 'compatibility_preferences.seller_specific.negotiation_style',             flag: 'compat-ns-bound',   otherCompanion: null },
                { id: '#compat_firm_on_price',                 prop: 'compatibility_preferences.seller_specific.firm_on_price',                 flag: 'compat-fop-bound',  otherCompanion: null },
                { id: '#compat_primary_transaction_goal',      prop: 'compatibility_preferences.seller_specific.primary_transaction_goal',      flag: 'compat-ptg-bound',  otherCompanion: 'compat_ptg_other_wrapper' },
                { id: '#compat_flexibility_on_timeline',       prop: 'compatibility_preferences.seller_specific.flexibility_on_timeline',       flag: 'compat-fot-bound',  otherCompanion: null },
                { id: '#compat_post_sale_plan',                prop: 'compatibility_preferences.seller_specific.post_sale_plan',                flag: 'compat-psp-bound',  otherCompanion: null },
                { id: '#compat_past_agent_experience',         prop: 'compatibility_preferences.seller_specific.past_agent_experience',         flag: 'compat-pae-bound',  otherCompanion: null },
                { id: '#compat_decision_making_style',         prop: 'compatibility_preferences.seller_specific.decision_making_style',         flag: 'compat-dms-bound',  otherCompanion: null },
                { id: '#compat_involvement_level',             prop: 'compatibility_preferences.seller_specific.involvement_level',             flag: 'compat-il-bound',   otherCompanion: null },
                { id: '#compat_preferred_agent_working_style', prop: 'compatibility_preferences.seller_specific.preferred_agent_working_style', flag: 'compat-paws-bound', otherCompanion: null },
                { id: '#compat_open_house_preference',         prop: 'compatibility_preferences.seller_specific.open_house_preference',         flag: 'compat-ohp-bound',  otherCompanion: null },
            ];
            compatSingles.forEach(function(item) {
                var $el = $(item.id);
                if (!$el.length) return;
                if (!$el.hasClass('select2-hidden-accessible')) {
                    $el.select2({ placeholder: 'Select', allowClear: true, width: '100%' });
                }
                // Closure captures both prop and companion id for the change handler
                $el.off('change.' + item.flag).on('change.' + item.flag, (function(prop, companionId) {
                    return function() {
                        @this.set(prop, $(this).val() || '', false);
                        if (companionId) {
                            var companion = document.getElementById(companionId);
                            if (companion) companion.style.display = ($(this).val() === 'Other') ? '' : 'none';
                        }
                    };
                })(item.prop, item.otherCompanion || null));
                var saved = @this.get(item.prop) || '';
                $el.val(saved).trigger('change.select2');
                // Apply Other-companion visibility immediately after rehydration
                if (item.otherCompanion) {
                    var companion = document.getElementById(item.otherCompanion);
                    if (companion) companion.style.display = (saved === 'Other') ? '' : 'none';
                }
            });
        };

        // Initialize Bootstrap tooltips and handle Livewire re-renders
        function initializeTooltips() {
            // Dispose of existing tooltips first to prevent sticking
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                const existingTooltip = bootstrap.Tooltip.getInstance(el);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
            });
            // Initialize fresh tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                new bootstrap.Tooltip(el, {
                    trigger: 'hover focus',
                    container: 'body'
                });
            });
        }

        // Hide all tooltips when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('[data-bs-toggle="tooltip"]')) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                    const tooltip = bootstrap.Tooltip.getInstance(el);
                    if (tooltip) {
                        tooltip.hide();
                    }
                });
            }
        });

        // Hide all tooltips when pressing Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                    const tooltip = bootstrap.Tooltip.getInstance(el);
                    if (tooltip) {
                        tooltip.hide();
                    }
                });
            }
        });

        // Seller Sale Terms visibility logic must remain identical between the dedicated Seller path and the shared TenantAgentAuction seller path.
        // If changes are made to one path, they must also be applied to the other to keep both Seller flows consistent.
        function applyFinancingVisibility() {
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

        function applyProvisionVisibility() {
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

        // Document-level event delegation for sale provision and financing visibility.
        // Using $(document).on() means these handlers survive any DOM replacement,
        // morphdom patching, or Select2 re-initialization — they are bound once and
        // never need to be re-registered.
        // @this.set() syncs the value into the Livewire component so that every
        // subsequent server-side re-render keeps sections visible (mirrors Appliances pattern).
        $(document).on('change', '#sale_provision', function() {
            var selectedValues = $(this).val() || [];
            @this.set('sale_provision', selectedValues, false);
            applyProvisionVisibility();
        });
        $(document).on('change', '#offered_financing', function() {
            var selectedValues = $(this).val() || [];
            @this.set('offered_financing', selectedValues, false);
            applyFinancingVisibility();
        });

        // Re-initialize tooltips after Livewire updates
        document.addEventListener('livewire:load', function() {
            initializeTooltips();

            Livewire.hook('message.processed', (message, component) => {
                initializeTooltips();

                if ($('#offered_financing').length) { applyFinancingVisibility(); }
                if ($('#sale_provision').length) { applyProvisionVisibility(); }

                var $appliances = $('#appliances');
                if ($appliances.length) {
                    if (!$appliances.hasClass('select2-hidden-accessible')) {
                        window.initFullServiceSelect2Multiple($appliances);
                        if (!$appliances.data('appliances-change-bound')) {
                            $appliances.on('change', function() {
                                var selectedValues = $(this).val() || [];
                                @this.set('appliances', selectedValues, false);
                                if (selectedValues.includes('Other')) {
                                    $('#other_appliances').show();
                                } else {
                                    $('#other_appliances').hide();
                                }
                            });
                            $appliances.data('appliances-change-bound', true);
                        }
                    }
                    var aData = $appliances.val() || [];
                    if (aData.includes('Other')) {
                        $('#other_appliances').show();
                    } else {
                        $('#other_appliances').hide();
                    }
                }

                var $nna = $('#non_negotiable_amenities');
                if ($nna.length) {
                    if (!$nna.hasClass('select2-hidden-accessible')) {
                        window.initFullServiceSelect2Multiple($nna);
                        if (!$nna.data('nna-change-bound')) {
                            $nna.on('change', function() {
                                var selectedValues = $(this).val() || [];
                                @this.set('non_negotiable_amenities', selectedValues, false);
                            });
                            $nna.data('nna-change-bound', true);
                        }
                    }
                    var nnaVals = $nna.val() || [];
                    var $otherNna = document.querySelector('.other_non_negotiable_amenities');
                    if ($otherNna) {
                        if (nnaVals.includes('Other')) {
                            $otherNna.classList.remove('d-none');
                        } else {
                            $otherNna.classList.add('d-none');
                        }
                    }
                }

                var $viewPref = $('#view_preference');
                if ($viewPref.length && $viewPref.hasClass('select2-hidden-accessible')) {
                    var vData = $viewPref.val() || [];
                    if (vData.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                    }
                }

                // Compatibility Select2 fields — seller full service only
                var _mpCompatContainer = document.getElementById('wizard-form-container');
                if (_mpCompatContainer && _mpCompatContainer.getAttribute('data-service-type') === 'full_service' && typeof window.initCompatSelect2Fields === 'function') {
                    window.initCompatSelect2Fields();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize tooltips on page load
            initializeTooltips();

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

            // Polling safety net: ensures sections show/hide within 100ms of any Select2 change,
            // regardless of whether jQuery event binding caught the change event.
            var _lastProvVal = '';
            var _lastFinVal = '';
            setInterval(function() {
                var $sp = $('#sale_provision');
                var $of = $('#offered_financing');
                if ($sp.length) {
                    var pv = JSON.stringify($sp.val() || []);
                    if (pv !== _lastProvVal) {
                        _lastProvVal = pv;
                        applyProvisionVisibility();
                    }
                }
                if ($of.length) {
                    var fv = JSON.stringify($of.val() || []);
                    if (fv !== _lastFinVal) {
                        _lastFinVal = fv;
                        applyFinancingVisibility();
                    }
                }
            }, 100);
        });
        
        // Sync select element values from Livewire component data
        function syncSelectValues() {
            const wireEl = document.querySelector('[wire\\:id]');
            if (!wireEl || typeof Livewire === 'undefined') return;
            const component = Livewire.find(wireEl.getAttribute('wire:id'));
            if (!component) return;
            document.querySelectorAll('select[wire\\:model]').forEach(select => {
                const wireModel = select.getAttribute('wire:model');
                if (wireModel && component.get) {
                    try {
                        const lwValue = component.get(wireModel);
                        if (lwValue && select.value !== lwValue) {
                            console.log('[SyncSelect] Syncing ' + wireModel + ': DOM="' + select.value + '" -> LW="' + lwValue + '"');
                            select.value = lwValue;
                        }
                    } catch (e) {}
                }
            });
        }
        
        // Listen for draftLoaded browser event
        window.addEventListener('draftLoaded', function() {
            console.log('[DraftLoaded] Event received - syncing select values');
            setTimeout(function() {
                syncSelectValues();
                rehydrateSelect2MultiFields();
                addIconsToInputs();
                if (typeof window.updateSaveButton === 'function') {
                    window.updateSaveButton();
                }
            }, 100);
        });

        // Re-apply icons and re-format money inputs when a Bootstrap tab is shown
        $(document).on('shown.bs.tab', function() {
            addIconsToInputs();
            if (typeof initializeMoneyInputs === 'function') {
                initializeMoneyInputs();
            }
        });

        function rehydrateSelect2MultiFields() {
            var multiFields = {
                '#exchange_item': 'exchange_item',
                '#non_negotiable_amenities': 'non_negotiable_amenities',
                '#sale_provision': 'sale_provision',
                '#offered_financing': 'offered_financing',
                '#view_preference': 'view_preference',
                '#appliances': 'appliances',
            };
            Object.keys(multiFields).forEach(function(selector) {
                var $el = $(selector);
                if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                    var prop = multiFields[selector];
                    var saved = @this.get(prop) || [];
                    if (saved.length > 0) {
                        $el.val(saved).trigger('change.select2');
                        console.log('[DraftLoaded] Rehydrated ' + prop + ':', saved);
                    }
                }
            });
            // Rehydrate #included_assets — conditionally rendered (Business/Commercial/Income only),
            // so Select2 may not be initialized when draftLoaded fires; initialize first if needed.
            try {
                var $ia = $('#included_assets');
                if ($ia.length) {
                    if (!$ia.hasClass('select2-hidden-accessible')) {
                        $ia.select2({
                            placeholder: "Select",
                            allowClear: true,
                            width: "100%",
                            closeOnSelect: false,
                        });
                        $ia.off('change.s2sync').on('change.s2sync', function() {
                            @this.set('business_assets', $(this).val() || [], false);
                        });
                    }
                    var savedBusinessAssets = @this.get('business_assets') || [];
                    if (savedBusinessAssets.length > 0) {
                        $ia.val(savedBusinessAssets).trigger('change.select2');
                        console.log('[DraftLoaded] Rehydrated business_assets:', savedBusinessAssets);
                    }
                }
            } catch(eIa) { console.log('[DraftLoaded] included_assets error', eIa); }
            // Compatibility Select2 fields — seller full service only
            var _rhCompatContainer = document.getElementById('wizard-form-container');
            if (_rhCompatContainer && _rhCompatContainer.getAttribute('data-service-type') === 'full_service') {
                window.initCompatSelect2Fields();
            }
        }

        // Listen for force-redirect event to ensure redirect works after submit
        window.addEventListener('force-redirect', function(event) {
            console.log('[ForceRedirect] Redirecting to:', event.detail.url);
            window.location.href = event.detail.url;
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

        function initializeFullService() {
            if ($('#offered_financing').length) {
                if (!$('#offered_financing').hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($('#offered_financing'));
                }
                if (!$('#offered_financing').data('of-change-bound')) {
                    $('#offered_financing').on('change', function() {
                        var selectedValues = $(this).val() || [];
                        @this.set('offered_financing', selectedValues, false);
                        applyFinancingVisibility();
                    });
                    $('#offered_financing').data('of-change-bound', true);
                }
                applyFinancingVisibility();
            }

            if ($('#sale_provision').length) {
                if (!$('#sale_provision').hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($('#sale_provision'));
                }
                if (!$('#sale_provision').data('sp-change-bound')) {
                    $('#sale_provision').on('change', function() {
                        var selectedValues = $(this).val() || [];
                        @this.set('sale_provision', selectedValues, false);
                        applyProvisionVisibility();
                    });
                    $('#sale_provision').data('sp-change-bound', true);
                }
                applyProvisionVisibility();
            }


            if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
                window.initFullServiceSelect2Multiple($('#property_items'));
                $('#property_items').on('change', function(e) {
                    let selectedValues = $(this).val();
                    debouncedSet('property_items', selectedValues);
                });
            }

            if ($('#non_negotiable_amenities').length) {
                if (!$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($('#non_negotiable_amenities'));
                }
                if (!$('#non_negotiable_amenities').data('nna-change-bound')) {
                    $('#non_negotiable_amenities').on('change', function(e) {
                        let selectedValues = $(this).val() || [];
                        @this.set('non_negotiable_amenities', selectedValues, false);
                    });
                    $('#non_negotiable_amenities').data('nna-change-bound', true);
                }
            }

            if ($('#property_style_select').length && !$('#property_style_select').hasClass('select2-hidden-accessible')) {
                $('#property_style_select').select2({ placeholder: 'Select', allowClear: true, width: '100%' });
                if (!$('#property_style_select').data('ps-change-bound')) {
                    $('#property_style_select').on('change', function() {
                        var selectedValue = $(this).val();
                        @this.set('property_items', selectedValue, false);
                    });
                    $('#property_style_select').data('ps-change-bound', true);
                }
            }

            if ($('#exchange_item').length) {
                var $exEl = $('#exchange_item');
                if (!$exEl.hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($exEl);
                }
                var savedExchangeItems = [];
                try { savedExchangeItems = JSON.parse($exEl.attr('data-selected') || '[]'); } catch(e) {}
                if (!savedExchangeItems.length) {
                    savedExchangeItems = @this.get('exchange_item') || [];
                }
                if (savedExchangeItems.length > 0) {
                    $exEl.val(savedExchangeItems).trigger('change.select2');
                }
                if (!$exEl.data('exchange-change-bound')) {
                    $exEl.on('change', function(e) {
                        var selectedValues = $(this).val() || [];
                        @this.set('exchange_item', selectedValues, false);
                    });
                    $exEl.data('exchange-change-bound', true);
                }
            }

            // Compatibility Select2 fields (single + multi) — centralized idempotent helper
            window.initCompatSelect2Fields();

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
                    if (_bathroomsDropdownHandler) {
                        bathroomsDropdown.removeEventListener('change', _bathroomsDropdownHandler);
                    }
                    _bathroomsDropdownHandler = function(e) { toggleOtherBathrooms(e.currentTarget); };
                    bathroomsDropdown.addEventListener('change', _bathroomsDropdownHandler);

                    // Manually trigger the toggle function on page load or after Livewire re-renders
                    toggleOtherBathrooms(bathroomsDropdown);
                }
            }

            // Attach the event listener initially
            attachBathroomsDropdownListener();





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
                if (garageOptions && garageOptions.value === "Other" && garageSelect.value === "Yes") {
                    otherInputWrapper.classList.remove('d-none'); // Show input field
                } else {
                    otherInputWrapper.classList.add('d-none'); // Hide input field
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

            if ($('#included_assets').length && !$('#included_assets').hasClass('select2-hidden-accessible')) {
                window.initFullServiceSelect2Multiple($('#included_assets'));
                $('#included_assets').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    @this.set('business_assets', selectedValues, false);
                    if (selectedValues && selectedValues.includes('Other')) {
                        $('.other_assets').show();
                    } else {
                        $('.other_assets').hide();
                    }
                });
                // Repopulate Select2 with any saved values from Livewire (edit/draft load)
                var _savedAssets = @this.get('business_assets') || [];
                if (_savedAssets.length > 0) {
                    $('#included_assets').val(_savedAssets).trigger('change.select2');
                }
                // Apply initial state if "Other" was already selected (e.g., on load)
                let _initAssets = $('#included_assets').val() || [];
                if (_initAssets.includes('Other')) {
                    $('.other_assets').show();
                } else {
                    $('.other_assets').hide();
                }
            }

            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                window.initFullServiceSelect2Multiple($('#view_preference'));
                $('#view_preference').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    @this.set('view_preference', selectedValues, false);
                    if (selectedValues.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                    }
                });
            }

            if ($('#appliances').length && !$('#appliances').hasClass('select2-hidden-accessible')) {
                window.initFullServiceSelect2Multiple($('#appliances'));
                $('#appliances').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    @this.set('appliances', selectedValues, false);
                    if (selectedValues && selectedValues.includes('Other')) {
                        $('#other_appliances').closest('.form-group').show();
                    } else {
                        $('#other_appliances').closest('.form-group').hide();
                    }
                });
            }

            if ($('#garage_parking_spaces_option_landlord').length && !$('#garage_parking_spaces_option_landlord').hasClass('select2-hidden-accessible')) {
                window.initFullServiceSelect2Multiple($('#garage_parking_spaces_option_landlord'));
                $('#garage_parking_spaces_option_landlord').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    @this.set('garage_parking_spaces_option', selectedValues, false);
                    const otherDiv = document.getElementById('other_garage_parking_spaces_option_landlord');
                    if (otherDiv) {
                        otherDiv.style.display = selectedValues.includes('Other') ? '' : 'none';
                    }
                });
            }

            // Function to toggle Non-Negotiable Amenities and Property Features:" input field

            function toggleOtherAmenities(selectElement) {
                const otherAmenitiesDiv = document.querySelector('.other_non_negotiable_amenities');

                if (!otherAmenitiesDiv) {
                    return;
                }
                var vals = $(selectElement).val() || [];
                if (vals.includes('Other')) {
                    otherAmenitiesDiv.classList.remove('d-none'); // Show the "Other" input field
                } else {
                    otherAmenitiesDiv.classList.add('d-none'); // Hide the "Other" input field
                }
            }
            // Delegate "Other" toggle for amenities — survives element replacement, no listener accumulation
            $(document).on('change', '#non_negotiable_amenities', function() {
                toggleOtherAmenities(this);
            });
            var _initNnaEl = document.getElementById('non_negotiable_amenities');
            if (_initNnaEl) { toggleOtherAmenities(_initNnaEl); }

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




            function toggleOtherPropertyItems(selectElement) {
                const otherConditionItemDiv = document.querySelector('.other_property_items');

                if (!otherConditionItemDiv) {
                    return;
                }

                // Show the "Other" input field if "Other" is selected
                if (selectElement.value === 'Other') {
                    otherConditionItemDiv.classList.remove('d-none'); // Show the "Other" input field
                } else {
                    otherConditionItemDiv.classList.add('d-none'); // Hide the "Other" input field
                }
            }

            // Function to attach the event listener to the condition dropdown
            function attachItemConditionDropdownListener() {
                const conditionDropdownItem = document.getElementById('property_items');
                if (conditionDropdownItem) {
                    conditionDropdownItem.addEventListener('change', function() {
                        toggleOtherPropertyItems(this);
                    });

                    // Manually trigger the toggle function on page load or after Livewire re-renders
                    toggleOtherPropertyItems(conditionDropdownItem);
                }
            }

            // Attach the event listener initially
            document.addEventListener('DOMContentLoaded', () => {
                attachItemConditionDropdownListener();
            });

            // Re-attach the event listener after Livewire re-renders the DOM
            Livewire.hook('message.processed', () => {
                attachItemConditionDropdownListener();
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

                if (file.size > 10 * 1024 * 1024) {
                    videoError.textContent =
                        'Video size must be under 10MB. For larger videos, please paste a link instead (e.g., YouTube or Vimeo).For privacy, you can set your video as unlisted on YouTube so only those with the link can view it.';
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


            // Helper function to check if element is visible (not hidden by d-none, display:none, collapse, etc.)
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
                    
                    if (el.getAttribute('aria-hidden') === 'true') {
                        return false;
                    }
                    
                    const style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                        return false;
                    }
                    
                    if (parseFloat(style.height) === 0 || parseFloat(style.maxHeight) === 0) {
                        return false;
                    }
                    
                    el = el.parentElement;
                }
                return true;
            }

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

                // Enable/disable save button (both CSS class and attribute)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    if (allValid) {
                        saveButton.classList.remove('disabled');
                        saveButton.removeAttribute('disabled');
                    } else {
                        saveButton.classList.add('disabled');
                        saveButton.setAttribute('disabled', 'disabled');
                    }
                }

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

                if (otherCheckbox && otherCheckbox.checked && (!otherTextarea || !hasOtherDescription)) {
                    isValid = false;
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'service-error error mt-2';
                    errorDiv.textContent = 'Please describe the additional services you require.';

                    if (otherTextarea) {
                        otherTextarea.classList.add('is-invalid');
                        const container = otherTextarea.closest('.mb-3') || otherTextarea.parentNode;
                        if (container) {
                            container.appendChild(errorDiv);
                        }
                    }
                }

                return isValid;
            }
            // MODIFY your existing next button click handler like this:
            document.querySelector('.wizard-step-next')?.addEventListener('click', function() {
                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                if (!currentTab) return;

                const currentTabContent = document.querySelector(currentTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                let isValid = true;

                // Validate all required fields in the current tab (your existing code)
                const requiredFields = currentTabContent.querySelectorAll(
                    'input[required], select[required], textarea[required]');
                if (requiredFields) {
                    requiredFields.forEach(function(input) {
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

                // Validate cities array (your existing code)
                const citiesContainer = currentTabContent.querySelector('.cities-container');
                if (citiesContainer) {
                    const cityBadges = citiesContainer.querySelectorAll('.badge');
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
                }

                // Validate counties array (your existing code)
                const countiesContainer = currentTabContent.querySelector('.counties-container');
                if (countiesContainer) {
                    const countyBadges = countiesContainer.querySelectorAll('.badge');
                    if (!countyBadges || countyBadges.length === 0) {
                        isValid = false;
                        const existingError = countiesContainer.parentNode.querySelector('.error');
                        if (!existingError) {
                            const countiesError = document.createElement('div');
                            countiesError.className = 'error';
                            countiesError.textContent = 'At least one county is required.';
                            countiesContainer.parentNode.insertBefore(countiesError, countiesContainer
                                .nextSibling);
                        }
                    } else {
                        const existingError = countiesContainer.parentNode.querySelector('.error');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                }

                // If all fields are valid, proceed to the next tab (your existing code)
                if (isValid) {
                    const nextTab = currentTab.parentElement?.nextElementSibling?.querySelector(
                        '.nav-link');
                    if (nextTab) {
                        const tabs = document.querySelectorAll('.nav-link');
                        if (tabs) {
                            const tabIndex = Array.from(tabs).indexOf(nextTab);
                            if (tabIndex !== -1) {
                                Livewire.emit('setActiveTab', tabIndex);
                                nextTab.click();
                            }
                        }
                    }
                }

                // Update save button state after validation (your existing code)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !isValid;
                }
            });

            // Handle back button click (new implementation that works with your code)
            document.querySelector('.wizard-step-back')?.addEventListener('click', function() {
                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                const prevTab = currentTab.parentElement.previousElementSibling?.querySelector('.nav-link');
                if (prevTab) {
                    Livewire.emit('setActiveTab', Array.from(document.querySelectorAll('.nav-link'))
                        .indexOf(prevTab));
                    prevTab.click();
                }
            });

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

        function initializeLimitedService() {

            // Helper function to check if element is visible (not hidden by d-none, display:none, collapse, etc.)
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
                    
                    if (el.getAttribute('aria-hidden') === 'true') {
                        return false;
                    }
                    
                    const style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                        return false;
                    }
                    
                    if (parseFloat(style.height) === 0 || parseFloat(style.maxHeight) === 0) {
                        return false;
                    }
                    
                    el = el.parentElement;
                }
                return true;
            }

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

                // Enable/disable save button (both CSS class and attribute)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    if (allValid) {
                        saveButton.classList.remove('disabled');
                        saveButton.removeAttribute('disabled');
                    } else {
                        saveButton.classList.add('disabled');
                        saveButton.setAttribute('disabled', 'disabled');
                    }
                }

                return allValid;
            }

            // MODIFY your existing next button click handler like this:
            document.querySelector('.wizard-step-next')?.addEventListener('click', function() {
                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                if (!currentTab) return;

                const currentTabContent = document.querySelector(currentTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                let isValid = true;

                // Validate all required fields in the current tab (your existing code)
                const requiredFields = currentTabContent.querySelectorAll(
                    'input[required], select[required], textarea[required]');
                if (requiredFields) {
                    requiredFields.forEach(function(input) {
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

                // Validate cities array (your existing code)
                const citiesContainer = currentTabContent.querySelector('.cities-container');
                if (citiesContainer) {
                    const cityBadges = citiesContainer.querySelectorAll('.badge');
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
                }

                // Validate counties array (your existing code)
                const countiesContainer = currentTabContent.querySelector('.counties-container');
                if (countiesContainer) {
                    const countyBadges = countiesContainer.querySelectorAll('.badge');
                    if (!countyBadges || countyBadges.length === 0) {
                        isValid = false;
                        const existingError = countiesContainer.parentNode.querySelector('.error');
                        if (!existingError) {
                            const countiesError = document.createElement('div');
                            countiesError.className = 'error';
                            countiesError.textContent = 'At least one county is required.';
                            countiesContainer.parentNode.insertBefore(countiesError, countiesContainer
                                .nextSibling);
                        }
                    } else {
                        const existingError = countiesContainer.parentNode.querySelector('.error');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
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
                    } else {
                        const existingError = understandTerms.parentNode.querySelector('.error');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                }

                // In your validation function



                // If all fields are valid, proceed to the next tab (your existing code)
                if (isValid) {
                    const nextTab = currentTab.parentElement?.nextElementSibling?.querySelector(
                        '.nav-link');
                    if (nextTab) {
                        const tabs = document.querySelectorAll('.nav-link');
                        if (tabs) {
                            const tabIndex = Array.from(tabs).indexOf(nextTab);
                            if (tabIndex !== -1) {
                                Livewire.emit('setActiveTab', tabIndex);
                                nextTab.click();
                            }
                        }
                    }
                }

                // Update save button state after validation (your existing code)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !isValid;
                }
            });

            // Handle back button click (new implementation that works with your code)
            document.querySelector('.wizard-step-back')?.addEventListener('click', function() {
                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                const prevTab = currentTab.parentElement.previousElementSibling?.querySelector('.nav-link');
                if (prevTab) {
                    Livewire.emit('setActiveTab', Array.from(document.querySelectorAll('.nav-link'))
                        .indexOf(prevTab));
                    prevTab.click();
                }
            });

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
                const parent = input.parentNode;
                if (!iconClass || !parent || !parent.classList || !parent.classList.contains('input-cover')) return;
                if (parent.querySelector(':scope > .input-icon')) return;
                const icon = document.createElement('i');
                icon.className = `input-icon ${iconClass}`;
                parent.insertBefore(icon, input);
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
            initializeMoneyInputs();
            addIconsToInputs();
            checkRepresentationStatus();

            // Re-detect selected service type after DOM update
            const fullServiceChecked = document.getElementById('fullService')?.checked;
            const limitedServiceChecked = document.getElementById('limitedService')?.checked;

            let newServiceType = null;
            if (fullServiceChecked) {
                newServiceType = 'full_service';
            } else if (limitedServiceChecked) {
                newServiceType = 'limited_service';
            } else {
                // Default to full service if no service type radio buttons found
                newServiceType = 'full_service';
            }

            if (newServiceType !== currentServiceType) {
                currentServiceType = newServiceType;
            }

            removeWizardEventListeners();

            var _now = Date.now();
            if (_now - _lastInitTime > 300) {
                _lastInitTime = _now;
                if (currentServiceType === 'full_service') {
                    initializeFullService();
                } else if (currentServiceType === 'limited_service') {
                    initializeLimitedService();
                }
            }

            // Re-sync any already-initialized Select2 multi-selects that Livewire
            // may have re-hydrated with different values (e.g., after draft load or
            // property-type change). rehydrateSelect2MultiFields() is idempotent —
            // it only acts on elements already marked select2-hidden-accessible.
            if (currentServiceType === 'full_service' && typeof rehydrateSelect2MultiFields === 'function') {
                rehydrateSelect2MultiFields();
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const saveButton = document.getElementById('save-button');
            const formContainer = document.getElementById('wizard-form-container');

            // Get all required fields using explicit ordered tab ID arrays — ensures correct
            // validation order and prevents accidental inclusion of non-wizard tab panes
            // (modals, other components).
            function getAllRequiredFields() {
                const requiredFields = [];
                const serviceType = formContainer.getAttribute('data-service-type');
                const userType = formContainer.getAttribute('data-user-type') || '';
                let tabIds;
                if (serviceType === 'full_service') {
                    tabIds = [
                        '#listing-details',
                        '#property-preferences',
                        '#sale-terms',
                        '#additional-details',
                    ];
                    if (userType === 'seller') {
                        tabIds.push('#representation-compatibility');
                    }
                    tabIds.push('#seller-information');
                } else {
                    tabIds = [
                        '#listing-details',
                        '#location-and-meeting-details',
                        '#service-selection-and-pricing',
                        '#information',
                    ];
                }
                tabIds.forEach(function(id) {
                    const tab = document.querySelector(id);
                    if (!tab) return;
                    tab.querySelectorAll('[required]').forEach(function(field) {
                        requiredFields.push(field);
                    });
                });
                return requiredFields;
            }

            function isFieldValid(field) {
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

                if (invalidFields.length > 0) {
                    invalidFields.forEach(item => {});
                    console.groupEnd();
                    return false;
                }
                return true;
            }

            function updateSaveButton() {
                const allValid = validateAllTabsStrictly();
                if (allValid) {
                    saveButton.classList.remove('disabled');
                    saveButton.removeAttribute('disabled');
                } else {
                    saveButton.classList.add('disabled');
                    saveButton.removeAttribute('disabled');
                }
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

            // Form submit listener: block submission and show banner when required fields are missing
            const createForm = document.getElementById('create-auction-form');
            if (createForm) {
                document.addEventListener('submit', function(e) {
                    if (!e.target || e.target.id !== 'create-auction-form') return;
                    const banner = document.getElementById('submit-error-banner');
                    const errorList = document.getElementById('submit-error-list');
                    if (banner) banner.classList.add('d-none');
                    if (errorList) errorList.innerHTML = '';

                    const requiredFields = getAllRequiredFields();
                    let invalidItems = [];

                    requiredFields.forEach(function(field) {
                        if (field.disabled || field.type === 'hidden') return;
                        if (!isFieldValid(field)) {
                            const tab = field.closest('.tab-pane');
                            const labelEl = field.closest('.form-group') && field.closest('.form-group').querySelector('label');
                            const fieldName = labelEl ? labelEl.textContent.replace(/[*:]/g, '').trim() : (field.getAttribute('placeholder') || field.name || field.id || 'Required field');
                            invalidItems.push({ field: field, tab: tab, fieldName: fieldName });
                        }
                    });

                    if (invalidItems.length > 0) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        if (banner && errorList) {
                            const seen = new Set();
                            invalidItems.forEach(function(item) {
                                if (!seen.has(item.fieldName)) {
                                    seen.add(item.fieldName);
                                    const li = document.createElement('li');
                                    li.textContent = item.fieldName;
                                    errorList.appendChild(li);
                                }
                            });
                            banner.querySelector('strong').textContent = 'Please complete the required fields before submitting.';
                            banner.classList.remove('d-none');
                        }

                        const firstItem = invalidItems[0];
                        if (firstItem.tab) {
                            const tabId = firstItem.tab.id;
                            const tabTrigger = document.querySelector('[data-bs-target="#' + tabId + '"], [href="#' + tabId + '"]');
                            if (tabTrigger) {
                                bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
                                const allTabPanes = [...document.querySelectorAll('.tab-pane')];
                                const tabIndex = allTabPanes.indexOf(firstItem.tab);
                                if (tabIndex >= 0 && typeof Livewire !== 'undefined') {
                                    Livewire.emit('setActiveTab', tabIndex);
                                }
                            }
                        }

                        setTimeout(function() {
                            if (firstItem.field && firstItem.field.classList) {
                                firstItem.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                if (typeof firstItem.field.focus === 'function' && firstItem.field.tagName !== 'DIV') {
                                    firstItem.field.focus();
                                }
                                firstItem.field.classList.add('is-invalid');
                            }
                            if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 300);

                        return false;
                    }

                    syncAllSelect2BeforeSave();
                    if (banner) banner.classList.add('d-none');
                }, true);
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var draftEl = document.getElementById('draftModal');
                if (draftEl) {
                    var draftModal = new bootstrap.Modal(draftEl);
                    draftModal.show();
                }
            }, 150);
        });
    </script>

    <script>
        // Global money input helper functions for comma-formatted currency inputs
        function getErrorEl(input) {
            const errorId = input.getAttribute('data-error-id');
            return errorId ? document.getElementById(errorId) : null;
        }

        function formatWithCommas(input) {
            let value = input.value.replace(/[^\d.]/g, '');
            let parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = parts.length > 1 ? parts[0] + '.' + parts[1].substring(0, 2) : parts[0];
        }

        function validateInput(input) {
            var el = input;
            var start  = el.selectionStart;
            var oldLen = el.value.length;
            var raw    = el.value.replace(/[^\d.]/g, '');
            var parts  = raw.split('.');
            var intPart = parts[0] || '';
            var decPart = parts.length > 1 ? parts[1] : null;
            if (intPart === '' && decPart === null) { el.value = ''; return; }
            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            var formatted = (decPart !== null) ? intPart + '.' + decPart.substring(0, 2) : intPart;
            el.value = formatted;
            var newLen = el.value.length;
            var newPos = Math.max(0, start + (newLen - oldLen));
            try { el.setSelectionRange(newPos, newPos); } catch(e) {}
        }

        function reformatNumber(input) {
            const errorEl = getErrorEl(input);
            let v = input.value.replace(/,/g, '');
            const parts = v.split('.');
            let intPart = parts[0] || '';
            let decPart = parts[1] || '';

            if (decPart) decPart = decPart.slice(0, 2);

            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = decPart ? `${intPart}.${decPart}` : intPart;

            errorEl && (errorEl.innerText = "");
        }

        function handlePaste(event) {
            event.preventDefault();
            const paste = (event.clipboardData || window.clipboardData).getData('text');
            let clean = paste.replace(/[^0-9.]/g, '');
            const parts = clean.split('.');
            if (parts.length > 2) {
                clean = parts[0] + '.' + parts.slice(1).join('');
            }
            let intPart = parts[0] || '';
            let decPart = parts[1] || '';
            if (decPart) decPart = decPart.slice(0, 2);
            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            event.target.value = decPart ? `${intPart}.${decPart}` : intPart;
            event.target.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // Re-initialize money formatting after Livewire updates.
        // Safe to call repeatedly — uses data-money-initialized guard to prevent
        // duplicate listener binding while always re-formatting the displayed value.
        function initializeMoneyInputs() {
            // All currency/money Livewire property names that need comma formatting.
            // Does NOT include percentage, phone, ZIP, year, or non-money fields.
            var moneyProps = [
                // Desired sale price
                'maximum_budget',
                // Sale / Financing terms
                'purchase_price',
                'down_payment_amount',
                'seller_down_payment_amount',
                'seller_financing_amount',
                'seller_late_fee_amount',
                'prepayment_penalty_amount',
                'balloon_payment_amount',
                'outstanding_balance',
                'assumable_monthly_escrow',
                'assumable_fee_amount',
                'max_monthly_payment',
                'gap_payment_amount',
                // Exchange / Trade
                'exchange_item_value',
                'additional_cash',
                // Lease Option / Lease Purchase
                'lease_option_price',
                'lease_option_payment',
                'option_fee_amount',
                'lease_purchase_price',
                'lease_purchase_payment',
                'lease_purchase_option_fee_amount',
                'seller_lease_purchase_rent_credit_amount',
                'seller_lease_purchase_deposit',
                // Seller Purchase Terms deposits
                'initial_deposit_requested',
                'additional_deposit_requested',
                // Assignment / contract terms
                'assignment_fee_amount',
                // Rental income (multi-family / unit-type)
                'expected_rent',
                // Broker compensation flat-fee fields
                // (these also carry inline oninput=validateInput for live typing;
                //  we still format them on load/re-render via initializeMoneyInputs)
                'purchase_fee_flat',
                'purchase_fee_flat_combo',
                'commission_structure_type_fee_flat',
                'commission_structure_type_fee_flat_combo',
                'nominal',
                'seller_leasing_gross_purchase_fee_flat_amount',
                'seller_leasing_gross_flat_combo',
                'seller_leasing_gross_flat_net_combo',
                'early_termination_fee_amount',
                'retainer_fee_amount',
            ];

            moneyProps.forEach(function(prop) {
                // Collect all visible inputs bound to this property (handles wire:model variants)
                var inputs = Array.from(document.querySelectorAll('input:not([type="hidden"])'))
                    .filter(function(el) {
                        var m = el.getAttribute('wire:model') ||
                                el.getAttribute('wire:model.lazy') ||
                                el.getAttribute('wire:model.defer') ||
                                el.getAttribute('wire:model.live') ||
                                el.getAttribute('wire:model.debounce');
                        return m === prop;
                    });

                inputs.forEach(function(input) {
                    // Always re-format on each call (formatWithCommas strips then re-adds
                    // commas so it is idempotent; empty values are left untouched)
                    if (input.value !== '') {
                        formatWithCommas(input);
                    }

                    // Guard: only bind the live input listener once per element instance
                    if (input.getAttribute('data-money-initialized')) return;
                    input.setAttribute('data-money-initialized', '1');

                    // If the field already has an inline oninput handler (e.g., validateInput)
                    // it handles live formatting itself — skip binding a second listener
                    if (input.getAttribute('oninput')) return;

                    input.addEventListener('input', function() {
                        var el = this;
                        var start  = el.selectionStart;
                        var oldLen = el.value.length;

                        // Strip everything except digits and a single decimal point
                        var raw = el.value.replace(/[^\d.]/g, '');
                        var parts = raw.split('.');
                        var intPart = parts[0] || '';
                        var decPart = parts.length > 1 ? parts[1] : null;

                        // Leave empty fields blank — never substitute 0
                        if (intPart === '' && decPart === null) {
                            el.value = '';
                            return;
                        }

                        // Insert thousand-separator commas
                        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        var formatted = (decPart !== null)
                            ? intPart + '.' + decPart.substring(0, 2)
                            : intPart;

                        el.value = formatted;

                        // Restore caret, offset by the net change in string length
                        var newLen = el.value.length;
                        var newPos = Math.max(0, start + (newLen - oldLen));
                        try { el.setSelectionRange(newPos, newPos); } catch(e) {}
                    });
                });
            });

            // lease_value and purchase_value — identified by wire:key since they are
            // rendered conditionally with a dynamic key suffix ("-flat" / "-percent")
            var leaseInput = document.querySelector('[wire\\:key^="lease-value-input-flat"]');
            if (leaseInput && leaseInput.value) formatWithCommas(leaseInput);

            var purchaseInput = document.querySelector('[wire\\:key^="purchase-value-input-flat"]');
            if (purchaseInput && purchaseInput.value) formatWithCommas(purchaseInput);
        }

        // Initialize on page load (covers fresh create pages where values are in the HTML)
        document.addEventListener('DOMContentLoaded', function() {
            initializeMoneyInputs();
        });

        // Initialize after Livewire has finished its initial JS hydration.
        // This covers draft/edit pages where Livewire may update input values
        // during component boot, after DOMContentLoaded has already fired.
        window.addEventListener('livewire:load', function() {
            initializeMoneyInputs();
        });

    </script>
@endpush
