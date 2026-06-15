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

        .seller-compact-textarea {
            min-height: 50px;
            height: 50px;
            resize: vertical;
        }

        .seller-icon-deep-pad {
            padding-left: 56px;
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
    ['name' => 'Other', 'class' => 'vacant-land-length' ,'id'=>'vacant-land-length-other']

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

                <div id="wizard-form-container" class="container pt-5 pb-5">

                    <form wire:submit.prevent="update">
                        <!-- Tab Navigation -->

                        @php
                                $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent';
                                $hasFinancialTab = in_array($property_type ?? '', ['Income', 'Commercial', 'Business']);
                                $saleTermsIdx        = $hasFinancialTab ? 3 : 2;
                                $additionalDetailsIdx = $hasFinancialTab ? 4 : 3;
                                $brokerCompIdx       = $hasFinancialTab ? 5 : 4;
                                $taxLegalIdx         = $hasFinancialTab ? 6 : 5;
                                $docsIdx             = $hasFinancialTab ? 7 : 6;
                                $photosIdx           = $hasFinancialTab ? 8 : 7;
                                $sellerInfoIdx       = $hasFinancialTab ? 9 : 8;
                                $aiIdx               = $hasFinancialTab ? 10 : 9;
                            @endphp

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 0 ? 'active' : '' }}"
                                        id="listing-details-tab" data-bs-toggle="tab"
                                        data-bs-target="#listing-details"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(0)"
                                        aria-controls="listing-details"
                                        aria-selected="{{ $activeTab === 0 ? 'true' : 'false' }}">
                                        Listing Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 1 ? 'active' : '' }}"
                                        id="property-details-tab" data-bs-toggle="tab"
                                        data-bs-target="#property-details"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(1)"
                                        aria-controls="property-details"
                                        aria-selected="{{ $activeTab === 1 ? 'true' : 'false' }}">
                                        Property Details
                                    </button>
                                </li>
                                @if ($hasFinancialTab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 2 ? 'active' : '' }}"
                                        id="financial-details-tab" data-bs-toggle="tab"
                                        data-bs-target="#financial-details"
                                        type="button" role="tab"
                                        wire:click="setActiveTab(2)"
                                        aria-controls="financial-details"
                                        aria-selected="{{ $activeTab === 2 ? 'true' : 'false' }}">
                                        Financial Details
                                    </button>
                                </li>
                                @endif
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $saleTermsIdx ? 'active' : '' }}"
                                        id="sale-terms-tab" data-bs-toggle="tab"
                                        data-bs-target="#sale-terms"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $saleTermsIdx }})"
                                        aria-controls="sale-terms"
                                        aria-selected="{{ $activeTab === $saleTermsIdx ? 'true' : 'false' }}">
                                        Sale Terms
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $additionalDetailsIdx ? 'active' : '' }}"
                                        id="additional-details-tab" data-bs-toggle="tab"
                                        data-bs-target="#additional-details"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $additionalDetailsIdx }})"
                                        aria-controls="additional-details"
                                        aria-selected="{{ $activeTab === $additionalDetailsIdx ? 'true' : 'false' }}">
                                        Description
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $brokerCompIdx ? 'active' : '' }}"
                                        id="broker-compensation-agency-agreement-terms-tab" data-bs-toggle="tab"
                                        data-bs-target="#broker-compensation-agency-agreement-terms"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $brokerCompIdx }})"
                                        aria-controls="broker-compensation-agency-agreement-terms"
                                        aria-selected="{{ $activeTab === $brokerCompIdx ? 'true' : 'false' }}">
                                        Broker Compensation &amp; Agency Agreement Terms
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $taxLegalIdx ? 'active' : '' }}"
                                        id="tax-legal-hoa-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#tax-legal-hoa-disclosures"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $taxLegalIdx }})"
                                        aria-controls="tax-legal-hoa-disclosures"
                                        aria-selected="{{ $activeTab === $taxLegalIdx ? 'true' : 'false' }}">
                                        Tax, Legal &amp; HOA
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $docsIdx ? 'active' : '' }}"
                                        id="documents-disclosures-tab" data-bs-toggle="tab"
                                        data-bs-target="#documents-disclosures"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $docsIdx }})"
                                        aria-controls="documents-disclosures"
                                        aria-selected="{{ $activeTab === $docsIdx ? 'true' : 'false' }}">
                                        Documents &amp; Disclosures
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $photosIdx ? 'active' : '' }}"
                                        id="photos-tours-documents-tab" data-bs-toggle="tab"
                                        data-bs-target="#photos-tours-documents"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $photosIdx }})"
                                        aria-controls="photos-tours-documents"
                                        aria-selected="{{ $activeTab === $photosIdx ? 'true' : 'false' }}">
                                        Photos &amp; Tours
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $sellerInfoIdx ? 'active' : '' }}"
                                        id="seller-information-tab" data-bs-toggle="tab"
                                        data-bs-target="#seller-information"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $sellerInfoIdx }})"
                                        aria-controls="seller-information"
                                        aria-selected="{{ $activeTab === $sellerInfoIdx ? 'true' : 'false' }}">
                                        Agent Credentials & Contact Info
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $aiIdx ? 'active' : '' }}"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        wire:click="setActiveTab({{ $aiIdx }})"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === $aiIdx ? 'true' : 'false' }}">
                                        AI Knowledge Base
                                    </button>
                                </li>
                            </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="myTabContent">

                            <!-- Listing Details Tab -->
                            <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}" id="listing-details"
                                role="tabpanel" aria-labelledby="listing-details-tab">
                                @include('livewire.offer-listing.offer-seller-tabs.commission-based.listing-details', ['isEditMode' => true])

                            </div>
                                <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}"
                                    id="property-details" role="tabpanel"
                                    aria-labelledby="property-details-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.property-preferences')

                                </div>

                                <!-- Financial Details Tab (index 2, only for Income/Commercial/Business property types) -->
                                @if ($hasFinancialTab)
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}" id="financial-details"
                                    role="tabpanel" aria-labelledby="financial-details-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.financial-details')
                                </div>
                                @endif

                                <!-- Sale Terms Tab -->
                                <div class="tab-pane fade {{ $activeTab === $saleTermsIdx ? 'show active' : '' }}"
                                    id="sale-terms" role="tabpanel" aria-labelledby="sale-terms-tab">

                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms')
                                </div>

                                <!-- Additional Details Tab -->
                                <div class="tab-pane fade {{ $activeTab === $additionalDetailsIdx ? 'show active' : '' }}"
                                    id="additional-details" role="tabpanel" aria-labelledby="additional-details-tab">

                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.additional-details')

                                </div>

                                <!-- Broker Compensation & Agency Agreement Terms Tab -->
                                <div class="tab-pane fade {{ $activeTab === $brokerCompIdx ? 'show active' : '' }}"
                                    id="broker-compensation-agency-agreement-terms" role="tabpanel"
                                    aria-labelledby="broker-compensation-agency-agreement-terms-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.broker-compensation')
                                </div>

                                <!-- Tax, Legal, HOA & Disclosures Tab -->
                                <div class="tab-pane fade {{ $activeTab === $taxLegalIdx ? 'show active' : '' }}"
                                    id="tax-legal-hoa-disclosures" role="tabpanel"
                                    aria-labelledby="tax-legal-hoa-disclosures-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.tax-legal-hoa-disclosures')
                                </div>

                                <!-- Documents & Disclosures Tab -->
                                <div class="tab-pane fade {{ $activeTab === $docsIdx ? 'show active' : '' }}"
                                    id="documents-disclosures" role="tabpanel"
                                    aria-labelledby="documents-disclosures-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.documents-disclosures')
                                </div>

                                <!-- Photos & Tours Tab -->
                                <div class="tab-pane fade {{ $activeTab === $photosIdx ? 'show active' : '' }}"
                                    id="photos-tours-documents" role="tabpanel"
                                    aria-labelledby="photos-tours-documents-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.photos-tours-documents')
                                </div>

                                <!-- Seller Info Tab -->
                                <div class="tab-pane fade {{ $activeTab === $sellerInfoIdx ? 'show active' : '' }}"
                                    id="seller-information" role="tabpanel" aria-labelledby="seller-information-tab">
                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                        @include('livewire.partials.agent-credentials')
                                    @else
                                        @include('livewire.offer-listing.offer-seller-tabs.commission-based.seller-info')
                                    @endif
                                </div>

                                <!-- AI Knowledge Base Tab -->
                                <div class="tab-pane fade {{ $activeTab === $aiIdx ? 'show active' : '' }}" id="ai-questions"
                                    role="tabpanel" aria-labelledby="ai-questions-tab">
                                    @include('livewire.offer-listing.shared.ai-questions-input')
                                </div>
                        </div>
                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between form-group mt-4">
                            <div>
                                <button type="button" class="btn btn-secondary wizard-step-back">Back</button>
                            </div>
                            <div>
                                @if($isListingDraft)
                                <button type="button" class="btn btn-outline-primary me-2" onclick="syncAllSelect2BeforeSave(); @this.call('saveDraftOnly');" wire:loading.attr="disabled" wire:target="saveDraftOnly">
                                    <span wire:loading.remove wire:target="saveDraftOnly"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraftOnly">Saving...</span>
                                </button>
                                @else
                                <button type="button" class="btn btn-outline-primary me-2" onclick="syncAllSelect2BeforeSave(); @this.call('update');" wire:loading.attr="disabled" wire:target="update">
                                    <span wire:loading.remove wire:target="update"><i class="fa-solid fa-save me-1"></i> Save Edit</span>
                                    <span wire:loading wire:target="update">Saving...</span>
                                </button>
                                @endif

                                <button wire:ignore type="button" class="btn btn-primary wizard-step-next"
                                    onclick="if(typeof window._wizardNextHandler==='function'){window._wizardNextHandler();}">Next</button>

                                <button wire:ignore type="submit" class="btn btn-success wizard-step-finish disabled"
                                    id="save-button" wire:loading.attr="disabled" wire:target="update"
                                    @if(!$isListingDraft) style="display:none;" @endif>
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

