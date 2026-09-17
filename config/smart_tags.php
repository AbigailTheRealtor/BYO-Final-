<?php

/*
|--------------------------------------------------------------------------
| Smart Tags — the ONE canonical property-characteristic taxonomy
|--------------------------------------------------------------------------
|
| Shared by Bridge MLS rows, native Seller/Landlord Offer Listings and (later)
| Buyer/Tenant preferences. There is no second vocabulary anywhere: a tag key
| means the same thing whichever source attached it.
|
| READ ONLY THROUGH App\Support\SmartTags\SmartTagConfig. A test asserts it.
|
| GOVERNANCE — read docs/smart-tags/SMART_TAGS_GOVERNANCE.md before editing.
|
|   • Keys are permanent. Retire a tag with 'status' => 'retired'; never reuse
|     or rename a key, because stored evidence and preferences reference it.
|   • ONE KEY, ONE MEANING. A phrase that means different things in different
|     property types becomes separate keys with disjoint contexts
|     (turnkey_home / turnkey_business, fenced_yard / fenced_lot).
|   • Tags describe the PROPERTY. Never people, demographics, protected
|     classes, neighbourhood quality or proximity. SmartTagComplianceGuard scans
|     every key, label and description; the guard lives in code so this file
|     cannot relax it.
|   • Proximity (schools, transit, highways, marinas) belongs to Location DNA.
|     Ranges and terms (price, beds, acreage, zoning, lease type, business type,
|     unit counts, ceiling-height bands) remain structured search criteria.
|   • 55+/62+ is a legal compliance gate, never a tag.
|
| FIELDS
|   contexts          where the tag applies (see SmartTagContext)
|   mls_derivable     a Bridge structured or remarks rule exists (test-enforced)
|   native_derivable  a native structured or description rule exists (test-enforced)
|   owner_selectable  a Seller/Landlord may select it manually
|   seeker_selectable a Buyer/Tenant may select it as a preference (later phase)
|   public_display    may be shown publicly (per-source gates still apply)
|   negatable         a structured source may record "confirmed not present"
|   compliance        status: approved | restricted | pending_review
|                     restricted     = approved with surface limits (see note)
|                     pending_review = derivable as evidence, but not
|                                      selectable and not publicly displayed
|                     notice         = fixed copy that must accompany display
|
*/

$order = 0;

$tag = static function (string $label, string $category, array $contexts, array $opts = []) use (&$order): array {
    $order += 10;

    return array_merge([
        'label'             => $label,
        'category'          => $category,
        'description'       => '',
        'status'            => 'active',
        'contexts'          => $contexts,
        'mls_derivable'     => false,
        'native_derivable'  => false,
        'owner_selectable'  => true,
        'seeker_selectable' => true,
        'public_display'    => true,
        'negatable'         => false,
        'compliance'        => ['status' => 'approved', 'note' => null, 'notice' => null],
        'display_order'     => $order,
    ], $opts);
};

// Context sets (readability only — the stored values are the strings).
$RS = 'residential.sale';
$RL = 'residential.lease';
$IS = 'income.sale';
$CS = 'commercial.sale';
$CL = 'commercial.lease';
$BS = 'business.sale';
$LS = 'land.sale';

$RES      = [$RS, $RL, $IS];
$RES_SALE = [$RS, $IS];
$COM      = [$CS, $CL, $BS];
$NONLAND  = [$RS, $RL, $IS, $CS, $CL, $BS];
$ALL      = [$RS, $RL, $IS, $CS, $CL, $BS, $LS];
$SITE     = [$IS, $CS, $CL, $BS, $LS];
$LEASE    = [$RL, $CL];
$SALE     = [$RS, $IS, $CS, $BS, $LS];

$both   = ['mls_derivable' => true, 'native_derivable' => true];
$native = ['native_derivable' => true];

