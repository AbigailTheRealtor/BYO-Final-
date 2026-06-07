<?php

namespace Tests\Feature;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LocationDnaAdminPreviewTest
 *
 * Covers the Location DNA admin read-only inspector layer:
 *  §1 — Index page renders listing_type, listing_id, and generated_at columns
 *  §2 — Location DNA card renders summary, lifestyle scores, and POIs when data is present
 *  §3 — "Has not been generated" message renders when no Location DNA record exists
 *  §4 — POI list renders correctly grouped by category
 *  §5 — Show page renders the "not generated" alert when no record exists for the given type/id
 */
class LocationDnaAdminPreviewTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        return $user;
    }

    private function makeSellerListingId(): int
    {
        $stateId = $this->ensureState('FL', 'Florida');
        $cityId  = $this->ensureCity('Tampa', $stateId);

        return DB::table('property_auctions')->insertGetId([
            'user_id'      => User::factory()->create()->id,
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'title'        => 'Location DNA Admin Test Listing',
            'address'      => '456 Test Ave',
            'city_id'      => $cityId,
            'state_id'     => $stateId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function insertLocationDna(string $listingType, int $listingId): PropertyLocationDna
    {
        $lifestyleJson = [
            'version'               => 'LDNA_LIFESTYLE_V1',
            'coastal_score'         => 85,
            'walkability_score'     => 70,
            'convenience_score'     => 60,
            'commuter_score'        => 50,
            'family_score'          => 45,
            'lifestyle_categories'  => ['Beach Lovers', 'Remote Workers'],
            'location_narrative'    => 'This location offers exceptional coastal access with beaches and waterways nearby.',
        ];

        $summaryJson = [
            'geocode' => [
                'lat'        => 27.9506,
                'lng'        => -82.4572,
                'source'     => 'google_geocoding_api',
                'geocoded_at' => now()->toIso8601String(),
            ],
            'category_counts' => [
                'total_categories' => 2,
                'found'            => 2,
                'not_found'        => 0,
                'error'            => 0,
            ],
        ];

        return PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '456 Test Ave',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'google_geocoding_api',
            'geocode_status' => 'geocoded',
            'summary_json'   => $summaryJson,
            'lifestyle_json' => $lifestyleJson,
            'generated_at'   => now(),
        ]);
    }

    private function insertPoi(string $listingType, int $listingId, string $category, string $name, float $distance): PropertyLocationPoi
    {
        return PropertyLocationPoi::create([
            'listing_type'     => $listingType,
            'listing_id'       => $listingId,
            'poi_category'     => $category,
            'poi_subtype'      => $category,
            'poi_name'         => $name,
            'poi_address'      => '1 Test Blvd, Tampa',
            'poi_lat'          => 27.9600,
            'poi_lng'          => -82.4600,
            'source_lat'       => 27.9506,
            'source_lng'       => -82.4572,
            'distance_miles'   => $distance,
            'data_source'      => 'google_places',
            'status'           => 'found',
            'calculated_at'    => now(),
        ]);
    }

    // =========================================================================
    // §1 — Index page renders listing_type, listing_id, and generated_at
    // =========================================================================

    public function test_location_index_renders_records(): void
    {
        $admin     = $this->makeAdmin();
        $listingId = $this->makeSellerListingId();
        $this->insertLocationDna('seller', $listingId);

        $response = $this->actingAs($admin)
            ->get(route('admin.dna.location.index'));

        $response->assertOk();
        $response->assertSee('seller');
        $response->assertSee((string) $listingId);
    }

    // =========================================================================
    // §2 — Card renders summary, scores, and POIs when data is present
    // =========================================================================

    public function test_location_show_renders_scores_and_narrative_when_data_exists(): void
    {
        $admin     = $this->makeAdmin();
        $listingId = $this->makeSellerListingId();
        $this->insertLocationDna('seller', $listingId);
        $this->insertPoi('seller', $listingId, 'beach', 'Clearwater Beach', 0.3);

        $response = $this->actingAs($admin)
            ->get(route('admin.dna.location.show', ['seller', $listingId]));

        $response->assertOk();
        $response->assertSee('exceptional coastal access');
        $response->assertSee('Beach Lovers');
        $response->assertSee('Remote Workers');
        $response->assertSee('Coastal');
        $response->assertSee('Walkability');
    }

    // =========================================================================
    // §3 — "Has not been generated" message renders when no record exists
    // =========================================================================

    public function test_location_show_renders_not_generated_message_when_no_record(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.dna.location.show', ['seller', 999999]));

        $response->assertOk();
        $response->assertSee('Location DNA has not been generated.');
    }

    // =========================================================================
    // §4 — POIs render correctly grouped by category
    // =========================================================================

    public function test_pois_render_grouped_by_category(): void
    {
        $admin     = $this->makeAdmin();
        $listingId = $this->makeSellerListingId();
        $this->insertLocationDna('seller', $listingId);
        $this->insertPoi('seller', $listingId, 'grocery_store', 'Publix Supermarket', 0.5);
        $this->insertPoi('seller', $listingId, 'pharmacy', 'CVS Pharmacy', 0.7);
        $this->insertPoi('seller', $listingId, 'park', 'Curtis Hixon Park', 0.8);

        $response = $this->actingAs($admin)
            ->get(route('admin.dna.location.show', ['seller', $listingId]));

        $response->assertOk();
        $response->assertSee('Publix Supermarket');
        $response->assertSee('CVS Pharmacy');
        $response->assertSee('Curtis Hixon Park');
        $response->assertSee('grocery store');
        $response->assertSee('park');
    }

    // =========================================================================
    // §5 — Show page for seller DNA profile also renders location DNA card
    // =========================================================================

    public function test_seller_dna_profile_page_shows_location_dna_card(): void
    {
        $admin     = $this->makeAdmin();
        $listingId = $this->makeSellerListingId();
        $this->insertLocationDna('seller', $listingId);

        $response = $this->actingAs($admin)
            ->get(route('admin.dna.profiles.seller', $listingId));

        $response->assertOk();
        $response->assertSee('Location DNA');
        $response->assertSee('exceptional coastal access');
    }

    // =========================================================================
    // §6 — Seller profile page shows "not generated" when location DNA absent
    // =========================================================================

    public function test_seller_dna_profile_page_shows_not_generated_when_no_location_dna(): void
    {
        $admin     = $this->makeAdmin();
        $listingId = $this->makeSellerListingId();

        $response = $this->actingAs($admin)
            ->get(route('admin.dna.profiles.seller', $listingId));

        $response->assertOk();
        $response->assertSee('Location DNA has not been generated.');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function ensureState(string $abbreviation, string $name): int
    {
        $existing = DB::table('us_states')->where('abbreviation', $abbreviation)->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('us_states')->insertGetId([
            'name'         => $name,
            'abbreviation' => $abbreviation,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function ensureCity(string $name, int $stateId): int
    {
        $existing = DB::table('us_cities')
            ->where('name', $name)
            ->where('state_id', $stateId)
            ->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('us_cities')->insertGetId([
            'name'       => $name,
            'state_id'   => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
