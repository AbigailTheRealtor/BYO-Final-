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

        .seller-compact-textarea {
            min-height: calc(1.5em + 0.75rem + 2px) !important;
            height: calc(1.5em + 0.75rem + 2px);
            resize: vertical;
        }

        /* Deep left-padding for inputs that have an icon sitting further right */
        .seller-icon-deep-pad {
            padding-left: 56px;
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
                                                        {{ $draft->title }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif <span class="badge bg-success">Current</span> ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                                    </span>
                                                @else
                                                <a class="btn btn-link text-start flex-grow-1"
                                                    href="{{ route('offer.listing.seller.edit', ['auctionId' => $draft->id]) }}">
                                                    {{ $draft->title }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                                </a>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-danger" style="border-color: #dc3545; color: #dc3545;"
                                                    data-bs-dismiss="modal"
                                                    wire:click="deleteDraft('{{ $draft->id }}')" wire:ignore.self
                                                    onclick="setTimeout(() => { window.location = '{{ route('offer.listing.seller')}}' }, 100)">
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
                                        onclick="setTimeout(() => { window.location = '{{ route('offer.listing.seller')}}' }, 100)">
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

                            @php
                                $isAgentUser = auth()->user() && auth()->user()->user_type === 'agent';
                                $hasFinancialTab = $user_type === 'seller' && in_array($property_type, ['Income', 'Commercial', 'Business']);
                                if ($user_type === 'seller') {
                                    $saleTermsIdx        = $hasFinancialTab ? 3 : 2;
                                    $additionalDetailsIdx = $hasFinancialTab ? 4 : 3;
                                    $brokerCompIdx       = $hasFinancialTab ? 5 : 4;
                                    $taxLegalIdx         = $hasFinancialTab ? 6 : 5;
                                    $docsIdx             = $hasFinancialTab ? 7 : 6;
                                    $photosIdx           = $hasFinancialTab ? 8 : 7;
                                    $sellerInfoIdx       = $hasFinancialTab ? 10 : 9;
                                    $aiIdx               = $hasFinancialTab ? 9 : 8;
                                } else {
                                    // Non-seller flows (no Services tab)
                                    $saleTermsIdx        = 2;
                                    $additionalDetailsIdx = 3;
                                    $brokerCompIdx       = 4;
                                    $taxLegalIdx         = 5;
                                    $docsIdx             = 6;
                                    $photosIdx           = 7;
                                    $sellerInfoIdx       = 9;
                                    $aiIdx               = 8;
                                }
                            @endphp

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                {{-- Tabs 0 and 1: always present for all user types --}}
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

                                @if ($user_type === 'seller')
                                    {{-- Financial Details tab: only for Income, Commercial, Business property types --}}
                                    @if ($hasFinancialTab)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === 2 ? 'active' : '' }}"
                                            wire:click="setActiveTab(2)"
                                            id="financial-details-tab" data-bs-toggle="tab"
                                            data-bs-target="#financial-details"
                                            type="button" role="tab"
                                            aria-controls="financial-details"
                                            aria-selected="{{ $activeTab === 2 ? 'true' : 'false' }}">
                                            Financial Details
                                        </button>
                                    </li>
                                    @endif

                                    {{-- Sale Terms --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $saleTermsIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $saleTermsIdx }})"
                                            id="sale-terms-tab" data-bs-toggle="tab"
                                            data-bs-target="#sale-terms"
                                            type="button" role="tab"
                                            aria-controls="sale-terms"
                                            aria-selected="{{ $activeTab === $saleTermsIdx ? 'true' : 'false' }}">
                                            Sale Terms
                                        </button>
                                    </li>

                                    {{-- Description --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $additionalDetailsIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $additionalDetailsIdx }})"
                                            id="additional-details-tab" data-bs-toggle="tab"
                                            data-bs-target="#additional-details"
                                            type="button" role="tab"
                                            aria-controls="additional-details"
                                            aria-selected="{{ $activeTab === $additionalDetailsIdx ? 'true' : 'false' }}">
                                            Description
                                        </button>
                                    </li>

                                    {{-- B7: Broker Compensation tab hidden from client listing form --}}

                                    {{-- Tax, Legal, HOA & Disclosures --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $taxLegalIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $taxLegalIdx }})"
                                            id="tax-legal-hoa-disclosures-tab" data-bs-toggle="tab"
                                            data-bs-target="#tax-legal-hoa-disclosures"
                                            type="button" role="tab"
                                            aria-controls="tax-legal-hoa-disclosures"
                                            aria-selected="{{ $activeTab === $taxLegalIdx ? 'true' : 'false' }}">
                                            Tax, Legal &amp; HOA
                                        </button>
                                    </li>

                                    {{-- Documents & Disclosures --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $docsIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $docsIdx }})"
                                            id="documents-disclosures-tab" data-bs-toggle="tab"
                                            data-bs-target="#documents-disclosures"
                                            type="button" role="tab"
                                            aria-controls="documents-disclosures"
                                            aria-selected="{{ $activeTab === $docsIdx ? 'true' : 'false' }}">
                                            Documents &amp; Disclosures
                                        </button>
                                    </li>

                                    {{-- Photos & Tours --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $photosIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $photosIdx }})"
                                            id="photos-tours-documents-tab" data-bs-toggle="tab"
                                            data-bs-target="#photos-tours-documents"
                                            type="button" role="tab"
                                            aria-controls="photos-tours-documents"
                                            aria-selected="{{ $activeTab === $photosIdx ? 'true' : 'false' }}">
                                            Photos &amp; Tours
                                        </button>
                                    </li>

                                    {{-- AI Knowledge Base --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $aiIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $aiIdx }})"
                                            id="ai-questions-tab" data-bs-toggle="tab"
                                            data-bs-target="#ai-questions"
                                            type="button" role="tab"
                                            aria-controls="ai-questions"
                                            aria-selected="{{ $activeTab === $aiIdx ? 'true' : 'false' }}">
                                            AI Knowledge Base
                                        </button>
                                    </li>

                                    {{-- Seller Information --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $sellerInfoIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $sellerInfoIdx }})"
                                            id="seller-information-tab" data-bs-toggle="tab"
                                            data-bs-target="#seller-information"
                                            type="button" role="tab"
                                            aria-controls="seller-information"
                                            aria-selected="{{ $activeTab === $sellerInfoIdx ? 'true' : 'false' }}">
                                            Agent Credentials & Contact Info
                                        </button>
                                    </li>
                                @else
                                    {{-- Non-seller: Sale Terms=2, Services=3, Description=4, Broker Comp=5, Photos=6, Info=7, AI=8 --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === 2 ? 'active' : '' }}"
                                            wire:click="setActiveTab(2)"
                                            id="sale-terms-tab" data-bs-toggle="tab"
                                            data-bs-target="#sale-terms"
                                            type="button" role="tab"
                                            aria-controls="sale-terms"
                                            aria-selected="{{ $activeTab === 2 ? 'true' : 'false' }}">
                                            @if ($user_type === 'tenant')
                                                Leasing Terms
                                            @elseif ($user_type === 'buyer')
                                                Purchasing Terms
                                            @elseif ($user_type === 'landlord')
                                                Lease Terms
                                            @else
                                                Sale Terms
                                            @endif
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $additionalDetailsIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $additionalDetailsIdx }})"
                                            id="additional-details-tab" data-bs-toggle="tab"
                                            data-bs-target="#additional-details"
                                            type="button" role="tab"
                                            aria-controls="additional-details"
                                            aria-selected="{{ $activeTab === $additionalDetailsIdx ? 'true' : 'false' }}">
                                            Description
                                        </button>
                                    </li>
                                    {{-- B7: Broker Compensation tab hidden from client listing form --}}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $photosIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $photosIdx }})"
                                            id="photos-tours-documents-tab" data-bs-toggle="tab"
                                            data-bs-target="#photos-tours-documents"
                                            type="button" role="tab"
                                            aria-controls="photos-tours-documents"
                                            aria-selected="{{ $activeTab === $photosIdx ? 'true' : 'false' }}">
                                            Photos &amp; Tours
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $sellerInfoIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $sellerInfoIdx }})"
                                            id="seller-information-tab" data-bs-toggle="tab"
                                            data-bs-target="#seller-information"
                                            type="button" role="tab"
                                            aria-controls="seller-information"
                                            aria-selected="{{ $activeTab === $sellerInfoIdx ? 'true' : 'false' }}">
                                            Agent Credentials & Contact Info
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $activeTab === $aiIdx ? 'active' : '' }}"
                                            wire:click="setActiveTab({{ $aiIdx }})"
                                            id="ai-questions-tab" data-bs-toggle="tab"
                                            data-bs-target="#ai-questions"
                                            type="button" role="tab"
                                            aria-controls="ai-questions"
                                            aria-selected="{{ $activeTab === $aiIdx ? 'true' : 'false' }}">
                                            AI Knowledge Base
                                        </button>
                                    </li>
                                @endif
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

                                @if ($user_type === 'seller')
                                <!-- Financial Details Tab (seller full_service: index 2, only for Income/Commercial/Business) -->
                                @if ($hasFinancialTab)
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}" id="financial-details"
                                    role="tabpanel" aria-labelledby="financial-details-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.financial-details')
                                </div>
                                @endif
                                @endif

                                <!-- Sale Terms Tab -->
                                <div class="tab-pane fade {{ $activeTab === $saleTermsIdx ? 'show active' : '' }}" id="sale-terms"
                                    role="tabpanel" aria-labelledby="sale-terms-tab">
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


                                <!-- Additional Details Tab -->
                                <div class="tab-pane fade {{ $activeTab === $additionalDetailsIdx ? 'show active' : '' }}"
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

                                {{-- B7: Broker Compensation panel hidden from client listing form --}}

                                @if ($user_type === 'seller')
                                <!-- Tax, Legal, HOA & Disclosures Tab (seller full_service) -->
                                <div class="tab-pane fade {{ $activeTab === $taxLegalIdx ? 'show active' : '' }}" id="tax-legal-hoa-disclosures"
                                    role="tabpanel" aria-labelledby="tax-legal-hoa-disclosures-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.tax-legal-hoa-disclosures')
                                </div>

                                <!-- Documents & Disclosures Tab (seller full_service) -->
                                <div class="tab-pane fade {{ $activeTab === $docsIdx ? 'show active' : '' }}" id="documents-disclosures"
                                    role="tabpanel" aria-labelledby="documents-disclosures-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.documents-disclosures')
                                </div>

                                <!-- Photos, Tours & Documents Tab (seller full_service) -->
                                <div class="tab-pane fade {{ $activeTab === $photosIdx ? 'show active' : '' }}" id="photos-tours-documents"
                                    role="tabpanel" aria-labelledby="photos-tours-documents-tab">
                                    @include('livewire.offer-listing.offer-seller-tabs.commission-based.photos-tours-documents')
                                </div>

                                <!-- AI Knowledge Base Tab (seller full_service) -->
                                <div class="tab-pane fade {{ $activeTab === $aiIdx ? 'show active' : '' }}" id="ai-questions"
                                    role="tabpanel" aria-labelledby="ai-questions-tab">
                                    @include('livewire.offer-listing.shared.ai-questions-input')
                                </div>

                                <!-- Seller Info Tab (seller full_service) -->
                                <div class="tab-pane fade {{ $activeTab === $sellerInfoIdx ? 'show active' : '' }}" id="seller-information"
                                    role="tabpanel" aria-labelledby="seller-information-tab">
                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                        @include('livewire.partials.agent-credentials')
                                    @else
                                        @include('livewire.offer-listing.offer-seller-tabs.commission-based.seller-info')
                                    @endif
                                </div>
                                @else
                                <!-- Photos, Tours & Documents Tab (non-seller full_service: index 6) -->
                                <div class="tab-pane fade {{ $activeTab === 6 ? 'show active' : '' }}" id="photos-tours-documents"
                                    role="tabpanel" aria-labelledby="photos-tours-documents-tab">
                                    @if($user_type === 'landlord')
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.photos-tours-documents')
                                    @endif
                                </div>

                                <!-- Info Tab (non-seller: index $sellerInfoIdx) -->
                                <div class="tab-pane fade {{ $activeTab === $sellerInfoIdx ? 'show active' : '' }}" id="seller-information"
                                    role="tabpanel" aria-labelledby="seller-information-tab">
                                    @if($isAgentUser ?? (auth()->user() && auth()->user()->user_type === 'agent'))
                                        @include('livewire.partials.agent-credentials')
                                    @elseif ($user_type === 'tenant')
                                        @include('livewire.offer-listing.offer-tenant-tabs.commission-based.tenant-info')
                                    @elseif($user_type === 'buyer')
                                        @include('livewire.offer-listing.offer-buyer-tabs.commission-based.buyer-info')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.landlord-info')
                                    @endif
                                </div>

                                <!-- AI Knowledge Base Tab (non-seller: index $aiIdx) -->
                                <div class="tab-pane fade {{ $activeTab === $aiIdx ? 'show active' : '' }}" id="ai-questions"
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

                        <button type="button" class="btn btn-outline-primary me-2" onclick="syncAllSelect2BeforeSave(); @this.call('saveDraft');" wire:loading.attr="disabled" wire:target="saveDraft">
                            <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                            <span wire:loading wire:target="saveDraft">Saving...</span>
                        </button>

                        <button type="button" class="btn btn-primary wizard-step-next"
                            onclick="if(typeof window._wizardNextHandler==='function'){window._wizardNextHandler();}">Next</button>

                        <button type="submit" class="btn btn-success wizard-step-finish" id="save-button" wire:loading.attr="disabled" wire:target="store">
                            <span wire:loading.remove wire:target="store">Save &amp; Submit Offer</span>
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
        let _bathroomsDropdownHandler = null;

        // Shared helper: reads native select.selected state and updates all
        // "Other" free-text wrapper visibility. Called from initializeFullService(),
        // message.processed hooks, and the delegated change handler below.
        function _restoreOtherSelectVisibility() {
            var _els = [
                { selId: 'appliances',                              wrapperId: 'other_appliances',                               wrapperClass: null },
                { selId: 'view_preference',                         wrapperId: 'other_preferences',                              wrapperClass: null },
                { selId: 'garage_parking_spaces_option_landlord',   wrapperId: 'other_garage_parking_spaces_option_landlord',    wrapperClass: null },
                { selId: 'included_assets',                         wrapperId: null,                                             wrapperClass: 'other_assets' },
            ];
            _els.forEach(function(cfg) {
                var sel = document.getElementById(cfg.selId);
                if (!sel) return;
                var hasOther = Array.from(sel.options).some(function(o) { return o.selected && o.value === 'Other'; });
                if (cfg.wrapperId) {
                    var w = document.getElementById(cfg.wrapperId);
                    if (w) w.style.display = hasOther ? '' : 'none';
                }
                if (cfg.wrapperClass) {
                    document.querySelectorAll('.' + cfg.wrapperClass).forEach(function(w) {
                        w.style.display = hasOther ? '' : 'none';
                    });
                }
            });
        }

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

        // Re-initialize tooltips after Livewire updates
        document.addEventListener('livewire:load', function() {
            initializeTooltips();
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize tooltips on page load
            initializeTooltips();

            currentServiceType = 'full_service';
            try { initializeFullService(); } catch(e) { console.warn('[Seller] initializeFullService DOMContentLoaded error:', e); }

            // Multi-pass icon injection AFTER initializeFullService()/Select2 have run.
            // Inline calls ensure icons fire even if runSellerInitialIconPasses is not yet
            // in scope at this point in the script execution order.
            addIconsToInputs();
            requestAnimationFrame(function() { addIconsToInputs(); });
            setTimeout(function() { addIconsToInputs(); }, 100);
            setTimeout(function() { addIconsToInputs(); }, 300);
            setTimeout(function() { addIconsToInputs(); }, 700);
            setTimeout(function() { addIconsToInputs(); }, 1500);
            setTimeout(function() { addIconsToInputs(); }, 2500);

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
                addIconsToInputs(); // Re-add icons after draft data loads
                rehydrateSelect2MultiFields();
                if (typeof window.updateSaveButton === 'function') {
                    window.updateSaveButton();
                }
            }, 100);
            // Additional delayed call to ensure FontAwesome has fully loaded
            setTimeout(function() {
                addIconsToInputs();
            }, 500);
        });

        function rehydrateSelect2MultiFields() {
            var regularFields = {
                '#exchange_item': 'exchange_item',
                '#non_negotiable_amenities': 'non_negotiable_amenities',
                '#sale_provision': 'sale_provision',
                '#offered_financing': 'offered_financing',
                '#view_preference': 'view_preference',
                '#appliances': 'appliances',
                '#included_assets': 'business_assets',
                '#association_fee_includes': 'association_fee_includes',
                '#association_amenities': 'association_amenities',
            };
            Object.keys(regularFields).forEach(function(selector) {
                var $el = $(selector);
                if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                    var prop = regularFields[selector];
                    var saved = @this.get(prop) || [];
                    if (saved.length > 0) {
                        $el.val(saved).trigger('change.select2');
                        console.log('[DraftLoaded] Rehydrated ' + prop + ':', saved);
                    }
                }
            });
            Object.entries(_mlsFieldSelectors).forEach(function([fieldId, selectors]) {
                var saved = @this.get(fieldId) || [];
                if (saved.length === 0) return;
                selectors.forEach(function(selector) {
                    var $el = $(selector);
                    if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                        $el.val(saved).trigger('change.select2');
                        console.log('[DraftLoaded] Rehydrated ' + fieldId + ':', saved);
                    }
                });
            });
        }

        // Re-hydrate all Select2 multi-selects after MLS "Apply Selected" completes.
        // Select2 elements use wire:ignore, so Livewire's DOM diff does not update them.
        // dispatchBrowserEvent('mlsApplied') fires from HasMlsImport::applyImportedFields().
        //
        // Two-pass approach:
        //   Pass 1 (200 ms): initializeMlsPropertyMultiSelects() first ensures any
        //     Select2 fields that were newly added to the DOM during apply (e.g. when
        //     property_type was empty and changed during apply) are initialized before
        //     rehydrating their values.  Fields already initialized are skipped via the
        //     !hasClass('select2-hidden-accessible') guard inside that function.
        //   Pass 2 (600 ms): second attempt in case the first races with a slow
        //     Livewire morphdom reconciliation on low-powered devices.
        window.addEventListener('mlsApplied', function() {
            console.log('[MlsApplied] Rehydrating Select2 fields from Livewire properties');
            setTimeout(function() {
                initializeMlsPropertyMultiSelects();
                rehydrateSelect2MultiFields();
            }, 200);
            setTimeout(function() {
                initializeMlsPropertyMultiSelects();
                rehydrateSelect2MultiFields();
            }, 600);
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

            // Initialize new service logic — delegated listener on document handles
            // wizard nav clicks via window._wizardNextHandler; no need to clone/replace buttons
            if (serviceType === 'full_service') {
                initializeFullService();
            }

            if (window.Livewire) { Livewire.emit('serviceTypeChanged', serviceType); }
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
            initializeWizardHandlers();
            if (!document._sellerCreateTabNavListenerAdded) {
                document._sellerCreateTabNavListenerAdded = true;
                document.addEventListener('shown.bs.tab', function(e) {
                    var _tgt = e.target.getAttribute('data-bs-target');
                    if (_tgt && e.target.closest('#myTab')) sessionStorage.setItem('seller_create_active_tab', _tgt);
                });
            }
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

            if ($('#association_fee_includes').length && !$('#association_fee_includes').hasClass('select2-hidden-accessible')) {
                $('#association_fee_includes').select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
            }
            if ($('#association_fee_includes').length) {
                var fiDataInit = $('#association_fee_includes').val() || [];
                $('#hoa-fee-includes-other-section').toggle(fiDataInit.includes('Other'));
            }

            if ($('#association_amenities').length && !$('#association_amenities').hasClass('select2-hidden-accessible')) {
                $('#association_amenities').select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
            }
            if ($('#association_amenities').length) {
                var amDataInit = $('#association_amenities').val() || [];
                $('#hoa-amenities-other-section').toggle(amDataInit.includes('Other'));
            }

            if ($('#association_fee_frequency').length) {
                $('#association-fee-frequency-other-section').toggle($('#association_fee_frequency').val() === 'Other');
            }

            if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                $('#non_negotiable_amenities').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
                $('#non_negotiable_amenities').on('change', function(e) {
                    let selectedValues = $(this).val() || [];
                    @this.set('non_negotiable_amenities', selectedValues, false);
                });
            }

            if ($('#exchange_item').length) {
                var $exEl = $('#exchange_item');
                if (!$exEl.hasClass('select2-hidden-accessible')) {
                    $exEl.select2({
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                    });
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
                        $('#other_exchange_item_wrapper').toggle(selectedValues.includes('Other'));
                    });
                    $exEl.data('exchange-change-bound', true);
                }
                $('#other_exchange_item_wrapper').toggle((savedExchangeItems || []).includes('Other'));
            }

            initializeMlsPropertyMultiSelects();

            var $appliances = $('#appliances');
            if ($appliances.length && !$appliances.hasClass('select2-hidden-accessible')) {
                $appliances.select2({ placeholder: "Select", allowClear: true, width: '100%', closeOnSelect: false });
            }

            _restoreOtherSelectVisibility();

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
                if (garageSelect && optionsWrapper) {
                    if (garageSelect.value === "Yes") {
                        optionsWrapper.classList.remove('d-none'); // Show options dropdown
                    } else {
                        optionsWrapper.classList.add('d-none'); // Hide options dropdown
                        if (otherInputWrapper) otherInputWrapper.classList.add('d-none'); // Also hide other input
                    }
                }

                // Then check if "Other" is selected in the options dropdown
                if (otherInputWrapper) {
                    if (garageOptions && garageOptions.value === "Other" && garageSelect && garageSelect.value === "Yes") {
                        otherInputWrapper.classList.remove('d-none'); // Show input field
                    } else {
                        otherInputWrapper.classList.add('d-none'); // Hide input field
                    }
                }
            }

            // Initialize on page load
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


            if ($('#included_assets').length && !$('#included_assets').hasClass('select2-hidden-accessible')) {
                $('#included_assets').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
            }

            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                $('#view_preference').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
            }

            if ($('#garage_parking_spaces_option_landlord').length && !$('#garage_parking_spaces_option_landlord').hasClass('select2-hidden-accessible')) {
                $('#garage_parking_spaces_option_landlord').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                });
            }

            // Restore all "Other" wrapper visibility from native select state
            _restoreOtherSelectVisibility();

            // Function to toggle Non-Negotiable Amenities and Property Features:" input field

            function toggleOtherAmenities(selectElement) {
                const otherAmenitiesDiv = document.querySelector('.other_non_negotiable_amenities');

                if (!otherAmenitiesDiv) {
                    return;
                }
                var vals = $(selectElement).val() || [];
                otherAmenitiesDiv.style.display = vals.includes('Other') ? '' : 'none';
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
                const conditionDropdownItem = document.getElementById('property_style_select');
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

            // RC-3: Register all inner message.processed hooks exactly once, regardless of how
            // many times initializeFullService() is called across Livewire round-trips.
            if (!window.__innerHooksBound) {
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                window.__innerHooksBound = true;
                Livewire.hook('message.processed', () => {
                    attachAuctionDropdownListener();
                    if ($('#exchange_item').length) {
                        var $exEl = $('#exchange_item');
                        if (!$exEl.hasClass('select2-hidden-accessible')) {
                            $exEl.select2({
                                placeholder: "Select",
                                allowClear: true,
                                width: '100%',
                                closeOnSelect: false,
                            });
                        }
                        var saved = [];
                        try { saved = JSON.parse($exEl.attr('data-selected') || '[]'); } catch(e) {}
                        if (!saved.length) { saved = @this.get('exchange_item') || []; }
                        var current = $exEl.val() || [];
                        if (saved.length > 0 && current.length === 0) {
                            $exEl.val(saved).trigger('change.select2');
                        }
                        if (!$exEl.data('exchange-change-bound')) {
                            $exEl.on('change', function(e) {
                                var selectedValues = $(this).val() || [];
                                @this.set('exchange_item', selectedValues, false);
                                $('#other_exchange_item_wrapper').toggle(selectedValues.includes('Other'));
                            });
                            $exEl.data('exchange-change-bound', true);
                        }
                        $('#other_exchange_item_wrapper').toggle(($exEl.val() || []).includes('Other'));
                    }
                    toggleGarageOptions();
                    toggleSpaceInput('carport-needed', 'other-carport-needed');
                    toggleSpaceInput('garage-needed', 'other-garage-needed');
                    attachAmenitiesDropdownListener();
                    attachBedroomsDropdownListener();
                    attachConditionDropdownListener();
                    attachItemConditionDropdownListener();

                    // Restore "Other" wrapper visibility after every Livewire re-render
                    _restoreOtherSelectVisibility();
                });
                } // end window.Livewire guard
            }

        }

        function initializeLimitedService() {
            initializeWizardHandlers();
        }

        function initializeWizardHandlers() {

            // Function to check if all required fields are filled.
            function checkFormValidity() {
                let allValid = true;

                // Check all tabs for required fields (skip hidden/disabled fields)
                document.querySelectorAll('.tab-pane').forEach(tabPane => {
                    tabPane.querySelectorAll('[required]').forEach(field => {
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

                // Validate required Select2 multi-selects
                var select2RequiredSelectors = ['#sale_provision'];
                select2RequiredSelectors.forEach(function(selector) {
                    var $el = $(selector);
                    if ($el.length) {
                        var val = $el.val();
                        if (!val || val.length === 0) {
                            allValid = false;
                        }
                    }
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

            window._wizardNextHandler = function() {
                const currentTab = document.querySelector('#myTab .nav-link.active');
                if (!currentTab) return;

                const currentTabContent = document.querySelector(currentTab.getAttribute('data-bs-target'));
                if (!currentTabContent) return;

                // Hide any previous validation error banner
                const _valBanner = document.getElementById('submit-error-banner');
                if (_valBanner) _valBanner.classList.add('d-none');

                let isValid = true;

                // Validate all required fields in the current tab
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

                // Validate cities array
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
                            citiesContainer.parentNode.insertBefore(citiesError, citiesContainer.nextSibling);
                        }
                    } else {
                        const existingError = citiesContainer.parentNode.querySelector('.error');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                }

                // Validate counties array
                const countiesContainer = currentTabContent.querySelector('.counties-container');
                if (countiesContainer && isElementVisible(countiesContainer)) {
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
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                }

                // Validate required Select2 multi-selects present in current tab
                var select2RequiredMap = {
                    '#sale_provision': '#sale_provision_error',
                };
                Object.keys(select2RequiredMap).forEach(function(selector) {
                    var $el = $(selector, currentTabContent);
                    if (!$el.length) {
                        $el = $(selector);
                        if (!$el.length || !currentTabContent.querySelector(selector)) return;
                    }
                    var errorSpan = document.querySelector(select2RequiredMap[selector]);
                    var val = $el.val();
                    if (!val || val.length === 0) {
                        isValid = false;
                        if (errorSpan) errorSpan.textContent = 'This field is required.';
                    } else {
                        if (errorSpan) errorSpan.textContent = '';
                    }
                });

                // If all fields are valid, proceed to the next tab
                if (isValid) {
                    const _allTabs = Array.from(document.querySelectorAll('#myTab .nav-link'));
                    const _curIdx = _allTabs.indexOf(currentTab);
                    if (_curIdx < _allTabs.length - 1) {
                        const _nextTab = _allTabs[_curIdx + 1];
                        var _nId = _nextTab.getAttribute('data-bs-target');
                        if (_nId) {
                            window._manualTabSwitch(_nId);
                            sessionStorage.setItem('seller_create_active_tab', _nId);
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

                // Update save button state after validation
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    saveButton.disabled = !isValid;
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
                sessionStorage.setItem('seller_create_active_tab', _pId);
            };

            // Add event listeners to update save button state when fields change
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

        // Delegated wizard nav — bound once, survives Livewire DOM morphing.
        // Next button has an inline onclick fallback; delegated listener only fires
        // when onclick is absent (e.g. after a morph that strips attributes) to
        // prevent double-calling _wizardNextHandler on the same click.
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

        // Submit intercept — validate all required Select2 fields across all tabs before allowing form submit
        if (!window.__submitInterceptBound) {
            window.__submitInterceptBound = true;
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.wizard-step-finish');
                if (!btn) return;

                var select2RequiredMap = {
                    '#sale_provision': '#sale_provision_error',
                };

                // Build ordered list of tab pane IDs from the nav so we can pick the earliest invalid tab
                var tabPaneOrder = Array.from(document.querySelectorAll('#myTab .nav-link')).map(function(link) {
                    var target = link.getAttribute('data-bs-target');
                    return target ? target : null;
                }).filter(Boolean);

                var invalidTabIds = [];

                Object.keys(select2RequiredMap).forEach(function(selector) {
                    var $el = $(selector);
                    if (!$el.length) return;
                    var val = $el.val();
                    var errorSpan = document.querySelector(select2RequiredMap[selector]);
                    if (!val || val.length === 0) {
                        if (errorSpan) errorSpan.textContent = 'This field is required.';
                        var pane = $el[0].closest('.tab-pane');
                        if (pane) invalidTabIds.push('#' + pane.id);
                    } else {
                        if (errorSpan) errorSpan.textContent = '';
                    }
                });

                if (invalidTabIds.length > 0) {
                    // Navigate to whichever invalid tab appears earliest in the wizard nav order
                    var firstInvalidTabId = invalidTabIds.reduce(function(earliest, current) {
                        var earliestIdx = tabPaneOrder.indexOf(earliest);
                        var currentIdx = tabPaneOrder.indexOf(current);
                        if (earliestIdx === -1) return current;
                        if (currentIdx === -1) return earliest;
                        return currentIdx < earliestIdx ? current : earliest;
                    });

                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof window._manualTabSwitch === 'function') {
                        window._manualTabSwitch(firstInvalidTabId);
                        sessionStorage.setItem('seller_create_active_tab', firstInvalidTabId);
                    }
                }
            }, true);
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

        // Seller-only: multi-pass icon injection called AFTER initializeFullService()
        // so Select2 has already wrapped selects before icons are injected.
        // Long tail (1500ms, 2500ms) covers slow Livewire hydration on no-draft fresh loads.
        function runSellerInitialIconPasses() {
            addIconsToInputs();
            requestAnimationFrame(addIconsToInputs);
            setTimeout(addIconsToInputs, 100);
            setTimeout(addIconsToInputs, 300);
            setTimeout(addIconsToInputs, 700);
            setTimeout(addIconsToInputs, 1500);
            setTimeout(addIconsToInputs, 2500);
        }


        if (window.Livewire && typeof window.Livewire.hook === 'function') {
        Livewire.hook('message.processed', () => {
            addIconsToInputs(); // synchronous — runs immediately after morphdom, like Buyer
            setTimeout(function() { addIconsToInputs(); }, 0); // deferred safety net

            // Re-evaluate garage/parking "Other" companion visibility after every Livewire
            // re-render. Select2 preserves its selected state across morphdom but the companion
            // div visibility must be explicitly re-synced here (Select2 won't fire "change").
            (function() {
                var _garageVals = $('#garage_parking_spaces_option_landlord').val() || [];
                $('#other_garage_parking_spaces_option_landlord').toggle(_garageVals.includes('Other'));
            })();

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
                    @this.set('property_items', $(this).val() || []);
                });
            })();

            var _now = Date.now();
            if (_now - _lastInitTime > 300) {
                _lastInitTime = _now;
                initializeFullService();
                // Re-run icon passes AFTER initializeFullService() so Select2 wrapping
                // has already happened before icons are injected into .input-cover wrappers
                runSellerInitialIconPasses();
            } else {
                // initializeFullService throttled — still re-run icon passes so any
                // Livewire morphdom patch that wiped icons is recovered
                runSellerInitialIconPasses();
            }

            var _savedTabId = sessionStorage.getItem('seller_create_active_tab');
            if (_savedTabId && typeof window._manualTabSwitch === 'function') {
                var _tabTrigger = document.querySelector('#myTab .nav-link[data-bs-target="' + _savedTabId + '"]');
                if (_tabTrigger && !_tabTrigger.classList.contains('active')) {
                    window._manualTabSwitch(_savedTabId);
                }
            }
        });
        } // end window.Livewire guard

        document.addEventListener('shown.bs.tab', function() {
            setTimeout(function() { addIconsToInputs(); }, 0);
        });

        // Single delegated jQuery handler for all five "Other" free-text wrapper fields.
        // $(document).on() receives Select2's synthetic jQuery change events, which
        // native document.addEventListener('change') misses. Guarded so it registers
        // only once across Livewire re-renders.
        if (!document._otherVisibilityDelegateAdded) {
            document._otherVisibilityDelegateAdded = true;
            $(document).on('change', '#appliances', function() {
                var vals = $(this).val() || [];
                $('#other_appliances').toggle(vals.includes('Other'));
                @this.set('appliances', vals, false);
            });
            $(document).on('change', '#view_preference', function() {
                var vals = $(this).val() || [];
                $('#other_preferences').toggle(vals.includes('Other'));
                @this.set('view_preference', vals, false);
            });
            $(document).on('change', '#included_assets', function() {
                var vals = $(this).val() || [];
                $('.other_assets').toggle(vals.includes('Other'));
                @this.set('business_assets', vals, false);
            });
            $(document).on('change', '#garage_parking_spaces_option_landlord', function() {
                var vals = $(this).val() || [];
                $('#other_garage_parking_spaces_option_landlord').toggle(vals.includes('Other'));
                @this.set('garage_parking_spaces_option', vals, false);
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
                    '#broker-compensation-agency-agreement-terms',
                    '#tax-legal-hoa-disclosures',
                    '#documents-disclosures',
                    '#photos-tours-documents',
                    '#ai-questions',
                    '#seller-information',
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
                if (field.type === 'checkbox') return field.checked;
                if (field.type === 'radio') return !!document.querySelector(`input[name="${field.name}"]:checked`);
                if (field.type === 'file') {
                    return field.files && field.files.length > 0;
                }
                if (field.type === 'select-one' || field.type === 'select-multiple') {
                    return field.value !== '' && field.value !== null;
                }
                return field.value?.toString().trim() !== '';
            }

            function validateAllTabsStrictly() {
                const requiredFields = getAllRequiredFields();
                let invalidFields = [];

                for (const field of requiredFields) {
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

            // Livewire reactivity hook
            if (typeof Livewire !== 'undefined' && !window.__wizardHandlerHookBound) {
                window.__wizardHandlerHookBound = true;
                Livewire.hook('message.processed', () => {
                    setTimeout(() => {
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

                    for (const field of requiredFields) {
                        if (field.disabled || field.type === 'hidden') continue;
                        if (!isFieldValid(field)) {
                            const tab = field.closest('.tab-pane');
                            const labelEl = field.closest('.form-group') && field.closest('.form-group').querySelector('label');
                            const fieldName = labelEl ? labelEl.textContent.replace(/[*:]/g, '').trim() : (field.getAttribute('placeholder') || field.name || field.id || 'Required field');
                            invalidItems.push({ field: field, tab: tab, fieldName: fieldName });
                        }
                    }

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
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeMoneyInputs();
        });

        // Re-initialize after Livewire updates (Livewire v2)
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:load', function() {
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    Livewire.hook('message.processed', function() {
                        initializeMoneyInputs();
                    });
                }
            });
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
            }
            document.addEventListener('shown.bs.tab', syncWizardButtons);
            document.addEventListener('DOMContentLoaded', syncWizardButtons);
        })();
    </script>
    <script>
        var _sellerAutofilling = false;

        function sellerAutoFillBuyNow() {
            var desired = document.getElementById('seller_desired_sale_price');
            var buyNow  = document.getElementById('seller_buy_now_price');
            if (!desired || !buyNow) return;
            // Fill if empty OR if previously auto-filled (user hasn't manually edited it yet)
            if (!buyNow.value.trim() || buyNow.dataset.autofilled === '1') {
                _sellerAutofilling = true;
                buyNow.value = desired.value;
                buyNow.dataset.autofilled = '1';
                buyNow.dispatchEvent(new Event('input', { bubbles: true }));
                _sellerAutofilling = false;
            }
        }

        document.addEventListener('input', function (e) {
            // User manually typed into Buy Now — clear the autofilled marker
            if (e.target && e.target.id === 'seller_buy_now_price' && !_sellerAutofilling) {
                e.target.dataset.autofilled = '';
            }
            // Desired Sale Price changed — trigger auto-fill
            if (e.target && e.target.id === 'seller_desired_sale_price') {
                sellerAutoFillBuyNow();
            }
        });

        document.addEventListener('livewire:load', function () {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                Livewire.hook('message.processed', function () {
                    sellerAutoFillBuyNow();
                });
            }
        });
    </script>
    <script>
    var _sellerPlacesNode = null;
    window.byoInitSellerOfferPlaces = function() {
        var input = document.getElementById('seller-offer-street-address');
        // Early-return when Maps API is not ready yet. _sellerPlacesNode is intentionally
        // NOT assigned here — the assignment only happens after a successful Autocomplete
        // construction below. This means the guard on the next line is safe: if we returned
        // early on a previous call (API not ready), _sellerPlacesNode is still null, so the
        // next call will try again. Do not move the assignment above this guard.
        if (!input || !window.google || !window.google.maps || !window.google.maps.places) { return; }
        if (input === _sellerPlacesNode) { return; }
        _sellerPlacesNode = input;

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
        window.byoInitSellerOfferPlaces && window.byoInitSellerOfferPlaces();
    });
    document.addEventListener('livewire:load', function () {
        window.byoInitSellerOfferPlaces && window.byoInitSellerOfferPlaces();
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            Livewire.hook('message.processed', function () {
                window.byoInitSellerOfferPlaces && window.byoInitSellerOfferPlaces();
            });
        }
    });
    </script>
    <x-google-maps-script callback="byoInitSellerOfferPlaces" />
@endpush