@if($listingId && $canViewLocationDnaPanel)
<div class="container-fluid mt-2 mb-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            @include('partials.location-dna-agent-panel', [
                'listingType'            => 'seller_agent',
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
        let _s2Timers = {};
        let _lastInitTime = 0;

        function debouncedSet(field, value, delay) {
            clearTimeout(_s2Timers[field]);
            _s2Timers[field] = setTimeout(function() {
                @this.set(field, value);
            }, delay || 200);
        }

        var _mlsFieldSelectors = {
            roof_type:              ['#roof_type_residential', '#roof_type_commercial', '#roof_type_business'],
            exterior_construction:  ['#exterior_construction_residential', '#exterior_construction_commercial', '#exterior_construction_business'],
            foundation:             ['#foundation_residential', '#foundation_commercial', '#foundation_business'],
            heating_and_fuel:       ['#heating_and_fuel_residential', '#heating_and_fuel_commercial', '#heating_and_fuel_business'],
            air_conditioning:       ['#air_conditioning_residential', '#air_conditioning_commercial', '#air_conditioning_business'],
            water:                  ['#water_residential', '#water_commercial', '#water_business', '#water_vacant_land'],
            sewer:                  ['#sewer_residential', '#sewer_commercial', '#sewer_business', '#sewer_vacant_land'],
            utilities:              ['#utilities_residential', '#utilities_commercial', '#utilities_business', '#utilities_vacant_land'],
            road_frontage:          ['#road_frontage_commercial', '#road_frontage_vacant_land'],
            road_surface_type:      ['#road_surface_type_commercial', '#road_surface_type_vacant_land'],
            electrical_service:     ['#electrical_service_commercial', '#electrical_service_business'],
            building_features:      ['#building_features'],
            licenses:               ['#licenses'],
            sale_includes:          ['#sale_includes'],
            current_use:            ['#current_use'],
            current_adjacent_use:   ['#current_adjacent_use'],
            fences:                 ['#fences'],
            vegetation:             ['#vegetation'],
            easements:              ['#easements'],
            water_access:           ['#water_access'],
            water_view:             ['#water_view'],
            interior_features:      ['#interior_features'],
        };

        function syncAllSelect2BeforeSave() {
            Object.keys(_s2Timers).forEach(function(k) { clearTimeout(_s2Timers[k]); });
            _s2Timers = {};
            var regularFields = {
                property_items: '#property_style_select',
                non_negotiable_amenities: '#non_negotiable_amenities',
                appliances: '#appliances',
                exchange_item: '#exchange_item',
                business_assets: '#included_assets',
                sale_provision: '#sale_provision',
                offered_financing: '#offered_financing',
                view_preference: '#view_preference',
                association_fee_includes: '#association_fee_includes',
                association_amenities: '#association_amenities',
            };
            Object.entries(regularFields).forEach(function([field, selector]) {
                var el = $(selector);
                if (el.length && el.hasClass('select2-hidden-accessible')) {
                    @this.set(field, el.val() || []);
                }
            });
            Object.entries(_mlsFieldSelectors).forEach(function([fieldId, selectors]) {
                selectors.forEach(function(selector) {
                    var el = $(selector);
                    if (el.length && el.hasClass('select2-hidden-accessible')) {
                        @this.set(fieldId, el.val() || []);
                    }
                });
            });
        }

        document.addEventListener('submit', function(e) {
            syncAllSelect2BeforeSave();
        }, true);

        Object.entries(_mlsFieldSelectors).forEach(function([fieldId, selectors]) {
            selectors.forEach(function(selector) {
                $(document).on('change', selector, function() {
                    var selectedValues = $(this).val() || [];
                    @this.set(fieldId, selectedValues, false);
                    var otherWrapper = document.getElementById('other_' + this.id + '_wrapper');
                    if (otherWrapper) {
                        otherWrapper.style.display = selectedValues.includes('Other') ? '' : 'none';
                    }
                });
            });
        });

        if (!document._editAppliancesDelegateAdded) {
            document._editAppliancesDelegateAdded = true;
            $(document).on('change', '#appliances', function() {
                var vals = $(this).val() || [];
                $('#other_appliances').toggle(vals.includes('Other'));
                @this.set('appliances', vals, false);
            });
        }

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
        $(document).on('change', '#association_fee_includes', function() {
            var selectedValues = $(this).val() || [];
            @this.set('association_fee_includes', selectedValues, false);
            $('#hoa-fee-includes-other-section').toggle(selectedValues.includes('Other'));
        });
        $(document).on('change', '#association_amenities', function() {
            var selectedValues = $(this).val() || [];
            @this.set('association_amenities', selectedValues, false);
            $('#hoa-amenities-other-section').toggle(selectedValues.includes('Other'));
        });
        $(document).on('change', '#association_fee_frequency', function() {
            $('#association-fee-frequency-other-section').toggle($(this).val() === 'Other');
        });

        function initializeMlsPropertyMultiSelects() {
            var _mlsFields = [
                { id: 'roof_type', placeholder: 'Select' },
                { id: 'exterior_construction', placeholder: 'Select' },
                { id: 'foundation', placeholder: 'Select' },
                { id: 'heating_and_fuel', placeholder: 'Select' },
                { id: 'air_conditioning', placeholder: 'Select' },
                { id: 'water', placeholder: 'Select' },
                { id: 'sewer', placeholder: 'Select' },
                { id: 'utilities', placeholder: 'Select' },
                { id: 'road_frontage', placeholder: 'Select' },
                { id: 'road_surface_type', placeholder: 'Select' },
                { id: 'electrical_service', placeholder: 'Select' },
                { id: 'building_features', placeholder: 'Select' },
                { id: 'licenses', placeholder: 'Select' },
                { id: 'sale_includes', placeholder: 'Select' },
                { id: 'current_use', placeholder: 'Select' },
                { id: 'current_adjacent_use', placeholder: 'Select' },
                { id: 'fences', placeholder: 'Select' },
                { id: 'vegetation', placeholder: 'Select' },
                { id: 'easements', placeholder: 'Select' },
                { id: 'water_access', placeholder: 'Select', otherId: 'other_water_access_wrapper' },
                { id: 'water_view', placeholder: 'Select', otherId: 'other_water_view_wrapper' },
                { id: 'interior_features', placeholder: 'Select', otherId: 'other_interior_features_wrapper' },
            ];
            _mlsFields.forEach(function(field) {
                var selectors = _mlsFieldSelectors[field.id] || ['#' + field.id];
                selectors.forEach(function(selector) {
                    var $el = $(selector);
                    if ($el.length && !$el.hasClass('select2-hidden-accessible')) {
                        $el.select2({ placeholder: field.placeholder, allowClear: true, width: '100%', closeOnSelect: false });
                    }
                    if ($el.length) {
                        var current = $el.val() || [];
                        var elId = selector.substring(1);
                        var otherWrapper = document.getElementById('other_' + elId + '_wrapper');
                        if (otherWrapper) {
                            otherWrapper.style.display = current.includes('Other') ? '' : 'none';
                        }
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            currentServiceType = 'full_service';
            initializeFullService();

            addIconsToInputs();
            setTimeout(function() { addIconsToInputs(); }, 0);
            checkRepresentationStatus();

            // Polling safety net: re-apply provision/financing section visibility
            // within 100ms of any Select2 change, mirroring the Create blade behaviour.
            var _lastProvVal = '';
            var _lastFinVal = '';
            setInterval(function() {
                var $sp = $('#sale_provision');
                var $of = $('#offered_financing');
                if ($sp.length) {
                    var pv = JSON.stringify($sp.val() || []);
                    if (pv !== _lastProvVal) { _lastProvVal = pv; applyProvisionVisibility(); }
                }
                if ($of.length) {
                    var fv = JSON.stringify($of.val() || []);
                    if (fv !== _lastFinVal) { _lastFinVal = fv; applyFinancingVisibility(); }
                }
            }, 100);
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

            // Initialize new service logic — delegated listener on document handles
            // wizard nav clicks via window._wizardNextHandler; no need to clone/replace buttons
            if (serviceType === 'full_service') {
                initializeFullService();
            }

            if (window.Livewire) { Livewire.emit('serviceTypeChanged', serviceType); }
        }

        function initializeFullService() {


            if ($('#property_style_select').length && !$('#property_style_select').hasClass('select2-hidden-accessible')) {
                $('#property_style_select').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: Infinity,
                });
                $('#property_style_select').data('last-prop-type', @this.get('property_type') || '');
                var _pssInit = @this.get('property_items') || [];
                if (_pssInit.length > 0) {
                    $('#property_style_select').val(_pssInit).trigger('change.select2');
                }
                $('#property_style_select').off('change.pss').on('change.pss', function(e) {
                    let selectedValues = $(this).val();
                    debouncedSet('property_items', selectedValues);
                });
            }

            if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                $('#non_negotiable_amenities').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                $('#non_negotiable_amenities').on('change', function(e) {
                    let selectedValues = $(this).val();
                    debouncedSet('non_negotiable_amenities', selectedValues);
                });
            }

            if ($('#appliances').length && !$('#appliances').hasClass('select2-hidden-accessible')) {
                $('#appliances').select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
            }
            if ($('#appliances').length) {
                var savedAppliances = @this.get('appliances') || [];
                if (savedAppliances.length > 0) {
                    var currentAppliances = $('#appliances').val() || [];
                    if (currentAppliances.length === 0) {
                        $('#appliances').val(savedAppliances).trigger('change.select2');
                    }
                }
                $('#other_appliances').toggle(($('#appliances').val() || []).includes('Other'));
            }

            if ($('#exchange_item').length && !$('#exchange_item').hasClass('select2-hidden-accessible')) {
                $('#exchange_item').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                var savedExchangeItems = @this.get('exchange_item') || [];
                if (savedExchangeItems.length > 0) {
                    $('#exchange_item').val(savedExchangeItems).trigger('change.select2');
                }
                $('#other_exchange_item_wrapper').toggle((savedExchangeItems || []).includes('Other'));
                $('#exchange_item').on('change', function(e) {
                    var selectedValues = $(this).val() || [];
                    @this.set('exchange_item', selectedValues, false);
                    $('#other_exchange_item_wrapper').toggle(selectedValues.includes('Other'));
                });
            } else if ($('#exchange_item').length && $('#exchange_item').hasClass('select2-hidden-accessible')) {
                var savedExchangeItems = @this.get('exchange_item') || [];
                var currentVal = $('#exchange_item').val() || [];
                if (savedExchangeItems.length > 0 && currentVal.length === 0) {
                    $('#exchange_item').val(savedExchangeItems).trigger('change.select2');
                }
            }

            if ($('#included_assets').length && !$('#included_assets').hasClass('select2-hidden-accessible')) {
                $('#included_assets').select2({
                    placeholder: "Select included assets",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                var savedBusinessAssets = @this.get('business_assets') || [];
                if (savedBusinessAssets.length > 0) {
                    $('#included_assets').val(savedBusinessAssets).trigger('change.select2');
                }
                $('#included_assets').on('change', function(e) {
                    var selectedValues = $(this).val() || [];
                    @this.set('business_assets', selectedValues, false);
                });
            } else if ($('#included_assets').length && $('#included_assets').hasClass('select2-hidden-accessible')) {
                var savedBusinessAssets = @this.get('business_assets') || [];
                var currentAssets = $('#included_assets').val() || [];
                if (savedBusinessAssets.length > 0 && currentAssets.length === 0) {
                    $('#included_assets').val(savedBusinessAssets).trigger('change.select2');
                }
            }

            initializeMlsPropertyMultiSelects();

            if ($('#offered_financing').length) {
                if (!$('#offered_financing').hasClass('select2-hidden-accessible')) {
                    $('#offered_financing').select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
                }
                applyFinancingVisibility();
            }

            if ($('#sale_provision').length) {
                var _spNewInit = !$('#sale_provision').hasClass('select2-hidden-accessible');
                if (_spNewInit) {
                    $('#sale_provision').select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
                    var _savedProvision = @json($sale_provision ?? []);
                    if (_savedProvision && _savedProvision.length > 0) {
                        $('#sale_provision').val(_savedProvision).trigger('change.select2');
                    }
                }
                applyProvisionVisibility();
            }

            if ($('#association_fee_includes').length && !$('#association_fee_includes').hasClass('select2-hidden-accessible')) {
                $('#association_fee_includes').select2({ placeholder: "Select what the fee includes", allowClear: true, width: '100%', closeOnSelect: false });
            }
            if ($('#association_fee_includes').length) {
                var _fiInit = $('#association_fee_includes').val() || [];
                $('#hoa-fee-includes-other-section').toggle(_fiInit.includes('Other'));
            }

            if ($('#association_amenities').length && !$('#association_amenities').hasClass('select2-hidden-accessible')) {
                $('#association_amenities').select2({ placeholder: "Select amenities", allowClear: true, width: '100%', closeOnSelect: false });
            }
            if ($('#association_amenities').length) {
                var _amInit = $('#association_amenities').val() || [];
                $('#hoa-amenities-other-section').toggle(_amInit.includes('Other'));
            }

            if ($('#association_fee_frequency').length) {
                $('#association-fee-frequency-other-section').toggle($('#association_fee_frequency').val() === 'Other');
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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachBathroomsDropdownListener();
                });
            }





            // Garage/Parking Features "Other" companion visibility.
            // #garage_parking_spaces_option_landlord is the active Select2 multi-select.
            // The old single-select #garage_parking_spaces_option is commented out in the partial.
            function toggleGarageOtherCompanion() {
                var vals = $('#garage_parking_spaces_option_landlord').val() || [];
                $('#other_garage_parking_spaces_option_landlord').toggle(vals.includes('Other'));
            }

            toggleGarageOtherCompanion();

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    toggleGarageOtherCompanion();
                });
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
                    toggleSpaceInput('carport-needed', 'other-carport-needed');
                    toggleSpaceInput('garage-needed', 'other-garage-needed');
                });
            }

            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                $('#view_preference').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                $('#view_preference').on('change', function() {
                    let selectedValues = $(this).val();
                    if (window.Livewire) { Livewire.emit('updatePreference', selectedValues); }
                    if (selectedValues.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                    }
                });
            }

            // Garage/Parking Features Select2 — multi-select, wire:ignore
            if ($('#garage_parking_spaces_option_landlord').length && !$('#garage_parking_spaces_option_landlord').hasClass('select2-hidden-accessible')) {
                $('#garage_parking_spaces_option_landlord').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
            }
            $('#garage_parking_spaces_option_landlord').off('change.garage').on('change.garage', function() {
                var vals = $(this).val() || [];
                @this.set('garage_parking_spaces_option', vals, false);
                toggleGarageOtherCompanion();
            });

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
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', () => {
                    attachItemConditionDropdownListener();
                });
            }

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
                    // Wait for at least 20 seconds before hiding the loader
                    setTimeout(() => {
                        videoLoader.style.visibility = "hidden";
                    }, 30000);
                });
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

                // Enable/disable save button
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !allValid;
                }

                return allValid;
            }

            document.addEventListener('DOMContentLoaded', function() {
                checkFormValidity();

                document.querySelectorAll('input, select, textarea').forEach(field => {
                    field.addEventListener('change', checkFormValidity);
                    field.addEventListener('keyup', checkFormValidity);
                });

                document.addEventListener('livewire:update', function() {
                    setTimeout(checkFormValidity, 100);
                });
            });


        }

        function initializeLimitedService() {

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

                // Enable/disable save button
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !allValid;
                }

                return allValid;
            }

            document.addEventListener('DOMContentLoaded', function() {
                checkFormValidity();

                document.querySelectorAll('input, select, textarea').forEach(field => {
                    field.addEventListener('change', checkFormValidity);
                    field.addEventListener('keyup', checkFormValidity);
                });

                document.addEventListener('livewire:update', function() {
                    setTimeout(checkFormValidity, 100);
                });
            });
        }

        // Wizard Next/Back handlers — defined once at module level, shared by both service types.
        // Uses _manualTabSwitch to avoid bootstrap.Tab event side-effects (double-advance).
        window._wizardNextHandler = function() {
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
                            if (errorMessageContainer) { errorMessageContainer.remove(); }
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
                        citiesContainer.parentNode.insertBefore(citiesError, citiesContainer.nextSibling);
                    }
                } else {
                    const existingError = citiesContainer.parentNode.querySelector('.error');
                    if (existingError) { existingError.remove(); }
                }
            }

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
                        countiesContainer.parentNode.insertBefore(countiesError, countiesContainer.nextSibling);
                    }
                } else {
                    const existingError = countiesContainer.parentNode.querySelector('.error');
                    if (existingError) { existingError.remove(); }
                }
            }

            if (isValid) {
                const _allTabs = Array.from(document.querySelectorAll('#myTab .nav-link'));
                const _curIdx = _allTabs.indexOf(currentTab);
                if (_curIdx < _allTabs.length - 1) {
                    const _nextTabEl = _allTabs[_curIdx + 1];
                    var _nId = _nextTabEl.getAttribute('data-bs-target');
                    if (_nId) {
                        window._manualTabSwitch(_nId);
                        sessionStorage.setItem('seller_edit_active_tab', _nId);
                        if (typeof window._syncWizardButtons === 'function') window._syncWizardButtons();
                        var _wcNext = _nextTabEl.getAttribute('wire:click') || '';
                        var _mNext = _wcNext.match(/setActiveTab\((\d+)\)/);
                        if (_mNext) { @this.call('setActiveTab', parseInt(_mNext[1])); }
                    }
                }
            }

            const saveButton = document.querySelector('.wizard-step-finish');
            if (saveButton) { saveButton.disabled = !isValid; }
        };

        window._wizardBackHandler = function() {
            const _allTabs = Array.from(document.querySelectorAll('#myTab .nav-link'));
            const _curTab = _allTabs.find(t => t.classList.contains('active'));
            if (!_curTab) return;
            const _curIdx = _allTabs.indexOf(_curTab);
            if (_curIdx <= 0) return;
            const _prevTabEl = _allTabs[_curIdx - 1];
            var _pId = _prevTabEl.getAttribute('data-bs-target');
            if (!_pId) return;
            window._manualTabSwitch(_pId);
            sessionStorage.setItem('seller_edit_active_tab', _pId);
            if (typeof window._syncWizardButtons === 'function') window._syncWizardButtons();
            var _wcBack = _prevTabEl.getAttribute('wire:click') || '';
            var _mBack = _wcBack.match(/setActiveTab\((\d+)\)/);
            if (_mBack) { @this.call('setActiveTab', parseInt(_mBack[1])); }
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

        // Direct DOM tab switch — bypasses Bootstrap Tab API entirely, preventing
        // double-advance caused by show.bs.tab/shown.bs.tab side-effects.
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

        // Re-inject icons and re-bind wizard handlers after draft data loads.
        // The delegated listener on `document` is already bound once and survives
        // any DOM replacement, so no button manipulation is needed here.
        window.addEventListener('draftLoaded', function() {
            setTimeout(function() {
                initializeFullService();
                addIconsToInputs();
            }, 50);
        });

        // Single delegated listener for direct nav-link clicks (Bootstrap Tab fires shown.bs.tab).
        // Guarded so it is never registered more than once regardless of Livewire rerenders.
        if (!window.__sellerEditTabShownBound) {
            window.__sellerEditTabShownBound = true;
            document.addEventListener('shown.bs.tab', function(e) {
                if (!e.target.closest('#myTab')) return;
                var targetId = e.target.getAttribute('data-bs-target') || e.target.getAttribute('id');
                if (targetId) {
                    sessionStorage.setItem('seller_edit_active_tab', targetId);
                }
                setTimeout(function() { addIconsToInputs(); }, 0);
            });
        }

        if (window.Livewire && typeof window.Livewire.hook === 'function') {
        Livewire.hook('message.processed', () => {
            addIconsToInputs();
            checkRepresentationStatus();
            // Reformat all money inputs that are not currently focused
            document.querySelectorAll('input[onblur="reformatNumber(this)"]').forEach(function(inp) {
                if (inp === document.activeElement || !inp.value) return;
                var v = inp.value.replace(/,/g, '');
                var parts = v.split('.');
                var intPart = parts[0] || '';
                var decPart = parts[1] || '';
                if (decPart) decPart = decPart.slice(0, 2);
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                inp.value = decPart ? (intPart + '.' + decPart) : intPart;
            });

            // Re-init #property_style_select Select2 when property_type changes so that
            // the correct option list and "Select" placeholder are shown (e.g. Business type)
            (function() {
                var $pss = $('#property_style_select');
                if (!$pss.length) return;
                var _pssType = @this.get('property_type') || '';
                if ($pss.data('last-prop-type') === _pssType) return;
                if ($pss.hasClass('select2-hidden-accessible')) {
                    $pss.select2('destroy');
                }
                $pss.data('last-prop-type', _pssType);
                $pss.select2({ placeholder: 'Select', allowClear: true, width: '100%', minimumResultsForSearch: Infinity });
                var _savedItems = @this.get('property_items') || [];
                if (_savedItems.length > 0) {
                    $pss.val(_savedItems).trigger('change.select2');
                }
                $pss.off('change.pss').on('change.pss', function() {
                    debouncedSet('property_items', $(this).val() || []);
                });
            })();

            var _now = Date.now();
            if (_now - _lastInitTime > 300) {
                _lastInitTime = _now;
                initializeFullService();
            }

            var savedTab = sessionStorage.getItem('seller_edit_active_tab');
            if (savedTab) {
                var tabTrigger = document.querySelector('#myTab .nav-link[data-bs-target="' + savedTab + '"]');
                if (tabTrigger && !tabTrigger.classList.contains('active')) {
                    window._manualTabSwitch(savedTab);
                }
            }
            if (typeof window._syncWizardButtons === 'function') window._syncWizardButtons();
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
                    '#financial-details',
                    '#sale-terms',
                    '#additional-details',
                    '#tax-legal-hoa-disclosures',
                    '#seller-information'
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
                        setupGlobalListeners();
                        updateSaveButton();
                    }, 300);
                });
            }
        });
    </script>
    <script>
        (function () {
            window._syncWizardButtons = function() {
                var aiPane = document.getElementById('ai-questions');
                var nextBtn = document.querySelector('.wizard-step-next');
                var finishBtn = document.querySelector('.wizard-step-finish');
                if (!nextBtn || !finishBtn) return;
                var onAI = !!aiPane && aiPane.classList.contains('show') && aiPane.classList.contains('active');
                nextBtn.style.display = onAI ? 'none' : '';
            };
            // Fires for direct nav-link clicks (Bootstrap Tab API raises shown.bs.tab).
            // _manualTabSwitch calls window._syncWizardButtons() directly for Next/Back.
            document.addEventListener('shown.bs.tab', window._syncWizardButtons);
            document.addEventListener('DOMContentLoaded', window._syncWizardButtons);
        })();
    </script>
    <script>
        if (typeof formatWithCommas !== 'function') {
            function formatWithCommas(input) {
                let value = input.value.replace(/[^\d.]/g, '');
                let parts = value.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                input.value = parts.length > 1 ? parts[0] + '.' + parts[1].substring(0, 2) : parts[0];
            }
        }
        if (typeof validateInput !== 'function') {
            function validateInput(input) {
                let v = input.value;
                v = v.replace(/[^0-9.,]/g, '');
                const firstDot = v.indexOf('.');
                if (firstDot !== -1) {
                    v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
                }
                input.value = v;
            }
        }
        if (typeof reformatNumber !== 'function') {
            function reformatNumber(input) {
                let v = input.value.replace(/,/g, '');
                const parts = v.split('.');
                let intPart = parts[0] || '';
                let decPart = parts[1] || '';
                if (decPart) decPart = decPart.slice(0, 2);
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                input.value = decPart ? (intPart + '.' + decPart) : intPart;
            }
        }
        if (typeof handlePaste !== 'function') {
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
                event.target.value = decPart ? (intPart + '.' + decPart) : intPart;
                event.target.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[onblur="reformatNumber(this)"]').forEach(function(inp) {
                if (!inp.value) return;
                var v = inp.value.replace(/,/g, '');
                var parts = v.split('.');
                var intPart = parts[0] || '';
                var decPart = parts[1] || '';
                if (decPart) decPart = decPart.slice(0, 2);
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                inp.value = decPart ? (intPart + '.' + decPart) : intPart;
            });
        });
    </script>
    <x-google-maps-script callback="byoInitSellerOfferPlaces" />

@endpush
