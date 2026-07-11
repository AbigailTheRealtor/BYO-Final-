<?php

namespace App\Http\Livewire\OfferListing\Concerns;

/**
 * BYO-H1 — Shared Seller Offer-Listing publish validation.
 *
 * Single source of truth for the full create/edit publish rules. Extracted
 * verbatim from SellerOfferListing::getConditionalRules()/getValidationMessages()
 * so create store() and edit update() enforce identical required fields.
 * Drafts stay intentionally lenient and never call these.
 */
trait SellerPublishValidation
{
    /**
     * Get conditional validation rules based on current selections
     */
    protected function getConditionalRules()
    {
        $rules = [
            'listing_title'  => 'required|string|max:255',
            'property_type'  => 'required|string',
            'state'          => 'nullable|string',
            'unit_address'   => 'nullable|string|max:100',
            'first_name'     => 'required|string',
            'last_name'      => 'required|string',
            'phone_number'   => 'required|string',
            'email'          => 'required|email',
            'current_status' => 'nullable|string',
        ];

        // ── MLS / Property Detail scalar fields ───────────────────────────────
        $rules['year_built']             = 'nullable|digits:4|integer|min:1800|max:' . date('Y');
        $rules['zoning']                 = 'nullable|string|max:255';
        $rules['front_footage']          = 'nullable|numeric|min:0';
        $rules['number_of_wells']        = 'nullable|integer|min:0';
        $rules['number_of_septics']      = 'nullable|integer|min:0';
        $rules['number_water_meters']    = 'nullable|integer|min:0';
        $rules['number_electric_meters'] = 'nullable|integer|min:0';

        // ── MLS / Property Detail multi-select array fields ───────────────────
        // Union of all valid options across all property types (Residential,
        // Income, Commercial, Business, Vacant Land) so any per-type selection
        // passes without requiring a per-property-type conditional.
        $rules['roof_type']    = 'nullable|array';
        $rules['roof_type.*']  = 'string|in:Built-Up,Cement,Concrete,Membrane,Metal,Roof Over,Shake,Shingle,Slate,Tile,Other';

        $rules['exterior_construction']    = 'nullable|array';
        $rules['exterior_construction.*']  = 'string|in:Asbestos,Block,Brick,Cedar,Cement Siding,Concrete,HardiPlank Type,ICFs (Insulated Concrete Forms),Log,Metal Frame,Metal Siding,SIP (Structurally Insulated Panel),Stone,Stucco,Tilt up Walls,Vinyl Siding,Wood Frame,Wood Frame (FSC),Wood Siding,Other';

        $rules['foundation']    = 'nullable|array';
        $rules['foundation.*']  = 'string|in:Basement,Block,Brick/Mortar,Concrete Perimeter,Crawlspace,Pillar/Post/Pier,Slab,Stem Wall,Stilt/On Piling,Other';

        // Union of Residential/Income/Business (20) + Commercial (22 — adds Central Building, Central Individual)
        $rules['heating_and_fuel']    = 'nullable|array';
        $rules['heating_and_fuel.*']  = 'string|in:Baseboard,Central,Central Building,Central Individual,Electric,Exhaust Fans,Gas,Heat Pump,Heat Recovery Unit,Natural Gas,Oil,Partial,Propane,Radiant Ceiling,Reverse Cycle,Solar,Space Heater,Wall Furnace,Wall Units / Window Unit,Zoned,None,Other';

        // Union of Residential (7) + Commercial (8 — adds A/C Office Only)
        $rules['air_conditioning']    = 'nullable|array';
        $rules['air_conditioning.*']  = 'string|in:A/C Office Only,Central Air,Humidity Control,Mini-Split Unit(s),Wall/Window Unit(s),Zoned,None,Other';

        $rules['water']    = 'nullable|array';
        $rules['water.*']  = 'string|in:Canal/Lake For Irrigation,Private,Public,Well,Well Required,None,Other';

        $rules['sewer']    = 'nullable|array';
        $rules['sewer.*']  = 'string|in:Aerobic Septic,PEP-Holding Tank,Private Sewer,Public Sewer,Septic Needed,Septic Tank,None,Other';

        // Union of Residential/Income (27) + Commercial Vacant Land (16) + Vacant Land (29)
        $rules['utilities']    = 'nullable|array';
        $rules['utilities.*']  = 'string|in:BB/HS Internet Available,BB/HS Internet Capable,Cable Available,Cable Connected,Electrical Nearby,Electricity Available,Electricity Connected,Emergency Power,Fiber Optics,Fire Hydrant,Mini Sewer,Natural Gas Available,Natural Gas Connected,Phone Available,Private,Propane,Public,Sewer Available,Sewer Connected,Sewer Nearby,Solar,Sprinkler Meter,Sprinkler Recycled,Sprinkler Well,Street Lights,Telephone Nearby,Underground Utilities,Utility Pole,Water - Multiple Meters,Water Available,Water Connected,Water Nearby,None,Other';

        $rules['road_frontage']    = 'nullable|array';
        $rules['road_frontage.*']  = 'string|in:Access Road,Alley,Business District,City Street,County Road,Divided Highway,Easement,Highway,Interchange,Interstate,Main Thoroughfare,Private Road,Rail,State Road,Turn Lanes,None,Other';

        $rules['road_surface_type']    = 'nullable|array';
        $rules['road_surface_type.*']  = 'string|in:Asphalt,Brick,Chip And Seal,Concrete,Dirt,Gravel,Limerock,Paved,Unimproved,Other';

        $rules['electrical_service']    = 'nullable|array';
        $rules['electrical_service.*']  = 'string|in:1 Phase (3-Wire),3 Phase,110 Volts,220 Volts,440 Volts,Separate Meter,None,Other';

        // #4: ceiling_height is a single-value <select> bound to a string prop, not a
        // multi-select. Validating it as an array made every Commercial listing that
        // picked a ceiling height fail submit. Rule matches the actual single-string UI.
        $rules['ceiling_height']    = 'nullable|string|in:Under 8 Feet,8-10 Feet,11-14 Feet,15-18 Feet,19-22 Feet,Over 22 Feet';

        $rules['building_features']    = 'nullable|array';
        $rules['building_features.*']  = 'string|in:Bathrooms,Clear Span,Columns,Common Lighting,Drive-Through,Dumpsters,Elevator,Elevator – None,Extra Storage,Fencing,Fiber Optic,Freight Elevator,Furnished,High Bays,Janitorial Services,Kitchen Facility,Lit Sign on Site,Loading Dock,Loft,Medical Disposal,On Site Shower,Outside Storage,Overhead Doors,Pool/Spa,Ramp,Reception,Seating,Service Stations,Solid Surface Counter,Stone Counter,Trash Removal,Truck Doors,Truck Well,Waiting Room,Other';

        $rules['licenses']    = 'nullable|array';
        $rules['licenses.*']  = 'string|in:Beer/Wine,Liquor,Off Site,On Site,None,Other';

        $rules['sale_includes']    = 'nullable|array';
        $rules['sale_includes.*']  = 'string|in:Business,Equipment/Fixtures,Furniture,Goodwill,Inventory,Land,Lease Agreement,Liquor License,Parking Lot,Signage,Training,Other';

        $rules['current_use']    = 'nullable|array';
        $rules['current_use.*']  = 'string|in:Agricultural,Commercial,Industrial,Recreational,Residential,Timber,Other';

        $rules['current_adjacent_use']    = 'nullable|array';
        $rules['current_adjacent_use.*']  = 'string|in:Church,Commercial,Industrial,Mobile Home Park,Multi-Family,Park,Professional Office,Residential,Retail,School,Vacant,Other';

        $rules['fences']    = 'nullable|array';
        $rules['fences.*']  = 'string|in:Board,Chain Link,Cross Fenced,Fenced,Split Rail,Vinyl,Wire,Wood,None,Other';

        $rules['vegetation']    = 'nullable|array';
        $rules['vegetation.*']  = 'string|in:Brush,Cleared,Crop,Oak Trees,Partially Wooded,Pasture,Timber,Trees/Wooded,None,Other';

        $rules['easements']    = 'nullable|array';
        $rules['easements.*']  = 'string|in:Access Road,Drainage,Electric,Telephone,Utilities,Water,None,Other';
        // ─────────────────────────────────────────────────────────────────────

        // Bidding Period fields - only validate if listing type is Bidding Period
        if ($this->auction_type === 'Bidding Period') {
            $rules['auction_time'] = 'required|string';
        }

        // Seller leasing fields - only validate if interested in leasing
        if ($this->interested_purchase_fee_type === 'Yes') {
            $rules['seller_leasing_fee_type'] = 'required|string';
            
            // Validate specific fields based on leasing fee type
            if ($this->seller_leasing_fee_type === 'Flat Fee') {
                $rules['seller_leasing_gross_flat'] = 'required|numeric|min:0';
            } elseif ($this->seller_leasing_fee_type === 'Percentage of the Gross Lease Value') {
                $rules['seller_leasing_gross'] = 'required|numeric|min:0|max:100';
            } elseif ($this->seller_leasing_fee_type === 'Percentage of the Rent Due Each Rental Period') {
                $rules['seller_leasing_gross_rental'] = 'required|numeric|min:0|max:100';
            } elseif ($this->seller_leasing_fee_type === 'other') {
                $rules['seller_leasing_gross_other'] = 'required|string';
            }
        }

        // Commission structure validation
        if (in_array($this->commission_structure, [
            'Seller\'s Broker to Compensate Buyer\'s Broker from Seller\'s Broker Commission',
            'Seller to Pay Buyer\'s Broker Separately'
        ])) {
            $rules['commission_structure_type'] = 'required|string';
            
            if ($this->commission_structure_type === 'Flat Fee') {
                $rules['commission_structure_type_fee_flat'] = 'required|string';
            } elseif ($this->commission_structure_type === 'Percentage of the Total Purchase Price') {
                $rules['commission_structure_type_fee_percentage'] = 'required|numeric|min:0|max:100';
            } elseif ($this->commission_structure_type === 'other') {
                $rules['commission_structure_type_fee_other'] = 'required|string';
            }
        }

        // Purchase fee validation (using actual option values: flat, percentage, combo, other)
        if (!empty($this->purchase_fee_type)) {
            if ($this->purchase_fee_type === 'flat') {
                $rules['purchase_fee_flat'] = 'required|string';
            } elseif ($this->purchase_fee_type === 'percentage') {
                $rules['purchase_fee_percentage'] = 'required|numeric|min:0|max:100';
            } elseif ($this->purchase_fee_type === 'combo') {
                $rules['purchase_fee_percentage_combo'] = 'required|numeric|min:0|max:100';
                $rules['purchase_fee_flat_combo'] = 'required|string';
            } elseif ($this->purchase_fee_type === 'other') {
                $rules['purchase_fee_other'] = 'required|string';
            }
        }

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    protected function getValidationMessages()
    {
        return [
            'listing_title.required'   => 'Listing Title is required',
            'property_type.required'   => 'Property Type is required',
            'first_name.required'      => 'First Name is required',
            'last_name.required'       => 'Last Name is required',
            'phone_number.required'    => 'Phone Number is required',
            'email.required'           => 'Email Address is required',
            'email.email'              => 'Please enter a valid email address',
            'auction_time.required' => 'Bidding Period Length is required for Bidding Period listings',
            'seller_leasing_fee_type.required' => 'Seller\'s Broker Leasing Fee type is required when offering leasing',
            'seller_leasing_gross_flat.required' => 'Flat Fee amount is required',
            'seller_leasing_gross.required' => 'Percentage of Gross Lease Value is required',
            'seller_leasing_gross_other.required' => 'Custom leasing fee structure is required',
            'commission_structure_type.required' => 'Buyer\'s Broker Commission Fee type is required',
            'commission_structure_type_fee_flat.required' => 'Commission flat fee amount is required',
            'commission_structure_type_fee_percentage.required' => 'Commission percentage is required',
            'commission_structure_type_fee_other.required' => 'Custom commission structure is required',
            'purchase_fee_flat.required' => 'Seller\'s Broker Purchase Fee (flat fee) is required',
            'purchase_fee_percentage.required' => 'Seller\'s Broker Purchase Fee (percentage) is required',
            'purchase_fee_percentage_combo.required' => 'Seller\'s Broker Purchase Fee (percentage) is required',
            'purchase_fee_flat_combo.required' => 'Seller\'s Broker Purchase Fee (flat fee) is required',
            'purchase_fee_other.required' => 'Custom purchase fee structure is required',
        ];
    }
}
