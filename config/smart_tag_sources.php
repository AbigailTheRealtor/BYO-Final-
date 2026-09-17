<?php

/*
|--------------------------------------------------------------------------
| Smart Tags — how each legitimate property source maps to canonical keys
|--------------------------------------------------------------------------
|
| READ ONLY THROUGH App\Support\SmartTags\SmartTagConfig. A test asserts it.
|
| Rules may only EMIT keys declared in config/smart_tags.php, and only in
| contexts the tag allows. They cannot define tags. Tests enforce every rule.
|
| SOURCES
|   bridge.rules         → structured_mls           (bridge_properties columns + raw_json)
|   native.<type>.rules  → structured_native_listing (Offer Listing meta)
|   description.rules    → mls_remarks               (Bridge PublicRemarks — NOT processed
|                                                     in production; the writer refuses it)
|                        → native_listing_description (the listing's public description)
|
| STRUCTURED RULE KINDS
|   boolean    field true → present; false → absent (when the tag is negatable)
|   equals     single value in `values` → present; in `negate_values` → absent
|   any        list contains any of `values` → present; else any `negate_values` → absent
|   prefix     list has a value starting with `prefix` → present
|   nonempty   list has any value other than `except` → present; only `negate_values` → absent
|   vocab      each list value is looked up in `vocabularies.<vocab>` → present per tag
|   number_gt  numeric value > `threshold` → present
|   flag       JSON object sub-key (`sub`) true → present
|
| `authoritative` => true marks a field that ANSWERS the tag outright (a Yes/No
| question). A tag answered that way cannot also be manually selected by the
| owner: they edit Property Details instead. Multi-select presence is never
| authoritative — a value missing from a checklist is unknown, not "no".
|
| Values compare after trimming, collapsing whitespace, reading en/em dashes as
| hyphens and case-folding (NativeMetaValueReader::normalizeOption).
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Shared value dictionaries
    |----------------------------------------------------------------------
    | Native option lists reuse RESO strings, so ONE dictionary maps both an
    | MLS field and the equivalent native field to the same key. Values not
    | listed emit nothing.
    */
    'vocabularies' => [

        'interior_features' => [
            'Open Floorplan'             => 'open_floor_plan',
            'Split Bedroom'              => 'split_floor_plan',
            'Vaulted Ceiling(s)'         => 'vaulted_ceilings',
            'Cathedral Ceiling(s)'       => 'vaulted_ceilings',
            'High Ceilings'              => 'high_ceilings',
            'Tray Ceiling(s)'            => 'tray_ceilings',
            'Crown Molding'              => 'crown_molding',
            'Skylight(s)'                => 'skylights',
            'Walk-In Closet(s)'          => 'walk_in_closet',
            'Primary Bedroom Main Floor' => 'primary_bedroom_main_floor',
            'Eat-in Kitchen'             => 'eat_in_kitchen',
            'Wet Bar'                    => 'wet_bar',
            'Fireplace'                  => 'fireplace',
            'Quartz Counters'            => 'quartz_countertops',
            'Granite Counters'           => 'granite_countertops',
            'Stone Counters'             => 'stone_countertops',
            'Solid Surface Counters'     => 'solid_surface_countertops',
        ],

        'appliances' => [
            'Range Gas'         => 'gas_range',
            'Wine Refrigerator' => 'wine_refrigerator',
        ],

        'flooring' => [
            'Wood'                => 'hardwood_flooring',
            'Hardwood'            => 'hardwood_flooring',
            'Engineered Hardwood' => 'hardwood_flooring',
            'Reclaimed Wood'      => 'hardwood_flooring',
            'Ceramic Tile'        => 'tile_flooring',
            'Porcelain Tile'      => 'tile_flooring',
            'Quarry Tile'         => 'tile_flooring',
            'Tile'                => 'tile_flooring',
            'Luxury Vinyl'        => 'luxury_vinyl_flooring',
            'Terrazzo'            => 'terrazzo_flooring',
        ],

        /*
        | The "Amenities and Property Features" option list, identical on the
        | Seller, Landlord, Buyer and Tenant forms. On listings it is a structured
        | source; on Buyer/Tenant criteria (a later phase) it seeds preferences.
        | Deliberately unmapped values are listed under amenity_exclusions.
        */
        'amenities' => [
            'Accessibility Features'  => 'accessible_features',
            'Handicap Accessibility'  => 'accessible_features',
            'Balcony/Patio'           => 'balcony_or_patio',
            'Carport'                 => 'carport',
            'Covered Carport'         => 'carport',
            'Central Air Conditioning' => 'central_air',
            'Clubhouse'               => 'clubhouse',
            'Conference Room'         => 'conference_room',
            'Elevator'                => 'elevator',
            'Fireplace'               => 'fireplace',
            'Fitness Center/Gym'      => 'fitness_center',
            'Garage'                  => 'garage',
            'Gated Community'         => 'gated_community',
            'Hardwood Floors'         => 'hardwood_flooring',
            'High-Speed Internet'     => 'high_speed_internet_ready',
            'In-Unit Laundry'         => 'in_unit_laundry',
            'Kitchenette/Break Room'  => 'kitchen_break_room',
            'Loading Dock'            => 'loading_dock',
            'On-site Laundry'         => 'on_site_laundry',
            'Pet Friendly'            => 'pets_allowed',
            'Playground'              => 'playground',
            'Reception Area'          => 'reception_area',
            'Security System'         => 'security_system',
            'Study/Den/Office'        => 'home_office',
            'Tile Floors'             => 'tile_flooring',
            'Updated Bathroom'        => 'updated_bathrooms',
            'Updated Kitchen'         => 'updated_kitchen',
            'Walk-in Closet'          => 'walk_in_closet',
            'Waterfront'              => 'waterfront',
        ],

        'community_amenities' => [
            // Bridge CommunityFeatures / AssociationAmenities
            'Clubhouse'                     => 'clubhouse',
            'Fitness Center'                => 'fitness_center',
            'Tennis Court(s)'               => 'tennis_court',
            'Pickleball Court(s)'           => 'pickleball_court',
            'Basketball Court'              => 'basketball_court',
            'Golf Course'                   => 'golf_course_community',
            'Dog Park'                      => 'dog_park',
            'Trail(s)'                      => 'walking_trails',
            'Gated Community - Guard'       => 'gated_community',
            'Gated Community - No Guard'    => 'gated_community',
            'Playground'                    => 'playground',
            'Pool'                          => 'community_pool',
            'Elevator(s)'                   => 'elevator',
            'Boat Slip'                     => 'boat_slip_marina',
            'Marina'                        => 'boat_slip_marina',
            // Native association_amenities
            'Fitness Center / Gym'          => 'fitness_center',
            'Tennis Court'                  => 'tennis_court',
            'Pickleball Court'              => 'pickleball_court',
            'Jogging / Walking Trail'       => 'walking_trails',
            'Gated Entry'                   => 'gated_community',
            'Boat Slip/Marina'              => 'boat_slip_marina',
        ],

        // Read by native.water_access (meta `water_access`) and bridge.water_access
        // (`STELLAR_WaterAccess`) — the same option vocabulary on both sides.
        //
        // NOT the water VIEW vocabulary. `STELLAR_WaterView` and the wizard's `water_view` field
        // carry their own strings ("Gulf/Ocean - Full", "Bay/Harbor - Partial", "Creek/Stream"),
        // and the live residential_lease fixture holds WaterAccess ['Beach','Gulf/Ocean'] beside
        // WaterView ['Beach','Gulf/Ocean - Full'] on one record. A view is not access, so a view
        // string must never be given an access tag here.
        'water_access' => [
            'Gulf/Ocean'            => 'gulf_or_ocean_access',
            'Gulf/Ocean to Bay'     => 'gulf_or_ocean_access',
            'Intracoastal Waterway' => 'intracoastal_access',
            'Canal - Freshwater'    => 'canal_frontage',
            'Canal - Saltwater'     => 'canal_frontage',
            'Lake'                  => 'lake_access',
            'Bay/Harbor'            => 'bay_or_harbor_access',
            'Bayou'                 => 'bayou_access',
            'Beach'                 => 'beach_access',
            'Creek'                 => 'creek_access',
            'Pond'                  => 'pond_access',
            'River'                 => 'river_access',
            // 'Other' is deliberately unmapped: the generic water_access tag still fires from
            // native.water_access.any, and inventing a body of water would be a false claim.
        ],

        'building_features' => [
            'Clear Span'        => 'clear_span',
            'Drive-Through'     => 'drive_through',
            'Elevator'          => 'elevator',
            'Fencing'           => 'fenced_lot',
            'Fiber Optic'       => 'fiber_internet',
            'Freight Elevator'  => 'freight_elevator',
            'Furnished'         => 'furnished',
            'High Bays'         => 'high_bays',
            'Kitchen Facility'  => 'kitchen_break_room',
            'Lit Sign on Site'  => 'lit_signage',
            'Loading Dock'      => 'loading_dock',
            'On Site Shower'    => 'on_site_shower',
            'Outside Storage'   => 'outside_storage',
            'Overhead Doors'    => 'overhead_doors',
            'Truck Doors'       => 'overhead_doors',
            'Truck Well'        => 'truck_well',
            'Reception'         => 'reception_area',
            'Waiting Room'      => 'reception_area',
            'Ramp'              => 'accessible_features',
        ],

        'parking_options' => [
            'RV Parking'                            => 'rv_parking',
            'Electric Vehicle Charging Station(s)'  => 'ev_charging',
            'Under Building'                        => 'covered_parking',
            'Underground'                           => 'covered_parking',
            'Covered'                               => 'covered_parking',
            'Secured'                               => 'secured_parking',
            'Circular Driveway'                     => 'circular_driveway',
        ],

        'condition' => [
            // Seller condition_prop
            'New Construction'                                          => 'new_construction',
            'Currently being built'                                     => 'under_construction',
            'Pre-Construction'                                          => 'under_construction',
            'No updates needed: Completely updated'                     => 'fully_updated',
            'Semi-updated: Needs minor updates'                         => 'partially_updated',
            'Not updated: Requires a complete update'                   => 'needs_complete_update',
            'Tear Down: Requires complete demolition and reconstruction' => 'teardown',
            // Landlord Create condition_prop (Edit uses the Seller wording above)
            'Updated / Renovated'                                       => 'fully_updated',
            'Partially Updated'                                         => 'partially_updated',
            'Older but Well Maintained'                                 => 'older_well_maintained',
        ],

        'bridge_condition' => [
            // Bridge PropertyCondition (RESO)
            'Under Construction' => 'under_construction',
            'To Be Built'        => 'under_construction',
        ],

        'owner_pays' => [
            'Water'            => 'water_included',
            'Electricity'      => 'electricity_included',
            'Internet'         => 'internet_included',
            'Cable TV'         => 'cable_included',
            'Trash Collection' => 'trash_included',
            'Grounds Care'     => 'lawn_care_included',
            'Pest Control'     => 'pest_control_included',
            'Pool Maintenance' => 'pool_maintenance_included',
        ],

        'special_conditions' => [
            // Bridge SpecialListingConditions (RESO)
            'Short Sale'        => 'short_sale',
            'Real Estate Owned' => 'reo_bank_owned',
            'Probate Listing'   => 'probate',
            'Auction'           => 'auction',
            'HUD Owned'         => 'government_owned',
            // Native sale_provision
            'Bank Owned/REO'    => 'reo_bank_owned',
            'Government Owned'  => 'government_owned',
        ],

        'financing' => [
            'Seller Financing' => 'seller_financing_available',
            'Assumable'        => 'assumable_loan',
            'Lease Option'     => 'lease_option_available',
            'Lease Purchase'   => 'lease_option_available',
        ],

        'sale_includes' => [
            'Liquor License'     => 'liquor_license_included',
            'Inventory'          => 'inventory_included',
            'Equipment/Fixtures' => 'equipment_fixtures_included',
            'Furniture'          => 'equipment_fixtures_included',
            'Goodwill'           => 'goodwill_included',
            'Training'           => 'training_included',
            'Furniture, Fixtures, and Equipment (as per attached inventory)' => 'equipment_fixtures_included',
        ],

        'licenses' => [
            'Liquor'    => 'liquor_license_included',
            'Beer/Wine' => 'beer_wine_license_included',
        ],

        'vegetation' => [
            'Cleared'          => 'cleared_land',
            'Pasture'          => 'pasture',
            'Trees/Wooded'     => 'wooded_land',
            'Partially Wooded' => 'wooded_land',
            'Timber'           => 'wooded_land',
            'Wooded'           => 'wooded_land',
        ],

        // Brick and Chip And Seal are real wizard options that mapped to NOTHING — unlike
        // water_access there is no `nonempty` fallback rule here, so they produced no tag at all.
        // Both are sealed, improved surfaces (a brick street is paved; chip seal is a bituminous
        // surface treatment over a prepared base), which is the line these two tags draw against
        // the loose aggregate of Dirt / Gravel / Limerock. 'Other' stays unmapped.
        'road_surface' => [
            'Paved'         => 'paved_road_access',
            'Asphalt'       => 'paved_road_access',
            'Concrete'      => 'paved_road_access',
            'Brick'         => 'paved_road_access',
            'Chip And Seal' => 'paved_road_access',
            'Dirt'          => 'unpaved_road_access',
            'Gravel'        => 'unpaved_road_access',
            'Unimproved'    => 'unpaved_road_access',
            'Limerock'      => 'unpaved_road_access',
        ],

        'road_frontage' => [
            'Highway'         => 'highway_frontage',
            'Divided Highway' => 'highway_frontage',
            'Interstate'      => 'highway_frontage',
        ],

        'water_source' => [
            'Public' => 'public_water',
            'Well'   => 'well_water',
        ],

        'sewer' => [
            'Public Sewer' => 'public_sewer',
            'Septic Tank'  => 'septic_system',
        ],

        'utilities' => [
            'Solar'                   => 'solar_power',
            'BB/HS Internet Capable'  => 'high_speed_internet_ready',
            'BB/HS Internet Available' => 'high_speed_internet_ready',
            'Fiber Optics'            => 'fiber_internet',
            'Electricity Available'   => 'electricity_available',
            'Electricity Connected'   => 'electricity_available',
        ],

        'electrical' => [
            '3 Phase'   => 'three_phase_power',
            'Generator' => 'backup_generator',
        ],

        'security_features' => [
            'Security System'          => 'security_system',
            'Security System Owned'    => 'security_system',
            'Security System Leased'   => 'security_system',
            'Fire Sprinkler System'    => 'fire_sprinkler_system',
            'Gated Community'          => 'gated_community',
            'Secured Garage/Parking'   => 'secured_parking',
        ],

        'laundry' => [
            'Inside'        => 'in_unit_laundry',
            'Laundry Room'  => 'in_unit_laundry',
            'Laundry Closet' => 'in_unit_laundry',
            'In Kitchen'    => 'in_unit_laundry',
            'Common Area'   => 'on_site_laundry',
        ],

        'patio_porch' => [
            'Screened'   => 'screened_lanai_porch',
            'Covered'    => 'covered_patio',
            'Patio'      => 'balcony_or_patio',
            'Open Patio' => 'balcony_or_patio',
        ],

        'exterior_features' => [
            'Balcony'         => 'balcony_or_patio',
            'Outdoor Kitchen' => 'outdoor_kitchen',
            'Outdoor Shower'  => 'outdoor_shower',
        ],

        'lot_features' => [
            'Oversized Lot'    => 'oversized_lot',
            'Corner Lot'       => 'corner_lot',
            'Cul-De-Sac'       => 'cul_de_sac',
            'Cleared'          => 'cleared_land',
            'Pasture'          => 'pasture',
            'Zoned for Horses' => 'zoned_for_horses',
        ],

        'other_structures' => [
            'Shed(s)'   => 'storage_shed',
            'Workshop'  => 'workshop',
            'Barn(s)'   => 'barn_or_stables',
        ],

        'additional_rooms' => [
            'Bonus Room'        => 'bonus_room',
            'Loft'              => 'loft',
            'Den/Library/Office' => 'home_office',
        ],

        'space_type' => [
            'Vanilla Shell' => 'vanilla_shell',
            'Gray Shell'    => 'gray_shell',
            'Grey Shell'    => 'gray_shell',
        ],
    ],

    /*
    | Amenity option strings that deliberately map to NO Smart Tag, and why.
    | A test asserts none of these appear in the amenities dictionary.
    */
    'amenity_exclusions' => [
        '55 and Over Community'           => '55+/62+ is a legal compliance gate (SeniorCommunityComplianceGate), never a tag.',
        'Specific School District'        => 'Location, and a steering risk. Location DNA, not a property tag.',
        'Access to Public Transportation' => 'Proximity. Location DNA.',
        'Proximity to Highways'           => 'Proximity. Location DNA.',
        'Visibility from Main Road'       => 'Location. Location DNA.',
        'HOA Community'                   => 'A structured search criterion (HOA), not a tag.',
        'Pool'                            => 'Ambiguous between a private and a community pool; mapped only via pool_type.',
    ],

    /*
    |----------------------------------------------------------------------
    | Bridge / MLS structured rules → structured_mls
    |----------------------------------------------------------------------
    | `column` reads a bridge_properties native column; `field` reads raw_json.
    | ListingTerms is deliberately NOT read: its display classification conflicts
    | between MlsFieldCatalog and Explore and is unresolved.
    */
    'bridge' => [
        'rules' => [
            ['id' => 'bridge.pool_private_yn',      'kind' => 'boolean', 'column' => 'pool_private_yn', 'tag' => 'private_pool'],
            ['id' => 'bridge.waterfront_yn',        'kind' => 'boolean', 'column' => 'waterfront_yn', 'tag' => 'waterfront'],
            ['id' => 'bridge.water_view_yn',        'kind' => 'boolean', 'column' => 'water_view_yn', 'tag' => 'water_view'],
            ['id' => 'bridge.garage_yn',            'kind' => 'boolean', 'column' => 'garage_yn', 'tag' => 'garage'],
            ['id' => 'bridge.new_construction_yn',  'kind' => 'boolean', 'column' => 'new_construction_yn', 'tag' => 'new_construction'],
            ['id' => 'bridge.spa_yn',               'kind' => 'boolean', 'field' => 'SpaYN', 'tag' => 'spa'],
            ['id' => 'bridge.fireplace_yn',         'kind' => 'boolean', 'field' => 'FireplaceYN', 'tag' => 'fireplace'],
            ['id' => 'bridge.carport_yn',           'kind' => 'boolean', 'field' => 'CarportYN', 'tag' => 'carport'],
            ['id' => 'bridge.water_access_yn',      'kind' => 'boolean', 'field' => 'STELLAR_WaterAccessYN', 'tag' => 'water_access'],
            ['id' => 'bridge.dock_yn',              'kind' => 'boolean', 'field' => 'STELLAR_DockYN', 'tag' => 'dock'],
            ['id' => 'bridge.building_elevator_yn', 'kind' => 'boolean', 'field' => 'STELLAR_BuildingElevatorYN', 'tag' => 'elevator'],
            ['id' => 'bridge.in_law_suite_yn',      'kind' => 'boolean', 'field' => 'STELLAR_InLawSuiteYN', 'tag' => 'guest_suite'],
            ['id' => 'bridge.freezer_space_yn',     'kind' => 'boolean', 'field' => 'STELLAR_FreezerSpaceYN', 'tag' => 'freezer_space'],
            ['id' => 'bridge.freestanding_yn',      'kind' => 'boolean', 'field' => 'STELLAR_FreestandingYN', 'tag' => 'freestanding_building'],
            ['id' => 'bridge.existing_lease_yn',    'kind' => 'boolean', 'field' => 'STELLAR_ExistLseTenantYN', 'tag' => 'existing_lease'],
            ['id' => 'bridge.bo_with_real_estate',  'kind' => 'boolean', 'field' => 'STELLAR_BusinessOpportunityWithRealEstateYN', 'tag' => 'sold_with_real_estate'],

            ['id' => 'bridge.pool_features.heated', 'kind' => 'any', 'field' => 'PoolFeatures', 'values' => ['Heated'], 'tag' => 'heated_pool'],
            ['id' => 'bridge.dock_lift_cap',        'kind' => 'number_gt', 'field' => 'STELLAR_DockLiftCap', 'threshold' => 0, 'tag' => 'boat_lift'],
            ['id' => 'bridge.water_extras.seawall', 'kind' => 'prefix', 'field' => 'STELLAR_WaterExtras', 'prefix' => 'Seawall', 'tag' => 'seawall'],
            ['id' => 'bridge.water_access',         'kind' => 'vocab', 'field' => 'STELLAR_WaterAccess', 'vocab' => 'water_access'],
            ['id' => 'bridge.waterfront_features.canal', 'kind' => 'prefix', 'field' => 'WaterfrontFeatures', 'prefix' => 'Canal', 'tag' => 'canal_frontage'],

            ['id' => 'bridge.interior_features',    'kind' => 'vocab', 'field' => 'InteriorFeatures', 'vocab' => 'interior_features'],
            ['id' => 'bridge.appliances',           'kind' => 'vocab', 'field' => 'Appliances', 'vocab' => 'appliances'],
            ['id' => 'bridge.flooring',             'kind' => 'vocab', 'field' => 'Flooring', 'vocab' => 'flooring'],
            ['id' => 'bridge.laundry_features',     'kind' => 'vocab', 'field' => 'LaundryFeatures', 'vocab' => 'laundry'],
            ['id' => 'bridge.additional_rooms',     'kind' => 'vocab', 'field' => 'STELLAR_AdditionalRooms', 'vocab' => 'additional_rooms'],
            ['id' => 'bridge.patio_porch',          'kind' => 'vocab', 'field' => 'PatioAndPorchFeatures', 'vocab' => 'patio_porch'],
            ['id' => 'bridge.exterior_features',    'kind' => 'vocab', 'field' => 'ExteriorFeatures', 'vocab' => 'exterior_features'],
            ['id' => 'bridge.lot_features',         'kind' => 'vocab', 'field' => 'LotFeatures', 'vocab' => 'lot_features'],
            ['id' => 'bridge.other_structures',     'kind' => 'vocab', 'field' => 'OtherStructures', 'vocab' => 'other_structures'],
            ['id' => 'bridge.vegetation.landscaping', 'kind' => 'any', 'field' => 'Vegetation', 'values' => ['Mature Landscaping'], 'tag' => 'mature_landscaping'],
            ['id' => 'bridge.vegetation',           'kind' => 'vocab', 'field' => 'Vegetation', 'vocab' => 'vegetation'],
            ['id' => 'bridge.horse_amenities.stables', 'kind' => 'any', 'field' => 'HorseAmenities', 'values' => ['Stable(s)', 'Barn'], 'tag' => 'barn_or_stables'],
            ['id' => 'bridge.fencing.residential',  'kind' => 'nonempty', 'field' => 'Fencing', 'except' => ['None'], 'tag' => 'fenced_yard'],
            ['id' => 'bridge.fencing.site',         'kind' => 'nonempty', 'field' => 'Fencing', 'except' => ['None'], 'tag' => 'fenced_lot'],

            ['id' => 'bridge.community_features',   'kind' => 'vocab', 'field' => 'CommunityFeatures', 'vocab' => 'community_amenities'],
            ['id' => 'bridge.association_amenities', 'kind' => 'vocab', 'field' => 'AssociationAmenities', 'vocab' => 'community_amenities'],

            ['id' => 'bridge.parking_features',     'kind' => 'vocab', 'field' => 'ParkingFeatures', 'vocab' => 'parking_options'],
            ['id' => 'bridge.parking_features.rv',  'kind' => 'prefix', 'field' => 'ParkingFeatures', 'prefix' => 'RV', 'tag' => 'rv_parking'],

            ['id' => 'bridge.cooling.central',      'kind' => 'any', 'field' => 'Cooling', 'values' => ['Central Air'], 'tag' => 'central_air'],
            ['id' => 'bridge.green_energy.solar',   'kind' => 'any', 'field' => 'GreenEnergyGeneration', 'values' => ['Solar'], 'tag' => 'solar_power'],
            ['id' => 'bridge.electric',             'kind' => 'vocab', 'field' => 'Electric', 'vocab' => 'electrical'],
            ['id' => 'bridge.security_features',    'kind' => 'vocab', 'field' => 'SecurityFeatures', 'vocab' => 'security_features'],
            ['id' => 'bridge.window_features.impact', 'kind' => 'prefix', 'field' => 'WindowFeatures', 'prefix' => 'Impact', 'tag' => 'impact_windows'],
            ['id' => 'bridge.utilities',            'kind' => 'vocab', 'field' => 'Utilities', 'vocab' => 'utilities'],
            ['id' => 'bridge.accessibility',        'kind' => 'nonempty', 'field' => 'AccessibilityFeatures', 'except' => ['None'], 'tag' => 'accessible_features'],

            ['id' => 'bridge.property_condition',   'kind' => 'vocab', 'field' => 'PropertyCondition', 'vocab' => 'bridge_condition'],

            ['id' => 'bridge.furnished',            'kind' => 'equals', 'field' => 'Furnished', 'values' => ['Furnished'], 'tag' => 'furnished'],
            ['id' => 'bridge.unfurnished',          'kind' => 'equals', 'field' => 'Furnished', 'values' => ['Unfurnished'], 'tag' => 'unfurnished'],
            ['id' => 'bridge.pets_allowed',         'kind' => 'any', 'field' => 'PetsAllowed',
                'values' => ['Yes', 'Cats OK', 'Dogs OK', 'Size Limit', 'Breed Restrictions', 'Number Limit'],
                'negate_values' => ['No'], 'tag' => 'pets_allowed', 'authoritative' => true],
            ['id' => 'bridge.owner_pays',           'kind' => 'vocab', 'field' => 'OwnerPays', 'vocab' => 'owner_pays'],

            ['id' => 'bridge.separate_electric_meters', 'kind' => 'number_gt', 'field' => 'NumberOfSeparateElectricMeters', 'threshold' => 1, 'tag' => 'separate_electric_meters'],
            ['id' => 'bridge.separate_water_meters',    'kind' => 'number_gt', 'field' => 'NumberOfSeparateWaterMeters', 'threshold' => 1, 'tag' => 'separate_water_meters'],

            ['id' => 'bridge.bays_dock_high',       'kind' => 'number_gt', 'field' => 'STELLAR_NumofBaysDockHigh', 'threshold' => 0, 'tag' => 'loading_dock'],
            ['id' => 'bridge.bays_grade_level',     'kind' => 'number_gt', 'field' => 'STELLAR_NumofBaysGradeLevel', 'threshold' => 0, 'tag' => 'overhead_doors'],
            ['id' => 'bridge.building_features',    'kind' => 'vocab', 'field' => 'BuildingFeatures', 'vocab' => 'building_features'],
            ['id' => 'bridge.offices',              'kind' => 'number_gt', 'field' => 'STELLAR_NumofOffices', 'threshold' => 0, 'tag' => 'private_offices'],
            ['id' => 'bridge.conference_rooms',     'kind' => 'number_gt', 'field' => 'STELLAR_NumofConferenceMeetingRooms', 'threshold' => 0, 'tag' => 'conference_room'],
            ['id' => 'bridge.space_type',           'kind' => 'vocab', 'field' => 'STELLAR_SpaceType', 'vocab' => 'space_type'],

            ['id' => 'bridge.road_surface',         'kind' => 'vocab', 'field' => 'RoadSurfaceType', 'vocab' => 'road_surface'],
            ['id' => 'bridge.road_frontage',        'kind' => 'vocab', 'field' => 'RoadFrontageType', 'vocab' => 'road_frontage'],
            ['id' => 'bridge.water_source',         'kind' => 'vocab', 'field' => 'WaterSource', 'vocab' => 'water_source'],
            ['id' => 'bridge.sewer',                'kind' => 'vocab', 'field' => 'Sewer', 'vocab' => 'sewer'],

            ['id' => 'bridge.occupant.tenant',      'kind' => 'equals', 'field' => 'OccupantType', 'values' => ['Tenant'], 'tag' => 'tenant_occupied'],
            ['id' => 'bridge.occupant.vacant',      'kind' => 'equals', 'field' => 'OccupantType', 'values' => ['Vacant'], 'tag' => 'vacant'],
            ['id' => 'bridge.special_conditions',   'kind' => 'vocab', 'field' => 'SpecialListingConditions', 'vocab' => 'special_conditions'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Native Offer Listing structured rules → structured_native_listing
    |----------------------------------------------------------------------
    | `field` is a meta key on the listing's *_agent_auction_metas table.
    | Shared rules apply to both roles; each role adds its own. A rule applies
    | only in contexts both its listing type and its tag allow.
    */
    'native' => [

        'property_type_field' => 'property_type',

        'shared_rules' => [
            ['id' => 'native.waterfront',             'kind' => 'boolean', 'field' => 'waterfront', 'tag' => 'waterfront'],
            ['id' => 'native.pool_needed.no',         'kind' => 'equals', 'field' => 'pool_needed', 'values' => [], 'negate_values' => ['No'], 'tag' => 'private_pool', 'authoritative' => true],
            ['id' => 'native.pool_type.private',      'kind' => 'flag', 'field' => 'pool_type', 'sub' => 'private', 'tag' => 'private_pool'],
            ['id' => 'native.pool_type.community',    'kind' => 'flag', 'field' => 'pool_type', 'sub' => 'community', 'tag' => 'community_pool'],
            ['id' => 'native.garage_needed',          'kind' => 'boolean', 'field' => 'garage_needed', 'tag' => 'garage'],
            ['id' => 'native.carport_needed',         'kind' => 'boolean', 'field' => 'carport_needed', 'tag' => 'carport'],
            ['id' => 'native.water_view',             'kind' => 'nonempty', 'field' => 'water_view', 'except' => [], 'tag' => 'water_view'],
            ['id' => 'native.water_access.any',       'kind' => 'nonempty', 'field' => 'water_access', 'except' => [], 'tag' => 'water_access'],
            ['id' => 'native.water_access',           'kind' => 'vocab', 'field' => 'water_access', 'vocab' => 'water_access'],
            ['id' => 'native.interior_features',      'kind' => 'vocab', 'field' => 'interior_features', 'vocab' => 'interior_features'],
            ['id' => 'native.appliances',             'kind' => 'vocab', 'field' => 'appliances', 'vocab' => 'appliances'],
            ['id' => 'native.amenities',              'kind' => 'vocab', 'field' => 'non_negotiable_amenities', 'vocab' => 'amenities'],
            ['id' => 'native.association_amenities',  'kind' => 'vocab', 'field' => 'association_amenities', 'vocab' => 'community_amenities'],
            ['id' => 'native.condition',              'kind' => 'vocab', 'field' => 'condition_prop', 'vocab' => 'condition'],
            ['id' => 'native.building_features',      'kind' => 'vocab', 'field' => 'building_features', 'vocab' => 'building_features'],
            ['id' => 'native.building_features.no_elevator', 'kind' => 'any', 'field' => 'building_features', 'values' => [], 'negate_values' => ['Elevator - None'], 'tag' => 'elevator', 'authoritative' => true],
            ['id' => 'native.air_conditioning.central', 'kind' => 'any', 'field' => 'air_conditioning', 'values' => ['Central Air'], 'tag' => 'central_air'],
            ['id' => 'native.electrical_service',     'kind' => 'vocab', 'field' => 'electrical_service', 'vocab' => 'electrical'],
            ['id' => 'native.parking_options',        'kind' => 'vocab', 'field' => 'garage_parking_spaces_option', 'vocab' => 'parking_options'],
            ['id' => 'native.road_surface',           'kind' => 'vocab', 'field' => 'road_surface_type', 'vocab' => 'road_surface'],
            ['id' => 'native.water',                  'kind' => 'vocab', 'field' => 'water', 'vocab' => 'water_source'],
            ['id' => 'native.sewer',                  'kind' => 'vocab', 'field' => 'sewer', 'vocab' => 'sewer'],
            ['id' => 'native.electric_meters',        'kind' => 'number_gt', 'field' => 'number_electric_meters', 'threshold' => 1, 'tag' => 'separate_electric_meters'],
            ['id' => 'native.water_meters',           'kind' => 'number_gt', 'field' => 'number_water_meters', 'threshold' => 1, 'tag' => 'separate_water_meters'],
        ],

        'seller_agent' => [
            'rules' => [
                ['id' => 'seller.utilities',              'kind' => 'vocab', 'field' => 'utilities', 'vocab' => 'utilities'],
                ['id' => 'seller.road_frontage',          'kind' => 'vocab', 'field' => 'road_frontage', 'vocab' => 'road_frontage'],
                ['id' => 'seller.real_estate_purchase',   'kind' => 'equals', 'field' => 'real_estate_purchase',
                    'values' => ['Real Estate Building and Business'], 'negate_values' => ['Business Only'],
                    'tag' => 'sold_with_real_estate', 'authoritative' => true],
                ['id' => 'seller.licenses',               'kind' => 'vocab', 'field' => 'licenses', 'vocab' => 'licenses'],
                ['id' => 'seller.sale_includes',          'kind' => 'vocab', 'field' => 'sale_includes', 'vocab' => 'sale_includes'],
                ['id' => 'seller.business_assets',        'kind' => 'vocab', 'field' => 'business_assets', 'vocab' => 'sale_includes'],
                ['id' => 'seller.vegetation',             'kind' => 'vocab', 'field' => 'vegetation', 'vocab' => 'vegetation'],
                ['id' => 'seller.fences',                 'kind' => 'nonempty', 'field' => 'fences', 'except' => ['None'], 'tag' => 'fenced_lot'],
                ['id' => 'seller.buildable',              'kind' => 'boolean', 'field' => 'buildable', 'tag' => 'buildable_lot'],
                ['id' => 'seller.electric_available',     'kind' => 'equals', 'field' => 'electric_available', 'values' => ['Yes'], 'tag' => 'electricity_available'],
                ['id' => 'seller.occupant.tenant',        'kind' => 'equals', 'field' => 'occupant_status', 'values' => ['Tenant'], 'tag' => 'tenant_occupied'],
                ['id' => 'seller.occupant.vacant',        'kind' => 'equals', 'field' => 'occupant_status', 'values' => ['Vacant'], 'tag' => 'vacant'],
                ['id' => 'seller.existing_lease',         'kind' => 'nonempty', 'field' => 'existing_lease_type',
                    'except' => ['No Existing Lease'], 'negate_values' => ['No Existing Lease'], 'tag' => 'existing_lease', 'authoritative' => true],
                ['id' => 'seller.sale_provision',         'kind' => 'vocab', 'field' => 'sale_provision', 'vocab' => 'special_conditions'],
                ['id' => 'seller.offered_financing',      'kind' => 'vocab', 'field' => 'offered_financing', 'vocab' => 'financing'],
            ],
        ],

        'landlord_agent' => [
            'rules' => [
                ['id' => 'landlord.property_utilities',   'kind' => 'vocab', 'field' => 'property_utilities', 'vocab' => 'utilities'],
                ['id' => 'landlord.laundry_features',     'kind' => 'vocab', 'field' => 'laundry_features', 'vocab' => 'laundry'],
                ['id' => 'landlord.floor_covering',       'kind' => 'vocab', 'field' => 'floor_covering', 'vocab' => 'flooring'],
                ['id' => 'landlord.security_features',    'kind' => 'vocab', 'field' => 'security_features', 'vocab' => 'security_features'],
                ['id' => 'landlord.furnished',            'kind' => 'equals', 'field' => 'tenant_require', 'values' => ['Furnished', 'Turnkey'], 'tag' => 'furnished'],
                ['id' => 'landlord.partially_furnished',  'kind' => 'equals', 'field' => 'tenant_require', 'values' => ['Partial'], 'tag' => 'partially_furnished'],
                ['id' => 'landlord.unfurnished',          'kind' => 'equals', 'field' => 'tenant_require', 'values' => ['Unfurnished'], 'tag' => 'unfurnished'],
                ['id' => 'landlord.pets',                 'kind' => 'boolean', 'field' => 'pets', 'tag' => 'pets_allowed'],
                ['id' => 'landlord.rent_includes',        'kind' => 'vocab', 'field' => 'rent_includes', 'vocab' => 'owner_pays'],
                ['id' => 'landlord.owner_pays',           'kind' => 'vocab', 'field' => 'owner_pays', 'vocab' => 'owner_pays'],
                ['id' => 'landlord.space_type',           'kind' => 'vocab', 'field' => 'space_type', 'vocab' => 'space_type'],
                ['id' => 'landlord.offices',              'kind' => 'number_gt', 'field' => 'number_of_offices', 'threshold' => 0, 'tag' => 'private_offices'],
                ['id' => 'landlord.conference_rooms',     'kind' => 'number_gt', 'field' => 'number_of_conference_rooms', 'threshold' => 0, 'tag' => 'conference_room'],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Native free text that must NEVER feed Smart Tags
    |----------------------------------------------------------------------
    | Screening, qualification, approval and provider prose, pet/breed text,
    | occupant and clientele descriptions, compatibility preferences, seeker
    | disclosures, private notes and every unrestricted "other" box. Tested
    | against every structured rule and the description field map.
    */
    'forbidden_source_keys' => [
        // Landlord applicant screening (LandlordScreeningPolicy)
        'criminal_background_requirement', 'custom_criminal_background_requirement',
        'eviction_history_requirement', 'custom_eviction_requirement',
        'bankruptcy_requirement', 'custom_bankruptcy_requirement',
        'credit_score_flexibility', 'min_credit_score', 'custom_credit_score_requirement',
        'pet_policy_requirement', 'income_verification_requirement',
        'income_qualification_method', 'custom_income_requirement', 'min_monthly_income_fixed', 'min_income_requirement',
        'smoking_policy_requirement', 'custom_smoking_policy_requirement',
        'reference_requirement', 'custom_reference_requirement',
        'preferred_move_in_timeframe', 'custom_preferred_move_in_timeframe',
        'number_of_occupants_allowed',
        // Retired Fair Housing keys
        'employment_requirement', 'custom_employment_requirement', 'employment_verification_requirement',
        'service_animal', 'support_animal', 'occupant_types', 'occupant_types_tenant', 'com_tenant_type',
        'risk_tolerance',
        // The retired Hire Agent tenant-type keys are deliberately NOT named here: they may not
        // appear in executing code at all (HireAgentFairHousingWordingTest), and they lived only
        // inside the compatibility_preferences blob, which is forbidden below.
        // Landlord provider prose other than the public description (LandlordProviderTextPolicy)
        'landlord_approval_conditions', 'pet_restrictions',
        // Ungoverned or unrelated free text
        'breed_restrictions', 'type_of_pets', 'weight_of_pets', 'number_of_pets',
        'parking_terms', 'neighboring_tenants', 'space_features', 'permitted_use_restrictions',
        'intended_business_use', 'zoning_allows', 'shared_amenities', 'commercial_approval_conditions',
        'personal_guarantee_requirement', 'association_approval_process', 'additional_lease_restrictions',
        'leasing_55_plus',
        'compatibility_preferences',
        'additional_details_broker', 'meeting_details_additional_details', 'nft_description',
        'legal_description', 'special_assessment_description', 'included_personal_property',
        'reason_for_sale', 'preferance_details',
        // Buyer/Tenant disclosures and deal breakers
        'accessibility_requirements', 'rental_purpose', 'screening_concerns', 'prior_eviction', 'prior_felony',
        'credit_score_range', 'monthly_income', 'number_occupant', 'buyer_deal_breakers', 'tenant_deal_breakers',
        'deal_breakers', 'buyer_must_have_features', 'buyer_nice_to_have',
    ],

    /*
    | Any meta key starting with one of these is unrestricted free text.
    */
    'forbidden_source_key_prefixes' => ['other_', 'custom_'],

    /*
    |----------------------------------------------------------------------
    | Listing descriptions → mls_remarks / native_listing_description
    |----------------------------------------------------------------------
    */
    'description' => [

        /*
        | The ONE public description field per native listing type, verified in
        | code (2026-09-15):
        |
        |   seller_agent   meta `additional_details` — "Property Description" tab,
        |                  every Seller property type; rendered publicly as the
        |                  "Property Description" card.
        |   landlord_agent meta `additional_details` — "Rental Description" tab, both
        |                  Landlord property types; saved and rendered through
        |                  LandlordProviderTextPolicy. It is read ONLY through
        |                  displayValue(): text that policy withholds from the
        |                  public page is never parsed.
        |
        | Bridge rows use raw_json PublicRemarks, which is licence-RESTRICTED and not
        | processed in production (see SmartTagEvidenceWriter).
        */
        'fields' => [
            'bridge'         => ['field' => 'PublicRemarks', 'gate' => null],
            'seller_agent'   => ['meta_key' => 'additional_details', 'gate' => null],
            'landlord_agent' => ['meta_key' => 'additional_details', 'gate' => 'landlord_provider_text'],
        ],

        /** Longer text is truncated before parsing. Bounds regex cost. */
        'max_length' => 12000,

        /** How many words before / after a match are inspected for cues. */
        'window_words' => 5,

        /** A match with one of these in the preceding window is negated. */
        'negation_before' => ['no', 'not', 'never', 'without', 'lacks', 'lacking', 'none', 'neither', 'nor',
            'isn\'t', 'isnt', 'aren\'t', 'arent', 'doesn\'t', 'doesnt', 'don\'t', 'dont', 'didn\'t', 'didnt',
            'wasn\'t', 'wasnt', 'non', 'minus', 'excluding', 'except'],

        /** A match followed (within the window) by one of these is negated. */
        'negation_after' => ['not included', 'not available', 'not allowed', 'not permitted', 'not provided',
            'excluded', 'removed', 'sold separately', 'not conveying', 'does not convey'],

        /** Positive-condition rules are suppressed after these (a need, not a fact). */
        'needs_cues' => ['needs', 'need', 'requires', 'require', 'could use', 'in need of', 'ready for',
            'awaiting', 'potential to', 'opportunity to', 'would benefit from', 'bring your'],

        /** Location-sensitive rules are suppressed near these (proximity is Location DNA). */
        'location_cues_before' => ['minutes', 'minute', 'mins', 'miles', 'mile', 'blocks', 'block', 'steps',
            'walking distance', 'walk to', 'close to', 'near', 'nearby', 'proximity to', 'short drive',
            'drive to', 'down the street from', 'around the corner from'],
        'location_cues_after' => ['nearby', 'down the street', 'around the corner', 'close by', 'minutes away',
            'within walking distance', 'just minutes'],

        /** Default confidence for a description rule. Condition rules are capped lower. */
        'default_confidence' => 65,

        /*
        | Phrases that must NEVER produce a condition tag on their own. Tested.
        */
        'vague_marketing_phrases' => [
            'make it your own', 'bring your vision', 'bring your ideas', 'blank canvas', 'endless possibilities',
            'great potential', 'priced to sell', 'motivated seller', 'opportunity knocks', 'won\'t last long',
            'dream home', 'charming', 'cozy', 'hidden gem', 'must see', 'pride of ownership',
        ],

        /*
        | Rules, keyed by canonical tag. `patterns` are PCRE fragments matched
        | case-insensitively on NORMALISED text, wrapped in word boundaries.
        | Optional: contexts (subset of the tag's), suppress_before / suppress_after
        | (fragments checked in the window), positive_condition, location_sensitive,
        | confidence.
        */
        'rules' => [

            // Kitchen
            'updated_kitchen' => [['patterns' => [
                '(?:updated|remodeled|remodelled|renovated|redone|upgraded|modernized|refreshed|new)\s+kitchen',
                'kitchen\s+(?:has\s+been\s+|was\s+|is\s+)?(?:fully\s+|completely\s+|recently\s+|beautifully\s+|newly\s+)?(?:updated|remodeled|remodelled|renovated|upgraded|redone)',
            ], 'positive_condition' => true, 'suppress_before' => ['original']]],
            'quartz_countertops'        => [['patterns' => ['quartz\s+(?:counter\s*tops?|counters?|surfaces?|tops?)']]],
            'granite_countertops'       => [['patterns' => ['granite\s+(?:counter\s*tops?|counters?|surfaces?|tops?)']]],
            'stone_countertops'         => [['patterns' => ['stone\s+(?:counter\s*tops?|counters?)']]],
            'butcher_block_countertops' => [['patterns' => ['butcher[\s-]?block(?:\s+(?:counter\s*tops?|counters?|island))?']]],
            'white_cabinets'            => [['patterns' => ['white\s+(?:shaker(?:[\s-]style)?\s+)?(?:cabinets?|cabinetry)']]],
            'shaker_cabinets'           => [['patterns' => ['shaker(?:[\s-]style)?\s+(?:cabinets?|cabinetry)']]],
            'kitchen_island'            => [['patterns' => ['(?:kitchen|center|centre|large|oversized|huge|quartz|granite|prep)\s+island', 'island\s+with\s+(?:seating|breakfast\s+bar)'], 'location_sensitive' => true]],
            'breakfast_bar'             => [['patterns' => ['breakfast\s+bar']]],
            'stainless_appliances'      => [['patterns' => ['stainless(?:[\s-]steel)?\s+(?:appliances?|appliance\s+package|refrigerator|range|dishwasher)']]],
            'gas_range'                 => [['patterns' => ['gas\s+(?:range|stove|cook\s*top|cooking)']]],
            'wine_refrigerator'         => [['patterns' => ['wine\s+(?:fridge|refrigerator|cooler|chiller)']]],
            'wet_bar'                   => [['patterns' => ['wet\s+bar']]],
            'eat_in_kitchen'            => [['patterns' => ['eat[\s-]in\s+kitchen']]],
            'walk_in_pantry'            => [['patterns' => ['walk[\s-]in\s+pantry']]],

            // Interior
            'open_floor_plan'  => [['patterns' => ['open\s+(?:floor\s*plan|floorplan|concept|layout)']]],
            'split_floor_plan' => [['patterns' => ['split\s+(?:bedroom\s+)?(?:floor\s*plan|floorplan|plan|layout|design)']]],
            'vaulted_ceilings' => [['patterns' => ['(?:vaulted|cathedral)\s+ceilings?']]],
            'high_ceilings'    => [['patterns' => ['high\s+ceilings?', '(?:9|10|11|12|14)\s*(?:ft|foot|feet|\x27)\.?\s+ceilings?']]],
            'tray_ceilings'    => [['patterns' => ['tray\s+ceilings?']]],
            'crown_molding'    => [['patterns' => ['crown\s+mou?ldings?']]],
            'skylights'        => [['patterns' => ['sky\s*lights?']]],
            'fireplace'        => [['patterns' => ['fire\s*places?'], 'suppress_before' => ['clubhouse', 'community', 'lobby'], 'location_sensitive' => true]],
            'updated_bathrooms' => [['patterns' => [
                '(?:updated|remodeled|remodelled|renovated|upgraded|redone|new)\s+(?:bath(?:room)?s|(?:primary|master|guest)\s+bath(?:room)?)',
                'bath(?:room)?s\s+(?:have\s+been\s+|were\s+)?(?:fully\s+|recently\s+|completely\s+|beautifully\s+)?(?:updated|remodeled|remodelled|renovated|upgraded)',
            ], 'positive_condition' => true]],
            'walk_in_closet' => [['patterns' => ['walk[\s-]in\s+closets?']]],
            'primary_bedroom_main_floor' => [['patterns' => [
                '(?:primary|master|owner\x27?s)\s+(?:bedroom|suite|retreat)\s+(?:is\s+)?(?:located\s+)?on\s+(?:the\s+)?(?:main|first|ground)\s+(?:floor|level)',
                '(?:main|first|ground)[\s-](?:floor|level)\s+(?:primary|master|owner\x27?s)\s+(?:bedroom|suite)',
            ]]],
            'home_office' => [['patterns' => ['home\s+office', 'dedicated\s+office', '(?:den|study)\s*/\s*office', 'office\s*/\s*(?:den|study)']]],
            'bonus_room'  => [['patterns' => ['bonus\s+room']]],
            'loft'        => [['patterns' => ['loft'], 'suppress_after' => ['style', 'like']]],
            'in_unit_laundry' => [['patterns' => ['in[\s-]unit\s+(?:laundry|washer)', '(?:inside|indoor|interior)\s+laundry', 'laundry\s+room'],
                'suppress_before' => ['community', 'common', 'shared', 'on-site', 'onsite', 'coin', 'building']]],
            'guest_suite' => [['patterns' => ['(?:guest|in[\s-]law|mother[\s-]in[\s-]law)\s+suite', 'separate\s+living\s+quarters', 'guest\s+(?:house|cottage)', 'casita']]],

            // Flooring
            'hardwood_flooring'     => [['patterns' => ['(?:hardwood|wood|engineered\s+hardwood)\s+(?:floors?|flooring)'], 'suppress_before' => ['look', 'vinyl', 'laminate', 'faux']]],
            'tile_flooring'         => [['patterns' => ['(?:tile|tiled|ceramic\s+tile|porcelain\s+tile|travertine)\s+(?:floors?|flooring)']]],
            'luxury_vinyl_flooring' => [['patterns' => ['luxury\s+vinyl(?:\s+(?:plank|tile|flooring|floors?))?', 'lvp', 'lvt', 'vinyl\s+plank(?:\s+(?:floors?|flooring))?']]],
            'terrazzo_flooring'     => [['patterns' => ['terrazzo(?:\s+(?:floors?|flooring))?']]],

            // Outdoor
            'screened_lanai_porch' => [['patterns' => ['screened(?:[\s-]in)?\s+(?:lanai|porch|patio|veranda)', 'screen(?:ed)?\s+enclosure', 'pool\s+cage']]],
            'covered_patio'        => [['patterns' => ['covered\s+(?:patio|lanai|porch|deck|veranda|terrace)']]],
            'balcony_or_patio'     => [['patterns' => ['(?:private\s+)?balcon(?:y|ies)', 'private\s+(?:patio|terrace)']]],
            'outdoor_kitchen'      => [['patterns' => ['(?:outdoor|summer)\s+kitchen']]],
            'outdoor_shower'       => [['patterns' => ['outdoor\s+shower']]],
            'fenced_yard'          => [['patterns' => ['fenced(?:[\s-]in)?\s+(?:back\s*yard|yard|rear\s+yard|front\s+yard)', 'fully\s+fenced', 'privacy\s+fenc(?:e|ed|ing)']]],
            'oversized_lot'        => [['patterns' => ['over[\s-]?sized\s+(?:lot|yard|parcel)', 'double\s+lot']]],
            'corner_lot'           => [['patterns' => ['corner\s+(?:lot|parcel|home\s*site)']]],
            'cul_de_sac'           => [['patterns' => ['cul[\s-]de[\s-]sac']]],
            'mature_landscaping'   => [['patterns' => ['mature\s+(?:landscaping|trees|oaks?|palms?|foliage)']]],
            'storage_shed'         => [['patterns' => ['(?:storage|garden|tool|detached|utility)\s+shed']]],
            'workshop'             => [['patterns' => ['work\s*shop'], 'location_sensitive' => true]],

            // Pool & Spa
            'private_pool' => [[
                'patterns' => [
                    '(?:private|in[\s-]?ground|heated|salt[\s-]?water|screened|sparkling|resurfaced)\s+(?:swimming\s+)?pool',
                    'pool\s+home',
                    'pool\s+(?:and|&)\s+spa',
                    '(?:your|its)\s+own\s+(?:private\s+)?pool',
                ],
                'suppress_before' => ['community', 'neighborhood', 'neighbourhood', 'shared', 'association', 'hoa', 'clubhouse',
                    'resort', 'complex', 'building', 'amenity', 'amenities', 'room for', 'space for', 'potential', 'possible', 'add a'],
                'suppress_after'  => ['sized', 'table', 'access', 'privileges'],
                'location_sensitive' => true,
            ]],
            'heated_pool' => [[
                'patterns' => ['heated\s+(?:swimming\s+)?pool', 'pool\s+(?:is\s+)?heated', 'pool\s+heater'],
                'suppress_before' => ['community', 'neighborhood', 'neighbourhood', 'shared', 'association', 'hoa', 'clubhouse', 'resort', 'complex', 'building'],
                'location_sensitive' => true,
            ]],
            'spa' => [[
                'patterns' => ['hot\s+tub', 'jacuzzi', '(?:private|in[\s-]?ground|heated|attached)\s+spa', 'pool\s+(?:and|&)\s+spa'],
                'suppress_before' => ['community', 'neighborhood', 'shared', 'association', 'clubhouse', 'resort'],
                'suppress_after'  => ['like', 'inspired', 'tub', 'bath', 'style'],
                'location_sensitive' => true,
            ]],
            'community_pool' => [['patterns' => ['(?:community|neighborhood|neighbourhood|shared|association|clubhouse|resort[\s-]style)\s+(?:swimming\s+)?pools?']]],

            // Water & Boating
            'waterfront' => [[
                'patterns' => ['water[\s-]?front(?:age)?', '(?:lake|ocean|gulf|river|bay|canal|intracoastal)[\s-]?front', 'on\s+the\s+water',
                    'directly\s+on\s+the\s+(?:lake|river|bay|gulf|ocean|canal|intracoastal)'],
                'suppress_after' => ['community', 'park', 'dining', 'restaurant', 'restaurants', 'district', 'views', 'view', 'access'],
                'location_sensitive' => true,
            ]],
            'water_view' => [['patterns' => ['(?:water|lake|ocean|gulf|bay|river|canal|intracoastal|pond)\s+views?', 'views?\s+of\s+the\s+(?:water|lake|ocean|gulf|bay|river|intracoastal)'], 'location_sensitive' => true]],
            'water_access' => [['patterns' => ['(?:deeded\s+)?(?:water|boat|beach|gulf|bay|lake|river)\s+access', 'direct\s+access\s+to\s+the\s+(?:gulf|bay|intracoastal|ocean|lake|river)'], 'location_sensitive' => true]],
            'gulf_or_ocean_access' => [['patterns' => ['(?:direct\s+)?(?:gulf|ocean)\s+access', 'access\s+to\s+the\s+(?:gulf|ocean)'], 'location_sensitive' => true]],
            'intracoastal_access'  => [['patterns' => ['intracoastal(?:\s+waterway)?\s+(?:access|frontage|front)', 'on\s+the\s+intracoastal'], 'location_sensitive' => true]],
            'canal_frontage'       => [['patterns' => ['canal[\s-]?front(?:age)?', '(?:on\s+an?|deep[\s-]?water|sailboat|saltwater|salt\s+water|freshwater)\s+canal', 'canal\s+(?:home|lot)'], 'location_sensitive' => true]],
            'lake_access'          => [['patterns' => ['lake\s+access', 'lake[\s-]?front', 'on\s+the\s+lake'], 'location_sensitive' => true]],
            'dock' => [[
                'patterns' => ['(?:private|boat|deeded|deep[\s-]?water|floating|covered)\s+docks?', 'docks?\s+(?:with|and|&)\s+(?:a\s+)?(?:boat\s+)?lift'],
                'suppress_before' => ['loading', 'community', 'marina', 'shared'],
                'suppress_after'  => ['high', 'height', 'doors', 'door', 'rights'],
                'location_sensitive' => true,
            ]],
            'boat_lift'        => [['patterns' => ['boat\s*lifts?']]],
            'seawall'          => [['patterns' => ['sea\s*walls?']]],
            'boat_slip_marina' => [['patterns' => ['boat\s+slips?', '(?:private|community|deeded)\s+marina'], 'location_sensitive' => true]],

            // Parking
            'garage' => [['patterns' => ['(?:\d|one|two|three|four|single|double|triple|tandem|attached|detached|private|oversized)[\s-]+(?:car\s+|bay\s+)?garage'],
                'suppress_after' => ['sale', 'condo', 'condominium']]],
            'carport'           => [['patterns' => ['car\s*ports?']]],
            'oversized_garage'  => [['patterns' => ['over[\s-]?sized\s+(?:(?:\d|two|three)[\s-]car\s+)?garage', 'extra[\s-](?:deep|wide|large)\s+garage']]],
            'rv_parking'        => [['patterns' => ['rv\s+(?:parking|pad|garage|hook[\s-]?ups?)', '(?:room|space)\s+for\s+(?:an?\s+)?rv', '(?:boat|rv)\s*(?:/|and|&)\s*(?:boat|rv)\s+parking']]],
            'boat_parking'      => [['patterns' => ['boat\s+parking', '(?:room|space)\s+for\s+(?:a\s+)?boat', '(?:boat|rv)\s*(?:/|and|&)\s*(?:boat|rv)\s+parking'], 'location_sensitive' => true]],
            'circular_driveway' => [['patterns' => ['circular\s+(?:drive|driveway)']]],
            'ev_charging'       => [['patterns' => ['(?:ev|electric\s+vehicle|tesla)\s+(?:charger|charging)']]],
            'covered_parking'   => [['patterns' => ['covered\s+parking', '(?:under[\s-]?building|underground)\s+parking']]],
            'secured_parking'   => [['patterns' => ['(?:gated|secured?)\s+parking']]],

            // Community
            'clubhouse'             => [['patterns' => ['club\s*house'], 'location_sensitive' => true]],
            'fitness_center'        => [['patterns' => ['(?:fitness|exercise|workout)\s+(?:center|centre|room|facility)', '(?:community|on[\s-]site)\s+gym'], 'location_sensitive' => true]],
            'tennis_court'          => [['patterns' => ['tennis\s+courts?'], 'location_sensitive' => true]],
            'pickleball_court'      => [['patterns' => ['pickle\s*ball(?:\s+courts?)?'], 'location_sensitive' => true]],
            'basketball_court'      => [['patterns' => ['basketball\s+courts?'], 'location_sensitive' => true]],
            'golf_course_community' => [['patterns' => ['golf\s+(?:course\s+)?community', '(?:on|overlooking)\s+the\s+golf\s+course', 'golf\s+course\s+(?:lot|views?|frontage|home)'], 'location_sensitive' => true]],
            'dog_park'              => [['patterns' => ['dog\s+park'], 'location_sensitive' => true]],
            'walking_trails'        => [['patterns' => ['(?:walking|nature|jogging|hiking|biking)\s+trails?'], 'location_sensitive' => true]],
            'gated_community'       => [['patterns' => ['gated\s+(?:community|neighborhood|neighbourhood|entry|entrance|subdivision)', 'guard[\s-]gated']]],
            'playground'            => [['patterns' => ['play\s*ground'], 'location_sensitive' => true]],

            // Systems
            'central_air'               => [['patterns' => ['central\s+(?:air|a\s*/\s*c|ac|hvac|heat\s+and\s+air)']]],
            'solar_power'               => [['patterns' => ['solar\s+(?:panels?|power|energy|system|array|electric)', '(?:owned|paid[\s-]off)\s+solar'], 'suppress_after' => ['water', 'screens', 'screen', 'lights', 'lighting']]],
            'backup_generator'          => [['patterns' => ['(?:whole[\s-]house|home|standby|backup|back[\s-]up)\s+generator', 'generac']]],
            'security_system'           => [['patterns' => ['(?:security|alarm)\s+system', 'monitored\s+alarm']]],
            'fire_sprinkler_system'     => [['patterns' => ['fire\s+sprinklers?(?:\s+system)?', 'sprinklered\s+building']]],
            'impact_windows'            => [['patterns' => ['impact[\s-](?:resistant\s+)?(?:windows?|glass|doors?)', 'hurricane[\s-](?:impact|rated)\s+(?:windows?|glass)']]],
            'high_speed_internet_ready' => [['patterns' => ['high[\s-]speed\s+internet', 'broadband', 'gigabit']]],
            'fiber_internet'            => [['patterns' => ['fiber(?:[\s-]optics?)?\s+(?:internet|connection|service)', 'fiber\s+optics?']]],

            // Condition
            'new_construction'   => [['patterns' => ['new\s+construction', 'newly\s+(?:built|constructed)', 'brand[\s-]new\s+(?:home|construction|build|residence|house)', 'never\s+(?:been\s+)?lived\s+in'], 'positive_condition' => true, 'confidence' => 70]],
            'under_construction' => [['patterns' => ['under\s+construction', 'pre[\s-]?construction', 'to\s+be\s+built', 'currently\s+being\s+built'], 'confidence' => 70]],
            'fully_updated' => [['patterns' => [
                '(?:fully|completely|totally|entirely)\s+(?:updated|renovated|remodeled|remodelled|upgraded|redone)',
                '(?:renovated|remodeled|remodelled|updated|upgraded)\s+(?:throughout|top\s+to\s+bottom|from\s+top\s+to\s+bottom)',
                'down\s+to\s+the\s+studs',
            ], 'positive_condition' => true, 'confidence' => 60]],
            'recently_renovated' => [['patterns' => ['(?:recently|newly|freshly)\s+(?:updated|renovated|remodeled|remodelled|upgraded)'], 'positive_condition' => true, 'confidence' => 60]],
            'partially_updated'  => [['patterns' => ['partially\s+(?:updated|renovated|remodeled|remodelled)'], 'confidence' => 60]],
            'move_in_ready'      => [['patterns' => ['move[\s-]in[\s-]ready', 'move[\s-]in\s+condition', 'move\s+right\s+in', 'ready\s+to\s+move\s+(?:right\s+)?in'], 'positive_condition' => true, 'confidence' => 60]],
            'turnkey_home' => [[
                'patterns' => ['turn[\s-]?key', 'nothing\s+to\s+do\s+but\s+move\s+in'],
                'contexts' => ['residential.sale'],
                'suppress_after' => ['business', 'restaurant', 'operation', 'opportunity', 'investment', 'rental', 'vacation', 'short-term', 'airbnb'],
                'positive_condition' => true, 'confidence' => 60,
            ]],
            'turnkey_business' => [[
                'patterns' => ['turn[\s-]?key', '(?:business|restaurant|operation)\s+is\s+turn[\s-]?key'],
                'contexts' => ['business.sale'],
                'confidence' => 60,
            ]],
            'fixer_upper'             => [['patterns' => ['fixer[\s-]?upper', '(?:a|this|true)\s+fixer'], 'confidence' => 70]],
            'handyman_special'        => [['patterns' => ['handy\s*man(?:\x27?s)?\s+special', 'bring\s+your\s+tools'], 'confidence' => 70]],
            'needs_tlc'               => [['patterns' => ['needs?\s+(?:some\s+|a\s+little\s+|a\s+bit\s+of\s+)?(?:tlc|tender\s+loving\s+care)'], 'confidence' => 70]],
            'renovation_opportunity'  => [['patterns' => ['renovation\s+opportunity', 'bring\s+your\s+contractor', '(?:needs?|in\s+need\s+of)\s+(?:renovation|remodeling|rehab)'], 'confidence' => 60]],
            'cosmetic_updates_needed' => [['patterns' => ['(?:needs?|could\s+use|in\s+need\s+of)\s+(?:some\s+)?cosmetic\s+(?:updates?|work|updating|touches)', 'cosmetic\s+(?:updates?|work)\s+needed', 'cosmetic\s+fixer'], 'confidence' => 60]],
            'needs_complete_update'   => [['patterns' => [
                '(?:needs?|requires?|in\s+need\s+of)\s+(?:an?\s+)?(?:complete|full|total|major|extensive|significant)\s+(?:renovation|remodel|rehab|update|repairs?|overhaul)',
                'major\s+repairs?\s+(?:needed|required)',
                'gut\s+rehab\s+(?:needed|required)',
            ], 'confidence' => 60]],
            'original_condition' => [['patterns' => ['original\s+condition', 'all[\s-]original', 'original\s+(?:kitchen|bath(?:room)?s?|cabinets|finishes)'], 'confidence' => 60]],
            'teardown'           => [['patterns' => ['tear[\s-]?down', 'value\s+(?:is\s+)?in\s+the\s+land', '(?:lot|land)\s+value\s+only'], 'suppress_after' => ['walls', 'wall'], 'confidence' => 60]],
            'investor_special'   => [['patterns' => ['investor\s+special'], 'confidence' => 70]],
            'value_add_opportunity' => [['patterns' => ['value[\s-]add(?:\s+(?:opportunity|potential|play))?'], 'confidence' => 60]],

            // Rental
            'furnished' => [[
                'patterns' => ['fully\s+furnished', '(?:sold|leased|rented|offered|comes|available)\s+furnished', 'furnished\s+(?:unit|condo|home|rental|apartment|office|suite)'],
                'suppress_before' => ['partially', 'partly', 'semi', 'optionally', 'can be'],
                'suppress_after'  => ['or unfurnished', 'optional'],
            ]],
            'partially_furnished' => [['patterns' => ['(?:partially|partly|semi)[\s-]furnished']]],
            'unfurnished'         => [['patterns' => ['unfurnished'], 'suppress_before' => ['or']]],
            'pets_allowed' => [['patterns' => [
                'pets?\s+(?:are\s+|is\s+)?(?:allowed|welcome|permitted|ok|okay|considered)',
                'pet[\s-]friendly',
                '(?:dogs?|cats?)\s+(?:are\s+)?(?:allowed|welcome|ok|okay|permitted)',
            ]]],
            'water_included'           => [['patterns' => ['water\s+(?:is\s+)?included', 'includes?\s+water', 'water\s+(?:and|&)\s+(?:sewer|trash)\s+(?:are\s+)?included']]],
            'electricity_included'     => [['patterns' => ['(?:electric(?:ity)?|power)\s+(?:is\s+)?included', 'includes?\s+electric(?:ity)?']]],
            'internet_included'        => [['patterns' => ['(?:internet|wi[\s-]?fi|wifi)\s+(?:is\s+)?included', 'includes?\s+(?:internet|wi[\s-]?fi|wifi)', 'free\s+(?:internet|wi[\s-]?fi|wifi)']]],
            'cable_included'           => [['patterns' => ['cable(?:\s+tv)?\s+(?:is\s+)?included', 'includes?\s+cable']]],
            'trash_included'           => [['patterns' => ['(?:trash|garbage)(?:\s+(?:service|pickup|collection))?\s+(?:is\s+)?included', 'includes?\s+(?:trash|garbage)']]],
            'lawn_care_included'       => [['patterns' => ['(?:lawn\s+(?:care|service|maintenance)|yard\s+(?:care|maintenance))\s+(?:is\s+)?included', 'includes?\s+(?:lawn\s+(?:care|service)|yard\s+maintenance)']]],
            'pest_control_included'    => [['patterns' => ['pest\s+control\s+(?:is\s+)?included', 'includes?\s+pest\s+control']]],
            'pool_maintenance_included' => [['patterns' => ['pool\s+(?:service|maintenance|care)\s+(?:is\s+)?included', 'includes?\s+pool\s+(?:service|maintenance|care)']]],

            // Income
            'separate_electric_meters' => [['patterns' => ['separate\s+electric(?:al)?\s+meters', '(?:separately|individually)\s+metered']]],
            'separate_water_meters'    => [['patterns' => ['separate\s+water\s+meters']]],
            'on_site_laundry'          => [['patterns' => ['(?:on[\s-]site|community|common|shared|coin[\s-](?:op|operated))\s+laundry']]],

            // Building & Loading
            'loading_dock'          => [['patterns' => ['loading\s+docks?', 'dock[\s-]high\s+(?:doors?|loading|bays?)', 'dock[\s-]height\s+(?:doors?|loading)']]],
            'overhead_doors'        => [['patterns' => ['overhead\s+(?:bay\s+)?doors?', 'roll[\s-]?up\s+doors?', 'grade[\s-]level\s+(?:doors?|loading|bays?)']]],
            'truck_well'            => [['patterns' => ['truck\s+wells?']]],
            'high_bays'             => [['patterns' => ['high[\s-]bays?']]],
            'clear_span'            => [['patterns' => ['clear[\s-]span']]],
            'freight_elevator'      => [['patterns' => ['freight\s+elevators?']]],
            'drive_through'         => [['patterns' => ['drive[\s-]?(?:through|thru)'], 'location_sensitive' => true]],
            'freezer_space'         => [['patterns' => ['walk[\s-]in\s+(?:freezers?|coolers?)', 'freezer\s+space', 'cold\s+storage']]],
            'freestanding_building' => [['patterns' => ['free[\s-]?standing(?:\s+(?:building|structure|retail|restaurant|office))?']]],

            // Build-Out
            'reception_area'     => [['patterns' => ['reception\s+(?:area|desk|room)', 'waiting\s+(?:room|area)']]],
            'private_offices'    => [['patterns' => ['private\s+offices?', '(?:\d+|two|three|four|five|six)\s+(?:private\s+)?offices']]],
            'conference_room'    => [['patterns' => ['(?:conference|meeting|board)\s*rooms?']]],
            'kitchen_break_room' => [['patterns' => ['break\s*rooms?', 'kitchenette', 'employee\s+kitchen']]],
            'on_site_shower'     => [['patterns' => ['shower\s+facilities', 'locker\s+rooms?']]],
            'vanilla_shell'      => [['patterns' => ['vanilla\s+(?:shell|box)']]],
            'gray_shell'         => [['patterns' => ['gr[ae]y\s+shell']]],

            // Site
            'fenced_lot'        => [['patterns' => ['fenced\s+(?:lot|property|land|acreage|parcel|storage\s+yard|yard)', '(?:fully|perimeter)\s+fenced', 'perimeter\s+fenc(?:e|ing)']]],
            'outside_storage'   => [['patterns' => ['(?:outside|outdoor)\s+storage', '(?:storage|lay[\s-]?down)\s+yard']]],
            'lit_signage'       => [['patterns' => ['(?:lit|lighted|illuminated|pylon|monument)\s+sign(?:age)?']]],
            'paved_road_access' => [['patterns' => ['paved\s+(?:road|street|access|frontage)']]],
            'unpaved_road_access' => [['patterns' => ['(?:unpaved|dirt|gravel|lime\s*rock)\s+(?:road|street)']]],
            'highway_frontage'  => [['patterns' => ['(?:highway|hwy|interstate|state\s+road)\s+frontage', 'fronting\s+(?:on\s+)?(?:highway|hwy|state\s+road)']]],
            'three_phase_power' => [['patterns' => ['(?:3|three)[\s-]phase(?:\s+(?:power|electric(?:al)?|service))?']]],
            'public_water'      => [['patterns' => ['(?:city|public|municipal|county)\s+water']]],
            'public_sewer'      => [['patterns' => ['(?:city|public|municipal|county)\s+sewer']]],
            'well_water'        => [['patterns' => ['(?:private\s+)?well\s+water', 'private\s+well']]],
            'septic_system'     => [['patterns' => ['septic(?:\s+(?:tank|system))?'], 'suppress_after' => ['needed', 'required']]],
            'electricity_available' => [['patterns' => ['(?:electric(?:ity)?|power)\s+(?:is\s+)?(?:available|on\s+site|at\s+the\s+(?:road|street|lot\s+line))']]],

            // Business
            'sold_with_real_estate' => [['patterns' => [
                '(?:real\s+estate|building|land|property)\s+(?:is\s+)?included',
                '(?:business|sale)\s+(?:includes|with)\s+(?:the\s+)?(?:real\s+estate|building|land|property)',
                'business\s+(?:and|&)\s+(?:real\s+estate|building|property)',
            ]]],
            'liquor_license_included'     => [['patterns' => ['(?:full\s+)?liquor\s+licen[cs]e', '4[\s-]?cop(?:\s+licen[cs]e)?']]],
            'beer_wine_license_included'  => [['patterns' => ['beer\s+(?:and|&|/)\s+wine\s+licen[cs]e', '2[\s-]?cop(?:\s+licen[cs]e)?']]],
            'inventory_included'          => [['patterns' => ['inventory\s+(?:is\s+)?included', 'includes?\s+(?:all\s+)?(?:the\s+)?inventory']]],
            'equipment_fixtures_included' => [['patterns' => ['ff\s*&\s*e', 'furniture,?\s+fixtures,?\s+(?:and|&)\s+equipment', 'equipment\s+(?:is\s+)?included', 'includes?\s+(?:all\s+)?(?:the\s+)?equipment']]],
            'training_included'           => [['patterns' => ['training\s+(?:is\s+)?(?:provided|included)', 'seller\s+(?:will|to)\s+(?:train|provide\s+training)']]],

            // Land
            'cleared_land'     => [['patterns' => ['(?:fully\s+)?cleared\s+(?:lot|land|acreage|parcel|site|property)', '(?:lot|land|property|parcel)\s+(?:is\s+)?(?:fully\s+)?cleared'], 'suppress_before' => ['partially', 'partly']]],
            'wooded_land'      => [['patterns' => ['(?:wooded|heavily\s+wooded|treed)\s+(?:lot|land|acreage|parcel|property)', 'mature\s+timber']]],
            'pasture'          => [['patterns' => ['pastures?', 'grazing\s+land']]],
            'zoned_for_horses' => [['patterns' => ['zoned\s+for\s+horses', 'horses\s+(?:are\s+)?(?:allowed|permitted)']]],
            'barn_or_stables'  => [['patterns' => ['(?:horse|pole|equipment|hay)\s+barn', 'barn', 'stables', 'horse\s+stalls?'], 'suppress_after' => ['door', 'doors', 'style']]],
            'buildable_lot'    => [['patterns' => ['buildable\s+(?:lot|land|parcel|site|acreage)']]],

            // Sale & Occupancy
            'tenant_occupied'  => [['patterns' => ['tenant[\s-]occupied', 'currently\s+(?:leased|rented|tenanted)', 'tenants?\s+in\s+place']]],
            'vacant'           => [['patterns' => ['currently\s+vacant', '(?:property|home|house|unit|building)\s+is\s+vacant', 'vacant\s+and\s+(?:ready|easy)']]],
            'existing_lease'   => [['patterns' => ['existing\s+lease', 'lease\s+in\s+place', 'leased\s+(?:through|until)']]],
            'short_sale'       => [['patterns' => ['short\s+sale']]],
            'reo_bank_owned'   => [['patterns' => ['bank[\s-]owned', 'lender[\s-]owned', 'reo']]],
            'probate'          => [['patterns' => ['probate(?:\s+(?:sale|listing|property))?']]],
            'government_owned' => [['patterns' => ['(?:hud|government)[\s-]owned']]],
            'seller_financing_available' => [['patterns' => ['(?:seller|owner)\s+financing', '(?:seller|owner)\s+(?:will|may|can)\s+(?:finance|hold\s+(?:a\s+)?(?:mortgage|note|paper))']]],
            'assumable_loan'   => [['patterns' => ['assumable(?:\s+(?:loan|mortgage|va\s+loan|fha\s+loan|financing))?']]],
            'lease_option_available' => [['patterns' => ['lease[\s-](?:option|purchase|to[\s-]own)', 'rent[\s-]to[\s-]own']]],
            'as_is' => [['patterns' => ['(?:sold|selling|being\s+sold|offered)\s+(?:in\s+)?["\x27]?as[\s-]is', 'as[\s-]is\s+(?:condition|sale|basis)', 'as[\s-]is,?\s+where[\s-]is']]],
        ],
    ],

    /*
    | Fixed confidence per structured kind. Stored for internal fidelity only;
    | never displayed.
    */
    'confidence' => [
        'structured_present' => 95,
        'structured_absent'  => 90,
        'structured_list'    => 90,
        'manual'             => 80,
    ],
];
