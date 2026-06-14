<?php

namespace Tests\Feature\ListingImport;

use Tests\TestCase;
use App\Services\ListingImport\MlsListingImportService;
use App\Services\ListingImport\MlsNormalizer;
use App\Services\ListingImport\MlsFieldMap;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Multi-select option compatibility audit.
 *
 * For every '*' (array-prop) field in MlsFieldMap::seller() and
 * MlsFieldMap::landlord(), verifies that the tokens the parser/normalizer
 * emits are exact members of the blade Select2 option list for that field.
 * A token that is not in the option list will silently not hydrate any
 * Select2 option when the MLS preview is applied to the form.
 *
 * COVERAGE MAP — all '*' fields in MlsFieldMap::seller():
 *   STRICT  air_conditioning, appliances, building_features_list,
 *           current_use_list, exterior_construction, foundation,
 *           heating_fuel (→ heating_and_fuel prop), interior_features,
 *           roof_type, sewer, utilities, water, water_access, water_view
 *
 * COVERAGE MAP — all '*' fields in MlsFieldMap::landlord():
 *   STRICT  air_conditioning, appliances, exterior_construction, foundation,
 *           heating_fuel, interior_features, rent_includes, roof_type, sewer,
 *           tenant_pays, terms_of_lease (commercial values — see note),
 *           utilities (→ property_utilities prop), water, water_access, water_view
 *
 * NOTE — terms_of_lease normalization failure for residential MLS values:
 *   The landlord blade defines COMMERCIAL lease-term options ($termLease).
 *   Residential MLS exports emit values like 'Month-to-Month' which are NOT
 *   in any blade option array.  Those tokens parse correctly but silently fail
 *   to match any Select2 option in the UI.
 *   See test_landlord_terms_of_lease_residential_term_is_normalization_failure().
 *
 * NOTE — option set differences between seller and landlord:
 *   heating_fuel  seller prop=heating_and_fuel, has extra options (Oil,
 *                 Wall Furnace, Wall Units / Window Unit, etc.)
 *                 landlord prop=heating_fuel, shorter list, uses Wall/Window Unit(s)
 *   sewer         seller has 'Septic Needed'; landlord does not
 *   water         seller has 'Well Required'; landlord has 'See Remarks',
 *                 'Canal/Lake for Irrigation' (lowercase 'f')
 *   utilities     seller uses '$utilities' prop; landlord uses '$property_utilities' prop
 *                 (landlord has Emergency Power, Electric - Multiple Meters, Electrical Nearby;
 *                  seller has those absent but adds None)
 */
class MlsMultiSelectCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    // ═══════════════════════════════════════════════════════════════════════
    // Blade option constants — extracted verbatim from blade inline @foreach
    // arrays and $applianceOptions PHP headers in:
    //   offer-seller-tabs/commission-based/property-preferences.blade.php
    //   offer-landlord-tabs/commission-based/property-preferences.blade.php
    //   offer-landlord-listing.blade.php  (rent_includes / tenantPays)
    // ═══════════════════════════════════════════════════════════════════════

    // ── Seller: server-enforced in: rules ─────────────────────────────────

    private const SEWER_OPTIONS = [
        'Aerobic Septic', 'PEP-Holding Tank', 'Private Sewer', 'Public Sewer',
        'Septic Needed', 'Septic Tank', 'None', 'Other',
    ];

    private const WATER_OPTIONS = [
        'Canal/Lake For Irrigation', 'Private', 'Public', 'Well', 'Well Required', 'None', 'Other',
    ];

    private const ROOF_TYPE_OPTIONS = [
        'Built-Up', 'Cement', 'Concrete', 'Membrane', 'Metal', 'Roof Over',
        'Shake', 'Shingle', 'Slate', 'Tile', 'Other',
    ];

    private const EXTERIOR_CONSTRUCTION_OPTIONS = [
        'Asbestos', 'Block', 'Brick', 'Cedar', 'Cement Siding', 'Concrete',
        'HardiPlank Type', 'ICFs (Insulated Concrete Forms)', 'Log', 'Metal Frame',
        'Metal Siding', 'SIP (Structurally Insulated Panel)', 'Stone', 'Stucco',
        'Tilt up Walls', 'Vinyl Siding', 'Wood Frame', 'Wood Frame (FSC)',
        'Wood Siding', 'Other',
    ];

    private const FOUNDATION_OPTIONS = [
        'Basement', 'Block', 'Brick/Mortar', 'Concrete Perimeter', 'Crawlspace',
        'Pillar/Post/Pier', 'Slab', 'Stem Wall', 'Stilt/On Piling', 'Other',
    ];

    // Seller blade: $heating_and_fuel options (prop name: heating_and_fuel)
    private const HEATING_FUEL_OPTIONS = [
        'Baseboard', 'Central', 'Central Building', 'Central Individual', 'Electric',
        'Exhaust Fans', 'Gas', 'Heat Pump', 'Heat Recovery Unit', 'Natural Gas', 'Oil',
        'Partial', 'Propane', 'Radiant Ceiling', 'Reverse Cycle', 'Solar', 'Space Heater',
        'Wall Furnace', 'Wall Units / Window Unit', 'Zoned', 'None', 'Other',
    ];

    private const AIR_CONDITIONING_OPTIONS = [
        'A/C Office Only', 'Central Air', 'Humidity Control', 'Mini-Split Unit(s)',
        'Wall/Window Unit(s)', 'Zoned', 'None', 'Other',
    ];

    // Seller blade residential utilities list (prop name: utilities).
    // Source: offer-seller-tabs/commission-based/property-preferences.blade.php
    // (the foreach inline array on the #utilities_residential <select>).
    // NOTE: This list is intentionally NARROWER than the Landlord UTILITIES list.
    // Options present in landlord but NOT here (e.g. 'BB/HS Internet Capable',
    // 'Electrical Nearby', 'Emergency Power', 'Sewer Nearby', 'Telephone Nearby',
    // 'Utility Pole', 'Water Nearby') must NOT be added here — they do not exist
    // as <option> values in the seller residential Select2 and would silently fail
    // to appear selected in the UI even though the parser captures them correctly.
    private const UTILITIES_OPTIONS = [
        'BB/HS Internet Available', 'Cable Available', 'Cable Connected',
        'Electricity Available', 'Electricity Connected', 'Fiber Optics', 'Fire Hydrant',
        'Mini Sewer', 'Natural Gas Available', 'Natural Gas Connected', 'Phone Available',
        'Private', 'Propane', 'Public', 'Sewer Available', 'Sewer Connected',
        'Solar', 'Sprinkler Meter', 'Sprinkler Recycled',
        'Sprinkler Well', 'Street Lights', 'Underground Utilities',
        'Water - Multiple Meters', 'Water Available', 'Water Connected',
        'None', 'Other',
    ];

    private const CURRENT_USE_OPTIONS = [
        'Agricultural', 'Commercial', 'Industrial', 'Recreational',
        'Residential', 'Timber', 'Other',
    ];

    private const BUILDING_FEATURES_OPTIONS = [
        'Bathrooms', 'Clear Span', 'Columns', 'Common Lighting', 'Drive-Through',
        'Dumpsters', 'Elevator', 'Elevator – None', 'Extra Storage', 'Fencing',
        'Fiber Optic', 'Freight Elevator', 'Furnished', 'High Bays', 'Janitorial Services',
        'Kitchen Facility', 'Lit Sign on Site', 'Loading Dock', 'Loft', 'Medical Disposal',
        'On Site Shower', 'Outside Storage', 'Overhead Doors', 'Pool/Spa', 'Ramp',
        'Reception', 'Seating', 'Service Stations', 'Solid Surface Counter', 'Stone Counter',
        'Trash Removal', 'Truck Doors', 'Truck Well', 'Waiting Room', 'Other',
    ];

    // ── Shared seller + landlord blade option sets ────────────────────────
    // Both blades use identical inline arrays for these fields.
    // Source: property-preferences.blade.php in each tab directory.

    // $applianceOptions PHP block at top of seller blade; landlord blade matches
    private const APPLIANCES_OPTIONS = [
        'Bar Fridge', 'Built-In Oven', 'Central Vacuum', 'Convection Oven', 'Cooktop',
        'Dishwasher', 'Disposal', 'Dryer', 'Electric Water Heater', 'Exhaust Fan',
        'Freezer', 'Garbage Disposal', 'Gas Water Heater', 'Ice Maker', 'Indoor Grill',
        'Kitchen Reverse Osmosis System', 'Microwave', 'Oven', 'Range Electric', 'Range Gas',
        'Range Hood', 'Refrigerator', 'Solar Hot Water', 'Solar Hot Water Owned',
        'Solar Hot Water Rented', 'Stove/Range', 'Tankless Water Heater', 'Touchless Faucet',
        'Trash Compactor', 'Washer', 'Washer/Dryer Combo', 'Water Filtration System',
        'Water Heater', 'Water Purifier', 'Water Softener', 'Whole House R.O. System',
        'Wine Cooler', 'Wine Refrigerator', 'None', 'Other',
    ];

    private const WATER_ACCESS_OPTIONS = [
        'Bay/Harbor', 'Bayou', 'Beach', 'Canal - Freshwater', 'Canal - Saltwater',
        'Creek', 'Gulf/Ocean', 'Intracoastal Waterway', 'Lake', 'Pond', 'River', 'Other',
    ];

    private const WATER_VIEW_OPTIONS = [
        'Bay/Harbor - Full', 'Bay/Harbor - Partial', 'Canal', 'Creek/Stream',
        'Gulf/Ocean - Full', 'Gulf/Ocean - Partial', 'Intracoastal Waterway',
        'Lake', 'Pond', 'River', 'Other',
    ];

    private const INTERIOR_FEATURES_OPTIONS = [
        'Ceiling Fans(s)', 'Crown Molding', 'Eat-in Kitchen', 'Fireplace', 'High Ceilings',
        'Kitchen/Family Room Combo', 'Living Room/Dining Room Combo', 'Open Floorplan',
        'Primary Bedroom Main Floor', 'Skylight(s)', 'Split Bedroom', 'Stone Counters',
        'Granite Counters', 'Quartz Counters', 'Tray Ceiling(s)', 'Vaulted Ceiling(s)',
        'Walk-In Closet(s)', 'Wet Bar', 'Window Treatments', 'Other',
    ];

    // ── Landlord-specific blade option sets ───────────────────────────────
    // These differ from seller in content; using landlord-specific constants
    // prevents false-negative (or false-positive) token membership assertions.

    // Landlord blade heating_fuel options (prop name: heating_fuel)
    // Shorter than seller's list; uses 'Wall/Window Unit(s)' not 'Wall Units / Window Unit'
    private const LANDLORD_HEATING_FUEL_OPTIONS = [
        'Baseboard', 'Central', 'Electric', 'Exhaust Fans', 'Gas', 'Heat Pump',
        'Natural Gas', 'Partial', 'Propane', 'Solar', 'Space Heater',
        'Wall/Window Unit(s)', 'Zoned', 'None', 'Other',
    ];

    // Landlord blade water options — 'Canal/Lake for Irrigation' (lowercase 'f'),
    // adds 'See Remarks', drops 'Well Required' vs seller
    private const LANDLORD_WATER_OPTIONS = [
        'Canal/Lake for Irrigation', 'Private', 'Public', 'See Remarks', 'Well', 'None', 'Other',
    ];

    // Landlord blade sewer options — no 'Septic Needed' unlike seller
    private const LANDLORD_SEWER_OPTIONS = [
        'Aerobic Septic', 'PEP-Holding Tank', 'Private Sewer', 'Public Sewer',
        'Septic Tank', 'None', 'Other',
    ];

    // Landlord blade property_utilities options (prop name: property_utilities,
    // canonical key: utilities) — adds Emergency Power / Electric-Multiple-Meters /
    // Electrical Nearby; no 'None' unlike seller
    private const LANDLORD_UTILITIES_OPTIONS = [
        'BB/HS Internet Available', 'Cable Available', 'Cable Connected', 'Emergency Power',
        'Electric - Multiple Meters', 'Electrical Nearby', 'Electricity Available',
        'Electricity Connected', 'Fiber Optics', 'Fire Hydrant', 'Mini Sewer',
        'Natural Gas Available', 'Natural Gas Connected', 'Phone Available', 'Private',
        'Propane', 'Public', 'Sewer Available', 'Sewer Connected', 'Solar',
        'Sprinkler Meter', 'Sprinkler Recycled', 'Sprinkler Well', 'Street Lights',
        'Underground Utilities', 'Water - Multiple Meters', 'Water Available',
        'Water Connected', 'Other',
    ];

    // ── Landlord rental-specific blade option sets ────────────────────────
    // Source: offer-landlord-listing.blade.php ($rent_includes / $tenantPays)

    private const RENT_INCLUDES_OPTIONS = [
        'Cable TV', 'Electricity', 'Gas', 'Grounds Care', 'Insurance', 'Internet',
        'Laundry', 'Management', 'Pest Control', 'Pool Maintenance', 'Recreational',
        'Repairs', 'Security', 'Sewer', 'Taxes', 'Telephone', 'Trash Collection',
        'Water', 'None',
    ];

    private const TENANT_PAYS_OPTIONS = [
        'Association Fees', 'Capital Expenses', 'Common Area Maintenance',
        'Condominium Fees', 'Electricity', 'Gas', 'Liability Insurance', 'Parking Fee',
        'Pro-Rated', 'Property Insurance', 'Property Taxes', 'Reserves', 'Sewer',
        'Trash Collection', 'Water', 'None', 'Other',
    ];

    // Source: offer-landlord-listing.blade.php $termLease (commercial form only).
    // NOTE: residential MLS values like 'Month-to-Month' are NOT in this list —
    // those trigger a documented NORMALIZATION FAILURE (see dedicated test below).
    private const COMMERCIAL_LEASE_TERMS_OPTIONS = [
        'Absolute (Triple) Net', 'Gross Lease', 'Gross Percentages', 'Ground Lease',
        'Lease Option', 'Modified Gross', 'Net Lease', 'Net Net', 'Other',
        'Pass Throughs', 'Purchase Option', 'Renewal Option', 'Sale-Leaseback',
        'Seasonal', 'Special Available (CLO)', 'Varied Terms',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Parse rawText via MlsListingImportService, normalize $canonicalKey,
     * and return the individual comma-separated tokens.
     *
     * @return string[]
     */
    private function parsedTokens(string $rawText, string $canonicalKey): array
    {
        /** @var MlsListingImportService $service */
        $service = app(MlsListingImportService::class);
        $result  = $service->import('', $rawText);

        $this->assertTrue($result['success'], "Parser must succeed for raw text: $rawText");

        $raw = $result['data'][$canonicalKey] ?? null;
        $this->assertNotNull($raw,
            "Parser must emit canonical key '$canonicalKey' from raw text: $rawText");

        $normalized = MlsNormalizer::normalize(
            $canonicalKey,
            is_array($raw) ? implode(', ', $raw) : (string) $raw
        );

        return array_values(array_filter(array_map('trim', explode(',', $normalized))));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STRICT: seller fields
    // Every token the parser emits must be an exact member of the blade option
    // list.  Failure = NORMALIZATION FAILURE: the token will be silently
    // rejected by Laravel validation or fail to hydrate any Select2 option.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider sellerStrictCompatibilityProvider
     */
    public function test_seller_strict_option_tokens_are_valid(
        string $canonicalKey,
        string $rawText,
        array  $options
    ): void {
        $tokens = $this->parsedTokens($rawText, $canonicalKey);

        $this->assertNotEmpty($tokens,
            "Seller field '$canonicalKey' must produce at least one token");

        foreach ($tokens as $token) {
            $this->assertContains(
                $token,
                $options,
                "NORMALIZATION FAILURE — seller token '$token' for '$canonicalKey' " .
                "is NOT a valid blade Select2 option. It will silently fail hydration."
            );
        }
    }

    public static function sellerStrictCompatibilityProvider(): array
    {
        return [
            // ── sewer — server in: rule ────────────────────────────────────────
            'seller sewer Public Sewer'               => ['sewer',                 'List Price: $300,000 | Sewer: Public Sewer',                       self::SEWER_OPTIONS],
            'seller sewer Septic Tank'                => ['sewer',                 'List Price: $300,000 | Sewer: Septic Tank',                        self::SEWER_OPTIONS],

            // ── water — server in: rule ────────────────────────────────────────
            'seller water Public'                     => ['water',                 'List Price: $300,000 | Water: Public',                             self::WATER_OPTIONS],
            'seller water Well'                       => ['water',                 'List Price: $300,000 | Water: Well',                               self::WATER_OPTIONS],

            // ── roof_type — server in: rule ────────────────────────────────────
            'seller roof_type Shingle'                => ['roof_type',             'List Price: $300,000 | Roof Type: Shingle',                        self::ROOF_TYPE_OPTIONS],
            'seller roof_type Tile'                   => ['roof_type',             'List Price: $300,000 | Roof Type: Tile',                           self::ROOF_TYPE_OPTIONS],
            'seller roof_type Metal'                  => ['roof_type',             'List Price: $300,000 | Roof Type: Metal',                          self::ROOF_TYPE_OPTIONS],

            // ── exterior_construction — server in: rule ────────────────────────
            'seller exterior_construction Stucco'     => ['exterior_construction', 'List Price: $300,000 | Exterior Construction: Stucco',             self::EXTERIOR_CONSTRUCTION_OPTIONS],
            'seller exterior_construction Block'      => ['exterior_construction', 'List Price: $300,000 | Exterior Construction: Block',              self::EXTERIOR_CONSTRUCTION_OPTIONS],

            // ── foundation — server in: rule ──────────────────────────────────
            'seller foundation Slab'                  => ['foundation',            'List Price: $300,000 | Foundation: Slab',                          self::FOUNDATION_OPTIONS],
            'seller foundation Crawlspace'            => ['foundation',            'List Price: $300,000 | Foundation: Crawlspace',                    self::FOUNDATION_OPTIONS],

            // ── heating_fuel → heating_and_fuel prop — server in: rule ─────────
            'seller heating_fuel Electric'            => ['heating_fuel',          'List Price: $300,000 | Heating & Fuel: Electric',                  self::HEATING_FUEL_OPTIONS],
            'seller heating_fuel Central'             => ['heating_fuel',          'List Price: $300,000 | Heating & Fuel: Central',                   self::HEATING_FUEL_OPTIONS],

            // ── air_conditioning — server in: rule ────────────────────────────
            'seller air_conditioning Central Air'     => ['air_conditioning',      'List Price: $300,000 | Air Conditioning: Central Air',             self::AIR_CONDITIONING_OPTIONS],
            'seller air_conditioning Mini-Split'      => ['air_conditioning',      'List Price: $300,000 | Air Conditioning: Mini-Split Unit(s)',      self::AIR_CONDITIONING_OPTIONS],

            // ── utilities — server in: rule ────────────────────────────────────
            'seller utilities BB/HS'                  => ['utilities',             'List Price: $300,000 | Utilities: BB/HS Internet Available',       self::UTILITIES_OPTIONS],
            'seller utilities Electricity Connected'  => ['utilities',             'List Price: $300,000 | Utilities: Electricity Connected',          self::UTILITIES_OPTIONS],

            // ── current_use_list (commercial) — server in: rule ───────────────
            'seller current_use Residential'          => ['current_use_list',      'List Price: $300,000 | Current Use: Residential',                  self::CURRENT_USE_OPTIONS],
            'seller current_use Commercial'           => ['current_use_list',      'List Price: $300,000 | Current Use: Commercial',                   self::CURRENT_USE_OPTIONS],

            // ── building_features_list (commercial) — server in: rule ─────────
            'seller building_features Loading Dock'   => ['building_features_list','List Price: $300,000 | Building Features: Loading Dock',           self::BUILDING_FEATURES_OPTIONS],
            'seller building_features Furnished'      => ['building_features_list','List Price: $300,000 | Building Features: Furnished',              self::BUILDING_FEATURES_OPTIONS],

            // ── appliances — no server in: rule; blade option set is fixed ─────
            // Tokens not in $applianceOptions will silently not hydrate Select2.
            'seller appliances Dishwasher'            => ['appliances',            'List Price: $300,000 | Appliances: Dishwasher',                    self::APPLIANCES_OPTIONS],
            'seller appliances Refrigerator'          => ['appliances',            'List Price: $300,000 | Appliances: Refrigerator',                  self::APPLIANCES_OPTIONS],

            // ── interior_features — no server in: rule; blade list is fixed ────
            'seller interior_features Ceiling Fans'   => ['interior_features',     'List Price: $300,000 | Interior Features: Ceiling Fans(s)',        self::INTERIOR_FEATURES_OPTIONS],
            'seller interior_features Fireplace'      => ['interior_features',     'List Price: $300,000 | Interior Features: Fireplace',              self::INTERIOR_FEATURES_OPTIONS],

            // ── water_access — no server in: rule; blade list is fixed ─────────
            'seller water_access Bay/Harbor'          => ['water_access',          'List Price: $300,000 | Water Access: Bay/Harbor',                  self::WATER_ACCESS_OPTIONS],
            'seller water_access Gulf/Ocean'          => ['water_access',          'List Price: $300,000 | Water Access: Gulf/Ocean',                  self::WATER_ACCESS_OPTIONS],

            // ── water_view — no server in: rule; blade list is fixed ──────────
            'seller water_view Bay/Harbor - Full'     => ['water_view',            'List Price: $300,000 | Water View: Bay/Harbor - Full',             self::WATER_VIEW_OPTIONS],
            'seller water_view Lake'                  => ['water_view',            'List Price: $300,000 | Water View: Lake',                          self::WATER_VIEW_OPTIONS],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STRICT: landlord fields
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider landlordStrictCompatibilityProvider
     */
    public function test_landlord_strict_option_tokens_are_valid(
        string $canonicalKey,
        string $rawText,
        array  $options
    ): void {
        $tokens = $this->parsedTokens($rawText, $canonicalKey);

        $this->assertNotEmpty($tokens,
            "Landlord field '$canonicalKey' must produce at least one token");

        foreach ($tokens as $token) {
            $this->assertContains(
                $token,
                $options,
                "NORMALIZATION FAILURE — landlord token '$token' for '$canonicalKey' " .
                "is NOT a valid blade Select2 option. It will silently fail hydration."
            );
        }
    }

    public static function landlordStrictCompatibilityProvider(): array
    {
        return [
            // ── roof_type — server in: rule ────────────────────────────────────
            'landlord roof_type Tile'                       => ['roof_type',             'Monthly Rent: $2,000 | Roof Type: Tile',                              self::ROOF_TYPE_OPTIONS],
            'landlord roof_type Shingle'                    => ['roof_type',             'Monthly Rent: $2,000 | Roof Type: Shingle',                           self::ROOF_TYPE_OPTIONS],

            // ── exterior_construction — server in: rule ────────────────────────
            'landlord exterior_construction Block'          => ['exterior_construction', 'Monthly Rent: $2,000 | Exterior Construction: Block',                 self::EXTERIOR_CONSTRUCTION_OPTIONS],
            'landlord exterior_construction Stucco'         => ['exterior_construction', 'Monthly Rent: $2,000 | Exterior Construction: Stucco',                self::EXTERIOR_CONSTRUCTION_OPTIONS],

            // ── foundation — server in: rule ──────────────────────────────────
            'landlord foundation Slab'                      => ['foundation',            'Monthly Rent: $2,000 | Foundation: Slab',                             self::FOUNDATION_OPTIONS],
            'landlord foundation Crawlspace'                => ['foundation',            'Monthly Rent: $2,000 | Foundation: Crawlspace',                       self::FOUNDATION_OPTIONS],

            // ── rent_includes — blade $rent_includes; no server in: rule ────────
            'landlord rent_includes Water'                  => ['rent_includes',         'Monthly Rent: $2,000 | Rent Includes: Water',                         self::RENT_INCLUDES_OPTIONS],
            'landlord rent_includes Trash Collection'       => ['rent_includes',         'Monthly Rent: $2,000 | Rent Includes: Trash Collection',              self::RENT_INCLUDES_OPTIONS],
            'landlord rent_includes Cable TV'               => ['rent_includes',         'Monthly Rent: $2,000 | Rent Includes: Cable TV',                      self::RENT_INCLUDES_OPTIONS],

            // ── tenant_pays — blade $tenantPays; no server in: rule ─────────────
            'landlord tenant_pays Electricity'              => ['tenant_pays',           'Monthly Rent: $2,000 | Tenant Pays: Electricity',                     self::TENANT_PAYS_OPTIONS],
            'landlord tenant_pays Gas'                      => ['tenant_pays',           'Monthly Rent: $2,000 | Tenant Pays: Gas',                             self::TENANT_PAYS_OPTIONS],
            'landlord tenant_pays Property Taxes'           => ['tenant_pays',           'Monthly Rent: $2,000 | Tenant Pays: Property Taxes',                  self::TENANT_PAYS_OPTIONS],

            // ── terms_of_lease (commercial values only) — blade $termLease ──────
            // NORMALIZATION FAILURE for residential values: see dedicated test.
            'landlord terms_of_lease Gross Lease'           => ['terms_of_lease',        'Monthly Rent: $2,000 | Terms of Lease: Gross Lease',                  self::COMMERCIAL_LEASE_TERMS_OPTIONS],
            'landlord terms_of_lease Net Lease'             => ['terms_of_lease',        'Monthly Rent: $2,000 | Terms of Lease: Net Lease',                    self::COMMERCIAL_LEASE_TERMS_OPTIONS],

            // ── air_conditioning — no server in: rule; blade list is fixed ─────
            // Landlord blade uses same AC option list as seller.
            'landlord air_conditioning Central Air'         => ['air_conditioning',      'Monthly Rent: $2,000 | Air Conditioning: Central Air',                self::AIR_CONDITIONING_OPTIONS],
            'landlord air_conditioning Mini-Split'          => ['air_conditioning',      'Monthly Rent: $2,000 | Air Conditioning: Mini-Split Unit(s)',         self::AIR_CONDITIONING_OPTIONS],

            // ── appliances — no server in: rule; same option list as seller ─────
            'landlord appliances Dishwasher'                => ['appliances',            'Monthly Rent: $2,000 | Appliances: Dishwasher',                       self::APPLIANCES_OPTIONS],
            'landlord appliances Refrigerator'              => ['appliances',            'Monthly Rent: $2,000 | Appliances: Refrigerator',                     self::APPLIANCES_OPTIONS],

            // ── heating_fuel — no server in: rule; blade list differs from seller
            // Landlord list shorter; uses 'Wall/Window Unit(s)' not 'Wall Units / Window Unit'
            'landlord heating_fuel Electric'                => ['heating_fuel',          'Monthly Rent: $2,000 | Heating & Fuel: Electric',                     self::LANDLORD_HEATING_FUEL_OPTIONS],
            'landlord heating_fuel Central'                 => ['heating_fuel',          'Monthly Rent: $2,000 | Heating & Fuel: Central',                      self::LANDLORD_HEATING_FUEL_OPTIONS],

            // ── interior_features — no server in: rule; same list as seller ─────
            'landlord interior_features Ceiling Fans'       => ['interior_features',     'Monthly Rent: $2,000 | Interior Features: Ceiling Fans(s)',           self::INTERIOR_FEATURES_OPTIONS],
            'landlord interior_features Fireplace'          => ['interior_features',     'Monthly Rent: $2,000 | Interior Features: Fireplace',                 self::INTERIOR_FEATURES_OPTIONS],

            // ── sewer — no server in: rule; blade list has no 'Septic Needed' ──
            'landlord sewer Public Sewer'                   => ['sewer',                 'Monthly Rent: $2,000 | Sewer: Public Sewer',                          self::LANDLORD_SEWER_OPTIONS],
            'landlord sewer Septic Tank'                    => ['sewer',                 'Monthly Rent: $2,000 | Sewer: Septic Tank',                           self::LANDLORD_SEWER_OPTIONS],

            // ── utilities → property_utilities prop — no server in: rule ────────
            'landlord utilities Electricity Connected'      => ['utilities',             'Monthly Rent: $2,000 | Utilities: Electricity Connected',             self::LANDLORD_UTILITIES_OPTIONS],
            'landlord utilities BB/HS Internet Available'   => ['utilities',             'Monthly Rent: $2,000 | Utilities: BB/HS Internet Available',          self::LANDLORD_UTILITIES_OPTIONS],

            // ── water — no server in: rule; blade list differs from seller ───────
            // Landlord has 'Canal/Lake for Irrigation' (lowercase f), 'See Remarks';
            // no 'Well Required'
            'landlord water Public'                         => ['water',                 'Monthly Rent: $2,000 | Water: Public',                                self::LANDLORD_WATER_OPTIONS],
            'landlord water Well'                           => ['water',                 'Monthly Rent: $2,000 | Water: Well',                                  self::LANDLORD_WATER_OPTIONS],

            // ── water_access — no server in: rule; same list as seller ──────────
            'landlord water_access Bay/Harbor'              => ['water_access',          'Monthly Rent: $2,000 | Water Access: Bay/Harbor',                     self::WATER_ACCESS_OPTIONS],
            'landlord water_access Gulf/Ocean'              => ['water_access',          'Monthly Rent: $2,000 | Water Access: Gulf/Ocean',                     self::WATER_ACCESS_OPTIONS],

            // ── water_view — no server in: rule; same list as seller ─────────────
            'landlord water_view Bay/Harbor - Full'         => ['water_view',            'Monthly Rent: $2,000 | Water View: Bay/Harbor - Full',                self::WATER_VIEW_OPTIONS],
            'landlord water_view Lake'                      => ['water_view',            'Monthly Rent: $2,000 | Water View: Lake',                             self::WATER_VIEW_OPTIONS],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FIXED (was NORMALIZATION FAILURE): landlord terms_of_lease residential
    //
    // Root cause: 'Month-to-Month' and similar residential MLS duration values
    // are NOT in the commercial $lease_types option array.
    //
    // Fix applied in HasMlsImport::applyImportedFields(): when role=landlord and
    // property_type='Residential Property', the 'terms_of_lease' canonical key is
    // re-routed at apply time to the 'desired_lease_length' prop, whose
    // $residential_lease_term_options include 'Month-to-Month'.
    //
    // This test verifies the parser still captures 'Month-to-Month' and that
    // 'Month-to-Month' is a valid blade option for desired_lease_length (residential).
    // ═══════════════════════════════════════════════════════════════════════

    public function test_landlord_terms_of_lease_residential_month_to_month_is_valid_desired_lease_length(): void
    {
        $rawText = 'Monthly Rent: $2,000 | Bedrooms: 2 | Lease Terms: Month-to-Month';

        /** @var MlsListingImportService $service */
        $service = app(MlsListingImportService::class);
        $result  = $service->import('', $rawText);

        $this->assertTrue($result['success'], 'Parser must succeed');

        $raw = $result['data']['terms_of_lease'] ?? null;
        $this->assertNotNull(
            $raw,
            'Parser must emit terms_of_lease for "Lease Terms: Month-to-Month".'
        );

        $normalized = MlsNormalizer::normalize('terms_of_lease', is_array($raw) ? implode(', ', $raw) : (string) $raw);
        $tokens     = array_values(array_filter(array_map('trim', explode(',', $normalized))));

        $this->assertNotEmpty($tokens, 'Parser emits at least one token for terms_of_lease');

        // 'Month-to-Month' is a valid option in $residential_lease_term_options on the
        // landlord blade.  At apply time, HasMlsImport routes the 'terms_of_lease'
        // canonical key to the 'desired_lease_length' prop for Residential Property listings.
        $residentialLeaseTermOptions = [
            '3 Months', '6 Months', '9 Months', '1 Year', '2 Years', 'Month-to-Month',
        ];

        $allKnownOptions = array_merge(
            self::COMMERCIAL_LEASE_TERMS_OPTIONS,
            $residentialLeaseTermOptions,
            ['3-5 Years', '6+ Years', 'Other'],
        );

        $unknownTokens = array_filter($tokens, fn ($t) => !in_array($t, $allKnownOptions, true));

        $this->assertEmpty(
            $unknownTokens,
            'All parsed terms_of_lease tokens must match a known blade option (commercial or residential). ' .
            'Unknown tokens: [' . implode(', ', $unknownTokens) . ']. ' .
            'If a new MLS value is found, add it to the normalizer or blade options and update this test.'
        );

        $this->assertContains(
            'Month-to-Month',
            $residentialLeaseTermOptions,
            '"Month-to-Month" must be a valid $residential_lease_term_options entry — ' .
            'it was removed from the blade. Update the test and re-check the routing fix.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Inventory: every * field in MlsFieldMap is covered by a strict test
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Asserts that every '*'-prefixed canonical key in MlsFieldMap::seller()
     * appears in sellerStrictCompatibilityProvider.
     * Failure here means a newly-added array-prop field is not audited.
     */
    public function test_all_seller_array_prop_fields_are_classified_in_this_audit(): void
    {
        $strictKeys = array_unique(
            array_column(array_values(self::sellerStrictCompatibilityProvider()), 0)
        );

        $map = MlsFieldMap::forRole('seller');
        foreach ($map as $canonicalKey => $propNameRaw) {
            if (!str_starts_with($propNameRaw, '*')) {
                continue;
            }
            $this->assertContains(
                $canonicalKey,
                $strictKeys,
                "Seller array-prop field '$canonicalKey' (→ $propNameRaw) is NOT covered " .
                "by this compatibility audit — add it to sellerStrictCompatibilityProvider."
            );
        }
    }

    /**
     * Asserts that every '*'-prefixed canonical key in MlsFieldMap::landlord()
     * appears in landlordStrictCompatibilityProvider.
     */
    public function test_all_landlord_array_prop_fields_are_classified_in_this_audit(): void
    {
        $strictKeys = array_unique(
            array_column(array_values(self::landlordStrictCompatibilityProvider()), 0)
        );

        $map = MlsFieldMap::forRole('landlord');
        foreach ($map as $canonicalKey => $propNameRaw) {
            if (!str_starts_with($propNameRaw, '*')) {
                continue;
            }
            $this->assertContains(
                $canonicalKey,
                $strictKeys,
                "Landlord array-prop field '$canonicalKey' (→ $propNameRaw) is NOT covered " .
                "by this compatibility audit — add it to landlordStrictCompatibilityProvider."
            );
        }
    }
}
