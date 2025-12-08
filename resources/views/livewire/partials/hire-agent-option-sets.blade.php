@php

$property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];

$property_condition = [
['name' => 'Completely Updated: No updates needed'],
['name' => 'New Construction'],
['name' => 'Not Updated: Requires a complete update'],
['name' => 'Semi-updated: Needs minor updates'],
// ['name' => 'Other'],
];
$property_condition_seller = [
['name' => 'Completely Updated: No updates needed'],
['name' => 'Currently Being Built'],
['name' => 'New Construction'],
['name' => 'Not Updated: Requires a complete update'],
['name' => 'Pre-Construction'],
['name' => 'Semi-updated: Needs minor updates'],
['name' => 'Tear Down: Requires complete demolition and reconstruction'],
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
$acceptable_leasing_space = [
['name' => 'Accessory Unit / Guest Suite (ADU)'],

['name' => 'Entire Property'],
['name' => 'Single Room'],

// ['name' => 'Open to Leasing Either Entire Property or Single Room'],
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
'The Seller is assigning their contractual rights to another Buyer (commonly used in wholesaling).⚠️ If the Seller is under contract to purchase a property and intends to assign their purchase rights to another Buyer, they are considered the Seller of that contract.If the Seller is looking to purchase an assignment contract, they are considered the Buyer of that contract. In that case, please switch to a Buyer listing instead.',
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
'Buyer is allowed to assume the Seller’s existing mortgage, subject to lender approval. This can be attractive if the current interest rate is lower than prevailing market rates.',
],
[
'name' => 'Cash',
'description' => 'Buyer is allowed to pay the full purchase price in cash, with no financing contingency.',
],
[
'name' => 'Conventional',
'description' => 'Buyer is allowed to use a traditional loan that meets standard underwriting guidelines.',
],
[
'name' => 'Cryptocurrency',
'description' =>
'Buyer is allowed to use digital currency (e.g., Bitcoin or Ethereum) as a form of payment, subject to Seller acceptance.',
],
[
'name' => 'Exchange/Trade',
'description' => 'Buyer is allowed to offer another asset as part of the purchase in a trade.',
],
[
'name' => 'FHA',
'description' =>
'Buyer is allowed to use a loan backed by the Federal Housing Administration, typically requiring the property to meet condition standards.',
],
[
'name' => 'Jumbo',
'description' =>
'Buyer is allowed to use a loan above conforming loan limits, often requiring stricter qualifications.',
],
[
'name' => 'Lease Option',
'description' =>
'Buyer is allowed to lease the property with the option to purchase it later under pre-agreed terms.',
],
[
'name' => 'Lease Purchase',
'description' =>
'Buyer is allowed to lease the property now and commit to purchase it later, often with a portion of rent credited toward the purchase.',
],
[
'name' => 'Non-Fungible Token (NFT)',
'description' =>
'Buyer is allowed to use a verified digital asset as full or partial consideration, subject to Seller approval.',
],
[
'name' => 'No-Doc',
'description' =>
'Buyer is allowed to use a loan requiring little to no income documentation, often used by self-employed borrowers.',
],
[
'name' => 'Non-QM',
'description' =>
'Buyer is allowed to use a Non-Qualified Mortgage that allows alternative income verification methods.',
],
[
'name' => 'Seller Financing',
'description' => 'Seller is offering to finance all or part of the purchase price directly to the Buyer.',
],
[
'name' => 'USDA',
'description' =>
'Buyer is allowed to use a USDA loan for eligible rural properties and low-to-moderate income buyers.',
],
[
'name' => 'VA',
'description' =>
'Buyer is allowed to use a VA loan backed by the U.S. Department of Veterans Affairs, available to eligible veterans and active-duty service members.',
],
[
'name' => 'Other',
'description' => 'Buyer is allowed to use an alternative financing method not listed above.',
],
];
$financing_options = [
[
'name' => 'Assumable',
'description' =>
'Buyer is allowed to assume the Seller’s existing mortgage, subject to lender approval. This can be attractive if the current interest rate is lower than prevailing market rates.',
],
[
'name' => 'Cash',
'description' => 'Buyer is allowed to pay the full purchase price in cash, with no financing contingency.',
],
[
'name' => 'Conventional',
'description' => 'Buyer is allowed to use a traditional loan that meets standard underwriting guidelines.',
],
[
'name' => 'Cryptocurrency',
'description' =>
'Buyer is allowed to use digital currency (e.g., Bitcoin or Ethereum) as a form of payment, subject to Seller acceptance.',
],
[
'name' => 'Exchange/Trade',
'description' => 'Buyer is allowed to offer another asset as part of the purchase in a trade.',
],
[
'name' => 'FHA',
'description' =>
'Buyer is allowed to use a loan backed by the Federal Housing Administration, typically requiring the property to meet condition standards.',
],
[
'name' => 'Jumbo',
'description' =>
'Buyer is allowed to use a loan above conforming loan limits, often requiring stricter qualifications.',
],
[
'name' => 'Lease Option',
'description' =>
'Buyer is allowed to lease the property with the option to purchase it later under pre-agreed terms.',
],
[
'name' => 'Lease Purchase',
'description' =>
'Buyer is allowed to lease the property now and commit to purchase it later, often with a portion of rent credited toward the purchase.',
],
[
'name' => 'Non-Fungible Token (NFT)',
'description' =>
'Buyer is allowed to use a verified digital asset as full or partial consideration, subject to Seller approval.',
],
[
'name' => 'No-Doc',
'description' =>
'Buyer is allowed to use a loan requiring little to no income documentation, often used by self-employed borrowers.',
],
[
'name' => 'Non-QM',
'description' =>
'Buyer is allowed to use a Non-Qualified Mortgage that allows alternative income verification methods.',
],
[
'name' => 'Seller Financing',
'description' => 'Seller is offering to finance all or part of the purchase price directly to the Buyer.',
],
[
'name' => 'USDA',
'description' =>
'Buyer is allowed to use a USDA loan for eligible rural properties and low-to-moderate income buyers.',
],
[
'name' => 'VA',
'description' =>
'Buyer is allowed to use a VA loan backed by the U.S. Department of Veterans Affairs, available to eligible veterans and active-duty service members.',
],
[
'name' => 'Other',
'description' => 'Buyer is allowed to use an alternative financing method not listed above.',
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