return [

    'version' => '2026-09-17.1',

    'categories' => [
        'kitchen'        => ['label' => 'Kitchen',                    'display_order' => 10],
        'interior'       => ['label' => 'Interior & Layout',          'display_order' => 20],
        'flooring'       => ['label' => 'Flooring',                   'display_order' => 30],
        'outdoor'        => ['label' => 'Outdoor Living & Yard',      'display_order' => 40],
        'pool_spa'       => ['label' => 'Pool & Spa',                 'display_order' => 50],
        'water'          => ['label' => 'Water & Boating',            'display_order' => 60],
        'parking'        => ['label' => 'Garage & Parking',           'display_order' => 70],
        'community'      => ['label' => 'Community & Association',    'display_order' => 80],
        'systems'        => ['label' => 'Systems & Security',         'display_order' => 90],
        'condition'      => ['label' => 'Home Condition',             'display_order' => 100],
        'rental'         => ['label' => 'Rental Features',            'display_order' => 110],
        'income'         => ['label' => 'Income Property',            'display_order' => 120],
        'building'       => ['label' => 'Building & Loading',         'display_order' => 130],
        'buildout'       => ['label' => 'Commercial Build-Out',       'display_order' => 140],
        'site'           => ['label' => 'Site, Access & Utilities',   'display_order' => 150],
        'business'       => ['label' => 'Business Sale',              'display_order' => 160],
        'land'           => ['label' => 'Land Features',              'display_order' => 170],
        'sale_occupancy' => ['label' => 'Sale & Occupancy',           'display_order' => 180],
    ],

    /*
    | Conflict sets. Two PRESENT tags that conflict cannot both stand: the one
    | backed by the stronger source survives; at equal strength neither does
    | (both become unknown). Absent rows never conflict.
    |
    |   mutually_exclusive: every member conflicts with every other member.
    |   opposed:            every tag in `a` conflicts with every tag in `b`.
    */
    'conflicts' => [
        'mutually_exclusive' => [
            'furnishing'  => ['furnished', 'partially_furnished', 'unfurnished'],
            'occupancy'   => ['tenant_occupied', 'vacant'],
        ],
        'opposed' => [
            'condition_ready_vs_needs_work' => [
                'a' => ['new_construction', 'fully_updated', 'recently_renovated', 'move_in_ready', 'turnkey_home'],
                'b' => ['fixer_upper', 'handyman_special', 'needs_tlc', 'needs_complete_update', 'teardown',
                        'cosmetic_updates_needed', 'renovation_opportunity', 'original_condition', 'investor_special'],
            ],
            'partial_vs_complete' => [
                'a' => ['partially_updated'],
                'b' => ['fully_updated', 'new_construction', 'needs_complete_update', 'teardown', 'original_condition'],
            ],
            'under_construction_vs_existing' => [
                'a' => ['under_construction'],
                'b' => ['move_in_ready', 'turnkey_home', 'recently_renovated', 'original_condition', 'teardown',
                        'older_well_maintained', 'tenant_occupied'],
            ],
            'older_vs_new' => [
                'a' => ['older_well_maintained'],
                'b' => ['new_construction'],
            ],
        ],
    ],

    'tags' => [

        // ── Kitchen ─────────────────────────────────────────────────────────
        'updated_kitchen'           => $tag('Updated Kitchen', 'kitchen', $RES, $both + ['description' => 'Kitchen described or recorded as updated, remodeled or renovated.']),
        'quartz_countertops'        => $tag('Quartz Countertops', 'kitchen', $RES, $both),
        'granite_countertops'       => $tag('Granite Countertops', 'kitchen', $RES, $both),
        'stone_countertops'         => $tag('Stone Countertops', 'kitchen', $RES, $both + ['description' => 'Stone counters where the specific stone is not stated.']),
        'solid_surface_countertops' => $tag('Solid Surface Countertops', 'kitchen', $RES, $both + ['description' => 'Structured fields only; MLS import can carry the RESO value into a native listing.']),
        'butcher_block_countertops' => $tag('Butcher-Block Countertops', 'kitchen', $RES, $both),
        'white_cabinets'            => $tag('White Cabinets', 'kitchen', $RES, $both),
        'shaker_cabinets'           => $tag('Shaker Cabinets', 'kitchen', $RES, $both),
        'kitchen_island'            => $tag('Kitchen Island', 'kitchen', $RES, $both),
        'breakfast_bar'             => $tag('Breakfast Bar', 'kitchen', $RES, $both),
        'stainless_appliances'      => $tag('Stainless Steel Appliances', 'kitchen', $RES, $both),
        'gas_range'                 => $tag('Gas Range', 'kitchen', $RES, $both),
        'wine_refrigerator'         => $tag('Wine Refrigerator', 'kitchen', $RES, $both),
        'wet_bar'                   => $tag('Wet Bar', 'kitchen', $RES, $both),
        'eat_in_kitchen'            => $tag('Eat-In Kitchen', 'kitchen', $RES, $both),
        'walk_in_pantry'            => $tag('Walk-In Pantry', 'kitchen', $RES, $both),

        // ── Interior & Layout ───────────────────────────────────────────────
        'open_floor_plan'            => $tag('Open Floor Plan', 'interior', $RES, $both),
        'split_floor_plan'           => $tag('Split Floor Plan', 'interior', $RES, $both),
        'vaulted_ceilings'           => $tag('Vaulted Ceilings', 'interior', $RES, $both),
        'high_ceilings'              => $tag('High Ceilings', 'interior', $RES, $both),
        'tray_ceilings'              => $tag('Tray Ceilings', 'interior', $RES, $both),
        'crown_molding'              => $tag('Crown Molding', 'interior', $RES, $both),
        'skylights'                  => $tag('Skylights', 'interior', $RES, $both),
        // Added 2026-09-16 for the Listing Preference foundation.
        //
        // APPROVED AS CANONICAL, AND NON-DERIVABLE. Interior daylight is claimed
        // in prose far more often than it is recorded in a structured field, so
        // until a source rule is separately reviewed there is to be:
        //   • NO phrase rule,
        //   • NO photo / vision inference,
        //   • NO marketing-copy inference.
        // Any of those written before the evidence is reviewed would manufacture
        // confident tags out of sales language.
        //
        // Owner- and seeker-selectable now; derivation may only be enabled later
        // by a separately reviewed rule in config/smart_tag_sources.php. Both
        // derivable flags stay false until one exists — SmartTagSourceRulesTest
        // asserts the flags and the rules agree in both directions, so they can
        // only flip in the same change that adds the rule.
        'natural_light'              => $tag('Natural Light', 'interior', $RES, [
            'description' => 'Interior daylight described or recorded as abundant.',
        ]),
        'fireplace'                  => $tag('Fireplace', 'interior', $RES, $both + ['negatable' => true]),
        'updated_bathrooms'          => $tag('Updated Bathrooms', 'interior', $RES, $both),
        'walk_in_closet'             => $tag('Walk-In Closet', 'interior', $RES, $both),
        'primary_bedroom_main_floor' => $tag('Primary Bedroom on Main Floor', 'interior', $RES, $both),
        'home_office'                => $tag('Home Office', 'interior', $RES, $both),
        'bonus_room'                 => $tag('Bonus Room', 'interior', $RES, $both),
        'loft'                       => $tag('Loft', 'interior', $RES, $both),
        'in_unit_laundry'            => $tag('In-Unit Laundry', 'interior', $RES, $both),
        'elevator'                   => $tag('Elevator', 'interior', $NONLAND, $both + ['negatable' => true]),
        'guest_suite'                => $tag('Guest Suite / Separate Living Quarters', 'interior', $RES, $both + [
            'negatable'  => true,
            'compliance' => ['status' => 'pending_review', 'note' => 'Label replaces in-law / multigenerational wording; confirm before display or selection.', 'notice' => null],
        ]),

        // ── Flooring ────────────────────────────────────────────────────────
        'hardwood_flooring'     => $tag('Hardwood Flooring', 'flooring', $RES, $both),
        'tile_flooring'         => $tag('Tile Flooring', 'flooring', $RES, $both),
        'luxury_vinyl_flooring' => $tag('Luxury Vinyl Flooring', 'flooring', $RES, $both),
        'terrazzo_flooring'     => $tag('Terrazzo Flooring', 'flooring', $RES, $both),

        // ── Outdoor Living & Yard ───────────────────────────────────────────
        'screened_lanai_porch' => $tag('Screened Lanai / Porch', 'outdoor', $RES, $both),
        'covered_patio'        => $tag('Covered Patio', 'outdoor', $RES, $both),
        'balcony_or_patio'     => $tag('Balcony / Patio', 'outdoor', $RES, $both),
        'outdoor_kitchen'      => $tag('Outdoor Kitchen', 'outdoor', $RES, $both),
        'outdoor_shower'       => $tag('Outdoor Shower', 'outdoor', $RES, $both),
        'fenced_yard'          => $tag('Fenced Yard', 'outdoor', $RES, $both + ['description' => 'Residential yard fencing. Commercial and land fencing is fenced_lot.']),
        'oversized_lot'        => $tag('Oversized Lot', 'outdoor', $RES, $both),
        'corner_lot'           => $tag('Corner Lot', 'outdoor', $RES, $both),
        'cul_de_sac'           => $tag('Cul-de-Sac', 'outdoor', $RES, $both),
        'mature_landscaping'   => $tag('Mature Landscaping', 'outdoor', $RES, $both),
        'storage_shed'         => $tag('Storage Shed', 'outdoor', $RES, $both),
        'workshop'             => $tag('Workshop', 'outdoor', [$RS, $IS, $LS], $both),

        // ── Pool & Spa ──────────────────────────────────────────────────────
        'private_pool'   => $tag('Private Pool', 'pool_spa', $RES, $both + ['negatable' => true, 'description' => 'A pool belonging to the property, not a community pool.']),
        'heated_pool'    => $tag('Heated Pool', 'pool_spa', $RES, $both),
        'spa'            => $tag('Spa / Hot Tub', 'pool_spa', $RES, $both + ['negatable' => true]),
        'community_pool' => $tag('Community Pool', 'pool_spa', $RES, $both),

        // ── Water & Boating ─────────────────────────────────────────────────
        'waterfront'           => $tag('Waterfront', 'water', $ALL, $both + ['negatable' => true]),
        'water_view'           => $tag('Water View', 'water', $ALL, $both + ['negatable' => true]),
        'water_access'         => $tag('Water Access', 'water', $ALL, $both + ['negatable' => true]),
        'gulf_or_ocean_access' => $tag('Gulf / Ocean Access', 'water', $ALL, $both),
        'intracoastal_access'  => $tag('Intracoastal Access', 'water', $ALL, $both),
        'canal_frontage'       => $tag('Canal Frontage', 'water', $ALL, $both),
        'lake_access'          => $tag('Lake Access', 'water', $ALL, $both),
        // Added 2026-09-17. The structured water-access field named THIS body of water for this
        // listing. Never proximity: "near the beach", "a short walk to the river" and any Location
        // DNA distance are a different concept and must never reach these keys. They carry no
        // description-derived rule for that reason — see config/smart_tag_sources.php.
        'bay_or_harbor_access' => $tag('Bay / Harbor Access', 'water', $ALL, $both + ['description' => 'The listing\'s stated water access is a bay or harbor. Never proximity to one.']),
        'bayou_access'         => $tag('Bayou Access', 'water', $ALL, $both + ['description' => 'The listing\'s stated water access is a bayou. Never proximity to one.']),
        'beach_access'         => $tag('Beach Access', 'water', $ALL, $both + ['description' => 'The listing\'s stated water access is a beach. NEVER "near the beach" — proximity is Location DNA.']),
        'creek_access'         => $tag('Creek Access', 'water', $ALL, $both + ['description' => 'The listing\'s stated water access is a creek. Never proximity to one.']),
        'pond_access'          => $tag('Pond Access', 'water', $ALL, $both + ['description' => 'The listing\'s stated water access is a pond. Never proximity to one.']),
        'river_access'         => $tag('River Access', 'water', $ALL, $both + ['description' => 'The listing\'s stated water access is a river. Never proximity to one.']),
        'dock'                 => $tag('Dock', 'water', $ALL, $both + ['negatable' => true, 'description' => 'A boat dock. Never a loading dock (see loading_dock).']),
        'boat_lift'            => $tag('Boat Lift', 'water', $ALL, $both),
        'seawall'              => $tag('Seawall', 'water', $ALL, $both),
        'boat_slip_marina'     => $tag('Boat Slip / Marina', 'water', $RES, $both),

        // ── Garage & Parking ────────────────────────────────────────────────
        'garage'            => $tag('Garage', 'parking', $RES, $both + ['negatable' => true]),
        'carport'           => $tag('Carport', 'parking', $RES, $both + ['negatable' => true]),
        'oversized_garage'  => $tag('Oversized Garage', 'parking', $RES, $both),
        'rv_parking'        => $tag('RV Parking', 'parking', $ALL, $both),
        'boat_parking'      => $tag('Boat Parking', 'parking', $RES, $both),
        'circular_driveway' => $tag('Circular Driveway', 'parking', $RES, $both),
        'ev_charging'       => $tag('EV Charging', 'parking', $NONLAND, $both),
        'covered_parking'   => $tag('Covered Parking', 'parking', $NONLAND, $both),
        'secured_parking'   => $tag('Secured Parking', 'parking', $NONLAND, $both),

        // ── Community & Association ─────────────────────────────────────────
        'clubhouse'             => $tag('Clubhouse', 'community', $RES, $both),
        'fitness_center'        => $tag('Fitness Center', 'community', $RES, $both),
        'tennis_court'          => $tag('Tennis Court', 'community', $RES, $both),
        'pickleball_court'      => $tag('Pickleball Court', 'community', $RES, $both),
        'basketball_court'      => $tag('Basketball Court', 'community', $RES, $both),
        'golf_course_community' => $tag('Golf Course Community', 'community', $RES, $both),
        'dog_park'              => $tag('Dog Park', 'community', $RES, $both),
        'walking_trails'        => $tag('Walking Trails', 'community', $RES, $both),
        'gated_community'       => $tag('Gated Community', 'community', $RES, $both + [
            'compliance' => ['status' => 'pending_review', 'note' => 'Physical access feature; confirm wording and use before display or selection.', 'notice' => null],
        ]),
        'playground'            => $tag('Playground', 'community', $RES, $both + [
            'seeker_selectable' => false,
            'compliance'        => ['status' => 'restricted', 'note' => 'Owner-describable amenity; not a Buyer/Tenant preference in V1 (familial-status sensitivity).', 'notice' => null],
        ]),

        // ── Systems & Security ──────────────────────────────────────────────
        'central_air'               => $tag('Central Air', 'systems', $NONLAND, $both),
        'solar_power'               => $tag('Solar Power', 'systems', $NONLAND, $both),
        'backup_generator'          => $tag('Backup Generator', 'systems', $NONLAND, $both),
        'security_system'           => $tag('Security System', 'systems', $NONLAND, $both),
        'fire_sprinkler_system'     => $tag('Fire Sprinkler System', 'systems', $NONLAND, $both),
        'impact_windows'            => $tag('Impact Windows', 'systems', $NONLAND, $both),
        'high_speed_internet_ready' => $tag('High-Speed Internet Ready', 'systems', $NONLAND, $both),
        'fiber_internet'            => $tag('Fiber Internet', 'systems', $NONLAND, $both),
        'accessible_features'       => $tag('Accessibility Features', 'systems', $NONLAND, $both + [
            'seeker_selectable' => false,
            'compliance'        => ['status' => 'restricted', 'note' => 'Describes the property only. Never selectable as a Buyer/Tenant preference and never scored against a seeker disclosure.', 'notice' => null],
        ]),

        // ── Home Condition ──────────────────────────────────────────────────
        'new_construction'        => $tag('New Construction', 'condition', [$RS, $RL, $IS, $CS, $CL], $both + ['negatable' => true]),
        'under_construction'      => $tag('Under Construction', 'condition', [$RS, $IS, $CS], $both),
        'fully_updated'           => $tag('Fully Updated', 'condition', $RES, $both),
        'recently_renovated'      => $tag('Recently Renovated', 'condition', $RES, $both),
        'partially_updated'       => $tag('Partially Updated', 'condition', $RES, $both),
        'older_well_maintained'   => $tag('Older but Well Maintained', 'condition', $RES, $native),
        'move_in_ready'           => $tag('Move-In Ready', 'condition', $RES, $both),
        'turnkey_home'            => $tag('Turnkey Home', 'condition', [$RS], $both + ['description' => 'A home ready to occupy with nothing to do. Business turnkey is turnkey_business; lease furnishings "Turnkey" map to furnished.']),
        'fixer_upper'             => $tag('Fixer Upper', 'condition', $RES_SALE, $both),
        'handyman_special'        => $tag('Handyman Special', 'condition', $RES_SALE, $both),
        'needs_tlc'               => $tag('Needs TLC', 'condition', $RES_SALE, $both),
        'renovation_opportunity'  => $tag('Renovation Opportunity', 'condition', [$RS, $IS, $CS], $both),
        'cosmetic_updates_needed' => $tag('Cosmetic Updates Needed', 'condition', $RES_SALE, $both),
        'needs_complete_update'   => $tag('Needs Complete Update', 'condition', $RES_SALE, $both),
        'original_condition'      => $tag('Original Condition', 'condition', $RES_SALE, $both),
        'teardown'                => $tag('Teardown', 'condition', [$RS, $IS, $LS], $both),
        'investor_special'        => $tag('Investor Special', 'condition', $RES_SALE, $both),
        'value_add_opportunity'   => $tag('Value-Add Opportunity', 'condition', [$IS, $CS], $both),

        // ── Rental Features ─────────────────────────────────────────────────
        'furnished'                => $tag('Furnished', 'rental', [$RL, $CL, $CS, $BS], $both),
        'partially_furnished'      => $tag('Partially Furnished', 'rental', [$RL], $both),
        'unfurnished'              => $tag('Unfurnished', 'rental', $LEASE, $both),
        'pets_allowed'             => $tag('Pets Allowed', 'rental', [$RL], $both + [
            'negatable'  => true,
            'compliance' => [
                'status' => 'restricted',
                'note'   => 'A pet policy only. "Not present" must never be read or displayed as excluding assistance animals.',
                'notice' => 'Assistance animals are not pets and are not governed by pet policies.',
            ],
        ]),
        'water_included'           => $tag('Water Included', 'rental', $LEASE, $both),
        'electricity_included'     => $tag('Electricity Included', 'rental', $LEASE, $both),
        'internet_included'        => $tag('Internet Included', 'rental', $LEASE, $both),
        'cable_included'           => $tag('Cable TV Included', 'rental', $LEASE, $both),
        'trash_included'           => $tag('Trash Service Included', 'rental', $LEASE, $both),
        'lawn_care_included'       => $tag('Lawn Care Included', 'rental', $LEASE, $both),
        'pest_control_included'    => $tag('Pest Control Included', 'rental', $LEASE, $both),
        'pool_maintenance_included' => $tag('Pool Maintenance Included', 'rental', $LEASE, $both),

        // ── Income Property ─────────────────────────────────────────────────
        'separate_electric_meters' => $tag('Separate Electric Meters', 'income', [$IS, $CS, $CL], $both),
        'separate_water_meters'    => $tag('Separate Water Meters', 'income', [$IS, $CS, $CL], $both),
        'on_site_laundry'          => $tag('On-Site Laundry', 'income', [$IS, $RL], $both),

        // ── Building & Loading ──────────────────────────────────────────────
        'loading_dock'          => $tag('Loading Dock', 'building', $COM, $both),
        'overhead_doors'        => $tag('Overhead Doors', 'building', $COM, $both),
        'truck_well'            => $tag('Truck Well', 'building', $COM, $both),
        'high_bays'             => $tag('High Bays', 'building', $COM, $both),
        'clear_span'            => $tag('Clear Span', 'building', $COM, $both),
        'freight_elevator'      => $tag('Freight Elevator', 'building', $COM, $both),
        'drive_through'         => $tag('Drive-Through', 'building', $COM, $both),
        'freezer_space'         => $tag('Freezer Space', 'building', $COM, $both + ['negatable' => true]),
        'freestanding_building' => $tag('Freestanding Building', 'building', $COM, $both + ['negatable' => true]),

        // ── Commercial Build-Out ────────────────────────────────────────────
        'reception_area'     => $tag('Reception Area', 'buildout', $COM, $both),
        'private_offices'    => $tag('Private Offices', 'buildout', $COM, $both),
        'conference_room'    => $tag('Conference Room', 'buildout', $COM, $both),
        'kitchen_break_room' => $tag('Kitchen / Break Room', 'buildout', $COM, $both),
        'on_site_shower'     => $tag('On-Site Shower', 'buildout', $COM, $both),
        'vanilla_shell'      => $tag('Vanilla Shell', 'buildout', [$CS, $CL], $both),
        'gray_shell'         => $tag('Gray Shell', 'buildout', [$CS, $CL], $both),

        // ── Site, Access & Utilities ────────────────────────────────────────
        'fenced_lot'            => $tag('Fenced Lot', 'site', [$CS, $CL, $BS, $LS], $both + ['description' => 'Commercial or land fencing. Residential yard fencing is fenced_yard.']),
        'outside_storage'       => $tag('Outside Storage', 'site', $COM, $both),
        'lit_signage'           => $tag('Lit Signage', 'site', $COM, $both),
        'paved_road_access'     => $tag('Paved Road Access', 'site', [$CS, $CL, $BS, $LS], $both),
        'unpaved_road_access'   => $tag('Unpaved Road Access', 'site', [$CS, $BS, $LS], $both),
        'highway_frontage'      => $tag('Highway Frontage', 'site', [$CS, $CL, $BS, $LS], $both + ['description' => 'The parcel itself fronts a highway. Proximity to a highway is Location DNA, not a tag.']),
        'three_phase_power'     => $tag('Three-Phase Power', 'site', $COM, $both),
        'public_water'          => $tag('Public Water', 'site', $SITE, $both),
        'public_sewer'          => $tag('Public Sewer', 'site', $SITE, $both),
        'well_water'            => $tag('Well Water', 'site', $SITE, $both),
        'septic_system'         => $tag('Septic System', 'site', $SITE, $both),
        'electricity_available' => $tag('Electricity Available', 'site', [$LS], $both),

        // ── Business Sale ───────────────────────────────────────────────────
        'sold_with_real_estate'       => $tag('Sold with Real Estate', 'business', [$BS], $both + ['negatable' => true]),
        'liquor_license_included'     => $tag('Liquor License Included', 'business', [$BS], $both),
        'beer_wine_license_included'  => $tag('Beer & Wine License Included', 'business', [$BS], $both),
        'inventory_included'          => $tag('Inventory Included', 'business', [$BS], $both),
        'equipment_fixtures_included' => $tag('Equipment & Fixtures Included', 'business', [$BS], $both),
        'goodwill_included'           => $tag('Goodwill Included', 'business', [$BS], $native),
        'training_included'           => $tag('Training Included', 'business', [$BS], $both),
        'turnkey_business'            => $tag('Turnkey Business', 'business', [$BS], $both + ['description' => 'An operating business ready to run. Never a residential condition.']),

        // ── Land Features ───────────────────────────────────────────────────
        'cleared_land'     => $tag('Cleared Land', 'land', [$LS], $both),
        'wooded_land'      => $tag('Wooded Land', 'land', [$LS], $both),
        'pasture'          => $tag('Pasture', 'land', [$LS], $both),
        'zoned_for_horses' => $tag('Zoned for Horses', 'land', [$LS, $RS], $both + [
            'compliance' => ['status' => 'pending_review', 'note' => 'A zoning claim carried from the source; display must name the source.', 'notice' => null],
        ]),
        'barn_or_stables'  => $tag('Barn / Stables', 'land', [$LS, $RS], $both),
        'buildable_lot'    => $tag('Buildable Lot', 'land', [$LS], $both + [
            'negatable'  => true,
            'compliance' => ['status' => 'pending_review', 'note' => 'An owner or MLS claim about permitting; display must name the source.', 'notice' => null],
        ]),

        // ── Sale & Occupancy ────────────────────────────────────────────────
        'tenant_occupied'            => $tag('Tenant Occupied', 'sale_occupancy', [$RS, $IS, $CS], $both + ['public_display' => false, 'description' => 'Occupancy status only. Never tenant identity.']),
        'vacant'                     => $tag('Vacant', 'sale_occupancy', [$RS, $IS, $CS, $BS], $both),
        'existing_lease'             => $tag('Existing Lease in Place', 'sale_occupancy', [$IS, $CS], $both + ['negatable' => true]),
        'short_sale'                 => $tag('Short Sale', 'sale_occupancy', $SALE, $both),
        'reo_bank_owned'             => $tag('Bank Owned (REO)', 'sale_occupancy', $SALE, $both),
        'probate'                    => $tag('Probate', 'sale_occupancy', $SALE, $both),
        'auction'                    => $tag('Auction Sale', 'sale_occupancy', $SALE, $both),
        'government_owned'           => $tag('Government Owned', 'sale_occupancy', $SALE, $both),
        'seller_financing_available' => $tag('Seller Financing Available', 'sale_occupancy', $SALE, $both),
        'assumable_loan'             => $tag('Assumable Loan', 'sale_occupancy', $SALE, $both),
        'lease_option_available'     => $tag('Lease Option Available', 'sale_occupancy', $SALE, $both),
        'as_is'                      => $tag('Sold As-Is', 'sale_occupancy', $SALE, $both),
    ],
];
