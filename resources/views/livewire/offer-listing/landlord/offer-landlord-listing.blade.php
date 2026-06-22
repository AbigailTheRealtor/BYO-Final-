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

        textarea.form-control {
            min-height: 80px;
            height: auto;
            resize: vertical;
        }

        textarea.form-control.landlord-compact-textarea[rows="1"] {
            height: calc(1.5em + 0.75rem + 2px);
            min-height: unset;
            resize: vertical;
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

        /* Fix invisible draft link text in Load Saved Draft modal.
           Tailwind preflight's "a { color: inherit }" interacts with Bootstrap's
           CSS custom property chain differently on this page, causing .btn-link
           text to resolve to white. Scope the fix tightly to #draftModal only. */
        #draftModal .btn-link {
            color: #0d6efd !important;
        }

        .pac-container { z-index: 99999 !important; }

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
        ['name' => 'Apartments', 'class' => 'residential-length'],
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
                                            @php
                                                $draftVersion = $draft->info('draft_version') ?? null;
                                                $isCurrentDraft = ($listingId && $draft->id == $listingId);
                                            @endphp
                                            <div
                                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                @if ($isCurrentDraft)
                                                    <span class="btn btn-link text-start flex-grow-1 text-muted pe-none">
                                                        {{ $draft->info('title') ?: 'Saved Draft' }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif <span class="badge bg-success">Current</span> ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                                    </span>
                                                @else
                                                <a class="btn btn-link text-start flex-grow-1"
                                                    href="{{ route('offer.listing.landlord.edit', ['auctionId' => $draft->id]) }}">
                                                    {{ $draft->info('title') ?: 'Saved Draft' }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                                </a>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-danger" style="border-color: #dc3545; color: #dc3545;"
                                                    data-bs-dismiss="modal"
                                                    wire:click="deleteDraft('{{ $draft->id }}')" wire:ignore.self
                                                    onclick="setTimeout(() => { window.location = '{{ route('offer.listing.landlord')}}' }, 100)">
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
                                        onclick="setTimeout(() => { window.location = '{{ route('offer.listing.landlord')}}' }, 100)">
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

                {{-- MLS Import Entry Point --}}
                <div class="container pt-3 pb-0">
                    <div class="d-flex align-items-center justify-content-end">
                        <button type="button" class="btn btn-outline-primary btn-sm"
                                style="color:#0d6efd;"
                                wire:click="$set('showImportModal', true)">
                            <i class="fas fa-file-import me-1"></i>Have an MLS listing? Import it to pre-fill this form &rarr;
                        </button>
                    </div>
                </div>
                @include('livewire.offer-listing.shared.mls-import-modal')
                @if($importSuccess)
                    <div class="container">
                        <div class="alert alert-success alert-dismissible py-2 mt-2" role="alert">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>Imported fields were applied.</strong> Please review all values before publishing.
                            <button type="button" class="btn-close" wire:click="$set('importSuccess', false)"></button>
                        </div>
                    </div>
                @endif

                <div id="wizard-form-container" class="container pt-5 pb-5">

                    <form id="create-auction-form" wire:submit.prevent="store" novalidate>
                        <div id="submit-error-banner" class="alert alert-danger d-none" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                            <strong>Please complete the required fields before submitting.</strong>
                            <ul id="submit-error-list" class="mb-0 mt-2"></ul>
                        </div>
                        <!-- Tab Navigation -->

                        @php $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent'; @endphp

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (['Listing Details', 'Property Details'] as $index => $tab)
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
                                    <button class="nav-link {{ $activeTab === 2 ? 'active' : '' }}"
                                        wire:click="setActiveTab(2)"
                                        id="additional-details-tab" data-bs-toggle="tab"
                                        data-bs-target="#additional-details"
                                        type="button" role="tab"
                                        aria-controls="additional-details"
                                        aria-selected="{{ $activeTab === 2 ? 'true' : 'false' }}">
                                        Description
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 3 ? 'active' : '' }}"
                                        wire:click="setActiveTab(3)"
                                        id="leasing-terms-tab" data-bs-toggle="tab"
                                        data-bs-target="#leasing-terms"
                                        type="button" role="tab"
                                        aria-controls="leasing-terms"
                                        aria-selected="{{ $activeTab === 3 ? 'true' : 'false' }}">
                                        Leasing Terms
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                        wire:click="setActiveTab(4)"
                                        id="applicant-requirements-tab" data-bs-toggle="tab"
                                        data-bs-target="#applicant-requirements"
                                        type="button" role="tab"
                                        aria-controls="applicant-requirements"
                                        aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                        Applicant Requirements
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 5 ? 'active' : '' }}"
                                        wire:click="setActiveTab(5)"
                                        id="broker-compensation-agency-agreement-terms-tab" data-bs-toggle="tab"
                                        data-bs-target="#broker-compensation-agency-agreement-terms"
                                        type="button" role="tab"
                                        aria-controls="broker-compensation-agency-agreement-terms"
                                        aria-selected="{{ $activeTab === 5 ? 'true' : 'false' }}">
                                        Broker Compensation &amp; Agency Agreement Terms
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 6 ? 'active' : '' }}"
                                        wire:click="setActiveTab(6)"
                                        id="tax-legal-hoa-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#tax-legal-hoa-disclosures"
                                        type="button" role="tab"
                                        aria-controls="tax-legal-hoa-disclosures"
                                        aria-selected="{{ $activeTab === 6 ? 'true' : 'false' }}">
                                        Tax, Legal &amp; HOA
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 7 ? 'active' : '' }}"
                                        wire:click="setActiveTab(7)"
                                        id="documents-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#documents-disclosures"
                                        type="button" role="tab"
                                        aria-controls="documents-disclosures"
                                        aria-selected="{{ $activeTab === 7 ? 'true' : 'false' }}">
                                        Documents &amp; Disclosures
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 8 ? 'active' : '' }}"
                                        wire:click="setActiveTab(8)"
                                        id="photos-tours-documents-tab" data-bs-toggle="tab"
                                        data-bs-target="#photos-tours-documents"
                                        type="button" role="tab"
                                        aria-controls="photos-tours-documents"
                                        aria-selected="{{ $activeTab === 8 ? 'true' : 'false' }}">
                                        Photos &amp; Tours
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 9 ? 'active' : '' }}"
                                        wire:click="setActiveTab(9)"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === 9 ? 'true' : 'false' }}">
                                        AI Knowledge Base
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 10 ? 'active' : '' }}"
                                        wire:click="setActiveTab(10)"
                                        id="landlord-information-tab" data-bs-toggle="tab"
                                        data-bs-target="#landlord-information"
                                        type="button" role="tab"
                                        aria-controls="landlord-information"
                                        aria-selected="{{ $activeTab === 10 ? 'true' : 'false' }}">
                                        Agent Credentials &amp; Contact Info
                                    </button>
                                </li>
                            </ul>

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
                                <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                                    id="property-details" role="tabpanel"
                                    aria-labelledby="property-details-tab">

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

                                <!-- Additional Details Tab (index 2) -->
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
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

                                <!-- Leasing Terms Tab (index 3) -->
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}"
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

                                <!-- Applicant Requirements Tab (index 4) -->
                                <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}"
                                    id="applicant-requirements" role="tabpanel" aria-labelledby="applicant-requirements-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.applicant-requirements')
                                </div>

                                <!-- Broker Compensation & Agency Agreement Terms Tab (index 5) -->
                                <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}"
                                    id="broker-compensation-agency-agreement-terms" role="tabpanel"
                                    aria-labelledby="broker-compensation-agency-agreement-terms-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.broker-compensation')
                                </div>

                                <!-- Tax, Legal, HOA & Disclosures Tab (index 6) -->
                                <div class="tab-pane fade {{ $activeTab === 6 ? 'show active' : '' }}"
                                    id="tax-legal-hoa-disclosures" role="tabpanel" aria-labelledby="tax-legal-hoa-disclosures-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.tax-legal-hoa-disclosures')
                                </div>

                                <!-- Documents & Disclosures Tab (index 7) -->
                                <div class="tab-pane fade {{ $activeTab === 7 ? 'show active' : '' }}"
                                    id="documents-disclosures" role="tabpanel" aria-labelledby="documents-disclosures-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.documents-disclosures')
                                </div>

                                <!-- Photos, Tours & Documents Tab (index 8) -->
                                <div class="tab-pane fade {{ $activeTab === 8 ? 'show active' : '' }}"
                                    id="photos-tours-documents" role="tabpanel" aria-labelledby="photos-tours-documents-tab">
                                    @include('livewire.offer-listing.offer-landlord-tabs.commission-based.photos-tours-documents')
                                </div>

                                <!-- AI Knowledge Base Tab (index 9) -->
                                <div class="tab-pane fade {{ $activeTab === 9 ? 'show active' : '' }}" id="ai-questions"
                                    role="tabpanel" aria-labelledby="ai-questions-tab">
                                    @include('livewire.offer-listing.shared.ai-questions-input')
                                </div>

                                <!-- Landlord Info Tab (index 10) -->
                                <div class="tab-pane fade {{ $activeTab === 10 ? 'show active' : '' }}"
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
                        </div>
                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between form-group mt-4">
                            <div>
                                <button type="button" class="btn btn-secondary wizard-step-back" onclick="(function(){var a=Array.from(document.querySelectorAll('#myTab .nav-link')),c=a.find(function(t){return t.classList.contains('active')}),i=c?a.indexOf(c):-1;if(!c||i<=0)return;var p=a[i-1].getAttribute('data-bs-target');if(!p)return;a.forEach(function(l){var lt=l.getAttribute('data-bs-target'),h=(lt===p);l.classList.toggle('active',h);l.setAttribute('aria-selected',h?'true':'false');if(lt){var pn=document.querySelector(lt);if(pn){pn.classList.toggle('show',h);pn.classList.toggle('active',h);}}});sessionStorage.setItem('landlord_create_active_tab',p);var _we=document.querySelector('[wire\\:id]');if(_we&&window.Livewire){var _comp=window.Livewire.find(_we.getAttribute('wire:id'));if(_comp)_comp.call('setActiveTab',i-1);}})();">Back</button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" onclick="syncLandlordSelect2BeforeSave()" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                    <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving...</span>
                                </button>
                                <button type="button" class="btn btn-primary wizard-step-next" onclick="if(typeof window._wizardNextHandler==='function')window._wizardNextHandler();">Next</button>
                                <button type="submit" class="btn btn-success wizard-step-finish disabled"
                                    id="save-button" wire:loading.attr="disabled" wire:target="store"
                                    onclick="syncLandlordSelect2BeforeSave()">
                                    <span wire:loading.remove wire:target="store">Submit Rental Offer</span>
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

