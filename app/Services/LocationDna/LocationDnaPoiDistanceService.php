<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Support\Telemetry\OutboundCallContext;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LocationDnaPoiDistanceService — Phase C POI Distance Engine (v2)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the POI proximity layer for the Location DNA pipeline. It reads
 * geocoded coordinates from Phase B (PropertyLocationDna) and queries the Google
 * Places Nearby Search API to find multiple candidates per POI category, persisting
 * up to 10 ranked rows per category to property_location_pois.
 *
 * v2 additions (task #3110):
 *   - Stores up to 10 raw candidates per category (rank 1 = nearest/primary).
 *   - Each row persists rank, rating, user_ratings_total, and types_json.
 *   - Category exclusion filters eliminate confirmed bad matches (P0 audit fixes).
 *   - Top Rated Dining derived category: restaurant candidates sorted by rating
 *     (minimum 10 reviews), stored as rank 1/2/3 under 'top_rated_dining'.
 *
 * v3 additions (task #3200 — cost optimisation):
 *   - Spatial tile cache (LocationDnaPoiTileCache): reuses raw candidates across
 *     nearby listings that fall in the same geographic tile. Opt-in via
 *     LOCATION_DNA_POI_TILE_PRECISION env var; disabled when absent/empty.
 *   - Category grouping: three category pairs share one API call instead of two,
 *     reducing fresh-call volume from 19 to 16 per tile miss.
 *     Pairs: park/waterfront_park, gym/fitness_center, beach/beach_access.
 *   - Per-run stats persisted to location_dna_poi_run_stats for cost reporting.
 *
 * This service MUST NEVER:
 *   - Call the geocoding API or modify Phase B records.
 *   - Connect to the AI marketing report or Property DNA persistence pipelines.
 *   - Perform AI or OpenAI calls of any kind.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute walk/bike/transit/coastal/compatibility/marketing scores.
 *   - Calculate drive times (travel_time_minutes column is reserved for a future phase).
 * ==================================================================================
 */
class LocationDnaPoiDistanceService
{
    private const NEARBY_API_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    private const EARTH_RADIUS_MILES = 3958.8;

    /**
     * Maximum number of raw API results to collect per category.
     * Google Nearby Search returns up to 20 results per page; we cap at 10 for storage.
     */
    private const MAX_CANDIDATES_PER_CATEGORY = 10;

    /**
     * How many raw results to examine when applying exclusion filters.
     * We look at more than MAX_CANDIDATES to ensure we find enough valid ones.
     */
    private const MAX_RESULTS_TO_EXAMINE = 20;

    /**
     * Minimum number of user reviews required to qualify for Top Rated Dining.
     */
    private const TOP_RATED_DINING_MIN_REVIEWS = 10;

    /**
     * Minimum review count used as the confidence denominator in the Top Rated Dining
     * quality-score formula: score = rating × min(reviews / TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS, 1.0).
     * A place must have at least this many reviews for its rating to count at full weight.
     * Places with fewer reviews are down-weighted proportionally, preventing low-sample
     * 5-star outliers from outranking high-confidence 4.8-star restaurants.
     */
    private const TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS = 50;

    /**
     * Maximum number of Top Rated Dining candidates to store.
     */
    private const TOP_RATED_DINING_MAX_CANDIDATES = 3;

    /**
     * All supported POI categories.
     *
     * Each entry maps a canonical category key to the parameters used for a
     * Google Places Nearby Search call. Two query strategies are used:
     *
     * NATIVE TYPE ('query_strategy' => 'native_type')
     *   The `type` parameter is passed directly to the Google Places API.
     *   These are first-class types in Google's taxonomy and return the most
     *   accurate, consistently-ranked results.
     *
     * KEYWORD-BASED ('query_strategy' => 'keyword')
     *   No exact Google Place type exists for these categories. A `keyword`
     *   parameter is sent instead (with an optional `type` hint where it helps
     *   narrow results). Result quality depends on how operators have labelled
     *   their listings and may vary by region.
     *
     * v2 change: fitness_center now uses keyword strategy ('fitness center' keyword
     *   combined with google_type='gym') to differentiate it from 'gym', which was
     *   previously a structural duplicate returning identical results. Audit finding:
     *   Section 8, gym/fitness_center structural duplicate, 🟠 Medium risk.
     *
     * Required keys per entry:
     *   'label'          — human-readable name stored as poi_subtype
     *   'query_strategy' — 'native_type' | 'keyword'
     *   'google_type'    — Google Places type string, or null for pure keyword searches
     *   'keyword'        — keyword string for keyword-based searches, or null for native types
     */
    public const CATEGORIES = [
        // ── Native Google Place type support ─────────────────────────────────
        'grocery_store'   => ['google_type' => 'grocery_or_supermarket', 'keyword' => null,              'label' => 'Grocery Store',   'query_strategy' => 'native_type'],
        'school'          => ['google_type' => 'school',                  'keyword' => null,              'label' => 'School',          'query_strategy' => 'native_type'],
        'hospital'        => ['google_type' => 'hospital',                'keyword' => null,              'label' => 'Hospital',        'query_strategy' => 'native_type'],
        'park'            => ['google_type' => 'park',                    'keyword' => null,              'label' => 'Park',            'query_strategy' => 'native_type'],
        'pharmacy'        => ['google_type' => 'pharmacy',                'keyword' => null,              'label' => 'Pharmacy',        'query_strategy' => 'native_type'],
        'gas_station'     => ['google_type' => 'gas_station',             'keyword' => null,              'label' => 'Gas Station',     'query_strategy' => 'native_type'],
        'restaurant'      => ['google_type' => 'restaurant',              'keyword' => null,              'label' => 'Restaurant',      'query_strategy' => 'native_type'],
        'gym'             => ['google_type' => 'gym',                     'keyword' => null,              'label' => 'Gym',             'query_strategy' => 'native_type'],
        // v2: fitness_center uses keyword 'fitness center' to differentiate from gym.
        // Audit finding: Section 8 — gym/fitness_center structural duplicate.
        'fitness_center'  => ['google_type' => 'gym',                     'keyword' => 'fitness center', 'label' => 'Fitness Center',  'query_strategy' => 'keyword'],
        'transit_station' => ['google_type' => 'transit_station',         'keyword' => null,              'label' => 'Transit Station', 'query_strategy' => 'native_type'],
        'coffee_shop'     => ['google_type' => 'cafe',                    'keyword' => null,              'label' => 'Coffee Shop',     'query_strategy' => 'native_type'],
        'shopping_center' => ['google_type' => 'shopping_mall',           'keyword' => null,              'label' => 'Shopping Center', 'query_strategy' => 'native_type'],

        // ── Keyword-based searches (no native Google Place type available) ───
        // Result quality depends on operator-provided labels and may vary by region.
        'beach'           => ['google_type' => null,   'keyword' => 'beach',         'label' => 'Beach',           'query_strategy' => 'keyword'],
        'beach_access'    => ['google_type' => null,   'keyword' => 'beach access',  'label' => 'Beach Access',    'query_strategy' => 'keyword'],
        'boat_ramp'       => ['google_type' => null,   'keyword' => 'boat ramp',     'label' => 'Boat Ramp',       'query_strategy' => 'keyword'],
        'marina'          => ['google_type' => null,   'keyword' => 'marina',        'label' => 'Marina',          'query_strategy' => 'keyword'],
        'waterfront_park' => ['google_type' => 'park', 'keyword' => 'waterfront',    'label' => 'Waterfront Park', 'query_strategy' => 'keyword'],
        'dog_park'        => ['google_type' => null,   'keyword' => 'dog park',      'label' => 'Dog Park',        'query_strategy' => 'keyword'],
        'golf_course'     => ['google_type' => null,   'keyword' => 'golf course',   'label' => 'Golf Course',     'query_strategy' => 'keyword'],
    ];

    /**
     * Category pairs that share a single Google Places API call (v3 cost optimisation).
     *
     * Key   = primary category  (makes the API call, params defined in CATEGORIES[primary])
     * Value = secondary category (reuses primary's raw candidates; own exclusion filters applied)
     *
     * Rationale per pair:
     *   park / waterfront_park   — both query google_type=park; waterfront_park uses the same
     *                              pool of park results filtered by its own exclusion rules.
     *   gym / fitness_center     — both query google_type=gym; fitness_center differentiates
     *                              via label/rank, not via a separate API result set.
     *   beach / beach_access     — both keyword-search "beach" territory; raw results are shared
     *                              and each category's exclusion rules are applied independently.
     *
     * This reduces fresh calls per listing on a full tile miss from 19 → 16.
     */
    public const CATEGORY_GROUPS = [
        'park'  => 'waterfront_park',
        'gym'   => 'fitness_center',
        'beach' => 'beach_access',
    ];

    /**
     * Maximum distance in miles for a beach/beach_access result to be considered
     * a meaningful nearby beach. Results beyond this threshold are suppressed even
     * if they pass all other exclusion filters, since a beach 20+ miles away
     * carries no lifestyle signal for the listed property.
     */
    private const BEACH_MAX_MEANINGFUL_DISTANCE_MILES = 20.0;

    /**
     * Within this distance (miles), two transit stops with the same name are
     * considered duplicates and the second is suppressed.
     * Approximately 100 metres in miles.
     */
    private const TRANSIT_DEDUP_DISTANCE_MILES = 0.0621;

    private const CATEGORY_EXCLUSION_RULES = [
        'grocery_store' => [
            // ── Grocery Store exclusion — two-part prioritized rule ─────────
            //
            // PRIMARY RULE (types-based, authoritative):
            //   Exclude any candidate whose types_json contains 'gas_station',
            //   regardless of whether it also has 'grocery_or_supermarket'.
            //   Rationale: Google dual-types convenience stores (e.g. BP, Shell,
            //   Wawa, RaceTrac) as both gas_station AND grocery_or_supermarket.
            //   The old `exclude_if_types_include_and_lacks` rule allowed these
            //   dual-typed entries to pass because they do have the grocery type.
            //   Types are the authoritative signal — a gas station is never a
            //   true grocery store regardless of secondary type tags.
            'exclude_if_types_include' => ['gas_station'],

            // FALLBACK RULE (name-pattern, safety net — types-absent only):
            //   When types_json is missing or empty (sparse API response), apply a
            //   brand-name guard against known gas-station / convenience-store chains.
            //   This rule uses `exclude_if_name_matches_when_types_empty`, which is
            //   only evaluated when the `types` array returned by Google is absent or
            //   empty. It MUST NOT override authoritative type data: if Google returned
            //   any types at all, the PRIMARY rule above is the sole arbiter.
            //   Pattern covers: BP, Shell, Chevron, RaceTrac, Wawa, Circle K,
            //   7-Eleven, Sunoco, Murphy USA, Cumberland Farms.
            'exclude_if_name_matches_when_types_empty' => '/\b(bp|shell|chevron|racetrac|wawa|circle\s*k|7-?eleven|sunoco|murphy\s+usa|cumberland\s+farms)\b/i',
        ],
        'pharmacy' => [
            // Exclude if result has veterinary_care type (animal pharmacies /
            // in-house dispensaries at vet clinics).
            'exclude_if_types_include' => ['veterinary_care'],

            // Name-pattern guard for veterinary/animal-care providers that Google
            // may tag as 'pharmacy' without the veterinary_care type.
            // Covers: animal hospital chains (Banfield, VCA, BluePearl),
            // generic animal hospital / pet ER naming, and pet medication providers.
            'exclude_if_name_matches' => '/\b(animal\s+hospital|pet\s+er|emergency\s+animal|banfield|vca\b|bluepearl|blue\s+pearl|pet\s+medication|veterinary|vet\s+clinic|animal\s+care\s+center|animal\s+medical|pet\s+pharmacy)\b/i',
        ],
        'hospital' => [
            // PRIMARY RULE (types-based, authoritative):
            //   Exclude any candidate carrying the 'veterinary_care' type — an animal
            //   hospital is never a human acute-care facility, regardless of the word
            //   "hospital" in its name. Mirrors the pharmacy rule so vet ERs that Google
            //   surfaces under a 'hospital' search are removed, not merely soft-penalized.
            'exclude_if_types_include' => ['veterinary_care'],

            // Exclude cosmetic clinics, aesthetic practices, and wellness spas that
            // Google occasionally surfaces under the 'hospital' type search, plus
            // veterinary / animal-care providers that lack the veterinary_care type.
            // These are not acute-care or primary-care human medical facilities.
            // Covered: MedSpa / Med Spa, IV Therapy / IV Drip / IV Lounge,
            //   Aesthetics / Aesthetic Center, Botox providers (standalone),
            //   Ketamine clinics, Infusion Lounge, Wellness Spa, CryoTherapy,
            //   HydraFacial / Hydra Facial studios, and animal hospitals / vet ERs
            //   (animal hospital, pet ER, emergency animal, Banfield, VCA, BluePearl,
            //   veterinary, vet clinic, animal medical/care center).
            'exclude_if_name_matches' => '/\b(med\s*spa|iv\s+therapy|iv\s+drip|iv\s+lounge|infusion\s+lounge|ketamine|cryotherapy|cryo\s+therapy|hydrafacial|hydra\s+facial|aesthetics?\s+center|aesthetic\s+clinic|botox\s+clinic|wellness\s+spa|beauty\s+lounge|laser\s+clinic|laser\s+aesthetics|animal\s+hospital|pet\s+er|emergency\s+animal|banfield|vca\b|bluepearl|blue\s+pearl|veterinary|vet\s+clinic|animal\s+care\s+center|animal\s+medical)\b/i',
        ],
        'school' => [
            // Exclude non-accredited enrichment and wellness businesses that Google
            // may surface under the 'school' type:
            //   - Life coaches / coaching studios
            //   - Yoga studios / yoga schools
            //   - Music teachers / music instruction (non-school building)
            //   - Swim schools / swim academies (for-profit instruction, not school)
            //   - Tutoring centers (standalone enrichment, not an accredited school)
            //   - Enrichment / learning studios without accredited K-12 status
            //   - Dance studios
            //   - Martial arts / karate / taekwondo dojos
            //   - Art / painting studios marketed as "art school"
            // Deliberately does NOT block: "School of Music" or "School of Dance"
            // in a university context — those contain 'university' in types and
            // would not be caught by name alone; type-preference in the ranking
            // profile handles accredited institutions.
            'exclude_if_name_matches' => '/\b(life\s+coach|coaching\s+studio|yoga\s+studio|yoga\s+school|music\s+(teacher|instructor|lesson|tutor)|swim\s+(school|academy|lesson)|tutoring\s+(center|centre)|learning\s+(center|centre|studio)|enrichment\s+(center|centre|studio)|dance\s+studio|martial\s+arts|karate|taekwondo|jiu.?jitsu|art\s+studio|painting\s+studio|guitar\s+lesson|piano\s+lesson|drum\s+lesson)\b/i',
        ],
        'beach' => [
            // Exclude lodging, hotel chains, and hospitality venues that Google
            // surfaces in beach keyword searches (hotels with "Beach" in their name,
            // resorts, vacation rentals, theme parks, water parks).
            'exclude_if_types_include' => ['lodging'],

            // Name-pattern guard for hospitality and entertainment venues.
            // Covers: Hotel, Motel, Resort, Inn, Suites, Vacation Rental,
            //   Theme Park, Water Park, Aquatic Park, Splash Pad / Splash Zone,
            //   Club Med, Sandals, Marriott, Hilton, Hyatt, etc.
            'exclude_if_name_matches' => '/\b(hotel|motel|resort|inn\b|suites?\b|vacation\s+rental|theme\s+park|water\s+park|aquatic\s+park|splash\s+(pad|zone|park)|club\s+med|sandals\b|marriott|hilton|hyatt|sheraton|westin|doubletree|holiday\s+inn|hampton\s+inn|courtyard|airbnb)\b/i',
        ],
        'beach_access' => [
            // Same exclusion set as 'beach' — access points should never be
            // lodging properties or entertainment parks.
            'exclude_if_types_include' => ['lodging'],
            'exclude_if_name_matches'  => '/\b(hotel|motel|resort|inn\b|suites?\b|vacation\s+rental|theme\s+park|water\s+park|aquatic\s+park|splash\s+(pad|zone|park)|club\s+med|sandals\b|marriott|hilton|hyatt|sheraton|westin|doubletree|holiday\s+inn|hampton\s+inn|courtyard|airbnb)\b/i',
        ],
        'golf_course' => [
            // Exclude entertainment / miniature golf venues and standalone driving ranges.
            // Audit findings + #3176 hardening:
            //   adventure golf, mini golf, miniature golf, putt-putt (existing),
            //   Topgolf, Drive Shack, Puttshack, PopStroke, entertainment + golf.
            // Driving-range handling (Location DNA audit Phase 1):
            //   A standalone driving range is not a golf course, but a real course
            //   frequently includes "Driving Range" in its name (e.g. "Pine Hills
            //   Golf Club & Driving Range"). The final alternation branch therefore
            //   excludes a name containing "driving range" / "golf range" ONLY when it
            //   does NOT also contain a course indicator (golf club/course, country
            //   club, links). "TopTracer" ranges are caught unconditionally.
            'exclude_if_name_matches' => '/adventure\s+golf|mini.?golf|miniature\s+golf|putt.?putt|topgolf|drive\s+shack|puttshack|popstroke|toptracer|entertainment.*golf|golf.*entertainment|^(?!.*\b(?:golf\s+club|golf\s+course|country\s+club|links)\b).*\b(?:driving|golf)\s+range\b/i',
        ],
        'transit_station' => [
            // Exclude retail stores that Google occasionally tags as transit stops.
            // These types are never genuine transit stations.
            'exclude_if_types_include' => [
                'grocery_or_supermarket',
                'pharmacy',
                'convenience_store',
                'clothing_store',
            ],
        ],
        'marina' => [
            // Marina is a keyword search ('marina') and previously had NO exclusion
            // rule, so boat dealers, marine retailers, and brokerages surfaced as
            // marinas (Location DNA audit Phase 1). Google has no dedicated
            // boat-dealer type — dealerships are commonly tagged 'car_dealer' or
            // 'store' — so exclusion is primarily name-based with a type guard.
            //
            // PRIMARY RULE (types-based): a vehicle dealership is never a marina.
            'exclude_if_types_include' => ['car_dealer'],

            // Name-pattern guard for boat/yacht sales, brokerages, marine retail and
            // service businesses. Deliberately does NOT match the bare word "marina",
            // so legitimate names like "Harbour Island Marina" pass.
            // Covers: boat dealer/sales/brokerage/broker, yacht sales/broker/dealer,
            //   marine sales/supply/center/electronics/service, MarineMax, boat rental.
            'exclude_if_name_matches' => '/\b(boat\s+(dealer|sales|brokerage|broker|rental)|yacht\s+(sales|broker|dealer|brokerage)|marine\s+(sales|supply|supplies|center|centre|electronics|service)|marinemax|boat\s+dealership)\b/i',
        ],
        'boat_ramp' => [
            // A 'boat ramp' keyword search surfaces the same boat-commerce false
            // positives as 'marina' — boat/yacht dealerships, marine retailers, and
            // brokerages — none of which are public launch ramps. Mirrors the marina
            // guard exactly (Location DNA audit Phase 1).
            'exclude_if_types_include' => ['car_dealer'],
            'exclude_if_name_matches'  => '/\b(boat\s+(dealer|sales|brokerage|broker|rental)|yacht\s+(sales|broker|dealer|brokerage)|marine\s+(sales|supply|supplies|center|centre|electronics|service)|marinemax|boat\s+dealership)\b/i',
        ],
    ];

    // =========================================================================
    // Per-run stat counters (reset before each calculateForListing() call)
    // =========================================================================

    private int $tileCacheHits     = 0;
    private int $tileCacheMisses   = 0;
    private int $categoriesGrouped = 0;

    /** Last-run stats, set at the end of calculateForListing() for introspection. */
    private array $lastRunStats = [];

    /**
     * Current fetch/scoring version stamps for this run (Stage E0). Set at the top
     * of calculateForListing() and written onto every persisted POI row. Empty
     * only before the first run in a given instance.
     */
    private string $currentFetchVersion   = '';
    private string $currentScoringVersion = '';

    /**
     * Read-only view of the inputs that define the SCORING version
     * (docs/canonical-field-mapping-spec.md §7; Stage E0). These are the rules
     * and constants that change how already-fetched candidates are filtered and
     * ranked — a change here recomputes from cache, never a refetch. Exposed for
     * LocationDnaVersionService::scoringVersion(); does not alter any behavior.
     *
     * @return array{exclusion_rules: array, beach_max_meaningful_distance_miles: float, transit_dedup_distance_miles: float}
     */
    /**
     * Recompute POI rankings for a listing from STORED candidates only — no Google
     * API call (Stage E0; docs/launch-audits/location-dna-architecture-review.md §2).
     *
     * Re-runs the ranking engine over the persisted rows of each standard fetched
     * category, updates their score/rank columns, and re-stamps pois_scoring_version
     * to the current scoring version. Derived categories (e.g. top_rated_dining) are
     * built by a separate path and left intact. Callers rebuild summary_json /
     * lifestyle_json afterward for consistency (see ldna:rerank-all).
     *
     * @return int Number of rows re-scored.
     */
    public function recomputeRankingsFromCache(string $listingType, int $listingId): int
    {
        $rows = PropertyLocationPoi::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $engine          = $this->rankingEngine ?? new LocationDnaRankingEngine();
        $versionService  = $this->versionService ?? new LocationDnaVersionService();
        $scoringVersion  = $versionService->scoringVersion();

        $sourceLat = (float) $rows->first()->source_lat;
        $sourceLng = (float) $rows->first()->source_lng;

        $updated = 0;

        foreach ($rows->groupBy('poi_category') as $category => $categoryRows) {
            if (! array_key_exists($category, self::CATEGORIES)) {
                continue; // derived / non-standard category — leave intact
            }

            $candidates = [];
            foreach ($categoryRows as $row) {
                $candidates[] = [
                    'geometry'           => ['location' => ['lat' => (float) $row->poi_lat, 'lng' => (float) $row->poi_lng]],
                    'types'              => $row->types_json ?? [],
                    'rating'             => $row->rating !== null ? (float) $row->rating : null,
                    'user_ratings_total' => (int) $row->user_ratings_total,
                    'name'               => $row->poi_name ?? '',
                    '_row_id'            => $row->id, // preserved through rankCandidates() array_merge
                ];
            }

            $ranked = $engine->rankCandidates(
                $category,
                PoiCandidate::fromGooglePlaces($candidates),
                $sourceLat,
                $sourceLng,
            );

            $rank = 1;
            foreach ($ranked as $rankedCandidate) {
                $row = $categoryRows->firstWhere('id', $rankedCandidate['_row_id'] ?? null);
                if ($row === null) {
                    continue;
                }

                $scores = $rankedCandidate['_ranking'];
                $row->category_match_score     = $scores['category_match_score'];
                $row->review_confidence_score  = $scores['review_confidence_score'];
                $row->consumer_relevance_score = $scores['consumer_relevance_score'];
                $row->ranking_score            = $scores['ranking_score'];
                $row->ranking_reasons_json     = $scores['ranking_reasons_json'];
                $row->rank                     = $rank++;
                $row->pois_scoring_version     = $scoringVersion;
                $row->save();
                $updated++;
            }
        }

        return $updated;
    }

    public static function scoringInputs(): array
    {
        return [
            'exclusion_rules'                     => self::CATEGORY_EXCLUSION_RULES,
            'beach_max_meaningful_distance_miles' => self::BEACH_MAX_MEANINGFUL_DISTANCE_MILES,
            'transit_dedup_distance_miles'        => self::TRANSIT_DEDUP_DISTANCE_MILES,
        ];
    }

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?LocationDnaAuditService $auditService = null,
        private readonly ?LocationDnaRankingEngine $rankingEngine = null,
        private readonly ?LocationDnaPoiTileCache $tileCache = null,
        private readonly ?LocationDnaVersionService $versionService = null,
    ) {}

    /**
     * Resolve the outbound HTTP client from the service container so tests can
     * bind a fake/blocking client.
     *
     * Phase 0 / S1b: the former `new Client()` fallback is removed. A bare client
     * cannot be intercepted by Http::fake() or by the container binding, which is
     * how the test suite reached live Google. If the binding is absent we now fail
     * loudly rather than silently opening a socket.
     */
    private function resolveHttpClient(): ClientInterface
    {
        return app(ClientInterface::class);
    }

    /**
     * Calculate and persist POI distances for a listing.
     *
     * Reads geocoded coordinates from the Phase B PropertyLocationDna record and
     * queries the Google Places Nearby Search API for up to 10 candidates per
     * category. All candidates are persisted to property_location_pois with rank,
     * rating, user_ratings_total, and types_json.
     *
     * A derived 'top_rated_dining' category is built from restaurant candidates
     * sorted by rating (minimum 10 reviews).
     *
     * Cache behaviour:
     *   - If existing rows match the current geocoded_lat/lng, no API call is made
     *     and cached rows are returned with status='cached'.
     *   - If coordinates changed, all existing rows are deleted and all categories
     *     are recalculated.
     *
     * Tile cache (v3):
     *   - When LOCATION_DNA_POI_TILE_PRECISION is set, raw candidates for each
     *     (tile, google_type, keyword) tuple are cached in Laravel Cache.
     *   - Nearby listings that fall in the same tile reuse raw candidates
     *     without a fresh Google Places call.
     *
     * Category grouping (v3):
     *   - CATEGORY_GROUPS pairs share one API call. The primary category fetches;
     *     the secondary reuses the raw candidates with its own exclusion filters.
     *
     * A single failed category does not abort the run. Each row independently
     * stores its status ('found' | 'not_found' | 'error') and error message.
     *
     * @param  string $listingType  The listing model type (e.g. 'seller_agent_auction').
     * @param  int    $listingId    The primary key of the listing record.
     * @return array                Approved Phase C eight-key output contract.
     */
    public function calculateForListing(string $listingType, int $listingId): array
    {
        // Phase 0 / S3a: attribute any outbound Google request made during this run
        // to the listing that provoked it. Read only by telemetry; never by behaviour.
        OutboundCallContext::for($listingType, $listingId);

        // Reset per-run counters
        $this->tileCacheHits     = 0;
        $this->tileCacheMisses   = 0;
        $this->categoriesGrouped = 0;

        // Current version stamps for this run (Stage E0). Computed once; written
        // onto every persisted row and compared against stored rows to decide
        // whether cached candidates are still valid for the active fetch surface.
        $versionService = $this->versionService ?? new LocationDnaVersionService();
        $this->currentFetchVersion   = $versionService->fetchVersion();
        $this->currentScoringVersion = $versionService->scoringVersion();

        try {
            // (a) Validate: Phase B record must exist
            $dnaRecord = PropertyLocationDna::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->first();

            if ($dnaRecord === null) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'No PropertyLocationDna record found for this listing',
                );
                $this->audit($listingType, $listingId, $output);
                $this->setLastRunStats($listingType, $listingId, null);
                return $output;
            }

            // (b) Validate: record must have geocode_status === 'geocoded'
            if ($dnaRecord->geocode_status !== 'geocoded') {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    "PropertyLocationDna geocode_status is '{$dnaRecord->geocode_status}', expected 'geocoded'",
                );
                $this->audit($listingType, $listingId, $output);
                $this->setLastRunStats($listingType, $listingId, null);
                return $output;
            }

            // (c) Validate: coordinates must be present
            if (blank($dnaRecord->geocoded_lat) || blank($dnaRecord->geocoded_lng)) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'PropertyLocationDna record is missing geocoded coordinates',
                );
                $this->audit($listingType, $listingId, $output);
                $this->setLastRunStats($listingType, $listingId, null);
                return $output;
            }

            $sourceLat = (float) $dnaRecord->geocoded_lat;
            $sourceLng = (float) $dnaRecord->geocoded_lng;

            // (d) Cache check: existing rows with matching source coordinates.
            // When coordinates match, current exclusion rules are re-applied against
            // cached rank-1 rows so that rule improvements made after the initial
            // fetch are honoured. Categories whose rank-1 still passes are reused;
            // categories whose rank-1 now fails are deleted and re-fetched below.
            $existingRows = PropertyLocationPoi::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->get();

            // Categories to skip in the fetch loop (their cached rows are clean).
            // Empty on a full run; populated only when a partial cache-invalidation
            // occurs (some categories stale, others still valid).
            $cachedCategoriesToSkip = [];

            if ($existingRows->isNotEmpty()) {
                $firstRow = $existingRows->first();
                $cachedLat = (float) $firstRow->source_lat;
                $cachedLng = (float) $firstRow->source_lng;

                // Stage E0: stored candidates are only reusable when they were
                // fetched under the SAME fetch surface (category defs, groups,
                // radius, provider set). A fetch-version mismatch means new query
                // params/providers could surface candidates never stored, so it is
                // treated exactly like a coordinate change — full delete + refetch.
                // NULL (un-stamped) rows are treated as stale by design (explicit
                // ldna:stamp-versions backfill, not NULL-grandfathering).
                $fetchVersionMatches =
                    ((string) $firstRow->pois_fetch_version) === $this->currentFetchVersion;

                if (
                    abs($cachedLat - $sourceLat) < 0.0000001 &&
                    abs($cachedLng - $sourceLng) < 0.0000001 &&
                    $fetchVersionMatches
                ) {
                    // Re-apply current exclusion rules per category.
                    // Only the rank-1 row is inspected — it is the primary result.
                    // Derived categories (e.g. top_rated_dining) are not in CATEGORIES
                    // and are skipped here; they are rebuilt when restaurant is refetched.
                    $rowsByCategory  = $existingRows->groupBy('poi_category');
                    $staleCategories = [];

                    foreach ($rowsByCategory as $category => $categoryRows) {
                        if (! array_key_exists($category, self::CATEGORIES)) {
                            continue;
                        }

                        $rank1 = $categoryRows->firstWhere('rank', 1);
                        if ($rank1 === null) {
                            continue;
                        }

                        // Build a synthetic Google Places-format array for the filter.
                        // types_json is cast to array|null by the model; coerce null → []
                        // so that name-pattern fallback rules (exclude_if_name_matches_when_types_empty)
                        // fire correctly on rows written before the ranking engine was deployed.
                        $syntheticPlace = [
                            'name'  => $rank1->poi_name ?? '',
                            'types' => $rank1->types_json ?? [],
                        ];

                        if (! $this->passesExclusionFilter($category, $syntheticPlace)) {
                            // Delete all rows for this category — they will be re-fetched below.
                            PropertyLocationPoi::where('listing_type', $listingType)
                                ->where('listing_id', $listingId)
                                ->where('poi_category', $category)
                                ->delete();
                            $staleCategories[] = $category;
                        }
                    }

                    // Also detect CATEGORIES that have no rows at all (e.g. previously deleted
                    // by ldna:backfill-exclusions). These are invisible to the loop above but
                    // must be treated as stale so they are re-fetched below.
                    $presentCategories = array_keys($rowsByCategory->toArray());
                    foreach (array_keys(self::CATEGORIES) as $category) {
                        if (! in_array($category, $presentCategories, true)) {
                            $staleCategories[] = $category;
                        }
                    }

                    if (empty($staleCategories)) {
                        // All cached categories pass current exclusion rules — return as-is.
                        $output = $this->completedOutput(
                            $listingType,
                            $listingId,
                            $sourceLat,
                            $sourceLng,
                            $existingRows->toArray(),
                            'cached',
                        );
                        $this->audit($listingType, $listingId, $output);
                        $this->setLastRunStats($listingType, $listingId, null);
                        return $output;
                    }

                    // Some categories were stale and deleted. Mark the clean ones to skip
                    // in the fetch loop so we only re-fetch the affected categories.
                    $cachedCategoriesToSkip = array_values(array_diff(
                        array_keys(self::CATEGORIES),
                        $staleCategories,
                    ));
                } else {
                    // Coordinates changed OR fetch-version changed (provider/category/
                    // radius surface differs) — clear all existing rows for a full refetch.
                    PropertyLocationPoi::where('listing_type', $listingType)
                        ->where('listing_id', $listingId)
                        ->delete();
                }
            }

            // (e0) Phase 0 / S2 — master kill switch. Short-circuits before any HTTP
            // call, and after the cache-return paths above so cached rows still serve.
            // Fail-safe default is DISABLED (config/google_places.php). Until this
            // commit the switch was referenced by zero application code.
            if (! config('google_places.enabled', false)) {
                $output = $this->failedOutput(
                    $listingType,
                    $listingId,
                    $sourceLat,
                    $sourceLng,
                    'google_places_disabled',
                );
                $this->audit($listingType, $listingId, $output);
                $this->setLastRunStats($listingType, $listingId, null);
                return $output;
            }

            // (e) API key guard
            $apiKey = config('services.google.places_key');
            if (blank($apiKey)) {
                $output = $this->failedOutput(
                    $listingType,
                    $listingId,
                    $sourceLat,
                    $sourceLng,
                    'missing_google_api_key',
                );
                $this->audit($listingType, $listingId, $output);
                $this->setLastRunStats($listingType, $listingId, null);
                return $output;
            }

            // Resolve from the container (bound in AppServiceProvider) so tests can
            // inject a fake/blocking client. There is no bare-client fallback.
            $client  = $this->httpClient ?? $this->resolveHttpClient();
            $results = [];

            // Capture restaurant raw candidates for Top Rated Dining derivation.
            $restaurantRawCandidates = [];

            // Preloaded raw candidates for secondary categories in CATEGORY_GROUPS.
            // Populated when the primary category is processed.
            $groupedRawCandidates = [];

            // Seed results with still-valid cached rows from a partial invalidation.
            // These rows were not deleted above; include their arrays in the output now.
            // top_rated_dining is excluded here — it is handled in step (g) below.
            if (! empty($cachedCategoriesToSkip)) {
                $stillCachedRows = PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $listingId)
                    ->whereIn('poi_category', $cachedCategoriesToSkip)
                    ->get();

                foreach ($stillCachedRows as $row) {
                    $results[] = $row->toArray();
                }
            }

            // (f) Query Google Places for each category.
            // Primary categories in CATEGORY_GROUPS fetch once and supply their raw
            // candidates to the corresponding secondary category.
            // Categories in $cachedCategoriesToSkip are still valid — skip them.
            foreach (self::CATEGORIES as $category => $meta) {
                if (in_array($category, $cachedCategoriesToSkip, true)) {
                    continue;
                }

                // Determine if this category has preloaded candidates from a group primary
                $preloaded = $groupedRawCandidates[$category] ?? null;

                if ($preloaded !== null) {
                    // Secondary category: track as grouped (no API call)
                    $this->categoriesGrouped++;
                }

                [$categoryRows, $rawCandidates] = $this->fetchAndPersistCategoryMulti(
                    client:                $client,
                    apiKey:                $apiKey,
                    listingType:           $listingType,
                    listingId:             $listingId,
                    category:              $category,
                    meta:                  $meta,
                    sourceLat:             $sourceLat,
                    sourceLng:             $sourceLng,
                    preloadedRawCandidates: $preloaded,
                );

                foreach ($categoryRows as $row) {
                    $results[] = $row;
                }

                if ($category === 'restaurant') {
                    $restaurantRawCandidates = $rawCandidates;
                }

                // If this category is a primary in CATEGORY_GROUPS, store its raw
                // candidates so the secondary can reuse them without an API call.
                if (isset(self::CATEGORY_GROUPS[$category])) {
                    $secondaryCategory = self::CATEGORY_GROUPS[$category];
                    $groupedRawCandidates[$secondaryCategory] = $rawCandidates;
                }
            }

            // (g) Derive and persist Top Rated Dining from restaurant candidates.
            // If restaurant was not re-fetched (it is in cachedCategoriesToSkip), the
            // existing top_rated_dining rows in DB are still valid — include them in the
            // output without re-deriving (no API call was made for restaurant).
            if (in_array('restaurant', $cachedCategoriesToSkip, true)) {
                $existingTopRated = PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $listingId)
                    ->where('poi_category', 'top_rated_dining')
                    ->get();

                foreach ($existingTopRated as $row) {
                    $results[] = $row->toArray();
                }
            } else {
                $topRatedRows = $this->deriveAndPersistTopRatedDining(
                    listingType:          $listingType,
                    listingId:            $listingId,
                    sourceLat:            $sourceLat,
                    sourceLng:            $sourceLng,
                    restaurantCandidates: $restaurantRawCandidates,
                );

                foreach ($topRatedRows as $row) {
                    $results[] = $row;
                }
            }

            $output = $this->completedOutput(
                $listingType,
                $listingId,
                $sourceLat,
                $sourceLng,
                $results,
                'completed',
            );
            $this->audit($listingType, $listingId, $output);
            $this->setLastRunStats($listingType, $listingId, $sourceLat);
            $this->persistRunStats($listingType, $listingId);
            return $output;

        } catch (Throwable $e) {
            $output = $this->failedOutput(
                $listingType,
                $listingId,
                null,
                null,
                $e->getMessage(),
            );
            $this->audit($listingType, $listingId, $output);
            $this->setLastRunStats($listingType, $listingId, null);
            return $output;
        }
    }

    /**
     * Return stats from the most recent calculateForListing() call.
     *
     * Keys: categories_fetched_fresh, categories_from_tile_cache, categories_grouped,
     *       precision_used.
     *
     * @return array
     */
    public function getLastRunStats(): array
    {
        return $this->lastRunStats;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Write an audit row. Wrapped in its own try/catch so a failure cannot
     * prevent the caller's return value from being delivered.
     */
    private function audit(string $listingType, int $listingId, array $output): void
    {
        try {
            $auditService = $this->auditService ?? new LocationDnaAuditService();
            $auditService->record(
                listingType:    $listingType,
                listingId:      $listingId,
                eventType:      'poi_distance',
                status:         $output['status'],
                source:         null,
                inputSnapshot:  ['listing_type' => $listingType, 'listing_id' => $listingId],
                outputSnapshot: $output,
                error:          $output['error'] ?? null,
            );
        } catch (Throwable) {
            // Audit failure must never alter the service's return value.
        }
    }

    /**
     * Set lastRunStats from the current counter state.
     *
     * @param  float|null $sourceLat  Listing latitude — used only to confirm a real run happened.
     */
    private function setLastRunStats(string $listingType, int $listingId, ?float $sourceLat): void
    {
        $tileCache     = $this->tileCache ?? new LocationDnaPoiTileCache();
        $precisionUsed = $tileCache->isEnabled() ? $tileCache->getPrecision() : null;

        $this->lastRunStats = [
            'listing_type'             => $listingType,
            'listing_id'               => $listingId,
            'categories_fetched_fresh' => $this->tileCacheMisses,
            'categories_from_tile_cache' => $this->tileCacheHits,
            'categories_grouped'       => $this->categoriesGrouped,
            'precision_used'           => $precisionUsed,
        ];
    }

    /**
     * Persist per-run stats to location_dna_poi_run_stats.
     * Wrapped in try/catch so a write failure never blocks a DNA run.
     */
    private function persistRunStats(string $listingType, int $listingId): void
    {
        try {
            $tileCache     = $this->tileCache ?? new LocationDnaPoiTileCache();
            $precisionUsed = $tileCache->isEnabled() ? $tileCache->getPrecision() : null;

            DB::table('location_dna_poi_run_stats')->insert([
                'listing_type'               => $listingType,
                'listing_id'                 => $listingId,
                'categories_fetched_fresh'   => $this->tileCacheMisses,
                'categories_from_tile_cache' => $this->tileCacheHits,
                'categories_grouped'         => $this->categoriesGrouped,
                'precision_used'             => $precisionUsed,
                'run_at'                     => now()->toDateTimeString(),
            ]);
        } catch (Throwable) {
            // Stats write failure must never block a DNA run.
        }
    }

    /**
     * Fetch raw candidates from the Google Places Nearby Search API.
     *
     * Checks the spatial tile cache before making an HTTP call.
     * On a tile cache hit, returns cached candidates and increments $tileCacheHits.
     * On a tile cache miss, calls the API, caches the result, and increments $tileCacheMisses.
     *
     * Exceptions propagate to the caller; fetchAndPersistCategoryMulti handles them
     * by writing an 'error' row so the run can continue with other categories.
     *
     * @return array  Raw Google Places results array (up to 20 entries).
     */
    private function fetchRawCandidates(
        ClientInterface $client,
        string          $apiKey,
        array           $meta,
        float           $sourceLat,
        float           $sourceLng,
    ): array {
        // Resolve tile cache (use injected or create on demand)
        $tileCache = $this->tileCache ?? new LocationDnaPoiTileCache();

        // ── Tile cache check ──────────────────────────────────────────────────
        if ($tileCache->isEnabled()) {
            $tileKey = $tileCache->buildKey($meta, $sourceLat, $sourceLng);
            $cached  = $tileCache->get($tileKey);

            if ($cached !== null) {
                $this->tileCacheHits++;
                return $cached;
            }
        }

        // ── Google Places Nearby Search call ──────────────────────────────────
        $queryParams = [
            'location' => "{$sourceLat},{$sourceLng}",
            'rankby'   => 'distance',
            'key'      => $apiKey,
        ];

        if (! empty($meta['google_type'])) {
            $queryParams['type'] = $meta['google_type'];
        }

        if (! empty($meta['keyword'])) {
            $queryParams['keyword'] = $meta['keyword'];
        }

        $response = $client->request('GET', self::NEARBY_API_URL, [
            'query' => $queryParams,
        ]);

        $body = json_decode((string) $response->getBody(), true);

        $results = $body['results'] ?? [];

        // ── Store in tile cache ───────────────────────────────────────────────
        if ($tileCache->isEnabled()) {
            $tileCache->put($tileKey, $results);
        }

        $this->tileCacheMisses++;

        return $results;
    }

    /**
     * Determine whether a Google Places result passes the exclusion filter
     * for the given category.
     *
     * @param  string $category  Canonical category key from CATEGORIES.
     * @param  array  $place     A single Google Places result object.
     * @return bool              true = keep, false = skip.
     */
    public function passesExclusionFilter(string $category, array $place): bool
    {
        $rules = self::CATEGORY_EXCLUSION_RULES[$category] ?? null;
        if ($rules === null) {
            return true;
        }

        // Coerce types to an empty array when null or absent. This handles two cases:
        //   (1) Sparse Google API responses where 'types' is missing.
        //   (2) Cached DB rows with types_json = NULL (written before the ranking engine
        //       persisted types). An empty array correctly allows exclude_if_types_include
        //       rules to produce no match while still enabling name-pattern fallbacks
        //       (exclude_if_name_matches and exclude_if_name_matches_when_types_empty).
        $types = $place['types'] ?? [];
        $name  = $place['name'] ?? '';

        // Rule: exclude_if_types_include — discard if ANY of these types are present
        if (! empty($rules['exclude_if_types_include'])) {
            foreach ($rules['exclude_if_types_include'] as $badType) {
                if (in_array($badType, $types, true)) {
                    return false;
                }
            }
        }

        // Rule: exclude_if_types_include_and_lacks — discard if has ALL of 'has' types
        //       AND lacks ALL of 'lacks' types
        if (! empty($rules['exclude_if_types_include_and_lacks'])) {
            $hasCheck   = $rules['exclude_if_types_include_and_lacks']['has']   ?? [];
            $lacksCheck = $rules['exclude_if_types_include_and_lacks']['lacks'] ?? [];

            $hasAllBadTypes   = ! empty($hasCheck) && count(array_intersect($hasCheck, $types)) > 0;
            $lacksAllGoodTypes = ! empty($lacksCheck) && count(array_intersect($lacksCheck, $types)) === 0;

            if ($hasAllBadTypes && $lacksAllGoodTypes) {
                return false;
            }
        }

        // Rule: exclude_if_name_matches — discard if name matches the regex (unconditional)
        if (! empty($rules['exclude_if_name_matches'])) {
            if (preg_match($rules['exclude_if_name_matches'], $name)) {
                return false;
            }
        }

        // Rule: exclude_if_name_matches_when_types_empty — name-pattern fallback that fires
        //       ONLY when Google returned no types (sparse/incomplete API response).
        //       When types are present, the type-based rules above are authoritative and
        //       this fallback is skipped entirely, preventing false positives on stores that
        //       legitimately share a brand name with a gas-station chain (e.g. "BP's Market").
        if (! empty($rules['exclude_if_name_matches_when_types_empty']) && empty($types)) {
            if (preg_match($rules['exclude_if_name_matches_when_types_empty'], $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch raw candidates for a category, apply exclusion filters, and persist
     * up to MAX_CANDIDATES_PER_CATEGORY ranked rows.
     *
     * When $preloadedRawCandidates is supplied, the API call is skipped entirely
     * and the preloaded data is used instead. This is used by secondary categories
     * in CATEGORY_GROUPS (e.g. waterfront_park reuses park's raw candidates).
     *
     * Returns a two-element tuple:
     *   [0] array  — persisted row arrays for this category
     *   [1] array  — all raw Google Places results (unfiltered), for downstream use
     *
     * Atomicity: existing rows for (listing_type, listing_id, poi_category) are
     * deleted before new rows are inserted (per task migration safety spec).
     *
     * Never throws — errors are stored as status='error' on rank-1 row.
     */
    private function fetchAndPersistCategoryMulti(
        ClientInterface $client,
        string          $apiKey,
        string          $listingType,
        int             $listingId,
        string          $category,
        array           $meta,
        float           $sourceLat,
        float           $sourceLng,
        ?array          $preloadedRawCandidates = null,
    ): array {
        try {
            // Delete existing rows for this category (atomic replacement)
            PropertyLocationPoi::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->where('poi_category', $category)
                ->delete();

            // Use preloaded candidates (grouped) or fetch fresh from API/tile cache
            $rawCandidates = ($preloadedRawCandidates !== null)
                ? $preloadedRawCandidates
                : $this->fetchRawCandidates($client, $apiKey, $meta, $sourceLat, $sourceLng);

            if (empty($rawCandidates)) {
                $row = $this->createPoiRow(
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                    rank:        1,
                    status:      'not_found',
                    error:       'Google Places returned zero results for this category',
                );
                return [[$row->toArray()], []];
            }

            // Transit deduplication: collapse stops at the same location or with
            // the same name within 100 m before applying other filters.
            $candidatesToExamine = ($category === 'transit_station')
                ? $this->deduplicateTransitCandidates($rawCandidates, $sourceLat, $sourceLng)
                : $rawCandidates;

            // Apply exclusion filters to collect valid candidates
            $validCandidates = [];
            $examined        = 0;

            foreach ($candidatesToExamine as $place) {
                if ($examined >= self::MAX_RESULTS_TO_EXAMINE) {
                    break;
                }
                $examined++;

                if (! $this->passesExclusionFilter($category, $place)) {
                    continue;
                }

                $validCandidates[] = $place;

                if (count($validCandidates) >= self::MAX_CANDIDATES_PER_CATEGORY) {
                    break;
                }
            }

            // All results were filtered out by exclusion rules
            if (empty($validCandidates)) {
                $row = $this->createPoiRow(
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                    rank:        1,
                    status:      'not_found',
                    error:       'All candidates were excluded by category quality filters',
                );
                return [[$row->toArray()], $rawCandidates];
            }

            // Beach / beach_access distance suppression: if the nearest valid result
            // is beyond the meaningful-beach threshold, suppress the entire category.
            // This prevents inland locations (Orlando, Ocala) from receiving a beach
            // result where the nearest "beach" is a far-away coastal town.
            if ($category === 'beach' || $category === 'beach_access') {
                $nearestBeachMiles = null;
                foreach ($validCandidates as $place) {
                    $pLat = (float) ($place['geometry']['location']['lat'] ?? 0);
                    $pLng = (float) ($place['geometry']['location']['lng'] ?? 0);
                    $d = $this->haversineDistanceMiles($sourceLat, $sourceLng, $pLat, $pLng);
                    if ($nearestBeachMiles === null || $d < $nearestBeachMiles) {
                        $nearestBeachMiles = $d;
                    }
                }

                if ($nearestBeachMiles !== null && $nearestBeachMiles > self::BEACH_MAX_MEANINGFUL_DISTANCE_MILES) {
                    $row = $this->createPoiRow(
                        listingType: $listingType,
                        listingId:   $listingId,
                        category:    $category,
                        meta:        $meta,
                        sourceLat:   $sourceLat,
                        sourceLng:   $sourceLng,
                        rank:        1,
                        status:      'not_found',
                        error:       'No meaningful beach found within ' . self::BEACH_MAX_MEANINGFUL_DISTANCE_MILES . ' miles (nearest was ' . round($nearestBeachMiles, 1) . ' miles)',
                    );
                    return [[$row->toArray()], $rawCandidates];
                }
            }

            // Pass valid candidates through the ranking engine.
            // rankCandidates() returns them sorted by ranking_score descending
            // so rank 1 = highest consumer-relevance score, not nearest distance.
            $engine = $this->rankingEngine ?? new LocationDnaRankingEngine();
            $rankedCandidates = $engine->rankCandidates(
                $category,
                PoiCandidate::fromGooglePlaces($validCandidates),
                $sourceLat,
                $sourceLng,
            );

            // Hospital allowlist enforcement: any candidate that carries a legitimate
            // acute-care type (hospital, emergency_room, medical_center, urgent_care,
            // doctor) must appear before specialist-only or aesthetic candidates, even
            // if the ranking engine scored the specialist higher (e.g. a specialist
            // 0.06 mi away with 4.9★ outscoring a real hospital 1.3 mi away).
            // The ranking-engine order is preserved within each group.
            if ($category === 'hospital') {
                $rankedCandidates = $this->prioritizeLegitimateHospitalCandidates($rankedCandidates);
            }

            // Persist in ranking order
            $persistedRows = [];
            foreach ($rankedCandidates as $rank => $place) {
                $rankNumber    = $rank + 1;
                $poiLat        = (float) ($place['geometry']['location']['lat'] ?? 0);
                $poiLng        = (float) ($place['geometry']['location']['lng'] ?? 0);
                $distanceMiles = $this->haversineDistanceMiles($sourceLat, $sourceLng, $poiLat, $poiLng);
                $scoring       = $place['_ranking'] ?? [];

                $row = $this->createPoiRow(
                    listingType:          $listingType,
                    listingId:            $listingId,
                    category:             $category,
                    meta:                 $meta,
                    sourceLat:            $sourceLat,
                    sourceLng:            $sourceLng,
                    rank:                 $rankNumber,
                    status:               'found',
                    error:                null,
                    poiName:              $place['name'] ?? null,
                    poiAddress:           $place['vicinity'] ?? null,
                    poiLat:               $poiLat,
                    poiLng:               $poiLng,
                    distanceMiles:        $distanceMiles,
                    rating:               isset($place['rating']) ? (float) $place['rating'] : null,
                    userRatingsTotal:     isset($place['user_ratings_total']) ? (int) $place['user_ratings_total'] : null,
                    typesJson:            $place['types'] ?? null,
                    categoryMatchScore:   $scoring['category_match_score'] ?? null,
                    consumerRelevance:    $scoring['consumer_relevance_score'] ?? null,
                    reviewConfidence:     $scoring['review_confidence_score'] ?? null,
                    rankingScore:         $scoring['ranking_score'] ?? null,
                    rankingReasons:       $scoring['ranking_reasons_json'] ?? null,
                );

                $persistedRows[] = $row->toArray();
            }

            return [$persistedRows, $rawCandidates];

        } catch (Throwable $e) {
            try {
                PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $listingId)
                    ->where('poi_category', $category)
                    ->delete();

                $row = $this->createPoiRow(
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                    rank:        1,
                    status:      'error',
                    error:       $e->getMessage(),
                );
                return [[$row->toArray()], []];
            } catch (Throwable) {
                return [[], []];
            }
        }
    }

    /**
     * Derive and persist the 'top_rated_dining' category from raw restaurant candidates.
     *
     * Filters restaurant candidates to those with >= TOP_RATED_DINING_MIN_REVIEWS reviews,
     * sorts by rating descending, and persists up to TOP_RATED_DINING_MAX_CANDIDATES rows
     * ranked 1/2/3 under the 'top_rated_dining' category key.
     *
     * This is a derived category — no additional API call is made.
     *
     * @param  array  $restaurantCandidates  Raw Google Places results from the restaurant query.
     * @return array  Array of persisted row arrays.
     */
    private function deriveAndPersistTopRatedDining(
        string $listingType,
        int    $listingId,
        float  $sourceLat,
        float  $sourceLng,
        array  $restaurantCandidates,
    ): array {
        // Atomic replacement: delete existing top_rated_dining rows
        PropertyLocationPoi::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('poi_category', 'top_rated_dining')
            ->delete();

        $topRatedMeta = [
            'label'          => 'Top Rated Dining',
            'query_strategy' => 'derived',
            'google_type'    => null,
            'keyword'        => null,
        ];

        if (empty($restaurantCandidates)) {
            $row = $this->createPoiRow(
                listingType: $listingType,
                listingId:   $listingId,
                category:    'top_rated_dining',
                meta:        $topRatedMeta,
                sourceLat:   $sourceLat,
                sourceLng:   $sourceLng,
                rank:        1,
                status:      'not_found',
                error:       'No restaurant candidates available to derive Top Rated Dining',
            );
            return [[$row->toArray()]];
        }

        // Filter: minimum review threshold
        $qualified = array_filter(
            $restaurantCandidates,
            fn($place) => ($place['user_ratings_total'] ?? 0) >= self::TOP_RATED_DINING_MIN_REVIEWS,
        );

        if (empty($qualified)) {
            $row = $this->createPoiRow(
                listingType: $listingType,
                listingId:   $listingId,
                category:    'top_rated_dining',
                meta:        $topRatedMeta,
                sourceLat:   $sourceLat,
                sourceLng:   $sourceLng,
                rank:        1,
                status:      'not_found',
                error:       'No qualifying restaurants found (minimum ' . self::TOP_RATED_DINING_MIN_REVIEWS . ' reviews required)',
            );
            return [[$row->toArray()]];
        }

        // Pass qualified restaurant candidates through the ranking engine
        // (top_rated_dining profile gives extra weight to review confidence).
        // rankCandidates() returns them sorted by ranking_score descending.
        $engine = $this->rankingEngine ?? new LocationDnaRankingEngine();
        $rankedQualified = $engine->rankCandidates(
            'top_rated_dining',
            PoiCandidate::fromGooglePlaces(array_values($qualified)),
            $sourceLat,
            $sourceLng,
        );

        $top  = array_slice($rankedQualified, 0, self::TOP_RATED_DINING_MAX_CANDIDATES);
        $rows = [];

        foreach ($top as $index => $place) {
            $rank          = $index + 1;
            $poiLat        = (float) ($place['geometry']['location']['lat'] ?? 0);
            $poiLng        = (float) ($place['geometry']['location']['lng'] ?? 0);
            $distanceMiles = $this->haversineDistanceMiles($sourceLat, $sourceLng, $poiLat, $poiLng);
            $scoring       = $place['_ranking'] ?? [];

            $row = $this->createPoiRow(
                listingType:         $listingType,
                listingId:           $listingId,
                category:            'top_rated_dining',
                meta:                $topRatedMeta,
                sourceLat:           $sourceLat,
                sourceLng:           $sourceLng,
                rank:                $rank,
                status:              'found',
                error:               null,
                poiName:             $place['name'] ?? null,
                poiAddress:          $place['vicinity'] ?? null,
                poiLat:              $poiLat,
                poiLng:              $poiLng,
                distanceMiles:       $distanceMiles,
                rating:              isset($place['rating']) ? (float) $place['rating'] : null,
                userRatingsTotal:    isset($place['user_ratings_total']) ? (int) $place['user_ratings_total'] : null,
                typesJson:           $place['types'] ?? null,
                categoryMatchScore:  $scoring['category_match_score'] ?? null,
                consumerRelevance:   $scoring['consumer_relevance_score'] ?? null,
                reviewConfidence:    $scoring['review_confidence_score'] ?? null,
                rankingScore:        $scoring['ranking_score'] ?? null,
                rankingReasons:      $scoring['ranking_reasons_json'] ?? null,
            );

            $rows[] = $row->toArray();
        }

        return $rows;
    }

    /**
     * Create and persist a single POI row.
     *
     * Uses create() (not updateOrCreate) since the calling context guarantees existing
     * rows for this (listing_type, listing_id, poi_category) have already been deleted.
     */
    private function createPoiRow(
        string  $listingType,
        int     $listingId,
        string  $category,
        array   $meta,
        float   $sourceLat,
        float   $sourceLng,
        int     $rank,
        string  $status,
        ?string $error,
        ?string $poiName           = null,
        ?string $poiAddress        = null,
        ?float  $poiLat            = null,
        ?float  $poiLng            = null,
        ?float  $distanceMiles     = null,
        ?float  $rating            = null,
        ?int    $userRatingsTotal  = null,
        ?array  $typesJson         = null,
        ?float  $categoryMatchScore  = null,
        ?float  $consumerRelevance   = null,
        ?float  $reviewConfidence    = null,
        ?float  $rankingScore        = null,
        ?array  $rankingReasons      = null,
    ): PropertyLocationPoi {
        return PropertyLocationPoi::create([
            'listing_type'             => $listingType,
            'listing_id'               => $listingId,
            'poi_category'             => $category,
            'rank'                     => $rank,
            'poi_subtype'              => $meta['label'],
            'poi_name'                 => $poiName,
            'poi_address'              => $poiAddress,
            'poi_lat'                  => $poiLat,
            'poi_lng'                  => $poiLng,
            'source_lat'               => $sourceLat,
            'source_lng'               => $sourceLng,
            'distance_miles'           => $distanceMiles,
            'rating'                   => $rating,
            'user_ratings_total'       => $userRatingsTotal,
            'types_json'               => $typesJson,
            'category_match_score'     => $categoryMatchScore,
            'consumer_relevance_score' => $consumerRelevance,
            'review_confidence_score'  => $reviewConfidence,
            'ranking_score'            => $rankingScore,
            'ranking_reasons_json'     => $rankingReasons,
            'travel_time_minutes'      => null,
            'data_source'              => 'google_places',
            // Stage E0: stamp the versions this row was fetched/scored under.
            'pois_fetch_version'       => $this->currentFetchVersion !== '' ? $this->currentFetchVersion : null,
            'pois_scoring_version'     => $this->currentScoringVersion !== '' ? $this->currentScoringVersion : null,
            'status'                   => $status,
            'error'                    => $error,
            'calculated_at'            => now(),
        ]);
    }

    /**
     * Deduplicate transit candidates before exclusion filtering.
     *
     * Two stops are considered duplicates when:
     *   (a) They share an identical name AND their coordinates are within
     *       TRANSIT_DEDUP_DISTANCE_MILES (~100 m) of each other, OR
     *   (b) Their coordinates are within a coordinate epsilon of 0.00001 degrees
     *       (effectively the same physical point).
     *
     * The first occurrence (nearest, since results are ordered by distance) is kept;
     * subsequent duplicates are discarded. The result order is preserved.
     *
     * @param  array  $candidates  Raw Google Places result objects, ordered by distance.
     * @param  float  $sourceLat   Source property latitude (unused but kept for symmetry).
     * @param  float  $sourceLng   Source property longitude (unused but kept for symmetry).
     * @return array               Deduplicated candidate list, order preserved.
     */
    private function deduplicateTransitCandidates(array $candidates, float $sourceLat, float $sourceLng): array
    {
        $seen   = [];
        $result = [];

        foreach ($candidates as $place) {
            $name = trim((string) ($place['name'] ?? ''));
            $pLat = (float) ($place['geometry']['location']['lat'] ?? 0);
            $pLng = (float) ($place['geometry']['location']['lng'] ?? 0);

            $isDuplicate = false;

            foreach ($seen as $seenEntry) {
                // Coordinate epsilon check (same physical point)
                if (
                    abs($pLat - $seenEntry['lat']) < 0.00001 &&
                    abs($pLng - $seenEntry['lng']) < 0.00001
                ) {
                    $isDuplicate = true;
                    break;
                }

                // Same name + within 100 m
                if (
                    $name !== '' &&
                    $name === $seenEntry['name'] &&
                    $this->haversineDistanceMiles($pLat, $pLng, $seenEntry['lat'], $seenEntry['lng'])
                        <= self::TRANSIT_DEDUP_DISTANCE_MILES
                ) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (! $isDuplicate) {
                $seen[]   = ['name' => $name, 'lat' => $pLat, 'lng' => $pLng];
                $result[] = $place;
            }
        }

        return $result;
    }

    /**
     * Explicit allowlist enforcement for the hospital category.
     *
     * After the ranking engine scores and orders candidates, this method
     * re-partitions them so that any candidate carrying at least one legitimate
     * acute-care facility type appears BEFORE any specialist-only candidate.
     * Within each partition the ranking-engine order is preserved.
     *
     * Legitimate types: hospital, emergency_room, medical_center, urgent_care,
     * doctor, health.  A "specialist-only" candidate is any result that has
     * none of these types (e.g. ophthalmology, ENT, or aesthetics clinic).
     *
     * This guard is needed because a specialist clinic sitting 0.06 mi away
     * with 4.9★ / 719 reviews can outscore a real hospital 1.3 mi away under
     * the standard proximity + rating formula, even after name-pattern exclusion
     * has removed the most obvious offenders.
     *
     * @param  array $rankedCandidates  Candidates sorted by ranking_score descending.
     * @return array                    Same candidates — legitimate types first.
     */
    private function prioritizeLegitimateHospitalCandidates(array $rankedCandidates): array
    {
        $legitimateTypes = [
            'hospital',
            'emergency_room',
            'medical_center',
            'urgent_care',
            'doctor',
            'health',
        ];

        $legitimate    = [];
        $specialistOnly = [];

        foreach ($rankedCandidates as $candidate) {
            $types = $candidate['types'] ?? [];
            if (count(array_intersect($legitimateTypes, $types)) > 0) {
                $legitimate[] = $candidate;
            } else {
                $specialistOnly[] = $candidate;
            }
        }

        return array_merge($legitimate, $specialistOnly);
    }

    /**
     * Haversine formula — straight-line distance between two coordinates in miles.
     */
    private function haversineDistanceMiles(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // =========================================================================
    // Output shape helpers — approved Phase C eight-key contract in all cases
    // =========================================================================

    private function completedOutput(
        string $listingType,
        int    $listingId,
        float  $sourceLat,
        float  $sourceLng,
        array  $results,
        string $status,
    ): array {
        return [
            'success'      => true,
            'status'       => $status,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'results'      => $results,
            'error'        => null,
            'source_lat'   => $sourceLat,
            'source_lng'   => $sourceLng,
        ];
    }

    private function skippedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'skipped',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'results'      => [],
            'error'        => $error,
            'source_lat'   => null,
            'source_lng'   => null,
        ];
    }

    private function failedOutput(
        string  $listingType,
        int     $listingId,
        ?float  $sourceLat,
        ?float  $sourceLng,
        ?string $error,
    ): array {
        return [
            'success'      => false,
            'status'       => 'failed',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'results'      => [],
            'error'        => $error,
            'source_lat'   => $sourceLat,
            'source_lng'   => $sourceLng,
        ];
    }
}
