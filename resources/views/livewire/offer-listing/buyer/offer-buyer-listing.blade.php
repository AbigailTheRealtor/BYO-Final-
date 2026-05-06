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
            z-index: 10;
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

        .input-cover.has-select-icon .select2-container .select2-selection {
            padding-left: 44px !important;
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

        .user-type-icon {
            color: #0ce7ef;
        }

        /* Dollar sign prefix styling for currency inputs */
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

    $bathroomsRes = [
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

    $financing_options = [
        ['name' => 'Assumable', 'description' => 'Allows an existing mortgage to be assumed by a Buyer, subject to lender approval. This may be beneficial if the existing interest rate is lower than current market rates.'],
        ['name' => 'Cash', 'description' => 'Purchase is completed without financing, with the full price paid in cash and no financing contingency.'],
        ['name' => 'Conventional', 'description' => 'Uses a traditional mortgage that meets standard underwriting guidelines.'],
        ['name' => 'FHA', 'description' => 'Uses a loan backed by the Federal Housing Administration, typically requiring the property to meet condition standards.'],
        ['name' => 'Jumbo', 'description' => 'Uses a loan that exceeds conforming loan limits and often requires stricter borrower qualifications.'],
        ['name' => 'VA', 'description' => 'Uses a VA-backed loan available to eligible veterans and active-duty service members.'],
        ['name' => 'No-Doc', 'description' => 'Uses a loan requiring limited or no income documentation.'],
        ['name' => 'Non-QM', 'description' => 'Uses a Non-Qualified Mortgage that allows alternative income verification methods.'],
        ['name' => 'USDA', 'description' => 'Uses a USDA-backed loan for eligible rural properties and qualifying buyers.'],
        ['name' => 'Cryptocurrency', 'description' => 'Uses digital currency (e.g., Bitcoin or Ethereum) as full or partial consideration, subject to Seller acceptance.'],
        ['name' => 'Exchange/Trade', 'description' => 'Includes another asset as part of the purchase consideration in a trade.'],
        ['name' => 'Lease Option', 'description' => 'Allows the property to be leased with an option to purchase later under pre-agreed terms.'],
        ['name' => 'Lease Purchase', 'description' => 'Allows the property to be leased now with a commitment to purchase later, often with a portion of rent credited toward the purchase price.'],
        ['name' => 'Non-Fungible Token (NFT)', 'description' => 'Uses a verified digital asset as full or partial consideration, subject to Seller approval.'],
        ['name' => 'Seller Financing', 'description' => 'Purchase price is financed in whole or in part directly by the Seller.'],
        ['name' => 'Other', 'description' => 'Uses an alternative financing or consideration method not listed above.'],
    ];

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

    $property_items_options = [
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
        ['name' => 'Assignment Contract', 'description' => 'The Buyer is open to purchasing an assignment contract from a wholesaler.'],
        ['name' => 'Auction', 'description' => 'The Buyer is open to purchasing a property at auction.'],
        ['name' => 'Bank Owned/REO', 'description' => 'The Buyer is open to properties that have been foreclosed on and are now owned by the bank (Real Estate Owned).'],
        ['name' => 'Government Owned', 'description' => 'The Buyer is open to purchasing government-owned properties.'],
        ['name' => 'None', 'description' => 'The Buyer is not interested in special sale provisions.'],
        ['name' => 'Probate Listing', 'description' => 'The Buyer is open to purchasing properties being sold through probate court.'],
        ['name' => 'Short Sale', 'description' => 'The Buyer is open to purchasing a property where the sale price is less than the outstanding mortgage balance.'],
        ['name' => 'Other', 'description' => 'The Buyer is open to other special sale provisions not listed here.'],
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
                                                    href="{{ route('offer.listing.buyer.edit', ['auctionId' => $draft->id]) }}">
                                                    {{ $draft->title }}@if($draftVersion) <span class="badge bg-secondary">v{{ $draftVersion }}</span>@endif ({{ $draft->updated_at->format('m/d/Y H:i') }})
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" style="border-color: #dc3545; color: #dc3545;"
                                                    data-bs-dismiss="modal"
                                                    wire:click="deleteDraft('{{ $draft->id }}')" wire:ignore.self
                                                    onclick="setTimeout(() => { window.location = '{{ route('offer.listing.buyer')}}' }, 100)">
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
                                        onclick="setTimeout(() => { window.location = '{{ route('offer.listing.buyer')}}' }, 100)">
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
                                @php
                                    $fullServiceTabs = $user_type === 'buyer'
                                        ? ['Listing Details', 'Property Preferences', 'Purchasing Terms', 'Description']
                                        : ['Listing Details', 'Property Preferences', 'Purchasing Terms', 'Services', 'Description'];
                                    $agentCredentialsIndex = $user_type === 'buyer' ? 4 : 5;
                                    $aiQuestionsIndex = $user_type === 'buyer' ? 5 : 6;
                                @endphp
                                @foreach ($fullServiceTabs as $index => $tab)
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
                                    <button class="nav-link {{ $activeTab === $agentCredentialsIndex ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $agentCredentialsIndex }})"
                                        id="buyer-information-tab" data-bs-toggle="tab"
                                        data-bs-target="#buyer-information"
                                        type="button" role="tab"
                                        aria-controls="buyer-information"
                                        aria-selected="{{ $activeTab === $agentCredentialsIndex ? 'true' : 'false' }}">
                                        Agent Credentials & Contact Info
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === $aiQuestionsIndex ? 'active' : '' }}"
                                        wire:click="setActiveTab({{ $aiQuestionsIndex }})"
                                        id="ai-questions-tab" data-bs-toggle="tab"
                                        data-bs-target="#ai-questions"
                                        type="button" role="tab"
                                        aria-controls="ai-questions"
                                        aria-selected="{{ $activeTab === $aiQuestionsIndex ? 'true' : 'false' }}">
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

                                <!-- Dynamic Information Tab Based on User Type -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $activeTab === 4 ? 'active' : '' }}"
                                        wire:click="setActiveTab(4)" id="information-tab" data-bs-toggle="tab"
                                        data-bs-target="#information" type="button" role="tab"
                                        aria-controls="information"
                                        aria-selected="{{ $activeTab === 4 ? 'true' : 'false' }}">
                                        Agent Credentials & Contact Info
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
                                    id="purchasing-terms" role="tabpanel" aria-labelledby="purchasing-terms-tab">

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

                                @if($user_type !== 'buyer')
                                <!-- Services Tab (not shown for buyer) -->
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="services"
                                    role="tabpanel" aria-labelledby="services-tab">

                                    @if ($user_type === 'tenant')
                                        @include('livewire.offer-listing.offer-tenant-tabs.commission-based.services')
                                    @elseif($user_type === 'seller')
                                        @include('livewire.offer-listing.offer-seller-tabs.commission-based.services')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.offer-listing.offer-landlord-tabs.commission-based.services')
                                    @endif
                                </div>
                                @endif
                                <!-- Additional Details Tab -->
                                @php $additionalDetailsIndex = $user_type === 'buyer' ? 3 : 4; @endphp
                                <div class="tab-pane fade {{ $activeTab === $additionalDetailsIndex ? 'show active' : '' }}"
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

                                <!-- Buyer Info Tab -->
                                @php $buyerInfoIndex = $user_type === 'buyer' ? 4 : 5; @endphp
                                <div class="tab-pane fade {{ $activeTab === $buyerInfoIndex ? 'show active' : '' }}"
                                    id="buyer-information" role="tabpanel" aria-labelledby="buyer-information-tab">
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

                                <!-- AI Questions Tab (full_service buyer: index 5, others: index 6) -->
                                @php $aiQuestionsTabIndex = $user_type === 'buyer' ? 5 : 6; @endphp
                                <div class="tab-pane fade {{ $activeTab === $aiQuestionsTabIndex ? 'show active' : '' }}" id="ai-questions"
                                    role="tabpanel" aria-labelledby="ai-questions-tab">
                                    @include('livewire.offer-listing.shared.ai-questions-input')
                                </div>
                            @elseif($service_type === 'limited_service')
                                <!-- AI Questions Tab (limited_service: index 5) -->
                                <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}" id="ai-questions"
                                    role="tabpanel" aria-labelledby="ai-questions-tab">
                                    @include('livewire.offer-listing.shared.ai-questions-input')
                                </div>
                            @endif
                        </div>
                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between form-group mt-4">
                            <div>
                                <button type="button" class="btn btn-secondary wizard-step-back" onclick="(function(){var a=Array.from(document.querySelectorAll('#myTab .nav-link')),c=a.find(function(t){return t.classList.contains('active')}),i=c?a.indexOf(c):-1;if(!c||i<=0)return;var p=a[i-1].getAttribute('data-bs-target');if(!p)return;a.forEach(function(l){var lt=l.getAttribute('data-bs-target'),h=(lt===p);l.classList.toggle('active',h);l.setAttribute('aria-selected',h?'true':'false');if(lt){var pn=document.querySelector(lt);if(pn){pn.classList.toggle('show',h);pn.classList.toggle('active',h);}}});sessionStorage.setItem('buyer_create_active_tab',p);var _we=document.querySelector('[wire\\:id]');if(_we&&window.Livewire){var _comp=window.Livewire.find(_we.getAttribute('wire:id'));if(_comp)_comp.call('setActiveTab',i-1);}})();">Back</button>
                            </div>
                            <div>

                                <button type="button" class="btn btn-outline-primary me-2" onclick="if(typeof syncBuyerSelect2BeforeSave==='function')syncBuyerSelect2BeforeSave()" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                    <span wire:loading.remove wire:target="saveDraft"><i class="fa-solid fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving...</span>
                                </button>

                                <button type="button" class="btn btn-primary wizard-step-next" onclick="if(typeof window._wizardNextHandler==='function')window._wizardNextHandler();">Next</button>

                                <button type="submit" class="btn btn-success wizard-step-finish disabled"
                                    id="save-button" wire:loading.attr="disabled" wire:target="store">
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
        let _s2Timers = {};
        let _lastInitTime = 0;
        let _bathroomsDropdownHandler = null;

        function debouncedSet(field, value, delay) {
            clearTimeout(_s2Timers[field]);
            // Use shorter delay for visibility-critical fields
            var effectiveDelay = delay || 200;
            if (field === 'offered_financing' || field === 'sale_provision') {
                effectiveDelay = 50;
            }
            _s2Timers[field] = setTimeout(function() {
                @this.set(field, value);
            }, effectiveDelay);
        }

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
                // Rehydrate Select2 multi-select fields from Livewire state
                var multiFields = {
                    '#sale_provision': 'sale_provision',
                    '#offered_financing': 'offered_financing',
                    '#non_negotiable_amenities': 'non_negotiable_amenities',
                    '#view_preference': 'view_preference',
                    '#condition_prop_buyer': 'condition_prop_buyer',
                    '#garage_parking_spaces_option': 'garage_parking_spaces_option',
                    '#assets': 'assets',
                };
                Object.entries(multiFields).forEach(function([selector, prop]) {
                    var $el = $(selector);
                    if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                        var saved = @this.get(prop) || [];
                        if (saved.length > 0) {
                            $el.val(saved).trigger('change.select2');
                            console.log('[DraftLoaded] Rehydrated ' + prop + ':', saved);
                        }
                    }
                });
                jsonRestoreSelect2();
                if (typeof window.updateSaveButton === 'function') {
                    window.updateSaveButton();
                }
            }, 100);
            // Additional delayed call to ensure FontAwesome has fully loaded
            setTimeout(function() {
                addIconsToInputs();
            }, 500);
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

        function setJsonModel(modelName, arr) {
            var json = JSON.stringify(arr || []);
            var input = document.querySelector('input[wire\\:model\\.defer="' + modelName + '"]')
                     || document.querySelector('input[wire\\:model="' + modelName + '"]');
            if (input) {
                input.value = json;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof @this !== 'undefined' && @this) {
                @this.set(modelName, json);
            }
            console.log('[JSON BRIDGE]', modelName, json, 'input found?', !!input);
        }

        function jsonRestoreSelect2() {
            try {
                var cond = JSON.parse(@this.get('condition_prop_buyer_json') || '[]');
                if (cond.length) $('#condition_prop_buyer').val(cond).trigger('change.select2');

                var units = JSON.parse(@this.get('number_of_unit_type_json') || '[]');
                if (units.length) $('.number_of_unit_type').each(function(){ $(this).val(units).trigger('change.select2'); });

                var items = JSON.parse(@this.get('property_items_json') || '[]');
                if (items.length) $('#property_items').val(items).trigger('change.select2');

                console.log('[JSON RESTORE OK]', {cond: cond, units: units, items: items});
            } catch(e) {
                console.log('[JSON RESTORE ERROR]', e);
            }
        }

        function syncBuyerSelect2BeforeSave() {
            Object.keys(_s2Timers).forEach(function(k) { clearTimeout(_s2Timers[k]); });
            _s2Timers = {};

            var cpb = $('#condition_prop_buyer').val() || [];
            var nut = $('.number_of_unit_type').val() || [];
            var pi = $('#property_items').val() || [];
            var nna = $('#non_negotiable_amenities').val() || [];
            var sp = $('#sale_provision').val() || [];
            var of = $('#offered_financing').val() || [];
            var vp = $('#view_preference').val() || [];
            var gps = $('#garage_parking_spaces_option').val() || [];
            var ass = $('#assets').val() || [];

            cpb = [...new Set(cpb)];
            nut = [...new Set(nut)];
            pi = [...new Set(pi)];
            nna = [...new Set(nna)];
            sp = [...new Set(sp)];
            of = [...new Set(of)];
            vp = [...new Set(vp)];
            gps = [...new Set(gps)];
            ass = [...new Set(ass)];

            @this.set('condition_prop_buyer', cpb);
            @this.set('number_of_unit_type', nut);
            @this.set('property_items', pi);
            @this.set('non_negotiable_amenities', nna);
            @this.set('sale_provision', sp);
            @this.set('offered_financing', of);
            @this.set('view_preference', vp);
            @this.set('garage_parking_spaces_option', gps);
            @this.set('assets', ass);

            setJsonModel('condition_prop_buyer_json', cpb);
            setJsonModel('number_of_unit_type_json', nut);
            setJsonModel('property_items_json', pi);
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
            if (!document._buyerCreateTabNavListenerAdded) {
                document._buyerCreateTabNavListenerAdded = true;
                document.addEventListener('shown.bs.tab', function(e) {
                    var _tgt = e.target.getAttribute('data-bs-target');
                    if (_tgt && e.target.closest('#myTab')) sessionStorage.setItem('buyer_create_active_tab', _tgt);
                });
            }


            if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
                $('#property_items').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });

                $('#property_items').off('change.piSync').on('change.piSync', function(e) {
                    let selectedValues = $(this).val() || [];
                    
                    if (selectedValues.includes('All') && selectedValues.length > 1) {
                        selectedValues = ['All'];
                        $(this).val(selectedValues).trigger('change.select2');
                    }
                    
                    selectedValues = [...new Set(selectedValues)];
                    
                    debouncedSet('property_items', selectedValues);
                    setJsonModel('property_items_json', selectedValues);
                });
            }

            if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                $('#non_negotiable_amenities').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });

                $('#non_negotiable_amenities').off('change.nnaSync').on('change.nnaSync', function(e) {
                    let selectedValues = $(this).val() || [];
                    
                    if (selectedValues.includes('All') && selectedValues.length > 1) {
                        selectedValues = ['All'];
                        $(this).val(selectedValues).trigger('change.select2');
                    }
                    
                    selectedValues = [...new Set(selectedValues)];
                    
                    debouncedSet('non_negotiable_amenities', selectedValues);
                });
            }

            if ($('#condition_prop_buyer').length && !$('#condition_prop_buyer').hasClass('select2-hidden-accessible')) {
                $('#condition_prop_buyer').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });

                $('#condition_prop_buyer').off('change.cpbSync').on('change.cpbSync', function(e) {
                    let selectedValues = $(this).val() || [];
                    selectedValues = [...new Set(selectedValues)];
                    debouncedSet('condition_prop_buyer', selectedValues);
                    setJsonModel('condition_prop_buyer_json', selectedValues);
                });
            }

            if ($('#garage_parking_spaces_option').length && !$('#garage_parking_spaces_option').hasClass('select2-hidden-accessible')) {
                $('#garage_parking_spaces_option').select2({
                    placeholder: "Select parking features",
                    allowClear: true,
                    width: '100%',
                });

                $('#garage_parking_spaces_option').off('change.gpsSync').on('change.gpsSync', function() {
                    let selectedValues = $(this).val() || [];
                    selectedValues = [...new Set(selectedValues)];
                    debouncedSet('garage_parking_spaces_option', selectedValues);
                    var hasOther = selectedValues.includes('Other');
                    var otherWrapper = document.getElementById('other_parking_space_wrapper');
                    var garageMainSelect = document.getElementById('garage_parking_spaces');
                    if (otherWrapper && garageMainSelect && garageMainSelect.value === 'Yes') {
                        otherWrapper.classList.toggle('d-none', !hasOther);
                    }
                });
            }

            if ($('#assets').length && !$('#assets').hasClass('select2-hidden-accessible')) {
                $('#assets').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });

                $('#assets').off('change.assetsSync').on('change.assetsSync', function() {
                    let selectedValues = $(this).val() || [];
                    selectedValues = [...new Set(selectedValues)];
                    debouncedSet('assets', selectedValues);
                });
            }

            $('.number_of_unit_type').each(function() {
                var $el = $(this);
                if (!$el.hasClass('select2-hidden-accessible')) {
                    $el.attr('multiple', 'multiple');
                    $el.select2({
                        placeholder: "Select unit types",
                        allowClear: true,
                        width: '100%',
                    });

                    $el.off('change.nutSync').on('change.nutSync', function(e) {
                        let selectedValues = $el.val() || [];
                        selectedValues = [...new Set(selectedValues)];
                        debouncedSet('number_of_unit_type', selectedValues);
                        setJsonModel('number_of_unit_type_json', selectedValues);
                    });
                }
            });

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
                attachBathroomsDropdownListener();
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
                        optionsWrapper.classList.remove('d-none');
                    } else {
                        optionsWrapper.classList.add('d-none');
                        if (otherInputWrapper) otherInputWrapper.classList.add('d-none');
                    }
                }

                // Then check if "Other" is selected in the options dropdown (handles both native and Select2 multi-select)
                var garageOptionValues = [];
                if (garageOptions) {
                    garageOptionValues = $('#garage_parking_spaces_option').hasClass('select2-hidden-accessible')
                        ? ($('#garage_parking_spaces_option').val() || [])
                        : Array.from(garageOptions.selectedOptions || []).map(function(o) { return o.value; });
                }
                if (otherInputWrapper && garageOptions && garageOptionValues.includes("Other") && garageSelect && garageSelect.value === "Yes") {
                    otherInputWrapper.classList.remove('d-none');
                } else if (otherInputWrapper) {
                    otherInputWrapper.classList.add('d-none');
                }
            }

            // Initialize on page load
            toggleGarageOptions();

            // Listen for Livewire updates
            Livewire.hook('message.processed', () => {
                toggleGarageOptions();
                if ($('#garage_parking_spaces_option').length && !$('#garage_parking_spaces_option').hasClass('select2-hidden-accessible')) {
                    $('#garage_parking_spaces_option').select2({
                        placeholder: "Select parking features",
                        allowClear: true,
                        width: '100%',
                    });
                    $('#garage_parking_spaces_option').off('change.gpsSync').on('change.gpsSync', function() {
                        let selectedValues = $(this).val() || [];
                        selectedValues = [...new Set(selectedValues)];
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

            // Initialize Select2 for multi-select
            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                $('#view_preference').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });

                $('#view_preference').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    
                    if (selectedValues.includes('All') && selectedValues.length > 1) {
                        selectedValues = ['All'];
                        $(this).val(selectedValues).trigger('change.select2');
                    }
                    
                    selectedValues = [...new Set(selectedValues)];
                    
                    debouncedSet('view_preference', selectedValues);

                    if (selectedValues.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                    }
                });
            }

            // Global Helpers: immediately update section visibility via Alpine events
            window.updateAssignmentContractSection = function(selectedValues) {
                if (!Array.isArray(selectedValues)) selectedValues = [selectedValues];
                var isVisible = selectedValues.includes('Assignment Contract');
                window.dispatchEvent(new CustomEvent('update-assignment-visibility', { detail: { visible: isVisible } }));
            };

            window.updateFinancingSections = function(selectedValues) {
                if (!Array.isArray(selectedValues)) selectedValues = [selectedValues];
                var traditionalLoanTypes = ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'];
                
                var types = ['Assumable', 'Cryptocurrency', 'Exchange/Trade', 'Lease Option', 'Lease Purchase', 'Non-Fungible Token (NFT)', 'Seller Financing'];
                
                types.forEach(function(type) {
                    window.dispatchEvent(new CustomEvent('update-financing-visibility', { 
                        detail: { type: type, visible: selectedValues.includes(type) } 
                    }));
                });

                // Traditional check
                var traditionalVisible = traditionalLoanTypes.some(function(t) { return selectedValues.includes(t); });
                window.dispatchEvent(new CustomEvent('update-financing-visibility', { 
                    detail: { type: 'Traditional', visible: traditionalVisible } 
                }));
            };

            // Initialize Select2 for sale_provision (Purchasing Terms tab)
            if ($('#sale_provision').length && !$('#sale_provision').hasClass('select2-hidden-accessible')) {
                $('#sale_provision').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });
                // Initial visibility sync
                window.updateAssignmentContractSection($('#sale_provision').val() || []);
            }
            // Bind sale_provision change handler
            $('#sale_provision').off('change.spSync select2:select.spSync select2:unselect.spSync')
                .on('change.spSync select2:select.spSync select2:unselect.spSync', function() {
                    let selectedValues = $('#sale_provision').val() || [];
                    window.updateAssignmentContractSection(selectedValues);
                    debouncedSet('sale_provision', selectedValues, 50);
                });

            // Global flag to prevent Livewire sync during draft/edit load
            window.financingSyncInProgress = false;
            
            // Initialize Select2 for offered_financing (Purchasing Terms tab)
            if ($('#offered_financing').length && !$('#offered_financing').hasClass('select2-hidden-accessible')) {
                $('#offered_financing').select2({
                    placeholder: "Select",
                    allowClear: true,
                    width: '100%',
                });
                // Initial visibility sync
                window.updateFinancingSections($('#offered_financing').val() || []);
            }
            // Bind offered_financing change handler
            $('#offered_financing').off('change.ofSync select2:select.ofSync select2:unselect.ofSync')
                .on('change.ofSync select2:select.ofSync select2:unselect.ofSync', function() {
                    if (window.financingSyncInProgress) return;
                    let selectedValues = $('#offered_financing').val() || [];
                    window.updateFinancingSections(selectedValues);
                    debouncedSet('offered_financing', selectedValues, 50);
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
                // Re-sync financing sections with current Select2 state (prevents Livewire re-render from overriding JS visibility)
                if ($('#offered_financing').hasClass('select2-hidden-accessible')) {
                    if (window.updateFinancingSections) window.updateFinancingSections($('#offered_financing').val() || []);
                }
                if ($('#sale_provision').hasClass('select2-hidden-accessible')) {
                    if (window.updateAssignmentContractSection) window.updateAssignmentContractSection($('#sale_provision').val() || []);
                }
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




            function toggleVacantLandOther(clearOnDeselect) {
                var vals = $('#property_items').val() || [];
                var otherDiv = document.querySelector('.other_property_items');
                if (!otherDiv) return;
                if (vals.includes('Other')) {
                    otherDiv.classList.remove('d-none');
                } else {
                    otherDiv.classList.add('d-none');
                    if (clearOnDeselect) {
                        @this.set('other_property_items', '');
                    }
                }
            }

            toggleVacantLandOther(false);

            $('#property_items').off('change.vacantOther').on('change.vacantOther', function() {
                toggleVacantLandOther(true);
            });

            Livewire.hook('message.processed', () => {
                toggleVacantLandOther(false);
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
            
            // State tracking for Buyer form validation (populated by browser events from Livewire)
            window.buyerState = {
                countiesCount: {{ count($counties ?? []) }},
                auctionType: '{{ $auction_type ?? '' }}'
            };
            
            // Listen for Livewire browser events to update state
            window.addEventListener('buyer-state-init', (e) => {
                window.buyerState.countiesCount = e.detail.countiesCount || 0;
                window.buyerState.auctionType = e.detail.auctionType || '';
                console.log('[Buyer] State init:', window.buyerState);
                checkFormValidity();
            });
            
            window.addEventListener('buyer-counties-updated', (e) => {
                window.buyerState.countiesCount = e.detail.count || 0;
                console.log('[Buyer] Counties updated:', window.buyerState.countiesCount);
                checkFormValidity();
            });
            
            window.addEventListener('buyer-auction-type-changed', (e) => {
                window.buyerState.auctionType = e.detail.type || '';
                console.log('[Buyer] Auction type changed:', window.buyerState.auctionType);
                checkFormValidity();
            });

            // Function to check if all required fields are filled
            // Split logic by listing type: Traditional vs Bidding Period
            function checkFormValidity() {
                let allValid = true;
                let invalidFields = []; // TEMP DEBUG

                // Get auction type from buyerState (set by browser events) - authoritative source
                let currentListingType = window.buyerState ? window.buyerState.auctionType : '';
                
                // Fallback to hidden input if buyerState not set
                if (!currentListingType) {
                    const auctionTypeHidden = document.getElementById('auction_type_hidden');
                    currentListingType = auctionTypeHidden ? auctionTypeHidden.value : '';
                }
                console.log('[Buyer] Current listing type:', currentListingType);

                // Define fields that are ONLY required for Bidding Period
                const biddingPeriodOnlyFields = ['auction_time'];
                
                // EXPLICIT CHECK: Counties are REQUIRED (use buyerState - authoritative source)
                let hasCounties = window.buyerState ? window.buyerState.countiesCount > 0 : false;
                console.log('[Buyer] Counties count from state:', window.buyerState ? window.buyerState.countiesCount : 'N/A');
                
                if (!hasCounties) {
                    allValid = false;
                    invalidFields.push('counties (at least one required)');
                }
                
                // Check all tabs for required fields (skip hidden/disabled fields)
                document.querySelectorAll('.tab-pane').forEach(tabPane => {
                    tabPane.querySelectorAll('[required]').forEach(field => {
                        const fieldName = field.name || field.id || field.getAttribute('wire:model') || 'unknown';
                        
                        // Skip the counties_hidden field - we check badges explicitly above
                        if (field.id === 'counties_hidden') {
                            return;
                        }
                        
                        // Skip hidden or disabled fields - they should not block form validity
                        if (!isElementVisible(field)) {
                            return;
                        }
                        // Skip disabled fields
                        if (field.disabled) {
                            return;
                        }
                        
                        // SPLIT LOGIC: Skip Bidding Period-only fields when Traditional is selected
                        if (currentListingType === 'Traditional' && biddingPeriodOnlyFields.includes(field.id)) {
                            console.log('[Buyer] Skipping Bidding Period field for Traditional:', fieldName);
                            return;
                        }
                        
                        // SPLIT LOGIC: Skip auction_type validation for hidden required field if value exists
                        if (field.id === 'auction_type_hidden' && field.value) {
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
                            // TEMP DEBUG: Log which fields are failing
                            invalidFields.push(fieldName);
                        }
                    });
                });

                // TEMP DEBUG: Log invalid fields to console
                if (invalidFields.length > 0) {
                    console.log('[Buyer validity check failing]:', invalidFields.join(', '));
                } else {
                    console.log('[Buyer validity check] All fields valid');
                }

                // Enable/disable save button (both CSS class and attribute)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    if (allValid) {
                        saveButton.classList.remove('disabled');
                        saveButton.removeAttribute('disabled');
                        console.log('[Buyer Submit] Button ENABLED');
                    } else {
                        saveButton.classList.add('disabled');
                        saveButton.setAttribute('disabled', 'disabled');
                        console.log('[Buyer Submit] Button DISABLED');
                    }
                }

                return allValid;
            }

            } catch (_setupErr) { console.warn('[buyer offer-listing] initializeFullService setup error:', _setupErr); }
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

                // Cities and Counties are OPTIONAL - no validation required
                // Clear any existing error messages for cities/counties
                const citiesContainer = currentTabContent.querySelector('.cities-container');
                if (citiesContainer) {
                    const existingCitiesError = citiesContainer.parentNode.querySelector('.error');
                    if (existingCitiesError) {
                        existingCitiesError.remove();
                    }
                }
                const countiesContainer = currentTabContent.querySelector('.counties-container');
                if (countiesContainer) {
                    const existingCountiesError = countiesContainer.parentNode.querySelector('.error');
                    if (existingCountiesError) {
                        existingCountiesError.remove();
                    }
                }

                // ADD THIS: Validate services tab if it's the current tab
                if (currentTabContent.id === 'services') {
                    isValid = isValid && validateServicesTab(currentTabContent);
                }

                // Offered Financing is required in purchasing-terms — Select2 removes 'required' so check manually
                if (currentTabContent.id === 'purchasing-terms') {
                    var ofErrorSpan2 = currentTabContent.querySelector('#offered_financing_error');
                    var ofJqVal = (typeof jQuery !== 'undefined') ? jQuery('#offered_financing').val() : null;
                    var ofIsEmpty = !ofJqVal || (Array.isArray(ofJqVal) && ofJqVal.length === 0);
                    if (ofIsEmpty) {
                        var ofNativeEl = currentTabContent.querySelector('#offered_financing');
                        if (ofNativeEl && ofNativeEl.selectedOptions && ofNativeEl.selectedOptions.length > 0) {
                            ofIsEmpty = false;
                        }
                    }
                    if (ofIsEmpty) {
                        isValid = false;
                        if (ofErrorSpan2) { ofErrorSpan2.textContent = 'This field is required.'; }
                    } else {
                        if (ofErrorSpan2) { ofErrorSpan2.textContent = ''; }
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

                // If all fields are valid, proceed to the next tab (your existing code)
                if (isValid) {
                    const _allTabs = Array.from(document.querySelectorAll('#myTab .nav-link'));
                    const _curIdx = _allTabs.indexOf(currentTab);
                    if (_curIdx < _allTabs.length - 1) {
                        const _nextTab = _allTabs[_curIdx + 1];
                        var _nId = _nextTab.getAttribute('data-bs-target');
                        if (_nId) {
                            window._manualTabSwitch(_nId);
                            sessionStorage.setItem('buyer_create_active_tab', _nId);
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
                sessionStorage.setItem('buyer_create_active_tab', _pId);
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
            // Split logic by listing type: Traditional vs Bidding Period
            function checkFormValidity() {
                let allValid = true;
                let invalidFields = []; // TEMP DEBUG

                // Get auction type from buyerState (set by browser events) - authoritative source
                let currentListingType = window.buyerState ? window.buyerState.auctionType : '';
                
                // Fallback to hidden input if buyerState not set
                if (!currentListingType) {
                    const auctionTypeHidden = document.getElementById('auction_type_hidden');
                    currentListingType = auctionTypeHidden ? auctionTypeHidden.value : '';
                }
                console.log('[Buyer] Current listing type:', currentListingType);

                // Define fields that are ONLY required for Bidding Period
                const biddingPeriodOnlyFields = ['auction_time'];
                
                // EXPLICIT CHECK: Counties are REQUIRED (use buyerState - authoritative source)
                let hasCounties = window.buyerState ? window.buyerState.countiesCount > 0 : false;
                console.log('[Buyer] Counties count from state:', window.buyerState ? window.buyerState.countiesCount : 'N/A');
                
                if (!hasCounties) {
                    allValid = false;
                    invalidFields.push('counties (at least one required)');
                }
                
                // Check all tabs for required fields (skip hidden/disabled fields)
                document.querySelectorAll('.tab-pane').forEach(tabPane => {
                    tabPane.querySelectorAll('[required]').forEach(field => {
                        const fieldName = field.name || field.id || field.getAttribute('wire:model') || 'unknown';
                        
                        // Skip the counties_hidden field - we check badges explicitly above
                        if (field.id === 'counties_hidden') {
                            return;
                        }
                        
                        // Skip hidden or disabled fields - they should not block form validity
                        if (!isElementVisible(field)) {
                            return;
                        }
                        // Skip disabled fields
                        if (field.disabled) {
                            return;
                        }
                        
                        // SPLIT LOGIC: Skip Bidding Period-only fields when Traditional is selected
                        if (currentListingType === 'Traditional' && biddingPeriodOnlyFields.includes(field.id)) {
                            console.log('[Buyer] Skipping Bidding Period field for Traditional:', fieldName);
                            return;
                        }
                        
                        // SPLIT LOGIC: Skip auction_type validation for hidden required field if value exists
                        if (field.id === 'auction_type_hidden' && field.value) {
                            return;
                        }
                        
                        if (!field.value) {
                            allValid = false;
                            // TEMP DEBUG: Log which fields are failing
                            invalidFields.push(fieldName);
                        }
                    });
                });

                // TEMP DEBUG: Log invalid fields to console
                if (invalidFields.length > 0) {
                    console.log('[Buyer validity check failing]:', invalidFields.join(', '));
                } else {
                    console.log('[Buyer validity check] All fields valid');
                }

                // Enable/disable save button (both CSS class and attribute)
                const saveButton = document.querySelector('.wizard-step-finish');
                if (saveButton) {
                    if (allValid) {
                        saveButton.classList.remove('disabled');
                        saveButton.removeAttribute('disabled');
                        console.log('[Buyer Submit] Button ENABLED');
                    } else {
                        saveButton.classList.add('disabled');
                        saveButton.setAttribute('disabled', 'disabled');
                        console.log('[Buyer Submit] Button DISABLED');
                    }
                }

                return allValid;
            }

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


        Livewire.hook('message.processed', () => {
            addIconsToInputs();

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

            var now = Date.now();
            var propertyItemsNeedsInit = $('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible');
            if (propertyItemsNeedsInit || now - _lastInitTime > 300) {
                _lastInitTime = now;
                removeWizardEventListeners();

                if (currentServiceType === 'full_service') {
                    initializeFullService();
                } else if (currentServiceType === 'limited_service') {
                    initializeLimitedService();
                }
            }

            var _savedTabId = sessionStorage.getItem('buyer_create_active_tab');
            if (_savedTabId && typeof window._manualTabSwitch === 'function') {
                var _tabTrigger = document.querySelector('#myTab .nav-link[data-bs-target="' + _savedTabId + '"]');
                if (_tabTrigger && !_tabTrigger.classList.contains('active')) {
                    window._manualTabSwitch(_savedTabId);
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
                    '#purchasing-terms',
                    '#services',
                    '#additional-details',
                    '#buyer-information'
                ] : [
                    '#listing-details',
                    '#location-and-meeting-details',
                    '#service-selection-and-pricing',
                    '#buyer-information'
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

                // Stable label map for every required Buyer field.
                // Keyed by wire:model property name.  Labels match exact wording shown in the live UI.
                const BUYER_FIELD_LABELS = {
                    'listing_title':           'Listing Title',
                    'desired_agent_hire_date': 'Desired Agent Hire Date',
                    'listing_date':            'Listing Date',
                    'expiration_date':         'Expiration Date',
                    'auction_type':            'Listing Type',
                    'auction_time':            'Bidding Period Length',
                    'state':                   'Acceptable State',
                    'property_type':           'Acceptable Property Type',
                    'property_items':          'Acceptable Property Style',
                    'bedrooms':                'Minimum Bedrooms Needed',
                    'bathrooms':               'Minimum Bathrooms Needed',
                    'counties':                'Acceptable Counties',
                    'target_closing_date':     'Target Closing Date',
                    'maximum_budget':          'Maximum Budget',
                    'offered_financing':       'Offered Financing',
                    'tenant_require':          'Furnishings Needed',
                    'real_estate_purchase':    'Business & Real Estate Purchase Requirements',
                    'first_name':              'First Name',
                    'last_name':               'Last Name',
                    'phone_number':            'Phone Number',
                    'email':                   'Email Address',
                };

                // Returns the canonical property key for a field: wire:model value first,
                // then wire:model.defer / wire:model.lazy, then id, then name.
                function resolveBuyerFieldKey(field) {
                    return field.getAttribute('wire:model')
                        || field.getAttribute('wire:model.defer')
                        || field.getAttribute('wire:model.lazy')
                        || field.id
                        || field.name
                        || '';
                }

                // Returns the user-facing label for a Buyer field.
                // Prefers BUYER_FIELD_LABELS (stable); falls back to DOM label text.
                function resolveBuyerFieldLabel(field) {
                    var key = resolveBuyerFieldKey(field);
                    if (key && BUYER_FIELD_LABELS[key]) {
                        return BUYER_FIELD_LABELS[key];
                    }
                    var label = field.closest('.form-group') && field.closest('.form-group').querySelector('label');
                    if (label) {
                        return label.textContent.replace(/[*:]/g, '').trim();
                    }
                    return field.getAttribute('placeholder') || field.name || field.id || 'Required field';
                }

                // Returns true if the field is hidden by conditional rendering WITHIN its
                // tab pane (d-none / inline display:none), but does NOT treat an inactive
                // tab-pane as hidden, enabling cross-tab validation.
                function isBuyerFieldHiddenWithinTab(field) {
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

                // ---- BUYER CORRECTION MODE -------------------------------------------
                // Tracks whether the form is in guided-correction mode (submit was blocked
                // and we are walking the user through each still-missing required field).
                var _buyerCorrectionMode = false;
                var _buyerMissingItems = [];

                // Re-runs all buyer required checks and returns the current ordered list
                // of still-missing required fields.
                function buyerGetInvalidItems() {
                    var items = [];
                    var reqFields = getAllRequiredFields();
                    reqFields.forEach(function(field) {
                        if (!isElementVisible(field)) return;
                        // Skip Select2-managed multi-selects — handled via Livewire state check below.
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
                            var _fKey = resolveBuyerFieldKey(field);
                            items.push({ field: field, tab: field.closest('.tab-pane'), fieldName: resolveBuyerFieldLabel(field), key: _fKey });
                        }
                    });

                    // Counties badge check
                    var countiesContainer = document.querySelector('.counties-container');
                    if (countiesContainer) {
                        var countyBadges = countiesContainer.querySelectorAll('.badge');
                        if (!countyBadges || countyBadges.length === 0) {
                            items.push({ field: countiesContainer, tab: countiesContainer.closest('.tab-pane'), fieldName: BUYER_FIELD_LABELS['counties'] || 'Acceptable Counties', key: 'counties' });
                        }
                    }

                    // Livewire-state checks for Select2 multi-selects (wire:ignore — not catchable via DOM)
                    try {
                        var _wireEl = document.querySelector('[wire\\:id]');
                        if (_wireEl && typeof Livewire !== 'undefined') {
                            var _comp = Livewire.find(_wireEl.getAttribute('wire:id'));
                            if (_comp && _comp.get) {
                                // property_items: required when property_type is selected
                                var _ptValBgi = _comp.get('property_type');
                                if (_ptValBgi && _ptValBgi !== '') {
                                    var _piValBgi = _comp.get('property_items');
                                    if (typeof _piValBgi === 'string') { try { _piValBgi = JSON.parse(_piValBgi); } catch(ex) {} }
                                    var _piEmptyBgi = !_piValBgi || (Array.isArray(_piValBgi) && _piValBgi.length === 0) || _piValBgi === '[]';
                                    if (_piEmptyBgi) {
                                        var $piDom = $('#property_items');
                                        if ($piDom.length) {
                                            var _piDomVal = $piDom.val();
                                            if (_piDomVal && Array.isArray(_piDomVal) && _piDomVal.length > 0) _piEmptyBgi = false;
                                        }
                                    }
                                    if (_piEmptyBgi && !items.some(function(i) { return i.key === 'property_items'; })) {
                                        var _piEl = document.getElementById('property_items');
                                        var _piTab = _piEl ? _piEl.closest('.tab-pane') : null;
                                        items.push({ field: _piEl || document.body, tab: _piTab, fieldName: BUYER_FIELD_LABELS['property_items'] || 'Acceptable Property Style', key: 'property_items' });
                                    }
                                }

                                // offered_financing: always required
                                var _ofValBgi = _comp.get('offered_financing');
                                if (typeof _ofValBgi === 'string') { try { _ofValBgi = JSON.parse(_ofValBgi); } catch(ex2) {} }
                                var _ofEmptyBgi = !_ofValBgi || (Array.isArray(_ofValBgi) && _ofValBgi.length === 0) || _ofValBgi === '' || _ofValBgi === '[]';
                                if (_ofEmptyBgi) {
                                    var $ofDom = $('#offered_financing');
                                    if ($ofDom.length) {
                                        var _ofDomVal = $ofDom.val();
                                        if (_ofDomVal && Array.isArray(_ofDomVal) && _ofDomVal.length > 0) _ofEmptyBgi = false;
                                    }
                                }
                                if (_ofEmptyBgi && !items.some(function(i) { return i.key === 'offered_financing'; })) {
                                    var _ofEl = document.getElementById('offered_financing');
                                    var _ofTab = _ofEl ? _ofEl.closest('.tab-pane') : null;
                                    items.push({ field: _ofEl || document.body, tab: _ofTab, fieldName: BUYER_FIELD_LABELS['offered_financing'] || 'Offered Financing', key: 'offered_financing' });
                                }
                            }
                        }
                    } catch(ex3) {}

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
                function buyerNavigateToItem(item) {
                    if (item && item.tab) {
                        var _tabId = item.tab.id;
                        var _tabTrigger = document.querySelector('[data-bs-target="#' + _tabId + '"], [href="#' + _tabId + '"]');
                        if (_tabTrigger) {
                            new bootstrap.Tab(_tabTrigger).show();
                            // Sync server $activeTab via wire:click="setActiveTab(N)"
                            var _wc = _tabTrigger.getAttribute('wire:click') || '';
                            var _m = _wc.match(/setActiveTab\((\d+)\)/);
                            if (_m) {
                                try { @this.call('setActiveTab', parseInt(_m[1])); } catch(ex4) {}
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
                            _banner2.classList.remove('d-none');
                            _banner2.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }, 350);
                }

                // Called after each Livewire update when in correction mode.
                // If a previously-missing field is now filled, advance to the next one.
                // When all required fields are complete, exit correction mode silently.
                function buyerAdvanceCorrection() {
                    if (!_buyerCorrectionMode) return;
                    var freshMissing = buyerGetInvalidItems();

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
                        // All required fields are now complete. Exit correction mode,
                        // hide the banner, and navigate back to the submit tab.
                        _buyerCorrectionMode = false;
                        _buyerMissingItems = [];
                        if (_banner3) _banner3.classList.add('d-none');
                        var _submitTabTrigger = document.querySelector('[data-bs-target="#buyer-information"]');
                        if (_submitTabTrigger) {
                            new bootstrap.Tab(_submitTabTrigger).show();
                            var _infoWc = _submitTabTrigger.getAttribute('wire:click') || '';
                            var _infoM = _infoWc.match(/setActiveTab\((\d+)\)/);
                            if (_infoM) {
                                try { @this.call('setActiveTab', parseInt(_infoM[1])); } catch(ex5) {}
                            }
                        }
                        return;
                    }

                    // Re-show banner (Livewire DOM-diff re-adds d-none on every setActiveTab round-trip)
                    if (_banner3) _banner3.classList.remove('d-none');

                    // Still have missing fields — navigate to the first remaining one
                    _buyerMissingItems = freshMissing;
                    buyerNavigateToItem(freshMissing[0]);
                }
                // ---- END BUYER CORRECTION MODE ----------------------------------------

                // Register a Livewire message.processed hook inside createForm to advance correction mode.
                if (typeof Livewire !== 'undefined') {
                    Livewire.hook('message.processed', function() {
                        setTimeout(function() {
                            buyerAdvanceCorrection();
                        }, 350);
                    });
                }

                document.addEventListener('submit', function(e) {
                    if (!e.target || e.target.id !== 'create-auction-form') return;
                    const banner = document.getElementById('submit-error-banner');
                    const errorList = document.getElementById('submit-error-list');
                    if (banner) banner.classList.add('d-none');
                    if (errorList) errorList.innerHTML = '';

                    let invalidItems = buyerGetInvalidItems();

                    if (invalidItems.length > 0) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        // Build deduplicated list and populate banner
                        const seen = new Set();
                        const deduplicatedItems = [];
                        invalidItems.forEach(function(item) {
                            const _dedupeKey = item.key || item.fieldName;
                            if (!seen.has(_dedupeKey)) {
                                seen.add(_dedupeKey);
                                deduplicatedItems.push(item);
                                const li = document.createElement('li');
                                li.textContent = item.fieldName;
                                errorList.appendChild(li);
                            }
                        });
                        if (banner) banner.classList.remove('d-none');

                        // Enter guided correction mode
                        _buyerCorrectionMode = true;
                        _buyerMissingItems = deduplicatedItems;
                        buyerNavigateToItem(deduplicatedItems[0]);
                        return false;
                    }

                    syncBuyerSelect2BeforeSave();
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
    <script>
        function initSelect2Fields() {
            $('.select2-multiple, .select2-single').each(function () {
                var $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) return;
                var placeholder = $el.attr('data-placeholder') || 'Select';
                $el.select2({
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%'
                });
            });
        }

        $(document).ready(function () {
            initSelect2Fields();
        });

        document.addEventListener('livewire:load', function () {
            Livewire.hook('message.processed', function () {
                initSelect2Fields();
            });
        });
    </script>
@endpush