@if($listingId && $canViewLocationDnaPanel)
<div class="container-fluid mt-2 mb-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            @include('partials.location-dna-agent-panel', [
                'listingType'            => 'landlord_agent',
                'listingId'              => $listingId,
                'locationDna'            => $locationDna,
                'locationPois'           => $locationPois,
                'canGenerateLocationDna' => $canGenerateLocationDna,
            ])
        </div>
    </div>
</div>
@endif

@push('scripts')
    <script>
        let currentServiceType = null;
        let _bathroomsDropdownHandler = null;

        document.addEventListener('DOMContentLoaded', () => {
            currentServiceType = 'full_service';
            initializeFullService();
            addIconsToInputs();
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
                    'pool_type': data.pool_type || [],
                    'photo_enhancements': data.photo_enhancements || [],
                    'property_items': data.property_items || [],
                    'tenant_require': data.tenant_require || [],
                    'pet_species_allowed': data.pet_species_allowed || [],
                    'pet_policy_requirement': data.pet_policy_requirement || [],
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
                        var $el;
                        if (fieldName === 'desired_lease_length') {
                            $el = $('.lease_term_options');
                        } else {
                            $el = $('#' + fieldName);
                        }
                        if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                            console.log('[DraftLoaded] Hydrating Select2: ' + fieldName + ' with ' + values.length + ' values');
                            $el.val(values).trigger('change');
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

        // Re-hydrate all Select2 multi-selects after MLS "Apply Selected" completes.
        // Select2 elements use wire:ignore so Livewire DOM diff does not update them.
        // dispatchBrowserEvent('mlsApplied') fires from HasMlsImport::applyImportedFields().
        //
        // Two-pass approach (200 ms + 600 ms): guards against newly-added DOM sections
        // (e.g. when property_type was empty and changed during apply) whose Select2
        // instances have not yet been initialized at the time the first pass runs.
        // initializeFullService() is called first; its internal !hasClass guard prevents
        // re-initializing already-active Select2 instances.
        (function() {
            function _landlordMlsRehydrate() {
                if (typeof initializeFullService === 'function') {
                    initializeFullService();
                }
                var mlsIdFields = ['heating_fuel','air_conditioning','water','sewer',
                    'property_utilities','laundry_features','floor_covering',
                    'security_features','road_surface_type','electrical_service',
                    'building_features','space_type','space_classification',
                    'roof_type','exterior_construction','foundation',
                    'water_access','water_view','interior_features'];
                mlsIdFields.forEach(function(fieldId) {
                    var values = @this.get(fieldId) || [];
                    if (!Array.isArray(values) || values.length === 0) return;
                    var $el = $('#' + fieldId);
                    if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                        $el.val(values).trigger('change.select2');
                        console.log('[MlsApplied] Rehydrated ' + fieldId + ':', values);
                    }
                });
                var regularFields = {
                    'appliances': '#appliances',
                    'offered_financing': '#offered_financing',
                    'tenant_pays': '#tenant_pays',
                    'owner_pays': '#owner_pays',
                    'terms_of_lease': '#terms_of_lease',
                    'rent_includes': '#rent_includes',
                    'view_preference': '#view_preference',
                    'non_negotiable_amenities': '#non_negotiable_amenities',
                };
                Object.keys(regularFields).forEach(function(prop) {
                    var values = @this.get(prop) || [];
                    if (!Array.isArray(values) || values.length === 0) return;
                    var $el = $(regularFields[prop]);
                    if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                        $el.val(values).trigger('change.select2');
                        console.log('[MlsApplied] Rehydrated ' + prop + ':', values);
                    }
                });
            }
            window.addEventListener('mlsApplied', function() {
                console.log('[MlsApplied] Rehydrating landlord Select2 fields from Livewire properties');
                setTimeout(_landlordMlsRehydrate, 200);
                setTimeout(_landlordMlsRehydrate, 600);
            });
        }());

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
            }

            if (window.Livewire) { Livewire.emit('serviceTypeChanged', serviceType); }
        }

        function removeWizardEventListeners() {
            const nextBtn = document.querySelector('.wizard-step-next');
            const backBtn = document.querySelector('.wizard-step-back');

            const nextClone = nextBtn?.cloneNode(true);
            const backClone = backBtn?.cloneNode(true);

            if (nextBtn && nextClone) nextBtn.parentNode.replaceChild(nextClone, nextBtn);
            if (backBtn && backClone) backBtn.parentNode.replaceChild(backClone, backBtn);
        }

        // Shared visibility helper — used across all validation functions in this file
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

        function initializeFullService() {
            try {
            if (!document._landlordCreateTabNavListenerAdded) {
                document._landlordCreateTabNavListenerAdded = true;
                document.addEventListener('shown.bs.tab', function(e) {
                    var _tgt = e.target.getAttribute('data-bs-target');
                    if (_tgt && e.target.closest('#myTab')) {
                        sessionStorage.setItem('landlord_create_active_tab', _tgt);
                        setTimeout(function() { addIconsToInputs(); }, 0);
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


            if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
                $('#property_items').select2({
                    placeholder: "Select",
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
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                var _preNNA = @json($this->non_negotiable_amenities ?? []);
                if (_preNNA.length > 0) {
                    $('#non_negotiable_amenities').val(_preNNA).trigger('change');
                }
                $('#non_negotiable_amenities').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('non_negotiable_amenities', selectedValues, true);
                    var _nnaOther = document.querySelector('.other_non_negotiable_amenities');
                    if (_nnaOther) {
                        selectedValues.includes('Other') ? _nnaOther.classList.remove('d-none') : _nnaOther.classList.add('d-none');
                    }
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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachAuctionDropdownListener();
                });
            }


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
                if (!otherInputWrapper) return;
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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    toggleGarageOptions();
                });
            }

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
                            if (window.Livewire) { Livewire.emit('updateModel', inputField.getAttribute('wire:model'), ''); }
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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    setTimeout(() => {
                        attachBathroomsDropdownListener();
                        toggleSpaceInput('carport-needed', 'other-carport-needed');
                        toggleSpaceInput('garage-needed', 'other-garage-needed');
                    }, 150);
                });
            }

            function initViewPreferenceSelect2() {
                if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                    $('#view_preference').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var _preVP = @json($this->view_preference ?? []);
                    if (_preVP.length > 0) {
                        $('#view_preference').val(_preVP).trigger('change');
                    }
                    $('#view_preference').on('change', function() {
                        let selectedValues = $(this).val() || [];
                        @this.set('view_preference', selectedValues, true);
                        if (selectedValues.includes('Other')) {
                            $('#other_preferences').show();
                        } else {
                            $('#other_preferences').hide();
                        }
                    });
                }
                if ($('#view_preference').length) {
                    var _vpVals = $('#view_preference').val() || [];
                    if (_vpVals.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                    }
                }
            }
            initViewPreferenceSelect2();

            // MLS Property Detail — multi-select fields with Other-toggle
            var mlsMultiSelects = [
                { id: 'heating_fuel',      field: 'heating_fuel',      placeholder: 'Select',   otherId: 'other_heating_fuel_wrapper' },
                { id: 'air_conditioning',  field: 'air_conditioning',  placeholder: 'Select', otherId: 'other_air_conditioning_wrapper' },
                { id: 'water',             field: 'water',             placeholder: 'Select',          otherId: 'other_water_wrapper' },
                { id: 'sewer',             field: 'sewer',             placeholder: 'Select',            otherId: 'other_sewer_wrapper' },
                { id: 'property_utilities',field: 'property_utilities',placeholder: 'Select',                otherId: 'other_property_utilities_wrapper' },
                { id: 'laundry_features',  field: 'laundry_features',  placeholder: 'Select',        otherId: 'other_laundry_features_wrapper' },
                { id: 'floor_covering',    field: 'floor_covering',    placeholder: 'Select',          otherId: 'other_floor_covering_wrapper' },
                { id: 'security_features', field: 'security_features', placeholder: 'Select',      otherId: 'other_security_features_wrapper' },
                { id: 'road_surface_type', field: 'road_surface_type', placeholder: 'Select',    otherId: 'other_road_surface_type_wrapper' },
                { id: 'electrical_service',field: 'electrical_service',placeholder: 'Select',      otherId: 'other_electrical_service_wrapper' },
                { id: 'building_features', field: 'building_features', placeholder: 'Select',       otherId: 'other_building_features_wrapper' },
                { id: 'space_type',           field: 'space_type',           placeholder: 'Select' },
                { id: 'space_classification', field: 'space_classification', placeholder: 'Select' },
                { id: 'garage_parking_spaces_option_landlord', field: 'garage_parking_spaces_option', placeholder: 'Select', otherId: 'other_garage_parking_spaces_option_landlord' },
                { id: 'water_access',      field: 'water_access',      placeholder: 'Select', otherId: 'other_water_access_wrapper' },
                { id: 'water_view',        field: 'water_view',         placeholder: 'Select', otherId: 'other_water_view_wrapper' },
                { id: 'interior_features', field: 'interior_features',  placeholder: 'Select', otherId: 'other_interior_features_wrapper' },
            ];
            function initMlsMultiSelects() {
                mlsMultiSelects.forEach(function(cfg) {
                    var $el = $('#' + cfg.id);
                    if ($el.length && !$el.hasClass('select2-hidden-accessible')) {
                        $el.select2({ placeholder: cfg.placeholder, allowClear: true, width: '100%', closeOnSelect: false });
                        $el.on('change', function() {
                            var vals = $(this).val() || [];
                            @this.set(cfg.field, vals, true);
                            if (cfg.otherId) {
                                var $wrapper = $('#' + cfg.otherId);
                                if ($wrapper.length) {
                                    $wrapper.css('display', vals.includes('Other') ? 'block' : 'none');
                                }
                            }
                        });
                    }
                    if ($el.length && cfg.otherId) {
                        var _currentVals = $el.val() || [];
                        var $wrapper = $('#' + cfg.otherId);
                        if ($wrapper.length) {
                            $wrapper.css('display', _currentVals.includes('Other') ? 'block' : 'none');
                        }
                    }
                });
            }
            initMlsMultiSelects();
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    setTimeout(function() {
                        initMlsMultiSelects();
                        initViewPreferenceSelect2();
                        initAppliancesSelect2();
                    }, 150);
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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachAmenitiesDropdownListener();
                });
            }

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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachBedroomsDropdownListener();
                });
            }

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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachConditionDropdownListener();
                });
            }




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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachItemConditionDropdownListener();
                });
            }



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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachOccupantTypesDropdownListener();
                });
            }


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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachDesiredRentalAmountDropdownListener();
                });
            }


            function initAppliancesSelect2() {
                if ($('#appliances').length && !$('#appliances').hasClass('select2-hidden-accessible')) {
                    $('#appliances').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var _preAppliances = @json($this->appliances ?? []);
                    if (_preAppliances.length > 0) {
                        $('#appliances').val(_preAppliances).trigger('change');
                    }
                    $('#appliances').on('change', function(e) {
                        let selectedValues = $(this).val() || [];
                        @this.set('appliances', selectedValues, true);
                        @this.call('updateAppliances', selectedValues);
                        var _appEl = document.getElementById('other_appliances');
                        if (_appEl) _appEl.style.display = selectedValues.includes('Other') ? '' : 'none';
                    });
                }
                if ($('#appliances').length) {
                    var _appVals = $('#appliances').val() || [];
                    var _appEl = document.getElementById('other_appliances');
                    if (_appEl) _appEl.style.display = _appVals.includes('Other') ? '' : 'none';
                }
            }
            initAppliancesSelect2();

            function initLeaseMetaSelect2() {
                if ($('#rent_includes').length && !$('#rent_includes').hasClass('select2-hidden-accessible')) {
                    $('#rent_includes').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var _preRI = @json($this->rent_includes ?? []);
                    if (_preRI.length > 0) {
                        $('#rent_includes').val(_preRI).trigger('change');
                    }
                    $('#rent_includes').on('change', function(e) {
                        let selectedValues = $(this).val() || [];
                        @this.set('rent_includes', selectedValues, true);
                        @this.call('updateRentIncludes', selectedValues);
                        var $w = $('#other_rent_includes_wrapper');
                        if ($w.length) $w.css('display', selectedValues.includes('Other') ? 'block' : 'none');
                    });
                }
                if ($('#rent_includes').length) {
                    var _riVals = $('#rent_includes').val() || [];
                    var $riW = $('#other_rent_includes_wrapper');
                    if ($riW.length) $riW.css('display', _riVals.includes('Other') ? 'block' : 'none');
                }

                if ($('#terms_of_lease').length && !$('#terms_of_lease').hasClass('select2-hidden-accessible')) {
                    $('#terms_of_lease').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var _preTOL = @json($this->terms_of_lease ?? []);
                    if (_preTOL.length > 0) {
                        $('#terms_of_lease').val(_preTOL).trigger('change');
                    }
                    $('#terms_of_lease').on('change', function(e) {
                        let selectedValues = $(this).val() || [];
                        @this.set('terms_of_lease', selectedValues, true);
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
                if ($('#terms_of_lease').length) {
                    var _tolVals = $('#terms_of_lease').val() || [];
                    var _tolContainer = document.getElementById('otherLeaseContainer');
                    if (_tolContainer) {
                        if (_tolVals.includes('Other')) {
                            _tolContainer.classList.remove('d-none');
                        } else {
                            _tolContainer.classList.add('d-none');
                        }
                    }
                }

                if ($('#tenant_pays').length && !$('#tenant_pays').hasClass('select2-hidden-accessible')) {
                    $('#tenant_pays').select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var _preTP = @json($this->tenant_pays ?? []);
                    if (_preTP.length > 0) {
                        $('#tenant_pays').val(_preTP).trigger('change');
                    }
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
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    var _preOP = @json($this->owner_pays ?? []);
                    if (_preOP.length > 0) {
                        $('#owner_pays').val(_preOP).trigger('change');
                    }
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
            }
            initLeaseMetaSelect2();

            function initLeaseTermSelect2() {
                var $dlt = $('.lease_term_options');
                if (!$dlt.length) return;
                if ($dlt.hasClass('select2-hidden-accessible')) {
                    var _s2Open = false;
                    try { _s2Open = !!($dlt.data('select2') && $dlt.data('select2').isOpen()); } catch(e) {}
                    if (_s2Open) return;
                    $dlt.select2('destroy');
                }
                $dlt.select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                $dlt.off('change.ltsSync').on('change.ltsSync', function() {
                    var selectedValues = $(this).val() || [];
                    @this.set('desired_lease_length', selectedValues, true);
                    var otherWrapper = document.querySelector('.other_lease_term_wrapper');
                    if (otherWrapper) {
                        otherWrapper.style.display = selectedValues.includes('Other') ? 'block' : 'none';
                    }
                });
            }

            initLeaseTermSelect2();

            function handleOtherToggle(selectId, wrapperId) {
                var $select = $('#' + selectId);
                var $wrapper = $('#' + wrapperId);
                if (!$select.length || !$wrapper.length) return;
                function toggle() {
                    var val = $select.val();
                    var show = Array.isArray(val) ? val.includes('Other') : val === 'Other';
                    show ? $wrapper.show() : $wrapper.hide();
                }
                toggle();
                $select.off('change.otherToggle').on('change.otherToggle', toggle);
            }

            var singleSelectOtherPairs = [
                { selectId: 'flood_zone_code',              wrapperId: 'flood_zone_code_other_wrapper' },
                { selectId: 'association_type',             wrapperId: 'association_type_other_wrapper' },
                { selectId: 'association_fee_frequency',    wrapperId: 'association_fee_frequency_other_wrapper' },
                { selectId: 'min_lease_period',             wrapperId: 'min_lease_period_other_wrapper' },
                { selectId: 'commercial_lease_type',        wrapperId: 'commercial_lease_type_other_wrapper' },
            ];

            function initSingleSelectOtherToggles() {
                singleSelectOtherPairs.forEach(function(pair) {
                    handleOtherToggle(pair.selectId, pair.wrapperId);
                });
            }

            initSingleSelectOtherToggles();

            function initTaxLegalSelect2() {
                if ($('#association_fee_includes').length && !$('#association_fee_includes').hasClass('select2-hidden-accessible')) {
                    $('#association_fee_includes').select2({
                        placeholder: 'Select',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $('#association_fee_includes').on('change', function() {
                        var vals = $(this).val() || [];
                        @this.set('association_fee_includes', vals, true);
                        var otherSection = document.getElementById('hoa-fee-includes-other-section');
                        if (otherSection) {
                            otherSection.style.display = vals.includes('Other') ? 'block' : 'none';
                        }
                    });
                }

                if ($('#association_amenities').length && !$('#association_amenities').hasClass('select2-hidden-accessible')) {
                    $('#association_amenities').select2({
                        placeholder: 'Select',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
                    $('#association_amenities').on('change', function() {
                        var vals = $(this).val() || [];
                        @this.set('association_amenities', vals, true);
                        var otherSection = document.getElementById('hoa-amenities-other-section');
                        if (otherSection) {
                            otherSection.style.display = vals.includes('Other') ? 'block' : 'none';
                        }
                    });
                }

            }

            initTaxLegalSelect2();

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
            Livewire.hook('message.processed', () => {
                initLeaseTermSelect2();
                initTaxLegalSelect2();
                initLeaseMetaSelect2();
                initSingleSelectOtherToggles();
                if (typeof reformatAllMoneyFields === 'function') reformatAllMoneyFields();
                // Re-sync structural Select2 fields from Livewire after import apply
                // (wire:ignore prevents DOM replacement, so Select2 must be updated manually)
                var structuralResync = {
                    'roof_type':             '#roof_type_landlord_res',
                    'exterior_construction': '#exterior_construction_landlord_res',
                    'foundation':            '#foundation_landlord_res',
                };
                Object.entries(structuralResync).forEach(function([field, selector]) {
                    var $el = $(selector);
                    if (!$el.length || !$el.hasClass('select2-hidden-accessible')) return;
                    var lwVal = @this.get(field) || [];
                    var cur = $el.val() || [];
                    if (JSON.stringify(lwVal.slice().sort()) !== JSON.stringify(cur.slice().sort())) {
                        $el.val(lwVal).trigger('change.select2');
                    }
                });
            });
            }

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
                    // ── Structural fields (Residential — property-preferences tab) ──
                    'roof_type':             '#roof_type_landlord_res',
                    'exterior_construction': '#exterior_construction_landlord_res',
                    'foundation':            '#foundation_landlord_res',
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

            function showLoaderForMinimumTime() {
                videoLoader.style.visibility = "visible";
            }

            // Function to handle video upload
            function handleVideoUpload(event) {
                const file = event.target.files[0];

                if (!validateVideo(file)) return;

                // Trigger Livewire video upload and show the loader
                if (window.Livewire) { Livewire.emit("upload:start"); }
                showLoaderForMinimumTime();
            }

            // Function to handle photo upload
            function handlePhotoUpload(event) {
                const file = event.target.files[0];

                if (!validatePhoto(file)) return;

                // Trigger Livewire photo upload and show the loader
                if (window.Livewire) { Livewire.emit("upload:start"); }
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
            if (window.Livewire) {
                Livewire.on("upload:start", () => {
                    showLoaderForMinimumTime();
                });

                Livewire.on("upload:finish", () => {
                    videoLoader.style.visibility = "hidden";
                });
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
                        const _fIsEmpty = (
                            field.type === 'file'     ? !field.files || field.files.length === 0 :
                            field.type === 'checkbox' ? !field.checked :
                            field.type === 'radio'    ? !document.querySelector(`input[name="${field.name}"]:checked`) :
                            !field.value?.toString().trim()
                        );
                        if (_fIsEmpty) {
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

            } catch (_setupErr) { console.warn('[landlord offer-listing] initializeFullService setup error:', _setupErr); }
            window._wizardNextHandler = function() {
                const currentTab = document.querySelector('#myTab .nav-link.active');
                if (!currentTab) return;

                const currentTabContent = document.querySelector(currentTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                // Hide any previous validation error banner
                const _valBanner = document.getElementById('submit-error-banner');
                if (_valBanner) _valBanner.classList.add('d-none');

                let isValid = true;

                // Validate all required fields in the current tab (your existing code)
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

                // Validate cities array (your existing code)
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

                // If all fields are valid, proceed to the next tab (your existing code)
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
                            sessionStorage.setItem('landlord_create_active_tab', _nId);
                            var _we = document.querySelector('[wire\\:id]');
                            if (_we && window.Livewire) {
                                var _nComp = window.Livewire.find(_we.getAttribute('wire:id'));
                                if (_nComp) _nComp.call('setActiveTab', _curIdx + 1);
                            }
                        }
                    }
                } else {
                    // Show validation error banner so the user sees why Next is blocked
                    var _errBanner = document.getElementById('submit-error-banner');
                    var _errList   = document.getElementById('submit-error-list');
                    if (_errBanner && _errList) {
                        _errList.innerHTML = '';
                        var _seen = new Set();
                        currentTabContent.querySelectorAll('.is-invalid').forEach(function(f) {
                            var _lbl = f.closest('.form-group') && f.closest('.form-group').querySelector('label');
                            var _name = _lbl ? _lbl.textContent.replace(/[*:]/g, '').trim() : (f.placeholder || f.name || 'Required field');
                            if (_name && !_seen.has(_name)) { _seen.add(_name); var _li = document.createElement('li'); _li.textContent = _name; _errList.appendChild(_li); }
                        });
                        currentTabContent.querySelectorAll('.error').forEach(function(ec) {
                            var _t = ec.textContent.trim();
                            if (_t && _t !== 'This field is required.') {
                                var _lbl = ec.closest('.form-group') && ec.closest('.form-group').querySelector('label');
                                var _fn = _lbl ? _lbl.textContent.replace(/[*:]/g, '').trim() : null;
                                if (_fn && !_seen.has(_fn)) { _seen.add(_fn); var _li = document.createElement('li'); _li.textContent = _fn; _errList.appendChild(_li); }
                            }
                        });
                        _errBanner.querySelector('strong').textContent = 'Please complete the required fields before continuing.';
                        _errBanner.classList.remove('d-none');
                        _errBanner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                // Update save button state after validation (your existing code)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !isValid;
                    saveButton.classList.toggle('disabled', !isValid);
                }
            };

            window._wizardBackHandler = function() {
                const _allTabs = Array.from(document.querySelectorAll('#myTab .nav-link'));
                const _curTab = _allTabs.find(t => t.classList.contains('active'));
                if (!_curTab) return;
                const _curIdx = _allTabs.indexOf(_curTab);
                if (_curIdx <= 0) return;
                const _prevTab = _allTabs[_curIdx - 1];
                var _pId = _prevTab.getAttribute('data-bs-target');
                if (!_pId) return;
                window._manualTabSwitch(_pId);
                sessionStorage.setItem('landlord_create_active_tab', _pId);
            };

            // Run initial validity check now (already inside DOMContentLoaded when called)
            setTimeout(function() {
                checkFormValidity();
                document.querySelectorAll('input, select, textarea').forEach(function(field) {
                    field.addEventListener('change', checkFormValidity);
                    field.addEventListener('keyup', checkFormValidity);
                });
            }, 150);

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', function() {
                    setTimeout(checkFormValidity, 150);
                });
            }


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

            // Run initial validity check now (already inside DOMContentLoaded when called)
            setTimeout(function() {
                checkFormValidity();
                document.querySelectorAll('input, select, textarea').forEach(function(field) {
                    field.addEventListener('change', checkFormValidity);
                    field.addEventListener('keyup', checkFormValidity);
                });
            }, 150);

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', function() {
                    setTimeout(checkFormValidity, 150);
                });
            }
        }



        // Direct DOM tab switch — bypasses Bootstrap Tab API entirely
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

        // Delegated wizard nav — bound once, survives Livewire DOM morphing
        if (!window.__wizardNavBound) {
            window.__wizardNavBound = true;
            document.addEventListener('click', function(e) {
                var nextBtn = e.target.closest('.wizard-step-next');
                if (nextBtn && typeof window._wizardNextHandler === 'function' && !nextBtn.onclick) {
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


        if (window.Livewire && typeof window.Livewire.hook === 'function') {
        Livewire.hook('message.processed', () => {
            var _scrollY = window.scrollY || document.documentElement.scrollTop || 0;

            addIconsToInputs();

            let newServiceType = 'full_service';

            if (newServiceType !== currentServiceType) {
                currentServiceType = newServiceType;
            }

            removeWizardEventListeners();

            if (currentServiceType === 'full_service') {
                initializeFullService();
            }

            var _savedTabId = sessionStorage.getItem('landlord_create_active_tab');
            if (_savedTabId && typeof window._manualTabSwitch === 'function') {
                var _tabTrigger = document.querySelector('#myTab .nav-link[data-bs-target="' + _savedTabId + '"]');
                if (_tabTrigger && !_tabTrigger.classList.contains('active')) {
                    window._manualTabSwitch(_savedTabId);
                }
            }

            requestAnimationFrame(() => { window.scrollTo(0, _scrollY); });
        });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const saveButton = document.getElementById('save-button');
            const formContainer = document.getElementById('wizard-form-container');

            // Get all required fields from active tabs
            function getAllRequiredFields() {
                const requiredFields = [];

                const tabSelector = [
                    '#listing-details',
                    '#property-details',
                    '#leasing-terms',
                    '#additional-details',
                    '#applicant-requirements',
                    '#broker-compensation-agency-agreement-terms',
                    '#tax-legal-hoa-disclosures',
                    '#documents-disclosures',
                    '#photos-tours-documents',
                    '#ai-questions',
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
                if (!isElementVisible(field)) return true;
                if (field.type === 'file') return field.files && field.files.length > 0;
                if (field.type === 'checkbox') return field.checked;
                if (field.type === 'radio') return !!document.querySelector(`input[name="${field.name}"]:checked`);
                if (field.type === 'select-one' || field.type === 'select-multiple') {
                    return field.value !== '' && field.value !== null;
                }
                return field.value?.toString().trim() !== '';
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

                for (const field of requiredFields) {
                    if (!isFieldVisibleAndEnabled(field)) continue;
                    if (!isFieldValid(field)) {
                        const tab = field.closest('.tab-pane');
                        const tabIndex = [...document.querySelectorAll('.tab-pane')].indexOf(tab) + 1;
                        invalidFields.push({
                            tab: tabIndex,
                            field: field.name || field.id,
                            value: field.value
                        });
                    }
                }

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
                    if (!isElementVisible(field)) return;
                    // Select2-initialised multi-selects: checked via Livewire property below
                    if (field.tagName === 'SELECT' && field.multiple &&
                        field.classList.contains('select2-hidden-accessible')) return;
                    var isEmpty = (
                        field.type === 'file'     ? !field.files || field.files.length === 0 :
                        field.type === 'checkbox' ? !field.checked :
                        field.type === 'radio'    ? !document.querySelector(`input[name="${field.name}"]:checked`) :
                        field.type === 'select-one' || field.type === 'select-multiple'
                            ? field.value === '' || field.value === null || field.value === undefined
                            : !field.value?.toString().trim()
                    );
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
                        var _exitWc = _submitTrigger.getAttribute('wire:click') || '';
                        var _exitM  = _exitWc.match(/setActiveTab\((\d+)\)/);
                        try { @this.call('setActiveTab', _exitM ? parseInt(_exitM[1]) : 9); } catch(exExit) {}
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

        function reformatAllMoneyFields() {
            var moneyFields = [
                'desired_rental_amount', 'starting_rent', 'reserve_rent', 'lease_now_price',
                'security_deposit_required', 'total_move_in_funds_required',
                'annual_property_taxes', 'annual_cdd_fee', 'special_assessment_amount',
                'association_fee_amount', 'association_application_fee', 'lease_fee_flat',
                'tenant_broker_flat_fee'
            ];
            moneyFields.forEach(function(field) {
                var el = document.querySelector('[wire\\:model="' + field + '"]')
                      || document.querySelector('[wire\\:model\\.lazy="' + field + '"]')
                      || document.getElementById(field);
                if (el && el.value) { reformatNumber(el); }
            });
        }
        document.addEventListener('DOMContentLoaded', reformatAllMoneyFields);

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

    </script>
    <script>
        (function () {
            function syncWizardButtons() {
                var allPanes = document.querySelectorAll('#myTabContent > .tab-pane');
                var lastPane = allPanes[allPanes.length - 1];
                var nextBtn = document.querySelector('.wizard-step-next');
                var finishBtn = document.querySelector('.wizard-step-finish');
                if (!nextBtn || !finishBtn) return;
                var onLast = !!lastPane && lastPane.classList.contains('show') && lastPane.classList.contains('active');
                nextBtn.style.display = onLast ? 'none' : '';
                finishBtn.style.display = onLast ? '' : 'none';
            }
            document.addEventListener('shown.bs.tab', syncWizardButtons);
            document.addEventListener('DOMContentLoaded', syncWizardButtons);
        })();
    </script>
    <script>
        var _landlordAutofilling = false;

        function landlordAutoFillLeaseNow() {
            var desired  = document.getElementById('landlord_desired_lease_price');
            var leaseNow = document.getElementById('landlord_lease_now_price');
            if (!desired || !leaseNow) return;
            // Fill if empty OR if previously auto-filled (user hasn't manually edited it yet)
            if (!leaseNow.value.trim() || leaseNow.dataset.autofilled === '1') {
                _landlordAutofilling = true;
                leaseNow.value = desired.value;
                leaseNow.dataset.autofilled = '1';
                leaseNow.dispatchEvent(new Event('input', { bubbles: true }));
                _landlordAutofilling = false;
            }
        }

        document.addEventListener('input', function (e) {
            // User manually typed into Lease Now — clear the autofilled marker
            if (e.target && e.target.id === 'landlord_lease_now_price' && !_landlordAutofilling) {
                e.target.dataset.autofilled = '';
            }
            // Desired Lease Price changed — trigger auto-fill
            if (e.target && e.target.id === 'landlord_desired_lease_price') {
                landlordAutoFillLeaseNow();
            }
        });

        document.addEventListener('livewire:load', function () {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', function () {
                    landlordAutoFillLeaseNow();
                });
            }
        });
    </script>
    <script>
        function initSelect2Fields() {
            $('.select2-multiple').each(function () {
                var $el = $(this);

                if ($el.hasClass('select2-hidden-accessible')) {
                    return;
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

        $(document).ready(function () {
            initSelect2Fields();
            addIconsToInputs();
        });

        document.addEventListener('livewire:load', function () {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', function () {
                    initSelect2Fields();
                });
            }
        });
    </script>
    <script>
        // pet_policy_requirement — multi-select bridge (Applicant Requirements tab).
        // select2-stable.js may call initUninitialized() via shown.bs.tab and initialize
        // Select2 WITHOUT attaching our handler. So we ALWAYS re-attach the handler
        // (namespaced .ppr prevents duplicates) regardless of init state.
        function initPetPolicyRequirementSelect2() {
            if (!$('#pet_policy_requirement').length) return;
            // Initialize Select2 only if not already done by another code path.
            if (!$('#pet_policy_requirement').hasClass('select2-hidden-accessible')) {
                $('#pet_policy_requirement').select2({
                    placeholder: "Select all that apply",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
                // Restore server-saved values into the freshly-initialized widget.
                var _pprSaved = @json($this->pet_policy_requirement ?? []);
                if (!Array.isArray(_pprSaved)) _pprSaved = [];
                if (_pprSaved.length) { $('#pet_policy_requirement').val(_pprSaved).trigger('change'); }
            }
            // Always re-attach using a namespace so duplicates are replaced, not stacked.
            $('#pet_policy_requirement')
                .off('select2:select.ppr select2:unselect.ppr')
                .on('select2:select.ppr select2:unselect.ppr', function () {
                    var selectedValues = $(this).val() || [];
                    var hasAllowed = selectedValues.some(function(v) { return v.toLowerCase().includes('allowed'); });
                    $('#pet-restrictions-wrapper').css('display', hasAllowed ? 'block' : 'none');
                    @this.set('pet_policy_requirement', selectedValues, true);
                });
            // Sync wrapper visibility to whatever is currently selected.
            var _currentVals = $('#pet_policy_requirement').val() || [];
            var _hasAllowed = _currentVals.some(function(v) { return v.toLowerCase().includes('allowed'); });
            if (_hasAllowed) { $('#pet-restrictions-wrapper').css('display', 'block'); }
        }
        $(document).ready(function () { initPetPolicyRequirementSelect2(); });
        // Re-run after Bootstrap makes the tab visible (shown.bs.tab fires before Livewire round-trip).
        $(document).on('shown.bs.tab', '#applicant-requirements-tab', function () {
            setTimeout(function () { initPetPolicyRequirementSelect2(); }, 150);
        });
        document.addEventListener('livewire:load', function () {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', function () { initPetPolicyRequirementSelect2(); });
            }
        });
    </script>
    <script>
    var _landlordPlacesNode = null;
    window.byoInitLandlordOfferPlaces = function() {
        var input = document.getElementById('landlord-offer-street-address');
        // Early-return when Maps API is not ready yet. _landlordPlacesNode is intentionally
        // NOT assigned here — the assignment only happens after a successful Autocomplete
        // construction below. This means the guard on the next line is safe: if we returned
        // early on a previous call (API not ready), _landlordPlacesNode is still null, so the
        // next call will try again. Do not move the assignment above this guard.
        if (!input || !window.google || !window.google.maps || !window.google.maps.places) { return; }
        if (input === _landlordPlacesNode) { return; }
        _landlordPlacesNode = input;

        var ac = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            componentRestrictions: { country: 'us' },
            fields: ['address_components', 'geometry', 'place_id']
        });

        google.maps.event.addDomListener(input, 'keydown', function(e) {
            if (e.keyCode === 13) { e.preventDefault(); }
        });

        ac.addListener('place_changed', function() {
            var place = ac.getPlace();
            if (!place || !place.geometry || !place.geometry.location) { return; }

            var lat = place.geometry.location.lat();
            var lng = place.geometry.location.lng();
            var placeId = place.place_id || '';

            var streetNum = '', route = '', city = '', county = '', state = '', zip = '';

            if (place.address_components) {
                place.address_components.forEach(function(c) {
                    var t = c.types;
                    if (t.indexOf('street_number') !== -1)                 streetNum = c.long_name;
                    if (t.indexOf('route') !== -1)                         route     = c.long_name;
                    if (t.indexOf('locality') !== -1)                      city      = c.long_name;
                    if (t.indexOf('sublocality_level_1') !== -1 && !city)  city      = c.long_name;
                    if (t.indexOf('administrative_area_level_2') !== -1)   county    = c.long_name.replace(/ County$/, '');
                    if (t.indexOf('administrative_area_level_1') !== -1)   state     = c.short_name;
                    if (t.indexOf('postal_code') !== -1)                   zip       = c.long_name;
                });
            }

            var street = streetNum ? (streetNum + ' ' + route).trim() : route;

            @this.call('fillFromGooglePlaces', street, city, county, state, zip, String(lat), String(lng), placeId);
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.byoInitLandlordOfferPlaces && window.byoInitLandlordOfferPlaces();
    });
    document.addEventListener('livewire:load', function () {
        window.byoInitLandlordOfferPlaces && window.byoInitLandlordOfferPlaces();
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            Livewire.hook('message.processed', function () {
                window.byoInitLandlordOfferPlaces && window.byoInitLandlordOfferPlaces();
            });
        }
    });
    </script>
    <x-google-maps-script callback="byoInitLandlordOfferPlaces" />
@endpush
