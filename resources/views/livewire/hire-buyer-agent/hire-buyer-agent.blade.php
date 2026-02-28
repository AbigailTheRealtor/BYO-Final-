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
        ['name' => 'Completely Updated: No updates needed'],
        ['name' => 'Currently Being Built'],
        ['name' => 'New Construction'],
        ['name' => 'Not Updated: Requires a complete update'],
        ['name' => 'Open to any type of property condition'],
        ['name' => 'Pre-Construction'],
        ['name' => 'Semi-updated: Needs minor updates'],
        ['name' => 'Tear Down: Requires complete demolition and reconstruction'],
        // ['name' => 'Open to any type of property condition.'],
        // ['name' => 'Semi-updated: Needs minor updates.'],
        // ['name' => 'Other'],
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

                    <form wire:submit.prevent="store">
                        <!-- Tab Navigation -->

                        @if ($service_type === 'full_service')

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (['Listing Details', 'Property Preferences', 'Purchasing Terms', 'Services', 'Additional Details', 'Broker Compensation', 'Buyer Information'] as $index => $tab)
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
                                <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}"
                                    id="purchasing-terms" role="tabpanel" aria-labelledby="purchasing-terms-tab">

                                    @if ($user_type === 'tenant')
                                        @include('livewire.tenant-agent-auction-tabs.commission-based.leasing-terms')
                                    @elseif($user_type === 'seller')
                                        @include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.purchasing-terms')
                                    @elseif($user_type === 'buyer')
                                        @include('livewire.hire-buyer-agent.buyer-agent-auction-tabs.commission-based.purchasing-terms')
                                    @elseif($user_type === 'landlord')
                                        @include('livewire.hire-landlord-agent.landlord-agent-auction-tabs.commission-based.purchasing-terms')
                                    @endif
                                </div>

                                <!-- Services Tab -->
                                <div class="tab-pane fade {{ $activeTab === 3 ? 'show active' : '' }}" id="services"
                                    role="tabpanel" aria-labelledby="services-tab">

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
                                <!-- Additional Details Tab -->
                                <div class="tab-pane fade {{ $activeTab === 4 ? 'show active' : '' }}"
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

                                <!-- Broker Compensation Tab -->
                                <div class="tab-pane fade {{ $activeTab === 5 ? 'show active' : '' }}"
                                    id="broker-compensation" role="tabpanel"
                                    aria-labelledby="broker-compensation-tab">

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

                                <!-- Buyer Info Tab -->
                                <div class="tab-pane fade {{ $activeTab === 6 ? 'show active' : '' }}"
                                    id="buyer-information" role="tabpanel" aria-labelledby="buyer-information-tab">
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
                            @endif
                        </div>
                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between form-group mt-4">
                            <div>
                                <button type="button" class="btn btn-secondary wizard-step-back">Back</button>
                            </div>
                            <div>

                                <button type="button" class="btn btn-outline-primary me-2" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                                    <span wire:loading.remove wire:target="saveDraft"><i class="fas fa-save me-1"></i> Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving...</span>
                                </button>

                                <button type="button" class="btn btn-primary wizard-step-next">Next</button>

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
            var $input = $('input[wire\\:model\\.defer="' + modelName + '"]');
            if (!$input.length) {
                $input = $('input[wire\\:model="' + modelName + '"]');
            }
            if ($input.length) {
                $input.val(json).trigger('input');
            } else {
                @this.set(modelName, json);
            }
            console.log('[JSON SET]', modelName, json);
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

        function initializeFullService() {


            if ($('#property_items').length && !$('#property_items').hasClass('select2-hidden-accessible')) {
                $('#property_items').select2({
                    placeholder: "Select property style",
                    allowClear: true,
                });

                $('#property_items').off('change.piSync').on('change.piSync', function(e) {
                    let selectedValues = $(this).val() || [];
                    
                    if (selectedValues.includes('All') && selectedValues.length > 1) {
                        selectedValues = ['All'];
                        $(this).val(selectedValues).trigger('change.select2');
                    }
                    
                    selectedValues = [...new Set(selectedValues)];
                    
                    setJsonModel('property_items_json', selectedValues);
                });
            }

            if ($('#non_negotiable_amenities').length && !$('#non_negotiable_amenities').hasClass('select2-hidden-accessible')) {
                $('#non_negotiable_amenities').select2({
                    placeholder: "Select credit score rating(s)",
                    allowClear: true,
                });

                $('#non_negotiable_amenities').off('change.nnaSync').on('change.nnaSync', function(e) {
                    let selectedValues = $(this).val() || [];
                    
                    if (selectedValues.includes('All') && selectedValues.length > 1) {
                        selectedValues = ['All'];
                        $(this).val(selectedValues).trigger('change.select2');
                    }
                    
                    selectedValues = [...new Set(selectedValues)];
                    
                    @this.set('non_negotiable_amenities', selectedValues);
                });
            }

            if ($('#condition_prop_buyer').length && !$('#condition_prop_buyer').hasClass('select2-hidden-accessible')) {
                $('#condition_prop_buyer').select2({
                    placeholder: "Select property conditions",
                    allowClear: true,
                });

                $('#condition_prop_buyer').off('change.cpbSync').on('change.cpbSync', function(e) {
                    let selectedValues = $(this).val() || [];
                    selectedValues = [...new Set(selectedValues)];
                    setJsonModel('condition_prop_buyer_json', selectedValues);
                });
            }

            $('.number_of_unit_type').each(function() {
                var $el = $(this);
                if (!$el.hasClass('select2-hidden-accessible')) {
                    $el.attr('multiple', 'multiple');
                    $el.select2({
                        placeholder: "Select unit types",
                        allowClear: true,
                    });

                    $el.off('change.nutSync').on('change.nutSync', function(e) {
                        let selectedValues = $el.val() || [];
                        selectedValues = [...new Set(selectedValues)];
                        setJsonModel('number_of_unit_type_json', selectedValues);
                    });
                }
            });

            jsonRestoreSelect2();

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

            // Initialize Select2 for multi-select
            if ($('#view_preference').length && !$('#view_preference').hasClass('select2-hidden-accessible')) {
                $('#view_preference').select2({
                    placeholder: "Select Preference",
                    allowClear: true
                });

                $('#view_preference').on('change', function() {
                    let selectedValues = $(this).val() || [];
                    
                    if (selectedValues.includes('All') && selectedValues.length > 1) {
                        selectedValues = ['All'];
                        $(this).val(selectedValues).trigger('change.select2');
                    }
                    
                    selectedValues = [...new Set(selectedValues)];
                    
                    Livewire.emit('updatePreference', selectedValues);

                    if (selectedValues.includes('Other')) {
                        $('#other_preferences').show();
                    } else {
                        $('#other_preferences').hide();
                    }
                });
            }

            // Initialize Select2 for sale_provision (Purchasing Terms tab)
            if ($('#sale_provision').length && !$('#sale_provision').hasClass('select2-hidden-accessible')) {
                $('#sale_provision').select2({
                    placeholder: "Select",
                    allowClear: true,
                }).on('change', function() {
                    let selectedValues = $(this).val() || [];
                    @this.set('sale_provision', selectedValues);
                });
            }

            // Global flag to prevent Livewire sync during draft/edit load
            window.financingSyncInProgress = false;
            
            // Initialize Select2 for offered_financing (Purchasing Terms tab)
            if ($('#offered_financing').length && !$('#offered_financing').hasClass('select2-hidden-accessible')) {
                $('#offered_financing').select2({
                    placeholder: "Select",
                    allowClear: true,
                }).on('change', function() {
                    if (window.financingSyncInProgress) {
                        return;
                    }
                    let selectedValues = $(this).val() || [];
                    @this.set('offered_financing', selectedValues);
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

                // Cities and Counties are OPTIONAL - no validation required
                // Clear any existing error messages for cities/counties
                const citiesContainer2 = currentTabContent.querySelector('.cities-container');
                if (citiesContainer2) {
                    const existingCitiesError2 = citiesContainer2.parentNode.querySelector('.error');
                    if (existingCitiesError2) {
                        existingCitiesError2.remove();
                    }
                }
                const countiesContainer2 = currentTabContent.querySelector('.counties-container');
                if (countiesContainer2) {
                    const existingCountiesError2 = countiesContainer2.parentNode.querySelector('.error');
                    if (existingCountiesError2) {
                        existingCountiesError2.remove();
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
                    '#purchasing-terms',
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
@endpush
