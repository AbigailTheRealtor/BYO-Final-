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

        .conditional-upload-block {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 18px;
            clear: both;
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

        /* Fix invisible draft link text in Load Saved Draft modal */
        #draftModal .btn-link {
            color: #0d6efd !important;
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
        ['name' => 'New Construction'],
        ['name' => 'No updates needed: Completely updated'],
        ['name' => 'Semi-updated: Needs minor updates'],
        ['name' => 'Not updated: Requires a complete update'],
    ];
    $property_condition_landlord = $property_condition;

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
            'name' => 'Guests are [allowed/not allowed]; any restrictions include [insert details, e.g., visiting hours, overnight stay rules]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'Tenants have access to common areas such as the [kitchen/living room/backyard]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'Maintenance issues are handled by [landlord/property manager/agent/tenant]; response time is typically [insert timeframe]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'Utilities: [included in rent/split among tenants/individually metered]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'Common areas are cleaned and maintained [insert frequency and by whom, e.g., weekly by landlord]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'Storage space available includes [insert size or type, e.g., closet, basement section, garage shelf]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'Bathroom facilities are [private/shared]',
            'class' => 'residential-length'
        ],
        [
            'name' => 'The room available for lease is approximately [insert square footage]',
            'class' => 'residential-length'
],


        [
            'name' => 'Shared amenities include [e.g., conference rooms, parking facilities]',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Building hours are [insert hours], with [yes/no] 24/7 access available',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Zoning allows for [insert permitted uses]; restrictions may apply',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Maintenance and repairs are handled by [e.g., property management, agent, landlord, tenant responsibilities]',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Utilities are [insert details on how they\'re divided or included]',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Common areas are professionally cleaned and maintained on a [daily/weekly] basis',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'The space features a [describe layout, e.g., open floor plan, private offices, etc.]',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Storage space of [insert size or description] is included',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'A designated reception area is [included/not included]',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'The leasable unit is approximately [insert square footage]',
            'class' => 'commercial-length'
        ],
        [
            'name' => 'Neighboring tenants include [list business types or names if notable]',
            'class' => 'commercial-length'
        ]];
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

                    <form wire:submit.prevent="update">
                        <!-- Tab Navigation -->

                        @if ($service_type === 'full_service')
                            @php $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent'; @endphp

                             <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (['Listing Details', 'Property Details', 'Leasing Terms'] as $index => $tab)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                            id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab" data-bs-toggle="tab"
                                            data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}"
                                            type="button" role="tab"
                                            wire:click="setActiveTab({{ $index }})"
                                            aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                            aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                            {{ $tab }}
                                        </button>
                                    </li>
                                @endforeach
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 3 ? 'active' : '' }}"
                                        id="additional-details-tab" data-bs-toggle="tab"
                                        data-bs-target="#additional-details"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(3)"
                                        aria-controls="additional-details"
                                        aria-selected="{{ $activeTab === 3 ? 'true' : 'false' }}">
                                        Description
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                        id="broker-compensation-agency-agreement-terms-tab" data-bs-toggle="tab"
                                        data-bs-target="#broker-compensation-agency-agreement-terms"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(4)"
                                        aria-controls="broker-compensation-agency-agreement-terms"
                                        aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                        Broker Compensation &amp; Agency Agreement Terms
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 5 ? 'active' : '' }}"
                                        id="tax-legal-hoa-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#tax-legal-hoa-disclosures"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(5)"
                                        aria-controls="tax-legal-hoa-disclosures"
                                        aria-selected="{{ $activeTab === 5 ? 'true' : 'false' }}">
                                        Tax, Legal &amp; HOA
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 6 ? 'active' : '' }}"
                                        id="documents-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#documents-disclosures"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(6)"
                                        aria-controls="documents-disclosures"
                                        aria-selected="{{ $activeTab === 6 ? 'true' : 'false' }}">
                                        Documents &amp; Disclosures
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 7 ? 'active' : '' }}"
                                        id="photos-tours-documents-tab" data-bs-toggle="tab"
                                        data-bs-target="#photos-tours-documents"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(7)"
                                        aria-controls="photos-tours-documents"
                                        aria-selected="{{ $activeTab === 7 ? 'true' : 'false' }}">
                                        Photos &amp; Tours
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 8 ? 'active' : '' }}"
                                        id="landlord-information-tab" data-bs-toggle="tab"
                                        data-bs-target="#landlord-information"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(8)"
                                        aria-controls="landlord-information"
                                        aria-selected="{{ $activeTab === 8 ? 'true' : 'false' }}">
                                        Agent Credentials &amp; Contact Info
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 9 ? 'active' : '' }}"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(9)"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === 9 ? 'true' : 'false' }}">
                                        AI Questions
                                    </button>
                                </li>
                            </ul>
                        @else
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (['Listing Details', 'Location and Meeting Details', 'Service Selection and Pricing', 'Additional Details'] as $index => $tab)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                            id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab" data-bs-toggle="tab"
                                            data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}"
                                            type="button" role="tab"
                                            wire:click="setActiveTab({{ $index }})"
                                            aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                                            aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                                            {{ $tab }}
                                        </button>
                                    </li>
                                @endforeach

                                <!-- Photos, Tours & Documents Tab (limited_service: index 4) -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                        id="photos-tours-documents-ls-tab" data-bs-toggle="tab"
                                        data-bs-target="#photos-tours-documents-ls"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(4)"
                                        aria-controls="photos-tours-documents-ls"
                                        aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                        Photos, Tours &amp; Documents
                                    </button>
                                </li>

                                <!-- Dynamic Information Tab Based on User Type -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 5 ? 'active' : '' }}"
                                        id="information-tab" data-bs-toggle="tab"
                                        data-bs-target="#information" type="button" role="tab"
                                        wire:click="setActiveTab(5)"
                                        aria-controls="information"
                                        aria-selected="{{ $activeTab === 5 ? 'true' : 'false' }}">
                                        Agent Credentials & Contact Info
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 6 ? 'active' : '' }}"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(6)"
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
                                @include('livewire.offer-listing.offer-landlord-tabs.commission-based.listing-details')

                            </div>
                            @if ($service_type === 'full_service')
                                <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                                    id="property-details" role="tabpanel"
                                    aria-labelledby="property-details-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.property-preferences')

                                </div>

                                <!-- Leasing Terms Tab (index 2) -->
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                                    id="leasing-terms" role="tabpanel" aria-labelledby="leasing-terms-tab">

                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.lease-terms')
                                </div>

                                <!-- Additional Details Tab (index 3) -->
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}"
                                    id="additional-details" role="tabpanel" aria-labelledby="additional-details-tab">

                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.additional-details')

                                </div>

                                <!-- Broker Compensation & Agency Agreement Terms Tab (index 4) -->
                                <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}"
                                    id="broker-compensation-agency-agreement-terms" role="tabpanel"
                                    aria-labelledby="broker-compensation-agency-agreement-terms-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.broker-compensation')
                                </div>

                                <!-- Tax, Legal, HOA & Disclosures Tab (index 5) -->
                                <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}"
                                    id="tax-legal-hoa-disclosures" role="tabpanel"
                                    aria-labelledby="tax-legal-hoa-disclosures-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.tax-legal-hoa-disclosures')
                                </div>

                                <!-- Documents & Disclosures Tab (index 6) -->
                                <div class="tab-pane fade {{ $activeTab === 6 ? 'show active' : '' }}"
                                    id="documents-disclosures" role="tabpanel"
                                    aria-labelledby="documents-disclosures-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.documents-disclosures')
                                </div>

                                <!-- Photos & Tours Tab (index 7) -->
                                <div class="tab-pane fade {{ $activeTab === 7 ? 'show active' : '' }}"
                                    id="photos-tours-documents" role="tabpanel"
                                    aria-labelledby="photos-tours-documents-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.photos-tours-documents')
                                </div>

                                <!-- Landlord Info Tab (index 8) -->
                                <div class="tab-pane fade {{ $activeTab === 8 ? 'show active' : '' }}"
                                    id="landlord-information" role="tabpanel" aria-labelledby="landlord-information-tab">
                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                        @include('livewire.partials.agent-credentials')
                                    @else
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.landlord-info')
                                    @endif
                                </div>

                                <!-- AI Questions Tab (full_service: index 9) -->
                                <div class="tab-pane fade {{ $activeTab === 9 ? 'show active' : '' }}" id="ai-questions"
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
                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
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
                                <button type="button" class="btn btn-secondary wizard-step-back" wire:loading.attr="disabled">Back</button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" onclick="syncSelectValues()" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                    <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving...</span>
                                </button>

                                <button type="button" class="btn btn-primary wizard-step-next" wire:loading.attr="disabled">Next</button>

                                <button type="submit" class="btn btn-success wizard-step-finish disabled"
                                    id="save-button" wire:loading.attr="disabled" wire:target="update">
                                    <span wire:loading.remove wire:target="update">Submit</span>
                                    <span wire:loading wire:target="update">Submitting...</span>
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
        let _lastInitTime = 0;
        var __tabNavLock = false;
        var __tabRestoreGuard = false;

        window.addEventListener('force-redirect', function(event) {
            if (event.detail && event.detail.url) {
                window.location.href = event.detail.url;
            }
        });

        (function() {
            var tabList = document.querySelector('#myTab');
            if (tabList) {
                tabList.addEventListener('shown.bs.tab', function(e) {
                    var tabId = e.target.getAttribute('id');
                    if (tabId) {
                        sessionStorage.setItem('landlord_edit_active_tab', tabId);
                    }
                    var _tgt = e.target.getAttribute('data-bs-target');
                    if (_tgt) {
                        var _pane = document.querySelector(_tgt);
                        if (_pane) {
                            _pane.querySelectorAll('.is-invalid').forEach(function(el) {
                                el.classList.remove('is-invalid');
                            });
                            _pane.querySelectorAll('.error:not([id])').forEach(function(el) {
                                el.remove();
                            });
                        }
                    }
                });
            }
        })();

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
            setTimeout(function() { addIconsToInputs(); }, 0);
            checkRepresentationStatus();
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

        window.addEventListener('draftLoaded', function() {
            console.log('[DraftLoaded] Edit event received - syncing select values');
            setTimeout(function() {
                if (currentServiceType === 'limited_service') {
                    initializeLimitedService();
                } else {
                    initializeFullService();
                }
                syncSelectValues();
                addIconsToInputs();
            }, 50);
        });

        function removeWizardEventListeners() {
            const nextBtn = document.querySelector('.wizard-step-next');
            const backBtn = document.querySelector('.wizard-step-back');

            const nextClone = nextBtn?.cloneNode(true);
            const backClone = backBtn?.cloneNode(true);

            if (nextBtn && nextClone) nextBtn.parentNode.replaceChild(nextClone, nextBtn);
            if (backBtn && backClone) backBtn.parentNode.replaceChild(backClone, backBtn);
        }

        function initSelect2Fields() {
            $('.select2-multiple').each(function () {
                var $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    var _s2Open = false;
                    try { _s2Open = !!($el.data('select2') && $el.data('select2').isOpen()); } catch(e) {}
                    if (_s2Open) return;
                    $el.select2('destroy');
                }
                var placeholder = $el.attr('data-placeholder') || 'Select';
                var isMultiple = $el.prop('multiple');
                var opts = {
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%'
                };
                if (isMultiple) {
                    opts.closeOnSelect = false;
                }
                $el.select2(opts);
            });
            addIconsToInputs();
        }

        document.querySelectorAll('#myTab .nav-link').forEach(function(tabEl) {
            tabEl.addEventListener('shown.bs.tab', function(e) {
                setTimeout(function() { addIconsToInputs(); }, 0);
            });
        });

        function initializeFullService() {

            if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
                $('#property_items').select2({
                    placeholder: "Select property style",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                $('#property_items').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('property_items', selectedValues, true);
                });
            }

            if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                $('#non_negotiable_amenities').select2({
                    placeholder: "Select credit score rating(s)",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
                $('#non_negotiable_amenities').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('non_negotiable_amenities', selectedValues, true);
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

            attachAuctionDropdownListener();


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

            toggleGarageOptions();

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

            toggleSpaceInput('carport-needed', 'other-carport-needed');
            toggleSpaceInput('garage-needed', 'other-garage-needed');

            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                $('#view_preference').select2({
                    placeholder: "Select Preference",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
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

            // Function to toggle Non-Negotiable Amenities and Property Features:" input field

            function toggleOtherAmenities(selectElement) {
                const otherAmenitiesDiv = document.querySelector('.other_non_negotiable_amenities');

                if (!otherAmenitiesDiv) {
                    return;
                }
                if (selectElement.value === 'Other') {
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

            attachAmenitiesDropdownListener();

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

            attachBedroomsDropdownListener();

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

            attachConditionDropdownListener();




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

            attachItemConditionDropdownListener();



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

            attachOccupantTypesDropdownListener();


////////////    Desired Rental Amount


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

            attachDesiredRentalAmountDropdownListener();


            if ($('#rent_includes').length && !$('#rent_includes').hasClass('select2-hidden-accessible')) {
                $('#rent_includes').select2({
                    placeholder: "Select rent",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
                $('#rent_includes').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('rent_includes', selectedValues, true);
                });
            }

            if ($('#terms_of_lease').length && !$('#terms_of_lease').hasClass('select2-hidden-accessible')) {
                $('#terms_of_lease').select2({
                    placeholder: "Select terms of lease",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
                $('#terms_of_lease').on('change', function(e) {
                    let selectedValues = $(this).val();
                    @this.set('terms_of_lease', selectedValues, true);
                });
            }

            if ($('#tenant_pays').length && !$('#tenant_pays').hasClass('select2-hidden-accessible')) {
                $('#tenant_pays').select2({
                    placeholder: "Select tenant pays",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
                $('#tenant_pays').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('tenant_pays', selectedValues, true);
                    @this.call('updateTenantPays', selectedValues);
                    var $w = $('#other_tenant_pays_wrapper');
                    if ($w.length) $w.css('display', selectedValues.includes('Other') ? 'block' : 'none');
                });
            }
            if ($('#tenant_pays').length) {
                var _tpVals = $('#tenant_pays').val() || [];
                var $tpW = $('#other_tenant_pays_wrapper');
                if ($tpW.length) $tpW.css('display', _tpVals.includes('Other') ? 'block' : 'none');
            }

            if ($('#owner_pays').length && !$('#owner_pays').hasClass('select2-hidden-accessible')) {
                $('#owner_pays').select2({
                    placeholder: "Select owner pays",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
                $('#owner_pays').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('owner_pays', selectedValues, true);
                    @this.call('updateOwnerPays', selectedValues);
                    var $w = $('#other_owner_pays_wrapper');
                    if ($w.length) $w.css('display', selectedValues.includes('Other') ? 'block' : 'none');
                });
            }
            if ($('#owner_pays').length) {
                var _opVals = $('#owner_pays').val() || [];
                var $opW = $('#other_owner_pays_wrapper');
                if ($opW.length) $opW.css('display', _opVals.includes('Other') ? 'block' : 'none');
            }

            // Initialize Desired Lease Term Select2 for edit mode
            function initEditLeaseTermSelect2() {
                var $dlt = $('.lease_term_options');
                if (!$dlt.length) return;
                if (!$dlt.hasClass('select2-hidden-accessible')) {
                    $dlt.select2({
                        placeholder: "Select desired lease term",
                        allowClear: true,
                        closeOnSelect: false,
                        width: '100%',
                    });
                }
                var savedValues = @json($desired_lease_length ?? []);
                if (savedValues && savedValues.length) {
                    $dlt.val(savedValues).trigger('change');
                }
                var otherWrapper = document.querySelector('.other_lease_term_wrapper');
                if (otherWrapper) {
                    var current = $dlt.val() || [];
                    otherWrapper.style.display = current.includes('Other') ? 'block' : 'none';
                }
                $dlt.off('change.ltsSync').on('change.ltsSync', function() {
                    var selectedValues = $(this).val() || [];
                    @this.set('desired_lease_length', selectedValues, true);
                    var wrapper = document.querySelector('.other_lease_term_wrapper');
                    if (wrapper) {
                        wrapper.style.display = selectedValues.includes('Other') ? 'block' : 'none';
                    }
                });
            }

            initEditLeaseTermSelect2();

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

                // Enable/disable save button
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !allValid;
                }

                return allValid;
            }

            window._wizardNextHandler = function() {
                if (__tabNavLock) return;
                __tabNavLock = true;
                setTimeout(function() { __tabNavLock = false; }, 250);

                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                if (!currentTab) return;

                const currentTabContent = document.querySelector(currentTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                let isValid = true;

                const requiredFields = currentTabContent.querySelectorAll(
                    'input[required], select[required], textarea[required]');
                for (const input of requiredFields) {
                    if (!isElementVisible(input)) continue;

                    const isEmpty = (
                        input.type === 'file'     ? !input.files || input.files.length === 0 :
                        input.type === 'checkbox' ? !input.checked :
                        input.type === 'radio'    ? !currentTabContent.querySelector(`input[name="${input.name}"]:checked`) :
                        !input.value?.toString().trim()
                    );

                    if (isEmpty) {
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
                }

                const citiesContainer = currentTabContent.querySelector('.cities-container');
                if (citiesContainer && isElementVisible(citiesContainer)) {
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

                if (isValid) {
                    currentTabContent.querySelectorAll('.is-invalid').forEach(function(el) {
                        el.classList.remove('is-invalid');
                    });
                    currentTabContent.querySelectorAll('.error:not([id])').forEach(function(el) {
                        el.remove();
                    });
                    const _allTabs = Array.from(document.querySelectorAll('#myTab .nav-link'));
                    const _curIdx = _allTabs.indexOf(currentTab);
                    if (_curIdx < _allTabs.length - 1) {
                        const _nextTab = _allTabs[_curIdx + 1];
                        var _nId = _nextTab.getAttribute('data-bs-target');
                        if (_nId) {
                            window._manualTabSwitch(_nId);
                            sessionStorage.setItem('landlord_edit_active_tab', _nId);
                        }
                    }
                }

                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !isValid;
                }
            };

            window._wizardBackHandler = function() {
                if (__tabNavLock) return;
                __tabNavLock = true;
                setTimeout(function() { __tabNavLock = false; }, 250);

                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                const prevTabEl = currentTab.parentElement.previousElementSibling?.querySelector('.nav-link');
                if (prevTabEl) {
                    new bootstrap.Tab(prevTabEl).show();
                    prevTabEl.click();
                }
            };

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

            // Initialize any remaining select2-multiple fields not covered above
            initSelect2Fields();

            // Re-inject icons after all Select2 instances are initialized
            addIconsToInputs();

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

                // Enable/disable save button
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !allValid;
                }

                return allValid;
            }

            window._wizardNextHandler = function() {
                if (__tabNavLock) return;
                __tabNavLock = true;
                setTimeout(function() { __tabNavLock = false; }, 250);

                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                if (!currentTab) return;

                const currentTabContent = document.querySelector(currentTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                let isValid = true;

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

                if (isValid) {
                    const nextTabEl = currentTab.parentElement?.nextElementSibling?.querySelector(
                        '.nav-link');
                    if (nextTabEl) {
                        new bootstrap.Tab(nextTabEl).show();
                        nextTabEl.click();
                    }
                }

                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !isValid;
                }
            };

            window._wizardBackHandler = function() {
                if (__tabNavLock) return;
                __tabNavLock = true;
                setTimeout(function() { __tabNavLock = false; }, 250);

                const currentTab = document.querySelector('.nav-tabs .nav-link.active');
                const prevTabEl = currentTab.parentElement.previousElementSibling?.querySelector('.nav-link');
                if (prevTabEl) {
                    new bootstrap.Tab(prevTabEl).show();
                    prevTabEl.click();
                }
            };

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



        // Direct DOM tab switch — bypasses Bootstrap Tab API entirely
        if (!window._manualTabSwitch) {
            window._manualTabSwitch = function(targetId) {
                var _links = Array.from(document.querySelectorAll('#myTab .nav-link'));
                _links.forEach(function(link) {
                    var _lt = link.getAttribute('data-bs-target');
                    var _hit = (_lt === targetId);
                    link.classList.toggle('active', _hit);
                    link.setAttribute('aria-selected', _hit ? 'true' : 'false');
                    if (_lt) {
                        var _pane = document.querySelector(_lt);
                        if (_pane) {
                            _pane.classList.toggle('show', _hit);
                            _pane.classList.toggle('active', _hit);
                        }
                    }
                });
            };
        }

        // Delegated wizard nav — bound once, survives Livewire DOM morphing
        if (!window.__wizardNavBound) {
            window.__wizardNavBound = true;
            document.addEventListener('click', function(e) {
                if (e.target.closest('.wizard-step-next') && typeof window._wizardNextHandler === 'function') {
                    window._wizardNextHandler();
                }
                if (e.target.closest('.wizard-step-back') && typeof window._wizardBackHandler === 'function') {
                    window._wizardBackHandler();
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
            setTimeout(addIconsToInputs, 150);
            setTimeout(addIconsToInputs, 400);
            setTimeout(addIconsToInputs, 800);
        });

        Livewire.hook('message.processed', () => {
            var _scrollY = window.scrollY || document.documentElement.scrollTop || 0;

            var now = Date.now();
            if (now - _lastInitTime > 400) {
                _lastInitTime = now;
                removeWizardEventListeners();

                if (currentServiceType === 'full_service') {
                    initializeFullService();
                } else if (currentServiceType === 'limited_service') {
                    initializeLimitedService();
                }
            }
            addIconsToInputs();
            checkRepresentationStatus();

            if (!__tabRestoreGuard) {
                __tabRestoreGuard = true;
                var savedTabId = sessionStorage.getItem('landlord_edit_active_tab');
                if (savedTabId && typeof window._manualTabSwitch === 'function') {
                    var _activePane = document.querySelector(savedTabId);
                    if (_activePane && !_activePane.classList.contains('active')) {
                        window._manualTabSwitch(savedTabId);
                    }
                }
                setTimeout(function() { __tabRestoreGuard = false; }, 200);
            }

            requestAnimationFrame(() => { window.scrollTo(0, _scrollY); });
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
                    '#property-details',
                    '#leasing-terms',
                    '#additional-details',
                    '#broker-compensation-agency-agreement-terms',
                    '#tax-legal-hoa-disclosures',
                    '#documents-disclosures',
                    '#photos-tours-documents',
                    '#landlord-information',
                    '#ai-questions'
                ] : [
                    '#listing-details',
                    '#location-and-meeting-details',
                    '#service-selection-and-pricing',
                    '#photos-tours-documents-ls',
                    '#information',
                    '#ai-questions'
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
                // Skip validation for fields inside hidden containers (conditional sections)
                var el = field;
                while (el && el !== document.body) {
                    var style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden') return true;
                    if (el.classList && el.classList.contains('d-none')) return true;
                    el = el.parentElement;
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
                    saveButton.setAttribute('disabled', 'disabled');
                }
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

                        // Re-init Desired Lease Term Select2 after Livewire re-render
                        initEditLeaseTermSelect2();

                        // Re-attach Bathrooms Other listener after Livewire re-render
                        attachBathroomsDropdownListener();

                        // Re-check Carport spaces input visibility after Livewire re-render
                        var carportSelect = document.getElementById('carport-needed');
                        var carportInput = document.getElementById('other-carport-needed');
                        if (carportSelect && carportInput) {
                            if (carportSelect.value === 'Yes') {
                                carportInput.classList.remove('d-none');
                            }
                        }
                    }, 300);
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

