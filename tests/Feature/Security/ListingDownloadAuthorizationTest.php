<?php

namespace Tests\Feature\Security;

use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HI-01 — Listing-download IDOR.
 *
 * Before this batch, ListingDownloadController::authorizeView() only checked
 * auth()->check(), so any authenticated user could download any listing's
 * snapshot packet (which exposes the listing's private terms) by iterating the
 * {id} in the URL. The guard now also requires ownership
 * (auction.user_id === Auth::id()).
 *
 * (A) Deterministic route-middleware wiring — runs in every environment.
 * (B) DB-backed ownership checks — exercised against the isolated SQLite
 *     :memory: schema the TestCase migrates once per process.
 */
class ListingDownloadAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    /** role => [model class, url builder] for all four download formats. */
    private function formats(): array
    {
        return [
            'seller'   => [SellerAgentAuction::class,   fn ($id) => "/seller/listings/{$id}/download"],
            'buyer'    => [BuyerCriteriaAuction::class,  fn ($id) => "/buyer/listings/{$id}/download"],
            'landlord' => [LandlordAgentAuction::class,  fn ($id) => "/landlord/listings/{$id}/download"],
            'tenant'   => [TenantAgentAuction::class,    fn ($id) => "/tenant/listings/{$id}/download"],
        ];
    }

    /** Create a listing owned by $userId, supplying each table's required columns. */
    private function makeListing(string $model, int $userId)
    {
        $extra = $model === BuyerCriteriaAuction::class
            ? ['buyer_id' => 0, 'max_price' => 0, 'title' => 'Test Criteria']
            : [];

        return $model::forceCreate(array_merge(['user_id' => $userId], $extra));
    }

    // ── (A) route wiring ────────────────────────────────────────────────
    public function test_all_download_routes_require_auth(): void
    {
        foreach (['seller.listings.download', 'buyer.listings.download', 'landlord.listings.download', 'tenant.listings.download'] as $name) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} must exist");
            $this->assertContains('auth', $route->gatherMiddleware(), "Route {$name} must require auth");
        }
    }

    // ── (B) ownership ───────────────────────────────────────────────────
    public function test_unrelated_authenticated_user_is_denied_all_formats(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();

        foreach ($this->formats() as $role => [$model, $url]) {
            $listing = $this->makeListing($model, $owner->id);
            $this->actingAs($attacker)
                ->get($url($listing->id))
                ->assertForbidden();
        }
    }

    public function test_owner_may_download_all_formats(): void
    {
        $owner = User::factory()->create();

        foreach ($this->formats() as $role => [$model, $url]) {
            $listing = $this->makeListing($model, $owner->id);
            $this->actingAs($owner)
                ->get($url($listing->id))
                ->assertOk();
        }
    }

    public function test_guest_is_redirected_by_auth_middleware(): void
    {
        $owner   = User::factory()->create();
        $listing = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);

        $this->get("/seller/listings/{$listing->id}/download")
            ->assertRedirect();
    }
}
