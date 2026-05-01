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

        @media (max-width: 768px) {
            .status-text {
                font-size: 0.9rem;
            }

            .status-icon {
                margin-right: 0.3rem;
                font-size: 1rem;
            }
        }

        /* Agent type selection button text styling */
        .user-selected {
            color: #0ce7ef;
            font-weight: 600;
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
        ['name' => 'Furniture, Fixtures, and Equipment (as per attached inventory)'],
        ['name' => 'Advertising Materials'],
        ['name' => 'Contract Rights'],
        ['name' => 'Leases'],
        ['name' => 'Licenses'],
        ['name' => 'Rights under any Agreement for Interests'],

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

    $purchasing_props = [['name' => 'Yes'], ['name' => 'No']];

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

    $occupant_types = [['name' => 'Tenant'], ['name' => 'Vacant'], ['name' => 'Occupied']];
    $desired_rental_amount = [['name' => 'Tenant'], ['name' => 'Vacant'], ['name' => 'Occupied']];

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
        // ['name' => 'Other'],
    ];

    $termLease = [
        ['name' => 'Absolute (Triple) Net'],
        ['name' => 'Gross Lease'],
        ['name' => 'Gross Percentages'],
        ['name' => 'Ground Lease'],
        ['name' => 'Lease Option'],
        ['name' => 'Modified Gross'],
        ['name' => 'Net Lease'],
        ['name' => 'Net Net'],
        ['name' => 'Other'],
        ['name' => 'Pass Throughs'],
        ['name' => 'Purchase Option'],
        ['name' => 'Renewal Option'],
        ['name' => 'Sale-Leaseback'],
        ['name' => 'Seasonal'],
        ['name' => 'Special Available (CLO)'],
        ['name' => 'Varied Terms'],
        // ['name' => 'Other', 'target' => '.other_terms_lease'],
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
        ['name' => 'None '],
        // ['name' => 'Other', 'target' => '.otherTenantPays'],
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
        // ['name' => 'Other', 'target' => '.otherOwnerPays'],
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

                <div id="wizard-form-container" class="container pt-5 pb-5" data-service-type="{{ $service_type }}">

                    <form id="create-auction-form" wire:submit.prevent="store" novalidate>
                        <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                            <strong>Please complete the required fields before submitting.</strong>
                            <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                        </div>
                        <!-- Tab Navigation -->

                        @if ($service_type === 'full_service')
                            @php $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent'; @endphp

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (['Listing Details', 'Property Details', 'Leasing Terms', 'Additional Details'] as $index => $tab)
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
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                        wire:click="setActiveTab(4)"
                                        id="tax-legal-hoa-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#tax-legal-hoa-disclosures"
                                        type="button" role="tab"
                                        aria-controls="tax-legal-hoa-disclosures"
                                        aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                        Tax, Legal, HOA &amp; Disclosures
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 5 ? 'active' : '' }}"
                                        wire:click="setActiveTab(5)"
                                        id="photos-tours-documents-tab" data-bs-toggle="tab"
                                        data-bs-target="#photos-tours-documents"
                                        type="button" role="tab"
                                        aria-controls="photos-tours-documents"
                                        aria-selected="{{ $activeTab === 5 ? 'true' : 'false' }}">
                                        Photos, Tours &amp; Documents
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 6 ? 'active' : '' }}"
                                        wire:click="setActiveTab(6)"
                                        id="landlord-information-tab" data-bs-toggle="tab"
                                        data-bs-target="#landlord-information"
                                        type="button" role="tab"
                                        aria-controls="landlord-information"
                                        aria-selected="{{ $activeTab === 6 ? 'true' : 'false' }}">
                                        Agent Credentials & Contact Info
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 7 ? 'active' : '' }}"
                                        wire:click="setActiveTab(7)"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === 7 ? 'true' : 'false' }}">
                                        AI Questions
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

                                <!-- Photos, Tours & Documents Tab (limited_service: index 4) -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                        wire:click="setActiveTab(4)"
                                        id="photos-tours-documents-ls-tab" data-bs-toggle="tab"
                                        data-bs-target="#photos-tours-documents-ls"
                                        type="button" role="tab"
                                        aria-controls="photos-tours-documents-ls"
                                        aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                        Photos, Tours &amp; Documents
                                    </button>
                                </li>

                                <!-- Dynamic Information Tab Based on User Type -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 5 ? 'active' : '' }}"
                                        wire:click="setActiveTab(5)" id="information-tab" data-bs-toggle="tab"
                                        data-bs-target="#information" type="button" role="tab"
                                        aria-controls="information"
                                        aria-selected="{{ $activeTab === 5 ? 'true' : 'false' }}">
                                        Agent Credentials & Contact Info
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 6 ? 'active' : '' }}"
                                        wire:click="setActiveTab(6)"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === 6 ? 'true' : 'false' }}">
                                        AI Questions
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
                                    id="property-preferences" role="tabpanel"
                                    aria-labelledby="property-preferences-tab">

                                    @if ($user_type === 'tenant')
                                        @include('livewire.offer-listing.offer-tenant-tabs.commission-based.property-details')
                                    @elseif($user_type === 'seller')
                                        @include('livewire.offer-listing.offer-seller-tabs.commission-based.property-preferences')
                                    @elseif($user_type === 'buyer')
                                        @include('livewire.offer-listing.offer-buyer-tabs.commission-based.property-preferences')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.property-preferences')
                                    @endif
                                </div>

                                <!-- Leasing Terms Tab -->
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                                    id="leasing-terms" role="tabpanel" aria-labelledby="leasing-terms-tab">

                                    @if ($user_type === 'tenant')
                                        @include('livewire.offer-listing.offer-tenant-tabs.commission-based.leasing-terms')
                                    @elseif($user_type === 'seller')
                                        @include('livewire.offer-listing.offer-seller-tabs.commission-based.purchasing-terms')
                                    @elseif($user_type === 'buyer')
                                        @include('livewire.offer-listing.offer-buyer-tabs.commission-based.purchasing-terms')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.lease-terms')
                                    @endif
                                </div>

                                <!-- Additional Details Tab -->
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}"
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

                                <!-- Tax, Legal, HOA & Disclosures Tab (full_service: index 4) -->
                                <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}"
                                    id="tax-legal-hoa-disclosures" role="tabpanel" aria-labelledby="tax-legal-hoa-disclosures-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.tax-legal-hoa-disclosures')
                                </div>

                                <!-- Photos, Tours & Documents Tab (full_service: index 5) -->
                                <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}"
                                    id="photos-tours-documents" role="tabpanel" aria-labelledby="photos-tours-documents-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.photos-tours-documents')
                                </div>

                                <!-- Landlord Info Tab (full_service: index 6) -->
                                <div class="tab-pane fade {{ $activeTab === 6 ? 'show active' : '' }}"
                                    id="landlord-information" role="tabpanel" aria-labelledby="landlord-information-tab">
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

                                <!-- AI Questions Tab (full_service: index 7) -->
                                <div class="tab-pane fade {{ $activeTab === 7 ? 'show active' : '' }}" id="ai-questions"
                                    role="tabpanel" aria-labelledby="ai-questions-tab">
                                    @include('livewire.offer-listing.shared.ai-questions-input')
                                </div>
                            @elseif($service_type === 'limited_service')
                                <!-- Photos, Tours & Documents Tab (limited_service: index 4) -->
                                <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}" id="photos-tours-documents-ls"
                                    role="tabpanel" aria-labelledby="photos-tours-documents-ls-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.photos-tours-documents')
                                </div>

                                <!-- Information Tab (limited_service: index 5) -->
                                <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}" id="information"
                                    role="tabpanel" aria-labelledby="information-tab">
                                    @if(auth()->user() && auth()->user()->user_type === 'agent')
                                        @include('livewire.partials.agent-credentials')
                                    @else
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.landlord-info')
                                    @endif
                                </div>

                                <!-- AI Questions Tab (limited_service: index 6) -->
                                <div class="tab-pane fade {{ $activeTab === 6 ? 'show active' : '' }}" id="ai-questions"
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
                                <button type="button" class="btn btn-outline-primary me-2" onclick="syncLandlordSelect2BeforeSave()" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                    <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving...</span>
                                </button>
                                <button type="button" class="btn btn-primary wizard-step-next">Next</button>
                                <button type="submit" class="btn btn-success wizard-step-finish disabled"
                                    id="save-button" wire:loading.attr="disabled" wire:target="store"
                                    onclick="syncLandlordSelect2BeforeSave()">
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
    <script>
        let currentServiceType = null;
        let _bathroomsDropdownHandler = null;

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
                        if (lwValue === null || lwValue === undefined) return;

                        if (select.multiple) {
                            const values = Array.isArray(lwValue) ? lwValue : [lwValue];
                            if (values.length > 0) {
                                console.log('[SyncSelect] Syncing multi-select ' + wireModel + ' with ' + values.length + ' values');
                                $(select).val(values).trigger('change');
                            }
                        } else {
                            if (lwValue && select.value !== lwValue) {
                                console.log('[SyncSelect] Syncing ' + wireModel + ': DOM="' + select.value + '" -> LW="' + lwValue + '"');
                                select.value = lwValue;
                            }
                        }
                    } catch (e) {}
                }
            });
        }
        
        window.addEventListener('draftLoaded', function(event) {
            console.log('[DraftLoaded] Event received - syncing select values', event.detail);
            setTimeout(function() {
                var data = event.detail || {};
                var select2Fields = {
                    'appliances': data.appliances || [],
                    'offered_financing': data.offered_financing || [],
                    'tenant_pays': data.tenant_pays || [],
                    'owner_pays': data.owner_pays || [],
                    'terms_of_lease': data.terms_of_lease || [],
                    'rent_includes': data.rent_includes || [],
                    'desired_lease_length': data.desired_lease_length || [],
                    'view_preference': data.view_preference || [],
                    'non_negotiable_amenities': data.non_negotiable_amenities || [],
                    'lease_for': data.lease_for || [],
                    'credit_scroe_rating': data.credit_scroe_rating || [],
                    'services': data.services || [],
                    'pool_type': data.pool_type || [],
                    'photo_enhancements': data.photo_enhancements || [],
                    'property_items': data.property_items || [],
                    'tenant_require': data.tenant_require || [],
                };
                // MLS Property Detail — hydrate by element ID
                var mlsIdFields = {
                    'heating_fuel': data.heating_fuel || [],
                    'air_conditioning': data.air_conditioning || [],
                    'water': data.water || [],
                    'sewer': data.sewer || [],
                    'property_utilities': data.property_utilities || [],
                    'laundry_features': data.laundry_features || [],
                    'floor_covering': data.floor_covering || [],
                    'security_features': data.security_features || [],
                    'road_surface_type': data.road_surface_type || [],
                    'electrical_service': data.electrical_service || [],
                    'building_features': data.building_features || [],
                    'space_type': data.space_type || [],
                    'space_classification': data.space_classification || [],
                };
                Object.keys(mlsIdFields).forEach(function(fieldId) {
                    var values = mlsIdFields[fieldId];
                    if (values && values.length > 0) {
                        var $el = $('#' + fieldId);
                        if ($el.length && $el.hasClass('select2-multiple')) {
                            $el.val(values).trigger('change');
                        }
                    }
                });
                Object.keys(select2Fields).forEach(function(fieldName) {
                    var values = select2Fields[fieldName];
                    if (values && values.length > 0) {
                        var selectEl = document.querySelector('select[wire\\:model="' + fieldName + '"]');
                        if (selectEl && $(selectEl).hasClass('select2-multiple')) {
                            console.log('[DraftLoaded] Hydrating Select2: ' + fieldName + ' with ' + values.length + ' values');
                            $(selectEl).val(values).trigger('change');
                        }
                    }
                });
                syncSelectValues();
                if (typeof window.updateSaveButton === 'function') {
                    window.updateSaveButton();
                }
                try {
                    setTimeout(function() {
                        @this.call('finishDraftLoad');
                        console.log('[DraftLoaded] Called finishDraftLoad - updated* hooks re-enabled');
                    }, 500);
                } catch (e) {
                    console.error('[DraftLoaded] Error calling finishDraftLoad, forcing clear:', e);
                    @this.set('isLoadingDraft', false);
                }
            }, 200);
        });

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


            if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
                $('#property_items').select2({
                    placeholder: "Select property style",
                    allowClear: true,
                });
                $('#property_items').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('property_items', selectedValues);
                });
            }

            if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                $('#non_negotiable_amenities').select2({
                    placeholder: "Select",
                    allowClear: true,
                });
                $('#non_negotiable_amenities').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('non_negotiable_amenities', selectedValues);
                });
            }

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
                setTimeout(() => {
                    attachBathroomsDropdownListener();
                    toggleSpaceInput('carport-needed', 'other-carport-needed');
                    toggleSpaceInput('garage-needed', 'other-garage-needed');
                }, 150);
            });

            function initViewPreferenceSelect2() {
                if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                    $('#view_preference').select2({
                        placeholder: "Select",
                        allowClear: true
                    });
                    $('#view_preference').on('change', function() {
                        let selectedValues = $(this).val();
                        Livewire.emit('updatePreference', selectedValues);
                        if (selectedValues.includes('Other')) {
                            $('#other_preferences').show();
                        } else {
                            $('#other_preferences').hide();
                        }
                    });
                }
            }
            initViewPreferenceSelect2();

            // MLS Property Detail — multi-select fields with Other-toggle
            var mlsMultiSelects = [
                { id: 'heating_fuel',      field: 'heating_fuel',      placeholder: 'Select heating / fuel type(s)',   otherId: 'other_heating_fuel_wrapper' },
                { id: 'air_conditioning',  field: 'air_conditioning',  placeholder: 'Select air conditioning type(s)', otherId: 'other_air_conditioning_wrapper' },
                { id: 'water',             field: 'water',             placeholder: 'Select water source(s)' },
                { id: 'sewer',             field: 'sewer',             placeholder: 'Select sewer type(s)',            otherId: 'other_sewer_wrapper' },
                { id: 'property_utilities',field: 'property_utilities',placeholder: 'Select utilities',                otherId: 'other_property_utilities_wrapper' },
                { id: 'laundry_features',  field: 'laundry_features',  placeholder: 'Select laundry features',        otherId: 'other_laundry_features_wrapper' },
                { id: 'floor_covering',    field: 'floor_covering',    placeholder: 'Select floor covering',          otherId: 'other_floor_covering_wrapper' },
                { id: 'security_features', field: 'security_features', placeholder: 'Select security features' },
                { id: 'road_surface_type', field: 'road_surface_type', placeholder: 'Select road surface type(s)',    otherId: 'other_road_surface_type_wrapper' },
                { id: 'electrical_service',field: 'electrical_service',placeholder: 'Select electrical service',      otherId: 'other_electrical_service_wrapper' },
                { id: 'building_features', field: 'building_features', placeholder: 'Select building features',       otherId: 'other_building_features_wrapper' },
                { id: 'space_type',           field: 'space_type',           placeholder: 'Select space type(s)' },
                { id: 'space_classification', field: 'space_classification', placeholder: 'Select space classification' },
            ];
            function initMlsMultiSelects() {
                mlsMultiSelects.forEach(function(cfg) {
                    var $el = $('#' + cfg.id);
                    if ($el.length && !$el.hasClass('select2-hidden-accessible')) {
                        $el.select2({ placeholder: cfg.placeholder, allowClear: true });
                        $el.on('change', function() {
                            var vals = $(this).val() || [];
                            @this.set(cfg.field, vals);
                            if (cfg.otherId) {
                                var $wrapper = $('#' + cfg.otherId);
                                if ($wrapper.length) {
                                    $wrapper.css('display', vals.includes('Other') ? 'block' : 'none');
                                }
                            }
                        });
                    }
                });
            }
            initMlsMultiSelects();
            Livewire.hook('message.processed', () => {
                setTimeout(function() {
                    initMlsMultiSelects();
                    initViewPreferenceSelect2();
                    initAppliancesSelect2();
                }, 150);
            });

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
            // Function to attach the event listener to the bathrooms dropdown
            function attachAmenitiesDropdownListener() {
                const bathroomsDropdown = document.getElementById('non_negotiable_amenities');
                if (bathroomsDropdown) {
                    bathroomsDropdown.addEventListener('change', function() {
                        toggleOtherAmenities(this);
                    });

                    // Manually trigger the toggle function on page load or after Livewire re-renders
                    toggleOtherAmenities(bathroomsDropdown);
                }
            }

            // Attach the event listener initially
            attachAmenitiesDropdownListener();

            // Re-attach the event listener after Livewire re-renders the DOM
            Livewire.hook('message.processed', () => {
                attachAmenitiesDropdownListener();
            });

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

                var vals = $(selectElement).val() || [];
                if (vals.includes('Other')) {
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



            function toggleOccupantTypesTenant(selectElement) {
                const tenantDateDiv = document.querySelector('.occupant_types_tenant');

                if (!tenantDateDiv) {
                    return;
                }

                // Show the date input field if "Tenant" is selected
                if (selectElement.value === 'Tenant') {
                    tenantDateDiv.classList.remove('d-none'); // Show the date input
                } else {
                    tenantDateDiv.classList.add('d-none'); // Hide the date input
                }
            }

            // Function to attach the event listener to the occupant types dropdown
            function attachOccupantTypesDropdownListener() {
                const occupantTypesSelect = document.getElementById('occupant_types');
                if (occupantTypesSelect) {
                    occupantTypesSelect.addEventListener('change', function() {
                        toggleOccupantTypesTenant(this);
                    });

                    // Manually trigger the toggle function on page load or after Livewire re-renders
                    toggleOccupantTypesTenant(occupantTypesSelect);
                }
            }

            // Attach the event listener initially
            document.addEventListener('DOMContentLoaded', () => {
                attachOccupantTypesDropdownListener();
            });

            // Re-attach the event listener after Livewire re-renders the DOM
            Livewire.hook('message.processed', () => {
                attachOccupantTypesDropdownListener();
            });


            ////////////        Desired Rental Amount


            function toggleDesiredRentalAmountTenant(selectElement) {
                const tenantDesiredRentalDiv = document.querySelector('.desired_rental_amount_tenant');

                if (!tenantDesiredRentalDiv) {
                    return;
                }

                // Show the date input field if "Tenant" is selected
                if (selectElement.value === 'Tenant') {
                    tenantDesiredRentalDiv.classList.remove('d-none'); // Show the date input
                } else {
                    tenantDesiredRentalDiv.classList.add('d-none'); // Hide the date input
                }
            }

            // Function to attach the event listener to the occupant types dropdown
            function attachDesiredRentalAmountDropdownListener() {
                const desiredRentalSelect = document.getElementById('desired_rental_amount');
                if (desiredRentalSelect) {
                    desiredRentalSelect.addEventListener('change', function() {
                        toggleDesiredRentalAmountTenant(this);
                    });

                    // Manually trigger the toggle function on page load or after Livewire re-renders
                    toggleDesiredRentalAmountTenant(desiredRentalSelect);
                }
            }

            // Attach the event listener initially
            document.addEventListener('DOMContentLoaded', () => {
                attachDesiredRentalAmountDropdownListener();
            });

            // Re-attach the event listener after Livewire re-renders the DOM
            Livewire.hook('message.processed', () => {
                attachDesiredRentalAmountDropdownListener();
            });


            function initAppliancesSelect2() {
                if ($('#appliances').length && !$('#appliances').hasClass('select2-hidden-accessible')) {
                    $('#appliances').select2({
                        placeholder: "Select",
                        allowClear: true,
                    });
                    $('#appliances').on('change', function(e) {
                        let selectedValues = $(this).val() || [];
                        @this.set('appliances', selectedValues);
                        @this.call('updateAppliances', selectedValues);
                    });
                }
            }
            initAppliancesSelect2();

            if ($('#rent_includes').length && !$('#rent_includes').hasClass('select2-hidden-accessible')) {
                $('#rent_includes').select2({
                    placeholder: "Select rent",
                    allowClear: true,
                });
                $('#rent_includes').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('rent_includes', selectedValues);
                    @this.call('updateRentIncludes', selectedValues);
                });
            }

            if ($('#terms_of_lease').length && !$('#terms_of_lease').hasClass('select2-hidden-accessible')) {
                $('#terms_of_lease').select2({
                    placeholder: "Select terms of lease",
                    allowClear: true,
                });
                $('#terms_of_lease').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('terms_of_lease', selectedValues);
                    var container = document.getElementById('otherLeaseContainer');
                    if (container) {
                        if (selectedValues.includes('Other')) {
                            container.classList.remove('d-none');
                        } else {
                            container.classList.add('d-none');
                        }
                    }
                });
            }

            if ($('#tenant_pays').length && !$('#tenant_pays').hasClass('select2-hidden-accessible')) {
                $('#tenant_pays').select2({
                    placeholder: "Select tenant pays",
                    allowClear: true,
                });
                $('#tenant_pays').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('tenant_pays', selectedValues);
                    @this.call('updateTenantPays', selectedValues);
                });
            }

            if ($('#owner_pays').length && !$('#owner_pays').hasClass('select2-hidden-accessible')) {
                $('#owner_pays').select2({
                    placeholder: "Select owner pays",
                    allowClear: true,
                });
                $('#owner_pays').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('owner_pays', selectedValues);
                    @this.call('updateOwnerPays', selectedValues);
                });
            }

            function initLeaseTermSelect2() {
                var $dlt = $('.lease_term_options');
                if (!$dlt.length || $dlt.hasClass('select2-hidden-accessible')) return;
                $dlt.select2({
                    placeholder: "Select desired lease term",
                    allowClear: true,
                });
                $dlt.off('change.ltsSync').on('change.ltsSync', function() {
                    var selectedValues = $(this).val() || [];
                    @this.set('desired_lease_length', selectedValues);
                    var otherWrapper = document.querySelector('.other_lease_term_wrapper');
                    if (otherWrapper) {
                        otherWrapper.style.display = selectedValues.includes('Other') ? 'block' : 'none';
                    }
                });
            }

            initLeaseTermSelect2();

            Livewire.hook('message.processed', () => {
                initLeaseTermSelect2();
            });

            window.syncLandlordSelect2BeforeSave = function() {
                var selects2Map = {
                    'appliances': '#appliances',
                    'rent_includes': '#rent_includes',
                    'terms_of_lease': '#terms_of_lease',
                    'tenant_pays': '#tenant_pays',
                    'owner_pays': '#owner_pays',
                    'non_negotiable_amenities': '#non_negotiable_amenities',
                    'property_items': '#property_items',
                    'view_preference': '#view_preference',
                    'heating_fuel': '#heating_fuel',
                    'air_conditioning': '#air_conditioning',
                    'water': '#water',
                    'sewer': '#sewer',
                    'property_utilities': '#property_utilities',
                    'laundry_features': '#laundry_features',
                    'floor_covering': '#floor_covering',
                    'security_features': '#security_features',
                    'road_surface_type': '#road_surface_type',
                    'electrical_service': '#electrical_service',
                    'building_features': '#building_features',
                    'space_type': '#space_type',
                    'space_classification': '#space_classification',
                    'association_fee_includes': '#association_fee_includes',
                    'association_amenities': '#association_amenities',
                    'additional_documents': '#additional_documents',
                };
                Object.entries(selects2Map).forEach(function([field, selector]) {
                    var $el = $(selector);
                    if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                        @this.set(field, $el.val() || []);
                    }
                });
                var $dlt = $('.lease_term_options');
                if ($dlt.length && $dlt.hasClass('select2-hidden-accessible')) {
                    @this.set('desired_lease_length', $dlt.val() || []);
                }
            };

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

                // ADD THIS: Validate services tab if it's the current tab
                if (currentTabContent.id === 'services') {
                    isValid = isValid && validateServicesTab(currentTabContent);
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
                    saveButton.classList.toggle('disabled', !isValid);
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

            // Run initial validity check now (already inside DOMContentLoaded when called)
            setTimeout(function() {
                checkFormValidity();
                document.querySelectorAll('input, select, textarea').forEach(function(field) {
                    field.addEventListener('change', checkFormValidity);
                    field.addEventListener('keyup', checkFormValidity);
                });
            }, 150);

            Livewire.hook('message.processed', function() {
                setTimeout(checkFormValidity, 150);
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
                    saveButton.classList.toggle('disabled', !isValid);
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

            // Run initial validity check now (already inside DOMContentLoaded when called)
            setTimeout(function() {
                checkFormValidity();
                document.querySelectorAll('input, select, textarea').forEach(function(field) {
                    field.addEventListener('change', checkFormValidity);
                    field.addEventListener('keyup', checkFormValidity);
                });
            }, 150);

            Livewire.hook('message.processed', function() {
                setTimeout(checkFormValidity, 150);
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
                if (isRepresented) {
                    saveBtn.disabled = true;
                    saveBtn.classList.add('disabled');
                }
                // When NOT represented, let updateSaveButton() control the button state
            }
        }

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
            }

            removeWizardEventListeners();

            if (currentServiceType === 'full_service') {
                initializeFullService();
            } else if (currentServiceType === 'limited_service') {
                initializeLimitedService();
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
                    '#leasing-terms',
                    '#services',
                    '#additional-details',
                    '#broker-compensation',
                    '#landlord-information'
                ] : [
                    '#listing-details',
                    '#location-and-meeting-details',
                    '#service-selection-and-pricing',
                    '#landlord-information'
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
                if (field.type === 'checkbox' || field.type === 'radio') {
                    return field.checked;
                }
                if (field.type === 'select-one' || field.type === 'select-multiple') {
                    return field.value !== '' && field.value !== null;
                }
                return field.value.trim() !== '';
            }

            function isFieldVisibleAndEnabled(field) {
                if (!field) return false;
                if (field.disabled) return false;
                if (field.getAttribute('aria-hidden') === 'true') return false;
                if (field.offsetWidth === 0 && field.offsetHeight === 0) return false;
                let el = field.parentElement;
                while (el && el !== document.body) {
                    const style = window.getComputedStyle(el);
                    if (style.display === 'none' || el.classList.contains('d-none')) return false;
                    el = el.parentElement;
                }
                return true;
            }

            function validateAllTabsStrictly() {
                const requiredFields = getAllRequiredFields();
                let invalidFields = [];

                requiredFields.forEach(field => {
                    if (!isFieldVisibleAndEnabled(field)) return;
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

            // Re-run validation whenever a Bootstrap tab becomes visible
            // (covers both Next-button navigation and direct tab-link clicks)
            document.addEventListener('shown.bs.tab', function() {
                setTimeout(updateSaveButton, 100);
            });

            // ---- LANDLORD GUIDED CORRECTION MODE ----------------------------------------

            // Stable label map: property name -> exact user-facing label shown in the UI.
            var LANDLORD_FIELD_LABELS = {
                'listing_title':           'Listing Title',
                'working_with_agent':      'Currently Working with an Agent',
                'desired_agent_hire_date': 'Desired Agent Hire Date',
                'listing_date':            'Listing Date',
                'expiration_date':         'Expiration Date',
                'auction_type':            'Listing Type',
                'auction_time':            'Bidding Period Length',
                'address':                 'Street Address',
                'property_city':           'Property City',
                'property_state':          'Property State',
                'property_county':         'Property County',
                'property_zip':            'Property Zip Code',
                'property_type':           'Property Type',
                'property_items':          'Property Style',
                'condition_prop':          'Property Condition',
                'bedrooms':                'Minimum Bedrooms',
                'bathrooms':               'Minimum Bathrooms',
                'minimum_heated_square':   'Minimum Heated SqFt',
                'minimum_leaseable':       'Net Leasable SqFt',
                'occupant_status':         'Current Occupant Status',
                'occupant_tenant':         'Occupied Until',
                'leasing_spaces':          'Leasing Space Type',
                'desired_lease_length':    'Desired Lease Term',
                'meeting_Preference':      'Meeting Preference',
                'first_name':              'First Name',
                'last_name':               'Last Name',
                'phone_number':            'Phone Number',
                'email':                   'Email Address',
            };

            // Returns the canonical deduplication key for a DOM field.
            function resolveLandlordFieldKey(field) {
                var wm = field.getAttribute('wire:model') ||
                         field.getAttribute('wire:model.defer') ||
                         field.getAttribute('wire:model.lazy') ||
                         field.getAttribute('wire:model.live') ||
                         field.getAttribute('wire:model.debounce.300ms');
                if (wm) {
                    var base = wm.split('.')[0];
                    return LANDLORD_FIELD_LABELS[base] ? base : (field.id || base);
                }
                return field.id || field.name || 'field';
            }

            // Returns the exact user-facing label for a field.
            function resolveLandlordFieldLabel(field) {
                var key = resolveLandlordFieldKey(field);
                if (LANDLORD_FIELD_LABELS[key]) return LANDLORD_FIELD_LABELS[key];
                var labelEl = field.closest('.form-group') && field.closest('.form-group').querySelector('label');
                return labelEl ? labelEl.textContent.replace(/[*:]/g, '').trim()
                               : (field.getAttribute('placeholder') || key || 'Required field');
            }

            // Returns true when a field is hidden by a d-none or display:none ancestor
            // within its tab pane (i.e. conditionally hidden, not just on an inactive tab).
            function isLandlordFieldHiddenWithinTab(field) {
                var tabPane = field.closest('.tab-pane');
                if (!tabPane) return false;
                var el = field.parentElement;
                while (el && el !== tabPane) {
                    if (el.classList && el.classList.contains('d-none')) return true;
                    if (el.style && el.style.display === 'none') return true;
                    el = el.parentElement;
                }
                return false;
            }

            // Collects all currently-missing required Landlord fields across every tab.
            function landlordGetInvalidItems() {
                var items = [];
                var seen  = new Set();

                // DOM-based required fields (respects conditional visibility)
                var reqFields = getAllRequiredFields();
                reqFields.forEach(function(field) {
                    if (isLandlordFieldHiddenWithinTab(field)) return;
                    // Select2-initialised multi-selects: checked via Livewire property below
                    if (field.tagName === 'SELECT' && field.multiple &&
                        field.classList.contains('select2-hidden-accessible')) return;
                    var isEmpty = false;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        isEmpty = !field.checked;
                    } else if (field.type === 'select-one' || field.type === 'select-multiple') {
                        isEmpty = (field.value === '' || field.value === null ||
                                   field.value === undefined);
                    } else {
                        isEmpty = !field.value || field.value.trim() === '';
                    }
                    if (isEmpty) {
                        var fKey = resolveLandlordFieldKey(field);
                        if (!seen.has(fKey)) {
                            seen.add(fKey);
                            items.push({
                                field:     field,
                                tab:       field.closest('.tab-pane'),
                                fieldName: resolveLandlordFieldLabel(field),
                                key:       fKey,
                            });
                        }
                    }
                });

                // Livewire property fallback: desired_lease_length (Select2 multi-select,
                // class="lease_term_options"; wire:ignore parent means DOM value is unreliable)
                try {
                    var _wireIdEl = document.querySelector('[wire\\:id]');
                    var _comp2 = _wireIdEl ? (window.livewire
                        ? window.livewire.find(_wireIdEl.getAttribute('wire:id'))
                        : null) : null;
                    if (_comp2) {
                        var _dll = _comp2.get('desired_lease_length');
                        if (!Array.isArray(_dll) || _dll.length === 0) {
                            if (!seen.has('desired_lease_length')) {
                                seen.add('desired_lease_length');
                                var _dllEl  = document.querySelector('.lease_term_options');
                                var _dllTab = _dllEl
                                    ? _dllEl.closest('.tab-pane')
                                    : document.querySelector('#leasing-terms');
                                items.push({
                                    field:     _dllEl || null,
                                    tab:       _dllTab,
                                    fieldName: 'Desired Lease Term',
                                    key:       'desired_lease_length',
                                });
                            }
                        }
                    }
                } catch(exLl) {}

                return items;
            }

            // Guided-correction mode state
            var _landlordCorrectionMode = false;
            var _landlordMissingItems   = [];

            // Navigate to the tab containing item, sync server-side $activeTab,
            // then scroll to and focus the field.
            function landlordNavigateToItem(item) {
                if (!item || !item.tab) return;
                var tabPane = item.tab;
                var tabId   = tabPane.id;
                var trigger = document.querySelector('[data-bs-target="#' + tabId + '"]');
                if (trigger) {
                    new bootstrap.Tab(trigger).show();
                    var allPanes = [...document.querySelectorAll('.tab-pane')];
                    var idx = allPanes.indexOf(tabPane);
                    if (idx >= 0) {
                        try { @this.call('setActiveTab', idx); } catch(exNav) {}
                    }
                }
                setTimeout(function() {
                    if (item.field && item.field.classList) {
                        item.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (typeof item.field.focus === 'function' &&
                            item.field.tagName !== 'DIV') {
                            item.field.focus();
                        }
                        item.field.classList.add('is-invalid');
                    }
                }, 350);
            }

            // Called after each Livewire round-trip while in correction mode.
            // Re-checks missing fields; navigates to the next one or exits when all are done.
            function landlordAdvanceCorrection() {
                if (!_landlordCorrectionMode) return;

                var freshMissing = landlordGetInvalidItems();
                var prevKeys = new Set(
                    _landlordMissingItems.map(function(i) { return i.key || i.fieldName; })
                );
                var someFixed = freshMissing.length < _landlordMissingItems.length ||
                    freshMissing.some(function(i) {
                        return !prevKeys.has(i.key || i.fieldName);
                    });

                var _banner4  = document.getElementById('submit-error-banner');
                var _errList4 = document.getElementById('submit-error-list');
                if (_errList4) {
                    _errList4.innerHTML = '';
                    freshMissing.forEach(function(i) {
                        var li = document.createElement('li');
                        li.textContent = i.fieldName;
                        _errList4.appendChild(li);
                    });
                }

                if (freshMissing.length === 0) {
                    // All required fields complete — exit correction and return to submit tab
                    _landlordCorrectionMode = false;
                    _landlordMissingItems   = [];
                    if (_banner4) _banner4.classList.add('d-none');
                    var _submitTrigger = document.querySelector(
                        '[data-bs-target="#landlord-information"]'
                    );
                    if (_submitTrigger) {
                        new bootstrap.Tab(_submitTrigger).show();
                        try { @this.call('setActiveTab', 6); } catch(exExit) {}
                    }
                    return;
                }

                // Re-show banner — Livewire DOM-diff re-adds d-none on every setActiveTab
                // round-trip, so we must explicitly remove it after each re-render.
                if (_banner4) _banner4.classList.remove('d-none');

                if (someFixed) {
                    _landlordMissingItems = freshMissing;
                    landlordNavigateToItem(freshMissing[0]);
                }
            }

            // Livewire reactivity hook: drives button state AND guided correction
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', () => {
                    setTimeout(() => {
                        const updatedServiceType = document.querySelector('[data-service-type]');
                        if (updatedServiceType) {
                            formContainer.setAttribute(
                                'data-service-type',
                                updatedServiceType.getAttribute('data-service-type')
                            );
                        }
                        setupGlobalListeners();
                        updateSaveButton();
                        if (_landlordCorrectionMode) {
                            landlordAdvanceCorrection();
                        }
                    }, 300);
                });
            }

            // Form submit handler with full guided correction
            const createForm = document.getElementById('create-auction-form');
            if (createForm) {
                document.addEventListener('submit', function(e) {
                    if (!e.target || e.target.id !== 'create-auction-form') return;

                    var banner    = document.getElementById('submit-error-banner');
                    var errorList = document.getElementById('submit-error-list');

                    var invalidItems = landlordGetInvalidItems();

                    if (invalidItems.length > 0) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        // Populate banner with every missing field
                        if (banner && errorList) {
                            errorList.innerHTML = '';
                            invalidItems.forEach(function(item) {
                                var li = document.createElement('li');
                                li.textContent = item.fieldName;
                                errorList.appendChild(li);
                            });
                            banner.classList.remove('d-none');
                        }

                        // Enter guided correction mode and navigate to first missing field
                        _landlordCorrectionMode = true;
                        _landlordMissingItems   = invalidItems;
                        landlordNavigateToItem(invalidItems[0]);

                        setTimeout(function() {
                            if (banner) {
                                // Re-assert visibility in case morphdom fired before this timeout
                                banner.classList.remove('d-none');
                                banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }, 350);

                        return false;
                    }

                    // All required fields valid — clear correction state and sync Select2 before submit
                    _landlordCorrectionMode = false;
                    _landlordMissingItems   = [];
                    if (banner) banner.classList.add('d-none');
                    syncLandlordSelect2BeforeSave();
                }, true);
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
            let v = input.value;
            v = v.replace(/[^0-9.,]/g, '');
            const firstDot = v.indexOf('.');
            if (firstDot !== -1) {
                v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
            }
            input.value = v;
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

        // Re-initialize money formatting after Livewire updates
        function initializeMoneyInputs() {
            // Format lease_value input if it's a $ (flat) type
            const leaseInput = document.querySelector('[wire\\:key^="lease-value-input-flat"]');
            if (leaseInput && leaseInput.value) {
                formatWithCommas(leaseInput);
            }
            // Format purchase_value input if it's a $ (flat) type  
            const purchaseInput = document.querySelector('[wire\\:key^="purchase-value-input-flat"]');
            if (purchaseInput && purchaseInput.value) {
                formatWithCommas(purchaseInput);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeMoneyInputs();
        });

        // Re-initialize after Livewire updates (Livewire v2)
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:load', function() {
                Livewire.hook('message.processed', function() {
                    initializeMoneyInputs();
                });
            });
        }
    </script>
    <script>
        (function () {
            function syncWizardButtons() {
                var aiPane = document.getElementById('ai-questions');
                var nextBtn = document.querySelector('.wizard-step-next');
                var finishBtn = document.querySelector('.wizard-step-finish');
                if (!nextBtn || !finishBtn) return;
                var onAI = !!aiPane && aiPane.classList.contains('show') && aiPane.classList.contains('active');
                nextBtn.style.display = onAI ? 'none' : '';
                finishBtn.style.display = onAI ? '' : 'none';
            }
            document.addEventListener('shown.bs.tab', syncWizardButtons);
            document.addEventListener('DOMContentLoaded', syncWizardButtons);
        })();
    </script>
@endpush
