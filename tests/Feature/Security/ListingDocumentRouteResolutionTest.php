<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HI-05A — the document-delivery routes accept `seller` and `landlord` listing
 * types (and nothing else). This asserts routing resolution only: an accepted
 * type reaches the controller (non-404 routing — authorization/existence may
 * still 403/404 downstream), while an unsupported type 404s at the router.
 */
class ListingDocumentRouteResolutionTest extends TestCase
{
    use DatabaseTransactions;

    /** A response whose 404 (if any) came from the ROUTER, not the controller. */
    private function assertRoutedButNotFound($response): void
    {
        // Router-rejected types never reach the controller, so the response is a
        // plain 404 with no controller body. We assert the status is 404 here only
        // for the negative cases; positive cases assert non-404.
        $response->assertNotFound();
    }

    public function test_seller_document_route_still_resolves(): void
    {
        $user = User::factory()->create();
        // Non-existent seller listing → controller runs and denies (403), i.e. the
        // route RESOLVED (not a routing 404).
        $this->actingAs($user)
            ->get('/listings/seller/1/document/seller_disclosure_file')
            ->assertStatus(403);
    }

    public function test_landlord_document_route_now_resolves(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/listings/landlord/1/document/landlord_disclosure_file')
            ->assertStatus(403); // reached controller → denied (listing absent), not a routing 404
    }

    public function test_seller_additional_route_still_resolves(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/listings/seller/1/additional-document/0')
            ->assertStatus(403);
    }

    public function test_landlord_additional_route_now_resolves(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/listings/landlord/1/additional-document/0')
            ->assertStatus(403);
    }

    public function test_unsupported_listing_type_document_route_is_404(): void
    {
        $user = User::factory()->create();
        foreach (['buyer', 'tenant', 'seller_offer', 'admin', 'x'] as $type) {
            $this->actingAs($user)
                ->get("/listings/{$type}/1/document/seller_disclosure_file")
                ->assertNotFound();
        }
    }

    public function test_unsupported_listing_type_additional_route_is_404(): void
    {
        $user = User::factory()->create();
        foreach (['buyer', 'tenant', 'landlord_offer', 'x'] as $type) {
            $this->actingAs($user)
                ->get("/listings/{$type}/1/additional-document/0")
                ->assertNotFound();
        }
    }

    public function test_non_numeric_listing_id_and_index_still_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/listings/landlord/abc/document/landlord_disclosure_file')->assertNotFound();
        $this->actingAs($user)->get('/listings/landlord/1/additional-document/xyz')->assertNotFound();
    }
}
