<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaPoiDistanceService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * CategoryExclusionRulesRegressionTest
 *
 * Verifies that CATEGORY_EXCLUSION_RULES cannot silently regress for the four
 * category areas hardened in Task #3176 (pharmacy, golf_course, transit_station,
 * beach / beach_access).
 *
 * All assertions run entirely in-memory — no database writes, no Google API calls.
 * The private passesExclusionFilter() method is accessed via ReflectionMethod.
 *
 * Coverage areas:
 *   Pharmacy      — veterinary/animal-care providers (type + name-pattern chains)
 *   Golf          — entertainment golf venues (name-pattern)
 *   Transit       — retail store false positives (type-based)
 *   Beach         — lodging/resort false positives (type + name-pattern)
 *   Beach Access  — same lodging/resort guard on beach_access category
 *
 * Pass cases (legitimate POIs that must NOT be excluded) are tested alongside
 * each block to guard against over-blocking regressions.
 */
class CategoryExclusionRulesRegressionTest extends TestCase
{
    private ReflectionMethod $filter;
    private LocationDnaPoiDistanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LocationDnaPoiDistanceService();
        $this->filter  = new ReflectionMethod(
            LocationDnaPoiDistanceService::class,
            'passesExclusionFilter'
        );
        $this->filter->setAccessible(true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    /** Invoke passesExclusionFilter() directly via Reflection. */
    private function passes(string $category, string $name, array $types = []): bool
    {
        return $this->filter->invoke(
            $this->service,
            $category,
            ['name' => $name, 'types' => $types]
        );
    }

    // =========================================================================
    // PHARMACY — veterinary / animal-care providers
    // =========================================================================

    /** @test */
    public function pharmacy_excludes_result_with_veterinary_care_type(): void
    {
        $this->assertFalse(
            $this->passes('pharmacy', 'Healthy Paws Dispensary', ['pharmacy', 'veterinary_care']),
            'veterinary_care type must trigger exclusion regardless of name'
        );
    }

    /** @test */
    public function pharmacy_excludes_banfield_by_name(): void
    {
        $this->assertFalse($this->passes('pharmacy', 'Banfield Pet Hospital', ['pharmacy']));
        $this->assertFalse($this->passes('pharmacy', 'Banfield Animal Hospital - PetSmart', ['pharmacy', 'health']));
    }

    /** @test */
    public function pharmacy_excludes_vca_by_name(): void
    {
        $this->assertFalse($this->passes('pharmacy', 'VCA Animal Hospital', ['pharmacy']));
        $this->assertFalse($this->passes('pharmacy', 'VCA Veterinary Care Clinic', ['health']));
    }

    /** @test */
    public function pharmacy_excludes_bluepearl_by_name(): void
    {
        $this->assertFalse($this->passes('pharmacy', 'BluePearl Specialty + Emergency Pet Hospital', ['pharmacy']));
        $this->assertFalse($this->passes('pharmacy', 'Blue Pearl Emergency Veterinary', ['pharmacy', 'health']));
    }

    /** @test */
    public function pharmacy_excludes_generic_animal_hospital_by_name(): void
    {
        $this->assertFalse($this->passes('pharmacy', 'Sunshine Animal Hospital', ['pharmacy']));
        $this->assertFalse($this->passes('pharmacy', 'Treasure Island Animal Hospital', []));
    }

    /** @test */
    public function pharmacy_excludes_vet_and_veterinary_patterns_by_name(): void
    {
        $this->assertFalse($this->passes('pharmacy', 'Bay Area Vet Clinic', ['health']));
        $this->assertFalse($this->passes('pharmacy', 'Gulf Coast Veterinary Center', ['pharmacy']));
    }

    /** @test */
    public function pharmacy_excludes_pet_pharmacy_and_pet_medication_by_name(): void
    {
        $this->assertFalse($this->passes('pharmacy', 'PetMeds Pet Pharmacy', ['pharmacy']));
        $this->assertFalse($this->passes('pharmacy', 'Animal Pet Medication Services', []));
    }

    /** @test */
    public function pharmacy_allows_legitimate_human_pharmacies(): void
    {
        $this->assertTrue($this->passes('pharmacy', 'Walgreens', ['pharmacy', 'health']));
        $this->assertTrue($this->passes('pharmacy', 'CVS Pharmacy', ['pharmacy']));
        $this->assertTrue($this->passes('pharmacy', 'Publix Pharmacy', ['pharmacy', 'grocery_or_supermarket']));
        $this->assertTrue($this->passes('pharmacy', 'Winn-Dixie Pharmacy', ['pharmacy']));
        $this->assertTrue($this->passes('pharmacy', 'Navarro Pharmacy', ['pharmacy', 'health']));
    }

    // =========================================================================
    // GOLF — entertainment golf venues
    // =========================================================================

    /** @test */
    public function golf_excludes_topgolf_by_name(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Topgolf Tampa', ['amusement_center', 'point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'Topgolf Boca Raton', []));
    }

    /** @test */
    public function golf_excludes_puttshack_by_name(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Puttshack - Miami', ['point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'Puttshack Brickell', []));
    }

    /** @test */
    public function golf_excludes_drive_shack_by_name(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Drive Shack Orlando', ['amusement_center']));
        $this->assertFalse($this->passes('golf_course', 'Drive Shack Lake Nona', []));
    }

    /** @test */
    public function golf_excludes_popstroke_by_name(): void
    {
        $this->assertFalse($this->passes('golf_course', 'PopStroke St. Petersburg', ['point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'PopStroke Fort Myers', []));
    }

    /** @test */
    public function golf_excludes_mini_golf_and_adventure_golf_by_name(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Congo River Mini Golf', ['amusement_center']));
        $this->assertFalse($this->passes('golf_course', 'Paradise Miniature Golf', []));
        $this->assertFalse($this->passes('golf_course', 'Smugglers Cove Adventure Golf', ['amusement_center']));
        $this->assertFalse($this->passes('golf_course', 'Putt-Putt Fun Center', []));
    }

    /** @test */
    public function golf_excludes_entertainment_golf_name_patterns(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Golf & Entertainment Complex', ['amusement_center']));
        $this->assertFalse($this->passes('golf_course', 'Entertainment Golf Venue', []));
    }

    /** @test */
    public function golf_allows_legitimate_regulation_golf_courses(): void
    {
        $this->assertTrue($this->passes('golf_course', 'Innisbrook Resort Golf Club', ['golf_course', 'point_of_interest']));
        $this->assertTrue($this->passes('golf_course', 'Bayou Club Golf Course', ['golf_course']));
        $this->assertTrue($this->passes('golf_course', 'TPC Tampa Bay', ['golf_course']));
        $this->assertTrue($this->passes('golf_course', 'Seminole Lake Country Club', ['golf_course', 'point_of_interest']));
        $this->assertTrue($this->passes('golf_course', 'Pelican Golf Club', ['golf_course']));
    }

    // =========================================================================
    // TRANSIT — retail store false positives
    // =========================================================================

    /** @test */
    public function transit_excludes_pharmacy_typed_result(): void
    {
        $this->assertFalse($this->passes('transit_station', 'Walgreens 524 Jefferson Ave', ['transit_station', 'pharmacy', 'health']));
        $this->assertFalse($this->passes('transit_station', 'CVS Pharmacy', ['transit_station', 'pharmacy']));
    }

    /** @test */
    public function transit_excludes_grocery_typed_result(): void
    {
        $this->assertFalse($this->passes('transit_station', 'Publix #1234', ['transit_station', 'grocery_or_supermarket']));
        $this->assertFalse($this->passes('transit_station', 'Winn-Dixie', ['grocery_or_supermarket']));
    }

    /** @test */
    public function transit_excludes_convenience_store_typed_result(): void
    {
        $this->assertFalse($this->passes('transit_station', '7-Eleven', ['transit_station', 'convenience_store', 'gas_station']));
        $this->assertFalse($this->passes('transit_station', 'Circle K', ['convenience_store']));
    }

    /** @test */
    public function transit_excludes_clothing_store_typed_result(): void
    {
        $this->assertFalse($this->passes('transit_station', 'H&M Bayside', ['transit_station', 'clothing_store']));
    }

    /** @test */
    public function transit_allows_legitimate_transit_stops(): void
    {
        $this->assertTrue($this->passes('transit_station', 'Blind Pass Rd + 87th Ave', ['transit_station', 'bus_station', 'point_of_interest']));
        $this->assertTrue($this->passes('transit_station', 'Government Center Metromover', ['transit_station', 'subway_station', 'point_of_interest']));
        $this->assertTrue($this->passes('transit_station', 'MIA Airport Station', ['transit_station', 'train_station']));
        $this->assertTrue($this->passes('transit_station', 'Park Street @ Bay Blvd', ['transit_station', 'bus_station']));
    }

    // =========================================================================
    // BEACH — lodging and resort false positives
    // =========================================================================

    /** @test */
    public function beach_excludes_lodging_type(): void
    {
        $this->assertFalse($this->passes('beach', 'The Savoy Hotel & Beach Club', ['lodging', 'point_of_interest']));
        $this->assertFalse($this->passes('beach', 'Treasure Island Resort', ['lodging']));
    }

    /** @test */
    public function beach_excludes_hotel_motel_inn_by_name(): void
    {
        $this->assertFalse($this->passes('beach', 'Clearwater Beach Hotel', ['point_of_interest']));
        $this->assertFalse($this->passes('beach', 'Sunset Motel Beach', []));
        $this->assertFalse($this->passes('beach', 'Hilton Beachfront Inn', ['point_of_interest']));
        $this->assertFalse($this->passes('beach', 'Marriott Suites on the Beach', []));
    }

    /** @test */
    public function beach_excludes_resort_by_name(): void
    {
        $this->assertFalse($this->passes('beach', 'Club Med Sandpiper Bay Resort', []));
        $this->assertFalse($this->passes('beach', 'Sandals Emerald Bay', ['point_of_interest']));
        $this->assertFalse($this->passes('beach', 'Hyatt Regency Clearwater Beach Resort', []));
    }

    /** @test */
    public function beach_excludes_vacation_rental_by_name(): void
    {
        // Name-pattern guard: literal "vacation rental" in the name
        $this->assertFalse($this->passes('beach', 'Gulf Breeze Vacation Rental', []));
        $this->assertFalse($this->passes('beach', 'Beachfront Vacation Rental Unit', ['point_of_interest']));
        // Airbnb pattern
        $this->assertFalse($this->passes('beach', 'Beachfront Airbnb Suite', ['point_of_interest']));
        // Google tags vacation rental listings with the lodging type — that is the
        // primary exclusion path for results like "Treasure Island Getaway w/ Private
        // Beach Access!" which don't contain the words "vacation rental" in their name.
        $this->assertFalse($this->passes('beach', 'Treasure Island Getaway w/ Private Beach Access!', ['lodging', 'point_of_interest']));
    }

    /** @test */
    public function beach_excludes_water_park_and_theme_park_by_name(): void
    {
        $this->assertFalse($this->passes('beach', 'Schlitterbahn Water Park', ['amusement_park']));
        $this->assertFalse($this->passes('beach', 'Universal Theme Park', ['amusement_park']));
        $this->assertFalse($this->passes('beach', 'Aquatic Park at Bay Center', ['point_of_interest']));
        $this->assertFalse($this->passes('beach', 'Splash Pad at Coachman Park', ['point_of_interest']));
        $this->assertFalse($this->passes('beach', 'Splash Zone Waterpark', []));
    }

    /** @test */
    public function beach_allows_legitimate_public_beaches(): void
    {
        $this->assertTrue($this->passes('beach', 'Clearwater Beach', ['natural_feature', 'tourist_attraction', 'point_of_interest']));
        $this->assertTrue($this->passes('beach', 'Lummus Park Beach', ['park', 'natural_feature']));
        $this->assertTrue($this->passes('beach', 'Caladesi Island State Park', ['park', 'natural_feature']));
        $this->assertTrue($this->passes('beach', 'Indian Shores Beach', ['natural_feature']));
        $this->assertTrue($this->passes('beach', 'Marjory Stoneman Douglas Beach', ['natural_feature', 'park']));
    }

    // =========================================================================
    // BEACH ACCESS — same lodging/resort guard
    // =========================================================================

    /** @test */
    public function beach_access_excludes_lodging_type(): void
    {
        $this->assertFalse($this->passes('beach_access', 'Marriott Beach Access Point', ['lodging']));
    }

    /** @test */
    public function beach_access_excludes_resort_hotel_by_name(): void
    {
        $this->assertFalse($this->passes('beach_access', 'Sandals Royal Bahamian Resort', ['point_of_interest']));
        $this->assertFalse($this->passes('beach_access', 'Hyatt Regency Beach Access', []));
        $this->assertFalse($this->passes('beach_access', 'Sheraton Beach Access Walk', ['point_of_interest']));
    }

    /** @test */
    public function beach_access_excludes_vacation_rental_by_name(): void
    {
        $this->assertFalse($this->passes('beach_access', 'Private Beach Access — Vacation Rental', []));
    }

    /** @test */
    public function beach_access_allows_legitimate_public_access_points(): void
    {
        $this->assertTrue($this->passes('beach_access', 'Sunset Beach Public Access', ['natural_feature', 'point_of_interest']));
        $this->assertTrue($this->passes('beach_access', 'Pinellas County Beach Access #5', ['point_of_interest']));
        $this->assertTrue($this->passes('beach_access', 'Treasure Island Beach Access Path', ['natural_feature']));
    }

    // =========================================================================
    // HOSPITAL — animal hospitals / veterinary providers must never appear
    // (Location DNA audit Phase 1). Previously vets were only soft-penalized.
    // =========================================================================

    /** @test */
    public function hospital_excludes_veterinary_care_type(): void
    {
        // Type is authoritative — a vet ER carrying veterinary_care is removed even
        // if the word "Hospital" appears in its name.
        $this->assertFalse($this->passes('hospital', 'Bayshore Animal Hospital', ['veterinary_care', 'health']));
        $this->assertFalse($this->passes('hospital', 'Coastal Pet Emergency', ['veterinary_care']));
    }

    /** @test */
    public function hospital_excludes_animal_providers_by_name(): void
    {
        // Name guard for vets that Google does NOT tag with veterinary_care.
        $this->assertFalse($this->passes('hospital', 'Banfield Pet Hospital', ['hospital', 'point_of_interest']));
        $this->assertFalse($this->passes('hospital', 'VCA Gulf Coast Animal Hospital', ['hospital']));
        $this->assertFalse($this->passes('hospital', 'BluePearl Pet ER', ['hospital']));
        $this->assertFalse($this->passes('hospital', 'Westchase Veterinary Clinic', ['hospital']));
        $this->assertFalse($this->passes('hospital', 'Animal Medical Center of Tampa', ['hospital']));
    }

    /** @test */
    public function hospital_still_excludes_medspa_and_aesthetic_clinics(): void
    {
        // Existing behavior must not regress with the vet additions.
        $this->assertFalse($this->passes('hospital', 'Radiance Med Spa', ['hospital', 'spa']));
        $this->assertFalse($this->passes('hospital', 'Drip IV Therapy Lounge', ['hospital']));
    }

    /** @test */
    public function hospital_allows_legitimate_human_hospitals(): void
    {
        $this->assertTrue($this->passes('hospital', 'Tampa General Hospital', ['hospital', 'health', 'point_of_interest']));
        $this->assertTrue($this->passes('hospital', 'AdventHealth North Pinellas', ['hospital', 'health']));
        $this->assertTrue($this->passes('hospital', 'Morton Plant Hospital', ['hospital']));
        // "Veterans" contains the substring "vet" but must NOT be excluded —
        // the regex uses the whole word "veterinary"/"vet clinic", not bare "vet".
        $this->assertTrue($this->passes('hospital', 'James A. Haley Veterans Hospital', ['hospital', 'health']));
    }

    // =========================================================================
    // MARINA — boat dealers / marine retail must never appear as marinas.
    // Previously the marina category had NO exclusion rule at all.
    // =========================================================================

    /** @test */
    public function marina_excludes_car_dealer_type(): void
    {
        $this->assertFalse($this->passes('marina', 'Gulf Coast Boat Sales', ['car_dealer', 'store']));
    }

    /** @test */
    public function marina_excludes_boat_and_marine_dealers_by_name(): void
    {
        $this->assertFalse($this->passes('marina', 'MarineMax Clearwater', ['store', 'point_of_interest']));
        $this->assertFalse($this->passes('marina', 'Tampa Bay Boat Sales', ['store']));
        $this->assertFalse($this->passes('marina', 'Galati Yacht Sales', ['store', 'point_of_interest']));
        $this->assertFalse($this->passes('marina', 'West Marine Supply', ['store']));
        $this->assertFalse($this->passes('marina', 'Suncoast Boat Rental', []));
        $this->assertFalse($this->passes('marina', 'Bluewater Yacht Brokerage', ['point_of_interest']));
    }

    /** @test */
    public function marina_allows_legitimate_marinas(): void
    {
        $this->assertTrue($this->passes('marina', 'Harbour Island Marina', ['marina', 'point_of_interest']));
        $this->assertTrue($this->passes('marina', 'Clearwater Municipal Marina', ['point_of_interest']));
        $this->assertTrue($this->passes('marina', 'Marina Bay Yacht Harbor', ['point_of_interest']));
        $this->assertTrue($this->passes('marina', 'Sailport Waterfront Marina', ['marina']));
    }

    // =========================================================================
    // GOLF — standalone driving ranges excluded, but real courses that include
    // "Driving Range" in their name are preserved (conditional name match).
    // =========================================================================

    /** @test */
    public function golf_excludes_standalone_driving_ranges(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Palm Bay Driving Range', ['point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'Eagle Golf Range', ['point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'TopTracer Range at Bay Center', ['point_of_interest']));
    }

    /** @test */
    public function golf_still_excludes_entertainment_venues(): void
    {
        $this->assertFalse($this->passes('golf_course', 'Topgolf Tampa', ['bowling_alley', 'point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'Adventure Golf & Games', ['point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'PopStroke Sarasota', ['point_of_interest']));
        $this->assertFalse($this->passes('golf_course', 'Smugglers Cove Miniature Golf', ['point_of_interest']));
    }

    /** @test */
    public function golf_allows_real_courses_including_those_with_a_driving_range(): void
    {
        $this->assertTrue($this->passes('golf_course', 'Innisbrook Golf Resort', ['point_of_interest']));
        $this->assertTrue($this->passes('golf_course', 'Pine Hills Golf Club & Driving Range', ['point_of_interest']));
        $this->assertTrue($this->passes('golf_course', 'Cypress Run Golf Course Driving Range', ['point_of_interest']));
        $this->assertTrue($this->passes('golf_course', 'Belleair Country Club', ['point_of_interest']));
        $this->assertTrue($this->passes('golf_course', 'Tarpon Springs Golf Links', ['point_of_interest']));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BOAT RAMP — mirrors marina boat-commerce guard (audit Phase 1)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function boat_ramp_excludes_boat_dealers_and_marine_retail_by_name(): void
    {
        $this->assertFalse($this->passes('boat_ramp', 'MarineMax Clearwater', ['point_of_interest']));
        $this->assertFalse($this->passes('boat_ramp', 'Tampa Boat Sales', ['point_of_interest']));
        $this->assertFalse($this->passes('boat_ramp', 'West Marine Supply', ['store']));
        $this->assertFalse($this->passes('boat_ramp', 'Gulf Coast Boats', ['car_dealer']));
    }

    /** @test */
    public function boat_ramp_allows_legitimate_public_ramps(): void
    {
        $this->assertTrue($this->passes('boat_ramp', 'Maximo Park Boat Ramp', ['point_of_interest']));
        $this->assertTrue($this->passes('boat_ramp', 'Fort De Soto Boat Ramp', ['point_of_interest']));
        $this->assertTrue($this->passes('boat_ramp', 'E.G. Simmons Public Boat Launch', ['point_of_interest']));
    }
}
