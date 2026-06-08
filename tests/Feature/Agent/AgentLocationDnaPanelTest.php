<?php

namespace Tests\Feature\Agent;

use App\Jobs\ComputeLocationDna;
use App\Models\AcceptedBidSummary;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgentLocationDnaPanelTest
 *
 * Covers the Agent Location DNA Generate/Refresh button and POST route.
 *
 * Listing type contract (end-to-end):
 *   'seller_agent'   → SellerAgentAuction (seller_agent_auctions table)
 *   'landlord_agent' → LandlordAgentAuction (landlord_agent_auctions table)
 *
 * These types map directly to what the Livewire pages display and to what the
 * LocationDnaPipelineRunner resolves for address extraction. Buyer ('buyer') and
 * Tenant ('tenant') types are rejected with 404 at the controller level.
 *
 * Sections:
 *  §1  — Unauthenticated requests redirect to login
 *  §2  — Buyer listing type returns 404
 *  §3  — Tenant listing type returns 404
 *  §4  — Seller listing not found returns 404
 *  §5  — Seller listing owned by another agent returns 403
 *  §6  — Assigned agent (accepted_bid_summaries) can trigger generation
 *  §7  — Missing street address returns validation error; job not dispatched
 *  §8  — Complete address dispatches ComputeLocationDna with correct type+id
 *  §9  — Non-owner without accepted bid is denied (403)
 *  §10 — Panel partial: renders "Generate" button when no DNA record exists
 *  §11 — Panel partial: renders narrative + scores when DNA is generated
 *  §12 — Panel partial: renders POI names when POI records exist
 *  §13 — Landlord listing not found returns 404
 *  §14 — Landlord listing with complete address dispatches job correctly
 */
class AgentLocationDnaPanelTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(): User
    {
        return User::factory()->asAgent()->create();
    }

    /**
     * Create a seller_agent_auctions row for the given user and insert EAV meta
     * for the address fields the controller reads via SellerAgentAuction::info().
     *
     * Pass empty string in $metaOverrides to simulate a missing field.
     * Omitting a key from $metaOverrides leaves that meta row absent.
     */
    private function makeSellerListing(int $userId, array $metaOverrides = []): int
    {
        $id = DB::table('seller_agent_auctions')->insertGetId([
            'user_id'    => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $meta = array_merge([
            'address'        => '123 Main St',
            'property_city'  => 'Tampa',
            'property_state' => 'FL',
        ], $metaOverrides);

        foreach ($meta as $key => $value) {
            DB::table('seller_agent_auction_metas')->insert([
                'seller_agent_auction_id' => $id,
                'meta_key'                => $key,
                'meta_value'              => $value,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        return $id;
    }

    /**
     * Create a landlord_agent_auctions row and EAV meta for address fields.
     */
    private function makeLandlordListing(int $userId, array $metaOverrides = []): int
    {
        $id = DB::table('landlord_agent_auctions')->insertGetId([
            'user_id'    => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $meta = array_merge([
            'address'        => '456 Oak Ave',
            'property_city'  => 'Orlando',
            'property_state' => 'FL',
        ], $metaOverrides);

        foreach ($meta as $key => $value) {
            DB::table('landlord_agent_auction_metas')->insert([
                'landlord_agent_auction_id' => $id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);
        }

        return $id;
    }

    private function insertAcceptedBid(string $listingType, int $listingId, int $agentUserId, int $clientUserId): void
    {
        DB::table('accepted_bid_summaries')->insert([
            'listing_type'    => $listingType,
            'listing_id'      => $listingId,
            'accepted_bid_id' => 0,
            'tenant_user_id'  => $clientUserId,
            'agent_user_id'   => $agentUserId,
            'summary_html'    => '<p>Test summary</p>',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function insertLocationDna(string $listingType, int $listingId): PropertyLocationDna
    {
        return PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '123 Main St, Tampa, FL',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'google_geocoding_api',
            'geocode_status' => 'geocoded',
            'summary_json'   => [
                'geocode' => ['lat' => 27.9506, 'lng' => -82.4572],
            ],
            'lifestyle_json' => [
                'version'              => 'LDNA_LIFESTYLE_V1',
                'coastal_score'        => 85,
                'walkability_score'    => 70,
                'convenience_score'    => 60,
                'commuter_score'       => 50,
                'family_score'         => 45,
                'lifestyle_categories' => ['Beach Lovers', 'Remote Workers'],
                'location_narrative'   => 'A vibrant coastal community with excellent walkability.',
            ],
            'generated_at' => now(),
        ]);
    }

    private function insertPoi(string $listingType, int $listingId, string $category, string $name, float $distance): PropertyLocationPoi
    {
        return PropertyLocationPoi::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'poi_category'   => $category,
            'poi_subtype'    => $category,
            'poi_name'       => $name,
            'poi_address'    => '1 Test Blvd, Tampa',
            'poi_lat'        => 27.9600,
            'poi_lng'        => -82.4600,
            'source_lat'     => 27.9506,
            'source_lng'     => -82.4572,
            'distance_miles' => $distance,
            'data_source'    => 'google_places',
            'status'         => 'found',
            'calculated_at'  => now(),
        ]);
    }

    private function generateRoute(string $listingType, int $listingId): string
    {
        return route('agent.location-dna.generate', [$listingType, $listingId]);
    }

    // =========================================================================
    // §1 — Unauthenticated requests redirect to login
    // =========================================================================

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post($this->generateRoute('seller_agent', 999));
        $response->assertRedirect(route('login'));
    }

    // =========================================================================
    // §2 — Buyer listing type returns 404
    // =========================================================================

    public function test_buyer_listing_type_returns_404(): void
    {
        $agent    = $this->makeAgent();
        $response = $this->actingAs($agent)->post($this->generateRoute('buyer', 1));
        $response->assertStatus(404);
    }

    // =========================================================================
    // §3 — Tenant listing type returns 404
    // =========================================================================

    public function test_tenant_listing_type_returns_404(): void
    {
        $agent    = $this->makeAgent();
        $response = $this->actingAs($agent)->post($this->generateRoute('tenant', 1));
        $response->assertStatus(404);
    }

    // =========================================================================
    // §4 — Seller listing not found returns 404
    // =========================================================================

    public function test_seller_listing_not_found_returns_404(): void
    {
        $agent    = $this->makeAgent();
        $response = $this->actingAs($agent)->post($this->generateRoute('seller_agent', 999999));
        $response->assertStatus(404);
    }

    // =========================================================================
    // §5 — Seller listing owned by another agent returns 403
    // =========================================================================

    public function test_seller_listing_owned_by_another_returns_403(): void
    {
        $owner     = $this->makeAgent();
        $other     = $this->makeAgent();
        $listingId = $this->makeSellerListing($owner->id);

        $response = $this->actingAs($other)->post($this->generateRoute('seller_agent', $listingId));
        $response->assertStatus(403);
    }

    // =========================================================================
    // §6 — Assigned agent (via accepted_bid_summaries) can trigger generation
    //
    // The AcceptedBidSummary listing_type must match the controller's type
    // ('seller_agent') for the bypass to work.
    // =========================================================================

    public function test_assigned_agent_with_accepted_bid_can_trigger_generation(): void
    {
        Bus::fake();

        $owner         = $this->makeAgent();
        $assignedAgent = $this->makeAgent();
        $listingId     = $this->makeSellerListing($owner->id);

        $this->insertAcceptedBid('seller_agent', $listingId, $assignedAgent->id, $owner->id);

        $response = $this->actingAs($assignedAgent)
            ->from(route('dashboard'))
            ->post($this->generateRoute('seller_agent', $listingId));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('dna_success');
        Bus::assertDispatched(ComputeLocationDna::class);
    }

    // =========================================================================
    // §7 — Missing street address blocks generation
    // =========================================================================

    public function test_seller_listing_with_missing_address_returns_error(): void
    {
        Bus::fake();

        $agent     = $this->makeAgent();
        $listingId = $this->makeSellerListing($agent->id, ['address' => '']);

        $response = $this->actingAs($agent)
            ->from(route('dashboard'))
            ->post($this->generateRoute('seller_agent', $listingId));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('address');
        Bus::assertNothingDispatched();
    }

    // =========================================================================
    // §8 — Seller listing with complete address dispatches ComputeLocationDna
    //
    // Asserts the dispatched job carries listing_type='seller_agent' and the
    // SellerAgentAuction ID — the same values the Livewire page and the
    // pipeline runner use — so there is no cross-namespace ID collision.
    // =========================================================================

    public function test_seller_listing_with_complete_address_dispatches_job(): void
    {
        Bus::fake();

        $agent     = $this->makeAgent();
        $listingId = $this->makeSellerListing($agent->id);

        $response = $this->actingAs($agent)
            ->from(route('dashboard'))
            ->post($this->generateRoute('seller_agent', $listingId));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('dna_success');
        Bus::assertDispatched(ComputeLocationDna::class, function ($job) use ($listingId) {
            return $job->listingType === 'seller_agent' && $job->listingId === $listingId;
        });
    }

    // =========================================================================
    // §9 — Non-owner without accepted bid is denied (403)
    //
    // Verifies via HTTP that agent B cannot trigger generation for a listing
    // owned by agent A when no AcceptedBidSummary links them.
    // =========================================================================

    public function test_non_owner_without_accepted_bid_cannot_view_panel(): void
    {
        $owner = $this->makeAgent();
        $other = $this->makeAgent();

        $listingId = $this->makeSellerListing($owner->id);

        $isOwner = SellerAgentAuction::where('id', $listingId)
            ->where('user_id', $other->id)
            ->exists();

        $isAssigned = AcceptedBidSummary::where('listing_type', 'seller_agent')
            ->where('listing_id', $listingId)
            ->where('agent_user_id', $other->id)
            ->exists();

        $this->assertFalse($isOwner,    'Non-owner should not own the listing');
        $this->assertFalse($isAssigned, 'Non-owner should not have an accepted bid');

        $response = $this->actingAs($other)->post($this->generateRoute('seller_agent', $listingId));
        $response->assertStatus(403);
    }

    // =========================================================================
    // §10 — Panel partial: renders "Generate" button when no DNA record exists
    // =========================================================================

    public function test_panel_partial_renders_generate_button_when_no_dna(): void
    {
        $agent     = $this->makeAgent();
        $listingId = $this->makeSellerListing($agent->id);

        $html = view('partials.location-dna-agent-panel', [
            'listingType'            => 'seller_agent',
            'listingId'              => $listingId,
            'locationDna'            => null,
            'locationPois'           => collect(),
            'canGenerateLocationDna' => true,
        ])->render();

        $this->assertStringContainsString('Generate Location DNA', $html);
        $this->assertStringContainsString('Not Generated', $html);
    }

    // =========================================================================
    // §11 — Panel partial: renders narrative + scores when DNA is generated
    // =========================================================================

    public function test_panel_partial_renders_narrative_and_scores_when_generated(): void
    {
        $agent     = $this->makeAgent();
        $listingId = $this->makeSellerListing($agent->id);
        $dna       = $this->insertLocationDna('seller_agent', $listingId);

        $html = view('partials.location-dna-agent-panel', [
            'listingType'            => 'seller_agent',
            'listingId'              => $listingId,
            'locationDna'            => $dna,
            'locationPois'           => collect(),
            'canGenerateLocationDna' => true,
        ])->render();

        $this->assertStringContainsString('A vibrant coastal community with excellent walkability.', $html);
        $this->assertStringContainsString('Refresh Location DNA', $html);
        $this->assertStringContainsString('Walkability', $html);
        $this->assertStringContainsString('Coastal', $html);
    }

    // =========================================================================
    // §12 — Panel partial: renders POI names when POI records exist
    //
    // property_location_pois has a unique constraint on (listing_type, listing_id,
    // poi_category) so each POI uses a distinct category.
    // =========================================================================

    public function test_panel_partial_renders_top_pois(): void
    {
        $agent     = $this->makeAgent();
        $listingId = $this->makeSellerListing($agent->id);
        $dna       = $this->insertLocationDna('seller_agent', $listingId);

        $pois = collect([
            $this->insertPoi('seller_agent', $listingId, 'grocery',  'Whole Foods Market', 0.4),
            $this->insertPoi('seller_agent', $listingId, 'park',     'Curtis Hixon Park',  0.5),
            $this->insertPoi('seller_agent', $listingId, 'pharmacy', 'CVS Pharmacy',       0.7),
            $this->insertPoi('seller_agent', $listingId, 'school',   'Tampa Academy',      0.9),
        ]);

        $html = view('partials.location-dna-agent-panel', [
            'listingType'            => 'seller_agent',
            'listingId'              => $listingId,
            'locationDna'            => $dna,
            'locationPois'           => $pois,
            'canGenerateLocationDna' => true,
        ])->render();

        $this->assertStringContainsString('Whole Foods Market', $html);
        $this->assertStringContainsString('Curtis Hixon Park', $html);
        $this->assertStringContainsString('CVS Pharmacy', $html);
        $this->assertStringContainsString('Tampa Academy', $html);
    }

    // =========================================================================
    // §13 — Landlord listing not found returns 404
    // =========================================================================

    public function test_landlord_listing_not_found_returns_404(): void
    {
        $agent    = $this->makeAgent();
        $response = $this->actingAs($agent)->post($this->generateRoute('landlord_agent', 999999));
        $response->assertStatus(404);
    }

    // =========================================================================
    // §14 — Landlord listing with complete address dispatches job correctly
    //
    // Mirrors the seller dispatch test for the landlord_agent path. Confirms
    // the job carries listing_type='landlord_agent' and the LandlordAgentAuction
    // ID — consistent with the pipeline runner's 'landlord_agent' branch.
    // =========================================================================

    public function test_landlord_listing_with_complete_address_dispatches_job(): void
    {
        Bus::fake();

        $agent     = $this->makeAgent();
        $listingId = $this->makeLandlordListing($agent->id);

        $response = $this->actingAs($agent)
            ->from(route('dashboard'))
            ->post($this->generateRoute('landlord_agent', $listingId));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('dna_success');
        Bus::assertDispatched(ComputeLocationDna::class, function ($job) use ($listingId) {
            return $job->listingType === 'landlord_agent' && $job->listingId === $listingId;
        });
    }
}
