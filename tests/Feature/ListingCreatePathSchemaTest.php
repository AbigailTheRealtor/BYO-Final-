<?php

namespace Tests\Feature;

use App\Models\BuyerCriteriaAuction;
use App\Models\PropertyAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production create-path coverage for three listing types whose schema had drifted
 * away from the model that writes it.
 *
 * These are NOT authorization tests and deliberately live outside
 * tests/Feature/Security — they assert that a *legitimate* create persists, which
 * is the half no ownership test can see. Each of the three defects below was
 * invisible in the security suite precisely because the row could never be
 * inserted in the first place.
 *
 *   property_auctions.is_draft          — PropertyAuction::$attributes always emits
 *                                         is_draft, but the column was never created.
 *   tenant_criteria_auctions.listing_id — HasListingId always assigns listing_id, but
 *                                         the create migration ran after the backfill
 *                                         migration's hasTable() guard had already
 *                                         skipped this table.
 *   buyer_criteria_auctions             — buyer_id / max_price / title are NOT NULL
 *                                         with no default and storeAuction never set
 *                                         them.
 *
 * Asserting through the model and the HTTP route rather than Schema::hasColumn():
 * a column can exist and the write still fail (wrong type, missing default, a NOT
 * NULL sibling), so presence alone would not have caught any of these.
 */
class ListingCreatePathSchemaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // CSRF is irrelevant to persistence assertions.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /** Mirrors the guard used across the security suite for this workspace's harness. */
    private function requireIsolatedDb(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Isolated SQLite test DB unavailable in this environment.');
        }
    }

    public function test_property_auction_legitimate_create_persists_with_is_draft(): void
    {
        $this->requireIsolatedDb();
        $owner = User::factory()->create();

        $auction = new PropertyAuction();
        $auction->user_id      = $owner->id;
        $auction->title        = 'Test Property Listing';
        $auction->address      = '123 Test Street';
        $auction->city_id      = 1;
        $auction->state_id     = 1;
        // auction_type is a pre-existing NOT NULL column with no default
        // (2025_02_06_062653); unrelated to is_draft, but the insert needs it.
        $auction->auction_type = 'Normal';
        $auction->save();

        $this->assertTrue($auction->exists, 'PropertyAuction create must persist');

        // Read the column back from the database, not the in-memory model: the
        // model would happily report its $attributes default even if the write
        // had silently dropped the column.
        $row = DB::table('property_auctions')->where('id', $auction->id)->first();
        $this->assertNotNull($row, 'PropertyAuction row must exist after save');
        $this->assertSame(0, (int) $row->is_draft, 'A new PropertyAuction defaults to not-a-draft');

        // The same insert also carries listing_id via HasListingId; if the column
        // set were wrong this is the other half that would fail.
        $this->assertNotEmpty($row->listing_id, 'PropertyAuction must receive a generated listing_id');
        $this->assertStringStartsWith('PA-', $row->listing_id);
    }

    public function test_tenant_criteria_auction_legitimate_create_persists_with_listing_id(): void
    {
        $this->requireIsolatedDb();
        $owner = User::factory()->create();

        $auction = new TenantCriteriaAuction();
        $auction->user_id = $owner->id;
        $auction->save();

        $this->assertTrue($auction->exists, 'TenantCriteriaAuction create must persist');

        $row = DB::table('tenant_criteria_auctions')->where('id', $auction->id)->first();
        $this->assertNotNull($row, 'TenantCriteriaAuction row must exist after save');
        $this->assertNotEmpty($row->listing_id, 'HasListingId must persist a listing_id');
        $this->assertStringStartsWith('TCA-', $row->listing_id, 'Prefix comes from HasListingId::getListingIdPrefix()');

        // listing_id is unique across the table; prove the generated value is real
        // rather than an empty string that would collide on the very next insert.
        $second = new TenantCriteriaAuction();
        $second->user_id = $owner->id;
        $second->save();
        $this->assertNotSame(
            $row->listing_id,
            DB::table('tenant_criteria_auctions')->where('id', $second->id)->value('listing_id'),
            'Each TenantCriteriaAuction must receive a distinct listing_id'
        );
    }

    public function test_buyer_criteria_store_route_persists_listing(): void
    {
        $this->requireIsolatedDb();

        // The create route sits behind auth + verified + AgentAuth.
        $agent = User::factory()->asAgent()->create();

        $before = BuyerCriteriaAuction::where('user_id', $agent->id)->count();

        $response = $this->actingAs($agent)->post('buyer-agent/auction/add', [
            'auction_type'   => 'Normal',
            'auction_length' => '30 days',
            'titleListing'   => 'Test Buyer Criteria',
            'max_price'      => 450000,
        ]);

        // storeAuction swallows every \Exception and — via a pre-existing
        // `return $e->getMessage();` — answers with a bare string body and HTTP 200
        // instead of redirecting. So a failed insert does NOT look like an error to
        // a status-code assertion; it looks like a 200. Pin the success branch by
        // its exact redirect target, which only the post-DB::commit() path produces.
        // (Flash-session assertions are not usable here: SESSION_DRIVER=array in
        // phpunit.xml, so the flash is not observable after the response.)
        $auction = BuyerCriteriaAuction::where('user_id', $agent->id)->latest('id')->first();
        $this->assertNotNull($auction, 'A legitimate Buyer Criteria create must persist a row');
        $response->assertRedirect(route('buyer.criteria.view', $auction->id));

        $this->assertSame(
            $before + 1,
            BuyerCriteriaAuction::where('user_id', $agent->id)->count(),
            'Exactly one Buyer Criteria row must be added'
        );

        // The three NOT NULL columns the schema has always declared.
        $row = DB::table('buyer_criteria_auctions')->where('id', $auction->id)->first();
        $this->assertNotNull($row->buyer_id, 'buyer_id is NOT NULL and must be populated');
        $this->assertNotNull($row->title, 'title is NOT NULL and must be populated');
        $this->assertNotNull($row->max_price, 'max_price is NOT NULL and must be populated');

        // The title the form supplied is what should land in the native column.
        $this->assertSame('Test Buyer Criteria', $row->title);
    }
}
